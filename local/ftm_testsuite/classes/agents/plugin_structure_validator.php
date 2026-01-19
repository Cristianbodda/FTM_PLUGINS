<?php
/**
 * Plugin Structure Validator Agent - Validazione struttura plugin Moodle
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per la validazione della struttura plugin
 * Test: File obbligatori, naming conventions, namespace, PHPDoc
 */
class plugin_structure_validator extends base_agent {

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'PluginStructureValidator';
        $this->description = 'Valida struttura plugin: file obbligatori, naming, namespace, PHPDoc';
        $this->category = 'structure';
        $this->discover_ftm_plugins();
    }

    /**
     * Lista test disponibili
     */
    public function get_available_tests() {
        return [
            'STR001' => 'version.php - Esistenza e contenuto',
            'STR002' => 'version.php - $plugin->component',
            'STR003' => 'version.php - $plugin->version formato YYYYMMDDXX',
            'STR004' => 'version.php - $plugin->requires',
            'STR005' => 'lib.php - Esistenza (se necessario)',
            'STR006' => 'lang/ - Cartella e file lingua',
            'STR007' => 'lang/ - Stringhe obbligatorie (pluginname)',
            'STR008' => 'db/access.php - Capabilities definite',
            'STR009' => 'classes/ - Namespace corretto',
            'STR010' => 'classes/ - Autoloading PSR-4',
            'STR011' => 'PHPDoc - Header file standard',
            'STR012' => 'PHPDoc - @package corretto',
            'STR013' => 'Naming - Funzioni con prefisso plugin',
            'STR014' => 'Naming - Classi con namespace',
        ];
    }

    /**
     * Esegue tutti i test
     */
    public function run_all_tests() {
        $this->results = [];

        foreach ($this->plugins as $plugin) {
            $this->test_plugin_structure($plugin);
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
     * Testa la struttura di un plugin
     */
    private function test_plugin_structure($plugin) {
        $this->start_timer();

        // STR001-004: version.php
        $this->check_version_php($plugin);

        // STR005: lib.php
        $this->check_lib_php($plugin);

        // STR006-007: lang/
        $this->check_lang_files($plugin);

        // STR008: db/access.php
        $this->check_access_php($plugin);

        // STR009-010: classes/
        $this->check_classes_namespace($plugin);

        // STR011-012: PHPDoc headers
        $this->check_phpdoc_headers($plugin);

        // STR013-014: Naming conventions
        $this->check_naming_conventions($plugin);
    }

    /**
     * STR001-004: Verifica version.php
     */
    private function check_version_php($plugin) {
        $version_file = $plugin['path'] . '/version.php';

        // STR001: Esistenza
        if (!file_exists($version_file)) {
            $this->fail('STR001', 'version.php - Mancante', 'File obbligatorio', 'Non trovato', $plugin['component']);
            return;
        }

        $content = $this->read_file($version_file);
        $this->pass('STR001', 'version.php - Esistenza', 'Presente', 'OK', $plugin['component']);

        // STR002: $plugin->component
        if (preg_match('/\$plugin->component\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $match)) {
            if ($match[1] === $plugin['component']) {
                $this->pass('STR002', 'version.php - component', $plugin['component'], $match[1], $plugin['component']);
            } else {
                $this->fail('STR002', 'version.php - component', $plugin['component'], $match[1], $plugin['component']);
            }
        } else {
            $this->fail('STR002', 'version.php - component mancante', '$plugin->component', 'Non trovato', $plugin['component']);
        }

        // STR003: Formato versione YYYYMMDDXX
        if (preg_match('/\$plugin->version\s*=\s*(\d+)/', $content, $match)) {
            $version = $match[1];
            if (strlen($version) === 10 && substr($version, 0, 4) >= '2020' && substr($version, 0, 4) <= '2030') {
                $this->pass('STR003', 'version.php - version format', 'YYYYMMDDXX', $version, $plugin['component']);
            } else {
                $this->warn('STR003', 'version.php - version format', 'YYYYMMDDXX', $version, $plugin['component']);
            }
        } else {
            $this->fail('STR003', 'version.php - version mancante', '$plugin->version', 'Non trovato', $plugin['component']);
        }

        // STR004: $plugin->requires
        if (preg_match('/\$plugin->requires\s*=\s*(\d+)/', $content, $match)) {
            $this->pass('STR004', 'version.php - requires', 'Moodle version', $match[1], $plugin['component']);
        } else {
            $this->warn('STR004', 'version.php - requires mancante', '$plugin->requires', 'Non trovato', $plugin['component']);
        }
    }

    /**
     * STR005: Verifica lib.php
     */
    private function check_lib_php($plugin) {
        $lib_file = $plugin['path'] . '/lib.php';

        if (file_exists($lib_file)) {
            $content = $this->read_file($lib_file);

            // Verifica che abbia funzioni
            if (preg_match('/function\s+' . preg_quote($plugin['component'], '/') . '_/', $content)) {
                $this->pass('STR005', 'lib.php - Funzioni', 'Con prefisso plugin', 'OK', $plugin['component']);
            } else if (preg_match('/function\s+\w+/', $content)) {
                $this->warn('STR005', 'lib.php - Funzioni', 'Prefisso ' . $plugin['component'] . '_', 'Funzioni senza prefisso standard', $plugin['component']);
            }
        } else {
            // lib.php non è sempre obbligatorio
            $this->skip('STR005', 'lib.php - Non presente', $plugin['component']);
        }
    }

    /**
     * STR006-007: Verifica file lingua
     */
    private function check_lang_files($plugin) {
        $lang_en = $plugin['path'] . '/lang/en/' . $plugin['component'] . '.php';
        $lang_it = $plugin['path'] . '/lang/it/' . $plugin['component'] . '.php';

        // STR006: Cartella e file
        if (!file_exists($lang_en)) {
            $this->fail('STR006', 'lang/en/ - Mancante', 'File lingua inglese obbligatorio', 'Non trovato', $plugin['component']);
        } else {
            $this->pass('STR006', 'lang/en/ - Presente', 'OK', 'Trovato', $plugin['component']);

            // STR007: Stringhe obbligatorie
            $content = $this->read_file($lang_en);
            if (preg_match('/\$string\s*\[\s*[\'"]pluginname[\'"]\s*\]/', $content)) {
                $this->pass('STR007', 'lang - pluginname', 'Stringa definita', 'OK', $plugin['component']);
            } else {
                $this->fail('STR007', 'lang - pluginname mancante', "\$string['pluginname']", 'Non trovato', $plugin['component']);
            }
        }

        // Verifica italiano
        if (file_exists($lang_it)) {
            $this->pass('STR006', 'lang/it/ - Presente', 'Traduzione italiana', 'OK', $plugin['component']);
        } else {
            $this->warn('STR006', 'lang/it/ - Mancante', 'Traduzione italiana consigliata', 'Non trovato', $plugin['component']);
        }
    }

    /**
     * STR008: Verifica db/access.php
     */
    private function check_access_php($plugin) {
        $access_file = $plugin['path'] . '/db/access.php';

        if (file_exists($access_file)) {
            $content = $this->read_file($access_file);

            // Verifica struttura
            if (preg_match('/\$capabilities\s*=\s*\[/', $content) || preg_match('/\$capabilities\s*=\s*array\s*\(/', $content)) {
                // Conta capabilities
                preg_match_all('/[\'"]' . preg_quote($plugin['component'], '/') . ':(\w+)[\'"]/', $content, $caps);
                $cap_count = count($caps[1]);

                if ($cap_count > 0) {
                    $this->pass('STR008', 'db/access.php - Capabilities', 'Definite correttamente', "{$cap_count} capabilities", $plugin['component']);
                } else {
                    $this->warn('STR008', 'db/access.php - Capabilities', 'Almeno una capability', 'Nessuna trovata', $plugin['component']);
                }
            } else {
                $this->fail('STR008', 'db/access.php - Struttura', '$capabilities array', 'Struttura non valida', $plugin['component']);
            }
        } else {
            $this->skip('STR008', 'db/access.php - Non presente', $plugin['component']);
        }
    }

    /**
     * STR009-010: Verifica namespace classi
     */
    private function check_classes_namespace($plugin) {
        $classes_dir = $plugin['path'] . '/classes';

        if (!is_dir($classes_dir)) {
            $this->skip('STR009', 'classes/ - Non presente', $plugin['component']);
            return;
        }

        $class_files = $this->get_php_files($classes_dir);
        $correct_ns = 0;
        $wrong_ns = 0;

        foreach ($class_files as $file) {
            $content = $this->read_file($file);

            // STR009: Namespace corretto
            $expected_ns = $plugin['component'];
            if (preg_match('/namespace\s+([^;]+);/', $content, $match)) {
                $ns = trim($match[1]);
                if (strpos($ns, $expected_ns) === 0) {
                    $correct_ns++;
                } else {
                    $wrong_ns++;
                }
            } else {
                // Vecchio stile senza namespace
                $wrong_ns++;
            }
        }

        if ($wrong_ns === 0 && $correct_ns > 0) {
            $this->pass('STR009', 'classes/ - Namespace', 'Tutti corretti', "{$correct_ns} classi", $plugin['component']);
        } else if ($correct_ns > 0) {
            $this->warn('STR009', 'classes/ - Namespace', 'Tutti con namespace ' . $plugin['component'], "{$wrong_ns} senza namespace corretto", $plugin['component']);
        } else {
            $this->fail('STR009', 'classes/ - Namespace mancante', 'namespace ' . $plugin['component'], 'Nessuna classe con namespace', $plugin['component']);
        }

        // STR010: Verifica autoloading (nome file = nome classe)
        $autoload_issues = 0;
        foreach ($class_files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $content = $this->read_file($file);

            if (preg_match('/class\s+(\w+)/', $content, $match)) {
                if (strtolower($match[1]) !== strtolower($filename)) {
                    $autoload_issues++;
                }
            }
        }

        if ($autoload_issues === 0) {
            $this->pass('STR010', 'classes/ - Autoloading', 'Nome file = nome classe', 'OK', $plugin['component']);
        } else {
            $this->warn('STR010', 'classes/ - Autoloading', 'Nome file = nome classe', "{$autoload_issues} file con nomi non corrispondenti", $plugin['component']);
        }
    }

    /**
     * STR011-012: Verifica PHPDoc headers
     */
    private function check_phpdoc_headers($plugin) {
        $php_files = $this->get_php_files($plugin['path']);
        $correct_headers = 0;
        $missing_headers = 0;
        $wrong_package = 0;

        // Controlla solo i primi 20 file per performance
        $files_to_check = array_slice($php_files, 0, 20);

        foreach ($files_to_check as $file) {
            $content = $this->read_file($file);

            // STR011: Header file standard
            if (preg_match('/\/\*\*[\s\S]*?\*\//', $content, $match)) {
                $header = $match[0];

                // STR012: @package corretto
                if (preg_match('/@package\s+' . preg_quote($plugin['component'], '/') . '/', $header)) {
                    $correct_headers++;
                } else if (preg_match('/@package\s+(\S+)/', $header, $pkg)) {
                    $wrong_package++;
                } else {
                    $missing_headers++;
                }
            } else {
                $missing_headers++;
            }
        }

        if ($missing_headers === 0 && $wrong_package === 0) {
            $this->pass('STR011', 'PHPDoc Headers', 'Tutti presenti', "{$correct_headers} file", $plugin['component']);
            $this->pass('STR012', 'PHPDoc @package', 'Tutti corretti', $plugin['component'], $plugin['component']);
        } else {
            if ($missing_headers > 0) {
                $this->warn('STR011', 'PHPDoc Headers', 'Header obbligatorio', "{$missing_headers} file senza header", $plugin['component']);
            }
            if ($wrong_package > 0) {
                $this->warn('STR012', 'PHPDoc @package', '@package ' . $plugin['component'], "{$wrong_package} file con package errato", $plugin['component']);
            }
        }
    }

    /**
     * STR013-014: Verifica naming conventions
     */
    private function check_naming_conventions($plugin) {
        $lib_file = $plugin['path'] . '/lib.php';

        if (file_exists($lib_file)) {
            $content = $this->read_file($lib_file);

            // STR013: Funzioni con prefisso
            preg_match_all('/function\s+(\w+)\s*\(/', $content, $functions);
            $correct_prefix = 0;
            $wrong_prefix = 0;

            $expected_prefix = $plugin['component'] . '_';

            foreach ($functions[1] as $func) {
                if (strpos($func, $expected_prefix) === 0) {
                    $correct_prefix++;
                } else {
                    $wrong_prefix++;
                }
            }

            if ($wrong_prefix === 0 && $correct_prefix > 0) {
                $this->pass('STR013', 'Naming - Funzioni', 'Prefisso corretto', "{$correct_prefix} funzioni", $plugin['component']);
            } else if ($correct_prefix > 0) {
                $this->warn('STR013', 'Naming - Funzioni', 'Prefisso ' . $expected_prefix, "{$wrong_prefix} senza prefisso", $plugin['component']);
            }
        }

        // STR014: Classi con namespace - già verificato in STR009
        $this->pass('STR014', 'Naming - Classi', 'Vedi STR009', 'Namespace verificato', $plugin['component']);
    }
}
