<?php
/**
 * Script per migrare le competenze dal Framework 1/9 al Framework 11
 *
 * Questo script aggiorna i riferimenti in qbank_competenciesbyquestion
 * per puntare alle competenze del framework attuale (11) invece dei vecchi (1, 9).
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/migrate_competencies_to_fw11.php'));
$PAGE->set_title('Migrazione Competenze a Framework 11');

$execute = optional_param('execute', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<h2>Migrazione Competenze a Framework 11</h2>';

// Framework target
$target_fw = 11;
$old_frameworks = [1, 9];

echo '<div class="alert alert-info">';
echo '<strong>Obiettivo:</strong> Migrare i riferimenti delle competenze dai Framework ';
echo implode(', ', $old_frameworks) . ' al Framework ' . $target_fw . ' (Passaporto tecnico FTM).';
echo '</div>';

// 1. Costruisci mappa: idnumber -> competency_id per FW 11
echo '<h3>1. Caricamento competenze Framework 11</h3>';

$fw11_competencies = $DB->get_records('competency', ['competencyframeworkid' => $target_fw], '', 'id, idnumber');
$fw11_map = []; // idnumber -> id
foreach ($fw11_competencies as $c) {
    if (!empty($c->idnumber)) {
        $fw11_map[$c->idnumber] = $c->id;
    }
}
echo '<p>Competenze nel Framework 11: <strong>' . count($fw11_map) . '</strong></p>';

// 2. Trova tutte le assegnazioni che usano FW vecchi
echo '<h3>2. Analisi assegnazioni da migrare</h3>';

$placeholders = implode(',', $old_frameworks);
$sql = "
    SELECT qbc.id as qbc_id, qbc.questionid, qbc.competencyid,
           c.idnumber as old_idnumber, c.competencyframeworkid as old_fw
    FROM {qbank_competenciesbyquestion} qbc
    JOIN {competency} c ON c.id = qbc.competencyid
    WHERE c.competencyframeworkid IN ({$placeholders})
    ORDER BY c.idnumber
";

$to_migrate = $DB->get_records_sql($sql);

echo '<p>Assegnazioni da migrare: <strong>' . count($to_migrate) . '</strong></p>';

if (empty($to_migrate)) {
    echo '<div class="alert alert-success">Nessuna assegnazione da migrare! Tutte usano già il Framework 11.</div>';
    echo $OUTPUT->footer();
    exit;
}

// 3. Analisi preliminare
$can_migrate = [];
$cannot_migrate = [];
$by_sector = [];

foreach ($to_migrate as $m) {
    $idnumber = $m->old_idnumber;

    // Estrai settore dall'idnumber (prima parte prima di _)
    $parts = explode('_', $idnumber);
    $sector = $parts[0] ?? 'UNKNOWN';

    if (!isset($by_sector[$sector])) {
        $by_sector[$sector] = ['can' => 0, 'cannot' => 0];
    }

    if (isset($fw11_map[$idnumber])) {
        $can_migrate[] = [
            'qbc_id' => $m->qbc_id,
            'questionid' => $m->questionid,
            'old_competencyid' => $m->competencyid,
            'new_competencyid' => $fw11_map[$idnumber],
            'idnumber' => $idnumber,
            'old_fw' => $m->old_fw
        ];
        $by_sector[$sector]['can']++;
    } else {
        $cannot_migrate[] = [
            'qbc_id' => $m->qbc_id,
            'idnumber' => $idnumber,
            'old_fw' => $m->old_fw
        ];
        $by_sector[$sector]['cannot']++;
    }
}

// Mostra riepilogo per settore
echo '<h4>Riepilogo per Settore</h4>';
echo '<table class="table table-bordered">';
echo '<tr><th>Settore</th><th>Migrabili</th><th>Non trovate in FW11</th></tr>';
ksort($by_sector);
foreach ($by_sector as $sector => $counts) {
    $class = $counts['cannot'] > 0 ? 'table-warning' : 'table-success';
    echo "<tr class='{$class}'><td>{$sector}</td><td>{$counts['can']}</td><td>{$counts['cannot']}</td></tr>";
}
echo '</table>';

// Riepilogo totale
echo '<h4>Riepilogo Totale</h4>';
echo '<table class="table table-bordered">';
echo '<tr><th>Status</th><th>Conteggio</th></tr>';
echo '<tr class="table-success"><td>Migrabili</td><td><strong>' . count($can_migrate) . '</strong></td></tr>';
echo '<tr class="table-danger"><td>Non migrabili (competenza non in FW11)</td><td>' . count($cannot_migrate) . '</td></tr>';
echo '<tr><td><strong>Totale</strong></td><td>' . count($to_migrate) . '</td></tr>';
echo '</table>';

// Se ci sono competenze non migrabili, mostra dettagli
if (!empty($cannot_migrate)) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Competenze non trovate in FW11:</strong><br>';
    $unique_missing = [];
    foreach ($cannot_migrate as $m) {
        $unique_missing[$m['idnumber']] = $m['old_fw'];
    }
    foreach ($unique_missing as $idnumber => $fw) {
        echo "<code>{$idnumber}</code> (FW{$fw})<br>";
    }
    echo '</div>';
}

// 4. Esecuzione o anteprima
if ($execute) {
    echo '<h3>3. Esecuzione Migrazione</h3>';
    echo '<pre style="max-height: 400px; overflow-y: auto; font-size: 11px; background: #f5f5f5; padding: 10px;">';

    $migrated = 0;
    $errors = 0;
    $duplicates = 0;

    foreach ($can_migrate as $m) {
        // Verifica se esiste già un'assegnazione per questa domanda con la nuova competenza
        $existing = $DB->get_record('qbank_competenciesbyquestion', [
            'questionid' => $m['questionid'],
            'competencyid' => $m['new_competencyid']
        ]);

        if ($existing) {
            // Già esiste, elimina il record vecchio
            $DB->delete_records('qbank_competenciesbyquestion', ['id' => $m['qbc_id']]);
            echo "Duplicato rimosso: QBC#{$m['qbc_id']} ({$m['idnumber']}) - già presente\n";
            $duplicates++;
        } else {
            // Aggiorna il competencyid
            try {
                $DB->set_field('qbank_competenciesbyquestion', 'competencyid', $m['new_competencyid'], ['id' => $m['qbc_id']]);
                echo "Migrato: QBC#{$m['qbc_id']} {$m['idnumber']} (FW{$m['old_fw']} -> FW{$target_fw})\n";
                $migrated++;
            } catch (Exception $e) {
                echo "ERRORE QBC#{$m['qbc_id']}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    echo '</pre>';

    echo '<div class="alert alert-success">';
    echo '<strong>Migrazione completata!</strong><br>';
    echo "Migrati: {$migrated}<br>";
    echo "Duplicati rimossi: {$duplicates}<br>";
    echo "Errori: {$errors}";
    echo '</div>';

    // Link alla test suite
    echo '<div class="mt-3">';
    echo '<a href="' . $CFG->wwwroot . '/local/ftm_testsuite/agent_tests.php" class="btn btn-primary btn-lg">';
    echo 'Verifica con Test Suite</a> ';
    echo '<a href="diagnose_competencies.php" class="btn btn-secondary">';
    echo 'Esegui Nuova Diagnosi</a>';
    echo '</div>';

} else {
    // Anteprima
    echo '<h3>3. Anteprima (prime 30 migrazioni)</h3>';
    echo '<table class="table table-sm">';
    echo '<tr><th>QBC ID</th><th>IDNumber</th><th>Da FW</th><th>Comp ID Vecchio</th><th>Comp ID Nuovo</th></tr>';

    $preview = array_slice($can_migrate, 0, 30);
    foreach ($preview as $m) {
        echo '<tr>';
        echo "<td>{$m['qbc_id']}</td>";
        echo "<td><code>{$m['idnumber']}</code></td>";
        echo "<td>{$m['old_fw']}</td>";
        echo "<td>{$m['old_competencyid']}</td>";
        echo "<td>{$m['new_competencyid']}</td>";
        echo '</tr>';
    }

    if (count($can_migrate) > 30) {
        echo '<tr><td colspan="5">... e altre ' . (count($can_migrate) - 30) . ' migrazioni</td></tr>';
    }
    echo '</table>';

    // Bottone esecuzione
    if (count($can_migrate) > 0) {
        echo '<form method="post" class="mt-3">';
        echo '<input type="hidden" name="execute" value="1">';
        echo '<button type="submit" class="btn btn-success btn-lg" onclick="return confirm(\'Confermi la migrazione di ' . count($can_migrate) . ' assegnazioni?\');">';
        echo 'Esegui Migrazione (' . count($can_migrate) . ' assegnazioni)';
        echo '</button>';
        echo '</form>';
    }
}

echo $OUTPUT->footer();
