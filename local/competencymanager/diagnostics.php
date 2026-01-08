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
$PAGE->set_heading('Diagnostica Quiz - ' . $course->fullname);

echo $OUTPUT->header();
?>

<style>
.diag-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; }
.diag-card h3 { margin-top: 0; color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
.diag-card h4 { margin-top: 20px; color: #555; }
.status-ok { color: #28a745; font-weight: bold; }
.status-error { color: #dc3545; font-weight: bold; }
.status-warning { color: #ffc107; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
th { background: #f5f5f5; }
tr:nth-child(even) { background: #fafafa; }
.btn { display: inline-block; padding: 8px 16px; margin: 5px; border-radius: 4px; text-decoration: none; }
.btn-primary { background: #0073aa; color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; }
.btn-success { background: #28a745; color: #fff; }
.btn-info { background: #17a2b8; color: #fff; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
</style>

<div class="container-fluid">
    <h2>🔬 Diagnostica Quiz</h2>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong> (ID: <?php echo $courseid; ?>)</p>
    
    <!-- SEZIONE 1: Lista Quiz -->
    <div class="diag-card">
        <h3>📋 Quiz nel Corso</h3>
        <?php
        // Recupera tutti i quiz del corso
        $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'timecreated DESC');
        
        if (empty($quizzes)) {
            echo '<p class="status-warning">⚠️ Nessun quiz trovato in questo corso.</p>';
        } else {
            echo '<table>';
            echo '<tr><th>ID</th><th>Nome</th><th>Creato</th><th>Slots</th><th>References</th><th>Stato</th><th>Azioni</th></tr>';
            
            foreach ($quizzes as $quiz) {
                // Conta slots
                $slotcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
                
                // Conta references
                $refcount = $DB->count_records_sql("
                    SELECT COUNT(*) 
                    FROM {question_references} qr
                    JOIN {quiz_slots} qs ON qs.id = qr.itemid
                    WHERE qr.component = 'mod_quiz'
                    AND qr.questionarea = 'slot'
                    AND qs.quizid = ?
                ", [$quiz->id]);
                
                // Determina stato
                if ($slotcount > 0 && $slotcount == $refcount) {
                    $status = '<span class="status-ok">✅ OK</span>';
                } elseif ($slotcount == 0) {
                    $status = '<span class="status-error">❌ Vuoto</span>';
                } else {
                    $status = '<span class="status-warning">⚠️ Mismatch</span>';
                }
                
                echo '<tr>';
                echo '<td>' . $quiz->id . '</td>';
                echo '<td>' . format_string($quiz->name) . '</td>';
                echo '<td>' . userdate($quiz->timecreated, '%d/%m/%Y %H:%M') . '</td>';
                echo '<td>Slots: ' . $slotcount . '</td>';
                echo '<td>Refs: ' . $refcount . '</td>';
                echo '<td>' . $status . '</td>';
                echo '<td>';
                echo '<a href="?courseid=' . $courseid . '&quizid=' . $quiz->id . '" class="btn btn-info btn-sm">🔍 Dettagli</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>
    
    <!-- SEZIONE 2: Dettaglio Quiz (se selezionato) -->
    <?php if ($quizid > 0): 
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if ($quiz):
    ?>
    <div class="diag-card">
        <h3>🔎 Dettaglio Quiz: <?php echo format_string($quiz->name); ?></h3>
        
        <h4>📋 Informazioni Base</h4>
        <table>
            <tr><th>Quiz ID</th><td><?php echo $quiz->id; ?></td></tr>
            <?php
            // Recupera course module
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
            if ($cm) {
                echo '<tr><th>Course Module ID</th><td>' . $cm->id . '</td></tr>';
                $modcontext = context_module::instance($cm->id);
                echo '<tr><th>Module Context ID</th><td>' . $modcontext->id . '</td></tr>';
            }
            ?>
            <tr><th>Sumgrades</th><td><?php echo $quiz->sumgrades; ?></td></tr>
            <tr><th>Creato il</th><td><?php echo userdate($quiz->timecreated, '%d/%m/%Y %H:%M:%S'); ?></td></tr>
        </table>
        
        <h4>📊 Quiz Sections</h4>
        <?php
        $sections = $DB->get_records('quiz_sections', ['quizid' => $quizid]);
        if (empty($sections)) {
            echo '<p class="status-error">❌ Nessuna sezione trovata (problema critico!)</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($sections) . ' sezione/i trovata/e</p>';
        }
        ?>
        
        <h4>🎰 Quiz Slots</h4>
        <?php
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot ASC');
        if (empty($slots)) {
            echo '<p class="status-error">❌ Nessuno slot trovato</p>';
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
        $refs = $DB->get_records_sql("
            SELECT qr.*, qbe.id as entryid,
                   (SELECT q.name FROM {question} q 
                    JOIN {question_versions} qv ON qv.questionid = q.id 
                    WHERE qv.questionbankentryid = qbe.id 
                    ORDER BY qv.version DESC LIMIT 1) as questionname
            FROM {question_references} qr
            JOIN {quiz_slots} qs ON qs.id = qr.itemid
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            WHERE qr.component = 'mod_quiz'
            AND qr.questionarea = 'slot'
            AND qs.quizid = ?
            ORDER BY qs.slot ASC
        ", [$quizid]);
        
        if (empty($refs)) {
            echo '<p class="status-error">❌ Nessun riferimento trovato</p>';
        } else {
            echo '<p class="status-ok">✅ ' . count($refs) . ' riferimenti trovati</p>';
            echo '<table>';
            echo '<tr><th>Ref ID</th><th>Item ID (slot)</th><th>QB Entry ID</th><th>Domanda</th></tr>';
            foreach ($refs as $ref) {
                echo '<tr>';
                echo '<td>' . $ref->id . '</td>';
                echo '<td>' . $ref->itemid . '</td>';
                echo '<td>' . $ref->questionbankentryid . '</td>';
                echo '<td>' . format_string($ref->questionname) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
        
        <h4>🧪 Test Caricamento Moodle API</h4>
        <?php
        // Test usando le API di Moodle in modo compatibile
        try {
            // Verifica se la classe quiz_settings esiste (Moodle 4.x)
            if (class_exists('\mod_quiz\quiz_settings')) {
                $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
                $structure = $quizobj->get_structure();
                
                // CORREZIONE: Invece di get_slot_count(), usiamo get_slots() e contiamo
                $allslots = $structure->get_slots();
                $slotcount = count($allslots);
                
                echo '<p class="status-ok">✅ Quiz caricato correttamente tramite API Moodle</p>';
                echo '<p>Domande visibili tramite API: <strong>' . $slotcount . '</strong></p>';
                
                if ($slotcount > 0) {
                    echo '<table>';
                    echo '<tr><th>Slot</th><th>Question ID</th><th>Nome</th><th>Tipo</th></tr>';
                    $count = 0;
                    foreach ($allslots as $slot) {
                        if ($count >= 10) {
                            echo '<tr><td colspan="4"><em>... e altri ' . ($slotcount - 10) . ' slot</em></td></tr>';
                            break;
                        }
                        try {
                            $question = \question_bank::load_question($slot->questionid);
                            // Recupera il tipo come stringa in modo sicuro
                            $qtypename = '';
                            if (method_exists($question, 'get_type_name')) {
                                $qtypename = $question->get_type_name();
                            } elseif (is_string($question->qtype)) {
                                $qtypename = $question->qtype;
                            } else {
                                // Se qtype è un oggetto, prova a ottenere il nome
                                $qtypename = get_class($question);
                                $qtypename = str_replace('qtype_', '', $qtypename);
                            }
                            echo '<tr>';
                            echo '<td>' . $slot->slot . '</td>';
                            echo '<td>' . $slot->questionid . '</td>';
                            echo '<td>' . format_string($question->name) . '</td>';
                            echo '<td>' . $qtypename . '</td>';
                            echo '</tr>';
                        } catch (Exception $e) {
                            echo '<tr><td>' . $slot->slot . '</td><td colspan="3" class="status-error">Errore: ' . $e->getMessage() . '</td></tr>';
                        }
                        $count++;
                    }
                    echo '</table>';
                }
            } else {
                echo '<p class="status-warning">⚠️ API quiz_settings non disponibile in questa versione di Moodle</p>';
            }
        } catch (Exception $e) {
            echo '<p class="status-error">❌ Errore API: ' . $e->getMessage() . '</p>';
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
    
    <div class="diag-card">
        <h3>🔧 Azioni</h3>
        <p>
            <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/create_quiz.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">➕ Crea Nuovo Quiz</a>
            <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">🏠 Dashboard</a>
            <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🔄 Aggiorna</a>
        </p>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
