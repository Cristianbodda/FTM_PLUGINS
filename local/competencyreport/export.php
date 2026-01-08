<?php
/**
 * Export dati in CSV o Excel
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);

// Context e permessi
if ($courseid) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();

if (!$canviewall && !$isadmin) {
    throw new moodle_exception('nopermissions', 'error', '', 'export competency reports');
}

// Genera i dati
if ($userid) {
    // Export singolo studente
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $data = \local_competencyreport\report_generator::export_student_csv($userid, $courseid);
    $filename = 'report_competenze_' . clean_filename(fullname($user)) . '_' . date('Y-m-d');
} else {
    // Export classe intera
    if (!$courseid) {
        throw new moodle_exception('nocourse', 'error');
    }
    $data = \local_competencyreport\report_generator::export_class_csv($courseid);
    $filename = 'report_competenze_classe_' . clean_filename($course->shortname) . '_' . date('Y-m-d');
}

if ($format === 'excel') {
    // Export Excel (XLSX)
    require_once($CFG->libdir . '/excellib.class.php');
    
    $workbook = new MoodleExcelWorkbook($filename);
    $worksheet = $workbook->add_worksheet('Report Competenze');
    
    // Stili
    $formatheader = $workbook->add_format();
    $formatheader->set_bold();
    $formatheader->set_bg_color('#4472C4');
    $formatheader->set_color('white');
    
    $formatpercent = $workbook->add_format();
    $formatpercent->set_num_format('0.0%');
    
    // Scrivi dati
    $row = 0;
    foreach ($data as $rowdata) {
        $col = 0;
        foreach ($rowdata as $cell) {
            if ($row === 0) {
                $worksheet->write($row, $col, $cell, $formatheader);
            } else {
                $worksheet->write($row, $col, $cell);
            }
            $col++;
        }
        $row++;
    }
    
    // Auto-size colonne
    for ($i = 0; $i < count($data[0]); $i++) {
        $worksheet->set_column($i, $i, 18);
    }
    
    $workbook->close();
    exit;
    
} else {
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // BOM per Excel UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';'); // Usa ; per Excel italiano
    }
    
    fclose($output);
    exit;
}
