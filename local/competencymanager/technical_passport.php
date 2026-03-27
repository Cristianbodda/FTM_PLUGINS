<?php
/**
 * Passaporto Tecnico - Technical Passport page
 *
 * Shows a radar chart of quiz areas (A-G) with coach comments per area.
 * Reuses the same data loading and aggregation logic as student_report.php.
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$cm_sector_filter = optional_param('cm_sector', 'all', PARAM_ALPHANUMEXT);

$student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$course = $courseid ? get_course($courseid) : null;

if ($courseid) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

// Check permissions.
$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();
$isownreport = ($USER->id == $userid);

if (!$canviewall && !$isadmin && !$isownreport) {
    throw new moodle_exception('nopermissions', 'error', '', 'view technical passport');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/technical_passport.php', [
    'userid' => $userid,
    'courseid' => $courseid,
]));
$PAGE->set_title('Passaporto Tecnico - ' . fullname($student));
$PAGE->set_heading('Passaporto Tecnico');
$PAGE->set_pagelayout('report');

// ========================================
// HELPER FUNCTIONS (same as student_report.php)
// ========================================

/**
 * Aggregate competencies by area.
 *
 * @param array $competencies Raw competency data from report_generator.
 * @param array $areaDescriptions Area descriptions from framework.
 * @param string $sector Sector code.
 * @return array Aggregated areas keyed by area key (e.g. AUTOMOBILE_A).
 */
function passport_aggregate_by_area($competencies, $areaDescriptions, $sector = '') {
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

/**
 * Generate an SVG radar chart.
 *
 * @param array $data Array of ['label' => string, 'value' => float 0-100].
 * @param string $title Chart title.
 * @param int $size Chart size in pixels.
 * @param string $fillColor Polygon fill color.
 * @param string $strokeColor Polygon stroke color.
 * @param int $labelFontSize Font size for labels.
 * @param int $maxLabelLen Max characters for label truncation.
 * @return string SVG markup.
 */
function passport_generate_svg_radar($data, $title = '', $size = 400, $fillColor = 'rgba(102,126,234,0.3)', $strokeColor = '#667eea', $labelFontSize = 10, $maxLabelLen = 250) {
    if (empty($data)) {
        return '<p style="color:#888;">Nessun dato disponibile</p>';
    }

    $horizontalPadding = 180;
    $svgWidth = $size + (2 * $horizontalPadding);
    $cx = $horizontalPadding + ($size / 2);
    $cy = $size / 2;
    $margin = max(70, $labelFontSize * 8);
    $radius = ($size / 2) - $margin;
    $n = count($data);

    // Fallback: single item -> bar chart.
    if ($n == 1) {
        return passport_generate_svg_bar_chart($data, $title, $size);
    }

    // For 2 elements: duplicate to create a diamond.
    if ($n == 2) {
        $data = array_values($data);
        $data = [
            $data[0],
            $data[1],
            ['label' => $data[0]['label'], 'value' => $data[0]['value'], '_duplicate' => true],
            ['label' => $data[1]['label'], 'value' => $data[1]['value'], '_duplicate' => true],
        ];
        $n = 4;
    }

    $angleStep = (2 * M_PI) / $n;

    $svg = '<svg width="' . $svgWidth . '" height="' . ($size + 50) . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';

    if ($title) {
        $svg .= '<text x="' . $cx . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">'
            . htmlspecialchars($title) . '</text>';
    }
    $offsetY = $title ? 35 : 10;

    // Grid circles.
    for ($p = 20; $p <= 100; $p += 20) {
        $r = $radius * ($p / 100);
        $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . $r
            . '" fill="none" stroke="#e0e0e0" stroke-width="1"/>';
        if ($p == 100) {
            $svg .= '<text x="' . ($cx + 5) . '" y="' . ($cy + $offsetY - $r - 3)
                . '" font-size="8" fill="#999">' . $p . '%</text>';
        }
    }

    // Threshold circles: 60% sufficient, 80% excellent.
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.6)
        . '" fill="none" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.8)
        . '" fill="none" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';

    $points = [];
    $i = 0;
    foreach ($data as $item) {
        $angle = ($i * $angleStep) - (M_PI / 2);

        // Axis line.
        $axisX = $cx + $radius * cos($angle);
        $axisY = ($cy + $offsetY) + $radius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . ($cy + $offsetY) . '" x2="' . $axisX . '" y2="' . $axisY
            . '" stroke="#ddd" stroke-width="1"/>';

        // Data point.
        $value = min(100, max(0, $item['value']));
        $pointRadius = $radius * ($value / 100);
        $pointX = $cx + $pointRadius * cos($angle);
        $pointY = ($cy + $offsetY) + $pointRadius * sin($angle);
        $points[] = $pointX . ',' . $pointY;

        // Labels.
        $labelRadius = $radius + 20;
        $labelX = $cx + $labelRadius * cos($angle);
        $labelY = ($cy + $offsetY) + $labelRadius * sin($angle);

        $anchor = 'middle';
        if ($labelX < $cx - 20) {
            $anchor = 'end';
        } else if ($labelX > $cx + 20) {
            $anchor = 'start';
        }

        $labelColor = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));

        $isDuplicate = !empty($item['_duplicate']);
        if (!$isDuplicate) {
            $displayLabel = mb_strlen($item['label']) > $maxLabelLen
                ? mb_substr($item['label'], 0, $maxLabelLen - 2) . '...'
                : $item['label'];

            $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="' . $anchor
                . '" font-size="' . $labelFontSize . '" fill="#333">' . htmlspecialchars($displayLabel) . '</text>';
            $svg .= '<text x="' . $labelX . '" y="' . ($labelY + $labelFontSize + 2) . '" text-anchor="' . $anchor
                . '" font-size="' . ($labelFontSize + 1) . '" font-weight="bold" fill="' . $labelColor . '">'
                . $value . '%</text>';
        }

        $i++;
    }

    // Polygon + dots.
    if (!empty($points)) {
        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="' . $fillColor
            . '" stroke="' . $strokeColor . '" stroke-width="2"/>';
        foreach ($points as $point) {
            list($px, $py) = explode(',', $point);
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="5" fill="' . $strokeColor
                . '" stroke="white" stroke-width="2"/>';
        }
    }

    // Legend.
    $legendY = $size + $offsetY + 10;
    $svg .= '<line x1="' . ($cx - 100) . '" y1="' . $legendY . '" x2="' . ($cx - 80) . '" y2="' . $legendY
        . '" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx - 75) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">60% Sufficiente</text>';
    $svg .= '<line x1="' . ($cx + 20) . '" y1="' . $legendY . '" x2="' . ($cx + 40) . '" y2="' . $legendY
        . '" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx + 45) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">80% Eccellente</text>';

    $svg .= '</svg>';
    return $svg;
}

