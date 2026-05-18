<?php
/**
 * AI-powered job scraper. Fetches job listing pages and uses GPT to extract
 * structured data instead of brittle CSS selectors.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_jobsearch;

defined('MOODLE_INTERNAL') || die();

class ai_scraper {

    /** @var string[] Search URLs per site. {QUERY} is replaced at runtime. Sites without {QUERY} are static and scraped once per sector run. */
    private const SITES = [
        // jobs.ch: keyword search sorted by date (newest first).
        'jobs.ch'        => 'https://www.jobs.ch/en/vacancies/?region=4&term={QUERY}&sort=date',
        // randstad.ch: keyword search sorted by date.
        'randstad.ch'    => 'https://www.randstad.ch/it/lavoro/q-{QUERY}/re-ticino/?sort=date',
        // jobup.ch: Ticino region (id=25).
        'jobup.ch'       => 'https://www.jobup.ch/it/impieghi/?region=25&term={QUERY}',
        // jobscout24.ch: Ticino filter in URL path.
        'jobscout24.ch'  => 'https://www.jobscout24.ch/it/jobs/{QUERY}/ticino',
        // carriera.ch: static page (no keyword filter), all offers mixed. Scraped once per sector run.
        'carriera.ch'    => 'https://www.carriera.ch/cgi-bin/annunci_offerte_lavoro.cgi',
    ];

    /** @var string[] Keywords per FTM sector — first keyword is used as search query. */
    private const SECTOR_KEYWORDS = [
        'MECCANICA'        => ['meccanico', 'CNC', 'fresatore', 'tornitore'],
        'AUTOMOBILE'       => ['meccanico auto', 'carrozziere', 'autofficina'],
        'CHIMFARM'         => ['operatore chimico', 'farmaceutico', 'laboratorio'],
        'LOGISTICA'        => ['magazziniere', 'logistica', 'mulettista'],
        'ELETTRICITA'      => ['elettricista', 'impianti elettrici', 'quadrista'],
        'AUTOMAZIONE'      => ['automazione', 'PLC', 'meccatronico'],
        'METALCOSTRUZIONE' => ['saldatore', 'carpentiere metallico', 'lamierista'],
    ];

    /**
     * Scrape job offers for a sector. Uses cache if fresh enough.
     *
     * @param string $settore  FTM sector code
     * @param string $mansione Optional free-text job title to narrow search
     * @return array ['offers' => [...], 'from_cache' => bool, 'scraped_sites' => int]
     */
    public static function scrape_sector(string $settore, string $mansione = '', bool $force = false): array {
        global $DB;

        if (!$force) {
            $cache_hours = (int)(get_config('local_ftm_jobsearch', 'cache_hours') ?: 24);
            $cache_cutoff = time() - ($cache_hours * 3600);

            // Check cache: do we have recent offers for this sector?
            $cached = $DB->count_records_select(
                'local_ftm_jobsearch_offers',
                'settore = :settore AND attivo = 1 AND data_scraping > :cutoff',
                ['settore' => $settore, 'cutoff' => $cache_cutoff]
            );

            if ($cached > 0) {
                return ['offers' => [], 'from_cache' => true, 'scraped_sites' => 0];
            }
        }

        // No fresh cache — scrape now.
        $apikey = self::get_api_key();
        if (empty($apikey)) {
            throw new \Exception('Chiave API OpenAI non configurata (ne in Job Search ne in JobAIDA).');
        }

        $model = get_config('local_ftm_jobsearch', 'openai_model') ?: 'gpt-4o-mini';

        // If caller specifies a mansione use only that; otherwise expand over ALL sector keywords for higher volume.
        if (!empty($mansione)) {
            $queries = [$mansione];
        } else {
            $queries = self::SECTOR_KEYWORDS[$settore] ?? [$settore];
        }

        $all_offers  = [];
        $sites_scraped = 0;
        $static_done = []; // Sites without {QUERY}: scraped once per sector run, not once per keyword.

        foreach ($queries as $query) {
            foreach (self::SITES as $site_name => $url_template) {
                $is_static = strpos($url_template, '{QUERY}') === false;
                if ($is_static) {
                    if (in_array($site_name, $static_done)) {
                        continue;
                    }
                    $static_done[] = $site_name;
                }

                try {
                    $offers = self::scrape_site($site_name, $url_template, $query, $settore, $apikey, $model);
                    $all_offers = array_merge($all_offers, $offers);
                    $sites_scraped++;
                } catch (\Exception $e) {
                    debugging("ftm_jobsearch: errore scraping {$site_name} query '{$query}': " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        // Save to DB with dedup by url_hash — safe to call with duplicates across keywords.
        $saved = self::save_offers($all_offers, $settore);

        return ['offers' => $all_offers, 'from_cache' => false, 'scraped_sites' => $sites_scraped, 'saved' => $saved];
    }

    /**
     * Detect relevant FTM sectors from CV text via AI.
     * Used when user doesn't select a sector but provides a CV.
     *
     * @param string $cv_text
     * @return array of sector codes (e.g., ['MECCANICA', 'AUTOMAZIONE'])
     */
    public static function detect_sectors_from_cv(string $cv_text): array {
        $apikey = self::get_api_key();
        if (empty($apikey)) {
            return ['MECCANICA']; // Safe fallback.
        }

        $cv_short = mb_substr(trim($cv_text), 0, 2000);
        $sectors_list = implode(', ', array_keys(self::SECTOR_KEYWORDS));

        $prompt = "Analizza questo CV e indica i settori professionali piu rilevanti.\n\n"
            . "Settori disponibili: {$sectors_list}\n\n"
            . "=== CV ===\n{$cv_short}\n\n"
            . "Rispondi SOLO con un JSON array dei codici settore rilevanti (max 3). Es: [\"MECCANICA\",\"AUTOMAZIONE\"]";

        try {
            $response = self::call_openai($apikey, 'gpt-4o-mini', [
                ['role' => 'system', 'content' => 'Analisi CV. Rispondi solo JSON array.'],
                ['role' => 'user', 'content' => $prompt],
            ], 100);

            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/', '', $response);
            $sectors = json_decode(trim($response), true);

            if (is_array($sectors) && !empty($sectors)) {
                // Validate against known sectors.
                $valid = array_keys(self::SECTOR_KEYWORDS);
                return array_values(array_intersect($sectors, $valid));
            }
        } catch (\Exception $e) {
            // Fallback.
        }

        return ['MECCANICA']; // Safe default.
    }

    /**
     * Match a CV text against a list of offers in a single AI call.
     * Returns array keyed by offer ID: [id => ['pct' => int, 'reason' => string]]
     *
     * @param string $cv_text  The CV text pasted by the user
     * @param array  $offers   Array of offer DB objects
     * @return array
     */
    public static function match_cv_to_offers(string $cv_text, array $offers): array {
        $apikey = self::get_api_key();
        if (empty($apikey)) {
            return [];
        }

        $model = get_config('local_ftm_jobsearch', 'openai_model') ?: 'gpt-4o-mini';

        // Truncate CV to keep within token limits.
        $cv_short = mb_substr(trim($cv_text), 0, 3000);

        // Build the list of offers (id, title, company, city, type + up to 600 chars of description).
        $offer_list = '';
        $count = 0;
        foreach ($offers as $o) {
            if ($count >= 30) {
                break; // Max 30 offers per batch.
            }
            $offer_list .= "ID:{$o->id} | {$o->titolo}";
            if (!empty($o->azienda)) {
                $offer_list .= " | {$o->azienda}";
            }
            if (!empty($o->citta)) {
                $offer_list .= " | {$o->citta}";
            }
            if (!empty($o->tipo_lavoro)) {
                $offer_list .= " | {$o->tipo_lavoro}";
            }
            if (!empty($o->descrizione)) {
                $offer_list .= "\n  » " . mb_substr(trim($o->descrizione), 0, 600);
            }
            $offer_list .= "\n";
            $count++;
        }

        $prompt = "Sei un esperto di selezione del personale. Analizza il CV del candidato e valuta "
            . "la compatibilita con ciascuna offerta di lavoro.\n\n"
            . "=== CV DEL CANDIDATO ===\n{$cv_short}\n\n"
            . "=== OFFERTE DI LAVORO ===\n{$offer_list}\n"
            . "=== ISTRUZIONI ===\n"
            . "Per OGNI offerta (usa il suo ID), rispondi con:\n"
            . "- pct: percentuale di compatibilita 0-100 (basata su: competenze tecniche, esperienza, settore, descrizione, localita)\n"
            . "- reason: UNA frase breve (max 15 parole) che spiega perche quel match (punti forti O gap principali)\n\n"
            . "Rispondi SOLO con JSON puro. Formato:\n"
            . "[{\"id\": 123, \"pct\": 75, \"reason\": \"Esperienza CNC diretta, manca certificazione ISO\"}]\n"
            . "Non aggiungere testo prima o dopo il JSON.";

        $messages = [
            ['role' => 'system', 'content' => 'Sei un valutatore di compatibilita CV/offerte. Rispondi solo JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        try {
            // Temperature 0 for deterministic results (same CV+offers = same scores).
            $response = self::call_openai($apikey, $model, $messages, 2000, 0.0);

            // Parse JSON.
            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/\s*```$/', '', $response);
            $matches = json_decode(trim($response), true);

            if (!is_array($matches)) {
                return [];
            }

            // Build map by offer ID.
            $result = [];
            foreach ($matches as $m) {
                if (isset($m['id']) && isset($m['pct'])) {
                    $result[(int)$m['id']] = [
                        'pct' => max(0, min(100, (int)$m['pct'])),
                        'reason' => $m['reason'] ?? '',
                    ];
                }
            }

            return $result;

        } catch (\Exception $e) {
            debugging("ftm_jobsearch: CV matching error: " . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Scrape a single site for job offers.
     */
    private static function scrape_site(
        string $site_name,
        string $url_template,
        string $query,
        string $settore,
        string $apikey,
        string $model
    ): array {
        $url = str_replace('{QUERY}', urlencode($query), $url_template);

        // Fetch HTML.
        $html = self::fetch_url($url);
        if (empty($html)) {
            return [];
        }

        // Clean HTML: keep only main content, strip scripts/styles/nav.
        $text = self::clean_html($html);
        if (mb_strlen($text) < 100) {
            return [];
        }

        // Truncate to ~15000 chars — gpt-4o-mini handles this well and recovers more offers per page.
        if (mb_strlen($text) > 15000) {
            $text = mb_substr($text, 0, 15000);
        }

        // Ask GPT to extract structured data — only recent offers.
        $max_age_days = (int)(get_config('local_ftm_jobsearch', 'max_offer_age_days') ?: 90);
        $today = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime("-{$max_age_days} days"));

        $prompt = "Sei un sistema di estrazione dati. DATA DI OGGI: {$today}.\n\n"
            . "Dalla pagina web qui sotto, estrai le offerte di lavoro RECENTI (pubblicate dopo il {$cutoff}).\n\n"
            . "Per ogni offerta estrai:\n"
            . "- titolo: il titolo del posto\n"
            . "- azienda: nome azienda (null se non presente)\n"
            . "- citta: citta del posto (null se non presente)\n"
            . "- tipo_lavoro: fulltime/parttime/stage/apprendistato/temporaneo (null se non chiaro)\n"
            . "- url: link all'offerta completa (ricostruisci URL assoluto se relativo, base: https://www.{$site_name})\n"
            . "- data_pubblicazione: data in formato YYYY-MM-DD (null se non leggibile). "
            . "Se la data indica che l'offerta e' precedente al {$cutoff}, NON includerla.\n\n"
            . "REGOLA CRITICA: escludi offerte con data_pubblicazione antecedente a {$cutoff}. "
            . "Se la data non e' presente, includi l'offerta con data_pubblicazione=null.\n"
            . "Rispondi SOLO con un JSON array. Se non trovi offerte recenti, rispondi con [].\n"
            . "Non aggiungere testo prima o dopo il JSON.\n\n"
            . "=== CONTENUTO PAGINA ({$site_name}) ===\n"
            . $text;

        $messages = [
            ['role' => 'system', 'content' => 'Estrai dati strutturati da pagine web. Rispondi solo con JSON valido.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = self::call_openai($apikey, $model, $messages, 2000);

        // Parse JSON.
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);
        $offers = json_decode(trim($response), true);

        if (!is_array($offers)) {
            return [];
        }

        // Normalize and tag with source.
        $now = time();
        foreach ($offers as &$offer) {
            $offer['fonte'] = $site_name;
            $offer['settore'] = $settore;
            $offer['data_scraping'] = $now;
            $offer['attivo'] = 1;
            $offer['url'] = $offer['url'] ?? '';
            $offer['titolo'] = $offer['titolo'] ?? 'Senza titolo';
        }
        unset($offer);

        return $offers;
    }

    /**
     * Fetch a URL via cURL.
     */
    private static function fetch_url(string $url): string {
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 400) ? ($html ?: '') : '';
    }

    /**
     * Strip HTML down to readable text. Remove scripts, styles, nav, footer, header.
     */
    private static function clean_html(string $html): string {
        // Remove scripts, styles, noscript.
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/si', '', $html);

        // Remove nav, header, footer (often site chrome, not content).
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);

        // Convert common elements to text markers.
        $html = preg_replace('/<(h[1-6])[^>]*>/i', "\n### ", $html);
        $html = preg_replace('/<\/(h[1-6])>/i', "\n", $html);
        $html = preg_replace('/<li[^>]*>/i', "\n- ", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/tr>/i', "\n", $html);

        // Preserve href for links (important for job URLs).
        $html = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>/i', ' [LINK:$1] ', $html);

        // Strip remaining tags.
        $text = strip_tags($html);

        // Normalize whitespace.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Save offers to DB with dedup by URL hash.
     * - Skips offers with a known publication date older than max_offer_age_days.
     * - Updates data_scraping for existing URLs so the cleanup timer resets.
     *
     * @return int Number of new offers saved
     */
    private static function save_offers(array $offers, string $settore): int {
        global $DB;

        $max_age_days = (int)(get_config('local_ftm_jobsearch', 'max_offer_age_days') ?: 90);
        $age_cutoff_ts = strtotime("-{$max_age_days} days");
        $saved = 0;

        foreach ($offers as $offer) {
            $url = trim($offer['url'] ?? '');
            if (empty($url) || $url === 'null') {
                continue;
            }

            // Skip offers whose publication date is known and too old.
            $pub_raw = $offer['data_pubblicazione'] ?? null;
            $pub_date = (!empty($pub_raw) && $pub_raw !== 'null') ? trim($pub_raw) : null;
            if ($pub_date) {
                $pub_ts = strtotime($pub_date);
                if ($pub_ts && $pub_ts < $age_cutoff_ts) {
                    continue;
                }
            }

            $url_hash = hash('sha256', $url);
            $now = (int)($offer['data_scraping'] ?? time());

            // If already in DB: refresh data_scraping so the cleanup timer resets.
            if ($DB->record_exists('local_ftm_jobsearch_offers', ['url_hash' => $url_hash])) {
                $DB->execute(
                    'UPDATE {local_ftm_jobsearch_offers} SET data_scraping = :now, attivo = 1 WHERE url_hash = :hash',
                    ['now' => $now, 'hash' => $url_hash]
                );
                continue;
            }

            // Geocode city if possible.
            $coords = self::geocode_city($offer['citta'] ?? '');

            $record = new \stdClass();
            $record->titolo = mb_substr($offer['titolo'] ?? 'Senza titolo', 0, 255);
            $record->azienda = mb_substr($offer['azienda'] ?? '', 0, 255) ?: null;
            $record->descrizione = $offer['descrizione'] ?? null;
            $record->settore = $settore;
            $record->tipo_lavoro = $offer['tipo_lavoro'] ?? null;
            $record->citta = mb_substr($offer['citta'] ?? '', 0, 100) ?: null;
            $record->lat = $coords ? $coords['lat'] : null;
            $record->lng = $coords ? $coords['lng'] : null;
            $record->url = $url;
            $record->fonte = mb_substr($offer['fonte'] ?? 'sconosciuto', 0, 100);
            $record->data_pubblicazione = $pub_date;
            $record->data_scraping = $now;
            $record->attivo = 1;
            $record->url_hash = $url_hash;

            try {
                $DB->insert_record('local_ftm_jobsearch_offers', $record);
                $saved++;
            } catch (\Exception $e) {
                // Duplicate or other error — skip.
            }
        }

        return $saved;
    }

    /**
     * Look up city coordinates from local_ftm_jobsearch_cities.
     */
    private static function geocode_city(string $city_name): ?array {
        global $DB;

        if (empty($city_name)) {
            return null;
        }

        // Try exact match first.
        $city = $DB->get_record('local_ftm_jobsearch_cities', ['nome' => $city_name], 'lat, lng');
        if ($city) {
            return ['lat' => (float)$city->lat, 'lng' => (float)$city->lng];
        }

        // Try partial match (e.g., "Lugano (TI)" → "Lugano").
        $clean = preg_replace('/\s*\(.*$/', '', $city_name);
        $clean = trim($clean);
        if ($clean !== $city_name) {
            $city = $DB->get_record('local_ftm_jobsearch_cities', ['nome' => $clean], 'lat, lng');
            if ($city) {
                return ['lat' => (float)$city->lat, 'lng' => (float)$city->lng];
            }
        }

        return null;
    }

    /**
     * Get API key: own config first, then fall back to JobAIDA.
     */
    private static function get_api_key(): string {
        $key = get_config('local_ftm_jobsearch', 'openai_apikey');
        if (!empty($key)) {
            return $key;
        }
        return get_config('local_jobaida', 'openai_apikey') ?: '';
    }

    /**
     * Call OpenAI chat completion. Returns the text response.
     */
    private static function call_openai(string $apikey, string $model, array $messages, int $max_tokens = 2000, float $temperature = 0.1): string {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apikey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Errore connessione OpenAI: ' . $error);
        }
        if ($httpcode !== 200) {
            $data = json_decode($response, true);
            throw new \Exception('OpenAI: ' . ($data['error']['message'] ?? "HTTP {$httpcode}"));
        }

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
