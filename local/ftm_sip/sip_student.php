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
$tab = optional_param('tab', 'accettazione', PARAM_ALPHANUMEXT);
// Il tab "Foglio Ricerche URC" e stato spostato dentro l'area "mandatory_searches"
// del Piano d'Azione. Se qualcuno arriva con il vecchio URL, redirigi.
if ($tab === 'ricerche') {
    $tab = 'piano';
}

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

// Weekly summary (count entries per area per week) — usato nel tab Piano per inline tracking.
$weekly_summary_global = \local_ftm_sip\sip_manager::get_weekly_summary($enrollment->id);

// Channel usage data — "Canali di ricerca" area.
$sip_channels_def = [
    'email'               => 'Email',
    'portali_lavoro'      => 'Portali del lavoro',
    'siti_aziendali'      => 'Siti aziendali',
    'foglio_ufficiale'    => 'Foglio ufficiale',
    'sito_confederazione' => 'Sito confederazione',
    'linkedin'            => 'LinkedIn',
    'facebook'            => 'Facebook ecc.',
    'giornali'            => 'Giornali / quotidiani / riviste',
    'job_room'            => 'Job-Room',
    'registro_commercio'  => 'Registro di commercio',
    'elenco_telefonico'   => 'Elenco telefonico',
    'contatti_personali'  => 'Contatti personali',
    'agenzie'             => 'Agenzie per il lavoro',
    'bacheche_negozi'     => 'Bacheche negozi',
    'mailing_list'        => 'Mailing-list',
    'porta_a_porta'       => 'Porta a porta',
    'telefonate'          => 'Telefonate',
    'sindacati'           => 'Sindacati',
];
$channel_usage = []; // channel_key => sip_week (int)
$channel_weekly_counts = []; // sip_week => count of channels first activated that week
if ($DB->get_manager()->table_exists('local_ftm_sip_channel_usage')) {
    $cu_rows = $DB->get_records('local_ftm_sip_channel_usage', ['enrollmentid' => $enrollment->id]);
    foreach ($cu_rows as $cu) {
        $channel_usage[$cu->channel_key] = (int)$cu->sip_week;
        $channel_weekly_counts[(int)$cu->sip_week] = ($channel_weekly_counts[(int)$cu->sip_week] ?? 0) + 1;
    }
}

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
$meetings = \local_ftm_sip\sip_manager::get_meetings($enrollment->id);

// All actions (for diary tab).
$all_actions = \local_ftm_sip\sip_manager::get_all_actions($enrollment->id);
$actions_by_meeting = [];
foreach ($all_actions as $act) {
    $actions_by_meeting[$act->meetingid][] = $act;
}

// Pending actions.
$pending_actions = \local_ftm_sip\sip_manager::get_pending_actions($enrollment->id);

