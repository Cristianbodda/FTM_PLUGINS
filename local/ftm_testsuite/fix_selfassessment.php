<?php
/**
 * FTM Test Suite - Risolvi Autovalutazioni Mancanti
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/fix_selfassessment.php'));
$PAGE->set_title('Genera Autovalutazioni - FTM Test Suite');
$PAGE->set_heading('Genera Autovalutazioni Mancanti');
$PAGE->set_pagelayout('admin');

// Parametri
$action = optional_param('fts_action', '', PARAM_ALPHA);
$frameworkid = optional_param('fts_frameworkid', 0, PARAM_INT);
$courseid = optional_param('fts_courseid', 0, PARAM_INT);
$userid = optional_param('fts_userid', 0, PARAM_INT);
$bloomlevel = optional_param('fts_bloomlevel', 3, PARAM_INT);
$competencyids = optional_param_array('fts_competencyids', [], PARAM_INT);

global $DB;

$message = '';
$messagetype = '';

// Carica utenti test
$testusers = $DB->get_records('local_ftm_testsuite_users');

// Esegui azione se richiesta
if ($action === 'generate' && confirm_sesskey() && !empty($competencyids)) {
    $generated = 0;
    
    // Se userid specifico, genera solo per quello
    $users_to_process = [];
    if ($userid > 0) {
        foreach ($testusers as $tu) {
            if ($tu->userid == $userid) {
                $users_to_process[] = $tu;
                break;
            }
        }
    } else {
        $users_to_process = $testusers;
    }
    
    foreach ($users_to_process as $tu) {
        // Determina livello Bloom in base al profilo
        $level = $bloomlevel;
        if ($bloomlevel == 0) { // Auto
            switch ($tu->testprofile) {
                case 'low30': $level = rand(4, 6); break; // Sovrastima
                case 'medium65': $level = rand(3, 4); break; // Allineato
                case 'high95': $level = rand(2, 4); break; // Sottostima
                default: $level = 3;
            }
        }
        
        foreach ($competencyids as $cid) {
            // Verifica se esiste già
            $exists = $DB->record_exists('local_selfassessment', [
                'userid' => $tu->userid,
                'competencyid' => $cid
            ]);
            
            if (!$exists) {
                $sa = new stdClass();
                $sa->userid = $tu->userid;
                $sa->competencyid = $cid;
                $sa->level = $level;
                $sa->timecreated = time();
                $sa->timemodified = time();
                $DB->insert_record('local_selfassessment', $sa);
                $generated++;
            }
        }
    }
    
    $message = "✅ Generate {$generated} autovalutazioni!";
    $messagetype = 'success';
}

// Carica framework
$frameworks = $DB->get_records_sql("
    SELECT cf.id, cf.shortname, COUNT(c.id) as comp_count
    FROM {competency_framework} cf
    LEFT JOIN {competency} c ON c.competencyframeworkid = cf.id
    GROUP BY cf.id, cf.shortname
    ORDER BY cf.shortname
");

// Carica corsi
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    ORDER BY c.fullname
");

// Trova competenze testate ma non autovalutate
$missing_competencies = [];

if (!empty($testusers)) {
    // Prendi il primo utente test come riferimento
    $ref_user = reset($testusers);
    
    // Costruisci query per competenze testate
    $where = '';
    $params = [$ref_user->userid];
    
    if ($frameworkid > 0) {
        $where .= ' AND c.competencyframeworkid = ?';
        $params[] = $frameworkid;
    }
    
    if ($courseid > 0) {
        $where .= ' AND q.course = ?';
        $params[] = $courseid;
    }
    
    $missing_competencies = $DB->get_records_sql("
        SELECT DISTINCT c.id, c.idnumber, c.shortname, cf.shortname as frameworkname
        FROM {competency} c
        JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
        JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
        JOIN {question_versions} qv ON qv.questionid = qc.questionid
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_references} qr ON qr.questionbankentryid = qbe.id AND qr.component = 'mod_quiz'
        JOIN {quiz_slots} qs ON qs.id = qr.itemid
        JOIN {quiz} q ON q.id = qs.quizid
        LEFT JOIN {local_selfassessment} sa ON sa.competencyid = c.id AND sa.userid = ?
        WHERE sa.id IS NULL {$where}
        ORDER BY cf.shortname, c.idnumber
    ", $params);
}

// Statistiche
$total_tested = $DB->count_records_sql("
    SELECT COUNT(DISTINCT c.id)
    FROM {competency} c
    JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
");

$total_selfassessed = 0;
if (!empty($testusers)) {
    $ref_user = reset($testusers);
    $total_selfassessed = $DB->count_records('local_selfassessment', ['userid' => $ref_user->userid]);
}

// Raggruppa per framework
$grouped = [];
foreach ($missing_competencies as $c) {
    if (!isset($grouped[$c->frameworkname])) {
        $grouped[$c->frameworkname] = [];
    }
    $grouped[$c->frameworkname][] = $c;
}

echo $OUTPUT->header();
?>

<style>
.fix-container { max-width: 1200px; margin: 0 auto; }
.fix-header {
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
.card-body { padding: 20px; }

.filters {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.filter-group { flex: 1; min-width: 180px; }
.filter-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
.form-control {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}
.stat-card .number { font-size: 28px; font-weight: 700; color: #1e3c72; }
.stat-card .label { font-size: 12px; color: #666; }
.stat-card.warning { background: #fff3cd; }
.stat-card.warning .number { color: #856404; }
.stat-card.success { background: #d4edda; }
.stat-card.success .number { color: #155724; }

.framework-group {
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}
.framework-header {
    padding: 12px 15px;
    background: #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.framework-header:hover { background: #dee2e6; }
.framework-header h4 { margin: 0; font-size: 14px; }
.framework-body { padding: 15px; display: none; }
.framework-group.open .framework-body { display: block; }

.competency-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}
.competency-item {
    padding: 10px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.competency-item:hover { border-color: #17a2b8; }
.competency-item .info { flex: 1; }
.competency-item .code { font-weight: 600; font-size: 12px; color: #17a2b8; }
.competency-item .name { font-size: 13px; }

.generate-panel {
    background: #d1ecf1;
    border: 2px solid #17a2b8;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}
.generate-panel h4 { margin: 0 0 15px 0; color: #0c5460; }
.generate-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.generate-row .field { min-width: 150px; }
.generate-row .field label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-info { background: #17a2b8; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; }
.alert-info { background: #d1ecf1; color: #0c5460; }

.count-badge {
    background: #dc3545;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
}
</style>

<div class="fix-container">
    
    <div class="fix-header">
        <h1>📋 Genera Autovalutazioni Mancanti</h1>
        <p>Crea autovalutazioni per competenze testate ma non ancora valutate dagli utenti test</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_tested; ?></div>
            <div class="label">Competenze Testate</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_selfassessed; ?></div>
            <div class="label">Autovalutazioni Esistenti</div>
        </div>
        <div class="stat-card <?php echo count($missing_competencies) > 0 ? 'warning' : 'success'; ?>">
            <div class="number"><?php echo count($missing_competencies); ?></div>
            <div class="label">Mancanti</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo count($testusers); ?></div>
            <div class="label">Utenti Test</div>
        </div>
    </div>
    
    <?php if (empty($testusers)): ?>
    <div class="alert alert-info">
        ⚠️ Nessun utente test trovato. <a href="index.php">Crea prima gli utenti test</a>.
    </div>
    <?php elseif (empty($missing_competencies)): ?>
    <div class="alert alert-success">
        ✅ Tutte le competenze testate hanno già autovalutazioni per gli utenti test.
    </div>
    <?php else: ?>
    
    <!-- Filtri -->
    <form method="get" action="">
        <div class="filters">
            <div class="filter-group">
                <label>🎯 Framework</label>
                <select name="fts_frameworkid" class="form-control" onchange="this.form.submit()">
                    <option value="0">Tutti i framework</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?>>
                        <?php echo $fw->shortname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📚 Corso</label>
                <select name="fts_courseid" class="form-control" onchange="this.form.submit()">
                    <option value="0">Tutti i corsi</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c->id; ?>" <?php echo $courseid == $c->id ? 'selected' : ''; ?>>
                        <?php echo $c->fullname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>👤 Utente Test</label>
                <select name="fts_userid" class="form-control" onchange="this.form.submit()">
                    <option value="0">Tutti gli utenti test</option>
                    <?php foreach ($testusers as $tu): ?>
                    <option value="<?php echo $tu->userid; ?>" <?php echo $userid == $tu->userid ? 'selected' : ''; ?>>
                        <?php echo $tu->username; ?> (<?php echo $tu->quiz_percentage; ?>%)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    
    <!-- Form Generazione -->
    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="fts_action" value="generate">
        <input type="hidden" name="fts_frameworkid" value="<?php echo $frameworkid; ?>">
        <input type="hidden" name="fts_courseid" value="<?php echo $courseid; ?>">
        <input type="hidden" name="fts_userid" value="<?php echo $userid; ?>">
        
        <div class="card">
            <div class="card-header">
                <h3>📋 Competenze Mancanti</h3>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(true)">
                        ☑️ Seleziona Tutto
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(false)">
                        ☐ Deseleziona
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                
                <?php foreach ($grouped as $fwname => $comps): ?>
                <div class="framework-group open" id="fw_<?php echo md5($fwname); ?>">
                    <div class="framework-header" onclick="toggleFramework('<?php echo md5($fwname); ?>')">
                        <h4>🎯 <?php echo $fwname; ?></h4>
                        <span class="count-badge"><?php echo count($comps); ?> mancanti</span>
                    </div>
                    <div class="framework-body">
                        <div class="competency-grid">
                            <?php foreach ($comps as $c): ?>
                            <label class="competency-item">
                                <input type="checkbox" name="fts_competencyids[]" value="<?php echo $c->id; ?>" class="comp-cb">
                                <div class="info">
                                    <div class="code"><?php echo $c->idnumber; ?></div>
                                    <div class="name"><?php echo $c->shortname; ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>
        
        <!-- Pannello Generazione -->
        <div class="generate-panel">
            <h4>⚙️ Opzioni Generazione</h4>
            <div class="generate-row">
                <div class="field">
                    <label>Livello Bloom:</label>
                    <select name="fts_bloomlevel" class="form-control">
                        <option value="0">Auto (basato su profilo)</option>
                        <option value="1">1 - Ricordare</option>
                        <option value="2">2 - Comprendere</option>
                        <option value="3" selected>3 - Applicare</option>
                        <option value="4">4 - Analizzare</option>
                        <option value="5">5 - Valutare</option>
                        <option value="6">6 - Creare</option>
                    </select>
                </div>
                <div class="field">
                    <label>Per utente:</label>
                    <select name="fts_userid" class="form-control">
                        <option value="0">Tutti gli utenti test</option>
                        <?php foreach ($testusers as $tu): ?>
                        <option value="<?php echo $tu->userid; ?>">
                            Solo <?php echo $tu->username; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-success">
                        ✅ Genera Autovalutazioni
                    </button>
                </div>
            </div>
            <p style="margin: 15px 0 0; font-size: 13px; color: #0c5460;">
                💡 <strong>Auto</strong>: Low30 → Bloom 4-6 (sovrastima), Medium65 → Bloom 3-4 (allineato), High95 → Bloom 2-4 (sottostima)
            </p>
        </div>
        
    </form>
    
    <?php endif; ?>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 25px;">
        <a href="fix.php" class="btn btn-secondary">← Centro Risoluzione</a>
        <a href="run.php" class="btn btn-secondary" style="margin-left: 10px;">▶️ Riesegui Test</a>
    </div>
    
</div>

<script>
function toggleFramework(id) {
    document.getElementById('fw_' + id).classList.toggle('open');
}

function selectAll(checked) {
    document.querySelectorAll('.comp-cb').forEach(function(cb) {
        cb.checked = checked;
    });
}
</script>

<?php
echo $OUTPUT->footer();
