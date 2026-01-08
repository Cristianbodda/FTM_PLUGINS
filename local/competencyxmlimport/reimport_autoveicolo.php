<?php
/**
 * Re-Import Domande Autoveicolo
 * 
 * Elimina le domande esistenti e le reimporta con i nomi corretti
 * incluso il codice competenza
 * 
 * @package    local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/questionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$step = optional_param('step', 1, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/reimport_autoveicolo.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Re-Import Autoveicolo');
$PAGE->set_heading($course->fullname);

$css = '<style>
.page { max-width: 900px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; border: 1px solid #e0e0e0; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.btn { padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; display: inline-block; margin-right: 10px; }
.btn-danger { background: #e74c3c; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-warning { background: #f39c12; color: white; }
.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; }
.alert-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
.progress-log { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; }
.progress-log .ok { color: #27ae60; }
.progress-log .err { color: #e74c3c; }
.progress-log .info { color: #3498db; }
.steps { display: flex; margin-bottom: 20px; }
.step { flex: 1; text-align: center; padding: 15px; background: #f8f9fa; border: 2px solid #ddd; }
.step.active { background: #e74c3c; color: white; border-color: #e74c3c; }
.step.done { background: #27ae60; color: white; border-color: #27ae60; }
.step-num { font-size: 24px; font-weight: bold; }
.step-label { font-size: 12px; }
</style>';

echo $OUTPUT->header();
echo $css;

// Framework e competenze
$framework_id = 1;
$competencies = $DB->get_records_sql("
    SELECT id, idnumber FROM {competency} 
    WHERE competencyframeworkid = ? 
    AND (idnumber LIKE 'AUTOMOBILE_MR_%' OR idnumber LIKE 'AUTOMOBILE_MAu_%')
", [$framework_id]);
$comp_lookup = [];
foreach ($competencies as $c) {
    $comp_lookup[$c->idnumber] = $c->id;
}

// Configurazione quiz
$quiz_configs = [
    ['file' => 'AUT_TEST_BASE_XML_Cristian.xml', 'name' => 'Autoveicolo - Test Base', 'category' => 'Test Base', 'level' => 1],
    ['file' => 'AUT_APPR_MOT_Motore_Powertrain.xml', 'name' => 'Approfondimento - Motore e Powertrain', 'category' => 'Motore e Powertrain', 'level' => 2],
    ['file' => 'AUT_APPR_MR_Meccanica_Riparazioni.xml', 'name' => 'Approfondimento - Meccanica e Riparazioni', 'category' => 'Meccanica e Riparazioni', 'level' => 2],
    ['file' => 'AUT_APPR_ELET_Elettronica_Veicolo.xml', 'name' => 'Approfondimento - Elettronica Veicolo', 'category' => 'Elettronica Veicolo', 'level' => 2],
    ['file' => 'AUT_APPR_ADAS_Sistemi_Assistenza_Guida.xml', 'name' => 'Approfondimento - Sistemi ADAS', 'category' => 'Sistemi ADAS', 'level' => 2],
    ['file' => 'AUT_APPR_HVAC_Climatizzazione.xml', 'name' => 'Approfondimento - Climatizzazione HVAC', 'category' => 'Climatizzazione HVAC', 'level' => 2],
    ['file' => 'AUT_APPR_HV_Alta_Tensione.xml', 'name' => 'Approfondimento - Alta Tensione HV', 'category' => 'Alta Tensione HV', 'level' => 2],
];

// Funzione per estrarre competenza
function extract_comp_code($text) {
    if (preg_match('/AUTOMOBILE_(MR|MAu)_([A-Z]\d+)/i', $text, $m)) {
        return 'AUTOMOBILE_' . $m[1] . '_' . $m[2];
    }
    return null;
}

// === STEP 1: ELIMINA QUIZ E DOMANDE ===
if ($action === 'delete' && $step == 1) {
    require_sesskey();
    
    echo '<div class="page">';
    echo '<div class="header"><h2>🗑️ Step 1: Eliminazione in corso...</h2></div>';
    echo '<div class="panel"><div class="progress-log">';
    
    // Trova e elimina quiz Autoveicolo
    $quizzes = $DB->get_records_sql("
        SELECT q.* FROM {quiz} q WHERE q.course = ? 
        AND (q.name LIKE 'Autoveicolo%' OR q.name LIKE 'Approfondimento%')
    ", [$courseid]);
    
    foreach ($quizzes as $quiz) {
        // Trova course_module
        $cm = $DB->get_record_sql("
            SELECT cm.* FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            WHERE cm.instance = ? AND cm.course = ?
        ", [$quiz->id, $courseid]);
        
        if ($cm) {
            // Elimina question_references
            $modcontext = context_module::instance($cm->id);
            $DB->delete_records('question_references', ['usingcontextid' => $modcontext->id]);
            
            // Rimuovi dalla sezione
            $section = $DB->get_record('course_sections', ['id' => $cm->section]);
            if ($section && $section->sequence) {
                $seq = array_filter(explode(',', $section->sequence), fn($id) => $id != $cm->id);
                $DB->set_field('course_sections', 'sequence', implode(',', $seq), ['id' => $section->id]);
            }
            
            // Elimina context
            $DB->delete_records('context', ['contextlevel' => CONTEXT_MODULE, 'instanceid' => $cm->id]);
            $DB->delete_records('course_modules', ['id' => $cm->id]);
        }
        
        // Elimina quiz data
        $DB->delete_records('quiz_slots', ['quizid' => $quiz->id]);
        $DB->delete_records('quiz_sections', ['quizid' => $quiz->id]);
        $DB->delete_records('quiz', ['id' => $quiz->id]);
        
        echo '<div class="ok">✅ Eliminato quiz: ' . $quiz->name . '</div>';
    }
    
    // Trova categorie Autoveicolo
    $categories = $DB->get_records_sql("
        SELECT qc.* FROM {question_categories} qc
        WHERE qc.contextid = ? 
        AND (qc.name = 'Autoveicolo' OR qc.parent IN (
            SELECT id FROM {question_categories} WHERE contextid = ? AND name = 'Autoveicolo'
        ))
    ", [$context->id, $context->id]);
    
    // Elimina domande e categorie
    foreach ($categories as $cat) {
        // Trova domande nella categoria
        $questions = $DB->get_records_sql("
            SELECT q.id FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = ?
        ", [$cat->id]);
        
        foreach ($questions as $q) {
            // Elimina competenze associate
            $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $q->id]);
            // Elimina risposte
            $DB->delete_records('question_answers', ['question' => $q->id]);
            // Elimina opzioni multichoice
            $DB->delete_records('qtype_multichoice_options', ['questionid' => $q->id]);
            // Elimina question
            $DB->delete_records('question', ['id' => $q->id]);
        }
        
        // Elimina question_versions e question_bank_entries
        $DB->execute("DELETE qv FROM {question_versions} qv 
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid 
            WHERE qbe.questioncategoryid = ?", [$cat->id]);
        $DB->delete_records('question_bank_entries', ['questioncategoryid' => $cat->id]);
        
        echo '<div class="ok">✅ Pulita categoria: ' . $cat->name . ' (' . count($questions) . ' domande)</div>';
    }
    
    // Elimina categorie (prima le figlie, poi la madre)
    $DB->execute("DELETE FROM {question_categories} WHERE contextid = ? AND parent IN (
        SELECT id FROM (SELECT id FROM {question_categories} WHERE contextid = ? AND name = 'Autoveicolo') as t
    )", [$context->id, $context->id]);
    $DB->delete_records('question_categories', ['contextid' => $context->id, 'name' => 'Autoveicolo']);
    
    echo '<div class="ok">✅ Categorie eliminate</div>';
    
    rebuild_course_cache($courseid, true);
    
    echo '</div></div>';
    echo '<div class="alert alert-success">✅ <strong>Step 1 completato!</strong> Quiz e domande eliminati.</div>';
    echo '<a href="?courseid='.$courseid.'&action=import&step=2" class="btn btn-success">➡️ Procedi a Step 2: Import</a>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// === STEP 2: IMPORT DOMANDE E CREA QUIZ ===
if ($action === 'import' && $step == 2) {
    
    echo '<div class="page">';
    echo '<div class="header"><h2>📥 Step 2: Import in corso...</h2></div>';
    echo '<div class="panel"><div class="progress-log">';
    
    // Crea categoria madre
    $parent_cat = new stdClass();
    $parent_cat->name = 'Autoveicolo';
    $parent_cat->contextid = $context->id;
    $parent_cat->info = 'Domande corso Autoveicolo';
    $parent_cat->infoformat = FORMAT_HTML;
    $parent_cat->parent = 0;
    $parent_cat->sortorder = 999;
    $parent_cat->stamp = make_unique_id_code();
    $parent_cat->id = $DB->insert_record('question_categories', $parent_cat);
    echo '<div class="info">📁 Creata categoria madre: Autoveicolo</div>';
    
    // Trova sezione 0
    $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);
    
    $total_questions = 0;
    $total_competencies = 0;
    
    foreach ($quiz_configs as $config) {
        $filepath = __DIR__ . '/xml/' . $config['file'];
        
        if (!file_exists($filepath)) {
            echo '<div class="err">❌ File non trovato: ' . $config['file'] . '</div>';
            continue;
        }
        
        echo '<div class="info">📄 Processo: ' . $config['file'] . '</div>';
        
        // Crea sottocategoria
        $sub_cat = new stdClass();
        $sub_cat->name = $config['category'];
        $sub_cat->contextid = $context->id;
        $sub_cat->info = '';
        $sub_cat->infoformat = FORMAT_HTML;
        $sub_cat->parent = $parent_cat->id;
        $sub_cat->sortorder = 999;
        $sub_cat->stamp = make_unique_id_code();
        $sub_cat->id = $DB->insert_record('question_categories', $sub_cat);
        
        // Leggi XML
        $xml = file_get_contents($filepath);
        preg_match_all('/<question type="multichoice">(.*?)<\/question>/s', $xml, $matches);
        
        $question_ids = [];
        
        foreach ($matches[0] as $qxml) {
            // Estrai nome completo (con codice competenza)
            preg_match('/<n><text>(.*?)<\/text><\/n>/', $qxml, $name_match);
            $full_name = isset($name_match[1]) ? trim($name_match[1]) : 'Domanda';
            
            // Estrai testo domanda
            preg_match('/<questiontext.*?><text>(.*?)<\/text>/s', $qxml, $text_match);
            $qtext = isset($text_match[1]) ? html_entity_decode($text_match[1]) : '';
            
            // Estrai competenza dal nome O dal testo
            $comp_code = extract_comp_code($full_name);
            if (!$comp_code) {
                $comp_code = extract_comp_code($qtext);
            }
            
            // Crea question_bank_entry
            $qbe = new stdClass();
            $qbe->questioncategoryid = $sub_cat->id;
            $qbe->ownerid = $USER->id;
            $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
            
            // Crea question CON NOME COMPLETO
            $question = new stdClass();
            $question->name = $full_name; // <-- NOME COMPLETO con codice!
            $question->questiontext = $qtext;
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = '';
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->defaultmark = 1;
            $question->penalty = 0.3333333;
            $question->qtype = 'multichoice';
            $question->length = 1;
            $question->stamp = make_unique_id_code();
            $question->timecreated = time();
            $question->timemodified = time();
            $question->createdby = $USER->id;
            $question->modifiedby = $USER->id;
            $question->id = $DB->insert_record('question', $question);
            
            // Question version
            $qv = new stdClass();
            $qv->questionbankentryid = $qbe->id;
            $qv->questionid = $question->id;
            $qv->version = 1;
            $qv->status = 'ready';
            $DB->insert_record('question_versions', $qv);
            
            // Opzioni multichoice
            $opts = new stdClass();
            $opts->questionid = $question->id;
            $opts->single = 1;
            $opts->shuffleanswers = 1;
            $opts->answernumbering = 'abc';
            $opts->correctfeedback = '';
            $opts->correctfeedbackformat = FORMAT_HTML;
            $opts->partiallycorrectfeedback = '';
            $opts->partiallycorrectfeedbackformat = FORMAT_HTML;
            $opts->incorrectfeedback = '';
            $opts->incorrectfeedbackformat = FORMAT_HTML;
            $opts->shownumcorrect = 0;
            $DB->insert_record('qtype_multichoice_options', $opts);
            
            // Risposte
            preg_match_all('/<answer fraction="(\d+)".*?><text>(.*?)<\/text>/s', $qxml, $answers);
            for ($i = 0; $i < count($answers[0]); $i++) {
                $ans = new stdClass();
                $ans->question = $question->id;
                $ans->answer = html_entity_decode($answers[2][$i]);
                $ans->answerformat = FORMAT_HTML;
                $ans->fraction = $answers[1][$i] / 100;
                $ans->feedback = '';
                $ans->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $ans);
            }
            
            // Assegna competenza
            if ($comp_code && isset($comp_lookup[$comp_code])) {
                $rec = new stdClass();
                $rec->questionid = $question->id;
                $rec->competencyid = $comp_lookup[$comp_code];
                $rec->difficultylevel = $config['level'];
                $DB->insert_record('qbank_competenciesbyquestion', $rec);
                $total_competencies++;
            }
            
            $question_ids[] = $question->id;
            $total_questions++;
        }
        
        echo '<div class="ok">✅ Importate ' . count($question_ids) . ' domande in "' . $config['category'] . '"</div>';
        
        // Crea Quiz
        $quiz = new stdClass();
        $quiz->course = $courseid;
        $quiz->name = $config['name'];
        $quiz->intro = '<p>Quiz ' . $config['category'] . '</p>';
        $quiz->introformat = FORMAT_HTML;
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->attempts = 0;
        $quiz->grademethod = 1;
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->grade = 10;
        $quiz->sumgrades = 0;
        $quiz->shuffleanswers = 1;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->reviewattempt = 69904;
        $quiz->reviewcorrectness = 69904;
        $quiz->reviewmaxmarks = 69904;
        $quiz->reviewmarks = 69904;
        $quiz->reviewspecificfeedback = 69904;
        $quiz->reviewgeneralfeedback = 69904;
        $quiz->reviewrightanswer = 69904;
        $quiz->reviewoverallfeedback = 69904;
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->canredoquestions = 0;
        $quiz->allowofflineattempts = 0;
        $quiz->id = $DB->insert_record('quiz', $quiz);
        
        // Course module
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $cm = new stdClass();
        $cm->course = $courseid;
        $cm->module = $module->id;
        $cm->instance = $quiz->id;
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->visibleoncoursepage = 1;
        $cm->added = time();
        $cm->id = $DB->insert_record('course_modules', $cm);
        
        course_add_cm_to_section($courseid, $cm->id, $section->section);
        context_module::instance($cm->id);
        $modcontext = context_module::instance($cm->id);
        
        // Quiz section
        $qs = new stdClass();
        $qs->quizid = $quiz->id;
        $qs->firstslot = 1;
        $qs->heading = '';
        $qs->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $qs);
        
        // Aggiungi domande
        $slot = 0;
        foreach ($question_ids as $qid) {
            $slot++;
            $qbe = $DB->get_record_sql("
                SELECT qbe.id FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = ?
            ", [$qid]);
            
            if (!$qbe) continue;
            
            $slotrecord = new stdClass();
            $slotrecord->quizid = $quiz->id;
            $slotrecord->slot = $slot;
            $slotrecord->page = ceil($slot / 5);
            $slotrecord->maxmark = 1.0;
            $slotrecord->id = $DB->insert_record('quiz_slots', $slotrecord);
            
            $qref = new stdClass();
            $qref->usingcontextid = $modcontext->id;
            $qref->component = 'mod_quiz';
            $qref->questionarea = 'slot';
            $qref->itemid = $slotrecord->id;
            $qref->questionbankentryid = $qbe->id;
            $qref->version = null;
            $DB->insert_record('question_references', $qref);
        }
        
        $DB->set_field('quiz', 'sumgrades', $slot, ['id' => $quiz->id]);
        
        echo '<div class="ok">✅ Creato quiz: ' . $config['name'] . ' (' . $slot . ' domande)</div>';
    }
    
    rebuild_course_cache($courseid, true);
    
    echo '</div></div>';
    echo '<div class="alert alert-success">';
    echo '<h4>🎉 Import Completato!</h4>';
    echo '<p><strong>Domande importate:</strong> ' . $total_questions . '</p>';
    echo '<p><strong>Competenze assegnate:</strong> ' . $total_competencies . '</p>';
    echo '</div>';
    echo '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '" class="btn btn-success">🚗 Vai al Corso</a>';
    echo '<a href="dashboard.php?courseid=' . $courseid . '" class="btn btn-secondary">📊 Dashboard</a>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// === PAGINA INIZIALE ===
?>
<div class="page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" style="color: #e74c3c; text-decoration: none;">← Dashboard</a>
    
    <div class="header">
        <h2>🔄 Re-Import Domande Autoveicolo</h2>
        <p>Elimina e reimporta le domande con i nomi corretti (incluso codice competenza)</p>
    </div>
    
    <div class="steps">
        <div class="step active">
            <div class="step-num">1</div>
            <div class="step-label">Elimina Quiz e Domande</div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-label">Import e Crea Quiz</div>
        </div>
        <div class="step">
            <div class="step-num">✓</div>
            <div class="step-label">Completato</div>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <strong>⚠️ Attenzione!</strong> Questa operazione eliminerà tutti i quiz e le domande Autoveicolo esistenti e li ricreerà da zero.
    </div>
    
    <div class="panel">
        <h3>📋 Cosa verrà fatto</h3>
        <ol>
            <li><strong>Step 1:</strong> Elimina tutti i quiz "Autoveicolo" e "Approfondimento"</li>
            <li><strong>Step 1:</strong> Elimina tutte le domande nella categoria "Autoveicolo"</li>
            <li><strong>Step 2:</strong> Reimporta le domande dai file XML con nomi corretti</li>
            <li><strong>Step 2:</strong> Assegna automaticamente le competenze</li>
            <li><strong>Step 2:</strong> Ricrea i 7 quiz</li>
        </ol>
    </div>
    
    <div class="panel">
        <h3>📁 File XML richiesti</h3>
        <p>Verifica che questi file siano presenti in <code>/local/competencyxmlimport/xml/</code>:</p>
        <ul>
            <?php foreach ($quiz_configs as $config): 
                $exists = file_exists(__DIR__ . '/xml/' . $config['file']);
            ?>
            <li>
                <?php echo $exists ? '✅' : '❌'; ?>
                <code><?php echo $config['file']; ?></code>
                <?php if (!$exists): ?><span style="color: red;"> - MANCANTE!</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <form method="post" action="?courseid=<?php echo $courseid; ?>&action=delete&step=1">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro? Tutti i quiz Autoveicolo verranno eliminati!');">
            🚀 Avvia Re-Import
        </button>
        <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
    </form>
</div>
<?php

echo $OUTPUT->footer();
