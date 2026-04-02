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
            $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
            break;

        case 'docx':
            $text = extract_docx_text($tmppath);
            break;

        case 'doc':
            throw new Exception('Il formato .doc (vecchio Word) non e supportato per l\'estrazione automatica. Salva il file come .docx oppure apri il file e copia il testo manualmente (Ctrl+A, Ctrl+C).');

        case 'pdf':
            $text = extract_pdf_text($tmppath);
            break;

        default:
            throw new Exception('Formato non supportato: ' . $ext . '. Usa DOCX, PDF o TXT.');
    }

    $text = trim($text);
    if (empty($text)) {
        throw new Exception('Impossibile estrarre testo dal file. Il file potrebbe contenere solo immagini o essere protetto.');
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
 * Extract text from a .docx file using proper XML parsing.
 */
function extract_docx_text($filepath) {
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new Exception('Impossibile aprire il file Word');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        throw new Exception('File Word non valido');
    }

    // Use DOMDocument for proper XML parsing.
    $dom = new DOMDocument();
    $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);

    $text = '';
    $paragraphs = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');

    foreach ($paragraphs as $p) {
        $paratext = '';
        // Get all text runs in this paragraph.
        $runs = $p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
        foreach ($runs as $t) {
            $paratext .= $t->textContent;
        }
        // Check for tab characters.
        $tabs = $p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tab');
        if ($tabs->length > 0 && !empty($paratext)) {
            $paratext = str_replace("\t", '', $paratext);
        }
        $text .= $paratext . "\n";
    }

    // Clean up excessive blank lines.
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}

/**
 * Extract text from a PDF file.
 * Uses pdftotext if available, otherwise returns error with instructions.
 */
function extract_pdf_text($filepath) {
    // Method 1: Try pdftotext (poppler-utils).
    $pdftotext = '';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
    }

    if (!empty($pdftotext) && is_executable($pdftotext)) {
        $output = [];
        $retval = 0;
        exec(escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($filepath) . ' -', $output, $retval);
        if ($retval === 0 && !empty($output)) {
            $text = implode("\n", $output);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            return trim($text);
        }
    }

    // Method 2: Try reading PDF streams for text content.
    $content = file_get_contents($filepath);
    $text = '';

    // Decompress FlateDecode streams and extract text.
    if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streams)) {
        foreach ($streams[1] as $stream) {
            // Try to decompress.
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if ($decoded !== false) {
                // Extract text operators.
                if (preg_match_all('/\(([^)]+)\)\s*Tj/s', $decoded, $tj)) {
                    $text .= implode(' ', $tj[1]) . "\n";
                }
                if (preg_match_all('/\[([^\]]+)\]\s*TJ/s', $decoded, $tjarr)) {
                    foreach ($tjarr[1] as $arr) {
                        if (preg_match_all('/\(([^)]*)\)/', $arr, $parts)) {
                            $text .= implode('', $parts[1]);
                        }
                    }
                    $text .= "\n";
                }
            }
        }
    }

    // Decode PDF escape sequences.
    $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    if (empty($text) || mb_strlen($text) < 20) {
        throw new Exception('Il PDF non contiene testo estraibile (potrebbe essere un\'immagine scansionata). Apri il file PDF, seleziona tutto il testo (Ctrl+A), copialo (Ctrl+C) e incollalo nel campo di testo (Ctrl+V).');
    }

    return $text;
}
