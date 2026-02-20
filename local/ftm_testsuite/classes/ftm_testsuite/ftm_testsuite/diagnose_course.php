<?php
/**
 * Diagnostica Corso - Verifica competenze e quiz
 *
 * @package    local_ftm_testsuite
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/diagnose_course.php'));
$PAGE->set_title('Diagnostica Corso');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px; font-family: monospace;">';
echo '<h1>🔍 Diagnostica Corso e Competenze</h1>';

global $DB;

// 1. Lista corsi con quiz
echo '<h2>1. Corsi con Quiz</h2>';
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, COUNT(q.id) as quiz_count
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
    LIMIT 20
");

echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
echo '<tr><th>ID</th><th>Nome</th><th>Quiz</th><th>Azione</th></tr>';
foreach ($courses as $c) {
    $selected = ($c->id == $courseid) ? 'style="background: #ffffcc;"' : '';
    echo "<tr {$selected}><td>{$c->id}</td><td>{$c->fullname}</td><td>{$c->quiz_count}</td>";
    echo '<td><a href="?courseid=' . $c->id . '">Analizza</a></td></tr>';
}
echo '</table>';

if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    echo '<h2>2. Analisi Corso: ' . s($course->fullname) . '</h2>';

    // 2a. Quiz nel corso
    echo '<h3>2a. Quiz nel corso</h3>';
    $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
    echo '<p>Trovati <strong>' . count($quizzes) . '</strong> quiz</p>';

    foreach ($quizzes as $quiz) {
        echo '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px;">';
        echo '<strong>' . s($quiz->name) . '</strong> (ID: ' . $quiz->id . ')';

        // Conta domande
        $q_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qv.questionid)
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            WHERE qs.quizid = ?
        ", [$quiz->id]);

        // Conta domande con competenze
        $qc_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qv.questionid)
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            WHERE qs.quizid = ?
        ", [$quiz->id]);

        $pct = $q_count > 0 ? round(($qc_count / $q_count) * 100) : 0;
        $color = $pct == 100 ? 'green' : ($pct > 0 ? 'orange' : 'red');

        echo " - <strong>{$q_count}</strong> domande, <span style='color: {$color};'><strong>{$qc_count}</strong> con competenze ({$pct}%)</span>";
        echo '</div>';
    }

    // 2b. Framework disponibili
    echo '<h3>2b. Framework Competenze</h3>';
    $frameworks = $DB->get_records_sql("
        SELECT cf.id, cf.shortname, cf.idnumber, COUNT(c.id) as comp_count
        FROM {competency_framework} cf
        LEFT JOIN {competency} c ON c.competencyframeworkid = cf.id
        GROUP BY cf.id, cf.shortname, cf.idnumber
        ORDER BY cf.shortname
    ");

    if (empty($frameworks)) {
        echo '<p style="color: red;">❌ NESSUN FRAMEWORK TROVATO!</p>';
    } else {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr><th>ID</th><th>Nome</th><th>IDNumber</th><th>Competenze</th></tr>';
        foreach ($frameworks as $f) {
            echo "<tr><td>{$f->id}</td><td>{$f->shortname}</td><td>{$f->idnumber}</td><td>{$f->comp_count}</td></tr>";
        }
        echo '</table>';
    }

    // 2c. Competenze con pattern ELETTRIC
    echo '<h3>2c. Competenze con "ELETTRIC" nel idnumber</h3>';
    $elettric_comps = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname, cf.shortname as framework
        FROM {competency} c
        JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
        WHERE c.idnumber LIKE '%ELETTRIC%'
        ORDER BY c.idnumber
        LIMIT 20
    ");

    if (empty($elettric_comps)) {
        echo '<p style="color: orange;">⚠️ Nessuna competenza con "ELETTRIC" nel idnumber</p>';
    } else {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr><th>ID</th><th>IDNumber</th><th>Nome</th><th>Framework</th></tr>';
        foreach ($elettric_comps as $c) {
            echo "<tr><td>{$c->id}</td><td>{$c->idnumber}</td><td>" . s(substr($c->shortname, 0, 50)) . "</td><td>{$c->framework}</td></tr>";
        }
        echo '</table>';
    }

    // 2d. Esempio prime 5 domande del primo quiz
    if (!empty($quizzes)) {
        $first_quiz = reset($quizzes);
        echo '<h3>2d. Prime 5 domande del quiz "' . s($first_quiz->name) . '"</h3>';

        $questions = $DB->get_records_sql("
            SELECT qv.questionid, q.name, q.questiontext, qc.competencyid, c.idnumber as comp_idnumber
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            LEFT JOIN {competency} c ON c.id = qc.competencyid
            WHERE qs.quizid = ?
            ORDER BY qs.slot
            LIMIT 5
        ", [$first_quiz->id]);

        foreach ($questions as $q) {
            echo '<div style="background: #e8f4e8; padding: 10px; margin: 10px 0; border-radius: 5px; border-left: 4px solid ' . ($q->competencyid ? 'green' : 'red') . ';">';
            echo '<strong>Q' . $q->questionid . ':</strong> ' . s(substr($q->name, 0, 80)) . '<br>';
            echo '<small>Testo: ' . s(substr(strip_tags($q->questiontext), 0, 100)) . '...</small><br>';
            if ($q->competencyid) {
                echo '<span style="color: green;">✅ Competenza: ' . $q->comp_idnumber . '</span>';
            } else {
                echo '<span style="color: red;">❌ Nessuna competenza assegnata</span>';
            }
            echo '</div>';
        }
    }

    // 2e. Domande nella Question Bank del corso (non necessariamente nei quiz)
    echo '<h3>2e. Domande nella Question Bank del corso</h3>';

    $context = context_course::instance($courseid);
    $qbank_questions = $DB->get_records_sql("
        SELECT q.id, q.name, qbe.id as entryid, qc.name as category_name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        ORDER BY q.name
        LIMIT 20
    ", [$context->id]);

    if (empty($qbank_questions)) {
        echo '<p style="color: red;">❌ NESSUNA DOMANDA nella Question Bank di questo corso!</p>';
        echo '<p>Le domande potrebbero essere in una categoria di contesto diverso (sistema o altra categoria).</p>';

        // Cerca domande con "elettric" nel nome ovunque
        echo '<h4>Ricerca domande con "elettric" nel nome (tutto il sistema)</h4>';
        $elettric_questions = $DB->get_records_sql("
            SELECT q.id, q.name, qc.name as category_name, qc.contextid
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE q.name LIKE '%elettric%' OR q.name LIKE '%ELETTRIC%'
            ORDER BY q.name
            LIMIT 20
        ");

        if (empty($elettric_questions)) {
            echo '<p style="color: red;">❌ Nessuna domanda con "elettric" trovata nel sistema!</p>';
        } else {
            echo '<p>Trovate ' . count($elettric_questions) . ' domande:</p>';
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
            echo '<tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Context</th></tr>';
            foreach ($elettric_questions as $eq) {
                echo "<tr><td>{$eq->id}</td><td>" . s(substr($eq->name, 0, 60)) . "</td><td>{$eq->category_name}</td><td>{$eq->contextid}</td></tr>";
            }
            echo '</table>';
        }
    } else {
        echo '<p>Trovate <strong>' . count($qbank_questions) . '</strong> domande nella Question Bank:</p>';
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr><th>ID</th><th>Nome</th><th>Categoria</th></tr>';
        foreach (array_slice($qbank_questions, 0, 10) as $q) {
            echo "<tr><td>{$q->id}</td><td>" . s(substr($q->name, 0, 60)) . "</td><td>{$q->category_name}</td></tr>";
        }
        echo '</table>';
    }

    // 2f. Tabella qbank_competenciesbyquestion
    echo '<h3>2f. Record in qbank_competenciesbyquestion per questo corso</h3>';
    $qbc_count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {qbank_competenciesbyquestion} qc
        JOIN {question_versions} qv ON qv.questionid = qc.questionid
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
            AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
        JOIN {quiz_slots} qs ON qs.id = qr.itemid
        JOIN {quiz} q ON q.id = qs.quizid
        WHERE q.course = ?
    ", [$courseid]);

    echo '<p>Record trovati: <strong>' . $qbc_count . '</strong></p>';
}

echo '</div>';
echo $OUTPUT->footer();
