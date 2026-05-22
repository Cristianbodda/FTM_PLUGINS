<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manually trigger fetch from all sources (RSS feeds + AI scraper).
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Explicit require_once to bypass Moodle's autoloader cache after fresh upload.
require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/match_engine.php');
require_once(__DIR__ . '/classes/source_manager.php');
require_once(__DIR__ . '/classes/source/rss_fetcher.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/fetch_now.php'));
$PAGE->set_title(get_string('fn_title', 'local_jobmatchagent'));
$PAGE->set_heading(get_string('fn_title', 'local_jobmatchagent'));
$PAGE->set_pagelayout('admin');

core_php_time_limit::raise(300);
raise_memory_limit(MEMORY_EXTRA);

$force = optional_param('force', 0, PARAM_INT) ? true : false;

global $DB, $USER;

$starttime = microtime(true);

// Ensure ti.ch and admin.ch RSS feeds are configured.
\local_jobmatchagent\source_manager::ensure_default_sources();

// Phase 1: RSS feeds (ti.ch, admin.ch + any custom feeds).
$rsstotals = \local_jobmatchagent\source_manager::run_all();

// Phase 2: AI scraper via ftm_jobsearch (jobs.ch / job-room / carriera).
$aitotals = \local_jobmatchagent\source_manager::run_ai_scraping_for_all_students($force);

// Phase 3: AI matching CV -> offerte (uses jobsearch::match_cv_to_offers).
$matchtotals = \local_jobmatchagent\match_engine::process_ai_matching_for_pending();

$durationms = (int) round((microtime(true) - $starttime) * 1000);

$totaloffers = $rsstotals['offers_added'] + $aitotals['offers_imported'];
$totalmatches = $rsstotals['matches_created'] + $aitotals['matches_created'];

$allerrors = array_merge(
    $rsstotals['errors'] ?? [],
    $aitotals['errors'] ?? [],
    array_combine(
        array_map(function ($i) { return 'AI match #' . $i; }, array_keys($matchtotals['errors'] ?? [])),
        $matchtotals['errors'] ?? []
    )
);

// Audit log.
$DB->insert_record('local_jobmatch_logs', (object) [
    'run_time' => time(),
    'task_name' => 'manual_fetch',
    'source_id' => null,
    'offers_fetched' => $totaloffers,
    'results_created' => $totalmatches,
    'ai_calls' => count($aitotals['sectors_scraped'] ?? []) * 3, // 3 sites per sector
    'ai_tokens' => 0,
    'errors_json' => !empty($allerrors) ? json_encode($allerrors) : null,
    'duration_ms' => $durationms,
]);

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('fn_intro', 'local_jobmatchagent'), ['class' => 'lead']);

// Top notification.
if ($totaloffers > 0) {
    echo $OUTPUT->notification(
        get_string('fn_success', 'local_jobmatchagent', (object) [
            'offers' => $totaloffers,
            'matches' => $totalmatches,
        ]),
        'notifysuccess'
    );
} else if (!empty($allerrors)) {
    echo $OUTPUT->notification(get_string('fn_with_errors', 'local_jobmatchagent'), 'notifywarning');
} else {
    echo $OUTPUT->notification(get_string('fn_no_new', 'local_jobmatchagent'), 'notifyinfo');
}

// Defensive: ensure all expected keys exist (in case server has older versions of helper classes).
$rsstotals = array_merge([
    'sources_run' => 0, 'sources_total' => 0, 'offers_added' => 0, 'matches_created' => 0,
    'errors' => [], 'per_source' => [],
], $rsstotals ?: []);

$aitotals = array_merge([
    'available' => false, 'students_processed' => 0,
    'sectors_scraped' => [], 'sectors_cached' => [], 'sectors_failed' => [],
    'offers_imported' => 0, 'matches_created' => 0, 'errors' => [],
], $aitotals ?: []);

$matchtotals = array_merge([
    'available' => false, 'students_processed' => 0, 'ai_calls' => 0,
    'matches_updated' => 0, 'auto_discarded' => 0, 'errors' => [], 'debug' => [],
], $matchtotals ?: []);

