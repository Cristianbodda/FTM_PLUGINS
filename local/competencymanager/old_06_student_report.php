<?php
/**
 * Report singolo studente - VERSIONE COMPLETA
 * Con selezione MULTIPLA dei quiz, doppio grafico radar INTERATTIVO
 * STAMPA PERSONALIZZATA con selezione sezioni e GRAFICI SVG
 * DESCRIZIONI DINAMICHE dal database Moodle
 * 
 * ============================================
 * VERSIONE CON ESTENSIONI ADDITIVE COACHMANAGER
 * - Doppio Radar (Autovalutazione affiancato)
 * - Gap Analysis
 * - Spunti Colloquio
 * ============================================
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$area = optional_param('area', '', PARAM_TEXT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$print = optional_param('print', 0, PARAM_INT);
$selectedArea = optional_param('selectedarea', '', PARAM_TEXT);

// Parametri per filtro e ordinamento nella tab Dettagli
$sortBy = optional_param('sort', 'area', PARAM_ALPHA);
$filterLevel = optional_param('filter', 'all', PARAM_ALPHANUMEXT);
$filterArea = optional_param('filter_area', '', PARAM_TEXT);

// Parametri stampa personalizzata
$printPanoramica = optional_param('print_panoramica', 1, PARAM_INT);
$printPiano = optional_param('print_piano', 1, PARAM_INT);
$printProgressi = optional_param('print_progressi', 1, PARAM_INT);
$printDettagli = optional_param('print_dettagli', 1, PARAM_INT);
$printRadarAree = optional_param('print_radar_aree', 1, PARAM_INT);
$printRadarAreas = optional_param_array('print_radar_areas', [], PARAM_TEXT);

// ============================================
// NUOVI PARAMETRI ADDITIVI (CoachManager)
// ============================================
$showDualRadar = optional_param('show_dual_radar', 0, PARAM_INT);
$showGapAnalysis = optional_param('show_gap', 0, PARAM_INT);
$showSpuntiColloquio = optional_param('show_spunti', 0, PARAM_INT);

// Nuovi parametri stampa additivi
$printDualRadar = optional_param('print_dual_radar', 0, PARAM_INT);
$printGapAnalysis = optional_param('print_gap', 0, PARAM_INT);
$printSpuntiColloquio = optional_param('print_spunti', 0, PARAM_INT);

// ============================================
// SOGLIE CONFIGURABILI (impostabili dal coach)
// ============================================
// Soglia per considerare un gap come "allineato" (default 15%)
$sogliaAllineamento = optional_param('soglia_allineamento', 15, PARAM_INT);
// Limita il range valido: minimo 5%, massimo 40%
$sogliaAllineamento = max(5, min(40, $sogliaAllineamento));

// Soglia per considerare un gap come "critico" (default 30%)
$sogliaCritico = optional_param('soglia_critico', 30, PARAM_INT);
// Limita il range valido: minimo 20%, massimo 60%
$sogliaCritico = max(20, min(60, $sogliaCritico));

// Assicura che soglia critico sia sempre > soglia allineamento
if ($sogliaCritico <= $sogliaAllineamento) {
    $sogliaCritico = $sogliaAllineamento + 15;
}
// ============================================

$selectedQuizzes = optional_param_array('quizids', [], PARAM_INT);
$singleQuizId = optional_param('quizid', 0, PARAM_INT);
if ($singleQuizId && empty($selectedQuizzes)) {
    $selectedQuizzes = [$singleQuizId];
}

$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

if ($courseid) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();
$isownreport = ($USER->id == $userid);

if (!$canviewall && !$isadmin) {
    if ($isownreport && $courseid) {
        if (!\local_competencymanager\report_generator::student_can_view_own_report($userid, $courseid)) {
            throw new moodle_exception('nopermissions', 'error', '', 'view this report');
        }
    } else {
        throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid, 'tab' => $tab]));
$PAGE->set_title(get_string('studentreport', 'local_competencymanager') . ': ' . fullname($student));
$PAGE->set_heading(get_string('studentreport', 'local_competencymanager'));
$PAGE->set_pagelayout('report');

// ========================================
// FUNZIONI HELPER
// ========================================

function get_evaluation_band($percentage) {
    if ($percentage >= 80) {
        return ['label' => 'ECCELLENTE', 'icon' => '🌟', 'color' => '#28a745', 'bgColor' => '#d4edda', 'description' => 'Padronanza completa della competenza.', 'action' => 'Pronto per attività avanzate e tutoraggio compagni.', 'class' => 'success'];
    } else if ($percentage >= 60) {
        return ['label' => 'BUONO', 'icon' => '✅', 'color' => '#20c997', 'bgColor' => '#d1f2eb', 'description' => 'Competenza acquisita con buona padronanza.', 'action' => 'Consolidare con esercizi pratici.', 'class' => 'info'];
    } else if ($percentage >= 50) {
        return ['label' => 'SUFFICIENTE', 'icon' => '⚠️', 'color' => '#ffc107', 'bgColor' => '#fff3cd', 'description' => 'Base acquisita ma da consolidare.', 'action' => 'Ripasso teoria ed esercitazioni.', 'class' => 'warning'];
    } else if ($percentage >= 30) {
        return ['label' => 'INSUFFICIENTE', 'icon' => '⚡', 'color' => '#fd7e14', 'bgColor' => '#ffe5d0', 'description' => 'Lacune significative identificate.', 'action' => 'Percorso di recupero mirato richiesto.', 'class' => 'orange'];
    } else {
        return ['label' => 'CRITICO', 'icon' => '🔴', 'color' => '#dc3545', 'bgColor' => '#f8d7da', 'description' => 'Competenza non acquisita.', 'action' => 'Formazione base completa richiesta.', 'class' => 'danger'];
    }
}

function aggregate_by_area($competencies, $areaDescriptions, $sector = '') {
    global $AREA_NAMES;
    $areas = [];
    $colors = ['#667eea','#764ba2','#f093fb','#f5576c','#4facfe','#00f2fe','#43e97b','#38f9d7','#fa709a','#fee140','#30cfd0','#c471f5','#48c6ef','#6f86d6'];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $areaInfo = get_area_info($code);
        $areaCode = $areaInfo['code'];
        $areaName = $areaInfo['name'];
        if (!isset($areas[$areaCode])) {
            $colorIndex = count($areas) % count($colors);
            $areas[$areaCode] = [
                'code' => $areaCode,
                'name' => $areaName,
                'icon' => '📁',
                'color' => $colors[$colorIndex],
                'total_questions' => 0,
                'correct_questions' => 0,
                'competencies' => [],
                'count' => 0
            ];
        }
        $areas[$areaCode]['total_questions'] += $comp['total_questions'];
        $areas[$areaCode]['correct_questions'] += $comp['correct_questions'];
        $areas[$areaCode]['competencies'][] = $comp;
        $areas[$areaCode]['count']++;
    }
    foreach ($areas as $code => &$area) {
        $area['percentage'] = $area['total_questions'] > 0 ? round($area['correct_questions'] / $area['total_questions'] * 100, 1) : 0;
    }
    ksort($areas);
    return $areas;
}

function generate_action_plan($competencies, $competencyDescriptions) {
    $plan = ['excellence' => [], 'good' => [], 'toImprove' => [], 'critical' => []];
    
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $info = $competencyDescriptions[$code] ?? ['name' => $code, 'full_name' => $code];
        $percentage = $comp['percentage'];
        $displayName = $info['full_name'] ?? $info['name'] ?? $code;
        
        $item = ['code' => $code, 'name' => $displayName, 'percentage' => $percentage, 'correct' => $comp['correct_questions'], 'total' => $comp['total_questions'], 'description' => $comp['description'] ?? $info['description'] ?? ''];
        
        if ($percentage >= 80) $plan['excellence'][] = $item;
        else if ($percentage >= 60) $plan['good'][] = $item;
        else if ($percentage >= 30) $plan['toImprove'][] = $item;
        else $plan['critical'][] = $item;
    }
    
    usort($plan['critical'], fn($a, $b) => $a['percentage'] <=> $b['percentage']);
    usort($plan['toImprove'], fn($a, $b) => $a['percentage'] <=> $b['percentage']);
    usort($plan['good'], fn($a, $b) => $b['percentage'] <=> $a['percentage']);
    usort($plan['excellence'], fn($a, $b) => $b['percentage'] <=> $a['percentage']);
    
    return $plan;
}

function generate_certification_progress($competencies) {
    $certified = $inProgress = $notStarted = 0;
    foreach ($competencies as $comp) {
        if ($comp['percentage'] >= 80) $certified++;
        else if ($comp['percentage'] > 0) $inProgress++;
        else $notStarted++;
    }
    $total = count($competencies);
    return ['certified' => $certified, 'inProgress' => $inProgress, 'notStarted' => $notStarted, 'total' => $total, 'percentage' => $total > 0 ? round($certified / $total * 100) : 0];
}

// ============================================
// NUOVE FUNZIONI ADDITIVE (CoachManager)
// ============================================
/**
 * Recupera dati autovalutazione dello studente (se esistono)
 * Cerca in local_coachmanager_assessment E in local_selfassessment
 */
