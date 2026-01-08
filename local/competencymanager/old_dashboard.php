<?php
/**
 * Dashboard - Competency Manager
 * Centro di controllo unificato per quiz e report
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);

$PAGE->set_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Competency Manager - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Get statistics
$canmanage = has_capability('moodle/course:manageactivities', $context);
$canviewreports = has_capability('moodle/grade:viewall', $context);

// Count quizzes and questions
$quizcount = $DB->count_records('quiz', ['course' => $courseid]);
$questioncount = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT q.id) FROM {question} q
     JOIN {question_versions} qv ON qv.questionid = q.id
     JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
     JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
     WHERE qc.contextid = ?",
    [$context->id]
);

// Count competency assignments
$assignedcount = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT qcbq.questionid) FROM {qbank_competenciesbyquestion} qcbq
     JOIN {question_versions} qv ON qv.questionid = qcbq.questionid
     JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
     JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
     WHERE qc.contextid = ?",
    [$context->id]
);

// Count students with attempts
$studentcount = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT qa.userid) FROM {quiz_attempts} qa
     JOIN {quiz} q ON q.id = qa.quiz
     WHERE q.course = ? AND qa.state = 'finished'",
    [$courseid]
);

// Get frameworks
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

echo $OUTPUT->header();
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(40, 167, 69, 0.3);
}
.dashboard-header h2 { margin: 0 0 10px; font-size: 2rem; }
.dashboard-header p { margin: 0; opacity: 0.9; }

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-top: 4px solid #28a745;
}
.stat-card .number { font-size: 2.5rem; font-weight: 700; color: #28a745; }
.stat-card .label { font-size: 0.9rem; color: #666; margin-top: 5px; }

.section-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #333;
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}
.section-title .icon { margin-right: 10px; }

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.tool-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: all 0.3s;
    border: 1px solid #e0e0e0;
}
.tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.tool-card .tool-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}
.tool-card h4 { margin: 0 0 10px; font-size: 1.2rem; }
.tool-card p { color: #666; margin: 0 0 15px; font-size: 0.95rem; }
.tool-card .btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}
.tool-card .btn:hover { transform: translateY(-2px); }

.btn-green { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.btn-blue { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.btn-orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
.btn-teal { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
.btn-red { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
.btn-gray { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; }

.tool-card.highlight {
    border: 2px solid #28a745;
    background: linear-gradient(135deg, #f8fff8, #e8f5e9);
}
.tool-card.report-highlight {
    border: 2px solid #667eea;
    background: linear-gradient(135deg, #f8f9ff, #e8ecff);
}

.quick-links {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}
.quick-links h5 { margin: 0 0 15px; }
.quick-links a {
    display: inline-block;
    margin: 5px 10px 5px 0;
    color: #28a745;
    text-decoration: none;
}
.quick-links a:hover { text-decoration: underline; }
</style>

<div class="dashboard-header">
    <h2>🎯 Competency Manager</h2>
    <p>Gestisci quiz, competenze e report per il corso <strong><?php echo format_string($course->shortname); ?></strong></p>
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="number"><?php echo $quizcount; ?></div>
        <div class="label">📝 Quiz nel corso</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo $questioncount; ?></div>
        <div class="label">❓ Domande totali</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo $assignedcount; ?></div>
        <div class="label">🔗 Con competenza</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo $studentcount; ?></div>
        <div class="label">👥 Studenti attivi</div>
    </div>
</div>

<?php if ($canmanage): ?>
<h3 class="section-title"><span class="icon">📝</span> Creazione Quiz</h3>
<div class="tools-grid">
    <div class="tool-card highlight">
        <div class="tool-icon">📁</div>
        <h4>Crea Quiz da XML</h4>
        <p>Carica un file XML e crea automaticamente il quiz con tutte le domande e competenze assegnate.</p>
        <a href="create_quiz.php?courseid=<?php echo $courseid; ?>&source=xml" class="btn btn-green">Crea Quiz →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">📚</div>
        <h4>Domande del Corso</h4>
        <p>Visualizza e gestisci le domande già presenti nel banco domande del corso.</p>
        <a href="create_quiz.php?courseid=<?php echo $courseid; ?>&source=course" class="btn btn-teal">Gestisci →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">🔍</div>
        <h4>Diagnostica Framework</h4>
        <p>Verifica la corrispondenza tra codici domande e competenze nel framework.</p>
        <a href="diagnostics.php?courseid=<?php echo $courseid; ?>" class="btn btn-orange">Verifica →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($canviewreports): ?>
<h3 class="section-title"><span class="icon">📊</span> Report e Analisi</h3>
<div class="tools-grid">
    <div class="tool-card report-highlight">
        <div class="tool-icon">📊</div>
        <h4>Report Competenze</h4>
        <p>Visualizza i report grafici delle competenze acquisite dagli studenti nei quiz del corso.</p>
        <a href="reports.php?courseid=<?php echo $courseid; ?>" class="btn btn-blue">Visualizza Report →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">🔐</div>
        <h4>Gestisci Autorizzazioni</h4>
        <p>Autorizza gli studenti a visualizzare il proprio report delle competenze.</p>
        <a href="authorize.php?courseid=<?php echo $courseid; ?>" class="btn btn-gray">Gestisci →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">📥</div>
        <h4>Esporta Dati Classe</h4>
        <p>Scarica i dati delle competenze della classe in formato CSV o Excel.</p>
        <a href="export.php?courseid=<?php echo $courseid; ?>&format=csv" class="btn btn-green">CSV</a>
        <a href="export.php?courseid=<?php echo $courseid; ?>&format=excel" class="btn btn-green">Excel</a>
    </div>
</div>
<?php endif; ?>

<h3 class="section-title"><span class="icon">⚙️</span> Configurazione</h3>
<div class="tools-grid">
    <div class="tool-card">
        <div class="tool-icon">🏗️</div>
        <h4>Framework Competenze</h4>
        <p>Gestisci i framework di competenze disponibili nel sistema.</p>
        <?php if (count($frameworks) > 0): ?>
        <p><small><strong><?php echo count($frameworks); ?></strong> framework disponibili</small></p>
        <?php endif; ?>
        <a href="<?php echo $CFG->wwwroot; ?>/admin/tool/lp/competencyframeworks.php?pagecontextid=<?php echo context_system::instance()->id; ?>" class="btn btn-gray">Gestisci →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">❓</div>
        <h4>Banco Domande</h4>
        <p>Accedi al banco domande del corso per gestire le domande esistenti.</p>
        <a href="<?php echo $CFG->wwwroot; ?>/question/edit.php?courseid=<?php echo $courseid; ?>" class="btn btn-teal">Apri →</a>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon">📋</div>
        <h4>Categorie Domande</h4>
        <p>Gestisci le categorie del banco domande del corso.</p>
        <a href="<?php echo $CFG->wwwroot; ?>/question/category.php?courseid=<?php echo $courseid; ?>" class="btn btn-orange">Gestisci →</a>
    </div>
</div>

<div class="quick-links">
    <h5>🔗 Link Rapidi</h5>
    <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>">📚 Vai al corso</a>
    <a href="<?php echo $CFG->wwwroot; ?>/grade/report/grader/index.php?id=<?php echo $courseid; ?>">📊 Registro valutazioni</a>
    <a href="<?php echo $CFG->wwwroot; ?>/user/index.php?id=<?php echo $courseid; ?>">👥 Partecipanti</a>
    <a href="<?php echo $CFG->wwwroot; ?>/report/log/index.php?id=<?php echo $courseid; ?>">📝 Log attività</a>
</div>

<?php
echo $OUTPUT->footer();
