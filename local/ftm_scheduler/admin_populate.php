<?php
// This file is part of Moodle - http://moodle.org/
//
// Script per popolare FTM Scheduler con TUTTO L'ANNO 2026.
// 19 cicli di gruppi + progetti esterni
//
// @package    local_ftm_scheduler
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/admin_populate.php'));
$PAGE->set_title('FTM Scheduler - Popola Anno 2026');
$PAGE->set_heading('FTM Scheduler - Popola TUTTO l\'Anno 2026');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">';

// =============================================================================
// CONFIGURAZIONE CICLI 2026 (estratti da Excel)
// =============================================================================
$year_cycles = [
    ['color' => 'giallo', 'kw' => 4, 'start' => '2026-01-19'],
    ['color' => 'grigio', 'kw' => 6, 'start' => '2026-02-02'],
    ['color' => 'rosso', 'kw' => 8, 'start' => '2026-02-16'],
    ['color' => 'viola', 'kw' => 12, 'start' => '2026-03-16'],
    ['color' => 'giallo', 'kw' => 14, 'start' => '2026-03-30'],
    ['color' => 'grigio', 'kw' => 16, 'start' => '2026-04-13'],
    ['color' => 'rosso', 'kw' => 18, 'start' => '2026-04-27'],
    ['color' => 'viola', 'kw' => 22, 'start' => '2026-05-26'],
    ['color' => 'giallo', 'kw' => 24, 'start' => '2026-06-08'],
    ['color' => 'grigio', 'kw' => 26, 'start' => '2026-06-22'],
    ['color' => 'rosso', 'kw' => 28, 'start' => '2026-07-06'],
    ['color' => 'viola', 'kw' => 32, 'start' => '2026-08-03'],
    ['color' => 'giallo', 'kw' => 34, 'start' => '2026-08-17'],
    ['color' => 'grigio', 'kw' => 36, 'start' => '2026-08-31'],
    ['color' => 'rosso', 'kw' => 38, 'start' => '2026-09-14'],
    ['color' => 'viola', 'kw' => 42, 'start' => '2026-10-12'],
    ['color' => 'giallo', 'kw' => 44, 'start' => '2026-10-26'],
    ['color' => 'grigio', 'kw' => 46, 'start' => '2026-11-09'],
    ['color' => 'rosso', 'kw' => 48, 'start' => '2026-11-23'],
];

$color_names = [
    'giallo' => 'Giallo',
    'grigio' => 'Grigio',
    'rosso' => 'Rosso',
    'marrone' => 'Marrone',
    'viola' => 'Viola',
];

$color_hex = [
    'giallo' => '#FFFF00',
    'grigio' => '#808080',
    'rosso' => '#FF0000',
    'marrone' => '#996633',
    'viola' => '#7030A0',
];

$color_emoji = [
    'giallo' => '🟡',
    'grigio' => '⚫',
    'rosso' => '🔴',
    'marrone' => '🟤',
    'viola' => '🟣',
];

// Statistiche attuali
$stats = new stdClass();
$stats->groups = $DB->count_records('local_ftm_groups');
$stats->activities = $DB->count_records('local_ftm_activities');
$stats->external = $DB->count_records('local_ftm_external_bookings');

echo '<h2>📊 Stato Attuale Database</h2>';
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">';
echo '<div style="text-align: center;"><strong style="font-size: 28px;">' . $stats->groups . '</strong><br><small>Gruppi</small></div>';
echo '<div style="text-align: center;"><strong style="font-size: 28px;">' . $stats->activities . '</strong><br><small>Attività</small></div>';
echo '<div style="text-align: center;"><strong style="font-size: 28px;">' . $stats->external . '</strong><br><small>Esterni</small></div>';
echo '</div>';
echo '</div>';

