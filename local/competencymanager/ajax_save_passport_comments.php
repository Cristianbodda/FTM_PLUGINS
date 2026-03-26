<?php
/**
 * AJAX endpoint for saving coach comments for the Technical Passport.
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/competencymanager:evaluate', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
    // Comments come as JSON array: [{area_code: "AUTOMOBILE_A", comment: "text"}, ...]
    $comments_json = required_param('comments', PARAM_RAW);

    // Validate JSON.
    $comments = json_decode($comments_json, true);
    if (!is_array($comments)) {
        throw new Exception('Invalid comments format');
    }

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('User not found');
    }

    $coachid = $USER->id;
    $now = time();
    $saved = 0;

    foreach ($comments as $item) {
        // Validate area_code: only alphanumeric and underscores allowed.
        $area_code = $item['area_code'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $area_code)) {
            continue;
        }
        $comment = trim($item['comment'] ?? '');

        // Check if record exists.
        $existing = $DB->get_record('local_passport_comments', [
            'userid' => $userid,
            'courseid' => $courseid,
            'area_code' => $area_code,
        ]);

        if ($existing) {
            if (empty($comment)) {
                // Delete empty comments.
                $DB->delete_records('local_passport_comments', ['id' => $existing->id]);
            } else {
                $existing->comment = $comment;
                $existing->coachid = $coachid;
                $existing->timemodified = $now;
                $DB->update_record('local_passport_comments', $existing);
            }
            $saved++;
        } else if (!empty($comment)) {
            $record = new stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->area_code = $area_code;
            $record->comment = $comment;
            $record->coachid = $coachid;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_passport_comments', $record);
            $saved++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $saved . ' commenti salvati',
        'saved' => $saved,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
