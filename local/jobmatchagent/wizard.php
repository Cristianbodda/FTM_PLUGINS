<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Wizard guidato per la ricerca opportunità di lavoro.
 * 3 step: Scegli studente → Imposta CV/preferenze → Vedi risultati.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/match_engine.php');
if (file_exists(__DIR__ . '/classes/source_manager.php')) {
    require_once(__DIR__ . '/classes/source_manager.php');
}

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$step = optional_param('step', 1, PARAM_INT);
$studentid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

global $USER, $DB, $OUTPUT, $PAGE;

// Verify access if a student is selected.
if ($studentid > 0 && !\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $studentid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$student = null;
$studentname = '';
if ($studentid > 0) {
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
    $studentname = fullname($student);
}

// ============================================================================
// PROCESS ACTIONS (before rendering)
// ============================================================================

// Save filters from step 2 form.
if ($action === 'savefilters' && $studentid > 0 && confirm_sesskey()) {
    save_student_filters($studentid, $USER->id);
    redirect(new moodle_url('/local/jobmatchagent/wizard.php', [
        'step' => 3, 'userid' => $studentid, 'fresh' => 1,
    ]));
}

// Coach decision (publish/discard) on a single result.
if ($action === 'decide' && confirm_sesskey()) {
    $resultid = required_param('resultid', PARAM_INT);
    $decision = required_param('decision', PARAM_ALPHA);
    process_decision($resultid, $decision, $USER->id);
    redirect(new moodle_url('/local/jobmatchagent/wizard.php', [
        'step' => 3, 'userid' => $studentid,
    ]), 'Decisione salvata.', 1);
}

// ============================================================================
// PAGE SETUP
// ============================================================================

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/wizard.php', [
    'step' => $step, 'userid' => $studentid,
]));
$PAGE->set_pagelayout('standard');

$titles = [
    1 => 'Cerca opportunita di lavoro — Step 1: Scegli lo studente',
    2 => 'Cerca opportunita di lavoro — Step 2: Imposta CV e preferenze',
    3 => 'Cerca opportunita di lavoro — Step 3: Risultati',
];
$PAGE->set_title($titles[$step] ?? 'Wizard ricerca lavoro');
$PAGE->set_heading($titles[$step] ?? 'Wizard ricerca lavoro');

echo $OUTPUT->header();

