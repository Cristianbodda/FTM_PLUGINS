<?php
/**
 * Report singolo studente - VERSIONE 7 (DINAMICA)
 * Con selezione MULTIPLA dei quiz, doppio grafico radar
 * STAMPA PERSONALIZZATA con selezione sezioni
 * DESCRIZIONI DINAMICHE dal database Moodle
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$area = optional_param('area', '', PARAM_TEXT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$print = optional_param('print', 0, PARAM_INT);
$selectedArea = optional_param('selectedarea', '', PARAM_TEXT);

// Parametri per filtro e ordinamento nella tab Dettagli
$sortBy = optional_param('sort', 'area', PARAM_ALPHA); // area, perc_asc, perc_desc, questions
$filterLevel = optional_param('filter', 'all', PARAM_ALPHANUMEXT); // all, excellent, good, sufficient, insufficient, critical
$filterArea = optional_param('filter_area', '', PARAM_TEXT); // filtra per area specifica

$printPanoramica = optional_param('print_panoramica', 1, PARAM_INT);
$printPiano = optional_param('print_piano', 1, PARAM_INT);
$printProgressi = optional_param('print_progressi', 1, PARAM_INT);
$printDettagli = optional_param('print_dettagli', 1, PARAM_INT);
$printRadarAree = optional_param('print_radar_aree', 1, PARAM_INT);
$printRadarAreas = optional_param_array('print_radar_areas', [], PARAM_TEXT);

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
        if (!\local_competencyreport\report_generator::student_can_view_own_report($userid, $courseid)) {
            throw new moodle_exception('nopermissions', 'error', '', 'view this report');
        }
    } else {
        throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyreport/student.php', ['userid' => $userid, 'courseid' => $courseid, 'tab' => $tab]));
$PAGE->set_title(get_string('studentreport', 'local_competencyreport') . ': ' . fullname($student));
$PAGE->set_heading(get_string('studentreport', 'local_competencyreport'));
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
    $areas = [];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $parts = explode('_', $code);
        
        // Determina il codice area in base al settore
        if ($sector == 'AUTOMOBILE') {
            // AUTOMOBILE_MR_A1 → area = A (prima lettera della terza parte)
            if (count($parts) >= 3) {
                $thirdPart = $parts[2];
                preg_match('/^([A-Z])/i', $thirdPart, $matches);
                $areaCode = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER';
            } else {
                $areaCode = 'OTHER';
            }
        } else {
            // MECCANICA, ELETTRICITA, ecc.: area = seconda parte
            $areaCode = count($parts) >= 2 ? $parts[1] : 'OTHER';
        }
        
        if (!isset($areas[$areaCode])) {
            $areaInfo = $areaDescriptions[$areaCode] ?? ['name' => $areaCode, 'icon' => '📁', 'color' => '#95a5a6'];
            $areas[$areaCode] = [
                'code' => $areaCode,
                'name' => $areaInfo['name'],
                'icon' => $areaInfo['icon'],
                'color' => $areaInfo['color'],
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

function generate_svg_radar($data, $title = '', $size = 300) {
    if (empty($data)) return '';
    
    $cx = $size / 2; $cy = $size / 2;
    $radius = ($size / 2) - 60;
    $n = count($data);
    $angleStep = (2 * M_PI) / $n;
    
    $svg = '<svg width="' . $size . '" height="' . ($size + 40) . '" xmlns="http://www.w3.org/2000/svg">';
    if ($title) $svg .= '<text x="' . $cx . '" y="20" text-anchor="middle" font-size="11" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    $offsetY = $title ? 30 : 0;
    
    for ($p = 20; $p <= 100; $p += 20) {
        $r = $radius * ($p / 100);
        $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . $r . '" fill="none" stroke="#ddd" stroke-width="0.5"/>';
    }
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.6) . '" fill="none" stroke="#f39c12" stroke-width="1.5" stroke-dasharray="5,3"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.8) . '" fill="none" stroke="#27ae60" stroke-width="1.5" stroke-dasharray="5,3"/>';
    
    $points = [];
    $i = 0;
    foreach ($data as $item) {
        $angle = ($i * $angleStep) - (M_PI / 2);
        $axisX = $cx + $radius * cos($angle);
        $axisY = ($cy + $offsetY) + $radius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . ($cy + $offsetY) . '" x2="' . $axisX . '" y2="' . $axisY . '" stroke="#ddd" stroke-width="0.5"/>';
        
        $value = min(100, max(0, $item['value']));
        $pointRadius = $radius * ($value / 100);
        $pointX = $cx + $pointRadius * cos($angle);
        $pointY = ($cy + $offsetY) + $pointRadius * sin($angle);
        $points[] = $pointX . ',' . $pointY;
        
        $labelRadius = $radius + 20;
        $labelX = $cx + $labelRadius * cos($angle);
        $labelY = ($cy + $offsetY) + $labelRadius * sin($angle);
        $anchor = ($labelX < $cx - 10) ? 'end' : (($labelX > $cx + 10) ? 'start' : 'middle');
        $labelColor = $item['value'] >= 80 ? '#27ae60' : ($item['value'] >= 60 ? '#3498db' : ($item['value'] >= 40 ? '#f39c12' : '#c0392b'));
        
        $displayLabel = strlen($item['label']) > 25 ? substr($item['label'], 0, 23) . '...' : $item['label'];
        $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="' . $anchor . '" font-size="7" fill="#333">' . htmlspecialchars($displayLabel) . '</text>';
        $svg .= '<text x="' . $labelX . '" y="' . ($labelY + 9) . '" text-anchor="' . $anchor . '" font-size="8" font-weight="bold" fill="' . $labelColor . '">' . $item['value'] . '%</text>';
        $i++;
    }
    
    if (!empty($points)) {
        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="rgba(102,126,234,0.3)" stroke="#667eea" stroke-width="2"/>';
        foreach ($points as $point) {
            list($px, $py) = explode(',', $point);
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="4" fill="#667eea"/>';
        }
    }
    
    $legendY = $size + $offsetY + 5;
    $svg .= '<line x1="' . ($cx - 80) . '" y1="' . $legendY . '" x2="' . ($cx - 60) . '" y2="' . $legendY . '" stroke="#f39c12" stroke-width="1.5" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx - 55) . '" y="' . ($legendY + 3) . '" font-size="7" fill="#666">60% Suff.</text>';
    $svg .= '<line x1="' . ($cx + 10) . '" y1="' . $legendY . '" x2="' . ($cx + 30) . '" y2="' . $legendY . '" stroke="#27ae60" stroke-width="1.5" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx + 35) . '" y="' . ($legendY + 3) . '" font-size="7" fill="#666">80% Ecc.</text>';
    $svg .= '</svg>';
    
    return $svg;
}

// ========================================
// CARICAMENTO DATI
// ========================================
$availableQuizzes = \local_competencyreport\report_generator::get_available_quizzes($userid, $courseid);
$quizIdsForQuery = !empty($selectedQuizzes) ? $selectedQuizzes : null;

$radardata = \local_competencyreport\report_generator::get_radar_chart_data($userid, $courseid, $quizIdsForQuery, $area ?: null);
$summary = \local_competencyreport\report_generator::get_student_summary($userid, $courseid, $quizIdsForQuery, $area ?: null);
$competencies = $radardata['competencies'];

$availableAreas = \local_competencyreport\report_generator::get_available_areas($userid, $courseid);
$quizComparison = \local_competencyreport\report_generator::get_quiz_comparison($userid, $courseid);
$progressData = \local_competencyreport\report_generator::get_progress_over_time($userid, $courseid);

// CARICAMENTO DESCRIZIONI DINAMICHE DAL DATABASE
$sector = \local_competencyreport\report_generator::detect_sector_from_competencies($competencies);
$areaDescriptions = \local_competencyreport\report_generator::get_area_descriptions_from_framework($sector, $courseid);
$competencyDescriptions = \local_competencyreport\report_generator::get_competency_descriptions_from_framework($sector);

$areasData = aggregate_by_area($competencies, $areaDescriptions, $sector);
$evaluation = get_evaluation_band($summary['overall_percentage']);
$actionPlan = generate_action_plan($competencies, $competencyDescriptions);
$certProgress = generate_certification_progress($competencies);

// Include file di visualizzazione
if ($print) {
    include(__DIR__ . '/student_print.php');
    exit;
}

// ========================================
// OUTPUT NORMALE
// ========================================
echo $OUTPUT->header();

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
echo '<div class="card mb-4"><div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;"><h5 class="mb-0">📝 Seleziona Quiz</h5></div><div class="card-body">';
echo '<form method="get" id="quizFilterForm"><input type="hidden" name="userid" value="' . $userid . '"><input type="hidden" name="courseid" value="' . $courseid . '"><input type="hidden" name="tab" value="' . $tab . '">';
if ($selectedArea) echo '<input type="hidden" name="selectedarea" value="' . $selectedArea . '">';
echo '<div class="row">';
foreach ($availableQuizzes as $quiz) {
    $isSelected = empty($selectedQuizzes) || in_array($quiz->id, $selectedQuizzes);
    echo '<div class="col-md-4 mb-2"><div class="p-2 border rounded" style="' . ($isSelected ? 'background:#e8f5e9;' : '') . '"><div class="form-check">';
    echo '<input type="checkbox" class="form-check-input quiz-checkbox" name="quizids[]" value="' . $quiz->id . '" id="quiz_' . $quiz->id . '" ' . ($isSelected ? 'checked' : '') . '>';
    echo '<label class="form-check-label" for="quiz_' . $quiz->id . '"><strong>' . format_string($quiz->name) . '</strong><br><small class="text-muted">' . $quiz->attempts . ' tentativo/i</small></label>';
    echo '</div></div></div>';
}
echo '</div><div class="mt-3 d-flex justify-content-between"><div><button type="button" class="btn btn-outline-primary btn-sm mr-2" onclick="document.querySelectorAll(\'.quiz-checkbox\').forEach(c=>c.checked=true)">✅ Tutti</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'.quiz-checkbox\').forEach(c=>c.checked=false)">☐ Nessuno</button></div><button type="submit" class="btn btn-success">🔄 Aggiorna</button></div></form></div></div>';

// Progresso
echo '<div class="card mb-4"><div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;"><h5 class="mb-0">📊 Progresso Certificazione</h5></div><div class="card-body">';
echo '<div class="progress mb-3" style="height: 30px;"><div class="progress-bar bg-success" style="width: ' . $certProgress['percentage'] . '%;"><strong>' . $certProgress['percentage'] . '% completato</strong></div></div>';
echo '<div class="row text-center"><div class="col-md-4"><div class="p-3 rounded" style="background: #d4edda;"><h3 class="text-success mb-0">' . $certProgress['certified'] . '</h3><small>✅ Certificate (≥80%)</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #fff3cd;"><h3 class="text-warning mb-0">' . $certProgress['inProgress'] . '</h3><small>🔄 In corso</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #f8d7da;"><h3 class="text-danger mb-0">' . $certProgress['notStarted'] . '</h3><small>⏳ Da iniziare</small></div></div></div></div></div>';

// Barra azioni
echo '<div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center">';
echo '<a href="' . new moodle_url('/local/competencyreport/index.php', ['courseid' => $courseid]) . '" class="btn btn-secondary">← Torna alla lista</a>';
echo '<div><button type="button" class="btn btn-primary mr-2" data-toggle="modal" data-target="#printModal">🖨️ Stampa</button>';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-success mr-2">📥 CSV</a>';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'excel']) . '" class="btn btn-success">📥 Excel</a></div></div></div>';

// Modale stampa
echo '<div class="modal fade" id="printModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">';
echo '<div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;"><h5 class="modal-title">🖨️ Opzioni di Stampa</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>';
echo '<div class="modal-body"><form id="printForm" method="get" target="_blank" action="' . new moodle_url('/local/competencyreport/student.php') . '">';
echo '<input type="hidden" name="userid" value="' . $userid . '"><input type="hidden" name="courseid" value="' . $courseid . '"><input type="hidden" name="print" value="1">';
foreach ($selectedQuizzes as $qid) echo '<input type="hidden" name="quizids[]" value="' . $qid . '">';
echo '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="print_panoramica" name="print_panoramica" value="1" checked><label class="custom-control-label" for="print_panoramica"><strong>📊 Panoramica</strong></label></div>';
echo '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="print_progressi" name="print_progressi" value="1" checked><label class="custom-control-label" for="print_progressi"><strong>📈 Progressi</strong></label></div>';
echo '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="print_piano" name="print_piano" value="1" checked><label class="custom-control-label" for="print_piano"><strong>📚 Piano d\'Azione</strong></label></div>';
echo '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="print_dettagli" name="print_dettagli" value="1" checked><label class="custom-control-label" for="print_dettagli"><strong>📋 Dettagli</strong></label></div>';
echo '<hr><p><strong>📊 Grafici Radar:</strong></p>';
echo '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="print_radar_aree" name="print_radar_aree" value="1" checked><label class="custom-control-label" for="print_radar_aree"><strong>🎯 Radar Aree</strong></label></div>';
echo '<p class="mt-3"><small><strong>🔍 Dettaglio aree:</strong></small></p><div class="pl-3">';
foreach ($areasData as $areaCode => $areaInfo) {
    echo '<div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input area-checkbox" id="print_area_' . $areaCode . '" name="print_radar_areas[]" value="' . $areaCode . '">';
    echo '<label class="custom-control-label" for="print_area_' . $areaCode . '">' . $areaInfo['icon'] . ' ' . $areaInfo['name'] . ' <small>(' . $areaInfo['percentage'] . '%)</small></label></div>';
}
echo '</div><div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'.area-checkbox\').forEach(c=>c.checked=true)">Tutte</button> <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll(\'.area-checkbox\').forEach(c=>c.checked=false)">Nessuna</button></div>';
echo '</form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button><button type="button" class="btn btn-primary" onclick="document.getElementById(\'printForm\').submit()">🖨️ Stampa</button></div></div></div></div>';

// Tabs
$tabs = ['overview' => '📊 Panoramica', 'action' => '📚 Piano', 'quiz' => '📝 Quiz', 'progress' => '📈 Progressi', 'details' => '📋 Dettagli'];
echo '<ul class="nav nav-tabs mb-4">';
foreach ($tabs as $tabKey => $tabLabel) {
    $activeClass = ($tab === $tabKey) ? 'active' : '';
    $url = new moodle_url('/local/competencyreport/student.php', array_merge(['userid' => $userid, 'courseid' => $courseid, 'tab' => $tabKey], $selectedArea ? ['selectedarea' => $selectedArea] : []));
    echo '<li class="nav-item"><a class="nav-link ' . $activeClass . '" href="' . $url . '">' . $tabLabel . '</a></li>';
}
echo '</ul>';

// Contenuto tabs
echo '<div class="tab-content">';

if ($tab === 'overview') {
    echo '<div class="row"><div class="col-md-6"><div class="card"><div class="card-header bg-primary text-white"><h5 class="mb-0">📊 Grafico Aree</h5></div><div class="card-body"><canvas id="radarAreas" style="max-height: 350px;"></canvas></div></div></div>';
    echo '<div class="col-md-6"><div class="card"><div class="card-header bg-info text-white"><h5 class="mb-0">📈 Statistiche</h5></div><div class="card-body">';
    echo '<p><strong>Aree totali:</strong> ' . count($areasData) . '</p><p><strong>Competenze:</strong> ' . count($competencies) . '</p><p><strong>Domande:</strong> ' . ($summary['questions_total'] ?? $summary['total_questions']) . '</p><hr>';
    echo '<h6>🏆 Aree Forti (≥60%)</h6><ul>';
    foreach ($areasData as $code => $areaData) { if ($areaData['percentage'] >= 60) echo '<li>' . $areaData['icon'] . ' ' . $areaData['name'] . ' - <strong>' . $areaData['percentage'] . '%</strong></li>'; }
    echo '</ul><h6>⚠️ Da Migliorare (<50%)</h6><ul>';
    foreach ($areasData as $code => $areaData) { if ($areaData['percentage'] < 50) echo '<li>' . $areaData['icon'] . ' ' . $areaData['name'] . ' - <strong>' . $areaData['percentage'] . '%</strong></li>'; }
    echo '</ul></div></div></div></div>';
    
} else if ($tab === 'action') {
    if (!empty($actionPlan['critical'])) {
        echo '<div class="card mb-3 border-danger"><div class="card-header bg-danger text-white"><h5 class="mb-0">🔴 CRITICO</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['critical'] as $item) echo '<tr class="table-danger"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-danger">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['toImprove'])) {
        echo '<div class="card mb-3 border-warning"><div class="card-header bg-warning"><h5 class="mb-0">🟠 DA MIGLIORARE</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['toImprove'] as $item) echo '<tr class="table-warning"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-warning">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['good'])) {
        echo '<div class="card mb-3 border-info"><div class="card-header bg-info text-white"><h5 class="mb-0">✅ ACQUISITE</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['good'] as $item) echo '<tr class="table-info"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-info">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['excellence'])) {
        echo '<div class="card mb-3 border-success"><div class="card-header bg-success text-white"><h5 class="mb-0">🌟 ECCELLENZA</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['excellence'] as $item) echo '<tr class="table-success"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-success">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    
} else if ($tab === 'quiz') {
    echo '<div class="card"><div class="card-header bg-secondary text-white"><h5 class="mb-0">📝 Per Quiz</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Quiz</th><th>Tentativo</th><th>Data</th><th>Punteggio</th><th>Competenze</th></tr></thead><tbody>';
    foreach ($quizComparison as $quiz) {
        foreach ($quiz['attempts'] as $attempt) {
            $badgeClass = $attempt['percentage'] >= 60 ? 'success' : ($attempt['percentage'] >= 40 ? 'warning' : 'danger');
            echo '<tr><td>' . format_string($quiz['name']) . '</td><td>#' . $attempt['attempt_number'] . '</td><td>' . date('d/m/Y H:i', $attempt['timefinish']) . '</td><td><span class="badge badge-' . $badgeClass . '">' . $attempt['percentage'] . '%</span></td><td>' . $attempt['competencies'] . '</td></tr>';
        }
    }
    echo '</tbody></table></div></div>';
    
} else if ($tab === 'progress') {
    echo '<div class="card"><div class="card-header bg-primary text-white"><h5 class="mb-0">📈 Progressi</h5></div><div class="card-body"><canvas id="progressChart" style="max-height: 300px;"></canvas></div></div>';
    
} else if ($tab === 'details') {
    // Prepara i dati per filtro e ordinamento
    $filteredCompetencies = $competencies;
    
    // Aggiungi area code a ogni competenza per il filtro
    foreach ($filteredCompetencies as &$comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $parts = explode('_', $code);
        
        // Determina il codice area in base al settore
        if ($sector == 'AUTOMOBILE') {
            if (count($parts) >= 3) {
                $thirdPart = $parts[2];
                preg_match('/^([A-Z])/i', $thirdPart, $matches);
                $comp['area_code'] = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER';
            } else {
                $comp['area_code'] = 'OTHER';
            }
        } else {
            $comp['area_code'] = count($parts) >= 2 ? $parts[1] : 'OTHER';
        }
        
        // Aggiungi livello valutazione
        $pct = $comp['percentage'];
        if ($pct >= 80) $comp['level'] = 'excellent';
        else if ($pct >= 60) $comp['level'] = 'good';
        else if ($pct >= 50) $comp['level'] = 'sufficient';
        else if ($pct >= 30) $comp['level'] = 'insufficient';
        else $comp['level'] = 'critical';
    }
    unset($comp);
    
    // Applica filtro per livello
    if ($filterLevel !== 'all') {
        $filteredCompetencies = array_filter($filteredCompetencies, function($c) use ($filterLevel) {
            return $c['level'] === $filterLevel;
        });
    }
    
    // Applica filtro per area
    if (!empty($filterArea)) {
        $filteredCompetencies = array_filter($filteredCompetencies, function($c) use ($filterArea) {
            return $c['area_code'] === $filterArea;
        });
    }
    
    // Applica ordinamento
    switch ($sortBy) {
        case 'perc_desc':
            usort($filteredCompetencies, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
            break;
        case 'perc_asc':
            usort($filteredCompetencies, fn($a, $b) => $a['percentage'] <=> $b['percentage']);
            break;
        case 'questions':
            usort($filteredCompetencies, fn($a, $b) => $b['total_questions'] <=> $a['total_questions']);
            break;
        case 'area':
        default:
            usort($filteredCompetencies, fn($a, $b) => strcmp($a['area_code'], $b['area_code']));
            break;
    }
    
    // Conta per livello (per mostrare i badge)
    $levelCounts = ['excellent' => 0, 'good' => 0, 'sufficient' => 0, 'insufficient' => 0, 'critical' => 0];
    foreach ($competencies as $c) {
        $pct = $c['percentage'];
        if ($pct >= 80) $levelCounts['excellent']++;
        else if ($pct >= 60) $levelCounts['good']++;
        else if ($pct >= 50) $levelCounts['sufficient']++;
        else if ($pct >= 30) $levelCounts['insufficient']++;
        else $levelCounts['critical']++;
    }
    
    // Estrai aree uniche per il filtro
    $uniqueAreas = [];
    foreach ($competencies as $c) {
        $code = $c['idnumber'] ?: $c['name'];
        $parts = explode('_', $code);
        if ($sector == 'AUTOMOBILE') {
            if (count($parts) >= 3) {
                preg_match('/^([A-Z])/i', $parts[2], $matches);
                $aCode = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER';
            } else {
                $aCode = 'OTHER';
            }
        } else {
            $aCode = count($parts) >= 2 ? $parts[1] : 'OTHER';
        }
        if (!isset($uniqueAreas[$aCode])) {
            $aInfo = $areaDescriptions[$aCode] ?? ['name' => $aCode, 'icon' => '📁'];
            $uniqueAreas[$aCode] = $aInfo;
        }
    }
    ksort($uniqueAreas);
    
    // URL base per i filtri
    $baseUrl = new moodle_url('/local/competencyreport/student.php', [
        'userid' => $userid,
        'courseid' => $courseid,
        'tab' => 'details'
    ]);
    
    ?>
    <div class="card">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0">📋 Dettaglio Competenze</h5>
                <span class="badge badge-light"><?php echo count($filteredCompetencies); ?> / <?php echo count($competencies); ?> competenze</span>
            </div>
        </div>
        
        <!-- Barra Filtri -->
        <div class="card-body border-bottom" style="background: #f8f9fa;">
            <div class="row">
                <!-- Ordinamento -->
                <div class="col-md-3 mb-2">
                    <label class="small text-muted mb-1"><strong>📊 Ordina per:</strong></label>
                    <select class="form-control form-control-sm" id="sortSelect" onchange="applyFilters()">
                        <option value="area" <?php echo $sortBy === 'area' ? 'selected' : ''; ?>>📁 Per Area (A-Z)</option>
                        <option value="perc_desc" <?php echo $sortBy === 'perc_desc' ? 'selected' : ''; ?>>📈 Valutazione (migliore → peggiore)</option>
                        <option value="perc_asc" <?php echo $sortBy === 'perc_asc' ? 'selected' : ''; ?>>📉 Valutazione (peggiore → migliore)</option>
                        <option value="questions" <?php echo $sortBy === 'questions' ? 'selected' : ''; ?>>🔢 Numero Domande</option>
                    </select>
                </div>
                
                <!-- Filtro Livello -->
                <div class="col-md-3 mb-2">
                    <label class="small text-muted mb-1"><strong>🎯 Filtra per livello:</strong></label>
                    <select class="form-control form-control-sm" id="filterSelect" onchange="applyFilters()">
                        <option value="all" <?php echo $filterLevel === 'all' ? 'selected' : ''; ?>>📋 Tutti (<?php echo count($competencies); ?>)</option>
                        <option value="excellent" <?php echo $filterLevel === 'excellent' ? 'selected' : ''; ?>>🌟 Eccellenti ≥80% (<?php echo $levelCounts['excellent']; ?>)</option>
                        <option value="good" <?php echo $filterLevel === 'good' ? 'selected' : ''; ?>>✅ Buoni 60-79% (<?php echo $levelCounts['good']; ?>)</option>
                        <option value="sufficient" <?php echo $filterLevel === 'sufficient' ? 'selected' : ''; ?>>⚠️ Sufficienti 50-59% (<?php echo $levelCounts['sufficient']; ?>)</option>
                        <option value="insufficient" <?php echo $filterLevel === 'insufficient' ? 'selected' : ''; ?>>⚡ Insufficienti 30-49% (<?php echo $levelCounts['insufficient']; ?>)</option>
                        <option value="critical" <?php echo $filterLevel === 'critical' ? 'selected' : ''; ?>>🔴 Critici &lt;30% (<?php echo $levelCounts['critical']; ?>)</option>
                    </select>
                </div>
                
                <!-- Filtro Area -->
                <div class="col-md-3 mb-2">
                    <label class="small text-muted mb-1"><strong>📁 Filtra per area:</strong></label>
                    <select class="form-control form-control-sm" id="filterAreaSelect" onchange="applyFilters()">
                        <option value="">🗂️ Tutte le aree</option>
                        <?php foreach ($uniqueAreas as $aCode => $aInfo): ?>
                        <option value="<?php echo $aCode; ?>" <?php echo $filterArea === $aCode ? 'selected' : ''; ?>>
                            <?php echo ($aInfo['icon'] ?? '📁') . ' ' . $aCode . ' - ' . ($aInfo['name'] ?? $aCode); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Reset -->
                <div class="col-md-3 mb-2">
                    <label class="small text-muted mb-1">&nbsp;</label>
                    <a href="<?php echo $baseUrl->out(false); ?>" class="btn btn-outline-secondary btn-sm btn-block">
                        🔄 Reset Filtri
                    </a>
                </div>
            </div>
            
            <!-- Quick Filters (Badge cliccabili) -->
            <div class="mt-2">
                <span class="small text-muted mr-2">Filtro rapido:</span>
                <a href="<?php echo $baseUrl->out(false); ?>&filter=critical&sort=perc_asc" class="badge badge-danger mr-1" style="cursor:pointer;">🔴 Critici (<?php echo $levelCounts['critical']; ?>)</a>
                <a href="<?php echo $baseUrl->out(false); ?>&filter=insufficient&sort=perc_asc" class="badge badge-warning mr-1" style="cursor:pointer; color:#856404;">⚡ Insufficienti (<?php echo $levelCounts['insufficient']; ?>)</a>
                <a href="<?php echo $baseUrl->out(false); ?>&filter=sufficient&sort=perc_asc" class="badge badge-secondary mr-1" style="cursor:pointer;">⚠️ Sufficienti (<?php echo $levelCounts['sufficient']; ?>)</a>
                <a href="<?php echo $baseUrl->out(false); ?>&filter=good&sort=perc_desc" class="badge badge-info mr-1" style="cursor:pointer;">✅ Buoni (<?php echo $levelCounts['good']; ?>)</a>
                <a href="<?php echo $baseUrl->out(false); ?>&filter=excellent&sort=perc_desc" class="badge badge-success mr-1" style="cursor:pointer;">🌟 Eccellenti (<?php echo $levelCounts['excellent']; ?>)</a>
            </div>
        </div>
        
        <!-- Tabella Risultati -->
        <div class="card-body p-0">
            <?php if (empty($filteredCompetencies)): ?>
            <div class="alert alert-info m-3">
                <strong>Nessun risultato</strong> - Nessuna competenza corrisponde ai filtri selezionati.
            </div>
            <?php else: ?>
            
            <?php if ($sortBy === 'area'): ?>
            <!-- Vista raggruppata per Area -->
            <?php
            $groupedByArea = [];
            foreach ($filteredCompetencies as $comp) {
                $aCode = $comp['area_code'];
                if (!isset($groupedByArea[$aCode])) {
                    $groupedByArea[$aCode] = [
                        'competencies' => [],
                        'total_questions' => 0,
                        'correct_questions' => 0
                    ];
                }
                $groupedByArea[$aCode]['competencies'][] = $comp;
                $groupedByArea[$aCode]['total_questions'] += $comp['total_questions'];
                $groupedByArea[$aCode]['correct_questions'] += $comp['correct_questions'];
            }
            ksort($groupedByArea);
            ?>
            
            <?php foreach ($groupedByArea as $aCode => $areaGroup): 
                $areaInfo = $areaDescriptions[$aCode] ?? ['name' => $aCode, 'icon' => '📁', 'color' => '#95a5a6'];
                $areaPct = $areaGroup['total_questions'] > 0 ? round($areaGroup['correct_questions'] / $areaGroup['total_questions'] * 100, 1) : 0;
                $areaBand = get_evaluation_band($areaPct);
            ?>
            <div class="area-group mb-0">
                <!-- Header Area -->
                <div class="area-header p-3" style="background: linear-gradient(135deg, <?php echo $areaInfo['color'] ?? '#95a5a6'; ?>15, <?php echo $areaInfo['color'] ?? '#95a5a6'; ?>05); border-left: 4px solid <?php echo $areaInfo['color'] ?? '#95a5a6'; ?>; border-bottom: 1px solid #dee2e6;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span style="font-size: 1.5rem;"><?php echo $areaInfo['icon'] ?? '📁'; ?></span>
                            <strong class="ml-2" style="font-size: 1.1rem;"><?php echo $aCode; ?> - <?php echo $areaInfo['name'] ?? $aCode; ?></strong>
                            <span class="badge badge-secondary ml-2"><?php echo count($areaGroup['competencies']); ?> competenze</span>
                        </div>
                        <div class="text-right">
                            <div class="mb-1">
                                <span class="badge badge-<?php echo $areaBand['class']; ?>" style="font-size: 1rem;">
                                    <?php echo $areaBand['icon']; ?> <?php echo $areaPct; ?>%
                                </span>
                            </div>
                            <small class="text-muted"><?php echo $areaGroup['correct_questions']; ?>/<?php echo $areaGroup['total_questions']; ?> risposte corrette</small>
                        </div>
                    </div>
                    <!-- Progress bar area -->
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-<?php echo $areaBand['class']; ?>" style="width: <?php echo $areaPct; ?>%;"></div>
                    </div>
                </div>
                
                <!-- Competenze dell'area -->
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                    <?php foreach ($areaGroup['competencies'] as $comp): 
                        $code = $comp['idnumber'] ?: $comp['name'];
                        $compInfo = $competencyDescriptions[$code] ?? null;
                        $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : (!empty($comp['description']) ? $comp['description'] : $code);
                        $band = get_evaluation_band($comp['percentage']);
                    ?>
                    <tr style="border-left: 4px solid <?php echo $band['color']; ?>;">
                        <td style="width: 180px;"><small class="text-muted"><?php echo $code; ?></small></td>
                        <td><?php echo htmlspecialchars($displayName); ?></td>
                        <td style="width: 100px;" class="text-center"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td>
                        <td style="width: 80px;" class="text-center"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td>
                        <td style="width: 130px; color: <?php echo $band['color']; ?>; font-weight: bold;"><?php echo $band['icon']; ?> <?php echo $band['label']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            
            <?php else: ?>
            <!-- Vista tabella semplice (ordinata per valutazione o domande) -->
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
            <?php endif; ?>
            
            <?php endif; ?>
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

echo '</div>';

// JavaScript
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if ($tab === 'overview' && !empty($areasData)): ?>
const ctxAreas = document.getElementById('radarAreas');
if (ctxAreas) {
    new Chart(ctxAreas, {
        type: 'radar',
        data: {
            labels: [<?php echo implode(',', array_map(function($a) { return "'" . addslashes($a['icon'] . ' ' . $a['name']) . "'"; }, $areasData)); ?>],
            datasets: [{
                label: 'Percentuale',
                data: [<?php echo implode(',', array_map(function($a) { return $a['percentage']; }, $areasData)); ?>],
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(102, 126, 234, 1)'
            }]
        },
        options: { scales: { r: { beginAtZero: true, max: 100 } } }
    });
}
<?php endif; ?>

<?php if ($tab === 'progress' && !empty($progressData)): ?>
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
                tension: 0.3
            }]
        },
        options: { scales: { y: { beginAtZero: true, max: 100 } } }
    });
}
<?php endif; ?>
</script>
<?php

echo $OUTPUT->footer();
