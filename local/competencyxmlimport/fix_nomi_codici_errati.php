<?php
/**
 * FIX CODICI ERRATI NEI NOMI DOMANDE
 * 
 * Problema: Domande con codici come CHIMFARM_6P_010, 6P_013, 7S_018
 * che non esistono nel framework (max 6P_07 e 7S_10)
 * 
 * Soluzione: Correggere il nome della domanda con il codice corretto
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 9, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/fix_nomi_codici_errati.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Nomi Codici Errati - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Mappatura codici errati → codici corretti
// Basata sull'analisi: i codici con 3 cifre (010, 013, etc.) devono diventare 2 cifre
$mapping = [
    // 6P errati (framework ha solo 01-07)
    'CHIMFARM_6P_010' => 'CHIMFARM_6P_01',
    'CHIMFARM_6P_013' => 'CHIMFARM_6P_03',
    'CHIMFARM_6P_015' => 'CHIMFARM_6P_05',
    'CHIMFARM_6P_016' => 'CHIMFARM_6P_06',
    'CHIMFARM_6P_018' => 'CHIMFARM_6P_03', // 08 non esiste, mappo a 03
    'CHIMFARM_6P_020' => 'CHIMFARM_6P_02',
    'CHIMFARM_6P_021' => 'CHIMFARM_6P_05',
    'CHIMFARM_6P_022' => 'CHIMFARM_6P_02',
    'CHIMFARM_6P_023' => 'CHIMFARM_6P_05',
    'CHIMFARM_6P_024' => 'CHIMFARM_6P_05',
    'CHIMFARM_6P_025' => 'CHIMFARM_6P_04',
    // 7S errati (framework ha solo 01-10)
    'CHIMFARM_7S_018' => 'CHIMFARM_7S_08',
];

// Carica competenze del framework
$competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber, shortname');
$comp_map = [];
foreach ($competencies as $c) {
    $comp_map[$c->idnumber] = $c->id;
}

// Trova domande con codici errati
$sql = "SELECT DISTINCT q.id, q.name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        ORDER BY q.name";

$all_questions = $DB->get_records_sql($sql, [$context->id]);

// Analizza e trova domande da correggere
$to_fix = [];
$not_mapped = [];

foreach ($all_questions as $q) {
    foreach ($mapping as $wrong => $correct) {
        if (strpos($q->name, $wrong) !== false) {
            // Verifica che il codice corretto esista
            if (isset($comp_map[$correct])) {
                $new_name = str_replace($wrong, $correct, $q->name);
                $to_fix[] = [
                    'id' => $q->id,
                    'old_name' => $q->name,
                    'new_name' => $new_name,
                    'wrong_code' => $wrong,
                    'correct_code' => $correct,
                    'comp_id' => $comp_map[$correct]
                ];
            } else {
                $not_mapped[] = [
                    'name' => $q->name,
                    'wrong_code' => $wrong,
                    'suggested' => $correct,
                    'error' => 'Codice corretto non trovato nel framework'
                ];
            }
            break; // Una sola correzione per domanda
        }
    }
}

// CSS
$css = '<style>
.fix-page { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.fix-header { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.fix-header h2 { margin: 0 0 10px 0; }
.panel { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #eee; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 10px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; text-transform: uppercase; }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
.code { font-family: monospace; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.code-wrong { background: #ffebee; color: #c62828; text-decoration: line-through; }
.code-correct { background: #e8f5e9; color: #2e7d32; }
.name-cell { max-width: 350px; word-break: break-all; }
.arrow { color: #666; font-weight: bold; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 10px; border: none; cursor: pointer; }
.btn-primary { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn:hover { opacity: 0.9; }
.success-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.warning-msg { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.back-link { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; }
.mapping-table { margin-bottom: 20px; }
.mapping-table td { padding: 5px 10px; }
.scrollable { max-height: 500px; overflow-y: auto; }
</style>';

echo $OUTPUT->header();
echo $css;

?>
<div class="fix-page">
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="back-link">← Torna all'Audit</a>
    
    <div class="fix-header">
        <h2>🔤 Fix Nomi Domande - Codici Errati</h2>
        <p>Corregge i codici competenza errati nei nomi delle domande (es. 6P_010 → 6P_01)</p>
    </div>

<?php if ($action == 'apply' && !empty($to_fix)): 
    // Applica le correzioni
    $fixed_names = 0;
    $fixed_comp = 0;
    $errors_apply = [];
    
    foreach ($to_fix as $item) {
        try {
            // 1. Aggiorna il nome della domanda
            $DB->set_field('question', 'name', $item['new_name'], ['id' => $item['id']]);
            $fixed_names++;
            
            // 2. Aggiorna/Inserisci la competenza
            $existing = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $item['id']]);
            if ($existing) {
                if ($existing->competencyid != $item['comp_id']) {
                    $DB->update_record('qbank_competenciesbyquestion', [
                        'id' => $existing->id,
                        'competencyid' => $item['comp_id']
                    ]);
                    $fixed_comp++;
                }
            } else {
                $DB->insert_record('qbank_competenciesbyquestion', [
                    'questionid' => $item['id'],
                    'competencyid' => $item['comp_id'],
                    'difficultylevel' => 1
                ]);
                $fixed_comp++;
            }
        } catch (Exception $e) {
            $errors_apply[] = $item['old_name'] . ': ' . $e->getMessage();
        }
    }
?>
    <div class="success-msg">
        ✅ <strong>Completato!</strong><br>
        📝 <?php echo $fixed_names; ?> nomi domande corretti<br>
        🔗 <?php echo $fixed_comp; ?> competenze aggiornate
        <?php if (!empty($errors_apply)): ?>
        <br><br>⚠️ Errori: <?php echo implode(', ', $errors_apply); ?>
        <?php endif; ?>
    </div>
    
    <a href="fix_nomi_codici_errati.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🔄 Verifica Risultato</a>
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="btn btn-primary">📊 Torna all'Audit</a>
    <a href="fix_competenze_da_nome.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🔧 Verifica Fix Competenze</a>

<?php else: ?>

    <!-- Mostra la tabella di mappatura -->
    <div class="panel">
        <h3>📋 Tabella di Mappatura Utilizzata</h3>
        <p style="color:#666; margin-bottom:15px;">Questi sono i codici errati e la loro correzione:</p>
        <table class="mapping-table" style="width:auto;">
            <tr><th>Codice Errato</th><th></th><th>Codice Corretto</th></tr>
            <?php foreach ($mapping as $wrong => $correct): ?>
            <tr>
                <td><span class="code code-wrong"><?php echo $wrong; ?></span></td>
                <td class="arrow">→</td>
                <td><span class="code code-correct"><?php echo $correct; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="number"><?php echo count($to_fix); ?></div>
            <div class="label">Domande da Correggere</div>
        </div>
        <div class="stat-card <?php echo count($not_mapped) > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo count($not_mapped); ?></div>
            <div class="label">Non Mappabili</div>
        </div>
    </div>

    <?php if (!empty($to_fix)): ?>
    <div class="panel">
        <h3>📝 Domande da Correggere (<?php echo count($to_fix); ?>)</h3>
        <div class="scrollable">
        <table>
            <thead>
                <tr>
                    <th>Nome Attuale</th>
                    <th>Codice</th>
                    <th></th>
                    <th>Nuovo Nome</th>
                    <th>Codice</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($to_fix as $item): ?>
                <tr>
                    <td class="name-cell"><?php echo htmlspecialchars($item['old_name']); ?></td>
                    <td><span class="code code-wrong"><?php echo $item['wrong_code']; ?></span></td>
                    <td class="arrow">→</td>
                    <td class="name-cell"><?php echo htmlspecialchars($item['new_name']); ?></td>
                    <td><span class="code code-correct"><?php echo $item['correct_code']; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="?courseid=<?php echo $courseid; ?>&action=apply&frameworkid=<?php echo $frameworkid; ?>" 
               class="btn btn-primary"
               onclick="return confirm('Confermi la correzione di <?php echo count($to_fix); ?> nomi domande?\n\nQuesta azione modificherà i nomi delle domande nel database.');">
                ✅ Applica Correzioni (<?php echo count($to_fix); ?> domande)
            </a>
            <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="btn btn-secondary">
                ❌ Annulla
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($not_mapped)): ?>
    <div class="panel">
        <h3>⚠️ Non Mappabili (<?php echo count($not_mapped); ?>)</h3>
        <div class="warning-msg">
            Queste domande hanno codici errati ma la mappatura proposta non è nel framework.
        </div>
        <table>
            <thead>
                <tr><th>Nome Domanda</th><th>Codice Errato</th><th>Problema</th></tr>
            </thead>
            <tbody>
            <?php foreach ($not_mapped as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><span class="code code-wrong"><?php echo $item['wrong_code']; ?></span></td>
                    <td style="color:#c62828;"><?php echo $item['error']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($to_fix) && empty($not_mapped)): ?>
    <div class="panel">
        <div class="success-msg">
            ✅ <strong>Tutto OK!</strong> Nessuna domanda con codici errati trovata.
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
