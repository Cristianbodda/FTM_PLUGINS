<?php
/**
 * Script per correggere i campi idnumber mancanti nelle question_bank_entries
 *
 * Questo script popola il campo idnumber basandosi sulla competenza assegnata
 * nella tabella qbank_competenciesbyquestion.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/fix_missing_idnumbers.php'));
$PAGE->set_title('Fix Missing idnumbers');

// Framework da usare
$frameworkid = optional_param('frameworkid', 11, PARAM_INT);
$execute = optional_param('execute', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<h2>Fix Missing idnumbers nelle Question Bank Entries</h2>';

// Ottieni tutte le competenze del framework
$competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber');
$comp_map = [];
foreach ($competencies as $c) {
    $comp_map[$c->id] = $c->idnumber;
}

echo '<p>Framework ID: <strong>' . $frameworkid . '</strong> (' . count($competencies) . ' competenze)</p>';

// Trova tutte le domande con competenza assegnata ma senza idnumber
$sql = "SELECT qbe.id as qbe_id, qbe.idnumber as current_idnumber,
               q.id as question_id, q.name,
               qbc.competencyid
        FROM {question_bank_entries} qbe
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q ON q.id = qv.questionid
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
        WHERE (qbe.idnumber IS NULL OR qbe.idnumber = '')
        ORDER BY q.name";

$questions = $DB->get_records_sql($sql);

echo '<p>Domande con competenza ma senza idnumber: <strong>' . count($questions) . '</strong></p>';

if (empty($questions)) {
    echo '<div class="alert alert-success">Nessuna domanda da correggere! Tutte hanno già l\'idnumber.</div>';
    echo $OUTPUT->footer();
    exit;
}

if ($execute) {
    echo '<h3>Esecuzione correzione...</h3>';
    echo '<pre>';

    $fixed = 0;
    $errors = 0;

    foreach ($questions as $q) {
        if (isset($comp_map[$q->competencyid])) {
            $new_idnumber = $comp_map[$q->competencyid];

            try {
                $DB->set_field('question_bank_entries', 'idnumber', $new_idnumber, ['id' => $q->qbe_id]);
                echo "✅ QBE #{$q->qbe_id}: {$new_idnumber} (Q: " . substr($q->name, 0, 50) . "...)\n";
                $fixed++;
            } catch (Exception $e) {
                echo "❌ QBE #{$q->qbe_id}: ERRORE - " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            echo "⚠️ QBE #{$q->qbe_id}: Competenza #{$q->competencyid} non trovata nel framework\n";
            $errors++;
        }
    }

    echo '</pre>';

    echo '<div class="alert alert-info">';
    echo '<strong>Risultato:</strong><br>';
    echo "✅ Corrette: {$fixed}<br>";
    echo "❌ Errori: {$errors}";
    echo '</div>';

    echo '<p><a href="' . $CFG->wwwroot . '/local/ftm_testsuite/" class="btn btn-primary">Vai alla Test Suite</a></p>';

} else {
    echo '<h3>Anteprima (prime 50 domande)</h3>';
    echo '<table class="table table-striped">';
    echo '<tr><th>QBE ID</th><th>Question</th><th>Competenza</th><th>Nuovo idnumber</th></tr>';

    $count = 0;
    foreach ($questions as $q) {
        if ($count >= 50) {
            echo '<tr><td colspan="4">... e altre ' . (count($questions) - 50) . ' domande</td></tr>';
            break;
        }

        $new_idnumber = isset($comp_map[$q->competencyid]) ? $comp_map[$q->competencyid] : '⚠️ NON TROVATA';

        echo '<tr>';
        echo '<td>' . $q->qbe_id . '</td>';
        echo '<td>' . htmlspecialchars(substr($q->name, 0, 60)) . '...</td>';
        echo '<td>#' . $q->competencyid . '</td>';
        echo '<td><code>' . $new_idnumber . '</code></td>';
        echo '</tr>';

        $count++;
    }

    echo '</table>';

    echo '<form method="post">';
    echo '<input type="hidden" name="frameworkid" value="' . $frameworkid . '">';
    echo '<input type="hidden" name="execute" value="1">';
    echo '<button type="submit" class="btn btn-danger" onclick="return confirm(\'Sei sicuro di voler correggere ' . count($questions) . ' domande?\');">';
    echo '🔧 Esegui Correzione (' . count($questions) . ' domande)';
    echo '</button>';
    echo '</form>';
}

echo $OUTPUT->footer();