function get_student_self_assessment($userid, $courseid) {
    global $DB;
    $dbman = $DB->get_manager();
    
    // METODO 1: Cerca in local_coachmanager_assessment
    if ($dbman->table_exists('local_coachmanager_assessment')) {
        $assessments = $DB->get_records('local_coachmanager_assessment', [
            'userid' => $userid,
            'courseid' => $courseid
        ], 'timecreated DESC', '*', 0, 1);
        if (!empty($assessments)) {
            $assessment = reset($assessments);
            $details = json_decode($assessment->details, true);
            if (!empty($details['competencies'])) {
                $autovalutazione = [];
                foreach ($details['competencies'] as $comp) {
                    $autovalutazione[$comp['idnumber']] = [
                        'idnumber' => $comp['idnumber'],
                        'name' => $comp['name'] ?? $comp['idnumber'],
                        'bloom_level' => $comp['bloom_level'] ?? 0,
                        'percentage' => isset($comp['bloom_level']) ? round(($comp['bloom_level'] / 6) * 100, 1) : 0
                    ];
                }
                return ['data' => $autovalutazione, 'timestamp' => $assessment->timecreated];
            }
        }
    }
    
    // METODO 2: Cerca in local_selfassessment
    if ($dbman->table_exists('local_selfassessment')) {
        $sql = "SELECT sa.id, sa.competencyid, sa.level, sa.timecreated, c.idnumber, c.shortname, c.description FROM {local_selfassessment} sa JOIN {competency} c ON sa.competencyid = c.id WHERE sa.userid = :userid ORDER BY c.idnumber ASC";
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        if (!empty($records)) {
            $autovalutazione = [];
            $latestTimestamp = 0;
            foreach ($records as $rec) {
                $autovalutazione[$rec->idnumber] = [
                    'idnumber' => $rec->idnumber,
                    'name' => $rec->shortname ?: $rec->idnumber,
                    'bloom_level' => (int)$rec->level,
                    'percentage' => round(($rec->level / 6) * 100, 1)
                ];
                if ($rec->timecreated > $latestTimestamp) {
                    $latestTimestamp = $rec->timecreated;
                }
            }
            return ['data' => $autovalutazione, 'timestamp' => $latestTimestamp];
        }
    }
    
    return null;
}

/**
 * Calcola Gap Analysis tra autovalutazione e performance reale
 */
function calculate_gap_analysis($autovalutazione, $performance, $soglia = 15) {
    if (empty($autovalutazione) || empty($performance)) {
        return [];
    }
    
    $gap = [];
    $perfMap = [];
    foreach ($performance as $comp) {
        $code = $comp['idnumber'] ?? $comp['name'];
        $perfMap[$code] = $comp['percentage'] ?? 0;
    }
    
    foreach ($autovalutazione as $code => $auto) {
        $autoVal = $auto['percentage'];
        $perfVal = $perfMap[$code] ?? null;
        
        if ($perfVal === null) continue;
        
        $diff = $autoVal - $perfVal;
        
        if ($diff > $soglia) {
            $tipo = 'sopravvalutazione';
            $icona = '⬆️';
            $colore = '#dc3545';
            $bg = '#fadbd8';
        } else if ($diff < -$soglia) {
            $tipo = 'sottovalutazione';
            $icona = '⬇️';
            $colore = '#f39c12';
            $bg = '#fef9e7';
        } else {
            $tipo = 'allineato';
            $icona = '✅';
            $colore = '#28a745';
            $bg = '#d5f5e3';
        }
        
        $gap[$code] = [
            'idnumber' => $code,
            'name' => $auto['name'],
            'autovalutazione' => $autoVal,
            'performance' => $perfVal,
            'differenza' => $diff,
            'tipo' => $tipo,
            'icona' => $icona,
            'colore' => $colore,
            'bg' => $bg
        ];
    }
    
    uasort($gap, fn($a, $b) => abs($b['differenza']) <=> abs($a['differenza']));
    return $gap;
}

/**
 * Genera spunti per il colloquio basati sul gap analysis
 * @param array $gapData Dati gap analysis
 * @param array $competencyDescriptions Descrizioni competenze
 * @param int $sogliaCritico Soglia percentuale per gap critici (default 30%)
 */
function generate_colloquio_hints($gapData, $competencyDescriptions, $sogliaCritico = 30) {
    $hints = ['critici' => [], 'attenzione' => [], 'positivi' => []];
    
    foreach ($gapData as $code => $gap) {
        $compInfo = $competencyDescriptions[$code] ?? ['name' => $code, 'full_name' => $code];
        $displayName = $compInfo['full_name'] ?? $compInfo['name'] ?? $code;
        
        $hint = ['competenza' => $displayName, 'codice' => $code, 'gap' => $gap];
        $diffAbs = abs($gap['differenza']);
        
        if ($gap['tipo'] === 'sopravvalutazione') {
            if ($diffAbs > $sogliaCritico) {  // ← USA PARAMETRO INVECE DI 30 HARDCODED
                $hint['messaggio'] = "Lo studente si percepisce molto più competente di quanto dimostrato. Approfondire le lacune.";
                $hint['domanda'] = "Come valuti le tue competenze in {$displayName}? Quali difficoltà hai incontrato?";
                $hints['critici'][] = $hint;
            } else {
                $hint['messaggio'] = "Leggera sopravvalutazione. Consolidare con esercizi pratici.";
                $hint['domanda'] = "Cosa potresti fare per migliorare in {$displayName}?";
                $hints['attenzione'][] = $hint;
            }
        } else if ($gap['tipo'] === 'sottovalutazione') {
            $hint['messaggio'] = "Lo studente si sottovaluta! Valorizzare i risultati positivi.";
            $hint['domanda'] = "Hai notato che nei quiz hai ottenuto risultati migliori di quanto ti aspettassi?";
            $hints['positivi'][] = $hint;
        } else {
            $hint['messaggio'] = "Buon allineamento tra percezione e realtà.";
            $hints['positivi'][] = $hint;
        }
    }
    
    return $hints;
}

/**
 * Aggrega autovalutazione per area
 */
function aggregate_autovalutazione_by_area($autovalutazione, $areaDescriptions, $sector = '') {
    global $AREA_NAMES;
    $areas = [];
    $colors = ['#667eea','#764ba2','#f093fb','#f5576c','#4facfe','#00f2fe','#43e97b','#38f9d7','#fa709a','#fee140'];
    foreach ($autovalutazione as $code => $comp) {
        $areaInfo = get_area_info($code);
        $areaCode = $areaInfo['code'];
        $areaName = $areaInfo['name'];
        if (!isset($areas[$areaCode])) {
            $colorIndex = count($areas) % count($colors);
            $areas[$areaCode] = [
                'code' => $areaCode,
                'name' => $areaName,
                'icon' => '📁',
                'color' => $colors[$colorIndex],
                'total_percentage' => 0,
                'count' => 0
            ];
        }
        $areas[$areaCode]['total_percentage'] += $comp['percentage'];
        $areas[$areaCode]['count']++;
    }
    foreach ($areas as $code => &$area) {
        $area['percentage'] = $area['count'] > 0 ? round($area['total_percentage'] / $area['count'], 1) : 0;
    }
    ksort($areas);
    return $areas;
}

