<?php
/**
 * Analyze Excel structure from screenshots.
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/analyze_excel.php'));
$PAGE->set_title('Analyze Excel Structure');

echo $OUTPUT->header();
echo '<h2>📊 Analisi Struttura Excel Planning</h2>';

$filepath = 'C:/Users/cristian.bodda/Fondazione Terzo Millennio/F3M - F3M/2026/1. V0.0120 Planning mensile attività formatori aggiornato 29.01.xlsx';

if (!file_exists($filepath)) {
    echo '<div class="alert alert-danger">File non trovato: ' . $filepath . '</div>';
    echo '<p>Carica manualmente:</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="excelfile" accept=".xlsx,.xls" class="form-control mb-2">';
    echo '<button type="submit" name="upload" class="btn btn-primary">Carica e Analizza</button>';
    echo '</form>';

    if (isset($_POST['upload']) && isset($_FILES['excelfile']) && is_uploaded_file($_FILES['excelfile']['tmp_name'])) {
        $filepath = $_FILES['excelfile']['tmp_name'];
    } else {
        echo $OUTPUT->footer();
        exit;
    }
}

require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($filepath);

    // Get February sheet
    $sheet = null;
    foreach ($spreadsheet->getSheetNames() as $name) {
        if (stripos($name, 'febbra') !== false) {
            $sheet = $spreadsheet->getSheetByName($name);
            break;
        }
    }
    if (!$sheet) $sheet = $spreadsheet->getSheet(0);

    echo '<div class="alert alert-info">Foglio: <strong>' . $sheet->getTitle() . '</strong></div>';

    // Show header rows (1-2)
    echo '<h3>Header (Righe 1-2)</h3>';
    echo '<table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr><th>Row</th>';
    foreach (range('A', 'R') as $col) {
        echo '<th>' . $col . '</th>';
    }
    echo '</tr>';

    for ($row = 1; $row <= 2; $row++) {
        echo '<tr><td><strong>' . $row . '</strong></td>';
        foreach (range('A', 'R') as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue());
            echo '<td>' . htmlspecialchars(substr($val, 0, 15)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';

    // Show first data rows
    echo '<h3>Prime Righe Dati (con Matt/Pom)</h3>';
    echo '<div style="overflow-x:auto;">';
    echo '<table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr><th>Row</th><th>Data</th><th>Slot</th>';
    foreach (range('D', 'R') as $col) {
        $style = '';
        if (in_array($col, ['J', 'K'])) $style = 'background:#e0e0e0;'; // Aula 1
        if (in_array($col, ['L', 'M'])) $style = 'background:#cce5ff;'; // Aula 2
        if (in_array($col, ['N', 'O'])) $style = 'background:#d4edda;'; // Aula 3
        if ($col == 'P') $style = 'background:#fff3cd;'; // Segreteria
        echo '<th style="' . $style . '">' . $col . '</th>';
    }
    echo '</tr>';

    $count = 0;
    $currentDate = '';
    for ($row = 3; $row <= 50 && $count < 15; $row++) {
        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $currentDate = $dt->format('d/m');
        }

        $slot = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue() ?? ''));
        if (strpos($slot, 'matt') === false && strpos($slot, 'pom') === false) {
            continue;
        }

        $count++;
        echo '<tr>';
        echo '<td>' . $row . '</td>';
        echo '<td>' . $currentDate . '</td>';
        echo '<td>' . ucfirst($slot) . '</td>';

        foreach (range('D', 'R') as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue());

            // Style based on content
            $cellStyle = '';
            $valUpper = strtoupper($val);

            // Coach initials
            if (in_array($valUpper, ['CB', 'FM', 'GM', 'RB', 'DB', 'NC', 'LP', 'SANDRA', 'ALE'])) {
                $cellStyle = 'background:#87CEEB;font-weight:bold;';
            }
            // Groups
            if (strpos($valUpper, 'GR.') !== false || strpos($valUpper, 'GRIGIO') !== false ||
                strpos($valUpper, 'GIALLO') !== false || strpos($valUpper, 'ROSSO') !== false ||
                strpos($valUpper, 'MARR') !== false || strpos($valUpper, 'VIOLA') !== false) {
                $cellStyle = 'background:#FFFF00;font-weight:bold;';
            }
            // Activities
            if (strpos($valUpper, 'AT.') !== false || strpos($valUpper, 'LABORATORIO') !== false ||
                strpos($valUpper, 'BILANCIO') !== false || strpos($valUpper, 'OML') !== false) {
                $cellStyle = 'background:#FFB6C1;font-weight:bold;';
            }
            // External
            if (strpos($valUpper, 'LADI') !== false || strpos($valUpper, 'BIT') !== false ||
                strpos($valUpper, 'URAR') !== false) {
                $cellStyle = 'background:#90EE90;font-weight:bold;';
            }

            echo '<td style="' . $cellStyle . '">' . htmlspecialchars(substr($val, 0, 15)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';

    // Summary of column mapping
    echo '<h3>🎯 Mapping Colonne Rilevato</h3>';
    echo '<table class="table table-bordered" style="max-width:600px;">';
    echo '<tr><th>Colonna</th><th>Contenuto</th><th>Esempi</th></tr>';

    // Scan to find what each column contains
    $columnExamples = [];
    for ($row = 3; $row <= 30; $row++) {
        $slot = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue() ?? ''));
        if (strpos($slot, 'matt') === false && strpos($slot, 'pom') === false) continue;

        foreach (range('D', 'R') as $col) {
            $val = strtoupper(trim((string)$sheet->getCell($col . $row)->getCalculatedValue()));
            if (empty($val)) continue;
            if (!isset($columnExamples[$col])) $columnExamples[$col] = [];
            if (!in_array($val, $columnExamples[$col]) && count($columnExamples[$col]) < 5) {
                $columnExamples[$col][] = $val;
            }
        }
    }

    foreach ($columnExamples as $col => $examples) {
        // Determine type
        $type = 'Unknown';
        $hasCoach = false;
        $hasGroup = false;
        $hasActivity = false;

        foreach ($examples as $ex) {
            if (in_array($ex, ['CB', 'FM', 'GM', 'RB', 'DB', 'NC', 'LP', 'SANDRA', 'ALE'])) $hasCoach = true;
            if (strpos($ex, 'GR.') !== false || strpos($ex, 'GRIGIO') !== false ||
                strpos($ex, 'GIALLO') !== false || strpos($ex, 'ROSSO') !== false) $hasGroup = true;
            if (strpos($ex, 'AT.') !== false || strpos($ex, 'LABORATORIO') !== false ||
                strpos($ex, 'BILANCIO') !== false) $hasActivity = true;
        }

        if ($hasCoach) $type = '👤 Coach';
        if ($hasGroup) $type = '🎨 Gruppo';
        if ($hasActivity) $type = '📋 Attività';
        if ($hasGroup && $hasActivity) $type = '🎨📋 Gruppo/Attività';

        $style = '';
        if ($hasCoach) $style = 'background:#87CEEB;';
        if ($hasGroup) $style = 'background:#FFFF00;';
        if ($hasActivity) $style = 'background:#FFB6C1;';

        echo '<tr style="' . $style . '">';
        echo '<td><strong>' . $col . '</strong></td>';
        echo '<td>' . $type . '</td>';
        echo '<td>' . implode(', ', array_slice($examples, 0, 4)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo $OUTPUT->footer();
