<?php
/**
 * FTM Test Suite - Risultati Dettagliati
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');
require_once($CFG->dirroot . '/local/ftm_common/classes/design_helper.php');

use local_ftm_testsuite\test_manager;
use local_ftm_common\design_helper;

require_login();
require_capability('local/ftm_testsuite:viewresults', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/results.php'));
$PAGE->set_title('Risultati Test - FTM Test Suite');
$PAGE->set_heading('Risultati Test');
$PAGE->set_pagelayout('admin');

// Carica FTM Design System
$is_new_design = design_helper::load_design($PAGE);

$runid = optional_param('fts_runid', 0, PARAM_INT);

// Carica run specifico o l'ultimo
if ($runid > 0) {
    $run = test_manager::get_run($runid);
} else {
    $history = test_manager::get_history(30);
    $run = !empty($history) ? test_manager::get_run(reset($history)->id) : null;
}

if (!$run) {
    redirect(new moodle_url('/local/ftm_testsuite/index.php'), 
        'Nessun risultato test trovato. Esegui prima un test.', null, \core\output\notification::NOTIFY_WARNING);
}

// Raggruppa risultati per modulo
$modules = [
    'quiz' => ['name' => 'Quiz e Competenze', 'icon' => '📝', 'results' => []],
    'selfassessment' => ['name' => 'Autovalutazioni', 'icon' => '📋', 'results' => []],
    'labeval' => ['name' => 'LabEval', 'icon' => '🔬', 'results' => []],
    'radar' => ['name' => 'Radar e Aggregazione', 'icon' => '📊', 'results' => []],
    'report' => ['name' => 'Report', 'icon' => '📄', 'results' => []],
    'coverage' => ['name' => 'Copertura Competenze', 'icon' => '📊', 'results' => []],
    'integrity' => ['name' => 'Integrità Dati', 'icon' => '🔒', 'results' => []],
    'assignments' => ['name' => 'Assegnazioni', 'icon' => '📋', 'results' => []]
];

// DEBUG: Mostra moduli unici nel database per questo run
$debug_modules = [];
foreach ($run->results as $r) {
    $debug_modules[$r->module] = ($debug_modules[$r->module] ?? 0) + 1;
}

// Evita duplicati (stesso modulo + testcode)
$seen_tests = [];
foreach ($run->results as $r) {
    $key = $r->module . '_' . $r->testcode;
    if (isset($modules[$r->module]) && !isset($seen_tests[$key])) {
        $modules[$r->module]['results'][] = $r;
        $seen_tests[$key] = true;
    }
}

// Calcola statistiche per modulo
foreach ($modules as $key => &$mod) {
    $mod['passed'] = count(array_filter($mod['results'], fn($r) => $r->status === 'passed'));
    $mod['failed'] = count(array_filter($mod['results'], fn($r) => $r->status === 'failed'));
    $mod['warning'] = count(array_filter($mod['results'], fn($r) => $r->status === 'warning'));
    $mod['skipped'] = count(array_filter($mod['results'], fn($r) => $r->status === 'skipped'));
    $mod['total'] = count($mod['results']);
}
unset($mod); // IMPORTANTE: Interrompe il riferimento per evitare bug nel foreach successivo

// Carica utente che ha eseguito
global $DB;
$executor = $DB->get_record('user', ['id' => $run->executedby]);

// Definizione test risolvibili
$fixable_tests = [
    '1.1' => 'fix_orphan_questions.php',
    '1.4' => 'fix_orphan_questions.php',
    '2.4' => 'fix_selfassessment.php',
    '3.6' => 'fix_labeval.php'
];

echo $OUTPUT->header();

// Toggle button per design
echo design_helper::render_toggle_button($PAGE->url);
?>

<?php if ($is_new_design): ?>
<style>
/* FTM Design System per Results */
.results-container { max-width: 1400px; margin: 0 auto; }
.results-header {
    background: linear-gradient(135deg, <?php echo $run->status === 'completed' ? '#28a745, #20c997' : '#dc3545, #fd7e14'; ?>);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(<?php echo $run->status === 'completed' ? '40, 167, 69' : '220, 53, 69'; ?>, 0.3);
}
.results-header h1 { margin: 0 0 8px 0; font-size: 28px; font-weight: 700; }
.results-header .meta { opacity: 0.95; font-size: 14px; }
.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}
.summary-card {
    background: white;
    border-radius: 16px;
    padding: 24px 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 4px solid #F5A623;
    transition: transform 0.2s;
}
.summary-card:hover { transform: translateY(-2px); }
.summary-card .number { font-size: 40px; font-weight: 700; color: #1A5A5A; line-height: 1; }
.summary-card .label { color: #64748B; font-size: 13px; margin-top: 8px; font-weight: 500; }
.summary-card.passed { border-left-color: #28a745; }
.summary-card.passed .number { color: #28a745; }
.summary-card.failed { border-left-color: #dc3545; }
.summary-card.failed .number { color: #dc3545; }
.summary-card.warning { border-left-color: #EAB308; }
.summary-card.warning .number { color: #EAB308; }
.progress-bar-large {
    height: 45px;
    background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
    border-radius: 25px;
    overflow: hidden;
    margin-bottom: 25px;
    box-shadow: inset 0 2px 8px rgba(0,0,0,0.1);
}
.progress-fill-large {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}
.card-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #1A5A5A; }
.card-header .stats { display: flex; gap: 15px; font-size: 14px; }
.card-body { padding: 0; }
.results-table { width: 100%; border-collapse: collapse; }
.results-table th, .results-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #E2E8F0;
}
.results-table th { background: #f8fafc; font-weight: 600; font-size: 13px; color: #1A5A5A; }
.results-table tr:hover { background: #f8fafc; }
.status-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
}
.status-passed { background: #d4edda; color: #28a745; }
.status-failed { background: #f8d7da; color: #dc3545; }
.status-warning { background: #fff3cd; color: #856404; }
.status-skipped { background: #e9ecef; color: #6c757d; }
.trace-btn {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}
.trace-btn:hover { background: #e2e8f0; border-color: #cbd5e1; }
.trace-panel {
    display: none;
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 20px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    border-top: 3px solid #F5A623;
}
.trace-panel.show { display: block; }
.sql-panel {
    display: none;
    background: #1A5A5A;
    color: #e8f4f8;
    padding: 20px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 11px;
    white-space: pre-wrap;
    word-break: break-all;
    border-top: 3px solid #F5A623;
}
.sql-panel.show { display: block; }
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-primary { background: #F5A623; color: white; }
.btn-primary:hover { background: #e09000; color: white; box-shadow: 0 4px 12px rgba(245, 166, 35, 0.4); }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #64748B; color: white; }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.integrity-box {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid #28a745;
}
.integrity-box .hash {
    font-family: 'Consolas', 'Monaco', monospace;
    background: #1A5A5A;
    color: #e8f4f8;
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 12px;
    word-break: break-all;
}
.module-stats { display: flex; gap: 10px; }
.module-stat {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
}
.module-stat.passed { background: #d4edda; color: #28a745; }
.module-stat.failed { background: #f8d7da; color: #dc3545; }
.module-stat.warning { background: #fff3cd; color: #856404; }
/* Debug box migliorato per nuovo design */
.debug-box-ftm {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 18px 20px;
    margin-bottom: 20px;
    border-radius: 12px;
    border-left: 4px solid #1A5A5A;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 13px;
}
.debug-box-ftm .title {
    font-weight: 700;
    color: #1A5A5A;
    margin-bottom: 12px;
    font-size: 14px;
}
</style>
<?php else: ?>
<style>
.results-container { max-width: 1400px; margin: 0 auto; }
.results-header {
    background: linear-gradient(135deg, <?php echo $run->status === 'completed' ? '#28a745, #20c997' : '#dc3545, #fd7e14'; ?>);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.results-header h1 { margin: 0 0 5px 0; }
.results-header .meta { opacity: 0.9; font-size: 14px; }
.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}
.summary-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.summary-card .number { font-size: 36px; font-weight: 700; }
.summary-card .label { color: #666; font-size: 13px; }
.summary-card.passed .number { color: #28a745; }
.summary-card.failed .number { color: #dc3545; }
.summary-card.warning .number { color: #ffc107; }
.progress-bar-large {
    height: 40px;
    background: #e9ecef;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 25px;
    box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
}
.progress-fill-large {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 18px;
}
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 { margin: 0; font-size: 18px; }
.card-header .stats {
    display: flex;
    gap: 15px;
    font-size: 14px;
}
.card-body { padding: 0; }
.results-table { width: 100%; border-collapse: collapse; }
.results-table th, .results-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.results-table th { background: #f8f9fa; font-weight: 600; font-size: 13px; }
.results-table tr:hover { background: #f8f9fa; }
.status-icon {
    display: inline-block;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    text-align: center;
    line-height: 24px;
    font-size: 12px;
}
.status-passed { background: #d4edda; color: #28a745; }
.status-failed { background: #f8d7da; color: #dc3545; }
.status-warning { background: #fff3cd; color: #856404; }
.status-skipped { background: #e9ecef; color: #6c757d; }
.trace-btn {
    background: #e9ecef;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
.trace-btn:hover { background: #dee2e6; }
.trace-panel {
    display: none;
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    font-family: monospace;
    font-size: 12px;
    border-top: 2px solid #333;
}
.trace-panel.show { display: block; }
.trace-step {
    padding: 8px 0;
    border-bottom: 1px solid #333;
}
.trace-step:last-child { border-bottom: none; }
.trace-step .step-num {
    display: inline-block;
    width: 25px;
    height: 25px;
    background: #4ec9b0;
    color: #1e1e1e;
    border-radius: 50%;
    text-align: center;
    line-height: 25px;
    margin-right: 10px;
    font-weight: bold;
}
.trace-step .formula { color: #dcdcaa; }
.trace-step .result { color: #4ec9b0; font-weight: bold; }
.sql-panel {
    display: none;
    background: #2d2d2d;
    color: #9cdcfe;
    padding: 15px;
    font-family: monospace;
    font-size: 11px;
    white-space: pre-wrap;
    word-break: break-all;
    border-top: 2px solid #444;
}
.sql-panel.show { display: block; }
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.integrity-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.integrity-box .hash {
    font-family: monospace;
    background: #e9ecef;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    word-break: break-all;
}
.module-stats {
    display: flex;
    gap: 10px;
}
.module-stat {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.module-stat.passed { background: #d4edda; color: #28a745; }
.module-stat.failed { background: #f8d7da; color: #dc3545; }
.module-stat.warning { background: #fff3cd; color: #856404; }
</style>
<?php endif; ?>

<div class="<?php echo $is_new_design ? 'ftm-page-bg' : ''; ?>" style="<?php echo $is_new_design ? 'padding: 20px;' : ''; ?>">
<div class="results-container">

    <!-- DEBUG: Moduli nel database -->
    <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe4e4 100%); padding: 15px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid #dc3545; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; color: #495057;">
        <div style="font-weight: 700; color: #dc3545; margin-bottom: 10px; font-size: 14px;">🔍 DEBUG - Dati Database</div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 10px 5px 0; color: #6c757d; width: 140px;">Moduli nel DB:</td>
                <td style="padding: 5px 0;"><code style="background: #f8f9fa; padding: 3px 8px; border-radius: 4px; color: #212529;"><?php echo json_encode($debug_modules, JSON_PRETTY_PRINT); ?></code></td>
            </tr>
            <tr>
                <td style="padding: 5px 10px 5px 0; color: #6c757d;">Moduli mappati:</td>
                <td style="padding: 5px 0;"><code style="background: #f8f9fa; padding: 3px 8px; border-radius: 4px; color: #212529;"><?php
                    $mapped = [];
                    foreach ($modules as $k => $m) {
                        $mapped[$k] = count($m['results']);
                    }
                    echo json_encode($mapped);
                ?></code></td>
            </tr>
            <tr>
                <td style="padding: 5px 10px 5px 0; color: #6c757d;">Totale risultati:</td>
                <td style="padding: 5px 0;"><strong style="color: #212529; font-size: 15px;"><?php echo count($run->results); ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- Header -->
    <div class="results-header">
        <h1><?php echo $run->status === 'completed' ? '✅' : '❌'; ?> <?php echo $run->name; ?></h1>
        <div class="meta">
            📅 <?php echo userdate($run->timecreated, '%d/%m/%Y %H:%M:%S'); ?> |
            👤 Eseguito da: <?php echo fullname($executor); ?> |
            ⏱️ Durata: <?php echo $run->timecompleted ? round(($run->timecompleted - $run->timecreated) / 60, 1) . ' min' : 'N/A'; ?>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress-bar-large">
        <div class="progress-fill-large" style="width: <?php echo $run->success_rate; ?>%;">
            <?php echo $run->success_rate; ?>% Test Passati
        </div>
    </div>
    
    <!-- Summary -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="number"><?php echo $run->total_tests; ?></div>
            <div class="label">📊 Test Totali</div>
        </div>
        <div class="summary-card passed">
            <div class="number"><?php echo $run->passed_tests; ?></div>
            <div class="label">✅ Passati</div>
        </div>
        <div class="summary-card failed">
            <div class="number"><?php echo $run->failed_tests; ?></div>
            <div class="label">❌ Falliti</div>
        </div>
        <div class="summary-card warning">
            <div class="number"><?php echo $run->warning_tests; ?></div>
            <div class="label">⚠️ Warning</div>
        </div>
        <div class="summary-card">
            <div class="number"><?php echo $run->total_tests - $run->passed_tests - $run->failed_tests - $run->warning_tests; ?></div>
            <div class="label">⏭️ Saltati</div>
        </div>
    </div>
    
    <!-- Risultati per Modulo -->
    <!-- DEBUG: Ordine rendering moduli -->
    <div style="background: linear-gradient(135deg, #f0fff4 0%, #e4ffe4 100%); padding: 15px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid #28a745; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; color: #495057;">
        <div style="font-weight: 700; color: #28a745; margin-bottom: 10px; font-size: 14px;">📋 DEBUG - Ordine Rendering Moduli</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
        <?php
        foreach ($modules as $k => $m) {
            if (!empty($m['results'])) {
                $count = count($m['results']);
                echo "<div style='background: #f8f9fa; padding: 8px 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;'>";
                echo "<span style='color: #212529;'><strong style='color: #0066cc;'>[{$k}]</strong> {$m['name']} {$m['icon']}</span>";
                echo "<span style='background: #28a745; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;'>{$count}</span>";
                echo "</div>";
            }
        }
        ?>
        </div>
    </div>
    <?php
    $rendered_modules = [];
    foreach ($modules as $key => $mod):
        if (empty($mod['results'])) continue;
        if (isset($rendered_modules[$key])) continue;
        $rendered_modules[$key] = true;
    ?>
    <div class="card" id="module-<?php echo $key; ?>">
        <div class="card-header">
            <h3><?php echo $mod['icon']; ?> Modulo: <?php echo $mod['name']; ?> <small style="color:#999;font-size:11px;">[<?php echo $key; ?>]</small></h3>
            <div class="module-stats">
                <?php if ($mod['passed'] > 0): ?>
                <span class="module-stat passed">✅ <?php echo $mod['passed']; ?></span>
                <?php endif; ?>
                <?php if ($mod['failed'] > 0): ?>
                <span class="module-stat failed">❌ <?php echo $mod['failed']; ?></span>
                <?php endif; ?>
                <?php if ($mod['warning'] > 0): ?>
                <span class="module-stat warning">⚠️ <?php echo $mod['warning']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Stato</th>
                        <th style="width: 60px;">Codice</th>
                        <th>Test</th>
                        <th style="width: 120px;">Atteso</th>
                        <th style="width: 120px;">Ottenuto</th>
                        <th style="width: 100px;">Tempo</th>
                        <th style="width: 140px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mod['results'] as $r): ?>
                    <tr>
                        <td>
                            <span class="status-icon status-<?php echo $r->status; ?>">
                                <?php echo $r->status === 'passed' ? '✓' : ($r->status === 'failed' ? '✗' : ($r->status === 'warning' ? '!' : '–')); ?>
                            </span>
                        </td>
                        <td><strong><?php echo $r->testcode; ?></strong></td>
                        <td>
                            <strong><?php echo $r->testname; ?></strong>
                            <?php if ($r->details): ?>
                            <br><small style="color: #666;"><?php echo $r->details; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($r->expected_value); ?></code></td>
                        <td><code><?php echo htmlspecialchars($r->actual_value); ?></code></td>
                        <td><?php echo round($r->execution_time * 1000, 1); ?> ms</td>
                        <td>
                            <?php if ($r->trace_data): ?>
                            <button class="trace-btn" onclick="toggleTrace('trace-<?php echo $r->id; ?>')">
                                🔍 Trace
                            </button>
                            <?php endif; ?>
                            <?php if ($r->sql_query): ?>
                            <button class="trace-btn" onclick="toggleTrace('sql-<?php echo $r->id; ?>')">
                                💾 SQL
                            </button>
                            <?php endif; ?>
                            <?php if (($r->status === 'failed' || $r->status === 'warning') && isset($fixable_tests[$r->testcode])): ?>
                            <a href="<?php echo $fixable_tests[$r->testcode]; ?>?fts_runid=<?php echo $run->id; ?>" 
                               class="trace-btn" style="background: #dc3545; color: white; text-decoration: none;">
                                🔧 Risolvi
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($r->trace_data): 
                        $trace = json_decode($r->trace_data, true);
                    ?>
                    <tr>
                        <td colspan="7" style="padding: 0;">
                            <div id="trace-<?php echo $r->id; ?>" class="trace-panel">
                                <strong style="color: #4ec9b0;">📊 TRACE CALCOLO: <?php echo $r->testname; ?></strong>
                                <div style="margin-top: 10px;">
                                    <?php foreach ($trace as $step): ?>
                                    <div class="trace-step">
                                        <span class="step-num"><?php echo $step['step']; ?></span>
                                        <span><?php echo $step['desc']; ?></span>
                                        <?php if (isset($step['formula'])): ?>
                                        <br><span style="margin-left: 35px;" class="formula">Formula: <?php echo $step['formula']; ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($step['result'])): ?>
                                        <br><span style="margin-left: 35px;">→ Risultato: <span class="result"><?php echo $step['result']; ?></span></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($r->sql_query): ?>
                    <tr>
                        <td colspan="7" style="padding: 0;">
                            <div id="sql-<?php echo $r->id; ?>" class="sql-panel"><?php echo htmlspecialchars($r->sql_query); ?></div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Hash Integrità -->
    <?php if ($run->hash_integrity): ?>
    <div class="card">
        <div class="card-header">
            <h3>🔐 Certificato di Integrità</h3>
        </div>
        <div class="card-body" style="padding: 20px;">
            <div class="integrity-box">
                <div>
                    <strong>✅ Hash SHA-256:</strong>
                    <div class="hash"><?php echo $run->hash_integrity; ?></div>
                </div>
            </div>
            <p style="margin: 15px 0 0; color: #666; font-size: 13px;">
                Questo hash certifica che i risultati del test non sono stati modificati dopo l'esecuzione.
                Generato il <?php echo userdate($run->timecompleted, '%d/%m/%Y alle %H:%M:%S'); ?>.
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Azioni -->
    <div style="text-align: center; margin-top: 30px;">
        <?php if ($run->failed_tests > 0 || $run->warning_tests > 0): ?>
        <a href="fix.php?fts_runid=<?php echo $run->id; ?>" class="btn btn-danger" style="background: #fd7e14;">
            🔧 Centro Risoluzione Problemi
        </a>
        <?php endif; ?>
        <a href="report_pdf.php?fts_runid=<?php echo $run->id; ?>" class="btn btn-success">
            📄 Genera PDF Certificazione
        </a>
        <a href="run.php" class="btn btn-primary" style="margin-left: 10px;">
            ▶️ Esegui Nuovo Test
        </a>
        <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">
            ← Dashboard
        </a>
    </div>
    
</div>
</div>

<script>
function toggleTrace(id) {
    var panel = document.getElementById(id);
    if (panel.classList.contains('show')) {
        panel.classList.remove('show');
    } else {
        // Chiudi tutti gli altri
        document.querySelectorAll('.trace-panel, .sql-panel').forEach(function(p) {
            p.classList.remove('show');
        });
        panel.classList.add('show');
    }
}
</script>

<?php
echo $OUTPUT->footer();
