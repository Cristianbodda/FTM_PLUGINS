<?php
// ============================================
// Self Assessment - Assegna Autovalutazioni
// ============================================
// Pagina per coach: assegna aree o competenze
// a singoli studenti o gruppi
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/selfassessment/assign.php'));
$PAGE->set_title('Assegna Autovalutazioni');
$PAGE->set_heading('Assegna Autovalutazioni');
$PAGE->set_pagelayout('admin');

// Verifica permessi
require_capability('local/selfassessment:manage', $context);

// Manager
$manager = new \local_selfassessment\manager();

// Parametri
$action = optional_param('action', '', PARAM_ALPHA);
$studentid = optional_param('studentid', 0, PARAM_INT);

// Gestisci azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $action = required_param('action', PARAM_ALPHA);
    
    if ($action === 'assign_area') {
        $area_prefix = required_param('area', PARAM_TEXT);
        $studentids = required_param_array('students', PARAM_INT);
        
        $total = 0;
        foreach ($studentids as $sid) {
            $total += $manager->assign_area($sid, $area_prefix, $USER->id);
        }
        
        redirect(
            new moodle_url('/local/selfassessment/assign.php'),
            "✅ Assegnate $total competenze a " . count($studentids) . " studente/i",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    
    if ($action === 'assign_competency') {
        $competencyid = required_param('competencyid', PARAM_INT);
        $studentids = required_param_array('students', PARAM_INT);
        
        $total = 0;
        foreach ($studentids as $sid) {
            if ($manager->assign_competency($sid, $competencyid, $USER->id)) {
                $total++;
            }
        }
        
        redirect(
            new moodle_url('/local/selfassessment/assign.php'),
            "✅ Competenza assegnata a $total studente/i",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Carica dati
$areas = $manager->get_available_areas();
$students = $manager->get_students_with_status('all', '', 0, 100);
$stats = $manager->get_assignment_stats();

// Carica tutte le competenze per selezione singola
$competencies = $DB->get_records_sql("
    SELECT c.id, c.idnumber, c.shortname
    FROM {competency} c
    JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
    WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
    ORDER BY c.idnumber
    LIMIT 500
");

echo $OUTPUT->header();
?>

<style>
.assign-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.assign-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;
}

.assign-header h1 {
    margin: 0 0 10px 0;
}

.stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.stat-box {
    background: white;
    border-radius: 12px;
    padding: 20px 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
    flex: 1;
    min-width: 150px;
}

.stat-box .value {
    font-size: 2em;
    font-weight: 700;
    color: #2c3e50;
}

.stat-box .label {
    color: #7f8c8d;
    font-size: 0.9em;
}

.assign-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.assign-card h2 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 1.3em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
}

.form-group select, .form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1em;
}

.form-group select:focus, .form-group input:focus {
    outline: none;
    border-color: #667eea;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.student-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.student-checkbox:hover {
    background: #e3f2fd;
}

.student-checkbox input {
    width: auto;
}

.btn-assign {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-assign:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.area-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}

.area-option {
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.area-option:hover {
    border-color: #667eea;
    transform: translateY(-2px);
}

.area-option.selected {
    border-color: #667eea;
    background: #667eea11;
}

.area-option .icon {
    font-size: 1.5em;
    display: block;
    margin-bottom: 5px;
}

.area-option .name {
    font-weight: 600;
    font-size: 0.9em;
}

.select-all-row {
    margin-bottom: 15px;
    padding: 10px;
    background: #e8f4fd;
    border-radius: 8px;
}

.select-all-row label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: 600;
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

<div class="assign-container">
    
    <a href="index.php" class="back-link">← Torna alla Dashboard</a>
    
    <!-- Header -->
    <div class="assign-header">
        <h1>📋 Assegna Autovalutazioni</h1>
        <p>Assegna aree o singole competenze agli studenti per l'autovalutazione</p>
    </div>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="value"><?php echo $stats['students_with_assignments']; ?></div>
            <div class="label">Studenti con assegnazioni</div>
        </div>
        <div class="stat-box">
            <div class="value"><?php echo $stats['from_quiz']; ?></div>
            <div class="label">Da Quiz (automatiche)</div>
        </div>
        <div class="stat-box">
            <div class="value"><?php echo $stats['from_coach']; ?></div>
            <div class="label">Da Coach (manuali)</div>
        </div>
    </div>
    
    <!-- Assegna per AREA -->
    <div class="assign-card">
        <h2>🎯 Assegna per Area</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px;">
            Seleziona un'area e gli studenti a cui assegnare tutte le competenze di quell'area.
        </p>
        
        <form method="post" action="">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="assign_area">
            
            <div class="form-group">
                <label>1. Seleziona Area</label>
                <div class="area-grid">
                    <?php foreach ($areas as $prefix => $info): ?>
                    <label class="area-option" onclick="selectArea(this, '<?php echo $prefix; ?>')">
                        <input type="radio" name="area" value="<?php echo $prefix; ?>" style="display:none;" required>
                        <span class="icon"><?php echo $info['icona']; ?></span>
                        <span class="name"><?php echo $info['nome']; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>2. Seleziona Studenti</label>
                <div class="select-all-row">
                    <label>
                        <input type="checkbox" onclick="toggleAllStudents(this, 'area_students')">
                        Seleziona tutti
                    </label>
                </div>
                <div class="students-grid" id="area_students">
                    <?php foreach ($students as $s): ?>
                    <label class="student-checkbox">
                        <input type="checkbox" name="students[]" value="<?php echo $s->id; ?>">
                        <?php echo fullname($s); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-assign">📤 Assegna Area</button>
        </form>
    </div>
    
    <!-- Assegna singola COMPETENZA -->
    <div class="assign-card">
        <h2>🔧 Assegna Singola Competenza</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px;">
            Seleziona una specifica competenza e gli studenti a cui assegnarla.
        </p>
        
        <form method="post" action="">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="assign_competency">
            
            <div class="form-group">
                <label>1. Seleziona Competenza</label>
                <select name="competencyid" required>
                    <option value="">-- Seleziona competenza --</option>
                    <?php foreach ($competencies as $c): ?>
                    <option value="<?php echo $c->id; ?>"><?php echo $c->idnumber; ?> - <?php echo $c->shortname ?: '(senza nome)'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>2. Seleziona Studenti</label>
                <div class="select-all-row">
                    <label>
                        <input type="checkbox" onclick="toggleAllStudents(this, 'comp_students')">
                        Seleziona tutti
                    </label>
                </div>
                <div class="students-grid" id="comp_students">
                    <?php foreach ($students as $s): ?>
                    <label class="student-checkbox">
                        <input type="checkbox" name="students[]" value="<?php echo $s->id; ?>">
                        <?php echo fullname($s); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-assign">📤 Assegna Competenza</button>
        </form>
    </div>
    
</div>

<script>
function selectArea(el, prefix) {
    // Rimuovi selezione da tutte le aree
    document.querySelectorAll('.area-option').forEach(opt => opt.classList.remove('selected'));
    // Aggiungi a questa
    el.classList.add('selected');
    // Seleziona il radio
    el.querySelector('input').checked = true;
}

function toggleAllStudents(checkbox, containerId) {
    const container = document.getElementById(containerId);
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}
</script>

<?php
echo $OUTPUT->footer();
