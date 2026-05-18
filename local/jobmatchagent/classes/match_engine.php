<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Orchestrates matching: snapshots, scoring, persistence.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

defined('MOODLE_INTERNAL') || die();

class match_engine {

    /**
     * Match a single offer against all active students.
     * Creates a result row per student (above threshold).
     *
     * @param int $offerid
     * @return int Number of results created
     */
    public static function match_offer_to_all_active_students($offerid) {
        global $DB;

        $offer = $DB->get_record('local_jobmatch_offers', ['id' => $offerid], '*', MUST_EXIST);
        $filters = $DB->get_records('local_jobmatch_student_filters', ['active' => 1]);

        $created = 0;
        foreach ($filters as $f) {
            if (self::match_one($offer, $f)) {
                $created++;
            }
        }
        return $created;
    }

    /**
     * Match all active offers against a single student (called when filters change).
     *
     * @param int $userid
     * @return int Number of results created
     */
    public static function match_all_active_offers_to_student($userid) {
        return self::match_all_active_offers_to_student_detailed($userid)['new_matches'];
    }

    /**
     * Like match_all_active_offers_to_student() but returns detailed statistics.
     *
     * @param int $userid
     * @return array {
     *     offers_in_catalog: int,
     *     skipped_already_done: int,
     *     new_matches: int,
     *     below_threshold: int,
     *     agent_off: bool
     * }
     */
    public static function match_all_active_offers_to_student_detailed($userid) {
        global $DB;

        $stats = [
            'offers_in_catalog' => 0,
            'skipped_already_done' => 0,
            'new_matches' => 0,
            'below_threshold' => 0,
            'agent_off' => false,
        ];

        $f = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);
        if (!$f || !$f->active) {
            $stats['agent_off'] = true;
            return $stats;
        }

        $offers = $DB->get_records('local_jobmatch_offers', ['status' => 'active'], 'timecreated DESC', '*', 0, 200);
        $stats['offers_in_catalog'] = count($offers);

        foreach ($offers as $offer) {
            // Already matched? skip.
            if ($DB->record_exists('local_jobmatch_results', ['offer_id' => $offer->id, 'userid' => $userid])) {
                $stats['skipped_already_done']++;
                continue;
            }

            $sectors = matcher::get_student_sectors($userid);
            $scoring = matcher::compute_deterministic($offer, $f, $sectors);

            // NO AUTO-DISCARD deterministico: il match va in pending, l'AI valutera,
            // il coach decidera tramite filtro AI e bottoni Pubblica/Scarta.

            // Create snapshot + result.
            $snapshotid = self::create_cv_snapshot($userid, $sectors, $f);
            $DB->insert_record('local_jobmatch_results', (object) [
                'offer_id' => $offer->id,
                'userid' => $userid,
                'cv_snapshot_id' => $snapshotid,
                'score_global' => $scoring['score_global'],
                'score_sector' => $scoring['score_sector'],
                'score_distance' => $scoring['score_distance'],
                'score_schedule' => $scoring['score_schedule'],
                'score_size' => $scoring['score_size'],
                'score_activity' => $scoring['score_activity'],
                'score_experience' => null,
                'status' => 'pending',
                'timecreated' => time(),
            ]);
            $stats['new_matches']++;
        }

