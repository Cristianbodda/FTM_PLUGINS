<?php
/**
 * Script per verificare che word_parser.php sia aggiornato
 * 
 * COME USARE:
 * 1. Carica questo file in /local/competencyxmlimport/
 * 2. Vai a: tuosito.com/local/competencyxmlimport/verifica_installazione.php
 * 3. Leggi il risultato
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h1>🔍 Verifica Installazione word_parser.php</h1>";

$filepath = __DIR__ . '/classes/word_parser.php';

if (!file_exists($filepath)) {
    echo "<p style='color:red;'>❌ File word_parser.php NON TROVATO!</p>";
    die();
}

$content = file_get_contents($filepath);

// Verifica 1: Pattern AUT_BASE_Q
if (strpos($content, '[A-Z_]+_)?Q') !== false) {
    echo "<p style='color:green;'>✅ Pattern domande AUT_BASE_Q: PRESENTE</p>";
} else {
    echo "<p style='color:red;'>❌ Pattern domande AUT_BASE_Q: MANCANTE - Aggiorna il file!</p>";
}

// Verifica 2: Pattern Competenza collegata
if (strpos($content, 'Competenza\s+collegata') !== false) {
    echo "<p style='color:green;'>✅ Pattern 'Competenza collegata': PRESENTE</p>";
} else {
    echo "<p style='color:red;'>❌ Pattern 'Competenza collegata': MANCANTE - Aggiorna il file!</p>";
}

// Verifica 3: Data modifica
$mtime = filemtime($filepath);
echo "<p>📅 Ultima modifica: " . date('Y-m-d H:i:s', $mtime) . "</p>";

// Verifica 4: Dimensione file
$size = filesize($filepath);
echo "<p>📊 Dimensione: " . number_format($size) . " bytes</p>";

// Test rapido
echo "<h2>🧪 Test Rapido Pattern</h2>";

$test_text = "AUT_BASE_Q01\nDomanda di test\nCompetenza collegata: AUTOMOBILE_MR_A1";
$pattern = '/(?:^|\n)\s*(?:\*\*)?(?:[A-Z_]+_)?Q(\d+)(?:\s*\([^)]+\))?(?:\*\*)?\s*(?:\n|$)/i';

if (preg_match($pattern, $test_text, $m)) {
    echo "<p style='color:green;'>✅ Pattern domande funziona! Trovato: Q" . $m[1] . "</p>";
} else {
    echo "<p style='color:red;'>❌ Pattern domande NON funziona!</p>";
}

$comp_pattern = '/Competenza\s+collegata:\s*(\S+)/i';
if (preg_match($comp_pattern, $test_text, $m)) {
    echo "<p style='color:green;'>✅ Pattern competenze funziona! Trovato: " . $m[1] . "</p>";
} else {
    echo "<p style='color:red;'>❌ Pattern competenze NON funziona!</p>";
}

echo "<hr><p><strong>Se vedi errori ❌, devi:</strong></p>";
echo "<ol>";
echo "<li>Scaricare il file word_parser.php dal pacchetto</li>";
echo "<li>Caricarlo in /local/competencyxmlimport/classes/</li>";
echo "<li>Svuotare la cache Moodle</li>";
echo "<li>Ricaricare questa pagina</li>";
echo "</ol>";
