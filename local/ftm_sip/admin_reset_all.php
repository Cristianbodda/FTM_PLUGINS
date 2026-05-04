<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * ADMIN ONLY: cancella tutti i dati CI (iscrizioni, piani, incontri, KPI, ecc.)
 * per permettere ai coach di esercitarsi su DB pulito.
 *
 * NON cancella: registro aziende (local_ftm_sip_companies) — condiviso e utile.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

// SOLO siteadmin (utente principale Moodle).
if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '',
        'Solo gli amministratori del sito possono eseguire questa operazione.');
}

$confirm = optional_param('confirm', '', PARAM_RAW);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/admin_reset_all.php'));
$PAGE->set_title('Reset CI — Solo amministratore');
$PAGE->set_heading('🗑 Reset COMPLETO Coaching Individualizzato');
$PAGE->set_pagelayout('admin');

global $DB;

// Conta record esistenti per anteprima.
$counts = [
    'enrollments' => $DB->count_records('local_ftm_sip_enrollments'),
    'eligibility' => $DB->count_records('local_ftm_sip_eligibility'),
    'action_plan' => $DB->count_records('local_ftm_sip_action_plan'),
    'action_history' => $DB->count_records('local_ftm_sip_action_history'),
    'meetings' => $DB->count_records('local_ftm_sip_meetings'),
    'actions' => $DB->count_records('local_ftm_sip_actions'),
    'appointments' => $DB->count_records('local_ftm_sip_appointments'),
    'applications' => $DB->count_records('local_ftm_sip_applications'),
    'contacts' => $DB->count_records('local_ftm_sip_contacts'),
    'opportunities' => $DB->count_records('local_ftm_sip_opportunities'),
    'phase_notes' => $DB->count_records('local_ftm_sip_phase_notes'),
];
$totalrecords = array_sum($counts);

// Esecuzione reset.
if ($action === 'reset' && $confirm === 'CANCELLA TUTTO' && confirm_sesskey()) {
    // Ordine: prima tabelle dipendenti, poi enrollments per ultimi.
    $deleted = [];
    $tables = [
        'local_ftm_sip_action_history',
        'local_ftm_sip_actions',
        'local_ftm_sip_appointments',
        'local_ftm_sip_applications',
        'local_ftm_sip_contacts',
        'local_ftm_sip_opportunities',
        'local_ftm_sip_meetings',
        'local_ftm_sip_action_plan',
        'local_ftm_sip_phase_notes',
        'local_ftm_sip_eligibility',
        'local_ftm_sip_enrollments',
    ];

    foreach ($tables as $t) {
        $cnt = $DB->count_records($t);
        $DB->delete_records($t);
        $deleted[$t] = $cnt;
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        '✅ Reset completato. Tutti i dati CI sono stati cancellati. '
        . 'Le aziende del registro condiviso sono state mantenute.',
        'notifysuccess'
    );

    echo '<h4>Dettaglio cancellazioni:</h4>';
    echo '<table class="generaltable" style="max-width:500px;">';
    echo '<tr><th>Tabella</th><th>Record cancellati</th></tr>';
    foreach ($deleted as $t => $cnt) {
        echo '<tr><td>' . s($t) . '</td><td>' . $cnt . '</td></tr>';
    }
    echo '<tr style="font-weight:bold;background:#dcfce7;"><td>TOTALE</td><td>' . array_sum($deleted) . '</td></tr>';
    echo '</table>';

    echo '<p style="margin-top:20px;">';
    echo html_writer::link(
        new moodle_url('/local/ftm_sip/sip_dashboard.php'),
        '← Torna alla dashboard CI',
        ['class' => 'btn btn-primary']
    );
    echo '</p>';
    echo $OUTPUT->footer();
    exit;
}

// Pagina di conferma.
echo $OUTPUT->header();
?>

