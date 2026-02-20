<?php
/**
 * AJAX Endpoint Tester Agent - Test endpoint AJAX Moodle
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per il test degli endpoint AJAX
 * Test: Response format, error handling, PARAM validation, sesskey
 */
class ajax_endpoint_tester extends base_agent {

    /** @var array Endpoint trovati */
    private $endpoints = [];

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'AjaxEndpointTester';
        $this->description = 'Testa endpoint AJAX: formato response, error handling, validazione parametri';
        $this->category = 'api';
        $this->discover_ftm_plugins();
    }

    /**
     * Lista test disponibili
     */
    public function get_available_tests() {
        return [
            'AJAX001' => 'Endpoint Discovery - Trova file ajax*.php',
            'AJAX002' => 'AJAX_SCRIPT - Definizione costante',
            'AJAX003' => 'Content-Type - Header JSON',
            'AJAX004' => 'Response Format - Struttura JSON',
            'AJAX005' => 'Error Handling - Try/catch con response',
            'AJAX006' => 'HTTP Status - Codici errore appropriati',
            'AJAX007' => 'PARAM Validation - Tutti i parametri validati',
            'AJAX008' => 'Sesskey - Protezione CSRF',
            'AJAX009' => 'Authentication - require_login()',
            'AJAX010' => 'Output Clean - Nessun output prima di JSON',
        ];
    }

    /**
     * Esegue tutti i test
     */
    public function run_all_tests() {
        $this->results = [];
        $this->endpoints = [];

        // Prima scopri tutti gli endpoint
        $this->discover_ajax_endpoints();

        // Poi testa ogni endpoint
        foreach ($this->endpoints as $endpoint) {
            $this->test_endpoint($endpoint);
        }

        return $this->results;
    }

    /**
     * Esegue un singolo test
     */
    public function run_single_test($test_code) {
        $this->start_timer();
        $this->discover_ajax_endpoints();

        foreach ($this->endpoints as $endpoint) {
            $method = 'check_' . strtolower(substr($test_code, 4));
            if (method_exists($this, $method)) {
                $this->$method($endpoint);
            }
        }

        return $this->results;
    }

    /**
     * AJAX001: Scopre gli endpoint AJAX
     */
    private function discover_ajax_endpoints() {
        foreach ($this->plugins as $plugin) {
            // Cerca file ajax*.php nella root e in sottocartelle ajax/
            $patterns = [
                $plugin['path'] . '/ajax*.php',
                $plugin['path'] . '/ajax/*.php',
            ];

            foreach ($patterns as $pattern) {
                $files = glob($pattern);
                foreach ($files as $file) {
                    $this->endpoints[] = [
                        'plugin' => $plugin,
                        'file' => $file,
                        'name' => basename($file),
                        'relative' => str_replace($plugin['path'], '', $file)
                    ];
                }
            }
        }

        $count = count($this->endpoints);
        if ($count > 0) {
            $this->pass('AJAX001', 'Endpoint Discovery', 'Endpoint trovati', "{$count} file ajax*.php", '');
        } else {
            $this->skip('AJAX001', 'Endpoint Discovery', 'Nessun endpoint AJAX trovato');
        }
    }

    /**
     * Testa un singolo endpoint
     */
    private function test_endpoint($endpoint) {
        $this->start_timer();
        $content = $this->read_file($endpoint['file']);
        if (!$content) return;

        $label = $endpoint['plugin']['component'] . $endpoint['relative'];

        // AJAX002: AJAX_SCRIPT
        $this->check_ajax_script($content, $label);

        // AJAX003: Content-Type
        $this->check_content_type($content, $label);

        // AJAX004: Response Format
        $this->check_response_format($content, $label);

        // AJAX005: Error Handling
        $this->check_error_handling($content, $label);

        // AJAX006: HTTP Status
        $this->check_http_status($content, $label);

        // AJAX007: PARAM Validation
        $this->check_param_validation($content, $label);

        // AJAX008: Sesskey
        $this->check_sesskey($content, $label);

        // AJAX009: Authentication
        $this->check_authentication($content, $label);

        // AJAX010: Output Clean
        $this->check_output_clean($content, $label);
    }

    /**
     * AJAX002: Verifica AJAX_SCRIPT
     */
    private function check_ajax_script($content, $label) {
        if (preg_match('/define\s*\(\s*[\'"]AJAX_SCRIPT[\'"]\s*,\s*true\s*\)/', $content)) {
            $this->pass('AJAX002', 'AJAX_SCRIPT', 'Definito', 'OK', $label);
        } else {
            $this->fail(
                'AJAX002',
                'AJAX_SCRIPT - Mancante',
                "define('AJAX_SCRIPT', true)",
                'Non trovato',
                $label
            );
        }
    }

    /**
     * AJAX003: Verifica Content-Type
     */
    private function check_content_type($content, $label) {
        if (preg_match('/header\s*\(\s*[\'"]Content-Type:\s*application\/json/', $content)) {
            $this->pass('AJAX003', 'Content-Type JSON', 'Header presente', 'OK', $label);
        } else {
            $this->warn(
                'AJAX003',
                'Content-Type JSON - Mancante',
                "header('Content-Type: application/json')",
                'Non trovato (potrebbe usare $OUTPUT)',
                $label
            );
        }
    }

    /**
     * AJAX004: Verifica formato response
     */
    private function check_response_format($content, $label) {
        // Cerca json_encode con struttura standard
        $has_success = preg_match('/[\'"]success[\'"]\s*=>/', $content);
        $has_json_encode = preg_match('/json_encode\s*\(/', $content);

        if ($has_json_encode && $has_success) {
            $this->pass('AJAX004', 'Response Format', "['success' => ...] con json_encode", 'OK', $label);
        } else if ($has_json_encode) {
            $this->warn(
                'AJAX004',
                'Response Format',
                "Struttura standard: ['success' => bool, 'data' => ..., 'message' => ...]",
                'json_encode trovato ma struttura non standard',
                $label
            );
        } else {
            $this->fail('AJAX004', 'Response Format', 'json_encode()', 'Non trovato', $label);
        }
    }

    /**
     * AJAX005: Verifica error handling
     */
    private function check_error_handling($content, $label) {
        $has_try = preg_match('/try\s*\{/', $content);
        $has_catch = preg_match('/catch\s*\(\s*(?:Exception|\\\Exception|Throwable)/', $content);
        $has_error_response = preg_match('/[\'"]success[\'"]\s*=>\s*false/', $content);

        if ($has_try && $has_catch && $has_error_response) {
            $this->pass('AJAX005', 'Error Handling', 'try/catch con error response', 'OK', $label);
        } else if ($has_try && $has_catch) {
            $this->warn(
                'AJAX005',
                'Error Handling',
                "Catch deve restituire JSON con success=false",
                'try/catch presente ma response non standard',
                $label
            );
        } else {
            $this->fail(
                'AJAX005',
                'Error Handling - Mancante',
                'try/catch block con error response',
                'Non trovato',
                $label
            );
        }
    }

    /**
     * AJAX006: Verifica HTTP status codes
     */
    private function check_http_status($content, $label) {
        $has_http_code = preg_match('/http_response_code\s*\(\s*\d+\s*\)/', $content);
        $has_header_status = preg_match('/header\s*\(\s*[\'"]HTTP/', $content);

        if ($has_http_code || $has_header_status) {
            $this->pass('AJAX006', 'HTTP Status', 'Status code appropriato', 'OK', $label);
        } else {
            $this->warn(
                'AJAX006',
                'HTTP Status',
                'http_response_code() per errori',
                'Non trovato - errori potrebbero restituire 200',
                $label
            );
        }
    }

    /**
     * AJAX007: Verifica PARAM validation
     */
    private function check_param_validation($content, $label) {
        // Conta required_param e optional_param
        preg_match_all('/(?:required|optional)_param\s*\([^)]+,\s*PARAM_(\w+)/', $content, $params);

        if (!empty($params[1])) {
            $param_types = array_unique($params[1]);
            $has_raw = in_array('RAW', $param_types);

            if ($has_raw) {
                $this->warn(
                    'AJAX007',
                    'PARAM Validation',
                    'PARAM_* specifici (no RAW)',
                    'PARAM_RAW trovato - potenziale rischio',
                    $label
                );
            } else {
                $this->pass(
                    'AJAX007',
                    'PARAM Validation',
                    'Parametri validati',
                    count($params[0]) . ' parametri con tipi: ' . implode(', ', $param_types),
                    $label
                );
            }
        } else {
            // Verifica se usa superglobals diretti
            if (preg_match('/\$_(?:GET|POST|REQUEST)\s*\[/', $content)) {
                $this->fail(
                    'AJAX007',
                    'PARAM Validation - Mancante',
                    'required_param() / optional_param()',
                    'Usa $_GET/$_POST direttamente',
                    $label
                );
            } else {
                $this->skip('AJAX007', 'PARAM Validation', 'Nessun parametro rilevato');
            }
        }
    }

    /**
     * AJAX008: Verifica sesskey
     */
    private function check_sesskey($content, $label) {
        $has_require_sesskey = preg_match('/require_sesskey\s*\(\s*\)/', $content);
        $has_confirm_sesskey = preg_match('/confirm_sesskey\s*\(\s*\)/', $content);
        $checks_sesskey_param = preg_match('/sesskey.*===|===.*sesskey/', $content);

        if ($has_require_sesskey || $has_confirm_sesskey) {
            $this->pass('AJAX008', 'Sesskey Protection', 'require_sesskey()', 'OK', $label);
        } else if ($checks_sesskey_param) {
            $this->pass('AJAX008', 'Sesskey Protection', 'Verifica manuale sesskey', 'OK', $label);
        } else {
            $this->fail(
                'AJAX008',
                'Sesskey - CSRF Protection Mancante',
                'require_sesskey() all\'inizio',
                'Non trovato - vulnerabile a CSRF',
                $label
            );
        }
    }

    /**
     * AJAX009: Verifica authentication
     */
    private function check_authentication($content, $label) {
        if (preg_match('/require_login\s*\(\s*\)/', $content)) {
            $this->pass('AJAX009', 'Authentication', 'require_login()', 'OK', $label);
        } else {
            $this->fail(
                'AJAX009',
                'Authentication - Mancante',
                'require_login() all\'inizio',
                'Non trovato - accessibile senza login',
                $label
            );
        }
    }

    /**
     * AJAX010: Verifica output pulito
     */
    private function check_output_clean($content, $label) {
        // Cerca echo/print prima di json_encode (escludendo header())
        $lines = explode("\n", $content);
        $found_json = false;
        $output_before_json = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Salta commenti
            if (strpos($line, '//') === 0 || strpos($line, '*') === 0) continue;

            if (preg_match('/json_encode/', $line)) {
                $found_json = true;
                break;
            }

            // Cerca output (echo, print) che non sia header()
            if (preg_match('/^(?:echo|print)\s+/', $line) && strpos($line, 'header') === false) {
                $output_before_json = true;
            }
        }

        if ($output_before_json) {
            $this->warn(
                'AJAX010',
                'Output Clean',
                'Nessun output prima di json_encode',
                'Trovato echo/print prima di JSON',
                $label
            );
        } else {
            $this->pass('AJAX010', 'Output Clean', 'Nessun output spurio', 'OK', $label);
        }
    }

    /**
     * Ottiene gli endpoint trovati
     */
    public function get_endpoints() {
        return $this->endpoints;
    }
}
