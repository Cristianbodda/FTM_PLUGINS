<?php
/**
 * Job search manager: query DB, geo-filter, save search history.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_jobsearch;

defined('MOODLE_INTERNAL') || die();

class job_manager {

    /**
     * Search offers in DB with optional geo-filtering.
     *
     * @param string $settore   FTM sector code
     * @param string $tipo      Job type filter (empty = all)
     * @param string $citta     City name for geo-filter (empty = no geo)
     * @param int    $raggio_km Radius in km (10, 30, 100)
     * @param string $mansione  Free-text keyword filter on title
     * @return array of offer objects with optional distanza_km field
     */
    public static function search(
        string $settore,
        string $tipo = '',
        string $citta = '',
        int $raggio_km = 30,
        string $mansione = ''
    ): array {
        global $DB;

        $params = ['attivo' => 1, 'settore' => $settore];
        $where = 'attivo = :attivo AND settore = :settore';

        if (!empty($tipo)) {
            $where .= ' AND tipo_lavoro = :tipo';
            $params['tipo'] = $tipo;
        }

        // Note: mansione is NOT used as DB filter — it's already used as the
        // search keyword by the AI scraper when fetching from job sites.
        // Filtering again by LIKE on title would be too restrictive
        // (e.g., "operaio di produzione" won't match "meccanico CNC").

        $offers = $DB->get_records_select(
            'local_ftm_jobsearch_offers',
            $where,
            $params,
            'data_scraping DESC',
            '*',
            0,
            200
        );

        // Geo-filter if city provided.
        if (!empty($citta) && $raggio_km > 0) {
            $coords = self::get_city_coords($citta);
            if ($coords) {
                $offers = self::filter_by_radius($offers, $coords['lat'], $coords['lng'], $raggio_km);
            }
        }

        return array_values($offers);
    }

    /**
     * Search across multiple sectors with workload filter.
     *
     * @param array  $settori   Array of sector codes
     * @param string $tipo      Job type filter
     * @param string $citta     City for geo-filter
     * @param int    $raggio_km Radius in km
     * @param string $workload  Workload filter: '100', '80', '50', '0', or '' (all)
     * @return array
     */
    public static function search_multi(
        array $settori,
        string $tipo = '',
        string $citta = '',
        int $raggio_km = 30,
        string $workload = ''
    ): array {
        global $DB;

        if (empty($settori)) {
            return [];
        }

        $max_age_days = (int)(get_config('local_ftm_jobsearch', 'max_offer_age_days') ?: 90);

        // Deactivate offers not re-scraped within max_age_days + 30 day buffer.
        $scraping_cutoff = time() - (($max_age_days + 30) * 86400);
        $DB->execute(
            'UPDATE {local_ftm_jobsearch_offers} SET attivo = 0 WHERE attivo = 1 AND data_scraping < ?',
            [$scraping_cutoff]
        );

        // Also deactivate offers with a known publication date older than max_age_days.
        $pub_cutoff_old = date('Y-m-d', strtotime("-{$max_age_days} days"));
        $DB->execute(
            "UPDATE {local_ftm_jobsearch_offers} SET attivo = 0 WHERE attivo = 1 AND data_pubblicazione IS NOT NULL AND data_pubblicazione != '' AND data_pubblicazione < ?",
            [$pub_cutoff_old]
        );

        list($sql_in, $params) = $DB->get_in_or_equal($settori, SQL_PARAMS_NAMED, 'sec');
        $where = "attivo = :attivo AND settore {$sql_in}";
        $params['attivo'] = 1;

        if (!empty($tipo)) {
            $where .= ' AND tipo_lavoro = :tipo';
            $params['tipo'] = $tipo;
        }

        $offers = $DB->get_records_select(
            'local_ftm_jobsearch_offers',
            $where,
            $params,
            'data_scraping DESC',
            '*',
            0,
            300
        );

        // Workload filter (parsed from tipo_lavoro or title text).
        if (!empty($workload)) {
            $offers = self::filter_by_workload($offers, $workload);
        }

        // Geo-filter if city provided.
        if (!empty($citta) && $raggio_km > 0) {
            $coords = self::get_city_coords($citta);
            if ($coords) {
                $offers = self::filter_by_radius($offers, $coords['lat'], $coords['lng'], $raggio_km);
            }
        }

        return array_values($offers);
    }

    /**
     * Filter offers by workload percentage (from title/tipo text).
     *
     * @param string $target '100' = 100%, '80' = 80-100%, '50' = 50-80%, '0' = under 50%
     */
    private static function filter_by_workload(array $offers, string $target): array {
        $result = [];
        foreach ($offers as $o) {
            $text = ($o->titolo ?? '') . ' ' . ($o->tipo_lavoro ?? '');
            // Try to extract percentage from text (e.g., "80 - 100%", "100%", "50%").
            $pct = null;
            if (preg_match('/(\d{2,3})\s*[-–]\s*(\d{2,3})\s*%/', $text, $m)) {
                $pct = (int)$m[2]; // Use upper bound.
            } elseif (preg_match('/(\d{2,3})\s*%/', $text, $m)) {
                $pct = (int)$m[1];
            }

            // If no percentage found, include by default (don't filter out unknowns).
            if ($pct === null) {
                $result[] = $o;
                continue;
            }

            switch ($target) {
                case '100': if ($pct >= 100) $result[] = $o; break;
                case '80':  if ($pct >= 80)  $result[] = $o; break;
                case '50':  if ($pct >= 50 && $pct < 80) $result[] = $o; break;
                case '0':   if ($pct < 50)   $result[] = $o; break;
                default:    $result[] = $o;
            }
        }
        return $result;
    }

    /**
     * Haversine formula: distance in km between two GPS points.
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $R = 6371;
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        $a = sin($dlat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }

    /**
     * Filter offers by radius from a point.
     */
    private static function filter_by_radius(array $offers, float $lat, float $lng, int $radius): array {
        $result = [];
        foreach ($offers as $offer) {
            if (empty($offer->lat) || empty($offer->lng)) {
                $offer->distanza_km = null;
                $result[] = $offer;
                continue;
            }
            $dist = self::haversine($lat, $lng, (float)$offer->lat, (float)$offer->lng);
            if ($dist <= $radius) {
                $offer->distanza_km = round($dist, 1);
                $result[] = $offer;
            }
        }

        usort($result, function ($a, $b) {
            $da = $a->distanza_km ?? 9999;
            $db = $b->distanza_km ?? 9999;
            return $da <=> $db;
        });

        return $result;
    }

    /**
     * Get GPS coordinates for a Ticino city.
     */
    public static function get_city_coords(string $nome): ?array {
        global $DB;
        $city = $DB->get_record('local_ftm_jobsearch_cities', ['nome' => $nome], 'lat, lng');
        return $city ? ['lat' => (float)$city->lat, 'lng' => (float)$city->lng] : null;
    }

    /**
     * Get all cities for the search form dropdown.
     */
    public static function get_cities(): array {
        global $DB;
        return array_values($DB->get_records('local_ftm_jobsearch_cities', null, 'nome ASC', 'id, nome'));
    }

    /**
     * Save a search to history.
     */
    public static function save_search(int $userid, array $params, int $n_results): int {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->mansione = $params['mansione'] ?? null;
        $record->settore = $params['settore'] ?? null;
        $record->tipo_lavoro = $params['tipo'] ?? null;
        $record->citta = $params['citta'] ?? null;
        $record->raggio_km = $params['raggio_km'] ?? 30;
        $record->n_risultati = $n_results;
        $record->timecreated = time();

        return $DB->insert_record('local_ftm_jobsearch_searches', $record);
    }

    /**
     * Count active offers per sector (for dashboard stats).
     */
    public static function count_by_sector(): array {
        global $DB;
        $sql = "SELECT settore, COUNT(*) AS total
                FROM {local_ftm_jobsearch_offers}
                WHERE attivo = 1
                GROUP BY settore
                ORDER BY total DESC";
        return $DB->get_records_sql($sql);
    }
}