// ============================================
// FINE FUNZIONI ADDITIVE
// ============================================

/**
 * Genera un grafico radar SVG
 */
function generate_svg_radar($data, $title = '', $size = 300, $fillColor = 'rgba(102,126,234,0.3)', $strokeColor = '#667eea') {
    if (empty($data)) return '<p class="text-muted">Nessun dato disponibile</p>';
    
    $cx = $size / 2;
    $cy = $size / 2;
    $radius = ($size / 2) - 60;
    $n = count($data);
    
    if ($n < 3) {
        return generate_svg_bar_chart($data, $title, $size);
    }
    
    $angleStep = (2 * M_PI) / $n;
    
    $svg = '<svg width="' . $size . '" height="' . ($size + 50) . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';
    
    if ($title) {
        $svg .= '<text x="' . $cx . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    }
    $offsetY = $title ? 35 : 10;
    
    for ($p = 20; $p <= 100; $p += 20) {
        $r = $radius * ($p / 100);
        $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . $r . '" fill="none" stroke="#e0e0e0" stroke-width="1"/>';
        if ($p == 100) {
            $svg .= '<text x="' . ($cx + 5) . '" y="' . ($cy + $offsetY - $r - 3) . '" font-size="8" fill="#999">' . $p . '%</text>';
        }
    }
    
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.6) . '" fill="none" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.8) . '" fill="none" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';
    
    $points = [];
    $i = 0;
    foreach ($data as $item) {
        $angle = ($i * $angleStep) - (M_PI / 2);
        
        $axisX = $cx + $radius * cos($angle);
        $axisY = ($cy + $offsetY) + $radius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . ($cy + $offsetY) . '" x2="' . $axisX . '" y2="' . $axisY . '" stroke="#ddd" stroke-width="1"/>';
        
        $value = min(100, max(0, $item['value']));
        $pointRadius = $radius * ($value / 100);
        $pointX = $cx + $pointRadius * cos($angle);
        $pointY = ($cy + $offsetY) + $pointRadius * sin($angle);
        $points[] = $pointX . ',' . $pointY;
        
        $labelRadius = $radius + 25;
        $labelX = $cx + $labelRadius * cos($angle);
        $labelY = ($cy + $offsetY) + $labelRadius * sin($angle);
        
        $anchor = 'middle';
        if ($labelX < $cx - 20) $anchor = 'end';
        else if ($labelX > $cx + 20) $anchor = 'start';
        
        $labelColor = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));
        $displayLabel = strlen($item['label']) > 20 ? substr($item['label'], 0, 18) . '...' : $item['label'];
        
        $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="' . $anchor . '" font-size="9" fill="#333">' . htmlspecialchars($displayLabel) . '</text>';
        $svg .= '<text x="' . $labelX . '" y="' . ($labelY + 11) . '" text-anchor="' . $anchor . '" font-size="10" font-weight="bold" fill="' . $labelColor . '">' . $value . '%</text>';
        
        $i++;
    }
    
    if (!empty($points)) {
        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="' . $fillColor . '" stroke="' . $strokeColor . '" stroke-width="2"/>';
        foreach ($points as $point) {
            list($px, $py) = explode(',', $point);
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="5" fill="' . $strokeColor . '" stroke="white" stroke-width="2"/>';
        }
    }
    
    $legendY = $size + $offsetY + 10;
    $svg .= '<line x1="' . ($cx - 100) . '" y1="' . $legendY . '" x2="' . ($cx - 80) . '" y2="' . $legendY . '" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx - 75) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">60% Sufficiente</text>';
    $svg .= '<line x1="' . ($cx + 20) . '" y1="' . $legendY . '" x2="' . ($cx + 40) . '" y2="' . $legendY . '" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx + 45) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">80% Eccellente</text>';
    
    $svg .= '</svg>';
    return $svg;
}

/**
 * Genera un grafico a barre SVG (per meno di 3 elementi)
 */
function generate_svg_bar_chart($data, $title = '', $width = 300) {
    $barHeight = 30;
    $padding = 10;
    $labelWidth = 100;
    $height = count($data) * ($barHeight + $padding) + 60;
    
    $svg = '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';
    
    if ($title) {
        $svg .= '<text x="' . ($width/2) . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    }
    
    $y = 40;
    foreach ($data as $item) {
        $value = min(100, max(0, $item['value']));
        $barWidth = ($width - $labelWidth - 50) * ($value / 100);
        $color = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));
        
        $svg .= '<text x="5" y="' . ($y + 20) . '" font-size="10" fill="#333">' . htmlspecialchars(substr($item['label'], 0, 15)) . '</text>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . ($width - $labelWidth - 50) . '" height="' . $barHeight . '" fill="#f0f0f0" rx="4"/>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $color . '" rx="4"/>';
        $svg .= '<text x="' . ($width - 40) . '" y="' . ($y + 20) . '" font-size="11" font-weight="bold" fill="' . $color . '">' . $value . '%</text>';
        
        $y += $barHeight + $padding;
    }
    
    $svg .= '</svg>';
    return $svg;
}

// ========================================
// CARICAMENTO DATI
// ========================================
$availableQuizzes = \local_competencymanager\report_generator::get_available_quizzes($userid, $courseid);
$quizIdsForQuery = !empty($selectedQuizzes) ? $selectedQuizzes : null;

$radardata = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid, $quizIdsForQuery, $area ?: null);
$summary = \local_competencymanager\report_generator::get_student_summary($userid, $courseid, $quizIdsForQuery, $area ?: null);
$competencies = $radardata['competencies'];

$availableAreas = \local_competencymanager\report_generator::get_available_areas($userid, $courseid);
$quizComparison = \local_competencymanager\report_generator::get_quiz_comparison($userid, $courseid);
$progressData = \local_competencymanager\report_generator::get_progress_over_time($userid, $courseid);

// CARICAMENTO DESCRIZIONI DINAMICHE DAL DATABASE
$sector = \local_competencymanager\report_generator::detect_sector_from_competencies($competencies);
$areaDescriptions = \local_competencymanager\report_generator::get_area_descriptions_from_framework($sector, $courseid);
$competencyDescriptions = \local_competencymanager\report_generator::get_competency_descriptions_from_framework($sector);

$areasData = aggregate_by_area($competencies, $areaDescriptions, $sector);
$evaluation = get_evaluation_band($summary['overall_percentage']);
$actionPlan = generate_action_plan($competencies, $competencyDescriptions);
$certProgress = generate_certification_progress($competencies);

// ============================================
// CARICAMENTO DATI ADDITIVI (CoachManager)
// ============================================
$autovalutazioneResult = null;
$autovalutazioneData = null;
$autovalutazioneTimestamp = null;
$gapAnalysisData = null;
$colloquioHints = null;
$autovalutazioneAreas = null;

// Carica solo se almeno una opzione è attivata
if ($showDualRadar || $showGapAnalysis || $showSpuntiColloquio || 
    $printDualRadar || $printGapAnalysis || $printSpuntiColloquio) {
    
    $autovalutazioneResult = get_student_self_assessment($userid, $courseid);
    
    if ($autovalutazioneResult) {
        $autovalutazioneData = $autovalutazioneResult['data'];
        $autovalutazioneTimestamp = $autovalutazioneResult['timestamp'];
        
        // Calcola gap se richiesto - USA SOGLIE CONFIGURABILI
        if ($showGapAnalysis || $printGapAnalysis || $showSpuntiColloquio || $printSpuntiColloquio) {
            $gapAnalysisData = calculate_gap_analysis($autovalutazioneData, $competencies, $sogliaAllineamento);
        }
        
        // Genera spunti colloquio se richiesto - USA SOGLIE CONFIGURABILI
        if ($showSpuntiColloquio || $printSpuntiColloquio) {
            $colloquioHints = generate_colloquio_hints($gapAnalysisData, $competencyDescriptions, $sogliaCritico);
        }
        
        // Aggrega autovalutazione per area
        if ($showDualRadar || $printDualRadar) {
            $autovalutazioneAreas = aggregate_autovalutazione_by_area($autovalutazioneData, $areaDescriptions, $sector);
        }
    }
}

