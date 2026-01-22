<?php
/**
 * Test Observer - Verifica funzionamento observer quiz
 *
 * @package    local_selfassessment
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/test_observer.php'));
$PAGE->set_title('Test Observer Selfassessment');
$PAGE->set_heading('Test Observer Selfassessment');

echo $OUTPUT->header();

echo '<div style="max-width: 900px; margin: 0 auto; padding: 20px;">';
echo '<h2>Diagnostica Observer Quiz → Autovalutazione</h2>';

// 1. Verifica registrazione observer
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>1. Registrazione Observer</h3>';

$events_file = __DIR__ . '/db/events.php';
if (file_exists($events_file)) {
    include($events_file);
    if (isset($observers) && !empty($observers)) {
        echo '<p style="color: green;">✅ File events.php trovato con ' . count($observers) . ' observer registrato/i</p>';
        foreach ($observers as $obs) {
            echo '<code>' . $obs['eventname'] . ' → ' . $obs['callback'] . '</code><br>';
        }
    } else {
        echo '<p style="color: red;">❌ File events.php trovato ma nessun observer definito!</p>';
    }
} else {
    echo '<p style="color: red;">❌ File events.php NON trovato!</p>';
}
echo '</div>';

// 2. Verifica classe observer
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>2. Classe Observer</h3>';

$observer_file = __DIR__ . '/classes/observer.php';
if (file_exists($observer_file)) {
    echo '<p style="color: green;">✅ File observer.php esiste</p>';

    if (class_exists('\local_selfassessment\observer')) {
        echo '<p style="color: green;">✅ Classe \local_selfassessment\observer caricata</p>';

        if (method_exists('\local_selfassessment\observer', 'quiz_attempt_submitted')) {
            echo '<p style="color: green;">✅ Metodo quiz_attempt_submitted() esiste</p>';
        } else {
            echo '<p style="color: red;">❌ Metodo quiz_attempt_submitted() NON trovato!</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ Classe non ancora autoloadata (normale se mai chiamata)</p>';
    }
} else {
    echo '<p style="color: red;">❌ File observer.php NON trovato!</p>';
}
echo '</div>';

// 3. Statistiche assegnazioni
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>3. Statistiche Assegnazioni per Source</h3>';

$stats = $DB->get_records_sql("
    SELECT source, COUNT(*) as count
    FROM {local_selfassessment_assign}
    GROUP BY source
    ORDER BY count DESC
");

if (empty($stats)) {
    echo '<p style="color: orange;">⚠️ Nessuna assegnazione nel sistema</p>';
} else {
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr style="background: #e9ecef;"><th style="padding: 8px; text-align: left;">Source</th><th style="padding: 8px; text-align: right;">Count</th></tr>';
    $has_quiz = false;
    foreach ($stats as $s) {
        $color = ($s->source === 'quiz') ? 'background: #d4edda;' : '';
        if ($s->source === 'quiz') $has_quiz = true;
        echo "<tr style='$color'><td style='padding: 8px;'>" . s($s->source) . "</td><td style='padding: 8px; text-align: right;'>{$s->count}</td></tr>";
    }
    echo '</table>';

    if (!$has_quiz) {
        echo '<p style="color: orange; margin-top: 10px;">⚠️ <strong>Nessuna assegnazione con source=\'quiz\'</strong> - L\'observer potrebbe non essere stato ancora attivato o nessun quiz completato dopo l\'installazione.</p>';
    } else {
        echo '<p style="color: green; margin-top: 10px;">✅ Ci sono assegnazioni da quiz - L\'observer funziona!</p>';
    }
}
echo '</div>';

// 4. Ultimi quiz completati
echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>4. Ultimi 5 Quiz Completati</h3>';

$recent_attempts = $DB->get_records_sql("
    SELECT qa.id, qa.userid, qa.quiz, qa.timefinish, q.name as quizname,
           u.firstname, u.lastname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {user} u ON u.id = qa.userid
    WHERE qa.state = 'finished'
    ORDER BY qa.timefinish DESC
    LIMIT 5
");

if (empty($recent_attempts)) {
    echo '<p>Nessun quiz completato trovato.</p>';
} else {
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr style="background: #e9ecef;">';
    echo '<th style="padding: 8px;">Data</th>';
    echo '<th style="padding: 8px;">Utente</th>';
    echo '<th style="padding: 8px;">Quiz</th>';
    echo '<th style="padding: 8px;">Assegnazioni create?</th>';
    echo '</tr>';

    foreach ($recent_attempts as $att) {
        // Verifica se sono state create assegnazioni per questo utente dopo questo quiz
        $assign_count = $DB->count_records_select('local_selfassessment_assign',
            "userid = ? AND source = 'quiz' AND sourceid = ?",
            [$att->userid, $att->quiz]
        );

        $status = $assign_count > 0
            ? "<span style='color: green;'>✅ Sì ($assign_count)</span>"
            : "<span style='color: orange;'>❌ No</span>";

        echo '<tr>';
        echo '<td style="padding: 8px;">' . userdate($att->timefinish, '%d/%m %H:%M') . '</td>';
        echo '<td style="padding: 8px;">' . s($att->firstname . ' ' . $att->lastname) . '</td>';
        echo '<td style="padding: 8px;">' . s($att->quizname) . '</td>';
        echo '<td style="padding: 8px;">' . $status . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

// 5. Azioni consigliate
echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>5. Azioni Consigliate</h3>';
echo '<ol>';
echo '<li><strong>Svuota cache Moodle</strong>: <a href="' . (new moodle_url('/admin/purgecaches.php'))->out() . '" target="_blank">Clicca qui</a></li>';
echo '<li><strong>Verifica upgrade plugin</strong>: <a href="' . (new moodle_url('/admin/index.php'))->out() . '" target="_blank">Notifiche Admin</a></li>';
echo '<li><strong>Test manuale</strong>: Completa un quiz con un utente di test e ricarica questa pagina</li>';
echo '</ol>';
echo '</div>';

// 6. Test manuale simulato (solo per admin)
echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
echo '<h3>6. Simula Evento Quiz (Test)</h3>';
echo '<p>Questo test simula l\'evento di completamento quiz per verificare che l\'observer funzioni.</p>';

$testuser = optional_param('testuser', 0, PARAM_INT);
$testquiz = optional_param('testquiz', 0, PARAM_INT);

if ($testuser && $testquiz && confirm_sesskey()) {
    // Simula l'evento
    require_once(__DIR__ . '/classes/observer.php');

    // Trova un attempt esistente per questo user/quiz
    $attempt = $DB->get_record_sql("
        SELECT * FROM {quiz_attempts}
        WHERE userid = ? AND quiz = ? AND state = 'finished'
        ORDER BY timefinish DESC LIMIT 1
    ", [$testuser, $testquiz]);

    if ($attempt) {
        // Conta assegnazioni PRIMA
        $before = $DB->count_records('local_selfassessment_assign', ['userid' => $testuser]);

        // Crea evento fake
        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid' => $attempt->id,
            'relateduserid' => $testuser,
            'context' => context_module::instance($DB->get_field('course_modules', 'id', [
                'instance' => $testquiz,
                'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])
            ])),
            'other' => ['quizid' => $testquiz]
        ]);

        // Chiama observer manualmente
        \local_selfassessment\observer::quiz_attempt_submitted($event);

        // Conta assegnazioni DOPO
        $after = $DB->count_records('local_selfassessment_assign', ['userid' => $testuser]);
        $new = $after - $before;

        echo "<div style='background: #d4edda; padding: 10px; border-radius: 6px; margin: 10px 0;'>";
        echo "<strong>✅ Test eseguito!</strong><br>";
        echo "Assegnazioni prima: $before<br>";
        echo "Assegnazioni dopo: $after<br>";
        echo "Nuove assegnazioni: <strong>$new</strong>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 6px;'>";
        echo "❌ Nessun attempt trovato per questo user/quiz";
        echo "</div>";
    }
}

// Form per test manuale
$users_with_attempts = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {quiz_attempts} qa ON qa.userid = u.id
    WHERE qa.state = 'finished'
    ORDER BY u.lastname
    LIMIT 20
");

$quizzes = $DB->get_records_sql("
    SELECT DISTINCT q.id, q.name
    FROM {quiz} q
    JOIN {quiz_attempts} qa ON qa.quiz = q.id
    WHERE qa.state = 'finished'
    ORDER BY q.name
    LIMIT 20
");

if (!empty($users_with_attempts) && !empty($quizzes)) {
    echo '<form method="get" style="margin-top: 15px;">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

    echo '<label>Utente: <select name="testuser">';
    foreach ($users_with_attempts as $u) {
        echo '<option value="' . $u->id . '">' . s($u->firstname . ' ' . $u->lastname) . '</option>';
    }
    echo '</select></label> ';

    echo '<label>Quiz: <select name="testquiz">';
    foreach ($quizzes as $q) {
        echo '<option value="' . $q->id . '">' . s($q->name) . '</option>';
    }
    echo '</select></label> ';

    echo '<button type="submit" style="padding: 5px 15px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">Simula Evento</button>';
    echo '</form>';
} else {
    echo '<p style="color: #666;">Nessun quiz completato trovato per il test.</p>';
}

echo '</div>';

echo '<p><a href="' . (new moodle_url('/local/ftm_testsuite/index.php'))->out() . '">&larr; Torna alla Test Suite</a></p>';
echo '</div>';

echo $OUTPUT->footer();
