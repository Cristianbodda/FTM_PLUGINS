<?php
/**
 * AJAX endpoint for saving action plan area data.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $planid = required_param('planid', PARAM_INT);

    // Check if this is a specific action call or a full area save.
    $action = optional_param('action', '', PARAM_ALPHANUMEXT);

    if ($action === 'save_level') {
        // Single level save (used by inline level buttons if any).
        $field = required_param('field', PARAM_ALPHANUMEXT);
        $level = required_param('level', PARAM_INT);
        $notes = optional_param('notes', null, PARAM_TEXT);
        \local_ftm_sip\sip_manager::save_area_level($planid, $field, $level, $USER->id, $notes);
        echo json_encode(['success' => true, 'message' => get_string('action_plan_saved', 'local_ftm_sip')]);

    } else {
        // Full area save from SipStudent.saveArea() - saves levels + details together.
        $level_initial = optional_param('level_initial', null, PARAM_INT);
        $level_current = optional_param('level_current', null, PARAM_INT);
        $objective = optional_param('objective', '', PARAM_TEXT);
        $notes = optional_param('notes', '', PARAM_TEXT);
        $verification = optional_param('verification', '', PARAM_TEXT);
        $actions_agreed = optional_param('actions_agreed', '', PARAM_TEXT);
        $is_draft = optional_param('is_draft', '0', PARAM_INT);

        // Save levels if provided.
        if ($level_initial !== null && $level_initial >= 0 && $level_initial <= 6) {
            try {
                \local_ftm_sip\sip_manager::save_area_level($planid, 'level_initial', $level_initial, $USER->id);
            } catch (\Exception $e) {
                // Initial level may be locked if plan is active - ignore silently.
            }
        }
        if ($level_current !== null && $level_current >= 0 && $level_current <= 6) {
            \local_ftm_sip\sip_manager::save_area_level($planid, 'level_current', $level_current, $USER->id);
        }

        // Save text details.
        \local_ftm_sip\sip_manager::save_area_details($planid, $objective, $notes, $verification, $actions_agreed);

        echo json_encode(['success' => true, 'message' => get_string('action_plan_saved', 'local_ftm_sip')]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
