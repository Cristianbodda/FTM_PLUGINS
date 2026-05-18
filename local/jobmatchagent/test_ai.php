<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Test AI connection with a hardcoded sample CV+offer.
 * Useful to diagnose why Phase 3 AI matching isn't producing results.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
// Nota: niente require_sesskey() — questa è una pagina di pura diagnosi
// (1 chiamata AI test da ~$0.001), pensata per essere apribile via URL diretto.

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/test_ai.php'));
$PAGE->set_title('Test AI connection');
$PAGE->set_heading('🔬 Test connessione AI');
$PAGE->set_pagelayout('admin');

global $DB, $USER;

echo $OUTPUT->header();

// === STEP 1: API key check ===
echo html_writer::tag('h4', '🔑 Step 1: API Key OpenAI');

$keyjm = get_config('local_jobmatchagent', 'openai_apikey');
$keyjs = get_config('local_ftm_jobsearch', 'openai_apikey');
$keyja = get_config('local_jobaida', 'openai_apikey');

$keystable = new html_table();
$keystable->head = ['Plugin', 'API Key configurata?', 'Lunghezza'];
$keystable->attributes['class'] = 'table table-sm table-bordered';
$keystable->data = [
    ['local_jobmatchagent',
        $keyjm ? html_writer::span('✅ SI', 'badge bg-success') : html_writer::span('❌ NO', 'badge bg-secondary'),
        $keyjm ? strlen($keyjm) . ' char' : '—'],
    ['local_ftm_jobsearch',
        $keyjs ? html_writer::span('✅ SI', 'badge bg-success') : html_writer::span('❌ NO', 'badge bg-secondary'),
        $keyjs ? strlen($keyjs) . ' char' : '—'],
    ['local_jobaida (fallback usato da ai_scraper)',
        $keyja ? html_writer::span('✅ SI', 'badge bg-success') : html_writer::span('❌ NO', 'badge bg-danger'),
        $keyja ? strlen($keyja) . ' char' : '—'],
];
echo html_writer::table($keystable);

if (empty($keyjs) && empty($keyja)) {
    echo $OUTPUT->notification(
        '❌ NESSUNA API key OpenAI trovata. ai_scraper::match_cv_to_offers() restituirà sempre array vuoto. '
        . 'Configura la key in: Amministrazione → Plugin → JobAIDA → openai_apikey',
        'notifyerror'
    );
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_jobaida']),
        '⚙ Configura API key in JobAIDA',
        ['class' => 'btn btn-primary']
    );
    echo $OUTPUT->footer();
    exit;
}

// === STEP 2: ftm_jobsearch class check ===
echo html_writer::tag('h4', '🔌 Step 2: Plugin ftm_jobsearch', ['class' => 'mt-4']);

$classexists = class_exists('\local_ftm_jobsearch\ai_scraper');
$tableexists = $DB->get_manager()->table_exists('local_ftm_jobsearch_offers');

$pstable = new html_table();
$pstable->attributes['class'] = 'table table-sm table-bordered';
$pstable->data = [
    ['Classe \local_ftm_jobsearch\ai_scraper',
        $classexists ? html_writer::span('✅ trovata', 'badge bg-success') : html_writer::span('❌ NON trovata', 'badge bg-danger')],
    ['Tabella local_ftm_jobsearch_offers',
        $tableexists ? html_writer::span('✅ esiste', 'badge bg-success') : html_writer::span('❌ NON esiste', 'badge bg-danger')],
];
echo html_writer::table($pstable);

if (!$classexists) {
    echo $OUTPUT->notification('Plugin local_ftm_jobsearch non installato o non riconosciuto.', 'notifyerror');
    echo $OUTPUT->footer();
    exit;
}

// === STEP 3: Live AI test call ===
echo html_writer::tag('h4', '🧪 Step 3: Test chiamata AI con dati sample', ['class' => 'mt-4']);

$cvtest = "MARIO ROSSI\nVia Roma 12, Lugano\n\n"
    . "ESPERIENZE:\n"
    . "2022-2025 Aiuto cuoco presso Ristorante Pizzeria Sole, Lugano\n"
    . "  - Preparazione pasta fresca\n"
    . "  - Pulizia cucina\n"
    . "  - Servizio clienti\n\n"
    . "FORMAZIONE: Diploma alberghiero, scuola SSAT Bellinzona";

$offertest1 = new \stdClass();
$offertest1->id = 9001;
$offertest1->titolo = 'Aiuto cuoco';
$offertest1->azienda = 'Hotel Splendide Lugano';
$offertest1->citta = 'Lugano';
$offertest1->tipo_lavoro = 'fulltime';

