<?php
/**
 * Pulizia Domande con Codici Errati
 *
 * Identifica e permette di correggere domande con:
 * 1. Codici con zero extra (CHIMFARM_6P_010 → CHIMFARM_6P_10)
 * 2. Codici vecchi (CHIMICA_* → CHIMFARM_*)
 * 3. Pattern di estrazione errato (CHIMFARM_BASE_Q01 invece di CHIMFARM_1C_02)
 * 4. Codici che non esistono nel framework
 *
 * @package    local_competencyxmlimport
 */

define('CLI_SCRIPT', false);
require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 9, PARAM_INT); // Default FTM

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/fix_codici_errati.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Pulizia Codici Errati');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Carica competenze valide dal framework
$valid_codes = [];
if ($frameworkid) {
    $competencies = $DB->get_records_sql("
        SELECT id, idnumber FROM {competency} WHERE competencyframeworkid = ?
    ", [$frameworkid]);
    foreach ($competencies as $c) {
        $valid_codes[strtoupper($c->idnumber)] = $c->id;
    }
}

// Funzione per analizzare una domanda e identificare problemi
function analyze_question($name, $valid_codes) {
    $problems = [];
    $suggestions = [];
    
    // Estrai tutti i codici dal nome
    $codes_found = [];
    
    // Pattern 1: Codici CHIMFARM
    if (preg_match_all('/\b(CHIMFARM_[A-Z0-9]+_[A-Z0-9]+)\b/i', $name, $matches)) {
        $codes_found = array_merge($codes_found, $matches[1]);
    }
    
    // Pattern 2: Codici CHIMICA (vecchi)
    if (preg_match_all('/\b(CHIMICA_[A-Z]+_[A-Z0-9]+)\b/i', $name, $matches)) {
        $codes_found = array_merge($codes_found, $matches[1]);
    }
    
    foreach ($codes_found as $code) {
        $code_upper = strtoupper($code);
        
        // Problema 1: Codice CHIMICA invece di CHIMFARM
        if (strpos($code_upper, 'CHIMICA_') === 0) {
            $problems[] = "Usa prefisso vecchio CHIMICA_";
            // Non possiamo suggerire automaticamente la conversione perché i codici sono diversi
            $suggestions[] = "Verificare manualmente la mappatura CHIMICA → CHIMFARM";
        }
        
        // Problema 2: Codice con zero extra (es. _010 invece di _10)
        if (preg_match('/_(0\d{2})$/', $code_upper, $m)) {
            $correct_num = ltrim($m[1], '0');
            $correct_code = preg_replace('/_(0\d{2})$/', '_' . str_pad($correct_num, 2, '0', STR_PAD_LEFT), $code_upper);
            
            if (isset($valid_codes[$correct_code])) {
                $problems[] = "Codice con zero extra: $code_upper";
                $suggestions[] = "Correggere in: $correct_code";
            } else {
                $problems[] = "Codice $code_upper non esiste (anche senza zero extra: $correct_code non trovato)";
            }
        }
        // Problema 3: Codice non esiste nel framework
        elseif (!isset($valid_codes[$code_upper]) && strpos($code_upper, 'CHIMFARM_') === 0) {
            // Potrebbe essere un codice Q (CHIMFARM_BASE_Q01)
            if (preg_match('/CHIMFARM_BASE_Q\d+/', $code_upper)) {
                $problems[] = "Pattern errato: estrae codice domanda invece di competenza";
                // Cerca il codice corretto dopo il " - "
                if (preg_match('/ - (CHIMFARM_[A-Z0-9]+_\d+)/', $name, $m2)) {
                    $suggestions[] = "Il codice corretto è: " . strtoupper($m2[1]);
                }
            } else {
                $problems[] = "Codice $code_upper non esiste nel framework";
            }
        }
    }
    
    // Nessun codice trovato
    if (empty($codes_found)) {
        $problems[] = "Nessun codice competenza trovato nel nome";
    }
    
    return [
        'codes' => $codes_found,
        'problems' => $problems,
        'suggestions' => $suggestions
    ];
}

// CSS
$css = '
<style>
.fix-page { max-width: 1400px; margin: 0 auto; padding: 20px; }
.fix-header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fix-header h2 { margin: 0 0 8px 0; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card .number { font-size: 28px; font-weight: bold; }
.stat-card .label { color: #666; margin-top: 5px; font-size: 13px; }
.problem-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.problem-table th, .problem-table td { padding: 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
.problem-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
.problem-table tr:hover { background: #fff8e1; }
.problem-table tr.ok { background: #e8f5e9; }
.problem-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin: 2px; }
.problem-badge.error { background: #ffebee; color: #c62828; }
.problem-badge.warning { background: #fff3e0; color: #e65100; }
.problem-badge.info { background: #e3f2fd; color: #1565c0; }
.suggestion { color: #2e7d32; font-size: 11px; font-style: italic; }
.btn { display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; margin-right: 10px; font-size: 14px; }
.btn-danger { background: #e74c3c; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.back-link { display: inline-block; margin-bottom: 20px; color: #e74c3c; }
.scroll-container { max-height: 600px; overflow-y: auto; }
.code { font-family: monospace; background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
.action-buttons { white-space: nowrap; }
.category-header { background: #ecf0f1; padding: 10px; font-weight: bold; border-bottom: 2px solid #bdc3c7; }
.log-item { padding: 8px 12px; border-left: 4px solid #ddd; margin-bottom: 5px; background: #f8f9fa; font-size: 13px; }
.log-item.success { border-color: #27ae60; background: #e8f5e9; }
.log-item.error { border-color: #e74c3c; background: #ffebee; }
.log-item.warning { border-color: #f39c12; background: #fff3e0; }
.checkbox-col { width: 30px; text-align: center; }
</style>';

echo $OUTPUT->header();
echo $css;

// Carica domande del corso
$questions = $DB->get_records_sql("
    SELECT q.id, q.name, qc.name as category_name, qc.id as category_id
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    ORDER BY qc.name, q.name
", [$context->id]);

// Analizza ogni domanda
$analysis = [];
$stats = [
    'total' => 0,
    'ok' => 0,
    'with_problems' => 0,
    'chimica_prefix' => 0,
    'zero_extra' => 0,
    'not_found' => 0,
    'no_code' => 0
];

foreach ($questions as $q) {
    $stats['total']++;
    $result = analyze_question($q->name, $valid_codes);
    $q->analysis = $result;
    
    if (empty($result['problems'])) {
        $q->status = 'ok';
        $stats['ok']++;
    } else {
        $q->status = 'problem';
        $stats['with_problems']++;
        
        // Conta tipi di problemi
        foreach ($result['problems'] as $p) {
            if (strpos($p, 'CHIMICA_') !== false) $stats['chimica_prefix']++;
            if (strpos($p, 'zero extra') !== false) $stats['zero_extra']++;
            if (strpos($p, 'non esiste') !== false) $stats['not_found']++;
            if (strpos($p, 'Nessun codice') !== false) $stats['no_code']++;
        }
    }
    
    $analysis[] = $q;
}

// === AZIONE: ELIMINA DOMANDE SELEZIONATE ===
if ($action === 'delete' && !empty($_POST['questions'])) {
    require_sesskey();
    
    $to_delete = $_POST['questions'];
    $deleted = 0;
    $errors = 0;
    
    echo '<div class="fix-page">';
    echo '<a href="fix_codici_errati.php?courseid='.$courseid.'" class="back-link">← Torna all\'analisi</a>';
    echo '<div class="fix-header"><h2>🗑️ Eliminazione Domande</h2></div>';
    echo '<div class="panel"><h3>📋 Log Operazioni</h3><div class="scroll-container">';
    
    foreach ($to_delete as $qid) {
        $qid = intval($qid);
        $q = $DB->get_record('question', ['id' => $qid]);
        
        if ($q) {
            try {
                // Elimina associazioni competenze
                $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $qid]);
                
                // Elimina la domanda (Moodle gestisce le versioni)
                question_delete_question($qid);
                
                echo '<div class="log-item success">✅ Eliminata: ' . htmlspecialchars(substr($q->name, 0, 60)) . '</div>';
                $deleted++;
            } catch (Exception $e) {
                echo '<div class="log-item error">❌ Errore: ' . htmlspecialchars(substr($q->name, 0, 40)) . ' - ' . $e->getMessage() . '</div>';
                $errors++;
            }
        }
    }
    
    echo '</div></div>';
    
    echo '<div class="panel">';
    echo '<h3>🎉 Operazione Completata!</h3>';
    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number" style="color: #27ae60;">' . $deleted . '</div><div class="label">Domande Eliminate</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #e74c3c;">' . $errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_codici_errati.php?courseid='.$courseid.'" class="btn btn-secondary">🔄 Torna all\'Analisi</a>';
    echo '</div></div>';
    
    echo $OUTPUT->footer();
    exit;
}

// === AZIONE: RINOMINA DOMANDE ===
if ($action === 'rename' && !empty($_POST['renames'])) {
    require_sesskey();
    
    $renames = $_POST['renames'];
    $renamed = 0;
    $errors = 0;
    
    echo '<div class="fix-page">';
    echo '<a href="fix_codici_errati.php?courseid='.$courseid.'" class="back-link">← Torna all\'analisi</a>';
    echo '<div class="fix-header"><h2>✏️ Rinomina Domande</h2></div>';
    echo '<div class="panel"><h3>📋 Log Operazioni</h3><div class="scroll-container">';
    
    foreach ($renames as $qid => $new_name) {
        $qid = intval($qid);
        $new_name = trim($new_name);
        
        if (empty($new_name)) continue;
        
        $q = $DB->get_record('question', ['id' => $qid]);
        if ($q && $q->name !== $new_name) {
            try {
                $DB->set_field('question', 'name', $new_name, ['id' => $qid]);
                echo '<div class="log-item success">✅ ' . htmlspecialchars(substr($q->name, 0, 30)) . ' → ' . htmlspecialchars(substr($new_name, 0, 30)) . '</div>';
                $renamed++;
            } catch (Exception $e) {
                echo '<div class="log-item error">❌ Errore: ' . $e->getMessage() . '</div>';
                $errors++;
            }
        }
    }
    
    echo '</div></div>';
    
    echo '<div class="panel">';
    echo '<h3>🎉 Operazione Completata!</h3>';
    echo '<p>Rinominate <strong>' . $renamed . '</strong> domande.</p>';
    echo '<a href="fix_codici_errati.php?courseid='.$courseid.'" class="btn btn-secondary">🔄 Torna all\'Analisi</a>';
    echo '<a href="fix_competenze_da_nome.php?courseid='.$courseid.'" class="btn btn-success">🔗 Riassegna Competenze</a>';
    echo '</div></div>';
    
    echo $OUTPUT->footer();
    exit;
}

// === PAGINA PRINCIPALE ===
?>
<div class="fix-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Dashboard</a>

    <div class="fix-header">
        <h2>🧹 Pulizia Domande con Codici Errati</h2>
        <p>Identifica e corregge domande con codici competenza problematici</p>
    </div>

    <!-- Statistiche -->
    <div class="panel">
        <h3>📊 Riepilogo Analisi</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo $stats['ok']; ?></div>
                <div class="label">OK ✓</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #e74c3c;"><?php echo $stats['with_problems']; ?></div>
                <div class="label">Con Problemi</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #e65100;"><?php echo $stats['chimica_prefix']; ?></div>
                <div class="label">Prefisso CHIMICA_</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #f39c12;"><?php echo $stats['zero_extra']; ?></div>
                <div class="label">Zero Extra</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #9c27b0;"><?php echo $stats['not_found']; ?></div>
                <div class="label">Non nel Framework</div>
            </div>
        </div>
    </div>

    <!-- Legenda -->
    <div class="panel">
        <h3>📖 Legenda Problemi</h3>
        <p>
            <span class="problem-badge error">CHIMICA_</span> Il codice usa il vecchio prefisso CHIMICA invece di CHIMFARM<br>
            <span class="problem-badge warning">Zero Extra</span> Il codice ha uno zero in più (es. _010 invece di _10)<br>
            <span class="problem-badge info">Non Trovato</span> Il codice non esiste nel framework competenze<br>
            <span class="problem-badge error">Nessun Codice</span> Nessun codice competenza trovato nel nome
        </p>
    </div>

    <!-- Tabella domande con problemi -->
    <div class="panel">
        <h3>🔍 Domande con Problemi (<?php echo $stats['with_problems']; ?>)</h3>
        
        <?php if ($stats['with_problems'] > 0): ?>
        <form method="post" action="fix_codici_errati.php?courseid=<?php echo $courseid; ?>&action=delete">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            
            <div style="margin-bottom: 15px;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare le domande selezionate?');">
                    🗑️ Elimina Selezionate
                </button>
                <button type="button" class="btn btn-secondary" onclick="toggleAll(this);">
                    ☑️ Seleziona/Deseleziona Tutto
                </button>
            </div>
            
            <div class="scroll-container">
                <table class="problem-table">
                    <thead>
                        <tr>
                            <th class="checkbox-col"><input type="checkbox" id="select-all" onclick="toggleAll(this);"></th>
                            <th style="width: 40%;">Nome Domanda</th>
                            <th>Codici Trovati</th>
                            <th>Problemi</th>
                            <th>Suggerimenti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_category = '';
                        foreach ($analysis as $q): 
                            if ($q->status !== 'problem') continue;
                            
                            if ($q->category_name !== $current_category):
                                $current_category = $q->category_name;
                        ?>
                        <tr>
                            <td colspan="5" class="category-header">📁 <?php echo htmlspecialchars($current_category); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="checkbox-col">
                                <input type="checkbox" name="questions[]" value="<?php echo $q->id; ?>">
                            </td>
                            <td>
                                <span class="code"><?php echo htmlspecialchars($q->name); ?></span>
                            </td>
                            <td>
                                <?php foreach ($q->analysis['codes'] as $code): ?>
                                    <span class="code"><?php echo htmlspecialchars($code); ?></span><br>
                                <?php endforeach; ?>
                                <?php if (empty($q->analysis['codes'])): ?>
                                    <em style="color: #999;">Nessuno</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($q->analysis['problems'] as $p): 
                                    $badge_class = 'error';
                                    if (strpos($p, 'zero') !== false) $badge_class = 'warning';
                                    if (strpos($p, 'non esiste') !== false) $badge_class = 'info';
                                ?>
                                    <span class="problem-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($p); ?></span><br>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php foreach ($q->analysis['suggestions'] as $s): ?>
                                    <span class="suggestion">💡 <?php echo htmlspecialchars($s); ?></span><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #27ae60;">
            <h2>✅ Nessun problema trovato!</h2>
            <p>Tutte le domande hanno codici competenza validi.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Azioni rapide -->
    <div class="panel">
        <h3>⚡ Azioni Rapide</h3>
        <a href="fix_competenze_da_nome.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">🔗 Riassegna Competenze</a>
        <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">📊 Audit Competenze</a>
        <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🏠 Dashboard</a>
    </div>
</div>

<script>
function toggleAll(el) {
    var checkboxes = document.querySelectorAll('input[name="questions[]"]');
    var selectAll = document.getElementById('select-all');
    var newState = !selectAll.checked;
    
    selectAll.checked = newState;
    checkboxes.forEach(function(cb) {
        cb.checked = newState;
    });
}
</script>

<?php
echo $OUTPUT->footer();
