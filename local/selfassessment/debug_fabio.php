<?php
// ============================================
// DEBUG: Verifica Quiz Fabio - Corso ID 12
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/debug_fabio.php'));
$PAGE->set_title('Debug Fabio - Corso 12');

// Parametri
$courseid = optional_param('courseid', 12, PARAM_INT);
$userid = optional_param('userid', 82, PARAM_INT); // Fabio

echo $OUTPUT->header();
?>
<style>
.debug-container { max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.debug-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.debug-title { font-size: 1.3em; font-weight: 700; margin-bottom: 15px; color: #333; }
.debug-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
.debug-table th, .debug-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
.debug-table th { background: #f8f9fa; font-weight: 600; }
.debug-table tr:hover { background: #f8f9ff; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 0.8em; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.alert { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 0.85em; }
</style>

<div class="debug-container">
    <h1>🔍 Debug Completo: Fabio e Corso ID <?php echo $courseid; ?></h1>

<?php
$dbman = $DB->get_manager();

// 1. TROVA UTENTE
echo '<div class="debug-card">';
echo '<div class="debug-title">1. 👤 Utente</div>';

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);

if ($user) {
    echo '<div class="alert alert-success">';
    echo "<strong>Trovato:</strong> {$user->firstname} {$user->lastname} (ID: {$user->id}, username: {$user->username})";
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">Utente ID ' . $userid . ' non trovato!</div>';
    echo $OUTPUT->footer();
    die();
}
echo '</div>';

// 2. CORSO
echo '<div class="debug-card">';
echo '<div class="debug-title">2. 📚 Corso</div>';

$course = $DB->get_record('course', ['id' => $courseid]);

if ($course) {
    echo '<div class="alert alert-success">';
    echo "<strong>Trovato:</strong> {$course->fullname} (ID: {$course->id}, shortname: {$course->shortname})";
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">Corso ID ' . $courseid . ' non trovato!</div>';
    echo $OUTPUT->footer();
    die();
}
echo '</div>';

// 3. ISCRIZIONE AL CORSO
echo '<div class="debug-card">';
echo '<div class="debug-title">3. 📝 Iscrizione al Corso</div>';

$context = context_course::instance($courseid);
$roles = get_user_roles($context, $userid, true);

if ($roles) {
    echo '<table class="debug-table">';
    echo '<tr><th>Ruolo</th><th>Shortname</th><th>Context</th></tr>';
    foreach ($roles as $role) {
        echo "<tr><td><strong>{$role->name}</strong></td><td>{$role->shortname}</td><td>{$role->contextid}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<div class="alert alert-danger">Utente NON ha ruoli nel corso!</div>';
}
echo '</div>';

// 4. QUIZ DEL CORSO
echo '<div class="debug-card">';
echo '<div class="debug-title">4. 📋 Quiz nel Corso</div>';

$quizzes = $DB->get_records_sql("
    SELECT q.id, q.name, cm.id as cmid
    FROM {quiz} q
    JOIN {course_modules} cm ON cm.instance = q.id
    JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
    WHERE q.course = ?
    ORDER BY q.name
", [$courseid]);

echo "<p><strong>Totale quiz nel corso:</strong> " . count($quizzes) . "</p>";

if ($quizzes) {
    echo '<table class="debug-table">';
    echo '<tr><th>ID</th><th>Nome Quiz</th><th>CMID</th></tr>';
    foreach ($quizzes as $quiz) {
        echo "<tr><td>{$quiz->id}</td><td>{$quiz->name}</td><td>{$quiz->cmid}</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// 5. TENTATIVI QUIZ DELL'UTENTE
echo '<div class="debug-card">';
echo '<div class="debug-title">5. 🎯 Tentativi Quiz (ultimi 7 giorni)</div>';

$week_ago = time() - (7 * 24 * 60 * 60);
$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.attempt, qa.state, qa.timefinish, qa.sumgrades,
           q.name as quizname, q.course
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ? AND qa.timefinish > ?
    ORDER BY qa.timefinish DESC
", [$userid, $week_ago]);

echo "<p><strong>Tentativi trovati:</strong> " . count($attempts) . "</p>";

if ($attempts) {
    echo '<table class="debug-table">';
    echo '<tr><th>Attempt ID</th><th>Quiz</th><th>Corso ID</th><th>Stato</th><th>Data Fine</th></tr>';
    foreach ($attempts as $att) {
        $date = date('d/m/Y H:i', $att->timefinish);
        $state_badge = $att->state == 'finished'
            ? '<span class="badge badge-success">Finished</span>'
            : '<span class="badge badge-warning">' . $att->state . '</span>';
        $is_this_course = $att->course == $courseid ? ' <span class="badge badge-info">Questo corso</span>' : '';
        echo "<tr><td>{$att->id}</td><td>{$att->quizname}{$is_this_course}</td><td>{$att->course}</td><td>{$state_badge}</td><td>{$date}</td></tr>";
    }
    echo '</table>';
} else {
    echo '<div class="alert alert-warning">Nessun tentativo negli ultimi 7 giorni!</div>';
}

// Tentativi OGGI
$today_start = strtotime('today');
$today_attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, q.name as quizname, qa.timefinish
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ? AND qa.state = 'finished' AND qa.timefinish > ?
    ORDER BY qa.timefinish DESC
", [$userid, $today_start]);

echo "<h4 style='margin-top: 20px;'>Quiz completati OGGI: " . count($today_attempts) . "</h4>";
if ($today_attempts) {
    foreach ($today_attempts as $ta) {
        $time = date('H:i', $ta->timefinish);
        echo "• {$ta->quizname} alle {$time}<br>";
    }
}
echo '</div>';

// 6. VERIFICA SETTORE PRIMARIO
echo '<div class="debug-card">';
echo '<div class="debug-title">6. 🏭 Settori Utente</div>';

if ($dbman->table_exists('local_student_sectors')) {
    $sectors = $DB->get_records('local_student_sectors', ['userid' => $userid]);
    if ($sectors) {
        echo '<table class="debug-table">';
        echo '<tr><th>Settore</th><th>Primario</th><th>Sorgente</th><th>Quiz Count</th></tr>';
        foreach ($sectors as $sec) {
            $primary = $sec->is_primary ? '<span class="badge badge-danger">SÌ PRIMARIO</span>' : '<span class="badge badge-info">No</span>';
            echo "<tr><td><strong>{$sec->sector}</strong></td><td>{$primary}</td><td>{$sec->source}</td><td>{$sec->quiz_count}</td></tr>";
        }
        echo '</table>';

        $primary_sector = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
        if ($primary_sector) {
            echo '<div class="alert alert-danger" style="margin-top: 15px;">';
            echo "<strong>⚠️ PROBLEMA CRITICO!</strong> L'utente ha settore primario: <strong>{$primary_sector->sector}</strong><br>";
            echo "L'observer SALTA le competenze di altri settori! Questo è il motivo per cui non vengono create assegnazioni.";
            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-success">Nessun settore impostato - OK, riceverà competenze da tutti i settori</div>';
    }
} else {
    echo '<div class="alert alert-info">Tabella local_student_sectors non esiste</div>';
}
echo '</div>';

// 7. COMPETENZE MAPPATE AI QUIZ DEL CORSO
echo '<div class="debug-card">';
echo '<div class="debug-title">7. 🔗 Competenze Mappate ai Quiz del Corso</div>';

$mapping_table = null;
foreach (['qbank_competenciesbyquestion', 'local_competencymanager_qcomp'] as $t) {
    if ($dbman->table_exists($t)) {
        $mapping_table = $t;
        break;
    }
}

if ($mapping_table && !empty($quizzes)) {
    $quiz_ids = array_keys($quizzes);
    list($in_sql, $params) = $DB->get_in_or_equal($quiz_ids, SQL_PARAMS_NAMED);

    $competency_counts = $DB->get_records_sql("
        SELECT q.id, q.name, COUNT(DISTINCT m.competencyid) as comp_count
        FROM {quiz} q
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {{$mapping_table}} m ON m.questionid = qs.questionid
        WHERE q.id $in_sql
        GROUP BY q.id, q.name
        ORDER BY q.name
    ", $params);

    if ($competency_counts) {
        echo '<table class="debug-table">';
        echo '<tr><th>Quiz</th><th>Competenze Mappate</th></tr>';
        $total_with_comp = 0;
        foreach ($competency_counts as $qc) {
            $badge = $qc->comp_count > 0
                ? '<span class="badge badge-success">' . $qc->comp_count . '</span>'
                : '<span class="badge badge-danger">0</span>';
            echo "<tr><td>{$qc->name}</td><td>{$badge}</td></tr>";
            if ($qc->comp_count > 0) $total_with_comp++;
        }
        echo '</table>';

        $quizzes_without = count($quizzes) - count($competency_counts);
        if ($quizzes_without > 0) {
            echo "<div class='alert alert-warning' style='margin-top: 15px;'>";
            echo "<strong>⚠️ {$quizzes_without} quiz NON hanno competenze mappate!</strong>";
            echo "</div>";
        }
    } else {
        echo '<div class="alert alert-danger"><strong>NESSUNA</strong> competenza mappata ai quiz di questo corso!</div>';
    }
} else {
    echo '<div class="alert alert-danger">Tabella mapping non trovata o nessun quiz!</div>';
}
echo '</div>';

// 8. ASSEGNAZIONI CREATE
echo '<div class="debug-card">';
echo '<div class="debug-title">8. 📥 Assegnazioni Autovalutazione</div>';

$assignments = $DB->get_records_sql("
    SELECT a.*, c.shortname as comp_shortname, c.idnumber as comp_idnumber
    FROM {local_selfassessment_assign} a
    LEFT JOIN {competency} c ON c.id = a.competencyid
    WHERE a.userid = ?
    ORDER BY a.timecreated DESC
    LIMIT 20
", [$userid]);

$total_assignments = $DB->count_records('local_selfassessment_assign', ['userid' => $userid]);
$today_assignments = $DB->count_records_sql("
    SELECT COUNT(*) FROM {local_selfassessment_assign}
    WHERE userid = ? AND timecreated > ?
", [$userid, $today_start]);

echo "<p><strong>Totale assegnazioni:</strong> {$total_assignments}</p>";
echo "<p><strong>Assegnazioni OGGI:</strong> <span class='" . ($today_assignments > 0 ? "badge badge-success" : "badge badge-danger") . "'>{$today_assignments}</span></p>";

if ($assignments) {
    echo '<h4>Ultime 20 assegnazioni:</h4>';
    echo '<table class="debug-table">';
    echo '<tr><th>ID</th><th>Competenza</th><th>Sorgente</th><th>Data Creazione</th></tr>';
    foreach ($assignments as $ass) {
        $date = date('d/m/Y H:i', $ass->timecreated);
        $comp_name = $ass->comp_idnumber ?: $ass->comp_shortname ?: "ID: {$ass->competencyid}";
        $is_today = $ass->timecreated > $today_start ? ' <span class="badge badge-info">OGGI</span>' : '';
        echo "<tr><td>{$ass->id}</td><td>{$comp_name}</td><td>{$ass->source}</td><td>{$date}{$is_today}</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// 9. CAPABILITY CHECK
echo '<div class="debug-card">';
echo '<div class="debug-title">9. 🔐 Verifica Capability</div>';

$can_complete = has_capability('local/selfassessment:complete', $context, $userid);

if ($can_complete) {
    echo '<div class="alert alert-success">';
    echo "✓ L'utente HA la capability <code>local/selfassessment:complete</code> in questo corso";
    echo '</div>';
} else {
    echo '<div class="alert alert-danger">';
    echo "✗ L'utente NON ha la capability <code>local/selfassessment:complete</code>!";
    echo '</div>';
}
echo '</div>';

// 10. RIEPILOGO PROBLEMI
echo '<div class="debug-card">';
echo '<div class="debug-title">10. ⚠️ RIEPILOGO PROBLEMI</div>';

$problems = [];
$warnings = [];

// Check settore primario
$primary_sector = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
if ($primary_sector) {
    $problems[] = "🔴 <strong>SETTORE PRIMARIO:</strong> L'utente ha settore '{$primary_sector->sector}' - le competenze di altri settori vengono SALTATE dall'observer";
}

// Check capability
if (!$can_complete) {
    $problems[] = "🔴 <strong>CAPABILITY:</strong> L'utente non ha la capability per completare autovalutazioni";
}

// Check assegnazioni oggi vs quiz oggi
$today_quiz_count = count($today_attempts);
if ($today_quiz_count > 0 && $today_assignments == 0) {
    $problems[] = "🔴 <strong>OBSERVER NON FUNZIONA:</strong> {$today_quiz_count} quiz completati oggi ma 0 assegnazioni create!";
}

// Check competenze mappate
if ($mapping_table && !empty($quizzes)) {
    $total_mapped = $DB->count_records_sql("
        SELECT COUNT(DISTINCT m.competencyid)
        FROM {quiz} q
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {{$mapping_table}} m ON m.questionid = qs.questionid
        WHERE q.course = ?
    ", [$courseid]);

    if ($total_mapped == 0) {
        $problems[] = "🔴 <strong>NESSUN MAPPING:</strong> I quiz del corso non hanno competenze mappate!";
    } else {
        $warnings[] = "🟡 Competenze mappate nel corso: {$total_mapped}";
    }
}

if (empty($problems)) {
    echo '<div class="alert alert-success">✅ Nessun problema critico rilevato</div>';
} else {
    echo '<div class="alert alert-danger">';
    echo '<strong>PROBLEMI CRITICI:</strong><br><br>';
    foreach ($problems as $p) {
        echo $p . '<br><br>';
    }
    echo '</div>';
}

if (!empty($warnings)) {
    echo '<div class="alert alert-info">';
    foreach ($warnings as $w) {
        echo $w . '<br>';
    }
    echo '</div>';
}

echo '<div style="margin-top: 20px;">';
echo '<a href="remove_sector_testers.php" class="btn" style="background: #dc3545; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-right: 10px; font-weight: 600;">🗑️ Rimuovi Settori Tester</a>';
echo '<a href="catchup_assignments.php" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-right: 10px; font-weight: 600;">🔄 Catchup Assegnazioni</a>';
echo '<a href="diagnose.php" class="btn" style="background: #007bff; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">🔍 Diagnosi Generale</a>';
echo '</div>';

echo '</div>';
?>

</div>
<?php
echo $OUTPUT->footer();
