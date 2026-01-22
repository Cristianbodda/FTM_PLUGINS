<?php
/**
 * Script per generare autovalutazioni automatiche
 *
 * Genera record di autovalutazione per utenti che hanno completato quiz
 * basandosi sulle competenze testate.
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/generate_selfassessments.php'));
$PAGE->set_title('Genera Autovalutazioni - FTM Test Suite');
$PAGE->set_heading('Genera Autovalutazioni Automatiche');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$mode = optional_param('mode', 'test_users', PARAM_ALPHA); // test_users | all_users | specific_user
$userid = optional_param('userid', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto;">';

// Header
echo '<div style="background: linear-gradient(135deg, #6f42c1 0%, #8969d3 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px;">';
echo '<h2 style="margin: 0 0 10px 0;">🧠 Generatore Autovalutazioni</h2>';
echo '<p style="margin: 0; opacity: 0.9;">Crea record di autovalutazione basati sulle competenze testate nei quiz.</p>';
echo '</div>';

// Statistiche attuali
$stats = [
    'selfassessments' => $DB->count_records('local_selfassessment'),
    'assignments' => $DB->count_records('local_selfassessment_assign'),
    'users_with_sa' => $DB->count_records_sql("SELECT COUNT(DISTINCT userid) FROM {local_selfassessment}"),
    'competencies_tested' => $DB->count_records_sql("
        SELECT COUNT(DISTINCT qc.competencyid)
        FROM {qbank_competenciesbyquestion} qc
        JOIN {question_versions} qv ON qv.questionid = qc.questionid
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
        JOIN {quiz_slots} qs ON qs.id = qr.itemid
    ")
];

echo '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">';
foreach ([
    ['Autovalutazioni', $stats['selfassessments'], '#28a745'],
    ['Assegnazioni', $stats['assignments'], '#17a2b8'],
    ['Utenti con SA', $stats['users_with_sa'], '#6f42c1'],
    ['Competenze Testate', $stats['competencies_tested'], '#fd7e14']
] as $s) {
    echo "<div style='background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>";
    echo "<div style='font-size: 32px; font-weight: bold; color: {$s[2]};'>{$s[1]}</div>";
    echo "<div style='color: #666; font-size: 13px;'>{$s[0]}</div>";
    echo "</div>";
}
echo '</div>';

// Form configurazione
echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">';
echo '<h3 style="margin: 0 0 15px 0;">⚙️ Configurazione</h3>';
echo '<form method="post" action="">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="generate">';

// Selettore corso
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, COUNT(qa.id) as attempts
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
    WHERE c.id > 1
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
");

echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; font-weight: 600; margin-bottom: 5px;">📚 Corso:</label>';
echo '<select name="courseid" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
echo '<option value="0">-- Tutti i corsi --</option>';
foreach ($courses as $c) {
    $selected = ($c->id == $courseid) ? 'selected' : '';
    echo "<option value=\"{$c->id}\" {$selected}>" . s($c->fullname) . " ({$c->attempts} tentativi)</option>";
}
echo '</select>';
echo '</div>';

// Selettore modalità
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; font-weight: 600; margin-bottom: 5px;">👤 Utenti:</label>';
echo '<div style="display: flex; gap: 15px; flex-wrap: wrap;">';
$modes = [
    'test_users' => ['🧪 Solo utenti test FTM', 'ftm_test_low30, medium65, high95'],
    'all_users' => ['👥 Tutti gli utenti con quiz', 'Genera per tutti gli studenti'],
    'specific_user' => ['🎯 Utente specifico', 'Seleziona un utente']
];
foreach ($modes as $m => $info) {
    $checked = ($m == $mode) ? 'checked' : '';
    echo "<label style='display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: #f8f9fa; border-radius: 6px; cursor: pointer;'>";
    echo "<input type='radio' name='mode' value='{$m}' {$checked} onchange='toggleUserSelect()'>";
    echo "<span><strong>{$info[0]}</strong><br><small style='color: #666;'>{$info[1]}</small></span>";
    echo "</label>";
}
echo '</div>';
echo '</div>';

// Selettore utente specifico
echo '<div id="user_select" style="margin-bottom: 15px; display: ' . ($mode == 'specific_user' ? 'block' : 'none') . ';">';
echo '<label style="display: block; font-weight: 600; margin-bottom: 5px;">Seleziona Utente:</label>';
$users_with_attempts = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, COUNT(qa.id) as attempts
    FROM {user} u
    JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.state = 'finished'
    GROUP BY u.id, u.username, u.firstname, u.lastname
    ORDER BY u.lastname, u.firstname
    LIMIT 100
");
echo '<select name="userid" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
foreach ($users_with_attempts as $u) {
    $selected = ($u->id == $userid) ? 'selected' : '';
    echo "<option value=\"{$u->id}\" {$selected}>{$u->lastname} {$u->firstname} ({$u->username}) - {$u->attempts} quiz</option>";
}
echo '</select>';
echo '</div>';

echo '<button type="submit" style="background: #6f42c1; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">🚀 Genera Autovalutazioni</button>';
echo '</form>';
echo '</div>';

echo '<script>
function toggleUserSelect() {
    var mode = document.querySelector("input[name=mode]:checked").value;
    document.getElementById("user_select").style.display = (mode == "specific_user") ? "block" : "none";
}
</script>';

// Esecuzione generazione
if ($action === 'generate' && confirm_sesskey()) {
    echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
    echo '<h3 style="margin: 0 0 15px 0;">📋 Log Generazione</h3>';
    echo '<div style="background: #1e1e1e; color: #fff; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; max-height: 500px; overflow-y: auto;">';

    $log = [];
    $created_sa = 0;
    $created_assign = 0;
    $skipped = 0;

    // Determina utenti da processare
    if ($mode === 'test_users') {
        $users = $DB->get_records_sql("
            SELECT tu.userid, u.username, u.firstname, u.lastname, tu.testprofile, tu.quiz_percentage
            FROM {local_ftm_testsuite_users} tu
            JOIN {user} u ON u.id = tu.userid
        ");
        $log[] = "<span style='color: #ffc107;'>⚡ Modalità: Solo utenti test FTM (" . count($users) . " utenti)</span>";
    } elseif ($mode === 'specific_user' && $userid > 0) {
        $u = $DB->get_record('user', ['id' => $userid]);
        if ($u) {
            $users = [(object)[
                'userid' => $u->id,
                'username' => $u->username,
                'firstname' => $u->firstname,
                'lastname' => $u->lastname,
                'testprofile' => 'real',
                'quiz_percentage' => 0
            ]];
            $log[] = "<span style='color: #ffc107;'>⚡ Modalità: Utente specifico - {$u->username}</span>";
        } else {
            $users = [];
        }
    } else {
        // Tutti gli utenti con quiz completati
        $course_filter = $courseid > 0 ? "AND q.course = ?" : "";
        $params = $courseid > 0 ? [$courseid] : [];

        $users = $DB->get_records_sql("
            SELECT DISTINCT u.id as userid, u.username, u.firstname, u.lastname,
                   'real' as testprofile, 0 as quiz_percentage
            FROM {user} u
            JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.state = 'finished'
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE 1=1 {$course_filter}
        ", $params);
        $log[] = "<span style='color: #ffc107;'>⚡ Modalità: Tutti gli utenti (" . count($users) . " utenti)</span>";
    }

    if ($courseid > 0) {
        $course = $DB->get_record('course', ['id' => $courseid]);
        $log[] = "<span style='color: #17a2b8;'>📚 Filtro corso: " . s($course->fullname) . "</span>";
    }

    $log[] = "";

    foreach ($users as $user) {
        $log[] = "<span style='color: #28a745;'>👤 Elaborazione: {$user->username}</span>";

        // Trova competenze testate dall'utente
        $course_filter = $courseid > 0 ? "AND q.course = ?" : "";
        $params = [$user->userid];
        if ($courseid > 0) {
            $params[] = $courseid;
        }

        $competencies = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.shortname, c.idnumber
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE qa.userid = ? AND qa.state = 'finished' {$course_filter}
        ", $params);

        if (empty($competencies)) {
            $log[] = "   <span style='color: #999;'>⏭️ Nessuna competenza trovata nei quiz</span>";
            continue;
        }

        $log[] = "   📊 Trovate " . count($competencies) . " competenze";

        // Calcola performance media dell'utente per determinare Bloom
        $avg_score = $DB->get_record_sql("
            SELECT AVG(qa.sumgrades / q.sumgrades * 100) as avg_pct
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = ? AND qa.state = 'finished' AND q.sumgrades > 0
        ", [$user->userid]);

        $user_pct = $avg_score->avg_pct ?? 50;

        // Determina range Bloom basato su performance (o testprofile se test user)
        if (isset($user->testprofile) && $user->testprofile !== 'real') {
            $bloom_range = match($user->testprofile) {
                'low30' => [4, 6],    // Sovrastima
                'medium65' => [3, 5], // Calibrato
                'high95' => [2, 4],   // Sottostima
                default => [3, 5]
            };
        } else {
            // Utente reale: Bloom inversamente correlato alla performance
            if ($user_pct >= 80) {
                $bloom_range = [2, 4]; // Bravi = tendono a sottostimarsi
            } elseif ($user_pct >= 50) {
                $bloom_range = [3, 5]; // Medi = calibrati
            } else {
                $bloom_range = [4, 6]; // Deboli = tendono a sovrastimarsi (Dunning-Kruger)
            }
        }

        $user_created = 0;

        foreach ($competencies as $comp) {
            // Verifica se esiste già autovalutazione
            $existing = $DB->get_record('local_selfassessment', [
                'userid' => $user->userid,
                'competencyid' => $comp->id
            ]);

            if ($existing) {
                $skipped++;
                continue;
            }

            // Genera livello Bloom
            $bloom_level = mt_rand($bloom_range[0], $bloom_range[1]);

            // Crea autovalutazione
            $sa = new stdClass();
            $sa->userid = $user->userid;
            $sa->competencyid = $comp->id;
            $sa->level = $bloom_level;
            $sa->timecreated = time();
            $sa->timemodified = time();
            $DB->insert_record('local_selfassessment', $sa);
            $created_sa++;
            $user_created++;

            // Crea anche assignment se non esiste
            $existing_assign = $DB->get_record('local_selfassessment_assign', [
                'userid' => $user->userid,
                'competencyid' => $comp->id
            ]);

            if (!$existing_assign) {
                $assign = new stdClass();
                $assign->userid = $user->userid;
                $assign->competencyid = $comp->id;
                $assign->source = 'testsuite';
                $assign->sourceid = 0;
                $assign->timecreated = time();
                $DB->insert_record('local_selfassessment_assign', $assign);
                $created_assign++;
            }
        }

        $log[] = "   <span style='color: #28a745;'>✅ Create {$user_created} autovalutazioni (Bloom {$bloom_range[0]}-{$bloom_range[1]})</span>";
    }

    $log[] = "";
    $log[] = "<span style='color: #ffc107;'>═══════════════════════════════════════</span>";
    $log[] = "<span style='color: #28a745;'>✅ RIEPILOGO:</span>";
    $log[] = "   • Autovalutazioni create: <strong>{$created_sa}</strong>";
    $log[] = "   • Assegnazioni create: <strong>{$created_assign}</strong>";
    $log[] = "   • Saltate (già esistenti): <strong>{$skipped}</strong>";

    foreach ($log as $l) {
        echo $l . "<br>";
    }

    echo '</div>';
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
