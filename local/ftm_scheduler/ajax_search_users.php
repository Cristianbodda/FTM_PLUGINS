<?php
/**
 * AJAX endpoint: search Moodle users by name/email.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

if (!is_siteadmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo admin']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $q = required_param('q', PARAM_TEXT);
    $q = trim($q);

    if (strlen($q) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query troppo corta']);
        die();
    }

    $search = '%' . $DB->sql_like_escape($q) . '%';

    $users = $DB->get_records_sql(
        "SELECT u.id, u.firstname, u.lastname, u.username, u.email
         FROM {user} u
         WHERE u.deleted = 0
           AND u.suspended = 0
           AND (" . $DB->sql_like('u.firstname', ':s1', false) . "
                OR " . $DB->sql_like('u.lastname', ':s2', false) . "
                OR " . $DB->sql_like('u.email', ':s3', false) . "
                OR " . $DB->sql_like('u.username', ':s4', false) . ")
         ORDER BY u.lastname, u.firstname",
        ['s1' => $search, 's2' => $search, 's3' => $search, 's4' => $search],
        0,
        20
    );

    $result = [];
    foreach ($users as $u) {
        $result[] = [
            'id' => (int) $u->id,
            'firstname' => $u->firstname,
            'lastname' => $u->lastname,
            'username' => $u->username,
            'email' => $u->email,
        ];
    }

    echo json_encode(['success' => true, 'users' => $result]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
