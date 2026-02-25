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

    /** @var string|null Cached competency-question mapping table name */
    private static $comp_table_cache = null;
    /** @var bool Whether we already checked for the table */
    private static $comp_table_checked = false;

    /**
     * Gestisce l'evento quiz_attempt_submitted
     * Assegna automaticamente le competenze del quiz per l'autovalutazione
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        try {
            $userid = $event->relateduserid;
            $attemptid = $event->objectid;

            if (!$userid || !$attemptid) {
                return;
            }

            // Trova l'attempt nel database
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
            if (!$attempt) {
                debugging("selfassessment observer: attempt $attemptid not found", DEBUG_DEVELOPER);
                return;
            }

            $quizid = $attempt->quiz;
            if (!$quizid) {
                return;
            }

            // Assegna competenze dal quiz
            $assigned = self::assign_competencies_from_attempt($userid, $attempt);

            // Invia notifica e redirect se ci sono nuove assegnazioni
            if ($assigned > 0) {
                self::send_assignment_notification($userid, $quizid, $assigned);
                self::show_success_message($userid, $assigned);
                self::set_redirect_flag($userid);
            }

            // Rileva settori (silenzioso)
            self::detect_sectors_from_attempt($userid, $attempt);

        } catch (\Exception $e) {
            // Non bloccare mai il quiz per errori del selfassessment
            debugging("selfassessment observer error: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Assegna competenze da un singolo quiz attempt.
     * Usabile sia dall'observer che dal retroactive assignment.
     *
     * @param int $userid User ID
     * @param object $attempt Quiz attempt record
     * @return int Number of newly assigned competencies
     */
    public static function assign_competencies_from_attempt($userid, $attempt) {
        global $DB;

        $quizid = $attempt->quiz;

        // Ottieni le domande dal quiz attempt
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);

        if (empty($questions)) {
            return 0;
        }

        $questionids = array_keys($questions);

        // Trova la tabella di mapping competenze-domande
        $comp_table = self::find_competency_table();
        if (!$comp_table) {
            return 0;
        }

        // Cerca competenze: prima con question IDs diretti, poi con versioning fallback
        $mappings = self::find_competency_mappings($questionids, $comp_table);

        if (empty($mappings)) {
            return 0;
        }

        // Get student's primary sector (if set)
        $primarySector = self::get_student_primary_sector($userid);

        // Assegna ogni competenza
        $now = time();
        $assigned = 0;

        foreach ($mappings as $mapping) {
            $competencyid = $mapping->competencyid;

            // Filtro settore: solo competenze del settore primario + generiche
            if (!empty($primarySector)) {
                $competencySector = self::get_competency_sector($competencyid);
                $genericSectors = ['GEN', 'GENERICO', 'GENERICHE', 'TRASVERSALI'];
                if (!empty($competencySector)
                    && $competencySector !== $primarySector
                    && !in_array($competencySector, $genericSectors)) {
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
                    // Duplicato o altro errore, continua
                }
            }
        }

        return $assigned;
    }

    /**
     * Assegnazione retroattiva: scansiona TUTTI i quiz completati dello studente
     * e assegna le competenze mancanti. Chiamato da compile.php come safety net.
     *
     * @param int $userid User ID
     * @return int Total number of newly assigned competencies
     */
    public static function retroactive_assign($userid) {
        global $DB;

        // Trova tutti i quiz attempts completati per questo studente
        $attempts = $DB->get_records_sql("
            SELECT qa.id, qa.quiz, qa.uniqueid, qa.state
            FROM {quiz_attempts} qa
            WHERE qa.userid = ?
            AND qa.state = 'finished'
            ORDER BY qa.timemodified DESC
        ", [$userid]);

        if (empty($attempts)) {
            return 0;
        }

        $total_assigned = 0;

        foreach ($attempts as $attempt) {
            $assigned = self::assign_competencies_from_attempt($userid, $attempt);
            $total_assigned += $assigned;
        }

        return $total_assigned;
    }

    /**
     * Trova la tabella di mapping competenze-domande (con cache).
     *
     * @return array|null ['table' => name, 'field' => column] or null
     */
    private static function find_competency_table() {
        global $DB;

        if (self::$comp_table_checked) {
            return self::$comp_table_cache;
        }

        $comp_tables = [
            'qbank_competenciesbyquestion' => 'questionid',
            'qbank_comp_question' => 'questionid',
            'local_competencymanager_qcomp' => 'questionid'
        ];

        $dbman = $DB->get_manager();

        foreach ($comp_tables as $table => $field) {
            if ($dbman->table_exists($table)) {
                self::$comp_table_cache = ['table' => $table, 'field' => $field];
                self::$comp_table_checked = true;
                return self::$comp_table_cache;
            }
        }

        self::$comp_table_checked = true;
        self::$comp_table_cache = null;
        return null;
    }

    /**
     * Trova i mapping competenza-domanda, con fallback per versioning Moodle 4.x.
     *
     * In Moodle 4.x, le domande hanno versioni:
     * question.id → question_versions → question_bank_entries
     * Il mapping competenza potrebbe essere su una versione diversa della stessa domanda.
     *
     * @param array $questionids Question IDs from question_attempts
     * @param array $comp_table ['table' => name, 'field' => column]
     * @return array Competency mapping records
     */
    private static function find_competency_mappings($questionids, $comp_table) {
        global $DB;

        $table = $comp_table['table'];
        $field = $comp_table['field'];

        // Tentativo 1: match diretto (question_attempts.questionid == mapping.questionid)
        list($sql_in, $params) = $DB->get_in_or_equal($questionids);
        $mappings = $DB->get_records_sql("
            SELECT DISTINCT competencyid
            FROM {{$table}}
            WHERE {$field} $sql_in
        ", $params);

        if (!empty($mappings)) {
            return $mappings;
        }

        // Tentativo 2: versioning fallback
        // Le competenze potrebbero essere mappate a una versione diversa della stessa domanda.
        // Mappa: question.id → question_versions.questionbankentryid → tutte le versioni → cerca mapping
        try {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('question_versions')) {
                return [];
            }

            list($sql_in, $params) = $DB->get_in_or_equal($questionids);

            // Trova tutti i question IDs di tutte le versioni delle stesse domande
            $all_version_ids = $DB->get_records_sql("
                SELECT DISTINCT qv2.questionid
                FROM {question_versions} qv1
                JOIN {question_versions} qv2 ON qv2.questionbankentryid = qv1.questionbankentryid
                WHERE qv1.questionid $sql_in
            ", $params);

            if (empty($all_version_ids)) {
                return [];
            }

            $all_ids = array_keys($all_version_ids);
            list($sql_in2, $params2) = $DB->get_in_or_equal($all_ids);

            $mappings = $DB->get_records_sql("
                SELECT DISTINCT competencyid
                FROM {{$table}}
                WHERE {$field} $sql_in2
            ", $params2);

            return $mappings;

        } catch (\Exception $e) {
            debugging("selfassessment: versioning fallback failed: " . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Rileva settori dalle competenze di un attempt
     */
    private static function detect_sectors_from_attempt($userid, $attempt) {
        global $DB;

        $quizid = $attempt->quiz;

        // Get questions and mappings
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);

        if (empty($questions)) {
            return;
        }

        $comp_table = self::find_competency_table();
        if (!$comp_table) {
            return;
        }

        $mappings = self::find_competency_mappings(array_keys($questions), $comp_table);
        if (!empty($mappings)) {
            self::detect_sectors_safe($userid, $quizid, $mappings);
        }
    }

    /**
     * Setta un flag per indicare che lo studente deve essere reindirizzato a compile.php
     */
    private static function set_redirect_flag($userid) {
        global $USER, $SESSION;

        if ($USER->id != $userid) {
            return;
        }

        global $DB;
        $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
        if ($status && $status->skip_accepted) {
            return;
        }

        $SESSION->selfassessment_redirect_pending = true;
    }

    /**
     * Mostra un messaggio di congratulazioni allo studente
     */
    private static function show_success_message($userid, $count) {
        global $USER;

        if ($USER->id != $userid) {
            return;
        }

        $message = get_string('competencies_assigned_success', 'local_selfassessment', $count);
        \core\notification::success($message);
    }

    /**
     * Get student's primary sector from local_student_sectors.
     */
    private static function get_student_primary_sector($userid) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            return null;
        }

        $record = $DB->get_record('local_student_sectors', [
            'userid' => $userid,
            'is_primary' => 1
        ]);

        return $record ? $record->sector : null;
    }

    /**
     * Get sector from competency idnumber.
     */
    private static function get_competency_sector($competencyid) {
        global $DB;

        $competency = $DB->get_record('competency', ['id' => $competencyid]);
        if (!$competency || empty($competency->idnumber)) {
            return null;
        }

        $parts = explode('_', $competency->idnumber);
        if (!empty($parts[0])) {
            $sector = strtoupper($parts[0]);
            $sector = str_replace(
                ['À', 'È', 'É', 'Ì', 'Ò', 'Ù'],
                ['A', 'E', 'E', 'I', 'O', 'U'],
                $sector
            );
            return $sector;
        }

        return null;
    }

    /**
     * Rileva settori in modo sicuro
     */
    private static function detect_sectors_safe($userid, $quizid, $mappings) {
        global $DB;

        $sector_manager_file = __DIR__ . '/../../competencymanager/classes/sector_manager.php';
        if (!file_exists($sector_manager_file)) {
            return;
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            return;
        }

        try {
            require_once($sector_manager_file);
            $competencyids = array_map(function($m) { return $m->competencyid; }, $mappings);
            \local_competencymanager\sector_manager::detect_sectors_from_quiz($userid, $quizid, $competencyids);
        } catch (\Exception $e) {
            // Errore silenzioso - non blocca il quiz
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

        $url = new \moodle_url('/local/selfassessment/compile.php');

        $messagedata = new \stdClass();
        $messagedata->fullname = fullname($student);
        $messagedata->quizname = $quiz->name;
        $messagedata->count = $count;
        $messagedata->url = $url->out();

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
            return false;
        }
    }
}
