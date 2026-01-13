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
 * Integrated reports page with area-based radar charts - v2.2
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/api.php');

use local_labeval\api;

// Require login
require_login();
$context = context_system::instance();
require_capability('local/labeval:view', $context);

// Parameters
$studentid = optional_param('studentid', 0, PARAM_INT);
$generate = optional_param('generate', 0, PARAM_INT);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/reports.php', ['studentid' => $studentid]));
$PAGE->set_title(get_string('reports', 'local_labeval'));
$PAGE->set_heading(get_string('integratedreport', 'local_labeval'));
$PAGE->set_pagelayout('standard');

$canviewall = has_capability('local/labeval:viewallreports', $context);
$canauthorize = has_capability('local/labeval:authorizestudents', $context);

// Area names mapping - NOMI COMPLETI E CHIARI
$areaNames = [
    'MIS' => 'Misure e Controlli',
    'DT' => 'Disegno Tecnico',
    'PIAN' => 'Pianificazione Lavoro',
    'SAQ' => 'Sicurezza e Qualità',
    'CSP' => 'Competenze Trasversali',
    'CNC' => 'Controllo Numerico CNC',
    'LMB' => 'Lavorazioni Base',
    'LMC' => 'Lavorazioni CNC',
    'ASS' => 'Assemblaggio Meccanico',
    'GEN' => 'Competenze Generali',
    'AUT' => 'Automazione Industriale',
    'MANUT' => 'Manutenzione',
];

// Get students list
$students = [];
if ($canviewall) {
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
            FROM {user} u
            JOIN {local_labeval_assignments} a ON a.studentid = u.id
            JOIN {local_labeval_sessions} s ON s.assignmentid = a.id
            WHERE s.status = 'completed'
            ORDER BY u.lastname, u.firstname";
    $students = $DB->get_records_sql($sql);
}

// Get selected student info
$student = null;
$evaluations = [];
$labevalscores = [];
$quizscores = [];
$selfassessscores = [];

if ($studentid) {
    $student = $DB->get_record('user', ['id' => $studentid]);
    if ($student) {
        // Get lab evaluation scores
        $labevalscores = api::get_student_competency_scores($studentid);
        
        // Get completed evaluations
        $evaluations = api::get_student_evaluations($studentid, true);
        
        // Get quiz scores from question tags
        $sql = "SELECT 
                    UPPER(t.rawname) as competencycode,
                    ROUND(AVG(qas.fraction) * 100, 2) as percentage
                FROM {quiz_attempts} qza
                JOIN {question_attempts} qa ON qa.questionusageid = qza.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id 
                    AND qas.sequencenumber = (
                        SELECT MAX(qas2.sequencenumber) 
                        FROM {question_attempt_steps} qas2 
                        WHERE qas2.questionattemptid = qa.id
                    )
                JOIN {question} q ON q.id = qa.questionid
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {tag_instance} ti ON ti.itemid = qbe.id AND ti.itemtype = 'question'
                JOIN {tag} t ON t.id = ti.tagid
                WHERE qza.userid = ? 
                  AND qza.state = 'finished'
                  AND UPPER(t.rawname) LIKE 'MECCANICA_%'
                GROUP BY t.rawname
                ORDER BY t.rawname";
        
        $quizrecords = $DB->get_records_sql($sql, [$studentid]);
        foreach ($quizrecords as $r) {
            if (!empty($r->competencycode)) {
                $quizscores[$r->competencycode] = round($r->percentage, 2);
            }
        }
        
        // Get self-assessment scores
        if ($DB->get_manager()->table_exists('local_selfassessment')) {
            $sql = "SELECT c.idnumber as competencycode, AVG(sa.level) * 16.67 as percentage
                    FROM {local_selfassessment} sa
                    JOIN {competency} c ON c.id = sa.competencyid
                    WHERE sa.userid = ? AND c.idnumber IS NOT NULL AND c.idnumber != ''
                    GROUP BY c.idnumber";
            $selfrecords = $DB->get_records_sql($sql, [$studentid]);
            foreach ($selfrecords as $r) {
                if (!empty($r->competencycode)) {
                    $selfassessscores[$r->competencycode] = round($r->percentage, 2);
                }
            }
        }
        
        if (empty($selfassessscores) && $DB->get_manager()->table_exists('local_coachmanager_self_assess')) {
            $sql = "SELECT c.idnumber as competencycode, AVG(sa.bloomlevel) * 16.67 as percentage
                    FROM {local_coachmanager_self_assess} sa
                    JOIN {competency} c ON c.id = sa.competencyid
                    WHERE sa.studentid = ? AND c.idnumber IS NOT NULL AND c.idnumber != ''
                    GROUP BY c.idnumber";
            $selfrecords = $DB->get_records_sql($sql, [$studentid]);
            foreach ($selfrecords as $r) {
                if (!empty($r->competencycode)) {
                    $selfassessscores[$r->competencycode] = round($r->percentage, 2);
                }
            }
        }
    }
}

