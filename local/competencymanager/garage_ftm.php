<?php
/**
 * Garage FTM - Configurazione Passaporto Tecnico
 *
 * Pagina dove il coach seleziona aree/competenze da includere nel passaporto,
 * sceglie formato di visualizzazione e puo' fare anteprima.
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');
require_once(__DIR__ . '/classes/coach_evaluation_manager.php');
require_once(__DIR__ . '/area_mapping.php');

use local_competencymanager\coach_evaluation_manager;

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$course = $courseid ? get_course($courseid) : null;

if ($courseid) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

// Check permissions (same as technical_passport.php).
$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();
$isownreport = ($USER->id == $userid);

if (!$canviewall && !$isadmin && !$isownreport) {
    throw new moodle_exception('nopermissions', 'error', '', 'view garage');
}

// Can save?
$canEvaluate = has_capability('local/competencymanager:evaluate', $context) || $isadmin;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/garage_ftm.php', [
    'userid' => $userid,
    'courseid' => $courseid,
]));
$PAGE->set_title('Garage FTM - ' . fullname($student));
$PAGE->set_heading('Garage FTM');
$PAGE->set_pagelayout('report');

// ========================================
// HELPER: get_sector_from_course_name
// ========================================
function garage_get_sector_from_course_name($coursename) {
    $name = strtoupper($coursename);
    if (strpos($name, 'AUTOVEICOLO') !== false || strpos($name, 'AUTOMOBILE') !== false) {
        return 'AUTOMOBILE';
    }
    if (strpos($name, 'CHIMICA') !== false || strpos($name, 'CHIMFARM') !== false || strpos($name, 'FARMAC') !== false) {
        return 'CHIMFARM';
    }
    if (strpos($name, 'LOGISTICA') !== false) {
        return 'LOGISTICA';
    }
    if (strpos($name, 'MECCANICA') !== false) {
        return 'MECCANICA';
    }
    if (strpos($name, 'ELETTRIC') !== false) {
        return 'ELETTRICITA';
    }
    if (strpos($name, 'AUTOMAZIONE') !== false) {
        return 'AUTOMAZIONE';
    }
    if (strpos($name, 'METALCOSTRUZIONE') !== false || strpos($name, 'METAL') !== false) {
        return 'METALCOSTRUZIONE';
    }
    return '';
}

// ========================================
// HELPER: Aggregate by area (same as passport)
// ========================================
function garage_aggregate_by_area($competencies, $areaDescriptions, $sector = '') {
    global $AREA_NAMES;
    $areas = [];
    $colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#30cfd0', '#c471f5',
        '#48c6ef', '#6f86d6',
    ];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $areaInfo = get_area_info($code);
        $areaKey = $areaInfo['key'];
        $areaCode = $areaInfo['code'];
        $areaName = $areaInfo['name'];
        if (!isset($areas[$areaKey])) {
            $colorIndex = count($areas) % count($colors);
            $areas[$areaKey] = [
                'key' => $areaKey,
                'code' => $areaCode,
                'name' => $areaName,
                'icon' => '',
                'color' => $colors[$colorIndex],
                'total_questions' => 0,
                'correct_questions' => 0,
                'competencies' => [],
                'count' => 0,
            ];
        }
        $areas[$areaKey]['total_questions'] += $comp['total_questions'];
        $areas[$areaKey]['correct_questions'] += $comp['correct_questions'];
        $areas[$areaKey]['competencies'][] = $comp;
        $areas[$areaKey]['count']++;
    }
    foreach ($areas as $key => &$area) {
        $area['percentage'] = $area['total_questions'] > 0
            ? round($area['correct_questions'] / $area['total_questions'] * 100, 1)
            : 0;
    }
    ksort($areas);
    return $areas;
}

// ========================================
// DATA LOADING (same as technical_passport.php)
// ========================================

$radardata = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid);
$summary = \local_competencymanager\report_generator::get_student_summary($userid, $courseid);
$competencies = $radardata['competencies'];

// Detect sector(s) from competency idnumbers.
$validSectors = [
    'AUTOMOBILE', 'AUTOVEICOLO', 'AUTOMAZIONE', 'AUTOM', 'AUTOMAZ',
    'CHIMFARM', 'CHIM', 'CHIMICA', 'FARMACEUTICA',
    'ELETTRICITA', 'ELETTR', 'ELETT',
    'LOGISTICA', 'LOG', 'MECCANICA', 'MECC', 'METALCOSTRUZIONE', 'METAL',
    'GENERICO', 'GEN',
];
$sectorsFound = [];
foreach ($competencies as $comp) {
    $idnumber = $comp['idnumber'] ?? '';
    if (empty($idnumber)) {
        continue;
    }
    $parts = explode('_', $idnumber);
    $potentialSector = strtoupper($parts[0] ?? '');
    if (count($parts) >= 2 && in_array($potentialSector, $validSectors)) {
        $sectorsFound[$potentialSector] = true;
    }
}
$sector = !empty($sectorsFound) ? array_key_first($sectorsFound) : null;

// Fallback: detect sector from course name.
$courseSector = $course ? garage_get_sector_from_course_name($course->fullname) : '';
if (empty($sector) && !empty($courseSector)) {
    $sector = $courseSector;
    $sectorsFound[$courseSector] = true;
}

// Use first detected sector as effective filter.
$effectiveSectorFilter = $sector ?: 'all';

// Filter competencies by sector BEFORE aggregating.
if ($effectiveSectorFilter !== 'all') {
    $normalizedFilter = normalize_sector_name($effectiveSectorFilter);
    $filtered = array_filter($competencies, function($comp) use ($normalizedFilter) {
        $idnumber = $comp['idnumber'] ?? '';
        $parts = explode('_', $idnumber);
        $compSector = normalize_sector_name($parts[0] ?? '');
        return strcasecmp($compSector, $normalizedFilter) === 0;
    });
    if (!empty($filtered)) {
        $competencies = array_values($filtered);
    }
}

// Load area descriptions for detected sectors.
$areaDescriptions = [];
foreach (array_keys($sectorsFound) as $sec) {
    $areaDesc = \local_competencymanager\report_generator::get_area_descriptions_from_framework($sec, $courseid);
    $areaDescriptions = array_merge($areaDesc, $areaDescriptions);
}

// Aggregate competencies by area.
$areasData = garage_aggregate_by_area($competencies, $areaDescriptions, $sector);

// Sector display name.
$sectorDisplay = '';
if ($sector) {
    $sectorDisplay = get_sector_display_name($sector);
}

// Overall percentage.
$overallPct = 0;
if ($summary['total_questions'] > 0) {
    $overallPct = round($summary['correct_questions'] / $summary['total_questions'] * 100, 1);
}

// Global threshold.
$threshold = (int) get_config('local_competencymanager', 'passport_threshold') ?: 60;

// ========================================
// LOAD SELF-ASSESSMENT DATA
// ========================================
$selfAssessmentData = [];
$hasSelfAssessment = false;

// Reuse function from student_report.php if already defined, otherwise query directly.
$dbman = $DB->get_manager();

// Method 1: local_coachmanager_assessment
if ($dbman->table_exists('local_coachmanager_assessment')) {
    $assessments = $DB->get_records('local_coachmanager_assessment', [
        'userid' => $userid,
        'courseid' => $courseid,
    ], 'timecreated DESC', '*', 0, 1);
    if (!empty($assessments)) {
        $assessment = reset($assessments);
        $details = json_decode($assessment->details, true);
        if (!empty($details['competencies'])) {
            foreach ($details['competencies'] as $comp) {
                $selfAssessmentData[$comp['idnumber']] = [
                    'idnumber' => $comp['idnumber'],
                    'bloom_level' => $comp['bloom_level'] ?? 0,
                ];
            }
            $hasSelfAssessment = true;
        }
    }
}

// Method 2: local_selfassessment table
if (!$hasSelfAssessment && $dbman->table_exists('local_selfassessment')) {
    $params = ['userid' => $userid];
    $sectorFilter = '';
    if (!empty($sector)) {
        $sectorFilter = " AND c.idnumber LIKE :sector_prefix";
        $params['sector_prefix'] = $sector . '_%';
    }
    $sql = "SELECT sa.id, sa.competencyid, sa.level, c.idnumber
            FROM {local_selfassessment} sa
            JOIN {competency} c ON sa.competencyid = c.id
            WHERE sa.userid = :userid" . $sectorFilter . "
            ORDER BY c.idnumber ASC";
    $records = $DB->get_records_sql($sql, $params);
    if (!empty($records)) {
        foreach ($records as $rec) {
            $selfAssessmentData[$rec->idnumber] = [
                'idnumber' => $rec->idnumber,
                'bloom_level' => (int)$rec->level,
            ];
        }
        $hasSelfAssessment = true;
    }
}

// ========================================
// LOAD COACH EVALUATION DATA
// ========================================
$coachEvalData = [];
$hasCoachEval = false;
$coachEvaluationData = null;

$currentSector = $sector ?: '';
if (!empty($currentSector)) {
    $coachEvaluations = coach_evaluation_manager::get_student_evaluations($userid, $currentSector);
    if (!empty($coachEvaluations)) {
        // Find evaluation with most ratings.
        $maxRatings = -1;
        foreach ($coachEvaluations as $eval) {
            $stats = coach_evaluation_manager::get_rating_stats($eval->id);
            if ($stats['total'] > $maxRatings) {
                $maxRatings = $stats['total'];
                $coachEvaluationData = $eval;
            }
        }
        if (!$coachEvaluationData) {
            $coachEvaluationData = reset($coachEvaluations);
        }
        // Load ratings by area.
        if ($coachEvaluationData) {
            $ratingsById = coach_evaluation_manager::get_ratings_by_area($coachEvaluationData->id);
            $hasCoachEval = !empty($ratingsById);
            // Flatten to idnumber => bloom level.
            foreach ($ratingsById as $area => $ratings) {
                foreach ($ratings as $r) {
                    $coachEvalData[$r->idnumber] = [
                        'idnumber' => $r->idnumber,
                        'bloom_level' => (int)$r->rating,
                    ];
                }
            }
        }
    }
}

// ========================================
// LOAD EXISTING GARAGE CONFIG
// ========================================
$garageConfig = null;
if ($dbman->table_exists('local_garage_config')) {
    $garageConfig = $DB->get_record('local_garage_config', [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);
}

// Parse saved config.
$savedAreas = [];
$savedCompetencies = [];
$savedExcluded = [];
$savedFormat = 'percentage';
$savedOverlay = 0;
$savedAutoval = 1;
$savedCoachEval = 1;
$savedCustomThreshold = null;

if ($garageConfig) {
    $savedAreas = json_decode($garageConfig->selected_areas, true) ?: [];
    $savedCompetencies = json_decode($garageConfig->selected_competencies, true) ?: [];
    $savedExcluded = json_decode($garageConfig->excluded_competencies, true) ?: [];
    $savedFormat = $garageConfig->display_format ?: 'percentage';
    $savedOverlay = (int)$garageConfig->show_overlay;
    $savedAutoval = (int)$garageConfig->show_autovalutazione;
    $savedCoachEval = (int)$garageConfig->show_coach_eval;
    $savedCustomThreshold = $garageConfig->custom_threshold;
    $savedEnabledSections = json_decode($garageConfig->enabled_sections ?? '[]', true) ?: [];
    $savedSectionOrder = json_decode($garageConfig->section_order ?? '[]', true) ?: [];
}

// All available sections with default order.
$allSections = [
    'valutazione'    => ['label' => 'Panoramica Valutazione', 'icon' => '&#128202;', 'default' => true],
    'progressi'      => ['label' => 'Progressi Certificazione', 'icon' => '&#128200;', 'default' => true],
    'radar_aree'     => ['label' => 'Radar Aree', 'icon' => '&#127919;', 'default' => true],
    'radar_dettagli' => ['label' => 'Radar Dettagli per Area', 'icon' => '&#128269;', 'default' => false],
    'piano'          => ['label' => 'Piano d\'Azione', 'icon' => '&#128203;', 'default' => true],
    'dettagli'       => ['label' => 'Dettagli Competenze', 'icon' => '&#128220;', 'default' => true],
    'dual_radar'     => ['label' => 'Radar Duale (Quiz vs Auto)', 'icon' => '&#128200;', 'default' => false],
    'overlay_radar'  => ['label' => 'Overlay Multi-Fonte', 'icon' => '&#127912;', 'default' => false],
    'gap_analysis'   => ['label' => 'Gap Analysis', 'icon' => '&#9888;', 'default' => false],
    'spunti'         => ['label' => 'Spunti Colloquio', 'icon' => '&#128172;', 'default' => false],
    'suggerimenti'   => ['label' => 'Suggerimenti Rapporto', 'icon' => '&#128161;', 'default' => false],
    'coach_eval'     => ['label' => 'Valutazione Coach', 'icon' => '&#128100;', 'default' => false],
];

// Build ordered section list (saved order or default).
$orderedSections = [];
if (!empty($savedSectionOrder)) {
    foreach ($savedSectionOrder as $key) {
        if (isset($allSections[$key])) {
            $orderedSections[$key] = $allSections[$key];
        }
    }
    // Add any missing sections at the end.
    foreach ($allSections as $key => $info) {
        if (!isset($orderedSections[$key])) {
            $orderedSections[$key] = $info;
        }
    }
} else {
    $orderedSections = $allSections;
}

// Determine which sections are enabled.
$enabledSections = [];
if (!empty($savedEnabledSections)) {
    $enabledSections = $savedEnabledSections;
} else {
    // Default: enable sections marked as default.
    foreach ($allSections as $key => $info) {
        if ($info['default']) {
            $enabledSections[] = $key;
        }
    }
}

// Effective threshold (custom or global).
$effectiveThreshold = ($savedCustomThreshold !== null) ? (int)$savedCustomThreshold : $threshold;

// Count stats.
$totalCompetencies = 0;
$aboveThreshold = 0;
foreach ($areasData as $areaKey => $area) {
    foreach ($area['competencies'] as $comp) {
        $totalCompetencies++;
        $compPct = $comp['total_questions'] > 0
            ? round($comp['correct_questions'] / $comp['total_questions'] * 100, 1)
            : 0;
        if ($compPct >= $effectiveThreshold) {
            $aboveThreshold++;
        }
    }
}

// ========================================
// OUTPUT
// ========================================
echo $OUTPUT->header();
?>

<style>
/* ========================================
   GARAGE FTM STYLES
   Same visual language as technical_passport.php
   ======================================== */
