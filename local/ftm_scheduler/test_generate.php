<?php
// This file is part of Moodle - http://moodle.org/
//
// Test page to generate sample activities for the calendar
// Access: /local/ftm_scheduler/test_generate.php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/test_generate.php'));
$PAGE->set_title('FTM Scheduler - Test Generate');

echo $OUTPUT->header();
echo '<h2>FTM Scheduler - Generatore Attività Test</h2>';

// Verifica tabelle
echo '<h3>1. Verifica Tabelle Database</h3>';
$tables = [
    'local_ftm_groups' => 'Gruppi',
    'local_ftm_rooms' => 'Aule',
    'local_ftm_coaches' => 'Coach',
    'local_ftm_week1_template' => 'Template Settimana 1',
    'local_ftm_activities' => 'Attività',
    'local_ftm_enrollments' => 'Iscrizioni',
    'local_ftm_external_bookings' => 'Prenotazioni Esterne',
    'local_ftm_atelier_catalog' => 'Catalogo Atelier',
];

echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
echo '<tr><th>Tabella</th><th>Descrizione</th><th>Record</th><th>Stato</th></tr>';

foreach ($tables as $table => $desc) {
    $dbman = $DB->get_manager();
    $exists = $dbman->table_exists($table);
    $count = $exists ? $DB->count_records($table) : 'N/A';
    $status = $exists ? '✅ Esiste' : '❌ Non esiste';
    echo "<tr><td>$table</td><td>$desc</td><td>$count</td><td>$status</td></tr>";
}
echo '</table>';

// Azioni
echo '<h3>2. Azioni</h3>';

if ($action === 'generate') {
    require_sesskey();

    echo '<div style="background: #d1fae5; padding: 15px; border-radius: 8px; margin: 10px 0;">';
    echo '<h4>Generazione Attività...</h4>';

    // Verifica che esistano i dati base
    $rooms = $DB->get_records('local_ftm_rooms', ['active' => 1]);
    $groups = $DB->get_records('local_ftm_groups');
    $templates = $DB->get_records('local_ftm_week1_template', ['active' => 1]);

    if (empty($rooms)) {
        echo '<p style="color: red;">❌ Nessuna aula trovata. Esegui prima setup_data.php</p>';
    } elseif (empty($groups)) {
        echo '<p style="color: red;">❌ Nessun gruppo trovato. Esegui prima setup_data.php</p>';
    } elseif (empty($templates)) {
        echo '<p style="color: red;">❌ Nessun template settimana 1 trovato. Esegui prima setup_data.php</p>';
    } else {
        // Prendi il primo gruppo
        $group = reset($groups);

        echo "<p>📋 Gruppo: {$group->name} (ID: {$group->id})</p>";
        echo "<p>📅 Data inizio: " . date('d/m/Y', $group->entry_date) . "</p>";
        echo "<p>📝 Template: " . count($templates) . " attività</p>";

        // Genera attività usando il manager
        $created = \local_ftm_scheduler\manager::generate_week1_activities($group->id);

        echo "<p style='color: green;'>✅ Create " . count($created) . " attività per la Settimana 1!</p>";

        // Mostra le attività create
        if (!empty($created)) {
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse; margin-top: 10px;">';
            echo '<tr><th>ID</th><th>Nome</th><th>Data</th><th>Orario</th><th>Aula</th></tr>';

            foreach ($created as $actid) {
                $act = $DB->get_record('local_ftm_activities', ['id' => $actid]);
                $room = $act->roomid ? $DB->get_field('local_ftm_rooms', 'name', ['id' => $act->roomid]) : 'REMOTO';
                echo "<tr>";
                echo "<td>{$act->id}</td>";
                echo "<td>{$act->name}</td>";
                echo "<td>" . date('D d/m/Y', $act->date_start) . "</td>";
                echo "<td>" . date('H:i', $act->date_start) . " - " . date('H:i', $act->date_end) . "</td>";
                echo "<td>{$room}</td>";
                echo "</tr>";
            }
            echo '</table>';
        }
    }

    echo '</div>';
}

if ($action === 'clear') {
    require_sesskey();

    $DB->delete_records('local_ftm_enrollments');
    $DB->delete_records('local_ftm_activities');

    echo '<div style="background: #fee2e2; padding: 15px; border-radius: 8px; margin: 10px 0;">';
    echo '<p>🗑️ Tutte le attività e iscrizioni sono state eliminate.</p>';
    echo '</div>';
}

// Bottoni azione
echo '<form method="post" style="margin: 20px 0;">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<button type="submit" name="action" value="generate" style="padding: 10px 20px; background: #22c55e; color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px;">
    🚀 Genera Attività Settimana 1
</button>';
echo '<button type="submit" name="action" value="clear" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer;" onclick="return confirm(\'Sei sicuro? Questa azione eliminerà tutte le attività.\');">
    🗑️ Elimina Tutte le Attività
</button>';
echo '</form>';

// Link al calendario
echo '<h3>3. Link Utili</h3>';
echo '<ul>';
echo '<li><a href="' . new moodle_url('/local/ftm_scheduler/index.php') . '">📅 Vai al Calendario</a></li>';
echo '<li><a href="' . new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => 4, 'year' => 2026]) . '">📅 Calendario KW04 2026</a></li>';
echo '</ul>';

// Test AJAX
echo '<h3>4. Test Popup Attività</h3>';
echo '<p>Clicca su un\'attività nel calendario per testare il popup. Se non funziona, controlla la console del browser (F12).</p>';

// Mostra attività esistenti
$activities = $DB->get_records('local_ftm_activities', [], 'date_start ASC', '*', 0, 10);
if (!empty($activities)) {
    echo '<h4>Attività esistenti (prime 10):</h4>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>ID</th><th>Nome</th><th>Data</th><th>Test Popup</th></tr>';

    foreach ($activities as $act) {
        echo "<tr>";
        echo "<td>{$act->id}</td>";
        echo "<td>{$act->name}</td>";
        echo "<td>" . date('d/m/Y H:i', $act->date_start) . "</td>";
        echo "<td><button onclick=\"testPopup({$act->id})\">Test Popup</button></td>";
        echo "</tr>";
    }
    echo '</table>';

    echo '<script>
    function testPopup(activityId) {
        fetch("' . $CFG->wwwroot . '/local/ftm_scheduler/ajax.php?action=get_activity&id=" + activityId)
            .then(response => response.json())
            .then(data => {
                console.log("AJAX Response:", data);
                if (data.success) {
                    alert("Popup funziona!\\n\\nTitolo: " + data.title + "\\n\\nContenuto disponibile nella console (F12)");
                } else {
                    alert("Errore: " + (data.error || "Errore sconosciuto"));
                }
            })
            .catch(error => {
                console.error("AJAX Error:", error);
                alert("Errore AJAX: " + error.message);
            });
    }
    </script>';
} else {
    echo '<p style="color: orange;">⚠️ Nessuna attività nel database. Clicca "Genera Attività Settimana 1" sopra.</p>';
}

echo $OUTPUT->footer();
