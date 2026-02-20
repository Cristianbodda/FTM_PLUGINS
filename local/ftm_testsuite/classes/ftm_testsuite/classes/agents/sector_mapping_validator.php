<?php
/**
 * Sector Mapping Validator - Test completi per mapping settori e valutazione coach
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per validare il mapping dei settori e il sistema di valutazione coach
 */
class sector_mapping_validator extends base_agent {

    /** @var array Settori attesi con codici numerici */
    private $expected_sectors = [
        '01' => 'AUTOMOBILE',
        '02' => 'CHIMFARM',
        '03' => 'ELETTRICITA',
        '04' => 'AUTOMAZIONE',
        '05' => 'LOGISTICA',
        '06' => 'MECCANICA',
        '07' => 'METALCOSTRUZIONE',
    ];

    /** @var array Aree attese per ogni settore */
    private $expected_areas = [
        'AUTOMOBILE' => ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'],
        'CHIMFARM' => ['1C','1G','1O','2M','3C','4S','5S','6P','7S','8T','9A'],
        'ELETTRICITA' => ['A','B','C','D','E','F','G','H'],
        'AUTOMAZIONE' => ['A','B','C','D','E','F','G','H'],
        'LOGISTICA' => ['A','B','C','D','E','F','G','H'],
        'MECCANICA' => ['LMB','LMC','CNC','ASS','MIS','GEN','MAN','DT','AUT','PIAN','SAQ','CSP','PRG'],
        'METALCOSTRUZIONE' => ['A','B','C','D','E','F','G','H','I','J'],
    ];

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'Sector Mapping Validator';
        $this->description = 'Verifica mapping settori, estrazione aree e sistema valutazione coach';
        $this->category = 'functionality';

