<?php
/**
 * AJAX endpoint — Canali di ricerca (search_channels area).
 * Manages per-enrollment channel activations (one activation per channel, locked across weeks).
 *
 * Actions:
 *   get_channels    → Return all activated channels for an enrollment.
 *   activate        → Activate a channel for a given week (idempotent if same week).
 *   deactivate      → Remove a channel activation (only if it belongs to the given week).
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
require_capability('local/ftm_sip:view', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action       = required_param('action', PARAM_ALPHANUMEXT);
    $enrollmentid = required_param('enrollmentid', PARAM_INT);

    // Verify enrollment exists and user can access it.
    $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);

    $is_coach    = has_capability('local/ftm_sip:manage', $context);
    $is_student  = ($USER->id === (int)$enrollment->userid);
    if (!$is_coach && !$is_student) {
        throw new moodle_exception('nopermissions', 'error');
    }

    $valid_channels = [
        'email', 'portali_lavoro', 'siti_aziendali', 'foglio_ufficiale',
        'sito_confederazione', 'linkedin', 'facebook', 'giornali',
        'job_room', 'registro_commercio', 'elenco_telefonico', 'contatti_personali',
        'agenzie', 'bacheche_negozi', 'mailing_list', 'porta_a_porta',
        'telefonate', 'sindacati',
    ];

    if ($action === 'get_channels') {
        $rows = $DB->get_records('local_ftm_sip_channel_usage', ['enrollmentid' => $enrollmentid]);
        $data = [];
        foreach ($rows as $r) {
            $data[$r->channel_key] = (int)$r->sip_week;
        }
        echo json_encode(['success' => true, 'data' => $data]);

    } else if ($action === 'activate') {
        $channel_key = required_param('channel_key', PARAM_ALPHANUMEXT);
        $sip_week    = required_param('sip_week', PARAM_INT);

        if (!in_array($channel_key, $valid_channels, true)) {
            throw new moodle_exception('invalidparameter', 'error', '', 'channel_key');
        }
        if ($sip_week < 1 || $sip_week > 10) {
            throw new moodle_exception('invalidparameter', 'error', '', 'sip_week');
        }

        // Check if already activated.
        $existing = $DB->get_record('local_ftm_sip_channel_usage', [
            'enrollmentid' => $enrollmentid,
            'channel_key'  => $channel_key,
        ]);

        if ($existing) {
            // Already activated — return current state without error.
            echo json_encode([
                'success'  => true,
                'data'     => ['sip_week' => (int)$existing->sip_week],
                'message'  => 'already_activated',
            ]);
        } else {
            $now = time();
            $record = (object)[
                'enrollmentid'   => $enrollmentid,
                'channel_key'    => $channel_key,
                'sip_week'       => $sip_week,
                'activated_date' => $now,
                'activatedby'    => $USER->id,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ];
            $DB->insert_record('local_ftm_sip_channel_usage', $record);
            echo json_encode(['success' => true, 'data' => ['sip_week' => $sip_week]]);
        }

    } else if ($action === 'deactivate') {
        $channel_key = required_param('channel_key', PARAM_ALPHANUMEXT);
        $sip_week    = required_param('sip_week', PARAM_INT);

        if (!in_array($channel_key, $valid_channels, true)) {
            throw new moodle_exception('invalidparameter', 'error', '', 'channel_key');
        }

        $existing = $DB->get_record('local_ftm_sip_channel_usage', [
            'enrollmentid' => $enrollmentid,
            'channel_key'  => $channel_key,
        ]);

        if (!$existing) {
            echo json_encode(['success' => true, 'message' => 'not_found']);
        } else if ((int)$existing->sip_week !== $sip_week) {
            // Can't remove a channel activated in a different week.
            echo json_encode([
                'success' => false,
                'message' => 'Canale attivato nella settimana ' . (int)$existing->sip_week . ' — non modificabile da questa settimana.',
            ]);
        } else {
            $DB->delete_records('local_ftm_sip_channel_usage', ['id' => $existing->id]);
            echo json_encode(['success' => true]);
        }

    } else {
        throw new moodle_exception('invalidparameter', 'error', '', 'action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
