<?php
/**
 * Diagnostic tool for quiz selector issues
 * Usage: diagnose_quiz_selector.php?userid=X
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/competencymanager:manage', $context);

$userid = required_param('userid', PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_quiz_selector.php'));
$PAGE->set_title('Diagnosi Selettore Quiz');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo '<h2>Diagnosi Selettore Quiz - Utente ID: ' . $userid . '</h2>';

$user = $DB->get_record('user', ['id' => $userid]);
if ($user) {
    echo '<p><strong>Utente:</strong> ' . fullname($user) . ' (' . $user->email . ')</p>';
}

// Style
echo '<style>
.diag-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
.diag-section h3 { margin-top: 0; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
.diag-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.diag-table th, .diag-table td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
.diag-table th { background: #f5f5f5; }
.diag-ok { color: #28a745; font-weight: bold; }
.diag-warn { color: #ffc107; font-weight: bold; }
.diag-error { color: #dc3545; font-weight: bold; }
.diag-info { background: #e7f3ff; padding: 15px; border-radius: 6px; margin: 10px 0; }
</style>';

// 1. ALL quiz attempts by this user (regardless of course)
echo '<div class="diag-section">';
echo '<h3>1. TUTTI i Quiz Attempts dello Studente (qualsiasi corso)</h3>';

$allAttempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.state, qa.timefinish, qa.sumgrades,
           q.name as quizname, q.course as quizcourse,
           c.shortname as courseshortname, c.fullname as coursefullname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    WHERE qa.userid = ?
    ORDER BY qa.timefinish DESC
", [$userid]);

if (empty($allAttempts)) {
    echo '<p class="diag-error">NESSUN tentativo quiz trovato per questo utente!</p>';
} else {
    echo '<p>Trovati <strong>' . count($allAttempts) . '</strong> tentativi totali.</p>';
    echo '<table class="diag-table">';
    echo '<tr><th>Attempt ID</th><th>Quiz</th><th>Quiz ID</th><th>Corso</th><th>Course ID</th><th>Stato</th><th>Data Fine</th><th>Link Report</th></tr>';

    foreach ($allAttempts as $a) {
        $stateClass = ($a->state === 'finished') ? 'diag-ok' : 'diag-warn';
        $dateStr = $a->timefinish ? date('d/m/Y H:i', $a->timefinish) : '-';

        // Build report link with correct courseid
        $reportUrl = new moodle_url('/local/competencymanager/student_report.php', [
            'userid' => $userid,
            'courseid' => $a->quizcourse
        ]);

        echo '<tr>';
        echo '<td>' . $a->id . '</td>';
        echo '<td><strong>' . s($a->quizname) . '</strong></td>';
        echo '<td>' . $a->quiz . '</td>';
        echo '<td>' . s($a->courseshortname) . '</td>';
        echo '<td class="diag-ok"><strong>' . $a->quizcourse . '</strong></td>';
        echo '<td class="' . $stateClass . '">' . $a->state . '</td>';
        echo '<td>' . $dateStr . '</td>';
        echo '<td><a href="' . $reportUrl . '" target="_blank">Apri Report</a></td>';
        echo '</tr>';
    }
    echo '</table>';

    // Summary by course
    echo '<h4>Riepilogo per Corso:</h4>';
    $courseStats = [];
    foreach ($allAttempts as $a) {
        $key = $a->quizcourse;
        if (!isset($courseStats[$key])) {
            $courseStats[$key] = [
                'courseid' => $a->quizcourse,
                'name' => $a->courseshortname,
                'fullname' => $a->coursefullname,
                'total' => 0,
                'finished' => 0
            ];
        }
        $courseStats[$key]['total']++;
        if ($a->state === 'finished') {
            $courseStats[$key]['finished']++;
        }
    }

    echo '<table class="diag-table">';
    echo '<tr><th>Course ID</th><th>Corso</th><th>Tentativi Totali</th><th>Completati</th><th>Link Corretto</th></tr>';
    foreach ($courseStats as $cs) {
        $reportUrl = new moodle_url('/local/competencymanager/student_report.php', [
            'userid' => $userid,
            'courseid' => $cs['courseid']
        ]);
        echo '<tr>';
        echo '<td class="diag-ok"><strong>' . $cs['courseid'] . '</strong></td>';
        echo '<td>' . s($cs['name']) . ' - ' . s($cs['fullname']) . '</td>';
        echo '<td>' . $cs['total'] . '</td>';
        echo '<td>' . $cs['finished'] . '</td>';
        echo '<td><a href="' . $reportUrl . '" class="btn btn-sm btn-primary" target="_blank">APRI REPORT con courseid=' . $cs['courseid'] . '</a></td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

// 2. Quiz attempts with competency data
echo '<div class="diag-section">';
echo '<h3>2. Quiz con Competenze Assegnate alle Domande</h3>';

$quizWithComps = $DB->get_records_sql("
    SELECT DISTINCT qa.quiz, q.name as quizname, q.course, c.shortname as coursename,
           (SELECT COUNT(DISTINCT qcbq.id)
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = qv.questionid
            WHERE qs.quizid = q.id) as comp_count,
           (SELECT COUNT(*) FROM {quiz_slots} qs2 WHERE qs2.quizid = q.id) as question_count
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    WHERE qa.userid = ?
    AND qa.state = 'finished'
    ORDER BY q.name
", [$userid]);

if (!empty($quizWithComps)) {
    echo '<table class="diag-table">';
    echo '<tr><th>Quiz</th><th>Corso</th><th>Domande</th><th>Con Competenza</th><th>Copertura</th></tr>';

    foreach ($quizWithComps as $qw) {
        $coverage = $qw->question_count > 0 ? round(($qw->comp_count / $qw->question_count) * 100) : 0;
        $coverageClass = $coverage >= 80 ? 'diag-ok' : ($coverage >= 50 ? 'diag-warn' : 'diag-error');

        echo '<tr>';
        echo '<td><strong>' . s($qw->quizname) . '</strong></td>';
        echo '<td>' . s($qw->coursename) . ' (ID: ' . $qw->course . ')</td>';
        echo '<td>' . $qw->question_count . '</td>';
        echo '<td>' . $qw->comp_count . '</td>';
        echo '<td class="' . $coverageClass . '">' . $coverage . '%</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="diag-warn">Nessun quiz completato trovato.</p>';
}
echo '</div>';

// 3. What get_available_quizzes returns for each course
echo '<div class="diag-section">';
echo '<h3>3. Test Funzione get_available_quizzes() per ogni Corso</h3>';

require_once(__DIR__ . '/classes/report_generator.php');

$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname, c.fullname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    WHERE qa.userid = ?
    AND qa.state = 'finished'
", [$userid]);

if (!empty($courses)) {
    foreach ($courses as $course) {
        echo '<h4>Corso: ' . s($course->shortname) . ' (ID: ' . $course->id . ')</h4>';

        $availableQuizzes = \local_competencymanager\report_generator::get_available_quizzes($userid, $course->id);

        if (empty($availableQuizzes)) {
            echo '<p class="diag-error">get_available_quizzes() ritorna VUOTO per questo corso!</p>';
        } else {
            echo '<p class="diag-ok">Trovati ' . count($availableQuizzes) . ' quiz disponibili:</p>';
            echo '<ul>';
            foreach ($availableQuizzes as $q) {
                echo '<li>' . s($q->name) . ' (ID: ' . $q->id . ', Tentativi: ' . $q->attempts . ')</li>';
            }
            echo '</ul>';
        }
    }
} else {
    echo '<p class="diag-error">Nessun corso con quiz completati.</p>';
}
echo '</div>';

// 4. SOLUZIONE
echo '<div class="diag-section">';
echo '<h3>4. SOLUZIONE - Link Corretti al Report</h3>';
echo '<div class="diag-info">';
echo '<p><strong>Il problema:</strong> Il selettore quiz filtra per <code>courseid</code>. Se apri il report con un courseid sbagliato, il quiz non appare.</p>';
echo '<p><strong>La soluzione:</strong> Usa i link qui sotto che hanno il <code>courseid</code> corretto:</p>';
echo '</div>';

if (!empty($courseStats)) {
    foreach ($courseStats as $cs) {
        if ($cs['finished'] > 0) {
            $reportUrl = new moodle_url('/local/competencymanager/student_report.php', [
                'userid' => $userid,
                'courseid' => $cs['courseid']
            ]);
            echo '<p><a href="' . $reportUrl . '" class="btn btn-primary" target="_blank">';
            echo 'Report per ' . s($cs['name']) . ' (courseid=' . $cs['courseid'] . ', ' . $cs['finished'] . ' quiz completati)';
            echo '</a></p>';
        }
    }
}
echo '</div>';

echo $OUTPUT->footer();
