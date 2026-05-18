<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Coach configures search filters for a single student.
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
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

global $USER, $DB;

if (!\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $studentid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$studentname = fullname($student);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/student_filters.php', ['userid' => $studentid]));
$PAGE->set_title(get_string('sf_title', 'local_jobmatchagent', $studentname));
$PAGE->set_heading(get_string('sf_title', 'local_jobmatchagent', $studentname));
$PAGE->set_pagelayout('admin');

$existing = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid]);

$message = '';
if ($action === 'save' && confirm_sesskey()) {
    $active = optional_param('active', 0, PARAM_INT) ? 1 : 0;
    $homeaddress = optional_param('home_address', '', PARAM_TEXT);
    $homelat = optional_param('home_lat', '', PARAM_RAW_TRIMMED);
    $homelng = optional_param('home_lng', '', PARAM_RAW_TRIMMED);
    $maxkm = optional_param('max_distance_km', 30, PARAM_INT);

    $sizes = optional_param_array('company_sizes', [], PARAM_ALPHA);
    $sizescsv = implode(',', array_intersect($sizes, ['S', 'M', 'L']));

    $scheds = optional_param_array('work_schedules', [], PARAM_ALPHANUMEXT);
    $allowedscheds = ['fulltime', 'parttime', 'shifts', 'flex'];
    $schedscsv = implode(',', array_intersect($scheds, $allowedscheds));

    $activitiesraw = optional_param('desired_activities', '', PARAM_TEXT);
    $lines = preg_split('/\r?\n/', trim($activitiesraw));
    $activities = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $activities[] = $line;
        }
    }
    $activitiesjson = empty($activities) ? null : json_encode(array_values($activities));

    $extranotes = optional_param('extra_notes', '', PARAM_TEXT);
    $manualcv = optional_param('manual_cv_text', '', PARAM_RAW);
    $clearcv = optional_param('clear_cv', 0, PARAM_INT);
    if ($clearcv) {
        $manualcv = '';
    }

    $rec = (object) [
        'userid' => $studentid,
        'active' => $active,
        'home_address' => $homeaddress ?: null,
        'home_lat' => $homelat !== '' ? (float) $homelat : null,
        'home_lng' => $homelng !== '' ? (float) $homelng : null,
        'max_distance_km' => $maxkm > 0 ? $maxkm : 30,
        'company_sizes' => $sizescsv ?: null,
        'work_schedules' => $schedscsv ?: null,
        'desired_activities' => $activitiesjson,
        'extra_notes' => $extranotes ?: null,
        'manual_cv_text' => trim($manualcv) !== '' ? $manualcv : null,
        'updatedby' => $USER->id,
        'timemodified' => time(),
    ];

    if ($existing) {
        $rec->id = $existing->id;
        $DB->update_record('local_jobmatch_student_filters', $rec);
    } else {
        $rec->timecreated = time();
        $DB->insert_record('local_jobmatch_student_filters', $rec);
    }

    // Trigger immediate matching against existing offers.
    if ($active) {
        \local_jobmatchagent\match_engine::match_all_active_offers_to_student($studentid);
    }

    $message = get_string('sf_saved', 'local_jobmatchagent');
    $existing = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid]);
}

// Defaults for form rendering.
$f = $existing ?: (object) [
    'active' => 1,
    'home_address' => '',
    'home_lat' => '',
    'home_lng' => '',
    'max_distance_km' => 30,
    'company_sizes' => '',
    'work_schedules' => '',
    'desired_activities' => null,
    'extra_notes' => '',
    'manual_cv_text' => '',
];

// Detect available CVs for info banner.
$jobaidacv = \local_jobmatchagent\matcher::get_latest_cv($studentid);
$hasmanualcv = !empty($f->manual_cv_text);
$hasjobaidacv = !empty($jobaidacv);

$activitieslist = '';
if (!empty($f->desired_activities)) {
    $arr = json_decode($f->desired_activities, true);
    if (is_array($arr)) {
        $activitieslist = implode("\n", $arr);
    }
}
$selectedsizes = array_filter(explode(',', $f->company_sizes ?? ''));
$selectedscheds = array_filter(explode(',', $f->work_schedules ?? ''));

echo $OUTPUT->header();

if ($message !== '') {
    echo $OUTPUT->notification($message, 'notifysuccess');
}

echo html_writer::tag('p', get_string('sf_intro', 'local_jobmatchagent'));

$dashurl = new moodle_url('/local/jobmatchagent/coach_dashboard.php');
echo html_writer::link($dashurl, '← ' . get_string('coachdashboard', 'local_jobmatchagent'),
    ['class' => 'btn btn-link']);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class' => 'mt-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

// Active toggle.
echo html_writer::div(
    html_writer::checkbox('active', 1, $f->active, ' ' . get_string('sf_active', 'local_jobmatchagent'),
        ['class' => 'form-check-input']) .
    html_writer::div(get_string('sf_active_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-check mb-3'
);

// Home address.
echo html_writer::div(
    html_writer::label(get_string('sf_home_address', 'local_jobmatchagent'), 'sf_home_address') .
    html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'home_address', 'id' => 'sf_home_address',
        'class' => 'form-control', 'value' => s($f->home_address ?? ''),
    ]) .
    html_writer::div(get_string('sf_home_address_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-group mb-3'
);

// Lat/Lng (optional, manual).
echo html_writer::start_div('row mb-3');
echo html_writer::start_div('col-md-6');
echo html_writer::label(get_string('sf_home_lat', 'local_jobmatchagent'), 'sf_home_lat');
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'home_lat', 'id' => 'sf_home_lat',
    'class' => 'form-control', 'value' => s($f->home_lat ?? ''),
    'placeholder' => '46.0103',
]);
echo html_writer::end_div();
echo html_writer::start_div('col-md-6');
echo html_writer::label(get_string('sf_home_lng', 'local_jobmatchagent'), 'sf_home_lng');
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'home_lng', 'id' => 'sf_home_lng',
    'class' => 'form-control', 'value' => s($f->home_lng ?? ''),
    'placeholder' => '8.9598',
]);
echo html_writer::end_div();
echo html_writer::end_div();

