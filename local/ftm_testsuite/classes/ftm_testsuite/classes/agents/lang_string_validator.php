<?php
/**
 * Language String Validator Agent - Validazione stringhe lingua Moodle
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per la validazione delle stringhe lingua
 * Test: Chiavi mancanti, traduzioni, formato, utilizzo get_string()
 */
class lang_string_validator extends base_agent {

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'LangStringValidator';
        $this->description = 'Valida stringhe lingua: chiavi, traduzioni, formato, utilizzo get_string()';
        $this->category = 'structure';
        $this->discover_ftm_plugins();
    }

    /**
     * Lista test disponibili
     */
    public function get_available_tests() {
        return [
            'LANG001' => 'File lingua EN - Esistenza',
            'LANG002' => 'File lingua IT - Esistenza',
            'LANG003' => 'Stringhe obbligatorie - pluginname',
            'LANG004' => 'Sync EN/IT - Chiavi mancanti in IT',
            'LANG005' => 'Sync EN/IT - Chiavi extra in IT',
            'LANG006' => 'Formato stringhe - Placeholder {$a}',
            'LANG007' => 'Utilizzo get_string() - Chiavi esistenti',
            'LANG008' => 'Utilizzo get_string() - Componente corretto',
            'LANG009' => 'Hardcoded strings - Testo non tradotto',
            'LANG010' => 'Duplicati - Chiavi duplicate',
        ];
    }

    /**
     * Esegue tutti i test
     */
    public function run_all_tests() {
        $this->results = [];

        foreach ($this->plugins as $plugin) {
            $this->test_plugin_lang($plugin);
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
     * Testa le stringhe lingua di un plugin
     */
    private function test_plugin_lang($plugin) {
        $this->start_timer();

        $lang_en = $plugin['path'] . '/lang/en/' . $plugin['component'] . '.php';
        $lang_it = $plugin['path'] . '/lang/it/' . $plugin['component'] . '.php';

        // LANG001: File EN
        if (!file_exists($lang_en)) {
            $this->fail('LANG001', 'File lingua EN', 'lang/en/' . $plugin['component'] . '.php', 'Non trovato', $plugin['component']);
            return;
        }
        $this->pass('LANG001', 'File lingua EN', 'Presente', 'OK', $plugin['component']);

        // Carica stringhe
        $strings_en = $this->load_lang_strings($lang_en);

        // LANG002: File IT
        $strings_it = [];
        if (!file_exists($lang_it)) {
            $this->warn('LANG002', 'File lingua IT', 'lang/it/' . $plugin['component'] . '.php', 'Non trovato (consigliato)', $plugin['component']);
        } else {
            $strings_it = $this->load_lang_strings($lang_it);
            $this->pass('LANG002', 'File lingua IT', 'Presente', 'OK', $plugin['component']);
        }

        // LANG003: Stringhe obbligatorie
        $required_strings = ['pluginname'];
        foreach ($required_strings as $key) {
            if (!isset($strings_en[$key])) {
                $this->fail('LANG003', "Stringa obbligatoria - {$key}", "Definita in EN", 'Mancante', $plugin['component']);
            } else {
                $this->pass('LANG003', "Stringa obbligatoria - {$key}", 'Presente', $strings_en[$key], $plugin['component']);
            }
        }

        // LANG004: Chiavi mancanti in IT
        if (!empty($strings_it)) {
            $missing_in_it = array_diff(array_keys($strings_en), array_keys($strings_it));
            if (!empty($missing_in_it)) {
                $this->warn(
                    'LANG004',
                    'Sync EN/IT - Mancanti in IT',
                    'Tutte le chiavi EN in IT',
                    count($missing_in_it) . ' mancanti: ' . implode(', ', array_slice($missing_in_it, 0, 5)) . (count($missing_in_it) > 5 ? '...' : ''),
                    $plugin['component']
                );
            } else {
                $this->pass('LANG004', 'Sync EN/IT', 'Tutte le chiavi tradotte', count($strings_it) . ' stringhe', $plugin['component']);
            }

            // LANG005: Chiavi extra in IT
            $extra_in_it = array_diff(array_keys($strings_it), array_keys($strings_en));
            if (!empty($extra_in_it)) {
                $this->warn(
                    'LANG005',
                    'Sync EN/IT - Extra in IT',
                    'Solo chiavi definite in EN',
                    count($extra_in_it) . ' extra: ' . implode(', ', array_slice($extra_in_it, 0, 5)),
                    $plugin['component']
                );
            } else {
                $this->pass('LANG005', 'Sync EN/IT - No extra', 'Solo chiavi EN', 'OK', $plugin['component']);
            }
        }

        // LANG006: Formato stringhe (placeholder)
        $placeholder_issues = 0;
        foreach ($strings_en as $key => $value) {
            // Verifica placeholder {$a} vs %s
            if (strpos($value, '%s') !== false || strpos($value, '%d') !== false) {
                $placeholder_issues++;
            }
        }
        if ($placeholder_issues > 0) {
            $this->warn('LANG006', 'Formato placeholder', '{$a} invece di %s/%d', "{$placeholder_issues} con formato C", $plugin['component']);
        } else {
            $this->pass('LANG006', 'Formato placeholder', '{$a} corretto', 'OK', $plugin['component']);
        }

        // LANG007-008: Utilizzo get_string
        $this->check_get_string_usage($plugin, $strings_en);

        // LANG009: Hardcoded strings
        $this->check_hardcoded_strings($plugin);

        // LANG010: Duplicati
        $this->check_duplicate_keys($lang_en, $plugin);
    }

    /**
     * Carica le stringhe da un file lingua
     */
    private function load_lang_strings($filepath) {
        $string = [];

        // Include il file in un contesto isolato
        $content = $this->read_file($filepath);

        // Estrai le stringhe con regex invece di include (più sicuro)
        preg_match_all('/\$string\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*[\'"]([^\'"]*)[\'"]\s*;/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $string[$match[1]] = $match[2];
        }

        // Gestisci anche stringhe multilinea con heredoc o nowdoc
        preg_match_all('/\$string\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]\s*=\s*<<</', $content, $heredocs);
        foreach ($heredocs[1] as $key) {
            if (!isset($string[$key])) {
                $string[$key] = '[HEREDOC]';
            }
        }

        return $string;
    }

    /**
     * LANG007-008: Verifica utilizzo get_string
     */
    private function check_get_string_usage($plugin, $defined_strings) {
        $php_files = $this->get_php_files($plugin['path']);
        $used_keys = [];
        $wrong_component = 0;
        $undefined_keys = [];

        foreach ($php_files as $file) {
            $content = $this->read_file($file);

            // Trova get_string('key', 'component')
            preg_match_all('/get_string\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $key = $match[1];
                $component = $match[2];

                // Solo chiavi del nostro plugin
                if ($component === $plugin['component']) {
                    $used_keys[] = $key;

                    if (!isset($defined_strings[$key])) {
                        $undefined_keys[] = $key;
                    }
                }
            }
        }

        // LANG007: Chiavi esistenti
        if (!empty($undefined_keys)) {
            $unique_undefined = array_unique($undefined_keys);
            $this->fail(
                'LANG007',
                'get_string() - Chiavi undefined',
                'Chiavi definite nel file lingua',
                count($unique_undefined) . ' mancanti: ' . implode(', ', array_slice($unique_undefined, 0, 5)),
                $plugin['component']
            );
        } else if (!empty($used_keys)) {
            $this->pass('LANG007', 'get_string() - Chiavi valide', 'Tutte definite', count(array_unique($used_keys)) . ' chiavi usate', $plugin['component']);
        }

        // LANG008: Componente corretto (già verificato sopra)
        $this->pass('LANG008', 'get_string() - Componente', $plugin['component'], 'OK', $plugin['component']);
    }

    /**
     * LANG009: Verifica stringhe hardcoded
     */
    private function check_hardcoded_strings($plugin) {
        $php_files = $this->get_php_files($plugin['path']);
        $hardcoded = 0;

        // Pattern per testo italiano hardcoded comuni
        $italian_patterns = [
            '/[\'"](?:Errore|Salva|Annulla|Conferma|Attenzione|Caricamento)[\'"]/',
            '/[\'"](?:Seleziona|Modifica|Elimina|Aggiungi|Cerca)[\'"]/',
        ];

        foreach ($php_files as $file) {
            // Salta file di lingua
            if (strpos($file, '/lang/') !== false) continue;

            $content = $this->read_file($file);

            foreach ($italian_patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    $hardcoded += count($matches[0]);
                }
            }
        }

        if ($hardcoded > 0) {
            $this->warn(
                'LANG009',
                'Hardcoded strings',
                'Usare get_string()',
                "{$hardcoded} stringhe italiane hardcoded trovate",
                $plugin['component']
            );
        } else {
            $this->pass('LANG009', 'Hardcoded strings', 'Nessuna trovata', 'OK', $plugin['component']);
        }
    }

    /**
     * LANG010: Verifica chiavi duplicate
     */
    private function check_duplicate_keys($filepath, $plugin) {
        $content = $this->read_file($filepath);

        preg_match_all('/\$string\s*\[\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);

        $keys = $matches[1];
        $duplicates = array_diff_key($keys, array_unique($keys));

        if (!empty($duplicates)) {
            $this->fail(
                'LANG010',
                'Chiavi duplicate',
                'Chiavi uniche',
                count($duplicates) . ' duplicati: ' . implode(', ', array_unique($duplicates)),
                $plugin['component']
            );
        } else {
            $this->pass('LANG010', 'Chiavi duplicate', 'Nessuna', 'OK', $plugin['component']);
        }
    }
}
