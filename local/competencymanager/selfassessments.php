<?php
/**
 * Gestione Autovalutazioni - Vista Coach
 * Mostra tutti gli studenti con le loro autovalutazioni raggruppate
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('local/competencymanager:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]);
$PAGE->set_title('Gestione Autovalutazioni');
$PAGE->set_heading('Gestione Autovalutazioni');
$PAGE->set_pagelayout('standard');

// Livelli Bloom
$bloomLevels = [
    1 => ['name' => 'Ricordare', 'color' => '#e74c3c', 'bg' => '#ffeaea'],
    2 => ['name' => 'Comprendere', 'color' => '#e67e22', 'bg' => '#fff5e6'],
    3 => ['name' => 'Applicare', 'color' => '#f1c40f', 'bg' => '#fffce6'],
    4 => ['name' => 'Analizzare', 'color' => '#27ae60', 'bg' => '#e8f8e8'],
    5 => ['name' => 'Valutare', 'color' => '#3498db', 'bg' => '#e8f4fc'],
    6 => ['name' => 'Creare', 'color' => '#9b59b6', 'bg' => '#f3e8fc']
];

// Recupera tutti gli studenti iscritti al corso con le loro autovalutazioni
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
               u.picture, u.imagealt, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               sc.sector, sc.area, sc.current_week
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        LEFT JOIN {local_student_coaching} sc ON sc.userid = u.id AND sc.courseid = :courseid2
        WHERE e.courseid = :courseid
        AND u.deleted = 0
        ORDER BY u.lastname, u.firstname";

$students = $DB->get_records_sql($sql, ['courseid' => $courseid, 'courseid2' => $courseid]);

// Filtra solo studenti (non docenti)
$studentrole = $DB->get_record('role', ['shortname' => 'student']);
$filteredStudents = [];
foreach ($students as $student) {
    // Verifica se è uno studente
    $isStudent = $DB->record_exists('role_assignments', [
        'userid' => $student->id,
        'roleid' => $studentrole->id,
        'contextid' => $context->id
    ]);
    if ($isStudent) {
        $filteredStudents[$student->id] = $student;
    }
}
$students = $filteredStudents;

// Recupera tutte le autovalutazioni
$allSelfAssessments = [];
if (!empty($students)) {
    $studentIds = array_keys($students);
    list($insql, $params) = $DB->get_in_or_equal($studentIds, SQL_PARAMS_NAMED);
    
    $sql = "SELECT sa.*, c.idnumber, c.shortname, c.description as comp_desc
            FROM {local_selfassessment} sa
            JOIN {competency} c ON c.id = sa.competencyid
            WHERE sa.userid $insql
            ORDER BY sa.userid, c.idnumber";
    
    $assessments = $DB->get_records_sql($sql, $params);
    
    foreach ($assessments as $sa) {
        if (!isset($allSelfAssessments[$sa->userid])) {
            $allSelfAssessments[$sa->userid] = [];
        }
        $allSelfAssessments[$sa->userid][] = $sa;
    }
}

// Statistiche globali
$totalStudents = count($students);
$studentsWithSA = count($allSelfAssessments);
$totalSA = 0;
foreach ($allSelfAssessments as $sas) {
    $totalSA += count($sas);
}

echo $OUTPUT->header();

// Tab navigazione
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/competencymanager/index.php', ['courseid' => $courseid]), 'Dashboard'),
    new tabobject('coaching', new moodle_url('/local/competencymanager/coaching.php', ['courseid' => $courseid]), 'Coaching'),
    new tabobject('selfassessments', new moodle_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]), 'Autovalutazioni'),
    new tabobject('reports', new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]), 'Report'),
];
echo $OUTPUT->tabtree($tabs, 'selfassessments');
?>

<style>
.sa-manager-container {
    max-width: 1200px;
    margin: 0 auto;
}

.sa-manager-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    text-align: center;
}

.sa-manager-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
}

/* Statistiche */
.stats-row {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
    flex-wrap: wrap;
}

.stat-box {
    background: rgba(255,255,255,0.2);
    padding: 15px 25px;
    border-radius: 10px;
    text-align: center;
}

.stat-box .num {
    font-size: 2em;
    font-weight: bold;
    display: block;
}

.stat-box .label {
    font-size: 0.85em;
    opacity: 0.9;
}

/* Filtri */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-size: 0.9em;
    color: #666;
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9em;
}

/* Student Card */
.student-sa-card {
    background: white;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.student-sa-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    cursor: pointer;
}

.student-sa-header:hover {
    opacity: 0.95;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
}

.student-avatar img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
}

.student-name {
    font-size: 1.2em;
    font-weight: bold;
}

.student-email {
    font-size: 0.85em;
    opacity: 0.9;
}

.student-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.badge-sm {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.75em;
    font-weight: 600;
}

.badge-sector {
    background: rgba(255,255,255,0.2);
}

.badge-count {
    background: #27ae60;
}

