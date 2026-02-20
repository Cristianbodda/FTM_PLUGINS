<?php
// ============================================
// CoachManager - Course Students Page
// ============================================
// Shows ALL enrolled students in a course
// with the same card UI as Coach Dashboard V2
// + coach badge per student
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('classes/dashboard_helper.php');

// Helper functions (must be defined before use - conditional functions are not hoisted in PHP)
if (!function_exists('get_user_dashboard_preferences')) {
    function get_user_dashboard_preferences($userid) {
        $view = get_user_preferences('local_coachmanager_dashboard_view', 'classica', $userid);
        $zoom = get_user_preferences('local_coachmanager_dashboard_zoom', 100, $userid);
        return ['view' => $view, 'zoom' => (int)$zoom];
    }
}
if (!function_exists('save_user_dashboard_preferences')) {
    function save_user_dashboard_preferences($userid, $view, $zoom) {
        set_user_preference('local_coachmanager_dashboard_view', $view, $userid);
        set_user_preference('local_coachmanager_dashboard_zoom', $zoom, $userid);
    }
}
if (!function_exists('get_student_notes')) {
    function get_student_notes($studentid, $coachid) {
        global $DB;
        $note = $DB->get_record('local_coachmanager_notes', ['studentid' => $studentid, 'coachid' => $coachid]);
        return $note ? $note->notes : '';
    }
}

require_login();

