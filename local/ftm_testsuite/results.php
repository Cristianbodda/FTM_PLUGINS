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

use local_ftm_testsuite\test_manager;

require_login();
require_capability('local/ftm_testsuite:viewresults', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/results.php'));
$PAGE->set_title('Risultati Test - FTM Test Suite');
$PAGE->set_heading('Risultati Test');
$PAGE->set_pagelayout('admin');

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

foreach ($run->results as $r) {
    if (isset($modules[$r->module])) {
        $modules[$r->module]['results'][] = $r;
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
?>

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

<div class="results-container">
    
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
    <?php foreach ($modules as $key => $mod): if (empty($mod['results'])) continue; ?>
    <div class="card">
        <div class="card-header">
            <h3><?php echo $mod['icon']; ?> Modulo: <?php echo $mod['name']; ?></h3>
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
