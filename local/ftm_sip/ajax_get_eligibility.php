<?php
/**
 * AJAX endpoint for loading existing eligibility data.
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
    $userid = required_param('userid', PARAM_INT);

    $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);

    // Check if CI is already activated.
    $enrollment = null;
    if ($DB->get_manager()->table_exists('local_ftm_sip_enrollments')) {
        $enrollment = $DB->get_record_sql(
            "SELECT * FROM {local_ftm_sip_enrollments}
             WHERE userid = :userid AND status = 'active'
             ORDER BY timecreated DESC LIMIT 1",
            ['userid' => $userid]
        );
    }

    $data = [
        'has_eligibility' => !empty($eligibility),
        'has_enrollment' => !empty($enrollment),
        'eligibility' => null,
        'enrollment' => null,
    ];

    if ($eligibility) {
        $data['eligibility'] = [
            'motivazione' => (int)($eligibility->motivazione ?? 0),
            'chiarezza_obiettivo' => (int)($eligibility->chiarezza_obiettivo ?? 0),
            'occupabilita' => (int)($eligibility->occupabilita ?? 0),
            'autonomia' => (int)($eligibility->autonomia ?? 0),
            'bisogno_coaching' => (int)($eligibility->bisogno_coaching ?? 0),
            'comportamento' => (int)($eligibility->comportamento ?? 0),
            'totale' => (int)($eligibility->totale ?? 0),
            'decisione' => $eligibility->decisione ?? 'pending',
            'coach_recommendation' => $eligibility->coach_recommendation ?? '',
            'referral_detail' => $eligibility->referral_detail ?? '',
            'note' => $eligibility->note ?? '',
            // Draft fields (preserve scritti anche senza attivazione).
            'draft_motivation' => $eligibility->draft_motivation ?? '',
            'draft_ladi_indemnity' => (int)($eligibility->draft_ladi_indemnity ?? 0),
            'draft_date_start' => $eligibility->draft_date_start
                ? date('Y-m-d', $eligibility->draft_date_start)
                : '',
        ];
    }

    if ($enrollment) {
        $data['enrollment'] = [
            'id' => $enrollment->id,
            'status' => $enrollment->status,
            'date_start' => $enrollment->date_start ? date('Y-m-d', $enrollment->date_start) : '',
            'ladi_indemnity' => (int)($enrollment->ladi_indemnity ?? 0),
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
