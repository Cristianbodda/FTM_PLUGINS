<?php
// ============================================
// FTM Scheduler - Room Occupancy & Secretary Dashboard
// Dashboard segreteria con occupazione aule e statistiche
// ============================================

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

// Page parameters
$week = optional_param('week', date('W'), PARAM_INT);
$year = optional_param('year', date('Y'), PARAM_INT);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/room_occupancy.php', ['week' => $week, 'year' => $year]));
$PAGE->set_title(get_string('dashboard_segreteria', 'local_ftm_scheduler'));
$PAGE->set_heading(get_string('dashboard_segreteria', 'local_ftm_scheduler'));
$PAGE->set_pagelayout('standard');

// Get data
$manager = new \local_ftm_scheduler\manager();
$rooms = $manager::get_rooms();
$groups = $manager::get_groups('active');
$week_dates = $manager::get_week_dates($year, $week);

// Calculate week boundaries
$monday_ts = $week_dates[0]['timestamp'];
$friday_ts = $week_dates[4]['timestamp'] + 86400;

// Get all activities for the week
$activities = $manager::get_activities([
    'date_start' => $monday_ts,
    'date_end' => $friday_ts,
]);

// Build room occupancy matrix
$room_occupancy = [];
$time_slots = ['08:30-12:00', '13:30-17:00'];
$day_names = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven'];

foreach ($rooms as $room) {
    $room_occupancy[$room->id] = [
        'room' => $room,
        'slots' => []
    ];
    foreach ($week_dates as $idx => $day) {
        $room_occupancy[$room->id]['slots'][$idx] = [
            'matt' => null,
            'pom' => null
        ];
    }
}

// Fill room occupancy from activities
foreach ($activities as $activity) {
    if (empty($activity->room_id)) continue;

    $day_idx = date('N', $activity->date_start) - 1; // 0-4 for Mon-Fri
    if ($day_idx > 4) continue;

    $slot = (strpos($activity->start_time, '08') !== false || strpos($activity->start_time, '09') !== false) ? 'matt' : 'pom';

    if (isset($room_occupancy[$activity->room_id])) {
        $room_occupancy[$activity->room_id]['slots'][$day_idx][$slot] = $activity;
    }
}

// Get absence statistics
$absence_stats = get_absence_statistics($monday_ts, $friday_ts);

// Get enrollment statistics by group
$enrollment_stats = get_enrollment_statistics($groups);

/**
 * Get absence statistics for a date range
 */
function get_absence_statistics($start, $end) {
    global $DB;

    $stats = new stdClass();

    // Total enrollments in period
    $sql = "SELECT COUNT(e.id) as total,
                   SUM(CASE WHEN e.status = 'attended' THEN 1 ELSE 0 END) as attended,
                   SUM(CASE WHEN e.status = 'absent' THEN 1 ELSE 0 END) as absent
            FROM {local_ftm_enrollments} e
            JOIN {local_ftm_activities} a ON e.activityid = a.id
            WHERE a.date_start >= :start AND a.date_start <= :end";

    $result = $DB->get_record_sql($sql, ['start' => $start, 'end' => $end]);

    $stats->total = (int)($result->total ?? 0);
    $stats->attended = (int)($result->attended ?? 0);
    $stats->absent = (int)($result->absent ?? 0);
    $stats->pending = $stats->total - $stats->attended - $stats->absent;
    $stats->absence_rate = $stats->attended + $stats->absent > 0
        ? round(($stats->absent / ($stats->attended + $stats->absent)) * 100, 1)
        : 0;

    // Students with high absence rate (>20%)
    $sql = "SELECT u.id, u.firstname, u.lastname,
                   COUNT(e.id) as total,
                   SUM(CASE WHEN e.status = 'absent' THEN 1 ELSE 0 END) as absences
            FROM {user} u
            JOIN {local_ftm_enrollments} e ON e.userid = u.id
            JOIN {local_ftm_activities} a ON e.activityid = a.id
            WHERE a.date_start >= :start AND a.date_start <= :end
            AND e.status IN ('attended', 'absent')
            GROUP BY u.id, u.firstname, u.lastname
            HAVING SUM(CASE WHEN e.status = 'absent' THEN 1 ELSE 0 END) > 0
            ORDER BY absences DESC
            LIMIT 10";

    $stats->high_absence_students = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

    return $stats;
}

/**
 * Get enrollment statistics by group
 */
