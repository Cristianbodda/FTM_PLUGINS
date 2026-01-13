<?php
/**
 * FTM Test Suite - Dashboard principale
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');
require_once(__DIR__ . '/classes/data_generator.php');
require_once(__DIR__ . '/classes/test_runner.php');

use local_ftm_testsuite\test_manager;
use local_ftm_testsuite\data_generator;

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_ftm_testsuite'));
$PAGE->set_heading(get_string('pluginname', 'local_ftm_testsuite'));
$PAGE->set_pagelayout('admin');

$action = optional_param('fts_action', '', PARAM_ALPHA);

// Gestione azioni
$message = '';
$messagetype = 'info';

if ($action === 'createusers' && confirm_sesskey()) {
    $manager = new test_manager();
    $created = $manager->create_test_users();
    $message = 'Utenti test creati: ' . count($created);
    $messagetype = 'success';
}

// Carica dati
$manager = new test_manager();
$testusers = $manager->get_test_users();
$history = test_manager::get_history(30);

// Statistiche sistema
global $DB;
$stats = [
    'courses_with_quiz' => $DB->count_records_sql("SELECT COUNT(DISTINCT course) FROM {quiz}"),
    'total_quizzes' => $DB->count_records('quiz'),
    'total_competencies' => $DB->count_records('competency'),
    'total_assignments' => $DB->count_records('qbank_competenciesbyquestion'),
    'test_runs' => count($history),
    'last_run' => !empty($history) ? reset($history) : null
];

echo $OUTPUT->header();
?>

<style>
.ftm-testsuite-container {
    max-width: 1400px;
    margin: 0 auto;
}
.ftm-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.ftm-header h1 { margin: 0 0 10px 0; font-size: 28px; }
.ftm-header p { margin: 0; opacity: 0.9; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
}
.stat-card .number {
    font-size: 36px;
    font-weight: 700;
    color: #1e3c72;
}
.stat-card .label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}
.stat-card.success .number { color: #28a745; }
.stat-card.warning .number { color: #ffc107; }
.stat-card.danger .number { color: #dc3545; }
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}
.card-header h3 { margin: 0; font-size: 18px; }
.card-body { padding: 20px; }
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
.btn-primary { background: #1e3c72; color: white; }
.btn-primary:hover { background: #2a5298; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; color: white; }
.btn-warning { background: #ffc107; color: #333; }
.btn-danger { background: #dc3545; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-lg { padding: 15px 30px; font-size: 16px; }
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.action-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.2s;
}
.action-card:hover {
    border-color: #1e3c72;
    transform: translateY(-2px);
}
.action-card .icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.action-card h4 { margin: 0 0 10px 0; }
.action-card p { margin: 0 0 15px 0; color: #666; font-size: 14px; }
.test-users-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}
.test-user-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}
.test-user-card.low { border-left: 4px solid #dc3545; }
.test-user-card.medium { border-left: 4px solid #ffc107; }
.test-user-card.high { border-left: 4px solid #28a745; }
.test-user-card .percentage {
    font-size: 24px;
    font-weight: 700;
}
.history-table {
    width: 100%;
    border-collapse: collapse;
}
.history-table th, .history-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.history-table th { background: #f8f9fa; font-weight: 600; }
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
</style>

<div class="ftm-testsuite-container">
    
    <!-- Header -->
    <div class="ftm-header">
        <h1>🧪 FTM Test Suite</h1>
        <p>Sistema completo per verificare il funzionamento di tutti i plugin FTM prima della produzione</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistiche Sistema -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $stats['courses_with_quiz']; ?></div>
            <div class="label">📚 Corsi con Quiz</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $stats['total_quizzes']; ?></div>
            <div class="label">📝 Quiz Totali</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $stats['total_competencies']; ?></div>
            <div class="label">🎯 Competenze</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $stats['total_assignments']; ?></div>
            <div class="label">🔗 Assegnazioni</div>
        </div>
        <div class="stat-card <?php echo $stats['test_runs'] > 0 ? 'success' : 'warning'; ?>">
            <div class="number"><?php echo $stats['test_runs']; ?></div>
            <div class="label">🧪 Test Eseguiti (30gg)</div>
        </div>
    </div>
    
    <!-- Utenti Test -->
    <div class="card">
        <div class="card-header">
            <h3>👥 Utenti Test</h3>
        </div>
        <div class="card-body">
            <?php if (empty($testusers)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Nessun utente test trovato.</strong> 
                Crea gli utenti test per poter generare dati e eseguire i test.
            </div>
            <form method="post" action="">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="fts_action" value="createusers">
                <button type="submit" class="btn btn-success btn-lg">
                    ➕ Crea Utenti Test (30%, 65%, 95%)
                </button>
            </form>
            <?php else: ?>
            <div class="test-users-grid">
                <?php foreach ($testusers as $tu): 
                    $class = $tu->testprofile === 'low30' ? 'low' : ($tu->testprofile === 'medium65' ? 'medium' : 'high');
                ?>
                <div class="test-user-card <?php echo $class; ?>">
                    <div class="percentage" style="color: <?php echo $class === 'low' ? '#dc3545' : ($class === 'medium' ? '#ffc107' : '#28a745'); ?>">
                        <?php echo $tu->quiz_percentage; ?>%
                    </div>
                    <div><strong><?php echo $tu->username; ?></strong></div>
                    <div style="font-size: 12px; color: #666;"><?php echo $tu->description; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Azioni Rapide -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Azioni Rapide</h3>
        </div>
        <div class="card-body">
            <div class="action-grid">
                <a href="generate.php" class="action-card">
                    <div class="icon">📊</div>
                    <h4>Genera Dati Test</h4>
                    <p>Crea tentativi quiz, autovalutazioni e valutazioni lab per gli utenti test</p>
                    <span class="btn btn-info">Genera Dati</span>
                </a>
                
                <a href="run.php" class="action-card">
                    <div class="icon">▶️</div>
                    <h4>Esegui Test</h4>
                    <p>Esegui la suite completa di 40 test per verificare il sistema</p>
                    <span class="btn btn-primary">Esegui Test</span>
                </a>
                
                <a href="results.php" class="action-card">
                    <div class="icon">📋</div>
                    <h4>Visualizza Risultati</h4>
                    <p>Vedi i risultati dettagliati degli ultimi test eseguiti</p>
                    <span class="btn btn-success">Vedi Risultati</span>
                </a>
                
                <a href="cleanup.php" class="action-card">
                    <div class="icon">🧹</div>
                    <h4>Pulisci Dati Test</h4>
                    <p>Elimina tutti i dati generati dagli utenti test</p>
                    <span class="btn btn-danger">Pulisci</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Storico Test -->
    <?php if (!empty($history)): ?>
    <div class="card">
        <div class="card-header">
            <h3>📜 Storico Test (ultimi 30 giorni)</h3>
        </div>
        <div class="card-body">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nome</th>
                        <th>Stato</th>
                        <th>Passati</th>
                        <th>Falliti</th>
                        <th>Warning</th>
                        <th>Successo</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($history, 0, 10) as $run): ?>
                    <tr>
                        <td><?php echo userdate($run->timecreated, '%d/%m/%Y %H:%M'); ?></td>
                        <td><?php echo $run->name; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($run->status); ?>
                            </span>
                        </td>
                        <td style="color: #28a745; font-weight: bold;"><?php echo $run->passed_tests; ?></td>
                        <td style="color: #dc3545; font-weight: bold;"><?php echo $run->failed_tests; ?></td>
                        <td style="color: #ffc107; font-weight: bold;"><?php echo $run->warning_tests; ?></td>
                        <td>
                            <strong><?php echo $run->success_rate; ?>%</strong>
                        </td>
                        <td>
                            <a href="results.php?fts_runid=<?php echo $run->id; ?>" class="btn btn-primary" style="padding: 5px 12px; font-size: 12px;">
                                👁️ Dettagli
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Link Sistema -->
    <div class="card">
        <div class="card-header">
            <h3>🔗 Link Sistema FTM</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="../competencymanager/system_check.php" class="btn btn-primary">🔬 System Check</a>
                <a href="../ftm_hub/index.php" class="btn btn-info">🛠️ FTM Hub</a>
                <a href="../competencymanager/index.php" class="btn btn-success">📊 Competency Manager</a>
                <a href="../coachmanager/index.php" class="btn btn-warning">👨‍🏫 Coach Manager</a>
                <a href="../labeval/index.php" class="btn btn-danger">🔬 Lab Eval</a>
                <a href="../competencymanager/simulate_student.php" class="btn btn-primary" style="background: #6f42c1;">🤖 Simulatore Studente</a>
            </div>
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
