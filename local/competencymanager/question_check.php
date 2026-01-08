<?php
/**
 * Diagnostica Domande - Verifica struttura domande e competenze
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/question_check.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Verifica Domande e Competenze');
$PAGE->set_heading('Verifica Struttura Domande - ' . $course->shortname);

echo $OUTPUT->header();
?>

<style>
.check-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
.check-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.check-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.check-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
th { background: #34495e; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 300px; }
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
.comp-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
.comp-badge.assigned { background: #d4edda; color: #155724; }
.comp-badge.missing { background: #f8d7da; color: #721c24; }
</style>

<div class="check-container">

    <div class="check-header">
        <h2>🔍 Verifica Struttura Domande e Competenze</h2>
        <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
    </div>

<?php if ($questionid > 0): 
    $question = $DB->get_record('question', ['id' => $questionid]);
    if ($question):
?>

<div class="check-card">
    <h3>📝 Dettaglio Domanda ID: <?php echo $questionid; ?></h3>
    
    <h4>Informazioni Base</h4>
    <table>
        <tr><td><strong>ID</strong></td><td><?php echo $question->id; ?></td></tr>
        <tr><td><strong>Nome</strong></td><td><?php echo format_string($question->name); ?></td></tr>
        <tr><td><strong>Tipo</strong></td><td><?php echo $question->qtype; ?></td></tr>
        <tr><td><strong>Testo</strong></td><td><?php echo substr(strip_tags($question->questiontext), 0, 200); ?>...</td></tr>
        <tr><td><strong>Parent</strong></td><td><?php echo $question->parent; ?> <?php echo $question->parent == 0 ? '✅' : '⚠️ Ha un parent!'; ?></td></tr>
        <tr><td><strong>Hidden</strong></td><td><?php echo $question->hidden; ?> <?php echo $question->hidden == 0 ? '✅' : '⚠️ Nascosta!'; ?></td></tr>
    </table>
    
    <h4>🎯 Competenza Assegnata</h4>
    <?php
    $competency_assignment = $DB->get_record_sql("
        SELECT qcbq.*, c.idnumber, c.shortname, c.description, cf.shortname as framework_name
        FROM {qbank_competenciesbyquestion} qcbq
        JOIN {competency} c ON c.id = qcbq.competencyid
        JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
        WHERE qcbq.questionid = ?
    ", [$questionid]);
    
    if ($competency_assignment):
    ?>
    <p class="status-ok">✅ Competenza assegnata</p>
    <table>
        <tr><td><strong>Codice (idnumber)</strong></td><td><?php echo $competency_assignment->idnumber; ?></td></tr>
        <tr><td><strong>Nome</strong></td><td><?php echo $competency_assignment->shortname; ?></td></tr>
        <tr><td><strong>Framework</strong></td><td><?php echo $competency_assignment->framework_name; ?></td></tr>
        <tr><td><strong>Livello difficoltà</strong></td><td><?php echo str_repeat('⭐', $competency_assignment->difficultylevel ?? 1); ?></td></tr>
    </table>
    <?php else: ?>
    <p class="status-error">❌ Nessuna competenza assegnata a questa domanda!</p>
    <?php 
        // Prova a estrarre il codice dal nome
        if (preg_match('/([A-Z]+_[A-Z]+_\d+)/i', $question->name, $matches)) {
            $extracted_code = strtoupper($matches[1]);
            echo '<p class="status-warning">💡 Codice rilevato nel nome: <strong>' . $extracted_code . '</strong></p>';
            
            // Cerca se esiste questa competenza
            $found_comp = $DB->get_record('competency', ['idnumber' => $extracted_code]);
            if ($found_comp) {
                echo '<p class="status-ok">✅ Competenza trovata nel database! ID: ' . $found_comp->id . '</p>';
                echo '<p>Puoi assegnarla manualmente o usare lo script di assegnazione automatica.</p>';
            } else {
                echo '<p class="status-error">❌ Competenza con codice "' . $extracted_code . '" NON trovata nel database.</p>';
            }
        }
    ?>
    <?php endif; ?>
    
    <h4>Risposte (question_answers)</h4>
    <?php
    $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC');
    if (empty($answers)):
    ?>
    <p class="status-error">❌ NESSUNA RISPOSTA TROVATA!</p>
    <?php else: ?>
    <p class="status-ok">✅ <?php echo count($answers); ?> risposte trovate</p>
    <table>
        <tr><th>ID</th><th>Testo</th><th>Fraction</th><th>Corretta?</th></tr>
        <?php foreach ($answers as $ans): ?>
        <tr>
            <td><?php echo $ans->id; ?></td>
            <td><?php echo format_string(substr(strip_tags($ans->answer), 0, 80)); ?></td>
            <td><?php echo $ans->fraction; ?></td>
            <td><?php echo $ans->fraction == 1 ? '✅ Sì' : ''; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    
    <h4>Opzioni Multichoice</h4>
    <?php
    $mcoptions = $DB->get_record('qtype_multichoice_options', ['questionid' => $questionid]);
    if (!$mcoptions):
    ?>
    <p class="status-warning">⚠️ Nessuna opzione multichoice (normale se non è multichoice)</p>
    <?php else: ?>
    <p class="status-ok">✅ Opzioni trovate</p>
    <?php endif; ?>
    
    <p style="margin-top: 20px;">
        <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Torna alla lista</a>
    </p>
</div>

<?php endif; endif; ?>

<div class="check-card">
    <h3>📋 Ultime 50 Domande del Corso</h3>
    
    <?php
    // Query che include le competenze
    $questions = $DB->get_records_sql("
        SELECT q.id, q.name, q.qtype, q.questiontext,
               (SELECT COUNT(*) FROM {question_answers} qa WHERE qa.question = q.id) as answer_count,
               (SELECT COUNT(*) FROM {qtype_multichoice_options} qmo WHERE qmo.questionid = q.id) as mc_options,
               qcbq.competencyid,
               qcbq.difficultylevel,
               c.idnumber as comp_code,
               c.shortname as comp_name
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
        LEFT JOIN {competency} c ON c.id = qcbq.competencyid
        WHERE qc.contextid = ?
        AND q.parent = 0
        ORDER BY q.id DESC
        LIMIT 50
    ", [$context->id]);
    
    // Calcola statistiche
    $total = count($questions);
    $with_comp = 0;
    $without_comp = 0;
    $with_answers = 0;
    
    foreach ($questions as $q) {
        if (!empty($q->competencyid)) $with_comp++;
        else $without_comp++;
        if ($q->answer_count > 0) $with_answers++;
    }
    ?>
    
    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-box info">
            <div class="number"><?php echo $total; ?></div>
            <div class="label">Domande Totali</div>
        </div>
        <div class="stat-box <?php echo $with_comp > 0 ? 'success' : 'danger'; ?>">
            <div class="number"><?php echo $with_comp; ?></div>
            <div class="label">✅ Con Competenza</div>
        </div>
        <div class="stat-box <?php echo $without_comp > 0 ? 'danger' : 'success'; ?>">
            <div class="number"><?php echo $without_comp; ?></div>
            <div class="label">❌ Senza Competenza</div>
        </div>
        <div class="stat-box <?php echo $with_answers == $total ? 'success' : 'danger'; ?>">
            <div class="number"><?php echo $with_answers; ?></div>
            <div class="label">Con Risposte</div>
        </div>
    </div>
    
    <?php if ($without_comp > 0): ?>
    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <strong>⚠️ Attenzione:</strong> <?php echo $without_comp; ?> domande non hanno una competenza assegnata. 
        Le competenze potrebbero non essere state assegnate durante l'import.
    </div>
    <?php endif; ?>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Risposte</th>
            <th>MC Opt</th>
            <th>Competenza</th>
            <th>Livello</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
        <?php foreach ($questions as $q): 
            $hasAnswers = $q->answer_count > 0;
            $hasMcOptions = $q->mc_options > 0 || $q->qtype != 'multichoice';
            $hasCompetency = !empty($q->competencyid);
            $isOk = $hasAnswers && $hasMcOptions;
        ?>
        <tr>
            <td><?php echo $q->id; ?></td>
            <td title="<?php echo htmlspecialchars($q->name); ?>"><?php echo format_string(substr($q->name, 0, 35)); ?><?php echo strlen($q->name) > 35 ? '...' : ''; ?></td>
            <td><?php echo $q->qtype; ?></td>
            <td class="<?php echo $hasAnswers ? 'status-ok' : 'status-error'; ?>">
                <?php echo $q->answer_count; ?> <?php echo $hasAnswers ? '✅' : '❌'; ?>
            </td>
            <td class="<?php echo $hasMcOptions ? 'status-ok' : 'status-error'; ?>">
                <?php echo $q->mc_options; ?> <?php echo $hasMcOptions ? '✅' : '❌'; ?>
            </td>
            <td>
                <?php if ($hasCompetency): ?>
                <span class="comp-badge assigned" title="<?php echo $q->comp_name; ?>"><?php echo $q->comp_code; ?></span>
                <?php else: ?>
                <span class="comp-badge missing">Nessuna</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($hasCompetency && $q->difficultylevel): ?>
                <?php echo str_repeat('⭐', $q->difficultylevel); ?>
                <?php else: ?>
                -
                <?php endif; ?>
            </td>
            <td class="<?php echo $isOk ? 'status-ok' : 'status-error'; ?>">
                <?php echo $isOk ? '✅' : '❌'; ?>
            </td>
            <td>
                <a href="?courseid=<?php echo $courseid; ?>&questionid=<?php echo $q->id; ?>">Dettagli</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<p style="margin-top: 20px;">
    <a href="diagnostics.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Diagnostica Quiz</a>
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🏠 Dashboard</a>
</p>

</div>

<?php
echo $OUTPUT->footer();
