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
 * FTM Scheduler main page.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:view', $context);

// Page parameters
$tab = optional_param('tab', 'calendario', PARAM_ALPHA);
$view = optional_param('view', 'week', PARAM_ALPHA); // 'week' or 'month'
$week = optional_param('week', date('W'), PARAM_INT);
$year = optional_param('year', date('Y'), PARAM_INT);
$month = optional_param('month', date('n'), PARAM_INT);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/index.php', ['tab' => $tab]));
$PAGE->set_title(get_string('ftm_scheduler', 'local_ftm_scheduler'));
$PAGE->set_heading(get_string('ftm_scheduler', 'local_ftm_scheduler'));
$PAGE->set_pagelayout('standard');

// Get data
$manager = new \local_ftm_scheduler\manager();
$stats = $manager::get_dashboard_stats();
$groups = $manager::get_groups();
$active_groups = $manager::get_groups('active');
$rooms = $manager::get_rooms();
$atelier_catalog = $manager::get_atelier_catalog();
$colors = local_ftm_scheduler_get_colors();

// Get week dates for calendar
$week_dates = $manager::get_week_dates($year, $week);
$monday_ts = $week_dates[0]['timestamp'];
$friday_ts = $week_dates[4]['timestamp'] + 86400; // End of Friday

// Get activities for the week
$activities = $manager::get_activities([
    'date_start' => $monday_ts,
    'date_end' => $friday_ts,
]);

// Get external bookings for the week
$external_bookings = $manager::get_external_bookings($monday_ts, $friday_ts);

// Organize activities by day and slot
$calendar_data = [];
foreach ($week_dates as $day) {
    $calendar_data[$day['day_of_week']] = [
        'matt' => [],
        'pom' => [],
    ];
}

foreach ($activities as $activity) {
    $day_of_week = date('N', $activity->date_start);
    $hour = date('H', $activity->date_start);
    $slot = ($hour < 12) ? 'matt' : 'pom';

    if (isset($calendar_data[$day_of_week])) {
        $calendar_data[$day_of_week][$slot][] = $activity;
    }
}

// Add external bookings to calendar
foreach ($external_bookings as $booking) {
    $day_of_week = date('N', $booking->date_start);
    $hour = date('H', $booking->date_start);
    $slot = ($hour < 12) ? 'matt' : 'pom';

    if (isset($calendar_data[$day_of_week])) {
        $booking->is_external = true;
        $calendar_data[$day_of_week][$slot][] = $booking;
    }
}

// Month view data
$month_weeks = [];
$month_activities = [];
$month_names = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
$current_month_name = $month_names[$month];

if ($view === 'month') {
    $month_weeks = $manager::get_month_weeks($year, $month);
    $month_activities = $manager::get_month_activities($year, $month);
}

// Output starts
echo $OUTPUT->header();

// Include CSS inline to match mockup exactly
?>
<style>
/* Reset and base - IDENTICO al mockup */
.ftm-scheduler * {
    box-sizing: border-box;
}

.ftm-scheduler {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #333;
    max-width: 1600px;
    margin: 0 auto;
}

