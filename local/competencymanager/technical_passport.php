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
                . '" font-size="8" fill="#999" class="radar-pct">' . $p . '%</text>';
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
                . '" font-size="' . ($labelFontSize + 1) . '" font-weight="bold" fill="' . $labelColor . '" class="radar-pct">'
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
            . '" font-size="11" font-weight="bold" fill="' . $color . '" class="radar-pct">' . $value . '%</text>';

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
// Records with area_code ending in '__ORIG' are permanent coach originals (never overwritten by AI).
$existingComments  = [];
$originalComments  = [];   // coach's permanent originals keyed by base area_code
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_passport_comments')) {
    $commentRecords = $DB->get_records('local_passport_comments', [
        'userid'   => $userid,
        'courseid' => $courseid,
    ]);
    foreach ($commentRecords as $rec) {
        if (substr($rec->area_code, -6) === '__ORIG') {
            $baseKey = substr($rec->area_code, 0, -6);
            $originalComments[$baseKey] = $rec->comment;
        } elseif ($rec->area_code !== '__PASSPORT_APPROVED__') {
            $existingComments[$rec->area_code] = $rec->comment;
        }
    }
}

// Load approval status.
$approvalRecord = null;
$isApproved = false;
$approvalTimeFormatted = '';
$approvalCoachName = '';
if ($dbman->table_exists('local_passport_comments')) {
    $approvalRecord = $DB->get_record('local_passport_comments', [
        'userid'    => $userid,
        'courseid'  => $courseid,
        'area_code' => '__PASSPORT_APPROVED__',
    ]);
    if ($approvalRecord) {
        $isApproved = true;
        $approvalData = json_decode($approvalRecord->comment, true);
        $approvalTimeFormatted = userdate(
            $approvalData['timestamp'] ?? $approvalRecord->timecreated,
            get_string('strftimedatetime', 'langconfig')
        );
        $approvalCoachName = $approvalData['coach_name'] ?? '';
    }
}

// AI profile fields from garage config.
$aiSettoreTarget  = $garageConfig->ai_settore_target ?? '';
$aiDisponibilita  = $garageConfig->ai_disponibilita ?? '';
$aiMobilita       = $garageConfig->ai_mobilita ?? '';
$aiPuntiForza     = $garageConfig->ai_punti_forza ?? '';
$aiNote           = $garageConfig->ai_note ?? '';
$aiPctCercaLavoro = isset($garageConfig->ai_pct_cerca_lavoro) ? (int)$garageConfig->ai_pct_cerca_lavoro : 50;

