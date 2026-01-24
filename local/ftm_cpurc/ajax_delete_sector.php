<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to delete a specific sector from student.
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
    $sector = required_param('sector', PARAM_ALPHANUMEXT);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('Utente non trovato');
    }

    // Delete from local_student_sectors if table exists.
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_student_sectors')) {
        $DB->delete_records('local_student_sectors', [
            'userid' => $userid,
            'sector' => $sector
        ]);
    }

    // If this was the primary sector in cpurc_students, clear it.
    $student = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
    if ($student && $student->sector_detected === $sector) {
        $student->sector_detected = null;
        $student->timemodified = time();
        $DB->update_record('local_ftm_cpurc_students', $student);
    }

    // Also clear from coaching table if it matches.
    if ($dbman->table_exists('local_student_coaching')) {
        $DB->execute(
            "UPDATE {local_student_coaching} SET sector = NULL, timemodified = :time WHERE userid = :userid AND sector = :sector",
            ['time' => time(), 'userid' => $userid, 'sector' => $sector]
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settore eliminato',
        'sector' => $sector
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
