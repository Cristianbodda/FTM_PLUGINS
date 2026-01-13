<?php
/**
 * TEST IMPORT v4 - Tutto manuale
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

echo "<h1>🧪 Test Import v4</h1>";
echo "<pre style='background:#f0f0f0; padding:20px;'>";

// XML
$xml = '<quiz>
<question type="multichoice">
    <n><text>TEST_V4_Q01</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda 1?&lt;/p&gt;</text></questiontext>
    <answer fraction="0" format="html"><text>&lt;p&gt;A&lt;/p&gt;</text></answer>
    <answer fraction="100" format="html"><text>&lt;p&gt;B CORRETTA&lt;/p&gt;</text></answer>
    <answer fraction="0" format="html"><text>&lt;p&gt;C&lt;/p&gt;</text></answer>
    <answer fraction="0" format="html"><text>&lt;p&gt;D&lt;/p&gt;</text></answer>
</question>
<question type="multichoice">
    <n><text>TEST_V4_Q02</text></n>
    <questiontext format="html"><text>&lt;p&gt;Domanda 2?&lt;/p&gt;</text></questiontext>
    <answer fraction="100" format="html"><text>&lt;p&gt;A CORRETTA&lt;/p&gt;</text></answer>
    <answer fraction="0" format="html"><text>&lt;p&gt;B&lt;/p&gt;</text></answer>
    <answer fraction="0" format="html"><text>&lt;p&gt;C&lt;/p&gt;</text></answer>
    <answer fraction="0" format="html"><text>&lt;p&gt;D&lt;/p&gt;</text></answer>
</question>
</quiz>';

// Categoria
$parent_cat = $DB->get_record_sql("
    SELECT * FROM {question_categories}
    WHERE contextid = ? AND parent = 0
    ORDER BY id LIMIT 1
", [$context->id]);

if (!$parent_cat) {
    $parent_cat = new stdClass();
    $parent_cat->name = 'Default';
    $parent_cat->contextid = $context->id;
    $parent_cat->info = '';
    $parent_cat->infoformat = FORMAT_HTML;
    $parent_cat->parent = 0;
    $parent_cat->sortorder = 999;
    $parent_cat->stamp = make_unique_id_code();
    $parent_cat->id = $DB->insert_record('question_categories', $parent_cat);
}

$test_cat = new stdClass();
$test_cat->name = 'TEST_V4_' . time();
$test_cat->contextid = $context->id;
$test_cat->info = '';
$test_cat->infoformat = FORMAT_HTML;
$test_cat->parent = $parent_cat->id;
$test_cat->sortorder = 999;
$test_cat->stamp = make_unique_id_code();
$test_cat->id = $DB->insert_record('question_categories', $test_cat);

echo "Categoria: {$test_cat->id}\n\n";

// Import domande
preg_match_all('/<question type="multichoice">(.*?)<\/question>/s', $xml, $questions);
$question_data = [];

foreach ($questions[0] as $idx => $qxml) {
    preg_match('/<n>\s*<text>(.*?)<\/text>\s*<\/n>/s', $qxml, $nm);
    $name = $nm[1] ?? 'Q'.($idx+1);
    
    preg_match('/<questiontext[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $tm);
    $text = html_entity_decode($tm[1] ?? '');
    
    preg_match_all('/<answer fraction="(\d+)"[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $ans);
    
    // question_bank_entries
    $qbe = new stdClass();
    $qbe->questioncategoryid = $test_cat->id;
    $qbe->ownerid = $USER->id;
    $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
    
    // question
    $q = new stdClass();
    $q->name = $name;
    $q->questiontext = $text;
    $q->questiontextformat = FORMAT_HTML;
    $q->generalfeedback = '';
    $q->generalfeedbackformat = FORMAT_HTML;
    $q->defaultmark = 1;
    $q->penalty = 0.3333333;
    $q->qtype = 'multichoice';
    $q->length = 1;
    $q->stamp = make_unique_id_code();
    $q->timecreated = time();
    $q->timemodified = time();
    $q->createdby = $USER->id;
    $q->modifiedby = $USER->id;
    $q->id = $DB->insert_record('question', $q);
    
    // question_versions
    $qv = new stdClass();
    $qv->questionbankentryid = $qbe->id;
    $qv->questionid = $q->id;
    $qv->version = 1;
    $qv->status = 'ready';
    $DB->insert_record('question_versions', $qv);
    
    // multichoice_options
    $opt = new stdClass();
    $opt->questionid = $q->id;
    $opt->single = 1;
    $opt->shuffleanswers = 1;
    $opt->answernumbering = 'abc';
    $opt->correctfeedback = '';
    $opt->correctfeedbackformat = FORMAT_HTML;
    $opt->partiallycorrectfeedback = '';
    $opt->partiallycorrectfeedbackformat = FORMAT_HTML;
    $opt->incorrectfeedback = '';
    $opt->incorrectfeedbackformat = FORMAT_HTML;
    $opt->shownumcorrect = 0;
    $DB->insert_record('qtype_multichoice_options', $opt);
    
    // answers
    for ($i = 0; $i < count($ans[0]); $i++) {
        $a = new stdClass();
        $a->question = $q->id;
        $a->answer = html_entity_decode($ans[2][$i]);
        $a->answerformat = FORMAT_HTML;
        $a->fraction = $ans[1][$i] / 100;
        $a->feedback = '';
        $a->feedbackformat = FORMAT_HTML;
        $DB->insert_record('question_answers', $a);
    }
    
    echo "✅ Domanda {$q->id}: {$name} - " . count($ans[0]) . " risposte\n";
    $question_data[] = ['questionid' => $q->id, 'qbeid' => $qbe->id];
}

echo "\n=== CREA QUIZ ===\n\n";

// Crea quiz
$quiz = new stdClass();
$quiz->course = $courseid;
$quiz->name = 'TEST_V4_' . date('His');
$quiz->intro = 'Test';
$quiz->introformat = FORMAT_HTML;
$quiz->timeopen = 0;
$quiz->timeclose = 0;
$quiz->timelimit = 0;
$quiz->overduehandling = 'autosubmit';
$quiz->graceperiod = 0;
$quiz->preferredbehaviour = 'deferredfeedback';
$quiz->canredoquestions = 0;
$quiz->attempts = 0;
$quiz->attemptonlast = 0;
$quiz->grademethod = 1;
$quiz->decimalpoints = 2;
$quiz->questiondecimalpoints = -1;
$quiz->reviewattempt = 69888;
$quiz->reviewcorrectness = 4352;
$quiz->reviewmaxmarks = 4352;
$quiz->reviewmarks = 4352;
$quiz->reviewspecificfeedback = 4352;
$quiz->reviewgeneralfeedback = 4352;
$quiz->reviewrightanswer = 4352;
$quiz->reviewoverallfeedback = 4352;
$quiz->questionsperpage = 1;
$quiz->navmethod = 'free';
$quiz->shuffleanswers = 1;
$quiz->sumgrades = count($question_data);
$quiz->grade = 10;
$quiz->timecreated = time();
$quiz->timemodified = time();
$quiz->password = '';
$quiz->subnet = '';
$quiz->browsersecurity = '-';
$quiz->delay1 = 0;
$quiz->delay2 = 0;
$quiz->showuserpicture = 0;
$quiz->showblocks = 0;
$quiz->completionattemptsexhausted = 0;
$quiz->completionminattempts = 0;
$quiz->allowofflineattempts = 0;
$quiz->id = $DB->insert_record('quiz', $quiz);

echo "Quiz ID: {$quiz->id}\n";

// Course module
$module = $DB->get_record('modules', ['name' => 'quiz']);
$cm = new stdClass();
$cm->course = $courseid;
$cm->module = $module->id;
$cm->instance = $quiz->id;
$cm->section = 1;
$cm->added = time();
$cm->visible = 1;
$cm->visibleoncoursepage = 1;
$cm->id = $DB->insert_record('course_modules', $cm);

echo "Course module: {$cm->id}\n";

// Sezione
$section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);
if ($section) {
    $seq = empty($section->sequence) ? $cm->id : $section->sequence . ',' . $cm->id;
    $DB->set_field('course_sections', 'sequence', $seq, ['id' => $section->id]);
}

// Context del modulo
$cmcontext = context_module::instance($cm->id);

echo "\n=== AGGIUNGI DOMANDE MANUALMENTE ===\n\n";

$slot = 1;
foreach ($question_data as $qd) {
    // quiz_slots
    $qs = new stdClass();
    $qs->quizid = $quiz->id;
    $qs->slot = $slot;
    $qs->page = 1;
    $qs->displaynumber = null;
    $qs->requireprevious = 0;
    $qs->maxmark = 1.0;
    $qs->id = $DB->insert_record('quiz_slots', $qs);
    
    // question_references - QUESTO È IL PUNTO CRITICO
    $qr = new stdClass();
    $qr->usingcontextid = $cmcontext->id;  // Context del course module, non del corso!
    $qr->component = 'mod_quiz';
    $qr->questionarea = 'slot';
    $qr->itemid = $qs->id;
    $qr->questionbankentryid = $qd['qbeid'];
    $qr->version = null;  // null = usa ultima versione
    $qr->id = $DB->insert_record('question_references', $qr);
    
    echo "✅ Slot $slot: question {$qd['questionid']}, qbe {$qd['qbeid']}, ref {$qr->id}\n";
    $slot++;
}

// Aggiorna sumgrades
$DB->set_field('quiz', 'sumgrades', count($question_data), ['id' => $quiz->id]);

rebuild_course_cache($courseid, true);

echo "\n=== VERIFICA FINALE ===\n\n";

// Verifica completa
$slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
echo "Slots nel quiz: " . count($slots) . "\n\n";

foreach ($slots as $s) {
    $ref = $DB->get_record('question_references', [
        'component' => 'mod_quiz',
        'questionarea' => 'slot',
        'itemid' => $s->id
    ]);
    
    if ($ref) {
        $qv = $DB->get_record('question_versions', ['questionbankentryid' => $ref->questionbankentryid]);
        if ($qv) {
            $qname = $DB->get_field('question', 'name', ['id' => $qv->questionid]);
            $cnt = $DB->count_records('question_answers', ['question' => $qv->questionid]);
            echo "Slot {$s->slot}:\n";
            echo "  - quiz_slots.id: {$s->id}\n";
            echo "  - question_references.id: {$ref->id}\n";
            echo "  - questionbankentryid: {$ref->questionbankentryid}\n";
            echo "  - usingcontextid: {$ref->usingcontextid}\n";
            echo "  - questionid: {$qv->questionid}\n";
            echo "  - nome: $qname\n";
            echo "  - risposte: $cnt\n\n";
        }
    }
}

echo "</pre>";

$url = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
echo "<h2><a href='$url' target='_blank'>🎯 APRI QUIZ</a></h2>";
