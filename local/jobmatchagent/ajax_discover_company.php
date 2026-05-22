<?php
/**
 * AJAX endpoint — analisi sito web aziendale con AI.
 *
 * Step:
 *   1. Fetch HTML pagina principale (con User-Agent Chrome)
 *   2. Strip script/style, estrai testo pulito
 *   3. Identifica link interni rilevanti (chi-siamo, prodotti, about, ecc.)
 *   4. Fetch 1-2 sub-pagine
 *   5. Combina testi (max 8000 chars)
 *   6. GPT analisi → JSON strutturato + domande
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

header('Content-Type: application/json; charset=utf-8');

// Aumenta timeout: il fetch del sito puo' impiegare diversi secondi.
set_time_limit(120);

global $DB;

try {
    $action     = optional_param('action',     'discover', PARAM_ALPHANUMEXT);
    $company_id = optional_param('company_id', 0,         PARAM_INT);

    if (!in_array($action, ['discover', 'describe'], true)) {
        throw new Exception('Azione non supportata.');
    }

    // --- Controlla cache DB prima di chiamare l'API ---
    if ($company_id > 0 && $DB->get_manager()->table_exists('local_jobmatch_ticino_companies')) {
        $cached = $DB->get_record('local_jobmatch_ticino_companies', ['id' => $company_id], '*', IGNORE_MISSING);
        $has_descrizione = $cached && !empty($cached->descrizione_ai ?? null);
        if ($has_descrizione) {
            echo json_encode([
                'success' => true,
                'data'    => [
                    'nome'        => $cached->nome        ?? '',
                    'indirizzo'   => $cached->indirizzo   ?? '',
                    'cap'         => $cached->cap         ?? '',
                    'localita'    => $cached->localita    ?? '',
                    'settore_ftm' => $cached->settore_ftm ?? 'ALTRO',
                    'settore_raw' => $cached->settore_raw ?? '',
                    'dimensione'  => $cached->dimensione  ?? 'unknown',
                    'website'     => $cached->website     ?? '',
                    'email'       => $cached->email       ?? '',
                    'telefono'    => $cached->telefono    ?? '',
                    'referente'   => $cached->referente   ?? '',
                    'descrizione' => $cached->descrizione_ai,
                    'confidence'  => 100,
                    'domande'     => [],
                    'cached'      => true,
                ],
                'message' => 'Dati da archivio (analisi precedente).',
            ]);
            die();
        }
    } else {
        $cached = null;
    }

    // --- API key ---
    $api_key = get_config('local_ftm_jobsearch', 'openai_apikey');
    if (empty($api_key)) {
        $api_key = get_config('local_jobaida', 'openai_apikey');
    }
    if (empty($api_key)) {
        throw new Exception('API key OpenAI non configurata.');
    }
    $model = get_config('local_jobmatchagent', 'openai_model') ?: 'gpt-4o-mini';

    // ============================================================
    // ACTION: describe — genera descrizione da nome/settore/luogo
    // (nessun scraping web, usa solo dati già in DB)
    // ============================================================
    if ($action === 'describe') {
        if (!$cached) {
            throw new Exception('Azienda non trovata nel database.');
        }
        $result = describe_company_from_db($cached, $api_key, $model);

        // Salva descrizione in DB se abbiamo il company_id.
        if ($company_id > 0 && !empty($result['descrizione'])
            && $DB->get_manager()->table_exists('local_jobmatch_ticino_companies')) {
            try {
                $upd = ['id' => $company_id, 'timemodified' => time(), 'descrizione_ai' => $result['descrizione']];
                $DB->update_record('local_jobmatch_ticino_companies', (object)$upd);
            } catch (\Throwable $e) {
                // Non-critical.
            }
        }

        echo json_encode(['success' => true, 'data' => $result, 'message' => 'Descrizione generata.']);
        die();
    }

    // ============================================================
    // ACTION: discover — scraping sito web + analisi AI
    // ============================================================
    $url = required_param('url', PARAM_URL);
    if (empty($url)) {
        throw new Exception('URL non valido.');
    }

    // Normalizza URL.
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    // --- Step 1 + 2: Fetch + clean pagina principale ---
    $main_text = fetch_and_clean_page($url);
    if (empty(trim($main_text))) {
        throw new Exception('Impossibile recuperare il contenuto del sito. Verifica che l\'URL sia accessibile pubblicamente.');
    }

    // --- Step 3: Identifica link interni rilevanti ---
    $subpage_keywords = ['chi-siamo', 'about', 'azienda', 'prodotti', 'services', 'servizi', 'produkte', 'unternehmen', 'kontakt'];
    $internal_links   = extract_internal_links($url, $main_text, $subpage_keywords);

    // --- Step 4: Fetch max 2 sub-pagine ---
    $combined_text = $main_text;
    $fetched       = 0;
    foreach (array_slice($internal_links, 0, 2) as $sub_url) {
        $sub_text = fetch_and_clean_page($sub_url, 5000);
        if (!empty(trim($sub_text))) {
            $combined_text .= "\n\n--- Pagina: $sub_url ---\n" . $sub_text;
            $fetched++;
        }
        if ($fetched >= 2) break;
    }

    // --- Step 5: Tronca a 8000 chars ---
    $combined_text = mb_substr($combined_text, 0, 8000);

    // --- Step 6: GPT analisi ---
    $result = analyze_company_with_ai($combined_text, $url, $api_key, $model);

    // --- Salva in DB se abbiamo un company_id valido ---
    if ($company_id > 0 && !empty($result['descrizione'])
        && $DB->get_manager()->table_exists('local_jobmatch_ticino_companies')) {
        try {
            $upd = ['id' => $company_id, 'timemodified' => time()];
            $upd['descrizione_ai'] = $result['descrizione'];
            if (!empty($result['telefono']))  $upd['telefono']  = $result['telefono'];
            if (!empty($result['email']))     $upd['email']     = $result['email'];
            if (!empty($result['referente'])) $upd['referente'] = $result['referente'];
            if (!empty($result['settore_raw'])) $upd['settore_raw'] = $result['settore_raw'];
            if (!empty($result['dimensione']) && $result['dimensione'] !== 'unknown') {
                $upd['dimensione'] = $result['dimensione'];
            }
            $DB->update_record('local_jobmatch_ticino_companies', (object)$upd);
            $result['cached'] = false;
        } catch (\Throwable $e) {
            // Non-critical: analisi riuscita, solo il salvataggio ha fallito.
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => $result,
        'message' => 'Analisi completata.',
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data'    => [],
    ]);
}

die();

// ============================================================================
// Helpers
// ============================================================================

/**
 * Recupera una pagina web e restituisce il testo pulito.
 *
 * @param string $url
 * @param int    $max_chars  Lunghezza massima del testo estratto
 * @return string
 */
