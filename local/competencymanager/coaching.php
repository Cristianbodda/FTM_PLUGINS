<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
require_capability('local/competencymanager:view', $context);
$canmanage = has_capability('local/competencymanager:managecoaching', $context);
$canassign = has_capability('local/competencymanager:assigncoach', $context);

$PAGE->set_url('/local/competencymanager/coaching.php', ['courseid' => $courseid]);
$PAGE->set_title('Gestione Coaching - ' . $course->shortname);
$PAGE->set_heading('Gestione Coaching');
$PAGE->set_pagelayout('standard');

$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
        sc.id as coaching_id, sc.coachid, sc.sector, sc.area,
        sc.date_start, sc.date_end, sc.date_extended,
        sc.current_week, sc.status, sc.notes,
        coach.firstname as coach_firstname, coach.lastname as coach_lastname,
        (SELECT COUNT(*) FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
         WHERE qa.userid = u.id AND q.course = :courseid1 AND qa.state = 'finished') as quiz_count,
        (SELECT COUNT(*) FROM {local_selfassessment} sa WHERE sa.userid = u.id) as selfassess_count
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        LEFT JOIN {local_student_coaching} sc ON sc.userid = u.id AND sc.courseid = :courseid2
        LEFT JOIN {user} coach ON coach.id = sc.coachid
        WHERE e.courseid = :courseid3 AND u.deleted = 0
        ORDER BY u.lastname, u.firstname";
$students = $DB->get_records_sql($sql, ['courseid1' => $courseid, 'courseid2' => $courseid, 'courseid3' => $courseid]);

$stats = ['total' => count($students), 'active' => 0, 'delayed' => 0, 'completed' => 0, 'no_sector' => 0, 'quiz_done' => 0];
foreach ($students as $s) {
    if ($s->status == 'active') $stats['active']++;
    if ($s->status == 'delayed') $stats['delayed']++;
    if ($s->status == 'completed') $stats['completed']++;
    if (empty($s->sector)) $stats['no_sector']++;
    $stats['quiz_done'] += $s->quiz_count;
}

$areas_by_sector = [
    'AUTOMOBILE' => ['MR' => 'MR - Manutenzione', 'EL' => 'EL - Elettronica', 'MO' => 'MO - Motore', 'HV' => 'HV - Alta Tensione'],
    'CHIMFARM' => ['1C' => '1C - Conformità GMP', '1O' => '1O - Operazioni', '3C' => '3C - Controllo Qualità', '4S' => '4S - Sicurezza', '5S' => '5S - Sterilità', '6P' => '6P - Produzione'],
    'MECCANICA' => ['BASE' => 'BASE - Competenze Base', 'CNC' => 'CNC - Macchine CNC', 'CAD' => 'CAD - Progettazione']
];

