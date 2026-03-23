<?php
/**
 * AJAX endpoint for closing/completing a SIP enrollment.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $now = time();

    switch ($action) {

        case 'close':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $outcome = required_param('outcome', PARAM_ALPHANUMEXT);

            // Validate outcome value.
            $valid_outcomes = [
                'hired', 'stage', 'tryout', 'intermediate_earning',
                'training', 'not_placed_activated', 'interrupted', 'not_suitable', 'none',
            ];
            if (!in_array($outcome, $valid_outcomes)) {
                throw new invalid_parameter_exception('Invalid outcome value: ' . $outcome);
            }

            // Load enrollment.
            $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);

            // Optional fields.
            $outcome_company = optional_param('outcome_company', '', PARAM_TEXT);
            $outcome_date_str = optional_param('outcome_date', '', PARAM_TEXT);
            $outcome_percentage = optional_param('outcome_percentage', null, PARAM_INT);
            $interruption_reason = optional_param('interruption_reason', '', PARAM_TEXT);
            $referral_measure = optional_param('referral_measure', '', PARAM_TEXT);
            $coach_final_evaluation = optional_param('coach_final_evaluation', '', PARAM_TEXT);
            $next_steps = optional_param('next_steps', '', PARAM_TEXT);

            // -------------------------------------------------------
            // Validation: check all required conditions before closing.
            // -------------------------------------------------------
            $missing = [];

            // 1. All 7 areas must have level_current set (not null).
            $areas_total = $DB->count_records('local_ftm_sip_action_plan', ['enrollmentid' => $enrollmentid]);
            $areas_with_level = $DB->count_records_select(
                'local_ftm_sip_action_plan',
                'enrollmentid = :eid AND level_current IS NOT NULL',
                ['eid' => $enrollmentid]
            );
            if ($areas_total < 7 || $areas_with_level < 7) {
                $missing[] = get_string('closure_missing_levels', 'local_ftm_sip');
            }

            // 2. At least 3 meetings registered.
            $meetings_count = $DB->count_records('local_ftm_sip_meetings', ['enrollmentid' => $enrollmentid]);
            if ($meetings_count < 3) {
                $missing[] = get_string('closure_missing_meetings', 'local_ftm_sip');
            }

            // 3. Outcome field is not empty (already validated above, but double-check).
            if (empty($outcome)) {
                $missing[] = get_string('closure_missing_outcome', 'local_ftm_sip');
            }

            // 4. coach_final_evaluation is not empty.
            if (empty(trim($coach_final_evaluation))) {
                $missing[] = get_string('closure_missing_evaluation', 'local_ftm_sip');
            }

            // If validation fails, return error with details.
            if (!empty($missing)) {
                echo json_encode([
                    'success' => false,
                    'message' => get_string('closure_validation_error', 'local_ftm_sip', implode(', ', $missing)),
                ]);
                die();
            }

            // -------------------------------------------------------
            // All checks passed - save closure data.
            // -------------------------------------------------------
            $enrollment->outcome = $outcome;
            $enrollment->status = 'completed';
            $enrollment->date_end_actual = $now;
            $enrollment->timemodified = $now;

            if (!empty($outcome_company)) {
                $enrollment->outcome_company = $outcome_company;
            }
            if (!empty($outcome_date_str)) {
                $outcome_date = strtotime($outcome_date_str);
                if ($outcome_date) {
                    $enrollment->outcome_date = $outcome_date;
                }
            }
            if ($outcome_percentage !== null && $outcome_percentage >= 0 && $outcome_percentage <= 100) {
                $enrollment->outcome_percentage = $outcome_percentage;
            }
            if (!empty($interruption_reason)) {
                $enrollment->interruption_reason = $interruption_reason;
            }
            if (!empty($referral_measure)) {
                $enrollment->referral_measure = $referral_measure;
            }
            $enrollment->coach_final_evaluation = $coach_final_evaluation;
            if (!empty($next_steps)) {
                $enrollment->next_steps = $next_steps;
            }
            $enrollment->closure_validated = 1;

            $DB->update_record('local_ftm_sip_enrollments', $enrollment);

            echo json_encode([
                'success' => true,
                'data' => [
                    'enrollmentid' => $enrollmentid,
                    'status' => 'completed',
                    'outcome' => $outcome,
                ],
                'message' => get_string('closure_saved', 'local_ftm_sip'),
            ]);
            break;

        default:
            throw new invalid_parameter_exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
