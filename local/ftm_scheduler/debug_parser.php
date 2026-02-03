<?php
/**
 * Debug: mostra cosa legge il parser per ogni riga
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_parser.php'));
$PAGE->set_title('Debug Parser');

echo $OUTPUT->header();
echo '<h2>🔍 Debug Parser - Cosa legge da ogni riga</h2>';

// Form per upload
if (!isset($_POST['upload'])) {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="excelfile" accept=".xlsx,.xls" class="form-control mb-2">';
    echo '<button type="submit" name="upload" class="btn btn-primary">Carica e Analizza</button>';
    echo '</form>';
    echo $OUTPUT->footer();
    exit;
}

if (!isset($_FILES['excelfile']) || !is_uploaded_file($_FILES['excelfile']['tmp_name'])) {
    echo '<div class="alert alert-danger">Errore upload</div>';
    echo $OUTPUT->footer();
    exit;
}

$filepath = $_FILES['excelfile']['tmp_name'];
require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');

// Coach map
$coach_map = ['CB', 'FM', 'GM', 'RB', 'DB', 'SANDRA', 'ALE', 'LP', 'NC'];

// Group patterns
$group_patterns = ['GR. GIALLO', 'GR. GRIGIO', 'GR. ROSSO', 'GR. MARRONE', 'GR. VIOLA',
                   'GIALLO', 'GRIGIO', 'ROSSO', 'MARRONE', 'VIOLA'];

// Activity types
$activity_types = ['LABORATORIO', 'BILANCIO', 'OML', 'ATELIER', 'STAGE', 'TEST', 'AT.'];

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($filepath);

    // Find February sheet
    $sheet = null;
    foreach ($spreadsheet->getSheetNames() as $name) {
        if (stripos($name, 'febbra') !== false) {
            $sheet = $spreadsheet->getSheetByName($name);
            break;
        }
    }
    if (!$sheet) $sheet = $spreadsheet->getSheet(0);

    echo '<p>Foglio: <strong>' . $sheet->getTitle() . '</strong></p>';

    // PASS 1: Build coach-group map per week
    // CORRECT MAPPING (verified from debug output):
    // M = Coach (GM, FM, RB, CB)
    // N = Group/Activity (GR. GRIGIO, At. Canali, LABORATORIO)
    $coachGroupMap = [];
    $tempDate = null;
    for ($r = 1; $r <= 100; $r++) {
        $dateVal = $sheet->getCell('A' . $r)->getCalculatedValue();
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $tempDate = $dt->getTimestamp();
        }

        $slot = strtolower(trim($sheet->getCell('C' . $r)->getCalculatedValue() ?? ''));
        if (strpos($slot, 'matt') === false && strpos($slot, 'pom') === false) {
            continue;
        }

        if (!$tempDate) continue;

        $yearWeek = date('Y-\WW', $tempDate);
        $colM = strtoupper(trim($sheet->getCell('M' . $r)->getCalculatedValue() ?? '')); // Coach
        $colN = strtoupper(trim($sheet->getCell('N' . $r)->getCalculatedValue() ?? '')); // Group/Activity

        // Coach from column M
        $coach = in_array($colM, $coach_map) ? $colM : null;

        // Group from column N
        $groupColor = null;
        foreach ($group_patterns as $pattern) {
            if (strpos($colN, $pattern) !== false) {
                $groupColor = $pattern;
                break;
            }
        }

        if ($coach && $groupColor) {
            if (!isset($coachGroupMap[$yearWeek])) {
                $coachGroupMap[$yearWeek] = [];
            }
            $coachGroupMap[$yearWeek][$coach] = $groupColor;
        }
    }

    // Show coach-group map
    echo '<h3>🗓️ Mappa Coach → Gruppo per Settimana</h3>';
    echo '<p><em>Questa mappa viene usata per assegnare il gruppo corretto ad attività come LABORATORIO</em></p>';
    echo '<table class="table table-bordered table-sm" style="font-size:11px; max-width:600px;">';
    echo '<tr><th>Settimana</th><th>Coach → Gruppo</th></tr>';
    foreach ($coachGroupMap as $week => $coaches) {
        echo '<tr><td><strong>' . $week . '</strong></td><td>';
        $pairs = [];
        foreach ($coaches as $c => $g) {
            $pairs[] = '<span style="background:#87CEEB;padding:2px 5px;">' . $c . '</span> → <span style="background:#FFFF00;padding:2px 5px;">' . $g . '</span>';
        }
        echo implode(', ', $pairs);
        echo '</td></tr>';
    }
    echo '</table>';
    echo '<hr>';

    echo '<h3>Analisi righe con Matt/Pom</h3>';
    echo '<p><em>Mapping corretto: M = Coach, N = Gruppo/Attività</em></p>';
    echo '<table class="table table-bordered table-sm" style="font-size:11px;">';
    echo '<tr>';
    echo '<th>Row</th><th>Slot</th>';
    echo '<th style="background:#cce5ff">M (Coach)</th>';
    echo '<th style="background:#fff3cd">N (Gruppo/Att.)</th>';
    echo '<th style="background:#e0e0e0">O (Aula3)</th>';
    echo '<th style="background:#d4edda">Coach Rilevato</th>';
    echo '<th style="background:#d4edda">Gruppo</th>';
    echo '<th style="background:#d4edda">Attività</th>';
    echo '<th>Gruppo Inferito?</th>';
    echo '</tr>';

    $currentDate = null;
    $currentTimestamp = null;
    $count = 0;

    for ($row = 1; $row <= 100 && $count < 30; $row++) {
        // Check date
        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $currentDate = $dt->format('d/m');
            $currentTimestamp = $dt->getTimestamp();
        }

        // Check slot
        $slot = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue() ?? ''));
        if (strpos($slot, 'matt') === false && strpos($slot, 'pom') === false) {
            continue;
        }

        $count++;

        // Read columns - CORRECT mapping (verified from debug output):
        // M = Coach, N = Group/Activity, O = Aula 3 Coach
        $colM = strtoupper(trim($sheet->getCell('M' . $row)->getCalculatedValue() ?? '')); // Coach
        $colN = trim($sheet->getCell('N' . $row)->getCalculatedValue() ?? ''); // Group/Activity
        $colNUpper = strtoupper($colN);
        $colO = strtoupper(trim($sheet->getCell('O' . $row)->getCalculatedValue() ?? '')); // Aula 3 Coach

        // Find coach from column M
        $coach = '-';
        $coachInitials = null;
        if (in_array($colM, $coach_map)) {
            $coach = $colM;
            $coachInitials = $colM;
        }

        // Find explicit group from N
        $group = '-';
        $hasExplicitGroup = false;
        foreach ($group_patterns as $pattern) {
            if (strpos($colNUpper, $pattern) !== false) {
                $group = $colN;
                $hasExplicitGroup = true;
                break;
            }
        }

        // Find activity from N
        $activity = '-';
        $hasActivity = false;
        foreach ($activity_types as $type) {
            if (strpos($colNUpper, $type) !== false) {
                $activity = $colN;
                $hasActivity = true;
                break;
            }
        }

        // Try to infer group from coach-group map if no explicit group
        $inferredGroup = '-';
        if (!$hasExplicitGroup && $hasActivity && $currentTimestamp) {
            $yearWeek = date('Y-\WW', $currentTimestamp);

            // First try the specific coach's group
            if ($coachInitials && isset($coachGroupMap[$yearWeek][$coachInitials])) {
                $inferredGroup = $coachGroupMap[$yearWeek][$coachInitials] . ' (da ' . $coachInitials . ')';
                $group = $inferredGroup;
            }
            // Fallback: use ANY group active in the same week
            elseif (isset($coachGroupMap[$yearWeek]) && !empty($coachGroupMap[$yearWeek])) {
                $weekGroups = array_keys($coachGroupMap[$yearWeek]);
                $firstCoach = $weekGroups[0];
                $inferredGroup = $coachGroupMap[$yearWeek][$firstCoach] . ' (da settimana)';
                $group = $inferredGroup;
            }
        }

        // Highlight row based on status
        $hasCoach = ($coach !== '-');
        $hasGroup = ($group !== '-');
        $rowStyle = '';
        if ($hasExplicitGroup) {
            $rowStyle = 'background:#d4edda;'; // Green = explicit group
        } elseif ($inferredGroup !== '-') {
            $rowStyle = 'background:#fff3cd;'; // Yellow = inferred group
        } elseif ($hasCoach) {
            $rowStyle = ''; // Normal
        } else {
            $rowStyle = 'background:#f8d7da;'; // Red = no coach
        }

        echo '<tr style="' . $rowStyle . '">';
        echo '<td>' . $row . '</td>';
        echo '<td>' . $currentDate . ' ' . ucfirst($slot) . '</td>';
        echo '<td style="background:#cce5ff">' . htmlspecialchars($colM) . '</td>';
        echo '<td style="background:#fff3cd">' . htmlspecialchars($colN) . '</td>';
        echo '<td style="background:#e0e0e0">' . htmlspecialchars($colO) . '</td>';
        echo '<td><strong>' . $coach . '</strong></td>';
        echo '<td><strong>' . htmlspecialchars($group) . '</strong></td>';
        echo '<td><strong>' . htmlspecialchars($activity) . '</strong></td>';
        echo '<td>' . ($inferredGroup !== '-' ? '⚡ ' . $inferredGroup : ($hasExplicitGroup ? '✅ Esplicito' : '-')) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<h4>Legenda Colori Riga:</h4>';
    echo '<ul>';
    echo '<li><span style="background:#d4edda;padding:2px 8px;">Verde</span> = Gruppo ESPLICITO nella colonna N (es. GR. GRIGIO)</li>';
    echo '<li><span style="background:#fff3cd;padding:2px 8px;">Giallo</span> = Gruppo INFERITO dalla mappa Coach→Gruppo (es. LABORATORIO con FM → GRIGIO)</li>';
    echo '<li><span style="background:#ffffff;padding:2px 8px;border:1px solid #ccc;">Bianco</span> = Ha coach ma nessun gruppo rilevato</li>';
    echo '<li><span style="background:#f8d7da;padding:2px 8px;">Rosso</span> = Nessun coach, nessun gruppo</li>';
    echo '</ul>';
    echo '<h4>Come Funziona l\'Inferenza:</h4>';
    echo '<ol>';
    echo '<li>Nella prima passata, scansiono tutte le righe e costruisco la mappa Coach→Gruppo per settimana</li>';
    echo '<li>Quando coach GM ha "GR. GRIGIO" nella settimana 7, memorizzo: <code>2026-W07[GM] = GRIGIO</code></li>';
    echo '<li>Quando vedo "LABORATORIO" con coach GM → assegno GRIGIO (dal coach)</li>';
    echo '<li><strong>NUOVO:</strong> Se coach RB fa LABORATORIO ma RB non ha gruppo esplicito, uso il gruppo attivo della settimana (es. GRIGIO da GM)</li>';
    echo '</ol>';
    echo '<p><em>Esempio: 10/02 Matt - RB + LABORATORIO → GR. GRIGIO (da settimana) perché GM ha GR. GRIGIO in W07</em></p>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo $OUTPUT->footer();