/* Buttons */
.ftm-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.ftm-btn-primary { background: #0066cc; color: white; }
.ftm-btn-secondary { background: #6c757d; color: white; }
.ftm-btn-danger { background: #dc3545; color: white; }
.ftm-btn-danger:hover { background: #c82333; }
.activity-block { cursor: pointer; transition: transform 0.1s, box-shadow 0.1s; }
.activity-block:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.ftm-btn-success { background: #28a745; color: white; }
.ftm-btn-sm { padding: 6px 12px; font-size: 13px; }

.ftm-btn:hover { opacity: 0.9; text-decoration: none; color: white; }

/* Colori Gruppi */
.gruppo-giallo { background: #FFFF00 !important; color: #333 !important; }
.gruppo-grigio { background: #808080 !important; color: white !important; }
.gruppo-rosso { background: #FF0000 !important; color: white !important; }
.gruppo-marrone { background: #996633 !important; color: white !important; }
.gruppo-viola { background: #7030A0 !important; color: white !important; }

.gruppo-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

/* Barra Gruppi Attivi */
.gruppi-bar {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.gruppi-bar-title {
    font-weight: 600;
    color: #666;
    margin-right: 10px;
}

.gruppo-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 500;
}

.gruppo-chip .week {
    background: rgba(0,0,0,0.15);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

/* Page Title */
.page-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title h2 { font-size: 24px; margin: 0; }

.page-title-buttons {
    display: flex;
    gap: 10px;
}

/* Tabs */
.ftm-tabs {
    display: flex;
    background: white;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
    border: 1px solid #dee2e6;
    border-bottom: none;
}

.ftm-tab {
    padding: 15px 25px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    color: #666;
    text-decoration: none;
}

.ftm-tab:hover { background: #f8f9fa; text-decoration: none; color: #666; }
.ftm-tab.active { color: #0066cc; border-bottom-color: #0066cc; }

.tab-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
    padding: 20px;
}

/* Stats */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    border: 1px solid #dee2e6;
    text-align: center;
}

.stat-card.yellow { border-left: 4px solid #EAB308; }
.stat-card.blue { border-left: 4px solid #0066cc; }
.stat-card.green { border-left: 4px solid #28a745; }
.stat-card.orange { border-left: 4px solid #fd7e14; }
.stat-card.red { border-left: 4px solid #dc3545; }

.stat-number { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: #666; margin-top: 5px; }

/* Alert */
.ftm-alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ftm-alert-success {
    background: #D1FAE5;
    border: 1px solid #10B981;
    color: #065F46;
}

.ftm-alert-warning {
    background: #FEF3C7;
    border: 1px solid #F59E0B;
    color: #92400E;
}

.ftm-alert-info {
    background: #DBEAFE;
    border: 1px solid #3B82F6;
    color: #1E40AF;
}

/* Legend */
.legend {
    display: flex;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

/* Filters */
.filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.filter-group select, .filter-group input {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

/* Calendar */
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.week-nav {
    display: flex;
    align-items: center;
    gap: 15px;
}

.week-nav button, .week-nav a {
    padding: 8px 15px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    color: #333;
}

.week-nav h3 {
    margin: 0;
    font-size: 18px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: 100px repeat(5, 1fr);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background: #f8f9fa;
    padding: 12px;
    font-weight: 600;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
}

.calendar-time-header {
    background: #e9ecef;
    padding: 12px;
    font-weight: 600;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
}

.time-slot-label {
    background: #f8f9fa;
    padding: 10px;
    font-size: 12px;
    text-align: center;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 120px;
}

.calendar-cell {
    min-height: 120px;
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    padding: 5px;
    background: white;
}

.calendar-cell:hover { background: #fafafa; }

/* Activity Blocks */
.activity-block {
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 11px;
    margin-bottom: 5px;
    cursor: pointer;
    border-left: 4px solid;
    transition: transform 0.1s;
}

.activity-block:hover { transform: scale(1.02); }

.activity-block.giallo {
    background: #FEF9C3;
    border-left-color: #EAB308;
}

.activity-block.grigio {
    background: #F3F4F6;
    border-left-color: #6B7280;
}

.activity-block.rosso {
    background: #FEE2E2;
    border-left-color: #EF4444;
}

.activity-block.marrone {
    background: #FED7AA;
    border-left-color: #92400E;
}

.activity-block.viola {
    background: #F3E8FF;
    border-left-color: #7C3AED;
}

.activity-block.external {
    background: #DBEAFE;
    border-left-color: #2563EB;
    border-left-style: dashed;
}

.activity-title {
    font-weight: 600;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.activity-info {
    color: #666;
    font-size: 10px;
}

.activity-gruppo-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.dot-giallo { background: #EAB308; }
.dot-grigio { background: #6B7280; }
.dot-rosso { background: #EF4444; }
.dot-marrone { background: #92400E; }
.dot-viola { background: #7C3AED; }

/* Gruppi Grid */
.gruppi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.gruppo-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #dee2e6;
    transition: all 0.2s;
}

.gruppo-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.gruppo-card-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gruppo-card-header.giallo { background: #FFFF00; color: #333; }
.gruppo-card-header.grigio { background: #808080; color: white; }
.gruppo-card-header.rosso { background: #FF0000; color: white; }
.gruppo-card-header.marrone { background: #996633; color: white; }
.gruppo-card-header.viola { background: #7030A0; color: white; }

.gruppo-card-header h3 {
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.gruppo-week-badge {
    background: rgba(0,0,0,0.2);
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 13px;
}

.gruppo-card-body {
    padding: 20px;
}

.gruppo-detail {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.gruppo-detail:last-child { border-bottom: none; }

.gruppo-progress {
    margin-top: 15px;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.progress-fill.giallo { background: #EAB308; }
.progress-fill.grigio { background: #6B7280; }
.progress-fill.rosso { background: #EF4444; }
.progress-fill.marrone { background: #92400E; }
.progress-fill.viola { background: #7C3AED; }

.gruppo-card-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
}

.gruppo-card-footer .ftm-btn { flex: 1; justify-content: center; }

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th, .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

.data-table tr:hover { background: #f8f9fa; }

/* Badge Aule */
.aula-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.aula-1 { background: #DBEAFE; color: #1E40AF; }
.aula-2 { background: #D1FAE5; color: #065F46; }
.aula-3 { background: #FEF3C7; color: #92400E; }

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-active { background: #D1FAE5; color: #065F46; }
.status-planning { background: #DBEAFE; color: #1E40AF; }
.status-completed { background: #E5E7EB; color: #374151; }

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 8px;
}

.action-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    text-decoration: none;
    color: #333;
}

.action-icon:hover {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

/* Modal */
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

.ftm-modal-overlay.active { display: flex; }

.ftm-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.ftm-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ftm-modal-header h3 {
    margin: 0;
}

.ftm-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.ftm-modal-body { padding: 20px; }

.ftm-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group { margin-bottom: 20px; }

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Color Picker */
.color-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.color-option {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    cursor: pointer;
    border: 3px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all 0.2s;
}

.color-option:hover { transform: scale(1.1); }
.color-option.selected { border-color: #333; }

.color-option.giallo { background: #FFFF00; }
.color-option.grigio { background: #808080; }
.color-option.rosso { background: #FF0000; }
.color-option.marrone { background: #996633; }
.color-option.viola { background: #7030A0; }

/* Remote slot */
.remote-slot {
    color: #999;
    font-size: 11px;
    padding: 10px;
    text-align: center;
}

/* View Toggle Buttons */
.view-toggle {
    display: flex;
    background: #f8f9fa;
    border-radius: 6px;
    padding: 4px;
    gap: 4px;
}

.view-toggle-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.view-toggle-btn:hover {
    background: #e9ecef;
    color: #333;
    text-decoration: none;
}

.view-toggle-btn.active {
    background: #0066cc;
    color: white;
}

.view-toggle-btn.active:hover {
    background: #0052a3;
    color: white;
}

/* Month View Grid */
.month-grid {
    display: grid;
    grid-template-columns: 80px repeat(5, 1fr);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.month-week-label {
    background: #f8f9fa;
    padding: 10px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100px;
}

.month-week-label .kw {
    color: #0066cc;
    font-size: 14px;
}

.month-week-label .dates {
    color: #999;
    font-size: 10px;
    margin-top: 5px;
}

.month-cell {
    min-height: 100px;
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    padding: 5px;
    background: white;
    position: relative;
}

.month-cell.other-month {
    background: #f8f9fa;
}

.month-cell:hover {
    background: #fafafa;
}

.month-cell .day-num {
    position: absolute;
    top: 5px;
    right: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #999;
}

.month-cell.other-month .day-num {
    color: #ccc;
}

.month-activity-mini {
    font-size: 10px;
    padding: 3px 5px;
    border-radius: 3px;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
    border-left: 3px solid;
}

.month-activity-mini.giallo { background: #FEF9C3; border-left-color: #EAB308; }
.month-activity-mini.grigio { background: #F3F4F6; border-left-color: #6B7280; }
.month-activity-mini.rosso { background: #FEE2E2; border-left-color: #EF4444; }
.month-activity-mini.marrone { background: #FED7AA; border-left-color: #92400E; }
.month-activity-mini.viola { background: #F3E8FF; border-left-color: #7C3AED; }
.month-activity-mini.external { background: #DBEAFE; border-left-color: #2563EB; }

.month-more-link {
    font-size: 10px;
    color: #0066cc;
    cursor: pointer;
}

.month-more-link:hover {
    text-decoration: underline;
}

/* Month Header */
.month-header-row {
    display: contents;
}

.month-day-header {
    background: #f8f9fa;
    padding: 10px;
    font-weight: 600;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .calendar-grid {
        overflow-x: auto;
    }

    .month-grid {
        overflow-x: auto;
    }

    .page-title {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="ftm-scheduler">
    <!-- Page Title -->
    <div class="page-title">
        <h2>📅 FTM Scheduler</h2>
        <div class="page-title-buttons">
            <a href="<?php echo new moodle_url('/local/competencymanager/sector_admin.php'); ?>" class="ftm-btn ftm-btn-secondary">
                👥 Gestione Settori
            </a>
            <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/import_calendar.php'); ?>" class="ftm-btn ftm-btn-secondary">
                📊 Importa Excel
            </a>
            <?php endif; ?>
            <button class="ftm-btn ftm-btn-success" onclick="ftmOpenModal('newGruppo')">
                ➕ Nuovo Gruppo
            </button>
            <button class="ftm-btn ftm-btn-primary" onclick="ftmOpenModal('newActivity')">
                📅 Nuova Attività
            </button>
            <button class="ftm-btn ftm-btn-secondary" onclick="ftmOpenModal('externalBooking')">
                🏢 Prenota Aula (Esterno)
            </button>
        </div>
    </div>
    
    <!-- Barra Gruppi Attivi -->
    <div class="gruppi-bar">
        <span class="gruppi-bar-title">🎨 GRUPPI ATTIVI:</span>
        <?php if (empty($active_groups)): ?>
            <span style="color: #999; font-size: 13px;">Nessun gruppo attivo</span>
        <?php else: ?>
            <?php foreach ($active_groups as $group): 
                $color_info = $colors[$group->color] ?? $colors['giallo'];
            ?>
                <div class="gruppo-chip gruppo-<?php echo $group->color; ?>">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>
                    <span class="week">Sett. 1</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <span style="color: #999; font-size: 13px; margin-left: auto;">
            📊 <?php echo $stats->active_groups; ?> gruppi attivi | 
            <?php echo $stats->students; ?> studenti | 
            <?php echo $stats->activities_week; ?> attività questa settimana
        </span>
    </div>
    
    <!-- Alert -->
    <?php if ($stats->active_groups > 0): ?>
    <div class="ftm-alert ftm-alert-success">
        <span>🚀</span>
        <div>
            <strong>Settimana KW<?php echo $week; ?></strong> - 
            Le attività sono state generate automaticamente. 
            <a href="#">Visualizza dettagli →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card yellow">
            <div class="stat-number"><?php echo $stats->active_groups; ?></div>
            <div class="stat-label">Gruppi Attivi</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number"><?php echo $stats->students; ?></div>
            <div class="stat-label">Studenti in Percorso</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats->activities_week; ?></div>
            <div class="stat-label">Attività Settimana</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats->rooms_used; ?></div>
            <div class="stat-label">Aule Utilizzate</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats->external_projects; ?></div>
            <div class="stat-label">Progetti Esterni</div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="ftm-tabs">
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>" 
           class="ftm-tab <?php echo $tab === 'calendario' ? 'active' : ''; ?>">📅 Calendario</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>"
           class="ftm-tab <?php echo $tab === 'gruppi' ? 'active' : ''; ?>">🎨 Gruppi</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'attivita']); ?>"
           class="ftm-tab <?php echo $tab === 'attivita' ? 'active' : ''; ?>">📋 Attività</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'aule']); ?>"
           class="ftm-tab <?php echo $tab === 'aule' ? 'active' : ''; ?>">🏫 Aule</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'atelier']); ?>"
           class="ftm-tab <?php echo $tab === 'atelier' ? 'active' : ''; ?>">🎭 Atelier</a>
        <?php if (has_capability('local/ftm_scheduler:markattendance', $context)): ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/attendance.php'); ?>"
           class="ftm-tab">📋 Presenze</a>
        <?php endif; ?>
        <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>"
           class="ftm-tab">🏢 Segreteria</a>
        <?php endif; ?>
    </div>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($tab === 'calendario'): ?>
            <!-- Tab Calendario -->
            <?php include(__DIR__ . '/tabs/calendario.php'); ?>
        <?php elseif ($tab === 'gruppi'): ?>
            <!-- Tab Gruppi -->
            <?php include(__DIR__ . '/tabs/gruppi.php'); ?>
        <?php elseif ($tab === 'attivita'): ?>
            <!-- Tab Attività -->
            <?php include(__DIR__ . '/tabs/attivita.php'); ?>
        <?php elseif ($tab === 'aule'): ?>
            <!-- Tab Aule -->
            <?php include(__DIR__ . '/tabs/aule.php'); ?>
        <?php elseif ($tab === 'atelier'): ?>
            <!-- Tab Atelier -->
            <?php include(__DIR__ . '/tabs/atelier.php'); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Nuovo Gruppo -->
<div class="ftm-modal-overlay" id="modal-newGruppo">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h3>➕ Crea Nuovo Gruppo</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('newGruppo')">×</button>
        </div>
        <form action="<?php echo new moodle_url('/local/ftm_scheduler/action.php'); ?>" method="post">
            <input type="hidden" name="action" value="create_group">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Seleziona Colore *</label>
                    <div class="color-options">
                        <?php foreach ($colors as $key => $color): ?>
                            <div class="color-option <?php echo $key; ?> <?php echo $key === 'giallo' ? 'selected' : ''; ?>" 
                                 title="<?php echo $color['name']; ?>"
                                 data-color="<?php echo $key; ?>"
                                 data-hex="<?php echo $color['hex']; ?>"
                                 onclick="ftmSelectColor(this)">
                                <?php echo $color['emoji']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="color" id="group_color" value="giallo">
                    <input type="hidden" name="color_hex" id="group_color_hex" value="#FFFF00">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Gruppo</label>
                        <input type="text" name="name" id="group_name" value="Gruppo Giallo - KW<?php echo $week; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Settimana Calendario (KW)</label>
                        <select name="calendar_week" onchange="ftmUpdateGroupName(this)">
                            <?php for ($w = $week; $w <= $week + 10; $w++): 
                                $monday = \local_ftm_scheduler\manager::get_week_dates($year, $w)[0];
                            ?>
                                <option value="<?php echo $w; ?>" <?php echo $w == $week ? 'selected' : ''; ?>>
                                    KW<?php echo str_pad($w, 2, '0', STR_PAD_LEFT); ?> - <?php echo date('j', $monday['timestamp']); ?> <?php echo local_ftm_scheduler_format_date($monday['timestamp'], 'month'); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Data Inizio (Lunedì) *</label>
                    <input type="date" name="entry_date" value="<?php echo date('Y-m-d', $week_dates[0]['timestamp']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Studenti da Assegnare</label>
                    <div style="border: 1px solid #dee2e6; border-radius: 6px; max-height: 200px; overflow-y: auto; padding: 10px;">
                        <p style="color: #999; text-align: center; margin: 0;">
                            Gli studenti verranno assegnati dopo la creazione del gruppo
                        </p>
                    </div>
                </div>
                
                <div class="ftm-alert ftm-alert-info">
                    <span>💡</span>
                    <div>
                        <strong>Cosa succede quando crei il gruppo:</strong>
                        <ul style="margin: 10px 0 0 20px; font-size: 13px;">
                            <li>Le attività della Settimana 1 vengono create automaticamente</li>
                            <li>Tutti gli studenti vengono iscritti alle attività</li>
                            <li>Notifiche email + calendario inviate agli studenti</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="ftm-btn ftm-btn-secondary" onclick="ftmCloseModal('newGruppo')">Annulla</button>
                <button type="submit" class="ftm-btn ftm-btn-success">✅ Crea Gruppo e Genera Attività</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Prenotazione Esterna -->
<div class="ftm-modal-overlay" id="modal-externalBooking">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h3>🏢 Prenota Aula per Progetto Esterno</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('externalBooking')">×</button>
        </div>
        <form action="<?php echo new moodle_url('/local/ftm_scheduler/action.php'); ?>" method="post">
            <input type="hidden" name="action" value="create_external_booking">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <div class="ftm-modal-body">
                <div class="form-group">
                    <label>Nome Progetto *</label>
                    <select name="project_name" required>
                        <option value="BIT URAR">BIT URAR</option>
                        <option value="BIT AI">BIT AI</option>
                        <option value="Corso Extra LADI">Corso Extra LADI</option>
                        <option value="Altro">Altro (specificare)</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Aula *</label>
                        <select name="roomid" required>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room->id; ?>">
                                    <?php echo $room->name; ?> (<?php echo $room->capacity; ?> postazioni)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data *</label>
                        <input type="date" name="booking_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fascia Oraria *</label>
                        <select name="time_slot">
                            <option value="all">Tutto il giorno (08:30-16:30)</option>
                            <option value="matt">Solo mattina (08:30-11:45)</option>
                            <option value="pom">Solo pomeriggio (13:15-16:30)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsabile</label>
                        <select name="responsible">
                            <option value="GM">GM</option>
                            <option value="RB">RB</option>
                            <option value="CB">CB</option>
                            <option value="FM">FM</option>
                            <option value="">Altro</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="ftm-modal-footer">
                <button type="button" class="ftm-btn ftm-btn-secondary" onclick="ftmCloseModal('externalBooking')">Annulla</button>
                <button type="submit" class="ftm-btn ftm-btn-primary">📅 Prenota Aula</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Visualizza Attività -->
<div class="ftm-modal-overlay" id="modal-viewActivity">
    <div class="ftm-modal">
        <div class="ftm-modal-header" style="background: #FEF9C3;">
            <h3 id="activity-modal-title">🟡 Attività - Gruppo</h3>
            <button class="ftm-modal-close" onclick="ftmCloseModal('viewActivity')">×</button>
        </div>
        <div class="ftm-modal-body" id="activity-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
        <div class="ftm-modal-footer">
            <button type="button" class="ftm-btn ftm-btn-secondary" onclick="ftmCloseModal('viewActivity')">Chiudi</button>
            <button type="button" class="ftm-btn ftm-btn-primary">✏️ Modifica</button>
        </div>
    </div>
</div>

<script>
// Modal functions
function ftmOpenModal(modalName) {
    document.getElementById('modal-' + modalName).classList.add('active');
}

function ftmCloseModal(modalName) {
    document.getElementById('modal-' + modalName).classList.remove('active');
}

// Color selection
function ftmSelectColor(element) {
    document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    
    const color = element.getAttribute('data-color');
    const hex = element.getAttribute('data-hex');
    
    document.getElementById('group_color').value = color;
    document.getElementById('group_color_hex').value = hex;
    
    // Update group name
    const colorNames = {
        'giallo': 'Giallo',
        'grigio': 'Grigio',
        'rosso': 'Rosso',
        'marrone': 'Marrone',
        'viola': 'Viola'
    };
    
    const nameInput = document.getElementById('group_name');
    const currentName = nameInput.value;
    const kwMatch = currentName.match(/KW\d+/);
    const kw = kwMatch ? kwMatch[0] : 'KW<?php echo $week; ?>';
    
    nameInput.value = 'Gruppo ' + colorNames[color] + ' - ' + kw;
}

// Update group name when week changes
function ftmUpdateGroupName(select) {
    const kw = 'KW' + select.value.toString().padStart(2, '0');
    const nameInput = document.getElementById('group_name');
    const currentName = nameInput.value;
    
    nameInput.value = currentName.replace(/KW\d+/, kw);
}

// View activity details
function ftmViewActivity(activityId) {
    // AJAX call to get activity details
    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax.php?action=get_activity&id=' + activityId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('activity-modal-title').innerHTML = data.title;
                document.getElementById('activity-modal-body').innerHTML = data.content;
                document.querySelector('#modal-viewActivity .ftm-modal-header').style.background = data.bg_color;
                ftmOpenModal('viewActivity');
            } else {
                alert('Errore: ' + (data.error || 'Impossibile caricare i dettagli'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Errore di connessione');
        });
}

// View external booking details
function ftmViewExternal(bookingId) {
    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax.php?action=get_external&id=' + bookingId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('activity-modal-title').innerHTML = data.title;
                document.getElementById('activity-modal-body').innerHTML = data.content;
                document.querySelector('#modal-viewActivity .ftm-modal-header').style.background = data.bg_color;
                ftmOpenModal('viewActivity');
            } else {
                alert('Errore: ' + (data.error || 'Impossibile caricare i dettagli'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Errore di connessione');
        });
}

// Close modal when clicking overlay
document.querySelectorAll('.ftm-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ftm-modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();