// Verifica disponibilità autovalutazione (per mostrare/nascondere opzioni)
$checkAutovalutazione = get_student_self_assessment($userid, $courseid);
$hasAutovalutazione = !empty($checkAutovalutazione);
if ($hasAutovalutazione && !$autovalutazioneTimestamp) {
    $autovalutazioneTimestamp = $checkAutovalutazione['timestamp'];
}
// ============================================

// Include file di visualizzazione stampa
if ($print) {
    include(__DIR__ . '/student_report_print.php');
    exit;
}

// ========================================
// OUTPUT NORMALE
// ========================================
echo $OUTPUT->header();
?>

<style>
.badge-orange { background-color: #fd7e14; color: white; }
.radar-interactive-container { position: relative; }
.radar-controls { margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
.radar-controls .form-check { display: inline-block; margin-right: 15px; margin-bottom: 10px; }
.radar-controls .form-check-input { margin-right: 5px; }
.area-toggle { cursor: pointer; padding: 5px 10px; border-radius: 4px; margin: 2px; display: inline-block; transition: all 0.2s; }
.area-toggle:hover { opacity: 0.8; }
.area-toggle.disabled { opacity: 0.4; text-decoration: line-through; }
.stat-box { text-align: center; padding: 20px; border-radius: 12px; }
.stat-number { font-size: 2.5rem; font-weight: 700; }

/* Stili per sezioni additive */
.gap-row-sopra { background: #fadbd8 !important; }
.gap-row-sotto { background: #fef9e7 !important; }
.gap-row-allineato { background: #d5f5e3 !important; }
</style>

<?php
// Header studente
echo '<div class="student-report-header mb-4 p-4 rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
echo '<div class="row align-items-center">';
echo '<div class="col-auto">' . $OUTPUT->user_picture($student, ['size' => 80, 'class' => 'rounded-circle border border-white']) . '</div>';
echo '<div class="col"><h2 class="mb-1 text-white">' . fullname($student) . '</h2><p class="mb-0 opacity-75">' . $student->email . '</p>';
if ($course) echo '<p class="mb-0"><small>📚 ' . format_string($course->fullname) . '</small></p>';
echo '</div><div class="col-auto text-right"><div style="font-size: 3.5rem; font-weight: bold;">' . $summary['overall_percentage'] . '%</div><p class="mb-0 opacity-75">Punteggio globale</p></div></div></div>';

// Box valutazione
echo '<div class="card mb-4" style="border-left: 5px solid ' . $evaluation['color'] . ';"><div class="card-body"><div class="row align-items-center">';
echo '<div class="col-auto"><div style="font-size: 3rem;">' . $evaluation['icon'] . '</div></div>';
echo '<div class="col"><h4 class="mb-1" style="color: ' . $evaluation['color'] . ';">VALUTAZIONE: ' . $evaluation['label'] . '</h4><p class="mb-1">' . $evaluation['description'] . '</p><p class="mb-0"><strong>💡 Azione:</strong> ' . $evaluation['action'] . '</p></div>';
echo '<div class="col-auto text-center p-3 rounded" style="background: ' . $evaluation['bgColor'] . ';"><div style="font-size: 2rem; font-weight: bold; color: ' . $evaluation['color'] . ';">' . ($summary['correct_total'] ?? $summary['correct_questions']) . '/' . ($summary['questions_total'] ?? $summary['total_questions']) . '</div><small>risposte corrette</small></div></div></div></div>';

// Pannello quiz
if (!empty($availableQuizzes)) {
    echo '<div class="card mb-4"><div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;"><h5 class="mb-0">📝 Seleziona Quiz</h5></div><div class="card-body">';
    echo '<form method="get" id="quizFilterForm"><input type="hidden" name="userid" value="' . $userid . '"><input type="hidden" name="courseid" value="' . $courseid . '"><input type="hidden" name="tab" value="' . $tab . '">';
    if ($selectedArea) echo '<input type="hidden" name="selectedarea" value="' . $selectedArea . '">';
    // Mantieni le opzioni additive attive
    if ($showDualRadar) echo '<input type="hidden" name="show_dual_radar" value="1">';
    if ($showGapAnalysis) echo '<input type="hidden" name="show_gap" value="1">';
    if ($showSpuntiColloquio) echo '<input type="hidden" name="show_spunti" value="1">';
    echo '<div class="row">';
    foreach ($availableQuizzes as $quiz) {
        $isSelected = empty($selectedQuizzes) || in_array($quiz->id, $selectedQuizzes);
        echo '<div class="col-md-4 mb-2"><div class="p-2 border rounded" style="' . ($isSelected ? 'background:#e8f5e9;' : '') . '"><div class="form-check">';
        echo '<input type="checkbox" class="form-check-input quiz-checkbox" name="quizids[]" value="' . $quiz->id . '" id="quiz_' . $quiz->id . '" ' . ($isSelected ? 'checked' : '') . '>';
        echo '<label class="form-check-label" for="quiz_' . $quiz->id . '"><strong>' . format_string($quiz->name) . '</strong><br><small class="text-muted">' . $quiz->attempts . ' tentativo/i</small></label>';
        echo '</div></div></div>';
    }
    echo '</div><div class="mt-3 d-flex justify-content-between"><div><button type="button" class="btn btn-outline-primary btn-sm mr-2" onclick="document.querySelectorAll(\'.quiz-checkbox\').forEach(c=>c.checked=true)">✅ Tutti</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'.quiz-checkbox\').forEach(c=>c.checked=false)">☐ Nessuno</button></div><button type="submit" class="btn btn-success">🔄 Aggiorna</button></div></form></div></div>';
}

// ============================================
// PANNELLO OPZIONI ADDITIVE (CoachManager)
// ============================================
?>
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
        <h5 class="mb-0">🔧 Opzioni Visualizzazione Aggiuntive</h5>
    </div>
    <div class="card-body">
        <form method="get" id="additiveOptionsForm">
            <input type="hidden" name="userid" value="<?php echo $userid; ?>">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <?php foreach ($selectedQuizzes as $qid): ?>
            <input type="hidden" name="quizids[]" value="<?php echo $qid; ?>">
            <?php endforeach; ?>
            
            <div class="row">
                <div class="col-md-7">
                    <h6>📊 Sezioni Aggiuntive (CoachManager):</h6>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_dual_radar" 
                               name="show_dual_radar" value="1" <?php echo $showDualRadar ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_dual_radar">
                            🎯 <strong>Doppio Radar</strong> (Autovalutazione affiancato)
                        </label>
                    </div>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_gap" 
                               name="show_gap" value="1" <?php echo $showGapAnalysis ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_gap">
                            📈 <strong>Gap Analysis</strong> (Confronto Auto vs Reale)
                        </label>
                    </div>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_spunti" 
                               name="show_spunti" value="1" <?php echo $showSpuntiColloquio ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_spunti">
                            💬 <strong>Spunti Colloquio</strong> (Domande suggerite)
                        </label>
                    </div>
                    
                    <!-- SOGLIE CONFIGURABILI -->
                    <hr class="my-3">
                    <h6>⚙️ Configurazione Soglie:</h6>
                    <div class="row">
                        <div class="col-6">
                            <label for="soglia_allineamento" class="small">
                                <strong>Soglia Allineamento</strong><br>
                                <span class="text-muted">(Gap ≤ questa % = allineato)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="soglia_allineamento" 
                                       name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>"
                                       min="5" max="40" step="5" <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="soglia_critico" class="small">
                                <strong>Soglia Gap Critico</strong><br>
                                <span class="text-muted">(Gap > questa % = critico)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="soglia_critico" 
                                       name="soglia_critico" value="<?php echo $sogliaCritico; ?>"
                                       min="20" max="60" step="5" <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        📊 <strong>Interpretazione:</strong> 
                        Gap ≤ <?php echo $sogliaAllineamento; ?>% = ✅ Allineato | 
                        Gap <?php echo $sogliaAllineamento; ?>-<?php echo $sogliaCritico; ?>% = ⚠️ Attenzione | 
                        Gap > <?php echo $sogliaCritico; ?>% = 🔴 Critico
                    </small>
                </div>
                
                <div class="col-md-5">
                    <?php if (!$hasAutovalutazione): ?>
                    <div class="alert alert-info mb-0">
                        <h6 class="mb-1">ℹ️ Autovalutazione non disponibile</h6>
                        <p class="mb-0 small">Lo studente non ha ancora completato un'autovalutazione nel CoachManager.</p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <h6 class="mb-1">✅ Autovalutazione disponibile</h6>
                        <p class="mb-0 small">
                            <strong>Data:</strong> <?php echo userdate($autovalutazioneTimestamp); ?><br>
                            <strong>Competenze:</strong> <?php echo count($checkAutovalutazione['data']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-3 text-right">
                <button type="submit" class="btn btn-success">🔄 Applica Opzioni</button>
            </div>
        </form>
    </div>
</div>
<?php
// ============================================

// Progresso certificazione
echo '<div class="card mb-4"><div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;"><h5 class="mb-0">📊 Progresso Certificazione</h5></div><div class="card-body">';
echo '<div class="progress mb-3" style="height: 30px;"><div class="progress-bar bg-success" style="width: ' . $certProgress['percentage'] . '%;"><strong>' . $certProgress['percentage'] . '% completato</strong></div></div>';
echo '<div class="row text-center"><div class="col-md-4"><div class="p-3 rounded" style="background: #d4edda;"><h3 class="text-success mb-0">' . $certProgress['certified'] . '</h3><small>✅ Certificate (≥80%)</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #fff3cd;"><h3 class="text-warning mb-0">' . $certProgress['inProgress'] . '</h3><small>🔄 In corso</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #f8d7da;"><h3 class="text-danger mb-0">' . $certProgress['notStarted'] . '</h3><small>⏳ Da iniziare</small></div></div></div></div></div>';

// Barra azioni
echo '<div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap">';
echo '<a href="' . new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]) . '" class="btn btn-secondary mb-2">← Torna alla lista</a>';
echo '<div><button type="button" class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#printModal">🖨️ Stampa Personalizzata</button>';
echo '<a href="' . new moodle_url('/local/competencymanager/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-success mr-2 mb-2">📥 CSV</a>';
echo '<a href="' . new moodle_url('/local/competencymanager/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'excel']) . '" class="btn btn-success mb-2">📥 Excel</a></div></div></div>';

// ========================================
// MODALE STAMPA PERSONALIZZATA
// ========================================
?>
<div class="modal fade" id="printModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">🖨️ Stampa Personalizzata</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="printForm" method="get" target="_blank" action="<?php echo new moodle_url('/local/competencymanager/student_report.php'); ?>">
                    <input type="hidden" name="userid" value="<?php echo $userid; ?>">
                    <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                    <input type="hidden" name="print" value="1">
                    <?php foreach ($selectedQuizzes as $qid): ?>
                    <input type="hidden" name="quizids[]" value="<?php echo $qid; ?>">
                    <?php endforeach; ?>
                    
                    <!-- SOGLIE CONFIGURABILI - passate alla stampa -->
                    <input type="hidden" name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>">
                    <input type="hidden" name="soglia_critico" value="<?php echo $sogliaCritico; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>📋 Sezioni da includere:</h6>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_panoramica" name="print_panoramica" value="1" checked>
                                <label class="custom-control-label" for="print_panoramica"><strong>📊 Panoramica e Valutazione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_progressi" name="print_progressi" value="1" checked>
                                <label class="custom-control-label" for="print_progressi"><strong>📈 Progresso Certificazione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_piano" name="print_piano" value="1" checked>
                                <label class="custom-control-label" for="print_piano"><strong>📚 Piano d'Azione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_dettagli" name="print_dettagli" value="1" checked>
                                <label class="custom-control-label" for="print_dettagli"><strong>📋 Tabella Dettagli</strong></label>
                            </div>
                            
                            <!-- NUOVE OPZIONI STAMPA ADDITIVE -->
                            <?php if ($hasAutovalutazione): ?>
                            <hr>
                            <h6 class="text-success">🆕 Sezioni CoachManager:</h6>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_dual_radar" name="print_dual_radar" value="1">
                                <label class="custom-control-label" for="print_dual_radar"><strong>🎯 Doppio Radar</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_gap" name="print_gap" value="1">
                                <label class="custom-control-label" for="print_gap"><strong>📈 Gap Analysis</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_spunti" name="print_spunti" value="1">
                                <label class="custom-control-label" for="print_spunti"><strong>💬 Spunti Colloquio</strong></label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>📊 Grafici Radar:</h6>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="print_radar_aree" name="print_radar_aree" value="1" checked>
                                <label class="custom-control-label" for="print_radar_aree"><strong>🎯 Radar Panoramica Aree</strong></label>
                            </div>
                            
                            <h6>🔍 Radar Dettaglio per Area:</h6>
                            <p class="small text-muted">Seleziona le aree per cui generare un radar dettagliato delle competenze:</p>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($areasData as $areaCode => $areaInfo): ?>
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="checkbox" class="custom-control-input area-detail-check" id="print_area_<?php echo $areaCode; ?>" name="print_radar_areas[]" value="<?php echo $areaCode; ?>">
                                    <label class="custom-control-label" for="print_area_<?php echo $areaCode; ?>">
                                        <?php echo $areaInfo['icon'] . ' ' . $areaInfo['name']; ?>
                                        <span class="badge badge-<?php echo get_evaluation_band($areaInfo['percentage'])['class']; ?>"><?php echo $areaInfo['percentage']; ?>%</span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.area-detail-check').forEach(c=>c.checked=true)">Tutte</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.area-detail-check').forEach(c=>c.checked=false)">Nessuna</button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.querySelectorAll('.section-check').forEach(c=>c.checked=true)">✅ Tutte le sezioni</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.section-check').forEach(c=>c.checked=false)">☐ Nessuna sezione</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('printForm').submit()">🖨️ Genera Stampa</button>
            </div>
        </div>
    </div>
</div>

<?php
// Tabs
$tabs = ['overview' => '📊 Panoramica', 'action' => '📚 Piano', 'quiz' => '📝 Quiz', 'progress' => '📈 Progressi', 'details' => '📋 Dettagli'];
echo '<ul class="nav nav-tabs mb-4">';
foreach ($tabs as $tabKey => $tabLabel) {
    $activeClass = ($tab === $tabKey) ? 'active' : '';
    $tabUrl = new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid, 'tab' => $tabKey]);
    if (!empty($selectedQuizzes)) {
        foreach ($selectedQuizzes as $qid) {
            $tabUrl->param('quizids[]', $qid);
        }
    }
    // Mantieni opzioni additive
    if ($showDualRadar) $tabUrl->param('show_dual_radar', 1);
    if ($showGapAnalysis) $tabUrl->param('show_gap', 1);
    if ($showSpuntiColloquio) $tabUrl->param('show_spunti', 1);
    echo '<li class="nav-item"><a class="nav-link ' . $activeClass . '" href="' . $tabUrl . '">' . $tabLabel . '</a></li>';
}
echo '</ul>';

// Contenuto tabs
echo '<div class="tab-content">';

// ========================================
// TAB PANORAMICA
// ========================================
if ($tab === 'overview') {
    ?>
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📊 Radar Aree di Competenza</h5>
                    <small>Clicca sulle aree per nasconderle/mostrarle</small>
                </div>
                <div class="card-body">
                    <!-- Controlli interattivi -->
                    <div class="radar-controls">
                        <strong>Aree visualizzate:</strong>
                        <div class="mt-2" id="areaToggles">
                            <?php foreach ($areasData as $code => $areaData): ?>
                            <span class="area-toggle" data-area="<?php echo $code; ?>" style="background: <?php echo $areaData['color']; ?>20; border: 2px solid <?php echo $areaData['color']; ?>; color: <?php echo $areaData['color']; ?>;">
                                <?php echo $areaData['icon'] . ' ' . $areaData['name']; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAllAreas(true)">Mostra tutte</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllAreas(false)">Nascondi tutte</button>
                        </div>
                    </div>
                    
                    <!-- Canvas per Chart.js -->
                    <canvas id="radarAreas" style="max-height: 400px;"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">📈 Riepilogo Aree</h5>
                </div>
                <div class="card-body">
                    <p><strong>Aree totali:</strong> <?php echo count($areasData); ?></p>
                    <p><strong>Competenze valutate:</strong> <?php echo count($competencies); ?></p>
                    <p><strong>Domande totali:</strong> <?php echo $summary['questions_total'] ?? $summary['total_questions']; ?></p>
                    
                    <hr>
                    
                    <h6>🏆 Aree Forti (≥60%)</h6>
                    <ul class="list-unstyled">
                    <?php foreach ($areasData as $code => $areaData): ?>
                        <?php if ($areaData['percentage'] >= 60): ?>
                        <li><span style="color: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?></span> <?php echo $areaData['name']; ?> - <strong class="text-success"><?php echo $areaData['percentage']; ?>%</strong></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                    
                    <h6 class="mt-3">⚠️ Da Migliorare (<50%)</h6>
                    <ul class="list-unstyled">
                    <?php foreach ($areasData as $code => $areaData): ?>
                        <?php if ($areaData['percentage'] < 50): ?>
                        <li><span style="color: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?></span> <?php echo $areaData['name']; ?> - <strong class="text-danger"><?php echo $areaData['percentage']; ?>%</strong></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Mini radar per area selezionata -->
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">🔍 Dettaglio Area</h5>
                </div>
                <div class="card-body">
                    <select id="areaDetailSelect" class="form-control mb-3">
                        <option value="">-- Seleziona un'area --</option>
                        <?php foreach ($areasData as $code => $areaData): ?>
                        <option value="<?php echo $code; ?>"><?php echo $areaData['icon'] . ' ' . $areaData['name'] . ' (' . $areaData['percentage'] . '%)'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="areaDetailContent">
                        <p class="text-muted text-center">Seleziona un'area per vedere il dettaglio delle competenze</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // ============================================
    // SEZIONI ADDITIVE (dopo il radar principale)
    // ============================================
    
    // DOPPIO RADAR AFFIANCATO
    if ($showDualRadar && !empty($autovalutazioneAreas)):
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h5 class="mb-0">🎯 Confronto Radar: Autovalutazione vs Performance</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="text-center mb-3">
                        <h6 style="color: #667eea;">🧑 Come lo studente si percepisce</h6>
                    </div>
                    <canvas id="radarAutovalutazione" style="max-height: 350px;"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="text-center mb-3">
                        <h6 style="color: #28a745;">📊 Risultati reali dai quiz</h6>
                    </div>
                    <canvas id="radarPerformanceDual" style="max-height: 350px;"></canvas>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 text-center">
                    <span class="badge mr-3" style="background: #667eea; color: white; padding: 8px 15px;">🧑 Autovalutazione</span>
                    <span class="badge" style="background: #28a745; color: white; padding: 8px 15px;">📊 Performance Reale</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    // GAP ANALYSIS
    if ($showGapAnalysis && !empty($gapAnalysisData)):
        $countSopra = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sopravvalutazione'));
        $countSotto = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sottovalutazione'));
        $countAllineato = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'allineato'));
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h5 class="mb-0">📊 Gap Analysis: Autovalutazione vs Performance Reale</h5>
        </div>
        <div class="card-body">
            <!-- INDICAZIONE SOGLIE CONFIGURATE -->
            <div class="alert alert-secondary mb-3 py-2">
                <small>
                    <strong>⚙️ Soglie applicate:</strong> 
                    Gap ≤ <strong><?php echo $sogliaAllineamento; ?>%</strong> = ✅ Allineato | 
                    Gap > <strong><?php echo $sogliaAllineamento; ?>%</strong> = ⚠️/⬆️⬇️ Attenzione | 
                    Gap > <strong><?php echo $sogliaCritico; ?>%</strong> = 🔴 Critico
                </small>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #fadbd8;">
                        <h3 class="text-danger mb-0"><?php echo $countSopra; ?></h3>
                        <small>⬆️ Sopravvalutazione</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #d5f5e3;">
                        <h3 class="text-success mb-0"><?php echo $countAllineato; ?></h3>
                        <small>✅ Allineato</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #fef9e7;">
                        <h3 class="text-warning mb-0"><?php echo $countSotto; ?></h3>
                        <small>⬇️ Sottovalutazione</small>
                    </div>
                </div>
            </div>
            
            <table class="table table-bordered">
                <thead>
                    <tr style="background: #34495e; color: white;">
                        <th>Competenza</th>
                        <th class="text-center" style="width: 100px;">🧑 Auto</th>
                        <th class="text-center" style="width: 100px;">📊 Reale</th>
                        <th class="text-center" style="width: 100px;">Gap</th>
                        <th class="text-center" style="width: 150px;">Analisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gapAnalysisData as $gap): ?>
                    <tr style="background: <?php echo $gap['bg']; ?>;">
                        <td>
                            <strong><?php echo htmlspecialchars($gap['name']); ?></strong><br>
                            <small class="text-muted"><?php echo $gap['idnumber']; ?></small>
                        </td>
                        <td class="text-center"><span class="badge badge-primary"><?php echo round($gap['autovalutazione']); ?>%</span></td>
                        <td class="text-center"><span class="badge badge-success"><?php echo round($gap['performance']); ?>%</span></td>
                        <td class="text-center" style="color: <?php echo $gap['colore']; ?>; font-weight: bold;">
                            <?php echo $gap['icona'] . ' ' . ($gap['differenza'] > 0 ? '+' : '') . round($gap['differenza']) . '%'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($gap['tipo'] === 'sopravvalutazione'): ?>
                            <span class="badge badge-danger">⬆️ Sopravvalutazione</span>
                            <?php elseif ($gap['tipo'] === 'sottovalutazione'): ?>
                            <span class="badge badge-warning">⬇️ Sottovalutazione</span>
                            <?php else: ?>
                            <span class="badge badge-success">✅ Allineato</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    // SPUNTI COLLOQUIO
    if ($showSpuntiColloquio && !empty($colloquioHints)):
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333;">
            <h5 class="mb-0">💬 Spunti per il Colloquio</h5>
        </div>
        <div class="card-body">
            <!-- INDICAZIONE SOGLIE CONFIGURATE -->
            <div class="alert alert-secondary mb-3 py-2">
                <small>
                    <strong>⚙️ Classificazione:</strong>
                    🔴 Gap Critico = > <strong><?php echo $sogliaCritico; ?>%</strong> | 
                    ⚠️ Gap Moderato = <strong><?php echo $sogliaAllineamento; ?>% - <?php echo $sogliaCritico; ?>%</strong> | 
                    ✅ Allineato = ≤ <strong><?php echo $sogliaAllineamento; ?>%</strong>
                </small>
            </div>
            
            <?php if (!empty($colloquioHints['critici'])): ?>
            <div class="mb-4">
                <h6 class="text-danger">🔴 Priorità Alta - Gap Critici</h6>
                <?php foreach ($colloquioHints['critici'] as $hint): ?>
                <div class="card mb-2 border-danger">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-danger ml-2">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                        <p class="mb-1 small"><?php echo $hint['messaggio']; ?></p>
                        <p class="mb-0 small text-primary"><em>💡 "<?php echo $hint['domanda']; ?>"</em></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colloquioHints['attenzione'])): ?>
            <div class="mb-4">
                <h6 class="text-warning">⚠️ Attenzione - Gap Moderati</h6>
                <?php foreach ($colloquioHints['attenzione'] as $hint): ?>
                <div class="card mb-2 border-warning">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-warning ml-2">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                        <p class="mb-1 small"><?php echo $hint['messaggio']; ?></p>
                        <p class="mb-0 small text-primary"><em>💡 "<?php echo $hint['domanda']; ?>"</em></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colloquioHints['positivi'])): ?>
            <div class="mb-4">
                <h6 class="text-success">✅ Punti di Forza</h6>
                <?php foreach (array_slice($colloquioHints['positivi'], 0, 5) as $hint): ?>
                <div class="card mb-2 border-success">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-success ml-2"><?php echo $hint['gap']['icona']; ?></span>
                        <p class="mb-0 small"><?php echo $hint['messaggio']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    // ============================================
}

