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
 * SIP Final Report export - generates a downloadable Word document.
 *
 * Produces an HTML-based .doc file with the complete SIP report for a student,
 * including eligibility, action plan, meetings, KPIs and final outcome.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_sip:view', $context);

$userid = required_param('userid', PARAM_INT);

global $DB, $USER;

// ============================================================
// 1. LOAD ALL DATA
// ============================================================

// Student user record.
$student = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', MUST_EXIST);

// SIP enrollment.
$enrollment = $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
if (!$enrollment) {
    throw new moodle_exception('enrollment_not_found', 'local_ftm_sip');
}

// Coach user record.
$coach = $DB->get_record('user', ['id' => $enrollment->coachid], 'id, firstname, lastname, email');

// Eligibility assessment.
$eligibility = null;
if (!empty($enrollment->eligibility_id)) {
    $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['id' => $enrollment->eligibility_id]);
}
if (!$eligibility) {
    $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);
}

// Action plan (7 areas).
$action_plans = $DB->get_records('local_ftm_sip_action_plan', ['enrollmentid' => $enrollment->id], 'id ASC');

// Meetings.
$meetings = $DB->get_records('local_ftm_sip_meetings', ['enrollmentid' => $enrollment->id], 'meeting_date ASC');

// Applications (KPI).
$applications = $DB->get_records('local_ftm_sip_applications', ['enrollmentid' => $enrollment->id], 'application_date ASC');

// Company contacts (KPI).
$contacts = $DB->get_records('local_ftm_sip_contacts', ['enrollmentid' => $enrollment->id], 'contact_date ASC');

// Opportunities (KPI).
$opportunities = $DB->get_records('local_ftm_sip_opportunities', ['enrollmentid' => $enrollment->id], 'opportunity_date ASC');

// ============================================================
// 2. HELPER FUNCTIONS
// ============================================================

/**
 * Format a unix timestamp as dd.mm.YYYY. Returns '-' if empty.
 *
 * @param int|null $ts Unix timestamp.
 * @return string Formatted date.
 */
function sip_format_date($ts) {
    if (empty($ts)) {
        return '-';
    }
    return date('d.m.Y', $ts);
}

/**
 * Escape a string for HTML output.
 *
 * @param string|null $text Text to escape.
 * @return string Escaped text.
 */
