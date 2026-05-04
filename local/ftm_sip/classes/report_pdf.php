<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * PDF report generator for Coaching Individualizzato (CI/SIP).
 * Used by ajax_inform_secretariat.php to attach the report to the email.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_sip;

defined('MOODLE_INTERNAL') || die();

class report_pdf {

    /**
     * Generate a PDF report for a student's CI enrollment.
     * Returns binary PDF content (you can save it or stream it).
     *
     * @param int $userid Student userid
     * @return string PDF binary content
     * @throws \moodle_exception
     */
    public static function generate($userid) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/pdflib.php');

        $data = self::load_data($userid);

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('FTM Coaching Individualizzato');
        $pdf->SetAuthor('Fondazione Terzo Millennio');
        $pdf->SetTitle('Richiesta apertura CI - ' . fullname($data['student']));
        $pdf->SetSubject('Richiesta apertura coaching individuale');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterData([0, 0, 0], [0, 0, 0]);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('helvetica', '', 11, '', true);

        // ============= PAGINA 1 — LETTERA FORMALE (UNICA PAGINA) =============
        $pdf->AddPage();
        $pdf->writeHTML(self::render_letter($data), true, false, true, false, '');

        return $pdf->Output('report.pdf', 'S'); // 'S' = return as string
    }

    /**
     * Genera il corpo della lettera formale per la segreteria.
     * Replica esatta del testo richiesto + dati istituzionali.
     */
    private static function render_letter($data) {
        $student = $data['student'];
        $e = $data['enrollment'];

        $studentname = s(fullname($student));
        $programname = s(get_config('local_ftm_sip', 'inst_program_name') ?: 'FTM sostegno tramite caching individuale');
        $nue = s(get_config('local_ftm_sip', 'inst_n_ue') ?: '1234816');
        $giornimax = s(get_config('local_ftm_sip', 'inst_giorni_max') ?: '35');
        $orarinote = s(get_config('local_ftm_sip', 'inst_orari_note') ?: 'Frequenza e orari da stabilire con organizzatore');

        // Date con valore se enrollment esiste, altrimenti placeholder per handwriting.
        $datafine_rilevamento = ($e && $e->date_start)
            ? userdate($e->date_start, '%d/%m/%Y')
            : '<u>______________</u>';
        $datainizio_ci = ($e && $e->date_start)
            ? userdate($e->date_start, '%d/%m/%Y')
            : '<u>______________</u>';
        $datafine_ci = '<u>______________</u>';
        if ($e) {
            $endts = $e->date_end_actual ?: $e->date_end_planned;
            if ($endts) {
                $datafine_ci = userdate($endts, '%d/%m/%Y');
            }
        }

        // PCI nr = nome cognome dello studente (usato come identificativo).
        // Se vuoi un numero invece, usa $student->id o $student->username.
        $pci_nr = $studentname;

        $html = '';

        // Intestazione mittente (FTM).
        $html .= '<p style="text-align:right; font-size:10pt; color:#374151;">'
            . '<b>Fondazione Terzo Millennio</b><br/>'
            . 'Locarno, ' . userdate(time(), '%d/%m/%Y') . '</p><br/>';

        $html .= '<p style="font-size:11pt;">Spett.le Segreteria,</p>';
        $html .= '<p style="font-size:11pt;">&nbsp;</p>';

        // Lettera formale (testo identico richiesto dall\'utente).
        $html .= '<p style="font-size:11pt; line-height:1.6; text-align:justify;">Buongiorno,</p>';

        $html .= '<p style="font-size:11pt; line-height:1.6; text-align:justify;">'
            . 'la presente per segnalare che per la PCI nr <b>' . $pci_nr . '</b> '
            . 'che termina il rilevamento delle competenze in data ' . $datafine_rilevamento . ' '
            . 'e stato deciso l\'affiancamento di FTM tramite coaching individuale di ulteriori 10 settimane.'
            . '</p>';

        $html .= '<p style="font-size:11pt; line-height:1.6; text-align:justify;">'
            . 'Preghiamo pertanto di voler emettere una nuova decisione con i seguenti dati:'
            . '</p>';

        // Blocco dati istituzionali — formato lista come richiesto.
        $html .= '<table cellpadding="6" cellspacing="0" style="width:100%; border:1px solid #333; margin:12px 0;">';
        $html .= '<tr><td colspan="2" style="background:#0891B2; color:#fff; font-size:12pt; font-weight:bold; text-align:center;">'
            . $programname . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; width:45%; font-weight:bold;">N. UE</td><td>' . $nue . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Nr. AS:</td><td>______________</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Data inizio:</td><td>' . $datainizio_ci . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Data fine:</td><td>' . $datafine_ci . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Giorni di frequenza massimi:</td><td>' . $giornimax . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Frequenza e orari</td><td>' . $orarinote . '</td></tr>';
        $html .= '</table>';

        $html .= '<p style="font-size:11pt; line-height:1.6; text-align:justify; margin-top:15px;">'
            . 'In attesa di ricevere la decisione via MFT per attivare il percorso salutiamo cordialmente.'
            . '</p>';

        // Firma del coach.
        global $USER;
        $html .= '<p style="font-size:11pt; margin-top:30px;">Cordiali saluti,</p>';
        $html .= '<p style="font-size:11pt; margin-top:20px;"><b>' . s(fullname($USER)) . '</b><br/>'
            . '<i>Coach FTM</i></p>';

        return $html;
    }

    /**
     * Save generated PDF to a temporary file and return its path.
     *
     * @param int $userid
     * @return array ['path' => string, 'filename' => string]
     */
    public static function generate_to_tempfile($userid) {
        $pdfbinary = self::generate($userid);
        $student = (object) ['id' => $userid];
        global $DB;
        $student = $DB->get_record('user', ['id' => $userid], 'firstname, lastname', MUST_EXIST);
        $studentname = fullname($student);

        $filename = 'CI_Report_' . clean_filename($studentname) . '_' . date('Y-m-d') . '.pdf';
        $tempdir = make_request_directory();
        $path = $tempdir . '/' . $filename;
        file_put_contents($path, $pdfbinary);

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * Load all data needed for the report.
     *
     * @param int $userid
     * @return array
     * @throws \moodle_exception
     */
    private static function load_data($userid) {
        global $DB, $USER;

        $student = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', MUST_EXIST);

        // Iscrizione: opzionale (potrebbe non esserci ancora).
        $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]) ?: null;

        // Coach: dall'iscrizione se esiste, altrimenti utente corrente.
        $coach = null;
        if ($enrollment && !empty($enrollment->coachid)) {
            $coach = $DB->get_record('user', ['id' => $enrollment->coachid], 'id, firstname, lastname, email');
        }
        if (!$coach && !empty($USER->id)) {
            $coach = $USER;
        }

        // Eligibility: cerca prima per enrollment, poi per userid.
        $eligibility = null;
        if ($enrollment && !empty($enrollment->eligibility_id)) {
            $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['id' => $enrollment->eligibility_id]);
        }
        if (!$eligibility) {
            $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);
        }

        // Tabelle dipendenti dall'enrollment: vuote se nessuna iscrizione.
        $actionplans = [];
        $meetings = [];
        $applications = [];
        $contacts = [];
        $opportunities = [];
        if ($enrollment) {
            $actionplans = $DB->get_records('local_ftm_sip_action_plan',
                ['enrollmentid' => $enrollment->id], 'id ASC');
            $meetings = $DB->get_records('local_ftm_sip_meetings',
                ['enrollmentid' => $enrollment->id], 'meeting_date ASC');
            $applications = $DB->get_records('local_ftm_sip_applications',
                ['enrollmentid' => $enrollment->id], 'application_date ASC');
            $contacts = $DB->get_records('local_ftm_sip_contacts',
                ['enrollmentid' => $enrollment->id], 'contact_date ASC');
            $opportunities = $DB->get_records('local_ftm_sip_opportunities',
                ['enrollmentid' => $enrollment->id], 'opportunity_date ASC');
        }

        return compact('student', 'enrollment', 'coach', 'eligibility',
            'actionplans', 'meetings', 'applications', 'contacts', 'opportunities');
    }

    private static function render_institutional_header($data) {
        $name = s(fullname($data['student']));
        $programname = s(get_config('local_ftm_sip', 'inst_program_name') ?: 'FTM sostegno tramite coaching individuale');
        $nue = s(get_config('local_ftm_sip', 'inst_n_ue') ?: '1234816');
        $giornimax = s(get_config('local_ftm_sip', 'inst_giorni_max') ?: '35');
        $orarinote = s(get_config('local_ftm_sip', 'inst_orari_note') ?: 'Frequenza e orari da stabilire con organizzatore');

        $e = $data['enrollment'];
        $datestart = ($e && $e->date_start) ? userdate($e->date_start, '%d/%m/%Y') : '_______________';
        $dateend = '_______________';
        if ($e) {
            $endts = $e->date_end_actual ?: $e->date_end_planned;
            if ($endts) {
                $dateend = userdate($endts, '%d/%m/%Y');
            }
        }

        // Header colorato col titolo programma.
        $html = '<table cellpadding="8" cellspacing="0" style="width:100%;">';
        $html .= '<tr><td style="background-color:#0891B2; color:#ffffff; font-size:13pt; text-align:center; font-weight:bold;">'
            . $programname . '</td></tr>';
        $html .= '<tr><td style="background-color:#155E75; color:#ffffff; font-size:9pt; text-align:center;">Fondazione Terzo Millennio &mdash; ' . $name . '</td></tr>';
        $html .= '</table><br/>';

        // Dati istituzionali obbligatori (formato scheda).
        $html .= '<table cellpadding="6" cellspacing="0" style="width:100%; border:1px solid #333;">';
        $html .= '<tr><td style="background:#f3f4f6; width:35%; font-weight:bold;">N. UE</td><td>' . $nue . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Nr. AS</td><td>______________</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Data inizio</td><td>' . $datestart . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Data fine</td><td>' . $dateend . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Giorni di frequenza massimi</td><td>' . $giornimax . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6; font-weight:bold;">Frequenza e orari</td><td><i>' . $orarinote . '</i></td></tr>';
        $html .= '</table><br/>';

        return $html;
    }

    private static function render_anagrafica($data) {
        $e = $data['enrollment']; // puo essere null
        $coach = $data['coach'];

        $statuses = [
            'requested' => 'Richiesto',
            'active' => 'Attivo',
            'closed_success' => 'Chiuso (esito positivo)',
            'closed_failure' => 'Chiuso (esito negativo)',
            'closed' => 'Chiuso',
        ];

        $statuslabel = $e ? ($statuses[$e->status] ?? $e->status) : 'Non ancora iscritto';
        $sector = $e ? strtoupper($e->sector ?? '-') : '-';
        $datestart = ($e && $e->date_start) ? userdate($e->date_start, '%d/%m/%Y') : '-';
        $dateend = '-';
        if ($e) {
            $end = $e->date_end_actual ?: $e->date_end_planned;
            if ($end) {
                $dateend = userdate($end, '%d/%m/%Y');
            }
        }
        $weeks = '-';
        if ($e && $e->date_start && ($e->date_end_actual || $e->date_end_planned)) {
            $end = $e->date_end_actual ?: $e->date_end_planned;
            $weeks = (string) max(0, (int) round(($end - $e->date_start) / (7 * 86400)));
        }

        $html = '<h3 style="color:#0891B2;">1. Anagrafica</h3>';
        $html .= '<table cellpadding="4" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr><td style="background:#f3f4f6; width:40%;"><b>Studente</b></td><td>' . s(fullname($data['student'])) . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Email</b></td><td>' . s($data['student']->email) . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Settore</b></td><td>' . s($sector) . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Coach</b></td><td>' . ($coach ? s(fullname($coach)) : '-') . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Inizio CI</b></td><td>' . $datestart . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Fine CI</b></td><td>' . $dateend . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Durata (settimane)</b></td><td>' . $weeks . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Stato</b></td><td><b>' . s($statuslabel) . '</b></td></tr>';
        if ($e && !empty($e->ladi_indemnity)) {
            $html .= '<tr><td style="background:#f3f4f6;"><b>Indennita LADI</b></td><td>' . s($e->ladi_indemnity) . '</td></tr>';
        }
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_eligibility($data) {
        $el = $data['eligibility'];
        $html = '<h3 style="color:#0891B2;">2. Griglia Valutazione PCI</h3>';
        if (!$el) {
            return $html . '<p><i>Nessuna valutazione PCI registrata.</i></p>';
        }
        // Usa le colonne reali della tabella local_ftm_sip_eligibility (scala 1-6).
        $criteria = [
            'motivazione' => 'Motivazione',
            'chiarezza_obiettivo' => 'Chiarezza Obbiettivi',
            'occupabilita' => 'Occupabilita',
            'autonomia' => 'Autonomia',
            'bisogno_coaching' => 'Bisogno Coaching',
            'comportamento' => 'Comportamento',
        ];
        $html .= '<table cellpadding="4" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr style="background:#f3f4f6;"><th><b>Criterio</b></th><th style="text-align:center;"><b>Score (1-6)</b></th></tr>';
        $total = 0;
        foreach ($criteria as $key => $label) {
            $val = isset($el->{$key}) ? (int) $el->{$key} : 0;
            $total += $val;
            $html .= '<tr><td>' . $label . '</td><td style="text-align:center;"><b>' . $val . '</b></td></tr>';
        }
        $totale_db = isset($el->totale) ? (int) $el->totale : $total;
        $decisione = isset($el->decisione) ? $el->decisione : '';
        $decmap = [
            'activate' => 'ATTIVA',
            'not_activate' => 'NON ATTIVARE',
            'refer_other' => 'INDIRIZZA AD ALTRO',
            'pending' => 'IN VALUTAZIONE',
        ];
        $declabel = $decmap[$decisione] ?? $decisione;
        $html .= '<tr style="background:#e0f2fe;"><td><b>TOTALE</b></td><td style="text-align:center;"><b>' . $totale_db . ' / 36</b></td></tr>';
        if ($declabel) {
            $html .= '<tr style="background:#dcfce7;"><td><b>DECISIONE</b></td><td style="text-align:center;"><b>' . s($declabel) . '</b></td></tr>';
        }
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_action_plan($data) {
        $html = '<h3 style="color:#0891B2;">3. Piano d\'Azione</h3>';
        if (empty($data['actionplans'])) {
            return $html . '<p><i>Nessun piano d\'azione registrato.</i></p>';
        }
        $html .= '<table cellpadding="4" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr style="background:#f3f4f6;"><th><b>Area</b></th><th style="text-align:center;"><b>Baseline</b></th><th style="text-align:center;"><b>Attuale</b></th><th style="text-align:center;"><b>Target</b></th></tr>';
        foreach ($data['actionplans'] as $ap) {
            $html .= '<tr>';
            $html .= '<td>' . s($ap->area_name ?? $ap->area_key ?? '-') . '</td>';
            $html .= '<td style="text-align:center;">' . (int) ($ap->baseline_level ?? 0) . '</td>';
            $html .= '<td style="text-align:center;"><b>' . (int) ($ap->current_level ?? 0) . '</b></td>';
            $html .= '<td style="text-align:center;">' . (int) ($ap->target_level ?? 0) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_meetings($data) {
        $html = '<h3 style="color:#0891B2;">4. Diario Coaching</h3>';
        $count = count($data['meetings']);
        $html .= '<p>Numero incontri totali: <b>' . $count . '</b></p>';
        if ($count === 0) {
            return $html;
        }
        $html .= '<table cellpadding="4" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr style="background:#f3f4f6;"><th><b>Data</b></th><th><b>Modalita</b></th><th><b>Note</b></th></tr>';
        foreach ($data['meetings'] as $m) {
            $date = $m->meeting_date ? userdate($m->meeting_date, '%d/%m/%Y') : '-';
            $notes = !empty($m->notes) ? shorten_text(s($m->notes), 200) : '-';
            $html .= '<tr><td>' . $date . '</td><td>' . s($m->modality ?? '-') . '</td><td>' . $notes . '</td></tr>';
        }
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_kpi($data) {
        $html = '<h3 style="color:#0891B2;">5. KPI</h3>';
        $apps = count($data['applications']);
        $cnts = count($data['contacts']);
        $opps = count($data['opportunities']);
        $hires = 0;
        foreach ($data['opportunities'] as $o) {
            if (!empty($o->status) && in_array($o->status, ['hired', 'collocato', 'success'], true)) {
                $hires++;
            }
        }
        $html .= '<table cellpadding="6" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr style="background:#f3f4f6;"><th><b>Indicatore</b></th><th style="text-align:center;"><b>Numero</b></th></tr>';
        $html .= '<tr><td>Candidature inviate</td><td style="text-align:center;"><b>' . $apps . '</b></td></tr>';
        $html .= '<tr><td>Contatti aziende</td><td style="text-align:center;"><b>' . $cnts . '</b></td></tr>';
        $html .= '<tr><td>Opportunita generate</td><td style="text-align:center;"><b>' . $opps . '</b></td></tr>';
        $html .= '<tr style="background:#dcfce7;"><td><b>Collocamenti</b></td><td style="text-align:center;"><b>' . $hires . '</b></td></tr>';
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_outcome($data) {
        $e = $data['enrollment'];
        $html = '<h3 style="color:#0891B2;">6. Esito Finale</h3>';
        if (in_array($e->status, ['active', 'requested'], true)) {
            return $html . '<p><i>Coaching in corso, esito non ancora definito.</i></p>';
        }
        $outcome = $e->final_outcome ?? $e->outcome ?? '-';
        $notes = $e->closure_notes ?? '-';
        $html .= '<table cellpadding="6" cellspacing="0" style="width:100%; border:1px solid #ccc;">';
        $html .= '<tr><td style="background:#f3f4f6; width:30%;"><b>Esito</b></td><td>' . s($outcome) . '</td></tr>';
        $html .= '<tr><td style="background:#f3f4f6;"><b>Note chiusura</b></td><td>' . nl2br(s($notes)) . '</td></tr>';
        $html .= '</table><br/>';
        return $html;
    }

    private static function render_footer($data) {
        global $USER;
        return '<br/><p style="font-size:8pt; color:#6b7280; text-align:right;"><i>Report generato il ' . date('d/m/Y H:i') . ' da ' . s(fullname($USER)) . '</i></p>';
    }
}
