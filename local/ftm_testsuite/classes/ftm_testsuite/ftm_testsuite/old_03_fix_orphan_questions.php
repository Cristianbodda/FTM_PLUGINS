<?php
/**
 * FTM Test Suite - Risolvi Domande Orfane
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/fix_orphan_questions.php'));
$PAGE->set_title('Risolvi Domande Orfane - FTM Test Suite');
$PAGE->set_heading('Risolvi Domande Orfane');
$PAGE->set_pagelayout('admin');

// Parametri
$action = optional_param('fts_action', '', PARAM_ALPHA);
$frameworkid = optional_param('fts_frameworkid', 0, PARAM_INT);
$courseid = optional_param('fts_courseid', 0, PARAM_INT);
$competencyid = optional_param('fts_competencyid', 0, PARAM_INT);
$questionids = optional_param_array('fts_questionids', [], PARAM_INT);

global $DB;

$message = '';
$messagetype = '';

// Esegui azione se richiesta
if ($action === 'assign' && confirm_sesskey() && $competencyid > 0 && !empty($questionids)) {
    $assigned = 0;
    foreach ($questionids as $qid) {
        // Verifica se già assegnata
        $exists = $DB->record_exists('qbank_competenciesbyquestion', [
            'questionid' => $qid,
            'competencyid' => $competencyid
        ]);
        
        if (!$exists) {
            $record = new stdClass();
            $record->questionid = $qid;
            $record->competencyid = $competencyid;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('qbank_competenciesbyquestion', $record);
            $assigned++;
        }
    }
    
    $message = "✅ Assegnata competenza a {$assigned} domande!";
    $messagetype = 'success';
}

// Carica framework
$frameworks = $DB->get_records_sql("
    SELECT cf.id, cf.shortname, COUNT(c.id) as comp_count
    FROM {competency_framework} cf
    LEFT JOIN {competency} c ON c.competencyframeworkid = cf.id
    GROUP BY cf.id, cf.shortname
    ORDER BY cf.shortname
");

// Carica corsi
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    ORDER BY c.fullname
");

// Costruisci query per domande orfane
$where_conditions = ["qc.id IS NULL"];
$params = [];

if ($courseid > 0) {
    $where_conditions[] = "q.course = ?";
    $params[] = $courseid;
}

$orphan_questions = $DB->get_records_sql("
    SELECT qv.questionid, qst.name as questionname, qst.qtype,
           q.id as quizid, q.name as quizname, q.course,
           c.fullname as coursename
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id 
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {question} qst ON qst.id = qv.questionid
    JOIN {course} c ON c.id = q.course
    LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY c.fullname, q.name, qst.name
    LIMIT 500
", $params);

// Carica competenze per il framework selezionato (per assegnazione)
$competencies = [];
if ($frameworkid > 0) {
    $competencies = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname
        FROM {competency} c
        WHERE c.competencyframeworkid = ?
        ORDER BY c.idnumber
    ", [$frameworkid]);
}

// Raggruppa per corso/quiz
$grouped = [];
foreach ($orphan_questions as $q) {
    $key = $q->course . '_' . $q->quizid;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'course' => $q->coursename,
            'quiz' => $q->quizname,
            'quizid' => $q->quizid,
            'courseid' => $q->course,
            'questions' => []
        ];
    }
    $grouped[$key]['questions'][] = $q;
}

$total_orphans = count($orphan_questions);

echo $OUTPUT->header();
?>

<style>
.fix-container { max-width: 1400px; margin: 0 auto; }
.fix-header {
    background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.fix-header h1 { margin: 0 0 5px 0; }
.fix-header p { margin: 0; opacity: 0.9; }

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 { margin: 0; font-size: 16px; }
.card-body { padding: 20px; }

.filters {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.filter-group { flex: 1; min-width: 200px; }
.filter-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
.form-control {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.stats-bar {
    display: flex;
    gap: 20px;
    padding: 15px 20px;
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    border-radius: 8px;
    margin-bottom: 20px;
}
.stats-bar.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.stat-item { text-align: center; }
.stat-item .number { font-size: 28px; font-weight: 700; color: #721c24; }
.stats-bar.success .stat-item .number { color: #155724; }
.stat-item .label { font-size: 12px; color: #666; }

.quiz-group {
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}
.quiz-group-header {
    padding: 12px 15px;
    background: #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.quiz-group-header:hover { background: #dee2e6; }
.quiz-group-header h4 { margin: 0; font-size: 14px; }
.quiz-group-header .count { 
    background: #dc3545; 
    color: white; 
    padding: 3px 10px; 
    border-radius: 12px; 
    font-size: 12px; 
}
.quiz-group-body { padding: 0; display: none; }
.quiz-group.open .quiz-group-body { display: block; }

.question-list { }
.question-item {
    padding: 10px 15px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 10px;
}
.question-item:last-child { border-bottom: none; }
.question-item:hover { background: #fff; }
.question-item input { flex-shrink: 0; }
.question-item .info { flex: 1; }
.question-item .name { font-weight: 600; }
.question-item .meta { font-size: 12px; color: #666; }
.question-item .link { 
    font-size: 12px; 
    color: #1e3c72; 
    text-decoration: none; 
}

.assign-panel {
    background: #e7f3ff;
    border: 2px solid #1e3c72;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}
.assign-panel h4 { margin: 0 0 15px 0; color: #1e3c72; }
.assign-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.assign-row .field { flex: 1; min-width: 200px; }
.assign-row .field label { display: block; margin-bottom: 5px; font-weight: 600; }

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; }

.select-all-bar {
    padding: 10px 15px;
    background: #fff3cd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="fix-container">
    
    <div class="fix-header">
        <h1>❓ Risolvi Domande Orfane</h1>
        <p>Visualizza e assegna competenze alle domande dei quiz che ne sono prive</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="stats-bar <?php echo $total_orphans == 0 ? 'success' : ''; ?>">
        <div class="stat-item">
            <div class="number"><?php echo $total_orphans; ?></div>
            <div class="label">Domande Orfane</div>
        </div>
        <div class="stat-item">
            <div class="number"><?php echo count($grouped); ?></div>
            <div class="label">Quiz Coinvolti</div>
        </div>
    </div>
    
    <?php if ($total_orphans == 0): ?>
    <div class="alert alert-success">
        <strong>✅ Ottimo!</strong> Non ci sono domande orfane nel sistema.
    </div>
    <?php else: ?>
    
    <!-- Filtri -->
    <form method="get" action="">
        <div class="filters">
            <div class="filter-group">
                <label>📚 Filtra per Corso</label>
                <select name="fts_courseid" class="form-control" onchange="this.form.submit()">
                    <option value="0">Tutti i corsi</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c->id; ?>" <?php echo $courseid == $c->id ? 'selected' : ''; ?>>
                        <?php echo $c->fullname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>🎯 Framework per Assegnazione</label>
                <select name="fts_frameworkid" class="form-control" onchange="this.form.submit()">
                    <option value="0">-- Seleziona Framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?>>
                        <?php echo $fw->shortname; ?> (<?php echo $fw->comp_count; ?> comp.)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    
    <!-- Form Assegnazione -->
    <form method="post" action="" id="assignForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="fts_action" value="assign">
        <input type="hidden" name="fts_frameworkid" value="<?php echo $frameworkid; ?>">
        <input type="hidden" name="fts_courseid" value="<?php echo $courseid; ?>">
        
        <!-- Lista Domande Raggruppate -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Domande da Correggere (max 500)</h3>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(true)">
                        ☑️ Seleziona Tutto
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(false)">
                        ☐ Deseleziona
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                
                <?php foreach ($grouped as $key => $group): ?>
                <div class="quiz-group" id="group_<?php echo $key; ?>">
                    <div class="quiz-group-header" onclick="toggleGroup('<?php echo $key; ?>')">
                        <h4>
                            📚 <?php echo $group['course']; ?> → 
                            📝 <?php echo $group['quiz']; ?>
                        </h4>
                        <span class="count"><?php echo count($group['questions']); ?> domande</span>
                    </div>
                    <div class="quiz-group-body">
                        <div class="select-all-bar">
                            <label>
                                <input type="checkbox" onchange="selectGroup('<?php echo $key; ?>', this.checked)">
                                Seleziona tutte di questo quiz
                            </label>
                            <a href="<?php echo $CFG->wwwroot; ?>/mod/quiz/edit.php?cmid=<?php 
                                echo $DB->get_field('course_modules', 'id', [
                                    'course' => $group['courseid'],
                                    'instance' => $group['quizid'],
                                    'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])
                                ]); 
                            ?>" target="_blank" class="btn btn-sm btn-secondary">
                                🔗 Apri Quiz Editor
                            </a>
                        </div>
                        <div class="question-list">
                            <?php foreach ($group['questions'] as $q): ?>
                            <div class="question-item">
                                <input type="checkbox" name="fts_questionids[]" 
                                       value="<?php echo $q->questionid; ?>"
                                       class="question-cb group_<?php echo $key; ?>">
                                <div class="info">
                                    <span class="name"><?php echo $q->questionname; ?></span>
                                    <span class="meta">ID: <?php echo $q->questionid; ?> · Tipo: <?php echo $q->qtype; ?></span>
                                </div>
                                <a href="<?php echo $CFG->wwwroot; ?>/question/bank/editquestion/question.php?id=<?php echo $q->questionid; ?>&courseid=<?php echo $q->course; ?>" 
                                   target="_blank" class="link">✏️ Modifica</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>
        
        <!-- Pannello Assegnazione -->
        <?php if ($frameworkid > 0 && !empty($competencies)): ?>
        <div class="assign-panel">
            <h4>🎯 Assegna Competenza alle Domande Selezionate</h4>
            <div class="assign-row">
                <div class="field">
                    <label>Competenza da assegnare:</label>
                    <select name="fts_competencyid" class="form-control" required>
                        <option value="">-- Seleziona Competenza --</option>
                        <?php foreach ($competencies as $comp): ?>
                        <option value="<?php echo $comp->id; ?>">
                            <?php echo $comp->idnumber; ?> - <?php echo $comp->shortname; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-success">
                        ✅ Assegna Competenza
                    </button>
                </div>
            </div>
            <p style="margin: 15px 0 0; font-size: 13px; color: #666;">
                ⚠️ L'assegnazione è immediata e non può essere annullata da qui. 
                Seleziona con attenzione le domande da modificare.
            </p>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            💡 Seleziona un <strong>Framework</strong> dal filtro sopra per poter assegnare competenze alle domande.
        </div>
        <?php endif; ?>
        
    </form>
    
    <?php endif; ?>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 25px;">
        <a href="fix.php" class="btn btn-secondary">← Centro Risoluzione</a>
        <a href="run.php" class="btn btn-secondary" style="margin-left: 10px;">▶️ Riesegui Test</a>
    </div>
    
</div>

<script>
function toggleGroup(key) {
    document.getElementById('group_' + key).classList.toggle('open');
}

function selectAll(checked) {
    document.querySelectorAll('.question-cb').forEach(function(cb) {
        cb.checked = checked;
    });
}

function selectGroup(key, checked) {
    document.querySelectorAll('.group_' + key).forEach(function(cb) {
        cb.checked = checked;
    });
}

// Apri primo gruppo
document.addEventListener('DOMContentLoaded', function() {
    var first = document.querySelector('.quiz-group');
    if (first) first.classList.add('open');
});
</script>

<?php
echo $OUTPUT->footer();
