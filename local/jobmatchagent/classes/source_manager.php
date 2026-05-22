<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Orchestrates fetch from configured sources and triggers matching.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

use local_jobmatchagent\source\rss_fetcher;

defined('MOODLE_INTERNAL') || die();

// Safety net: explicitly require rss_fetcher in case autoloader cache is stale.
if (!class_exists('\local_jobmatchagent\source\rss_fetcher')) {
    $rssfetcherpath = __DIR__ . '/source/rss_fetcher.php';
    if (file_exists($rssfetcherpath)) {
        require_once($rssfetcherpath);
    }
}

class source_manager {

    /**
     * Run fetch on all enabled RSS sources (scraper-type sources are excluded —
     * they are handled by run_ai_scraping_for_all_students()).
     *
     * @return array stats: sources_run, sources_total, offers_added, matches_created, errors
     */
    public static function run_all() {
        global $DB;

        $sources = $DB->get_records('local_jobmatch_sources', ['enabled' => 1, 'type' => 'rss']);
        $totals = [
            'sources_run' => 0,
            'sources_total' => count($sources),
            'offers_added' => 0,
            'matches_created' => 0,
            'errors' => [],
            'per_source' => [],
        ];

        foreach ($sources as $source) {
            $r = self::run_source($source);
            $totals['sources_run']++;
            $totals['offers_added'] += $r['offers_added'];
            $totals['matches_created'] += $r['matches_created'];
            if (!empty($r['error'])) {
                $totals['errors'][$source->name] = $r['error'];
            }
            $totals['per_source'][$source->name] = $r;
        }

        return $totals;
    }

