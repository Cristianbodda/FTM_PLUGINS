<?php
// ============================================
// CATCHUP TEST USERS
// Assegna retroattivamente le competenze agli
// utenti di test che hanno completato quiz
// prima che l'observer fosse corretto
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/catchup_test_users.php'));
$PAGE->set_title('Catchup Test Users');

// Utenti di test da processare
$test_usernames = ['fabio.marinoni', 'fmarinoni', 'fabio', 'roberto', 'alessandra', 'sandra', 'francesco'];

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

echo $OUTPUT->header();
echo '<h1>Catchup Competenze - Utenti Test</h1>';

// Trova tabella competenze-domande
$comp_tables = [
    'qbank_competenciesbyquestion' => 'questionid',
    'local_competencymanager_qcomp' => 'questionid'
];

$comp_question_table = null;
$comp_question_field = null;

foreach ($comp_tables as $table => $field) {
    if ($DB->get_manager()->table_exists($table)) {
        $comp_question_table = $table;
        $comp_question_field = $field;
        break;
    }
}

if (!$comp_question_table) {
    echo '<p style="color: red;">Nessuna tabella di mapping competenze-domande trovata!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo "<p>Tabella mapping: <strong>{$comp_question_table}</strong></p>";

// Trova utenti di test
$users = [];
foreach ($test_usernames as $uname) {
    // Cerca per username esatto o parziale
    $found = $DB->get_records_sql("
        SELECT * FROM {user}
        WHERE username LIKE ?
        OR firstname LIKE ?
        OR lastname LIKE ?
        LIMIT 10
    ", ["%{$uname}%", "%{$uname}%", "%{$uname}%"]);

    foreach ($found as $u) {
        if (!isset($users[$u->id])) {
            $users[$u->id] = $u;
        }
    }
}

// Azione: processa singolo utente
if ($action === 'process' && $userid > 0) {
    echo '<div style="background: #d1ecf1; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo "<h3>Elaborazione utente ID: {$userid}</h3>";

    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        echo '<p style="color: red;">Utente non trovato!</p>';
    } else {
        echo "<p>Utente: <strong>" . fullname($user) . "</strong> ({$user->username})</p>";

        // Trova tutti i quiz completati
        $attempts = $DB->get_records_sql("
            SELECT qa.*, q.name as quizname, q.id as quizid
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = ?
            AND qa.state = 'finished'
            ORDER BY qa.timefinish DESC
        ", [$userid]);

        echo "<p>Quiz completati: " . count($attempts) . "</p>";

        $total_assigned = 0;
        $now = time();

        foreach ($attempts as $attempt) {
            // Trova domande del quiz
            $questions = $DB->get_records_sql("
                SELECT DISTINCT qa.questionid
                FROM {question_attempts} qa
                WHERE qa.questionusageid = ?
            ", [$attempt->uniqueid]);

            if (empty($questions)) {
                continue;
            }

            $questionids = array_keys($questions);

            // Trova competenze associate
            list($sql_in, $params) = $DB->get_in_or_equal($questionids);
            $mappings = $DB->get_records_sql("
                SELECT DISTINCT competencyid
                FROM {{$comp_question_table}}
                WHERE {$comp_question_field} $sql_in
            ", $params);

            if (empty($mappings)) {
                continue;
            }

            $quiz_assigned = 0;
            foreach ($mappings as $mapping) {
                // Verifica se già assegnata
                $exists = $DB->record_exists('local_selfassessment_assign', [
                    'userid' => $userid,
                    'competencyid' => $mapping->competencyid
                ]);

                if (!$exists) {
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->competencyid = $mapping->competencyid;
                    $record->source = 'quiz';
                    $record->sourceid = $attempt->quiz;
                    $record->timecreated = $now;

                    try {
                        $DB->insert_record('local_selfassessment_assign', $record);
                        $quiz_assigned++;
                        $total_assigned++;
                    } catch (Exception $e) {
                        // Ignora duplicati
                    }
                }
            }

            if ($quiz_assigned > 0) {
                echo "<p>✅ Quiz \"{$attempt->quizname}\": {$quiz_assigned} competenze assegnate</p>";
            }
        }

        echo "<br><strong style='color: green; font-size: 1.2em;'>Totale nuove assegnazioni: {$total_assigned}</strong>";
    }

    echo '</div>';
    echo '<p><a href="catchup_test_users.php" style="background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">← Torna alla lista</a></p>';
}

// Azione: processa TUTTI
if ($action === 'processall') {
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo "<h3>Elaborazione TUTTI gli utenti di test</h3>";

    $grand_total = 0;
    $now = time();

    foreach ($users as $user) {
        echo "<hr><h4>" . fullname($user) . " ({$user->username})</h4>";

        // Trova tutti i quiz completati
        $attempts = $DB->get_records_sql("
            SELECT qa.*, q.name as quizname, q.id as quizid
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = ?
            AND qa.state = 'finished'
            ORDER BY qa.timefinish DESC
        ", [$user->id]);

        if (empty($attempts)) {
            echo "<p>Nessun quiz completato</p>";
            continue;
        }

        $user_assigned = 0;

        foreach ($attempts as $attempt) {
            $questions = $DB->get_records_sql("
                SELECT DISTINCT qa.questionid
                FROM {question_attempts} qa
                WHERE qa.questionusageid = ?
            ", [$attempt->uniqueid]);

            if (empty($questions)) continue;

            $questionids = array_keys($questions);
            list($sql_in, $params) = $DB->get_in_or_equal($questionids);
            $mappings = $DB->get_records_sql("
                SELECT DISTINCT competencyid
                FROM {{$comp_question_table}}
                WHERE {$comp_question_field} $sql_in
            ", $params);

            if (empty($mappings)) continue;

            foreach ($mappings as $mapping) {
                $exists = $DB->record_exists('local_selfassessment_assign', [
                    'userid' => $user->id,
                    'competencyid' => $mapping->competencyid
                ]);

                if (!$exists) {
                    $record = new stdClass();
                    $record->userid = $user->id;
                    $record->competencyid = $mapping->competencyid;
                    $record->source = 'quiz';
                    $record->sourceid = $attempt->quiz;
                    $record->timecreated = $now;

                    try {
                        $DB->insert_record('local_selfassessment_assign', $record);
                        $user_assigned++;
                        $grand_total++;
                    } catch (Exception $e) {
                        // Ignora
                    }
                }
            }
        }

        echo "<p>Nuove assegnazioni: <strong>{$user_assigned}</strong></p>";
    }

    echo "<hr><h3 style='color: green;'>TOTALE GENERALE: {$grand_total} competenze assegnate</h3>";
    echo '</div>';
    echo '<p><a href="catchup_test_users.php" style="background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">← Torna alla lista</a></p>';
}

// Mostra lista utenti
if (empty($action)) {
    echo '<h2>Utenti Trovati</h2>';

    if (empty($users)) {
        echo '<p>Nessun utente di test trovato.</p>';
    } else {
        echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
        echo '<tr style="background: #f8f9fa;"><th>ID</th><th>Username</th><th>Nome</th><th>Email</th><th>Quiz Completati</th><th>Competenze Assegnate</th><th>Pending</th><th>Azioni</th></tr>';

        foreach ($users as $user) {
            // Conta quiz completati
            $quiz_count = $DB->count_records_sql("
                SELECT COUNT(*) FROM {quiz_attempts}
                WHERE userid = ? AND state = 'finished'
            ", [$user->id]);

            // Conta competenze assegnate
            $assigned_count = $DB->count_records('local_selfassessment_assign', ['userid' => $user->id]);

            // Conta completate
            $completed_count = $DB->count_records('local_selfassessment', ['userid' => $user->id]);

            // Calcola pending
            $pending = $assigned_count - $completed_count;
            if ($pending < 0) $pending = 0;

            $row_style = $assigned_count == 0 && $quiz_count > 0 ? 'background: #fff3cd;' : '';

            echo "<tr style='{$row_style}'>";
            echo "<td>{$user->id}</td>";
            echo "<td>{$user->username}</td>";
            echo "<td>" . fullname($user) . "</td>";
            echo "<td>{$user->email}</td>";
            echo "<td>{$quiz_count}</td>";
            echo "<td>{$assigned_count}</td>";
            echo "<td>{$pending}</td>";
            echo "<td>";
            echo "<a href='?action=process&userid={$user->id}' style='background: #28a745; color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none; margin-right: 5px;'>Processa</a>";
            echo "<a href='diagnose_critest.php?userid={$user->id}' style='background: #17a2b8; color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none;'>Diagnosi</a>";
            echo "</td>";
            echo "</tr>";
        }

        echo '</table>';

        echo '<div style="margin-top: 30px;">';
        echo '<a href="?action=processall" style="background: #dc3545; color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-size: 1.2em; font-weight: bold;">⚡ PROCESSA TUTTI GLI UTENTI</a>';
        echo '</div>';
    }
}

// Link utili
echo '<h2 style="margin-top: 40px;">Link Utili</h2>';
echo '<p>';
echo '<a href="diagnose_critest.php" style="margin-right: 15px;">Diagnosi Critest</a>';
echo '<a href="fix_observer.php" style="margin-right: 15px;">Fix Observer</a>';
echo '<a href="' . $CFG->wwwroot . '/admin/purgecaches.php">Purge Caches</a>';
echo '</p>';

echo $OUTPUT->footer();
