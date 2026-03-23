<?php
/**
 * AJAX endpoint for saving roadmap phase notes.
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
require_capability('local/ftm_sip:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $enrollmentid = required_param('enrollmentid', PARAM_INT);
    $phase = required_param('phase', PARAM_INT);
    $notes = optional_param('notes', '', PARAM_TEXT);
    $objectives_met = optional_param('objectives_met', 0, PARAM_INT);

    if ($phase < 1 || $phase > 6) {
        throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

    $record = $DB->get_record('local_ftm_sip_phase_notes', [
        'enrollmentid' => $enrollmentid,
        'phase' => $phase,
    ]);

    if ($record) {
        $record->notes = $notes;
        $record->objectives_met = $objectives_met ? 1 : 0;
        $record->timemodified = time();
        $DB->update_record('local_ftm_sip_phase_notes', $record);
    } else {
        $record = new stdClass();
        $record->enrollmentid = $enrollmentid;
        $record->phase = $phase;
        $record->notes = $notes;
        $record->objectives_met = $objectives_met ? 1 : 0;
        $record->timecreated = time();
        $record->timemodified = time();
        $DB->insert_record('local_ftm_sip_phase_notes', $record);
    }

    echo json_encode(['success' => true, 'message' => get_string('success_saved', 'local_ftm_sip')]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
