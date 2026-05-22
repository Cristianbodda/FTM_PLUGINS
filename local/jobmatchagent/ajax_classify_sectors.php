<?php
/**
 * AJAX endpoint — classificazione settori aziende con GPT.
 *
 * Azioni supportate:
 *   classify_batch — prende batch di id, chiama GPT, aggiorna DB
 *   classify_one   — classifica singola azienda (id + nome + settore_raw in POST)
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/company_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {

        // ------------------------------------------------------------------ //
        case 'classify_one':
            $id          = required_param('id', PARAM_INT);
            $nome        = optional_param('nome', '', PARAM_TEXT);
            $settore_raw = optional_param('settore_raw', '', PARAM_TEXT);

            if ($id <= 0) {
                throw new Exception('ID azienda non valido.');
            }

            // Se nome non passato, carica dal DB.
            if (empty($nome)) {
                $company = \local_jobmatchagent\company_manager::get_company($id);
                if (!$company) {
                    throw new Exception('Azienda non trovata.');
                }
                $nome        = $company->nome;
                $settore_raw = $company->settore_raw ?? '';
            }

            $classified = classify_single_with_ai($nome, $settore_raw);

            // Aggiorna DB.
            \local_jobmatchagent\company_manager::save_company([
                'id'         => $id,
                'settore_ftm' => $classified,
                'status'     => 'active',
            ]);

            echo json_encode([
                'success' => true,
                'data'    => ['id' => $id, 'settore_ftm' => $classified],
                'message' => 'Classificato come ' . $classified,
            ]);
            break;

        // ------------------------------------------------------------------ //
        case 'classify_batch':
            $ids_json = required_param('ids', PARAM_RAW);
            $ids = json_decode($ids_json, true);
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Nessun ID fornito per il batch.');
            }

            // Valida IDs.
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) { return $id > 0; });
            $ids = array_values($ids);

            if (empty($ids)) {
                throw new Exception('ID non validi nel batch.');
            }

            // Carica aziende da DB.
            global $DB;
            list($in_sql, $in_params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
            $companies = $DB->get_records_sql(
                "SELECT id, nome, settore_raw FROM {local_jobmatch_ticino_companies} WHERE id $in_sql",
                $in_params
            );

            if (empty($companies)) {
                throw new Exception('Nessuna azienda trovata per gli ID specificati.');
            }

            $results = classify_batch_with_ai(array_values($companies));

            // Aggiorna DB per ciascuna classificata.
            foreach ($results as $res) {
                if (!empty($res['settore_ftm']) && $res['settore_ftm'] !== 'ERRORE') {
                    \local_jobmatchagent\company_manager::save_company([
                        'id'          => $res['id'],
                        'settore_ftm' => $res['settore_ftm'],
                        'status'      => 'active',
                    ]);
                }
            }

            echo json_encode([
                'success' => true,
                'data'    => ['results' => $results],
                'message' => count($results) . ' aziende processate.',
            ]);
            break;

        default:
            throw new Exception('Azione non riconosciuta: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();

// ============================================================================
// Helpers
// ============================================================================

/**
 * Classifica una singola azienda tramite GPT.
 *
 * @param string $nome        Nome azienda
 * @param string $settore_raw Descrizione attivita (eventualmente vuota)
 * @return string Settore FTM (AUTOMOBILE|AUTOMAZIONE|CHIMFARM|ELETTRICITA|LOGISTICA|MECCANICA|METALCOSTRUZIONE|ALTRO)
 */
function classify_single_with_ai(string $nome, string $settore_raw): string {
    $api_key = get_config('local_ftm_jobsearch', 'openai_apikey');
    if (empty($api_key)) {
        $api_key = get_config('local_jobaida', 'openai_apikey');
    }
    if (empty($api_key)) {
        throw new Exception('API key OpenAI non configurata. Impostarla in local_ftm_jobsearch o local_jobaida.');
    }

    $model = get_config('local_jobmatchagent', 'openai_model') ?: 'gpt-4o-mini';

    $prompt = build_classify_prompt_single($nome, $settore_raw);

    $response = call_openai_api($api_key, $model, $prompt);

    return parse_single_sector($response);
}

/**
 * Classifica un batch di aziende tramite GPT (max 20 per chiamata).
 *
 * @param array $companies Array di stdClass con id, nome, settore_raw
 * @return array Array di ['id' => int, 'settore_ftm' => string]
 */
