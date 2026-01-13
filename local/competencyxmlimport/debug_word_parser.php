<?php
/**
 * DEBUG COMPLETO Word Parser
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

require_once(__DIR__ . '/classes/word_parser.php');

echo "<h1>🔍 Debug Word Parser</h1>";

// Form upload
echo "<form method='post' enctype='multipart/form-data' style='margin:20px 0; padding:20px; background:#e0e0ff;'>";
echo "<strong>Carica file Word:</strong> ";
echo "<input type='file' name='testfile' accept='.docx'> ";
echo "<button type='submit'>Analizza</button>";
echo "</form>";

if (!isset($_FILES['testfile']) || $_FILES['testfile']['error'] != 0) {
    echo "<p>Carica un file .docx per analizzarlo.</p>";
    exit;
}

$tmp = $_FILES['testfile']['tmp_name'];
$filename = $_FILES['testfile']['name'];

echo "<pre style='background:#f5f5f5; padding:20px; font-size:12px; max-height:none; overflow:auto;'>";
echo "=== FILE: $filename ===\n\n";

// Step 1: Apri ZIP
$zip = new ZipArchive();
$zip_result = $zip->open($tmp);

if ($zip_result !== true) {
    echo "❌ ERRORE: Impossibile aprire come ZIP (codice: $zip_result)\n";
    echo "</pre>";
    exit;
}
echo "✅ ZIP aperto correttamente\n";

// Step 2: Leggi document.xml
$xml_content = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml_content === false) {
    echo "❌ ERRORE: word/document.xml non trovato nello ZIP\n";
    echo "</pre>";
    exit;
}
echo "✅ document.xml estratto (" . strlen($xml_content) . " bytes)\n\n";

// Step 3: Prova parsing con DOMDocument (più robusto)
echo "=== PARSING XML CON DOMDocument ===\n";

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$load_result = $dom->loadXML($xml_content);
$xml_errors = libxml_get_errors();
libxml_clear_errors();

if (!$load_result) {
    echo "❌ Errori XML:\n";
    foreach ($xml_errors as $e) {
        echo "  Line {$e->line}: {$e->message}";
    }
} else {
    echo "✅ XML parsato correttamente\n";
}

// Step 4: Estrai testo
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

// Metodo 1: Estrai tutti i w:t
$text_nodes = $xpath->query('//w:t');
echo "Nodi w:t trovati: " . $text_nodes->length . "\n";

$full_text_raw = '';
foreach ($text_nodes as $node) {
    $full_text_raw .= $node->textContent;
}

// Metodo 2: Estrai per paragrafi (con newline)
$paragraphs = $xpath->query('//w:p');
echo "Paragrafi w:p trovati: " . $paragraphs->length . "\n";

$full_text_para = '';
foreach ($paragraphs as $p) {
    $para_texts = $xpath->query('.//w:t', $p);
    $para_content = '';
    foreach ($para_texts as $t) {
        $para_content .= $t->textContent;
    }
    if (trim($para_content)) {
        $full_text_para .= $para_content . "\n";
    }
}

echo "\n=== TESTO ESTRATTO (raw, senza newline) ===\n";
echo "Lunghezza: " . strlen($full_text_raw) . " caratteri\n";
echo htmlspecialchars(substr($full_text_raw, 0, 1500)) . "\n";

echo "\n=== TESTO ESTRATTO (con newline per paragrafo) ===\n";
echo "Lunghezza: " . strlen($full_text_para) . " caratteri\n";
echo "Righe: " . substr_count($full_text_para, "\n") . "\n\n";
echo htmlspecialchars(substr($full_text_para, 0, 2000)) . "\n";

// Step 5: Test TUTTI i pattern possibili
echo "\n=== TEST PATTERN SU TESTO RAW ===\n";

$patterns = [
    'Q(\d+)' => '/Q(\d+)/',
    'Q01, Q02...' => '/Q0(\d)/',
    '\nQ(\d+)\n' => '/\nQ(\d+)\n/',
    '\nQ(\d+)\s' => '/\nQ(\d+)\s/',
    'Domanda N' => '/Domanda\s*(\d+)/i',
    'N. o N)' => '/\n(\d+)[\.\)]\s/',
    '**Q01**' => '/\*\*Q(\d+)\*\*/',
    'Q01 (CODICE)' => '/Q(\d+)\s*\([^)]+\)/',
];

foreach ($patterns as $name => $pattern) {
    preg_match_all($pattern, $full_text_raw, $m);
    $count = count($m[0]);
    echo sprintf("%-20s: %d match", $name, $count);
    if ($count > 0 && $count <= 10) {
        echo " → " . implode(', ', $m[0]);
    }
    echo "\n";
}

echo "\n=== TEST PATTERN SU TESTO CON PARAGRAFI ===\n";

foreach ($patterns as $name => $pattern) {
    preg_match_all($pattern, $full_text_para, $m);
    $count = count($m[0]);
    echo sprintf("%-20s: %d match", $name, $count);
    if ($count > 0 && $count <= 10) {
        echo " → " . implode(', ', $m[0]);
    }
    echo "\n";
}

// Step 6: Pattern originale del parser
echo "\n=== PATTERN ORIGINALE DEL PARSER ===\n";
$original_pattern = '/(?:^|\n)\s*(?:\*\*)?Q(\d+)(?:\s*\([^)]+\))?(?:\*\*)?\s*\n/';
echo "Pattern: $original_pattern\n";

preg_match_all($original_pattern, $full_text_para, $m);
echo "Match su testo paragrafi: " . count($m[0]) . "\n";

preg_match_all($original_pattern, $full_text_raw, $m);
echo "Match su testo raw: " . count($m[0]) . "\n";

// Prova split come fa il parser
$parts = preg_split($original_pattern, $full_text_para, -1, PREG_SPLIT_DELIM_CAPTURE);
echo "Split risultato: " . count($parts) . " parti\n";

// Step 7: Ora prova il parser vero
echo "\n=== TEST PARSER REALE ===\n";

$parser = new \local_competencyxmlimport\word_parser('AUTOMOBILE', []);
$result = $parser->parse_file($tmp);

echo "Success: " . ($result['success'] ? 'SI' : 'NO') . "\n";
echo "Domande trovate: " . $result['total_questions'] . "\n";
echo "Valid: " . $result['valid_count'] . "\n";
echo "Warning: " . $result['warning_count'] . "\n";
echo "Error: " . $result['error_count'] . "\n";

if (!empty($result['errors'])) {
    echo "\nErrori parser:\n";
    foreach ($result['errors'] as $e) {
        echo "- $e\n";
    }
}

if (!empty($result['questions'])) {
    echo "\n=== PRIME 3 DOMANDE TROVATE ===\n";
    foreach (array_slice($result['questions'], 0, 3) as $i => $q) {
        echo "\n--- Domanda " . ($i+1) . " ---\n";
        echo "Num: " . $q['num'] . "\n";
        echo "Text: " . substr($q['text'], 0, 100) . "...\n";
        echo "Competency: " . $q['competency'] . "\n";
        echo "Correct: " . $q['correct_answer'] . "\n";
        echo "Answers: " . count($q['answers']) . "\n";
        echo "Status: " . $q['status'] . "\n";
    }
}

echo "\n</pre>";