        return $stats;
    }

    /**
     * Compute match for one (offer, filters) pair. Idempotent: if already exists, skip.
     * Tutti i match deterministici sono creati come 'pending'. La decisione finale
     * spetta al coach (che si basa sull'AI score). Niente auto-discard.
     *
     * @param object $offer
     * @param object $filters
     * @return bool true if a result was created
     */
    public static function match_one($offer, $filters) {
        global $DB;

        // Skip if already matched.
        if ($DB->record_exists('local_jobmatch_results', ['offer_id' => $offer->id, 'userid' => $filters->userid])) {
            return false;
        }

        $sectors = matcher::get_student_sectors($filters->userid);
        $scoring = matcher::compute_deterministic($offer, $filters, $sectors);

        // NO AUTO-DISCARD: tutti i match vanno in pending, AI poi valuta, coach decide.
        // Above threshold — create CV snapshot and result row.
        $snapshotid = self::create_cv_snapshot($filters->userid, $sectors, $filters);

        $rec = (object) [
            'offer_id' => $offer->id,
            'userid' => $filters->userid,
            'cv_snapshot_id' => $snapshotid,
            'score_global' => $scoring['score_global'],
            'score_sector' => $scoring['score_sector'],
            'score_distance' => $scoring['score_distance'],
            'score_schedule' => $scoring['score_schedule'],
            'score_size' => $scoring['score_size'],
            'score_activity' => $scoring['score_activity'],
            'score_experience' => null,
            'status' => 'pending',
            'timecreated' => time(),
        ];
        $DB->insert_record('local_jobmatch_results', $rec);
        return true;
    }

    /**
     * Create immutable CV snapshot for a student.
     * If most recent snapshot has identical fingerprint, reuse it.
     *
     * @param int $userid
     * @param array $sectors
     * @param object|null $filters Optional pre-loaded filters row (avoids extra query)
     * @return int|null snapshot id (null if no CV available)
     */
    public static function create_cv_snapshot($userid, $sectors = null, $filters = null) {
        global $DB;

        $resolved = matcher::resolve_cv($userid, $filters);
        if ($resolved['text'] === null) {
            $cvtext = '(CV non disponibile — incolla un CV nei filtri studente o usa JobAIDA)';
            $source = 'placeholder';
        } else {
            $cvtext = $resolved['text'];
            $source = $resolved['source']; // manual or jobaida
        }

        if ($sectors === null) {
            $sectors = matcher::get_student_sectors($userid);
        }
        $primary = $sectors[0] ?? null;
        $allsectors = implode(',', $sectors);
        $fingerprint = hash('sha256', $cvtext . '|' . $allsectors);

        // Reuse if last snapshot has same fingerprint.
        $existing = $DB->get_record_sql(
            "SELECT id FROM {local_jobmatch_cv_snapshots}
             WHERE userid = :uid AND fingerprint = :fp
             ORDER BY timecreated DESC",
            ['uid' => $userid, 'fp' => $fingerprint],
            IGNORE_MULTIPLE
        );
        if ($existing) {
            return (int) $existing->id;
        }

        $rec = (object) [
            'userid' => $userid,
            'cv_text' => $cvtext,
            'cv_source' => $source,
            'competencies_json' => null,
            'primary_sector' => $primary,
            'all_sectors' => $allsectors,
            'fingerprint' => $fingerprint,
            'timecreated' => time(),
        ];
        return (int) $DB->insert_record('local_jobmatch_cv_snapshots', $rec);
    }

    /**
     * Compute SHA-256 fingerprint for an offer (used for dedup).
     *
     * @param string $title
     * @param string $company
     * @param string $url
     * @param string $text
     * @return string
     */
    public static function offer_fingerprint($title, $company, $url, $text) {
        $normalized = strtolower(trim($title)) . '|'
            . strtolower(trim($company)) . '|'
            . strtolower(trim($url)) . '|'
            . substr(strtolower(preg_replace('/\s+/', ' ', $text)), 0, 500);
        return hash('sha256', $normalized);
    }

    /**
     * Run AI matching on all pending results that haven't been AI-processed yet.
     * Uses local_ftm_jobsearch::ai_scraper::match_cv_to_offers (batch of 30).
     *
     * @param bool $force If true, re-process even already-AI-evaluated matches.
     * @return array stats: students_processed, ai_calls, matches_updated, auto_discarded, errors
     */
    public static function process_ai_matching_for_pending($force = false) {
        global $DB;

        $stats = [
            'available' => false,
            'students_processed' => 0,
            'ai_calls' => 0,
            'matches_updated' => 0,
            'auto_discarded' => 0,
            'errors' => [],
            'debug' => [],
        ];

        // Load own AI matcher (uses richer prompt with descriptions).
        require_once(__DIR__ . '/ai_matcher.php');
        $stats['available'] = true;

        // Get distinct active students with work to do.
        if ($force) {
            $userids = $DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {local_jobmatch_results}
                 WHERE status IN ('pending', 'ai_done')"
            );
            $stats['debug'][] = 'Mode: FORCE (rivaluta anche match gia processati)';
        } else {
            $userids = $DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {local_jobmatch_results}
                 WHERE status IN ('pending', 'ai_done') AND score_experience IS NULL"
            );
            $stats['debug'][] = 'Mode: solo nuovi (status=pending/ai_done E score_experience NULL)';
        }

        $stats['debug'][] = 'Studenti trovati: ' . count($userids);

        $threshold = matcher::get_threshold();

        foreach ($userids as $userid) {
            $cvres = matcher::resolve_cv($userid);
            if (empty($cvres['text']) || $cvres['source'] === 'none') {
                $stats['errors'][] = 'User ' . $userid . ': nessun CV disponibile';
                continue;
            }

            if ($force) {
                $matches = $DB->get_records_sql(
                    "SELECT r.*, o.title AS offer_title, o.company AS offer_company,
                            o.location AS offer_location, o.work_schedule AS offer_schedule,
                            o.parsed_text AS offer_text
                     FROM {local_jobmatch_results} r
                     INNER JOIN {local_jobmatch_offers} o ON o.id = r.offer_id
                     WHERE r.userid = :uid AND r.status IN ('pending', 'ai_done')",
                    ['uid' => $userid]
                );
            } else {
                $matches = $DB->get_records_sql(
                    "SELECT r.*, o.title AS offer_title, o.company AS offer_company,
                            o.location AS offer_location, o.work_schedule AS offer_schedule,
                            o.parsed_text AS offer_text
                     FROM {local_jobmatch_results} r
                     INNER JOIN {local_jobmatch_offers} o ON o.id = r.offer_id
                     WHERE r.userid = :uid AND r.status IN ('pending', 'ai_done') AND r.score_experience IS NULL",
                    ['uid' => $userid]
                );
            }

            $stats['debug'][] = 'User ' . $userid . ' (' . $cvres['source'] . ' CV, ' . strlen($cvres['text']) . ' char): ' . count($matches) . ' match da processare';

            if (empty($matches)) {
                continue;
            }

            // Get desired activity for context (if set).
            $filter = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);
            $desiredactivity = '';
            if ($filter && !empty($filter->desired_activities)) {
                $arr = json_decode($filter->desired_activities, true);
                if (is_array($arr) && !empty($arr)) {
                    $desiredactivity = implode(', ', $arr);
                }
            }

            // Batch in groups of 20 (smaller than jobsearch's 30 to allow more chars per offer).
            $batches = array_chunk($matches, 20, true);

            foreach ($batches as $batch) {
                $aioffers = [];
                $idmap = [];
                foreach ($batch as $m) {
                    $obj = new \stdClass();
                    $obj->id = (int) $m->offer_id;
                    $obj->title = $m->offer_title;
                    $obj->company = $m->offer_company ?: '';
                    $obj->location = $m->offer_location ?: '';
                    $obj->work_schedule = $m->offer_schedule ?: '';
                    $obj->parsed_text = $m->offer_text ?: '';
                    $aioffers[] = $obj;
                    $idmap[(int) $m->offer_id] = $m;
                }

                try {
                    $aimatches = ai_matcher::match_cv_to_offers($cvres['text'], $aioffers, $desiredactivity);
                    $stats['ai_calls']++;
                } catch (\Throwable $e) {
                    $stats['errors'][] = 'User ' . $userid . ' batch error: ' . $e->getMessage();
                    continue;
                }

                foreach ($aimatches as $offerid => $ai) {
                    if (!isset($idmap[$offerid])) {
                        continue;
                    }
                    $m = $idmap[$offerid];
                    $aipct = max(0, min(100, (int) ($ai['pct'] ?? 0)));
                    $aireason = (string) ($ai['reason'] ?? '');

                    // Recompute global with AI score using the veto logic.
                    $m->score_experience = $aipct;
                    $newglobal = matcher::recompute_global_with_ai($m);

                    // NO AUTO-DISCARD: il coach decide tramite Pubblica/Scarta.
                    // Il sistema valuta e ordina, ma non scarta autonomamente.
                    // Cosi il coach vede TUTTO e usa i filtri AI per ordinare/escludere.
                    $update = (object) [
                        'id' => $m->id,
                        'score_experience' => $aipct,
                        'score_global' => $newglobal,
                        'ai_explanation_text' => $aireason,
                        'ai_processed_at' => time(),
                        'status' => 'ai_done',
                    ];

                    $DB->update_record('local_jobmatch_results', $update);
                    $stats['matches_updated']++;
                }
            }

            $stats['students_processed']++;
        }

        return $stats;
    }

    /**
     * Get coach-managed students (uses local_student_coaching).
     * Falls back to all active students for managers/admins.
     *
     * @param int $coachid
     * @return array Array of user records
     */
    public static function get_coach_students($coachid) {
        global $DB;

        $context = \context_system::instance();
        $isadmin = is_siteadmin() || has_capability('moodle/site:config', $context, $coachid);

        if ($isadmin) {
            // Admins/secretariat: see all students with filters set or all enrolled in any coaching record.
            $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                    FROM {user} u
                    INNER JOIN {local_student_coaching} sc ON sc.userid = u.id
                    WHERE u.deleted = 0 AND u.suspended = 0
                    ORDER BY u.lastname, u.firstname";
            return $DB->get_records_sql($sql);
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                INNER JOIN {local_student_coaching} sc ON sc.userid = u.id
                WHERE sc.coachid = :cid AND u.deleted = 0 AND u.suspended = 0
                ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, ['cid' => $coachid]);
    }

    /**
     * Check if a coach can manage a specific student.
     *
     * @param int $coachid
     * @param int $studentid
     * @return bool
     */
    public static function coach_can_manage_student($coachid, $studentid) {
        global $DB;
        $context = \context_system::instance();
        if (is_siteadmin() || has_capability('moodle/site:config', $context, $coachid)) {
            return true;
        }
        return $DB->record_exists('local_student_coaching', ['coachid' => $coachid, 'userid' => $studentid]);
    }
}