// Build combined competency list
$allcompetencies = [];
foreach ($labevalscores as $code => $data) {
    $allcompetencies[$code] = true;
}
foreach ($quizscores as $code => $val) {
    $allcompetencies[$code] = true;
}
foreach ($selfassessscores as $code => $val) {
    $allcompetencies[$code] = true;
}
ksort($allcompetencies);

// Build competency info
$competencyinfo = [];
foreach ($allcompetencies as $code => $dummy) {
    $competencyinfo[$code] = local_labeval_get_competency_info($code);
}

// CALCOLA MEDIE PER AREA
function calculateAreaAverages($scores, $areaNames) {
    $areaScores = [];
    $areaCounts = [];
    
    foreach ($scores as $code => $value) {
        $parts = explode('_', $code);
        $areaCode = count($parts) >= 2 ? $parts[1] : '';
        if (empty($areaCode)) continue;
        
        $val = is_array($value) ? ($value['percentage'] ?? 0) : $value;
        
        if (!isset($areaScores[$areaCode])) {
            $areaScores[$areaCode] = 0;
            $areaCounts[$areaCode] = 0;
        }
        $areaScores[$areaCode] += $val;
        $areaCounts[$areaCode]++;
    }
    
    $result = [];
    foreach ($areaScores as $areaCode => $total) {
        if ($areaCounts[$areaCode] > 0) {
            $result[$areaCode] = round($total / $areaCounts[$areaCode], 2);
        }
    }
    
    // Ordina per nome area
    uksort($result, function($a, $b) use ($areaNames) {
        $nameA = $areaNames[$a] ?? $a;
        $nameB = $areaNames[$b] ?? $b;
        return strcmp($nameA, $nameB);
    });
    
    return $result;
}

// Calcola medie per area
$quizAreaAvg = calculateAreaAverages($quizscores, $areaNames);
$selfAreaAvg = calculateAreaAverages($selfassessscores, $areaNames);
$labAreaAvg = calculateAreaAverages($labevalscores, $areaNames);

// Tutte le aree presenti
$allAreas = array_unique(array_merge(
    array_keys($quizAreaAvg),
    array_keys($selfAreaAvg),
    array_keys($labAreaAvg)
));
sort($allAreas);

// Output
echo $OUTPUT->header();

// Navigation tabs (wrapped in no-print for print)
echo '<div class="no-print">';
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/labeval/index.php'), get_string('dashboard', 'local_labeval')),
    new tabobject('templates', new moodle_url('/local/labeval/templates.php'), get_string('templates', 'local_labeval')),
    new tabobject('assignments', new moodle_url('/local/labeval/assignments.php'), get_string('assignments', 'local_labeval')),
    new tabobject('reports', new moodle_url('/local/labeval/reports.php'), get_string('reports', 'local_labeval')),
];
echo $OUTPUT->tabtree($tabs, 'reports');
echo '</div>';

echo local_labeval_get_common_styles();
?>

<style>
/* === LAYOUT === */
.report-container {
    max-width: 1600px;
    margin: 0 auto;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #1e7e34, #28a745);
    color: white;
    padding: 20px;
}

.card-header h3 {
    margin: 0;
    font-size: 20px;
}

.card-body {
    padding: 25px;
}

