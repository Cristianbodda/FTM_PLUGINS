<?php
/**
 * Debug: mostra contenuto RAW di tutte le colonne Excel
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_excel_raw.php'));
$PAGE->set_title('Debug Excel RAW');

echo $OUTPUT->header();
echo '<h2>🔍 Debug Excel - Contenuto RAW Colonne</h2>';

// Cerca il file in più posizioni possibili
$searchPaths = [
    $CFG->dataroot . '/temp/ftm_scheduler/',
    $CFG->dataroot . '/temp/',
    $CFG->tempdir . '/',
    sys_get_temp_dir() . '/',
    $CFG->dataroot . '/filedir/',
];

$files = [];
foreach ($searchPaths as $dir) {
    if (is_dir($dir)) {
        $found = glob($dir . '*.xlsx');
        if (!empty($found)) {
            $files = array_merge($files, $found);
        }
        $found = glob($dir . '*.xls');
        if (!empty($found)) {
            $files = array_merge($files, $found);
        }
    }
}

// Cerca anche file recenti con pattern planning
$planningFiles = glob($CFG->dataroot . '/temp/*planning*.xlsx');
if (!empty($planningFiles)) {
    $files = array_merge($files, $planningFiles);
}

// Mostra percorsi cercati per debug
echo '<details><summary>Percorsi cercati (debug)</summary><ul>';
foreach ($searchPaths as $p) {
    echo '<li>' . $p . ' - ' . (is_dir($p) ? 'esiste' : 'non esiste') . '</li>';
}
echo '</ul></details>';

if (empty($files)) {
    echo '<div class="alert alert-danger">Nessun file Excel trovato!</div>';
    echo '<p>Prova a caricare manualmente il file qui sotto:</p>';

    // Form per upload manuale
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="excelfile" accept=".xlsx,.xls" class="form-control mb-2">';
    echo '<button type="submit" name="upload" class="btn btn-primary">Carica e Analizza</button>';
    echo '</form>';

    // Gestisci upload manuale
    if (isset($_POST['upload']) && isset($_FILES['excelfile'])) {
        $uploadedFile = $_FILES['excelfile']['tmp_name'];
        if (is_uploaded_file($uploadedFile)) {
            $files = [$uploadedFile];
        }
    } else {
        echo $OUTPUT->footer();
        exit;
    }
}

$filepath = end($files);
echo '<div class="alert alert-info">File: <strong>' . basename($filepath) . '</strong></div>';

require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');

try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($filepath);

    // Trova foglio febbraio
    $sheet = null;
    foreach ($spreadsheet->getSheetNames() as $name) {
        if (stripos($name, 'febbra') !== false) {
            $sheet = $spreadsheet->getSheetByName($name);
            break;
        }
    }
    if (!$sheet) $sheet = $spreadsheet->getSheet(0);

    echo '<p>Foglio: <strong>' . $sheet->getTitle() . '</strong></p>';

    // Trova prima riga con "Matt" in colonna C
    $dataStartRow = 0;
    for ($r = 1; $r <= 20; $r++) {
        $val = strtolower(trim($sheet->getCell('C' . $r)->getCalculatedValue()));
        if (strpos($val, 'matt') !== false) {
            $dataStartRow = $r;
            break;
        }
    }

    if ($dataStartRow == 0) {
        echo '<div class="alert alert-warning">Non trovo righe con "Matt" in colonna C!</div>';
        $dataStartRow = 3; // Prova comunque
    }

    echo '<h3>📊 Contenuto colonne A-V per righe ' . $dataStartRow . '-' . ($dataStartRow + 5) . '</h3>';
    echo '<p><em>Cerca dove appaiono GM, GR. GRIGIO, LADI, etc.</em></p>';

    // Mostra header (riga 1 e 2)
    echo '<h4>Header (righe 1-2):</h4>';
    echo '<div style="overflow-x:auto;"><table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr><th>Row</th>';
    foreach (range('A', 'V') as $c) echo '<th>' . $c . '</th>';
    echo '</tr>';

    for ($r = 1; $r <= 2; $r++) {
        echo '<tr><td><strong>' . $r . '</strong></td>';
        foreach (range('A', 'V') as $c) {
            $val = $sheet->getCell($c . $r)->getCalculatedValue();
            $display = substr((string)$val, 0, 15);
            echo '<td>' . htmlspecialchars($display) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></div>';

    // Mostra dati
    echo '<h4>Dati (righe ' . $dataStartRow . '-' . ($dataStartRow + 7) . '):</h4>';
    echo '<div style="overflow-x:auto;"><table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr><th>Row</th><th>Slot</th>';
    foreach (range('A', 'V') as $c) {
        // Evidenzia colonne importanti
        $style = '';
        if (in_array($c, ['L','M','N','O','P','Q'])) $style = 'background:#ffffcc;';
        if (in_array($c, ['S','T','U'])) $style = 'background:#ccffcc;';
        echo '<th style="' . $style . '">' . $c . '</th>';
    }
    echo '</tr>';

    for ($r = $dataStartRow; $r <= $dataStartRow + 7; $r++) {
        $slot = $sheet->getCell('C' . $r)->getCalculatedValue();
        $slotLower = strtolower(trim($slot));
        $isDataRow = (strpos($slotLower, 'matt') !== false || strpos($slotLower, 'pom') !== false);

        $rowStyle = $isDataRow ? '' : 'background:#f0f0f0;';

        echo '<tr style="' . $rowStyle . '"><td><strong>' . $r . '</strong></td>';
        echo '<td>' . htmlspecialchars($slot) . '</td>';

        foreach (range('A', 'V') as $c) {
            $val = $sheet->getCell($c . $r)->getCalculatedValue();

            // Converti data Excel
            if ($c == 'A' && is_numeric($val) && $val > 40000) {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                $val = $dt->format('d/m');
            }

            $display = substr((string)$val, 0, 12);

            // Evidenzia valori importanti
            $cellStyle = '';
            $valUpper = strtoupper((string)$val);
            if (in_array($valUpper, ['GM','FM','CB','RB','DB','SANDRA','ALE','LP','NC'])) {
                $cellStyle = 'background:#87CEEB;font-weight:bold;'; // Coach = blu
            }
            if (strpos($valUpper, 'GR.') !== false || strpos($valUpper, 'GRIGIO') !== false ||
                strpos($valUpper, 'GIALLO') !== false || strpos($valUpper, 'ROSSO') !== false) {
                $cellStyle = 'background:#FFFF00;font-weight:bold;'; // Gruppo = giallo
            }
            if (strpos($valUpper, 'LADI') !== false || strpos($valUpper, 'BIT') !== false ||
                strpos($valUpper, 'URAR') !== false) {
                $cellStyle = 'background:#90EE90;font-weight:bold;'; // External = verde
            }
            if (strpos($valUpper, 'AT.') !== false || strpos($valUpper, 'LABORATORIO') !== false ||
                strpos($valUpper, 'ATELIER') !== false) {
                $cellStyle = 'background:#FFB6C1;font-weight:bold;'; // Activity = rosa
            }

            // Colonne L-Q evidenziate
            if (in_array($c, ['L','M','N','O','P','Q']) && empty($cellStyle)) {
                $cellStyle = 'background:#ffffee;';
            }
            if (in_array($c, ['S','T','U']) && empty($cellStyle)) {
                $cellStyle = 'background:#eeffee;';
            }

            echo '<td style="' . $cellStyle . '">' . htmlspecialchars($display) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></div>';

    echo '<h4>🎨 Legenda colori:</h4>';
    echo '<ul>';
    echo '<li><span style="background:#87CEEB;padding:2px 8px;">BLU</span> = Coach (GM, FM, CB, RB, DB)</li>';
    echo '<li><span style="background:#FFFF00;padding:2px 8px;">GIALLO</span> = Gruppo (GR. GRIGIO, etc.)</li>';
    echo '<li><span style="background:#90EE90;padding:2px 8px;">VERDE</span> = Esterno (LADI, BIT)</li>';
    echo '<li><span style="background:#FFB6C1;padding:2px 8px;">ROSA</span> = Attività (At. Canali, LABORATORIO)</li>';
    echo '</ul>';

    echo '<h4>📝 Dimmi quali colonne contengono:</h4>';
    echo '<ul>';
    echo '<li>Coach principale (GM, FM): Colonna <strong>___</strong></li>';
    echo '<li>Gruppo (GR. GRIGIO): Colonna <strong>___</strong></li>';
    echo '<li>Attività (At. Canali): Colonna <strong>___</strong></li>';
    echo '<li>LADI/BIT: Colonna <strong>___</strong></li>';
    echo '</ul>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo $OUTPUT->footer();
