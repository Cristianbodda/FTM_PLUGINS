<?php
/**
 * AJAX endpoint — update the status of a student autocandidatura target.
 *
 * Sets data_invio when transitioning to 'inviata'.
 * Sets data_risposta (once) when transitioning to risposta/colloquio/assunto/rifiutato.
 * Delegates SIP auto-log to target_manager::update_status().
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

/** @var array Allowed status values for student targets. */
$allowed_statuses = ['pending', 'lettera_generata', 'inviata', 'risposta', 'colloquio', 'assunto', 'rifiutato'];

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];

    if ($action !== 'update_status') {
        throw new Exception(get_string('st_err_invalid_action', 'local_jobmatchagent'));
    }

    $id         = required_param('id', PARAM_INT);
    $new_status = required_param('status', PARAM_ALPHANUMEXT);
    $note_esito = optional_param('note_esito', '', PARAM_TEXT);

    if (!in_array($new_status, $allowed_statuses, true)) {
        throw new Exception(get_string('st_err_invalid_status', 'local_jobmatchagent'));
    }

    $target = \local_jobmatchagent\target_manager::get_target($id);

    if (!$target) {
        throw new Exception(get_string('st_err_target_not_found', 'local_jobmatchagent'));
    }

    // Verify coach owns this student.
    if (!is_siteadmin()) {
        $assigned = $DB->record_exists_sql(
            "SELECT 1 FROM {local_student_coaching}
              WHERE studentid = :uid AND coachid = :cid AND status = 'active'",
            ['uid' => $target->userid, 'cid' => $USER->id]
        );
        if (!$assigned) {
            throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
        }
    }

    // Delegate to target_manager::update_status which handles timestamps + SIP log.
    // The method already sets data_invio and data_risposta internally.
    \local_jobmatchagent\target_manager::update_status($id, $new_status, $note_esito);

    // Return the updated target (for UI updates, especially sip_entry_id).
    $updated = \local_jobmatchagent\target_manager::get_target($id);

    $result['data'] = [
        'id'           => (int)$updated->id,
        'status'       => $updated->status,
        'sip_entry_id' => isset($updated->sip_entry_id) ? (int)$updated->sip_entry_id : null,
        'data_invio'   => isset($updated->data_invio)   ? (int)$updated->data_invio   : null,
        'data_risposta'=> isset($updated->data_risposta) ? (int)$updated->data_risposta : null,
    ];
    $result['message'] = get_string('st_status_updated', 'local_jobmatchagent');

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

die();
