<?php
/**
 * Script per eliminare le valutazioni coach senza ratings
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/cleanup_empty_evaluations.php'));
$PAGE->set_title('Pulizia Valutazioni Vuote');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();
echo '<h2>🧹 Pulizia Valutazioni Coach Vuote</h2>';

// Trova valutazioni senza ratings
$empty_evals = $DB->get_records_sql(
    "SELECT e.id, e.studentid, e.coachid, e.sector, e.status, e.timecreated,
            u.firstname, u.lastname,
            c.firstname as coach_firstname, c.lastname as coach_lastname
     FROM {local_coach_evaluations} e
     LEFT JOIN {local_coach_eval_ratings} r ON r.evaluationid = e.id
     LEFT JOIN {user} u ON u.id = e.studentid
     LEFT JOIN {user} c ON c.id = e.coachid
     WHERE r.id IS NULL
     ORDER BY e.timecreated DESC"
);

$total_empty = count($empty_evals);

// Conta valutazioni totali
$total_evals = $DB->count_records('local_coach_evaluations');
$total_with_ratings = $total_evals - $total_empty;

echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h4>📊 Statistiche Valutazioni</h4>';
echo '<table border="0" cellpadding="8">';
echo '<tr><td><strong>Valutazioni totali:</strong></td><td>' . $total_evals . '</td></tr>';
echo '<tr><td><strong>Con ratings:</strong></td><td style="color: green;">' . $total_with_ratings . '</td></tr>';
echo '<tr><td><strong>Vuote (senza ratings):</strong></td><td style="color: red;">' . $total_empty . '</td></tr>';
echo '</table>';
echo '</div>';

if ($total_empty === 0) {
    echo '<div class="alert alert-success">✅ Non ci sono valutazioni vuote da eliminare!</div>';
    echo $OUTPUT->footer();
    die();
}

// Azione: elimina
if ($action === 'delete' && $confirm === 1 && confirm_sesskey()) {
    $deleted = 0;

    foreach ($empty_evals as $eval) {
        $DB->delete_records('local_coach_evaluations', ['id' => $eval->id]);
        $deleted++;
    }

    echo '<div class="alert alert-success">';
    echo '<h4>✅ Pulizia Completata</h4>';
    echo '<p><strong>' . $deleted . '</strong> valutazioni vuote eliminate con successo.</p>';
    echo '</div>';

    echo '<p><a href="' . new moodle_url('/local/competencymanager/cleanup_empty_evaluations.php') . '" class="btn btn-secondary">Verifica Risultato</a></p>';

    echo $OUTPUT->footer();
    die();
}

// Mostra elenco valutazioni vuote
echo '<h4>📋 Valutazioni Vuote da Eliminare (' . $total_empty . ')</h4>';

echo '<table class="table table-striped table-bordered" style="font-size: 12px;">';
echo '<thead class="thead-dark">';
echo '<tr>';
echo '<th>ID</th>';
echo '<th>Studente</th>';
echo '<th>Settore</th>';
echo '<th>Coach</th>';
echo '<th>Status</th>';
echo '<th>Data Creazione</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($empty_evals as $eval) {
    $student_name = $eval->firstname . ' ' . $eval->lastname;
    $coach_name = $eval->coach_firstname . ' ' . $eval->coach_lastname;
    $date = date('d/m/Y H:i', $eval->timecreated);

    $status_badge = '';
    switch ($eval->status) {
        case 'draft':
            $status_badge = '<span class="badge badge-secondary">📝 Draft</span>';
            break;
        case 'completed':
            $status_badge = '<span class="badge badge-success">✅ Completed</span>';
            break;
        case 'signed':
            $status_badge = '<span class="badge badge-primary">🔒 Signed</span>';
            break;
        default:
            $status_badge = '<span class="badge badge-light">' . $eval->status . '</span>';
    }

    echo '<tr>';
    echo '<td>' . $eval->id . '</td>';
    echo '<td>' . htmlspecialchars($student_name) . '</td>';
    echo '<td><strong>' . htmlspecialchars($eval->sector) . '</strong></td>';
    echo '<td>' . htmlspecialchars($coach_name) . '</td>';
    echo '<td>' . $status_badge . '</td>';
    echo '<td>' . $date . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Form di conferma
echo '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px;">';
echo '<h4>⚠️ Conferma Eliminazione</h4>';
echo '<p>Stai per eliminare <strong>' . $total_empty . '</strong> valutazioni vuote.</p>';
echo '<p>Questa operazione <strong>non può essere annullata</strong>.</p>';
echo '<p>Le valutazioni con ratings (<strong>' . $total_with_ratings . '</strong>) NON verranno toccate.</p>';

$delete_url = new moodle_url('/local/competencymanager/cleanup_empty_evaluations.php', [
    'action' => 'delete',
    'confirm' => 1,
    'sesskey' => sesskey()
]);

echo '<form method="post" action="' . $delete_url . '" style="margin-top: 15px;">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<button type="submit" class="btn btn-danger btn-lg">🗑️ Elimina ' . $total_empty . ' Valutazioni Vuote</button>';
echo ' <a href="' . new moodle_url('/local/ftm_testsuite/agent_tests.php') . '" class="btn btn-secondary">Annulla</a>';
echo '</form>';
echo '</div>';

echo $OUTPUT->footer();
