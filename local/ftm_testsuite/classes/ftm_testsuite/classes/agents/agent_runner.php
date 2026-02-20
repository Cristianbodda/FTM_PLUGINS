<?php
/**
 * Agent Runner - Orchestratore per tutti gli agenti di test
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per orchestrare l'esecuzione di tutti gli agenti di test
 */
class agent_runner {

    /** @var array Agenti registrati */
    private $agents = [];

    /** @var array Risultati combinati */
    private $results = [];

    /** @var array Sommari per agente */
    private $summaries = [];

    /** @var \local_ftm_testsuite\test_manager|null Manager test */
    private $manager;

    /** @var float Tempo di inizio */
    private $start_time;

    /**
     * Costruttore - registra tutti gli agenti disponibili
     * @param \local_ftm_testsuite\test_manager|null $manager
     */
    public function __construct($manager = null) {
        $this->manager = $manager;
        $this->register_agents();
    }

    /**
     * Registra tutti gli agenti disponibili
     */
    private function register_agents() {
        // Trova tutti i file agent nella directory
        $agents_dir = __DIR__;
        $agent_files = glob($agents_dir . '/*.php');

        foreach ($agent_files as $file) {
            $filename = basename($file, '.php');

            // Salta file speciali
            if (in_array($filename, ['base_agent', 'agent_runner'])) {
                continue;
            }

            // Costruisci nome classe
            $class_name = '\\local_ftm_testsuite\\agents\\' . $filename;

            if (class_exists($class_name)) {
                try {
                    $agent = new $class_name($this->manager);
                    $this->agents[$filename] = $agent;
                } catch (\Exception $e) {
                    // Agent non valido, salta
                    debugging("Impossibile caricare agente {$filename}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Ottiene la lista degli agenti disponibili
     * @return array [id => [name, description, category, tests_count]]
     */
    public function get_available_agents() {
        $list = [];

        foreach ($this->agents as $id => $agent) {
            $list[$id] = [
                'id' => $id,
                'name' => $agent->get_name(),
                'description' => $agent->get_description(),
                'category' => $agent->get_category(),
                'tests' => $agent->get_available_tests(),
                'tests_count' => count($agent->get_available_tests())
            ];
        }

        // Ordina per categoria
        uasort($list, function($a, $b) {
            $cat_order = ['security' => 1, 'database' => 2, 'api' => 3, 'structure' => 4, 'functionality' => 5, 'performance' => 6];
            $a_order = $cat_order[$a['category']] ?? 99;
            $b_order = $cat_order[$b['category']] ?? 99;
            return $a_order - $b_order;
        });

        return $list;
    }

    /**
     * Ottiene la lista degli agenti per categoria
     * @return array [category => [agents]]
     */
    public function get_agents_by_category() {
        $by_category = [];

        foreach ($this->get_available_agents() as $id => $agent) {
            $cat = $agent['category'];
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = [
                    'name' => $this->get_category_name($cat),
                    'icon' => $this->get_category_icon($cat),
                    'agents' => []
                ];
            }
            $by_category[$cat]['agents'][$id] = $agent;
        }

        return $by_category;
    }

    /**
     * Ottiene il nome leggibile della categoria
     */
    private function get_category_name($category) {
        $names = [
            'security' => 'Sicurezza',
            'database' => 'Database',
            'api' => 'API & Endpoint',
            'structure' => 'Struttura & Standard',
            'functionality' => 'Funzionalità',
            'performance' => 'Performance'
        ];
        return $names[$category] ?? ucfirst($category);
    }

    /**
     * Ottiene l'icona della categoria
     */
    private function get_category_icon($category) {
        $icons = [
            'security' => '🔴',
            'database' => '🔵',
            'api' => '🟢',
            'structure' => '🟡',
            'functionality' => '🟣',
            'performance' => '⚫'
        ];
        return $icons[$category] ?? '⚪';
    }

    /**
     * Esegue tutti gli agenti
     * @return array Risultati combinati
     */
    public function run_all() {
        $this->start_time = microtime(true);
        $this->results = [];
        $this->summaries = [];

        foreach ($this->agents as $id => $agent) {
            $agent_results = $agent->run_all_tests();
            $this->results = array_merge($this->results, $agent_results);
            $this->summaries[$id] = $agent->get_summary();
        }

        return $this->results;
    }

    /**
     * Esegue solo gli agenti di una categoria
     * @param string $category
     * @return array
     */
    public function run_category($category) {
        $this->start_time = microtime(true);
        $this->results = [];
        $this->summaries = [];

        foreach ($this->agents as $id => $agent) {
            if ($agent->get_category() === $category) {
                $agent_results = $agent->run_all_tests();
                $this->results = array_merge($this->results, $agent_results);
                $this->summaries[$id] = $agent->get_summary();
            }
        }

        return $this->results;
    }

    /**
     * Esegue un singolo agente
     * @param string $agent_id
     * @return array
     */
    public function run_agent($agent_id) {
        $this->start_time = microtime(true);
        $this->results = [];
        $this->summaries = [];

        if (isset($this->agents[$agent_id])) {
            $agent = $this->agents[$agent_id];
            $this->results = $agent->run_all_tests();
            $this->summaries[$agent_id] = $agent->get_summary();
        }

        return $this->results;
    }

    /**
     * Esegue un singolo test di un agente
     * @param string $agent_id
     * @param string $test_code
     * @return array
     */
    public function run_single_test($agent_id, $test_code) {
        $this->start_time = microtime(true);
        $this->results = [];

        if (isset($this->agents[$agent_id])) {
            $this->results = $this->agents[$agent_id]->run_single_test($test_code);
        }

        return $this->results;
    }

    /**
     * Ottiene i risultati dell'ultima esecuzione
     * @return array
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Ottiene i sommari per agente
     * @return array
     */
    public function get_summaries() {
        return $this->summaries;
    }

    /**
     * Ottiene il sommario globale
     * @return array
     */
    public function get_global_summary() {
        $total = 0;
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $skipped = 0;

        foreach ($this->summaries as $summary) {
            $total += $summary['total'];
            $passed += $summary['passed'];
            $failed += $summary['failed'];
            $warnings += $summary['warnings'];
            $skipped += $summary['skipped'];
        }

        return [
            'total_agents' => count($this->summaries),
            'total_tests' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'skipped' => $skipped,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'execution_time' => microtime(true) - $this->start_time,
            'status' => $failed > 0 ? 'failed' : ($warnings > 0 ? 'warning' : 'passed')
        ];
    }

    /**
     * Ottiene i risultati raggruppati per status
     * @return array
     */
    public function get_results_by_status() {
        $by_status = [
            'failed' => [],
            'warning' => [],
            'passed' => [],
            'skipped' => []
        ];

        foreach ($this->results as $result) {
            $status = $result['status'] ?? 'skipped';
            $by_status[$status][] = $result;
        }

        return $by_status;
    }

    /**
     * Genera report HTML
     * @return string
     */
    public function generate_html_report() {
        $global = $this->get_global_summary();
        $by_status = $this->get_results_by_status();

        $html = '<div class="test-report">';

        // Header con summary
        $status_class = $global['status'] === 'passed' ? 'success' : ($global['status'] === 'warning' ? 'warning' : 'danger');
        $html .= '<div class="report-header alert alert-' . $status_class . '">';
        $html .= '<h3>Test Report - ' . date('d/m/Y H:i:s') . '</h3>';
        $html .= '<div class="summary-stats">';
        $html .= '<span class="stat"><strong>' . $global['total_tests'] . '</strong> test</span>';
        $html .= '<span class="stat text-success"><strong>' . $global['passed'] . '</strong> passati</span>';
        $html .= '<span class="stat text-danger"><strong>' . $global['failed'] . '</strong> falliti</span>';
        $html .= '<span class="stat text-warning"><strong>' . $global['warnings'] . '</strong> warning</span>';
        $html .= '<span class="stat"><strong>' . number_format($global['execution_time'], 2) . 's</strong> tempo</span>';
        $html .= '</div>';
        $html .= '<div class="progress" style="height: 20px; margin-top: 10px;">';
        $passed_pct = $global['total_tests'] > 0 ? ($global['passed'] / $global['total_tests']) * 100 : 0;
        $warning_pct = $global['total_tests'] > 0 ? ($global['warnings'] / $global['total_tests']) * 100 : 0;
        $failed_pct = $global['total_tests'] > 0 ? ($global['failed'] / $global['total_tests']) * 100 : 0;
        $html .= '<div class="progress-bar bg-success" style="width: ' . $passed_pct . '%"></div>';
        $html .= '<div class="progress-bar bg-warning" style="width: ' . $warning_pct . '%"></div>';
        $html .= '<div class="progress-bar bg-danger" style="width: ' . $failed_pct . '%"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // Errori (se presenti)
        if (!empty($by_status['failed'])) {
            $html .= '<div class="report-section">';
            $html .= '<h4 class="text-danger">❌ Test Falliti (' . count($by_status['failed']) . ')</h4>';
            $html .= '<table class="table table-sm table-bordered">';
            $html .= '<thead><tr><th>Codice</th><th>Test</th><th>Atteso</th><th>Ottenuto</th><th>Dettagli</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($by_status['failed'] as $r) {
                $html .= '<tr class="table-danger">';
                $html .= '<td><code>' . htmlspecialchars($r['code']) . '</code></td>';
                $html .= '<td>' . htmlspecialchars($r['name']) . '</td>';
                $html .= '<td><small>' . htmlspecialchars(substr($r['expected'] ?? '', 0, 50)) . '</small></td>';
                $html .= '<td><small>' . htmlspecialchars(substr($r['actual'] ?? '', 0, 50)) . '</small></td>';
                $html .= '<td><small>' . htmlspecialchars($r['details'] ?? '') . '</small></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Warnings (se presenti)
        if (!empty($by_status['warning'])) {
            $html .= '<div class="report-section">';
            $html .= '<h4 class="text-warning">⚠️ Warning (' . count($by_status['warning']) . ')</h4>';
            $html .= '<table class="table table-sm table-bordered">';
            $html .= '<thead><tr><th>Codice</th><th>Test</th><th>Dettagli</th></tr></thead>';
            $html .= '<tbody>';
            foreach (array_slice($by_status['warning'], 0, 20) as $r) {
                $html .= '<tr class="table-warning">';
                $html .= '<td><code>' . htmlspecialchars($r['code']) . '</code></td>';
                $html .= '<td>' . htmlspecialchars($r['name']) . '</td>';
                $html .= '<td><small>' . htmlspecialchars($r['details'] ?? '') . '</small></td>';
                $html .= '</tr>';
            }
            if (count($by_status['warning']) > 20) {
                $html .= '<tr><td colspan="3" class="text-center">... e altri ' . (count($by_status['warning']) - 20) . '</td></tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Summary per agente
        $html .= '<div class="report-section">';
        $html .= '<h4>📊 Summary per Agente</h4>';
        $html .= '<table class="table table-sm">';
        $html .= '<thead><tr><th>Agente</th><th>Categoria</th><th>Test</th><th>✅</th><th>❌</th><th>⚠️</th><th>Rate</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($this->summaries as $id => $s) {
            $row_class = $s['failed'] > 0 ? 'table-danger' : ($s['warnings'] > 0 ? 'table-warning' : '');
            $html .= '<tr class="' . $row_class . '">';
            $html .= '<td>' . htmlspecialchars($s['agent']) . '</td>';
            $html .= '<td>' . $this->get_category_icon($s['category']) . ' ' . $this->get_category_name($s['category']) . '</td>';
            $html .= '<td>' . $s['total'] . '</td>';
            $html .= '<td class="text-success">' . $s['passed'] . '</td>';
            $html .= '<td class="text-danger">' . $s['failed'] . '</td>';
            $html .= '<td class="text-warning">' . $s['warnings'] . '</td>';
            $html .= '<td>' . $s['success_rate'] . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
