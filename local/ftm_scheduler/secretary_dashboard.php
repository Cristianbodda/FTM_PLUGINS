<?php
/**
 * FTM Scheduler - Dashboard Segreteria Completa
 *
 * Funzionalità:
 * - Occupazione Aule (matrice settimanale)
 * - Carico Docenti (ore per coach)
 * - Rilevamento Conflitti
 * - Pianificazione Rapida
 * - Statistiche
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

// Page parameters
$week = optional_param('week', (int)date('W'), PARAM_INT);
$year = optional_param('year', (int)date('Y'), PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/secretary_dashboard.php', ['week' => $week, 'year' => $year]));
$PAGE->set_title(get_string('dashboard_segreteria', 'local_ftm_scheduler'));
$PAGE->set_heading(get_string('dashboard_segreteria', 'local_ftm_scheduler'));
$PAGE->set_pagelayout('standard');

// Get manager instance
$manager = new \local_ftm_scheduler\manager();

// Get basic data
$rooms = $manager::get_rooms();
$groups = $manager::get_groups('active');
$coaches = $manager::get_coaches();
$colors = local_ftm_scheduler_get_colors();

// Get week dates
$week_dates = $manager::get_week_dates($year, $week);
$monday_ts = $week_dates[0]['timestamp'];
$friday_ts = $week_dates[4]['timestamp'] + 86400;

// Get activities for the week
$activities = $manager::get_activities([
    'date_start' => $monday_ts,
    'date_end' => $friday_ts,
]);

// Get external bookings
$external_bookings = $manager::get_external_bookings($monday_ts, $friday_ts);

// ============================================
// CALCOLA OCCUPAZIONE AULE
// ============================================
$room_occupancy = [];
$day_names = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven'];

foreach ($rooms as $room) {
    $room_occupancy[$room->id] = [
        'room' => $room,
        'slots' => [],
        'total_hours' => 0,
        'free_slots' => 0,
        'occupied_slots' => 0,
    ];
    foreach ($week_dates as $idx => $day) {
        $room_occupancy[$room->id]['slots'][$idx] = [
            'matt' => null,
            'pom' => null
        ];
    }
}

// Fill from activities
foreach ($activities as $activity) {
    if (empty($activity->roomid)) continue;

    $day_idx = date('N', $activity->date_start) - 1;
    if ($day_idx > 4) continue;

    $hour = (int)date('H', $activity->date_start);
    $slot = ($hour < 12) ? 'matt' : 'pom';

    if (isset($room_occupancy[$activity->roomid])) {
        $room_occupancy[$activity->roomid]['slots'][$day_idx][$slot] = $activity;
        $room_occupancy[$activity->roomid]['occupied_slots']++;
        $room_occupancy[$activity->roomid]['total_hours'] += 3.5; // Approx hours per slot
    }
}

// Fill from external bookings
foreach ($external_bookings as $booking) {
    if (empty($booking->roomid)) continue;

    $day_idx = date('N', $booking->date_start) - 1;
    if ($day_idx > 4) continue;

    $hour = (int)date('H', $booking->date_start);
    $slot = ($hour < 12) ? 'matt' : 'pom';

    if (isset($room_occupancy[$booking->roomid])) {
        $booking->is_external = true;
        $room_occupancy[$booking->roomid]['slots'][$day_idx][$slot] = $booking;
        $room_occupancy[$booking->roomid]['occupied_slots']++;
        $room_occupancy[$booking->roomid]['total_hours'] += 3.5;
    }
}

// Calculate free slots
foreach ($room_occupancy as $room_id => &$ro) {
    $total_slots = 10; // 5 days * 2 slots
    $ro['free_slots'] = $total_slots - $ro['occupied_slots'];
    $ro['occupancy_percent'] = round(($ro['occupied_slots'] / $total_slots) * 100);
}
unset($ro);

// ============================================
// CALCOLA CARICO DOCENTI
// ============================================
$coach_workload = [];
$max_hours_week = 35; // Soglia sovraccarico

foreach ($coaches as $coach) {
    $coach_workload[$coach->userid] = [
        'coach' => $coach,
        'hours' => 0,
        'activities_count' => 0,
        'days' => [0, 0, 0, 0, 0], // Ore per giorno Lun-Ven
        'activities' => [],
    ];
}

foreach ($activities as $activity) {
    if (empty($activity->teacherid)) continue;

    if (!isset($coach_workload[$activity->teacherid])) {
        // Coach non nella tabella local_ftm_coaches, aggiungi comunque
        $coach_workload[$activity->teacherid] = [
            'coach' => (object)[
                'userid' => $activity->teacherid,
                'initials' => substr($activity->teacher_firstname ?? '', 0, 1) . substr($activity->teacher_lastname ?? '', 0, 1),
                'firstname' => $activity->teacher_firstname ?? '',
                'lastname' => $activity->teacher_lastname ?? '',
            ],
            'hours' => 0,
            'activities_count' => 0,
            'days' => [0, 0, 0, 0, 0],
            'activities' => [],
        ];
    }

    $day_idx = date('N', $activity->date_start) - 1;
    if ($day_idx > 4) continue;

    $duration_hours = ($activity->date_end - $activity->date_start) / 3600;

    $coach_workload[$activity->teacherid]['hours'] += $duration_hours;
    $coach_workload[$activity->teacherid]['activities_count']++;
    $coach_workload[$activity->teacherid]['days'][$day_idx] += $duration_hours;
    $coach_workload[$activity->teacherid]['activities'][] = $activity;
}

// Sort by hours descending
uasort($coach_workload, function($a, $b) {
    return $b['hours'] <=> $a['hours'];
});

// ============================================
// RILEVA CONFLITTI
// ============================================
$conflicts = [];

// Check room conflicts (same room, same time)
$room_time_map = [];
foreach ($activities as $activity) {
    if (empty($activity->roomid)) continue;

    $key = $activity->roomid . '_' . date('Y-m-d', $activity->date_start) . '_' . date('H', $activity->date_start);

    if (isset($room_time_map[$key])) {
        $conflicts[] = [
            'type' => 'room',
            'message' => 'Conflitto aula: ' . ($activity->room_name ?? 'Aula') . ' il ' . date('d/m H:i', $activity->date_start),
            'activity1' => $room_time_map[$key],
            'activity2' => $activity,
        ];
    } else {
        $room_time_map[$key] = $activity;
    }
}

// Check also external bookings
foreach ($external_bookings as $booking) {
    if (empty($booking->roomid)) continue;

    $key = $booking->roomid . '_' . date('Y-m-d', $booking->date_start) . '_' . date('H', $booking->date_start);

    if (isset($room_time_map[$key])) {
        $conflicts[] = [
            'type' => 'room',
            'message' => 'Conflitto aula con progetto esterno: ' . ($booking->room_name ?? 'Aula') . ' il ' . date('d/m H:i', $booking->date_start),
            'activity1' => $room_time_map[$key],
            'activity2' => $booking,
        ];
    }
}

// Check teacher conflicts (same teacher, same time)
$teacher_time_map = [];
foreach ($activities as $activity) {
    if (empty($activity->teacherid)) continue;

    $key = $activity->teacherid . '_' . date('Y-m-d', $activity->date_start) . '_' . date('H', $activity->date_start);

    if (isset($teacher_time_map[$key])) {
        $conflicts[] = [
            'type' => 'teacher',
            'message' => 'Conflitto docente: Coach ' . ($activity->teacher_firstname ? substr($activity->teacher_firstname, 0, 1) . substr($activity->teacher_lastname, 0, 1) : '?') . ' il ' . date('d/m H:i', $activity->date_start),
            'activity1' => $teacher_time_map[$key],
            'activity2' => $activity,
        ];
    } else {
        $teacher_time_map[$key] = $activity;
    }
}

// ============================================
// STATISTICHE GENERALI
// ============================================
$stats = new stdClass();
$stats->active_groups = count($groups);
$stats->total_activities = count($activities);
$stats->external_bookings = count($external_bookings);
$stats->conflicts_count = count($conflicts);

// Attendance stats
$sql = "SELECT
            COUNT(e.id) as total,
            SUM(CASE WHEN e.status = 'attended' THEN 1 ELSE 0 END) as attended,
            SUM(CASE WHEN e.status = 'absent' THEN 1 ELSE 0 END) as absent
        FROM {local_ftm_enrollments} e
        JOIN {local_ftm_activities} a ON e.activityid = a.id
        WHERE a.date_start >= :start AND a.date_start <= :end";
$attendance = $DB->get_record_sql($sql, ['start' => $monday_ts, 'end' => $friday_ts]);

$stats->total_enrollments = (int)($attendance->total ?? 0);
$stats->attended = (int)($attendance->attended ?? 0);
$stats->absent = (int)($attendance->absent ?? 0);
$stats->pending = $stats->total_enrollments - $stats->attended - $stats->absent;
$stats->absence_rate = ($stats->attended + $stats->absent) > 0
    ? round(($stats->absent / ($stats->attended + $stats->absent)) * 100, 1)
    : 0;

// Today's activities
$today_start = strtotime('today 00:00:00');
$today_end = strtotime('today 23:59:59');
$today_activities = array_filter($activities, function($a) use ($today_start, $today_end) {
    return $a->date_start >= $today_start && $a->date_start <= $today_end;
});

echo $OUTPUT->header();
?>

<style>
.secretary-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 1600px;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 2px solid #dee2e6;
}

.dashboard-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.week-navigator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.week-navigator a, .week-navigator button {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    cursor: pointer;
}

.week-navigator a:hover, .week-navigator button:hover {
    background: #e9ecef;
}

.week-current {
    background: #0066cc !important;
    color: white !important;
    font-weight: 600;
    padding: 10px 20px !important;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    text-align: center;
    border-left: 4px solid #dee2e6;
}

.stat-card.success { border-left-color: #28a745; }
.stat-card.danger { border-left-color: #dc3545; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-card.primary { border-left-color: #0066cc; }
.stat-card.purple { border-left-color: #7030A0; }

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #333;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    text-transform: uppercase;
}

/* Tabs */
.dashboard-tabs {
    display: flex;
    background: white;
    border-radius: 8px 8px 0 0;
    border: 1px solid #dee2e6;
    border-bottom: none;
    overflow: hidden;
}

