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
 * Manager class for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_scheduler;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for handling all scheduler operations.
 */
class manager {

    /**
     * Get all groups with optional filters.
     *
     * @param string $status Filter by status (planning, active, completed)
     * @return array
     */
    public static function get_groups($status = null) {
        global $DB;
        
        $params = [];
        $where = '';
        
        if ($status) {
            $where = 'WHERE status = :status';
            $params['status'] = $status;
        }
        
        $sql = "SELECT g.*, 
                       (SELECT COUNT(*) FROM {local_ftm_group_members} gm WHERE gm.groupid = g.id) as member_count
                FROM {local_ftm_groups} g
                $where
                ORDER BY g.entry_date DESC, g.id DESC";
        
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get a single group by ID.
     *
     * @param int $groupid
     * @return object|false
     */
    public static function get_group($groupid) {
        global $DB;
        return $DB->get_record('local_ftm_groups', ['id' => $groupid]);
    }

    /**
     * Create a new group.
     *
     * @param object $data
     * @return int Group ID
     */
    public static function create_group($data) {
        global $DB, $USER;
        
        $now = time();
        
        $record = new \stdClass();
        $record->name = $data->name;
        $record->color = $data->color;
        $record->color_hex = $data->color_hex;
        $record->entry_date = $data->entry_date;
        $record->planned_end_date = $data->entry_date + (6 * 7 * 24 * 60 * 60); // 6 weeks
        $record->calendar_week = \local_ftm_scheduler_get_calendar_week($data->entry_date);
        $record->status = 'planning';
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->createdby = $USER->id;
        
        $groupid = $DB->insert_record('local_ftm_groups', $record);
        
        return $groupid;
    }

    /**
     * Add members to a group.
     *
     * @param int $groupid
     * @param array $userids
     * @return bool
     */
    public static function add_group_members($groupid, $userids) {
        global $DB;
        
        $now = time();
        
        foreach ($userids as $userid) {
            // Check if already member
            if ($DB->record_exists('local_ftm_group_members', ['groupid' => $groupid, 'userid' => $userid])) {
                continue;
            }
            
            $record = new \stdClass();
            $record->groupid = $groupid;
            $record->userid = $userid;
            $record->current_week = 1;
            $record->extended_weeks = 0;
            $record->status = 'active';
            $record->timecreated = $now;
            $record->timemodified = $now;
            
            $DB->insert_record('local_ftm_group_members', $record);
        }
        
        return true;
    }

    /**
     * Get members of a group.
     *
     * @param int $groupid
     * @return array
     */
    public static function get_group_members($groupid) {
        global $DB;
        
        $sql = "SELECT gm.*, u.firstname, u.lastname, u.email
                FROM {local_ftm_group_members} gm
                JOIN {user} u ON u.id = gm.userid
                WHERE gm.groupid = :groupid
                ORDER BY u.lastname, u.firstname";
        
        return $DB->get_records_sql($sql, ['groupid' => $groupid]);
    }

    /**
     * Get all rooms.
     *
     * @param bool $active_only
     * @return array
     */
    public static function get_rooms($active_only = true) {
        global $DB;
        
        $params = [];
        if ($active_only) {
            $params['active'] = 1;
        }
        
        return $DB->get_records('local_ftm_rooms', $params, 'sortorder ASC, id ASC');
    }

    /**
     * Get a single room.
     *
     * @param int $roomid
     * @return object|false
     */
    public static function get_room($roomid) {
        global $DB;
        return $DB->get_record('local_ftm_rooms', ['id' => $roomid]);
    }

    /**
     * Get week 1 template activities.
     *
     * @return array
     */
    public static function get_week1_template() {
        global $DB;
        return $DB->get_records('local_ftm_week1_template', ['active' => 1], 'day_of_week ASC, time_slot ASC, sortorder ASC');
    }

    /**
     * Get activities with filters.
     *
     * @param array $filters
     * @return array
     */
    public static function get_activities($filters = []) {
        global $DB;
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['groupid'])) {
            $where[] = 'a.groupid = :groupid';
            $params['groupid'] = $filters['groupid'];
        }
        
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $where[] = 'a.date_start >= :date_start AND a.date_start <= :date_end';
            $params['date_start'] = $filters['date_start'];
            $params['date_end'] = $filters['date_end'];
        }
        
        if (!empty($filters['roomid'])) {
            $where[] = 'a.roomid = :roomid';
            $params['roomid'] = $filters['roomid'];
        }
        
        if (!empty($filters['activity_type'])) {
            $where[] = 'a.activity_type = :activity_type';
            $params['activity_type'] = $filters['activity_type'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql = "SELECT a.*, 
                       g.name as group_name, g.color as group_color, g.color_hex as group_color_hex,
                       r.name as room_name, r.shortname as room_shortname,
                       u.firstname as teacher_firstname, u.lastname as teacher_lastname,
                       (SELECT COUNT(*) FROM {local_ftm_enrollments} e WHERE e.activityid = a.id AND e.status = 'enrolled') as enrolled_count
                FROM {local_ftm_activities} a
                LEFT JOIN {local_ftm_groups} g ON g.id = a.groupid
                LEFT JOIN {local_ftm_rooms} r ON r.id = a.roomid
                LEFT JOIN {user} u ON u.id = a.teacherid
                WHERE $where_sql
                ORDER BY a.date_start ASC, a.id ASC";
        
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get a single activity.
     *
     * @param int $activityid
     * @return object|false
     */
    public static function get_activity($activityid) {
        global $DB;
        
        $sql = "SELECT a.*, 
                       g.name as group_name, g.color as group_color, g.color_hex as group_color_hex,
                       r.name as room_name, r.shortname as room_shortname,
                       u.firstname as teacher_firstname, u.lastname as teacher_lastname
                FROM {local_ftm_activities} a
                LEFT JOIN {local_ftm_groups} g ON g.id = a.groupid
                LEFT JOIN {local_ftm_rooms} r ON r.id = a.roomid
                LEFT JOIN {user} u ON u.id = a.teacherid
                WHERE a.id = :activityid";
        
        return $DB->get_record_sql($sql, ['activityid' => $activityid]);
    }

    /**
     * Create activity.
     *
     * @param object $data
     * @return int Activity ID
     */
    public static function create_activity($data) {
        global $DB, $USER;
        
        $now = time();
        
        $record = new \stdClass();
        $record->name = $data->name;
        $record->activity_type = $data->activity_type;
        $record->groupid = $data->groupid ?? null;
        $record->target_week = $data->target_week ?? null;
        $record->date_start = $data->date_start;
        $record->date_end = $data->date_end;
        $record->roomid = $data->roomid ?? null;
        $record->teacherid = $data->teacherid ?? null;
        $record->max_participants = $data->max_participants ?? null;
        $record->atelierid = $data->atelierid ?? null;
        $record->templateid = $data->templateid ?? null;
        $record->status = 'scheduled';
        $record->has_conflict = 0;
        $record->notes = $data->notes ?? null;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->createdby = $USER->id;
        
        return $DB->insert_record('local_ftm_activities', $record);
    }

    /**
     * Generate Week 1 activities for a group.
     *
     * @param int $groupid
     * @return array Created activity IDs
     */
    public static function generate_week1_activities($groupid) {
        global $DB;
        
        $group = self::get_group($groupid);
        if (!$group) {
            return [];
        }
        
        $template = self::get_week1_template();
        if (empty($template)) {
            return [];
        }
        
        $colors = \local_ftm_scheduler_get_colors();
        $color_info = $colors[$group->color] ?? $colors['giallo'];
        
        $created = [];
        $monday = strtotime('monday this week', $group->entry_date);
        
        foreach ($template as $tpl) {
            // Calculate actual date
            $activity_date = strtotime('+' . ($tpl->day_of_week - 1) . ' days', $monday);
            
            // Parse times
            $start_parts = explode(':', $tpl->time_start);
            $end_parts = explode(':', $tpl->time_end);
            
            $date_start = mktime($start_parts[0], $start_parts[1], 0, 
                                 date('n', $activity_date), date('j', $activity_date), date('Y', $activity_date));
            $date_end = mktime($end_parts[0], $end_parts[1], 0,
                               date('n', $activity_date), date('j', $activity_date), date('Y', $activity_date));
            
            $data = new \stdClass();
            $data->name = $tpl->name;
            $data->activity_type = 'week1';
            $data->groupid = $groupid;
            $data->target_week = 1;
            $data->date_start = $date_start;
            $data->date_end = $date_end;
            $data->roomid = $tpl->default_roomid;
            $data->templateid = $tpl->id;
            
            $activityid = self::create_activity($data);
            $created[] = $activityid;
            
            // Auto-enroll all group members
            $members = self::get_group_members($groupid);
            foreach ($members as $member) {
                self::enroll_user($activityid, $member->userid, $groupid, 'auto');
            }
        }
        
        // Update group status to active
        $DB->set_field('local_ftm_groups', 'status', 'active', ['id' => $groupid]);
        
        return $created;
    }

    /**
     * Enroll user in activity.
     *
     * @param int $activityid
     * @param int $userid
     * @param int $groupid
     * @param string $type
     * @return int|false
     */
    public static function enroll_user($activityid, $userid, $groupid = null, $type = 'manual') {
        global $DB, $USER;
        
        // Check if already enrolled
        if ($DB->record_exists('local_ftm_enrollments', ['activityid' => $activityid, 'userid' => $userid])) {
            return false;
        }
        
        $now = time();
        
        $record = new \stdClass();
        $record->activityid = $activityid;
        $record->userid = $userid;
        $record->groupid = $groupid;
        $record->enrolledby = $USER->id;
        $record->enrollment_type = $type;
        $record->status = 'enrolled';
        $record->notification_sent = 0;
        $record->reminder_sent = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;
        
        return $DB->insert_record('local_ftm_enrollments', $record);
    }

    /**
     * Get enrollments for an activity.
     *
     * @param int $activityid
     * @return array
     */
    public static function get_activity_enrollments($activityid) {
        global $DB;
        
        $sql = "SELECT e.*, u.firstname, u.lastname, u.email
                FROM {local_ftm_enrollments} e
                JOIN {user} u ON u.id = e.userid
                WHERE e.activityid = :activityid
                ORDER BY u.lastname, u.firstname";
        
        return $DB->get_records_sql($sql, ['activityid' => $activityid]);
    }

    /**
     * Get atelier catalog.
     *
     * @return array
     */
    public static function get_atelier_catalog() {
        global $DB;
        return $DB->get_records('local_ftm_atelier_catalog', ['active' => 1], 'sortorder ASC, id ASC');
    }

    /**
     * Get external bookings.
     *
     * @param int $date_start
     * @param int $date_end
     * @return array
     */
    public static function get_external_bookings($date_start = null, $date_end = null) {
        global $DB;
        
        $where = [];
        $params = [];
        
        if ($date_start && $date_end) {
            $where[] = 'date_start >= :date_start AND date_start <= :date_end';
            $params['date_start'] = $date_start;
            $params['date_end'] = $date_end;
        }
        
        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT eb.*, r.name as room_name, r.shortname as room_shortname
                FROM {local_ftm_external_bookings} eb
                LEFT JOIN {local_ftm_rooms} r ON r.id = eb.roomid
                $where_sql
                ORDER BY eb.date_start ASC";
        
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get a single external booking by ID.
     *
     * @param int $bookingid
     * @return object|false
     */
    public static function get_external_booking($bookingid) {
        global $DB;
        
        $sql = "SELECT eb.*, r.name as room_name, r.shortname as room_shortname
                FROM {local_ftm_external_bookings} eb
                LEFT JOIN {local_ftm_rooms} r ON r.id = eb.roomid
                WHERE eb.id = :id";
        
        return $DB->get_record_sql($sql, ['id' => $bookingid]);
    }

    /**
     * Create external booking.
     *
     * @param object $data
     * @return int
     */
    public static function create_external_booking($data) {
        global $DB, $USER;
        
        $now = time();
        
        $record = new \stdClass();
        $record->project_name = $data->project_name;
        $record->roomid = $data->roomid;
        $record->date_start = $data->date_start;
        $record->date_end = $data->date_end;
        $record->responsible = $data->responsible ?? null;
        $record->notes = $data->notes ?? null;
        $record->recurring = $data->recurring ?? 'none';
        $record->recurring_until = $data->recurring_until ?? null;
        $record->timecreated = $now;
        $record->createdby = $USER->id;
        
        return $DB->insert_record('local_ftm_external_bookings', $record);
    }

    /**
     * Get coaches list.
     *
     * @return array
     */
    public static function get_coaches() {
        global $DB;
        
        $sql = "SELECT c.*, u.firstname, u.lastname, u.email
                FROM {local_ftm_coaches} c
                JOIN {user} u ON u.id = c.userid
                WHERE c.active = 1
                ORDER BY c.initials ASC";
        
        return $DB->get_records_sql($sql);
    }

    /**
     * Get statistics for dashboard.
     *
     * @return object
     */
    public static function get_dashboard_stats() {
        global $DB;
        
        $stats = new \stdClass();
        
        // Active groups
        $stats->active_groups = $DB->count_records('local_ftm_groups', ['status' => 'active']);
        
        // Students in active groups
        $sql = "SELECT COUNT(DISTINCT gm.userid) 
                FROM {local_ftm_group_members} gm
                JOIN {local_ftm_groups} g ON g.id = gm.groupid
                WHERE g.status = 'active' AND gm.status = 'active'";
        $stats->students = $DB->count_records_sql($sql);
        
        // Activities this week
        $monday = strtotime('monday this week');
        $sunday = strtotime('sunday this week 23:59:59');
        $stats->activities_week = $DB->count_records_select('local_ftm_activities', 
            'date_start >= :monday AND date_start <= :sunday AND status = :status',
            ['monday' => $monday, 'sunday' => $sunday, 'status' => 'scheduled']);
        
        // Rooms used this week
        $sql = "SELECT COUNT(DISTINCT roomid) 
                FROM {local_ftm_activities}
                WHERE date_start >= :monday AND date_start <= :sunday AND roomid IS NOT NULL";
        $stats->rooms_used = $DB->count_records_sql($sql, ['monday' => $monday, 'sunday' => $sunday]);
        
        // External projects this week
        $stats->external_projects = $DB->count_records_select('local_ftm_external_bookings',
            'date_start >= :monday AND date_start <= :sunday',
            ['monday' => $monday, 'sunday' => $sunday]);
        
        // Conflicts (placeholder - would need conflict detection logic)
        $stats->conflicts = 0;
        
        return $stats;
    }

    /**
     * Get week dates array (Monday to Friday).
     *
     * @param int $year
     * @param int $week
     * @return array
     */
    public static function get_week_dates($year, $week) {
        $monday = self::get_monday_of_week_ts($year, $week);
        
        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $timestamp = strtotime("+$i days", $monday);
            $days[] = [
                'timestamp' => $timestamp,
                'date' => date('Y-m-d', $timestamp),
                'day_num' => date('j', $timestamp),
                'day_name' => \local_ftm_scheduler_format_date($timestamp, 'short'),
                'day_of_week' => $i + 1, // 1=Mon, 5=Fri
            ];
        }
        
        return $days;
    }

    /**
     * Get Monday timestamp for a given year and week.
     *
     * @param int $year
     * @param int $week
     * @return int
     */
    private static function get_monday_of_week_ts($year, $week) {
        $dto = new \DateTime();
        $dto->setISODate($year, $week, 1);
        return $dto->getTimestamp();
    }
}
