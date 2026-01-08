<?php
/**
 * La Mia Autovalutazione - Pagina Studente
 * Lo studente vede le competenze dai quiz fatti e si autovaluta
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

$studentid = $USER->id;

if ($courseid) {
    $context = context_course::instance($courseid);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
} else {
    $context = context_system::instance();
    $course = null;
}

$PAGE->set_context($context);
$PAGE->set_url('/local/competencymanager/my_selfassessment.php', ['courseid' => $courseid]);
$PAGE->set_title('La Mia Autovalutazione');
$PAGE->set_heading('La Mia Autovalutazione');
$PAGE->set_pagelayout('standard');

// Livelli Bloom
$bloomLevels = [
    1 => ['name' => 'Ricordare', 'color' => '#c0392b', 'bg' => '#ffeaea', 'desc' => 'Recuperare conoscenze dalla memoria a lungo termine'],
    2 => ['name' => 'Comprendere', 'color' => '#d35400', 'bg' => '#fff5e6', 'desc' => 'Costruire significato da messaggi orali, scritti e grafici'],
    3 => ['name' => 'Applicare', 'color' => '#b8860b', 'bg' => '#fffce6', 'desc' => 'Eseguire o utilizzare una procedura in una situazione data'],
    4 => ['name' => 'Analizzare', 'color' => '#27ae60', 'bg' => '#e8f8e8', 'desc' => 'Scomporre il materiale in parti e determinare le relazioni'],
    5 => ['name' => 'Valutare', 'color' => '#2980b9', 'bg' => '#e8f4fc', 'desc' => 'Esprimere giudizi basati su criteri e standard'],
    6 => ['name' => 'Creare', 'color' => '#8e44ad', 'bg' => '#f3e8fc', 'desc' => 'Mettere insieme elementi per formare un nuovo insieme']
];

// Recupera competenze dai quiz fatti dallo studente
$sql = "SELECT DISTINCT c.id as competencyid, c.idnumber, c.shortname, c.description,
               COUNT(DISTINCT qat.id) as num_risposte,
               GROUP_CONCAT(DISTINCT q.name SEPARATOR ', ') as quiz_names
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON qa.quiz = q.id
        JOIN {question_usages} qu ON qu.id = qa.uniqueid
        JOIN {question_attempts} qat ON qat.questionusageid = qu.id
        JOIN {qbank_competenciesbyquestion} qcq ON qcq.questionid = qat.questionid
        JOIN {competency} c ON c.id = qcq.competencyid
        WHERE qa.userid = :studentid
        AND qa.state = 'finished'
        GROUP BY c.id, c.idnumber, c.shortname, c.description
        ORDER BY c.idnumber ASC";

$competencies = $DB->get_records_sql($sql, ['studentid' => $studentid]);

// Recupera autovalutazioni già fatte
$existingSA = [];
$saRecords = $DB->get_records('local_selfassessment', ['userid' => $studentid]);
foreach ($saRecords as $sa) {
    $existingSA[$sa->competencyid] = $sa;
}

// Statistiche
$totalComp = count($competencies);
$completedComp = 0;
foreach ($competencies as $c) {
    if (isset($existingSA[$c->competencyid])) {
        $completedComp++;
    }
}

echo $OUTPUT->header();
?>

<style>
/* ========== STILI BASE ========== */
.sa-container {
    max-width: 1000px;
    margin: 0 auto;
}

.sa-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    text-align: center;
    box-shadow: 0 5px 30px rgba(0,0,0,0.2);
}

.sa-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
}

.sa-header p {
    margin: 0;
    opacity: 0.9;
}

.sa-progress-info {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
    display: inline-block;
}

.sa-progress-info .big-num {
    font-size: 2em;
    font-weight: bold;
}

