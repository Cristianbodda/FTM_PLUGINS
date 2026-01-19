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

        // Date range filter.
        if (!empty($filters['date_from'])) {
            $where[] = "cs.date_start >= :datefrom";
            $params['datefrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "cs.date_start <= :dateto";
            $params['dateto'] = $filters['date_to'];
        }

        $wheresql = implode(' AND ', $where);

        // Build query with all required user fields for fullname.
        $sql = "SELECT cs.*, u.id as moodleuserid, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       r.id as reportid, r.status as report_status
                FROM {local_ftm_cpurc_students} cs
                JOIN {user} u ON u.id = cs.userid
                LEFT JOIN {local_ftm_cpurc_reports} r ON r.studentid = cs.id
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
}