// Appointments (for calendar tab).
$appointments = \local_ftm_sip\sip_manager::get_enrollment_appointments($enrollment->id);
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
                <a href="<?php echo new moodle_url('/local/competencymanager/technical_passport.php', ['userid' => $userid, 'courseid' => 0]); ?>" class="sip-btn-outline-teal" target="_blank" title="Apri il Passaporto Tecnico dello studente">
                    <i class="fa fa-id-card"></i> Passaporto Tecnico
                </a>
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
                'idoneo_prioritario' => 'Idoneo Prioritario',
                'idoneo' => 'Idoneo',
                'non_idoneo' => 'Non Idoneo',
                'pending' => 'In sospeso',
            ];
            $decisione_colors = [
                'idoneo_prioritario' => 'green', 'idoneo' => 'yellow', 'non_idoneo' => 'red', 'pending' => 'gray',
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
                        <?php echo get_string('eligibility_total', 'local_ftm_sip'); ?>: <?php echo $elig_totale; ?>/36
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
                            <?php for ($dot = 1; $dot <= 6; $dot++): ?>
                            <span style="width:12px; height:12px; border-radius:50%; display:inline-block; <?php echo ($dot <= $cdata['value']) ? 'background:#0891B2;' : 'background:#DEE2E6;'; ?>"></span>
                            <?php endfor; ?>
                        </span>
                        <span style="color:#6b7280; font-size:11px;">(<?php echo $cdata['value']; ?>/6)</span>
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

                <!-- Bottone Richiesta Attivazione CI -->
                <?php if ($canedit && in_array($elig_decisione, ['idoneo', 'idoneo_prioritario'])): ?>
                <div style="margin-top:14px; padding-top:12px; border-top:1px solid #e5e7eb;">
                    <button onclick="richiediAttivazione()" id="btn-richiedi-ci"
                            style="padding:10px 20px; background:#0891B2; color:#fff; border:none; border-radius:6px; font-size:0.88rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="fa fa-envelope"></i> Richiedi attivazione CI alla segreteria
                    </button>
                    <span id="richiedi-feedback" style="font-size:0.82rem; margin-top:6px; display:block;"></span>
                </div>
                <script>
                function richiediAttivazione() {
                    var btn = document.getElementById('btn-richiedi-ci');
                    var fb = document.getElementById('richiedi-feedback');
                    if (!confirm('Inviare la richiesta di attivazione CI a segreteria@f3m.ch per ' + <?php echo json_encode(fullname($student)); ?> + '?')) return;

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Invio in corso...';

                    var fd = new FormData();
                    fd.append('sesskey', M.cfg.sesskey);
                    fd.append('action', 'request_activation');
                    fd.append('userid', <?php echo $userid; ?>);
                    fd.append('enrollmentid', <?php echo $enrollment->id; ?>);

                    fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_request_activation.php'))->out(false); ?>', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        btn.disabled = false;
                        if (resp.success) {
                            btn.innerHTML = '<i class="fa fa-check"></i> Richiesta inviata!';
                            btn.style.background = '#28a745';
                            fb.textContent = resp.message || 'Email inviata a segreteria@f3m.ch';
                            fb.style.color = '#28a745';
                        } else {
                            btn.innerHTML = '<i class="fa fa-envelope"></i> Richiedi attivazione CI alla segreteria';
                            fb.textContent = 'Errore: ' + resp.message;
                            fb.style.color = '#dc3545';
                        }
                    })
                    .catch(function(e) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-envelope"></i> Richiedi attivazione CI alla segreteria';
                        fb.textContent = 'Errore connessione';
                        fb.style.color = '#dc3545';
                    });
                }
                </script>
                <?php endif; ?>
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
        <a href="?userid=<?php echo $userid; ?>&tab=accettazione" class="sip-tab <?php echo $tab === 'accettazione' ? 'active' : ''; ?>" data-tab="accettazione">
            <i class="fa fa-check-square-o"></i> Accettazione
        </a>
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
        <a href="?userid=<?php echo $userid; ?>&tab=tracking" class="sip-tab <?php echo $tab === 'tracking' ? 'active' : ''; ?>" data-tab="tracking">
            <i class="fa fa-table"></i> Tracking Settimanale
        </a>
        <a href="?userid=<?php echo $userid; ?>&tab=roadmap" class="sip-tab <?php echo $tab === 'roadmap' ? 'active' : ''; ?>" data-tab="roadmap">
            <i class="fa fa-road"></i> Roadmap
        </a>
    </div>

    <!-- ======== TAB CONTENT ======== -->
    <div class="sip-tab-content">

        <?php if ($tab === 'piano'): ?>
        <!-- ==================== TAB 1: PIANO D'AZIONE ==================== -->

        <style>
            /* Griglia 3 colonne x 4 righe per le 12 aree */
            .sip-area-pills {
                display:grid;
                grid-template-columns: repeat(3, 1fr);
                gap:8px;
                margin-bottom:18px;
                padding:12px;
                background:#f8fafc;
                border-radius:10px;
                border:1px solid #e5e7eb;
            }
            @media (max-width: 900px) {
                .sip-area-pills { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 600px) {
                .sip-area-pills { grid-template-columns: 1fr; }
            }
            .sip-area-pill {
                display:grid;
                grid-template-columns: 26px 28px 1fr 30px;
                align-items:center;
                gap:8px;
                padding:8px 10px;
                background:#fff;
                border:2px solid #e5e7eb;
                border-radius:8px;
                cursor:pointer;
                font-size:0.85rem;
                font-weight:600;
                color:#374151;
                transition:all 0.2s;
                min-height:42px;
            }
            .sip-area-pill:hover {
                border-color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;
                color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;
                transform:translateY(-1px);
            }
            .sip-area-pill.active {
                background:<?php echo LOCAL_FTM_SIP_COLOR; ?>;
                color:white;
                border-color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;
            }
            .sip-area-pill.active .sip-pill-num,
            .sip-area-pill.active .sip-pill-level {
                background:rgba(255,255,255,0.25); color:white;
            }
            .sip-pill-num {
                background:#f3f4f6; color:#6b7280;
                width:24px; height:24px; border-radius:50%;
                display:inline-flex; align-items:center; justify-content:center;
                font-weight:700; font-size:0.78rem;
                flex-shrink:0;
            }
            .sip-pill-icon {
                color:#fff;
                width:26px; height:26px; border-radius:6px;
                display:inline-flex; align-items:center; justify-content:center;
                font-size:0.9rem;
                box-shadow:0 1px 3px rgba(0,0,0,0.15);
                flex-shrink:0;
            }
            .sip-pill-name {
                white-space:nowrap;
                overflow:hidden;
                text-overflow:ellipsis;
            }
            .sip-area-pill.active .sip-pill-icon {
                box-shadow:0 0 0 2px rgba(255,255,255,0.5);
            }
            .sip-pill-level {
                background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;
                padding:2px 8px; border-radius:10px; font-size:0.72rem; font-weight:700;
                text-align:center;
                justify-self:end;
            }
            /* Tutte le card area nascoste tranne quella attiva */
            .sip-area-card { display:none !important; }
            .sip-area-card.sip-area-shown { display:block !important; }
            /* La card mostrata sempre espansa */
            .sip-area-card.sip-area-shown .sip-area-body { display:block !important; }
            .sip-area-card.sip-area-shown .sip-area-chevron { display:none; }
            .sip-area-card.sip-area-shown .sip-area-header { cursor:default; }
            /* Tracker bars: solo quello attivo è visibile */
            .sip-tracker-bar { display:none; }
            .sip-tracker-bar.sip-tracker-shown { display:block; }
            /* Pannello inline tabella entries settimana */
            .sip-week-inline { background:#fff; border:2px solid <?php echo LOCAL_FTM_SIP_COLOR; ?>; border-radius:10px; padding:14px; margin-top:10px; display:none; }
            .sip-week-inline.shown { display:block; }
            .sip-week-inline-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #e5e7eb; }
            .sip-week-inline-title { font-weight:700; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; font-size:1rem; }
            .sip-week-inline table { width:100%; border-collapse:collapse; font-size:0.78rem; }
            .sip-week-inline th { background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>; color:#374151; padding:6px 4px; border:1px solid #e5e7eb; font-weight:600; text-align:left; }
            .sip-week-inline td { padding:4px; border:1px solid #f3f4f6; vertical-align:middle; }
            .sip-week-inline input[type="text"], .sip-week-inline input[type="email"], .sip-week-inline input[type="tel"], .sip-week-inline input[type="date"], .sip-week-inline select {
                width:100%; padding:4px 6px; border:1px solid #cbd5e1; border-radius:3px; font-size:0.78rem;
            }
            .sip-week-inline input[type="checkbox"] { transform:scale(1.1); }
            .sip-week-inline .row-add { background:#fffbeb; }
            .sip-week-inline .btn-row {
                background:#fff; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; cursor:pointer;
                color:#374151; font-weight:600;
            }
            .sip-week-inline .btn-row.save { background:<?php echo LOCAL_FTM_SIP_COLOR; ?>; color:#fff; border-color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; }
            .sip-week-inline .btn-row.delete { color:#dc2626; }
            .sip-week-inline .btn-row.add { background:#059669; color:#fff; border-color:#059669; padding:6px 14px; }
        </style>

        <?php
        // Hardcoded labels (fallback se lang file vecchio sul server).
        $piano_labels = [
            'target_companies' => [
                'name' => 'Lista aziende Target',
                'desc' => 'Redigere una lista di 30-50 aziende che sono in linea con il mio profilo',
                'obj'  => 'Creare e mantenere una lista esaustiva di aziende target',
                'verify' => 'Numero aziende target identificate',
            ],
            'mandatory_searches' => [
                'name' => 'Numero ricerche obbligatorie',
                'desc' => 'Mantenere e aumentare il numero di ricerche obbligatorie settimanali',
                'obj'  => 'Aumentare il numero e la qualità delle ricerche di lavoro',
                'verify' => 'Numero ricerche effettuate per settimana',
            ],
            'search_channels' => [
                'name' => 'Canali di ricerca',
                'desc' => 'Aumentare i canali di ricerca utilizzati dalla PCI (foglio ufficiale, giornali, motori di ricerca…)',
                'obj'  => 'Aumentare i canali di ricerca utilizzati dalla PCI',
                'verify' => 'Numero canali attivati e utilizzati',
            ],
            'social_network' => [
                'name' => 'Socialnetwork',
                'desc' => 'Utilizzare i social network in maniera appropriata e idonea alla ricerca di lavoro',
                'obj'  => 'Attivare e utilizzare LinkedIn, Facebook Lavoro, ecc.',
                'verify' => 'Profili attivati e attività di ricerca sui social',
            ],
            'personal_network' => [
                'name' => 'Rete personale',
                'desc' => 'Utilizzo della propria rete personale per la ricerca d\'impiego',
                'obj'  => 'Attivare e monitorare la propria rete di contatti',
                'verify' => 'Numero contatti personali attivati',
            ],
            'targeted_applications' => [
                'name' => 'Annunci di lavoro mirati',
                'desc' => 'Aumentare il numero candidature ad annuncio di lavoro mirati',
                'obj'  => 'Aumentare il numero di candidature ad annunci mirati',
                'verify' => 'Numero candidature inviate ad annunci mirati',
            ],
            'unsolicited_applications' => [
                'name' => 'Autocandidature',
                'desc' => 'Aumentare il numero candidature senza annuncio pubblico',
                'obj'  => 'Ampliare le opportunità tramite autocandidature',
                'verify' => 'Numero autocandidature inviate',
            ],
            'agencies_urc' => [
                'name' => 'Agenzie e URC',
                'desc' => 'Aumentare il numero di agenzie utilizzate e utilizzo dell\'URC',
                'obj'  => 'Attivare nuove agenzie interinali e utilizzare Job-Room',
                'verify' => 'Numero agenzie contattate e iscrizioni attive',
            ],
            'interview_training' => [
                'name' => 'Training colloqui',
                'desc' => 'Effettuare colloqui di prova/formazione mirati su possibili opportunità',
                'obj'  => 'Prepararsi ai colloqui con simulazioni e feedback',
                'verify' => 'Numero colloqui di prova effettuati',
            ],
            'stage_trials' => [
                'name' => 'Stage / giorni di prova',
                'desc' => 'Promuovere e utilizzare gli strumenti di stage e giorni di prova',
                'obj'  => 'Ottenere opportunità di stage o giorni di prova',
                'verify' => 'Numero stage/prove attivati',
            ],
            'strategy_improvement' => [
                'name' => 'Miglioramento strategia di ricerca',
                'desc' => 'Il coach valuta l\'evoluzione della PCI nel migliorare la propria strategia',
                'obj'  => 'Migliorare progressivamente le strategie di ricerca d\'impiego',
                'verify' => 'Valutazione coach settimanale (1-10)',
            ],
            'growing_autonomy' => [
                'name' => 'Autonomia crescente',
                'desc' => 'Il coach valuta l\'evoluzione della PCI nella gestione autonoma della ricerca',
                'obj'  => 'Raggiungere piena autonomia nella gestione della ricerca di lavoro',
                'verify' => 'Valutazione coach settimanale (1-10)',
            ],
        ];

        // Pre-calcola dati pillole.
        $area_keys_list = array_keys($areas_def);
        $first_area_key = $area_keys_list[0] ?? '';
        ?>

        <!-- ================== PILLOLE ORIZZONTALI 12 AREE ================== -->
        <div class="sip-area-pills">
            <?php $pn = 0; foreach ($areas_def as $area_key => $area_info):
                $pn++;
                $plan_item_p = isset($action_plan[$area_key]) ? $action_plan[$area_key] : null;
                $level_p = $plan_item_p ? (int)($plan_item_p->level_current ?? 0) : 0;
                $name_p = isset($piano_labels[$area_key]) ? $piano_labels[$area_key]['name']
                    : get_string($area_info['name'], 'local_ftm_sip');
                $icon_p = $area_info['icon'] ?? 'fa-circle';
                $color_p = $area_info['color'] ?? LOCAL_FTM_SIP_COLOR;
            ?>
            <div class="sip-area-pill <?php echo $area_key === $first_area_key ? 'active' : ''; ?>"
                 data-target="<?php echo s($area_key); ?>"
                 data-color="<?php echo s($color_p); ?>"
                 title="<?php echo s($name_p); ?>">
                <span class="sip-pill-num"><?php echo $pn; ?></span>
                <span class="sip-pill-icon" style="background:<?php echo $color_p; ?>;"><i class="fa <?php echo s($icon_p); ?>"></i></span>
                <span class="sip-pill-name"><?php echo s($name_p); ?></span>
                <span class="sip-pill-level"><?php echo $level_p; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ================== TRACKER BAR PER AREA SELEZIONATA ================== -->
        <div class="sip-tracker-bars-container">
            <?php foreach ($areas_def as $area_key => $area_info):
                $area_type_check = $area_info['type'] ?? 'quantitative';
                $name_t = isset($piano_labels[$area_key]) ? $piano_labels[$area_key]['name']
                    : get_string($area_info['name'], 'local_ftm_sip');
                $is_active_bar = ($area_key === $first_area_key);
            ?>
            <div class="sip-tracker-bar <?php echo $is_active_bar ? 'sip-tracker-shown' : ''; ?>" data-area="<?php echo s($area_key); ?>">
                <?php if ($area_type_check === 'quantitative'): ?>
                    <!-- TRACKING 10 SETTIMANE per quest'area -->
                    <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; margin-bottom:14px;">
                        <div style="font-weight:700; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; margin-bottom:10px; font-size:0.95rem;">
                            📅 Tracking settimanale — <?php echo s($name_t); ?>
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(10, 1fr) auto; gap:6px; align-items:stretch;">
                            <?php for ($w = 1; $w <= 10; $w++):
                                if ($area_key === 'search_channels') {
                                    $w_count = $channel_weekly_counts[$w] ?? 0;
                                } else {
                                    $w_count = isset($weekly_summary_global[$area_key][$w]) ? (int)$weekly_summary_global[$area_key][$w] : 0;
                                }
                                $w_color = $w_count > 0 ? LOCAL_FTM_SIP_COLOR : '#cbd5e1';
                                $w_bg = $w_count > 0 ? LOCAL_FTM_SIP_COLOR_BG : '#f9fafb';
                            ?>
                            <button type="button" class="sip-week-btn"
                                    data-week="<?php echo $w; ?>"
                                    data-area="<?php echo s($area_key); ?>"
                                    data-name="<?php echo s($name_t); ?>"
                                    style="border:2px solid <?php echo $w_color; ?>; background:<?php echo $w_bg; ?>; padding:8px 4px; border-radius:6px; cursor:pointer; transition:all 0.2s; color:#222;"
                                    onmouseover="this.style.background='<?php echo LOCAL_FTM_SIP_COLOR; ?>'; this.style.color='white';"
                                    onmouseout="this.style.background='<?php echo $w_bg; ?>'; this.style.color='#222';">
                                <div style="font-size:0.7rem; font-weight:600; color:#6b7280;">Sett.</div>
                                <div style="font-size:1rem; font-weight:700; color:#222;"><?php echo $w; ?></div>
                                <div style="font-size:0.85rem; font-weight:700; color:<?php echo $w_count > 0 ? LOCAL_FTM_SIP_COLOR : '#9ca3af'; ?>;">
                                    <?php echo $w_count; ?>
                                </div>
                            </button>
                            <?php endfor; ?>
                            <?php
                            // Pulsante Totale (11°) — mostra la somma di tutte le settimane.
                            $area_total = ($area_key === 'search_channels')
                                ? array_sum($channel_weekly_counts)
                                : array_sum($weekly_summary_global[$area_key] ?? []);
                            $tot_color = $area_total > 0 ? LOCAL_FTM_SIP_COLOR : '#cbd5e1';
                            $tot_bg    = $area_total > 0 ? LOCAL_FTM_SIP_COLOR_BG : '#f9fafb';
                            ?>
                            <button type="button" class="sip-week-btn"
                                    data-week="0"
                                    data-area="<?php echo s($area_key); ?>"
                                    data-name="<?php echo s($name_t); ?>"
                                    title="Mostra tutte le voci (tutte le settimane)"
                                    style="border:2px dashed <?php echo $tot_color; ?>; background:<?php echo $tot_bg; ?>; padding:8px 8px; border-radius:6px; cursor:pointer; transition:all 0.2s; color:#222; min-width:54px;"
                                    onmouseover="this.style.background='<?php echo LOCAL_FTM_SIP_COLOR; ?>'; this.style.color='white';"
                                    onmouseout="this.style.background='<?php echo $tot_bg; ?>'; this.style.color='#222';">
                                <div style="font-size:0.7rem; font-weight:600; color:#6b7280;">Totale</div>
                                <div style="font-size:0.95rem; font-weight:700; color:#222;">&#931;</div>
                                <div style="font-size:0.85rem; font-weight:700; color:<?php echo $area_total > 0 ? LOCAL_FTM_SIP_COLOR : '#9ca3af'; ?>;">
                                    <?php echo $area_total; ?>
                                </div>
                            </button>
                        </div>
                        <div style="font-size:0.75rem; color:#6b7280; margin-top:6px; font-style:italic;">
                            <?php if ($area_key === 'search_channels'): ?>
                            Clicca su una settimana per selezionare i canali attivati. Un canale attivato non può essere riselezionato nelle settimane successive.
                            <?php else: ?>
                            Clicca su una settimana per inserire le aziende/contatti trovati. I valori si propagano in <b>Tracking Settimanale</b> e <b>KPI</b>.
                            <?php endif; ?>
                        </div>

                        <?php if ($area_key === 'mandatory_searches'):
                            $urc_entries = \local_ftm_sip\sip_manager::get_search_entries($enrollment->id, 'mandatory_searches');
                            $urc_proofs = method_exists('\local_ftm_sip\sip_manager', 'get_search_proofs')
                                ? \local_ftm_sip\sip_manager::get_search_proofs($enrollment->id) : [];
                        ?>
                        <details style="margin-top:14px; background:#fafafa; border:1px solid #e5e7eb; border-radius:6px;">
                            <summary style="padding:10px 14px; cursor:pointer; font-weight:700; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;">
                                📋 Foglio Ricerche URC ufficiale (<?php echo count($urc_entries); ?> entries) — clicca per espandere
                            </summary>
                            <div style="padding:14px;">
                                <p style="font-size:0.82rem; color:#6b7280; margin:0 0 12px;">
                                    Modulo "Prova degli sforzi personali intrapresi per trovare lavoro" — Formato URC ufficiale.
                                </p>
                                <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:collapse; font-size:0.78rem;">
                                    <thead>
                                        <tr style="background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>;">
                                            <th style="padding:6px; border:1px solid #dee2e6;">Sett.</th>
                                            <th style="padding:6px; border:1px solid #dee2e6;">Data</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; min-width:160px;">Ditta / Indirizzo / Email</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; min-width:80px;">Impiego</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">URC</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">TP</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">Parz</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">Let</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">Pers</th>
                                            <th style="padding:6px; border:1px solid #dee2e6; text-align:center;">Tel</th>
                                            <th style="padding:6px; border:1px solid #dee2e6;">Risultato</th>
                                            <?php if ($canedit): ?><th style="padding:6px; border:1px solid #dee2e6; width:30px;"></th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($urc_entries)): ?>
                                        <tr><td colspan="<?php echo $canedit ? 12 : 11; ?>" style="padding:20px; text-align:center; color:#9ca3af;">Nessuna ricerca registrata. Clicca su una settimana sopra per aggiungere.</td></tr>
                                    <?php else: foreach ($urc_entries as $e): ?>
                                        <tr style="border-bottom:1px solid #f3f4f6;">
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center; font-weight:600;"><?php echo (int)$e->sip_week; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6;"><?php echo $e->entry_date ? userdate($e->entry_date, '%d.%m.%Y') : '-'; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; font-size:0.75rem;">
                                                <?php echo s($e->company_name ?: ''); ?>
                                                <?php if ($e->company_address): ?><br><?php echo s($e->company_address); ?><?php endif; ?>
                                                <?php if ($e->company_email): ?><br><?php echo s($e->company_email); ?><?php endif; ?>
                                            </td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6;"><?php echo s($e->position ?: ''); ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->urc_assigned ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->occupation_fulltime ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->occupation_parttime ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_letter ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_person ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_phone ? '&times;' : ''; ?></td>
                                            <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center; font-size:0.72rem;"><?php echo s($e->result ?? ''); ?></td>
                                            <?php if ($canedit): ?>
                                            <td style="padding:4px; border:1px solid #f3f4f6;">
                                                <button onclick="if(confirm('Eliminare questa voce URC?')){deleteUrcInline(<?php echo $e->id; ?>)}" style="background:none; border:none; color:#dc3545; cursor:pointer;">&#128465;</button>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                                </div>
                                <div style="margin-top:14px; padding:10px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;">
                                    <h6 style="margin:0 0 6px; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; font-size:0.85rem;">
                                        <i class="fa fa-file-pdf-o"></i> Documenti caricati (PDF Job-Room)
                                    </h6>
                                    <?php if (!empty($urc_proofs)): ?>
                                    <ul style="margin:0; padding-left:18px; font-size:0.82rem;">
                                        <?php foreach ($urc_proofs as $p): ?>
                                        <li><?php echo s($p->filename); ?> — <?php echo s($p->month_year); ?> (<?php echo userdate($p->timecreated, '%d/%m/%Y'); ?>)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php else: ?>
                                    <p style="font-size:0.78rem; color:#9ca3af; margin:4px 0;">Nessun documento caricato.</p>
                                    <?php endif; ?>
                                    <?php if ($canedit): ?>
                                    <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                        <input type="month" id="proof-month-inline" value="<?php echo date('Y-m'); ?>" style="padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                                        <input type="file" id="proof-file-inline" accept=".pdf,.jpg,.jpeg,.png" style="font-size:0.82rem;">
                                        <button onclick="uploadProofInline()" class="sip-btn-save" style="font-size:0.82rem; padding:6px 14px;">
                                            <i class="fa fa-upload"></i> Carica
                                        </button>
                                    </div>
                                    <?php if (!empty($urc_proofs)): ?>
                                    <div style="margin-top:10px;">
                                        <button onclick="SipProofParser.open(<?php echo (int)$enrollment->id; ?>)"
                                                style="background:#7c3aed; color:white; border:none; padding:8px 18px; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                                            <i class="fa fa-magic"></i> Analizza documenti con AI (<?php echo count($urc_proofs); ?> file)
                                        </button>
                                        <span style="font-size:0.75rem; color:#6b7280; margin-left:8px;">Legge i PDF/JPG e importa le ricerche nella tabella URC</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </details>

                        <!-- ===== MODAL: AI Proof Parser ===== -->
                        <div id="sipProofParserModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center;">
                            <div style="background:#fff; border-radius:12px; max-width:900px; width:95%; max-height:90vh; overflow-y:auto; padding:24px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <h3 style="margin:0; color:#7c3aed; font-size:1.1rem;"><i class="fa fa-magic"></i> Analisi AI — Foglio Ricerche URC</h3>
                                    <button onclick="SipProofParser.close()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#6b7280;">&times;</button>
                                </div>

                                <!-- Step 1: loading -->
                                <div id="spp-loading" style="display:none; text-align:center; padding:30px 0;">
                                    <div style="font-size:2rem; margin-bottom:12px;">🤖</div>
                                    <div style="font-size:1rem; font-weight:600; color:#7c3aed;">Analisi in corso…</div>
                                    <div style="font-size:0.85rem; color:#6b7280; margin-top:6px;">GPT-4o sta leggendo i tuoi documenti. Può richiedere 15-30 secondi.</div>
                                    <div style="margin-top:16px; width:60%; margin-left:auto; margin-right:auto; height:6px; background:#e5e7eb; border-radius:3px; overflow:hidden;">
                                        <div id="spp-progress-bar" style="height:100%; background:#7c3aed; border-radius:3px; animation:sppSlide 2s ease-in-out infinite;"></div>
                                    </div>
                                </div>

                                <!-- Step 2: preview results -->
                                <div id="spp-results" style="display:none;">
                                    <div id="spp-summary" style="background:#f3e8ff; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.88rem; color:#5b21b6;"></div>
                                    <div id="spp-errors" style="display:none; background:#FEF2F2; border:1px solid #FECACA; border-radius:6px; padding:10px 14px; margin-bottom:14px; font-size:0.82rem; color:#DC2626;"></div>
                                    <div style="overflow-x:auto;">
                                        <table style="width:100%; border-collapse:collapse; font-size:0.78rem;" id="spp-preview-table">
                                            <thead>
                                                <tr style="background:#f3e8ff;">
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">✓</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Sett.</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Data</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; min-width:140px;">Ditta</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Indirizzo</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Email</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Impiego</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">URC</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">TP</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">Parz</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">Let</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">Pers</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; text-align:center;">Tel</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb;">Risultato</th>
                                                    <th style="padding:6px; border:1px solid #e5e7eb; color:#6b7280;">File</th>
                                                </tr>
                                            </thead>
                                            <tbody id="spp-tbody"></tbody>
                                        </table>
                                    </div>
                                    <div style="margin-top:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                        <button onclick="SipProofParser.confirmImport()" id="spp-import-btn"
                                                style="background:#7c3aed; color:white; border:none; padding:10px 24px; border-radius:6px; font-weight:700; cursor:pointer; font-size:0.95rem;">
                                            <i class="fa fa-check"></i> <span id="spp-import-label">Importa tutte</span>
                                        </button>
                                        <button onclick="SipProofParser.close()"
                                                style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:10px 18px; border-radius:6px; cursor:pointer;">
                                            Annulla
                                        </button>
                                        <span id="spp-import-feedback" style="font-size:0.85rem;"></span>
                                    </div>
                                </div>

                                <!-- Step 3: done -->
                                <div id="spp-done" style="display:none; text-align:center; padding:24px 0;">
                                    <div style="font-size:2.5rem; margin-bottom:10px;">✅</div>
                                    <div id="spp-done-msg" style="font-size:1rem; font-weight:600; color:#059669;"></div>
                                    <button onclick="location.reload()" style="margin-top:14px; background:#059669; color:white; border:none; padding:10px 24px; border-radius:6px; cursor:pointer; font-weight:600;">
                                        <i class="fa fa-refresh"></i> Ricarica pagina
                                    </button>
                                </div>
                            </div>
                        </div>
                        <style>
                        @keyframes sppSlide {
                            0%   { width:10%; margin-left:0; }
                            50%  { width:60%; margin-left:20%; }
                            100% { width:10%; margin-left:90%; }
                        }
                        </style>
                        <script>
                        var SipProofParser = {
                            enrollmentid: 0,
                            entries: [],

                            open: function(enrollmentid) {
                                this.enrollmentid = enrollmentid;
                                this.entries = [];
                                document.getElementById('sipProofParserModal').style.display = 'flex';
                                document.getElementById('spp-loading').style.display = 'block';
                                document.getElementById('spp-results').style.display = 'none';
                                document.getElementById('spp-done').style.display = 'none';
                                this.parse();
                            },

                            close: function() {
                                document.getElementById('sipProofParserModal').style.display = 'none';
                            },

                            parse: function() {
                                var self = this;
                                var fd = new FormData();
                                fd.append('sesskey', M.cfg.sesskey);
                                fd.append('action', 'parse');
                                fd.append('enrollmentid', this.enrollmentid);

                                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_parse_proofs.php', {method:'POST', body:fd})
                                .then(function(r){ return r.json(); })
                                .then(function(resp) {
                                    document.getElementById('spp-loading').style.display = 'none';
                                    if (!resp.success) {
                                        alert('Errore: ' + resp.message);
                                        self.close();
                                        return;
                                    }
                                    self.entries = resp.data.entries || [];
                                    self.renderPreview(resp.data);
                                    document.getElementById('spp-results').style.display = 'block';
                                })
                                .catch(function(err) {
                                    document.getElementById('spp-loading').style.display = 'none';
                                    alert('Errore di rete: ' + err.message);
                                    self.close();
                                });
                            },

                            renderPreview: function(data) {
                                var entries = data.entries || [];
                                var errors  = data.errors  || [];

                                document.getElementById('spp-summary').innerHTML =
                                    '<strong>' + entries.length + ' ricerche trovate</strong> in ' + data.file_count + ' documenti. '
                                    + 'Verifica e deseleziona le righe da non importare, poi clicca "Importa".';

                                if (errors.length > 0) {
                                    var errDiv = document.getElementById('spp-errors');
                                    errDiv.style.display = 'block';
                                    errDiv.innerHTML = '<strong>Avvisi:</strong><ul style="margin:4px 0 0 16px;">'
                                        + errors.map(function(e){ return '<li>' + e + '</li>'; }).join('') + '</ul>';
                                }

                                var tbody = document.getElementById('spp-tbody');
                                tbody.innerHTML = '';

                                var resultLabels = {positive:'✅ Positivo', negative:'❌ Rifiuto', pending:'⏳ In attesa'};
                                var resultColors = {positive:'#d1fae5', negative:'#fee2e2', pending:'#fefce8'};

                                entries.forEach(function(e, idx) {
                                    var tr = document.createElement('tr');
                                    tr.style.borderBottom = '1px solid #f3f4f6';
                                    var resBg = resultColors[e.result] || '#fff';
                                    tr.innerHTML =
                                        '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">'
                                            + '<input type="checkbox" class="spp-row-check" data-idx="'+idx+'" checked></td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center; font-weight:600;">' + (e.sip_week||1) + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; white-space:nowrap;">' + (e.entry_date||'-') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; font-weight:500;">' + self.esc(e.company_name) + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; font-size:0.73rem;">' + self.esc(e.company_address||'') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; font-size:0.73rem;">' + self.esc(e.company_email||'') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb;">' + self.esc(e.position||'') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.urc_assigned ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.occupation_fulltime ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.occupation_parttime ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.method_letter ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.method_person ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; text-align:center;">' + (e.method_phone ? '×' : '') + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; background:'+resBg+'; font-size:0.73rem;">' + (resultLabels[e.result]||e.result) + '</td>'
                                        + '<td style="padding:4px 6px; border:1px solid #e5e7eb; font-size:0.7rem; color:#9ca3af;">' + self.esc(e.source_file||'') + '</td>';
                                    tbody.appendChild(tr);
                                });

                                document.getElementById('spp-import-label').textContent =
                                    'Importa ' + entries.length + ' ricerche';
                            },

                            confirmImport: function() {
                                // Collect only checked rows.
                                var checked = document.querySelectorAll('.spp-row-check:checked');
                                var toImport = [];
                                checked.forEach(function(cb) {
                                    var idx = parseInt(cb.dataset.idx);
                                    if (!isNaN(idx) && SipProofParser.entries[idx]) {
                                        toImport.push(SipProofParser.entries[idx]);
                                    }
                                });
                                if (toImport.length === 0) {
                                    alert('Nessuna riga selezionata.');
                                    return;
                                }
                                var btn = document.getElementById('spp-import-btn');
                                btn.disabled = true;
                                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Importazione…';

                                var fd = new FormData();
                                fd.append('sesskey', M.cfg.sesskey);
                                fd.append('action', 'import');
                                fd.append('enrollmentid', SipProofParser.enrollmentid);
                                fd.append('entries', JSON.stringify(toImport));

                                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_parse_proofs.php', {method:'POST', body:fd})
                                .then(function(r){ return r.json(); })
                                .then(function(resp) {
                                    document.getElementById('spp-results').style.display = 'none';
                                    document.getElementById('spp-done').style.display = 'block';
                                    document.getElementById('spp-done-msg').textContent =
                                        resp.success
                                            ? (resp.data.imported + ' ricerche importate nel Foglio URC!')
                                            : ('Errore: ' + resp.message);
                                })
                                .catch(function(err) {
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fa fa-check"></i> Importa';
                                    alert('Errore di rete: ' + err.message);
                                });
                            },

                            esc: function(s) {
                                if (!s) return '';
                                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                            }
                        };
                        </script>
                        <?php endif; ?>
                    </div>
                <?php else: // area qualitative ?>
                    <div style="background:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; padding:12px 16px; margin-bottom:14px; font-size:0.88rem;">
                        <i class="fa fa-info-circle" style="color:#f59e0b;"></i>
                        Quest'area è <b>qualitativa</b>: la valutazione è espressa dal coach con un voto 1-10 settimanale (vedi tab <b>Tracking Settimanale</b>) — non si registrano contatti/aziende.
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

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

            // Use hardcoded labels (Excel-fedeli) with fallback to lang strings.
            if (isset($piano_labels[$area_key])) {
                $area_name = $piano_labels[$area_key]['name'];
                $area_desc = $piano_labels[$area_key]['desc'];
            } else {
                $area_name = get_string($area_info['name'], 'local_ftm_sip');
                $area_desc = get_string($area_info['desc'], 'local_ftm_sip');
            }
            $area_color = $area_info['color'];
            $area_icon = $area_info['icon'];
        ?>

        <div class="sip-area-card<?php echo $area_key === $first_area_key ? ' sip-area-shown' : ''; ?>" id="sip-area-<?php echo $area_key; ?>" data-planid="<?php echo $plan_id; ?>" data-areakey="<?php echo $area_key; ?>">
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

                <?php if ($area_key === 'search_channels'): ?>
                <!-- Pannello inline canali di ricerca (apre cliccando un bottone settimana) -->
                <div class="sip-week-inline sip-channel-inline" id="sip-inline-search_channels" data-area="search_channels" style="margin-top:14px;">
                    <div class="sip-week-inline-header">
                        <div class="sip-week-inline-title">📡 Canali di ricerca — <span class="sip-inline-week-num">-</span></div>
                        <button type="button" class="btn-row" onclick="SipChannelTracker.close()">× Chiudi</button>
                    </div>
                    <div class="sip-channel-feedback" style="font-size:0.85rem; margin-bottom:8px;"></div>
                    <!-- JS popola la griglia canali -->
                    <div class="sip-channel-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:8px; padding:4px 0 10px;">
                    </div>
                    <div style="font-size:0.75rem; color:#6b7280; font-style:italic; margin-top:4px;">
                        Seleziona i canali usati questa settimana. Un canale attivato in una settimana precedente appare bloccato.
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($area_key, ['target_companies', 'mandatory_searches'], true)):
                    // Pannello inline tabella aziende — usato sia per "Lista aziende Target" che per "Numero ricerche obbligatorie".
                    $panel_title = $area_key === 'target_companies' ? '📋 Aziende / contatti' : '🔍 Ricerche obbligatorie / contatti';
                ?>
                <!-- Pannello inline tabella entries (apre cliccando un bottone settimana sopra) -->
                <div class="sip-week-inline" id="sip-inline-<?php echo s($area_key); ?>" data-area="<?php echo s($area_key); ?>" style="margin-top:14px;">
                    <div class="sip-week-inline-header">
                        <div class="sip-week-inline-title"><?php echo $panel_title; ?> — <span class="sip-inline-week-num">-</span></div>
                        <button type="button" class="btn-row" onclick="SipInlineTracker.close('<?php echo s($area_key); ?>')">× Chiudi</button>
                    </div>
                    <div class="sip-inline-feedback" style="font-size:0.85rem; margin-bottom:8px;"></div>
                    <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:30px;">#</th>
                                <th style="min-width:140px;">Nominativo realtà *</th>
                                <th style="min-width:110px;">Contatto</th>
                                <th style="min-width:130px;">Email</th>
                                <th style="min-width:90px;">Tel</th>
                                <th style="min-width:130px;">Indirizzo / Luogo</th>
                                <th style="min-width:100px;">Ruolo</th>
                                <th style="min-width:110px;">Data</th>
                                <th style="width:36px;" title="Sito Azienda">SAZ</th>
                                <th style="width:36px;" title="Sito Agenzia">SAG</th>
                                <th style="width:36px;" title="Portale">POR</th>
                                <th style="width:36px;" title="Concorso">CON</th>
                                <th style="width:36px;" title="Giornale">GIO</th>
                                <th style="width:80px;">Azione</th>
                            </tr>
                        </thead>
                        <tbody class="sip-inline-rows">
                            <!-- Popolato da JS al click di un bottone settimana -->
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php endif; ?>


                <!-- Level selectors: two columns -->
                <div class="sip-level-columns" style="display:none;">
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
                <?php
                // Pulisce valori [[...]] salvati quando il lang file era incompleto.
                $raw_obj = $plan_item->objective ?? '';
                if (preg_match('/^\[\[.+\]\]$/', trim($raw_obj)) && isset($piano_labels[$area_key]['obj'])) {
                    $raw_obj = $piano_labels[$area_key]['obj'];
                }
                $raw_ver = $plan_item->verification ?? '';
                if (preg_match('/^\[\[.+\]\]$/', trim($raw_ver)) && isset($piano_labels[$area_key]['verify'])) {
                    $raw_ver = $piano_labels[$area_key]['verify'];
                }
                ?>
                <div class="sip-field-group">
                    <label>OBIETTIVI</label>
                    <textarea rows="2" id="objective_<?php echo $area_key; ?>"
                              <?php echo !$canedit ? 'disabled' : ''; ?>
                              onchange="SipStudent.markDirty('<?php echo $area_key; ?>')"
                    ><?php echo s($raw_obj); ?></textarea>
                </div>

                <div class="sip-field-group">
                    <label>AZIONI PIANIFICATE</label>
                    <textarea rows="2" id="actions_<?php echo $area_key; ?>"
                              <?php echo !$canedit ? 'disabled' : ''; ?>
                              onchange="SipStudent.markDirty('<?php echo $area_key; ?>')"
                    ><?php echo s($plan_item->actions_agreed ?? ''); ?></textarea>
                </div>

                <div class="sip-field-group">
                    <label>INDICATORE DI VERIFICA</label>
                    <input type="text" id="verification_<?php echo $area_key; ?>"
                           value="<?php echo s($raw_ver); ?>"
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

        <!-- ============ MODALE TRACKING SETTIMANA SINGOLA ============ -->
        <div id="weekTrackerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10001; justify-content:center; align-items:flex-start; padding-top:40px; overflow-y:auto;">
            <div style="background:white; border-radius:10px; padding:22px; max-width:780px; width:94%; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
                    <div>
                        <h3 style="margin:0; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;">📅 Settimana <span id="wt-week-num">-</span></h3>
                        <div id="wt-area-name" style="font-size:0.95rem; color:#374151; margin-top:4px; font-weight:600;"></div>
                    </div>
                    <button onclick="SipWeekTracker.close()" style="background:#f3f4f6; border:none; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:18px;">×</button>
                </div>

                <!-- Lista entries esistenti -->
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:14px;">
                    <div style="font-weight:700; margin-bottom:8px;">Aziende/contatti già inseriti per questa settimana</div>
                    <div id="wt-entries-list" style="max-height:240px; overflow-y:auto;">
                        <div style="color:#9ca3af; font-style:italic;">Caricamento...</div>
                    </div>
                </div>

                <!-- Form nuova entry -->
                <div style="border:2px solid <?php echo LOCAL_FTM_SIP_COLOR; ?>; border-radius:8px; padding:12px; background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>;">
                    <div style="font-weight:700; margin-bottom:8px;">➕ Aggiungi nuova azienda / contatto</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Nome azienda *</label>
                            <input type="text" id="wt-company-name" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;" required>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Persona contatto</label>
                            <input type="text" id="wt-contact-person" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Email</label>
                            <input type="email" id="wt-company-email" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Telefono</label>
                            <input type="tel" id="wt-company-phone" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:0.8rem; font-weight:600;">Indirizzo</label>
                            <input type="text" id="wt-company-address" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Posizione cercata</label>
                            <input type="text" id="wt-position" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600;">Data contatto</label>
                            <input type="date" id="wt-entry-date" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:0.8rem; font-weight:600;">Note</label>
                            <textarea id="wt-notes" rows="2" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px;"></textarea>
                        </div>
                    </div>

                    <!-- Canali "Annuncio visto su:" (visibili solo per area "Lista aziende Target") -->
                    <div id="wt-channels-fields" style="display:none; margin-top:12px; padding:10px; background:#fff; border:1px dashed #0e7490; border-radius:6px;">
                        <div style="font-weight:700; font-size:0.85rem; color:#374151; margin-bottom:8px;">
                            📢 Annuncio visto su:
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:0.82rem;">
                            <label><input type="checkbox" id="wt-ch-azienda"> Sito azienda</label>
                            <label><input type="checkbox" id="wt-ch-agenzia"> Sito agenzia</label>
                            <label><input type="checkbox" id="wt-ch-portale"> Portale</label>
                            <label><input type="checkbox" id="wt-ch-concorso"> Concorso</label>
                            <label><input type="checkbox" id="wt-ch-giornale"> Giornale</label>
                        </div>
                    </div>

                    <!-- Campi URC ufficiali (visibili solo per area "Numero ricerche obbligatorie") -->
                    <div id="wt-urc-fields" style="display:none; margin-top:12px; padding:10px; background:#fff; border:1px dashed #94a3b8; border-radius:6px;">
                        <div style="font-weight:700; font-size:0.85rem; color:#374151; margin-bottom:8px;">
                            📋 Campi modulo URC ufficiale
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:0.82rem;">
                            <label><input type="checkbox" id="wt-urc-assigned"> Assegnato dall'URC</label>
                            <label><input type="checkbox" id="wt-urc-fulltime" checked> Tempo pieno</label>
                            <label><input type="checkbox" id="wt-urc-parttime"> Tempo parziale</label>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:0.82rem; margin-top:6px;">
                            <label><input type="checkbox" id="wt-urc-letter" checked> Per lettera/elettronica</label>
                            <label><input type="checkbox" id="wt-urc-person"> Di persona</label>
                            <label><input type="checkbox" id="wt-urc-phone"> Telefonicamente</label>
                        </div>
                        <div style="margin-top:8px;">
                            <label style="font-size:0.8rem; font-weight:600;">Risultato:</label>
                            <select id="wt-urc-result" style="padding:5px; border:1px solid #cbd5e1; border-radius:4px; margin-left:6px;">
                                <option value="pending">In sospeso</option>
                                <option value="interview">Colloquio</option>
                                <option value="hired">Assunzione</option>
                                <option value="rejected">Non assunzione</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                        <span id="wt-feedback" style="font-size:0.85rem; align-self:center;"></span>
                        <button onclick="SipWeekTracker.save()" id="wt-save-btn" style="background:<?php echo LOCAL_FTM_SIP_COLOR; ?>; color:white; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-weight:600;">
                            <i class="fa fa-plus"></i> Aggiungi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var SipWeekTracker = {
            currentWeek: 0,
            currentArea: '',
            enrollmentid: <?php echo (int)$enrollment->id; ?>,

            open: function(week, areaKey, areaName) {
                this.currentWeek = week;
                this.currentArea = areaKey;
                document.getElementById('wt-week-num').textContent = week;
                document.getElementById('wt-area-name').textContent = areaName;
                // Reset form fields.
                ['wt-company-name','wt-contact-person','wt-company-email','wt-company-phone',
                 'wt-company-address','wt-position','wt-entry-date','wt-notes'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Mostra/nasconde sezione URC se area = "Numero ricerche obbligatorie".
                var urcFields = document.getElementById('wt-urc-fields');
                if (areaKey === 'mandatory_searches') {
                    urcFields.style.display = 'block';
                    // Reset checkbox URC ai default.
                    document.getElementById('wt-urc-assigned').checked = false;
                    document.getElementById('wt-urc-fulltime').checked = true;
                    document.getElementById('wt-urc-parttime').checked = false;
                    document.getElementById('wt-urc-letter').checked = true;
                    document.getElementById('wt-urc-person').checked = false;
                    document.getElementById('wt-urc-phone').checked = false;
                    document.getElementById('wt-urc-result').value = 'pending';
                    // Pre-compila la data con oggi.
                    document.getElementById('wt-entry-date').value = new Date().toISOString().split('T')[0];
                } else {
                    urcFields.style.display = 'none';
                }
                // Mostra/nasconde sezione canali se area = "Lista aziende Target".
                var chanFields = document.getElementById('wt-channels-fields');
                if (areaKey === 'target_companies') {
                    chanFields.style.display = 'block';
                    ['wt-ch-azienda','wt-ch-agenzia','wt-ch-portale','wt-ch-concorso','wt-ch-giornale'].forEach(function(id) {
                        document.getElementById(id).checked = false;
                    });
                    document.getElementById('wt-entry-date').value = new Date().toISOString().split('T')[0];
                } else {
                    chanFields.style.display = 'none';
                }
                document.getElementById('wt-feedback').textContent = '';
                document.getElementById('weekTrackerModal').style.display = 'flex';
                this.loadEntries();
            },

            close: function() {
                document.getElementById('weekTrackerModal').style.display = 'none';
            },

            loadEntries: function() {
                var listEl = document.getElementById('wt-entries-list');
                listEl.innerHTML = '<div style="color:#9ca3af;">Caricamento...</div>';

                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'get_entries');
                fd.append('enrollmentid', this.enrollmentid);
                fd.append('area_key', this.currentArea);
                fd.append('sip_week', this.currentWeek);

                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (!resp.success) {
                        listEl.innerHTML = '<div style="color:#dc2626;">Errore: '+resp.message+'</div>';
                        return;
                    }
                    var entries = resp.data || [];
                    if (entries.length === 0) {
                        listEl.innerHTML = '<div style="color:#9ca3af; font-style:italic;">Nessuna voce ancora inserita.</div>';
                        return;
                    }
                    var html = '';
                    entries.forEach(function(e) {
                        var dateStr = e.entry_date ? new Date(e.entry_date * 1000).toLocaleDateString('it') : '';
                        html += '<div style="display:flex; justify-content:space-between; align-items:flex-start; padding:6px 8px; border-bottom:1px solid #e5e7eb;">';
                        html += '<div style="flex:1;">';
                        html += '<div style="font-weight:600;">'+escapeHtml(e.company_name||'(senza nome)')+'</div>';
                        var meta = [];
                        if (e.contact_person) meta.push(escapeHtml(e.contact_person));
                        if (e.company_email) meta.push(escapeHtml(e.company_email));
                        if (e.company_phone) meta.push(escapeHtml(e.company_phone));
                        if (dateStr) meta.push(dateStr);
                        if (meta.length) html += '<div style="font-size:0.78rem; color:#6b7280;">'+meta.join(' | ')+'</div>';
                        if (e.notes) html += '<div style="font-size:0.78rem; color:#374151; margin-top:2px;">'+escapeHtml(e.notes)+'</div>';
                        html += '</div>';
                        html += '<button onclick="SipWeekTracker.deleteEntry('+e.id+')" style="background:none; border:none; color:#dc2626; cursor:pointer;" title="Elimina">🗑</button>';
                        html += '</div>';
                    });
                    listEl.innerHTML = html;
                });
            },

            save: function() {
                var name = document.getElementById('wt-company-name').value.trim();
                if (!name) {
                    document.getElementById('wt-feedback').innerHTML = '<span style="color:#dc2626;">Nome azienda obbligatorio.</span>';
                    return;
                }
                var btn = document.getElementById('wt-save-btn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvataggio...';

                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'create_entry');
                fd.append('enrollmentid', this.enrollmentid);
                fd.append('area_key', this.currentArea);
                fd.append('sip_week', this.currentWeek);
                fd.append('company_name', name);
                fd.append('contact_person', document.getElementById('wt-contact-person').value);
                fd.append('company_email', document.getElementById('wt-company-email').value);
                fd.append('company_phone', document.getElementById('wt-company-phone').value);
                fd.append('company_address', document.getElementById('wt-company-address').value);
                fd.append('position', document.getElementById('wt-position').value);
                fd.append('entry_date_str', document.getElementById('wt-entry-date').value);
                fd.append('notes', document.getElementById('wt-notes').value);

                // Campi URC ufficiali (inviati solo se area è mandatory_searches).
                if (this.currentArea === 'mandatory_searches') {
                    fd.append('urc_assigned', document.getElementById('wt-urc-assigned').checked ? 1 : 0);
                    fd.append('occupation_fulltime', document.getElementById('wt-urc-fulltime').checked ? 1 : 0);
                    fd.append('occupation_parttime', document.getElementById('wt-urc-parttime').checked ? 1 : 0);
                    fd.append('method_letter', document.getElementById('wt-urc-letter').checked ? 1 : 0);
                    fd.append('method_person', document.getElementById('wt-urc-person').checked ? 1 : 0);
                    fd.append('method_phone', document.getElementById('wt-urc-phone').checked ? 1 : 0);
                    fd.append('result', document.getElementById('wt-urc-result').value);
                }
                // Canali (annuncio visto su) — solo per target_companies, salvati comma-separated nel campo "channel".
                if (this.currentArea === 'target_companies') {
                    var chs = [];
                    if (document.getElementById('wt-ch-azienda').checked) chs.push('azienda');
                    if (document.getElementById('wt-ch-agenzia').checked) chs.push('agenzia');
                    if (document.getElementById('wt-ch-portale').checked) chs.push('portale');
                    if (document.getElementById('wt-ch-concorso').checked) chs.push('concorso');
                    if (document.getElementById('wt-ch-giornale').checked) chs.push('giornale');
                    fd.append('channel', chs.join(','));
                }

                var self = this;
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-plus"></i> Aggiungi';
                    if (resp.success) {
                        document.getElementById('wt-feedback').innerHTML = '<span style="color:#059669;">✅ Aggiunta!</span>';
                        // Aggiorna conteggio sul bottone settimana corrispondente.
                        var btnSel = document.querySelector('.sip-week-btn[data-week="'+self.currentWeek+'"][data-area="'+self.currentArea+'"]');
                        if (btnSel) {
                            var cntDiv = btnSel.querySelector('div:last-child');
                            var newCnt = (parseInt(cntDiv.textContent)||0) + 1;
                            cntDiv.textContent = newCnt;
                            btnSel.style.borderColor = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                            cntDiv.style.color = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                        }
                        // Pulisci form e ricarica lista.
                        ['wt-company-name','wt-contact-person','wt-company-email','wt-company-phone',
                         'wt-company-address','wt-position','wt-entry-date','wt-notes'].forEach(function(id) {
                            document.getElementById(id).value = '';
                        });
                        self.loadEntries();
                        setTimeout(function(){ document.getElementById('wt-feedback').textContent = ''; }, 2000);
                    } else {
                        document.getElementById('wt-feedback').innerHTML = '<span style="color:#dc2626;">Errore: '+resp.message+'</span>';
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-plus"></i> Aggiungi';
                    document.getElementById('wt-feedback').innerHTML = '<span style="color:#dc2626;">Errore rete: '+err.message+'</span>';
                });
            },

            deleteEntry: function(entryid) {
                if (!confirm('Eliminare questa voce?')) return;
                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'delete_entry');
                fd.append('entryid', entryid);
                fd.append('enrollmentid', this.enrollmentid);
                var self = this;
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp.success) {
                        // Decrementa contatore sul bottone settimana.
                        var btnSel = document.querySelector('.sip-week-btn[data-week="'+self.currentWeek+'"][data-area="'+self.currentArea+'"]');
                        if (btnSel) {
                            var cntDiv = btnSel.querySelector('div:last-child');
                            var newCnt = Math.max(0, (parseInt(cntDiv.textContent)||0) - 1);
                            cntDiv.textContent = newCnt;
                            if (newCnt === 0) {
                                btnSel.style.borderColor = '#cbd5e1';
                                cntDiv.style.color = '#9ca3af';
                            }
                        }
                        self.loadEntries();
                    } else {
                        alert('Errore: '+resp.message);
                    }
                });
            }
        };

        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s == null ? '' : String(s);
            return div.innerHTML;
        }

        // Attacca event listener ai bottoni settimana.
        // Per "Lista aziende Target" usa il pannello inline; per le altre aree usa il modale.
        document.addEventListener('click', function(ev) {
            var btn = ev.target.closest('.sip-week-btn');
            if (!btn) return;
            ev.preventDefault();
            ev.stopPropagation();
            var w = parseInt(btn.dataset.week);
            var area = btn.dataset.area;
            var name = btn.dataset.name || area;
            if (area === 'target_companies' || area === 'mandatory_searches') {
                SipInlineTracker.open(area, w);
            } else if (area === 'search_channels') {
                SipChannelTracker.open(w);
            } else {
                SipWeekTracker.open(w, area, name);
            }
        });

        // ============ SIP INLINE TRACKER (tabella inline) ============
        var SipInlineTracker = {
            enrollmentid: <?php echo (int)$enrollment->id; ?>,

            channelDefs: {
                'target_companies': ['azienda','agenzia','portale','concorso','giornale'],
                'mandatory_searches': null
            },

            open: function(area, week) {
                var panel = document.getElementById('sip-inline-' + area);
                if (!panel) return;
                // Chiudi tutti gli altri pannelli inline.
                document.querySelectorAll('.sip-week-inline').forEach(function(p) { p.classList.remove('shown'); });
                panel.classList.add('shown');
                panel.dataset.week = week;
                panel.querySelector('.sip-inline-feedback').innerHTML = '';
                // loadRows aggiorna il titolo e carica i dati filtrati per settimana.
                this.loadRows(area, week);
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },

            close: function(area) {
                var panel = document.getElementById('sip-inline-' + area);
                if (panel) panel.classList.remove('shown');
            },

            loadRows: function(area, week) {
                var panel = document.getElementById('sip-inline-' + area);
                var tbody = panel.querySelector('.sip-inline-rows');

                // Contatore richieste per evitare race condition:
                // se l'utente cambia settimana velocemente, scarta le risposte obsolete.
                if (!this._reqCounter) this._reqCounter = {};
                var reqId = (this._reqCounter[area] || 0) + 1;
                this._reqCounter[area] = reqId;

                // Mostra settimana corrente nel titolo del pannello.
                var weekLabel = (week === 0) ? 'Tutte le settimane (sola lettura)' : ('Settimana ' + week);
                panel.querySelector('.sip-inline-week-num').textContent = weekLabel;
                tbody.innerHTML = '<tr><td colspan="20" style="text-align:center; color:#9ca3af; padding:12px;">Caricamento settimana ' + (week || '(totale)') + '…</td></tr>';

                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'get_entries');
                fd.append('enrollmentid', this.enrollmentid);
                fd.append('area_key', area);
                fd.append('sip_week', week);

                var self = this;
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    // Scarta risposta obsoleta se nel frattempo l'utente ha cliccato un'altra settimana.
                    if (self._reqCounter[area] !== reqId) return;

                    tbody.innerHTML = '';
                    if (resp.success && resp.data && resp.data.length > 0) {
                        resp.data.forEach(function(e, idx) {
                            tbody.appendChild(self.renderRow(area, week, e, idx + 1));
                        });
                        if (week > 0) {
                            tbody.appendChild(self.renderEmptyRow(area, week, resp.data.length + 1));
                        }
                    } else if (!resp.success) {
                        tbody.innerHTML = '<tr><td colspan="20" style="color:#dc2626; padding:10px;">Errore: ' + (resp.message || '') + '</td></tr>';
                    } else {
                        // Nessuna voce per questa settimana.
                        if (week > 0) {
                            // Mostra solo la riga vuota per aggiungere (senza "Nessuna voce").
                            tbody.appendChild(self.renderEmptyRow(area, week, 1));
                        } else {
                            tbody.innerHTML = '<tr><td colspan="20" style="padding:14px; text-align:center; color:#9ca3af; font-style:italic;">Nessuna voce registrata.</td></tr>';
                        }
                    }
                })
                .catch(function(err) {
                    if (self._reqCounter[area] !== reqId) return;
                    tbody.innerHTML = '<tr><td colspan="20" style="color:#dc2626; padding:10px;">Errore di rete: ' + err.message + '</td></tr>';
                });
            },

            renderRow: function(area, week, entry, idx) {
                var tr = document.createElement('tr');
                tr.dataset.entryid = entry.id;
                var dateStr = entry.entry_date ? new Date(entry.entry_date * 1000).toISOString().split('T')[0] : '';
                tr.innerHTML = this.buildRowHtml(area, week, idx, entry, false, dateStr);
                return tr;
            },

            renderEmptyRow: function(area, week, idx) {
                var tr = document.createElement('tr');
                tr.classList.add('row-add');
                tr.dataset.entryid = '';
                var today = new Date().toISOString().split('T')[0];
                tr.innerHTML = this.buildRowHtml(area, week, idx, {}, true, today);
                return tr;
            },

            buildRowHtml: function(area, week, idx, e, isNew, dateStr) {
                e = e || {};
                // Nella vista totale (week=0) mostra anche il numero settimana nella prima colonna.
                var idxCell = (week === 0 && e.sip_week)
                    ? idx + '<br><small style="color:#6b7280;font-weight:400;">S.' + e.sip_week + '</small>'
                    : idx;
                var html = '<td style="text-align:center; font-weight:600; white-space:nowrap;">' + idxCell + '</td>';
                html += '<td><input type="text" class="f-name" value="' + this.esc(e.company_name) + '" placeholder="Nome azienda *"></td>';
                html += '<td><input type="text" class="f-contact" value="' + this.esc(e.contact_person) + '"></td>';
                html += '<td><input type="email" class="f-email" value="' + this.esc(e.company_email) + '"></td>';
                html += '<td><input type="tel" class="f-phone" value="' + this.esc(e.company_phone) + '"></td>';
                html += '<td><input type="text" class="f-address" value="' + this.esc(e.company_address) + '"></td>';
                html += '<td><input type="text" class="f-position" value="' + this.esc(e.position) + '"></td>';
                html += '<td><input type="date" class="f-date" value="' + this.esc(dateStr) + '"></td>';

                // Sia "target_companies" che "mandatory_searches" usano gli stessi 5 canali (SAZ/SAG/POR/CON/GIO).
                if (area === 'target_companies' || area === 'mandatory_searches') {
                    var chs = (e.channel || '').split(',').map(function(c){ return c.trim(); });
                    ['azienda','agenzia','portale','concorso','giornale'].forEach(function(c) {
                        var checked = chs.indexOf(c) !== -1 ? 'checked' : '';
                        html += '<td style="text-align:center;"><input type="checkbox" class="ch-' + c + '" ' + checked + '></td>';
                    });
                }

                if (isNew) {
                    html += '<td style="text-align:center; white-space:nowrap;">';
                    html += '<button type="button" class="btn-row add" onclick="SipInlineTracker.addRow(this, \'' + area + '\', ' + week + ')">+ Aggiungi</button>';
                    html += '</td>';
                } else {
                    html += '<td style="text-align:center; white-space:nowrap;">';
                    html += '<button type="button" class="btn-row save" onclick="SipInlineTracker.updateRow(this, \'' + area + '\', ' + week + ')" title="Salva modifiche">💾</button> ';
                    html += '<button type="button" class="btn-row delete" onclick="SipInlineTracker.deleteRow(this, \'' + area + '\', ' + week + ')" title="Elimina">🗑</button>';
                    html += '</td>';
                }
                return html;
            },

            esc: function(v) { if (v == null) return ''; return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },

            collectRowData: function(tr, area) {
                var data = {
                    company_name: tr.querySelector('.f-name').value.trim(),
                    contact_person: tr.querySelector('.f-contact').value.trim(),
                    company_email: tr.querySelector('.f-email').value.trim(),
                    company_phone: tr.querySelector('.f-phone').value.trim(),
                    company_address: tr.querySelector('.f-address').value.trim(),
                    position: tr.querySelector('.f-position').value.trim(),
                    entry_date_str: tr.querySelector('.f-date').value
                };
                if (area === 'target_companies' || area === 'mandatory_searches') {
                    var chs = [];
                    ['azienda','agenzia','portale','concorso','giornale'].forEach(function(c) {
                        if (tr.querySelector('.ch-' + c).checked) chs.push(c);
                    });
                    data.channel = chs.join(',');
                }
                return data;
            },

            addRow: function(btn, area, week) {
                var tr = btn.closest('tr');
                var data = this.collectRowData(tr, area);
                if (!data.company_name) {
                    this.showFeedback(area, 'Nome azienda obbligatorio.', 'error');
                    return;
                }
                btn.disabled = true; btn.textContent = '...';

                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'create_entry');
                fd.append('enrollmentid', this.enrollmentid);
                fd.append('area_key', area);
                fd.append('sip_week', week);
                Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });

                var self = this;
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp.success) {
                        self.showFeedback(area, '✅ Azienda aggiunta.', 'ok');
                        self.updateWeekBtnCount(area, week, +1);
                        self.updateTotalBtn(area, +1);
                        self.loadRows(area, week);
                    } else {
                        self.showFeedback(area, 'Errore: ' + resp.message, 'error');
                        btn.disabled = false; btn.textContent = '+ Aggiungi';
                    }
                });
            },

            updateRow: function(btn, area, week) {
                // Per ora "update" = elimina + ricrea (l'endpoint non ha un update_entry).
                var tr = btn.closest('tr');
                var entryid = tr.dataset.entryid;
                var data = this.collectRowData(tr, area);
                if (!data.company_name) {
                    this.showFeedback(area, 'Nome azienda obbligatorio.', 'error');
                    return;
                }
                btn.disabled = true; btn.textContent = '...';
                var self = this;
                // Delete vecchia entry
                var fd1 = new FormData();
                fd1.append('sesskey', M.cfg.sesskey);
                fd1.append('action', 'delete_entry');
                fd1.append('entryid', entryid);
                fd1.append('enrollmentid', this.enrollmentid);
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd1 })
                .then(function(r){ return r.json(); })
                .then(function(){
                    // Crea nuova
                    var fd2 = new FormData();
                    fd2.append('sesskey', M.cfg.sesskey);
                    fd2.append('action', 'create_entry');
                    fd2.append('enrollmentid', self.enrollmentid);
                    fd2.append('area_key', area);
                    fd2.append('sip_week', week);
                    Object.keys(data).forEach(function(k){ fd2.append(k, data[k]); });
                    return fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd2 });
                })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp.success) {
                        self.showFeedback(area, '✅ Modifiche salvate.', 'ok');
                        self.loadRows(area, week);
                    } else {
                        self.showFeedback(area, 'Errore: ' + resp.message, 'error');
                        btn.disabled = false; btn.textContent = '💾';
                    }
                });
            },

            deleteRow: function(btn, area, week) {
                if (!confirm('Eliminare questa azienda?')) return;
                var tr = btn.closest('tr');
                var entryid = tr.dataset.entryid;
                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'delete_entry');
                fd.append('entryid', entryid);
                fd.append('enrollmentid', this.enrollmentid);
                var self = this;
                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method:'POST', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp.success) {
                        self.showFeedback(area, '🗑 Eliminata.', 'ok');
                        self.updateWeekBtnCount(area, week, -1);
                        self.updateTotalBtn(area, -1);
                        self.loadRows(area, week);
                    } else {
                        self.showFeedback(area, 'Errore: ' + resp.message, 'error');
                    }
                });
            },

            showFeedback: function(area, msg, type) {
                var fb = document.querySelector('#sip-inline-' + area + ' .sip-inline-feedback');
                if (!fb) return;
                fb.innerHTML = '<span style="color:' + (type === 'ok' ? '#059669' : '#dc2626') + '">' + msg + '</span>';
                setTimeout(function(){ fb.innerHTML = ''; }, 3000);
            },

            updateWeekBtnCount: function(area, week, delta) {
                var btn = document.querySelector('.sip-week-btn[data-week="' + week + '"][data-area="' + area + '"]');
                if (!btn) return;
                var cntDiv = btn.querySelector('div:last-child');
                if (!cntDiv) return;
                var n = (parseInt(cntDiv.textContent) || 0) + delta;
                if (n < 0) n = 0;
                cntDiv.textContent = n;
                var sipColor = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                cntDiv.style.color = n > 0 ? sipColor : '#9ca3af';
                btn.style.borderColor = n > 0 ? sipColor : '#cbd5e1';
                btn.style.background  = n > 0 ? '<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>' : '#f9fafb';
            },

            updateTotalBtn: function(area, delta) {
                var btn = document.querySelector('.sip-week-btn[data-week="0"][data-area="' + area + '"]');
                if (!btn) return;
                var cntDiv = btn.querySelector('div:last-child');
                if (!cntDiv) return;
                var n = (parseInt(cntDiv.textContent) || 0) + delta;
                if (n < 0) n = 0;
                cntDiv.textContent = n;
                var sipColor = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                cntDiv.style.color = n > 0 ? sipColor : '#9ca3af';
                btn.style.borderColor = n > 0 ? sipColor : '#cbd5e1';
                btn.style.background  = n > 0 ? '<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>' : '#f9fafb';
            }
        };

        // ============ SIP CHANNEL TRACKER (Canali di ricerca) ============
        // Channel usage state: channel_key => week it was activated (or 0 if not activated).
        var SIP_CHANNEL_USAGE = <?php echo json_encode($channel_usage); ?>;

        var SIP_CHANNELS_DEF = <?php echo json_encode($sip_channels_def); ?>;

        var SipChannelTracker = {
            enrollmentid: <?php echo (int)$enrollment->id; ?>,
            currentWeek: 0,

            open: function(week) {
                this.currentWeek = week;
                var panel = document.getElementById('sip-inline-search_channels');
                if (!panel) return;
                // Close other inline panels.
                document.querySelectorAll('.sip-week-inline').forEach(function(p) { p.classList.remove('shown'); });
                panel.classList.add('shown');
                panel.dataset.week = week;
                panel.querySelector('.sip-inline-week-num').textContent = (week === 0) ? 'Riepilogo totale (sola lettura)' : ('Settimana ' + week);
                panel.querySelector('.sip-channel-feedback').innerHTML = '';
                this.renderGrid(week);
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },

            close: function() {
                var panel = document.getElementById('sip-inline-search_channels');
                if (panel) panel.classList.remove('shown');
            },

            renderGrid: function(week) {
                var grid = document.querySelector('#sip-inline-search_channels .sip-channel-grid');
                if (!grid) return;
                var html = '';
                var canEdit = <?php echo $canedit ? 'true' : 'false'; ?>;
                var isTotal = (week === 0);
                Object.keys(SIP_CHANNELS_DEF).forEach(function(key) {
                    var label = SIP_CHANNELS_DEF[key];
                    var activatedWeek = SIP_CHANNEL_USAGE[key] || 0;

                    if (isTotal) {
                        // Vista totale: sola lettura. Mostra tutti i canali con il loro stato.
                        if (activatedWeek > 0) {
                            html += '<div style="background:#d1fae5; border:2px solid #10b981; border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px;" title="Attivato nella sett. ' + activatedWeek + '">';
                            html += '<input type="checkbox" checked disabled style="margin-top:3px; flex-shrink:0;">';
                            html += '<div><div style="font-size:0.82rem; font-weight:600; color:#065f46;">' + escapeHtml(label) + '</div>';
                            html += '<div style="font-size:0.72rem; color:#059669;">Sett. ' + activatedWeek + '</div></div>';
                            html += '</div>';
                        } else {
                            html += '<div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px; opacity:0.6;">';
                            html += '<input type="checkbox" disabled style="margin-top:3px; flex-shrink:0;">';
                            html += '<div style="font-size:0.82rem; font-weight:600; color:#9ca3af;">' + escapeHtml(label) + '</div>';
                            html += '</div>';
                        }
                        return;
                    }

                    var isPrevWeek = activatedWeek > 0 && activatedWeek < week;
                    var isThisWeek = activatedWeek === week;

                    if (isPrevWeek) {
                        // Locked — activated in a previous week.
                        html += '<div style="background:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px; opacity:0.6;" title="Attivato nella sett. ' + activatedWeek + '">';
                        html += '<span style="font-size:1.1rem; flex-shrink:0;">🔒</span>';
                        html += '<div><div style="font-size:0.82rem; font-weight:600; color:#374151;">' + escapeHtml(label) + '</div>';
                        html += '<div style="font-size:0.72rem; color:#6b7280;">Attivato sett. ' + activatedWeek + '</div></div>';
                        html += '</div>';
                    } else if (isThisWeek) {
                        // Activated this week — can deactivate.
                        html += '<div style="background:#d1fae5; border:2px solid #10b981; border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px; cursor:' + (canEdit ? 'pointer' : 'default') + ';" ';
                        if (canEdit) {
                            html += 'onclick="SipChannelTracker.toggle(\'' + key + '\', ' + week + ')" ';
                        }
                        html += 'data-key="' + key + '">';
                        html += '<input type="checkbox" checked ' + (canEdit ? '' : 'disabled') + ' style="margin-top:3px; flex-shrink:0;" onclick="event.stopPropagation();">';
                        html += '<div><div style="font-size:0.82rem; font-weight:600; color:#065f46;">' + escapeHtml(label) + '</div>';
                        html += '<div style="font-size:0.72rem; color:#059669;">Attivato questa sett.</div></div>';
                        html += '</div>';
                    } else {
                        // Not yet activated — can activate.
                        html += '<div style="background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; display:flex; align-items:flex-start; gap:8px; cursor:' + (canEdit ? 'pointer' : 'default') + ';" ';
                        if (canEdit) {
                            html += 'onclick="SipChannelTracker.toggle(\'' + key + '\', ' + week + ')" ';
                        }
                        html += 'data-key="' + key + '">';
                        html += '<input type="checkbox" ' + (canEdit ? '' : 'disabled') + ' style="margin-top:3px; flex-shrink:0;" onclick="event.stopPropagation();">';
                        html += '<div style="font-size:0.82rem; font-weight:600; color:#374151;">' + escapeHtml(label) + '</div>';
                        html += '</div>';
                    }
                });
                grid.innerHTML = html;
            },

            toggle: function(channelKey, week) {
                var activatedWeek = SIP_CHANNEL_USAGE[channelKey] || 0;
                var self = this;

                if (activatedWeek > 0 && activatedWeek < week) {
                    // Locked — do nothing.
                    return;
                }

                var action = (activatedWeek === week) ? 'deactivate' : 'activate';
                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', action);
                fd.append('enrollmentid', this.enrollmentid);
                fd.append('channel_key', channelKey);
                fd.append('sip_week', week);

                fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_channels.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success) {
                        if (action === 'activate') {
                            SIP_CHANNEL_USAGE[channelKey] = week;
                            self.updateWeekBtnCount('search_channels', week, +1);
                            self.updateTotalBtn('search_channels', +1);
                        } else {
                            delete SIP_CHANNEL_USAGE[channelKey];
                            self.updateWeekBtnCount('search_channels', week, -1);
                            self.updateTotalBtn('search_channels', -1);
                        }
                        self.renderGrid(week);
                        self.showFeedback(action === 'activate' ? '✅ Canale attivato.' : '✅ Canale rimosso.', 'ok');
                    } else {
                        self.showFeedback('Errore: ' + resp.message, 'error');
                    }
                })
                .catch(function(err) {
                    self.showFeedback('Errore di rete: ' + err.message, 'error');
                });
            },

            showFeedback: function(msg, type) {
                var fb = document.querySelector('#sip-inline-search_channels .sip-channel-feedback');
                if (!fb) return;
                fb.innerHTML = '<span style="color:' + (type === 'ok' ? '#059669' : '#dc2626') + '">' + msg + '</span>';
                setTimeout(function() { fb.innerHTML = ''; }, 3000);
            },

            updateWeekBtnCount: function(area, week, delta) {
                var btn = document.querySelector('.sip-week-btn[data-week="' + week + '"][data-area="' + area + '"]');
                if (!btn) return;
                var cntDiv = btn.querySelector('div:last-child');
                if (!cntDiv) return;
                var n = (parseInt(cntDiv.textContent) || 0) + delta;
                if (n < 0) n = 0;
                cntDiv.textContent = n;
                var sipColor = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                cntDiv.style.color = n > 0 ? sipColor : '#9ca3af';
                btn.style.borderColor = n > 0 ? sipColor : '#cbd5e1';
                btn.style.background  = n > 0 ? '<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>' : '#f9fafb';
            },

            updateTotalBtn: function(area, delta) {
                var btn = document.querySelector('.sip-week-btn[data-week="0"][data-area="' + area + '"]');
                if (!btn) return;
                var cntDiv = btn.querySelector('div:last-child');
                if (!cntDiv) return;
                var n = (parseInt(cntDiv.textContent) || 0) + delta;
                if (n < 0) n = 0;
                cntDiv.textContent = n;
                var sipColor = '<?php echo LOCAL_FTM_SIP_COLOR; ?>';
                cntDiv.style.color = n > 0 ? sipColor : '#9ca3af';
                btn.style.borderColor = n > 0 ? sipColor : '#cbd5e1';
                btn.style.background  = n > 0 ? '<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>' : '#f9fafb';
            }
        };

        // ============ NAVIGAZIONE PILLOLE ORIZZONTALI 12 AREE ============
        document.addEventListener('click', function(ev) {
            var pill = ev.target.closest('.sip-area-pill');
            if (!pill) return;
            ev.preventDefault();
            var target = pill.dataset.target;
            // Disattiva pillole, nascondi card e tracker bars
            document.querySelectorAll('.sip-area-pill').forEach(function(p) { p.classList.remove('active'); });
            document.querySelectorAll('.sip-area-card').forEach(function(c) { c.classList.remove('sip-area-shown'); });
            document.querySelectorAll('.sip-tracker-bar').forEach(function(t) { t.classList.remove('sip-tracker-shown'); });
            // Attiva pillola cliccata
            pill.classList.add('active');
            // Mostra tracker bar relativo
            var bar = document.querySelector('.sip-tracker-bar[data-area="' + target + '"]');
            if (bar) bar.classList.add('sip-tracker-shown');
            // Mostra card relativa
            var card = document.getElementById('sip-area-' + target);
            if (card) card.classList.add('sip-area-shown');
        });

        // Cancella entry URC dalla tabella inline.
        function deleteUrcInline(entryid) {
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'delete_entry');
            fd.append('entryid', entryid);
            fd.append('enrollmentid', <?php echo (int)$enrollment->id; ?>);
            fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); else alert('Errore: ' + resp.message); });
        }

        // Upload PDF prova URC dalla sezione inline.
        function uploadProofInline() {
            var fileInput = document.getElementById('proof-file-inline');
            if (!fileInput.files.length) { alert('Seleziona un file PDF.'); return; }
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'upload_proof');
            fd.append('enrollmentid', <?php echo (int)$enrollment->id; ?>);
            fd.append('month_year', document.getElementById('proof-month-inline').value);
            fd.append('proof_file', fileInput.files[0]);
            fetch(M.cfg.wwwroot + '/local/ftm_sip/ajax_save_tracking.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); else alert('Errore: ' + resp.message); });
        }
        </script>

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
        <?php if ($canedit): ?>
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
                <?php if ($canedit): ?>
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
            <?php if ($canedit): ?>
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
        <?php if ($canedit): ?>
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
                <?php if ($canedit && in_array($appt->status, ['scheduled', 'confirmed'])): ?>
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
        <?php if ($canedit): ?>
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
                    <?php if ($canedit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
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
                        <?php if ($canedit): ?>
                        <select onchange="updateAppStatus(<?php echo $app->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;color:<?php echo $status_colors[$app->status] ?? '#6B7280'; ?>;">
                            <?php foreach ($status_labels as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo $app->status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $status_colors[$app->status] ?? '#6B7280'; ?>;font-weight:500;"><?php echo $status_labels[$app->status] ?? $app->status; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canedit): ?>
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
                    <?php if ($canedit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
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
                        <?php if ($canedit): ?>
                        <select onchange="updateContactOutcome(<?php echo $cnt->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;color:<?php echo $outcome_colors[$cnt->outcome] ?? '#6B7280'; ?>;">
                            <?php foreach ($outcome_labels as $ok => $ol): ?>
                            <option value="<?php echo $ok; ?>" <?php echo $cnt->outcome === $ok ? 'selected' : ''; ?>><?php echo $ol; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $outcome_colors[$cnt->outcome] ?? '#6B7280'; ?>;"><?php echo $outcome_labels[$cnt->outcome] ?? $cnt->outcome; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canedit): ?>
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
                    <?php if ($canedit): ?><th style="padding:6px 8px;"></th><?php endif; ?>
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
                        <?php if ($canedit): ?>
                        <select onchange="updateOppStatus(<?php echo $opp->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px 4px;font-size:11px;">
                            <?php foreach ($opp_status_labels as $sk => $sl): ?>
                            <option value="<?php echo $sk; ?>" <?php echo $opp->status === $sk ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span style="color:<?php echo $opp_status_colors[$opp->status] ?? '#6B7280'; ?>;"><?php echo $opp_status_labels[$opp->status] ?? $opp->status; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canedit): ?>
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

        <?php elseif ($tab === 'accettazione'): ?>
        <!-- ==================== TAB 6: ACCETTAZIONE E VALUTAZIONE INIZIALE (layout Excel) ==================== -->
        <?php
        // Ensure acceptance records exist.
        \local_ftm_sip\sip_manager::create_default_acceptance($enrollment->id);
        $acceptance = \local_ftm_sip\sip_manager::get_acceptance($enrollment->id);
        $areas_def_all = local_ftm_sip_get_activation_areas();
        // Solo le 10 aree quantitative (esclude le 2 qualitative dal template).
        $areas_def_excel = [];
        foreach ($areas_def_all as $k => $a) {
            if (($a['type'] ?? 'quantitative') === 'quantitative') {
                $areas_def_excel[$k] = $a;
            }
        }
        $studentname = fullname($student);
        $studentparts = explode(' ', $studentname, 2);
        $student_first = $studentparts[0] ?? '';
        $student_last = $studentparts[1] ?? '';
        ?>

        <style>
            /* Layout fedele al foglio Excel "accetazione e valutazione.xlsx" */
            .accv2-page { background:#fff; padding:18px; max-width:1100px; margin:0 auto; font-family:Calibri, Arial, sans-serif; color:#000; }
            .accv2-title { font-size:1.6rem; font-weight:700; text-align:center; margin-bottom:14px; color:#000; border-bottom:2px solid #000; padding-bottom:8px; }
            .accv2-namerow { display:grid; grid-template-columns: 80px 200px 100px 200px 1fr; gap:8px; align-items:center; margin-bottom:10px; padding:6px 0; }
            .accv2-namerow .lbl { font-weight:700; }
            .accv2-namerow input { padding:4px 6px; border:1px solid #000; border-radius:0; width:100%; }
            .accv2-intro { font-style:italic; font-weight:700; text-decoration:underline; margin:14px 0 16px 0; }
            /* Tabella per ogni area: stile foglio Excel con bordi neri */
            .accv2-area { border:2px solid #000; margin-bottom:14px; padding:0; }
            .accv2-area-title { padding:8px 12px; border-bottom:1px solid #000; font-weight:700; color:#000; }
            .accv2-area-title-num { color:#000; margin-right:4px; }
            .accv2-area-title-text { font-weight:700; text-decoration:underline; }
            .accv2-area-title-desc { font-weight:400; }
            /* Grid 3 colonne come Excel */
            .accv2-grid { display:grid; grid-template-columns: 220px 1fr 1fr; }
            .accv2-cell { padding:6px 10px; border-right:1px solid #000; border-top:1px solid #000; text-align:center; }
            .accv2-cell:last-child { border-right:none; }
            .accv2-cell-header { font-weight:700; background:#fff; color:#000; }
            /* Sub-grid per Accetto SI/NO o Sett./10sett. */
            .accv2-subgrid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0; padding:0; }
            .accv2-subcell { padding:4px 8px; border-right:1px solid #000; }
            .accv2-subcell:last-child { border-right:none; }
            .accv2-subcell-label { font-size:0.7rem; color:#000; font-weight:600; margin-bottom:2px; }
            .accv2-input { width:100%; padding:4px 6px; border:none; background:transparent; text-align:center; font-weight:600; min-height:26px; }
            .accv2-input:focus { background:#fffbe6; outline:1px solid #0891B2; }
            .accv2-input:disabled { color:#9ca3af; }
            .accv2-radios { display:flex; gap:14px; align-items:center; justify-content:center; }
            .accv2-radios label { display:flex; align-items:center; gap:4px; font-weight:700; cursor:pointer; }
            .accv2-radios input[type="radio"] { transform:scale(1.2); cursor:pointer; }
            .accv2-actions { display:flex; gap:12px; margin-top:18px; padding-top:14px; border-top:2px solid #000; align-items:center; }
            .accv2-feedback { font-size:0.9rem; font-weight:600; }
            @media print {
                .sip-tabs, .sip-header-stats, .accv2-actions, nav, footer, .nav, .breadcrumb, .sip-print-hide { display:none !important; }
                .accv2-page { padding:0; max-width:100%; }
                .accv2-area { page-break-inside:avoid; }
                .accv2-input { border:none; background:transparent; }
            }
        </style>

        <div class="accv2-page">
            <div class="accv2-title">Accettazione e valutazione iniziale</div>

            <div class="accv2-namerow">
                <div class="lbl">Nome:</div>
                <div><input type="text" value="<?php echo s($student_first); ?>" readonly></div>
                <div class="lbl">Cognome:</div>
                <div><input type="text" value="<?php echo s($student_last); ?>" readonly></div>
                <div></div>
            </div>

            <div class="accv2-intro">Criteri valutativi (da compilare prima dell'inizio del percorso)</div>

            <div style="background:#dcfce7; border:1px solid #16a34a; padding:8px 12px; margin-bottom:14px; font-size:0.85rem;" class="sip-print-hide">
                <i class="fa fa-info-circle" style="color:#16a34a;"></i>
                Compila i campi numerici come ti servono. Solo "Numero ricerche obbligatorie" ha 4 sub-campi (Sett. + 10 sett.). Le altre aree hanno 1 campo per Sit. partenza e 1 per Obiettivo (totali 10 settimane). I valori vengono propagati al Piano d'Azione.
            </div>

            <form id="acceptance-form">
            <?php
            // Hardcoded texts matching the official Excel template (no lang dependency).
            $accv2_labels = [
                'target_companies' => [
                    'title' => 'Lista aziende Target',
                    'desc' => 'Redigere una lista di 30-50 aziende che sono in linea con il mio profilo',
                ],
                'mandatory_searches' => [
                    'title' => 'Numero ricerche obbligatorie',
                    'desc' => 'mantenere e aumentare il numero di ricerche obbligatorie settimanali.',
                ],
                'search_channels' => [
                    'title' => 'Canali di ricerca',
                    'desc' => 'Aumentare i canali di ricerca utilizzati dalla PCI (foglio ufficiale, giornali, motori di ricerca…)',
                ],
                'social_network' => [
                    'title' => 'Socialnetwork',
                    'desc' => 'utilizzare i social network in maniera appropriata e idonea alla ricerca di lavoro.',
                ],
                'personal_network' => [
                    'title' => 'Rete personale',
                    'desc' => 'utilizzo della propria rete personale per la ricerca d\'impiego',
                ],
                'targeted_applications' => [
                    'title' => 'Annunci di lavoro mirati',
                    'desc' => 'aumentare il numero candidature ad annuncio di lavoro mirati',
                ],
                'unsolicited_applications' => [
                    'title' => 'Autocandidature',
                    'desc' => 'aumentare il numero candidature ad annuncio di lavoro mirati',
                ],
                'agencies_urc' => [
                    'title' => 'Agenzie e URC',
                    'desc' => 'aumentare il numero di agenzie utilizzate e utilizzo dell\'URC',
                ],
                'interview_training' => [
                    'title' => 'Training colloqui',
                    'desc' => 'effettuare colloqui di prova/formazione mirati su possibili opportunità',
                ],
                'stage_trials' => [
                    'title' => 'Stage / giorni di prova',
                    'desc' => 'promuovere e utilizzare gli strumenti di stage e giorni di prova',
                ],
            ];
            $idx = 0; foreach ($areas_def_excel as $key => $area):
                $idx++;
                $acc = isset($acceptance[$key]) ? $acceptance[$key] : null;
                $acc_id = $acc ? (int)$acc->id : 0;
                $accepted_val = $acc ? (int)$acc->accepted : 0;
                $baseline_total = $acc ? (float)($acc->baseline_value ?? 0) : 0;
                $target_total = $acc ? (float)($acc->target_value ?? 0) : (float)($area['default_target'] ?? 0);
                $baseline_week = $acc ? (float)($acc->baseline_per_week ?? 0) : 0;
                $target_week = $acc ? (float)($acc->target_per_week ?? 0) : 0;
                // Usa testi hardcoded dell'Excel (fallback su lang string se area sconosciuta).
                if (isset($accv2_labels[$key])) {
                    $area_name = $accv2_labels[$key]['title'];
                    $area_desc = $accv2_labels[$key]['desc'];
                } else {
                    $area_name = get_string($area['name'], 'local_ftm_sip');
                    $area_desc = get_string($area['desc'], 'local_ftm_sip');
                }
                $fmt = function($n) {
                    return ($n == (int)$n) ? (int)$n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                };
            ?>
            <?php
                // Solo l'area "Numero ricerche obbligatorie" ha sub-campi Sett./10 sett (come da Excel).
                $has_subweek = ($key === 'mandatory_searches');
            ?>
            <div class="accv2-area" data-accid="<?php echo $acc_id; ?>" data-key="<?php echo $key; ?>" data-subweek="<?php echo $has_subweek ? '1' : '0'; ?>">
                <!-- Riga titolo: "Lista aziende Target: Redigere una lista..." -->
                <div class="accv2-area-title">
                    <span class="accv2-area-title-num"><?php echo $idx; ?>.</span>
                    <span class="accv2-area-title-text"><?php echo s($area_name); ?>:</span>
                    <span class="accv2-area-title-desc"> <?php echo s($area_desc); ?></span>
                </div>

                <!-- Header riga: Accetto | Sit. partenza | Obiettivo -->
                <div class="accv2-grid">
                    <div class="accv2-cell accv2-cell-header">Accetto</div>
                    <div class="accv2-cell accv2-cell-header">Sit. di partenza ultime 10 sett.</div>
                    <div class="accv2-cell accv2-cell-header">Obbiettivo a 10 sett.</div>
                </div>

                <?php if ($has_subweek): ?>
                <!-- Sub-header per "Numero ricerche obbligatorie": Sett. / 10 sett. -->
                <div class="accv2-grid">
                    <div class="accv2-cell">
                        <div class="accv2-radios">
                            <label><input type="radio" name="acc_<?php echo $key; ?>" value="1" <?php echo $accepted_val === 1 ? 'checked' : ''; ?> <?php echo !$canedit ? 'disabled' : ''; ?>> SI</label>
                            <label><input type="radio" name="acc_<?php echo $key; ?>" value="0" <?php echo $accepted_val === 0 ? 'checked' : ''; ?> <?php echo !$canedit ? 'disabled' : ''; ?>> NO</label>
                        </div>
                    </div>
                    <div class="accv2-cell" style="padding:0;">
                        <div class="accv2-subgrid-2">
                            <div class="accv2-subcell">
                                <div class="accv2-subcell-label">Sett.</div>
                                <input type="number" min="0" step="0.1" class="accv2-input acc-baseline-week" value="<?php echo $fmt($baseline_week); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>>
                            </div>
                            <div class="accv2-subcell">
                                <div class="accv2-subcell-label">10 sett.</div>
                                <input type="number" min="0" step="0.1" class="accv2-input acc-baseline-total" value="<?php echo $fmt($baseline_total); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="accv2-cell" style="padding:0;">
                        <div class="accv2-subgrid-2">
                            <div class="accv2-subcell">
                                <div class="accv2-subcell-label">Sett.</div>
                                <input type="number" min="0" step="0.1" class="accv2-input acc-target-week" value="<?php echo $fmt($target_week); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>>
                            </div>
                            <div class="accv2-subcell">
                                <div class="accv2-subcell-label">10 sett.</div>
                                <input type="number" min="0" step="0.1" class="accv2-input acc-target-total" value="<?php echo $fmt($target_total); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Riga semplice: SI/NO + 1 input partenza + 1 input obiettivo -->
                <div class="accv2-grid">
                    <div class="accv2-cell">
                        <div class="accv2-radios">
                            <label><input type="radio" name="acc_<?php echo $key; ?>" value="1" <?php echo $accepted_val === 1 ? 'checked' : ''; ?> <?php echo !$canedit ? 'disabled' : ''; ?>> SI</label>
                            <label><input type="radio" name="acc_<?php echo $key; ?>" value="0" <?php echo $accepted_val === 0 ? 'checked' : ''; ?> <?php echo !$canedit ? 'disabled' : ''; ?>> NO</label>
                        </div>
                    </div>
                    <div class="accv2-cell">
                        <input type="number" min="0" step="0.1" class="accv2-input acc-baseline-total" value="<?php echo $fmt($baseline_total); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>
                               placeholder="totale 10 sett.">
                    </div>
                    <div class="accv2-cell">
                        <input type="number" min="0" step="0.1" class="accv2-input acc-target-total" value="<?php echo $fmt($target_total); ?>" <?php echo !$canedit ? 'disabled' : ''; ?>
                               placeholder="totale 10 sett.">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </form>

            <?php if ($canedit): ?>
            <div class="accv2-actions sip-print-hide">
                <button onclick="saveAcceptance()" class="sip-btn-save">
                    <i class="fa fa-save"></i> Salva Accettazione e Valutazione Iniziale
                </button>
                <button onclick="window.print()" class="sip-btn-save" style="background:#64748B;">
                    <i class="fa fa-print"></i> Stampa
                </button>
                <span id="acc-feedback" class="accv2-feedback"></span>
            </div>
            <?php endif; ?>

            <!-- Signature lines (visible only on print) -->
            <div style="margin-top:40px; display:flex; justify-content:space-between; gap:60px;" class="sip-print-only">
                <div style="flex:1; border-top:1px solid #333; padding-top:6px; text-align:center;">
                    <div style="font-weight:600;">Coach (firma)</div>
                </div>
                <div style="flex:1; border-top:1px solid #333; padding-top:6px; text-align:center;">
                    <div style="font-weight:600;">PCI (firma)</div>
                </div>
            </div>
        </div>

        <style>
            .sip-print-only { display:none; }
            @media print { .sip-print-only { display:flex !important; } }
        </style>

        <script>
        function saveAcceptance() {
            var items = [];
            document.querySelectorAll('.accv2-area').forEach(function(area) {
                var accid = area.dataset.accid;
                if (!accid || accid === '0') return;
                var key = area.dataset.key;
                var acceptedRadio = area.querySelector('input[name="acc_' + key + '"]:checked');
                var accepted = acceptedRadio ? acceptedRadio.value : '0';
                // Solo "Numero ricerche obbligatorie" ha sub-week; le altre solo total.
                var baselineWeekEl = area.querySelector('.acc-baseline-week');
                var targetWeekEl = area.querySelector('.acc-target-week');
                var baselineWeek = baselineWeekEl ? (baselineWeekEl.value || '0') : '0';
                var targetWeek = targetWeekEl ? (targetWeekEl.value || '0') : '0';
                var baselineTotal = area.querySelector('.acc-baseline-total')?.value || '0';
                var targetTotal = area.querySelector('.acc-target-total')?.value || '0';
                items.push({
                    id: parseInt(accid),
                    accepted: parseInt(accepted),
                    baseline: parseFloat(baselineTotal),
                    target: parseFloat(targetTotal),
                    baseline_week: parseFloat(baselineWeek),
                    target_week: parseFloat(targetWeek)
                });
            });

            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'save_all');
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fd.append('items', JSON.stringify(items));

            var fb = document.getElementById('acc-feedback');
            fb.textContent = 'Salvataggio...';
            fb.style.color = '#6b7280';

            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_acceptance.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                fb.textContent = resp.success
                    ? '✅ Salvato. Il target "10 sett." e stato propagato al Piano d\'Azione.'
                    : ('❌ Errore: ' + (resp.message || 'sconosciuto'));
                fb.style.color = resp.success ? '#059669' : '#dc2626';
                setTimeout(function() { fb.textContent = ''; }, 5000);
            })
            .catch(function(err) {
                fb.textContent = '❌ Errore di rete: ' + err.message;
                fb.style.color = '#dc2626';
            });
        }
        </script>

        <?php elseif ($tab === 'tracking'): ?>
        <!-- ==================== TAB 7: TRACKING SETTIMANALE ==================== -->
        <?php
        $areas_def = local_ftm_sip_get_activation_areas();
        $weekly_summary = \local_ftm_sip\sip_manager::get_weekly_summary($enrollment->id);
        $acceptance = \local_ftm_sip\sip_manager::get_acceptance($enrollment->id);
        $coach_evals = \local_ftm_sip\sip_manager::get_coach_evals($enrollment->id);
        $selected_area = optional_param('area', '', PARAM_ALPHANUMEXT);
        $selected_week = optional_param('week', 0, PARAM_INT);
        // Fallback hardcoded (se il lang file sul server è vecchio).
        $tracking_labels = [
            'target_companies'        => 'Lista aziende Target',
            'mandatory_searches'      => 'Numero ricerche obbligatorie',
            'search_channels'         => 'Canali di ricerca',
            'social_network'          => 'Socialnetwork',
            'personal_network'        => 'Rete personale',
            'targeted_applications'   => 'Annunci di lavoro mirati',
            'unsolicited_applications'=> 'Autocandidature',
            'agencies_urc'            => 'Agenzie e URC',
            'interview_training'      => 'Training colloqui',
            'stage_trials'            => 'Stage / giorni di prova',
            'strategy_improvement'    => 'Miglioramento strategia di ricerca',
            'growing_autonomy'        => 'Autonomia crescente',
        ];
        ?>

        <h3 style="margin:0 0 8px; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;">Tracking Settimanale</h3>
        <p style="font-size:0.88rem; color:#6b7280; margin-bottom:16px;">Matrice di avanzamento dei 12 obiettivi sulle 10 settimane.</p>

        <!-- Matrice Overview: aree x settimane -->
        <div style="overflow-x:auto; margin-bottom:24px;">
        <table style="width:100%; border-collapse:collapse; font-size:0.78rem;">
            <thead>
                <tr style="background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>;">
                    <th style="padding:6px; text-align:left; border:1px solid <?php echo LOCAL_FTM_SIP_COLOR_BORDER; ?>; min-width:180px;">Obiettivo</th>
                    <th style="padding:6px; text-align:center; border:1px solid <?php echo LOCAL_FTM_SIP_COLOR_BORDER; ?>; min-width:28px;">Obj</th>
                    <?php for ($w = 1; $w <= 10; $w++): ?>
                    <th style="padding:6px; text-align:center; border:1px solid <?php echo LOCAL_FTM_SIP_COLOR_BORDER; ?>; min-width:28px; <?php echo ($w == $current_week) ? 'background:#0891B2; color:#fff;' : ''; ?>"><?php echo $w; ?></th>
                    <?php endfor; ?>
                    <th style="padding:6px; text-align:center; border:1px solid <?php echo LOCAL_FTM_SIP_COLOR_BORDER; ?>; min-width:40px;">Tot</th>
                </tr>
            </thead>
            <tbody>
            <?php $idx = 0; foreach ($areas_def as $key => $area):
                $idx++;
                $area_name = isset($tracking_labels[$key]) ? $tracking_labels[$key] : get_string($area['name'], 'local_ftm_sip');
                $ws = $area['week_start'] ?? 1;
                $we = $area['week_end'] ?? 10;
                $is_qual = ($area['type'] ?? 'quantitative') === 'qualitative';
                $acc = isset($acceptance[$key]) ? $acceptance[$key] : null;
                $target = $acc ? (int)$acc->target_value : ($area['default_target'] ?? 0);
                $total = 0;
            ?>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:4px 6px; font-weight:600; white-space:nowrap;">
                        <span style="color:<?php echo $area['color']; ?>;"><?php echo $idx; ?>.</span>
                        <a href="?userid=<?php echo $userid; ?>&tab=tracking&area=<?php echo $key; ?>" style="color:#333; text-decoration:none;">
                            <?php echo s($area_name); ?>
                        </a>
                    </td>
                    <td style="padding:4px; text-align:center; font-weight:700; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;"><?php echo $target; ?></td>
                    <?php for ($w = 1; $w <= 10; $w++):
                        $active = ($w >= $ws && $w <= $we);
                        if ($is_qual) {
                            $eval = isset($coach_evals[$key][$w]) ? $coach_evals[$key][$w]->score : null;
                            $cell = $eval !== null ? $eval : '';
                            $total += ($eval ?? 0);
                            $bg = !$active ? '#f3f4f6' : ($eval ? '#ECFEFF' : '#fff');
                        } else {
                            $count = isset($weekly_summary[$key][$w]) ? $weekly_summary[$key][$w] : 0;
                            $cell = $count > 0 ? $count : ($active ? '' : '');
                            $total += $count;
                            $bg = !$active ? '#f3f4f6' : ($count > 0 ? '#ECFEFF' : '#fff');
                        }
                    ?>
                    <td style="padding:4px; text-align:center; background:<?php echo $bg; ?>; border:1px solid #e5e7eb; cursor:<?php echo $active ? 'pointer' : 'default'; ?>; <?php echo ($w == $current_week) ? 'font-weight:700;' : ''; ?>"
                        <?php if ($active): ?>
                        onclick="location.href='?userid=<?php echo $userid; ?>&tab=tracking&area=<?php echo $key; ?>&week=<?php echo $w; ?>'"
                        title="<?php echo s($area_name); ?> — Settimana <?php echo $w; ?>"
                        <?php endif; ?>
                    ><?php echo $cell; ?></td>
                    <?php endfor; ?>
                    <td style="padding:4px; text-align:center; font-weight:700; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;"><?php echo $total; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Detail: entries for selected area+week -->
        <?php if (!empty($selected_area) && isset($areas_def[$selected_area])): ?>
        <?php
        $sel_area_def = $areas_def[$selected_area];
        $sel_area_name = isset($tracking_labels[$selected_area]) ? $tracking_labels[$selected_area] : get_string($sel_area_def['name'], 'local_ftm_sip');
        $is_qual = ($sel_area_def['type'] ?? 'quantitative') === 'qualitative';
        $entries = \local_ftm_sip\sip_manager::get_search_entries($enrollment->id, $selected_area, $selected_week > 0 ? $selected_week : 0);
        ?>

        <div style="background:#fff; border:2px solid <?php echo $sel_area_def['color']; ?>; border-radius:8px; padding:16px;">
            <h4 style="margin:0 0 12px; color:<?php echo $sel_area_def['color']; ?>;">
                <i class="fa <?php echo $sel_area_def['icon']; ?>"></i>
                <?php echo s($sel_area_name); ?>
                <?php if ($selected_week > 0): ?> — Settimana <?php echo $selected_week; ?><?php endif; ?>
            </h4>

            <?php if ($is_qual): ?>
            <!-- Qualitative: coach evaluation 1-10 checkboxes -->
            <p style="font-size:0.85rem; color:#6b7280; margin-bottom:12px;">Valutazione del coach (1-10) per ogni settimana attiva.</p>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:8px;">
                <?php for ($w = ($sel_area_def['week_start'] ?? 7); $w <= ($sel_area_def['week_end'] ?? 10); $w++):
                    $eval_score = isset($coach_evals[$selected_area][$w]) ? $coach_evals[$selected_area][$w]->score : 0;
                ?>
                <div style="background:#fafafa; border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
                    <div style="font-weight:600; font-size:0.82rem; margin-bottom:6px;">Settimana <?php echo $w; ?></div>
                    <div style="display:flex; gap:3px; flex-wrap:wrap;">
                        <?php for ($s = 1; $s <= 10; $s++): ?>
                        <label style="cursor:pointer;">
                            <input type="checkbox" class="eval-cb" data-area="<?php echo $selected_area; ?>" data-week="<?php echo $w; ?>" data-score="<?php echo $s; ?>"
                                   <?php echo ($s <= $eval_score) ? 'checked' : ''; ?> <?php echo !$canedit ? 'disabled' : ''; ?>
                                   onchange="saveEvalCheckbox(this)"
                                   style="accent-color:<?php echo $sel_area_def['color']; ?>;">
                        </label>
                        <?php endfor; ?>
                        <span class="eval-value" id="eval-val-<?php echo $selected_area; ?>-<?php echo $w; ?>" style="font-weight:700; color:<?php echo $sel_area_def['color']; ?>; margin-left:4px;"><?php echo $eval_score; ?>/10</span>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <script>
            function saveEvalCheckbox(cb) {
                var area = cb.dataset.area;
                var week = parseInt(cb.dataset.week);
                var score = parseInt(cb.dataset.score);

                // Count checked boxes up to this score.
                if (cb.checked) {
                    // Check all boxes up to this score.
                    document.querySelectorAll('.eval-cb[data-area="' + area + '"][data-week="' + week + '"]').forEach(function(c) {
                        c.checked = (parseInt(c.dataset.score) <= score);
                    });
                } else {
                    // Uncheck all boxes from this score up.
                    document.querySelectorAll('.eval-cb[data-area="' + area + '"][data-week="' + week + '"]').forEach(function(c) {
                        if (parseInt(c.dataset.score) >= score) c.checked = false;
                    });
                }

                // Count final score.
                var finalScore = 0;
                document.querySelectorAll('.eval-cb[data-area="' + area + '"][data-week="' + week + '"]:checked').forEach(function() { finalScore++; });

                document.getElementById('eval-val-' + area + '-' + week).textContent = finalScore + '/10';

                // Save via AJAX.
                var fd = new FormData();
                fd.append('sesskey', M.cfg.sesskey);
                fd.append('action', 'save_eval');
                fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
                fd.append('area_key', area);
                fd.append('sip_week', week);
                fd.append('score', finalScore);

                fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd });
            }
            </script>

            <?php else: ?>
            <!-- Quantitative: entry table -->
            <?php if (!empty($entries)): ?>
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem; margin-bottom:12px;">
                <thead>
                    <tr style="background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>;">
                        <th style="padding:6px; text-align:left; border-bottom:1px solid #dee2e6;">Data</th>
                        <th style="padding:6px; text-align:left; border-bottom:1px solid #dee2e6;">Azienda</th>
                        <th style="padding:6px; text-align:left; border-bottom:1px solid #dee2e6;">Ruolo</th>
                        <th style="padding:6px; text-align:center; border-bottom:1px solid #dee2e6;">Canale</th>
                        <th style="padding:6px; text-align:center; border-bottom:1px solid #dee2e6;">Risultato</th>
                        <?php if ($canedit): ?><th style="padding:6px; width:30px;"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px;"><?php echo $e->entry_date ? userdate($e->entry_date, '%d/%m/%Y') : '-'; ?></td>
                        <td style="padding:5px 6px; font-weight:500;"><?php echo s($e->company_name ?: '-'); ?></td>
                        <td style="padding:5px 6px;"><?php echo s($e->position ?: '-'); ?></td>
                        <td style="padding:5px 6px; text-align:center;">
                            <?php
                            $methods = [];
                            if ($e->method_letter) $methods[] = 'Lettera';
                            if ($e->method_person) $methods[] = 'Di persona';
                            if ($e->method_phone) $methods[] = 'Telefono';
                            if (!empty($e->channel)) $methods[] = $e->channel;
                            echo s(implode(', ', $methods) ?: '-');
                            ?>
                        </td>
                        <td style="padding:5px 6px; text-align:center;">
                            <?php
                            $result_colors = ['pending' => '#f59e0b', 'interview' => '#0891B2', 'hired' => '#28a745', 'rejected' => '#dc3545'];
                            $rc = $result_colors[$e->result] ?? '#6b7280';
                            ?>
                            <span style="background:<?php echo $rc; ?>20; color:<?php echo $rc; ?>; padding:2px 6px; border-radius:4px; font-size:0.75rem; font-weight:600;">
                                <?php echo s($e->result); ?>
                            </span>
                        </td>
                        <?php if ($canedit): ?>
                        <td style="padding:5px;">
                            <button onclick="deleteTrackingEntry(<?php echo $e->id; ?>)" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:14px;" title="Elimina">&#128465;</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#9ca3af; font-size:0.85rem; text-align:center; padding:20px;">Nessuna voce registrata<?php echo $selected_week > 0 ? ' per questa settimana' : ''; ?>.</p>
            <?php endif; ?>

            <!-- Add new entry form -->
            <?php if ($canedit): ?>
            <div style="background:#fafafa; border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-top:12px;">
                <h5 style="margin:0 0 10px; color:<?php echo $sel_area_def['color']; ?>; font-size:0.9rem;">Aggiungi voce</h5>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Data</label>
                        <input type="date" id="te-date" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Azienda</label>
                        <input type="text" id="te-company" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;" placeholder="Nome azienda">
                    </div>
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Ruolo</label>
                        <input type="text" id="te-position" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;" placeholder="Posizione">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-top:8px;">
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Email azienda</label>
                        <input type="text" id="te-email" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;" placeholder="Email">
                    </div>
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Canale</label>
                        <select id="te-channel" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;">
                            <option value="">Seleziona...</option>
                            <option value="Portale lavoro">Portale lavoro</option>
                            <option value="Sito azienda">Sito azienda</option>
                            <option value="Email">Email</option>
                            <option value="Telefono">Telefono</option>
                            <option value="Di persona">Di persona</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="Agenzia">Agenzia</option>
                            <option value="Rete personale">Rete personale</option>
                            <option value="Giornale">Giornale</option>
                            <option value="Altro">Altro</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.78rem; font-weight:600;">Risultato</label>
                        <select id="te-result" style="width:100%; padding:6px; border:1px solid #dee2e6; border-radius:4px; font-size:0.85rem;">
                            <option value="pending">In sospeso</option>
                            <option value="interview">Colloquio</option>
                            <option value="hired">Assunzione</option>
                            <option value="rejected">Non assunzione</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <button onclick="addTrackingEntry()" class="sip-btn-save" style="font-size:0.85rem; padding:8px 16px;">
                        <i class="fa fa-plus"></i> Aggiungi
                    </button>
                    <span id="te-feedback" style="font-size:0.82rem; margin-left:8px;"></span>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <script>
        function addTrackingEntry() {
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'create_entry');
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fd.append('area_key', '<?php echo $selected_area; ?>');
            fd.append('sip_week', <?php echo max(1, $selected_week ?: $current_week); ?>);
            fd.append('entry_date_str', document.getElementById('te-date').value);
            fd.append('company_name', document.getElementById('te-company').value);
            fd.append('position', document.getElementById('te-position').value);
            fd.append('company_email', document.getElementById('te-email').value);
            fd.append('channel', document.getElementById('te-channel').value);
            fd.append('result', document.getElementById('te-result').value);

            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    document.getElementById('te-feedback').textContent = 'Errore: ' + resp.message;
                    document.getElementById('te-feedback').style.color = '#dc3545';
                }
            });
        }

        function deleteTrackingEntry(id) {
            if (!confirm('Eliminare questa voce?')) return;
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'delete_entry');
            fd.append('entryid', id);
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); });
        }
        </script>

        <?php endif; ?>

        <?php elseif ($tab === 'ricerche'): ?>
        <!-- ==================== TAB 8: FOGLIO RICERCHE URC ==================== -->
        <?php
        $entries_all = \local_ftm_sip\sip_manager::get_search_entries($enrollment->id, 'mandatory_searches');
        $proofs = \local_ftm_sip\sip_manager::get_search_proofs($enrollment->id);
        ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>;">Foglio Ricerche URC</h3>
            <button onclick="window.print()" class="sip-btn-save" style="background:#64748B;">
                <i class="fa fa-print"></i> Stampa
            </button>
        </div>
        <p style="font-size:0.85rem; color:#6b7280; margin-bottom:16px;">
            Modulo "Prova degli sforzi personali intrapresi per trovare lavoro" — Formato URC ufficiale.
        </p>

        <!-- Header info (print-friendly) -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px; padding:12px; background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>; border-radius:8px;">
            <div><strong>Cognome e nome:</strong> <?php echo fullname($DB->get_record('user', ['id' => $enrollment->userid])); ?></div>
            <div><strong>Mese e anno:</strong> <?php echo date('F Y'); ?></div>
            <div><strong>N. domande:</strong> <?php echo count($entries_all); ?></div>
        </div>

        <!-- URC table (exact columns from the official form) -->
        <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.78rem;">
            <thead>
                <tr style="background:<?php echo LOCAL_FTM_SIP_COLOR_BG; ?>;">
                    <th style="padding:6px; border:1px solid #dee2e6; min-width:70px;">Data</th>
                    <th style="padding:6px; border:1px solid #dee2e6; min-width:180px;">Ditta / Indirizzo / Email</th>
                    <th style="padding:6px; border:1px solid #dee2e6; min-width:100px;">Impiego</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:40px;" title="Assegnato dall'URC">URC</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:30px;" title="Tempo pieno">TP</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:30px;" title="Tempo parziale">Parz</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:30px;" title="Per lettera/elettronica">Let</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:30px;" title="Di persona">Pers</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:30px;" title="Telefonicamente">Tel</th>
                    <th style="padding:6px; border:1px solid #dee2e6; text-align:center; min-width:50px;">Risultato</th>
                    <?php if ($canedit): ?><th style="padding:6px; border:1px solid #dee2e6; width:30px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($entries_all)): ?>
                <tr><td colspan="11" style="padding:20px; text-align:center; color:#9ca3af;">Nessuna ricerca registrata.</td></tr>
            <?php else: ?>
            <?php foreach ($entries_all as $e): ?>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:4px 6px; border:1px solid #f3f4f6;"><?php echo $e->entry_date ? userdate($e->entry_date, '%d.%m.%Y') : '-'; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; font-size:0.75rem;">
                        <?php echo s($e->company_name ?: ''); ?>
                        <?php if ($e->company_address): ?><br><?php echo s($e->company_address); ?><?php endif; ?>
                        <?php if ($e->company_email): ?><br><?php echo s($e->company_email); ?><?php endif; ?>
                    </td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6;"><?php echo s($e->position ?: ''); ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->urc_assigned ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->occupation_fulltime ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->occupation_parttime ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_letter ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_person ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center;"><?php echo $e->method_phone ? '&times;' : ''; ?></td>
                    <td style="padding:4px 6px; border:1px solid #f3f4f6; text-align:center; font-size:0.72rem;">
                        <?php echo s($e->result ?? ''); ?>
                    </td>
                    <?php if ($canedit): ?>
                    <td style="padding:4px; border:1px solid #f3f4f6;">
                        <button onclick="deleteTrackingEntry(<?php echo $e->id; ?>)" style="background:none; border:none; color:#dc3545; cursor:pointer;">&#128465;</button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Add URC entry form -->
        <?php if ($canedit): ?>
        <div style="background:#fafafa; border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-top:16px;">
            <h5 style="margin:0 0 10px; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; font-size:0.9rem;">Aggiungi ricerca</h5>
            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:600;">Data</label>
                    <input type="date" id="urc-date" value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:600;">Ditta</label>
                    <input type="text" id="urc-company" placeholder="Nome azienda" style="width:100%; padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:600;">Indirizzo / Email</label>
                    <input type="text" id="urc-address" placeholder="Indirizzo o email" style="width:100%; padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:600;">Impiego</label>
                    <input type="text" id="urc-position" placeholder="Ruolo cercato" style="width:100%; padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                </div>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:8px; font-size:0.8rem;">
                <label><input type="checkbox" id="urc-assigned"> Assegn. URC</label>
                <label><input type="checkbox" id="urc-fulltime" checked> Tempo pieno</label>
                <label><input type="checkbox" id="urc-parttime"> Tempo parziale</label>
                <label><input type="checkbox" id="urc-letter" checked> Per lettera/elettronica</label>
                <label><input type="checkbox" id="urc-person"> Di persona</label>
                <label><input type="checkbox" id="urc-phone"> Telefonicamente</label>
                <select id="urc-result" style="padding:4px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                    <option value="pending">In sospeso</option>
                    <option value="interview">Colloquio</option>
                    <option value="hired">Assunzione</option>
                    <option value="rejected">Non assunzione</option>
                </select>
            </div>
            <div style="margin-top:10px;">
                <button onclick="addUrcEntry()" class="sip-btn-save" style="font-size:0.85rem; padding:8px 16px;">
                    <i class="fa fa-plus"></i> Aggiungi ricerca
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upload PDF proofs -->
        <div style="margin-top:20px; padding:14px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
            <h5 style="margin:0 0 8px; color:<?php echo LOCAL_FTM_SIP_COLOR; ?>; font-size:0.9rem;">
                <i class="fa fa-file-pdf-o"></i> Documenti caricati (PDF Job-Room)
            </h5>
            <?php if (!empty($proofs)): ?>
            <ul style="margin:0; padding-left:18px; font-size:0.85rem;">
                <?php foreach ($proofs as $p): ?>
                <li><?php echo s($p->filename); ?> — <?php echo s($p->month_year); ?> (<?php echo userdate($p->timecreated, '%d/%m/%Y'); ?>)</li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p style="font-size:0.82rem; color:#9ca3af;">Nessun documento caricato.</p>
            <?php endif; ?>

            <?php if ($canedit): ?>
            <div style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                <input type="month" id="proof-month" value="<?php echo date('Y-m'); ?>" style="padding:5px; border:1px solid #dee2e6; border-radius:4px; font-size:0.82rem;">
                <input type="file" id="proof-file" accept=".pdf,.jpg,.jpeg,.png" style="font-size:0.82rem;">
                <button onclick="uploadProof()" class="sip-btn-save" style="font-size:0.82rem; padding:6px 14px;">
                    <i class="fa fa-upload"></i> Carica
                </button>
            </div>
            <?php endif; ?>
        </div>

        <style>
            @media print {
                .sip-tabs, .sip-btn-save, nav, footer, .nav, .breadcrumb, input[type="file"], select { display: none !important; }
                table { font-size: 9px !important; }
            }
        </style>

        <script>
        function addUrcEntry() {
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'create_entry');
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fd.append('area_key', 'mandatory_searches');
            fd.append('sip_week', <?php echo max(1, $current_week); ?>);
            fd.append('entry_date_str', document.getElementById('urc-date').value);
            fd.append('company_name', document.getElementById('urc-company').value);
            fd.append('company_address', document.getElementById('urc-address').value);
            fd.append('position', document.getElementById('urc-position').value);
            fd.append('urc_assigned', document.getElementById('urc-assigned').checked ? 1 : 0);
            fd.append('occupation_fulltime', document.getElementById('urc-fulltime').checked ? 1 : 0);
            fd.append('occupation_parttime', document.getElementById('urc-parttime').checked ? 1 : 0);
            fd.append('method_letter', document.getElementById('urc-letter').checked ? 1 : 0);
            fd.append('method_person', document.getElementById('urc-person').checked ? 1 : 0);
            fd.append('method_phone', document.getElementById('urc-phone').checked ? 1 : 0);
            fd.append('result', document.getElementById('urc-result').value);

            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); else alert('Errore: ' + resp.message); });
        }

        function uploadProof() {
            var fileInput = document.getElementById('proof-file');
            if (!fileInput.files.length) { alert('Seleziona un file.'); return; }
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'upload_proof');
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fd.append('month_year', document.getElementById('proof-month').value);
            fd.append('proof_file', fileInput.files[0]);

            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); else alert('Errore: ' + resp.message); });
        }

        function deleteTrackingEntry(id) {
            if (!confirm('Eliminare questa voce?')) return;
            var fd = new FormData();
            fd.append('sesskey', M.cfg.sesskey);
            fd.append('action', 'delete_entry');
            fd.append('entryid', id);
            fd.append('enrollmentid', <?php echo $enrollment->id; ?>);
            fetch('<?php echo (new moodle_url('/local/ftm_sip/ajax_save_tracking.php'))->out(false); ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(resp) { if (resp.success) location.reload(); });
        }
        </script>

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
// ==================== SIP CONFIG ====================
var sipConfig = {
    sesskey: '<?php echo sesskey(); ?>',
    wwwroot: '<?php echo $CFG->wwwroot; ?>',
    userid: <?php echo $userid; ?>,
    enrollmentid: <?php echo $enrollment->id; ?>,
    isDraft: <?php echo $is_draft ? 'true' : 'false'; ?>,
    canEdit: <?php echo $canedit ? 'true' : 'false'; ?>
};

function sipAjax(url, fd, onSuccess) {
    fd.append('sesskey', sipConfig.sesskey);
    fetch(url, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) { if (onSuccess) onSuccess(result); }
        else { alert(result.message || 'Errore'); }
    })
    .catch(function() { alert('Errore di connessione'); });
}

