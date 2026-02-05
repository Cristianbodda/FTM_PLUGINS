<?php
/**
 * Verifica se l'observer selfassessment è registrato e funzionante
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/check_observer.php'));
$PAGE->set_title('Check Observer Selfassessment');

echo $OUTPUT->header();

echo '<h2>Verifica Observer Selfassessment</h2>';

// 1. Verifica file events.php
echo '<h3>1. File db/events.php</h3>';
$eventsFile = __DIR__ . '/db/events.php';
if (file_exists($eventsFile)) {
    echo '<div class="alert alert-success">File events.php esiste</div>';

    // Leggi e mostra contenuto
    $content = file_get_contents($eventsFile);
    echo '<pre style="background:#f5f5f5; padding:10px; font-size:12px;">' . htmlspecialchars($content) . '</pre>';
} else {
    echo '<div class="alert alert-danger">File events.php NON TROVATO!</div>';
}

// 2. Verifica classe observer
echo '<h3>2. Classe Observer</h3>';
$observerFile = __DIR__ . '/classes/observer.php';
if (file_exists($observerFile)) {
    echo '<div class="alert alert-success">File observer.php esiste</div>';

    // Verifica se la classe è caricabile
    if (class_exists('\local_selfassessment\observer')) {
        echo '<div class="alert alert-success">Classe \local_selfassessment\observer caricata correttamente</div>';

        // Verifica metodo
        if (method_exists('\local_selfassessment\observer', 'quiz_attempt_submitted')) {
            echo '<div class="alert alert-success">Metodo quiz_attempt_submitted esiste</div>';
        } else {
            echo '<div class="alert alert-danger">Metodo quiz_attempt_submitted NON TROVATO!</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Classe observer NON caricata! Possibile errore di sintassi.</div>';

        // Prova a includere per vedere l'errore
        echo '<pre>';
        try {
            include_once($observerFile);
        } catch (Exception $e) {
            echo 'Errore: ' . $e->getMessage();
        }
        echo '</pre>';
    }
} else {
    echo '<div class="alert alert-danger">File observer.php NON TROVATO!</div>';
}

// 3. Verifica registrazione evento in Moodle
echo '<h3>3. Registrazione Evento in Moodle</h3>';

// Ottieni tutti gli observer registrati per quiz_attempt_submitted
$eventname = '\mod_quiz\event\attempt_submitted';

// Query nella tabella events_handlers (Moodle < 2.7) o tramite get_observers
if ($DB->get_manager()->table_exists('events_handlers')) {
    $handlers = $DB->get_records('events_handlers', ['eventname' => 'quiz_attempt_submitted']);
    if ($handlers) {
        echo '<div class="alert alert-info">Trovati ' . count($handlers) . ' handler in events_handlers</div>';
    }
}

// Moodle moderno usa event observers
echo '<p>Verifica manuale degli observer registrati...</p>';

// Leggi il file events.php di selfassessment
$observers = [];
if (file_exists($eventsFile)) {
    include($eventsFile);
}

if (!empty($observers)) {
    echo '<div class="alert alert-success">Observer definiti in events.php:</div>';
    echo '<ul>';
    foreach ($observers as $obs) {
        echo '<li>';
        echo '<strong>Evento:</strong> ' . $obs['eventname'] . '<br>';
        echo '<strong>Callback:</strong> ' . $obs['callback'] . '<br>';
        echo '<strong>Priority:</strong> ' . ($obs['priority'] ?? 'default') . '<br>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<div class="alert alert-danger">Nessun observer definito in $observers!</div>';
}

// 4. Verifica versione plugin
echo '<h3>4. Versione Plugin</h3>';
$versionFile = __DIR__ . '/version.php';
if (file_exists($versionFile)) {
    $plugin = new stdClass();
    include($versionFile);
    echo '<ul>';
    echo '<li>Component: ' . ($plugin->component ?? 'N/A') . '</li>';
    echo '<li>Version: ' . ($plugin->version ?? 'N/A') . '</li>';
    echo '<li>Release: ' . ($plugin->release ?? 'N/A') . '</li>';
    echo '</ul>';

    // Verifica versione installata nel DB
    $installed = $DB->get_record('config_plugins', [
        'plugin' => 'local_selfassessment',
        'name' => 'version'
    ]);

    if ($installed) {
        echo '<p>Versione installata nel DB: <strong>' . $installed->value . '</strong></p>';

        if ($installed->value != $plugin->version) {
            echo '<div class="alert alert-warning">ATTENZIONE: Versione file (' . $plugin->version . ') diversa da versione DB (' . $installed->value . '). Esegui upgrade!</div>';
        } else {
            echo '<div class="alert alert-success">Versione file e DB coincidono</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Plugin NON risulta installato nel DB!</div>';
    }
}

// 5. Test manuale observer
echo '<h3>5. Test Manuale Observer</h3>';
echo '<p>Per testare l\'observer manualmente, seleziona un quiz attempt completato:</p>';

$testAttemptId = optional_param('test_attempt', 0, PARAM_INT);

if ($testAttemptId) {
    echo '<div class="alert alert-info">Testing attempt ID: ' . $testAttemptId . '</div>';

    $attempt = $DB->get_record('quiz_attempts', ['id' => $testAttemptId]);
    if ($attempt && $attempt->state == 'finished') {
        echo '<p>Attempt trovato per utente ID: ' . $attempt->userid . '</p>';

        // Conta competenze prima
        $beforeCount = $DB->count_records('local_selfassessment_assign', ['userid' => $attempt->userid]);
        echo '<p>Competenze assegnate PRIMA: ' . $beforeCount . '</p>';

        // Simula l'evento
        echo '<p>Simulazione evento quiz_attempt_submitted...</p>';

        // Crea un evento finto
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz);
        $context = context_module::instance($cm->id);

        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => $context,
            'other' => [
                'quizid' => $attempt->quiz
            ]
        ]);

        // Chiama direttamente l'observer
        try {
            \local_selfassessment\observer::quiz_attempt_submitted($event);
            echo '<div class="alert alert-success">Observer eseguito senza errori!</div>';
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">ERRORE nell\'observer: ' . $e->getMessage() . '</div>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
        }

        // Conta competenze dopo
        $afterCount = $DB->count_records('local_selfassessment_assign', ['userid' => $attempt->userid]);
        echo '<p>Competenze assegnate DOPO: ' . $afterCount . '</p>';

        $diff = $afterCount - $beforeCount;
        if ($diff > 0) {
            echo '<div class="alert alert-success">SUCCESSO! Assegnate ' . $diff . ' nuove competenze.</div>';
        } else {
            echo '<div class="alert alert-warning">Nessuna nuova competenza assegnata. Potrebbero essere già tutte presenti o il quiz non ha competenze associate.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Attempt non trovato o non completato</div>';
    }
} else {
    // Mostra ultimi attempt per test
    $recentAttempts = $DB->get_records_sql("
        SELECT qa.id, qa.userid, qa.quiz, qa.timefinish, u.firstname, u.lastname, q.name as quizname
        FROM {quiz_attempts} qa
        JOIN {user} u ON u.id = qa.userid
        JOIN {quiz} q ON q.id = qa.quiz
        WHERE qa.state = 'finished'
        ORDER BY qa.timefinish DESC
        LIMIT 10
    ");

    if ($recentAttempts) {
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Utente</th><th>Quiz</th><th>Completato</th><th>Test</th></tr></thead>';
        echo '<tbody>';
        foreach ($recentAttempts as $att) {
            echo '<tr>';
            echo '<td>' . $att->firstname . ' ' . $att->lastname . '</td>';
            echo '<td>' . substr($att->quizname, 0, 50) . '</td>';
            echo '<td>' . userdate($att->timefinish, '%d/%m %H:%M') . '</td>';
            echo '<td><a href="?test_attempt=' . $att->id . '" class="btn btn-sm btn-primary">Test Observer</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

// 6. Azioni consigliate
echo '<h3>6. Azioni Consigliate</h3>';
echo '<ol>';
echo '<li><a href="/admin/purgecaches.php" target="_blank">Svuota tutte le cache</a></li>';
echo '<li><a href="/admin/index.php" target="_blank">Vai a Notifiche per aggiornare plugin</a></li>';
echo '<li><a href="fix_missing_assignments.php?dryrun=0&confirm=1" onclick="return confirm(\'Confermi?\')" class="btn btn-warning">Fix tutte le assegnazioni mancanti</a></li>';
echo '</ol>';

echo $OUTPUT->footer();
