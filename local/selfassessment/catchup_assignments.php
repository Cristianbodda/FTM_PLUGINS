<?php
/**
 * Catch-up Script - Crea assegnazioni retroattive per quiz già completati
 *
 * Questo script processa tutti i quiz_attempts completati e crea le assegnazioni
 * di autovalutazione mancanti, simulando ciò che l'observer avrebbe fatto.
 *
 * @package    local_selfassessment
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/catchup_assignments.php'));
$PAGE->set_title('Catch-up Assegnazioni Autovalutazione');
$PAGE->set_heading('Catch-up Assegnazioni Autovalutazione');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

// Trova la tabella competenze-domande
$comp_table = null;
$comp_tables = [
    'qbank_competenciesbyquestion' => 'questionid',
    'qbank_comp_question' => 'questionid',
    'local_competencymanager_qcomp' => 'questionid'
];

foreach ($comp_tables as $table => $field) {
    if ($DB->get_manager()->table_exists($table)) {
        $comp_table = $table;
        $comp_field = $field;
        break;
    }
}

if (!$comp_table) {
    echo $OUTPUT->notification('Nessuna tabella di mapping competenze-domande trovata!', 'error');
    echo $OUTPUT->footer();
    die();
}

// Verifica tabella assegnazioni
if (!$DB->get_manager()->table_exists('local_selfassessment_assign')) {
    echo $OUTPUT->notification('Tabella local_selfassessment_assign non esiste!', 'error');
    echo $OUTPUT->footer();
    die();
}

?>
<style>
.catchup-container { max-width: 900px; margin: 0 auto; }
.catchup-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.stat-card .number { font-size: 32px; font-weight: 700; color: #1e3c72; }
.stat-card .label { font-size: 14px; color: #666; margin-top: 5px; }
.stat-card.success .number { color: #28a745; }
.stat-card.warning .number { color: #ffc107; }
.stat-card.info .number { color: #17a2b8; }
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    margin: 5px;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.result-box {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.progress-log {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    max-height: 400px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 13px;
}
.log-success { color: #28a745; }
.log-info { color: #17a2b8; }
.log-warning { color: #ffc107; }
.log-error { color: #dc3545; }
</style>

<div class="catchup-container">
    <div class="catchup-header">
        <h1>🔄 Catch-up Assegnazioni Autovalutazione</h1>
        <p>Crea retroattivamente le assegnazioni per i quiz completati prima dell'attivazione dell'observer</p>
    </div>

<?php

if ($action === 'run' && $confirm && confirm_sesskey()) {
    // ========== ESEGUI CATCH-UP ==========

    echo '<div class="result-box">';
    echo '<h3>📋 Esecuzione Catch-up</h3>';
    echo '<div class="progress-log" id="progressLog">';

    $start_time = microtime(true);
    $stats = [
        'attempts_processed' => 0,
        'users_processed' => 0,
        'assignments_created' => 0,
        'assignments_skipped' => 0,
        'errors' => 0
    ];

    // Trova tutti i quiz attempts completati
    $attempts = $DB->get_records_sql("
        SELECT qa.id, qa.userid, qa.quiz, qa.uniqueid, q.name as quizname, u.username
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {user} u ON u.id = qa.userid
        WHERE qa.state = 'finished'
        ORDER BY qa.userid, qa.timefinish
    ");

    $total_attempts = count($attempts);
    echo "<div class='log-info'>Trovati {$total_attempts} quiz attempts completati</div>";

    $processed_users = [];
    $now = time();

    foreach ($attempts as $attempt) {
        $stats['attempts_processed']++;

        // Ottieni le domande dal quiz attempt
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);

        if (empty($questions)) {
            continue;
        }

        $questionids = array_keys($questions);

        // Trova competenze associate alle domande
        list($sql_in, $params) = $DB->get_in_or_equal($questionids);
        $mappings = $DB->get_records_sql("
            SELECT DISTINCT competencyid
            FROM {{$comp_table}}
            WHERE {$comp_field} $sql_in
        ", $params);

        if (empty($mappings)) {
            continue;
        }

        $user_created = 0;

        foreach ($mappings as $mapping) {
            $competencyid = $mapping->competencyid;

            // Verifica se già assegnata
            $exists = $DB->record_exists('local_selfassessment_assign', [
                'userid' => $attempt->userid,
                'competencyid' => $competencyid
            ]);

            if (!$exists) {
                $record = new stdClass();
                $record->userid = $attempt->userid;
                $record->competencyid = $competencyid;
                $record->source = 'quiz';
                $record->sourceid = $attempt->quiz;
                $record->timecreated = $now;

                try {
                    $DB->insert_record('local_selfassessment_assign', $record);
                    $stats['assignments_created']++;
                    $user_created++;
                } catch (Exception $e) {
                    $stats['errors']++;
                }
            } else {
                $stats['assignments_skipped']++;
            }
        }

        if (!isset($processed_users[$attempt->userid])) {
            $processed_users[$attempt->userid] = true;
            $stats['users_processed']++;
        }

        // Log ogni 10 attempts o se ci sono nuove assegnazioni
        if ($stats['attempts_processed'] % 10 == 0 || $user_created > 0) {
            $pct = round(($stats['attempts_processed'] / $total_attempts) * 100);
            if ($user_created > 0) {
                echo "<div class='log-success'>✓ {$attempt->username} - Quiz \"{$attempt->quizname}\": +{$user_created} assegnazioni</div>";
            }
            flush();
        }
    }

    $elapsed = round(microtime(true) - $start_time, 2);

    echo "<div class='log-info'>----------------------------------------</div>";
    echo "<div class='log-success'>✅ Completato in {$elapsed} secondi</div>";
    echo '</div>'; // progress-log

    echo '<div class="stats-grid">';
    echo '<div class="stat-card info"><div class="number">' . $stats['attempts_processed'] . '</div><div class="label">Attempts Processati</div></div>';
    echo '<div class="stat-card info"><div class="number">' . $stats['users_processed'] . '</div><div class="label">Utenti Processati</div></div>';
    echo '<div class="stat-card success"><div class="number">' . $stats['assignments_created'] . '</div><div class="label">Assegnazioni Create</div></div>';
    echo '<div class="stat-card warning"><div class="number">' . $stats['assignments_skipped'] . '</div><div class="label">Già Esistenti (skip)</div></div>';
    echo '</div>';

    if ($stats['errors'] > 0) {
        echo $OUTPUT->notification("Errori durante l'inserimento: {$stats['errors']}", 'warning');
    }

    echo '</div>'; // result-box

    echo '<div style="text-align: center; margin-top: 20px;">';
    echo '<a href="' . new moodle_url('/local/ftm_testsuite/run.php') . '" class="btn btn-primary">🧪 Riesegui Test Suite</a>';
    echo '<a href="' . $PAGE->url . '" class="btn btn-secondary">🔄 Torna alla Pagina</a>';
    echo '</div>';

} else {
    // ========== MOSTRA ANTEPRIMA ==========

    // Statistiche attuali
    $total_attempts = $DB->count_records_sql("
        SELECT COUNT(DISTINCT userid) FROM {quiz_attempts} WHERE state = 'finished'
    ");

    $users_with_assign = $DB->count_records_sql("
        SELECT COUNT(DISTINCT userid) FROM {local_selfassessment_assign} WHERE source = 'quiz'
    ");

    $total_assignments = $DB->count_records('local_selfassessment_assign');
    $quiz_assignments = $DB->count_records('local_selfassessment_assign', ['source' => 'quiz']);

    // Stima assegnazioni mancanti
    $missing_estimate = $DB->get_record_sql("
        SELECT COUNT(*) as count
        FROM (
            SELECT DISTINCT qa.userid, qc.competencyid
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
            JOIN {{$comp_table}} qc ON qc.{$comp_field} = qat.questionid
            WHERE qa.state = 'finished'
        ) AS potential
        LEFT JOIN {local_selfassessment_assign} saa
            ON saa.userid = potential.userid AND saa.competencyid = potential.competencyid
        WHERE saa.id IS NULL
    ");

    $missing_count = $missing_estimate->count ?? 0;
    $coverage = $total_attempts > 0 ? round(($users_with_assign / $total_attempts) * 100, 1) : 0;

    echo '<div class="result-box">';
    echo '<h3>📊 Situazione Attuale</h3>';

    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number">' . $total_attempts . '</div><div class="label">Utenti con Quiz Completati</div></div>';
    echo '<div class="stat-card ' . ($coverage >= 80 ? 'success' : 'warning') . '"><div class="number">' . $users_with_assign . '</div><div class="label">Utenti con Assegnazioni Quiz</div></div>';
    echo '<div class="stat-card"><div class="number">' . $coverage . '%</div><div class="label">Copertura Observer</div></div>';
    echo '<div class="stat-card warning"><div class="number">' . $missing_count . '</div><div class="label">Assegnazioni Mancanti (stima)</div></div>';
    echo '</div>';

    echo '<p style="margin-top: 15px; color: #666;">';
    echo '<strong>Tabella mapping:</strong> ' . $comp_table . '<br>';
    echo '<strong>Assegnazioni totali:</strong> ' . $total_assignments . ' (di cui ' . $quiz_assignments . ' da quiz)';
    echo '</p>';
    echo '</div>';

    if ($missing_count > 0) {
        echo '<div class="result-box" style="border-left: 4px solid #ffc107;">';
        echo '<h3>⚠️ Azione Richiesta</h3>';
        echo '<p>Ci sono <strong>' . $missing_count . '</strong> assegnazioni mancanti che possono essere create retroattivamente.</p>';
        echo '<p>Questo script processerà tutti i quiz completati e creerà le assegnazioni di autovalutazione per le competenze associate.</p>';

        echo '<form method="post" style="margin-top: 20px;">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="run">';
        echo '<input type="hidden" name="confirm" value="1">';
        echo '<button type="submit" class="btn btn-success">▶️ Esegui Catch-up</button>';
        echo '<a href="' . new moodle_url('/local/ftm_testsuite/index.php') . '" class="btn btn-secondary">Annulla</a>';
        echo '</form>';
        echo '</div>';
    } else {
        echo '<div class="result-box" style="border-left: 4px solid #28a745;">';
        echo '<h3>✅ Nessuna Azione Richiesta</h3>';
        echo '<p>Tutte le assegnazioni sono già presenti. L\'observer sta funzionando correttamente.</p>';
        echo '</div>';
    }
}

?>

    <div style="text-align: center; margin-top: 25px;">
        <a href="<?php echo new moodle_url('/local/selfassessment/index.php'); ?>" class="btn btn-secondary">← Autovalutazioni</a>
        <a href="<?php echo new moodle_url('/local/ftm_testsuite/index.php'); ?>" class="btn btn-secondary">🧪 Test Suite</a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
