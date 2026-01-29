<?php
// ============================================
// DIAGNOSI CRITEST - Verifica stato completo
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/diagnose_critest.php'));
$PAGE->set_title('Diagnosi Critest');

echo $OUTPUT->header();
echo '<h1>Diagnosi Selfassessment - Critest</h1>';

// Trova utente critest
$critest = $DB->get_record('user', ['username' => 'critest']);
if (!$critest) {
    echo '<p style="color: red;">Utente critest non trovato!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo "<h2>1. Utente Critest</h2>";
echo "<p>ID: {$critest->id} | Username: {$critest->username} | Email: {$critest->email}</p>";

// Verifica capabilities
echo "<h2>2. Capabilities</h2>";
$context = context_system::instance();
$can_complete = has_capability('local/selfassessment:complete', $context, $critest->id);
$can_view = has_capability('local/selfassessment:view', $context, $critest->id);
echo "<p>local/selfassessment:complete: " . ($can_complete ? '✅ SI' : '❌ NO') . "</p>";
echo "<p>local/selfassessment:view (coach): " . ($can_view ? '⚠️ SI (è coach, reminder non mostrati)' : '✅ NO (studente)') . "</p>";

// Verifica status
echo "<h2>3. Status Autovalutazione</h2>";
$status = $DB->get_record('local_selfassessment_status', ['userid' => $critest->id]);
if ($status) {
    echo "<pre>" . print_r($status, true) . "</pre>";
    if ($status->skip_accepted) {
        echo '<p style="color: orange;">⚠️ SKIP PERMANENTE ATTIVO - i reminder non verranno mostrati!</p>';
    }
} else {
    echo "<p>Nessun record status (default: abilitato, no skip)</p>";
}

// Verifica assegnazioni competenze
echo "<h2>4. Competenze Assegnate</h2>";
$assignments = $DB->get_records('local_selfassessment_assign', ['userid' => $critest->id]);
echo "<p>Totale assegnazioni: " . count($assignments) . "</p>";

if (!empty($assignments)) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Competency ID</th><th>Source</th><th>Source ID</th><th>Data</th></tr>";
    foreach ($assignments as $a) {
        $comp = $DB->get_record('competency', ['id' => $a->competencyid]);
        $comp_name = $comp ? $comp->idnumber : 'N/A';
        echo "<tr><td>{$a->id}</td><td>{$a->competencyid} ({$comp_name})</td><td>{$a->source}</td><td>{$a->sourceid}</td><td>" . date('Y-m-d H:i', $a->timecreated) . "</td></tr>";
    }
    echo "</table>";
}

// Verifica autovalutazioni completate
echo "<h2>5. Autovalutazioni Completate</h2>";
$assessments = $DB->get_records('local_selfassessment', ['userid' => $critest->id]);
echo "<p>Totale autovalutazioni: " . count($assessments) . "</p>";

// Conta pending
$assigned_ids = array_column($assignments, 'competencyid');
$completed = 0;
foreach ($assessments as $a) {
    if (in_array($a->competencyid, $assigned_ids) && $a->level > 0) {
        $completed++;
    }
}
$pending = count($assignments) - $completed;
echo "<p><strong>Pending: {$pending}</strong> | Completate: {$completed} / " . count($assignments) . "</p>";

