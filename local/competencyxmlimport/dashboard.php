<?php
/**
 * Dashboard principale - Gestione Competenze e Quiz
 * 
 * Pagina indice che mostra tutte le funzionalità disponibili nel plugin
 * 
 * @package    local_competencyxmlimport
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Parametro corso obbligatorio
$courseid = required_param('courseid', PARAM_INT);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/dashboard.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_competencyxmlimport') . ' - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// CSS inline per mantenere lo stesso stile
$customcss = '
<style>
.competency-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.dashboard-header h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
}
.dashboard-header p {
    margin: 0;
    opacity: 0.9;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e0e0e0;
}
.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.dashboard-card h3 {
    margin: 0 0 12px 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dashboard-card p {
    color: #666;
    font-size: 14px;
    margin: 0 0 20px 0;
    line-height: 1.5;
}
.dashboard-card .btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s;
}
.btn-primary {
    background: #667eea;
    color: white;
}
.btn-primary:hover {
    background: #5a6fd6;
    color: white;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-success:hover {
    background: #218838;
    color: white;
}
.btn-info {
    background: #17a2b8;
    color: white;
}
.btn-info:hover {
    background: #138496;
    color: white;
}
.btn-warning {
    background: #ffc107;
    color: #333;
}
.btn-warning:hover {
    background: #e0a800;
    color: #333;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #5a6268;
    color: white;
}
.btn-purple {
    background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
    color: white;
}
.btn-purple:hover {
    background: linear-gradient(135deg, #6D28D9 0%, #5B21B6 100%);
    color: white;
}
.card-icon {
    font-size: 24px;
}
.section-title {
    font-size: 16px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 30px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
}
.badge-new {
    background: #28a745;
    color: white;
}
.badge-updated {
    background: #7C3AED;
    color: white;
}
.badge-existing {
    background: #6c757d;
    color: white;
}
.course-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.course-info strong {
    color: #333;
}
.feature-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.feature-tag {
    background: rgba(124, 58, 237, 0.1);
    color: #7C3AED;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
</style>
';

echo $OUTPUT->header();
echo $customcss;

// Conta statistiche corso
$question_count = $DB->count_records_sql("
    SELECT COUNT(DISTINCT q.id) 
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
", [$context->id]);

$quiz_count = $DB->count_records('quiz', ['course' => $courseid]);

$competency_count = $DB->count_records_sql("
    SELECT COUNT(DISTINCT qbc.id)
    FROM {qbank_competenciesbyquestion} qbc
    JOIN {question} q ON q.id = qbc.questionid
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
", [$context->id]);

?>

<div class="competency-dashboard">
    
    <div class="dashboard-header">
        <h2>🎯 Gestione Competenze e Quiz</h2>
        <p>Strumenti per importare domande, creare quiz e gestire le competenze</p>
    </div>
    
    <div class="course-info">
        <div>
            <strong>📚 Corso:</strong> <?php echo format_string($course->fullname); ?>
        </div>
        <div>
            <strong>📝 Domande:</strong> <?php echo $question_count; ?> |
            <strong>📋 Quiz:</strong> <?php echo $quiz_count; ?> |
            <strong>🔗 Competenze assegnate:</strong> <?php echo $competency_count; ?>
        </div>
    </div>

    <!-- SEZIONE: STRUMENTO PRINCIPALE -->
    <div class="section-title">🚀 Strumento Principale</div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card" style="grid-column: span 2; background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 2px solid #667eea;">
            <h3><span class="card-icon">🛠️</span> Setup Universale Quiz e Competenze <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Strumento completo per creare quiz e assegnare competenze per <strong>qualsiasi framework e settore</strong>. Seleziona framework, settore, carica XML e crea tutto automaticamente!</p>
            <a href="setup_universale.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">🚀 Avvia Setup Universale</a>
        </div>
    </div>

    <!-- SEZIONE: IMPORT E CREAZIONE -->
    <div class="section-title">📥 Import e Creazione</div>
    
    <div class="dashboard-grid">
        
        <!-- NUOVO: Import Quiz Wizard -->
        <div class="dashboard-card" style="background: linear-gradient(135deg, #7C3AED15 0%, #6D28D915 100%); border: 2px solid #7C3AED;">
            <h3><span class="card-icon">📥</span> Import Quiz (Word/XML) <span class="status-badge badge-updated">AGGIORNATO</span></h3>
            <p>Wizard guidato per importare domande da <strong>file Word (.docx)</strong> o <strong>XML Moodle</strong>. Seleziona framework e settore, valida competenze e importa con un click!</p>
            <div class="feature-list">
                <span class="feature-tag">Word .docx</span>
                <span class="feature-tag">XML Moodle</span>
                <span class="feature-tag">Validazione</span>
                <span class="feature-tag">Template</span>
            </div>
            <div style="margin-top: 15px;">
                <a href="import.php?courseid=<?php echo $courseid; ?>" class="btn btn-purple">📥 Avvia Import Wizard</a>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">📄</span> Import XML (Legacy)</h3>
            <p>Importa domande da file XML Moodle con assegnazione automatica delle competenze dal nome della domanda.</p>
            <a href="import_DEBUG.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">Importa XML</a>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">📝</span> Crea Quiz <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Wizard guidato per creare quiz selezionando domande dalla question bank e assegnando livelli di difficoltà.</p>
            <a href="create_quiz.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">Crea Quiz</a>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">📁</span> Aggiungi Categorie</h3>
            <p>Crea la struttura di categorie nella question bank basandosi sul framework competenze selezionato.</p>
            <a href="create_categories_from_framework.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">Aggiungi Categorie</a>
        </div>
        
    </div>

    <!-- SEZIONE: COMPETENZE -->
    <div class="section-title">🔗 Gestione Competenze</div>
    
    <div class="dashboard-grid">
        
        <div class="dashboard-card">
            <h3><span class="card-icon">🔗</span> Assegna Competenze <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Assegna automaticamente le competenze alle domande dei quiz leggendo il codice dal nome della domanda.</p>
            <a href="assign_competencies.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">Assegna Competenze</a>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">🔍</span> Diagnostica Framework <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Verifica le competenze disponibili nel framework e controlla la corrispondenza con le domande.</p>
            <a href="diagnostics.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">Esegui Diagnostica</a>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">📋</span> Diagnostica Framework v2</h3>
            <p>Report completo aree, competenze e domande con vista espandibile e statistiche dettagliate.</p>
            <a href="diagnostics_v2.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">Diagnostica v2</a>
        </div>
        
    </div>

    <!-- SEZIONE: AUDIT E VERIFICA -->
    <div class="section-title">🔎 Audit e Verifica Valutazioni</div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card" style="background: linear-gradient(135deg, #1e3c7215 0%, #2a529815 100%); border: 2px solid #1e3c72;">
            <h3><span class="card-icon">🔎</span> Sistema Audit Competenze <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Sistema completo per <strong>verificare e difendere le valutazioni</strong>. Include: analisi domande orfane, assegnazione automatica competenze, report dettagliati per quiz e audit individuale studenti con certificazione stampabile.</p>
            <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">🔎 Apri Sistema Audit</a>
        </div>
        
        <div class="dashboard-card" style="background: linear-gradient(135deg, #16a08515 0%, #1abc9c15 100%); border: 2px solid #16a085;">
            <h3><span class="card-icon">📋</span> Scheda Competenze <span class="status-badge badge-new">NUOVO</span></h3>
            <p>Centro unificato per <strong>audit, report e stampa</strong>. Report quiz, audit studenti, domande orfane e certificazioni - tutto in un'unica pagina con opzioni di stampa selezionabili.</p>
            <a href="scheda_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%);">📋 Apri Scheda Competenze</a>
        </div>
    </div>

    <!-- SEZIONE: REPORT E MANUTENZIONE -->
    <div class="section-title">📊 Report e Manutenzione</div>
    
    <div class="dashboard-grid">
        
        <div class="dashboard-card">
            <h3><span class="card-icon">📊</span> Report Grafico</h3>
            <p>Visualizza il report grafico delle competenze acquisite dagli studenti nei vari quiz del corso.</p>
            <a href="../competencyreport/index.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">Visualizza Report</a>
        </div>
        
        <div class="dashboard-card">
            <h3><span class="card-icon">🧹</span> Pulisci Categorie</h3>
            <p>Rimuovi le categorie vuote dalla question bank per mantenere la struttura ordinata.</p>
            <a href="cleanup_categories.php?courseid=<?php echo $courseid; ?>" class="btn btn-warning">Pulisci</a>
        </div>
        
    </div>

</div>

<?php
echo $OUTPUT->footer();
