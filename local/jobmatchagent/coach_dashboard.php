<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Coach dashboard: list of managed students with match counts.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Explicit require_once to bypass Moodle's autoloader cache after fresh upload.
require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/match_engine.php');
if (file_exists(__DIR__ . '/classes/source_manager.php')) {
    require_once(__DIR__ . '/classes/source_manager.php');
}

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/coach_dashboard.php'));
$PAGE->set_title(get_string('cd_title', 'local_jobmatchagent'));
$PAGE->set_heading(get_string('cd_title', 'local_jobmatchagent'));
$PAGE->set_pagelayout('admin');

global $USER, $DB;

$students = \local_jobmatchagent\match_engine::get_coach_students($USER->id);

// Bulk fetch counts for all students (one query).
$counts = [];
$filterstatus = [];
if (!empty($students)) {
    $userids = array_keys($students);
    list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

    $rows = $DB->get_records_sql(
        "SELECT userid, status, COUNT(*) AS cnt
         FROM {local_jobmatch_results}
         WHERE userid $insql
         GROUP BY userid, status",
        $inparams
    );
    foreach ($rows as $r) {
        if (!isset($counts[$r->userid])) {
            $counts[$r->userid] = ['pending' => 0, 'ai_done' => 0, 'published' => 0, 'discarded' => 0, 'onhold' => 0];
        }
        $counts[$r->userid][$r->status] = (int) $r->cnt;
    }

    $filters = $DB->get_records_select(
        'local_jobmatch_student_filters',
        "userid $insql",
        $inparams,
        '',
        'userid, active'
    );
    foreach ($filters as $f) {
        $filterstatus[$f->userid] = (int) $f->active;
    }
}

echo $OUTPUT->header();

// Banner guidato: invita all'uso del wizard.
echo html_writer::div(
    '<h5 class="mb-2">🧙 Modalita guidata disponibile</h5>'
    . '<p class="mb-2">Per cercare opportunita in modo semplice (3 step guidati: scegli studente → imposta CV → vedi risultati) usa il <strong>Wizard</strong>.</p>'
    . html_writer::link(
        new moodle_url('/local/jobmatchagent/wizard.php'),
        '🧙 Apri il Wizard guidato →',
        ['class' => 'btn btn-primary btn-lg']
    ),
    'alert alert-info'
);

echo html_writer::tag('h4', '⚙ Modalita avanzata (dashboard tecnica)', ['class' => 'mt-4 mb-3']);
echo html_writer::tag('p', get_string('cd_intro', 'local_jobmatchagent'));

// Catalog status.
$totaloffers = $DB->count_records('local_jobmatch_offers', ['status' => 'active']);
$totalsources = $DB->count_records('local_jobmatch_sources', ['enabled' => 1]);
$aiavailable = false;
if (class_exists('\local_jobmatchagent\source_manager')) {
    $aiavailable = \local_jobmatchagent\source_manager::is_ftm_jobsearch_available();
}

echo html_writer::div(
    html_writer::tag('strong', get_string('cd_catalog', 'local_jobmatchagent') . ': ') .
    html_writer::span($totaloffers . ' ' . get_string('cd_catalog_offers', 'local_jobmatchagent'),
        'badge bg-secondary me-2') .
    html_writer::span($totalsources . ' RSS ' . get_string('cd_catalog_sources', 'local_jobmatchagent'),
        'badge bg-info me-2') .
    ($aiavailable
        ? html_writer::span('🤖 AI Scraper attivo (jobs.ch / randstad / carriera)', 'badge bg-success')
        : html_writer::span('⚠ AI Scraper non disponibile', 'badge bg-warning text-dark')),
    'alert alert-light py-2 mb-3'
);

