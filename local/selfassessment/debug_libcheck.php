<?php
require_once(__DIR__ . '/../../config.php');
require_login();

echo '<h2>Test lib.php</h2>';

// Test 1: Can we load lib.php?
echo '<p>1. Loading lib.php... ';
try {
    require_once($CFG->dirroot . '/local/selfassessment/lib.php');
    echo '<span style="color:green">OK</span></p>';
} catch (Throwable $e) {
    echo '<span style="color:red">ERRORE: ' . $e->getMessage() . '</span></p>';
}

// Test 2: Functions exist?
$fns = [
    'local_selfassessment_render_navbar_output',
    'local_selfassessment_extend_navigation',
    'local_selfassessment_before_footer',
    'local_selfassessment_inject_popup_fallback',
    'local_selfassessment_get_reminder_status',
];
echo '<p>2. Funzioni:</p><ul>';
foreach ($fns as $fn) {
    $exists = function_exists($fn);
    echo '<li>' . $fn . ': ' . ($exists ? '<span style="color:green">ESISTE</span>' : '<span style="color:red">NON ESISTE</span>') . '</li>';
}
echo '</ul>';

// Test 3: Theme info
echo '<p>3. Tema attivo: <strong>' . $PAGE->theme->name . '</strong></p>';

// Test 4: Try calling render_navbar_output
echo '<p>4. Test render_navbar_output... ';
if (function_exists('local_selfassessment_render_navbar_output')) {
    try {
        $result = local_selfassessment_render_navbar_output($OUTPUT);
        $len = strlen($result);
        echo '<span style="color:green">OK - restituito ' . $len . ' caratteri</span></p>';
        if ($len > 0) {
            echo '<p>5. Output HTML (primi 200 char): <code>' . htmlspecialchars(substr($result, 0, 200)) . '...</code></p>';
        }
    } catch (Throwable $e) {
        echo '<span style="color:red">ERRORE: ' . $e->getMessage() . ' (riga ' . $e->getLine() . ')</span></p>';
    }
} else {
    echo '<span style="color:red">Funzione non trovata!</span></p>';
}

// Test 5: PHP errors in error log
echo '<p>6. Ultimi errori PHP:</p>';
$errorLog = ini_get('error_log');
echo '<p>Error log: ' . ($errorLog ?: 'default') . '</p>';
