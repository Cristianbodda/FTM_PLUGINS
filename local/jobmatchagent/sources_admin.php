<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin page to manage RSS/Atom job feed sources.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/sources_admin.php'));
$PAGE->set_title(get_string('src_title', 'local_jobmatchagent'));
$PAGE->set_heading(get_string('src_title', 'local_jobmatchagent'));
$PAGE->set_pagelayout('admin');

global $DB, $USER;

$action = optional_param('action', 'list', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);
$message = '';
$messagetype = 'success';

if ($action === 'delete' && $id > 0 && confirm_sesskey()) {
    $DB->delete_records('local_jobmatch_sources', ['id' => $id]);
    $message = get_string('src_deleted', 'local_jobmatchagent');
    $action = 'list';
}

if ($action === 'toggle' && $id > 0 && confirm_sesskey()) {
    $src = $DB->get_record('local_jobmatch_sources', ['id' => $id], '*', MUST_EXIST);
    $DB->set_field('local_jobmatch_sources', 'enabled', $src->enabled ? 0 : 1, ['id' => $id]);
    $message = get_string('src_toggled', 'local_jobmatchagent');
    $action = 'list';
}

if ($action === 'save' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $url = required_param('url', PARAM_URL);
    $enabled = optional_param('enabled', 0, PARAM_INT) ? 1 : 0;
    $editid = optional_param('editid', 0, PARAM_INT);

    $config = json_encode(['url' => $url]);

    if ($editid > 0) {
        $DB->update_record('local_jobmatch_sources', (object) [
            'id' => $editid,
            'name' => $name,
            'type' => 'rss',
            'config' => $config,
            'enabled' => $enabled,
            'timemodified' => time(),
        ]);
    } else {
        $DB->insert_record('local_jobmatch_sources', (object) [
            'name' => $name,
            'type' => 'rss',
            'config' => $config,
            'enabled' => $enabled,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
    $message = get_string('src_saved', 'local_jobmatchagent');
    $action = 'list';
}

echo $OUTPUT->header();

$dashurl = new moodle_url('/local/jobmatchagent/coach_dashboard.php');
echo html_writer::link($dashurl, '← ' . get_string('coachdashboard', 'local_jobmatchagent'),
    ['class' => 'btn btn-link']);

if ($message !== '') {
    echo $OUTPUT->notification($message, 'notify' . $messagetype);
}

echo html_writer::tag('p', get_string('src_intro', 'local_jobmatchagent'));

// ============ FORM (add/edit) ============
if ($action === 'edit' || $action === 'add') {
    $edit = null;
    if ($action === 'edit' && $id > 0) {
        $edit = $DB->get_record('local_jobmatch_sources', ['id' => $id], '*', MUST_EXIST);
    }
    $config = $edit ? json_decode($edit->config ?: '{}', true) : [];

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class' => 'card p-3 mb-4 mt-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
    if ($edit) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'editid', 'value' => $edit->id]);
    }

    echo html_writer::div(
        html_writer::label(get_string('src_name', 'local_jobmatchagent'), 'src_name') .
        html_writer::empty_tag('input', [
            'type' => 'text', 'name' => 'name', 'id' => 'src_name',
            'class' => 'form-control', 'required' => 'required', 'maxlength' => 100,
            'value' => $edit ? s($edit->name) : '',
        ]),
        'form-group mb-3'
    );

    echo html_writer::div(
        html_writer::label(get_string('src_url', 'local_jobmatchagent'), 'src_url') .
        html_writer::empty_tag('input', [
            'type' => 'url', 'name' => 'url', 'id' => 'src_url',
            'class' => 'form-control', 'required' => 'required',
            'placeholder' => 'https://www.example.com/jobs/feed.rss',
            'value' => $edit ? s($config['url'] ?? '') : '',
        ]) .
        html_writer::div(get_string('src_url_desc', 'local_jobmatchagent'), 'form-text text-muted'),
        'form-group mb-3'
    );

    echo html_writer::div(
        html_writer::checkbox('enabled', 1, $edit ? (bool) $edit->enabled : true,
            ' ' . get_string('src_enabled', 'local_jobmatchagent'),
            ['class' => 'form-check-input']),
        'form-check mb-3'
    );

    echo html_writer::tag('button', get_string('src_save', 'local_jobmatchagent'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo ' ';
    echo html_writer::link($PAGE->url, get_string('cancel'), ['class' => 'btn btn-outline-secondary']);

    echo html_writer::end_tag('form');
}

// ============ LIST ============
echo html_writer::link(
    new moodle_url($PAGE->url, ['action' => 'add']),
    '+ ' . get_string('src_add', 'local_jobmatchagent'),
    ['class' => 'btn btn-primary mb-3']
);

$sources = $DB->get_records('local_jobmatch_sources', null, 'name ASC');

if (empty($sources)) {
    echo $OUTPUT->notification(get_string('src_none', 'local_jobmatchagent'), 'notifyinfo');
    echo html_writer::div(
        html_writer::tag('strong', get_string('src_examples', 'local_jobmatchagent')) .
        html_writer::tag('ul',
            html_writer::tag('li', 'Cantone Ticino posti di lavoro: <code>https://www4.ti.ch/can/rss/posti-di-lavoro/</code>') .
            html_writer::tag('li', 'Esempi aggiuntivi: cercare "rss feed lavoro" su Google per siti svizzeri')
        ),
        'alert alert-light mt-3'
    );
} else {
    $table = new html_table();
    $table->head = [
        get_string('src_name', 'local_jobmatchagent'),
        get_string('src_url', 'local_jobmatchagent'),
        get_string('src_enabled', 'local_jobmatchagent'),
        get_string('src_last_fetch', 'local_jobmatchagent'),
        get_string('src_last_error', 'local_jobmatchagent'),
        get_string('cd_actions', 'local_jobmatchagent'),
    ];
    $table->attributes['class'] = 'generaltable table-striped';

    foreach ($sources as $s) {
        $config = json_decode($s->config ?: '{}', true) ?: [];
        $url = $config['url'] ?? '';

        $enabledbadge = $s->enabled
            ? html_writer::span('✅ ' . get_string('src_on', 'local_jobmatchagent'), 'badge bg-success')
            : html_writer::span('⏸ ' . get_string('src_off', 'local_jobmatchagent'), 'badge bg-secondary');

        $lastfetch = $s->last_fetch ? userdate($s->last_fetch) : '—';
        $lasterror = $s->last_error
            ? html_writer::span(s(substr($s->last_error, 0, 80)), 'text-danger small')
            : '—';

        $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $s->id]);
        $toggleurl = new moodle_url($PAGE->url,
            ['action' => 'toggle', 'id' => $s->id, 'sesskey' => sesskey()]);
        $deleteurl = new moodle_url($PAGE->url,
            ['action' => 'delete', 'id' => $s->id, 'sesskey' => sesskey()]);

        $actions = html_writer::link($editurl, '✏', ['class' => 'btn btn-sm btn-outline-secondary me-1',
            'title' => get_string('edit')]);
        $actions .= ' ' . html_writer::link($toggleurl, $s->enabled ? '⏸' : '▶',
            ['class' => 'btn btn-sm btn-outline-info me-1',
                'title' => $s->enabled ? get_string('src_disable', 'local_jobmatchagent') : get_string('src_enable', 'local_jobmatchagent')]);
        $actions .= ' ' . html_writer::link($deleteurl, '🗑',
            ['class' => 'btn btn-sm btn-outline-danger',
                'title' => get_string('delete'),
                'onclick' => 'return confirm(' . json_encode(get_string('src_confirm_delete', 'local_jobmatchagent')) . ');']);

        $table->data[] = [
            s($s->name),
            $url ? html_writer::link($url, shorten_text(s($url), 60), ['target' => '_blank', 'rel' => 'noopener']) : '—',
            $enabledbadge,
            $lastfetch,
            $lasterror,
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
