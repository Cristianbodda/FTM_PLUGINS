<?php
// ============================================
// CoachManager - AJAX Enroll Student to Atelier
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once('classes/dashboard_helper.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/coachmanager:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHA);
    $studentid = required_param('studentid', PARAM_INT);

    $dashboard = new \local_coachmanager\dashboard_helper($USER->id);
    $result = ['success' => false, 'message' => 'Azione non riconosciuta'];

    switch ($action) {

        case 'enroll':
            // Enroll student in atelier
            $activityid = required_param('activityid', PARAM_INT);
            $result = $dashboard->enroll_student_atelier($studentid, $activityid, $USER->id);
            break;

        case 'unenroll':
            // Unenroll student from atelier
            $activityid = required_param('activityid', PARAM_INT);

            // Check if enrollment exists
            $enrollment = $DB->get_record('local_ftm_enrollments', [
                'activityid' => $activityid,
                'userid' => $studentid
            ]);

            if (!$enrollment) {
                $result = ['success' => false, 'message' => 'Iscrizione non trovata'];
            } else if ($enrollment->status === 'attended') {
                $result = ['success' => false, 'message' => 'Non puoi annullare un atelier già frequentato'];
            } else {
                // Update status to cancelled
                $enrollment->status = 'cancelled';
                $enrollment->timemodified = time();
                $DB->update_record('local_ftm_enrollments', $enrollment);

                $result = ['success' => true, 'message' => 'Iscrizione annullata'];
            }
            break;

        case 'getdates':
            // Get available dates for an atelier
            $atelierid = required_param('atelierid', PARAM_INT);
            $dates = $dashboard->get_atelier_next_dates($atelierid, 5);
            $result = ['success' => true, 'dates' => $dates];
            break;

        case 'getateliers':
            // Get student's atelier status
            $current_week = optional_param('week', 1, PARAM_INT);
            $ateliers = $dashboard->get_student_ateliers($studentid, $current_week);
            $result = ['success' => true, 'ateliers' => $ateliers];
            break;

        case 'getweek':
            // Get student's current week activities
            $activities = $dashboard->get_student_this_week_activities($studentid);
            $result = ['success' => true, 'activities' => $activities];
            break;

        case 'getabsences':
            // Get student's absence statistics
            $absences = $dashboard->get_student_absences($studentid);
            $result = ['success' => true, 'absences' => $absences];
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
