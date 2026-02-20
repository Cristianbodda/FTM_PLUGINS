<?php
/**
 * Analizza file Word per debug formato
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

$folder = 'C:\\Users\\cristian.bodda\\OneDrive - Fondazione Terzo Millennio\\Desktop\\Nuova cartella (2)\\Elettricità LogStyleV2\\Elettricità LogStyleV2';

echo "=== ANALISI FILE WORD ELETTRICITA ===\n\n";

// Lista file
$files = glob($folder . '\\*.docx');
echo "Trovati " . count($files) . " file Word\n\n";

foreach ($files as $filepath) {
    $filename = basename($filepath);
    echo "========================================\n";
    echo "FILE: $filename\n";
    echo "========================================\n";

    // Estrai testo dal docx
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        echo "ERRORE: Impossibile aprire il file\n\n";
        continue;
    }

    $xml_content = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml_content === false) {
        echo "ERRORE: document.xml non trovato\n\n";
        continue;
    }

    // Estrai paragrafi con gestione <w:br/>
    $paragraphs = [];
    preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml_content, $matches);

    foreach ($matches[1] as $p_content) {
        preg_match_all('/<w:br[^>]*\\/?>|<w:t[^>]*>([^<]*)<\/w:t>/i', $p_content, $elem_matches, PREG_SET_ORDER);

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

    // Mostra prime 30 righe
    echo "PRIME 30 RIGHE:\n";
    echo "---------------\n";
    $lines = explode("\n", $text);
    for ($i = 0; $i < min(30, count($lines)); $i++) {
        echo sprintf("%3d: %s\n", $i+1, $lines[$i]);
    }

    // Cerca pattern competenze
    echo "\nPATTERN COMPETENZE TROVATI:\n";
    echo "---------------------------\n";

    // Pattern ELETTRICITÀ_XX_YY
    preg_match_all('/ELETTRICIT[AÀ]_[A-Z]+_[A-Z0-9]+/ui', $text, $comp_matches);
    $unique_comps = array_unique($comp_matches[0]);
    echo "Competenze ELETTRICITÀ trovate: " . count($unique_comps) . "\n";
    foreach (array_slice($unique_comps, 0, 10) as $c) {
        echo "  - $c\n";
    }

    // Cerca Q01, Q02, etc.
    preg_match_all('/Q0?\d+/i', $text, $q_matches);
    $unique_q = array_unique($q_matches[0]);
    echo "\nDomande (Q##) trovate: " . count($unique_q) . "\n";

    // Cerca pattern specifici
    $has_elet_base = (bool) preg_match('/ELET_BASE_Q\d+/i', $text);
    $has_pipe = (bool) preg_match('/\|\s*Codice\s*competenza/i', $text);
    $has_checkmark = (bool) preg_match('/[✓✔☑]/u', $text);
    $has_bullet = (bool) preg_match('/^[•●○◦▪▸►→-]\s/m', $text);
    $has_competenza_label = (bool) preg_match('/Competenza:/i', $text);
    $has_codice_label = (bool) preg_match('/Codice:/i', $text);

    echo "\nMARKER FORMATO:\n";
    echo "  ELET_BASE_Q##: " . ($has_elet_base ? "SI" : "NO") . "\n";
    echo "  | Codice competenza: " . ($has_pipe ? "SI" : "NO") . "\n";
    echo "  Checkmark (✓): " . ($has_checkmark ? "SI" : "NO") . "\n";
    echo "  Bullet list: " . ($has_bullet ? "SI" : "NO") . "\n";
    echo "  Competenza: label: " . ($has_competenza_label ? "SI" : "NO") . "\n";
    echo "  Codice: label: " . ($has_codice_label ? "SI" : "NO") . "\n";

    echo "\n\n";
}

echo "=== FINE ANALISI ===\n";