// Load final note from local_passport_comments with area_code = 'FINAL_NOTE'.
$finalNote = $existingComments['FINAL_NOTE'] ?? '';

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
$needsCoach = array_intersect(['overlay_radar', 'coach_eval', 'dual_radar', 'dettagli'], $enabledSections);
if (!empty($needsCoach) || $showOverlay || in_array('dettagli', $enabledSections)) {
    if ($dbman->table_exists('local_coach_evaluations')) {
        // Include draft status — coaches edit inline without completing.
        $coachEvalHeader = $DB->get_record_sql(
            "SELECT e.id, e.coachid, e.status, e.evaluation_date, e.notes
               FROM {local_coach_evaluations} e
              WHERE e.studentid = :sid AND e.status IN ('completed','signed','draft')
              ORDER BY e.evaluation_date DESC
              LIMIT 1",
            ['sid' => $userid]
        );
        if ($coachEvalHeader && $dbman->table_exists('local_coach_eval_ratings')) {
            // Base ratings (may be 0 if only edited via inline/history).
            $baseRatings = $DB->get_records_sql(
                "SELECT r.id, c.idnumber, r.competencyid, r.rating, r.notes
                   FROM {local_coach_eval_ratings} r
                   JOIN {competency} c ON c.id = r.competencyid
                  WHERE r.evaluationid = :eid",
                ['eid' => $coachEvalHeader->id]
            );

            // Override with actual values from history (inline edits save there).
            if ($dbman->table_exists('local_coach_eval_history')) {
                foreach ($baseRatings as &$br) {
                    $lastHistory = $DB->get_record_sql(
                        "SELECT new_value FROM {local_coach_eval_history}
                          WHERE ratingid = :rid AND field_changed = 'rating'
                          ORDER BY id DESC LIMIT 1",
                        ['rid' => $br->id]
                    );
                    if ($lastHistory && $lastHistory->new_value !== null) {
                        $br->rating = (int)$lastHistory->new_value;
                    }
                }
                unset($br);
            }

            $coachRatingsAll = $baseRatings;
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
                if (isset($coachRatingsKeyed[$idnum])) {
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

// --- Pre-build coach scores per area (for Dettaglio per Area section) ---
// The REAL coach values are in local_compman_final_ratings (manual edits from
// the student_report comparative table), NOT in local_coach_eval_ratings.
// Filter by courseid to avoid bleeding records from other courses.
$coachScorePerArea = [];
if ($dbman->table_exists('local_compman_final_ratings') && !empty($areasData)) {
    $finalRatings = $DB->get_records_sql(
        "SELECT * FROM {local_compman_final_ratings}
         WHERE studentid = :studentid AND courseid = :courseid AND method = :method",
        ['studentid' => $userid, 'courseid' => $courseid, 'method' => 'coach_comp']
    );

    $finalByArea = [];
    foreach ($finalRatings as $fr) {
        // Use the sector stored in the record to reconstruct the same key format
        // that passport_aggregate_by_area() uses (e.g. "AUTOMAZIONE_A").
        $areaKey = strtoupper($fr->sector) . '_' . $fr->area_code;
        $finalByArea[$areaKey] = (float)$fr->manual_value;
    }

    foreach ($areasData as $areaKey => $area) {
        if (isset($finalByArea[$areaKey])) {
            $pct = $finalByArea[$areaKey];
            $coachScorePerArea[$areaKey] = [
                'avg' => round(($pct / 100) * 6, 1),
                'pct' => round($pct, 1),
                'count' => 1,
            ];
        }
    }
}

// --- Overall coach percentage for print header ---
if (!empty($coachScorePerArea)) {
    $coachOverallPct = round(array_sum(array_column($coachScorePerArea, 'pct')) / count($coachScorePerArea), 1);
} else {
    $coachOverallPct = null;
}

// --- Rebuild radar with coach scores from comparative table ---
// If coach scores are available, use them for the radar instead of quiz scores.
if (!empty($coachScorePerArea)) {
    $radarItems = [];
    foreach ($areasData as $areaKey => $area) {
        $pct = isset($coachScorePerArea[$areaKey]) ? $coachScorePerArea[$areaKey]['pct'] : $area['percentage'];
        $radarItems[] = [
            'label' => $area['code'] . '. ' . $area['name'],
            'value' => $pct,
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
    // Use description as name if shortname looks like an idnumber (e.g. AUTOMAZIONE_OA_H4).
    $displayName = $comp['name'] ?? $idnum;
    if (!empty($comp['description']) && (
        $displayName === $idnum ||
        preg_match('/^[A-Z]+_[A-Z]+_[A-Z0-9]+$/i', $displayName)
    )) {
        $displayName = mb_substr(strip_tags($comp['description']), 0, 100);
    }
    $compDetails[] = [
        'idnumber' => $idnum,
        'name' => $displayName,
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

/* AI inline button group */
.ai-btn-group {
    display: flex;
    flex-direction: column;
    gap: 3px;
    flex-shrink: 0;
    min-width: 30px;
}
.ai-btn-group .passport-btn {
    padding: 4px 7px;
    font-size: 0.73rem;
    border-radius: 5px;
    white-space: nowrap;
    line-height: 1.2;
    border: none;
    cursor: pointer;
}
.btn-ai-gen     { background: #7c3aed !important; color: #fff !important; }
.btn-ai-improve { background: #0066cc !important; color: #fff !important; }
.btn-ai-rewrite { background: #e67e22 !important; color: #fff !important; }
.btn-ai-restore { background: #6c757d !important; color: #fff !important; }
/* Auto-gen banner */
#passport-autogen-banner {
    background: linear-gradient(135deg,#7c3aed,#5b21b6);
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 0.88rem;
}
#passport-autogen-banner button {
    padding: 6px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
}
.banner-btn-gen  { background: #fff; color: #7c3aed; }
.banner-btn-skip { background: rgba(255,255,255,0.2); color: #fff; }

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
.comment-print-text {
    display: none;
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
    @page { size: portrait; margin: 0; }
    body { background: #fff !important; margin: 0; padding: 12mm; font-size: 10pt; }

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

    /* Show comments in print as plain text div (not textarea) */
    .passport-table textarea,
    #passport-final-note {
        display: none !important;
    }
    .comment-print-text {
        display: block !important;
        font-size: 10pt;
        font-family: inherit;
        color: #333;
        white-space: pre-wrap;
        word-wrap: break-word;
        width: 100%;
        padding: 0;
        margin: 0;
        min-height: 14px;
    }

    /* Badge colors: hidden in print — scores visible on screen, not in printed document */
    .pct-badge {
        display: none !important;
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

/* ---- Bottom action bar ---- */
#passport-bottom-bar {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 2px solid #dee2e6;
    box-shadow: 0 -3px 10px rgba(0,0,0,0.08);
    padding: 7px 16px;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 16px;
}
#passport-bottom-bar-feedback {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: none;
}
#passport-bottom-bar-feedback.success { background:#d4edda; color:#155724; display:block; }
#passport-bottom-bar-feedback.error   { background:#f8d7da; color:#721c24; display:block; }
.btn-approved {
    background: #28a745 !important;
    color: #fff !important;
    border-color: #28a745 !important;
}
.btn-approved:hover { background: #218838 !important; }

/* ---- Approval stamp (shown in print if approved) ---- */
.passport-approval-stamp {
    display: none;
    margin-top: 32px;
    padding: 14px 20px;
    border: 2px solid #28a745;
    border-radius: 8px;
    background: #f0fff4;
    color: #155724;
    font-weight: 600;
    font-size: 0.95rem;
    text-align: center;
}
.passport-approval-stamp.is-approved {
    display: block;
}

/* Hide print header on screen */
.passport-print-header {
    display: none;
}

/* AI Modal */
.ai-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.ai-modal-overlay.open {
    display: flex;
}
.ai-modal {
    background: #fff;
    border-radius: 12px;
    padding: 28px 32px;
    max-width: 640px;
    width: 95%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.ai-modal h3 {
    margin: 0 0 6px 0;
    font-size: 1.2rem;
    color: #1a1a2e;
}
.ai-modal p {
    color: #666;
    font-size: 0.88rem;
    margin-bottom: 16px;
}
.ai-modal textarea {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 0.88rem;
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
}
.ai-modal textarea:focus {
    border-color: #7c3aed;
    outline: none;
    box-shadow: 0 0 0 3px rgba(124,58,237,0.15);
}
.ai-modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 16px;
    flex-wrap: wrap;
}
.ai-spinner {
    display: none;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }
.ai-progress-bar {
    display: none;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 12px;
    overflow: hidden;
}
.ai-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed, #a855f7);
    border-radius: 2px;
    transition: width 0.4s;
}
@media print {
    .ai-modal-overlay,
    .ai-area-btn,
    .ai-btn-group,
    .no-print { display: none !important; }
    #passport-final-note-section .passport-btn { display: none !important; }
    #passport-bottom-bar { display: none !important; }
    /* Hide all percentage values from print output */
    .radar-pct { display: none !important; }
    .print-score-block { display: none !important; }
    /* Show approval stamp in print */
    .passport-approval-stamp.is-approved { display: block !important; }
}
</style>

<div class="passport-container">

    <!-- AI Generation Modal -->
    <?php if ($canEvaluate): ?>
    <div class="ai-modal-overlay" id="ai-modal-overlay" onclick="closeAiModalOnOverlay(event)">
        <div class="ai-modal">
            <h3>&#129302; Genera con AI</h3>
            <p style="margin-bottom:10px;">I dati del profilo sono caricati dal Garage FTM. Il CV viene anonimizzato prima dell'invio.</p>
            <div style="background:#fff8e1;border-left:3px solid #f59e0b;padding:8px 12px;border-radius:4px;font-size:0.82rem;color:#555;margin-bottom:12px;">
                <strong>Consiglio:</strong> Incolla il CV del candidato — pi&ugrave; contesto fornisci (formazione, esperienze, motivazioni), pi&ugrave; precisi e contestualizzati saranno i commenti generati.
            </div>

            <label style="font-weight:600;font-size:0.88rem;color:#333;display:block;margin-bottom:6px;">
                CV del candidato (incolla testo)
            </label>
            <textarea id="ai-cv-text" rows="8" placeholder="Incolla qui il CV del candidato (testo libero, Word, PDF copiato...).&#10;&#10;Pi&ugrave; informazioni fornisci sulla storia professionale, pi&ugrave; il commento sara' pertinente alla persona."></textarea>

            <!-- Profilo precompilato (sola lettura nel modal, modificabile nel Garage) -->
            <div style="margin-top:14px;background:#f8f9fa;border-radius:6px;padding:14px;font-size:0.82rem;color:#555;line-height:1.8;">
                <strong style="color:#333;">Profilo guidato (dal Garage FTM):</strong><br>
                Settore target: <strong><?php echo $aiSettoreTarget ? s($aiSettoreTarget) : '&mdash;'; ?></strong> &nbsp;|&nbsp;
                Disponibilita: <strong><?php echo $aiDisponibilita ? s($aiDisponibilita) : '&mdash;'; ?></strong> &nbsp;|&nbsp;
                Mobilita: <strong><?php echo $aiMobilita ? s(str_replace('_', ' ', $aiMobilita)) : '&mdash;'; ?></strong><br>
                Motivazione ricerca lavoro: <strong><?php echo $aiPctCercaLavoro; ?>%</strong><br>
                <?php if ($aiPuntiForza): ?>Punti di forza: <em><?php echo s($aiPuntiForza); ?></em><br><?php endif; ?>
                <?php if ($aiNote): ?>Note: <em><?php echo s($aiNote); ?></em><?php endif; ?>
            </div>

            <div id="ai-progress-bar" class="ai-progress-bar">
                <div class="ai-progress-fill" id="ai-progress-fill" style="width:0%"></div>
            </div>
            <div id="ai-modal-status" style="display:none;margin-top:10px;font-size:0.82rem;color:#7c3aed;font-weight:600;"></div>

            <div class="ai-modal-footer">
                <button type="button" class="passport-btn passport-btn-secondary" onclick="closeAiModal()">Annulla</button>
                <button type="button" class="passport-btn" id="ai-generate-btn"
                        style="background:#7c3aed;color:#fff;"
                        onclick="generateAll()">
                    <span class="ai-spinner" id="ai-spinner"></span>
                    &#129302; Genera Tutto
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Auto-generate banner (shown by JS if there are empty comment areas) -->
    <?php if ($canEvaluate): ?>
    <div id="passport-autogen-banner" class="no-print" style="display:none;">
        <span id="passport-autogen-msg">&#9889; Alcune aree non hanno ancora un commento. Vuoi generarle automaticamente con AI?</span>
        <div style="display:flex;gap:8px;flex-shrink:0;">
            <button class="banner-btn-gen" onclick="autoGenerateEmpty()">&#129302; Genera automaticamente</button>
            <button class="banner-btn-skip" onclick="dismissAutoBanner()">Ignora</button>
        </div>
    </div>
    <?php endif; ?>

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
            <div class="score-pct"><?php echo $coachOverallPct !== null ? $coachOverallPct : $overallPct; ?>%</div>
            <div class="score-label"><?php echo $coachOverallPct !== null ? 'Valutazione Docente' : 'Punteggio Quiz'; ?></div>
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
            <?php if ($canEvaluate): ?>
            <button type="button" class="passport-btn" style="background:#7c3aed;color:#fff;" onclick="openAiModal()">
                &#129302; Genera con AI
            </button>
            <?php endif; ?>
            <button type="button" class="passport-btn passport-btn-primary" onclick="window.print()">
                Stampa Passaporto Tecnico
            </button>
        </div>
    </div>

    <!-- Save feedback -->
    <div id="passport-save-feedback" class="passport-save-feedback no-print"></div>

    <!-- Summary bar — solo settore con selettore -->
    <div class="passport-summary" style="justify-content:center;">
        <?php if (!empty($sectorsFound) && count($sectorsFound) > 1): ?>
        <div class="passport-summary-item">
            <div class="label">Settore</div>
            <div class="value">
                <select onchange="location.href='?userid=<?php echo $userid; ?>&courseid=<?php echo $courseid; ?>&cm_sector=' + this.value"
                        style="font-size:1.1rem; font-weight:700; border:2px solid #c62828; border-radius:6px; padding:4px 10px; color:#c62828; background:#fff; cursor:pointer;">
                    <?php foreach ($sectorsFound as $sf): ?>
                    <option value="<?php echo s($sf); ?>" <?php echo (strcasecmp($effectiveSectorFilter, $sf) === 0 || $sectorDisplay === get_sector_display_name($sf)) ? 'selected' : ''; ?>>
                        <?php echo s(get_sector_display_name($sf)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php elseif ($sectorDisplay): ?>
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
        // 1. VALUTAZIONE - NASCOSTA
        // ================================================================
        case 'valutazione':
            break;

        // ================================================================
        // 2. PROGRESSI - NASCOSTA
        // ================================================================
        case 'progressi':
            break;

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
        // 5. PIANO - NASCOSTO (rimosso dalla visualizzazione e stampa)
        // ================================================================
        case 'piano':
            // Sezione piano d'azione nascosta come richiesto.
            break;

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
                            <th style="width: 120px; text-align: center;">Punteggio Coach</th>
                            <th style="width: 45%;" class="col-comment">Commento Coach</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areasData as $areaKey => $area): ?>
                        <?php
                            // Use pre-calculated coach scores (built before output).
                            $cs = $coachScorePerArea[$areaKey] ?? ['avg' => 0, 'pct' => 0, 'count' => 0];
                            $pct = $cs['pct'];
                            $coachAvg = $cs['avg'];
                            $coachCount = $cs['count'];
                            $pctClass = passport_pct_class($pct);
                            $existingComment = $existingComments[$areaKey] ?? '';
                        ?>
                        <tr>
                            <td class="area-name"><?php echo s($area['name']); ?></td>
                            <td style="text-align: center;">
                                <?php if ($coachCount > 0): ?>
                                    <?php if ($displayFormat === 'qualitative'): ?>
                                    <span class="pct-badge <?php echo $pctClass; ?>"><?php echo passport_level_label($pct); ?></span>
                                    <?php else: ?>
                                    <span class="pct-badge <?php echo $pctClass; ?>"><?php echo $pct; ?>%</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#9ca3af; font-size:0.82rem;">Non valutato</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-comment">
                                <div style="display:flex;gap:8px;align-items:flex-start;">
                                    <textarea
                                        name="comment_<?php echo s($areaKey); ?>"
                                        data-area="<?php echo s($areaKey); ?>"
                                        data-original="<?php echo s($originalComments[$areaKey] ?? ''); ?>"
                                        rows="3"
                                        placeholder="Inserire commento..."
                                        class="passport-comment"
                                        <?php if (!$canEvaluate): ?>readonly<?php endif; ?>
                                        style="flex:1;"
                                    ><?php echo s($existingComment); ?></textarea>
                                    <?php if ($canEvaluate): ?>
                                    <div class="ai-btn-group no-print"
                                         data-group-area="<?php echo s($areaKey); ?>"
                                         data-area-name="<?php echo s($area['name']); ?>"
                                         data-pct="<?php echo $cs['pct']; ?>">
                                        <button type="button"
                                                class="passport-btn btn-ai-gen ai-area-btn"
                                                data-area="<?php echo s($areaKey); ?>"
                                                title="Genera commento da zero">&#129302;</button>
                                        <button type="button"
                                                class="passport-btn btn-ai-improve"
                                                data-area="<?php echo s($areaKey); ?>"
                                                title="Migliora testo esistente">&#10024;</button>
                                        <button type="button"
                                                class="passport-btn btn-ai-rewrite"
                                                data-area="<?php echo s($areaKey); ?>"
                                                title="Riscrivi (mantieni fatti, migliora testo)">&#128260;</button>
                                        <button type="button"
                                                class="passport-btn btn-ai-restore"
                                                data-area="<?php echo s($areaKey); ?>"
                                                title="Ripristina bozza salvata"
                                                style="display:none;">&#8617;</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-print-text"><?php echo nl2br(s($existingComment)); ?></div>
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

    <?php if ($canEvaluate || !empty($finalNote)): ?>
    <div class="passport-table-section" id="passport-final-note-section">
        <h2>Nota Finale per il Datore di Lavoro</h2>
        <p style="font-size:0.85rem;color:#888;margin-bottom:12px;">
            Testo di presentazione sintetico, pensato per essere letto da un potenziale datore di lavoro.
        </p>
        <?php if ($canEvaluate): ?>
        <div style="display:flex;gap:10px;align-items:flex-start;">
            <textarea id="passport-final-note"
                      data-area="FINAL_NOTE"
                      data-original="<?php echo s($originalComments['FINAL_NOTE'] ?? ''); ?>"
                      rows="6"
                      placeholder="Inserire nota finale..."
                      class="passport-comment"
                      style="flex:1;font-size:0.92rem;"><?php echo s($finalNote); ?></textarea>
            <div class="ai-btn-group no-print"
                 data-group-area="FINAL_NOTE"
                 data-area-name="Nota Finale"
                 data-pct="0">
                <button type="button"
                        class="passport-btn btn-ai-gen ai-area-btn"
                        data-area="FINAL_NOTE"
                        title="Genera nota finale da zero">&#129302;</button>
                <button type="button"
                        class="passport-btn btn-ai-improve"
                        data-area="FINAL_NOTE"
                        title="Migliora testo esistente">&#10024;</button>
                <button type="button"
                        class="passport-btn btn-ai-rewrite"
                        data-area="FINAL_NOTE"
                        title="Riscrivi mantenendo i fatti">&#128260;</button>
                <button type="button"
                        class="passport-btn btn-ai-restore"
                        data-area="FINAL_NOTE"
                        title="Ripristina testo originale coach"
                        style="display:none;">&#8617;</button>
            </div>
        </div>
        <div class="comment-print-text"><?php echo nl2br(s($finalNote)); ?></div>
        <div class="no-print" style="margin-top:10px;">
            <button type="button" class="passport-btn passport-btn-success" onclick="saveFinalNote()">
                Salva Nota Finale
            </button>
        </div>
        <?php else: ?>
        <?php if (!empty($finalNote)): ?>
        <div style="font-size:0.92rem;color:#333;line-height:1.7;white-space:pre-wrap;"><?php echo s($finalNote); ?></div>
        <?php else: ?>
        <p style="color:#888;">Nessuna nota finale disponibile.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Approval stamp — visible in print, hidden on screen unless approved -->
    <div class="passport-approval-stamp <?php echo $isApproved ? 'is-approved' : ''; ?>" id="passport-approval-stamp">
        &#10003; Passaporto Tecnico approvato<?php if ($isApproved): ?> il <?php echo s($approvalTimeFormatted); ?> da <?php echo s($approvalCoachName); ?><?php endif; ?>
    </div>

    <?php if ($canEvaluate): ?>
    <!-- Bottom action bar — sticky inside content column, no-print -->
    <div id="passport-bottom-bar" class="no-print">
        <span id="passport-approval-info" style="font-size:0.82rem;font-weight:600;margin-right:auto;">
            <?php if ($isApproved): ?>
            <span style="color:#28a745;">&#10003; Approvato il <?php echo s($approvalTimeFormatted); ?> da <?php echo s($approvalCoachName); ?></span>
            <?php else: ?>
            <span style="color:#888;">Non ancora approvato</span>
            <?php endif; ?>
        </span>
        <span id="passport-bottom-bar-feedback"></span>
        <button type="button" class="passport-btn passport-btn-success" onclick="savePassportComments()">
            Salva Commenti
        </button>
        <button type="button"
                id="btn-approve-passport"
                class="passport-btn <?php echo $isApproved ? 'btn-approved' : ''; ?>"
                style="<?php echo $isApproved ? '' : 'background:#6c757d;color:#fff;'; ?>"
                onclick="toggleApproval()">
            <?php echo $isApproved ? '&#10003; Approvato &mdash; Annulla' : '&#10003; Approva'; ?>
        </button>
    </div>
    <?php endif; ?>

</div><!-- end passport-container -->

<?php if ($canEvaluate && !empty($areasData)): ?>
<script>
// ============================================================
// AI PASSPORT GENERATION — v2 (auto-gen, improve, rewrite, baseline)
// ============================================================
var AI_ENDPOINT = '<?php echo (new moodle_url('/local/competencymanager/ajax_generate_ai_passport.php'))->out(false); ?>';
var AI_SESSKEY  = '<?php echo sesskey(); ?>';
var AI_USERID   = <?php echo $userid; ?>;
var AI_COURSEID = <?php echo $courseid; ?>;

// ---- Modal helpers ----
function openAiModal() {
    document.getElementById('ai-modal-overlay').classList.add('open');
}
function closeAiModal() {
    document.getElementById('ai-modal-overlay').classList.remove('open');
    setAiStatus('');
    document.getElementById('ai-progress-bar').style.display = 'none';
    document.getElementById('ai-progress-fill').style.width = '0%';
}
function closeAiModalOnOverlay(e) {
    if (e.target === document.getElementById('ai-modal-overlay')) closeAiModal();
}
function setAiStatus(msg) {
    var el = document.getElementById('ai-modal-status');
    if (msg) { el.style.display = 'block'; el.textContent = msg; }
    else      { el.style.display = 'none';  el.textContent = ''; }
}
function setAiProgress(pct) {
    document.getElementById('ai-progress-bar').style.display = 'block';
    document.getElementById('ai-progress-fill').style.width = pct + '%';
}

// ---- Baseline helpers (localStorage) ----
function _blKey(areaKey) {
    return 'passport_bl_' + AI_USERID + '_' + AI_COURSEID + '_' + areaKey;
}
function getBaseline(areaKey) {
    return localStorage.getItem(_blKey(areaKey)) || '';
}
function setBaseline(areaKey, text) {
    if (text) {
        localStorage.setItem(_blKey(areaKey), text);
    } else {
        localStorage.removeItem(_blKey(areaKey));
    }
}

// ---- Button group state ----
// Gen / Migliora / Riscrivi are ALWAYS visible.
// Restore (↩) appears when:
//   - the textarea has a data-original (coach's DB-persisted original), AND
//   - the current text differs from that original.
function updateAreaButtons(ta) {
    if (!ta) return;
    var areaKey  = ta.getAttribute('data-area');
    var group    = document.querySelector('.ai-btn-group[data-group-area="' + areaKey + '"]');
    if (!group) return;
    var original   = ta.getAttribute('data-original') || '';
    var current    = ta.value.trim();
    var hasOriginal = original.length > 0;
    var isDirty    = hasOriginal && original !== current;
    var btnRestore = group.querySelector('.btn-ai-restore');
    if (btnRestore) btnRestore.style.display = isDirty ? 'block' : 'none';
}

// ---- Spinner helper ----
var _spinnerHtml = '<span style="display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;vertical-align:middle;"></span>';
function _btnSpin(btn, on, orig) {
    if (!btn) return;
    if (on)  { btn.disabled = true;  btn.innerHTML = _spinnerHtml; }
    else     { btn.disabled = false; btn.innerHTML = orig; }
}

// ---- Fill textarea from AI response ----
function _fillTextarea(ta, text) {
    ta.value = text;
    ta.style.borderColor = '#28a745';
    ta.style.boxShadow   = '0 0 0 3px rgba(40,167,69,0.2)';
    setTimeout(function() { ta.style.borderColor = ''; ta.style.boxShadow = ''; }, 2500);
    // Sync print div
    var printDiv = ta.parentElement ? ta.parentElement.nextElementSibling : ta.nextElementSibling;
    if (printDiv && printDiv.classList.contains('comment-print-text')) {
        printDiv.innerHTML = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }
    updateAreaButtons(ta);
}

/**
 * Core AI call for a single area.
 * aiAction: 'area' (generate from scratch), 'improve', 'rewrite'
 */
function callAiForArea(areaKey, aiAction, btnEl) {
    if (areaKey === 'FINAL_NOTE') {
        _callFinalNote(btnEl, aiAction);
        return;
    }

    var ta = document.querySelector('textarea[data-area="' + areaKey + '"]');
    if (!ta) return;

    var cvText = (document.getElementById('ai-cv-text') || {}).value || '';
    var draftText = (aiAction === 'improve' || aiAction === 'rewrite') ? ta.value.trim() : '';

    // Require existing text for improve/rewrite
    if ((aiAction === 'improve' || aiAction === 'rewrite') && !draftText) {
        alert('Scrivi prima un testo nel commento, poi usa Migliora o Riscrivi.');
        return;
    }

    var origHtml = btnEl ? btnEl.innerHTML : '';
    _btnSpin(btnEl, true);

    var params = 'sesskey=' + encodeURIComponent(AI_SESSKEY)
        + '&userid=' + AI_USERID
        + '&courseid=' + AI_COURSEID
        + '&action=' + encodeURIComponent(aiAction)
        + '&area_key=' + encodeURIComponent(areaKey)
        + '&cv_text=' + encodeURIComponent(cvText)
        + '&draft_text=' + encodeURIComponent(draftText);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AI_ENDPOINT, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        _btnSpin(btnEl, false, origHtml);
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success && resp.text) {
                _fillTextarea(ta, resp.text);
            } else {
                alert('Errore AI: ' + (resp.message || 'Risposta non valida'));
            }
        } catch(e) {
            alert('Errore di comunicazione con il server.');
        }
    };
    xhr.send(params);
}

function _callFinalNote(btnEl, aiAction) {
    var ta = document.getElementById('passport-final-note');
    if (!ta) return;
    aiAction = aiAction || 'final_note';
    var cvText = (document.getElementById('ai-cv-text') || {}).value || '';
    var draftText = (aiAction === 'improve' || aiAction === 'rewrite') ? ta.value.trim() : '';

    if ((aiAction === 'improve' || aiAction === 'rewrite') && !draftText) {
        alert('Scrivi prima un testo nella nota finale, poi usa Migliora o Riscrivi.');
        return;
    }

    var action = (aiAction === 'improve' || aiAction === 'rewrite') ? aiAction : 'final_note';
    var origHtml = btnEl ? btnEl.innerHTML : '';
    _btnSpin(btnEl, true);

    var params = 'sesskey=' + encodeURIComponent(AI_SESSKEY)
        + '&userid=' + AI_USERID + '&courseid=' + AI_COURSEID
        + '&action=' + encodeURIComponent(action) + '&area_key=FINAL_NOTE'
        + '&cv_text=' + encodeURIComponent(cvText)
        + '&draft_text=' + encodeURIComponent(draftText);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AI_ENDPOINT, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        _btnSpin(btnEl, false, origHtml);
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success && resp.text) { _fillTextarea(ta, resp.text); }
            else alert('Errore AI: ' + (resp.message || 'Risposta non valida'));
        } catch(e) { alert('Errore di comunicazione con il server.'); }
    };
    xhr.send(params);
}

// Legacy shim — still used by modal "Genera Tutto per area singola" buttons
function generateAiSingle(areaKey, areaName, areaPct, btnEl) {
    callAiForArea(areaKey, 'area', btnEl);
}

/**
 * Generate ALL comments + final note in a single call (modal "Genera Tutto").
 */
function generateAll() {
    var cvText = document.getElementById('ai-cv-text').value.trim();
    var btn = document.getElementById('ai-generate-btn');
    var spinner = document.getElementById('ai-spinner');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    setAiProgress(5);
    setAiStatus('Invio dati al server...');

    var params = 'sesskey=' + encodeURIComponent(AI_SESSKEY)
        + '&userid=' + AI_USERID
        + '&courseid=' + AI_COURSEID
        + '&action=all'
        + '&cv_text=' + encodeURIComponent(cvText);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AI_ENDPOINT, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        btn.disabled = false;
        spinner.style.display = 'none';
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                setAiProgress(100);
                setAiStatus('Commenti generati. Salvataggio in corso...');
                if (resp.areas) {
                    Object.keys(resp.areas).forEach(function(ak) {
                        var ta = document.querySelector('textarea[data-area="' + ak + '"]');
                        if (ta) _fillTextarea(ta, resp.areas[ak]);
                    });
                }
                if (resp.final_note) {
                    var fnTa = document.getElementById('passport-final-note');
                    if (fnTa) fnTa.value = resp.final_note;
                }
                setTimeout(function() {
                    savePassportComments(false);  // AI auto-save: don't create __ORIG
                    saveFinalNote(false);
                    setAiStatus('Salvato!');
                    setTimeout(function() { closeAiModal(); }, 1200);
                }, 500);
            } else {
                setAiStatus('Errore: ' + (resp.message || 'Risposta non valida'));
            }
        } catch(e) {
            setAiStatus('Errore di comunicazione con il server.');
        }
    };
    xhr.send(params);
}

// ---- Auto-generate banner ----
function dismissAutoBanner() {
    var b = document.getElementById('passport-autogen-banner');
    if (b) b.style.display = 'none';
}
function autoGenerateEmpty() {
    dismissAutoBanner();
    openAiModal();
    // Slight delay to let modal open, then trigger generateAll
    setTimeout(function() { generateAll(); }, 300);
}

// ---- Init on DOM ready ----
document.addEventListener('DOMContentLoaded', function() {
    var emptyCount = 0;

    // Initialise button state for each textarea
    document.querySelectorAll('.passport-comment').forEach(function(ta) {
        var val = ta.value.trim();
        if (!val) emptyCount++;
        updateAreaButtons(ta);

        // Update button state live as coach types
        ta.addEventListener('input', function() { updateAreaButtons(ta); });
    });

    // Show auto-gen banner if there are empty areas
    if (emptyCount > 0) {
        var banner = document.getElementById('passport-autogen-banner');
        var msg    = document.getElementById('passport-autogen-msg');
        if (banner) {
            if (msg) msg.textContent = '⚡ ' + emptyCount + ' aree senza commento. Vuoi generarle automaticamente con AI?';
            banner.style.display = 'flex';
        }
    }

    // Attach generate buttons (btn-ai-gen)
    document.querySelectorAll('.btn-ai-gen').forEach(function(btn) {
        btn.addEventListener('click', function() {
            callAiForArea(btn.getAttribute('data-area'), 'area', btn);
        });
    });

    // Attach improve buttons
    document.querySelectorAll('.btn-ai-improve').forEach(function(btn) {
        btn.addEventListener('click', function() {
            callAiForArea(btn.getAttribute('data-area'), 'improve', btn);
        });
    });

    // Attach rewrite buttons
    document.querySelectorAll('.btn-ai-rewrite').forEach(function(btn) {
        btn.addEventListener('click', function() {
            callAiForArea(btn.getAttribute('data-area'), 'rewrite', btn);
        });
    });

    // Attach restore buttons — always use data-original (DB-persisted coach baseline)
    document.querySelectorAll('.btn-ai-restore').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var areaKey = btn.getAttribute('data-area');
            var ta = document.querySelector('textarea[data-area="' + areaKey + '"]');
            if (!ta) return;
            var original = ta.getAttribute('data-original') || '';
            if (!original) { alert('Nessun testo originale del coach salvato per questa area.'); return; }
            ta.value = original;
            var printDiv = ta.parentElement ? ta.parentElement.nextElementSibling : ta.nextElementSibling;
            if (printDiv && printDiv.classList.contains('comment-print-text')) {
                printDiv.innerHTML = original.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
            }
            updateAreaButtons(ta);
        });
    });
});

