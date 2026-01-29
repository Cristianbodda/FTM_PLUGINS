<?php
// ============================================
// CoachManager - Dashboard Coach V2
// ============================================
// Versione migliorata con:
// - 4 viste: Classica, Compatta, Standard, Dettagliata
// - Zoom A+ A- per accessibilità
// - Note coach
// - Timeline 6 settimane
// - Export Word
// - Preferenze utente salvate
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('classes/dashboard_helper.php');

require_login();
$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);
$colorfilter = optional_param('color', '', PARAM_ALPHA);
$weekfilter = optional_param('week', 0, PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_TEXT);
$view = optional_param('view', '', PARAM_ALPHA); // classica, compatta, standard, dettagliata
$zoom = optional_param('zoom', 0, PARAM_INT); // 90, 100, 120, 140

// Carica preferenze utente
$user_prefs = get_user_dashboard_preferences($USER->id);
if (empty($view)) {
    $view = $user_prefs['view'] ?? 'classica';
}
if ($zoom === 0) {
    $zoom = $user_prefs['zoom'] ?? 100;
}

// Salva preferenze se cambiate via URL
if (optional_param('save_prefs', 0, PARAM_INT)) {
    save_user_dashboard_preferences($USER->id, $view, $zoom);
}

// Setup pagina
$PAGE->set_url(new moodle_url('/local/coachmanager/coach_dashboard_v2.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coach_dashboard', 'local_coachmanager'));
$PAGE->set_heading(get_string('coach_dashboard', 'local_coachmanager'));
$PAGE->set_pagelayout('report');

// Carica dati
$dashboard = new \local_coachmanager\dashboard_helper($USER->id);
$students = $dashboard->get_my_students($courseid, $colorfilter, $weekfilter, $statusfilter, $search);
$courses = $dashboard->get_coach_courses();
$groups = $dashboard->get_color_groups();
$stats = $dashboard->get_dashboard_stats($students);
$end6weeks = $dashboard->get_students_end_6_weeks($students);
$calendar_data = $dashboard->get_calendar_data(date('Y'), date('m'));

echo $OUTPUT->header();

// ============================================
// CSS - Stile armonico con dashboard attuale
// ============================================
?>
<style>
/* =============================================
   COACH DASHBOARD V2 - CSS
   Mantiene colori e stile della versione classica
   ============================================= */

/* Zoom levels */
.coach-dashboard-v2.zoom-90 { font-size: 90%; }
.coach-dashboard-v2.zoom-100 { font-size: 100%; }
.coach-dashboard-v2.zoom-120 { font-size: 120%; }
.coach-dashboard-v2.zoom-140 { font-size: 140%; }

.coach-dashboard-v2 {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* View Selector & Zoom - GRANDE per over 50 */
.view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px 20px;
    background: white;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    flex-wrap: wrap;
    gap: 15px;
}

.view-selector {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.view-selector label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
    margin-right: 10px;
    display: flex;
    align-items: center;
}

.view-btn {
    padding: 12px 20px;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333 !important; /* SEMPRE VISIBILE - testo scuro */
}

.view-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

.view-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important; /* Testo bianco su sfondo viola */
    border-color: #667eea;
}

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.zoom-controls label {
    font-weight: 600;
    color: #555;
    font-size: 14px;
    margin-right: 5px;
}

