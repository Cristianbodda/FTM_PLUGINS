<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Quality checker for SIP data completeness validation.
 *
 * Validates data quality across SIP enrollment lifecycle:
 * - Baseline completeness (7 initial activation levels)
 * - Final levels completeness
 * - Meeting frequency
 * - KPI activity (applications + contacts + opportunities)
 * - Closure prerequisites
 * - Phase-level quality for roadmap display
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_sip;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Data quality validation utility for SIP enrollments.
 */
class quality_checker {

    /** @var int Total activation areas. */
    const TOTAL_AREAS = 7;

    /** @var int Minimum meetings required for closure. */
    const MIN_MEETINGS_FOR_CLOSURE = 3;

    /**
     * Get comprehensive quality status for an enrollment.
     *
     * @param int $enrollmentid The enrollment ID to check.
     * @return object Quality status with the following properties:
     *   ->baseline_complete (bool) - all 7 areas have level_initial
     *   ->baseline_count (int) - how many areas have level_initial
     *   ->levels_final_complete (bool) - all 7 have level_current
     *   ->levels_final_count (int)
     *   ->meetings_count (int)
     *   ->meetings_sufficient (bool) - >= MIN_MEETINGS_FOR_CLOSURE
     *   ->meetings_per_week (float) - meetings / weeks elapsed
     *   ->kpi_count (int) - total applications + contacts + opportunities
     *   ->has_kpi (bool) - kpi_count > 0
     *   ->has_eligibility (bool) - eligibility record exists
     *   ->has_outcome (bool) - outcome field is not null/empty
     *   ->has_evaluation (bool) - coach_final_evaluation is not null/empty
     *   ->can_close (bool) - all required fields present for closure
     *   ->missing_for_closure (array) - list of missing items as lang string keys
     *   ->overall_status (string) - 'complete', 'partial', 'incomplete'
     *   ->overall_score (int) - 0-100 percentage of completeness
     */
    public static function get_quality($enrollmentid) {
        global $DB;

        $result = new \stdClass();

        // Get the enrollment record.
        $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid]);
        if (!$enrollment) {
            return self::empty_quality();
        }

        // --- Baseline (initial levels) ---
        $sql = "SELECT COUNT(*) FROM {local_ftm_sip_action_plan}
                WHERE enrollmentid = :eid AND level_initial IS NOT NULL";
        $result->baseline_count = (int) $DB->count_records_sql($sql, ['eid' => $enrollmentid]);
        $result->baseline_complete = ($result->baseline_count >= self::TOTAL_AREAS);

        // --- Final levels (current levels) ---
        $sql = "SELECT COUNT(*) FROM {local_ftm_sip_action_plan}
                WHERE enrollmentid = :eid AND level_current IS NOT NULL";
        $result->levels_final_count = (int) $DB->count_records_sql($sql, ['eid' => $enrollmentid]);
        $result->levels_final_complete = ($result->levels_final_count >= self::TOTAL_AREAS);

        // --- Meetings ---
        $result->meetings_count = (int) $DB->count_records('local_ftm_sip_meetings',
            ['enrollmentid' => $enrollmentid]);
        $result->meetings_sufficient = ($result->meetings_count >= self::MIN_MEETINGS_FOR_CLOSURE);

        // Meetings per week.
        $result->meetings_per_week = 0.0;
        if ($enrollment->date_start > 0) {
            $weeks_elapsed = \local_ftm_sip_calculate_week($enrollment->date_start);
            if ($weeks_elapsed > 0) {
                $result->meetings_per_week = round($result->meetings_count / $weeks_elapsed, 2);
            }
        }

        // --- KPI counts ---
        $applications = (int) $DB->count_records('local_ftm_sip_applications',
            ['enrollmentid' => $enrollmentid]);
        $contacts = (int) $DB->count_records('local_ftm_sip_contacts',
            ['enrollmentid' => $enrollmentid]);
        $opportunities = (int) $DB->count_records('local_ftm_sip_opportunities',
            ['enrollmentid' => $enrollmentid]);
        $result->kpi_count = $applications + $contacts + $opportunities;
        $result->has_kpi = ($result->kpi_count > 0);

        // --- Eligibility ---
        $result->has_eligibility = !empty($enrollment->eligibility_id) &&
            $DB->record_exists('local_ftm_sip_eligibility', ['id' => $enrollment->eligibility_id]);

        // --- Outcome ---
        $result->has_outcome = !empty($enrollment->outcome);

        // --- Coach final evaluation ---
        $result->has_evaluation = !empty($enrollment->coach_final_evaluation);

        // --- Closure validation ---
        $result->missing_for_closure = [];

        if (!$result->levels_final_complete) {
            $result->missing_for_closure[] = 'quality_missing_final_levels';
        }
        if (!$result->meetings_sufficient) {
            $result->missing_for_closure[] = 'quality_missing_meetings';
        }
        if (!$result->has_outcome) {
            $result->missing_for_closure[] = 'quality_missing_outcome';
        }
        if (!$result->has_evaluation) {
            $result->missing_for_closure[] = 'quality_missing_evaluation';
        }

        $result->can_close = empty($result->missing_for_closure);

        // --- Overall score (0-100) ---
        $checks = [
            $result->baseline_complete,
            $result->levels_final_complete,
            $result->meetings_sufficient,
            $result->has_kpi,
            $result->has_eligibility,
            $result->has_outcome,
            $result->has_evaluation,
        ];
        $passed = count(array_filter($checks));
        $total_checks = count($checks);
        $result->overall_score = (int) round(($passed / $total_checks) * 100);

        // --- Overall status ---
        if ($result->overall_score >= 100) {
            $result->overall_status = 'complete';
        } else if ($result->overall_score >= 40) {
            $result->overall_status = 'partial';
        } else {
            $result->overall_status = 'incomplete';
        }

        return $result;
    }

    /**
     * Get phase quality for roadmap display.
     *
     * Rules:
     * - Phase has at least 1 meeting in its week range: green
     * - Phase has 0 meetings but is current or future: yellow
     * - Phase has 0 meetings and is past: red
     *
     * @param int $enrollmentid The enrollment ID.
     * @param int $phase Phase number (1-6).
     * @return string 'green', 'yellow', or 'red'.
     */
    public static function get_phase_quality($enrollmentid, $phase) {
        global $DB;

        $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid]);
        if (!$enrollment || $enrollment->date_start <= 0) {
            return 'yellow';
        }

        // Determine week range for the phase.
        $phase_weeks = self::get_phase_week_range($phase);
        if (!$phase_weeks) {
            return 'yellow';
        }

        // Count meetings in this phase's week range.
        list($week_start, $week_end) = $phase_weeks;
        $sql = "SELECT COUNT(*) FROM {local_ftm_sip_meetings}
                WHERE enrollmentid = :eid
                AND sip_week >= :wstart AND sip_week <= :wend";
        $meeting_count = (int) $DB->count_records_sql($sql, [
            'eid' => $enrollmentid,
            'wstart' => $week_start,
            'wend' => $week_end,
        ]);

        if ($meeting_count > 0) {
            return 'green';
        }

        // No meetings: check if phase is current or future.
        $current_week = \local_ftm_sip_calculate_week($enrollment->date_start);
        $current_phase = \local_ftm_sip_get_phase($current_week);

        if ($phase >= $current_phase) {
            return 'yellow'; // Current or future phase.
        }

        return 'red'; // Past phase with no meetings.
    }

    /**
     * Check if closure is allowed and list any missing prerequisites.
     *
     * Closure requires:
     * 1. All 7 areas must have level_current set (not null)
     * 2. At least 3 meetings registered
     * 3. Outcome must be selected
     * 4. coach_final_evaluation must not be empty
     *
     * @param int $enrollmentid The enrollment ID.
     * @return object With ->allowed (bool) and ->missing (array of lang string keys).
     */
    public static function validate_closure($enrollmentid) {
        global $DB;

        $result = new \stdClass();
        $result->allowed = false;
        $result->missing = [];

        $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid]);
        if (!$enrollment) {
            $result->missing[] = 'quality_missing_enrollment';
            return $result;
        }

        // 1. All 7 areas must have level_current set.
        $sql = "SELECT COUNT(*) FROM {local_ftm_sip_action_plan}
                WHERE enrollmentid = :eid AND level_current IS NOT NULL";
        $levels_count = (int) $DB->count_records_sql($sql, ['eid' => $enrollmentid]);
        if ($levels_count < self::TOTAL_AREAS) {
            $result->missing[] = 'quality_missing_final_levels';
        }

        // 2. At least 3 meetings registered.
        $meetings_count = (int) $DB->count_records('local_ftm_sip_meetings',
            ['enrollmentid' => $enrollmentid]);
        if ($meetings_count < self::MIN_MEETINGS_FOR_CLOSURE) {
            $result->missing[] = 'quality_missing_meetings';
        }

        // 3. Outcome must be selected.
        if (empty($enrollment->outcome)) {
            $result->missing[] = 'quality_missing_outcome';
        }

        // 4. Coach final evaluation must not be empty.
        if (empty($enrollment->coach_final_evaluation)) {
            $result->missing[] = 'quality_missing_evaluation';
        }

        $result->allowed = empty($result->missing);

        return $result;
    }

    /**
     * Get the week range for a given phase.
     *
     * Phase definitions from lib.php:
     *   Phase 1: week 1
     *   Phase 2: week 2
     *   Phase 3: weeks 3-4
     *   Phase 4: weeks 5-6
     *   Phase 5: weeks 7-8
     *   Phase 6: weeks 9-10
     *
     * @param int $phase Phase number 1-6.
     * @return array|null [week_start, week_end] or null if invalid.
     */
    private static function get_phase_week_range($phase) {
        $ranges = [
            1 => [1, 1],
            2 => [2, 2],
            3 => [3, 4],
            4 => [5, 6],
            5 => [7, 8],
            6 => [9, 10],
        ];
        return isset($ranges[$phase]) ? $ranges[$phase] : null;
    }

    /**
     * Return an empty quality object for missing enrollments.
     *
     * @return object
     */
    private static function empty_quality() {
        $result = new \stdClass();
        $result->baseline_complete = false;
        $result->baseline_count = 0;
        $result->levels_final_complete = false;
        $result->levels_final_count = 0;
        $result->meetings_count = 0;
        $result->meetings_sufficient = false;
        $result->meetings_per_week = 0.0;
        $result->kpi_count = 0;
        $result->has_kpi = false;
        $result->has_eligibility = false;
        $result->has_outcome = false;
        $result->has_evaluation = false;
        $result->can_close = false;
        $result->missing_for_closure = ['quality_missing_enrollment'];
        $result->overall_status = 'incomplete';
        $result->overall_score = 0;
        return $result;
    }
}
