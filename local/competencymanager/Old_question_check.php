<?php
/**
 * Diagnostica Domande - Verifica struttura domande
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
$PAGE->set_title('Verifica Domande');
$PAGE->set_heading('Verifica Struttura Domande');

echo $OUTPUT->header();
?>

<style>
.check-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
.check-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.status-ok { color: #27ae60; }
.status-error { color: #e74c3c; }
.status-warning { color: #f39c12; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
th { background: #34495e; color: white; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 300px; }
</style>

<div class="check-container">

<h2>🔍 Verifica Struttura Domande</h2>

<?php if ($questionid > 0): 
    $question = $DB->get_record('question', ['id' => $questionid]);
    if ($question):
?>

<div class="check-card">
    <h3>📝 Domanda ID: <?php echo $questionid; ?></h3>
    
    <h4>Informazioni Base</h4>
    <table>
        <tr><td><strong>ID</strong></td><td><?php echo $question->id; ?></td></tr>
        <tr><td><strong>Nome</strong></td><td><?php echo format_string($question->name); ?></td></tr>
        <tr><td><strong>Tipo</strong></td><td><?php echo $question->qtype; ?></td></tr>
        <tr><td><strong>Testo</strong></td><td><?php echo substr(strip_tags($question->questiontext), 0, 200); ?>...</td></tr>
        <tr><td><strong>Parent</strong></td><td><?php echo $question->parent; ?> <?php echo $question->parent == 0 ? '✅' : '⚠️ Ha un parent!'; ?></td></tr>
        <tr><td><strong>Hidden</strong></td><td><?php echo $question->hidden; ?> <?php echo $question->hidden == 0 ? '✅' : '⚠️ Nascosta!'; ?></td></tr>
    </table>
    
    <h4>Risposte (question_answers)</h4>
    <?php
    $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC');
    if (empty($answers)):
    ?>
    <p class="status-error">❌ NESSUNA RISPOSTA TROVATA! Questo è il problema.</p>
    <?php else: ?>
    <p class="status-ok">✅ <?php echo count($answers); ?> risposte trovate</p>
    <table>
        <tr><th>ID</th><th>Testo</th><th>Fraction</th><th>Feedback</th></tr>
        <?php foreach ($answers as $ans): ?>
        <tr>
            <td><?php echo $ans->id; ?></td>
            <td><?php echo format_string(substr(strip_tags($ans->answer), 0, 100)); ?></td>
            <td><?php echo $ans->fraction; ?> <?php echo $ans->fraction == 1 ? '✅ Corretta' : ''; ?></td>
            <td><?php echo substr(strip_tags($ans->feedback), 0, 50); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    
    <h4>Opzioni Multichoice (qtype_multichoice_options)</h4>
    <?php
    $mcoptions = $DB->get_record('qtype_multichoice_options', ['questionid' => $questionid]);
    if (!$mcoptions):
    ?>
    <p class="status-error">❌ NESSUNA OPZIONE MULTICHOICE! Per domande multichoice questo è obbligatorio.</p>
    <?php else: ?>
    <p class="status-ok">✅ Opzioni trovate</p>
    <table>
        <tr><td><strong>Single answer</strong></td><td><?php echo $mcoptions->single; ?> (1=singola, 0=multipla)</td></tr>
        <tr><td><strong>Shuffle answers</strong></td><td><?php echo $mcoptions->shuffleanswers; ?></td></tr>
        <tr><td><strong>Answer numbering</strong></td><td><?php echo $mcoptions->answernumbering; ?></td></tr>
        <tr><td><strong>Show standard instruction</strong></td><td><?php echo $mcoptions->showstandardinstruction ?? 'N/A'; ?></td></tr>
    </table>
    <?php endif; ?>
    
    <h4>Question Version</h4>
    <?php
    $qv = $DB->get_record_sql("
        SELECT qv.*, qbe.id as bankentryid, qbe.questioncategoryid
        FROM {question_versions} qv
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qv.questionid = ?
    ", [$questionid]);
    if ($qv):
    ?>
    <table>
        <tr><td><strong>Version</strong></td><td><?php echo $qv->version; ?></td></tr>
        <tr><td><strong>Status</strong></td><td><?php echo $qv->status; ?> <?php echo $qv->status == 'ready' ? '✅' : '⚠️'; ?></td></tr>
        <tr><td><strong>Bank Entry ID</strong></td><td><?php echo $qv->bankentryid; ?></td></tr>
        <tr><td><strong>Category ID</strong></td><td><?php echo $qv->questioncategoryid; ?></td></tr>
    </table>
    <?php else: ?>
    <p class="status-error">❌ Nessuna versione trovata!</p>
    <?php endif; ?>
    
    <h4>Raw Data</h4>
    <pre><?php print_r($question); ?></pre>
    
</div>

<?php endif; endif; ?>

<div class="check-card">
    <h3>📋 Ultime 20 Domande del Corso</h3>
    <?php
    $questions = $DB->get_records_sql("
        SELECT q.id, q.name, q.qtype, q.questiontext,
               (SELECT COUNT(*) FROM {question_answers} qa WHERE qa.question = q.id) as answer_count,
               (SELECT COUNT(*) FROM {qtype_multichoice_options} qmo WHERE qmo.questionid = q.id) as mc_options
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        AND q.parent = 0
        ORDER BY q.id DESC
        LIMIT 20
    ", [$context->id]);
    ?>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Risposte</th>
            <th>MC Options</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
        <?php foreach ($questions as $q): 
            $hasAnswers = $q->answer_count > 0;
            $hasMcOptions = $q->mc_options > 0 || $q->qtype != 'multichoice';
            $isOk = $hasAnswers && $hasMcOptions;
        ?>
        <tr>
            <td><?php echo $q->id; ?></td>
            <td><?php echo format_string(substr($q->name, 0, 40)); ?></td>
            <td><?php echo $q->qtype; ?></td>
            <td class="<?php echo $hasAnswers ? 'status-ok' : 'status-error'; ?>">
                <?php echo $q->answer_count; ?> <?php echo $hasAnswers ? '✅' : '❌'; ?>
            </td>
            <td class="<?php echo $hasMcOptions ? 'status-ok' : 'status-error'; ?>">
                <?php echo $q->mc_options; ?> <?php echo $hasMcOptions ? '✅' : '❌'; ?>
            </td>
            <td class="<?php echo $isOk ? 'status-ok' : 'status-error'; ?>">
                <?php echo $isOk ? '✅ OK' : '❌ PROBLEMA'; ?>
            </td>
            <td>
                <a href="?courseid=<?php echo $courseid; ?>&questionid=<?php echo $q->id; ?>">Dettagli</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<p>
    <a href="diagnostics.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Diagnostica Quiz</a>
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🏠 Dashboard</a>
</p>

</div>

<?php
echo $OUTPUT->footer();
