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
 * SIP Manager - core CRUD operations for Sostegno Individuale Personalizzato.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_sip;

defined('MOODLE_INTERNAL') || die();

/**
 * Core manager class for SIP operations.
 */
class sip_manager {

    // Enrollment statuses.
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    // Plan statuses.
    const PLAN_DRAFT = 'draft';
    const PLAN_ACTIVE = 'active';
    const PLAN_FROZEN = 'frozen';

    // Action statuses.
    const ACTION_PENDING = 'pending';
    const ACTION_IN_PROGRESS = 'in_progress';
    const ACTION_COMPLETED = 'completed';
    const ACTION_NOT_DONE = 'not_done';

    // Appointment statuses.
    const APPT_SCHEDULED = 'scheduled';
    const APPT_CONFIRMED = 'confirmed';
    const APPT_COMPLETED = 'completed';
    const APPT_CANCELLED = 'cancelled';
    const APPT_NO_SHOW = 'no_show';

    // SIP color.
    const SIP_COLOR = '#0891B2';
    const SIP_COLOR_BG = '#ECFEFF';
    const SIP_COLOR_BORDER = '#06B6D4';
    const SIP_COLOR_TEXT = '#155E75';

    // 7 activation area keys.
    const AREAS = [
        'professional_strategy',
        'job_monitoring',
        'targeted_applications',
        'unsolicited_applications',
        'direct_company_contact',
        'personal_network',
        'intermediaries',
    ];

    // =========================================================================
    // ENROLLMENT OPERATIONS
    // =========================================================================

    /**
     * Activate SIP for a student.
     *
     * @param int $userid Student user ID.
     * @param int $coachid Coach user ID.
     * @param int $activatedby Who activated (coach or secretary).
     * @param string $motivation Motivation text.
     * @param int $date_start Start date timestamp.
     * @param string|null $sector Primary sector.
     * @return int Enrollment ID.
     */
    public static function activate_sip($userid, $coachid, $activatedby, $motivation, $date_start, $sector = null) {
        global $DB;

        // Check if already enrolled.
        $existing = $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
        if ($existing && $existing->status === self::STATUS_ACTIVE) {
            throw new \moodle_exception('error_already_enrolled', 'local_ftm_sip');
        }

        $now = time();
        $date_end_planned = $date_start + (LOCAL_FTM_SIP_TOTAL_WEEKS * 7 * 86400);

        // Determine plan status: if action plan was pre-filled (draft), keep it; otherwise start as draft.
        $plan_status = self::PLAN_DRAFT;

        if ($existing) {
            // Reactivation: update existing record.
            $existing->coachid = $coachid;
            $existing->activatedby = $activatedby;
            $existing->motivation = $motivation;
            $existing->date_start = $date_start;
            $existing->date_end_planned = $date_end_planned;
            $existing->date_end_actual = null;
            $existing->current_phase = 1;
            $existing->status = self::STATUS_ACTIVE;
            $existing->plan_status = self::PLAN_ACTIVE;
            $existing->sector = $sector;
            $existing->outcome = null;
            $existing->outcome_company = null;
            $existing->outcome_date = null;
            $existing->outcome_notes = null;
            $existing->timemodified = $now;
            $DB->update_record('local_ftm_sip_enrollments', $existing);

            // Freeze initial levels if action plan exists.
            self::freeze_baseline($existing->id);

            return $existing->id;
        }

        // New enrollment.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->coachid = $coachid;
        $record->activatedby = $activatedby;
        $record->motivation = $motivation;
        $record->date_start = $date_start;
        $record->date_end_planned = $date_end_planned;
        $record->current_phase = 1;
        $record->status = self::STATUS_ACTIVE;
        $record->student_visible = 0;
        $record->plan_status = self::PLAN_ACTIVE;
        $record->sector = $sector;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $enrollmentid = $DB->insert_record('local_ftm_sip_enrollments', $record);

        // Create the 7 action plan areas with default objectives.
        self::create_default_action_plan($enrollmentid);

        // Create the 6 phase note records.
        self::create_phase_notes($enrollmentid);

        return $enrollmentid;
    }

