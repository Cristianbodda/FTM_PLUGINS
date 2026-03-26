<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to cancel student enrollment.
// Removes student from CPURC, unenrols from course, removes group/coach/sector.
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:import', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('Utente non trovato');
    }

    // Validate CPURC record exists.
    $cpurc = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
    if (!$cpurc) {
        throw new Exception('Studente non presente in CPURC');
    }

    if ($cpurc->status === 'cancelled') {
        throw new Exception('Iscrizione gia annullata');
    }

    $details = [];

    // 1. Set CPURC status to cancelled.
    $cpurc->status = 'cancelled';
    $cpurc->timemodified = time();
    $DB->update_record('local_ftm_cpurc_students', $cpurc);
    $details[] = 'Stato CPURC: annullato';

    // 2. Remove from color groups.
    $groupcount = $DB->count_records('local_ftm_group_members', ['userid' => $userid]);
    if ($groupcount > 0) {
        $DB->delete_records('local_ftm_group_members', ['userid' => $userid]);
        $details[] = 'Rimosso da ' . $groupcount . ' gruppo/i colore';
    }

    // 3. Remove coach assignment.
    $coachcount = $DB->count_records('local_student_coaching', ['userid' => $userid]);
    if ($coachcount > 0) {
        $DB->delete_records('local_student_coaching', ['userid' => $userid]);
        $details[] = 'Coach rimosso';
    }

    // 4. Unenrol from R.comp course.
    $courseid = \local_ftm_cpurc\user_manager::find_course('R.comp');
    if (!$courseid) {
        $courseid = \local_ftm_cpurc\user_manager::find_course('Rcomp');
    }
    if (!$courseid) {
        $courseid = \local_ftm_cpurc\user_manager::find_course('competenz');
    }

    if ($courseid) {
        $enrolplugin = enrol_get_plugin('manual');
        if ($enrolplugin) {
            $instances = enrol_get_instances($courseid, true);
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual') {
                    $enrolplugin->unenrol_user($instance, $userid);
                    $details[] = 'Disiscritto dal corso';
                    break;
                }
            }
        }
    }

    // 5. Remove sector assignments.
    if ($DB->get_manager()->table_exists('local_student_sectors')) {
        $sectorcount = $DB->count_records('local_student_sectors', ['userid' => $userid]);
        if ($sectorcount > 0) {
            $DB->delete_records('local_student_sectors', ['userid' => $userid]);
            $details[] = 'Rimosso ' . $sectorcount . ' settore/i';
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Iscrizione annullata per ' . fullname($user),
        'details' => $details,
        'userid' => $userid,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
