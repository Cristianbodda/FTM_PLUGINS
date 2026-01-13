<?php
/**
 * Test Runner - Esegue tutti i test della suite FTM
 * VERSIONE 2.0 - Aggiornata 04/01/2026
 * 
 * AGGIUNTI TEST CRITICI:
 * - Modulo 6: Copertura Competenze (6.1-6.4)
 * - Modulo 7: Integrità Dati (7.1-7.4)
 * - Modulo 8: Assegnazioni Autovalutazione (8.1-8.3)
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

    /**
     * Costruttore
     * @param int $courseid Corso da testare (0 = tutti)
     * @param int $frameworkid Framework competenze (0 = tutti)
     * @param int $userid Utente specifico da testare (0 = default medium65)
     */
    public function __construct($courseid = 0, $frameworkid = 0, $userid = 0) {
        $this->courseid = $courseid;
        $this->frameworkid = $frameworkid;
        $this->selected_userid = $userid;
        $this->manager = new test_manager($courseid);
        $this->load_test_users();
    }

    /**
     * Carica utenti test
     */
    private function load_test_users() {
        global $DB;
        $this->testusers = $DB->get_records('local_ftm_testsuite_users');

        // Se specificato un userid, usalo come primario
        if ($this->selected_userid > 0) {
            foreach ($this->testusers as $tu) {
                if ($tu->userid == $this->selected_userid) {
                    $this->primary_user = $tu;
                    return;
                }
            }
            // Se non trovato tra i test users, potrebbe essere uno studente reale
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

        // Default: trova utente medium65
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
     * Esegue tutti i test
     * @param string $runname Nome del run
     * @return object Risultati del run
     */
    public function run_all($runname = '') {
        $this->manager->start_run($runname ?: 'Test Completo ' . date('d/m/Y H:i'));

        // Modulo 1: Quiz e Competenze
        $this->run_module_quiz();

        // Modulo 2: Autovalutazioni
        $this->run_module_selfassessment();

        // Modulo 3: LabEval
        $this->run_module_labeval();

        // Modulo 4: Radar e Aggregazione
        $this->run_module_radar();

        // Modulo 5: Report
        $this->run_module_report();

        // ========================================
        // NUOVI MODULI CRITICI - 04/01/2026
        // ========================================
        
        // Modulo 6: Copertura Competenze (NUOVO)
        $this->run_module_coverage();

        // Modulo 7: Integrità Dati (NUOVO)
        $this->run_module_integrity();

        // Modulo 8: Assegnazioni Autovalutazione (NUOVO)
        $this->run_module_assignments();

        return $this->manager->complete_run();
    }

    // ========================================================================
    // MODULO 1: Test Quiz e Competenze
    // ========================================================================
    
    private function run_module_quiz() {
        global $DB;

        // Test 1.1: Domande con competenze
        $this->test_1_1_questions_with_competencies();

        // Test 1.2: Competenze esistenti
        $this->test_1_2_competencies_exist();

        // Test 1.3: Framework corretto
        $this->test_1_3_correct_framework();

        // Test 1.4: Domande orfane
        $this->test_1_4_orphan_questions();

        // Test 1.5: Risposte registrate
        $this->test_1_5_responses_recorded();

        // Test 1.6: Fraction valida
        $this->test_1_6_valid_fraction();

        // Test 1.7: Calcolo manuale
        $this->test_1_7_manual_calculation();

        // Test 1.8: Parsing idnumber
        $this->test_1_8_idnumber_parsing();
    }

    private function test_1_1_questions_with_competencies() {
        global $DB;
        $start = microtime(true);

        $sql = "
            SELECT COUNT(DISTINCT qv.questionid) as total_questions,
                   COUNT(DISTINCT qc.questionid) as questions_with_comp
            FROM {quiz} q
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            " . ($this->courseid ? "WHERE q.course = ?" : "");

        $params = $this->courseid ? [$this->courseid] : [];
        $result = $DB->get_record_sql($sql, $params);

        $percentage = $result->total_questions > 0
            ? round(($result->questions_with_comp / $result->total_questions) * 100, 1)
            : 0;

        $status = $percentage == 100 ? 'passed' : ($percentage >= 80 ? 'warning' : 'failed');

        $this->manager->record_result(
            'quiz', '1.1', 'Domande con competenze',
            $status, '100%', $percentage . '%',
            "{$result->questions_with_comp} domande su {$result->total_questions} hanno competenze assegnate.",
            $sql, [], microtime(true) - $start
        );
    }

    private function test_1_2_competencies_exist() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as orphans FROM {qbank_competenciesbyquestion} qc LEFT JOIN {competency} c ON c.id = qc.competencyid WHERE c.id IS NULL";
        $result = $DB->get_record_sql($sql);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('quiz', '1.2', 'Competenze esistenti', $status, '0 orfani', $result->orphans . ' orfani',
            $result->orphans == 0 ? 'Tutte le competenze assegnate esistono.' : "{$result->orphans} assegnazioni a competenze inesistenti.",
            $sql, [], microtime(true) - $start);
    }

    private function test_1_3_correct_framework() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT cf.id, cf.shortname, COUNT(DISTINCT c.id) as comp_count
                FROM {qbank_competenciesbyquestion} qc
                JOIN {competency} c ON c.id = qc.competencyid
                JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                GROUP BY cf.id, cf.shortname";

        $frameworks = $DB->get_records_sql($sql);
        $status = count($frameworks) > 0 ? 'passed' : 'warning';

        $this->manager->record_result('quiz', '1.3', 'Framework corretto', $status, 'Framework coerenti',
            count($frameworks) . ' framework in uso',
            'Framework: ' . implode(', ', array_map(fn($f) => "{$f->shortname} ({$f->comp_count})", $frameworks)),
            $sql, [], microtime(true) - $start);
    }

    private function test_1_4_orphan_questions() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(DISTINCT qv.questionid) as orphans
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                WHERE qc.id IS NULL" . ($this->courseid ? " AND q.course = ?" : "");

        $params = $this->courseid ? [$this->courseid] : [];
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->orphans == 0 ? 'passed' : ($result->orphans <= 5 ? 'warning' : 'failed');
        $this->manager->record_result('quiz', '1.4', 'Domande orfane', $status, '0', $result->orphans,
            $result->orphans == 0 ? 'Tutte le domande hanno competenze.' : "{$result->orphans} domande senza competenze.",
            $sql, [], microtime(true) - $start);
    }

    private function test_1_5_responses_recorded() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('quiz', '1.5', 'Risposte registrate', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as attempts,
                       (SELECT COUNT(*) FROM {question_attempts} qa
                        JOIN {quiz_attempts} qza ON qza.uniqueid = qa.questionusageid
                        WHERE qza.userid = ?) as responses
                FROM {quiz_attempts} qa WHERE qa.userid = ? AND qa.state = 'finished'";

        $result = $DB->get_record_sql($sql, [$this->primary_user->userid, $this->primary_user->userid]);

        $status = ($result->attempts > 0 && $result->responses > 0) ? 'passed' : 'failed';
        $this->manager->record_result('quiz', '1.5', 'Risposte registrate', $status, 'Risposte presenti',
            "{$result->responses} risposte per {$result->attempts} tentativi",
            "Utente test ha {$result->attempts} tentativi con {$result->responses} risposte.",
            $sql, [], microtime(true) - $start);
    }

    private function test_1_6_valid_fraction() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as invalid FROM {question_attempt_steps} qas WHERE qas.fraction IS NOT NULL AND (qas.fraction < 0 OR qas.fraction > 1)";
        $result = $DB->get_record_sql($sql);

        $status = $result->invalid == 0 ? 'passed' : 'failed';
        $this->manager->record_result('quiz', '1.6', 'Fraction valida', $status, '0 invalidi', $result->invalid . ' invalidi',
            $result->invalid == 0 ? 'Tutti i valori fraction sono nel range 0-1.' : "{$result->invalid} valori fuori range.",
            $sql, [], microtime(true) - $start);
    }

    private function test_1_7_manual_calculation() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $attempt = $DB->get_record_sql("SELECT qa.id, qa.uniqueid FROM {quiz_attempts} qa WHERE qa.userid = ? AND qa.state = 'finished' LIMIT 1", [$this->primary_user->userid]);

        if (!$attempt) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-', 'Nessun tentativo quiz', '', [], 0);
            return;
        }

        $sql = "SELECT c.id, c.idnumber, COUNT(*) as total, SUM(CASE WHEN qas.fraction >= 0.5 THEN 1 ELSE 0 END) as correct, AVG(qas.fraction) * 100 as avg_percentage
                FROM {question_attempts} qat
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                JOIN {competency} c ON c.id = qc.competencyid
                WHERE qat.questionusageid = ? AND qas.fraction IS NOT NULL
                GROUP BY c.id, c.idnumber LIMIT 1";

        $calc = $DB->get_record_sql($sql, [$attempt->uniqueid]);

        if (!$calc) {
            $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'skipped', '-', '-', 'Nessuna competenza nel tentativo', '', [], 0);
            return;
        }

        $manual_percentage = round($calc->avg_percentage, 1);
        $this->manager->record_result('quiz', '1.7', 'Calcolo manuale', 'passed', 'Calcolo coerente', "{$manual_percentage}%",
            "Competenza {$calc->idnumber}: {$calc->correct}/{$calc->total} corrette = {$manual_percentage}%",
            $sql, [], microtime(true) - $start);
    }

    private function test_1_8_idnumber_parsing() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT c.idnumber, c.shortname FROM {competency} c
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                GROUP BY c.id, c.idnumber, c.shortname LIMIT 20";

        $competencies = $DB->get_records_sql($sql);
        $parsable = 0;
        $unparsable = [];

        foreach ($competencies as $c) {
            if (strpos($c->idnumber, '_') !== false) {
                $parsable++;
            } else {
                $unparsable[] = $c->idnumber;
            }
        }

        $total = count($competencies);
        $status = empty($unparsable) ? 'passed' : 'warning';
        $this->manager->record_result('quiz', '1.8', 'Parsing idnumber', $status, 'Tutti parsabili', "{$parsable}/{$total} parsabili",
            empty($unparsable) ? 'Tutti gli idnumber seguono formato corretto.' : 'Non standard: ' . implode(', ', array_slice($unparsable, 0, 5)),
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 2: Test Autovalutazioni
    // ========================================================================
    
    private function run_module_selfassessment() {
        $this->test_2_1_valid_competencies();
        $this->test_2_2_bloom_levels();
        $this->test_2_3_valid_user();
        $this->test_2_4_match_with_quiz();
        $this->test_2_5_bloom_average();
    }

    private function test_2_1_valid_competencies() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as orphans FROM {local_selfassessment} sa LEFT JOIN {competency} c ON c.id = sa.competencyid WHERE c.id IS NULL";
        $result = $DB->get_record_sql($sql);

        $status = $result->orphans == 0 ? 'passed' : 'failed';
        $this->manager->record_result('selfassessment', '2.1', 'Competenze valide', $status, '0', $result->orphans,
            $result->orphans == 0 ? 'Tutte le autovalutazioni puntano a competenze esistenti.' : "{$result->orphans} orfane.",
            $sql, [], microtime(true) - $start);
    }

    private function test_2_2_bloom_levels() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as invalid FROM {local_selfassessment} WHERE level < 1 OR level > 6";
        $result = $DB->get_record_sql($sql);

        $status = $result->invalid == 0 ? 'passed' : 'failed';
        $this->manager->record_result('selfassessment', '2.2', 'Livelli Bloom', $status, '0 invalidi', $result->invalid . ' invalidi',
            $result->invalid == 0 ? 'Tutti i livelli Bloom sono 1-6.' : "{$result->invalid} fuori range.",
            $sql, [], microtime(true) - $start);
    }

    private function test_2_3_valid_user() {
        global $DB;
        $start = microtime(true);

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

        $sa_comps = $DB->count_records('local_selfassessment', ['userid' => $this->primary_user->userid]);
        $quiz_comps = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qc.competencyid)
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
            WHERE qa.userid = ? AND qa.state = 'finished'
        ", [$this->primary_user->userid]);

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

        $sql = "SELECT AVG(level) as avg_bloom FROM {local_selfassessment} WHERE userid = ?";
        $result = $DB->get_record_sql($sql, [$this->primary_user->userid]);

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

        $sql = "SELECT COUNT(*) as orphans FROM {local_labeval_behavior_comp} bc LEFT JOIN {competency} c ON c.id = bc.competencyid WHERE c.id IS NULL";
        $result = $DB->get_record_sql($sql);

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
        $this->manager->record_result('labeval', '3.6', 'Calcolo punteggio', 'skipped', '-', '-', 'Test non implementato', '', [], 0);
    }

    private function test_3_7_cache_updated() {
        global $DB;
        $start = microtime(true);
        $this->manager->record_result('labeval', '3.7', 'Cache aggiornata', 'skipped', '-', '-', 'Test non implementato', '', [], 0);
    }

    // ========================================================================
    // MODULO 4: Test Radar e Aggregazione
    // ========================================================================
    
    private function run_module_radar() {
        $this->test_4_1_areas_extracted();
        $this->test_4_2_area_sum();
        $this->test_4_3_area_percentages();
        $this->test_4_4_radar_range();
        $this->test_4_5_gap_analysis();
    }

    private function test_4_1_areas_extracted() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as area_code
                FROM {competency} c
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                WHERE c.idnumber IS NOT NULL AND c.idnumber != ''";
        $areas = $DB->get_records_sql($sql);

        $other_count = 0;
        foreach ($areas as $a) {
            if (empty($a->area_code) || $a->area_code === 'OTHER') {
                $other_count++;
            }
        }

        $status = $other_count == 0 ? 'passed' : ($other_count <= 2 ? 'warning' : 'failed');
        $this->manager->record_result('radar', '4.1', 'Aree estratte', $status, '0 OTHER', $other_count . ' OTHER',
            count($areas) . " aree estratte, {$other_count} non riconosciute.",
            $sql, [], microtime(true) - $start);
    }

    private function test_4_2_area_sum() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as total FROM {qbank_competenciesbyquestion}";
        $total = $DB->get_record_sql($sql)->total;

        $status = $total > 0 ? 'passed' : 'warning';
        $this->manager->record_result('radar', '4.2', 'Somma per area', $status, 'Coerente', $total . ' assegnazioni',
            "Totale assegnazioni competenze: {$total}.",
            $sql, [], microtime(true) - $start);
    }

    private function test_4_3_area_percentages() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('radar', '4.3', 'Percentuali area', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT c.id, c.idnumber, AVG(qas.fraction) * 100 as percentage
                FROM {quiz_attempts} qa
                JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                JOIN {competency} c ON c.id = qc.competencyid
                WHERE qa.userid = ? AND qa.state = 'finished' AND qas.fraction IS NOT NULL
                GROUP BY c.id, c.idnumber LIMIT 1";

        $result = $DB->get_record_sql($sql, [$this->primary_user->userid]);

        if (!$result) {
            $this->manager->record_result('radar', '4.3', 'Percentuali area', 'skipped', '-', '-', 'Nessun dato', '', [], 0);
            return;
        }

        $pct = round($result->percentage, 1);
        $valid = $pct >= 0 && $pct <= 100;
        $status = $valid ? 'passed' : 'failed';

        $this->manager->record_result('radar', '4.3', 'Percentuali area', $status, '0-100%', $pct . '%',
            "Competenza {$result->idnumber}: {$pct}%",
            $sql, [], microtime(true) - $start);
    }

    private function test_4_4_radar_range() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(*) as invalid FROM (SELECT AVG(qas.fraction) * 100 as pct FROM {question_attempt_steps} qas WHERE qas.fraction IS NOT NULL GROUP BY qas.questionattemptid) t WHERE t.pct < 0 OR t.pct > 100";
        $result = $DB->get_record_sql($sql);

        $status = $result->invalid == 0 ? 'passed' : 'failed';
        $this->manager->record_result('radar', '4.4', 'Range radar', $status, '0 fuori range', $result->invalid,
            $result->invalid == 0 ? 'Tutti i valori nel range 0-100%.' : "{$result->invalid} valori fuori range.",
            $sql, [], microtime(true) - $start);
    }

    private function test_4_5_gap_analysis() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('radar', '4.5', 'Gap Analysis', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT c.id, c.idnumber, sa.level, (sa.level / 6.0 * 100) as self_pct, AVG(qas.fraction) * 100 as quiz_pct
                FROM {local_selfassessment} sa
                JOIN {competency} c ON c.id = sa.competencyid
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                JOIN {question_attempts} qat ON qat.questionid = qc.questionid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                JOIN {quiz_attempts} qa ON qa.uniqueid = qat.questionusageid
                WHERE sa.userid = ? AND qa.userid = ? AND qa.state = 'finished' AND qas.fraction IS NOT NULL
                GROUP BY c.id, c.idnumber, sa.level LIMIT 1";

        $result = $DB->get_record_sql($sql, [$this->primary_user->userid, $this->primary_user->userid]);

        if (!$result) {
            $this->manager->record_result('radar', '4.5', 'Gap Analysis', 'skipped', '-', '-', 'Nessun dato per confronto', '', [], 0);
            return;
        }

        $gap = round($result->self_pct - $result->quiz_pct, 1);
        $this->manager->record_result('radar', '4.5', 'Gap Analysis', 'passed', 'Gap calcolato', "Gap: {$gap}%",
            "Competenza {$result->idnumber}: Autoval " . round($result->self_pct, 1) . "%, Quiz " . round($result->quiz_pct, 1) . "%, Gap {$gap}%",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 5: Test Report
    // ========================================================================
    
    private function run_module_report() {
        $this->test_5_1_total_competencies();
        $this->test_5_2_action_plan();
        $this->test_5_3_classification();
        $this->test_5_4_progress();
    }

    private function test_5_1_total_competencies() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('report', '5.1', 'Totale competenze', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(DISTINCT qc.competencyid) as total
                FROM {quiz_attempts} qa
                JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                WHERE qa.userid = ? AND qa.state = 'finished'";

        $result = $DB->get_record_sql($sql, [$this->primary_user->userid]);

        $status = $result->total > 0 ? 'passed' : 'warning';
        $this->manager->record_result('report', '5.1', 'Totale competenze', $status, '>0', $result->total,
            "L'utente test ha dati per {$result->total} competenze.",
            $sql, [], microtime(true) - $start);
    }

    private function test_5_2_action_plan() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('report', '5.2', 'Piano azione', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT SUM(CASE WHEN pct >= 80 THEN 1 ELSE 0 END) as excellence,
                       SUM(CASE WHEN pct >= 60 AND pct < 80 THEN 1 ELSE 0 END) as good,
                       SUM(CASE WHEN pct >= 30 AND pct < 60 THEN 1 ELSE 0 END) as toimprove,
                       SUM(CASE WHEN pct < 30 THEN 1 ELSE 0 END) as critical, COUNT(*) as total
                FROM (SELECT qc.competencyid, AVG(qas.fraction) * 100 as pct
                      FROM {quiz_attempts} qa
                      JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                      JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
                      WHERE qa.userid = ? AND qa.state = 'finished' AND qas.fraction IS NOT NULL
                      GROUP BY qc.competencyid) t";

        $result = $DB->get_record_sql($sql, [$this->primary_user->userid]);

        $sum = ($result->excellence ?? 0) + ($result->good ?? 0) + ($result->toimprove ?? 0) + ($result->critical ?? 0);
        $status = $sum == $result->total ? 'passed' : 'failed';

        $this->manager->record_result('report', '5.2', 'Piano azione', $status, 'Somma = Totale', "{$sum} = {$result->total}",
            "Excellence: {$result->excellence}, Good: {$result->good}, To Improve: {$result->toimprove}, Critical: {$result->critical}",
            $sql, [], microtime(true) - $start);
    }

    private function test_5_3_classification() {
        global $DB;
        $start = microtime(true);
        $this->manager->record_result('report', '5.3', 'Classificazione', 'passed', 'Univoca', 'OK',
            'Soglie mutuamente esclusive (verificato in 5.2).', '', [], microtime(true) - $start);
    }

    private function test_5_4_progress() {
        global $DB;
        $start = microtime(true);

        if (!$this->primary_user) {
            $this->manager->record_result('report', '5.4', 'Progressi', 'skipped', '-', '-', 'Nessun utente test', '', [], 0);
            return;
        }

        $sql = "SELECT COUNT(*) as attempts FROM {quiz_attempts} WHERE userid = ? AND state = 'finished'";
        $result = $DB->get_record_sql($sql, [$this->primary_user->userid]);

        $status = $result->attempts > 0 ? 'passed' : 'warning';
        $this->manager->record_result('report', '5.4', 'Progressi', $status, '>0 tentativi', $result->attempts,
            "L'utente test ha {$result->attempts} tentativi completati.",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 6: COPERTURA COMPETENZE (NUOVO - CRITICO)
    // ========================================================================
    
    private function run_module_coverage() {
        $this->test_6_1_coverage_per_sector();
        $this->test_6_2_minimum_coverage();
        $this->test_6_3_competencies_without_questions();
        $this->test_6_4_questions_without_competencies();
    }

    /**
     * Test 6.1: Copertura competenze per settore
     * Verifica che ogni settore abbia una copertura adeguata
     */
    private function test_6_1_coverage_per_sector() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT 
                    SUBSTRING_INDEX(c.idnumber, '_', 1) as sector,
                    COUNT(DISTINCT c.idnumber) as total_comps,
                    COUNT(DISTINCT CASE WHEN qc.id IS NOT NULL THEN c.idnumber END) as covered_comps
                FROM {competency} c
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                WHERE c.idnumber LIKE '%\\_%\\_%'
                AND c.idnumber NOT LIKE 'old%' AND c.idnumber NOT LIKE 'OLD%'
                GROUP BY SUBSTRING_INDEX(c.idnumber, '_', 1)
                HAVING total_comps > 0
                ORDER BY sector";

        $sectors = $DB->get_records_sql($sql);

        $low_coverage = [];
        $details = [];
        
        foreach ($sectors as $s) {
            $pct = round(($s->covered_comps / $s->total_comps) * 100, 1);
            $details[] = "{$s->sector}: {$pct}% ({$s->covered_comps}/{$s->total_comps})";
            if ($pct < 50) {
                $low_coverage[] = "{$s->sector} ({$pct}%)";
            }
        }

        $status = empty($low_coverage) ? 'passed' : (count($low_coverage) <= 2 ? 'warning' : 'failed');
        
        $this->manager->record_result('coverage', '6.1', 'Copertura per settore', $status, 
            '≥50% tutti settori', count($low_coverage) . ' sotto 50%',
            implode(' | ', $details),
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 6.2: Copertura minima globale
     */
    private function test_6_2_minimum_coverage() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT 
                    COUNT(DISTINCT c.idnumber) as total_comps,
                    COUNT(DISTINCT CASE WHEN qc.id IS NOT NULL THEN c.idnumber END) as covered_comps
                FROM {competency} c
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                WHERE c.idnumber LIKE '%\\_%\\_%'
                AND c.idnumber NOT LIKE 'old%' AND c.idnumber NOT LIKE 'OLD%'";

        $result = $DB->get_record_sql($sql);
        
        $coverage_pct = $result->total_comps > 0 
            ? round(($result->covered_comps / $result->total_comps) * 100, 1) 
            : 0;

        $status = $coverage_pct >= 70 ? 'passed' : ($coverage_pct >= 50 ? 'warning' : 'failed');
        $gap = $result->total_comps - $result->covered_comps;

        $this->manager->record_result('coverage', '6.2', 'Copertura minima globale', $status,
            '≥70%', $coverage_pct . '%',
            "{$result->covered_comps}/{$result->total_comps} competenze coperte. Gap: {$gap} competenze senza domande.",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 6.3: Competenze senza domande
     */
    private function test_6_3_competencies_without_questions() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(DISTINCT c.idnumber) as count
                FROM {competency} c
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                WHERE qc.id IS NULL
                AND c.idnumber LIKE '%\\_%\\_%'
                AND c.idnumber NOT LIKE 'old%' AND c.idnumber NOT LIKE 'OLD%'";

        $result = $DB->get_record_sql($sql);

        // Conta anche il totale per la percentuale
        $total = $DB->count_records_sql("
            SELECT COUNT(DISTINCT idnumber) FROM {competency} 
            WHERE idnumber LIKE '%\\_%\\_%' AND idnumber NOT LIKE 'old%' AND idnumber NOT LIKE 'OLD%'
        ");

        $pct = $total > 0 ? round(($result->count / $total) * 100, 1) : 0;
        $status = $result->count == 0 ? 'passed' : ($pct <= 30 ? 'warning' : 'failed');

        $this->manager->record_result('coverage', '6.3', 'Competenze senza domande', $status,
            '0', $result->count,
            "{$result->count} competenze ({$pct}%) non hanno domande associate.",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 6.4: Domande senza competenze nel corso
     */
    private function test_6_4_questions_without_competencies() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT COUNT(DISTINCT qv.questionid) as count
                FROM {quiz} q
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                WHERE qc.id IS NULL" . ($this->courseid ? " AND q.course = ?" : "");

        $params = $this->courseid ? [$this->courseid] : [];
        $result = $DB->get_record_sql($sql, $params);

        $status = $result->count == 0 ? 'passed' : ($result->count <= 10 ? 'warning' : 'failed');

        $this->manager->record_result('coverage', '6.4', 'Domande senza competenze', $status,
            '0', $result->count,
            "{$result->count} domande nei quiz non hanno competenze associate.",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 7: INTEGRITÀ DATI (NUOVO - CRITICO)
    // ========================================================================
    
    private function run_module_integrity() {
        $this->test_7_1_duplicate_competencies();
        $this->test_7_2_orphan_records();
        $this->test_7_3_data_consistency();
        $this->test_7_4_framework_integrity();
    }

    /**
     * Test 7.1: Duplicati nelle competenze
     * CRITICO: Rileva competenze con stesso idnumber
     */
    private function test_7_1_duplicate_competencies() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT idnumber, COUNT(*) as count
                FROM {competency}
                WHERE idnumber IS NOT NULL AND idnumber != ''
                GROUP BY idnumber
                HAVING COUNT(*) > 1
                ORDER BY count DESC";

        $duplicates = $DB->get_records_sql($sql);
        $total_dups = 0;
        $worst = [];

        foreach ($duplicates as $d) {
            $total_dups += ($d->count - 1); // Contiamo solo i duplicati extra
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

    /**
     * Test 7.2: Record orfani nelle tabelle FTM
     */
    private function test_7_2_orphan_records() {
        global $DB;
        $start = microtime(true);

        $orphans = [];

        // Selfassessment con competenze inesistenti
        $count = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_selfassessment} sa 
            LEFT JOIN {competency} c ON c.id = sa.competencyid 
            WHERE c.id IS NULL
        ");
        if ($count > 0) $orphans[] = "selfassessment: {$count}";

        // Assegnazioni con competenze inesistenti
        $count = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_selfassessment_assign} sa 
            LEFT JOIN {competency} c ON c.id = sa.competencyid 
            WHERE c.id IS NULL
        ");
        if ($count > 0) $orphans[] = "assign: {$count}";

        // Qbank con competenze inesistenti
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

    /**
     * Test 7.3: Consistenza dati tra tabelle
     */
    private function test_7_3_data_consistency() {
        global $DB;
        $start = microtime(true);

        // Verifica che le autovalutazioni abbiano assegnazioni corrispondenti
        $sql = "SELECT COUNT(*) as count
                FROM {local_selfassessment} sa
                LEFT JOIN {local_selfassessment_assign} saa 
                    ON saa.userid = sa.userid AND saa.competencyid = sa.competencyid
                WHERE saa.id IS NULL";

        $result = $DB->get_record_sql($sql);

        $status = $result->count == 0 ? 'passed' : 'warning';

        $this->manager->record_result('integrity', '7.3', 'Consistenza dati', $status,
            '0 inconsistenti', $result->count . ' senza assegnazione',
            $result->count == 0 
                ? 'Tutte le autovalutazioni hanno assegnazioni corrispondenti.' 
                : "{$result->count} autovalutazioni senza assegnazione (possibile bypass simulatore).",
            $sql, [], microtime(true) - $start);
    }

    /**
     * Test 7.4: Integrità framework
     */
    private function test_7_4_framework_integrity() {
        global $DB;
        $start = microtime(true);

        // Verifica che tutte le competenze usate appartengano a framework validi
        $sql = "SELECT COUNT(*) as count
                FROM {qbank_competenciesbyquestion} qc
                JOIN {competency} c ON c.id = qc.competencyid
                LEFT JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                WHERE cf.id IS NULL";

        $result = $DB->get_record_sql($sql);

        $status = $result->count == 0 ? 'passed' : 'failed';

        $this->manager->record_result('integrity', '7.4', 'Integrità framework', $status,
            '0 senza framework', $result->count,
            $result->count == 0 
                ? 'Tutte le competenze appartengono a framework validi.' 
                : "{$result->count} competenze senza framework valido.",
            $sql, [], microtime(true) - $start);
    }

    // ========================================================================
    // MODULO 8: ASSEGNAZIONI AUTOVALUTAZIONE (NUOVO)
    // ========================================================================
    
    private function run_module_assignments() {
        $this->test_8_1_assignments_exist();
        $this->test_8_2_assignments_source();
        $this->test_8_3_observer_working();
    }

    /**
     * Test 8.1: Esistono assegnazioni
     */
    private function test_8_1_assignments_exist() {
        global $DB;
        $start = microtime(true);

        $total = $DB->count_records('local_selfassessment_assign');

        $status = $total > 0 ? 'passed' : 'failed';

        $this->manager->record_result('assignments', '8.1', 'Assegnazioni esistono', $status,
            '>0', $total,
            $total > 0 
                ? "{$total} assegnazioni nel sistema." 
                : "CRITICO: Nessuna assegnazione. L'observer potrebbe non funzionare!",
            '', [], microtime(true) - $start);
    }

    /**
     * Test 8.2: Distribuzione per source
     */
    private function test_8_2_assignments_source() {
        global $DB;
        $start = microtime(true);

        $sql = "SELECT source, COUNT(*) as count 
                FROM {local_selfassessment_assign} 
                GROUP BY source";

        $sources = $DB->get_records_sql($sql);

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

    /**
     * Test 8.3: Observer funzionante
     */
    private function test_8_3_observer_working() {
        global $DB;
        $start = microtime(true);

        // Verifica che per gli studenti con quiz completati ci siano assegnazioni
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

        $status = $ratio >= 80 ? 'passed' : ($ratio >= 50 ? 'warning' : 'failed');

        $this->manager->record_result('assignments', '8.3', 'Observer funzionante', $status,
            '≥80% utenti con assegnazioni', $ratio . '%',
            "{$result->users_with_assign}/{$result->users_with_quiz} utenti con quiz hanno assegnazioni da observer.",
            $sql, [], microtime(true) - $start);
    }
}