function classify_batch_with_ai(array $companies): array {
    $api_key = get_config('local_ftm_jobsearch', 'openai_apikey');
    if (empty($api_key)) {
        $api_key = get_config('local_jobaida', 'openai_apikey');
    }
    if (empty($api_key)) {
        throw new Exception('API key OpenAI non configurata.');
    }

    $model = get_config('local_jobmatchagent', 'openai_model') ?: 'gpt-4o-mini';

    // Costruisce lista numerata per il prompt.
    $lines = [];
    foreach ($companies as $idx => $c) {
        $desc = trim($c->settore_raw ?? '');
        $lines[] = ($idx + 1) . '. Nome: ' . $c->nome . ($desc ? ' | Attivita: ' . $desc : '');
    }

    $prompt = 'Sei un esperto di industria manifatturiera svizzera (Ticino).
Classifica ciascuna delle seguenti aziende in UNO dei settori FTM:
AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, MECCANICA, METALCOSTRUZIONE, ALTRO

Rispondi SOLO con un oggetto JSON con questa struttura esatta:
{"results": [{"n": 1, "settore": "MECCANICA"}, {"n": 2, "settore": "LOGISTICA"}, ...]}

Regole:
- AUTOMOBILE = officine, carrozzerie, concessionarie, manutenzione veicoli
- AUTOMAZIONE = sistemi automatici, PLC, robotica, meccatronica, elettronica industriale
- CHIMFARM = chimica, farmaceutica, laboratori, materie plastiche
- ELETTRICITA = installatori elettrici, quadri elettrici, energia, fotovoltaico
- LOGISTICA = trasporti, spedizioni, magazzini, corrieri
- MECCANICA = officine meccaniche, tornitori, CNC, stampi, utensili
- METALCOSTRUZIONE = saldatura, carpenteria metallica, costruzioni metalliche, serramentisti
- ALTRO = settori non manifatturieri (commercio, servizi, edilizia, alimentare, ecc.)

Aziende da classificare:
' . implode("\n", $lines);

    $response = call_openai_api($api_key, $model, $prompt);

    // Parse JSON risposta.
    $json = extract_json_from_response($response);
    if (!$json || empty($json['results'])) {
        // Fallback: ritorna ALTRO per tutte.
        return array_map(function($c) {
            return ['id' => (int)$c->id, 'settore_ftm' => 'ALTRO'];
        }, $companies);
    }

    // Mappa n -> settore.
    $map = [];
    foreach ($json['results'] as $r) {
        if (isset($r['n']) && isset($r['settore'])) {
            $map[(int)$r['n']] = validate_settore($r['settore']);
        }
    }

    $results = [];
    foreach ($companies as $idx => $c) {
        $n = $idx + 1;
        $results[] = [
            'id'          => (int)$c->id,
            'settore_ftm' => $map[$n] ?? 'ALTRO',
        ];
    }
    return $results;
}

/**
 * Build prompt per classificazione singola.
 */
function build_classify_prompt_single(string $nome, string $settore_raw): string {
    $desc = $settore_raw ? ' Attivita descritta: ' . $settore_raw : '';
    return 'Sei un esperto di industria manifatturiera svizzera (Ticino).
Classifica l\'azienda "' . $nome . '"' . $desc . ' in UNO dei settori FTM:
AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, MECCANICA, METALCOSTRUZIONE, ALTRO

Rispondi SOLO con la parola del settore, nient\'altro.

Regole:
- AUTOMOBILE = officine, carrozzerie, concessionarie, manutenzione veicoli
- AUTOMAZIONE = sistemi automatici, PLC, robotica, meccatronica, elettronica industriale
- CHIMFARM = chimica, farmaceutica, laboratori, materie plastiche
- ELETTRICITA = installatori elettrici, quadri elettrici, energia, fotovoltaico
- LOGISTICA = trasporti, spedizioni, magazzini, corrieri
- MECCANICA = officine meccaniche, tornitori, CNC, stampi, utensili
- METALCOSTRUZIONE = saldatura, carpenteria metallica, costruzioni metalliche, serramentisti
- ALTRO = settori non manifatturieri (commercio, servizi, edilizia, alimentare, ecc.)';
}

/**
 * Parse risposta singola: estrae il settore dalla risposta AI.
 */
function parse_single_sector(string $response): string {
    $clean = strtoupper(trim($response));
    $valid = ['AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITA', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE', 'ALTRO'];
    foreach ($valid as $v) {
        if (strpos($clean, $v) !== false) {
            return $v;
        }
    }
    return 'ALTRO';
}

/**
 * Valida e normalizza un settore FTM.
 */
function validate_settore(string $raw): string {
    $valid = ['AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITA', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE', 'ALTRO'];
    $up = strtoupper(trim($raw));
    return in_array($up, $valid, true) ? $up : 'ALTRO';
}

/**
 * Chiama l'API OpenAI Chat Completions.
 *
 * @param string $api_key
 * @param string $model
 * @param string $user_prompt
 * @return string Testo risposta
 * @throws Exception on HTTP error
 */
function call_openai_api(string $api_key, string $model, string $user_prompt): string {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => [
            ['role' => 'user', 'content' => $user_prompt],
        ],
        'temperature' => 0.1,
        'max_tokens'  => 500,
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"
                       . "Authorization: Bearer $api_key\r\n",
            'content' => $payload,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ];

    $ctx      = stream_context_create($opts);
    $raw      = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $ctx);
    $headers  = $http_response_header ?? [];
    $status   = 200;

    foreach ($headers as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
            $status = (int)$m[1];
        }
    }

    if ($raw === false || $status >= 400) {
        $err = $raw ? (json_decode($raw, true)['error']['message'] ?? $raw) : 'Nessuna risposta dal server AI';
        throw new Exception('Errore API OpenAI (HTTP ' . $status . '): ' . $err);
    }

    $decoded = json_decode($raw, true);
    $text    = $decoded['choices'][0]['message']['content'] ?? '';
    if (trim($text) === '') {
        throw new Exception('Risposta AI vuota. Riprova.');
    }
    return $text;
}

/**
 * Estrae il primo oggetto JSON valido da una stringa di testo.
 *
 * @param string $text
 * @return array|null
 */
function extract_json_from_response(string $text): ?array {
    // Prova parsing diretto.
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    // Cerca blocco JSON tra ``` ```.
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
        $decoded = json_decode($m[1], true);
        if (is_array($decoded)) return $decoded;
    }
    // Cerca primo { ... } nel testo.
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}