.dashboard-tab {
    padding: 14px 24px;
    cursor: pointer;
    font-weight: 500;
    color: #666;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.dashboard-tab:hover {
    background: #f8f9fa;
    color: #333;
    text-decoration: none;
}

.dashboard-tab.active {
    color: #0066cc;
    border-bottom-color: #0066cc;
    background: white;
}

.dashboard-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
    padding: 25px;
}

/* Sections */
.section {
    margin-bottom: 30px;
}

.section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Room Occupancy Table */
.occupancy-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
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
}

.occupancy-table th.room-col {
    width: 140px;
    text-align: left;
}

.occupancy-table .day-header {
    min-width: 120px;
}

.occupancy-table .slot-header {
    font-size: 10px;
    color: #666;
    font-weight: normal;
}

.slot-cell {
    height: 50px;
    min-width: 60px;
    position: relative;
}

.slot-cell.free {
    background: #f0fff0;
}

.slot-cell.occupied {
    background: #fff0f0;
}

.slot-cell.external {
    background: #f0f0ff;
}

.slot-activity {
    font-size: 10px;
    padding: 4px;
    border-radius: 4px;
    background: #0066cc;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.slot-activity.external {
    background: #7030A0;
}

.slot-activity.giallo { background: #EAB308; color: #333; }
.slot-activity.grigio { background: #6B7280; }
.slot-activity.rosso { background: #EF4444; }
.slot-activity.marrone { background: #92400E; }
.slot-activity.viola { background: #7C3AED; }

.room-stats {
    font-size: 11px;
    color: #666;
}

.occupancy-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-top: 5px;
    overflow: hidden;
}

.occupancy-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}

.occupancy-bar-fill.low { background: #28a745; }
.occupancy-bar-fill.medium { background: #ffc107; }
.occupancy-bar-fill.high { background: #dc3545; }

/* Coach Workload */
.workload-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.coach-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 18px;
    border: 1px solid #dee2e6;
}

.coach-card.overload {
    border-color: #dc3545;
    background: #fff5f5;
}

.coach-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.coach-name {
    font-weight: 600;
    font-size: 16px;
}

.coach-initials {
    background: #0066cc;
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
}

.coach-hours {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.coach-hours small {
    font-size: 14px;
    font-weight: normal;
    color: #666;
}

.coach-hours.overload {
    color: #dc3545;
}

.coach-days {
    display: flex;
    gap: 5px;
    margin-top: 12px;
}

.coach-day {
    flex: 1;
    text-align: center;
    padding: 6px 4px;
    background: white;
    border-radius: 6px;
    font-size: 11px;
}

.coach-day .day-name {
    color: #666;
    font-size: 10px;
}

.coach-day .day-hours {
    font-weight: 600;
    color: #333;
}

.workload-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    margin-top: 10px;
    overflow: hidden;
}

.workload-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.workload-bar-fill.low { background: #28a745; }
.workload-bar-fill.medium { background: #ffc107; }
.workload-bar-fill.high { background: #dc3545; }

/* Conflicts */
.conflicts-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.conflict-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #fff5f5;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
}

.conflict-icon {
    font-size: 20px;
}

.conflict-message {
    flex: 1;
    font-size: 14px;
}

.conflict-actions {
    display: flex;
    gap: 8px;
}

.no-conflicts {
    padding: 30px;
    text-align: center;
    color: #28a745;
    font-size: 16px;
}

/* Today's Schedule */
.today-schedule {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.today-activity {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #0066cc;
}

.today-activity.external {
    border-left-color: #7030A0;
}

.today-time {
    font-weight: 600;
    color: #0066cc;
    min-width: 50px;
}

.today-details {
    flex: 1;
}

.today-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.today-meta {
    font-size: 12px;
    color: #666;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 20px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action-btn:hover {
    border-color: #0066cc;
    background: #f0f7ff;
    text-decoration: none;
    color: #0066cc;
}

.quick-action-btn .icon {
    font-size: 20px;
}

/* Two columns layout */
.two-columns {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
}

@media (max-width: 1200px) {
    .two-columns {
        grid-template-columns: 1fr;
    }
}

/* Buttons */
.btn-primary {
    background: #0066cc;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
}

.btn-primary:hover {
    background: #0052a3;
    color: white;
    text-decoration: none;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

/* Back link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #0066cc;
    text-decoration: none;
    margin-bottom: 15px;
    font-size: 14px;
}

.back-link:hover {
    text-decoration: underline;
}

/* Export buttons */
.export-buttons {
    display: flex;
    gap: 10px;
    margin-left: auto;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .workload-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>

<div class="secretary-dashboard">

    <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="back-link">
        ← Torna allo Scheduler
    </a>

    <!-- Header -->
    <div class="dashboard-header">
        <div class="dashboard-title">
            <span>📋</span> Dashboard Segreteria
        </div>
        <div class="week-navigator">
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
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php', ['week' => $prev_week, 'year' => $prev_year]); ?>">
                ← Sett. Prec.
            </a>
            <span class="week-current">
                KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?> | <?php echo date('d/m', $monday_ts); ?> - <?php echo date('d/m', $friday_ts - 86400); ?> <?php echo $year; ?>
            </span>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php', ['week' => $next_week, 'year' => $next_year]); ?>">
                Sett. Succ. →
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>" class="btn-secondary btn-sm">
                Oggi
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-value"><?php echo $stats->active_groups; ?></div>
            <div class="stat-label">Gruppi Attivi</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $stats->total_activities; ?></div>
            <div class="stat-label">Attività Settimana</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-value"><?php echo $stats->external_bookings; ?></div>
            <div class="stat-label">Progetti Esterni</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?php echo $stats->attended; ?></div>
            <div class="stat-label">Presenze</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-value"><?php echo $stats->absent; ?></div>
            <div class="stat-label">Assenze</div>
        </div>
        <div class="stat-card <?php echo $stats->conflicts_count > 0 ? 'danger' : 'success'; ?>">
            <div class="stat-value"><?php echo $stats->conflicts_count; ?></div>
            <div class="stat-label">Conflitti</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="dashboard-tabs">
        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=overview" class="dashboard-tab <?php echo $tab === 'overview' ? 'active' : ''; ?>">
            📊 Panoramica
        </a>
        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=rooms" class="dashboard-tab <?php echo $tab === 'rooms' ? 'active' : ''; ?>">
            🏫 Occupazione Aule
        </a>
        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=coaches" class="dashboard-tab <?php echo $tab === 'coaches' ? 'active' : ''; ?>">
            👥 Carico Docenti
        </a>
        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=conflicts" class="dashboard-tab <?php echo $tab === 'conflicts' ? 'active' : ''; ?>">
            ⚠️ Conflitti <?php if ($stats->conflicts_count > 0): ?><span style="background:#dc3545;color:white;padding:2px 6px;border-radius:10px;font-size:11px;margin-left:5px;"><?php echo $stats->conflicts_count; ?></span><?php endif; ?>
        </a>
        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=planning" class="dashboard-tab <?php echo $tab === 'planning' ? 'active' : ''; ?>">
            📅 Pianificazione
        </a>
    </div>

    <div class="dashboard-content">

        <?php if ($tab === 'overview'): ?>
        <!-- ============================================ -->
        <!-- TAB: PANORAMICA -->
        <!-- ============================================ -->

        <div class="two-columns">
            <div>
                <!-- Oggi -->
                <div class="section">
                    <?php
                    $giorni_it = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
                    $mesi_it = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
                    $oggi_label = $giorni_it[(int)date('w')] . ' ' . date('d') . ' ' . $mesi_it[(int)date('n')];
                    ?>
                    <h3 class="section-title">📅 Oggi - <?php echo $oggi_label; ?></h3>

                    <?php if (empty($today_activities)): ?>
                        <p style="color: #666; padding: 20px; text-align: center; background: #f8f9fa; border-radius: 8px;">
                            Nessuna attività programmata per oggi
                        </p>
                    <?php else: ?>
                        <div class="today-schedule">
                            <?php foreach ($today_activities as $activity):
                                $group_color = $activity->group_color ?? 'giallo';
                            ?>
                                <div class="today-activity">
                                    <div class="today-time"><?php echo date('H:i', $activity->date_start); ?></div>
                                    <div class="today-details">
                                        <div class="today-title"><?php echo s($activity->name); ?></div>
                                        <div class="today-meta">
                                            <?php echo $activity->room_name ?? 'Aula'; ?> |
                                            Coach <?php echo $activity->teacher_firstname ? substr($activity->teacher_firstname, 0, 1) . substr($activity->teacher_lastname, 0, 1) : '-'; ?> |
                                            <?php echo $activity->group_name ?? 'Gruppo'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Conflitti (se presenti) -->
                <?php if (!empty($conflicts)): ?>
                <div class="section">
                    <h3 class="section-title" style="color: #dc3545;">⚠️ Conflitti da Risolvere</h3>
                    <div class="conflicts-list">
                        <?php foreach (array_slice($conflicts, 0, 3) as $conflict): ?>
                            <div class="conflict-item">
                                <span class="conflict-icon"><?php echo $conflict['type'] === 'room' ? '🏫' : '👤'; ?></span>
                                <span class="conflict-message"><?php echo s($conflict['message']); ?></span>
                                <div class="conflict-actions">
                                    <a href="?tab=conflicts" class="btn-primary btn-sm">Risolvi</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($conflicts) > 3): ?>
                            <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=conflicts" style="text-align: center; display: block; padding: 10px; color: #0066cc;">
                                Vedi tutti i <?php echo count($conflicts); ?> conflitti →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Occupazione Aule Compatta -->
                <div class="section">
                    <h3 class="section-title">🏫 Occupazione Aule</h3>
                    <table class="occupancy-table">
                        <thead>
                            <tr>
                                <th class="room-col">Aula</th>
                                <th>Occupazione</th>
                                <th>Slot Liberi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_occupancy as $ro): ?>
                            <tr>
                                <td style="text-align: left; font-weight: 600;">
                                    <?php echo s($ro['room']->name); ?>
                                    <div class="room-stats"><?php echo $ro['room']->capacity; ?> postazioni</div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo $ro['occupancy_percent']; ?>%</div>
                                    <div class="occupancy-bar">
                                        <?php
                                        $bar_class = 'low';
                                        if ($ro['occupancy_percent'] > 70) $bar_class = 'high';
                                        else if ($ro['occupancy_percent'] > 40) $bar_class = 'medium';
                                        ?>
                                        <div class="occupancy-bar-fill <?php echo $bar_class; ?>" style="width: <?php echo $ro['occupancy_percent']; ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 18px; font-weight: 600; color: <?php echo $ro['free_slots'] > 0 ? '#28a745' : '#dc3545'; ?>">
                                        <?php echo $ro['free_slots']; ?>
                                    </span>
                                    <span style="color: #666; font-size: 12px;">/10</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 15px; text-align: right;">
                        <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=rooms" class="btn-primary btn-sm">Dettaglio Completo →</a>
                    </div>
                </div>
            </div>

            <div>
                <!-- Azioni Rapide -->
                <div class="section">
                    <h3 class="section-title">⚡ Azioni Rapide</h3>
                    <div class="quick-actions" style="grid-template-columns: 1fr;">
                        <!-- CREAZIONE RAPIDA -->
                        <button onclick="ftmOpenModal('createActivity')" class="quick-action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none;">
                            <span class="icon">➕</span> Nuova Attività
                        </button>
                        <button onclick="ftmOpenModal('createExternal')" class="quick-action-btn" style="background: linear-gradient(135deg, #0066cc 0%, #00a3e0 100%); color: white; border: none;">
                            <span class="icon">🏢</span> Prenota Aula (Esterno)
                        </button>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 10px 0;">
                        <!-- NAVIGAZIONE -->
                        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>" class="quick-action-btn">
                            <span class="icon">📅</span> Vai al Calendario
                        </a>
                        <a href="<?php echo new moodle_url('/local/ftm_scheduler/attendance.php'); ?>" class="quick-action-btn">
                            <span class="icon">📋</span> Registro Presenze
                        </a>
                        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>" class="quick-action-btn">
                            <span class="icon">🎨</span> Gestione Gruppi
                        </a>
                        <a href="<?php echo new moodle_url('/local/ftm_scheduler/import_calendar.php'); ?>" class="quick-action-btn">
                            <span class="icon">📊</span> Importa Excel
                        </a>
                        <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="quick-action-btn">
                            <span class="icon">👥</span> Gestione CPURC
                        </a>
                    </div>
                </div>

                <!-- Carico Docenti Compatto -->
                <div class="section">
                    <h3 class="section-title">👥 Carico Docenti</h3>
                    <?php if (empty($coach_workload)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">Nessun docente assegnato</p>
                    <?php else: ?>
                        <?php foreach ($coach_workload as $cw):
                            $is_overload = $cw['hours'] > $max_hours_week;
                            $workload_percent = min(100, ($cw['hours'] / $max_hours_week) * 100);
                            $bar_class = 'low';
                            if ($workload_percent > 90) $bar_class = 'high';
                            else if ($workload_percent > 60) $bar_class = 'medium';
                        ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="coach-initials"><?php echo s($cw['coach']->initials ?? '??'); ?></span>
                            <div style="flex: 1;">
                                <div style="font-weight: 500;"><?php echo s(($cw['coach']->firstname ?? '') . ' ' . ($cw['coach']->lastname ?? '')); ?></div>
                                <div class="workload-bar" style="margin-top: 4px;">
                                    <div class="workload-bar-fill <?php echo $bar_class; ?>" style="width: <?php echo $workload_percent; ?>%"></div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="coach-hours <?php echo $is_overload ? 'overload' : ''; ?>" style="font-size: 18px;">
                                    <?php echo number_format($cw['hours'], 1); ?>h
                                </span>
                                <?php if ($is_overload): ?>
                                    <span style="color: #dc3545; font-size: 11px; display: block;">SOVRACCARICO</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 15px; text-align: right;">
                            <a href="?week=<?php echo $week; ?>&year=<?php echo $year; ?>&tab=coaches" class="btn-primary btn-sm">Dettaglio Completo →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'rooms'): ?>
        <!-- ============================================ -->
        <!-- TAB: OCCUPAZIONE AULE -->
        <!-- ============================================ -->

        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 class="section-title" style="margin-bottom: 0;">🏫 Matrice Occupazione Aule - Settimana <?php echo $week; ?></h3>
                <div class="export-buttons">
                    <button class="btn-secondary btn-sm" onclick="window.print()">🖨️ Stampa</button>
                </div>
            </div>

            <table class="occupancy-table">
                <thead>
                    <tr>
                        <th class="room-col">Aula</th>
                        <?php foreach ($day_names as $idx => $day_name): ?>
                        <th class="day-header" colspan="2">
                            <?php echo $day_name; ?> <?php echo date('d/m', $week_dates[$idx]['timestamp']); ?>
                            <div class="slot-header">Matt | Pom</div>
                        </th>
                        <?php endforeach; ?>
                        <th style="width: 80px;">Totale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($room_occupancy as $ro): ?>
                    <tr>
                        <td class="room-col">
                            <strong><?php echo s($ro['room']->name); ?></strong>
                            <div class="room-stats"><?php echo $ro['room']->capacity; ?> posti</div>
                        </td>
                        <?php foreach ($ro['slots'] as $day_idx => $slots): ?>
                            <?php foreach (['matt', 'pom'] as $slot):
                                $activity = $slots[$slot];
                                $cell_class = 'free';
                                if ($activity) {
                                    $cell_class = !empty($activity->is_external) ? 'external' : 'occupied';
                                }
                            ?>
                            <td class="slot-cell <?php echo $cell_class; ?>"
                                <?php if (!$activity): ?>
                                data-date="<?php echo date('Y-m-d', $week_dates[$day_idx]['timestamp']); ?>"
                                data-slot="<?php echo $slot; ?>"
                                data-roomid="<?php echo $ro['room']->id; ?>"
                                onclick="ftmQuickCreate(this)"
                                style="cursor: pointer;"
                                title="Clicca per creare attività"
                                <?php endif; ?>
                            >
                                <?php if ($activity):
                                    $group_color = $activity->group_color ?? '';
                                    $activity_name = !empty($activity->is_external) ? $activity->project_name : $activity->name;
                                    $is_external = !empty($activity->is_external);
                                ?>
                                    <div class="slot-activity <?php echo $group_color; ?> <?php echo $is_external ? 'external' : ''; ?>"
                                         title="<?php echo s($activity_name); ?> - Clicca per modificare"
                                         style="cursor: pointer;"
                                         onclick="<?php echo $is_external ? 'ftmEditExternal(' . $activity->id . ')' : 'ftmEditActivity(' . $activity->id . ')'; ?>">
                                        <?php echo s(substr($activity_name, 0, 12)); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #28a745; font-size: 16px;">✓</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <td style="text-align: center;">
                            <div style="font-weight: 600; font-size: 18px;"><?php echo $ro['occupancy_percent']; ?>%</div>
                            <div class="occupancy-bar">
                                <?php
                                $bar_class = 'low';
                                if ($ro['occupancy_percent'] > 70) $bar_class = 'high';
                                else if ($ro['occupancy_percent'] > 40) $bar_class = 'medium';
                                ?>
                                <div class="occupancy-bar-fill <?php echo $bar_class; ?>" style="width: <?php echo $ro['occupancy_percent']; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px; display: flex; gap: 20px; font-size: 13px;">
                <div><span style="display: inline-block; width: 15px; height: 15px; background: #f0fff0; border: 1px solid #28a745; border-radius: 3px; vertical-align: middle;"></span> Libero</div>
                <div><span style="display: inline-block; width: 15px; height: 15px; background: #fff0f0; border: 1px solid #dc3545; border-radius: 3px; vertical-align: middle;"></span> Occupato (Attività)</div>
                <div><span style="display: inline-block; width: 15px; height: 15px; background: #f0f0ff; border: 1px solid #7030A0; border-radius: 3px; vertical-align: middle;"></span> Progetto Esterno</div>
            </div>
        </div>

        <?php elseif ($tab === 'coaches'): ?>
        <!-- ============================================ -->
        <!-- TAB: CARICO DOCENTI -->
        <!-- ============================================ -->

        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 class="section-title" style="margin-bottom: 0;">👥 Carico Docenti - Settimana <?php echo $week; ?></h3>
                <div style="font-size: 13px; color: #666;">
                    Soglia sovraccarico: <strong><?php echo $max_hours_week; ?>h/settimana</strong>
                </div>
            </div>

            <?php if (empty($coach_workload)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">Nessun docente assegnato ad attività questa settimana</p>
            <?php else: ?>
                <div class="workload-grid">
                    <?php foreach ($coach_workload as $cw):
                        $is_overload = $cw['hours'] > $max_hours_week;
                        $workload_percent = min(100, ($cw['hours'] / $max_hours_week) * 100);
                        $bar_class = 'low';
                        if ($workload_percent > 90) $bar_class = 'high';
                        else if ($workload_percent > 60) $bar_class = 'medium';
                    ?>
                    <div class="coach-card <?php echo $is_overload ? 'overload' : ''; ?>">
                        <div class="coach-header">
                            <span class="coach-name"><?php echo s(($cw['coach']->firstname ?? '') . ' ' . ($cw['coach']->lastname ?? '')); ?></span>
                            <span class="coach-initials"><?php echo s($cw['coach']->initials ?? '??'); ?></span>
                        </div>
                        <div class="coach-hours <?php echo $is_overload ? 'overload' : ''; ?>">
                            <?php echo number_format($cw['hours'], 1); ?>h
                            <small>/ <?php echo $max_hours_week; ?>h</small>
                            <?php if ($is_overload): ?>
                                <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px;">SOVRACCARICO</span>
                            <?php endif; ?>
                        </div>
                        <div class="workload-bar">
                            <div class="workload-bar-fill <?php echo $bar_class; ?>" style="width: <?php echo $workload_percent; ?>%"></div>
                        </div>
                        <div class="coach-days">
                            <?php foreach ($day_names as $idx => $day_name): ?>
                            <div class="coach-day">
                                <div class="day-name"><?php echo $day_name; ?></div>
                                <div class="day-hours"><?php echo number_format($cw['days'][$idx], 1); ?>h</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 12px; font-size: 12px; color: #666;">
                            <?php echo $cw['activities_count']; ?> attività assegnate
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'conflicts'): ?>
        <!-- ============================================ -->
        <!-- TAB: CONFLITTI -->
        <!-- ============================================ -->

        <div class="section">
            <h3 class="section-title">⚠️ Conflitti Rilevati - Settimana <?php echo $week; ?></h3>

            <?php if (empty($conflicts)): ?>
                <div class="no-conflicts">
                    <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                    <div>Nessun conflitto rilevato per questa settimana</div>
                </div>
            <?php else: ?>
                <div class="conflicts-list">
                    <?php foreach ($conflicts as $idx => $conflict): ?>
                    <div class="conflict-item">
                        <span class="conflict-icon"><?php echo $conflict['type'] === 'room' ? '🏫' : '👤'; ?></span>
                        <div class="conflict-message">
                            <strong><?php echo $conflict['type'] === 'room' ? 'Conflitto Aula' : 'Conflitto Docente'; ?>:</strong>
                            <?php echo s($conflict['message']); ?>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                Attività 1: <?php echo s($conflict['activity1']->name ?? $conflict['activity1']->project_name ?? '-'); ?><br>
                                Attività 2: <?php echo s($conflict['activity2']->name ?? $conflict['activity2']->project_name ?? '-'); ?>
                            </div>
                        </div>
                        <div class="conflict-actions">
                            <?php if (!empty($conflict['activity1']->id)):
                                $is_ext1 = !empty($conflict['activity1']->is_external);
                            ?>
                            <button onclick="<?php echo $is_ext1 ? 'ftmEditExternal(' . $conflict['activity1']->id . ')' : 'ftmEditActivity(' . $conflict['activity1']->id . ')'; ?>" class="btn-primary btn-sm">Modifica 1</button>
                            <?php endif; ?>
                            <?php if (!empty($conflict['activity2']->id)):
                                $is_ext2 = !empty($conflict['activity2']->is_external);
                            ?>
                            <button onclick="<?php echo $is_ext2 ? 'ftmEditExternal(' . $conflict['activity2']->id . ')' : 'ftmEditActivity(' . $conflict['activity2']->id . ')'; ?>" class="btn-secondary btn-sm">Modifica 2</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'planning'): ?>
        <!-- ============================================ -->
        <!-- TAB: PIANIFICAZIONE RAPIDA -->
        <!-- ============================================ -->

        <div class="section">
            <h3 class="section-title">📅 Pianificazione Rapida</h3>

            <div class="quick-actions">
                <button onclick="ftmOpenModal('createActivity')" class="quick-action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none;">
                    <span class="icon">➕</span> Nuova Attività
                </button>
                <button onclick="ftmOpenModal('createExternal')" class="quick-action-btn" style="background: linear-gradient(135deg, #0066cc 0%, #00a3e0 100%); color: white; border: none;">
                    <span class="icon">🏢</span> Prenota Aula (Esterno)
                </button>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>" class="quick-action-btn">
                    <span class="icon">🎨</span> Nuovo Gruppo
                </a>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/import_calendar.php'); ?>" class="quick-action-btn">
                    <span class="icon">📊</span> Importa da Excel
                </a>
            </div>
        </div>

        <!-- Slot Liberi -->
        <div class="section">
            <h3 class="section-title">✅ Slot Disponibili - Settimana <?php echo $week; ?></h3>

            <table class="occupancy-table">
                <thead>
                    <tr>
                        <th class="room-col">Aula</th>
                        <?php foreach ($day_names as $idx => $day_name): ?>
                        <th class="day-header">
                            <?php echo $day_name; ?> <?php echo date('d/m', $week_dates[$idx]['timestamp']); ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($room_occupancy as $ro): ?>
                    <tr>
                        <td class="room-col">
                            <strong><?php echo s($ro['room']->name); ?></strong>
                        </td>
                        <?php foreach ($ro['slots'] as $day_idx => $slots): ?>
                        <td style="padding: 5px;">
                            <?php
                            $free_matt = empty($slots['matt']);
                            $free_pom = empty($slots['pom']);
                            ?>
                            <?php if ($free_matt): ?>
                                <div style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 3px; cursor: pointer;"
                                     onclick="ftmQuickBook(<?php echo $ro['room']->id; ?>, '<?php echo date('Y-m-d', $week_dates[$day_idx]['timestamp']); ?>', 'matt')"
                                     title="Clicca per prenotare">
                                    08:30-11:45 ✓
                                </div>
                            <?php else: ?>
                                <div style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 3px;">
                                    Matt: Occupato
                                </div>
                            <?php endif; ?>
                            <?php if ($free_pom): ?>
                                <div style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer;"
                                     onclick="ftmQuickBook(<?php echo $ro['room']->id; ?>, '<?php echo date('Y-m-d', $week_dates[$day_idx]['timestamp']); ?>', 'pom')"
                                     title="Clicca per prenotare">
                                    13:15-16:30 ✓
                                </div>
                            <?php else: ?>
                                <div style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                    Pom: Occupato
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>

</div>

<!-- ============================================ -->
<!-- MODAL: CREAZIONE RAPIDA ATTIVITA -->
<!-- ============================================ -->
<div class="ftm-modal-overlay" id="modal-createActivity">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h3>📅 Crea Nuova Attività</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('createActivity')">&times;</button>
        </div>
        <form id="form-createActivity" onsubmit="return ftmSubmitActivity(event)">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Nome Attività *</label>
                    <input type="text" name="name" id="activity-name" required placeholder="Es: Test Meccanica">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="activity_type" id="activity-type">
                            <option value="week1">Attività Gruppo</option>
                            <option value="atelier">Atelier</option>
                            <option value="week2_test">Test Sett. 2</option>
                            <option value="week2_lab">Lab Sett. 2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gruppo</label>
                        <select name="groupid" id="activity-groupid">
                            <option value="">-- Nessun gruppo --</option>
                            <?php foreach ($manager::get_groups() as $g):
                                $ci = $colors[$g->color] ?? $colors['giallo'];
                                $kw = $g->calendar_week ? ' - KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                            ?>
                            <option value="<?php echo $g->id; ?>"><?php echo $ci['emoji'] . ' ' . $ci['name'] . $kw; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data *</label>
                        <input type="date" name="date" id="activity-date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Fascia Oraria *</label>
                        <select name="time_slot" id="activity-slot">
                            <option value="matt">Mattina (08:30-11:45)</option>
                            <option value="pom">Pomeriggio (13:15-16:30)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aula</label>
                        <select name="roomid" id="activity-roomid">
                            <option value="">-- Seleziona aula --</option>
                            <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r->id; ?>"><?php echo s($r->name); ?> (<?php echo $r->capacity; ?> posti)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coach</label>
                        <select name="teacherid" id="activity-teacherid">
                            <option value="">-- Seleziona coach --</option>
                            <?php foreach ($coaches as $c): ?>
                            <option value="<?php echo $c->userid; ?>"><?php echo s($c->initials . ' - ' . $c->firstname . ' ' . $c->lastname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" id="activity-notes" rows="2" placeholder="Note aggiuntive..."></textarea>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="btn-secondary" onclick="ftmCloseModal('createActivity')">Annulla</button>
                <button type="submit" class="btn-primary">✅ Crea Attività</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: CREAZIONE RAPIDA PRENOTAZIONE ESTERNA -->
<!-- ============================================ -->
<div class="ftm-modal-overlay" id="modal-createExternal">
    <div class="ftm-modal">
        <div class="ftm-modal-header" style="background: #f0f0ff;">
            <h3>🏢 Prenota Aula (Progetto Esterno)</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('createExternal')">&times;</button>
        </div>
        <form id="form-createExternal" onsubmit="return ftmSubmitExternal(event)">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Nome Progetto *</label>
                    <select name="project_name" id="external-project" required>
                        <option value="BIT URAR">BIT URAR</option>
                        <option value="BIT AI">BIT AI</option>
                        <option value="Corso Extra LADI">Corso Extra LADI</option>
                        <option value="Altro">Altro</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aula *</label>
                        <select name="roomid" id="external-roomid" required>
                            <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r->id; ?>"><?php echo s($r->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsabile</label>
                        <select name="responsible" id="external-responsible">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($coaches as $c): ?>
                            <option value="<?php echo s($c->initials); ?>"><?php echo s($c->initials); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data *</label>
                        <input type="date" name="date" id="external-date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Fascia Oraria *</label>
                        <select name="time_slot" id="external-slot">
                            <option value="all">Tutto il giorno</option>
                            <option value="matt">Solo Mattina</option>
                            <option value="pom">Solo Pomeriggio</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" id="external-notes" rows="2" placeholder="Note aggiuntive..."></textarea>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="btn-secondary" onclick="ftmCloseModal('createExternal')">Annulla</button>
                <button type="submit" class="btn-primary">✅ Prenota Aula</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: MODIFICA ATTIVITA -->
<!-- ============================================ -->
<div class="ftm-modal-overlay" id="modal-editActivity">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h3>✏️ Modifica Attività</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('editActivity')">&times;</button>
        </div>
        <form id="form-editActivity" onsubmit="return ftmUpdateActivity(event)">
            <input type="hidden" name="id" id="edit-activity-id">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Nome Attività *</label>
                    <input type="text" name="name" id="edit-activity-name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="activity_type" id="edit-activity-type">
                            <option value="week1">Attività Gruppo</option>
                            <option value="atelier">Atelier</option>
                            <option value="week2_test">Test Sett. 2</option>
                            <option value="week2_lab">Lab Sett. 2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gruppo</label>
                        <select name="groupid" id="edit-activity-groupid">
                            <option value="">-- Nessun gruppo --</option>
                            <?php foreach ($manager::get_groups() as $g):
                                $ci = $colors[$g->color] ?? $colors['giallo'];
                                $kw = $g->calendar_week ? ' - KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                            ?>
                            <option value="<?php echo $g->id; ?>"><?php echo $ci['emoji'] . ' ' . $ci['name'] . $kw; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="date" id="edit-activity-date">
                    </div>
                    <div class="form-group">
                        <label>Fascia Oraria</label>
                        <select name="time_slot" id="edit-activity-slot">
                            <option value="matt">Mattina (08:30-11:45)</option>
                            <option value="pom">Pomeriggio (13:15-16:30)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aula</label>
                        <select name="roomid" id="edit-activity-roomid">
                            <option value="">-- Seleziona aula --</option>
                            <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r->id; ?>"><?php echo s($r->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coach</label>
                        <select name="teacherid" id="edit-activity-teacherid">
                            <option value="">-- Seleziona coach --</option>
                            <?php foreach ($coaches as $c): ?>
                            <option value="<?php echo $c->userid; ?>"><?php echo s($c->initials . ' - ' . $c->firstname . ' ' . $c->lastname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" id="edit-activity-notes" rows="2"></textarea>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="btn-danger" onclick="ftmDeleteActivity()" style="margin-right: auto;">🗑️ Elimina</button>
                <button type="button" class="btn-secondary" onclick="ftmCloseModal('editActivity')">Annulla</button>
                <button type="submit" class="btn-primary">💾 Salva Modifiche</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: MODIFICA PRENOTAZIONE ESTERNA -->
<!-- ============================================ -->
<div class="ftm-modal-overlay" id="modal-editExternal">
    <div class="ftm-modal">
        <div class="ftm-modal-header" style="background: #f0f0ff;">
            <h3>✏️ Modifica Prenotazione</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('editExternal')">&times;</button>
        </div>
        <form id="form-editExternal" onsubmit="return ftmUpdateExternal(event)">
            <input type="hidden" name="id" id="edit-external-id">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Nome Progetto *</label>
                    <select name="project_name" id="edit-external-project" required>
                        <option value="BIT URAR">BIT URAR</option>
                        <option value="BIT AI">BIT AI</option>
                        <option value="Corso Extra LADI">Corso Extra LADI</option>
                        <option value="Altro">Altro</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aula *</label>
                        <select name="roomid" id="edit-external-roomid" required>
                            <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r->id; ?>"><?php echo s($r->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsabile</label>
                        <select name="responsible" id="edit-external-responsible">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($coaches as $c): ?>
                            <option value="<?php echo s($c->initials); ?>"><?php echo s($c->initials); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="date" id="edit-external-date">
                    </div>
                    <div class="form-group">
                        <label>Fascia Oraria</label>
                        <select name="time_slot" id="edit-external-slot">
                            <option value="all">Tutto il giorno</option>
                            <option value="matt">Solo Mattina</option>
                            <option value="pom">Solo Pomeriggio</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="notes" id="edit-external-notes" rows="2"></textarea>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="btn-danger" onclick="ftmDeleteExternal()" style="margin-right: auto;">🗑️ Elimina</button>
                <button type="button" class="btn-secondary" onclick="ftmCloseModal('editExternal')">Annulla</button>
                <button type="submit" class="btn-primary">💾 Salva Modifiche</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: GUIDA RAPIDA -->
<!-- ============================================ -->
<div class="ftm-modal-overlay" id="modal-help">
    <div class="ftm-modal" style="max-width: 800px;">
        <div class="ftm-modal-header" style="background: #e8f4fc;">
            <h3>📖 Guida Rapida - Dashboard Segreteria</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('help')">&times;</button>
        </div>
        <div class="ftm-modal-body" style="max-height: 500px; overflow-y: auto;">
            <h4>🎯 Funzioni Principali</h4>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>📅 Nuova Attività</strong></td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">Crea lezioni, test, atelier. Assegna aula e coach.</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>🏢 Prenota Aula</strong></td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">Riserva aule per progetti esterni (BIT, LADI).</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>✏️ Modifica</strong></td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">Clicca su qualsiasi attività per modificarla.</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>🗑️ Elimina</strong></td>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">Dal popup di modifica, usa il pulsante rosso "Elimina".</td>
                </tr>
            </table>

            <h4>📊 Tab Disponibili</h4>
            <ul style="line-height: 2;">
                <li><strong>Panoramica:</strong> Vista rapida di oggi, conflitti, occupazione</li>
                <li><strong>Occupazione Aule:</strong> Matrice settimanale con tutti gli slot</li>
                <li><strong>Carico Docenti:</strong> Ore lavorate per ogni coach</li>
                <li><strong>Conflitti:</strong> Problemi da risolvere (stessa aula/docente)</li>
                <li><strong>Pianificazione:</strong> Crea nuove attività e vedi slot liberi</li>
            </ul>

            <h4>🎨 Legenda Colori</h4>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                <span style="background: #FEF9C3; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #EAB308;">🟡 Giallo</span>
                <span style="background: #F3F4F6; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #6B7280;">⚫ Grigio</span>
                <span style="background: #FEE2E2; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #EF4444;">🔴 Rosso</span>
                <span style="background: #FED7AA; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #92400E;">🟤 Marrone</span>
                <span style="background: #F3E8FF; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #7C3AED;">🟣 Viola</span>
                <span style="background: #DBEAFE; padding: 5px 10px; border-radius: 4px; border-left: 4px solid #2563EB;">🏢 Esterno</span>
            </div>

            <h4>⚡ Scorciatoie</h4>
            <ul style="line-height: 2;">
                <li>Clicca su uno <strong>slot verde</strong> nella pianificazione per prenotare rapidamente</li>
                <li>Clicca su un'<strong>attività colorata</strong> per modificarla</li>
                <li>Usa i <strong>pulsanti freccia</strong> per navigare tra le settimane</li>
            </ul>
        </div>
        <div class="ftm-modal-footer">
            <button type="button" class="btn-primary" onclick="ftmCloseModal('help')">Ho capito!</button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.ftm-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.ftm-modal-overlay.active {
    display: flex;
}

.ftm-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.ftm-modal-header {
    padding: 18px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.ftm-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.ftm-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.ftm-modal-close:hover {
    color: #333;
}

.ftm-modal-body {
    padding: 20px;
}

.ftm-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 13px;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #0066cc;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.btn-danger {
    background: #dc3545;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
}

.btn-danger:hover {
    background: #c82333;
}

/* Clickable activity blocks */
.slot-activity.clickable {
    cursor: pointer;
    transition: transform 0.1s, box-shadow 0.1s;
}

.slot-activity.clickable:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Help button */
.help-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #0066cc;
    color: white;
    border: none;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1000;
}

.help-btn:hover {
    background: #0052a3;
    transform: scale(1.1);
}

/* Toast notification */
.toast {
    position: fixed;
    bottom: 80px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10001;
    animation: slideIn 0.3s ease;
}

.toast.success { background: #28a745; }
.toast.error { background: #dc3545; }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<!-- Help Button -->
<button class="help-btn" onclick="ftmOpenModal('help')" title="Guida Rapida">?</button>

<script>
const AJAX_URL = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_secretary.php';
const SESSKEY = '<?php echo sesskey(); ?>';

// ============================================
// MODAL FUNCTIONS
// ============================================
function ftmOpenModal(name) {
    document.getElementById('modal-' + name).classList.add('active');
}

function ftmCloseModal(name) {
    document.getElementById('modal-' + name).classList.remove('active');
}

// Close on overlay click
document.querySelectorAll('.ftm-modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ftm-modal-overlay.active').forEach(function(m) {
            m.classList.remove('active');
        });
    }
});

// ============================================
// TOAST NOTIFICATIONS
// ============================================
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.remove();
    }, 3000);
}

// ============================================
// CREATE ACTIVITY
// ============================================
function ftmSubmitActivity(e) {
    e.preventDefault();

    const form = document.getElementById('form-createActivity');
    const data = new FormData(form);
    data.append('action', 'create_activity');
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Attività creata con successo!', 'success');
            ftmCloseModal('createActivity');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    })
    .catch(err => {
        showToast('Errore di connessione', 'error');
    });

    return false;
}

// ============================================
// CREATE EXTERNAL BOOKING
// ============================================
function ftmSubmitExternal(e) {
    e.preventDefault();

    const form = document.getElementById('form-createExternal');
    const data = new FormData(form);
    data.append('action', 'create_external');
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Prenotazione creata con successo!', 'success');
            ftmCloseModal('createExternal');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    })
    .catch(err => {
        showToast('Errore di connessione', 'error');
    });

    return false;
}

