<?php
/**
 * Debug script to verify Excel column mapping.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_columns.php'));
$PAGE->set_title('Debug Column Mapping');

echo $OUTPUT->header();
echo '<h2>Debug Excel Column Mapping</h2>';

// Check for uploaded file
$uploaddir = $CFG->dataroot . '/temp/ftm_scheduler/';
$files = glob($uploaddir . '*.xlsx');

if (empty($files)) {
    $files = glob($uploaddir . '*.xls');
}

if (empty($files)) {
    echo '<div class="alert alert-warning">Nessun file Excel trovato in ' . $uploaddir . '</div>';
    echo '<p>Carica un file Excel tramite <a href="import_calendar.php">Import Calendar</a> prima.</p>';
    echo $OUTPUT->footer();
    exit;
}

$filepath = end($files);
echo '<div class="alert alert-info">File: ' . basename($filepath) . '</div>';

require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($filepath);

    $sheetNames = $spreadsheet->getSheetNames();
    echo '<p>Fogli trovati: ' . implode(', ', $sheetNames) . '</p>';

    // Use first sheet or "febbraio" if exists
    $sheet = null;
    foreach ($sheetNames as $name) {
        if (stripos($name, 'febbra') !== false) {
            $sheet = $spreadsheet->getSheetByName($name);
            echo '<p>Usando foglio: <strong>' . $name . '</strong></p>';
            break;
        }
    }
    if (!$sheet) {
        $sheet = $spreadsheet->getSheet(0);
        echo '<p>Usando primo foglio: <strong>' . $sheet->getTitle() . '</strong></p>';
    }

    echo '<h3>Mapping Colonne (NUOVO - corretto)</h3>';
    echo '<table class="table table-bordered" style="font-size: 12px;">';
    echo '<tr><th>Colonna</th><th>Uso</th></tr>';
    echo '<tr><td>A</td><td>Data</td></tr>';
    echo '<tr><td>C</td><td>Matt/Pom</td></tr>';
    echo '<tr class="table-success"><td>D, E, F, G, H</td><td>Coach in Smartworking (CB, FM, GM, RB, NC)</td></tr>';
    echo '<tr class="table-primary"><td>K</td><td><strong>Aula 1</strong> - Coach</td></tr>';
    echo '<tr class="table-primary"><td>L</td><td><strong>Aula 1</strong> - ATT (Attività/Gruppo)</td></tr>';
    echo '<tr class="table-warning"><td>M</td><td><strong>Aula 2</strong> - Coach</td></tr>';
    echo '<tr class="table-warning"><td>N</td><td><strong>Aula 2</strong> - ATT (Attività/Gruppo)</td></tr>';
    echo '<tr class="table-danger"><td>O</td><td><strong>Aula 3</strong> - Coach</td></tr>';
    echo '<tr class="table-danger"><td>P</td><td><strong>Aula 3</strong> - ATT (Attività/Gruppo)</td></tr>';
    echo '<tr class="table-info"><td>Q</td><td>Segreteria Smartworking</td></tr>';
    echo '<tr><td>R</td><td>Segreteria Assenza</td></tr>';
    echo '<tr><td>S, T, U</td><td>Altre attività (LADI, BIT, etc.)</td></tr>';
    echo '</table>';

    echo '<h3>Prime 15 righe dati (Febbraio)</h3>';
    echo '<table class="table table-bordered table-sm" style="font-size: 11px;">';
    echo '<tr>';
    echo '<th>Row</th>';
    echo '<th>A (Data)</th>';
    echo '<th>C (Slot)</th>';
    echo '<th>D</th><th>E</th>';
    echo '<th style="background:#d4edda">K (A1)</th>';
    echo '<th style="background:#d4edda">L (ATT1)</th>';
    echo '<th style="background:#fff3cd">M (A2)</th>';
    echo '<th style="background:#fff3cd">N (ATT2)</th>';
    echo '<th style="background:#f8d7da">O (A3)</th>';
    echo '<th style="background:#f8d7da">P (ATT3)</th>';
    echo '<th>Q (Segr)</th>';
    echo '</tr>';

    for ($row = 1; $row <= 20; $row++) {
        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        $slotVal = $sheet->getCell('C' . $row)->getCalculatedValue();

        // Skip header rows (check if C contains Matt or Pom)
        $isDataRow = (stripos($slotVal, 'matt') !== false || stripos($slotVal, 'pom') !== false);

        $rowClass = $isDataRow ? '' : 'table-secondary';

        echo '<tr class="' . $rowClass . '">';
        echo '<td>' . $row . '</td>';

        // Date - convert if numeric
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            echo '<td>' . $dateObj->format('d/m/Y') . '</td>';
        } else {
            echo '<td>' . htmlspecialchars(substr((string)$dateVal, 0, 20)) . '</td>';
        }

        echo '<td><strong>' . htmlspecialchars($slotVal) . '</strong></td>';
        echo '<td>' . htmlspecialchars($sheet->getCell('D' . $row)->getCalculatedValue()) . '</td>';
        echo '<td>' . htmlspecialchars($sheet->getCell('E' . $row)->getCalculatedValue()) . '</td>';

        // Aula 1 (K, L) - green
        echo '<td style="background:#d4edda">' . htmlspecialchars($sheet->getCell('K' . $row)->getCalculatedValue()) . '</td>';
        echo '<td style="background:#d4edda">' . htmlspecialchars($sheet->getCell('L' . $row)->getCalculatedValue()) . '</td>';

        // Aula 2 (M, N) - yellow
        echo '<td style="background:#fff3cd">' . htmlspecialchars($sheet->getCell('M' . $row)->getCalculatedValue()) . '</td>';
        echo '<td style="background:#fff3cd">' . htmlspecialchars($sheet->getCell('N' . $row)->getCalculatedValue()) . '</td>';

        // Aula 3 (O, P) - red
        echo '<td style="background:#f8d7da">' . htmlspecialchars($sheet->getCell('O' . $row)->getCalculatedValue()) . '</td>';
        echo '<td style="background:#f8d7da">' . htmlspecialchars($sheet->getCell('P' . $row)->getCalculatedValue()) . '</td>';

        // Segreteria (Q)
        echo '<td>' . htmlspecialchars($sheet->getCell('Q' . $row)->getCalculatedValue()) . '</td>';

        echo '</tr>';
    }
    echo '</table>';

    echo '<h3>Verifica Lunedì 2 Febbraio</h3>';
    echo '<p>Cerco la riga con data 02/02/2026 e Matt...</p>';

    for ($row = 1; $row <= 50; $row++) {
        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        $slotVal = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue()));

        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $day = (int)$dateObj->format('j');
            $month = (int)$dateObj->format('n');

            if ($day == 2 && $month == 2 && strpos($slotVal, 'matt') !== false) {
                echo '<div class="alert alert-success">';
                echo '<strong>Trovato! Row ' . $row . '</strong><br>';
                echo '<table class="table table-sm">';
                echo '<tr><th>Colonna</th><th>Valore</th><th>Interpretazione</th></tr>';
                echo '<tr><td>A (Data)</td><td>' . $dateObj->format('d/m/Y') . '</td><td>Lunedì 2 Febbraio 2026</td></tr>';
                echo '<tr><td>C (Slot)</td><td>' . $slotVal . '</td><td>Mattina</td></tr>';
                echo '<tr><td>D (Smartw)</td><td>' . $sheet->getCell('D' . $row)->getCalculatedValue() . '</td><td>Coach in smartworking</td></tr>';
                echo '<tr class="table-primary"><td>K (Aula 1)</td><td><strong>' . $sheet->getCell('K' . $row)->getCalculatedValue() . '</strong></td><td>Coach in Aula 1</td></tr>';
                echo '<tr class="table-primary"><td>L (ATT 1)</td><td><strong>' . $sheet->getCell('L' . $row)->getCalculatedValue() . '</strong></td><td>Attività Aula 1</td></tr>';
                echo '<tr class="table-warning"><td>M (Aula 2)</td><td><strong>' . $sheet->getCell('M' . $row)->getCalculatedValue() . '</strong></td><td>Coach in Aula 2</td></tr>';
                echo '<tr class="table-warning"><td>N (ATT 2)</td><td><strong>' . $sheet->getCell('N' . $row)->getCalculatedValue() . '</strong></td><td>Attività Aula 2</td></tr>';
                echo '<tr class="table-danger"><td>O (Aula 3)</td><td><strong>' . $sheet->getCell('O' . $row)->getCalculatedValue() . '</strong></td><td>Coach in Aula 3</td></tr>';
                echo '<tr class="table-danger"><td>P (ATT 3)</td><td><strong>' . $sheet->getCell('P' . $row)->getCalculatedValue() . '</strong></td><td>Attività Aula 3</td></tr>';
                echo '</table>';

                echo '<h4>Confronto con Excel atteso:</h4>';
                echo '<ul>';
                echo '<li><strong>Excel mostra:</strong> GM in Aula 2 con GR. GRIGIO, RB in Aula 3</li>';
                echo '<li><strong>Colonna M (Aula 2):</strong> ' . $sheet->getCell('M' . $row)->getCalculatedValue() . '</li>';
                echo '<li><strong>Colonna N (ATT 2):</strong> ' . $sheet->getCell('N' . $row)->getCalculatedValue() . '</li>';
                echo '</ul>';

                echo '</div>';
                break;
            }
        }
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo '<hr>';
echo '<p><a href="import_calendar.php" class="btn btn-primary">Torna a Import Calendar</a></p>';

echo $OUTPUT->footer();
