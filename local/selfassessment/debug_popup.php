<?php
/**
 * Debug: verifica perché il popup autovalutazione non appare.
 * Accedere come lo STUDENTE (non admin) per testare.
 *
 * @package    local_selfassessment
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/selfassessment/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/debug_popup.php'));
$PAGE->set_title('Debug Popup Autovalutazione');

echo $OUTPUT->header();

echo '<div style="max-width:800px; margin:20px auto; font-family:monospace; font-size:14px;">';
echo '<h2>Debug Popup Autovalutazione</h2>';
echo '<p><strong>Utente corrente:</strong> ' . fullname($USER) . ' (id=' . $USER->id . ')</p>';
echo '<p><strong>is_siteadmin():</strong> ' . (is_siteadmin() ? '<span style="color:red">SI - popup bloccato per admin!</span>' : '<span style="color:green">NO - ok</span>') . '</p>';
echo '<hr>';

// Check 1: popup_enabled
$popupEnabled = get_config('local_selfassessment', 'popup_enabled');
echo '<h3>1. Setting popup_enabled</h3>';
echo '<p>Valore nel DB: <strong>' . var_export($popupEnabled, true) . '</strong> → ';
echo ($popupEnabled ? '<span style="color:green">ATTIVO</span>' : '<span style="color:red">DISATTIVO - popup non apparirà mai!</span>') . '</p>';

// Check 2: reminder status
echo '<h3>2. Reminder status per utente corrente</h3>';
$status = local_selfassessment_get_reminder_status($USER->id);
echo '<pre>' . print_r($status, true) . '</pre>';

// Check 3: Capabilities
echo '<h3>3. Capabilities</h3>';
$ctx = context_system::instance();
echo '<p>:complete = ' . (has_capability('local/selfassessment:complete', $ctx) ? '<span style="color:green">SI</span>' : '<span style="color:red">NO</span>') . '</p>';
echo '<p>:view = ' . (has_capability('local/selfassessment:view', $ctx) ? '<span style="color:orange">SI (blocca reminder)</span>' : '<span style="color:green">NO (ok per studente)</span>') . '</p>';

// Check 4: Hook registration
echo '<h3>4. Hook registration</h3>';
$hookClass = 'core\hook\output\before_standard_head_html_generation';
$hookExists = class_exists($hookClass);
echo '<p>Classe hook ' . $hookClass . ': ' . ($hookExists ? '<span style="color:green">ESISTE</span>' : '<span style="color:red">NON ESISTE - Moodle troppo vecchio?</span>') . '</p>';

// Check if callback is registered
try {
    if ($hookExists) {
        $manager = \core\hook\manager::get_instance();
        $ref = new ReflectionMethod($manager, 'get_callbacks_for_hook');
        $ref->setAccessible(true);
        $hookObj = new $hookClass();
        $callbacks = $ref->invoke($manager, $hookObj);
        echo '<p>Callbacks registrati per il hook: <strong>' . count($callbacks) . '</strong></p>';
        foreach ($callbacks as $cb) {
            $cbStr = is_array($cb['callback']) ? implode('::', $cb['callback']) : (is_string($cb['callback']) ? $cb['callback'] : 'closure');
            echo '<p style="margin-left:20px;">→ ' . $cbStr . '</p>';
        }
        if (count($callbacks) === 0) {
            echo '<p style="color:red; font-weight:bold;">NESSUN CALLBACK REGISTRATO! Il hook non funziona.</p>';
        }
    }
} catch (Throwable $e) {
    echo '<p style="color:orange;">Impossibile verificare callbacks: ' . s($e->getMessage()) . '</p>';
}

// Check 5: before_footer function exists
echo '<h3>5. Fallback before_footer</h3>';
$fnExists = function_exists('local_selfassessment_before_footer');
echo '<p>local_selfassessment_before_footer(): ' . ($fnExists ? '<span style="color:green">ESISTE</span>' : '<span style="color:red">NON ESISTE</span>') . '</p>';

// Check 6: extend_navigation function exists
$fnNavExists = function_exists('local_selfassessment_extend_navigation');
echo '<p>local_selfassessment_extend_navigation(): ' . ($fnNavExists ? '<span style="color:green">ESISTE</span>' : '<span style="color:red">NON ESISTE</span>') . '</p>';

// Check 7: get_plugins_with_function cache
echo '<h3>6. Plugin function cache (before_footer)</h3>';
try {
    $pluginsWithBeforeFooter = get_plugin_list_with_function('before_footer');
    $found = false;
    foreach ($pluginsWithBeforeFooter as $name => $file) {
        if (strpos($name, 'selfassessment') !== false) {
            echo '<p style="color:green;">TROVATO: ' . $name . ' → ' . $file . '</p>';
            $found = true;
        }
    }
    if (!$found) {
        echo '<p style="color:red; font-weight:bold;">NON TROVATO nel cache! Moodle non sa che local_selfassessment ha before_footer().</p>';
        echo '<p>Prova: <a href="' . $CFG->wwwroot . '/admin/purgecaches.php">Purge All Caches</a> poi ricarica questa pagina.</p>';
    }
} catch (Throwable $e) {
    echo '<p style="color:orange;">Errore: ' . s($e->getMessage()) . '</p>';
}

// Check 8: additionalhtmlfooter
echo '<h3>7. $CFG->additionalhtmlfooter</h3>';
$footerContent = $CFG->additionalhtmlfooter ?? '';
$hasSaPopup = strpos($footerContent, 'sa-popup-banner') !== false || strpos($footerContent, 'sa-reminder-banner') !== false;
echo '<p>Contiene popup SA: ' . ($hasSaPopup ? '<span style="color:green">SI</span>' : '<span style="color:red">NO - popup non iniettato!</span>') . '</p>';
echo '<p>Lunghezza footer HTML: ' . strlen($footerContent) . ' caratteri</p>';

// Check 9: Manual test - try injecting now
echo '<h3>8. Test iniezione manuale</h3>';
if ($status['should_show'] && !is_siteadmin()) {
    echo '<p style="color:green; font-weight:bold;">Il sistema DOVREBBE mostrare il popup. Se non lo vedi, il problema è nel meccanismo di iniezione.</p>';

    // Force injection for this page
    $compile_url = (new moodle_url('/local/selfassessment/compile.php'))->out(false);
    $pending = (int)$status['pending_count'];
    $total = (int)$status['total_count'];

    echo '<div style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center;">';
    echo '<h3 style="color:white; margin:0 0 10px;">TEST BANNER POPUP</h3>';
    echo '<p style="margin:0 0 15px;">Hai <strong>' . $pending . ' competenze su ' . $total . '</strong> da autovalutare.</p>';
    echo '<a href="' . $compile_url . '" style="display:inline-block; background:white; color:#ee5a24; padding:12px 30px; border-radius:25px; text-decoration:none; font-weight:bold;">Compila Autovalutazione</a>';
    echo '</div>';

    echo '<p>Se vedi il banner rosso qui sopra ma NON nelle altre pagine, il problema è che Moodle non chiama i callback.</p>';
} else {
    echo '<p>should_show=' . var_export($status['should_show'], true) . ', is_siteadmin=' . var_export(is_siteadmin(), true) . '</p>';
}

echo '</div>';
echo $OUTPUT->footer();