function sip_esc($text) {
    if ($text === null || $text === '') {
        return '-';
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the activation scale label for a given level (0-6).
 *
 * @param int|null $level Activation level.
 * @return string Level description.
 */
function sip_level_label($level) {
    if ($level === null || $level === '') {
        return '-';
    }
    $level = (int)$level;
    $labels = [
        0 => get_string('score_0', 'local_ftm_sip'),
        1 => get_string('score_1', 'local_ftm_sip'),
        2 => get_string('score_2', 'local_ftm_sip'),
        3 => get_string('score_3', 'local_ftm_sip'),
        4 => get_string('score_4', 'local_ftm_sip'),
        5 => get_string('score_5', 'local_ftm_sip'),
        6 => get_string('score_6', 'local_ftm_sip'),
    ];
    return $labels[$level] ?? '-';
}

/**
 * Get the localized area name from its key.
 *
 * @param string $areakey Area key identifier.
 * @return string Localized area name.
 */
function sip_area_name($areakey) {
    $key = 'area_' . $areakey;
    return get_string($key, 'local_ftm_sip');
}

/**
 * Get the localized meeting modality label.
 *
 * @param string $modality Modality key.
 * @return string Localized label.
 */
function sip_modality_label($modality) {
    $map = [
        'presence' => get_string('meeting_modality_presence', 'local_ftm_sip'),
        'remote'   => get_string('meeting_modality_remote', 'local_ftm_sip'),
        'phone'    => get_string('meeting_modality_phone', 'local_ftm_sip'),
        'email'    => get_string('meeting_modality_email', 'local_ftm_sip'),
    ];
    return $map[$modality] ?? sip_esc($modality);
}

/**
 * Get the localized outcome label.
 *
 * @param string|null $outcome Outcome key.
 * @return string Localized label.
 */
function sip_outcome_label($outcome) {
    if (empty($outcome)) {
        return '-';
    }
    $map = [
        'hired'                => get_string('closure_outcome_hired', 'local_ftm_sip'),
        'stage'                => get_string('closure_outcome_stage', 'local_ftm_sip'),
        'training'             => get_string('closure_outcome_training', 'local_ftm_sip'),
        'interrupted'          => get_string('closure_outcome_interrupted', 'local_ftm_sip'),
        'not_suitable'         => get_string('closure_outcome_not_suitable', 'local_ftm_sip'),
        'none'                 => get_string('closure_outcome_none', 'local_ftm_sip'),
        'tryout'               => get_string('closure_outcome_tryout', 'local_ftm_sip'),
        'intermediate_earning' => get_string('closure_outcome_intermediate', 'local_ftm_sip'),
        'intermediate'         => get_string('closure_outcome_intermediate', 'local_ftm_sip'),
        'not_placed_activated' => get_string('closure_outcome_not_placed', 'local_ftm_sip'),
        'not_placed'           => get_string('closure_outcome_not_placed', 'local_ftm_sip'),
    ];
    return $map[$outcome] ?? sip_esc($outcome);
}

// Legacy functions sip_sector_label() and sip_level_elig_label() removed.
// The Griglia Valutazione PCI (v1.2.0) uses 6 numeric criteria 1-5 instead.

/**
 * Get the localized recommendation label.
 *
 * @param string $val Recommendation key.
 * @return string Localized label.
 */
function sip_recommendation_label($val) {
    $map = [
        'activate'     => get_string('eligibility_recommend_activate', 'local_ftm_sip'),
        'not_activate' => get_string('eligibility_recommend_not_activate', 'local_ftm_sip'),
        'refer_other'  => get_string('eligibility_recommend_refer', 'local_ftm_sip'),
    ];
    return $map[$val] ?? sip_esc($val);
}

// ============================================================
// 3. COMPUTE DERIVED DATA
// ============================================================

// SIP duration in weeks.
$duration_weeks = '-';
if (!empty($enrollment->date_start)) {
    $end = !empty($enrollment->date_end_actual) ? $enrollment->date_end_actual :
           (!empty($enrollment->date_end_planned) ? $enrollment->date_end_planned : time());
    $diff_days = max(1, round(($end - $enrollment->date_start) / 86400));
    $duration_weeks = round($diff_days / 7, 1);
}

// Status label.
$status_label = get_string('status_' . $enrollment->status, 'local_ftm_sip');

// Meeting statistics.
$total_meetings = count($meetings);
$total_minutes = 0;
$modality_counts = [];
foreach ($meetings as $m) {
    $total_minutes += (int)($m->duration_minutes ?? 0);
    $mod = $m->modality ?? 'presence';
    if (!isset($modality_counts[$mod])) {
        $modality_counts[$mod] = 0;
    }
    $modality_counts[$mod]++;
}
$total_hours = round($total_minutes / 60, 1);

// Average frequency (meetings per week).
$avg_frequency = '-';
if ($duration_weeks !== '-' && $duration_weeks > 0) {
    $avg_frequency = round($total_meetings / $duration_weeks, 1);
}

// Most frequent modality.
$most_frequent_modality = '-';
if (!empty($modality_counts)) {
    arsort($modality_counts);
    $most_frequent_modality = sip_modality_label(array_key_first($modality_counts));
}

// KPI aggregates.
$app_total = count($applications);
$app_with_interview = 0;
foreach ($applications as $app) {
    if ($app->status === 'interview') {
        $app_with_interview++;
    }
}

$contact_total = count($contacts);
$contact_positive = 0;
foreach ($contacts as $c) {
    if ($c->outcome === 'positive') {
        $contact_positive++;
    }
}

$opp_total = count($opportunities);
$opp_completed = 0;
foreach ($opportunities as $o) {
    if ($o->status === 'completed') {
        $opp_completed++;
    }
}

// ============================================================
// 4. GENERATE WORD-COMPATIBLE HTML
// ============================================================

$studentname = fullname($student);
$filename = 'CI_Report_' . clean_filename($studentname) . '_' . date('Y-m-d') . '.doc';

header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Begin HTML output.
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>' . sip_esc(get_string('report_sip_title', 'local_ftm_sip')) . ' - ' . sip_esc($studentname) . '</title>
<style>
@page {
    size: A4;
    margin: 2cm;
}
body {
    font-family: Calibri, "Segoe UI", Arial, sans-serif;
    font-size: 12pt;
    line-height: 1.5;
    color: #333333;
    margin: 0;
    padding: 20px;
}
h1 {
    font-family: Calibri, "Segoe UI", Arial, sans-serif;
    font-size: 20pt;
    font-weight: bold;
    color: #1A1A2E;
    text-align: center;
    margin-top: 10px;
    margin-bottom: 5px;
}
h2 {
    font-family: Calibri, "Segoe UI", Arial, sans-serif;
    font-size: 14pt;
    font-weight: bold;
    color: #1A1A2E;
    border-bottom: 2px solid #0891B2;
    padding-bottom: 4px;
    margin-top: 28px;
    margin-bottom: 12px;
}
h3 {
    font-family: Calibri, "Segoe UI", Arial, sans-serif;
    font-size: 12pt;
    font-weight: bold;
    color: #333333;
    margin-top: 16px;
    margin-bottom: 8px;
}
table {
    border-collapse: collapse;
    width: 100%;
    margin: 10px 0 16px 0;
    font-size: 11pt;
}
th, td {
    padding: 6px 10px;
    text-align: left;
    border: 1px solid #DEE2E6;
    vertical-align: top;
}
th {
    background-color: #0891B2;
    color: #FFFFFF;
    font-weight: bold;
}
.header-bar {
    text-align: center;
    padding: 20px 0 10px 0;
    border-bottom: 3px solid #0891B2;
    margin-bottom: 20px;
}
.header-bar .subtitle {
    font-size: 10pt;
    color: #666666;
    margin-top: 6px;
}
.info-table td:first-child {
    background-color: #F8F9FA;
    font-weight: bold;
    width: 220px;
    white-space: nowrap;
}
.summary-box {
    background-color: #F0FDFA;
    border: 1px solid #0891B2;
    border-radius: 4px;
    padding: 10px 14px;
    margin: 10px 0;
    font-size: 11pt;
}
.summary-box strong {
    color: #0891B2;
}
.delta-positive {
    color: #28A745;
    font-weight: bold;
}
.delta-negative {
    color: #DC3545;
    font-weight: bold;
}
.delta-zero {
    color: #6C757D;
}
.kpi-highlight {
    font-size: 14pt;
    font-weight: bold;
    color: #0891B2;
}
.section-empty {
    color: #999999;
    font-style: italic;
    padding: 10px 0;
}
.footer {
    margin-top: 40px;
    padding-top: 10px;
    border-top: 1px solid #DEE2E6;
    text-align: center;
    font-size: 9pt;
    color: #999999;
    font-style: italic;
}
</style>
</head>
<body>';

// ---- SECTION 1: HEADER ----
echo '<div class="header-bar">
    <div style="font-size: 10pt; color: #0891B2; font-weight: bold; letter-spacing: 2px; margin-bottom: 4px;">FONDAZIONE TERZO MILLENNIO</div>
    <h1>' . sip_esc(get_string('report_sip_title', 'local_ftm_sip')) . '</h1>
    <div class="subtitle">' . sip_esc($studentname) . '</div>
    <div class="subtitle">' . sip_format_date(time()) . '</div>';
if ($coach) {
    echo '<div class="subtitle">Coach: ' . sip_esc(fullname($coach)) . '</div>';
}
echo '</div>';

// ---- SECTION 2: DATI PCI ----
echo '<h2>' . sip_esc(get_string('report_section_pci', 'local_ftm_sip')) . '</h2>';
echo '<table class="info-table">';
echo '<tr><td>' . sip_esc(get_string('firstname', 'local_ftm_sip')) . ' / ' . sip_esc(get_string('lastname', 'local_ftm_sip')) . '</td><td>' . sip_esc($studentname) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('email', 'local_ftm_sip')) . '</td><td>' . sip_esc($student->email) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('company_sector', 'local_ftm_sip')) . '</td><td>' . sip_esc(strtoupper($enrollment->sector ?? '-')) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('coach', 'local_ftm_sip')) . '</td><td>' . ($coach ? sip_esc(fullname($coach)) : '-') . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('enrollment_start', 'local_ftm_sip')) . '</td><td>' . sip_format_date($enrollment->date_start) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('enrollment_end', 'local_ftm_sip')) . '</td><td>' . sip_format_date($enrollment->date_end_actual ?: $enrollment->date_end_planned) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('report_duration_weeks', 'local_ftm_sip')) . '</td><td>' . sip_esc((string)$duration_weeks) . '</td></tr>';
echo '<tr><td>' . sip_esc(get_string('enrollment_status', 'local_ftm_sip')) . '</td><td><strong>' . sip_esc($status_label) . '</strong></td></tr>';
echo '</table>';

