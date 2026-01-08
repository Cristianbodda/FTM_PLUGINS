<?php
// ============================================
// FTM Hub - Pagina Principale
// ============================================
// Centro di accesso a tutti gli strumenti FTM
// organizzati per categoria
// ============================================

require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);

// Setup pagina
require_login();

if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

// Verifica permessi
$is_admin = has_capability('moodle/site:config', context_system::instance());
$is_teacher = $courseid > 0 && has_capability('moodle/course:manageactivities', $context);
$is_student = $courseid > 0 && has_capability('mod/quiz:attempt', $context) && !$is_teacher;

$PAGE->set_url(new moodle_url('/local/ftm_hub/index.php', $courseid > 0 ? ['courseid' => $courseid] : []));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_ftm_hub'));
$PAGE->set_heading(get_string('pluginname', 'local_ftm_hub'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
.ftm-hub {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.ftm-hub-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    text-align: center;
}

.ftm-hub-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 700;
}

.ftm-hub-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.ftm-course-selector {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.ftm-course-selector label {
    font-weight: 600;
    color: #495057;
}

.ftm-course-selector select {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1em;
    max-width: 400px;
}

.ftm-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 30px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.ftm-section-title h2 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 600;
    color: #343a40;
}

.ftm-section-title .icon {
    font-size: 1.5em;
}

.ftm-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ftm-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    text-decoration: none;
    color: inherit;
    display: block;
}

.ftm-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #667eea;
}

.ftm-card-icon {
    font-size: 2.5em;
    margin-bottom: 15px;
}

.ftm-card-title {
    font-size: 1.2em;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 8px;
}

.ftm-card-desc {
    color: #6c757d;
    font-size: 0.9em;
    line-height: 1.5;
}

.ftm-card-disabled {
    opacity: 0.5;
    pointer-events: none;
}

.ftm-card-disabled .ftm-card-title::after {
    content: ' (seleziona un corso)';
    font-size: 0.75em;
    color: #dc3545;
    font-weight: normal;
}

