<?php
/**
 * AJAX endpoint for approving/revoking a Technical Passport.
 *
 * Approved passports are stored as JSON snapshots in local_passport_comments
 * with area_code = '__PASSPORT_APPROVED__'. These snapshots are later used
 * as few-shot examples to calibrate AI generation for the same sector.
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
    $userid   = required_param('userid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
    $action   = required_param('action', PARAM_ALPHA); // 'approve' or 'revoke'

    if (!in_array($action, ['approve', 'revoke'])) {
        throw new Exception('Azione non valida');
    }

    // Validate user.
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

    $approvalCode = '__PASSPORT_APPROVED__';
    $coachid = $USER->id;
    $now = time();

    // ----------------------------------------------------------------
    // ACTION: revoke
    // ----------------------------------------------------------------
    if ($action === 'revoke') {
        $DB->delete_records('local_passport_comments', [
            'userid'    => $userid,
            'courseid'  => $courseid,
            'area_code' => $approvalCode,
        ]);
        echo json_encode(['success' => true, 'approved' => false]);
        die();
    }

    // ----------------------------------------------------------------
    // ACTION: approve
    // ----------------------------------------------------------------

    // Detect sector from primary sector assignment.
    $sector = '';
    $sectorRecord = $DB->get_record_sql(
        "SELECT sector FROM {local_student_sectors}
          WHERE userid = :uid AND is_primary = 1
          ORDER BY timemodified DESC",
        ['uid' => $userid],
        IGNORE_MISSING
    );
    if ($sectorRecord) {
        $sector = strtoupper($sectorRecord->sector);
    }

    // Load all current passport comments (exclude __ORIG and __PASSPORT_APPROVED__).
    $existingComments = $DB->get_records_sql(
        "SELECT area_code, comment
           FROM {local_passport_comments}
          WHERE userid = :uid
            AND courseid = :cid
            AND area_code NOT LIKE '%\\_\\_ORIG'
            AND area_code != :acode",
        ['uid' => $userid, 'cid' => $courseid, 'acode' => $approvalCode]
    );

    $examples = [];
    foreach ($existingComments as $row) {
        $comment = trim($row->comment);
        if ($comment !== '') {
            $examples[] = ['area_key' => $row->area_code, 'comment' => $comment];
        }
    }

    if (empty($examples)) {
        throw new Exception('Nessun commento trovato nel passaporto. Aggiungi i commenti prima di approvare.');
    }

    // Coach name.
    $coach = $DB->get_record('user', ['id' => $coachid], 'id,firstname,lastname', MUST_EXIST);
    $coachName = trim($coach->firstname . ' ' . $coach->lastname);

    // Build snapshot.
    $snapshot = [
        'timestamp'  => $now,
        'coachid'    => $coachid,
        'coach_name' => $coachName,
        'sector'     => $sector,
        'examples'   => $examples,
    ];

    // Upsert the approval record.
    $existing = $DB->get_record('local_passport_comments', [
        'userid'    => $userid,
        'courseid'  => $courseid,
        'area_code' => $approvalCode,
    ]);

    if ($existing) {
        $existing->comment      = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $existing->coachid      = $coachid;
        $existing->timemodified = $now;
        $DB->update_record('local_passport_comments', $existing);
    } else {
        $record = new stdClass();
        $record->userid       = $userid;
        $record->courseid     = $courseid;
        $record->area_code    = $approvalCode;
        $record->comment      = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $record->coachid      = $coachid;
        $record->timecreated  = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_passport_comments', $record);
    }

    $dateFormatted = userdate($now, get_string('strftimedatetimeshort', 'core_langconfig'));

    echo json_encode([
        'success'        => true,
        'approved'       => true,
        'date'           => $dateFormatted,
        'coach'          => $coachName,
        'examples_count' => count($examples),
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
