<?php
/**
 * AUDIT DOCENTE - Verifica Qualità Corso
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'dashboard', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/audit_competenze.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Audit Docente - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// FUNZIONI
function analyze_quiz_coherence($quizid) {
    global $DB;
    $analysis = ['total_questions' => 0, 'with_competency' => 0, 'without_competency' => 0, 'competencies' => [], 'levels' => [1=>0,2=>0,3=>0], 'duplicates' => [], 'issues' => []];
    
    $sql = "SELECT qs.slot, q.id as questionid, q.name, c.id as comp_id, c.idnumber as comp_code, c.shortname as comp_name, qbc.difficultylevel
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            LEFT JOIN {competency} c ON c.id = qbc.competencyid
            WHERE qs.quizid = ? ORDER BY qs.slot";
    
    $questions = $DB->get_records_sql($sql, [$quizid]);
    foreach ($questions as $q) {
        $analysis['total_questions']++;
        if ($q->comp_id) {
            $analysis['with_competency']++;
            $level = $q->difficultylevel ?: 1;
            $analysis['levels'][$level]++;
            if (!isset($analysis['competencies'][$q->comp_code])) {
                $analysis['competencies'][$q->comp_code] = ['code' => $q->comp_code, 'name' => $q->comp_name, 'count' => 0, 'questions' => []];
            }
            $analysis['competencies'][$q->comp_code]['count']++;
            $analysis['competencies'][$q->comp_code]['questions'][] = $q->slot;
        } else {
            $analysis['without_competency']++;
        }
    }
    foreach ($analysis['competencies'] as $code => $data) {
        if ($data['count'] > 1) $analysis['duplicates'][$code] = $data;
    }
    if ($analysis['without_competency'] > 0) {
        $analysis['issues'][] = ['type' => 'warning', 'message' => $analysis['without_competency'] . ' domande senza competenza'];
    }
    return $analysis;
}

function analyze_framework_coverage($courseid, $contextid, $frameworkid) {
    global $DB;
    $all_competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid]);
    $sql = "SELECT DISTINCT c.id, c.idnumber, c.shortname, COUNT(qbc.id) as usage_count
            FROM {competency} c
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
            LEFT JOIN {question} q ON q.id = qbc.questionid
            LEFT JOIN {question_versions} qv ON qv.questionid = q.id
            LEFT JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid AND qc.contextid = ?
            WHERE c.competencyframeworkid = ?
            GROUP BY c.id, c.idnumber, c.shortname ORDER BY c.idnumber";
    $used = $DB->get_records_sql($sql, [$contextid, $frameworkid]);
    
    $coverage = ['total_in_framework' => count($all_competencies), 'used_in_course' => 0, 'never_used' => [], 'low_usage' => [], 'by_area' => []];
    foreach ($used as $c) {
        if ($c->usage_count > 0) {
            $coverage['used_in_course']++;
            if ($c->usage_count < 3) $coverage['low_usage'][] = $c;
        } else {
            $coverage['never_used'][] = $c;
        }
        if (preg_match('/^([A-Z]+_[A-Z]+)_/', $c->idnumber, $m)) {
            $area = $m[1];
            if (!isset($coverage['by_area'][$area])) $coverage['by_area'][$area] = ['total' => 0, 'used' => 0];
            $coverage['by_area'][$area]['total']++;
            if ($c->usage_count > 0) $coverage['by_area'][$area]['used']++;
        }
    }
    return $coverage;
}

function get_publication_checklist($courseid, $contextid) {
    global $DB;
    $checks = [];
    
    $total = $DB->count_records_sql("SELECT COUNT(DISTINCT q.id) FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = ?", [$contextid]);
    $with_comp = $DB->count_records_sql("SELECT COUNT(DISTINCT q.id) FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id WHERE qc.contextid = ?", [$contextid]);
    $checks['all_questions_have_competency'] = ['label' => 'Tutte le domande hanno competenza', 'status' => ($total > 0 && $total == $with_comp), 'detail' => "$with_comp / $total"];
    
    $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
    $issues = [];
    foreach ($quizzes as $q) {
        $slots = $DB->count_records('quiz_slots', ['quizid' => $q->id]);
        if ($slots < 5) $issues[] = $q->name . ": $slots";
    }
    $checks['quizzes_min_questions'] = ['label' => 'Quiz con almeno 5 domande', 'status' => empty($issues), 'detail' => empty($issues) ? count($quizzes) . ' quiz OK' : implode(', ', $issues)];
    
    $levels = $DB->get_records_sql("SELECT difficultylevel, COUNT(*) as cnt FROM {qbank_competenciesbyquestion} qbc JOIN {question} q ON q.id = qbc.questionid JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = ? GROUP BY difficultylevel", [$contextid]);
    $checks['difficulty_mix'] = ['label' => 'Mix livelli difficoltà', 'status' => count($levels) >= 2, 'detail' => count($levels) . ' livelli'];
    
    $attempts = $DB->count_records_sql("SELECT COUNT(*) FROM {quiz_attempts} qa JOIN {quiz} q ON q.id = qa.quiz WHERE q.course = ? AND qa.state = 'finished'", [$courseid]);
    $checks['has_test_attempts'] = ['label' => 'Almeno un tentativo test', 'status' => $attempts > 0, 'detail' => $attempts . ' tentativi'];
    
    return $checks;
}

function get_competency_quiz_matrix($courseid) {
    global $DB;
    $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');
    $matrix = [];
    $all_comps = [];
    foreach ($quizzes as $quiz) {
        $sql = "SELECT c.idnumber as comp_code, c.shortname, COUNT(*) as cnt
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
                JOIN {competency} c ON c.id = qbc.competencyid
                WHERE qs.quizid = ? GROUP BY c.idnumber, c.shortname";
        $comps = $DB->get_records_sql($sql, [$quiz->id]);
        $matrix[$quiz->id] = ['quiz_name' => $quiz->name, 'competencies' => []];
        foreach ($comps as $c) {
            $matrix[$quiz->id]['competencies'][$c->comp_code] = $c->cnt;
            $all_comps[$c->comp_code] = $c->shortname;
        }
    }
    ksort($all_comps);
    return ['matrix' => $matrix, 'competencies' => $all_comps, 'quizzes' => $quizzes];
}

function get_recent_activity($courseid, $contextid) {
    global $DB;
    $activities = [];
    $quizzes = $DB->get_records_sql("SELECT id, name, timecreated, timemodified FROM {quiz} WHERE course = ? ORDER BY timemodified DESC LIMIT 20", [$courseid]);
    foreach ($quizzes as $q) {
        $activities[] = ['time' => $q->timemodified ?: $q->timecreated, 'type' => 'quiz', 'detail' => "Quiz: {$q->name}"];
    }
    $questions = $DB->get_records_sql("SELECT q.id, q.name, q.timecreated, q.timemodified FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = ? ORDER BY q.timemodified DESC LIMIT 20", [$contextid]);
    foreach ($questions as $q) {
        $activities[] = ['time' => $q->timemodified ?: $q->timecreated, 'type' => 'question', 'detail' => "Domanda: " . substr($q->name, 0, 40)];
    }
    usort($activities, function($a, $b) { return $b['time'] - $a['time']; });
    return array_slice($activities, 0, 30);
}

// CSS
$css = '<style>
.audit-page { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.audit-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 30px; border-radius: 16px; margin-bottom: 25px; }
.audit-header h2 { margin: 0 0 10px 0; }
.audit-header p { margin: 0; opacity: 0.9; }
.course-badge { background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px; display: inline-block; margin-top: 12px; }
.nav-tabs { display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
.nav-tab { padding: 12px 20px; border-radius: 8px; text-decoration: none; color: #555; font-weight: 600; background: #f5f5f5; transition: all 0.3s; }
.nav-tab:hover { background: #e8e8e8; }
.nav-tab.active { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; }
.panel { background: white; border-radius: 14px; padding: 25px; box-shadow: 0 3px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
.panel h3 { margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 2px solid #eee; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 12px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 11px; opacity: 0.9; margin-top: 5px; text-transform: uppercase; }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.stat-card.teal { background: linear-gradient(135deg, #1abc9c, #16a085); color: white; }
.stat-card.gray { background: #f8f9fa; color: #333; border: 1px solid #e0e0e0; }
.checklist-item { display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px; margin-bottom: 10px; }
.checklist-item.pass { border-left: 4px solid #27ae60; }
.checklist-item.fail { border-left: 4px solid #e74c3c; }
.checklist-icon { font-size: 24px; }
.checklist-content { flex: 1; }
.checklist-label { font-weight: 600; }
.checklist-detail { font-size: 12px; color: #666; }
.issue-item { padding: 12px 15px; border-radius: 8px; margin-bottom: 8px; }
.issue-item.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.issue-item.info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
.issue-item.success { background: #d4edda; border-left: 4px solid #28a745; }
.progress-bar { height: 24px; background: #e9ecef; border-radius: 12px; overflow: hidden; margin: 10px 0; }
.progress-fill { height: 100%; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 11px; }
.progress-fill.excellent { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.progress-fill.good { background: linear-gradient(90deg, #3498db, #2980b9); }
.progress-fill.warning { background: linear-gradient(90deg, #f39c12, #e67e22); }
.quiz-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; margin-bottom: 15px; }
.quiz-card h4 { margin: 0 0 15px 0; display: flex; justify-content: space-between; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.matrix-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.matrix-table th, .matrix-table td { padding: 8px; border: 1px solid #e0e0e0; text-align: center; }
.matrix-table th { background: #f8f9fa; }
.matrix-table th.rotate { writing-mode: vertical-rl; height: 100px; }
.matrix-table .has-q { background: #d4edda; color: #155724; font-weight: 600; }
.matrix-table .no-q { background: #f8f9fa; color: #ccc; }
.coverage-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
.coverage-code { font-family: monospace; background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 4px; font-size: 11px; min-width: 150px; }
.coverage-bar { flex: 1; }
.coverage-count { min-width: 80px; text-align: right; font-size: 12px; color: #666; }
.timeline-item { display: flex; gap: 15px; padding: 12px 0; border-bottom: 1px solid #eee; }
.timeline-date { min-width: 130px; font-size: 12px; color: #666; }
.timeline-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.timeline-icon.quiz { background: #e8f4fd; }
.timeline-icon.question { background: #e8f8f5; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 18px; color: #3498db; text-decoration: none; }
.scrollable { max-height: 400px; overflow-y: auto; }
.filter-row { display: flex; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px; }
.filter-group label { font-size: 11px; font-weight: 600; color: #555; display: block; margin-bottom: 5px; }
.filter-group select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; min-width: 200px; }
</style>';

echo $OUTPUT->header();
echo $css;

$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');
$quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');
?>

<div class="audit-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="audit-header">
        <h2>🔍 Audit Docente - Verifica Qualità Corso</h2>
        <p>Strumenti per verificare la coerenza e completezza del corso</p>
        <div class="course-badge">📚 <?php echo format_string($course->fullname); ?></div>
    </div>
    
    <div class="nav-tabs">
        <a href="?courseid=<?php echo $courseid; ?>&action=dashboard" class="nav-tab <?php echo $action == 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
        <a href="?courseid=<?php echo $courseid; ?>&action=checklist" class="nav-tab <?php echo $action == 'checklist' ? 'active' : ''; ?>">✅ Checklist</a>
        <a href="?courseid=<?php echo $courseid; ?>&action=coherence" class="nav-tab <?php echo $action == 'coherence' ? 'active' : ''; ?>">🎯 Coerenza Quiz</a>
        <a href="?courseid=<?php echo $courseid; ?>&action=coverage" class="nav-tab <?php echo $action == 'coverage' ? 'active' : ''; ?>">📈 Copertura</a>
        <a href="?courseid=<?php echo $courseid; ?>&action=matrix" class="nav-tab <?php echo $action == 'matrix' ? 'active' : ''; ?>">🗺️ Matrice</a>
        <a href="?courseid=<?php echo $courseid; ?>&action=timeline" class="nav-tab <?php echo $action == 'timeline' ? 'active' : ''; ?>">📅 Storico</a>
    </div>

<?php
// DASHBOARD
if ($action == 'dashboard'):
    $checklist = get_publication_checklist($courseid, $context->id);
    $passed = count(array_filter($checklist, function($c) { return $c['status']; }));
    $total_checks = count($checklist);
    $total_q = $DB->count_records_sql("SELECT COUNT(DISTINCT q.id) FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = ?", [$context->id]);
    $with_comp = $DB->count_records_sql("SELECT COUNT(DISTINCT qbc.questionid) FROM {qbank_competenciesbyquestion} qbc JOIN {question} q ON q.id = qbc.questionid JOIN {question_versions} qv ON qv.questionid = q.id JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid WHERE qc.contextid = ?", [$context->id]);
    $cov_pct = $total_q > 0 ? round(($with_comp / $total_q) * 100) : 0;
?>
    <div class="panel">
        <h3>📊 Riepilogo Qualità</h3>
        <div class="stats-grid">
            <div class="stat-card <?php echo $passed == $total_checks ? 'green' : 'orange'; ?>"><div class="number"><?php echo $passed; ?>/<?php echo $total_checks; ?></div><div class="label">Check OK</div></div>
            <div class="stat-card blue"><div class="number"><?php echo count($quizzes); ?></div><div class="label">Quiz</div></div>
            <div class="stat-card teal"><div class="number"><?php echo $total_q; ?></div><div class="label">Domande</div></div>
            <div class="stat-card <?php echo $cov_pct == 100 ? 'green' : 'orange'; ?>"><div class="number"><?php echo $cov_pct; ?>%</div><div class="label">Copertura</div></div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill <?php echo $passed == $total_checks ? 'excellent' : 'warning'; ?>" style="width: <?php echo ($passed/$total_checks)*100; ?>%;"><?php echo round(($passed/$total_checks)*100); ?>%</div>
        </div>
    </div>
    <div class="panel">
        <h3>✅ Checklist Rapida</h3>
        <?php foreach ($checklist as $check): ?>
        <div class="checklist-item <?php echo $check['status'] ? 'pass' : 'fail'; ?>">
            <div class="checklist-icon"><?php echo $check['status'] ? '✅' : '❌'; ?></div>
            <div class="checklist-content">
                <div class="checklist-label"><?php echo $check['label']; ?></div>
                <div class="checklist-detail"><?php echo $check['detail']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action == 'checklist'):
    $checklist = get_publication_checklist($courseid, $context->id);
?>
    <div class="panel">
        <h3>✅ Checklist Pre-Pubblicazione</h3>
        <?php foreach ($checklist as $check): ?>
        <div class="checklist-item <?php echo $check['status'] ? 'pass' : 'fail'; ?>">
            <div class="checklist-icon"><?php echo $check['status'] ? '✅' : '❌'; ?></div>
            <div class="checklist-content">
                <div class="checklist-label"><?php echo $check['label']; ?></div>
                <div class="checklist-detail"><?php echo $check['detail']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action == 'coherence'): ?>
    <div class="panel">
        <h3>🎯 Coerenza Quiz</h3>
        <?php foreach ($quizzes as $quiz): 
            $analysis = analyze_quiz_coherence($quiz->id);
        ?>
        <div class="quiz-card">
            <h4><?php echo format_string($quiz->name); ?>
                <?php if ($analysis['without_competency'] == 0): ?><span class="badge badge-success">✅ OK</span>
                <?php else: ?><span class="badge badge-warning">⚠️</span><?php endif; ?>
            </h4>
            <div class="stats-grid">
                <div class="stat-card gray"><div class="number"><?php echo $analysis['total_questions']; ?></div><div class="label">Domande</div></div>
                <div class="stat-card gray"><div class="number"><?php echo $analysis['with_competency']; ?></div><div class="label">Con Comp.</div></div>
                <div class="stat-card gray"><div class="number"><?php echo count($analysis['competencies']); ?></div><div class="label">Competenze</div></div>
                <div class="stat-card gray"><div class="number"><?php echo $analysis['levels'][1]; ?>/<?php echo $analysis['levels'][2]; ?>/<?php echo $analysis['levels'][3]; ?></div><div class="label">Livelli</div></div>
            </div>
            <?php foreach ($analysis['issues'] as $issue): ?>
            <div class="issue-item <?php echo $issue['type']; ?>"><?php echo $issue['message']; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action == 'coverage'): ?>
    <div class="panel">
        <h3>📈 Copertura Framework</h3>
        <div class="filter-row">
            <div class="filter-group">
                <label>Framework</label>
                <select onchange="if(this.value) window.location='?courseid=<?php echo $courseid; ?>&action=coverage&frameworkid='+this.value">
                    <option value="">-- Seleziona --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?>><?php echo $fw->shortname; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($frameworkid):
            $coverage = analyze_framework_coverage($courseid, $context->id, $frameworkid);
            $pct = $coverage['total_in_framework'] > 0 ? round(($coverage['used_in_course']/$coverage['total_in_framework'])*100) : 0;
        ?>
        <div class="stats-grid">
            <div class="stat-card blue"><div class="number"><?php echo $coverage['total_in_framework']; ?></div><div class="label">Totali</div></div>
            <div class="stat-card green"><div class="number"><?php echo $coverage['used_in_course']; ?></div><div class="label">Usate</div></div>
            <div class="stat-card orange"><div class="number"><?php echo count($coverage['never_used']); ?></div><div class="label">Mai usate</div></div>
            <div class="stat-card purple"><div class="number"><?php echo $pct; ?>%</div><div class="label">Copertura</div></div>
        </div>
        <?php if (!empty($coverage['by_area'])): ?>
        <h4>Per Area</h4>
        <div class="scrollable">
        <?php foreach ($coverage['by_area'] as $area => $data): 
            $apct = $data['total'] > 0 ? round(($data['used']/$data['total'])*100) : 0;
        ?>
        <div class="coverage-item">
            <div class="coverage-code"><?php echo $area; ?></div>
            <div class="coverage-bar"><div class="progress-bar" style="height:16px;margin:0;"><div class="progress-fill <?php echo $apct>=80?'excellent':($apct>=50?'good':'warning'); ?>" style="width:<?php echo max($apct,5); ?>%;"><?php echo $apct; ?>%</div></div></div>
            <div class="coverage-count"><?php echo $data['used']; ?>/<?php echo $data['total']; ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="issue-item info">Seleziona un framework.</div>
        <?php endif; ?>
    </div>

<?php elseif ($action == 'matrix'):
    $matrix_data = get_competency_quiz_matrix($courseid);
?>
    <div class="panel">
        <h3>🗺️ Matrice Competenze vs Quiz</h3>
        <?php if (empty($matrix_data['competencies'])): ?>
        <div class="issue-item warning">Nessuna competenza assegnata.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="matrix-table">
            <thead><tr><th>Quiz</th>
            <?php foreach ($matrix_data['competencies'] as $code => $name): ?>
            <th class="rotate" title="<?php echo $name; ?>"><?php echo $code; ?></th>
            <?php endforeach; ?>
            <th>Tot</th></tr></thead>
            <tbody>
            <?php foreach ($matrix_data['matrix'] as $qid => $data): $tot = array_sum($data['competencies']); ?>
            <tr><td style="text-align:left;"><?php echo $data['quiz_name']; ?></td>
            <?php foreach ($matrix_data['competencies'] as $code => $name): $c = $data['competencies'][$code] ?? 0; ?>
            <td class="<?php echo $c > 0 ? 'has-q' : 'no-q'; ?>"><?php echo $c > 0 ? $c : '-'; ?></td>
            <?php endforeach; ?>
            <td style="font-weight:600;background:#e8e8e8;"><?php echo $tot; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action == 'timeline'):
    $activities = get_recent_activity($courseid, $context->id);
?>
    <div class="panel">
        <h3>📅 Storico</h3>
        <div class="scrollable">
        <?php foreach ($activities as $act): ?>
        <div class="timeline-item">
            <div class="timeline-date"><?php echo date('d/m/Y H:i', $act['time']); ?></div>
            <div class="timeline-icon <?php echo $act['type']; ?>"><?php echo $act['type'] == 'quiz' ? '📝' : '❓'; ?></div>
            <div><?php echo $act['detail']; ?></div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