</script>
<?php endif; ?>

<?php if ($canEvaluate): ?>
<script>
var SAVE_URL      = '<?php echo (new moodle_url('/local/competencymanager/ajax_save_passport_comments.php'))->out(false); ?>';
var SAVE_SESSKEY  = '<?php echo sesskey(); ?>';
var SAVE_USERID   = <?php echo $userid; ?>;
var SAVE_COURSEID = <?php echo $courseid; ?>;
var APPROVE_URL   = '<?php echo (new moodle_url('/local/competencymanager/ajax_approve_passport.php'))->out(false); ?>';

window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.passport-comment').forEach(function(ta) {
        // Print div is sibling of the flex container (ta.parentElement), same pattern as _fillTextarea
        var printDiv = ta.parentElement ? ta.parentElement.nextElementSibling : ta.nextElementSibling;
        if (printDiv && printDiv.classList.contains('comment-print-text')) {
            printDiv.innerHTML = ta.value.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
        }
    });
});

function showSaveFeedback(ok, msg) {
    // Bottom bar feedback
    var fb = document.getElementById('passport-bottom-bar-feedback');
    if (fb) {
        fb.className = ok ? 'success' : 'error';
        fb.textContent = msg;
        setTimeout(function() { fb.className = ''; fb.textContent = ''; }, 4000);
    }
    // Top feedback (legacy)
    var f = document.getElementById('passport-save-feedback');
    if (f) {
        f.className = 'passport-save-feedback ' + (ok ? 'success' : 'error') + ' no-print';
        f.textContent = msg;
        f.style.display = 'block';
        setTimeout(function() { f.style.display = 'none'; }, 5000);
    }
}

