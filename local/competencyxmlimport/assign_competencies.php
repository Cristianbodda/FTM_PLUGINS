<?php
/**
 * Assegnazione Competenze alle Domande
 * 
 * Assegna automaticamente le competenze alle domande dei quiz
 * leggendo il codice dal nome della domanda
 * 
 * @package    local_competencyxmlimport
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'form', PARAM_ALPHA);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/assign_competencies.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Assegna Competenze - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// CSS inline (stesso stile)
$customcss = '
<style>
.assign-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}
.assign-header {
    background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
}
.assign-header h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
}
.assign-header p {
    margin: 0;
    opacity: 0.9;
}
.assign-form, .result-panel {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    margin-bottom: 20px;
}
.form-section {
    margin-bottom: 25px;
}
.form-section h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
}
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.quiz-selector {
    display: grid;
    gap: 10px;
}
.quiz-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    transition: all 0.2s;
}
.quiz-item:hover {
    border-color: #17a2b8;
    background: #f8ffff;
}
.quiz-item.selected {
    border-color: #17a2b8;
    background: #e3f7fa;
}
.quiz-item input {
    margin-right: 12px;
    width: 18px;
    height: 18px;
}
.quiz-item .quiz-name {
    flex: 1;
    font-weight: 500;
}
.quiz-item .quiz-info {
    font-size: 12px;
    color: #666;
}
.level-selector {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.level-btn {
    padding: 10px 20px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}
.level-btn:hover {
    border-color: #17a2b8;
}
.level-btn.active {
    border-color: #17a2b8;
    background: #e3f7fa;
}
.level-btn input {
    display: none;
}
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
}
.btn-info {
    background: #17a2b8;
    color: white;
}
.btn-info:hover {
    background: #138496;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}
.results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 13px;
}
.results-table th, .results-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}
.results-table th {
    background: #f8f9fa;
    font-weight: 600;
}
.results-table tr:hover {
    background: #f8f9fa;
}
.status-ok { color: #28a745; }
.status-skip { color: #6c757d; }
.status-warn { color: #ffc107; }
.status-error { color: #dc3545; }
.summary-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.summary-item {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}
.summary-item.success { background: #d4edda; }
.summary-item.info { background: #d1ecf1; }
.summary-item.warning { background: #fff3cd; }
.summary-item.error { background: #f8d7da; }
.summary-item .number {
    font-size: 28px;
    font-weight: bold;
}
.summary-item .label {
    font-size: 12px;
    color: #666;
}
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #17a2b8;
    text-decoration: none;
}
.back-link:hover {
    text-decoration: underline;
}
</style>
';

echo $OUTPUT->header();
echo $customcss;

// Carica quiz del corso
$quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');

// Carica framework
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

// Funzione estrazione competenza
function extract_comp_code($name) {
    if (preg_match('/(MECCANICA_[A-Z]+_\d+)/', $name, $m)) return $m[1];
    if (preg_match('/([A-Z]+_[A-Z]+_\d+)/', $name, $m)) return $m[1];
    return null;
}

// AZIONE: Assegna
if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    
    $frameworkid = required_param('frameworkid', PARAM_INT);
    $selectedquizzes = optional_param_array('quizzes', [], PARAM_INT);
    $level = required_param('level', PARAM_INT);
    
    if (empty($selectedquizzes)) {
        echo '<div class="assign-page">';
        echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Torna alla Dashboard</a>';
        echo '<div class="result-panel" style="background:#f8d7da;border-color:#f5c6cb;">';
        echo '<h3>❌ Errore</h3><p>Seleziona almeno un quiz.</p>';
        echo '</div>';
        echo '<a href="assign_competencies.php?courseid='.$courseid.'" class="btn btn-secondary">← Indietro</a>';
        echo '</div>';
        echo $OUTPUT->footer();
        exit;
    }
    
    // Carica competenze framework
    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid]);
    $comp_lookup = [];
    foreach ($competencies as $c) {
        $comp_lookup[$c->idnumber] = $c;
    }
    
    $stats = ['total' => 0, 'assigned' => 0, 'already' => 0, 'not_found' => 0, 'no_code' => 0];
    $results = [];
    
    foreach ($selectedquizzes as $quizid) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) continue;
        
        // Ottieni domande del quiz
        $questions = $DB->get_records_sql("
            SELECT qv.questionid, q.name
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            WHERE qs.quizid = ?
        ", [$quizid]);
        
        foreach ($questions as $q) {
            $stats['total']++;
            $code = extract_comp_code($q->name);
            
            $result = [
                'quiz' => $quiz->name,
                'question' => substr($q->name, 0, 50),
                'code' => $code ?: '-',
                'status' => '',
                'status_class' => ''
            ];
            
            if (!$code) {
                $stats['no_code']++;
                $result['status'] = 'Nessun codice';
                $result['status_class'] = 'status-warn';
            } elseif (!isset($comp_lookup[$code])) {
                $stats['not_found']++;
                $result['status'] = 'Competenza non trovata';
                $result['status_class'] = 'status-warn';
            } else {
                $comp = $comp_lookup[$code];
                $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                    'questionid' => $q->questionid,
                    'competencyid' => $comp->id
                ]);
                
                if ($exists) {
                    $stats['already']++;
                    $result['status'] = 'Già assegnata';
                    $result['status_class'] = 'status-skip';
                } else {
                    $record = new stdClass();
                    $record->questionid = $q->questionid;
                    $record->competencyid = $comp->id;
                    $record->difficultylevel = $level;
                    $DB->insert_record('qbank_competenciesbyquestion', $record);
                    $stats['assigned']++;
                    $result['status'] = '✅ Assegnata (L'.$level.')';
                    $result['status_class'] = 'status-ok';
                }
            }
            
            $results[] = $result;
        }
    }
    
    // Mostra risultati
    ?>
    <div class="assign-page">
        <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
        
        <div class="assign-header">
            <h2>🔗 Risultato Assegnazione Competenze</h2>
            <p>Operazione completata</p>
        </div>
        
        <div class="result-panel">
            <div class="summary-box">
                <div class="summary-item info">
                    <div class="number"><?php echo $stats['total']; ?></div>
                    <div class="label">Domande processate</div>
                </div>
                <div class="summary-item success">
                    <div class="number"><?php echo $stats['assigned']; ?></div>
                    <div class="label">✅ Assegnate</div>
                </div>
                <div class="summary-item info">
                    <div class="number"><?php echo $stats['already']; ?></div>
                    <div class="label">⏭️ Già assegnate</div>
                </div>
                <div class="summary-item warning">
                    <div class="number"><?php echo $stats['not_found'] + $stats['no_code']; ?></div>
                    <div class="label">⚠️ Problemi</div>
                </div>
            </div>
            
            <h3>📋 Dettaglio</h3>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Domanda</th>
                        <th>Codice</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?php echo format_string($r['quiz']); ?></td>
                        <td><?php echo format_string($r['question']); ?></td>
                        <td><code><?php echo $r['code']; ?></code></td>
                        <td class="<?php echo $r['status_class']; ?>"><?php echo $r['status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="btn-group">
            <a href="assign_competencies.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">🔄 Nuova Assegnazione</a>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Dashboard</a>
        </div>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// FORM
?>
<div class="assign-page">
    
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="assign-header">
        <h2>🔗 Assegna Competenze alle Domande</h2>
        <p>Assegna automaticamente le competenze leggendo il codice dal nome delle domande</p>
    </div>
    
    <form method="post" action="assign_competencies.php?courseid=<?php echo $courseid; ?>&action=assign" class="assign-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <div class="form-section">
            <h3>📚 Seleziona Framework Competenze</h3>
            <div class="form-group">
                <select name="frameworkid" required>
                    <option value="">-- Seleziona framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>"><?php echo format_string($fw->shortname); ?> (<?php echo $DB->count_records('competency', ['competencyframeworkid' => $fw->id]); ?> competenze)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-section">
            <h3>⭐ Livello Difficoltà</h3>
            <div class="level-selector">
                <label class="level-btn active" onclick="selectLevelBtn(this)">
                    <input type="radio" name="level" value="1" checked> ⭐ Base
                </label>
                <label class="level-btn" onclick="selectLevelBtn(this)">
                    <input type="radio" name="level" value="2"> ⭐⭐ Intermedio
                </label>
                <label class="level-btn" onclick="selectLevelBtn(this)">
                    <input type="radio" name="level" value="3"> ⭐⭐⭐ Avanzato
                </label>
            </div>
        </div>
        
        <div class="form-section">
            <h3>📋 Seleziona Quiz (<?php echo count($quizzes); ?> disponibili)</h3>
            <?php if (empty($quizzes)): ?>
            <p style="color: #666;">Nessun quiz nel corso. <a href="create_quiz.php?courseid=<?php echo $courseid; ?>">Crea un quiz</a> prima.</p>
            <?php else: ?>
            <div class="quiz-selector">
                <?php foreach ($quizzes as $quiz): 
                    $qcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
                ?>
                <label class="quiz-item">
                    <input type="checkbox" name="quizzes[]" value="<?php echo $quiz->id; ?>">
                    <span class="quiz-name"><?php echo format_string($quiz->name); ?></span>
                    <span class="quiz-info"><?php echo $qcount; ?> domande</span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn btn-info">🔗 Assegna Competenze</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </div>
    </form>
</div>

<script>
function selectLevelBtn(el) {
    document.querySelectorAll('.level-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input').checked = true;
}
</script>

<?php
echo $OUTPUT->footer();
