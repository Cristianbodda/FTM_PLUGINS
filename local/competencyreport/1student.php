<?php
/**
 * Report singolo studente - VERSIONE 5
 * Con selezione MULTIPLA dei quiz e doppio grafico radar
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

// NUOVO: Gestione selezione multipla quiz
$selectedQuizzes = optional_param_array('quizids', [], PARAM_INT);
// Supporto anche per parametro singolo (retrocompatibilità)
$singleQuizId = optional_param('quizid', 0, PARAM_INT);
if ($singleQuizId && empty($selectedQuizzes)) {
    $selectedQuizzes = [$singleQuizId];
}

// Verifica utente esiste
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Context e permessi
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
$PAGE->set_url(new moodle_url('/local/competencyreport/student.php', [
    'userid' => $userid, 
    'courseid' => $courseid,
    'tab' => $tab
]));
$PAGE->set_title(get_string('studentreport', 'local_competencyreport') . ': ' . fullname($student));
$PAGE->set_heading(get_string('studentreport', 'local_competencyreport'));

// ========================================
// MAPPATURA AREE E DESCRIZIONI
// ========================================
$areaDescriptions = [
    'DT' => ['name' => 'Disegno Tecnico', 'icon' => '📐', 'color' => '#e74c3c'],
    'MIS' => ['name' => 'Metrologia e Misure', 'icon' => '📏', 'color' => '#3498db'],
    'LMB' => ['name' => 'Lavorazioni Base', 'icon' => '🔧', 'color' => '#2ecc71'],
    'LMC' => ['name' => 'Macchine Convenzionali', 'icon' => '⚙️', 'color' => '#f39c12'],
    'CNC' => ['name' => 'CNC e Programmazione', 'icon' => '🖥️', 'color' => '#9b59b6'],
    'ASS' => ['name' => 'Assemblaggio e Montaggio', 'icon' => '🔩', 'color' => '#1abc9c'],
    'GEN' => ['name' => 'Processi Generali', 'icon' => '🏭', 'color' => '#e67e22'],
    'MAT' => ['name' => 'Materiali', 'icon' => '🧱', 'color' => '#34495e'],
    'SIC' => ['name' => 'Sicurezza', 'icon' => '⚠️', 'color' => '#c0392b'],
];

$competencyDescriptions = [
    'MECCANICA_DT_01' => ['name' => 'Disegno tecnico e simbologia', 'desc' => 'Lettura e interpretazione di disegni meccanici'],
    'MECCANICA_DT_02' => ['name' => 'Disegno tecnico avanzato', 'desc' => 'Proiezioni ortogonali, sezioni, quotature'],
    'MECCANICA_MIS_01' => ['name' => 'Strumenti di misura base', 'desc' => 'Uso di calibro, micrometro e comparatore'],
    'MECCANICA_MIS_02' => ['name' => 'Rugosità superficiale', 'desc' => 'Parametri Ra, Rz e controllo finitura'],
    'MECCANICA_MIS_04' => ['name' => 'Tolleranze e accoppiamenti', 'desc' => 'Sistema ISO di tolleranze'],
    'MECCANICA_LMB_02' => ['name' => 'Foratura e alesatura', 'desc' => 'Tecniche di foratura, trapano'],
    'MECCANICA_LMB_03' => ['name' => 'Aggiustaggio manuale', 'desc' => 'Limatura, raschiatura'],
    'MECCANICA_LMB_04' => ['name' => 'Materiali metallici', 'desc' => 'Proprietà di acciai, ghise, leghe'],
    'MECCANICA_LMB_05' => ['name' => 'Lavorazioni a freddo', 'desc' => 'Punzonatura, piegatura'],
    'MECCANICA_LMC_01' => ['name' => 'Fresatura convenzionale', 'desc' => 'Parametri di taglio, frese'],
    'MECCANICA_LMC_02' => ['name' => 'Tornitura convenzionale', 'desc' => 'Parametri di taglio, utensili'],
    'MECCANICA_LMC_04' => ['name' => 'Lubrorefrigeranti', 'desc' => 'Tipi di fluidi, manutenzione'],
    'MECCANICA_CNC_01' => ['name' => 'Programmazione CNC base', 'desc' => 'Codici G e M, struttura programma'],
    'MECCANICA_CNC_02' => ['name' => 'CNC avanzato', 'desc' => 'Cicli fissi, sottoprogrammi'],
    'MECCANICA_ASS_01' => ['name' => 'Assemblaggio meccanico', 'desc' => 'Tecniche di montaggio'],
    'MECCANICA_ASS_04' => ['name' => 'Collaudo e verifica', 'desc' => 'Procedure di collaudo'],
    'MECCANICA_GEN_01' => ['name' => 'Trattamenti termici', 'desc' => 'Tempra, rinvenimento, bonifica'],
    'MECCANICA_GEN_02' => ['name' => 'Saldatura', 'desc' => 'Processi di saldatura'],
];

// ========================================
// FUNZIONI HELPER
// ========================================

function get_evaluation_band($percentage) {
    if ($percentage >= 81) {
        return ['level' => 'excellent', 'label' => 'ECCELLENTE', 'icon' => '🌟', 'color' => '#28a745', 'bgColor' => '#d4edda',
            'description' => 'Padronanza completa della competenza.', 'action' => 'Pronto per attività avanzate.'];
    } elseif ($percentage >= 66) {
        return ['level' => 'good', 'label' => 'BUONO', 'icon' => '🟢', 'color' => '#20c997', 'bgColor' => '#d1f2eb',
            'description' => 'Competenza acquisita con buona padronanza.', 'action' => 'Consolidare con esercizi pratici.'];
    } elseif ($percentage >= 51) {
        return ['level' => 'sufficient', 'label' => 'SUFFICIENTE', 'icon' => '🟡', 'color' => '#ffc107', 'bgColor' => '#fff3cd',
            'description' => 'Base acquisita ma da consolidare.', 'action' => 'Ripasso teoria ed esercitazioni.'];
    } elseif ($percentage >= 31) {
        return ['level' => 'insufficient', 'label' => 'INSUFFICIENTE', 'icon' => '🟠', 'color' => '#fd7e14', 'bgColor' => '#ffe5d0',
            'description' => 'Lacune significative.', 'action' => 'Percorso di recupero mirato.'];
    } else {
        return ['level' => 'critical', 'label' => 'CRITICO', 'icon' => '🔴', 'color' => '#dc3545', 'bgColor' => '#f8d7da',
            'description' => 'Competenza non acquisita.', 'action' => 'Formazione base completa.'];
    }
}

function aggregate_by_area($competencies, $areaDescriptions) {
    $areas = [];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $parts = explode('_', $code);
        $areaCode = count($parts) >= 2 ? $parts[1] : $parts[0];
        
        if (!isset($areas[$areaCode])) {
            $areaInfo = $areaDescriptions[$areaCode] ?? ['name' => $areaCode, 'icon' => '📊', 'color' => '#6c757d'];
            $areas[$areaCode] = [
                'code' => $areaCode, 'name' => $areaInfo['name'], 'icon' => $areaInfo['icon'], 'color' => $areaInfo['color'],
                'total_questions' => 0, 'correct_questions' => 0, 'competencies' => [], 'competency_count' => 0
            ];
        }
        $areas[$areaCode]['total_questions'] += $comp['total_questions'];
        $areas[$areaCode]['correct_questions'] += $comp['correct_questions'];
        $areas[$areaCode]['competencies'][] = $comp;
        $areas[$areaCode]['competency_count']++;
    }
    foreach ($areas as &$area) {
        $area['percentage'] = $area['total_questions'] > 0 ? round(($area['correct_questions'] / $area['total_questions']) * 100, 1) : 0;
    }
    return $areas;
}

function generate_area_radar_svg($areas, $areaDescriptions, $width = 400, $height = 400) {
    $cx = $width / 2; $cy = $height / 2; $maxRadius = min($width, $height) / 2 - 60;
    $n = count($areas);
    if ($n == 0) return '<p class="text-muted">Nessun dato disponibile</p>';
    
    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="max-width: 100%; height: auto;">';
    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#fafafa" rx="10"/>';
    
    for ($i = 5; $i >= 1; $i--) {
        $r = $maxRadius * ($i / 5);
        $opacity = 0.1 + ($i * 0.05);
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="rgba(102, 126, 234, ' . $opacity . ')" stroke="#ddd" stroke-width="1"/>';
    }
    
    for ($i = 1; $i <= 5; $i++) {
        $r = $maxRadius * ($i / 5);
        $svg .= '<text x="' . ($cx + 5) . '" y="' . ($cy - $r + 4) . '" font-size="9" fill="#999">' . ($i * 20) . '%</text>';
    }
    
    $i = 0; $areasArray = array_values($areas);
    foreach ($areasArray as $area) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $x = $cx + $maxRadius * cos($angle); $y = $cy + $maxRadius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" stroke="#ccc" stroke-width="1" stroke-dasharray="3,3"/>';
        $labelX = $cx + ($maxRadius + 35) * cos($angle); $labelY = $cy + ($maxRadius + 35) * sin($angle);
        $svg .= '<circle cx="' . $labelX . '" cy="' . $labelY . '" r="18" fill="' . $area['color'] . '" stroke="white" stroke-width="2"/>';
        $svg .= '<text x="' . $labelX . '" y="' . ($labelY + 4) . '" text-anchor="middle" font-size="9" fill="white" font-weight="bold">' . $area['code'] . '</text>';
        $i++;
    }
    
    $svg .= '<defs><linearGradient id="areaGradient" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '<stop offset="0%" style="stop-color:#667eea;stop-opacity:0.6"/>';
    $svg .= '<stop offset="100%" style="stop-color:#764ba2;stop-opacity:0.6"/></linearGradient></defs>';
    
    $points = []; $i = 0;
    foreach ($areasArray as $area) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $r = $maxRadius * ($area['percentage'] / 100);
        $points[] = ($cx + $r * cos($angle)) . ',' . ($cy + $r * sin($angle));
        $i++;
    }
    $svg .= '<polygon points="' . implode(' ', $points) . '" fill="url(#areaGradient)" stroke="#667eea" stroke-width="3"/>';
    
    $i = 0;
    foreach ($areasArray as $area) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $r = $maxRadius * ($area['percentage'] / 100);
        $x = $cx + $r * cos($angle); $y = $cy + $r * sin($angle);
        $band = get_evaluation_band($area['percentage']);
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="8" fill="' . $band['color'] . '" stroke="white" stroke-width="3"/>';
        if ($area['percentage'] > 20) {
            $lx = $cx + ($r - 20) * cos($angle); $ly = $cy + ($r - 20) * sin($angle);
            $svg .= '<text x="' . $lx . '" y="' . ($ly + 4) . '" text-anchor="middle" font-size="10" fill="#333" font-weight="bold">' . $area['percentage'] . '%</text>';
        }
        $i++;
    }
    $svg .= '</svg>';
    return $svg;
}

function generate_competency_radar_svg($competencies, $width = 400, $height = 400) {
    $cx = $width / 2; $cy = $height / 2; $maxRadius = min($width, $height) / 2 - 50;
    $n = count($competencies);
    if ($n == 0) return '<p class="text-muted">Seleziona un\'area</p>';
    
    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="max-width: 100%; height: auto;">';
    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="#f8f9fa" rx="10"/>';
    
    for ($i = 5; $i >= 1; $i--) {
        $r = $maxRadius * ($i / 5);
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#e0e0e0" stroke-width="1"/>';
    }
    
    $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
    $i = 0;
    foreach ($competencies as $comp) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $x = $cx + $maxRadius * cos($angle); $y = $cy + $maxRadius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" stroke="#ddd" stroke-width="1"/>';
        $labelX = $cx + ($maxRadius + 20) * cos($angle); $labelY = $cy + ($maxRadius + 20) * sin($angle);
        $svg .= '<circle cx="' . $labelX . '" cy="' . $labelY . '" r="12" fill="' . $colors[$i % count($colors)] . '"/>';
        $svg .= '<text x="' . $labelX . '" y="' . ($labelY + 4) . '" text-anchor="middle" font-size="10" fill="white" font-weight="bold">' . ($i + 1) . '</text>';
        $i++;
    }
    
    $points = []; $i = 0;
    foreach ($competencies as $comp) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $r = $maxRadius * ($comp['percentage'] / 100);
        $points[] = ($cx + $r * cos($angle)) . ',' . ($cy + $r * sin($angle));
        $i++;
    }
    $svg .= '<polygon points="' . implode(' ', $points) . '" fill="rgba(46, 204, 113, 0.3)" stroke="#2ecc71" stroke-width="2"/>';
    
    $i = 0;
    foreach ($competencies as $comp) {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $r = $maxRadius * ($comp['percentage'] / 100);
        $svg .= '<circle cx="' . ($cx + $r * cos($angle)) . '" cy="' . ($cy + $r * sin($angle)) . '" r="5" fill="#2ecc71" stroke="white" stroke-width="2"/>';
        $i++;
    }
    $svg .= '</svg>';
    return $svg;
}

function generate_action_plan($competencies, $descriptions) {
    $critical = []; $toImprove = []; $acquired = []; $excellent = [];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $info = $descriptions[$code] ?? ['name' => $code, 'desc' => ''];
        $item = ['code' => $code, 'name' => $info['name'], 'desc' => $info['desc'] ?? '', 
                 'percentage' => $comp['percentage'], 'correct' => $comp['correct_questions'], 'total' => $comp['total_questions']];
        if ($comp['percentage'] < 31) $critical[] = $item;
        elseif ($comp['percentage'] < 51) $toImprove[] = $item;
        elseif ($comp['percentage'] < 81) $acquired[] = $item;
        else $excellent[] = $item;
    }
    return ['critical' => $critical, 'toImprove' => $toImprove, 'acquired' => $acquired, 'excellent' => $excellent];
}

function generate_certification_progress($competencies) {
    $total = count($competencies); $certified = $inProgress = $notStarted = 0;
    foreach ($competencies as $comp) {
        if ($comp['percentage'] >= 80) $certified++;
        elseif ($comp['percentage'] > 0) $inProgress++;
        else $notStarted++;
    }
    return ['total' => $total, 'certified' => $certified, 'inProgress' => $inProgress, 'notStarted' => $notStarted,
            'percentage' => $total > 0 ? round(($certified / $total) * 100) : 0];
}

// ========================================
// OTTIENI DATI CON SUPPORTO QUIZ MULTIPLI
// ========================================
$availableQuizzes = \local_competencyreport\report_generator::get_available_quizzes($userid, $courseid);

// Se sono selezionati quiz specifici, usa quelli, altrimenti tutti
$quizIdsForQuery = !empty($selectedQuizzes) ? $selectedQuizzes : null;

$radardata = \local_competencyreport\report_generator::get_radar_chart_data($userid, $courseid, $quizIdsForQuery, $area ?: null);
$summary = \local_competencyreport\report_generator::get_student_summary($userid, $courseid, $quizIdsForQuery, $area ?: null);
$competencies = $radardata['competencies'];

$availableAreas = \local_competencyreport\report_generator::get_available_areas($userid, $courseid);
$quizComparison = \local_competencyreport\report_generator::get_quiz_comparison($userid, $courseid);
$progressData = \local_competencyreport\report_generator::get_progress_over_time($userid, $courseid);

$areasData = aggregate_by_area($competencies, $areaDescriptions);
$evaluation = get_evaluation_band($summary['overall_percentage']);
$actionPlan = generate_action_plan($competencies, $competencyDescriptions);
$certProgress = generate_certification_progress($competencies);

$filteredCompetencies = [];
if ($selectedArea && isset($areasData[$selectedArea])) {
    $filteredCompetencies = $areasData[$selectedArea]['competencies'];
}

// ========================================
// MODALITÀ STAMPA
// ========================================
if ($print) {
    // Output pagina stampabile standalone
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheda Valutazione Competenze - <?php echo fullname($student); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 18pt; margin-bottom: 5px; }
        .header p { margin: 2px 0; font-size: 10pt; }
        .score-big { font-size: 36pt; font-weight: bold; text-align: right; }
        .evaluation-box { border-left: 5px solid; padding: 15px; margin-bottom: 20px; background: #f8f9fa; }
        .section { margin-bottom: 20px; }
        .section-title { background: #667eea; color: white; padding: 8px 15px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 9pt; color: white; }
        .badge-success { background: #28a745; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-danger { background: #dc3545; }
        .badge-info { background: #17a2b8; }
        .progress-bar { height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); }
        .stats-row { display: flex; justify-content: space-around; margin: 15px 0; }
        .stat-box { text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px; min-width: 100px; }
        .stat-number { font-size: 24pt; font-weight: bold; color: #667eea; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #667eea; font-size: 9pt; color: #666; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        @media print {
            body { padding: 10px; }
            .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .section-title { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="row">
            <div class="col">
                <h1>📋 SCHEDA VALUTAZIONE COMPETENZE</h1>
                <p><strong>Studente:</strong> <?php echo fullname($student); ?></p>
                <p><strong>Email:</strong> <?php echo $student->email; ?></p>
                <?php if ($course): ?>
                <p><strong>Corso:</strong> <?php echo format_string($course->fullname); ?></p>
                <?php endif; ?>
                <p><strong>Data:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <div class="col" style="text-align: right;">
                <div class="score-big"><?php echo $summary['overall_percentage']; ?>%</div>
                <p>Punteggio Globale</p>
            </div>
        </div>
    </div>

    <div class="evaluation-box" style="border-color: <?php echo $evaluation['color']; ?>; background: <?php echo $evaluation['bgColor']; ?>;">
        <strong style="color: <?php echo $evaluation['color']; ?>; font-size: 14pt;">
            <?php echo $evaluation['icon']; ?> VALUTAZIONE: <?php echo $evaluation['label']; ?>
        </strong>
        <p style="margin-top: 5px;"><?php echo $evaluation['description']; ?></p>
        <p><strong>💡 Azione consigliata:</strong> <?php echo $evaluation['action']; ?></p>
        <p style="margin-top: 5px;"><strong>Risposte corrette:</strong> <?php echo ($summary['correct_total'] ?? $summary['correct_questions']) . '/' . ($summary['questions_total'] ?? $summary['total_questions']); ?></p>
    </div>

    <div class="section">
        <div class="section-title">📊 Progresso Certificazione</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $certProgress['percentage']; ?>%;"></div>
        </div>
        <div class="stats-row">
            <div class="stat-box" style="background: #d4edda;">
                <div class="stat-number" style="color: #28a745;"><?php echo $certProgress['certified']; ?></div>
                <small>✅ Certificate (≥80%)</small>
            </div>
            <div class="stat-box" style="background: #fff3cd;">
                <div class="stat-number" style="color: #856404;"><?php echo $certProgress['inProgress']; ?></div>
                <small>🔄 In corso (1-79%)</small>
            </div>
            <div class="stat-box" style="background: #f8d7da;">
                <div class="stat-number" style="color: #721c24;"><?php echo $certProgress['notStarted']; ?></div>
                <small>⏳ Da iniziare (0%)</small>
            </div>
        </div>
    </div>

    <?php if (!empty($actionPlan['critical']) || !empty($actionPlan['toImprove'])): ?>
    <div class="section">
        <div class="section-title">📚 Piano d'Azione</div>
        <?php if (!empty($actionPlan['critical'])): ?>
        <p style="margin: 10px 0;"><strong style="color: #dc3545;">🔴 PRIORITÀ ALTA:</strong></p>
        <ul style="margin-left: 20px;">
            <?php foreach ($actionPlan['critical'] as $item): ?>
            <li><?php echo $item['name']; ?> (<?php echo $item['code']; ?>) - <span class="badge badge-danger"><?php echo $item['percentage']; ?>%</span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        
        <?php if (!empty($actionPlan['toImprove'])): ?>
        <p style="margin: 10px 0;"><strong style="color: #fd7e14;">🟠 PRIORITÀ MEDIA:</strong></p>
        <ul style="margin-left: 20px;">
            <?php foreach ($actionPlan['toImprove'] as $item): ?>
            <li><?php echo $item['name']; ?> (<?php echo $item['code']; ?>) - <span class="badge badge-warning"><?php echo $item['percentage']; ?>%</span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">📋 Dettaglio Competenze</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 10%;">Area</th>
                    <th style="width: 20%;">Codice</th>
                    <th style="width: 30%;">Competenza</th>
                    <th style="width: 12%;">Risposte</th>
                    <th style="width: 10%;">%</th>
                    <th style="width: 13%;">Valutazione</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($competencies as $comp):
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $parts = explode('_', $code);
                    $areaCode = count($parts) >= 2 ? $parts[1] : '';
                    $info = $competencyDescriptions[$code] ?? ['name' => $code];
                    $band = get_evaluation_band($comp['percentage']);
                ?>
                <tr style="background: <?php echo $band['bgColor']; ?>;">
                    <td><?php echo $i; ?></td>
                    <td><span class="badge badge-info"><?php echo $areaCode; ?></span></td>
                    <td><small><?php echo $code; ?></small></td>
                    <td><?php echo $info['name']; ?></td>
                    <td><?php echo $comp['correct_questions'] . '/' . $comp['total_questions']; ?></td>
                    <td><strong><?php echo $comp['percentage']; ?>%</strong></td>
                    <td style="color: <?php echo $band['color']; ?>;"><?php echo $band['icon'] . ' ' . $band['label']; ?></td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <div class="row">
            <div class="col">
                <p><strong>Documento generato il:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                <p><strong>Sistema:</strong> Report Competenze Moodle</p>
            </div>
            <div class="col" style="text-align: right;">
                <p><strong>Firma Docente:</strong> _______________________</p>
                <p style="margin-top: 20px;"><strong>Firma Studente:</strong> _______________________</p>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
    <?php
    exit; // Termina qui per la modalità stampa
}

// ========================================
// OUTPUT NORMALE
// ========================================
echo $OUTPUT->header();

// Header studente
echo '<div class="student-report-header mb-4 p-4 rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
echo '<div class="row align-items-center">';
echo '<div class="col-auto">' . $OUTPUT->user_picture($student, ['size' => 80, 'class' => 'rounded-circle border border-white']) . '</div>';
echo '<div class="col">';
echo '<h2 class="mb-1 text-white">' . fullname($student) . '</h2>';
echo '<p class="mb-0 opacity-75">' . $student->email . '</p>';
if ($course) echo '<p class="mb-0"><small>📚 ' . format_string($course->fullname) . '</small></p>';
echo '</div>';
echo '<div class="col-auto text-right">';
echo '<div style="font-size: 3.5rem; font-weight: bold;">' . $summary['overall_percentage'] . '%</div>';
echo '<p class="mb-0 opacity-75">Punteggio globale</p>';
echo '</div></div></div>';

// Box valutazione
echo '<div class="card mb-4" style="border-left: 5px solid ' . $evaluation['color'] . ';">';
echo '<div class="card-body"><div class="row align-items-center">';
echo '<div class="col-auto"><div style="font-size: 3rem;">' . $evaluation['icon'] . '</div></div>';
echo '<div class="col">';
echo '<h4 class="mb-1" style="color: ' . $evaluation['color'] . ';">VALUTAZIONE: ' . $evaluation['label'] . '</h4>';
echo '<p class="mb-1">' . $evaluation['description'] . '</p>';
echo '<p class="mb-0"><strong>💡 Azione:</strong> ' . $evaluation['action'] . '</p>';
echo '</div>';
echo '<div class="col-auto text-center p-3 rounded" style="background: ' . $evaluation['bgColor'] . ';">';
echo '<div style="font-size: 2rem; font-weight: bold; color: ' . $evaluation['color'] . ';">' . ($summary['correct_total'] ?? $summary['correct_questions']) . '/' . ($summary['questions_total'] ?? $summary['total_questions']) . '</div>';
echo '<small>risposte corrette</small>';
echo '</div></div></div></div>';

// ========================================
// NUOVO: PANNELLO SELEZIONE QUIZ MULTIPLA
// ========================================
echo '<div class="card mb-4">';
echo '<div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">';
echo '<h5 class="mb-0">📝 Seleziona Quiz da includere nel Report</h5>';
echo '</div>';
echo '<div class="card-body">';

echo '<form method="get" id="quizFilterForm">';
echo '<input type="hidden" name="userid" value="' . $userid . '">';
echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
echo '<input type="hidden" name="tab" value="' . $tab . '">';
if ($selectedArea) echo '<input type="hidden" name="selectedarea" value="' . $selectedArea . '">';

echo '<div class="row">';

// Lista quiz con checkbox
if (!empty($availableQuizzes)) {
    $quizCount = count($availableQuizzes);
    $colSize = $quizCount <= 4 ? 'col-md-3' : 'col-md-4';
    
    foreach ($availableQuizzes as $quiz) {
        $checked = empty($selectedQuizzes) || in_array($quiz->id, $selectedQuizzes) ? 'checked' : '';
        $bgColor = $checked ? '#e8f5e9' : '#fff';
        
        echo '<div class="' . $colSize . ' mb-2">';
        echo '<div class="form-check p-2 rounded" style="background: ' . $bgColor . '; border: 1px solid #ddd;">';
        echo '<input class="form-check-input quiz-checkbox" type="checkbox" name="quizids[]" value="' . $quiz->id . '" id="quiz_' . $quiz->id . '" ' . $checked . '>';
        echo '<label class="form-check-label" for="quiz_' . $quiz->id . '">';
        echo '<strong>' . format_string($quiz->name) . '</strong><br>';
        echo '<small class="text-muted">' . $quiz->attempts . ' tentativo/i</small>';
        echo '</label>';
        echo '</div></div>';
    }
} else {
    echo '<div class="col-12"><div class="alert alert-info mb-0">Nessun quiz completato da questo studente.</div></div>';
}

echo '</div>'; // Fine row

// Pulsanti azione
echo '<div class="mt-3 d-flex justify-content-between align-items-center">';
echo '<div>';
echo '<button type="button" class="btn btn-sm btn-outline-primary mr-2" onclick="selectAllQuizzes()">✅ Seleziona tutti</button>';
echo '<button type="button" class="btn btn-sm btn-outline-secondary mr-2" onclick="deselectAllQuizzes()">☐ Deseleziona tutti</button>';
echo '</div>';
echo '<div>';

// Mostra quali quiz sono attualmente selezionati
if (!empty($selectedQuizzes) && count($selectedQuizzes) < count($availableQuizzes)) {
    $selectedNames = [];
    foreach ($availableQuizzes as $q) {
        if (in_array($q->id, $selectedQuizzes)) {
            $selectedNames[] = $q->name;
        }
    }
    echo '<span class="badge badge-info mr-2">' . count($selectedQuizzes) . '/' . count($availableQuizzes) . ' quiz selezionati</span>';
}

echo '<button type="submit" class="btn btn-primary">🔄 Aggiorna Report</button>';
echo '</div>';
echo '</div>';

echo '</form>';
echo '</div></div>';

// JavaScript per selezione
echo '<script>
function selectAllQuizzes() {
    document.querySelectorAll(".quiz-checkbox").forEach(cb => cb.checked = true);
}
function deselectAllQuizzes() {
    document.querySelectorAll(".quiz-checkbox").forEach(cb => cb.checked = false);
}
</script>';

// Progresso certificazione
echo '<div class="card mb-4">';
echo '<div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">';
echo '<h5 class="mb-0">📊 Progresso verso la Certificazione</h5></div>';
echo '<div class="card-body">';
$progressColor = $certProgress['percentage'] >= 80 ? '#28a745' : ($certProgress['percentage'] >= 50 ? '#ffc107' : '#dc3545');
echo '<div class="progress mb-3" style="height: 30px;">';
echo '<div class="progress-bar" style="width: ' . $certProgress['percentage'] . '%; background: ' . $progressColor . ';">';
echo '<span style="font-weight: bold;">' . $certProgress['percentage'] . '% completato</span></div></div>';
echo '<div class="row text-center">';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #d4edda;"><div style="font-size: 2rem; font-weight: bold; color: #28a745;">' . $certProgress['certified'] . '</div><small>✅ Certificate (≥80%)</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #fff3cd;"><div style="font-size: 2rem; font-weight: bold; color: #856404;">' . $certProgress['inProgress'] . '</div><small>🔄 In corso (1-79%)</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #f8d7da;"><div style="font-size: 2rem; font-weight: bold; color: #721c24;">' . $certProgress['notStarted'] . '</div><small>⏳ Da iniziare (0%)</small></div></div>';
echo '</div></div></div>';

// Toolbar export
echo '<div class="card mb-4"><div class="card-body">';
echo '<div class="d-flex justify-content-between align-items-center flex-wrap">';
echo '<div class="mb-2"><a href="' . new moodle_url('/local/competencyreport/index.php', ['courseid' => $courseid]) . '" class="btn btn-outline-secondary">← Torna alla lista</a></div>';
echo '<div class="mb-2">';
echo '<a href="' . new moodle_url('/local/competencyreport/student.php', ['userid' => $userid, 'courseid' => $courseid, 'print' => 1]) . '" class="btn btn-info mr-2" target="_blank">🖨️ Stampa</a>';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-success mr-2">📥 CSV</a>';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'excel']) . '" class="btn btn-success">📥 Excel</a>';
echo '</div></div></div></div>';

// TAB
$baseurl = new moodle_url('/local/competencyreport/student.php', ['userid' => $userid, 'courseid' => $courseid]);
// Aggiungi i quiz selezionati all'URL
$tabUrlParams = ['userid' => $userid, 'courseid' => $courseid];
foreach ($selectedQuizzes as $qid) {
    $tabUrlParams['quizids[]'] = $qid;
}

echo '<ul class="nav nav-tabs mb-4">';
$tabs = [
    'overview' => '📊 Panoramica',
    'action' => '📚 Piano d\'Azione', 
    'byquiz' => '📝 Per Quiz',
    'progress' => '📈 Progressi',
    'details' => '📋 Dettagli'
];
foreach ($tabs as $tabKey => $tabLabel) {
    $active = ($tab == $tabKey) ? 'active' : '';
    $url = new moodle_url('/local/competencyreport/student.php', array_merge($tabUrlParams, ['tab' => $tabKey]));
    echo '<li class="nav-item"><a class="nav-link ' . $active . '" href="' . $url . '">' . $tabLabel . '</a></li>';
}
echo '</ul>';

// ========================================
// CONTENUTO TAB
// ========================================
if (!empty($competencies)) {
    switch ($tab) {
        case 'overview':
        default:
            echo '<div class="row">';
            
            // Grafico AREE
            echo '<div class="col-lg-6 mb-4"><div class="card h-100">';
            echo '<div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">';
            echo '<h5 class="mb-0">🎯 Grafico per AREE</h5></div>';
            echo '<div class="card-body"><div class="text-center mb-3">';
            echo generate_area_radar_svg($areasData, $areaDescriptions, 380, 380);
            echo '</div>';
            
            // Legenda aree
            echo '<h6>📖 Legenda Aree</h6><div class="row">';
            foreach ($areasData as $area) {
                $band = get_evaluation_band($area['percentage']);
                echo '<div class="col-6 mb-2"><div class="d-flex align-items-center p-2 rounded" style="background: ' . $band['bgColor'] . ';">';
                echo '<span style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: ' . $area['color'] . '; color: white; border-radius: 50%; font-size: 10px; font-weight: bold; margin-right: 8px;">' . $area['code'] . '</span>';
                echo '<div style="flex: 1;"><div style="font-size: 12px; font-weight: bold;">' . $area['icon'] . ' ' . $area['name'] . '</div>';
                echo '<small class="text-muted">' . $area['competency_count'] . ' comp.</small></div>';
                echo '<span class="badge" style="background: ' . $band['color'] . '; color: white;">' . $area['percentage'] . '%</span>';
                echo '</div></div>';
            }
            echo '</div></div></div></div>';
            
            // Grafico COMPETENZE
            echo '<div class="col-lg-6 mb-4"><div class="card h-100">';
            echo '<div class="card-header text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">';
            echo '<h5 class="mb-0">🔍 Dettaglio Competenze per Area</h5></div>';
            echo '<div class="card-body">';
            
            // Selettore area
            echo '<form method="get" class="mb-3">';
            echo '<input type="hidden" name="userid" value="' . $userid . '">';
            echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
            echo '<input type="hidden" name="tab" value="overview">';
            foreach ($selectedQuizzes as $qid) echo '<input type="hidden" name="quizids[]" value="' . $qid . '">';
            echo '<div class="input-group"><div class="input-group-prepend"><span class="input-group-text">Area:</span></div>';
            echo '<select name="selectedarea" class="form-control" onchange="this.form.submit()"><option value="">-- Seleziona --</option>';
            foreach ($areasData as $areaCode => $area) {
                $sel = ($selectedArea == $areaCode) ? 'selected' : '';
                echo '<option value="' . $areaCode . '" ' . $sel . '>' . $area['icon'] . ' ' . $area['name'] . '</option>';
            }
            echo '</select></div></form>';
            
            if (!empty($filteredCompetencies)) {
                echo '<div class="text-center mb-3">' . generate_competency_radar_svg($filteredCompetencies, 350, 350) . '</div>';
                echo '<h6>📖 Competenze - ' . $areasData[$selectedArea]['name'] . '</h6>';
                $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
                $i = 0;
                foreach ($filteredCompetencies as $comp) {
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $band = get_evaluation_band($comp['percentage']);
                    echo '<div class="d-flex align-items-center mb-1 p-1" style="background: ' . $band['bgColor'] . '; border-radius: 4px;">';
                    echo '<span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: ' . $colors[$i % count($colors)] . '; color: white; border-radius: 50%; font-size: 11px; font-weight: bold; margin-right: 8px;">' . ($i + 1) . '</span>';
                    echo '<span style="flex: 1; font-size: 12px;">' . $code . '</span>';
                    echo '<span class="badge" style="background: ' . $band['color'] . '; color: white;">' . $comp['percentage'] . '%</span></div>';
                    $i++;
                }
            } else {
                echo '<div class="text-center p-5 text-muted"><div style="font-size: 4rem;">👆</div><p>Seleziona un\'area</p></div>';
            }
            echo '</div></div></div></div>';
            
            // Statistiche
            echo '<div class="row">';
            echo '<div class="col-md-4 mb-4"><div class="card h-100"><div class="card-header" style="background: #17a2b8; color: white;"><h5 class="mb-0">📈 Statistiche</h5></div>';
            echo '<div class="card-body"><div class="row text-center">';
            echo '<div class="col-4"><div style="font-size: 2rem; font-weight: bold; color: #667eea;">' . count($areasData) . '</div><small>Aree</small></div>';
            echo '<div class="col-4"><div style="font-size: 2rem; font-weight: bold; color: #28a745;">' . count($competencies) . '</div><small>Competenze</small></div>';
            echo '<div class="col-4"><div style="font-size: 2rem; font-weight: bold; color: #17a2b8;">' . ($summary['questions_total'] ?? $summary['total_questions']) . '</div><small>Domande</small></div>';
            echo '</div></div></div></div>';
            
            // Aree forti
            echo '<div class="col-md-4 mb-4"><div class="card h-100 border-success"><div class="card-header bg-success text-white"><h5 class="mb-0">💪 Aree Forti</h5></div><div class="card-body p-2">';
            $strongAreas = array_filter($areasData, fn($a) => $a['percentage'] >= 60);
            usort($strongAreas, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
            if (!empty($strongAreas)) {
                foreach (array_slice($strongAreas, 0, 3) as $area) {
                    echo '<div class="d-flex justify-content-between p-2 mb-1" style="background: #d4edda; border-radius: 4px;">';
                    echo '<span>' . $area['icon'] . ' ' . $area['name'] . '</span><span class="badge badge-success">' . $area['percentage'] . '%</span></div>';
                }
            } else { echo '<small class="text-muted">Nessuna ≥60%</small>'; }
            echo '</div></div></div>';
            
            // Aree deboli
            echo '<div class="col-md-4 mb-4"><div class="card h-100 border-danger"><div class="card-header bg-danger text-white"><h5 class="mb-0">📚 Da Migliorare</h5></div><div class="card-body p-2">';
            $weakAreas = array_filter($areasData, fn($a) => $a['percentage'] < 50);
            usort($weakAreas, fn($a, $b) => $a['percentage'] <=> $b['percentage']);
            if (!empty($weakAreas)) {
                foreach (array_slice($weakAreas, 0, 3) as $area) {
                    echo '<div class="d-flex justify-content-between p-2 mb-1" style="background: #f8d7da; border-radius: 4px;">';
                    echo '<span>' . $area['icon'] . ' ' . $area['name'] . '</span><span class="badge badge-danger">' . $area['percentage'] . '%</span></div>';
                }
            } else { echo '<small class="text-muted">Nessuna <50%</small>'; }
            echo '</div></div></div></div>';
            break;
            
        case 'action':
            echo '<div class="card mb-4"><div class="card-header text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"><h4 class="mb-0">📚 Piano d\'Azione</h4></div><div class="card-body">';
            
            if (!empty($actionPlan['critical'])) {
                echo '<div class="mb-4 p-3 rounded" style="background: #f8d7da; border-left: 5px solid #dc3545;"><h5 style="color: #dc3545;">🔴 PRIORITÀ ALTA</h5>';
                echo '<div class="table-responsive"><table class="table table-sm bg-white"><thead><tr><th>Competenza</th><th>Risultato</th></tr></thead><tbody>';
                foreach ($actionPlan['critical'] as $item) {
                    echo '<tr><td><strong>' . $item['name'] . '</strong><br><small>' . $item['code'] . '</small></td>';
                    echo '<td><span class="badge badge-danger">' . $item['percentage'] . '%</span></td></tr>';
                }
                echo '</tbody></table></div></div>';
            }
            
            if (!empty($actionPlan['toImprove'])) {
                echo '<div class="mb-4 p-3 rounded" style="background: #ffe5d0; border-left: 5px solid #fd7e14;"><h5 style="color: #fd7e14;">🟠 PRIORITÀ MEDIA</h5><div class="row">';
                foreach ($actionPlan['toImprove'] as $item) {
                    echo '<div class="col-md-6 mb-2"><div class="p-2 bg-white rounded d-flex justify-content-between">';
                    echo '<span>' . $item['name'] . '</span><span class="badge" style="background:#fd7e14;color:white;">' . $item['percentage'] . '%</span></div></div>';
                }
                echo '</div></div>';
            }
            
            if (!empty($actionPlan['acquired'])) {
                echo '<div class="mb-4 p-3 rounded" style="background: #d1f2eb; border-left: 5px solid #20c997;"><h5 style="color: #20c997;">🟢 ACQUISITE</h5><div class="row">';
                foreach ($actionPlan['acquired'] as $item) {
                    echo '<div class="col-md-6 mb-2"><div class="p-2 bg-white rounded d-flex justify-content-between">';
                    echo '<span>' . $item['name'] . '</span><span class="badge" style="background:#20c997;color:white;">' . $item['percentage'] . '%</span></div></div>';
                }
                echo '</div></div>';
            }
            
            if (!empty($actionPlan['excellent'])) {
                echo '<div class="mb-4 p-3 rounded" style="background: #d4edda; border-left: 5px solid #28a745;"><h5 style="color: #28a745;">🌟 ECCELLENZA</h5><div class="row">';
                foreach ($actionPlan['excellent'] as $item) {
                    echo '<div class="col-md-6 mb-2"><div class="p-2 bg-white rounded d-flex justify-content-between">';
                    echo '<span>' . $item['name'] . '</span><span class="badge badge-success">' . $item['percentage'] . '%</span></div></div>';
                }
                echo '</div></div>';
            }
            echo '</div></div>';
            break;
            
        case 'byquiz':
            echo '<div class="row">';
            foreach ($quizComparison as $quiz) {
                echo '<div class="col-lg-6 mb-4"><div class="card h-100">';
                echo '<div class="card-header text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"><h5 class="mb-0">📝 ' . format_string($quiz['name']) . '</h5></div>';
                echo '<div class="card-body">';
                if (!empty($quiz['attempts'])) {
                    echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Data</th><th>Risultato</th></tr></thead><tbody>';
                    foreach ($quiz['attempts'] as $att) {
                        $band = get_evaluation_band($att['percentage']);
                        echo '<tr><td>' . $att['attempt_number'] . '</td><td><small>' . $att['date'] . '</small></td>';
                        echo '<td><span class="badge" style="background:' . $band['color'] . ';color:white;">' . $att['percentage'] . '%</span></td></tr>';
                    }
                    echo '</tbody></table></div>';
                }
                echo '</div></div></div>';
            }
            if (empty($quizComparison)) echo '<div class="col-12"><div class="alert alert-info">Nessun quiz.</div></div>';
            echo '</div>';
            break;
            
        case 'progress':
            echo '<div class="card"><div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><h4 class="mb-0">📈 Progressi</h4></div>';
            echo '<div class="card-body">';
            if (!empty($progressData)) {
                echo '<table class="table"><thead><tr><th>Data</th><th>Quiz</th><th>Risultato</th><th>Valutazione</th></tr></thead><tbody>';
                foreach (array_reverse($progressData) as $item) {
                    $band = get_evaluation_band($item['percentage']);
                    echo '<tr><td>' . $item['date'] . '</td><td>' . $item['quiz'] . '</td>';
                    echo '<td><span class="badge" style="background:' . $band['color'] . ';color:white;">' . $item['percentage'] . '%</span></td>';
                    echo '<td style="color:' . $band['color'] . ';">' . $band['icon'] . ' ' . $band['label'] . '</td></tr>';
                }
                echo '</tbody></table>';
            } else { echo '<p class="text-muted">Nessun dato.</p>'; }
            echo '</div></div>';
            break;
            
        case 'details':
            $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
            echo '<div class="card"><div class="card-header text-white" style="background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);"><h4 class="mb-0">📋 Dettaglio</h4></div>';
            echo '<div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">';
            echo '<thead style="background:#f8f9fa;"><tr><th>#</th><th>Area</th><th>Codice</th><th>Competenza</th><th class="text-center">Risposte</th><th class="text-center">%</th><th>Valutazione</th></tr></thead><tbody>';
            $i = 0;
            foreach ($competencies as $comp) {
                $code = $comp['idnumber'] ?: $comp['name'];
                $parts = explode('_', $code);
                $areaCode = count($parts) >= 2 ? $parts[1] : '';
                $areaInfo = $areaDescriptions[$areaCode] ?? ['name' => $areaCode, 'icon' => '📊', 'color' => '#6c757d'];
                $info = $competencyDescriptions[$code] ?? ['name' => $code];
                $band = get_evaluation_band($comp['percentage']);
                
                echo '<tr style="background:' . $band['bgColor'] . ';">';
                echo '<td><span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:' . $colors[$i % count($colors)] . ';color:white;border-radius:50%;font-weight:bold;font-size:12px;">' . ($i + 1) . '</span></td>';
                echo '<td><span class="badge" style="background:' . $areaInfo['color'] . ';color:white;">' . $areaInfo['icon'] . ' ' . $areaCode . '</span></td>';
                echo '<td><small>' . $code . '</small></td><td><strong>' . $info['name'] . '</strong></td>';
                echo '<td class="text-center">' . $comp['correct_questions'] . '/' . $comp['total_questions'] . '</td>';
                echo '<td class="text-center"><span class="badge" style="background:' . $band['color'] . ';color:white;">' . $comp['percentage'] . '%</span></td>';
                echo '<td style="color:' . $band['color'] . ';font-weight:bold;">' . $band['icon'] . ' ' . $band['label'] . '</td></tr>';
                $i++;
            }
            echo '</tbody></table></div></div></div>';
            break;
    }
} else {
    echo '<div class="alert alert-warning">Nessun dato disponibile.</div>';
}

echo $OUTPUT->footer();
