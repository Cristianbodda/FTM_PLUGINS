<?php
/**
 * ANALISI COMPETENZE MANCANTI
 * 
 * Mostra in dettaglio quali competenze non hanno domande associate
 * per permettere di creare le domande mancanti
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$frameworkid = optional_param('frameworkid', 9, PARAM_INT);
$sector = optional_param('sector', 'CHIMFARM', PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/competenze_mancanti.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Competenze Mancanti - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Prendi tutte le competenze del framework per il settore
$sql = "SELECT c.id, c.idnumber, c.shortname, c.description
        FROM {competency} c
        WHERE c.competencyframeworkid = ?
        AND c.idnumber LIKE ?
        ORDER BY c.idnumber";

$all_competencies = $DB->get_records_sql($sql, [$frameworkid, $sector . '_%']);

// Prendi le competenze usate nelle domande del corso
$sql_used = "SELECT DISTINCT c.idnumber
             FROM {competency} c
             JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
             JOIN {question} q ON q.id = qbc.questionid
             JOIN {question_versions} qv ON qv.questionid = q.id
             JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE qc.contextid = ?
             AND c.competencyframeworkid = ?
             AND c.idnumber LIKE ?";

$used_records = $DB->get_records_sql($sql_used, [$context->id, $frameworkid, $sector . '_%']);
$used = array_keys($used_records);

// Trova mancanti e raggruppa per area
$missing = [];
$covered = [];
$by_area = [];

foreach ($all_competencies as $c) {
    // Estrai area (es. CHIMFARM_1C)
    if (preg_match('/^([A-Z]+_[A-Z0-9]+)_/', $c->idnumber, $m)) {
        $area = $m[1];
    } else {
        $area = 'ALTRO';
    }
    
    if (!isset($by_area[$area])) {
        $by_area[$area] = ['total' => 0, 'covered' => 0, 'missing' => []];
    }
    
    $by_area[$area]['total']++;
    
    if (in_array($c->idnumber, $used)) {
        $by_area[$area]['covered']++;
        $covered[] = $c;
    } else {
        $by_area[$area]['missing'][] = $c;
        $missing[] = $c;
    }
}

ksort($by_area);

// CSS
$css = '<style>
.missing-page { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.missing-header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.missing-header h2 { margin: 0 0 10px 0; }
.panel { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #eee; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 10px; text-align: center; }
.stat-card .number { font-size: 36px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; text-transform: uppercase; }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.area-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
.area-card.complete { border-left: 4px solid #27ae60; }
.area-card.incomplete { border-left: 4px solid #e74c3c; }
.area-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.area-name { font-weight: 600; font-size: 16px; }
.area-stats { display: flex; gap: 15px; align-items: center; }
.area-pct { font-weight: 700; font-size: 18px; }
.area-pct.complete { color: #27ae60; }
.area-pct.incomplete { color: #e74c3c; }
.area-count { color: #666; font-size: 13px; }
.missing-list { margin-top: 10px; }
.missing-item { display: flex; gap: 15px; padding: 10px; background: #fff; border: 1px solid #ffcdd2; border-radius: 6px; margin-bottom: 8px; align-items: flex-start; }
.missing-code { font-family: monospace; background: #ffebee; color: #c62828; padding: 4px 10px; border-radius: 4px; font-size: 12px; white-space: nowrap; }
.missing-name { flex: 1; font-size: 13px; color: #333; }
.missing-desc { font-size: 11px; color: #666; margin-top: 3px; }
.back-link { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; }
.progress-bar { height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden; flex: 1; }
.progress-fill { height: 100%; border-radius: 5px; }
.progress-fill.complete { background: #27ae60; }
.progress-fill.incomplete { background: #e74c3c; }
.action-box { background: #e3f2fd; border: 2px solid #2196f3; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.action-box h4 { margin: 0 0 10px 0; color: #1565c0; }
.action-box p { margin: 0; color: #333; }
.btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 10px; margin-top: 10px; }
.btn-primary { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.export-box { background: #f5f5f5; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-top: 15px; }
.export-box pre { background: #fff; padding: 10px; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 200px; }
</style>';

echo $OUTPUT->header();
echo $css;

$total_comp = count($all_competencies);
$total_covered = count($covered);
$total_missing = count($missing);
$pct = $total_comp > 0 ? round(($total_covered / $total_comp) * 100) : 0;

?>
<div class="missing-page">
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=coverage&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>" class="back-link">← Torna alla Copertura</a>
    
    <div class="missing-header">
        <h2>🎯 Competenze Mancanti per <?php echo $sector; ?></h2>
        <p>Dettaglio delle competenze senza domande associate - per raggiungere il 100%</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo $total_comp; ?></div>
            <div class="label">Totali <?php echo $sector; ?></div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $total_covered; ?></div>
            <div class="label">Coperte</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $total_missing; ?></div>
            <div class="label">Mancanti</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo $pct; ?>%</div>
            <div class="label">Copertura</div>
        </div>
    </div>

    <?php if ($total_missing > 0): ?>
    <div class="action-box">
        <h4>📋 Per raggiungere il 100% devi creare domande per <?php echo $total_missing; ?> competenze</h4>
        <p>Le competenze mancanti sono elencate sotto, raggruppate per area. Per ogni competenza devi creare almeno 1 domanda con il codice corrispondente nel nome.</p>
        <a href="<?php echo $CFG->wwwroot; ?>/question/bank/importquestions/import.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">📥 Importa Domande XML</a>
        <a href="<?php echo $CFG->wwwroot; ?>/question/bank/editquestion/question.php?courseid=<?php echo $courseid; ?>&qtype=multichoice" class="btn btn-secondary">➕ Crea Domanda Manuale</a>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h3>📊 Dettaglio per Area</h3>
        
        <?php foreach ($by_area as $area => $data): 
            $area_pct = $data['total'] > 0 ? round(($data['covered'] / $data['total']) * 100) : 0;
            $is_complete = empty($data['missing']);
        ?>
        <div class="area-card <?php echo $is_complete ? 'complete' : 'incomplete'; ?>">
            <div class="area-header">
                <span class="area-name"><?php echo $area; ?></span>
                <div class="area-stats">
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $is_complete ? 'complete' : 'incomplete'; ?>" 
                             style="width: <?php echo max($area_pct, 5); ?>%;"></div>
                    </div>
                    <span class="area-pct <?php echo $is_complete ? 'complete' : 'incomplete'; ?>"><?php echo $area_pct; ?>%</span>
                    <span class="area-count"><?php echo $data['covered']; ?>/<?php echo $data['total']; ?></span>
                </div>
            </div>
            
            <?php if (!empty($data['missing'])): ?>
            <div class="missing-list">
                <strong style="color:#c62828; font-size:12px;">❌ Competenze mancanti:</strong>
                <?php foreach ($data['missing'] as $m): ?>
                <div class="missing-item">
                    <span class="missing-code"><?php echo $m->idnumber; ?></span>
                    <div class="missing-name">
                        <?php echo htmlspecialchars($m->shortname); ?>
                        <?php if (!empty($m->description)): ?>
                        <div class="missing-desc"><?php echo strip_tags(substr($m->description, 0, 150)); ?>...</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="color:#27ae60; font-size:13px;">✅ Tutte le competenze coperte!</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_missing > 0): ?>
    <div class="panel">
        <h3>📝 Lista Competenze Mancanti (copia per riferimento)</h3>
        <div class="export-box">
            <p style="margin-bottom:10px; font-size:12px; color:#666;">Usa questi codici per nominare le nuove domande (es. <code>CHIM_NEW_Q01 - CHIMFARM_1C_10</code>)</p>
            <pre><?php 
foreach ($missing as $m) {
    echo $m->idnumber . " - " . $m->shortname . "\n";
}
            ?></pre>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
