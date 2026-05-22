<?php
/**
 * AJAX endpoint: Parse uploaded proof files with OpenAI Vision to extract URC search entries.
 *
 * Supports JPG/PNG via vision API; text-based PDFs via text extraction + chat API.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    // =========================================================================
    // ACTION: parse — read files and call AI
    // =========================================================================
    if ($action === 'parse') {
        $enrollmentid = required_param('enrollmentid', PARAM_INT);

        $enrollment = \local_ftm_sip\sip_manager::get_enrollment_by_id($enrollmentid);
        if (!$enrollment) {
            throw new Exception('Enrollment non trovato.');
        }
        $date_start = (int)($enrollment->date_start ?? 0);

        $apikey = get_config('local_jobaida', 'openai_apikey');
        if (empty($apikey)) {
            throw new Exception('API key OpenAI non configurata. Vai in Amministrazione > Plugin > JobAIDA > impostazioni.');
        }
        $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';

        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $context->id, 'local_ftm_sip', 'search_proofs', $enrollmentid,
            'filepath ASC, filename ASC', false
        );

        if (empty($files)) {
            throw new Exception('Nessun documento caricato per questo studente. Carica prima i PDF/JPG nella sezione "Documenti caricati".');
        }

        $all_entries = [];
        $errors      = [];

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $res = sip_parse_image_file($file, $apikey, $model);
            } elseif ($ext === 'pdf') {
                $res = sip_parse_pdf_file($file, $apikey, $model, $filename);
            } else {
                $errors[] = "$filename: formato non supportato (usa JPG, PNG o PDF).";
                continue;
            }

            if (!empty($res['entries'])) {
                foreach ($res['entries'] as $entry) {
                    $entry['source_file'] = $filename;
                    // Auto-calculate sip_week from entry_date and enrollment date_start.
                    $ts = !empty($entry['entry_date']) ? strtotime($entry['entry_date']) : 0;
                    if ($ts && $date_start > 0) {
                        $week = local_ftm_sip_calculate_week($date_start, $ts);
                        $entry['sip_week'] = max(1, min(10, $week ?: 1));
                    } else {
                        $entry['sip_week'] = 1;
                    }
                    $all_entries[] = $entry;
                }
            } elseif (!empty($res['error'])) {
                $errors[] = "$filename: " . $res['error'];
            }
        }

        echo json_encode([
            'success' => true,
            'data'    => ['entries' => $all_entries, 'errors' => $errors, 'file_count' => count($files)],
            'message' => count($all_entries) . ' ricerche trovate in ' . count($files) . ' documenti.',
        ]);

    // =========================================================================
    // ACTION: import — save extracted entries to DB
    // =========================================================================
    } elseif ($action === 'import') {
        $enrollmentid  = required_param('enrollmentid', PARAM_INT);
        $entries_json  = required_param('entries', PARAM_RAW);
        $entries       = json_decode($entries_json, true);

        if (!is_array($entries) || empty($entries)) {
            throw new Exception('Nessuna voce da importare.');
        }

        $imported = 0;
        foreach ($entries as $e) {
            $company = trim($e['company_name'] ?? '');
            if ($company === '') {
                continue; // Skip rows without company name.
            }
            $sip_week = isset($e['sip_week']) ? max(1, min(10, (int)$e['sip_week'])) : 1;
            $data = [
                'entry_date'          => !empty($e['entry_date']) ? (int)strtotime($e['entry_date']) : 0,
                'company_name'        => $company,
                'company_address'     => trim($e['company_address'] ?? ''),
                'company_email'       => trim($e['company_email'] ?? ''),
                'position'            => trim($e['position'] ?? ''),
                'urc_assigned'        => !empty($e['urc_assigned']) ? 1 : 0,
                'occupation_fulltime' => !empty($e['occupation_fulltime']) ? 1 : 0,
                'occupation_parttime' => !empty($e['occupation_parttime']) ? 1 : 0,
                'method_letter'       => !empty($e['method_letter']) ? 1 : 0,
                'method_person'       => !empty($e['method_person']) ? 1 : 0,
                'method_phone'        => !empty($e['method_phone']) ? 1 : 0,
                'result'              => in_array($e['result'] ?? '', ['positive', 'negative', 'pending'])
                                         ? $e['result'] : 'pending',
                'notes'               => trim($e['notes'] ?? ''),
            ];
            \local_ftm_sip\sip_manager::create_search_entry(
                $enrollmentid, 'mandatory_searches', $sip_week, $data, $USER->id
            );
            $imported++;
        }

        echo json_encode([
            'success' => true,
            'data'    => ['imported' => $imported],
            'message' => "$imported ricerche importate nel Foglio URC.",
        ]);

    } else {
        throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();

// ============================================================================
// Helper: prompt
// ============================================================================

function sip_urc_prompt() {
    return 'Analizza questo documento URC svizzero "Prova degli sforzi personali intrapresi per trovare lavoro" '
        . '(foglio Job-Room o modulo cartaceo PCI). Estrai TUTTE le righe della tabella ricerche.'
        . "\n\nPer ogni riga restituisci un oggetto JSON con questi campi:\n"
        . '- entry_date: data in formato YYYY-MM-DD (o null se non leggibile)' . "\n"
        . '- company_name: nome ditta/azienda/contatto (stringa)' . "\n"
        . '- company_address: indirizzo completo (stringa o "")' . "\n"
        . '- company_email: email (stringa o "")' . "\n"
        . '- position: tipo impiego o ruolo cercato (stringa o "")' . "\n"
        . '- urc_assigned: 1 se X nella colonna URC, altrimenti 0' . "\n"
        . '- occupation_fulltime: 1 se X in TP/Tempo pieno/Full, altrimenti 0' . "\n"
        . '- occupation_parttime: 1 se X in Parz/Part time, altrimenti 0' . "\n"
        . '- method_letter: 1 se X in Let/Lettera, altrimenti 0' . "\n"
        . '- method_person: 1 se X in Pers/Di persona, altrimenti 0' . "\n"
        . '- method_phone: 1 se X in Tel/Telefono, altrimenti 0' . "\n"
        . '- result: "positive" se assunto/risposta positiva, "negative" se rifiuto, "pending" altrimenti' . "\n"
        . '- notes: note aggiuntive (stringa o "")' . "\n\n"
        . 'Rispondi SOLO con un array JSON valido, senza testo aggiuntivo. '
        . 'Esempio minimo: [{"entry_date":"2026-03-15","company_name":"Fiat SA","company_address":"","company_email":"",'
        . '"position":"","urc_assigned":0,"occupation_fulltime":1,"occupation_parttime":0,'
        . '"method_letter":1,"method_person":0,"method_phone":0,"result":"pending","notes":""}]';
}

// ============================================================================
// Helper: call OpenAI Vision (images)
// ============================================================================

function sip_call_openai_vision($apikey, $model, $base64, $mime) {
    $payload = [
        'model'      => $model,
        'max_tokens' => 3000,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                ['type' => 'text', 'text' => sip_urc_prompt()],
                ['type' => 'image_url', 'image_url' => [
                    'url'    => "data:{$mime};base64,{$base64}",
                    'detail' => 'high',
                ]],
            ],
        ]],
    ];
    return sip_openai_request($apikey, $payload);
}

// ============================================================================
// Helper: call OpenAI Chat (text PDFs)
// ============================================================================

function sip_call_openai_text($apikey, $model, $text_content) {
    // Remove null bytes and control characters that break JSON encoding.
    $text_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text_content);
    // Replace invalid UTF-8 sequences with a safe substitute.
    $text_content = mb_convert_encoding($text_content, 'UTF-8', 'UTF-8');
    // Cap at 12000 chars — more than enough for a Job-Room page.
    $text_content = mb_substr($text_content, 0, 12000);

    $payload = [
        'model'      => $model,
        'max_tokens' => 3000,
        'messages'   => [[
            'role'    => 'user',
            'content' => sip_urc_prompt() . "\n\nContenuto testo del PDF:\n" . $text_content,
        ]],
    ];
    return sip_openai_request($apikey, $payload);
}

// ============================================================================
// Helper: HTTP request to OpenAI
// ============================================================================

function sip_openai_request($apikey, $payload) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => 'Errore di rete: ' . $err];
    }
    $data = json_decode($response, true);
    if (!empty($data['error'])) {
        return ['error' => 'OpenAI: ' . ($data['error']['message'] ?? 'errore sconosciuto')];
    }
    $text = $data['choices'][0]['message']['content'] ?? null;
    if (!$text) {
        return ['error' => 'Risposta AI vuota'];
    }
    // Strip markdown code fences if present.
    $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/i', '', $text);
    $entries = json_decode(trim($text), true);
    if (!is_array($entries)) {
        return ['error' => 'Risposta AI non parsabile: ' . substr($text, 0, 300)];
    }
    return ['entries' => $entries];
}

// ============================================================================
// Helper: parse image file (JPG/PNG)
// ============================================================================

function sip_parse_image_file($file, $apikey, $model) {
    $content = $file->get_content();
    if (empty($content)) {
        return ['error' => 'File vuoto'];
    }
    $ext  = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    return sip_call_openai_vision($apikey, $model, base64_encode($content), $mime);
}

// ============================================================================
// Helper: parse PDF file (text extraction + chat)
// ============================================================================

function sip_parse_pdf_file($file, $apikey, $model, $filename) {
    $content = $file->get_content();
    if (empty($content)) {
        return ['error' => 'File vuoto'];
    }
    $text = sip_extract_pdf_text($content);
    if (strlen($text) < 20) {
        return ['error' => "$filename: impossibile estrarre testo dal PDF. Prova a esportarlo di nuovo da Job-Room o convertilo in JPG/PNG."];
    }
    return sip_call_openai_text($apikey, $model, $text);
}

// ============================================================================
// Helper: extract readable text from binary PDF.
// Handles FlateDecode (zlib) compressed streams — standard in modern PDFs
// including Job-Room exports. Falls back to uncompressed scan for old PDFs.
// ============================================================================

function sip_extract_pdf_text($pdf_binary) {
    $text = '';

    // --- Pass 1: decompress FlateDecode streams (modern PDFs) ---
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf_binary, $streams);
    foreach ($streams[1] as $stream_data) {
        // gzinflate = raw deflate (most common FlateDecode).
        $dec = @gzinflate($stream_data);
        if ($dec === false) {
            // gzuncompress = zlib with header (less common).
            $dec = @gzuncompress($stream_data);
        }
        if ($dec !== false && strlen($dec) > 5) {
            $text .= sip_pdf_stream_to_text($dec);
        }
    }

    // --- Pass 2: fallback for uncompressed PDFs ---
    if (strlen(trim($text)) < 20) {
        $cleaned = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $pdf_binary);
        preg_match_all('/\(([^)]{2,200})\)/', $cleaned, $m);
        if (!empty($m[1])) {
            foreach ($m[1] as $chunk) {
                $chunk = trim($chunk);
                if (strlen($chunk) > 2 && preg_match('/[a-zA-Z0-9]{2}/', $chunk)) {
                    $text .= $chunk . "\n";
                }
            }
        }
    }

    return trim($text);
}

// ============================================================================
// Helper: extract text strings from a decompressed PDF content stream.
// Handles Tj (single string), TJ (array), ' and " operators.
// ============================================================================

function sip_pdf_stream_to_text($stream) {
    $text = '';

    // (string) Tj — single string show operator.
    preg_match_all('/\(([^)\\\\]{0,400}(?:\\\\.[^)\\\\]{0,400})*)\)\s*Tj/s', $stream, $m1);
    foreach ($m1[1] as $chunk) {
        $chunk = sip_pdf_unescape($chunk);
        if (strlen(trim($chunk)) > 0) {
            $text .= $chunk . ' ';
        }
    }

    // [(string1)(string2)...] TJ — array string show operator.
    preg_match_all('/\[([^\]]+)\]\s*TJ/s', $stream, $m2);
    foreach ($m2[1] as $arr) {
        preg_match_all('/\(([^)]*)\)/', $arr, $sub);
        foreach ($sub[1] as $chunk) {
            $text .= sip_pdf_unescape($chunk);
        }
        $text .= ' ';
    }

    // (string) ' and (string) " — move-to-next-line-and-show operators.
    preg_match_all('/\(([^)\\\\]{0,400}(?:\\\\.[^)\\\\]{0,400})*)\)\s*[\'"]/', $stream, $m3);
    foreach ($m3[1] as $chunk) {
        $chunk = sip_pdf_unescape($chunk);
        if (strlen(trim($chunk)) > 0) {
            $text .= $chunk . "\n";
        }
    }

    return $text;
}

// ============================================================================
// Helper: unescape PDF string escape sequences.
// ============================================================================

function sip_pdf_unescape($str) {
    // PDF escape sequences → actual characters.
    $str = str_replace(
        ['\\n', '\\r', '\\t', '\\b', '\\f', '\\\\', '\\(', '\\)'],
        ["\n",  "\r",  "\t",  "\x08", "\x0C", '\\',  '(',   ')'],
        $str
    );
    // Octal \ddd → character.
    $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
        return chr(octdec($m[1]));
    }, $str);
    return $str;
}