// Custom CSS per garantire visibilita scritte sui bottoni e badge.
echo '<style>
/* BOTTONI con sfondo colorato → testo bianco bold */
.local-jobmatch .btn-primary,
.local-jobmatch .btn-success,
.local-jobmatch .btn-danger,
.local-jobmatch .btn-info,
.local-jobmatch .btn-warning {
    color: #fff !important;
    font-weight: 600 !important;
    text-shadow: none !important;
}
.local-jobmatch .btn-warning {
    color: #212529 !important; /* yellow bg = dark text */
}
.local-jobmatch .btn-secondary {
    color: #fff !important;
    font-weight: 600 !important;
}
/* OUTLINE buttons: testo scuro chiaro */
.local-jobmatch .btn-outline-primary { color: #0066cc !important; border-color: #0066cc !important; font-weight: 600 !important; background: #fff !important; }
.local-jobmatch .btn-outline-primary:hover { background: #0066cc !important; color: #fff !important; }
.local-jobmatch .btn-outline-secondary { color: #495057 !important; border-color: #6c757d !important; font-weight: 600 !important; background: #fff !important; }
.local-jobmatch .btn-outline-secondary:hover { background: #6c757d !important; color: #fff !important; }
.local-jobmatch .btn-outline-success { color: #28a745 !important; border-color: #28a745 !important; font-weight: 600 !important; background: #fff !important; }
.local-jobmatch .btn-outline-success:hover { background: #28a745 !important; color: #fff !important; }
.local-jobmatch .btn-outline-info { color: #17a2b8 !important; border-color: #17a2b8 !important; font-weight: 600 !important; background: #fff !important; }
.local-jobmatch .btn-outline-info:hover { background: #17a2b8 !important; color: #fff !important; }
.local-jobmatch .btn-outline-danger { color: #dc3545 !important; border-color: #dc3545 !important; font-weight: 600 !important; background: #fff !important; }
.local-jobmatch .btn-outline-danger:hover { background: #dc3545 !important; color: #fff !important; }

/* BADGE colorati */
.local-jobmatch .badge.bg-primary,
.local-jobmatch .badge.bg-success,
.local-jobmatch .badge.bg-danger,
.local-jobmatch .badge.bg-info,
.local-jobmatch .badge.bg-secondary {
    color: #fff !important;
    font-weight: 600 !important;
}
.local-jobmatch .badge.bg-warning,
.local-jobmatch .badge.bg-light {
    color: #212529 !important;
    font-weight: 600 !important;
}

/* Percentuale compatibilita: piu grande e leggibile */
.local-jobmatch .compat-badge {
    font-size: 1.1em !important;
    padding: 8px 14px !important;
    font-weight: 700 !important;
    letter-spacing: 0.3px;
}

/* Cards opportunita: titolo piu visibile */
.local-jobmatch .card-body h5 {
    font-size: 1.2em;
    color: #212529;
}
.local-jobmatch .card-body details summary {
    cursor: pointer;
    user-select: none;
}
.local-jobmatch .card-body details summary:hover {
    color: #0066cc;
}
</style>';

echo '<div class="local-jobmatch">';

render_progress_indicator($step, $studentname);

// ============================================================================
// STEP DISPATCH
// ============================================================================

switch ($step) {
    case 2:
        if ($studentid === 0) {
            redirect(new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 1]));
        }
        render_step2($studentid, $student, $studentname);
        break;
    case 3:
        if ($studentid === 0) {
            redirect(new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 1]));
        }
        render_step3($studentid, $student, $studentname);
        break;
    case 1:
    default:
        render_step1($USER->id);
        break;
}

echo '</div>'; // close .local-jobmatch wrapper

echo $OUTPUT->footer();

// ============================================================================
// RENDER FUNCTIONS
// ============================================================================

function render_progress_indicator($step, $studentname = '') {
    $steps = [
        1 => 'Scegli studente',
        2 => 'Imposta CV e preferenze',
        3 => 'Risultati',
    ];

    echo html_writer::start_div('mb-4 p-3', ['style' => 'background: #f8f9fa; border-radius: 8px;']);
    echo html_writer::start_div('d-flex justify-content-between align-items-center');

    foreach ($steps as $n => $label) {
        $isactive = $n === $step;
        $isdone = $n < $step;
        $color = $isactive ? '#0066cc' : ($isdone ? '#28a745' : '#dee2e6');
        $textcolor = $isactive || $isdone ? '#fff' : '#6c757d';

        echo html_writer::start_div('text-center flex-fill');
        echo html_writer::div(
            $isdone ? '✓' : (string) $n,
            'd-inline-block rounded-circle',
            ['style' => 'width: 40px; height: 40px; line-height: 40px; background: ' . $color
                . '; color: ' . $textcolor . '; font-weight: bold; font-size: 1.2em;']
        );
        echo html_writer::div(
            $label,
            'mt-1 small ' . ($isactive ? 'fw-bold text-primary' : 'text-muted')
        );
        echo html_writer::end_div();

        if ($n < count($steps)) {
            echo html_writer::div('', 'flex-fill',
                ['style' => 'border-top: 2px solid ' . ($isdone ? '#28a745' : '#dee2e6')
                    . '; margin-top: 20px; max-width: 80px;']);
        }
    }

    echo html_writer::end_div();

    if ($studentname && $step > 1) {
        echo html_writer::div(
            '👤 Studente: <strong>' . s($studentname) . '</strong>',
            'mt-2 text-center text-muted'
        );
    }
    echo html_writer::end_div();
}

// ============================================================================
// STEP 1: Choose student
// ============================================================================

function render_step1($coachid) {
    global $DB;

    $students = \local_jobmatchagent\match_engine::get_coach_students($coachid);

    echo html_writer::tag('h3', '👋 Per quale studente vuoi cercare opportunita di lavoro?',
        ['class' => 'mb-3']);

    if (empty($students)) {
        echo html_writer::div(
            '⚠ Non hai studenti assegnati. Contatta la segreteria.',
            'alert alert-warning'
        );
        return;
    }

    // Bulk fetch filter status.
    $userids = array_keys($students);
    list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
    $filters = $DB->get_records_select('local_jobmatch_student_filters', "userid $insql",
        $inparams, '', 'userid, active, manual_cv_text, desired_activities');
    $filtersmap = [];
    foreach ($filters as $f) {
        $filtersmap[$f->userid] = $f;
    }

    // Bulk fetch result counts.
    $counts = $DB->get_records_sql(
        "SELECT userid,
                SUM(CASE WHEN status IN ('pending','ai_done') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published
         FROM {local_jobmatch_results}
         WHERE userid $insql
         GROUP BY userid",
        $inparams
    );

    echo html_writer::start_div('row g-3');
    foreach ($students as $s) {
        $name = fullname($s);
        $f = $filtersmap[$s->id] ?? null;
        $c = $counts[$s->id] ?? null;

        $hascv = $f && !empty($f->manual_cv_text);
        $hasactivities = false;
        if ($f && !empty($f->desired_activities)) {
            $arr = json_decode($f->desired_activities, true);
            $hasactivities = is_array($arr) && !empty($arr);
        }

        $pending = $c ? (int) $c->pending : 0;
        $published = $c ? (int) $c->published : 0;

        $statusbadges = '';
        if ($f && $f->active) {
            $statusbadges .= html_writer::span('✓ Filtri OK', 'badge bg-success me-1');
        } else {
            $statusbadges .= html_writer::span('⚠ Da configurare', 'badge bg-warning text-dark me-1');
        }
        if ($pending > 0) {
            $statusbadges .= html_writer::span($pending . ' da rivedere', 'badge bg-info me-1');
        }
        if ($published > 0) {
            $statusbadges .= html_writer::span($published . ' pubblicati', 'badge bg-secondary me-1');
        }

        $url = new moodle_url('/local/jobmatchagent/wizard.php', [
            'step' => 2, 'userid' => $s->id,
        ]);

        echo html_writer::start_div('col-md-6 col-lg-4');
        echo html_writer::start_tag('a', [
            'href' => $url,
            'class' => 'card h-100 text-decoration-none text-dark shadow-sm',
            'style' => 'border: 2px solid #dee2e6; transition: all 0.2s;',
            'onmouseover' => "this.style.borderColor='#0066cc'; this.style.transform='translateY(-2px)';",
            'onmouseout' => "this.style.borderColor='#dee2e6'; this.style.transform='translateY(0)';",
        ]);
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h5', s($name), ['class' => 'card-title mb-2']);
        echo html_writer::div($statusbadges, 'mb-2');
        echo html_writer::tag('p',
            '👉 Clicca per cercare opportunita',
            ['class' => 'card-text text-primary mb-0']);
        echo html_writer::end_div();
        echo html_writer::end_tag('a');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

// ============================================================================
// STEP 2: Configure filters + CV
// ============================================================================

function render_step2($studentid, $student, $studentname) {
    global $DB;

    $f = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid]);
    if (!$f) {
        $f = (object) [
            'active' => 1,
            'home_address' => '',
            'max_distance_km' => 30,
            'desired_activities' => null,
            'manual_cv_text' => '',
        ];
    }

    $jobaidacv = \local_jobmatchagent\matcher::get_latest_cv($studentid);

    $activitieslist = '';
    if (!empty($f->desired_activities)) {
        $arr = json_decode($f->desired_activities, true);
        if (is_array($arr)) {
            $activitieslist = implode("\n", $arr);
        }
    }

    echo html_writer::tag('h3', '⚙ Configura ricerca per ' . s($studentname),
        ['class' => 'mb-3']);

    echo html_writer::div(
        'Compila i campi qui sotto. Tutti sono importanti per trovare le opportunita giuste.',
        'alert alert-info'
    );

    $formurl = new moodle_url('/local/jobmatchagent/wizard.php');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savefilters']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $studentid]);

    // Section 1: CV
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo html_writer::tag('h5', '📋 1. Curriculum dello studente', ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    if (!empty($f->manual_cv_text)) {
        echo html_writer::div('✓ Stai usando un CV personalizzato (incollato qui sotto)',
            'alert alert-success py-2');
    } else if (!empty($jobaidacv)) {
        echo html_writer::div('ℹ Stai usando l\'ultimo CV salvato in JobAIDA. Puoi sovrascriverlo qui sotto.',
            'alert alert-info py-2');
    } else {
        echo html_writer::div('⚠ Nessun CV trovato. Incolla obbligatoriamente il CV qui sotto.',
            'alert alert-warning py-2');
    }

    echo html_writer::tag('label', 'CV completo (incolla testo):', ['for' => 'manual_cv_text', 'class' => 'form-label fw-bold']);
    echo html_writer::tag('textarea', s($f->manual_cv_text ?? ''), [
        'name' => 'manual_cv_text',
        'id' => 'manual_cv_text',
        'class' => 'form-control',
        'rows' => 12,
        'placeholder' => "Incolla qui il CV. Esempio:\n\nMario Rossi\n2022-2025 Aiuto cuoco - Ristorante Sole, Lugano\n  - Preparazione pasta fresca\n...",
        'style' => 'font-family: monospace; font-size: 0.9em;',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Section 2: Activities + Address
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo html_writer::tag('h5', '🔍 2. Cosa cercare e dove', ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    echo html_writer::tag('label', 'Attivita / mansioni cercate (una per riga):',
        ['for' => 'desired_activities', 'class' => 'form-label fw-bold']);
    echo html_writer::tag('textarea', s($activitieslist), [
        'name' => 'desired_activities',
        'id' => 'desired_activities',
        'class' => 'form-control mb-3',
        'rows' => 4,
        'placeholder' => "aiuto cuoco\ncameriere\nristorazione",
    ]);
    echo html_writer::div('Es: "aiuto cuoco", "magazziniere", "elettricista"',
        'form-text text-muted mb-3');

    echo html_writer::start_div('row');
    echo html_writer::start_div('col-md-8');
    echo html_writer::tag('label', 'Indirizzo di casa:',
        ['for' => 'home_address', 'class' => 'form-label fw-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'home_address',
        'id' => 'home_address',
        'class' => 'form-control',
        'value' => s($f->home_address ?? ''),
        'placeholder' => 'Es: Via Roma 12, Lugano',
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-4');
    echo html_writer::tag('label', 'Distanza max (km):',
        ['for' => 'max_distance_km', 'class' => 'form-label fw-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'max_distance_km',
        'id' => 'max_distance_km',
        'class' => 'form-control',
        'value' => (int) ($f->max_distance_km ?? 30),
        'min' => 5,
        'max' => 200,
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();

    // Submit buttons
    echo html_writer::start_div('d-flex justify-content-between mt-4');
    echo html_writer::link(
        new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 1]),
        '← Indietro',
        ['class' => 'btn btn-outline-secondary btn-lg']
    );
    echo html_writer::tag('button',
        '💾 Salva e cerca opportunita →',
        ['type' => 'submit', 'class' => 'btn btn-primary btn-lg']);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
}

// ============================================================================
// STEP 3: Run search and show results
// ============================================================================

function render_step3($studentid, $student, $studentname) {
    global $DB, $OUTPUT;

    $isfresh = optional_param('fresh', 0, PARAM_INT) ? true : false;

    if ($isfresh) {
        run_search_with_progress($studentid);
    }

    // User-selectable filter (default 0 = mostra tutti).
    $minscore = optional_param('minscore', 0, PARAM_INT);

    $allmatches = $DB->get_records_sql(
        "SELECT r.*, o.title AS offer_title, o.company AS offer_company,
                o.location AS offer_location, o.url AS offer_url, o.parsed_text AS offer_text,
                o.work_schedule AS offer_schedule
         FROM {local_jobmatch_results} r
         INNER JOIN {local_jobmatch_offers} o ON o.id = r.offer_id
         WHERE r.userid = :uid AND r.status IN ('pending', 'ai_done')
         ORDER BY COALESCE(r.score_experience, 0) DESC, r.score_global DESC, r.timecreated DESC",
        ['uid' => $studentid]
    );

    // Apply user filter.
    $pending = [];
    $hidden = 0;
    foreach ($allmatches as $m) {
        $aipct = $m->score_experience !== null ? (int) $m->score_experience : null;
        if ($minscore === 0 || ($aipct !== null && $aipct >= $minscore)) {
            $pending[] = $m;
        } else {
            $hidden++;
        }
    }
    $totalmatches = count($allmatches);

    $published = $DB->count_records('local_jobmatch_results',
        ['userid' => $studentid, 'status' => 'published']);
    $discarded = $DB->count_records('local_jobmatch_results',
        ['userid' => $studentid, 'status' => 'discarded']);

    echo html_writer::tag('h3', '🎯 Risultati per ' . s($studentname), ['class' => 'mb-3']);

    // Summary box.
    echo html_writer::start_div('row g-3 mb-3');
    echo html_writer::div(
        html_writer::tag('h2', $totalmatches, ['class' => 'mb-0 text-primary']) .
        html_writer::div('Trovate (totale)', 'small text-muted'),
        'col-md-3 text-center p-3 border rounded shadow-sm bg-light'
    );
    echo html_writer::div(
        html_writer::tag('h2', $published, ['class' => 'mb-0 text-success']) .
        html_writer::div('Gia pubblicati', 'small text-muted'),
        'col-md-3 text-center p-3 border rounded shadow-sm bg-light'
    );
    echo html_writer::div(
        html_writer::tag('h2', $discarded, ['class' => 'mb-0 text-danger']) .
        html_writer::div('Scartati AI (CV incompatibile)', 'small text-muted'),
        'col-md-3 text-center p-3 border rounded shadow-sm bg-light'
    );
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/jobmatchagent/wizard.php', [
                'step' => 3, 'userid' => $studentid, 'fresh' => 1,
            ]),
            '🔄 Cerca di nuovo',
            ['class' => 'btn btn-outline-primary',
                'onclick' => 'return confirm("Rifare la ricerca? Saranno valutate di nuovo le offerte non ancora decise.");']
        ),
        'col-md-3 text-center p-3'
    );
    echo html_writer::end_div();

    // Filter dropdown.
    echo html_writer::start_div('d-flex align-items-center gap-2 mb-3 p-2 bg-light border rounded');
    echo html_writer::tag('strong', '🎚 Mostra solo opportunita con compatibilita AI:');
    $options = [
        0 => 'Tutte',
        20 => '≥ 20% (potenzialmente)',
        40 => '≥ 40% (parziale)',
        60 => '≥ 60% (buona)',
        80 => '≥ 80% (ottima)',
    ];
    foreach ($options as $val => $lbl) {
        $url = new moodle_url('/local/jobmatchagent/wizard.php', [
            'step' => 3, 'userid' => $studentid, 'minscore' => $val,
        ]);
        $cls = $minscore === $val ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm';
        echo html_writer::link($url, $lbl, ['class' => $cls]);
    }
    if ($hidden > 0) {
        echo html_writer::span(
            "({$hidden} nascoste con questo filtro)",
            'text-muted small ms-2'
        );
    }
    echo html_writer::end_div();

    if (empty($pending)) {
        if ($totalmatches > 0 && $minscore > 0) {
            echo $OUTPUT->notification(
                'Ci sono ' . $totalmatches . ' opportunita ma nessuna supera il filtro AI ≥ ' . $minscore . '%. '
                . 'Abbassa il filtro qui sopra per vederle.',
                'notifyinfo'
            );
        } else if ($discarded > 0) {
            echo $OUTPUT->notification(
                'L\'AI ha trovato ' . ($totalmatches + $discarded) . ' offerte ma le ha valutate '
                . 'tutte poco compatibili col CV (' . $discarded . ' scartate, '
                . $totalmatches . ' trovate ma sotto soglia). Prova a modificare il CV o le mansioni cercate.',
                'notifyinfo'
            );
        } else {
            echo $OUTPUT->notification(
                'Nessuna opportunita trovata. Forse il catalogo annunci e vuoto, '
                . 'oppure le mansioni cercate non hanno corrispondenze. Modifica i filtri.',
                'notifyinfo'
            );
        }
        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/jobmatchagent/wizard.php', [
                    'step' => 2, 'userid' => $studentid,
                ]),
                '← Modifica filtri',
                ['class' => 'btn btn-outline-secondary']
            ),
            'mb-3'
        );

        echo html_writer::start_div('d-flex justify-content-between mt-4 pt-3 border-top');
        echo html_writer::link(
            new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 1]),
            '← Cerca per un altro studente',
            ['class' => 'btn btn-outline-secondary']
        );
        echo html_writer::end_div();
        return;
    }

    echo html_writer::tag('h4', '✨ ' . count($pending) . ' opportunita ' .
        ($minscore > 0 ? '(filtro AI ≥ ' . $minscore . '%)' : '(ordinate per compatibilita)'),
        ['class' => 'mb-3']);

    // Render all matches sorted by AI score desc.
    foreach ($pending as $r) {
        render_opportunity_card($r, $studentid);
    }

    // Footer.
    echo html_writer::start_div('d-flex justify-content-between mt-4 pt-3 border-top');
    echo html_writer::link(
        new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 1]),
        '← Cerca per un altro studente',
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::link(
        new moodle_url('/local/jobmatchagent/wizard.php', [
            'step' => 2, 'userid' => $studentid,
        ]),
        '⚙ Modifica filtri',
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::end_div();
}

function render_opportunity_card($r, $studentid) {
    $aipct = $r->score_experience !== null ? (int) $r->score_experience : null;
    $hasai = $aipct !== null;

    if ($hasai) {
        if ($aipct >= 70) {
            $cls = 'success';
            $bordercolor = '#28a745';
        } else if ($aipct >= 40) {
            $cls = 'warning';
            $bordercolor = '#EAB308';
        } else {
            $cls = 'danger';
            $bordercolor = '#dc3545';
        }
        $scorelabel = $aipct . '% MATCH';
    } else {
        $cls = 'secondary';
        $bordercolor = '#6c757d';
        $scorelabel = '⏳ AI in corso';
    }

    echo html_writer::start_div('card mb-3 shadow-sm',
        ['style' => 'border-left: 6px solid ' . $bordercolor . ';']);
    echo html_writer::start_div('card-body');

    // Title row.
    echo html_writer::start_div('d-flex justify-content-between align-items-start mb-2 gap-2');
    echo html_writer::tag('h5',
        s($r->offer_title) .
        ($r->offer_company ? ' — ' . html_writer::tag('small', s($r->offer_company), ['class' => 'text-muted']) : ''),
        ['class' => 'mb-0 flex-grow-1']);
    echo html_writer::span($scorelabel, 'badge bg-' . $cls . ' compat-badge');
    echo html_writer::end_div();

    // Meta line.
    $meta = [];
    if (!empty($r->offer_location)) {
        $meta[] = '📍 ' . s($r->offer_location);
    }
    if (!empty($r->offer_schedule) && $r->offer_schedule !== 'unknown') {
        $meta[] = '⏱ ' . s($r->offer_schedule);
    }
    if (!empty($r->offer_url)) {
        $meta[] = html_writer::link($r->offer_url, '🔗 vedi annuncio',
            ['target' => '_blank', 'rel' => 'noopener']);
    }
    if (!empty($meta)) {
        echo html_writer::div(implode(' &nbsp;|&nbsp; ', $meta), 'text-muted small mb-2');
    }

    // Warning visibile per match a bassa compatibilita (AI < 50%).
    if ($hasai && $aipct < 50) {
        echo html_writer::div(
            html_writer::tag('strong', '⚠ Bassa compatibilita ('. $aipct .'%) — verifica con attenzione i requisiti dell\'annuncio prima di pubblicare allo studente.'),
            'alert alert-warning py-2 mb-2 small'
        );
    }

    // AI explanation (the most important part!)
    if (!empty($r->ai_explanation_text)) {
        echo html_writer::div(
            html_writer::tag('strong', '🧠 Valutazione AI: ') . s($r->ai_explanation_text),
            'p-2 bg-light border-start border-' . $cls . ' border-3 mb-2 rounded'
        );
    } else {
        echo html_writer::div(
            html_writer::tag('em', '⏳ L\'AI sta ancora valutando questa offerta...', ['class' => 'text-muted']),
            'p-2 bg-light mb-2 rounded'
        );
    }

    // Offer text preview.
    $preview = shorten_text(strip_tags($r->offer_text), 300, true);
    echo html_writer::tag('details',
        html_writer::tag('summary', '📄 Descrizione offerta', ['class' => 'small text-muted']) .
        html_writer::div(nl2br(s($preview)), 'small p-2 bg-light mt-1 rounded'),
        ['class' => 'mb-2']
    );

    // Decision buttons.
    echo html_writer::start_div('d-flex gap-2 mt-3');

    $publishform = build_decision_form($r->id, $studentid, 'published');
    echo $publishform . html_writer::tag('button',
        '✅ Pubblica allo studente',
        ['type' => 'submit', 'class' => 'btn btn-success']) . '</form>';

    $discardform = build_decision_form($r->id, $studentid, 'discarded');
    echo $discardform . html_writer::tag('button',
        '⏭ Scarta',
        ['type' => 'submit', 'class' => 'btn btn-outline-secondary']) . '</form>';

    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

function build_decision_form($resultid, $studentid, $decision) {
    $action = (new moodle_url('/local/jobmatchagent/wizard.php'))->out(false);
    $html = '<form method="post" action="' . s($action) . '" class="d-inline">';
    $html .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    $html .= '<input type="hidden" name="action" value="decide">';
    $html .= '<input type="hidden" name="step" value="3">';
    $html .= '<input type="hidden" name="userid" value="' . $studentid . '">';
    $html .= '<input type="hidden" name="resultid" value="' . $resultid . '">';
    $html .= '<input type="hidden" name="decision" value="' . $decision . '">';
    return $html;
}

// ============================================================================
// SEARCH WITH LIVE PROGRESS
// ============================================================================

function run_search_with_progress($studentid) {
    global $DB;

    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level()) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    core_php_time_limit::raise(300);
    raise_memory_limit(MEMORY_EXTRA);

    echo html_writer::start_div('alert alert-info', ['id' => 'search-progress']);
    echo html_writer::tag('h5', '🔎 Sto cercando opportunita...', ['class' => 'mb-3']);
    echo html_writer::start_tag('ol', ['class' => 'mb-0']);
    flush_output();

    // Step A: clear stale results (everything except those manually decided by a coach).
    // Auto-discarded (coach_decision_userid = 0) gets cleared too, so they can be re-evaluated.
    echo html_writer::tag('li', 'Pulizia risultati precedenti non decisi...');
    flush_output();
    $cleared = $DB->delete_records_select('local_jobmatch_results',
        "userid = :uid AND (status IN ('pending', 'ai_done') OR (status = 'discarded' AND (coach_decision_userid = 0 OR coach_decision_userid IS NULL)))",
        ['uid' => $studentid]
    );
    echo html_writer::tag('li', '→ Cancellati ' . $cleared . ' match auto-generati precedenti.', ['class' => 'text-muted small']);
    flush_output();

    // Step B: deterministic match.
    echo html_writer::tag('li', 'Confronto offerte del catalogo con i filtri studente...');
    flush_output();
    $detres = \local_jobmatchagent\match_engine::match_all_active_offers_to_student_detailed($studentid);
    echo html_writer::tag('li', '→ Trovate ' . $detres['new_matches'] . ' opportunita potenziali ('
        . $detres['offers_in_catalog'] . ' annunci nel catalogo, '
        . $detres['below_threshold'] . ' scartate per criteri base).',
        ['class' => 'text-success']);
    flush_output();

    // Step C: AI matching (ora usa ai_matcher interno, non dipende da ftm_jobsearch).
    echo html_writer::tag('li', 'Sto chiedendo all\'AI di valutare ogni offerta vs il CV (questo richiede ~10 secondi)...');
    flush_output();
    try {
        $aires = \local_jobmatchagent\match_engine::process_ai_matching_for_pending(true);
        if ($aires['ai_calls'] > 0) {
            echo html_writer::tag('li', '→ AI ha valutato ' . $aires['matches_updated'] . ' opportunita ('
                . $aires['auto_discarded'] . ' scartate per CV non compatibile).',
                ['class' => 'text-success']);
        } else if (!empty($aires['errors'])) {
            echo html_writer::tag('li', '⚠ Errori AI: ' . s(implode('; ', $aires['errors'])),
                ['class' => 'text-danger']);
        } else {
            echo html_writer::tag('li', '→ Nessuna nuova valutazione AI necessaria.',
                ['class' => 'text-muted']);
        }
    } catch (\Throwable $e) {
        echo html_writer::tag('li', '⚠ Errore AI matching: ' . s($e->getMessage()),
            ['class' => 'text-danger']);
    }
    flush_output();

    echo html_writer::tag('li', '✅ Ricerca completata! Vedi risultati qui sotto.',
        ['class' => 'fw-bold text-success']);
    echo html_writer::end_tag('ol');
    echo html_writer::end_div();
    flush_output();
}

function flush_output() {
    if (ob_get_level()) {
        @ob_flush();
    }
    @flush();
}

// ============================================================================
// FILTER SAVE + DECISION
// ============================================================================

function save_student_filters($studentid, $coachid) {
    global $DB;

    $cv = optional_param('manual_cv_text', '', PARAM_RAW);
    $address = optional_param('home_address', '', PARAM_TEXT);
    $maxkm = optional_param('max_distance_km', 30, PARAM_INT);
    $activitiesraw = optional_param('desired_activities', '', PARAM_TEXT);

    $lines = preg_split('/\r?\n/', trim($activitiesraw));
    $activities = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $activities[] = $line;
        }
    }

    $existing = $DB->get_record('local_jobmatch_student_filters', ['userid' => $studentid]);

    $rec = (object) [
        'userid' => $studentid,
        'active' => 1,
        'home_address' => $address ?: null,
        'home_lat' => $existing->home_lat ?? null,
        'home_lng' => $existing->home_lng ?? null,
        'max_distance_km' => $maxkm > 0 ? $maxkm : 30,
        'company_sizes' => $existing->company_sizes ?? null,
        'work_schedules' => $existing->work_schedules ?? null,
        'desired_activities' => empty($activities) ? null : json_encode($activities),
        'extra_notes' => $existing->extra_notes ?? null,
        'manual_cv_text' => trim($cv) !== '' ? $cv : null,
        'updatedby' => $coachid,
        'timemodified' => time(),
    ];

    if ($existing) {
        $rec->id = $existing->id;
        $DB->update_record('local_jobmatch_student_filters', $rec);
    } else {
        $rec->timecreated = time();
        $DB->insert_record('local_jobmatch_student_filters', $rec);
    }
}

function process_decision($resultid, $decision, $coachid) {
    global $DB;

    if (!in_array($decision, ['published', 'discarded'], true)) {
        return;
    }

    $result = $DB->get_record('local_jobmatch_results', ['id' => $resultid], '*', MUST_EXIST);
    if (!\local_jobmatchagent\match_engine::coach_can_manage_student($coachid, $result->userid)) {
        throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
    }

    $update = (object) [
        'id' => $resultid,
        'status' => $decision,
        'coach_decision_userid' => $coachid,
        'coach_decision_time' => time(),
    ];
    if ($decision === 'published') {
        $update->published_to_student_at = time();
    }
    $DB->update_record('local_jobmatch_results', $update);
}
