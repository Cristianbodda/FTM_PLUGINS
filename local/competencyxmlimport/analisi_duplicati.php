<?php
/**
 * ANALISI E PULIZIA DOMANDE DUPLICATE
 * 
 * Trova domande con lo stesso prefisso (es. CHIM_BASE_Q01) 
 * e propone quali eliminare (versioni senza competenza o legacy)
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'analyze', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/analisi_duplicati.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Analisi Duplicati - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Recupera tutte le domande del corso con info competenze
$sql = "SELECT q.id, q.name, q.qtype, qbe.id as entryid,
               qbc.competencyid, c.idnumber as comp_code, c.shortname as comp_name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
        LEFT JOIN {competency} c ON c.id = qbc.competencyid
        WHERE qc.contextid = ?
        ORDER BY q.name";

$all_questions = $DB->get_records_sql($sql, [$context->id]);

// Raggruppa per prefisso (es. CHIM_BASE_Q01)
$groups = [];
foreach ($all_questions as $q) {
    // Estrai prefisso: tutto prima del " - " 
    if (preg_match('/^([A-Z_]+_Q\d+)/', $q->name, $m)) {
        $prefix = $m[1];
    } else {
        // Fallback: usa il nome completo
        $prefix = $q->name;
    }
    
    if (!isset($groups[$prefix])) {
        $groups[$prefix] = [];
    }
    $groups[$prefix][] = $q;
}

// Analizza i gruppi
$duplicates = [];      // Gruppi con più di 1 domanda
$singles = [];         // Domande singole (no duplicati)
$to_delete = [];       // Domande candidate per eliminazione

foreach ($groups as $prefix => $questions) {
    if (count($questions) > 1) {
        // Gruppo con duplicati
        $group_info = [
            'prefix' => $prefix,
            'count' => count($questions),
            'questions' => [],
            'has_good' => false,
            'has_bad' => false
        ];
        
        foreach ($questions as $q) {
            $is_good = !empty($q->comp_code) && strpos($q->comp_code, 'CHIMFARM_') === 0;
            $is_legacy = strpos($q->name, 'CHIMICA_') !== false;
            
            $q_info = [
                'id' => $q->id,
                'name' => $q->name,
                'entryid' => $q->entryid,
                'comp_code' => $q->comp_code ?: '-',
                'comp_name' => $q->comp_name ?: '-',
                'is_good' => $is_good,
                'is_legacy' => $is_legacy,
                'delete_candidate' => !$is_good || $is_legacy
            ];
            
            $group_info['questions'][] = $q_info;
            
            if ($is_good && !$is_legacy) {
                $group_info['has_good'] = true;
            }
            if (!$is_good || $is_legacy) {
                $group_info['has_bad'] = true;
            }
        }
        
        // Se il gruppo ha almeno una versione buona, proponi di eliminare le cattive
        if ($group_info['has_good'] && $group_info['has_bad']) {
            foreach ($group_info['questions'] as $q_info) {
                if ($q_info['delete_candidate']) {
                    $to_delete[] = $q_info;
                }
            }
        }
        
        $duplicates[$prefix] = $group_info;
    } else {
        $singles[$prefix] = $questions[0];
    }
}

// Ordina duplicati per numero di elementi (decrescente)
uasort($duplicates, function($a, $b) {
    return $b['count'] - $a['count'];
});

// CSS
$css = '<style>
.dup-page { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.dup-header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.dup-header h2 { margin: 0 0 10px 0; }
.panel { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #eee; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 10px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; text-transform: uppercase; }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.stat-card.orange { background: linear-gradient(135deg, #e67e22, #d35400); color: white; }
.group-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
.group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.group-prefix { font-family: monospace; font-weight: 600; font-size: 14px; background: #e3f2fd; color: #1565c0; padding: 5px 10px; border-radius: 5px; }
.group-count { background: #ffebee; color: #c62828; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #f1f1f1; font-weight: 600; }
.code { font-family: monospace; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.code-good { background: #e8f5e9; color: #2e7d32; }
.code-bad { background: #ffebee; color: #c62828; }
.code-none { background: #f5f5f5; color: #999; }
.row-good { background: #f1f8e9; }
.row-bad { background: #fff8e1; }
.row-delete { background: #ffebee; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; margin-left: 5px; }
.badge-keep { background: #c8e6c9; color: #2e7d32; }
.badge-delete { background: #ffcdd2; color: #c62828; }
.badge-legacy { background: #ffe0b2; color: #e65100; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 10px; border: none; cursor: pointer; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn-primary { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn:hover { opacity: 0.9; }
.success-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.warning-msg { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.back-link { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; }
.scrollable { max-height: 600px; overflow-y: auto; }
.legend { display: flex; gap: 20px; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; }
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 12px; }
.legend-color { width: 20px; height: 20px; border-radius: 4px; }
</style>';

echo $OUTPUT->header();
echo $css;

?>
<div class="dup-page">
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="back-link">← Torna all'Audit</a>
    
    <div class="dup-header">
        <h2>🔍 Analisi Domande Duplicate</h2>
        <p>Identifica e propone l'eliminazione di domande duplicate o con codici legacy</p>
    </div>

<?php if ($action == 'delete' && !empty($to_delete)): 
    if ($confirm == 1) {
        // Esegui eliminazione
        $deleted = 0;
        $errors_del = [];
        
        foreach ($to_delete as $q) {
            try {
                // Elimina associazione competenza se esiste
                $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $q['id']]);
                
                // Elimina la domanda (questo elimina anche versioni e riferimenti)
                question_delete_question($q['id']);
                $deleted++;
            } catch (Exception $e) {
                $errors_del[] = $q['name'] . ': ' . $e->getMessage();
            }
        }
        ?>
        <div class="success-msg">
            ✅ <strong>Completato!</strong> <?php echo $deleted; ?> domande eliminate.
            <?php if (!empty($errors_del)): ?>
            <br><br>⚠️ Errori: <br><?php echo implode('<br>', $errors_del); ?>
            <?php endif; ?>
        </div>
        
        <a href="analisi_duplicati.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🔄 Rianalizza</a>
        <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="btn btn-primary">📊 Torna all'Audit</a>
        <?php
    } else {
        // Mostra conferma
        ?>
        <div class="warning-msg">
            ⚠️ <strong>ATTENZIONE!</strong> Stai per eliminare <?php echo count($to_delete); ?> domande.<br>
            Questa azione è <strong>IRREVERSIBILE</strong>.
        </div>
        
        <div class="panel">
            <h3>📋 Domande che verranno eliminate</h3>
            <table>
                <thead>
                    <tr><th>#</th><th>Nome Domanda</th><th>Competenza</th><th>Motivo</th></tr>
                </thead>
                <tbody>
                <?php foreach ($to_delete as $i => $q): ?>
                    <tr class="row-delete">
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($q['name']); ?></td>
                        <td><span class="code code-bad"><?php echo $q['comp_code']; ?></span></td>
                        <td><?php echo $q['is_legacy'] ? 'Codice legacy (CHIMICA_*)' : 'Senza competenza valida'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px;">
                <a href="?courseid=<?php echo $courseid; ?>&action=delete&confirm=1" 
                   class="btn btn-danger"
                   onclick="return confirm('ULTIMA CONFERMA: Eliminare definitivamente <?php echo count($to_delete); ?> domande?');">
                    🗑️ CONFERMA ELIMINAZIONE
                </a>
                <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">
                    ❌ Annulla
                </a>
            </div>
        </div>
        <?php
    }

else: ?>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo count($all_questions); ?></div>
            <div class="label">Domande Totali</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo count($duplicates); ?></div>
            <div class="label">Gruppi Duplicati</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo count($to_delete); ?></div>
            <div class="label">Da Eliminare</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo count($singles); ?></div>
            <div class="label">Domande Uniche</div>
        </div>
    </div>

    <!-- Legenda -->
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background:#e8f5e9;"></div>
            <span>✅ Versione buona (CHIMFARM_*) - DA TENERE</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background:#ffebee;"></div>
            <span>❌ Versione legacy/senza comp. - DA ELIMINARE</span>
        </div>
    </div>

    <?php if (!empty($to_delete)): ?>
    <div class="panel" style="border: 2px solid #e74c3c;">
        <h3>🗑️ Azione Consigliata</h3>
        <p>Trovate <strong><?php echo count($to_delete); ?></strong> domande duplicate/legacy che possono essere eliminate in sicurezza.</p>
        <p>Ogni gruppo mantiene almeno una versione con competenza CHIMFARM valida.</p>
        <a href="?courseid=<?php echo $courseid; ?>&action=delete" class="btn btn-danger">
            🗑️ Elimina <?php echo count($to_delete); ?> Domande Duplicate
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($duplicates)): ?>
    <div class="panel">
        <h3>📋 Gruppi con Duplicati (<?php echo count($duplicates); ?>)</h3>
        <div class="scrollable">
        <?php foreach ($duplicates as $prefix => $group): ?>
            <div class="group-card">
                <div class="group-header">
                    <span class="group-prefix"><?php echo htmlspecialchars($prefix); ?></span>
                    <span class="group-count"><?php echo $group['count']; ?> versioni</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Nome Completo</th><th>Competenza</th><th>Stato</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['questions'] as $q): 
                        $row_class = $q['is_good'] && !$q['is_legacy'] ? 'row-good' : 'row-delete';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo htmlspecialchars($q['name']); ?></td>
                            <td>
                                <?php if ($q['comp_code'] != '-'): ?>
                                <span class="code <?php echo $q['is_good'] ? 'code-good' : 'code-bad'; ?>">
                                    <?php echo $q['comp_code']; ?>
                                </span>
                                <?php else: ?>
                                <span class="code code-none">Nessuna</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($q['is_good'] && !$q['is_legacy']): ?>
                                <span class="badge badge-keep">✅ TENERE</span>
                                <?php elseif ($q['is_legacy']): ?>
                                <span class="badge badge-legacy">⚠️ LEGACY</span>
                                <span class="badge badge-delete">🗑️ ELIMINARE</span>
                                <?php else: ?>
                                <span class="badge badge-delete">🗑️ ELIMINARE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($duplicates)): ?>
    <div class="panel">
        <div class="success-msg">
            ✅ <strong>Nessun duplicato trovato!</strong> Tutte le domande sono uniche.
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