<div style="max-width:700px; margin:0 auto;">

    <div style="background:#fef2f2; border-left:6px solid #dc2626; padding:20px; border-radius:8px; margin-bottom:25px;">
        <h3 style="color:#dc2626; margin-top:0;">⚠️ ATTENZIONE — Operazione IRREVERSIBILE</h3>
        <p style="font-size:14px; color:#7f1d1d; margin:8px 0;">
            Questa operazione cancella <b>TUTTI</b> i dati del Coaching Individualizzato:
            iscrizioni, valutazioni PCI, piani d'azione, incontri, azioni, appuntamenti,
            candidature, contatti aziende, opportunita, note di fase.
        </p>
        <p style="font-size:14px; color:#7f1d1d; margin:8px 0;">
            Serve a ripulire l'ambiente per permettere ai coach di fare esercitazioni su un DB pulito.
        </p>
        <p style="font-size:13px; color:#374151; background:#dcfce7; padding:8px 12px; border-radius:4px; margin-top:12px;">
            ✅ Il <b>registro aziende condiviso</b> (local_ftm_sip_companies) viene <b>mantenuto</b>.
        </p>
    </div>

    <h4 style="color:#374151;">Stato attuale del database CI:</h4>
    <table class="generaltable" style="width:100%;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th>Tabella</th>
                <th style="text-align:right;">Record presenti</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($counts as $name => $cnt): ?>
            <tr>
                <td><code><?php echo s($name); ?></code></td>
                <td style="text-align:right; font-weight:<?php echo $cnt > 0 ? 'bold' : 'normal'; ?>; color:<?php echo $cnt > 0 ? '#dc2626' : '#6b7280'; ?>;">
                    <?php echo $cnt; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#fef3c7; font-weight:bold;">
                <td>TOTALE record che verranno cancellati</td>
                <td style="text-align:right; color:#dc2626;"><?php echo $totalrecords; ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($totalrecords === 0): ?>
        <div style="background:#dcfce7; border:1px solid #16a34a; padding:15px; border-radius:8px; margin-top:20px;">
            <p style="margin:0; color:#166534;">✅ Il database CI e gia vuoto. Niente da cancellare.</p>
        </div>
        <p style="margin-top:20px;">
            <?php echo html_writer::link(
                new moodle_url('/local/ftm_sip/sip_dashboard.php'),
                '← Torna alla dashboard CI',
                ['class' => 'btn btn-secondary']
            ); ?>
        </p>
    <?php else: ?>
        <div style="background:#fff7ed; border:1px solid #ea580c; padding:18px; border-radius:8px; margin-top:25px;">
            <p style="margin-top:0;"><b>Per confermare la cancellazione, scrivi esattamente:</b></p>
            <p style="font-family:monospace; font-size:18px; font-weight:bold; color:#dc2626; margin:10px 0;">
                CANCELLA TUTTO
            </p>

            <form method="post" action="<?php echo $PAGE->url->out(false); ?>" id="reset-form">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="reset">
                <label for="confirm" style="display:block; font-weight:600; margin-top:12px;">Scrivi qui la frase di conferma:</label>
                <input type="text" name="confirm" id="confirm" required
                       autocomplete="off"
                       style="width:100%; padding:10px; font-family:monospace; font-size:16px; border:2px solid #ea580c; border-radius:6px; margin-top:6px;"
                       placeholder="CANCELLA TUTTO">

                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="submit" id="reset-submit"
                            style="background:#dc2626; color:white; border:none; padding:12px 24px; border-radius:6px; font-weight:700; cursor:pointer; font-size:14px;"
                            onclick="return confirm('CONFERMA FINALE: cancellare definitivamente TUTTI i dati CI? Operazione NON reversibile.');">
                        🗑 Cancella DEFINITIVAMENTE tutti i dati CI
                    </button>
                    <a href="<?php echo new moodle_url('/local/ftm_sip/sip_dashboard.php'); ?>"
                       style="background:#f3f4f6; color:#374151; padding:12px 24px; border:1px solid #dee2e6; border-radius:6px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center;">
                        Annulla
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php
echo $OUTPUT->footer();
