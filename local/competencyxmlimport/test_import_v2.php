<?php
/**
 * TEST IMPORT DIRETTO v2 - FIX question_references
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $USER, $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

echo "<h1>🧪 Test Import Diretto v2</h1>";
echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace;'>";

// XML di test
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<quiz>
<question type="multichoice">
    <n><text>TEST_V2_Q01</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda 1: Quale risposta è corretta?&lt;/p&gt;</text></questiontext>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta A&lt;/p&gt;</text>
    </answer>
    <answer fraction="100" format="html">
        <text>&lt;p&gt;Risposta B - CORRETTA&lt;/p&gt;</text>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta C&lt;/p&gt;</text>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta D&lt;/p&gt;</text>
    </answer>
</question>
<question type="multichoice">
    <n><text>TEST_V2_Q02</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda 2: Altra domanda?&lt;/p&gt;</text></questiontext>
    <answer fraction="100" format="html">
        <text>&lt;p&gt;Risposta A - CORRETTA&lt;/p&gt;</text>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta B&lt;/p&gt;</text>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta C&lt;/p&gt;</text>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta D&lt;/p&gt;</text>
    </answer>
</question>
</quiz>';

echo "=== STEP 1: TROVA/CREA CATEGORIA ===\n\n";

// Usa categoria top del corso
$parent_cat = $DB->get_record_sql("
    SELECT qc.* FROM {question_categories} qc
    WHERE qc.contextid = ? AND qc.parent = 0
    ORDER BY qc.id ASC LIMIT 1
", [$context->id]);

if (!$parent_cat) {
    $parent_cat = new stdClass();
    $parent_cat->name = 'Default per ' . $course->shortname;
    $parent_cat->contextid = $context->id;
    $parent_cat->info = '';
    $parent_cat->infoformat = FORMAT_HTML;
    $parent_cat->parent = 0;
    $parent_cat->sortorder = 999;
    $parent_cat->stamp = make_unique_id_code();
    $parent_cat->id = $DB->insert_record('question_categories', $parent_cat);
}
echo "Categoria parent: {$parent_cat->id}\n";

// Crea/trova sottocategoria
$cat_name = 'TEST_V2_' . date('Ymd_His');
$test_cat = new stdClass();
$test_cat->name = $cat_name;
$test_cat->contextid = $context->id;
$test_cat->info = '';
$test_cat->infoformat = FORMAT_HTML;
$test_cat->parent = $parent_cat->id;
$test_cat->sortorder = 999;
$test_cat->stamp = make_unique_id_code();
$test_cat->id = $DB->insert_record('question_categories', $test_cat);
echo "Categoria test: {$test_cat->id} ($cat_name)\n\n";

echo "=== STEP 2: IMPORT DOMANDE ===\n\n";

preg_match_all('/<question type="multichoice">(.*?)<\/question>/s', $xml, $questions);
$question_ids = [];

foreach ($questions[0] as $idx => $qxml) {
    echo "--- Domanda " . ($idx + 1) . " ---\n";
    
    // Nome
    preg_match('/<n>\s*<text>(.*?)<\/text>\s*<\/n>/s', $qxml, $name_match);
    $full_name = isset($name_match[1]) ? trim($name_match[1]) : 'Q' . ($idx + 1);
    echo "Nome: $full_name\n";
    
    // Testo
    preg_match('/<questiontext[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $text_match);
    $qtext = isset($text_match[1]) ? html_entity_decode($text_match[1]) : '';
    
    // Risposte
    preg_match_all('/<answer fraction="(\d+)"[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $answers);
    echo "Risposte: " . count($answers[0]) . "\n";
    
    // 1. question_bank_entries
    $qbe = new stdClass();
    $qbe->questioncategoryid = $test_cat->id;
    $qbe->ownerid = $USER->id;
    $qbe->idnumber = null;
    $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
    
    // 2. question
    $question = new stdClass();
    $question->name = $full_name;
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
    echo "Question ID: {$question->id}\n";
    
    // 3. question_versions
    $qv = new stdClass();
    $qv->questionbankentryid = $qbe->id;
    $qv->questionid = $question->id;
    $qv->version = 1;
    $qv->status = 'ready';
    $DB->insert_record('question_versions', $qv);
    
    // 4. qtype_multichoice_options
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
    
    // 5. question_answers
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
    
    $count = $DB->count_records('question_answers', ['question' => $question->id]);
    echo "✅ Inserite $count risposte\n\n";
    
    $question_ids[] = ['questionid' => $question->id, 'qbeid' => $qbe->id];
}

echo "=== STEP 3: CREA QUIZ (metodo Moodle API) ===\n\n";

// Usa le API Moodle per creare il quiz
$moduleinfo = new stdClass();
$moduleinfo->modulename = 'quiz';
$moduleinfo->name = 'TEST_V2_' . date('His');
$moduleinfo->intro = '<p>Quiz di test</p>';
$moduleinfo->introformat = FORMAT_HTML;
$moduleinfo->course = $courseid;
$moduleinfo->section = 0;
$moduleinfo->visible = 1;
$moduleinfo->timeopen = 0;
$moduleinfo->timeclose = 0;
$moduleinfo->timelimit = 0;
$moduleinfo->gradecat = 0;
$moduleinfo->attempts = 0;
$moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
$moduleinfo->questionsperpage = 0;
$moduleinfo->shuffleanswers = 1;
$moduleinfo->preferredbehaviour = 'deferredfeedback';
$moduleinfo->navmethod = QUIZ_NAVMETHOD_FREE;
$moduleinfo->quizpassword = '';
$moduleinfo->subnet = '';
$moduleinfo->browsersecurity = '-';
$moduleinfo->delay1 = 0;
$moduleinfo->delay2 = 0;
$moduleinfo->showuserpicture = 0;
$moduleinfo->showblocks = 0;
$moduleinfo->completionattemptsexhausted = 0;
$moduleinfo->completionminattempts = 0;
$moduleinfo->allowofflineattempts = 0;

// Aggiungi i campi necessari per create_module
$moduleinfo->cmidnumber = '';
$moduleinfo->groupmode = 0;
$moduleinfo->groupingid = 0;

try {
    $moduleinfo = create_module($moduleinfo);
    echo "Quiz creato: {$moduleinfo->instance}\n";
    echo "Course module: {$moduleinfo->coursemodule}\n\n";
    
    $quizobj = \mod_quiz\quiz_settings::create($moduleinfo->coursemodule);
    $structure = $quizobj->get_structure();
    
    echo "=== STEP 4: AGGIUNGI DOMANDE AL QUIZ ===\n\n";
    
    foreach ($question_ids as $idx => $qdata) {
        $slot = $idx + 1;
        
        // Usa l'API di quiz per aggiungere la domanda
        quiz_add_quiz_question($qdata['questionid'], $quizobj->get_quiz(), 0, 1.0);
        
        echo "✅ Aggiunta domanda {$qdata['questionid']} allo slot $slot\n";
    }
    
    // Aggiorna sumgrades
    quiz_update_sumgrades($quizobj->get_quiz());
    
    echo "\n=== VERIFICA FINALE ===\n\n";
    
    // Verifica slot
    $slots = $DB->get_records('quiz_slots', ['quizid' => $moduleinfo->instance]);
    echo "Slots nel quiz: " . count($slots) . "\n";
    
    foreach ($slots as $slot) {
        echo "  Slot {$slot->slot}: questionid riferimento...\n";
    }
    
    // Verifica question_references
    $refs = $DB->get_records_sql("
        SELECT qr.*, qs.slot 
        FROM {question_references} qr
        JOIN {quiz_slots} qs ON qs.id = qr.itemid
        WHERE qr.component = 'mod_quiz' 
        AND qr.questionarea = 'slot'
        AND qs.quizid = ?
    ", [$moduleinfo->instance]);
    echo "Question references: " . count($refs) . "\n\n";
    
    foreach ($refs as $ref) {
        echo "  Slot {$ref->slot}: qbeid={$ref->questionbankentryid}\n";
    }
    
    echo "</pre>";
    
    $quiz_url = new moodle_url('/mod/quiz/view.php', ['id' => $moduleinfo->coursemodule]);
    echo "<h2><a href='$quiz_url' target='_blank'>🎯 APRI IL QUIZ</a></h2>";
    
} catch (Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}
