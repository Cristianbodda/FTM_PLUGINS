<?php
// This file is part of Moodle - http://moodle.org/
//
// Script per inizializzare i dati di base FTM Scheduler
// Eseguire una sola volta dopo l'installazione

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== FTM Scheduler - Setup Dati Iniziali ===\n\n";

// =====================
// 1. AULE
// =====================
echo "1. Creazione Aule...\n";

$rooms = [
    ['name' => 'AULA 1', 'shortname' => 'A1', 'capacity' => 12, 'is_lab' => 0, 'sortorder' => 1, 'active' => 1, 'color_hex' => '#DBEAFE'],
    ['name' => 'AULA 2', 'shortname' => 'A2', 'capacity' => 12, 'is_lab' => 0, 'sortorder' => 2, 'active' => 1, 'color_hex' => '#D1FAE5'],
    ['name' => 'AULA 3', 'shortname' => 'A3', 'capacity' => 10, 'is_lab' => 0, 'sortorder' => 3, 'active' => 1, 'color_hex' => '#FEF3C7'],
];

foreach ($rooms as $room) {
    if (!$DB->record_exists('local_ftm_rooms', ['shortname' => $room['shortname']])) {
        $DB->insert_record('local_ftm_rooms', (object)$room);
        echo "   - Creata: {$room['name']}\n";
    } else {
        echo "   - Esiste già: {$room['name']}\n";
    }
}

// =====================
// 2. COACH
// =====================
echo "\n2. Creazione Coach...\n";

// NOTA: I coach devono avere un account Moodle. Usiamo userid temporanei (0)
// che andranno aggiornati con gli ID reali degli utenti Moodle

$coaches = [
    ['initials' => 'CB', 'role' => 'coach', 'fullname' => 'Cristian Bodda', 'can_week2_mon_tue' => 1, 'can_week2_thu_fri' => 1],
    ['initials' => 'FM', 'role' => 'coach', 'fullname' => 'Fabio Marinoni', 'can_week2_mon_tue' => 1, 'can_week2_thu_fri' => 1],
    ['initials' => 'GM', 'role' => 'coach', 'fullname' => 'Graziano Margonar', 'can_week2_mon_tue' => 1, 'can_week2_thu_fri' => 1],
    ['initials' => 'RB', 'role' => 'coach', 'fullname' => 'Roberto Bravo', 'can_week2_mon_tue' => 1, 'can_week2_thu_fri' => 1],
    ['initials' => 'SANDRA', 'role' => 'secretary', 'fullname' => 'Sandra (Segreteria)', 'can_week2_mon_tue' => 0, 'can_week2_thu_fri' => 0],
    ['initials' => 'ALE', 'role' => 'secretary', 'fullname' => 'Alessandra (Segreteria)', 'can_week2_mon_tue' => 0, 'can_week2_thu_fri' => 0],
];

// Prima cerchiamo gli utenti nel sistema
foreach ($coaches as $coach) {
    if (!$DB->record_exists('local_ftm_coaches', ['initials' => $coach['initials']])) {
        // Cerca utente per nome o email (placeholder - userid = 2 admin)
        $record = new stdClass();
        $record->userid = 2; // Placeholder - da aggiornare con ID reali
        $record->initials = $coach['initials'];
        $record->role = $coach['role'];
        $record->can_week2_mon_tue = $coach['can_week2_mon_tue'];
        $record->can_week2_thu_fri = $coach['can_week2_thu_fri'];
        $record->active = 1;

        $DB->insert_record('local_ftm_coaches', $record);
        echo "   - Creato: {$coach['initials']} ({$coach['fullname']})\n";
    } else {
        echo "   - Esiste già: {$coach['initials']}\n";
    }
}

// =====================
// 3. TEMPLATE SETTIMANA 1
// =====================
echo "\n3. Creazione Template Settimana 1...\n";

// Basato sul calendario Excel - GRUPPO GIALLO dal 19 Gennaio
// AULA 2 = id 2, AULA 3 = id 3
$room_aula2 = $DB->get_record('local_ftm_rooms', ['shortname' => 'A2']);
$room_aula3 = $DB->get_record('local_ftm_rooms', ['shortname' => 'A3']);
$roomid_a2 = $room_aula2 ? $room_aula2->id : null;
$roomid_a3 = $room_aula3 ? $room_aula3->id : null;

