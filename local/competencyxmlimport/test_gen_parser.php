<?php
/**
 * Debug script per testare il parsing del formato GENERICI LOG-STYLE
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

require_once($CFG->dirroot . '/local/competencyxmlimport/classes/word_parser.php');

$filepath = 'C:\\Users\\cristian.bodda\\OneDrive - Fondazione Terzo Millennio\\Desktop\\Nuova cartella (2)\\Cristian Generici\\Generici LOG_STYLE V2\\Generici LOG_STYLE V2\\Generici 80 Domande Log_Style V2.docx';

echo "=== DEBUG WORD PARSER - GENERICI LOG-STYLE ===\n\n";

// Test 1: Verifica file esiste
echo "1. File esiste: " . (file_exists($filepath) ? "SI" : "NO") . "\n";

if (!file_exists($filepath)) {
    die("File non trovato!\n");
}

// Test 2: Estrai testo raw dal docx
echo "\n2. Estrazione testo dal DOCX...\n";

$zip = new ZipArchive();
if ($zip->open($filepath) !== true) {
    die("Impossibile aprire il file ZIP!\n");
}

$xml_content = $zip->getFromName('word/document.xml');
$zip->close();

if ($xml_content === false) {
    die("document.xml non trovato!\n");
}

// Estrai paragrafi
$paragraphs = [];
preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml_content, $matches);

foreach ($matches[1] as $p_content) {
    preg_match_all('/<w:br[^>]*\/?>|<w:t[^>]*>([^<]*)<\/w:t>/i', $p_content, $elem_matches, PREG_SET_ORDER);

    $text_parts = [];
    foreach ($elem_matches as $match) {
        if (stripos($match[0], '<w:br') !== false) {
            $text_parts[] = "\n";
        } elseif (isset($match[1])) {
            $text_parts[] = $match[1];
        }
    }

    $paragraph_text = implode('', $text_parts);
    $lines = explode("\n", $paragraph_text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $paragraphs[] = $line;
        }
    }
}

$text = implode("\n", $paragraphs);

echo "Testo estratto (primi 2000 caratteri):\n";
echo "----------------------------------------\n";
echo substr($text, 0, 2000);
echo "\n----------------------------------------\n";

// Test 3: Verifica pattern di rilevamento
echo "\n3. Verifica pattern rilevamento:\n";

$has_domanda_marker = (bool) preg_match('/(?:^|\n)Domanda\s+\d+\s*\n/um', $text);
echo "   - has_domanda_marker (Domanda X\\n): " . ($has_domanda_marker ? "SI" : "NO") . "\n";

$has_gen_competenza = (bool) preg_match('/Competenza:\s*\n\s*GEN_/um', $text);
echo "   - has_gen_competenza (Competenza:\\n GEN_): " . ($has_gen_competenza ? "SI" : "NO") . "\n";

$has_id_domanda = (bool) preg_match('/ID\s*domanda:\s*\n\s*GEN_Q/um', $text);
echo "   - has_id_domanda (ID domanda:\\n GEN_Q): " . ($has_id_domanda ? "SI" : "NO") . "\n";

// Test 3b: Pattern alternativi
echo "\n3b. Pattern alternativi:\n";

$has_domanda_simple = (bool) preg_match('/Domanda\s+\d+/um', $text);
echo "   - Domanda X (senza newline): " . ($has_domanda_simple ? "SI" : "NO") . "\n";

$has_competenza_gen = (bool) preg_match('/Competenza:.*GEN_/us', $text);
echo "   - Competenza:...GEN_: " . ($has_competenza_gen ? "SI" : "NO") . "\n";

$has_gen_code = (bool) preg_match('/GEN_[A-Z]_\d+/u', $text);
echo "   - GEN_X_## pattern: " . ($has_gen_code ? "SI" : "NO") . "\n";

// Test 4: Mostra prime righe
echo "\n4. Prime 50 righe del testo:\n";
$lines = explode("\n", $text);
for ($i = 0; $i < min(50, count($lines)); $i++) {
    echo sprintf("%3d: %s\n", $i+1, $lines[$i]);
}

// Test 5: Usa il parser
echo "\n\n5. Test con word_parser:\n";
$parser = new \local_competencyxmlimport\word_parser('GENERICO', []);
$result = $parser->parse_file($filepath);

echo "   - Formato rilevato: " . $result['format'] . "\n";
echo "   - Descrizione: " . $parser->get_format_description() . "\n";
echo "   - Totale domande: " . $result['total_questions'] . "\n";
echo "   - Valide: " . $result['valid_count'] . "\n";
echo "   - Warning: " . $result['warning_count'] . "\n";
echo "   - Errori: " . $result['error_count'] . "\n";

if (!empty($result['errors'])) {
    echo "\n   Errori:\n";
    foreach (array_slice($result['errors'], 0, 5) as $err) {
        echo "   - $err\n";
    }
}

if (!empty($result['questions'])) {
    echo "\n   Prima domanda:\n";
    print_r($result['questions'][0]);
}

echo "\n=== FINE DEBUG ===\n";