function get_enrollment_statistics($groups) {
    global $DB;

    $stats = [];

    foreach ($groups as $group) {
        $group_stats = new stdClass();
        $group_stats->group = $group;

        // Get students in group
        $students = $DB->get_records('local_ftm_group_members', ['groupid' => $group->id]);
        $group_stats->student_count = count($students);

        // Get activities count for this group
        $activities_count = $DB->count_records('local_ftm_activities', ['groupid' => $group->id]);
        $group_stats->activities_count = $activities_count;

        // Calculate attendance for group
        $sql = "SELECT COUNT(e.id) as total,
                       SUM(CASE WHEN e.status = 'attended' THEN 1 ELSE 0 END) as attended
                FROM {local_ftm_enrollments} e
                JOIN {local_ftm_activities} a ON e.activityid = a.id
                WHERE a.groupid = :groupid";

        $result = $DB->get_record_sql($sql, ['groupid' => $group->id]);
        $group_stats->attendance_rate = $result->total > 0
            ? round(($result->attended / $result->total) * 100, 1)
            : 0;

        $stats[$group->id] = $group_stats;
    }

    return $stats;
}

echo $OUTPUT->header();
?>

<style>
.secretary-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #dee2e6;
}

.dashboard-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
}

.week-nav {
    display: flex;
    align-items: center;
    gap: 15px;
}

.week-nav a {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
}

.week-nav a:hover {
    background: #e9ecef;
}

.week-nav .current-week {
    font-weight: 600;
    font-size: 16px;
    padding: 8px 20px;
    background: #0066cc;
    color: white;
    border-radius: 6px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}

