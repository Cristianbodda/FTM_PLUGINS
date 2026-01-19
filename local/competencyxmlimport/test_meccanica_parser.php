<?php
/**
 * Script di test per il parser MECCANICA
 * Esegui da linea di comando: php test_meccanica_parser.php [percorso_file.docx]
 *
 * Oppure apri nel browser (richiede Moodle): /local/competencyxmlimport/test_meccanica_parser.php
 */

// Modalità CLI o Web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Moodle mode
    require_once(__DIR__ . '/../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());

    echo '<html><head><title>Test Parser MECCANICA</title>';
    echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;overflow-x:auto;}</style>';
    echo '</head><body>';
    echo '<h1>Test Parser MECCANICA</h1>';
}

// Includi il parser (senza Moodle se in CLI)
if ($is_cli) {
    // Minimal autoload per CLI
    spl_autoload_register(function ($class) {
        if (strpos($class, 'local_competencyxmlimport\\') === 0) {
            $class_name = str_replace('local_competencyxmlimport\\', '', $class);
            $file = __DIR__ . '/classes/' . $class_name . '.php';
            if (file_exists($file)) {
                require_once($file);
            }
        }
    });
    define('MOODLE_INTERNAL', true);
}

require_once(__DIR__ . '/classes/word_parser.php');

/**
 * Funzione per testare un file Word MECCANICA
 */
function test_meccanica_file($filepath, $is_cli = true) {
    $output = [];

    $output[] = $is_cli ? "\n=== TEST FILE: " . basename($filepath) . " ===" : "<h2>File: " . basename($filepath) . "</h2>";

    if (!file_exists($filepath)) {
        $output[] = $is_cli ? "ERRORE: File non trovato!" : "<p class='error'>ERRORE: File non trovato!</p>";
        return implode($is_cli ? "\n" : "", $output);
    }

    // Crea parser
    $parser = new \local_competencyxmlimport\word_parser('MECCANICA');

    // Parsa file
    $result = $parser->parse_file($filepath);

    // Output risultati
    $output[] = $is_cli ? "\nFormato rilevato: " . $result['format'] : "<p><strong>Formato rilevato:</strong> " . $result['format'] . "</p>";
    $output[] = $is_cli ? "Descrizione: " . $parser->get_format_description() : "<p><strong>Descrizione:</strong> " . $parser->get_format_description() . "</p>";
    $output[] = $is_cli ? "Totale domande: " . $result['total_questions'] : "<p><strong>Totale domande:</strong> " . $result['total_questions'] . "</p>";
    $output[] = $is_cli ? "Valide: " . $result['valid_count'] : "<p class='ok'><strong>Valide:</strong> " . $result['valid_count'] . "</p>";
    $output[] = $is_cli ? "Warning: " . $result['warning_count'] : "<p class='warning'><strong>Warning:</strong> " . $result['warning_count'] . "</p>";
    $output[] = $is_cli ? "Errori: " . $result['error_count'] : "<p class='error'><strong>Errori:</strong> " . $result['error_count'] . "</p>";

    // Verifica formato MECCANICA
    $expected_format = 'FORMATO_19_MECCANICA';
    if ($result['format'] === $expected_format) {
        $output[] = $is_cli ? "\n✓ FORMATO CORRETTO!" : "<p class='ok'>✓ FORMATO CORRETTO!</p>";
    } else {
        $output[] = $is_cli ? "\n✗ FORMATO ERRATO! Atteso: $expected_format" : "<p class='error'>✗ FORMATO ERRATO! Atteso: $expected_format</p>";
    }

    // Mostra prime 3 domande come esempio
    if (!empty($result['questions'])) {
        $output[] = $is_cli ? "\n--- Prime 3 domande ---" : "<h3>Prime 3 domande di esempio:</h3><pre>";

        $count = 0;
        foreach ($result['questions'] as $q) {
            if ($count >= 3) break;

            $status_icon = $q['status'] === 'ok' ? '✓' : ($q['status'] === 'warning' ? '⚠' : '✗');

            $output[] = sprintf("\n[%s] Q%s:", $status_icon, $q['num']);
            $output[] = "  Testo: " . substr($q['text'], 0, 80) . (strlen($q['text']) > 80 ? '...' : '');
            $output[] = "  Competenza: " . ($q['competency'] ?: 'NON TROVATA');
            $output[] = "  Risposta: " . ($q['correct_answer'] ?: 'NON TROVATA');
            $output[] = "  Risposte: " . count($q['answers']) . " (" . implode(', ', array_keys($q['answers'])) . ")";

            if (!empty($q['issues'])) {
                $output[] = "  Issues: " . implode('; ', $q['issues']);
            }

            $count++;
        }

        if (!$is_cli) {
            $output[] = "</pre>";
        }
    }

    // Mostra errori se presenti
    if (!empty($result['errors'])) {
        $output[] = $is_cli ? "\n--- ERRORI ---" : "<h3 class='error'>Errori:</h3><pre class='error'>";
        foreach ($result['errors'] as $err) {
            $output[] = "  - " . $err;
        }
        if (!$is_cli) $output[] = "</pre>";
    }

    // Riepilogo
    $success = $result['format'] === $expected_format && $result['total_questions'] > 0;
    $output[] = $is_cli ? "\n=== RISULTATO: " . ($success ? "OK" : "FALLITO") . " ===" :
                          "<h3 class='" . ($success ? "ok" : "error") . "'>RISULTATO: " . ($success ? "OK" : "FALLITO") . "</h3>";

    return implode($is_cli ? "\n" : "", $output);
}

