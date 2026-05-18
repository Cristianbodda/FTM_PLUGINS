<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student view: list of published matches with CV snapshot, breakdown,
 * AI explanation, and feedback actions.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:viewown', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/student_view.php'));
$PAGE->set_title(get_string('sv_title', 'local_jobmatchagent'));
$PAGE->set_heading(get_string('sv_title', 'local_jobmatchagent'));
$PAGE->set_pagelayout('standard');

global $USER, $DB;

$results = $DB->get_records_sql(
    "SELECT r.*, o.title AS offer_title, o.company AS offer_company, o.location AS offer_location,
            o.url AS offer_url, o.parsed_text AS offer_text, o.work_schedule AS offer_schedule,
            o.company_size AS offer_size,
            cv.cv_text AS cv_text, cv.timecreated AS cv_time
     FROM {local_jobmatch_results} r
     INNER JOIN {local_jobmatch_offers} o ON o.id = r.offer_id
     LEFT JOIN {local_jobmatch_cv_snapshots} cv ON cv.id = r.cv_snapshot_id
     WHERE r.userid = :uid AND r.status = 'published'
     ORDER BY r.published_to_student_at DESC",
    ['uid' => $USER->id]
);

// Mark as seen first time the student views.
$nowunseen = $DB->get_records_select('local_jobmatch_results',
    "userid = :uid AND status = 'published' AND seen_by_student_at IS NULL",
    ['uid' => $USER->id], '', 'id');
foreach ($nowunseen as $r) {
    $DB->set_field('local_jobmatch_results', 'seen_by_student_at', time(), ['id' => $r->id]);
}

// Existing actions per result.
$actions = [];
if (!empty($results)) {
    $rids = array_keys($results);
    list($insql, $inparams) = $DB->get_in_or_equal($rids, SQL_PARAMS_NAMED, 'rid');
    $arows = $DB->get_records_sql(
        "SELECT * FROM {local_jobmatch_student_actions} WHERE result_id $insql ORDER BY timecreated DESC",
        $inparams
    );
    foreach ($arows as $a) {
        if (!isset($actions[$a->result_id])) {
            $actions[$a->result_id] = $a; // most recent (we ordered desc)
        }
    }
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('sv_intro', 'local_jobmatchagent'), ['class' => 'lead']);

