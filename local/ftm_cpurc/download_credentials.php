<?php
/**
 * Download credentials as Excel file (LADI template format).
 *
 * Supports two modes:
 * - source=session (default): reads from SESSION after import
 * - source=db: reads all active students from DB (for dashboard button)
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:import', $context);

$source = optional_param('source', 'session', PARAM_ALPHA);

global $DB, $SESSION;

$credentials = [];
$importdate = date('d.m.Y');

if ($source === 'db') {
    // ============================================================
    // Mode: DB - load filtered students using same logic as dashboard.
    // ============================================================
    $search = optional_param('search', '', PARAM_TEXT);
    $urc = optional_param('urc', '', PARAM_TEXT);
    $sector = optional_param('sector', '', PARAM_TEXT);
    $status = optional_param('status', '', PARAM_TEXT);
    $reportstatus = optional_param('reportstatus', '', PARAM_TEXT);
    $coach = optional_param('coach', 0, PARAM_INT);
    $datefrom = optional_param('datefrom', '', PARAM_TEXT);
    $dateto = optional_param('dateto', '', PARAM_TEXT);
    $groupcolor = optional_param('groupcolor', '', PARAM_TEXT);

    // Parse date filters (same logic as index.php).
    $datefrom_ts = 0;
    $dateto_ts = 0;
    if ($datefrom && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datefrom, $m)) {
        $datefrom_ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    if ($dateto && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateto, $m)) {
        $dateto_ts = mktime(23, 59, 59, (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    if ($datefrom_ts > 0 && $dateto_ts == 0) {
        $dateto_ts = $datefrom_ts + 86399;
    }

    $students = \local_ftm_cpurc\cpurc_manager::get_students([
        'search' => $search,
        'urc' => $urc,
        'sector' => $sector,
        'status' => $status,
        'report_status' => $reportstatus,
        'coach' => $coach,
        'date_from' => $datefrom_ts,
        'date_to' => $dateto_ts,
        'group_color' => $groupcolor,
    ]);

    $maxdate = 0;
    foreach ($students as $st) {
        $password = \local_ftm_cpurc\user_manager::generate_password($st->firstname);

        $group = '';
        if (!empty($st->date_start)) {
            $kw = (int)date('W', $st->date_start);
            $colorindex = ($kw - 1) % 5;
            $colors = ['giallo', 'grigio', 'rosso', 'marrone', 'viola'];
            $color = $colors[$colorindex];
            $group = 'KW ' . str_pad($kw, 2, '0', STR_PAD_LEFT) . ' gruppo ' . $color;
        }

        $credentials[] = [
            'firstname' => $st->firstname,
            'lastname' => $st->lastname,
            'city' => $st->address_city ?? '',
            'email' => $st->email,
            'personal_number' => $st->personal_number ?? '',
            'trainer' => $st->trainer ?? '',
            'username' => $st->username ?? '',
            'password' => $password,
            'group' => $group,
        ];

        $ds = $st->date_start ?? 0;
        if ($ds > $maxdate) {
            $maxdate = $ds;
        }
    }

    $importdate = $maxdate > 0 ? date('d.m.Y', $maxdate) : date('d.m.Y');

    if (empty($credentials)) {
        redirect(new moodle_url('/local/ftm_cpurc/index.php'),
            'Nessuno studente trovato con i filtri selezionati.', null, \core\output\notification::NOTIFY_WARNING);
    }

} else {
    // ============================================================
    // Mode: SESSION - read from session (after import).
    // ============================================================
    if (empty($SESSION->cpurc_credentials)) {
        redirect(new moodle_url('/local/ftm_cpurc/import.php'),
            'Nessuna credenziale disponibile. Esegui prima un import.', null, \core\output\notification::NOTIFY_ERROR);
    }

    $credentials = $SESSION->cpurc_credentials;
    $importdate = $SESSION->cpurc_credentials_date ?? date('d.m.Y');
}

// Use Moodle's bundled PhpSpreadsheet.
require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Credenziali');

// ============================================================
// Colors (matching LADI template).
// ============================================================
$colorHeaderBg = '595959';      // Dark gray header.
$colorHeaderFont = 'FFFFFF';    // White text.
$colorInputBg = 'D6E4F0';      // Light blue input fields.
$colorAcademyBg = 'F2DCAB';    // Light orange/gold F3M Academy.
$colorBorderAll = 'B4B4B4';    // Light gray borders.

// ============================================================
// Row 1: "Inizio" + date.
// ============================================================
$sheet->setCellValue('B1', 'Inizio');
$sheet->setCellValue('C1', $importdate);
$sheet->getStyle('B1')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('C1')->getFont()->setSize(12);
$sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ============================================================
// Row 3: Headers.
// Layout: A=# | B=Nome | C=Cognome | D=Localita | E=E-mail | F=N.Personale | G=Formatore | H=Username | I=Password | J=Gruppo
// ============================================================
$headers = [
    'A3' => '#',
    'B3' => 'Nome',
    'C3' => 'Cognome',
    'D3' => 'Localita',
    'E3' => 'E-mail',
    'F3' => 'N. Personale',
    'G3' => 'Formatore',
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Input section header style (A3:G3) - dark gray bg, white bold text.
$headerStyle = [
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colorHeaderFont]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorHeaderBg]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colorBorderAll]]],
];
$sheet->getStyle('A3:G3')->applyFromArray($headerStyle);
$sheet->getStyle('F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// F3M Academy section headers (H3:J3) - orange bg.
$academyHeaders = [
    'H3' => 'Username',
    'I3' => 'Password',
    'J3' => 'Gruppo',
];
foreach ($academyHeaders as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}
$academyHeaderStyle = [
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $colorHeaderFont]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorAcademyBg]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colorBorderAll]]],
];
$sheet->getStyle('H3:J3')->applyFromArray($academyHeaderStyle);

// F3M Academy label above (merged H1:J1).
$sheet->mergeCells('H1:J1');
$sheet->setCellValue('H1', 'F3M Academy');
$sheet->getStyle('H1:J1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorAcademyBg]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// ============================================================
// Column widths.
// ============================================================
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(16);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(32);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(16);
$sheet->getColumnDimension('H')->setWidth(14);
$sheet->getColumnDimension('I')->setWidth(20);
$sheet->getColumnDimension('J')->setWidth(28);

// ============================================================
// Styles.
// ============================================================
$inputStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorInputBg]],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colorBorderAll]]],
    'font' => ['size' => 11],
];

$academyStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorAcademyBg]],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colorBorderAll]]],
    'font' => ['size' => 11],
];

// ============================================================
// Data rows.
// ============================================================
$row = 4;
foreach ($credentials as $i => $cred) {
    $num = $i + 1;
    $firstname = $cred['firstname'] ?? '';
    $lastname = $cred['lastname'] ?? '';
    $city = $cred['city'] ?? '';
    $email = $cred['email'] ?? '';
    $personalnumber = $cred['personal_number'] ?? '';
    $trainer = $cred['trainer'] ?? '';
    $username = $cred['username'] ?? '';
    $password = $cred['password'] ?? '';
    $group = $cred['group'] ?? '';

    // A: # (bold).
    $sheet->setCellValue("A{$row}", $num);
    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle("A{$row}")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($colorBorderAll);

    // B-G: Input data (light blue).
    $sheet->setCellValue("B{$row}", $firstname);
    $sheet->setCellValue("C{$row}", $lastname);
    $sheet->setCellValue("D{$row}", $city);
    $sheet->setCellValue("E{$row}", $email);
    $sheet->setCellValueExplicit("F{$row}", $personalnumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue("G{$row}", $trainer);

    $sheet->getStyle("B{$row}:G{$row}")->applyFromArray($inputStyle);
    $sheet->getStyle("C{$row}")->getFont()->setBold(true); // Cognome bold.
    $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // H: Username (orange, bold).
    $sheet->setCellValue("H{$row}", $username);
    $sheet->getStyle("H{$row}")->applyFromArray($academyStyle);
    $sheet->getStyle("H{$row}")->getFont()->setBold(true);

    // I: Password (orange).
    $sheet->setCellValue("I{$row}", $password);
    $sheet->getStyle("I{$row}")->applyFromArray($academyStyle);

    // J: Group (orange).
    $sheet->setCellValue("J{$row}", $group);
    $sheet->getStyle("J{$row}")->applyFromArray($academyStyle);

    $row++;
}

// ============================================================
// Note row at the bottom.
// ============================================================
$noteRow = $row + 1;
$sheet->mergeCells("B{$noteRow}:G{$noteRow}");
$sheet->setCellValue("B{$noteRow}", 'Compilare solo questi campi/colore');
$sheet->getStyle("B{$noteRow}")->getFont()->setBold(true);
$sheet->getStyle("B{$noteRow}:G{$noteRow}")->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorInputBg]],
]);

// ============================================================
// Output Excel file.
// ============================================================
$filename = 'Credenziali_LADI_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

die();