// Verifica quiz recenti
echo "<h2>6. Quiz Recenti (ultimi 7 giorni)</h2>";
$week_ago = time() - (7 * 24 * 60 * 60);
$attempts = $DB->get_records_sql("
    SELECT qa.*, q.name as quizname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ?
    AND qa.timefinish > ?
    ORDER BY qa.timefinish DESC
", [$critest->id, $week_ago]);

if (empty($attempts)) {
    echo "<p>Nessun quiz completato negli ultimi 7 giorni</p>";
} else {
    echo "<table border='1' cellpadding='5'><tr><th>Attempt ID</th><th>Quiz</th><th>State</th><th>Fine</th></tr>";
    foreach ($attempts as $a) {
        echo "<tr><td>{$a->id}</td><td>{$a->quizname}</td><td>{$a->state}</td><td>" . date('Y-m-d H:i', $a->timefinish) . "</td></tr>";
    }
    echo "</table>";
}

// Verifica ultimo quiz METALCOSTRUZIONE
echo "<h2>7. Quiz METALCOSTRUZIONE più recente</h2>";
$metal_attempt = $DB->get_record_sql("
    SELECT qa.*, q.name as quizname, q.id as quizid
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ?
    AND q.name LIKE '%METALCOSTRUZIONE%'
    ORDER BY qa.timefinish DESC
    LIMIT 1
", [$critest->id]);

if ($metal_attempt) {
    echo "<p>Quiz: {$metal_attempt->quizname}</p>";
    echo "<p>Attempt ID: {$metal_attempt->id} | Quiz ID: {$metal_attempt->quizid}</p>";
    echo "<p>State: {$metal_attempt->state} | Finish: " . ($metal_attempt->timefinish ? date('Y-m-d H:i:s', $metal_attempt->timefinish) : 'N/A') . "</p>";

    // Verifica domande del quiz
    $questions = $DB->get_records_sql("
        SELECT DISTINCT qa.questionid
        FROM {question_attempts} qa
        WHERE qa.questionusageid = ?
    ", [$metal_attempt->uniqueid]);

    echo "<p>Domande nel quiz: " . count($questions) . "</p>";

    if (!empty($questions)) {
        $qids = array_keys($questions);

        // Cerca competenze associate
        $comp_tables = [
            'qbank_competenciesbyquestion' => 'questionid',
            'local_competencymanager_qcomp' => 'questionid'
        ];

        foreach ($comp_tables as $table => $field) {
            if ($DB->get_manager()->table_exists($table)) {
                list($sql_in, $params) = $DB->get_in_or_equal($qids);
                $mappings = $DB->get_records_sql("
                    SELECT DISTINCT competencyid
                    FROM {{$table}}
                    WHERE {$field} $sql_in
                ", $params);

                echo "<p>Tabella {$table}: " . count($mappings) . " competenze trovate</p>";

                if (!empty($mappings)) {
                    echo "<ul>";
                    foreach ($mappings as $m) {
                        $comp = $DB->get_record('competency', ['id' => $m->competencyid]);
                        $assigned = $DB->record_exists('local_selfassessment_assign', [
                            'userid' => $critest->id,
                            'competencyid' => $m->competencyid
                        ]);
                        $status_icon = $assigned ? '✅ Assegnata' : '❌ NON assegnata';
                        echo "<li>{$m->competencyid} - " . ($comp ? $comp->idnumber : 'N/A') . " - {$status_icon}</li>";
                    }
                    echo "</ul>";
                }
            }
        }
    }
} else {
    echo "<p style='color: red;'>Nessun quiz METALCOSTRUZIONE trovato per critest</p>";
}

// Verifica hook registration
echo "<h2>8. Verifica Hook Registration</h2>";
$hooks_file = __DIR__ . '/db/hooks.php';
if (file_exists($hooks_file)) {
    echo "<p style='color: green;'>✅ File db/hooks.php esiste</p>";
    $callbacks = [];
    include($hooks_file);
    echo "<pre>" . print_r($callbacks, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ File db/hooks.php NON esiste!</p>";
}

// Verifica hook_callbacks class
$class_file = __DIR__ . '/classes/hook_callbacks.php';
if (file_exists($class_file)) {
    echo "<p style='color: green;'>✅ File classes/hook_callbacks.php esiste</p>";
} else {
    echo "<p style='color: red;'>❌ File classes/hook_callbacks.php NON esiste!</p>";
}

// Test funzione reminder status
echo "<h2>9. Test local_selfassessment_get_reminder_status()</h2>";
require_once(__DIR__ . '/lib.php');
$reminder_status = local_selfassessment_get_reminder_status($critest->id);
echo "<pre>" . print_r($reminder_status, true) . "</pre>";

if ($reminder_status['should_show']) {
    echo "<p style='color: green;'>✅ I reminder DOVREBBERO essere mostrati</p>";
} else {
    echo "<p style='color: orange;'>⚠️ I reminder NON verranno mostrati</p>";
    if ($reminder_status['has_permanent_skip']) {
        echo "<p>Motivo: Skip permanente attivo</p>";
    } elseif ($reminder_status['pending_count'] == 0) {
        echo "<p>Motivo: Nessuna competenza pending (tutte completate o nessuna assegnata)</p>";
    }
}

// Link utili
echo "<h2>10. Azioni</h2>";
echo '<p>';
echo '<a href="force_assign.php?userid=' . $critest->id . '" style="margin-right: 15px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">⚡ Force Assign per Critest</a>';
echo '<a href="fix_observer.php" style="margin-right: 15px; background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">🔧 Fix Observer</a>';
echo '<a href="' . $CFG->wwwroot . '/admin/purgecaches.php" style="background: #dc3545; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">🗑️ Purge Caches</a>';
echo '</p>';

echo $OUTPUT->footer();