    /**
     * Prepare SIP plan in draft mode (during the 6 weeks).
     *
     * @param int $userid Student user ID.
     * @param int $coachid Coach user ID.
     * @param string|null $sector Primary sector.
     * @return int Enrollment ID.
     */
    public static function prepare_sip_draft($userid, $coachid, $sector = null) {
        global $DB;

        $existing = $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
        if ($existing) {
            return $existing->id;
        }

        $now = time();
        $record = new \stdClass();
        $record->userid = $userid;
        $record->coachid = $coachid;
        $record->activatedby = $coachid;
        $record->motivation = '';
        $record->date_start = 0;
        $record->current_phase = 0;
        $record->status = self::STATUS_SUSPENDED; // Not active yet.
        $record->student_visible = 0;
        $record->plan_status = self::PLAN_DRAFT;
        $record->sector = $sector;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $enrollmentid = $DB->insert_record('local_ftm_sip_enrollments', $record);

        // Create 7 action plan areas.
        self::create_default_action_plan($enrollmentid);

        return $enrollmentid;
    }

    /**
     * Get enrollment by user ID.
     *
     * @param int $userid
     * @return object|false
     */
    public static function get_enrollment($userid) {
        global $DB;
        return $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
    }

    /**
     * Get enrollment by ID.
     *
     * @param int $id
     * @return object|false
     */
    public static function get_enrollment_by_id($id) {
        global $DB;
        return $DB->get_record('local_ftm_sip_enrollments', ['id' => $id]);
    }

    /**
     * Check if user has active SIP.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_sip_active($userid) {
        global $DB;
        return $DB->record_exists('local_ftm_sip_enrollments', [
            'userid' => $userid,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Check if user has any SIP enrollment (including draft).
     *
     * @param int $userid
     * @return bool
     */
    public static function has_sip($userid) {
        global $DB;
        return $DB->record_exists('local_ftm_sip_enrollments', ['userid' => $userid]);
    }