.stat-card.success { border-left: 4px solid #28a745; }
.stat-card.danger { border-left: 4px solid #dc3545; }
.stat-card.warning { border-left: 4px solid #ffc107; }
.stat-card.info { border-left: 4px solid #17a2b8; }

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #333;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

/* Room Occupancy Table */
.occupancy-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
}

.occupancy-table {
    width: 100%;
    border-collapse: collapse;
}

.occupancy-table th,
.occupancy-table td {
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: center;
    vertical-align: middle;
}

.occupancy-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.occupancy-table th.room-header {
    text-align: left;
    width: 150px;
}

.occupancy-table .day-header {
    width: 180px;
}

.occupancy-table .slot-header {
    font-size: 11px;
    color: #6c757d;
}

.slot-cell {
    height: 60px;
    position: relative;
}

.slot-cell.occupied {
    background: #e3f2fd;
}

.slot-cell.free {
    background: #f8f9fa;
}

.slot-activity {
    font-size: 11px;
    padding: 5px;
    border-radius: 4px;
    background: #0066cc;
    color: white;
}

.slot-activity .activity-title {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.slot-activity .activity-group {
    font-size: 10px;
    opacity: 0.9;
}

/* Group colors */
.slot-activity.giallo { background: #FFD700; color: #333; }
.slot-activity.grigio { background: #808080; color: white; }
.slot-activity.rosso { background: #FF4444; color: white; }
.slot-activity.marrone { background: #996633; color: white; }
.slot-activity.viola { background: #7030A0; color: white; }

/* Absence Section */
.absence-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.absence-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.student-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.student-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.student-list li:last-child {
    border-bottom: none;
}

.student-name {
    font-weight: 500;
}

.absence-count {
    background: #dc3545;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Groups Section */
.groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.group-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #ccc;
}

.group-card.giallo { border-left-color: #FFD700; }
.group-card.grigio { border-left-color: #808080; }
.group-card.rosso { border-left-color: #FF4444; }
.group-card.marrone { border-left-color: #996633; }
.group-card.viola { border-left-color: #7030A0; }

.group-name {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
}

.group-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    text-align: center;
}

.group-stat-value {
    font-size: 24px;
    font-weight: 700;
}

.group-stat-label {
    font-size: 11px;
    color: #6c757d;
}

/* Back link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0066cc;
    text-decoration: none;
    margin-bottom: 20px;
}

.back-link:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .absence-section {
        grid-template-columns: 1fr;
    }

    .occupancy-table {
        font-size: 12px;
    }
}
</style>

<div class="secretary-dashboard">

    <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="back-link">
        ← Torna allo Scheduler
    </a>

    <div class="dashboard-header">
        <div class="dashboard-title">Dashboard Segreteria</div>
        <div class="week-nav">
            <?php
            $prev_week = $week - 1;
            $prev_year = $year;
            if ($prev_week < 1) {
                $prev_week = 52;
                $prev_year--;
            }
            $next_week = $week + 1;
            $next_year = $year;
            if ($next_week > 52) {
                $next_week = 1;
                $next_year++;
            }
            ?>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/room_occupancy.php', ['week' => $prev_week, 'year' => $prev_year]); ?>">
                ← Settimana precedente
            </a>
            <span class="current-week">
                Settimana <?php echo $week; ?> / <?php echo $year; ?>
                <br><small><?php echo date('d/m', $monday_ts); ?> - <?php echo date('d/m', $friday_ts - 86400); ?></small>
            </span>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/room_occupancy.php', ['week' => $next_week, 'year' => $next_year]); ?>">
                Settimana successiva →
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-value"><?php echo $absence_stats->attended; ?></div>
            <div class="stat-label">Presenze questa settimana</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-value"><?php echo $absence_stats->absent; ?></div>
            <div class="stat-label">Assenze questa settimana</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?php echo $absence_stats->pending; ?></div>
            <div class="stat-label">Da registrare</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $absence_stats->absence_rate; ?>%</div>
            <div class="stat-label">Tasso assenza</div>
        </div>
    </div>

    <!-- Room Occupancy Matrix -->
    <div class="occupancy-section">
        <h2 class="section-title">Occupazione Aule - Settimana <?php echo $week; ?></h2>

        <table class="occupancy-table">
            <thead>
                <tr>
                    <th class="room-header">Aula</th>
                    <?php foreach ($day_names as $idx => $day): ?>
                    <th class="day-header" colspan="2">
                        <?php echo $day; ?> <?php echo date('d/m', $week_dates[$idx]['timestamp']); ?>
                        <div class="slot-header">Matt | Pom</div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($room_occupancy as $room_id => $room_data): ?>
                <tr>
                    <td class="room-header">
                        <strong><?php echo s($room_data['room']->name); ?></strong>
                        <br><small><?php echo $room_data['room']->capacity; ?> posti</small>
                    </td>
                    <?php foreach ($room_data['slots'] as $day_idx => $slots): ?>
                        <?php foreach (['matt', 'pom'] as $slot): ?>
                        <td class="slot-cell <?php echo $slots[$slot] ? 'occupied' : 'free'; ?>">
                            <?php if ($slots[$slot]):
                                $activity = $slots[$slot];
                                $group = isset($groups[$activity->groupid]) ? $groups[$activity->groupid] : null;
                                $color_class = $group ? strtolower($group->color) : '';
                            ?>
                            <div class="slot-activity <?php echo $color_class; ?>">
                                <div class="activity-title"><?php echo s($activity->title); ?></div>
                                <?php if ($group): ?>
                                <div class="activity-group"><?php echo s($group->name); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Absence Section -->
    <div class="absence-section">
        <div class="absence-card">
            <h2 class="section-title">Studenti con Assenze (ultimi 7 giorni)</h2>
            <?php if (!empty($absence_stats->high_absence_students)): ?>
            <ul class="student-list">
                <?php foreach ($absence_stats->high_absence_students as $student): ?>
                <li>
                    <span class="student-name"><?php echo s($student->firstname . ' ' . $student->lastname); ?></span>
                    <span class="absence-count"><?php echo $student->absences; ?> assenze</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p style="color: #6c757d; text-align: center; padding: 20px;">
                Nessuna assenza registrata in questo periodo
            </p>
            <?php endif; ?>
        </div>

        <div class="absence-card">
            <h2 class="section-title">Azioni Rapide</h2>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/attendance.php'); ?>"
                   class="btn btn-primary" style="text-align: center; padding: 15px; border-radius: 8px; text-decoration: none;">
                    📋 Registro Presenze
                </a>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>"
                   class="btn btn-secondary" style="text-align: center; padding: 15px; border-radius: 8px; text-decoration: none; background: #6c757d; color: white;">
                    📅 Calendario Settimanale
                </a>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>"
                   class="btn btn-secondary" style="text-align: center; padding: 15px; border-radius: 8px; text-decoration: none; background: #6c757d; color: white;">
                    🎨 Gestione Gruppi
                </a>
            </div>
        </div>
    </div>

    <!-- Groups Overview -->
    <div class="occupancy-section">
        <h2 class="section-title">Panoramica Gruppi Attivi</h2>

        <div class="groups-grid">
            <?php foreach ($enrollment_stats as $group_id => $gstats): ?>
            <div class="group-card <?php echo strtolower($gstats->group->color); ?>">
                <div class="group-name"><?php echo s($gstats->group->name); ?></div>
                <div class="group-stats">
                    <div>
                        <div class="group-stat-value"><?php echo $gstats->student_count; ?></div>
                        <div class="group-stat-label">Studenti</div>
                    </div>
                    <div>
                        <div class="group-stat-value"><?php echo $gstats->activities_count; ?></div>
                        <div class="group-stat-label">Attività</div>
                    </div>
                    <div>
                        <div class="group-stat-value"><?php echo $gstats->attendance_rate; ?>%</div>
                        <div class="group-stat-label">Frequenza</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($enrollment_stats)): ?>
            <p style="color: #6c757d; text-align: center; padding: 40px; grid-column: 1/-1;">
                Nessun gruppo attivo
            </p>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
echo $OUTPUT->footer();