// Max distance.
echo html_writer::div(
    html_writer::label(get_string('sf_max_distance', 'local_jobmatchagent'), 'sf_max_distance') .
    html_writer::empty_tag('input', [
        'type' => 'number', 'name' => 'max_distance_km', 'id' => 'sf_max_distance',
        'class' => 'form-control', 'value' => (int) $f->max_distance_km, 'min' => 1, 'max' => 500,
        'style' => 'max-width: 200px;',
    ]) .
    html_writer::div(get_string('sf_max_distance_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-group mb-3'
);

// Company sizes (multi checkbox).
$sizesblock = '';
foreach (['S', 'M', 'L'] as $code) {
    $key = 'sf_company_size_' . strtolower($code);
    $sizesblock .= html_writer::div(
        html_writer::checkbox('company_sizes[]', $code, in_array($code, $selectedsizes, true),
            ' ' . get_string($key, 'local_jobmatchagent'),
            ['class' => 'form-check-input']),
        'form-check form-check-inline'
    );
}
echo html_writer::div(
    html_writer::tag('label', get_string('sf_company_sizes', 'local_jobmatchagent'), ['class' => 'form-label d-block']) .
    $sizesblock,
    'form-group mb-3'
);

// Work schedules (multi checkbox).
$schedsblock = '';
foreach (['fulltime', 'parttime', 'shifts', 'flex'] as $code) {
    $key = 'sf_schedule_' . $code;
    $schedsblock .= html_writer::div(
        html_writer::checkbox('work_schedules[]', $code, in_array($code, $selectedscheds, true),
            ' ' . get_string($key, 'local_jobmatchagent'),
            ['class' => 'form-check-input']),
        'form-check form-check-inline'
    );
}
echo html_writer::div(
    html_writer::tag('label', get_string('sf_work_schedules', 'local_jobmatchagent'), ['class' => 'form-label d-block']) .
    $schedsblock,
    'form-group mb-3'
);

// Desired activities (textarea).
echo html_writer::div(
    html_writer::label(get_string('sf_desired_activities', 'local_jobmatchagent'), 'sf_activities') .
    html_writer::tag('textarea', s($activitieslist), [
        'name' => 'desired_activities', 'id' => 'sf_activities',
        'class' => 'form-control', 'rows' => 5,
        'placeholder' => "manutentore di stabili\nelettricista\nmagazziniere",
    ]) .
    html_writer::div(get_string('sf_desired_activities_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-group mb-3'
);

// Extra notes.
echo html_writer::div(
    html_writer::label(get_string('sf_extra_notes', 'local_jobmatchagent'), 'sf_extranotes') .
    html_writer::tag('textarea', s($f->extra_notes ?? ''), [
        'name' => 'extra_notes', 'id' => 'sf_extranotes',
        'class' => 'form-control', 'rows' => 3,
    ]) .
    html_writer::div(get_string('sf_extra_notes_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-group mb-3'
);

// CV section heading.
echo html_writer::tag('h4', get_string('sf_cv_section', 'local_jobmatchagent'), ['class' => 'mt-4 mb-2']);

// CV source banner.
if ($hasmanualcv) {
    echo html_writer::div(
        '✅ <strong>' . get_string('sf_cv_using_manual', 'local_jobmatchagent') . '</strong>',
        'alert alert-success py-2 mb-2'
    );
} else if ($hasjobaidacv) {
    echo html_writer::div(
        'ℹ️ ' . get_string('sf_cv_using_jobaida', 'local_jobmatchagent'),
        'alert alert-info py-2 mb-2'
    );
} else {
    echo html_writer::div(
        '⚠️ ' . get_string('sf_cv_none', 'local_jobmatchagent'),
        'alert alert-warning py-2 mb-2'
    );
}

// Manual CV textarea.
echo html_writer::div(
    html_writer::label(get_string('sf_manual_cv', 'local_jobmatchagent'), 'sf_manual_cv') .
    html_writer::tag('textarea', s($f->manual_cv_text ?? ''), [
        'name' => 'manual_cv_text', 'id' => 'sf_manual_cv',
        'class' => 'form-control', 'rows' => 12,
        'placeholder' => get_string('sf_manual_cv_placeholder', 'local_jobmatchagent'),
        'style' => 'font-family: monospace; font-size: 0.9em;',
    ]) .
    html_writer::div(get_string('sf_manual_cv_desc', 'local_jobmatchagent'), 'form-text text-muted'),
    'form-group mb-3'
);

if ($hasmanualcv) {
    echo html_writer::div(
        html_writer::checkbox('clear_cv', 1, false, ' ' . get_string('sf_clear_cv', 'local_jobmatchagent'),
            ['class' => 'form-check-input']),
        'form-check mb-3'
    );
}

echo html_writer::tag('button', get_string('sf_save', 'local_jobmatchagent'), [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg',
]);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
