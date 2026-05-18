<?php
/**
 * AJAX endpoint for SIP activation.
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
    $activate = optional_param('activate', '1', PARAM_INT);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

    // Draft fields (sempre salvati, anche senza attivazione).
    // Permettono al coach di tornare e ritrovare quanto scritto.
    $draft_motivation = trim(optional_param('motivation', '', PARAM_TEXT));
    $draft_ladi_indemnity = optional_param('ladi_indemnity', 0, PARAM_INT);
    $draft_date_start_str = optional_param('date_start', '', PARAM_TEXT);
    $draft_date_start_ts = null;
    if (!empty($draft_date_start_str)) {
        $ts = strtotime($draft_date_start_str);
        if ($ts !== false) {
            $draft_date_start_ts = $ts;
        }
    }

    // -------------------------------------------------------
    // Save eligibility assessment - Griglia Valutazione PCI.
    // 6 numeric criteria (1-5) + totale + decisione + note.
    // -------------------------------------------------------
    $motivazione = optional_param('motivazione', 0, PARAM_INT);
    $chiarezza_obiettivo = optional_param('chiarezza_obiettivo', 0, PARAM_INT);
    $occupabilita = optional_param('occupabilita', 0, PARAM_INT);
    $autonomia = optional_param('autonomia', 0, PARAM_INT);
    $bisogno_coaching = optional_param('bisogno_coaching', 0, PARAM_INT);
    $comportamento = optional_param('comportamento', 0, PARAM_INT);
    $decisione = optional_param('decisione', 'pending', PARAM_ALPHANUMEXT);
    $coach_recommendation = optional_param('coach_recommendation', '', PARAM_ALPHANUMEXT);
    $referral_detail = optional_param('referral_detail', '', PARAM_TEXT);
    $note = optional_param('note', '', PARAM_TEXT);

    $now = time();
    $eligibility_id = null;

    // Save if there's ANY data: criteria, draft fields, note, recommendation, referral.
    $criteria = [$motivazione, $chiarezza_obiettivo, $occupabilita, $autonomia, $bisogno_coaching, $comportamento];
    $has_criteria = false;
    foreach ($criteria as $c) {
        if ($c > 0) {
            $has_criteria = true;
            break;
        }
    }
    $has_any_data = $has_criteria
        || $draft_motivation !== ''
        || $draft_ladi_indemnity > 0
        || $draft_date_start_ts !== null
        || $note !== ''
        || $referral_detail !== ''
        || $coach_recommendation !== '';

    if ($has_any_data) {
        // Validate each criterion is between 1 and 6.
        foreach ($criteria as $c) {
            if ($c > 0 && ($c < 1 || $c > 6)) {
                throw new invalid_parameter_exception('Each criterion must be between 1 and 6');
            }
        }

        // Calculate totale (sum of all 6 criteria, max 36).
        $totale = array_sum($criteria);

        // Auto-determine decisione based on totale (semaphore system).
        // 0-20: non_idoneo (rosso), 21-28: idoneo (arancione), 29-36: idoneo_prioritario (verde).
        if ($totale >= 29) {
            $decisione = 'idoneo_prioritario';
        } else if ($totale >= 21) {
            $decisione = 'idoneo';
        } else {
            $decisione = 'non_idoneo';
        }

        // Validate decisione.
        $valid_decisioni = ['idoneo', 'idoneo_prioritario', 'non_idoneo', 'pending'];
        if (!in_array($decisione, $valid_decisioni)) {
            $decisione = 'pending';
        }

        // Validate coach_recommendation.
        $valid_recs = ['activate', 'not_activate', 'refer_other', ''];
        if (!in_array($coach_recommendation, $valid_recs)) {
            $coach_recommendation = '';
        }

        // Check if an eligibility record already exists for this user.
        $existing_elig = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);

        if ($existing_elig) {
            $existing_elig->assessedby = $USER->id;
            $existing_elig->motivazione = $motivazione ?: null;
            $existing_elig->chiarezza_obiettivo = $chiarezza_obiettivo ?: null;
            $existing_elig->occupabilita = $occupabilita ?: null;
            $existing_elig->autonomia = $autonomia ?: null;
            $existing_elig->bisogno_coaching = $bisogno_coaching ?: null;
            $existing_elig->comportamento = $comportamento ?: null;
            $existing_elig->totale = $totale ?: null;
            $existing_elig->decisione = $decisione;
            $existing_elig->coach_recommendation = $coach_recommendation ?: null;
            $existing_elig->referral_detail = $referral_detail ?: null;
            $existing_elig->note = $note ?: null;
            $existing_elig->draft_motivation = $draft_motivation !== '' ? $draft_motivation : null;
            $existing_elig->draft_ladi_indemnity = $draft_ladi_indemnity > 0 ? $draft_ladi_indemnity : null;
            $existing_elig->draft_date_start = $draft_date_start_ts;
            $existing_elig->timemodified = $now;

            $DB->update_record('local_ftm_sip_eligibility', $existing_elig);
            $eligibility_id = $existing_elig->id;
        } else {
            $elig_record = new stdClass();
            $elig_record->userid = $userid;
            $elig_record->assessedby = $USER->id;
            $elig_record->motivazione = $motivazione ?: null;
            $elig_record->chiarezza_obiettivo = $chiarezza_obiettivo ?: null;
            $elig_record->occupabilita = $occupabilita ?: null;
            $elig_record->autonomia = $autonomia ?: null;
            $elig_record->bisogno_coaching = $bisogno_coaching ?: null;
            $elig_record->comportamento = $comportamento ?: null;
            $elig_record->totale = $totale ?: null;
            $elig_record->decisione = $decisione;
            $elig_record->coach_recommendation = $coach_recommendation ?: null;
            $elig_record->referral_detail = $referral_detail ?: null;
            $elig_record->note = $note ?: null;
            $elig_record->draft_motivation = $draft_motivation !== '' ? $draft_motivation : null;
            $elig_record->draft_ladi_indemnity = $draft_ladi_indemnity > 0 ? $draft_ladi_indemnity : null;
            $elig_record->draft_date_start = $draft_date_start_ts;
            $elig_record->approved = 0;
            $elig_record->timecreated = $now;
            $elig_record->timemodified = $now;

            $eligibility_id = $DB->insert_record('local_ftm_sip_eligibility', $elig_record);
        }
    }

    // -------------------------------------------------------
    // If not activating, just save assessment and return.
    // -------------------------------------------------------
    if (!$activate) {
        echo json_encode([
            'success' => true,
            'data' => [
                'eligibility_id' => $eligibility_id,
                'userid' => $userid,
                'student_name' => fullname($user),
            ],
            'message' => get_string('eligibility_saved', 'local_ftm_sip'),
        ]);
        die();
    }

    // -------------------------------------------------------
    // Activation flow.
    // -------------------------------------------------------
    $motivation = optional_param('motivation', '', PARAM_TEXT);
    $date_start_str = required_param('date_start', PARAM_TEXT);
    $ladi_indemnity = optional_param('ladi_indemnity', 0, PARAM_INT);

    // Parse date (YYYY-MM-DD from HTML date input).
    $date_start = strtotime($date_start_str);
    if (!$date_start) {
        throw new moodle_exception('error_date_invalid', 'local_ftm_sip');
    }

    // Get coach ID: use current user's assigned coach or current user as coach.
    $coachid = $USER->id;

    // Check if student has a coaching record with a specific coach.
    $coaching = $DB->get_record('local_student_coaching', ['userid' => $userid]);
    if ($coaching && $coaching->coachid > 0) {
        // If the person activating is the assigned coach or a manager, use the assigned coach.
        $coachid = $coaching->coachid;
    }

    // Get student's primary sector.
    $sector = null;
    if ($DB->get_manager()->table_exists('local_student_sectors')) {
        $sector_record = $DB->get_record_sql(
            "SELECT sector FROM {local_student_sectors}
             WHERE userid = :userid AND is_primary = 1
             ORDER BY timemodified DESC
             LIMIT 1",
            ['userid' => $userid]
        );
        if ($sector_record) {
            $sector = $sector_record->sector;
        }
    }

    require_once(__DIR__ . '/lib.php');
    require_once(__DIR__ . '/classes/sip_manager.php');

    $enrollmentid = \local_ftm_sip\sip_manager::activate_sip(
        $userid,
        $coachid,
        $USER->id,
        $motivation,
        $date_start,
        $sector
    );

    // Link eligibility to enrollment if we saved one.
    if ($eligibility_id && $enrollmentid) {
        $DB->set_field('local_ftm_sip_enrollments', 'eligibility_id', $eligibility_id, ['id' => $enrollmentid]);
    }

    // Save LADI indemnity on the enrollment record.
    if ($enrollmentid && $ladi_indemnity > 0) {
        $DB->set_field('local_ftm_sip_enrollments', 'ladi_indemnity', $ladi_indemnity, ['id' => $enrollmentid]);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'enrollmentid' => $enrollmentid,
            'userid' => $userid,
            'student_name' => fullname($user),
            'eligibility_id' => $eligibility_id,
        ],
        'message' => get_string('enrollment_saved', 'local_ftm_sip'),
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
