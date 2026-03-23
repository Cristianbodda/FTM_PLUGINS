<?php
/**
 * SIP Student page - Coach's main interface for managing a single SIP student.
 *
 * 5 tabs: Piano d'Azione, Diario Coaching, Calendario, KPI & Ricerche, Roadmap.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_sip:view', $context);

$userid = required_param('userid', PARAM_INT);
$tab = optional_param('tab', 'piano', PARAM_ALPHANUMEXT);

// Validate user.
$student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

// Get enrollment.
$enrollment = \local_ftm_sip\sip_manager::get_enrollment($userid);
if (!$enrollment) {
    throw new moodle_exception('enrollment_not_found', 'local_ftm_sip');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/sip_student.php', ['userid' => $userid, 'tab' => $tab]));
$PAGE->set_title(get_string('action_plan_title', 'local_ftm_sip') . ': ' . fullname($student));
$PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));
$PAGE->set_pagelayout('standard');

// Can edit?
$canedit = has_capability('local/ftm_sip:edit', $context);
$is_draft = ($enrollment->plan_status === \local_ftm_sip\sip_manager::PLAN_DRAFT);

// Compute current week and phase.
$current_week = 0;
$current_phase = 0;
$phase_name = '';
if ($enrollment->status === 'active' && $enrollment->date_start > 0) {
    $current_week = local_ftm_sip_calculate_week($enrollment->date_start);
    $current_phase = local_ftm_sip_get_phase($current_week);
    $phases_def = local_ftm_sip_get_phases();
    if (isset($phases_def[$current_phase])) {
        $phase_name = get_string($phases_def[$current_phase]['name'], 'local_ftm_sip');
    }
}

// Coach name.
$coach = $DB->get_record('user', ['id' => $enrollment->coachid, 'deleted' => 0]);
$coach_name = $coach ? fullname($coach) : '-';

// Sector.
$sector = $enrollment->sector ?: '-';

// Next appointment.
$next_appt = \local_ftm_sip\sip_manager::get_next_appointment($userid);

// KPI summary.
$kpi = \local_ftm_sip\sip_manager::get_kpi_summary($enrollment->id);

// Action plan data.
$action_plan = \local_ftm_sip\sip_manager::get_action_plan($enrollment->id);
$areas_def = local_ftm_sip_get_activation_areas();
$scale = local_ftm_sip_get_activation_scale();

// Phase notes (for roadmap tab).
$phase_notes_raw = $DB->get_records('local_ftm_sip_phase_notes', ['enrollmentid' => $enrollment->id]);
$phase_notes = [];
foreach ($phase_notes_raw as $pn) {
    $phase_notes[$pn->phase] = $pn;
}
$phases_def = local_ftm_sip_get_phases();

// Week progress percentage.
$week_display = min($current_week, LOCAL_FTM_SIP_TOTAL_WEEKS);
$week_pct = ($current_week > 0) ? round(($week_display / LOCAL_FTM_SIP_TOTAL_WEEKS) * 100) : 0;

// Meetings data (for diary tab).
$meetings = sip_manager::get_meetings($enrollment->id);

// All actions (for diary tab).
$all_actions = sip_manager::get_all_actions($enrollment->id);
$actions_by_meeting = [];
foreach ($all_actions as $act) {
    $actions_by_meeting[$act->meetingid][] = $act;
}

// Pending actions.
$pending_actions = sip_manager::get_pending_actions($enrollment->id);

// Appointments (for calendar tab).
$appointments = sip_manager::get_enrollment_appointments($enrollment->id);
$upcoming_appts = [];
$past_appts = [];
$now = time();
foreach ($appointments as $appt) {
    if ($appt->appointment_date >= $now || in_array($appt->status, ['scheduled', 'confirmed'])) {
        $upcoming_appts[] = $appt;
    } else {
        $past_appts[] = $appt;
    }
}

// KPI detailed data (for KPI tab).
$kpi_applications = $DB->get_records('local_ftm_sip_applications',
    ['enrollmentid' => $enrollment->id], 'application_date DESC');
$kpi_contacts = $DB->get_records('local_ftm_sip_contacts',
    ['enrollmentid' => $enrollment->id], 'contact_date DESC');
$kpi_opportunities = $DB->get_records('local_ftm_sip_opportunities',
    ['enrollmentid' => $enrollment->id], 'opportunity_date DESC');

// Status label.
$status_key = 'status_' . $enrollment->status;
$status_label = get_string($status_key, 'local_ftm_sip');

// Plan status label.
$plan_key = 'plan_status_' . $enrollment->plan_status;
$plan_label = get_string($plan_key, 'local_ftm_sip');

// Eligibility assessment data.
$eligibility = null;
if ($enrollment->eligibility_id) {
    $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['id' => $enrollment->eligibility_id]);
} else {
    // Try to find by userid.
    $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);
}

// Quality indicators computation.
$quality = new stdClass();
// 1. Baseline levels: count how many of the 7 areas have level_initial set (not null and > 0).
$quality->baseline_set = 0;
foreach ($action_plan as $ap) {
    if (isset($ap->level_initial) && $ap->level_initial !== null && (int)$ap->level_initial > 0) {
        $quality->baseline_set++;
    }
}
$quality->baseline_total = 7;
$quality->baseline_status = ($quality->baseline_set >= 7) ? 'ok' : (($quality->baseline_set > 0) ? 'partial' : 'missing');

// 2. Meetings count.
$quality->meetings_count = count($meetings);
$quality->meetings_status = ($quality->meetings_count >= 3) ? 'ok' : (($quality->meetings_count > 0) ? 'partial' : 'missing');

// 3. KPI entries.
$quality->kpi_count = count($kpi_applications) + count($kpi_contacts) + count($kpi_opportunities);
$quality->kpi_status = ($quality->kpi_count > 0) ? 'ok' : 'missing';

// 4. Meeting frequency (meetings per week).
$quality->frequency_ratio = 0;
if ($current_week > 0 && $quality->meetings_count > 0) {
    $quality->frequency_ratio = round($quality->meetings_count / $current_week, 1);
}
$quality->frequency_status = ($quality->frequency_ratio >= 0.5) ? 'ok' : (($quality->frequency_ratio > 0) ? 'partial' : 'missing');

// Final levels count for closure validation.
$quality->final_levels_set = 0;
foreach ($action_plan as $ap) {
    if (isset($ap->level_current) && $ap->level_current !== null && (int)$ap->level_current > 0) {
        $quality->final_levels_set++;
    }
}

// =============================================
// Competency assessment data from 6-week phase (Gap G integration).
// =============================================
$assessment_data = new stdClass();
$assessment_data->sector = null;
$assessment_data->quiz_avg = null;
$assessment_data->autoval_avg = null;
$assessment_data->coach_eval_avg = null;
$assessment_data->quiz_count = 0;
$assessment_data->competency_count = 0;

// Primary sector from local_student_sectors.
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_student_sectors')) {
    $sector_rec = $DB->get_record_sql(
        "SELECT sector, quiz_count FROM {local_student_sectors}
         WHERE userid = :userid AND is_primary = 1
         ORDER BY timemodified DESC",
        ['userid' => $userid],
        IGNORE_MULTIPLE
    );
    if ($sector_rec) {
        $assessment_data->sector = $sector_rec->sector;
        $assessment_data->quiz_count = (int)$sector_rec->quiz_count;
    }
}

// Quiz average score.
$quiz_avg = $DB->get_record_sql(
    "SELECT AVG(qas.fraction) * 100 as avg_score,
            COUNT(DISTINCT qa.questionid) as q_count
     FROM {quiz_attempts} quiza
     JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
     JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
     WHERE quiza.userid = :userid AND quiza.state = :state
     AND qas.fraction IS NOT NULL
     AND qas.sequencenumber = (
         SELECT MAX(qas2.sequencenumber)
         FROM {question_attempt_steps} qas2
         WHERE qas2.questionattemptid = qa.id
     )",
    ['userid' => $userid, 'state' => 'finished']
);
if ($quiz_avg && $quiz_avg->avg_score !== null) {
    $assessment_data->quiz_avg = round((float)$quiz_avg->avg_score, 1);
    $assessment_data->competency_count = (int)($quiz_avg->q_count ?? 0);
}

// Self-assessment average from local_selfassessment.
if ($dbman->table_exists('local_selfassessment')) {
    $sa_avg = $DB->get_record_sql(
        "SELECT AVG(sa.level) as avg_level, COUNT(*) as cnt
         FROM {local_selfassessment} sa
         WHERE sa.userid = :userid AND sa.level > 0",
        ['userid' => $userid]
    );
    if ($sa_avg && (int)$sa_avg->cnt > 0) {
        $assessment_data->autoval_avg = round((float)$sa_avg->avg_level, 1);
    }
}

// Coach evaluation average from local_coach_evaluations + local_coach_eval_ratings.
if ($dbman->table_exists('local_coach_evaluations')) {
    $ce_avg = $DB->get_record_sql(
        "SELECT AVG(r.rating) as avg_rating
         FROM {local_coach_eval_ratings} r
         JOIN {local_coach_evaluations} e ON e.id = r.evaluationid
         WHERE e.studentid = :userid AND r.rating > 0
         AND e.status IN ('completed', 'signed')",
        ['userid' => $userid]
    );
    if ($ce_avg && $ce_avg->avg_rating !== null) {
        $assessment_data->coach_eval_avg = round((float)$ce_avg->avg_rating, 1);
    }
}

// Check if we have any assessment data at all.
$has_assessment_data = ($assessment_data->sector !== null || $assessment_data->quiz_avg !== null
    || $assessment_data->autoval_avg !== null || $assessment_data->coach_eval_avg !== null);

echo $OUTPUT->header();

// =============================================
// GENERATE RADAR CHART SVG (7-area, dual polygon)
// =============================================
$radar_svg = '';
$area_keys_ordered = array_keys($areas_def);
$n_areas = count($area_keys_ordered);

if ($n_areas >= 3) {
    $svg_size = 400;
    $padding_h = 120;
    $svg_w = $svg_size + 2 * $padding_h;
    $svg_h = $svg_size + 60;
    $cx = $padding_h + ($svg_size / 2);
    $cy = $svg_size / 2 + 10;
    $margin = 60;
    $radius = ($svg_size / 2) - $margin;
    $angle_step = (2 * M_PI) / $n_areas;
    $max_level = 6;

    $radar_svg = '<svg width="' . $svg_w . '" height="' . $svg_h . '" xmlns="http://www.w3.org/2000/svg" style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif;">';

    // Grid rings at levels 1-6.
    for ($lv = 1; $lv <= $max_level; $lv++) {
        $r = $radius * ($lv / $max_level);
        $stroke_color = ($lv % 2 === 0) ? '#d1d5db' : '#e5e7eb';
        $radar_svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . round($r, 1) . '" fill="none" stroke="' . $stroke_color . '" stroke-width="1"/>';
        // Level label on the right.
        $radar_svg .= '<text x="' . round($cx + $r + 4, 1) . '" y="' . round($cy + 3, 1) . '" font-size="8" fill="#9ca3af">' . $lv . '</text>';
    }

    // Axes and labels.
    $label_coords = [];
    for ($i = 0; $i < $n_areas; $i++) {
        $angle = ($i * $angle_step) - (M_PI / 2);
        $ax = $cx + $radius * cos($angle);
        $ay = $cy + $radius * sin($angle);
        $radar_svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . round($ax, 1) . '" y2="' . round($ay, 1) . '" stroke="#e5e7eb" stroke-width="1"/>';

        $area_key = $area_keys_ordered[$i];
        $area_label = get_string($areas_def[$area_key]['name'], 'local_ftm_sip');
        $label_r = $radius + 18;
        $lx = $cx + $label_r * cos($angle);
        $ly = $cy + $label_r * sin($angle);
        $anchor = 'middle';
        if ($lx < $cx - 15) {
            $anchor = 'end';
        } else if ($lx > $cx + 15) {
            $anchor = 'start';
        }

        // Truncate label if too long.
        $short_label = mb_strlen($area_label) > 20 ? mb_substr($area_label, 0, 18) . '...' : $area_label;
        $radar_svg .= '<text x="' . round($lx, 1) . '" y="' . round($ly + 3, 1) . '" text-anchor="' . $anchor . '" font-size="10" fill="#374151">' . htmlspecialchars($short_label) . '</text>';

        $label_coords[] = ['x' => $lx, 'y' => $ly];
    }

    // Build data arrays.
    $initial_data = [];
    $current_data = [];
    foreach ($area_keys_ordered as $akey) {
        $plan_item = isset($action_plan[$akey]) ? $action_plan[$akey] : null;
        $initial_data[] = $plan_item ? (int)($plan_item->level_initial ?? 0) : 0;
        $current_data[] = $plan_item ? (int)($plan_item->level_current ?? 0) : 0;
    }

    // Polygon helper.
    $make_polygon = function($data, $fill, $stroke, $dash = '') use ($cx, $cy, $radius, $angle_step, $max_level, $n_areas) {
        $points = [];
        $dots = '';
        for ($i = 0; $i < $n_areas; $i++) {
            $angle = ($i * $angle_step) - (M_PI / 2);
            $val = min($max_level, max(0, $data[$i]));
            $pr = $radius * ($val / $max_level);
            $px = $cx + $pr * cos($angle);
            $py = $cy + $pr * sin($angle);
            $points[] = round($px, 1) . ',' . round($py, 1);
            $dots .= '<circle cx="' . round($px, 1) . '" cy="' . round($py, 1) . '" r="3.5" fill="' . $stroke . '" stroke="white" stroke-width="1.5"/>';
        }
        $dashattr = $dash ? ' stroke-dasharray="' . $dash . '"' : '';
        return '<polygon points="' . implode(' ', $points) . '" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"' . $dashattr . '/>' . $dots;
    };

    // Initial polygon (gray dashed).
    $radar_svg .= $make_polygon($initial_data, 'rgba(156,163,175,0.15)', '#9ca3af', '6,3');

    // Current polygon (teal solid).
    $radar_svg .= $make_polygon($current_data, 'rgba(8,145,178,0.2)', '#0891B2');

    // Legend.
    $leg_y = $svg_h - 15;
    $radar_svg .= '<rect x="' . ($cx - 120) . '" y="' . ($leg_y - 5) . '" width="12" height="12" fill="#9ca3af" rx="2"/>';
    $radar_svg .= '<text x="' . ($cx - 104) . '" y="' . ($leg_y + 5) . '" font-size="10" fill="#374151">' . htmlspecialchars(get_string('sip_student_initial_level', 'local_ftm_sip')) . '</text>';
    $radar_svg .= '<rect x="' . ($cx + 20) . '" y="' . ($leg_y - 5) . '" width="12" height="12" fill="#0891B2" rx="2"/>';
    $radar_svg .= '<text x="' . ($cx + 36) . '" y="' . ($leg_y + 5) . '" font-size="10" fill="#374151">' . htmlspecialchars(get_string('sip_student_current_level', 'local_ftm_sip')) . '</text>';

    $radar_svg .= '</svg>';
}

?>

<style>
/* ==========================================
   SIP Student Page Styles
   ========================================== */
