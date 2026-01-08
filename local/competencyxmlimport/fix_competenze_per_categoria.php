<?php
/**
 * Fix Competenze per Categoria
 * 
 * Assegna le competenze alle domande basandosi su:
 * 1. La CATEGORIA della domanda (Alta Tensione HV, Climatizzazione HVAC, ecc.)
 * 2. La POSIZIONE ordinale nella categoria (domanda 1, 2, 3, ecc.)
 * 
 * Mapping estratto direttamente dai file XML originali
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
$PAGE->set_url('/local/competencyxmlimport/fix_competenze_per_categoria.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Competenze per Categoria');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================
// MAPPING CATEGORIA -> COMPETENZE (per ordine)
// Estratto dai file XML originali
// ============================================
$category_mappings = [
    'Alta Tensione HV' => [
        'AUTOMOBILE_MAu_J1', 'AUTOMOBILE_MAu_J1', 'AUTOMOBILE_MAu_J3', 'AUTOMOBILE_MAu_J4', 'AUTOMOBILE_MAu_J4',
        'AUTOMOBILE_MAu_J3', 'AUTOMOBILE_MAu_J3', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J6',
        'AUTOMOBILE_MAu_J6', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MR_J7',  'AUTOMOBILE_MAu_J1',
        'AUTOMOBILE_MAu_J1', 'AUTOMOBILE_MAu_J3', 'AUTOMOBILE_MAu_J3', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J4',
        'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J2', 'AUTOMOBILE_MAu_J4', 'AUTOMOBILE_MAu_J1', 'AUTOMOBILE_MAu_J3'
    ],
    'Climatizzazione HVAC' => [
        'AUTOMOBILE_MR_I1', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I2', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3',
        'AUTOMOBILE_MR_I6', 'AUTOMOBILE_MR_I5', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3',
        'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I6', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3',
        'AUTOMOBILE_MR_I2', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I4',
        'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I3', 'AUTOMOBILE_MR_I6', 'AUTOMOBILE_MR_I6', 'AUTOMOBILE_MR_I3'
    ],
    'Elettronica Veicolo' => [
        'AUTOMOBILE_MR_G3',  'AUTOMOBILE_MAu_F5', 'AUTOMOBILE_MR_G7',  'AUTOMOBILE_MR_G3',  'AUTOMOBILE_MAu_G2',
        'AUTOMOBILE_MAu_G4', 'AUTOMOBILE_MR_K3',  'AUTOMOBILE_MAu_G5', 'AUTOMOBILE_MR_G3',  'AUTOMOBILE_MR_G3',
        'AUTOMOBILE_MR_G7',  'AUTOMOBILE_MAu_G9', 'AUTOMOBILE_MAu_G4', 'AUTOMOBILE_MR_G3',  'AUTOMOBILE_MAu_G2',
        'AUTOMOBILE_MAu_G4', 'AUTOMOBILE_MR_K3',  'AUTOMOBILE_MR_G3',  'AUTOMOBILE_MR_G10', 'AUTOMOBILE_MR_G1',
        'AUTOMOBILE_MR_G7',  'AUTOMOBILE_MAu_G2', 'AUTOMOBILE_MAu_G2', 'AUTOMOBILE_MR_H3',  'AUTOMOBILE_MAu_G6'
    ],
    'Meccanica e Riparazioni' => [
        'AUTOMOBILE_MR_E5', 'AUTOMOBILE_MR_E5', 'AUTOMOBILE_MR_E2', 'AUTOMOBILE_MR_E2', 'AUTOMOBILE_MR_E2',
        'AUTOMOBILE_MR_E3', 'AUTOMOBILE_MR_E4', 'AUTOMOBILE_MR_E1', 'AUTOMOBILE_MR_F3', 'AUTOMOBILE_MR_F3',
        'AUTOMOBILE_MR_F4', 'AUTOMOBILE_MR_F4', 'AUTOMOBILE_MR_F5', 'AUTOMOBILE_MR_F5', 'AUTOMOBILE_MR_F6',
        'AUTOMOBILE_MR_F6', 'AUTOMOBILE_MR_F7', 'AUTOMOBILE_MR_F1', 'AUTOMOBILE_MR_E4', 'AUTOMOBILE_MR_F3',
        'AUTOMOBILE_MR_E4', 'AUTOMOBILE_MR_F3', 'AUTOMOBILE_MR_F1', 'AUTOMOBILE_MR_F2', 'AUTOMOBILE_MR_F3'
    ],
    'Motore e Powertrain' => [
        'AUTOMOBILE_MR_B9',  'AUTOMOBILE_MR_B3',  'AUTOMOBILE_MR_B9',  'AUTOMOBILE_MR_B4',  'AUTOMOBILE_MR_B10',
        'AUTOMOBILE_MR_B5',  'AUTOMOBILE_MR_B3',  'AUTOMOBILE_MR_B7',  'AUTOMOBILE_MR_B9',  'AUTOMOBILE_MR_B9',
        'AUTOMOBILE_MR_B5',  'AUTOMOBILE_MR_B3',  'AUTOMOBILE_MR_B5',  'AUTOMOBILE_MR_B4',  'AUTOMOBILE_MR_B9',
        'AUTOMOBILE_MR_B6',  'AUTOMOBILE_MR_B3',  'AUTOMOBILE_MR_B5',  'AUTOMOBILE_MR_B10', 'AUTOMOBILE_MR_B9',
        'AUTOMOBILE_MR_B11', 'AUTOMOBILE_MR_B6',  'AUTOMOBILE_MR_B2',  'AUTOMOBILE_MR_C3',  'AUTOMOBILE_MR_B9'
    ],
    'Sistemi ADAS' => [
        'AUTOMOBILE_MAu_H2', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H4',
        'AUTOMOBILE_MR_H3',  'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H4', 'AUTOMOBILE_MAu_H2',
        'AUTOMOBILE_MAu_G4', 'AUTOMOBILE_MAu_H4', 'AUTOMOBILE_MAu_H2', 'AUTOMOBILE_MAu_H2', 'AUTOMOBILE_MAu_H1',
        'AUTOMOBILE_MAu_G4', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H4', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MAu_H2',
        'AUTOMOBILE_MAu_H4', 'AUTOMOBILE_MAu_H1', 'AUTOMOBILE_MR_H3',  'AUTOMOBILE_MAu_H4', 'AUTOMOBILE_MAu_H2'
    ]
];

// CSS
$css = '
<style>
.fix-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.fix-header { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fix-header h2 { margin: 0 0 8px 0; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card .number { font-size: 28px; font-weight: bold; color: #9b59b6; }
.stat-card .label { color: #666; margin-top: 5px; font-size: 13px; }
.category-section { margin-bottom: 25px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
.category-header { background: #f8f9fa; padding: 15px 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.category-header .count { background: #9b59b6; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
.question-list { max-height: 300px; overflow-y: auto; }
.question-item { padding: 10px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
.question-item:last-child { border-bottom: none; }
.question-item:hover { background: #f8f9fa; }
.code-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: monospace; }
.code-new { background: #27ae60; color: white; }
.code-exists { background: #3498db; color: white; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; margin-right: 10px; }
.btn-primary { background: #9b59b6; color: white; }
.btn-primary:hover { background: #8e44ad; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.back-link { display: inline-block; margin-bottom: 20px; color: #9b59b6; }
.log-item { padding: 8px 12px; border-left: 4px solid #ddd; margin-bottom: 5px; background: #f8f9fa; font-size: 13px; }
.log-item.success { border-color: #27ae60; }
.log-item.error { border-color: #e74c3c; background: #fdeaea; }
.log-item.skip { border-color: #f39c12; }
select.form-control { padding: 10px; border-radius: 6px; border: 1px solid #ddd; min-width: 300px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.warning-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
</style>';

echo $OUTPUT->header();
echo $css;

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
$comp_lookup = [];
if ($frameworkid) {
    $competencies = $DB->get_records_sql("
        SELECT id, idnumber, shortname 
        FROM {competency} 
        WHERE competencyframeworkid = ? AND idnumber LIKE 'AUTOMOBILE_%'
        ORDER BY idnumber
    ", [$frameworkid]);
    foreach ($competencies as $c) {
        $comp_lookup[strtoupper($c->idnumber)] = $c->id;
    }
}

// Carica domande raggruppate per categoria
$questions_by_category = [];
$questions = $DB->get_records_sql("
    SELECT q.id, q.name, q.questiontext, qbe.id as qbeid, qc.name as category_name, qc.id as category_id,
           (SELECT GROUP_CONCAT(c.idnumber) FROM {qbank_competenciesbyquestion} qbc 
            JOIN {competency} c ON c.id = qbc.competencyid 
            WHERE qbc.questionid = q.id) as existing_competencies
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    ORDER BY qc.name, q.id
", [$context->id]);

foreach ($questions as $q) {
    if (!isset($questions_by_category[$q->category_name])) {
        $questions_by_category[$q->category_name] = [];
    }
    $questions_by_category[$q->category_name][] = $q;
}

// Prepara analisi
$analysis = [];
$stats = [
    'total' => 0,
    'mappable' => 0,
    'to_assign' => 0,
    'already_assigned' => 0,
    'no_mapping' => 0
];

foreach ($questions_by_category as $cat_name => $cat_questions) {
    // Cerca mapping per questa categoria
    $mapping = null;
    foreach ($category_mappings as $map_name => $map_codes) {
        if (stripos($cat_name, $map_name) !== false || stripos($map_name, $cat_name) !== false) {
            $mapping = $map_codes;
            break;
        }
    }
    
    foreach ($cat_questions as $index => $q) {
        $stats['total']++;
        
        $q->target_code = null;
        $q->target_competency_id = null;
        $q->status = 'no_mapping';
        $q->position = $index + 1;
        
        if ($mapping && isset($mapping[$index])) {
            $code = strtoupper($mapping[$index]);
            $q->target_code = $code;
            $stats['mappable']++;
            
            if (isset($comp_lookup[$code])) {
                $q->target_competency_id = $comp_lookup[$code];
                
                // Verifica se già assegnata
                $already = $DB->record_exists('qbank_competenciesbyquestion', [
                    'questionid' => $q->id,
                    'competencyid' => $q->target_competency_id
                ]);
                
                if ($already) {
                    $q->status = 'already_assigned';
                    $stats['already_assigned']++;
                } else {
                    $q->status = 'to_assign';
                    $stats['to_assign']++;
                }
            } else {
                $q->status = 'code_not_found';
            }
        } else {
            $stats['no_mapping']++;
        }
        
        $analysis[$cat_name][] = $q;
    }
}

// === AZIONE: ESEGUI FIX ===
if ($action === 'execute' && $frameworkid) {
    require_sesskey();
    
    echo '<div class="fix-page">';
    echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Dashboard</a>';
    echo '<div class="fix-header"><h2>🔧 Assegnazione Competenze in Corso...</h2></div>';
    
    $assigned = 0;
    $errors = 0;
    $skipped = 0;
    
    echo '<div class="panel"><h3>📋 Log Operazioni</h3><div style="max-height: 400px; overflow-y: auto;">';
    
    foreach ($analysis as $cat_name => $cat_questions) {
        echo '<div class="log-item" style="background: #e8f4f8; border-color: #3498db;"><strong>📁 ' . htmlspecialchars($cat_name) . '</strong></div>';
        
        foreach ($cat_questions as $q) {
            if ($q->status === 'to_assign' && $q->target_competency_id) {
                try {
                    $rec = new stdClass();
                    $rec->questionid = $q->id;
                    $rec->competencyid = $q->target_competency_id;
                    $rec->difficultylevel = 2; // Livello approfondimento
                    $DB->insert_record('qbank_competenciesbyquestion', $rec);
                    
                    echo '<div class="log-item success">✅ #' . $q->position . ' → ' . $q->target_code . '</div>';
                    $assigned++;
                } catch (Exception $e) {
                    echo '<div class="log-item error">❌ #' . $q->position . ' - Errore: ' . $e->getMessage() . '</div>';
                    $errors++;
                }
            } elseif ($q->status === 'already_assigned') {
                $skipped++;
            }
        }
    }
    
    echo '</div></div>';
    
    // Riepilogo
    echo '<div class="panel">';
    echo '<h3>🎉 Operazione Completata!</h3>';
    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number" style="color: #27ae60;">' . $assigned . '</div><div class="label">Competenze Assegnate</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #3498db;">' . $skipped . '</div><div class="label">Già Presenti (skip)</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #e74c3c;">' . $errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_competenze_per_categoria.php?courseid='.$courseid.'&frameworkid='.$frameworkid.'" class="btn btn-secondary">🔄 Verifica Risultato</a>';
    echo '<a href="audit_competenze.php?courseid='.$courseid.'" class="btn btn-primary">📊 Vai all\'Audit</a>';
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
        <h2>🗂️ Fix Competenze per Categoria</h2>
        <p>Assegna automaticamente le competenze basandosi sulla categoria e posizione ordinale</p>
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
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <?php if ($frameworkid && count($comp_lookup) > 0): ?>
        <p style="color: #27ae60;">✅ Caricate <strong><?php echo count($comp_lookup); ?></strong> competenze AUTOMOBILE dal framework</p>
        <?php endif; ?>
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
                <div class="number"><?php echo $stats['mappable']; ?></div>
                <div class="label">Con Mapping</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo $stats['to_assign']; ?></div>
                <div class="label">Da Assegnare</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?php echo $stats['already_assigned']; ?></div>
                <div class="label">Già Assegnate</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #999;"><?php echo $stats['no_mapping']; ?></div>
                <div class="label">Senza Mapping</div>
            </div>
        </div>
    </div>
    
    <!-- Categorie disponibili -->
    <div class="panel">
        <h3>📁 Mapping per Categoria</h3>
        <p style="color: #666; margin-bottom: 15px;">Le competenze sono assegnate in base alla posizione della domanda nella categoria</p>
        
        <?php foreach ($analysis as $cat_name => $cat_questions): 
            $to_assign_count = count(array_filter($cat_questions, function($q) { return $q->status === 'to_assign'; }));
            $already_count = count(array_filter($cat_questions, function($q) { return $q->status === 'already_assigned'; }));
            $has_mapping = isset($category_mappings[array_keys(array_filter($category_mappings, function($k) use ($cat_name) { 
                return stripos($cat_name, $k) !== false || stripos($k, $cat_name) !== false; 
            }, ARRAY_FILTER_USE_KEY))[0] ?? '']);
        ?>
        <div class="category-section">
            <div class="category-header">
                <span>
                    <?php if ($has_mapping): ?>✅<?php else: ?>⚠️<?php endif; ?>
                    <?php echo htmlspecialchars($cat_name); ?>
                </span>
                <span>
                    <?php if ($to_assign_count > 0): ?>
                    <span class="count" style="background: #27ae60;"><?php echo $to_assign_count; ?> da assegnare</span>
                    <?php endif; ?>
                    <?php if ($already_count > 0): ?>
                    <span class="count" style="background: #3498db;"><?php echo $already_count; ?> già ok</span>
                    <?php endif; ?>
                    <span class="count" style="background: #6c757d;"><?php echo count($cat_questions); ?> totali</span>
                </span>
            </div>
            <div class="question-list">
                <?php foreach ($cat_questions as $q): ?>
                <div class="question-item">
                    <span>#<?php echo $q->position; ?> - <?php echo htmlspecialchars(substr($q->name, 0, 50)); ?></span>
                    <span>
                        <?php if ($q->target_code): ?>
                            <?php if ($q->status === 'to_assign'): ?>
                            <span class="code-badge code-new"><?php echo $q->target_code; ?></span>
                            <?php elseif ($q->status === 'already_assigned'): ?>
                            <span class="code-badge code-exists"><?php echo $q->target_code; ?> ✓</span>
                            <?php else: ?>
                            <span class="code-badge" style="background: #f39c12; color: white;"><?php echo $q->target_code; ?> ?</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Azioni -->
    <?php if ($stats['to_assign'] > 0 && $frameworkid): ?>
    <div class="panel" style="background: #e8f5e9; border-color: #27ae60;">
        <h3 style="color: #27ae60;">🚀 Pronto per l'Assegnazione!</h3>
        <p>Verranno assegnate <strong><?php echo $stats['to_assign']; ?></strong> competenze alle domande.</p>
        <form method="post" action="fix_competenze_per_categoria.php?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-success">✅ Esegui Assegnazione</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
    </div>
    <?php elseif ($stats['to_assign'] == 0 && $stats['already_assigned'] > 0): ?>
    <div class="panel" style="background: #e3f2fd; border-color: #3498db;">
        <h3 style="color: #3498db;">✅ Tutto OK!</h3>
        <p>Tutte le domande mappabili hanno già le competenze assegnate.</p>
        <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">📊 Vai all'Audit</a>
    </div>
    <?php endif; ?>
</div>
<?php

echo $OUTPUT->footer();