function toggleApproval() {
    var btn = document.getElementById('btn-approve-passport');
    var isCurrentlyApproved = btn && btn.classList.contains('btn-approved');
    var action = isCurrentlyApproved ? 'revoke' : 'approve';

    if (isCurrentlyApproved && !confirm('Sei sicuro di voler annullare l\'approvazione di questo passaporto?')) return;

    if (btn) btn.disabled = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APPROVE_URL, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        if (btn) btn.disabled = false;
        try {
            var resp = JSON.parse(xhr.responseText);
            if (!resp.success) { alert('Errore: ' + (resp.message || 'Operazione fallita')); return; }

            var stamp  = document.getElementById('passport-approval-stamp');
            var info   = document.getElementById('passport-approval-info');

            if (action === 'approve') {
                // Update button
                if (btn) {
                    btn.classList.add('btn-approved');
                    btn.style.background = ''; btn.style.color = '';
                    btn.innerHTML = '&#10003; Approvato &mdash; Annulla';
                }
                // Update info bar
                if (info) info.innerHTML = '<span style="color:#28a745;">&#10003; Approvato il ' + resp.date + ' da ' + resp.coach + '</span>';
                // Update / show stamp
                if (stamp) {
                    stamp.innerHTML = '&#10003; Passaporto Tecnico approvato il ' + resp.date + ' da ' + resp.coach;
                    stamp.classList.add('is-approved');
                }
                showSaveFeedback(true, 'Passaporto approvato. I commenti saranno usati come esempio per la AI (' + resp.examples_count + ' aree salvate).');
            } else {
                if (btn) {
                    btn.classList.remove('btn-approved');
                    btn.style.background = '#6c757d'; btn.style.color = '#fff';
                    btn.innerHTML = '&#10003; Approva';
                }
                if (info) info.innerHTML = '<span style="color:#888;">Non ancora approvato</span>';
                if (stamp) stamp.classList.remove('is-approved');
                showSaveFeedback(true, 'Approvazione annullata.');
            }
        } catch(e) {
            alert('Errore di comunicazione con il server.');
        }
    };
    xhr.send('sesskey=' + encodeURIComponent(SAVE_SESSKEY)
        + '&userid=' + SAVE_USERID
        + '&courseid=' + SAVE_COURSEID
        + '&action=' + action);
}

