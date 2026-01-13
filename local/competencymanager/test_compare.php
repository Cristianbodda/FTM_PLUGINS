<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

// Quiz results
$quizResults = \local_competencymanager\report_generator::get_student_competency_scores(3, 12);
echo "Quiz Results keys (primi 5): " . implode(", ", array_slice(array_keys($quizResults), 0, 5)) . "\n";

// Competenze dal corso
$sql = "SELECT DISTINCT c.id, c.idnumber
        FROM {competency} c
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
        JOIN {question_versions} qv ON qv.questionid = qbc.questionid
        JOIN {question_references} qr ON qr.questionbankentryid = qv.questionbankentryid
        JOIN {quiz_slots} qs ON qs.id = qr.itemid
        JOIN {quiz} q ON q.id = qs.quizid
        WHERE q.course = 12 AND qr.component = 'mod_quiz'
        ORDER BY c.idnumber LIMIT 5";
$competencies = $DB->get_records_sql($sql);
echo "Competenze corso 12 (primi 5):\n";
foreach ($competencies as $c) {
    echo "  ID: {$c->id}, idnumber: {$c->idnumber}\n";
    echo "  -> Esiste in quizResults? " . (isset($quizResults[$c->id]) ? "SI" : "NO") . "\n";
}
