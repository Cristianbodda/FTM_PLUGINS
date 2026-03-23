<?php
/**
 * AJAX endpoint for managing appointments.
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
        case 'create':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $studentid = required_param('studentid', PARAM_INT);
            $date_str = required_param('appointment_date', PARAM_TEXT);
            $time_start = required_param('time_start', PARAM_TEXT);
            $duration = optional_param('duration', 60, PARAM_INT);
            $modality = optional_param('modality', 'presence', PARAM_ALPHANUMEXT);
            $location = optional_param('location', '', PARAM_TEXT);
            $topic = optional_param('topic', '', PARAM_TEXT);

            $date = strtotime($date_str);
            if (!$date) {
                throw new moodle_exception('error_date_invalid', 'local_ftm_sip');
            }

            // Validate time format HH:MM.
            if (!preg_match('/^\d{2}:\d{2}$/', $time_start)) {
                throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            }

            $apptid = sip_manager::create_appointment(
                $enrollmentid, $USER->id, $studentid,
                $date, $time_start, $duration, $modality, $location, $topic
            );

            // Send notification to student.
            require_once(__DIR__ . '/classes/notification_helper.php');
            $appt_record = $DB->get_record('local_ftm_sip_appointments', ['id' => $apptid]);
            $student_user = $DB->get_record('user', ['id' => $studentid]);
            if ($appt_record && $student_user) {
                \local_ftm_sip\notification_helper::send_appointment_created(
                    $appt_record, $student_user, $USER
                );
            }

            echo json_encode([
                'success' => true,
                'data' => ['appointmentid' => $apptid],
                'message' => get_string('appointment_saved_notification', 'local_ftm_sip'),
            ]);
            break;

        case 'update_status':
            $apptid = required_param('appointmentid', PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);

            $allowed = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];
            if (!in_array($status, $allowed)) {
                throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            }

            $record = new stdClass();
            $record->id = $apptid;
            $record->status = $status;
            $record->timemodified = time();
            $DB->update_record('local_ftm_sip_appointments', $record);

            // If completed, link to a meeting if desired.
            if ($status === 'completed') {
                $appt = $DB->get_record('local_ftm_sip_appointments', ['id' => $apptid]);
                if ($appt && !$appt->meetingid) {
                    // Auto-create a meeting stub.
                    $meetingid = sip_manager::create_meeting(
                        $appt->enrollmentid, $appt->coachid, $appt->appointment_date,
                        $appt->duration_minutes, $appt->modality, $appt->topic, ''
                    );
                    $record2 = new stdClass();
                    $record2->id = $apptid;
                    $record2->meetingid = $meetingid;
                    $record2->timemodified = time();
                    $DB->update_record('local_ftm_sip_appointments', $record2);
                }
            }

            echo json_encode(['success' => true, 'message' => get_string('appointment_saved', 'local_ftm_sip')]);
            break;

        case 'delete':
            $apptid = required_param('appointmentid', PARAM_INT);
            $DB->delete_records('local_ftm_sip_appointments', ['id' => $apptid]);
            echo json_encode(['success' => true, 'message' => get_string('appointment_deleted', 'local_ftm_sip')]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
