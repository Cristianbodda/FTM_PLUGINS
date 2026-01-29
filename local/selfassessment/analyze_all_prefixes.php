<?php
// ============================================
// ANALISI COMPLETA PREFISSI COMPETENZE
// Trova TUTTI i prefissi usati nel database
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/analyze_all_prefixes.php'));
$PAGE->set_title('Analisi Prefissi Competenze');

echo $OUTPUT->header();
echo '<h1>Analisi Completa Prefissi Competenze</h1>';

// Mapping attuale in compile.php (aggiornato)
$current_map = [
    // AUTOMOBILE
    'AUTOMOBILE_MAu', 'AUTOMOBILE_MR', 'AUTOMOBILE',
    // MECCANICA
    'MECCANICA_ASS', 'MECCANICA_AUT', 'MECCANICA_CNC', 'MECCANICA_CSP',
    'MECCANICA_DIS', 'MECCANICA_DT', 'MECCANICA_LAV', 'MECCANICA_LMC',
    'MECCANICA_LMB', 'MECCANICA_MAN', 'MECCANICA_MIS', 'MECCANICA_PIA',
    'MECCANICA_PRO', 'MECCANICA_PRG', 'MECCANICA_SIC', 'MECCANICA_SAQ',
    'MECCANICA_GEN', 'MECCANICA_PIAN', 'MECCANICA', 'MECC_',
    // LOGISTICA
    'LOGISTICA_LO', 'LOGISTICA',
    // AUTOMAZIONE
    'AUTOMAZIONE_', 'AUTOMAZIONE', 'AUTO_EA',
    // ELETTRONICA / ELETTRICITA
    'ELETTRONICA_', 'ELETTRONICA',
    'ELETTRICITÀ_', 'ELETTRICITÀ', 'ELETTRICITA_', 'ELETTRICITA', 'ELET_',
    // CHIMICA
    'CHIMFARM_', 'CHIMFARM', 'CHIMICA_', 'CHIMICA', 'FARMACEUTICA',
    // METALCOSTRUZIONE
    'METALCOSTRUZIONE_', 'METALCOSTRUZIONE', 'METAL_',
    // INFORMATICA
    'INFORMATICA_', 'INFORMATICA', 'ICT_',
    // SICUREZZA
    'SICUREZZA_', 'SICUREZZA',
    // GENERICHE
    'GEN_', 'GEN', 'GENERICO_', 'GENERICO', 'GENERICHE', 'TRASVERSALI', 'SOFT_',
    // EDILIZIA
    'EDILIZIA_', 'EDILIZIA', 'COSTRUZIONI',
    // RISTORAZIONE
    'RISTORAZIONE', 'ALIMENTARE', 'CUCINA',
    // COMMERCIO
    'COMMERCIO', 'VENDITA',
    // OLD prefixes
    'OLD_LOGISTICA', 'OLD_MECCANICA', 'OLD_AUTOMOBILE', 'OLD_CHIMFARM', 'OLD_CHIMICA',
    'OLD_ELETTRONICA', 'OLD_ELETTRICITA', 'OLD_AUTOMAZIONE', 'OLD_METALCOSTRUZIONE',
    'OLD_INFORMATICA', 'OLD_GENERICO', 'OLD_02', 'OLD_', 'OLD'
];

// 1. Trova TUTTE le competenze nel database
$all_competencies = $DB->get_records('competency', [], 'idnumber', 'id, idnumber, shortname');
echo "<p>Totale competenze nel database: <strong>" . count($all_competencies) . "</strong></p>";

// 2. Trova competenze assegnate per autovalutazione
$assigned_comps = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.idnumber, c.shortname
    FROM {local_selfassessment_assign} a
    JOIN {competency} c ON c.id = a.competencyid
");
echo "<p>Competenze assegnate per autovalutazione: <strong>" . count($assigned_comps) . "</strong></p>";

// 3. Estrai tutti i prefissi unici
function extract_prefix($idnumber) {
    // Strategia 1: Prendi tutto prima dell'ultimo underscore seguito da lettere/numeri (es. A1, B2)
    if (preg_match('/^(.+)_[A-Z]\d+$/i', $idnumber, $matches)) {
        return $matches[1];
    }
    // Strategia 2: Prendi le prime due parti separate da underscore
    $parts = explode('_', $idnumber);
    if (count($parts) >= 2) {
        return $parts[0] . '_' . $parts[1];
    }
    // Strategia 3: Prendi la prima parte
    return $parts[0];
}

function extract_sector($idnumber) {
    $parts = explode('_', $idnumber);
    return $parts[0];
}

// Analizza competenze assegnate
$prefixes = [];
$sectors = [];

foreach ($assigned_comps as $comp) {
    $prefix = extract_prefix($comp->idnumber);
    $sector = extract_sector($comp->idnumber);

    if (!isset($prefixes[$prefix])) {
        $prefixes[$prefix] = ['count' => 0, 'examples' => [], 'sector' => $sector];
    }
    $prefixes[$prefix]['count']++;
    if (count($prefixes[$prefix]['examples']) < 3) {
        $prefixes[$prefix]['examples'][] = $comp->idnumber;
    }

    if (!isset($sectors[$sector])) {
        $sectors[$sector] = 0;
    }
    $sectors[$sector]++;
}

