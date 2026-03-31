<?php
/**
 * Passaporto Tecnico - Technical Passport page
 *
 * Shows configurable sections (radar, tables, gap analysis, etc.) controlled by
 * the Garage FTM configuration (enabled_sections / section_order).
 * All charts use SVG for print compatibility.
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
// HELPER FUNCTIONS
// ========================================

/**
 * Detect sector from course name.
 *
 * Local copy to avoid dependency on student_report.php.
 *
 * @param string $coursename Course full name.
 * @return string Sector code or empty string.
 */
function passport_get_sector_from_course_name($coursename) {
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
    if (strpos($name, 'AUTOMAZ') !== false) {
        return 'AUTOMAZIONE';
    }
    if (strpos($name, 'METAL') !== false) {
        return 'METALCOSTRUZIONE';
    }
    if (strpos($name, 'GENERICO') !== false) {
        return 'GENERICO';
    }
    return '';
}

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

/**
 * Generate overlay radar SVG with multiple datasets.
 *
 * @param array $datasets Array of datasets, each with 'data', 'label', 'fill', 'stroke'.
 * @param array $labels Axis labels.
 * @param int $size Chart size.
 * @param string $title Chart title.
 * @return string SVG markup.
 */
function passport_generate_svg_overlay_radar($datasets, $labels, $size = 400, $title = '') {
    if (empty($datasets) || empty($labels)) return '<p>Nessun dato disponibile</p>';

    $n = count($labels);
    if ($n < 3) return '<p>Servono almeno 3 aree per il radar</p>';

    $horizontalPadding = 180;
    $svgWidth = $size + (2 * $horizontalPadding);
    $cx = $horizontalPadding + ($size / 2);
    $cy = $size / 2;
    $margin = 70;
    $radius = ($size / 2) - $margin;
    $angleStep = (2 * M_PI) / $n;
    $offsetY = $title ? 35 : 10;

    $svg = '<svg width="' . $svgWidth . '" height="' . ($size + 80) . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';

    if ($title) {
        $svg .= '<text x="' . $cx . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    }

    for ($p = 20; $p <= 100; $p += 20) {
        $r = $radius * ($p / 100);
        $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . $r . '" fill="none" stroke="#e0e0e0" stroke-width="1"/>';
    }

    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.6) . '" fill="none" stroke="#f39c12" stroke-width="1.5" stroke-dasharray="5,3"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.8) . '" fill="none" stroke="#27ae60" stroke-width="1.5" stroke-dasharray="5,3"/>';

    for ($i = 0; $i < $n; $i++) {
        $angle = ($i * $angleStep) - (M_PI / 2);
        $axisX = $cx + $radius * cos($angle);
        $axisY = ($cy + $offsetY) + $radius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . ($cy + $offsetY) . '" x2="' . $axisX . '" y2="' . $axisY . '" stroke="#ddd" stroke-width="1"/>';

        $labelRadius = $radius + 20;
        $labelX = $cx + $labelRadius * cos($angle);
        $labelY = ($cy + $offsetY) + $labelRadius * sin($angle);
        $anchor = 'middle';
        if ($labelX < $cx - 20) $anchor = 'end';
        else if ($labelX > $cx + 20) $anchor = 'start';

        $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="' . $anchor . '" font-size="9" fill="#333">' . htmlspecialchars($labels[$i]) . '</text>';
    }

    foreach ($datasets as $ds) {
        $dsData = $ds['data'];
        $fill = $ds['fill'] ?? 'rgba(0,0,0,0.1)';
        $stroke = $ds['stroke'] ?? '#333';
        $points = [];

        for ($i = 0; $i < $n; $i++) {
            $angle = ($i * $angleStep) - (M_PI / 2);
            $value = min(100, max(0, $dsData[$i] ?? 0));
            $pointRadius = $radius * ($value / 100);
            $px = $cx + $pointRadius * cos($angle);
            $py = ($cy + $offsetY) + $pointRadius * sin($angle);
            $points[] = round($px, 1) . ',' . round($py, 1);
        }

        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="2"/>';
        foreach ($points as $point) {
            list($px, $py) = explode(',', $point);
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="4" fill="' . $stroke . '" stroke="white" stroke-width="1.5"/>';
        }
    }

    $legendY = $size + $offsetY + 15;
    $legendX = $cx - ($svgWidth / 2) + 30;
    foreach ($datasets as $idx => $ds) {
        $lx = $legendX + ($idx * 170);
        $svg .= '<rect x="' . $lx . '" y="' . ($legendY - 5) . '" width="12" height="12" fill="' . ($ds['stroke'] ?? '#333') . '" rx="2"/>';
        $svg .= '<text x="' . ($lx + 16) . '" y="' . ($legendY + 5) . '" font-size="9" fill="#333">' . htmlspecialchars($ds['label'] ?? '') . '</text>';
    }

    $svg .= '</svg>';
    return $svg;
}

/**
 * Generate a mini SVG radar for a single area's competencies.
 *
 * @param array $areaData Area data with 'competencies' array.
 * @param int $size Chart size.
 * @return string SVG markup.
 */
function passport_generate_mini_radar($areaData, $size = 250) {
    $comps = $areaData['competencies'] ?? [];
    if (empty($comps)) {
        return '<p style="color:#888; font-size:0.8rem;">Nessun dato</p>';
    }

    $items = [];
    foreach ($comps as $comp) {
        $pct = $comp['total_questions'] > 0
            ? round($comp['correct_questions'] / $comp['total_questions'] * 100, 1)
            : 0;
        $shortName = $comp['shortname'] ?? $comp['idnumber'] ?? $comp['name'];
        // Shorten label: use last part after underscore.
        $parts = explode('_', $shortName);
        $label = end($parts);
        if (strlen($label) > 12) {
            $label = substr($label, 0, 10) . '..';
        }
        $items[] = ['label' => $label, 'value' => $pct];
    }

    if (count($items) === 1) {
        return passport_generate_svg_bar_chart($items, '', $size);
    }

    return passport_generate_svg_radar(
        $items,
        '',
        $size,
        'rgba(102,126,234,0.25)',
        '#667eea',
        8,
        12
    );
}