.zoom-btn {
    width: 50px;
    height: 50px;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 20px;
    font-weight: 700;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.zoom-btn:hover {
    border-color: #667eea;
    color: #667eea;
    transform: scale(1.05);
}

.zoom-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.zoom-level {
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    min-width: 60px;
    text-align: center;
}

/* Dashboard Header - Grande */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h2 {
    font-size: 28px;
    color: #333;
    margin: 0;
}

.dashboard-header .student-count {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 16px;
    margin-left: 10px;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Buttons - Grandi per over 50 */
.btn {
    padding: 14px 24px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.btn-sm {
    padding: 10px 18px;
    font-size: 14px;
}

.btn-lg {
    padding: 16px 28px;
    font-size: 18px;
}

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 2px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.filters-header h4 {
    font-size: 16px;
    color: #667eea;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.filter-icon {
    font-size: 18px;
}

.filters-toggle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(102, 126, 234, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s;
    font-size: 14px;
    color: #667eea;
}

.filters-section.collapsed .filters-toggle {
    transform: rotate(-90deg);
}

.filters-section.collapsed .filters-body {
    display: none;
}

.filters-body {
    display: flex !important; /* FORZA orizzontale */
    flex-direction: row !important;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.filters-body .filter-group {
    flex: 1;
    min-width: 180px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-size: 14px;
    font-weight: 600;
    color: #555;
}

.filter-group select {
    padding: 14px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.2s;
    background: white;
}

.filter-group select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

/* Color Chips - Grandi */
.color-chips {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.color-chip {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.2s;
}

.color-chip:hover {
    transform: scale(1.15);
}

.color-chip.selected {
    border-color: #333;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 2px solid #e0e0e0;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card.violet { border-left: 5px solid #667eea; }
.stat-card.violet .stat-number { color: #667eea; }
.stat-card.blue { border-left: 5px solid #4facfe; }
.stat-card.blue .stat-number { color: #4facfe; }
.stat-card.green { border-left: 5px solid #28a745; }
.stat-card.green .stat-number { color: #28a745; }
.stat-card.orange { border-left: 5px solid #fd7e14; }
.stat-card.orange .stat-number { color: #fd7e14; }
.stat-card.red { border-left: 5px solid #dc3545; }
.stat-card.red .stat-number { color: #dc3545; }

.stat-number {
    font-size: 32px;
    font-weight: 700;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 8px;
}

/* Quick Filters - Grandi */
.quick-filters {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.quick-filter {
    padding: 12px 20px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.quick-filter:hover {
    border-color: #667eea;
    color: #667eea;
}

.quick-filter.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.quick-filter.end6 {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.quick-filter.end6.active {
    background: #ffc107;
    color: #333;
}

/* =============================================
   VISTA COMPATTA - Una riga per studente
   ============================================= */
.view-compatta .students-list {
    background: white;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    overflow: hidden;
}

.view-compatta .student-row {
    display: grid;
    grid-template-columns: 40px 250px 120px 80px 100px 100px 100px 1fr;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    transition: background 0.2s;
    gap: 15px;
}

.view-compatta .student-row:hover {
    background: #f8f9fa;
}

.view-compatta .student-row:last-child {
    border-bottom: none;
}

.view-compatta .student-row.header {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    font-weight: 700;
    font-size: 13px;
    color: #667eea;
    text-transform: uppercase;
}

.view-compatta .expand-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-compatta .expand-btn:hover {
    background: #667eea;
    color: white;
}

.view-compatta .student-name-cell {
    font-weight: 600;
    font-size: 15px;
}

.view-compatta .student-name-cell .email {
    font-size: 12px;
    color: #666;
    font-weight: normal;
}

.view-compatta .settore-badge {
    padding: 6px 14px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
}

.view-compatta .week-cell {
    font-weight: 600;
    text-align: center;
}

.view-compatta .competency-cell {
    font-weight: 700;
    font-size: 18px;
}

.view-compatta .competency-cell.danger {
    color: #dc3545;
}

.view-compatta .competency-cell.success {
    color: #28a745;
}

.view-compatta .status-cell {
    display: flex;
    gap: 5px;
}

.view-compatta .status-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.view-compatta .status-icon.done {
    background: #d4edda;
    color: #155724;
}

.view-compatta .status-icon.missing {
    background: #f8d7da;
    color: #721c24;
}

.view-compatta .actions-cell {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* Row color indicators */
.view-compatta .student-row.color-giallo { border-left: 5px solid #EAB308; }
.view-compatta .student-row.color-blu { border-left: 5px solid #3B82F6; }
.view-compatta .student-row.color-verde { border-left: 5px solid #10B981; }
.view-compatta .student-row.color-arancione { border-left: 5px solid #F97316; }
.view-compatta .student-row.color-rosso { border-left: 5px solid #EF4444; }
.view-compatta .student-row.color-viola { border-left: 5px solid #8B5CF6; }
.view-compatta .student-row.color-grigio { border-left: 5px solid #6B7280; }

/* Alert rows */
.view-compatta .student-row.alert-end6 {
    background: #fff3cd;
}

.view-compatta .student-row.alert-below {
    background: #f8d7da;
}

/* =============================================
   VISTA STANDARD - Card espandibili
   ============================================= */
.view-standard .students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 20px;
}

.view-standard .student-card {
    background: white;
    border-radius: 16px;
    border: 2px solid #e0e0e0;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.view-standard .student-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.view-standard .student-card.alert-border {
    border-color: #dc3545;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
}

.view-standard .student-card.end-6-weeks {
    border-color: #ffc107;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

/* Card Header - Colorato */
.view-standard .student-card-header {
    padding: 18px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.view-standard .student-card-header:hover {
    filter: brightness(0.97);
}

.view-standard .student-card-header.giallo {
    background: linear-gradient(135deg, #FEF9C3 0%, #FDE68A 100%);
    border-left: 5px solid #EAB308;
}

.view-standard .student-card-header.blu {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    border-left: 5px solid #3B82F6;
}

.view-standard .student-card-header.verde {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    border-left: 5px solid #10B981;
}

.view-standard .student-card-header.arancione {
    background: linear-gradient(135deg, #FFEDD5 0%, #FED7AA 100%);
    border-left: 5px solid #F97316;
}

.view-standard .student-card-header.rosso {
    background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
    border-left: 5px solid #EF4444;
}

.view-standard .student-card-header.viola {
    background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
    border-left: 5px solid #8B5CF6;
}

.view-standard .student-card-header.grigio {
    background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
    border-left: 5px solid #6B7280;
}

.view-standard .student-info-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.view-standard .collapse-toggle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s;
    font-size: 16px;
}

.view-standard .student-card.collapsed .collapse-toggle {
    transform: rotate(-90deg);
}

.view-standard .student-name {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.view-standard .student-email {
    font-size: 14px;
    color: #666;
}

.view-standard .student-badges {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.view-standard .badge-6-weeks {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #333;
    padding: 6px 14px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    animation: pulse 2s infinite;
}

.view-standard .badge-below {
    background: #dc3545;
    color: white;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.view-standard .settore-badge {
    padding: 6px 14px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.view-standard .week-badge {
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.view-standard .week-badge.giallo { background: #EAB308; color: #333; }
.view-standard .week-badge.blu { background: #3B82F6; color: white; }
.view-standard .week-badge.verde { background: #10B981; color: white; }
.view-standard .week-badge.arancione { background: #F97316; color: white; }
.view-standard .week-badge.rosso { background: #EF4444; color: white; }
.view-standard .week-badge.viola { background: #8B5CF6; color: white; }
.view-standard .week-badge.grigio { background: #6B7280; color: white; }

/* Collapsible Content */
.view-standard .student-card-collapsible {
    max-height: 1200px;
    overflow: hidden;
    transition: max-height 0.35s ease-out, opacity 0.25s ease;
    opacity: 1;
}

.view-standard .student-card.collapsed .student-card-collapsible {
    max-height: 0;
    opacity: 0;
}

.view-standard .student-card-body {
    padding: 22px;
}

/* Progress Section */
.view-standard .progress-section {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 18px;
}

.view-standard .progress-item {
    text-align: center;
    padding: 15px 10px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.view-standard .progress-item .value {
    font-size: 26px;
    font-weight: 700;
}

.view-standard .progress-item .label {
    font-size: 13px;
    color: #666;
    margin-top: 6px;
}

.view-standard .progress-item.competenze { border-left: 4px solid #667eea; }
.view-standard .progress-item.competenze .value { color: #667eea; }
.view-standard .progress-item.autoval { border-left: 4px solid #20c997; }
.view-standard .progress-item.autoval .value { color: #20c997; }
.view-standard .progress-item.lab { border-left: 4px solid #fd7e14; }
.view-standard .progress-item.lab .value { color: #fd7e14; }
.view-standard .progress-item.danger { border-left: 4px solid #dc3545; background: #f8d7da; }
.view-standard .progress-item.danger .value { color: #dc3545; }

.view-standard .mini-progress {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    margin-top: 8px;
    overflow: hidden;
}

.view-standard .mini-progress-fill {
    height: 100%;
    border-radius: 4px;
}

.view-standard .mini-progress-fill.violet { background: linear-gradient(90deg, #667eea, #764ba2); }
.view-standard .mini-progress-fill.teal { background: linear-gradient(90deg, #11998e, #38ef7d); }
.view-standard .mini-progress-fill.orange { background: #fd7e14; }
.view-standard .mini-progress-fill.red { background: #dc3545; }

/* Status Row */
.view-standard .status-row {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.view-standard .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.view-standard .status-badge.done { background: #d4edda; color: #155724; }
.view-standard .status-badge.pending { background: #fff3cd; color: #856404; }
.view-standard .status-badge.missing { background: #f8d7da; color: #721c24; }
.view-standard .status-badge.end-path { background: #fff3cd; color: #856404; }

/* Timeline 6 Settimane */
.view-standard .timeline-section {
    margin: 20px 0;
    padding: 18px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    border-radius: 12px;
    border: 1px solid rgba(102, 126, 234, 0.2);
}

.view-standard .timeline-title {
    font-size: 15px;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-standard .timeline-weeks {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.view-standard .timeline-week {
    flex: 1;
    min-width: 80px;
    padding: 12px;
    background: white;
    border-radius: 10px;
    text-align: center;
    border: 2px solid #e0e0e0;
    transition: all 0.2s;
}

.view-standard .timeline-week.completed {
    background: #d4edda;
    border-color: #28a745;
}

.view-standard .timeline-week.current {
    background: #fff3cd;
    border-color: #ffc107;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
}

.view-standard .timeline-week.future {
    background: #f8f9fa;
    border-color: #dee2e6;
    opacity: 0.7;
}

.view-standard .timeline-week .week-num {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 6px;
}

.view-standard .timeline-week.completed .week-num { color: #155724; }
.view-standard .timeline-week.current .week-num { color: #856404; }
.view-standard .timeline-week.future .week-num { color: #6c757d; }

.view-standard .timeline-week .week-icon {
    font-size: 20px;
    margin-bottom: 6px;
}

.view-standard .timeline-week .week-detail {
    font-size: 11px;
    color: #666;
}

/* Week Choices */
.view-standard .week-choices {
    margin-top: 18px;
    padding: 18px;
    border-radius: 12px;
}

.view-standard .week-choices.giallo {
    background: linear-gradient(135deg, #FEF9C3 0%, #FDE68A 100%);
    border: 2px solid #EAB308;
}

.view-standard .week-choices.blu {
    background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
    border: 2px solid #3B82F6;
}

.view-standard .week-choices.verde {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
    border: 2px solid #10B981;
}

.view-standard .week-choices h4 {
    font-size: 15px;
    margin: 0 0 12px 0;
}

.view-standard .week-choices.giallo h4 { color: #92400E; }
.view-standard .week-choices.blu h4 { color: #1E40AF; }
.view-standard .week-choices.verde h4 { color: #065F46; }

.view-standard .choice-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    font-size: 14px;
}

.view-standard .choice-row:last-child {
    border-bottom: none;
}

.view-standard .choice-label {
    font-weight: 600;
}

.view-standard .choice-select {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    min-width: 200px;
    border: 2px solid #ccc;
}

.view-standard .choice-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

/* Notes Section */
.view-standard .notes-section {
    margin-top: 18px;
    padding: 18px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.view-standard .notes-title {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-standard .notes-textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    resize: vertical;
    font-family: inherit;
}

.view-standard .notes-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.view-standard .notes-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

.view-standard .notes-meta {
    font-size: 12px;
    color: #666;
}

/* End Path Box */
.view-standard .end-path-box {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    padding: 18px;
    border-radius: 12px;
    border: 2px solid #ffc107;
    margin-top: 18px;
}

.view-standard .end-path-box h4 {
    color: #856404;
    font-size: 15px;
    margin: 0 0 10px 0;
}

.view-standard .end-path-box p {
    font-size: 14px;
    color: #856404;
    margin: 0;
}

/* Card Footer */
.view-standard .student-card-footer {
    padding: 18px 22px;
    background: #f8f9fa;
    display: flex;
    gap: 12px;
    border-top: 1px solid #e0e0e0;
    flex-wrap: wrap;
}

.view-standard .student-card-footer .btn {
    flex: 1;
    justify-content: center;
    min-width: 100px;
}

/* =============================================
   VISTA DETTAGLIATA - Tutto sempre aperto
   ============================================= */
.view-dettagliata .students-list {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.view-dettagliata .student-panel {
    background: white;
    border-radius: 16px;
    border: 2px solid #e0e0e0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.view-dettagliata .student-panel.alert-border {
    border-color: #dc3545;
}

.view-dettagliata .student-panel.end-6-weeks {
    border-color: #ffc107;
}

.view-dettagliata .panel-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.view-dettagliata .panel-header.giallo { background: linear-gradient(135deg, #FEF9C3 0%, #FDE68A 100%); border-left: 6px solid #EAB308; }
.view-dettagliata .panel-header.blu { background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%); border-left: 6px solid #3B82F6; }
.view-dettagliata .panel-header.verde { background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); border-left: 6px solid #10B981; }
.view-dettagliata .panel-header.arancione { background: linear-gradient(135deg, #FFEDD5 0%, #FED7AA 100%); border-left: 6px solid #F97316; }
.view-dettagliata .panel-header.rosso { background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%); border-left: 6px solid #EF4444; }
.view-dettagliata .panel-header.viola { background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%); border-left: 6px solid #8B5CF6; }
.view-dettagliata .panel-header.grigio { background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%); border-left: 6px solid #6B7280; }

.view-dettagliata .student-main-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.view-dettagliata .student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.view-dettagliata .student-name-block .name {
    font-size: 22px;
    font-weight: 700;
    color: #333;
}

.view-dettagliata .student-name-block .email {
    font-size: 15px;
    color: #666;
}

.view-dettagliata .student-badges {
    display: flex;
    gap: 12px;
    align-items: center;
}

.view-dettagliata .badge-6-weeks {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #333;
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
}

.view-dettagliata .settore-badge {
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.view-dettagliata .week-badge {
    padding: 10px 18px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 700;
}

.view-dettagliata .week-badge.giallo { background: #EAB308; color: #333; }
.view-dettagliata .week-badge.blu { background: #3B82F6; color: white; }
.view-dettagliata .week-badge.verde { background: #10B981; color: white; }

.view-dettagliata .panel-body {
    padding: 25px;
}

.view-dettagliata .panel-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

/* Left Column - Stats & Timeline */
.view-dettagliata .left-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.view-dettagliata .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.view-dettagliata .stat-box {
    text-align: center;
    padding: 18px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.view-dettagliata .stat-box .value {
    font-size: 30px;
    font-weight: 700;
}

.view-dettagliata .stat-box .label {
    font-size: 14px;
    color: #666;
    margin-top: 8px;
}

.view-dettagliata .stat-box.competenze { border-left: 5px solid #667eea; }
.view-dettagliata .stat-box.competenze .value { color: #667eea; }
.view-dettagliata .stat-box.autoval { border-left: 5px solid #20c997; }
.view-dettagliata .stat-box.autoval .value { color: #20c997; }
.view-dettagliata .stat-box.lab { border-left: 5px solid #fd7e14; }
.view-dettagliata .stat-box.lab .value { color: #fd7e14; }
.view-dettagliata .stat-box.danger { border-left: 5px solid #dc3545; background: #f8d7da; }
.view-dettagliata .stat-box.danger .value { color: #dc3545; }

/* Right Column - Notes & Actions */
.view-dettagliata .right-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.view-dettagliata .notes-box {
    padding: 18px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    flex: 1;
}

.view-dettagliata .notes-box h4 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 12px 0;
    color: #333;
}

.view-dettagliata .notes-textarea {
    width: 100%;
    min-height: 100px;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    resize: vertical;
    font-family: inherit;
}

.view-dettagliata .notes-textarea:focus {
    outline: none;
    border-color: #667eea;
}

/* Panel Footer - Actions */
.view-dettagliata .panel-footer {
    padding: 20px 25px;
    background: #f8f9fa;
    display: flex;
    gap: 15px;
    border-top: 1px solid #e0e0e0;
    flex-wrap: wrap;
}

/* =============================================
   VISTA CLASSICA - Identica all'originale
   ============================================= */
.view-classica {
    /* Importa stili dalla dashboard originale */
}

/* Import originale per vista classica */
.view-classica .students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

/* Responsive - MODIFICATO per over 50: testo sempre visibile, filtri sempre orizzontali */
@media (max-width: 1200px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    /* Filtri restano orizzontali anche su schermi medi */
    .filters-body {
        grid-template-columns: repeat(4, 1fr);
    }
    .view-dettagliata .panel-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .view-standard .students-grid,
    .view-classica .students-grid {
        grid-template-columns: 1fr;
    }
    .view-compatta .student-row {
        grid-template-columns: 40px 1fr 80px;
    }
    .view-compatta .student-row .settore-badge,
    .view-compatta .student-row .status-cell {
        display: none;
    }
    /* Filtri: 2 colonne su tablet, mai verticali */
    .filters-body {
        grid-template-columns: repeat(2, 1fr);
    }
    .view-controls {
        flex-direction: column;
        align-items: stretch;
    }
    .view-selector {
        justify-content: center;
    }
    .zoom-controls {
        justify-content: center;
    }
}

@media (max-width: 600px) {
    .view-btn {
        padding: 10px 14px;
        font-size: 13px;
    }
    /* RIMOSSO: non nascondere mai il testo dei bottoni */
    /* I bottoni devono essere SEMPRE leggibili per over 50 */

    /* Filtri: 2 colonne anche su mobile piccolo */
    .filters-body {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* =============================================
   NEW: PERCORSO ATELIER BOX
   ============================================= */
.atelier-section {
    margin-top: 18px;
    padding: 15px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 10px;
    border: 1px solid #7dd3fc;
}

.atelier-section .section-title {
    font-weight: 700;
    font-size: 14px;
    color: #0369a1;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.atelier-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.atelier-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    font-size: 13px;
}

.atelier-item.attended {
    background: #dcfce7;
    border-color: #86efac;
}

.atelier-item.enrolled {
    background: #fef3c7;
    border-color: #fcd34d;
}

.atelier-item.mandatory {
    border-color: #f87171;
    background: #fef2f2;
}

.atelier-item .atelier-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.atelier-item .atelier-status {
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 600;
}

.atelier-item.attended .atelier-status {
    background: #22c55e;
    color: white;
}

.atelier-item.enrolled .atelier-status {
    background: #f59e0b;
    color: white;
}

.atelier-item .btn-enroll {
    padding: 5px 12px;
    font-size: 12px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.atelier-item .btn-enroll:hover {
    background: #2563eb;
}

.atelier-item .btn-enroll:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.atelier-item .full-badge {
    font-size: 11px;
    color: #dc2626;
    font-weight: 600;
}

.mandatory-alert {
    margin-top: 10px;
    padding: 10px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    color: #dc2626;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* =============================================
   NEW: QUESTA SETTIMANA BOX
   ============================================= */
.week-activities-section {
    margin-top: 18px;
    padding: 15px;
    background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
    border-radius: 10px;
    border: 1px solid #facc15;
}

.week-activities-section .section-title {
    font-weight: 700;
    font-size: 14px;
    color: #a16207;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.week-activities-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.week-activity-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
    font-size: 13px;
    border-left: 3px solid #facc15;
}

.week-activity-item .day-label {
    font-weight: 700;
    color: #a16207;
    min-width: 40px;
}

.week-activity-item .activity-name {
    flex: 1;
}

.week-activity-item .activity-room {
    font-size: 12px;
    color: #666;
    background: #f3f4f6;
    padding: 2px 8px;
    border-radius: 4px;
}

.week-activity-item .activity-time {
    font-weight: 600;
    color: #0369a1;
}

/* =============================================
   NEW: STORICO ASSENZE
   ============================================= */
.absence-stats {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 12px;
    margin-left: 8px;
}

.absence-stats.good {
    background: #dcfce7;
    color: #166534;
}

.absence-stats.warning {
    background: #fef3c7;
    color: #a16207;
}

.absence-stats.danger {
    background: #fef2f2;
    color: #dc2626;
}

/* Enroll Modal */
.enroll-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.enroll-modal.active {
    display: flex;
}

.enroll-modal-content {
    background: white;
    border-radius: 12px;
    padding: 25px;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.enroll-modal-content h3 {
    margin: 0 0 20px;
    color: #333;
}

.enroll-date-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.enroll-date-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.enroll-date-item:hover:not(.full) {
    border-color: #3b82f6;
    background: #eff6ff;
}

.enroll-date-item.full {
    background: #f3f4f6;
    cursor: not-allowed;
}

.enroll-date-item .date-info {
    font-weight: 600;
}

.enroll-date-item .date-info .room {
    font-weight: 400;
    color: #666;
    font-size: 12px;
}

.enroll-date-item .spots {
    font-size: 13px;
}

.enroll-date-item .spots.available {
    color: #22c55e;
}

.enroll-date-item .spots.full {
    color: #dc2626;
}

.enroll-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<div class="coach-dashboard-v2 zoom-<?php echo $zoom; ?>">

    <!-- View Controls & Zoom -->
    <div class="view-controls">
        <div class="view-selector">
            <label>Vista:</label>
            <button class="view-btn <?php echo $view === 'classica' ? 'active' : ''; ?>"
                    onclick="changeView('classica')" title="Vista originale">
                <span>&#128196;</span>
                <span class="view-label">Classica</span>
            </button>
            <button class="view-btn <?php echo $view === 'compatta' ? 'active' : ''; ?>"
                    onclick="changeView('compatta')" title="Una riga per studente">
                <span>&#9776;</span>
                <span class="view-label">Compatta</span>
            </button>
            <button class="view-btn <?php echo $view === 'standard' ? 'active' : ''; ?>"
                    onclick="changeView('standard')" title="Card espandibili con dettagli">
                <span>&#128203;</span>
                <span class="view-label">Standard</span>
            </button>
            <button class="view-btn <?php echo $view === 'dettagliata' ? 'active' : ''; ?>"
                    onclick="changeView('dettagliata')" title="Tutto sempre visibile">
                <span>&#128202;</span>
                <span class="view-label">Dettagliata</span>
            </button>
        </div>
        <div class="zoom-controls">
            <label>Zoom:</label>
            <button class="zoom-btn <?php echo $zoom == 90 ? 'active' : ''; ?>"
                    onclick="changeZoom(90)" title="Piccolo">A-</button>
            <button class="zoom-btn <?php echo $zoom == 100 ? 'active' : ''; ?>"
                    onclick="changeZoom(100)" title="Normale">A</button>
            <button class="zoom-btn <?php echo $zoom == 120 ? 'active' : ''; ?>"
                    onclick="changeZoom(120)" title="Grande">A+</button>
            <button class="zoom-btn <?php echo $zoom == 140 ? 'active' : ''; ?>"
                    onclick="changeZoom(140)" title="Molto grande">A++</button>
            <span class="zoom-level"><?php echo $zoom; ?>%</span>
        </div>
    </div>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="header-left">
            <h2><?php echo get_string('my_students', 'local_coachmanager'); ?></h2>
            <span class="student-count"><?php echo count($students); ?> studenti</span>
        </div>
        <div class="header-actions">
            <button class="btn btn-info" onclick="location.href='coach_dashboard.php'" title="Torna alla versione classica">
                &#8592; Versione Classica
            </button>
            <button class="btn btn-warning" onclick="openQuickChoices()">
                <?php echo get_string('quick_choices', 'local_coachmanager'); ?>
            </button>
            <button class="btn btn-primary" onclick="location.href='reports_class.php'">
                <?php echo get_string('class_report', 'local_coachmanager'); ?>
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section" id="filtersSection">
        <div class="filters-header" onclick="toggleFilters()">
            <h4>
                <span class="filter-icon">&#9776;</span>
                <?php echo get_string('advanced_filters', 'local_coachmanager'); ?>
            </h4>
            <div class="filters-toggle">&#9660;</div>
        </div>
        <div class="filters-body">
            <form method="get" action="" id="filterForm">
                <input type="hidden" name="view" value="<?php echo s($view); ?>">
                <input type="hidden" name="zoom" value="<?php echo $zoom; ?>">

                <!-- TABELLA per forzare layout orizzontale -->
                <table style="width: 100%; border: none; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; vertical-align: top; width: 25%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('course', 'local_coachmanager'); ?></label>
                        <select name="courseid" onchange="this.form.submit()" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                            <option value=""><?php echo get_string('all_courses', 'local_coachmanager'); ?></option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course->id; ?>" <?php echo $courseid == $course->id ? 'selected' : ''; ?>>
                                <?php echo format_string($course->fullname); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="padding: 10px; vertical-align: top; width: 25%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('group_color', 'local_coachmanager'); ?></label>
                        <div class="color-chips" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php
                            $colors = ['giallo' => '#FFFF00', 'blu' => '#0066cc', 'verde' => '#28a745',
                                       'arancione' => '#fd7e14', 'rosso' => '#dc3545', 'viola' => '#7030A0', 'grigio' => '#808080'];
                            foreach ($colors as $name => $hex):
                            ?>
                            <div class="color-chip <?php echo $name; ?> <?php echo $colorfilter == $name ? 'selected' : ''; ?>"
                                 onclick="setColorFilter('<?php echo $name; ?>')"
                                 title="<?php echo ucfirst($name); ?>"
                                 style="background: <?php echo $hex; ?>; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; border: 3px solid <?php echo $colorfilter == $name ? '#333' : 'transparent'; ?>;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="color" id="colorFilter" value="<?php echo s($colorfilter); ?>">
                    </td>
                    <td style="padding: 10px; vertical-align: top; width: 25%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('week', 'local_coachmanager'); ?></label>
                        <select name="week" onchange="this.form.submit()" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                            <option value=""><?php echo get_string('all_weeks', 'local_coachmanager'); ?></option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $weekfilter == $i ? 'selected' : ''; ?>>
                                Settimana <?php echo $i; ?>
                                <?php echo $i == 6 ? ' (Fine)' : ''; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </td>
                    <td style="padding: 10px; vertical-align: top; width: 25%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('status', 'local_coachmanager'); ?></label>
                        <select name="status" onchange="this.form.submit()" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                            <option value=""><?php echo get_string('all_statuses', 'local_coachmanager'); ?></option>
                            <option value="end6" <?php echo $statusfilter == 'end6' ? 'selected' : ''; ?>>Fine 6 Settimane</option>
                            <option value="below50" <?php echo $statusfilter == 'below50' ? 'selected' : ''; ?>>Sotto Soglia 50%</option>
                            <option value="no_autoval" <?php echo $statusfilter == 'no_autoval' ? 'selected' : ''; ?>>Manca Autovalutazione</option>
                            <option value="no_lab" <?php echo $statusfilter == 'no_lab' ? 'selected' : ''; ?>>Manca Laboratorio</option>
                            <option value="no_choices" <?php echo $statusfilter == 'no_choices' ? 'selected' : ''; ?>>Mancano Scelte</option>
                        </select>
                    </td>
                </tr>
                </table>
            </form>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card violet">
            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
            <div class="stat-label">Studenti Assegnati</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number"><?php echo $stats['avg_competency']; ?>%</div>
            <div class="stat-label">Media Competenze</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['autoval_complete']; ?>/<?php echo $stats['total_students']; ?></div>
            <div class="stat-label">Autoval. Complete</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['lab_evaluated']; ?>/<?php echo $stats['total_students']; ?></div>
            <div class="stat-label">Lab Valutati</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['end_6_weeks']; ?></div>
            <div class="stat-label">Fine 6 Settimane</div>
        </div>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <button class="quick-filter <?php echo empty($statusfilter) ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>'">
            Tutti (<?php echo $stats['total_students']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_choices' ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=no_choices'">
            Mancano Scelte (<?php echo $stats['missing_choices']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_autoval' ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=no_autoval'">
            Manca Autoval (<?php echo $stats['missing_autoval']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_lab' ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=no_lab'">
            Manca Lab (<?php echo $stats['missing_lab']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'below50' ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=below50'">
            Sotto Soglia (<?php echo $stats['below_threshold']; ?>)
        </button>
        <button class="quick-filter end6 <?php echo $statusfilter == 'end6' ? 'active' : ''; ?>"
                onclick="location.href='?view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=end6'">
            Fine 6 Sett. (<?php echo $stats['end_6_weeks']; ?>)
        </button>
    </div>

    <!-- Students Container - Different views -->
    <?php
    // Render based on selected view
    switch ($view) {
        case 'compatta':
            render_view_compatta($students, $dashboard);
            break;
        case 'standard':
            render_view_standard($students, $dashboard);
            break;
        case 'dettagliata':
            render_view_dettagliata($students, $dashboard);
            break;
        case 'classica':
        default:
            render_view_classica($students, $dashboard);
            break;
    }
    ?>

</div>

<script>
// Change view
function changeView(newView) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', newView);
    url.searchParams.set('save_prefs', '1');
    window.location.href = url.toString();
}

// Change zoom
function changeZoom(newZoom) {
    const url = new URL(window.location.href);
    url.searchParams.set('zoom', newZoom);
    url.searchParams.set('save_prefs', '1');
    window.location.href = url.toString();
}

// Toggle filters
function toggleFilters() {
    document.getElementById('filtersSection').classList.toggle('collapsed');
}

// Set color filter
function setColorFilter(color) {
    const chips = document.querySelectorAll('.color-chip');
    chips.forEach(chip => chip.classList.remove('selected'));

    const input = document.getElementById('colorFilter');
    if (input.value === color) {
        input.value = '';
    } else {
        document.querySelector('.color-chip.' + color).classList.add('selected');
        input.value = color;
    }
    document.getElementById('filterForm').submit();
}

// Toggle card (for standard view)
function toggleCard(cardId) {
    document.getElementById(cardId).classList.toggle('collapsed');
}

// Expand all cards
function expandAllCards() {
    document.querySelectorAll('.student-card').forEach(card => {
        card.classList.remove('collapsed');
    });
}

// Collapse all cards
function collapseAllCards() {
    document.querySelectorAll('.student-card').forEach(card => {
        card.classList.add('collapsed');
    });
}

// Save notes
function saveNotes(studentId) {
    const textarea = document.querySelector('#notes-' + studentId);
    if (!textarea) return;

    const notes = textarea.value;

    fetch('ajax_save_notes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'studentid=' + studentId + '&notes=' + encodeURIComponent(notes) + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success feedback
            const btn = document.querySelector('#save-notes-btn-' + studentId);
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '&#10004; Salvato!';
                btn.style.background = '#28a745';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
            }
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => {
        alert('Errore di connessione');
    });
}

// Export Word
function exportWord(studentId) {
    window.location.href = 'export_word.php?studentid=' + studentId + '&sesskey=<?php echo sesskey(); ?>';
}

// Quick choices modal
function openQuickChoices() {
    alert('Funzionalità Scelte Rapide in arrivo!');
}

// Save choices
function saveChoices(studentId) {
    const testSelect = document.querySelector('select[data-studentid="' + studentId + '"][data-type="test"]');
    const labSelect = document.querySelector('select[data-studentid="' + studentId + '"][data-type="lab"]');

    const testId = testSelect ? testSelect.value : '';
    const labId = labSelect ? labSelect.value : '';

    if (!testId && !labId) {
        alert('Seleziona almeno un test o un laboratorio');
        return;
    }

    fetch('ajax_save_choices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'studentid=' + studentId + '&testid=' + testId + '&labid=' + labId + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Scelte salvate con successo!');
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    });
}

// ============================================
// ATELIER ENROLLMENT FUNCTIONS
// ============================================

let currentEnrollStudentId = null;
let currentEnrollAtelierId = null;

// Open enrollment modal
function openEnrollModal(studentId, atelierId, atelierName) {
    currentEnrollStudentId = studentId;
    currentEnrollAtelierId = atelierId;

    // Create modal if not exists
    let modal = document.getElementById('enrollModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'enrollModal';
        modal.className = 'enroll-modal';
        modal.innerHTML = `
            <div class="enroll-modal-content">
                <h3 id="enrollModalTitle">Iscrizione Atelier</h3>
                <p>Seleziona una data disponibile:</p>
                <div id="enrollDateList" class="enroll-date-list">
                    <div style="text-align: center; padding: 20px;">Caricamento date...</div>
                </div>
                <div class="enroll-modal-footer">
                    <button class="btn btn-secondary" onclick="closeEnrollModal()">Annulla</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeEnrollModal();
        });
    }

    // Update title
    document.getElementById('enrollModalTitle').textContent = 'Iscrizione: ' + atelierName;

    // Show modal
    modal.classList.add('active');

    // Load dates
    loadAtelierDates(atelierId);
}

// Close enrollment modal
function closeEnrollModal() {
    const modal = document.getElementById('enrollModal');
    if (modal) {
        modal.classList.remove('active');
    }
    currentEnrollStudentId = null;
    currentEnrollAtelierId = null;
}

// Load available dates for atelier
function loadAtelierDates(atelierId) {
    const dateList = document.getElementById('enrollDateList');

    fetch('ajax_enroll_atelier.php?action=getdates&atelierid=' + atelierId + '&studentid=' + currentEnrollStudentId + '&sesskey=<?php echo sesskey(); ?>')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.dates.length > 0) {
            let html = '';
            data.dates.forEach(date => {
                const isFull = date.is_full;
                const spotsClass = isFull ? 'full' : 'available';
                const spotsText = isFull ? 'PIENO' : date.available + ' posti';

                html += `
                    <div class="enroll-date-item ${isFull ? 'full' : ''}"
                         ${!isFull ? 'onclick="confirmEnroll(' + date.activity_id + ')"' : ''}>
                        <div class="date-info">
                            <strong>${date.date_formatted}</strong> - ${date.time_formatted}
                            <div class="room">${date.room || 'Aula da definire'}</div>
                        </div>
                        <div class="spots ${spotsClass}">${spotsText}</div>
                    </div>
                `;
            });
            dateList.innerHTML = html;
        } else {
            dateList.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">Nessuna data disponibile per questo atelier.</div>';
        }
    })
    .catch(err => {
        dateList.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc2626;">Errore nel caricamento delle date.</div>';
    });
}

// Confirm enrollment
function confirmEnroll(activityId) {
    if (!confirm('Confermi l\'iscrizione a questo atelier?')) {
        return;
    }

    fetch('ajax_enroll_atelier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=enroll&studentid=' + currentEnrollStudentId + '&activityid=' + activityId + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Iscrizione confermata!\n\n' + data.atelier_name + '\nData: ' + data.date);
            closeEnrollModal();
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(err => {
        alert('Errore di connessione. Riprova.');
    });
}
</script>

<?php
echo $OUTPUT->footer();

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get user dashboard preferences
 */
function get_user_dashboard_preferences($userid) {
    global $DB;

    // Try to get from user preferences
    $view = get_user_preferences('local_coachmanager_dashboard_view', 'classica', $userid);
    $zoom = get_user_preferences('local_coachmanager_dashboard_zoom', 100, $userid);

    return [
        'view' => $view,
        'zoom' => (int)$zoom
    ];
}

/**
 * Save user dashboard preferences
 */
function save_user_dashboard_preferences($userid, $view, $zoom) {
    set_user_preference('local_coachmanager_dashboard_view', $view, $userid);
    set_user_preference('local_coachmanager_dashboard_zoom', $zoom, $userid);
}

/**
 * Get student notes
 */
function get_student_notes($studentid, $coachid) {
    global $DB;

    $note = $DB->get_record('local_coachmanager_notes', [
        'studentid' => $studentid,
        'coachid' => $coachid
    ]);

    return $note ? $note->notes : '';
}

/**
 * Render VISTA COMPATTA
 */
function render_view_compatta($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-compatta">
        <div class="students-list">
            <!-- Header Row -->
            <div class="student-row header">
                <div></div>
                <div>Studente</div>
                <div>Settore</div>
                <div>Sett.</div>
                <div>Competenze</div>
                <div>Autoval</div>
                <div>Lab</div>
                <div>Azioni</div>
            </div>

            <?php if (empty($students)): ?>
            <div class="student-row">
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    Nessuno studente trovato. Prova a modificare i filtri.
                </div>
            </div>
            <?php else: ?>

            <?php foreach ($students as $student):
                $is_end6 = ($student->current_week ?? 0) >= 6;
                $is_below = ($student->competency_avg ?? 0) < 50;
                $row_class = '';
                if ($is_end6) $row_class = 'alert-end6';
                elseif ($is_below) $row_class = 'alert-below';
                $color = $student->group_color ?? 'giallo';
            ?>
            <div class="student-row color-<?php echo $color; ?> <?php echo $row_class; ?>">
                <div>
                    <button class="expand-btn" onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'" title="Vedi Report">
                        &#128270;
                    </button>
                </div>
                <div class="student-name-cell">
                    <?php echo fullname($student); ?>
                    <div class="email"><?php echo $student->email; ?></div>
                </div>
                <div>
                    <span class="settore-badge"><?php echo strtoupper($student->sector ?? 'N/D'); ?></span>
                </div>
                <div class="week-cell">
                    <?php echo $student->current_week ?? 1; ?>
                </div>
                <div class="competency-cell <?php echo $is_below ? 'danger' : 'success'; ?>">
                    <?php echo round($student->competency_avg ?? 0); ?>%
                </div>
                <div class="status-cell">
                    <span class="status-icon <?php echo ($student->autoval_done ?? false) ? 'done' : 'missing'; ?>"
                          title="Autovalutazione <?php echo ($student->autoval_done ?? false) ? 'completata' : 'mancante'; ?>">
                        <?php echo ($student->autoval_done ?? false) ? '&#10004;' : '&#10008;'; ?>
                    </span>
                </div>
                <div class="status-cell">
                    <span class="status-icon <?php echo ($student->lab_done ?? false) ? 'done' : 'missing'; ?>"
                          title="Laboratorio <?php echo ($student->lab_done ?? false) ? 'valutato' : 'mancante'; ?>">
                        <?php echo ($student->lab_done ?? false) ? '&#10004;' : '&#10008;'; ?>
                    </span>
                </div>
                <div class="actions-cell">
                    <button class="btn btn-secondary btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'"
                            title="Report Dettagliato">
                        &#128202;
                    </button>
                    <button class="btn btn-primary btn-sm"
                            onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>'"
                            title="Colloquio">
                        &#128172;
                    </button>
                    <?php if ($is_end6): ?>
                    <button class="btn btn-warning btn-sm"
                            onclick="exportWord(<?php echo $student->id; ?>)"
                            title="Esporta Word">
                        &#128196;
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render VISTA STANDARD
 */
function render_view_standard($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-standard">

        <!-- Expand/Collapse All -->
        <div style="margin-bottom: 15px; display: flex; gap: 10px;">
            <button class="btn btn-secondary btn-sm" onclick="expandAllCards()">
                &#9660; Espandi Tutto
            </button>
            <button class="btn btn-secondary btn-sm" onclick="collapseAllCards()">
                &#9650; Comprimi Tutto
            </button>
        </div>

        <div class="students-grid">
            <?php if (empty($students)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #e0e0e0;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#128101;</div>
                <h3 style="color: #666;">Nessuno studente trovato</h3>
                <p style="color: #999;">Prova a modificare i filtri di ricerca</p>
            </div>
            <?php else: ?>

            <?php foreach ($students as $student):
                $is_end6 = ($student->current_week ?? 0) >= 6;
                $is_below = ($student->competency_avg ?? 0) < 50;
                $card_class = $is_end6 ? 'end-6-weeks' : ($is_below ? 'alert-border' : '');
                $header_class = $student->group_color ?? 'giallo';
                $current_week = $student->current_week ?? 1;
                $notes = get_student_notes($student->id, $USER->id);

                // Load atelier, activities, and absence data
                $student_ateliers = $dashboard->get_student_ateliers($student->id, $current_week);
                $week_activities = $dashboard->get_student_this_week_activities($student->id);
                $absence_stats = $dashboard->get_student_absences($student->id);
            ?>
            <div class="student-card <?php echo $card_class; ?>" id="student-<?php echo $student->id; ?>">

                <!-- Card Header -->
                <div class="student-card-header <?php echo $header_class; ?>" onclick="toggleCard('student-<?php echo $student->id; ?>')">
                    <div class="student-info-left">
                        <div class="collapse-toggle">&#9660;</div>
                        <div>
                            <div class="student-name"><?php echo fullname($student); ?></div>
                            <div class="student-email"><?php echo $student->email; ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">FINE 6 SETT.</span>
                        <?php endif; ?>
                        <?php if ($is_below): ?>
                        <span class="badge-below">SOTTO SOGLIA</span>
                        <?php endif; ?>
                        <span class="settore-badge"><?php echo strtoupper($student->sector ?? 'N/D'); ?></span>
                        <span class="week-badge <?php echo $header_class; ?>">Sett. <?php echo $current_week; ?></span>
                    </div>
                </div>

                <!-- Collapsible Content -->
                <div class="student-card-collapsible">
                    <div class="student-card-body">

                        <!-- Progress Section -->
                        <div class="progress-section">
                            <div class="progress-item competenze <?php echo $is_below ? 'danger' : ''; ?>">
                                <div class="value"><?php echo round($student->competency_avg ?? 0); ?>%</div>
                                <div class="label">Competenze</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill <?php echo $is_below ? 'red' : 'violet'; ?>"
                                         style="width: <?php echo $student->competency_avg ?? 0; ?>%;"></div>
                                </div>
                            </div>
                            <div class="progress-item autoval">
                                <div class="value"><?php echo $student->autoval_avg !== null ? number_format($student->autoval_avg, 1) : '--'; ?></div>
                                <div class="label">Autovalutazione</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill teal"
                                         style="width: <?php echo ($student->autoval_avg ?? 0) * 20; ?>%;"></div>
                                </div>
                            </div>
                            <div class="progress-item lab">
                                <div class="value"><?php echo $student->lab_avg !== null ? number_format($student->lab_avg, 1) : '--'; ?></div>
                                <div class="label">Laboratorio</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill orange"
                                         style="width: <?php echo ($student->lab_avg ?? 0) * 20; ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Row -->
                        <div class="status-row">
                            <?php if ($student->quiz_done ?? false): ?>
                            <span class="status-badge done">&#10004; Quiz Fatto</span>
                            <?php else: ?>
                            <span class="status-badge missing">&#10008; Quiz Mancante</span>
                            <?php endif; ?>

                            <?php if ($student->autoval_done ?? false): ?>
                            <span class="status-badge done">&#10004; Autoval Fatta</span>
                            <?php else: ?>
                            <span class="status-badge missing">&#10008; Autoval Mancante</span>
                            <?php endif; ?>

                            <?php if ($student->lab_done ?? false): ?>
                            <span class="status-badge done">&#10004; Lab Valutato</span>
                            <?php elseif ($student->lab_pending ?? false): ?>
                            <span class="status-badge pending">&#9203; Lab in Attesa</span>
                            <?php else: ?>
                            <span class="status-badge missing">&#10008; Lab Mancante</span>
                            <?php endif; ?>

                            <?php if ($is_end6): ?>
                            <span class="status-badge end-path">&#127937; Fine Percorso</span>
                            <?php endif; ?>
                        </div>

                        <!-- Timeline 6 Settimane -->
                        <div class="timeline-section">
                            <div class="timeline-title">
                                &#128197; Timeline 6 Settimane
                            </div>
                            <div class="timeline-weeks">
                                <?php for ($week = 1; $week <= 6; $week++):
                                    $week_class = 'future';
                                    $week_icon = '&#9711;'; // Circle
                                    if ($week < $current_week) {
                                        $week_class = 'completed';
                                        $week_icon = '&#10004;'; // Check
                                    } elseif ($week == $current_week) {
                                        $week_class = 'current';
                                        $week_icon = '&#9679;'; // Filled circle
                                    }
                                ?>
                                <div class="timeline-week <?php echo $week_class; ?>">
                                    <div class="week-icon"><?php echo $week_icon; ?></div>
                                    <div class="week-num">Sett. <?php echo $week; ?></div>
                                    <div class="week-detail">
                                        <?php if ($week < $current_week): ?>
                                        Completata
                                        <?php elseif ($week == $current_week): ?>
                                        In corso
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- NEW: Questa Settimana -->
                        <?php if (!empty($week_activities)): ?>
                        <div class="week-activities-section">
                            <div class="section-title">
                                &#128197; Questa Settimana
                                <?php
                                $absence_class = 'good';
                                if ($absence_stats['absence_rate'] > 20) $absence_class = 'danger';
                                elseif ($absence_stats['absence_rate'] > 10) $absence_class = 'warning';
                                ?>
                                <span class="absence-stats <?php echo $absence_class; ?>">
                                    Assenze: <?php echo $absence_stats['absent']; ?>/<?php echo $absence_stats['total_activities']; ?>
                                </span>
                            </div>
                            <div class="week-activities-list">
                                <?php foreach ($week_activities as $activity): ?>
                                <div class="week-activity-item">
                                    <span class="day-label"><?php echo $activity->day_short; ?></span>
                                    <span class="activity-name"><?php echo $activity->name; ?></span>
                                    <span class="activity-room"><?php echo $activity->room_short ?? $activity->room; ?></span>
                                    <span class="activity-time"><?php echo $activity->time; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php elseif ($absence_stats['total_activities'] > 0): ?>
                        <div class="week-activities-section">
                            <div class="section-title">
                                &#128197; Storico Attività
                                <?php
                                $absence_class = 'good';
                                if ($absence_stats['absence_rate'] > 20) $absence_class = 'danger';
                                elseif ($absence_stats['absence_rate'] > 10) $absence_class = 'warning';
                                ?>
                                <span class="absence-stats <?php echo $absence_class; ?>">
                                    Assenze: <?php echo $absence_stats['absent']; ?>/<?php echo $absence_stats['total_activities']; ?>
                                    (<?php echo $absence_stats['absence_rate']; ?>%)
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- NEW: Percorso Atelier -->
                        <?php if ($current_week >= 3): ?>
                        <div class="atelier-section">
                            <div class="section-title">
                                &#127919; Percorso Atelier
                            </div>
                            <div class="atelier-list">
                                <?php
                                // Attended ateliers
                                foreach ($student_ateliers['attended'] as $atelier): ?>
                                <div class="atelier-item attended">
                                    <span class="atelier-name">
                                        &#10004; <?php echo $atelier->name; ?>
                                    </span>
                                    <span class="atelier-status">Completato</span>
                                </div>
                                <?php endforeach;

                                // Enrolled ateliers
                                foreach ($student_ateliers['enrolled'] as $atelier): ?>
                                <div class="atelier-item enrolled">
                                    <span class="atelier-name">
                                        &#128197; <?php echo $atelier->name; ?>
                                        <small>(<?php echo userdate($atelier->activity_date, '%d/%m'); ?>)</small>
                                    </span>
                                    <span class="atelier-status">Iscritto</span>
                                </div>
                                <?php endforeach;

                                // Available ateliers
                                foreach ($student_ateliers['available'] as $atelier):
                                    $has_dates = !empty($atelier->next_dates);
                                    $is_mandatory = $atelier->is_mandatory;
                                ?>
                                <div class="atelier-item <?php echo $is_mandatory ? 'mandatory' : ''; ?>">
                                    <span class="atelier-name">
                                        <?php echo $is_mandatory ? '&#9888;' : '&#9711;'; ?>
                                        <?php echo $atelier->name; ?>
                                        <?php if ($is_mandatory): ?>
                                        <small style="color: #dc2626;">(Obbligatorio)</small>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($has_dates): ?>
                                        <?php
                                        $first_date = $atelier->next_dates[0];
                                        if ($first_date->is_full): ?>
                                        <span class="full-badge">Pieno - Prossimo: <?php
                                            $next_available = null;
                                            foreach ($atelier->next_dates as $d) {
                                                if (!$d->is_full) { $next_available = $d; break; }
                                            }
                                            echo $next_available ? $next_available->date_formatted : 'N/D';
                                        ?></span>
                                        <?php else: ?>
                                        <button class="btn-enroll"
                                                onclick="openEnrollModal(<?php echo $student->id; ?>, <?php echo $atelier->id; ?>, '<?php echo addslashes($atelier->name); ?>')">
                                            Iscrivimi
                                        </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <span style="color: #666; font-size: 12px;">Nessuna data disponibile</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($student_ateliers['mandatory_missing']): ?>
                            <div class="mandatory-alert">
                                &#9888; ATTENZIONE: Atelier obbligatorio (Bilancio) non ancora iscritto!
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Week Choices (if needed) -->
                        <?php if (!$is_end6 && ($student->needs_choices ?? false)): ?>
                        <div class="week-choices <?php echo $header_class; ?>">
                            <h4>&#128221; Scelte per Settimana <?php echo $current_week + 1; ?></h4>
                            <div class="choice-row">
                                <span class="choice-label">Test Teoria:</span>
                                <select class="choice-select"
                                        data-studentid="<?php echo $student->id; ?>"
                                        data-type="test">
                                    <option value="">-- Seleziona Test --</option>
                                    <?php foreach ($dashboard->get_available_tests($student->sector) as $test): ?>
                                    <option value="<?php echo $test->id; ?>"><?php echo $test->name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="choice-row">
                                <span class="choice-label">Laboratorio:</span>
                                <select class="choice-select"
                                        data-studentid="<?php echo $student->id; ?>"
                                        data-type="lab">
                                    <option value="">-- Seleziona Lab --</option>
                                    <?php foreach ($dashboard->get_available_labs($student->sector) as $lab): ?>
                                    <option value="<?php echo $lab->id; ?>"><?php echo $lab->name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php elseif ($is_end6): ?>
                        <div class="end-path-box">
                            <h4>&#127937; Report Finale Richiesto</h4>
                            <p>Lo studente ha completato le 6 settimane. È necessario generare il report finale.</p>
                        </div>
                        <?php endif; ?>

                        <!-- Notes Section -->
                        <div class="notes-section">
                            <div class="notes-title">&#128221; Note Coach</div>
                            <textarea class="notes-textarea"
                                      id="notes-<?php echo $student->id; ?>"
                                      placeholder="Scrivi qui le tue note su questo studente..."><?php echo s($notes); ?></textarea>
                            <div class="notes-footer">
                                <span class="notes-meta">Visibili anche alla segreteria</span>
                                <button class="btn btn-success btn-sm"
                                        id="save-notes-btn-<?php echo $student->id; ?>"
                                        onclick="saveNotes(<?php echo $student->id; ?>)">
                                    &#128190; Salva Note
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="student-card-footer">
                        <button class="btn btn-info btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_student_view.php?studentid=<?php echo $student->id; ?>'">
                            &#128203; Profilo Semplice
                        </button>
                        <button class="btn btn-secondary btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                            &#128202; Report Avanzato
                        </button>
                        <?php if ($is_end6): ?>
                        <button class="btn btn-warning btn-sm"
                                onclick="exportWord(<?php echo $student->id; ?>)">
                            &#128196; Esporta Word
                        </button>
                        <?php elseif (!($student->autoval_done ?? false)): ?>
                        <button class="btn btn-warning btn-sm" onclick="sendReminder(<?php echo $student->id; ?>, 'autoval')">
                            &#128232; Sollecita Autoval
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-sm"
                                onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>'">
                            &#128172; Colloquio
                        </button>
                        <?php if ($student->needs_choices ?? false): ?>
                        <button class="btn btn-success btn-sm" onclick="saveChoices(<?php echo $student->id; ?>)">
                            &#10004; Salva Scelte
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render VISTA DETTAGLIATA
 */
function render_view_dettagliata($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-dettagliata">
        <div class="students-list">
            <?php if (empty($students)): ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #e0e0e0;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#128101;</div>
                <h3 style="color: #666;">Nessuno studente trovato</h3>
                <p style="color: #999;">Prova a modificare i filtri di ricerca</p>
            </div>
            <?php else: ?>

            <?php foreach ($students as $student):
                $is_end6 = ($student->current_week ?? 0) >= 6;
                $is_below = ($student->competency_avg ?? 0) < 50;
                $panel_class = $is_end6 ? 'end-6-weeks' : ($is_below ? 'alert-border' : '');
                $header_class = $student->group_color ?? 'giallo';
                $current_week = $student->current_week ?? 1;
                $notes = get_student_notes($student->id, $USER->id);
                $initials = strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1));
            ?>
            <div class="student-panel <?php echo $panel_class; ?>">

                <!-- Panel Header -->
                <div class="panel-header <?php echo $header_class; ?>">
                    <div class="student-main-info">
                        <div class="student-avatar"><?php echo $initials; ?></div>
                        <div class="student-name-block">
                            <div class="name"><?php echo fullname($student); ?></div>
                            <div class="email"><?php echo $student->email; ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">&#127937; FINE 6 SETTIMANE</span>
                        <?php endif; ?>
                        <span class="settore-badge"><?php echo strtoupper($student->sector ?? 'N/D'); ?></span>
                        <span class="week-badge <?php echo $header_class; ?>">Settimana <?php echo $current_week; ?></span>
                    </div>
                </div>

                <!-- Panel Body -->
                <div class="panel-body">
                    <div class="panel-grid">

                        <!-- Left Column -->
                        <div class="left-column">

                            <!-- Stats Grid -->
                            <div class="stats-grid">
                                <div class="stat-box competenze <?php echo $is_below ? 'danger' : ''; ?>">
                                    <div class="value"><?php echo round($student->competency_avg ?? 0); ?>%</div>
                                    <div class="label">Competenze</div>
                                </div>
                                <div class="stat-box autoval">
                                    <div class="value"><?php echo $student->autoval_avg !== null ? number_format($student->autoval_avg, 1) : '--'; ?></div>
                                    <div class="label">Autovalutazione</div>
                                </div>
                                <div class="stat-box lab">
                                    <div class="value"><?php echo $student->lab_avg !== null ? number_format($student->lab_avg, 1) : '--'; ?></div>
                                    <div class="label">Laboratorio</div>
                                </div>
                            </div>

                            <!-- Timeline -->
                            <div class="timeline-section" style="margin: 0; padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #e0e0e0;">
                                <div class="timeline-title" style="color: #333;">
                                    &#128197; Timeline 6 Settimane
                                </div>
                                <div class="timeline-weeks" style="display: flex; gap: 8px;">
                                    <?php for ($week = 1; $week <= 6; $week++):
                                        $week_class = 'future';
                                        $week_icon = '&#9711;';
                                        if ($week < $current_week) {
                                            $week_class = 'completed';
                                            $week_icon = '&#10004;';
                                        } elseif ($week == $current_week) {
                                            $week_class = 'current';
                                            $week_icon = '&#9679;';
                                        }
                                    ?>
                                    <div class="timeline-week <?php echo $week_class; ?>" style="flex: 1; min-width: 60px;">
                                        <div class="week-icon"><?php echo $week_icon; ?></div>
                                        <div class="week-num">S<?php echo $week; ?></div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Week Choices -->
                            <?php if (!$is_end6 && ($student->needs_choices ?? false)): ?>
                            <div class="week-choices <?php echo $header_class; ?>" style="margin: 0;">
                                <h4>&#128221; Scelte Settimana <?php echo $current_week + 1; ?></h4>
                                <div class="choice-row">
                                    <span class="choice-label">Test:</span>
                                    <select class="choice-select" data-studentid="<?php echo $student->id; ?>" data-type="test">
                                        <option value="">-- Seleziona --</option>
                                        <?php foreach ($dashboard->get_available_tests($student->sector) as $test): ?>
                                        <option value="<?php echo $test->id; ?>"><?php echo $test->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="choice-row">
                                    <span class="choice-label">Lab:</span>
                                    <select class="choice-select" data-studentid="<?php echo $student->id; ?>" data-type="lab">
                                        <option value="">-- Seleziona --</option>
                                        <?php foreach ($dashboard->get_available_labs($student->sector) as $lab): ?>
                                        <option value="<?php echo $lab->id; ?>"><?php echo $lab->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column -->
                        <div class="right-column">

                            <!-- Notes -->
                            <div class="notes-box">
                                <h4>&#128221; Note Coach (visibili anche a segreteria)</h4>
                                <textarea class="notes-textarea"
                                          id="notes-<?php echo $student->id; ?>"
                                          placeholder="Scrivi qui le tue note..."><?php echo s($notes); ?></textarea>
                                <div style="margin-top: 10px; text-align: right;">
                                    <button class="btn btn-success btn-sm"
                                            id="save-notes-btn-<?php echo $student->id; ?>"
                                            onclick="saveNotes(<?php echo $student->id; ?>)">
                                        &#128190; Salva Note
                                    </button>
                                </div>
                            </div>

                            <!-- Status Summary -->
                            <div style="padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #e0e0e0;">
                                <h4 style="margin: 0 0 12px 0; font-size: 15px;">&#128202; Stato Attività</h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php if ($student->quiz_done ?? false): ?>
                                    <span class="status-badge done">&#10004; Quiz</span>
                                    <?php else: ?>
                                    <span class="status-badge missing">&#10008; Quiz</span>
                                    <?php endif; ?>

                                    <?php if ($student->autoval_done ?? false): ?>
                                    <span class="status-badge done">&#10004; Autoval</span>
                                    <?php else: ?>
                                    <span class="status-badge missing">&#10008; Autoval</span>
                                    <?php endif; ?>

                                    <?php if ($student->lab_done ?? false): ?>
                                    <span class="status-badge done">&#10004; Lab</span>
                                    <?php elseif ($student->lab_pending ?? false): ?>
                                    <span class="status-badge pending">&#9203; Lab</span>
                                    <?php else: ?>
                                    <span class="status-badge missing">&#10008; Lab</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Footer -->
                <div class="panel-footer">
                    <button class="btn btn-info"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_student_view.php?studentid=<?php echo $student->id; ?>'">
                        &#128203; Profilo Semplice
                    </button>
                    <button class="btn btn-secondary"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                        &#128202; Report Avanzato
                    </button>
                    <button class="btn btn-primary"
                            onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>'">
                        &#128172; Colloquio
                    </button>
                    <?php if ($is_end6): ?>
                    <button class="btn btn-warning"
                            onclick="exportWord(<?php echo $student->id; ?>)">
                        &#128196; Esporta Word
                    </button>
                    <?php endif; ?>
                    <?php if ($student->needs_choices ?? false): ?>
                    <button class="btn btn-success" onclick="saveChoices(<?php echo $student->id; ?>)">
                        &#10004; Salva Scelte
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render VISTA CLASSICA (identica all'originale)
 */
function render_view_classica($students, $dashboard) {
    global $CFG, $USER;

    // Include original CSS
    require_once('styles/dashboard.css.php');
    ?>
    <div class="view-classica">

        <!-- Link to original -->
        <div style="margin-bottom: 15px; padding: 15px; background: #e3f2fd; border-radius: 10px; border: 1px solid #2196f3;">
            <strong>&#128161; Tip:</strong> Questa è la vista classica. Per la versione originale completa,
            <a href="coach_dashboard.php" style="color: #1976d2; font-weight: 600;">clicca qui</a>.
        </div>

        <div class="students-grid">
            <?php if (empty($students)): ?>
            <div class="no-students">
                <div class="icon">&#128101;</div>
                <h3>Nessuno studente trovato</h3>
                <p>Prova a modificare i filtri di ricerca</p>
            </div>
            <?php else: ?>

            <?php foreach ($students as $student):
                $is_end6 = ($student->current_week ?? 0) >= 6;
                $is_below = ($student->competency_avg ?? 0) < 50;
                $card_class = $is_end6 ? 'end-6-weeks' : ($is_below ? 'alert-border' : '');
                $header_class = $student->group_color ?? 'giallo';
            ?>
            <div class="student-card <?php echo $card_class; ?>"
                 id="student-<?php echo $student->id; ?>"
                 data-color="<?php echo $header_class; ?>">

                <div class="student-card-header <?php echo $header_class; ?>" onclick="toggleCard('student-<?php echo $student->id; ?>')">
                    <div class="student-info-left">
                        <div class="collapse-toggle">&#9660;</div>
                        <div>
                            <div class="student-name"><?php echo fullname($student); ?></div>
                            <div class="student-email"><?php echo $student->email; ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">FINE 6 SETT.</span>
                        <?php endif; ?>
                        <?php if ($is_below): ?>
                        <span class="badge-below">SOTTO SOGLIA</span>
                        <?php endif; ?>
                        <span class="settore-badge"><?php echo strtoupper($student->sector ?? 'N/D'); ?></span>
                        <span class="week-badge <?php echo $header_class; ?>">Sett. <?php echo $student->current_week ?? 1; ?></span>
                    </div>
                </div>

                <div class="student-card-collapsible">
                    <div class="student-card-body">
                        <div class="progress-section">
                            <div class="progress-item competenze <?php echo $is_below ? 'danger' : ''; ?>">
                                <div class="value"><?php echo round($student->competency_avg ?? 0); ?>%</div>
                                <div class="label">Competenze</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill <?php echo $is_below ? 'red' : 'violet'; ?>"
                                         style="width: <?php echo $student->competency_avg ?? 0; ?>%;"></div>
                                </div>
                            </div>
                            <div class="progress-item autoval">
                                <div class="value"><?php echo $student->autoval_avg !== null ? number_format($student->autoval_avg, 1) : '--'; ?></div>
                                <div class="label">Autoval</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill teal"
                                         style="width: <?php echo ($student->autoval_avg ?? 0) * 20; ?>%;"></div>
                                </div>
                            </div>
                            <div class="progress-item lab">
                                <div class="value"><?php echo $student->lab_avg !== null ? number_format($student->lab_avg, 1) : '--'; ?></div>
                                <div class="label">Lab</div>
                                <div class="mini-progress">
                                    <div class="mini-progress-fill orange"
                                         style="width: <?php echo ($student->lab_avg ?? 0) * 20; ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="status-row">
                            <?php if ($student->quiz_done ?? false): ?>
                            <span class="status-badge done">Quiz &#10004;</span>
                            <?php else: ?>
                            <span class="status-badge missing">Quiz &#10008;</span>
                            <?php endif; ?>

                            <?php if ($student->autoval_done ?? false): ?>
                            <span class="status-badge done">Autoval &#10004;</span>
                            <?php else: ?>
                            <span class="status-badge missing">Autoval &#10008;</span>
                            <?php endif; ?>

                            <?php if ($student->lab_done ?? false): ?>
                            <span class="status-badge done">Lab &#10004;</span>
                            <?php elseif ($student->lab_pending ?? false): ?>
                            <span class="status-badge pending">Lab &#9203;</span>
                            <?php else: ?>
                            <span class="status-badge missing">Lab &#10008;</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="student-card-footer">
                        <button class="btn btn-info btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_student_view.php?studentid=<?php echo $student->id; ?>'">
                            &#128203; Profilo
                        </button>
                        <button class="btn btn-secondary btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                            Report
                        </button>
                        <button class="btn btn-primary btn-sm"
                                onclick="location.href='reports_v2.php?studentid=<?php echo $student->id; ?>'">
                            Colloquio
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
