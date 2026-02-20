<?php
/**
 * Security Validator Agent - Test vulnerabilità sicurezza OWASP
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per la validazione della sicurezza
 * Test: SQL Injection, XSS, CSRF, sesskey, escape output
 */
class security_validator extends base_agent {

    /** @var array Vulnerabilità trovate */
    private $vulnerabilities = [];

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'SecurityValidator';
        $this->description = 'Verifica vulnerabilità OWASP: SQL Injection, XSS, CSRF, sesskey validation';
        $this->category = 'security';
        $this->discover_ftm_plugins();
    }

    /**
     * Lista test disponibili
     */
    public function get_available_tests() {
        return [
            'SEC001' => 'SQL Injection - Query non parametrizzate',
            'SEC002' => 'SQL Injection - Concatenazione stringhe in query',
            'SEC003' => 'XSS - Output non escaped',
            'SEC004' => 'XSS - Echo diretto di variabili $_GET/$_POST',
            'SEC005' => 'CSRF - Mancanza sesskey in form POST',
            'SEC006' => 'CSRF - Mancanza require_sesskey() in handler',
            'SEC007' => 'Input Validation - Mancanza PARAM_* in required_param',
            'SEC008' => 'Input Validation - Uso diretto $_GET/$_POST/$_REQUEST',
            'SEC009' => 'File Inclusion - Include/require con variabili',
            'SEC010' => 'Authentication - Pagine senza require_login()',
            'SEC011' => 'Authorization - Mancanza require_capability()',
            'SEC012' => 'Sensitive Data - Password/chiavi hardcoded',
        ];
    }

    /**
     * Esegue tutti i test
     */
    public function run_all_tests() {
        $this->results = [];
        $this->vulnerabilities = [];

        foreach ($this->plugins as $plugin) {
            $this->test_plugin_security($plugin);
        }

        return $this->results;
    }

    /**
     * Esegue un singolo test
     */
    public function run_single_test($test_code) {
        $this->start_timer();

        foreach ($this->plugins as $plugin) {
            $method = 'test_' . strtolower($test_code);
            if (method_exists($this, $method)) {
                $this->$method($plugin);
            }
        }

        return $this->results;
    }

    /**
     * Testa la sicurezza di un plugin
     */
    private function test_plugin_security($plugin) {
        $files = $this->get_php_files($plugin['path']);

        foreach ($files as $filepath) {
            $this->start_timer();
            $content = $this->read_file($filepath);
            if (!$content) continue;

            $relative_path = str_replace($plugin['path'], '', $filepath);

            // SEC001: Query SQL non parametrizzate
            $this->check_sql_injection_direct($content, $plugin, $relative_path);

            // SEC002: Concatenazione in query
            $this->check_sql_concatenation($content, $plugin, $relative_path);

            // SEC003: Output non escaped
            $this->check_xss_unescaped_output($content, $plugin, $relative_path);

            // SEC004: Echo diretto $_GET/$_POST
            $this->check_xss_direct_superglobals($content, $plugin, $relative_path);

            // SEC005: Form senza sesskey
            $this->check_csrf_form_sesskey($content, $plugin, $relative_path);

            // SEC006: Handler senza require_sesskey
            $this->check_csrf_handler_sesskey($content, $plugin, $relative_path);

            // SEC007: Mancanza PARAM_* validation
            $this->check_param_validation($content, $plugin, $relative_path);

            // SEC008: Uso diretto superglobals
            $this->check_direct_superglobals($content, $plugin, $relative_path);

            // SEC009: File inclusion con variabili
            $this->check_file_inclusion($content, $plugin, $relative_path);

            // SEC010: Pagine senza require_login
            $this->check_require_login($content, $plugin, $relative_path);

            // SEC011: Mancanza require_capability
            $this->check_require_capability($content, $plugin, $relative_path);

            // SEC012: Dati sensibili hardcoded
            $this->check_hardcoded_secrets($content, $plugin, $relative_path);
        }
    }

    /**
     * SEC001: Query SQL dirette non parametrizzate
     */
    private function check_sql_injection_direct($content, $plugin, $file) {
        // Cerca pattern pericolosi: $DB->execute("...{$var}...")
        $patterns = [
            '/\$DB->execute\s*\(\s*["\'][^"\']*\$[a-zA-Z_]+[^"\']*["\']\s*\)/s',
            '/\$DB->get_records_sql\s*\(\s*["\'][^"\']*\$[a-zA-Z_]+[^"\']*["\']\s*\)/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    // Verifica se usa placeholder
                    if (strpos($match, ':') === false && strpos($match, '?') === false) {
                        $this->fail(
                            'SEC001',
                            'SQL Injection - Query non parametrizzata',
                            'Query con placeholder (:param o ?)',
                            'Variabile interpolata direttamente',
                            "{$plugin['component']}{$file}: " . substr($match, 0, 100)
                        );
                        $this->vulnerabilities[] = [
                            'type' => 'SQL_INJECTION',
                            'severity' => 'CRITICAL',
                            'file' => $plugin['path'] . $file,
                            'code' => $match
                        ];
                    }
                }
            }
        }
    }

    /**
     * SEC002: Concatenazione stringhe in query SQL
     */
    private function check_sql_concatenation($content, $plugin, $file) {
        // Cerca: "SELECT ... " . $var o 'SELECT ... ' . $var
        $pattern = '/(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE)[^;]*\.\s*\$[a-zA-Z_]+/i';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                $this->fail(
                    'SEC002',
                    'SQL Injection - Concatenazione in query',
                    'Query con parametri separati',
                    'Concatenazione di variabile in SQL',
                    "{$plugin['component']}{$file}: " . substr($match, 0, 80)
                );
            }
        }
    }

    /**
     * SEC003: Output non escaped (XSS)
     */
    private function check_xss_unescaped_output($content, $plugin, $file) {
        // Cerca echo/print di variabili senza escape
        // Esclude echo di funzioni safe come get_string, format_string, s()
        $pattern = '/(?:echo|print)\s+\$(?!OUTPUT|PAGE|CFG|DB|USER)[a-zA-Z_]+(?:->|\[)[^;]*;/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                // Verifica se usa funzioni di escape
                if (!preg_match('/(?:s\(|format_string|htmlspecialchars|clean_param|format_text)/', $match)) {
                    $this->warn(
                        'SEC003',
                        'XSS - Output potenzialmente non escaped',
                        'Output con s() o format_string()',
                        'Output diretto di variabile',
                        "{$plugin['component']}{$file}: " . substr($match, 0, 80)
                    );
                }
            }
        }
    }

    /**
     * SEC004: Echo diretto di $_GET/$_POST
     */
    private function check_xss_direct_superglobals($content, $plugin, $file) {
        $pattern = '/(?:echo|print)\s+\$_(?:GET|POST|REQUEST)\s*\[/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                $this->fail(
                    'SEC004',
                    'XSS - Echo diretto superglobal',
                    'Uso di required_param() + s()',
                    'Echo diretto di $_GET/$_POST',
                    "{$plugin['component']}{$file}"
                );
            }
        }
    }

    /**
     * SEC005: Form POST senza sesskey
     */
    private function check_csrf_form_sesskey($content, $plugin, $file) {
        // Cerca form con method POST
        if (preg_match('/<form[^>]*method\s*=\s*["\']?post["\']?/i', $content)) {
            // Verifica presenza sesskey
            if (!preg_match('/sesskey|<input[^>]*name\s*=\s*["\']sesskey["\']/i', $content)) {
                $this->fail(
                    'SEC005',
                    'CSRF - Form POST senza sesskey',
                    'Campo hidden sesskey nel form',
                    'Form POST senza protezione CSRF',
                    "{$plugin['component']}{$file}"
                );
            } else {
                $this->pass(
                    'SEC005',
                    'CSRF - Form POST con sesskey',
                    'sesskey presente',
                    'sesskey trovato',
                    "{$plugin['component']}{$file}"
                );
            }
        }
    }

    /**
     * SEC006: Handler POST senza require_sesskey()
     */
    private function check_csrf_handler_sesskey($content, $plugin, $file) {
        // Se il file gestisce POST
        if (preg_match('/\$_POST|\$_REQUEST|optional_param.*PARAM|required_param/', $content)) {
            // E fa operazioni di scrittura
            if (preg_match('/\$DB->(?:insert|update|delete|execute)/', $content)) {
                // Deve avere require_sesskey()
                if (!preg_match('/require_sesskey\s*\(\s*\)/', $content)) {
                    $this->fail(
                        'SEC006',
                        'CSRF - Handler senza require_sesskey()',
                        'require_sesskey() prima di operazioni DB',
                        'Operazioni DB senza verifica sesskey',
                        "{$plugin['component']}{$file}"
                    );
                } else {
                    $this->pass(
                        'SEC006',
                        'CSRF - Handler con require_sesskey()',
                        'require_sesskey() presente',
                        'require_sesskey() trovato',
                        "{$plugin['component']}{$file}"
                    );
                }
            }
        }
    }

    /**
     * SEC007: Mancanza validazione PARAM_*
     */
    private function check_param_validation($content, $plugin, $file) {
        // Cerca required_param senza tipo o con PARAM_RAW
        $pattern = '/required_param\s*\([^)]*PARAM_RAW/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                $this->warn(
                    'SEC007',
                    'Input - Uso di PARAM_RAW',
                    'PARAM_* specifico (INT, TEXT, ALPHA, etc)',
                    'PARAM_RAW permette qualsiasi input',
                    "{$plugin['component']}{$file}"
                );
            }
        }
    }

    /**
     * SEC008: Uso diretto superglobals
     */
    private function check_direct_superglobals($content, $plugin, $file) {
        // Cerca uso di $_GET, $_POST, $_REQUEST non in contesto di verifica
        $pattern = '/\$_(?:GET|POST|REQUEST)\s*\[[^\]]+\](?!\s*===?\s*)/';

        if (preg_match_all($pattern, $content, $matches)) {
            // Esclude file di test e file che già usano required_param
            if (!preg_match('/required_param|optional_param/', $content)) {
                $this->warn(
                    'SEC008',
                    'Input - Uso diretto superglobals',
                    'required_param() o optional_param()',
                    'Accesso diretto a $_GET/$_POST/$_REQUEST',
                    "{$plugin['component']}{$file}: " . count($matches[0]) . " occorrenze"
                );
            }
        }
    }

    /**
     * SEC009: File inclusion con variabili
     */
    private function check_file_inclusion($content, $plugin, $file) {
        // Cerca include/require con variabili
        $pattern = '/(?:include|require)(?:_once)?\s*\(\s*\$[a-zA-Z_]+/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                // Esclude $CFG->dirroot che è sicuro
                if (strpos($match, '$CFG') === false) {
                    $this->fail(
                        'SEC009',
                        'File Inclusion - Path da variabile',
                        'Path hardcoded o da $CFG->dirroot',
                        'Include/require con variabile user-controlled',
                        "{$plugin['component']}{$file}: " . $match
                    );
                }
            }
        }
    }

    /**
     * SEC010: Pagine senza require_login()
     */
    private function check_require_login($content, $plugin, $file) {
        // Solo per file nella root del plugin (non classes, db, lang, etc)
        if (preg_match('/^\/[^\/]+\.php$/', $file)) {
            // Esclude file speciali
            if (!in_array(basename($file), ['version.php', 'lib.php', 'settings.php', 'db/install.php'])) {
                if (!preg_match('/require_login\s*\(/', $content) &&
                    !preg_match('/AJAX_SCRIPT|CLI_SCRIPT/', $content)) {
                    $this->fail(
                        'SEC010',
                        'Auth - Pagina senza require_login()',
                        'require_login() all\'inizio del file',
                        'Accesso possibile senza autenticazione',
                        "{$plugin['component']}{$file}"
                    );
                }
            }
        }
    }

    /**
     * SEC011: Mancanza require_capability()
     */
    private function check_require_capability($content, $plugin, $file) {
        // Per file che fanno operazioni sensibili
        if (preg_match('/\$DB->(?:insert|update|delete)/', $content)) {
            if (!preg_match('/require_capability|has_capability/', $content)) {
                $this->warn(
                    'SEC011',
                    'Authz - Operazioni DB senza capability check',
                    'require_capability() o has_capability()',
                    'Operazioni DB senza verifica permessi',
                    "{$plugin['component']}{$file}"
                );
            }
        }
    }

    /**
     * SEC012: Dati sensibili hardcoded
     */
    private function check_hardcoded_secrets($content, $plugin, $file) {
        // Cerca pattern di password/chiavi
        $patterns = [
            '/["\']password["\']\s*=>\s*["\'][^"\']{8,}["\']/i',
            '/api[_-]?key\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i',
            '/secret\s*=\s*["\'][^"\']{10,}["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $this->fail(
                    'SEC012',
                    'Sensitive - Dati sensibili hardcoded',
                    'Uso di get_config() o variabili ambiente',
                    'Password/chiavi nel codice sorgente',
                    "{$plugin['component']}{$file}"
                );
            }
        }
    }

    /**
     * Ottiene le vulnerabilità trovate
     */
    public function get_vulnerabilities() {
        return $this->vulnerabilities;
    }
}