.garage-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header card */
.garage-header {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 24px 30px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.garage-header-left h1 {
    margin: 0 0 4px 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1a2e;
}
.garage-header-left .garage-subtitle {
    color: #666;
    font-size: 0.95rem;
    margin: 0;
}
.garage-header-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Buttons (same as passport-btn) */
.garage-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.garage-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-decoration: none;
}
.garage-btn-secondary {
    background: #6c757d;
    color: #fff;
}
.garage-btn-secondary:hover {
    background: #5a6268;
    color: #fff;
}
.garage-btn-primary {
    background: #0066cc;
    color: #fff;
}
.garage-btn-primary:hover {
    background: #0052a3;
    color: #fff;
}
.garage-btn-success {
    background: #28a745;
    color: #fff;
}
.garage-btn-success:hover {
    background: #218838;
    color: #fff;
}

/* Cards */
.garage-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 24px;
}
.garage-card h2 {
    font-size: 1.2rem;
    color: #333;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f4f8;
}

/* Save feedback */
.garage-save-feedback {
    display: none;
    padding: 10px 20px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-weight: 600;
    text-align: center;
}
.garage-save-feedback.success {
    background: #d4edda;
    color: #155724;
    display: block;
}
.garage-save-feedback.error {
    background: #f8d7da;
    color: #721c24;
    display: block;
}