    /**
     * Run fetch on a single source.
     *
     * @param object $source Row from local_jobmatch_sources
     * @return array ['offers_added' => int, 'matches_created' => int, 'error' => string|null]
     */
    public static function run_source($source) {
        global $DB;

        $result = ['offers_added' => 0, 'matches_created' => 0, 'error' => null];

        try {
            if ($source->type !== 'rss') {
                $result['error'] = 'Tipo non supportato in questa versione: ' . $source->type;
                return $result;
            }

            $config = json_decode($source->config ?: '{}', true) ?: [];
            $url = $config['url'] ?? '';
            if (empty($url)) {
                $result['error'] = 'URL non configurato';
                return $result;
            }

            $items = rss_fetcher::fetch($url, 30);

            foreach ($items as $item) {
                if (empty($item['title'])) {
                    continue;
                }

                $fingerprint = match_engine::offer_fingerprint(
                    $item['title'],
                    '',
                    $item['link'] ?? '',
                    $item['description'] ?? ''
                );

                if ($DB->record_exists('local_jobmatch_offers', ['fingerprint' => $fingerprint])) {
                    continue;
                }

                $parsedtext = $item['description'] ?? '';
                if (trim($parsedtext) === '') {
                    $parsedtext = $item['title'];
                }

                $offer = (object) [
                    'source_id' => $source->id,
                    'external_id' => substr($item['guid'] ?? '', 0, 255) ?: null,
                    'url' => substr($item['link'] ?? '', 0, 500) ?: null,
                    'title' => substr($item['title'], 0, 255),
                    'company' => null,
                    'location' => null,
                    'location_lat' => null,
                    'location_lng' => null,
                    'company_size' => 'U',
                    'work_schedule' => 'unknown',
                    'raw_html' => null,
                    'parsed_text' => $parsedtext,
                    'fingerprint' => $fingerprint,
                    'published_at' => $item['pub_date'] ?? null,
                    'expires_at' => null,
                    'status' => 'active',
                    'timecreated' => time(),
                ];
                $offerid = $DB->insert_record('local_jobmatch_offers', $offer);
                $result['offers_added']++;

                // Match this new offer against all active students.
                $result['matches_created'] += match_engine::match_offer_to_all_active_students($offerid);
            }

            // Update last_fetch.
            $DB->update_record('local_jobmatch_sources', (object) [
                'id' => $source->id,
                'last_fetch' => time(),
                'last_error' => null,
                'timemodified' => time(),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            $DB->update_record('local_jobmatch_sources', (object) [
                'id' => $source->id,
                'last_error' => substr($e->getMessage(), 0, 1000),
                'timemodified' => time(),
            ]);
        }

        return $result;
    }

    /**
     * Ensure default RSS sources (ti.ch, admin.ch, Indeed Ticino) exist in DB.
     * Safe to call multiple times — uses INSERT IGNORE via record_exists check.
     */
    public static function ensure_default_sources() {
        global $DB;

        // Indeed RSS base URL for Canton Ticino (one feed per keyword, sorted by date).
        $indeedBase = 'https://ch.indeed.com/rss?l=Canton+Ticino&sort=date&radius=100&q=';

        $defaults = [
            // --- Fonti istituzionali CH ---
            [
                'name' => 'Canton Ticino — Posti di lavoro (RSS)',
                'url'  => 'https://www4.ti.ch/can/rss/posti-di-lavoro/',
            ],
            [
                'name' => 'Confederazione CH — Posti vacanti (RSS)',
                'url'  => 'https://www.stelle.admin.ch/stellenangebote/feeds/rss.xml',
            ],
            // --- Indeed Ticino — offerte generali ---
            [
                'name' => 'Indeed Ticino — operaio',
                'url'  => $indeedBase . 'operaio',
            ],
            [
                'name' => 'Indeed Ticino — tecnico',
                'url'  => $indeedBase . 'tecnico',
            ],
            [
                'name' => 'Indeed Ticino — addetto produzione',
                'url'  => $indeedBase . 'addetto+produzione',
            ],
            [
                'name' => 'Indeed Ticino — montatore',
                'url'  => $indeedBase . 'montatore',
            ],
            [
                'name' => 'Indeed Ticino — manutentore',
                'url'  => $indeedBase . 'manutentore',
            ],
            [
                'name' => 'Indeed Ticino — elettricista',
                'url'  => $indeedBase . 'elettricista',
            ],
            [
                'name' => 'Indeed Ticino — meccanico',
                'url'  => $indeedBase . 'meccanico',
            ],
            [
                'name' => 'Indeed Ticino — logistica magazzino',
                'url'  => $indeedBase . 'logistica+magazzino',
            ],
            [
                'name' => 'Indeed Ticino — automazione',
                'url'  => $indeedBase . 'automazione',
            ],
            [
                'name' => 'Indeed Ticino — elettromeccanico',
                'url'  => $indeedBase . 'elettromeccanico',
            ],
            [
                'name' => 'Indeed Ticino — carpentiere',
                'url'  => $indeedBase . 'carpentiere',
            ],
            [
                'name' => 'Indeed Ticino — saldatore',
                'url'  => $indeedBase . 'saldatore',
            ],
            [
                'name' => 'Indeed Ticino — autista',
                'url'  => $indeedBase . 'autista',
            ],
            [
                'name' => 'Indeed Ticino — chimico farmaceutico',
                'url'  => $indeedBase . 'chimico+farmaceutico',
            ],
        ];

        foreach ($defaults as $d) {
            if (!$DB->record_exists('local_jobmatch_sources', ['name' => $d['name']])) {
                $DB->insert_record('local_jobmatch_sources', (object)[
                    'name'         => $d['name'],
                    'type'         => 'rss',
                    'config'       => json_encode(['url' => $d['url']]),
                    'enabled'      => 1,
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ]);
            }
        }
    }

    /**
     * Run AI-powered scraping (jobs.ch / job-room.ch / carriera.ch) for all
     * active students, deduplicated by (sector, mansione) combo.
     * Uses local_ftm_jobsearch's ai_scraper.
     *
     * @param bool $force If true, bypasses ftm_jobsearch's 24h cache.
     * @return array stats
     */
    public static function run_ai_scraping_for_all_students($force = false) {
        global $DB;

        $totals = [
            'available' => self::is_ftm_jobsearch_available(),
            'students_processed' => 0,
            'sectors_scraped' => [],
            'sectors_cached' => [],
            'sectors_failed' => [],
            'offers_imported' => 0,
            'matches_created' => 0,
            'errors' => [],
        ];

        if (!$totals['available']) {
            $totals['errors']['ftm_jobsearch'] = 'Plugin local_ftm_jobsearch non installato.';
            return $totals;
        }

        $filterslist = $DB->get_records('local_jobmatch_student_filters', ['active' => 1]);
        $seen = [];

        foreach ($filterslist as $f) {
            $activities = !empty($f->desired_activities) ? json_decode($f->desired_activities, true) : [];
            $hasactivities = is_array($activities) && !empty($activities);

            // Se ci sono attivita desiderate, scrappa con settore detettato DALL'AI dal CV
            // (replica logica jobsearch detect_sectors_from_cv).
            // Se non ci sono, usa il settore primario FTM dello studente.
            if ($hasactivities) {
                // Usa CV per dedurre i settori giusti (puo essere "ristorazione" via mansione, etc).
                $cvres = matcher::resolve_cv($f->userid, $f);
                $sectorstoscrape = [];
                if (!empty($cvres['text']) && class_exists('\local_ftm_jobsearch\ai_scraper')) {
                    try {
                        $sectorstoscrape = \local_ftm_jobsearch\ai_scraper::detect_sectors_from_cv($cvres['text']);
                    } catch (\Throwable $e) {
                        // Fallback al settore primario.
                        $sectorstoscrape = matcher::get_student_sectors($f->userid);
                    }
                }
                if (empty($sectorstoscrape)) {
                    $sectorstoscrape = matcher::get_student_sectors($f->userid);
                }
                if (empty($sectorstoscrape)) {
                    continue;
                }

                // Per ogni attivita, scrappa su CIASCUN settore detettato (jobs.ch search query = mansione).
                $combos = [];
                foreach ($sectorstoscrape as $sec) {
                    foreach ($activities as $act) {
                        $combos[] = [$sec, $act];
                    }
                }
            } else {
                // Nessuna attivita specificata: prova a derivare query specifiche dal CV.
                $sectors = matcher::get_student_sectors($f->userid);
                if (empty($sectors)) {
                    continue;
                }

                $cv_queries = [];
                $cvres = matcher::resolve_cv($f->userid, $f);
                if (!empty($cvres['text']) && class_exists('\local_ftm_jobsearch\ai_scraper')) {
                    try {
                        $cv_queries = \local_ftm_jobsearch\ai_scraper::extract_search_queries_from_cv(
                            $cvres['text'], $sectors[0]
                        );
                    } catch (\Throwable $e) {
                        // Fallback al metodo precedente.
                    }
                }

                if (!empty($cv_queries)) {
                    // Query personalizzate dal CV × tutti i settori rilevanti.
                    $combos = [];
                    foreach ($sectors as $sec) {
                        foreach ($cv_queries as $q) {
                            $combos[] = [$sec, $q];
                        }
                    }
                } else {
                    $combos = [[$sectors[0], '']];
                }
            }

            foreach ($combos as [$sector, $mansione]) {
                $key = $sector . '|' . strtolower(trim($mansione));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                try {
                    $r = \local_ftm_jobsearch\ai_scraper::scrape_sector($sector, $mansione, $force);
                    if (!empty($r['from_cache'])) {
                        $totals['sectors_cached'][] = $key;
                    } else {
                        $totals['sectors_scraped'][] = $key . ' (' . (int)($r['saved'] ?? 0) . ' nuovi)';
                    }
                } catch (\Throwable $e) {
                    $totals['sectors_failed'][] = $key;
                    $totals['errors'][$key] = $e->getMessage();
                }
            }

            $totals['students_processed']++;
        }

        // Broad Ticino scrape — runs regardless of student sectors.
        // Ensures catalog always has recent generic Ticino offers.
        if (class_exists('\local_ftm_jobsearch\ai_scraper')) {
            try {
                \local_ftm_jobsearch\ai_scraper::scrape_broad_ticino($force);
            } catch (\Throwable $e) {
                $totals['errors']['broad_ticino'] = $e->getMessage();
            }
        }

        // Import all jobsearch offers into our catalog (regardless of cache state).
        $imp = self::import_jobsearch_offers();
        $totals['offers_imported'] = $imp['offers_added'];
        $totals['matches_created'] = $imp['matches_created'];

        return $totals;
    }

    /**
     * Run AI-powered scraping for a SINGLE student (called from wizard).
     * Same logic as run_ai_scraping_for_all_students() but for one userid only.
     *
     * @param int  $studentid
     * @param bool $force Bypass 24h cache
     * @return array stats
     */
    public static function run_ai_scraping_for_student($studentid, $force = false) {
        global $DB;

        $totals = [
            'available' => self::is_ftm_jobsearch_available(),
            'offers_imported' => 0,
            'matches_created' => 0,
            'errors' => [],
        ];

        if (!$totals['available']) {
            return $totals;
        }

        $f = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid, 'active' => 1]);
        if (!$f) {
            // No active filters: still run broad Ticino scrape.
            if (class_exists('\local_ftm_jobsearch\ai_scraper')) {
                try {
                    \local_ftm_jobsearch\ai_scraper::scrape_broad_ticino($force);
                } catch (\Throwable $e) {
                    $totals['errors']['broad_ticino'] = $e->getMessage();
                }
            }
            $imp = self::import_jobsearch_offers();
            $totals['offers_imported'] = $imp['offers_added'];
            return $totals;
        }

        $activities = !empty($f->desired_activities) ? json_decode($f->desired_activities, true) : [];
        $hasactivities = is_array($activities) && !empty($activities);
        $combos = [];

        if ($hasactivities) {
            $cvres = matcher::resolve_cv($f->userid, $f);
            $sectorstoscrape = [];
            if (!empty($cvres['text']) && class_exists('\local_ftm_jobsearch\ai_scraper')) {
                try {
                    $sectorstoscrape = \local_ftm_jobsearch\ai_scraper::detect_sectors_from_cv($cvres['text']);
                } catch (\Throwable $e) {
                    $sectorstoscrape = matcher::get_student_sectors($f->userid);
                }
            }
            if (empty($sectorstoscrape)) {
                $sectorstoscrape = matcher::get_student_sectors($f->userid);
            }
            foreach ($sectorstoscrape as $sec) {
                foreach ($activities as $act) {
                    $combos[] = [$sec, $act];
                }
            }
        } else {
            $sectors = matcher::get_student_sectors($f->userid);
            $cv_queries = [];
            $cvres = matcher::resolve_cv($f->userid, $f);
            if (!empty($cvres['text']) && class_exists('\local_ftm_jobsearch\ai_scraper')) {
                try {
                    $cv_queries = \local_ftm_jobsearch\ai_scraper::extract_search_queries_from_cv(
                        $cvres['text'], $sectors[0] ?? ''
                    );
                } catch (\Throwable $e) {
                    // Use sector only.
                }
            }
            if (!empty($cv_queries)) {
                foreach ($sectors as $sec) {
                    foreach ($cv_queries as $q) {
                        $combos[] = [$sec, $q];
                    }
                }
            } else if (!empty($sectors)) {
                $combos = [[$sectors[0], '']];
            }
        }

        foreach ($combos as [$sector, $mansione]) {
            try {
                \local_ftm_jobsearch\ai_scraper::scrape_sector($sector, $mansione, $force);
            } catch (\Throwable $e) {
                $totals['errors'][$sector . '|' . $mansione] = $e->getMessage();
            }
        }

        // Always run broad Ticino.
        if (class_exists('\local_ftm_jobsearch\ai_scraper')) {
            try {
                \local_ftm_jobsearch\ai_scraper::scrape_broad_ticino($force);
            } catch (\Throwable $e) {
                $totals['errors']['broad_ticino'] = $e->getMessage();
            }
        }

        $imp = self::import_jobsearch_offers();
        $totals['offers_imported'] = $imp['offers_added'];
        $totals['matches_created'] = $imp['matches_created'];

        return $totals;
    }

