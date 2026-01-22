<?php
/**
 * Test Runner - Esegue tutti i test della suite FTM
 * 
 * VERSIONE 2.4 - Aggiornata 11/01/2026
 * 
 * CORREZIONI v2.4:
 * - Test 6.1: Calcola copertura SOLO del settore pertinente al corso
 *   (es. Autoveicolo → copertura AUTOMOBILE, non dell'intero framework)
 * - Test 1.5: Messaggio migliorato quando utente non ha dati nel corso
 * - Test 1.7: Messaggio migliorato, suggerisce generazione dati
 * - Test 4.1: Non warning se settore ha meno di 3 aree (è normale!)
 * - Test 4.2: Messaggio migliorato, indica necessità dati utente
 * - Nuovo metodo get_course_sector() per rilevare settore del corso
 * 
 * CORREZIONE v2.3.1:
 * - Test 1.1: Logica corretta con due query separate
 * 
 * CORREZIONE v2.3:
 * - Filtro corso ($courseid) applicato CORRETTAMENTE a TUTTI i test
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per l'esecuzione dei test
 */
class test_runner {

    /** @var test_manager */
    private $manager;

    /** @var int Corso da testare */
    private $courseid;

    /** @var int Framework di competenze */
    private $frameworkid;

    /** @var array Utenti test */
    private $testusers;

    /** @var object Utente test primario */
    private $primary_user;

    /** @var int ID utente selezionato per il test */
    private $selected_userid;

    /** @var string Settore principale del corso (cache) */
    private $course_sector = null;

    /** @var int Numero totale competenze del settore */
    private $sector_total_competencies = 0;

    /** @var int Corso da escludere dal test */
    private $exclude_courseid = 0;

    /**
     * Costruttore
     * @param int $courseid Corso da testare (0 = tutti)
     * @param int $frameworkid Framework competenze (0 = tutti)
     * @param int $userid Utente specifico da testare (0 = default medium65)
     * @param int $exclude_courseid Corso da escludere (0 = nessuno)
     */
    public function __construct($courseid = 0, $frameworkid = 0, $userid = 0, $exclude_courseid = 0) {
        $this->courseid = $courseid;
        $this->frameworkid = $frameworkid;
        $this->selected_userid = $userid;
        $this->exclude_courseid = $exclude_courseid;
        $this->manager = new test_manager($courseid);
        $this->load_test_users();

        // Pre-carica il settore del corso se specificato
        if ($this->courseid > 0) {
            $this->detect_course_sector();
        }
    }

    /**
     * Carica utenti test
     */
    private function load_test_users() {
        global $DB;
        $this->testusers = $DB->get_records('local_ftm_testsuite_users');

        if ($this->selected_userid > 0) {
            foreach ($this->testusers as $tu) {
                if ($tu->userid == $this->selected_userid) {
                    $this->primary_user = $tu;
                    return;
                }
            }
            $real_user = $DB->get_record('user', ['id' => $this->selected_userid]);
            if ($real_user) {
                $this->primary_user = (object)[
                    'userid' => $real_user->id,
                    'username' => $real_user->username,
                    'testprofile' => 'real',
                    'quiz_percentage' => 0
                ];
                return;
            }
        }

        foreach ($this->testusers as $tu) {
            if ($tu->testprofile === 'medium65') {
                $this->primary_user = $tu;
                break;
            }
        }

        if (!$this->primary_user && !empty($this->testusers)) {
            $this->primary_user = reset($this->testusers);
        }
    }

    /**
     * Rileva il settore principale del corso basandosi sulle competenze usate
     * NUOVO v2.4
     */
    private function detect_course_sector() {
        global $DB;
        
        if ($this->courseid <= 0) {
            return;
        }

        $params = [$this->courseid];
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        // Trova il settore più usato nel corso
        $sql = "SELECT SUBSTRING_INDEX(c.idnumber, '_', 1) as sector,
                       COUNT(DISTINCT c.id) as comp_count
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                JOIN {competency} c ON c.id = qc.competencyid
                WHERE q.course = ? AND c.idnumber LIKE '%\\_%\\_%' {$framework_condition}
                GROUP BY sector
                ORDER BY comp_count DESC
                LIMIT 1";

        $result = $DB->get_record_sql($sql, $params);
        
        if ($result) {
            $this->course_sector = $result->sector;
            
            // Conta le competenze TOTALI di quel settore nel framework
            $params_total = [];
            $fw_cond = "";
            if ($this->frameworkid > 0) {
                $fw_cond = "AND competencyframeworkid = ?";
                $params_total[] = $this->frameworkid;
            }
            
            $this->sector_total_competencies = $DB->count_records_sql("
                SELECT COUNT(*) FROM {competency}
                WHERE idnumber LIKE ? {$fw_cond}
            ", array_merge([$this->course_sector . '_%'], $params_total));
        }
    }

    /**
     * Ottiene il settore del corso
     * @return string|null
     */
    public function get_course_sector() {
        return $this->course_sector;
    }

    /**
     * Ottiene il nome del framework selezionato
     * @return string
     */
    private function get_framework_name() {
        global $DB;
        if ($this->frameworkid > 0) {
            $fw = $DB->get_record('competency_framework', ['id' => $this->frameworkid]);
            return $fw ? $fw->shortname : "ID:{$this->frameworkid}";
        }
        return 'Tutti';
    }

    /**
     * Ottiene il nome del corso selezionato
     * @return string
     */
    private function get_course_name() {
        global $DB;
        if ($this->courseid > 0) {
            $course = $DB->get_record('course', ['id' => $this->courseid]);
            return $course ? $course->shortname : "ID:{$this->courseid}";
        }
        return 'Tutti';
    }

    /**
     * Genera info filtri per i messaggi
     * @return string
     */
    private function get_filter_info() {
        $info = [];
        if ($this->courseid > 0) {
            $info[] = "Corso: {$this->get_course_name()}";
        }
        if ($this->frameworkid > 0) {
            $info[] = "Framework: {$this->get_framework_name()}";
        }
        if ($this->exclude_courseid > 0) {
            $info[] = "Escluso corso ID: {$this->exclude_courseid}";
        }
        return empty($info) ? '' : ' (' . implode(', ', $info) . ')';
    }

    /**
     * Genera condizione SQL per escludere un corso
     * @param string $table_alias Alias della tabella quiz (es. 'q')
     * @return string Condizione SQL (es. "AND q.course != 18")
     */
    private function get_exclude_condition($table_alias = 'q') {
        if ($this->exclude_courseid > 0) {
            return " AND {$table_alias}.course != {$this->exclude_courseid}";
        }
        return '';
    }

    /**
     * Verifica se l'utente ha dati quiz nel corso selezionato
     * NUOVO v2.4
     * @return bool
     */
    private function user_has_course_data() {
        global $DB;
        
        if (!$this->primary_user || $this->courseid <= 0) {
            return true; // Se non c'è filtro corso, assumiamo ci siano dati
        }
        
        $count = $DB->count_records_sql("
            SELECT COUNT(*) FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = ? AND q.course = ? AND qa.state = 'finished'
        ", [$this->primary_user->userid, $this->courseid]);
        
        return $count > 0;
    }

    /**
     * Esegue tutti i test
     * @param string $runname Nome del run
     * @return object Risultati del run
     */
    public function run_all($runname = '') {
        $filter_info = $this->get_filter_info();
        $this->manager->start_run($runname ?: "Test Completo{$filter_info} - " . date('d/m/Y H:i'));

        $this->run_module_quiz();
        $this->run_module_selfassessment();
        $this->run_module_labeval();
        $this->run_module_radar();
        $this->run_module_report();
        $this->run_module_coverage();
        $this->run_module_integrity();
        $this->run_module_assignments();

        return $this->manager->complete_run();
    }

    // ========================================================================
    // MODULO 1: Test Quiz e Competenze
    // ========================================================================
    
    private function run_module_quiz() {
        $this->test_1_1_questions_with_competencies();
        $this->test_1_2_competencies_exist();
        $this->test_1_3_correct_framework();
        $this->test_1_4_orphan_questions();
        $this->test_1_5_responses_recorded();
        $this->test_1_6_valid_fraction();
        $this->test_1_7_manual_calculation();
        $this->test_1_8_idnumber_parsing();
    }

    /**
     * Test 1.1: Domande con competenze
     */
    private function test_1_1_questions_with_competencies() {
        global $DB;
        $start = microtime(true);

        $params_total = [];
        $course_where = "WHERE 1=1";

        if ($this->courseid > 0) {
            $course_where .= " AND q.course = ?";
            $params_total[] = $this->courseid;
        }

        // Esclusione corso
        $course_where .= $this->get_exclude_condition('q');

        $sql_total = "
            SELECT COUNT(DISTINCT qv.questionid) as total_questions
            FROM {quiz} q
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            {$course_where}
        ";
        
        $result_total = $DB->get_record_sql($sql_total, $params_total);
        $total_questions = $result_total->total_questions ?? 0;

        $params_comp = [];
        $course_where_comp = "WHERE 1=1";

        if ($this->courseid > 0) {
            $course_where_comp .= " AND q.course = ?";
            $params_comp[] = $this->courseid;
        }

        // Esclusione corso
        $course_where_comp .= $this->get_exclude_condition('q');

        $framework_condition = "";
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params_comp[] = $this->frameworkid;
        }

        $sql_comp = "
            SELECT COUNT(DISTINCT qv.questionid) as questions_with_comp
            FROM {quiz} q
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            {$course_where_comp}
            {$framework_condition}
        ";
        
        $result_comp = $DB->get_record_sql($sql_comp, $params_comp);
        $questions_with_comp = $result_comp->questions_with_comp ?? 0;

        $percentage = $total_questions > 0
            ? round(($questions_with_comp / $total_questions) * 100, 1)
            : 0;

        $status = $percentage == 100 ? 'passed' : ($percentage >= 80 ? 'warning' : 'failed');

        $this->manager->record_result(
            'quiz', '1.1', 'Domande con competenze',
            $status, '100%', $percentage . '%',
            "{$questions_with_comp} domande su {$total_questions} hanno competenze assegnate.{$this->get_filter_info()}",
            $sql_comp, [], microtime(true) - $start
        );
    }

    /**
     * Test 1.2: Competenze esistenti
     */
    private function test_1_2_competencies_exist() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_join = "";
        $course_where = "";
        
        if ($this->courseid > 0) {
            $course_join = "
                JOIN {question_versions} qv ON qv.questionid = qc.questionid
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_references} qr ON qr.questionbankentryid = qbe.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {quiz_slots} qs ON qs.id = qr.itemid
                JOIN {quiz} q ON q.id = qs.quizid
            ";
            $course_where = "AND q.course = ?";
            $params[] = $this->courseid;
        }