// Summary table.
$table = new html_table();
$table->attributes['class'] = 'table table-bordered';
$table->data = [
    [
        '<strong>📡 Fase 1 — RSS</strong>',
        $rsstotals['sources_run'] . ' / ' . $rsstotals['sources_total']
        . ' · ' . $rsstotals['offers_added'] . ' nuovi annunci',
    ],
    [
        '<strong>🤖 Fase 2 — AI Scraper (jobs.ch / job-room / carriera)</strong>',
        $aitotals['available']
            ? (count($aitotals['sectors_scraped']) . ' nuove + ' . count($aitotals['sectors_cached']) . ' da cache · '
                . $aitotals['offers_imported'] . ' annunci importati')
            : '<span class="text-muted">Plugin local_ftm_jobsearch non installato (o source_manager.php obsoleto sul server)</span>',
    ],
    [
        '<strong>🧠 Fase 3 — Match CV → Offerte (AI)</strong>',
        $matchtotals['available']
            ? ($matchtotals['students_processed'] . ' studenti · ' . $matchtotals['ai_calls'] . ' chiamate AI · '
                . $matchtotals['matches_updated'] . ' match valutati ('
                . html_writer::span($matchtotals['auto_discarded'] . ' scartati per CV non compatibile', 'text-danger small') . ')')
            : '<span class="text-muted">AI matching non disponibile (controlla che match_engine.php contenga process_ai_matching_for_pending)</span>',
    ],
    [
        '✅ <strong>Totale nuovi annunci nel catalogo</strong>',
        html_writer::span($totaloffers,
            'badge ' . ($totaloffers > 0 ? 'bg-success' : 'bg-light text-dark') . ' fs-6'),
    ],
    [
        '🎯 <strong>Match in revisione coach (dopo veto AI)</strong>',
        html_writer::span(($matchtotals['matches_updated'] ?? 0) - ($matchtotals['auto_discarded'] ?? 0),
            'badge bg-info fs-6'),
    ],
];
echo html_writer::table($table);

echo html_writer::div(
    html_writer::tag('small',
        'Durata: ' . $durationms . ' ms · Eseguito da ' . fullname($USER) . ' il ' . userdate(time()),
        ['class' => 'text-muted']),
    'mb-3'
);

// Per-RSS source detail.
if (!empty($rsstotals['per_source'])) {
    echo html_writer::tag('h5', '📡 ' . get_string('fn_rss_detail', 'local_jobmatchagent'), ['class' => 'mt-4']);
    $detail = new html_table();
    $detail->head = [
        get_string('src_name', 'local_jobmatchagent'),
        get_string('fn_offers_added', 'local_jobmatchagent'),
        get_string('fn_matches_created', 'local_jobmatchagent'),
        get_string('fn_status', 'local_jobmatchagent'),
    ];
    $detail->attributes['class'] = 'table table-sm';
    foreach ($rsstotals['per_source'] as $name => $r) {
        $status = !empty($r['error'])
            ? html_writer::span('❌ ' . s($r['error']), 'text-danger')
            : html_writer::span('✅ OK', 'text-success');
        $detail->data[] = [s($name), $r['offers_added'], $r['matches_created'], $status];
    }
    echo html_writer::table($detail);
}

// AI scraper detail.
if ($aitotals['available'] && (!empty($aitotals['sectors_scraped']) || !empty($aitotals['sectors_cached']))) {
    echo html_writer::tag('h5', '🤖 ' . get_string('fn_ai_detail', 'local_jobmatchagent'), ['class' => 'mt-4']);
    echo html_writer::tag('p',
        get_string('fn_ai_explanation', 'local_jobmatchagent'),
        ['class' => 'text-muted small']);

    if (!empty($aitotals['sectors_scraped'])) {
        echo html_writer::tag('h6', '🆕 Combo scrappate (nuove richieste AI):');
        echo html_writer::start_tag('ul');
        foreach ($aitotals['sectors_scraped'] as $combo) {
            echo html_writer::tag('li', html_writer::span(s($combo), 'badge bg-success'));
        }
        echo html_writer::end_tag('ul');
    }

    if (!empty($aitotals['sectors_cached'])) {
        echo html_writer::tag('h6', '💾 Combo da cache 24h (nessuna chiamata AI):');
        echo html_writer::start_tag('ul');
        foreach ($aitotals['sectors_cached'] as $combo) {
            echo html_writer::tag('li', html_writer::span(s($combo), 'badge bg-info'));
        }
        echo html_writer::end_tag('ul');

        // Show "force refresh" button if there are cached entries.
        $forceurl = new moodle_url('/local/jobmatchagent/fetch_now.php', [
            'sesskey' => sesskey(), 'force' => 1,
        ]);
        echo html_writer::div(
            html_writer::link($forceurl, '🔁 Forza refresh AI (bypassa cache 24h)',
                ['class' => 'btn btn-sm btn-warning',
                 'onclick' => 'return confirm("Forzare nuove chiamate AI bypassando la cache di 24h? Costera token OpenAI.");']),
            'mb-2'
        );
    }
}

// Errors.
if (!empty($allerrors)) {
    echo html_writer::tag('h5', '⚠ ' . get_string('fn_errors', 'local_jobmatchagent'), ['class' => 'mt-4 text-danger']);
    foreach ($allerrors as $src => $err) {
        echo html_writer::div(
            html_writer::tag('strong', s($src) . ': ') . html_writer::tag('code', s($err)),
            'alert alert-danger py-2'
        );
    }
}

// Action buttons.
echo html_writer::start_div('d-flex gap-2 mt-3 flex-wrap');
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
    '← ' . get_string('rs_back_dashboard', 'local_jobmatchagent'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/sources_admin.php'),
    '⚙ ' . get_string('cd_search_settings', 'local_jobmatchagent'),
    ['class' => 'btn btn-outline-info']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
