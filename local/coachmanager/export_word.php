<?php
// ============================================
// CoachManager - Export Word per Studente
// ============================================
// Genera un documento Word con il report completo dello studente
// Usa PHPWord per la generazione
// ============================================

define('AJAX_SCRIPT', false);

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('classes/dashboard_helper.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

$studentid = required_param('studentid', PARAM_INT);

// Get student data
$student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
$dashboard = new \local_coachmanager\dashboard_helper($USER->id);

// Enrich student data
$students = $dashboard->get_my_students();
$student_data = null;
foreach ($students as $s) {
    if ($s->id == $studentid) {
        $student_data = $s;
        break;
    }
}

if (!$student_data) {
    throw new moodle_exception('studentnotfound', 'local_coachmanager');
}

// Get notes
$notes = $DB->get_record('local_coachmanager_notes', [
    'studentid' => $studentid,
    'coachid' => $USER->id
]);
$notes_text = $notes ? $notes->notes : '';

// Try to use PHPWord if available
$phpword_available = false;

// Check if PHPWord is available via Composer autoload
$autoload_paths = [
    $CFG->dirroot . '/vendor/autoload.php',
    $CFG->dirroot . '/local/coachmanager/vendor/autoload.php',
    $CFG->libdir . '/vendor/autoload.php'
];

foreach ($autoload_paths as $autoload) {
    if (file_exists($autoload)) {
        require_once($autoload);
        if (class_exists('\PhpOffice\PhpWord\PhpWord')) {
            $phpword_available = true;
            break;
        }
    }
}

// Generate filename
$filename = 'Report_' . str_replace(' ', '_', fullname($student_data)) . '_' . date('Y-m-d') . '.docx';

if ($phpword_available) {
    // Use PHPWord
    generate_word_phpword($student_data, $notes_text, $filename);
} else {
    // Fallback: Generate simple Word-compatible HTML
    generate_word_html($student_data, $notes_text, $filename);
}

/**
 * Generate Word document using PHPWord library
 */
function generate_word_phpword($student, $notes, $filename) {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();

    // Set document properties
    $properties = $phpWord->getDocInfo();
    $properties->setCreator('FTM Coach Manager');
    $properties->setTitle('Report Studente - ' . fullname($student));
    $properties->setDescription('Report generato automaticamente dal sistema FTM');

    // Styles
    $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 24, 'color' => '667eea'], ['alignment' => 'center']);
    $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 16, 'color' => '764ba2']);
    $phpWord->addParagraphStyle('Normal', ['alignment' => 'left', 'spaceAfter' => 120]);

    $section = $phpWord->addSection();

    // Header
    $section->addTitle('Report Studente FTM', 1);
    $section->addText('Generato il: ' . date('d/m/Y H:i'), ['italic' => true, 'size' => 10]);
    $section->addTextBreak(2);

    // Student Info
    $section->addTitle('Informazioni Studente', 2);
    $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);

    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F0F0F0'])->addText('Nome:', ['bold' => true]);
    $table->addCell(6000)->addText(fullname($student));

    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F0F0F0'])->addText('Email:', ['bold' => true]);
    $table->addCell(6000)->addText($student->email);

    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F0F0F0'])->addText('Settore:', ['bold' => true]);
    $table->addCell(6000)->addText(strtoupper($student->sector ?? 'N/D'));

    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F0F0F0'])->addText('Settimana:', ['bold' => true]);
    $table->addCell(6000)->addText('Settimana ' . ($student->current_week ?? 1) . ' di 6');

    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F0F0F0'])->addText('Gruppo Colore:', ['bold' => true]);
    $table->addCell(6000)->addText(ucfirst($student->group_color ?? 'N/D'));

    $section->addTextBreak(2);

    // Progress Section
    $section->addTitle('Progressi', 2);
    $table2 = $section->addTable(['borderSize' => 1, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);

    $table2->addRow();
    $table2->addCell(3000, ['bgColor' => '667eea'])->addText('Competenze', ['bold' => true, 'color' => 'FFFFFF']);
    $table2->addCell(3000, ['bgColor' => '20c997'])->addText('Autovalutazione', ['bold' => true, 'color' => 'FFFFFF']);
    $table2->addCell(3000, ['bgColor' => 'fd7e14'])->addText('Laboratorio', ['bold' => true, 'color' => 'FFFFFF']);

    $table2->addRow();
    $comp_color = ($student->competency_avg ?? 0) < 50 ? 'dc3545' : '28a745';
    $table2->addCell(3000, ['bgColor' => 'F8F9FA'])->addText(
        round($student->competency_avg ?? 0) . '%',
        ['bold' => true, 'size' => 18, 'color' => $comp_color]
    );
    $table2->addCell(3000, ['bgColor' => 'F8F9FA'])->addText(
        $student->autoval_avg !== null ? number_format($student->autoval_avg, 1) . '/5' : 'Non completata',
        ['bold' => true, 'size' => 18]
    );
    $table2->addCell(3000, ['bgColor' => 'F8F9FA'])->addText(
        $student->lab_avg !== null ? number_format($student->lab_avg, 1) . '/5' : 'Non valutato',
        ['bold' => true, 'size' => 18]
    );

    $section->addTextBreak(2);

    // Status Section
    $section->addTitle('Stato Attività', 2);
    $section->addText('Quiz: ' . (($student->quiz_done ?? false) ? '✓ Completato' : '✗ Da completare'));
    $section->addText('Autovalutazione: ' . (($student->autoval_done ?? false) ? '✓ Completata' : '✗ Da completare'));
    $lab_status = 'Da valutare';
    if ($student->lab_done ?? false) {
        $lab_status = '✓ Valutato';
    } elseif ($student->lab_pending ?? false) {
        $lab_status = '⏳ In attesa';
    }
    $section->addText('Laboratorio: ' . $lab_status);

    $section->addTextBreak(2);

    // Timeline
    $section->addTitle('Timeline 6 Settimane', 2);
    $current_week = $student->current_week ?? 1;
    for ($week = 1; $week <= 6; $week++) {
        $status = 'Da fare';
        if ($week < $current_week) {
            $status = '✓ Completata';
        } elseif ($week == $current_week) {
            $status = '● In corso';
        }
        $section->addText('Settimana ' . $week . ': ' . $status);
    }

    $section->addTextBreak(2);

    // Notes
    if (!empty($notes)) {
        $section->addTitle('Note del Coach', 2);
        $section->addText($notes, [], 'Normal');
        $section->addTextBreak(2);
    }

    // Footer
    $section->addText(
        'Documento generato da FTM Coach Manager - ' . date('d/m/Y H:i'),
        ['italic' => true, 'size' => 9, 'color' => '888888'],
        ['alignment' => 'center']
    );

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
}

