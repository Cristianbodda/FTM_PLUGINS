<?php
/**
 * TEST IMPORT DIRETTO - ISOLATO
 * 
 * Questo script bypassa completamente setup_universale.php
 * e inserisce direttamente le domande con le risposte.
 * 
 * Uso: /local/competencyxmlimport/test_import_diretto.php?courseid=XX
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

echo "<h1>🧪 Test Import Diretto</h1>";
echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace;'>";

// XML di test con 2 domande
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<quiz>
<question type="multichoice">
    <n><text>TEST_DIRETTO_Q01 - AUTOMOBILE_MR_A1</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda di test 1: Quale risposta è corretta?&lt;/p&gt;</text></questiontext>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta A - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="100" format="html">
        <text>&lt;p&gt;Risposta B - CORRETTA&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta C - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta D - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
</question>
<question type="multichoice">
    <n><text>TEST_DIRETTO_Q02 - AUTOMOBILE_MR_A2</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda di test 2: Quale altra risposta è corretta?&lt;/p&gt;</text></questiontext>
    <answer fraction="100" format="html">
        <text>&lt;p&gt;Risposta A - CORRETTA&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta B - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta C - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text>&lt;p&gt;Risposta D - sbagliata&lt;/p&gt;</text>
        <feedback format="html"><text /></feedback>
    </answer>
</question>
</quiz>';

echo "=== STEP 1: PARSING XML ===\n\n";

// Estrai domande
preg_match_all('/<question type="multichoice">(.*?)<\/question>/s', $xml, $questions);
echo "Domande trovate: " . count($questions[0]) . "\n\n";

// Trova o crea categoria
$parent_cat = $DB->get_record('question_categories', [
    'contextid' => $context->id,
    'parent' => 0
]);

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
    echo "Creata categoria principale: {$parent_cat->id}\n";
}

// Crea sottocategoria test
$test_cat = $DB->get_record('question_categories', [
    'contextid' => $context->id,
    'name' => 'TEST_IMPORT_DIRETTO',
    'parent' => $parent_cat->id
]);

if (!$test_cat) {
    $test_cat = new stdClass();
    $test_cat->name = 'TEST_IMPORT_DIRETTO';
    $test_cat->contextid = $context->id;
    $test_cat->info = 'Categoria di test';
    $test_cat->infoformat = FORMAT_HTML;
    $test_cat->parent = $parent_cat->id;
    $test_cat->sortorder = 999;
    $test_cat->stamp = make_unique_id_code();
    $test_cat->id = $DB->insert_record('question_categories', $test_cat);
    echo "Creata sottocategoria: {$test_cat->id}\n";
}

echo "\n=== STEP 2: IMPORT DOMANDE ===\n\n";

$question_ids = [];

foreach ($questions[0] as $idx => $qxml) {
    echo "--- Domanda " . ($idx + 1) . " ---\n";
    
    // Estrai nome
    preg_match('/<n>\s*<text>(.*?)<\/text>\s*<\/n>/s', $qxml, $name_match);
    $full_name = isset($name_match[1]) ? trim($name_match[1]) : 'Domanda ' . ($idx + 1);
    echo "Nome: $full_name\n";
    
    // Estrai testo
    preg_match('/<questiontext[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $text_match);
    $qtext = isset($text_match[1]) ? html_entity_decode($text_match[1]) : '';
    echo "Testo: " . substr(strip_tags($qtext), 0, 50) . "...\n";
    
    // Estrai risposte
    preg_match_all('/<answer fraction="(\d+)"[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $answers);
    echo "Risposte trovate nel XML: " . count($answers[0]) . "\n";
    
    if (count($answers[0]) == 0) {
        echo "❌ ERRORE: Nessuna risposta trovata!\n\n";
        continue;
    }
    
    // Elimina domanda esistente con stesso nome
    $existing = $DB->get_record_sql("
        SELECT q.id FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.questioncategoryid = ? AND q.name = ?
    ", [$test_cat->id, $full_name]);
    
    if ($existing) {
        echo "Elimino domanda esistente ID: {$existing->id}\n";
        $DB->delete_records('question_answers', ['question' => $existing->id]);
        $DB->delete_records('qtype_multichoice_options', ['questionid' => $existing->id]);
        $qv = $DB->get_record('question_versions', ['questionid' => $existing->id]);
        if ($qv) {
            $DB->delete_records('question_versions', ['questionid' => $existing->id]);
            $DB->delete_records('question_bank_entries', ['id' => $qv->questionbankentryid]);
        }
        $DB->delete_records('question', ['id' => $existing->id]);
    }
    
    // Crea question_bank_entry
    $qbe = new stdClass();
    $qbe->questioncategoryid = $test_cat->id;
    $qbe->ownerid = $USER->id;
    $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
    echo "Creato question_bank_entry: {$qbe->id}\n";
    
    // Crea question
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
    echo "Creata question: {$question->id}\n";
    
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
    
    // INSERISCI RISPOSTE
    echo "Inserisco risposte:\n";
    for ($i = 0; $i < count($answers[0]); $i++) {
        $ans = new stdClass();
        $ans->question = $question->id;
        $ans->answer = html_entity_decode($answers[2][$i]);
        $ans->answerformat = FORMAT_HTML;
        $ans->fraction = $answers[1][$i] / 100;
        $ans->feedback = '';
        $ans->feedbackformat = FORMAT_HTML;
        $ans->id = $DB->insert_record('question_answers', $ans);
        echo "  ✅ Risposta {$i} inserita (ID: {$ans->id}, fraction: {$ans->fraction})\n";
    }
    
    // VERIFICA IMMEDIATA
    $check = $DB->count_records('question_answers', ['question' => $question->id]);
    echo "VERIFICA: {$check} risposte nel database per questa domanda\n\n";
    
    $question_ids[] = $question->id;
}

echo "=== STEP 3: CREA QUIZ ===\n\n";

// Crea quiz
$quiz = new stdClass();
$quiz->course = $courseid;
$quiz->name = 'TEST_IMPORT_DIRETTO_' . date('His');
$quiz->intro = 'Quiz di test creato automaticamente';
$quiz->introformat = FORMAT_HTML;
$quiz->timeopen = 0;
$quiz->timeclose = 0;
$quiz->timelimit = 0;
$quiz->preferredbehaviour = 'deferredfeedback';
$quiz->attempts = 0;
$quiz->grademethod = 1;
$quiz->grade = 10;
$quiz->sumgrades = count($question_ids);
$quiz->timecreated = time();
$quiz->timemodified = time();
$quiz->id = $DB->insert_record('quiz', $quiz);
echo "Creato quiz: {$quiz->id} - {$quiz->name}\n";

// Aggiungi domande al quiz
$slot = 1;
foreach ($question_ids as $qid) {
    // Quiz slot
    $qs = new stdClass();
    $qs->quizid = $quiz->id;
    $qs->slot = $slot;
    $qs->page = 1;
    $qs->requireprevious = 0;
    $qs->maxmark = 1;
    $qs->id = $DB->insert_record('quiz_slots', $qs);
    
    // Question reference
    $qbe_id = $DB->get_field_sql("
        SELECT qv.questionbankentryid 
        FROM {question_versions} qv 
        WHERE qv.questionid = ?
    ", [$qid]);
    
    $qr = new stdClass();
    $qr->usingcontextid = $context->id;
    $qr->component = 'mod_quiz';
    $qr->questionarea = 'slot';
    $qr->itemid = $qs->id;
    $qr->questionbankentryid = $qbe_id;
    $qr->version = null;
    $DB->insert_record('question_references', $qr);
    
    echo "Aggiunta domanda $qid allo slot $slot\n";
    $slot++;
}

// Crea course module
$module = $DB->get_record('modules', ['name' => 'quiz']);
$cm = new stdClass();
$cm->course = $courseid;
$cm->module = $module->id;
$cm->instance = $quiz->id;
$cm->section = 1;
$cm->added = time();
$cm->visible = 1;
$cm->id = $DB->insert_record('course_modules', $cm);

// Aggiungi alla sezione
$section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);
if ($section) {
    $sequence = empty($section->sequence) ? $cm->id : $section->sequence . ',' . $cm->id;
    $DB->set_field('course_sections', 'sequence', $sequence, ['id' => $section->id]);
}

rebuild_course_cache($courseid, true);

echo "\n=== RISULTATO FINALE ===\n\n";

// Verifica finale
foreach ($question_ids as $qid) {
    $count = $DB->count_records('question_answers', ['question' => $qid]);
    $name = $DB->get_field('question', 'name', ['id' => $qid]);
    $status = $count > 0 ? "✅" : "❌";
    echo "$status Domanda $qid ($name): $count risposte\n";
}

echo "\n</pre>";

$quiz_url = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
echo "<h2><a href='$quiz_url' target='_blank'>🎯 APRI IL QUIZ DI TEST</a></h2>";
echo "<p>Se questo quiz mostra le risposte correttamente, il problema è in setup_universale.php</p>";
