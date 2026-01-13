<?php
/**
 * FIX COMPLETO CHIMFARM
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
// MAPPATURA CHIMICA_LBIO → CHIMFARM_6P (Microbiologia)
// ============================================================
// Basata sui contenuti delle domande di microbiologia
$lbio_mapping = [
    'CHIMICA_LBIO_C1'  => 'CHIMFARM_6P_01', // Diluizioni seriali
    'CHIMICA_LBIO_C2'  => 'CHIMFARM_6P_06', // Incubazione batteri mesofili
    'CHIMICA_LBIO_C3'  => 'CHIMFARM_6P_03', // Colorazione Gram
    'CHIMICA_LBIO_C4'  => 'CHIMFARM_6P_02', // Semina su piastra
    'CHIMICA_LBIO_C5'  => 'CHIMFARM_6P_03', // Terreni selettivi
    'CHIMICA_LBIO_C6'  => 'CHIMFARM_6P_03', // Test catalasi
    'CHIMICA_LBIO_C7'  => 'CHIMFARM_6P_05', // Filtrazione su membrana
    'CHIMICA_LBIO_C8'  => 'CHIMFARM_6P_03', // Colonie su Endo Agar
    'CHIMICA_LBIO_C9'  => 'CHIMFARM_6P_02', // Cabina biologica
    'CHIMICA_LBIO_C10' => 'CHIMFARM_6P_03', // Distinzione batteri/funghi
    'CHIMICA_LBIO_C11' => 'CHIMFARM_6P_03', // Test identificazione Gram-
    'CHIMICA_LBIO_C12' => 'CHIMFARM_6P_01', // Calcolo diluizioni
    'CHIMICA_LBIO_C13' => 'CHIMFARM_6P_05', // Antibiogramma
    'CHIMICA_LBIO_C14' => 'CHIMFARM_6P_03', // MacConkey Agar
    'CHIMICA_LBIO_C15' => 'CHIMFARM_6P_05', // Spettrofotometria torbidimetria
    'CHIMICA_LBIO_C16' => 'CHIMFARM_6P_02', // Errori striatura
    'CHIMICA_LBIO_C17' => 'CHIMFARM_6P_06', // Terreni lieviti/muffe
    'CHIMICA_LBIO_C18' => 'CHIMFARM_6P_03', // Gram variabile
    'CHIMICA_LBIO_C19' => 'CHIMFARM_6P_03', // Test ossidasi
    'CHIMICA_LBIO_C20' => 'CHIMFARM_6P_06', // Terreno arricchimento
    'CHIMICA_LBIO_C21' => 'CHIMFARM_6P_05', // Microscopia campo oscuro
    'CHIMICA_LBIO_C22' => 'CHIMFARM_6P_02', // Contaminazione incrociata
    'CHIMICA_LBIO_C23' => 'CHIMFARM_6P_05', // Controllo negativo
    'CHIMICA_LBIO_C24' => 'CHIMFARM_6P_05', // Preparazione vetrino
    'CHIMICA_LBIO_C25' => 'CHIMFARM_6P_04', // Smaltimento rifiuti biologici
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
.mapping-table { font-size: 12px; }
.mapping-table td { padding: 4px 8px; }
code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
</style>';

echo $OUTPUT->header();
echo $css;

// ============================================================
// FASE 1: ANALISI DUPLICATI
// ============================================================

// Trova duplicati CHIM_APPR03
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

// Raggruppa per nome e trova duplicati
$grouped = [];
foreach ($appr03_questions as $q) {
    // Estrai nome base (senza timestamp)
    $base_name = $q->name;
    if (!isset($grouped[$base_name])) {
        $grouped[$base_name] = [];
    }
    $grouped[$base_name][] = $q;
}

$duplicates_to_delete = [];
$duplicates_to_keep = [];
foreach ($grouped as $name => $questions) {
    if (count($questions) > 1) {
        // Tieni la più recente, elimina le altre
        $keep = array_shift($questions);
        $duplicates_to_keep[$name] = $keep;
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

// Carica competenze CHIMFARM dal framework
$framework = $DB->get_record_sql("
    SELECT id FROM {competency_framework} 
    WHERE shortname LIKE '%FTM%' OR idnumber LIKE '%FTM%'
    LIMIT 1
");
$comp_lookup = [];
if ($framework) {
    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $framework->id]);
    foreach ($competencies as $c) {
        $comp_lookup[strtoupper($c->idnumber)] = $c->id;
    }
}

// Analizza quali LBIO possono essere mappate
$lbio_analysis = [];
foreach ($lbio_questions as $q) {
    // Estrai codice LBIO
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
        q.name LIKE '%CHIMFARM_%018%'
        OR q.name LIKE '%CHIMFARM_%010%'
        OR q.name LIKE '%CHIMFARM_%011%'
        OR q.name LIKE '%CHIMFARM_%012%'
        OR q.name LIKE '%CHIMFARM_%013%'
        OR q.name LIKE '%CHIMFARM_%014%'
        OR q.name LIKE '%CHIMFARM_%015%'
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

if ($action === 'execute') {
    require_sesskey();
    
    echo '<div class="fix-page">';
    echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Dashboard</a>';
    echo '<div class="fix-header"><h2>🔧 Esecuzione Fix Completo CHIMFARM</h2></div>';
    
    $total_success = 0;
    $total_errors = 0;
    
    // === STEP 1: ELIMINA DUPLICATI ===
    echo '<div class="panel">';
    echo '<div class="step-header step1"><strong>STEP 1:</strong> Eliminazione Duplicati CHIM_APPR03</div>';
    echo '<div class="scroll-box">';
    
    $step1_success = 0;
    $step1_errors = 0;
    
    foreach ($duplicates_to_delete as $dup) {
        try {
            // Elimina associazioni competenze
            $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $dup->id]);
            
            // Trova e elimina version/entry
            $version = $DB->get_record('question_versions', ['questionid' => $dup->id]);
            if ($version) {
                $DB->delete_records('question_versions', ['id' => $version->id]);
                
                // Verifica se l'entry ha altre versioni
                $other_versions = $DB->count_records('question_versions', ['questionbankentryid' => $version->questionbankentryid]);
                if ($other_versions == 0) {
                    $DB->delete_records('question_bank_entries', ['id' => $version->questionbankentryid]);
                }
            }
            
            // Elimina la domanda
            $DB->delete_records('question', ['id' => $dup->id]);
            
            echo '<div class="log-item success">🗑️ Eliminato duplicato: ' . htmlspecialchars(substr($dup->name, 0, 50)) . '</div>';
            $step1_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ Errore: ' . htmlspecialchars(substr($dup->name, 0, 40)) . ' - ' . $e->getMessage() . '</div>';
            $step1_errors++;
        }
    }
    
    if ($step1_success == 0 && $step1_errors == 0) {
        echo '<div class="log-item info">ℹ️ Nessun duplicato da eliminare</div>';
    }
    echo '</div>';
    echo '<p><strong>Risultato Step 1:</strong> ' . $step1_success . ' eliminati, ' . $step1_errors . ' errori</p>';
    echo '</div>';
    
    $total_success += $step1_success;
    $total_errors += $step1_errors;
    
    // === STEP 2: MAPPA CHIMICA_LBIO ===
    echo '<div class="panel">';
    echo '<div class="step-header step2"><strong>STEP 2:</strong> Mappatura CHIMICA_LBIO → CHIMFARM</div>';
    echo '<div class="scroll-box">';
    
    $step2_success = 0;
    $step2_errors = 0;
    
    foreach ($lbio_analysis as $item) {
        if (!$item->can_map) {
            echo '<div class="log-item warning">⚠️ Skip (no mapping): ' . htmlspecialchars($item->lbio_code) . '</div>';
            continue;
        }
        
        try {
            // Aggiorna nome domanda
            $new_name = str_ireplace($item->lbio_code, $item->chimfarm_code, $item->name);
            $DB->set_field('question', 'name', $new_name, ['id' => $item->id]);
            
            // Verifica se competenza già assegnata
            $exists = $DB->record_exists('qbank_competenciesbyquestion', [
                'questionid' => $item->id,
                'competencyid' => $item->comp_id
            ]);
            
            if (!$exists) {
                // Assegna competenza
                $rec = new stdClass();
                $rec->questionid = $item->id;
                $rec->competencyid = $item->comp_id;
                $rec->difficultylevel = 1;
                $DB->insert_record('qbank_competenciesbyquestion', $rec);
            }
            
            echo '<div class="log-item success">✅ ' . htmlspecialchars($item->lbio_code) . ' → <strong>' . $item->chimfarm_code . '</strong></div>';
            $step2_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ Errore: ' . $e->getMessage() . '</div>';
            $step2_errors++;
        }
    }
    
    if ($step2_success == 0 && $step2_errors == 0) {
        echo '<div class="log-item info">ℹ️ Nessuna domanda LBIO da mappare</div>';
    }
    echo '</div>';
    echo '<p><strong>Risultato Step 2:</strong> ' . $step2_success . ' mappate, ' . $step2_errors . ' errori</p>';
    echo '</div>';
    
    $total_success += $step2_success;
    $total_errors += $step2_errors;
    
    // === STEP 3: CORREGGI CODICI ERRATI ===
    echo '<div class="panel">';
    echo '<div class="step-header step3"><strong>STEP 3:</strong> Correzione Codici Errati</div>';
    echo '<div class="scroll-box">';
    
    $step3_success = 0;
    $step3_errors = 0;
    
    foreach ($wrong_analysis as $item) {
        if (!$item->can_fix) {
            echo '<div class="log-item warning">⚠️ Skip (competenza non trovata): ' . htmlspecialchars($item->new_code) . '</div>';
            continue;
        }
        
        try {
            // Aggiorna nome domanda
            $DB->set_field('question', 'name', $item->new_name, ['id' => $item->id]);
            
            // Rimuovi vecchia competenza errata (se esiste)
            // e aggiungi quella corretta
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
            
            echo '<div class="log-item success">✅ ' . htmlspecialchars($item->old_code) . ' → <strong>' . $item->new_code . '</strong></div>';
            $step3_success++;
        } catch (Exception $e) {
            echo '<div class="log-item error">❌ Errore: ' . $e->getMessage() . '</div>';
            $step3_errors++;
        }
    }
    
    if ($step3_success == 0 && $step3_errors == 0) {
        echo '<div class="log-item info">ℹ️ Nessun codice errato da correggere</div>';
    }
    echo '</div>';
    echo '<p><strong>Risultato Step 3:</strong> ' . $step3_success . ' corretti, ' . $step3_errors . ' errori</p>';
    echo '</div>';
    
    $total_success += $step3_success;
    $total_errors += $step3_errors;
    
    // === RIEPILOGO FINALE ===
    echo '<div class="panel" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-color: #27ae60;">';
    echo '<h3 style="color: #27ae60;">🎉 Operazioni Completate!</h3>';
    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number" style="color: #27ae60;">' . $total_success . '</div><div class="label">Operazioni Riuscite</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #e74c3c;">' . $total_errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_completo_chimfarm.php?courseid='.$courseid.'" class="btn btn-secondary">🔄 Verifica Risultato</a>';
    echo '<a href="fix_competenze_da_nome.php?courseid='.$courseid.'" class="btn btn-primary">📊 Fix Competenze</a>';
    echo '</div>';
    
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// ============================================================
// PAGINA PREVIEW
// ============================================================
?>
<div class="fix-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Dashboard</a>

    <div class="fix-header">
        <h2>🔧 Fix Completo CHIMFARM</h2>
        <p>Esegue 3 operazioni: Elimina duplicati → Mappa LBIO → Corregge codici</p>
    </div>

    <!-- STEP 1: DUPLICATI -->
    <div class="panel">
        <div class="step-header step1">
            <strong>STEP 1:</strong> Eliminazione Duplicati CHIM_APPR03
            <span class="badge badge-danger" style="float: right;"><?php echo count($duplicates_to_delete); ?> da eliminare</span>
        </div>
        
        <?php if (count($duplicates_to_delete) > 0): ?>
        <p>Trovate <strong><?php echo count($duplicates_to_delete); ?></strong> domande duplicate che verranno eliminate (verrà mantenuta la più recente).</p>
        <div class="scroll-box">
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Nome Domanda</th>
                        <th>Data Creazione</th>
                        <th>Azione</th>
                    </tr>
                </thead>
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
        <p style="color: #27ae60;">✅ Nessun duplicato trovato</p>
        <?php endif; ?>
    </div>

    <!-- STEP 2: MAPPATURA LBIO -->
    <div class="panel">
        <div class="step-header step2">
            <strong>STEP 2:</strong> Mappatura CHIMICA_LBIO → CHIMFARM_6P
            <span class="badge badge-info" style="float: right;"><?php echo count($lbio_analysis); ?> da mappare</span>
        </div>
        
        <?php if (count($lbio_analysis) > 0): ?>
        <p>Trovate <strong><?php echo count($lbio_analysis); ?></strong> domande con codice CHIMICA_LBIO che verranno convertite in CHIMFARM.</p>
        
        <details style="margin: 15px 0;">
            <summary style="cursor: pointer; color: #3498db;">📋 Mostra tabella mappatura completa</summary>
            <table class="item-table mapping-table" style="margin-top: 10px;">
                <thead>
                    <tr><th>Codice LBIO</th><th>→</th><th>Codice CHIMFARM</th><th>Descrizione</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lbio_mapping as $lbio => $chimfarm): ?>
                    <tr>
                        <td><code><?php echo $lbio; ?></code></td>
                        <td>→</td>
                        <td><code><?php echo $chimfarm; ?></code></td>
                        <td style="font-size: 11px; color: #666;">
                            <?php 
                            $desc = [
                                'CHIMFARM_6P_01' => 'Diluizioni e preparazioni',
                                'CHIMFARM_6P_02' => 'Semina e colture',
                                'CHIMFARM_6P_03' => 'Identificazione microrganismi',
                                'CHIMFARM_6P_04' => 'Smaltimento rifiuti biologici',
                                'CHIMFARM_6P_05' => 'Tecniche analitiche',
                                'CHIMFARM_6P_06' => 'Incubazione e terreni',
                            ];
                            echo $desc[$chimfarm] ?? '';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
        
        <div class="scroll-box">
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Domanda</th>
                        <th>LBIO</th>
                        <th>→ CHIMFARM</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lbio_analysis as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($item->name, 0, 35)); ?>...</td>
                        <td><code><?php echo $item->lbio_code; ?></code></td>
                        <td><code><?php echo $item->chimfarm_code ?: '-'; ?></code></td>
                        <td>
                            <?php if ($item->can_map): ?>
                            <span class="badge badge-success">Pronto</span>
                            <?php else: ?>
                            <span class="badge badge-warning">No mapping</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #27ae60;">✅ Nessuna domanda LBIO da mappare</p>
        <?php endif; ?>
    </div>

    <!-- STEP 3: CODICI ERRATI -->
    <div class="panel">
        <div class="step-header step3">
            <strong>STEP 3:</strong> Correzione Codici Errati (zero extra)
            <span class="badge badge-success" style="float: right;"><?php echo count($wrong_analysis); ?> da correggere</span>
        </div>
        
        <?php if (count($wrong_analysis) > 0): ?>
        <p>Trovate <strong><?php echo count($wrong_analysis); ?></strong> domande con codici errati (es. _018 invece di _18).</p>
        <div class="scroll-box">
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Domanda</th>
                        <th>Codice Errato</th>
                        <th>→ Corretto</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wrong_analysis as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($item->name, 0, 40)); ?>...</td>
                        <td><code style="color: #e74c3c;"><?php echo $item->old_code; ?></code></td>
                        <td><code style="color: #27ae60;"><?php echo $item->new_code; ?></code></td>
                        <td>
                            <?php if ($item->can_fix): ?>
                            <span class="badge badge-success">Pronto</span>
                            <?php else: ?>
                            <span class="badge badge-warning">Competenza non trovata</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: #27ae60;">✅ Nessun codice errato trovato</p>
        <?php endif; ?>
    </div>

    <!-- RIEPILOGO E AZIONE -->
    <div class="panel" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border-color: #9b59b6;">
        <h3 style="color: #9b59b6;">📊 Riepilogo Operazioni</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number" style="color: #e74c3c;"><?php echo count($duplicates_to_delete); ?></div>
                <div class="label">Duplicati da eliminare</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?php echo count(array_filter($lbio_analysis, fn($x) => $x->can_map)); ?></div>
                <div class="label">LBIO da mappare</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo count(array_filter($wrong_analysis, fn($x) => $x->can_fix)); ?></div>
                <div class="label">Codici da correggere</div>
            </div>
        </div>
        
        <?php 
        $total_actions = count($duplicates_to_delete) + 
                         count(array_filter($lbio_analysis, fn($x) => $x->can_map)) + 
                         count(array_filter($wrong_analysis, fn($x) => $x->can_fix));
        ?>
        
        <?php if ($total_actions > 0): ?>
        <form method="post" action="fix_completo_chimfarm.php?courseid=<?php echo $courseid; ?>&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-primary">🚀 Esegui Tutte le Operazioni (<?php echo $total_actions; ?> azioni)</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
        <?php else: ?>
        <p style="color: #27ae60; font-weight: bold;">✅ Nessuna operazione necessaria - tutto OK!</p>
        <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Torna alla Dashboard</a>
        <?php endif; ?>
    </div>
</div>
<?php

echo $OUTPUT->footer();
