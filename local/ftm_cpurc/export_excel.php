<?php
// This file is part of Moodle - http://moodle.org/
//
// Export students to Excel.
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');
require_once($CFG->libdir . '/excellib.class.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:view', $context);

// Get filter parameters (same as index.php).
$search = optional_param('search', '', PARAM_TEXT);
$urc = optional_param('urc', '', PARAM_TEXT);
$sector = optional_param('sector', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_TEXT);
$reportstatus = optional_param('reportstatus', '', PARAM_TEXT);
$coach = optional_param('coach', 0, PARAM_INT);

// Get filtered data.
$students = \local_ftm_cpurc\cpurc_manager::get_students([
    'search' => $search,
    'urc' => $urc,
    'sector' => $sector,
    'status' => $status,
    'report_status' => $reportstatus,
    'coach' => $coach,
]);

// Create Excel workbook.
$filename = 'cpurc_studenti_' . date('Y-m-d_His') . '.xlsx';
$workbook = new MoodleExcelWorkbook($filename);
$worksheet = $workbook->add_worksheet('Studenti CPURC');

// Define column headers.
$headers = [
    'ID',
    'Cognome',
    'Nome',
    'Email',
    'Telefono',
    'Cellulare',
    'Indirizzo',
    'CAP',
    'Citta',
    'Data Nascita',
    'Nazionalita',
    'Permesso',
    'Numero AVS',
    'URC',
    'Consulente URC',
    'Settore',
    'Coach',
    'Formatore',
    'Misura',
    'Data Inizio',
    'Data Fine Prevista',
    'Data Fine Effettiva',
    'Settimana',
    'Stato',
    'Stato Report',
    'Assenze Totali',
    'Colloqui',
    'Ultima Professione',
    'IBAN',
    'Stato Civile'
];

// Write headers with formatting.
$formatheader = $workbook->add_format();
$formatheader->set_bold();
$formatheader->set_bg_color('#e0e0e0');
$formatheader->set_border(1);

$col = 0;
foreach ($headers as $header) {
    $worksheet->write_string(0, $col, $header, $formatheader);
    $col++;
}

// Write data rows.
$formatdate = $workbook->add_format();
$formatdate->set_num_format('DD/MM/YYYY');

$row = 1;
foreach ($students as $student) {
    $week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($student->date_start);
    $weekstatus = \local_ftm_cpurc\cpurc_manager::get_week_status($week);

    // Report status.
    $reportStatusText = 'Non iniziato';
    if (!empty($student->reportid)) {
        if ($student->report_status === 'draft') {
            $reportStatusText = 'Bozza';
        } else {
            $reportStatusText = 'Completo';
        }
    }

    // Coach name.
    $coachName = '';
    if (!empty($student->coach_firstname) && !empty($student->coach_lastname)) {
        $coachName = $student->coach_lastname . ' ' . $student->coach_firstname;
    }

    // Sector name.
    $sectorName = '';
    if (!empty($student->sector_detected)) {
        $sectorName = \local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected);
    }

    $col = 0;
    $worksheet->write_number($row, $col++, $student->id);
    $worksheet->write_string($row, $col++, $student->lastname ?? '');
    $worksheet->write_string($row, $col++, $student->firstname ?? '');
    $worksheet->write_string($row, $col++, $student->email ?? '');
    $worksheet->write_string($row, $col++, $student->phone ?? '');
    $worksheet->write_string($row, $col++, $student->mobile ?? '');
    $worksheet->write_string($row, $col++, $student->address_street ?? '');
    $worksheet->write_string($row, $col++, $student->address_cap ?? '');
    $worksheet->write_string($row, $col++, $student->address_city ?? '');

    // Date of birth.
    if (!empty($student->birthdate)) {
        $worksheet->write_string($row, $col++, date('d/m/Y', $student->birthdate));
    } else {
        $worksheet->write_string($row, $col++, '');
    }

    $worksheet->write_string($row, $col++, $student->nationality ?? '');
    $worksheet->write_string($row, $col++, $student->permit ?? '');
    $worksheet->write_string($row, $col++, $student->avs_number ?? '');
    $worksheet->write_string($row, $col++, $student->urc_office ?? '');
    $worksheet->write_string($row, $col++, $student->urc_consultant ?? '');
    $worksheet->write_string($row, $col++, $sectorName);
    $worksheet->write_string($row, $col++, $coachName);
    $worksheet->write_string($row, $col++, $student->trainer ?? '');
    $worksheet->write_string($row, $col++, $student->measure ?? '');

    // Dates.
    if (!empty($student->date_start)) {
        $worksheet->write_string($row, $col++, date('d/m/Y', $student->date_start));
    } else {
        $worksheet->write_string($row, $col++, '');
    }

    if (!empty($student->date_end_planned)) {
        $worksheet->write_string($row, $col++, date('d/m/Y', $student->date_end_planned));
    } else {
        $worksheet->write_string($row, $col++, '');
    }

    if (!empty($student->date_end_actual)) {
        $worksheet->write_string($row, $col++, date('d/m/Y', $student->date_end_actual));
    } else {
        $worksheet->write_string($row, $col++, '');
    }

    $worksheet->write_number($row, $col++, $week);
    $worksheet->write_string($row, $col++, $weekstatus['label']);
    $worksheet->write_string($row, $col++, $reportStatusText);
    $worksheet->write_number($row, $col++, (int)($student->absence_total ?? 0));
    $worksheet->write_number($row, $col++, (int)($student->interviews ?? 0));
    $worksheet->write_string($row, $col++, $student->last_profession ?? '');
    $worksheet->write_string($row, $col++, $student->iban ?? '');
    $worksheet->write_string($row, $col++, $student->civil_status ?? '');

    $row++;
}

// Set column widths.
$widths = [8, 15, 15, 25, 15, 15, 25, 8, 15, 12, 12, 10, 18, 15, 20, 15, 20, 15, 15, 12, 12, 12, 10, 12, 12, 10, 10, 25, 25, 12];
for ($i = 0; $i < count($widths); $i++) {
    $worksheet->set_column($i, $i, $widths[$i]);
}

// Close and send file.
$workbook->close();
exit;