        // Carica area_mapping.php se esiste
        // Path: da /local/ftm_testsuite/classes/agents/ a /local/competencymanager/
        global $CFG;
        $mapping_file = $CFG->dirroot . '/local/competencymanager/area_mapping.php';
        if (file_exists($mapping_file)) {
            require_once($mapping_file);
        }
    }

    /**
     * Ottiene la lista dei test disponibili
     * @return array
     */
    public function get_available_tests() {
        return [
            'SECT001' => 'Normalize Sector - Codici Numerici (01-07)',
            'SECT002' => 'Normalize Sector - Pattern XX-YY',
            'SECT003' => 'Normalize Sector - Nomi Testuali',
            'SECT004' => 'Normalize Sector - Alias',
            'SECT005' => 'Normalize Sector - Caratteri Speciali (accenti)',
            'SECT006' => 'Extract Sector - Competenze Testuali',
            'SECT007' => 'Extract Sector - Competenze Numeriche',
            'SECT008' => 'Get Area Info - Tutti i Settori',
            'SECT009' => 'DB: Competenze AUTOMOBILE mappate',
            'SECT010' => 'DB: Competenze CHIMFARM mappate',
            'SECT011' => 'DB: Competenze ELETTRICITA mappate',
            'SECT012' => 'DB: Competenze AUTOMAZIONE mappate',
            'SECT013' => 'DB: Competenze LOGISTICA mappate',
            'SECT014' => 'DB: Competenze MECCANICA mappate',
            'SECT015' => 'DB: Competenze METALCOSTRUZIONE mappate',
            'SECT016' => 'DB: Framework GENERICO mappato',
            'SECT017' => 'DB: Nessuna competenza UNKNOWN',
            'COACH001' => 'Coach Eval: get_rating_stats() funziona',
            'COACH002' => 'Coach Eval: get_radar_data() funziona',
            'COACH003' => 'Coach Eval: Selezione valutazione con ratings',
            'COACH004' => 'Coach Eval: Valutazioni vuote non selezionate',
        ];
    }

    /**
     * Esegue tutti i test
     * @return array
     */
    public function run_all_tests() {
        $this->results = [];

        foreach (array_keys($this->get_available_tests()) as $code) {
            $this->start_timer();
            $this->run_single_test($code);
        }

        return $this->results;
    }

    /**
     * Esegue un singolo test
     * @param string $test_code
     * @return array
     */
    public function run_single_test($test_code) {
        $this->start_timer();

        switch ($test_code) {
            case 'SECT001': return $this->test_normalize_numeric_codes();
            case 'SECT002': return $this->test_normalize_xx_yy_pattern();
            case 'SECT003': return $this->test_normalize_textual_names();
            case 'SECT004': return $this->test_normalize_aliases();
            case 'SECT005': return $this->test_normalize_special_chars();
            case 'SECT006': return $this->test_extract_textual();
            case 'SECT007': return $this->test_extract_numeric();
            case 'SECT008': return $this->test_get_area_info();
            case 'SECT009': return $this->test_db_sector('AUTOMOBILE', '01');
            case 'SECT010': return $this->test_db_sector('CHIMFARM', '02');
            case 'SECT011': return $this->test_db_sector('ELETTRICITA', '03');
            case 'SECT012': return $this->test_db_sector('AUTOMAZIONE', '04');
            case 'SECT013': return $this->test_db_sector('LOGISTICA', '05');
            case 'SECT014': return $this->test_db_sector('MECCANICA', '06');
            case 'SECT015': return $this->test_db_sector('METALCOSTRUZIONE', '07');
            case 'SECT016': return $this->test_db_generico();
            case 'SECT017': return $this->test_no_unknown();
            case 'COACH001': return $this->test_coach_rating_stats();
            case 'COACH002': return $this->test_coach_radar_data();
            case 'COACH003': return $this->test_coach_selection_with_ratings();
            case 'COACH004': return $this->test_coach_empty_not_selected();
            default:
                return $this->skip($test_code, 'Test sconosciuto', 'Codice test non riconosciuto');
        }
    }

    // ========================================
    // TEST NORMALIZE_SECTOR_NAME
    // ========================================

    /**
     * Test codici numerici 01-07
     */
    private function test_normalize_numeric_codes() {
        $code = 'SECT001';
        $name = 'Normalize Sector - Codici Numerici';

        if (!function_exists('normalize_sector_name')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata',
                'La funzione normalize_sector_name() non è disponibile');
        }

        $errors = [];
        foreach ($this->expected_sectors as $num => $expected) {
            $result = normalize_sector_name($num);
            if ($result !== $expected) {
                $errors[] = "$num: atteso '$expected', ottenuto '$result'";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, '7 codici mappati', '7/7 corretti',
                'Tutti i codici numerici (01-07) mappati correttamente');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', $errors));
        }
    }

    /**
     * Test pattern XX-YY
     */
    private function test_normalize_xx_yy_pattern() {
        $code = 'SECT002';
        $name = 'Normalize Sector - Pattern XX-YY';

        if (!function_exists('normalize_sector_name')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            '01-01' => 'AUTOMOBILE', '01-14' => 'AUTOMOBILE',
            '02-01' => 'CHIMFARM', '02-11' => 'CHIMFARM',
            '03-01' => 'ELETTRICITA', '03-08' => 'ELETTRICITA',
            '04-01' => 'AUTOMAZIONE', '04-08' => 'AUTOMAZIONE',
            '05-01' => 'LOGISTICA', '05-08' => 'LOGISTICA',
            '06-01' => 'MECCANICA', '06-13' => 'MECCANICA',
            '07-01' => 'METALCOSTRUZIONE', '07-10' => 'METALCOSTRUZIONE',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = normalize_sector_name($input);
            if ($result !== $expected) {
                $errors[] = "$input: '$result' != '$expected'";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, count($tests) . ' pattern', count($tests) . '/'. count($tests) . ' OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', array_slice($errors, 0, 5)));
        }
    }

    /**
     * Test nomi testuali
     */
    private function test_normalize_textual_names() {
        $code = 'SECT003';
        $name = 'Normalize Sector - Nomi Testuali';

        if (!function_exists('normalize_sector_name')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            'AUTOMOBILE' => 'AUTOMOBILE',
            'CHIMFARM' => 'CHIMFARM',
            'ELETTRICITA' => 'ELETTRICITA',
            'AUTOMAZIONE' => 'AUTOMAZIONE',
            'LOGISTICA' => 'LOGISTICA',
            'MECCANICA' => 'MECCANICA',
            'METALCOSTRUZIONE' => 'METALCOSTRUZIONE',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = normalize_sector_name($input);
            if ($result !== $expected) {
                $errors[] = "$input → $result";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, '7 nomi', '7/7 OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori');
        }
    }

    /**
     * Test alias
     */
    private function test_normalize_aliases() {
        $code = 'SECT004';
        $name = 'Normalize Sector - Alias';

        if (!function_exists('normalize_sector_name')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            'MECC' => 'MECCANICA',
            'AUTO' => 'AUTOMOBILE',
            'CHIM' => 'CHIMFARM',
            'ELETTR' => 'ELETTRICITA',
            'LOG' => 'LOGISTICA',
            'METAL' => 'METALCOSTRUZIONE',
            'AUTOM' => 'AUTOMAZIONE',
            'CNC' => 'MECCANICA',
            'LMB' => 'MECCANICA',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = normalize_sector_name($input);
            if ($result !== $expected) {
                $errors[] = "$input → $result (atteso $expected)";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, count($tests) . ' alias', count($tests) . '/' . count($tests) . ' OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', $errors));
        }
    }

    /**
     * Test caratteri speciali (accenti)
     */
    private function test_normalize_special_chars() {
        $code = 'SECT005';
        $name = 'Normalize Sector - Caratteri Speciali';

        if (!function_exists('normalize_sector_name')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $result = normalize_sector_name('ELETTRICITÀ');
        if ($result === 'ELETTRICITA') {
            return $this->pass($code, $name, 'ELETTRICITA', $result,
                'Accento gestito correttamente');
        } else {
            return $this->fail($code, $name, 'ELETTRICITA', $result,
                'Accento non gestito');
        }
    }

    // ========================================
    // TEST EXTRACT_SECTOR_FROM_IDNUMBER
    // ========================================

    /**
     * Test estrazione da idnumber testuali
     */
    private function test_extract_textual() {
        $code = 'SECT006';
        $name = 'Extract Sector - Competenze Testuali';

        if (!function_exists('extract_sector_from_idnumber')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            'MECCANICA_LMB_01' => 'MECCANICA',
            'AUTOMOBILE_MR_A1' => 'AUTOMOBILE',
            'CHIMFARM_1C_01' => 'CHIMFARM',
            'ELETTRICITÀ_EM_C1' => 'ELETTRICITA',
            'AUTOMAZIONE_MA_B1' => 'AUTOMAZIONE',
            'LOGISTICA_LO_A1' => 'LOGISTICA',
            'METALCOSTRUZIONE_DF_A1' => 'METALCOSTRUZIONE',
            'GEN_A_01' => 'GEN',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = extract_sector_from_idnumber($input);
            if ($result !== $expected) {
                $errors[] = "$input → $result";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, count($tests) . ' idnumber', count($tests) . '/' . count($tests) . ' OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', $errors));
        }
    }

    /**
     * Test estrazione da idnumber numerici
     */
    private function test_extract_numeric() {
        $code = 'SECT007';
        $name = 'Extract Sector - Competenze Numeriche';

        if (!function_exists('extract_sector_from_idnumber')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            '06' => 'MECCANICA',
            '06-01' => 'MECCANICA',
            '01' => 'AUTOMOBILE',
            '01-05' => 'AUTOMOBILE',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = extract_sector_from_idnumber($input);
            if ($result !== $expected) {
                $errors[] = "$input → $result (atteso $expected)";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, count($tests) . ' idnumber', count($tests) . '/' . count($tests) . ' OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', $errors));
        }
    }

    // ========================================
    // TEST GET_AREA_INFO
    // ========================================

    /**
     * Test get_area_info per tutti i settori
     */
    private function test_get_area_info() {
        $code = 'SECT008';
        $name = 'Get Area Info - Tutti i Settori';

        if (!function_exists('get_area_info')) {
            return $this->fail($code, $name, 'funzione disponibile', 'funzione non trovata');
        }

        $tests = [
            'MECCANICA_CNC_01' => 'CNC',
            'MECCANICA_LMB_05' => 'LMB',
            'AUTOMOBILE_MR_A1' => 'A',
            'CHIMFARM_1C_01' => '1C',
            'LOGISTICA_LO_A1' => 'A',
            'GEN_A_01' => 'A',
        ];

        $errors = [];
        foreach ($tests as $input => $expected) {
            $result = get_area_info($input);
            if ($result['code'] !== $expected) {
                $errors[] = "$input → {$result['code']} (atteso $expected)";
            }
        }

        if (empty($errors)) {
            return $this->pass($code, $name, count($tests) . ' aree', count($tests) . '/' . count($tests) . ' OK');
        } else {
            return $this->fail($code, $name, 'tutti corretti', count($errors) . ' errori',
                implode('; ', $errors));
        }
    }

    // ========================================
    // TEST DATABASE COMPETENZE
    // ========================================

    /**
     * Test competenze di un settore nel DB
     */
    private function test_db_sector($sector_name, $num_code) {
        global $DB;

        $code = 'SECT0' . (8 + array_search($num_code, array_keys($this->expected_sectors)) + 1);
        $name = "DB: Competenze $sector_name mappate";

        if (!function_exists('extract_sector_from_idnumber')) {
            return $this->skip($code, $name, 'Funzione non disponibile');
        }

        // Cerca competenze testuali
        $textual = $DB->get_records_sql(
            "SELECT c.idnumber FROM {competency} c
             JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
             WHERE cf.idnumber = 'FTM-01' AND c.idnumber LIKE ?",
            [$sector_name . '_%']
        );

        // Cerca competenze numeriche
        $numeric = $DB->get_records_sql(
            "SELECT c.idnumber FROM {competency} c
             JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
             WHERE cf.idnumber = 'FTM-01' AND (c.idnumber = ? OR c.idnumber LIKE ?)",
            [$num_code, $num_code . '-%']
        );

        $all = array_merge($textual, $numeric);
        $total = count($all);

        if ($total === 0) {
            return $this->warn($code, $name, '>0 competenze', '0 trovate',
                'Nessuna competenza trovata nel DB per ' . $sector_name);
        }

        $errors = 0;
        foreach ($all as $comp) {
            $extracted = extract_sector_from_idnumber($comp->idnumber);
            if ($extracted !== $sector_name) {
                $errors++;
            }
        }

        if ($errors === 0) {
            return $this->pass($code, $name, "$total competenze", "$total/$total OK",
                "Tutte le competenze $sector_name mappate correttamente");
        } else {
            return $this->fail($code, $name, "$total corrette", ($total - $errors) . "/$total OK",
                "$errors competenze con mapping errato");
        }
    }

    /**
     * Test framework GENERICO
     */
    private function test_db_generico() {
        global $DB;

        $code = 'SECT016';
        $name = 'DB: Framework GENERICO mappato';

        if (!function_exists('extract_sector_from_idnumber')) {
            return $this->skip($code, $name, 'Funzione non disponibile');
        }

        $comps = $DB->get_records_sql(
            "SELECT c.idnumber FROM {competency} c
             JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
             WHERE cf.idnumber = 'FTM_GEN'"
        );

        $total = count($comps);
        if ($total === 0) {
            return $this->warn($code, $name, '>0 competenze', '0 trovate',
                'Framework FTM_GEN non trovato o vuoto');
        }

        $errors = 0;
        foreach ($comps as $comp) {
            $extracted = extract_sector_from_idnumber($comp->idnumber);
            if ($extracted !== 'GEN' && $extracted !== 'GENERICO') {
                $errors++;
            }
        }

        if ($errors === 0) {
            return $this->pass($code, $name, "$total competenze", "$total/$total OK");
        } else {
            return $this->fail($code, $name, "$total corrette", ($total - $errors) . "/$total OK");
        }
    }

    /**
     * Test nessuna competenza UNKNOWN
     */
    private function test_no_unknown() {
        global $DB;

        $code = 'SECT017';
        $name = 'DB: Nessuna competenza UNKNOWN';

        if (!function_exists('extract_sector_from_idnumber')) {
            return $this->skip($code, $name, 'Funzione non disponibile');
        }

        $comps = $DB->get_records_sql(
            "SELECT c.idnumber FROM {competency} c
             JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
             WHERE cf.idnumber IN ('FTM-01', 'FTM_GEN')"
        );

        $unknown = [];
        foreach ($comps as $comp) {
            $extracted = extract_sector_from_idnumber($comp->idnumber);
            if ($extracted === 'UNKNOWN') {
                $unknown[] = $comp->idnumber;
            }
        }

        if (empty($unknown)) {
            return $this->pass($code, $name, '0 UNKNOWN', '0 trovate',
                'Tutte le competenze hanno un settore valido');
        } else {
            return $this->fail($code, $name, '0 UNKNOWN', count($unknown) . ' trovate',
                'idnumber: ' . implode(', ', array_slice($unknown, 0, 5)));
        }
    }

    // ========================================
    // TEST COACH EVALUATION
    // ========================================

    /**
     * Test get_rating_stats
     */
    private function test_coach_rating_stats() {
        global $CFG;

        $code = 'COACH001';
        $name = 'Coach Eval: get_rating_stats()';

        $manager_file = $CFG->dirroot . '/local/competencymanager/classes/coach_evaluation_manager.php';
        if (!file_exists($manager_file)) {
            return $this->skip($code, $name, 'coach_evaluation_manager.php non trovato');
        }

        require_once($manager_file);

        if (!class_exists('\\local_competencymanager\\coach_evaluation_manager')) {
            return $this->fail($code, $name, 'classe disponibile', 'classe non trovata');
        }

        // Test con ID fittizio (dovrebbe restituire 0)
        $stats = \local_competencymanager\coach_evaluation_manager::get_rating_stats(999999);

        if (isset($stats['total']) && isset($stats['rated']) && isset($stats['not_observed'])) {
            return $this->pass($code, $name, 'array con 3 chiavi', 'OK',
                'Restituisce: total=' . $stats['total'] . ', rated=' . $stats['rated'] . ', not_observed=' . $stats['not_observed']);
        } else {
            return $this->fail($code, $name, 'array con 3 chiavi', 'struttura errata');
        }
    }

    /**
     * Test get_radar_data
     */
    private function test_coach_radar_data() {
        global $CFG;

        $code = 'COACH002';
        $name = 'Coach Eval: get_radar_data()';

        $manager_file = $CFG->dirroot . '/local/competencymanager/classes/coach_evaluation_manager.php';
        if (!file_exists($manager_file)) {
            return $this->skip($code, $name, 'coach_evaluation_manager.php non trovato');
        }

        require_once($manager_file);

        if (!method_exists('\\local_competencymanager\\coach_evaluation_manager', 'get_radar_data')) {
            return $this->fail($code, $name, 'metodo disponibile', 'metodo non trovato');
        }

        // Test con utente fittizio (dovrebbe restituire array vuoto)
        $data = \local_competencymanager\coach_evaluation_manager::get_radar_data(999999, 'MECCANICA');

        if (is_array($data)) {
            return $this->pass($code, $name, 'array', 'array(' . count($data) . ' elementi)',
                'Funzione eseguita correttamente');
        } else {
            return $this->fail($code, $name, 'array', gettype($data));
        }
    }

    /**
     * Test selezione valutazione con ratings
     */
    private function test_coach_selection_with_ratings() {
        global $CFG, $DB;

        $code = 'COACH003';
        $name = 'Coach Eval: Selezione valutazione con ratings';

        $manager_file = $CFG->dirroot . '/local/competencymanager/classes/coach_evaluation_manager.php';
        if (!file_exists($manager_file)) {
            return $this->skip($code, $name, 'coach_evaluation_manager.php non trovato');
        }

        require_once($manager_file);

        // Cerca una valutazione con ratings nel DB
        $eval_with_ratings = $DB->get_record_sql(
            "SELECT e.id, e.studentid, e.sector, COUNT(r.id) as rating_count
             FROM {local_coach_evaluations} e
             LEFT JOIN {local_coach_eval_ratings} r ON r.evaluationid = e.id
             GROUP BY e.id, e.studentid, e.sector
             HAVING COUNT(r.id) > 0
             ORDER BY rating_count DESC
             LIMIT 1"
        );

        if (!$eval_with_ratings) {
            return $this->skip($code, $name, 'Nessuna valutazione con ratings nel DB');
        }

        // Verifica che get_radar_data trovi questa valutazione
        $radar_data = \local_competencymanager\coach_evaluation_manager::get_radar_data(
            $eval_with_ratings->studentid,
            $eval_with_ratings->sector
        );

        if (!empty($radar_data)) {
            return $this->pass($code, $name, 'dati radar', count($radar_data) . ' aree',
                'Valutazione con ' . $eval_with_ratings->rating_count . ' ratings trovata correttamente');
        } else {
            return $this->fail($code, $name, 'dati radar', 'array vuoto',
                'La valutazione con ratings non viene trovata');
        }
    }

    /**
     * Test che valutazioni vuote non vengano selezionate
     */
    private function test_coach_empty_not_selected() {
        global $CFG, $DB;

        $code = 'COACH004';
        $name = 'Coach Eval: Valutazioni vuote non selezionate';

        $manager_file = $CFG->dirroot . '/local/competencymanager/classes/coach_evaluation_manager.php';
        if (!file_exists($manager_file)) {
            return $this->skip($code, $name, 'coach_evaluation_manager.php non trovato');
        }

        require_once($manager_file);

        // Cerca uno studente con valutazione vuota E valutazione con ratings
        $student_with_both = $DB->get_record_sql(
            "SELECT e1.studentid, e1.sector,
                    COUNT(DISTINCT CASE WHEN r.id IS NULL THEN e1.id END) as empty_evals,
                    COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN e1.id END) as full_evals
             FROM {local_coach_evaluations} e1
             LEFT JOIN {local_coach_eval_ratings} r ON r.evaluationid = e1.id
             GROUP BY e1.studentid, e1.sector
             HAVING COUNT(DISTINCT CASE WHEN r.id IS NULL THEN e1.id END) > 0
                AND COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN e1.id END) > 0
             LIMIT 1"
        );

        if (!$student_with_both) {
            return $this->skip($code, $name,
                'Nessuno studente con entrambe valutazioni vuote e piene per stesso settore');
        }

        // Verifica che get_radar_data restituisca dati (quindi ha scelto quella piena)
        $radar_data = \local_competencymanager\coach_evaluation_manager::get_radar_data(
            $student_with_both->studentid,
            $student_with_both->sector
        );

        if (!empty($radar_data)) {
            return $this->pass($code, $name, 'valutazione con ratings', count($radar_data) . ' aree',
                'La valutazione vuota è stata ignorata correttamente');
        } else {
            return $this->fail($code, $name, 'valutazione con ratings', 'array vuoto',
                'Probabilmente è stata selezionata la valutazione vuota');
        }
    }
}