/**
 * Fallback bar chart for a single data item.
 *
 * @param array $data Array of ['label' => string, 'value' => float 0-100].
 * @param string $title Chart title.
 * @param int $width Chart width.
 * @return string SVG markup.
 */
function passport_generate_svg_bar_chart($data, $title = '', $width = 400) {
    $barHeight = 30;
    $padding = 10;
    $labelWidth = 100;
    $height = count($data) * ($barHeight + $padding) + 60;

    $svg = '<svg width="' . $width . '" height="' . $height
        . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';

    if ($title) {
        $svg .= '<text x="' . ($width / 2) . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">'
            . htmlspecialchars($title) . '</text>';
    }

    $y = 40;
    foreach ($data as $item) {
        $value = min(100, max(0, $item['value']));
        $barWidth = ($width - $labelWidth - 50) * ($value / 100);
        $color = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));

        $svg .= '<text x="5" y="' . ($y + 20) . '" font-size="10" fill="#333">'
            . htmlspecialchars(substr($item['label'], 0, 15)) . '</text>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . ($width - $labelWidth - 50)
            . '" height="' . $barHeight . '" fill="#f0f0f0" rx="4"/>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . $barWidth
            . '" height="' . $barHeight . '" fill="' . $color . '" rx="4"/>';
        $svg .= '<text x="' . ($width - 40) . '" y="' . ($y + 20)
            . '" font-size="11" font-weight="bold" fill="' . $color . '">' . $value . '%</text>';

        $y += $barHeight + $padding;
    }

    $svg .= '</svg>';
    return $svg;
}

// ========================================
// DATA LOADING
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
$courseSector = $course ? get_sector_from_course_name($course->fullname) : '';
if (empty($sector) && !empty($courseSector)) {
    $sector = $courseSector;
    $sectorsFound[$courseSector] = true;
}