/* === FILTRO AREA === */
.area-filter {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.area-filter label {
    font-weight: 600;
    color: #333;
}

.area-filter select {
    padding: 10px 15px;
    font-size: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    min-width: 250px;
}

/* === 3 RADAR IN RIGA === */
.three-radars {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.single-radar-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.single-radar-header {
    padding: 15px 20px;
    color: white;
    font-weight: 600;
    font-size: 18px;
    text-align: center;
}

.single-radar-header.quiz { background: linear-gradient(135deg, #2196F3, #36a2eb); }
.single-radar-header.selfassess { background: linear-gradient(135deg, #00897B, #4bc0c0); }
.single-radar-header.labeval { background: linear-gradient(135deg, #F57C00, #ff9f40); }

.single-radar-body {
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 420px;
}

.single-radar-canvas {
    width: 100%;
    max-width: 400px;
    height: 400px;
}

.no-data-message {
    text-align: center;
    padding: 100px 20px;
    color: #999;
    font-size: 16px;
}

/* === RADAR CONFRONTO === */
.comparison-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 30px;
    overflow: hidden;
}

.comparison-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
}

.comparison-header h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
}

.comparison-controls {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.comparison-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
}

.comparison-checkbox input {
    width: 18px;
    height: 18px;
}

.comparison-checkbox.quiz { border-left: 4px solid #36a2eb; }
.comparison-checkbox.selfassess { border-left: 4px solid #4bc0c0; }
.comparison-checkbox.labeval { border-left: 4px solid #ff9f40; }

.comparison-area-filter {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

.comparison-area-filter select {
    padding: 8px 12px;
    border-radius: 6px;
    border: none;
    font-size: 14px;
}

.comparison-body {
    padding: 30px;
    display: flex;
    justify-content: center;
}

.comparison-radar-container {
    width: 700px;
    height: 700px;
}

/* === LEGENDA === */
.radar-legend-inline {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.legend-item-inline {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.legend-dot {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

/* === STAMPA === */
.print-section {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    border-radius: 12px;
    padding: 25px;
    margin: 25px 0;
}

.print-section h4 {
    margin: 0 0 20px 0;
    color: #2e7d32;
    font-size: 18px;
}

.print-options {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.print-option-group {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    min-width: 200px;
}

.print-option-group h5 {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}

.print-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    cursor: pointer;
    font-size: 14px;
}

.print-checkbox input {
    width: 18px;
    height: 18px;
}

.print-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-print {
    padding: 14px 28px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-print.primary { background: linear-gradient(135deg, #667eea, #764ba2); }
.btn-print.success { background: linear-gradient(135deg, #28a745, #20c997); }
.btn-print.info { background: linear-gradient(135deg, #17a2b8, #138496); }

/* === TABELLA === */
.detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.detail-table th {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 14px 12px;
    text-align: center;
    font-weight: 600;
    font-size: 13px;
}

.detail-table th:first-child {
    text-align: left;
    min-width: 180px;
}

.detail-table th:nth-child(2) {
    text-align: left;
    min-width: 200px;
}

.detail-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.detail-table tr:hover {
    background: #f8f9fa;
}

.detail-table .area-cell {
    text-align: left;
    font-weight: 600;
    color: #667eea;
    font-size: 13px;
}

.detail-table .comp-cell {
    text-align: left;
}

.detail-table .comp-code {
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.detail-table .comp-desc {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}

.detail-table .score-cell {
    text-align: center;
}

.score-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    min-width: 50px;
}

.score-badge.green { background: #d4edda; color: #155724; }
.score-badge.yellow { background: #fff3cd; color: #856404; }
.score-badge.red { background: #f8d7da; color: #721c24; }
.score-badge.none { background: #f1f1f1; color: #999; }

.gap-value {
    font-weight: 600;
    font-size: 13px;
}
.gap-value.positive { color: #28a745; }
.gap-value.neutral { color: #ffc107; }
.gap-value.negative { color: #dc3545; }

/* === HEADER E FOOTER STAMPA === */
.print-header {
    display: none;
}

.print-footer {
    display: none;
}

/* === PRINT STYLES === */
@media print {
    /* Nascondi elementi Moodle e navigazione */
    .no-print,
    .area-filter,
    .print-section,
    .comparison-controls,
    header,
    footer,
    nav,
    .navbar,
    #page-header,
    #page-footer,
    .drawer,
    .usermenu,
    .breadcrumb,
    [role="navigation"],
    .secondary-navigation,
    .activity-navigation,
    #nav-drawer,
    .tabtree,
    .nav-tabs,
    ul.nav-tabs,
    ul.tabrow,
    .tabrow,
    div.tabtree,
    .card-header select,
    #region-main-settings-menu,
    .action-menu,
    #user-notifications,
    .block,
    #block-region-side-pre,
    #block-region-side-post,
    aside {
        display: none !important;
    }
    
    /* MOSTRA header e footer stampa */
    .print-header {
        display: block !important;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #333;
    }
    
    .print-header-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .print-logo {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    
    .print-student-info {
        font-size: 12px;
        line-height: 1.6;
    }
    
    .print-date {
        font-size: 12px;
        text-align: right;
    }
    
    .print-footer {
        display: block !important;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ccc;
        page-break-inside: avoid;
    }
    
    .print-footer-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 30px;
    }
    
    .signature-box {
        flex: 1;
        text-align: center;
    }
    
    .signature-line {
        border-top: 1px solid #333;
        margin-top: 50px;
        padding-top: 5px;
        font-size: 11px;
    }
    
    /* Reset layout */
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    #page, #page-content, .report-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 10px !important;
    }
    
    /* Cards */
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
        margin-bottom: 15px !important;
    }
    
    /* Nascondi intestazione studente originale se non selezionata */
    #section_header {
        display: none !important;
    }
    
    /* 3 Radar in riga */
    .three-radars {
        display: flex !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
        page-break-inside: avoid;
    }
    
    .single-radar-card {
        flex: 1 !important;
        min-width: 0 !important;
    }
    
    .single-radar-body {
        min-height: auto !important;
        padding: 10px !important;
    }
    
    /* Immagini radar per stampa */
    .print-radar-img {
        max-width: 100% !important;
        height: auto !important;
    }
    
    .single-radar-canvas,
    #radarQuiz,
    #radarSelfassess,
    #radarLabeval {
        max-width: 250px !important;
        max-height: 250px !important;
    }
    
    /* Radar confronto */
    .comparison-section {
        page-break-before: always;
    }
    
    .comparison-header {
        padding: 10px 15px !important;
    }
    
    .comparison-body {
        padding: 15px !important;
    }
    
    .comparison-radar-container,
    #radarComparison {
        width: 400px !important;
        height: 400px !important;
        margin: 0 auto !important;
    }
    
    /* Tabella */
    .detail-table {
        font-size: 10px !important;
    }
    
    .detail-table th,
    .detail-table td {
        padding: 6px 4px !important;
    }
    
    /* Nascondi sezioni se richiesto */
    .hide-on-print {
        display: none !important;
    }
    
    /* Colori per stampa */
    .single-radar-header,
    .card-header,
    .comparison-header {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    
    .score-badge {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Legenda sotto radar confronto */
    .radar-legend-inline {
        margin: 10px 0 !important;
    }
}

@media (max-width: 1200px) {
    .three-radars { grid-template-columns: 1fr; }
    .comparison-radar-container { width: 100%; height: 500px; }
}
</style>

<div class="report-container">
    
    <!-- Selezione Studente -->
    <div class="card no-print">
        <div class="card-header">
            <h3>📊 Report Integrato Competenze</h3>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo new moodle_url('/local/labeval/reports.php'); ?>">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <label style="font-weight: 600; font-size: 16px;">👤 Seleziona Studente:</label>
                    <select name="studentid" style="flex: 1; min-width: 300px; padding: 12px; font-size: 15px; border: 2px solid #ddd; border-radius: 8px;" onchange="this.form.submit()">
                        <option value="">-- Seleziona uno studente --</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php echo $studentid == $s->id ? 'selected' : ''; ?>>
                            <?php echo $s->firstname . ' ' . $s->lastname; ?> (<?php echo $s->email; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="generate" value="1">
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($student && !empty($allcompetencies)): ?>
    
    <!-- Info Studente -->
    <div class="card" id="section_header">
        <div class="card-body" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h2 style="margin: 0; font-size: 26px;">👤 <?php echo $student->firstname . ' ' . $student->lastname; ?></h2>
                    <p style="margin: 8px 0 0; opacity: 0.9;"><?php echo $student->email; ?></p>
                </div>
                <div style="display: flex; gap: 15px;">
                    <div style="background: rgba(255,255,255,0.2); padding: 15px 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700;"><?php echo count($quizscores); ?></div>
                        <div style="font-size: 12px;">Quiz</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.2); padding: 15px 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700;"><?php echo count($selfassessscores); ?></div>
                        <div style="font-size: 12px;">Autovalut.</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.2); padding: 15px 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700;"><?php echo count($labevalscores); ?></div>
                        <div style="font-size: 12px;">Prove Prat.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Header per stampa (visibile solo in stampa) -->
    <div class="print-header" id="printHeader">
        <div class="print-header-content">
            <div class="print-logo">📊 Report Competenze FTM</div>
            <div class="print-student-info">
                <strong>Studente:</strong> <?php echo $student->firstname . ' ' . $student->lastname; ?><br>
                <strong>Email:</strong> <?php echo $student->email; ?>
            </div>
            <div class="print-date">
                <strong>Data:</strong> <?php echo date('d/m/Y'); ?>
            </div>
        </div>
    </div>
    
    <!-- Filtro Area -->
    <div class="area-filter no-print">
        <label>🔍 Visualizza:</label>
        <select id="areaFilter">
            <option value="areas">📊 Riepilogo per Aree (medie)</option>
            <option value="all">📋 Tutte le Competenze</option>
            <?php foreach ($allAreas as $areaCode): 
                $areaName = $areaNames[$areaCode] ?? $areaCode;
            ?>
            <option value="<?php echo $areaCode; ?>">🎯 Dettaglio: <?php echo $areaName; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- 3 RADAR SEPARATI -->
    <div class="three-radars" id="section_radars">
        
        <!-- Radar Quiz -->
        <div class="single-radar-card">
            <div class="single-radar-header quiz">📝 Quiz (Teoria)</div>
            <div class="single-radar-body">
                <?php if (!empty($quizscores)): ?>
                <canvas id="radarQuiz" class="single-radar-canvas"></canvas>
                <?php else: ?>
                <div class="no-data-message">Nessun dato Quiz disponibile</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Radar Autovalutazione -->
        <div class="single-radar-card">
            <div class="single-radar-header selfassess">🎯 Autovalutazione (Bloom)</div>
            <div class="single-radar-body">
                <?php if (!empty($selfassessscores)): ?>
                <canvas id="radarSelfassess" class="single-radar-canvas"></canvas>
                <?php else: ?>
                <div class="no-data-message">Nessun dato Autovalutazione disponibile</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Radar Prove Pratiche -->
        <div class="single-radar-card">
            <div class="single-radar-header labeval">🔬 Prove Pratiche</div>
            <div class="single-radar-body">
                <?php if (!empty($labevalscores)): ?>
                <canvas id="radarLabeval" class="single-radar-canvas"></canvas>
                <?php else: ?>
                <div class="no-data-message">Nessun dato Prove Pratiche disponibile</div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- RADAR CONFRONTO -->
    <div class="comparison-section" id="section_comparison">
        <div class="comparison-header">
            <h3>📊 Radar di Confronto</h3>
            <div class="comparison-controls">
                <label class="comparison-checkbox quiz">
                    <input type="checkbox" id="compare_quiz" checked <?php echo empty($quizscores) ? 'disabled' : ''; ?>>
                    <span>📝 Quiz</span>
                </label>
                <label class="comparison-checkbox selfassess">
                    <input type="checkbox" id="compare_selfassess" checked <?php echo empty($selfassessscores) ? 'disabled' : ''; ?>>
                    <span>🎯 Autovalutazione</span>
                </label>
                <label class="comparison-checkbox labeval">
                    <input type="checkbox" id="compare_labeval" checked <?php echo empty($labevalscores) ? 'disabled' : ''; ?>>
                    <span>🔬 Prove Pratiche</span>
                </label>
                
                <div class="comparison-area-filter">
                    <label style="color: rgba(255,255,255,0.8);">Filtra:</label>
                    <select id="comparisonAreaFilter">
                        <option value="areas">Riepilogo Aree</option>
                        <option value="all">Tutte le Competenze</option>
                        <?php foreach ($allAreas as $areaCode): 
                            $areaName = $areaNames[$areaCode] ?? $areaCode;
                        ?>
                        <option value="<?php echo $areaCode; ?>"><?php echo $areaName; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="comparison-body">
            <div class="comparison-radar-container">
                <canvas id="radarComparison"></canvas>
            </div>
        </div>
        <div class="radar-legend-inline">
            <div class="legend-item-inline" id="legend_quiz">
                <span class="legend-dot" style="background: #36a2eb;"></span>
                <span>Quiz (Teoria)</span>
            </div>
            <div class="legend-item-inline" id="legend_selfassess">
                <span class="legend-dot" style="background: #4bc0c0;"></span>
                <span>Autovalutazione</span>
            </div>
            <div class="legend-item-inline" id="legend_labeval">
                <span class="legend-dot" style="background: #ff9f40;"></span>
                <span>Prove Pratiche</span>
            </div>
        </div>
    </div>
    
    <!-- TABELLA DETTAGLI -->
    <div class="card" id="section_table">
        <div class="card-header">
            <h3>📋 Dettaglio Competenze</h3>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Competenza</th>
                        <th>📝 Quiz</th>
                        <th>🎯 Auto</th>
                        <th>🔬 Pratica</th>
                        <th>Gap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allcompetencies as $code => $dummy): 
                        $quiz = $quizscores[$code] ?? null;
                        $self = $selfassessscores[$code] ?? null;
                        $lab = isset($labevalscores[$code]) ? $labevalscores[$code]['percentage'] : null;
                        $info = $competencyinfo[$code] ?? ['area' => '', 'description' => ''];
                        
                        // Get area code for filtering
                        $parts = explode('_', $code);
                        $areaCode = count($parts) >= 2 ? $parts[1] : '';
                        $areaFullName = $areaNames[$areaCode] ?? $info['area'];
                        
                        // Calculate gap
                        $values = array_filter([$quiz, $self, $lab], function($v) { return $v !== null; });
                        $gap = null;
                        $gapclass = 'neutral';
                        if (count($values) >= 2) {
                            $gap = max($values) - min($values);
                            $gapclass = $gap > 15 ? 'negative' : ($gap < 5 ? 'positive' : 'neutral');
                        }
                    ?>
                    <tr data-area="<?php echo $areaCode; ?>">
                        <td class="area-cell"><?php echo $areaFullName; ?></td>
                        <td class="comp-cell">
                            <div class="comp-code"><?php echo $code; ?></div>
                            <?php if (!empty($info['description'])): ?>
                            <div class="comp-desc"><?php echo $info['description']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell">
                            <?php if ($quiz !== null): 
                                $class = $quiz >= 70 ? 'green' : ($quiz >= 50 ? 'yellow' : 'red');
                            ?>
                            <span class="score-badge <?php echo $class; ?>"><?php echo round($quiz); ?>%</span>
                            <?php else: ?>
                            <span class="score-badge none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell">
                            <?php if ($self !== null): 
                                $class = $self >= 70 ? 'green' : ($self >= 50 ? 'yellow' : 'red');
                            ?>
                            <span class="score-badge <?php echo $class; ?>"><?php echo round($self); ?>%</span>
                            <?php else: ?>
                            <span class="score-badge none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell">
                            <?php if ($lab !== null): 
                                $class = $lab >= 70 ? 'green' : ($lab >= 50 ? 'yellow' : 'red');
                            ?>
                            <span class="score-badge <?php echo $class; ?>"><?php echo round($lab); ?>%</span>
                            <?php else: ?>
                            <span class="score-badge none">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell">
                            <?php if ($gap !== null): ?>
                            <span class="gap-value <?php echo $gapclass; ?>">
                                <?php echo $gapclass == 'positive' ? '✓ ' : ($gapclass == 'negative' ? '⚠️ ' : ''); ?>
                                <?php echo round($gap); ?>%
                            </span>
                            <?php else: ?>
                            <span class="score-badge none">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Footer per stampa (visibile solo in stampa) -->
    <div class="print-footer" id="printFooter">
        <div class="print-footer-content">
            <div class="signature-box">
                <div class="signature-line">Firma Studente</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Firma Coach</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Data: <?php echo date('d/m/Y'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- OPZIONI STAMPA -->
    <div class="print-section no-print">
        <h4>🖨️ Opzioni di Stampa</h4>
        <div class="print-options">
            <div class="print-option-group">
                <h5>Sezioni da stampare</h5>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_header" checked>
                    <span>Intestazione studente</span>
                </label>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_radars" checked>
                    <span>3 Radar separati</span>
                </label>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_comparison" checked>
                    <span>Radar confronto</span>
                </label>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_table" checked>
                    <span>Tabella dettagli</span>
                </label>
            </div>
            <div class="print-option-group">
                <h5>Fonti dati</h5>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_quiz" checked>
                    <span>📝 Quiz (Teoria)</span>
                </label>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_selfassess" checked>
                    <span>🎯 Autovalutazione</span>
                </label>
                <label class="print-checkbox">
                    <input type="checkbox" id="print_labeval" checked>
                    <span>🔬 Prove Pratiche</span>
                </label>
            </div>
        </div>
        <div class="print-buttons">
            <button class="btn-print primary" onclick="printReport()">
                📄 Stampa Selezione
            </button>
            <button class="btn-print success" onclick="printAll()">
                📋 Stampa Tutto
            </button>
            <button class="btn-print info" onclick="printTableOnly()">
                📊 Solo Tabella
            </button>
        </div>
    </div>
    
    <?php endif; // student ?>
    
</div>

<?php if ($student && !empty($allcompetencies)): ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Area names
    var areaNames = <?php echo json_encode($areaNames); ?>;
    
    // Full competency codes
    var fullCodes = <?php echo json_encode(array_keys($allcompetencies)); ?>;
    
    // All data - by competency
    var allQuizData = <?php echo json_encode($quizscores); ?>;
    var allSelfData = <?php echo json_encode($selfassessscores); ?>;
    var allLabData = {};
    <?php foreach ($labevalscores as $code => $data): ?>
    allLabData['<?php echo $code; ?>'] = <?php echo $data['percentage']; ?>;
    <?php endforeach; ?>
    
    // Area averages
    var quizAreaAvg = <?php echo json_encode($quizAreaAvg); ?>;
    var selfAreaAvg = <?php echo json_encode($selfAreaAvg); ?>;
    var labAreaAvg = <?php echo json_encode($labAreaAvg); ?>;
    var allAreas = <?php echo json_encode($allAreas); ?>;
    
    // Charts storage
    var charts = {
        quiz: null,
        selfassess: null,
        labeval: null,
        comparison: null
    };
    
    // Common options - SENZA GRASSETTO
    function getRadarOptions(fontSize) {
        return {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    min: 0,
                    ticks: {
                        stepSize: 20,
                        font: { size: fontSize - 2, weight: 'normal' },
                        backdropColor: 'transparent'
                    },
                    pointLabels: {
                        font: { size: fontSize, weight: 'normal' },
                        color: '#333'
                    },
                    grid: { color: 'rgba(0,0,0,0.1)' },
                    angleLines: { color: 'rgba(0,0,0,0.1)' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var val = context.raw;
                            if (val === null) return 'Nessun dato';
                            return Math.round(val) + '%';
                        }
                    }
                }
            }
        };
    }
    
    // Get data for specific view (areas, all, or specific area)
    function getDataForView(viewType, sourceData, isLabData) {
        var labels = [];
        var data = [];
        
        if (viewType === 'areas') {
            // Show area averages
            var areaAvg = isLabData ? labAreaAvg : (sourceData === allQuizData ? quizAreaAvg : selfAreaAvg);
            allAreas.forEach(function(areaCode) {
                labels.push(areaNames[areaCode] || areaCode);
                data.push(areaAvg[areaCode] || null);
            });
        } else if (viewType === 'all') {
            // Show all competencies
            fullCodes.forEach(function(code) {
                var parts = code.split('_');
                var areaCode = parts.length >= 2 ? parts[1] : '';
                var num = parts[parts.length - 1];
                labels.push((areaNames[areaCode] || areaCode) + ' ' + num);
                if (isLabData) {
                    data.push(allLabData[code] || null);
                } else {
                    data.push(sourceData[code] || null);
                }
            });
        } else {
            // Show specific area
            fullCodes.forEach(function(code) {
                var parts = code.split('_');
                var codeArea = parts.length >= 2 ? parts[1] : '';
                if (codeArea === viewType) {
                    var num = parts[parts.length - 1];
                    labels.push((areaNames[codeArea] || codeArea) + ' ' + num);
                    if (isLabData) {
                        data.push(allLabData[code] || null);
                    } else {
                        data.push(sourceData[code] || null);
                    }
                }
            });
        }
        
        return { labels: labels, data: data };
    }
    
    // Create or update chart
    function createChart(canvasId, color, sourceData, isLabData, viewType) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        
        var viewData = getDataForView(viewType, sourceData, isLabData);
        
        // Destroy existing
        if (charts[canvasId.replace('radar', '').toLowerCase()]) {
            charts[canvasId.replace('radar', '').toLowerCase()].destroy();
        }
        
        var chart = new Chart(canvas, {
            type: 'radar',
            data: {
                labels: viewData.labels,
                datasets: [{
                    data: viewData.data,
                    borderColor: color,
                    backgroundColor: color.replace(')', ', 0.2)').replace('rgb', 'rgba'),
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: color,
                    spanGaps: true
                }]
            },
            options: getRadarOptions(11)
        });
        
        return chart;
    }
    
    // Create comparison chart
    function createComparisonChart(viewType) {
        var canvas = document.getElementById('radarComparison');
        if (!canvas) return;
        
        var quizView = getDataForView(viewType, allQuizData, false);
        var selfView = getDataForView(viewType, allSelfData, false);
        var labView = getDataForView(viewType, null, true);
        
        // Use labels from the one with most data
        var labels = quizView.labels.length >= selfView.labels.length ? 
                     (quizView.labels.length >= labView.labels.length ? quizView.labels : labView.labels) :
                     (selfView.labels.length >= labView.labels.length ? selfView.labels : labView.labels);
        
        if (charts.comparison) {
            charts.comparison.destroy();
        }
        
        charts.comparison = new Chart(canvas, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Quiz',
                        data: quizView.data,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgb(54, 162, 235)',
                        spanGaps: true,
                        hidden: false
                    },
                    {
                        label: 'Autovalutazione',
                        data: selfView.data,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgb(75, 192, 192)',
                        spanGaps: true,
                        hidden: false
                    },
                    {
                        label: 'Prove Pratiche',
                        data: labView.data,
                        borderColor: 'rgb(255, 159, 64)',
                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgb(255, 159, 64)',
                        spanGaps: true,
                        hidden: false
                    }
                ]
            },
            options: getRadarOptions(12)
        });
    }
    
    // Initialize charts with area view
    var currentView = 'areas';
    
    <?php if (!empty($quizscores)): ?>
    charts.quiz = createChart('radarQuiz', 'rgb(54, 162, 235)', allQuizData, false, currentView);
    <?php endif; ?>
    
    <?php if (!empty($selfassessscores)): ?>
    charts.selfassess = createChart('radarSelfassess', 'rgb(75, 192, 192)', allSelfData, false, currentView);
    <?php endif; ?>
    
    <?php if (!empty($labevalscores)): ?>
    charts.labeval = createChart('radarLabeval', 'rgb(255, 159, 64)', null, true, currentView);
    <?php endif; ?>
    
    createComparisonChart(currentView);
    
    // Area filter change
    document.getElementById('areaFilter').addEventListener('change', function() {
        currentView = this.value;
        
        <?php if (!empty($quizscores)): ?>
        charts.quiz = createChart('radarQuiz', 'rgb(54, 162, 235)', allQuizData, false, currentView);
        <?php endif; ?>
        
        <?php if (!empty($selfassessscores)): ?>
        charts.selfassess = createChart('radarSelfassess', 'rgb(75, 192, 192)', allSelfData, false, currentView);
        <?php endif; ?>
        
        <?php if (!empty($labevalscores)): ?>
        charts.labeval = createChart('radarLabeval', 'rgb(255, 159, 64)', null, true, currentView);
        <?php endif; ?>
        
        // Update table visibility
        var rows = document.querySelectorAll('.detail-table tbody tr');
        rows.forEach(function(row) {
            if (currentView === 'areas' || currentView === 'all') {
                row.style.display = '';
            } else {
                row.style.display = row.dataset.area === currentView ? '' : 'none';
            }
        });
    });
    
    // Comparison area filter
    document.getElementById('comparisonAreaFilter').addEventListener('change', function() {
        createComparisonChart(this.value);
    });
    
    // Comparison checkboxes
    document.getElementById('compare_quiz').addEventListener('change', function() {
        if (charts.comparison) {
            charts.comparison.data.datasets[0].hidden = !this.checked;
            charts.comparison.update();
        }
        document.getElementById('legend_quiz').style.opacity = this.checked ? '1' : '0.3';
    });
    
    document.getElementById('compare_selfassess').addEventListener('change', function() {
        if (charts.comparison) {
            charts.comparison.data.datasets[1].hidden = !this.checked;
            charts.comparison.update();
        }
        document.getElementById('legend_selfassess').style.opacity = this.checked ? '1' : '0.3';
    });
    
    document.getElementById('compare_labeval').addEventListener('change', function() {
        if (charts.comparison) {
            charts.comparison.data.datasets[2].hidden = !this.checked;
            charts.comparison.update();
        }
        document.getElementById('legend_labeval').style.opacity = this.checked ? '1' : '0.3';
    });
    
    // Print functions - Converte canvas in immagini
    function convertCanvasToImages() {
        // Trova tutti i canvas e crea immagini temporanee
        var canvases = document.querySelectorAll('canvas');
        canvases.forEach(function(canvas) {
            try {
                var img = document.createElement('img');
                img.src = canvas.toDataURL('image/png');
                img.className = 'print-radar-img';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                canvas.style.display = 'none';
                canvas.parentNode.insertBefore(img, canvas.nextSibling);
            } catch(e) {
                console.log('Errore conversione canvas:', e);
            }
        });
    }
    
    function restoreCanvases() {
        // Rimuovi immagini temporanee e mostra canvas
        var imgs = document.querySelectorAll('.print-radar-img');
        imgs.forEach(function(img) {
            img.remove();
        });
        var canvases = document.querySelectorAll('canvas');
        canvases.forEach(function(canvas) {
            canvas.style.display = '';
        });
    }
    
    window.printReport = function() {
        // Apply print selections
        var showHeader = document.getElementById('print_header').checked;
        var showRadars = document.getElementById('print_radars').checked;
        var showComparison = document.getElementById('print_comparison').checked;
        var showTable = document.getElementById('print_table').checked;
        
        document.getElementById('section_header').classList.toggle('hide-on-print', !showHeader);
        document.getElementById('section_radars').classList.toggle('hide-on-print', !showRadars);
        document.getElementById('section_comparison').classList.toggle('hide-on-print', !showComparison);
        document.getElementById('section_table').classList.toggle('hide-on-print', !showTable);
        
        // Converti canvas in immagini per stampa
        convertCanvasToImages();
        
        // Breve delay per permettere rendering
        setTimeout(function() {
            window.print();
            
            // Ripristina dopo stampa
            setTimeout(function() {
                restoreCanvases();
                document.getElementById('section_header').classList.remove('hide-on-print');
                document.getElementById('section_radars').classList.remove('hide-on-print');
                document.getElementById('section_comparison').classList.remove('hide-on-print');
                document.getElementById('section_table').classList.remove('hide-on-print');
            }, 1000);
        }, 300);
    };
    
    window.printAll = function() {
        document.getElementById('print_header').checked = true;
        document.getElementById('print_radars').checked = true;
        document.getElementById('print_comparison').checked = true;
        document.getElementById('print_table').checked = true;
        
        // Converti canvas in immagini per stampa
        convertCanvasToImages();
        
        setTimeout(function() {
            window.print();
            setTimeout(restoreCanvases, 1000);
        }, 300);
    };
    
    window.printTableOnly = function() {
        document.getElementById('section_header').classList.add('hide-on-print');
        document.getElementById('section_radars').classList.add('hide-on-print');
        document.getElementById('section_comparison').classList.add('hide-on-print');
        
        window.print();
        
        setTimeout(function() {
            document.getElementById('section_header').classList.remove('hide-on-print');
            document.getElementById('section_radars').classList.remove('hide-on-print');
            document.getElementById('section_comparison').classList.remove('hide-on-print');
        }, 500);
    };
});
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
