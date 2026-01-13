<?php
/**
 * FTM Test Suite - Pulizia Dati Test
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');
require_once(__DIR__ . '/classes/data_generator.php');

use local_ftm_testsuite\test_manager;
use local_ftm_testsuite\data_generator;

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/cleanup.php'));
$PAGE->set_title('Pulizia Dati - FTM Test Suite');
$PAGE->set_heading('Pulizia Dati Test');
$PAGE->set_pagelayout('admin');

$action = optional_param('fts_action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

$stats = null;
$message = '';

// Esegui pulizia se confermata
if ($action === 'cleandata' && confirm_sesskey() && $confirm) {
    $generator = new data_generator();
    $stats = $generator->cleanup_all();
    $message = 'data_cleaned';
}

if ($action === 'cleanruns' && confirm_sesskey() && $confirm) {
    $deleted = test_manager::cleanup_old_runs(0); // Elimina tutti
    $message = 'runs_cleaned';
    $stats = ['runs_deleted' => $deleted];
}

// Carica statistiche esistenti
$manager = new test_manager();
$testusers = $manager->get_test_users();

global $DB;
$existing = [
    'quiz_attempts' => 0,
    'selfassessments' => 0,
    'labeval_sessions' => 0
];

foreach ($testusers as $tu) {
    $existing['quiz_attempts'] += $DB->count_records('quiz_attempts', ['userid' => $tu->userid]);
    $existing['selfassessments'] += $DB->count_records('local_selfassessment', ['userid' => $tu->userid]);
    $existing['labeval_sessions'] += $DB->count_records_sql("
        SELECT COUNT(*) FROM {local_labeval_sessions} s
        JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
        WHERE a.studentid = ?
    ", [$tu->userid]);
}

$run_count = $DB->count_records('local_ftm_testsuite_runs');

echo $OUTPUT->header();
?>

<style>
.cleanup-container { max-width: 900px; margin: 0 auto; }
.cleanup-header {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}
.card-header { padding: 20px; border-bottom: 1px solid #eee; }
.card-header h3 { margin: 0; }
.card-body { padding: 20px; }
.warning-box {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.danger-box {
    background: #f8d7da;
    border: 2px solid #dc3545;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}
.stat-item .number { font-size: 28px; font-weight: 700; color: #dc3545; }
.stat-item .label { color: #666; font-size: 13px; }
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-lg { padding: 15px 30px; font-size: 16px; }
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; }
.success-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 15px;
}
.success-stat {
    background: white;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
}
.success-stat .num { font-size: 24px; font-weight: bold; color: #28a745; }
</style>

<div class="cleanup-container">
    
    <div class="cleanup-header">
        <h1>🧹 Pulizia Dati Test</h1>
        <p>Elimina i dati generati per gli utenti test e lo storico dei run</p>
    </div>
    
    <?php if ($message === 'data_cleaned'): ?>
    <div class="alert alert-success">
        <strong>✅ Pulizia dati completata!</strong>
        <div class="success-stats">
            <div class="success-stat">
                <div class="num"><?php echo $stats['quiz_attempts']; ?></div>
                <div>Quiz eliminati</div>
            </div>
            <div class="success-stat">
                <div class="num"><?php echo $stats['selfassessments']; ?></div>
                <div>Autovalutazioni</div>
            </div>
            <div class="success-stat">
                <div class="num"><?php echo $stats['labeval_sessions']; ?></div>
                <div>Sessioni Lab</div>
            </div>
        </div>
    </div>
    <?php elseif ($message === 'runs_cleaned'): ?>
    <div class="alert alert-success">
        <strong>✅ Storico test eliminato!</strong> <?php echo $stats['runs_deleted']; ?> run eliminati.
    </div>
    <?php endif; ?>
    
    <!-- Pulizia Dati Utenti Test -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Dati Utenti Test</h3>
        </div>
        <div class="card-body">
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="number"><?php echo $existing['quiz_attempts']; ?></div>
                    <div class="label">📝 Tentativi Quiz</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo $existing['selfassessments']; ?></div>
                    <div class="label">📋 Autovalutazioni</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo $existing['labeval_sessions']; ?></div>
                    <div class="label">🔬 Sessioni Lab</div>
                </div>
            </div>
            
            <?php if ($existing['quiz_attempts'] + $existing['selfassessments'] + $existing['labeval_sessions'] > 0): ?>
            <div class="warning-box">
                <strong>⚠️ Attenzione!</strong>
                <p style="margin: 10px 0 0;">Questa operazione eliminerà <strong>tutti</strong> i dati generati per i 3 utenti test 
                (tentativi quiz, autovalutazioni, valutazioni lab). I dati degli utenti reali <strong>non</strong> saranno toccati.</p>
            </div>
            
            <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler eliminare tutti i dati test? Questa azione non può essere annullata.');">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="fts_action" value="cleandata">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger btn-lg">
                    🗑️ Elimina Dati Utenti Test
                </button>
            </form>
            <?php else: ?>
            <p style="color: #666; text-align: center;">Nessun dato test da eliminare.</p>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Pulizia Storico Run -->
    <div class="card">
        <div class="card-header">
            <h3>📜 Storico Test Run</h3>
        </div>
        <div class="card-body">
            
            <div class="stats-grid" style="grid-template-columns: 1fr;">
                <div class="stat-item">
                    <div class="number"><?php echo $run_count; ?></div>
                    <div class="label">🧪 Test Run Salvati</div>
                </div>
            </div>
            
            <?php if ($run_count > 0): ?>
            <div class="danger-box">
                <strong>🚨 Attenzione!</strong>
                <p style="margin: 10px 0 0;">Questa operazione eliminerà <strong>tutto</strong> lo storico dei test eseguiti, 
                inclusi i risultati dettagliati e gli hash di integrità. Utile solo per reset completo del sistema.</p>
            </div>
            
            <form method="post" action="" onsubmit="return confirm('ATTENZIONE: Stai per eliminare TUTTO lo storico dei test. Sei assolutamente sicuro?');">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="fts_action" value="cleanruns">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger">
                    🗑️ Elimina Storico Test
                </button>
            </form>
            <?php else: ?>
            <p style="color: #666; text-align: center;">Nessun run salvato.</p>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Info Utenti Test -->
    <div class="card">
        <div class="card-header">
            <h3>ℹ️ Informazioni</h3>
        </div>
        <div class="card-body">
            <p><strong>Cosa viene eliminato con "Elimina Dati Utenti Test":</strong></p>
            <ul>
                <li>Tutti i tentativi quiz degli utenti test (quiz_attempts + question_attempts + steps)</li>
                <li>Tutte le autovalutazioni degli utenti test (local_selfassessment)</li>
                <li>Tutte le valutazioni lab degli utenti test (assignments, sessions, ratings, comp_scores)</li>
            </ul>
            
            <p><strong>Cosa NON viene eliminato:</strong></p>
            <ul>
                <li>Gli utenti test stessi (rimangono nel sistema per poter rigenerare dati)</li>
                <li>I dati di utenti reali</li>
                <li>Le iscrizioni ai corsi degli utenti test</li>
                <li>Lo storico dei test run (usa il pulsante dedicato)</li>
            </ul>
        </div>
    </div>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" class="btn btn-secondary">← Torna alla Dashboard</a>
        <a href="generate.php" class="btn btn-secondary" style="margin-left: 10px;">📊 Genera Nuovi Dati</a>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