// ========================================
// TAB PIANO D'AZIONE
// ========================================
else if ($tab === 'action') {
    if (!empty($actionPlan['critical'])) {
        echo '<div class="card mb-3 border-danger"><div class="card-header bg-danger text-white"><h5 class="mb-0">🔴 CRITICO - Formazione urgente richiesta</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['critical'] as $item) echo '<tr class="table-danger"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-danger">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['toImprove'])) {
        echo '<div class="card mb-3 border-warning"><div class="card-header bg-warning"><h5 class="mb-0">🟠 DA MIGLIORARE - Richiede attenzione</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['toImprove'] as $item) echo '<tr class="table-warning"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-warning">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['good'])) {
        echo '<div class="card mb-3 border-info"><div class="card-header bg-info text-white"><h5 class="mb-0">✅ ACQUISITE - Consolidare con pratica</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['good'] as $item) echo '<tr class="table-info"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-info">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['excellence'])) {
        echo '<div class="card mb-3 border-success"><div class="card-header bg-success text-white"><h5 class="mb-0">🌟 ECCELLENZA - Padronanza completa</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['excellence'] as $item) echo '<tr class="table-success"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-success">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
}

// ========================================
// TAB QUIZ
// ========================================
else if ($tab === 'quiz') {
    echo '<div class="card"><div class="card-header bg-secondary text-white"><h5 class="mb-0">📝 Confronto per Quiz</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Quiz</th><th>Tentativo</th><th>Data</th><th>Punteggio</th><th>Competenze</th></tr></thead><tbody>';
    if (!empty($quizComparison)) {
        foreach ($quizComparison as $quiz) {
            foreach ($quiz['attempts'] as $attempt) {
                $badgeClass = $attempt['percentage'] >= 60 ? 'success' : ($attempt['percentage'] >= 40 ? 'warning' : 'danger');
                echo '<tr><td>' . format_string($quiz['name']) . '</td><td>#' . $attempt['attempt_number'] . '</td><td>' . date('d/m/Y H:i', $attempt['timefinish']) . '</td><td><span class="badge badge-' . $badgeClass . '">' . $attempt['percentage'] . '%</span></td><td>' . $attempt['competencies'] . '</td></tr>';
            }
        }
    } else {
        echo '<tr><td colspan="5" class="text-center text-muted">Nessun tentativo quiz completato</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

// ========================================
// TAB PROGRESSI
// ========================================
else if ($tab === 'progress') {
    echo '<div class="card"><div class="card-header bg-primary text-white"><h5 class="mb-0">📈 Progressi nel Tempo</h5></div><div class="card-body">';
    if (!empty($progressData)) {
        echo '<canvas id="progressChart" style="max-height: 300px;"></canvas>';
    } else {
        echo '<p class="text-muted text-center">Non ci sono ancora abbastanza dati per mostrare i progressi. Completa più quiz per vedere l\'andamento.</p>';
    }
    echo '</div></div>';
}

// ========================================
// TAB DETTAGLI
// ========================================
else if ($tab === 'details') {
    $filteredCompetencies = $competencies;
    
    foreach ($filteredCompetencies as &$comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $areaInfo = get_area_info($code);
        $comp['area_code'] = $areaInfo['code'];
        $comp['area_name'] = $areaInfo['name'];
        
        $pct = $comp['percentage'];
        if ($pct >= 80) $comp['level'] = 'excellent';
        else if ($pct >= 60) $comp['level'] = 'good';
        else if ($pct >= 50) $comp['level'] = 'sufficient';
        else if ($pct >= 30) $comp['level'] = 'insufficient';
        else $comp['level'] = 'critical';
    }
    unset($comp);
    
    if ($filterLevel !== 'all') {
        $filteredCompetencies = array_filter($filteredCompetencies, fn($c) => $c['level'] === $filterLevel);
    }
    
    if (!empty($filterArea)) {
        $filteredCompetencies = array_filter($filteredCompetencies, fn($c) => $c['area_code'] === $filterArea);
    }
    
    switch ($sortBy) {
        case 'perc_desc': usort($filteredCompetencies, fn($a, $b) => $b['percentage'] <=> $a['percentage']); break;
        case 'perc_asc': usort($filteredCompetencies, fn($a, $b) => $a['percentage'] <=> $b['percentage']); break;
        case 'questions': usort($filteredCompetencies, fn($a, $b) => $b['total_questions'] <=> $a['total_questions']); break;
        default: usort($filteredCompetencies, fn($a, $b) => strcmp($a['area_code'], $b['area_code'])); break;
    }
    
    $levelCounts = ['excellent' => 0, 'good' => 0, 'sufficient' => 0, 'insufficient' => 0, 'critical' => 0];
    foreach ($competencies as $c) {
        $pct = $c['percentage'];
        if ($pct >= 80) $levelCounts['excellent']++;
        else if ($pct >= 60) $levelCounts['good']++;
        else if ($pct >= 50) $levelCounts['sufficient']++;
        else if ($pct >= 30) $levelCounts['insufficient']++;
        else $levelCounts['critical']++;
    }
    
    $uniqueAreas = [];
    foreach ($competencies as $c) {
        $code = $c['idnumber'] ?: $c['name'];
        $areaInfo = get_area_info($code);
        $aCode = $areaInfo['code'];
        if (!isset($uniqueAreas[$aCode])) {
            $uniqueAreas[$aCode] = ['name' => $areaInfo['name'], 'icon' => '📁'];
        }
    }
    ksort($uniqueAreas);
    
    $baseUrl = new moodle_url('/local/competencymanager/student_report.php', [
        'userid' => $userid, 'courseid' => $courseid, 'tab' => 'details'
    ]);
    ?>
    <div class="card">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0">📋 Dettaglio Competenze</h5>
                <span class="badge badge-light"><?php echo count($filteredCompetencies); ?> / <?php echo count($competencies); ?> competenze</span>
            </div>
        </div>
        
        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label><strong>Ordina per:</strong></label>
                    <select id="sortSelect" class="form-control form-control-sm">
                        <option value="area" <?php echo $sortBy=='area'?'selected':''; ?>>📁 Per Area</option>
                        <option value="perc_desc" <?php echo $sortBy=='perc_desc'?'selected':''; ?>>📈 % Decrescente</option>
                        <option value="perc_asc" <?php echo $sortBy=='perc_asc'?'selected':''; ?>>📉 % Crescente</option>
                        <option value="questions" <?php echo $sortBy=='questions'?'selected':''; ?>>❓ Per Domande</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label><strong>Filtra livello:</strong></label>
                    <select id="filterSelect" class="form-control form-control-sm">
                        <option value="all">Tutti (<?php echo count($competencies); ?>)</option>
                        <option value="excellent" <?php echo $filterLevel=='excellent'?'selected':''; ?>>🌟 Eccellente (<?php echo $levelCounts['excellent']; ?>)</option>
                        <option value="good" <?php echo $filterLevel=='good'?'selected':''; ?>>✅ Buono (<?php echo $levelCounts['good']; ?>)</option>
                        <option value="sufficient" <?php echo $filterLevel=='sufficient'?'selected':''; ?>>⚠️ Sufficiente (<?php echo $levelCounts['sufficient']; ?>)</option>
                        <option value="insufficient" <?php echo $filterLevel=='insufficient'?'selected':''; ?>>⚡ Insufficiente (<?php echo $levelCounts['insufficient']; ?>)</option>
                        <option value="critical" <?php echo $filterLevel=='critical'?'selected':''; ?>>🔴 Critico (<?php echo $levelCounts['critical']; ?>)</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label><strong>Filtra area:</strong></label>
                    <select id="filterAreaSelect" class="form-control form-control-sm">
                        <option value="">Tutte le aree</option>
                        <?php foreach ($uniqueAreas as $aCode => $aInfo): ?>
                        <option value="<?php echo $aCode; ?>" <?php echo $filterArea==$aCode?'selected':''; ?>><?php echo $aInfo['icon'] . ' ' . $aInfo['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="applyFilters()">🔄 Applica Filtri</button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Area</th>
                        <th>Codice</th>
                        <th>Competenza</th>
                        <th class="text-center">Risposte</th>
                        <th class="text-center">%</th>
                        <th>Valutazione</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($filteredCompetencies as $comp): 
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $aCode = $comp['area_code'];
                    $areaInfo = $areaDescriptions[$aCode] ?? ['icon' => '📁', 'color' => '#95a5a6'];
                    $compInfo = $competencyDescriptions[$code] ?? null;
                    $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : (!empty($comp['description']) ? $comp['description'] : $code);
                    $band = get_evaluation_band($comp['percentage']);
                ?>
                <tr style="border-left: 4px solid <?php echo $band['color']; ?>;">
                    <td class="text-center"><strong><?php echo $i; ?></strong></td>
                    <td><span class="badge" style="background: <?php echo $areaInfo['color'] ?? '#95a5a6'; ?>; color: white;"><?php echo ($areaInfo['icon'] ?? '📁') . ' ' . $aCode; ?></span></td>
                    <td><small><?php echo $code; ?></small></td>
                    <td><?php echo htmlspecialchars($displayName); ?></td>
                    <td class="text-center"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td>
                    <td class="text-center"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td>
                    <td style="color: <?php echo $band['color']; ?>; font-weight: bold;"><?php echo $band['icon']; ?> <?php echo $band['label']; ?></td>
                </tr>
                <?php $i++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function applyFilters() {
        const sort = document.getElementById('sortSelect').value;
        const filter = document.getElementById('filterSelect').value;
        const filterArea = document.getElementById('filterAreaSelect').value;
        
        let url = '<?php echo $baseUrl->out(false); ?>';
        url += '&sort=' + sort;
        url += '&filter=' + filter;
        if (filterArea) url += '&filter_area=' + filterArea;
        
        window.location.href = url;
    }
    </script>
    <?php
}

echo '</div>'; // Fine tab-content

// ========================================
// JAVASCRIPT
// ========================================
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dati per i grafici
const areasData = <?php echo json_encode(array_values($areasData)); ?>;
const competenciesData = <?php echo json_encode(array_values($competencies)); ?>;
const competencyDescriptions = <?php echo json_encode($competencyDescriptions); ?>;
const areaDescriptions = <?php echo json_encode($areaDescriptions); ?>;

// Stato delle aree visibili
let visibleAreas = {};
areasData.forEach(a => visibleAreas[a.code] = true);

// Chart radar principale
let radarChart = null;

<?php if ($tab === 'overview' && !empty($areasData)): ?>
// Inizializza radar
function initRadarChart() {
    const ctx = document.getElementById('radarAreas');
    if (!ctx) return;
    
    const visibleData = areasData.filter(a => visibleAreas[a.code]);
    
    radarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: visibleData.map(a => a.icon + ' ' + a.name),
            datasets: [{
                label: 'Percentuale',
                data: visibleData.map(a => a.percentage),
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                pointBackgroundColor: visibleData.map(a => a.color),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        callback: v => v + '%'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const area = visibleData[context.dataIndex];
                            return area.name + ': ' + area.percentage + '% (' + area.correct_questions + '/' + area.total_questions + ')';
                        }
                    }
                }
            }
        }
    });
}

