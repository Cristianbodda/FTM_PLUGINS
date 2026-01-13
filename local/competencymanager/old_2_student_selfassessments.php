<?php
/**
 * Dettaglio Autovalutazioni Studente
 * Visualizza tutte le autovalutazioni di uno studente con confronto quiz
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Parametri
$studentid = required_param('studentid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

// Contesto
if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

$PAGE->set_context($context);
require_capability('local/competencymanager:view', $context);

// Carica dati studente
$student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
$fullname = fullname($student);

// Carica dati coaching (se esiste)
$coaching = null;
if ($courseid) {
    $coaching = $DB->get_record('local_student_coaching', ['userid' => $studentid, 'courseid' => $courseid]);
}

// Livelli Bloom
$bloomLevels = [
    1 => ['name' => 'Ricordare', 'color' => '#e74c3c', 'bg' => '#ffeaea', 'desc' => 'Recuperare conoscenze dalla memoria a lungo termine'],
    2 => ['name' => 'Comprendere', 'color' => '#e67e22', 'bg' => '#fff5e6', 'desc' => 'Costruire significato da messaggi orali, scritti e grafici'],
    3 => ['name' => 'Applicare', 'color' => '#f1c40f', 'bg' => '#fffce6', 'desc' => 'Eseguire o utilizzare una procedura in una situazione data'],
    4 => ['name' => 'Analizzare', 'color' => '#27ae60', 'bg' => '#e8f8e8', 'desc' => 'Scomporre il materiale in parti e determinare le relazioni'],
    5 => ['name' => 'Valutare', 'color' => '#3498db', 'bg' => '#e8f4fc', 'desc' => 'Esprimere giudizi basati su criteri e standard'],
    6 => ['name' => 'Creare', 'color' => '#9b59b6', 'bg' => '#f3e8fc', 'desc' => 'Mettere insieme elementi per formare un nuovo insieme']
];

// Query autovalutazioni studente
$sql = "SELECT sa.id, sa.userid, sa.competencyid, sa.level, sa.comment, sa.timecreated, sa.timemodified,
               c.idnumber, c.shortname, c.description
        FROM {local_selfassessment} sa
        JOIN {competency} c ON c.id = sa.competencyid
        WHERE sa.userid = :studentid
        ORDER BY c.idnumber ASC";
$selfassessments = $DB->get_records_sql($sql, ['studentid' => $studentid]);

// Funzione per ottenere risultato quiz per competenza
function get_quiz_result_for_competency($DB, $studentid, $competencyid, $courseid = 0) {
    // Determina il nome della tabella competenze
    $qcTable = 'local_qbank_competencies';
    $tableExists = $DB->get_manager()->table_exists($qcTable);
    if (!$tableExists) {
        $qcTable = 'qbank_competencies';
        if (!$DB->get_manager()->table_exists($qcTable)) {
            return null;
        }
    }
    
    $courseCondition = $courseid ? "AND q.course = :courseid" : "";
    $params = ['studentid' => $studentid, 'competencyid' => $competencyid];
    if ($courseid) $params['courseid'] = $courseid;
    
    $sql = "SELECT 
                qat.id as unique_row_id,
                qa.id as attemptid,
                q.name as quizname,
                qat.questionid,
                que.name as questionname,
                que.questiontext,
                (SELECT MAX(qas.fraction) FROM {question_attempt_steps} qas 
                 WHERE qas.questionattemptid = qat.id AND qas.fraction IS NOT NULL) as fraction
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON qa.quiz = q.id
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} que ON que.id = qat.questionid
            JOIN {{$qcTable}} qcq ON qcq.questionid = qat.questionid
            WHERE qa.userid = :studentid
            AND qa.state = 'finished'
            AND qcq.competencyid = :competencyid
            {$courseCondition}
            ORDER BY qa.timefinish DESC";
    
    try {
        $results = $DB->get_records_sql($sql, $params);
        if (empty($results)) return null;
        
        $total = 0;
        $correct = 0;
        $questions = [];
        
        foreach ($results as $r) {
            $total++;
            $isCorrect = ($r->fraction >= 0.5);
            if ($isCorrect) $correct++;
            $questions[] = [
                'name' => $r->questionname,
                'text' => strip_tags($r->questiontext),
                'quiz' => $r->quizname,
                'correct' => $isCorrect
            ];
        }
        
        return [
            'percent' => $total > 0 ? round(($correct / $total) * 100) : 0,
            'total' => $total,
            'correct' => $correct,
            'questions' => $questions
        ];
    } catch (Exception $e) {
        return null;
    }
}

// Calcola statistiche
$stats = [
    'total_competencies' => count($selfassessments),
    'avg_level' => 0,
    'avg_quiz' => 0,
    'avg_gap' => 0,
    'last_date' => 0,
    'quiz_count' => 0
];

$totalLevel = 0;
$totalQuiz = 0;
$totalGap = 0;
$quizCount = 0;

// Prepara dati con risultati quiz
$assessmentsWithQuiz = [];
foreach ($selfassessments as $sa) {
    $quizResult = get_quiz_result_for_competency($DB, $studentid, $sa->competencyid, $courseid);
    
    $sa->quiz_percent = $quizResult ? $quizResult['percent'] : null;
    $sa->quiz_questions = $quizResult ? $quizResult['questions'] : [];
    
    // Calcola gap (converti livello Bloom in percentuale: 1=16.6%, 6=100%)
    if ($quizResult) {
        $bloomPercent = ($sa->level / 6) * 100;
        $sa->gap = round(($quizResult['percent'] - $bloomPercent) / 16.6, 1); // Gap in "livelli"
        $totalGap += $sa->gap;
        $totalQuiz += $quizResult['percent'];
        $quizCount++;
    } else {
        $sa->gap = null;
    }
    
    $totalLevel += $sa->level;
    if ($sa->timecreated > $stats['last_date']) {
        $stats['last_date'] = $sa->timecreated;
    }
    
    // Estrai area dal codice competenza
    $parts = explode('-', $sa->idnumber);
    $sa->area = $parts[0] ?? 'N/D';
    
    $assessmentsWithQuiz[] = $sa;
}

$stats['avg_level'] = $stats['total_competencies'] > 0 ? round($totalLevel / $stats['total_competencies'], 1) : 0;
$stats['avg_quiz'] = $quizCount > 0 ? round($totalQuiz / $quizCount) : 0;
$stats['avg_gap'] = $quizCount > 0 ? round($totalGap / $quizCount, 1) : 0;
$stats['quiz_count'] = $quizCount;

// Raggruppa per area
$byArea = [];
foreach ($assessmentsWithQuiz as $sa) {
    if (!isset($byArea[$sa->area])) {
        $byArea[$sa->area] = [
            'count' => 0,
            'total_level' => 0,
            'total_quiz' => 0,
            'quiz_count' => 0
        ];
    }
    $byArea[$sa->area]['count']++;
    $byArea[$sa->area]['total_level'] += $sa->level;
    if ($sa->quiz_percent !== null) {
        $byArea[$sa->area]['total_quiz'] += $sa->quiz_percent;
        $byArea[$sa->area]['quiz_count']++;
    }
}

// Setup pagina
$PAGE->set_url('/local/competencymanager/student_selfassessments.php', ['studentid' => $studentid, 'courseid' => $courseid]);
$PAGE->set_title('Autovalutazioni - ' . $fullname);
$PAGE->set_heading('Autovalutazioni - ' . $fullname);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
/* ============================================
   STILI IDENTICI AL MOCKUP APPROVATO
   ============================================ */
