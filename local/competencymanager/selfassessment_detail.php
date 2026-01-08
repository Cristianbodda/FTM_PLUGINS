<?php
/**
 * Dettaglio Singola Autovalutazione
 * Mostra il dettaglio di una specifica autovalutazione
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$said = required_param('said', PARAM_INT); // selfassessment id
$courseid = optional_param('courseid', 0, PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);

require_login();

if ($courseid) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

$PAGE->set_context($context);
require_capability('local/competencymanager:view', $context);

// Livelli Bloom
$bloomLevels = [
    1 => ['name' => 'Ricordare', 'color' => '#e74c3c', 'bg' => '#ffeaea', 'desc' => 'Recuperare conoscenze dalla memoria a lungo termine'],
    2 => ['name' => 'Comprendere', 'color' => '#e67e22', 'bg' => '#fff5e6', 'desc' => 'Costruire significato da messaggi orali, scritti e grafici'],
    3 => ['name' => 'Applicare', 'color' => '#f1c40f', 'bg' => '#fffce6', 'desc' => 'Eseguire o utilizzare una procedura in una situazione data'],
    4 => ['name' => 'Analizzare', 'color' => '#27ae60', 'bg' => '#e8f8e8', 'desc' => 'Scomporre il materiale in parti e determinare le relazioni'],
    5 => ['name' => 'Valutare', 'color' => '#3498db', 'bg' => '#e8f4fc', 'desc' => 'Esprimere giudizi basati su criteri e standard'],
    6 => ['name' => 'Creare', 'color' => '#9b59b6', 'bg' => '#f3e8fc', 'desc' => 'Mettere insieme elementi per formare un nuovo insieme']
];

// Carica autovalutazione
$sql = "SELECT sa.*, c.idnumber, c.shortname, c.description as comp_description, 
               u.firstname, u.lastname, u.email
        FROM {local_selfassessment} sa
        JOIN {competency} c ON c.id = sa.competencyid
        JOIN {user} u ON u.id = sa.userid
        WHERE sa.id = :said";
$sa = $DB->get_record_sql($sql, ['said' => $said], MUST_EXIST);

$bloom = $bloomLevels[$sa->level] ?? ['name' => 'N/D', 'color' => '#999', 'bg' => '#f0f0f0', 'desc' => ''];

// Estrai area
$parts = explode('-', $sa->idnumber);
$area = $parts[0] ?? 'N/D';

// Cerca risultati quiz per questa competenza
$quizResult = null;
$questions = [];

// DEBUG: Forza la tabella corretta
$qcTable = 'qbank_competenciesbyquestion';

// Query semplificata e testata
$sql = "SELECT 
            qat.id as unique_row_id,
            q.name as quizname,
            que.name as questionname,
            que.questiontext,
            (SELECT MAX(qas.fraction) FROM {question_attempt_steps} qas 
             WHERE qas.questionattemptid = qat.id AND qas.fraction IS NOT NULL) as fraction
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON qa.quiz = q.id
        JOIN {question_usages} qu ON qu.id = qa.uniqueid
        JOIN {question_attempts} qat ON qat.questionusageid = qu.id
        JOIN {question} que ON que.id = qat.questionid
        JOIN {qbank_competenciesbyquestion} qcq ON qcq.questionid = qat.questionid
        WHERE qa.userid = :studentid
        AND qa.state = 'finished'
        AND qcq.competencyid = :competencyid
        ORDER BY qa.timefinish DESC";

try {
    $results = $DB->get_records_sql($sql, ['studentid' => $sa->userid, 'competencyid' => $sa->competencyid]);
        if (!empty($results)) {
            $total = 0;
            $correct = 0;
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
            $quizResult = [
                'percent' => $total > 0 ? round(($correct / $total) * 100) : 0,
                'total' => $total,
                'correct' => $correct
            ];
        }
    } catch (Exception $e) {
        // Ignora errori - debugging: $e->getMessage()
    }

// Calcola gap
$gap = null;
$gapClass = 'neutral';
$gapText = '';
if ($quizResult) {
    $bloomPercent = ($sa->level / 6) * 100;
    $gap = round(($quizResult['percent'] - $bloomPercent) / 16.6, 1);
    if ($gap > 0.5) {
        $gapClass = 'positive';
        $gapText = 'Lo studente si è sottovalutato. Le competenze reali sono superiori all\'autovalutazione.';
    } elseif ($gap < -0.5) {
        $gapClass = 'negative';
        $gapText = 'Lo studente si è sopravvalutato. Necessaria formazione mirata.';
    } else {
        $gapClass = 'neutral';
        $gapText = 'Autovalutazione allineata ai risultati quiz.';
    }
}

$backUrl = new moodle_url('/local/competencymanager/student_selfassessments.php', ['studentid' => $sa->userid, 'courseid' => $courseid]);

$PAGE->set_url('/local/competencymanager/selfassessment_detail.php', ['said' => $said, 'courseid' => $courseid]);
$PAGE->set_title('Dettaglio Autovalutazione - ' . $sa->idnumber);
$PAGE->set_heading('Dettaglio Autovalutazione');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
.sa-detail-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.sa-detail-card {
    background: #ffffff;
    border-radius: 15px;
    box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 25px;
}

.sa-detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
}

.sa-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.sa-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 25px;
    font-weight: 600;
}

.btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 25px;
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

.sa-detail-header h1 {
    margin: 0;
    font-size: 1.5em;
}

.sa-detail-header p {
    margin: 5px 0 0 0;
    opacity: 0.9;
}

.sa-detail-body {
    padding: 30px;
    background: #ffffff;
}

.sa-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eee;
    background: #ffffff;
}

.sa-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.sa-section h3 {
    margin: 0 0 20px 0;
    font-size: 1.2em;
    color: #333;
}

/* Descrizione */
.sa-description-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
}