function fetch_and_clean_page(string $url, int $max_chars = 4000): string {
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                       . "AppleWebKit/537.36 (KHTML, like Gecko) "
                       . "Chrome/124.0.0.0 Safari/537.36\r\n"
                       . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
                       . "Accept-Language: it-IT,it;q=0.9,de-CH;q=0.8,en;q=0.7\r\n",
            'timeout' => 12,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ];

    $ctx = stream_context_create($opts);

    // Usa cURL se disponibile (piu affidabile di file_get_contents su hosting condiviso).
    if (function_exists('curl_init')) {
        $html = curl_fetch($url, 12);
    } else {
        $html = @file_get_contents($url, false, $ctx);
    }

    if ($html === false || empty($html)) {
        return '';
    }

    return clean_html_to_text($html, $max_chars);
}

/**
 * Fetch tramite cURL.
 */
function curl_fetch(string $url, int $timeout = 12): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept-Language: it-IT,it;q=0.9,de-CH;q=0.8,en;q=0.7'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING       => 'gzip,deflate',
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: '';
}

/**
 * Converte HTML grezzo in testo pulito.
 *
 * @param string $html
 * @param int    $max_chars
 * @return string
 */
function clean_html_to_text(string $html, int $max_chars = 4000): string {
    // Rimuovi script, style, nav, footer, form, svg, noscript.
    $html = preg_replace('/<(script|style|nav|footer|form|svg|noscript|header)[^>]*>.*?<\/\1>/si', ' ', $html);

    // Converti tag block in newline.
    $html = preg_replace('/<\/(p|div|li|h[1-6]|br|tr|td|th|section|article|aside)[^>]*>/i', "\n", $html);

    // Strip tutti i tag HTML.
    $html = strip_tags($html);

    // Decode entita HTML.
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalizza whitespace.
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    $html = trim($html);

    return mb_substr($html, 0, $max_chars);
}

/**
 * Estrae link interni rilevanti dalla pagina principale.
 *
 * @param string $base_url URL base del sito
 * @param string $html     HTML grezzo della pagina principale
 * @param array  $keywords Parole chiave nei path URL
 * @return string[]  Array di URL assoluti
 */
