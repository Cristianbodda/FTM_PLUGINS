<?php
/**
 * AJAX endpoint for preparing SIP draft (during 6-week phase).
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:coach', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);

    // Validate user exists.
    $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

    $coachid = $USER->id;

    // Get student's primary sector.
    $sector = null;
    if ($DB->get_manager()->table_exists('local_student_sectors')) {
        $sector_record = $DB->get_record_sql(
            "SELECT sector FROM {local_student_sectors}
             WHERE userid = :userid AND is_primary = 1
             ORDER BY timemodified DESC
             LIMIT 1",
            ['userid' => $userid]
        );
        if ($sector_record) {
            $sector = $sector_record->sector;
        }
    }

    require_once(__DIR__ . '/lib.php');
    require_once(__DIR__ . '/classes/sip_manager.php');

    $enrollmentid = \local_ftm_sip\sip_manager::prepare_sip_draft($userid, $coachid, $sector);

    echo json_encode([
        'success' => true,
        'data' => ['enrollmentid' => $enrollmentid],
        'message' => 'Piano SIP creato in bozza',
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
