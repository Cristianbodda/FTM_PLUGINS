<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Deterministic job-student match scoring engine.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

defined('MOODLE_INTERNAL') || die();

class matcher {

    /** @var array Sector aliases for fuzzy text matching. Use FULL words to avoid
     * partial matches (e.g. "autom" used to match "automobile" — wrong!). */
    private static $sectoraliases = [
        'AUTOMOBILE' => ['automobile', 'autoveicolo', 'meccanico auto', 'carrozziere', 'autoriparatore'],
        'AUTOMAZIONE' => ['automazione', 'plc', 'robotica', 'meccatronico', 'meccatronica'],
        'CHIMFARM' => ['chimica', 'farmaceutica', 'chimfarm', 'laboratorio'],
        'ELETTRICITA' => ['elettricita', 'elettricista', 'elettronico', 'impianti elettrici', 'quadrista'],
        'LOGISTICA' => ['logistica', 'magazziniere', 'magazzino', 'spedizioniere', 'mulettista'],
        'MECCANICA' => ['meccanica', 'meccanico', 'tornitore', 'fresatore', 'cnc'],
        'METALCOSTRUZIONE' => ['metalcostruzione', 'saldatore', 'carpentiere', 'lattoniere', 'fabbro', 'lamierista'],
    ];

    /**
     * Compute deterministic score (0-100) for an offer-student pair.
     * Excludes AI-based score_experience.
     *
     * @param object $offer Row from local_jobmatch_offers
     * @param object $filters Row from local_jobmatch_student_filters
     * @param array $studentsectors Array of student sector codes (primary first)
     * @return array ['score_global', 'score_sector', 'score_distance', 'score_schedule',
     *                'score_size', 'score_activity', 'breakdown' => [...]]
     */
    public static function compute_deterministic($offer, $filters, $studentsectors) {
        $weights = self::get_weights();

        // Se il coach ha specificato attivita desiderate, IGNORA lo score_sector FTM
        // (la mansione cercata vince sul settore — replica logica jobsearch).
        $hasdesiredactivities = !empty($filters->desired_activities)
            && !empty(json_decode($filters->desired_activities, true));

        if ($hasdesiredactivities) {
            $sscore = 50; // neutro: ignora settore FTM, lascia che decida l'AI
        } else {
            $sscore = self::score_sector($offer, $studentsectors);
        }

        $dscore = self::score_distance($offer, $filters);
        $hscore = self::score_schedule($offer, $filters);
        $zscore = self::score_size($offer, $filters);
        $ascore = self::score_activity($offer, $filters);

        // Renormalize weights without experience (which is AI-based and not yet computed).
        // We assume 0% for experience at this stage, but split its weight pro-rata over the others
        // so the deterministic pre-filter is comparable to the final score.
        $detweights = [
            'sector' => $weights['sector'],
            'distance' => $weights['distance'],
            'schedule' => $weights['schedule'],
            'size' => $weights['size'],
        ];
        $detsum = array_sum($detweights);
        if ($detsum <= 0) {
            $detsum = 1;
        }

        $global = (int) round(
            ($sscore * $detweights['sector']
                + $dscore * $detweights['distance']
                + $hscore * $detweights['schedule']
                + $zscore * $detweights['size']) / $detsum
        );

        // Activity score is informational at this stage (modulates AI prompt later).
        return [
            'score_global' => $global,
            'score_sector' => $sscore,
            'score_distance' => $dscore,
            'score_schedule' => $hscore,
            'score_size' => $zscore,
            'score_activity' => $ascore,
            'breakdown' => [
                'sector' => ['value' => $sscore, 'weight' => $detweights['sector']],
                'distance' => ['value' => $dscore, 'weight' => $detweights['distance']],
                'schedule' => ['value' => $hscore, 'weight' => $detweights['schedule']],
                'size' => ['value' => $zscore, 'weight' => $detweights['size']],
                'activity' => ['value' => $ascore, 'weight' => 0], // informational
            ],
        ];
    }