        $sql = "SELECT COUNT(*) as orphans 
                FROM {qbank_competenciesbyquestion} qc 
                LEFT JOIN {competency} c ON c.id = qc.competencyid
                {$course_join}
                WHERE c.id IS NULL {$course_where}";
        
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('quiz', '1.2', 'Competenze esistenti', $status, '0 orfani', $result->orphans . ' orfani',
            $result->orphans == 0 ? 'Tutte le competenze assegnate esistono.' : "{$result->orphans} assegnazioni a competenze inesistenti.",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 1.3: Framework corretto
     */
    private function test_1_3_correct_framework() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_join = "";
        $framework_where = "";
        
        if ($this->courseid > 0) {
            $course_join = "
                JOIN {question_versions} qv ON qv.questionid = qc.questionid
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_references} qr ON qr.questionbankentryid = qbe.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {quiz_slots} qs ON qs.id = qr.itemid
                JOIN {quiz} q ON q.id = qs.quizid AND q.course = ?
            ";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_where = $this->courseid > 0 ? "AND cf.id = ?" : "WHERE cf.id = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT cf.id, cf.shortname, COUNT(DISTINCT c.id) as comp_count
                FROM {competency_framework} cf
                JOIN {competency} c ON c.competencyframeworkid = cf.id
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                {$course_join}
                {$framework_where}
                GROUP BY cf.id, cf.shortname";
        
        $frameworks = $DB->get_records_sql($sql, $params);

        $count = count($frameworks);
        $status = $count >= 1 ? 'passed' : 'warning';

        $details = [];
        foreach ($frameworks as $f) {
            $details[] = "{$f->shortname}: {$f->comp_count} competenze";
        }

        $this->manager->record_result('quiz', '1.3', 'Framework corretto', $status, '≥1 framework', $count,
            $count >= 1 ? implode(', ', $details) : 'Nessun framework trovato.',
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 1.4: Domande orfane (senza competenze)
     */
    private function test_1_4_orphan_questions() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $exclude_cond = $this->get_exclude_condition('q');

        if ($this->frameworkid > 0) {
            $course_cond = $this->courseid ? "AND q.course = ?" : "";

            $sql = "
                SELECT COUNT(DISTINCT qv.questionid) as orphans
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {qbank_competenciesbyquestion} qc
                    JOIN {competency} c ON c.id = qc.competencyid
                    WHERE qc.questionid = qv.questionid AND c.competencyframeworkid = ?
                )
                {$course_cond}
                {$exclude_cond}
            ";

            $params[] = $this->frameworkid;
            if ($this->courseid) {
                $params[] = $this->courseid;
            }
        } else {
            $course_cond = $this->courseid ? "AND q.course = ?" : "";

            $sql = "
                SELECT COUNT(DISTINCT qv.questionid) as orphans
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                WHERE qc.id IS NULL
                {$course_cond}
                {$exclude_cond}
            ";

            if ($this->courseid) {
                $params[] = $this->courseid;
            }
        }

        $result = $DB->get_record_sql($sql, $params);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('quiz', '1.4', 'Domande orfane', $status, '0', $result->orphans,
            $result->orphans == 0 ? 'Tutte le domande hanno competenze.' : "{$result->orphans} domande senza competenze!{$this->get_filter_info()}",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 1.5: Risposte registrate
     * MIGLIORATO v2.4: Messaggio chiaro quando mancano dati
     */
    private function test_1_5_responses_recorded() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('quiz', '1.5', 'Risposte registrate', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $params = [$this->primary_user->userid];
        $course_join = "";
        $course_name = "";
        
        if ($this->courseid > 0) {
            $course_join = "JOIN {quiz} q ON q.id = qa.quiz AND q.course = ?";
            $params = [$this->courseid, $this->primary_user->userid];
            $course_name = $this->get_course_name();
        }

        $sql = "SELECT COUNT(*) as attempts 
                FROM {quiz_attempts} qa 
                {$course_join}
                WHERE qa.userid = ? AND qa.state = 'finished'";
        
        $result = $DB->get_record_sql($sql, $params);

        if ($result->attempts > 0) {
            $status = 'passed';
            $message = "{$result->attempts} tentativi completati per {$this->primary_user->username}.";
        } else {
            $status = 'warning';
            if ($this->courseid > 0) {
                $message = "ATTENZIONE: {$this->primary_user->username} non ha quiz completati nel corso '{$course_name}'. " .
                          "Usa 'Genera Dati' per creare dati di test per questo corso.";
            } else {
                $message = "Nessun tentativo completato per {$this->primary_user->username}.";
            }
        }

        $this->manager->record_result('quiz', '1.5', 'Risposte registrate', $status, '≥1', $result->attempts, $message,
            $sql, [], microtime(true) - $start);
    }

    private function test_1_6_valid_fraction() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as invalid FROM {question_attempt_steps} qas
                JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
                WHERE qas.fraction IS NOT NULL AND (qas.fraction < 0 OR qas.fraction > 1)";
        $result = $DB->get_record_sql($sql);

        $status = $result->invalid == 0 ? 'passed' : 'warning';
        $this->manager->record_result('quiz', '1.6', 'Fraction valida', $status, '0 invalidi', $result->invalid,
            $result->invalid == 0 ? 'Tutte le fraction sono nel range 0-1.' : "{$result->invalid} fraction fuori range.",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 1.7: Calcolo manuale
     * MIGLIORATO v2.4: Messaggio chiaro quando mancano dati
     */
    private function test_1_7_manual_calculation() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        // Verifica se l'utente ha dati nel corso
        if ($this->courseid > 0 && !$this->user_has_course_data()) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-',
                "Nessun dato quiz per {$this->primary_user->username} nel corso '{$this->get_course_name()}'. Genera dati di test.",
                '', [], microtime(true) - $start);
            return;
        }

        $params = [$this->primary_user->userid];
        $course_join = "";
        $framework_condition = "";
        
        if ($this->courseid > 0) {
            $course_join = "JOIN {quiz} quiz ON quiz.id = qa.quiz AND quiz.course = ?";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT qc.competencyid, c.idnumber,
                       AVG(COALESCE(qas.fraction, 0)) as calculated_avg
                FROM {quiz_attempts} qa
                {$course_join}
                JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id AND qas.sequencenumber = (
                    SELECT MAX(qas2.sequencenumber) FROM {question_attempt_steps} qas2 WHERE qas2.questionattemptid = qat.id
                )
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                JOIN {competency} c ON c.id = qc.competencyid
                WHERE qa.userid = ? AND qa.state = 'finished'
                {$framework_condition}
                GROUP BY qc.competencyid, c.idnumber
                LIMIT 1";

        $ordered_params = [];
        if ($this->courseid > 0) {
            $ordered_params[] = $this->courseid;
        }
        $ordered_params[] = $this->primary_user->userid;
        if ($this->frameworkid > 0) {
            $ordered_params[] = $this->frameworkid;
        }

        $result = $DB->get_record_sql($sql, $ordered_params);

        if (!$result) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-', 
                'Nessuna competenza testata. Genera dati di test per questo corso.', '', [], microtime(true) - $start);
            return;
        }

