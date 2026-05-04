<?php
/**
 * AJAX endpoint: Request CI activation — sends email to segreteria@f3m.ch
 * with PDF attachment containing activation request data.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:coach', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    if ($action !== 'request_activation') {
        throw new Exception('Azione non valida.');
    }

    $userid = required_param('userid', PARAM_INT);
    $enrollmentid = required_param('enrollmentid', PARAM_INT);

    // Load student data.
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid, 'userid' => $userid], '*', MUST_EXIST);

    // Load eligibility data.
    $eligibility = null;
    if ($enrollment->eligibility_id) {
        $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['id' => $enrollment->eligibility_id]);
    }
    if (!$eligibility) {
        $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);
    }

    // Calculate dates.
    $date_start = $enrollment->date_start ? userdate($enrollment->date_start, '%d.%m.%Y') : '...............';
    $date_end = $enrollment->date_end_planned ? userdate($enrollment->date_end_planned, '%d.%m.%Y') : '...............';

    // Get end date of 6-week period (rilevamento competenze).
    // Assume the 6-week assessment ends when CI starts.
    $date_fine_rilevamento = $date_start;

    // LADI indemnities.
    $ladi = $enrollment->ladi_indemnity ? (string)$enrollment->ladi_indemnity : '...............';

    // Student's personal number (from idnumber or custom profile field).
    $nr_personale = !empty($student->idnumber) ? $student->idnumber : '...............';

    // Build the letter text.
    $student_name = fullname($student);
    $letter_text = "Buongiorno,\n\n"
        . "la presente per segnalare che per la PCI nr. {$nr_personale} ({$student_name}) "
        . "che termina il rilevamento delle competenze in data {$date_fine_rilevamento} "
        . "e' stato deciso l'affiancamento di FTM tramite coaching individuale di ulteriori 10 settimane.\n\n"
        . "Preghiamo pertanto di voler emettere una nuova decisione con i seguenti dati:\n\n"
        . "FTM sostegno tramite coaching individuale\n"
        . "N. UE 1234816\n"
        . "Nr. AS: {$nr_personale}\n"
        . "Data inizio: {$date_start}\n"
        . "Data fine: {$date_end}\n"
        . "Giorni di frequenza massimi: 35\n"
        . "Frequenza e orari da stabilire con organizzatore\n\n"
        . "In attesa di ricevere la decisione via MFT per attivare il percorso salutiamo cordialmente.\n\n"
        . "Amministrazione";

    // ========== Generate PDF ==========
    // Use TCPDF if available (Moodle ships it).
    $pdfpath = null;
    $pdfcontent = null;

    if (class_exists('\TCPDF') || file_exists($CFG->dirroot . '/lib/tcpdf/tcpdf.php')) {
        if (!class_exists('\TCPDF')) {
            require_once($CFG->dirroot . '/lib/tcpdf/tcpdf.php');
        }

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('FTM Academy');
        $pdf->SetAuthor('FTM - Coaching Individualizzato');
        $pdf->SetTitle('Richiesta Attivazione CI - ' . $student_name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(25, 25, 25);
        $pdf->AddPage();

        // Header.
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Richiesta Attivazione Coaching Individualizzato', 0, 1, 'C');
        $pdf->Ln(5);

        // Date.
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Data: ' . date('d.m.Y'), 0, 1, 'R');
        $pdf->Ln(5);

        // Body.
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 7, $letter_text, 0, 'L');

        // Eligibility summary if available.
        if ($eligibility) {
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 7, 'Griglia Valutazione PCI', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);

            $criteria = [
                'Motivazione' => $eligibility->motivazione ?? '-',
                'Chiarezza Obiettivo' => $eligibility->chiarezza_obiettivo ?? '-',
                'Occupabilita' => $eligibility->occupabilita ?? '-',
                'Autonomia' => $eligibility->autonomia ?? '-',
                'Bisogno Coaching' => $eligibility->bisogno_coaching ?? '-',
                'Comportamento' => $eligibility->comportamento ?? '-',
            ];

            foreach ($criteria as $label => $val) {
                $pdf->Cell(60, 6, $label . ':', 0, 0, 'L');
                $pdf->Cell(20, 6, $val . '/6', 0, 1, 'L');
            }

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(60, 6, 'Totale:', 0, 0, 'L');
            $pdf->Cell(20, 6, ($eligibility->totale ?? '-') . '/36', 0, 1, 'L');

            $decisione_labels = [
                'idoneo_prioritario' => 'Idoneo Prioritario',
                'idoneo' => 'Idoneo',
                'non_idoneo' => 'Non Idoneo',
            ];
            $pdf->Cell(60, 6, 'Decisione:', 0, 0, 'L');
            $pdf->Cell(60, 6, $decisione_labels[$eligibility->decisione ?? ''] ?? ($eligibility->decisione ?? '-'), 0, 1, 'L');
        }

        // Signature lines.
        $pdf->Ln(20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 6, '________________________________', 0, 0, 'C');
        $pdf->Cell(0, 6, '________________________________', 0, 1, 'C');
        $pdf->Cell(80, 6, 'Coach', 0, 0, 'C');
        $pdf->Cell(0, 6, 'Amministrazione', 0, 1, 'C');

        // Save PDF to temp file.
        $pdfpath = tempnam(sys_get_temp_dir(), 'ci_request_') . '.pdf';
        $pdf->Output($pdfpath, 'F');
        $pdfcontent = file_get_contents($pdfpath);
    }

    // ========== Send Email ==========
    $to = 'segreteria@f3m.ch';
    $subject = 'Richiesta Attivazione CI - ' . $student_name;

    // Build HTML email body.
    $html_body = '<div style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto;">';
    $html_body .= '<div style="background:#0891B2; color:#fff; padding:16px 20px; border-radius:8px 8px 0 0;">';
    $html_body .= '<h2 style="margin:0; font-size:16px;">Richiesta Attivazione Coaching Individualizzato</h2>';
    $html_body .= '</div>';
    $html_body .= '<div style="padding:20px; background:#fff; border:1px solid #e5e7eb; border-top:none; border-radius:0 0 8px 8px;">';
    $html_body .= '<p>' . nl2br(htmlspecialchars($letter_text)) . '</p>';

    if ($eligibility) {
        $html_body .= '<hr style="border:none; border-top:1px solid #e5e7eb; margin:16px 0;">';
        $html_body .= '<h3 style="color:#0891B2; font-size:14px;">Griglia Valutazione PCI</h3>';
        $html_body .= '<table style="border-collapse:collapse; font-size:13px;">';
        foreach ($criteria as $label => $val) {
            $html_body .= '<tr><td style="padding:3px 10px 3px 0; font-weight:600;">' . $label . '</td><td>' . $val . '/6</td></tr>';
        }
        $html_body .= '<tr style="border-top:1px solid #e5e7eb;"><td style="padding:6px 10px 3px 0; font-weight:700;">Totale</td><td style="padding:6px 0 3px; font-weight:700;">' . ($eligibility->totale ?? '-') . '/36</td></tr>';
        $html_body .= '</table>';
    }

    $html_body .= '<p style="font-size:11px; color:#9ca3af; margin-top:16px;">Inviato da: ' . fullname($USER) . ' — ' . date('d/m/Y H:i') . '</p>';
    $html_body .= '</div></div>';

    // Use Moodle email API.
    $supportuser = \core_user::get_support_user();

    // Create a fake user for the segreteria address.
    $touser = new \stdClass();
    $touser->id = -1;
    $touser->email = $to;
    $touser->firstname = 'Segreteria';
    $touser->lastname = 'FTM';
    $touser->maildisplay = 1;
    $touser->mailformat = 1;
    $touser->auth = 'manual';
    $touser->suspended = 0;
    $touser->deleted = 0;
    $touser->emailstop = 0;

    // Send email.
    $success = email_to_user(
        $touser,
        $USER,
        $subject,
        strip_tags($letter_text),
        $html_body,
        $pdfpath ? $pdfpath : '',           // attachment path
        $pdfpath ? 'Richiesta_CI_' . str_replace(' ', '_', $student_name) . '.pdf' : '' // attachment name
    );

    // Cleanup temp PDF.
    if ($pdfpath && file_exists($pdfpath)) {
        unlink($pdfpath);
    }

    if (!$success) {
        throw new Exception('Errore nell\'invio dell\'email. Verifica la configurazione SMTP di Moodle.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Richiesta inviata a segreteria@f3m.ch con PDF allegato.',
    ]);

} catch (Exception $e) {
    // Cleanup temp PDF on error.
    if (isset($pdfpath) && $pdfpath && file_exists($pdfpath)) {
        unlink($pdfpath);
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