/**
 * Get a percentage class for badge styling.
 *
 * @param float $pct Percentage value.
 * @return string CSS class.
 */
function passport_pct_class($pct) {
    if ($pct >= 80) return 'pct-excellent';
    if ($pct >= 60) return 'pct-good';
    if ($pct >= 40) return 'pct-sufficient';
    return 'pct-critical';
}

/**
 * Get a level label from percentage.
 *
 * @param float $pct Percentage value.
 * @return string Level label.
 */
function passport_level_label($pct) {
    if ($pct >= 80) return 'Eccellente';
    if ($pct >= 60) return 'Buono';
    if ($pct >= 40) return 'Sufficiente';
    return 'Insufficiente';
}

/**
 * Bloom level to descriptive string.
 *
 * @param int $level Bloom level 0-6.
 * @return string Description.
 */
function passport_bloom_label($level) {
    $labels = [
        0 => 'N/O',
        1 => '1 - Ricordare',
        2 => '2 - Comprendere',
        3 => '3 - Applicare',
        4 => '4 - Analizzare',
        5 => '5 - Valutare',
        6 => '6 - Creare',
    ];
    return $labels[$level] ?? 'N/O';
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
$courseSector = $course ? passport_get_sector_from_course_name($course->fullname) : '';
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
    $filtered = array_filter($competencies, function($comp) use ($normalizedFilter) {
        $idnumber = $comp['idnumber'] ?? '';
        $parts = explode('_', $idnumber);
        $compSector = normalize_sector_name($parts[0] ?? '');
        return strcasecmp($compSector, $normalizedFilter) === 0;
    });
    // Only apply filter if it matches something; otherwise show all data.
    if (!empty($filtered)) {
        $competencies = array_values($filtered);
    }
}

// Load area descriptions for all detected sectors.
$areaDescriptions = [];
foreach (array_keys($sectorsFound) as $sec) {
    $areaDesc = \local_competencymanager\report_generator::get_area_descriptions_from_framework($sec, $courseid);
    $areaDescriptions = array_merge($areaDesc, $areaDescriptions);
}

