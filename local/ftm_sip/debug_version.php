<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo '<pre>';

// 1. Check version in DB.
$dbversion = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_ftm_sip', 'name' => 'version']);
echo "Versione nel DB: " . ($dbversion ?: 'NON TROVATA') . "\n";

// 2. Check version in file.
$plugin = new stdClass();
include(__DIR__ . '/version.php');
echo "Versione nel file: " . ($plugin->version ?? 'ERRORE LETTURA') . "\n";
echo "Release: " . ($plugin->release ?? '?') . "\n";

// 3. Check if new tables exist.
$dbman = $DB->get_manager();
$tables = ['local_ftm_sip_acceptance', 'local_ftm_sip_search_entries', 'local_ftm_sip_coach_evals', 'local_ftm_sip_search_proofs'];
echo "\nNuove tabelle CI v2:\n";
foreach ($tables as $t) {
    $exists = $dbman->table_exists($t);
    echo "  {$t}: " . ($exists ? 'ESISTE' : 'NON ESISTE') . "\n";
}

// 4. Check if new fields exist in action_plan.
$table = new xmldb_table('local_ftm_sip_action_plan');
$fields = ['week_start', 'week_end', 'area_type', 'target_global'];
echo "\nNuovi campi in action_plan:\n";
foreach ($fields as $f) {
    $field = new xmldb_field($f);
    $exists = $dbman->field_exists($table, $field);
    echo "  {$f}: " . ($exists ? 'ESISTE' : 'NON ESISTE') . "\n";
}

echo "\nUpgrade necessario: " . ($dbversion < $plugin->version ? 'SI' : 'NO (DB gia aggiornato)') . "\n";
echo '</pre>';