* { box-sizing: border-box; }

.container-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header Pagina */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 1.8em;
    margin-bottom: 5px;
}

.page-header .subtitle {
    opacity: 0.9;
    font-size: 1em;
}

.header-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-avatar {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
}

.student-details h2 {
    margin: 0;
    font-size: 1.4em;
}

.student-details p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9em;
}

.header-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.header-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.85em;
}

.btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 25px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-back:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    text-decoration: none;
}

/* Statistiche Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

@media (max-width: 992px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 576px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px 15px;
    text-align: center;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card .icon {
    font-size: 1.8em;
    margin-bottom: 8px;
}

.stat-card .number {
    font-size: 2em;
    font-weight: 700;
    color: #333;
}

.stat-card .label {
    font-size: 0.8em;
    color: #666;
    margin-top: 5px;
}

/* Legenda Bloom */
.bloom-legend {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
}

.bloom-legend h4 {
    margin: 0 0 12px 0;
    font-size: 1em;
    color: #333;
}

.bloom-levels {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.bloom-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
}

.bloom-1 { background: #ffeaea; color: #e74c3c; }
.bloom-2 { background: #fff5e6; color: #e67e22; }
.bloom-3 { background: #fffce6; color: #f1c40f; }
.bloom-4 { background: #e8f8e8; color: #27ae60; }
.bloom-5 { background: #e8f4fc; color: #3498db; }
.bloom-6 { background: #f3e8fc; color: #9b59b6; }

/* Filtri */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
}

.filters-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-size: 0.85em;
    color: #666;
    white-space: nowrap;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9em;
}

/* Riepilogo per Area */
.area-summary {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 25px;
}

.area-summary .card-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 15px 20px;
}

.area-summary .card-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.area-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    padding: 20px;
}

.area-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    border-left: 4px solid #667eea;
}

.area-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.area-card-title {
    font-weight: 700;
    color: #333;
}

.area-card-count {
    font-size: 0.8em;
    color: #666;
}

.area-bars {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.area-bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.area-bar-label {
    font-size: 0.8em;
    color: #666;
    width: 80px;
}

.area-bar {
    flex: 1;
    height: 10px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.area-bar-fill {
    height: 100%;
    border-radius: 5px;
}

.area-bar-fill.auto { background: linear-gradient(90deg, #667eea, #764ba2); }
.area-bar-fill.quiz { background: linear-gradient(90deg, #11998e, #38ef7d); }

.area-bar-value {
    font-size: 0.85em;
    font-weight: 600;
    width: 40px;
    text-align: right;
}

/* Tabella Competenze */
.competencies-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 25px;
}

.competencies-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.competencies-card .card-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.btn-export {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.85em;
}

.btn-export:hover {
    background: rgba(255,255,255,0.3);
}

.competencies-table {
    width: 100%;
    border-collapse: collapse;
}

.competencies-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-size: 0.85em;
    color: #666;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
}

.competencies-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.competencies-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}

.competencies-table tbody tr:hover {
    background: #f8f9fa;
}

.competencies-table tbody tr.negative-gap {
    background: #fff8f8;
}

/* Competenza info */
.competency-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.competency-code {
    font-weight: 700;
    color: #333;
    font-size: 0.95em;
}

.competency-name {
    font-size: 0.85em;
    color: #666;
}

.competency-area {
    font-size: 0.75em;
    color: #999;
}

/* Badge Bloom */
.bloom-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.bloom-badge.level-1 { background: #ffeaea; color: #e74c3c; }
.bloom-badge.level-2 { background: #fff5e6; color: #e67e22; }
.bloom-badge.level-3 { background: #fffce6; color: #b8860b; }
.bloom-badge.level-4 { background: #e8f8e8; color: #27ae60; }
.bloom-badge.level-5 { background: #e8f4fc; color: #3498db; }
.bloom-badge.level-6 { background: #f3e8fc; color: #9b59b6; }

/* Quiz result */
.quiz-result {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quiz-bar {
    width: 60px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.quiz-bar-fill {
    height: 100%;
    border-radius: 4px;
}

.quiz-bar-fill.high { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.quiz-bar-fill.medium { background: linear-gradient(90deg, #f39c12, #f1c40f); }
.quiz-bar-fill.low { background: linear-gradient(90deg, #e74c3c, #c0392b); }

.quiz-percent {
    font-weight: 600;
    font-size: 0.9em;
    min-width: 40px;
}

/* Gap indicator */
.gap-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.85em;
    font-weight: 600;
}

.gap-indicator.positive { background: #e8f8e8; color: #27ae60; }
.gap-indicator.neutral { background: #f0f0f0; color: #666; }
.gap-indicator.negative { background: #ffeaea; color: #e74c3c; }

/* Comment */
.comment-cell {
    max-width: 200px;
}

.comment-text {
    font-size: 0.85em;
    color: #666;
    font-style: italic;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.comment-text.empty {
    color: #bbb;
}

/* Date */
.date-cell {
    font-size: 0.85em;
    color: #666;
}

/* Actions */
.action-btns {
    display: flex;
    gap: 5px;
}

.btn-action {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8em;
    transition: transform 0.2s;
}

.btn-action:hover { transform: scale(1.1); }
.btn-action.view { background: #667eea; color: white; }
.btn-action.compare { background: #11998e; color: white; }

/* Responsive */
@media (max-width: 768px) {
    .page-header h1 { font-size: 1.4em; }
    .student-avatar { width: 50px; height: 50px; font-size: 1.5em; }
    .competencies-table { font-size: 0.85em; }
    .competencies-table th,
    .competencies-table td { padding: 10px 8px; }
    .comment-cell { display: none; }
}

/* ============================================
   MODAL DETTAGLIO - IDENTICO AL MOCKUP
   ============================================ */
.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 2000;
    overflow-y: auto;
    padding: 20px;
}

.modal-backdrop.active {
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.modal-detail {
    background: white;
    border-radius: 15px;
    max-width: 800px;
    width: 100%;
    margin: 20px auto;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
    overflow: hidden;
}

.modal-detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.modal-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
}

.modal-detail-header h3 {
    margin: 0;
    font-size: 1.3em;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    font-size: 1.5em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: rgba(255,255,255,0.3);
}

.modal-detail-body {
    padding: 25px;
    max-height: 70vh;
    overflow-y: auto;
}

.detail-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.detail-section h4 {
    margin: 0 0 15px 0;
    font-size: 1.1em;
    color: #333;
}

.highlight-section {
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
    margin: -25px -25px 25px -25px;
    padding: 25px;
    border-bottom: 2px solid #667eea;
}

.competency-description {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
}

.competency-description p {
    margin: 5px 0;
    color: #555;
}

.bloom-selected {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.bloom-number {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    font-weight: 700;
    color: white;
}

.bloom-number.level-1 { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.bloom-number.level-2 { background: linear-gradient(135deg, #e67e22, #d35400); }
.bloom-number.level-3 { background: linear-gradient(135deg, #f1c40f, #f39c12); }
.bloom-number.level-4 { background: linear-gradient(135deg, #27ae60, #2ecc71); }
.bloom-number.level-5 { background: linear-gradient(135deg, #3498db, #2980b9); }
.bloom-number.level-6 { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

.bloom-info {
    display: flex;
    flex-direction: column;
}

.bloom-name {
    font-size: 1.4em;
    font-weight: 700;
}

.bloom-name.level-1 { color: #e74c3c; }
.bloom-name.level-2 { color: #e67e22; }
.bloom-name.level-3 { color: #f1c40f; }
.bloom-name.level-4 { color: #27ae60; }
.bloom-name.level-5 { color: #3498db; }
.bloom-name.level-6 { color: #9b59b6; }

.bloom-date {
    font-size: 0.85em;
    color: #666;
}

.bloom-description {
    padding: 15px;
    border-radius: 10px;
    border-left: 4px solid;
    margin-bottom: 20px;
}

.bloom-description.level-1 { background: #ffeaea; border-color: #e74c3c; }
.bloom-description.level-2 { background: #fff5e6; border-color: #e67e22; }
.bloom-description.level-3 { background: #fffce6; border-color: #f1c40f; }
.bloom-description.level-4 { background: #e8f8e8; border-color: #27ae60; }
.bloom-description.level-5 { background: #e8f4fc; border-color: #3498db; }
.bloom-description.level-6 { background: #f3e8fc; border-color: #9b59b6; }

.bloom-description p {
    margin: 5px 0;
    color: #555;
}

.bloom-scale {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
}

.bloom-scale-item {
    flex: 1;
    padding: 12px 8px;
    text-align: center;
    border-radius: 8px;
    position: relative;
    transition: all 0.2s;
}

.bloom-scale-item.level-1 { background: #ffeaea; }
.bloom-scale-item.level-2 { background: #fff5e6; }
.bloom-scale-item.level-3 { background: #fffce6; }
.bloom-scale-item.level-4 { background: #e8f8e8; }
.bloom-scale-item.level-5 { background: #e8f4fc; }
.bloom-scale-item.level-6 { background: #f3e8fc; }

.bloom-scale-item.selected {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border: 2px solid currentColor;
}

.scale-num {
    display: block;
    font-size: 1.2em;
    font-weight: 700;
}

.scale-name {
    display: block;
    font-size: 0.7em;
    margin-top: 3px;
}

.scale-check {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #27ae60;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
}

.student-comment h5 {
    margin: 0 0 10px 0;
    color: #333;
}

.comment-box {
    background: white;
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-style: italic;
    color: #555;
}

.comment-box p {
    margin: 0;
}

.comment-box.empty {
    color: #999;
    font-style: normal;
}

.comparison-row {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 15px;
}

.comparison-item {
    flex: 1;
    padding: 15px;
    border-radius: 10px;
}

.comparison-item.auto {
    background: linear-gradient(135deg, #f0f4ff, #e8edff);
    border-left: 4px solid #667eea;
}

.comparison-item.quiz {
    background: linear-gradient(135deg, #e8fff0, #d8f8e8);
    border-left: 4px solid #27ae60;
}

.comparison-label {
    font-size: 0.8em;
    color: #666;
    margin-bottom: 5px;
}

.comparison-value {
    font-size: 1.2em;
    font-weight: 700;
    margin-bottom: 10px;
}

.comparison-bar {
    height: 10px;
    background: rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow: hidden;
}

.comparison-item.auto .bar-fill {
    background: linear-gradient(90deg, #667eea, #764ba2);
    height: 100%;
    border-radius: 5px;
}

.comparison-item.quiz .bar-fill {
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    height: 100%;
    border-radius: 5px;
}

.comparison-vs {
    font-weight: 700;
    color: #999;
    font-size: 0.9em;
}

.comparison-result {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    border-radius: 8px;
}

.comparison-result.positive {
    background: #e8f8e8;
    color: #27ae60;
}

.comparison-result.negative {
    background: #ffeaea;
    color: #e74c3c;
}

.comparison-result.neutral {
    background: #f0f0f0;
    color: #666;
}

.result-icon {
    font-size: 1.5em;
}

.related-questions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.question-item {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid;
}

.question-item.correct {
    border-left-color: #27ae60;
}

.question-item.wrong {
    border-left-color: #e74c3c;
    background: #fff8f8;
}

.question-status {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.question-item.correct .question-status {
    background: #e8f8e8;
    color: #27ae60;
}

.question-item.wrong .question-status {
    background: #ffeaea;
    color: #e74c3c;
}

.question-content {
    flex: 1;
}

.question-text {
    color: #333;
    margin-bottom: 5px;
}

.question-meta {
    display: flex;
    gap: 15px;
    font-size: 0.8em;
}

.quiz-name-meta {
    color: #666;
}

.question-result-text {
    font-weight: 600;
}

.question-result-text.correct { color: #27ae60; }
.question-result-text.wrong { color: #e74c3c; }

.modal-detail-footer {
    background: #f8f9fa;
    padding: 15px 25px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #eee;
}

.btn-secondary {
    background: #e0e0e0;
    color: #333;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-secondary:hover { background: #d0d0d0; }
.btn-primary:hover { opacity: 0.9; }

@media (max-width: 768px) {
    .modal-detail { margin: 10px; }
    .bloom-scale { flex-wrap: wrap; }
    .bloom-scale-item { flex: 0 0 calc(33% - 5px); }
    .comparison-row { flex-direction: column; }
    .comparison-vs { display: none; }
}
</style>

<div class="container-main">
    <!-- Header -->
    <div class="page-header">
        <a href="coaching.php?courseid=<?php echo $courseid; ?>" class="btn-back">← Torna a Coaching</a>
        
        <div class="header-info">
            <div class="student-info">
                <div class="student-avatar">👤</div>
                <div class="student-details">
                    <h2><?php echo htmlspecialchars($fullname); ?></h2>
                    <p><?php echo htmlspecialchars($student->email); ?></p>
                </div>
            </div>
            
            <div class="header-badges">
                <?php if ($coaching && $coaching->sector): ?>
                <span class="header-badge">🏭 <?php echo htmlspecialchars($coaching->sector); ?></span>
                <?php endif; ?>
                <?php if ($coaching && $coaching->area): ?>
                <span class="header-badge">🎯 <?php echo htmlspecialchars($coaching->area); ?></span>
                <?php endif; ?>
                <?php if ($coaching && $coaching->current_week): ?>
                <span class="header-badge">📅 Sett. <?php echo $coaching->current_week; ?>/6</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiche -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="icon">📝</div>
            <div class="number"><?php echo $stats['total_competencies']; ?></div>
            <div class="label">Competenze Valutate</div>
        </div>
        <div class="stat-card">
            <div class="icon">📊</div>
            <div class="number" style="color: #667eea;"><?php echo $stats['avg_level']; ?></div>
            <div class="label">Media Autovalutazione</div>
        </div>
        <div class="stat-card">
            <div class="icon">✅</div>
            <div class="number" style="color: #27ae60;"><?php echo $stats['avg_quiz']; ?>%</div>
            <div class="label">Media Quiz</div>
        </div>
        <div class="stat-card">
            <div class="icon">⚖️</div>
            <div class="number" style="color: <?php echo $stats['avg_gap'] >= 0 ? '#27ae60' : '#e74c3c'; ?>;"><?php echo ($stats['avg_gap'] >= 0 ? '+' : '') . $stats['avg_gap']; ?></div>
            <div class="label">Gap Medio</div>
        </div>
        <div class="stat-card">
            <div class="icon">📅</div>
            <div class="number" style="font-size: 1.2em;"><?php echo $stats['last_date'] ? date('d/m', $stats['last_date']) : 'N/D'; ?></div>
            <div class="label">Ultima Valutazione</div>
        </div>
    </div>
    
    <!-- Legenda Bloom -->
    <div class="bloom-legend">
        <h4>📚 Livelli Tassonomia di Bloom</h4>
        <div class="bloom-levels">
            <?php foreach ($bloomLevels as $level => $info): ?>
            <span class="bloom-item bloom-<?php echo $level; ?>"><?php echo $level; ?> <?php echo $info['name']; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="filters-section">
        <div class="filters-row">
            <div class="filter-group">
                <label>🔍</label>
                <input type="text" id="searchComp" placeholder="Cerca competenza..." onkeyup="filterTable()">
            </div>
            <div class="filter-group">
                <label>📁 Area:</label>
                <select id="filterArea" onchange="filterTable()">
                    <option value="">Tutte le aree</option>
                    <?php foreach (array_keys($byArea) as $area): ?>
                    <option value="<?php echo $area; ?>"><?php echo $area; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📊 Livello:</label>
                <select id="filterLevel" onchange="filterTable()">
                    <option value="">Tutti i livelli</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?> - <?php echo $bloomLevels[$i]['name']; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>⚖️ Gap:</label>
                <select id="filterGap" onchange="filterTable()">
                    <option value="">Tutti</option>
                    <option value="positive">✅ Sottovalutati</option>
                    <option value="neutral">➖ Allineati</option>
                    <option value="negative">⚠️ Sopravvalutati</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Riepilogo per Area -->
    <?php if (!empty($byArea)): ?>
    <div class="area-summary">
        <div class="card-header">
            <h3>📊 Riepilogo per Area - Confronto Autovalutazione vs Quiz</h3>
        </div>
        <div class="area-grid">
            <?php foreach ($byArea as $areaCode => $areaData): 
                $avgLevel = $areaData['count'] > 0 ? round($areaData['total_level'] / $areaData['count'], 1) : 0;
                $avgQuiz = $areaData['quiz_count'] > 0 ? round($areaData['total_quiz'] / $areaData['quiz_count']) : 0;
            ?>
            <div class="area-card">
                <div class="area-card-header">
                    <span class="area-card-title"><?php echo htmlspecialchars($areaCode); ?></span>
                    <span class="area-card-count"><?php echo $areaData['count']; ?> competenze</span>
                </div>
                <div class="area-bars">
                    <div class="area-bar-row">
                        <span class="area-bar-label">Autovalut.</span>
                        <div class="area-bar"><div class="area-bar-fill auto" style="width: <?php echo ($avgLevel / 6) * 100; ?>%;"></div></div>
                        <span class="area-bar-value"><?php echo $avgLevel; ?></span>
                    </div>
                    <div class="area-bar-row">
                        <span class="area-bar-label">Quiz</span>
                        <div class="area-bar"><div class="area-bar-fill quiz" style="width: <?php echo $avgQuiz; ?>%;"></div></div>
                        <span class="area-bar-value"><?php echo $avgQuiz; ?>%</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tabella Dettaglio -->
    <div class="competencies-card">
        <div class="card-header">
            <h3>📋 Dettaglio Autovalutazioni per Competenza</h3>
            <button class="btn-export" onclick="alert('Funzione export in sviluppo')">📥 Esporta CSV</button>
        </div>
        
        <table class="competencies-table" id="competenciesTable">
            <thead>
                <tr>
                    <th>Competenza</th>
                    <th>Autovalutazione</th>
                    <th>Quiz</th>
                    <th>Gap</th>
                    <th class="comment-cell">Commento</th>
                    <th>Data</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessmentsWithQuiz as $sa): 
                    $bloom = $bloomLevels[$sa->level] ?? ['name' => 'N/D', 'color' => '#999'];
                    $gapClass = 'neutral';
                    $gapIcon = '=';
                    if ($sa->gap !== null) {
                        if ($sa->gap > 0.5) { $gapClass = 'positive'; $gapIcon = '↑'; }
                        elseif ($sa->gap < -0.5) { $gapClass = 'negative'; $gapIcon = '↓'; }
                    }
                    $quizClass = 'medium';
                    if ($sa->quiz_percent !== null) {
                        if ($sa->quiz_percent >= 70) $quizClass = 'high';
                        elseif ($sa->quiz_percent < 50) $quizClass = 'low';
                    }
                    $quizColor = '#999';
                    if ($sa->quiz_percent !== null) {
                        if ($sa->quiz_percent >= 70) $quizColor = '#27ae60';
                        elseif ($sa->quiz_percent >= 50) $quizColor = '#f39c12';
                        else $quizColor = '#e74c3c';
                    }
                ?>
                <tr class="<?php echo $gapClass == 'negative' ? 'negative-gap' : ''; ?>"
                    data-code="<?php echo htmlspecialchars(strtolower($sa->idnumber)); ?>"
                    data-name="<?php echo htmlspecialchars(strtolower($sa->shortname ?? '')); ?>"
                    data-area="<?php echo htmlspecialchars($sa->area); ?>"
                    data-level="<?php echo $sa->level; ?>"
                    data-gap="<?php echo $gapClass; ?>"
                    data-id="<?php echo $sa->id; ?>"
                    data-competencyid="<?php echo $sa->competencyid; ?>"
                    onclick="openDetailModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $sa->id,
                        'code' => $sa->idnumber,
                        'name' => $sa->shortname ?? $sa->idnumber,
                        'description' => $sa->description ?? '',
                        'area' => $sa->area,
                        'level' => $sa->level,
                        'levelName' => $bloom['name'],
                        'comment' => $sa->comment ?? '',
                        'date' => date('d/m/Y H:i', $sa->timecreated),
                        'quizPercent' => $sa->quiz_percent,
                        'gap' => $sa->gap,
                        'questions' => $sa->quiz_questions
                    ])); ?>)">
                    <td>
                        <div class="competency-info">
                            <span class="competency-code"><?php echo htmlspecialchars($sa->idnumber); ?></span>
                            <span class="competency-name"><?php echo htmlspecialchars($sa->shortname ?? ''); ?></span>
                            <span class="competency-area"><?php echo htmlspecialchars($sa->area); ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="bloom-badge level-<?php echo $sa->level; ?>"><?php echo $sa->level; ?> <?php echo $bloom['name']; ?></span>
                    </td>
                    <td>
                        <?php if ($sa->quiz_percent !== null): ?>
                        <div class="quiz-result">
                            <div class="quiz-bar">
                                <div class="quiz-bar-fill <?php echo $quizClass; ?>" style="width: <?php echo $sa->quiz_percent; ?>%;"></div>
                            </div>
                            <span class="quiz-percent" style="color: <?php echo $quizColor; ?>;"><?php echo $sa->quiz_percent; ?>%</span>
                        </div>
                        <?php else: ?>
                        <span class="quiz-percent" style="color: #999;">N/D</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($sa->gap !== null): ?>
                        <span class="gap-indicator <?php echo $gapClass; ?>"><?php echo $gapIcon; ?> <?php echo ($sa->gap >= 0 ? '+' : '') . $sa->gap; ?></span>
                        <?php else: ?>
                        <span class="gap-indicator neutral">--</span>
                        <?php endif; ?>
                    </td>
                    <td class="comment-cell">
                        <?php if (!empty($sa->comment)): ?>
                        <span class="comment-text"><?php echo htmlspecialchars($sa->comment); ?></span>
                        <?php else: ?>
                        <span class="comment-text empty">Nessun commento</span>
                        <?php endif; ?>
                    </td>
                    <td class="date-cell"><?php echo date('d/m/Y', $sa->timecreated); ?></td>
                    <td>
                        <div class="action-btns" onclick="event.stopPropagation();">
                            <button class="btn-action view" title="Vedi dettaglio">👁️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dettaglio -->
<div class="modal-backdrop" id="detailModal" style="background: rgba(0,0,0,0.95) !important; backdrop-filter: blur(10px);" style="background: rgba(0,0,0,0.8) !important;">
    <div class="modal-detail" style="background: #ffffff !important; opacity: 1 !important; position: relative; z-index: 9999;">
        <div class="modal-detail-header">
            <div class="modal-title-section">
                <span class="modal-badge" id="modalCode"></span>
                <h3 id="modalName"></h3>
            </div>
            <button class="modal-close" onclick="closeDetailModal()">×</button>
        </div>
        
        <div class="modal-detail-body" style="background: #ffffff !important;">
            <!-- Competenza Info -->
            <div class="detail-section" style="background: #ffffff !important;">
                <h4>📋 Descrizione Competenza</h4>
                <div class="competency-description" style="background: #f8f9fa !important;">
                    <p><strong>Area:</strong> <span id="modalArea"></span></p>
                    <p><strong>Descrizione:</strong> <span id="modalDescription"></span></p>
                </div>
            </div>
            
            <!-- Autovalutazione Studente -->
            <div class="detail-section highlight-section" style="background: #f0f4ff !important;">
                <h4>🎯 Autovalutazione dello Studente</h4>
                <div class="self-assessment-detail">
                    <div class="bloom-selected">
                        <span class="bloom-number" id="modalBloomNumber"></span>
                        <div class="bloom-info">
                            <span class="bloom-name" id="modalBloomName"></span>
                            <span class="bloom-date" id="modalBloomDate"></span>
                        </div>
                    </div>
                    <div class="bloom-description" id="modalBloomDesc" style="background: #e8f8e8 !important;">
                        <p><strong>Cosa significa "<span id="modalBloomNameDesc"></span>":</strong></p>
                        <p id="modalBloomDescText"></p>
                    </div>
                    
                    <!-- Scala Bloom Visuale -->
                    <div class="bloom-scale" id="modalBloomScale"></div>
                    
                    <!-- Commento Studente -->
                    <div class="student-comment">
                        <h5>💬 Commento dello Studente</h5>
                        <div class="comment-box" id="modalComment" style="background: #ffffff !important;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Confronto con Quiz -->
            <div class="detail-section" id="comparisonSection" style="background: #ffffff !important;">
                <h4>📊 Confronto con Risultati Quiz</h4>
                <div class="comparison-detail">
                    <div class="comparison-row">
                        <div class="comparison-item auto">
                            <div class="comparison-label">Autovalutazione</div>
                            <div class="comparison-value" id="modalAutoValue"></div>
                            <div class="comparison-bar">
                                <div class="bar-fill" id="modalAutoBar"></div>
                            </div>
                        </div>
                        <div class="comparison-vs">VS</div>
                        <div class="comparison-item quiz">
                            <div class="comparison-label">Risultato Quiz</div>
                            <div class="comparison-value" id="modalQuizValue"></div>
                            <div class="comparison-bar">
                                <div class="bar-fill" id="modalQuizBar"></div>
                            </div>
                        </div>
                    </div>
                    <div class="comparison-result" id="modalGapResult">
                        <span class="result-icon" id="modalGapIcon"></span>
                        <span class="result-text" id="modalGapText"></span>
                    </div>
                </div>
            </div>
            
            <!-- Quiz Correlati -->
            <div class="detail-section" id="questionsSection" style="background: #ffffff !important;">
                <h4>📝 Domande Quiz su questa Competenza</h4>
                <div class="related-questions" id="modalQuestions"></div>
            </div>
        </div>
        
        <div class="modal-detail-footer" style="background: #f8f9fa !important;">
            <button class="btn-secondary" onclick="closeDetailModal()">Chiudi</button>
        </div>
    </div>
</div>

<script>
// Dati Bloom per JavaScript
const bloomData = <?php echo json_encode($bloomLevels); ?>;

// Filtro tabella
function filterTable() {
    const search = document.getElementById('searchComp').value.toLowerCase();
    const area = document.getElementById('filterArea').value;
    const level = document.getElementById('filterLevel').value;
    const gap = document.getElementById('filterGap').value;
    
    document.querySelectorAll('#competenciesTable tbody tr').forEach(row => {
        const code = row.dataset.code || '';
        const name = row.dataset.name || '';
        const rowArea = row.dataset.area || '';
        const rowLevel = row.dataset.level || '';
        const rowGap = row.dataset.gap || '';
        
        let show = true;
        
        if (search && !code.includes(search) && !name.includes(search)) show = false;
        if (area && rowArea !== area) show = false;
        if (level && rowLevel !== level) show = false;
        if (gap && rowGap !== gap) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Apri modal dettaglio
function openDetailModal(data) {
    // Popola header
    document.getElementById('modalCode').textContent = data.code;
    document.getElementById('modalName').textContent = data.name;
    
    // Popola descrizione
    document.getElementById('modalArea').textContent = data.area;
    document.getElementById('modalDescription').textContent = data.description || 'Nessuna descrizione disponibile';
    
    // Popola Bloom
    const bloom = bloomData[data.level] || {name: 'N/D', desc: ''};
    document.getElementById('modalBloomNumber').textContent = data.level;
    document.getElementById('modalBloomNumber').className = 'bloom-number level-' + data.level;
    document.getElementById('modalBloomName').textContent = bloom.name;
    document.getElementById('modalBloomName').className = 'bloom-name level-' + data.level;
    document.getElementById('modalBloomDate').textContent = 'Compilato il ' + data.date;
    document.getElementById('modalBloomNameDesc').textContent = bloom.name;
    document.getElementById('modalBloomDescText').textContent = bloom.desc;
    document.getElementById('modalBloomDesc').className = 'bloom-description level-' + data.level;
    
    // Scala Bloom
    let scaleHtml = '';
    for (let i = 1; i <= 6; i++) {
        const selected = i === data.level ? 'selected' : '';
        scaleHtml += `<div class="bloom-scale-item level-${i} ${selected}">
            <span class="scale-num">${i}</span>
            <span class="scale-name">${bloomData[i].name}</span>
            ${i === data.level ? '<span class="scale-check">✓</span>' : ''}
        </div>`;
    }
    document.getElementById('modalBloomScale').innerHTML = scaleHtml;
    
    // Commento
    const commentBox = document.getElementById('modalComment');
    if (data.comment) {
        commentBox.innerHTML = '<p>"' + data.comment + '"</p>';
        commentBox.className = 'comment-box';
    } else {
        commentBox.innerHTML = '<p>Nessun commento inserito dallo studente.</p>';
        commentBox.className = 'comment-box empty';
    }
    
    // Confronto Quiz
    if (data.quizPercent !== null) {
        document.getElementById('comparisonSection').style.display = 'block';
        document.getElementById('modalAutoValue').textContent = data.level + ' - ' + bloom.name;
        document.getElementById('modalAutoBar').style.width = ((data.level / 6) * 100) + '%';
        document.getElementById('modalQuizValue').textContent = data.quizPercent + '%';
        document.getElementById('modalQuizBar').style.width = data.quizPercent + '%';
        
        const gapResult = document.getElementById('modalGapResult');
        const gapIcon = document.getElementById('modalGapIcon');
        const gapText = document.getElementById('modalGapText');
        
        if (data.gap > 0.5) {
            gapResult.className = 'comparison-result positive';
            gapIcon.textContent = '↑';
            gapText.innerHTML = '<strong>Gap: +' + data.gap + '</strong> - Lo studente si è sottovalutato. Le competenze reali sono superiori all\'autovalutazione.';
        } else if (data.gap < -0.5) {
            gapResult.className = 'comparison-result negative';
            gapIcon.textContent = '↓';
            gapText.innerHTML = '<strong>Gap: ' + data.gap + '</strong> - Lo studente si è sopravvalutato. Necessaria formazione mirata.';
        } else {
            gapResult.className = 'comparison-result neutral';
            gapIcon.textContent = '=';
            gapText.innerHTML = '<strong>Gap: ' + (data.gap >= 0 ? '+' : '') + data.gap + '</strong> - Autovalutazione allineata ai risultati quiz.';
        }
    } else {
        document.getElementById('comparisonSection').style.display = 'none';
    }
    
    // Domande Quiz
    const questionsDiv = document.getElementById('modalQuestions');
    if (data.questions && data.questions.length > 0) {
        document.getElementById('questionsSection').style.display = 'block';
        let questionsHtml = '';
        data.questions.forEach(q => {
            const qClass = q.correct ? 'correct' : 'wrong';
            const qIcon = q.correct ? '✓' : '✗';
            const qResult = q.correct ? 'Corretta' : 'Errata';
            const qText = q.text.length > 100 ? q.text.substring(0, 100) + '...' : q.text;
            questionsHtml += `<div class="question-item ${qClass}">
                <div class="question-status">${qIcon}</div>
                <div class="question-content">
                    <div class="question-text">${qText}</div>
                    <div class="question-meta">
                        <span class="quiz-name-meta">Quiz: ${q.quiz}</span>
                        <span class="question-result-text ${qClass}">${qResult}</span>
                    </div>
                </div>
            </div>`;
        });
        questionsDiv.innerHTML = questionsHtml;
    } else {
        document.getElementById('questionsSection').style.display = 'none';
    }
    
    // Mostra modal
    document.getElementById('detailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Chiudi modal
function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Click fuori dal modal per chiudere
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});

// FORZA SFONDI BIANCHI
document.getElementById("detailModal").querySelector(".modal-detail").style.cssText = "background: #ffffff !important; opacity: 1 !important;";

// ESC per chiudere
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailModal();
    }
});
</script>

<?php
echo $OUTPUT->footer();
