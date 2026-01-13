<?php
/**
 * Debug Competenze - Verifica calcolo punteggi
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/debug_competencies.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Debug Competenze');
$PAGE->set_heading('Debug Calcolo Competenze');

echo $OUTPUT->header();
?>

<style>
.debug-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
.debug-header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.debug-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.debug-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 12px; }
th { background: #34495e; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 200px; }
.step { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; border-radius: 0 8px 8px 0; }
.step-title { font-weight: bold; color: #2980b9; margin-bottom: 10px; }
</style>

<div class="debug-container">

<div class="debug-header">
    <h2>🔧 Debug Calcolo Competenze</h2>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong> (ID: <?php echo $courseid; ?>)</p>
</div>

<?php
// Se non è specificato un utente, mostra la lista
if (!$userid) {
    $students = get_enrolled_users($context, 'mod/quiz:attempt');
    ?>
    <div class="debug-card">
        <h3>👥 Seleziona uno studente da analizzare</h3>
        <table>
            <tr><th>ID</th><th>Nome</th><th>Email</th><th>Azioni</th></tr>
            <?php foreach ($students as $student): ?>
            <tr>
                <td><?php echo $student->id; ?></td>
                <td><?php echo fullname($student); ?></td>
                <td><?php echo $student->email; ?></td>
                <td><a href="?courseid=<?php echo $courseid; ?>&userid=<?php echo $student->id; ?>" class="btn btn-primary btn-sm">Analizza</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
} else {
    $student = $DB->get_record('user', ['id' => $userid]);
    ?>
    
    <div class="debug-card">
        <h3>👤 Analisi per: <?php echo fullname($student); ?></h3>
        <p>Email: <?php echo $student->email; ?> | ID: <?php echo $userid; ?></p>
        <p><a href="?courseid=<?php echo $courseid; ?>">← Torna alla lista studenti</a></p>
    </div>

    <!-- STEP 1: Verifica tentativi quiz -->
    <div class="debug-card">
        <h3>📝 STEP 1: Tentativi Quiz</h3>
        
        <div class="step">
            <div class="step-title">Query: Cerco tutti i tentativi completati per questo studente nel corso</div>
        </div>
        
        <?php
        $attempts = $DB->get_records_sql("
            SELECT 
                qa.id as attemptid,
                qa.quiz,
                qa.userid,
                qa.attempt,
                qa.state,
                qa.timefinish,
                qa.sumgrades,
                q.name as quizname,
                q.sumgrades as quiz_maxgrade
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE qa.userid = :userid
            AND q.course = :courseid
            AND qa.state = 'finished'
            ORDER BY qa.timefinish DESC
        ", ['userid' => $userid, 'courseid' => $courseid]);
        
        if (empty($attempts)) {
            echo '<p class="status-error">❌ NESSUN TENTATIVO TROVATO!</p>';
            echo '<p>Lo studente non ha completato nessun quiz in questo corso.</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($attempts) . ' tentativi trovati</p>';
            echo '<table>';
            echo '<tr><th>Attempt ID</th><th>Quiz</th><th>Stato</th><th>Punteggio</th><th>Data</th></tr>';
            foreach ($attempts as $att) {
                echo '<tr>';
                echo '<td>' . $att->attemptid . '</td>';
                echo '<td>' . $att->quizname . ' (ID: ' . $att->quiz . ')</td>';
                echo '<td>' . $att->state . '</td>';
                echo '<td>' . round($att->sumgrades, 2) . ' / ' . round($att->quiz_maxgrade, 2) . '</td>';
                echo '<td>' . date('d/m/Y H:i', $att->timefinish) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <?php if (!empty($attempts)): 
        // Prendi il primo tentativo per analisi dettagliata
        $firstAttempt = reset($attempts);
    ?>
    
    <!-- STEP 2: Verifica domande del tentativo -->
    <div class="debug-card">
        <h3>❓ STEP 2: Domande del Tentativo (ID: <?php echo $firstAttempt->attemptid; ?>)</h3>
        
        <div class="step">
            <div class="step-title">Query: Cerco le domande e le risposte di questo tentativo</div>
        </div>
        
        <?php
        $questions = $DB->get_records_sql("
            SELECT 
                qat.id as attemptquestionid,
                qat.questionid,
                q.name as questionname,
                qat.slot,
                qat.maxmark,
                qat.minfraction,
                qat.maxfraction
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} q ON q.id = qat.questionid
            WHERE qa.id = :attemptid
            ORDER BY qat.slot
        ", ['attemptid' => $firstAttempt->attemptid]);
        
        if (empty($questions)) {
            echo '<p class="status-error">❌ NESSUNA DOMANDA TROVATA nel tentativo!</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($questions) . ' domande trovate</p>';
            echo '<table>';
            echo '<tr><th>Slot</th><th>Question ID</th><th>Nome</th><th>Max Mark</th></tr>';
            $count = 0;
            foreach ($questions as $q) {
                if ($count >= 10) {
                    echo '<tr><td colspan="4"><em>... e altre ' . (count($questions) - 10) . ' domande</em></td></tr>';
                    break;
                }
                echo '<tr>';
                echo '<td>' . $q->slot . '</td>';
                echo '<td>' . $q->questionid . '</td>';
                echo '<td>' . format_string(substr($q->questionname, 0, 50)) . '</td>';
                echo '<td>' . $q->maxmark . '</td>';
                echo '</tr>';
                $count++;
            }
            echo '</table>';
        }
        ?>
    </div>

    <!-- STEP 3: Verifica risposte con punteggi -->
    <div class="debug-card">
        <h3>📊 STEP 3: Risposte e Punteggi</h3>
        
        <div class="step">
            <div class="step-title">Query: Cerco i punteggi (fraction) delle risposte</div>
        </div>
        
        <?php
        $results = $DB->get_records_sql("
            SELECT 
                qat.id as attemptquestionid,
                qat.questionid,
                q.name as questionname,
                qat.slot,
                qat.maxmark,
                (SELECT MAX(qas.fraction) 
                 FROM {question_attempt_steps} qas 
                 WHERE qas.questionattemptid = qat.id 
                 AND qas.fraction IS NOT NULL) as fraction
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} q ON q.id = qat.questionid
            WHERE qa.id = :attemptid
            ORDER BY qat.slot
        ", ['attemptid' => $firstAttempt->attemptid]);
        
        $totalCorrect = 0;
        $totalQuestions = count($results);
        
        echo '<table>';
        echo '<tr><th>Slot</th><th>Domanda</th><th>Fraction</th><th>Corretto?</th></tr>';
        $count = 0;
        foreach ($results as $r) {
            if ($count >= 10) {
                echo '<tr><td colspan="4"><em>... e altre ' . (count($results) - 10) . '</em></td></tr>';
                break;
            }
            $isCorrect = ($r->fraction >= 0.5);
            if ($isCorrect) $totalCorrect++;
            echo '<tr>';
            echo '<td>' . $r->slot . '</td>';
            echo '<td>' . format_string(substr($r->questionname, 0, 40)) . '</td>';
            echo '<td>' . ($r->fraction !== null ? round($r->fraction, 2) : 'NULL') . '</td>';
            echo '<td>' . ($isCorrect ? '✅' : '❌') . '</td>';
            echo '</tr>';
            $count++;
        }
        echo '</table>';
        echo '<p><strong>Risposte corrette:</strong> ' . $totalCorrect . ' / ' . $totalQuestions . '</p>';
        ?>
    </div>

    <!-- STEP 4: Verifica collegamento competenze -->
    <div class="debug-card">
        <h3>🎯 STEP 4: Collegamento Domande → Competenze</h3>
        
        <div class="step">
            <div class="step-title">Query: Verifico se le domande del quiz hanno competenze assegnate in qbank_competenciesbyquestion</div>
        </div>
        
        <?php
        $questionIds = array_column($questions, 'questionid');
        
        if (empty($questionIds)) {
            echo '<p class="status-error">❌ Nessuna domanda da verificare</p>';
        } else {
            list($insql, $params) = $DB->get_in_or_equal($questionIds, SQL_PARAMS_NAMED);
            
            $competencyLinks = $DB->get_records_sql("
                SELECT 
                    qcbq.id,
                    qcbq.questionid,
                    qcbq.competencyid,
                    qcbq.difficultylevel,
                    c.idnumber,
                    c.shortname
                FROM {qbank_competenciesbyquestion} qcbq
                JOIN {competency} c ON c.id = qcbq.competencyid
                WHERE qcbq.questionid $insql
            ", $params);
            
            $linkedQuestions = count($competencyLinks);
            $totalQ = count($questionIds);
            
            if ($linkedQuestions == 0) {
                echo '<p class="status-error">❌ NESSUNA DOMANDA ha una competenza assegnata!</p>';
                echo '<p>Questo è il problema! Le domande del quiz non sono collegate alle competenze.</p>';
            } else if ($linkedQuestions < $totalQ) {
                echo '<p class="status-warning">⚠️ Solo ' . $linkedQuestions . ' domande su ' . $totalQ . ' hanno competenze assegnate</p>';
            } else {
                echo '<p class="status-ok">✅ Tutte le ' . $linkedQuestions . ' domande hanno competenze assegnate</p>';
            }
            
            echo '<table>';
            echo '<tr><th>Question ID</th><th>Competency ID</th><th>Codice</th><th>Nome</th><th>Livello</th></tr>';
            foreach ($competencyLinks as $link) {
                echo '<tr>';
                echo '<td>' . $link->questionid . '</td>';
                echo '<td>' . $link->competencyid . '</td>';
                echo '<td>' . $link->idnumber . '</td>';
                echo '<td>' . $link->shortname . '</td>';
                echo '<td>' . str_repeat('⭐', $link->difficultylevel ?: 1) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <!-- STEP 5: Calcolo finale competenze -->
    <div class="debug-card">
        <h3>📈 STEP 5: Calcolo Punteggi Competenze</h3>
        
        <div class="step">
            <div class="step-title">Query completa: Unisco risposte + competenze per calcolare i punteggi</div>
        </div>
        
        <?php
        $fullResults = $DB->get_records_sql("
            SELECT 
                qat.questionid,
                q.name as questionname,
                (SELECT MAX(qas.fraction) 
                 FROM {question_attempt_steps} qas 
                 WHERE qas.questionattemptid = qat.id 
                 AND qas.fraction IS NOT NULL) as fraction,
                qat.maxmark,
                qcbq.competencyid,
                qcbq.difficultylevel,
                c.idnumber as comp_code,
                c.shortname as comp_name
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} q ON q.id = qat.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
            LEFT JOIN {competency} c ON c.id = qcbq.competencyid
            WHERE qa.id = :attemptid
        ", ['attemptid' => $firstAttempt->attemptid]);
        
        // Raggruppa per competenza
        $competencyScores = [];
        foreach ($fullResults as $r) {
            if (empty($r->competencyid)) continue;
            
            $cid = $r->competencyid;
            if (!isset($competencyScores[$cid])) {
                $competencyScores[$cid] = [
                    'code' => $r->comp_code,
                    'name' => $r->comp_name,
                    'total' => 0,
                    'correct' => 0,
                    'score' => 0,
                    'max' => 0
                ];
            }
            
            $competencyScores[$cid]['total']++;
            $competencyScores[$cid]['max'] += $r->maxmark;
            if ($r->fraction !== null) {
                $competencyScores[$cid]['score'] += ($r->fraction * $r->maxmark);
                if ($r->fraction >= 0.5) {
                    $competencyScores[$cid]['correct']++;
                }
            }
        }
        
        if (empty($competencyScores)) {
            echo '<p class="status-error">❌ Nessun punteggio competenza calcolato!</p>';
            echo '<p>Motivo probabile: le domande del quiz non hanno competenze assegnate nella tabella qbank_competenciesbyquestion</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($competencyScores) . ' competenze calcolate</p>';
            echo '<table>';
            echo '<tr><th>Codice</th><th>Nome</th><th>Domande</th><th>Corrette</th><th>Punteggio</th><th>Percentuale</th><th>Stato</th></tr>';
            foreach ($competencyScores as $comp) {
                $percentage = $comp['max'] > 0 ? round(($comp['score'] / $comp['max']) * 100, 1) : 0;
                $status = $percentage >= 80 ? '✅ Acquisita' : ($percentage >= 60 ? '🔶 Buono' : '❌ Da migliorare');
                echo '<tr>';
                echo '<td><strong>' . $comp['code'] . '</strong></td>';
                echo '<td>' . $comp['name'] . '</td>';
                echo '<td>' . $comp['total'] . '</td>';
                echo '<td>' . $comp['correct'] . '</td>';
                echo '<td>' . round($comp['score'], 1) . ' / ' . $comp['max'] . '</td>';
                echo '<td><strong>' . $percentage . '%</strong></td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <?php endif; ?>

<?php } ?>

<p style="margin-top: 20px;">
    <a href="reports.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">← Report Studenti</a>
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🏠 Dashboard</a>
</p>

</div>

<?php
echo $OUTPUT->footer();
