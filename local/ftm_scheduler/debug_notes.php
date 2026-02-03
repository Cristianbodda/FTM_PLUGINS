<?php
/**
 * Debug script to read Excel comments/notes.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_notes.php'));
$PAGE->set_title('Debug Excel Notes/Comments');

echo $OUTPUT->header();
echo '<h2>Debug Excel Notes/Comments</h2>';

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
    $reader->setReadDataOnly(false); // IMPORTANTE: false per leggere i commenti!
    $spreadsheet = $reader->load($filepath);

    $sheetNames = $spreadsheet->getSheetNames();

    // Use febbraio sheet if exists
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

    // Get ALL comments from the sheet
    $comments = $sheet->getComments();

    echo '<h3>Tutti i commenti/note trovati nel foglio</h3>';

    if (empty($comments)) {
        echo '<div class="alert alert-warning">Nessun commento trovato nel foglio!</div>';
        echo '<p>Le note potrebbero essere:</p>';
        echo '<ul>';
        echo '<li>In un formato diverso (non commenti standard Excel)</li>';
        echo '<li>Nel contenuto delle celle stesse</li>';
        echo '<li>In colonne diverse</li>';
        echo '</ul>';
    } else {
        echo '<div class="alert alert-success">Trovati ' . count($comments) . ' commenti!</div>';

        echo '<table class="table table-bordered table-sm" style="font-size: 11px;">';
        echo '<tr><th>Cella</th><th>Valore Cella</th><th>Commento/Nota</th><th>Contiene LADI/BIT?</th></tr>';

        foreach ($comments as $cellAddress => $comment) {
            $cellValue = $sheet->getCell($cellAddress)->getCalculatedValue();
            $commentText = $comment->getText()->getPlainText();

            // Check for external projects
            $hasExternal = '';
            $upperComment = strtoupper($commentText);
            if (strpos($upperComment, 'LADI') !== false) {
                $hasExternal .= '<span class="badge badge-primary bg-primary">LADI</span> ';
            }
            if (strpos($upperComment, 'BIT') !== false) {
                $hasExternal .= '<span class="badge badge-warning bg-warning">BIT</span> ';
            }
            if (strpos($upperComment, 'URAR') !== false) {
                $hasExternal .= '<span class="badge badge-info bg-info">URAR</span> ';
            }

            $rowClass = !empty($hasExternal) ? 'table-success' : '';

            echo '<tr class="' . $rowClass . '">';
            echo '<td><strong>' . $cellAddress . '</strong></td>';
            echo '<td>' . htmlspecialchars(substr((string)$cellValue, 0, 30)) . '</td>';
            echo '<td>' . htmlspecialchars($commentText) . '</td>';
            echo '<td>' . ($hasExternal ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // Also scan specific columns for LADI/BIT in cell VALUES
    echo '<h3>Ricerca LADI/BIT/URAR nei VALORI celle (non commenti)</h3>';
    echo '<p>Scansione colonne K-Z per le prime 50 righe...</p>';

    $found = [];
    $columnsToScan = ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    for ($row = 1; $row <= 50; $row++) {
        foreach ($columnsToScan as $col) {
            $value = strtoupper(trim((string)$sheet->getCell($col . $row)->getCalculatedValue()));
            if (empty($value)) continue;

            if (strpos($value, 'LADI') !== false ||
                strpos($value, 'BIT') !== false ||
                strpos($value, 'URAR') !== false ||
                strpos($value, 'EXTRA') !== false) {
                $found[] = [
                    'cell' => $col . $row,
                    'value' => $value,
                ];
            }
        }
    }

    if (empty($found)) {
        echo '<div class="alert alert-warning">Nessun LADI/BIT/URAR trovato nei valori celle!</div>';
    } else {
        echo '<div class="alert alert-success">Trovati ' . count($found) . ' riferimenti esterni nei valori celle!</div>';
        echo '<table class="table table-bordered table-sm">';
        echo '<tr><th>Cella</th><th>Valore</th></tr>';
        foreach ($found as $f) {
            echo '<tr><td>' . $f['cell'] . '</td><td>' . htmlspecialchars($f['value']) . '</td></tr>';
        }
        echo '</table>';
    }

    // Show raw data for first data rows to understand structure
    // FOCUS: Column N analysis
    echo '<h3>🎯 FOCUS: Colonna N (Gruppi + Attività)</h3>';
    echo '<p>Questa è la colonna principale con gruppi e nomi attività:</p>';
    echo '<table class="table table-bordered table-sm">';
    echo '<tr><th>Riga</th><th>Data</th><th>Slot</th><th>Col M (Coach)</th><th style="background:#fff3cd"><strong>Col N (Valore)</strong></th><th style="background:#90EE90"><strong>Col N (Commento)</strong></th></tr>';

    for ($row = 1; $row <= 35; $row++) {
        $slotVal = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue()));
        if (strpos($slotVal, 'matt') === false && strpos($slotVal, 'pom') === false) {
            continue; // Skip non-data rows
        }

        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        $dateStr = '';
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $dateStr = $dateObj->format('d/m');
        }

        $colM = $sheet->getCell('M' . $row)->getCalculatedValue();
        $colN = $sheet->getCell('N' . $row)->getCalculatedValue();

        // Get comment from N
        $colNComment = '';
        try {
            $comment = $sheet->getComment('N' . $row);
            if ($comment && $comment->getText()) {
                $colNComment = $comment->getText()->getPlainText();
            }
        } catch (\Exception $e) {}

        echo '<tr>';
        echo '<td>' . $row . '</td>';
        echo '<td>' . $dateStr . '</td>';
        echo '<td>' . htmlspecialchars($slotVal) . '</td>';
        echo '<td>' . htmlspecialchars($colM) . '</td>';
        echo '<td style="background:#fff3cd"><strong>' . htmlspecialchars($colN) . '</strong></td>';
        echo '<td style="background:#90EE90">' . htmlspecialchars($colNComment) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<h3>Struttura raw righe 3-6 (prime righe dati)</h3>';
    echo '<p>Mostra TUTTE le colonne A-Z per capire dove sono i dati...</p>';

    echo '<div style="overflow-x: auto;">';
    echo '<table class="table table-bordered table-sm" style="font-size: 10px; white-space: nowrap;">';
    echo '<tr><th>Row</th>';
    foreach (range('A', 'Z') as $col) {
        echo '<th>' . $col . '</th>';
    }
    echo '</tr>';

    for ($row = 1; $row <= 8; $row++) {
        echo '<tr>';
        echo '<td><strong>' . $row . '</strong></td>';
        foreach (range('A', 'Z') as $col) {
            $value = $sheet->getCell($col . $row)->getCalculatedValue();

            // Format date if numeric
            if ($col === 'A' && is_numeric($value) && $value > 40000) {
                $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                $value = $dateObj->format('d/m');
            }

            // Truncate long values
            $display = substr((string)$value, 0, 12);
            if (strlen((string)$value) > 12) $display .= '...';

            // Highlight if contains key data
            $style = '';
            $upper = strtoupper((string)$value);
            if (strpos($upper, 'GR.') !== false) $style = 'background: #ffff00;';
            if (strpos($upper, 'LADI') !== false || strpos($upper, 'BIT') !== false) $style = 'background: #90EE90;';
            if (in_array($upper, ['CB', 'FM', 'GM', 'RB', 'DB', 'NC', 'SANDRA', 'ALE', 'LP'])) $style = 'background: #87CEEB;';

            echo '<td style="' . $style . '">' . htmlspecialchars($display) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

echo '<hr>';
echo '<p><a href="import_calendar.php" class="btn btn-primary">Torna a Import Calendar</a></p>';
echo '<p><a href="debug_columns.php" class="btn btn-secondary">Debug Colonne</a></p>';

echo $OUTPUT->footer();