if (empty($results)) {
    echo $OUTPUT->notification(get_string('sv_no_matches', 'local_jobmatchagent'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

foreach ($results as $r) {
    $scoreclass = 'bg-secondary';
    if ($r->score_global >= 70) {
        $scoreclass = 'bg-success';
    } else if ($r->score_global >= 40) {
        $scoreclass = 'bg-warning text-dark';
    } else if ($r->score_global >= 10) {
        $scoreclass = 'bg-info text-dark';
    }

    $existingaction = $actions[$r->id] ?? null;

    echo html_writer::start_div('card mb-4 shadow');

    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
    echo html_writer::tag('h4',
        s($r->offer_title) .
        ($r->offer_company ? ' — ' . html_writer::tag('small', s($r->offer_company), ['class' => 'text-muted']) : ''),
        ['class' => 'mb-0']
    );
    echo html_writer::span(
        $r->score_global . '%',
        'badge ' . $scoreclass . ' fs-4'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('card-body');

    // Meta line.
    $metaparts = [];
    if ($r->offer_location) {
        $metaparts[] = '📍 ' . s($r->offer_location);
    }
    if ($r->offer_schedule && $r->offer_schedule !== 'unknown') {
        $key = 'sf_schedule_' . $r->offer_schedule;
        $metaparts[] = '⏱ ' . (get_string_manager()->string_exists($key, 'local_jobmatchagent') ? get_string($key, 'local_jobmatchagent') : s($r->offer_schedule));
    }
    if ($r->offer_size && $r->offer_size !== 'U') {
        $key = 'sf_company_size_' . strtolower($r->offer_size);
        $metaparts[] = '🏢 ' . (get_string_manager()->string_exists($key, 'local_jobmatchagent') ? get_string($key, 'local_jobmatchagent') : s($r->offer_size));
    }
    if ($r->published_to_student_at) {
        $metaparts[] = get_string('sv_published_at', 'local_jobmatchagent', userdate($r->published_to_student_at, '%d/%m/%Y'));
    }
    echo html_writer::div(implode(' &nbsp;|&nbsp; ', $metaparts), 'text-muted small mb-3');

    // Why this match (AI explanation).
    echo html_writer::tag('h5', '✨ ' . get_string('sv_why_match', 'local_jobmatchagent'));
    if (!empty($r->ai_explanation_text)) {
        echo html_writer::div(format_text($r->ai_explanation_text, FORMAT_PLAIN),
            'border-start border-4 border-success ps-3 mb-3');
    } else {
        echo html_writer::div(get_string('cr_no_ai_yet', 'local_jobmatchagent'),
            'text-muted fst-italic mb-3');
    }

    // Score breakdown.
    echo html_writer::tag('h6', get_string('cr_breakdown', 'local_jobmatchagent'));
    echo html_writer::start_div('mb-3');
    $criteria = [
        'sector' => $r->score_sector,
        'experience' => $r->score_experience,
        'distance' => $r->score_distance,
        'schedule' => $r->score_schedule,
        'size' => $r->score_size,
        'activity' => $r->score_activity,
    ];
    foreach ($criteria as $key => $val) {
        if ($val === null) {
            continue;
        }
        $label = get_string('score_' . $key, 'local_jobmatchagent');
        $barclass = $val >= 70 ? 'bg-success' : ($val >= 40 ? 'bg-warning' : 'bg-danger');
        echo html_writer::start_div('mb-1');
        echo html_writer::div($label . ' — ' . $val . '%', 'small');
        echo html_writer::start_div('progress', ['style' => 'height: 8px;']);
        echo html_writer::div('', 'progress-bar ' . $barclass,
            ['style' => 'width: ' . max(0, min(100, (int) $val)) . '%;']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    // Offer original (collapsible).
    echo html_writer::tag('details',
        html_writer::tag('summary', '📄 ' . get_string('cr_offer', 'local_jobmatchagent'),
            ['class' => 'fw-bold mb-2']) .
        html_writer::div(nl2br(s($r->offer_text)), 'p-2 bg-light border rounded small') .
        ($r->offer_url
            ? html_writer::div(
                html_writer::link($r->offer_url, '🔗 ' . get_string('sv_view_offer', 'local_jobmatchagent'),
                    ['target' => '_blank', 'rel' => 'noopener', 'class' => 'btn btn-sm btn-outline-primary mt-2']),
                'mt-2')
            : ''),
        ['class' => 'mb-3']
    );

    // CV used (collapsible).
    if (!empty($r->cv_text)) {
        echo html_writer::tag('details',
            html_writer::tag('summary', '📋 ' . get_string('sv_cv_used', 'local_jobmatchagent'),
                ['class' => 'fw-bold mb-2']) .
            html_writer::div(nl2br(s($r->cv_text)), 'p-2 bg-light border rounded small'),
            ['class' => 'mb-3']
        );
    }

    // Feedback actions.
    if ($existingaction) {
        echo $OUTPUT->notification(
            get_string('sv_action_saved', 'local_jobmatchagent') . ' (' . s($existingaction->action) . ')',
            'notifyinfo'
        );
    } else {
        $actformurl = new moodle_url('/local/jobmatchagent/student_action.php');
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => $actformurl->out(false), 'class' => 'mt-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $r->id]);

        echo html_writer::div(
            html_writer::label(get_string('sv_reason', 'local_jobmatchagent'), 'reason_' . $r->id) .
            html_writer::tag('textarea', '', [
                'name' => 'reason_text', 'id' => 'reason_' . $r->id,
                'class' => 'form-control form-control-sm', 'rows' => 2,
            ]),
            'mb-2'
        );

        echo html_writer::start_div('d-flex gap-2 flex-wrap');

        // Interested → could redirect to JobAIDA precompiled.
        echo html_writer::tag('button', '✅ ' . get_string('sv_action_interested', 'local_jobmatchagent'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'interested', 'class' => 'btn btn-success']);

        echo html_writer::tag('button', '⏭ ' . get_string('sv_action_not_interested', 'local_jobmatchagent'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'not_interested', 'class' => 'btn btn-outline-secondary']);

        echo html_writer::tag('button', '📝 ' . get_string('sv_action_already_applied', 'local_jobmatchagent'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'already_applied', 'class' => 'btn btn-outline-info']);

        echo html_writer::end_div();
        echo html_writer::end_tag('form');
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

echo $OUTPUT->footer();
