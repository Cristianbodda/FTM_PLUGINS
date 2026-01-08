<?php
// Debug attivo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Audit Competenze</h1>";

echo "<h2>1. Config</h2>";
try {
    require_once(__DIR__ . '/../../config.php');
    echo "<p style='color:green'>✅ config.php OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    die();
}

echo "<h2>2. Parametri</h2>";
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', 'dashboard', PARAM_ALPHANUMEXT);
echo "<p>courseid: $courseid</p>";
echo "<p>action: $action</p>";

if (!$courseid) {
    echo "<p style='color:red'>❌ Manca courseid!</p>";
    die();
}

echo "<h2>3. Corso</h2>";
try {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    echo "<p style='color:green'>✅ Corso: " . $course->fullname . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    die();
}

echo "<h2>4. Login</h2>";
try {
    require_login($course);
    echo "<p style='color:green'>✅ Login OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    die();
}

echo "<h2>5. Context</h2>";
try {
    $context = context_course::instance($courseid);
    echo "<p style='color:green'>✅ Context: " . $context->id . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    die();
}

echo "<h2>6. Quiz</h2>";
try {
    $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
    echo "<p style='color:green'>✅ Quiz trovati: " . count($quizzes) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
}

echo "<h2>7. Framework</h2>";
try {
    $frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');
    echo "<p style='color:green'>✅ Framework trovati: " . count($frameworks) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
}

echo "<h2>8. Test funzione checklist</h2>";
try {
    // Test semplice
    $total_questions = $DB->count_records_sql("
        SELECT COUNT(DISTINCT q.id)
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
    ", [$context->id]);
    echo "<p style='color:green'>✅ Domande totali: $total_questions</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
}

echo "<h2>✅ Debug completato</h2>";
echo "<p><a href='audit_competenze.php?courseid=$courseid'>Prova audit_competenze.php</a></p>";
