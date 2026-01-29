<?php
// ============================================
// FIX OBSERVER: Verifica e ripara registrazione
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/fix_observer.php'));
$PAGE->set_title('Fix Observer Registration');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<h1>🔧 Fix Observer Registration</h1>';

// 1. Verifica nella tabella events_handlers (Moodle < 2.9) o config_plugins
echo '<h2>1. Verifica Cache Eventi Moodle</h2>';

// In Moodle 4.x, gli observer sono cachati in MUC
$cache = cache::make('core', 'observers');
$observers = $cache->get('all');

if ($observers === false) {
    echo '<p style="color: orange;">Cache observer vuota o non accessibile</p>';
} else {
    echo '<p>Cache observer trovata</p>';

    // Cerca il nostro observer
    $found = false;
    $observers_array = (array) $observers;
    foreach ($observers_array as $eventname => $obs_list) {
        if (strpos($eventname, 'attempt_submitted') !== false) {
            echo "<p><strong>Evento:</strong> {$eventname}</p>";
            $obs_list_array = is_array($obs_list) ? $obs_list : (array) $obs_list;
            foreach ($obs_list_array as $obs) {
                $obs_arr = is_array($obs) ? $obs : (array) $obs;
                $callback = $obs_arr['callback'] ?? ($obs_arr['callable'] ?? '');
                if (strpos($callback, 'selfassessment') !== false) {
                    echo '<p style="color: green;">✓ Observer selfassessment TROVATO nella cache!</p>';
                    echo '<pre>' . print_r($obs_arr, true) . '</pre>';
                    $found = true;
                }
            }
        }
    }

    if (!$found) {
        echo '<p style="color: red;">❌ Observer selfassessment NON trovato nella cache eventi!</p>';
    }
}

// 2. Verifica file events.php
echo '<h2>2. Verifica File events.php</h2>';

$events_file = __DIR__ . '/db/events.php';
if (file_exists($events_file)) {
    echo '<p style="color: green;">✓ File esiste: ' . $events_file . '</p>';

    // Include e mostra contenuto
    $observers_local = [];
    include($events_file);

    if (!empty($observers)) {
        echo '<p style="color: green;">✓ Observer definiti: ' . count($observers) . '</p>';
        foreach ($observers as $obs) {
            echo '<pre>' . print_r($obs, true) . '</pre>';
        }
    }
} else {
    echo '<p style="color: red;">❌ File events.php NON trovato!</p>';
}

// 3. Azione: Purge observer cache
echo '<h2>3. Azioni</h2>';

if ($action === 'purge') {
    echo '<div style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
    echo '<strong>Esecuzione purge cache observer...</strong><br><br>';

    // Purge observer cache
    $cache = cache::make('core', 'observers');
    $cache->purge();
    echo '✓ Cache observer purgata<br>';

    // Purge all caches
    purge_all_caches();
    echo '✓ Tutte le cache purgate<br>';

    // Force rebuild
    echo '✓ Ricostruzione forzata completata<br>';

    echo '<br><strong>Ora ricarica questa pagina per verificare.</strong>';
    echo '</div>';
}

if ($action === 'rebuild') {
    echo '<div style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
    echo '<strong>Ricostruzione eventi...</strong><br><br>';

    // Get events manager and rebuild
    $manager = \core\event\manager::instance();

    // This forces a rebuild of the observers cache
    $cache = cache::make('core', 'observers');
    $cache->purge();

    // Purge everything
    purge_all_caches();

    echo '✓ Cache eventi ricostruita<br>';
    echo '<br><strong>Ora testa completando un nuovo quiz.</strong>';
    echo '</div>';
}

echo '<div style="margin: 20px 0;">';
echo '<a href="?action=purge" style="background: #dc3545; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin-right: 10px; font-weight: bold;">🗑️ Purge Cache Observer</a>';
echo '<a href="?action=rebuild" style="background: #007bff; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin-right: 10px; font-weight: bold;">🔄 Ricostruisci Eventi</a>';
echo '</div>';

