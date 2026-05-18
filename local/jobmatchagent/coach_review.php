<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Review matches for a single student. Coach decides publish/discard/onhold.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$studentid = required_param('userid', PARAM_INT);
$show = optional_param('show', 'pending', PARAM_ALPHA); // pending, published, discarded

global $USER, $DB;

if (!\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $studentid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$studentname = fullname($student);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $studentid]));
$PAGE->set_title(get_string('cr_title', 'local_jobmatchagent', $studentname));
$PAGE->set_heading(get_string('cr_title', 'local_jobmatchagent', $studentname));
$PAGE->set_pagelayout('admin');

// Determine status filter.
$statusfilter = ['pending', 'ai_done'];
if ($show === 'published') {
    $statusfilter = ['published'];
} else if ($show === 'discarded') {
    $statusfilter = ['discarded', 'onhold'];
}

list($insql, $inparams) = $DB->get_in_or_equal($statusfilter, SQL_PARAMS_NAMED, 'st');
$inparams['uid'] = $studentid;

$results = $DB->get_records_sql(
    "SELECT r.*, o.title AS offer_title, o.company AS offer_company, o.location AS offer_location,
            o.url AS offer_url, o.parsed_text AS offer_text, o.work_schedule AS offer_schedule,
            o.company_size AS offer_size
     FROM {local_jobmatch_results} r
     INNER JOIN {local_jobmatch_offers} o ON o.id = r.offer_id
     WHERE r.userid = :uid AND r.status $insql
     ORDER BY r.score_global DESC, r.timecreated DESC",
    $inparams
);

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('cr_intro', 'local_jobmatchagent'));

// Tabs.
$tabs = [
    'pending' => get_string('cd_pending', 'local_jobmatchagent'),
    'published' => get_string('cr_show_published', 'local_jobmatchagent'),
    'discarded' => get_string('cr_show_discarded', 'local_jobmatchagent'),
];
echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-3']);
foreach ($tabs as $key => $label) {
    $url = new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $studentid, 'show' => $key]);
    $active = $show === $key ? ' active' : '';
    echo html_writer::tag('li',
        html_writer::link($url, $label, ['class' => 'nav-link' . $active]),
        ['class' => 'nav-item']);
}
echo html_writer::end_tag('ul');

$dashurl = new moodle_url('/local/jobmatchagent/coach_dashboard.php');
$searchurl = new moodle_url('/local/jobmatchagent/run_search.php', [
    'userid' => $studentid,
    'sesskey' => sesskey(),
    'returnurl' => $PAGE->url->out_as_local_url(false),
]);
$filtersurl = new moodle_url('/local/jobmatchagent/student_filters.php', ['userid' => $studentid]);

$clearurl = new moodle_url('/local/jobmatchagent/clear_results.php', [
    'userid' => $studentid,
    'sesskey' => sesskey(),
    'snapshots' => 1,
    'returnurl' => $PAGE->url->out_as_local_url(false),
]);

echo html_writer::start_div('d-flex gap-2 mb-3 flex-wrap');
echo html_writer::link($dashurl, '← ' . get_string('coachdashboard', 'local_jobmatchagent'),
    ['class' => 'btn btn-link']);
echo html_writer::link($filtersurl, '⚙ ' . get_string('cd_setfilters', 'local_jobmatchagent'),
    ['class' => 'btn btn-outline-secondary']);
echo html_writer::link($searchurl, '🔍 ' . get_string('cd_runsearch', 'local_jobmatchagent'),
    [
        'class' => 'btn btn-info',
        'onclick' => 'return confirm(' . json_encode(get_string('rs_confirm', 'local_jobmatchagent', $studentname)) . ');',
    ]);
echo html_writer::link($clearurl, '🗑 Reset (cancella tutto)',
    [
        'class' => 'btn btn-outline-danger',
        'title' => 'Cancella tutti i match per testare da zero',
        'onclick' => 'return confirm(' . json_encode('Cancellare TUTTI i match (pending+published+discarded) e snapshot CV per ' . $studentname . '? Operazione non reversibile.') . ');',
    ]);
echo html_writer::end_div();

