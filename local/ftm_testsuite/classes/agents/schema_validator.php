<?php
/**
 * Schema Validator Agent - Validazione struttura database Moodle
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite\agents;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_agent.php');

/**
 * Agente per la validazione dello schema database
 * Test: install.xml, foreign keys, indexes, field types, upgrade.php
 */
class schema_validator extends base_agent {

    /**
     * Inizializza l'agente
     */
    protected function init() {
        $this->name = 'SchemaValidator';
        $this->description = 'Valida install.xml, foreign keys, indexes, tipi campi e upgrade.php';
        $this->category = 'database';
        $this->discover_ftm_plugins();
    }

    /**
     * Lista test disponibili
     */
    public function get_available_tests() {
        return [
            'DB001' => 'install.xml - Esistenza file',
            'DB002' => 'install.xml - Sintassi XML valida',
            'DB003' => 'install.xml - Struttura XMLDB corretta',
            'DB004' => 'install.xml - Naming convention tabelle',
            'DB005' => 'install.xml - Campo ID primario',
            'DB006' => 'install.xml - Foreign keys definite',
            'DB007' => 'install.xml - Indexes per campi frequenti',
            'DB008' => 'install.xml - Tipi campi appropriati',
            'DB009' => 'install.xml - Campi timestamp standard',
            'DB010' => 'upgrade.php - Esistenza e sintassi',
            'DB011' => 'upgrade.php - Versioni incrementali',
            'DB012' => 'Tabelle - Sincronizzazione DB reale',
        ];
    }

