<?php
/**
 * AJAX endpoint for quiz unlock management.
 *
 * Actions:
 *   getquizzes - List R.comp quizzes with attempt status for a student
 *   unlock     - Create/update quiz_overrides to allow one more attempt
 *
 * @package    local_selfassessment
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

// Coach or manager can unlock quizzes
if (!has_capability('local/selfassessment:manage', $context) &&
    !has_capability('local/coachmanager:view', $context)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permesso negato.']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $studentid = required_param('studentid', PARAM_INT);

    // Verify student exists
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);

    // Find R.comp course(s)
    $rcomp_courses = $DB->get_records_select('course', $DB->sql_like('fullname', ':name'), ['name' => '%R.comp%']);
    if (empty($rcomp_courses)) {
        echo json_encode(['success' => false, 'message' => 'Nessun corso R.comp trovato.']);
        die();
    }
    $courseids = array_keys($rcomp_courses);

    switch ($action) {
        case 'getquizzes':
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $quizzes = $DB->get_records_select('quiz', "course $insql", $params, 'name ASC');

            $result = [];
            foreach ($quizzes as $quiz) {
                // Count finished attempts for this student
                $finished = $DB->count_records('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'userid' => $studentid,
                    'state' => 'finished',
                ]);

                // Check for existing override
                $override = $DB->get_record('quiz_overrides', [
                    'quiz' => $quiz->id,
                    'userid' => $studentid,
                ]);

                // Effective max attempts: override takes priority
                $max_attempts = $override ? (int)$override->attempts : (int)$quiz->attempts;
                // attempts=0 means unlimited
                $is_unlimited = ($max_attempts === 0);

                if ($is_unlimited) {
                    $status = 'unlimited';
                } else if ($finished >= $max_attempts) {
                    $status = 'blocked';
                } else {
                    $status = 'free';
                }

                // If there's an override with more attempts than the quiz default
                $has_override = false;
                if ($override && (int)$quiz->attempts > 0 && (int)$override->attempts > (int)$quiz->attempts) {
                    $has_override = true;
                }

                $result[] = [
                    'quizid' => (int)$quiz->id,
                    'name' => $quiz->name,
                    'finished' => (int)$finished,
                    'max_attempts' => $max_attempts,
                    'status' => $status,
                    'has_override' => $has_override,
                    'override_attempts' => $override ? (int)$override->attempts : null,
                ];
            }

            echo json_encode(['success' => true, 'quizzes' => $result]);
            break;

        case 'unlock':
            $quizid = required_param('quizid', PARAM_INT);

            // Verify quiz exists and belongs to R.comp
            $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            if (!in_array($quiz->course, $courseids)) {
                echo json_encode(['success' => false, 'message' => 'Il quiz non appartiene a R.comp.']);
                die();
            }

            // Count current finished attempts
            $finished = $DB->count_records('quiz_attempts', [
                'quiz' => $quizid,
                'userid' => $studentid,
                'state' => 'finished',
            ]);

            // New max = finished + 1 (allow exactly one more attempt)
            $new_max = $finished + 1;

            // Check for existing override
            $existing = $DB->get_record('quiz_overrides', [
                'quiz' => $quizid,
                'userid' => $studentid,
            ]);

            if ($existing) {
                $existing->attempts = $new_max;
                $existing->timemodified = time();
                $DB->update_record('quiz_overrides', $existing);
            } else {
                $override = new stdClass();
                $override->quiz = $quizid;
                $override->userid = $studentid;
                $override->attempts = $new_max;
                $override->timeopen = null;
                $override->timeclose = null;
                $override->timelimit = null;
                $override->password = null;
                $override->timemodified = time();
                $DB->insert_record('quiz_overrides', $override);
            }

            // Log
            $eventdata = [
                'context' => context_course::instance($quiz->course),
                'relateduserid' => $studentid,
                'other' => [
                    'quizid' => $quizid,
                    'quizname' => $quiz->name,
                    'new_attempts' => $new_max,
                    'unlocked_by' => $USER->id,
                ],
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Quiz sbloccato! ' . fullname($student) . ' puo\' ora fare il tentativo #' . $new_max . '.',
                'new_max' => $new_max,
                'finished' => $finished,
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
