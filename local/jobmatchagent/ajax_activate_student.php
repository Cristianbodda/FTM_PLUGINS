<?php
/**
 * AJAX endpoint — toggle the student view (student_view_enabled) for a student.
 *
 * Upserts local_jobmatch_student_filters for the given userid,
 * setting student_view_enabled, activated_by, activated_at.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/target_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:managetargets', $context);

header('Content-Type: application/json; charset=utf-8');

global $DB, $USER;

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];

    if ($action !== 'toggle') {
        throw new Exception(get_string('st_err_invalid_action', 'local_jobmatchagent'));
    }

    $userid  = required_param('userid', PARAM_INT);
    $enabled = optional_param('enabled', -1, PARAM_INT);

    if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
    }

    // Verify coach owns this student (or siteadmin).
    if (!is_siteadmin()) {
        $assigned = $DB->record_exists_sql(
            "SELECT 1 FROM {local_student_coaching}
              WHERE studentid = :uid AND coachid = :cid AND status = 'active'",
            ['uid' => $userid, 'cid' => $USER->id]
        );
        if (!$assigned) {
            throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
        }
    }

    // If enabled is -1, determine current state and invert it.
    if ($enabled < 0) {
        $current = \local_jobmatchagent\target_manager::student_view_enabled($userid);
        $new_enabled = $current ? false : true;
    } else {
        $new_enabled = (bool)$enabled;
    }

    // Upsert local_jobmatch_student_filters.
    $existing = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);

    $now = time();

    if ($existing) {
        $update = (object)[
            'id'                  => $existing->id,
            'student_view_enabled'=> $new_enabled ? 1 : 0,
            'activated_by'        => $new_enabled ? $USER->id : ($existing->activated_by ?? $USER->id),
            'activated_at'        => $new_enabled ? $now : ($existing->activated_at ?? null),
            'timemodified'        => $now,
        ];
        $DB->update_record('local_jobmatch_student_filters', $update);
    } else {
        $insert = (object)[
            'userid'              => $userid,
            'active'              => 0,
            'student_view_enabled'=> $new_enabled ? 1 : 0,
            'activated_by'        => $new_enabled ? $USER->id : null,
            'activated_at'        => $new_enabled ? $now : null,
            'updatedby'           => $USER->id,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ];
        $DB->insert_record('local_jobmatch_student_filters', $insert);
    }

    $result['enabled'] = $new_enabled;
    $result['message'] = $new_enabled
        ? get_string('st_sv_activated', 'local_jobmatchagent')
        : get_string('st_sv_deactivated', 'local_jobmatchagent');
    $result['data'] = [
        'userid'  => $userid,
        'enabled' => $new_enabled,
    ];

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

die();
