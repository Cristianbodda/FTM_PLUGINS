<?php
/**
 * AJAX endpoint for authorization management and letter deletion.
 *
 * Actions:
 *   search   - Search users not yet authorized
 *   authorize - Insert auth record for a user
 *   revoke   - Set active=0 for an auth record
 *   delete_letter - Delete a generated letter
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];

    switch ($action) {

        // ── Search users not yet authorized ──────────────────────────────
        case 'search':
            require_capability('local/jobaida:authorize', $context);

            $query = required_param('query', PARAM_TEXT);
            $query = trim($query);
            if (strlen($query) < 2) {
                $result['data'] = [];
                break;
            }

            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $likefullname = $DB->sql_like($fullname, ':q1', false);
            $likeemail = $DB->sql_like('u.email', ':q2', false);

            $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                      FROM {user} u
                     WHERE u.deleted = 0
                       AND u.suspended = 0
                       AND u.id > 2
                       AND ({$likefullname} OR {$likeemail})
                       AND u.id NOT IN (
                           SELECT a.userid FROM {local_jobaida_auth} a WHERE a.active = 1
                       )
                  ORDER BY u.lastname, u.firstname
                     LIMIT 20";

            $searchparam = '%' . $DB->sql_like_escape($query) . '%';
            $users = $DB->get_records_sql($sql, [
                'q1' => $searchparam,
                'q2' => $searchparam,
            ]);

            $data = [];
            foreach ($users as $u) {
                $data[] = [
                    'id' => (int)$u->id,
                    'fullname' => s(fullname($u)),
                    'email' => s($u->email),
                ];
            }
            $result['data'] = $data;
            break;

        // ── Authorize a user ─────────────────────────────────────────────
        case 'authorize':
            require_capability('local/jobaida:authorize', $context);

            $userid = required_param('userid', PARAM_INT);

            // Validate user exists.
            $targetuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);

            // Check if already authorized (might be revoked row).
            $existing = $DB->get_record('local_jobaida_auth', ['userid' => $userid]);
            if ($existing) {
                if ((int)$existing->active === 1) {
                    throw new Exception('User is already authorized.');
                }
                // Re-activate.
                $existing->active = 1;
                $existing->authorizedby = $USER->id;
                $existing->timemodified = time();
                $DB->update_record('local_jobaida_auth', $existing);
            } else {
                $record = new stdClass();
                $record->userid = $userid;
                $record->authorizedby = $USER->id;
                $record->active = 1;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('local_jobaida_auth', $record);
            }

            $result['message'] = get_string('student_authorized', 'local_jobaida');
            break;

        // ── Revoke authorization ─────────────────────────────────────────
        case 'revoke':
            require_capability('local/jobaida:authorize', $context);

            $userid = required_param('userid', PARAM_INT);

            $auth = $DB->get_record('local_jobaida_auth', ['userid' => $userid, 'active' => 1]);
            if (!$auth) {
                throw new Exception('Authorization not found.');
            }

            $auth->active = 0;
            $auth->timemodified = time();
            $DB->update_record('local_jobaida_auth', $auth);

            $result['message'] = get_string('student_revoked', 'local_jobaida');
            break;

        // ── Delete a letter ──────────────────────────────────────────────
        case 'delete_letter':
            $letterid = required_param('letterid', PARAM_INT);

            $letter = $DB->get_record('local_jobaida_letters', ['id' => $letterid], '*', MUST_EXIST);

            // Only own letters OR viewall capability.
            $canviewall = has_capability('local/jobaida:viewall', $context) || is_siteadmin();
            if ((int)$letter->userid !== (int)$USER->id && !$canviewall) {
                throw new Exception('Permission denied.');
            }

            $DB->delete_records('local_jobaida_letters', ['id' => $letterid]);

            $result['message'] = get_string('letter_deleted', 'local_jobaida');
            break;

        default:
            throw new Exception('Invalid action.');
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
