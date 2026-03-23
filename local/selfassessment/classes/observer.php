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

            // FIX PREVIEW: Admin e coach hanno mod/quiz:preview che salva i tentativi
            // come preview (non contano come tentativi reali). Convertiamo in normali.
            if (!empty($attempt->preview)) {
                self::convert_preview_to_normal($attempt);
                // Ricarica il record aggiornato.
                $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
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

            // Notifica email a coach e segreteria.
            self::notify_quiz_completion($userid, $attempt);

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
     * Notifica via email coach e segreteria quando uno studente completa un quiz.
     *
     * Destinatari:
     * - Coach assegnato allo studente (da local_student_coaching)
     * - Tutti i siteadmin (include Segreteria)
     *
     * @param int $userid Student user ID
     * @param object $attempt Quiz attempt record
     */
    private static function notify_quiz_completion($userid, $attempt) {
        global $DB, $CFG;

        try {
            $student = $DB->get_record('user', ['id' => $userid]);
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
            if (!$student || !$quiz) {
                return;
            }

            $course = $DB->get_record('course', ['id' => $quiz->course]);
            $coursename = $course ? $course->fullname : '?';

            // Orario completamento.
            $completiontime = $attempt->timefinish ? $attempt->timefinish : $attempt->timestart;
            $timestr = userdate($completiontime, '%A %d %B %Y, %H:%M');

            // Voto.
            $gradestr = 'N/D';
            if ($attempt->state === 'finished' && $attempt->sumgrades !== null) {
                $quizgrades = $DB->get_record('quiz', ['id' => $attempt->quiz], 'sumgrades');
                if ($quizgrades && $quizgrades->sumgrades > 0) {
                    $percent = round(($attempt->sumgrades / $quizgrades->sumgrades) * 100, 1);
                    $gradestr = $attempt->sumgrades . ' / ' . $quizgrades->sumgrades . ' (' . $percent . '%)';
                } else {
                    $gradestr = $attempt->sumgrades;
                }
            }

            // Link al report studente.
            $reporturl = $CFG->wwwroot . '/local/competencymanager/student_report.php?userid=' . $userid . '&courseid=' . $quiz->course;
            // Link alla review del tentativo.
            $reviewurl = $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $attempt->id;

            // Costruisci il messaggio.
            $studentname = fullname($student);
            $subject = "FTM - Quiz completato: {$studentname} - {$quiz->name}";

            $body = "Notifica completamento Quiz FTM\n";
            $body .= "================================\n\n";
            $body .= "Studente:  {$studentname}\n";
            $body .= "Email:     {$student->email}\n";
            $body .= "Quiz:      {$quiz->name}\n";
            $body .= "Corso:     {$coursename}\n";
            $body .= "Data/Ora:  {$timestr}\n";
            $body .= "Voto:      {$gradestr}\n\n";
            $body .= "Link:\n";
            $body .= "- Review tentativo: {$reviewurl}\n";
            $body .= "- Report studente:  {$reporturl}\n\n";
            $body .= "---\n";
            $body .= "Notifica automatica FTM Academy\n";

            // Barra di progresso voto.
            $percentval = 0;
            if ($attempt->state === 'finished' && $attempt->sumgrades !== null) {
                $qz = $DB->get_record('quiz', ['id' => $attempt->quiz], 'sumgrades');
                if ($qz && $qz->sumgrades > 0) {
                    $percentval = round(($attempt->sumgrades / $qz->sumgrades) * 100, 1);
                }
            }
            $barcolor = $percentval >= 70 ? '#28a745' : ($percentval >= 40 ? '#EAB308' : '#dc3545');

            // Estrai settore dal nome quiz (prima parte prima del trattino).
            $quizparts = explode(' - ', $quiz->name, 2);
            $sector = isset($quizparts[0]) ? trim($quizparts[0]) : '';
            $quizshort = isset($quizparts[1]) ? trim($quizparts[1]) : $quiz->name;
            // Pulizia nome quiz (rimuovi date e underscore).
            $quizshort = preg_replace('/_\d{8}_\d+$/', '', $quizshort);
            $quizshort = str_replace('_', ' ', $quizshort);

            $htmlbody = self::build_email_html(
                'quiz',
                $studentname,
                $student->email,
                [
                    ['label' => 'Settore', 'value' => $sector, 'bold' => true],
                    ['label' => 'Quiz', 'value' => $quizshort],
                    ['label' => 'Corso', 'value' => $coursename],
                    ['label' => 'Data/Ora', 'value' => $timestr],
                ],
                $percentval,
                $barcolor,
                $gradestr,
                [
                    ['url' => $reviewurl, 'label' => 'Review Tentativo', 'color' => '#0066cc'],
                    ['url' => $reporturl, 'label' => 'Report Studente', 'color' => '#28a745'],
                ]
            );

            // Raccogli destinatari (coach + siteadmins + tutti i coach), dedup.
            $recipients = self::get_notification_recipients($userid);

            if (empty($recipients)) {
                return;
            }

            // Invia email a ogni destinatario.
            $noreply = \core_user::get_noreply_user();
            $sent = 0;
            foreach ($recipients as $recipient) {
                try {
                    email_to_user($recipient, $noreply, $subject, $body, $htmlbody);
                    $sent++;
                } catch (\Exception $e) {
                    debugging("selfassessment: email to {$recipient->email} failed: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            debugging("selfassessment: quiz completion notification sent to {$sent} recipients for user {$userid}", DEBUG_DEVELOPER);

        } catch (\Exception $e) {
            // Non bloccare mai il quiz per errori di notifica.
            debugging("selfassessment: notification error: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Notifica coach e segreteria quando uno studente salva l'autovalutazione.
     * Invia email solo al completamento (100% competenze valutate).
     *
     * @param int $userid Student ID
     * @param int $saved Number of assessments saved in this batch
     * @param int $total_rated Total assessments rated so far
     * @param int $total_assigned Total competencies assigned
     * @param bool $just_completed True if this save triggered 100% completion
     */
    public static function notify_selfassessment_saved($userid, $saved, $total_rated, $total_assigned, $just_completed) {
        global $DB, $CFG;

        // Invia email solo al completamento, non ad ogni salvataggio parziale.
        if (!$just_completed) {
            return;
        }

        try {
            $student = $DB->get_record('user', ['id' => $userid]);
            if (!$student) {
                return;
            }

            $studentname = fullname($student);
            $timestr = userdate(time(), '%A %d %B %Y, %H:%M');

            // Link alla pagina compile dello studente (per coach).
            $compileurl = $CFG->wwwroot . '/local/selfassessment/compile.php';

            // Trova il corso R.comp per il link al report.
            $rcomp = $DB->get_record_sql("SELECT id FROM {course} WHERE fullname = :fn OR shortname = :sn LIMIT 1",
                ['fn' => 'R.comp', 'sn' => 'R.comp']);
            $reporturl = $rcomp
                ? $CFG->wwwroot . '/local/competencymanager/student_report.php?userid=' . $userid . '&courseid=' . $rcomp->id
                : $CFG->wwwroot . '/local/competencymanager/student_report.php?userid=' . $userid;

            $subject = "FTM - Autovalutazione completata: {$studentname}";

            $body = "Notifica Autovalutazione FTM\n";
            $body .= "================================\n\n";
            $body .= "Studente:     {$studentname}\n";
            $body .= "Email:        {$student->email}\n";
            $body .= "Competenze:   {$total_rated} / {$total_assigned} valutate (100%)\n";
            $body .= "Data/Ora:     {$timestr}\n\n";
            $body .= "Link:\n";
            $body .= "- Report studente: {$reporturl}\n\n";
            $body .= "---\n";
            $body .= "Notifica automatica FTM Academy\n";

            $htmlbody = self::build_email_html(
                'selfassessment',
                $studentname,
                $student->email,
                [
                    ['label' => 'Competenze', 'value' => "{$total_rated} / {$total_assigned} valutate", 'bold' => true],
                    ['label' => 'Data/Ora', 'value' => $timestr],
                ],
                100,
                '#28a745',
                "{$total_rated} / {$total_assigned} (100%)",
                [
                    ['url' => $reporturl, 'label' => 'Report Studente', 'color' => '#28a745'],
                ]
            );

            // Stessi destinatari del quiz: coach assegnato + siteadmins + tutti i coach.
            $recipients = self::get_notification_recipients($userid);

            if (empty($recipients)) {
                return;
            }

            $noreply = \core_user::get_noreply_user();
            foreach ($recipients as $recipient) {
                try {
                    email_to_user($recipient, $noreply, $subject, $body, $htmlbody);
                } catch (\Exception $e) {
                    debugging("selfassessment: email to {$recipient->email} failed: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

        } catch (\Exception $e) {
            debugging("selfassessment: selfassessment notification error: " . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Genera l'HTML professionale per le email di notifica FTM.
     *
     * @param string $type 'quiz' o 'selfassessment'
     * @param string $studentname Nome completo studente
     * @param string $email Email studente
     * @param array $fields Campi aggiuntivi [['label'=>..., 'value'=>..., 'bold'=>bool], ...]
     * @param float $percent Percentuale per la barra di progresso (0-100)
     * @param string $barcolor Colore della barra
     * @param string $scoretext Testo del punteggio
     * @param array $buttons Bottoni [['url'=>..., 'label'=>..., 'color'=>...], ...]
     * @return string HTML email
     */
    private static function build_email_html($type, $studentname, $email, $fields, $percent, $barcolor, $scoretext, $buttons) {

        $isQuiz = ($type === 'quiz');
        $headerBg = $isQuiz ? '#0066cc' : '#28a745';
        $headerIcon = $isQuiz ? '&#128221;' : '&#9989;';
        $headerTitle = $isQuiz ? 'Quiz Completato' : 'Autovalutazione Completata';
        $accentColor = $isQuiz ? '#0066cc' : '#28a745';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0; padding:0; background:#f4f6f8;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8; padding:20px 0;">';
        $html .= '<tr><td align="center">';

        // Container.
        $html .= '<table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);">';

        // Header con gradiente.
        $html .= '<tr><td style="background:' . $headerBg . '; padding:28px 32px;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td style="color:#ffffff; font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<div style="font-size:28px; margin-bottom:4px;">' . $headerIcon . '</div>';
        $html .= '<div style="font-size:22px; font-weight:700; letter-spacing:-0.3px;">' . $headerTitle . '</div>';
        $html .= '<div style="font-size:13px; opacity:0.85; margin-top:4px;">FTM Academy - Notifica automatica</div>';
        $html .= '</td>';
        $html .= '<td align="right" valign="top" style="color:#ffffff; font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<div style="font-size:11px; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">FTM</div>';
        $html .= '<div style="font-size:11px; opacity:0.7;">Academy</div>';
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</td></tr>';

        // Studente card.
        $html .= '<tr><td style="padding:24px 32px 0;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa; border-radius:8px; border:1px solid #e9ecef;">';
        $html .= '<tr><td style="padding:16px 20px;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0"><tr>';
        // Avatar cerchio con iniziali.
        $initials = '';
        $nameparts = explode(' ', $studentname);
        foreach ($nameparts as $np) {
            if (!empty($np)) {
                $initials .= mb_strtoupper(mb_substr($np, 0, 1));
            }
        }
        $initials = mb_substr($initials, 0, 2);
        $html .= '<td width="48" valign="top">';
        $html .= '<div style="width:44px; height:44px; border-radius:50%; background:' . $accentColor . '; color:#fff; font-family:Arial,sans-serif; font-size:16px; font-weight:700; line-height:44px; text-align:center;">' . s($initials) . '</div>';
        $html .= '</td>';
        $html .= '<td style="padding-left:12px; font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<div style="font-size:16px; font-weight:700; color:#1a1a2e;">' . s($studentname) . '</div>';
        $html .= '<div style="font-size:13px; color:#6c757d; margin-top:2px;">' . s($email) . '</div>';
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</td></tr></table>';
        $html .= '</td></tr>';

        // Dettagli.
        $html .= '<tr><td style="padding:20px 32px 0;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif;">';
        foreach ($fields as $f) {
            $bold = !empty($f['bold']) ? 'font-weight:700;' : '';
            $html .= '<tr>';
            $html .= '<td width="110" style="padding:7px 0; font-size:13px; color:#6c757d; vertical-align:top;">' . s($f['label']) . '</td>';
            $html .= '<td style="padding:7px 0; font-size:14px; color:#1a1a2e; ' . $bold . '">' . s($f['value']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</td></tr>';

        // Barra punteggio.
        $html .= '<tr><td style="padding:20px 32px 0;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<tr><td style="font-size:12px; color:#6c757d; text-transform:uppercase; letter-spacing:0.5px; padding-bottom:8px;">Risultato</td></tr>';
        $html .= '<tr><td>';
        // Barra sfondo.
        $html .= '<table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td style="background:#e9ecef; border-radius:20px; height:28px; overflow:hidden;">';
        $html .= '<div style="background:' . $barcolor . '; width:' . max($percent, 3) . '%; height:28px; border-radius:20px; text-align:center; line-height:28px;">';
        if ($percent >= 15) {
            $html .= '<span style="color:#fff; font-size:12px; font-weight:700;">' . $percent . '%</span>';
        }
        $html .= '</div>';
        $html .= '</td></tr></table>';
        if ($percent < 15) {
            $html .= '<div style="text-align:left; margin-top:4px; font-size:12px; color:#6c757d;">' . $percent . '%</div>';
        }
        $html .= '</td></tr>';
        $html .= '<tr><td style="font-size:14px; font-weight:600; color:#1a1a2e; padding-top:6px;">' . s($scoretext) . '</td></tr>';
        $html .= '</table>';
        $html .= '</td></tr>';

        // Bottoni.
        $html .= '<tr><td style="padding:24px 32px;" align="center">';
        $html .= '<table cellpadding="0" cellspacing="0"><tr>';
        foreach ($buttons as $btn) {
            $html .= '<td style="padding:0 6px;">';
            $html .= '<a href="' . $btn['url'] . '" style="display:inline-block; background:' . $btn['color'] . '; color:#ffffff; font-family:Arial,sans-serif; font-size:14px; font-weight:600; text-decoration:none; padding:11px 24px; border-radius:8px;">';
            $html .= s($btn['label']);
            $html .= '</a>';
            $html .= '</td>';
        }
        $html .= '</tr></table>';
        $html .= '</td></tr>';

        // Footer.
        $html .= '<tr><td style="background:#f8f9fa; padding:16px 32px; border-top:1px solid #e9ecef;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td style="font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#adb5bd;">';
        $html .= 'Notifica automatica &middot; FTM Academy &middot; Fondazione Terzo Millennio';
        $html .= '</td>';
        $html .= '<td align="right" style="font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#adb5bd;">';
        $html .= date('d.m.Y H:i');
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</td></tr>';

        $html .= '</table>'; // container
        $html .= '</td></tr></table>'; // outer
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Raccoglie i destinatari delle notifiche: coach dello studente + siteadmins + tutti i coach.
     *
     * @param int $userid Student user ID (escluso dai destinatari)
     * @return array User records
     */
    private static function get_notification_recipients($userid) {
        global $DB;

        $recipients = [];

        // 1. Coach assegnato allo studente.
        $coaching = $DB->get_records('local_student_coaching', ['userid' => $userid, 'status' => 'active']);
        foreach ($coaching as $c) {
            if (!empty($c->coachid) && $c->coachid > 0) {
                $coach = $DB->get_record('user', ['id' => $c->coachid, 'deleted' => 0, 'suspended' => 0]);
                if ($coach) {
                    $recipients[$coach->id] = $coach;
                }
            }
        }

        // 2. Siteadmins.
        $admins = get_admins();
        foreach ($admins as $admin) {
            if (!$admin->deleted && !$admin->suspended && !empty($admin->email)) {
                $recipients[$admin->id] = $admin;
            }
        }

        // 3. NON notificare tutti i coach: solo il coach assegnato riceve la notifica.
        // I coach registrati in local_ftm_coaches non vengono piu notificati per tutti gli studenti,
        // ma solo per quelli a loro assegnati tramite local_student_coaching (punto 1).

        // Non notificare lo studente stesso.
        unset($recipients[$userid]);

        return $recipients;
    }

    /**
     * Converte un tentativo PREVIEW in un tentativo normale.
     * Necessario perché admin/coach hanno mod/quiz:preview che marca
     * automaticamente i tentativi come preview (non visibili nei report).
     *
     * @param object $attempt Quiz attempt record with preview=1
     */
    private static function convert_preview_to_normal($attempt) {
        global $DB, $CFG;

        try {
            // Calcola il numero tentativo corretto (sequenziale per user+quiz).
            $maxattempt = $DB->get_field_sql(
                "SELECT COALESCE(MAX(attempt), 0)
                   FROM {quiz_attempts}
                  WHERE quiz = :quiz AND userid = :userid AND preview = 0",
                ['quiz' => $attempt->quiz, 'userid' => $attempt->userid]
            );

            $DB->set_field('quiz_attempts', 'preview', 0, ['id' => $attempt->id]);
            $DB->set_field('quiz_attempts', 'attempt', $maxattempt + 1, ['id' => $attempt->id]);

            // Ricalcola il voto finale del quiz per questo utente.
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
            if ($quiz) {
                quiz_update_all_final_grades($quiz);
            }

            debugging("selfassessment: converted preview attempt {$attempt->id} to normal for user {$attempt->userid}", DEBUG_DEVELOPER);
        } catch (\Exception $e) {
            debugging("selfassessment: preview conversion failed: " . $e->getMessage(), DEBUG_DEVELOPER);
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