    /**
     * Get all active SIP enrollments for a coach.
     *
     * @param int $coachid
     * @return array
     */
    public static function get_coach_enrollments($coachid) {
        global $DB;
        $sql = "SELECT e.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename, u.email
               FROM {local_ftm_sip_enrollments} e
               JOIN {user} u ON u.id = e.userid
               WHERE e.coachid = :coachid AND e.status = :status AND u.deleted = 0
               ORDER BY e.date_start DESC";
        return $DB->get_records_sql($sql, [
            'coachid' => $coachid,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Get all SIP enrollments (for secretary dashboard).
     *
     * @param string|null $status Filter by status.
     * @return array
     */
    public static function get_all_enrollments($status = null) {
        global $DB;
        $params = [];
        $where = 'u.deleted = 0';
        if ($status) {
            $where .= ' AND e.status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT e.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename, u.email,
               c.firstname AS coach_firstname, c.lastname AS coach_lastname
               FROM {local_ftm_sip_enrollments} e
               JOIN {user} u ON u.id = e.userid
               LEFT JOIN {user} c ON c.id = e.coachid
               WHERE {$where}
               ORDER BY e.date_start DESC";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Update enrollment field.
     *
     * @param int $enrollmentid
     * @param string $field
     * @param mixed $value
     */
    public static function update_enrollment($enrollmentid, $field, $value) {
        global $DB;
        $allowed = ['status', 'student_visible', 'current_phase', 'plan_status',
                     'outcome', 'outcome_company', 'outcome_date', 'outcome_notes',
                     'date_end_actual', 'coachid', 'motivation', 'sector'];
        if (!in_array($field, $allowed)) {
            throw new \moodle_exception('error_invalid_data', 'local_ftm_sip');
        }
        $record = new \stdClass();
        $record->id = $enrollmentid;
        $record->{$field} = $value;
        $record->timemodified = time();
        $DB->update_record('local_ftm_sip_enrollments', $record);
    }

    /**
     * Toggle student visibility.
     *
     * @param int $enrollmentid
     * @param bool $visible
     */
    public static function set_student_visibility($enrollmentid, $visible) {
        self::update_enrollment($enrollmentid, 'student_visible', $visible ? 1 : 0);
    }

    /**
     * Get dashboard stats.
     *
     * @return object
     */
    public static function get_stats() {
        global $DB;
        $stats = new \stdClass();
        $stats->total = $DB->count_records('local_ftm_sip_enrollments');
        $stats->active = $DB->count_records('local_ftm_sip_enrollments', ['status' => self::STATUS_ACTIVE]);
        $stats->completed = $DB->count_records('local_ftm_sip_enrollments', ['status' => self::STATUS_COMPLETED]);
        $stats->draft = $DB->count_records('local_ftm_sip_enrollments', ['plan_status' => self::PLAN_DRAFT]);

        // Upcoming appointments (next 7 days).
        $now = time();
        $next_week = $now + (7 * 86400);
        $stats->upcoming_appointments = $DB->count_records_select(
            'local_ftm_sip_appointments',
            'appointment_date BETWEEN :now AND :next AND status IN (:s1, :s2)',
            ['now' => $now, 'next' => $next_week, 's1' => self::APPT_SCHEDULED, 's2' => self::APPT_CONFIRMED]
        );

        return $stats;
    }

    // =========================================================================
    // ACTION PLAN OPERATIONS
    // =========================================================================

    /**
     * Create default action plan with 7 areas.
     *
     * @param int $enrollmentid
     */
    private static function create_default_action_plan($enrollmentid) {
        global $DB;
        $now = time();
        $areas = \local_ftm_sip_get_activation_areas();

        foreach ($areas as $key => $area) {
            $record = new \stdClass();
            $record->enrollmentid = $enrollmentid;
            $record->area_key = $key;
            $record->objective = get_string($area['objective'], 'local_ftm_sip');
            $record->verification = get_string($area['verify'], 'local_ftm_sip');
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_ftm_sip_action_plan', $record);
        }
    }

    /**
     * Create 6 phase note records.
     *
     * @param int $enrollmentid
     */
    private static function create_phase_notes($enrollmentid) {
        global $DB;
        $now = time();
        for ($phase = 1; $phase <= LOCAL_FTM_SIP_TOTAL_PHASES; $phase++) {
            $record = new \stdClass();
            $record->enrollmentid = $enrollmentid;
            $record->phase = $phase;
            $record->objectives_met = 0;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_ftm_sip_phase_notes', $record);
        }
    }

    /**
     * Freeze initial levels as baseline when SIP is activated.
     *
     * @param int $enrollmentid
     */
    public static function freeze_baseline($enrollmentid) {
        global $DB;
        // level_initial values are already set during draft phase.
        // We just mark the plan as active so initial levels are no longer editable.
        $record = new \stdClass();
        $record->id = $enrollmentid;
        $record->plan_status = self::PLAN_ACTIVE;
        $record->timemodified = time();
        $DB->update_record('local_ftm_sip_enrollments', $record);
    }

    /**
     * Get action plan for an enrollment.
     *
     * @param int $enrollmentid
     * @return array Area key => record.
     */
    public static function get_action_plan($enrollmentid) {
        global $DB;
        $records = $DB->get_records('local_ftm_sip_action_plan', ['enrollmentid' => $enrollmentid]);
        $plan = [];
        foreach ($records as $record) {
            $plan[$record->area_key] = $record;
        }
        return $plan;
    }

    /**
     * Save activation level for an area.
     *
     * @param int $planid Action plan record ID.
     * @param string $field 'level_initial' or 'level_current'.
     * @param int $level Level 0-6.
     * @param int $changedby User ID.
     * @param string|null $notes Notes.
     */
    public static function save_area_level($planid, $field, $level, $changedby, $notes = null) {
        global $DB;

        if ($level < 0 || $level > 6) {
            throw new \moodle_exception('error_score_range', 'local_ftm_sip');
        }
        if (!in_array($field, ['level_initial', 'level_current'])) {
            throw new \moodle_exception('error_invalid_data', 'local_ftm_sip');
        }

        $plan = $DB->get_record('local_ftm_sip_action_plan', ['id' => $planid], '*', MUST_EXIST);

        // Check plan status: initial levels only editable in draft.
        if ($field === 'level_initial') {
            $enrollment = $DB->get_record('local_ftm_sip_enrollments',
                ['id' => $plan->enrollmentid], '*', MUST_EXIST);
            if ($enrollment->plan_status !== self::PLAN_DRAFT) {
                throw new \moodle_exception('error_permission', 'local_ftm_sip');
            }
        }

        $now = time();
        $old_level = $plan->{$field};

        // Update level.
        $plan->{$field} = $level;
        $plan->timemodified = $now;
        $DB->update_record('local_ftm_sip_action_plan', $plan);

        // Log history for current level changes.
        if ($field === 'level_current') {
            $history = new \stdClass();
            $history->planid = $planid;
            $history->level_before = $old_level;
            $history->level_after = $level;
            $history->changedby = $changedby;
            $history->notes = $notes;
            $history->timecreated = $now;
            $history->timemodified = $now;
            $DB->insert_record('local_ftm_sip_action_history', $history);
        }
    }

    /**
     * Save area objective and notes.
     *
     * @param int $planid
     * @param string $objective
     * @param string|null $notes
     * @param string|null $verification
     * @param string|null $actions_agreed JSON array.
     */
    public static function save_area_details($planid, $objective, $notes = null,
                                              $verification = null, $actions_agreed = null) {
        global $DB;
        $plan = $DB->get_record('local_ftm_sip_action_plan', ['id' => $planid], '*', MUST_EXIST);
        $plan->objective = $objective;
        $plan->notes = $notes;
        if ($verification !== null) {
            $plan->verification = $verification;
        }
        if ($actions_agreed !== null) {
            $plan->actions_agreed = $actions_agreed;
        }
        $plan->timemodified = time();
        $DB->update_record('local_ftm_sip_action_plan', $plan);
    }

    /**
     * Get level evolution history for an enrollment (for radar/chart).
     *
     * @param int $enrollmentid
     * @return array Area key => [history records].
     */
    public static function get_level_history($enrollmentid) {
        global $DB;
        $sql = "SELECT h.*, p.area_key
                FROM {local_ftm_sip_action_history} h
                JOIN {local_ftm_sip_action_plan} p ON p.id = h.planid
                WHERE p.enrollmentid = :enrollmentid
                ORDER BY h.timecreated ASC";
        $records = $DB->get_records_sql($sql, ['enrollmentid' => $enrollmentid]);

        $history = [];
        foreach ($records as $record) {
            $history[$record->area_key][] = $record;
        }
        return $history;
    }

    // =========================================================================
    // MEETING OPERATIONS
    // =========================================================================

    /**
     * Create a meeting record.
     *
     * @param int $enrollmentid
     * @param int $coachid
     * @param int $meeting_date
     * @param int|null $duration_minutes
     * @param string $modality presence|remote|phone|email
     * @param string|null $summary
     * @param string|null $notes
     * @return int Meeting ID.
     */
    public static function create_meeting($enrollmentid, $coachid, $meeting_date,
                                           $duration_minutes = null, $modality = 'presence',
                                           $summary = null, $notes = null) {
        global $DB;

        $enrollment = $DB->get_record('local_ftm_sip_enrollments',
            ['id' => $enrollmentid], '*', MUST_EXIST);

        $sip_week = \local_ftm_sip_calculate_week($enrollment->date_start);

        $now = time();
        $record = new \stdClass();
        $record->enrollmentid = $enrollmentid;
        $record->coachid = $coachid;
        $record->meeting_date = $meeting_date;
        $record->duration_minutes = $duration_minutes;
        $record->modality = $modality;
        $record->sip_week = $sip_week;
        $record->summary = $summary;
        $record->notes = $notes;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record('local_ftm_sip_meetings', $record);
    }

    /**
     * Get meetings for an enrollment.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_meetings($enrollmentid) {
        global $DB;
        return $DB->get_records('local_ftm_sip_meetings',
            ['enrollmentid' => $enrollmentid], 'meeting_date DESC');
    }

    // =========================================================================
    // ACTION (TASK) OPERATIONS
    // =========================================================================

    /**
     * Create an action assigned to a student.
     *
     * @param int $meetingid
     * @param int $enrollmentid
     * @param string $description
     * @param int|null $deadline
     * @return int Action ID.
     */
    public static function create_action($meetingid, $enrollmentid, $description, $deadline = null) {
        global $DB;
        $now = time();
        $record = new \stdClass();
        $record->meetingid = $meetingid;
        $record->enrollmentid = $enrollmentid;
        $record->description = $description;
        $record->deadline = $deadline;
        $record->status = self::ACTION_PENDING;
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record('local_ftm_sip_actions', $record);
    }

    /**
     * Update action status.
     *
     * @param int $actionid
     * @param string $status
     * @param int $completedby User ID.
     * @param string|null $coach_notes
     */
    public static function update_action_status($actionid, $status, $completedby, $coach_notes = null) {
        global $DB;
        $allowed = [self::ACTION_PENDING, self::ACTION_IN_PROGRESS, self::ACTION_COMPLETED, self::ACTION_NOT_DONE];
        if (!in_array($status, $allowed)) {
            throw new \moodle_exception('error_invalid_data', 'local_ftm_sip');
        }
        $now = time();
        $record = new \stdClass();
        $record->id = $actionid;
        $record->status = $status;
        $record->timemodified = $now;
        if ($status === self::ACTION_COMPLETED || $status === self::ACTION_NOT_DONE) {
            $record->completed_date = $now;
            $record->completed_by = $completedby;
        }
        if ($coach_notes !== null) {
            $record->coach_notes = $coach_notes;
        }
        $DB->update_record('local_ftm_sip_actions', $record);
    }

    /**
     * Get pending actions for an enrollment.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_pending_actions($enrollmentid) {
        global $DB;
        return $DB->get_records_select('local_ftm_sip_actions',
            'enrollmentid = :eid AND status IN (:s1, :s2)',
            ['eid' => $enrollmentid, 's1' => self::ACTION_PENDING, 's2' => self::ACTION_IN_PROGRESS],
            'deadline ASC, timecreated ASC');
    }

    /**
     * Get all actions for an enrollment.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_all_actions($enrollmentid) {
        global $DB;
        return $DB->get_records('local_ftm_sip_actions',
            ['enrollmentid' => $enrollmentid], 'timecreated DESC');
    }

    // =========================================================================
    // APPOINTMENT OPERATIONS
    // =========================================================================

    /**
     * Create an appointment.
     *
     * @param int $enrollmentid
     * @param int $coachid
     * @param int $studentid
     * @param int $appointment_date
     * @param string $time_start HH:MM
     * @param int $duration_minutes
     * @param string $modality
     * @param string|null $location
     * @param string|null $topic
     * @return int Appointment ID.
     */
    public static function create_appointment($enrollmentid, $coachid, $studentid,
                                               $appointment_date, $time_start,
                                               $duration_minutes = 60, $modality = 'presence',
                                               $location = null, $topic = null) {
        global $DB;
        $now = time();
        $record = new \stdClass();
        $record->enrollmentid = $enrollmentid;
        $record->coachid = $coachid;
        $record->studentid = $studentid;
        $record->appointment_date = $appointment_date;
        $record->time_start = $time_start;
        $record->duration_minutes = $duration_minutes;
        $record->modality = $modality;
        $record->location = $location;
        $record->topic = $topic;
        $record->status = self::APPT_SCHEDULED;
        $record->reminder_sent = 0;
        $record->reminder_coach_sent = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record('local_ftm_sip_appointments', $record);
    }

    /**
     * Get upcoming appointments for a coach.
     *
     * @param int $coachid
     * @param int $days_ahead
     * @return array
     */
    public static function get_coach_appointments($coachid, $days_ahead = 14) {
        global $DB;
        $now = time();
        $until = $now + ($days_ahead * 86400);
        $sql = "SELECT a.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename, u.email
               FROM {local_ftm_sip_appointments} a
               JOIN {user} u ON u.id = a.studentid
               WHERE a.coachid = :coachid
               AND a.appointment_date BETWEEN :now AND :until
               AND a.status IN (:s1, :s2)
               ORDER BY a.appointment_date ASC, a.time_start ASC";
        return $DB->get_records_sql($sql, [
            'coachid' => $coachid,
            'now' => $now,
            'until' => $until,
            's1' => self::APPT_SCHEDULED,
            's2' => self::APPT_CONFIRMED,
        ]);
    }

    /**
     * Get appointments for an enrollment.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_enrollment_appointments($enrollmentid) {
        global $DB;
        return $DB->get_records('local_ftm_sip_appointments',
            ['enrollmentid' => $enrollmentid], 'appointment_date DESC, time_start DESC');
    }

    /**
     * Get next appointment for a student.
     *
     * @param int $userid Student user ID.
     * @return object|false
     */
    public static function get_next_appointment($userid) {
        global $DB;
        $now = time();
        $sql = "SELECT a.*
                FROM {local_ftm_sip_appointments} a
                JOIN {local_ftm_sip_enrollments} e ON e.id = a.enrollmentid
                WHERE e.userid = :userid
                AND a.appointment_date >= :now
                AND a.status IN (:s1, :s2)
                ORDER BY a.appointment_date ASC, a.time_start ASC
                LIMIT 1";
        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'now' => $now,
            's1' => self::APPT_SCHEDULED,
            's2' => self::APPT_CONFIRMED,
        ]);
        return $records ? reset($records) : false;
    }

    // =========================================================================
    // KPI OPERATIONS
    // =========================================================================

    /**
     * Get KPI summary for an enrollment.
     *
     * @param int $enrollmentid
     * @return object
     */
    public static function get_kpi_summary($enrollmentid) {
        global $DB;

        $kpi = new \stdClass();
        $kpi->applications_total = $DB->count_records('local_ftm_sip_applications',
            ['enrollmentid' => $enrollmentid]);
        $kpi->applications_interview = $DB->count_records('local_ftm_sip_applications',
            ['enrollmentid' => $enrollmentid, 'status' => 'interview']);
        $kpi->contacts_total = $DB->count_records('local_ftm_sip_contacts',
            ['enrollmentid' => $enrollmentid]);
        $kpi->contacts_positive = $DB->count_records('local_ftm_sip_contacts',
            ['enrollmentid' => $enrollmentid, 'outcome' => 'positive']);
        $kpi->opportunities_total = $DB->count_records('local_ftm_sip_opportunities',
            ['enrollmentid' => $enrollmentid]);
        $kpi->opportunities_completed = $DB->count_records('local_ftm_sip_opportunities',
            ['enrollmentid' => $enrollmentid, 'status' => 'completed']);

        // Meetings count.
        $kpi->meetings_total = $DB->count_records('local_ftm_sip_meetings',
            ['enrollmentid' => $enrollmentid]);

        // Pending actions.
        $kpi->actions_pending = $DB->count_records_select('local_ftm_sip_actions',
            'enrollmentid = :eid AND status IN (:s1, :s2)',
            ['eid' => $enrollmentid, 's1' => self::ACTION_PENDING, 's2' => self::ACTION_IN_PROGRESS]);

        return $kpi;
    }

    // =========================================================================
    // COMPANY OPERATIONS
    // =========================================================================

    /**
     * Find or create a company by name (autocomplete/dedup).
     *
     * @param string $name Company name.
     * @param int $createdby User ID.
     * @param string|null $sector Sector.
     * @param string|null $city City.
     * @return int Company ID.
     */
    public static function find_or_create_company($name, $createdby, $sector = null, $city = null) {
        global $DB;

        $normalized = strtolower(trim($name));

        // Try to find existing.
        $existing = $DB->get_record('local_ftm_sip_companies',
            ['name_normalized' => $normalized]);
        if ($existing) {
            // Update interaction count.
            $existing->interaction_count++;
            $existing->timemodified = time();
            $DB->update_record('local_ftm_sip_companies', $existing);
            return $existing->id;
        }

        // Create new.
        $now = time();
        $record = new \stdClass();
        $record->name = trim($name);
        $record->name_normalized = $normalized;
        $record->sector = $sector;
        $record->city = $city;
        $record->status = 'prospect';
        $record->interaction_count = 1;
        $record->createdby = $createdby;
        $record->timecreated = $now;
        $record->timemodified = $now;
        return $DB->insert_record('local_ftm_sip_companies', $record);
    }

    /**
     * Search companies by name (for autocomplete).
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public static function search_companies($query, $limit = 10) {
        global $DB;
        $normalized = strtolower(trim($query));
        $sql = "SELECT * FROM {local_ftm_sip_companies}
                WHERE name_normalized LIKE :query
                ORDER BY interaction_count DESC, name ASC";
        return $DB->get_records_sql($sql, ['query' => '%' . $DB->sql_like_escape($normalized) . '%'], 0, $limit);
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    /**
     * Get enriched SIP data for a student (used by coach dashboard).
     *
     * @param int $userid
     * @return object|null SIP data with week, phase, next appointment, KPI summary.
     */
    public static function get_student_sip_data($userid) {
        $enrollment = self::get_enrollment($userid);
        if (!$enrollment || $enrollment->status === self::STATUS_CANCELLED) {
            return null;
        }

        $data = new \stdClass();
        $data->enrollment = $enrollment;
        $data->is_active = ($enrollment->status === self::STATUS_ACTIVE);
        $data->is_draft = ($enrollment->plan_status === self::PLAN_DRAFT);
        $data->current_week = 0;
        $data->current_phase = 0;
        $data->phase_name = '';

        if ($data->is_active && $enrollment->date_start > 0) {
            $data->current_week = \local_ftm_sip_calculate_week($enrollment->date_start);
            $data->current_phase = \local_ftm_sip_get_phase($data->current_week);
            $phases = \local_ftm_sip_get_phases();
            if (isset($phases[$data->current_phase])) {
                $data->phase_name = get_string($phases[$data->current_phase]['name'], 'local_ftm_sip');
            }
        }

        $data->next_appointment = self::get_next_appointment($userid);
        $data->kpi = self::get_kpi_summary($enrollment->id);

        return $data;
    }
}
