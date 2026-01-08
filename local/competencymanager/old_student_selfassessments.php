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
<div class="modal-backdrop" id="detailModal">
    <div class="modal-detail">
        <div class="modal-detail-header">
            <div class="modal-title-section">
                <span class="modal-badge" id="modalCode"></span>
                <h3 id="modalName"></h3>
            </div>
            <button class="modal-close" onclick="closeDetailModal()">×</button>
        </div>
        
        <div class="modal-detail-body">
            <!-- Competenza Info -->
            <div class="detail-section">
                <h4>📋 Descrizione Competenza</h4>
                <div class="competency-description">
                    <p><strong>Area:</strong> <span id="modalArea"></span></p>
                    <p><strong>Descrizione:</strong> <span id="modalDescription"></span></p>
                </div>
            </div>
            
            <!-- Autovalutazione Studente -->
            <div class="detail-section highlight-section">
                <h4>🎯 Autovalutazione dello Studente</h4>
                <div class="self-assessment-detail">
                    <div class="bloom-selected">
                        <span class="bloom-number" id="modalBloomNumber"></span>
                        <div class="bloom-info">
                            <span class="bloom-name" id="modalBloomName"></span>
                            <span class="bloom-date" id="modalBloomDate"></span>
                        </div>
                    </div>
                    <div class="bloom-description" id="modalBloomDesc">
                        <p><strong>Cosa significa "<span id="modalBloomNameDesc"></span>":</strong></p>
                        <p id="modalBloomDescText"></p>
                    </div>
                    
                    <!-- Scala Bloom Visuale -->
                    <div class="bloom-scale" id="modalBloomScale"></div>
                    
                    <!-- Commento Studente -->
                    <div class="student-comment">
                        <h5>💬 Commento dello Studente</h5>
                        <div class="comment-box" id="modalComment"></div>
                    </div>
                </div>
            </div>
            
            <!-- Confronto con Quiz -->
            <div class="detail-section" id="comparisonSection">
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
            <div class="detail-section" id="questionsSection">
                <h4>📝 Domande Quiz su questa Competenza</h4>
                <div class="related-questions" id="modalQuestions"></div>
            </div>
        </div>
        
        <div class="modal-detail-footer">
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

// ESC per chiudere
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailModal();
    }
});
</script>

<?php
echo $OUTPUT->footer();
