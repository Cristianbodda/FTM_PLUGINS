<?php
/**
 * AJAX endpoint for Week Planner modal
 *
 * @package    local_coachmanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/dashboard_helper.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/coachmanager:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $dashboard = new \local_coachmanager\dashboard_helper($USER->id);
    $result = ['success' => false, 'message' => 'Azione non riconosciuta'];

    switch ($action) {

        case 'getplan':
            // Get week plan + available activities for a student/week
            $studentid = required_param('studentid', PARAM_INT);
            $week = required_param('week', PARAM_INT);

            $plan = $dashboard->get_week_plan($studentid, $week);
            $available = $dashboard->get_available_activities_for_week($week);

            $result = [
                'success' => true,
                'plan' => $plan,
                'available' => $available
            ];
            break;

        case 'assignatelier':
            // Enroll student in atelier activity (reuses existing method)
            $studentid = required_param('studentid', PARAM_INT);
            $activityid = required_param('activityid', PARAM_INT);

            $result = $dashboard->enroll_student_atelier($studentid, $activityid, $USER->id);
            break;

        case 'assignactivity':
            // Assign test/lab/external to student's week
            $studentid = required_param('studentid', PARAM_INT);
            $week = required_param('week', PARAM_INT);
            $type = required_param('type', PARAM_ALPHANUMEXT);
            $activityname = required_param('activityname', PARAM_TEXT);
            $activitydetails = optional_param('activitydetails', '', PARAM_TEXT);
            $day = optional_param('day', 1, PARAM_INT);
            $slot = optional_param('slot', 'matt', PARAM_ALPHA);

            $activity_data = [
                'name' => $activityname,
                'details' => $activitydetails,
                'day' => $day,
                'slot' => $slot
            ];

            $result = $dashboard->assign_week_activity($studentid, $week, $type, $activity_data, $USER->id);
            break;

        case 'removeactivity':
            // Remove activity (atelier or program)
            $studentid = required_param('studentid', PARAM_INT);
            $type = required_param('type', PARAM_ALPHANUMEXT);
            $recordid = required_param('recordid', PARAM_INT);

            if ($type === 'atelier') {
                // Cancel atelier enrollment
                $enrollment = $DB->get_record('local_ftm_enrollments', [
                    'id' => $recordid,
                    'userid' => $studentid
                ]);

                if (!$enrollment) {
                    $result = ['success' => false, 'message' => 'Iscrizione non trovata'];
                } else if ($enrollment->status === 'attended') {
                    $result = ['success' => false, 'message' => 'Non puoi rimuovere un atelier già frequentato'];
                } else {
                    $enrollment->status = 'cancelled';
                    $enrollment->timemodified = time();
                    $DB->update_record('local_ftm_enrollments', $enrollment);
                    $result = ['success' => true, 'message' => 'Iscrizione atelier annullata'];
                }
            } else {
                // Remove from student_program
                $result = $dashboard->remove_week_activity($recordid, $studentid);
            }
            break;

        case 'getatelierdates':
            // Get available dates for a specific atelier
            $atelierid = required_param('atelierid', PARAM_INT);
            $dates = $dashboard->get_atelier_next_dates($atelierid, 10);
            $result = ['success' => true, 'dates' => $dates];
            break;
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