// ============================================
// DEBUG MODE: ?mode=debug&courseid=2
// ============================================
$mode = optional_param('mode', '', PARAM_ALPHA);
if ($mode === 'debug' && is_siteadmin()) {
    $context = context_system::instance();
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><body style='font-family:Arial,sans-serif;padding:30px;max-width:900px;margin:0 auto;'>";
    echo "<h2>Debug: I Miei Studenti</h2>";

    echo "<h3>1. Utente corrente</h3>";
    echo "<p><b>User ID:</b> {$USER->id} | <b>Nome:</b> " . fullname($USER) . " | <b>Username:</b> {$USER->username} | <b>Admin:</b> SI</p>";

    echo "<h3>2. Tabella local_student_coaching</h3>";
    $table_exists = $DB->get_manager()->table_exists('local_student_coaching');
    echo "<p><b>Tabella esiste:</b> " . ($table_exists ? 'SI' : '<span style=\"color:red\">NO</span>') . "</p>";

    if ($table_exists) {
        $total = $DB->count_records('local_student_coaching');
        $my_active = $DB->count_records('local_student_coaching', ['coachid' => $USER->id, 'status' => 'active']);
        $my_all = $DB->count_records('local_student_coaching', ['coachid' => $USER->id]);
        echo "<p><b>Record totali:</b> {$total} | <b>Per te (ID {$USER->id}):</b> {$my_all} | <b>Attivi:</b> {$my_active}</p>";

        // All coaches
        echo "<h3>3. Tutti i Coach</h3>";
        $coaches = $DB->get_records_sql("
            SELECT sc.coachid, u.firstname, u.lastname, u.username,
                   COUNT(*) as total,
                   SUM(CASE WHEN sc.status = 'active' THEN 1 ELSE 0 END) as active_count
            FROM {local_student_coaching} sc
            JOIN {user} u ON u.id = sc.coachid
            GROUP BY sc.coachid, u.firstname, u.lastname, u.username
            ORDER BY u.lastname
        ");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee;'><th>Coach ID</th><th>Nome</th><th>Username</th><th>Tot</th><th>Attivi</th><th>Sei tu?</th></tr>";
        foreach ($coaches as $c) {
            $me = ($c->coachid == $USER->id) ? '<b style=\"color:green\">SI</b>' : 'no';
            echo "<tr><td>{$c->coachid}</td><td>" . s($c->firstname.' '.$c->lastname) . "</td><td>" . s($c->username) . "</td><td>{$c->total}</td><td>{$c->active_count}</td><td>{$me}</td></tr>";
        }
        echo "</table>";

        // First 10 records
        echo "<h3>4. Primi 10 record</h3>";
        $first10 = $DB->get_records_sql("SELECT * FROM {local_student_coaching} ORDER BY id LIMIT 10");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee;'><th>ID</th><th>userid</th><th>coachid</th><th>courseid</th><th>status</th></tr>";
        foreach ($first10 as $r) {
            echo "<tr><td>{$r->id}</td><td>{$r->userid}</td><td>{$r->coachid}</td><td>" . ($r->courseid ?? 'NULL') . "</td><td>{$r->status}</td></tr>";
        }
        echo "</table>";

        // Test get_my_students
        echo "<h3>5. Test get_my_students()</h3>";
        try {
            require_once('classes/dashboard_helper.php');
            $dashboard = new \local_coachmanager\dashboard_helper($USER->id);
            $students = $dashboard->get_my_students();
            echo "<p><b>Risultato:</b> " . count($students) . " studenti</p>";
            foreach ($students as $s) {
                echo "<p>- " . fullname($s) . " (ID: {$s->id})</p>";
            }
        } catch (\Throwable $e) {
            echo "<p style='color:red;'><b>ERRORE:</b> " . s($e->getMessage()) . "</p>";
        }
    }

    echo "<hr><p><a href='" . (new moodle_url('/local/coachmanager/coach_dashboard_v2.php'))->out() . "'>Torna a Dashboard Coach</a></p>";
    echo "</body></html>";
    die();
}

// courseid is required
$courseid = required_param('courseid', PARAM_INT);
$colorfilter = optional_param('color', '', PARAM_ALPHA);
$weekfilter = optional_param('week', 0, PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_TEXT);
$view = optional_param('view', '', PARAM_ALPHA);
$zoom = optional_param('zoom', 0, PARAM_INT);

// Permissions: coachmanager:view + (siteadmin OR grade:viewall in course)
$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

$coursecontext = context_course::instance($courseid);
if (!is_siteadmin() && !has_capability('moodle/grade:viewall', $coursecontext)) {
    throw new moodle_exception('nopermissions', 'error', '', 'view course students');
}

// Get course info
$course = get_course($courseid);

// Load user preferences
$user_prefs = get_user_dashboard_preferences($USER->id);
if (empty($view)) {
    $view = $user_prefs['view'] ?? 'classica';
}
if ($zoom === 0) {
    $zoom = $user_prefs['zoom'] ?? 100;
}

// Save preferences if changed via URL
if (optional_param('save_prefs', 0, PARAM_INT)) {
    save_user_dashboard_preferences($USER->id, $view, $zoom);
}

// Setup page
$PAGE->set_url(new moodle_url('/local/coachmanager/course_students.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$pagetitle = get_string('course_students', 'local_coachmanager') . ': ' . format_string($course->fullname);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('report');

// Load data
$dashboard = new \local_coachmanager\dashboard_helper($USER->id);
$students = $dashboard->get_course_students($courseid, $colorfilter, $weekfilter, $statusfilter, $search);
$stats = $dashboard->get_dashboard_stats($students);
$end6weeks = $dashboard->get_students_end_6_weeks($students);

// All available sectors for sector selector
$ALL_SECTORS = [
    'AUTOMOBILE' => 'Automobile',
    'MECCANICA' => 'Meccanica',
    'LOGISTICA' => 'Logistica',
    'ELETTRICITA' => 'Elettricita',
    'AUTOMAZIONE' => 'Automazione',
    'METALCOSTRUZIONE' => 'Metalcostruzione',
    'CHIMFARM' => 'Chimico-Farm.',
    'GEN' => 'Generico',
    'BIT' => 'BIT',
    'URAR' => 'URAR',
    'ESTERNO' => 'Esterno',
];

// Helper function to render sector multi-select chips + add dropdown
function render_sector_selector_cs($student) {
    global $ALL_SECTORS;
    $current = strtoupper($student->sector ?? '');
    $sectors_all = $student->sectors_all ?? ['primary' => $current, 'secondary' => null, 'tertiary' => null];

    $assigned = [];
    if (!empty($sectors_all['primary'])) $assigned[] = strtoupper($sectors_all['primary']);
    if (!empty($sectors_all['secondary'])) $assigned[] = strtoupper($sectors_all['secondary']);
    if (!empty($sectors_all['tertiary'])) $assigned[] = strtoupper($sectors_all['tertiary']);
    $assigned = array_values(array_unique($assigned));

    $medals = ['&#129351;', '&#129352;', '&#129353;'];
    $sid = $student->id;

    $json = htmlspecialchars(json_encode($assigned), ENT_QUOTES, 'UTF-8');

    $html = '<div class="sector-chips-wrap" id="sector-wrap-' . $sid . '" data-sectors="' . $json . '" onclick="event.stopPropagation();" style="display:flex; flex-direction:row; flex-wrap:wrap; align-items:center; gap:5px;">';

    foreach ($assigned as $idx => $sec) {
        $label = $ALL_SECTORS[$sec] ?? $sec;
        $medal = $medals[$idx] ?? '';
        $html .= '<span class="sector-chip" data-sector="' . $sec . '" style="display:inline-flex; white-space:nowrap;">';
        $html .= $medal . ' ' . $sec;
        $html .= '<button type="button" class="chip-remove" onclick="removeSectorChip(' . $sid . ', \'' . $sec . '\')" title="Rimuovi">&times;</button>';
        $html .= '</span>';
    }

    if (count($assigned) < 3) {
        $html .= '<div class="sector-add-wrap" style="display:inline-flex;">';
        $html .= '<button type="button" class="sector-add-btn" onclick="toggleSectorDropdown(' . $sid . ')" title="Aggiungi settore">+</button>';
        $html .= '<div class="sector-dropdown" id="sector-dd-' . $sid . '">';
        foreach ($ALL_SECTORS as $key => $label) {
            $disabled = in_array($key, $assigned) ? ' disabled' : '';
            $cls = in_array($key, $assigned) ? ' class="already-assigned"' : '';
            $html .= '<div' . $cls . ' data-value="' . $key . '"' . $disabled;
            if (!in_array($key, $assigned)) {
                $html .= ' onclick="addSectorChip(' . $sid . ', \'' . $key . '\')"';
            }
            $html .= '>' . $label . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

echo $OUTPUT->header();

// ============================================
// CSS - Same as Coach Dashboard V2
// ============================================
?>
<style>
/* =============================================
   COURSE STUDENTS - CSS
   Same as Coach Dashboard V2
   ============================================= */

/* Zoom levels */
.coach-dashboard-v2.zoom-90 { font-size: 90%; }
.coach-dashboard-v2.zoom-100 { font-size: 100%; }
.coach-dashboard-v2.zoom-120 { font-size: 120%; }
.coach-dashboard-v2.zoom-140 { font-size: 140%; }

/* Override Moodle container limits */
#page-content, #region-main, [role="main"], #region-main-box {
    max-width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}
#page.drawers .main-inner {
    max-width: 100% !important;
}

.coach-dashboard-v2 {
    max-width: 100%;
    margin: 0;
    padding: 15px 20px;
}

/* Sector badges */
.settore-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: #475569;
    color: white;
    display: inline-block;
    margin: 1px 0;
    white-space: nowrap;
}

.settore-secondary { background: #78808d !important; }
.settore-tertiary { background: #94979d !important; }

.sector-badges-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}

/* Coach badge */
.coach-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.coach-badge.assigned {
    background: #e8eff8;
    color: #2e5a96;
    border: 1px solid #a0bcd8;
}

.coach-badge.no-coach {
    background: #f8f0e0;
    color: #8a6d14;
    border: 1px solid #d4c48a;
}

/* View Selector & Zoom */
.view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px 20px;
    background: white;
    border-radius: 12px;
    border: 2px solid #b8bcc8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
    border: 2px solid #a0a8c0;
    background: #f8f9fc;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333 !important;
}

.view-btn:hover { border-color: #475569; color: #475569; }
.view-btn.active { background: #475569; color: white !important; border-color: #475569; }

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.zoom-controls label { font-weight: 600; color: #555; font-size: 14px; margin-right: 5px; }

.zoom-btn {
    width: 50px;
    height: 50px;
    border: 2px solid #a0a8c0;
    background: #f8f9fc;
    border-radius: 8px;
    cursor: pointer;
    font-size: 20px;
    font-weight: 700;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.zoom-btn:hover { border-color: #475569; color: #475569; transform: scale(1.05); }
.zoom-btn.active { background: #475569; color: white; border-color: #475569; }
.zoom-level { padding: 10px 15px; background: #f8f9fa; border-radius: 8px; font-weight: 600; font-size: 16px; min-width: 60px; text-align: center; }

/* Dashboard Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.dashboard-header h2 { font-size: 28px; color: #333; margin: 0; }
.dashboard-header .student-count { background: #f0f2f5; color: #475569; padding: 6px 16px; border-radius: 20px; font-size: 16px; margin-left: 10px; font-weight: 600; }
.header-actions { display: flex; gap: 12px; }

/* Breadcrumb-style course info */
.course-breadcrumb {
    background: #e8eff8;
    border: 1px solid #a0bcd8;
    border-radius: 10px;
    padding: 10px 20px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #2e5a96;
}

.course-breadcrumb a { color: #2e5a96; text-decoration: none; font-weight: 600; }
.course-breadcrumb a:hover { text-decoration: underline; }

/* Buttons */
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

.btn-primary { background: #475569; color: white; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(71, 85, 105, 0.3); background: #334155; }
.btn-secondary { background: #64748b; color: white; }
.btn-success { background: #3d7a60; color: white; }
.btn-warning { background: #9a6c20; color: white; }
.btn-info { background: #3a6a8a; color: white; }
.btn-sm { padding: 10px 18px; font-size: 14px; }
.btn-lg { padding: 16px 28px; font-size: 18px; }

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 2px solid #b8bcc8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.filters-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.filters-header h4 { font-size: 16px; color: #475569; display: flex; align-items: center; gap: 10px; margin: 0; }
.filter-icon { font-size: 18px; }

.filters-toggle {
    width: 30px; height: 30px; border-radius: 50%; background: #f0f2f5;
    display: flex; align-items: center; justify-content: center; transition: transform 0.3s; font-size: 14px; color: #475569;
}

.filters-section.collapsed .filters-toggle { transform: rotate(-90deg); }
.filters-section.collapsed .filters-body { display: none; }

.filters-body {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #c5c9d4;
}

.filters-body .filter-group { flex: 1; min-width: 180px; }
.filter-group { display: flex; flex-direction: column; gap: 8px; }
.filter-group label { font-size: 14px; font-weight: 600; color: #555; }
.filter-group select { padding: 14px 16px; border: 2px solid #a0a8c0; border-radius: 8px; font-size: 16px; transition: all 0.2s; background: white; color: #333; }
.filter-group select:focus { border-color: #475569; outline: none; box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.15); }

/* Color Chips */
.color-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.color-chip { width: 36px; height: 36px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: all 0.2s; }
.color-chip:hover { transform: scale(1.15); }
.color-chip.selected { border-color: #333; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }

/* Stats Row */
.stats-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 20px; border: 2px solid #c5c9d4; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.stat-card.violet { border-left: 4px solid #475569; }
.stat-card.violet .stat-number { color: #334155; }
.stat-card.blue { border-left: 4px solid #3a6a8a; }
.stat-card.blue .stat-number { color: #2c5a78; }
.stat-card.green { border-left: 4px solid #3d7a60; }
.stat-card.green .stat-number { color: #2d6a4e; }
.stat-card.orange { border-left: 4px solid #9a6c20; }
.stat-card.orange .stat-number { color: #7a5418; }
.stat-card.red { border-left: 4px solid #9b3030; }
.stat-card.red .stat-number { color: #8a2828; }
.stat-number { font-size: 32px; font-weight: 700; }
.stat-label { font-size: 14px; color: #444; margin-top: 8px; font-weight: 500; }

/* Quick Filters */
.quick-filters { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.quick-filter { padding: 12px 20px; background: #f0f2f8; border: 2px solid #a0a8c0; border-radius: 25px; font-size: 15px; cursor: pointer; transition: all 0.2s; font-weight: 600; color: #333; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.quick-filter:hover { border-color: #475569; color: #334155; background: #eef0f4; }
.quick-filter.active { background: #475569; color: white; border-color: #475569; box-shadow: 0 2px 6px rgba(71, 85, 105, 0.25); }
.quick-filter.end6 { background: #fef8e7; border-color: #c4a430; color: #6d5010; font-weight: 700; }
.quick-filter.end6.active { background: #8a6d14; color: white; border-color: #8a6d14; }

/* =============================================
   VISTA COMPATTA
   ============================================= */
.view-compatta .students-list { background: white; border-radius: 12px; border: 2px solid #b8bcc8; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.view-compatta .student-row { display: grid; grid-template-columns: 40px 220px 120px 100px 80px 80px 80px 80px 1fr; align-items: center; padding: 15px 20px; border-bottom: 1px solid #c5c9d4; transition: background 0.2s; gap: 15px; }
.view-compatta .student-row:hover { background: #f8f9fa; }
.view-compatta .student-row:last-child { border-bottom: none; }
.view-compatta .student-row.header { background: #f0f2f5; font-weight: 700; font-size: 13px; color: #475569; text-transform: uppercase; }
.view-compatta .expand-btn { width: 32px; height: 32px; border: none; background: #f0f2f5; border-radius: 50%; cursor: pointer; font-size: 14px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.view-compatta .expand-btn:hover { background: #475569; color: white; }
.view-compatta .student-name-cell { font-weight: 600; font-size: 15px; }
.view-compatta .student-name-cell .email { font-size: 12px; color: #4a4a4a; font-weight: normal; }
.view-compatta .settore-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #475569; color: white; text-align: center; display: inline-block; margin: 1px 0; }
.view-compatta .settore-secondary { background: #78808d; }
.view-compatta .settore-tertiary { background: #94979d; }
.view-compatta .sector-badges-container { flex-direction: column; align-items: flex-start; }
.view-compatta .week-cell { font-weight: 600; text-align: center; }
.view-compatta .competency-cell { font-weight: 700; font-size: 18px; }
.view-compatta .competency-cell.danger { color: #9b2c2c; }
.view-compatta .competency-cell.success { color: #2d6a4e; }
.view-compatta .status-cell { display: flex; gap: 5px; }
.view-compatta .status-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.view-compatta .status-icon.done { background: #d4edda; color: #155724; }
.view-compatta .status-icon.missing { background: #f8d7da; color: #721c24; }
.view-compatta .actions-cell { display: flex; gap: 8px; justify-content: flex-end; }

/* Row color indicators */
.view-compatta .student-row.color-giallo { border-left: 4px solid #a08210; }
.view-compatta .student-row.color-blu { border-left: 4px solid #2e5a96; }
.view-compatta .student-row.color-verde { border-left: 4px solid #1f6a4c; }
.view-compatta .student-row.color-arancione { border-left: 4px solid #9a5c14; }
.view-compatta .student-row.color-rosso { border-left: 4px solid #9a3030; }
.view-compatta .student-row.color-viola { border-left: 4px solid #5c3a80; }
.view-compatta .student-row.color-grigio { border-left: 4px solid #4e5565; }
.view-compatta .student-row.alert-end6 { background: #f7f2dc; }
.view-compatta .student-row.alert-below { background: #f5eaea; }

/* =============================================
   VISTA STANDARD
   ============================================= */
.view-standard .students-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 20px; }
.view-standard .student-card { background: white; border-radius: 16px; border: 2px solid #b8bcc8; overflow: visible; transition: all 0.3s; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.view-standard .student-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.12); transform: translateY(-2px); }
.view-standard .student-card.alert-border { border-color: #b03838; box-shadow: 0 2px 10px rgba(176, 56, 56, 0.1); }
.view-standard .student-card.end-6-weeks { border-color: #b8960f; box-shadow: 0 2px 10px rgba(160, 130, 30, 0.1); }

.view-standard .student-card-header { padding: 14px 16px; display: flex; justify-content: space-between; align-items: flex-start; cursor: pointer; user-select: none; gap: 8px; }
.view-standard .student-card-header:hover { filter: brightness(0.97); }
.view-standard .student-card-header.giallo { background: #f7f2dc; border-left: 5px solid #a08210; }
.view-standard .student-card-header.blu { background: #e8eff8; border-left: 5px solid #2e5a96; }
.view-standard .student-card-header.verde { background: #e8f3ec; border-left: 5px solid #1f6a4c; }
.view-standard .student-card-header.arancione { background: #f5eddf; border-left: 5px solid #9a5c14; }
.view-standard .student-card-header.rosso { background: #f5eaea; border-left: 5px solid #9a3030; }
.view-standard .student-card-header.viola { background: #f0ebf6; border-left: 5px solid #5c3a80; }
.view-standard .student-card-header.grigio { background: #eeeff1; border-left: 5px solid #4e5565; }

.view-standard .student-info-left { display: flex; align-items: flex-start; gap: 15px; flex: 1; min-width: 0; }
.view-standard .collapse-toggle { width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; transition: transform 0.3s; font-size: 16px; }
.view-standard .student-card.collapsed .collapse-toggle { transform: rotate(-90deg); }
.view-standard .student-name { font-size: 18px; font-weight: 600; color: #333; }
.view-standard .student-email { font-size: 14px; color: #4a4a4a; }
.view-standard .student-email a, .student-email a, .email a { color: inherit; text-decoration: none; }
.view-standard .student-email a:hover, .student-email a:hover, .email a:hover { color: #2563eb; text-decoration: underline; }
.view-standard .student-info-left > div:last-child { flex: 1; min-width: 0; }
.view-standard .student-badges { display: flex; gap: 6px; align-items: center; flex-wrap: nowrap; flex-shrink: 0; white-space: nowrap; }
.view-standard .badge-6-weeks { background: #f5edd0; color: #6d5510; border: 1px solid #b8a040; padding: 4px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; animation: pulse 2s infinite; }
.view-standard .badge-below { background: #f2e4e4; color: #7a2020; border: 1px solid #c48888; padding: 4px 8px; border-radius: 8px; font-size: 11px; font-weight: 600; }

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }

.view-standard .settore-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: #475569; color: white; display: inline-block; margin: 1px 0; }
.view-standard .settore-secondary { background: #78808d; }
.view-standard .settore-tertiary { background: #94979d; }
.view-standard .sector-badges-container { flex-direction: column; align-items: flex-start; }

.view-standard .week-badge { padding: 8px 14px; border-radius: 20px; font-size: 14px; font-weight: 600; }
.view-standard .week-badge.giallo { background: #f7f2dc; color: #6d5510; border: 2px solid #a08210; }
.view-standard .week-badge.blu { background: #e8eff8; color: #244a78; border: 2px solid #2e5a96; }
.view-standard .week-badge.verde { background: #e8f3ec; color: #18583e; border: 2px solid #1f6a4c; }
.view-standard .week-badge.arancione { background: #f5eddf; color: #70440e; border: 2px solid #9a5c14; }
.view-standard .week-badge.rosso { background: #f5eaea; color: #722424; border: 2px solid #9a3030; }
.view-standard .week-badge.viola { background: #f0ebf6; color: #462e68; border: 2px solid #5c3a80; }
.view-standard .week-badge.grigio { background: #eeeff1; color: #3c424c; border: 2px solid #4e5565; }

/* Collapsible Content */
.view-standard .student-card-collapsible { max-height: 2500px; overflow: visible; transition: max-height 0.35s ease-out, opacity 0.25s ease; opacity: 1; }
.view-standard .student-card.collapsed .student-card-collapsible { max-height: 0; opacity: 0; }
.view-standard .student-card-body { padding: 22px; }

/* Progress Section */
.view-standard .progress-section { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 18px; }
.view-standard .progress-item { text-align: center; padding: 15px 10px; background: #f8f9fa; border-radius: 12px; border: 2px solid #c5c9d4; }
.view-standard .progress-item .value { font-size: 26px; font-weight: 700; }
.view-standard .progress-item .label { font-size: 13px; color: #444; margin-top: 6px; font-weight: 500; }
.view-standard .progress-item.competenze { border-left: 4px solid #475569; }
.view-standard .progress-item.competenze .value { color: #334155; }
.view-standard .progress-item.autoval { border-left: 4px solid #3d7a70; }
.view-standard .progress-item.autoval .value { color: #2d6a5e; }
.view-standard .progress-item.lab { border-left: 4px solid #9a6c20; }
.view-standard .progress-item.lab .value { color: #7a5418; }
.view-standard .progress-item.danger { border-left: 4px solid #9a3030; background: #f2e4e4; }
.view-standard .progress-item.danger .value { color: #9b2c2c; }

.view-standard .mini-progress { height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 8px; overflow: hidden; }
.view-standard .mini-progress-fill { height: 100%; border-radius: 4px; }
.view-standard .mini-progress-fill.violet { background: #4f6080; }
.view-standard .mini-progress-fill.teal { background: #3d8a74; }
.view-standard .mini-progress-fill.orange { background: #a07028; }
.view-standard .mini-progress-fill.red { background: #9a3838; }

/* Status Row */
.view-standard .status-row { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.view-standard .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; }
.view-standard .status-badge.done { background: #d8e8dc; color: #1e5a38; }
.view-standard .status-badge.pending { background: #f0e8c8; color: #6d5010; }
.view-standard .status-badge.missing { background: #ecd4d4; color: #6a1a1a; }
.view-standard .status-badge.end-path { background: #fff3cd; color: #856404; }

/* Timeline 6 Settimane */
.view-standard .timeline-section { margin: 20px 0; padding: 18px; background: #f8f9fb; border-radius: 12px; border: 1px solid #d8dce4; }
.view-standard .timeline-title { font-size: 15px; font-weight: 600; color: #475569; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
.view-standard .timeline-weeks { display: flex; gap: 10px; flex-wrap: wrap; }
.view-standard .timeline-week { flex: 1; min-width: 80px; padding: 12px; background: white; border-radius: 10px; text-align: center; border: 2px solid #c0c5d0; transition: all 0.2s; }
.view-standard .timeline-week.completed { background: #e4f0e8; border-color: #5a8a68; }
.view-standard .timeline-week.current { background: #f5edd0; border-color: #a09030; box-shadow: 0 2px 8px rgba(140, 120, 40, 0.18); }
.view-standard .timeline-week.future { background: #f8f9fa; border-color: #dee2e6; opacity: 0.7; }
.view-standard .timeline-week .week-num { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
.view-standard .timeline-week.completed .week-num { color: #2d6a4e; }
.view-standard .timeline-week.current .week-num { color: #6d5010; }
.view-standard .timeline-week.future .week-num { color: #6c757d; }
.view-standard .timeline-week .week-icon { font-size: 20px; margin-bottom: 6px; }
.view-standard .timeline-week .week-detail { font-size: 11px; color: #4a4a4a; }

/* Notes Section */
.view-standard .notes-section { margin-top: 18px; padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #c5c9d4; }
.view-standard .notes-title { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.view-standard .notes-textarea { width: 100%; min-height: 80px; padding: 12px; border: 2px solid #b0b5c0; border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit; color: #333; }
.view-standard .notes-textarea:focus { outline: none; border-color: #475569; box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.15); }
.view-standard .notes-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
.view-standard .notes-meta { font-size: 12px; color: #555; }

/* End Path Box */
.view-standard .end-path-box { background: #f5edd0; padding: 18px; border-radius: 12px; border: 1px solid #b8a040; margin-top: 18px; }
.view-standard .end-path-box h4 { color: #6d5010; font-size: 15px; margin: 0 0 10px 0; }
.view-standard .end-path-box p { font-size: 14px; color: #6d5010; margin: 0; }

/* Quick Actions */
.quick-actions { display: flex !important; gap: 8px; padding: 12px 18px; background: #f8f9fa; border-bottom: 1px solid #e2e5ea; border-top: 1px solid #e8eaf0; flex-wrap: wrap; align-items: center; }

.quick-btn {
    display: inline-flex !important; align-items: center; gap: 5px; padding: 8px 14px; border-radius: 8px;
    font-size: 13px; font-weight: 600; text-decoration: none !important; transition: all 0.2s; cursor: pointer;
    border: 1px solid #c0c5d0; opacity: 1 !important; visibility: visible !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06); background: #f8f9fa !important; color: #334155 !important;
}

.quick-btn.report, .quick-btn.eval, .quick-btn.qb-profile, .quick-btn.word, .quick-btn.colloquio { background: #f8f9fa !important; color: #334155 !important; }
.quick-btn.cpurc { background: #475569 !important; color: #fff !important; border-color: #475569 !important; }
.quick-btn.cpurc:hover { background: #334155 !important; color: #fff !important; }
.quick-btn.cpurc:visited, .quick-btn.cpurc:focus, .quick-btn.cpurc:active { color: #fff !important; }
.quick-btn:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,0.12); text-decoration: none !important; background: #e8eaed !important; color: #1e293b !important; }
.quick-btn:visited, .quick-btn:focus, .quick-btn:active { color: #334155 !important; text-decoration: none !important; }

/* Card Footer */
.view-standard .student-card-footer { display: none; }

/* =============================================
   VISTA DETTAGLIATA
   ============================================= */
.view-dettagliata .students-list { display: flex; flex-direction: column; gap: 25px; }
.view-dettagliata .student-panel { background: white; border-radius: 16px; border: 2px solid #b8bcc8; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.view-dettagliata .student-panel.alert-border { border-color: #b03838; }
.view-dettagliata .student-panel.end-6-weeks { border-color: #b8960f; }

.view-dettagliata .panel-header { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
.view-dettagliata .panel-header:hover { filter: brightness(0.97); }
.view-dettagliata .panel-collapse-toggle { width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; transition: transform 0.3s; font-size: 16px; margin-right: 15px; flex-shrink: 0; }
.view-dettagliata .student-panel.collapsed .panel-collapse-toggle { transform: rotate(-90deg); }
.view-dettagliata .panel-collapsible { max-height: 3000px; overflow: hidden; transition: max-height 0.35s ease-out, opacity 0.25s ease; opacity: 1; }
.view-dettagliata .student-panel.collapsed .panel-collapsible { max-height: 0; opacity: 0; }

.view-dettagliata .panel-header.giallo { background: #f7f2dc; border-left: 5px solid #a08210; }
.view-dettagliata .panel-header.blu { background: #e8eff8; border-left: 5px solid #2e5a96; }
.view-dettagliata .panel-header.verde { background: #e8f3ec; border-left: 5px solid #1f6a4c; }
.view-dettagliata .panel-header.arancione { background: #f5eddf; border-left: 5px solid #9a5c14; }
.view-dettagliata .panel-header.rosso { background: #f5eaea; border-left: 5px solid #9a3030; }
.view-dettagliata .panel-header.viola { background: #f0ebf6; border-left: 5px solid #5c3a80; }
.view-dettagliata .panel-header.grigio { background: #eeeff1; border-left: 5px solid #4e5565; }

.view-dettagliata .student-main-info { display: flex; align-items: center; gap: 20px; }
.view-dettagliata .student-avatar { width: 60px; height: 60px; border-radius: 50%; background: rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; color: #333; }
.view-dettagliata .student-name-block .name { font-size: 22px; font-weight: 700; color: #333; }
.view-dettagliata .student-name-block .email { font-size: 15px; color: #4a4a4a; }
.view-dettagliata .student-badges { display: flex; gap: 12px; align-items: center; }
.view-dettagliata .badge-6-weeks { background: #f5edd0; color: #6d5510; border: 1px solid #b8a040; padding: 8px 16px; border-radius: 12px; font-size: 14px; font-weight: 700; }
.view-dettagliata .settore-badge { padding: 5px 12px; border-radius: 15px; font-size: 13px; font-weight: 600; background: #475569; color: white; display: inline-block; margin: 2px 0; }
.view-dettagliata .settore-secondary { background: #78808d; }
.view-dettagliata .settore-tertiary { background: #94979d; }
.view-dettagliata .sector-badges-container { flex-direction: column; align-items: flex-start; }
.view-dettagliata .week-badge { padding: 10px 18px; border-radius: 25px; font-size: 16px; font-weight: 700; }
.view-dettagliata .week-badge.giallo { background: #f7f2dc; color: #6d5510; border: 2px solid #a08210; }
.view-dettagliata .week-badge.blu { background: #e8eff8; color: #244a78; border: 2px solid #2e5a96; }
.view-dettagliata .week-badge.verde { background: #e8f3ec; color: #18583e; border: 2px solid #1f6a4c; }

.view-dettagliata .panel-body { padding: 25px; }
.view-dettagliata .panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
.view-dettagliata .left-column { display: flex; flex-direction: column; gap: 20px; }
.view-dettagliata .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
.view-dettagliata .stat-box { text-align: center; padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #b8bcc8; }
.view-dettagliata .stat-box .value { font-size: 30px; font-weight: 700; }
.view-dettagliata .stat-box .label { font-size: 14px; color: #555; margin-top: 8px; }
.view-dettagliata .stat-box.competenze { border-left: 4px solid #475569; }
.view-dettagliata .stat-box.competenze .value { color: #334155; }
.view-dettagliata .stat-box.autoval { border-left: 4px solid #3d7a70; }
.view-dettagliata .stat-box.autoval .value { color: #2d6a5e; }
.view-dettagliata .stat-box.lab { border-left: 4px solid #9a6c20; }
.view-dettagliata .stat-box.lab .value { color: #7a5418; }
.view-dettagliata .stat-box.danger { border-left: 4px solid #9a3030; background: #f2e4e4; }
.view-dettagliata .stat-box.danger .value { color: #9b2c2c; }
.view-dettagliata .right-column { display: flex; flex-direction: column; gap: 20px; }
.view-dettagliata .notes-box { padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #b8bcc8; flex: 1; }
.view-dettagliata .notes-box h4 { font-size: 15px; font-weight: 600; margin: 0 0 12px 0; color: #333; }
.view-dettagliata .notes-textarea { width: 100%; min-height: 100px; padding: 12px; border: 2px solid #b8bcc8; border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit; }
.view-dettagliata .notes-textarea:focus { outline: none; border-color: #475569; }
.view-dettagliata .panel-footer { padding: 20px 25px; background: #f8f9fa; display: flex; gap: 15px; border-top: 1px solid #e0e0e0; flex-wrap: wrap; }

/* =============================================
   VISTA CLASSICA
   ============================================= */
.view-classica .students-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }

/* Sector chips */
.sector-chips-wrap { display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; position: relative; }
.sector-chip { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 12px; font-size: 0.8em; font-weight: 600; background: #475569; color: #fff; white-space: nowrap; transition: all 0.2s; }
.sector-chip .chip-remove { display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; border: none; background: rgba(255,255,255,0.25); color: #fff; border-radius: 50%; font-size: 10px; line-height: 1; cursor: pointer; padding: 0; margin-left: 2px; transition: background 0.15s; }
.sector-chip .chip-remove:hover { background: rgba(255,255,255,0.5); }
.sector-chip.saving { opacity: 0.5; pointer-events: none; }
.sector-add-wrap { position: relative; display: inline-flex; }
.sector-add-btn { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border: 1.5px dashed #94a3b8; background: transparent; color: #64748b; border-radius: 50%; font-size: 14px; font-weight: 700; line-height: 1; cursor: pointer; padding: 0; transition: all 0.2s; }
.sector-add-btn:hover { border-color: #475569; color: #475569; background: #f1f5f9; }
.sector-dropdown { display: none; position: absolute; top: 100%; left: 0; z-index: 1000; min-width: 170px; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.15); margin-top: 4px; }
.sector-dropdown.open { display: block; }
.sector-dropdown > div { padding: 7px 12px; font-size: 0.82em; font-weight: 500; color: #334155; cursor: pointer; transition: background 0.1s; }
.sector-dropdown > div:hover:not(.already-assigned) { background: #f1f5f9; }
.sector-dropdown > div.already-assigned { color: #c0c5ce; cursor: default; text-decoration: line-through; }

.view-compatta .sector-chip { font-size: 0.75em; padding: 2px 7px; }
.view-compatta .sector-add-btn { width: 20px; height: 20px; font-size: 14px; }
.view-compatta .sector-chips-wrap { flex-direction: row; gap: 3px; }

.student-sectors-row { margin-top: 4px; display: flex; flex-direction: row; align-items: center; max-width: 100%; }
.student-sectors-row .sector-chips-wrap { display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; align-items: center !important; gap: 5px !important; max-width: 100% !important; }
.student-sectors-row .sector-chip { display: inline-flex !important; font-size: 0.82em !important; padding: 3px 8px !important; border-radius: 12px !important; }
.student-sectors-row .sector-add-wrap { display: inline-flex !important; }

/* Responsive */
@media (max-width: 1200px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
    .view-dettagliata .panel-grid { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .view-standard .students-grid, .view-classica .students-grid { grid-template-columns: 1fr; }
    .view-compatta .student-row { grid-template-columns: 40px 1fr 80px; }
    .view-compatta .student-row .settore-badge, .view-compatta .student-row .status-cell { display: none; }
    .view-controls { flex-direction: column; align-items: stretch; }
    .view-selector { justify-content: center; }
    .zoom-controls { justify-content: center; }
}

/* Week Planner - Clickable timeline */
.view-standard .timeline-week, .view-dettagliata .timeline-week { cursor: pointer; }
.view-standard .timeline-week:hover, .view-dettagliata .timeline-week:hover { transform: scale(1.05); box-shadow: 0 3px 12px rgba(0,0,0,0.15); z-index: 2; }
.week-planner-row { display: flex; gap: 4px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e4e8; }
.week-planner-btn { flex: 1; padding: 4px 2px; font-size: 11px; font-weight: 600; border: 1px solid #c0c5d0; border-radius: 5px; background: #f8f9fa; cursor: pointer; text-align: center; transition: all 0.2s; color: #555; }
.week-planner-btn:hover { background: #e8ecf0; border-color: #475569; color: #333; }
.week-planner-btn.completed { background: #e4f0e8; border-color: #5a8a68; color: #2d6a4e; }
.week-planner-btn.current { background: #f5edd0; border-color: #a09030; color: #6d5010; }
.week-planner-mini { display: inline-flex; gap: 3px; margin-left: 6px; }
.week-planner-mini .wp-mini-btn { width: 18px; height: 18px; font-size: 9px; font-weight: 700; border: 1px solid #c0c5d0; border-radius: 4px; background: #f0f2f4; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0; line-height: 1; color: #666; transition: all 0.15s; }
.week-planner-mini .wp-mini-btn:hover { background: #dde0e4; border-color: #475569; transform: scale(1.15); }
.week-planner-mini .wp-mini-btn.completed { background: #d4edda; border-color: #5a8a68; color: #2d6a4e; }
.week-planner-mini .wp-mini-btn.current { background: #f5edd0; border-color: #a09030; color: #6d5010; }
.wp-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; justify-content: center; align-items: flex-start; padding-top: 50px; overflow-y: auto; }
.wp-modal.active { display: flex; }
.wp-modal-content { background: white; border-radius: 14px; width: 95%; max-width: 600px; box-shadow: 0 10px 50px rgba(0,0,0,0.25); margin-bottom: 50px; }
.wp-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 2px solid #e2e4e8; background: #f8f9fb; border-radius: 14px 14px 0 0; }
.wp-modal-header h3 { margin: 0; font-size: 17px; color: #333; }
.wp-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 0 4px; line-height: 1; }
.wp-modal-close:hover { color: #dc3545; }
.wp-modal-body { padding: 20px 24px; }
.wp-current-section { margin-bottom: 20px; }
.wp-current-section h4 { font-size: 14px; color: #475569; margin: 0 0 10px; font-weight: 600; }
.wp-activity-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border: 1px solid #d8dce4; border-radius: 8px; margin-bottom: 6px; background: #fafbfc; }
.wp-activity-item .wp-act-info { flex: 1; }
.wp-activity-item .wp-act-name { font-weight: 600; font-size: 13px; color: #333; }
.wp-activity-item .wp-act-detail { font-size: 11px; color: #666; margin-top: 2px; }
.wp-activity-item .wp-act-type { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; margin-right: 8px; }
.wp-act-type.atelier { background: #dbeafe; color: #1e40af; }
.wp-act-type.test { background: #fef3c7; color: #92400e; }
.wp-act-type.lab { background: #d1fae5; color: #065f46; }
.wp-act-type.external { background: #ede9fe; color: #5b21b6; }
.wp-remove-btn { background: none; border: 1px solid #e5e7eb; border-radius: 6px; padding: 4px 10px; font-size: 12px; cursor: pointer; color: #dc3545; transition: all 0.15s; }
.wp-remove-btn:hover { background: #fee2e2; border-color: #dc3545; }
.wp-no-activities { text-align: center; padding: 15px; color: #999; font-style: italic; font-size: 13px; }
.wp-add-section { border-top: 2px solid #e2e4e8; padding-top: 18px; }
.wp-add-section h4 { font-size: 14px; color: #475569; margin: 0 0 14px; font-weight: 600; }
.wp-add-group { margin-bottom: 14px; padding: 14px; border: 1px solid #e2e4e8; border-radius: 10px; background: #fafbfc; }
.wp-add-group-title { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.wp-add-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.wp-add-row select, .wp-add-row input { flex: 1; min-width: 120px; padding: 8px 10px; border: 1px solid #c0c5d0; border-radius: 6px; font-size: 13px; color: #333; }
.wp-add-row select:focus, .wp-add-row input:focus { border-color: #0066cc; outline: none; box-shadow: 0 0 0 2px rgba(0,102,204,0.15); }
.wp-assign-btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.wp-assign-btn.atelier { background: #0066cc; color: white; }
.wp-assign-btn.atelier:hover { background: #0052a3; }
.wp-assign-btn.test { background: #d97706; color: white; }
.wp-assign-btn.test:hover { background: #b45309; }
.wp-assign-btn.lab { background: #059669; color: white; }
.wp-assign-btn.lab:hover { background: #047857; }
.wp-assign-btn.external { background: #7c3aed; color: white; }
.wp-assign-btn.external:hover { background: #6d28d9; }
.wp-atelier-dates { display: none; margin-top: 8px; }
.wp-atelier-dates.visible { display: block; }
</style>

<div class="coach-dashboard-v2 zoom-<?php echo $zoom; ?>">

    <!-- Course Breadcrumb -->
    <div class="course-breadcrumb">
        &#128218;
        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>">
            <?php echo format_string($course->fullname); ?>
        </a>
        &raquo;
        <span><?php echo get_string('course_students', 'local_coachmanager'); ?></span>
    </div>

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
            <h2><?php echo get_string('course_students', 'local_coachmanager'); ?></h2>
            <span class="student-count"><?php echo count($students); ?> <?php echo get_string('all_enrolled_students', 'local_coachmanager'); ?></span>
        </div>
        <div class="header-actions">
            <button class="btn btn-info" onclick="location.href='<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>'" title="Torna al corso">
                &#8592; Torna al Corso
            </button>
            <button class="btn btn-primary" onclick="location.href='coach_dashboard_v2.php'">
                &#128100; <?php echo get_string('coach_dashboard', 'local_coachmanager'); ?>
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section collapsed" id="filtersSection">
        <div class="filters-header" onclick="toggleFilters()">
            <h4>
                <span class="filter-icon">&#9776;</span>
                <?php echo get_string('advanced_filters', 'local_coachmanager'); ?>
            </h4>
            <div class="filters-toggle">&#9660;</div>
        </div>
        <div class="filters-body">
            <form method="get" action="" id="filterForm">
                <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                <input type="hidden" name="view" value="<?php echo s($view); ?>">
                <input type="hidden" name="zoom" value="<?php echo $zoom; ?>">

                <table style="width: 100%; border: none; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; vertical-align: top; width: 33%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('group_color', 'local_coachmanager'); ?></label>
                        <div class="color-chips" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php
                            $colors = ['giallo' => '#c4a830', 'blu' => '#3668a8', 'verde' => '#3d7a60',
                                       'arancione' => '#b06a1a', 'rosso' => '#b03838', 'viola' => '#6b4590', 'grigio' => '#6a7080'];
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
                    <td style="padding: 10px; vertical-align: top; width: 33%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('week', 'local_coachmanager'); ?></label>
                        <select name="week" onchange="this.form.submit()" style="width: 100%; padding: 12px; border: 2px solid #a0a8c0; border-radius: 8px; font-size: 15px; color: #333;">
                            <option value=""><?php echo get_string('all_weeks', 'local_coachmanager'); ?></option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $weekfilter == $i ? 'selected' : ''; ?>>
                                Settimana <?php echo $i; ?>
                                <?php echo $i == 6 ? ' (Fine)' : ''; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </td>
                    <td style="padding: 10px; vertical-align: top; width: 33%;">
                        <label style="display: block; font-weight: 600; color: #555; margin-bottom: 8px;"><?php echo get_string('status', 'local_coachmanager'); ?></label>
                        <select name="status" onchange="this.form.submit()" style="width: 100%; padding: 12px; border: 2px solid #a0a8c0; border-radius: 8px; font-size: 15px; color: #333;">
                            <option value=""><?php echo get_string('all_statuses', 'local_coachmanager'); ?></option>
                            <option value="end6" <?php echo $statusfilter == 'end6' ? 'selected' : ''; ?>>Fine 6 Settimane</option>
                            <option value="below50" <?php echo $statusfilter == 'below50' ? 'selected' : ''; ?>>Sotto Soglia 50%</option>
                            <option value="no_autoval" <?php echo $statusfilter == 'no_autoval' ? 'selected' : ''; ?>>Manca Autovalutazione</option>
                            <option value="no_lab" <?php echo $statusfilter == 'no_lab' ? 'selected' : ''; ?>>Manca Laboratorio</option>
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
            <div class="stat-label"><?php echo get_string('all_enrolled_students', 'local_coachmanager'); ?></div>
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
                onclick="location.href='?courseid=<?php echo $courseid; ?>&view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>'">
            Tutti (<?php echo $stats['total_students']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_autoval' ? 'active' : ''; ?>"
                onclick="location.href='?courseid=<?php echo $courseid; ?>&view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=no_autoval'">
            Manca Autoval (<?php echo $stats['missing_autoval']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'no_lab' ? 'active' : ''; ?>"
                onclick="location.href='?courseid=<?php echo $courseid; ?>&view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=no_lab'">
            Manca Lab (<?php echo $stats['missing_lab']; ?>)
        </button>
        <button class="quick-filter <?php echo $statusfilter == 'below50' ? 'active' : ''; ?>"
                onclick="location.href='?courseid=<?php echo $courseid; ?>&view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=below50'">
            Sotto Soglia (<?php echo $stats['below_threshold']; ?>)
        </button>
        <button class="quick-filter end6 <?php echo $statusfilter == 'end6' ? 'active' : ''; ?>"
                onclick="location.href='?courseid=<?php echo $courseid; ?>&view=<?php echo $view; ?>&zoom=<?php echo $zoom; ?>&status=end6'">
            Fine 6 Sett. (<?php echo $stats['end_6_weeks']; ?>)
        </button>
    </div>

    <!-- Students Container - Different views -->
    <?php
    switch ($view) {
        case 'compatta':
            cs_render_view_compatta($students, $dashboard);
            break;
        case 'standard':
            cs_render_view_standard($students, $dashboard);
            break;
        case 'dettagliata':
            cs_render_view_dettagliata($students, $dashboard);
            break;
        case 'classica':
        default:
            cs_render_view_classica($students, $dashboard);
            break;
    }
    ?>

</div>

<script>
// Open email in classic Outlook 365 desktop app
function openOutlook(email) {
    var emlContent = 'To: ' + email + '\r\n'
        + 'Subject: \r\n'
        + 'X-Unsent: 1\r\n'
        + 'Content-Type: text/plain; charset=utf-8\r\n'
        + '\r\n';
    var blob = new Blob([emlContent], { type: 'message/rfc822' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'email.eml';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
}

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

// Toggle card (for standard/classic view)
function toggleCard(cardId) {
    document.getElementById(cardId).classList.toggle('collapsed');
}

// Toggle panel (for detailed view)
function togglePanel(panelId) {
    document.getElementById(panelId).classList.toggle('collapsed');
}

// Expand/Collapse all
function expandAllCards() {
    document.querySelectorAll('.student-card, .student-panel').forEach(el => {
        el.classList.remove('collapsed');
    });
}

function collapseAllCards() {
    document.querySelectorAll('.student-card, .student-panel').forEach(el => {
        el.classList.add('collapsed');
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
            const btn = document.querySelector('#save-notes-btn-' + studentId);
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '&#10004; Salvato!';
                btn.style.background = '#3d7a60';
                setTimeout(() => { btn.innerHTML = originalText; btn.style.background = ''; }, 2000);
            }
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => { alert('Errore di connessione'); });
}

// Export Word
function exportWord(studentId) {
    window.location.href = 'export_word.php?studentid=' + studentId + '&sesskey=<?php echo sesskey(); ?>';
}

// ============================================
// SECTOR MULTI-SELECT CHIP FUNCTIONS
// ============================================

const SECTOR_LABELS = <?php echo json_encode($ALL_SECTORS); ?>;
const MEDALS = ['&#129351;', '&#129352;', '&#129353;'];

function getStudentSectors(studentId) {
    const wrap = document.getElementById('sector-wrap-' + studentId);
    if (!wrap) return [];
    try { return JSON.parse(wrap.dataset.sectors || '[]'); } catch(e) { return []; }
}

function saveSectors(studentId, sectors) {
    const wrap = document.getElementById('sector-wrap-' + studentId);
    if (!wrap) return;
    wrap.dataset.sectors = JSON.stringify(sectors);
    wrap.querySelectorAll('.sector-chip').forEach(c => c.classList.add('saving'));

    fetch('ajax_save_sector.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'userid=' + studentId + '&sectors=' + encodeURIComponent(sectors.join(',')) + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { rebuildChips(studentId, data.sectors); }
        else { alert('Errore: ' + (data.message || 'Salvataggio fallito')); location.reload(); }
    })
    .catch(() => { alert('Errore di connessione'); location.reload(); });
}

function rebuildChips(studentId, sectors) {
    const wrap = document.getElementById('sector-wrap-' + studentId);
    if (!wrap) return;
    wrap.dataset.sectors = JSON.stringify(sectors);
    let html = '';
    sectors.forEach((sec, idx) => {
        const medal = MEDALS[idx] || '';
        html += '<span class="sector-chip" data-sector="' + sec + '" style="display:inline-flex; white-space:nowrap;">';
        html += medal + ' ' + sec;
        html += '<button type="button" class="chip-remove" onclick="removeSectorChip(' + studentId + ', \'' + sec + '\')" title="Rimuovi">&times;</button>';
        html += '</span>';
    });
    if (sectors.length < 3) {
        html += '<div class="sector-add-wrap" style="display:inline-flex;">';
        html += '<button type="button" class="sector-add-btn" onclick="toggleSectorDropdown(' + studentId + ')" title="Aggiungi settore">+</button>';
        html += '<div class="sector-dropdown" id="sector-dd-' + studentId + '">';
        for (const [key, label] of Object.entries(SECTOR_LABELS)) {
            const assigned = sectors.includes(key);
            if (assigned) { html += '<div class="already-assigned" data-value="' + key + '" disabled>' + label + '</div>'; }
            else { html += '<div data-value="' + key + '" onclick="addSectorChip(' + studentId + ', \'' + key + '\')">' + label + '</div>'; }
        }
        html += '</div></div>';
    }
    wrap.innerHTML = html;
}

function addSectorChip(studentId, sector) {
    const sectors = getStudentSectors(studentId);
    if (sectors.length >= 3 || sectors.includes(sector)) return;
    sectors.push(sector);
    closeSectorDropdowns();
    saveSectors(studentId, sectors);
}

function removeSectorChip(studentId, sector) {
    let sectors = getStudentSectors(studentId);
    sectors = sectors.filter(s => s !== sector);
    if (sectors.length === 0) {
        if (!confirm('Rimuovere tutti i settori? Lo studente restera senza settore assegnato.')) return;
    }
    saveSectors(studentId, sectors.length > 0 ? sectors : [sector]);
}

function toggleSectorDropdown(studentId) {
    closeSectorDropdowns();
    const dd = document.getElementById('sector-dd-' + studentId);
    if (dd) dd.classList.toggle('open');
}

function closeSectorDropdowns() {
    document.querySelectorAll('.sector-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.sector-add-wrap')) { closeSectorDropdowns(); }
});

// ============================================
// WEEK PLANNER FUNCTIONS
// ============================================

let wpStudentId = null;
let wpWeek = null;
let wpStudentName = '';

function openWeekPlanner(studentId, week, studentName) {
    wpStudentId = studentId;
    wpWeek = week;
    wpStudentName = studentName;

    let modal = document.getElementById('wpModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'wpModal';
        modal.className = 'wp-modal';
        modal.innerHTML = `
            <div class="wp-modal-content">
                <div class="wp-modal-header">
                    <h3 id="wpTitle">Pianificazione Settimana</h3>
                    <button class="wp-modal-close" onclick="closeWeekPlanner()">&times;</button>
                </div>
                <div class="wp-modal-body">
                    <div class="wp-current-section">
                        <h4>&#128203; Attivit&agrave; Attuali</h4>
                        <div id="wpCurrentActivities">
                            <div style="text-align:center;padding:20px;color:#999;">Caricamento...</div>
                        </div>
                    </div>
                    <div class="wp-add-section">
                        <h4>&#10133; Aggiungi Attivit&agrave;</h4>
                        <div class="wp-add-group">
                            <div class="wp-add-group-title">Atelier</div>
                            <div class="wp-add-row">
                                <select id="wpAtelierSelect" onchange="wpLoadAtelierDates()">
                                    <option value="">-- Seleziona Atelier --</option>
                                </select>
                                <button class="wp-assign-btn atelier" onclick="wpAssignAtelier()" id="wpAtelierBtn" disabled>Iscrivi</button>
                            </div>
                            <div id="wpAtelierDates" class="wp-atelier-dates"></div>
                        </div>
                        <div class="wp-add-group">
                            <div class="wp-add-group-title">Test Teoria</div>
                            <div class="wp-add-row">
                                <select id="wpTestSelect"><option value="">-- Seleziona Test --</option></select>
                                <button class="wp-assign-btn test" onclick="wpAssignActivity('test_teoria')">Assegna</button>
                            </div>
                        </div>
                        <div class="wp-add-group">
                            <div class="wp-add-group-title">Laboratorio</div>
                            <div class="wp-add-row">
                                <select id="wpLabSelect"><option value="">-- Seleziona Lab --</option></select>
                                <button class="wp-assign-btn lab" onclick="wpAssignActivity('laboratorio')">Assegna</button>
                            </div>
                        </div>
                        <div class="wp-add-group">
                            <div class="wp-add-group-title">Attivit&agrave; Esterna</div>
                            <div class="wp-add-row">
                                <input type="text" id="wpExternalName" placeholder="Nome attivit&agrave;...">
                                <button class="wp-assign-btn external" onclick="wpAddExternal()">Aggiungi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeWeekPlanner();
        });
    }

    document.getElementById('wpTitle').textContent = 'Settimana ' + week + ' - ' + studentName;
    modal.classList.add('active');
    wpLoadPlan();
}

function closeWeekPlanner() {
    const modal = document.getElementById('wpModal');
    if (modal) modal.classList.remove('active');
    wpStudentId = null;
    wpWeek = null;
}

function wpLoadPlan() {
    const url = 'ajax_week_planner.php?action=getplan&studentid=' + wpStudentId +
                '&week=' + wpWeek + '&sesskey=<?php echo sesskey(); ?>';

    fetch(url)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            wpRenderCurrentActivities(data.plan);
            wpPopulateDropdowns(data.available);
        } else {
            document.getElementById('wpCurrentActivities').innerHTML =
                '<div class="wp-no-activities">Errore: ' + (data.message || 'Caricamento fallito') + '</div>';
        }
    })
    .catch(() => {
        document.getElementById('wpCurrentActivities').innerHTML =
            '<div class="wp-no-activities">Errore di connessione</div>';
    });
}

function wpRenderCurrentActivities(plan) {
    const container = document.getElementById('wpCurrentActivities');
    let html = '';
    let hasActivities = false;

    if (plan.ateliers && plan.ateliers.length > 0) {
        hasActivities = true;
        plan.ateliers.forEach(a => {
            const canRemove = a.status !== 'attended';
            html += `<div class="wp-activity-item">
                <div class="wp-act-info">
                    <span class="wp-act-type atelier">ATELIER</span>
                    <span class="wp-act-name">${a.name}</span>
                    <div class="wp-act-detail">${a.date ? a.date + ' ' + a.time : ''} ${a.room ? '- ' + a.room : ''} ${a.status === 'attended' ? '(Frequentato)' : ''}</div>
                </div>
                ${canRemove ? '<button class="wp-remove-btn" onclick="wpRemoveActivity(\'atelier\',' + a.id + ')">&#10005;</button>' : '<span style="color:#2d6a4e;font-size:13px;">&#10004;</span>'}
            </div>`;
        });
    }
    if (plan.tests && plan.tests.length > 0) {
        hasActivities = true;
        plan.tests.forEach(a => {
            html += `<div class="wp-activity-item">
                <div class="wp-act-info"><span class="wp-act-type test">TEST</span><span class="wp-act-name">${a.name}</span><div class="wp-act-detail">${a.details || ''}</div></div>
                <button class="wp-remove-btn" onclick="wpRemoveActivity('test_teoria',${a.id})">&#10005;</button>
            </div>`;
        });
    }
    if (plan.labs && plan.labs.length > 0) {
        hasActivities = true;
        plan.labs.forEach(a => {
            html += `<div class="wp-activity-item">
                <div class="wp-act-info"><span class="wp-act-type lab">LAB</span><span class="wp-act-name">${a.name}</span><div class="wp-act-detail">${a.details || ''}</div></div>
                <button class="wp-remove-btn" onclick="wpRemoveActivity('laboratorio',${a.id})">&#10005;</button>
            </div>`;
        });
    }
    if (plan.external && plan.external.length > 0) {
        hasActivities = true;
        plan.external.forEach(a => {
            html += `<div class="wp-activity-item">
                <div class="wp-act-info"><span class="wp-act-type external">ESTERNA</span><span class="wp-act-name">${a.name}</span><div class="wp-act-detail">${a.details || ''}</div></div>
                <button class="wp-remove-btn" onclick="wpRemoveActivity('external',${a.id})">&#10005;</button>
            </div>`;
        });
    }

    if (!hasActivities) {
        html = '<div class="wp-no-activities">Nessuna attivit&agrave; assegnata per questa settimana</div>';
    }
    container.innerHTML = html;
}

function wpPopulateDropdowns(available) {
    const atelierSel = document.getElementById('wpAtelierSelect');
    atelierSel.innerHTML = '<option value="">-- Seleziona Atelier --</option>';
    if (available.ateliers) {
        available.ateliers.forEach(a => { atelierSel.innerHTML += '<option value="' + a.id + '">' + a.name + '</option>'; });
    }
    const testSel = document.getElementById('wpTestSelect');
    testSel.innerHTML = '<option value="">-- Seleziona Test --</option>';
    if (available.tests) {
        available.tests.forEach(t => { testSel.innerHTML += '<option value="' + t.id + '" data-name="' + t.name.replace(/"/g, '&quot;') + '">' + t.name + '</option>'; });
    }
    const labSel = document.getElementById('wpLabSelect');
    labSel.innerHTML = '<option value="">-- Seleziona Lab --</option>';
    if (available.labs) {
        available.labs.forEach(l => { labSel.innerHTML += '<option value="' + l.id + '" data-name="' + l.name.replace(/"/g, '&quot;') + '">' + l.name + '</option>'; });
    }
}

function wpLoadAtelierDates() {
    const atelierId = document.getElementById('wpAtelierSelect').value;
    const datesDiv = document.getElementById('wpAtelierDates');
    const btn = document.getElementById('wpAtelierBtn');
    if (!atelierId) { datesDiv.classList.remove('visible'); datesDiv.innerHTML = ''; btn.disabled = true; return; }
    datesDiv.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">Caricamento date...</div>';
    datesDiv.classList.add('visible');

    fetch('ajax_week_planner.php?action=getatelierdates&atelierid=' + atelierId + '&sesskey=<?php echo sesskey(); ?>')
    .then(r => r.json())
    .then(data => {
        if (data.success && data.dates && data.dates.length > 0) {
            let html = '<div style="display:flex;flex-direction:column;gap:4px;margin-top:8px;">';
            data.dates.forEach(d => {
                const full = d.is_full;
                html += `<label style="display:flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid ${full ? '#e5e7eb' : '#c0c5d0'};border-radius:6px;cursor:${full ? 'not-allowed' : 'pointer'};background:${full ? '#f3f4f6' : 'white'};opacity:${full ? '0.6' : '1'};">
                    <input type="radio" name="wpAtelierDate" value="${d.activity_id}" ${full ? 'disabled' : ''} onchange="document.getElementById('wpAtelierBtn').disabled=false">
                    <span style="flex:1;font-size:12px;"><strong>${d.date_formatted}</strong> ${d.time_formatted} ${d.room ? '- ' + d.room : ''}</span>
                    <span style="font-size:11px;color:${full ? '#dc3545' : '#2d6a4e'};">${full ? 'PIENO' : d.available + ' posti'}</span>
                </label>`;
            });
            html += '</div>';
            datesDiv.innerHTML = html;
            btn.disabled = true;
        } else {
            datesDiv.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">Nessuna data disponibile</div>';
            btn.disabled = true;
        }
    })
    .catch(() => { datesDiv.innerHTML = '<div style="padding:10px;color:#dc3545;font-size:12px;">Errore caricamento date</div>'; });
}

function wpAssignAtelier() {
    const radio = document.querySelector('input[name="wpAtelierDate"]:checked');
    if (!radio) { alert('Seleziona una data'); return; }
    const formData = new URLSearchParams();
    formData.append('action', 'assignatelier');
    formData.append('studentid', wpStudentId);
    formData.append('activityid', radio.value);
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    fetch('ajax_week_planner.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { wpLoadPlan(); document.getElementById('wpAtelierSelect').value = ''; document.getElementById('wpAtelierDates').classList.remove('visible'); document.getElementById('wpAtelierBtn').disabled = true; }
        else { alert(data.message || 'Errore'); }
    })
    .catch(() => alert('Errore di connessione'));
}

function wpAssignActivity(type) {
    const selId = type === 'test_teoria' ? 'wpTestSelect' : 'wpLabSelect';
    const sel = document.getElementById(selId);
    if (!sel.value) { alert('Seleziona un\'attivit\u00e0'); return; }
    const name = sel.options[sel.selectedIndex].getAttribute('data-name') || sel.options[sel.selectedIndex].textContent;
    const formData = new URLSearchParams();
    formData.append('action', 'assignactivity');
    formData.append('studentid', wpStudentId);
    formData.append('week', wpWeek);
    formData.append('type', type);
    formData.append('activityname', name);
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    fetch('ajax_week_planner.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (data.success) { wpLoadPlan(); sel.value = ''; } else { alert(data.message || 'Errore'); } })
    .catch(() => alert('Errore di connessione'));
}

function wpAddExternal() {
    const nameInput = document.getElementById('wpExternalName');
    const name = nameInput.value.trim();
    if (!name) { alert('Inserisci il nome dell\'attivit\u00e0'); return; }
    const formData = new URLSearchParams();
    formData.append('action', 'assignactivity');
    formData.append('studentid', wpStudentId);
    formData.append('week', wpWeek);
    formData.append('type', 'external');
    formData.append('activityname', name);
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    fetch('ajax_week_planner.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (data.success) { wpLoadPlan(); nameInput.value = ''; } else { alert(data.message || 'Errore'); } })
    .catch(() => alert('Errore di connessione'));
}

function wpRemoveActivity(type, recordId) {
    if (!confirm('Rimuovere questa attivit\u00e0?')) return;
    const formData = new URLSearchParams();
    formData.append('action', 'removeactivity');
    formData.append('studentid', wpStudentId);
    formData.append('type', type);
    formData.append('recordid', recordId);
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    fetch('ajax_week_planner.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (data.success) { wpLoadPlan(); } else { alert(data.message || 'Errore'); } })
    .catch(() => alert('Errore di connessione'));
}

</script>

<?php
echo $OUTPUT->footer();

// ============================================
// RENDER AND HELPER FUNCTIONS
// ============================================

/**
 * Render coach badge for a student
 */
function render_coach_badge($student) {
    if (!empty($student->coach_name)) {
        return '<span class="coach-badge assigned" title="' . get_string('assigned_coach', 'local_coachmanager') . '">&#128100; ' . s($student->coach_name) . '</span>';
    }
    return '<span class="coach-badge no-coach" title="' . get_string('no_coach_assigned', 'local_coachmanager') . '">&#128100; ' . get_string('no_coach_assigned', 'local_coachmanager') . '</span>';
}

// ============================================
// RENDER FUNCTIONS (with coach badge)
// ============================================

/**
 * Render VISTA COMPATTA
 */
function cs_render_view_compatta($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-compatta">
        <div class="students-list">
            <div class="student-row header">
                <div></div>
                <div>Studente</div>
                <div>Settore</div>
                <div>Coach</div>
                <div>Sett.</div>
                <div>Comp.</div>
                <div>Autov.</div>
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
                    <div class="email"><a href="mailto:<?php echo $student->email; ?>" onclick="event.stopPropagation(); openOutlook('<?php echo $student->email; ?>'); return false;" title="Apri in Outlook"><?php echo $student->email; ?></a></div>
                </div>
                <div>
                    <?php echo render_sector_selector_cs($student); ?>
                </div>
                <div>
                    <?php echo render_coach_badge($student); ?>
                </div>
                <div class="week-cell">
                    <?php echo $student->current_week ?? 1; ?>
                    <div class="week-planner-mini">
                        <?php
                        $compatta_cw = $student->current_week ?? 1;
                        for ($wp_w = 1; $wp_w <= 6; $wp_w++):
                            $wp_c = 'future';
                            if ($wp_w < $compatta_cw) $wp_c = 'completed';
                            elseif ($wp_w == $compatta_cw) $wp_c = 'current';
                        ?>
                        <button class="wp-mini-btn <?php echo $wp_c; ?>"
                                onclick="event.stopPropagation(); openWeekPlanner(<?php echo $student->id; ?>, <?php echo $wp_w; ?>, '<?php echo s(fullname($student)); ?>')"
                                title="S<?php echo $wp_w; ?>"><?php echo $wp_w; ?></button>
                        <?php endfor; ?>
                    </div>
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
                    <button class="btn btn-success btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/coach_evaluation.php?studentid=<?php echo $student->id; ?>&sector=<?php echo urlencode($student->sector ?? 'MECCANICA'); ?>'"
                            title="Valutazione Formatore">
                        &#128100;
                    </button>
                    <button class="btn btn-info btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/student_card.php?userid=<?php echo $student->id; ?>'"
                            title="Profilo Studente"
                            style="background: #475569 !important; color: #fff !important; border-color: #3d4a5c !important;">
                        &#128203;
                    </button>
                    <button class="btn btn-primary btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>&amp;courseid=0&amp;viz_configured=1&amp;cm_sector=<?php echo strtolower($student->sector ?? 'meccanica'); ?>&amp;show_spunti=1&amp;show_dual_radar=1&amp;show_gap=1&amp;show_coach_eval=1&amp;show_overlay=1&amp;soglia_allineamento=10&amp;soglia_critico=30&amp;attempt_filter=all&amp;open_tab=spunti'"
                            title="Colloquio">
                        &#128172;
                    </button>
                    <button class="btn btn-primary btn-sm"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/report.php?userid=<?php echo $student->id; ?>'"
                            title="Rapporto CPURC">
                        &#128209;
                    </button>
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
function cs_render_view_standard($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-standard">

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
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #b0b5c0;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#128101;</div>
                <h3 style="color: #555;">Nessuno studente trovato</h3>
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
            ?>
            <div class="student-card collapsed <?php echo $card_class; ?>" id="student-<?php echo $student->id; ?>">

                <!-- Card Header -->
                <div class="student-card-header <?php echo $header_class; ?>" onclick="toggleCard('student-<?php echo $student->id; ?>')">
                    <div class="student-info-left">
                        <div class="collapse-toggle">&#9660;</div>
                        <div>
                            <div class="student-name"><?php echo fullname($student); ?></div>
                            <div class="student-email"><a href="mailto:<?php echo $student->email; ?>" onclick="event.stopPropagation(); openOutlook('<?php echo $student->email; ?>'); return false;" title="Apri in Outlook"><?php echo $student->email; ?></a></div>
                            <div class="student-sectors-row"><?php echo render_sector_selector_cs($student); ?></div>
                            <div style="margin-top: 4px;"><?php echo render_coach_badge($student); ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">FINE 6 SETT.</span>
                        <?php endif; ?>
                        <?php if ($is_below): ?>
                        <span class="badge-below">SOTTO SOGLIA</span>
                        <?php endif; ?>
                        <span class="week-badge <?php echo $header_class; ?>">Sett. <?php echo $current_week; ?></span>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>"
                       class="quick-btn report" title="Report Competenze">
                        &#128202; Report
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/coach_evaluation.php?studentid=<?php echo $student->id; ?>&sector=<?php echo urlencode($student->sector ?? 'MECCANICA'); ?>"
                       class="quick-btn eval" title="Valutazione Formatore">
                        &#128100; Valutazione
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/student_card.php?userid=<?php echo $student->id; ?>"
                       class="quick-btn qb-profile" title="Profilo Studente">
                        &#128203; Profilo
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>&amp;courseid=0&amp;viz_configured=1&amp;cm_sector=<?php echo strtolower($student->sector ?? 'meccanica'); ?>&amp;show_spunti=1&amp;show_dual_radar=1&amp;show_gap=1&amp;show_coach_eval=1&amp;show_overlay=1&amp;soglia_allineamento=10&amp;soglia_critico=30&amp;attempt_filter=all&amp;open_tab=spunti"
                       class="quick-btn colloquio" title="Spunti Colloquio">
                        &#128172; Colloquio
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/report.php?userid=<?php echo $student->id; ?>"
                       class="quick-btn cpurc" title="Rapporto CPURC">
                        &#128209; Rapporto CPURC
                    </a>
                    <?php if ($is_end6): ?>
                    <a href="#" onclick="exportWord(<?php echo $student->id; ?>); return false;"
                       class="quick-btn word" title="Esporta Word">
                        &#128196; Word
                    </a>
                    <?php endif; ?>
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
                                    $week_icon = '&#9711;';
                                    if ($week < $current_week) {
                                        $week_class = 'completed';
                                        $week_icon = '&#10004;';
                                    } elseif ($week == $current_week) {
                                        $week_class = 'current';
                                        $week_icon = '&#9679;';
                                    }
                                ?>
                                <div class="timeline-week <?php echo $week_class; ?>"
                                     onclick="openWeekPlanner(<?php echo $student->id; ?>, <?php echo $week; ?>, '<?php echo s(fullname($student)); ?>')"
                                     title="Pianifica Settimana <?php echo $week; ?>">
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

                        <?php if ($is_end6): ?>
                        <div class="end-path-box">
                            <h4>&#127937; Report Finale Richiesto</h4>
                            <p>Lo studente ha completato le 6 settimane. E necessario generare il report finale.</p>
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
function cs_render_view_dettagliata($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-dettagliata">

        <div style="margin-bottom: 15px; display: flex; gap: 10px;">
            <button class="btn btn-secondary btn-sm" onclick="expandAllCards()">
                &#9660; Espandi Tutto
            </button>
            <button class="btn btn-secondary btn-sm" onclick="collapseAllCards()">
                &#9650; Comprimi Tutto
            </button>
        </div>

        <div class="students-list">
            <?php if (empty($students)): ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #b0b5c0;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#128101;</div>
                <h3 style="color: #555;">Nessuno studente trovato</h3>
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
            <div class="student-panel collapsed <?php echo $panel_class; ?>" id="panel-<?php echo $student->id; ?>">

                <!-- Panel Header -->
                <div class="panel-header <?php echo $header_class; ?>" onclick="togglePanel('panel-<?php echo $student->id; ?>')">
                    <div class="student-main-info">
                        <div class="panel-collapse-toggle">&#9660;</div>
                        <div class="student-avatar"><?php echo $initials; ?></div>
                        <div class="student-name-block">
                            <div class="name"><?php echo fullname($student); ?></div>
                            <div class="email"><a href="mailto:<?php echo $student->email; ?>" onclick="event.stopPropagation(); openOutlook('<?php echo $student->email; ?>'); return false;" title="Apri in Outlook"><?php echo $student->email; ?></a></div>
                            <div class="student-sectors-row"><?php echo render_sector_selector_cs($student); ?></div>
                            <div style="margin-top: 4px;"><?php echo render_coach_badge($student); ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">&#127937; FINE 6 SETTIMANE</span>
                        <?php endif; ?>
                        <span class="week-badge <?php echo $header_class; ?>">Settimana <?php echo $current_week; ?></span>
                    </div>
                </div>

                <!-- Collapsible Content -->
                <div class="panel-collapsible">
                <div class="panel-body">
                    <div class="panel-grid">

                        <!-- Left Column -->
                        <div class="left-column">
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
                            <div class="timeline-section" style="margin: 0; padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #b8bcc8;">
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
                                    <div class="timeline-week <?php echo $week_class; ?>" style="flex: 1; min-width: 60px; cursor: pointer;"
                                         onclick="openWeekPlanner(<?php echo $student->id; ?>, <?php echo $week; ?>, '<?php echo s(fullname($student)); ?>')"
                                         title="Pianifica Settimana <?php echo $week; ?>">
                                        <div class="week-icon"><?php echo $week_icon; ?></div>
                                        <div class="week-num">S<?php echo $week; ?></div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="right-column">
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

                            <div style="padding: 18px; background: #f8f9fa; border-radius: 12px; border: 2px solid #b8bcc8;">
                                <h4 style="margin: 0 0 12px 0; font-size: 15px;">&#128202; Stato Attivita</h4>
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
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/student_card.php?userid=<?php echo $student->id; ?>'"
                            style="background: #475569 !important; color: #fff !important; border-color: #3d4a5c !important;">
                        &#128203; Profilo
                    </button>
                    <button class="btn btn-secondary"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                        &#128202; Report Avanzato
                    </button>
                    <button class="btn btn-success"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/coach_evaluation.php?studentid=<?php echo $student->id; ?>&sector=<?php echo urlencode($student->sector ?? 'MECCANICA'); ?>'">
                        &#128100; Valutazione
                    </button>
                    <button class="btn btn-primary"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>&amp;courseid=0&amp;viz_configured=1&amp;cm_sector=<?php echo strtolower($student->sector ?? 'meccanica'); ?>&amp;show_spunti=1&amp;show_dual_radar=1&amp;show_gap=1&amp;show_coach_eval=1&amp;show_overlay=1&amp;soglia_allineamento=10&amp;soglia_critico=30&amp;attempt_filter=all&amp;open_tab=spunti'">
                        &#128172; Colloquio
                    </button>
                    <button class="btn btn-primary"
                            onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/report.php?userid=<?php echo $student->id; ?>'">
                        &#128209; Rapporto CPURC
                    </button>
                    <?php if ($is_end6): ?>
                    <button class="btn btn-warning"
                            onclick="exportWord(<?php echo $student->id; ?>)">
                        &#128196; Esporta Word
                    </button>
                    <?php endif; ?>
                </div>
                </div><!-- /panel-collapsible -->
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render VISTA CLASSICA
 */
function cs_render_view_classica($students, $dashboard) {
    global $CFG, $USER;
    ?>
    <div class="view-classica">
        <div class="students-grid">
            <?php if (empty($students)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #b0b5c0;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#128101;</div>
                <h3 style="color: #555;">Nessuno studente trovato</h3>
                <p style="color: #999;">Prova a modificare i filtri di ricerca</p>
            </div>
            <?php else: ?>

            <?php foreach ($students as $student):
                $is_end6 = ($student->current_week ?? 0) >= 6;
                $is_below = ($student->competency_avg ?? 0) < 50;
                $card_class = $is_end6 ? 'end-6-weeks' : ($is_below ? 'alert-border' : '');
                $header_class = $student->group_color ?? 'giallo';
            ?>
            <div class="student-card collapsed <?php echo $card_class; ?>"
                 id="student-<?php echo $student->id; ?>"
                 data-color="<?php echo $header_class; ?>">

                <div class="student-card-header <?php echo $header_class; ?>" onclick="toggleCard('student-<?php echo $student->id; ?>')">
                    <div class="student-info-left">
                        <div class="collapse-toggle">&#9660;</div>
                        <div>
                            <div class="student-name"><?php echo fullname($student); ?></div>
                            <div class="student-email"><a href="mailto:<?php echo $student->email; ?>" onclick="event.stopPropagation(); openOutlook('<?php echo $student->email; ?>'); return false;" title="Apri in Outlook"><?php echo $student->email; ?></a></div>
                            <div class="student-sectors-row"><?php echo render_sector_selector_cs($student); ?></div>
                            <div style="margin-top: 4px;"><?php echo render_coach_badge($student); ?></div>
                        </div>
                    </div>
                    <div class="student-badges">
                        <?php if ($is_end6): ?>
                        <span class="badge-6-weeks">FINE 6 SETT.</span>
                        <?php endif; ?>
                        <?php if ($is_below): ?>
                        <span class="badge-below">SOTTO SOGLIA</span>
                        <?php endif; ?>
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

                        <!-- Week Planner Row (Classica view) -->
                        <div class="week-planner-row">
                            <?php
                            $classica_cw = $student->current_week ?? 1;
                            for ($wp_w = 1; $wp_w <= 6; $wp_w++):
                                $wp_c = 'future';
                                if ($wp_w < $classica_cw) $wp_c = 'completed';
                                elseif ($wp_w == $classica_cw) $wp_c = 'current';
                            ?>
                            <button class="week-planner-btn <?php echo $wp_c; ?>"
                                    onclick="event.stopPropagation(); openWeekPlanner(<?php echo $student->id; ?>, <?php echo $wp_w; ?>, '<?php echo s(fullname($student)); ?>')"
                                    title="Pianifica Settimana <?php echo $wp_w; ?>">
                                S<?php echo $wp_w; ?>
                            </button>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="student-card-footer" style="display: flex; padding: 15px 18px; gap: 8px; background: #f8f9fa; border-top: 1px solid #e0e0e0; flex-wrap: wrap;">
                        <button class="btn btn-info btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/student_card.php?userid=<?php echo $student->id; ?>'"
                                style="background: #475569 !important; color: #fff !important; border-color: #3d4a5c !important;">
                            &#128203; Profilo
                        </button>
                        <button class="btn btn-secondary btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>'">
                            Report
                        </button>
                        <button class="btn btn-primary btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $student->id; ?>&amp;courseid=0&amp;viz_configured=1&amp;cm_sector=<?php echo strtolower($student->sector ?? 'meccanica'); ?>&amp;show_spunti=1&amp;show_dual_radar=1&amp;show_gap=1&amp;show_coach_eval=1&amp;show_overlay=1&amp;soglia_allineamento=10&amp;soglia_critico=30&amp;attempt_filter=all&amp;open_tab=spunti'">
                            Colloquio
                        </button>
                        <button class="btn btn-primary btn-sm"
                                onclick="location.href='<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/report.php?userid=<?php echo $student->id; ?>'">
                            &#128209; CPURC
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