/* Colori per sezioni */
.ftm-section-creation .ftm-card:hover { border-color: #28a745; }
.ftm-section-management .ftm-card:hover { border-color: #17a2b8; }
.ftm-section-reports .ftm-card:hover { border-color: #6f42c1; }
.ftm-section-debug .ftm-card:hover { border-color: #fd7e14; }
.ftm-section-student .ftm-card:hover { border-color: #20c997; }

/* Card highlight */
.ftm-card-highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.ftm-card-highlight .ftm-card-title,
.ftm-card-highlight .ftm-card-desc {
    color: white;
}

.ftm-card-highlight:hover {
    border-color: white;
}

/* Notice */
.ftm-notice {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    color: #856404;
}

.ftm-notice strong {
    color: #664d03;
}

/* Footer */
.ftm-hub-footer {
    text-align: center;
    padding: 20px;
    color: #6c757d;
    font-size: 0.9em;
    border-top: 1px solid #e9ecef;
    margin-top: 30px;
}
</style>

<div class="ftm-hub">
    
    <!-- Header -->
    <div class="ftm-hub-header">
        <h1>🛠️ FTM Tools</h1>
        <p><?php echo get_string('hub_description', 'local_ftm_hub'); ?></p>
    </div>
    
    <!-- Selettore Corso -->
    <div class="ftm-course-selector">
        <label for="course-select">📚 <?php echo get_string('select_course', 'local_ftm_hub'); ?>:</label>
        <select id="course-select" onchange="changeCourse(this.value)">
            <option value="0"><?php echo get_string('no_course', 'local_ftm_hub'); ?></option>
            <?php
            // Lista corsi dove l'utente ha accesso
            $mycourses = enrol_get_my_courses();
            foreach ($mycourses as $c) {
                $selected = ($c->id == $courseid) ? 'selected' : '';
                echo "<option value=\"{$c->id}\" {$selected}>" . format_string($c->fullname) . "</option>";
            }
            ?>
        </select>
    </div>
    
    <?php if ($courseid == 0): ?>
    <div class="ftm-notice">
        <strong>ℹ️ <?php echo get_string('notice_title', 'local_ftm_hub'); ?>:</strong>
        <?php echo get_string('notice_text', 'local_ftm_hub'); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($is_admin || $is_teacher): ?>
    
    <!-- SEZIONE CREAZIONE -->
    <div class="ftm-section-creation">
        <div class="ftm-section-title">
            <span class="icon">📝</span>
            <h2><?php echo get_string('section_creation', 'local_ftm_hub'); ?></h2>
        </div>
        <div class="ftm-cards-grid">
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📝</div>
                <div class="ftm-card-title"><?php echo get_string('create_quiz', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('create_quiz_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/import.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📤</div>
                <div class="ftm-card-title"><?php echo get_string('import_questions', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('import_questions_desc', 'local_ftm_hub'); ?></div>
            </a>
        </div>
    </div>
    
    <!-- SEZIONE GESTIONE -->
    <div class="ftm-section-management">
        <div class="ftm-section-title">
            <span class="icon">⚙️</span>
            <h2><?php echo get_string('section_management', 'local_ftm_hub'); ?></h2>
        </div>
        <div class="ftm-cards-grid">
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/assign_competencies.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🎯</div>
                <div class="ftm-card-title"><?php echo get_string('assign_competencies', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('assign_competencies_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/manage_competencies.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📋</div>
                <div class="ftm-card-title"><?php echo get_string('manage_assignments', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('manage_assignments_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/fix_quiz_reviews.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🔧</div>
                <div class="ftm-card-title"><?php echo get_string('fix_reviews', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('fix_reviews_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🔐</div>
                <div class="ftm-card-title"><?php echo get_string('manage_auth', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('manage_auth_desc', 'local_ftm_hub'); ?></div>
            </a>
        </div>
    </div>
    
    <!-- SEZIONE REPORT -->
    <div class="ftm-section-reports">
        <div class="ftm-section-title">
            <span class="icon">📊</span>
            <h2><?php echo get_string('section_reports', 'local_ftm_hub'); ?></h2>
        </div>
        <div class="ftm-cards-grid">
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card ftm-card-highlight <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📊</div>
                <div class="ftm-card-title"><?php echo get_string('class_report', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('class_report_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">✍️</div>
                <div class="ftm-card-title"><?php echo get_string('selfassessments', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('selfassessments_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/export.php', ['courseid' => $courseid, 'format' => 'csv']))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📥</div>
                <div class="ftm-card-title"><?php echo get_string('export_data', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('export_data_desc', 'local_ftm_hub'); ?></div>
            </a>
        </div>
    </div>
    
    <?php if ($is_admin): ?>
    <!-- SEZIONE DEBUG -->
    <div class="ftm-section-debug">
        <div class="ftm-section-title">
            <span class="icon">🔧</span>
            <h2><?php echo get_string('section_debug', 'local_ftm_hub'); ?></h2>
        </div>
        <div class="ftm-cards-grid">
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/question_check.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🔍</div>
                <div class="ftm-card-title"><?php echo get_string('question_check', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('question_check_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/debug_competencies.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🐛</div>
                <div class="ftm-card-title"><?php echo get_string('debug_competencies', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('debug_competencies_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/diagnostics.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">🩺</div>
                <div class="ftm-card-title"><?php echo get_string('diagnostics', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('diagnostics_desc', 'local_ftm_hub'); ?></div>
            </a>
            
            <a href="<?php echo (new moodle_url('/local/competencymanager/system_check.php'))->out(); ?>" 
               class="ftm-card">
                <div class="ftm-card-icon">🔬</div>
                <div class="ftm-card-title">System Check FTM</div>
                <div class="ftm-card-desc">Verifica completa di tutti i plugin FTM: tabelle, componenti e test funzionali.</div>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; // end admin/teacher ?>
    
    <!-- SEZIONE STUDENTE -->
    <div class="ftm-section-student">
        <div class="ftm-section-title">
            <span class="icon">👨‍🎓</span>
            <h2><?php echo get_string('section_student', 'local_ftm_hub'); ?></h2>
        </div>
        <div class="ftm-cards-grid">
            <?php if ($is_student): ?>
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/student_report.php', ['courseid' => $courseid, 'userid' => $USER->id]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">📊</div>
                <div class="ftm-card-title"><?php echo get_string('my_report', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('my_report_desc', 'local_ftm_hub'); ?></div>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo $courseid > 0 ? (new moodle_url('/local/competencymanager/selfassessment.php', ['courseid' => $courseid]))->out() : '#'; ?>" 
               class="ftm-card <?php echo $courseid == 0 ? 'ftm-card-disabled' : ''; ?>">
                <div class="ftm-card-icon">✍️</div>
                <div class="ftm-card-title"><?php echo get_string('my_selfassessment', 'local_ftm_hub'); ?></div>
                <div class="ftm-card-desc"><?php echo get_string('my_selfassessment_desc', 'local_ftm_hub'); ?></div>
            </a>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="ftm-hub-footer">
        <p>FTM Tools v1.0.0 | Passaporto Tecnico FTM</p>
    </div>
    
</div>

<script>
function changeCourse(courseid) {
    if (courseid > 0) {
        window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_hub/index.php?courseid=' + courseid;
    } else {
        window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_hub/index.php';
    }
}
</script>

<?php
echo $OUTPUT->footer();