// ============================================
// EDIT ACTIVITY - Open Modal
// ============================================
function ftmEditActivity(id) {
    fetch(AJAX_URL + '?action=get_activity&id=' + id + '&sesskey=' + SESSKEY)
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            const d = result.data;
            document.getElementById('edit-activity-id').value = d.id;
            document.getElementById('edit-activity-name').value = d.name;
            document.getElementById('edit-activity-type').value = d.activity_type || 'week1';
            document.getElementById('edit-activity-groupid').value = d.groupid || '';
            document.getElementById('edit-activity-date').value = d.date_start;
            document.getElementById('edit-activity-slot').value = d.time_slot;
            document.getElementById('edit-activity-roomid').value = d.roomid || '';
            document.getElementById('edit-activity-teacherid').value = d.teacherid || '';
            document.getElementById('edit-activity-notes').value = d.notes || '';
            ftmOpenModal('editActivity');
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });
}

// ============================================
// UPDATE ACTIVITY
// ============================================
function ftmUpdateActivity(e) {
    e.preventDefault();

    const form = document.getElementById('form-editActivity');
    const data = new FormData(form);
    data.append('action', 'update_activity');
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Attività aggiornata!', 'success');
            ftmCloseModal('editActivity');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });

    return false;
}

// ============================================
// DELETE ACTIVITY
// ============================================
function ftmDeleteActivity() {
    if (!confirm('Sei sicuro di voler eliminare questa attività?\n\nQuesta azione è irreversibile.')) {
        return;
    }

    const id = document.getElementById('edit-activity-id').value;
    const data = new FormData();
    data.append('action', 'delete_activity');
    data.append('id', id);
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Attività eliminata!', 'success');
            ftmCloseModal('editActivity');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });
}

