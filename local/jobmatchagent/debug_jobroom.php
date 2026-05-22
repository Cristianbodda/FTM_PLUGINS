<?php
/**
 * Debug: verifica offerte job-room.ch — mostra UUID, risposta API, match.
 * Accessibile solo a siteadmin.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/jobmatchagent/debug_jobroom.php'));
$PAGE->set_title('Debug job-room.ch');
$PAGE->set_pagelayout('admin');

// Opzionale: testare un singolo offerid specifico
$offerid = optional_param('offerid', 0, PARAM_INT);

echo $OUTPUT->header();
echo '<h3>Debug job-room.ch — verifica UUID e API</h3>';

// ---- Carica offerte job-room.ch dal DB ----
if ($offerid) {
    $offers = $DB->get_records_select('local_jobmatch_offers',
        "id = :id AND url LIKE :pat",
        ['id' => $offerid, 'pat' => '%job-room.ch%'], '', '*', 0, 1);
} else {
    $offers = $DB->get_records_select('local_jobmatch_offers',
        "url LIKE '%job-room.ch%'",
        [], 'timecreated DESC', '*', 0, 10);
}

if (empty($offers)) {
    echo '<div class="alert alert-warning">Nessuna offerta job-room.ch nel DB.</div>';
    echo $OUTPUT->footer();
    die();
}

echo '<p class="text-muted">Mostrando ' . count($offers) . ' offerta/e job-room.ch. <a href="?">Mostra ultime 10</a></p>';

foreach ($offers as $offer) {
    $url  = $offer->url ?? '';

    // Estrai UUID
    $uuid = '';
    if (preg_match('|/job-search/detail/([a-zA-Z0-9\-]+)|', $url, $m)) {
        $uuid = $m[1];
    }

    echo '<div class="card mb-4">';
    echo '<div class="card-header"><strong>' . s($offer->title) . '</strong>';
    echo ' — ' . s($offer->company ?? '?') . ' — ' . s($offer->location ?? '?');
    echo ' &nbsp;<small class="text-muted">[ID ' . (int)$offer->id . ']</small></div>';
    echo '<div class="card-body" style="font-size:.88rem">';

    echo '<p><strong>URL stored:</strong> <code>' . s($url) . '</code></p>';
    echo '<p><strong>UUID estratto:</strong> <code>' . ($uuid ?: '<span class="text-danger">NON TROVATO nel pattern</span>') . '</code></p>';
    echo '<p><strong>Data scraping:</strong> ' . userdate((int)($offer->timecreated ?? 0)) . '</p>';

    if (!$uuid) {
        echo '<div class="alert alert-danger">UUID non estraibile — URL potrebbe avere formato diverso da /job-search/detail/{uuid}</div>';
        echo '</div></div>';
        continue;
    }

    // ---- Chiama API POST search con il titolo ----
    $title_words = preg_split('/[\s\/\-|,]+/u', strip_tags($offer->title), -1, PREG_SPLIT_NO_EMPTY);
    $search_kw3  = implode(' ', array_slice($title_words, 0, 3));
    $search_kw1  = $title_words[0] ?? '';

    echo '<p><strong>Keyword usata per search (3 parole):</strong> <code>' . s($search_kw3) . '</code></p>';

    // Test con 3 parole
    foreach ([
        ['kw' => $search_kw3, 'label' => '3 parole titolo'],
        ['kw' => $search_kw1, 'label' => '1 parola titolo'],
        ['kw' => '',          'label' => 'nessuna keyword (tutti Ticino)'],
    ] as $test) {
        $kw_label = $test['label'];
        $kw       = $test['kw'];

        $body = json_encode([
            'workloadPercentageMin' => 0,
            'workloadPercentageMax' => 100,
            'permanent'             => null,
            'companyName'           => null,
            'onlineSince'           => 365,
            'displayRestricted'     => false,
            'professionCodes'       => [],
            'keywords'              => $kw ? [$kw] : [],
            'communalCodes'         => [],
            'cantonCodes'           => ['TI'],
        ]);

        $ch = curl_init('https://www.job-room.ch/jobadservice/api/jobAdvertisements/_search'
            . '?page=0&size=100&sort=date_desc&_ng=aXQ=');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Language: it',
            ],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $api_body   = curl_exec($ch);
        $api_code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        echo '<hr><h6>Test: ' . s($kw_label) . '</h6>';
        echo '<p>HTTP: <strong>' . $api_code . '</strong>';
        if ($curl_error) {
            echo ' &nbsp; <span class="text-danger">Curl error: ' . s($curl_error) . '</span>';
        }

        if ($api_code !== 200 || !$api_body) {
            echo ' — <span class="text-danger">API non risponde o errore</span></p>';
            continue;
        }

        $data = json_decode($api_body, true);
        if (!is_array($data)) {
            echo ' — <span class="text-danger">JSON non valido. Raw (primi 200 bytes): ' . s(substr($api_body, 0, 200)) . '</span></p>';
            continue;
        }

        echo ' — <strong>' . count($data) . ' risultati</strong> (X-Total-Count da header non disponibile qui)</p>';

        // Cerca l'UUID
        $found       = false;
        $found_job   = null;
        $all_ids     = [];
        foreach ($data as $item) {
            $job_obj = $item['jobAdvertisement'] ?? $item;
            $jid     = $job_obj['id'] ?? '';
            $all_ids[] = $jid;
            if ($jid === $uuid || strtolower($jid) === strtolower($uuid)) {
                $found     = true;
                $found_job = $job_obj;
                break;
            }
        }

        if ($found) {
            echo '<div class="alert alert-success">✅ UUID TROVATO nei risultati! Il job è ancora attivo sul portale.<br>';
            echo '<strong>URL job-room.ch detail page:</strong> <a href="' . s($url) . '" target="_blank">' . s($url) . '</a><br>';
            // Cerca externalUrl o applyChannel
            $apply = $found_job['jobContent']['applyChannel'] ?? [];
            $ext_url = $apply['url'] ?? ($apply['formUrl'] ?? ($found_job['publication']['externalUrl'] ?? ''));
            if ($ext_url && str_starts_with($ext_url, 'http')) {
                echo '<strong>External URL (azienda):</strong> <a href="' . s($ext_url) . '" target="_blank">' . s($ext_url) . '</a><br>';
            }
            echo '</div>';
            break; // non serve testare altri keyword se già trovato
        } else {
            echo '<div class="alert alert-warning">⚠ UUID NON trovato in questo search. ';
            echo count($all_ids) . ' IDs restituiti.<br>';
            if (count($all_ids) > 0) {
                echo '<strong>Primi 5 IDs restituiti:</strong> ' . s(implode(', ', array_slice($all_ids, 0, 5)));
            }
            echo '</div>';
        }
    }

    // ---- Test GET diretto per UUID ----
    echo '<hr><h6>Test GET diretto: <code>/jobadservice/api/jobAdvertisements/{uuid}</code></h6>';
    $get_url = 'https://www.job-room.ch/jobadservice/api/jobAdvertisements/' . urlencode($uuid) . '?_ng=aXQ=';
    $ch2 = curl_init($get_url);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $get_body = curl_exec($ch2);
    $get_code = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo '<p>HTTP: <strong>' . $get_code . '</strong> ';
    if ($get_code === 200) {
        $get_data = json_decode($get_body, true);
        if (is_array($get_data)) {
            echo '<span class="text-success">✅ GET by UUID funziona! Restituisce JSON valido.</span>';
            $apply = $get_data['jobContent']['applyChannel'] ?? [];
            $ext = $apply['url'] ?? ($apply['formUrl'] ?? ($get_data['publication']['externalUrl'] ?? ''));
            if ($ext && str_starts_with($ext, 'http')) {
                echo '<br><strong>External URL dall\'API GET:</strong> <a href="' . s($ext) . '" target="_blank">' . s($ext) . '</a>';
            }
        } else {
            echo '<span class="text-warning">HTTP 200 ma JSON non valido. Raw: ' . s(substr($get_body, 0, 300)) . '</span>';
        }
    } else {
        echo '<span class="text-danger">❌ HTTP ' . $get_code . ' — GET by UUID non funziona</span>';
        if ($get_body) {
            echo ' — Response: ' . s(substr($get_body, 0, 200));
        }
    }
    echo '</p>';

    echo '</div></div>'; // card-body, card
}

echo $OUTPUT->footer();
