<?php
/**
 * Script diagnostico per verificare idnumber competenze MECCANICA
 * Versione 2.0 - Mostra anche competenze assegnate a quiz
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_meccanica.php'));
$PAGE->set_title('Diagnosi Competenze Meccanica');

echo $OUTPUT->header();
echo '<h2>Diagnosi Competenze MECCANICA</h2>';

// =============================================
// SEZIONE 1: Competenze MECCANICA nel framework
// =============================================
echo '<h3 style="background: #007bff; color: white; padding: 10px;">1. Competenze MECCANICA nel Framework FTM-01</h3>';

$meccanicaComps = $DB->get_records_sql(
    "SELECT c.id, c.idnumber, c.shortname, cf.idnumber as framework_idnumber
     FROM {competency} c
     JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
     WHERE cf.idnumber = 'FTM-01'
       AND (c.idnumber LIKE 'MECCANICA%' OR c.idnumber LIKE '06%')
     ORDER BY c.idnumber"
);

echo '<p><strong>Trovate ' . count($meccanicaComps) . ' competenze MECCANICA</strong></p>';
echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 12px;">';
echo '<tr style="background: #f0f0f0;"><th>ID</th><th>idnumber</th><th>shortname</th><th>Settore Estratto</th><th>Area</th></tr>';

foreach ($meccanicaComps as $comp) {
    $extractedSector = extract_sector_from_idnumber($comp->idnumber);
    $areaInfo = get_area_info($comp->idnumber);
    $isCorrect = ($extractedSector === 'MECCANICA');
    $rowStyle = $isCorrect ? 'background: #d4edda;' : 'background: #f8d7da;';

    echo "<tr style='$rowStyle'>";
    echo "<td>{$comp->id}</td>";
    echo "<td><code>{$comp->idnumber}</code></td>";
    echo "<td>" . substr($comp->shortname, 0, 50) . "</td>";
    echo "<td><strong>{$extractedSector}</strong> " . ($isCorrect ? '✅' : '❌') . "</td>";
    echo "<td>{$areaInfo['code']}</td>";
    echo "</tr>";
}
echo '</table>';

// =============================================
// SEZIONE 2: Tutti i pattern idnumber nel DB
// =============================================
echo '<h3 style="background: #28a745; color: white; padding: 10px; margin-top: 30px;">2. Tutti i Pattern idnumber nel Framework</h3>';

$allComps = $DB->get_records_sql(
    "SELECT c.id, c.idnumber, c.shortname
     FROM {competency} c
     JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
     WHERE cf.idnumber IN ('FTM-01', 'FTM_GEN')
     ORDER BY c.idnumber"
);

$patterns = [];
$sectorCounts = [];
foreach ($allComps as $comp) {
    $parts = explode('_', $comp->idnumber);
    $pattern = $parts[0] ?? 'N/A';
    $sector = extract_sector_from_idnumber($comp->idnumber);

    if (!isset($patterns[$pattern])) {
        $patterns[$pattern] = ['count' => 0, 'examples' => [], 'sector' => $sector];
    }
    $patterns[$pattern]['count']++;
    if (count($patterns[$pattern]['examples']) < 2) {
        $patterns[$pattern]['examples'][] = $comp->idnumber;
    }

    if (!isset($sectorCounts[$sector])) {
        $sectorCounts[$sector] = 0;
    }
    $sectorCounts[$sector]++;
}

echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
echo '<tr style="background: #f0f0f0;"><th>Pattern (primo segmento)</th><th>Conteggio</th><th>Settore Mappato</th><th>Esempi</th></tr>';
foreach ($patterns as $pattern => $data) {
    $isMeccanica = ($data['sector'] === 'MECCANICA');
    $style = $isMeccanica ? 'background: #d4edda;' : '';
    echo "<tr style='$style'>";
    echo "<td><strong>{$pattern}</strong></td>";
    echo "<td>{$data['count']}</td>";
    echo "<td>{$data['sector']}</td>";
    echo "<td><code>" . implode('</code>, <code>', $data['examples']) . "</code></td>";
    echo "</tr>";
}
echo '</table>';

echo '<h4>Riepilogo per Settore:</h4>';
echo '<ul>';
arsort($sectorCounts);
foreach ($sectorCounts as $sector => $count) {
    $style = ($sector === 'MECCANICA') ? 'color: green; font-weight: bold;' : '';
    echo "<li style='$style'>{$sector}: {$count} competenze</li>";
}
echo '</ul>';

// =============================================
// SEZIONE 3: Test funzione normalize_sector_name
// =============================================
echo '<h3 style="background: #ffc107; color: black; padding: 10px; margin-top: 30px;">3. Test Funzione normalize_sector_name()</h3>';

$testCases = [
    '06' => 'MECCANICA',
    '06-01' => 'MECCANICA',
    '06-13' => 'MECCANICA',
    'MECCANICA' => 'MECCANICA',
    'MECCANICA_LMB_01' => 'MECCANICA (via extract)',
    '04' => 'AUTOMAZIONE',
    '04-01' => 'AUTOMAZIONE',
    'AUTOMAZIONE' => 'AUTOMAZIONE',
    'MECC' => 'MECCANICA',
    'CNC' => 'MECCANICA',
    'LMB' => 'MECCANICA',
];

echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
echo '<tr style="background: #f0f0f0;"><th>Input</th><th>Atteso</th><th>Risultato</th><th>Status</th></tr>';
foreach ($testCases as $input => $expected) {
    if (strpos($expected, 'via extract') !== false) {
        $result = extract_sector_from_idnumber($input);
        $expected = 'MECCANICA';
    } else {
        $result = normalize_sector_name($input);
    }
    $isOk = ($result === $expected);
    $style = $isOk ? 'background: #d4edda;' : 'background: #f8d7da;';
    echo "<tr style='$style'>";
    echo "<td><code>{$input}</code></td>";
    echo "<td>{$expected}</td>";
    echo "<td><strong>{$result}</strong></td>";
    echo "<td>" . ($isOk ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo '</table>';

echo $OUTPUT->footer();
