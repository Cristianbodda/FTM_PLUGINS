<?php
/**
 * Debug script to see Excel file structure.
 * DELETE THIS FILE AFTER TESTING.
 *
 * @package    local_ftm_scheduler
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_excel.php'));
$PAGE->set_title('Debug Excel Import');

echo $OUTPUT->header();

echo "<h2>Debug Excel Import</h2>";

// Simple upload form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excelfile'])) {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<p>Carica il file Excel per vedere la struttura:</p>';
    echo '<input type="file" name="excelfile" accept=".xlsx,.xls">';
    echo '<br><br>';
    echo '<label>Foglio da analizzare: <input type="text" name="sheet" value="Gennaio" size="20"></label>';
    echo '<br><br>';
    echo '<label>Righe da mostrare: <input type="number" name="rows" value="50" size="5"></label>';
    echo '<br><br>';
    echo '<button type="submit">Analizza</button>';
    echo '</form>';
    echo $OUTPUT->footer();
    die();
}

require_sesskey();

if ($_FILES['excelfile']['error'] !== UPLOAD_ERR_OK) {
    echo "<p style='color:red'>Errore upload: " . $_FILES['excelfile']['error'] . "</p>";
    echo $OUTPUT->footer();
    die();
}

$sheetname = optional_param('sheet', 'Gennaio', PARAM_TEXT);
$maxrows = optional_param('rows', 50, PARAM_INT);

// Move to temp.
$tempdir = make_temp_directory('ftm_debug');
$tempfile = $tempdir . '/debug_' . time() . '.xlsx';
move_uploaded_file($_FILES['excelfile']['tmp_name'], $tempfile);

echo "<h3>File: " . s($_FILES['excelfile']['name']) . "</h3>";

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempfile);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tempfile);

    // Show all sheet names.
    $sheets = $spreadsheet->getSheetNames();
    echo "<h3>Fogli disponibili:</h3><ul>";
    foreach ($sheets as $s) {
        $selected = ($s === $sheetname) ? ' <strong>(SELEZIONATO)</strong>' : '';
        echo "<li>" . s($s) . $selected . "</li>";
    }
    echo "</ul>";

    // Get the requested sheet.
    $sheet = $spreadsheet->getSheetByName($sheetname);
    if (!$sheet) {
        $sheet = $spreadsheet->getSheet(0);
        echo "<p style='color:orange'>Foglio '$sheetname' non trovato, uso il primo foglio: " . s($sheet->getTitle()) . "</p>";
    }

    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    echo "<h3>Foglio: " . s($sheet->getTitle()) . "</h3>";
    echo "<p>Righe: $highestRow | Colonne: $highestColumn ($highestColumnIndex)</p>";

    echo "<h3>Prime $maxrows righe:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; font-size:12px;'>";

    // Header row with column letters.
    echo "<tr style='background:#f0f0f0'><th>Riga</th>";
    for ($col = 1; $col <= min($highestColumnIndex, 15); $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        echo "<th>$colLetter</th>";
    }
    echo "</tr>";

    // Data rows.
    for ($row = 1; $row <= min($highestRow, $maxrows); $row++) {
        $rowData = [];
        $hasData = false;

        for ($col = 1; $col <= min($highestColumnIndex, 15); $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cell = $sheet->getCell($colLetter . $row);
            $value = $cell->getValue();

            // Handle dates.
            if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($value)) {
                $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                $value = $dateValue->format('d/m/Y H:i');
            }

            $rowData[$colLetter] = $value;
            if (!empty($value)) {
                $hasData = true;
            }
        }

        // Color rows based on content.
        $style = '';
        $cellA = strtolower(trim($rowData['A'] ?? ''));

        // Check if it looks like a date row.
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $rowData['A'] ?? '')) {
            $style = 'background:#ffffcc;'; // Yellow for dates.
        } elseif (preg_match('/(luned|marted|mercoled|gioved|venerd|sabato|domenica)/i', $cellA)) {
            $style = 'background:#ffffcc;'; // Yellow for day names.
        } elseif (strpos($cellA, 'matt') !== false || $cellA === 'm') {
            $style = 'background:#ccffcc;'; // Green for morning.
        } elseif (strpos($cellA, 'pom') !== false || $cellA === 'p') {
            $style = 'background:#ccccff;'; // Blue for afternoon.
        }

        if ($hasData) {
            echo "<tr style='$style'>";
            echo "<td><strong>$row</strong></td>";
            foreach ($rowData as $val) {
                $display = s(substr((string)$val, 0, 30));
                if (strlen((string)$val) > 30) {
                    $display .= '...';
                }
                echo "<td>$display</td>";
            }
            echo "</tr>";
        }
    }

    echo "</table>";

    echo "<h3>Legenda colori:</h3>";
    echo "<ul>";
    echo "<li style='background:#ffffcc; padding:5px;'>Giallo = Riga con data</li>";
    echo "<li style='background:#ccffcc; padding:5px;'>Verde = Mattina (Matt/M)</li>";
    echo "<li style='background:#ccccff; padding:5px;'>Blu = Pomeriggio (Pom/P)</li>";
    echo "</ul>";

    echo "<h3>Analisi struttura:</h3>";
    echo "<p>Il parser cerca:</p>";
    echo "<ol>";
    echo "<li>Righe con date (es. 'lunedì, 5 gennaio' o '05/01/2026')</li>";
    echo "<li>Righe con 'Matt' o 'Pom' nella colonna A</li>";
    echo "<li>Coach (CB, FM, GM, RB) nelle colonne successive</li>";
    echo "<li>Gruppi (GR. GIALLO, etc.) nelle colonne</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<p style='color:red'>Errore: " . s($e->getMessage()) . "</p>";
    echo "<pre>" . s($e->getTraceAsString()) . "</pre>";
}

// Cleanup.
if (file_exists($tempfile)) {
    unlink($tempfile);
}

echo "<br><br><a href='debug_excel.php'>← Carica un altro file</a>";
echo " | <a href='import_calendar.php'>→ Vai all'Import</a>";

echo $OUTPUT->footer();
