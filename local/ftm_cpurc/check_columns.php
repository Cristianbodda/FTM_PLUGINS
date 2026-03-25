<?php
/**
 * Quick diagnostic - check if DB columns exist.
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: text/plain; charset=utf-8');

$dbman = $DB->get_manager();
$table = new xmldb_table('local_ftm_cpurc_reports');

$columns = [
    'reinsertion_assessment',
    'hired_profession',
    'hired_contract',
    'hired_details',
    'hired_company',
    'sip_consent',
    'allegati',
];

echo "=== CPURC REPORTS COLUMN CHECK ===\n\n";

foreach ($columns as $col) {
    $field = new xmldb_field($col);
    $exists = $dbman->field_exists($table, $field);
    echo ($exists ? "OK" : "MISSING") . ": $col\n";
}

$version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_ftm_cpurc',
    'name' => 'version'
]);
echo "\nPlugin version in DB: $version\n";
echo "Expected version: 2026032501\n";

if ($version < 2026032501) {
    echo "\n>>> UPGRADE NEEDED! Go to /admin/index.php <<<\n";
}
