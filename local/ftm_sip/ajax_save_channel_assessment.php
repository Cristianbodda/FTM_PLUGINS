<?php
/**
 * AJAX: Save channel assessment data (levels + actions) for a CI enrollment.
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
    $action       = required_param('action', PARAM_ALPHANUMEXT);
    $enrollmentid = required_param('enrollmentid', PARAM_INT);

    $enrollment = \local_ftm_sip\sip_manager::get_enrollment_by_id($enrollmentid);
    if (!$enrollment) {
        throw new Exception('Enrollment non trovato.');
    }

    if ($action === 'save') {
        $channel_key   = required_param('channel_key', PARAM_ALPHANUMEXT);
        $level_initial = required_param('level_initial', PARAM_INT);
        $level_target  = required_param('level_target',  PARAM_INT);
        $level_final   = optional_param('level_final',   '', PARAM_RAW);
        $actions_text  = optional_param('actions_text',  '', PARAM_TEXT);

        // Validate 0-6.
        $level_initial = max(0, min(6, $level_initial));
        $level_target  = max(0, min(6, $level_target));
        if ($level_final !== '' && $level_final !== null) {
            $level_final = max(0, min(6, (int)$level_final));
        }

        $channels = local_ftm_sip_get_channel_assessment_data();
        if (!isset($channels[$channel_key])) {
            throw new Exception('Canale non valido: ' . $channel_key);
        }

        $id = \local_ftm_sip\sip_manager::save_channel_assessment($enrollmentid, $channel_key, [
            'level_initial' => $level_initial,
            'level_target'  => $level_target,
            'level_final'   => $level_final,
            'actions_text'  => $actions_text,
        ], $USER->id);

        echo json_encode([
            'success' => true,
            'data'    => ['id' => $id, 'gap' => $level_target - $level_initial],
            'message' => 'Salvato.',
        ]);

    } else if ($action === 'get_all') {
        $data = \local_ftm_sip\sip_manager::get_channel_assessment($enrollmentid);
        echo json_encode(['success' => true, 'data' => $data]);

    } else {
        throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
