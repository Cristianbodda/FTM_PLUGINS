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
 * CPURC data manager.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages CPURC student data and reports.
 */
class cpurc_manager {

    /**
     * Get dashboard statistics.
     *
     * @return object Statistics object.
     */
    public static function get_stats() {
        global $DB;

        $stats = new \stdClass();

        // Total CPURC students.
        $stats->total = $DB->count_records('local_ftm_cpurc_students');

        // Active students (status = active or Aperto).
        $stats->active = $DB->count_records_select(
            'local_ftm_cpurc_students',
            "status = 'active' OR status = 'Aperto' OR status IS NULL"
        );

        // Reports in draft.
        $stats->reports_draft = $DB->count_records('local_ftm_cpurc_reports', ['status' => 'draft']);

        // Reports finalized.
        $stats->reports_final = $DB->count_records_select(
            'local_ftm_cpurc_reports',
            "status = 'final' OR status = 'sent'"
        );

        // Students by URC.
        $stats->by_urc = $DB->get_records_sql(
            "SELECT urc_office, COUNT(*) as cnt
             FROM {local_ftm_cpurc_students}
             WHERE urc_office IS NOT NULL AND urc_office != ''
             GROUP BY urc_office
             ORDER BY cnt DESC"
        );

        // Students by sector.
        $stats->by_sector = $DB->get_records_sql(
            "SELECT sector_detected, COUNT(*) as cnt
             FROM {local_ftm_cpurc_students}
             WHERE sector_detected IS NOT NULL AND sector_detected != ''
             GROUP BY sector_detected
             ORDER BY cnt DESC"
        );

        return $stats;
    }

