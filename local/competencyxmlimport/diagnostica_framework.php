<?php
/**
 * DIAGNOSTICA FRAMEWORK UNIVERSALE
 * 
 * Analizza qualsiasi framework di competenze:
 * - Struttura gerarchica (settore → area → competenza)
 * - Copertura nel corso (quali aree hanno domande)
 * - Competenze orfane (senza domande)
 * - Statistiche dettagliate
 * 
 * @package    local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$action = optional_param('action', 'select', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/competencyxmlimport/diagnostica_framework.php', ['courseid' => $courseid]);
$PAGE->set_title('Diagnostica Framework');
$PAGE->set_heading('Diagnostica Framework - ' . $course->fullname);
$PAGE->set_pagelayout('incourse');

// CSS
$css = '
<style>
.diag-page { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

.diag-header { 
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
    color: white; 
    padding: 30px; 
    border-radius: 16px; 
    margin-bottom: 25px;
}
.diag-header h2 { margin: 0 0 10px 0; }
.diag-header p { margin: 0; opacity: 0.9; }

.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 3px 15px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 2px solid #eee; display: flex; align-items: center; gap: 10px; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-box { padding: 20px; border-radius: 12px; text-align: center; }
.stat-box .number { font-size: 36px; font-weight: 700; }
.stat-box .label { font-size: 12px; opacity: 0.9; margin-top: 5px; text-transform: uppercase; }
.stat-box.blue { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.stat-box.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-box.orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
.stat-box.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.stat-box.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.stat-box.teal { background: linear-gradient(135deg, #1abc9c, #16a085); color: white; }

.progress-bar { height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; margin: 15px 0; }
.progress-fill { height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; border-radius: 15px; }
.progress-fill.excellent { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.progress-fill.good { background: linear-gradient(90deg, #3498db, #2980b9); }
.progress-fill.warning { background: linear-gradient(90deg, #f39c12, #e67e22); }
.progress-fill.danger { background: linear-gradient(90deg, #e74c3c, #c0392b); }

.area-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 12px; }
.area-card.complete { border-left: 4px solid #27ae60; }
.area-card.partial { border-left: 4px solid #f39c12; }
.area-card.empty { border-left: 4px solid #e74c3c; }
.area-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.area-title { font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
.area-stats { display: flex; gap: 15px; font-size: 13px; color: #666; }
.area-progress { height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
.area-progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }

.comp-list { margin-top: 10px; padding: 10px; background: white; border-radius: 8px; max-height: 200px; overflow-y: auto; }
.comp-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
.comp-item:last-child { border-bottom: none; }
.comp-item.has-questions { background: #e8f5e9; }
.comp-item.no-questions { background: #fff3e0; }
.comp-code { font-family: monospace; color: #2e7d32; font-weight: 600; }
.comp-questions { font-weight: 600; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #cce5ff; color: #004085; }

.framework-select { padding: 12px 20px; border: 2px solid #3498db; border-radius: 10px; font-size: 15px; min-width: 300px; margin-right: 10px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s; }
.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-success { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }

.back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; color: #3498db; text-decoration: none; font-weight: 500; }

.legend { display: flex; gap: 20px; flex-wrap: wrap; padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px; }
.legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.legend-color { width: 20px; height: 20px; border-radius: 4px; }

.export-buttons { display: flex; gap: 10px; margin-top: 20px; }

@media print {
    .btn, .back-link, .export-buttons { display: none !important; }
    .panel { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
}
</style>';

echo $OUTPUT->header();
echo $css;

// Carica frameworks disponibili
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

?>
<div class="diag-page">
    
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="diag-header">
        <h2>🔬 Diagnostica Framework Competenze</h2>
        <p>Analizza la struttura e la copertura di qualsiasi framework nel corso</p>
    </div>

<?php if (!$frameworkid): ?>
    
    <div class="panel">
        <h3>📋 Seleziona Framework da Analizzare</h3>
        
        <form method="get" action="">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            
            <select name="frameworkid" class="framework-select" required>
                <option value="">-- Seleziona un Framework --</option>
                <?php foreach ($frameworks as $fw): 
                    $count = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
                ?>
                <option value="<?php echo $fw->id; ?>">
                    <?php echo format_string($fw->shortname); ?> (<?php echo $count; ?> competenze)
                </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">🔍 Analizza Framework</button>
        </form>
        
        <div style="margin-top: 30px;">
            <h4>Framework Disponibili:</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Nome</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ID Number</th>
                        <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Competenze</th>
                        <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Azione</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($frameworks as $fw): 
                    $count = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
                ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo format_string($fw->shortname); ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code><?php echo $fw->idnumber; ?></code></td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><strong><?php echo $count; ?></strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">
                            <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $fw->id; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Analizza</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: 
    // ANALISI FRAMEWORK
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid], '*', MUST_EXIST);
    
    // Tutte le competenze del framework
    $all_competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], 'idnumber ASC');
    
    // Competenze usate nel corso (con domande)
    $sql_used = "SELECT DISTINCT c.id, c.idnumber, c.shortname, COUNT(qbc.id) as num_domande
                 FROM {competency} c
                 JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
                 JOIN {question} q ON q.id = qbc.questionid
                 JOIN {question_versions} qv ON qv.questionid = q.id
                 JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE c.competencyframeworkid = ? AND qc.contextid = ?
                 GROUP BY c.id, c.idnumber, c.shortname";
    $used_competencies = $DB->get_records_sql($sql_used, [$frameworkid, $context->id]);
    $used_ids = array_keys($used_competencies);
    
    // Raggruppa per settore e area
    $structure = [];
    foreach ($all_competencies as $comp) {
        $parts = explode('_', $comp->idnumber);
        $sector = $parts[0] ?? 'ALTRO';
        $area = $parts[1] ?? 'GEN';
        
        if (!isset($structure[$sector])) {
            $structure[$sector] = [];
        }
        if (!isset($structure[$sector][$area])) {
            $structure[$sector][$area] = [
                'code' => $area,
                'competencies' => [],
                'total' => 0,
                'with_questions' => 0,
                'total_questions' => 0
            ];
        }
        
        $has_questions = in_array($comp->id, $used_ids);
        $num_questions = $has_questions ? ($used_competencies[$comp->id]->num_domande ?? 0) : 0;
        
        $structure[$sector][$area]['competencies'][] = [
            'id' => $comp->id,
            'idnumber' => $comp->idnumber,
            'shortname' => $comp->shortname,
            'has_questions' => $has_questions,
            'num_questions' => $num_questions
        ];
        $structure[$sector][$area]['total']++;
        if ($has_questions) {
            $structure[$sector][$area]['with_questions']++;
            $structure[$sector][$area]['total_questions'] += $num_questions;
        }
    }
    
    // Calcola statistiche globali
    $total_competencies = count($all_competencies);
    $total_with_questions = count($used_competencies);
    $total_questions = array_sum(array_column($used_competencies, 'num_domande'));
    $coverage_pct = $total_competencies > 0 ? round(($total_with_questions / $total_competencies) * 100, 1) : 0;
    
    $total_areas = 0;
    $areas_with_questions = 0;
    $areas_complete = 0;
    foreach ($structure as $sector => $areas) {
        foreach ($areas as $area) {
            $total_areas++;
            if ($area['with_questions'] > 0) $areas_with_questions++;
            if ($area['with_questions'] == $area['total']) $areas_complete++;
        }
    }
?>

    <div class="panel">
        <h3>📊 Framework: <?php echo format_string($framework->shortname); ?></h3>
        
        <div class="stats-grid">
            <div class="stat-box blue">
                <div class="number"><?php echo $total_competencies; ?></div>
                <div class="label">Competenze Totali</div>
            </div>
            <div class="stat-box green">
                <div class="number"><?php echo $total_with_questions; ?></div>
                <div class="label">Con Domande</div>
            </div>
            <div class="stat-box orange">
                <div class="number"><?php echo $total_competencies - $total_with_questions; ?></div>
                <div class="label">Senza Domande</div>
            </div>
            <div class="stat-box purple">
                <div class="number"><?php echo $total_questions; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-box teal">
                <div class="number"><?php echo $total_areas; ?></div>
                <div class="label">Aree</div>
            </div>
            <div class="stat-box <?php echo $coverage_pct >= 80 ? 'green' : ($coverage_pct >= 50 ? 'blue' : 'orange'); ?>">
                <div class="number"><?php echo $coverage_pct; ?>%</div>
                <div class="label">Copertura</div>
            </div>
        </div>
        
        <div class="progress-bar">
            <?php $class = $coverage_pct >= 80 ? 'excellent' : ($coverage_pct >= 50 ? 'good' : ($coverage_pct >= 20 ? 'warning' : 'danger')); ?>
            <div class="progress-fill <?php echo $class; ?>" style="width: <?php echo max($coverage_pct, 5); ?>%;">
                <?php echo $coverage_pct; ?>% competenze coperte da domande
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item"><div class="legend-color" style="background: #27ae60;"></div> Area completa (100%)</div>
            <div class="legend-item"><div class="legend-color" style="background: #f39c12;"></div> Area parziale</div>
            <div class="legend-item"><div class="legend-color" style="background: #e74c3c;"></div> Area vuota (0 domande)</div>
        </div>
    </div>
    
    <?php foreach ($structure as $sector => $areas): 
        ksort($areas);
    ?>
    <div class="panel">
        <h3>🏭 Settore: <?php echo $sector; ?> (<?php echo count($areas); ?> aree)</h3>
        
        <?php foreach ($areas as $areaCode => $area): 
            $pct = $area['total'] > 0 ? round(($area['with_questions'] / $area['total']) * 100) : 0;
            $status = $pct == 100 ? 'complete' : ($pct > 0 ? 'partial' : 'empty');
            $color = $pct == 100 ? '#27ae60' : ($pct > 0 ? '#f39c12' : '#e74c3c');
        ?>
        <div class="area-card <?php echo $status; ?>">
            <div class="area-header">
                <div class="area-title">
                    <span style="font-size: 20px;">📁</span>
                    <strong><?php echo $areaCode; ?></strong>
                    <?php if ($pct == 100): ?>
                        <span class="badge badge-success">✅ Completa</span>
                    <?php elseif ($pct > 0): ?>
                        <span class="badge badge-warning">⚠️ Parziale</span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ Vuota</span>
                    <?php endif; ?>
                </div>
                <div class="area-stats">
                    <span>📋 <?php echo $area['with_questions']; ?>/<?php echo $area['total']; ?> competenze</span>
                    <span>❓ <?php echo $area['total_questions']; ?> domande</span>
                    <span style="font-weight: 600; color: <?php echo $color; ?>;"><?php echo $pct; ?>%</span>
                </div>
            </div>
            
            <div class="area-progress">
                <div class="area-progress-fill" style="width: <?php echo max($pct, 2); ?>%; background: <?php echo $color; ?>;"></div>
            </div>
            
            <details>
                <summary style="cursor: pointer; font-size: 13px; color: #666; margin-top: 10px;">
                    👁️ Mostra <?php echo $area['total']; ?> competenze
                </summary>
                <div class="comp-list">
                    <?php foreach ($area['competencies'] as $comp): ?>
                    <div class="comp-item <?php echo $comp['has_questions'] ? 'has-questions' : 'no-questions'; ?>">
                        <div>
                            <span class="comp-code"><?php echo $comp['idnumber']; ?></span>
                            <span style="margin-left: 10px; color: #555;"><?php echo $comp['shortname']; ?></span>
                        </div>
                        <div class="comp-questions">
                            <?php if ($comp['has_questions']): ?>
                                <span style="color: #27ae60;">✅ <?php echo $comp['num_questions']; ?> domande</span>
                            <?php else: ?>
                                <span style="color: #e74c3c;">❌ 0 domande</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Competenze senza domande -->
    <?php 
    $orphan_comps = array_filter($all_competencies, function($c) use ($used_ids) {
        return !in_array($c->id, $used_ids);
    });
    if (!empty($orphan_comps)):
    ?>
    <div class="panel">
        <h3>⚠️ Competenze Senza Domande (<?php echo count($orphan_comps); ?>)</h3>
        <p>Queste competenze del framework non hanno domande associate nel corso:</p>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f5f5f5; position: sticky; top: 0;">
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Codice</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Nome</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Area</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orphan_comps as $comp): 
                    $parts = explode('_', $comp->idnumber);
                    $area = $parts[1] ?? '-';
                ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><code><?php echo $comp->idnumber; ?></code></td>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo $comp->shortname; ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><span class="badge badge-info"><?php echo $area; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="export-buttons">
        <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">← Analizza altro Framework</a>
        <button onclick="window.print();" class="btn btn-success">🖨️ Stampa Report</button>
    </div>

<?php endif; ?>

</div>

<?php
echo $OUTPUT->footer();