// Effective sector filter: use cm_sector param, or auto-detect if single sector.
if ($cm_sector_filter !== 'all') {
    $effectiveSectorFilter = $cm_sector_filter;
} else if (count($sectorsFound) === 1 && !empty($sector)) {
    $effectiveSectorFilter = $sector;
} else {
    $effectiveSectorFilter = 'all';
}

// Filter competencies by sector BEFORE aggregating (same logic as student_report.php).
if ($effectiveSectorFilter !== 'all') {
    $normalizedFilter = normalize_sector_name($effectiveSectorFilter);
    $competencies = array_filter($competencies, function($comp) use ($normalizedFilter) {
        $idnumber = $comp['idnumber'] ?? '';
        $parts = explode('_', $idnumber);
        $compSector = normalize_sector_name($parts[0] ?? '');
        return strcasecmp($compSector, $normalizedFilter) === 0;
    });
    $competencies = array_values($competencies);
}

// Load area descriptions for all detected sectors.
$areaDescriptions = [];
foreach (array_keys($sectorsFound) as $sec) {
    $areaDesc = \local_competencymanager\report_generator::get_area_descriptions_from_framework($sec, $courseid);
    $areaDescriptions = array_merge($areaDesc, $areaDescriptions);
}

// Aggregate competencies by area.
$areasData = passport_aggregate_by_area($competencies, $areaDescriptions, $sector);

// Load existing coach comments from DB.
$existingComments = [];
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_passport_comments')) {
    $commentRecords = $DB->get_records('local_passport_comments', [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);
    foreach ($commentRecords as $rec) {
        $existingComments[$rec->area_code] = $rec->comment;
    }
}

// Prepare radar chart data.
$radarItems = [];
foreach ($areasData as $areaKey => $area) {
    $radarItems[] = [
        'label' => $area['code'] . '. ' . $area['name'],
        'value' => $area['percentage'],
    ];
}

// Sector display name (use effective filter if set).
$sectorDisplay = '';
$displaySector = ($effectiveSectorFilter !== 'all') ? $effectiveSectorFilter : $sector;
if ($displaySector) {
    $sectorDisplay = get_sector_display_name($displaySector);
}

// Overall percentage.
$overallPct = $summary['overall_percentage'] ?? 0;
if ($summary['total_questions'] > 0) {
    $overallPct = round($summary['correct_questions'] / $summary['total_questions'] * 100, 1);
}

// Can the current user save comments?
$canEvaluate = has_capability('local/competencymanager:evaluate', $context) || $isadmin;

// ========================================
// OUTPUT
// ========================================
echo $OUTPUT->header();
?>

<style>
/* ========================================
   PASSAPORTO TECNICO STYLES
   ======================================== */
.passport-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header card */
.passport-header {
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
.passport-header-left h1 {
    margin: 0 0 4px 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1a2e;
}
.passport-header-left .passport-subtitle {
    color: #666;
    font-size: 0.95rem;
    margin: 0;
}
.passport-header-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Buttons */
.passport-btn {
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
.passport-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    text-decoration: none;
}
.passport-btn-secondary {
    background: #6c757d;
    color: #fff;
}
.passport-btn-secondary:hover {
    background: #5a6268;
    color: #fff;
}
.passport-btn-primary {
    background: #0066cc;
    color: #fff;
}
.passport-btn-primary:hover {
    background: #0052a3;
    color: #fff;
}
.passport-btn-success {
    background: #28a745;
    color: #fff;
}
.passport-btn-success:hover {
    background: #218838;
    color: #fff;
}

/* Summary bar */
.passport-summary {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 20px 30px;
    margin-bottom: 24px;
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
}
.passport-summary-item {
    text-align: center;
}
.passport-summary-item .label {
    font-size: 0.8rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.passport-summary-item .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a2e;
}

/* Radar section */
.passport-radar-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 24px;
    text-align: center;
}
.passport-radar-section h2 {
    font-size: 1.2rem;
    color: #333;
    margin: 0 0 20px 0;
}
.passport-radar-section svg {
    max-width: 100%;
    height: auto;
}

/* Table section */
.passport-table-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 24px;
}
.passport-table-section h2 {
    font-size: 1.2rem;
    color: #333;
    margin: 0 0 20px 0;
}
.passport-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.passport-table thead th {
    background: #f0f4f8;
    color: #333;
    padding: 12px 14px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}