/* Configuration panel - two columns */
.garage-config-grid {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}
.garage-config-left {
    flex: 1;
    min-width: 320px;
}
.garage-config-right {
    flex: 0 0 320px;
    min-width: 280px;
}

/* Form fields */
.garage-field {
    margin-bottom: 20px;
}
.garage-field label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
    font-size: 0.9rem;
}
.garage-field .garage-hint {
    color: #888;
    font-size: 0.8rem;
    margin-top: 4px;
}
.garage-field input[type="number"] {
    width: 100px;
    padding: 6px 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    font-family: inherit;
}
.garage-field input[type="number"]:focus {
    border-color: #0066cc;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
}

/* Radio and checkbox options */
.garage-radio-group,
.garage-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.garage-radio-option,
.garage-checkbox-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    color: #333;
}
.garage-radio-option input[type="radio"],
.garage-checkbox-option input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #0066cc;
}

/* Info panel (right column) */
.garage-info-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e9ecef;
}
.garage-info-panel h3 {
    margin: 0 0 16px 0;
    font-size: 1rem;
    color: #333;
}
.garage-info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
}
.garage-info-row:last-child {
    border-bottom: none;
}
.garage-info-row .info-label {
    color: #666;
}
.garage-info-row .info-value {
    font-weight: 600;
    color: #1a1a2e;
}
.garage-info-row .info-value.available {
    color: #28a745;
}
.garage-info-row .info-value.unavailable {
    color: #dc3545;
}