    /**
     * Score sector match (0-100): 100 if primary sector found in offer text,
     * 70 if any secondary sector found, 40 if related sector via keywords, 0 otherwise.
     *
     * @param object $offer
     * @param array $studentsectors First element is primary
     * @return int
     */
    public static function score_sector($offer, $studentsectors) {
        if (empty($studentsectors)) {
            return 50; // Unknown student sector — neutral.
        }

        $haystack = strtolower(($offer->title ?? '') . ' ' . ($offer->parsed_text ?? ''));
        if (trim($haystack) === '') {
            return 0;
        }

        $primary = $studentsectors[0];
        if (self::sector_in_text($primary, $haystack)) {
            return 100;
        }

        for ($i = 1; $i < count($studentsectors); $i++) {
            if (self::sector_in_text($studentsectors[$i], $haystack)) {
                return 70;
            }
        }

        // Check for cross-sector affinity (e.g. metalcostruzione ↔ meccanica share workshop skills).
        $affinitymap = [
            'METALCOSTRUZIONE' => ['MECCANICA'],
            'MECCANICA' => ['METALCOSTRUZIONE', 'AUTOMOBILE'],
            'AUTOMOBILE' => ['MECCANICA'],
            'AUTOMAZIONE' => ['ELETTRICITA', 'MECCANICA'],
            'ELETTRICITA' => ['AUTOMAZIONE'],
        ];
        if (!empty($affinitymap[$primary])) {
            foreach ($affinitymap[$primary] as $related) {
                if (self::sector_in_text($related, $haystack)) {
                    return 40;
                }
            }
        }

        return 0;
    }