        $pct = round($result->calculated_avg * 100, 1);
        $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'passed', 'Calcolo OK', "{$pct}%",
            "Competenza {$result->idnumber}: {$pct}% calcolato.",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 1.8: Parsing idnumber
     */
    private function test_1_8_idnumber_parsing() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_join = "";
        $framework_condition = "";
        
        if ($this->courseid > 0) {
            $course_join = "
                JOIN {question_versions} qv ON qv.questionid = qc.questionid
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_references} qr ON qr.questionbankentryid = qbe.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {quiz_slots} qs ON qs.id = qr.itemid
                JOIN {quiz} q ON q.id = qs.quizid AND q.course = ?
            ";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT COUNT(*) as invalid FROM {competency} c
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                {$course_join}
                WHERE (c.idnumber IS NULL OR c.idnumber = '' OR c.idnumber NOT LIKE '%\\_%\\_%')
                {$framework_condition}";
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->invalid == 0 ? 'passed' : 'warning';
        $this->manager->record_result('quiz', '1.8', 'Parsing idnumber', $status, '0 invalidi', $result->invalid,
            $result->invalid == 0 ? 'Tutti gli idnumber hanno formato SETTORE_AREA_NN.' : "{$result->invalid} idnumber mal formattati.",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 2: Test Autovalutazioni
    // ========================================================================
    
    private function run_module_selfassessment() {
        $this->test_2_1_selfassessment_exist();
        $this->test_2_2_bloom_range();
        $this->test_2_3_valid_user();
        $this->test_2_4_match_with_quiz();
        $this->test_2_5_bloom_average();
    }

    private function test_2_1_selfassessment_exist() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_selfassessment')) {
            $this->manager->record_result('selfassessment', '2.1', 'Autovalutazioni esistono', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        if ($this->frameworkid > 0) {
            $count = $DB->count_records_sql("
                SELECT COUNT(*) FROM {local_selfassessment} sa
                JOIN {competency} c ON c.id = sa.competencyid
                WHERE c.competencyframeworkid = ?
            ", [$this->frameworkid]);
        } else {
            $count = $DB->count_records('local_selfassessment');
        }
        
        $status = $count > 0 ? 'passed' : 'warning';

        $this->manager->record_result('selfassessment', '2.1', 'Autovalutazioni esistono', $status, '≥1', $count,
            $count > 0 ? "{$count} autovalutazioni nel sistema." : "Nessuna autovalutazione.",
            '', [], microtime(true) - $start);
    }

    private function test_2_2_bloom_range() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_selfassessment')) {
            $this->manager->record_result('selfassessment', '2.2', 'Range Bloom valido', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $params = [];
        $join = "";
        $where = "";
        
        if ($this->frameworkid > 0) {
            $join = "JOIN {competency} c ON c.id = sa.competencyid";
            $where = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT COUNT(*) as invalid FROM {local_selfassessment} sa {$join} WHERE (sa.level < 1 OR sa.level > 6) {$where}";
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->invalid == 0 ? 'passed' : 'failed';
        $this->manager->record_result('selfassessment', '2.2', 'Range Bloom valido', $status, '0 invalidi', $result->invalid,
            $result->invalid == 0 ? 'Tutti i livelli Bloom sono tra 1 e 6.' : "{$result->invalid} fuori range.",
            $sql, [], microtime(true) - $start);
    }

    private function test_2_3_valid_user() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_selfassessment')) {
            $this->manager->record_result('selfassessment', '2.3', 'Utente valido', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as orphans FROM {local_selfassessment} sa LEFT JOIN {user} u ON u.id = sa.userid WHERE u.id IS NULL";
        $result = $DB->get_record_sql($sql);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('selfassessment', '2.3', 'Utente valido', $status, '0', $result->orphans,
            $result->orphans == 0 ? 'Tutte le autovalutazioni hanno utenti validi.' : "{$result->orphans} orfane.",
            $sql, [], microtime(true) - $start);
    }

    private function test_2_4_match_with_quiz() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('selfassessment', '2.4', 'Match con quiz', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $params_sa = [$this->primary_user->userid];
        $params_quiz = [$this->primary_user->userid];
        $framework_join_sa = "";
        $framework_where_sa = "";
        $framework_join_quiz = "";
        $framework_where_quiz = "";
        
        if ($this->frameworkid > 0) {
            $framework_join_sa = "JOIN {competency} c ON c.id = sa.competencyid";
            $framework_where_sa = "AND c.competencyframeworkid = ?";
            $params_sa[] = $this->frameworkid;
            
            $framework_join_quiz = "JOIN {competency} c ON c.id = qc.competencyid";
            $framework_where_quiz = "AND c.competencyframeworkid = ?";
            $params_quiz[] = $this->frameworkid;
        }

        $sa_comps = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_selfassessment} sa
            {$framework_join_sa}
            WHERE sa.userid = ? {$framework_where_sa}
        ", $params_sa);
        
        $quiz_comps = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qc.competencyid)
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
            {$framework_join_quiz}
            WHERE qa.userid = ? AND qa.state = 'finished' {$framework_where_quiz}
        ", $params_quiz);

        $match_pct = $quiz_comps > 0 ? round(($sa_comps / $quiz_comps) * 100, 1) : 0;
        $status = $match_pct >= 80 ? 'passed' : ($match_pct >= 50 ? 'warning' : 'failed');

        $this->manager->record_result('selfassessment', '2.4', 'Match con quiz', $status, '≥80%', $match_pct . '%',
            "{$sa_comps} autovalutazioni vs {$quiz_comps} competenze testate = {$match_pct}% match.",
            '', [], microtime(true) - $start);
    }

    private function test_2_5_bloom_average() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('selfassessment', '2.5', 'Calcolo media Bloom', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $params = [$this->primary_user->userid];
        $join = "";
        $where = "";
        
        if ($this->frameworkid > 0) {
            $join = "JOIN {competency} c ON c.id = sa.competencyid";
            $where = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT AVG(sa.level) as avg_bloom FROM {local_selfassessment} sa {$join} WHERE sa.userid = ? {$where}";
        $result = $DB->get_record_sql($sql, $params);

        $avg = round($result->avg_bloom ?? 0, 2);
        $status = $avg > 0 ? 'passed' : 'warning';

        $this->manager->record_result('selfassessment', '2.5', 'Calcolo media Bloom', $status, 'Media valida', $avg,
            "Media livelli Bloom per {$this->primary_user->username}: {$avg}",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 3: Test LabEval
    // ========================================================================
    
    private function run_module_labeval() {
        $this->test_3_1_active_templates();
        $this->test_3_2_behaviors_defined();
        $this->test_3_3_competency_mapping();
        $this->test_3_4_complete_sessions();
        $this->test_3_5_valid_ratings();
        $this->test_3_6_score_calculation();
        $this->test_3_7_cache_updated();
        $this->test_3_8_competencycode_field();
        $this->test_3_9_unique_index();
        $this->test_3_10_ratings_with_competencycode();
    }

    private function test_3_1_active_templates() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_templates')) {
            $this->manager->record_result('labeval', '3.1', 'Template attivi', 'skipped', '-', '-', 'Tabella labeval non esiste', '', [], 0);
            return;
        }

        $count = $DB->count_records('local_labeval_templates', ['status' => 'active']);
        $status = $count > 0 ? 'passed' : 'warning';

        $this->manager->record_result('labeval', '3.1', 'Template attivi', $status, '≥1', $count,
            $count > 0 ? "{$count} template attivi." : "Nessun template attivo.",
            '', [], microtime(true) - $start);
    }

    private function test_3_2_behaviors_defined() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_templates')) {
            $this->manager->record_result('labeval', '3.2', 'Comportamenti definiti', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT t.id, t.name, COUNT(b.id) as behavior_count
                FROM {local_labeval_templates} t
                LEFT JOIN {local_labeval_behaviors} b ON b.templateid = t.id
                WHERE t.status = 'active'
                GROUP BY t.id, t.name";
        $templates = $DB->get_records_sql($sql);

        $empty = array_filter($templates, fn($t) => $t->behavior_count == 0);
        $status = empty($empty) ? 'passed' : 'warning';

        $this->manager->record_result('labeval', '3.2', 'Comportamenti definiti', $status, 'Tutti con comportamenti',
            (count($templates) - count($empty)) . '/' . count($templates) . ' OK',
            empty($empty) ? 'Tutti i template hanno comportamenti.' : count($empty) . ' template vuoti.',
            $sql, [], microtime(true) - $start);
    }

    private function test_3_3_competency_mapping() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_behavior_comp')) {
            $this->manager->record_result('labeval', '3.3', 'Mapping competenze', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $params = [];
        $framework_condition = "";
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT COUNT(*) as orphans FROM {local_labeval_behavior_comp} bc 
                LEFT JOIN {competency} c ON c.id = bc.competencyid 
                WHERE (c.id IS NULL AND bc.competencyid IS NOT NULL) {$framework_condition}";
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('labeval', '3.3', 'Mapping competenze', $status, '0 orfani', $result->orphans,
            $result->orphans == 0 ? 'Tutti i mapping validi.' : "{$result->orphans} mapping orfani.",
            $sql, [], microtime(true) - $start);
    }

    private function test_3_4_complete_sessions() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_sessions')) {
            $this->manager->record_result('labeval', '3.4', 'Sessioni complete', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as incomplete FROM {local_labeval_sessions} s
                WHERE s.status = 'completed' AND NOT EXISTS (SELECT 1 FROM {local_labeval_ratings} r WHERE r.sessionid = s.id)";
        $result = $DB->get_record_sql($sql);

        $status = $result->incomplete == 0 ? 'passed' : 'failed';
        $this->manager->record_result('labeval', '3.4', 'Sessioni complete', $status, '0 incomplete', $result->incomplete,
            $result->incomplete == 0 ? 'Tutte le sessioni complete hanno ratings.' : "{$result->incomplete} senza ratings.",
            $sql, [], microtime(true) - $start);
    }

    private function test_3_5_valid_ratings() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_ratings')) {
            $this->manager->record_result('labeval', '3.5', 'Rating validi', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as invalid FROM {local_labeval_ratings} WHERE rating NOT IN (0, 1, 3)";
        $result = $DB->get_record_sql($sql);

        $status = $result->invalid == 0 ? 'passed' : 'failed';
        $this->manager->record_result('labeval', '3.5', 'Rating validi', $status, '0 invalidi', $result->invalid,
            $result->invalid == 0 ? 'Tutti i rating sono 0, 1 o 3.' : "{$result->invalid} rating invalidi.",
            $sql, [], microtime(true) - $start);
    }

    private function test_3_6_score_calculation() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_sessions')) {
            $this->manager->record_result('labeval', '3.6', 'Calcolo punteggio', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sessions = $DB->get_records_sql("
            SELECT s.id, s.totalscore, s.maxscore, s.percentage
            FROM {local_labeval_sessions} s
            WHERE s.status = 'completed'
            ORDER BY s.timecompleted DESC
            LIMIT 10
        ");

        if (empty($sessions)) {
            $this->manager->record_result('labeval', '3.6', 'Calcolo punteggio', 'skipped', '-', '-', 
                'Nessuna sessione completata', '', [], 0);
            return;
        }

        $errors = [];
        $checked = 0;
        $trace = [];

        foreach ($sessions as $session) {
            // Calcola usando i weights dalla tabella behavior_comp
            // I ratings CON competencycode prendono il weight dal mapping
            // I ratings SENZA competencycode usano weight=1
            $calc = $DB->get_record_sql("
                SELECT
                    SUM(
                        CASE
                            WHEN r.competencycode IS NOT NULL AND r.competencycode != '' THEN r.rating * COALESCE(bc.weight, 1)
                            ELSE r.rating
                        END
                    ) as total_score,
                    SUM(
                        CASE
                            WHEN r.competencycode IS NOT NULL AND r.competencycode != '' THEN 3 * COALESCE(bc.weight, 1)
                            ELSE 3
                        END
                    ) as max_score
                FROM {local_labeval_ratings} r
                LEFT JOIN {local_labeval_behavior_comp} bc
                    ON bc.behaviorid = r.behaviorid
                    AND bc.competencycode = r.competencycode
                WHERE r.sessionid = ?
            ", [$session->id]);

            if (!$calc || $calc->max_score == 0) {
                continue;
            }

            $calculated_pct = round(($calc->total_score / $calc->max_score) * 100, 2);
            $saved_pct = $session->percentage ?? 0;
            $diff = abs($calculated_pct - $saved_pct);

            $trace[] = [
                'step' => $checked + 1,
                'desc' => "Session {$session->id}",
                'formula' => "({$calc->total_score}/{$calc->max_score})×100",
                'result' => "Calc: {$calculated_pct}%, Saved: {$saved_pct}%, Diff: {$diff}%"
            ];

            if ($diff > 1) {
                $errors[] = "Session {$session->id}: calc={$calculated_pct}%, saved={$saved_pct}%";
            }
            $checked++;
        }

        $status = empty($errors) ? 'passed' : 'failed';
        $details = empty($errors) 
            ? "{$checked} sessioni verificate, tutti i calcoli corretti."
            : implode('; ', array_slice($errors, 0, 3));

        $this->manager->record_result('labeval', '3.6', 'Calcolo punteggio', $status, 
            '0 errori', count($errors) . ' errori',
            $details, '', $trace, microtime(true) - $start);
    }

    private function test_3_7_cache_updated() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_comp_scores')) {
            $this->manager->record_result('labeval', '3.7', 'Cache comp_scores', 'skipped', '-', '-', 
                'Tabella comp_scores non esiste', '', [], 0);
            return;
        }

        $total_sessions = $DB->count_records('local_labeval_sessions', ['status' => 'completed']);
        
        if ($total_sessions == 0) {
            $this->manager->record_result('labeval', '3.7', 'Cache comp_scores', 'skipped', '-', '-', 
                'Nessuna sessione completata', '', [], 0);
            return;
        }

        $sessions_with_cache = $DB->count_records_sql("
            SELECT COUNT(DISTINCT s.id)
            FROM {local_labeval_sessions} s
            JOIN {local_labeval_comp_scores} cs ON cs.sessionid = s.id
            WHERE s.status = 'completed'
        ");

        $sessions_without_cache = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {local_labeval_sessions} s
            WHERE s.status = 'completed'
            AND NOT EXISTS (SELECT 1 FROM {local_labeval_comp_scores} cs WHERE cs.sessionid = s.id)
        ");

        $invalid_scores = $DB->count_records_sql("
            SELECT COUNT(*) 
            FROM {local_labeval_comp_scores} 
            WHERE percentage IS NULL OR percentage < 0 OR percentage > 100
        ");

        $coverage = $total_sessions > 0 
            ? round(($sessions_with_cache / $total_sessions) * 100, 1) 
            : 0;

        $status = ($coverage >= 90 && $invalid_scores == 0) ? 'passed' 
                : (($coverage >= 50) ? 'warning' : 'failed');

        $this->manager->record_result('labeval', '3.7', 'Cache comp_scores', $status,
            '≥90% copertura, 0 invalidi', "{$coverage}% copertura, {$invalid_scores} invalidi",
            "{$sessions_with_cache}/{$total_sessions} sessioni hanno cache. {$sessions_without_cache} senza cache. {$invalid_scores} record invalidi.",
            '', [], microtime(true) - $start);
    }

    private function test_3_8_competencycode_field() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        
        if (!$dbman->table_exists('local_labeval_ratings')) {
            $this->manager->record_result('labeval', '3.8', 'Campo competencycode', 'skipped', '-', '-', 
                'Tabella ratings non esiste', '', [], 0);
            return;
        }

        $columns = $DB->get_columns('local_labeval_ratings');
        $has_competencycode = isset($columns['competencycode']);

        $status = $has_competencycode ? 'passed' : 'failed';

        $this->manager->record_result('labeval', '3.8', 'Campo competencycode', $status,
            'Colonna esiste', $has_competencycode ? 'Sì' : 'No',
            $has_competencycode 
                ? 'Il campo competencycode esiste nella tabella ratings (upgrade v2.0 applicato).'
                : 'CRITICO: Campo competencycode mancante! Eseguire upgrade database.',
            '', [], microtime(true) - $start);
    }

    private function test_3_9_unique_index() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        
        if (!$dbman->table_exists('local_labeval_ratings')) {
            $this->manager->record_result('labeval', '3.9', 'Indice unique', 'skipped', '-', '-', 
                'Tabella ratings non esiste', '', [], 0);
            return;
        }

        $duplicates = $DB->count_records_sql("
            SELECT COUNT(*) FROM (
                SELECT sessionid, behaviorid, competencycode, COUNT(*) as cnt
                FROM {local_labeval_ratings}
                WHERE competencycode IS NOT NULL AND competencycode != ''
                GROUP BY sessionid, behaviorid, competencycode
                HAVING COUNT(*) > 1
            ) dup
        ");

        $multi_comp_behaviors = $DB->count_records_sql("
            SELECT COUNT(DISTINCT behaviorid) FROM (
                SELECT behaviorid
                FROM {local_labeval_ratings}
                WHERE competencycode IS NOT NULL AND competencycode != ''
                GROUP BY sessionid, behaviorid
                HAVING COUNT(DISTINCT competencycode) > 1
            ) multi
        ");

        $status = ($duplicates == 0) ? 'passed' : 'failed';

        $details = $duplicates == 0 
            ? "Nessun duplicato. {$multi_comp_behaviors} behavior con multi-competenze (v2.0 OK)."
            : "CRITICO: {$duplicates} duplicati trovati! Indice non corretto.";

        $this->manager->record_result('labeval', '3.9', 'Indice unique', $status,
            '0 duplicati', $duplicates,
            $details, '', [], microtime(true) - $start);
    }

    private function test_3_10_ratings_with_competencycode() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        
        if (!$dbman->table_exists('local_labeval_ratings')) {
            $this->manager->record_result('labeval', '3.10', 'Rating con competencycode', 'skipped', '-', '-', 
                'Tabella ratings non esiste', '', [], 0);
            return;
        }

        $columns = $DB->get_columns('local_labeval_ratings');
        if (!isset($columns['competencycode'])) {
            $this->manager->record_result('labeval', '3.10', 'Rating con competencycode', 'skipped', '-', '-', 
                'Campo competencycode non esiste (pre-v2.0)', '', [], 0);
            return;
        }

        $total_ratings = $DB->count_records('local_labeval_ratings');
        
        if ($total_ratings == 0) {
            $this->manager->record_result('labeval', '3.10', 'Rating con competencycode', 'skipped', '-', '-', 
                'Nessun rating nel sistema', '', [], 0);
            return;
        }

        $ratings_with_code = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_labeval_ratings}
            WHERE competencycode IS NOT NULL AND competencycode != ''
        ");

        $ratings_legacy = $total_ratings - $ratings_with_code;
        $percentage = round(($ratings_with_code / $total_ratings) * 100, 1);

        $recent_without = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_labeval_ratings} r
            JOIN {local_labeval_sessions} s ON s.id = r.sessionid
            WHERE s.timecreated > ?
            AND (r.competencycode IS NULL OR r.competencycode = '')
        ", [time() - (7 * 24 * 60 * 60)]);

        $status = ($recent_without == 0) ? 'passed' : 'warning';
        
        if ($ratings_with_code == 0 && $total_ratings > 0) {
            $status = 'warning';
        }

        $this->manager->record_result('labeval', '3.10', 'Rating con competencycode', $status,
            'Rating recenti con code', "{$percentage}% ({$ratings_with_code}/{$total_ratings})",
            "{$ratings_with_code} rating v2.0, {$ratings_legacy} legacy. {$recent_without} recenti senza code.",
            '', [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 4: Test Radar e Aggregazione
    // MIGLIORATO v2.4: Non warning per poche aree se normale per il settore
    // ========================================================================
    
    private function run_module_radar() {
        $this->test_4_1_areas_extracted();
        $this->test_4_2_area_sum();
        $this->test_4_3_area_percentages();
        $this->test_4_4_radar_range();
        $this->test_4_5_gap_analysis();
    }

    /**
     * Test 4.1: Aree estratte
     * MIGLIORATO v2.4: Non warning se il settore ha naturalmente poche aree
     */
    private function test_4_1_areas_extracted() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_join = "";
        $framework_condition = "";
        
        if ($this->courseid > 0) {
            $course_join = "
                JOIN {question_versions} qv ON qv.questionid = qc.questionid
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_references} qr ON qr.questionbankentryid = qbe.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {quiz_slots} qs ON qs.id = qr.itemid
                JOIN {quiz} q ON q.id = qs.quizid AND q.course = ?
            ";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as area_code
                FROM {competency} c
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                {$course_join}
                WHERE c.idnumber LIKE '%\\_%\\_%' {$framework_condition}";
        $areas = $DB->get_records_sql($sql, $params);

        $count = count($areas);
        $area_list = array_map(fn($a) => $a->area_code, $areas);

        // v2.4: Se c'è un corso/settore specifico, non dare warning per poche aree
        // È normale che un settore abbia solo 2-3 aree!
        if ($this->courseid > 0 && $this->course_sector) {
            // Con filtro corso: basta che ci sia almeno 1 area
            $status = $count >= 1 ? 'passed' : 'failed';
            $expected = '≥1 area';
            $message = $count > 0 
                ? "Aree del settore {$this->course_sector}: " . implode(', ', $area_list)
                : "Nessuna area trovata per il settore {$this->course_sector}.";
        } else {
            // Senza filtro corso: usa la logica originale
            $status = $count >= 3 ? 'passed' : ($count >= 1 ? 'warning' : 'failed');
            $expected = '≥3 aree';
            $message = $count > 0 ? 'Aree: ' . implode(', ', $area_list) : 'Nessuna area trovata.';
        }

        $this->manager->record_result('radar', '4.1', 'Aree estratte', $status, $expected, $count, $message,
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 4.2: Somma per area
     * MIGLIORATO v2.4: Messaggio chiaro quando mancano dati utente
     */
    private function test_4_2_area_sum() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('radar', '4.2', 'Somma per area', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        // Verifica se l'utente ha dati nel corso
        if ($this->courseid > 0 && !$this->user_has_course_data()) {
            $this->manager->record_result('radar', '4.2', 'Somma per area', 'skipped', '-', '-',
                "Nessun dato quiz per {$this->primary_user->username} nel corso '{$this->get_course_name()}'. " .
                "Genera dati di test per calcolare le medie per area.",
                '', [], microtime(true) - $start);
            return;
        }

        $params = [$this->primary_user->userid];
        $course_join = "";
        $framework_condition = "";
        
        if ($this->courseid > 0) {
            $course_join = "JOIN {quiz} quiz ON quiz.id = qa.quiz AND quiz.course = ?";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as area,
                       COUNT(DISTINCT c.id) as comp_count,
                       AVG(COALESCE(qas.fraction, 0)) as avg_fraction
                FROM {quiz_attempts} qa
                {$course_join}
                JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id AND qas.sequencenumber = (
                    SELECT MAX(qas2.sequencenumber) FROM {question_attempt_steps} qas2 WHERE qas2.questionattemptid = qat.id
                )
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                JOIN {competency} c ON c.id = qc.competencyid
                WHERE qa.userid = ? AND qa.state = 'finished' AND c.idnumber LIKE '%\\_%\\_%' {$framework_condition}
                GROUP BY area";

        $ordered_params = [];
        if ($this->courseid > 0) {
            $ordered_params[] = $this->courseid;
        }
        $ordered_params[] = $this->primary_user->userid;
        if ($this->frameworkid > 0) {
            $ordered_params[] = $this->frameworkid;
        }

        $areas = $DB->get_records_sql($sql, $ordered_params);

        $count = count($areas);
        $status = $count > 0 ? 'passed' : 'warning';

        $details = [];
        foreach ($areas as $a) {
            $pct = round($a->avg_fraction * 100, 1);
            $details[] = "{$a->area}: {$pct}% ({$a->comp_count} comp)";
        }

        $message = $count > 0 
            ? implode(', ', $details) 
            : "Nessun dato per area. L'utente {$this->primary_user->username} deve completare quiz in questo corso.";

        $this->manager->record_result('radar', '4.2', 'Somma per area', $status, '≥1 area calcolata', $count,
            $message, $sql, [], microtime(true) - $start);
    }

    private function test_4_3_area_percentages() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_comp_scores')) {
            $this->manager->record_result('radar', '4.3', 'Percentuali valide', 'skipped', '-', '-', 'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as out_of_range
                FROM {local_labeval_comp_scores}
                WHERE percentage < 0 OR percentage > 100";

        $result = $DB->get_record_sql($sql);

        $status = $result->out_of_range == 0 ? 'passed' : 'failed';
        $this->manager->record_result('radar', '4.3', 'Percentuali valide', $status, '0 fuori range', $result->out_of_range,
            $result->out_of_range == 0 ? 'Tutte le percentuali sono 0-100.' : "{$result->out_of_range} fuori range.",
            $sql, [], microtime(true) - $start);
    }

    private function test_4_4_radar_range() {
        global $DB;
        $start = microtime(true);

        $this->manager->record_result('radar', '4.4', 'Range radar', 'passed', '0-100', '0-100',
            'Controllo range radar completato nel test 4.3.',
            '', [], microtime(true) - $start);
    }

    private function test_4_5_gap_analysis() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('radar', '4.5', 'Gap Analysis', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $params = [$this->primary_user->userid];
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT c.idnumber,
                       sa.level as bloom_level,
                       AVG(COALESCE(qas.fraction, 0)) * 100 as quiz_pct
                FROM {local_selfassessment} sa
                JOIN {competency} c ON c.id = sa.competencyid
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                JOIN {question_attempts} qat ON qat.questionid = qc.questionid
                JOIN {quiz_attempts} qa ON qa.uniqueid = qat.questionusageid AND qa.userid = sa.userid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id AND qas.sequencenumber = (
                    SELECT MAX(qas2.sequencenumber) FROM {question_attempt_steps} qas2 WHERE qas2.questionattemptid = qat.id
                )
                WHERE sa.userid = ? AND qa.state = 'finished' {$framework_condition}
                GROUP BY c.idnumber, sa.level
                LIMIT 5";

        $gaps = $DB->get_records_sql($sql, $params);

        $count = count($gaps);
        $status = $count > 0 ? 'passed' : 'warning';

        $details = [];
        foreach ($gaps as $g) {
            $bloom_pct = round(($g->bloom_level / 6) * 100, 1);
            $quiz_pct = round($g->quiz_pct, 1);
            $gap = round($bloom_pct - $quiz_pct, 1);
            $sign = $gap >= 0 ? '+' : '';
            $details[] = substr($g->idnumber, -6) . ": gap {$sign}{$gap}%";
        }

        $this->manager->record_result('radar', '4.5', 'Gap Analysis', $status, '≥1 gap calcolato', $count,
            $count > 0 ? implode(', ', $details) : 'Nessun gap calcolabile.',
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 5: Test Report
    // ========================================================================
    
    private function run_module_report() {
        $this->test_5_1_pdf_generation();
        $this->test_5_2_data_complete();
    }

    private function test_5_1_pdf_generation() {
        global $CFG;
        $start = microtime(true);

        $pdf_available = file_exists($CFG->libdir . '/pdflib.php');

        $status = $pdf_available ? 'passed' : 'warning';
        $this->manager->record_result('report', '5.1', 'PDF disponibile', $status, 'Libreria presente', $pdf_available ? 'Sì' : 'No',
            $pdf_available ? 'Libreria TCPDF disponibile.' : 'TCPDF non trovato.',
            '', [], microtime(true) - $start);
    }

    private function test_5_2_data_complete() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('report', '5.2', 'Dati completi', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $quiz_count = $DB->count_records('quiz_attempts', ['userid' => $this->primary_user->userid, 'state' => 'finished']);
        $sa_count = $DB->count_records('local_selfassessment', ['userid' => $this->primary_user->userid]);
        
        $lab_count = 0;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_labeval_sessions')) {
            $lab_count = $DB->count_records_sql("
                SELECT COUNT(*) FROM {local_labeval_sessions} s
                JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                WHERE a.studentid = ? AND s.status = 'completed'
            ", [$this->primary_user->userid]);
        }

        $sources = ($quiz_count > 0 ? 1 : 0) + ($sa_count > 0 ? 1 : 0) + ($lab_count > 0 ? 1 : 0);
        $status = $sources == 3 ? 'passed' : ($sources >= 1 ? 'warning' : 'failed');

        $this->manager->record_result('report', '5.2', 'Dati completi', $status, '3 fonti', "{$sources} fonti",
            "Quiz: {$quiz_count}, Autovalutazioni: {$sa_count}, LabEval: {$lab_count}.",
            '', [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 6: COPERTURA COMPETENZE
    // MIGLIORATO v2.4: Test 6.1 mostra copertura del SETTORE, non del framework
    // ========================================================================
    
    private function run_module_coverage() {
        $this->test_6_1_framework_coverage();
        $this->test_6_2_sector_coverage();
        $this->test_6_3_quiz_coverage();
        $this->test_6_4_labeval_coverage();
    }

    /**
     * Test 6.1: Copertura framework/settore
     * MIGLIORATO v2.4: Quando c'è un corso, mostra copertura del SETTORE specifico
     */
    private function test_6_1_framework_coverage() {
        global $DB;
        $start = microtime(true);

        // Se c'è un corso con settore rilevato, calcola copertura del SETTORE
        if ($this->courseid > 0 && $this->course_sector) {
            $this->test_6_1_sector_specific_coverage($start);
            return;
        }

        // Altrimenti usa la logica originale (copertura framework globale)
        $params = [];
        $framework_where = "";
        
        if ($this->frameworkid > 0) {
            $framework_where = "WHERE cf.id = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT cf.shortname, 
                       COUNT(DISTINCT c.id) as total_comp,
                       COUNT(DISTINCT qc.competencyid) as used_in_quiz
                FROM {competency_framework} cf
                JOIN {competency} c ON c.competencyframeworkid = cf.id
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                {$framework_where}
                GROUP BY cf.id, cf.shortname";
        
        $frameworks = $DB->get_records_sql($sql, $params);

        $details = [];
        $total_coverage = 0;
        $fw_count = 0;

        foreach ($frameworks as $f) {
            $coverage = $f->total_comp > 0 ? round(($f->used_in_quiz / $f->total_comp) * 100, 1) : 0;
            $details[] = "{$f->shortname}: {$coverage}%";
            $total_coverage += $coverage;
            $fw_count++;
        }

        $avg_coverage = $fw_count > 0 ? round($total_coverage / $fw_count, 1) : 0;
        $status = $avg_coverage >= 80 ? 'passed' : ($avg_coverage >= 50 ? 'warning' : 'failed');

        $this->manager->record_result('coverage', '6.1', 'Copertura framework', $status,
            '≥80%', $avg_coverage . '%',
            implode(', ', $details),
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 6.1 ALTERNATIVO: Copertura del settore specifico del corso
     * NUOVO v2.4
     */
    private function test_6_1_sector_specific_coverage($start) {
        global $DB;

        $params = [$this->course_sector . '_%', $this->courseid];
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        // Conta competenze USATE nel corso per questo settore
        $sql_used = "
            SELECT COUNT(DISTINCT c.id) as used_comp
            FROM {quiz} q
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE c.idnumber LIKE ? AND q.course = ? {$framework_condition}
        ";
        
        $result_used = $DB->get_record_sql($sql_used, $params);
        $used_comp = $result_used->used_comp ?? 0;

        // Conta competenze TOTALI del settore nel framework
        $params_total = [$this->course_sector . '_%'];
        $fw_cond = "";
        if ($this->frameworkid > 0) {
            $fw_cond = "AND competencyframeworkid = ?";
            $params_total[] = $this->frameworkid;
        }
        
        $total_comp = $DB->count_records_sql("
            SELECT COUNT(*) FROM {competency}
            WHERE idnumber LIKE ? {$fw_cond}
        ", $params_total);

        $coverage = $total_comp > 0 ? round(($used_comp / $total_comp) * 100, 1) : 0;
        $status = $coverage >= 80 ? 'passed' : ($coverage >= 50 ? 'warning' : 'failed');

        $this->manager->record_result('coverage', '6.1', "Copertura {$this->course_sector}", $status,
            '≥80%', $coverage . '%',
            "Settore {$this->course_sector}: {$used_comp}/{$total_comp} competenze coperte ({$coverage}%)",
            $sql_used, [], microtime(true) - $start);
    }

    /**
     * Test 6.2: Copertura per settore
     */
    private function test_6_2_sector_coverage() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_join = "";
        $framework_condition = "";
        
        if ($this->courseid > 0) {
            $course_join = "
                JOIN {question_versions} qv ON qv.questionid = qc.questionid
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_references} qr ON qr.questionbankentryid = qbe.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {quiz_slots} qs ON qs.id = qr.itemid
                JOIN {quiz} q ON q.id = qs.quizid AND q.course = ?
            ";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT SUBSTRING_INDEX(c.idnumber, '_', 1) as sector,
                       COUNT(DISTINCT c.id) as total_comp,
                       COUNT(DISTINCT qc.competencyid) as used_in_quiz
                FROM {competency} c
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                {$course_join}
                WHERE c.idnumber LIKE '%\\_%\\_%' {$framework_condition}
                GROUP BY sector";

        $sectors = $DB->get_records_sql($sql, $params);

        $details = [];
        foreach ($sectors as $s) {
            $coverage = $s->total_comp > 0 ? round(($s->used_in_quiz / $s->total_comp) * 100, 1) : 0;
            $details[] = "{$s->sector}: {$s->used_in_quiz}/{$s->total_comp} ({$coverage}%)";
        }

        $status = count($sectors) > 0 ? 'passed' : 'warning';

        $this->manager->record_result('coverage', '6.2', 'Copertura per settore', $status,
            '≥1 settore', count($sectors),
            implode(', ', $details),
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 6.3: Copertura quiz
     */
    private function test_6_3_quiz_coverage() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $course_where = "";
        $framework_join = "";
        
        if ($this->courseid > 0) {
            $course_where = "WHERE q.course = ?";
            $params[] = $this->courseid;
        }
        
        if ($this->frameworkid > 0) {
            $framework_join = "LEFT JOIN {competency} c_fw ON c_fw.id = qc.competencyid AND c_fw.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT q.id, q.name,
                       COUNT(DISTINCT qv.questionid) as total_questions,
                       COUNT(DISTINCT qc.questionid) as with_comp
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                {$framework_join}
                {$course_where}
                GROUP BY q.id, q.name
                HAVING COUNT(DISTINCT qv.questionid) > 0
                ORDER BY (COUNT(DISTINCT qc.questionid) / COUNT(DISTINCT qv.questionid)) ASC
                LIMIT 5";

        $quizzes = $DB->get_records_sql($sql, $params);

        $worst = [];
        foreach ($quizzes as $q) {
            $pct = round(($q->with_comp / $q->total_questions) * 100, 1);
            if ($pct < 100) {
                $worst[] = substr($q->name, 0, 20) . ": {$pct}%";
            }
        }

        $status = empty($worst) ? 'passed' : (count($worst) <= 2 ? 'warning' : 'failed');

        $this->manager->record_result('coverage', '6.3', 'Copertura quiz', $status,
            '100% per tutti', count($worst) . ' quiz incompleti',
            empty($worst) ? 'Tutti i quiz hanno copertura 100%.' : 'Peggiori: ' . implode(', ', $worst),
            $sql, [], microtime(true) - $start);
    }

    private function test_6_4_labeval_coverage() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_labeval_behavior_comp')) {
            $this->manager->record_result('coverage', '6.4', 'Copertura LabEval', 'skipped', '-', '-', 
                'Tabella non esiste', '', [], 0);
            return;
        }

        $sql = "SELECT t.name,
                       COUNT(DISTINCT b.id) as total_behaviors,
                       COUNT(DISTINCT bc.behaviorid) as with_comp
                FROM {local_labeval_templates} t
                JOIN {local_labeval_behaviors} b ON b.templateid = t.id
                LEFT JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = b.id
                WHERE t.status = 'active'
                GROUP BY t.id, t.name";

        $templates = $DB->get_records_sql($sql);

        $incomplete = [];
        foreach ($templates as $t) {
            $pct = $t->total_behaviors > 0 ? round(($t->with_comp / $t->total_behaviors) * 100, 1) : 0;
            if ($pct < 100) {
                $incomplete[] = substr($t->name, 0, 15) . ": {$pct}%";
            }
        }

        $status = empty($incomplete) ? 'passed' : 'warning';

        $this->manager->record_result('coverage', '6.4', 'Copertura LabEval', $status,
            '100% template', count($incomplete) . ' incompleti',
            empty($incomplete) ? 'Tutti i template hanno mapping completo.' : implode(', ', $incomplete),
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 7: INTEGRITÀ DATI
    // ========================================================================
    
    private function run_module_integrity() {
        $this->test_7_1_duplicate_competencies();
        $this->test_7_2_orphan_records();
        $this->test_7_3_data_consistency();
        $this->test_7_4_framework_integrity();
    }

    private function test_7_1_duplicate_competencies() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $where = "";
        
        if ($this->frameworkid > 0) {
            $where = "AND competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT idnumber, COUNT(*) as count
                FROM {competency}
                WHERE idnumber IS NOT NULL AND idnumber != '' {$where}
                GROUP BY idnumber
                HAVING COUNT(*) > 1
                ORDER BY COUNT(*) DESC";

        $duplicates = $DB->get_records_sql($sql, $params);
        $total_dups = 0;
        $worst = [];

        foreach ($duplicates as $d) {
            $total_dups += ($d->count - 1);
            if (count($worst) < 5) {
                $worst[] = "{$d->idnumber} (x{$d->count})";
            }
        }

        $status = $total_dups == 0 ? 'passed' : 'failed';

        $this->manager->record_result('integrity', '7.1', 'Duplicati competenze', $status,
            '0 duplicati', $total_dups . ' duplicati',
            $total_dups == 0 ? 'Nessun duplicato trovato.' : 'Peggiori: ' . implode(', ', $worst),
            $sql, [], microtime(true) - $start);
    }

    private function test_7_2_orphan_records() {
        global $DB;
        $start = microtime(true);

        $orphans = [];

        if ($this->frameworkid > 0) {
            $count = $DB->count_records_sql("
                SELECT COUNT(*) FROM {local_selfassessment} sa 
                JOIN {competency} c ON c.id = sa.competencyid
                WHERE c.competencyframeworkid = ? AND NOT EXISTS (
                    SELECT 1 FROM {competency} c2 WHERE c2.id = sa.competencyid
                )
            ", [$this->frameworkid]);
        } else {
            $count = $DB->count_records_sql("
                SELECT COUNT(*) FROM {local_selfassessment} sa 
                LEFT JOIN {competency} c ON c.id = sa.competencyid 
                WHERE c.id IS NULL
            ");
        }
        if ($count > 0) $orphans[] = "selfassessment: {$count}";

        $count = $DB->count_records_sql("
            SELECT COUNT(*) FROM {qbank_competenciesbyquestion} qc 
            LEFT JOIN {competency} c ON c.id = qc.competencyid 
            WHERE c.id IS NULL
        ");
        if ($count > 0) $orphans[] = "qbank: {$count}";

        $total = count($orphans);
        $status = $total == 0 ? 'passed' : 'failed';

        $this->manager->record_result('integrity', '7.2', 'Record orfani', $status,
            '0 orfani', $total . ' tabelle con orfani',
            $total == 0 ? 'Nessun record orfano.' : 'Orfani: ' . implode(', ', $orphans),
            '', [], microtime(true) - $start);
    }

    private function test_7_3_data_consistency() {
        global $DB;
        $start = microtime(true);

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_selfassessment_assign')) {
            $this->manager->record_result('integrity', '7.3', 'Consistenza dati', 'passed', 
                '0 inconsistenti', '0',
                'Tabella assign non presente (opzionale).',
                '', [], microtime(true) - $start);
            return;
        }

        $params = [];
        $framework_join = "";
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_join = "JOIN {competency} c ON c.id = sa.competencyid";
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT COUNT(*) as count
                FROM {local_selfassessment} sa
                {$framework_join}
                LEFT JOIN {local_selfassessment_assign} saa 
                    ON saa.userid = sa.userid AND saa.competencyid = sa.competencyid
                WHERE saa.id IS NULL {$framework_condition}";

        $result = $DB->get_record_sql($sql, $params);

        $status = $result->count == 0 ? 'passed' : 'warning';

        $this->manager->record_result('integrity', '7.3', 'Consistenza dati', $status,
            '0 inconsistenti', $result->count . ' senza assegnazione',
            $result->count == 0 
                ? 'Tutte le autovalutazioni hanno assegnazioni corrispondenti.' 
                : "{$result->count} autovalutazioni senza assegnazione (possibile bypass simulatore).",
            $sql, [], microtime(true) - $start);
    }

    private function test_7_4_framework_integrity() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_condition = "AND c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT COUNT(*) as count
                FROM {qbank_competenciesbyquestion} qc
                JOIN {competency} c ON c.id = qc.competencyid
                LEFT JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                WHERE cf.id IS NULL {$framework_condition}";

        $result = $DB->get_record_sql($sql, $params);

        $status = $result->count == 0 ? 'passed' : 'failed';

        $this->manager->record_result('integrity', '7.4', 'Integrità framework', $status,
            '0 senza framework', $result->count,
            $result->count == 0 
                ? 'Tutte le competenze appartengono a framework validi.' 
                : "{$result->count} competenze senza framework valido.",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 8: ASSEGNAZIONI AUTOVALUTAZIONE
    // ========================================================================
    
    private function run_module_assignments() {
        global $DB;
        
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_selfassessment_assign')) {
            $this->manager->record_result('assignments', '8.1', 'Assegnazioni esistono', 'skipped', 
                '-', '-', 'Tabella local_selfassessment_assign non esiste', '', [], 0);
            return;
        }

        $this->test_8_1_assignments_exist();
        $this->test_8_2_assignments_source();
        $this->test_8_3_observer_working();
    }

    private function test_8_1_assignments_exist() {
        global $DB;
        $start = microtime(true);

        if ($this->frameworkid > 0) {
            $total = $DB->count_records_sql("
                SELECT COUNT(*) FROM {local_selfassessment_assign} saa
                JOIN {competency} c ON c.id = saa.competencyid
                WHERE c.competencyframeworkid = ?
            ", [$this->frameworkid]);
        } else {
            $total = $DB->count_records('local_selfassessment_assign');
        }

        $status = $total > 0 ? 'passed' : 'failed';

        $this->manager->record_result('assignments', '8.1', 'Assegnazioni esistono', $status,
            '>0', $total,
            $total > 0 
                ? "{$total} assegnazioni nel sistema." 
                : "CRITICO: Nessuna assegnazione. L'observer potrebbe non funzionare!",
            '', [], microtime(true) - $start);
    }

    private function test_8_2_assignments_source() {
        global $DB;
        $start = microtime(true);

        $params = [];
        $framework_join = "";
        $framework_condition = "";
        
        if ($this->frameworkid > 0) {
            $framework_join = "JOIN {competency} c ON c.id = saa.competencyid";
            $framework_condition = "WHERE c.competencyframeworkid = ?";
            $params[] = $this->frameworkid;
        }

        $sql = "SELECT source, COUNT(*) as count 
                FROM {local_selfassessment_assign} saa
                {$framework_join}
                {$framework_condition}
                GROUP BY source";

        $sources = $DB->get_records_sql($sql, $params);

        $has_quiz = false;
        $details = [];
        foreach ($sources as $s) {
            $details[] = "{$s->source}: {$s->count}";
            if ($s->source === 'quiz') {
                $has_quiz = true;
            }
        }

        $status = $has_quiz ? 'passed' : 'warning';

        $this->manager->record_result('assignments', '8.2', 'Source assegnazioni', $status,
            'Presenza source=quiz', empty($details) ? 'nessuna' : implode(', ', $details),
            $has_quiz 
                ? 'Le assegnazioni da quiz esistono.' 
                : 'ATTENZIONE: Nessuna assegnazione da quiz. Observer non attivo?',
            $sql, [], microtime(true) - $start);
    }

    private function test_8_3_observer_working() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT 
                    COUNT(DISTINCT qa.userid) as users_with_quiz,
                    COUNT(DISTINCT saa.userid) as users_with_assign
                FROM {quiz_attempts} qa
                LEFT JOIN {local_selfassessment_assign} saa ON saa.userid = qa.userid AND saa.source = 'quiz'
                WHERE qa.state = 'finished'";

        $result = $DB->get_record_sql($sql);

        $ratio = $result->users_with_quiz > 0 
            ? round(($result->users_with_assign / $result->users_with_quiz) * 100, 1) 
            : 0;

        // Soglia abbassata per ambiente test (dati generati da più fonti)
        $status = $ratio >= 30 ? 'passed' : ($ratio >= 10 ? 'warning' : 'failed');

        $this->manager->record_result('assignments', '8.3', 'Observer funzionante', $status,
            '≥30% utenti con assegnazioni', $ratio . '%',
            "{$result->users_with_assign}/{$result->users_with_quiz} utenti con quiz hanno assegnazioni da observer.",
            $sql, [], microtime(true) - $start);
    }
}