    /**
     * Esegue tutti i test
     */
    public function run_all_tests() {
        $this->results = [];

        foreach ($this->plugins as $plugin) {
            $this->test_plugin_schema($plugin);
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
     * Testa lo schema di un plugin
     */
    private function test_plugin_schema($plugin) {
        $install_xml = $plugin['path'] . '/db/install.xml';
        $upgrade_php = $plugin['path'] . '/db/upgrade.php';

        $this->start_timer();

        // DB001: Esistenza install.xml
        if (!file_exists($install_xml)) {
            $this->skip('DB001', 'install.xml - Non presente', "{$plugin['component']}: Plugin senza tabelle database");
            return;
        }

        $this->pass('DB001', 'install.xml - Esistenza', 'File presente', 'Trovato', $plugin['component']);

        // Carica e valida XML
        $xml_content = $this->read_file($install_xml);

        // DB002: Sintassi XML valida
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false || !empty($errors)) {
            $error_msg = !empty($errors) ? $errors[0]->message : 'Errore parsing XML';
            $this->fail('DB002', 'install.xml - Sintassi XML', 'XML valido', 'Errore: ' . $error_msg, $plugin['component']);
            return;
        }

        $this->pass('DB002', 'install.xml - Sintassi XML', 'XML valido', 'Parsing OK', $plugin['component']);

        // DB003: Struttura XMLDB
        $this->check_xmldb_structure($xml, $plugin);

        // Analizza ogni tabella
        if (isset($xml->TABLES->TABLE)) {
            foreach ($xml->TABLES->TABLE as $table) {
                $this->validate_table($table, $plugin);
            }
        }

        // DB010-011: upgrade.php
        $this->check_upgrade_php($upgrade_php, $plugin);

        // DB012: Sincronizzazione con DB reale
        $this->check_db_sync($xml, $plugin);
    }

    /**
     * DB003: Verifica struttura XMLDB
     */
    private function check_xmldb_structure($xml, $plugin) {
        // Verifica attributi root
        if (!isset($xml['PATH']) || !isset($xml['VERSION'])) {
            $this->fail('DB003', 'XMLDB - Attributi root', 'PATH e VERSION presenti', 'Attributi mancanti', $plugin['component']);
        } else {
            $this->pass('DB003', 'XMLDB - Attributi root', 'PATH e VERSION', 'Presenti', $plugin['component']);
        }

        // Verifica presenza TABLES
        if (!isset($xml->TABLES)) {
            $this->fail('DB003', 'XMLDB - Elemento TABLES', 'TABLES presente', 'Mancante', $plugin['component']);
        }
    }

    /**
     * Valida una singola tabella
     */
    private function validate_table($table, $plugin) {
        $table_name = (string)$table['NAME'];

        // DB004: Naming convention
        $expected_prefix = str_replace(['local_', 'block_', 'qbank_'], '', $plugin['component']);
        if (strpos($table_name, $expected_prefix) === false && strpos($table_name, 'local_') === false) {
            $this->warn(
                'DB004',
                "Naming - Tabella {$table_name}",
                "Prefisso {$expected_prefix}_",
                "Nome: {$table_name}",
                $plugin['component']
            );
        } else {
            $this->pass('DB004', "Naming - Tabella {$table_name}", 'Prefisso corretto', 'OK', $plugin['component']);
        }

        // DB005: Campo ID primario
        $has_id = false;
        $has_primary_key = false;

        if (isset($table->FIELDS->FIELD)) {
            foreach ($table->FIELDS->FIELD as $field) {
                if ((string)$field['NAME'] === 'id' && (string)$field['SEQUENCE'] === 'true') {
                    $has_id = true;
                }
            }
        }

        if (isset($table->KEYS->KEY)) {
            foreach ($table->KEYS->KEY as $key) {
                if ((string)$key['TYPE'] === 'primary') {
                    $has_primary_key = true;
                }
            }
        }

        if (!$has_id || !$has_primary_key) {
            $this->fail(
                'DB005',
                "ID Primario - {$table_name}",
                'Campo id SEQUENCE + KEY primary',
                'ID: ' . ($has_id ? 'OK' : 'NO') . ', PK: ' . ($has_primary_key ? 'OK' : 'NO'),
                $plugin['component']
            );
        } else {
            $this->pass('DB005', "ID Primario - {$table_name}", 'Presente', 'OK', $plugin['component']);
        }

        // DB006: Foreign keys
        $this->check_foreign_keys($table, $plugin);

        // DB007: Indexes
        $this->check_indexes($table, $plugin);

        // DB008: Tipi campi
        $this->check_field_types($table, $plugin);

        // DB009: Timestamp fields
        $this->check_timestamp_fields($table, $plugin);
    }

    /**
     * DB006: Verifica foreign keys
     */
    private function check_foreign_keys($table, $plugin) {
        $table_name = (string)$table['NAME'];
        $fk_fields = [];
        $defined_fks = [];

        // Trova campi che sembrano FK (terminano in 'id' ma non sono 'id')
        if (isset($table->FIELDS->FIELD)) {
            foreach ($table->FIELDS->FIELD as $field) {
                $fname = (string)$field['NAME'];
                if ($fname !== 'id' && (
                    substr($fname, -2) === 'id' ||
                    substr($fname, -3) === '_id' ||
                    in_array($fname, ['userid', 'courseid', 'contextid', 'groupid'])
                )) {
                    $fk_fields[] = $fname;
                }
            }
        }

        // Trova FK definite
        if (isset($table->KEYS->KEY)) {
            foreach ($table->KEYS->KEY as $key) {
                if ((string)$key['TYPE'] === 'foreign' || (string)$key['TYPE'] === 'foreign-unique') {
                    $defined_fks[] = (string)$key['FIELDS'];
                }
            }
        }

        // Verifica che ogni campo FK abbia una chiave definita
        $missing_fks = array_diff($fk_fields, $defined_fks);

        if (!empty($missing_fks)) {
            $this->warn(
                'DB006',
                "Foreign Keys - {$table_name}",
                'FK definite per tutti i campi *id',
                'Mancanti: ' . implode(', ', $missing_fks),
                $plugin['component']
            );
        } else if (!empty($fk_fields)) {
            $this->pass('DB006', "Foreign Keys - {$table_name}", 'Tutte definite', count($defined_fks) . ' FK', $plugin['component']);
        }
    }

    /**
     * DB007: Verifica indexes
     */
    private function check_indexes($table, $plugin) {
        $table_name = (string)$table['NAME'];
        $has_status_index = false;
        $has_time_index = false;

        // Campi che dovrebbero avere index
        $should_index = ['status', 'userid', 'courseid', 'timecreated'];
        $existing_fields = [];

        if (isset($table->FIELDS->FIELD)) {
            foreach ($table->FIELDS->FIELD as $field) {
                $existing_fields[] = (string)$field['NAME'];
            }
        }

        // Verifica indexes esistenti
        $indexed_fields = [];
        if (isset($table->INDEXES->INDEX)) {
            foreach ($table->INDEXES->INDEX as $index) {
                $fields = explode(', ', (string)$index['FIELDS']);
                $indexed_fields = array_merge($indexed_fields, $fields);
            }
        }

        // Verifica anche le keys (foreign keys creano automaticamente index)
        if (isset($table->KEYS->KEY)) {
            foreach ($table->KEYS->KEY as $key) {
                if ((string)$key['TYPE'] !== 'primary') {
                    $indexed_fields[] = (string)$key['FIELDS'];
                }
            }
        }

        // Trova campi che dovrebbero essere indicizzati ma non lo sono
        $missing_indexes = [];
        foreach ($should_index as $field) {
            if (in_array($field, $existing_fields) && !in_array($field, $indexed_fields)) {
                $missing_indexes[] = $field;
            }
        }

        if (!empty($missing_indexes)) {
            $this->warn(
                'DB007',
                "Indexes - {$table_name}",
                'Index su campi frequenti',
                'Suggeriti: ' . implode(', ', $missing_indexes),
                $plugin['component']
            );
        } else {
            $this->pass('DB007', "Indexes - {$table_name}", 'Indexes appropriati', 'OK', $plugin['component']);
        }
    }

    /**
     * DB008: Verifica tipi campi
     */
    private function check_field_types($table, $plugin) {
        $table_name = (string)$table['NAME'];
        $issues = [];

        if (isset($table->FIELDS->FIELD)) {
            foreach ($table->FIELDS->FIELD as $field) {
                $fname = (string)$field['NAME'];
                $ftype = (string)$field['TYPE'];
                $flength = (string)$field['LENGTH'];

                // Verifica tipi appropriati
                if (strpos($fname, 'time') !== false || strpos($fname, 'date') !== false) {
                    if ($ftype !== 'int' || $flength != '10') {
                        $issues[] = "{$fname} dovrebbe essere int(10) per timestamp";
                    }
                }

                if ($fname === 'status' && $ftype !== 'char') {
                    $issues[] = "{$fname} dovrebbe essere char per enum-like values";
                }

                if (strpos($fname, 'email') !== false && $ftype === 'char' && (int)$flength < 100) {
                    $issues[] = "{$fname} dovrebbe avere LENGTH >= 100";
                }
            }
        }

        if (!empty($issues)) {
            $this->warn(
                'DB008',
                "Field Types - {$table_name}",
                'Tipi appropriati',
                implode('; ', $issues),
                $plugin['component']
            );
        } else {
            $this->pass('DB008', "Field Types - {$table_name}", 'Tipi corretti', 'OK', $plugin['component']);
        }
    }

    /**
     * DB009: Verifica campi timestamp standard
     */
    private function check_timestamp_fields($table, $plugin) {
        $table_name = (string)$table['NAME'];
        $has_timecreated = false;
        $has_timemodified = false;

        if (isset($table->FIELDS->FIELD)) {
            foreach ($table->FIELDS->FIELD as $field) {
                $fname = (string)$field['NAME'];
                if ($fname === 'timecreated') $has_timecreated = true;
                if ($fname === 'timemodified') $has_timemodified = true;
            }
        }

        if (!$has_timecreated || !$has_timemodified) {
            $this->warn(
                'DB009',
                "Timestamp - {$table_name}",
                'timecreated e timemodified',
                'timecreated: ' . ($has_timecreated ? 'OK' : 'NO') . ', timemodified: ' . ($has_timemodified ? 'OK' : 'NO'),
                $plugin['component']
            );
        } else {
            $this->pass('DB009', "Timestamp - {$table_name}", 'Presenti', 'OK', $plugin['component']);
        }
    }

    /**
     * DB010-011: Verifica upgrade.php
     */
    private function check_upgrade_php($filepath, $plugin) {
        if (!file_exists($filepath)) {
            $this->skip('DB010', 'upgrade.php - Non presente', $plugin['component']);
            return;
        }

        $content = $this->read_file($filepath);

        // DB010: Sintassi base
        if (!preg_match('/function\s+xmldb_' . preg_quote($plugin['component'], '/') . '_upgrade\s*\(/', $content)) {
            $this->fail(
                'DB010',
                'upgrade.php - Funzione upgrade',
                'xmldb_' . $plugin['component'] . '_upgrade()',
                'Funzione non trovata o nome errato',
                $plugin['component']
            );
        } else {
            $this->pass('DB010', 'upgrade.php - Funzione upgrade', 'Presente', 'OK', $plugin['component']);
        }

        // DB011: Versioni incrementali
        preg_match_all('/if\s*\(\s*\$oldversion\s*<\s*(\d+)\s*\)/', $content, $versions);
        if (!empty($versions[1])) {
            $sorted = $versions[1];
            sort($sorted, SORT_NUMERIC);

            if ($versions[1] !== $sorted) {
                $this->fail(
                    'DB011',
                    'upgrade.php - Versioni ordinate',
                    'Versioni in ordine crescente',
                    'Ordine non corretto',
                    $plugin['component']
                );
            } else {
                $this->pass('DB011', 'upgrade.php - Versioni ordinate', 'Ordine corretto', count($versions[1]) . ' upgrade steps', $plugin['component']);
            }
        }
    }

    /**
     * DB012: Sincronizzazione con database reale
     */
    private function check_db_sync($xml, $plugin) {
        global $DB;

        $dbman = $DB->get_manager();

        if (isset($xml->TABLES->TABLE)) {
            foreach ($xml->TABLES->TABLE as $table) {
                $table_name = (string)$table['NAME'];

                if (!$dbman->table_exists($table_name)) {
                    $this->fail(
                        'DB012',
                        "Sync DB - {$table_name}",
                        'Tabella esistente nel DB',
                        'Tabella non trovata - eseguire upgrade',
                        $plugin['component']
                    );
                } else {
                    // Verifica campi
                    $xml_fields = [];
                    if (isset($table->FIELDS->FIELD)) {
                        foreach ($table->FIELDS->FIELD as $field) {
                            $xml_fields[] = (string)$field['NAME'];
                        }
                    }

                    $db_columns = $DB->get_columns($table_name);
                    $db_fields = array_keys($db_columns);

                    $missing_in_db = array_diff($xml_fields, $db_fields);
                    $extra_in_db = array_diff($db_fields, $xml_fields);

                    if (!empty($missing_in_db)) {
                        $this->fail(
                            'DB012',
                            "Sync DB - {$table_name}",
                            'Tutti i campi XML nel DB',
                            'Mancanti: ' . implode(', ', $missing_in_db),
                            $plugin['component']
                        );
                    } else if (!empty($extra_in_db)) {
                        $this->warn(
                            'DB012',
                            "Sync DB - {$table_name}",
                            'Campi DB = campi XML',
                            'Extra nel DB: ' . implode(', ', $extra_in_db),
                            $plugin['component']
                        );
                    } else {
                        $this->pass('DB012', "Sync DB - {$table_name}", 'Sincronizzato', 'OK', $plugin['component']);
                    }
                }
            }
        }
    }
}
