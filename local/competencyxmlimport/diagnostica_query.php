<?php
// Abilita tutti gli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Diagnostica</h1>";
echo "<p>Se vedi questo, PHP funziona.</p>";

// Test 1: config.php
echo "<h2>1. Caricamento config.php</h2>";
try {
    require_once(__DIR__ . '/../../config.php');
    echo "<p style='color:green;'>✅ config.php caricato</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
    die();
}

// Test 2: parametro courseid
echo "<h2>2. Parametro courseid</h2>";
$courseid = optional_param('courseid', 0, PARAM_INT);
echo "<p>courseid = $courseid</p>";

if ($courseid == 0) {
    echo "<p style='color:red;'>❌ Manca courseid! Usa: ?courseid=8</p>";
    die();
}

// Test 3: corso esiste
echo "<h2>3. Verifica corso</h2>";
try {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    echo "<p style='color:green;'>✅ Corso trovato: " . $course->fullname . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Corso non trovato: " . $e->getMessage() . "</p>";
    die();
}

// Test 4: login
echo "<h2>4. Login</h2>";
try {
    require_login($course);
    echo "<p style='color:green;'>✅ Login OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore login: " . $e->getMessage() . "</p>";
    die();
}

// Test 5: context
echo "<h2>5. Context</h2>";
try {
    $context = context_course::instance($courseid);
    echo "<p style='color:green;'>✅ Context ID: " . $context->id . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore context: " . $e->getMessage() . "</p>";
    die();
}

// Test 6: capability
echo "<h2>6. Capability</h2>";
try {
    require_capability('moodle/course:manageactivities', $context);
    echo "<p style='color:green;'>✅ Hai i permessi</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Permessi mancanti: " . $e->getMessage() . "</p>";
    die();
}

// Test 7: Query base
echo "<h2>7. Query Quiz</h2>";
try {
    $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
    echo "<p style='color:green;'>✅ Quiz trovati: " . count($quizzes) . "</p>";
    foreach ($quizzes as $q) {
        echo "<li>{$q->id} - {$q->name}</li>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore query: " . $e->getMessage() . "</p>";
}

// Test 8: Tabella qbank_competenciesbyquestion
echo "<h2>8. Tabella competenze</h2>";
try {
    $count = $DB->count_records('qbank_competenciesbyquestion');
    echo "<p style='color:green;'>✅ qbank_competenciesbyquestion: $count record</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Tabella non esiste: " . $e->getMessage() . "</p>";
}

// Test 9: question_references
echo "<h2>9. Question References</h2>";
try {
    $refs = $DB->get_records_sql("SELECT DISTINCT questionarea FROM {question_references} LIMIT 10");
    echo "<p style='color:green;'>✅ Valori questionarea:</p><ul>";
    foreach ($refs as $r) {
        echo "<li>'{$r->questionarea}'</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
}

// Test 10: Query completa quiz slots
echo "<h2>10. Query Quiz Slots</h2>";
if (!empty($quizzes)) {
    $first_quiz = reset($quizzes);
    
    try {
        $sql = "SELECT qs.slot, qr.questionarea, qr.questionbankentryid
                FROM {quiz_slots} qs
                LEFT JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
                WHERE qs.quizid = ?
                LIMIT 5";
        
        $results = $DB->get_records_sql($sql, [$first_quiz->id]);
        echo "<p style='color:green;'>✅ Risultati per quiz '{$first_quiz->name}':</p>";
        echo "<table border='1' cellpadding='5'><tr><th>Slot</th><th>questionarea</th><th>qbe_id</th></tr>";
        foreach ($results as $r) {
            echo "<tr><td>{$r->slot}</td><td>{$r->questionarea}</td><td>{$r->questionbankentryid}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>✅ Diagnostica completata!</h2>";
echo "<p><a href='dashboard.php?courseid=$courseid'>Torna alla Dashboard</a></p>";