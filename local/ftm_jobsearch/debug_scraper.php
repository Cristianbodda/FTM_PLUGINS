<?php
/**
 * Diagnostica scraper AI - testa ogni step della pipeline.
 *
 * USO: /local/ftm_jobsearch/debug_scraper.php
 *
 * @package    local_ftm_jobsearch
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/ftm_jobsearch/debug_scraper.php');
$PAGE->set_title('Debug AI Scraper');
$PAGE->set_heading('Debug AI Scraper - Pipeline diagnostica');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo '<pre style="background:#1e1e2e; color:#cdd6f4; padding:20px; border-radius:8px; font-size:0.85rem; overflow-x:auto; line-height:1.6;">';

// ============ STEP 0: API Key ============
echo "<strong style='color:#89b4fa;'>== STEP 0: API Key ==</strong>\n";
$key = get_config('local_ftm_jobsearch', 'openai_apikey');
if (empty($key)) {
    $key = get_config('local_jobaida', 'openai_apikey');
    if (!empty($key)) {
        echo "<span style='color:#a6e3a1;'>OK</span> - Usando chiave API di JobAIDA (fallback)\n";
    } else {
        echo "<span style='color:#f38ba8;'>ERRORE</span> - Nessuna chiave API configurata!\n";
        echo "Configura in: Amministrazione > Plugin > FTM Job Search > Chiave API OpenAI\n";
        echo "Oppure in: Amministrazione > Plugin > JobAIDA > Chiave API OpenAI\n";
        echo '</pre>';
        echo $OUTPUT->footer();
        die();
    }
} else {
    echo "<span style='color:#a6e3a1;'>OK</span> - Chiave API propria configurata\n";
}
$model = get_config('local_ftm_jobsearch', 'openai_model') ?: 'gpt-4o-mini';
echo "Modello: {$model}\n";
echo "Chiave (prime 8): " . substr($key, 0, 8) . "...\n\n";

// ============ STEP 1: Tabelle DB ============
echo "<strong style='color:#89b4fa;'>== STEP 1: Stato DB ==</strong>\n";
$tables = ['local_ftm_jobsearch_offers', 'local_ftm_jobsearch_searches', 'local_ftm_jobsearch_cities'];
foreach ($tables as $t) {
    $dbman = $DB->get_manager();
    if ($dbman->table_exists($t)) {
        $count = $DB->count_records($t);
        echo "<span style='color:#a6e3a1;'>OK</span> - {$t}: {$count} record\n";
    } else {
        echo "<span style='color:#f38ba8;'>MANCA</span> - {$t} non esiste! Reinstalla il plugin.\n";
    }
}
echo "\n";

// ============ STEP 2: Fetch HTML ============
echo "<strong style='color:#89b4fa;'>== STEP 2: Fetch HTML dai siti ==</strong>\n";

$test_urls = [
    'jobs.ch'     => 'https://www.jobs.ch/en/vacancies/?region=4&term=meccanico',
    'adecco.ch'   => 'https://www.adecco.com/it-ch/ricerca-lavoro?jobTitle=meccanico&jobLocation=Ticino%2C+Svizzera&radius=100',
    'manpower.ch' => 'https://www.manpower.ch/it/trova-lavoro?page=1&searchKeyword=meccanico&place=Ticino,+Svizzera',
    'randstad.ch' => 'https://www.randstad.ch/it/lavoro/q-meccanico/re-ticino/',
    'job-room.ch' => 'https://www.job-room.ch/job-search?query-values=%7B%22occupations%22%3A%5B%5D%2C%22keywords%22%3A%5B%22meccanico%22%5D%2C%22localities%22%3A%5B%7B%22type%22%3A%22canton%22%2C%22payload%22%3A%7B%22cantonCode%22%3A%22TI%22%7D%2C%22label%22%3A%22Ticino%20(TI)%22%2C%22order%22%3A0%2C%22_id%22%3A%22canton_Ticino%20(TI)%22%7D%5D%2C%22radius%22%3A30%7D',
    'carriera.ch' => 'https://www.carriera.ch/cgi-bin/annunci_offerte_lavoro.cgi',
    'tuttojob.ch' => 'https://tuttojob.ch/search/?searchText=meccanico&idZona=230',
];

$fetched_html = [];

foreach ($test_urls as $site => $url) {
    echo "\n<span style='color:#f9e2af;'>Fetching {$site}...</span>\n";
    echo "URL: {$url}\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept-Language: it-IT,it;q=0.9',
            'Accept: text/html,application/xhtml+xml',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $html = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "HTTP Code: {$httpcode}\n";
    echo "Content-Type: {$content_type}\n";
    echo "Effective URL: {$effective_url}\n";

    if ($error) {
        echo "<span style='color:#f38ba8;'>CURL Error: {$error}</span>\n";
        continue;
    }

    if ($httpcode < 200 || $httpcode >= 400) {
        echo "<span style='color:#f38ba8;'>HTTP Error {$httpcode}</span>\n";
        // Show first 500 chars of response for debugging
        echo "Response preview: " . htmlspecialchars(substr($html ?: '', 0, 500)) . "\n";
        continue;
    }

    $html_len = strlen($html ?: '');
    echo "<span style='color:#a6e3a1;'>OK</span> - {$html_len} bytes ricevuti\n";

    // Check for Cloudflare/bot protection
    if (stripos($html, 'cloudflare') !== false && stripos($html, 'challenge') !== false) {
        echo "<span style='color:#fab387;'>ATTENZIONE: Possibile protezione Cloudflare!</span>\n";
    }
    if (stripos($html, 'captcha') !== false) {
        echo "<span style='color:#fab387;'>ATTENZIONE: Possibile CAPTCHA!</span>\n";
    }

    $fetched_html[$site] = $html;

    // Show title
    preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $title_match);
    echo "Page title: " . htmlspecialchars(trim($title_match[1] ?? 'N/A')) . "\n";
}

echo "\n";

// ============ STEP 3: Clean HTML ============
echo "<strong style='color:#89b4fa;'>== STEP 3: Pulizia HTML ==</strong>\n";

$cleaned_texts = [];

foreach ($fetched_html as $site => $html) {
    echo "\n<span style='color:#f9e2af;'>Cleaning {$site}...</span>\n";

    // Same cleaning logic as ai_scraper.php
    $clean = $html;
    $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $clean);
    $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $clean);
    $clean = preg_replace('/<noscript[^>]*>.*?<\/noscript>/si', '', $clean);
    $clean = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $clean);
    $clean = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $clean);
    $clean = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $clean);
    $clean = preg_replace('/<(h[1-6])[^>]*>/i', "\n### ", $clean);
    $clean = preg_replace('/<\/(h[1-6])>/i', "\n", $clean);
    $clean = preg_replace('/<li[^>]*>/i', "\n- ", $clean);
    $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);
    $clean = preg_replace('/<\/p>/i', "\n", $clean);
    $clean = preg_replace('/<\/div>/i', "\n", $clean);
    $clean = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>/i', ' [LINK:$1] ', $clean);
    $text = strip_tags($clean);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    echo "Testo pulito: " . strlen($text) . " chars\n";

    if (strlen($text) < 100) {
        echo "<span style='color:#f38ba8;'>TROPPO CORTO - il sito potrebbe essere bloccato o vuoto</span>\n";
    } else {
        echo "<span style='color:#a6e3a1;'>OK</span>\n";
    }

    // Show first 1500 chars of cleaned text
    $preview = mb_substr($text, 0, 1500);
    echo "\n<span style='color:#94e2d5;'>--- PREVIEW TESTO PULITO ({$site}, primi 1500 chars) ---</span>\n";
    echo htmlspecialchars($preview);
    echo "\n<span style='color:#94e2d5;'>--- FINE PREVIEW ---</span>\n";

    $cleaned_texts[$site] = $text;
}

echo "\n";

// ============ STEP 4: AI Extraction ============
echo "<strong style='color:#89b4fa;'>== STEP 4: Estrazione AI (GPT) ==</strong>\n";

// Test with the first site that has content
$test_site = null;
$test_text = null;
foreach ($cleaned_texts as $site => $text) {
    if (strlen($text) > 200) {
        $test_site = $site;
        $test_text = $text;
        break;
    }
}

if (!$test_site) {
    echo "<span style='color:#f38ba8;'>Nessun sito ha restituito testo sufficiente. Impossibile testare AI.</span>\n";
    echo '</pre>';
    echo $OUTPUT->footer();
    die();
}

echo "Testo da: {$test_site} (" . strlen($test_text) . " chars)\n";

// Truncate for API
$truncated = mb_substr($test_text, 0, 6000);
echo "Troncato a: " . strlen($truncated) . " chars per API\n\n";

$prompt = "Sei un sistema di estrazione dati. Dalla pagina web qui sotto, estrai TUTTE le offerte di lavoro visibili.\n\n"
    . "Per ogni offerta estrai:\n"
    . "- titolo: il titolo del posto\n"
    . "- azienda: nome azienda (null se non presente)\n"
    . "- citta: citta del posto (null se non presente)\n"
    . "- tipo_lavoro: fulltime/parttime/stage/apprendistato/temporaneo (null se non chiaro)\n"
    . "- url: link all'offerta completa (ricostruisci URL assoluto se relativo, base: https://www.{$test_site})\n"
    . "- data_pubblicazione: data se presente (formato YYYY-MM-DD, null se non presente)\n\n"
    . "Rispondi SOLO con un JSON array. Se non trovi offerte, rispondi con [].\n"
    . "Non aggiungere testo prima o dopo il JSON.\n\n"
    . "=== CONTENUTO PAGINA ({$test_site}) ===\n"
    . $truncated;

echo "<span style='color:#f9e2af;'>Chiamando OpenAI ({$model})...</span>\n";

$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'Estrai dati strutturati da pagine web. Rispondi solo con JSON valido.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'max_tokens' => 2000,
    'temperature' => 0.1,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<span style='color:#f38ba8;'>CURL Error: {$error}</span>\n";
} else if ($httpcode !== 200) {
    echo "<span style='color:#f38ba8;'>HTTP {$httpcode}</span>\n";
    $errdata = json_decode($response, true);
    echo "Error: " . htmlspecialchars($errdata['error']['message'] ?? $response) . "\n";
} else {
    $data = json_decode($response, true);
    $ai_text = trim($data['choices'][0]['message']['content'] ?? '');
    $usage = $data['usage'] ?? [];

    echo "<span style='color:#a6e3a1;'>OK</span> - Risposta ricevuta\n";
    echo "Tokens: input=" . ($usage['prompt_tokens'] ?? '?') . " output=" . ($usage['completion_tokens'] ?? '?') . " total=" . ($usage['total_tokens'] ?? '?') . "\n";
    echo "Costo stimato: ~$" . number_format(
        (($usage['prompt_tokens'] ?? 0) * 0.00000015) + (($usage['completion_tokens'] ?? 0) * 0.0000006),
        4
    ) . "\n\n";

    echo "<span style='color:#94e2d5;'>--- RISPOSTA AI ---</span>\n";
    echo htmlspecialchars($ai_text);
    echo "\n<span style='color:#94e2d5;'>--- FINE RISPOSTA ---</span>\n\n";

    // Parse
    $cleaned = preg_replace('/^```json\s*/i', '', $ai_text);
    $cleaned = preg_replace('/\s*```$/', '', $cleaned);
    $offers = json_decode(trim($cleaned), true);

    if ($offers === null) {
        echo "<span style='color:#f38ba8;'>JSON PARSE ERROR:</span> " . json_last_error_msg() . "\n";
    } else if (empty($offers)) {
        echo "<span style='color:#fab387;'>ATTENZIONE: Array vuoto - AI non ha trovato offerte nel testo.</span>\n";
        echo "Possibili cause:\n";
        echo "  - Il testo pulito non contiene offerte riconoscibili\n";
        echo "  - Il sito ha restituito una pagina di errore/redirect\n";
        echo "  - Il formato del sito non e' stato interpretato correttamente\n";
    } else {
        echo "<span style='color:#a6e3a1;'>TROVATE " . count($offers) . " OFFERTE!</span>\n\n";
        foreach (array_slice($offers, 0, 5) as $i => $o) {
            echo "<span style='color:#cba6f7;'>Offerta " . ($i + 1) . ":</span>\n";
            echo "  Titolo: " . htmlspecialchars($o['titolo'] ?? '?') . "\n";
            echo "  Azienda: " . htmlspecialchars($o['azienda'] ?? '-') . "\n";
            echo "  Citta: " . htmlspecialchars($o['citta'] ?? '-') . "\n";
            echo "  URL: " . htmlspecialchars($o['url'] ?? '-') . "\n";
            echo "  Tipo: " . htmlspecialchars($o['tipo_lavoro'] ?? '-') . "\n\n";
        }
        if (count($offers) > 5) {
            echo "... e altre " . (count($offers) - 5) . " offerte.\n";
        }
    }
}

