<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint: invia email alla segreteria con il report PDF del CI dello studente.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/report_pdf.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    $context = context_system::instance();
    require_capability('local/ftm_sip:view', $context);

    // Necessario per TCPDF + email_to_user che usano $PAGE internamente.
    global $PAGE;
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/ftm_sip/ajax_inform_secretariat.php'));

    $userid = required_param('userid', PARAM_INT);
    $extramessage = optional_param('message', '', PARAM_TEXT);

    global $DB, $USER, $CFG;

    // Email destinatario configurabile (default = lucio.pagani@f3m.ch).
    $secretaryemail = get_config('local_ftm_sip', 'secretariat_email');
    if (empty($secretaryemail)) {
        $secretaryemail = 'lucio.pagani@f3m.ch';
    }

    // Verifica che lo studente esista. L'iscrizione CI e' OPZIONALE — la mail
    // puo essere inviata anche prima dell'iscrizione (richiesta apertura).
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]) ?: null;

    // Genera PDF su file temporaneo.
    $pdfinfo = \local_ftm_sip\report_pdf::generate_to_tempfile($userid);
    if (!file_exists($pdfinfo['path'])) {
        throw new moodle_exception('PDF non generato correttamente.');
    }

    // Crea destinatario fittizio per email_to_user (Moodle richiede un user object).
    $recipient = (object) [
        'id' => -99, // fake user id
        'firstname' => 'Segreteria',
        'lastname' => 'F3M',
        'email' => $secretaryemail,
        'maildisplay' => 0,
        'mailformat' => 1,
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
        'username' => 'segreteria_f3m',
        'auth' => 'manual',
        'suspended' => 0,
        'deleted' => 0,
        'emailstop' => 0,
        'lang' => 'it',
        'timezone' => 99,
        'mnethostid' => 1,
    ];

    $studentname = fullname($student);
    $coachname = fullname($USER);
    $sector = ($enrollment && !empty($enrollment->sector)) ? strtoupper($enrollment->sector) : 'N/D';
    $statuses = [
        'requested' => 'Richiesto',
        'active' => 'Attivo',
        'closed_success' => 'Chiuso (esito positivo)',
        'closed_failure' => 'Chiuso (esito negativo)',
        'closed' => 'Chiuso',
    ];
    $statuslabel = $enrollment
        ? ($statuses[$enrollment->status] ?? $enrollment->status)
        : 'NON ANCORA ISCRITTO (richiesta apertura iscrizione)';

    $subject = $enrollment
        ? '[CI] Report Coaching Individualizzato - ' . $studentname
        : '[CI] RICHIESTA APERTURA iscrizione - ' . $studentname;

    $intro = $enrollment
        ? 'in allegato trovate il report del Coaching Individualizzato relativo a:'
        : '<b>RICHIESTA APERTURA ISCRIZIONE CI</b><br/>In allegato la griglia di valutazione PCI compilata. Vi prego di procedere con l\'apertura dell\'iscrizione e di confermarmi via email l\'avvenuta accettazione.';

    $bodyhtml = '<p>Buongiorno,</p>'
        . '<p>' . $intro . '</p>'
        . '<table cellpadding="6" style="border-collapse:collapse;">'
        . '<tr><td style="border:1px solid #ccc;"><b>Studente</b></td><td style="border:1px solid #ccc;">' . s($studentname) . '</td></tr>'
        . '<tr><td style="border:1px solid #ccc;"><b>Email studente</b></td><td style="border:1px solid #ccc;">' . s($student->email) . '</td></tr>'
        . '<tr><td style="border:1px solid #ccc;"><b>Settore</b></td><td style="border:1px solid #ccc;">' . s($sector) . '</td></tr>'
        . '<tr><td style="border:1px solid #ccc;"><b>Stato CI</b></td><td style="border:1px solid #ccc;"><b>' . s($statuslabel) . '</b></td></tr>'
        . '<tr><td style="border:1px solid #ccc;"><b>Coach</b></td><td style="border:1px solid #ccc;">' . s($coachname) . '</td></tr>'
        . '</table>';

    if (!empty($extramessage)) {
        $bodyhtml .= '<p><b>Note del coach:</b><br/>' . nl2br(s($extramessage)) . '</p>';
    }

    $bodyhtml .= '<p>Cordiali saluti,<br/>' . s($coachname) . '</p>';
    $bodyhtml .= '<hr/><p style="font-size:11px;color:#888;">Email automatica generata da FTM Coaching Individualizzato il '
        . userdate(time(), '%d/%m/%Y alle %H:%M') . '.</p>';

    $bodytext = html_to_text($bodyhtml);

    // email_to_user firma:
    // email_to_user($user, $from, $subject, $messagetext, $messagehtml = '',
    //               $attachment = '', $attachname = '', $usetrueaddress = true,
    //               $replyto = '', $replytoname = '', $wordwrapwidth = 79)
    // attachment: path RELATIVO al $CFG->dataroot
    // attachname: nome con cui apparira l'allegato

    // Sposta il file PDF dentro $CFG->dataroot per usarlo come attachment relativo.
    $relpath = 'temp/sip_email/' . basename($pdfinfo['path']);
    $absdest = $CFG->dataroot . '/' . $relpath;
    $absdestdir = dirname($absdest);
    if (!is_dir($absdestdir)) {
        mkdir($absdestdir, $CFG->directorypermissions, true);
    }
    copy($pdfinfo['path'], $absdest);

    $sender = \core_user::get_noreply_user();
    if ($USER && !empty($USER->email)) {
        $sender = $USER; // usa il coach come reply-to
    }

    $sent = email_to_user(
        $recipient,
        $sender,
        $subject,
        $bodytext,
        $bodyhtml,
        $relpath,
        $pdfinfo['filename'],
        true,
        !empty($USER->email) ? $USER->email : '',
        !empty($USER->email) ? fullname($USER) : ''
    );

    // Cleanup attachment dal dataroot dopo invio.
    @unlink($absdest);

    if (!$sent) {
        throw new moodle_exception('Invio email fallito. Controlla la configurazione SMTP di Moodle.');
    }

    // Log dell'invio.
    if ($DB->get_manager()->table_exists('local_ftm_sip_logs')) {
        $DB->insert_record('local_ftm_sip_logs', (object) [
            'userid' => $userid,
            'coachid' => $USER->id,
            'action' => 'inform_secretariat',
            'details' => json_encode([
                'recipient' => $secretaryemail,
                'pdf_filename' => $pdfinfo['filename'],
                'message_excerpt' => substr($extramessage, 0, 200),
            ]),
            'timecreated' => time(),
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Email inviata con successo a ' . $secretaryemail
            . ' con il report PDF allegato (' . $pdfinfo['filename'] . ').',
        'recipient' => $secretaryemail,
        'filename' => $pdfinfo['filename'],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