.passport-table tbody tr {
    border-bottom: 1px solid #eee;
}
.passport-table tbody tr:nth-child(even) {
    background: #fafbfc;
}
.passport-table tbody tr:hover {
    background: #f0f4ff;
}
.passport-table td {
    padding: 10px 14px;
    vertical-align: middle;
}
.passport-table .area-code {
    font-weight: 700;
    font-size: 1rem;
    color: #0066cc;
    white-space: nowrap;
}
.passport-table .area-name {
    color: #333;
}
.passport-table .pct-badge {
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

.passport-table textarea {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 0.85rem;
    font-family: inherit;
    resize: vertical;
    transition: border-color 0.2s;
}
.passport-table textarea:focus {
    border-color: #0066cc;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
}

/* Save feedback */
.passport-save-feedback {
    display: none;
    padding: 10px 20px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-weight: 600;
    text-align: center;
}
.passport-save-feedback.success {
    background: #d4edda;
    color: #155724;
    display: block;
}
.passport-save-feedback.error {
    background: #f8d7da;
    color: #721c24;
    display: block;
}

/* ========================================
   PRINT STYLES
   ======================================== */
@media print {
    @page { size: portrait; margin: 10mm; }
    body { background: #fff !important; margin: 0; padding: 0; font-size: 10pt; }

    /* Hide Moodle UI + screen-only elements */
    .no-print,
    #page-header,
    #nav-drawer,
    .navbar,
    .drawer-toggler,
    #page-footer,
    .footer-popover,
    nav,
    .breadcrumb,
    .passport-header,
    .passport-summary,
    .passport-save-feedback {
        display: none !important;
    }

    .passport-container {
        max-width: 100%;
        padding: 0;
        margin: 0;
    }

    .passport-radar-section,
    .passport-table-section {
        box-shadow: none;
        border: none;
        padding: 0;
        margin: 0;
    }

    /* ---- FTM Red Header Block ---- */
    .passport-print-header {
        display: block !important;
        background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%) !important;
        color: #fff !important;
        padding: 20px 25px 18px 25px;
        margin-bottom: 12px;
        border-radius: 8px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        position: relative;
    }
    /* Top logo row */
    .print-logos {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255,255,255,0.3);
    }
    .print-logo-box {
        background: #fff !important;
        border-radius: 4px;
        padding: 8px 15px;
        font-size: 9pt;
        color: #c0392b !important;
        font-weight: 700;
        line-height: 1.3;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-logo-box small {
        display: block;
        font-size: 6pt;
        font-weight: 400;
        color: #666 !important;
    }
    .print-org-box {
        background: #fff !important;
        border-radius: 4px;
        padding: 8px 15px;
        color: #333 !important;
        font-size: 10pt;
        font-weight: 700;
        line-height: 1.3;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-org-box span {
        display: block;
        font-size: 9pt;
        font-weight: 400;
        color: #e74c3c !important;
    }
    /* Title + details */
    .print-main-title {
        font-size: 16pt;
        font-weight: 700;
        color: #fff;
        margin: 0 0 8px 0;
        text-transform: uppercase;
    }
    .print-details {
        font-size: 10pt;
        color: #fff;
        line-height: 1.8;
    }
    .print-details strong {
        font-weight: 700;
    }
    .print-sector-badge {
        display: inline-block;
        background: rgba(0,0,0,0.25) !important;
        color: #fff !important;
        padding: 2px 12px;
        border-radius: 4px;
        font-size: 9pt;
        font-weight: 700;
        text-transform: uppercase;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* Score on the right */
    .print-score-block {
        position: absolute;
        right: 25px;
        bottom: 20px;
        text-align: right;
        color: #fff;
    }
    .print-score-block .score-pct {
        font-size: 28pt;
        font-weight: 700;
        line-height: 1;
    }
    .print-score-block .score-label {
        font-size: 9pt;
        opacity: 0.9;
    }

    /* ---- Radar ---- */
    .passport-radar-section {
        margin-bottom: 15px;
        overflow: hidden;
    }
    .passport-radar-section h2 {
        font-size: 11pt;
        text-align: center;
        margin: 5px 0 0 0;
    }
    .passport-radar-section svg {
        display: block;
        margin: 0 auto;
        max-width: 100%;
        height: auto;
        transform: scale(1.1);
        transform-origin: top center;
    }

    /* ---- Table with comments ---- */
    .passport-table-section h2 {
        display: none;
    }
    .passport-table {
        font-size: 11pt;
        border-collapse: collapse;
        width: 100%;
    }
    .passport-table thead th {
        background: #f5f5f5 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        padding: 6px 10px;
        font-size: 10pt;
        border: 1px solid #ccc;
    }
    .passport-table td {
        padding: 5px 10px;
        border: 1px solid #ccc;
        vertical-align: top;
    }
    .passport-table .area-name {
        font-size: 11pt;
    }

    /* Show comments in print as plain text */
    .passport-table textarea {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        padding: 0;
        margin: 0;
        font-size: 10pt;
        font-family: inherit;
        resize: none;
        overflow: visible;
        height: auto !important;
        min-height: 16px;
        width: 100%;
        color: #333;
    }

    /* Badge colors */
    .pct-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        padding: 2px 10px;
        font-size: 10pt;
    }
}

/* Hide print header on screen */
.passport-print-header {
    display: none;
}
</style>

<div class="passport-container">

    <!-- Print-only header (FTM red block) -->
    <div class="passport-print-header">
        <div class="print-logos">
            <div class="print-logo-box">
                fondazione <strong>Millennio</strong>
                <small>Unita formativa di Aiti - Associazione industrie ticinesi</small>
            </div>
            <div class="print-org-box">
                Fondazione Terzo Millennio
                <span>Formazione Professionale</span>
            </div>
        </div>
        <h2 class="print-main-title">Passaporto Tecnico</h2>
        <div class="print-details">
            <strong>Studente:</strong> <?php echo s(fullname($student)); ?><br>
            <strong>Email:</strong> <?php echo s($student->email); ?><br>
            <strong>Settore:</strong>
            <?php if ($sectorDisplay): ?>
                <span class="print-sector-badge"><?php echo s($sectorDisplay); ?></span>
            <?php else: ?>
                -
            <?php endif; ?>
            <br>
            <strong>Data stampa:</strong> <?php echo date('d/m/Y H:i'); ?>
        </div>
        <div class="print-score-block">
            <div class="score-pct"><?php echo $overallPct; ?>%</div>
            <div class="score-label">Punteggio Globale</div>
        </div>
    </div>

    <!-- Header -->
    <div class="passport-header">
        <div class="passport-header-left">
            <h1>Passaporto Tecnico</h1>
            <p class="passport-subtitle">
                <?php echo s(fullname($student)); ?>
                <?php if ($sectorDisplay): ?>
                    &mdash; Settore: <?php echo s($sectorDisplay); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="passport-header-right no-print">
            <a href="<?php echo (new moodle_url('/local/competencymanager/student_report.php', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]))->out(false); ?>" class="passport-btn passport-btn-secondary">
                &#8592; Torna allo Student Report
            </a>
            <?php if ($canEvaluate): ?>
            <button type="button" class="passport-btn passport-btn-success" onclick="savePassportComments()">
                Salva Commenti
            </button>
            <?php endif; ?>
            <button type="button" class="passport-btn passport-btn-primary" onclick="window.print()">
                Stampa Passaporto Tecnico
            </button>
        </div>
    </div>

    <!-- Save feedback -->
    <div id="passport-save-feedback" class="passport-save-feedback no-print"></div>

    <!-- Summary bar -->
    <div class="passport-summary">
        <div class="passport-summary-item">
            <div class="label">Aree</div>
            <div class="value"><?php echo count($areasData); ?></div>
        </div>
        <div class="passport-summary-item">
            <div class="label">Competenze</div>
            <div class="value"><?php echo $summary['total_competencies']; ?></div>
        </div>
        <div class="passport-summary-item">
            <div class="label">Risposte Corrette</div>
            <div class="value"><?php echo $summary['correct_questions']; ?> / <?php echo $summary['total_questions']; ?></div>
        </div>
        <div class="passport-summary-item">
            <div class="label">Punteggio Globale</div>
            <div class="value" style="color: <?php
                echo $overallPct >= 80 ? '#27ae60' : ($overallPct >= 60 ? '#3498db' : ($overallPct >= 40 ? '#f39c12' : '#c0392b'));
            ?>;"><?php echo $overallPct; ?>%</div>
        </div>
        <?php if ($sectorDisplay): ?>
        <div class="passport-summary-item">
            <div class="label">Settore</div>
            <div class="value" style="font-size: 1.1rem;"><?php echo s($sectorDisplay); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Radar chart -->
    <div class="passport-radar-section">
        <h2>Panoramica Aree</h2>
        <?php
        if (!empty($radarItems)) {
            echo passport_generate_svg_radar($radarItems, '', 400);
        } else {
            echo '<p style="color: #888;">Nessun dato quiz disponibile per questo studente.</p>';
        }
        ?>
    </div>

    <!-- Areas table with comments -->
    <div class="passport-table-section">
        <h2>Dettaglio per Area</h2>
        <?php if (!empty($areasData)): ?>
        <table class="passport-table" id="passport-areas-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th style="width: 100px; text-align: center;">Competenze</th>
                    <th style="width: 100px; text-align: center;">Risposte</th>
                    <th style="width: 100px; text-align: center;">Punteggio</th>
                    <th style="width: 35%;" class="col-comment">Commento Coach</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($areasData as $areaKey => $area): ?>
                <?php
                    $pct = $area['percentage'];
                    $pctClass = $pct >= 80 ? 'pct-excellent' : ($pct >= 60 ? 'pct-good' : ($pct >= 40 ? 'pct-sufficient' : 'pct-critical'));
                    $existingComment = $existingComments[$areaKey] ?? '';
                ?>
                <tr>
                    <td class="area-name"><?php echo s($area['code']); ?>. <?php echo s($area['name']); ?></td>
                    <td style="text-align: center;"><?php echo (int)$area['count']; ?></td>
                    <td style="text-align: center;">
                        <?php echo (int)$area['correct_questions']; ?> / <?php echo (int)$area['total_questions']; ?>
                    </td>
                    <td style="text-align: center;">
                        <span class="pct-badge <?php echo $pctClass; ?>"><?php echo $pct; ?>%</span>
                    </td>
                    <td class="col-comment">
                        <textarea
                            name="comment_<?php echo s($areaKey); ?>"
                            data-area="<?php echo s($areaKey); ?>"
                            rows="3"
                            placeholder="Inserire commento..."
                            class="passport-comment"
                            <?php if (!$canEvaluate): ?>readonly<?php endif; ?>
                        ><?php echo s($existingComment); ?></textarea>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #888; text-align: center; padding: 30px;">
            Nessun dato quiz disponibile per questo studente e corso.
        </p>
        <?php endif; ?>
    </div>

</div>

<?php if ($canEvaluate && !empty($areasData)): ?>
<script>
/**
 * Save all passport comments via AJAX.
 */
function savePassportComments() {
    var textareas = document.querySelectorAll('.passport-comment');
    var comments = [];
    textareas.forEach(function(ta) {
        comments.push({
            area_code: ta.getAttribute('data-area'),
            comment: ta.value.trim()
        });
    });

    var feedback = document.getElementById('passport-save-feedback');
    feedback.className = 'passport-save-feedback no-print';
    feedback.style.display = 'none';
    feedback.textContent = '';

    var xhr = new XMLHttpRequest();
    var url = '<?php echo (new moodle_url('/local/competencymanager/ajax_save_passport_comments.php'))->out(false); ?>';
    var params = 'sesskey=<?php echo sesskey(); ?>'
        + '&userid=<?php echo $userid; ?>'
        + '&courseid=<?php echo $courseid; ?>'
        + '&comments=' + encodeURIComponent(JSON.stringify(comments));

    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    feedback.className = 'passport-save-feedback success no-print';
                    feedback.textContent = resp.message || 'Commenti salvati con successo.';
                } else {
                    feedback.className = 'passport-save-feedback error no-print';
                    feedback.textContent = resp.message || 'Errore nel salvataggio.';
                }
            } catch (e) {
                feedback.className = 'passport-save-feedback error no-print';
                feedback.textContent = 'Errore di comunicazione con il server.';
            }
            feedback.style.display = 'block';
            // Auto-hide after 5 seconds.
            setTimeout(function() {
                feedback.style.display = 'none';
            }, 5000);
        }
    };

    xhr.send(params);
}
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