    /**
     * Import offers from local_ftm_jobsearch_offers into local_jobmatch_offers.
     * Skips duplicates via fingerprint.
     *
     * @return array ['offers_added', 'matches_created']
     */
    public static function import_jobsearch_offers() {
        global $DB;

        $result = ['offers_added' => 0, 'matches_created' => 0];

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_ftm_jobsearch_offers')) {
            return $result;
        }

        // Ensure a scraper source row exists.
        $sourceid = $DB->get_field('local_jobmatch_sources', 'id', ['type' => 'scraper']);
        if (!$sourceid) {
            $sourceid = $DB->insert_record('local_jobmatch_sources', (object) [
                'name' => 'AI Scraper (jobs.ch + job-room + carriera)',
                'type' => 'scraper',
                'config' => null,
                'enabled' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $rows = $DB->get_records('local_ftm_jobsearch_offers', ['attivo' => 1]);

        foreach ($rows as $jo) {
            $title = $jo->titolo;
            $company = $jo->azienda ?? '';
            $url = $jo->url;

            // Primary dedup: same URL = same job, regardless of sector scraped.
            if (!empty($url) && $DB->record_exists('local_jobmatch_offers', ['url' => substr($url, 0, 500)])) {
                continue;
            }

            $textparts = [];
            if (!empty($company)) {
                $textparts[] = 'Azienda: ' . $company;
            }
            if (!empty($jo->citta)) {
                $textparts[] = 'Localita: ' . $jo->citta;
            }
            // NOTE: settore NOT included in text — same job scraped under multiple sectors
            // would produce different fingerprints and be imported as duplicates.
            if (!empty($jo->descrizione)) {
                $textparts[] = $jo->descrizione;
            }
            if (!empty($jo->fonte)) {
                $textparts[] = 'Fonte: ' . $jo->fonte;
            }
            $text = implode("\n", $textparts);
            if (trim($text) === '') {
                $text = $title;
            }

            $fp = match_engine::offer_fingerprint($title, $company, $url, $text);
            if ($DB->record_exists('local_jobmatch_offers', ['fingerprint' => $fp])) {
                continue;
            }

            $schedule = self::map_tipo_lavoro($jo->tipo_lavoro ?? '');
            $publishedat = null;
            if (!empty($jo->data_pubblicazione)) {
                $ts = strtotime($jo->data_pubblicazione);
                if ($ts !== false) {
                    $publishedat = $ts;
                }
            }

            $offer = (object) [
                'source_id' => $sourceid,
                'external_id' => 'jobsearch:' . $jo->id,
                'url' => substr($url, 0, 500),
                'title' => substr($title, 0, 255),
                'company' => $company ? substr($company, 0, 255) : null,
                'location' => !empty($jo->citta) ? substr($jo->citta, 0, 255) : null,
                'location_lat' => isset($jo->lat) ? (float) $jo->lat : null,
                'location_lng' => isset($jo->lng) ? (float) $jo->lng : null,
                'company_size' => 'U',
                'work_schedule' => $schedule,
                'raw_html' => null,
                'parsed_text' => $text,
                'fingerprint' => $fp,
                'published_at' => $publishedat,
                'expires_at' => null,
                'status' => 'active',
                'timecreated' => time(),
            ];
            $offerid = $DB->insert_record('local_jobmatch_offers', $offer);
            $result['offers_added']++;

            $result['matches_created'] += match_engine::match_offer_to_all_active_students($offerid);
        }

        return $result;
    }

    /**
     * Map ftm_jobsearch's tipo_lavoro to our work_schedule taxonomy.
     *
     * @param string $tipo
     * @return string
     */
    private static function map_tipo_lavoro($tipo) {
        if (empty($tipo)) {
            return 'unknown';
        }
        $t = strtolower(trim($tipo));
        if (strpos($t, 'fulltime') !== false || strpos($t, 'tempo pieno') !== false || strpos($t, 'full') !== false) {
            return 'fulltime';
        }
        if (strpos($t, 'parttime') !== false || strpos($t, 'parziale') !== false || strpos($t, 'part') !== false) {
            return 'parttime';
        }
        if (strpos($t, 'turn') !== false || strpos($t, 'shift') !== false) {
            return 'shifts';
        }
        if (strpos($t, 'flex') !== false) {
            return 'flex';
        }
        return 'unknown';
    }

    /**
     * @return bool true if local_ftm_jobsearch plugin is installed
     */
    public static function is_ftm_jobsearch_available() {
        global $CFG;
        return file_exists($CFG->dirroot . '/local/ftm_jobsearch/classes/ai_scraper.php')
            && class_exists('\local_ftm_jobsearch\ai_scraper');
    }
}