/* ========== BLOOM LEGEND ========== */
.bloom-legend {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.bloom-legend h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.bloom-scale-legend {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.bloom-item {
    flex: 1;
    min-width: 140px;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
}

.bloom-item .num {
    font-size: 1.5em;
    font-weight: bold;
    display: block;
}

.bloom-item .name {
    font-weight: 600;
    display: block;
    margin: 5px 0;
}

.bloom-item .desc {
    font-size: 0.75em;
    opacity: 0.9;
}

.bloom-1 { background: #ffeaea; color: #c0392b; }
.bloom-2 { background: #fff5e6; color: #d35400; }
.bloom-3 { background: #fffce6; color: #b8860b; }
.bloom-4 { background: #e8f8e8; color: #27ae60; }
.bloom-5 { background: #e8f4fc; color: #2980b9; }
.bloom-6 { background: #f3e8fc; color: #8e44ad; }

/* ========== COMPETENCY CARD ========== */
.competency-card {
    background: white;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.competency-card.completed {
    border: 3px solid #27ae60;
}

.competency-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.competency-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: bold;
    margin-bottom: 10px;
}

.competency-badge.done {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
}

.competency-name {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.competency-desc {
    color: #666;
    font-size: 0.9em;
}

.quiz-info {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 0.85em;
    color: #1976d2;
}

.competency-body {
    padding: 25px;
}

.assessment-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    font-size: 1.1em;
}

/* ========== OPZIONE 1: RADIO BUTTONS COLORATI ========== */
.option-simple {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.radio-bloom {
    position: relative;
}

.radio-bloom input {
    display: none;
}

.radio-bloom label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 20px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    border: 3px solid transparent;
    min-width: 100px;
}

.radio-bloom input:checked + label {
    transform: scale(1.1);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.radio-bloom input:checked + label::after {
    content: '✓';
    position: absolute;
    top: -8px;
    right: -8px;
    background: #27ae60;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}

.radio-bloom label .level-num {
    font-size: 1.8em;
    font-weight: bold;
}

.radio-bloom label .level-name {
    font-size: 0.8em;
    margin-top: 5px;
}

.radio-bloom.l1 label { background: #ffeaea; color: #c0392b; }
.radio-bloom.l1 input:checked + label { border-color: #c0392b; }
.radio-bloom.l2 label { background: #fff5e6; color: #d35400; }
.radio-bloom.l2 input:checked + label { border-color: #d35400; }
.radio-bloom.l3 label { background: #fffce6; color: #b8860b; }
.radio-bloom.l3 input:checked + label { border-color: #b8860b; }
.radio-bloom.l4 label { background: #e8f8e8; color: #27ae60; }
.radio-bloom.l4 input:checked + label { border-color: #27ae60; }
.radio-bloom.l5 label { background: #e8f4fc; color: #2980b9; }
.radio-bloom.l5 input:checked + label { border-color: #2980b9; }
.radio-bloom.l6 label { background: #f3e8fc; color: #8e44ad; }
.radio-bloom.l6 input:checked + label { border-color: #8e44ad; }

/* ========== COMMENTO ========== */
.comment-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px dashed #ddd;
}

.comment-section label {
    display: block;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.comment-section textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-family: inherit;
    font-size: 1em;
    resize: vertical;
    min-height: 80px;
}

.comment-section textarea:focus {
    border-color: #667eea;
    outline: none;
}

/* ========== BUTTONS ========== */
.actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 25px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary {
    background: #e9ecef;
    color: #333;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* ========== CURRENT VALUE DISPLAY ========== */
.current-value {
    background: #e8f8e8;
    border: 2px solid #27ae60;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-value .icon {
    font-size: 2em;
}

.current-value .text {
    flex: 1;
}

.current-value .text strong {
    color: #27ae60;
}

/* ========== EMPTY STATE ========== */
.empty-state {
    background: white;
    border-radius: 15px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.empty-state .icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #333;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
}

/* ========== TOAST NOTIFICATION ========== */
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #27ae60;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    display: none;
    z-index: 9999;
    animation: slideIn 0.3s ease;
}

.toast.error {
    background: #e74c3c;
}

@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .option-simple {
        justify-content: center;
    }
    
    .radio-bloom label {
        padding: 12px 15px;
        min-width: 80px;
    }
    
    .radio-bloom label .level-num {
        font-size: 1.5em;
    }
    
    .radio-bloom label .level-name {
        font-size: 0.7em;
    }
    
    .bloom-scale-legend {
        flex-direction: column;
    }
    
    .bloom-item {
        min-width: 100%;
    }
}
</style>

<div class="sa-container">
    <!-- Header -->
    <div class="sa-header">
        <h1>🎯 La Mia Autovalutazione</h1>
        <p>Valuta le tue competenze usando la scala di Bloom</p>
        <div class="sa-progress-info">
            <span class="big-num"><?php echo $completedComp; ?>/<?php echo $totalComp; ?></span>
            <br>Competenze valutate
        </div>
    </div>
    
    <!-- Legenda Bloom -->
    <div class="bloom-legend">
        <h3>📚 Scala Tassonomia di Bloom</h3>
        <div class="bloom-scale-legend">
            <?php foreach ($bloomLevels as $level => $data): ?>
            <div class="bloom-item bloom-<?php echo $level; ?>">
                <span class="num"><?php echo $level; ?></span>
                <span class="name"><?php echo $data['name']; ?></span>
                <span class="desc"><?php echo $data['desc']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if (empty($competencies)): ?>
    <!-- Nessuna competenza -->
    <div class="empty-state">
        <div class="icon">📝</div>
        <h3>Nessun quiz completato</h3>
        <p>Completa almeno un quiz per poter autovalutare le tue competenze.</p>
        <?php if ($course): ?>
        <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>" class="btn btn-primary" style="display: inline-block; margin-top: 20px; text-decoration: none;">
            Vai al corso
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    
    <!-- Lista Competenze -->
    <?php foreach ($competencies as $comp): 
        $existing = isset($existingSA[$comp->competencyid]) ? $existingSA[$comp->competencyid] : null;
        $cardClass = $existing ? 'competency-card completed' : 'competency-card';
        $badgeClass = $existing ? 'competency-badge done' : 'competency-badge';
        $badgeText = $existing ? '✓ ' . $comp->idnumber : $comp->idnumber;
    ?>
    <div class="<?php echo $cardClass; ?>" id="card-<?php echo $comp->competencyid; ?>">
        <div class="competency-header">
            <span class="<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
            <div class="competency-name"><?php echo htmlspecialchars($comp->shortname ?: $comp->idnumber); ?></div>
            <div class="competency-desc"><?php echo $comp->description ? strip_tags($comp->description) : 'Nessuna descrizione disponibile'; ?></div>
            <div class="quiz-info">📝 Quiz completato: <?php echo htmlspecialchars($comp->quiz_names); ?> (<?php echo $comp->num_risposte; ?> domand<?php echo $comp->num_risposte > 1 ? 'e' : 'a'; ?>)</div>
        </div>
        <div class="competency-body">
            <?php if ($existing): ?>
            <div class="current-value">
                <span class="icon">✅</span>
                <div class="text">
                    <strong>Già valutata:</strong> Livello <?php echo $existing->level; ?> - <?php echo $bloomLevels[$existing->level]['name']; ?>
                    <?php if (!empty($existing->comment)): ?>
                    <br><small>Commento: "<?php echo htmlspecialchars($existing->comment); ?>"</small>
                    <?php endif; ?>
                    <br><small>Compilata il <?php echo date('d/m/Y H:i', $existing->timecreated); ?></small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="assessment-label">Come valuti le tue competenze su questo argomento?</div>
            <div class="option-simple">
                <?php for ($i = 1; $i <= 6; $i++): 
                    $checked = ($existing && $existing->level == $i) ? 'checked' : '';
                ?>
                <div class="radio-bloom l<?php echo $i; ?>">
                    <input type="radio" name="bloom_<?php echo $comp->competencyid; ?>" id="b<?php echo $comp->competencyid; ?>-<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo $checked; ?>>
                    <label for="b<?php echo $comp->competencyid; ?>-<?php echo $i; ?>">
                        <span class="level-num"><?php echo $i; ?></span>
                        <span class="level-name"><?php echo $bloomLevels[$i]['name']; ?></span>
                    </label>
                </div>
                <?php endfor; ?>
            </div>
            <div class="comment-section">
                <label>💬 Commento (opzionale)</label>
                <textarea id="comment_<?php echo $comp->competencyid; ?>" placeholder="Spiega perché hai scelto questo livello..."><?php echo $existing ? htmlspecialchars($existing->comment) : ''; ?></textarea>
            </div>
            <div class="actions">
                <button class="btn btn-primary" onclick="saveSelfAssessment(<?php echo $comp->competencyid; ?>)">
                    💾 <?php echo $existing ? 'Aggiorna' : 'Salva'; ?> Autovalutazione
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
</div>

<!-- Toast Notification -->
<div class="toast" id="toast"></div>

<script>
function saveSelfAssessment(competencyId) {
    const selectedRadio = document.querySelector('input[name="bloom_' + competencyId + '"]:checked');
    const comment = document.getElementById('comment_' + competencyId).value;
    
    if (!selectedRadio) {
        showToast('Seleziona un livello prima di salvare', true);
        return;
    }
    
    const level = selectedRadio.value;
    
    // Disabilita il pulsante durante il salvataggio
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Salvataggio...';
    
    fetch('ajax_save_selfassessment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'competencyid=' + competencyId + '&level=' + level + '&comment=' + encodeURIComponent(comment) + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Autovalutazione salvata con successo!');
            
            // Aggiorna la card visivamente
            const card = document.getElementById('card-' + competencyId);
            card.classList.add('completed');
            
            // Aggiorna il badge
            const badge = card.querySelector('.competency-badge');
            badge.classList.add('done');
            if (!badge.textContent.startsWith('✓')) {
                badge.textContent = '✓ ' + badge.textContent;
            }
            
            // Aggiorna il pulsante
            btn.textContent = '💾 Aggiorna Autovalutazione';
            btn.disabled = false;
            
            // Aggiorna contatore header
            updateProgressCounter();
        } else {
            showToast('❌ Errore: ' + data.error, true);
            btn.textContent = '💾 Salva Autovalutazione';
            btn.disabled = false;
        }
    })
    .catch(error => {
        showToast('❌ Errore di connessione', true);
        btn.textContent = '💾 Salva Autovalutazione';
        btn.disabled = false;
    });
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast' + (isError ? ' error' : '');
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

function updateProgressCounter() {
    const total = document.querySelectorAll('.competency-card').length;
    const completed = document.querySelectorAll('.competency-card.completed').length;
    const counter = document.querySelector('.sa-progress-info .big-num');
    if (counter) {
        counter.textContent = completed + '/' + total;
    }
}
</script>

<?php
echo $OUTPUT->footer();