.sa-description-box p {
    margin: 8px 0;
    color: #555;
}

/* Bloom Section */
.sa-bloom-section {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8edff 100%);
    padding: 25px;
    border-radius: 12px;
    border-left: 5px solid #667eea;
}

.sa-bloom-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.sa-bloom-number {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2em;
    font-weight: 700;
    color: white;
}

.sa-bloom-info h4 {
    margin: 0;
    font-size: 1.5em;
}

.sa-bloom-info p {
    margin: 5px 0 0 0;
    color: #666;
}

.sa-bloom-desc {
    background: #ffffff;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.sa-bloom-desc p {
    margin: 5px 0;
    color: #555;
}

/* Scala Bloom */
.sa-bloom-scale {
    display: flex;
    gap: 8px;
}

.sa-scale-item {
    flex: 1;
    padding: 15px 10px;
    text-align: center;
    border-radius: 10px;
    transition: transform 0.2s;
}

.sa-scale-item.selected {
    transform: scale(1.1);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    border: 3px solid currentColor;
    position: relative;
}

.sa-scale-item.selected::after {
    content: '✓';
    position: absolute;
    top: -10px;
    right: -10px;
    background: #27ae60;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.sa-scale-item .num {
    font-size: 1.3em;
    font-weight: 700;
    display: block;
}

.sa-scale-item .name {
    font-size: 0.75em;
    display: block;
    margin-top: 5px;
}

