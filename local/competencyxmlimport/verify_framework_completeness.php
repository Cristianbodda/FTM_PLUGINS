<?php
/**
 * Verifica completezza framework competenze
 *
 * Confronta il CSV del framework con le competenze presenti nel database Moodle.
 * Identifica competenze mancanti per ogni settore.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Percorso CSV framework
$csv_path = 'C:\\Users\\cristian.bodda\\Downloads\\Passaporto tecnico FTM-FTM-01-20251204_1501-comma_separated.csv';

// Framework ID in Moodle
$framework_id = 9; // Passaporto tecnico FTM

echo "=======================================================\n";
echo "   VERIFICA COMPLETEZZA FRAMEWORK COMPETENZE\n";
echo "=======================================================\n\n";

// 1. Leggi competenze dal CSV
echo "1. Lettura competenze dal CSV...\n";
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

$line_num = 1;
while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
    $line_num++;

    if (count($row) < 3) {
        continue;
    }

    $idnumber = trim($row[1]);

    // Le competenze hanno formato: SETTORE_AREA_CODICE (es. AUTOMOBILE_MR_A1, ELETTRICITÀ_PE_A1)
    // Escludiamo i record che sono settori o aree (senza underscore intermedi nel formato competenza)
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

echo "   Trovate " . count($csv_competencies) . " competenze nel CSV\n\n";

// Mostra riepilogo per settore
echo "   Riepilogo per settore (CSV):\n";
echo "   " . str_repeat("-", 50) . "\n";
foreach ($csv_sectors as $sector => $comps) {
    echo "   " . str_pad($sector, 20) . ": " . count($comps) . " competenze\n";
}
echo "\n";

// 2. Leggi competenze dal database
echo "2. Lettura competenze dal database (Framework ID: $framework_id)...\n";

$db_competencies = $DB->get_records('competency', ['competencyframeworkid' => $framework_id], 'idnumber ASC');

$db_by_sector = [];
$db_idnumbers = [];

foreach ($db_competencies as $comp) {
    if (empty($comp->idnumber)) {
        continue;
    }

    $db_idnumbers[] = $comp->idnumber;

    // Estrai settore
    if (preg_match('/^([A-ZÀÈÉÌÒÙ]+)_/u', $comp->idnumber, $m)) {
        $sector = $m[1];
        if (!isset($db_by_sector[$sector])) {
            $db_by_sector[$sector] = [];
        }
        $db_by_sector[$sector][] = $comp->idnumber;
    }
}

echo "   Trovate " . count($db_idnumbers) . " competenze nel database\n\n";

// Mostra riepilogo per settore (DB)
echo "   Riepilogo per settore (DB):\n";
echo "   " . str_repeat("-", 50) . "\n";
foreach ($db_by_sector as $sector => $comps) {
    echo "   " . str_pad($sector, 20) . ": " . count($comps) . " competenze\n";
}
echo "\n";

// 3. Confronta e trova differenze
echo "3. Confronto CSV vs Database...\n\n";

$missing_in_db = [];
$extra_in_db = [];

// Competenze nel CSV ma non nel DB
foreach ($csv_competencies as $idnumber => $comp) {
    if (!in_array($idnumber, $db_idnumbers)) {
        $missing_in_db[$idnumber] = $comp;
    }
}

// Competenze nel DB ma non nel CSV
foreach ($db_idnumbers as $idnumber) {
    if (!isset($csv_competencies[$idnumber])) {
        $extra_in_db[] = $idnumber;
    }
}

// Report competenze mancanti per settore
echo "=======================================================\n";
echo "   COMPETENZE MANCANTI NEL DATABASE\n";
echo "=======================================================\n\n";

if (empty($missing_in_db)) {
    echo "   Nessuna competenza mancante! Il framework e' completo.\n\n";
} else {
    $missing_by_sector = [];
    foreach ($missing_in_db as $idnumber => $comp) {
        $sector = $comp['sector'];
        if (!isset($missing_by_sector[$sector])) {
            $missing_by_sector[$sector] = [];
        }
        $missing_by_sector[$sector][] = $comp;
    }

    echo "   TOTALE MANCANTI: " . count($missing_in_db) . " competenze\n\n";

    foreach ($missing_by_sector as $sector => $comps) {
        $csv_count = isset($csv_sectors[$sector]) ? count($csv_sectors[$sector]) : 0;
        $db_count = isset($db_by_sector[$sector]) ? count($db_by_sector[$sector]) : 0;

        echo "   --- $sector ---\n";
        echo "   CSV: $csv_count | DB: $db_count | Mancanti: " . count($comps) . "\n\n";

        // Raggruppa per area
        $by_area = [];
        foreach ($comps as $c) {
            $area = $c['area'];
            if (!isset($by_area[$area])) {
                $by_area[$area] = [];
            }
            $by_area[$area][] = $c;
        }

        ksort($by_area);
        foreach ($by_area as $area => $area_comps) {
            echo "   Area $area (" . count($area_comps) . "):\n";
            foreach ($area_comps as $c) {
                echo "      - " . $c['idnumber'] . "\n";
            }
            echo "\n";
        }
    }
}

// Report competenze extra nel DB
if (!empty($extra_in_db)) {
    echo "=======================================================\n";
    echo "   COMPETENZE EXTRA NEL DATABASE (non nel CSV)\n";
    echo "=======================================================\n\n";

    foreach ($extra_in_db as $idnumber) {
        echo "   - $idnumber\n";
    }
    echo "\n";
}

// 4. Riepilogo finale
echo "=======================================================\n";
echo "   RIEPILOGO FINALE\n";
echo "=======================================================\n\n";

echo "   " . str_pad("Settore", 20) . str_pad("CSV", 8) . str_pad("DB", 8) . str_pad("Mancanti", 10) . "Status\n";
echo "   " . str_repeat("-", 60) . "\n";

$total_csv = 0;
$total_db = 0;
$total_missing = 0;

// Unisci tutti i settori
$all_sectors = array_unique(array_merge(array_keys($csv_sectors), array_keys($db_by_sector)));
sort($all_sectors);

foreach ($all_sectors as $sector) {
    $csv_count = isset($csv_sectors[$sector]) ? count($csv_sectors[$sector]) : 0;
    $db_count = isset($db_by_sector[$sector]) ? count($db_by_sector[$sector]) : 0;
    $missing = $csv_count - $db_count;
    if ($missing < 0) $missing = 0;

    $status = ($csv_count == $db_count) ? "OK" : "INCOMPLETO";

    echo "   " . str_pad($sector, 20) . str_pad($csv_count, 8) . str_pad($db_count, 8) . str_pad($missing, 10) . $status . "\n";

    $total_csv += $csv_count;
    $total_db += $db_count;
    $total_missing += $missing;
}

echo "   " . str_repeat("-", 60) . "\n";
echo "   " . str_pad("TOTALE", 20) . str_pad($total_csv, 8) . str_pad($total_db, 8) . str_pad($total_missing, 10) . "\n";
echo "\n";

// Percentuale completezza
$completeness = $total_csv > 0 ? round(($total_db / $total_csv) * 100, 1) : 0;
echo "   Completezza framework: $completeness%\n\n";

if ($total_missing > 0) {
    echo "   AZIONE RICHIESTA: Reimportare il framework completo in Moodle.\n";
    echo "   File CSV: $csv_path\n";
    echo "   Percorso: Amministrazione > Competenze > Importa quadro delle competenze\n\n";
}

echo "=== FINE VERIFICA ===\n";