// Load garage config if available.
$garageConfig = null;
if ($DB->get_manager()->table_exists('local_garage_config')) {
    // Try exact courseid match first, then fallback to courseid=0.
    $garageConfig = $DB->get_record('local_garage_config', [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);
    if (!$garageConfig && $courseid != 0) {
        $garageConfig = $DB->get_record('local_garage_config', [
            'userid' => $userid,
            'courseid' => 0,
        ]);
    }
}

// Apply garage selections: filter competencies.
if ($garageConfig && !empty($garageConfig->selected_competencies)) {
    $selectedComps = json_decode($garageConfig->selected_competencies, true);
    if (is_array($selectedComps) && !empty($selectedComps)) {
        $competencies = array_filter($competencies, function($comp) use ($selectedComps) {
            return in_array($comp['idnumber'] ?? '', $selectedComps);
        });
        $competencies = array_values($competencies);
    }
}

// Apply garage options.
$displayFormat = ($garageConfig && !empty($garageConfig->display_format)) ? $garageConfig->display_format : 'percentage';
$showOverlay = ($garageConfig && !empty($garageConfig->show_overlay)) ? true : false;

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

// ========================================
// GARAGE SECTION CONFIG
// ========================================

$enabledSections = [];
$sectionOrder = [];
if ($garageConfig) {
    $enabledSections = json_decode($garageConfig->enabled_sections ?? '[]', true) ?: [];
    $sectionOrder = json_decode($garageConfig->section_order ?? '[]', true) ?: [];
}

// Default sections if no garage config.
if (empty($enabledSections)) {
    $enabledSections = ['valutazione', 'radar_aree', 'dettagli'];
}
if (empty($sectionOrder)) {
    $sectionOrder = [
        'valutazione', 'progressi', 'radar_aree', 'radar_dettagli',
        'piano', 'dettagli', 'dual_radar', 'overlay_radar',
        'gap_analysis', 'spunti', 'suggerimenti', 'coach_eval',
    ];
}

// ========================================
// LOAD ADDITIONAL DATA FOR OPTIONAL SECTIONS
// ========================================

// Sector display name.
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

// --- Autovalutazione data (for dual_radar, overlay, gap_analysis) ---
$autoData = null;
$needsAuto = array_intersect(['dual_radar', 'overlay_radar', 'gap_analysis'], $enabledSections);
if (!empty($needsAuto) || $showOverlay) {
    if ($dbman->table_exists('local_selfassessment')) {
        $sectorPrefix = $displaySector ? ($displaySector . '_%') : '';
        $params = ['userid' => $userid];
        $sectorFilter = '';
        if ($sectorPrefix) {
            $sectorFilter = " AND c.idnumber LIKE :sp";
            $params['sp'] = $sectorPrefix;
        }
        $autoRecords = $DB->get_records_sql(
            "SELECT c.idnumber, sa.level
               FROM {local_selfassessment} sa
               JOIN {competency} c ON sa.competencyid = c.id
              WHERE sa.userid = :userid{$sectorFilter}",
            $params
        );
        if (!empty($autoRecords)) {
            $autoData = ['data' => []];
            foreach ($autoRecords as $r) {
                $autoData['data'][$r->idnumber] = [
                    'bloom_level' => (int)$r->level,
                    'percentage' => round(($r->level / 6) * 100, 1),
                ];
            }
        }
    }
}

// --- Coach evaluation data (for overlay, coach_eval section) ---
$coachEvalData = null;
$coachRatingsAll = [];
$coachEvalHeader = null;
$needsCoach = array_intersect(['overlay_radar', 'coach_eval', 'dual_radar'], $enabledSections);
if (!empty($needsCoach) || $showOverlay) {
    if ($dbman->table_exists('local_coach_evaluations')) {
        $coachEvalHeader = $DB->get_record_sql(
            "SELECT e.id, e.coachid, e.status, e.evaluation_date, e.notes
               FROM {local_coach_evaluations} e
              WHERE e.studentid = :sid AND e.status IN ('completed','signed')
              ORDER BY e.evaluation_date DESC
              LIMIT 1",
            ['sid' => $userid]
        );
        if ($coachEvalHeader && $dbman->table_exists('local_coach_eval_ratings')) {
            $coachRatingsAll = $DB->get_records_sql(
                "SELECT r.id, c.idnumber, r.competencyid, r.rating, r.notes
                   FROM {local_coach_eval_ratings} r
                   JOIN {competency} c ON c.id = r.competencyid
                  WHERE r.evaluationid = :eid",
                ['eid' => $coachEvalHeader->id]
            );
        }
    }
}

// --- Build overlay datasets ---
$overlayDatasets = [];
$overlayLabels = [];
if (($showOverlay || in_array('overlay_radar', $enabledSections) || in_array('dual_radar', $enabledSections)) && !empty($areasData)) {
    // Labels = area names.
    foreach ($areasData as $areaKey => $area) {
        $overlayLabels[] = $area['code'] . '. ' . $area['name'];
    }

    // Dataset 1: Quiz.
    $quizValues = [];
    foreach ($areasData as $area) {
        $quizValues[] = $area['percentage'];
    }
    $overlayDatasets[] = [
        'data' => $quizValues,
        'label' => 'Quiz',
        'fill' => 'rgba(102,126,234,0.2)',
        'stroke' => '#667eea',
    ];

    // Dataset 2: Autovalutazione.
    $autoValuesForOverlay = [];
    if ($autoData && !empty($autoData['data'])) {
        foreach ($areasData as $areaKey => $area) {
            $areaAutoSum = 0;
            $areaAutoCount = 0;
            foreach ($area['competencies'] as $comp) {
                $idnum = $comp['idnumber'] ?? '';
                if (isset($autoData['data'][$idnum])) {
                    $areaAutoSum += $autoData['data'][$idnum]['percentage'];
                    $areaAutoCount++;
                }
            }
            $autoValuesForOverlay[] = $areaAutoCount > 0 ? round($areaAutoSum / $areaAutoCount, 1) : 0;
        }
        $overlayDatasets[] = [
            'data' => $autoValuesForOverlay,
            'label' => 'Autovalutazione',
            'fill' => 'rgba(46,204,113,0.15)',
            'stroke' => '#2ecc71',
        ];
    }

    // Dataset 3: Coach evaluation.
    if (!empty($coachRatingsAll)) {
        $coachRatingsKeyed = [];
        foreach ($coachRatingsAll as $cr) {
            $coachRatingsKeyed[$cr->idnumber] = $cr;
        }
        $coachValues = [];
        foreach ($areasData as $areaKey => $area) {
            $areaCoachSum = 0;
            $areaCoachCount = 0;
            foreach ($area['competencies'] as $comp) {
                $idnum = $comp['idnumber'] ?? '';
                if (isset($coachRatingsKeyed[$idnum]) && $coachRatingsKeyed[$idnum]->rating > 0) {
                    $areaCoachSum += round(($coachRatingsKeyed[$idnum]->rating / 6) * 100, 1);
                    $areaCoachCount++;
                }
            }
            $coachValues[] = $areaCoachCount > 0 ? round($areaCoachSum / $areaCoachCount, 1) : 0;
        }
        $overlayDatasets[] = [
            'data' => $coachValues,
            'label' => 'Valutazione Coach',
            'fill' => 'rgba(243,156,18,0.15)',
            'stroke' => '#f39c12',
        ];
    }
}

// --- Build per-competency data for gap analysis, piano, spunti ---
$compDetails = [];
foreach ($competencies as $comp) {
    $pct = $comp['total_questions'] > 0
        ? round($comp['correct_questions'] / $comp['total_questions'] * 100, 1)
        : 0;
    $idnum = $comp['idnumber'] ?? '';
    $areaInfo = get_area_info($idnum);
    $autoPct = 0;
    if ($autoData && isset($autoData['data'][$idnum])) {
        $autoPct = $autoData['data'][$idnum]['percentage'];
    }
    $compDetails[] = [
        'idnumber' => $idnum,
        'name' => $comp['name'] ?? $idnum,
        'shortname' => $comp['shortname'] ?? '',
        'area_code' => $areaInfo['code'],
        'area_name' => $areaInfo['name'],
        'total_questions' => $comp['total_questions'],
        'correct_questions' => $comp['correct_questions'],
        'quiz_pct' => $pct,
        'auto_pct' => $autoPct,
        'gap' => round($autoPct - $pct, 1),
    ];
}

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

/* Section card (shared style for all sections) */
.passport-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 24px;
}
.passport-section h2 {
    font-size: 1.2rem;
    color: #333;
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f4f8;
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

/* Progress bar (certification) */
.passport-progress-bar {
    display: flex;
    height: 32px;
    border-radius: 16px;
    overflow: hidden;
    background: #e9ecef;
    margin: 15px 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fff;
}
.passport-progress-bar > div {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: width 0.6s;
    min-width: 0;
}
.passport-progress-bar .progress-certified { background: #27ae60; }
.passport-progress-bar .progress-inprogress { background: #f39c12; }
.passport-progress-bar .progress-notstarted { background: #c0392b; }

.passport-progress-legend {
    display: flex;
    gap: 24px;
    margin-top: 10px;
    font-size: 0.85rem;
    flex-wrap: wrap;
}
.passport-progress-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.passport-progress-legend .dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

/* Mini radar grid */
.passport-mini-radars {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.passport-mini-radar-card {
    background: #fafbfc;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}
.passport-mini-radar-card h3 {
    font-size: 0.95rem;
    color: #333;
    margin: 0 0 10px 0;
}
.passport-mini-radar-card svg {
    max-width: 100%;
    height: auto;
}

/* Action plan level groups */
.passport-plan-group {
    margin-bottom: 20px;
}
.passport-plan-group h3 {
    font-size: 1rem;
    margin: 0 0 10px 0;
    padding: 8px 14px;
    border-radius: 6px;
    color: #fff;
    font-weight: 600;
}
.passport-plan-group h3.level-excellent { background: #27ae60; }
.passport-plan-group h3.level-good { background: #3498db; }
.passport-plan-group h3.level-improve { background: #f39c12; }
.passport-plan-group h3.level-critical { background: #c0392b; }

/* Gap analysis colors */
.gap-positive { color: #c0392b; font-weight: 700; }
.gap-moderate { color: #f39c12; font-weight: 600; }
.gap-aligned { color: #27ae60; font-weight: 600; }
.gap-negative { color: #3498db; font-weight: 600; }

.gap-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fff;
}
.gap-type-sopra { background: #c0392b; }
.gap-type-allineato { background: #27ae60; }
.gap-type-sotto { background: #3498db; }

/* Spunti / Suggerimenti cards */
.passport-spunti-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
}
.passport-spunto-card {
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid;
}
.passport-spunto-card.priority-critico {
    background: #fdf0f0;
    border-left-color: #c0392b;
}
.passport-spunto-card.priority-attenzione {
    background: #fef9e7;
    border-left-color: #f39c12;
}
.passport-spunto-card.priority-positivo {
    background: #eaf7f0;
    border-left-color: #3498db;
}
.passport-spunto-card.priority-eccellenza {
    background: #e8f8f0;
    border-left-color: #27ae60;
}
.passport-spunto-card h4 {
    font-size: 0.9rem;
    margin: 0 0 6px 0;
    color: #333;
}
.passport-spunto-card p {
    font-size: 0.85rem;
    color: #555;
    margin: 0;
}

/* Coach eval bloom badge */
.bloom-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fff;
    min-width: 40px;
    text-align: center;
}
.bloom-0 { background: #bdc3c7; color: #333; }
.bloom-1 { background: #c0392b; }
.bloom-2 { background: #e67e22; }
.bloom-3 { background: #f39c12; }
.bloom-4 { background: #3498db; }
.bloom-5 { background: #2980b9; }
.bloom-6 { background: #27ae60; }

/* Dual radar side-by-side */
.passport-dual-radar {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: center;
}
.passport-dual-radar > div {
    flex: 1;
    min-width: 350px;
    text-align: center;
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

    .passport-section,
    .passport-radar-section,
    .passport-table-section {
        box-shadow: none !important;
        border: none !important;
        padding: 10px 0;
        margin: 0 0 10px 0;
        overflow: visible !important;
    }

    /* Allow content to flow across pages */
    .passport-container,
    #page-content,
    #region-main,
    #region-main-box,
    [role="main"],
    .pagelayout-report #page,
    .pagelayout-report #page-content,
    .pagelayout-standard #page,
    .pagelayout-standard #page-content {
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
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

    /* ---- Sections ---- */
    .passport-section h2 {
        font-size: 11pt;
        border-bottom: 1px solid #ccc;
        padding-bottom: 5px;
        margin-bottom: 8px;
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

    /* Progress bar print */
    .passport-progress-bar > div {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .passport-progress-legend .dot {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Mini radars: 2 per row in print */
    .passport-mini-radars {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .passport-mini-radar-card {
        page-break-inside: avoid;
    }

    /* Bloom badges */
    .bloom-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Gap badges */
    .gap-type-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Spunti cards */
    .passport-spunto-card {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        page-break-inside: avoid;
    }

    /* Plan groups */
    .passport-plan-group h3 {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Dual radar */
    .passport-dual-radar {
        flex-wrap: nowrap;
    }
    .passport-dual-radar > div {
        min-width: 0;
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
                <img src="<?php echo $CFG->wwwroot; ?>/local/competencymanager/pix/ftm_logo.png" alt="Fondazione Millennio" style="height: 45px; display: block;">
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
            <a href="<?php echo (new moodle_url('/local/competencymanager/garage_ftm.php', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]))->out(false); ?>" class="passport-btn" style="background:#f59e0b; color:#fff;">
                Garage FTM
            </a>
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

    <!-- Summary bar (always visible on screen) -->
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

    <!-- ========================================
         CONFIGURABLE SECTIONS (rendered in garage order)
         ======================================== -->
    <?php foreach ($sectionOrder as $sectionKey):
        if (!in_array($sectionKey, $enabledSections)) continue;

        switch ($sectionKey):

        // ================================================================
        // 1. VALUTAZIONE - Panoramica (summary stats)
        // ================================================================
        case 'valutazione': ?>
            <div class="passport-section" style="text-align:center;">
                <h2>Panoramica Valutazione</h2>
                <div style="display:flex; gap:30px; justify-content:center; flex-wrap:wrap; margin-bottom:15px;">
                    <?php
                    // Count areas by level.
                    $levExcellent = $levGood = $levSuff = $levCrit = 0;
                    foreach ($areasData as $a) {
                        if ($a['percentage'] >= 80) $levExcellent++;
                        else if ($a['percentage'] >= 60) $levGood++;
                        else if ($a['percentage'] >= 40) $levSuff++;
                        else $levCrit++;
                    }
                    ?>
                    <div style="text-align:center;">
                        <div style="font-size:2rem; font-weight:700; color:<?php echo $overallPct >= 80 ? '#27ae60' : ($overallPct >= 60 ? '#3498db' : ($overallPct >= 40 ? '#f39c12' : '#c0392b')); ?>;">
                            <?php echo $overallPct; ?>%
                        </div>
                        <div style="font-size:0.8rem; color:#888; text-transform:uppercase;">Punteggio Globale</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:2rem; font-weight:700; color:#27ae60;"><?php echo $levExcellent; ?></div>
                        <div style="font-size:0.8rem; color:#888; text-transform:uppercase;">Eccellenti (&ge;80%)</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:2rem; font-weight:700; color:#3498db;"><?php echo $levGood; ?></div>
                        <div style="font-size:0.8rem; color:#888; text-transform:uppercase;">Buone (60-80%)</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:2rem; font-weight:700; color:#f39c12;"><?php echo $levSuff; ?></div>
                        <div style="font-size:0.8rem; color:#888; text-transform:uppercase;">Sufficienti (40-60%)</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:2rem; font-weight:700; color:#c0392b;"><?php echo $levCrit; ?></div>
                        <div style="font-size:0.8rem; color:#888; text-transform:uppercase;">Critiche (&lt;40%)</div>
                    </div>
                </div>
                <?php if (!empty($radarItems)): ?>
                    <?php echo passport_generate_svg_radar($radarItems, '', 400); ?>
                <?php else: ?>
                    <p style="color: #888;">Nessun dato quiz disponibile per questo studente.</p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 2. PROGRESSI - Certification progress bar
        // ================================================================
        case 'progressi': ?>
            <div class="passport-section">
                <h2>Progressi Certificazione</h2>
                <?php
                $certCount = $inProgCount = $notStartCount = 0;
                $totalComps = count($compDetails);
                foreach ($compDetails as $cd) {
                    if ($cd['quiz_pct'] >= 80) $certCount++;
                    else if ($cd['quiz_pct'] > 0) $inProgCount++;
                    else $notStartCount++;
                }
                $certPct = $totalComps > 0 ? round($certCount / $totalComps * 100, 1) : 0;
                $inProgPct = $totalComps > 0 ? round($inProgCount / $totalComps * 100, 1) : 0;
                $notStartPct = $totalComps > 0 ? round($notStartCount / $totalComps * 100, 1) : 0;
                ?>
                <div class="passport-progress-bar">
                    <?php if ($certPct > 0): ?>
                    <div class="progress-certified" style="width:<?php echo $certPct; ?>%;"><?php echo $certPct; ?>%</div>
                    <?php endif; ?>
                    <?php if ($inProgPct > 0): ?>
                    <div class="progress-inprogress" style="width:<?php echo $inProgPct; ?>%;"><?php echo $inProgPct; ?>%</div>
                    <?php endif; ?>
                    <?php if ($notStartPct > 0): ?>
                    <div class="progress-notstarted" style="width:<?php echo $notStartPct; ?>%;"><?php echo $notStartPct; ?>%</div>
                    <?php endif; ?>
                </div>
                <div class="passport-progress-legend">
                    <span><span class="dot" style="background:#27ae60;"></span> Certificato (&ge;80%): <?php echo $certCount; ?>/<?php echo $totalComps; ?></span>
                    <span><span class="dot" style="background:#f39c12;"></span> In Corso (1-79%): <?php echo $inProgCount; ?>/<?php echo $totalComps; ?></span>
                    <span><span class="dot" style="background:#c0392b;"></span> Non Iniziato (0%): <?php echo $notStartCount; ?>/<?php echo $totalComps; ?></span>
                </div>
            </div>
        <?php break;

        // ================================================================
        // 3. RADAR_AREE - Main radar chart (areas only)
        // ================================================================
        case 'radar_aree': ?>
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
        <?php break;

        // ================================================================
        // 4. RADAR_DETTAGLI - Individual mini radar per area
        // ================================================================
        case 'radar_dettagli': ?>
            <div class="passport-section">
                <h2>Dettaglio Radar per Area</h2>
                <?php if (!empty($areasData)): ?>
                <div class="passport-mini-radars">
                    <?php foreach ($areasData as $areaKey => $area): ?>
                    <div class="passport-mini-radar-card">
                        <h3><?php echo s($area['code']); ?>. <?php echo s($area['name']); ?>
                            <span class="pct-badge <?php echo passport_pct_class($area['percentage']); ?>" style="font-size:0.75rem; vertical-align:middle; margin-left:6px;">
                                <?php echo $area['percentage']; ?>%
                            </span>
                        </h3>
                        <?php echo passport_generate_mini_radar($area, 250); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#888;">Nessun dato disponibile.</p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 5. PIANO - Action Plan grouped by level
        // ================================================================
        case 'piano': ?>
            <div class="passport-section">
                <h2>Piano d'Azione</h2>
                <?php
                // Group competencies by level bands.
                $planGroups = [
                    'excellent' => ['label' => 'Eccellente (&ge;80%)', 'class' => 'level-excellent', 'items' => []],
                    'good' => ['label' => 'Buono (60-80%)', 'class' => 'level-good', 'items' => []],
                    'improve' => ['label' => 'Da Migliorare (30-60%)', 'class' => 'level-improve', 'items' => []],
                    'critical' => ['label' => 'Critico (&lt;30%)', 'class' => 'level-critical', 'items' => []],
                ];
                foreach ($compDetails as $cd) {
                    if ($cd['quiz_pct'] >= 80) $planGroups['excellent']['items'][] = $cd;
                    else if ($cd['quiz_pct'] >= 60) $planGroups['good']['items'][] = $cd;
                    else if ($cd['quiz_pct'] >= 30) $planGroups['improve']['items'][] = $cd;
                    else $planGroups['critical']['items'][] = $cd;
                }
                foreach ($planGroups as $gKey => $group):
                    if (empty($group['items'])) continue;
                ?>
                <div class="passport-plan-group">
                    <h3 class="<?php echo $group['class']; ?>"><?php echo $group['label']; ?> (<?php echo count($group['items']); ?>)</h3>
                    <table class="passport-table">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Competenza</th>
                                <th style="text-align:center; width:80px;">Risposte</th>
                                <th style="text-align:center; width:80px;">%</th>
                                <th style="text-align:center; width:100px;">Livello</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($group['items'] as $item): ?>
                            <tr>
                                <td class="area-code"><?php echo s($item['area_code']); ?></td>
                                <td><?php echo s($item['name']); ?></td>
                                <td style="text-align:center;"><?php echo (int)$item['correct_questions']; ?>/<?php echo (int)$item['total_questions']; ?></td>
                                <td style="text-align:center;">
                                    <span class="pct-badge <?php echo passport_pct_class($item['quiz_pct']); ?>"><?php echo $item['quiz_pct']; ?>%</span>
                                </td>
                                <td style="text-align:center;"><?php echo passport_level_label($item['quiz_pct']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
        <?php break;

        // ================================================================
        // 6. DETTAGLI - Full competency table with comments
        // ================================================================
        case 'dettagli': ?>
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
                            $pctClass = passport_pct_class($pct);
                            $existingComment = $existingComments[$areaKey] ?? '';
                        ?>
                        <tr>
                            <td class="area-name"><?php echo s($area['code']); ?>. <?php echo s($area['name']); ?></td>
                            <td style="text-align: center;"><?php echo (int)$area['count']; ?></td>
                            <td style="text-align: center;">
                                <?php echo (int)$area['correct_questions']; ?> / <?php echo (int)$area['total_questions']; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($displayFormat === 'qualitative'): ?>
                                <span class="pct-badge <?php echo $pctClass; ?>"><?php echo passport_level_label($pct); ?></span>
                                <?php else: ?>
                                <span class="pct-badge <?php echo $pctClass; ?>"><?php echo $pct; ?>%</span>
                                <?php endif; ?>
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
        <?php break;

        // ================================================================
        // 7. DUAL_RADAR - Two radars side by side (Quiz vs Autovalutazione)
        // ================================================================
        case 'dual_radar': ?>
            <div class="passport-section">
                <h2>Confronto Quiz vs Autovalutazione</h2>
                <?php if (!empty($areasData) && $autoData && !empty($autoData['data'])): ?>
                <div class="passport-dual-radar">
                    <div>
                        <h3 style="font-size:1rem; color:#667eea; margin-bottom:10px;">Quiz</h3>
                        <?php
                        $quizRadarItems = [];
                        foreach ($areasData as $area) {
                            $quizRadarItems[] = ['label' => $area['code'] . '. ' . $area['name'], 'value' => $area['percentage']];
                        }
                        echo passport_generate_svg_radar($quizRadarItems, '', 350, 'rgba(102,126,234,0.3)', '#667eea', 9);
                        ?>
                    </div>
                    <div>
                        <h3 style="font-size:1rem; color:#2ecc71; margin-bottom:10px;">Autovalutazione</h3>
                        <?php
                        $autoRadarItems = [];
                        foreach ($areasData as $areaKey => $area) {
                            $areaAutoSum = 0;
                            $areaAutoCount = 0;
                            foreach ($area['competencies'] as $comp) {
                                $idnum = $comp['idnumber'] ?? '';
                                if (isset($autoData['data'][$idnum])) {
                                    $areaAutoSum += $autoData['data'][$idnum]['percentage'];
                                    $areaAutoCount++;
                                }
                            }
                            $autoRadarItems[] = [
                                'label' => $area['code'] . '. ' . $area['name'],
                                'value' => $areaAutoCount > 0 ? round($areaAutoSum / $areaAutoCount, 1) : 0,
                            ];
                        }
                        echo passport_generate_svg_radar($autoRadarItems, '', 350, 'rgba(46,204,113,0.3)', '#2ecc71', 9);
                        ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="color:#888; text-align:center;">
                    <?php if (!$autoData || empty($autoData['data'])): ?>
                        Nessun dato di autovalutazione disponibile per questo studente.
                    <?php else: ?>
                        Nessun dato quiz disponibile.
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 8. OVERLAY_RADAR - Multi-source overlay radar
        // ================================================================
        case 'overlay_radar': ?>
            <?php if (!empty($overlayDatasets) && count($overlayDatasets) > 1): ?>
            <div class="passport-radar-section">
                <h2>Confronto Fonti di Valutazione</h2>
                <?php echo passport_generate_svg_overlay_radar($overlayDatasets, $overlayLabels, 400); ?>
            </div>
            <?php else: ?>
            <div class="passport-section">
                <h2>Confronto Fonti di Valutazione</h2>
                <p style="color:#888; text-align:center;">Servono almeno 2 fonti di valutazione (Quiz + Autovalutazione o Coach) per generare il grafico overlay.</p>
            </div>
            <?php endif; ?>
        <?php break;

        // ================================================================
        // 9. GAP_ANALYSIS - Gap table between auto and quiz
        // ================================================================
        case 'gap_analysis': ?>
            <div class="passport-section">
                <h2>Analisi Gap (Autovalutazione vs Quiz)</h2>
                <?php if ($autoData && !empty($autoData['data']) && !empty($compDetails)): ?>
                <?php
                // Filter only competencies that have autovalutazione data.
                $gapItems = array_filter($compDetails, function($cd) use ($autoData) {
                    return isset($autoData['data'][$cd['idnumber']]);
                });
                ?>
                <?php if (!empty($gapItems)): ?>
                <table class="passport-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Competenza</th>
                            <th style="text-align:center; width:80px;">Auto %</th>
                            <th style="text-align:center; width:80px;">Quiz %</th>
                            <th style="text-align:center; width:80px;">Gap</th>
                            <th style="text-align:center; width:130px;">Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gapItems as $gi):
                        $autoPctVal = $autoData['data'][$gi['idnumber']]['percentage'];
                        $quizPctVal = $gi['quiz_pct'];
                        $gapVal = round($autoPctVal - $quizPctVal, 1);
                        $absGap = abs($gapVal);
                        if ($absGap < 10) {
                            $gapClass = 'gap-aligned';
                            $gapTypeClass = 'gap-type-allineato';
                            $gapTypeLabel = 'Allineato';
                        } else if ($gapVal > 0) {
                            $gapClass = $absGap > 25 ? 'gap-positive' : 'gap-moderate';
                            $gapTypeClass = 'gap-type-sopra';
                            $gapTypeLabel = 'Sopravvalutazione';
                        } else {
                            $gapClass = $absGap > 25 ? 'gap-negative' : 'gap-moderate';
                            $gapTypeClass = 'gap-type-sotto';
                            $gapTypeLabel = 'Sottovalutazione';
                        }
                    ?>
                        <tr>
                            <td class="area-code"><?php echo s($gi['area_code']); ?></td>
                            <td><?php echo s($gi['name']); ?></td>
                            <td style="text-align:center;"><?php echo $autoPctVal; ?>%</td>
                            <td style="text-align:center;"><?php echo $quizPctVal; ?>%</td>
                            <td style="text-align:center;" class="<?php echo $gapClass; ?>">
                                <?php echo ($gapVal > 0 ? '+' : '') . $gapVal; ?>%
                            </td>
                            <td style="text-align:center;">
                                <span class="gap-type-badge <?php echo $gapTypeClass; ?>"><?php echo $gapTypeLabel; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                // Summary statistics.
                $sopraCount = 0; $sottoCount = 0; $allinCount = 0;
                foreach ($gapItems as $gi) {
                    $autoPctVal = $autoData['data'][$gi['idnumber']]['percentage'];
                    $gapVal = round($autoPctVal - $gi['quiz_pct'], 1);
                    $absGap = abs($gapVal);
                    if ($absGap < 10) $allinCount++;
                    else if ($gapVal > 0) $sopraCount++;
                    else $sottoCount++;
                }
                ?>
                <div style="margin-top:15px; display:flex; gap:20px; flex-wrap:wrap; font-size:0.9rem;">
                    <span><span class="gap-type-badge gap-type-sopra">Sopravvalutazione</span> <?php echo $sopraCount; ?> competenze</span>
                    <span><span class="gap-type-badge gap-type-allineato">Allineato</span> <?php echo $allinCount; ?> competenze</span>
                    <span><span class="gap-type-badge gap-type-sotto">Sottovalutazione</span> <?php echo $sottoCount; ?> competenze</span>
                </div>
                <?php else: ?>
                <p style="color:#888;">Nessuna competenza con dati di autovalutazione trovata.</p>
                <?php endif; ?>
                <?php else: ?>
                <p style="color:#888; text-align:center;">Nessun dato di autovalutazione disponibile per calcolare il gap.</p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 10. SPUNTI - Spunti Colloquio (tips for coach)
        // ================================================================
        case 'spunti': ?>
            <div class="passport-section">
                <h2>Spunti per il Colloquio</h2>
                <?php if (!empty($compDetails)): ?>
                <?php
                $spuntiGroups = [
                    'critico' => ['label' => 'Critici (&lt;30%) - Intervento necessario', 'items' => [], 'priority' => 'critico'],
                    'attenzione' => ['label' => 'Attenzione (30-60%) - Da monitorare', 'items' => [], 'priority' => 'attenzione'],
                    'positivo' => ['label' => 'Positivi (60-80%) - Consolidare', 'items' => [], 'priority' => 'positivo'],
                    'eccellenza' => ['label' => 'Eccellenze (&ge;80%) - Valorizzare', 'items' => [], 'priority' => 'eccellenza'],
                ];
                foreach ($compDetails as $cd) {
                    if ($cd['quiz_pct'] < 30) $spuntiGroups['critico']['items'][] = $cd;
                    else if ($cd['quiz_pct'] < 60) $spuntiGroups['attenzione']['items'][] = $cd;
                    else if ($cd['quiz_pct'] < 80) $spuntiGroups['positivo']['items'][] = $cd;
                    else $spuntiGroups['eccellenza']['items'][] = $cd;
                }
                foreach ($spuntiGroups as $gKey => $group):
                    if (empty($group['items'])) continue;
                ?>
                <h3 style="font-size:0.95rem; color:#555; margin:15px 0 10px 0;"><?php echo $group['label']; ?></h3>
                <div class="passport-spunti-grid">
                    <?php foreach ($group['items'] as $item): ?>
                    <div class="passport-spunto-card priority-<?php echo $group['priority']; ?>">
                        <h4><?php echo s($item['area_code']); ?> - <?php echo s($item['name']); ?></h4>
                        <p>
                            <strong>Risultato:</strong> <?php echo $item['quiz_pct']; ?>%
                            (<?php echo (int)$item['correct_questions']; ?>/<?php echo (int)$item['total_questions']; ?>)
                            <br>
                            <?php if ($gKey === 'critico'): ?>
                                Verificare le basi della competenza. Possibile necessita di formazione supplementare o supporto individuale.
                            <?php elseif ($gKey === 'attenzione'): ?>
                                Risultato in fase di sviluppo. Discutere le difficolta riscontrate e pianificare attivita di rinforzo.
                            <?php elseif ($gKey === 'positivo'): ?>
                                Buon livello raggiunto. Consolidare con esercizi pratici e valutare la possibilita di certificazione.
                            <?php else: ?>
                                Eccellente padronanza. Lo studente potrebbe supportare i colleghi o affrontare compiti piu complessi.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color:#888;">Nessun dato disponibile.</p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 11. SUGGERIMENTI - Formal tone suggestions
        // ================================================================
        case 'suggerimenti': ?>
            <div class="passport-section">
                <h2>Suggerimenti Formativi</h2>
                <?php if (!empty($compDetails)): ?>
                <?php
                // Group and sort by percentage ascending (worst first).
                $sorted = $compDetails;
                usort($sorted, function($a, $b) { return $a['quiz_pct'] - $b['quiz_pct']; });
                $hasSuggestions = false;
                ?>
                <table class="passport-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Competenza</th>
                            <th style="text-align:center; width:80px;">%</th>
                            <th style="width:45%;">Suggerimento</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sorted as $item):
                        $sug = '';
                        if ($item['quiz_pct'] < 30) {
                            $sug = 'Si raccomanda un percorso di recupero mirato su questa competenza. '
                                 . 'Prevedere sessioni di formazione individuale e verifiche intermedie.';
                            $hasSuggestions = true;
                        } else if ($item['quiz_pct'] < 60) {
                            $sug = 'Competenza in fase di sviluppo. Si consiglia di integrare il percorso '
                                 . 'con esercitazioni pratiche e momenti di feedback strutturato.';
                            $hasSuggestions = true;
                        } else if ($item['quiz_pct'] < 80) {
                            $sug = 'Livello buono. Si suggerisce di consolidare attraverso attivita '
                                 . 'applicative e di preparare lo studente alla certificazione.';
                            $hasSuggestions = true;
                        } else {
                            $sug = 'Competenza pienamente acquisita. Valutare l\'assegnazione di compiti '
                                 . 'avanzati o il ruolo di tutor per i colleghi.';
                            $hasSuggestions = true;
                        }
                    ?>
                        <tr>
                            <td class="area-code"><?php echo s($item['area_code']); ?></td>
                            <td><?php echo s($item['name']); ?></td>
                            <td style="text-align:center;">
                                <span class="pct-badge <?php echo passport_pct_class($item['quiz_pct']); ?>"><?php echo $item['quiz_pct']; ?>%</span>
                            </td>
                            <td style="font-size:0.85rem; color:#555;"><?php echo s($sug); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$hasSuggestions): ?>
                <p style="color:#888;">Nessun suggerimento disponibile.</p>
                <?php endif; ?>
                <?php else: ?>
                <p style="color:#888;">Nessun dato disponibile.</p>
                <?php endif; ?>
            </div>
        <?php break;

        // ================================================================
        // 12. COACH_EVAL - Coach evaluation (Bloom ratings)
        // ================================================================
        case 'coach_eval': ?>
            <div class="passport-section">
                <h2>Valutazione Coach (Scala Bloom)</h2>
                <?php if ($coachEvalHeader && !empty($coachRatingsAll)): ?>
                <?php
                // Get coach name.
                $coachUser = $DB->get_record('user', ['id' => $coachEvalHeader->coachid, 'deleted' => 0]);
                $coachName = $coachUser ? fullname($coachUser) : '-';
                $evalDate = $coachEvalHeader->evaluation_date ? userdate($coachEvalHeader->evaluation_date, '%d/%m/%Y') : '-';
                ?>
                <div style="margin-bottom:15px; font-size:0.9rem; color:#555;">
                    <strong>Coach:</strong> <?php echo s($coachName); ?> |
                    <strong>Data:</strong> <?php echo s($evalDate); ?> |
                    <strong>Stato:</strong> <?php echo s(ucfirst($coachEvalHeader->status)); ?>
                </div>
                <?php if (!empty($coachEvalHeader->notes)): ?>
                <div style="background:#f8f9fa; padding:12px 16px; border-radius:6px; margin-bottom:15px; font-size:0.9rem; border-left:3px solid #f39c12;">
                    <strong>Note generali:</strong> <?php echo s($coachEvalHeader->notes); ?>
                </div>
                <?php endif; ?>
                <table class="passport-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Competenza</th>
                            <th style="text-align:center; width:140px;">Bloom (0-6)</th>
                            <th style="width:30%;">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Group ratings by area using area_mapping.
                    $ratingsByArea = [];
                    foreach ($coachRatingsAll as $cr) {
                        $areaInfo = get_area_info($cr->idnumber);
                        $areaKey = $areaInfo['key'];
                        if (!isset($ratingsByArea[$areaKey])) {
                            $ratingsByArea[$areaKey] = [
                                'code' => $areaInfo['code'],
                                'name' => $areaInfo['name'],
                                'ratings' => [],
                            ];
                        }
                        // Get competency name.
                        $compName = $cr->idnumber;
                        foreach ($compDetails as $cd) {
                            if ($cd['idnumber'] === $cr->idnumber) {
                                $compName = $cd['name'];
                                break;
                            }
                        }
                        $ratingsByArea[$areaKey]['ratings'][] = [
                            'idnumber' => $cr->idnumber,
                            'name' => $compName,
                            'rating' => (int)$cr->rating,
                            'notes' => $cr->notes ?? '',
                        ];
                    }
                    ksort($ratingsByArea);
                    foreach ($ratingsByArea as $areaKey => $areaGroup):
                        $isFirstInArea = true;
                        foreach ($areaGroup['ratings'] as $rating):
                    ?>
                        <tr>
                            <td class="area-code">
                                <?php if ($isFirstInArea): ?>
                                    <?php echo s($areaGroup['code']); ?>. <?php echo s($areaGroup['name']); ?>
                                    <?php $isFirstInArea = false; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo s($rating['name']); ?></td>
                            <td style="text-align:center;">
                                <span class="bloom-badge bloom-<?php echo $rating['rating']; ?>">
                                    <?php echo passport_bloom_label($rating['rating']); ?>
                                </span>
                            </td>
                            <td style="font-size:0.85rem; color:#555;">
                                <?php echo $rating['notes'] ? s($rating['notes']) : '-'; ?>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    endforeach;
                    ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#888; text-align:center;">Nessuna valutazione coach disponibile per questo studente.</p>
                <?php endif; ?>
            </div>
        <?php break;

        endswitch;
    endforeach; ?>

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
