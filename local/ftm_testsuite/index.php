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
require_once($CFG->dirroot . '/local/ftm_common/classes/design_helper.php');

use local_ftm_testsuite\test_manager;
use local_ftm_testsuite\data_generator;
use local_ftm_common\design_helper;

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_ftm_testsuite'));
$PAGE->set_heading(get_string('pluginname', 'local_ftm_testsuite'));
$PAGE->set_pagelayout('admin');

// Carica FTM Design System
$is_new_design = design_helper::load_design($PAGE);

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

// Carica lista corsi per i link (tutti i corsi, non solo quelli con quiz)
$courses_with_quiz = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname, COUNT(q.id) as quiz_count
    FROM {course} c
    LEFT JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
");
$selected_courseid = optional_param('courseid', 0, PARAM_INT);

echo $OUTPUT->header();

// Toggle button per design
echo design_helper::render_toggle_button($PAGE->url);

$container_class = $is_new_design ? 'ftm-page-bg' : '';
$header_class = $is_new_design ? 'ftm-header' : 'ftm-header-classic';
?>

<?php if ($is_new_design): ?>
<style>
/* Override per FTM Design System */
.ftm-testsuite-container {
    max-width: 1400px;
    margin: 0 auto;
}
.ftm-header {
    background: linear-gradient(135deg, #F5A623 0%, #f7b84e 100%);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(245, 166, 35, 0.3);
}
.ftm-header h1 { margin: 0 0 10px 0; font-size: 28px; font-weight: 700; }
.ftm-header p { margin: 0; opacity: 0.95; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    text-align: center;
    border-left: 4px solid #F5A623;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.stat-card .number {
    font-size: 40px;
    font-weight: 700;
    color: #1A5A5A;
    line-height: 1;
}
.stat-card .label {
    color: #64748B;
    font-size: 14px;
    margin-top: 8px;
    font-weight: 500;
}
.stat-card.success { border-left-color: #28a745; }
.stat-card.success .number { color: #28a745; }
.stat-card.warning { border-left-color: #EAB308; }
.stat-card.warning .number { color: #EAB308; }
.stat-card.danger { border-left-color: #dc3545; }
.stat-card.danger .number { color: #dc3545; }
.card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}
.card-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #1A5A5A; }
.card-body { padding: 24px; }
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
.btn-success:hover { background: #218838; color: white; }
.btn-warning { background: #EAB308; color: #333; }
.btn-danger { background: #dc3545; color: white; }
.btn-info { background: #1A5A5A; color: white; }
.btn-info:hover { background: #134545; color: white; }
.btn-lg { padding: 15px 30px; font-size: 16px; }
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.action-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
}
.action-card:hover {
    border-color: #F5A623;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(245, 166, 35, 0.2);
}
.action-card .icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.action-card h4 { margin: 0 0 10px 0; color: #1A5A5A; font-weight: 600; }
.action-card p { margin: 0 0 15px 0; color: #64748B; font-size: 14px; }
.test-users-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}
.test-user-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.test-user-card.low { border-left: 5px solid #dc3545; }
.test-user-card.medium { border-left: 5px solid #EAB308; }
.test-user-card.high { border-left: 5px solid #28a745; }
.test-user-card .percentage {
    font-size: 32px;
    font-weight: 700;
}
.history-table {
    width: 100%;
    border-collapse: collapse;
}
.history-table th, .history-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #E2E8F0;
}
.history-table th { background: #f8fafc; font-weight: 600; color: #1A5A5A; }
.history-table tr:hover { background: #f8fafc; }
.badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 4px solid;
}
.alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
.alert-warning { background: #fff3cd; color: #856404; border-left-color: #EAB308; }
.alert-info { background: #e8f4f8; color: #0c5460; border-left-color: #1A5A5A; }
</style>
<?php else: ?>
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
<?php endif; ?>

<div class="<?php echo $is_new_design ? 'ftm-page-bg' : ''; ?>" style="<?php echo $is_new_design ? 'padding: 20px;' : ''; ?>">
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

    <!-- Strumenti Diagnostica e Generazione -->
    <div class="card">
        <div class="card-header">
            <h3>🔧 Strumenti Diagnostica e Generazione</h3>
        </div>
        <div class="card-body">
            <div class="action-grid">
                <a href="quiz_competencies_editor.php" class="action-card" style="border: 3px solid #28a745;">
                    <div class="icon">✏️</div>
                    <h4>Quiz Competencies Editor</h4>
                    <p>Visualizza e modifica domande quiz con competenze, risposte e export Excel</p>
                    <span class="btn btn-success">Apri Editor</span>
                </a>

                <a href="../competencyxmlimport/quiz_export.php<?php echo $selected_courseid > 0 ? '?courseid=' . $selected_courseid : ''; ?>" class="action-card" style="border: 3px solid #17a2b8;">
                    <div class="icon">📤</div>
                    <h4>Quiz Export Tool</h4>
                    <p>Esporta domande, risposte e competenze dei quiz in CSV/Excel per analisi duplicati</p>
                    <span class="btn btn-info">Esporta Quiz</span>
                </a>

                <a href="find_orphan_questions.php" class="action-card">
                    <div class="icon">🔍</div>
                    <h4>Domande Orfane</h4>
                    <p>Trova domande senza competenze assegnate</p>
                    <span class="btn btn-danger">Trova</span>
                </a>

                <a href="generate_selfassessments.php" class="action-card">
                    <div class="icon">🧠</div>
                    <h4>Genera Autovalutazioni</h4>
                    <p>Crea autovalutazioni automatiche per utenti</p>
                    <span class="btn" style="background: #6f42c1; color: white;">Genera SA</span>
                </a>

                <a href="generate_labeval.php" class="action-card">
                    <div class="icon">🔬</div>
                    <h4>Genera LabEval</h4>
                    <p>Crea sessioni valutazione laboratorio</p>
                    <span class="btn" style="background: #dc3545; color: white;">Genera Lab</span>
                </a>

                <a href="analyze_sector_coverage.php" class="action-card">
                    <div class="icon">📊</div>
                    <h4>Copertura Settori</h4>
                    <p>Analizza competenze mancanti per settore</p>
                    <span class="btn" style="background: #fd7e14; color: white;">Analizza</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Demo Coach e Quiz Tester -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); color: white;">
            <h3 style="color: white;">👨‍🏫 Demo Coach e Quiz Tester</h3>
        </div>
        <div class="card-body">
            <div class="action-grid" style="grid-template-columns: repeat(3, 1fr);">
                <a href="generate_coach_demo.php" class="action-card" style="border: 3px solid #6f42c1;">
                    <div class="icon">🎓</div>
                    <h4>Genera Demo Coach</h4>
                    <p>Crea 3 coach (Roberto Bravo, Fabio Marinoni, Graziano Margonar) con 21 studenti demo (7 per coach, uno per settore). Include quiz solo del settore assegnato, autovalutazioni, CPURC, settore primario e assegnazione coach.</p>
                    <span class="btn" style="background: #6f42c1; color: white;">Genera Demo</span>
                </a>

                <a href="admin_quiz_tester.php" class="action-card" style="border: 3px solid #9b59b6;">
                    <div class="icon">🎯</div>
                    <h4>Admin Quiz Tester</h4>
                    <p>Seleziona uno studente specifico, scegli quali quiz fargli fare e imposta la percentuale target. Utile per rigenerare singoli tentativi o testare quiz appena importati con un punteggio a tua scelta.</p>
                    <span class="btn" style="background: #9b59b6; color: white;">Quiz Tester</span>
                </a>

                <a href="../coachmanager/grant_coach_role.php" class="action-card" style="border: 3px solid #0066cc;">
                    <div class="icon">🔑</div>
                    <h4>Assegna Ruolo Coach</h4>
                    <p>Assegna la capability <strong>editingteacher a livello sistema</strong> a un utente. Necessario per accedere a: Coach Dashboard V2, Student Report, Export Word, confronto studenti e note coach.</p>
                    <span class="btn" style="background: #0066cc; color: white;">Assegna Ruolo</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Tool Import/Export Quiz -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
            <h3 style="color: white;">📥 Import/Export Quiz e Competenze</h3>
        </div>
        <div class="card-body">
            <div class="action-grid">
                <?php if ($selected_courseid > 0): ?>
                <a href="../competencyxmlimport/setup_universale.php?courseid=<?php echo $selected_courseid; ?>" class="action-card" style="border: 3px solid #28a745;">
                    <div class="icon">📥</div>
                    <h4>Setup Universale Quiz</h4>
                    <p>Import XML/Word/Excel con assegnazione competenze automatica (multi-quiz da Excel)</p>
                    <span class="btn btn-success">Setup Universale</span>
                </a>
                <?php else: ?>
                <div class="action-card" style="opacity: 0.6;">
                    <div class="icon">📥</div>
                    <h4>Setup Universale Quiz</h4>
                    <p>⚠️ Seleziona un corso sopra per abilitare questo tool</p>
                    <span class="btn" style="background: #ccc;">Seleziona Corso</span>
                </div>
                <?php endif; ?>

                <a href="../competencyxmlimport/quiz_export.php<?php echo $selected_courseid > 0 ? '?courseid=' . $selected_courseid : ''; ?>" class="action-card" style="border: 3px solid #17a2b8;">
                    <div class="icon">📤</div>
                    <h4>Quiz Export Tool</h4>
                    <p>Esporta domande, risposte e competenze in CSV/Excel per analisi</p>
                    <span class="btn btn-info">Esporta Quiz</span>
                </a>

                <a href="../competencyxmlimport/diagnostics_v2.php" class="action-card">
                    <div class="icon">🔍</div>
                    <h4>Diagnostica Competenze</h4>
                    <p>Analisi dettagliata mapping competenze-domande</p>
                    <span class="btn" style="background: #6f42c1; color: white;">Diagnostica</span>
                </a>

                <a href="../competencyxmlimport/competenze_mancanti.php" class="action-card">
                    <div class="icon">⚠️</div>
                    <h4>Competenze Mancanti</h4>
                    <p>Trova domande senza competenze assegnate per corso</p>
                    <span class="btn btn-warning">Mancanti</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Diagnostica Autovalutazione -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
            <h3 style="color: white;">🩺 Diagnostica Autovalutazione</h3>
        </div>
        <div class="card-body">
            <div class="action-grid">
                <a href="../selfassessment/diagnose_user.php" class="action-card" style="border: 3px solid #dc3545;">
                    <div class="icon">👤</div>
                    <h4>Diagnosi Utente</h4>
                    <p>Analizza settore, status, competenze assegnate e quiz completati per un utente specifico</p>
                    <span class="btn btn-danger">Diagnosi Utente</span>
                </a>

                <a href="../selfassessment/fix_missing_assignments.php" class="action-card" style="border: 3px solid #28a745;">
                    <div class="icon">🔧</div>
                    <h4>Fix Assegnazioni Mancanti</h4>
                    <p>Assegna competenze mancanti per quiz completati (batch fix per tutti gli utenti)</p>
                    <span class="btn btn-success">Esegui Fix</span>
                </a>

                <a href="../selfassessment/check_observer.php" class="action-card" style="border: 3px solid #17a2b8;">
                    <div class="icon">🔍</div>
                    <h4>Verifica Observer</h4>
                    <p>Controlla se l'observer selfassessment è registrato e funzionante</p>
                    <span class="btn btn-info">Verifica Observer</span>
                </a>

                <a href="../selfassessment/debug_observer.php" class="action-card">
                    <div class="icon">🐛</div>
                    <h4>Debug Observer</h4>
                    <p>Debug dettagliato del flusso observer quiz_attempt_submitted</p>
                    <span class="btn" style="background: #6f42c1; color: white;">Debug</span>
                </a>

                <a href="../selfassessment/force_assign.php" class="action-card">
                    <div class="icon">⚡</div>
                    <h4>Forza Assegnazione</h4>
                    <p>Assegna manualmente competenze a un utente specifico</p>
                    <span class="btn btn-warning">Forza</span>
                </a>

                <a href="../selfassessment/catchup_assignments.php" class="action-card">
                    <div class="icon">🔄</div>
                    <h4>Catchup Assignments</h4>
                    <p>Recupera assegnazioni mancanti per utenti test</p>
                    <span class="btn" style="background: #fd7e14; color: white;">Catchup</span>
                </a>

                <a href="../selfassessment/index.php" class="action-card">
                    <div class="icon">📋</div>
                    <h4>Gestione Autovalutazione</h4>
                    <p>Dashboard principale gestione autovalutazione</p>
                    <span class="btn btn-primary">Gestione</span>
                </a>

                <a href="../selfassessment/analyze_all_prefixes.php" class="action-card">
                    <div class="icon">🏷️</div>
                    <h4>Analisi Prefissi</h4>
                    <p>Analizza tutti i prefissi competenze per area mapping</p>
                    <span class="btn" style="background: #20c997; color: white;">Prefissi</span>
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
            <!-- Selettore Corso per link che richiedono courseid -->
            <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #1976d2;">
                <label style="font-weight: 600; display: block; margin-bottom: 10px;">📚 Seleziona corso per i link:</label>
                <form method="get" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select name="courseid" style="flex: 1; min-width: 250px; padding: 10px; border: 2px solid #1976d2; border-radius: 6px; font-size: 14px;">
                        <option value="0">-- Seleziona un corso --</option>
                        <?php foreach ($courses_with_quiz as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php echo ($selected_courseid == $c->id) ? 'selected' : ''; ?>>
                            <?php echo format_string($c->fullname); ?> (<?php echo $c->quiz_count; ?> quiz)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Seleziona</button>
                </form>
                <?php if ($selected_courseid > 0): ?>
                <p style="margin: 10px 0 0; color: #1565c0; font-size: 13px;">
                    ✅ Corso selezionato: <strong><?php echo format_string($courses_with_quiz[$selected_courseid]->fullname ?? 'N/A'); ?></strong>
                </p>
                <?php endif; ?>
            </div>

            <!-- Link generali (senza courseid) -->
            <p style="font-weight: 600; margin-bottom: 10px; color: #666;">Link Generali:</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                <a href="../competencymanager/system_check.php" class="btn btn-primary">🔬 System Check</a>
                <a href="../ftm_hub/index.php" class="btn btn-info">🛠️ FTM Hub</a>
                <a href="../labeval/index.php" class="btn btn-danger">🔬 Lab Eval</a>
            </div>

            <!-- Link che richiedono courseid -->
            <p style="font-weight: 600; margin-bottom: 10px; color: #666;">Link con Corso (seleziona sopra):</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($selected_courseid > 0): ?>
                <a href="../competencyxmlimport/setup_universale.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-success">📥 Setup Universale Quiz</a>
                <a href="../competencymanager/dashboard.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-success">📊 Competency Manager</a>
                <a href="../coachmanager/index.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-warning">👨‍🏫 Coach Manager</a>
                <a href="../competencymanager/simulate_student.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-primary" style="background: #6f42c1;">🤖 Simulatore Studente</a>
                <a href="../competencymanager/reports.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-info">📈 Report Classe</a>
                <a href="diagnose_course.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-danger">🔍 Diagnosi Corso</a>
                <?php else: ?>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">📥 Setup Universale Quiz</span>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">📊 Competency Manager</span>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">👨‍🏫 Coach Manager</span>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">🤖 Simulatore Studente</span>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">📈 Report Classe</span>
                <span class="btn" style="background: #ccc; color: #666; cursor: not-allowed;">🔍 Diagnosi Corso</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>
</div>

<?php
echo $OUTPUT->footer();