// 4. Test manuale evento
echo '<h2>4. Test Trigger Evento</h2>';

if ($action === 'testevent') {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';

    // Trova un attempt recente
    $attempt = $DB->get_record_sql("
        SELECT qa.*, q.course
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
        WHERE qa.state = 'finished'
        ORDER BY qa.timefinish DESC
        LIMIT 1
    ");

    if ($attempt) {
        echo "Testing con attempt ID: {$attempt->id}<br>";

        // Conta assegnazioni prima
        $before = $DB->count_records('local_selfassessment_assign', ['userid' => $attempt->userid]);
        echo "Assegnazioni prima: {$before}<br>";

        // Ottieni context
        $cm = $DB->get_record_sql("
            SELECT cm.id
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            WHERE cm.instance = ?
        ", [$attempt->quiz]);

        if ($cm) {
            // Triggera evento
            $event = \mod_quiz\event\attempt_submitted::create([
                'objectid' => $attempt->id,
                'relateduserid' => $attempt->userid,
                'context' => context_module::instance($cm->id),
                'other' => [
                    'quizid' => $attempt->quiz,
                    'submitterid' => $attempt->userid
                ]
            ]);

            echo "Evento creato, triggering...<br>";
            $event->trigger();
            echo "✓ Evento triggerato!<br><br>";

            // Conta dopo
            $after = $DB->count_records('local_selfassessment_assign', ['userid' => $attempt->userid]);
            echo "Assegnazioni dopo: {$after}<br>";

            $diff = $after - $before;
            if ($diff > 0) {
                echo "<br><strong style='color: green;'>✅ FUNZIONA! {$diff} nuove assegnazioni create!</strong>";
            } else {
                echo "<br><strong style='color: orange;'>⚠️ Nessuna nuova assegnazione (potrebbero esistere già tutte)</strong>";
            }
        } else {
            echo "❌ Course module non trovato";
        }
    } else {
        echo "❌ Nessun attempt trovato";
    }

    echo '</div>';
}

echo '<div style="margin: 20px 0;">';
echo '<a href="?action=testevent" style="background: #28a745; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">▶️ Test Trigger Evento</a>';
echo '</div>';

// 5. Informazioni di debug
echo '<h2>5. Info Debug</h2>';
echo '<pre>';
echo "Moodle Version: " . $CFG->version . "\n";
echo "Release: " . $CFG->release . "\n";
echo "Plugin Version (DB): ";
$plugin_version = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_selfassessment', 'name' => 'version']);
echo $plugin_version . "\n";

// Read version from file without including
$version_file = file_get_contents(__DIR__ . '/version.php');
preg_match('/\$plugin->version\s*=\s*(\d+)/', $version_file, $matches);
$file_version = $matches[1] ?? 'unknown';
echo "Plugin Version (File): " . $file_version . "\n";
echo '</pre>';

if ($plugin_version != $file_version) {
    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 8px;">';
    echo '<strong>⚠️ VERSIONE MISMATCH!</strong><br>';
    echo "Database: {$plugin_version}<br>";
    echo "File: {$file_version}<br><br>";
    echo 'Vai a <a href="' . $CFG->wwwroot . '/admin/index.php">/admin</a> per aggiornare il plugin.';
    echo '</div>';
}

// Link
echo '<h2>Link</h2>';
echo '<p>';
echo '<a href="force_assign.php" style="margin-right: 15px;">⚡ Force Assign</a>';
echo '<a href="catchup_assignments.php" style="margin-right: 15px;">🔄 Catchup</a>';
echo '<a href="diagnose.php" style="margin-right: 15px;">🔍 Diagnosi</a>';
echo '<a href="' . $CFG->wwwroot . '/admin/purgecaches.php">🗑️ Purge Caches</a>';
echo '</p>';

echo $OUTPUT->footer();