    /**
     * Check if a sector code appears in text (using its aliases).
     *
     * @param string $sector
     * @param string $haystack Lowercase text
     * @return bool
     */
    private static function sector_in_text($sector, $haystack) {
        $aliases = self::$sectoraliases[$sector] ?? [strtolower($sector)];
        foreach ($aliases as $alias) {
            if (strpos($haystack, $alias) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Score distance (0-100): 100 within max_distance_km, decays linearly to 0 at 2x max.
     * If offer or home coords missing, falls back to text match on city → 50 if found, else 30.
     *
     * @param object $offer
     * @param object $filters
     * @return int
     */
    public static function score_distance($offer, $filters) {
        $maxkm = (int) ($filters->max_distance_km ?? 30);
        if ($maxkm <= 0) {
            $maxkm = 30;
        }

        $hasoffercoords = !empty($offer->location_lat) && !empty($offer->location_lng);
        $hashomecoords = !empty($filters->home_lat) && !empty($filters->home_lng);

        if ($hasoffercoords && $hashomecoords) {
            $dist = self::haversine_km(
                (float) $filters->home_lat, (float) $filters->home_lng,
                (float) $offer->location_lat, (float) $offer->location_lng
            );
            if ($dist <= $maxkm) {
                return 100;
            }
            if ($dist >= $maxkm * 2) {
                return 0;
            }
            // Linear decay from 100 at maxkm to 0 at 2*maxkm.
            return (int) round(100 * (1 - ($dist - $maxkm) / $maxkm));
        }

        // Fallback: if home address contains a token that appears in offer location → 50.
        $home = strtolower($filters->home_address ?? '');
        $loc = strtolower($offer->location ?? '');
        if ($home !== '' && $loc !== '') {
            $hometokens = preg_split('/[\s,]+/', $home, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($hometokens as $token) {
                if (strlen($token) >= 4 && strpos($loc, $token) !== false) {
                    return 50;
                }
            }
        }

        return 30; // Unknown — give it a chance.
    }

    /**
     * Haversine distance in km between two lat/lng points.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float
     */
    public static function haversine_km($lat1, $lng1, $lat2, $lng2) {
        $r = 6371.0;
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        $a = sin($dlat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * Score work schedule (0-100): 100 if exact match, 60 if "flex" + any other,
     * 30 if no match but at least one schedule listed, else 50 (unknown).
     *
     * @param object $offer
     * @param object $filters
     * @return int
     */
    public static function score_schedule($offer, $filters) {
        $offered = strtolower($offer->work_schedule ?? '');
        if ($offered === '' || $offered === 'unknown') {
            return 50;
        }

        $allowed = array_filter(array_map('trim', explode(',', strtolower($filters->work_schedules ?? ''))));
        if (empty($allowed)) {
            return 70; // No filter set — neutral-positive.
        }

        if (in_array($offered, $allowed, true)) {
            return 100;
        }
        if (in_array('flex', $allowed, true) || $offered === 'flex') {
            return 60;
        }
        return 30;
    }

    /**
     * Score company size (0-100): 100 exact match, 60 adjacent, 0 opposite, 50 unknown.
     *
     * @param object $offer
     * @param object $filters
     * @return int
     */
    public static function score_size($offer, $filters) {
        $offered = strtoupper($offer->company_size ?? '');
        if ($offered === '' || $offered === 'U') {
            return 50;
        }

        $allowed = array_filter(array_map('trim', explode(',', strtoupper($filters->company_sizes ?? ''))));
        if (empty($allowed)) {
            return 70;
        }

        if (in_array($offered, $allowed, true)) {
            return 100;
        }

        // Adjacency: S↔M, M↔L score 60.
        $adjacent = ['S' => 'M', 'M' => 'L', 'L' => 'M'];
        if (isset($adjacent[$offered]) && in_array($adjacent[$offered], $allowed, true)) {
            return 60;
        }
        // Cross-adjacency M can also be adjacent to S.
        if ($offered === 'M' && (in_array('S', $allowed, true) || in_array('L', $allowed, true))) {
            return 60;
        }
        return 0;
    }

    /**
     * Score desired activity match (0-100): scan offer text for keywords from
     * filters.desired_activities. Returns max match across keywords.
     *
     * @param object $offer
     * @param object $filters
     * @return int
     */
    public static function score_activity($offer, $filters) {
        $raw = $filters->desired_activities ?? '';
        if (empty($raw)) {
            return 50;
        }

        $activities = json_decode($raw, true);
        if (!is_array($activities) || empty($activities)) {
            return 50;
        }

        $haystack = strtolower(($offer->title ?? '') . ' ' . ($offer->parsed_text ?? ''));
        $best = 0;
        foreach ($activities as $activity) {
            $needle = strtolower(trim($activity));
            if ($needle === '') {
                continue;
            }
            // Full phrase match → 100.
            if (strpos($haystack, $needle) !== false) {
                return 100;
            }
            // Token-based partial match.
            $tokens = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY);
            $matched = 0;
            foreach ($tokens as $tok) {
                if (strlen($tok) >= 4 && strpos($haystack, $tok) !== false) {
                    $matched++;
                }
            }
            if (count($tokens) > 0) {
                $partial = (int) round(100 * $matched / count($tokens));
                if ($partial > $best) {
                    $best = $partial;
                }
            }
        }
        return $best;
    }

    /**
     * Get configured weights from settings.
     *
     * @return array
     */
    public static function get_weights() {
        return [
            'sector' => (int) (get_config('local_jobmatchagent', 'weight_sector') ?: 35),
            'experience' => (int) (get_config('local_jobmatchagent', 'weight_experience') ?: 25),
            'distance' => (int) (get_config('local_jobmatchagent', 'weight_distance') ?: 15),
            'schedule' => (int) (get_config('local_jobmatchagent', 'weight_schedule') ?: 15),
            'size' => (int) (get_config('local_jobmatchagent', 'weight_size') ?: 10),
        ];
    }

    /**
     * Get the configured score threshold (0-100).
     *
     * @return int
     */
    public static function get_threshold() {
        $t = (int) get_config('local_jobmatchagent', 'score_threshold');
        return $t > 0 ? $t : 10;
    }

    /**
     * Recompute global score including AI-derived experience score.
     * The AI score acts as a HARD CAP: if AI says CV doesn't match (<20%),
     * no amount of FTM sector/location/schedule match can compensate.
     * This prevents matching a "cuoco" CV with "meccanico" offers just because
     * the student's FTM sector happens to be related.
     *
     * @param object $result Row from local_jobmatch_results (with all score_* fields)
     * @return int
     */
    public static function recompute_global_with_ai($result) {
        $expscore = $result->score_experience !== null ? (int) $result->score_experience : null;

        // VETO: if AI says CV doesn't match, the score IS the AI score.
        if ($expscore !== null && $expscore < self::get_ai_veto_threshold()) {
            return $expscore;
        }

        $w = self::get_weights();
        $sum = $w['sector'] + $w['experience'] + $w['distance'] + $w['schedule'] + $w['size'];
        if ($sum <= 0) {
            $sum = 1;
        }

        // If AI hasn't run yet, treat experience as 50 (neutral) to avoid bias.
        $expforavg = $expscore !== null ? $expscore : 50;

        return (int) round(
            ($result->score_sector * $w['sector']
                + $expforavg * $w['experience']
                + $result->score_distance * $w['distance']
                + $result->score_schedule * $w['schedule']
                + $result->score_size * $w['size']) / $sum
        );
    }

    /**
     * @return int below this AI %, the AI score caps the global (CV mismatch veto)
     */
    public static function get_ai_veto_threshold() {
        $t = (int) get_config('local_jobmatchagent', 'ai_veto_threshold');
        return $t > 0 ? $t : 20;
    }

    /**
     * Get all student sectors (primary first) from local_student_sectors.
     *
     * @param int $userid
     * @return string[] Array of sector codes
     */
    public static function get_student_sectors($userid) {
        global $DB;
        $rows = $DB->get_records('local_student_sectors', ['userid' => $userid], 'is_primary DESC, quiz_count DESC');
        $sectors = [];
        foreach ($rows as $r) {
            if (!empty($r->sector) && !in_array($r->sector, $sectors, true)) {
                $sectors[] = $r->sector;
            }
        }
        return $sectors;
    }

    /**
     * Get the latest CV text for a student from local_jobaida_letters.
     * Returns null if no CV ever saved.
     *
     * @param int $userid
     * @return string|null
     */
    public static function get_latest_cv($userid) {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_jobaida_letters')) {
            return null;
        }
        $row = $DB->get_record_sql(
            "SELECT cv_text FROM {local_jobaida_letters}
             WHERE userid = :uid AND cv_text IS NOT NULL AND cv_text <> ''
             ORDER BY timecreated DESC",
            ['uid' => $userid],
            IGNORE_MULTIPLE
        );
        return $row ? $row->cv_text : null;
    }

    /**
     * Resolve which CV to use for matching, given filters.
     * Priority: 1) manual_cv_text in filters, 2) latest from JobAIDA, 3) null.
     *
     * @param int $userid
     * @param object|null $filters Row from local_jobmatch_student_filters
     * @return array ['text' => string|null, 'source' => 'manual'|'jobaida'|'none']
     */
    public static function resolve_cv($userid, $filters = null) {
        global $DB;

        if ($filters === null) {
            $filters = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);
        }

        if ($filters && !empty($filters->manual_cv_text) && trim($filters->manual_cv_text) !== '') {
            return ['text' => $filters->manual_cv_text, 'source' => 'manual'];
        }

        $cv = self::get_latest_cv($userid);
        if ($cv !== null) {
            return ['text' => $cv, 'source' => 'jobaida'];
        }

        return ['text' => null, 'source' => 'none'];
    }
}
