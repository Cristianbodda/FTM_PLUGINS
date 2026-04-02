<?php
/**
 * Extract text from uploaded PDF/Word/TXT files.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_FILES['file'])) {
        throw new Exception('Nessun file caricato');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore nel caricamento del file');
    }

    $filename = clean_param($file['name'], PARAM_FILE);
    $tmppath = $file['tmp_name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $text = '';

    switch ($ext) {
        case 'txt':
            $text = file_get_contents($tmppath);
            // Handle BOM.
            $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
            break;

        case 'docx':
            $text = extract_docx_text($tmppath);
            break;

        case 'doc':
            // Old .doc format - try basic extraction.
            $text = extract_doc_text($tmppath);
            break;

        case 'pdf':
            $text = extract_pdf_text($tmppath);
            break;

        default:
            throw new Exception('Formato non supportato: ' . $ext);
    }

    $text = trim($text);
    if (empty($text)) {
        throw new Exception('Impossibile estrarre testo dal file. Il file potrebbe essere un\'immagine o protetto. Prova a copiare il testo manualmente.');
    }

    echo json_encode([
        'success' => true,
        'text' => $text,
        'filename' => $filename,
        'chars' => mb_strlen($text),
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();

/**
 * Extract text from a .docx file (ZIP with XML inside).
 */
function extract_docx_text($filepath) {
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new Exception('Impossibile aprire il file Word');
    }

    $content = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($content === false) {
        throw new Exception('File Word non valido');
    }

    // Remove XML tags, keep text content.
    // Replace paragraph and line break tags with newlines.
    $content = str_replace('</w:p>', "\n", $content);
    $content = str_replace('<w:br/>', "\n", $content);
    $content = str_replace('</w:tr>', "\n", $content); // Table rows.
    $content = str_replace('</w:tc>', "\t", $content); // Table cells.

    // Strip all remaining XML tags.
    $text = strip_tags($content);

    // Clean up whitespace.
    $text = preg_replace('/\t+/', "\t", $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    return $text;
}

/**
 * Extract text from an old .doc file (binary format).
 * Basic extraction - may not work for all .doc files.
 */
function extract_doc_text($filepath) {
    $content = file_get_contents($filepath);

    // Try to find text between common markers in binary .doc.
    // This is a rough approach but works for many files.
    $text = '';

    // Method 1: Look for readable ASCII text.
    $len = strlen($content);
    $intext = false;
    $buffer = '';

    for ($i = 0; $i < $len; $i++) {
        $char = ord($content[$i]);
        // Printable ASCII or common extended chars.
        if (($char >= 32 && $char <= 126) || $char === 10 || $char === 13 || $char >= 192) {
            $buffer .= $content[$i];
            $intext = true;
        } else {
            if ($intext && strlen($buffer) > 10) {
                $text .= $buffer . "\n";
            }
            $buffer = '';
            $intext = false;
        }
    }
    if ($intext && strlen($buffer) > 10) {
        $text .= $buffer;
    }

    // Clean up.
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    if (empty($text)) {
        throw new Exception('Impossibile estrarre testo dal file .doc. Prova a salvarlo come .docx e ricaricarlo.');
    }

    return $text;
}

/**
 * Extract text from a PDF file.
 * Tries pdftotext command first, then falls back to basic PHP extraction.
 */
function extract_pdf_text($filepath) {
    // Method 1: Try pdftotext (poppler-utils) if available.
    $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
    if (!empty($pdftotext) && is_executable($pdftotext)) {
        $output = [];
        $retval = 0;
        exec(escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($filepath) . ' -', $output, $retval);
        if ($retval === 0 && !empty($output)) {
            return implode("\n", $output);
        }
    }

    // Method 2: Basic PHP PDF text extraction.
    $content = file_get_contents($filepath);

    $text = '';

    // Look for text between BT and ET markers (PDF text objects).
    if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
        foreach ($matches[1] as $block) {
            // Extract text from Tj and TJ operators.
            if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $block, $tjmatches)) {
                $text .= implode('', $tjmatches[1]) . "\n";
            }
            if (preg_match_all('/\[([^\]]*)\]\s*TJ/s', $block, $tjmatches)) {
                foreach ($tjmatches[1] as $arr) {
                    if (preg_match_all('/\(([^)]*)\)/', $arr, $parts)) {
                        $text .= implode('', $parts[1]);
                    }
                }
                $text .= "\n";
            }
        }
    }

    // Decode common PDF escape sequences.
    $text = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r'], ['(', ')', '\\', "\n", "\r"], $text);

    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    if (empty($text)) {
        throw new Exception('Impossibile estrarre testo dal PDF. Il file potrebbe contenere solo immagini. Prova ad aprirlo e copiare il testo manualmente (Ctrl+A, Ctrl+C).');
    }

    return $text;
}
