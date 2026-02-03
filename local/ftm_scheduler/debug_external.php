<?php
/**
 * Debug: verifica rilevamento progetti esterni (LADI, BIT, CORSO EXTRA)
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_external.php'));
$PAGE->set_title('Debug External Projects');

echo $OUTPUT->header();
echo '<h2>Debug Progetti Esterni</h2>';

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

// External projects to detect
$external_projects = ['BIT URAR', 'BIT AI', 'URAR', 'LADI', 'EXTRA LADI', 'EXTRA-LADI', 'CORSO EXTRA'];

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

    echo '<h3>Scansione Colonne P-V per Progetti Esterni</h3>';
    echo '<table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr>';
    echo '<th>Row</th><th>Data</th><th>Slot</th>';
    echo '<th style="background:#ffe0e0">P</th>';
    echo '<th style="background:#e0ffe0">Q</th>';
    echo '<th style="background:#e0e0ff">R</th>';
    echo '<th style="background:#fff0e0">S</th>';
    echo '<th style="background:#f0e0ff">T</th>';
    echo '<th style="background:#e0fff0">U</th>';
    echo '<th style="background:#ffe0f0">V</th>';
    echo '<th>Progetti Rilevati</th>';
    echo '</tr>';

    $currentDate = null;
    $count = 0;
    $totalExternal = 0;

    for ($row = 1; $row <= 100 && $count < 40; $row++) {
        // Check date
        $dateVal = $sheet->getCell('A' . $row)->getCalculatedValue();
        if (is_numeric($dateVal) && $dateVal > 40000) {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal);
            $currentDate = $dt->format('d/m');
        }

        // Check slot
        $slot = strtolower(trim($sheet->getCell('C' . $row)->getCalculatedValue() ?? ''));
        if (strpos($slot, 'matt') === false && strpos($slot, 'pom') === false) {
            continue;
        }

        $count++;

        // Read columns P-V
        $columns = ['P', 'Q', 'R', 'S', 'T', 'U', 'V'];
        $cellValues = [];
        $foundProjects = [];

        foreach ($columns as $col) {
            $val = trim((string)$sheet->getCell($col . $row)->getCalculatedValue() ?? '');
            $valUpper = strtoupper($val);
            $cellValues[$col] = $val;

            // Check if this cell contains external project
            foreach ($external_projects as $project) {
                if (strpos($valUpper, $project) !== false) {
                    if (!in_array($project, $foundProjects)) {
                        $foundProjects[] = $project;
                    }
                }
            }
        }

        // Also check column N (sometimes external in group column)
        $colN = strtoupper(trim($sheet->getCell('N' . $row)->getCalculatedValue() ?? ''));
        foreach ($external_projects as $project) {
            if (strpos($colN, $project) !== false) {
                if (!in_array($project . ' (N)', $foundProjects)) {
                    $foundProjects[] = $project . ' (N)';
                }
            }
        }

        if (!empty($foundProjects)) {
            $totalExternal++;
        }

        // Highlight row if external found
        $rowStyle = !empty($foundProjects) ? 'background:#90EE90;' : '';

        echo '<tr style="' . $rowStyle . '">';
        echo '<td>' . $row . '</td>';
        echo '<td>' . $currentDate . '</td>';
        echo '<td>' . ucfirst($slot) . '</td>';

        // Show each column value with highlighting if contains external
        $colColors = ['P' => '#ffe0e0', 'Q' => '#e0ffe0', 'R' => '#e0e0ff', 'S' => '#fff0e0', 'T' => '#f0e0ff', 'U' => '#e0fff0', 'V' => '#ffe0f0'];
        foreach ($columns as $col) {
            $val = $cellValues[$col];
            $valUpper = strtoupper($val);
            $hasExternal = false;
            foreach ($external_projects as $project) {
                if (strpos($valUpper, $project) !== false) {
                    $hasExternal = true;
                    break;
                }
            }
            $cellStyle = $hasExternal ? 'background:#FFD700;font-weight:bold;' : 'background:' . $colColors[$col] . ';';
            echo '<td style="' . $cellStyle . '">' . htmlspecialchars(substr($val, 0, 20)) . '</td>';
        }

        echo '<td>';
        if (!empty($foundProjects)) {
            echo '<strong style="color:green;">' . implode(', ', $foundProjects) . '</strong>';
        } else {
            echo '-';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<h4>Riepilogo</h4>';
    echo '<ul>';
    echo '<li>Righe analizzate: ' . $count . '</li>';
    echo '<li>Righe con progetti esterni: <strong>' . $totalExternal . '</strong></li>';
    echo '</ul>';

    echo '<h4>Pattern cercati:</h4>';
    echo '<ul>';
    foreach ($external_projects as $p) {
        echo '<li><code>' . $p . '</code></li>';
    }
    echo '</ul>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo $OUTPUT->footer();
