<?php
/**
 * AJAX endpoint — create/update/delete student targets and search companies.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/target_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:managetargets', $context);

header('Content-Type: application/json; charset=utf-8');

global $DB, $USER;

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];

    switch ($action) {

        // ----------------------------------------------------------------
        // action=create — add a new target for a student.
        // ----------------------------------------------------------------
        case 'create':
            $userid     = required_param('userid', PARAM_INT);
            $company_id = required_param('company_id', PARAM_INT);
            $note       = optional_param('note_per_ai', '', PARAM_TEXT);

            // Validate student exists.
            if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
                throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
            }

            // Verify coach ownership (or siteadmin).
            if (!is_siteadmin()) {
                $assigned = $DB->record_exists_sql(
                    "SELECT 1 FROM {local_student_coaching}
                      WHERE studentid = :uid AND coachid = :cid AND status = 'active'",
                    ['uid' => $userid, 'cid' => $USER->id]
                );
                if (!$assigned) {
                    throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
                }
            }

            // Validate company exists.
            if (!$DB->record_exists('local_jobmatch_ticino_companies', ['id' => $company_id])) {
                throw new Exception(get_string('st_err_company_not_found', 'local_jobmatchagent'));
            }

            $new_id = \local_jobmatchagent\target_manager::create_target($userid, $company_id, $USER->id, $note);
            $result['data'] = ['id' => $new_id];
            $result['message'] = get_string('st_target_created', 'local_jobmatchagent');
            break;

        // ----------------------------------------------------------------
        // action=update_note — update note_per_ai for an existing target.
        // ----------------------------------------------------------------
        case 'update_note':
            $id   = required_param('id', PARAM_INT);
            $note = optional_param('note_per_ai', '', PARAM_TEXT);

            $target = \local_jobmatchagent\target_manager::get_target($id);
            if (!$target) {
                throw new Exception(get_string('st_err_target_not_found', 'local_jobmatchagent'));
            }

            // Verify ownership.
            if (!is_siteadmin() && !_coach_owns_student($target->userid, $USER->id, $DB)) {
                throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
            }

            \local_jobmatchagent\target_manager::update_target($id, ['note_per_ai' => $note]);
            $result['message'] = get_string('st_note_saved', 'local_jobmatchagent');
            break;

        // ----------------------------------------------------------------
        // action=delete — remove a target.
        // ----------------------------------------------------------------
        case 'delete':
            $id     = required_param('id', PARAM_INT);
            $userid = required_param('userid', PARAM_INT);

            $target = \local_jobmatchagent\target_manager::get_target($id);
            if (!$target) {
                throw new Exception(get_string('st_err_target_not_found', 'local_jobmatchagent'));
            }
            if ((int)$target->userid !== (int)$userid) {
                throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
            }

            // Verify ownership.
            if (!is_siteadmin() && !_coach_owns_student($userid, $USER->id, $DB)) {
                throw new Exception(get_string('err_invalid_student', 'local_jobmatchagent'));
            }

            \local_jobmatchagent\target_manager::delete_target($id, $userid);
            $result['message'] = get_string('st_target_deleted', 'local_jobmatchagent');
            break;

        // ----------------------------------------------------------------
        // action=search_companies — search ticino_companies by text/sector.
        // ----------------------------------------------------------------
        case 'search_companies':
            $q       = optional_param('q', '', PARAM_TEXT);
            $settore = optional_param('settore', '', PARAM_TEXT);

            $q       = trim($q);
            $settore = trim($settore);

            if (strlen($q) < 2 && $settore === '') {
                $result['data'] = [];
                break;
            }

            $params = [];
            $wheres = ["c.status != 'archived'"];

            if ($q !== '') {
                $wheres[] = $DB->sql_like('c.nome', ':q', false, false);
                $params['q'] = '%' . $DB->sql_like_escape($q) . '%';
            }
            if ($settore !== '') {
                $wheres[] = 'c.settore_ftm = :settore';
                $params['settore'] = $settore;
            }

            $where_sql = implode(' AND ', $wheres);

            $rows = $DB->get_records_sql(
                "SELECT c.id, c.nome, c.settore_ftm, c.localita, c.dimensione
                   FROM {local_jobmatch_ticino_companies} c
                  WHERE {$where_sql}
               ORDER BY c.nome ASC
                  LIMIT 30",
                $params
            );

            $result['data'] = array_values($rows);
            break;

        default:
            throw new Exception(get_string('st_err_invalid_action', 'local_jobmatchagent'));
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

die();

// --------------------------------------------------------------------------
// Helper: verify a coach is assigned to a student.
// --------------------------------------------------------------------------
/**
 * Check that coachid is assigned to studentid in local_student_coaching.
 *
 * @param int $studentid
 * @param int $coachid
 * @param moodle_database $DB
 * @return bool
 */
function _coach_owns_student(int $studentid, int $coachid, $DB): bool {
    return $DB->record_exists_sql(
        "SELECT 1 FROM {local_student_coaching}
          WHERE studentid = :uid AND coachid = :cid AND status = 'active'",
        ['uid' => $studentid, 'cid' => $coachid]
    );
}