/**
 * Crea un file Word di test MECCANICA
 */
function create_test_meccanica_docx($filepath) {
    // Contenuto di test nel formato MECCANICA
    $test_content = <<<'EOT'
1. Qual è la tolleranza dimensionale standard per lavorazioni di sgrossatura?

A) ±0.01 mm
B) ±0.1 mm
C) ±0.5 mm
D) ±1 mm

Risposta corretta: C
Codice competenza: MECCANICA_LMC_01

2. In un tornio parallelo, quale movimento compie il mandrino?

A) Movimento lineare
B) Movimento rotatorio
C) Movimento alternativo
D) Movimento oscillante

Risposta corretta: B
Codice competenza: MECCANICA_LMC_02

A1. Quale strumento si usa per misurare diametri interni con precisione?

A) Calibro a corsoio
B) Micrometro per interni
C) Comparatore
D) Squadra

Risposta corretta: B
Codice competenza: MECCANICA_MIS_01

B1. Nel disegno tecnico, cosa indica una linea tratteggiata?

A) Contorno visibile
B) Asse di simmetria
C) Contorno nascosto
D) Linea di quota

Risposta corretta: C
Codice competenza: MECCANICA_DT_01
EOT;

    // Crea XML per Word
    $document_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>';

    // Converti ogni riga in paragrafo
    $lines = explode("\n", $test_content);
    foreach ($lines as $line) {
        $line = htmlspecialchars(trim($line), ENT_XML1, 'UTF-8');
        if (!empty($line)) {
            $document_xml .= '<w:p><w:r><w:t>' . $line . '</w:t></w:r></w:p>';
        }
    }

    $document_xml .= '</w:body></w:document>';

    // Crea ZIP (docx)
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    // Content Types
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';

    // Relationships
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

    $zip->addFromString('[Content_Types].xml', $content_types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document_xml);

    $zip->close();
    return true;
}

// ========================
// MAIN
// ========================

if ($is_cli) {
    echo "\n========================================\n";
    echo "TEST PARSER MECCANICA - word_parser v4.0\n";
    echo "========================================\n";

    // Se passato un file come argomento, testa quello
    if (isset($argv[1]) && file_exists($argv[1])) {
        echo test_meccanica_file($argv[1], true);
    } else {
        // Crea e testa un file di esempio
        $test_file = __DIR__ . '/test_meccanica_temp.docx';

        echo "\nCreazione file di test MECCANICA...\n";

        if (create_test_meccanica_docx($test_file)) {
            echo "File creato: $test_file\n";
            echo test_meccanica_file($test_file, true);

            // Pulisci
            unlink($test_file);
            echo "\n\nFile di test eliminato.\n";
        } else {
            echo "ERRORE: Impossibile creare file di test!\n";
        }
    }

    echo "\n";
} else {
    // Web mode

    // Se upload file
    if (isset($_FILES['testfile']) && $_FILES['testfile']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['testfile']['tmp_name'];
        echo test_meccanica_file($tmp, false);
    } else {
        // Mostra form upload
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<p>Carica un file Word MECCANICA per testare il parser:</p>';
        echo '<input type="file" name="testfile" accept=".docx">';
        echo '<button type="submit">Testa File</button>';
        echo '</form>';

        echo '<hr>';
        echo '<h2>Test con file generato</h2>';

        // Test con file generato
        $test_file = __DIR__ . '/test_meccanica_temp_' . time() . '.docx';

        if (create_test_meccanica_docx($test_file)) {
            echo test_meccanica_file($test_file, false);
            unlink($test_file);
        } else {
            echo '<p class="error">Errore nella creazione del file di test</p>';
        }
    }

    echo '</body></html>';
}
