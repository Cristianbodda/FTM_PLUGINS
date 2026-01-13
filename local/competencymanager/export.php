<?php
/**
 * Export CSV/Excel - Competency Manager
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$quizid = optional_param('quizid', 0, PARAM_INT);
$area = optional_param('area', '', PARAM_TEXT);

$context = context_course::instance($courseid);
$course = get_course($courseid);

$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();

if (!$canviewall && !$isadmin) {
    if ($userid && $userid == $USER->id) {
        if (!\local_competencymanager\report_generator::student_can_view_own_report($userid, $courseid)) {
            throw new moodle_exception('nopermissions', 'error', '', 'export this report');
        }
    } else {
        throw new moodle_exception('nopermissions', 'error', '', 'export competency reports');
    }
}

if ($userid) {
    $csvdata = \local_competencymanager\report_generator::export_student_csv($userid, $courseid, $quizid, $area);
    $student = $DB->get_record('user', ['id' => $userid]);
    $filename = 'competenze_' . clean_filename(fullname($student)) . '_' . date('Ymd');
} else {
    $csvdata = \local_competencymanager\report_generator::export_class_csv($courseid, $quizid, $area);
    $filename = 'competenze_classe_' . clean_filename($course->shortname) . '_' . date('Ymd');
}

if ($format === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    
    $workbook = new MoodleExcelWorkbook($filename . '.xlsx');
    $worksheet = $workbook->add_worksheet('Competenze');
    
    $headerformat = $workbook->add_format(['bold' => 1, 'bg_color' => '#34495e', 'color' => 'white']);
    
    $row = 0;
    foreach ($csvdata as $rowdata) {
        $col = 0;
        foreach ($rowdata as $cell) {
            if ($row === 0) {
                $worksheet->write($row, $col, $cell, $headerformat);
            } else {
                $worksheet->write($row, $col, $cell);
            }
            $col++;
        }
        $row++;
    }
    
    $workbook->close();
    exit;
} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    foreach ($csvdata as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}
