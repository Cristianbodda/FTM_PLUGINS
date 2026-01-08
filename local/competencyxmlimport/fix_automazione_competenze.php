<?php
/**
 * Verifica e assegna competenze alle domande AUTOMAZIONE_72_REALI
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'check', PARAM_ALPHA);

echo "<h1>🔧 Verifica/Assegna Competenze AUTOMAZIONE</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #4a5568; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.ok { color: green; }
.error { color: red; }
.warning { color: orange; }
.btn { display: inline-block; padding: 10px 20px; background: #3182ce; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
.btn:hover { background: #2c5282; }
.btn-green { background: #38a169; }
.btn-green:hover { background: #2f855a; }
.stats { display: flex; gap: 20px; margin: 20px 0; }
.stat-box { background: #e2e8f0; padding: 20px; border-radius: 8px; text-align: center; min-width: 150px; }
.stat-box .number { font-size: 36px; font-weight: bold; color: #2d3748; }
.stat-box .label { color: #718096; }
</style>";

$frameworkid = 9; // FTM-01

// Trova tutte le domande AUTOMAZIONE_72_REALI
$questions = $DB->get_records_sql(
    "SELECT q.id, q.name
     FROM {question} q
     WHERE q.name LIKE 'AUTOMAZIONE_72_REALI%'
     ORDER BY q.id"
);

$total = count($questions);
$with_comp = 0;
$without_comp = 0;
$details = [];

foreach ($questions as $q) {
    // Estrai codice competenza
    $comp_code = null;
    if (preg_match('/(AUTOMAZIONE_[A-Z]+_[A-Z0-9]+)/', $q->name, $m)) {
        $comp_code = $m[1];
    }
    
    // Cerca se già assegnata
    $assigned = $DB->get_record_sql(
        "SELECT qbc.*, c.idnumber 
         FROM {qbank_competenciesbyquestion} qbc
         JOIN {competency} c ON c.id = qbc.competencyid
         WHERE qbc.questionid = ?",
        [$q->id]
    );
    
    if ($assigned) {
        $with_comp++;
        $details[] = [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $comp_code,
            'status' => 'ok',
            'assigned_code' => $assigned->idnumber
        ];
    } else {
        $without_comp++;
        $details[] = [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $comp_code,
            'status' => 'missing',
            'assigned_code' => null
        ];
    }
}

// Statistiche
echo "<div class='stats'>";
echo "<div class='stat-box'><div class='number'>$total</div><div class='label'>Domande Totali</div></div>";
echo "<div class='stat-box'><div class='number ok'>$with_comp</div><div class='label'>✅ Con Competenza</div></div>";
echo "<div class='stat-box'><div class='number error'>$without_comp</div><div class='label'>❌ Senza Competenza</div></div>";
echo "</div>";

if ($action == 'assign' && $without_comp > 0) {
    echo "<h2>🔧 Assegnazione in corso...</h2>";
    
    $assigned_count = 0;
    $errors = [];
    
    foreach ($details as &$d) {
        if ($d['status'] == 'missing' && $d['code']) {
            // Trova competenza
            $comp = $DB->get_record('competency', [
                'idnumber' => $d['code'],
                'competencyframeworkid' => $frameworkid
            ]);
            
            if ($comp) {
                // Assegna
                $record = new stdClass();
                $record->questionid = $d['id'];
                $record->competencyid = $comp->id;
                $record->timecreated = time();
                $record->timemodified = time();
                
                try {
                    $DB->insert_record('qbank_competenciesbyquestion', $record);
                    $assigned_count++;
                    $d['status'] = 'assigned';
                    $d['assigned_code'] = $d['code'];
                } catch (Exception $e) {
                    $errors[] = "Q{$d['id']}: " . $e->getMessage();
                }
            } else {
                $errors[] = "Competenza {$d['code']} non trovata";
            }
        }
    }
    
    echo "<div style='background: #c6f6d5; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>✅ Assegnate $assigned_count competenze!</strong>";
    echo "</div>";
    
    if (!empty($errors)) {
        echo "<div style='background: #fed7d7; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        echo "<strong>⚠️ Errori:</strong><ul>";
        foreach ($errors as $e) {
            echo "<li>$e</li>";
        }
        echo "</ul></div>";
    }
    
    // Aggiorna conteggi
    $with_comp += $assigned_count;
    $without_comp -= $assigned_count;
}

// Tabella dettaglio
echo "<h2>📋 Dettaglio Domande</h2>";
echo "<table>";
echo "<tr><th>ID</th><th>Nome Domanda</th><th>Codice Estratto</th><th>Stato</th><th>Competenza Assegnata</th></tr>";

foreach ($details as $d) {
    $status_class = $d['status'] == 'ok' || $d['status'] == 'assigned' ? 'ok' : 'error';
    $status_icon = $d['status'] == 'ok' ? '✅' : ($d['status'] == 'assigned' ? '🆕' : '❌');
    
    echo "<tr>";
    echo "<td>{$d['id']}</td>";
    echo "<td>" . htmlspecialchars(substr($d['name'], 0, 60)) . "</td>";
    echo "<td><code>{$d['code']}</code></td>";
    echo "<td class='$status_class'>$status_icon</td>";
    echo "<td>" . ($d['assigned_code'] ?: '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Pulsanti azione
echo "<div style='margin: 30px 0;'>";
if ($without_comp > 0) {
    echo "<a href='?courseid=$courseid&action=assign' class='btn btn-green'>🔧 Assegna $without_comp Competenze Mancanti</a>";
}
echo "<a href='debug_competenze.php?courseid=$courseid' class='btn'>🔍 Debug Completo</a>";
echo "<a href='dashboard.php?courseid=$courseid' class='btn'>← Dashboard</a>";
echo "</div>";
