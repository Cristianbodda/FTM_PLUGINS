<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to assign coach to student.
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);
    $coachid = optional_param('coachid', 0, PARAM_INT);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('Utente non trovato');
    }

    // If coachid is provided, validate coach exists.
    if ($coachid > 0) {
        $coach = $DB->get_record('user', ['id' => $coachid, 'deleted' => 0]);
        if (!$coach) {
            throw new Exception('Coach non trovato');
        }
    }

    // Assign or remove coach.
    $coachData = null;
    if ($coachid > 0) {
        $result = \local_ftm_cpurc\cpurc_manager::assign_coach($userid, $coachid);
        $message = 'Coach assegnato con successo';
        $coachData = [
            'id' => $coach->id,
            'name' => $coach->firstname . ' ' . $coach->lastname,
            'email' => $coach->email
        ];
    } else {
        // Remove coach assignment.
        $DB->delete_records('local_student_coaching', ['userid' => $userid]);
        $result = true;
        $message = 'Coach rimosso';
    }

    echo json_encode([
        'success' => (bool)$result,
        'message' => $message,
        'userid' => $userid,
        'coachid' => $coachid,
        'coach' => $coachData
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