if (empty($results)) {
    echo $OUTPUT->notification(get_string('cr_no_pending', 'local_jobmatchagent'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

// Render each match as a card.
foreach ($results as $r) {
    $scoreclass = 'bg-secondary';
    if ($r->score_global >= 70) {
        $scoreclass = 'bg-success';
    } else if ($r->score_global >= 40) {
        $scoreclass = 'bg-warning text-dark';
    } else if ($r->score_global >= 10) {
        $scoreclass = 'bg-info text-dark';
    }

    echo html_writer::start_div('card mb-3 shadow-sm');
    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
    echo html_writer::tag('h5',
        s($r->offer_title) .
        ($r->offer_company ? ' — ' . html_writer::tag('small', s($r->offer_company), ['class' => 'text-muted']) : ''),
        ['class' => 'mb-0']
    );
    echo html_writer::span(
        $r->score_global . '% ' . get_string('cr_score', 'local_jobmatchagent'),
        'badge ' . $scoreclass . ' fs-5'
    );
    echo html_writer::end_div(); // card-header

    echo html_writer::start_div('card-body');

    // Meta line.
    $metaparts = [];
    if (!empty($r->offer_location)) {
        $metaparts[] = html_writer::tag('strong', get_string('ao_location', 'local_jobmatchagent') . ':') . ' ' . s($r->offer_location);
    }
    if (!empty($r->offer_schedule) && $r->offer_schedule !== 'unknown') {
        $key = 'sf_schedule_' . $r->offer_schedule;
        $metaparts[] = html_writer::tag('strong', get_string('ao_work_schedule', 'local_jobmatchagent') . ':') . ' ' .
            (get_string_manager()->string_exists($key, 'local_jobmatchagent') ? get_string($key, 'local_jobmatchagent') : s($r->offer_schedule));
    }
    if (!empty($r->offer_size) && $r->offer_size !== 'U') {
        $key = 'sf_company_size_' . strtolower($r->offer_size);
        $metaparts[] = html_writer::tag('strong', get_string('ao_company_size', 'local_jobmatchagent') . ':') . ' ' .
            (get_string_manager()->string_exists($key, 'local_jobmatchagent') ? get_string($key, 'local_jobmatchagent') : s($r->offer_size));
    }
    if (!empty($r->offer_url)) {
        $metaparts[] = html_writer::link($r->offer_url, '🔗 ' . s($r->offer_url),
            ['target' => '_blank', 'rel' => 'noopener']);
    }
    if (!empty($metaparts)) {
        echo html_writer::div(implode(' &nbsp;|&nbsp; ', $metaparts), 'text-muted small mb-2');
    }

    // Score breakdown table.
    echo html_writer::start_div('row mb-3');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('h6', get_string('cr_breakdown', 'local_jobmatchagent'));
    $breakdown = new html_table();
    $breakdown->attributes['class'] = 'table table-sm';
    $breakdown->head = ['', '%'];
    $breakdown->data = [
        [get_string('score_sector', 'local_jobmatchagent'), $r->score_sector . '%'],
        [get_string('score_distance', 'local_jobmatchagent'), $r->score_distance . '%'],
        [get_string('score_schedule', 'local_jobmatchagent'), $r->score_schedule . '%'],
        [get_string('score_size', 'local_jobmatchagent'), $r->score_size . '%'],
        [get_string('score_activity', 'local_jobmatchagent'), ($r->score_activity ?? 0) . '%'],
        [get_string('score_experience', 'local_jobmatchagent') . ' (AI)',
            $r->score_experience !== null ? $r->score_experience . '%' :
                html_writer::tag('em', get_string('cr_no_ai_yet', 'local_jobmatchagent'), ['class' => 'text-muted'])],
    ];
    echo html_writer::table($breakdown);
    echo html_writer::end_div(); // col-md-6

    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('h6', get_string('cr_explanation', 'local_jobmatchagent'));
    if (!empty($r->ai_explanation_text)) {
        echo html_writer::div(format_text($r->ai_explanation_text, FORMAT_PLAIN),
            'border rounded p-2 bg-light');
    } else {
        echo html_writer::div(get_string('cr_no_ai_yet', 'local_jobmatchagent'),
            'text-muted fst-italic');
    }
    echo html_writer::end_div(); // col-md-6
    echo html_writer::end_div(); // row

    // Offer text preview (collapsible).
    $offerpreview = shorten_text(strip_tags($r->offer_text), 600, true);
    echo html_writer::tag('details',
        html_writer::tag('summary', get_string('cr_offer', 'local_jobmatchagent'), ['class' => 'fw-bold']) .
        html_writer::div(nl2br(s($offerpreview)), 'mt-2 p-2 bg-light border rounded small'),
        ['class' => 'mb-3']
    );

    // Action buttons.
    if (in_array($r->status, ['pending', 'ai_done'], true)) {
        echo html_writer::start_div('d-flex gap-2');

        $publishform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/jobmatchagent/ajax_publish.php'),
            'class' => 'd-inline',
        ]);
        $publishform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $publishform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $r->id]);
        $publishform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnurl',
            'value' => $PAGE->url->out(false) . '&show=' . $show]);

        echo $publishform . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => 'published']) .
            html_writer::tag('button', get_string('cr_publish', 'local_jobmatchagent'),
                ['type' => 'submit', 'class' => 'btn btn-success']) . '</form>';

        echo $publishform . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => 'discarded']) .
            html_writer::tag('button', get_string('cr_discard', 'local_jobmatchagent'),
                ['type' => 'submit', 'class' => 'btn btn-outline-danger']) . '</form>';

        echo $publishform . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => 'onhold']) .
            html_writer::tag('button', get_string('cr_onhold', 'local_jobmatchagent'),
                ['type' => 'submit', 'class' => 'btn btn-outline-secondary']) . '</form>';

        echo html_writer::end_div();
    } else {
        $statuslabel = '';
        if ($r->status === 'published' && $r->coach_decision_time) {
            $statuslabel = get_string('cr_published', 'local_jobmatchagent', userdate($r->coach_decision_time));
        } else if ($r->status === 'discarded' && $r->coach_decision_time) {
            $statuslabel = get_string('cr_discarded', 'local_jobmatchagent', userdate($r->coach_decision_time));
        } else if ($r->status === 'onhold' && $r->coach_decision_time) {
            $statuslabel = get_string('cr_onhold_at', 'local_jobmatchagent', userdate($r->coach_decision_time));
        }
        if ($statuslabel) {
            echo html_writer::div($statuslabel, 'text-muted small');
        }
        if (!empty($r->coach_note)) {
            echo html_writer::div('<strong>' . get_string('cr_note', 'local_jobmatchagent') . ':</strong> ' . s($r->coach_note),
                'mt-1 small');
        }
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

echo $OUTPUT->footer();
