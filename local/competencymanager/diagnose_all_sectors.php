<?php
/**
 * Script diagnostico per verificare TUTTI i settori del framework FTM
 * Verifica: mapping numerico, mapping testuale, estrazione aree
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_all_sectors.php'));
$PAGE->set_title('Diagnosi Tutti i Settori FTM');

echo $OUTPUT->header();
echo '<h2>🔍 Diagnosi Completa - Tutti i Settori FTM</h2>';

// Definizione settori attesi
$expectedSectors = [
    '01' => ['name' => 'AUTOMOBILE', 'areas' => ['A','B','C','D','E','F','G','H','I','J','K','L','M','N']],
    '02' => ['name' => 'CHIMFARM', 'areas' => ['1C','1G','1O','2M','3C','4S','5S','6P','7S','8T','9A']],
    '03' => ['name' => 'ELETTRICITA', 'areas' => ['A','B','C','D','E','F','G','H']],
    '04' => ['name' => 'AUTOMAZIONE', 'areas' => ['A','B','C','D','E','F','G','H']],
    '05' => ['name' => 'LOGISTICA', 'areas' => ['A','B','C','D','E','F','G','H']],
    '06' => ['name' => 'MECCANICA', 'areas' => ['LMB','LMC','CNC','ASS','MIS','GEN','MAN','DT','AUT','PIAN','SAQ','CSP','PRG']],
    '07' => ['name' => 'METALCOSTRUZIONE', 'areas' => ['A','B','C','D','E','F','G','H','I','J']],
];

// Risultati globali
$globalResults = [
    'total_competencies' => 0,
    'correctly_mapped' => 0,
    'incorrectly_mapped' => 0,
    'sector_errors' => [],
    'area_errors' => [],
];

// =============================================
// TEST 1: Funzioni di normalizzazione
// =============================================
echo '<h3 style="background: #007bff; color: white; padding: 10px;">1. Test Funzione normalize_sector_name()</h3>';

$testCases = [
    // Codici numerici
    '01' => 'AUTOMOBILE', '02' => 'CHIMFARM', '03' => 'ELETTRICITA',
    '04' => 'AUTOMAZIONE', '05' => 'LOGISTICA', '06' => 'MECCANICA', '07' => 'METALCOSTRUZIONE',
    // Pattern XX-YY
    '01-01' => 'AUTOMOBILE', '02-05' => 'CHIMFARM', '03-03' => 'ELETTRICITA',
    '04-02' => 'AUTOMAZIONE', '05-04' => 'LOGISTICA', '06-07' => 'MECCANICA', '07-10' => 'METALCOSTRUZIONE',
    // Nomi testuali
    'AUTOMOBILE' => 'AUTOMOBILE', 'CHIMFARM' => 'CHIMFARM', 'ELETTRICITA' => 'ELETTRICITA',
    'AUTOMAZIONE' => 'AUTOMAZIONE', 'LOGISTICA' => 'LOGISTICA', 'MECCANICA' => 'MECCANICA',
    'METALCOSTRUZIONE' => 'METALCOSTRUZIONE',
    // Alias
    'MECC' => 'MECCANICA', 'AUTO' => 'AUTOMOBILE', 'CHIM' => 'CHIMFARM',
    'ELETTR' => 'ELETTRICITA', 'LOG' => 'LOGISTICA', 'METAL' => 'METALCOSTRUZIONE',
    'AUTOM' => 'AUTOMAZIONE',
    // Con accenti
    'ELETTRICITÀ' => 'ELETTRICITA',
];

$passedTests = 0;
$failedTests = 0;

echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 12px;">';
echo '<tr style="background: #f0f0f0;"><th>Input</th><th>Atteso</th><th>Risultato</th><th>Status</th></tr>';

foreach ($testCases as $input => $expected) {
    $result = normalize_sector_name($input);
    $isOk = ($result === $expected);
    if ($isOk) {
        $passedTests++;
    } else {
        $failedTests++;
    }
    $style = $isOk ? 'background: #d4edda;' : 'background: #f8d7da;';
    echo "<tr style='$style'>";
    echo "<td><code>{$input}</code></td>";
    echo "<td>{$expected}</td>";
    echo "<td><strong>{$result}</strong></td>";
    echo "<td>" . ($isOk ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo '</table>';
echo "<p><strong>Risultato:</strong> {$passedTests}/" . count($testCases) . " test passati</p>";

// =============================================
// TEST 2: Competenze per ogni settore
// =============================================
echo '<h3 style="background: #28a745; color: white; padding: 10px; margin-top: 30px;">2. Competenze per Settore nel Database</h3>';

foreach ($expectedSectors as $numCode => $sectorInfo) {
    $sectorName = $sectorInfo['name'];
    $expectedAreas = $sectorInfo['areas'];

    echo "<h4 style='background: #6c757d; color: white; padding: 8px; margin-top: 20px;'>";
    echo "Settore: {$sectorName} (Codice: {$numCode})";
    echo "</h4>";

    // Cerca competenze con pattern testuale
    $textualComps = $DB->get_records_sql(
        "SELECT c.id, c.idnumber, c.shortname
         FROM {competency} c
         JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
         WHERE cf.idnumber = 'FTM-01'
           AND c.idnumber LIKE ?
         ORDER BY c.idnumber",
        [$sectorName . '_%']
    );

    // Cerca competenze con pattern numerico (categorie)
    $numericComps = $DB->get_records_sql(
        "SELECT c.id, c.idnumber, c.shortname
         FROM {competency} c
         JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
         WHERE cf.idnumber = 'FTM-01'
           AND (c.idnumber = ? OR c.idnumber LIKE ?)
         ORDER BY c.idnumber",
        [$numCode, $numCode . '-%']
    );

    $totalComps = count($textualComps) + count($numericComps);
    $globalResults['total_competencies'] += $totalComps;

    echo "<p><strong>Competenze testuali ({$sectorName}_*):</strong> " . count($textualComps) . "</p>";
    echo "<p><strong>Competenze numeriche ({$numCode}, {$numCode}-*):</strong> " . count($numericComps) . "</p>";

    // Verifica mapping per ogni competenza
    $correctMappings = 0;
    $incorrectMappings = [];
    $areasFound = [];

    // Verifica competenze testuali
    foreach ($textualComps as $comp) {
        $extractedSector = extract_sector_from_idnumber($comp->idnumber);
        $areaInfo = get_area_info($comp->idnumber);

        if ($extractedSector === $sectorName) {
            $correctMappings++;
            $globalResults['correctly_mapped']++;
        } else {
            $incorrectMappings[] = [
                'idnumber' => $comp->idnumber,
                'expected' => $sectorName,
                'got' => $extractedSector
            ];
            $globalResults['incorrectly_mapped']++;
        }

        // Traccia aree trovate
        if (!in_array($areaInfo['code'], $areasFound) && $areaInfo['code'] !== 'OTHER') {
            $areasFound[] = $areaInfo['code'];
        }
    }

    // Verifica competenze numeriche
    foreach ($numericComps as $comp) {
        $extractedSector = extract_sector_from_idnumber($comp->idnumber);

        if ($extractedSector === $sectorName) {
            $correctMappings++;
            $globalResults['correctly_mapped']++;
        } else {
            $incorrectMappings[] = [
                'idnumber' => $comp->idnumber,
                'expected' => $sectorName,
                'got' => $extractedSector
            ];
            $globalResults['incorrectly_mapped']++;
        }
    }

    // Risultato mapping
    $mappingOk = empty($incorrectMappings);
    $mappingStyle = $mappingOk ? 'color: green;' : 'color: red;';
    echo "<p style='{$mappingStyle}'><strong>Mapping settore:</strong> {$correctMappings}/{$totalComps} corretti ";
    echo $mappingOk ? '✅' : '❌';
    echo "</p>";

    if (!empty($incorrectMappings)) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>⚠️ Errori di mapping:</strong><ul>";
        foreach (array_slice($incorrectMappings, 0, 5) as $err) {
            echo "<li><code>{$err['idnumber']}</code>: atteso <strong>{$err['expected']}</strong>, ottenuto <strong>{$err['got']}</strong></li>";
        }
        if (count($incorrectMappings) > 5) {
            echo "<li>... e altri " . (count($incorrectMappings) - 5) . " errori</li>";
        }
        echo "</ul></div>";
        $globalResults['sector_errors'][$sectorName] = $incorrectMappings;
    }

    // Verifica aree
    sort($areasFound);
    sort($expectedAreas);

    $missingAreas = array_diff($expectedAreas, $areasFound);
    $extraAreas = array_diff($areasFound, $expectedAreas);

    echo "<p><strong>Aree trovate:</strong> " . implode(', ', $areasFound) . "</p>";
    echo "<p><strong>Aree attese:</strong> " . implode(', ', $expectedAreas) . "</p>";

    if (!empty($missingAreas)) {
        echo "<p style='color: orange;'>⚠️ Aree mancanti: " . implode(', ', $missingAreas) . "</p>";
        $globalResults['area_errors'][$sectorName]['missing'] = $missingAreas;
    }
    if (!empty($extraAreas)) {
        echo "<p style='color: blue;'>ℹ️ Aree extra (non previste): " . implode(', ', $extraAreas) . "</p>";
    }
    if (empty($missingAreas) && empty($extraAreas)) {
        echo "<p style='color: green;'>✅ Tutte le aree correttamente mappate</p>";
    }

    // Mostra esempi competenze
    echo "<details><summary>📋 Mostra esempi competenze (primi 5)</summary>";
    echo "<table border='1' cellpadding='3' style='border-collapse: collapse; font-size: 11px; margin-top: 10px;'>";
    echo "<tr style='background: #f0f0f0;'><th>idnumber</th><th>Settore</th><th>Area</th></tr>";
    $count = 0;
    foreach ($textualComps as $comp) {
        if ($count++ >= 5) break;
        $sector = extract_sector_from_idnumber($comp->idnumber);
        $area = get_area_info($comp->idnumber);
        $style = ($sector === $sectorName) ? 'background: #d4edda;' : 'background: #f8d7da;';
        echo "<tr style='{$style}'>";
        echo "<td><code>{$comp->idnumber}</code></td>";
        echo "<td>{$sector}</td>";
        echo "<td>{$area['code']}</td>";
        echo "</tr>";
    }
    echo "</table></details>";
}

// =============================================
// RIEPILOGO GLOBALE
// =============================================
echo '<h3 style="background: #ffc107; color: black; padding: 10px; margin-top: 30px;">3. Riepilogo Globale</h3>';

$allOk = ($globalResults['incorrectly_mapped'] === 0);
$summaryStyle = $allOk ? 'background: #d4edda; border: 2px solid #28a745;' : 'background: #f8d7da; border: 2px solid #dc3545;';

echo "<div style='{$summaryStyle} padding: 20px; border-radius: 10px;'>";
echo "<h4>" . ($allOk ? '✅ TUTTI I SETTORI FUNZIONANO CORRETTAMENTE' : '❌ ALCUNI SETTORI HANNO PROBLEMI') . "</h4>";
echo "<table border='0' cellpadding='5'>";
echo "<tr><td><strong>Competenze totali analizzate:</strong></td><td>{$globalResults['total_competencies']}</td></tr>";
echo "<tr><td><strong>Mappate correttamente:</strong></td><td style='color: green;'>{$globalResults['correctly_mapped']}</td></tr>";
echo "<tr><td><strong>Mappate incorrettamente:</strong></td><td style='color: red;'>{$globalResults['incorrectly_mapped']}</td></tr>";
echo "</table>";

if (!empty($globalResults['sector_errors'])) {
    echo "<h5 style='margin-top: 15px;'>Settori con errori di mapping:</h5>";
    echo "<ul>";
    foreach ($globalResults['sector_errors'] as $sector => $errors) {
        echo "<li><strong>{$sector}</strong>: " . count($errors) . " errori</li>";
    }
    echo "</ul>";
}

if (!empty($globalResults['area_errors'])) {
    echo "<h5 style='margin-top: 15px;'>Settori con aree mancanti:</h5>";
    echo "<ul>";
    foreach ($globalResults['area_errors'] as $sector => $areaErrs) {
        if (!empty($areaErrs['missing'])) {
            echo "<li><strong>{$sector}</strong>: mancano " . implode(', ', $areaErrs['missing']) . "</li>";
        }
    }
    echo "</ul>";
}
echo "</div>";

// =============================================
// TEST 3: Verifica GENERICO (framework separato)
// =============================================
echo '<h3 style="background: #17a2b8; color: white; padding: 10px; margin-top: 30px;">4. Framework GENERICO (FTM_GEN)</h3>';

$genComps = $DB->get_records_sql(
    "SELECT c.id, c.idnumber, c.shortname
     FROM {competency} c
     JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
     WHERE cf.idnumber = 'FTM_GEN'
     ORDER BY c.idnumber"
);

echo "<p><strong>Competenze nel framework FTM_GEN:</strong> " . count($genComps) . "</p>";

if (!empty($genComps)) {
    echo "<table border='1' cellpadding='3' style='border-collapse: collapse; font-size: 11px;'>";
    echo "<tr style='background: #f0f0f0;'><th>idnumber</th><th>Settore Estratto</th><th>Area</th></tr>";
    foreach ($genComps as $comp) {
        $sector = extract_sector_from_idnumber($comp->idnumber);
        $area = get_area_info($comp->idnumber);
        $style = ($sector === 'GEN' || $sector === 'GENERICO') ? 'background: #d4edda;' : '';
        echo "<tr style='{$style}'>";
        echo "<td><code>{$comp->idnumber}</code></td>";
        echo "<td>{$sector}</td>";
        echo "<td>{$area['code']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo $OUTPUT->footer();