// ==================== SipStudent OBJECT ====================
var SipStudent = {
    toggleArea: function(areaKey) {
        var card = document.getElementById('sip-area-' + areaKey);
        if (card) card.classList.toggle('expanded');
    },

    markDirty: function(areaKey) { /* no-op, save is manual */ },

    saveArea: function(areaKey) {
        var card = document.getElementById('sip-area-' + areaKey);
        if (!card) return;
        var planId = card.getAttribute('data-planid');
        if (!planId || planId === '0') { alert('Piano non trovato'); return; }

        var btn = document.getElementById('save-btn-' + areaKey);
        var feedback = document.getElementById('feedback-' + areaKey);
        if (btn) { btn.disabled = true; btn.textContent = '...'; }

        var initialRadio = document.querySelector('input[name="initial_' + areaKey + '"]:checked');
        var currentRadio = document.querySelector('input[name="current_' + areaKey + '"]:checked');

        var fd = new FormData();
        fd.append('planid', planId);
        fd.append('level_initial', initialRadio ? initialRadio.value : '0');
        fd.append('level_current', currentRadio ? currentRadio.value : '0');
        fd.append('objective', (document.getElementById('objective_' + areaKey) || {}).value || '');
        fd.append('actions_agreed', (document.getElementById('actions_' + areaKey) || {}).value || '');
        fd.append('verification', (document.getElementById('verification_' + areaKey) || {}).value || '');
        fd.append('notes', (document.getElementById('notes_' + areaKey) || {}).value || '');
        fd.append('is_draft', sipConfig.isDraft ? '1' : '0');

        sipAjax(sipConfig.wwwroot + '/local/ftm_sip/ajax_save_plan.php', fd, function(result) {
            if (btn) { btn.disabled = false; btn.textContent = 'Salva'; }
            if (feedback) { feedback.textContent = 'Salvato'; feedback.style.color = '#059669'; feedback.style.opacity = '1'; setTimeout(function() { feedback.style.opacity = '0'; }, 2000); }
            if (card) { card.style.backgroundColor = '#f0fdf4'; setTimeout(function() { card.style.backgroundColor = ''; }, 1000); }
        });
    },

    savePhase: function(phaseNum) {
        var node = document.getElementById('sip-phase-' + phaseNum);
        if (!node) return;
        var phaseId = node.getAttribute('data-phaseid');
        if (!phaseId || phaseId === '0') return;

        var met = document.getElementById('phase-met-' + phaseNum);
        var notes = document.getElementById('phase-notes-' + phaseNum);
        var feedback = document.getElementById('phase-feedback-' + phaseNum);

        var fd = new FormData();
        fd.append('phaseid', phaseId);
        fd.append('objectives_met', met && met.checked ? '1' : '0');
        fd.append('coach_notes', notes ? notes.value : '');

        sipAjax(sipConfig.wwwroot + '/local/ftm_sip/ajax_save_phase.php', fd, function() {
            if (feedback) { feedback.textContent = 'Salvato'; feedback.style.color = '#059669'; feedback.style.opacity = '1'; setTimeout(function() { feedback.style.opacity = '0'; }, 2000); }
        });
    },

    toggleClosure: function() {
        var card = document.getElementById('sip-closure-card');
        if (!card) return;
        if (card.style.display === 'none' || card.style.display === '') {
            card.style.display = 'block';
            card.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else {
            card.style.display = 'none';
        }
    },

    closureOutcomeChanged: function() {
        var outcome = (document.getElementById('sip-closure-outcome') || {}).value || '';
        var pctRow = document.getElementById('sip-closure-percentage-row');
        var intRow = document.getElementById('sip-closure-interruption-row');
        var refRow = document.getElementById('sip-closure-referral-row');
        if (pctRow) pctRow.style.display = (outcome === 'hired') ? 'block' : 'none';
        if (intRow) intRow.style.display = (outcome === 'interrupted') ? 'block' : 'none';
        if (refRow) refRow.style.display = (outcome === 'not_suitable') ? 'block' : 'none';
        SipStudent.validateClosure();
    },

    updateCharCount: function() {
        var textarea = document.getElementById('sip-closure-evaluation');
        var counter = document.getElementById('sip-closure-charcount');
        if (textarea && counter) {
            var len = textarea.value.length;
            counter.textContent = len + ' / 100 caratteri';
            counter.style.color = len >= 100 ? '#059669' : '#dc2626';
        }
        SipStudent.validateClosure();
    },

    validateClosure: function() {
        var btn = document.getElementById('sip-closure-submit');
        if (!btn) return;
        var outcome = (document.getElementById('sip-closure-outcome') || {}).value || '';
        var evalText = (document.getElementById('sip-closure-evaluation') || {}).value || '';
        var canSubmit = outcome !== '' && evalText.length >= 100;
        btn.disabled = !canSubmit;
    },

    submitClosure: function() {
        var outcome = (document.getElementById('sip-closure-outcome') || {}).value;
        var evalText = (document.getElementById('sip-closure-evaluation') || {}).value;
        if (!outcome) { alert('Seleziona un esito'); return; }
        if (evalText.length < 100) { alert('La valutazione deve essere di almeno 100 caratteri'); return; }
        if (!confirm('Sei sicuro di voler chiudere il SIP?')) return;

        var fd = new FormData();
        fd.append('action', 'close');
        fd.append('enrollmentid', sipConfig.enrollmentid);
        fd.append('outcome', outcome);
        fd.append('outcome_company', (document.getElementById('sip-closure-company') || {}).value || '');
        fd.append('outcome_date', (document.getElementById('sip-closure-date') || {}).value || '');
        fd.append('outcome_percentage', (document.getElementById('sip-closure-percentage') || {}).value || '');
        fd.append('interruption_reason', (document.getElementById('sip-closure-interruption') || {}).value || '');
        fd.append('referral_measure', (document.getElementById('sip-closure-referral') || {}).value || '');
        fd.append('coach_final_evaluation', evalText);
        fd.append('next_steps', (document.getElementById('sip-closure-nextsteps') || {}).value || '');

        sipAjax(sipConfig.wwwroot + '/local/ftm_sip/ajax_close_sip.php', fd, function() {
            alert('Coaching Individualizzato chiuso con successo');
            location.reload();
        });
    }
};

// Visibility toggle.
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('sip-visibility-toggle');
    if (toggle) {
        toggle.addEventListener('change', function() {
            var fd = new FormData();
            fd.append('enrollmentid', sipConfig.enrollmentid);
            fd.append('visible', this.checked ? '1' : '0');
            sipAjax(sipConfig.wwwroot + '/local/ftm_sip/ajax_toggle_visibility.php', fd, function() {});
        });
    }
});

// DO NOT use localStorage redirect - let tabs work as normal links.


// ==================== STANDALONE FUNCTIONS ====================
// Note: Visibility toggle is handled in the SipStudent object above via DOMContentLoaded.
// The old visibility listener below is also disabled.

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
