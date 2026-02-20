<?php
/**
 * FTM Test Suite - Centro Risoluzione Problemi
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');

use local_ftm_testsuite\test_manager;

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/fix.php'));
$PAGE->set_title('Risoluzione Problemi - FTM Test Suite');
$PAGE->set_heading('Centro Risoluzione Problemi');
$PAGE->set_pagelayout('admin');

// Parametri
$runid = optional_param('fts_runid', 0, PARAM_INT);
$testcode = optional_param('fts_testcode', '', PARAM_ALPHANUMEXT);

// Carica run se specificato
$run = null;
$failed_tests = [];

if ($runid > 0) {
    $run = test_manager::get_run($runid);
    if ($run) {
        foreach ($run->results as $r) {
            if ($r->status === 'failed' || $r->status === 'warning') {
                $failed_tests[] = $r;
            }
        }
    }
}

// Definizione problemi risolvibili
$fixable_problems = [
    '1.4' => [
        'title' => 'Domande Orfane',
        'description' => 'Domande nei quiz senza competenze assegnate',
        'icon' => '❓',
        'page' => 'fix_orphan_questions.php',
        'actions' => ['Visualizza lista', 'Assegna competenza default', 'Link a Question Bank']
    ],
    '2.4' => [
        'title' => 'Autovalutazioni Mancanti',
        'description' => 'Competenze testate ma non autovalutate dagli utenti test',
        'icon' => '📋',
        'page' => 'fix_selfassessment.php',
        'actions' => ['Visualizza mancanti', 'Genera per utenti test']
    ],
    '3.6' => [
        'title' => 'Punteggi LabEval Errati',
        'description' => 'Sessioni con punteggio salvato diverso dal calcolato',
        'icon' => '🔬',
        'page' => 'fix_labeval.php',
        'actions' => ['Visualizza dettaglio', 'Ricalcola punteggio', 'Elimina sessione']
    ],
    '1.1' => [
        'title' => 'Copertura Competenze',
        'description' => 'Percentuale di domande con competenze assegnate',
        'icon' => '📊',
        'page' => 'fix_orphan_questions.php',
        'actions' => ['Vai a domande orfane']
    ]
];

global $DB;

// Conta problemi attuali nel sistema
$current_problems = [];

// Domande orfane
$current_problems['1.4'] = $DB->count_records_sql("
    SELECT COUNT(DISTINCT qv.questionid)
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id 
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    WHERE qc.id IS NULL
");

// Sessioni labeval con punteggio errato
$current_problems['3.6'] = $DB->count_records_sql("
    SELECT COUNT(*)
    FROM {local_labeval_sessions} s
    WHERE s.status = 'completed'
    AND ABS(COALESCE(s.totalscore, 0) - COALESCE((
        SELECT SUM(r.rating)
        FROM {local_labeval_ratings} r
        WHERE r.sessionid = s.id
    ), 0)) > 1
");

echo $OUTPUT->header();
?>

<style>
.fix-container { max-width: 1200px; margin: 0 auto; }
.fix-header {
    background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.fix-header h1 { margin: 0 0 5px 0; }
.fix-header p { margin: 0; opacity: 0.9; }

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}
.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}
.card-header h3 { margin: 0; font-size: 16px; }
.card-body { padding: 20px; }

.problems-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.problem-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.problem-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}
.problem-card.has-issues { border-left: 4px solid #dc3545; }
.problem-card.no-issues { border-left: 4px solid #28a745; }

.problem-header {
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}
.problem-icon {
    font-size: 32px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 10px;
}
.problem-info { flex: 1; }
.problem-info h4 { margin: 0 0 5px 0; }
.problem-info p { margin: 0; color: #666; font-size: 13px; }

.problem-stats {
    padding: 0 20px 15px;
    display: flex;
    gap: 15px;
}
.stat-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.stat-badge.danger { background: #f8d7da; color: #721c24; }
.stat-badge.success { background: #d4edda; color: #155724; }
.stat-badge.warning { background: #fff3cd; color: #856404; }

.problem-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-primary:hover { background: #2a5298; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }

.run-context {
    background: #e9ecef;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.run-context .info { }
.run-context .info strong { display: block; }
.run-context .info small { color: #666; }

.failed-tests-list {
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}
.failed-test-item {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.failed-test-item:last-child { border-bottom: none; }
.failed-test-item .test-info { }
.failed-test-item .test-code {
    font-weight: 700;
    color: #dc3545;
    margin-right: 10px;
}
.failed-test-item .test-name { font-weight: 600; }
.failed-test-item .test-detail { font-size: 13px; color: #666; display: block; margin-top: 3px; }
</style>

<div class="fix-container">
    
    <div class="fix-header">
        <h1>🔧 Centro Risoluzione Problemi</h1>
        <p>Visualizza, analizza e risolvi i problemi trovati nei test</p>
    </div>
    
    <?php if ($run): ?>
    <!-- Contesto Run -->
    <div class="run-context">
        <div class="info">
            <strong><?php echo $run->name; ?></strong>
            <small><?php echo userdate($run->timecreated, '%d/%m/%Y %H:%M'); ?> · 
                   <?php echo $run->failed_tests; ?> problemi trovati</small>
        </div>
        <a href="results.php?fts_runid=<?php echo $run->id; ?>" class="btn btn-secondary btn-sm">
            📋 Vedi Risultati Completi
        </a>
    </div>
    
    <?php if (!empty($failed_tests)): ?>
    <!-- Test Falliti dal Run -->
    <div class="card">
        <div class="card-header">
            <h3>❌ Problemi da Risolvere (dal test)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="failed-tests-list">
                <?php foreach ($failed_tests as $ft): ?>
                <div class="failed-test-item">
                    <div class="test-info">
                        <span class="test-code"><?php echo $ft->testcode; ?></span>
                        <span class="test-name"><?php echo $ft->testname; ?></span>
                        <span class="test-detail"><?php echo $ft->details; ?></span>
                    </div>
                    <?php if (isset($fixable_problems[$ft->testcode])): ?>
                    <a href="<?php echo $fixable_problems[$ft->testcode]['page']; ?>?fts_runid=<?php echo $run->id; ?>&fts_testcode=<?php echo $ft->testcode; ?>" 
                       class="btn btn-danger">
                        🔧 Risolvi
                    </a>
                    <?php else: ?>
                    <span class="btn btn-secondary" style="opacity: 0.5;">Non risolvibile</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Tutti i Problemi Risolvibili -->
    <div class="card">
        <div class="card-header">
            <h3>🛠️ Strumenti di Risoluzione Disponibili</h3>
        </div>
        <div class="card-body">
            <div class="problems-grid">
                
                <!-- Domande Orfane -->
                <div class="problem-card <?php echo $current_problems['1.4'] > 0 ? 'has-issues' : 'no-issues'; ?>">
                    <div class="problem-header">
                        <div class="problem-icon">❓</div>
                        <div class="problem-info">
                            <h4>Domande Orfane</h4>
                            <p>Domande nei quiz senza competenze assegnate</p>
                        </div>
                    </div>
                    <div class="problem-stats">
                        <?php if ($current_problems['1.4'] > 0): ?>
                        <span class="stat-badge danger"><?php echo $current_problems['1.4']; ?> domande</span>
                        <?php else: ?>
                        <span class="stat-badge success">✓ Nessuna</span>
                        <?php endif; ?>
                    </div>
                    <div class="problem-actions">
                        <a href="fix_orphan_questions.php" class="btn btn-primary">
                            🔍 Visualizza e Risolvi
                        </a>
                    </div>
                </div>
                
                <!-- Autovalutazioni -->
                <div class="problem-card no-issues">
                    <div class="problem-header">
                        <div class="problem-icon">📋</div>
                        <div class="problem-info">
                            <h4>Autovalutazioni</h4>
                            <p>Genera autovalutazioni mancanti per utenti test</p>
                        </div>
                    </div>
                    <div class="problem-stats">
                        <span class="stat-badge warning">Solo utenti test</span>
                    </div>
                    <div class="problem-actions">
                        <a href="fix_selfassessment.php" class="btn btn-primary">
                            🔍 Visualizza e Genera
                        </a>
                    </div>
                </div>
                
                <!-- LabEval -->
                <div class="problem-card <?php echo $current_problems['3.6'] > 0 ? 'has-issues' : 'no-issues'; ?>">
                    <div class="problem-header">
                        <div class="problem-icon">🔬</div>
                        <div class="problem-info">
                            <h4>Punteggi LabEval</h4>
                            <p>Sessioni con punteggio inconsistente</p>
                        </div>
                    </div>
                    <div class="problem-stats">
                        <?php if ($current_problems['3.6'] > 0): ?>
                        <span class="stat-badge danger"><?php echo $current_problems['3.6']; ?> sessioni</span>
                        <?php else: ?>
                        <span class="stat-badge success">✓ Nessuna</span>
                        <?php endif; ?>
                    </div>
                    <div class="problem-actions">
                        <a href="fix_labeval.php" class="btn btn-primary">
                            🔍 Visualizza e Correggi
                        </a>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 25px;">
        <a href="index.php" class="btn btn-secondary">← Dashboard</a>
        <a href="run.php" class="btn btn-secondary" style="margin-left: 10px;">▶️ Esegui Test</a>
        <a href="results.php" class="btn btn-secondary" style="margin-left: 10px;">📋 Risultati</a>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