// ============ STEP 5: job-room.ch REST API ============
echo "<strong style='color:#89b4fa;'>== STEP 5: job-room.ch REST API (nessuna AI) ==</strong>\n";
echo "L'app e' Angular - il fetch HTML classico non funziona. Qui si chiama direttamente l'API REST.\n\n";

$jr_url = 'https://www.job-room.ch/jobadservice/api/jobAdvertisements/_search?page=0&size=20&sort=date_desc';
$jr_body = json_encode([
    'workloadPercentageMin' => 10,
    'workloadPercentageMax' => 100,
    'permanent'             => null,
    'companyName'           => null,
    'onlineSince'           => 60,
    'displayRestricted'     => false,
    'professionCodes'       => [],
    'keywords'              => ['meccanico'],
    'communalCodes'         => [],
    'cantonCodes'           => ['TI'],
]);

echo "<span style='color:#f9e2af;'>POST {$jr_url}</span>\n";
echo "Body: " . htmlspecialchars($jr_body) . "\n\n";

$ch = curl_init($jr_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $jr_body,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: it-IT,it;q=0.9',
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$raw      = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hsize    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<span style='color:#f38ba8;'>CURL Error: {$err}</span>\n";
} else {
    echo "HTTP Code: {$httpcode}\n";

    $resp_headers = substr($raw, 0, $hsize);
    $resp_body    = substr($raw, $hsize);

    // Leggi X-Total-Count
    $total = 0;
    if (preg_match('/X-Total-Count:\s*(\d+)/i', $resp_headers, $m)) {
        $total = (int)$m[1];
    }
    echo "X-Total-Count: {$total} offerte totali disponibili\n";
    echo "Body ricevuto: " . strlen($resp_body) . " bytes\n\n";

    if ($httpcode !== 200) {
        echo "<span style='color:#f38ba8;'>Errore HTTP {$httpcode}</span>\n";
        echo htmlspecialchars(substr($resp_body, 0, 500)) . "\n";
    } else {
        $jobs = json_decode($resp_body, true);

        if (!is_array($jobs)) {
            echo "<span style='color:#f38ba8;'>JSON PARSE ERROR: " . json_last_error_msg() . "</span>\n";
            echo "Raw body: " . htmlspecialchars(substr($resp_body, 0, 500)) . "\n";
        } else if (empty($jobs)) {
            echo "<span style='color:#fab387;'>Array vuoto - nessuna offerta trovata per questa keyword/canton.</span>\n";
            echo "Prova con un'altra keyword (es. manutentore, elettricista, saldatore).\n";
        } else {
            echo "<span style='color:#a6e3a1;'>OK - " . count($jobs) . " offerte ricevute nella pagina</span>\n\n";

            foreach (array_slice($jobs, 0, 5) as $i => $job) {
                $uuid  = $job['id'] ?? '?';
                $title = '';
                $tobj  = $job['title'] ?? null;
                if (is_array($tobj)) {
                    $title = $tobj['it'] ?? $tobj['de'] ?? $tobj['fr'] ?? $tobj['en'] ?? '';
                }
                if (empty($title)) {
                    foreach ($job['jobContent']['jobDescriptions'] ?? [] as $d) {
                        if (!empty($d['title'])) { $title = $d['title']; break; }
                    }
                }
                $azienda  = $job['company']['name'] ?? '-';
                $citta    = $job['jobContent']['location']['city'] ?? '-';
                $canton   = $job['jobContent']['location']['cantonCode'] ?? '-';
                $data_pub = $job['publication']['startDate'] ?? '-';
                $lat      = $job['jobContent']['location']['coordinates']['lat'] ?? null;
                $lng      = $job['jobContent']['location']['coordinates']['lon'] ?? null;
                $tipo_perm = !empty($job['jobContent']['jobType']['permanent']) ? 'permanente' : '';
                $tipo_temp = !empty($job['jobContent']['jobType']['temporary']) ? 'temporaneo' : '';
                $tipo      = $tipo_perm ?: $tipo_temp ?: '-';

                echo "<span style='color:#cba6f7;'>Offerta " . ($i + 1) . ":</span>\n";
                echo "  UUID: {$uuid}\n";
                echo "  Titolo: " . htmlspecialchars($title) . "\n";
                echo "  Azienda: " . htmlspecialchars($azienda) . "\n";
                echo "  Citta: " . htmlspecialchars($citta) . " ({$canton})";
                if ($lat && $lng) {
                    echo " - coords: {$lat}, {$lng}";
                }
                echo "\n";
                echo "  Tipo: {$tipo}\n";
                echo "  Pubblicato: {$data_pub}\n";
                echo "  URL: https://www.job-room.ch/job-search/detail/{$uuid}\n\n";
            }

            if (count($jobs) > 5) {
                echo "... e altre " . (count($jobs) - 5) . " offerte in questa pagina.\n";
            }
            if ($total > 20) {
                echo "\n<span style='color:#94e2d5;'>NOTA: " . ($total - count($jobs)) . " offerte aggiuntive disponibili su altre pagine (paginazione).</span>\n";
            }
        }
    }
}

echo "\n<strong style='color:#89b4fa;'>== FINE DIAGNOSTICA ==</strong>\n";
echo '</pre>';

echo $OUTPUT->footer();
