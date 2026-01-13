<?php
/**
 * Diagnostica Quiz - Competency Manager
 * Verifica se i quiz vengono creati correttamente
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/diagnostics.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Diagnostica Quiz');
$PAGE->set_heading('Diagnostica Quiz - ' . $course->shortname);

echo $OUTPUT->header();
?>

<style>
.diag-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
.diag-header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.diag-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.diag-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #34495e; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.count-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; margin-right: 10px; }
.count-green { background: #d4edda; color: #155724; }
.count-red { background: #f8d7da; color: #721c24; }
.count-blue { background: #cce5ff; color: #004085; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
</style>

<div class="diag-container">
    
    <div class="diag-header">
        <h2>🔍 Diagnostica Quiz e Domande</h2>
        <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
    </div>

    <!-- SEZIONE 1: Quiz del corso -->
    <div class="diag-card">
        <h3>📝 Quiz nel Corso</h3>
        <?php
        $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'timecreated DESC');
        
        if (empty($quizzes)) {
            echo '<p class="status-warning">⚠️ Nessun quiz trovato in questo corso.</p>';
        } else {
            echo '<p><span class="count-badge count-blue">' . count($quizzes) . ' quiz</span> trovati</p>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Nome</th><th>Creato</th><th>Domande (slots)</th><th>Sumgrades</th><th>Azioni</th></tr>';
            
            foreach ($quizzes as $quiz) {
                // Conta slots
                $slotcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
                
                // Conta question_references
                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
                $refcount = 0;
                if ($cm) {
                    $modcontext = context_module::instance($cm->id);
                    $refcount = $DB->count_records('question_references', [
                        'component' => 'mod_quiz',
                        'questionarea' => 'slot',
                        'usingcontextid' => $modcontext->id
                    ]);
                }
                
                $statusClass = ($slotcount > 0 && $refcount > 0) ? 'status-ok' : 'status-error';
                
                echo '<tr>';
                echo '<td>' . $quiz->id . '</td>';
                echo '<td><strong>' . format_string($quiz->name) . '</strong></td>';
                echo '<td>' . date('d/m/Y H:i', $quiz->timecreated) . '</td>';
                echo '<td class="' . $statusClass . '">';
                echo 'Slots: ' . $slotcount . ' | Refs: ' . $refcount;
                if ($slotcount == 0) echo ' ❌';
                elseif ($slotcount == $refcount) echo ' ✅';
                else echo ' ⚠️';
                echo '</td>';
                echo '<td>' . $quiz->sumgrades . '</td>';
                echo '<td><a href="?courseid=' . $courseid . '&quizid=' . $quiz->id . '" class="btn btn-sm btn-primary">Dettagli</a></td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <?php if ($quizid > 0): 
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if ($quiz):
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
            $modcontext = $cm ? context_module::instance($cm->id) : null;
    ?>
    
    <!-- SEZIONE 2: Dettaglio Quiz Selezionato -->
    <div class="diag-card">
        <h3>🔎 Dettaglio Quiz: <?php echo format_string($quiz->name); ?></h3>
        
        <h4>📋 Informazioni Base</h4>
        <table>
            <tr><td><strong>Quiz ID</strong></td><td><?php echo $quiz->id; ?></td></tr>
            <tr><td><strong>Course Module ID</strong></td><td><?php echo $cm ? $cm->id : '<span class="status-error">NON TROVATO!</span>'; ?></td></tr>
            <tr><td><strong>Module Context ID</strong></td><td><?php echo $modcontext ? $modcontext->id : '<span class="status-error">NON TROVATO!</span>'; ?></td></tr>
            <tr><td><strong>Sumgrades</strong></td><td><?php echo $quiz->sumgrades; ?></td></tr>
            <tr><td><strong>Creato il</strong></td><td><?php echo date('d/m/Y H:i:s', $quiz->timecreated); ?></td></tr>
        </table>
        
        <h4>📊 Quiz Sections</h4>
        <?php
        $sections = $DB->get_records('quiz_sections', ['quizid' => $quizid]);
        if (empty($sections)) {
            echo '<p class="status-error">❌ Nessuna quiz_section trovata! Questo può causare problemi.</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($sections) . ' sezione/i trovata/e</p>';
        }
        ?>
        
        <h4>🎰 Quiz Slots</h4>
        <?php
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot ASC');
        if (empty($slots)) {
            echo '<p class="status-error">❌ Nessuno slot trovato! Le domande NON sono collegate al quiz.</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($slots) . ' slot trovati</p>';
            echo '<table>';
            echo '<tr><th>Slot ID</th><th>Slot #</th><th>Page</th><th>MaxMark</th></tr>';
            foreach ($slots as $slot) {
                echo '<tr>';
                echo '<td>' . $slot->id . '</td>';
                echo '<td>' . $slot->slot . '</td>';
                echo '<td>' . $slot->page . '</td>';
                echo '<td>' . $slot->maxmark . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
        
        <h4>🔗 Question References</h4>
        <?php
        if ($modcontext) {
            $refs = $DB->get_records('question_references', [
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'usingcontextid' => $modcontext->id
            ]);
            
            if (empty($refs)) {
                echo '<p class="status-error">❌ Nessuna question_reference trovata! Le domande NON sono collegate.</p>';
            } else {
                echo '<p class="status-ok">✅ ' . count($refs) . ' riferimenti trovati</p>';
                echo '<table>';
                echo '<tr><th>Ref ID</th><th>Item ID (slot)</th><th>QB Entry ID</th><th>Domanda</th></tr>';
                foreach ($refs as $ref) {
                    $qname = '-';
                    $qversion = $DB->get_record_sql("
                        SELECT q.name 
                        FROM {question} q
                        JOIN {question_versions} qv ON qv.questionid = q.id
                        WHERE qv.questionbankentryid = ?
                        ORDER BY qv.version DESC
                        LIMIT 1
                    ", [$ref->questionbankentryid]);
                    if ($qversion) {
                        $qname = $qversion->name;
                    }
                    
                    echo '<tr>';
                    echo '<td>' . $ref->id . '</td>';
                    echo '<td>' . $ref->itemid . '</td>';
                    echo '<td>' . $ref->questionbankentryid . '</td>';
                    echo '<td>' . format_string($qname) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } else {
            echo '<p class="status-error">❌ Context del modulo non trovato!</p>';
        }
        ?>
        
        <h4>🧪 Test Caricamento Moodle</h4>
        <?php
        try {
            $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
            $structure = $quizobj->get_structure();
            $slotcount = $structure->get_slot_count();
            
            echo '<p class="status-ok">✅ Quiz caricato correttamente</p>';
            echo '<p>Domande visibili: <strong>' . $slotcount . '</strong></p>';
            
            if ($slotcount > 0) {
                echo '<table>';
                echo '<tr><th>Slot</th><th>Question ID</th><th>Nome</th><th>Tipo</th></tr>';
                foreach ($structure->get_slots() as $slot) {
                    $question = \question_bank::load_question($slot->questionid);
                    echo '<tr>';
                    echo '<td>' . $slot->slot . '</td>';
                    echo '<td>' . $slot->questionid . '</td>';
                    echo '<td>' . format_string($question->name) . '</td>';
                    echo '<td>' . $question->qtype . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } catch (Exception $e) {
            echo '<p class="status-error">❌ Errore: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <?php endif; endif; ?>
    
    <!-- SEZIONE 3: Domande nel Banco -->
    <div class="diag-card">
        <h3>❓ Domande nel Banco (ultime 30)</h3>
        <?php
        $questions = $DB->get_records_sql("
            SELECT q.id, q.name, q.qtype, qc.name as catname,
                   (SELECT COUNT(*) FROM {qbank_competenciesbyquestion} qcbq WHERE qcbq.questionid = q.id) as comp_count
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qc.contextid = ?
            AND q.parent = 0
            ORDER BY q.timecreated DESC
            LIMIT 30
        ", [$context->id]);
        
        if (empty($questions)) {
            echo '<p class="status-warning">⚠️ Nessuna domanda nel banco.</p>';
        } else {
            echo '<table>';
            echo '<tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Competenza</th></tr>';
            foreach ($questions as $q) {
                echo '<tr>';
                echo '<td>' . $q->id . '</td>';
                echo '<td>' . format_string(substr($q->name, 0, 60)) . '</td>';
                echo '<td>' . $q->qtype . '</td>';
                echo '<td>' . ($q->comp_count > 0 ? '✅' : '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>
    
    <p>
        <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🏠 Dashboard</a>
        <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🔄 Aggiorna</a>
    </p>
    
</div>

<?php
echo $OUTPUT->footer();