if ($action === 'populate' && confirm_sesskey()) {
    // ==========================================================================
    // ESECUZIONE POPOLAMENTO COMPLETO
    // ==========================================================================
    echo '<h2>🚀 Popolamento ANNO 2026 COMPLETO...</h2>';
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 8px; border: 1px solid #28a745; margin-bottom: 20px; max-height: 700px; overflow-y: auto;">';
    echo '<pre style="margin: 0; white-space: pre-wrap; font-size: 11px;">';
    
    $now = time();
    $total_groups = 0;
    $total_activities = 0;
    
    // Recupera aule
    $aula1 = $DB->get_record('local_ftm_rooms', ['shortname' => 'A1']);
    $aula2 = $DB->get_record('local_ftm_rooms', ['shortname' => 'A2']);
    $aula1_id = $aula1 ? $aula1->id : null;
    $aula2_id = $aula2 ? $aula2->id : null;
    
    // Template attività per ogni ciclo
    // day_offset: 0=Lun, 1=Mar, 2=Mer, 3=Gio, 4=Ven
    $week1_template = [
        ['day' => 0, 'slot' => 'matt', 'name' => 'Spiegazione percorso + Formulari iniziali'],
        ['day' => 0, 'slot' => 'pom', 'name' => 'Piano d\'azione + F3MAcademy + Stage'],
        ['day' => 1, 'slot' => 'matt', 'name' => 'Test Entrata + Autovalutazione'],
        ['day' => 1, 'slot' => 'pom', 'name' => 'Test Entrata + Autovalutazione (cont.)'],
        // Mer = REMOTO
        ['day' => 3, 'slot' => 'matt', 'name' => 'Atelier CVBD redazione'],
        ['day' => 3, 'slot' => 'pom', 'name' => 'CVBD + Job room'],
        ['day' => 4, 'slot' => 'matt', 'name' => 'Newsletter + Redazione documento'],
        // Ven pom = REMOTO
    ];
    
    $week2_template = [
        ['day' => 0, 'slot' => 'matt', 'name' => 'Test approfondimento (Coach GM/RB)', 'type' => 'week2_test'],
        ['day' => 0, 'slot' => 'pom', 'name' => 'Test approfondimento (Coach GM/RB)', 'type' => 'week2_test'],
        ['day' => 1, 'slot' => 'matt', 'name' => 'Lab pratico (Coach GM/RB)', 'type' => 'week2_lab', 'max' => 2],
        ['day' => 1, 'slot' => 'pom', 'name' => 'Lab pratico (Coach GM/RB)', 'type' => 'week2_lab', 'max' => 2],
        // Mer = REMOTO
        ['day' => 3, 'slot' => 'matt', 'name' => 'Test approfondimento (Coach CB/FM)', 'type' => 'week2_test'],
        ['day' => 3, 'slot' => 'pom', 'name' => 'Test approfondimento (Coach CB/FM)', 'type' => 'week2_test'],
        ['day' => 4, 'slot' => 'matt', 'name' => 'Lab pratico (Coach CB/FM)', 'type' => 'week2_lab', 'max' => 2],
        ['day' => 4, 'slot' => 'pom', 'name' => 'Lab pratico (Coach CB/FM)', 'type' => 'week2_lab', 'max' => 2],
    ];
    
    $atelier_template = [
        // Settimana 3: Canali + Colloquio
        ['week' => 3, 'day' => 2, 'slot' => 'matt', 'name' => 'At. Canali - strumenti e mercato del lavoro'],
        ['week' => 3, 'day' => 2, 'slot' => 'pom', 'name' => 'At. Collo. - Colloquio di lavoro'],
        // Settimana 4: Agenzie GI
        ['week' => 4, 'day' => 2, 'slot' => 'matt', 'name' => 'At. Ag. e GI - Agenzie e guadagno intermedio'],
        ['week' => 4, 'day' => 2, 'slot' => 'pom', 'name' => 'At. Ag. e GI (cont.)'],
        // Settimana 5: CV
        ['week' => 5, 'day' => 2, 'slot' => 'matt', 'name' => 'At. CV - Curriculum Vitae redazione/revisione'],
        ['week' => 5, 'day' => 2, 'slot' => 'pom', 'name' => 'At. CV (cont.)'],
        // Settimana 6: AC/RA + Bilancio
        ['week' => 6, 'day' => 2, 'slot' => 'matt', 'name' => 'At. AC/RA - Lettere AC + RA'],
        ['week' => 6, 'day' => 2, 'slot' => 'pom_early', 'name' => 'At. AC/RA (cont.)', 'time_end' => '15:00'],
        ['week' => 6, 'day' => 2, 'slot' => 'pom_late', 'name' => '⭐ BILANCIO DI FINE MISURA', 'type' => 'bilancio', 'time_start' => '15:00'],
    ];
    
    // Funzione helper
    function ftm_create_activity_full($DB, $name, $type, $groupid, $week, $date_start, $date_end, $roomid, $max, $now, $userid) {
        $exists = $DB->get_record('local_ftm_activities', [
            'groupid' => $groupid,
            'date_start' => $date_start,
        ]);
        
        if ($exists) {
            return false;
        }
        
        $activity = new stdClass();
        $activity->name = $name;
        $activity->activity_type = $type;
        $activity->groupid = $groupid;
        $activity->target_week = $week;
        $activity->date_start = $date_start;
        $activity->date_end = $date_end;
        $activity->roomid = $roomid;
        $activity->max_participants = $max;
        $activity->status = 'scheduled';
        $activity->has_conflict = 0;
        $activity->timecreated = $now;
        $activity->timemodified = $now;
        $activity->createdby = $userid;
        
        return $DB->insert_record('local_ftm_activities', $activity);
    }
    
    echo "═══════════════════════════════════════════════════════════════════════════════\n";
    echo "                    POPOLAMENTO ANNO 2026 - 19 CICLI\n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n\n";
    
    // Ciclo principale per ogni gruppo
    foreach ($year_cycles as $cycle) {
        $color = $cycle['color'];
        $kw = $cycle['kw'];
        $start_date = strtotime($cycle['start']);
        
        $emoji = $color_emoji[$color];
        $name = $color_names[$color];
        $hex = $color_hex[$color];
        
        echo "───────────────────────────────────────────────────────────────────────────────\n";
        echo "{$emoji} GRUPPO {$name} - KW{$kw} (Inizio: " . date('d/m/Y', $start_date) . ")\n";
        echo "───────────────────────────────────────────────────────────────────────────────\n";
        
        // Crea o trova il gruppo
        $group_name = "Gruppo {$name} - KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
        $existing = $DB->get_record('local_ftm_groups', ['name' => $group_name]);
        
        if ($existing) {
            echo "  ⚠️ Gruppo già esistente (ID: {$existing->id})\n";
            $groupid = $existing->id;
        } else {
            $group = new stdClass();
            $group->name = $group_name;
            $group->color = $color;
            $group->color_hex = $hex;
            $group->entry_date = $start_date;
            $group->planned_end_date = strtotime('+41 days', $start_date);
            $group->calendar_week = $kw;
            $group->status = ($start_date <= time()) ? 'active' : 'planning';
            $group->timecreated = $now;
            $group->timemodified = $now;
            $group->createdby = $USER->id;
            
            $groupid = $DB->insert_record('local_ftm_groups', $group);
            echo "  ✅ Gruppo creato (ID: {$groupid})\n";
            $total_groups++;
        }
        
        // ========== SETTIMANA 1 ==========
        echo "  📅 Settimana 1:\n";
        $week1_monday = $start_date;
        
        foreach ($week1_template as $tpl) {
            $activity_date = strtotime('+' . $tpl['day'] . ' days', $week1_monday);
            
            if ($tpl['slot'] === 'matt') {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 08:30:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 11:45:00');
            } else {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 13:15:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 16:30:00');
            }
            
            if (ftm_create_activity_full($DB, $tpl['name'], 'week1', $groupid, 1, $time_start, $time_end, $aula2_id, 10, $now, $USER->id)) {
                $slot = strtoupper($tpl['slot']);
                echo "     ✓ " . date('D d/m', $activity_date) . " {$slot}\n";
                $total_activities++;
            }
        }
        
        // ========== SETTIMANA 2 ==========
        echo "  📅 Settimana 2:\n";
        $week2_monday = strtotime('+7 days', $week1_monday);
        
        foreach ($week2_template as $tpl) {
            $activity_date = strtotime('+' . $tpl['day'] . ' days', $week2_monday);
            
            if ($tpl['slot'] === 'matt') {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 08:30:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 11:45:00');
            } else {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 13:15:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 16:30:00');
            }
            
            $type = $tpl['type'] ?? 'week2_test';
            $max = $tpl['max'] ?? 10;
            
            if (ftm_create_activity_full($DB, $tpl['name'], $type, $groupid, 2, $time_start, $time_end, $aula2_id, $max, $now, $USER->id)) {
                $slot = strtoupper($tpl['slot']);
                echo "     ✓ " . date('D d/m', $activity_date) . " {$slot}\n";
                $total_activities++;
            }
        }
        
        // ========== SETTIMANE 3-6 (ATELIER) ==========
        echo "  📅 Settimane 3-6 (Atelier):\n";
        
        foreach ($atelier_template as $tpl) {
            $week_num = $tpl['week'];
            $week_monday = strtotime('+' . (($week_num - 1) * 7) . ' days', $week1_monday);
            $activity_date = strtotime('+' . $tpl['day'] . ' days', $week_monday);
            
            // Gestione slot speciali per bilancio
            if ($tpl['slot'] === 'matt') {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 08:30:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 11:45:00');
            } elseif ($tpl['slot'] === 'pom_early') {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 13:15:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' ' . ($tpl['time_end'] ?? '16:30') . ':00');
            } elseif ($tpl['slot'] === 'pom_late') {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' ' . ($tpl['time_start'] ?? '15:00') . ':00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 16:30:00');
            } else {
                $time_start = strtotime(date('Y-m-d', $activity_date) . ' 13:15:00');
                $time_end = strtotime(date('Y-m-d', $activity_date) . ' 16:30:00');
            }
            
            $type = $tpl['type'] ?? 'atelier';
            $room = ($type === 'bilancio') ? $aula1_id : $aula2_id;
            
            if (ftm_create_activity_full($DB, $tpl['name'], $type, $groupid, $week_num, $time_start, $time_end, $room, 10, $now, $USER->id)) {
                $star = ($type === 'bilancio') ? '⭐' : '✓';
                echo "     {$star} Sett.{$week_num} " . date('D d/m', $activity_date) . "\n";
                $total_activities++;
            }
        }
        
        echo "\n";
    }
    
    // ========== PROGETTI ESTERNI ==========
    echo "═══════════════════════════════════════════════════════════════════════════════\n";
    echo "🏢 PROGETTI ESTERNI\n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n";
    
    // Genera progetti esterni per ogni mercoledì dell'anno
    $external_projects = [];
    $current = strtotime('2026-01-01');
    $year_end = strtotime('2026-12-31');
    
    while ($current <= $year_end) {
        // Se è mercoledì
        if (date('N', $current) == 3) {
            $kw = date('W', $current);
            
            // Alterna BIT URAR e altri progetti
            if ($kw % 2 == 0) {
                $external_projects[] = ['date' => date('Y-m-d', $current), 'name' => 'BIT URAR', 'responsible' => 'GM'];
            } else {
                $external_projects[] = ['date' => date('Y-m-d', $current), 'name' => 'Corso Extra LADI', 'responsible' => 'RB'];
            }
        }
        $current = strtotime('+1 day', $current);
    }
    
    $total_external = 0;
    foreach ($external_projects as $proj) {
        $date_start = strtotime($proj['date'] . ' 08:30:00');
        $date_end = strtotime($proj['date'] . ' 16:30:00');
        
        $exists = $DB->get_record('local_ftm_external_bookings', [
            'project_name' => $proj['name'],
            'date_start' => $date_start
        ]);
        
        if (!$exists) {
            $booking = new stdClass();
            $booking->project_name = $proj['name'];
            $booking->roomid = $aula1_id;
            $booking->date_start = $date_start;
            $booking->date_end = $date_end;
            $booking->responsible = $proj['responsible'];
            $booking->recurring = 'none';
            $booking->timecreated = $now;
            $booking->createdby = $USER->id;
            
            $DB->insert_record('local_ftm_external_bookings', $booking);
            $total_external++;
        }
    }
    
    echo "  ✅ Creati {$total_external} progetti esterni (mercoledì)\n";
    
    echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
    echo "                         ✅ POPOLAMENTO COMPLETATO!\n";
    echo "═══════════════════════════════════════════════════════════════════════════════\n";
    echo "\n  📊 Riepilogo:\n";
    echo "     • Gruppi creati: {$total_groups}\n";
    echo "     • Attività create: {$total_activities}\n";
    echo "     • Progetti esterni: {$total_external}\n";
    
    echo '</pre>';
    echo '</div>';
    
    // Nuove statistiche
    $stats->groups = $DB->count_records('local_ftm_groups');
    $stats->activities = $DB->count_records('local_ftm_activities');
    $stats->external = $DB->count_records('local_ftm_external_bookings');
    
    echo '<h3>📊 Nuove Statistiche</h3>';
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 8px; border: 1px solid #28a745;">';
    echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">';
    echo '<div style="text-align: center;"><strong style="font-size: 28px; color: #28a745;">' . $stats->groups . '</strong><br><small>Gruppi</small></div>';
    echo '<div style="text-align: center;"><strong style="font-size: 28px; color: #28a745;">' . $stats->activities . '</strong><br><small>Attività</small></div>';
    echo '<div style="text-align: center;"><strong style="font-size: 28px; color: #28a745;">' . $stats->external . '</strong><br><small>Esterni</small></div>';
    echo '</div>';
    echo '</div>';
    
    echo '<p style="margin-top: 20px;">';
    echo '<a href="' . new moodle_url('/local/ftm_scheduler/index.php') . '" class="btn btn-primary" style="margin-right: 10px;">🚀 Vai allo Scheduler</a>';
    echo '</p>';
    
} else {
    // ==========================================================================
    // FORM INIZIALE
    // ==========================================================================
    echo '<h2>🗓️ Popola Database - ANNO 2026 COMPLETO</h2>';
    
    echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; border: 1px solid #2196f3; margin-bottom: 20px;">';
    echo '<h4>📅 Questa azione creerà l\'INTERO ANNO 2026:</h4>';
    
    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">';
    echo '<tr style="background: #2196f3; color: white;">';
    echo '<th style="padding: 8px; text-align: left;">Gruppo</th>';
    echo '<th style="padding: 8px;">KW</th>';
    echo '<th style="padding: 8px;">Data Inizio</th>';
    echo '<th style="padding: 8px;">Data Fine</th>';
    echo '</tr>';
    
    $row_colors = ['#fff', '#f5f5f5'];
    $i = 0;
    foreach ($year_cycles as $cycle) {
        $emoji = $color_emoji[$cycle['color']];
        $name = $color_names[$cycle['color']];
        $start = strtotime($cycle['start']);
        $end = strtotime('+41 days', $start);
        $bg = $row_colors[$i % 2];
        
        echo "<tr style='background: {$bg};'>";
        echo "<td style='padding: 8px;'><strong>{$emoji} {$name}</strong></td>";
        echo "<td style='padding: 8px; text-align: center;'>KW" . str_pad($cycle['kw'], 2, '0', STR_PAD_LEFT) . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . date('d/m/Y', $start) . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . date('d/m/Y', $end) . "</td>";
        echo "</tr>";
        $i++;
    }
    echo '</table>';
    
    echo '<div style="margin-top: 20px; padding: 15px; background: #bbdefb; border-radius: 6px;">';
    echo '<strong>📊 Totale previsto:</strong><br>';
    echo '• <strong>19 gruppi</strong> (5 cicli Giallo, 5 Grigio, 5 Rosso, 4 Viola)<br>';
    echo '• <strong>~450 attività</strong> (24 per gruppo × 19 cicli)<br>';
    echo '• <strong>~52 progetti esterni</strong> (ogni mercoledì)<br>';
    echo '</div>';
    
    echo '<p style="margin-top: 15px; color: #666;">I dati esistenti NON verranno sovrascritti.</p>';
    echo '</div>';
    
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="action" value="populate">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<button type="submit" class="btn btn-success" style="padding: 20px 40px; font-size: 18px;">🚀 Popola TUTTO l\'Anno 2026</button>';
    echo '</form>';
}

echo '</div>';

echo $OUTPUT->footer();
