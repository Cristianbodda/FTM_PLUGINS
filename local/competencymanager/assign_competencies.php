<?php
/**
 * Assegnazione Automatica Competenze alle Domande
 * Legge il codice competenza dal nome della domanda e lo assegna
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$difficultylevel = optional_param('difficultylevel', 1, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/assign_competencies.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Assegna Competenze');
$PAGE->set_heading('Assegnazione Automatica Competenze');

// Funzione per estrarre il codice competenza dal nome
function extract_competency_code($name) {
    // Pattern: SETTORE_AREA_NUMERO (es. MECCANICA_LMC_01, AUTOMOBILE_MR_A1)
    if (preg_match('/([A-Z]+_[A-Z]+_[A-Z0-9]+)/i', $name, $matches)) {
        return strtoupper($matches[1]);
    }
    // Pattern alternativo: AREA_NUMERO
    if (preg_match('/([A-Z]{2,}_\d+)/i', $name, $matches)) {
        return strtoupper($matches[1]);
    }
    return null;
}

// Carica i framework disponibili
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

echo $OUTPUT->header();
?>

<style>
.assign-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
.assign-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.assign-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.assign-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 12px; }
th { background: #34495e; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
.stat-box { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; }
.stat-box .number { font-size: 32px; font-weight: 700; }
.stat-box .label { font-size: 12px; color: #666; margin-top: 5px; }
.stat-box.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.stat-box.success .number { color: #155724; }
.stat-box.danger { background: linear-gradient(135deg, #f8d7da, #f5c6cb); }
.stat-box.danger .number { color: #721c24; }
.stat-box.info { background: linear-gradient(135deg, #cce5ff, #b8daff); }
.stat-box.info .number { color: #004085; }
.stat-box.warning { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.stat-box.warning .number { color: #856404; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
.form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
.btn-secondary { background: #6c757d; color: white; }
.btn-primary { background: #007bff; color: white; }
.result-box { padding: 20px; border-radius: 10px; margin: 20px 0; }
.result-box.success { background: #d4edda; border: 1px solid #c3e6cb; }
.result-box.error { background: #f8d7da; border: 1px solid #f5c6cb; }
</style>

<div class="assign-container">

<div class="assign-header">
    <h2>🎯 Assegnazione Automatica Competenze</h2>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
</div>

<?php if ($action === 'assign' && $frameworkid > 0): ?>
    <!-- ESECUZIONE ASSEGNAZIONE -->
    <?php
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    
    // Prendi TUTTE le domande del corso (non solo le ultime 50)
    $allQuestions = $DB->get_records_sql("
        SELECT DISTINCT q.id, q.name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        AND q.parent = 0
    ", [$context->id]);
    
    // Prendi tutte le competenze del framework
    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber, shortname');
    $compByCode = [];
    foreach ($competencies as $c) {
        $compByCode[$c->idnumber] = $c;
    }
    
    $assigned = 0;
    $alreadyAssigned = 0;
    $notFound = 0;
    $noCode = 0;
    $errors = [];
    
    foreach ($allQuestions as $question) {
        // Estrai codice dal nome
        $code = extract_competency_code($question->name);
        
        if (!$code) {
            $noCode++;
            continue;
        }
        
        // Cerca la competenza
        if (!isset($compByCode[$code])) {
            $notFound++;
            $errors[] = "Codice '$code' non trovato nel framework (domanda: {$question->name})";
            continue;
        }
        
        $competency = $compByCode[$code];
        
        // Verifica se già assegnata
        $exists = $DB->record_exists('qbank_competenciesbyquestion', [
            'questionid' => $question->id,
            'competencyid' => $competency->id
        ]);
        
        if ($exists) {
            $alreadyAssigned++;
            continue;
        }
        
        // Assegna la competenza
        $record = new stdClass();
        $record->questionid = $question->id;
        $record->competencyid = $competency->id;
        $record->difficultylevel = $difficultylevel;
        
        try {
            $DB->insert_record('qbank_competenciesbyquestion', $record);
            $assigned++;
        } catch (Exception $e) {
            $errors[] = "Errore assegnazione domanda {$question->id}: " . $e->getMessage();
        }
    }
    ?>
    
    <div class="assign-card">
        <h3>✅ Assegnazione Completata</h3>
        
        <div class="stats-grid">
            <div class="stat-box success">
                <div class="number"><?php echo $assigned; ?></div>
                <div class="label">✅ Nuove Assegnazioni</div>
            </div>
            <div class="stat-box info">
                <div class="number"><?php echo $alreadyAssigned; ?></div>
                <div class="label">ℹ️ Già Assegnate</div>
            </div>
            <div class="stat-box warning">
                <div class="number"><?php echo $notFound; ?></div>
                <div class="label">⚠️ Codice Non Trovato</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $noCode; ?></div>
                <div class="label">📝 Senza Codice</div>
            </div>
        </div>
        
        <div class="result-box success">
            <h4>🎉 Risultato</h4>
            <p><strong><?php echo $assigned; ?></strong> competenze sono state assegnate alle domande.</p>
            <p>Framework utilizzato: <strong><?php echo format_string($framework->shortname); ?></strong></p>
            <p>Livello difficoltà: <strong><?php echo str_repeat('⭐', $difficultylevel); ?></strong></p>
            
            <?php if ($assigned > 0): ?>
            <p style="margin-top: 15px; padding: 10px; background: #c3e6cb; border-radius: 5px;">
                <strong>💡 I quiz già completati ora mostreranno le competenze nel report!</strong><br>
                Non è necessario rifare i quiz - i dati storici verranno visualizzati correttamente.
            </p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($errors) && count($errors) <= 20): ?>
        <details style="margin-top: 15px;">
            <summary style="cursor: pointer; color: #856404;">⚠️ Mostra dettagli codici non trovati (<?php echo count($errors); ?>)</summary>
            <ul style="margin-top: 10px; font-size: 12px; max-height: 200px; overflow-y: auto;">
                <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    
    <p>
        <a href="debug_competencies.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🔍 Verifica Risultato</a>
        <a href="question_check.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">📋 Verifica Domande</a>
        <a href="reports.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">📊 Vai ai Report</a>
    </p>

<?php else: ?>
    <!-- FORM CONFIGURAZIONE -->
    <?php
    // Analisi domande senza competenza
    $allQuestions = $DB->get_records_sql("
        SELECT q.id, q.name,
               (SELECT COUNT(*) FROM {qbank_competenciesbyquestion} qcbq WHERE qcbq.questionid = q.id) as has_comp
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        AND q.parent = 0
    ", [$context->id]);
    
    $total = count($allQuestions);
    $withComp = 0;
    $withoutComp = 0;
    $withCode = 0;
    
    foreach ($allQuestions as $q) {
        if ($q->has_comp > 0) {
            $withComp++;
        } else {
            $withoutComp++;
            if (extract_competency_code($q->name)) {
                $withCode++;
            }
        }
    }
    ?>
    
    <div class="assign-card">
        <h3>📊 Analisi Domande del Corso</h3>
        
        <div class="stats-grid">
            <div class="stat-box info">
                <div class="number"><?php echo $total; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-box success">
                <div class="number"><?php echo $withComp; ?></div>
                <div class="label">✅ Con Competenza</div>
            </div>
            <div class="stat-box danger">
                <div class="number"><?php echo $withoutComp; ?></div>
                <div class="label">❌ Senza Competenza</div>
            </div>
            <div class="stat-box warning">
                <div class="number"><?php echo $withCode; ?></div>
                <div class="label">🔄 Assegnabili</div>
            </div>
        </div>
        
        <?php if ($withoutComp > 0 && $withCode > 0): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>💡 Nota:</strong> <?php echo $withCode; ?> domande hanno un codice competenza nel nome e possono essere assegnate automaticamente.
        </div>
        <?php endif; ?>
    </div>
    
    <div class="assign-card">
        <h3>⚙️ Configura Assegnazione</h3>
        
        <form method="get" action="">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="action" value="assign">
            
            <div class="form-group">
                <label>📚 Framework di Competenze *</label>
                <select name="frameworkid" required>
                    <option value="">-- Seleziona un framework --</option>
                    <?php foreach ($frameworks as $fw): 
                        $compCount = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
                    ?>
                    <option value="<?php echo $fw->id; ?>"><?php echo format_string($fw->shortname); ?> (<?php echo $compCount; ?> competenze)</option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666;">Il framework che contiene le competenze da assegnare (es. MECCANICA_LMC_01)</small>
            </div>
            
            <div class="form-group">
                <label>⭐ Livello Difficoltà</label>
                <select name="difficultylevel">
                    <option value="1">⭐ Base</option>
                    <option value="2">⭐⭐ Intermedio</option>
                    <option value="3">⭐⭐⭐ Avanzato</option>
                </select>
                <small style="color: #666;">Il livello di difficoltà da assegnare alle competenze</small>
            </div>
            
            <div style="background: #e8f4f8; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <strong>ℹ️ Come funziona:</strong>
                <ol style="margin: 10px 0 0 20px;">
                    <li>Lo script legge il nome di ogni domanda (es. "Q01 - MECCANICA_LMC_01")</li>
                    <li>Estrae il codice competenza (es. "MECCANICA_LMC_01")</li>
                    <li>Cerca la competenza nel framework selezionato</li>
                    <li>Se trova la competenza, la assegna alla domanda</li>
                </ol>
            </div>
            
            <p>
                <button type="submit" class="btn btn-success">🚀 Avvia Assegnazione</button>
                <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
            </p>
        </form>
    </div>
    
    <!-- Anteprima domande senza competenza -->
    <div class="assign-card">
        <h3>📋 Anteprima Domande Senza Competenza (prime 30)</h3>
        
        <table>
            <tr>
                <th>ID</th>
                <th>Nome Domanda</th>
                <th>Codice Estratto</th>
                <th>Assegnabile?</th>
            </tr>
            <?php 
            $count = 0;
            foreach ($allQuestions as $q): 
                if ($q->has_comp > 0) continue;
                if ($count >= 30) break;
                $code = extract_competency_code($q->name);
            ?>
            <tr>
                <td><?php echo $q->id; ?></td>
                <td><?php echo format_string(substr($q->name, 0, 50)); ?></td>
                <td><?php echo $code ?: '<em style="color:#999">Nessun codice</em>'; ?></td>
                <td><?php echo $code ? '<span class="status-ok">✅ Sì</span>' : '<span class="status-error">❌ No</span>'; ?></td>
            </tr>
            <?php 
                $count++;
            endforeach; 
            ?>
        </table>
    </div>
    
<?php endif; ?>

<p style="margin-top: 20px;">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🏠 Dashboard</a>
</p>

</div>

<?php
echo $OUTPUT->footer();