.sip-student-page {
    max-width: 1200px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* --- Header Card --- */
.sip-header {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
}

.sip-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.sip-header-left h2 {
    margin: 0 0 6px 0;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
}

.sip-header-left .sip-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #6b7280;
}

.sip-header-left .sip-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.sip-header-right {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.sip-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.sip-badge-teal {
    background: #ECFEFF;
    color: #155E75;
    border: 1px solid #06B6D4;
}

.sip-badge-draft {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #F59E0B;
}

.sip-badge-sector {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #93C5FD;
}

.sip-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: opacity 0.2s;
}

.sip-btn:hover { opacity: 0.85; text-decoration: none !important; }
a.sip-btn, a.sip-btn:visited, a.sip-btn:hover, a.sip-btn:active { color: white !important; text-decoration: none !important; }

.sip-btn-secondary { background: #6b7280; color: white; }
.sip-btn-teal { background: #0891B2; color: white; }

/* Header stats row */
.sip-header-stats {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    flex-wrap: wrap;
    align-items: stretch;
}

.sip-stat-card {
    flex: 1;
    min-width: 140px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sip-stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
}

.sip-stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.sip-stat-sub {
    font-size: 11px;
    color: #9ca3af;
}

/* Progress bar */
.sip-progress-bar {
    width: 100%;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
}

.sip-progress-fill {
    height: 100%;
    background: #0891B2;
    border-radius: 3px;
    transition: width 0.4s ease;
}

/* Visibility toggle */
.sip-toggle-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f3f4f6;
}

.sip-toggle-row label {
    font-size: 13px;
    color: #374151;
    cursor: pointer;
    user-select: none;
}

.sip-toggle-row input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #0891B2;
    cursor: pointer;
}

.sip-toggle-feedback {
    font-size: 12px;
    margin-left: 4px;
    opacity: 0;
    transition: opacity 0.3s;
}

/* --- Tab Navigation --- */
.sip-tabs {
    display: flex;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    overflow-x: auto;
}

.sip-tab {
    padding: 14px 22px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    font-size: 14px;
    color: #6b7280;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s, border-color 0.2s;
}

.sip-tab:hover {
    background: #f9fafb;
    color: #374151;
    text-decoration: none;
}

.sip-tab.active {
    color: #0891B2;
    border-bottom-color: #0891B2;
    background: white;
}

.sip-tab-content {
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 24px;
}

/* --- Action Plan Cards --- */
.sip-area-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.sip-area-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.sip-area-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    cursor: pointer;
    user-select: none;
    background: #fafbfc;
    transition: background 0.2s;
}

.sip-area-header:hover {
    background: #f3f4f6;
}

.sip-area-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
    flex-shrink: 0;
}

.sip-area-title {
    flex: 1;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}

.sip-level-indicators {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sip-level-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: white;
}

.sip-level-dot-initial {
    background: #9ca3af;
}

.sip-level-dot-current {
    background: #0891B2;
}

.sip-level-arrow {
    font-size: 14px;
    color: #9ca3af;
}

.sip-delta-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}