// Ordina per settore
ksort($prefixes);
arsort($sectors);

// 4. Mostra settori principali
echo '<h2>Settori Principali</h2>';
echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
echo '<tr style="background: #f0f0f0;"><th>Settore</th><th>Competenze</th><th>Nel mapping?</th></tr>';

foreach ($sectors as $sector => $count) {
    $in_map = false;
    foreach ($current_map as $mapped) {
        if (strpos($mapped, $sector) === 0 || $sector === $mapped) {
            $in_map = true;
            break;
        }
    }
    $status = $in_map ? '<span style="color: green;">✅ SI</span>' : '<span style="color: red;">❌ NO - DA AGGIUNGERE</span>';
    $row_style = $in_map ? '' : 'background: #ffebee;';
    echo "<tr style='{$row_style}'><td><strong>{$sector}</strong></td><td>{$count}</td><td>{$status}</td></tr>";
}
echo '</table>';

// 5. Mostra prefissi dettagliati
echo '<h2>Prefissi Dettagliati</h2>';
echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">';
echo '<tr style="background: #f0f0f0;"><th>Prefisso</th><th>Settore</th><th>Count</th><th>Esempi</th><th>Nel mapping?</th></tr>';

foreach ($prefixes as $prefix => $data) {
    $in_map = in_array($prefix, $current_map);
    // Controlla anche match parziali
    if (!$in_map) {
        foreach ($current_map as $mapped) {
            if (strpos($prefix, $mapped) === 0) {
                $in_map = true;
                break;
            }
        }
    }

    $status = $in_map ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌ MANCANTE</span>';
    $row_style = $in_map ? '' : 'background: #fff3cd;';

    echo "<tr style='{$row_style}'>";
    echo "<td><strong>{$prefix}</strong></td>";
    echo "<td>{$data['sector']}</td>";
    echo "<td>{$data['count']}</td>";
    echo "<td style='font-size: 0.85em;'>" . implode('<br>', $data['examples']) . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}
echo '</table>';

// 6. Genera codice PHP per mapping completo
echo '<h2>Codice PHP per Mapping Completo</h2>';
echo '<p>Copia questo codice in compile.php:</p>';

$suggested_map = [
    // Settori principali con icone e colori
    'AUTOMOBILE' => ['nome' => 'Automobile', 'icona' => '🚗', 'colore' => '#3498db'],
    'MECCANICA' => ['nome' => 'Meccanica', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'LOGISTICA' => ['nome' => 'Logistica', 'icona' => '📦', 'colore' => '#ff9800'],
    'AUTOMAZIONE' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#673ab7'],
    'ELETTRONICA' => ['nome' => 'Elettronica', 'icona' => '⚡', 'colore' => '#2196f3'],
    'ELETTRICITA' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],
    'CHIMFARM' => ['nome' => 'Chimica Farmaceutica', 'icona' => '🧪', 'colore' => '#9c27b0'],
    'CHIMICA' => ['nome' => 'Chimica', 'icona' => '⚗️', 'colore' => '#673ab7'],
    'METALCOSTRUZIONE' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#455a64'],
    'GENERICO' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'INFORMATICA' => ['nome' => 'Informatica', 'icona' => '💻', 'colore' => '#00bcd4'],
    'SICUREZZA' => ['nome' => 'Sicurezza', 'icona' => '🛡️', 'colore' => '#f44336'],
    'OLD' => ['nome' => 'Legacy', 'icona' => '📁', 'colore' => '#9e9e9e'],
];

echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto;">';
echo htmlspecialchars('$area_map = [') . "\n";

// Genera mapping per ogni settore trovato
foreach ($sectors as $sector => $count) {
    if (isset($suggested_map[$sector])) {
        $info = $suggested_map[$sector];
    } else {
        // Genera automaticamente
        $info = [
            'nome' => ucfirst(strtolower($sector)),
            'icona' => '📋',
            'colore' => '#' . substr(md5($sector), 0, 6)
        ];
    }

    echo htmlspecialchars("    '{$sector}' => ['nome' => '{$info['nome']}', 'icona' => '{$info['icona']}', 'colore' => '{$info['colore']}'],") . "\n";
}

echo htmlspecialchars('];');
echo '</pre>';

// 7. Lista competenze che andrebbero in ALTRO
echo '<h2>Competenze che finirebbero in "ALTRO"</h2>';

$altro = [];
foreach ($assigned_comps as $comp) {
    $matched = false;
    foreach ($sectors as $sector => $c) {
        if (strpos($comp->idnumber, $sector) === 0) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $altro[] = $comp->idnumber;
    }
}

if (empty($altro)) {
    echo '<p style="color: green; font-weight: bold;">✅ Nessuna competenza finirà in "ALTRO" con il mapping completo!</p>';
} else {
    echo '<p style="color: red;">⚠️ ' . count($altro) . ' competenze finirebbero ancora in "ALTRO":</p>';
    echo '<ul>';
    foreach ($altro as $idn) {
        echo "<li>{$idn}</li>";
    }
    echo '</ul>';
}

echo $OUTPUT->footer();