// ============================================
// EDIT EXTERNAL - Open Modal
// ============================================
function ftmEditExternal(id) {
    fetch(AJAX_URL + '?action=get_external&id=' + id + '&sesskey=' + SESSKEY)
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            const d = result.data;
            document.getElementById('edit-external-id').value = d.id;
            document.getElementById('edit-external-project').value = d.project_name;
            document.getElementById('edit-external-roomid').value = d.roomid;
            document.getElementById('edit-external-date').value = d.date_start;
            document.getElementById('edit-external-slot').value = d.time_slot;
            document.getElementById('edit-external-responsible').value = d.responsible || '';
            document.getElementById('edit-external-notes').value = d.notes || '';
            ftmOpenModal('editExternal');
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });
}

// ============================================
// UPDATE EXTERNAL
// ============================================
function ftmUpdateExternal(e) {
    e.preventDefault();

    const form = document.getElementById('form-editExternal');
    const data = new FormData(form);
    data.append('action', 'update_external');
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Prenotazione aggiornata!', 'success');
            ftmCloseModal('editExternal');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });

    return false;
}

// ============================================
// DELETE EXTERNAL
// ============================================
function ftmDeleteExternal() {
    if (!confirm('Sei sicuro di voler eliminare questa prenotazione?')) {
        return;
    }

    const id = document.getElementById('edit-external-id').value;
    const data = new FormData();
    data.append('action', 'delete_external');
    data.append('id', id);
    data.append('sesskey', SESSKEY);

    fetch(AJAX_URL, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('Prenotazione eliminata!', 'success');
            ftmCloseModal('editExternal');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showToast('Errore: ' + result.message, 'error');
        }
    });
}