/**
 * Generate Word-compatible HTML document (fallback when PHPWord not available)
 */
function generate_word_html($student, $notes, $filename) {
    $current_week = $student->current_week ?? 1;
    $is_below = ($student->competency_avg ?? 0) < 50;

    // Timeline HTML
    $timeline_html = '';
    for ($week = 1; $week <= 6; $week++) {
        $status = 'Da fare';
        $color = '#6c757d';
        if ($week < $current_week) {
            $status = '&#10004; Completata';
            $color = '#28a745';
        } elseif ($week == $current_week) {
            $status = '&#9679; In corso';
            $color = '#ffc107';
        }
        $timeline_html .= '<tr><td style="padding: 8px; border: 1px solid #ddd;">Settimana ' . $week . '</td>';
        $timeline_html .= '<td style="padding: 8px; border: 1px solid #ddd; color: ' . $color . ';">' . $status . '</td></tr>';
    }

    // Status
    $quiz_status = ($student->quiz_done ?? false) ? '&#10004; Completato' : '&#10008; Da completare';
    $autoval_status = ($student->autoval_done ?? false) ? '&#10004; Completata' : '&#10008; Da completare';
    $lab_status = '&#10008; Da valutare';
    if ($student->lab_done ?? false) {
        $lab_status = '&#10004; Valutato';
    } elseif ($student->lab_pending ?? false) {
        $lab_status = '&#9203; In attesa';
    }

    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Report Studente - ' . htmlspecialchars(fullname($student)) . '</title>
<style>
body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
h1 { color: #667eea; text-align: center; border-bottom: 3px solid #764ba2; padding-bottom: 10px; }
h2 { color: #764ba2; margin-top: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 5px; }
table { border-collapse: collapse; width: 100%; margin: 15px 0; }
th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
th { background: #667eea; color: white; }
.info-table td:first-child { background: #f0f0f0; font-weight: bold; width: 200px; }
.progress-table td { text-align: center; font-size: 24px; font-weight: bold; }
.progress-table .header-competenze { background: #667eea; color: white; }
.progress-table .header-autoval { background: #20c997; color: white; }
.progress-table .header-lab { background: #fd7e14; color: white; }
.value-danger { color: #dc3545; }
.value-success { color: #28a745; }
.notes-box { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; margin: 15px 0; }
.footer { margin-top: 40px; text-align: center; color: #888; font-size: 12px; font-style: italic; }
.status-ok { color: #28a745; }
.status-warning { color: #ffc107; }
.status-missing { color: #dc3545; }
</style>
</head>
<body>

<h1>&#128203; Report Studente FTM</h1>
<p style="text-align: center; color: #666;">Generato il: ' . date('d/m/Y H:i') . '</p>

<h2>&#128100; Informazioni Studente</h2>
<table class="info-table">
<tr><td>Nome Completo</td><td>' . htmlspecialchars(fullname($student)) . '</td></tr>
<tr><td>Email</td><td>' . htmlspecialchars($student->email) . '</td></tr>
<tr><td>Settore</td><td><strong>' . strtoupper($student->sector ?? 'N/D') . '</strong></td></tr>
<tr><td>Settimana Corrente</td><td>Settimana ' . $current_week . ' di 6</td></tr>
<tr><td>Gruppo Colore</td><td>' . ucfirst($student->group_color ?? 'N/D') . '</td></tr>
</table>

<h2>&#128200; Progressi</h2>
<table class="progress-table">
<tr>
<th class="header-competenze">Competenze</th>
<th class="header-autoval">Autovalutazione</th>
<th class="header-lab">Laboratorio</th>
</tr>
<tr>
<td class="' . ($is_below ? 'value-danger' : 'value-success') . '">' . round($student->competency_avg ?? 0) . '%</td>
<td>' . ($student->autoval_avg !== null ? number_format($student->autoval_avg, 1) . '/5' : '--') . '</td>
<td>' . ($student->lab_avg !== null ? number_format($student->lab_avg, 1) . '/5' : '--') . '</td>
</tr>
</table>

<h2>&#128221; Stato Attività</h2>
<table>
<tr><td style="width: 200px;">Quiz</td><td class="' . (($student->quiz_done ?? false) ? 'status-ok' : 'status-missing') . '">' . $quiz_status . '</td></tr>
<tr><td>Autovalutazione</td><td class="' . (($student->autoval_done ?? false) ? 'status-ok' : 'status-missing') . '">' . $autoval_status . '</td></tr>
<tr><td>Laboratorio</td><td>' . $lab_status . '</td></tr>
</table>

<h2>&#128197; Timeline 6 Settimane</h2>
<table>
<tr><th>Settimana</th><th>Stato</th></tr>
' . $timeline_html . '
</table>';

    if (!empty($notes)) {
        $html .= '
<h2>&#128221; Note del Coach</h2>
<div class="notes-box">
' . nl2br(htmlspecialchars($notes)) . '
</div>';
    }

    $html .= '
<div class="footer">
Documento generato automaticamente da FTM Coach Manager<br>
' . date('d/m/Y H:i') . '
</div>

</body>
</html>';

    // Send as Word document
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment; filename="' . str_replace('.docx', '.doc', $filename) . '"');
    header('Cache-Control: max-age=0');

    echo $html;
    exit;
}
