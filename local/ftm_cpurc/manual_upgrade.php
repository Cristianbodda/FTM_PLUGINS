<?php
// This file is part of Moodle - http://moodle.org/
//
// Fix upgrade - adds missing columns directly.
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/manual_upgrade.php'));
$PAGE->set_title('Fix Upgrade CPURC');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<h2>Fix Upgrade - local_ftm_cpurc</h2>';

// Show current status
$dbversion = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_ftm_cpurc', 'name' => 'version']);
echo '<p><strong>Versione attuale nel DB:</strong> ' . ($dbversion ?: 'N/A') . '</p>';

$dbman = $DB->get_manager();
$table = new xmldb_table('local_ftm_cpurc_reports');
$testfield = new xmldb_field('possible_sectors');
$columnsExist = $dbman->field_exists($table, $testfield);

echo '<p><strong>Colonne nuove esistono:</strong> ' . ($columnsExist ? 'SI' : 'NO') . '</p>';

if ($action === 'fix') {
    require_sesskey();

    echo '<h3>Aggiunta colonne mancanti...</h3>';

    // Define all columns to add
    $columnsToAdd = [
        ['name' => 'possible_sectors', 'after' => 'sector_competency_text'],
        ['name' => 'final_summary', 'after' => 'possible_sectors'],
        ['name' => 'obs_personal', 'after' => 'tic_competencies'],
        ['name' => 'obs_social', 'after' => 'obs_personal'],
        ['name' => 'obs_methodological', 'after' => 'obs_social'],
        ['name' => 'obs_search_channels', 'after' => 'search_evaluation'],
        ['name' => 'obs_search_evaluation', 'after' => 'obs_search_channels'],
    ];

    $added = 0;
    $skipped = 0;
    $errors = 0;

    echo '<ul>';
    foreach ($columnsToAdd as $colDef) {
        $field = new xmldb_field($colDef['name'], XMLDB_TYPE_TEXT, null, null, null, null, null, $colDef['after']);

        if ($dbman->field_exists($table, $field)) {
            echo '<li style="color: gray;">- ' . $colDef['name'] . ' (già esistente)</li>';
            $skipped++;
        } else {
            try {
                $dbman->add_field($table, $field);
                echo '<li style="color: green;">✓ ' . $colDef['name'] . ' aggiunta</li>';
                $added++;
            } catch (Exception $e) {
                echo '<li style="color: red;">✗ ' . $colDef['name'] . ' - Errore: ' . $e->getMessage() . '</li>';
                $errors++;
            }
        }
    }
    echo '</ul>';

    echo '<p><strong>Risultato:</strong> ' . $added . ' aggiunte, ' . $skipped . ' già esistenti, ' . $errors . ' errori</p>';

    if ($errors == 0) {
        echo '<div class="alert alert-success">';
        echo '<strong>Completato!</strong> Tutte le colonne sono ora presenti nel database.';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>Attenzione:</strong> Ci sono stati degli errori.';
        echo '</div>';
    }

    echo '<p><a href="' . new moodle_url('/local/ftm_cpurc/diagnose_template.php', ['id' => 5]) . '" class="btn btn-primary">Verifica con Diagnostico</a></p>';

} else {
    // Show explanation and button

    if (!$columnsExist) {
        echo '<div class="alert alert-warning">';
        echo '<strong>Problema rilevato:</strong><br>';
        echo 'Le colonne per i nuovi campi non esistono nel database.<br>';
        echo 'Questo script le aggiungerà direttamente.';
        echo '</div>';

        echo '<h3>Colonne che verranno aggiunte:</h3>';
        echo '<ul>';
        echo '<li>possible_sectors - Possibili settori e ambiti</li>';
        echo '<li>final_summary - Sintesi conclusiva</li>';
        echo '<li>obs_personal - Osservazioni competenze personali</li>';
        echo '<li>obs_social - Osservazioni competenze sociali</li>';
        echo '<li>obs_methodological - Osservazioni competenze metodologiche</li>';
        echo '<li>obs_search_channels - Osservazioni canali ricerca</li>';
        echo '<li>obs_search_evaluation - Osservazioni valutazione ricerca</li>';
        echo '</ul>';

        $fixurl = new moodle_url('/local/ftm_cpurc/manual_upgrade.php', [
            'action' => 'fix',
            'sesskey' => sesskey()
        ]);

        echo '<p><a href="' . $fixurl . '" class="btn btn-danger btn-lg">Aggiungi Colonne Mancanti</a></p>';

    } else {
        echo '<div class="alert alert-success">';
        echo '<strong>Tutto OK!</strong> Le colonne esistono già nel database.';
        echo '</div>';

        echo '<p><a href="' . new moodle_url('/local/ftm_cpurc/diagnose_template.php', ['id' => 5]) . '" class="btn btn-primary">Vai al Diagnostico</a></p>';
    }
}

echo $OUTPUT->footer();