    /**
     * Get students with filters.
     *
     * @param array $filters Filter options.
     * @return array Array of student records with user data.
     */
    public static function get_students($filters = []) {
        global $DB;

        $params = [];
        $where = ['1=1'];

        // Search filter.
        if (!empty($filters['search'])) {
            $search = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where[] = "(" . $DB->sql_like('u.firstname', ':search1', false) . " OR " .
                       $DB->sql_like('u.lastname', ':search2', false) . " OR " .
                       $DB->sql_like('u.email', ':search3', false) . ")";
            $params['search1'] = $search;
            $params['search2'] = $search;
            $params['search3'] = $search;
        }

        // URC filter.
        if (!empty($filters['urc'])) {
            $where[] = "cs.urc_office = :urc";
            $params['urc'] = $filters['urc'];
        }

        // Sector filter.
        if (!empty($filters['sector'])) {
            $where[] = "cs.sector_detected = :sector";
            $params['sector'] = $filters['sector'];
        }

        // Status filter.
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $where[] = "(cs.status = 'active' OR cs.status = 'Aperto' OR cs.status IS NULL)";
            } else {
                $where[] = "cs.status = :status";
                $params['status'] = $filters['status'];
            }
        }

        // Report status filter.
        if (!empty($filters['report_status'])) {
            if ($filters['report_status'] === 'none') {
                $where[] = "r.id IS NULL";
            } else if ($filters['report_status'] === 'draft') {
                $where[] = "r.status = 'draft'";
            } else if ($filters['report_status'] === 'complete') {
                $where[] = "(r.status = 'final' OR r.status = 'sent')";
            }
        }

        // Coach filter.
        if (!empty($filters['coach'])) {
            $where[] = "sc.coachid = :coachid";
            $params['coachid'] = $filters['coach'];
        }

        // Date range filter.
        if (!empty($filters['date_from'])) {
            $where[] = "cs.date_start >= :datefrom";
            $params['datefrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "cs.date_start <= :dateto";
            $params['dateto'] = $filters['date_to'];
        }

        // Group color filter.
        $groupjoin = '';
        if (!empty($filters['group_color'])) {
            $groupjoin = "LEFT JOIN {local_ftm_group_members} gm ON gm.userid = cs.userid
                          LEFT JOIN {local_ftm_groups} grp ON grp.id = gm.groupid";
            $where[] = "grp.color = :groupcolor";
            $params['groupcolor'] = $filters['group_color'];
        }

        $wheresql = implode(' AND ', $where);

        // Build query with all required user fields for fullname.
        // Include coach from local_student_coaching (shared table).
        $sql = "SELECT cs.*, u.id as moodleuserid, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       r.id as reportid, r.status as report_status,
                       sc.coachid, coach.firstname as coach_firstname, coach.lastname as coach_lastname
                FROM {local_ftm_cpurc_students} cs
                JOIN {user} u ON u.id = cs.userid
                LEFT JOIN {local_ftm_cpurc_reports} r ON r.studentid = cs.id
                LEFT JOIN {local_student_coaching} sc ON sc.userid = cs.userid
                LEFT JOIN {user} coach ON coach.id = sc.coachid
                {$groupjoin}
                WHERE {$wheresql}
                ORDER BY cs.date_start DESC, u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get student by ID.
     *
     * @param int $studentid CPURC student ID.
     * @return object|false Student record or false.
     */
    public static function get_student($studentid) {
        global $DB;

        return $DB->get_record_sql(
            "SELECT cs.*, u.firstname, u.lastname, u.email,
                    u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
             FROM {local_ftm_cpurc_students} cs
             JOIN {user} u ON u.id = cs.userid
             WHERE cs.id = :id",
            ['id' => $studentid]
        );
    }

    /**
     * Get student by user ID.
     *
     * @param int $userid Moodle user ID.
     * @return object|false Student record or false.
     */
    public static function get_student_by_userid($userid) {
        global $DB;

        return $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
    }

    /**
     * Save student data.
     *
     * @param object $data Student data object.
     * @return int Student ID.
     */
    public static function save_student($data) {
        global $DB;

        $data->timemodified = time();

        if (!empty($data->id)) {
            $DB->update_record('local_ftm_cpurc_students', $data);
            return $data->id;
        } else {
            $data->timecreated = time();
            return $DB->insert_record('local_ftm_cpurc_students', $data);
        }
    }

    /**
     * Get report for student.
     *
     * @param int $studentid Student ID.
     * @return object|false Report record or false.
     */
    public static function get_report($studentid) {
        global $DB;

        return $DB->get_record('local_ftm_cpurc_reports', ['studentid' => $studentid]);
    }

    /**
     * Save report data.
     *
     * @param object $data Report data object.
     * @return int Report ID.
     */
    public static function save_report($data) {
        global $DB, $USER;

        $data->timemodified = time();
        $data->coachid = $USER->id;

        if (!empty($data->id)) {
            $DB->update_record('local_ftm_cpurc_reports', $data);
            return $data->id;
        } else {
            $data->timecreated = time();
            $data->status = 'draft';
            return $DB->insert_record('local_ftm_cpurc_reports', $data);
        }
    }

    /**
     * Get available URC offices from data.
     *
     * @return array Array of URC offices.
     */
    public static function get_urc_offices() {
        global $DB;

        return $DB->get_records_sql(
            "SELECT DISTINCT urc_office
             FROM {local_ftm_cpurc_students}
             WHERE urc_office IS NOT NULL AND urc_office != ''
             ORDER BY urc_office"
        );
    }

    /**
     * Get available sectors from data.
     *
     * @return array Array of sectors.
     */
    public static function get_sectors() {
        global $DB;

        return $DB->get_records_sql(
            "SELECT DISTINCT sector_detected
             FROM {local_ftm_cpurc_students}
             WHERE sector_detected IS NOT NULL AND sector_detected != ''
             ORDER BY sector_detected"
        );
    }

    /**
     * Get import history.
     *
     * @param int $limit Number of records.
     * @return array Array of import records.
     */
    public static function get_import_history($limit = 10) {
        global $DB;

        return $DB->get_records_sql(
            "SELECT i.*, u.firstname, u.lastname
             FROM {local_ftm_cpurc_imports} i
             JOIN {user} u ON u.id = i.importedby
             ORDER BY i.timecreated DESC",
            [],
            0,
            $limit
        );
    }

    /**
     * Calculate student week number.
     *
     * @param int $datestart Start date timestamp.
     * @return int Week number (1-6+).
     */
    public static function calculate_week_number($datestart) {
        if (empty($datestart)) {
            return 0;
        }

        $now = time();
        $diff = $now - $datestart;
        $weeks = floor($diff / (7 * 24 * 60 * 60)) + 1;

        return max(1, $weeks);
    }

    /**
     * Get status badge class based on week.
     *
     * @param int $week Week number.
     * @return array Array with class and icon.
     */
    public static function get_week_status($week) {
        if ($week <= 0) {
            return ['class' => 'status-unknown', 'icon' => '-', 'label' => 'Non impostato'];
        }
        if ($week <= 2) {
            return ['class' => 'status-new', 'icon' => '🆕', 'label' => 'Nuovo'];
        }
        if ($week <= 4) {
            return ['class' => 'status-progress', 'icon' => '⏳', 'label' => 'In corso'];
        }
        if ($week <= 6) {
            return ['class' => 'status-ending', 'icon' => '⚠️', 'label' => 'Fine vicina'];
        }
        return ['class' => 'status-extended', 'icon' => '🔴', 'label' => 'Prolungo'];
    }

    /**
     * Export student data for report.
     *
     * @param int $studentid Student ID.
     * @return array Data array for export.
     */
    public static function export_student_data($studentid) {
        $student = self::get_student($studentid);
        if (!$student) {
            return null;
        }

        $report = self::get_report($studentid);

        return [
            'student' => $student,
            'report' => $report,
            'week' => self::calculate_week_number($student->date_start),
            'sector_name' => profession_mapper::get_sector_name($student->sector_detected),
        ];
    }

    /**
     * Get available coaches.
     *
     * @return array Array of coach records.
     */
    public static function get_coaches() {
        global $DB;

        // Try to get coaches from local_ftm_coaches first (scheduler).
        $coaches = [];
        $dbman = $DB->get_manager();

        if ($dbman->table_exists('local_ftm_coaches')) {
            $coaches = $DB->get_records_sql(
                "SELECT c.userid, u.firstname, u.lastname, c.initials
                 FROM {local_ftm_coaches} c
                 JOIN {user} u ON u.id = c.userid
                 WHERE c.active = 1 AND c.role = 'coach'
                 ORDER BY u.lastname, u.firstname"
            );
        }

        // If no coaches found, get from role assignments.
        if (empty($coaches)) {
            $coachroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            if ($coachroleid) {
                $coaches = $DB->get_records_sql(
                    "SELECT DISTINCT u.id as userid, u.firstname, u.lastname, '' as initials
                     FROM {user} u
                     JOIN {role_assignments} ra ON ra.userid = u.id
                     WHERE ra.roleid = :roleid AND u.deleted = 0
                     ORDER BY u.lastname, u.firstname",
                    ['roleid' => $coachroleid]
                );
            }
        }

        return $coaches;
    }

    /**
     * Assign coach to student.
     * Uses shared local_student_coaching table.
     *
     * @param int $userid Moodle user ID.
     * @param int $coachid Coach user ID.
     * @param int $courseid Course ID (optional).
     * @return bool Success.
     */
    public static function assign_coach($userid, $coachid, $courseid = 0) {
        global $DB;

        // Check if coaching record exists.
        $existing = $DB->get_record('local_student_coaching', [
            'userid' => $userid,
            'courseid' => $courseid
        ]);

        $now = time();

        if ($existing) {
            // Update existing record.
            $existing->coachid = $coachid;
            $existing->timemodified = $now;
            return $DB->update_record('local_student_coaching', $existing);
        } else {
            // Create new record.
            $record = new \stdClass();
            $record->userid = $userid;
            $record->coachid = $coachid;
            $record->courseid = $courseid;
            $record->status = 'active';
            $record->current_week = 1;
            $record->date_start = $now;
            $record->date_end = $now + (6 * 7 * 24 * 60 * 60); // 6 weeks.
            $record->timecreated = $now;
            $record->timemodified = $now;
            return $DB->insert_record('local_student_coaching', $record);
        }
    }

    /**
     * Get student's assigned coach.
     *
     * @param int $userid Moodle user ID.
     * @return object|false Coach user record or false.
     */
    public static function get_student_coach($userid) {
        global $DB;

        return $DB->get_record_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email
             FROM {local_student_coaching} sc
             JOIN {user} u ON u.id = sc.coachid
             WHERE sc.userid = :userid AND sc.status = 'active'",
            ['userid' => $userid]
        );
    }

    /**
     * Get student sectors (multi-sector support).
     * Uses shared local_student_sectors table.
     *
     * @param int $userid Moodle user ID.
     * @return array Array of sector records.
     */
    public static function get_student_sectors($userid) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            // Fallback to cpurc single sector.
            $student = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
            if ($student && !empty($student->sector_detected)) {
                return [(object)[
                    'sector' => $student->sector_detected,
                    'is_primary' => 1,
                    'source' => 'cpurc',
                    'quiz_count' => 0
                ]];
            }
            return [];
        }

        return $DB->get_records_sql(
            "SELECT *
             FROM {local_student_sectors}
             WHERE userid = :userid
             ORDER BY is_primary DESC, quiz_count DESC, timecreated ASC",
            ['userid' => $userid]
        );
    }

    /**
     * Set student sectors (primary, secondary, tertiary).
     *
     * @param int $userid Moodle user ID.
     * @param string $primary Primary sector code.
     * @param string $secondary Secondary sector code (optional).
     * @param string $tertiary Tertiary sector code (optional).
     * @param int $courseid Course ID (optional).
     * @return bool Success.
     */
    public static function set_student_sectors($userid, $primary, $secondary = '', $tertiary = '', $courseid = 0) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_sectors')) {
            // Fallback: update cpurc table only.
            $student = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
            if ($student) {
                $student->sector_detected = $primary;
                $student->timemodified = time();
                return $DB->update_record('local_ftm_cpurc_students', $student);
            }
            return false;
        }

        $now = time();

        // Clear all existing is_primary flags for this user.
        $DB->execute(
            "UPDATE {local_student_sectors} SET is_primary = 0, timemodified = :time WHERE userid = :userid",
            ['time' => $now, 'userid' => $userid]
        );

        // Helper function to upsert sector.
        $upsertSector = function($sector, $isPrimary, $rank) use ($DB, $userid, $courseid, $now) {
            if (empty($sector)) {
                return;
            }

            $existing = $DB->get_record('local_student_sectors', [
                'userid' => $userid,
                'courseid' => $courseid,
                'sector' => $sector
            ]);

            if ($existing) {
                $existing->is_primary = $isPrimary;
                $existing->source = 'manual';
                $existing->timemodified = $now;
                $DB->update_record('local_student_sectors', $existing);
            } else {
                $record = new \stdClass();
                $record->userid = $userid;
                $record->courseid = $courseid;
                $record->sector = $sector;
                $record->is_primary = $isPrimary;
                $record->source = 'manual';
                $record->quiz_count = 0;
                $record->first_detected = $now;
                $record->last_detected = $now;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_student_sectors', $record);
            }
        };

        // Set sectors with proper priority.
        $upsertSector($primary, 1, 1);
        $upsertSector($secondary, 0, 2);
        $upsertSector($tertiary, 0, 3);

        // Also update cpurc student table for compatibility.
        $student = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
        if ($student) {
            $student->sector_detected = $primary;
            $student->timemodified = $now;
            $DB->update_record('local_ftm_cpurc_students', $student);
        }

        // Sync with coaching table.
        self::sync_coaching_sector($userid, $courseid, $primary);

        return true;
    }

    /**
     * Sync sector to coaching table.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param string $sector Sector code.
     */
    private static function sync_coaching_sector($userid, $courseid, $sector) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_student_coaching')) {
            return;
        }

        $now = time();

        if ($courseid > 0) {
            $DB->execute(
                "UPDATE {local_student_coaching} SET sector = :sector, timemodified = :time
                 WHERE userid = :userid AND courseid = :courseid",
                ['sector' => $sector, 'time' => $now, 'userid' => $userid, 'courseid' => $courseid]
            );
        } else {
            $DB->execute(
                "UPDATE {local_student_coaching} SET sector = :sector, timemodified = :time
                 WHERE userid = :userid",
                ['sector' => $sector, 'time' => $now, 'userid' => $userid]
            );
        }
    }

    /**
     * Get all students with complete reports for bulk export.
     *
     * @return array Array of student IDs.
     */
    public static function get_students_with_reports() {
        global $DB;

        return $DB->get_records_sql(
            "SELECT cs.id, cs.userid, u.firstname, u.lastname
             FROM {local_ftm_cpurc_students} cs
             JOIN {user} u ON u.id = cs.userid
             JOIN {local_ftm_cpurc_reports} r ON r.studentid = cs.id
             WHERE r.status IN ('draft', 'final', 'sent')
             ORDER BY u.lastname, u.firstname"
        );
    }
}
