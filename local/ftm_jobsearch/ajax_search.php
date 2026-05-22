<?php
/**
 * AJAX endpoint for job search + AI scraping.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    $context = context_system::instance();
    $isauthorized = is_siteadmin()
        || has_capability('local/ftm_jobsearch:use', $context)
        || has_capability('local/ftm_jobsearch:manage', $context);

    if (!$isauthorized) {
        throw new Exception('Non autorizzato.');
    }

    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {

        case 'search':
            $settori_raw = optional_param('settori', '', PARAM_TEXT);
            $mansione    = optional_param('mansione', '', PARAM_TEXT);
            $tipo        = optional_param('tipo_lavoro', '', PARAM_ALPHANUMEXT);
            $citta       = optional_param('citta', '', PARAM_TEXT);
            $raggio_km   = optional_param('raggio_km', 30, PARAM_INT);
            $workload    = optional_param('workload', '', PARAM_TEXT);
            $force       = optional_param('force', 0, PARAM_INT);
            $cv_text     = optional_param('cv_text', '', PARAM_RAW);

            // Parse multi-sector (comma-separated).
            $settori = array_filter(array_map('trim', explode(',', $settori_raw)));

            // CV-only search: if no sectors selected but CV present, detect from CV via AI.
            $cv_detected_sectors = [];
            if (empty($settori) && !empty(trim($cv_text)) && mb_strlen($cv_text) > 30) {
                $cv_detected_sectors = \local_ftm_jobsearch\ai_scraper::detect_sectors_from_cv($cv_text);
                $settori = $cv_detected_sectors;
            }

            if (empty($settori)) {
                throw new Exception('Seleziona almeno un settore o inserisci il CV.');
            }

            $debug = [];

            // Step 1: Scrape for each sector.
            $scrape_from_cache = true;
            $total_scraped_sites = 0;
            $total_saved = 0;
            foreach ($settori as $s) {
                try {
                    $sr = \local_ftm_jobsearch\ai_scraper::scrape_sector($s, $mansione, (bool)$force);
                    if (!$sr['from_cache']) $scrape_from_cache = false;
                    $total_scraped_sites += ($sr['scraped_sites'] ?? 0);
                    $total_saved += ($sr['saved'] ?? 0);
                } catch (\Exception $e) {
                    // Continue with other sectors.
                }
            }
            $scrape_result = ['from_cache' => $scrape_from_cache, 'scraped_sites' => $total_scraped_sites];
            $debug['scraper'] = 'Settori: ' . implode(', ', $settori) . ' | cache=' . ($scrape_from_cache ? 'yes' : 'no')
                . ' | sites=' . $total_scraped_sites . ' | saved=' . $total_saved;
            if (!empty($cv_detected_sectors)) {
                $debug['cv_detect'] = 'Settori rilevati dal CV: ' . implode(', ', $cv_detected_sectors);
            }

            // Step 2: Search DB across all selected sectors.
            $offers = \local_ftm_jobsearch\job_manager::search_multi($settori, $tipo, $citta, $raggio_km, $workload);
            $debug['search'] = 'Trovate: ' . count($offers) . ' (settori=' . implode(',', $settori) . ", tipo={$tipo}, citta={$citta}, raggio={$raggio_km}, workload={$workload})";

            // Step 3: Save search to history.
            \local_ftm_jobsearch\job_manager::save_search($USER->id, [
                'mansione' => $mansione,
                'settore' => implode(',', $settori),
                'tipo' => $tipo,
                'citta' => $citta,
                'raggio_km' => $raggio_km,
            ], count($offers));

            // Step 4: CV matching with SESSION cache for stability.
            $has_matching = false;
            $match_map = [];

            if (!empty(trim($cv_text)) && mb_strlen($cv_text) > 30 && !empty($offers)) {
                $has_matching = true;

                // Cache key: hash of CV + sorted offer IDs.
                $offer_ids = array_map(function($o) { return $o->id; }, $offers);
                sort($offer_ids);
                $cache_key = 'jobsearch_match_' . md5(mb_substr($cv_text, 0, 2000) . '|' . implode(',', $offer_ids));

                if (isset($SESSION->$cache_key)) {
                    $match_map = $SESSION->$cache_key;
                    $debug['matching'] = 'Da cache sessione (' . count($match_map) . ' match)';
                } else {
                    $match_map = \local_ftm_jobsearch\ai_scraper::match_cv_to_offers($cv_text, $offers);
                    $SESSION->$cache_key = $match_map;
                    $debug['matching'] = 'Calcolato AI (' . count($match_map) . ' match, salvato in sessione)';
                }
            }

            // Format offers for frontend.
            $formatted = [];
            foreach ($offers as $o) {
                // Prefer publication date over scraping date for display.
                $pub = !empty($o->data_pubblicazione) && $o->data_pubblicazione !== 'null'
                    ? trim($o->data_pubblicazione) : null;
                if ($pub) {
                    $pub_ts = strtotime($pub);
                    $display_date = $pub_ts ? date('d/m/Y', $pub_ts) : $pub;
                    $is_pub_date = true;
                } else {
                    $display_date = userdate($o->data_scraping, '%d/%m/%Y');
                    $is_pub_date = false;
                }

                $entry = [
                    'id'           => $o->id,
                    'titolo'       => $o->titolo,
                    'azienda'      => $o->azienda ?: '',
                    'citta'        => $o->citta ?: 'Ticino',
                    'tipo'         => $o->tipo_lavoro ?: '',
                    'fonte'        => $o->fonte,
                    'url'          => $o->url,
                    'data'         => $display_date,
                    'is_pub_date'  => $is_pub_date,
                    'workload'     => $o->tipo_lavoro ?: '',
                    'distanza'     => isset($o->distanza_km) ? $o->distanza_km : null,
                    'match_pct'    => null,
                    'match_reason' => null,
                ];
                if ($has_matching && isset($match_map[$o->id])) {
                    $entry['match_pct'] = $match_map[$o->id]['pct'];
                    $entry['match_reason'] = $match_map[$o->id]['reason'];
                }
                $formatted[] = $entry;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'offers' => $formatted,
                    'total' => count($formatted),
                    'from_cache' => $scrape_result['from_cache'],
                    'scraped_sites' => $scrape_result['scraped_sites'] ?? 0,
                    'has_matching' => $has_matching,
                    'debug' => $debug,
                ],
            ]);
            break;

        case 'stats':
            $stats = \local_ftm_jobsearch\job_manager::count_by_sector();
            $data = [];
            foreach ($stats as $s) {
                $data[$s->settore] = (int)$s->total;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
