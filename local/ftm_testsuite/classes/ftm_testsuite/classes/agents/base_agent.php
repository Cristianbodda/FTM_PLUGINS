<?php
/**
 * Base Test Agent - Classe astratta per tutti gli agenti di test Moodle
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe base astratta per gli agenti di test
 */
abstract class base_agent {

    /** @var string Nome dell'agente */
    protected $name;

    /** @var string Descrizione dell'agente */
    protected $description;

    /** @var string Categoria (security, database, api, structure, functionality, performance) */
    protected $category;

    /** @var array Plugin da testare */
    protected $plugins = [];

    /** @var array Risultati dei test */
    protected $results = [];

    /** @var float Tempo di inizio */
    protected $start_time;

    /** @var \local_ftm_testsuite\test_manager Manager dei test */
    protected $manager;

    /** @var array Configurazione agente */
    protected $config = [];

    /**
     * Costruttore
     * @param \local_ftm_testsuite\test_manager|null $manager
     */
    public function __construct($manager = null) {
        $this->manager = $manager;
        $this->init();
    }

    /**
     * Inizializza l'agente - da implementare nelle sottoclassi
     */
    abstract protected function init();

    /**
     * Esegue tutti i test dell'agente
     * @return array Risultati dei test
     */
    abstract public function run_all_tests();

    /**
     * Ottiene la lista dei test disponibili
     * @return array [code => name]
     */
    abstract public function get_available_tests();

    /**
     * Esegue un singolo test
     * @param string $test_code Codice del test
     * @return array Risultato del test
     */
    abstract public function run_single_test($test_code);

    /**
     * Avvia il timer
     */
    protected function start_timer() {
        $this->start_time = microtime(true);
    }

    /**
     * Ottiene il tempo trascorso
     * @return float Secondi
     */
    protected function get_elapsed_time() {
        return microtime(true) - $this->start_time;
    }

    /**
     * Registra un risultato di test
     * @param string $code Codice test
     * @param string $name Nome test
     * @param string $status passed|failed|warning|skipped
     * @param mixed $expected Valore atteso
     * @param mixed $actual Valore ottenuto
     * @param string $details Dettagli aggiuntivi
     * @param array $extra Dati extra (sql, trace, etc)
     */
    protected function record_result($code, $name, $status, $expected = null, $actual = null, $details = '', $extra = []) {
        $result = [
            'agent' => $this->name,
            'category' => $this->category,
            'code' => $code,
            'name' => $name,
            'status' => $status,
            'expected' => $expected,
            'actual' => $actual,
            'details' => $details,
            'execution_time' => $this->get_elapsed_time(),
            'timestamp' => time()
        ];

        $result = array_merge($result, $extra);
        $this->results[] = $result;

        // Se c'è un manager, registra anche lì
        if ($this->manager) {
            $this->manager->record_result(
                $this->category,
                $code,
                $name,
                $status,
                $expected,
                $actual,
                $details,
                $extra['sql'] ?? '',
                $extra['trace'] ?? [],
                $this->get_elapsed_time()
            );
        }

        return $result;
    }

    /**
     * Test passato
     */
    protected function pass($code, $name, $expected = null, $actual = null, $details = '') {
        return $this->record_result($code, $name, 'passed', $expected, $actual, $details);
    }

    /**
     * Test fallito
     */
    protected function fail($code, $name, $expected = null, $actual = null, $details = '') {
        return $this->record_result($code, $name, 'failed', $expected, $actual, $details);
    }

    /**
     * Test con warning
     */
    protected function warn($code, $name, $expected = null, $actual = null, $details = '') {
        return $this->record_result($code, $name, 'warning', $expected, $actual, $details);
    }

    /**
     * Test saltato
     */
    protected function skip($code, $name, $reason = '') {
        return $this->record_result($code, $name, 'skipped', null, null, $reason);
    }

    /**
     * Ottiene tutti i risultati
     * @return array
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Ottiene il sommario dei risultati
     * @return array
     */
    public function get_summary() {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $skipped = 0;

        foreach ($this->results as $r) {
            switch ($r['status']) {
                case 'passed': $passed++; break;
                case 'failed': $failed++; break;
                case 'warning': $warnings++; break;
                case 'skipped': $skipped++; break;
            }
        }

        $total = count($this->results);
        return [
            'agent' => $this->name,
            'category' => $this->category,
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'skipped' => $skipped,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Ottiene il nome dell'agente
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Ottiene la descrizione
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Ottiene la categoria
     * @return string
     */
    public function get_category() {
        return $this->category;
    }

    /**
     * Scopre i plugin FTM installati
     * @return array
     */
    protected function discover_ftm_plugins() {
        global $CFG;

        $plugins = [];

        // Plugin local
        $local_dir = $CFG->dirroot . '/local';
        if (is_dir($local_dir)) {
            $dirs = scandir($local_dir);
            foreach ($dirs as $dir) {
                if ($dir[0] !== '.' && is_dir($local_dir . '/' . $dir)) {
                    // Verifica se è un plugin FTM
                    if (strpos($dir, 'ftm') !== false ||
                        in_array($dir, ['competencymanager', 'coachmanager', 'selfassessment', 'labeval', 'competencyreport', 'competencyxmlimport'])) {
                        $plugins[] = [
                            'type' => 'local',
                            'name' => $dir,
                            'component' => 'local_' . $dir,
                            'path' => $local_dir . '/' . $dir
                        ];
                    }
                }
            }
        }

        // Plugin blocks
        $blocks_dir = $CFG->dirroot . '/blocks';
        if (is_dir($blocks_dir)) {
            $dirs = scandir($blocks_dir);
            foreach ($dirs as $dir) {
                if (strpos($dir, 'ftm') !== false) {
                    $plugins[] = [
                        'type' => 'block',
                        'name' => $dir,
                        'component' => 'block_' . $dir,
                        'path' => $blocks_dir . '/' . $dir
                    ];
                }
            }
        }

        // Plugin question bank
        $qbank_dir = $CFG->dirroot . '/question/bank';
        if (is_dir($qbank_dir)) {
            $dirs = scandir($qbank_dir);
            foreach ($dirs as $dir) {
                if (strpos($dir, 'competenc') !== false) {
                    $plugins[] = [
                        'type' => 'qbank',
                        'name' => $dir,
                        'component' => 'qbank_' . $dir,
                        'path' => $qbank_dir . '/' . $dir
                    ];
                }
            }
        }

        $this->plugins = $plugins;
        return $plugins;
    }

    /**
     * Legge un file PHP e restituisce il contenuto
     * @param string $filepath
     * @return string|false
     */
    protected function read_file($filepath) {
        if (file_exists($filepath) && is_readable($filepath)) {
            return file_get_contents($filepath);
        }
        return false;
    }

    /**
     * Cerca pattern in un file
     * @param string $filepath
     * @param string $pattern Regex
     * @return array Matches
     */
    protected function search_in_file($filepath, $pattern) {
        $content = $this->read_file($filepath);
        if ($content === false) {
            return [];
        }

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        return $matches;
    }

    /**
     * Ottiene tutti i file PHP in una directory (ricorsivo)
     * @param string $dir
     * @return array
     */
    protected function get_php_files($dir) {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Salta file "old_" e backup
                $filename = $file->getFilename();
                if (strpos($filename, 'old_') !== 0 && strpos($filename, '.bak') === false) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
