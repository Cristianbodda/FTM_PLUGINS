<?php
/**
 * FIX CHIMFARM_BASE_Qxx
 * 
 * Problema: Le domande con nome "CHIMFARM_BASE_Q01 - CHIMFARM_1C_02"
 * vengono estratte come "CHIMFARM_BASE_Q01" invece di "CHIMFARM_1C_02"
 * 
 * Soluzione: Estrarre il codice competenza DOPO il trattino
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 9, PARAM_INT); // Default: FTM-01

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/fix_chimfarm_base.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix CHIMFARM_BASE - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Carica competenze del framework
$competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber, shortname');
$comp_map = [];
foreach ($competencies as $c) {
    $comp_map[$c->idnumber] = $c->id;
}

// Trova domande CHIMFARM_BASE_Qxx
$sql = "SELECT DISTINCT q.id, q.name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        AND q.name LIKE 'CHIMFARM_BASE_Q%'
        ORDER BY q.name";

$questions = $DB->get_records_sql($sql, [$context->id]);

// Analizza ogni domanda
$to_fix = [];
$already_ok = [];
$errors = [];

foreach ($questions as $q) {
    // Estrai il codice DOPO il trattino
    // Es: "CHIMFARM_BASE_Q01 - CHIMFARM_1C_02" → "CHIMFARM_1C_02"
    if (preg_match('/CHIMFARM_BASE_Q\d+\s*-\s*(CHIMFARM_[A-Z0-9]+_\d+)/', $q->name, $matches)) {
        $code = $matches[1];
        
        // Verifica se esiste nel framework
        if (isset($comp_map[$code])) {
            $comp_id = $comp_map[$code];
            
            // Verifica se già assegnata
            $existing = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $q->id]);
            
            if ($existing) {
                if ($existing->competencyid == $comp_id) {
                    $already_ok[] = [
                        'question' => $q->name,
                        'code' => $code,
                        'status' => 'Già OK'
                    ];
                } else {
                    // Ha competenza sbagliata
                    $old_comp = $DB->get_record('competency', ['id' => $existing->competencyid]);
                    $to_fix[] = [
                        'question_id' => $q->id,
                        'question_name' => $q->name,
                        'code' => $code,
                        'comp_id' => $comp_id,
                        'old_code' => $old_comp ? $old_comp->idnumber : '?',
                        'existing_id' => $existing->id
                    ];
                }
            } else {
                // Non ha competenza - da aggiungere
                $to_fix[] = [
                    'question_id' => $q->id,
                    'question_name' => $q->name,
                    'code' => $code,
                    'comp_id' => $comp_id,
                    'old_code' => null,
                    'existing_id' => null
                ];
            }
        } else {
            $errors[] = [
                'question' => $q->name,
                'code' => $code,
                'error' => 'Codice non trovato nel framework'
            ];
        }
    } else {
        $errors[] = [
            'question' => $q->name,
            'code' => '-',
            'error' => 'Formato nome non riconosciuto'
        ];
    }
}

// CSS
$css = '<style>
.fix-page { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.fix-header { background: linear-gradient(135deg, #e67e22, #d35400); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.fix-header h2 { margin: 0 0 10px 0; }
.panel { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #eee; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 10px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; text-transform: uppercase; }
.stat-card.orange { background: linear-gradient(135deg, #e67e22, #d35400); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; font-weight: 600; }
.code { font-family: monospace; background: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 4px; }
.code-old { background: #ffebee; color: #c62828; }
.code-new { background: #e3f2fd; color: #1565c0; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 10px; }
.btn-primary { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn:hover { opacity: 0.9; }
.success-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.arrow { color: #666; margin: 0 8px; }
.back-link { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; }
</style>';

echo $OUTPUT->header();
echo $css;

?>
<div class="fix-page">
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="back-link">← Torna all'Audit</a>
    
    <div class="fix-header">
        <h2>🔧 Fix Domande CHIMFARM_BASE_Qxx</h2>
        <p>Corregge l'estrazione del codice competenza per le domande con formato errato</p>
    </div>

<?php if ($action == 'apply' && !empty($to_fix)): 
    // Applica le correzioni
    $fixed = 0;
    $errors_apply = [];
    
    foreach ($to_fix as $item) {
        try {
            if ($item['existing_id']) {
                // Aggiorna esistente
                $DB->update_record('qbank_competenciesbyquestion', [
                    'id' => $item['existing_id'],
                    'competencyid' => $item['comp_id']
                ]);
            } else {
                // Inserisci nuovo
                $DB->insert_record('qbank_competenciesbyquestion', [
                    'questionid' => $item['question_id'],
                    'competencyid' => $item['comp_id'],
                    'difficultylevel' => 1
                ]);
            }
            $fixed++;
        } catch (Exception $e) {
            $errors_apply[] = $item['question_name'] . ': ' . $e->getMessage();
        }
    }
?>
    <div class="success-msg">
        ✅ <strong>Completato!</strong> <?php echo $fixed; ?> domande corrette.
        <?php if (!empty($errors_apply)): ?>
        <br><br>⚠️ Errori: <?php echo implode(', ', $errors_apply); ?>
        <?php endif; ?>
    </div>
    
    <a href="fix_chimfarm_base.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🔄 Verifica Risultato</a>
    <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="btn btn-primary">📊 Torna all'Audit</a>

<?php else: ?>
    
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo count($questions); ?></div>
            <div class="label">Domande Trovate</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo count($to_fix); ?></div>
            <div class="label">Da Correggere</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo count($already_ok); ?></div>
            <div class="label">Già OK</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo count($errors); ?></div>
            <div class="label">Errori</div>
        </div>
    </div>

    <?php if (!empty($to_fix)): ?>
    <div class="panel">
        <h3>📝 Domande da Correggere (<?php echo count($to_fix); ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>Nome Domanda</th>
                    <th>Codice Attuale</th>
                    <th></th>
                    <th>Codice Corretto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($to_fix as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['question_name']); ?></td>
                    <td>
                        <?php if ($item['old_code']): ?>
                        <span class="code code-old"><?php echo $item['old_code']; ?></span>
                        <?php else: ?>
                        <em style="color:#999;">Nessuna</em>
                        <?php endif; ?>
                    </td>
                    <td class="arrow">→</td>
                    <td><span class="code code-new"><?php echo $item['code']; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <a href="?courseid=<?php echo $courseid; ?>&action=apply&frameworkid=<?php echo $frameworkid; ?>" 
               class="btn btn-primary"
               onclick="return confirm('Confermi la correzione di <?php echo count($to_fix); ?> domande?');">
                ✅ Applica Correzioni
            </a>
            <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=dashboard" class="btn btn-secondary">
                ❌ Annulla
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($already_ok)): ?>
    <div class="panel">
        <h3>✅ Già Corrette (<?php echo count($already_ok); ?>)</h3>
        <table>
            <thead>
                <tr><th>Nome Domanda</th><th>Codice</th><th>Stato</th></tr>
            </thead>
            <tbody>
            <?php foreach ($already_ok as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['question']); ?></td>
                    <td><span class="code"><?php echo $item['code']; ?></span></td>
                    <td>✅ <?php echo $item['status']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="panel">
        <h3>⚠️ Problemi (<?php echo count($errors); ?>)</h3>
        <table>
            <thead>
                <tr><th>Nome Domanda</th><th>Codice Estratto</th><th>Problema</th></tr>
            </thead>
            <tbody>
            <?php foreach ($errors as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['question']); ?></td>
                    <td><span class="code code-old"><?php echo $item['code']; ?></span></td>
                    <td style="color:#c62828;"><?php echo $item['error']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($to_fix) && empty($errors)): ?>
    <div class="panel">
        <div class="success-msg">
            ✅ <strong>Tutto OK!</strong> Nessuna domanda CHIMFARM_BASE_Qxx da correggere.
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
