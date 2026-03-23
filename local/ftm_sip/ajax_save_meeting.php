<?php
/**
 * AJAX endpoint for saving coaching meetings and actions.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

use local_ftm_sip\sip_manager;

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:coach', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {
        case 'create_meeting':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $meeting_date_str = required_param('meeting_date', PARAM_TEXT);
            $duration = optional_param('duration', 60, PARAM_INT);
            $modality = optional_param('modality', 'presence', PARAM_ALPHANUMEXT);
            $summary = optional_param('summary', '', PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            $meeting_date = strtotime($meeting_date_str);
            if (!$meeting_date) {
                throw new moodle_exception('error_date_invalid', 'local_ftm_sip');
            }

            $meetingid = sip_manager::create_meeting(
                $enrollmentid, $USER->id, $meeting_date,
                $duration, $modality, $summary, $notes
            );

            echo json_encode([
                'success' => true,
                'data' => ['meetingid' => $meetingid],
                'message' => get_string('meeting_saved', 'local_ftm_sip'),
            ]);
            break;

        case 'update_meeting':
            $meetingid = required_param('meetingid', PARAM_INT);
            $summary = optional_param('summary', '', PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);
            $duration = optional_param('duration', null, PARAM_INT);

            $record = $DB->get_record('local_ftm_sip_meetings', ['id' => $meetingid], '*', MUST_EXIST);
            $record->summary = $summary;
            $record->notes = $notes;
            if ($duration !== null) {
                $record->duration_minutes = $duration;
            }
            $record->timemodified = time();
            $DB->update_record('local_ftm_sip_meetings', $record);

            echo json_encode(['success' => true, 'message' => get_string('meeting_saved', 'local_ftm_sip')]);
            break;

        case 'delete_meeting':
            $meetingid = required_param('meetingid', PARAM_INT);
            // Delete associated actions first.
            $DB->delete_records('local_ftm_sip_actions', ['meetingid' => $meetingid]);
            $DB->delete_records('local_ftm_sip_meetings', ['id' => $meetingid]);
            echo json_encode(['success' => true, 'message' => get_string('meeting_deleted', 'local_ftm_sip')]);
            break;

        case 'create_action':
            $meetingid = required_param('meetingid', PARAM_INT);
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $description = required_param('description', PARAM_TEXT);
            $deadline_str = optional_param('deadline', '', PARAM_TEXT);
            $deadline = !empty($deadline_str) ? strtotime($deadline_str) : null;

            $actionid = sip_manager::create_action($meetingid, $enrollmentid, $description, $deadline);
            echo json_encode([
                'success' => true,
                'data' => ['actionid' => $actionid],
                'message' => get_string('action_saved', 'local_ftm_sip'),
            ]);
            break;

        case 'update_action':
            $actionid = required_param('actionid', PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);
            $coach_notes = optional_param('coach_notes', null, PARAM_TEXT);

            sip_manager::update_action_status($actionid, $status, $USER->id, $coach_notes);
            echo json_encode(['success' => true, 'message' => get_string('action_saved', 'local_ftm_sip')]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