function extract_internal_links(string $base_url, string $html, array $keywords): array {
    $parsed  = parse_url($base_url);
    $base    = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    $found   = [];

    preg_match_all('/<a[^>]+href=["\']([^"\'#?]+)["\'][^>]*>/i', $html, $matches);
    foreach ($matches[1] as $href) {
        $href = trim($href);
        if (empty($href) || $href === '/') continue;

        // Costruisci URL assoluto.
        if (strpos($href, 'http') === 0) {
            // Solo link interni allo stesso dominio.
            if (strpos($href, $base) !== 0) continue;
            $abs = $href;
        } elseif (strpos($href, '/') === 0) {
            $abs = $base . $href;
        } else {
            $abs = rtrim($base_url, '/') . '/' . $href;
        }

        // Controlla se il path contiene una keyword.
        $path = strtolower(parse_url($abs, PHP_URL_PATH) ?? '');
        foreach ($keywords as $kw) {
            if (strpos($path, $kw) !== false) {
                $found[] = $abs;
                break;
            }
        }
    }

    return array_unique($found);
}

/**
 * Genera una descrizione aziendale via AI usando solo i dati già presenti in DB
 * (senza scraping web). Utile per aziende senza sito web.
 */
function describe_company_from_db(object $company, string $api_key, string $model): array {
    $nome     = $company->nome     ?? 'N/D';
    $localita = $company->localita ?? '';
    $cap      = $company->cap      ?? '';
    $settore  = $company->settore_raw ?? ($company->settore_ftm ?? '');
    $dim      = $company->dimensione ?? 'unknown';
    $note     = $company->note_interne ?? '';

    $dim_label = match($dim) {
        'S' => 'piccola (meno di 50 dipendenti)',
        'M' => 'media (50-249 dipendenti)',
        'L' => 'grande (250+ dipendenti)',
        default => ''
    };

    $context = "Azienda: $nome\n";
    if ($localita || $cap) $context .= "Sede: " . trim("$cap $localita") . "\n";
    if ($settore) $context .= "Settore/Attività: $settore\n";
    if ($dim_label) $context .= "Dimensione: $dim_label\n";
    if ($note) $context .= "Note: $note\n";

    $prompt = 'Sei un esperto di mercato del lavoro svizzero (Ticino).
Sulla base delle informazioni seguenti, scrivi una scheda aziendale utile per un candidato in cerca di lavoro.
Rispondi SOLO con un oggetto JSON valido:

{
  "nome": "' . $nome . '",
  "localita": "' . ($localita ?: '') . '",
  "settore_ftm": "' . ($company->settore_ftm ?? 'ALTRO') . '",
  "settore_raw": "descrizione attivita in 1-2 frasi (italiano)",
  "dimensione": "' . $dim . '",
  "descrizione": "paragrafo descrittivo in italiano (4-6 frasi): tipo di azienda, cosa produce/fa, mercato, punti di forza per un candidato. Non inventare dettagli specifici non presenti nelle informazioni.",
  "confidence": 60,
  "domande": []
}

INFORMAZIONI AZIENDA:
' . $context;

    $payload = json_encode([
        'model'           => $model,
        'messages'        => [
            ['role' => 'system', 'content' => 'Rispondi SEMPRE e SOLO con JSON valido. Nessun testo aggiuntivo.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature'     => 0.3,
        'max_tokens'      => 600,
        'response_format' => ['type' => 'json_object'],
    ]);

    $opts = [
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAuthorization: Bearer $api_key\r\n",
            'content'       => $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
    ];

    $raw = @file_get_contents('https://api.openai.com/v1/chat/completions', false, stream_context_create($opts));
    if (!$raw) throw new Exception('Errore API OpenAI (nessuna risposta).');

    $decoded = json_decode($raw, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    if (empty($content)) throw new Exception('Risposta AI vuota.');

    $data = json_decode($content, true);
    if (!is_array($data)) {
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
        }
    }
    if (!is_array($data)) throw new Exception('AI non ha restituito JSON valido.');

    // Mantieni i campi originali dal DB se non sovrascritti.
    $data['nome']        = $nome;
    $data['indirizzo']   = $company->indirizzo ?? '';
    $data['cap']         = $company->cap       ?? '';
    $data['localita']    = $company->localita  ?? ($data['localita'] ?? '');
    $data['email']       = $company->email     ?? '';
    $data['telefono']    = $company->telefono  ?? '';
    $data['referente']   = $company->referente ?? '';
    $data['website']     = $company->website   ?? '';
    $data['domande']     = [];
    $data['cached']      = false;

    return $data;
}

/**
 * Analizza il testo estratto dal sito con GPT e restituisce dati strutturati.
 *
 * @param string $text      Testo combinato delle pagine (max 8000 chars)
 * @param string $url       URL originale (usato come fallback website)
 * @param string $api_key
 * @param string $model
 * @return array  Dati estratti + domande AI
 */
function analyze_company_with_ai(string $text, string $url, string $api_key, string $model): array {
    $prompt = 'Sei un esperto di industria manifatturiera svizzera (Ticino).
Analizza questo testo estratto dal sito web aziendale e rispondi SOLO con un oggetto JSON valido:

{
  "nome": "nome completo dell\'azienda",
  "indirizzo": "via e numero civico",
  "cap": "codice postale svizzero (6xxx per Ticino)",
  "localita": "citta o comune ticinese",
  "settore_ftm": "uno tra: AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, MECCANICA, METALCOSTRUZIONE, ALTRO",
  "settore_raw": "descrizione attivita reale in 1-2 frasi (italiano)",
  "dimensione": "S oppure M oppure L oppure unknown",
  "website": "URL homepage principale",
  "email": "indirizzo email di contatto (se presente)",
  "telefono": "numero di telefono principale (se presente)",
  "referente": "nome HR o responsabile contatti (se presente)",
  "descrizione": "paragrafo descrittivo dell\'azienda in italiano (4-8 frasi): storia, prodotti/servizi, mercato, punti di forza. Scrivi come se fosse una scheda informativa per un candidato in cerca di lavoro.",
  "confidence": 75,
  "domande": [
    {
      "id": "q1",
      "testo": "domanda specifica se c\'e\' ambiguita reale",
      "tipo": "select",
      "opzioni": ["Opzione A", "Opzione B", "Opzione C"]
    }
  ]
}

Regole settore_ftm:
- AUTOMOBILE = officine, carrozzerie, concessionarie, manutenzione veicoli
- AUTOMAZIONE = sistemi automatici, PLC, robotica, meccatronica, elettronica industriale
- CHIMFARM = chimica, farmaceutica, laboratori, materie plastiche, cosmetici
- ELETTRICITA = installatori elettrici, quadri elettrici, energia, fotovoltaico, reti
- LOGISTICA = trasporti, spedizioni, magazzini, corrieri, distribuzione
- MECCANICA = officine meccaniche, tornitori, CNC, stampi, utensili, costruzioni macchine
- METALCOSTRUZIONE = saldatura, carpenteria metallica, costruzioni metalliche, serramentisti, lamieristi
- ALTRO = settori non manifatturieri

Regole dimensione:
- S = meno di 50 dipendenti (artigiani, PMI)
- M = 50-249 dipendenti
- L = 250+ dipendenti (grandi industrie)
- unknown = non determinabile

Regole domande:
- Genera 1-2 domande SOLO se ci sono ambiguita reali (es. azienda multiprodotto con settori diversi)
- Se il settore e\' chiaro, metti "domande": []
- tipo "select" per scelte multiple, tipo "text" per risposte libere

URL analizzato: ' . $url . '

TESTO DEL SITO:
' . $text;

    $payload = json_encode([
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => 'Rispondi SEMPRE e SOLO con JSON valido. Nessun testo aggiuntivo.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.2,
        'max_tokens'  => 1200,
        'response_format' => ['type' => 'json_object'],
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"
                       . "Authorization: Bearer $api_key\r\n",
            'content' => $payload,
            'timeout' => 40,
            'ignore_errors' => true,
        ],
    ];

    $ctx     = stream_context_create($opts);
    $raw     = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $ctx);
    $headers = $http_response_header ?? [];
    $status  = 200;

    foreach ($headers as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
            $status = (int)$m[1];
        }
    }

    if ($raw === false || $status >= 400) {
        $err = $raw ? (json_decode($raw, true)['error']['message'] ?? $raw) : 'Nessuna risposta';
        throw new Exception('Errore API OpenAI (HTTP ' . $status . '): ' . $err);
    }

    $decoded = json_decode($raw, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';

    if (empty($content)) {
        throw new Exception('Risposta AI vuota. Riprova.');
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        // Ultimo tentativo: cerca JSON nel testo.
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $data = json_decode($m[0], true);
        }
        if (!is_array($data)) {
            throw new Exception('AI non ha restituito un JSON valido. Riprova o controlla manualmente il sito.');
        }
    }

    // Normalizza e valida settore.
    $valid_sectors = ['AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITA', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE', 'ALTRO'];
    $data['settore_ftm'] = strtoupper($data['settore_ftm'] ?? 'ALTRO');
    if (!in_array($data['settore_ftm'], $valid_sectors, true)) {
        $data['settore_ftm'] = 'ALTRO';
    }

    // Normalizza dimensione.
    $valid_dim = ['S', 'M', 'L', 'unknown'];
    $data['dimensione'] = strtoupper($data['dimensione'] ?? 'unknown');
    if (!in_array($data['dimensione'], $valid_dim, true)) {
        $data['dimensione'] = 'unknown';
    }

    // Fallback website.
    if (empty($data['website'])) {
        $data['website'] = $url;
    }

    // Assicura domande sia array.
    if (!isset($data['domande']) || !is_array($data['domande'])) {
        $data['domande'] = [];
    }

    return $data;
}