$week1_template = [
    // Lunedì 19 - INIZIO GRUPPO
    ['name' => 'Accoglienza e Presentazione', 'day_of_week' => 1, 'time_slot' => 'matt',
     'time_start' => '08:30', 'time_end' => '11:45', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 1,
     'description' => 'Accoglienza nuovi studenti, presentazione percorso e coach (GM, RB)'],

    ['name' => 'Orientamento al Percorso', 'day_of_week' => 1, 'time_slot' => 'pom',
     'time_start' => '13:15', 'time_end' => '16:30', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 2,
     'description' => 'Orientamento, obiettivi e metodologia (GM)'],

    // Martedì 20
    ['name' => 'Competenze Trasversali', 'day_of_week' => 2, 'time_slot' => 'matt',
     'time_start' => '08:30', 'time_end' => '11:45', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 3,
     'description' => 'Introduzione competenze trasversali (GM, RB)'],

    ['name' => 'Competenze Trasversali', 'day_of_week' => 2, 'time_slot' => 'pom',
     'time_start' => '13:15', 'time_end' => '16:30', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 4,
     'description' => 'Lavoro competenze trasversali (GM)'],

    // Mercoledì 21 - REMOTO per gruppo
    ['name' => 'Lavoro Autonomo Remoto', 'day_of_week' => 3, 'time_slot' => 'matt',
     'time_start' => '08:30', 'time_end' => '11:45', 'default_roomid' => null,
     'is_mandatory' => 1, 'is_remote' => 1, 'sortorder' => 5,
     'description' => 'Lavoro autonomo da remoto'],

    ['name' => 'Lavoro Autonomo Remoto', 'day_of_week' => 3, 'time_slot' => 'pom',
     'time_start' => '13:15', 'time_end' => '16:30', 'default_roomid' => null,
     'is_mandatory' => 1, 'is_remote' => 1, 'sortorder' => 6,
     'description' => 'Lavoro autonomo da remoto'],

    // Giovedì 22
    ['name' => 'Formazione Gruppo', 'day_of_week' => 4, 'time_slot' => 'matt',
     'time_start' => '08:30', 'time_end' => '11:45', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 7,
     'description' => 'Formazione gruppo (FM + Segreteria Sandra)'],

    ['name' => 'Formazione Gruppo', 'day_of_week' => 4, 'time_slot' => 'pom',
     'time_start' => '13:15', 'time_end' => '16:30', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 8,
     'description' => 'Formazione gruppo (FM + Segreteria Sandra)'],

    // Venerdì 23
    ['name' => 'Formazione Gruppo', 'day_of_week' => 5, 'time_slot' => 'matt',
     'time_start' => '08:30', 'time_end' => '11:45', 'default_roomid' => $roomid_a3,
     'is_mandatory' => 1, 'is_remote' => 0, 'sortorder' => 9,
     'description' => 'Formazione gruppo (FM + Segreteria Alessandra)'],

    ['name' => 'Lavoro Autonomo Remoto', 'day_of_week' => 5, 'time_slot' => 'pom',
     'time_start' => '13:15', 'time_end' => '16:30', 'default_roomid' => null,
     'is_mandatory' => 0, 'is_remote' => 1, 'sortorder' => 10,
     'description' => 'Lavoro autonomo da remoto (opzionale)'],
];

// Prima elimina template esistenti
$DB->delete_records('local_ftm_week1_template');
echo "   - Template esistenti eliminati\n";

foreach ($week1_template as $template) {
    $template['active'] = 1;
    $DB->insert_record('local_ftm_week1_template', (object)$template);
    echo "   - Creato: {$template['name']} (Giorno {$template['day_of_week']} - {$template['time_slot']})\n";
}

// =====================
// 4. GRUPPO GIALLO ESEMPIO
// =====================
echo "\n4. Creazione Gruppo Giallo esempio (KW04 - 19 Gennaio 2026)...\n";

// Data inizio: Lunedì 19 Gennaio 2026
$entry_date = mktime(8, 30, 0, 1, 19, 2026);
$end_date = strtotime('+6 weeks', $entry_date);

if (!$DB->record_exists('local_ftm_groups', ['color' => 'giallo', 'calendar_week' => 4])) {
    $group = new stdClass();
    $group->name = 'Gruppo Giallo - KW04';
    $group->color = 'giallo';
    $group->color_hex = '#FFFF00';
    $group->entry_date = $entry_date;
    $group->planned_end_date = $end_date;
    $group->calendar_week = 4; // KW04
    $group->status = 'planning';
    $group->timecreated = time();
    $group->timemodified = time();
    $group->createdby = 2;

    $groupid = $DB->insert_record('local_ftm_groups', $group);
    echo "   - Creato: Gruppo Giallo - KW04 (ID: $groupid)\n";
    echo "   - Inizio: Lunedì 19 Gennaio 2026\n";
    echo "   - Fine prevista: " . date('d/m/Y', $end_date) . "\n";
} else {
    echo "   - Gruppo Giallo KW04 esiste già\n";
}

// =====================
// 5. CATALOGO ATELIER
// =====================
echo "\n5. Creazione Catalogo Atelier...\n";

$atelier_catalog = [
    ['name' => 'Bilancio delle Competenze', 'shortname' => 'BILANCIO', 'typical_week_start' => 2, 'typical_week_end' => 2, 'is_mandatory' => 1, 'mandatory_week' => 2, 'max_participants' => 12],
    ['name' => 'Atelier CV', 'shortname' => 'AT.CV', 'typical_week_start' => 3, 'typical_week_end' => 6, 'is_mandatory' => 0, 'max_participants' => 10],
    ['name' => 'Atelier Colloquio', 'shortname' => 'AT.COLL', 'typical_week_start' => 3, 'typical_week_end' => 6, 'is_mandatory' => 0, 'max_participants' => 10],
    ['name' => 'Atelier Digital Skills', 'shortname' => 'AT.DIG', 'typical_week_start' => 3, 'typical_week_end' => 6, 'is_mandatory' => 0, 'max_participants' => 12],
    ['name' => 'Laboratorio Tecnico', 'shortname' => 'LAB', 'typical_week_start' => 2, 'typical_week_end' => 6, 'is_mandatory' => 0, 'max_participants' => 10],
];

$sortorder = 1;
foreach ($atelier_catalog as $atelier) {
    if (!$DB->record_exists('local_ftm_atelier_catalog', ['shortname' => $atelier['shortname']])) {
        $atelier['sortorder'] = $sortorder++;
        $atelier['active'] = 1;
        $DB->insert_record('local_ftm_atelier_catalog', (object)$atelier);
        echo "   - Creato: {$atelier['name']}\n";
    } else {
        echo "   - Esiste già: {$atelier['name']}\n";
    }
}

echo "\n=== Setup completato! ===\n";
echo "\nProssimi passi:\n";
echo "1. Aggiornare gli userid dei coach con gli ID reali degli utenti Moodle\n";
echo "2. Aggiungere studenti al Gruppo Giallo\n";
echo "3. Attivare il gruppo per generare le attività della Settimana 1\n";
