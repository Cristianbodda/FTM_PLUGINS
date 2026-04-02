<?php
/**
 * Export AIDA letter as Word (.docx) document.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$attention = required_param('attention', PARAM_RAW);
$attention_rationale = optional_param('attention_rationale', '', PARAM_RAW);
$interest = required_param('interest', PARAM_RAW);
$interest_rationale = optional_param('interest_rationale', '', PARAM_RAW);
$desire = required_param('desire', PARAM_RAW);
$desire_rationale = optional_param('desire_rationale', '', PARAM_RAW);
$action = required_param('action', PARAM_RAW);
$action_rationale = optional_param('action_rationale', '', PARAM_RAW);
$full_letter = required_param('full_letter', PARAM_RAW);
$student_name = optional_param('student_name', fullname($USER), PARAM_TEXT);

// Build Word document as HTML (simple .doc compatible format).
$filename = 'Lettera_AIDA_' . clean_filename($student_name) . '_' . date('Y-m-d') . '.doc';

header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Output HTML that Word can open.
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8">';
echo '<style>
body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #333; line-height: 1.6; margin: 40px; }
h1 { font-size: 16pt; color: #0066cc; margin-bottom: 20px; text-align: center; text-transform: uppercase; letter-spacing: 2px; }
.section { margin-bottom: 25px; page-break-inside: avoid; }
.section-header { padding: 8px 14px; border-radius: 4px; margin-bottom: 10px; font-weight: bold; font-size: 12pt; }
.section-header.attention { background: #fde8e8; color: #dc3545; border-left: 4px solid #dc3545; }
.section-header.interest { background: #e8f0fe; color: #0066cc; border-left: 4px solid #0066cc; }
.section-header.desire { background: #e8f5e9; color: #28a745; border-left: 4px solid #28a745; }
.section-header.action { background: #fff8e1; color: #f59e0b; border-left: 4px solid #f59e0b; }
.section-content { padding: 0 14px; font-size: 11pt; }
.rationale { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 12px 14px; margin-top: 10px; font-size: 10pt; color: #495057; }
.rationale-title { font-weight: bold; color: #6c757d; font-size: 9pt; text-transform: uppercase; margin-bottom: 6px; }
.full-letter { border: 2px solid #0066cc; padding: 30px; margin-top: 30px; font-size: 11pt; line-height: 1.8; }
.full-letter-header { background: #0066cc; color: white; padding: 10px 14px; font-weight: bold; font-size: 12pt; margin: -30px -30px 20px -30px; }
.placeholder { color: #dc3545; font-weight: bold; }
.footer { margin-top: 30px; text-align: center; font-size: 9pt; color: #999; border-top: 1px solid #dee2e6; padding-top: 10px; }
</style></head><body>';

// Title.
echo '<h1>Analisi AIDA - Lettera di Candidatura</h1>';
echo '<p style="text-align:center; color:#666; font-size:10pt;">Generata il ' . date('d.m.Y') . ' - ' . s($student_name) . '</p>';
echo '<hr style="border:none; border-top:2px solid #0066cc; margin:20px 0 30px;">';

// Section A - Attention.
echo '<div class="section">';
echo '<div class="section-header attention">A - ATTENTION: Cattura l\'Attenzione</div>';
echo '<div class="section-content">' . nl2br(s($attention)) . '</div>';
if (!empty($attention_rationale)) {
    echo '<div class="rationale"><div class="rationale-title">Perche questa scelta</div>' . nl2br(s($attention_rationale)) . '</div>';
}
echo '</div>';

// Section I - Interest.
echo '<div class="section">';
echo '<div class="section-header interest">I - INTEREST: Suscita Interesse</div>';
echo '<div class="section-content">' . nl2br(s($interest)) . '</div>';
if (!empty($interest_rationale)) {
    echo '<div class="rationale"><div class="rationale-title">Perche questa scelta</div>' . nl2br(s($interest_rationale)) . '</div>';
}
echo '</div>';

// Section D - Desire.
echo '<div class="section">';
echo '<div class="section-header desire">D - DESIRE: Crea il Desiderio</div>';
echo '<div class="section-content">' . nl2br(s($desire)) . '</div>';
if (!empty($desire_rationale)) {
    echo '<div class="rationale"><div class="rationale-title">Perche questa scelta</div>' . nl2br(s($desire_rationale)) . '</div>';
}
echo '</div>';

// Section A - Action.
echo '<div class="section">';
echo '<div class="section-header action">A - ACTION: Invito all\'Azione</div>';
echo '<div class="section-content">' . nl2br(s($action)) . '</div>';
if (!empty($action_rationale)) {
    echo '<div class="rationale"><div class="rationale-title">Perche questa scelta</div>' . nl2br(s($action_rationale)) . '</div>';
}
echo '</div>';

// Full Letter (page break before).
echo '<br style="page-break-before:always;">';
echo '<div class="full-letter">';
echo '<div class="full-letter-header">LETTERA COMPLETA - Pronta da Inviare</div>';

// Process full letter: convert [placeholder] markers to styled spans.
$letter_html = nl2br(s($full_letter));
$letter_html = preg_replace('/\[([^\]]+)\]/', '<span class="placeholder">[$1]</span>', $letter_html);
echo $letter_html;

echo '</div>';

// Footer.
echo '<div class="footer">';
echo 'Documento generato con JobAIDA - FTM Academy | ' . date('d.m.Y H:i');
echo '</div>';

echo '</body></html>';
