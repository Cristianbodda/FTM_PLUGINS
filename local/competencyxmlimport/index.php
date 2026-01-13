<?php
/**
 * FTM Suite - Hub Centrale
 * 
 * Pagina principale con link a tutti gli strumenti FTM
 * URL: /local/competencyxmlimport/
 * 
 * @package    local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/competencyxmlimport/index.php');
$PAGE->set_title('FTM Suite - Strumenti');
$PAGE->set_heading('FTM Suite - Strumenti Import e Competenze');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
.ftm-hub {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.ftm-hub h1 {
    color: #1a73e8;
    border-bottom: 3px solid #1a73e8;
    padding-bottom: 10px;
    margin-bottom: 30px;
}
.ftm-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.ftm-card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    display: block;
}
.ftm-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}
.ftm-card-icon {
    font-size: 40px;
    margin-bottom: 15px;
}
.ftm-card-title {
    font-size: 1.3em;
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}
.ftm-card-desc {
    color: #666;
    font-size: 0.95em;
    line-height: 1.5;
}
.ftm-card.primary {
    background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
    color: white;
}
.ftm-card.primary .ftm-card-title,
.ftm-card.primary .ftm-card-desc {
    color: white;
}
.ftm-section-title {
    font-size: 1.2em;
    color: #555;
    margin: 30px 0 15px 0;
    padding-left: 10px;
    border-left: 4px solid #1a73e8;
}
.ftm-course-select {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.ftm-course-select select {
    padding: 10px 15px;
    font-size: 1em;
    border-radius: 6px;
    border: 1px solid #ddd;
    min-width: 300px;
}
.ftm-quick-links {
    background: #e3f2fd;
    padding: 15px 20px;
    border-radius: 8px;
    margin-top: 20px;
}
.ftm-quick-links a {
    margin-right: 20px;
    color: #1565c0;
}
</style>

<div class="ftm-hub">
    <h1>🛠️ FTM Suite</h1>
    
    <div class="ftm-course-select">
        <strong>📚 Seleziona Corso:</strong><br><br>
        <select id="course-select" onchange="updateLinks()">
            <option value="">-- Seleziona un corso --</option>
            <?php
            // Recupera corsi dove l'utente ha accesso
            $courses = enrol_get_my_courses();
            foreach ($courses as $course) {
                echo '<option value="' . $course->id . '">' . format_string($course->fullname) . '</option>';
            }
            ?>
        </select>
    </div>
    
    <div class="ftm-section-title">📥 Import Quiz e Domande</div>
    <div class="ftm-cards">
        <a href="#" class="ftm-card primary" id="link-setup-universale">
            <div class="ftm-card-icon">🎯</div>
            <div class="ftm-card-title">Setup Universale</div>
            <div class="ftm-card-desc">Import completo: Word → XML → Quiz con assegnazione competenze automatica. Supporta tutti i settori.</div>
        </a>
        
        <a href="#" class="ftm-card" id="link-create-quiz">
            <div class="ftm-card-icon">📝</div>
            <div class="ftm-card-title">Crea Quiz</div>
            <div class="ftm-card-desc">Wizard guidato per creare quiz e importare domande XML.</div>
        </a>
        
        <a href="#" class="ftm-card" id="link-dashboard">
            <div class="ftm-card-icon">📊</div>
            <div class="ftm-card-title">Dashboard Import</div>
            <div class="ftm-card-desc">Panoramica di tutti i quiz e domande importate nel corso.</div>
        </a>
    </div>
    
    <div class="ftm-section-title">🎓 Gestione Competenze</div>
    <div class="ftm-cards">
        <a href="#" class="ftm-card" id="link-assign-comp">
            <div class="ftm-card-icon">🔗</div>
            <div class="ftm-card-title">Assegna Competenze</div>
            <div class="ftm-card-desc">Collega domande esistenti alle competenze del framework.</div>
        </a>
        
        <a href="#" class="ftm-card" id="link-diagnostics">
            <div class="ftm-card-icon">🔍</div>
            <div class="ftm-card-title">Diagnostica Framework</div>
            <div class="ftm-card-desc">Verifica competenze disponibili e corrispondenza con domande.</div>
        </a>
    </div>
    
    <div class="ftm-section-title">🔧 Strumenti e Manutenzione</div>
    <div class="ftm-cards">
        <a href="#" class="ftm-card" id="link-cleanup">
            <div class="ftm-card-icon">🧹</div>
            <div class="ftm-card-title">Pulizia Quiz</div>
            <div class="ftm-card-desc">Elimina quiz rotti o vuoti e libera spazio.</div>
        </a>
        
        <a href="<?php echo new moodle_url('/admin/purgecaches.php'); ?>" class="ftm-card">
            <div class="ftm-card-icon">🗑️</div>
            <div class="ftm-card-title">Svuota Cache</div>
            <div class="ftm-card-desc">Pulisce tutte le cache di Moodle.</div>
        </a>
    </div>
    
    <div class="ftm-quick-links">
        <strong>🔗 Link Rapidi:</strong>
        <a href="<?php echo new moodle_url('/admin/tool/lp/competencyframeworks.php'); ?>">Framework Competenze</a>
        <a href="<?php echo new moodle_url('/question/edit.php'); ?>">Banca Domande</a>
        <a href="<?php echo new moodle_url('/my/'); ?>">I Miei Corsi</a>
    </div>
</div>

<script>
function updateLinks() {
    var courseId = document.getElementById('course-select').value;
    var baseUrl = '<?php echo $CFG->wwwroot; ?>/local/competencyxmlimport/';
    
    if (courseId) {
        document.getElementById('link-setup-universale').href = baseUrl + 'setup_universale.php?courseid=' + courseId;
        document.getElementById('link-create-quiz').href = baseUrl + 'create_quiz.php?courseid=' + courseId;
        document.getElementById('link-dashboard').href = baseUrl + 'dashboard.php?courseid=' + courseId;
        document.getElementById('link-assign-comp').href = baseUrl + 'assign_competencies.php?courseid=' + courseId;
        document.getElementById('link-diagnostics').href = baseUrl + 'diagnostics.php?courseid=' + courseId;
        document.getElementById('link-cleanup').href = baseUrl + 'cleanup_broken_quizzes.php?courseid=' + courseId;
    } else {
        // Reset links
        var links = ['link-setup-universale', 'link-create-quiz', 'link-dashboard', 
                     'link-assign-comp', 'link-diagnostics', 'link-cleanup'];
        links.forEach(function(id) {
            document.getElementById(id).href = '#';
        });
    }
}

// Auto-select if courseid in URL
var urlParams = new URLSearchParams(window.location.search);
var courseIdParam = urlParams.get('courseid');
if (courseIdParam) {
    document.getElementById('course-select').value = courseIdParam;
    updateLinks();
}
</script>

<?php
echo $OUTPUT->footer();
