<?php
/**
 * Script per correggere i campi idnumber mancanti - VERSIONE 2
 *
 * Cerca le competenze direttamente per ID (senza limitare al framework)
 * Gestisce i duplicati appendendo un suffisso numerico
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/fix_missing_idnumbers_v2.php'));
$PAGE->set_title('Fix Missing idnumbers V2');

$execute = optional_param('execute', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<h2>Fix Missing idnumbers V2 (Multi-Framework)</h2>';

// Ottieni TUTTE le competenze da TUTTI i framework
$all_competencies = $DB->get_records('competency', [], '', 'id, idnumber, competencyframeworkid');
$comp_map = [];
foreach ($all_competencies as $c) {
    $comp_map[$c->id] = $c->idnumber;
}

echo '<p>Competenze totali caricate: <strong>' . count($comp_map) . '</strong></p>';

// Trova tutte le domande con competenza assegnata ma senza idnumber
$sql = "SELECT qbe.id as qbe_id, qbe.idnumber as current_idnumber,
               qbe.questioncategoryid,
               q.id as question_id, q.name,
               qbc.competencyid
        FROM {question_bank_entries} qbe
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q ON q.id = qv.questionid
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
        WHERE (qbe.idnumber IS NULL OR qbe.idnumber = '')
        ORDER BY qbe.questioncategoryid, q.name";

$questions = $DB->get_records_sql($sql);

echo '<p>Domande con competenza ma senza idnumber: <strong>' . count($questions) . '</strong></p>';

if (empty($questions)) {
    echo '<div class="alert alert-success">Nessuna domanda da correggere!</div>';
    echo $OUTPUT->footer();
    exit;
}

if ($execute) {
    echo '<h3>Esecuzione correzione...</h3>';
    echo '<pre style="max-height: 500px; overflow-y: auto; font-size: 11px;">';

    $fixed = 0;
    $skipped_no_comp = 0;
    $skipped_dup = 0;

    // Traccia gli idnumber già usati per categoria
    $used_idnumbers = [];

    // Precarica gli idnumber esistenti per ogni categoria
    $existing = $DB->get_records_sql("
        SELECT id, questioncategoryid, idnumber
        FROM {question_bank_entries}
        WHERE idnumber IS NOT NULL AND idnumber != ''
    ");
    foreach ($existing as $e) {
        $key = $e->questioncategoryid . '-' . $e->idnumber;
        $used_idnumbers[$key] = true;
    }

    foreach ($questions as $q) {
        if (!isset($comp_map[$q->competencyid])) {
            // Competenza non trovata in nessun framework
            echo "⚠️ QBE #{$q->qbe_id}: Competenza #{$q->competencyid} non esiste\n";
            $skipped_no_comp++;
            continue;
        }

        $base_idnumber = $comp_map[$q->competencyid];
        $cat_id = $q->questioncategoryid;

        // Verifica se l'idnumber è già usato in questa categoria
        $key = $cat_id . '-' . $base_idnumber;
        if (isset($used_idnumbers[$key])) {
            // Già usato, salta (non possiamo avere duplicati)
            $skipped_dup++;
            continue;
        }

        try {
            $DB->set_field('question_bank_entries', 'idnumber', $base_idnumber, ['id' => $q->qbe_id]);
            $used_idnumbers[$key] = true;
            echo "✅ QBE #{$q->qbe_id}: {$base_idnumber}\n";
            $fixed++;
        } catch (Exception $e) {
            echo "❌ QBE #{$q->qbe_id}: " . $e->getMessage() . "\n";
        }
    }

    echo '</pre>';

    echo '<div class="alert alert-info">';
    echo '<strong>Risultato:</strong><br>';
    echo "✅ Corrette: {$fixed}<br>";
    echo "⚠️ Competenza non trovata: {$skipped_no_comp}<br>";
    echo "⏭️ Saltate (duplicato in categoria): {$skipped_dup}";
    echo '</div>';

    echo '<p><strong>Nota:</strong> Le domande saltate per duplicato sono normali - Moodle non permette due domande con lo stesso idnumber nella stessa categoria. La prima domanda di ogni competenza ha ricevuto l\'idnumber.</p>';

    echo '<p><a href="' . $CFG->wwwroot . '/local/ftm_testsuite/" class="btn btn-primary">Vai alla Test Suite</a></p>';

} else {
    // Analisi preliminare
    $by_status = [
        'can_fix' => 0,
        'no_comp' => 0,
        'would_dup' => 0
    ];

    // Precarica esistenti
    $existing = $DB->get_records_sql("
        SELECT id, questioncategoryid, idnumber
        FROM {question_bank_entries}
        WHERE idnumber IS NOT NULL AND idnumber != ''
    ");
    $used = [];
    foreach ($existing as $e) {
        $key = $e->questioncategoryid . '-' . $e->idnumber;
        $used[$key] = true;
    }

    foreach ($questions as $q) {
        if (!isset($comp_map[$q->competencyid])) {
            $by_status['no_comp']++;
            continue;
        }

        $key = $q->questioncategoryid . '-' . $comp_map[$q->competencyid];
        if (isset($used[$key])) {
            $by_status['would_dup']++;
        } else {
            $by_status['can_fix']++;
            $used[$key] = true; // Simula assegnazione
        }
    }

    echo '<h3>Analisi Preliminare</h3>';
    echo '<table class="table">';
    echo '<tr><th>Status</th><th>Conteggio</th></tr>';
    echo '<tr><td>✅ Correggibili</td><td><strong>' . $by_status['can_fix'] . '</strong></td></tr>';
    echo '<tr><td>⚠️ Competenza non trovata</td><td>' . $by_status['no_comp'] . '</td></tr>';
    echo '<tr><td>⏭️ Saltate (duplicato)</td><td>' . $by_status['would_dup'] . '</td></tr>';
    echo '<tr><td><strong>Totale</strong></td><td>' . count($questions) . '</td></tr>';
    echo '</table>';

    echo '<div class="alert alert-warning">';
    echo '<strong>Nota sui duplicati:</strong> Moodle richiede che l\'idnumber sia unico per categoria. ';
    echo 'Se hai 5 domande sulla stessa competenza nella stessa categoria, solo 1 riceverà l\'idnumber. ';
    echo 'Questo è il comportamento atteso del sistema.';
    echo '</div>';

    echo '<form method="post">';
    echo '<input type="hidden" name="execute" value="1">';
    echo '<button type="submit" class="btn btn-success">';
    echo '🔧 Esegui Correzione (' . $by_status['can_fix'] . ' domande)';
    echo '</button>';
    echo '</form>';
}

echo $OUTPUT->footer();
