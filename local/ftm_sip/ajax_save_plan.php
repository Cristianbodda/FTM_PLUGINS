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
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $planid = required_param('planid', PARAM_INT);

    switch ($action) {
        case 'save_level':
            $field = required_param('field', PARAM_ALPHANUMEXT);
            $level = required_param('level', PARAM_INT);
            $notes = optional_param('notes', null, PARAM_TEXT);
            \local_ftm_sip\sip_manager::save_area_level($planid, $field, $level, $USER->id, $notes);
            echo json_encode(['success' => true, 'message' => get_string('action_plan_saved', 'local_ftm_sip')]);
            break;

        case 'save_details':
            $objective = optional_param('objective', '', PARAM_TEXT);
            $notes = optional_param('notes', null, PARAM_TEXT);
            $verification = optional_param('verification', null, PARAM_TEXT);
            $actions_agreed = optional_param('actions_agreed', null, PARAM_RAW);
            // Validate actions_agreed is valid JSON if provided.
            if ($actions_agreed !== null && $actions_agreed !== '') {
                $decoded = json_decode($actions_agreed);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    $actions_agreed = json_encode([['text' => $actions_agreed, 'done' => false]]);
                }
            }
            \local_ftm_sip\sip_manager::save_area_details($planid, $objective, $notes, $verification, $actions_agreed);
            echo json_encode(['success' => true, 'message' => get_string('action_plan_saved', 'local_ftm_sip')]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