.sip-delta-positive { background: #D1FAE5; color: #065F46; }
.sip-delta-zero { background: #F3F4F6; color: #6B7280; }
.sip-delta-negative { background: #FEE2E2; color: #991B1B; }

.sip-area-chevron {
    font-size: 18px;
    color: #9ca3af;
    transition: transform 0.25s;
    margin-left: 8px;
}

.sip-area-card.expanded .sip-area-chevron {
    transform: rotate(180deg);
}

.sip-area-body {
    display: none;
    padding: 0 18px 18px;
    border-top: 1px solid #f3f4f6;
}

.sip-area-card.expanded .sip-area-body {
    display: block;
}

.sip-area-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 14px 0 18px;
    padding: 10px 14px;
    background: #f9fafb;
    border-radius: 6px;
    border-left: 3px solid #e5e7eb;
}

.sip-level-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 18px;
}

.sip-level-section h4 {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin: 0 0 10px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid #f3f4f6;
}

.sip-level-options {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sip-level-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.15s;
    font-size: 13px;
}

.sip-level-option:hover {
    background: #f3f4f6;
}

.sip-level-option input[type="radio"] {
    accent-color: #0891B2;
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

.sip-level-option .sip-lv-num {
    font-weight: 700;
    width: 18px;
    text-align: center;
    color: #374151;
}

.sip-level-option .sip-lv-label {
    color: #6b7280;
}

.sip-level-option input:disabled {
    opacity: 0.5;
}

.sip-level-option.disabled-option {
    opacity: 0.55;
    cursor: default;
}

.sip-field-group {
    margin-bottom: 14px;
}

.sip-field-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.sip-field-group textarea,
.sip-field-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.sip-field-group textarea:focus,
.sip-field-group input[type="text"]:focus {
    outline: none;
    border-color: #0891B2;
    box-shadow: 0 0 0 2px rgba(8,145,178,0.15);
}

.sip-area-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f3f4f6;
}

.sip-btn-save {
    background: #0891B2;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}

.sip-btn-save:hover { background: #0e7490; }
.sip-btn-save:disabled { background: #9ca3af; cursor: not-allowed; }

.sip-save-feedback {
    font-size: 12px;
    margin-left: 8px;
    opacity: 0;
    transition: opacity 0.3s;
    color: #059669;
}

/* --- Radar Chart Container --- */
.sip-radar-container {
    display: flex;
    justify-content: center;
    margin: 30px 0 10px;
    padding: 20px;
    background: #fafbfc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.sip-radar-title {
    text-align: center;
    font-size: 15px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12px;
}

/* --- Roadmap Timeline --- */
.sip-roadmap {
    position: relative;
    padding: 20px 0 20px 40px;
}

.sip-roadmap::before {
    content: '';
    position: absolute;
    left: 19px;
    top: 30px;
    bottom: 30px;
    width: 3px;
    background: #e5e7eb;
    border-radius: 2px;
}

.sip-phase-node {
    position: relative;
    padding: 16px 20px;
    margin-bottom: 16px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: box-shadow 0.2s, border-color 0.2s;
}

.sip-phase-node:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.sip-phase-node::before {
    content: '';
    position: absolute;
    left: -29px;
    top: 22px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid #e5e7eb;
    background: white;
    z-index: 1;
}

.sip-phase-node.phase-completed::before {
    background: #059669;
    border-color: #059669;
}

.sip-phase-node.phase-current {
    border-color: #0891B2;
    border-width: 2px;
}

.sip-phase-node.phase-current::before {
    background: #0891B2;
    border-color: #0891B2;
    box-shadow: 0 0 0 4px rgba(8,145,178,0.2);
}

.sip-phase-node.phase-future::before {
    background: white;
    border-color: #d1d5db;
}

.sip-phase-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.sip-phase-number {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}

.phase-completed .sip-phase-number { background: #D1FAE5; color: #065F46; }
.phase-current .sip-phase-number { background: #ECFEFF; color: #155E75; }
.phase-future .sip-phase-number { background: #F3F4F6; color: #6B7280; }

.sip-phase-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    flex: 1;
}

.sip-phase-weeks {
    font-size: 12px;
    color: #9ca3af;
    background: #f3f4f6;
    padding: 3px 10px;
    border-radius: 12px;
}

.sip-phase-desc {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 14px;
}

.sip-phase-fields {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px;
    align-items: start;
}

.sip-phase-check {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
}

.sip-phase-check input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #059669;
    cursor: pointer;
}

.sip-phase-check label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
}

.sip-phase-notes textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
    min-height: 60px;
    box-sizing: border-box;
    transition: border-color 0.2s;
}

.sip-phase-notes textarea:focus {
    outline: none;
    border-color: #0891B2;
    box-shadow: 0 0 0 2px rgba(8,145,178,0.15);
}

.sip-phase-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}

.sip-phase-feedback {
    font-size: 12px;
    margin-left: 8px;
    opacity: 0;
    transition: opacity 0.3s;
    color: #059669;
}

/* --- KPI Mini Cards (header) --- */
.sip-kpi-mini {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sip-kpi-mini .sip-kpi-icon {
    font-size: 14px;
}

/* --- Flash effect --- */
@keyframes sipFlashGreen {
    0% { background-color: inherit; }
    20% { background-color: #D1FAE5; }
    100% { background-color: inherit; }
}

.sip-flash-success {
    animation: sipFlashGreen 1s ease;
}

/* --- Eligibility Summary --- */
.sip-eligibility-summary {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f3f4f6;
}

.sip-eligibility-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #6b7280;
    user-select: none;
}

.sip-eligibility-toggle:hover { color: #374151; }

.sip-eligibility-toggle .fa { transition: transform 0.2s; }
.sip-eligibility-expanded .sip-eligibility-toggle .fa-chevron-right { transform: rotate(90deg); }

.sip-eligibility-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: 8px;
}

.sip-elig-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.sip-elig-badge-green { background: #D1FAE5; color: #065F46; }
.sip-elig-badge-yellow { background: #FEF3C7; color: #92400E; }
.sip-elig-badge-red { background: #FEE2E2; color: #991B1B; }
.sip-elig-badge-gray { background: #F3F4F6; color: #6b7280; }
.sip-elig-badge-teal { background: #ECFEFF; color: #155E75; }

.sip-eligibility-details {
    display: none;
    margin-top: 12px;
    background: #f8fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
}

.sip-eligibility-expanded .sip-eligibility-details { display: block; }

.sip-eligibility-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.sip-elig-field {
    font-size: 12px;
}

.sip-elig-field-label {
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 2px;
}

.sip-elig-field-value {
    color: #111827;
}

/* --- Assessment Results (Gap G) --- */
.sip-assessment-summary {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f3f4f6;
}

.sip-assessment-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #6b7280;
    user-select: none;
}

.sip-assessment-toggle:hover { color: #374151; }

.sip-assessment-toggle .fa { transition: transform 0.2s; }
.sip-assessment-expanded .sip-assessment-toggle .fa-chevron-right { transform: rotate(90deg); }

.sip-assessment-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto;
}

.sip-assess-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.sip-assess-badge-gray { background: #F3F4F6; color: #374151; }
.sip-assess-badge-blue { background: #DBEAFE; color: #1E40AF; }
.sip-assess-badge-purple { background: #EDE9FE; color: #5B21B6; }
.sip-assess-badge-green { background: #D1FAE5; color: #065F46; }

.sip-assessment-details {
    display: none;
    margin-top: 12px;
    background: #f8fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
}

.sip-assessment-expanded .sip-assessment-details { display: block; }

.sip-assessment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

.sip-assess-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
}

.sip-assess-card-value {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
}

.sip-assess-card-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    margin-top: 4px;
}

.sip-assess-note {
    margin-top: 12px;
    font-size: 11px;
    color: #9ca3af;
}

/* --- Quality Indicator Dots --- */
.sip-quality-dots {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f3f4f6;
    flex-wrap: wrap;
}

.sip-quality-dot {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #6b7280;
}

.sip-quality-dot .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.sip-quality-dot .dot-ok { background: #10B981; }
.sip-quality-dot .dot-partial { background: #F59E0B; }
.sip-quality-dot .dot-missing { background: #EF4444; }

/* --- Closure Card --- */
.sip-closure-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-top: 16px;
    overflow: hidden;
    transition: max-height 0.4s ease, opacity 0.3s ease;
}

.sip-closure-card.sip-closure-hidden {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    margin-top: 0;
    border: none;
}

.sip-closure-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #0e7490 0%, #0891B2 100%);
    color: white;
}

.sip-closure-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.sip-closure-header .fa-exclamation-triangle {
    color: #FCD34D;
    font-size: 18px;
}

.sip-closure-body {
    padding: 24px;
}

.sip-closure-row {
    margin-bottom: 16px;
}

.sip-closure-row label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 5px;
}

.sip-closure-row label .required {
    color: #EF4444;
}

.sip-closure-row select,
.sip-closure-row input[type="text"],
.sip-closure-row input[type="number"],
.sip-closure-row input[type="date"],
.sip-closure-row textarea {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    font-family: inherit;
    background: white;
    box-sizing: border-box;
    transition: border-color 0.2s;
}

.sip-closure-row select:focus,
.sip-closure-row input:focus,
.sip-closure-row textarea:focus {
    outline: none;
    border-color: #0891B2;
    box-shadow: 0 0 0 2px rgba(8,145,178,0.15);
}

.sip-closure-row textarea {
    resize: vertical;
    min-height: 80px;
}

.sip-closure-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.sip-closure-conditional {
    display: none;
}

.sip-closure-conditional.visible {
    display: block;
}

/* Closure validation checklist */
.sip-closure-validation {
    background: #f8fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    margin-top: 16px;
}

.sip-closure-validation h4 {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sip-validation-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    padding: 4px 0;
    color: #374151;
}

.sip-validation-item .fa-check {
    color: #10B981;
}

.sip-validation-item .fa-times {
    color: #EF4444;
}

.sip-closure-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.sip-btn-outline-teal {
    padding: 8px 16px;
    border: 2px solid #0891B2;
    background: transparent;
    color: #0891B2;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.sip-btn-outline-teal:hover {
    background: #0891B2;
    color: white;
}

.sip-btn-teal-solid {
    padding: 8px 16px;
    background: #0891B2;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.2s;
}

.sip-btn-teal-solid:hover { opacity: 0.85; }
.sip-btn-teal-solid:disabled { opacity: 0.5; cursor: not-allowed; }

.sip-char-count {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
    text-align: right;
}

.sip-char-count.sip-char-ok { color: #10B981; }

/* --- Responsive --- */
@media (max-width: 768px) {
    .sip-header-top { flex-direction: column; }
    .sip-header-stats { flex-direction: column; }
    .sip-level-columns { grid-template-columns: 1fr; }
    .sip-tabs { overflow-x: auto; }
    .sip-tab { padding: 12px 16px; font-size: 13px; }
    .sip-phase-fields { grid-template-columns: 1fr; }
    .sip-roadmap { padding-left: 30px; }
    .sip-roadmap::before { left: 14px; }
    .sip-phase-node::before { left: -24px; width: 14px; height: 14px; }
    .sip-closure-grid { grid-template-columns: 1fr; }
    .sip-eligibility-grid { grid-template-columns: 1fr; }
}
</style>

<div class="sip-student-page">

    <!-- ======== HEADER ======== -->
    <div class="sip-header">
        <div class="sip-header-top">
            <div class="sip-header-left">
                <h2><?php echo s(fullname($student)); ?></h2>
                <div class="sip-meta">
                    <span><i class="fa fa-envelope"></i> <?php echo s($student->email); ?></span>
                    <span><i class="fa fa-user-circle"></i> <?php echo get_string('coach', 'local_ftm_sip'); ?>: <?php echo s($coach_name); ?></span>
                    <?php if ($sector !== '-'): ?>
                    <span class="sip-badge sip-badge-sector"><?php echo s($sector); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sip-header-right">
                <span class="sip-badge sip-badge-teal">
                    <?php echo get_string('sip_badge', 'local_ftm_sip'); ?> &mdash; <?php echo s($status_label); ?>
                </span>
                <?php if ($is_draft): ?>
                <span class="sip-badge sip-badge-draft"><?php echo s($plan_label); ?></span>
                <?php endif; ?>
                <?php if ($canedit && $enrollment->status === 'active'): ?>
                <button class="sip-btn-outline-teal" onclick="SipStudent.toggleClosure()">
                    <i class="fa fa-flag-checkered"></i> <?php echo get_string('closure_complete_sip', 'local_ftm_sip'); ?>
                </button>
                <?php endif; ?>
                <a href="<?php echo new moodle_url('/local/ftm_sip/sip_dashboard.php'); ?>" class="sip-btn sip-btn-secondary">
                    <i class="fa fa-arrow-left"></i> <?php echo get_string('back', 'local_ftm_sip'); ?>
                </a>
            </div>
        </div>

        <!-- Stats row -->
        <div class="sip-header-stats">
            <!-- Week -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('current_week', 'local_ftm_sip'); ?></div>
                <div class="sip-stat-value"><?php echo $week_display; ?> / <?php echo LOCAL_FTM_SIP_TOTAL_WEEKS; ?></div>
                <div class="sip-progress-bar"><div class="sip-progress-fill" style="width:<?php echo $week_pct; ?>%"></div></div>
            </div>

            <!-- Phase -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('sip_student_phase', 'local_ftm_sip'); ?></div>
                <div class="sip-stat-value"><?php echo $current_phase; ?> / <?php echo LOCAL_FTM_SIP_TOTAL_PHASES; ?></div>
                <div class="sip-stat-sub"><?php echo s($phase_name); ?></div>
            </div>

            <!-- Next appointment -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('next_appointment', 'local_ftm_sip'); ?></div>
                <?php if ($next_appt): ?>
                <div class="sip-stat-value" style="font-size:14px;"><?php echo userdate($next_appt->appointment_date, '%d/%m/%Y'); ?></div>
                <div class="sip-stat-sub"><?php echo s($next_appt->time_start ?? ''); ?></div>
                <?php else: ?>
                <div class="sip-stat-value" style="font-size:13px; color:#9ca3af;"><?php echo get_string('no_next_appointment', 'local_ftm_sip'); ?></div>
                <?php endif; ?>
            </div>

            <!-- KPI: Candidature -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('kpi_applications', 'local_ftm_sip'); ?></div>
                <div class="sip-stat-value"><?php echo (int)$kpi->applications_total; ?></div>
            </div>

            <!-- KPI: Contatti -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('kpi_company_contacts', 'local_ftm_sip'); ?></div>
                <div class="sip-stat-value"><?php echo (int)$kpi->contacts_total; ?></div>
            </div>

            <!-- KPI: Opportunita -->
            <div class="sip-stat-card">
                <div class="sip-stat-label"><?php echo get_string('kpi_opportunities', 'local_ftm_sip'); ?></div>
                <div class="sip-stat-value"><?php echo (int)$kpi->opportunities_total; ?></div>
            </div>
        </div>

        <!-- Quality indicator dots -->
        <div class="sip-quality-dots">
            <span class="sip-quality-dot" title="<?php echo get_string('quality_baseline_levels', 'local_ftm_sip'); ?>: <?php echo $quality->baseline_set; ?>/7">
                <span class="dot dot-<?php echo $quality->baseline_status; ?>"></span>
                <?php echo get_string('quality_baseline_levels', 'local_ftm_sip'); ?>
            </span>
            <span class="sip-quality-dot" title="<?php echo $quality->meetings_count; ?> <?php echo get_string('quality_meetings_count', 'local_ftm_sip'); ?>">
                <span class="dot dot-<?php echo $quality->meetings_status; ?>"></span>
                <?php echo get_string('quality_meetings_count', 'local_ftm_sip'); ?> (<?php echo $quality->meetings_count; ?>)
            </span>
            <span class="sip-quality-dot" title="<?php echo $quality->kpi_count; ?> <?php echo get_string('quality_kpi_entries', 'local_ftm_sip'); ?>">
                <span class="dot dot-<?php echo $quality->kpi_status; ?>"></span>
                <?php echo get_string('quality_kpi_entries', 'local_ftm_sip'); ?> (<?php echo $quality->kpi_count; ?>)
            </span>
            <span class="sip-quality-dot" title="<?php echo $quality->frequency_ratio; ?> <?php echo get_string('quality_meeting_frequency', 'local_ftm_sip'); ?>">
                <span class="dot dot-<?php echo $quality->frequency_status; ?>"></span>
                <?php echo get_string('quality_meeting_frequency', 'local_ftm_sip'); ?> (<?php echo $quality->frequency_ratio; ?>)
            </span>
        </div>

        <!-- Eligibility summary - Griglia Valutazione PCI (if assessment exists) -->
        <?php if ($eligibility): ?>
        <?php
            // Decisione labels and colors.
            $decisione_labels = [
                'idoneo' => get_string('eligibility_decisione_idoneo', 'local_ftm_sip'),
                'non_idoneo' => get_string('eligibility_decisione_non_idoneo', 'local_ftm_sip'),
                'pending' => get_string('eligibility_decisione_pending', 'local_ftm_sip'),
            ];
            $decisione_colors = [
                'idoneo' => 'green', 'non_idoneo' => 'red', 'pending' => 'yellow',
            ];
            $rec_labels = [
                'activate' => get_string('eligibility_recommend_activate', 'local_ftm_sip'),
                'not_activate' => get_string('eligibility_recommend_not_activate', 'local_ftm_sip'),
                'refer_other' => get_string('eligibility_recommend_refer', 'local_ftm_sip'),
            ];
            $elig_totale = (int)($eligibility->totale ?? 0);
            $elig_decisione = $eligibility->decisione ?? 'pending';
            // 6 criteria for display.
            $elig_criteria_display = [
                'motivazione' => ['label' => get_string('eligibility_criterion_motivazione', 'local_ftm_sip'), 'value' => (int)($eligibility->motivazione ?? 0)],
                'chiarezza' => ['label' => get_string('eligibility_criterion_chiarezza', 'local_ftm_sip'), 'value' => (int)($eligibility->chiarezza_obiettivo ?? 0)],
                'occupabilita' => ['label' => get_string('eligibility_criterion_occupabilita', 'local_ftm_sip'), 'value' => (int)($eligibility->occupabilita ?? 0)],
                'autonomia' => ['label' => get_string('eligibility_criterion_autonomia', 'local_ftm_sip'), 'value' => (int)($eligibility->autonomia ?? 0)],
                'bisogno_coaching' => ['label' => get_string('eligibility_criterion_bisogno_coaching', 'local_ftm_sip'), 'value' => (int)($eligibility->bisogno_coaching ?? 0)],
                'comportamento' => ['label' => get_string('eligibility_criterion_comportamento', 'local_ftm_sip'), 'value' => (int)($eligibility->comportamento ?? 0)],
            ];
        ?>
        <div class="sip-eligibility-summary" id="sip-eligibility-summary">
            <div class="sip-eligibility-toggle" onclick="document.getElementById('sip-eligibility-summary').classList.toggle('sip-eligibility-expanded')">
                <i class="fa fa-chevron-right"></i>
                <span style="font-weight:600;"><?php echo get_string('eligibility_summary', 'local_ftm_sip'); ?></span>
                <div class="sip-eligibility-badges">
                    <span class="sip-elig-badge sip-elig-badge-teal">
                        <?php echo get_string('eligibility_total', 'local_ftm_sip'); ?>: <?php echo $elig_totale; ?>/30
                    </span>
                    <span class="sip-elig-badge sip-elig-badge-<?php echo $decisione_colors[$elig_decisione] ?? 'gray'; ?>">
                        <?php echo $decisione_labels[$elig_decisione] ?? s($elig_decisione); ?>
                    </span>
                </div>
            </div>
            <div class="sip-eligibility-details">
                <!-- 6 criteria dots display -->
                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;">
                    <?php foreach ($elig_criteria_display as $ckey => $cdata): ?>
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
                        <span style="width:130px; font-weight:600; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $cdata['label']; ?></span>
                        <span style="display:flex; gap:3px;">
                            <?php for ($dot = 1; $dot <= 5; $dot++): ?>
                            <span style="width:12px; height:12px; border-radius:50%; display:inline-block; <?php echo ($dot <= $cdata['value']) ? 'background:#0891B2;' : 'background:#DEE2E6;'; ?>"></span>
                            <?php endfor; ?>
                        </span>
                        <span style="color:#6b7280; font-size:11px;">(<?php echo $cdata['value']; ?>/5)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Recommendation -->
                <?php if (!empty($eligibility->coach_recommendation)): ?>
                <div style="font-size:12px; margin-bottom:6px;">
                    <span style="font-weight:600; color:#6b7280;"><?php echo get_string('eligibility_recommendation', 'local_ftm_sip'); ?>:</span>
                    <span style="color:#374151;"><?php echo $rec_labels[$eligibility->coach_recommendation] ?? s($eligibility->coach_recommendation); ?></span>
                    <?php if ($eligibility->coach_recommendation === 'refer_other' && !empty($eligibility->referral_detail)): ?>
                    <span style="color:#6b7280;">- <?php echo s($eligibility->referral_detail); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <!-- Note -->
                <?php if (!empty($eligibility->note)): ?>
                <div style="font-size:12px; margin-bottom:6px;">
                    <span style="font-weight:600; color:#6b7280;"><?php echo get_string('eligibility_note', 'local_ftm_sip'); ?>:</span>
                    <span style="color:#374151;"><?php echo s($eligibility->note); ?></span>
                </div>
                <?php endif; ?>
                <!-- Assessor info -->
                <div style="font-size:11px; color:#9ca3af; margin-top:10px;">
                    <?php
                        $assessor = $DB->get_record('user', ['id' => $eligibility->assessedby, 'deleted' => 0]);
                        $assess_date = userdate($eligibility->timecreated, '%d/%m/%Y %H:%M');
                    ?>
                    <?php echo get_string('coach', 'local_ftm_sip'); ?>: <?php echo $assessor ? s(fullname($assessor)) : '-'; ?> &mdash; <?php echo $assess_date; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Risultati Rilevamento Competenze (Gap G) -->
        <?php if ($has_assessment_data): ?>
        <div class="sip-assessment-summary" id="sip-assessment-summary">
            <div class="sip-assessment-toggle" onclick="document.getElementById('sip-assessment-summary').classList.toggle('sip-assessment-expanded')">
                <i class="fa fa-chevron-right"></i>
                <span style="font-weight:600;"><?php echo get_string('assessment_results', 'local_ftm_sip'); ?></span>
                <div class="sip-assessment-badges">
                    <?php if ($assessment_data->sector): ?>
                    <span class="sip-assess-badge sip-assess-badge-gray">
                        <?php echo s($assessment_data->sector); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($assessment_data->quiz_avg !== null): ?>
                    <span class="sip-assess-badge sip-assess-badge-blue">
                        Quiz: <?php echo $assessment_data->quiz_avg; ?>%
                    </span>
                    <?php endif; ?>
                    <?php if ($assessment_data->autoval_avg !== null): ?>
                    <span class="sip-assess-badge sip-assess-badge-purple">
                        Autoval: <?php echo $assessment_data->autoval_avg; ?>/6
                    </span>
                    <?php endif; ?>
                    <?php if ($assessment_data->coach_eval_avg !== null): ?>
                    <span class="sip-assess-badge sip-assess-badge-green">
                        Coach: <?php echo $assessment_data->coach_eval_avg; ?>/6
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sip-assessment-details">
                <div class="sip-assessment-grid">
                    <!-- Settore Rilevato -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#374151;">
                            <?php echo $assessment_data->sector ? s($assessment_data->sector) : '-'; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_sector', 'local_ftm_sip'); ?></div>
                    </div>
                    <!-- Media Quiz -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#2563EB;">
                            <?php echo $assessment_data->quiz_avg !== null ? $assessment_data->quiz_avg . '%' : '-'; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_quiz_avg', 'local_ftm_sip'); ?></div>
                    </div>
                    <!-- Autovalutazione Media -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#7C3AED;">
                            <?php echo $assessment_data->autoval_avg !== null ? $assessment_data->autoval_avg . '/6' : '-'; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_autoval_avg', 'local_ftm_sip'); ?></div>
                    </div>
                    <!-- Valutazione Coach Media -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#059669;">
                            <?php echo $assessment_data->coach_eval_avg !== null ? $assessment_data->coach_eval_avg . '/6' : '-'; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_coach_avg', 'local_ftm_sip'); ?></div>
                    </div>
                    <!-- Quiz Completati -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#2563EB;">
                            <?php echo (int)$assessment_data->quiz_count; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_quiz_count', 'local_ftm_sip'); ?></div>
                    </div>
                    <!-- Competenze Valutate -->
                    <div class="sip-assess-card">
                        <div class="sip-assess-card-value" style="color:#374151;">
                            <?php echo (int)$assessment_data->competency_count; ?>
                        </div>
                        <div class="sip-assess-card-label"><?php echo get_string('assessment_comp_count', 'local_ftm_sip'); ?></div>
                    </div>
                </div>
                <p class="sip-assess-note">
                    <?php echo get_string('assessment_baseline_note', 'local_ftm_sip'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visibility toggle -->
        <?php if ($canedit): ?>
        <div class="sip-toggle-row">
            <input type="checkbox" id="sip-visibility-toggle"
                   <?php echo $enrollment->student_visible ? 'checked' : ''; ?>
                   data-enrollmentid="<?php echo $enrollment->id; ?>">
            <label for="sip-visibility-toggle"><?php echo get_string('student_visibility', 'local_ftm_sip'); ?></label>
            <span id="sip-visibility-feedback" class="sip-toggle-feedback"></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ======== TABS ======== -->
    <div class="sip-tabs" id="sip-tabs">
        <a href="?userid=<?php echo $userid; ?>&tab=piano" class="sip-tab <?php echo $tab === 'piano' ? 'active' : ''; ?>" data-tab="piano">
            <i class="fa fa-list-alt"></i> <?php echo get_string('action_plan', 'local_ftm_sip'); ?>
        </a>
        <a href="?userid=<?php echo $userid; ?>&tab=diario" class="sip-tab <?php echo $tab === 'diario' ? 'active' : ''; ?>" data-tab="diario">
            <i class="fa fa-book"></i> <?php echo get_string('coaching_diary', 'local_ftm_sip'); ?>
        </a>
        <a href="?userid=<?php echo $userid; ?>&tab=calendario" class="sip-tab <?php echo $tab === 'calendario' ? 'active' : ''; ?>" data-tab="calendario">
            <i class="fa fa-calendar"></i> <?php echo get_string('appointments', 'local_ftm_sip'); ?>
        </a>
        <a href="?userid=<?php echo $userid; ?>&tab=kpi" class="sip-tab <?php echo $tab === 'kpi' ? 'active' : ''; ?>" data-tab="kpi">
            <i class="fa fa-line-chart"></i> <?php echo get_string('kpi_overview', 'local_ftm_sip'); ?>
        </a>
        <a href="?userid=<?php echo $userid; ?>&tab=roadmap" class="sip-tab <?php echo $tab === 'roadmap' ? 'active' : ''; ?>" data-tab="roadmap">
            <i class="fa fa-road"></i> Roadmap
        </a>
    </div>

    <!-- ======== TAB CONTENT ======== -->
    <div class="sip-tab-content">

        <?php if ($tab === 'piano'): ?>
        <!-- ==================== TAB 1: PIANO D'AZIONE ==================== -->

        <?php
        $area_index = 0;
        foreach ($areas_def as $area_key => $area_info):
            $plan_item = isset($action_plan[$area_key]) ? $action_plan[$area_key] : null;
            $level_initial = $plan_item ? (int)($plan_item->level_initial ?? 0) : 0;
            $level_current = $plan_item ? (int)($plan_item->level_current ?? 0) : 0;
            $delta = $level_current - $level_initial;
            $plan_id = $plan_item ? (int)$plan_item->id : 0;

            $delta_class = 'sip-delta-zero';
            $delta_text = '=';
            if ($delta > 0) {
                $delta_class = 'sip-delta-positive';
                $delta_text = '+' . $delta;
            } else if ($delta < 0) {
                $delta_class = 'sip-delta-negative';
                $delta_text = $delta;
            }

            $area_name = get_string($area_info['name'], 'local_ftm_sip');
            $area_desc = get_string($area_info['desc'], 'local_ftm_sip');
            $area_color = $area_info['color'];
            $area_icon = $area_info['icon'];
        ?>

        <div class="sip-area-card" id="sip-area-<?php echo $area_key; ?>" data-planid="<?php echo $plan_id; ?>" data-areakey="<?php echo $area_key; ?>">
            <div class="sip-area-header" onclick="SipStudent.toggleArea('<?php echo $area_key; ?>')">
                <div class="sip-area-icon" style="background:<?php echo $area_color; ?>">
                    <i class="fa <?php echo $area_icon; ?>"></i>
                </div>
                <div class="sip-area-title"><?php echo s($area_name); ?></div>
                <div class="sip-level-indicators">
                    <span class="sip-level-dot sip-level-dot-initial" title="<?php echo get_string('sip_student_initial_level', 'local_ftm_sip'); ?>"><?php echo $level_initial; ?></span>
                    <span class="sip-level-arrow">&#8594;</span>
                    <span class="sip-level-dot sip-level-dot-current" title="<?php echo get_string('sip_student_current_level', 'local_ftm_sip'); ?>"><?php echo $level_current; ?></span>
                    <span class="sip-delta-badge <?php echo $delta_class; ?>"><?php echo $delta_text; ?></span>
                </div>
                <span class="sip-area-chevron">&#9660;</span>
            </div>

            <div class="sip-area-body">
                <div class="sip-area-desc"><?php echo s($area_desc); ?></div>

                <!-- Level selectors: two columns -->
                <div class="sip-level-columns">
                    <!-- Initial -->
                    <div class="sip-level-section">
                        <h4><?php echo get_string('sip_student_initial_level', 'local_ftm_sip'); ?></h4>
                        <div class="sip-level-options">
                            <?php for ($lv = 0; $lv <= 6; $lv++):
                                $lv_label = get_string($scale[$lv], 'local_ftm_sip');
                                $disabled_attr = (!$is_draft || !$canedit) ? 'disabled' : '';
                                $opt_class = (!$is_draft || !$canedit) ? ' disabled-option' : '';
                            ?>
                            <label class="sip-level-option<?php echo $opt_class; ?>">
                                <input type="radio" name="initial_<?php echo $area_key; ?>" value="<?php echo $lv; ?>"
                                       <?php echo ($lv === $level_initial) ? 'checked' : ''; ?>
                                       <?php echo $disabled_attr; ?>
                                       onchange="SipStudent.markDirty('<?php echo $area_key; ?>')">
                                <span class="sip-lv-num"><?php echo $lv; ?></span>
                                <span class="sip-lv-label"><?php echo s($lv_label); ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Current -->
                    <div class="sip-level-section">
                        <h4><?php echo get_string('sip_student_current_level', 'local_ftm_sip'); ?></h4>
                        <div class="sip-level-options">
                            <?php for ($lv = 0; $lv <= 6; $lv++):
                                $lv_label = get_string($scale[$lv], 'local_ftm_sip');
                                $disabled_attr = !$canedit ? 'disabled' : '';
                                $opt_class = !$canedit ? ' disabled-option' : '';
                            ?>
                            <label class="sip-level-option<?php echo $opt_class; ?>">
                                <input type="radio" name="current_<?php echo $area_key; ?>" value="<?php echo $lv; ?>"
                                       <?php echo ($lv === $level_current) ? 'checked' : ''; ?>
                                       <?php echo $disabled_attr; ?>
                                       onchange="SipStudent.markDirty('<?php echo $area_key; ?>')">
                                <span class="sip-lv-num"><?php echo $lv; ?></span>
                                <span class="sip-lv-label"><?php echo s($lv_label); ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Text fields -->
                <div class="sip-field-group">
                    <label><?php echo get_string('area_objectives', 'local_ftm_sip'); ?></label>
                    <textarea rows="2" id="objective_<?php echo $area_key; ?>"
                              <?php echo !$canedit ? 'disabled' : ''; ?>
                              onchange="SipStudent.markDirty('<?php echo $area_key; ?>')"
                    ><?php echo s($plan_item->objective ?? ''); ?></textarea>
                </div>

                <div class="sip-field-group">
                    <label><?php echo get_string('area_actions', 'local_ftm_sip'); ?></label>
                    <textarea rows="2" id="actions_<?php echo $area_key; ?>"
                              <?php echo !$canedit ? 'disabled' : ''; ?>
                              onchange="SipStudent.markDirty('<?php echo $area_key; ?>')"
                    ><?php echo s($plan_item->actions_agreed ?? ''); ?></textarea>
                </div>

                <div class="sip-field-group">
                    <label><?php echo get_string('sip_student_verification', 'local_ftm_sip'); ?></label>
                    <input type="text" id="verification_<?php echo $area_key; ?>"
                           value="<?php echo s($plan_item->verification ?? ''); ?>"
                           <?php echo !$canedit ? 'disabled' : ''; ?>
                           onchange="SipStudent.markDirty('<?php echo $area_key; ?>')">
                </div>

                <div class="sip-field-group">
                    <label><?php echo get_string('area_notes', 'local_ftm_sip'); ?></label>
                    <textarea rows="2" id="notes_<?php echo $area_key; ?>"
                              <?php echo !$canedit ? 'disabled' : ''; ?>
                              onchange="SipStudent.markDirty('<?php echo $area_key; ?>')"
                    ><?php echo s($plan_item->notes ?? ''); ?></textarea>
                </div>

                <?php if ($canedit): ?>
                <div class="sip-area-actions">
                    <button class="sip-btn-save" id="save-btn-<?php echo $area_key; ?>"
                            onclick="SipStudent.saveArea('<?php echo $area_key; ?>')">
                        <i class="fa fa-check"></i> <?php echo get_string('save', 'local_ftm_sip'); ?>
                    </button>
                    <span class="sip-save-feedback" id="feedback-<?php echo $area_key; ?>"></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
            $area_index++;
        endforeach;
        ?>

        <!-- Radar Chart -->
        <?php if (!empty($radar_svg)): ?>
        <div class="sip-radar-title"><?php echo get_string('report_area_radar', 'local_ftm_sip'); ?></div>
        <div class="sip-radar-container">
            <?php echo $radar_svg; ?>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'diario'): ?>
        <!-- ==================== TAB 2: DIARIO COACHING ==================== -->

        <!-- Pending actions from previous meetings -->
        <?php if (!empty($pending_actions)): ?>
        <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:16px;margin-bottom:16px;">
            <h4 style="margin:0 0 10px;font-size:14px;color:#92400E;"><i class="fa fa-exclamation-triangle"></i> <?php echo get_string('meeting_previous_actions', 'local_ftm_sip'); ?> (<?php echo count($pending_actions); ?>)</h4>
            <?php foreach ($pending_actions as $pa): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #FDE68A;" id="pa-<?php echo $pa->id; ?>">
                <select onchange="updateActionStatus(<?php echo $pa->id; ?>, this.value)" style="border:1px solid #D1D5DB;border-radius:4px;padding:4px;font-size:12px;">
                    <option value="pending" <?php echo $pa->status==='pending'?'selected':''; ?>>&#9744; <?php echo get_string('action_status_pending', 'local_ftm_sip'); ?></option>
                    <option value="in_progress" <?php echo $pa->status==='in_progress'?'selected':''; ?>>&#9998; <?php echo get_string('action_status_in_progress', 'local_ftm_sip'); ?></option>
                    <option value="completed" <?php echo $pa->status==='completed'?'selected':''; ?>>&#9745; <?php echo get_string('action_status_completed', 'local_ftm_sip'); ?></option>
                    <option value="not_done" <?php echo $pa->status==='not_done'?'selected':''; ?>>&#10060; <?php echo get_string('action_status_not_done', 'local_ftm_sip'); ?></option>
                </select>
                <span style="flex:1;font-size:13px;"><?php echo s($pa->description); ?></span>
                <?php if ($pa->deadline): ?>
                <span style="font-size:11px;color:<?php echo $pa->deadline < time() ? '#DC2626' : '#6B7280'; ?>;"><?php echo userdate($pa->deadline, '%d/%m'); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- New meeting button -->
        <?php if ($can_edit): ?>
        <button onclick="document.getElementById('newMeetingForm').style.display = document.getElementById('newMeetingForm').style.display === 'none' ? 'block' : 'none';"
                style="background:#0891B2;color:white;border:none;padding:10px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-bottom:16px;">
            <i class="fa fa-plus"></i> <?php echo get_string('new_meeting', 'local_ftm_sip'); ?>
        </button>

        <!-- New meeting form -->
        <div id="newMeetingForm" style="display:none;background:white;border:1px solid #DEE2E6;border-radius:8px;padding:20px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;font-size:15px;color:#1A1A2E;"><?php echo get_string('new_meeting', 'local_ftm_sip'); ?></h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_date', 'local_ftm_sip'); ?> *</label>
                    <input type="date" id="newMeetingDate" value="<?php echo date('Y-m-d'); ?>" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_duration', 'local_ftm_sip'); ?></label>
                    <select id="newMeetingDuration" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                        <option value="30">30 min</option>
                        <option value="45">45 min</option>
                        <option value="60" selected>60 min</option>
                        <option value="90">90 min</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_modality', 'local_ftm_sip'); ?></label>
                    <select id="newMeetingModality" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                        <option value="presence"><?php echo get_string('meeting_modality_presence', 'local_ftm_sip'); ?></option>
                        <option value="remote"><?php echo get_string('meeting_modality_remote', 'local_ftm_sip'); ?></option>
                        <option value="phone"><?php echo get_string('meeting_modality_phone', 'local_ftm_sip'); ?></option>
                        <option value="email"><?php echo get_string('meeting_modality_email', 'local_ftm_sip'); ?></option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_summary', 'local_ftm_sip'); ?></label>
                <textarea id="newMeetingSummary" rows="2" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;font-family:inherit;" placeholder="Breve riepilogo dell'incontro..."></textarea>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_notes', 'local_ftm_sip'); ?></label>
                <textarea id="newMeetingNotes" rows="3" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;font-family:inherit;" placeholder="Note dettagliate..."></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="saveMeeting()" style="background:#0891B2;color:white;border:none;padding:8px 20px;border-radius:6px;font-weight:600;cursor:pointer;"><?php echo get_string('save', 'local_ftm_sip'); ?></button>
                <button onclick="document.getElementById('newMeetingForm').style.display='none';" style="background:#F3F4F6;color:#374151;border:1px solid #DEE2E6;padding:8px 20px;border-radius:6px;cursor:pointer;"><?php echo get_string('cancel', 'local_ftm_sip'); ?></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meeting timeline -->
        <?php if (empty($meetings)): ?>
        <div style="background:white;border-radius:8px;padding:40px;text-align:center;border:1px solid #DEE2E6;">
            <div style="font-size:40px;margin-bottom:8px;">&#128221;</div>
            <p style="color:#6B7280;"><?php echo get_string('no_meetings', 'local_ftm_sip'); ?></p>
        </div>
        <?php else: ?>
        <?php foreach ($meetings as $meeting):
            $m_actions = $actions_by_meeting[$meeting->id] ?? [];
            $modality_icons = ['presence' => '&#128100;', 'remote' => '&#128187;', 'phone' => '&#128222;', 'email' => '&#9993;'];
            $mod_icon = $modality_icons[$meeting->modality] ?? '&#128100;';
        ?>
        <div style="background:white;border-radius:8px;padding:18px;margin-bottom:12px;border:1px solid #DEE2E6;border-left:4px solid #0891B2;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <span style="font-weight:700;font-size:15px;color:#1A1A2E;"><?php echo $mod_icon; ?> <?php echo userdate($meeting->meeting_date, '%d/%m/%Y'); ?></span>
                    <?php if ($meeting->duration_minutes): ?>
                    <span style="color:#6B7280;font-size:13px;margin-left:8px;"><?php echo $meeting->duration_minutes; ?> min</span>
                    <?php endif; ?>
                    <?php if ($meeting->sip_week): ?>
                    <span style="background:#ECFEFF;color:#155E75;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;margin-left:8px;">S.<?php echo $meeting->sip_week; ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($can_edit): ?>
                <button onclick="if(confirm('Eliminare questo incontro?')) deleteMeeting(<?php echo $meeting->id; ?>);" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:16px;" title="Elimina">&#128465;</button>
                <?php endif; ?>
            </div>
            <?php if ($meeting->summary): ?>
            <div style="font-size:14px;color:#374151;margin-bottom:6px;font-weight:500;"><?php echo s($meeting->summary); ?></div>
            <?php endif; ?>
            <?php if ($meeting->notes): ?>
            <div style="font-size:13px;color:#6B7280;margin-bottom:8px;white-space:pre-line;"><?php echo s($meeting->notes); ?></div>
            <?php endif; ?>

            <!-- Actions for this meeting -->
            <?php if (!empty($m_actions)): ?>
            <div style="margin-top:10px;padding-top:10px;border-top:1px solid #E5E7EB;">
                <div style="font-size:12px;font-weight:600;color:#6B7280;margin-bottom:6px;"><?php echo get_string('meeting_actions_title', 'local_ftm_sip'); ?>:</div>
                <?php foreach ($m_actions as $act):
                    $status_icons = ['pending' => '&#9744;', 'in_progress' => '&#9998;', 'completed' => '&#9745;', 'not_done' => '&#10060;'];
                    $status_colors = ['pending' => '#6B7280', 'in_progress' => '#2563EB', 'completed' => '#059669', 'not_done' => '#DC2626'];
                ?>
                <div style="display:flex;align-items:center;gap:6px;padding:3px 0;font-size:13px;color:<?php echo $status_colors[$act->status] ?? '#6B7280'; ?>;">
                    <span><?php echo $status_icons[$act->status] ?? '&#9744;'; ?></span>
                    <span style="<?php echo $act->status==='completed' ? 'text-decoration:line-through;' : ''; ?>"><?php echo s($act->description); ?></span>
                    <?php if ($act->deadline): ?>
                    <span style="font-size:10px;color:#9CA3AF;">(<?php echo userdate($act->deadline, '%d/%m'); ?>)</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Add action inline -->
            <?php if ($can_edit): ?>
            <div style="margin-top:8px;display:flex;gap:6px;align-items:center;">
                <input type="text" id="new-action-<?php echo $meeting->id; ?>" placeholder="<?php echo get_string('add_action', 'local_ftm_sip'); ?>..." style="flex:1;border:1px solid #DEE2E6;border-radius:4px;padding:6px 8px;font-size:12px;">
                <input type="date" id="new-action-deadline-<?php echo $meeting->id; ?>" style="border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;">
                <button onclick="addAction(<?php echo $meeting->id; ?>, <?php echo $enrollment->id; ?>)" style="background:#0891B2;color:white;border:none;padding:6px 12px;border-radius:4px;font-size:12px;cursor:pointer;">+</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php elseif ($tab === 'calendario'): ?>
        <!-- ==================== TAB 3: CALENDARIO APPUNTAMENTI ==================== -->

        <!-- New appointment button -->
        <?php if ($can_edit): ?>
        <button onclick="document.getElementById('newApptForm').style.display = document.getElementById('newApptForm').style.display === 'none' ? 'block' : 'none';"
                style="background:#0891B2;color:white;border:none;padding:10px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-bottom:16px;">
            <i class="fa fa-plus"></i> <?php echo get_string('new_appointment', 'local_ftm_sip'); ?>
        </button>

        <!-- New appointment form -->
        <div id="newApptForm" style="display:none;background:white;border:1px solid #DEE2E6;border-radius:8px;padding:20px;margin-bottom:16px;">
            <h4 style="margin:0 0 12px;font-size:15px;color:#1A1A2E;"><?php echo get_string('new_appointment', 'local_ftm_sip'); ?></h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('appointment_date', 'local_ftm_sip'); ?> *</label>
                    <input type="date" id="newApptDate" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('appointment_time', 'local_ftm_sip'); ?> *</label>
                    <input type="time" id="newApptTime" value="14:00" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('appointment_duration', 'local_ftm_sip'); ?></label>
                    <select id="newApptDuration" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                        <option value="30">30 min</option>
                        <option value="45">45 min</option>
                        <option value="60" selected>60 min</option>
                        <option value="90">90 min</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('meeting_modality', 'local_ftm_sip'); ?></label>
                    <select id="newApptModality" style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                        <option value="presence"><?php echo get_string('appointment_modality_presence', 'local_ftm_sip'); ?></option>
                        <option value="remote"><?php echo get_string('appointment_modality_remote', 'local_ftm_sip'); ?></option>
                        <option value="phone"><?php echo get_string('appointment_modality_phone', 'local_ftm_sip'); ?></option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('appointment_location', 'local_ftm_sip'); ?></label>
                    <input type="text" id="newApptLocation" placeholder="Ufficio, Sala riunioni, Link video..." style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?php echo get_string('appointment_topic', 'local_ftm_sip'); ?></label>
                    <input type="text" id="newApptTopic" placeholder="Argomento dell'incontro..." style="width:100%;border:1px solid #DEE2E6;border-radius:6px;padding:8px;">
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="saveAppointment()" style="background:#0891B2;color:white;border:none;padding:8px 20px;border-radius:6px;font-weight:600;cursor:pointer;"><?php echo get_string('save', 'local_ftm_sip'); ?></button>
                <button onclick="document.getElementById('newApptForm').style.display='none';" style="background:#F3F4F6;color:#374151;border:1px solid #DEE2E6;padding:8px 20px;border-radius:6px;cursor:pointer;"><?php echo get_string('cancel', 'local_ftm_sip'); ?></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming appointments -->
        <h3 style="font-size:16px;color:#1A1A2E;margin:0 0 12px;"><i class="fa fa-calendar-check-o"></i> <?php echo get_string('upcoming_appointments', 'local_ftm_sip'); ?></h3>
        <?php if (empty($upcoming_appts)): ?>
        <div style="background:white;border-radius:8px;padding:30px;text-align:center;border:1px solid #DEE2E6;margin-bottom:20px;">
            <p style="color:#6B7280;margin:0;"><?php echo get_string('no_upcoming', 'local_ftm_sip'); ?></p>
        </div>
        <?php else: ?>
        <?php foreach ($upcoming_appts as $appt):
            $appt_status_colors = ['scheduled' => '#6B7280', 'confirmed' => '#2563EB', 'completed' => '#059669', 'cancelled' => '#DC2626', 'no_show' => '#92400E'];
            $appt_status_bg = ['scheduled' => '#F3F4F6', 'confirmed' => '#DBEAFE', 'completed' => '#D1FAE5', 'cancelled' => '#FEE2E2', 'no_show' => '#FEF3C7'];
            $sc = $appt_status_colors[$appt->status] ?? '#6B7280';
            $sb = $appt_status_bg[$appt->status] ?? '#F3F4F6';
            $mod_icons = ['presence' => '&#128100;', 'remote' => '&#128187;', 'phone' => '&#128222;'];
        ?>
        <div style="background:white;border-radius:8px;padding:16px;margin-bottom:10px;border:1px solid #DEE2E6;display:flex;align-items:center;gap:16px;" id="appt-<?php echo $appt->id; ?>">
            <div style="text-align:center;min-width:60px;">
                <div style="font-size:24px;font-weight:700;color:#0891B2;"><?php echo userdate($appt->appointment_date, '%d'); ?></div>
                <div style="font-size:12px;color:#6B7280;"><?php echo userdate($appt->appointment_date, '%b'); ?></div>
            </div>
            <div style="flex:1;">
                <div style="font-weight:600;font-size:15px;color:#1A1A2E;">
                    <?php echo $mod_icons[$appt->modality] ?? ''; ?> <?php echo s($appt->time_start); ?>
                    <span style="font-weight:400;color:#6B7280;font-size:13px;"> &middot; <?php echo $appt->duration_minutes; ?> min</span>
                </div>
                <?php if ($appt->topic): ?>
                <div style="font-size:13px;color:#374151;margin-top:2px;"><?php echo s($appt->topic); ?></div>
                <?php endif; ?>
                <?php if ($appt->location): ?>
                <div style="font-size:12px;color:#6B7280;margin-top:2px;"><i class="fa fa-map-marker"></i> <?php echo s($appt->location); ?></div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="background:<?php echo $sb; ?>;color:<?php echo $sc; ?>;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;">
                    <?php echo get_string('appt_' . $appt->status, 'local_ftm_sip'); ?>
                </span>
                <?php if ($can_edit && in_array($appt->status, ['scheduled', 'confirmed'])): ?>
                <select onchange="updateApptStatus(<?php echo $appt->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:4px;font-size:11px;">
                    <option value="">-- Azione --</option>
                    <option value="confirmed"><?php echo get_string('appt_confirmed', 'local_ftm_sip'); ?></option>
                    <option value="completed"><?php echo get_string('appt_completed', 'local_ftm_sip'); ?></option>
                    <option value="cancelled"><?php echo get_string('appt_cancelled', 'local_ftm_sip'); ?></option>
                    <option value="no_show"><?php echo get_string('appt_no_show', 'local_ftm_sip'); ?></option>
                </select>
                <button onclick="if(confirm('<?php echo get_string('appointment_confirm_delete', 'local_ftm_sip'); ?>')) deleteAppt(<?php echo $appt->id; ?>);"
                        style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:14px;">&#128465;</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Past appointments -->
        <?php if (!empty($past_appts)): ?>
        <h3 style="font-size:14px;color:#6B7280;margin:20px 0 10px;cursor:pointer;" onclick="document.getElementById('pastAppts').style.display = document.getElementById('pastAppts').style.display === 'none' ? 'block' : 'none';">
            &#9660; <?php echo get_string('past_appointments', 'local_ftm_sip'); ?> (<?php echo count($past_appts); ?>)
        </h3>
        <div id="pastAppts" style="display:none;">
            <?php foreach ($past_appts as $appt):
                $sc = $appt_status_colors[$appt->status] ?? '#6B7280';
            ?>
            <div style="background:#F9FAFB;border-radius:6px;padding:10px 14px;margin-bottom:6px;border:1px solid #E5E7EB;display:flex;align-items:center;gap:12px;opacity:0.8;">
                <span style="font-size:13px;color:#6B7280;"><?php echo userdate($appt->appointment_date, '%d/%m/%Y'); ?></span>
                <span style="font-size:13px;"><?php echo s($appt->time_start); ?></span>
                <?php if ($appt->topic): ?>
                <span style="font-size:12px;color:#374151;flex:1;"><?php echo s($appt->topic); ?></span>
                <?php endif; ?>
                <span style="color:<?php echo $sc; ?>;font-size:11px;font-weight:600;"><?php echo get_string('appt_' . $appt->status, 'local_ftm_sip'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'kpi'): ?>
        <!-- ==================== TAB 4: KPI & RICERCHE ==================== -->

        <!-- KPI Summary Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
            <div style="background:white;border-radius:8px;padding:16px;text-align:center;border:1px solid #DEE2E6;border-top:4px solid #2563EB;">
                <div style="font-size:28px;font-weight:700;color:#2563EB;"><?php echo $kpi->applications_total; ?></div>
                <div style="font-size:11px;color:#6B7280;text-transform:uppercase;"><?php echo get_string('kpi_applications', 'local_ftm_sip'); ?></div>
                <?php if ($kpi->applications_interview > 0): ?>
                <div style="font-size:11px;color:#059669;margin-top:4px;"><?php echo $kpi->applications_interview; ?> colloqui ottenuti</div>
                <?php endif; ?>
            </div>
            <div style="background:white;border-radius:8px;padding:16px;text-align:center;border:1px solid #DEE2E6;border-top:4px solid #7C3AED;">
                <div style="font-size:28px;font-weight:700;color:#7C3AED;"><?php echo $kpi->contacts_total; ?></div>
                <div style="font-size:11px;color:#6B7280;text-transform:uppercase;"><?php echo get_string('kpi_company_contacts', 'local_ftm_sip'); ?></div>
                <?php if ($kpi->contacts_positive > 0): ?>
                <div style="font-size:11px;color:#059669;margin-top:4px;"><?php echo $kpi->contacts_positive; ?> positivi</div>
                <?php endif; ?>
            </div>
            <div style="background:white;border-radius:8px;padding:16px;text-align:center;border:1px solid #DEE2E6;border-top:4px solid #059669;">
                <div style="font-size:28px;font-weight:700;color:#059669;"><?php echo $kpi->opportunities_total; ?></div>
                <div style="font-size:11px;color:#6B7280;text-transform:uppercase;"><?php echo get_string('kpi_opportunities', 'local_ftm_sip'); ?></div>
                <?php if ($kpi->opportunities_completed > 0): ?>
                <div style="font-size:11px;color:#059669;margin-top:4px;"><?php echo $kpi->opportunities_completed; ?> completate</div>
                <?php endif; ?>
            </div>
            <div style="background:white;border-radius:8px;padding:16px;text-align:center;border:1px solid #DEE2E6;border-top:4px solid #F59E0B;">
                <div style="font-size:28px;font-weight:700;color:#F59E0B;"><?php echo $kpi->actions_pending; ?></div>
                <div style="font-size:11px;color:#6B7280;text-transform:uppercase;">Azioni In Sospeso</div>
            </div>
        </div>

        <!-- Quick add buttons -->
        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <button onclick="document.getElementById('kpiAddApp').style.display=document.getElementById('kpiAddApp').style.display==='none'?'block':'none';"
                    style="background:#2563EB;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                <i class="fa fa-plus"></i> Candidatura
            </button>
            <button onclick="document.getElementById('kpiAddCnt').style.display=document.getElementById('kpiAddCnt').style.display==='none'?'block':'none';"
                    style="background:#7C3AED;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                <i class="fa fa-plus"></i> Contatto
            </button>
            <button onclick="document.getElementById('kpiAddOpp').style.display=document.getElementById('kpiAddOpp').style.display==='none'?'block':'none';"
                    style="background:#059669;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                <i class="fa fa-plus"></i> Opportunita
            </button>
        </div>

        <!-- Inline add application form -->
        <div id="kpiAddApp" style="display:none;background:white;border:1px solid #BFDBFE;border-radius:8px;padding:16px;margin-bottom:12px;">
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr 1fr 1fr;gap:8px;align-items:end;">
                <div><label style="font-size:11px;font-weight:600;display:block;">Azienda *</label>
                    <input type="text" id="kpi-app-company" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;" placeholder="Nome azienda"></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Posizione</label>
                    <input type="text" id="kpi-app-position" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;" placeholder="Ruolo"></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Tipo</label>
                    <select id="kpi-app-type" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;">
                        <option value="targeted">Mirata</option><option value="unsolicited">Auto</option>
                    </select></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Data</label>
                    <input type="date" id="kpi-app-date" value="<?php echo date('Y-m-d'); ?>" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;"></div>
                <div><button onclick="kpiAddApplication()" style="background:#2563EB;color:white;border:none;padding:6px 14px;border-radius:4px;font-size:12px;cursor:pointer;width:100%;">Salva</button></div>
            </div>
        </div>

        <!-- Inline add contact form -->
        <div id="kpiAddCnt" style="display:none;background:white;border:1px solid #DDD6FE;border-radius:8px;padding:16px;margin-bottom:12px;">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;align-items:end;">
                <div><label style="font-size:11px;font-weight:600;display:block;">Azienda *</label>
                    <input type="text" id="kpi-cnt-company" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;" placeholder="Nome azienda"></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Tipo</label>
                    <select id="kpi-cnt-type" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;">
                        <option value="phone">Tel</option><option value="visit">Visita</option><option value="email">Email</option><option value="linkedin">LinkedIn</option><option value="network">Rete</option>
                    </select></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Persona</label>
                    <input type="text" id="kpi-cnt-person" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;" placeholder="Nome"></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Data</label>
                    <input type="date" id="kpi-cnt-date" value="<?php echo date('Y-m-d'); ?>" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;"></div>
                <div><button onclick="kpiAddContact()" style="background:#7C3AED;color:white;border:none;padding:6px 14px;border-radius:4px;font-size:12px;cursor:pointer;width:100%;">Salva</button></div>
            </div>
        </div>

        <!-- Inline add opportunity form -->
        <div id="kpiAddOpp" style="display:none;background:white;border:1px solid #A7F3D0;border-radius:8px;padding:16px;margin-bottom:12px;">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:8px;align-items:end;">
                <div><label style="font-size:11px;font-weight:600;display:block;">Azienda *</label>
                    <input type="text" id="kpi-opp-company" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;" placeholder="Nome azienda"></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Tipo</label>
                    <select id="kpi-opp-type" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;">
                        <option value="interview">Colloquio</option><option value="trial_day">Giorno prova</option><option value="stage">Stage</option><option value="intermediate_earning">Guadagno int.</option><option value="hiring">Assunzione</option><option value="training">Formazione</option>
                    </select></div>
                <div><label style="font-size:11px;font-weight:600;display:block;">Data</label>
                    <input type="date" id="kpi-opp-date" value="<?php echo date('Y-m-d'); ?>" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:12px;"></div>
                <div><button onclick="kpiAddOpportunity()" style="background:#059669;color:white;border:none;padding:6px 14px;border-radius:4px;font-size:12px;cursor:pointer;width:100%;">Salva</button></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CANDIDATURE LIST -->
        <div style="background:white;border-radius:8px;padding:18px;margin-bottom:16px;border:1px solid #DEE2E6;">
            <h3 style="margin:0 0 12px;font-size:15px;color:#2563EB;"><i class="fa fa-paper-plane"></i> <?php echo get_string('kpi_applications', 'local_ftm_sip'); ?> (<?php echo count($kpi_applications); ?>)</h3>
            <?php if (empty($kpi_applications)): ?>
            <p style="color:#9CA3AF;text-align:center;padding:12px;">Nessuna candidatura registrata</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr style="border-bottom:2px solid #E5E7EB;text-align:left;">
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Data</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Azienda</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Posizione</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Tipo</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Stato</th>
                    <?php if ($can_edit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($kpi_applications as $app):
                    $type_labels = ['targeted' => 'Mirata', 'unsolicited' => 'Auto'];
                    $status_colors = ['sent' => '#6B7280', 'waiting' => '#F59E0B', 'interview' => '#059669', 'rejected' => '#DC2626', 'no_response' => '#9CA3AF'];
                    $status_labels = ['sent' => 'Inviata', 'waiting' => 'In attesa', 'interview' => 'Colloquio', 'rejected' => 'Rifiutata', 'no_response' => 'Nessuna risposta'];
                ?>
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:6px 8px;"><?php echo userdate($app->application_date, '%d/%m/%Y'); ?></td>
                    <td style="padding:6px 8px;font-weight:500;"><?php echo s($app->company_name); ?></td>
                    <td style="padding:6px 8px;color:#6B7280;"><?php echo s($app->position ?: '-'); ?></td>
                    <td style="padding:6px 8px;"><span style="background:#EFF6FF;color:#2563EB;padding:2px 6px;border-radius:4px;font-size:10px;"><?php echo $type_labels[$app->application_type] ?? $app->application_type; ?></span></td>
                    <td style="padding:6px 8px;">
                        <?php if ($can_edit): ?>
                        <select onchange="updateAppStatus(<?php echo $app->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;color:<?php echo $status_colors[$app->status] ?? '#6B7280'; ?>;">
                            <?php foreach ($status_labels as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo $app->status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $status_colors[$app->status] ?? '#6B7280'; ?>;font-weight:500;"><?php echo $status_labels[$app->status] ?? $app->status; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_edit): ?>
                    <td style="padding:6px 8px;"><button onclick="deleteKpiEntry('applications', <?php echo $app->id; ?>)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:13px;">&#128465;</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- CONTATTI LIST -->
        <div style="background:white;border-radius:8px;padding:18px;margin-bottom:16px;border:1px solid #DEE2E6;">
            <h3 style="margin:0 0 12px;font-size:15px;color:#7C3AED;"><i class="fa fa-phone"></i> <?php echo get_string('kpi_company_contacts', 'local_ftm_sip'); ?> (<?php echo count($kpi_contacts); ?>)</h3>
            <?php if (empty($kpi_contacts)): ?>
            <p style="color:#9CA3AF;text-align:center;padding:12px;">Nessun contatto registrato</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr style="border-bottom:2px solid #E5E7EB;text-align:left;">
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Data</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Azienda</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Tipo</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Persona</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Esito</th>
                    <?php if ($can_edit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($kpi_contacts as $cnt):
                    $type_icons = ['phone' => '&#128222;', 'visit' => '&#128100;', 'email' => '&#9993;', 'linkedin' => '&#128279;', 'network' => '&#128101;', 'other' => '&#128172;'];
                    $outcome_colors = ['positive' => '#059669', 'neutral' => '#6B7280', 'negative' => '#DC2626'];
                    $outcome_labels = ['positive' => 'Positivo', 'neutral' => 'Neutro', 'negative' => 'Negativo'];
                ?>
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:6px 8px;"><?php echo userdate($cnt->contact_date, '%d/%m/%Y'); ?></td>
                    <td style="padding:6px 8px;font-weight:500;"><?php echo s($cnt->company_name); ?></td>
                    <td style="padding:6px 8px;"><?php echo $type_icons[$cnt->contact_type] ?? ''; ?> <?php echo s($cnt->contact_type); ?></td>
                    <td style="padding:6px 8px;color:#6B7280;"><?php echo s($cnt->contact_person ?: '-'); ?></td>
                    <td style="padding:6px 8px;">
                        <?php if ($can_edit): ?>
                        <select onchange="updateContactOutcome(<?php echo $cnt->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;color:<?php echo $outcome_colors[$cnt->outcome] ?? '#6B7280'; ?>;">
                            <?php foreach ($outcome_labels as $ok => $ol): ?>
                            <option value="<?php echo $ok; ?>" <?php echo $cnt->outcome === $ok ? 'selected' : ''; ?>><?php echo $ol; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $outcome_colors[$cnt->outcome] ?? '#6B7280'; ?>;"><?php echo $outcome_labels[$cnt->outcome] ?? $cnt->outcome; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_edit): ?>
                    <td style="padding:6px 8px;"><button onclick="deleteKpiEntry('contacts', <?php echo $cnt->id; ?>)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:13px;">&#128465;</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- OPPORTUNITA LIST -->
        <div style="background:white;border-radius:8px;padding:18px;margin-bottom:16px;border:1px solid #DEE2E6;">
            <h3 style="margin:0 0 12px;font-size:15px;color:#059669;"><i class="fa fa-star"></i> <?php echo get_string('kpi_opportunities', 'local_ftm_sip'); ?> (<?php echo count($kpi_opportunities); ?>)</h3>
            <?php if (empty($kpi_opportunities)): ?>
            <p style="color:#9CA3AF;text-align:center;padding:12px;">Nessuna opportunita registrata</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr style="border-bottom:2px solid #E5E7EB;text-align:left;">
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Data</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Azienda</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Tipo</th>
                    <th style="padding:6px 8px;font-size:11px;color:#6B7280;">Stato</th>
                    <?php if ($can_edit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($kpi_opportunities as $opp):
                    $opp_type_labels = ['interview' => 'Colloquio', 'trial_day' => 'Giorno prova', 'stage' => 'Stage', 'intermediate_earning' => 'Guadagno int.', 'hiring' => 'Assunzione', 'training' => 'Formazione'];
                    $opp_status_colors = ['planned' => '#6B7280', 'in_progress' => '#2563EB', 'completed' => '#059669', 'cancelled' => '#DC2626'];
                    $opp_status_labels = ['planned' => 'Pianificata', 'in_progress' => 'In corso', 'completed' => 'Completata', 'cancelled' => 'Annullata'];
                ?>
                <tr style="border-bottom:1px solid #F3F4F6;">
                    <td style="padding:6px 8px;"><?php echo userdate($opp->opportunity_date, '%d/%m/%Y'); ?></td>
                    <td style="padding:6px 8px;font-weight:500;"><?php echo s($opp->company_name); ?></td>
                    <td style="padding:6px 8px;"><span style="background:#D1FAE5;color:#065F46;padding:2px 6px;border-radius:4px;font-size:10px;"><?php echo $opp_type_labels[$opp->opportunity_type] ?? $opp->opportunity_type; ?></span></td>
                    <td style="padding:6px 8px;">
                        <?php if ($can_edit): ?>
                        <select onchange="updateOppStatus(<?php echo $opp->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;">
                            <?php foreach ($opp_status_labels as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo $opp->status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $opp_status_colors[$opp->status] ?? '#6B7280'; ?>;"><?php echo $opp_status_labels[$opp->status] ?? $opp->status; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($can_edit): ?>
                    <td style="padding:6px 8px;"><button onclick="deleteKpiEntry('opportunities', <?php echo $opp->id; ?>)" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:13px;">&#128465;</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'roadmap'): ?>
        <!-- ==================== TAB 5: ROADMAP ==================== -->

        <div class="sip-roadmap">
            <?php
            foreach ($phases_def as $phase_num => $phase_info):
                $p_name = get_string($phase_info['name'], 'local_ftm_sip');
                $p_desc = get_string($phase_info['desc'], 'local_ftm_sip');
                $p_weeks = $phase_info['weeks'];
                $pn = isset($phase_notes[$phase_num]) ? $phase_notes[$phase_num] : null;
                $pn_id = $pn ? (int)$pn->id : 0;
                $objectives_met = $pn ? (int)$pn->objectives_met : 0;
                $coach_notes_val = $pn ? ($pn->coach_notes ?? '') : '';

                // Determine state.
                $phase_class = 'phase-future';
                if ($current_phase > 0 && $phase_num < $current_phase) {
                    $phase_class = 'phase-completed';
                } else if ($phase_num == $current_phase) {
                    $phase_class = 'phase-current';
                }
            ?>
            <div class="sip-phase-node <?php echo $phase_class; ?>" id="sip-phase-<?php echo $phase_num; ?>" data-phaseid="<?php echo $pn_id; ?>" data-phase="<?php echo $phase_num; ?>">
                <div class="sip-phase-header">
                    <div class="sip-phase-number"><?php echo $phase_num; ?></div>
                    <div class="sip-phase-title"><?php echo s($p_name); ?></div>
                    <span class="sip-phase-weeks"><?php echo get_string('sip_student_weeks_label', 'local_ftm_sip'); ?> <?php echo s($p_weeks); ?></span>
                </div>
                <div class="sip-phase-desc"><?php echo s($p_desc); ?></div>
                <div class="sip-phase-fields">
                    <div class="sip-phase-check">
                        <input type="checkbox" id="phase-met-<?php echo $phase_num; ?>"
                               <?php echo $objectives_met ? 'checked' : ''; ?>
                               <?php echo !$canedit ? 'disabled' : ''; ?>
                               onchange="SipStudent.savePhase(<?php echo $phase_num; ?>)">
                        <label for="phase-met-<?php echo $phase_num; ?>"><?php echo get_string('sip_student_objectives_met', 'local_ftm_sip'); ?></label>
                    </div>
                    <div class="sip-phase-notes">
                        <textarea id="phase-notes-<?php echo $phase_num; ?>"
                                  placeholder="<?php echo get_string('sip_student_phase_notes_placeholder', 'local_ftm_sip'); ?>"
                                  <?php echo !$canedit ? 'disabled' : ''; ?>
                        ><?php echo s($coach_notes_val); ?></textarea>
                    </div>
                </div>
                <?php if ($canedit): ?>
                <div class="sip-phase-actions">
                    <button class="sip-btn-save" onclick="SipStudent.savePhase(<?php echo $phase_num; ?>)">
                        <i class="fa fa-check"></i> <?php echo get_string('save', 'local_ftm_sip'); ?>
                    </button>
                    <span class="sip-phase-feedback" id="phase-feedback-<?php echo $phase_num; ?>"></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </div><!-- .sip-tab-content -->

    <!-- ======== CLOSURE FORM (hidden by default, shown on button click) ======== -->
    <?php if ($canedit && $enrollment->status === 'active'): ?>
    <?php
        // Closure validation data (for JS).
        $closure_validation = [
            'final_levels' => $quality->final_levels_set >= 7,
            'final_levels_count' => $quality->final_levels_set,
            'meetings_min' => $quality->meetings_count >= 3,
            'meetings_count' => $quality->meetings_count,
            'has_outcome' => !empty($enrollment->outcome),
            'has_evaluation' => !empty($enrollment->coach_final_evaluation) && mb_strlen($enrollment->coach_final_evaluation) >= 100,
        ];
    ?>
    <div class="sip-closure-card sip-closure-hidden" id="sip-closure-card">
        <div class="sip-closure-header">
            <i class="fa fa-exclamation-triangle"></i>
            <h3><?php echo get_string('closure_warning', 'local_ftm_sip'); ?> &mdash; <?php echo s(fullname($student)); ?></h3>
        </div>
        <div class="sip-closure-body">
            <div class="sip-closure-grid">
                <div class="sip-closure-row">
                    <label><?php echo get_string('closure_outcome', 'local_ftm_sip'); ?> <span class="required">*</span></label>
                    <select id="sip-closure-outcome" onchange="SipStudent.closureOutcomeChanged()">
                        <option value=""><?php echo get_string('closure_select_outcome', 'local_ftm_sip'); ?></option>
                        <option value="hired" <?php echo ($enrollment->outcome === 'hired') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_hired', 'local_ftm_sip'); ?></option>
                        <option value="stage" <?php echo ($enrollment->outcome === 'stage') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_stage', 'local_ftm_sip'); ?></option>
                        <option value="training" <?php echo ($enrollment->outcome === 'training') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_training', 'local_ftm_sip'); ?></option>
                        <option value="interrupted" <?php echo ($enrollment->outcome === 'interrupted') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_interrupted', 'local_ftm_sip'); ?></option>
                        <option value="not_suitable" <?php echo ($enrollment->outcome === 'not_suitable') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_not_suitable', 'local_ftm_sip'); ?></option>
                        <option value="none" <?php echo ($enrollment->outcome === 'none') ? 'selected' : ''; ?>><?php echo get_string('closure_outcome_none', 'local_ftm_sip'); ?></option>
                    </select>
                </div>
                <div class="sip-closure-row">
                    <label><?php echo get_string('closure_date', 'local_ftm_sip'); ?></label>
                    <input type="date" id="sip-closure-date" value="<?php echo $enrollment->outcome_date ? date('Y-m-d', $enrollment->outcome_date) : date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="sip-closure-grid">
                <div class="sip-closure-row">
                    <label><?php echo get_string('closure_company', 'local_ftm_sip'); ?></label>
                    <input type="text" id="sip-closure-company" value="<?php echo s($enrollment->outcome_company ?? ''); ?>"
                           placeholder="<?php echo get_string('closure_company_placeholder', 'local_ftm_sip'); ?>">
                </div>
                <div class="sip-closure-row sip-closure-conditional" id="sip-closure-percentage-row">
                    <label><?php echo get_string('closure_percentage', 'local_ftm_sip'); ?></label>
                    <input type="number" id="sip-closure-percentage" min="0" max="100" value="<?php echo (int)($enrollment->outcome_percentage ?? 100); ?>">
                </div>
            </div>

            <div class="sip-closure-row sip-closure-conditional" id="sip-closure-interruption-row">
                <label><?php echo get_string('closure_interruption_reason', 'local_ftm_sip'); ?></label>
                <textarea id="sip-closure-interruption" rows="2"
                          placeholder="<?php echo get_string('closure_interruption_placeholder', 'local_ftm_sip'); ?>"><?php echo s($enrollment->interruption_reason ?? ''); ?></textarea>
            </div>

            <div class="sip-closure-row sip-closure-conditional" id="sip-closure-referral-row">
                <label><?php echo get_string('closure_referral', 'local_ftm_sip'); ?></label>
                <input type="text" id="sip-closure-referral" value="<?php echo s($enrollment->referral_measure ?? ''); ?>"
                       placeholder="<?php echo get_string('closure_referral_placeholder', 'local_ftm_sip'); ?>">
            </div>

            <div class="sip-closure-row">
                <label><?php echo get_string('closure_coach_evaluation', 'local_ftm_sip'); ?> <span class="required">*</span></label>
                <textarea id="sip-closure-evaluation" rows="4"
                          placeholder="<?php echo get_string('closure_coach_evaluation_placeholder', 'local_ftm_sip'); ?>"
                          oninput="SipStudent.updateCharCount()"><?php echo s($enrollment->coach_final_evaluation ?? ''); ?></textarea>
                <div class="sip-char-count" id="sip-closure-charcount">0 / 100 caratteri</div>
            </div>

            <div class="sip-closure-row">
                <label><?php echo get_string('closure_next_steps', 'local_ftm_sip'); ?> <span class="required">*</span></label>
                <textarea id="sip-closure-nextsteps" rows="3"
                          placeholder="<?php echo get_string('closure_next_steps_placeholder', 'local_ftm_sip'); ?>"><?php echo s($enrollment->next_steps ?? ''); ?></textarea>
            </div>

            <!-- Validation checklist -->
            <div class="sip-closure-validation" id="sip-closure-validation">
                <h4><?php echo get_string('closure_validation', 'local_ftm_sip'); ?></h4>
                <div class="sip-validation-item" id="sip-val-levels">
                    <i class="fa <?php echo $closure_validation['final_levels'] ? 'fa-check' : 'fa-times'; ?>"></i>
                    <?php echo $quality->final_levels_set; ?>/7 <?php echo get_string('quality_baseline_levels', 'local_ftm_sip'); ?>
                </div>
                <div class="sip-validation-item" id="sip-val-meetings">
                    <i class="fa <?php echo $closure_validation['meetings_min'] ? 'fa-check' : 'fa-times'; ?>"></i>
                    <?php echo $quality->meetings_count; ?> <?php echo get_string('quality_meetings_count', 'local_ftm_sip'); ?> (min. 3)
                </div>
                <div class="sip-validation-item" id="sip-val-outcome">
                    <i class="fa fa-times"></i>
                    <span><?php echo get_string('closure_outcome', 'local_ftm_sip'); ?></span>
                </div>
                <div class="sip-validation-item" id="sip-val-evaluation">
                    <i class="fa fa-times"></i>
                    <span><?php echo get_string('closure_coach_evaluation', 'local_ftm_sip'); ?> (min. 100 caratteri)</span>
                </div>
            </div>

            <div class="sip-closure-actions">
                <button class="sip-btn sip-btn-secondary" onclick="SipStudent.toggleClosure()">
                    <i class="fa fa-times"></i> <?php echo get_string('closure_cancel', 'local_ftm_sip'); ?>
                </button>
                <button class="sip-btn-teal-solid" id="sip-closure-submit" onclick="SipStudent.submitClosure()" disabled>
                    <i class="fa fa-flag-checkered"></i> <?php echo get_string('closure_confirm_btn', 'local_ftm_sip'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .sip-student-page -->

<script>
var SipStudent = SipStudent || {};

SipStudent = (function() {
    'use strict';

    var config = {
        sesskey: '<?php echo sesskey(); ?>',
        wwwroot: '<?php echo $CFG->wwwroot; ?>',
        userid: <?php echo $userid; ?>,
        enrollmentid: <?php echo $enrollment->id; ?>,
        isDraft: <?php echo $is_draft ? 'true' : 'false'; ?>,
        canEdit: <?php echo $canedit ? 'true' : 'false'; ?>
    };

    var dirty = {};

    function flashElement(el) {
        el.classList.remove('sip-flash-success');
        void el.offsetWidth;
        el.classList.add('sip-flash-success');
    }

    function showFeedback(el, msg, isError) {
        el.textContent = msg;
        el.style.color = isError ? '#dc2626' : '#059669';
        el.style.opacity = '1';
        setTimeout(function() { el.style.opacity = '0'; }, 2500);
    }

    function ajaxPost(url, formData, callback) {
        formData.append('sesskey', config.sesskey);
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(function(resp) { return resp.json(); })
        .then(function(result) {
            if (result.success) {
                callback(null, result.data || {});
            } else {
                callback(result.message || 'Errore');
            }
        })
        .catch(function(err) { callback(err.message || 'Errore di rete'); });
    }

    // Remember active tab in localStorage.
    var tabKey = 'sip_student_tab_' + config.userid;
    var storedTab = localStorage.getItem(tabKey);
    // Only use stored tab on first page load without explicit tab param.
    if (storedTab && window.location.search.indexOf('tab=') === -1) {
        // Redirect to stored tab.
        var url = new URL(window.location.href);
        url.searchParams.set('tab', storedTab);
        window.location.replace(url.toString());
    }

    // Store current tab.
    var currentTab = '<?php echo $tab; ?>';
    localStorage.setItem(tabKey, currentTab);

    return {
        toggleArea: function(areaKey) {
            var card = document.getElementById('sip-area-' + areaKey);
            if (card) {
                card.classList.toggle('expanded');
            }
        },

        markDirty: function(areaKey) {
            dirty[areaKey] = true;
        },

        saveArea: function(areaKey) {
            var card = document.getElementById('sip-area-' + areaKey);
            if (!card) return;

            var planId = card.getAttribute('data-planid');
            if (!planId || planId === '0') {
                alert('Piano non trovato per questa area');
                return;
            }

            var btn = document.getElementById('save-btn-' + areaKey);
            var feedback = document.getElementById('feedback-' + areaKey);
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ...';

            // Read radio values.
            var initialRadio = document.querySelector('input[name="initial_' + areaKey + '"]:checked');
            var currentRadio = document.querySelector('input[name="current_' + areaKey + '"]:checked');
            var levelInitial = initialRadio ? initialRadio.value : '0';
            var levelCurrent = currentRadio ? currentRadio.value : '0';

            var fd = new FormData();
            fd.append('planid', planId);
            fd.append('level_initial', levelInitial);
            fd.append('level_current', levelCurrent);
            fd.append('objective', document.getElementById('objective_' + areaKey).value);
            fd.append('actions_agreed', document.getElementById('actions_' + areaKey).value);
            fd.append('verification', document.getElementById('verification_' + areaKey).value);
            fd.append('notes', document.getElementById('notes_' + areaKey).value);
            fd.append('is_draft', config.isDraft ? '1' : '0');

            ajaxPost(config.wwwroot + '/local/ftm_sip/ajax_save_plan.php', fd, function(err, data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check"></i> <?php echo get_string('save', 'local_ftm_sip'); ?>';

                if (err) {
                    showFeedback(feedback, err, true);
                    return;
                }

                showFeedback(feedback, '<?php echo get_string('action_plan_saved', 'local_ftm_sip'); ?>', false);
                flashElement(card);

                // Update header dots.
                var dots = card.querySelectorAll('.sip-level-dot');
                if (dots.length >= 2) {
                    dots[0].textContent = levelInitial;
                    dots[1].textContent = levelCurrent;
                }
                var d = parseInt(levelCurrent) - parseInt(levelInitial);
                var badge = card.querySelector('.sip-delta-badge');
                if (badge) {
                    badge.className = 'sip-delta-badge ' +
                        (d > 0 ? 'sip-delta-positive' : (d < 0 ? 'sip-delta-negative' : 'sip-delta-zero'));
                    badge.textContent = d > 0 ? '+' + d : (d === 0 ? '=' : '' + d);
                }

                dirty[areaKey] = false;
            });
        },

        savePhase: function(phaseNum) {
            var node = document.getElementById('sip-phase-' + phaseNum);
            if (!node) return;

            var phaseId = node.getAttribute('data-phaseid');
            if (!phaseId || phaseId === '0') return;

            var btn = node.querySelector('.sip-btn-save');
            var feedback = document.getElementById('phase-feedback-' + phaseNum);

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ...';
            }

            var met = document.getElementById('phase-met-' + phaseNum);
            var notes = document.getElementById('phase-notes-' + phaseNum);

            var fd = new FormData();
            fd.append('phaseid', phaseId);
            fd.append('objectives_met', met && met.checked ? '1' : '0');
            fd.append('coach_notes', notes ? notes.value : '');

            ajaxPost(config.wwwroot + '/local/ftm_sip/ajax_save_phase.php', fd, function(err, data) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check"></i> <?php echo get_string('save', 'local_ftm_sip'); ?>';
                }

                if (err) {
                    if (feedback) showFeedback(feedback, err, true);
                    return;
                }

                if (feedback) showFeedback(feedback, '<?php echo get_string('success_saved', 'local_ftm_sip'); ?>', false);
                flashElement(node);
            });
        },

        toggleVisibility: function() {
            // Called from event listener below.
        },

        // --- Closure functions ---
        toggleClosure: function() {
            var card = document.getElementById('sip-closure-card');
            if (!card) return;
            card.classList.toggle('sip-closure-hidden');
            if (!card.classList.contains('sip-closure-hidden')) {
                // Scroll to closure card.
                setTimeout(function() { card.scrollIntoView({behavior: 'smooth', block: 'start'}); }, 100);
                // Init state.
                SipStudent.closureOutcomeChanged();
                SipStudent.updateCharCount();
                SipStudent.validateClosure();
            }
        },

        closureOutcomeChanged: function() {
            var outcome = document.getElementById('sip-closure-outcome').value;
            // Show/hide conditional fields.
            var pctRow = document.getElementById('sip-closure-percentage-row');
            var intRow = document.getElementById('sip-closure-interruption-row');
            var refRow = document.getElementById('sip-closure-referral-row');
            if (pctRow) pctRow.className = 'sip-closure-row sip-closure-conditional' + ((outcome === 'hired') ? ' visible' : '');
            if (intRow) intRow.className = 'sip-closure-row sip-closure-conditional' + ((outcome === 'interrupted') ? ' visible' : '');
            if (refRow) refRow.className = 'sip-closure-row sip-closure-conditional' + ((outcome === 'not_suitable') ? ' visible' : '');
            SipStudent.validateClosure();
        },

        updateCharCount: function() {
            var ta = document.getElementById('sip-closure-evaluation');
            var counter = document.getElementById('sip-closure-charcount');
            if (!ta || !counter) return;
            var len = ta.value.length;
            counter.textContent = len + ' / 100 caratteri';
            counter.className = 'sip-char-count' + (len >= 100 ? ' sip-char-ok' : '');
            SipStudent.validateClosure();
        },

        validateClosure: function() {
            var outcome = document.getElementById('sip-closure-outcome');
            var evaluation = document.getElementById('sip-closure-evaluation');
            var submitBtn = document.getElementById('sip-closure-submit');
            if (!outcome || !evaluation || !submitBtn) return;

            var hasOutcome = outcome.value !== '';
            var hasEvaluation = evaluation.value.length >= 100;

            // Update validation icons.
            var valOutcome = document.getElementById('sip-val-outcome');
            var valEval = document.getElementById('sip-val-evaluation');
            if (valOutcome) {
                var icon = valOutcome.querySelector('.fa');
                icon.className = 'fa ' + (hasOutcome ? 'fa-check' : 'fa-times');
            }
            if (valEval) {
                var icon2 = valEval.querySelector('.fa');
                icon2.className = 'fa ' + (hasEvaluation ? 'fa-check' : 'fa-times');
            }

            // Enable/disable submit. Levels + meetings are server-checked too,
            // but we also validate outcome + evaluation client-side.
            var allValid = hasOutcome && hasEvaluation;
            submitBtn.disabled = !allValid;
        },

        submitClosure: function() {
            var outcome = document.getElementById('sip-closure-outcome').value;
            if (!outcome) { alert('Seleziona un esito finale'); return; }
            var evaluation = document.getElementById('sip-closure-evaluation').value.trim();
            if (evaluation.length < 100) { alert('La valutazione finale deve contenere almeno 100 caratteri'); return; }

            if (!confirm('Sei sicuro di voler completare il SIP? Questa azione non e reversibile.')) return;

            var fd = new FormData();
            fd.append('sesskey', config.sesskey);
            fd.append('action', 'close');
            fd.append('enrollmentid', config.enrollmentid);
            fd.append('outcome', outcome);
            fd.append('outcome_date', document.getElementById('sip-closure-date').value);
            fd.append('outcome_company', document.getElementById('sip-closure-company').value.trim());
            fd.append('outcome_percentage', document.getElementById('sip-closure-percentage') ? document.getElementById('sip-closure-percentage').value : '');
            fd.append('interruption_reason', document.getElementById('sip-closure-interruption') ? document.getElementById('sip-closure-interruption').value.trim() : '');
            fd.append('referral_measure', document.getElementById('sip-closure-referral') ? document.getElementById('sip-closure-referral').value.trim() : '');
            fd.append('coach_final_evaluation', evaluation);
            fd.append('next_steps', document.getElementById('sip-closure-nextsteps').value.trim());

            var btn = document.getElementById('sip-closure-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ...';

            fetch(config.wwwroot + '/local/ftm_sip/ajax_close_sip.php', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + (result.message || 'Errore sconosciuto'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-flag-checkered"></i> <?php echo get_string('closure_confirm_btn', 'local_ftm_sip'); ?>';
                }
            })
            .catch(function(err) {
                alert('Errore di connessione');
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-flag-checkered"></i> <?php echo get_string('closure_confirm_btn', 'local_ftm_sip'); ?>';
            });
        }
    };
})();

// Visibility toggle event listener.
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('sip-visibility-toggle');
    if (toggle) {
        toggle.addEventListener('change', function() {
            var feedback = document.getElementById('sip-visibility-feedback');
            var fd = new FormData();
            fd.append('sesskey', '<?php echo sesskey(); ?>');
            fd.append('enrollmentid', '<?php echo $enrollment->id; ?>');
            fd.append('visible', this.checked ? '1' : '0');

            fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_toggle_visibility.php', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    feedback.textContent = '<?php echo get_string('success_saved', 'local_ftm_sip'); ?>';
                    feedback.style.color = '#059669';
                } else {
                    feedback.textContent = result.message || 'Errore';
                    feedback.style.color = '#dc2626';
                }
                feedback.style.opacity = '1';
                setTimeout(function() { feedback.style.opacity = '0'; }, 2000);
            })
            .catch(function() {
                feedback.textContent = 'Errore di rete';
                feedback.style.color = '#dc2626';
                feedback.style.opacity = '1';
                setTimeout(function() { feedback.style.opacity = '0'; }, 2000);
            });
        });
    }
});

// ==================== DIARY FUNCTIONS ====================

function saveMeeting() {
    var fd = new FormData();
    fd.append('action', 'create_meeting');
    fd.append('enrollmentid', '<?php echo $enrollment->id; ?>');
    fd.append('meeting_date', document.getElementById('newMeetingDate').value);
    fd.append('duration', document.getElementById('newMeetingDuration').value);
    fd.append('modality', document.getElementById('newMeetingModality').value);
    fd.append('summary', document.getElementById('newMeetingSummary').value);
    fd.append('notes', document.getElementById('newMeetingNotes').value);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_meeting.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Errore');
        }
    })
    .catch(function() { alert('Errore di connessione'); });
}

function deleteMeeting(meetingid) {
    var fd = new FormData();
    fd.append('action', 'delete_meeting');
    fd.append('meetingid', meetingid);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_meeting.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function addAction(meetingid, enrollmentid) {
    var input = document.getElementById('new-action-' + meetingid);
    var deadline = document.getElementById('new-action-deadline-' + meetingid);
    if (!input.value.trim()) return;

    var fd = new FormData();
    fd.append('action', 'create_action');
    fd.append('meetingid', meetingid);
    fd.append('enrollmentid', enrollmentid);
    fd.append('description', input.value.trim());
    fd.append('deadline', deadline.value);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_meeting.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function updateActionStatus(actionid, status) {
    if (!status) return;
    var fd = new FormData();
    fd.append('action', 'update_action');
    fd.append('actionid', actionid);
    fd.append('status', status);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_meeting.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('pa-' + actionid);
            if (el && (status === 'completed' || status === 'not_done')) {
                el.style.opacity = '0.5';
                el.style.textDecoration = status === 'completed' ? 'line-through' : 'none';
            }
        } else {
            alert(data.message || 'Errore');
        }
    })
    .catch(function() { alert('Errore di connessione'); });
}

// ==================== APPOINTMENT FUNCTIONS ====================

function saveAppointment() {
    var date = document.getElementById('newApptDate').value;
    var time = document.getElementById('newApptTime').value;
    if (!date || !time) { alert('Data e ora sono obbligatori'); return; }

    var fd = new FormData();
    fd.append('action', 'create');
    fd.append('enrollmentid', '<?php echo $enrollment->id; ?>');
    fd.append('studentid', '<?php echo $userid; ?>');
    fd.append('appointment_date', date);
    fd.append('time_start', time);
    fd.append('duration', document.getElementById('newApptDuration').value);
    fd.append('modality', document.getElementById('newApptModality').value);
    fd.append('location', document.getElementById('newApptLocation').value);
    fd.append('topic', document.getElementById('newApptTopic').value);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_appointment.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function updateApptStatus(apptid, status) {
    if (!status) return;
    var fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('appointmentid', apptid);
    fd.append('status', status);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_appointment.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function deleteAppt(apptid) {
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('appointmentid', apptid);
    fd.append('sesskey', '<?php echo sesskey(); ?>');

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_appointment.php', {
        method: 'POST', body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('appt-' + apptid);
            if (el) el.remove();
        } else {
            alert(data.message || 'Errore');
        }
    })
    .catch(function() { alert('Errore di connessione'); });
}

// ==================== KPI FUNCTIONS ====================

var kpiAjax = '<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_save_kpi.php';

function kpiPost(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('sesskey', '<?php echo sesskey(); ?>');
    for (var k in data) fd.append(k, data[k]);
    fetch(kpiAjax, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) location.reload();
        else alert(result.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function kpiAddApplication() {
    var company = document.getElementById('kpi-app-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    kpiPost('add_application', {
        enrollmentid: '<?php echo $enrollment->id; ?>',
        company_name: company,
        position: document.getElementById('kpi-app-position').value,
        application_type: document.getElementById('kpi-app-type').value,
        application_date: document.getElementById('kpi-app-date').value
    });
}

function kpiAddContact() {
    var company = document.getElementById('kpi-cnt-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    kpiPost('add_contact', {
        enrollmentid: '<?php echo $enrollment->id; ?>',
        company_name: company,
        contact_type: document.getElementById('kpi-cnt-type').value,
        contact_person: document.getElementById('kpi-cnt-person').value,
        contact_date: document.getElementById('kpi-cnt-date').value
    });
}

function kpiAddOpportunity() {
    var company = document.getElementById('kpi-opp-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    kpiPost('add_opportunity', {
        enrollmentid: '<?php echo $enrollment->id; ?>',
        company_name: company,
        opportunity_type: document.getElementById('kpi-opp-type').value,
        opportunity_date: document.getElementById('kpi-opp-date').value
    });
}

function updateAppStatus(id, status) {
    kpiPost('update_app_status', { id: id, status: status });
}

function updateContactOutcome(id, outcome) {
    kpiPost('update_contact_outcome', { id: id, outcome: outcome });
}

function updateOppStatus(id, status) {
    kpiPost('update_opp_status', { id: id, status: status });
}

function deleteKpiEntry(table, id) {
    if (!confirm('Eliminare questo record?')) return;
    kpiPost('delete_entry', { table: table, id: id });
}
</script>

<?php
echo $OUTPUT->footer();