// Toggle area
function toggleArea(areaCode) {
    visibleAreas[areaCode] = !visibleAreas[areaCode];
    
    const toggle = document.querySelector(`.area-toggle[data-area="${areaCode}"]`);
    if (toggle) {
        toggle.classList.toggle('disabled', !visibleAreas[areaCode]);
    }
    
    updateRadarChart();
}

// Toggle tutte le aree
function toggleAllAreas(show) {
    Object.keys(visibleAreas).forEach(code => {
        visibleAreas[code] = show;
        const toggle = document.querySelector(`.area-toggle[data-area="${code}"]`);
        if (toggle) {
            toggle.classList.toggle('disabled', !show);
        }
    });
    updateRadarChart();
}

// Aggiorna radar
function updateRadarChart() {
    if (!radarChart) return;
    
    const visibleData = areasData.filter(a => visibleAreas[a.code]);
    
    radarChart.data.labels = visibleData.map(a => a.icon + ' ' + a.name);
    radarChart.data.datasets[0].data = visibleData.map(a => a.percentage);
    radarChart.data.datasets[0].pointBackgroundColor = visibleData.map(a => a.color);
    radarChart.update();
}

// Event listeners per toggle
document.querySelectorAll('.area-toggle').forEach(el => {
    el.addEventListener('click', function() {
        toggleArea(this.dataset.area);
    });
});

