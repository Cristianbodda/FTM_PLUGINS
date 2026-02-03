<?php
/**
 * DIAGNOSI COMPLETA - Trova esattamente dove sta il problema
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/diagnose_problem.php'));
$PAGE->set_title('Diagnosi Problemi Import');

echo $OUTPUT->header();
echo "<h2 style='color:red;'>DIAGNOSI COMPLETA IMPORT CALENDARIO</h2>";
echo "<p>Timezone PHP: <strong>" . date_default_timezone_get() . "</strong></p>";
echo "<p>Timezone Moodle: <strong>" . (isset($CFG->timezone) ? $CFG->timezone : 'non impostato') . "</strong></p>";
echo "<p>Data/ora server: <strong>" . date('Y-m-d H:i:s') . "</strong></p>";

// Show what week the calendar would show
$testWeek = 6; // KW06 Feb 2026
$testYear = 2026;
$manager = new \local_ftm_scheduler\manager();
$week_dates = $manager::get_week_dates($testYear, $testWeek);

echo "<h3>TEST: Settimana KW06 2026 secondo get_week_dates():</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr style='background:#e0e0e0;'><th>Index</th><th>day_of_week</th><th>timestamp</th><th>date()</th><th>day name</th><th>date('N')</th></tr>";
foreach ($week_dates as $idx => $day) {
    $dayN = date('N', $day['timestamp']);
    $dayName = date('l', $day['timestamp']);
    $dateStr = date('d/m/Y H:i:s', $day['timestamp']);
    echo "<tr>";
    echo "<td>$idx</td>";
    echo "<td><strong>{$day['day_of_week']}</strong></td>";
    echo "<td>{$day['timestamp']}</td>";
    echo "<td>$dateStr</td>";
    echo "<td>$dayName</td>";
    echo "<td><strong>$dayN</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='background:#ffffcc; padding:10px;'><strong>IMPORTANTE:</strong> day_of_week nella tabella sopra DEVE corrispondere a date('N'). Se non corrisponde, ecco il bug!</p>";

// 1. CHECK DATABASE ACTIVITIES - TUTTE le attività di Febbraio 2026
echo "<hr><h3>1. ATTIVITA' FEBBRAIO 2026 NEL DATABASE</h3>";

// Query specifica per febbraio 2026
$feb_start = mktime(0, 0, 0, 2, 1, 2026);
$feb_end = mktime(23, 59, 59, 2, 28, 2026);

$activities = $DB->get_records_sql(
    "SELECT * FROM {local_ftm_activities} WHERE date_start >= ? AND date_start <= ? ORDER BY date_start ASC",
    [$feb_start, $feb_end]
);

echo "<p><strong>Query:</strong> Attività dal " . date('d/m/Y H:i', $feb_start) . " al " . date('d/m/Y H:i', $feb_end) . "</p>";

if (empty($activities)) {
    echo "<p style='color:red;'><strong>NESSUNA ATTIVITA' NEL DATABASE!</strong></p>";
} else {
    echo "<p>Trovate " . count($activities) . " attività (prime 30):</p>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse; font-size:12px;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th>ID</th><th>Nome</th><th>date_start (timestamp)</th><th>Data Calcolata</th><th>Giorno Settimana</th><th>Ora</th><th>groupid</th><th>activity_type</th>";
    echo "</tr>";

    foreach ($activities as $act) {
        $dateCalc = date('d/m/Y', $act->date_start);
        $dayName = date('l', $act->date_start);
        $dayNameIt = [
            'Monday' => 'Lunedì', 'Tuesday' => 'Martedì', 'Wednesday' => 'Mercoledì',
            'Thursday' => 'Giovedì', 'Friday' => 'Venerdì', 'Saturday' => 'Sabato', 'Sunday' => 'Domenica'
        ][$dayName] ?? $dayName;
        $time = date('H:i', $act->date_start);

        // Highlight Monday
        $style = ($dayName === 'Monday') ? 'background:#90EE90;' : '';
        if ($dayName === 'Tuesday') $style = 'background:#ffcccc;';

        echo "<tr style='$style'>";
        echo "<td>{$act->id}</td>";
        echo "<td>" . s(substr($act->name, 0, 30)) . "</td>";
        echo "<td><strong>{$act->date_start}</strong></td>";
        echo "<td><strong>$dateCalc</strong></td>";
        echo "<td><strong>$dayNameIt</strong></td>";
        echo "<td>$time</td>";
        echo "<td>" . ($act->groupid ?? '-') . "</td>";
        echo "<td>" . ($act->activity_type ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. CHECK EXTERNAL BOOKINGS
echo "<hr><h3>2. PRENOTAZIONI ESTERNE (BIT, URAR, LADI)</h3>";

if (!$DB->get_manager()->table_exists('local_ftm_external_bookings')) {
    echo "<p style='color:orange;'>Tabella local_ftm_external_bookings NON ESISTE!</p>";
} else {
    $externals = $DB->get_records('local_ftm_external_bookings', null, 'date_start ASC');

    if (empty($externals)) {
        echo "<p style='color:red;'><strong>NESSUNA PRENOTAZIONE ESTERNA!</strong></p>";
    } else {
        echo "<p>Trovate " . count($externals) . " prenotazioni esterne:</p>";
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        echo "<tr style='background:#dbeafe;'><th>ID</th><th>Progetto</th><th>Data</th><th>Giorno</th><th>Room</th></tr>";
        foreach ($externals as $ext) {
            $dateCalc = date('d/m/Y', $ext->date_start);
            $dayName = date('l', $ext->date_start);
            echo "<tr>";
            echo "<td>{$ext->id}</td>";
            echo "<td><strong>{$ext->project_name}</strong></td>";
            echo "<td>$dateCalc</td>";
            echo "<td>$dayName</td>";
            echo "<td>{$ext->roomid}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// 3. CHECK GROUPS
echo "<hr><h3>3. GRUPPI ATTIVI</h3>";

$groups = $DB->get_records('local_ftm_groups', null, 'name ASC');
if (empty($groups)) {
    echo "<p style='color:orange;'>Nessun gruppo nel database.</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Nome</th><th>Colore</th><th>Status</th><th>Entry Date</th></tr>";
    foreach ($groups as $g) {
        $entryDate = !empty($g->entry_date) ? date('d/m/Y', $g->entry_date) : '-';
        $statusStyle = ($g->status === 'active') ? 'background:#90EE90;' : '';
        echo "<tr style='$statusStyle'>";
        echo "<td>{$g->id}</td>";
        echo "<td>{$g->name}</td>";
        echo "<td><strong>{$g->color}</strong></td>";
        echo "<td>{$g->status}</td>";
        echo "<td>$entryDate</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. TEST DATE CONVERSION
echo "<hr><h3>4. TEST CONVERSIONE DATE</h3>";
echo "<p>Verifica che le date Excel vengano convertite correttamente:</p>";

$testSerials = [
    46055 => '02/02/2026 (Lunedì)',
    46056 => '03/02/2026 (Martedì)',
    46057 => '04/02/2026 (Mercoledì)',
    46058 => '05/02/2026 (Giovedì)',
    46059 => '06/02/2026 (Venerdì)',
];

echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
echo "<tr style='background:#f0f0f0;'><th>Excel Serial</th><th>Atteso</th><th>Calcolato PHP</th><th>Timestamp</th><th>OK?</th></tr>";

foreach ($testSerials as $serial => $expected) {
    // Convert using PhpSpreadsheet method
    $unixTimestamp = ($serial - 25569) * 86400; // Excel epoch to Unix
    $dateCalc = date('d/m/Y (l)', $unixTimestamp);
    $timestamp = $unixTimestamp;

    // Also try with mktime at noon
    $year = 2026;
    $month = 2;
    $day = $serial - 46053; // 46053 = 31/01/2026, so 46055 = 02/02
    $timestampNoon = mktime(12, 0, 0, $month, $day, $year);
    $dateNoon = date('d/m/Y (l)', $timestampNoon);

    $ok = (strpos($dateCalc, substr($expected, 0, 10)) !== false) ? '✅' : '❌';

    echo "<tr>";
    echo "<td>$serial</td>";
    echo "<td>$expected</td>";
    echo "<td>$dateCalc</td>";
    echo "<td>$timestamp</td>";
    echo "<td>$ok</td>";
    echo "</tr>";
}
echo "</table>";

// 5. CHECK WHAT CALENDAR VIEW SHOWS
echo "<hr><h3>5. TIMESTAMP ANALYSIS</h3>";
echo "<p>Se il Lunedì 02/02/2026 mostra come Martedì, verifichiamo i timestamp:</p>";

$feb2_2026 = mktime(12, 0, 0, 2, 2, 2026);
echo "<ul>";
echo "<li>mktime(12,0,0, 2, 2, 2026) = <strong>$feb2_2026</strong></li>";
echo "<li>date('d/m/Y l', $feb2_2026) = <strong>" . date('d/m/Y l', $feb2_2026) . "</strong></li>";
echo "<li>date('N', $feb2_2026) = <strong>" . date('N', $feb2_2026) . "</strong> (1=Lunedì, 7=Domenica)</li>";
echo "</ul>";

// Check if there's an off-by-one in the view
echo "<h4>Verifica inizio settimana:</h4>";
$weekStart = strtotime('monday this week', $feb2_2026);
echo "<ul>";
echo "<li>strtotime('monday this week', timestamp_02_feb) = " . date('d/m/Y l', $weekStart) . "</li>";
echo "</ul>";

// 6. QUICK FIX SUGGESTIONS
echo "<hr><h3>6. POSSIBILI CAUSE E SOLUZIONI</h3>";
echo "<div style='background:#ffffcc; padding:15px; border-radius:8px;'>";
echo "<h4>Se le date nel DB sono CORRETTE ma il calendario mostra SBAGLIATO:</h4>";
echo "<p>Il problema è nel <strong>codice di visualizzazione</strong> del calendario, non nell'import.</p>";
echo "<p>Devo controllare: <code>index.php</code>, <code>calendar_view.php</code>, o template Mustache.</p>";

echo "<h4>Se le date nel DB sono SBAGLIATE:</h4>";
echo "<p>Il problema è nell'<strong>import</strong>. Verifica i timestamp sopra.</p>";

echo "<h4>Se mancano i progetti esterni:</h4>";
echo "<p>I progetti esterni (BIT, URAR, LADI) potrebbero essere:</p>";
echo "<ul>";
echo "<li>In un foglio diverso del file Excel</li>";
echo "<li>In righe che NON hanno Matt/Pom nella colonna C</li>";
echo "<li>Scritti in modo diverso (es. 'BIT-URAR' invece di 'BIT URAR')</li>";
echo "</ul>";
echo "</div>";

// 7. CRITICAL TEST: Simula esattamente cosa vedrebbe il calendario
echo "<hr><h3 style='color:red;'>7. SIMULAZIONE CALENDARIO (CRITICO!)</h3>";
echo "<p>Questo mostra esattamente dove il calendario metterebbe ogni attività:</p>";

// Get activities for KW06 2026
$monday_ts = $week_dates[0]['timestamp'];
$friday_ts = $week_dates[4]['timestamp'] + 86400;

$week_activities = $DB->get_records_sql(
    "SELECT * FROM {local_ftm_activities} WHERE date_start >= ? AND date_start <= ? ORDER BY date_start",
    [$monday_ts, $friday_ts]
);

if (empty($week_activities)) {
    echo "<p style='color:orange;'>Nessuna attività nella settimana KW06 2026 (dal " . date('d/m/Y', $monday_ts) . " al " . date('d/m/Y', $friday_ts) . ")</p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#ff6b6b; color:white;'>";
    echo "<th>ID</th><th>Nome</th><th>date_start</th><th>date('d/m/Y')</th><th>date('l')</th><th>date('N')</th><th>COLONNA CALENDARIO</th><th>CORRETTO?</th>";
    echo "</tr>";

    $dayNames = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

    foreach ($week_activities as $act) {
        $dateStr = date('d/m/Y', $act->date_start);
        $dayNameEN = date('l', $act->date_start);
        $dayN = date('N', $act->date_start);
        $colName = $dayNames[$dayN] ?? "N/A";

        // Verifica se è corretto
        // Se l'attività dice 02/02/2026 e date('N') restituisce 1, è corretto
        // Se dice 02/02/2026 ma date('N') restituisce 2, c'è un BUG
        $expectedDay = date('N', $act->date_start); // What PHP thinks the day is
        $dateOnly = date('d/m/Y', $act->date_start);

        // Check what day of week Feb 2, 2026 actually is
        $feb2check = mktime(12, 0, 0, 2, 2, 2026);
        $isFeb2 = (date('d/m/Y', $act->date_start) === '02/02/2026');

        $correct = '✅';
        $rowStyle = 'background:#90EE90;';

        // If it's Feb 2 but shows as Tuesday, that's WRONG
        if ($isFeb2 && $dayN != 1) {
            $correct = "❌ ERRORE! Feb 2 è Lunedì ma date('N') dice $dayN";
            $rowStyle = 'background:#ff6b6b; color:white;';
        }

        echo "<tr style='$rowStyle'>";
        echo "<td>{$act->id}</td>";
        echo "<td>" . s(substr($act->name, 0, 25)) . "</td>";
        echo "<td>{$act->date_start}</td>";
        echo "<td><strong>$dateStr</strong></td>";
        echo "<td>$dayNameEN</td>";
        echo "<td><strong>$dayN</strong></td>";
        echo "<td><strong>$colName</strong></td>";
        echo "<td>$correct</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test critical: February 2, 2026
echo "<hr><h3 style='color:red;'>8. TEST CRITICO: 2 Febbraio 2026</h3>";
$testTs = mktime(8, 30, 0, 2, 2, 2026);
echo "<table border='1' cellpadding='10' style='border-collapse:collapse; font-size:16px;'>";
echo "<tr><td>Timestamp 02/02/2026 08:30</td><td><strong>$testTs</strong></td></tr>";
echo "<tr><td>date('d/m/Y', ts)</td><td><strong>" . date('d/m/Y', $testTs) . "</strong></td></tr>";
echo "<tr><td>date('l', ts) (giorno EN)</td><td><strong>" . date('l', $testTs) . "</strong></td></tr>";
echo "<tr><td>date('N', ts) (1=Lun)</td><td style='font-size:24px; background:#90EE90;'><strong>" . date('N', $testTs) . "</strong></td></tr>";
echo "</table>";

if (date('N', $testTs) == 1) {
    echo "<p style='background:#90EE90; padding:15px; font-size:18px;'>✅ <strong>PHP riconosce correttamente il 02/02/2026 come LUNEDI (N=1)</strong></p>";
} else {
    echo "<p style='background:#ff6b6b; color:white; padding:15px; font-size:18px;'>❌ <strong>BUG PHP! Il 02/02/2026 NON viene riconosciuto come Lunedì!</strong></p>";
}

// 9. ACTION BUTTONS
echo "<hr><h3>9. AZIONI RAPIDE</h3>";
echo "<p>";
echo "<a href='import_calendar.php' style='padding:10px 20px; background:#0066cc; color:white; text-decoration:none; border-radius:6px; margin-right:10px;'>Vai all'Import</a>";
echo "<a href='debug_import.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:6px; margin-right:10px;'>Debug Excel</a>";
echo "<a href='index.php?tab=calendario&view=week&week=6&year=2026' style='padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:6px;'>Vai al Calendario KW06</a>";
echo "</p>";

echo $OUTPUT->footer();