echo html_writer::start_div('d-flex gap-2 flex-wrap mb-3');
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/fetch_now.php', ['sesskey' => sesskey()]),
    '🔄 ' . get_string('cd_fetch_now', 'local_jobmatchagent'),
    [
        'class' => 'btn btn-primary btn-lg',
        'onclick' => 'return confirm(' . json_encode(get_string('cd_fetch_confirm', 'local_jobmatchagent')) . ');',
    ]
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/run_ai_matching.php', ['sesskey' => sesskey(), 'force' => 1]),
    '🧠 Esegui AI matching (FORCE)',
    [
        'class' => 'btn btn-success btn-lg',
        'title' => 'Valuta CV vs TUTTE le offerte pending (force, anche se già valutate)',
    ]
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/test_ai.php', ['sesskey' => sesskey()]),
    '🔬 Test AI',
    [
        'class' => 'btn btn-outline-success btn-lg',
        'title' => 'Diagnosi: verifica API key e funzionamento AI con sample CV',
    ]
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/sources_admin.php'),
    '⚙ ' . get_string('cd_search_settings', 'local_jobmatchagent'),
    ['class' => 'btn btn-warning btn-lg']
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/add_offer_manual.php'),
    '+ ' . get_string('cd_addoffer', 'local_jobmatchagent'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();

if (empty($students)) {
    echo $OUTPUT->notification(get_string('cd_nostudents', 'local_jobmatchagent'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('cd_student', 'local_jobmatchagent'),
    get_string('cd_filters', 'local_jobmatchagent'),
    get_string('cd_pending', 'local_jobmatchagent') . ' / ' .
        get_string('score_global', 'local_jobmatchagent') . '+AI',
    get_string('cd_published', 'local_jobmatchagent'),
    get_string('cd_discarded', 'local_jobmatchagent'),
    get_string('cd_actions', 'local_jobmatchagent'),
];
$table->attributes['class'] = 'generaltable table-striped';

foreach ($students as $s) {
    $name = fullname($s);
    $c = $counts[$s->id] ?? ['pending' => 0, 'ai_done' => 0, 'published' => 0, 'discarded' => 0, 'onhold' => 0];
    $hasfilters = isset($filterstatus[$s->id]);
    $isactive = $hasfilters && $filterstatus[$s->id] === 1;

    $statusbadge = $hasfilters
        ? html_writer::span(
            $isactive ? get_string('cd_agent_on', 'local_jobmatchagent') : get_string('cd_agent_off', 'local_jobmatchagent'),
            $isactive ? 'badge bg-success' : 'badge bg-secondary'
        )
        : html_writer::span('—', 'badge bg-light text-dark');

    $reviewurl = new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $s->id]);
    $filtersurl = new moodle_url('/local/jobmatchagent/student_filters.php', ['userid' => $s->id]);
    $searchurl = new moodle_url('/local/jobmatchagent/run_search.php', [
        'userid' => $s->id,
        'sesskey' => sesskey(),
    ]);

    $actions = html_writer::link($filtersurl,
        '⚙ ' . get_string('cd_setfilters', 'local_jobmatchagent'),
        ['class' => 'btn btn-sm btn-outline-secondary me-1 mb-1']);

    // "Cerca opportunita" only enabled if agent is active.
    if ($isactive) {
        $actions .= ' ' . html_writer::link($searchurl,
            '🔍 ' . get_string('cd_runsearch', 'local_jobmatchagent'),
            [
                'class' => 'btn btn-sm btn-info me-1 mb-1',
                'title' => get_string('rs_confirm', 'local_jobmatchagent', $name),
                'onclick' => 'return confirm(' . json_encode(get_string('rs_confirm', 'local_jobmatchagent', $name)) . ');',
            ]);
    } else {
        $actions .= ' ' . html_writer::tag('button',
            '🔍 ' . get_string('cd_runsearch', 'local_jobmatchagent'),
            [
                'class' => 'btn btn-sm btn-info me-1 mb-1',
                'disabled' => 'disabled',
                'title' => get_string('rs_agent_off', 'local_jobmatchagent'),
            ]);
    }

    $actions .= ' ' . html_writer::link($reviewurl,
        '👀 ' . get_string('cd_review', 'local_jobmatchagent'),
        ['class' => 'btn btn-sm btn-primary mb-1']);

    $clearurl = new moodle_url('/local/jobmatchagent/clear_results.php', [
        'userid' => $s->id,
        'sesskey' => sesskey(),
        'snapshots' => 1,
        'returnurl' => $PAGE->url->out_as_local_url(false),
    ]);
    $actions .= ' ' . html_writer::link($clearurl,
        '🗑 Reset',
        [
            'class' => 'btn btn-sm btn-outline-danger mb-1',
            'title' => 'Cancella tutti i match e snapshot CV per testare da zero',
            'onclick' => 'return confirm(' . json_encode('Cancellare TUTTI i match (pending+published+discarded) e snapshot CV per ' . $name . '? Operazione non reversibile.') . ');',
        ]);

    $pendingcount = ($c['pending'] ?? 0) + ($c['ai_done'] ?? 0);
    $pendingcell = $pendingcount > 0
        ? html_writer::span($pendingcount, 'badge bg-warning text-dark fs-6')
        : '0';

    $table->data[] = [
        $name,
        $statusbadge,
        $pendingcell,
        ($c['published'] ?? 0),
        ($c['discarded'] ?? 0),
        $actions,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
