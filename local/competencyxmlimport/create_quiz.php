<?php
/**
 * Wizard Creazione Quiz
 * 
 * Permette di creare quiz selezionando domande dalla question bank
 * con assegnazione automatica delle competenze
 * 
 * @package    local_competencyxmlimport
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'form', PARAM_ALPHA);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/create_quiz.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Crea Quiz - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// CSS inline (stesso stile del plugin esistente)
$customcss = '
<style>
.quiz-wizard {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}
.wizard-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}
.wizard-header h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
}
.wizard-header p {
    margin: 0;
    opacity: 0.9;
}
.wizard-form {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
}
.form-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}
.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.form-section h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #444;
}
.form-group input[type="text"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #28a745;
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
}
.form-group textarea {
    min-height: 80px;
    resize: vertical;
}
.level-options {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
.level-option {
    flex: 1;
    min-width: 150px;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.level-option:hover {
    border-color: #28a745;
    background: #f8fff8;
}
.level-option.selected {
    border-color: #28a745;
    background: #e8f5e9;
}
.level-option input {
    display: none;
}
.level-option .stars {
    font-size: 20px;
    margin-bottom: 5px;
}
.level-option .label {
    font-weight: 500;
    color: #333;
}
.question-selector {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
}
.question-item {
    display: flex;
    align-items: flex-start;
    padding: 12px;
    border-bottom: 1px solid #eee;
    transition: background 0.2s;
    gap: 12px;
}
.question-item:last-child {
    border-bottom: none;
}
.question-item:hover {
    background: #f8f9fa;
}
.question-item input[type="checkbox"] {
    margin-top: 4px;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}
.question-item .q-content {
    flex: 1;
    min-width: 0;
}
.question-item .q-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}
.question-item .q-preview {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
}
.question-item .q-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-end;
    flex-shrink: 0;
}
.question-item .q-competency {
    font-size: 11px;
    color: #fff;
    background: #28a745;
    padding: 2px 8px;
    border-radius: 4px;
}
.question-item .q-category {
    font-size: 10px;
    color: #666;
    background: #e9ecef;
    padding: 2px 8px;
    border-radius: 4px;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}
.filter-bar select,
.filter-bar input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
}
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-success:hover {
    background: #218838;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #5a6268;
}
.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}
.select-all-bar {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.selected-count {
    margin-left: auto;
    font-weight: 500;
    color: #28a745;
}
.result-box {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}
.result-box.error {
    background: #f8d7da;
    border-color: #f5c6cb;
}
.result-box h3 {
    margin: 0 0 10px 0;
    color: #155724;
}
.result-box.error h3 {
    color: #721c24;
}
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #667eea;
    text-decoration: none;
}
.back-link:hover {
    text-decoration: underline;
}
</style>
';

echo $OUTPUT->header();
echo $customcss;

// Carica framework disponibili
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

// Carica domande del corso con più dettagli
$questions = $DB->get_records_sql("
    SELECT q.id, q.name, q.questiontext, q.qtype, qc.name as category_name
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    AND q.qtype != 'random'
    ORDER BY qc.name, q.name
", [$context->id]);

// Funzione per estrarre codice competenza
function extract_competency_code_from_name($name) {
    if (preg_match('/(MECCANICA_[A-Z]+_\d+)/', $name, $matches)) {
        return $matches[1];
    }
    if (preg_match('/([A-Z]+_[A-Z]+_\d+)/', $name, $matches)) {
        return $matches[1];
    }
    return null;
}

// AZIONE: Creazione Quiz
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    
    $quizname = required_param('quizname', PARAM_TEXT);
    $quizdescription = optional_param('quizdescription', '', PARAM_RAW);
    $difficultylevel = required_param('difficultylevel', PARAM_INT);
    $frameworkid = required_param('frameworkid', PARAM_INT);
    $selectedquestions = optional_param_array('questions', [], PARAM_INT);
    $assigncompetencies = optional_param('assigncompetencies', 0, PARAM_INT);
    
    if (empty($selectedquestions)) {
        echo '<div class="quiz-wizard">';
        echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Torna alla Dashboard</a>';
        echo '<div class="result-box error"><h3>❌ Errore</h3><p>Devi selezionare almeno una domanda.</p></div>';
        echo '<p><a href="create_quiz.php?courseid='.$courseid.'" class="btn btn-secondary">← Torna indietro</a></p>';
        echo '</div>';
        echo $OUTPUT->footer();
        exit;
    }
    
    // Trova la prima sezione del corso
    $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);
    if (!$section) {
        $section = $DB->get_record_sql("SELECT * FROM {course_sections} WHERE course = ? ORDER BY section ASC LIMIT 1", [$courseid]);
    }
    
    // Crea il quiz
    $quiz = new stdClass();
    $quiz->course = $courseid;
    $quiz->name = $quizname;
    $quiz->intro = $quizdescription;
    $quiz->introformat = FORMAT_HTML;
    $quiz->timeopen = 0;
    $quiz->timeclose = 0;
    $quiz->timelimit = 0;
    $quiz->preferredbehaviour = 'deferredfeedback';
    $quiz->attempts = 0;
    $quiz->grademethod = 1;
    $quiz->decimalpoints = 2;
    $quiz->questiondecimalpoints = -1;
    $quiz->grade = 10;
    $quiz->sumgrades = 0;
    $quiz->shuffleanswers = 1;
    $quiz->questionsperpage = 1;
    $quiz->navmethod = 'free';
    $quiz->timecreated = time();
    $quiz->timemodified = time();
    
    // Campi review (tutti a 0 per evitare errori)
    $quiz->reviewattempt = 69904;
    $quiz->reviewcorrectness = 69904;
    $quiz->reviewmaxmarks = 69904;
    $quiz->reviewmarks = 69904;
    $quiz->reviewspecificfeedback = 69904;
    $quiz->reviewgeneralfeedback = 69904;
    $quiz->reviewrightanswer = 69904;
    $quiz->reviewoverallfeedback = 69904;
    $quiz->overduehandling = 'autosubmit';
    $quiz->graceperiod = 0;
    $quiz->canredoquestions = 0;
    $quiz->allowofflineattempts = 0;
    
    try {
        $quiz->id = $DB->insert_record('quiz', $quiz);
        
        // Crea course module
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        
        $cm = new stdClass();
        $cm->course = $courseid;
        $cm->module = $module->id;
        $cm->instance = $quiz->id;
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->visibleoncoursepage = 1;
        $cm->added = time();
        
        $cm->id = $DB->insert_record('course_modules', $cm);
        
        // Aggiungi alla sezione usando la funzione corretta di Moodle
        course_add_cm_to_section($courseid, $cm->id, $section->section);
        
        // Crea context
        context_module::instance($cm->id);
        $modcontext = context_module::instance($cm->id);
        
        // Crea quiz_sections (obbligatorio in Moodle 4.x)
        $quiz_section = new stdClass();
        $quiz_section->quizid = $quiz->id;
        $quiz_section->firstslot = 1;
        $quiz_section->heading = '';
        $quiz_section->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $quiz_section);
        
        // Aggiungi domande
        $slot = 0;
        $sumgrades = 0;
        $assigned_count = 0;
        
        foreach ($selectedquestions as $questionid) {
            $slot++;
            $page = ceil($slot / 5);
            
            // Trova questionbankentryid
            $qbe = $DB->get_record_sql("
                SELECT qbe.id 
                FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = ?
                ORDER BY qv.version DESC
                LIMIT 1
            ", [$questionid]);
            
            if (!$qbe) continue;
            
            // Inserisci slot
            $slotrecord = new stdClass();
            $slotrecord->quizid = $quiz->id;
            $slotrecord->slot = $slot;
            $slotrecord->page = $page;
            $slotrecord->maxmark = 1.0;
            $slotrecord->id = $DB->insert_record('quiz_slots', $slotrecord);
            
            // Inserisci question_references
            $qref = new stdClass();
            $qref->usingcontextid = $modcontext->id;
            $qref->component = 'mod_quiz';
            $qref->questionarea = 'slot';
            $qref->itemid = $slotrecord->id;
            $qref->questionbankentryid = $qbe->id;
            $qref->version = null;
            $DB->insert_record('question_references', $qref);
            
            $sumgrades += 1.0;
            
            // Assegna competenza se richiesto
            if ($assigncompetencies && $frameworkid > 0) {
                $question = $DB->get_record('question', ['id' => $questionid]);
                if ($question) {
                    $comp_code = extract_competency_code_from_name($question->name);
                    if ($comp_code) {
                        $competency = $DB->get_record('competency', [
                            'idnumber' => $comp_code,
                            'competencyframeworkid' => $frameworkid
                        ]);
                        if ($competency) {
                            $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                                'questionid' => $questionid,
                                'competencyid' => $competency->id
                            ]);
                            if (!$exists) {
                                $record = new stdClass();
                                $record->questionid = $questionid;
                                $record->competencyid = $competency->id;
                                $record->difficultylevel = $difficultylevel;
                                $DB->insert_record('qbank_competenciesbyquestion', $record);
                                $assigned_count++;
                            }
                        }
                    }
                }
            }
        }
        
        // Aggiorna sumgrades
        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quiz->id]);
        
        // Rebuild course cache
        rebuild_course_cache($courseid, true);
        
        // Mostra risultato
        echo '<div class="quiz-wizard">';
        echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Torna alla Dashboard</a>';
        echo '<div class="result-box">';
        echo '<h3>✅ Quiz Creato con Successo!</h3>';
        echo '<p><strong>Nome:</strong> ' . format_string($quizname) . '</p>';
        echo '<p><strong>Domande:</strong> ' . $slot . '</p>';
        echo '<p><strong>Livello:</strong> ' . str_repeat('⭐', $difficultylevel) . '</p>';
        if ($assigncompetencies) {
            echo '<p><strong>Competenze assegnate:</strong> ' . $assigned_count . '</p>';
        }
        echo '</div>';
        echo '<div class="btn-group">';
        echo '<a href="' . $CFG->wwwroot . '/mod/quiz/view.php?id=' . $cm->id . '" class="btn btn-success">📝 Vai al Quiz</a>';
        echo '<a href="create_quiz.php?courseid=' . $courseid . '" class="btn btn-secondary">➕ Crea un altro Quiz</a>';
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="quiz-wizard">';
        echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Torna alla Dashboard</a>';
        echo '<div class="result-box error">';
        echo '<h3>❌ Errore nella creazione</h3>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '</div>';
        echo '<p><a href="create_quiz.php?courseid='.$courseid.'" class="btn btn-secondary">← Torna indietro</a></p>';
        echo '</div>';
    }
    
    echo $OUTPUT->footer();
    exit;
}

// FORM CREAZIONE QUIZ
?>

<div class="quiz-wizard">
    
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="wizard-header">
        <h2>📝 Crea Nuovo Quiz</h2>
        <p>Seleziona le domande e configura il quiz</p>
    </div>
    
    <form method="post" action="create_quiz.php?courseid=<?php echo $courseid; ?>&action=create" class="wizard-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <!-- SEZIONE 1: Info Base -->
        <div class="form-section">
            <h3>📋 Informazioni Quiz</h3>
            
            <div class="form-group">
                <label for="quizname">Nome Quiz *</label>
                <input type="text" id="quizname" name="quizname" required placeholder="Es: Quiz Approfondimento Disegno Tecnico">
            </div>
            
            <div class="form-group">
                <label for="quizdescription">Descrizione (opzionale)</label>
                <textarea id="quizdescription" name="quizdescription" placeholder="Inserisci una descrizione per il quiz..."></textarea>
            </div>
        </div>
        
        <!-- SEZIONE 2: Livello -->
        <div class="form-section">
            <h3>⭐ Livello Difficoltà</h3>
            
            <div class="level-options">
                <label class="level-option selected" onclick="selectLevel(this, 1)">
                    <input type="radio" name="difficultylevel" value="1" checked>
                    <div class="stars">⭐</div>
                    <div class="label">Base</div>
                </label>
                <label class="level-option" onclick="selectLevel(this, 2)">
                    <input type="radio" name="difficultylevel" value="2">
                    <div class="stars">⭐⭐</div>
                    <div class="label">Intermedio</div>
                </label>
                <label class="level-option" onclick="selectLevel(this, 3)">
                    <input type="radio" name="difficultylevel" value="3">
                    <div class="stars">⭐⭐⭐</div>
                    <div class="label">Avanzato</div>
                </label>
            </div>
        </div>
        
        <!-- SEZIONE 3: Framework -->
        <div class="form-section">
            <h3>🔗 Framework Competenze</h3>
            
            <div class="form-group">
                <label for="frameworkid">Seleziona Framework</label>
                <select id="frameworkid" name="frameworkid">
                    <option value="0">-- Nessun framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>"><?php echo format_string($fw->shortname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="assigncompetencies" value="1" checked>
                    Assegna automaticamente competenze alle domande
                </label>
            </div>
        </div>
        
        <!-- SEZIONE 4: Selezione Domande -->
        <div class="form-section">
            <h3>📝 Seleziona Domande (<?php echo count($questions); ?> disponibili)</h3>
            
            <div class="filter-bar">
                <input type="text" id="searchQuestion" placeholder="🔍 Cerca domanda..." onkeyup="filterQuestions()">
                <select id="filterCategory" onchange="filterQuestions()">
                    <option value="">Tutte le categorie</option>
                    <?php
                    $categories = [];
                    foreach ($questions as $q) {
                        if (!in_array($q->category_name, $categories)) {
                            $categories[] = $q->category_name;
                            echo '<option value="'.htmlspecialchars($q->category_name).'">'.format_string($q->category_name).'</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="select-all-bar">
                <label>
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    Seleziona tutte
                </label>
                <span class="selected-count" id="selectedCount">0 domande selezionate</span>
            </div>
            
            <div class="question-selector" id="questionList">
                <?php foreach ($questions as $q): 
                    $comp_code = extract_competency_code_from_name($q->name);
                    // Estrai un'anteprima del testo della domanda (rimuovi HTML e tronca)
                    $preview = strip_tags($q->questiontext);
                    $preview = html_entity_decode($preview);
                    $preview = preg_replace('/\s+/', ' ', $preview); // rimuovi spazi multipli
                    $preview = trim($preview);
                    if (strlen($preview) > 80) {
                        $preview = substr($preview, 0, 80) . '...';
                    }
                ?>
                <div class="question-item" data-category="<?php echo htmlspecialchars($q->category_name); ?>" data-name="<?php echo htmlspecialchars(strtolower($q->name . ' ' . $preview)); ?>">
                    <input type="checkbox" name="questions[]" value="<?php echo $q->id; ?>" onchange="updateCount()">
                    <div class="q-content">
                        <div class="q-name"><?php echo format_string($q->name); ?></div>
                        <div class="q-preview"><?php echo htmlspecialchars($preview); ?></div>
                    </div>
                    <div class="q-meta">
                        <?php if ($comp_code): ?>
                        <span class="q-competency"><?php echo $comp_code; ?></span>
                        <?php endif; ?>
                        <span class="q-category"><?php echo format_string($q->category_name); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- PULSANTI -->
        <div class="btn-group">
            <button type="submit" class="btn btn-success">✅ Crea Quiz</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </div>
        
    </form>
</div>

<script>
function selectLevel(element, level) {
    document.querySelectorAll('.level-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.question-item:not([style*="display: none"]) input[type="checkbox"]').forEach(cb => {
        cb.checked = checked;
    });
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.question-item input[type="checkbox"]:checked').length;
    document.getElementById('selectedCount').textContent = count + ' domande selezionate';
}

function filterQuestions() {
    const search = document.getElementById('searchQuestion').value.toLowerCase();
    const category = document.getElementById('filterCategory').value;
    
    document.querySelectorAll('.question-item').forEach(item => {
        const name = item.dataset.name;
        const cat = item.dataset.category;
        
        const matchSearch = !search || name.includes(search);
        const matchCategory = !category || cat === category;
        
        item.style.display = (matchSearch && matchCategory) ? '' : 'none';
    });
}
</script>

<?php
echo $OUTPUT->footer();
