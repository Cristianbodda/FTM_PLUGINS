<?php
/**
 * FIX COMPLETO CHIMFARM - v2 CON DEBUG
 * 
 * Esegue 3 operazioni in sequenza:
 * 1. Elimina duplicati CHIM_APPR03
 * 2. Mappa CHIMICA_LBIO_* → CHIMFARM_6P_* (microbiologia)
 * 3. Corregge codici errati (es. CHIMFARM_7S_018 → CHIMFARM_7S_18)
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
$PAGE->set_url('/local/competencyxmlimport/fix_completo_chimfarm.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Completo CHIMFARM');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================
// CARICA FRAMEWORKS E COMPETENZE
// ============================================================

// Carica tutti i frameworks
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

// Se non specificato, cerca FTM (non old_)
if (!$frameworkid) {
    foreach ($frameworks as $fw) {
        if ((stripos($fw->shortname, 'FTM') !== false || stripos($fw->idnumber, 'FTM') !== false)
            && stripos($fw->shortname, 'old') === false) {
            $frameworkid = $fw->id;
            break;
        }
    }
}

// Carica TUTTE le competenze dal framework
$comp_lookup = [];
$chimfarm_comps = [];
if ($frameworkid) {
    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid]);
    foreach ($competencies as $c) {
        $comp_lookup[strtoupper($c->idnumber)] = $c->id;
        if (stripos($c->idnumber, 'CHIMFARM_') === 0) {
            $chimfarm_comps[] = strtoupper($c->idnumber);
        }
    }
}

// ============================================================
// MAPPATURA CHIMICA_LBIO → CHIMFARM_6P (Microbiologia)
// ============================================================
$lbio_mapping = [
    'CHIMICA_LBIO_C1'  => 'CHIMFARM_6P_01',
    'CHIMICA_LBIO_C2'  => 'CHIMFARM_6P_06',
    'CHIMICA_LBIO_C3'  => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C4'  => 'CHIMFARM_6P_02',
    'CHIMICA_LBIO_C5'  => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C6'  => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C7'  => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C8'  => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C9'  => 'CHIMFARM_6P_02',
    'CHIMICA_LBIO_C10' => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C11' => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C12' => 'CHIMFARM_6P_01',
    'CHIMICA_LBIO_C13' => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C14' => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C15' => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C16' => 'CHIMFARM_6P_02',
    'CHIMICA_LBIO_C17' => 'CHIMFARM_6P_06',
    'CHIMICA_LBIO_C18' => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C19' => 'CHIMFARM_6P_03',
    'CHIMICA_LBIO_C20' => 'CHIMFARM_6P_06',
    'CHIMICA_LBIO_C21' => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C22' => 'CHIMFARM_6P_02',
    'CHIMICA_LBIO_C23' => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C24' => 'CHIMFARM_6P_05',
    'CHIMICA_LBIO_C25' => 'CHIMFARM_6P_04',
];

// ============================================================
// CORREZIONI CODICI ERRATI (zero extra)
// ============================================================
$code_corrections = [
    'CHIMFARM_7S_018' => 'CHIMFARM_7S_18',
    'CHIMFARM_6P_010' => 'CHIMFARM_6P_10',
    'CHIMFARM_6P_013' => 'CHIMFARM_6P_13',
    'CHIMFARM_6P_015' => 'CHIMFARM_6P_15',
    'CHIMFARM_5S_010' => 'CHIMFARM_5S_10',
    'CHIMFARM_5S_011' => 'CHIMFARM_5S_11',
    'CHIMFARM_5S_012' => 'CHIMFARM_5S_12',
];

// CSS
$css = '
<style>
.fix-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.fix-header { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fix-header h2 { margin: 0 0 8px 0; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.step-header { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #9b59b6; }
.step-header.step1 { border-color: #e74c3c; }
.step-header.step2 { border-color: #3498db; }
.step-header.step3 { border-color: #27ae60; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
.stat-card .number { font-size: 24px; font-weight: bold; }
.stat-card .label { color: #666; margin-top: 5px; font-size: 12px; }
.item-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
.item-table th, .item-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
.item-table th { background: #f8f9fa; font-weight: 600; }
.item-table tr:hover { background: #f8f9fa; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-danger { background: #e74c3c; color: white; }
.badge-info { background: #3498db; color: white; }
.badge-success { background: #27ae60; color: white; }
.badge-warning { background: #f39c12; color: white; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; margin-right: 10px; }
.btn-primary { background: #9b59b6; color: white; }
.btn-primary:hover { background: #8e44ad; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.back-link { display: inline-block; margin-bottom: 20px; color: #9b59b6; }
.log-item { padding: 8px 12px; border-left: 4px solid #ddd; margin-bottom: 5px; background: #f8f9fa; font-size: 13px; }
.log-item.success { border-color: #27ae60; }
.log-item.error { border-color: #e74c3c; background: #fdeaea; }
.log-item.info { border-color: #3498db; }
.log-item.warning { border-color: #f39c12; }
.scroll-box { max-height: 300px; overflow-y: auto; }
code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
.debug-panel { background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-family: monospace; font-size: 12px; }
.debug-panel pre { margin: 0; white-space: pre-wrap; }
select.form-control { padding: 10px; border-radius: 6px; border: 1px solid #ddd; min-width: 300px; }
</style>';

echo $OUTPUT->header();
echo $css;

// Sample competenze 6P e 7S
$sample_6p = array_filter($chimfarm_comps, fn($c) => strpos($c, 'CHIMFARM_6P_') === 0);
$sample_7s = array_filter($chimfarm_comps, fn($c) => strpos($c, 'CHIMFARM_7S_') === 0);

?>
<div class="fix-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Dashboard</a>

    <div class="fix-header">
        <h2>🔧 Fix Completo CHIMFARM v2</h2>
        <p>Esegue 3 operazioni: Elimina duplicati → Mappa LBIO → Corregge codici</p>
    </div>

    <!-- DEBUG INFO -->
    <div class="debug-panel">
        <pre><strong>=== 🐛 DEBUG INFO ===</strong>
Framework ID: <?php echo $frameworkid ?: 'NON SELEZIONATO'; ?>

Framework Nome: <?php echo $frameworkid && isset($frameworks[$frameworkid]) ? $frameworks[$frameworkid]->shortname : 'N/A'; ?>

Totale Competenze caricate: <?php echo count($comp_lookup); ?>

Competenze CHIMFARM: <?php echo count($chimfarm_comps); ?>

Competenze 6P nel framework: <?php echo implode(', ', array_slice(array_values($sample_6p), 0, 10)) ?: 'NESSUNA'; ?>

Competenze 7S nel framework: <?php echo implode(', ', array_slice(array_values($sample_7s), 0, 10)) ?: 'NESSUNA'; ?>

Test lookup CHIMFARM_6P_01: <?php echo isset($comp_lookup['CHIMFARM_6P_01']) ? '✅ TROVATO (ID: '.$comp_lookup['CHIMFARM_6P_01'].')' : '❌ NON TROVATO'; ?>

Test lookup CHIMFARM_7S_18: <?php echo isset($comp_lookup['CHIMFARM_7S_18']) ? '✅ TROVATO (ID: '.$comp_lookup['CHIMFARM_7S_18'].')' : '❌ NON TROVATO'; ?>
</pre>
    </div>

    <!-- SELEZIONE FRAMEWORK -->
    <div class="panel">
        <h3>⚙️ Seleziona Framework</h3>
        <form method="get" action="">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <select name="frameworkid" class="form-control" onchange="this.form.submit()">
                <option value="">-- Seleziona Framework --</option>
                <?php foreach ($frameworks as $fw): 
                    $count = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
                ?>
                <option value="<?php echo $fw->id; ?>" <?php echo ($frameworkid == $fw->id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($fw->shortname); ?> (<?php echo $count; ?> competenze)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <?php if ($frameworkid && count($comp_lookup) > 0): ?>
        <p style="color: #27ae60; margin-top: 15px;">✅ Framework caricato con <strong><?php echo count($comp_lookup); ?></strong> competenze</p>
        <?php elseif ($frameworkid): ?>
        <p style="color: #e74c3c; margin-top: 15px;">⚠️ Framework selezionato ma nessuna competenza trovata!</p>
        <?php else: ?>
        <p style="color: #f39c12; margin-top: 15px;">⚠️ Seleziona un framework per continuare</p>
        <?php endif; ?>
    </div>

<?php
// ============================================================
// FASE 1: ANALISI DUPLICATI
// ============================================================

$duplicates_sql = "
    SELECT q.id, q.name, q.timecreated, qc.name as category_name
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    AND q.name LIKE 'CHIM_APPR03_%'
    ORDER BY q.name, q.timecreated DESC
";
$appr03_questions = $DB->get_records_sql($duplicates_sql, [$context->id]);

$grouped = [];
foreach ($appr03_questions as $q) {
    $base_name = $q->name;
    if (!isset($grouped[$base_name])) {
        $grouped[$base_name] = [];
    }
    $grouped[$base_name][] = $q;
}

$duplicates_to_delete = [];
foreach ($grouped as $name => $questions) {
    if (count($questions) > 1) {
        array_shift($questions); // Tieni il primo
        foreach ($questions as $dup) {
            $duplicates_to_delete[] = $dup;
        }
    }
}

// ============================================================
// FASE 2: ANALISI CHIMICA_LBIO
// ============================================================

$lbio_sql = "
    SELECT q.id, q.name
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    AND q.name LIKE '%CHIMICA_LBIO_%'
    ORDER BY q.name
";
$lbio_questions = $DB->get_records_sql($lbio_sql, [$context->id]);

$lbio_analysis = [];
foreach ($lbio_questions as $q) {
    if (preg_match('/(CHIMICA_LBIO_C\d+)/i', $q->name, $matches)) {
        $lbio_code = strtoupper($matches[1]);
        $chimfarm_code = $lbio_mapping[$lbio_code] ?? null;
        $comp_id = $chimfarm_code ? ($comp_lookup[$chimfarm_code] ?? null) : null;
        
        $lbio_analysis[] = (object)[
            'id' => $q->id,
            'name' => $q->name,
            'lbio_code' => $lbio_code,
            'chimfarm_code' => $chimfarm_code,
            'comp_id' => $comp_id,
            'can_map' => ($comp_id !== null)
        ];
    }
}

// ============================================================
// FASE 3: ANALISI CODICI ERRATI
// ============================================================

$wrong_codes_sql = "
    SELECT q.id, q.name
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    AND (
        q.name LIKE '%\\_018%'
        OR q.name LIKE '%\\_010%'
        OR q.name LIKE '%\\_011%'
        OR q.name LIKE '%\\_012%'
        OR q.name LIKE '%\\_013%'
        OR q.name LIKE '%\\_014%'
        OR q.name LIKE '%\\_015%'
    )
    ORDER BY q.name
";
$wrong_code_questions = $DB->get_records_sql($wrong_codes_sql, [$context->id]);

$wrong_analysis = [];
foreach ($wrong_code_questions as $q) {
    $old_code = null;
    $new_code = null;
    
    foreach ($code_corrections as $wrong => $correct) {
        if (stripos($q->name, $wrong) !== false) {
            $old_code = $wrong;
            $new_code = $correct;
            break;
        }
    }
    
    if ($old_code) {
        $new_name = str_ireplace($old_code, $new_code, $q->name);
        $comp_id = $comp_lookup[strtoupper($new_code)] ?? null;
        
        $wrong_analysis[] = (object)[
            'id' => $q->id,
            'name' => $q->name,
            'new_name' => $new_name,
            'old_code' => $old_code,
            'new_code' => $new_code,
            'comp_id' => $comp_id,
            'can_fix' => ($comp_id !== null)
        ];
    }
}

// ============================================================
// ESECUZIONE
// ============================================================

if ($action === 'execute' && $frameworkid) {
    require_sesskey();
    
    echo '<div class="panel"><div class="step-header"><strong>🚀 ESECUZIONE IN CORSO...</strong></div>';
    
    $total_success = 0;
    $total_errors = 0;
    
    // === STEP 1: ELIMINA DUPLICATI ===
    echo '<h4>STEP 1: Eliminazione Duplicati</h4><div class="scroll-box">';
    $step1_success = 0;
    
    foreach ($duplicates_to_delete as $dup) {
        try {
            $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $dup->id]);
            $version = $DB->get_record('question_versions', ['questionid' => $dup->id]);
            if ($version) {
                $DB->delete_records('question_versions', ['id' => $version->id]);
                $other_versions = $DB->count_records('question_versions', ['questionbankentryid' => $version->questionbankentryid]);
                if ($other_versions == 0) {
                    $DB->delete_records('question_bank_entries', ['id' => $version->questionbankentryid]);
                }
            }
            $DB->delete_records('question', ['id' => $dup->id]);
            echo '<div class="log-item success">🗑️ ' . htmlspecialchars(substr($dup->name, 0, 50)) . '</div>';
            $step1_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ Errore: ' . $e->getMessage() . '</div>';
            $total_errors++;
        }
    }
    if ($step1_success == 0) echo '<div class="log-item info">Nessun duplicato da eliminare</div>';
    echo '</div>';
    $total_success += $step1_success;
    
    // === STEP 2: MAPPA LBIO ===
    echo '<h4>STEP 2: Mappatura LBIO → CHIMFARM</h4><div class="scroll-box">';
    $step2_success = 0;
    
    foreach ($lbio_analysis as $item) {
        if (!$item->can_map) {
            echo '<div class="log-item warning">⚠️ Skip ' . $item->lbio_code . ' (competenza ' . $item->chimfarm_code . ' non trovata)</div>';
            continue;
        }
        
        try {
            $new_name = str_ireplace($item->lbio_code, $item->chimfarm_code, $item->name);
            $DB->set_field('question', 'name', $new_name, ['id' => $item->id]);
            
            $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                'questionid' => $item->id,
                'competencyid' => $item->comp_id
            ]);
            
            if (!$exists) {
                $rec = new stdClass();
                $rec->questionid = $item->id;
                $rec->competencyid = $item->comp_id;
                $rec->difficultylevel = 1;
                $DB->insert_record('qbank_competenciesbyquestion', $rec);
            }
            
            echo '<div class="log-item success">✅ ' . $item->lbio_code . ' → ' . $item->chimfarm_code . '</div>';
            $step2_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ ' . $e->getMessage() . '</div>';
            $total_errors++;
        }
    }
    if ($step2_success == 0 && count($lbio_analysis) == 0) echo '<div class="log-item info">Nessuna domanda LBIO</div>';
    echo '</div>';
    $total_success += $step2_success;
    
    // === STEP 3: CORREGGI CODICI ===
    echo '<h4>STEP 3: Correzione Codici Errati</h4><div class="scroll-box">';
    $step3_success = 0;
    
    foreach ($wrong_analysis as $item) {
        if (!$item->can_fix) {
            echo '<div class="log-item warning">⚠️ Skip ' . $item->old_code . ' (competenza ' . $item->new_code . ' non trovata)</div>';
            continue;
        }
        
        try {
            $DB->set_field('question', 'name', $item->new_name, ['id' => $item->id]);
            
            $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                'questionid' => $item->id,
                'competencyid' => $item->comp_id
            ]);
            
            if (!$exists) {
                $rec = new stdClass();
                $rec->questionid = $item->id;
                $rec->competencyid = $item->comp_id;
                $rec->difficultylevel = 1;
                $DB->insert_record('qbank_competenciesbyquestion', $rec);
            }
            
            echo '<div class="log-item success">✅ ' . $item->old_code . ' → ' . $item->new_code . '</div>';
            $step3_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ ' . $e->getMessage() . '</div>';
            $total_errors++;
        }
    }
    if ($step3_success == 0 && count($wrong_analysis) == 0) echo '<div class="log-item info">Nessun codice errato</div>';
    echo '</div>';
    $total_success += $step3_success;
    
    // Riepilogo
    echo '<div class="stats-grid" style="margin-top: 20px;">';
    echo '<div class="stat-card"><div class="number" style="color: #27ae60;">' . $total_success . '</div><div class="label">Successi</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #e74c3c;">' . $total_errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_completo_chimfarm.php?courseid='.$courseid.'&frameworkid='.$frameworkid.'" class="btn btn-secondary">🔄 Verifica</a>';
    echo '<a href="fix_competenze_da_nome.php?courseid='.$courseid.'" class="btn btn-primary">📊 Fix Competenze</a>';
    echo '</div></div>';
    echo $OUTPUT->footer();
    exit;
}

// ============================================================
// PAGINA PREVIEW
// ============================================================
?>

    <!-- STEP 1: DUPLICATI -->
    <div class="panel">
        <div class="step-header step1">
            <strong>STEP 1:</strong> Eliminazione Duplicati CHIM_APPR03
            <span class="badge badge-danger" style="float: right;"><?php echo count($duplicates_to_delete); ?></span>
        </div>
        <?php if (count($duplicates_to_delete) > 0): ?>
        <div class="scroll-box">
            <table class="item-table">
                <thead><tr><th>Nome</th><th>Data</th><th>Azione</th></tr></thead>
                <tbody>
                    <?php foreach ($duplicates_to_delete as $dup): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($dup->name, 0, 50)); ?></td>
                        <td><?php echo date('d/m/Y H:i', $dup->timecreated); ?></td>
                        <td><span class="badge badge-danger">Eliminare</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #27ae60;">✅ Nessun duplicato</p>
        <?php endif; ?>
    </div>

    <!-- STEP 2: LBIO -->
    <div class="panel">
        <div class="step-header step2">
            <strong>STEP 2:</strong> Mappatura CHIMICA_LBIO → CHIMFARM
            <span class="badge badge-info" style="float: right;"><?php echo count(array_filter($lbio_analysis, fn($x) => $x->can_map)); ?> pronte</span>
        </div>
        <?php $lbio_ready = count(array_filter($lbio_analysis, fn($x) => $x->can_map)); ?>
        <?php if (count($lbio_analysis) > 0): ?>
        <p>Trovate <?php echo count($lbio_analysis); ?> domande, di cui <strong style="color: <?php echo $lbio_ready > 0 ? '#27ae60' : '#e74c3c'; ?>;"><?php echo $lbio_ready; ?></strong> mappabili.</p>
        <?php if ($lbio_ready == 0 && $frameworkid): ?>
        <p style="color: #e74c3c;">⚠️ Le competenze CHIMFARM_6P_* non esistono nel framework selezionato. Verifica il framework!</p>
        <?php endif; ?>
        <div class="scroll-box">
            <table class="item-table">
                <thead><tr><th>Domanda</th><th>LBIO</th><th>→ CHIMFARM</th><th>Stato</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($lbio_analysis, 0, 30) as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($item->name, 0, 35)); ?>...</td>
                        <td><code><?php echo $item->lbio_code; ?></code></td>
                        <td><code><?php echo $item->chimfarm_code ?: '-'; ?></code></td>
                        <td>
                            <?php if ($item->can_map): ?>
                            <span class="badge badge-success">✓ Pronto</span>
                            <?php else: ?>
                            <span class="badge badge-warning">No comp</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #27ae60;">✅ Nessuna domanda LBIO</p>
        <?php endif; ?>
    </div>

    <!-- STEP 3: CODICI ERRATI -->
    <div class="panel">
        <div class="step-header step3">
            <strong>STEP 3:</strong> Correzione Codici Errati
            <span class="badge badge-success" style="float: right;"><?php echo count(array_filter($wrong_analysis, fn($x) => $x->can_fix)); ?></span>
        </div>
        <?php $wrong_ready = count(array_filter($wrong_analysis, fn($x) => $x->can_fix)); ?>
        <?php if (count($wrong_analysis) > 0): ?>
        <p>Trovate <?php echo count($wrong_analysis); ?> domande, di cui <strong style="color: <?php echo $wrong_ready > 0 ? '#27ae60' : '#e74c3c'; ?>;"><?php echo $wrong_ready; ?></strong> correggibili.</p>
        <div class="scroll-box">
            <table class="item-table">
                <thead><tr><th>Domanda</th><th>Errato</th><th>→ Corretto</th><th>Stato</th></tr></thead>
                <tbody>
                    <?php foreach ($wrong_analysis as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($item->name, 0, 40)); ?>...</td>
                        <td><code style="color: #e74c3c;"><?php echo $item->old_code; ?></code></td>
                        <td><code style="color: #27ae60;"><?php echo $item->new_code; ?></code></td>
                        <td>
                            <?php if ($item->can_fix): ?>
                            <span class="badge badge-success">✓ Pronto</span>
                            <?php else: ?>
                            <span class="badge badge-warning">No comp</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #27ae60;">✅ Nessun codice errato</p>
        <?php endif; ?>
    </div>

    <!-- RIEPILOGO E AZIONE -->
    <?php 
    $lbio_ready = count(array_filter($lbio_analysis, fn($x) => $x->can_map));
    $wrong_ready = count(array_filter($wrong_analysis, fn($x) => $x->can_fix));
    $total_actions = count($duplicates_to_delete) + $lbio_ready + $wrong_ready;
    ?>
    
    <div class="panel" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border-color: #9b59b6;">
        <h3 style="color: #9b59b6;">📊 Riepilogo</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number" style="color: #e74c3c;"><?php echo count($duplicates_to_delete); ?></div>
                <div class="label">Duplicati</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?php echo $lbio_ready; ?></div>
                <div class="label">LBIO mappabili</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo $wrong_ready; ?></div>
                <div class="label">Codici correggibili</div>
            </div>
        </div>
        
        <?php if ($total_actions > 0 && $frameworkid): ?>
        <form method="post" action="fix_completo_chimfarm.php?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-primary">🚀 Esegui (<?php echo $total_actions; ?> azioni)</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
        <?php elseif (!$frameworkid): ?>
        <p style="color: #e74c3c;"><strong>⚠️ Seleziona un framework prima di procedere!</strong></p>
        <?php elseif ($total_actions == 0 && (count($lbio_analysis) > 0 || count($wrong_analysis) > 0)): ?>
        <p style="color: #e74c3c;"><strong>⚠️ Nessuna azione possibile - le competenze target non esistono nel framework selezionato!</strong></p>
        <p>Verifica che il framework contenga le competenze CHIMFARM_6P_* e CHIMFARM_7S_*</p>
        <?php else: ?>
        <p style="color: #27ae60; font-weight: bold;">✅ Nessuna operazione necessaria</p>
        <?php endif; ?>
    </div>
</div>
<?php

echo $OUTPUT->footer();