.sa-scale-item.level-1 { background: #ffeaea; color: #e74c3c; }
.sa-scale-item.level-2 { background: #fff5e6; color: #e67e22; }
.sa-scale-item.level-3 { background: #fffce6; color: #b8860b; }
.sa-scale-item.level-4 { background: #e8f8e8; color: #27ae60; }
.sa-scale-item.level-5 { background: #e8f4fc; color: #3498db; }
.sa-scale-item.level-6 { background: #f3e8fc; color: #9b59b6; }

/* Commento */
.sa-comment-box {
    background: #ffffff;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #ddd;
    font-style: italic;
    color: #555;
}

.sa-comment-box.empty {
    color: #999;
    font-style: normal;
}

/* Confronto */
.sa-comparison {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.sa-comparison-item {
    flex: 1;
    padding: 20px;
    border-radius: 12px;
}

.sa-comparison-item.auto {
    background: linear-gradient(135deg, #f0f4ff, #e8edff);
    border-left: 5px solid #667eea;
}

.sa-comparison-item.quiz {
    background: linear-gradient(135deg, #e8fff0, #d8f8e8);
    border-left: 5px solid #27ae60;
}

.sa-comparison-item .label {
    font-size: 0.85em;
    color: #666;
    margin-bottom: 5px;
}

.sa-comparison-item .value {
    font-size: 1.4em;
    font-weight: 700;
    margin-bottom: 10px;
}

.sa-comparison-bar {
    height: 12px;
    background: rgba(0,0,0,0.1);
    border-radius: 6px;
    overflow: hidden;
}

.sa-comparison-bar .fill {
    height: 100%;
    border-radius: 6px;
}

.sa-comparison-item.auto .fill { background: linear-gradient(90deg, #667eea, #764ba2); }
.sa-comparison-item.quiz .fill { background: linear-gradient(90deg, #27ae60, #2ecc71); }

.sa-gap-result {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    border-radius: 10px;
}

.sa-gap-result.positive { background: #e8f8e8; color: #27ae60; }
.sa-gap-result.negative { background: #ffeaea; color: #e74c3c; }
.sa-gap-result.neutral { background: #f0f0f0; color: #666; }

.sa-gap-result .icon { font-size: 1.8em; }

/* Domande */
.sa-questions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sa-question {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid;
}

.sa-question.correct { border-left-color: #27ae60; }
.sa-question.wrong { border-left-color: #e74c3c; background: #fff8f8; }

.sa-question-status {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.sa-question.correct .sa-question-status { background: #e8f8e8; color: #27ae60; }
.sa-question.wrong .sa-question-status { background: #ffeaea; color: #e74c3c; }

.sa-question-content { flex: 1; }
.sa-question-text { color: #333; margin-bottom: 5px; }
.sa-question-meta { font-size: 0.85em; color: #666; }
.sa-question-meta .result { font-weight: 600; margin-left: 15px; }
.sa-question-meta .result.correct { color: #27ae60; }
.sa-question-meta .result.wrong { color: #e74c3c; }

/* Footer */
.sa-detail-footer {
    background: #f8f9fa;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-primary-sa {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
}

.btn-primary-sa:hover {
    opacity: 0.9;
    color: white;
    text-decoration: none;
}

@media (max-width: 768px) {
    .sa-bloom-scale { flex-wrap: wrap; }
    .sa-scale-item { flex: 0 0 calc(33% - 8px); }
    .sa-comparison { flex-direction: column; }
}
</style>

<div class="sa-detail-container">
    <div class="sa-detail-card">
        <div class="sa-detail-header">
            <div class="sa-header-top">
                <span class="sa-badge"><?php echo htmlspecialchars($sa->idnumber); ?></span>
                <a href="<?php echo $backUrl; ?>" class="btn-back">← Torna alla lista</a>
            </div>
            <h1><?php echo htmlspecialchars($sa->shortname ?: $sa->idnumber); ?></h1>
            <p>Studente: <?php echo htmlspecialchars($sa->firstname . ' ' . $sa->lastname); ?></p>
        </div>
        
        <div class="sa-detail-body">
            <!-- Descrizione Competenza -->
            <div class="sa-section">
                <h3>📋 Descrizione Competenza</h3>
                <div class="sa-description-box">
                    <p><strong>Area:</strong> <?php echo htmlspecialchars($area); ?></p>
                    <p><strong>Descrizione:</strong> <?php echo $sa->comp_description ?: 'Nessuna descrizione disponibile'; ?></p>
                </div>
            </div>
            
            <!-- Autovalutazione -->
            <div class="sa-section">
                <h3>🎯 Autovalutazione dello Studente</h3>
                <div class="sa-bloom-section">
                    <div class="sa-bloom-header">
                        <div class="sa-bloom-number" style="background: linear-gradient(135deg, <?php echo $bloom['color']; ?>, <?php echo $bloom['color']; ?>dd);">
                            <?php echo $sa->level; ?>
                        </div>
                        <div class="sa-bloom-info">
                            <h4 style="color: <?php echo $bloom['color']; ?>;"><?php echo $bloom['name']; ?></h4>
                            <p>Compilato il <?php echo date('d/m/Y H:i', $sa->timecreated); ?></p>
                        </div>
                    </div>
                    
                    <div class="sa-bloom-desc">
                        <p><strong>Cosa significa "<?php echo $bloom['name']; ?>":</strong></p>
                        <p><?php echo $bloom['desc']; ?></p>
                    </div>
                    
                    <div class="sa-bloom-scale">
                        <?php for ($i = 1; $i <= 6; $i++): 
                            $selected = $i == $sa->level ? 'selected' : '';
                        ?>
                        <div class="sa-scale-item level-<?php echo $i; ?> <?php echo $selected; ?>">
                            <span class="num"><?php echo $i; ?></span>
                            <span class="name"><?php echo $bloomLevels[$i]['name']; ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            
            <!-- Commento -->
            <div class="sa-section">
                <h3>💬 Commento dello Studente</h3>
                <?php if (!empty($sa->comment)): ?>
                <div class="sa-comment-box">
                    "<?php echo htmlspecialchars($sa->comment); ?>"
                </div>
                <?php else: ?>
                <div class="sa-comment-box empty">
                    Nessun commento inserito dallo studente.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Confronto Quiz -->
            <?php if ($quizResult): ?>
            <div class="sa-section">
                <h3>📊 Confronto con Risultati Quiz</h3>
                <div class="sa-comparison">
                    <div class="sa-comparison-item auto">
                        <div class="label">Autovalutazione</div>
                        <div class="value"><?php echo $sa->level; ?> - <?php echo $bloom['name']; ?></div>
                        <div class="sa-comparison-bar">
                            <div class="fill" style="width: <?php echo ($sa->level / 6) * 100; ?>%;"></div>
                        </div>
                    </div>
                    <div class="sa-comparison-item quiz">
                        <div class="label">Risultato Quiz</div>
                        <div class="value"><?php echo $quizResult['percent']; ?>%</div>
                        <div class="sa-comparison-bar">
                            <div class="fill" style="width: <?php echo $quizResult['percent']; ?>%;"></div>
                        </div>
                    </div>
                </div>
                <div class="sa-gap-result <?php echo $gapClass; ?>">
                    <span class="icon"><?php echo $gap > 0.5 ? '↑' : ($gap < -0.5 ? '↓' : '='); ?></span>
                    <span><strong>Gap: <?php echo ($gap >= 0 ? '+' : '') . $gap; ?></strong> - <?php echo $gapText; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Domande Quiz -->
            <?php if (!empty($questions)): ?>
            <div class="sa-section">
                <h3>📝 Domande Quiz su questa Competenza</h3>
                <div class="sa-questions">
                    <?php foreach ($questions as $q): 
                        $qClass = $q['correct'] ? 'correct' : 'wrong';
                        $qIcon = $q['correct'] ? '✓' : '✗';
                        $qResult = $q['correct'] ? 'Corretta' : 'Errata';
                        $qText = strlen($q['text']) > 120 ? substr($q['text'], 0, 120) . '...' : $q['text'];
                    ?>
                    <div class="sa-question <?php echo $qClass; ?>">
                        <div class="sa-question-status"><?php echo $qIcon; ?></div>
                        <div class="sa-question-content">
                            <div class="sa-question-text"><?php echo htmlspecialchars($qText); ?></div>
                            <div class="sa-question-meta">
                                Quiz: <?php echo htmlspecialchars($q['quiz']); ?>
                                <span class="result <?php echo $qClass; ?>"><?php echo $qResult; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="sa-detail-footer">
            <a href="<?php echo $backUrl; ?>" class="btn-primary-sa">← Torna alla lista</a>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
