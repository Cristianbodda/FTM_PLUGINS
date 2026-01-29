<?php
// ============================================
// DEBUG SPECIFICO: Quiz CHIMFARM di Fabio
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/debug_chimfarm.php'));
$PAGE->set_title('Debug CHIMFARM Fabio');

echo $OUTPUT->header();

echo '<h1>🧪 Debug Quiz CHIMFARM - Fabio Marinoni (ID 82)</h1>';

// Fabio ID
$userid = 82;
$courseid = 12; // Chimica 23

echo '<h2>1. Tentativi CHIMFARM di Fabio</h2>';

$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.state, qa.timefinish, qa.uniqueid,
           q.name as quizname, q.course
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ?
    AND q.course = ?
    ORDER BY qa.timefinish DESC
", [$userid, $courseid]);

if (empty($attempts)) {
    echo '<p style="color: red; font-weight: bold;">❌ NESSUN tentativo trovato per Fabio nel corso 12!</p>';

    // Debug: cerca tutti i tentativi di Fabio
    echo '<h3>Debug: Tutti i tentativi di Fabio (qualsiasi corso)</h3>';
    $all_attempts = $DB->get_records_sql("
        SELECT qa.id, qa.quiz, qa.state, qa.timefinish, q.name, q.course
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
        WHERE qa.userid = ?
        ORDER BY qa.timefinish DESC
        LIMIT 20
    ", [$userid]);

    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Attempt ID</th><th>Quiz</th><th>Course ID</th><th>Data</th></tr>';
    foreach ($all_attempts as $a) {
        $date = date('d/m H:i', $a->timefinish);
        $highlight = $a->course == 12 ? 'background: yellow;' : '';
        echo "<tr style='{$highlight}'><td>{$a->id}</td><td>{$a->name}</td><td>{$a->course}</td><td>{$date}</td></tr>";
    }
    echo '</table>';

} else {
    echo '<p style="color: green;">✓ Trovati ' . count($attempts) . ' tentativi</p>';

    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr style="background: #f0f0f0;"><th>Attempt ID</th><th>Quiz</th><th>UniqueID</th><th>Stato</th><th>Data</th></tr>';

    foreach ($attempts as $att) {
        $date = date('d/m/Y H:i', $att->timefinish);
        echo "<tr>";
        echo "<td><strong>{$att->id}</strong></td>";
        echo "<td>{$att->quizname}</td>";
        echo "<td>{$att->uniqueid}</td>";
        echo "<td>{$att->state}</td>";
        echo "<td>{$date}</td>";
        echo "</tr>";
    }
    echo '</table>';

    // Analizza il primo tentativo
    $first_attempt = reset($attempts);

    echo '<h2>2. Analisi Attempt ID: ' . $first_attempt->id . '</h2>';

    // Domande
    $questions = $DB->get_records_sql("
        SELECT DISTINCT qa.questionid, q.name, q.qtype
        FROM {question_attempts} qa
        JOIN {question} q ON q.id = qa.questionid
        WHERE qa.questionusageid = ?
    ", [$first_attempt->uniqueid]);

    echo '<h3>Domande nel tentativo: ' . count($questions) . '</h3>';

    if (!empty($questions)) {
        $questionids = array_keys($questions);

        // Competenze mappate
        $dbman = $DB->get_manager();
        $comp_table = $dbman->table_exists('qbank_competenciesbyquestion') ? 'qbank_competenciesbyquestion' : null;

        if ($comp_table) {
            list($sql_in, $params) = $DB->get_in_or_equal($questionids);
            $mappings = $DB->get_records_sql("
                SELECT DISTINCT m.questionid, m.competencyid, c.idnumber
                FROM {{$comp_table}} m
                JOIN {competency} c ON c.id = m.competencyid
                WHERE m.questionid $sql_in
            ", $params);

            echo '<h3>Competenze mappate: ' . count($mappings) . '</h3>';

            if (empty($mappings)) {
                echo '<p style="color: red; font-weight: bold;">❌ NESSUNA COMPETENZA MAPPATA!</p>';
                echo '<p>Le domande di questo quiz NON hanno competenze associate. Questo è il problema!</p>';

                echo '<h4>Question IDs nel quiz:</h4>';
                echo '<pre>' . implode(', ', $questionids) . '</pre>';

                // Verifica se queste question hanno mapping in generale
                echo '<h4>Verifica mapping per ogni domanda:</h4>';
                foreach ($questionids as $qid) {
                    $count = $DB->count_records($comp_table, ['questionid' => $qid]);
                    echo "Question {$qid}: " . ($count > 0 ? "✓ {$count}" : "✗ 0") . " competenze<br>";
                }
            } else {
                echo '<table border="1" cellpadding="5">';
                echo '<tr><th>Question ID</th><th>Competency ID</th><th>IDNumber</th></tr>';
                foreach ($mappings as $m) {
                    echo "<tr><td>{$m->questionid}</td><td>{$m->competencyid}</td><td>{$m->idnumber}</td></tr>";
                }
                echo '</table>';
            }
        } else {
            echo '<p style="color: red;">Tabella mapping competenze non trovata!</p>';
        }
    }

    // Assegnazioni esistenti per Fabio
    echo '<h2>3. Assegnazioni Autovalutazione per Fabio</h2>';

    $assignments = $DB->get_records_sql("
        SELECT a.id, a.competencyid, a.source, a.timecreated, c.idnumber
        FROM {local_selfassessment_assign} a
        JOIN {competency} c ON c.id = a.competencyid
        WHERE a.userid = ?
        AND c.idnumber LIKE 'CHIMFARM%'
        ORDER BY a.timecreated DESC
    ", [$userid]);

    echo '<p>Assegnazioni CHIMFARM: <strong>' . count($assignments) . '</strong></p>';

    if (!empty($assignments)) {
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>ID</th><th>Competenza</th><th>Sorgente</th><th>Data</th></tr>';
        foreach ($assignments as $a) {
            $date = date('d/m/Y H:i', $a->timecreated);
            echo "<tr><td>{$a->id}</td><td>{$a->idnumber}</td><td>{$a->source}</td><td>{$date}</td></tr>";
        }
        echo '</table>';
    } else {
        echo '<p style="color: red;">Nessuna assegnazione CHIMFARM trovata per Fabio!</p>';
    }
}

// Verifica corso 12
echo '<h2>4. Verifica Corso ID 12</h2>';
$course = $DB->get_record('course', ['id' => 12]);
if ($course) {
    echo "<p>✓ Corso trovato: <strong>{$course->fullname}</strong> (shortname: {$course->shortname})</p>";
} else {
    echo '<p style="color: red;">❌ Corso ID 12 NON TROVATO!</p>';
}

// Quiz nel corso 12
$quizzes = $DB->get_records_sql("
    SELECT q.id, q.name
    FROM {quiz} q
    WHERE q.course = 12
");
echo '<p>Quiz nel corso 12: <strong>' . count($quizzes) . '</strong></p>';
foreach ($quizzes as $q) {
    echo "• {$q->name} (ID: {$q->id})<br>";
}

echo $OUTPUT->footer();
