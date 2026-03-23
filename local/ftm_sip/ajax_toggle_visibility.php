<?php
/**
 * AJAX endpoint for toggling student visibility.
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
require_capability('local/ftm_sip:coach', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $enrollmentid = required_param('enrollmentid', PARAM_INT);
    $visible = required_param('visible', PARAM_INT);

    \local_ftm_sip\sip_manager::set_student_visibility($enrollmentid, (bool)$visible);

    echo json_encode([
        'success' => true,
        'message' => get_string('field_saved', 'local_ftm_sip'),
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