// ============================================
// QUICK BOOK FROM SLOT
// ============================================
function ftmQuickBook(roomId, date, slot) {
    document.getElementById('external-roomid').value = roomId;
    document.getElementById('external-date').value = date;
    document.getElementById('external-slot').value = slot;
    ftmOpenModal('createExternal');
}

// ============================================
// QUICK CREATE ACTIVITY FROM SLOT
// ============================================
function ftmQuickActivity(roomId, date, slot) {
    document.getElementById('activity-roomid').value = roomId;
    document.getElementById('activity-date').value = date;
    document.getElementById('activity-slot').value = slot;
    ftmOpenModal('createActivity');
}

// ============================================
// QUICK CREATE FROM EMPTY SLOT (click on cell)
// ============================================
function ftmQuickCreate(cell) {
    const date = cell.dataset.date;
    const slot = cell.dataset.slot;
    const roomid = cell.dataset.roomid;

    // Apri modale con scelta
    if (confirm('Cosa vuoi creare?\n\nOK = Nuova Attività\nAnnulla = Prenotazione Esterna')) {
        // Nuova attività
        document.getElementById('activity-roomid').value = roomid;
        document.getElementById('activity-date').value = date;
        document.getElementById('activity-slot').value = slot;
        ftmOpenModal('createActivity');
    } else {
        // Prenotazione esterna
        document.getElementById('external-roomid').value = roomid;
        document.getElementById('external-date').value = date;
        document.getElementById('external-slot').value = slot;
        ftmOpenModal('createExternal');
    }
}
</script>

<?php
echo $OUTPUT->footer();