function ajaxSaveComments(commentsArr, successMsg, isCoachSave) {
    var coachSave = (isCoachSave !== false) ? 1 : 0;
    var xhr = new XMLHttpRequest();
    var params = 'sesskey=' + encodeURIComponent(SAVE_SESSKEY)
        + '&userid=' + SAVE_USERID
        + '&courseid=' + SAVE_COURSEID
        + '&is_coach_save=' + coachSave
        + '&comments=' + encodeURIComponent(JSON.stringify(commentsArr));
    xhr.open('POST', SAVE_URL, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        try {
            var resp = JSON.parse(xhr.responseText);
            showSaveFeedback(resp.success, resp.success ? successMsg : (resp.message || 'Errore nel salvataggio.'));
            // After a coach save, lock in data-original for textareas that don't have it yet
            if (resp.success && coachSave) {
                commentsArr.forEach(function(item) {
                    if (!item.comment) return;
                    var ta = document.querySelector('textarea[data-area="' + item.area_code + '"]');
                    if (ta && !ta.getAttribute('data-original')) {
                        ta.setAttribute('data-original', item.comment);
                        updateAreaButtons(ta);
                    }
                });
            }
        } catch(e) {
            showSaveFeedback(false, 'Errore di comunicazione con il server.');
        }
    };
    xhr.send(params);
}

function savePassportComments(isCoachSave) {
    var comments = [];
    document.querySelectorAll('.passport-comment').forEach(function(ta) {
        var areaKey = ta.getAttribute('data-area');
        var txt = ta.value.trim();
        comments.push({ area_code: areaKey, comment: txt });
        if (typeof updateAreaButtons === 'function') updateAreaButtons(ta);
    });
    ajaxSaveComments(comments, 'Commenti salvati con successo.', isCoachSave !== false);
}

function saveFinalNote(isCoachSave) {
    var ta = document.getElementById('passport-final-note');
    if (!ta) return;
    ajaxSaveComments([{ area_code: 'FINAL_NOTE', comment: ta.value.trim() }], 'Nota finale salvata.', isCoachSave !== false);
    updateAreaButtons(ta);
}
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
