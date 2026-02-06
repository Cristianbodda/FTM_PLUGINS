<?php
/**
 * Export Student Individual Program to Excel or PDF
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');
require_once($CFG->libdir . '/excellib.class.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);
$format = required_param('format', PARAM_ALPHA); // 'excel' or 'pdf'

$context = context_system::instance();

// Check permissions
$canmanage = has_capability('local/ftm_scheduler:manage', $context);
$cancoach = has_capability('local/ftm_scheduler:markattendance', $context);

if (!$canmanage && !$cancoach) {
    throw new moodle_exception('nopermission', 'error');
}

// Get student and group data
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$group = $DB->get_record('local_ftm_groups', ['id' => $groupid], '*', MUST_EXIST);

// Get student's sector
$sector = '';
$sectorRecord = $DB->get_record('local_student_sectors', ['userid' => $userid, 'priority' => 'primary']);
if ($sectorRecord) {
    $sector = $sectorRecord->sector;
}

// Get program data
$program = $DB->get_records('local_ftm_student_program', [
    'userid' => $userid,
    'groupid' => $groupid
], 'week_number, day_of_week, time_slot');

// Get tests data
$tests = $DB->get_records('local_ftm_student_tests', [
    'userid' => $userid,
    'groupid' => $groupid
], 'test_code');

// Organize program by week/day/slot
$programGrid = [];
foreach ($program as $p) {
    $key = "{$p->week_number}-{$p->day_of_week}-{$p->time_slot}";
    $programGrid[$key] = $p;
}

// Time slots definition
$timeSlots = [
    'AM1' => '08:00-10:00',
    'AM2' => '10:15-12:15',
    'PM1' => '13:15-15:15',
    'PM2' => '15:30-17:30'
];

$days = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì'];

$studentFullname = fullname($student);
$filename = clean_filename("Programma_Individuale_{$studentFullname}_" . date('Ymd'));

if ($format === 'excel') {
    // Excel export using Moodle's excellib
    $workbook = new MoodleExcelWorkbook("-");
    $workbook->send($filename . '.xlsx');

    $worksheet = $workbook->add_worksheet('Programma Individuale');

    // Formats
    $formatHeader = $workbook->add_format(['bold' => 1, 'bg_color' => '#4472C4', 'color' => 'white', 'align' => 'center']);
    $formatTitle = $workbook->add_format(['bold' => 1, 'size' => 14]);
    $formatSubtitle = $workbook->add_format(['bold' => 1, 'size' => 11]);
    $formatWeekHeader = $workbook->add_format(['bold' => 1, 'bg_color' => '#E2EFDA', 'border' => 1]);
    $formatDayHeader = $workbook->add_format(['bold' => 1, 'bg_color' => '#DDEBF7', 'border' => 1, 'align' => 'center']);
    $formatSlotHeader = $workbook->add_format(['bold' => 1, 'bg_color' => '#FFF2CC', 'border' => 1]);
    $formatCell = $workbook->add_format(['border' => 1, 'text_wrap' => 1, 'valign' => 'top']);
    $formatCellFixed = $workbook->add_format(['border' => 1, 'text_wrap' => 1, 'valign' => 'top', 'bg_color' => '#F2F2F2']);
    $formatTestPending = $workbook->add_format(['border' => 1, 'bg_color' => '#FFF2CC']);
    $formatTestComplete = $workbook->add_format(['border' => 1, 'bg_color' => '#C6EFCE']);

    // Set column widths
    $worksheet->set_column(0, 0, 12);  // Slot column
    for ($d = 1; $d <= 5; $d++) {
        $worksheet->set_column($d, $d, 25);  // Day columns
    }

    $row = 0;

    // Header
    $worksheet->write($row, 0, 'PROGRAMMA INDIVIDUALE - FONDAZIONE TERZO MILLENNIO', $formatTitle);
    $row += 2;

    $worksheet->write($row, 0, 'Studente:', $formatSubtitle);
    $worksheet->write($row, 1, $studentFullname);
    $row++;

    $worksheet->write($row, 0, 'Gruppo:', $formatSubtitle);
    $worksheet->write($row, 1, $group->name);
    $row++;

    $worksheet->write($row, 0, 'Settore:', $formatSubtitle);
    $worksheet->write($row, 1, $sector ?: 'Non definito');
    $row++;

    $worksheet->write($row, 0, 'Data export:', $formatSubtitle);
    $worksheet->write($row, 1, date('d/m/Y H:i'));
    $row += 2;

    // Calendar for each week
    for ($week = 1; $week <= 6; $week++) {
        $isFixed = ($week == 1);

        // Week header
        $weekLabel = $isFixed ? "SETTIMANA $week (FISSO)" : "SETTIMANA $week";
        $worksheet->write($row, 0, $weekLabel, $formatWeekHeader);
        $worksheet->write_blank($row, 1, $formatWeekHeader);
        $worksheet->write_blank($row, 2, $formatWeekHeader);
        $worksheet->write_blank($row, 3, $formatWeekHeader);
        $worksheet->write_blank($row, 4, $formatWeekHeader);
        $worksheet->write_blank($row, 5, $formatWeekHeader);
        $row++;

        // Day headers
        $worksheet->write($row, 0, 'Orario', $formatDayHeader);
        $col = 1;
        foreach ($days as $dayNum => $dayName) {
            $worksheet->write($row, $col, $dayName, $formatDayHeader);
            $col++;
        }
        $row++;

        // Slots
        foreach ($timeSlots as $slotCode => $slotTime) {
            $worksheet->write($row, 0, $slotTime, $formatSlotHeader);
            $worksheet->set_row($row, 50); // Row height

            $col = 1;
            foreach ($days as $dayNum => $dayName) {
                $key = "{$week}-{$dayNum}-{$slotCode}";
                $cellFormat = $isFixed ? $formatCellFixed : $formatCell;

                if (isset($programGrid[$key])) {
                    $p = $programGrid[$key];
                    $content = $p->activity_name;
                    if (!empty($p->activity_details)) {
                        $content .= "\n" . $p->activity_details;
                    }
                    if (!empty($p->location)) {
                        $content .= "\n[" . $p->location . "]";
                    }
                    $worksheet->write($row, $col, $content, $cellFormat);
                } else {
                    $worksheet->write_blank($row, $col, $cellFormat);
                }
                $col++;
            }
            $row++;
        }
        $row++; // Space between weeks
    }

    // Tests section
    $row += 2;
    $worksheet->write($row, 0, 'PROVE ASSEGNATE', $formatTitle);
    $row += 2;

    $worksheet->write($row, 0, 'Codice', $formatHeader);
    $worksheet->write($row, 1, 'Nome Prova', $formatHeader);
    $worksheet->write($row, 2, 'Tipo', $formatHeader);
    $worksheet->write($row, 3, 'Stato', $formatHeader);
    $worksheet->write($row, 4, 'Data Completamento', $formatHeader);
    $row++;

    if (!empty($tests)) {
        foreach ($tests as $test) {
            $testFormat = ($test->status === 'completed') ? $formatTestComplete : $formatTestPending;
            $worksheet->write($row, 0, $test->test_code, $testFormat);
            $worksheet->write($row, 1, $test->test_name, $testFormat);
            $worksheet->write($row, 2, ucfirst($test->test_type), $testFormat);
            $worksheet->write($row, 3, ($test->status === 'completed') ? 'Completato' : 'Da fare', $testFormat);
            $completedDate = $test->completed_date ? date('d/m/Y', $test->completed_date) : '-';
            $worksheet->write($row, 4, $completedDate, $testFormat);
            $row++;
        }
    } else {
        $worksheet->write($row, 0, 'Nessuna prova assegnata', $formatCell);
    }

    $workbook->close();
    exit;

} else if ($format === 'pdf') {
    // PDF export using TCPDF
    require_once($CFG->libdir . '/pdflib.php');

    $pdf = new pdf('L', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->SetCreator('FTM Scheduler');
    $pdf->SetAuthor('Fondazione Terzo Millennio');
    $pdf->SetTitle('Programma Individuale - ' . $studentFullname);
    $pdf->SetSubject('Programma Individuale Studente');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFooterData([0, 0, 0], [0, 0, 0]);

    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->setFontSubsetting(true);
    $pdf->SetFont('helvetica', '', 9, '', true);

    $pdf->AddPage();

    // Header
    $html = '<h1 style="color:#4472C4; font-size:16pt;">PROGRAMMA INDIVIDUALE</h1>';
    $html .= '<h2 style="color:#333; font-size:12pt;">Fondazione Terzo Millennio</h2>';
    $html .= '<br/>';
    $html .= '<table cellpadding="3">';
    $html .= '<tr><td width="100"><strong>Studente:</strong></td><td>' . s($studentFullname) . '</td></tr>';
    $html .= '<tr><td><strong>Gruppo:</strong></td><td>' . s($group->name) . '</td></tr>';
    $html .= '<tr><td><strong>Settore:</strong></td><td>' . s($sector ?: 'Non definito') . '</td></tr>';
    $html .= '<tr><td><strong>Data export:</strong></td><td>' . date('d/m/Y H:i') . '</td></tr>';
    $html .= '</table>';
    $html .= '<br/><br/>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Calendar for each week (2 weeks per page)
    $weeksOnPage = 0;

    for ($week = 1; $week <= 6; $week++) {
        if ($weeksOnPage >= 2) {
            $pdf->AddPage();
            $weeksOnPage = 0;
        }

        $isFixed = ($week == 1);
        $bgColor = $isFixed ? '#F2F2F2' : '#FFFFFF';
        $weekLabel = $isFixed ? "SETTIMANA $week (FISSO)" : "SETTIMANA $week";

        $html = '<table border="1" cellpadding="3" style="font-size:8pt;">';
        $html .= '<tr style="background-color:#E2EFDA;"><td colspan="6"><strong>' . $weekLabel . '</strong></td></tr>';

        // Day headers
        $html .= '<tr style="background-color:#DDEBF7;">';
        $html .= '<td width="12%" align="center"><strong>Orario</strong></td>';
        foreach ($days as $dayNum => $dayName) {
            $html .= '<td width="17.6%" align="center"><strong>' . $dayName . '</strong></td>';
        }
        $html .= '</tr>';

        // Slots
        foreach ($timeSlots as $slotCode => $slotTime) {
            $html .= '<tr>';
            $html .= '<td style="background-color:#FFF2CC;" align="center"><strong>' . $slotTime . '</strong></td>';

            foreach ($days as $dayNum => $dayName) {
                $key = "{$week}-{$dayNum}-{$slotCode}";
                $cellBg = $isFixed ? 'background-color:#F2F2F2;' : '';

                if (isset($programGrid[$key])) {
                    $p = $programGrid[$key];
                    $content = s($p->activity_name);
                    if (!empty($p->activity_details)) {
                        $content .= '<br/><small>' . s($p->activity_details) . '</small>';
                    }
                    if (!empty($p->location)) {
                        $content .= '<br/><small>[' . s($p->location) . ']</small>';
                    }
                    $html .= '<td style="' . $cellBg . '">' . $content . '</td>';
                } else {
                    $html .= '<td style="' . $cellBg . '">&nbsp;</td>';
                }
            }
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '<br/>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $weeksOnPage++;
    }

    // Tests section - new page
    $pdf->AddPage('P'); // Portrait for tests

    $html = '<h2 style="color:#4472C4;">PROVE ASSEGNATE</h2>';
    $html .= '<br/>';

    $html .= '<table border="1" cellpadding="5">';
    $html .= '<tr style="background-color:#4472C4; color:white;">';
    $html .= '<td width="15%" align="center"><strong>Codice</strong></td>';
    $html .= '<td width="40%"><strong>Nome Prova</strong></td>';
    $html .= '<td width="15%" align="center"><strong>Tipo</strong></td>';
    $html .= '<td width="15%" align="center"><strong>Stato</strong></td>';
    $html .= '<td width="15%" align="center"><strong>Data</strong></td>';
    $html .= '</tr>';

    if (!empty($tests)) {
        foreach ($tests as $test) {
            $bgColor = ($test->status === 'completed') ? '#C6EFCE' : '#FFF2CC';
            $statusLabel = ($test->status === 'completed') ? 'Completato' : 'Da fare';
            $completedDate = $test->completed_date ? date('d/m/Y', $test->completed_date) : '-';

            $html .= '<tr style="background-color:' . $bgColor . ';">';
            $html .= '<td align="center">' . s($test->test_code) . '</td>';
            $html .= '<td>' . s($test->test_name) . '</td>';
            $html .= '<td align="center">' . ucfirst(s($test->test_type)) . '</td>';
            $html .= '<td align="center">' . $statusLabel . '</td>';
            $html .= '<td align="center">' . $completedDate . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="5" align="center">Nessuna prova assegnata</td></tr>';
    }

    $html .= '</table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Output
    $pdf->Output($filename . '.pdf', 'D');
    exit;

} else {
    throw new moodle_exception('invalidformat', 'error');
}
