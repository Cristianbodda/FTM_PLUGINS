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
     * Create default action plan with 12 areas (CI v2).
     * Includes week_start/week_end, area_type, target_global.
     *
     * @param int $enrollmentid
     */
    private static function create_default_action_plan($enrollmentid) {
        global $DB;
        $now = time();
        $areas = \local_ftm_sip_get_activation_areas();

        // Hardcoded IT strings to avoid [[missing_key]] if lang file is outdated on server.
        $default_objectives = [
            'target_companies'         => 'Creare e mantenere una lista esaustiva di aziende target',
            'mandatory_searches'       => 'Aumentare il numero e la qualità delle ricerche di lavoro',
            'search_channels'          => 'Aumentare i canali di ricerca utilizzati dalla PCI',
            'social_network'           => 'Attivare e utilizzare LinkedIn, Facebook Lavoro, ecc.',
            'personal_network'         => 'Attivare e monitorare la propria rete di contatti',
            'targeted_applications'    => 'Aumentare il numero di candidature ad annunci mirati',
            'unsolicited_applications' => 'Ampliare le opportunità tramite autocandidature',
            'agencies_urc'             => 'Attivare nuove agenzie interinali e utilizzare Job-Room',
            'interview_training'       => 'Prepararsi ai colloqui con simulazioni e feedback',
            'stage_trials'             => 'Ottenere opportunità di stage o giorni di prova',
            'strategy_improvement'     => "Migliorare progressivamente le strategie di ricerca d'impiego",
            'growing_autonomy'         => 'Raggiungere piena autonomia nella gestione della ricerca di lavoro',
        ];
        $default_verifications = [
            'target_companies'         => 'Numero aziende target identificate',
            'mandatory_searches'       => 'Numero ricerche effettuate per settimana',
            'search_channels'          => 'Numero canali attivati e utilizzati',
            'social_network'           => 'Profili attivati e attività di ricerca sui social',
            'personal_network'         => 'Numero contatti personali attivati',
            'targeted_applications'    => 'Numero candidature inviate ad annunci mirati',
            'unsolicited_applications' => 'Numero autocandidature inviate',
            'agencies_urc'             => 'Numero agenzie contattate e iscrizioni attive',
            'interview_training'       => 'Numero colloqui di prova effettuati',
            'stage_trials'             => 'Numero stage/prove attivati',
            'strategy_improvement'     => 'Valutazione coach settimanale (1-10)',
            'growing_autonomy'         => 'Valutazione coach settimanale (1-10)',
        ];

        foreach ($areas as $key => $area) {
            $record = new \stdClass();
            $record->enrollmentid = $enrollmentid;
            $record->area_key = $key;
            $record->week_start = $area['week_start'] ?? 1;
            $record->week_end = $area['week_end'] ?? 10;
            $record->area_type = $area['type'] ?? 'quantitative';
            $record->target_global = $area['default_target'] ?? 0;
            $record->objective = $default_objectives[$key] ?? '';
            $record->verification = $default_verifications[$key] ?? '';
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

        // Calculate week from the actual meeting date, not from today.
        $sip_week = \local_ftm_sip_calculate_week($enrollment->date_start, $meeting_date);

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

    // =========================================================================
    // CI v2.0 — ACCEPTANCE FORM OPERATIONS
    // =========================================================================

    /**
     * Create default acceptance records for 12 areas.
     *
     * @param int $enrollmentid
     */
    public static function create_default_acceptance($enrollmentid) {
        global $DB;
        $now = time();
        $areas = \local_ftm_sip_get_activation_areas();

        foreach ($areas as $key => $area) {
            if ($DB->record_exists('local_ftm_sip_acceptance', ['enrollmentid' => $enrollmentid, 'area_key' => $key])) {
                continue;
            }
            $record = new \stdClass();
            $record->enrollmentid = $enrollmentid;
            $record->area_key = $key;
            $record->accepted = 0;
            $record->baseline_value = 0;
            $record->target_value = $area['default_target'] ?? 0;
            $record->actual_value = 0;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_ftm_sip_acceptance', $record);
        }
    }

    /**
     * Get acceptance form data for an enrollment.
     *
     * @param int $enrollmentid
     * @return array area_key => record
     */
    public static function get_acceptance($enrollmentid) {
        global $DB;
        $records = $DB->get_records('local_ftm_sip_acceptance', ['enrollmentid' => $enrollmentid]);
        $result = [];
        foreach ($records as $r) {
            $result[$r->area_key] = $r;
        }
        return $result;
    }

    /**
     * Save a single acceptance item.
     *
     * @param int $acceptanceid Record ID
     * @param int $accepted 0 or 1
     * @param int $baseline Situazione partenza
     * @param int $target Obiettivo 10 settimane
     */
    /**
     * Salva un item acceptance.
     * baseline_total / target_total = totale 10 settimane (es. 30 aziende, 12 lettere)
     * baseline_week / target_week = valore per settimana (decisi separatamente, non calcolati)
     *
     * @param int $acceptanceid
     * @param int $accepted 0/1
     * @param float $baseline_total Sit. partenza (totale 10 sett.)
     * @param float $target_total Obiettivo (totale 10 sett.)
     * @param float $baseline_week Sit. partenza (per settimana)
     * @param float $target_week Obiettivo (per settimana)
     */
    public static function save_acceptance_item($acceptanceid, $accepted, $baseline_total, $target_total,
                                                 $baseline_week = 0, $target_week = 0) {
        global $DB;
        $record = new \stdClass();
        $record->id = $acceptanceid;
        $record->accepted = $accepted ? 1 : 0;
        $record->baseline_value = max(0, (float)$baseline_total);
        $record->target_value = max(0, (float)$target_total);
        $record->baseline_per_week = max(0, (float)$baseline_week);
        $record->target_per_week = max(0, (float)$target_week);
        $record->timemodified = time();
        $DB->update_record('local_ftm_sip_acceptance', $record);

        // Propaga al Piano d'Azione: target_global = target totale (NO moltiplicazione).
        $accrow = $DB->get_record('local_ftm_sip_acceptance', ['id' => $acceptanceid], 'enrollmentid, area_key');
        if ($accrow) {
            self::sync_acceptance_to_action_plan_area(
                (int) $accrow->enrollmentid,
                $accrow->area_key,
                (float) $record->baseline_value,
                (float) $record->target_value,
                (float) $record->baseline_per_week,
                (float) $record->target_per_week
            );
        }
    }

    /**
     * Propaga i valori acceptance al action_plan corrispondente.
     * target_global = target_total (deciso dal coach, NO calcoli).
     *
     * @param int $enrollmentid
     * @param string $area_key
     * @param float $baseline_total
     * @param float $target_total
     * @param float $baseline_week
     * @param float $target_week
     */
    public static function sync_acceptance_to_action_plan_area($enrollmentid, $area_key,
                                                                $baseline_total, $target_total,
                                                                $baseline_week = 0, $target_week = 0) {
        global $DB;

        $now = time();
        $target_global = (int) round($target_total);

        $existing = $DB->get_record('local_ftm_sip_action_plan', [
            'enrollmentid' => $enrollmentid,
            'area_key' => $area_key,
        ]);

        // Componi testo obiettivo descrittivo.
        $objparts = [];
        if ($target_total > 0) {
            $objparts[] = 'Obiettivo: ' . self::fmt_num($target_total) . ' in 10 settimane';
        }
        if ($target_week > 0) {
            $objparts[] = '(' . self::fmt_num($target_week) . '/sett)';
        }
        if ($baseline_total > 0 || $baseline_week > 0) {
            $bp = [];
            if ($baseline_total > 0) {
                $bp[] = self::fmt_num($baseline_total) . ' totali';
            }
            if ($baseline_week > 0) {
                $bp[] = self::fmt_num($baseline_week) . '/sett';
            }
            $objparts[] = 'Partenza: ' . implode(', ', $bp);
        }
        $objtext = implode(' ', $objparts);

        if ($existing) {
            $update = new \stdClass();
            $update->id = $existing->id;
            $update->target_global = $target_global;
            // Precompila objective solo se vuoto (per non sovrascrivere note coach).
            if (empty(trim((string) ($existing->objective ?? '')))) {
                $update->objective = $objtext;
            }
            $update->timemodified = $now;
            $DB->update_record('local_ftm_sip_action_plan', $update);
        } else {
            $rec = new \stdClass();
            $rec->enrollmentid = $enrollmentid;
            $rec->area_key = $area_key;
            $rec->level_initial = null;
            $rec->level_current = null;
            $rec->target_global = $target_global;
            $rec->objective = $objtext;
            $rec->actions_agreed = null;
            $rec->verification = null;
            $rec->notes = null;
            $rec->week_start = 1;
            $rec->week_end = LOCAL_FTM_SIP_TOTAL_WEEKS;
            $rec->area_type = 'quantitative';
            $rec->timecreated = $now;
            $rec->timemodified = $now;
            $DB->insert_record('local_ftm_sip_action_plan', $rec);
        }
    }

    /**
     * Format helper: rimuove decimali se sono zero (es. 5.00 → 5, 1.50 → 1.5).
     */
    private static function fmt_num($n) {
        $n = (float) $n;
        return ($n == (int) $n) ? (string)(int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /**
     * Sync esplicito di tutti gli acceptance items di un enrollment al action_plan.
     *
     * @param int $enrollmentid
     * @return int Numero righe sincronizzate
     */
    public static function sync_all_acceptance_to_action_plan($enrollmentid) {
        global $DB;
        $items = $DB->get_records('local_ftm_sip_acceptance', ['enrollmentid' => $enrollmentid]);
        $count = 0;
        foreach ($items as $item) {
            self::sync_acceptance_to_action_plan_area(
                $enrollmentid,
                $item->area_key,
                (float) ($item->baseline_value ?? 0),
                (float) ($item->target_value ?? 0),
                (float) ($item->baseline_per_week ?? 0),
                (float) ($item->target_per_week ?? 0)
            );
            $count++;
        }
        return $count;
    }

    /**
     * Update actual_value for an acceptance item (called when tracking entries change).
     *
     * @param int $enrollmentid
     * @param string $area_key
     */
    public static function update_acceptance_actual($enrollmentid, $area_key) {
        global $DB;
        $count = $DB->count_records('local_ftm_sip_search_entries', [
            'enrollmentid' => $enrollmentid,
            'area_key' => $area_key,
        ]);
        $acceptance = $DB->get_record('local_ftm_sip_acceptance', [
            'enrollmentid' => $enrollmentid,
            'area_key' => $area_key,
        ]);
        if ($acceptance) {
            $DB->set_field('local_ftm_sip_acceptance', 'actual_value', $count, ['id' => $acceptance->id]);
        }
    }

    // =========================================================================
    // CI v2.0 — SEARCH ENTRIES (WEEKLY TRACKING)
    // =========================================================================

    /**
     * Create a search/contact entry for a specific area and week.
     *
     * @param int $enrollmentid
     * @param string $area_key
     * @param int $sip_week
     * @param array $data Entry data fields
     * @param int $addedby User ID
     * @return int New record ID
     */
    public static function create_search_entry($enrollmentid, $area_key, $sip_week, array $data, $addedby) {
        global $DB;
        $now = time();

        $record = new \stdClass();
        $record->enrollmentid = $enrollmentid;
        $record->area_key = $area_key;
        $record->sip_week = max(1, min(10, (int)$sip_week));
        $record->entry_date = !empty($data['entry_date']) ? (int)$data['entry_date'] : $now;
        $record->company_name = $data['company_name'] ?? null;
        $record->company_address = $data['company_address'] ?? null;
        $record->company_email = $data['company_email'] ?? null;
        $record->company_phone = $data['company_phone'] ?? null;
        $record->contact_person = $data['contact_person'] ?? null;
        $record->position = $data['position'] ?? null;
        $record->urc_assigned = !empty($data['urc_assigned']) ? 1 : 0;
        $record->occupation_fulltime = !empty($data['occupation_fulltime']) ? 1 : 0;
        $record->occupation_parttime = !empty($data['occupation_parttime']) ? 1 : 0;
        $record->method_letter = !empty($data['method_letter']) ? 1 : 0;
        $record->method_person = !empty($data['method_person']) ? 1 : 0;
        $record->method_phone = !empty($data['method_phone']) ? 1 : 0;
        $record->result = $data['result'] ?? 'pending';
        $record->result_reason = $data['result_reason'] ?? null;
        $record->channel = $data['channel'] ?? null;
        $record->notes = $data['notes'] ?? null;
        $record->addedby = $addedby;
        $record->timecreated = $now;
        $record->timemodified = $now;

        // Auto-add company to shared registry.
        if (!empty($record->company_name)) {
            $companyid = self::find_or_create_company(
                $record->company_name,
                $addedby,
                null,
                null
            );
            $record->companyid = $companyid;

            // Update company details if provided.
            if ($companyid && (!empty($record->company_email) || !empty($record->company_phone) || !empty($record->company_address))) {
                $company = $DB->get_record('local_ftm_sip_companies', ['id' => $companyid]);
                $updated = false;
                if (empty($company->email) && !empty($record->company_email)) {
                    $company->email = $record->company_email;
                    $updated = true;
                }
                if (empty($company->phone) && !empty($record->company_phone)) {
                    $company->phone = $record->company_phone;
                    $updated = true;
                }
                if (empty($company->address) && !empty($record->company_address)) {
                    $company->address = $record->company_address;
                    $updated = true;
                }
                if (!empty($record->contact_person) && empty($company->contact_person)) {
                    $company->contact_person = $record->contact_person;
                    $updated = true;
                }
                if ($updated) {
                    $company->timemodified = $now;
                    $company->last_contact_date = $now;
                    $DB->update_record('local_ftm_sip_companies', $company);
                }
            }
        }

        $id = $DB->insert_record('local_ftm_sip_search_entries', $record);

        // Update acceptance actual count.
        self::update_acceptance_actual($enrollmentid, $area_key);

        return $id;
    }

    /**
     * Get search entries for an enrollment, optionally filtered by area and week.
     *
     * @param int $enrollmentid
     * @param string $area_key Optional
     * @param int $sip_week Optional
     * @return array
     */
    public static function get_search_entries($enrollmentid, $area_key = '', $sip_week = 0) {
        global $DB;
        $conditions = ['enrollmentid' => $enrollmentid];
        if (!empty($area_key)) {
            $conditions['area_key'] = $area_key;
        }
        if ($sip_week > 0) {
            $conditions['sip_week'] = $sip_week;
        }
        return $DB->get_records('local_ftm_sip_search_entries', $conditions, 'sip_week ASC, entry_date ASC');
    }

    /**
     * Get weekly summary counts for all areas.
     *
     * @param int $enrollmentid
     * @return array area_key => [week1 => count, week2 => count, ...]
     */
    public static function get_weekly_summary($enrollmentid) {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_ftm_sip_search_entries')) {
            return [];
        }
        $sql = "SELECT area_key, sip_week, COUNT(*) AS cnt
                FROM {local_ftm_sip_search_entries}
                WHERE enrollmentid = ?
                GROUP BY area_key, sip_week
                ORDER BY area_key, sip_week";
        $rs = $DB->get_recordset_sql($sql, [$enrollmentid]);

        $summary = [];
        foreach ($rs as $r) {
            $summary[$r->area_key][$r->sip_week] = (int)$r->cnt;
        }
        $rs->close();
        return $summary;
    }

    /**
     * Delete a search entry.
     *
     * @param int $entryid
     * @param int $enrollmentid For security check
     */
    public static function delete_search_entry($entryid, $enrollmentid) {
        global $DB;
        $entry = $DB->get_record('local_ftm_sip_search_entries', ['id' => $entryid, 'enrollmentid' => $enrollmentid]);
        if ($entry) {
            $DB->delete_records('local_ftm_sip_search_entries', ['id' => $entryid]);
            self::update_acceptance_actual($enrollmentid, $entry->area_key);
        }
    }

    // =========================================================================
    // CI v2.0 — COACH WEEKLY EVALUATIONS (STRATEGY + AUTONOMY)
    // =========================================================================

    /**
     * Save or update a weekly coach evaluation (1-10).
     *
     * @param int $enrollmentid
     * @param string $area_key 'strategy_improvement' or 'growing_autonomy'
     * @param int $sip_week 1-10
     * @param int $score 1-10
     * @param int $coachid
     * @param string $notes Optional
     */
    public static function save_coach_eval($enrollmentid, $area_key, $sip_week, $score, $coachid, $notes = '') {
        global $DB;
        $now = time();
        $score = max(1, min(10, (int)$score));

        $existing = $DB->get_record('local_ftm_sip_coach_evals', [
            'enrollmentid' => $enrollmentid,
            'area_key' => $area_key,
            'sip_week' => $sip_week,
        ]);

        if ($existing) {
            $existing->score = $score;
            $existing->coachid = $coachid;
            $existing->notes = $notes;
            $existing->timemodified = $now;
            $DB->update_record('local_ftm_sip_coach_evals', $existing);
        } else {
            $record = new \stdClass();
            $record->enrollmentid = $enrollmentid;
            $record->area_key = $area_key;
            $record->sip_week = $sip_week;
            $record->score = $score;
            $record->coachid = $coachid;
            $record->notes = $notes;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_ftm_sip_coach_evals', $record);
        }
    }

    /**
     * Get all coach evaluations for an enrollment.
     *
     * @param int $enrollmentid
     * @return array area_key => [week => score]
     */
    public static function get_coach_evals($enrollmentid) {
        global $DB;
        $records = $DB->get_records('local_ftm_sip_coach_evals', ['enrollmentid' => $enrollmentid]);
        $result = [];
        foreach ($records as $r) {
            $result[$r->area_key][$r->sip_week] = (object)[
                'id' => $r->id,
                'score' => (int)$r->score,
                'notes' => $r->notes,
                'coachid' => $r->coachid,
            ];
        }
        return $result;
    }

    // =========================================================================
    // CI v2.0 — SEARCH PROOFS (PDF UPLOAD)
    // =========================================================================

    /**
     * Save a search proof (PDF Job-Room upload) reference.
     *
     * @param int $enrollmentid
     * @param string $month_year Format: YYYY-MM
     * @param string $filename
     * @param string $contenthash Moodle file contenthash
     * @param int $filesize
     * @param int $uploadedby
     * @return int
     */
    public static function save_search_proof($enrollmentid, $month_year, $filename, $contenthash, $filesize, $uploadedby) {
        global $DB;
        $record = new \stdClass();
        $record->enrollmentid = $enrollmentid;
        $record->month_year = $month_year;
        $record->filename = $filename;
        $record->contenthash = $contenthash;
        $record->filesize = $filesize;
        $record->uploadedby = $uploadedby;
        $record->timecreated = time();
        return $DB->insert_record('local_ftm_sip_search_proofs', $record);
    }

    /**
     * Get search proofs for an enrollment.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_search_proofs($enrollmentid) {
        global $DB;
        return $DB->get_records('local_ftm_sip_search_proofs', ['enrollmentid' => $enrollmentid], 'month_year DESC');
    }

    // =========================================================================
    // CI v2.0 — ENHANCED KPI SUMMARY
    // =========================================================================

    /**
     * Get enhanced KPI summary including v2 tracking data.
     *
     * @param int $enrollmentid
     * @return object
     */
    public static function get_kpi_summary_v2($enrollmentid) {
        global $DB;

        // Base KPI (from original tables).
        $kpi = self::get_kpi_summary($enrollmentid);

        // v2 tracking counts per area.
        $kpi->tracking_per_area = self::get_weekly_summary($enrollmentid); // guarded internally

        // Total entries across all areas.
        $kpi->total_entries = 0;
        if ($DB->get_manager()->table_exists('local_ftm_sip_search_entries')) {
            $kpi->total_entries = $DB->count_records('local_ftm_sip_search_entries', ['enrollmentid' => $enrollmentid]);
        }

        // Acceptance data.
        $kpi->acceptance_accepted = 0;
        $kpi->acceptance_total = 0;
        $kpi->acceptance_progress = [];
        if ($DB->get_manager()->table_exists('local_ftm_sip_acceptance')) {
            $acceptance = self::get_acceptance($enrollmentid);
            $kpi->acceptance_total = count($acceptance);
            foreach ($acceptance as $key => $a) {
                if ($a->accepted) {
                    $kpi->acceptance_accepted++;
                }
                $kpi->acceptance_progress[$key] = (object)[
                    'target' => (int)$a->target_value,
                    'actual' => (int)$a->actual_value,
                    'pct' => $a->target_value > 0 ? min(100, round(($a->actual_value / $a->target_value) * 100)) : 0,
                ];
            }
        }

        // Coach evaluations.
        $kpi->coach_evals = [];
        if ($DB->get_manager()->table_exists('local_ftm_sip_coach_evals')) {
            $kpi->coach_evals = self::get_coach_evals($enrollmentid);
        }

        // Search proofs count.
        $kpi->search_proofs = 0;
        if ($DB->get_manager()->table_exists('local_ftm_sip_search_proofs')) {
            $kpi->search_proofs = $DB->count_records('local_ftm_sip_search_proofs', ['enrollmentid' => $enrollmentid]);
        }

        return $kpi;
    }

    /**
     * Get all channel assessment records for an enrollment.
     * Returns assoc array: channel_key => record object.
     *
     * @param int $enrollmentid
     * @return array
     */
    public static function get_channel_assessment(int $enrollmentid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_ftm_sip_channel_assess')) {
            return [];
        }
        $rows = $DB->get_records('local_ftm_sip_channel_assess', ['enrollmentid' => $enrollmentid]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->channel_key] = $row;
        }
        return $result;
    }

    /**
     * Save (upsert) a channel assessment record.
     *
     * @param int    $enrollmentid
     * @param string $channel_key
     * @param array  $data  [level_initial, level_target, level_final, actions_text]
     * @param int    $userid
     * @return int  Record ID
     */
    public static function save_channel_assessment(int $enrollmentid, string $channel_key, array $data, int $userid): int {
        global $DB;
        $now = time();
        $existing = $DB->get_record('local_ftm_sip_channel_assess', [
            'enrollmentid' => $enrollmentid,
            'channel_key'  => $channel_key,
        ]);
        if ($existing) {
            $existing->level_initial  = (int)($data['level_initial'] ?? $existing->level_initial);
            $existing->level_target   = (int)($data['level_target']  ?? $existing->level_target);
            if (isset($data['level_final'])) {
                $existing->level_final = ($data['level_final'] === '' || $data['level_final'] === null)
                    ? null : (int)$data['level_final'];
            }
            $existing->actions_text   = $data['actions_text'] ?? $existing->actions_text;
            $existing->modifiedby     = $userid;
            $existing->timemodified   = $now;
            $DB->update_record('local_ftm_sip_channel_assess', $existing);
            return (int)$existing->id;
        } else {
            $record = (object)[
                'enrollmentid' => $enrollmentid,
                'channel_key'  => $channel_key,
                'level_initial' => (int)($data['level_initial'] ?? 0),
                'level_target'  => (int)($data['level_target']  ?? 0),
                'level_final'   => isset($data['level_final']) && $data['level_final'] !== ''
                    ? (int)$data['level_final'] : null,
                'actions_text'  => $data['actions_text'] ?? '',
                'createdby'     => $userid,
                'modifiedby'    => $userid,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ];
            return (int)$DB->insert_record('local_ftm_sip_channel_assess', $record);
        }
    }
}
