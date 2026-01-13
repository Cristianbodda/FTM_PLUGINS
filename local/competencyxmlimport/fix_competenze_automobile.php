<?php
/**
 * Fix Competenze Domande Automobile
 * 
 * Assegna le competenze alle domande esistenti basandosi sul codice nel nome
 * Pattern: Q01 - AUTOMOBILE_MAu_J1 → estrae AUTOMOBILE_MAu_J1
 * 
 * @package    local_competencyxmlimport
 */

define('CLI_SCRIPT', false);
require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/fix_competenze_automobile.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Competenze Automobile');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// CSS
$css = '
<style>
.fix-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.fix-header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fix-header h2 { margin: 0 0 8px 0; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: bold; color: #e74c3c; }
.stat-card .label { color: #666; margin-top: 5px; }
.question-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.question-table th, .question-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.question-table th { background: #f8f9fa; position: sticky; top: 0; }
.question-table tr:hover { background: #f8f9fa; }
.question-table tr.has-code { background: #e8f5e9; }
.question-table tr.no-code { background: #ffebee; }
.code-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: monospace; }
.code-found { background: #27ae60; color: white; }
.code-missing { background: #e74c3c; color: white; }
.code-exists { background: #3498db; color: white; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; margin-right: 10px; }
.btn-danger { background: #e74c3c; color: white; }
.btn-danger:hover { background: #c0392b; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.back-link { display: inline-block; margin-bottom: 20px; color: #e74c3c; }
.log-item { padding: 8px 12px; border-left: 4px solid #ddd; margin-bottom: 5px; background: #f8f9fa; font-size: 13px; }
.log-item.success { border-color: #27ae60; }
.log-item.error { border-color: #e74c3c; background: #fdeaea; }
.log-item.info { border-color: #3498db; }
.scroll-table { max-height: 500px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; }
select.form-control { padding: 10px; border-radius: 6px; border: 1px solid #ddd; min-width: 300px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
</style>';

echo $OUTPUT->header();
echo $css;

/**
 * Estrae il codice competenza AUTOMOBILE dal nome della domanda
 * Pattern supportati:
 * - Q01 - AUTOMOBILE_MAu_J1
 * - AUTOMOBILE_MR_B2 - Descrizione
 * - <b>AUTOMOBILE_MR_A1</b> nel testo
 */
function extract_automobile_code($name, $questiontext = '') {
    // Prima cerca nel nome
    if (preg_match('/\b(AUTOMOBILE_[A-Za-z]+_[A-Z0-9]+)\b/i', $name, $m)) {
        return strtoupper($m[1]);
    }
    // Poi nel testo della domanda
    if (preg_match('/\b(AUTOMOBILE_[A-Za-z]+_[A-Z0-9]+)\b/i', $questiontext, $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

// Carica frameworks disponibili
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

// Se non specificato, cerca framework con competenze AUTOMOBILE
if (!$frameworkid) {
    foreach ($frameworks as $fw) {
        $has_auto = $DB->record_exists_sql("
            SELECT 1 FROM {competency} 
            WHERE competencyframeworkid = ? AND idnumber LIKE 'AUTOMOBILE_%'
        ", [$fw->id]);
        if ($has_auto) {
            $frameworkid = $fw->id;
            break;
        }
    }
}

// Carica competenze AUTOMOBILE dal framework selezionato
$competencies = [];
$comp_lookup = [];
if ($frameworkid) {
    $competencies = $DB->get_records_sql("
        SELECT id, idnumber, shortname, description 
        FROM {competency} 
        WHERE competencyframeworkid = ? AND idnumber LIKE 'AUTOMOBILE_%'
        ORDER BY idnumber
    ", [$frameworkid]);
    foreach ($competencies as $c) {
        $comp_lookup[strtoupper($c->idnumber)] = $c->id;
    }
}

// Carica tutte le domande del corso
$questions = $DB->get_records_sql("
    SELECT q.id, q.name, q.questiontext, qbe.id as qbeid, qc.name as category_name,
           (SELECT COUNT(*) FROM {qbank_competenciesbyquestion} qbc WHERE qbc.questionid = q.id) as comp_count
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    ORDER BY qc.name, q.name
", [$context->id]);

// Analizza le domande
$analysis = [];
$stats = [
    'total' => 0,
    'with_code' => 0,
    'code_found_in_framework' => 0,
    'already_assigned' => 0,
    'to_assign' => 0
];

foreach ($questions as $q) {
    $stats['total']++;
    
    $code = extract_automobile_code($q->name, $q->questiontext);
    $q->extracted_code = $code;
    $q->competency_id = null;
    $q->status = 'no_code';
    
    if ($code) {
        $stats['with_code']++;
        
        if (isset($comp_lookup[$code])) {
            $stats['code_found_in_framework']++;
            $q->competency_id = $comp_lookup[$code];
            
            if ($q->comp_count > 0) {
                $stats['already_assigned']++;
                $q->status = 'already_assigned';
            } else {
                $stats['to_assign']++;
                $q->status = 'to_assign';
            }
        } else {
            $q->status = 'code_not_in_framework';
        }
    }
    
    $analysis[] = $q;
}

// === AZIONE: ESEGUI FIX ===
if ($action === 'execute' && $frameworkid) {
    require_sesskey();
    
    echo '<div class="fix-page">';
    echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Dashboard</a>';
    echo '<div class="fix-header"><h2>🔧 Fix Competenze in Corso...</h2></div>';
    
    $assigned = 0;
    $errors = 0;
    
    echo '<div class="panel"><h3>📋 Log Operazioni</h3>';
    
    foreach ($analysis as $q) {
        if ($q->status === 'to_assign' && $q->competency_id) {
            // Verifica che non esista già
            $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                'questionid' => $q->id,
                'competencyid' => $q->competency_id
            ]);
            
            if (!$exists) {
                try {
                    $rec = new stdClass();
                    $rec->questionid = $q->id;
                    $rec->competencyid = $q->competency_id;
                    $rec->difficultylevel = 1; // Default level
                    $DB->insert_record('qbank_competenciesbyquestion', $rec);
                    
                    echo '<div class="log-item success">✅ ' . htmlspecialchars($q->name) . ' → ' . $q->extracted_code . '</div>';
                    $assigned++;
                } catch (Exception $e) {
                    echo '<div class="log-item error">❌ ' . htmlspecialchars($q->name) . ' - Errore: ' . $e->getMessage() . '</div>';
                    $errors++;
                }
            }
        }
    }
    
    echo '</div>';
    
    // Riepilogo
    echo '<div class="panel">';
    echo '<h3>🎉 Fix Completato!</h3>';
    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number">' . $assigned . '</div><div class="label">Competenze Assegnate</div></div>';
    echo '<div class="stat-card"><div class="number">' . $errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_competenze_automobile.php?courseid='.$courseid.'&frameworkid='.$frameworkid.'" class="btn btn-secondary">🔄 Verifica Risultato</a>';
    echo '<a href="audit_competenze.php?courseid='.$courseid.'" class="btn btn-danger">📊 Vai all\'Audit</a>';
    echo '</div>';
    
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// === PAGINA PREVIEW ===
?>
<div class="fix-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Dashboard</a>
    
    <div class="fix-header">
        <h2>🔧 Fix Competenze Domande Automobile</h2>
        <p>Assegna automaticamente le competenze alle domande basandosi sul codice nel nome</p>
    </div>
    
    <!-- Selezione Framework -->
    <div class="panel">
        <h3>⚙️ Configurazione</h3>
        <form method="get" action="">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <div class="form-group">
                <label>Framework Competenze:</label>
                <select name="frameworkid" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Seleziona Framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo ($frameworkid == $fw->id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($fw->shortname); ?>
                        <?php 
                        $auto_count = $DB->count_records_sql("SELECT COUNT(*) FROM {competency} WHERE competencyframeworkid = ? AND idnumber LIKE 'AUTOMOBILE_%'", [$fw->id]);
                        if ($auto_count > 0) echo " ($auto_count competenze AUTOMOBILE)";
                        ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <?php if ($frameworkid && count($comp_lookup) > 0): ?>
        <p style="color: #27ae60;">✅ Caricate <strong><?php echo count($comp_lookup); ?></strong> competenze AUTOMOBILE dal framework</p>
        <?php elseif ($frameworkid): ?>
        <p style="color: #e74c3c;">⚠️ Nessuna competenza AUTOMOBILE trovata nel framework selezionato</p>
        <?php endif; ?>
    </div>
    
    <!-- Statistiche -->
    <div class="panel">
        <h3>📊 Analisi Domande</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['with_code']; ?></div>
                <div class="label">Con Codice AUTOMOBILE</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo $stats['to_assign']; ?></div>
                <div class="label">Da Assegnare</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?php echo $stats['already_assigned']; ?></div>
                <div class="label">Già Assegnate</div>
            </div>
        </div>
    </div>
    
    <!-- Tabella Domande -->
    <div class="panel">
        <h3>📋 Dettaglio Domande (<?php echo count($analysis); ?>)</h3>
        <div class="scroll-table">
            <table class="question-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nome Domanda</th>
                        <th>Categoria</th>
                        <th style="width: 180px;">Codice Estratto</th>
                        <th style="width: 120px;">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 0;
                    foreach ($analysis as $q): 
                        $i++;
                        $row_class = '';
                        if ($q->status === 'to_assign') $row_class = 'has-code';
                        elseif ($q->status === 'no_code') $row_class = 'no-code';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo $i; ?></td>
                        <td><?php echo htmlspecialchars(substr($q->name, 0, 80)); ?></td>
                        <td><?php echo htmlspecialchars($q->category_name); ?></td>
                        <td>
                            <?php if ($q->extracted_code): ?>
                                <span class="code-badge code-found"><?php echo $q->extracted_code; ?></span>
                            <?php else: ?>
                                <span class="code-badge code-missing">Non rilevato</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            switch ($q->status) {
                                case 'to_assign':
                                    echo '<span style="color: #27ae60;">✅ Da assegnare</span>';
                                    break;
                                case 'already_assigned':
                                    echo '<span style="color: #3498db;">🔗 Già assegnata</span>';
                                    break;
                                case 'code_not_in_framework':
                                    echo '<span style="color: #f39c12;">⚠️ Codice non trovato</span>';
                                    break;
                                default:
                                    echo '<span style="color: #999;">—</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Azioni -->
    <?php if ($stats['to_assign'] > 0 && $frameworkid): ?>
    <div class="panel" style="background: #e8f5e9; border-color: #27ae60;">
        <h3 style="color: #27ae60;">🚀 Pronto per il Fix!</h3>
        <p>Verranno assegnate <strong><?php echo $stats['to_assign']; ?></strong> competenze alle domande.</p>
        <form method="post" action="fix_competenze_automobile.php?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-success">✅ Esegui Fix Competenze</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
    </div>
    <?php elseif ($stats['to_assign'] == 0 && $frameworkid): ?>
    <div class="panel" style="background: #e3f2fd; border-color: #3498db;">
        <h3 style="color: #3498db;">ℹ️ Nessuna azione necessaria</h3>
        <p>Tutte le domande con codice AUTOMOBILE sono già assegnate alle rispettive competenze.</p>
        <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-danger">📊 Vai all'Audit</a>
    </div>
    <?php endif; ?>
</div>
<?php

echo $OUTPUT->footer();
