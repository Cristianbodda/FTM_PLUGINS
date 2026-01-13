<?php
/**
 * FTM Test Suite - Configurazione ed Esecuzione Test
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');
require_once(__DIR__ . '/classes/test_runner.php');

use local_ftm_testsuite\test_manager;
use local_ftm_testsuite\test_runner;

require_login();
require_capability('local/ftm_testsuite:execute', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/run.php'));
$PAGE->set_title('Configura ed Esegui Test - FTM Test Suite');
$PAGE->set_heading('Configura ed Esegui Test');
$PAGE->set_pagelayout('admin');

// Parametri
$action = optional_param('fts_action', '', PARAM_ALPHA);
$frameworkid = optional_param('fts_frameworkid', 0, PARAM_INT);
$courseid = optional_param('fts_courseid', 0, PARAM_INT);
$userid = optional_param('fts_userid', 0, PARAM_INT);

// Verifica utenti test
$manager = new test_manager();
if (!$manager->has_test_users()) {
    redirect(new moodle_url('/local/ftm_testsuite/index.php'), 
        'Crea prima gli utenti test dalla dashboard.', null, \core\output\notification::NOTIFY_ERROR);
}

$testusers = $manager->get_test_users();
$run_result = null;

global $DB;

// Carica framework disponibili
$frameworks = $DB->get_records_sql("
    SELECT cf.id, cf.shortname, cf.idnumber, 
           COUNT(c.id) as comp_count,
           (SELECT COUNT(DISTINCT qc.questionid) 
            FROM {qbank_competenciesbyquestion} qc 
            JOIN {competency} c2 ON c2.id = qc.competencyid 
            WHERE c2.competencyframeworkid = cf.id) as question_count
    FROM {competency_framework} cf
    LEFT JOIN {competency} c ON c.competencyframeworkid = cf.id
    GROUP BY cf.id, cf.shortname, cf.idnumber
    ORDER BY cf.shortname
");

// Carica corsi con quiz
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.shortname,
           COUNT(DISTINCT q.id) as quiz_count,
           COUNT(DISTINCT qc.competencyid) as comp_count
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    LEFT JOIN {quiz_slots} qs ON qs.quizid = q.id
    LEFT JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
    LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    WHERE c.id > 1
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
");

// Rileva framework per ogni corso
foreach ($courses as &$c) {
    $detected = $DB->get_field_sql("
        SELECT comp.competencyframeworkid
        FROM {quiz} q
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
        JOIN {competency} comp ON comp.id = qbc.competencyid
        WHERE q.course = ?
        GROUP BY comp.competencyframeworkid
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ", [$c->id]);
    $c->detected_frameworkid = $detected ?: 0;
}

// Esegui test se richiesto
if ($action === 'run' && confirm_sesskey()) {
    $runname = optional_param('fts_runname', '', PARAM_TEXT);
    
    // Se non selezionato userid, usa il medium65
    if ($userid == 0) {
        foreach ($testusers as $tu) {
            if ($tu->testprofile === 'medium65') {
                $userid = $tu->userid;
                break;
            }
        }
    }
    
    // Passa la configurazione al runner
    $runner = new test_runner($courseid, $frameworkid, $userid);
    $run_result = $runner->run_all($runname);
}

// Calcola statistiche per la configurazione corrente
$config_stats = [
    'competencies' => 0,
    'questions' => 0,
    'quizzes' => 0
];

if ($frameworkid > 0 && isset($frameworks[$frameworkid])) {
    $config_stats['competencies'] = $frameworks[$frameworkid]->comp_count;
    $config_stats['questions'] = $frameworks[$frameworkid]->question_count;
}

if ($courseid > 0 && isset($courses[$courseid])) {
    $config_stats['quizzes'] = $courses[$courseid]->quiz_count;
}

echo $OUTPUT->header();
?>

<style>
.run-container { max-width: 1200px; margin: 0 auto; }
.run-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.card-header { 
    padding: 15px 20px; 
    border-bottom: 1px solid #eee; 
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 { margin: 0; font-size: 16px; }
.card-header .step-num {
    background: #1e3c72;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-weight: bold;
}
.card-body { padding: 20px; }

.radio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 10px;
}
.radio-option {
    display: flex;
    align-items: flex-start;
    padding: 12px 15px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.radio-option:hover { border-color: #28a745; background: #f0fff4; }
.radio-option.selected { border-color: #28a745; background: #d4edda; }
.radio-option.deprecated { opacity: 0.6; }
.radio-option input { margin-right: 10px; margin-top: 3px; }
.radio-option .info { flex: 1; }
.radio-option .name { font-weight: 600; display: block; }
.radio-option .meta { font-size: 12px; color: #666; }
.badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 5px; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-success { background: #d4edda; color: #155724; }

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}
.form-control:focus { border-color: #28a745; outline: none; }

.config-summary {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    border-radius: 12px;
    padding: 20px;
}
.config-summary h4 { margin: 0 0 15px 0; }
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}
.summary-item {
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}
.summary-item .value { font-size: 24px; font-weight: 700; }
.summary-item .label { font-size: 12px; opacity: 0.8; }

.btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; color: white; }
.btn-primary { background: #1e3c72; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-lg { padding: 15px 40px; font-size: 18px; }

.auto-detect { 
    background: #17a2b8; 
    color: white; 
    padding: 2px 8px; 
    border-radius: 10px; 
    font-size: 10px; 
    margin-left: 5px; 
}

.result-box {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.result-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.result-card {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
}
.result-card.passed { background: #d4edda; }
.result-card.failed { background: #f8d7da; }
.result-card.warning { background: #fff3cd; }
.result-card.total { background: #e9ecef; }
.result-card .number { font-size: 36px; font-weight: 700; }
.result-card .label { font-size: 12px; color: #666; }

.progress-bar {
    height: 25px;
    background: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.student-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
</style>

<div class="run-container">
    
    <div class="run-header">
        <h1>⚙️ Configura ed Esegui Test FTM</h1>
        <p>Seleziona framework, corso e studente prima di eseguire i test</p>
    </div>
    
    <?php if ($run_result): ?>
    <!-- Risultati Test -->
    <div class="result-box" style="border-left: 5px solid <?php echo $run_result->status === 'completed' ? '#28a745' : '#dc3545'; ?>;">
        <div class="result-header">
            <h2 style="margin: 0;">
                <?php echo $run_result->status === 'completed' ? '✅' : '❌'; ?>
                <?php echo $run_result->name; ?>
            </h2>
            <span style="color: #666;"><?php echo userdate($run_result->timecreated, '%d/%m/%Y %H:%M'); ?></span>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $run_result->success_rate; ?>%;">
                <?php echo $run_result->success_rate; ?>% Successo
            </div>
        </div>
        
        <div class="result-summary">
            <div class="result-card total">
                <div class="number"><?php echo $run_result->total_tests; ?></div>
                <div class="label">Test Totali</div>
            </div>
            <div class="result-card passed">
                <div class="number" style="color: #28a745;"><?php echo $run_result->passed_tests; ?></div>
                <div class="label">✅ Passati</div>
            </div>
            <div class="result-card failed">
                <div class="number" style="color: #dc3545;"><?php echo $run_result->failed_tests; ?></div>
                <div class="label">❌ Falliti</div>
            </div>
            <div class="result-card warning">
                <div class="number" style="color: #ffc107;"><?php echo $run_result->warning_tests; ?></div>
                <div class="label">⚠️ Warning</div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="results.php?fts_runid=<?php echo $run_result->id; ?>" class="btn btn-success btn-lg">
                📋 Vedi Dettagli e Risolvi Problemi
            </a>
            <a href="report_pdf.php?fts_runid=<?php echo $run_result->id; ?>" class="btn btn-primary" style="margin-left: 10px;">
                📄 Genera PDF
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Form Configurazione -->
    <form method="post" action="" id="testConfigForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="fts_action" value="run">
        
        <!-- Step 1: Framework -->
        <div class="card">
            <div class="card-header">
                <h3><span class="step-num">1</span> Framework di Competenze</h3>
            </div>
            <div class="card-body">
                <div class="radio-grid">
                    <label class="radio-option <?php echo $frameworkid == 0 ? 'selected' : ''; ?>">
                        <input type="radio" name="fts_frameworkid" value="0" 
                               <?php echo $frameworkid == 0 ? 'checked' : ''; ?>
                               onchange="updateSelection()">
                        <div class="info">
                            <span class="name">Tutti i Framework</span>
                            <span class="meta">Non consigliato - può mescolare dati</span>
                        </div>
                    </label>
                    
                    <?php foreach ($frameworks as $fw): 
                        $is_old = stripos($fw->shortname, 'old') !== false;
                    ?>
                    <label class="radio-option <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?> <?php echo $is_old ? 'deprecated' : ''; ?>">
                        <input type="radio" name="fts_frameworkid" value="<?php echo $fw->id; ?>" 
                               <?php echo $frameworkid == $fw->id ? 'checked' : ''; ?>
                               onchange="updateSelection()">
                        <div class="info">
                            <span class="name">
                                <?php echo $fw->shortname; ?>
                                <?php if ($is_old): ?><span class="badge badge-warning">⚠️ old</span><?php endif; ?>
                            </span>
                            <span class="meta">
                                <?php echo $fw->comp_count; ?> competenze · <?php echo $fw->question_count; ?> domande
                            </span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Corso -->
        <div class="card">
            <div class="card-header">
                <h3><span class="step-num">2</span> Corso</h3>
            </div>
            <div class="card-body">
                <select name="fts_courseid" id="fts_courseid" class="form-control" onchange="detectFramework(this)">
                    <option value="0">Tutti i corsi</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c->id; ?>" 
                            data-framework="<?php echo $c->detected_frameworkid; ?>"
                            <?php echo $courseid == $c->id ? 'selected' : ''; ?>>
                        <?php echo $c->fullname; ?> 
                        (<?php echo $c->quiz_count; ?> quiz, <?php echo $c->comp_count; ?> comp.)
                        <?php if ($c->detected_frameworkid && isset($frameworks[$c->detected_frameworkid])): ?>
                        → <?php echo $frameworks[$c->detected_frameworkid]->shortname; ?>
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666; display: block; margin-top: 8px;">
                    💡 Selezionando un corso, il framework viene rilevato automaticamente
                </small>
            </div>
        </div>
        
        <!-- Step 3: Studente -->
        <div class="card">
            <div class="card-header">
                <h3><span class="step-num">3</span> Studente da Testare</h3>
            </div>
            <div class="card-body">
                <p style="margin: 0 0 15px 0; color: #666;">Utenti Test:</p>
                <div class="radio-grid">
                    <?php foreach ($testusers as $tu): 
                        $color = $tu->testprofile === 'low30' ? '#dc3545' : ($tu->testprofile === 'medium65' ? '#ffc107' : '#28a745');
                        $is_default = $tu->testprofile === 'medium65';
                    ?>
                    <label class="radio-option <?php echo ($userid == $tu->userid || ($userid == 0 && $is_default)) ? 'selected' : ''; ?>">
                        <input type="radio" name="fts_userid" value="<?php echo $tu->userid; ?>" 
                               <?php echo ($userid == $tu->userid || ($userid == 0 && $is_default)) ? 'checked' : ''; ?>
                               onchange="updateSelection()">
                        <div class="info">
                            <span class="name">
                                <span style="color: <?php echo $color; ?>;">●</span>
                                <?php echo $tu->quiz_percentage; ?>% - <?php echo $tu->username; ?>
                                <?php if ($is_default): ?><span class="badge badge-success">consigliato</span><?php endif; ?>
                            </span>
                            <span class="meta"><?php echo $tu->description; ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="student-section">
                    <p style="margin: 0 0 10px 0; color: #666;">Oppure cerca uno studente reale:</p>
                    <input type="text" id="studentSearch" class="form-control" 
                           placeholder="🔍 Digita nome o email..." disabled
                           title="Funzionalità in arrivo">
                    <small style="color: #999;">Funzionalità in sviluppo</small>
                </div>
            </div>
        </div>
        
        <!-- Riepilogo -->
        <div class="config-summary">
            <h4>📊 Riepilogo Configurazione</h4>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="value" id="summaryFramework">
                        <?php 
                        if ($frameworkid > 0 && isset($frameworks[$frameworkid])) {
                            echo substr($frameworks[$frameworkid]->shortname, 0, 15);
                        } else {
                            echo 'Tutti';
                        }
                        ?>
                    </div>
                    <div class="label">Framework</div>
                </div>
                <div class="summary-item">
                    <div class="value"><?php echo $config_stats['competencies'] ?: '—'; ?></div>
                    <div class="label">Competenze</div>
                </div>
                <div class="summary-item">
                    <div class="value"><?php echo $config_stats['quizzes'] ?: '—'; ?></div>
                    <div class="label">Quiz</div>
                </div>
                <div class="summary-item">
                    <div class="value"><?php echo $config_stats['questions'] ?: '—'; ?></div>
                    <div class="label">Domande</div>
                </div>
            </div>
        </div>
        
        <!-- Nome e Submit -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-body" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label for="fts_runname" style="display: block; margin-bottom: 8px; font-weight: 600;">
                        📝 Nome Test Run
                    </label>
                    <input type="text" name="fts_runname" id="fts_runname" class="form-control" 
                           value="Test <?php echo date('d/m/Y H:i'); ?>">
                </div>
                <button type="submit" class="btn btn-success btn-lg">
                    ▶️ ESEGUI TEST
                </button>
            </div>
        </div>
        
    </form>
    
    <div style="text-align: center; margin-top: 25px;">
        <a href="index.php" class="btn btn-secondary">← Dashboard</a>
        <a href="generate.php" class="btn btn-secondary" style="margin-left: 10px;">📊 Genera Dati</a>
        <a href="results.php" class="btn btn-secondary" style="margin-left: 10px;">📋 Ultimi Risultati</a>
    </div>
    
</div>

<script>
function detectFramework(select) {
    var option = select.options[select.selectedIndex];
    var frameworkId = option.getAttribute('data-framework');
    
    if (frameworkId && frameworkId != '0' && frameworkId != '') {
        var radios = document.querySelectorAll('input[name="fts_frameworkid"]');
        radios.forEach(function(radio) {
            var label = radio.closest('.radio-option');
            if (radio.value == frameworkId) {
                radio.checked = true;
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    }
}

function updateSelection() {
    document.querySelectorAll('.radio-option').forEach(function(opt) {
        var input = opt.querySelector('input[type="radio"]');
        if (input && input.checked) {
            opt.classList.add('selected');
        } else {
            opt.classList.remove('selected');
        }
    });
}

document.addEventListener('DOMContentLoaded', updateSelection);
</script>

<?php
echo $OUTPUT->footer();