// ---- SECTION 3: ELIGIBILITY ----
echo '<h2>' . sip_esc(get_string('report_section_eligibility', 'local_ftm_sip')) . '</h2>';

if ($eligibility) {
    // Griglia Valutazione PCI - 6 numeric criteria (1-5).
    $report_criteria = [
        'motivazione' => [get_string('eligibility_criterion_motivazione', 'local_ftm_sip'), (int)($eligibility->motivazione ?? 0)],
        'chiarezza' => [get_string('eligibility_criterion_chiarezza', 'local_ftm_sip'), (int)($eligibility->chiarezza_obiettivo ?? 0)],
        'occupabilita' => [get_string('eligibility_criterion_occupabilita', 'local_ftm_sip'), (int)($eligibility->occupabilita ?? 0)],
        'autonomia' => [get_string('eligibility_criterion_autonomia', 'local_ftm_sip'), (int)($eligibility->autonomia ?? 0)],
        'bisogno_coaching' => [get_string('eligibility_criterion_bisogno_coaching', 'local_ftm_sip'), (int)($eligibility->bisogno_coaching ?? 0)],
        'comportamento' => [get_string('eligibility_criterion_comportamento', 'local_ftm_sip'), (int)($eligibility->comportamento ?? 0)],
    ];
    $report_totale = (int)($eligibility->totale ?? 0);
    $report_decisione = $eligibility->decisione ?? 'pending';
    $decisione_map = [
        'idoneo' => get_string('eligibility_decisione_idoneo', 'local_ftm_sip'),
        'non_idoneo' => get_string('eligibility_decisione_non_idoneo', 'local_ftm_sip'),
        'pending' => get_string('eligibility_decisione_pending', 'local_ftm_sip'),
    ];

    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('eligibility_report_criterion', 'local_ftm_sip')) . '</th>';
    echo '<th style="width:60px; text-align:center;">1</th>';
    echo '<th style="width:60px; text-align:center;">2</th>';
    echo '<th style="width:60px; text-align:center;">3</th>';
    echo '<th style="width:60px; text-align:center;">4</th>';
    echo '<th style="width:60px; text-align:center;">5</th>';
    echo '<th style="width:80px; text-align:center;">' . sip_esc(get_string('eligibility_report_score', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($report_criteria as $ckey => $cdata) {
        $clabel = $cdata[0];
        $cval = $cdata[1];
        echo '<tr>';
        echo '<td style="font-weight:bold;">' . sip_esc($clabel) . '</td>';
        for ($v = 1; $v <= 5; $v++) {
            $marker = ($v === $cval) ? '&#9679;' : '&#9675;';
            $style = ($v === $cval) ? 'color:#0891B2; font-weight:bold;' : 'color:#DEE2E6;';
            echo '<td style="text-align:center; font-size:14pt; ' . $style . '">' . $marker . '</td>';
        }
        echo '<td style="text-align:center; font-weight:bold; color:#0891B2;">' . ($cval > 0 ? $cval : '-') . '</td>';
        echo '</tr>';
    }

    // Total row.
    echo '<tr style="background-color:#F0FDFA; font-weight:bold;">';
    echo '<td colspan="6" style="text-align:right; font-weight:bold;">' . sip_esc(get_string('eligibility_total', 'local_ftm_sip')) . '</td>';
    echo '<td style="text-align:center; font-size:14pt; color:#0891B2;">' . $report_totale . '/30</td>';
    echo '</tr>';
    echo '</table>';

    // Decision and recommendation.
    echo '<table class="info-table" style="margin-top:8px;">';
    echo '<tr><td>' . sip_esc(get_string('eligibility_decisione', 'local_ftm_sip')) . '</td><td><strong>' . sip_esc($decisione_map[$report_decisione] ?? $report_decisione) . '</strong></td></tr>';
    if (!empty($eligibility->coach_recommendation)) {
        echo '<tr><td>' . sip_esc(get_string('eligibility_recommendation', 'local_ftm_sip')) . '</td><td>' . sip_esc(sip_recommendation_label($eligibility->coach_recommendation)) . '</td></tr>';
    }
    if ($eligibility->coach_recommendation === 'refer_other' && !empty($eligibility->referral_detail)) {
        echo '<tr><td>' . sip_esc(get_string('eligibility_referral_detail', 'local_ftm_sip')) . '</td><td>' . sip_esc($eligibility->referral_detail) . '</td></tr>';
    }
    if (!empty($eligibility->note)) {
        echo '<tr><td>' . sip_esc(get_string('eligibility_note', 'local_ftm_sip')) . '</td><td>' . nl2br(sip_esc($eligibility->note)) . '</td></tr>';
    }
    echo '</table>';
} else {
    echo '<p class="section-empty">' . sip_esc(get_string('no_data', 'local_ftm_sip')) . '</p>';
}

// ---- SECTION 4: BASELINE - INITIAL LEVELS ----
echo '<h2>' . sip_esc(get_string('report_section_baseline', 'local_ftm_sip')) . '</h2>';

if (!empty($action_plans)) {
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('activation_areas', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 120px; text-align: center;">' . sip_esc(get_string('sip_student_initial_level', 'local_ftm_sip')) . ' (0-6)</th>';
    echo '<th>' . sip_esc(get_string('report_level_description', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($action_plans as $ap) {
        echo '<tr>';
        echo '<td>' . sip_esc(sip_area_name($ap->area_key)) . '</td>';
        echo '<td style="text-align: center; font-weight: bold;">' . ($ap->level_initial !== null ? (int)$ap->level_initial : '-') . '</td>';
        echo '<td style="font-size: 10pt; color: #666;">' . sip_esc(sip_level_label($ap->level_initial)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="section-empty">' . sip_esc(get_string('no_data', 'local_ftm_sip')) . '</p>';
}

// ---- SECTION 5: FINAL LEVELS AND PROGRESS ----
echo '<h2>' . sip_esc(get_string('report_section_final', 'local_ftm_sip')) . '</h2>';

if (!empty($action_plans)) {
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('activation_areas', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 80px; text-align: center;">' . sip_esc(get_string('report_initial', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 80px; text-align: center;">' . sip_esc(get_string('report_final', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 80px; text-align: center;">Delta</th>';
    echo '<th>' . sip_esc(get_string('area_objectives', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('sip_student_verification', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($action_plans as $ap) {
        $initial = $ap->level_initial !== null ? (int)$ap->level_initial : null;
        $current = $ap->level_current !== null ? (int)$ap->level_current : null;
        $delta = '-';
        $delta_class = 'delta-zero';
        if ($initial !== null && $current !== null) {
            $diff = $current - $initial;
            $delta = ($diff >= 0 ? '+' : '') . $diff;
            if ($diff > 0) {
                $delta_class = 'delta-positive';
            } else if ($diff < 0) {
                $delta_class = 'delta-negative';
            }
        }

        echo '<tr>';
        echo '<td>' . sip_esc(sip_area_name($ap->area_key)) . '</td>';
        echo '<td style="text-align: center;">' . ($initial !== null ? $initial : '-') . '</td>';
        echo '<td style="text-align: center; font-weight: bold;">' . ($current !== null ? $current : '-') . '</td>';
        echo '<td style="text-align: center;" class="' . $delta_class . '">' . $delta . '</td>';
        echo '<td style="font-size: 10pt;">' . sip_esc($ap->objective) . '</td>';
        echo '<td style="font-size: 10pt;">' . sip_esc($ap->verification) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="section-empty">' . sip_esc(get_string('no_data', 'local_ftm_sip')) . '</p>';
}

// ---- SECTION 6: MEETINGS SUMMARY ----
echo '<h2>' . sip_esc(get_string('report_section_meetings', 'local_ftm_sip')) . '</h2>';

echo '<div class="summary-box">';
echo '<strong>' . sip_esc(get_string('report_total_meetings', 'local_ftm_sip')) . ':</strong> ' . $total_meetings . ' &nbsp;&nbsp; ';
echo '<strong>' . sip_esc(get_string('report_total_hours', 'local_ftm_sip')) . ':</strong> ' . $total_hours . ' &nbsp;&nbsp; ';
echo '<strong>' . sip_esc(get_string('report_avg_frequency', 'local_ftm_sip')) . ':</strong> ' . sip_esc((string)$avg_frequency) . ' ' . sip_esc(get_string('report_meetings_per_week', 'local_ftm_sip')) . ' &nbsp;&nbsp; ';
echo '<strong>' . sip_esc(get_string('report_most_frequent_modality', 'local_ftm_sip')) . ':</strong> ' . sip_esc($most_frequent_modality);
echo '</div>';

if (!empty($meetings)) {
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('meeting_date', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 80px; text-align: center;">' . sip_esc(get_string('meeting_duration', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 100px;">' . sip_esc(get_string('meeting_modality', 'local_ftm_sip')) . '</th>';
    echo '<th style="width: 80px; text-align: center;">' . sip_esc(get_string('meeting_sip_week', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('meeting_summary', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($meetings as $m) {
        echo '<tr>';
        echo '<td style="white-space: nowrap;">' . sip_format_date($m->meeting_date) . '</td>';
        echo '<td style="text-align: center;">' . (int)($m->duration_minutes ?? 0) . ' min</td>';
        echo '<td>' . sip_esc(sip_modality_label($m->modality)) . '</td>';
        echo '<td style="text-align: center;">' . ($m->sip_week ? (int)$m->sip_week : '-') . '</td>';
        echo '<td style="font-size: 10pt;">' . nl2br(sip_esc($m->summary)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="section-empty">' . sip_esc(get_string('no_meetings', 'local_ftm_sip')) . '</p>';
}

// ---- SECTION 7: KPI ----
echo '<h2>' . sip_esc(get_string('report_section_kpi', 'local_ftm_sip')) . '</h2>';

// KPI summary boxes.
echo '<div class="summary-box">';
echo '<strong>' . sip_esc(get_string('kpi_applications', 'local_ftm_sip')) . ':</strong> ';
echo '<span class="kpi-highlight">' . $app_total . '</span>';
echo ' (' . sip_esc(get_string('report_with_interview', 'local_ftm_sip')) . ': ' . $app_with_interview . ') &nbsp;&nbsp; ';

echo '<strong>' . sip_esc(get_string('kpi_company_contacts', 'local_ftm_sip')) . ':</strong> ';
echo '<span class="kpi-highlight">' . $contact_total . '</span>';
echo ' (' . sip_esc(get_string('report_positive_outcome', 'local_ftm_sip')) . ': ' . $contact_positive . ') &nbsp;&nbsp; ';

echo '<strong>' . sip_esc(get_string('kpi_opportunities', 'local_ftm_sip')) . ':</strong> ';
echo '<span class="kpi-highlight">' . $opp_total . '</span>';
echo ' (' . sip_esc(get_string('report_completed', 'local_ftm_sip')) . ': ' . $opp_completed . ')';
echo '</div>';

// Applications table.
if (!empty($applications)) {
    echo '<h3>' . sip_esc(get_string('kpi_applications', 'local_ftm_sip')) . '</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('entry_date', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('company_name', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_position', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_type', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('appointment_status', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($applications as $app) {
        echo '<tr>';
        echo '<td style="white-space: nowrap;">' . sip_format_date($app->application_date) . '</td>';
        echo '<td>' . sip_esc($app->company_name) . '</td>';
        echo '<td>' . sip_esc($app->position) . '</td>';
        echo '<td>' . sip_esc($app->application_type) . '</td>';
        echo '<td>' . sip_esc($app->status) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// Contacts table.
if (!empty($contacts)) {
    echo '<h3>' . sip_esc(get_string('kpi_company_contacts', 'local_ftm_sip')) . '</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('entry_date', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('company_name', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_contact_type', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_contact_person', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_outcome_col', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($contacts as $c) {
        echo '<tr>';
        echo '<td style="white-space: nowrap;">' . sip_format_date($c->contact_date) . '</td>';
        echo '<td>' . sip_esc($c->company_name) . '</td>';
        echo '<td>' . sip_esc($c->contact_type) . '</td>';
        echo '<td>' . sip_esc($c->contact_person) . '</td>';
        echo '<td>' . sip_esc($c->outcome) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// Opportunities table.
if (!empty($opportunities)) {
    echo '<h3>' . sip_esc(get_string('kpi_opportunities', 'local_ftm_sip')) . '</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>' . sip_esc(get_string('entry_date', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('company_name', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_type', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('appointment_status', 'local_ftm_sip')) . '</th>';
    echo '<th>' . sip_esc(get_string('report_outcome_col', 'local_ftm_sip')) . '</th>';
    echo '</tr>';

    foreach ($opportunities as $o) {
        echo '<tr>';
        echo '<td style="white-space: nowrap;">' . sip_format_date($o->opportunity_date) . '</td>';
        echo '<td>' . sip_esc($o->company_name) . '</td>';
        echo '<td>' . sip_esc($o->opportunity_type) . '</td>';
        echo '<td>' . sip_esc($o->status) . '</td>';
        echo '<td style="font-size: 10pt;">' . nl2br(sip_esc($o->outcome)) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

if (empty($applications) && empty($contacts) && empty($opportunities)) {
    echo '<p class="section-empty">' . sip_esc(get_string('no_data', 'local_ftm_sip')) . '</p>';
}

// ---- SECTION 8: FINAL OUTCOME ----
echo '<h2>' . sip_esc(get_string('report_section_outcome', 'local_ftm_sip')) . '</h2>';

echo '<table class="info-table">';
echo '<tr><td>' . sip_esc(get_string('closure_outcome', 'local_ftm_sip')) . '</td><td><strong>' . sip_esc(sip_outcome_label($enrollment->outcome)) . '</strong></td></tr>';

if (!empty($enrollment->outcome_company)) {
    echo '<tr><td>' . sip_esc(get_string('closure_company', 'local_ftm_sip')) . '</td><td>' . sip_esc($enrollment->outcome_company) . '</td></tr>';
}
echo '<tr><td>' . sip_esc(get_string('closure_date', 'local_ftm_sip')) . '</td><td>' . sip_format_date($enrollment->outcome_date) . '</td></tr>';

if (!empty($enrollment->outcome_percentage)) {
    echo '<tr><td>' . sip_esc(get_string('closure_percentage', 'local_ftm_sip')) . '</td><td>' . (int)$enrollment->outcome_percentage . '%</td></tr>';
}
if (!empty($enrollment->interruption_reason)) {
    echo '<tr><td>' . sip_esc(get_string('closure_interruption_reason', 'local_ftm_sip')) . '</td><td>' . nl2br(sip_esc($enrollment->interruption_reason)) . '</td></tr>';
}
if (!empty($enrollment->referral_measure)) {
    echo '<tr><td>' . sip_esc(get_string('closure_referral', 'local_ftm_sip')) . '</td><td>' . sip_esc($enrollment->referral_measure) . '</td></tr>';
}
echo '</table>';

// ---- SECTION 9: COACH FINAL EVALUATION ----
echo '<h2>' . sip_esc(get_string('report_section_evaluation', 'local_ftm_sip')) . '</h2>';

if (!empty($enrollment->coach_final_evaluation)) {
    echo '<div class="summary-box">';
    echo '<h3>' . sip_esc(get_string('closure_coach_evaluation', 'local_ftm_sip')) . '</h3>';
    echo '<p>' . nl2br(sip_esc($enrollment->coach_final_evaluation)) . '</p>';
    echo '</div>';
} else {
    echo '<p class="section-empty">' . sip_esc(get_string('no_data', 'local_ftm_sip')) . '</p>';
}

if (!empty($enrollment->next_steps)) {
    echo '<div class="summary-box">';
    echo '<h3>' . sip_esc(get_string('closure_next_steps', 'local_ftm_sip')) . '</h3>';
    echo '<p>' . nl2br(sip_esc($enrollment->next_steps)) . '</p>';
    echo '</div>';
}

// ---- FOOTER ----
$generated_info = new stdClass();
$generated_info->date = date('d.m.Y H:i');
$generated_info->user = fullname($USER);

echo '<div class="footer">';
echo sip_esc(get_string('report_generated_by', 'local_ftm_sip', $generated_info));
echo '<br>';
echo 'Fondazione Terzo Millennio - Coaching Individualizzato';
echo '</div>';

echo '</body></html>';
die();
