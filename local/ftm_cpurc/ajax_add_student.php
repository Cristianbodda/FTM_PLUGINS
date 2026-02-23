<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint for searching Moodle users and adding them to CPURC.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:import', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'search') {
        // Search Moodle users NOT already in CPURC.
        $query = required_param('query', PARAM_TEXT);

        if (strlen($query) < 2) {
            throw new Exception('Inserisci almeno 2 caratteri');
        }

        $like1 = $DB->sql_like('u.firstname', ':search1', false);
        $like2 = $DB->sql_like('u.lastname', ':search2', false);
        $like3 = $DB->sql_like('u.email', ':search3', false);
        $searchparam = '%' . $DB->sql_like_escape($query) . '%';

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                WHERE u.deleted = 0
                  AND u.suspended = 0
                  AND u.id > 2
                  AND ({$like1} OR {$like2} OR {$like3})
                  AND u.id NOT IN (SELECT userid FROM {local_ftm_cpurc_students})
                ORDER BY u.lastname, u.firstname
                LIMIT 20";

        $params = [
            'search1' => $searchparam,
            'search2' => $searchparam,
            'search3' => $searchparam,
        ];

        $users = $DB->get_records_sql($sql, $params);

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => (int)$user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
            ];
        }

        echo json_encode(['success' => true, 'users' => $results]);

    } else if ($action === 'add') {
        // Add a Moodle user to CPURC students table.
        $userid = required_param('userid', PARAM_INT);

        // Verify user exists and is not deleted.
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        // Check not already in CPURC.
        if ($DB->record_exists('local_ftm_cpurc_students', ['userid' => $userid])) {
            throw new Exception('Questo utente e gia presente nel sistema CPURC');
        }

        $now = time();

        // Insert minimal CPURC record.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->status = 'active';
        $record->date_start = $now;
        $record->absence_x = 0;
        $record->absence_o = 0;
        $record->absence_a = 0;
        $record->absence_b = 0;
        $record->absence_c = 0;
        $record->absence_d = 0;
        $record->absence_e = 0;
        $record->absence_f = 0;
        $record->absence_g = 0;
        $record->absence_h = 0;
        $record->absence_i = 0;
        $record->absence_total = 0;
        $record->interviews = 0;
        $record->stages_count = 0;
        $record->stage_days = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $studentid = $DB->insert_record('local_ftm_cpurc_students', $record);

        // Also create coaching record if not exists (so student appears in Coach Dashboard).
        if (!$DB->record_exists('local_student_coaching', ['userid' => $userid])) {
            $coaching = new \stdClass();
            $coaching->userid = $userid;
            $coaching->coachid = 0;
            $coaching->courseid = 0;
            $coaching->current_week = 1;
            $coaching->status = 'active';
            $coaching->timecreated = $now;
            $coaching->timemodified = $now;
            $DB->insert_record('local_student_coaching', $coaching);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Studente aggiunto: ' . fullname($user),
            'studentid' => (int)$studentid,
        ]);

    } else {
        throw new Exception('Azione non valida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
