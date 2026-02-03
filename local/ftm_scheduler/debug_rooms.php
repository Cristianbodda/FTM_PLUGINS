<?php
/**
 * Debug: verifica parsing 3 aule con colori celle e commenti
 *
 * Struttura colonne:
 * - K: Aula 1 Coach (es. DB)
 * - L: Aula 1 Attività (colore = gruppo, nero = esterno, attività in commento)
 * - M: Aula 2 Coach (es. GM, FM, RB)
 * - N: Aula 2 Attività (GR. GRIGIO, At. Canali, etc.)
 * - O: Aula 3 Coach (es. RB, commento con attività)
 * - P: Aula 3 Attività (colore = gruppo/esterno)
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/debug_rooms.php'));
$PAGE->set_title('Debug 3 Aule');

echo $OUTPUT->header();
echo '<h2>Debug Parsing 3 Aule</h2>';

// Form per upload
if (!isset($_POST['upload'])) {
    echo '<div class="alert alert-info">';
    echo '<h4>Struttura Colonne Attesa:</h4>';
    echo '<table class="table table-sm" style="max-width:600px;">';
    echo '<tr><th>Colonna</th><th>Contenuto</th></tr>';
    echo '<tr style="background:#ffe0e0;"><td>K</td><td>Aula 1 - Coach</td></tr>';
    echo '<tr style="background:#ffe0e0;"><td>L</td><td>Aula 1 - Attività (colore/commento)</td></tr>';
    echo '<tr style="background:#e0ffe0;"><td>M</td><td>Aula 2 - Coach</td></tr>';
    echo '<tr style="background:#e0ffe0;"><td>N</td><td>Aula 2 - Attività (testo/colore)</td></tr>';
    echo '<tr style="background:#e0e0ff;"><td>O</td><td>Aula 3 - Coach (commento con attività)</td></tr>';
    echo '<tr style="background:#e0e0ff;"><td>P</td><td>Aula 3 - Attività (colore)</td></tr>';
    echo '</table>';
    echo '</div>';

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

// Coach list
$coach_list = ['CB', 'FM', 'GM', 'RB', 'DB', 'SANDRA', 'ALE', 'LP', 'NC'];

// Color mapping
function colorToGroup($hex) {
    $hex = strtoupper(ltrim($hex, '#'));
    if (empty($hex)) return null;

    // Parse RGB
    if (strlen($hex) !== 6) return null;
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Black = external
    if ($r < 30 && $g < 30 && $b < 30) return 'NERO (Esterno)';

    // Yellow
    if ($r > 200 && $g > 200 && $b < 150) return 'GIALLO';

    // Gray
    if (abs($r - $g) < 30 && abs($g - $b) < 30 && $r > 100 && $r < 220) return 'GRIGIO';

    // Red
    if ($r > 180 && $g < 150 && $b < 150) return 'ROSSO';

    // Brown
    if ($r > 150 && $r < 230 && $g > 80 && $g < 180 && $b < 120) return 'MARRONE';

    // Purple
    if ($r > 80 && $b > 100 && $g < $r && $g < $b) return 'VIOLA';

    // Green
    if ($g > $r && $g > $b && $g > 150) return 'VERDE';

    return null;
}

function getCellColor($sheet, $cell) {
    try {
        $style = $sheet->getStyle($cell);
        $fill = $style->getFill();
        if ($fill->getFillType() === \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID) {
            return '#' . $fill->getStartColor()->getRGB();
        }
    } catch (Exception $e) {}
    return '';
}

function getCellComment($sheet, $cell) {
    try {
        $comment = $sheet->getComment($cell);
        if ($comment && $comment->getText()) {
            return trim($comment->getText()->getPlainText());
        }
    } catch (Exception $e) {}
    return '';
}

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

    echo '<h3>Analisi per Aula</h3>';
    echo '<table class="table table-bordered table-sm" style="font-size:10px;">';
    echo '<tr>';
    echo '<th>Row</th>';
    echo '<th>Data</th>';
    echo '<th>Slot</th>';
    echo '<th colspan="4" style="background:#ffe0e0;">AULA 1 (K-L)</th>';
    echo '<th colspan="4" style="background:#e0ffe0;">AULA 2 (M-N)</th>';
    echo '<th colspan="4" style="background:#e0e0ff;">AULA 3 (O-P)</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th></th><th></th><th></th>';
    // Aula 1
    echo '<th style="background:#ffe0e0;">K (Coach)</th>';
    echo '<th style="background:#ffe0e0;">L (Valore)</th>';
    echo '<th style="background:#ffe0e0;">L Colore</th>';
    echo '<th style="background:#ffe0e0;">Commento</th>';
    // Aula 2
    echo '<th style="background:#e0ffe0;">M (Coach)</th>';
    echo '<th style="background:#e0ffe0;">N (Valore)</th>';
    echo '<th style="background:#e0ffe0;">N Colore</th>';
    echo '<th style="background:#e0ffe0;">Commento</th>';
    // Aula 3
    echo '<th style="background:#e0e0ff;">O (Coach)</th>';
    echo '<th style="background:#e0e0ff;">P (Valore)</th>';
    echo '<th style="background:#e0e0ff;">P Colore</th>';
    echo '<th style="background:#e0e0ff;">Commento O</th>';
    echo '</tr>';

    $currentDate = null;
    $count = 0;
    $summary = [
        'aula1' => ['activities' => 0, 'external' => 0],
        'aula2' => ['activities' => 0, 'external' => 0],
        'aula3' => ['activities' => 0, 'external' => 0],
    ];

    for ($row = 1; $row <= 150 && $count < 50; $row++) {
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

        // Read all columns
        $colK = strtoupper(trim($sheet->getCell('K' . $row)->getCalculatedValue() ?? ''));
        $colL = trim($sheet->getCell('L' . $row)->getCalculatedValue() ?? '');
        $colLColor = getCellColor($sheet, 'L' . $row);
        $colLComment = getCellComment($sheet, 'L' . $row);
        $colKComment = getCellComment($sheet, 'K' . $row);

        $colM = strtoupper(trim($sheet->getCell('M' . $row)->getCalculatedValue() ?? ''));
        $colN = trim($sheet->getCell('N' . $row)->getCalculatedValue() ?? '');
        $colNColor = getCellColor($sheet, 'N' . $row);
        $colNComment = getCellComment($sheet, 'N' . $row);
        $colMComment = getCellComment($sheet, 'M' . $row);

        $colO = strtoupper(trim($sheet->getCell('O' . $row)->getCalculatedValue() ?? ''));
        $colP = trim($sheet->getCell('P' . $row)->getCalculatedValue() ?? '');
        $colPColor = getCellColor($sheet, 'P' . $row);
        $colOComment = getCellComment($sheet, 'O' . $row);

        // Analyze Aula 1
        $aula1Coach = in_array($colK, $coach_list) ? $colK : '';
        $aula1Group = colorToGroup($colLColor);
        $aula1External = ($aula1Group === 'NERO (Esterno)');
        $aula1Comment = !empty($colKComment) ? $colKComment : $colLComment;

        if ($aula1Coach) {
            if ($aula1External) {
                $summary['aula1']['external']++;
            } else {
                $summary['aula1']['activities']++;
            }
        }

        // Analyze Aula 2
        $aula2Coach = in_array($colM, $coach_list) ? $colM : '';
        $aula2Group = colorToGroup($colNColor);
        if (!$aula2Group && !empty($colN)) {
            // Check text for group
            if (stripos($colN, 'GRIGIO') !== false) $aula2Group = 'GRIGIO';
            elseif (stripos($colN, 'GIALLO') !== false) $aula2Group = 'GIALLO';
            elseif (stripos($colN, 'ROSSO') !== false) $aula2Group = 'ROSSO';
            elseif (stripos($colN, 'MARRONE') !== false) $aula2Group = 'MARRONE';
            elseif (stripos($colN, 'VIOLA') !== false) $aula2Group = 'VIOLA';
        }
        $aula2External = ($aula2Group === 'NERO (Esterno)');
        $aula2Comment = !empty($colMComment) ? $colMComment : $colNComment;

        if ($aula2Coach) {
            if ($aula2External) {
                $summary['aula2']['external']++;
            } else {
                $summary['aula2']['activities']++;
            }
        }

        // Analyze Aula 3
        $aula3Coach = in_array($colO, $coach_list) ? $colO : '';
        $aula3Group = colorToGroup($colPColor);
        $aula3External = ($aula3Group === 'NERO (Esterno)');

        if ($aula3Coach) {
            if ($aula3External) {
                $summary['aula3']['external']++;
            } else {
                $summary['aula3']['activities']++;
            }
        }

        echo '<tr>';
        echo '<td>' . $row . '</td>';
        echo '<td>' . $currentDate . '</td>';
        echo '<td>' . ucfirst($slot) . '</td>';

        // Aula 1
        $a1Style = $aula1Coach ? ($aula1External ? 'background:#333;color:white;' : 'background:#ffe0e0;') : '';
        echo '<td style="' . $a1Style . '"><strong>' . $aula1Coach . '</strong></td>';
        echo '<td>' . htmlspecialchars(substr($colL, 0, 15)) . '</td>';
        echo '<td style="' . ($colLColor ? 'background:' . $colLColor . ';' : '') . '">' . ($aula1Group ?: $colLColor) . '</td>';
        echo '<td style="font-size:9px;">' . htmlspecialchars(substr($aula1Comment, 0, 30)) . '</td>';

        // Aula 2
        $a2Style = $aula2Coach ? ($aula2External ? 'background:#333;color:white;' : 'background:#e0ffe0;') : '';
        echo '<td style="' . $a2Style . '"><strong>' . $aula2Coach . '</strong></td>';
        echo '<td>' . htmlspecialchars(substr($colN, 0, 15)) . '</td>';
        echo '<td style="' . ($colNColor ? 'background:' . $colNColor . ';' : '') . '">' . ($aula2Group ?: $colNColor) . '</td>';
        echo '<td style="font-size:9px;">' . htmlspecialchars(substr($aula2Comment, 0, 30)) . '</td>';

        // Aula 3
        $a3Style = $aula3Coach ? ($aula3External ? 'background:#333;color:white;' : 'background:#e0e0ff;') : '';
        echo '<td style="' . $a3Style . '"><strong>' . $aula3Coach . '</strong></td>';
        echo '<td>' . htmlspecialchars(substr($colP, 0, 15)) . '</td>';
        echo '<td style="' . ($colPColor ? 'background:' . $colPColor . ';' : '') . '">' . ($aula3Group ?: $colPColor) . '</td>';
        echo '<td style="font-size:9px;">' . htmlspecialchars(substr($colOComment, 0, 30)) . '</td>';

        echo '</tr>';
    }
    echo '</table>';

    // Summary
    echo '<h4>Riepilogo</h4>';
    echo '<table class="table table-bordered" style="max-width:500px;">';
    echo '<tr><th>Aula</th><th>Attività</th><th>Esterni</th><th>Totale</th></tr>';
    foreach ($summary as $aula => $data) {
        echo '<tr>';
        echo '<td><strong>' . strtoupper($aula) . '</strong></td>';
        echo '<td>' . $data['activities'] . '</td>';
        echo '<td>' . $data['external'] . '</td>';
        echo '<td><strong>' . ($data['activities'] + $data['external']) . '</strong></td>';
        echo '</tr>';
    }
    $totalAct = $summary['aula1']['activities'] + $summary['aula2']['activities'] + $summary['aula3']['activities'];
    $totalExt = $summary['aula1']['external'] + $summary['aula2']['external'] + $summary['aula3']['external'];
    echo '<tr style="background:#f0f0f0;"><td><strong>TOTALE</strong></td>';
    echo '<td><strong>' . $totalAct . '</strong></td>';
    echo '<td><strong>' . $totalExt . '</strong></td>';
    echo '<td><strong>' . ($totalAct + $totalExt) . '</strong></td></tr>';
    echo '</table>';

    echo '<h4>Legenda</h4>';
    echo '<ul>';
    echo '<li><span style="background:#ffe0e0;padding:2px 8px;">Rosa</span> = Aula 1 con attività</li>';
    echo '<li><span style="background:#e0ffe0;padding:2px 8px;">Verde</span> = Aula 2 con attività</li>';
    echo '<li><span style="background:#e0e0ff;padding:2px 8px;">Blu</span> = Aula 3 con attività</li>';
    echo '<li><span style="background:#333;color:white;padding:2px 8px;">Nero</span> = Attività esterna (LADI, BIT)</li>';
    echo '</ul>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Errore: ' . $e->getMessage() . '</div>';
}

echo $OUTPUT->footer();
