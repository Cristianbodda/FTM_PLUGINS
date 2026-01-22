<?php
/**
 * Analizza CSV Framework e genera query SQL per verifica
 * Script standalone - non richiede Moodle
 */

// Percorso CSV framework
$csv_path = 'C:\\Users\\cristian.bodda\\Downloads\\Passaporto tecnico FTM-FTM-01-20251204_1501-comma_separated.csv';

echo "=======================================================\n";
echo "   ANALISI CSV FRAMEWORK COMPETENZE\n";
echo "=======================================================\n\n";

// Leggi competenze dal CSV
$csv_competencies = [];
$csv_sectors = [];

if (!file_exists($csv_path)) {
    echo "ERRORE: File CSV non trovato: $csv_path\n";
    exit(1);
}

$handle = fopen($csv_path, 'r');
if (!$handle) {
    echo "ERRORE: Impossibile aprire il file CSV\n";
    exit(1);
}

// Skip header
fgetcsv($handle, 0, ',', '"');

while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
    if (count($row) < 3) {
        continue;
    }

    $idnumber = trim($row[1]);

    // Le competenze hanno formato: SETTORE_AREA_CODICE
    if (preg_match('/^([A-ZÀÈÉÌÒÙ]+)_([A-Z0-9]+)_([A-Z0-9]+)$/u', $idnumber, $matches)) {
        $sector = $matches[1];
        $area = $matches[2];

        $csv_competencies[$idnumber] = [
            'idnumber' => $idnumber,
            'sector' => $sector,
            'area' => $area,
            'shortname' => trim($row[2])
        ];

        if (!isset($csv_sectors[$sector])) {
            $csv_sectors[$sector] = [];
        }
        $csv_sectors[$sector][] = $idnumber;
    }
}
fclose($handle);

echo "TOTALE COMPETENZE NEL CSV: " . count($csv_competencies) . "\n\n";

// Riepilogo per settore
echo "RIEPILOGO PER SETTORE:\n";
echo str_repeat("-", 40) . "\n";
echo str_pad("Settore", 22) . "Competenze\n";
echo str_repeat("-", 40) . "\n";

ksort($csv_sectors);
foreach ($csv_sectors as $sector => $comps) {
    echo str_pad($sector, 22) . count($comps) . "\n";
}
echo str_repeat("-", 40) . "\n";
echo str_pad("TOTALE", 22) . count($csv_competencies) . "\n\n";

// Genera query SQL per phpMyAdmin
echo "=======================================================\n";
echo "   QUERY SQL PER PHPMYADMIN\n";
echo "=======================================================\n\n";

echo "-- 1. Conta competenze totali nel framework 9\n";
echo "SELECT COUNT(*) as totale FROM mdl_competency WHERE competencyframeworkid = 9;\n\n";

echo "-- 2. Conta competenze per settore\n";
echo "SELECT \n";
echo "    CASE \n";
foreach (array_keys($csv_sectors) as $i => $sector) {
    $pattern = $sector . '_%';
    echo "        WHEN idnumber LIKE '$pattern' THEN '$sector'\n";
}
echo "        ELSE 'ALTRO'\n";
echo "    END as settore,\n";
echo "    COUNT(*) as totale\n";
echo "FROM mdl_competency \n";
echo "WHERE competencyframeworkid = 9 \n";
echo "AND idnumber IS NOT NULL \n";
echo "AND idnumber != ''\n";
echo "GROUP BY settore\n";
echo "ORDER BY settore;\n\n";

// Genera query per ogni settore
echo "-- 3. Query dettagliate per settore (esegui una alla volta)\n\n";

foreach ($csv_sectors as $sector => $expected_comps) {
    echo "-- $sector: attese " . count($expected_comps) . " competenze\n";
    echo "SELECT idnumber, shortname FROM mdl_competency \n";
    echo "WHERE competencyframeworkid = 9 AND idnumber LIKE '{$sector}_%'\n";
    echo "ORDER BY idnumber;\n\n";
}

// Lista completa competenze attese per settore
echo "=======================================================\n";
echo "   LISTA COMPETENZE ATTESE PER SETTORE\n";
echo "=======================================================\n\n";

foreach ($csv_sectors as $sector => $comps) {
    echo "--- $sector (" . count($comps) . " competenze) ---\n";
    sort($comps);
    foreach ($comps as $c) {
        echo "$c\n";
    }
    echo "\n";
}

echo "=== FINE ANALISI ===\n";
