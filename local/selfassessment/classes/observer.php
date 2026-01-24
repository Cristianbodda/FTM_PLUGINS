<?php
// ============================================
// Self Assessment - Event Observer
// ============================================
// Quando uno studente completa un quiz,
// assegna automaticamente le competenze associate
// ============================================

namespace local_selfassessment;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    /**
     * Gestisce l'evento quiz_attempt_submitted
     * Assegna automaticamente le competenze del quiz per l'autovalutazione
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        $userid = $event->relateduserid;
        $quizid = $event->other['quizid'] ?? null;
        $attemptid = $event->objectid;
        
        if (!$userid || !$quizid) {
            return;
        }
        
        // Trova le domande del quiz attempt
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return;
        }
        
        // Ottieni le domande dal quiz attempt
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);
        
        if (empty($questions)) {
            return;
        }
        
        $questionids = array_keys($questions);
        
        // Cerca tabella competenze-domande
        $comp_tables = [
            'qbank_competenciesbyquestion' => 'questionid',
            'qbank_comp_question' => 'questionid',
            'local_competencymanager_qcomp' => 'questionid'
        ];
        
        $comp_question_table = null;
        $comp_question_field = null;
        
        foreach ($comp_tables as $table => $field) {
            if ($DB->get_manager()->table_exists($table)) {
                $comp_question_table = $table;
                $comp_question_field = $field;
                break;
            }
        }
        
        if (!$comp_question_table) {
            // Nessuna tabella di mapping trovata
            return;
        }
        
        // Trova competenze associate alle domande
        list($sql_in, $params) = $DB->get_in_or_equal($questionids);
        $mappings = $DB->get_records_sql("
            SELECT DISTINCT competencyid 
            FROM {{$comp_question_table}} 
            WHERE {$comp_question_field} $sql_in
        ", $params);
        
        if (empty($mappings)) {
            return;
        }
        
        // Get student's primary sector (if set).
        $primarySector = self::get_student_primary_sector($userid);

        // Assegna ogni competenza per l'autovalutazione
        $now = time();
        $assigned = 0;

        foreach ($mappings as $mapping) {
            $competencyid = $mapping->competencyid;

            // If student has a primary sector, only assign competencies from that sector.
            if (!empty($primarySector)) {
                $competencySector = self::get_competency_sector($competencyid);
                if (!empty($competencySector) && $competencySector !== $primarySector) {
                    // Skip competencies from non-primary sectors.
                    debugging("SelfAssessment: Skipping competency $competencyid (sector $competencySector) - not primary sector ($primarySector)", DEBUG_DEVELOPER);
                    continue;
                }
            }

            // Verifica se già assegnata
            $exists = $DB->record_exists('local_selfassessment_assign', [
                'userid' => $userid,
                'competencyid' => $competencyid
            ]);

            if (!$exists) {
                $record = new \stdClass();
                $record->userid = $userid;
                $record->competencyid = $competencyid;
                $record->source = 'quiz';
                $record->sourceid = $quizid;
                $record->timecreated = $now;

                try {
                    $DB->insert_record('local_selfassessment_assign', $record);
                    $assigned++;
                } catch (\Exception $e) {
                    // Ignora errori (es. duplicati)
                }
            }
        }
        
        // Invia notifica allo studente se ci sono nuove assegnazioni
        if ($assigned > 0) {
            self::send_assignment_notification($userid, $quizid, $assigned);
            debugging("SelfAssessment: Assigned $assigned competencies to user $userid from quiz $quizid", DEBUG_DEVELOPER);
        }

        // Rileva e registra i settori dalle competenze del quiz (opzionale)
        self::detect_sectors_safe($userid, $quizid, $mappings);
    }

    /**
     * Get student's primary sector from local_student_sectors.
     *
     * @param int $userid User ID.
     * @return string|null Primary sector code or null if not set.
     */
    private static function get_student_primary_sector($userid) {
        global $DB;

        // Check if table exists.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            return null;
        }

        // Get primary sector.
        $record = $DB->get_record('local_student_sectors', [
            'userid' => $userid,
            'is_primary' => 1
        ]);

        return $record ? $record->sector : null;
    }

    /**
     * Get sector from competency idnumber.
     *
     * @param int $competencyid Competency ID.
     * @return string|null Sector code or null.
     */
    private static function get_competency_sector($competencyid) {
        global $DB;

        // Get competency idnumber.
        $competency = $DB->get_record('competency', ['id' => $competencyid]);
        if (!$competency || empty($competency->idnumber)) {
            return null;
        }

        // Extract sector from idnumber (e.g., "LOGISTICA_LO_A1" → "LOGISTICA").
        $parts = explode('_', $competency->idnumber);
        if (!empty($parts[0])) {
            // Normalize encoding.
            $sector = strtoupper($parts[0]);
            $sector = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], $sector);
            return $sector;
        }

        return null;
    }

    /**
     * Rileva settori in modo sicuro (non blocca se sector_manager non esiste)
     */
    private static function detect_sectors_safe($userid, $quizid, $mappings) {
        global $DB;

        // Verifica se il file sector_manager esiste
        $sector_manager_file = __DIR__ . '/../../competencymanager/classes/sector_manager.php';
        if (!file_exists($sector_manager_file)) {
            return; // Sector manager non installato, skip silenzioso
        }

        // Verifica se la tabella esiste
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            return; // Tabella non creata, skip silenzioso
        }

        try {
            require_once($sector_manager_file);
            $competencyids = array_map(function($m) { return $m->competencyid; }, $mappings);
            $detected_sectors = \local_competencymanager\sector_manager::detect_sectors_from_quiz($userid, $quizid, $competencyids);
            if (!empty($detected_sectors)) {
                debugging("SelfAssessment: Detected sectors for user $userid: " . implode(', ', $detected_sectors), DEBUG_DEVELOPER);
            }
        } catch (\Exception $e) {
            // Errore silenzioso - non blocca il quiz
            debugging("SelfAssessment: Error detecting sectors: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Invia notifica allo studente quando vengono assegnate nuove competenze
     */
    private static function send_assignment_notification($userid, $quizid, $count) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/lib/messagelib.php');

        $student = $DB->get_record('user', ['id' => $userid]);
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);

        if (!$student || !$quiz) {
            return false;
        }

        // Prepara dati per il messaggio
        $url = new \moodle_url('/local/selfassessment/compile.php');

        $messagedata = new \stdClass();
        $messagedata->fullname = fullname($student);
        $messagedata->quizname = $quiz->name;
        $messagedata->count = $count;
        $messagedata->url = $url->out();

        // Crea messaggio Moodle
        $message = new \core\message\message();
        $message->component = 'local_selfassessment';
        $message->name = 'assignment';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $student;
        $message->subject = get_string('notification_assignment_subject', 'local_selfassessment');
        $message->fullmessage = get_string('notification_assignment_body', 'local_selfassessment', $messagedata);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = get_string('notification_assignment_small', 'local_selfassessment', $messagedata);
        $message->notification = 1;
        $message->contexturl = $url;
        $message->contexturlname = get_string('myassessment', 'local_selfassessment');

        try {
            message_send($message);
            return true;
        } catch (\Exception $e) {
            debugging("SelfAssessment: Failed to send notification to user $userid: " . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
