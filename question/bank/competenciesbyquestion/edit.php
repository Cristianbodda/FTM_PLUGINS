<?php
// Page to assign a competency to a question.
require(__DIR__ . '/../../../config.php');

use context;
use context_course;
use moodle_url;
use qbank_competenciesbyquestion\local\manager;

// ID della domanda.
$questionid = required_param('id', PARAM_INT);

// Recupera la domanda.
$question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);

// Recupera il contesto dalla tabella question_bank_entries
$sql = "SELECT qbe.questioncategoryid, qc.contextid
        FROM {question_bank_entries} qbe
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qbe.id = (SELECT qbe2.id 
                        FROM {question_versions} qv
                        JOIN {question_bank_entries} qbe2 ON qbe2.id = qv.questionbankentryid
                        WHERE qv.questionid = :questionid
                        ORDER BY qv.version DESC
                        LIMIT 1)";

$categoryinfo = $DB->get_record_sql($sql, ['questionid' => $questionid], MUST_EXIST);
$context = context::instance_by_id($categoryinfo->contextid);

// Controllo permessi: chi può modificare le domande.
require_capability('moodle/question:editall', $context);

// URL di ritorno al deposito delle domande.
$coursecontext = $context->get_course_context(false);
if ($coursecontext instanceof context_course) {
    $returnurl = new moodle_url('/question/edit.php', ['courseid' => $coursecontext->instanceid]);
} else {
    $returnurl = new moodle_url('/question/edit.php');
}

// Impostazioni pagina.
$PAGE->set_url(new moodle_url('/question/bank/competenciesbyquestion/edit.php', ['id' => $questionid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('editcompetencypagetitle', 'qbank_competenciesbyquestion'));
$PAGE->set_heading(get_string('editcompetencypagetitle', 'qbank_competenciesbyquestion'));

// Costruzione form "a mano" (niente moodleform per semplificare il codice nel repo).
if (optional_param('save', false, PARAM_BOOL) && confirm_sesskey()) {
    $competencyid = optional_param('competencyid', 0, PARAM_INT);
    $difficultylevel = optional_param('difficultylevel', manager::LEVEL_BASE, PARAM_INT);
    
    // Salva competenza e livello di difficoltà
    manager::set_competency_for_question($questionid, $competencyid ?: null, $difficultylevel);
    
    redirect($returnurl, get_string('editcompetencysaved', 'qbank_competenciesbyquestion'));
}

// Dati attuali.
$mapping = manager::get_mapping($questionid);
$currentcompetencyid = $mapping ? $mapping->competencyid : 0;
$currentlevel = $mapping ? $mapping->difficultylevel : manager::LEVEL_BASE;

$competencyoptions = manager::get_competency_options();
$leveloptions = manager::get_difficulty_options();

echo $OUTPUT->header();
echo html_writer::tag('h3', format_string($question->name));

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $questionid]);

// Sezione Competenza
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('competency', 'qbank_competenciesbyquestion'), 'id_competencyid');
echo ' ';
echo html_writer::select($competencyoptions, 'competencyid', $currentcompetencyid, []);
echo html_writer::end_div();

// Sezione Livello di difficoltà
echo html_writer::start_div('form-group', ['style' => 'margin-top: 1em;']);
echo html_writer::label(get_string('difficultylevel', 'qbank_competenciesbyquestion'), 'id_difficultylevel');
echo ' ';
echo html_writer::select($leveloptions, 'difficultylevel', $currentlevel, false);

// Link di aiuto (opzionale)
if (get_string_manager()->string_exists('difficultylevel_help', 'qbank_competenciesbyquestion')) {
    echo ' ';
    echo $OUTPUT->help_icon('difficultylevel', 'qbank_competenciesbyquestion');
}

echo html_writer::end_div();

// Bottone salva
echo html_writer::start_div('form-group', ['style' => 'margin-top: 1.5em;']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'save',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary'
]);
echo ' ';
echo html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $OUTPUT->footer();