// Dettaglio area
document.getElementById('areaDetailSelect')?.addEventListener('change', function() {
    const areaCode = this.value;
    const container = document.getElementById('areaDetailContent');
    
    if (!areaCode) {
        container.innerHTML = '<p class="text-muted text-center">Seleziona un\'area per vedere il dettaglio</p>';
        return;
    }
    
    const areaInfo = areaDescriptions[areaCode] || { name: areaCode, icon: '📁' };
    const areaComps = competenciesData.filter(c => {
        const code = c.idnumber || c.name;
        const parts = code.split('_');
        return parts[1] === areaCode || (parts.length >= 3 && parts[2].charAt(0).toUpperCase() === areaCode);
    });
    
    if (areaComps.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">Nessuna competenza per questa area</p>';
        return;
    }
    
    let html = '<table class="table table-sm">';
    html += '<thead><tr><th>Competenza</th><th>%</th></tr></thead><tbody>';
    
    areaComps.forEach(c => {
        const code = c.idnumber || c.name;
        const desc = competencyDescriptions[code];
        const name = desc ? (desc.name || code) : code;
        const color = c.percentage >= 80 ? '#28a745' : (c.percentage >= 60 ? '#20c997' : (c.percentage >= 40 ? '#ffc107' : '#dc3545'));
        html += `<tr><td><small>${name}</small></td><td><span style="color:${color};font-weight:bold;">${c.percentage}%</span></td></tr>`;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
});

// Inizializza
initRadarChart();

// ============================================
// RADAR ADDITIVI (CoachManager)
// ============================================
<?php if ($showDualRadar && !empty($autovalutazioneAreas)): ?>
const autovalutazioneAreas = <?php echo json_encode(array_values($autovalutazioneAreas)); ?>;

// Radar Autovalutazione
const ctxAuto = document.getElementById('radarAutovalutazione');
if (ctxAuto && autovalutazioneAreas.length > 0) {
    new Chart(ctxAuto, {
        type: 'radar',
        data: {
            labels: autovalutazioneAreas.map(a => a.icon + ' ' + a.name),
            datasets: [{
                label: 'Autovalutazione',
                data: autovalutazioneAreas.map(a => a.percentage),
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                pointBackgroundColor: '#667eea',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } },
            plugins: { legend: { display: false } }
        }
    });
}

// Radar Performance (duplicato per confronto)
const ctxPerfDual = document.getElementById('radarPerformanceDual');
if (ctxPerfDual) {
    new Chart(ctxPerfDual, {
        type: 'radar',
        data: {
            labels: areasData.map(a => a.icon + ' ' + a.name),
            datasets: [{
                label: 'Performance',
                data: areasData.map(a => a.percentage),
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2,
                pointBackgroundColor: '#28a745',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } },
            plugins: { legend: { display: false } }
        }
    });
}
<?php endif; ?>
// ============================================

<?php endif; ?>

<?php if ($tab === 'progress' && !empty($progressData)): ?>
// Grafico progressi
const ctxProgress = document.getElementById('progressChart');
if (ctxProgress) {
    new Chart(ctxProgress, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($p) { return "'" . ($p['date'] ?? date('d/m', $p['datetime'] ?? time())) . "'"; }, $progressData)); ?>],
            datasets: [{
                label: 'Punteggio %',
                data: [<?php echo implode(',', array_map(function($p) { return $p['percentage'] ?? 0; }, $progressData)); ?>],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + '%' }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => 'Punteggio: ' + ctx.parsed.y + '%'
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php
echo $OUTPUT->footer();
