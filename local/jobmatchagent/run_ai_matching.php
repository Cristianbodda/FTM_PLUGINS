<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Standalone Phase 3: run AI matching CV -> offerte for all pending results.
 * Supports force=1 to re-process already AI-evaluated matches.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/match_engine.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$force = optional_param('force', 0, PARAM_INT) ? true : false;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/run_ai_matching.php'));
$PAGE->set_title('AI matching CV → Offerte');
$PAGE->set_heading('AI matching CV → Offerte' . ($force ? ' (FORCE)' : ''));
$PAGE->set_pagelayout('admin');

core_php_time_limit::raise(300);
raise_memory_limit(MEMORY_EXTRA);

global $DB, $USER;

// ============ DB STATE OVERVIEW ============
$dbstate = $DB->get_records_sql(
    "SELECT status, COUNT(*) AS cnt,
            SUM(CASE WHEN score_experience IS NULL THEN 1 ELSE 0 END) AS no_ai,
            SUM(CASE WHEN score_experience IS NOT NULL THEN 1 ELSE 0 END) AS with_ai
     FROM {local_jobmatch_results}
     GROUP BY status"
);

$starttime = microtime(true);
$stats = \local_jobmatchagent\match_engine::process_ai_matching_for_pending($force);
$durationms = (int) round((microtime(true) - $starttime) * 1000);

// Audit log.
$DB->insert_record('local_jobmatch_logs', (object) [
    'run_time' => time(),
    'task_name' => 'manual_ai_matching' . ($force ? '_force' : ''),
    'source_id' => null,
    'offers_fetched' => 0,
    'results_created' => $stats['matches_updated'],
    'ai_calls' => $stats['ai_calls'] ?? 0,
    'ai_tokens' => 0,
    'errors_json' => !empty($stats['errors']) ? json_encode($stats['errors']) : null,
    'duration_ms' => $durationms,
]);

echo $OUTPUT->header();

if (!$stats['available']) {
    echo $OUTPUT->notification(
        'AI matching non disponibile: plugin local_ftm_jobsearch non installato o classe ai_scraper non trovata.',
        'notifyerror'
    );
    echo html_writer::link(
        new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
        '← Dashboard',
        ['class' => 'btn btn-outline-secondary']
    );
    echo $OUTPUT->footer();
    exit;
}

// === STATO DB ===
echo html_writer::tag('h4', '📊 Stato attuale DB match');
if (empty($dbstate)) {
    echo $OUTPUT->notification('Nessun match in DB. Devi prima eseguire "Aggiorna catalogo".', 'notifywarning');
} else {
    $st = new html_table();
    $st->head = ['Status', 'Totale', 'Senza score AI', 'Con score AI'];
    $st->attributes['class'] = 'table table-sm table-bordered';
    foreach ($dbstate as $row) {
        $st->data[] = [
            html_writer::span(s($row->status), 'badge bg-secondary'),
            $row->cnt,
            html_writer::span($row->no_ai, $row->no_ai > 0 ? 'badge bg-warning text-dark' : 'text-muted'),
            html_writer::span($row->with_ai, 'text-success'),
        ];
    }
    echo html_writer::table($st);
}

// === RISULTATO RUN ===
echo html_writer::tag('h4', '🧠 Risultato esecuzione AI', ['class' => 'mt-4']);

if ($stats['matches_updated'] > 0) {
    echo $OUTPUT->notification(
        'AI matching completato: ' . $stats['matches_updated'] . ' match valutati, '
        . $stats['auto_discarded'] . ' scartati per CV non compatibile.',
        'notifysuccess'
    );
} else {
    echo $OUTPUT->notification(
        'Nessun match processato. ' .
        ($force
            ? 'In modalita FORCE non ci sono match pending/ai_done.'
            : 'Nessun match con score_experience=NULL. Prova con FORCE per rivalutare anche quelli gia processati.'),
        'notifyinfo'
    );
}

$table = new html_table();
$table->attributes['class'] = 'table table-bordered';
$table->data = [
    ['<strong>Studenti processati</strong>', $stats['students_processed']],
    ['<strong>Chiamate AI</strong>', $stats['ai_calls']],
    ['<strong>Match valutati</strong>', $stats['matches_updated']],
    ['<strong>Auto-scartati per CV non compatibile (sotto veto AI)</strong>',
        html_writer::span($stats['auto_discarded'], 'badge bg-danger')],
    ['<strong>Errori</strong>', count($stats['errors'] ?? [])],
];
echo html_writer::table($table);

// === DEBUG ===
if (!empty($stats['debug'])) {
    echo html_writer::tag('h5', '🔍 Debug', ['class' => 'mt-3']);
    echo html_writer::start_tag('ul', ['class' => 'small text-muted']);
    foreach ($stats['debug'] as $line) {
        echo html_writer::tag('li', s($line));
    }
    echo html_writer::end_tag('ul');
}

// === ERRORI ===
if (!empty($stats['errors'])) {
    echo html_writer::tag('h5', '⚠ Errori', ['class' => 'mt-3 text-danger']);
    foreach ($stats['errors'] as $err) {
        echo html_writer::div(html_writer::tag('code', s($err)), 'alert alert-danger py-2');
    }
}

echo html_writer::div(
    html_writer::tag('small', 'Durata: ' . $durationms . ' ms · Mode: '
        . ($force ? 'FORCE (rivaluta tutto)' : 'normale (solo NULL)')
        . ' · Eseguito da ' . fullname($USER) . ' il ' . userdate(time()),
        ['class' => 'text-muted']),
    'mb-3'
);

// === ACTIONS ===
echo html_writer::start_div('d-flex gap-2 mt-3 flex-wrap');
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
    '← Dashboard',
    ['class' => 'btn btn-outline-secondary']
);
if (!$force) {
    $forceurl = new moodle_url('/local/jobmatchagent/run_ai_matching.php', ['sesskey' => sesskey(), 'force' => 1]);
    echo html_writer::link($forceurl,
        '🔁 Rivaluta TUTTO (force, anche match gia processati)',
        [
            'class' => 'btn btn-warning',
            'onclick' => 'return confirm("Rivalutare TUTTI i match con AI? Costera token OpenAI per ogni match.");',
        ]);
}
echo html_writer::end_div();

echo $OUTPUT->footer();
