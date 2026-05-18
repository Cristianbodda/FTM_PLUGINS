<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manually trigger a job search for a single student.
 * Re-runs matching against all active offers in the catalog and shows
 * a clear result page with statistics.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$studentid = required_param('userid', PARAM_INT);

global $USER, $DB;

if (!\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $studentid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$studentname = fullname($student);

// Pre-checks.
$filters = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid]);
if (!$filters) {
    redirect(
        new moodle_url('/local/jobmatchagent/student_filters.php', ['userid' => $studentid]),
        get_string('rs_no_filters', 'local_jobmatchagent'),
        4,
        \core\output\notification::NOTIFY_WARNING
    );
}
if (!$filters->active) {
    redirect(
        new moodle_url('/local/jobmatchagent/student_filters.php', ['userid' => $studentid]),
        get_string('rs_agent_off', 'local_jobmatchagent'),
        4,
        \core\output\notification::NOTIFY_WARNING
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/run_search.php', ['userid' => $studentid]));
$PAGE->set_title(get_string('rs_title', 'local_jobmatchagent', $studentname));
$PAGE->set_heading(get_string('rs_title', 'local_jobmatchagent', $studentname));
$PAGE->set_pagelayout('admin');

// Run the search.
$starttime = microtime(true);
$stats = \local_jobmatchagent\match_engine::match_all_active_offers_to_student_detailed($studentid);
$durationms = (int) round((microtime(true) - $starttime) * 1000);

// Audit log.
$DB->insert_record('local_jobmatch_logs', (object) [
    'run_time' => time(),
    'task_name' => 'manual_search',
    'source_id' => null,
    'offers_fetched' => 0,
    'results_created' => $stats['new_matches'],
    'ai_calls' => 0,
    'ai_tokens' => 0,
    'errors_json' => null,
    'duration_ms' => $durationms,
]);

$threshold = \local_jobmatchagent\matcher::get_threshold();

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('rs_intro', 'local_jobmatchagent', $studentname), ['class' => 'lead']);

// Result summary card.
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

if ($stats['offers_in_catalog'] === 0) {
    echo $OUTPUT->notification(get_string('rs_no_offers', 'local_jobmatchagent'), 'notifywarning');
} else if ($stats['new_matches'] === 0) {
    echo $OUTPUT->notification(
        get_string('rs_no_new', 'local_jobmatchagent', $threshold),
        'notifyinfo'
    );
} else {
    echo $OUTPUT->notification(
        get_string('rs_success', 'local_jobmatchagent', $stats['new_matches']),
        'notifysuccess'
    );
}

// Stats table.
$table = new html_table();
$table->attributes['class'] = 'table table-bordered';
$table->data = [
    [
        '<strong>' . get_string('rs_offers_checked', 'local_jobmatchagent') . '</strong>',
        html_writer::span($stats['offers_in_catalog'], 'badge bg-secondary fs-6'),
    ],
    [
        '✅ <strong>' . get_string('rs_new_matches', 'local_jobmatchagent') . '</strong>',
        html_writer::span(
            $stats['new_matches'],
            'badge ' . ($stats['new_matches'] > 0 ? 'bg-success' : 'bg-light text-dark') . ' fs-6'
        ),
    ],
    [
        '⏭ ' . get_string('rs_already_done', 'local_jobmatchagent'),
        html_writer::span($stats['skipped_already_done'], 'badge bg-light text-dark fs-6'),
    ],
    [
        '❌ ' . get_string('rs_below_threshold', 'local_jobmatchagent', $threshold),
        html_writer::span($stats['below_threshold'], 'badge bg-light text-dark fs-6'),
    ],
];
echo html_writer::table($table);

echo html_writer::div(
    html_writer::tag('small',
        'Durata: ' . $durationms . ' ms · Eseguito da ' . fullname($USER) . ' il ' . userdate(time()),
        ['class' => 'text-muted']
    ),
    'mb-2'
);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Action buttons.
echo html_writer::start_div('d-flex gap-2');

if ($stats['new_matches'] > 0) {
    echo html_writer::link(
        new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $studentid]),
        '👀 ' . get_string('rs_view_matches', 'local_jobmatchagent'),
        ['class' => 'btn btn-primary btn-lg']
    );
}

echo html_writer::link(
    new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
    '← ' . get_string('rs_back_dashboard', 'local_jobmatchagent'),
    ['class' => 'btn btn-outline-secondary']
);

echo html_writer::end_div();

echo $OUTPUT->footer();
