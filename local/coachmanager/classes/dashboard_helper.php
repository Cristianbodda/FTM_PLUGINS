<?php
// ============================================
// CoachManager - Dashboard Helper Class
// ============================================
// Fornisce i dati per la dashboard coach integrata
// Integra: competencymanager, selfassessment, labeval
// ============================================

namespace local_coachmanager;

defined('MOODLE_INTERNAL') || die();

class dashboard_helper {

    /** @var int Coach user ID */
    private $coachid;

    /** @var \moodle_database Database instance */
    private $db;

    /**
     * Constructor
     * @param int $coachid The coach user ID
     */
    public function __construct($coachid) {
        global $DB;
        $this->coachid = $coachid;
        $this->db = $DB;
    }

    /**
     * Get students assigned to this coach
     * @param int $courseid Filter by course
     * @param string $colorfilter Filter by group color
     * @param int $weekfilter Filter by week
     * @param string $statusfilter Filter by status
     * @param string $search Search term
     * @return array Array of student objects
     */
    public function get_my_students($courseid = 0, $colorfilter = '', $weekfilter = 0, $statusfilter = '', $search = '') {
        global $CFG;

        // Base query - students with quiz attempts
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                FROM {user} u
                JOIN {quiz_attempts} qa ON qa.userid = u.id
                WHERE qa.state = 'finished'
                AND u.deleted = 0";

        $params = [];

        // Course filter
        if ($courseid > 0) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM {quiz_attempts} qa2
                JOIN {quiz} q ON qa2.quiz = q.id
                WHERE qa2.userid = u.id AND q.course = ?
            )";
            $params[] = $courseid;
        }

        // Search filter
        if (!empty($search)) {
            $sql .= " AND (LOWER(u.firstname) LIKE LOWER(?) OR LOWER(u.lastname) LIKE LOWER(?) OR LOWER(u.email) LIKE LOWER(?))";
            $searchparam = '%' . $search . '%';
            $params[] = $searchparam;
            $params[] = $searchparam;
            $params[] = $searchparam;
        }

        $sql .= " ORDER BY u.lastname, u.firstname";

        $students = $this->db->get_records_sql($sql, $params);

        // Enrich with additional data
        foreach ($students as &$student) {
            $this->enrich_student_data($student);
        }

        // Apply filters that need enriched data
        if (!empty($colorfilter)) {
            $students = array_filter($students, function($s) use ($colorfilter) {
                return ($s->group_color ?? '') === $colorfilter;
            });
        }

        if ($weekfilter > 0) {
            $students = array_filter($students, function($s) use ($weekfilter) {
                return ($s->current_week ?? 0) == $weekfilter;
            });
        }

        if (!empty($statusfilter)) {
            $students = $this->filter_by_status($students, $statusfilter);
        }

        return $students;
    }

    /**
     * Enrich student data with competencies, autovaluation, lab data
     * @param object $student Student object to enrich
     */
    private function enrich_student_data(&$student) {
        // Get competency average from competencymanager
        $student->competency_avg = $this->get_student_competency_avg($student->id);

        // Get autovaluation data from selfassessment
        $autoval = $this->get_student_autoval($student->id);
        $student->autoval_avg = $autoval['avg'];
        $student->autoval_done = $autoval['done'];

        // Get lab data from labeval
        $lab = $this->get_student_lab($student->id);
        $student->lab_avg = $lab['avg'];
        $student->lab_done = $lab['done'];
        $student->lab_pending = $lab['pending'];

        // Get group color and week
        $group = $this->get_student_group($student->id);
        $student->group_color = $group['color'];
        $student->current_week = $group['week'];

        // Get sector
        $student->sector = $this->get_student_sector($student->id);

        // Get course info
        $course = $this->get_student_main_course($student->id);
        $student->course_id = $course['id'];
        $student->course_shortname = $course['shortname'];

        // Quiz status
        $student->quiz_done = $this->has_completed_quiz($student->id);

        // Needs choices for next week?
        $student->needs_choices = $this->needs_week_choices($student->id, $student->current_week);
    }

    /**
     * Get student's competency average
     * @param int $userid
     * @return float
     */
    private function get_student_competency_avg($userid) {
        // Try to get from competencymanager cached data
        $sql = "SELECT AVG(fraction) * 100 as avg_score
                FROM (
                    SELECT qa.questionid, MAX(qas.fraction) as fraction
                    FROM {quiz_attempts} quiza
                    JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                    JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                    WHERE quiza.userid = ?
                    AND quiza.state = 'finished'
                    AND qas.sequencenumber = (
                        SELECT MAX(qas2.sequencenumber)
                        FROM {question_attempt_steps} qas2
                        WHERE qas2.questionattemptid = qa.id
                    )
                    GROUP BY qa.questionid
                ) subq";

        $result = $this->db->get_record_sql($sql, [$userid]);
        return $result ? round($result->avg_score, 1) : 0;
    }

    /**
     * Get student's autovaluation data
     * @param int $userid
     * @return array
     */
    private function get_student_autoval($userid) {
        $result = ['avg' => null, 'done' => false];

        // Check local_selfassessment table
        if ($this->db->get_manager()->table_exists('local_selfassessment')) {
            $sql = "SELECT AVG(level) as avg_level, COUNT(*) as count
                    FROM {local_selfassessment}
                    WHERE userid = ?";
            $data = $this->db->get_record_sql($sql, [$userid]);

            if ($data && $data->count > 0) {
                $result['avg'] = round($data->avg_level, 1);
                $result['done'] = true;
            }
        }

        // Also check coachmanager_assessment
        if (!$result['done'] && $this->db->get_manager()->table_exists('local_coachmanager_assessment')) {
            $assessment = $this->db->get_record('local_coachmanager_assessment', ['userid' => $userid]);
            if ($assessment) {
                $details = json_decode($assessment->details, true);
                if (!empty($details['competencies'])) {
                    $sum = 0;
                    $count = 0;
                    foreach ($details['competencies'] as $comp) {
                        if (isset($comp['bloom_level'])) {
                            $sum += $comp['bloom_level'];
                            $count++;
                        }
                    }
                    if ($count > 0) {
                        $result['avg'] = round($sum / $count, 1);
                        $result['done'] = true;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get student's lab evaluation data
     * @param int $userid
     * @return array
     */
    private function get_student_lab($userid) {
        $result = ['avg' => null, 'done' => false, 'pending' => false];

        // Check local_labeval table
        if ($this->db->get_manager()->table_exists('local_labeval_evaluations')) {
            $sql = "SELECT AVG(score) as avg_score, COUNT(*) as count,
                           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                    FROM {local_labeval_evaluations}
                    WHERE studentid = ?";
            $data = $this->db->get_record_sql($sql, [$userid]);

            if ($data && $data->count > 0) {
                $result['avg'] = round($data->avg_score, 1);
                $result['done'] = $data->pending_count == 0;
                $result['pending'] = $data->pending_count > 0;
            }
        }

        return $result;
    }

    /**
     * Get student's group color and week
     * @param int $userid
     * @return array
     */
    private function get_student_group($userid) {
        // Try to get from scheduler data
        if ($this->db->get_manager()->table_exists('local_ftm_scheduler_assignments')) {
            $sql = "SELECT g.color, a.current_week
                    FROM {local_ftm_scheduler_assignments} a
                    JOIN {local_ftm_scheduler_groups} g ON a.groupid = g.id
                    WHERE a.userid = ?
                    ORDER BY a.timecreated DESC
                    LIMIT 1";
            $data = $this->db->get_record_sql($sql, [$userid]);
            if ($data) {
                return ['color' => $data->color, 'week' => $data->current_week];
            }
        }

        // Default: assign based on enrollment date (demo)
        $enrollments = $this->db->get_records_sql(
            "SELECT ue.timecreated FROM {user_enrolments} ue
             JOIN {enrol} e ON ue.enrolid = e.id
             WHERE ue.userid = ?
             ORDER BY ue.timecreated ASC LIMIT 1",
            [$userid]
        );

        if (!empty($enrollments)) {
            $first = reset($enrollments);
            $weeks_enrolled = floor((time() - $first->timecreated) / (7 * 24 * 60 * 60));
            $week = min(6, max(1, $weeks_enrolled + 1));
        } else {
            $week = 1;
        }

        // Assign color based on user ID (demo)
        $colors = ['giallo', 'blu', 'verde', 'arancione', 'rosso', 'viola', 'grigio'];
        $color = $colors[$userid % count($colors)];

        return ['color' => $color, 'week' => $week];
    }

    /**
     * Get student's sector
     * @param int $userid
     * @return string
     */
    private function get_student_sector($userid) {
        // Get from course name
        $sql = "SELECT c.fullname, c.shortname
                FROM {course} c
                JOIN {enrol} e ON e.courseid = c.id
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE ue.userid = ?
                AND c.id > 1
                ORDER BY ue.timecreated DESC
                LIMIT 1";
        $course = $this->db->get_record_sql($sql, [$userid]);

        if ($course) {
            $name = strtoupper($course->fullname . ' ' . $course->shortname);
            if (strpos($name, 'MECCANICA') !== false) return 'MECCANICA';
            if (strpos($name, 'ELETTRIC') !== false) return 'ELETTRICITA';
            if (strpos($name, 'AUTOMAZIONE') !== false) return 'AUTOMAZIONE';
            if (strpos($name, 'AUTOMOBILE') !== false) return 'AUTOMOBILE';
            if (strpos($name, 'LOGISTICA') !== false) return 'LOGISTICA';
            if (strpos($name, 'CHIMICA') !== false || strpos($name, 'FARMAC') !== false) return 'CHIMFARM';
            if (strpos($name, 'INFORMATICA') !== false) return 'INFORMATICA';
        }

        return 'ALTRO';
    }

    /**
     * Get student's main course
     * @param int $userid
     * @return array
     */
    private function get_student_main_course($userid) {
        $sql = "SELECT c.id, c.shortname, c.fullname
                FROM {course} c
                JOIN {enrol} e ON e.courseid = c.id
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE ue.userid = ?
                AND c.id > 1
                ORDER BY ue.timecreated DESC
                LIMIT 1";
        $course = $this->db->get_record_sql($sql, [$userid]);

        if ($course) {
            return ['id' => $course->id, 'shortname' => $course->shortname];
        }
        return ['id' => 0, 'shortname' => ''];
    }

    /**
     * Check if student has completed any quiz
     * @param int $userid
     * @return bool
     */
    private function has_completed_quiz($userid) {
        return $this->db->record_exists_sql(
            "SELECT 1 FROM {quiz_attempts} WHERE userid = ? AND state = 'finished'",
            [$userid]
        );
    }

    /**
     * Check if student needs week choices
     * @param int $userid
     * @param int $current_week
     * @return bool
     */
    private function needs_week_choices($userid, $current_week) {
        // Check if choices exist for next week
        if ($this->db->get_manager()->table_exists('local_ftm_scheduler_choices')) {
            $next_week = $current_week + 1;
            return !$this->db->record_exists('local_ftm_scheduler_choices', [
                'userid' => $userid,
                'week' => $next_week
            ]);
        }
        // Default: needs choices if not at week 6
        return $current_week < 6;
    }

    /**
     * Filter students by status
     * @param array $students
     * @param string $status
     * @return array
     */
    private function filter_by_status($students, $status) {
        switch ($status) {
            case 'end6':
                return array_filter($students, fn($s) => ($s->current_week ?? 0) >= 6);
            case 'below50':
                return array_filter($students, fn($s) => ($s->competency_avg ?? 0) < 50);
            case 'no_autoval':
                return array_filter($students, fn($s) => !($s->autoval_done ?? false));
            case 'no_lab':
                return array_filter($students, fn($s) => !($s->lab_done ?? false));
            case 'no_choices':
                return array_filter($students, fn($s) => ($s->needs_choices ?? false));
            default:
                return $students;
        }
    }

    /**
     * Get courses where coach has role
     * @return array
     */
    public function get_coach_courses() {
        global $USER;

        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname
                FROM {course} c
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = ?
                JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ?
                WHERE c.id > 1
                ORDER BY c.fullname";

        return $this->db->get_records_sql($sql, [CONTEXT_COURSE, $USER->id]);
    }

    /**
     * Get color groups for this coach
     * @return array
     */
    public function get_color_groups() {
        // Get unique groups from students
        $students = $this->get_my_students();
        $groups = [];

        foreach ($students as $student) {
            $color = $student->group_color ?? 'giallo';
            if (!isset($groups[$color])) {
                $groups[$color] = (object)[
                    'color' => $color,
                    'name' => 'Gruppo ' . ucfirst($color),
                    'current_week' => $student->current_week ?? 1,
                    'count' => 0
                ];
            }
            $groups[$color]->count++;
        }

        return array_values($groups);
    }

    /**
     * Get dashboard statistics
     * @param array $students
     * @return array
     */
    public function get_dashboard_stats($students) {
        $stats = [
            'total_students' => count($students),
            'avg_competency' => 0,
            'autoval_complete' => 0,
            'lab_evaluated' => 0,
            'end_6_weeks' => 0,
            'below_threshold' => 0,
            'missing_autoval' => 0,
            'missing_lab' => 0,
            'missing_choices' => 0
        ];

        if (empty($students)) {
            return $stats;
        }

        $competency_sum = 0;
        foreach ($students as $student) {
            $competency_sum += $student->competency_avg ?? 0;

            if ($student->autoval_done ?? false) $stats['autoval_complete']++;
            else $stats['missing_autoval']++;

            if ($student->lab_done ?? false) $stats['lab_evaluated']++;
            else $stats['missing_lab']++;

            if (($student->current_week ?? 0) >= 6) $stats['end_6_weeks']++;
            if (($student->competency_avg ?? 0) < 50) $stats['below_threshold']++;
            if ($student->needs_choices ?? false) $stats['missing_choices']++;
        }

        $stats['avg_competency'] = round($competency_sum / count($students), 1);

        return $stats;
    }

    /**
     * Get students at end of 6 weeks
     * @param array $students
     * @return array
     */
    public function get_students_end_6_weeks($students) {
        return array_filter($students, fn($s) => ($s->current_week ?? 0) >= 6);
    }

    /**
     * Get next deadline
     * @return object|null
     */
    public function get_next_deadline() {
        // Check scheduler deadlines
        if ($this->db->get_manager()->table_exists('local_ftm_scheduler_deadlines')) {
            $sql = "SELECT * FROM {local_ftm_scheduler_deadlines}
                    WHERE deadline > ?
                    ORDER BY deadline ASC
                    LIMIT 1";
            $deadline = $this->db->get_record_sql($sql, [time()]);
            if ($deadline) {
                $deadline->days_remaining = ceil(($deadline->deadline - time()) / (24 * 60 * 60));
                return $deadline;
            }
        }

        // Default demo deadline
        $friday = strtotime('next friday');
        return (object)[
            'title' => get_string('week_choices', 'local_coachmanager') . ' 3',
            'description' => get_string('choose_tests_description', 'local_coachmanager'),
            'deadline' => $friday,
            'days_remaining' => ceil(($friday - time()) / (24 * 60 * 60))
        ];
    }

    /**
     * Get calendar data for a month
     * @param int $year
     * @param int $month
     * @return array
     */
    public function get_calendar_data($year, $month) {
        $data = [
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
            'current_week' => date('W'),
            'days' => [],
            'events' => []
        ];

        // Generate days of the month
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day);
        $first_weekday = date('N', $first_day); // 1=Mon, 7=Sun

        // Add days from previous month
        for ($i = 1; $i < $first_weekday; $i++) {
            $data['days'][] = ['day' => '', 'class' => 'empty', 'events' => []];
        }

        // Add days of this month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = mktime(0, 0, 0, $month, $day, $year);
            $weekday = date('N', $date);
            $is_weekend = $weekday >= 6;
            $is_today = date('Y-m-d', $date) === date('Y-m-d');

            $day_data = [
                'day' => $day,
                'date' => date('Y-m-d', $date),
                'class' => $is_weekend ? 'weekend' : '',
                'is_today' => $is_today,
                'events' => []
            ];

            // Add demo events (in real implementation, load from scheduler)
            if (!$is_weekend) {
                $week_of_month = ceil($day / 7);
                if ($week_of_month == 1 || $week_of_month == 3) {
                    $day_data['class'] = 'giallo';
                    $day_data['events'][] = ['title' => 'Giallo', 'type' => $weekday % 2 == 0 ? 'Lab' : 'Test'];
                } elseif ($week_of_month == 2 || $week_of_month == 4) {
                    $day_data['class'] = 'blu';
                    $day_data['events'][] = ['title' => 'Blu', 'type' => $weekday % 2 == 0 ? 'Lab' : 'Test'];
                }
            }

            $data['days'][] = $day_data;
        }

        return $data;
    }

    /**
     * Render week view HTML
     * @param array $calendar_data
     * @return string
     */
    public function render_week_view($calendar_data) {
        $html = '';
        $today = date('Y-m-d');
        $current_week_days = [];

        // Find current week days
        $start_of_week = strtotime('monday this week');
        for ($i = 0; $i < 5; $i++) { // Mon-Fri
            $date = date('Y-m-d', strtotime("+$i days", $start_of_week));
            $current_week_days[] = $date;
        }

        // Header row
        $days_names = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven'];
        foreach ($days_names as $i => $name) {
            $date = date('d', strtotime($current_week_days[$i]));
            $html .= '<div class="calendar-day header">' . $name . ' ' . $date . '</div>';
        }

        // Events row (demo)
        $colors = ['giallo', 'giallo', 'empty', 'giallo', 'giallo'];
        $events = ['Test Appr.', 'Lab', 'REMOTO', 'Test Appr.', 'Lab'];
        for ($i = 0; $i < 5; $i++) {
            $html .= '<div class="calendar-day ' . $colors[$i] . '">' . $events[$i] . '</div>';
        }

        return $html;
    }

    /**
     * Render month view HTML
     * @param array $calendar_data
     * @return string
     */
    public function render_month_view($calendar_data) {
        $html = '<div class="month-grid">';

        // Header
        $days = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
        foreach ($days as $day) {
            $html .= '<div class="month-day header">' . $day . '</div>';
        }

        // Days
        foreach ($calendar_data['days'] as $day) {
            $classes = ['month-day'];
            if (!empty($day['class'])) $classes[] = $day['class'];
            if (!empty($day['is_today'])) $classes[] = 'today';

            $html .= '<div class="' . implode(' ', $classes) . '">';
            if (!empty($day['day'])) {
                $html .= '<div class="day-number">' . $day['day'] . '</div>';
                foreach ($day['events'] as $event) {
                    $html .= '<div class="day-event">' . $event['type'] . '</div>';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        // Legend
        $html .= '<div style="margin-top: 15px; display: flex; gap: 15px; font-size: 12px;">';
        $html .= '<span><span style="display: inline-block; width: 12px; height: 12px; background: #FEF9C3; border: 1px solid #EAB308; border-radius: 3px;"></span> Gruppo Giallo</span>';
        $html .= '<span><span style="display: inline-block; width: 12px; height: 12px; background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 3px;"></span> Gruppo Blu</span>';
        $html .= '<span><span style="display: inline-block; width: 12px; height: 12px; background: #D1FAE5; border: 1px solid #10B981; border-radius: 3px;"></span> Gruppo Verde</span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get available tests for a sector
     * @param string $sector
     * @return array
     */
    public function get_available_tests($sector) {
        $sql = "SELECT q.id, q.name
                FROM {quiz} q
                JOIN {course} c ON q.course = c.id
                WHERE UPPER(c.fullname) LIKE ?
                OR UPPER(c.shortname) LIKE ?
                ORDER BY q.name";

        $sector_pattern = '%' . strtoupper($sector) . '%';
        $tests = $this->db->get_records_sql($sql, [$sector_pattern, $sector_pattern]);

        if (empty($tests)) {
            // Return demo tests
            return [
                (object)['id' => 1, 'name' => 'Quiz ' . $sector . ' Base'],
                (object)['id' => 2, 'name' => 'Quiz ' . $sector . ' Avanzato'],
                (object)['id' => 3, 'name' => 'Quiz ' . $sector . ' Pratico'],
            ];
        }

        return $tests;
    }

    /**
     * Get available labs for a sector
     * @param string $sector
     * @return array
     */
    public function get_available_labs($sector) {
        // Check labeval for available labs
        if ($this->db->get_manager()->table_exists('local_labeval_labs')) {
            $sql = "SELECT id, name FROM {local_labeval_labs}
                    WHERE sector = ? OR sector = 'all'
                    ORDER BY name";
            $labs = $this->db->get_records_sql($sql, [$sector]);
            if (!empty($labs)) {
                return $labs;
            }
        }

        // Return demo labs
        return [
            (object)['id' => 1, 'name' => 'Lab ' . $sector . ' Base'],
            (object)['id' => 2, 'name' => 'Lab ' . $sector . ' Intermedio'],
            (object)['id' => 3, 'name' => 'Lab ' . $sector . ' Avanzato'],
        ];
    }
}
