<?php
/**
 * FTM Test Suite - Genera Dati Test
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
require_capability('local/ftm_testsuite:execute', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/generate.php'));
$PAGE->set_title('Genera Dati Test - FTM Test Suite');
$PAGE->set_heading('Genera Dati Test');
$PAGE->set_pagelayout('admin');

$action = optional_param('fts_action', '', PARAM_ALPHA);
$courseid = optional_param('fts_courseid', 0, PARAM_INT);

// Verifica utenti test
$manager = new test_manager();
if (!$manager->has_test_users()) {
    redirect(new moodle_url('/local/ftm_testsuite/index.php'), 
        'Crea prima gli utenti test dalla dashboard.', null, \core\output\notification::NOTIFY_ERROR);
}

$testusers = $manager->get_test_users();
$stats = null;
$log = [];

// Genera dati se richiesto
if ($action === 'generate' && confirm_sesskey()) {
    $generator = new data_generator();
    $stats = $generator->generate_all($courseid);
    $log = $generator->get_log();
}

// Carica corsi disponibili
global $DB;
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, COUNT(q.id) as quiz_count
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
");

// Statistiche esistenti per utenti test
$existing_stats = [];
foreach ($testusers as $tu) {
    $existing_stats[$tu->userid] = [
        'quiz_attempts' => $DB->count_records('quiz_attempts', ['userid' => $tu->userid, 'state' => 'finished']),
        'selfassessments' => $DB->count_records('local_selfassessment', ['userid' => $tu->userid]),
        'labeval' => $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_labeval_sessions} s
            JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
            WHERE a.studentid = ? AND s.status = 'completed'
        ", [$tu->userid])
    ];
}

echo $OUTPUT->header();
?>

<style>
.generate-container { max-width: 1200px; margin: 0 auto; }
.generate-header {
    background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
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
.card-header { padding: 20px; border-bottom: 1px solid #eee; background: #f8f9fa; }
.card-header h3 { margin: 0; }
.card-body { padding: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
}
.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-info { background: #17a2b8; color: white; }
.btn-lg { padding: 15px 40px; font-size: 18px; }
.btn-secondary { background: #6c757d; color: white; }
.users-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.user-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.user-card.low { border-top: 4px solid #dc3545; }
.user-card.medium { border-top: 4px solid #ffc107; }
.user-card.high { border-top: 4px solid #28a745; }
.user-card .percentage {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 10px;
}
.user-card .stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 15px;
    font-size: 12px;
}
.user-card .stat-item {
    background: white;
    padding: 8px;
    border-radius: 6px;
}
.user-card .stat-item .num { font-size: 18px; font-weight: bold; }
.log-container {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 20px;
    border-radius: 8px;
    max-height: 400px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 13px;
}
.log-entry { padding: 4px 0; border-bottom: 1px solid #333; }
.log-entry.info { color: #9cdcfe; }
.log-entry.success { color: #4ec9b0; }
.log-entry.warning { color: #dcdcaa; }
.log-entry.error { color: #f14c4c; }
.log-time { color: #858585; margin-right: 10px; }
.stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.stat-box {
    background: #d4edda;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
}
.stat-box .number { font-size: 36px; font-weight: 700; color: #28a745; }
.stat-box .label { color: #666; }
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; }
.alert-warning { background: #fff3cd; color: #856404; }
</style>

<div class="generate-container">
    
    <div class="generate-header">
        <h1>📊 Genera Dati Test</h1>
        <p>Crea tentativi quiz, autovalutazioni e valutazioni lab per gli utenti test</p>
    </div>
    
    <?php if ($stats): ?>
    <!-- Risultati Generazione -->
    <div class="card">
        <div class="card-header" style="background: #d4edda;">
            <h3>✅ Generazione Completata!</h3>
        </div>
        <div class="card-body">
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="number"><?php echo $stats['quiz_attempts']; ?></div>
                    <div class="label">📝 Tentativi Quiz</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?php echo $stats['selfassessments']; ?></div>
                    <div class="label">📋 Autovalutazioni</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?php echo $stats['labeval_sessions']; ?></div>
                    <div class="label">🔬 Sessioni Lab</div>
                </div>
            </div>
            
            <?php if (!empty($stats['errors'])): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Attenzione:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <?php foreach ($stats['errors'] as $err): ?>
                    <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Log -->
            <?php if (!empty($log)): ?>
            <h4 style="margin-top: 20px;">📜 Log Operazioni</h4>
            <div class="log-container">
                <?php foreach ($log as $entry): ?>
                <div class="log-entry <?php echo $entry['type']; ?>">
                    <span class="log-time">[<?php echo $entry['time']; ?>]</span>
                    <?php echo $entry['message']; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="run.php" class="btn btn-info btn-lg">
                    ▶️ Esegui Test Adesso
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Utenti Test e Dati Esistenti -->
    <div class="card">
        <div class="card-header">
            <h3>👥 Utenti Test e Dati Esistenti</h3>
        </div>
        <div class="card-body">
            <div class="users-grid">
                <?php foreach ($testusers as $tu): 
                    $class = $tu->testprofile === 'low30' ? 'low' : ($tu->testprofile === 'medium65' ? 'medium' : 'high');
                    $color = $class === 'low' ? '#dc3545' : ($class === 'medium' ? '#ffc107' : '#28a745');
                    $es = $existing_stats[$tu->userid];
                ?>
                <div class="user-card <?php echo $class; ?>">
                    <div class="percentage" style="color: <?php echo $color; ?>;">
                        <?php echo $tu->quiz_percentage; ?>%
                    </div>
                    <div><strong><?php echo $tu->username; ?></strong></div>
                    <div class="stats">
                        <div class="stat-item">
                            <div class="num"><?php echo $es['quiz_attempts']; ?></div>
                            <div>Quiz</div>
                        </div>
                        <div class="stat-item">
                            <div class="num"><?php echo $es['selfassessments']; ?></div>
                            <div>Autoval.</div>
                        </div>
                        <div class="stat-item">
                            <div class="num"><?php echo $es['labeval']; ?></div>
                            <div>Lab</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            $has_data = false;
            foreach ($existing_stats as $es) {
                if ($es['quiz_attempts'] > 0 || $es['selfassessments'] > 0 || $es['labeval'] > 0) {
                    $has_data = true;
                    break;
                }
            }
            if ($has_data): 
            ?>
            <div class="alert alert-warning">
                <strong>ℹ️ Nota:</strong> Esistono già dati per gli utenti test. 
                La generazione salterà i dati già esistenti. 
                Per rigenerare tutto, usa prima la <a href="cleanup.php">pulizia dati</a>.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Form Generazione -->
    <div class="card">
        <div class="card-header">
            <h3>⚙️ Configura Generazione</h3>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="fts_action" value="generate">
                
                <div class="form-group">
                    <label for="fts_courseid">📚 Corso Target</label>
                    <select name="fts_courseid" id="fts_courseid" class="form-control">
                        <option value="0">Tutti i corsi con quiz (<?php echo count($courses); ?> corsi)</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c->id; ?>">
                            <?php echo $c->fullname; ?> (<?php echo $c->quiz_count; ?> quiz)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666;">Seleziona un corso specifico o genera dati per tutti i corsi.</small>
                </div>
                
                <div class="form-group">
                    <label>📋 Cosa verrà generato</label>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <p style="margin: 0 0 10px 0;"><strong>Per ogni utente test:</strong></p>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li><strong>Quiz:</strong> Un tentativo completato per ogni quiz del corso, con risposte distribuite secondo la % target dell'utente</li>
                            <li><strong>Autovalutazioni:</strong> Una autovalutazione per ogni competenza con livelli Bloom strategici per creare gap intenzionali</li>
                            <li><strong>LabEval:</strong> Una valutazione lab per ogni template disponibile nel settore del corso</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>🎯 Distribuzione Gap Intenzionali</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div style="background: #f8d7da; padding: 15px; border-radius: 8px;">
                            <strong style="color: #dc3545;">Studente 30%</strong>
                            <p style="margin: 5px 0 0; font-size: 13px;">Bloom alto (4-6) → Sovrastima<br><em>"Pensa di sapere ma non sa"</em></p>
                        </div>
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                            <strong style="color: #856404;">Studente 65%</strong>
                            <p style="margin: 5px 0 0; font-size: 13px;">Bloom medio (3-5) → Allineato<br><em>"Sa quanto pensa di sapere"</em></p>
                        </div>
                        <div style="background: #d4edda; padding: 15px; border-radius: 8px;">
                            <strong style="color: #28a745;">Studente 95%</strong>
                            <p style="margin: 5px 0 0; font-size: 13px;">Bloom basso (2-4) → Sottostima<br><em>"Sa più di quanto pensa"</em></p>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-info btn-lg">
                        📊 GENERA DATI TEST
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" class="btn btn-secondary">← Torna alla Dashboard</a>
        <a href="cleanup.php" class="btn btn-secondary" style="margin-left: 10px;">🧹 Pulisci Dati</a>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
