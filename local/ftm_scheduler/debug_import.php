<?php
/**
 * Debug script per analizzare l'import del calendario.
 * Mostra esattamente cosa viene letto e come viene interpretato.
 *
 * ELIMINA QUESTO FILE DOPO IL DEBUG!
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/classes/calendar_importer.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_import.php'));
$PAGE->set_title('Debug Import Calendario');

echo $OUTPUT->header();
echo "<h2>🔍 Debug Import Calendario</h2>";

// Form upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excelfile'])) {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<p><strong>Carica il file Excel per analizzarlo:</strong></p>';
    echo '<input type="file" name="excelfile" accept=".xlsx,.xls" required>';
    echo '<br><br>';
    echo '<label>Foglio: <input type="text" name="sheet" value="Febbraio" size="15"></label>';
    echo '<br><br>';
    echo '<label>Anno: <input type="number" name="year" value="2026" size="6"></label>';
    echo '<br><br>';
    echo '<button type="submit" style="padding:10px 20px; background:#0066cc; color:white; border:none; border-radius:6px; cursor:pointer;">🔍 Analizza File</button>';
    echo '</form>';
    echo $OUTPUT->footer();
    die();
}

require_sesskey();

$sheetName = optional_param('sheet', 'Febbraio', PARAM_TEXT);
$importYear = optional_param('year', 2026, PARAM_INT);

// Upload file
$tempdir = make_temp_directory('ftm_debug');
$tempfile = $tempdir . '/debug_' . time() . '.xlsx';
move_uploaded_file($_FILES['excelfile']['tmp_name'], $tempfile);

echo "<h3>📁 File: " . s($_FILES['excelfile']['name']) . "</h3>";
echo "<p><strong>Foglio selezionato:</strong> $sheetName | <strong>Anno:</strong> $importYear</p>";

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempfile);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($tempfile);

    // Get sheet
    $sheet = $spreadsheet->getSheetByName($sheetName);
    if (!$sheet) {
        $sheet = $spreadsheet->getSheet(0);
        echo "<p style='color:orange;'>⚠️ Foglio '$sheetName' non trovato, uso: " . s($sheet->getTitle()) . "</p>";
    }

    echo "<hr>";
    echo "<h3>📊 ANALISI STRUTTURA FILE</h3>";

    // FIRST: Scan ALL columns to find BIT, URAR, LADI anywhere
    echo "<h4>🔍 RICERCA PROGETTI ESTERNI (BIT, URAR, LADI) IN TUTTE LE COLONNE</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-size:11px; margin-bottom:20px;'>";
    echo "<tr style='background:#ffcccc;'><th>Riga</th><th>Colonna</th><th>Valore</th><th>Tipo</th></tr>";

    $externalFound = [];
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    for ($row = 1; $row <= 100; $row++) {
        for ($colIndex = 1; $colIndex <= $highestColIndex; $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $cellValue = trim((string)$sheet->getCell($colLetter . $row)->getCalculatedValue());
            $cellUpper = strtoupper($cellValue);

            // Check for external project keywords
            $found = null;
            if (strpos($cellUpper, 'BIT') !== false) $found = 'BIT';
            if (strpos($cellUpper, 'URAR') !== false) $found = 'URAR';
            if (strpos($cellUpper, 'LADI') !== false) $found = 'LADI';
            if (strpos($cellUpper, 'EXTRA') !== false) $found = 'EXTRA';

            if ($found) {
                echo "<tr style='background:#ffe0e0;'>";
                echo "<td><strong>$row</strong></td>";
                echo "<td><strong>$colLetter</strong></td>";
                echo "<td>" . s($cellValue) . "</td>";
                echo "<td>$found</td>";
                echo "</tr>";
                $externalFound[] = ['row' => $row, 'col' => $colLetter, 'value' => $cellValue, 'type' => $found];
            }
        }
    }

    if (empty($externalFound)) {
        echo "<tr><td colspan='4' style='color:red; font-weight:bold;'>⚠️ NESSUN PROGETTO ESTERNO TROVATO nei valori celle! BIT/URAR/LADI non presenti.</td></tr>";
    } else {
        echo "<tr><td colspan='4' style='background:#90EE90;'>✅ Trovati " . count($externalFound) . " riferimenti a progetti esterni</td></tr>";
    }
    echo "</table>";

    // Check for cell COMMENTS/NOTES
    echo "<h4>📝 RICERCA NELLE NOTE/COMMENTI DELLE CELLE</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-size:11px; margin-bottom:20px;'>";
    echo "<tr style='background:#e6f3ff;'><th>Riga</th><th>Colonna</th><th>Commento</th><th>Contiene BIT/URAR/LADI?</th></tr>";

    $commentsFound = [];
    try {
        // Get all comments from the sheet
        $comments = $sheet->getComments();
        foreach ($comments as $cellAddress => $comment) {
            if ($comment && $comment->getText()) {
                $commentText = $comment->getText()->getPlainText();
                if (!empty(trim($commentText))) {
                    $commentUpper = strtoupper($commentText);
                    $hasExternal = (strpos($commentUpper, 'BIT') !== false ||
                                    strpos($commentUpper, 'URAR') !== false ||
                                    strpos($commentUpper, 'LADI') !== false);

                    $style = $hasExternal ? 'background:#90EE90;' : '';
                    echo "<tr style='$style'>";
                    echo "<td colspan='2'><strong>$cellAddress</strong></td>";
                    echo "<td>" . s(substr($commentText, 0, 150)) . "</td>";
                    echo "<td>" . ($hasExternal ? '✅ SÌ' : 'No') . "</td>";
                    echo "</tr>";
                    $commentsFound[] = ['cell' => $cellAddress, 'text' => $commentText, 'hasExternal' => $hasExternal];
                }
            }
        }
    } catch (Exception $e) {
        echo "<tr><td colspan='4' style='color:orange;'>Errore lettura commenti: " . s($e->getMessage()) . "</td></tr>";
    }

    if (empty($commentsFound)) {
        echo "<tr><td colspan='4'>Nessun commento/nota trovato nelle celle.</td></tr>";
    } else {
        $extCount = count(array_filter($commentsFound, fn($c) => $c['hasExternal']));
        if ($extCount > 0) {
            echo "<tr><td colspan='4' style='background:#90EE90;'><strong>✅ Trovati $extCount commenti con progetti esterni!</strong></td></tr>";
        }
    }
    echo "</table>";

    // Show header row to understand column structure
    echo "<h4>📋 INTESTAZIONI COLONNE (Prima riga con contenuto)</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-size:10px; margin-bottom:20px;'>";
    echo "<tr style='background:#e0e0e0;'>";
    for ($colIndex = 1; $colIndex <= min($highestColIndex, 26); $colIndex++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        echo "<th>$colLetter</th>";
    }
    echo "</tr>";

    // Find first row with content and show header structure
    for ($headerRow = 1; $headerRow <= 5; $headerRow++) {
        echo "<tr>";
        for ($colIndex = 1; $colIndex <= min($highestColIndex, 26); $colIndex++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = trim((string)$sheet->getCell($colLetter . $headerRow)->getCalculatedValue());
            $shortVal = mb_substr($val, 0, 12);
            echo "<td title='" . s($val) . "'>" . s($shortVal) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";

    // Analyze first 30 rows
    $currentDate = null;
    $activities = [];

    echo "<h4>📊 DETTAGLIO RIGHE DATI</h4>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-size:12px; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th>Riga</th><th>Col A (Data)</th><th>Col A Calc</th><th>Col C (Slot)</th>";
    echo "<th>D-G (Smartwork)</th><th>L-Q (Aule)</th><th>R-Z (Extra)</th><th>Interpretazione</th>";
    echo "</tr>";

    for ($row = 1; $row <= 50; $row++) {
        $cellA = $sheet->getCell('A' . $row);
        $cellAValue = $cellA->getValue();
        $cellACalc = $cellA->getCalculatedValue();
        $cellC = trim((string)$sheet->getCell('C' . $row)->getCalculatedValue());

        // Skip empty rows
        if (empty($cellACalc) && empty($cellC)) {
            continue;
        }

        // Columns D-G (smartworking)
        $smartwork = [];
        foreach (['D', 'E', 'F', 'G'] as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue());
            if (!empty($val)) {
                $smartwork[] = $val;
            }
        }

        // Columns L-Q (rooms)
        $rooms = [];
        foreach (['L', 'M', 'N', 'O', 'P', 'Q'] as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue());
            if (!empty($val)) {
                $rooms[$col] = $val;
            }
        }

        // Columns R-Z (extra - might contain external projects)
        $extra = [];
        foreach (['R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'] as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue());
            if (!empty($val)) {
                $extra[$col] = $val;
            }
        }

        // Interpretation
        $interpretation = [];
        $rowStyle = '';

        // Check if A is a date
        if (is_numeric($cellACalc) && $cellACalc > 40000 && $cellACalc < 70000) {
            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellACalc);
            $currentDate = $dateObj;
            $dateStr = $dateObj->format('D d/m/Y');
            $interpretation[] = "📅 DATA: $dateStr";
            $rowStyle = 'background:#ffffcc;';
        }

        // Check slot
        $slot = null;
        $cellCLower = strtolower($cellC);
        if (strpos($cellCLower, 'matt') !== false) {
            $slot = 'MATTINA';
            $interpretation[] = "☀️ $slot";
            $rowStyle = $rowStyle ?: 'background:#ccffcc;';
        } elseif (strpos($cellCLower, 'pom') !== false) {
            $slot = 'POMERIGGIO';
            $interpretation[] = "🌙 $slot";
            $rowStyle = $rowStyle ?: 'background:#ccccff;';
        }

        // Check for groups
        foreach ($rooms as $col => $val) {
            $valUpper = strtoupper($val);
            if (strpos($valUpper, 'GR.') !== false || strpos($valUpper, 'GRIGIO') !== false ||
                strpos($valUpper, 'GIALLO') !== false || strpos($valUpper, 'ROSSO') !== false ||
                strpos($valUpper, 'MARRONE') !== false || strpos($valUpper, 'VIOLA') !== false) {
                $interpretation[] = "🎨 GRUPPO: $val (col $col)";
            }
        }

        // Check for external projects in L-Q
        foreach ($rooms as $col => $val) {
            $valUpper = strtoupper($val);
            if (strpos($valUpper, 'BIT') !== false || strpos($valUpper, 'URAR') !== false ||
                strpos($valUpper, 'LADI') !== false) {
                $interpretation[] = "🏢 ESTERNO: $val (col $col)";
                $rowStyle = 'background:#dbeafe;';
            }
        }

        // Check for external projects in R-Z
        foreach ($extra as $col => $val) {
            $valUpper = strtoupper($val);
            if (strpos($valUpper, 'BIT') !== false || strpos($valUpper, 'URAR') !== false ||
                strpos($valUpper, 'LADI') !== false || strpos($valUpper, 'EXTRA') !== false) {
                $interpretation[] = "🏢 ESTERNO R-Z: $val (col $col)";
                $rowStyle = 'background:#ffcccc;';
            }
        }

        // Check for activities
        foreach ($rooms as $col => $val) {
            $valUpper = strtoupper($val);
            if (strpos($valUpper, 'LABORATORIO') !== false || strpos($valUpper, 'BILANCIO') !== false ||
                strpos($valUpper, 'OML') !== false || strpos($valUpper, 'ATELIER') !== false) {
                $interpretation[] = "📋 ATTIVITÀ: $val (col $col)";
            }
        }

        // Check for coaches in rooms
        $coachInitials = ['CB', 'FM', 'GM', 'RB'];
        foreach ($rooms as $col => $val) {
            if (in_array(strtoupper($val), $coachInitials)) {
                $interpretation[] = "👤 COACH: $val (col $col)";
            }
        }

        echo "<tr style='$rowStyle'>";
        echo "<td><strong>$row</strong></td>";
        echo "<td>" . s(substr((string)$cellAValue, 0, 15)) . "</td>";
        echo "<td>" . s($cellACalc) . "</td>";
        echo "<td><strong>" . s($cellC) . "</strong></td>";
        echo "<td>" . s(implode(', ', $smartwork)) . "</td>";
        echo "<td style='font-size:10px;'>" . s(implode(' | ', $rooms)) . "</td>";
        echo "<td style='font-size:10px; color:#666;'>" . s(implode(' | ', $extra)) . "</td>";
        echo "<td style='font-size:11px;'>" . implode('<br>', $interpretation) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Now test the actual importer
    echo "<hr>";
    echo "<h3>🔧 TEST IMPORTER</h3>";

    $importer = new \local_ftm_scheduler\calendar_importer($importYear);
    $preview = $importer->preview_file($tempfile, $sheetName, 20);

    echo "<p><strong>Attività trovate:</strong> " . count($preview['preview']) . "</p>";

    if (!empty($preview['preview'])) {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-size:12px;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>#</th><th>Data</th><th>Giorno</th><th>Slot</th><th>Gruppi</th><th>Esterni</th><th>Attività</th><th>Coach Aula</th>";
        echo "</tr>";

        foreach ($preview['preview'] as $i => $act) {
            $date = date('d/m/Y', $act['timestamp_start']);
            $dayName = date('l', $act['timestamp_start']); // English day name
            $dayNameIt = [
                'Monday' => 'Lunedì',
                'Tuesday' => 'Martedì',
                'Wednesday' => 'Mercoledì',
                'Thursday' => 'Giovedì',
                'Friday' => 'Venerdì',
                'Saturday' => 'Sabato',
                'Sunday' => 'Domenica'
            ][$dayName] ?? $dayName;

            $groups = [];
            if (!empty($act['groups'])) {
                foreach ($act['groups'] as $g) {
                    $groups[] = $g['color'] . ' (' . $g['label'] . ')';
                }
            }

            $externals = [];
            if (!empty($act['external_projects'])) {
                foreach ($act['external_projects'] as $e) {
                    $externals[] = $e['name'];
                }
            }

            $activities = [];
            if (!empty($act['activities'])) {
                foreach ($act['activities'] as $a) {
                    $activities[] = $a['label'];
                }
            }

            $coaches = [];
            if (!empty($act['coaches'])) {
                foreach ($act['coaches'] as $c) {
                    $coaches[] = $c['initials'] . ' (' . $c['room'] . ')';
                }
            }

            $rowColor = !empty($externals) ? 'background:#dbeafe;' : (!empty($groups) ? 'background:#ffffcc;' : '');

            echo "<tr style='$rowColor'>";
            echo "<td>" . ($i + 1) . "</td>";
            echo "<td><strong>$date</strong></td>";
            echo "<td>$dayNameIt</td>";
            echo "<td>" . $act['slot_label'] . "</td>";
            echo "<td>" . implode(', ', $groups) . "</td>";
            echo "<td>" . implode(', ', $externals) . "</td>";
            echo "<td>" . implode(', ', $activities) . "</td>";
            echo "<td>" . implode(', ', $coaches) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    // Server info
    echo "<hr>";
    echo "<h3>ℹ️ INFO SERVER</h3>";
    echo "<ul>";
    echo "<li><strong>Timezone PHP:</strong> " . date_default_timezone_get() . "</li>";
    echo "<li><strong>Data/ora server:</strong> " . date('Y-m-d H:i:s') . "</li>";
    echo "<li><strong>Timestamp now:</strong> " . time() . "</li>";
    echo "</ul>";

    // Test date conversion
    echo "<h4>Test conversione date Excel:</h4>";
    $testDates = [46023, 46024, 46054, 46055, 46056]; // Some test serial numbers
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Excel Serial</th><th>PhpSpreadsheet Result</th><th>Giorno Settimana</th></tr>";
    foreach ($testDates as $serial) {
        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial);
        $dayName = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'][$dt->format('w')];
        echo "<tr><td>$serial</td><td>" . $dt->format('d/m/Y H:i:s') . "</td><td>$dayName</td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRORE:</strong> " . s($e->getMessage()) . "</p>";
    echo "<pre>" . s($e->getTraceAsString()) . "</pre>";
}

// Cleanup
if (file_exists($tempfile)) {
    unlink($tempfile);
}

echo "<hr>";
echo "<p><a href='debug_import.php'>← Carica un altro file</a> | <a href='import_calendar.php'>→ Vai all'Import</a></p>";

echo $OUTPUT->footer();
