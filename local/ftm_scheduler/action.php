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
 * Action handler for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();

$action = required_param('action', PARAM_ALPHANUMEXT);

$returnurl = new moodle_url('/local/ftm_scheduler/index.php');

switch ($action) {
    case 'create_group':
        require_capability('local/ftm_scheduler:managegroups', $context);
        
        $data = new stdClass();
        $data->name = required_param('name', PARAM_TEXT);
        $data->color = required_param('color', PARAM_ALPHA);
        $data->color_hex = required_param('color_hex', PARAM_TEXT);
        $entry_date_str = required_param('entry_date', PARAM_TEXT);
        $data->entry_date = strtotime($entry_date_str);
        
        // Create the group
        $groupid = \local_ftm_scheduler\manager::create_group($data);
        
        // Generate Week 1 activities
        $created_activities = \local_ftm_scheduler\manager::generate_week1_activities($groupid);
        
        // Set success message
        \core\notification::success(get_string('gruppo_creato', 'local_ftm_scheduler'));
        
        $returnurl = new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']);
        break;
        
    case 'create_external_booking':
        require_capability('local/ftm_scheduler:managerooms', $context);
        
        $data = new stdClass();
        $data->project_name = required_param('project_name', PARAM_TEXT);
        $data->roomid = required_param('roomid', PARAM_INT);
        $booking_date_str = required_param('booking_date', PARAM_TEXT);
        $time_slot = required_param('time_slot', PARAM_ALPHA);
        $data->responsible = optional_param('responsible', '', PARAM_TEXT);
        
        // Calculate start and end times based on slot
        $booking_date = strtotime($booking_date_str);
        $slots = local_ftm_scheduler_get_time_slots();
        
        if ($time_slot === 'all') {
            // All day
            $data->date_start = mktime(8, 30, 0, date('n', $booking_date), date('j', $booking_date), date('Y', $booking_date));
            $data->date_end = mktime(16, 30, 0, date('n', $booking_date), date('j', $booking_date), date('Y', $booking_date));
        } else {
            $slot_info = $slots[$time_slot];
            $start_parts = explode(':', $slot_info['start']);
            $end_parts = explode(':', $slot_info['end']);
            $data->date_start = mktime($start_parts[0], $start_parts[1], 0, date('n', $booking_date), date('j', $booking_date), date('Y', $booking_date));
            $data->date_end = mktime($end_parts[0], $end_parts[1], 0, date('n', $booking_date), date('j', $booking_date), date('Y', $booking_date));
        }
        
        // Create the booking
        \local_ftm_scheduler\manager::create_external_booking($data);
        
        \core\notification::success('Prenotazione creata con successo');
        
        $returnurl = new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']);
        break;
        
    case 'add_group_members':
        require_capability('local/ftm_scheduler:enrollstudents', $context);

        $groupid = required_param('groupid', PARAM_INT);
        $userids = required_param_array('userids', PARAM_INT);

        \local_ftm_scheduler\manager::add_group_members($groupid, $userids);

        \core\notification::success('Studenti aggiunti al gruppo');

        $returnurl = new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $groupid]);
        break;

    case 'remove_group_member':
        require_capability('local/ftm_scheduler:enrollstudents', $context);

        $groupid = required_param('groupid', PARAM_INT);
        $userid = required_param('userid', PARAM_INT);

        global $DB;

        // Remove member from group
        $DB->delete_records('local_ftm_group_members', [
            'groupid' => $groupid,
            'userid' => $userid
        ]);

        // Also remove any enrollments for this user in activities of this group
        $DB->delete_records('local_ftm_enrollments', [
            'groupid' => $groupid,
            'userid' => $userid
        ]);

        \core\notification::success('Studente rimosso dal gruppo');

        $returnurl = new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $groupid]);
        break;

    default:
        throw new moodle_exception('invalidaction', 'error');
}

redirect($returnurl);