echo $OUTPUT->header();
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/competencymanager/index.php', ['courseid' => $courseid]), 'Dashboard'),
    new tabobject('students', new moodle_url('/local/competencymanager/students.php', ['courseid' => $courseid]), 'Studenti'),
    new tabobject('coaching', new moodle_url('/local/competencymanager/coaching.php', ['courseid' => $courseid]), 'Coaching'),
    new tabobject('selfassessments', new moodle_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]), 'Autovalutazioni'),
    new tabobject('reports', new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]), 'Report'),
];
echo $OUTPUT->tabtree($tabs, 'coaching');
?>
<style>
.coaching-container{max-width:1400px;margin:0 auto}
.page-header-coaching{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:20px;border-radius:15px;margin-bottom:25px;text-align:center}
.main-actions-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:30px}
@media(max-width:992px){.main-actions-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.main-actions-grid{grid-template-columns:1fr}}
.action-card{background:#fff;border-radius:15px;padding:25px 20px;text-align:center;box-shadow:0 5px 20px rgba(0,0,0,.1);transition:all .3s;cursor:pointer;border-top:5px solid;position:relative;overflow:hidden}
.action-card:hover{transform:translateY(-8px);box-shadow:0 10px 30px rgba(0,0,0,.2)}
.action-card.autoval{border-top-color:#11998e}.action-card.quiz{border-top-color:#f5576c}.action-card.colloquio{border-top-color:#667eea}.action-card.matching{border-top-color:#f39c12}
.action-card-icon{font-size:2.5em;margin-bottom:12px}.action-card-title{font-size:1.2em;font-weight:700;margin-bottom:8px;color:#333}.action-card-desc{color:#666;font-size:.9em;margin-bottom:15px}
.action-card-btn{display:inline-block;padding:10px 20px;border-radius:25px;color:#fff;font-weight:700;text-decoration:none;font-size:.9em}
.action-card.autoval .action-card-btn{background:linear-gradient(135deg,#11998e,#38ef7d)}.action-card.quiz .action-card-btn{background:linear-gradient(135deg,#f093fb,#f5576c)}.action-card.colloquio .action-card-btn{background:linear-gradient(135deg,#667eea,#764ba2)}.action-card.matching .action-card-btn{background:linear-gradient(135deg,#f39c12,#e74c3c)}
.coming-soon-badge{position:absolute;top:10px;right:-30px;background:#e74c3c;color:#fff;padding:5px 40px;font-size:.7em;font-weight:700;transform:rotate(45deg)}
.filters-section{background:#fff;border-radius:12px;padding:15px 20px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,.1)}
.filters-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.filter-group{display:flex;align-items:center;gap:6px}
.filter-group label{font-size:.85em;color:#666}
.filter-group select,.filter-group input{padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:.85em}
.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px}
@media(max-width:992px){.stats-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:576px){.stats-row{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:#fff;border-radius:10px;padding:15px 10px;box-shadow:0 2px 10px rgba(0,0,0,.1);text-align:center}
.stat-card .number{font-size:1.8em;font-weight:700}.stat-card .label{font-size:.8em;color:#666}
.student-card{background:#f8f9fa;border-radius:12px;margin-bottom:15px;box-shadow:0 3px 15px rgba(0,0,0,.08);border-left:5px solid #667eea;overflow:hidden}
.student-card:hover{box-shadow:0 5px 20px rgba(0,0,0,.12)}
.student-card.status-delayed{border-left-color:#e74c3c}.student-card.status-completed{border-left-color:#2ecc71}.student-card.no-sector{border-left-color:#f39c12}
.student-card-header{background:linear-gradient(135deg,#f8f9fa,#e9ecef);padding:12px 15px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-bottom:1px solid #dee2e6}
.student-main-info{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.student-name{font-size:1.1em;font-weight:700;color:#333}
.badge-sm{padding:3px 8px;border-radius:10px;font-size:.7em;font-weight:700}
.badge-coach{background:#e3f2fd;color:#1976d2}.badge-sector{background:#f3e5f5;color:#7b1fa2}.badge-week{background:#e8f5e9;color:#388e3c}.badge-delayed{background:#ffebee;color:#c62828}.badge-nosector{background:#fff3e0;color:#e65100}
.header-actions{display:flex;gap:6px}
.btn-icon{width:32px;height:32px;border-radius:50%;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9em;text-decoration:none}
.btn-icon:hover{transform:scale(1.1)}.btn-email{background:#3498db;color:#fff}.btn-report{background:#667eea;color:#fff}.btn-autoval{background:#11998e;color:#fff}.btn-quiz{background:#f5576c;color:#fff}
.student-card-body{padding:15px;background:#fff}
.student-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px}
.info-item label{display:block;font-size:.75em;color:#666;text-transform:uppercase;margin-bottom:4px}
.info-item select,.info-item input{width:100%;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:.9em}
.info-item select:focus,.info-item input:focus{border-color:#667eea;outline:none}
.info-item select.empty{border-color:#f39c12;background:#fffbf0}
.progress-row{display:flex;gap:20px;margin-top:15px;flex-wrap:wrap}
.progress-item{flex:1;min-width:100px}
.progress-item .label{font-size:.75em;color:#666;margin-bottom:4px}
.progress-bar-mini{height:8px;background:#e9ecef;border-radius:4px;overflow:hidden}
.progress-fill{height:100%;border-radius:4px}
.progress-fill.blue{background:linear-gradient(90deg,#3498db,#2980b9)}.progress-fill.green{background:linear-gradient(90deg,#2ecc71,#27ae60)}
.progress-item .value{font-size:.8em;font-weight:600;margin-top:2px}
.quick-actions{margin-top:15px;padding-top:15px;border-top:1px dashed #dee2e6;display:flex;gap:8px;flex-wrap:wrap}
.btn-quick{padding:6px 12px;border:none;border-radius:20px;font-size:.8em;cursor:pointer;display:flex;align-items:center;gap:4px;text-decoration:none}
.btn-quick.green{background:#2ecc71;color:#fff}.btn-quick.blue{background:#3498db;color:#fff}.btn-quick.purple{background:#667eea;color:#fff}
</style>
<div class="coaching-container">
<div class="page-header-coaching"><h1>👥 Gestione Coaching</h1><p>Corso: <?php echo htmlspecialchars($course->fullname); ?></p></div>
<div class="main-actions-grid">
<div class="action-card autoval" onclick="window.location='selfassessments.php?courseid=<?php echo $courseid; ?>'"><div class="action-card-icon">🎯</div><div class="action-card-title">Autovalutazioni</div><div class="action-card-desc">Gestisci le autovalutazioni Bloom</div><a href="selfassessments.php?courseid=<?php echo $courseid; ?>" class="action-card-btn">Vai →</a></div>
<div class="action-card quiz" onclick="window.location='<?php echo $CFG->wwwroot; ?>/local/competencyreport/index.php?courseid=<?php echo $courseid; ?>'"><div class="action-card-icon">📈</div><div class="action-card-title">Report Quiz</div><div class="action-card-desc">Risultati competenze dai quiz</div><a href="<?php echo $CFG->wwwroot; ?>/local/competencyreport/index.php?courseid=<?php echo $courseid; ?>" class="action-card-btn">Vai →</a></div>
<div class="action-card colloquio" onclick="window.location='reports.php?courseid=<?php echo $courseid; ?>'"><div class="action-card-icon">📊</div><div class="action-card-title">Report Colloquio</div><div class="action-card-desc">Radar chart e gap analysis</div><a href="reports.php?courseid=<?php echo $courseid; ?>" class="action-card-btn">Vai →</a></div>
<div class="action-card matching" style="opacity:.7;cursor:not-allowed"><div class="coming-soon-badge">COMING SOON</div><div class="action-card-icon">🔍</div><div class="action-card-title">Talent Matching</div><div class="action-card-desc">Matching con offerte lavoro</div><span class="action-card-btn" style="opacity:.5">Prossimamente</span></div>
</div>
<div class="filters-section"><div class="filters-row">
<div class="filter-group"><label>🔍</label><input type="text" id="searchStudent" placeholder="Cerca studente..." onkeyup="filterStudents()"></div>
<div class="filter-group"><label>📍 Stato:</label><select id="filterStatus" onchange="filterStudents()"><option value="">Tutti</option><option value="active">🔵 In corso</option><option value="delayed">🔴 In ritardo</option><option value="completed">🟢 Completati</option><option value="nosector">🟠 Senza settore</option></select></div>
<div class="filter-group"><label>🏭 Settore:</label><select id="filterSector" onchange="filterStudents()"><option value="">Tutti</option><option value="AUTOMOBILE">AUTOMOBILE</option><option value="CHIMFARM">CHIMFARM</option><option value="MECCANICA">MECCANICA</option></select></div>
</div></div>
<div class="stats-row">
<div class="stat-card"><div class="number"><?php echo $stats['total']; ?></div><div class="label">Totali</div></div>
<div class="stat-card"><div class="number" style="color:#667eea"><?php echo $stats['active']; ?></div><div class="label">In Corso</div></div>
<div class="stat-card"><div class="number" style="color:#e74c3c"><?php echo $stats['delayed']; ?></div><div class="label">In Ritardo</div></div>
<div class="stat-card"><div class="number" style="color:#2ecc71"><?php echo $stats['completed']; ?></div><div class="label">Completati</div></div>
<div class="stat-card"><div class="number" style="color:#667eea"><?php echo $stats['quiz_done']; ?></div><div class="label">Quiz Fatti</div></div>
<div class="stat-card"><div class="number" style="color:#f39c12"><?php echo $stats['no_sector']; ?></div><div class="label">Senza Settore</div></div>
</div>
<div id="studentsList">
<?php foreach ($students as $student): 
$cardClass = 'student-card';
if (empty($student->sector)) $cardClass .= ' no-sector';
elseif ($student->status == 'delayed') $cardClass .= ' status-delayed';
elseif ($student->status == 'completed') $cardClass .= ' status-completed';
?>
<div class="<?php echo $cardClass; ?>" data-name="<?php echo strtolower($student->lastname.' '.$student->firstname); ?>" data-status="<?php echo empty($student->sector)?'nosector':($student->status?:'active'); ?>" data-sector="<?php echo $student->sector; ?>">
<div class="student-card-header">
<div class="student-main-info">
<span class="student-name">👤 <?php echo htmlspecialchars($student->lastname.' '.$student->firstname); ?></span>
<?php if($student->sector): ?><span class="badge-sm badge-sector"><?php echo $student->sector; ?></span><?php else: ?><span class="badge-sm badge-nosector">⚠️ Senza settore</span><?php endif; ?>
<?php if($student->current_week): ?><span class="badge-sm badge-week">Sett. <?php echo $student->current_week; ?>/6</span><?php endif; ?>
</div>
<div class="header-actions">
<a href="student_selfassessments.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $student->id; ?>" class="btn-icon btn-autoval" title="Autovalutazioni">🎯</a>
<a href="<?php echo $CFG->wwwroot; ?>/local/competencyreport/student.php?userid=<?php echo $student->id; ?>&courseid=<?php echo $courseid; ?>" class="btn-icon btn-quiz" title="Report Quiz">📈</a>
<a href="reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $student->id; ?>" class="btn-icon btn-report" title="Report Colloquio">📊</a>
</div>
</div>
<div class="student-card-body">
<div class="student-info-grid">
<div class="info-item"><label>📅 Entrata Misura</label><input type="date" value="<?php echo $student->date_start?date('Y-m-d',$student->date_start):''; ?>" onchange="saveCoachingData(<?php echo $student->id; ?>,'date_start',this.value)" <?php echo $canmanage?'':'disabled'; ?>></div>
<div class="info-item"><label>📅 Fine Prevista</label><input type="date" value="<?php echo $student->date_end?date('Y-m-d',$student->date_end):''; ?>" onchange="saveCoachingData(<?php echo $student->id; ?>,'date_end',this.value)" <?php echo $canmanage?'':'disabled'; ?>></div>
<div class="info-item"><label>🏭 Settore</label><select class="<?php echo empty($student->sector)?'empty':''; ?>" onchange="saveCoachingData(<?php echo $student->id; ?>,'sector',this.value);updateAreaOptions(this,<?php echo $student->id; ?>)" <?php echo $canmanage?'':'disabled'; ?>><option value="">-- Seleziona --</option><option value="AUTOMOBILE" <?php echo $student->sector=='AUTOMOBILE'?'selected':''; ?>>AUTOMOBILE</option><option value="CHIMFARM" <?php echo $student->sector=='CHIMFARM'?'selected':''; ?>>CHIMFARM</option><option value="MECCANICA" <?php echo $student->sector=='MECCANICA'?'selected':''; ?>>MECCANICA</option></select></div>
<div class="info-item"><label>🎯 Area</label><select id="area_<?php echo $student->id; ?>" onchange="saveCoachingData(<?php echo $student->id; ?>,'area',this.value)" <?php echo $canmanage?'':'disabled'; ?>><option value="">-- Seleziona settore --</option><?php if($student->sector && isset($areas_by_sector[$student->sector])):foreach($areas_by_sector[$student->sector] as $code=>$name): ?><option value="<?php echo $code; ?>" <?php echo $student->area==$code?'selected':''; ?>><?php echo $name; ?></option><?php endforeach;endif; ?></select></div>
</div>
<div class="progress-row">
<div class="progress-item"><div class="label">Quiz Completati</div><div class="progress-bar-mini"><div class="progress-fill blue" style="width:<?php echo min(100,($student->quiz_count/5)*100); ?>%"></div></div><div class="value"><?php echo $student->quiz_count; ?>/5</div></div>
<div class="progress-item"><div class="label">Autovalutazioni</div><div class="progress-bar-mini"><div class="progress-fill green" style="width:<?php echo min(100,($student->selfassess_count/60)*100); ?>%"></div></div><div class="value"><?php echo $student->selfassess_count; ?>/60</div></div>
</div>
<div class="quick-actions">
<a href="student_selfassessments.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $student->id; ?>" class="btn-quick green">🎯 Autovalutazioni</a>
<a href="<?php echo $CFG->wwwroot; ?>/local/competencyreport/student.php?userid=<?php echo $student->id; ?>&courseid=<?php echo $courseid; ?>" class="btn-quick blue">📈 Report Quiz</a>
<a href="reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $student->id; ?>" class="btn-quick purple">📊 Report Colloquio</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<script>
const areasBySector=<?php echo json_encode($areas_by_sector); ?>;
function filterStudents(){const s=document.getElementById('searchStudent').value.toLowerCase(),st=document.getElementById('filterStatus').value,se=document.getElementById('filterSector').value;document.querySelectorAll('.student-card').forEach(c=>{let show=true;if(s&&!c.dataset.name.includes(s))show=false;if(st&&c.dataset.status!=st)show=false;if(se&&c.dataset.sector!=se)show=false;c.style.display=show?'block':'none'})}
function updateAreaOptions(sel,id){const sector=sel.value,area=document.getElementById('area_'+id);area.innerHTML='<option value="">-- Seleziona --</option>';if(sector&&areasBySector[sector])Object.entries(areasBySector[sector]).forEach(([c,n])=>{const o=document.createElement('option');o.value=c;o.textContent=n;area.appendChild(o)});sel.classList.toggle('empty',!sector)}
function saveCoachingData(studentId,field,value){fetch('ajax_save_coaching.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'studentid='+studentId+'&courseid=<?php echo $courseid; ?>&field='+field+'&value='+encodeURIComponent(value)+'&sesskey=<?php echo sesskey(); ?>'}).then(r=>r.json()).then(d=>{if(!d.success)alert('Errore: '+d.error)})}
</script>
<?php echo $OUTPUT->footer();