/* Percentage badge (same as passport) */
.pct-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.85rem;
    color: #fff;
    min-width: 55px;
    text-align: center;
}
.pct-excellent { background: #27ae60; }
.pct-good { background: #3498db; }
.pct-sufficient { background: #f39c12; }
.pct-critical { background: #c0392b; }

/* ========================================
   COMPETENCY SELECTOR
   ======================================== */
.garage-selector-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    align-items: center;
}
.garage-selector-info {
    color: #666;
    font-size: 0.88rem;
    margin-bottom: 16px;
    line-height: 1.5;
}

/* Area block */
.garage-area-block {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.garage-area-block:hover {
    border-color: #c8d0d8;
}
.garage-area-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: #f8f9fa;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}
.garage-area-header:hover {
    background: #f0f4f8;
}
.garage-area-header input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #0066cc;
    flex-shrink: 0;
}
.garage-area-name {
    flex: 1;
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}
.garage-area-toggle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e9ecef;
    color: #666;
    font-size: 0.8rem;
    transition: all 0.3s;
    flex-shrink: 0;
}
.garage-area-toggle.expanded {
    transform: rotate(180deg);
}

/* Competency list inside area */
.garage-comp-list {
    display: none;
    padding: 0;
    border-top: 1px solid #e9ecef;
}
.garage-comp-list.show {
    display: block;
}
.garage-comp-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px 10px 48px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.88rem;
    transition: background 0.15s;
}
.garage-comp-row:last-child {
    border-bottom: none;
}
.garage-comp-row:hover {
    background: #f8f9fa;
}
.garage-comp-row input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #0066cc;
    flex-shrink: 0;
}
.garage-comp-name {
    flex: 1;
    color: #333;
    line-height: 1.3;
}
.garage-comp-scores {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-shrink: 0;
    font-size: 0.82rem;
}
.garage-comp-score-item {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.garage-comp-score-item .score-label {
    color: #888;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.garage-comp-score-item .score-value {
    font-weight: 600;
}
.garage-comp-row.below-threshold {
    opacity: 0.55;
}
.garage-comp-row.below-threshold .garage-comp-name {
    color: #999;
}

/* Bloom level display */
.bloom-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    font-size: 0.72rem;
    font-weight: 700;
    color: #fff;
}
.bloom-tag-0 { background: #ccc; }
.bloom-tag-1 { background: #e74c3c; }
.bloom-tag-2 { background: #f39c12; }
.bloom-tag-3 { background: #f1c40f; color: #333; }
.bloom-tag-4 { background: #2ecc71; }
.bloom-tag-5 { background: #3498db; }
.bloom-tag-6 { background: #9b59b6; }

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    .garage-config-grid {
        flex-direction: column;
    }
    .garage-config-right {
        flex: 1;
    }
    .garage-comp-row {
        padding-left: 28px;
    }
    .garage-comp-scores {
        flex-wrap: wrap;
    }
}
</style>

<div class="garage-container">

    <!-- Header -->
    <div class="garage-header">
        <div class="garage-header-left">
            <h1>Garage FTM</h1>
            <p class="garage-subtitle">
                <?php echo s(fullname($student)); ?>
                <?php if ($sectorDisplay): ?>
                    &mdash; Settore: <?php echo s($sectorDisplay); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="garage-header-right">
            <a href="<?php echo (new moodle_url('/local/competencymanager/student_report.php', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]))->out(false); ?>" class="garage-btn garage-btn-secondary">
                &#8592; Torna allo Student Report
            </a>
            <?php if ($canEvaluate): ?>
            <button type="button" class="garage-btn garage-btn-success" onclick="saveGarageConfig()">
                Salva Configurazione
            </button>
            <?php endif; ?>
            <a href="<?php echo (new moodle_url('/local/competencymanager/technical_passport.php', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]))->out(false); ?>" class="garage-btn garage-btn-primary">
                Anteprima Passaporto &#8594;
            </a>
        </div>
    </div>

    <!-- Save feedback -->
    <div id="garage-save-feedback" class="garage-save-feedback"></div>

    <!-- Configuration panel -->
    <div class="garage-card">
        <h2>Configurazione Passaporto</h2>
        <div class="garage-config-grid">

            <!-- Left column: options -->
            <div class="garage-config-left">

                <!-- Threshold -->
                <div class="garage-field">
                    <label>Soglia minima</label>
                    <div class="garage-hint" style="margin-bottom:8px;">
                        Soglia globale: <strong><?php echo $threshold; ?>%</strong> (impostata dall'amministratore)
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="garage-checkbox-option" style="margin:0;">
                            <input type="checkbox" id="garage-use-custom-threshold"
                                   <?php if ($savedCustomThreshold !== null) echo 'checked'; ?>
                                   onchange="toggleCustomThreshold()">
                            Soglia personalizzata:
                        </label>
                        <input type="number" id="garage-custom-threshold"
                               min="0" max="100"
                               value="<?php echo ($savedCustomThreshold !== null) ? (int)$savedCustomThreshold : $threshold; ?>"
                               <?php if ($savedCustomThreshold === null) echo 'disabled'; ?>
                               style="width:80px;">
                        <span>%</span>
                    </div>
                </div>

                <!-- Display format -->
                <div class="garage-field">
                    <label>Formato visualizzazione</label>
                    <div class="garage-radio-group">
                        <label class="garage-radio-option">
                            <input type="radio" name="display_format" value="percentage"
                                   <?php if ($savedFormat === 'percentage') echo 'checked'; ?>>
                            Percentuali numeriche (es. 85%)
                        </label>
                        <label class="garage-radio-option">
                            <input type="radio" name="display_format" value="qualitative"
                                   <?php if ($savedFormat === 'qualitative') echo 'checked'; ?>>
                            Scala qualitativa (es. Eccellente, Buono, Sufficiente)
                        </label>
                    </div>
                </div>

                <!-- Overlay checkbox -->
                <div class="garage-field">
                    <label>Visualizzazione dati</label>
                    <div class="garage-checkbox-group">
                        <label class="garage-checkbox-option">
                            <input type="checkbox" id="garage-show-overlay"
                                   <?php if ($savedOverlay) echo 'checked'; ?>>
                            Mostra radar overlay (Quiz + Autovalutazione + Valutazione Coach)
                        </label>
                        <label class="garage-checkbox-option">
                            <input type="checkbox" id="garage-show-autoval"
                                   <?php if ($savedAutoval) echo 'checked'; ?>>
                            Mostra Autovalutazione
                        </label>
                        <label class="garage-checkbox-option">
                            <input type="checkbox" id="garage-show-coach-eval"
                                   <?php if ($savedCoachEval) echo 'checked'; ?>>
                            Mostra Valutazione Coach
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right column: info panel -->
            <div class="garage-config-right">
                <div class="garage-info-panel">
                    <h3>Info studente</h3>
                    <div class="garage-info-row">
                        <span class="info-label">Settore rilevato</span>
                        <span class="info-value"><?php echo $sectorDisplay ? s($sectorDisplay) : '-'; ?></span>
                    </div>
                    <div class="garage-info-row">
                        <span class="info-label">Quiz completati</span>
                        <span class="info-value"><?php echo count($areasData); ?> aree</span>
                    </div>
                    <div class="garage-info-row">
                        <span class="info-label">Competenze totali</span>
                        <span class="info-value">
                            <?php echo $totalCompetencies; ?>
                            (sopra soglia: <?php echo $aboveThreshold; ?>)
                        </span>
                    </div>
                    <div class="garage-info-row">
                        <span class="info-label">Punteggio globale</span>
                        <span class="info-value" style="color: <?php
                            echo $overallPct >= 80 ? '#27ae60' : ($overallPct >= 60 ? '#3498db' : ($overallPct >= 40 ? '#f39c12' : '#c0392b'));
                        ?>;"><?php echo $overallPct; ?>%</span>
                    </div>
                    <div class="garage-info-row">
                        <span class="info-label">Autovalutazione</span>
                        <span class="info-value <?php echo $hasSelfAssessment ? 'available' : 'unavailable'; ?>">
                            <?php echo $hasSelfAssessment ? 'Disponibile' : 'Non disponibile'; ?>
                        </span>
                    </div>
                    <div class="garage-info-row">
                        <span class="info-label">Valutazione coach</span>
                        <span class="info-value <?php echo $hasCoachEval ? 'available' : 'unavailable'; ?>">
                            <?php echo $hasCoachEval ? 'Disponibile' : 'Non disponibile'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Competency Selector -->
    <div class="garage-card">
        <h2>Seleziona Aree e Competenze</h2>

        <p class="garage-selector-info">
            Seleziona le aree e competenze da includere nel passaporto.
            Solo le competenze con punteggio &ge; <strong id="threshold-display"><?php echo $effectiveThreshold; ?></strong>% sono selezionate per default.
            Le competenze sotto soglia possono essere incluse manualmente.
        </p>

        <div class="garage-selector-actions">
            <button type="button" class="garage-btn garage-btn-primary" onclick="selectAll(true)" style="font-size:0.82rem;padding:6px 14px;">
                Seleziona Tutto
            </button>
            <button type="button" class="garage-btn garage-btn-secondary" onclick="selectAll(false)" style="font-size:0.82rem;padding:6px 14px;">
                Deseleziona Tutto
            </button>
            <span style="color:#888;font-size:0.82rem;margin-left:8px;">
                Selezionate: <strong id="selected-count">0</strong> / <?php echo $totalCompetencies; ?>
            </span>
        </div>

        <?php if (empty($areasData)): ?>
        <p style="color: #888; text-align: center; padding: 30px;">
            Nessun dato quiz disponibile per questo studente.
        </p>
        <?php else: ?>
        <?php foreach ($areasData as $areaKey => $area):
            $areaPct = $area['percentage'];
            $areaPctClass = $areaPct >= 80 ? 'pct-excellent' : ($areaPct >= 60 ? 'pct-good' : ($areaPct >= 40 ? 'pct-sufficient' : 'pct-critical'));

            // Determine if area should be checked.
            $areaChecked = false;
            if ($garageConfig) {
                // If we have saved config, check if area is in saved_areas.
                $areaChecked = in_array($areaKey, $savedAreas);
            } else {
                // Default: area is checked if percentage >= threshold.
                $areaChecked = ($areaPct >= $effectiveThreshold);
            }
        ?>
        <div class="garage-area-block" data-area-key="<?php echo s($areaKey); ?>">
            <div class="garage-area-header" onclick="toggleAreaExpand(this)">
                <input type="checkbox"
                       class="garage-area-checkbox"
                       data-area-key="<?php echo s($areaKey); ?>"
                       <?php if ($areaChecked) echo 'checked'; ?>
                       onclick="event.stopPropagation(); toggleAreaCheckbox(this);">
                <span class="garage-area-name">
                    <?php echo s($area['code']); ?>. <?php echo s($area['name']); ?>
                    <span style="color:#888;font-weight:400;font-size:0.82rem;margin-left:4px;">
                        (<?php echo (int)$area['count']; ?> competenze)
                    </span>
                </span>
                <span class="pct-badge <?php echo $areaPctClass; ?>"><?php echo $areaPct; ?>%</span>
                <span class="garage-area-toggle">&#9660;</span>
            </div>
            <div class="garage-comp-list">
                <?php foreach ($area['competencies'] as $comp):
                    $compIdnumber = $comp['idnumber'] ?? '';
                    $compName = $comp['name'] ?? $compIdnumber;
                    $compTotal = (int)($comp['total_questions'] ?? 0);
                    $compCorrect = (int)($comp['correct_questions'] ?? 0);
                    $compPct = $compTotal > 0 ? round($compCorrect / $compTotal * 100, 1) : 0;
                    $isBelowThreshold = ($compPct < $effectiveThreshold);

                    // Determine if competency is checked.
                    if ($garageConfig) {
                        $compChecked = in_array($compIdnumber, $savedCompetencies);
                    } else {
                        $compChecked = !$isBelowThreshold;
                    }

                    $pctColor = $compPct >= 80 ? '#27ae60' : ($compPct >= 60 ? '#3498db' : ($compPct >= 40 ? '#f39c12' : '#c0392b'));

                    // Self-assessment for this competency.
                    $saBloom = isset($selfAssessmentData[$compIdnumber]) ? $selfAssessmentData[$compIdnumber]['bloom_level'] : null;

                    // Coach eval for this competency.
                    $ceBloom = isset($coachEvalData[$compIdnumber]) ? $coachEvalData[$compIdnumber]['bloom_level'] : null;
                ?>
                <div class="garage-comp-row <?php if ($isBelowThreshold) echo 'below-threshold'; ?>"
                     data-idnumber="<?php echo s($compIdnumber); ?>"
                     data-pct="<?php echo $compPct; ?>">
                    <input type="checkbox"
                           class="garage-comp-checkbox"
                           data-area-key="<?php echo s($areaKey); ?>"
                           data-idnumber="<?php echo s($compIdnumber); ?>"
                           <?php if ($compChecked) echo 'checked'; ?>>
                    <span class="garage-comp-name" title="<?php echo s($compIdnumber); ?>">
                        <?php echo s($compName); ?>
                    </span>
                    <div class="garage-comp-scores">
                        <!-- Quiz score -->
                        <span class="garage-comp-score-item">
                            <span class="score-label">Quiz</span>
                            <span class="score-value" style="color:<?php echo $pctColor; ?>;">
                                <?php echo $compCorrect; ?>/<?php echo $compTotal; ?>
                                (<?php echo $compPct; ?>%)
                            </span>
                        </span>
                        <?php if ($saBloom !== null): ?>
                        <!-- Self-assessment -->
                        <span class="garage-comp-score-item">
                            <span class="score-label">Autoval</span>
                            <span class="bloom-tag bloom-tag-<?php echo (int)$saBloom; ?>"><?php echo (int)$saBloom; ?></span>
                        </span>
                        <?php endif; ?>
                        <?php if ($ceBloom !== null): ?>
                        <!-- Coach eval -->
                        <span class="garage-comp-score-item">
                            <span class="score-label">Coach</span>
                            <span class="bloom-tag bloom-tag-<?php echo (int)$ceBloom; ?>"><?php echo (int)$ceBloom; ?></span>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Sections & Order -->
    <div class="garage-card" style="margin-bottom: 24px;">
        <h2 style="font-size: 1.2rem; color: #333; margin: 0 0 8px 0;">Sezioni e Ordine Stampa</h2>
        <p style="font-size: 0.85rem; color: #888; margin-bottom: 16px;">Attiva/disattiva le sezioni e trascinale per cambiare l'ordine di stampa nel passaporto.</p>

        <div id="garage-sections-list" style="display: flex; flex-direction: column; gap: 6px;">
            <?php $idx = 1; foreach ($orderedSections as $sKey => $sInfo):
                $isEnabled = in_array($sKey, $enabledSections);
            ?>
            <div class="garage-section-item" data-section="<?php echo s($sKey); ?>" draggable="true"
                 style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: <?php echo $isEnabled ? '#fff' : '#f9fafb'; ?>; border: 1px solid <?php echo $isEnabled ? '#dee2e6' : '#eee'; ?>; border-radius: 6px; cursor: grab; user-select: none;">
                <span class="drag-handle" style="color: #aaa; font-size: 16px; cursor: grab;">&#9776;</span>
                <span style="background: #f0f0f0; color: #666; border-radius: 4px; padding: 1px 8px; font-size: 11px; min-width: 22px; text-align: center;"><?php echo $idx; ?></span>
                <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; flex: 1; font-size: 0.9rem;">
                    <input type="checkbox" class="garage-section-checkbox" data-section="<?php echo s($sKey); ?>"
                           <?php echo $isEnabled ? 'checked' : ''; ?>
                           style="width: 16px; height: 16px; cursor: pointer;">
                    <span><?php echo $sInfo['icon']; ?></span>
                    <span style="color: <?php echo $isEnabled ? '#333' : '#aaa'; ?>;"><?php echo s($sInfo['label']); ?></span>
                </label>
            </div>
            <?php $idx++; endforeach; ?>
        </div>
    </div>

    <!-- Preview button (bottom) -->
    <?php if (!empty($areasData)): ?>
    <div style="text-align: center; margin-bottom: 30px;">
        <a href="<?php echo (new moodle_url('/local/competencymanager/technical_passport.php', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]))->out(false); ?>" class="garage-btn garage-btn-primary" style="font-size: 1rem; padding: 12px 30px;">
            Apri Anteprima Passaporto &#8594;
        </a>
    </div>
    <?php endif; ?>

</div>

<script>
(function() {
    // Update selected count on load.
    updateSelectedCount();

    // ---- Drag & Drop for sections ----
    var list = document.getElementById('garage-sections-list');
    if (list) {
        var dragItem = null;

        list.addEventListener('dragstart', function(e) {
            dragItem = e.target.closest('.garage-section-item');
            if (dragItem) {
                dragItem.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
            }
        });

        list.addEventListener('dragend', function(e) {
            if (dragItem) {
                dragItem.style.opacity = '1';
                dragItem = null;
            }
            // Update order numbers.
            list.querySelectorAll('.garage-section-item').forEach(function(item, idx) {
                var numSpan = item.querySelector('span:nth-child(2)');
                if (numSpan) numSpan.textContent = idx + 1;
            });
        });

        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var target = e.target.closest('.garage-section-item');
            if (target && target !== dragItem) {
                var rect = target.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    list.insertBefore(dragItem, target);
                } else {
                    list.insertBefore(dragItem, target.nextSibling);
                }
            }
        });

        // Checkbox visual update.
        list.addEventListener('change', function(e) {
            if (e.target.classList.contains('garage-section-checkbox')) {
                var item = e.target.closest('.garage-section-item');
                var label = item.querySelector('label span:last-child');
                if (e.target.checked) {
                    item.style.background = '#fff';
                    item.style.borderColor = '#dee2e6';
                    if (label) label.style.color = '#333';
                } else {
                    item.style.background = '#f9fafb';
                    item.style.borderColor = '#eee';
                    if (label) label.style.color = '#aaa';
                }
            }
        });
    }
})();

/**
 * Get enabled section keys from checkboxes.
 */
function getEnabledSections() {
    var enabled = [];
    document.querySelectorAll('.garage-section-checkbox:checked').forEach(function(cb) {
        enabled.push(cb.getAttribute('data-section'));
    });
    return enabled;
}

/**
 * Get section order from DOM order.
 */
function getSectionOrder() {
    var order = [];
    document.querySelectorAll('.garage-section-item').forEach(function(item) {
        order.push(item.getAttribute('data-section'));
    });
    return order;
}

/**
 * Toggle custom threshold input enabled/disabled.
 */
function toggleCustomThreshold() {
    var useCustom = document.getElementById('garage-use-custom-threshold').checked;
    var input = document.getElementById('garage-custom-threshold');
    input.disabled = !useCustom;
    if (!useCustom) {
        input.value = <?php echo $threshold; ?>;
    }
    // Update threshold display.
    var newThreshold = useCustom ? parseInt(input.value) || <?php echo $threshold; ?> : <?php echo $threshold; ?>;
    document.getElementById('threshold-display').textContent = newThreshold;
}

/**
 * Toggle expand/collapse for area competency list.
 */
function toggleAreaExpand(headerEl) {
    var block = headerEl.closest('.garage-area-block');
    var list = block.querySelector('.garage-comp-list');
    var toggle = block.querySelector('.garage-area-toggle');

    if (list.classList.contains('show')) {
        list.classList.remove('show');
        toggle.classList.remove('expanded');
    } else {
        list.classList.add('show');
        toggle.classList.add('expanded');
    }
}

/**
 * Toggle all competency checkboxes when area checkbox is toggled.
 */
function toggleAreaCheckbox(areaCheckbox) {
    var areaKey = areaCheckbox.getAttribute('data-area-key');
    var checked = areaCheckbox.checked;
    var block = areaCheckbox.closest('.garage-area-block');
    var compCheckboxes = block.querySelectorAll('.garage-comp-checkbox');
    compCheckboxes.forEach(function(cb) {
        cb.checked = checked;
    });
    updateSelectedCount();
}

/**
 * Select/deselect all competencies.
 */
function selectAll(selectState) {
    // Toggle all area checkboxes.
    document.querySelectorAll('.garage-area-checkbox').forEach(function(cb) {
        cb.checked = selectState;
    });
    // Toggle all competency checkboxes.
    document.querySelectorAll('.garage-comp-checkbox').forEach(function(cb) {
        cb.checked = selectState;
    });
    updateSelectedCount();
}

/**
 * Update the selected count display and sync area checkbox states.
 */
function updateSelectedCount() {
    var total = document.querySelectorAll('.garage-comp-checkbox').length;
    var checked = document.querySelectorAll('.garage-comp-checkbox:checked').length;
    var countEl = document.getElementById('selected-count');
    if (countEl) {
        countEl.textContent = checked;
    }

    // Sync area checkboxes: if all children checked -> area checked.
    document.querySelectorAll('.garage-area-block').forEach(function(block) {
        var areaCb = block.querySelector('.garage-area-checkbox');
        var compCbs = block.querySelectorAll('.garage-comp-checkbox');
        var compChecked = block.querySelectorAll('.garage-comp-checkbox:checked');
        if (compCbs.length > 0) {
            areaCb.checked = (compChecked.length === compCbs.length);
            areaCb.indeterminate = (compChecked.length > 0 && compChecked.length < compCbs.length);
        }
    });
}

// Listen for individual competency checkbox changes.
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('garage-comp-checkbox')) {
        updateSelectedCount();
    }
});

<?php if ($canEvaluate): ?>
/**
 * Save garage configuration via AJAX.
 */
function saveGarageConfig() {
    // Collect selected areas.
    var selectedAreas = [];
    document.querySelectorAll('.garage-area-checkbox:checked').forEach(function(cb) {
        selectedAreas.push(cb.getAttribute('data-area-key'));
    });

    // Collect selected and excluded competencies.
    var selectedCompetencies = [];
    var excludedCompetencies = [];
    document.querySelectorAll('.garage-comp-checkbox').forEach(function(cb) {
        var idnumber = cb.getAttribute('data-idnumber');
        if (cb.checked) {
            selectedCompetencies.push(idnumber);
        } else {
            excludedCompetencies.push(idnumber);
        }
    });

    // Display format.
    var displayFormat = 'percentage';
    var formatRadios = document.querySelectorAll('input[name="display_format"]');
    formatRadios.forEach(function(r) {
        if (r.checked) displayFormat = r.value;
    });

    // Toggles.
    var showOverlay = document.getElementById('garage-show-overlay').checked ? 1 : 0;
    var showAutoval = document.getElementById('garage-show-autoval').checked ? 1 : 0;
    var showCoachEval = document.getElementById('garage-show-coach-eval').checked ? 1 : 0;

    // Custom threshold.
    var customThreshold = '';
    if (document.getElementById('garage-use-custom-threshold').checked) {
        customThreshold = document.getElementById('garage-custom-threshold').value;
    }

    var feedback = document.getElementById('garage-save-feedback');
    feedback.className = 'garage-save-feedback';
    feedback.style.display = 'none';
    feedback.textContent = '';

    var xhr = new XMLHttpRequest();
    var url = '<?php echo (new moodle_url('/local/competencymanager/ajax_save_garage_config.php'))->out(false); ?>';
    var params = 'sesskey=<?php echo sesskey(); ?>'
        + '&userid=<?php echo $userid; ?>'
        + '&courseid=<?php echo $courseid; ?>'
        + '&selected_areas=' + encodeURIComponent(JSON.stringify(selectedAreas))
        + '&selected_competencies=' + encodeURIComponent(JSON.stringify(selectedCompetencies))
        + '&excluded_competencies=' + encodeURIComponent(JSON.stringify(excludedCompetencies))
        + '&display_format=' + encodeURIComponent(displayFormat)
        + '&show_overlay=' + showOverlay
        + '&show_autovalutazione=' + showAutoval
        + '&show_coach_eval=' + showCoachEval
        + '&custom_threshold=' + encodeURIComponent(customThreshold)
        + '&enabled_sections=' + encodeURIComponent(JSON.stringify(getEnabledSections()))
        + '&section_order=' + encodeURIComponent(JSON.stringify(getSectionOrder()));

    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    feedback.className = 'garage-save-feedback success';
                    feedback.textContent = resp.message || 'Configurazione salvata con successo.';
                } else {
                    feedback.className = 'garage-save-feedback error';
                    feedback.textContent = resp.message || 'Errore nel salvataggio.';
                }
            } catch (e) {
                feedback.className = 'garage-save-feedback error';
                feedback.textContent = 'Errore di comunicazione con il server.';
            }
            feedback.style.display = 'block';
            // Scroll to top to show feedback.
            window.scrollTo({top: 0, behavior: 'smooth'});
            // Auto-hide after 5 seconds.
            setTimeout(function() {
                feedback.style.display = 'none';
            }, 5000);
        }
    };

    xhr.send(params);
}
<?php endif; ?>
</script>

<?php
echo $OUTPUT->footer();
