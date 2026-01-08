<?php
/**
 * Debug: Verifica perché le competenze non vengono assegnate
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);

echo "<h1>🔍 Debug Assegnazione Competenze</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #4a5568; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.ok { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.section { background: #e2e8f0; padding: 15px; margin: 20px 0; border-radius: 8px; }
code { background: #f1f1f1; padding: 2px 6px; border-radius: 4px; }
</style>";

// 1. Verifica Framework
echo "<div class='section'>";
echo "<h2>1️⃣ Framework disponibili</h2>";
$frameworks = $DB->get_records('competency_framework', [], 'id ASC');
echo "<table><tr><th>ID</th><th>Nome</th><th>IDNumber</th><th>Competenze</th></tr>";
foreach ($frameworks as $fw) {
    $count = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
    echo "<tr><td>{$fw->id}</td><td>{$fw->shortname}</td><td>{$fw->idnumber}</td><td>{$count}</td></tr>";
}
echo "</table>";
echo "</div>";

// 2. Cerca competenze AUTOMAZIONE
echo "<div class='section'>";
echo "<h2>2️⃣ Competenze AUTOMAZIONE nel database</h2>";

$auto_comps = $DB->get_records_sql(
    "SELECT id, idnumber, shortname, competencyframeworkid 
     FROM {competency} 
     WHERE idnumber LIKE 'AUTOMAZIONE_%' 
     ORDER BY idnumber"
);

if (empty($auto_comps)) {
    echo "<p class='error'>❌ NESSUNA competenza trovata con prefisso AUTOMAZIONE_</p>";
    
    // Cerca con pattern diversi
    echo "<h3>Ricerca alternative:</h3>";
    $patterns = ['AUTO%', '%AUTOMAZIONE%', '%OA_%', '%MA_%'];
    foreach ($patterns as $p) {
        $count = $DB->count_records_sql("SELECT COUNT(*) FROM {competency} WHERE idnumber LIKE ?", [$p]);
        echo "<p>Pattern <code>$p</code>: $count risultati</p>";
    }
    
    // Mostra prime 20 competenze
    echo "<h3>Prime 20 competenze nel database:</h3>";
    $sample = $DB->get_records_sql("SELECT id, idnumber, shortname, competencyframeworkid FROM {competency} LIMIT 20");
    echo "<table><tr><th>ID</th><th>IDNumber</th><th>ShortName</th><th>Framework</th></tr>";
    foreach ($sample as $c) {
        echo "<tr><td>{$c->id}</td><td>{$c->idnumber}</td><td>" . substr($c->shortname, 0, 50) . "</td><td>{$c->competencyframeworkid}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='ok'>✅ Trovate " . count($auto_comps) . " competenze AUTOMAZIONE</p>";
    echo "<table><tr><th>ID</th><th>IDNumber</th><th>Framework ID</th></tr>";
    foreach ($auto_comps as $c) {
        echo "<tr><td>{$c->id}</td><td>{$c->idnumber}</td><td>{$c->competencyframeworkid}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 3. Verifica domande importate
echo "<div class='section'>";
echo "<h2>3️⃣ Domande recenti con pattern AUTOMAZIONE</h2>";

$recent_q = $DB->get_records_sql(
    "SELECT q.id, q.name, q.timecreated
     FROM {question} q
     WHERE q.name LIKE '%AUTOMAZIONE%'
     ORDER BY q.timecreated DESC
     LIMIT 20"
);

if (empty($recent_q)) {
    echo "<p class='error'>❌ Nessuna domanda trovata con AUTOMAZIONE nel nome</p>";
} else {
    echo "<table><tr><th>ID</th><th>Nome</th><th>Codice Estratto</th><th>Creata</th></tr>";
    foreach ($recent_q as $q) {
        // Estrai codice competenza dal nome
        $code = '-';
        if (preg_match('/(AUTOMAZIONE_[A-Z]+_[A-Z0-9]+)/', $q->name, $m)) {
            $code = $m[1];
        }
        echo "<tr><td>{$q->id}</td><td>" . htmlspecialchars($q->name) . "</td><td><code>$code</code></td><td>" . date('Y-m-d H:i', $q->timecreated) . "</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 4. Verifica tabella qbank_competenciesbyquestion
echo "<div class='section'>";
echo "<h2>4️⃣ Tabella qbank_competenciesbyquestion</h2>";

$qbc_count = $DB->count_records('qbank_competenciesbyquestion');
echo "<p>Totale record: <strong>$qbc_count</strong></p>";

// Ultimi 10 record
$qbc_recent = $DB->get_records_sql(
    "SELECT qbc.*, c.idnumber as comp_code, q.name as q_name
     FROM {qbank_competenciesbyquestion} qbc
     LEFT JOIN {competency} c ON c.id = qbc.competencyid
     LEFT JOIN {question} q ON q.id = qbc.questionid
     ORDER BY qbc.id DESC
     LIMIT 10"
);

if (!empty($qbc_recent)) {
    echo "<h3>Ultimi 10 record:</h3>";
    echo "<table><tr><th>ID</th><th>Question ID</th><th>Competency ID</th><th>Codice</th></tr>";
    foreach ($qbc_recent as $r) {
        echo "<tr><td>{$r->id}</td><td>{$r->questionid}</td><td>{$r->competencyid}</td><td>{$r->comp_code}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 5. Test ricerca competenza specifica
echo "<div class='section'>";
echo "<h2>5️⃣ Test ricerca competenza specifica</h2>";

$test_codes = ['AUTOMAZIONE_OA_A1', 'AUTOMAZIONE_MA_B1', 'AUTOMAZIONE_OA_D1'];

foreach ($test_codes as $code) {
    echo "<h3>Cerco: <code>$code</code></h3>";
    
    // Cerca in tutti i framework
    $found = $DB->get_records('competency', ['idnumber' => $code]);
    
    if (empty($found)) {
        echo "<p class='error'>❌ Non trovata in nessun framework</p>";
        
        // Cerca con LIKE
        $like_found = $DB->get_records_sql(
            "SELECT id, idnumber, competencyframeworkid FROM {competency} WHERE idnumber LIKE ?",
            ['%' . substr($code, -5) . '%']
        );
        if (!empty($like_found)) {
            echo "<p>Trovate simili con LIKE:</p><ul>";
            foreach ($like_found as $lf) {
                echo "<li>{$lf->idnumber} (framework {$lf->competencyframeworkid})</li>";
            }
            echo "</ul>";
        }
    } else {
        foreach ($found as $f) {
            echo "<p class='ok'>✅ Trovata: ID={$f->id}, Framework={$f->competencyframeworkid}</p>";
        }
    }
}
echo "</div>";

// 6. Verifica sessione (se disponibile)
echo "<div class='section'>";
echo "<h2>6️⃣ Variabili di sessione import</h2>";
if (isset($_SESSION['import_frameworkid'])) {
    echo "<p>import_frameworkid: <strong>" . $_SESSION['import_frameworkid'] . "</strong></p>";
} else {
    echo "<p class='error'>import_frameworkid: non impostato</p>";
}
if (isset($_SESSION['import_sector'])) {
    echo "<p>import_sector: <strong>" . $_SESSION['import_sector'] . "</strong></p>";
} else {
    echo "<p class='error'>import_sector: non impostato</p>";
}
echo "</div>";

// 7. Query diretta di test
echo "<div class='section'>";
echo "<h2>7️⃣ Query diretta di test</h2>";

$fw_id = 9; // FTM-01 tipicamente
$test_code = 'AUTOMAZIONE_OA_A1';

echo "<p>Query: <code>SELECT * FROM competency WHERE idnumber='$test_code' AND competencyframeworkid=$fw_id</code></p>";

$direct = $DB->get_record('competency', [
    'idnumber' => $test_code,
    'competencyframeworkid' => $fw_id
]);

if ($direct) {
    echo "<p class='ok'>✅ Trovata! ID: {$direct->id}</p>";
} else {
    echo "<p class='error'>❌ Non trovata con framework ID $fw_id</p>";
    
    // Prova senza framework
    $any = $DB->get_record('competency', ['idnumber' => $test_code]);
    if ($any) {
        echo "<p>Ma esiste con framework ID: <strong>{$any->competencyframeworkid}</strong></p>";
    }
}
echo "</div>";

echo "<hr><p><a href='dashboard.php?courseid=$courseid'>← Torna alla Dashboard</a></p>";
