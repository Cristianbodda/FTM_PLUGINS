<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manual job offer entry form.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:addoffer', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/add_offer_manual.php'));
$PAGE->set_title(get_string('ao_title', 'local_jobmatchagent'));
$PAGE->set_heading(get_string('ao_title', 'local_jobmatchagent'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$message = '';
$messagetype = 'info';

if ($action === 'save' && confirm_sesskey()) {
    $url = optional_param('offer_url', '', PARAM_URL);
    $title = required_param('title', PARAM_TEXT);
    $company = optional_param('company', '', PARAM_TEXT);
    $location = optional_param('location', '', PARAM_TEXT);
    $companysize = optional_param('company_size', '', PARAM_ALPHA);
    $workschedule = optional_param('work_schedule', '', PARAM_ALPHANUMEXT);
    $text = required_param('offer_text', PARAM_RAW);

    if (trim($title) === '') {
        $message = get_string('err_no_title', 'local_jobmatchagent');
        $messagetype = 'danger';
    } else if (trim($text) === '') {
        $message = get_string('err_no_text', 'local_jobmatchagent');
        $messagetype = 'danger';
    } else {
        // Make sure manual source exists.
        $sourceid = $DB->get_field('local_jobmatch_sources', 'id', ['type' => 'manual']);
        if (!$sourceid) {
            $sourceid = $DB->insert_record('local_jobmatch_sources', (object) [
                'name' => 'Inserimento manuale',
                'type' => 'manual',
                'config' => null,
                'enabled' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $fingerprint = \local_jobmatchagent\match_engine::offer_fingerprint($title, $company, $url, $text);

        if ($DB->record_exists('local_jobmatch_offers', ['fingerprint' => $fingerprint])) {
            $message = get_string('ao_duplicate', 'local_jobmatchagent');
            $messagetype = 'warning';
        } else {
            $offer = (object) [
                'source_id' => $sourceid,
                'external_id' => null,
                'url' => $url ?: null,
                'title' => $title,
                'company' => $company ?: null,
                'location' => $location ?: null,
                'location_lat' => null,
                'location_lng' => null,
                'company_size' => $companysize ?: 'U',
                'work_schedule' => $workschedule ?: 'unknown',
                'raw_html' => null,
                'parsed_text' => $text,
                'fingerprint' => $fingerprint,
                'published_at' => time(),
                'expires_at' => null,
                'status' => 'active',
                'timecreated' => time(),
            ];
            $offerid = $DB->insert_record('local_jobmatch_offers', $offer);

            $matches = \local_jobmatchagent\match_engine::match_offer_to_all_active_students($offerid);
            $message = get_string('ao_saved', 'local_jobmatchagent', $matches);
            $messagetype = 'success';
        }
    }
}

echo $OUTPUT->header();

if ($message !== '') {
    echo $OUTPUT->notification($message, 'notify' . $messagetype);
}

echo html_writer::tag('p', get_string('ao_intro', 'local_jobmatchagent'));

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

$row = function($label, $field) {
    return html_writer::div(
        html_writer::label($label, '') . $field,
        'form-group mb-3'
    );
};

echo $row(
    get_string('ao_jobtitle', 'local_jobmatchagent') . ' *',
    html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'title',
        'class' => 'form-control', 'required' => 'required', 'maxlength' => 255,
    ])
);

echo $row(
    get_string('ao_company', 'local_jobmatchagent'),
    html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'company',
        'class' => 'form-control', 'maxlength' => 255,
    ])
);

echo $row(
    get_string('ao_url', 'local_jobmatchagent'),
    html_writer::empty_tag('input', [
        'type' => 'url', 'name' => 'offer_url',
        'class' => 'form-control', 'placeholder' => 'https://...',
    ])
);

echo $row(
    get_string('ao_location', 'local_jobmatchagent'),
    html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'location',
        'class' => 'form-control', 'placeholder' => 'Es. Lugano, TI',
    ])
);

$sizeselect = html_writer::select(
    [
        'U' => get_string('ao_company_size_unknown', 'local_jobmatchagent'),
        'S' => get_string('sf_company_size_s', 'local_jobmatchagent'),
        'M' => get_string('sf_company_size_m', 'local_jobmatchagent'),
        'L' => get_string('sf_company_size_l', 'local_jobmatchagent'),
    ],
    'company_size', 'U', false, ['class' => 'form-select']
);
echo $row(get_string('ao_company_size', 'local_jobmatchagent'), $sizeselect);

$schedselect = html_writer::select(
    [
        'unknown' => get_string('ao_company_size_unknown', 'local_jobmatchagent'),
        'fulltime' => get_string('sf_schedule_fulltime', 'local_jobmatchagent'),
        'parttime' => get_string('sf_schedule_parttime', 'local_jobmatchagent'),
        'shifts' => get_string('sf_schedule_shifts', 'local_jobmatchagent'),
        'flex' => get_string('sf_schedule_flex', 'local_jobmatchagent'),
    ],
    'work_schedule', 'unknown', false, ['class' => 'form-select']
);
echo $row(get_string('ao_work_schedule', 'local_jobmatchagent'), $schedselect);

echo $row(
    get_string('ao_text', 'local_jobmatchagent') . ' *',
    html_writer::tag('textarea', '', [
        'name' => 'offer_text',
        'class' => 'form-control', 'rows' => 12, 'required' => 'required',
        'placeholder' => get_string('ao_text_desc', 'local_jobmatchagent'),
    ])
);

echo html_writer::tag('button', get_string('ao_save', 'local_jobmatchagent'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
