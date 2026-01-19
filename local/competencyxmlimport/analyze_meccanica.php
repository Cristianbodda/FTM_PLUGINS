<?php
/**
 * Script per analizzare tutti i file Word MECCANICA
 */

// Percorso cartella Meccanica
$folder = 'C:\Users\cristian.bodda\OneDrive - Fondazione Terzo Millennio\Desktop\Nuova cartella (2)\Meccanica LogStyleV2 (2)\Meccanica LogStyleV2';

echo "=== ANALISI FILE MECCANICA ===\n\n";

$files = glob($folder . '/*.docx');

foreach ($files as $filepath) {
    $filename = basename($filepath);
    echo str_repeat("=", 80) . "\n";
    echo "FILE: $filename\n";
    echo str_repeat("=", 80) . "\n\n";

    // Estrai testo dal Word
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        echo "ERRORE: Impossibile aprire il file\n\n";
        continue;
    }

    $xml_content = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xml_content) {
        echo "ERRORE: Impossibile leggere document.xml\n\n";
        continue;
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

    // Mostra prime 50 righe
    echo "PRIME 50 RIGHE:\n";
    echo str_repeat("-", 40) . "\n";
    foreach (array_slice($paragraphs, 0, 50) as $i => $line) {
        $num = str_pad($i + 1, 3, ' ', STR_PAD_LEFT);

        // Evidenzia pattern importanti
        $marker = "";
        if (preg_match('/^MECC_/', $line)) {
            $marker = " [MECC_ID]";
        } elseif (preg_match('/^\d+\.\s*MECC_/', $line)) {
            $marker = " [NUM.MECC_ID]";
        } elseif (preg_match('/^Q\d+/i', $line)) {
            $marker = " [Q##]";
        } elseif (preg_match('/Competenza:/i', $line)) {
            $marker = " [COMPETENZA]";
        } elseif (preg_match('/Codice.*competenza/i', $line)) {
            $marker = " [CODICE_COMP]";
        } elseif (preg_match('/^[A-D][\)\.]/i', $line)) {
            $marker = " [RISPOSTA]";
        } elseif (preg_match('/Risposta.*corretta/i', $line)) {
            $marker = " [RISP_CORR]";
        } elseif (preg_match('/MECCANICA_[A-Z0-9_]+/i', $line)) {
            $marker = " [COMP_CODE]";
        }

        echo "$num: $line$marker\n";
    }

    echo "\n";

    // Analizza pattern
    $text = implode("\n", $paragraphs);

    echo "PATTERN RILEVATI:\n";
    echo str_repeat("-", 40) . "\n";

    // Pattern specifici per MECCANICA
    $patterns = [
        'MECC_XXX_Q## (solo ID)' => '/(?:^|\n)MECC_[A-Z]+_Q\d+\s*$/um',
        '1. MECC_XXX_Q## (num + ID)' => '/(?:^|\n)\d+\.\s*MECC_[A-Z0-9_]+_Q\d+\s*$/um',
        'Q## semplice' => '/(?:^|\n)Q\d+\s*$/um',
        'Q## – COMP' => '/(?:^|\n)Q\d+\s*[–—-]\s*[A-Z_]+/um',
        'Competenza: MECCANICA_' => '/Competenza:\s*MECCANICA_/ui',
        'Codice competenza:' => '/Codice\s*competenza:/ui',
        'MECCANICA_XX_YY (anywhere)' => '/MECCANICA_[A-Z0-9]+_[A-Z0-9]+/i',
    ];

    foreach ($patterns as $name => $pattern) {
        $found = (bool) preg_match($pattern, $text);
        echo "  $name: " . ($found ? "SI" : "NO") . "\n";
    }

    // Cerca primo pattern di domanda
    echo "\nPRIMO PATTERN DOMANDA TROVATO:\n";
    if (preg_match('/(?:^|\n)(\d+\.\s*MECC_[A-Z0-9_]+_Q\d+)\s*$/um', $text, $m)) {
        echo "  Tipo: Numerato (1. MECC_XXX_Q01)\n";
        echo "  Match: " . $m[1] . "\n";
    } elseif (preg_match('/(?:^|\n)(MECC_[A-Z]+_Q\d+)\s*$/um', $text, $m)) {
        echo "  Tipo: Solo ID (MECC_XXX_Q01)\n";
        echo "  Match: " . $m[1] . "\n";
    } elseif (preg_match('/(?:^|\n)(Q\d+)\s*[–—-]\s*([A-Z_]+_[A-Z0-9_]+)/um', $text, $m)) {
        echo "  Tipo: Q## – COMP\n";
        echo "  Match: " . $m[1] . " – " . $m[2] . "\n";
    } elseif (preg_match('/(?:^|\n)(Q\d+)\s*$/um', $text, $m)) {
        echo "  Tipo: Q## semplice\n";
        echo "  Match: " . $m[1] . "\n";
    } else {
        echo "  Tipo: NON TROVATO\n";
    }

    echo "\n\n";
}

echo "=== ANALISI COMPLETATA ===\n";