$offertest2 = new \stdClass();
$offertest2->id = 9002;
$offertest2->titolo = 'Operaio meccanico CNC';
$offertest2->azienda = 'OfficinaTech SA';
$offertest2->citta = 'Bellinzona';
$offertest2->tipo_lavoro = 'fulltime';

echo html_writer::tag('p', 'Invio una chiamata di test a OpenAI con: 1 CV cuoco + 2 offerte (1 cuoco + 1 meccanico). Atteso: cuoco ~85%, meccanico ~5%.');

echo html_writer::tag('h6', 'Input CV:');
echo html_writer::tag('pre', s($cvtest), ['class' => 'small bg-light p-2']);

echo html_writer::tag('h6', 'Input Offerte:');
echo html_writer::tag('pre',
    "ID 9001 — Aiuto cuoco — Hotel Splendide Lugano (Lugano, fulltime)\n"
    . "ID 9002 — Operaio meccanico CNC — OfficinaTech SA (Bellinzona, fulltime)",
    ['class' => 'small bg-light p-2']);

$starttime = microtime(true);
try {
    $result = \local_ftm_jobsearch\ai_scraper::match_cv_to_offers($cvtest, [$offertest1, $offertest2]);
    $duration = (int) round((microtime(true) - $starttime) * 1000);

    if (empty($result)) {
        echo $OUTPUT->notification(
            '⚠ AI ha risposto array VUOTO. Possibili cause: API key invalid, rate limit, errore parsing JSON, oppure ai_scraper ha eseguito catch silente. '
            . 'Controlla i log Moodle (Sviluppo → Visualizza log) e debug developer.',
            'notifyerror'
        );
    } else {
        echo $OUTPUT->notification(
            '✅ AI ha risposto in ' . $duration . ' ms con ' . count($result) . ' valutazioni.',
            'notifysuccess'
        );

        $rt = new html_table();
        $rt->head = ['Offer ID', 'Titolo', 'Score AI', 'Reason'];
        $rt->attributes['class'] = 'table table-bordered';
        foreach ([9001 => 'Aiuto cuoco', 9002 => 'Operaio meccanico CNC'] as $id => $title) {
            if (isset($result[$id])) {
                $r = $result[$id];
                $pct = (int) ($r['pct'] ?? 0);
                $cls = $pct >= 70 ? 'bg-success' : ($pct >= 40 ? 'bg-warning text-dark' : 'bg-danger');
                $rt->data[] = [
                    $id,
                    s($title),
                    html_writer::span($pct . '%', 'badge ' . $cls . ' fs-6'),
                    s($r['reason'] ?? ''),
                ];
            } else {
                $rt->data[] = [$id, s($title),
                    html_writer::span('mancante', 'badge bg-secondary'),
                    'AI non ha valutato questa offerta'];
            }
        }
        echo html_writer::table($rt);

        // Sanity check.
        $cuoco = (int) ($result[9001]['pct'] ?? 0);
        $mecc = (int) ($result[9002]['pct'] ?? 0);
        if ($cuoco > 60 && $mecc < 30) {
            echo $OUTPUT->notification(
                '✅✅ AI funziona PERFETTAMENTE! Cuoco ' . $cuoco . '% > Meccanico ' . $mecc . '%. '
                . 'Il problema è altrove (probabilmente fetch_now.php non chiama Phase 3).',
                'notifysuccess'
            );
        } else if ($cuoco > $mecc) {
            echo $OUTPUT->notification(
                '⚠ AI funziona ma score sub-ottimali. Cuoco ' . $cuoco . '%, Meccanico ' . $mecc . '%. Procedere con caution.',
                'notifywarning'
            );
        } else {
            echo $OUTPUT->notification(
                '❌ AI funziona ma score INVERSI o errati. Cuoco ' . $cuoco . '%, Meccanico ' . $mecc . '%. Modello AI confuso.',
                'notifyerror'
            );
        }
    }

} catch (\Throwable $e) {
    $duration = (int) round((microtime(true) - $starttime) * 1000);
    echo $OUTPUT->notification(
        '❌ ECCEZIONE durante chiamata AI (' . $duration . ' ms): ' . s($e->getMessage()),
        'notifyerror'
    );
    echo html_writer::tag('pre', s($e->getTraceAsString()), ['class' => 'small bg-light p-2']);
}

// === Actions ===
echo html_writer::start_div('d-flex gap-2 mt-4');
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
    '← Dashboard',
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    new moodle_url('/local/jobmatchagent/run_ai_matching.php', ['sesskey' => sesskey(), 'force' => 1]),
    '🧠 Esegui AI matching su tutti i pending (force)',
    ['class' => 'btn btn-success']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