.badge-empty {
    background: #e74c3c;
}

.toggle-icon {
    font-size: 1.5em;
    transition: transform 0.3s;
}

.student-sa-card.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.student-sa-card.collapsed .student-sa-body {
    display: none;
}

/* Body con autovalutazioni */
.student-sa-body {
    padding: 20px;
}

.sa-summary {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.sa-summary-item {
    flex: 1;
    min-width: 150px;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}

.sa-summary-item .value {
    font-size: 1.5em;
    font-weight: bold;
    color: #333;
}

.sa-summary-item .label {
    font-size: 0.8em;
    color: #666;
}

/* Griglia autovalutazioni */
.sa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.sa-item {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    border-left: 5px solid;
    transition: all 0.2s;
}

.sa-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.sa-item.level-1 { border-left-color: #e74c3c; }
.sa-item.level-2 { border-left-color: #e67e22; }
.sa-item.level-3 { border-left-color: #f1c40f; }
.sa-item.level-4 { border-left-color: #27ae60; }
.sa-item.level-5 { border-left-color: #3498db; }
.sa-item.level-6 { border-left-color: #9b59b6; }

.sa-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.sa-item-code {
    font-weight: bold;
    color: #333;
    font-size: 0.9em;
}

.sa-item-level {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
}

.sa-item-level.l1 { background: #ffeaea; color: #e74c3c; }
.sa-item-level.l2 { background: #fff5e6; color: #e67e22; }
.sa-item-level.l3 { background: #fffce6; color: #b8860b; }
.sa-item-level.l4 { background: #e8f8e8; color: #27ae60; }
.sa-item-level.l5 { background: #e8f4fc; color: #3498db; }
.sa-item-level.l6 { background: #f3e8fc; color: #9b59b6; }

.sa-item-name {
    font-size: 0.85em;
    color: #666;
    margin-bottom: 8px;
}

.sa-item-comment {
    font-size: 0.8em;
    color: #888;
    font-style: italic;
    background: white;
    padding: 8px;
    border-radius: 6px;
    margin-top: 8px;
}

.sa-item-date {
    font-size: 0.75em;
    color: #999;
    margin-top: 8px;
}

/* Actions */
.student-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed #ddd;
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: 20px;
    font-size: 0.85em;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-action.primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-action.secondary {
    background: #e9ecef;
    color: #333;
}

.btn-action:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Empty state */
.empty-sa {
    text-align: center;
    padding: 30px;
    color: #999;
}

.empty-sa .icon {
    font-size: 3em;
    margin-bottom: 10px;
}

/* No students */
.no-students {
    background: white;
    border-radius: 15px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.no-students .icon {
    font-size: 4em;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .student-sa-header {
        flex-direction: column;
        text-align: center;
    }
    
    .student-info {
        flex-direction: column;
    }
    
    .sa-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="sa-manager-container">
    <!-- Header -->
    <div class="sa-manager-header">
        <h1>🎯 Gestione Autovalutazioni</h1>
        <p>Corso: <?php echo htmlspecialchars($course->fullname); ?></p>
        <div class="stats-row">
            <div class="stat-box">
                <span class="num"><?php echo $totalStudents; ?></span>
                <span class="label">Studenti</span>
            </div>
            <div class="stat-box">
                <span class="num"><?php echo $studentsWithSA; ?></span>
                <span class="label">Con autovalutazioni</span>
            </div>
            <div class="stat-box">
                <span class="num"><?php echo $totalSA; ?></span>
                <span class="label">Totale autovalutazioni</span>
            </div>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="filters-section">
        <div class="filter-group">
            <label>🔍</label>
            <input type="text" id="searchStudent" placeholder="Cerca studente..." onkeyup="filterStudents()">
        </div>
        <div class="filter-group">
            <label>📊 Mostra:</label>
            <select id="filterStatus" onchange="filterStudents()">
                <option value="all">Tutti</option>
                <option value="with">Con autovalutazioni</option>
                <option value="without">Senza autovalutazioni</option>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn-action secondary" onclick="expandAll()">📂 Espandi tutti</button>
            <button class="btn-action secondary" onclick="collapseAll()">📁 Comprimi tutti</button>
        </div>
    </div>
    
    <?php if (empty($students)): ?>
    <div class="no-students">
        <div class="icon">👥</div>
        <h3>Nessuno studente iscritto</h3>
        <p>Non ci sono studenti iscritti a questo corso.</p>
    </div>
    <?php else: ?>
    
    <!-- Lista Studenti -->
    <div id="studentsList">
        <?php foreach ($students as $student): 
            $studentSAs = isset($allSelfAssessments[$student->id]) ? $allSelfAssessments[$student->id] : [];
            $saCount = count($studentSAs);
            $hasSA = $saCount > 0;
            
            // Calcola media livello
            $avgLevel = 0;
            if ($hasSA) {
                $sum = 0;
                foreach ($studentSAs as $sa) {
                    $sum += $sa->level;
                }
                $avgLevel = round($sum / $saCount, 1);
            }
            
            // Avatar
            $userpicture = new user_picture($student);
            $userpicture->size = 50;
        ?>
        <div class="student-sa-card <?php echo $hasSA ? '' : 'collapsed'; ?>" 
             data-name="<?php echo strtolower($student->firstname . ' ' . $student->lastname); ?>"
             data-status="<?php echo $hasSA ? 'with' : 'without'; ?>">
            
            <div class="student-sa-header" onclick="toggleCard(this)">
                <div class="student-info">
                    <div class="student-avatar">
                        <?php echo $OUTPUT->render($userpicture); ?>
                    </div>
                    <div>
                        <div class="student-name"><?php echo htmlspecialchars($student->firstname . ' ' . $student->lastname); ?></div>
                        <div class="student-email"><?php echo htmlspecialchars($student->email); ?></div>
                    </div>
                </div>
                <div class="student-badges">
                    <?php if ($student->sector): ?>
                    <span class="badge-sm badge-sector">🏭 <?php echo htmlspecialchars($student->sector); ?></span>
                    <?php endif; ?>
                    <?php if ($student->area): ?>
                    <span class="badge-sm badge-sector">🎯 <?php echo htmlspecialchars($student->area); ?></span>
                    <?php endif; ?>
                    <?php if ($hasSA): ?>
                    <span class="badge-sm badge-count">✅ <?php echo $saCount; ?> autovalutazioni</span>
                    <?php else: ?>
                    <span class="badge-sm badge-empty">⚠️ Nessuna autovalutazione</span>
                    <?php endif; ?>
                </div>
                <span class="toggle-icon">▼</span>
            </div>
            
            <div class="student-sa-body">
                <?php if ($hasSA): ?>
                
                <!-- Riepilogo -->
                <div class="sa-summary">
                    <div class="sa-summary-item">
                        <div class="value"><?php echo $saCount; ?></div>
                        <div class="label">Competenze valutate</div>
                    </div>
                    <div class="sa-summary-item">
                        <div class="value"><?php echo $avgLevel; ?></div>
                        <div class="label">Media livello Bloom</div>
                    </div>
                    <div class="sa-summary-item">
                        <div class="value"><?php echo date('d/m/Y', end($studentSAs)->timecreated); ?></div>
                        <div class="label">Ultima valutazione</div>
                    </div>
                </div>
                
                <!-- Griglia autovalutazioni -->
                <div class="sa-grid">
                    <?php foreach ($studentSAs as $sa): 
                        $bloom = $bloomLevels[$sa->level];
                    ?>
                    <div class="sa-item level-<?php echo $sa->level; ?>">
                        <div class="sa-item-header">
                            <span class="sa-item-code"><?php echo htmlspecialchars($sa->idnumber); ?></span>
                            <span class="sa-item-level l<?php echo $sa->level; ?>"><?php echo $sa->level; ?> <?php echo $bloom['name']; ?></span>
                        </div>
                        <div class="sa-item-name"><?php echo htmlspecialchars($sa->shortname ?: $sa->idnumber); ?></div>
                        <?php if (!empty($sa->comment)): ?>
                        <div class="sa-item-comment">"<?php echo htmlspecialchars($sa->comment); ?>"</div>
                        <?php endif; ?>
                        <div class="sa-item-date">📅 <?php echo date('d/m/Y H:i', $sa->timecreated); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php else: ?>
                
                <div class="empty-sa">
                    <div class="icon">📝</div>
                    <p>Questo studente non ha ancora compilato autovalutazioni.</p>
                </div>
                
                <?php endif; ?>
                
                <!-- Azioni -->
                <div class="student-actions">
                    <a href="student_selfassessments.php?studentid=<?php echo $student->id; ?>&courseid=<?php echo $courseid; ?>" class="btn-action primary">
                        📊 Dettaglio completo
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/local/competencyreport/student.php?userid=<?php echo $student->id; ?>&courseid=<?php echo $courseid; ?>" class="btn-action secondary">
                        📈 Report Quiz
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
function toggleCard(header) {
    const card = header.closest('.student-sa-card');
    card.classList.toggle('collapsed');
}

function expandAll() {
    document.querySelectorAll('.student-sa-card').forEach(card => {
        card.classList.remove('collapsed');
    });
}

function collapseAll() {
    document.querySelectorAll('.student-sa-card').forEach(card => {
        card.classList.add('collapsed');
    });
}

function filterStudents() {
    const search = document.getElementById('searchStudent').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    
    document.querySelectorAll('.student-sa-card').forEach(card => {
        const name = card.dataset.name;
        const cardStatus = card.dataset.status;
        
        let show = true;
        
        if (search && !name.includes(search)) show = false;
        if (status !== 'all' && cardStatus !== status) show = false;
        
        card.style.display = show ? 'block' : 'none';
    });
}
</script>

<?php
echo $OUTPUT->footer();
