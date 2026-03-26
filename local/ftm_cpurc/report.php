<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Report generation page - Document-style interface.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:generatereport', $context);

$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Support access by userid (from coach dashboard)
if (!$id && $userid) {
    $cpurc_student = \local_ftm_cpurc\cpurc_manager::get_student_by_userid($userid);
    if ($cpurc_student) {
        $id = $cpurc_student->id;
    } else {
        // Auto-create CPURC student record
        $moodle_user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $newstudent = new stdClass();
        $newstudent->userid = $userid;
        $newstudent->timecreated = time();
        $newstudent->timemodified = time();
        $id = $DB->insert_record('local_ftm_cpurc_students', $newstudent);
    }
}

if (!$id) {
    throw new moodle_exception('Missing id or userid parameter');
}

// Get student data.
$student = \local_ftm_cpurc\cpurc_manager::get_student($id);
if (!$student) {
    throw new moodle_exception('Student not found');
}

// Block access to cancelled students.
if ($student->status === 'cancelled') {
    redirect(
        new moodle_url('/local/ftm_cpurc/index.php'),
        'Iscrizione annullata - studente non piu accessibile.',
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Get Moodle user.
$user = $DB->get_record('user', ['id' => $student->userid]);

// Get existing report if any.
$report = \local_ftm_cpurc\cpurc_manager::get_report($id);

// Get ASSIGNED coach for this student (not current user).
$assignedCoach = \local_ftm_cpurc\cpurc_manager::get_student_coach($student->userid);
if ($assignedCoach) {
    $coach = $assignedCoach;
} else {
    // Fallback to current user if no coach assigned.
    $coach = $USER;
}
$coachInitials = strtoupper(substr($coach->firstname, 0, 1) . substr($coach->lastname, 0, 1));
$coachEmail = strtolower($coach->firstname) . '.' . strtolower($coach->lastname) . '@f3m.ch';
// Clean accents/spaces from email.
$coachEmail = str_replace(' ', '', $coachEmail);
$coachEmail = strtr($coachEmail, [
    'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
]);

// Calculate participation days: X + O (real attendance from Aladino data, supports half-days).
$presenze_x = (float) ($student->absence_x ?? 0);
$presenze_o = (float) ($student->absence_o ?? 0);
$participationDays = $presenze_x + $presenze_o;

// Fallback to date-based calculation if no Aladino data loaded yet.
// Format: show decimal only if needed (22 not 22.0, but 6.5 stays 6.5).
$participationDays = (floor($participationDays) == $participationDays)
    ? (int) $participationDays : $participationDays;

if ($participationDays == 0 && !empty($student->date_start)) {
    $end_ts = $student->date_end_actual ?? $student->date_end_planned ?? 0;
    if ($end_ts > 0) {
        $start = new DateTime();
        $start->setTimestamp($student->date_start);
        $end = new DateTime();
        $end->setTimestamp($end_ts);
        $days = 0;
        $current = clone $start;
        while ($current <= $end) {
            if ((int)$current->format('N') <= 5) {
                $days++;
            }
            $current->modify('+1 day');
        }
        $participationDays = $days - (int)($student->absence_total ?? 0);
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/report.php', ['id' => $id]));
$PAGE->set_title('Rapporto finale d\'attivita\' - ' . fullname($user));
$PAGE->set_heading('Rapporto finale d\'attivita\'');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
/* Document container - FULL WIDTH for better readability */
.doc-container {
    max-width: 100%;
    margin: 0;
    background: white;
    box-shadow: none;
    font-family: 'Calibri', 'Arial', sans-serif;
    font-size: 13pt;
    line-height: 1.5;
    color: #000;
}

/* Toolbar */
.doc-toolbar {
    position: sticky;
    top: 0;
    background: #2c3e50;
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 100;
    border-radius: 0;
}

.doc-toolbar-title {
    font-weight: 600;
    font-size: 18px;
}

.doc-toolbar-buttons {
    display: flex;
    gap: 10px;
}

.doc-toolbar-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.doc-toolbar-btn-primary { background: #3498db; color: white !important; }
.doc-toolbar-btn-success { background: #27ae60; color: white !important; }
.doc-toolbar-btn-secondary { background: #95a5a6; color: white !important; }
.doc-toolbar-btn-aladino { background: #e67e22; color: white !important; }
.doc-toolbar-btn:hover { opacity: 0.9; color: white !important; }
a.doc-toolbar-btn, a.doc-toolbar-btn:visited, a.doc-toolbar-btn:hover { color: white !important; text-decoration: none !important; }

/* Document page - wider padding */
.doc-page {
    padding: 20px 30px;
    min-height: auto;
}

/* Document header */
.doc-header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #000;
}

.doc-header-title {
    font-size: 18pt;
    font-weight: bold;
    margin: 0;
    letter-spacing: 1px;
}

.doc-header-subtitle {
    font-size: 10pt;
    color: #555;
    margin-top: 8px;
    font-style: italic;
    line-height: 1.4;
}

/* Section styling */
.doc-section {
    margin-bottom: 25px;
    page-break-inside: avoid;
}

.doc-section-title {
    font-size: 15pt;
    font-weight: bold;
    background: #f0f0f0;
    padding: 12px 15px;
    margin: 0 0 15px 0;
    border-left: 5px solid #2c3e50;
}

.doc-section-content {
    padding: 0 12px;
}

/* Official table - bordered, gray headers */
.doc-official-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}

.doc-official-table td,
.doc-official-table th {
    border: 1px solid #999;
    padding: 8px 12px;
    vertical-align: top;
    font-size: 13pt;
}

.doc-official-table .label-cell {
    font-weight: 600;
    background: #e8e8e8;
    width: 220px;
    white-space: nowrap;
}

.doc-official-table .value-cell {
    background: #fafafa;
}

/* Info table (old style kept for compat) */
.doc-info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}

.doc-info-table td {
    padding: 6px 10px;
    vertical-align: top;
}

.doc-info-table .label {
    font-weight: 600;
    width: 200px;
    color: #333;
    font-size: 13pt;
}

.doc-info-table .value {
    background: #fafafa;
    border-bottom: 1px dotted #ccc;
}

/* Editable field */
.doc-field {
    width: 100%;
    border: none;
    border-bottom: 1px solid #ccc;
    background: white;
    padding: 4px 8px;
    font-family: inherit;
    font-size: inherit;
    transition: all 0.2s;
}

.doc-field:focus {
    outline: none;
    background: #f8f9fa;
    border-bottom: 2px solid #2c3e50;
}

.doc-field-textarea {
    width: 100%;
    min-height: 120px;
    border: 1px solid #ccc;
    background: white;
    padding: 15px;
    font-family: inherit;
    font-size: 14pt;
    resize: vertical;
    border-radius: 4px;
}

.doc-field-textarea:focus {
    outline: none;
    background: #f8f9fa;
    border-color: #2c3e50;
}

/* Absence table */
.doc-absence-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12pt;
}

.doc-absence-table th,
.doc-absence-table td {
    border: 1px solid #999;
    padding: 10px 12px;
    text-align: center;
}

.doc-absence-table th {
    background: #e8e8e8;
    font-weight: 600;
}

.doc-absence-table td {
    background: #fafafa;
}

/* Competency table with text scale */
.doc-competency-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.doc-competency-table th,
.doc-competency-table td {
    border: 1px solid #bbb;
    padding: 10px 8px;
    text-align: left;
    font-size: 13pt;
}

.doc-competency-table th {
    background: #e8e8e8;
    font-weight: 600;
    font-size: 11pt;
    text-align: center;
}

.doc-competency-table th:first-child {
    text-align: left;
    font-size: 13pt;
}

.doc-competency-table .rating-cell {
    text-align: center;
    width: 110px;
    padding: 4px;
    vertical-align: middle;
}

/* Rating radio buttons - styled as table cells */
.doc-rating-cell {
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.doc-rating-cell input[type="radio"] {
    display: none;
}

.doc-rating-cell label {
    display: block;
    width: 100%;
    height: 100%;
    padding: 10px 4px;
    cursor: pointer;
    margin: 0;
    font-size: 12pt;
    transition: all 0.2s;
}

.doc-rating-cell:hover {
    background: #e3f2fd;
}

.doc-rating-cell.selected {
    background: #2c3e50;
    color: white;
}

.doc-rating-cell.selected label {
    color: white;
}

/* Overall rating scale */
.doc-rating-scale {
    display: flex;
    justify-content: space-around;
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}

.doc-rating-option {
    text-align: center;
    flex: 1;
}

.doc-rating-option input[type="radio"] {
    display: none;
}

.doc-rating-option label {
    display: block;
    padding: 15px 10px;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 4px;
    transition: all 0.2s;
}

.doc-rating-option label:hover {
    background: #e3f2fd;
}

.doc-rating-option input[type="radio"]:checked + label {
    background: #2c3e50;
    color: white;
    border-color: #2c3e50;
}

.doc-rating-label {
    font-size: 12pt;
    font-weight: 600;
}

/* Checkbox styling */
.doc-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 10px 0;
}

.doc-checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: #f5f5f5;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13pt;
}

.doc-checkbox-item:hover {
    background: #e8e8e8;
}

.doc-checkbox-item input[type="checkbox"] {
    width: 22px;
    height: 22px;
    cursor: pointer;
    flex-shrink: 0;
}

/* Yes/No toggle */
.doc-yesno {
    display: flex;
    gap: 20px;
    margin: 10px 0;
}

.doc-yesno-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

.doc-yesno-option input[type="radio"] {
    width: 24px;
    height: 24px;
}

.doc-yesno-option span {
    font-size: 14pt;
}

/* Subsection */
.doc-subsection {
    margin: 15px 0;
    padding-left: 10px;
    border-left: 2px solid #ddd;
}

.doc-subsection-title {
    font-weight: 600;
    font-size: 13pt;
    color: #333;
    margin-bottom: 12px;
}

.doc-subsection-hint {
    display: none;
}

/* Hired details */
.doc-hired-details {
    display: none;
    padding: 15px;
    background: #e8f5e9;
    border-radius: 4px;
    margin-top: 10px;
}

.doc-hired-details.visible {
    display: block;
}

/* Alert messages */
.doc-alert {
    padding: 18px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
    display: none;
    font-size: 14pt;
    line-height: 1.6;
}

.doc-alert.success {
    display: block;
    background: #d4edda;
    border: 2px solid #28a745;
    color: #155724;
}

.doc-alert.error {
    display: block;
    background: #f8d7da;
    border: 2px solid #dc3545;
    color: #721c24;
}

/* Signatures section */
.doc-signatures {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ccc;
}

.doc-signatures-row {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.doc-signature-block {
    width: 45%;
}

.doc-signature-line {
    border-bottom: 1px solid #000;
    margin-top: 60px;
    padding-bottom: 5px;
}

.doc-signature-label {
    font-size: 11pt;
    color: #333;
    margin-top: 5px;
}

/* Reinsertion cell */
.reinsertion-cell {
    transition: all 0.2s;
}

.reinsertion-cell:hover {
    background: #e3f2fd;
}

.reinsertion-cell.selected {
    background: #2c3e50;
    color: white;
}

/* Print styles */
@media print {
    .doc-toolbar { display: none; }
    .doc-container { box-shadow: none; }
    .doc-field, .doc-field-textarea {
        background: transparent;
        border: none;
        border-bottom: 1px solid #000;
    }
    .doc-rating-cell.selected {
        background: #2c3e50 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .doc-signature-line {
        margin-top: 80px;
    }
    .reinsertion-cell.selected {
        background: #2c3e50 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<div class="doc-container">
    <!-- Toolbar -->
    <div class="doc-toolbar">
        <div class="doc-toolbar-title">
            Rapporto finale d'attivita' - <?php echo fullname($user); ?>
        </div>
        <div class="doc-toolbar-buttons">
            <button type="button" class="doc-toolbar-btn doc-toolbar-btn-secondary" onclick="window.print();">
                Stampa
            </button>
            <button type="button" id="btn-save" class="doc-toolbar-btn doc-toolbar-btn-primary">
                Salva
            </button>
            <button type="button" id="btn-export" class="doc-toolbar-btn doc-toolbar-btn-success">
                Esporta Word
            </button>
            <button type="button" id="btn-aladino" class="doc-toolbar-btn doc-toolbar-btn-aladino" onclick="document.getElementById('aladino-modal').style.display='flex';">
                Carica dati Aladino
            </button>
        </div>
    </div>

    <!-- Modal Aladino -->
    <div id="aladino-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:12px; padding:30px; max-width:800px; width:90%; max-height:85vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px;">Carica dati Aladino - <?php echo fullname($user); ?></h3>
                <button type="button" onclick="document.getElementById('aladino-modal').style.display='none';"
                        style="background:none; border:none; font-size:24px; cursor:pointer; color:#666;">&times;</button>
            </div>

            <div id="aladino-upload" style="border:2px dashed #ccc; border-radius:8px; padding:40px; text-align:center; cursor:pointer; transition:all 0.2s;"
                 ondragover="event.preventDefault(); this.style.borderColor='#e67e22'; this.style.background='#fef5ec';"
                 ondragleave="this.style.borderColor='#ccc'; this.style.background='white';"
                 ondrop="event.preventDefault(); this.style.borderColor='#ccc'; this.style.background='white'; handleAladinoFile(event.dataTransfer.files[0]);"
                 onclick="document.getElementById('aladino-file-input').click();">
                <div style="font-size:40px; color:#e67e22; margin-bottom:10px;">&#128196;</div>
                <p style="font-size:14px; color:#666; margin:0;">Trascina il file Excel Aladino qui oppure <strong>clicca per selezionare</strong></p>
                <p style="font-size:12px; color:#999; margin-top:8px;">Formato: .xlsx / .xls</p>
                <input type="file" id="aladino-file-input" accept=".xlsx,.xls" style="display:none;"
                       onchange="if(this.files[0]) handleAladinoFile(this.files[0]);">
            </div>

            <div id="aladino-preview" style="display:none; margin-top:20px;">
                <h4 style="margin:0 0 12px 0; color:#27ae60;">Dati trovati per <?php echo fullname($user); ?></h4>
                <div id="aladino-preview-content"></div>
                <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
                    <button type="button" onclick="resetAladinoModal();"
                            style="padding:10px 20px; border:1px solid #ccc; border-radius:6px; background:white; cursor:pointer; font-size:14px;">Annulla</button>
                    <button type="button" id="btn-aladino-confirm"
                            style="padding:10px 20px; border:none; border-radius:6px; background:#e67e22; color:white; cursor:pointer; font-size:14px; font-weight:600;">Importa dati</button>
                </div>
            </div>

            <div id="aladino-result" style="display:none; margin-top:20px;"></div>
            <div id="aladino-error" style="display:none; margin-top:20px; padding:15px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px; color:#721c24;"></div>
        </div>
    </div>

    <form id="report-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="studentid" value="<?php echo $id; ?>">
        <input type="hidden" name="reportid" value="<?php echo $report ? $report->id : ''; ?>">

        <div class="doc-page">
            <!-- Document Header -->
            <div class="doc-header">
                <h1 class="doc-header-title">Rapporto finale d'attivita'</h1>
                <p class="doc-header-subtitle">
                    Il formulario dev'essere compilato al termine del provvedimento dalla persona responsabile,
                    sottoscritto dal partecipante ed inviato al consulente del personale URC (tramite MFT).
                </p>
            </div>

            <div id="save-alert" class="doc-alert"></div>

            <!-- Organizzatore -->
            <div class="doc-section">
                <h2 class="doc-section-title">Organizzatore</h2>
                <div class="doc-section-content">
                    <table class="doc-official-table">
                        <tr>
                            <td class="label-cell">Nome e misura:</td>
                            <td class="value-cell" colspan="3">Fondazione Terzo Millennio / Rilevamento delle competenze del settore industriale</td>
                        </tr>
                        <tr>
                            <td class="label-cell">Persona responsabile:</td>
                            <td class="value-cell"><?php echo fullname($coach); ?></td>
                            <td class="label-cell">Telefono / e-mail</td>
                            <td class="value-cell">091 945 01 38 / <?php echo s($coachEmail); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Partecipante -->
            <div class="doc-section">
                <h2 class="doc-section-title">Partecipante</h2>
                <div class="doc-section-content">
                    <table class="doc-official-table">
                        <tr>
                            <td class="label-cell">Cognome, nome</td>
                            <td class="value-cell" colspan="3"><?php echo s($user->lastname); ?>, <?php echo s($user->firstname); ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Indirizzo completo:</td>
                            <td class="value-cell" colspan="3"><?php
                                $address_parts = [];
                                if (!empty($student->address_street)) $address_parts[] = s($student->address_street);
                                $cap_city = '';
                                if (!empty($student->address_cap)) $cap_city .= s($student->address_cap);
                                if (!empty($student->address_city)) $cap_city .= (!empty($cap_city) ? ' ' : '') . s($student->address_city);
                                if (!empty($cap_city)) $address_parts[] = $cap_city;
                                echo implode(', ', $address_parts) ?: '-';
                            ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">No. AS:</td>
                            <td class="value-cell"><?php echo s($student->avs_number ?? '-'); ?></td>
                            <td class="label-cell">Data di nascita:</td>
                            <td class="value-cell"><?php echo $student->birthdate ? date('d.m.Y', $student->birthdate) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Consulente del personale:</td>
                            <td class="value-cell"><?php echo s($student->urc_consultant ?? '-'); ?></td>
                            <td class="label-cell">Ufficio regionale di collocamento:</td>
                            <td class="value-cell"><?php echo s($student->urc_office ?? '-'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Partecipazione al provvedimento -->
            <div class="doc-section">
                <h2 class="doc-section-title">Partecipazione al provvedimento</h2>
                <div class="doc-section-content">
                    <table class="doc-official-table">
                        <tr>
                            <td class="label-cell">Grado d'occupazione:</td>
                            <td class="value-cell"><?php echo ($student->occupation_grade ?? 100); ?>%</td>
                            <td class="label-cell">Giorni e orari di presenza individuali:</td>
                            <td class="value-cell">30 giorni / 08:30-16:30</td>
                        </tr>
                    </table>

                    <table class="doc-official-table">
                        <tr>
                            <td class="label-cell">Data di inizio:</td>
                            <td class="value-cell"><?php echo $student->date_start ? date('d.m.Y', $student->date_start) : '-'; ?></td>
                            <td class="label-cell">Data di fine prevista:</td>
                            <td class="value-cell"><?php echo $student->date_end_planned ? date('d.m.Y', $student->date_end_planned) : '-'; ?></td>
                            <td class="label-cell">Data di fine effettiva:</td>
                            <td class="value-cell"><?php echo $student->date_end_actual ? date('d.m.Y', $student->date_end_actual) : '-'; ?></td>
                            <td class="label-cell">Numero di giorni di partecipazione effettiva in misura:</td>
                            <td class="value-cell"><?php echo $participationDays; ?></td>
                        </tr>
                    </table>

                    <h3 style="font-size: 12pt; font-weight: 600; margin: 15px 0 10px; text-decoration: underline;">N&deg; giorni per tipo di assenza</h3>
                    <table class="doc-absence-table" style="font-size: 11pt;">
                        <tr>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>A =</strong>Giorni di vacanza (solo se gia' maturati)</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px; width:60px;"><?php echo $student->absence_a ?? 0; ?></td>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>F =</strong>Guadagno intermedio</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px; width:60px;"><?php echo $student->absence_f ?? 0; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>B =</strong>Malattia, gravidanza</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_b ?? 0; ?></td>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>G =</strong>Altre assenze giustificate</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_g ?? 0; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>C =</strong>Infortunio</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_c ?? 0; ?></td>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>H =</strong> Giorni festivi, vacanze aziendali</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_h ?? 0; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>D =</strong> Congedo maternita', congedo genitore, congedo assistenza</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_d ?? 0; ?></td>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>I =</strong>Assenze non giustificate</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_i ?? 0; ?></td>
                        </tr>
                        <tr>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>E =</strong>Servizio militare, civile, protezione civile</td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px;"><?php echo $student->absence_e ?? 0; ?></td>
                            <td style="text-align:left; border:1px solid #999; padding:6px 10px;"><strong>TOTALE GIORNI DI ASSENZA</strong></td>
                            <td style="text-align:center; border:1px solid #999; padding:6px 10px; font-weight:bold;"><?php echo $student->absence_total ?? 0; ?></td>
                        </tr>
                    </table>

                    <!-- Conferma SIP coaching -->
                    <table style="width:100%; border-collapse:collapse; margin-top:15px; font-size:12pt;">
                        <tr>
                            <td style="border:1px solid #999; padding:12px; width:70%;">
                                La PCI ha svolto il sostegno al collocamento individuale personalizzato nel settore industriale
                            </td>
                            <td style="border:1px solid #999; padding:12px; text-align:center; width:15%;">
                                <strong>Si'</strong><br>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="sip_consent" value="1"
                                           <?php echo (!empty($report->sip_consent) && $report->sip_consent == 1) ? 'checked' : ''; ?>
                                           style="width:18px; height:18px; margin-top:5px;">
                                </label>
                            </td>
                            <td style="border:1px solid #999; padding:12px; text-align:center; width:15%;">
                                <strong>No</strong><br>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="sip_consent" value="0"
                                           <?php echo (isset($report->sip_consent) && $report->sip_consent == 0) ? 'checked' : ''; ?>
                                           style="width:18px; height:18px; margin-top:5px;">
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 1. Situazione iniziale -->
            <div class="doc-section">
                <h2 class="doc-section-title">1. Situazione iniziale</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Qual'e' la situazione iniziale della PCI?</div>
                        <p class="doc-subsection-hint">
                            Sintesi situazione iniziale e obiettivi, inserire una storia della carriera professionale e formativa della PCI.
                        </p>
                        <textarea name="initial_situation" class="doc-field-textarea" rows="6"
                                  placeholder="Sintesi situazione iniziale e obiettivi, inserire una storia della carriera professionale e formativa della PCI."><?php echo s($report->initial_situation ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Quale/Quali sono il/i settore/i di riferimento su cui viene effettuato il rilevamento?</div>
                        <p class="doc-subsection-hint">
                            Es.: Generico, meccanica, automazione, logistica, elettrico ecc...
                        </p>
                        <textarea name="initial_situation_sector" class="doc-field-textarea" rows="3"
                                  placeholder="Es.: Generico, meccanica, automazione, logistica, elettrico ecc. Indicare eventuali settori in cui si svolge un rilevamento approfondito teorico o pratico."><?php echo s($report->initial_situation_sector ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 2. Situazione della persona in cerca d'impiego al termine della misura -->
            <div class="doc-section">
                <h2 class="doc-section-title">2. Situazione della persona in cerca d'impiego al termine della misura</h2>
                <div class="doc-section-content">

                    <!-- Tabella reinserimento -->
                    <?php $reinsertion = $report->reinsertion_assessment ?? ''; ?>
                    <table class="doc-official-table" style="margin-bottom: 20px;">
                        <tr>
                            <td style="border: 1px solid #999; padding: 12px; font-size: 13pt;">
                                Competenze/esperienze della PCI che fanno presupporre <strong>un reinserimento a breve termine</strong> nel settore industriale
                            </td>
                            <td style="border: 1px solid #999; padding: 12px; text-align: center; width: 60px; cursor: pointer;" class="reinsertion-cell <?php echo ($reinsertion === 'breve_termine') ? 'selected' : ''; ?>" onclick="selectReinsertion('breve_termine', this)">
                                <input type="radio" name="reinsertion_assessment" value="breve_termine" style="display:none;" <?php echo ($reinsertion === 'breve_termine') ? 'checked' : ''; ?>>
                                <span style="font-size: 16pt; font-weight: bold;"><?php echo ($reinsertion === 'breve_termine') ? 'X' : ''; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #999; padding: 12px; font-size: 13pt;">
                                Competenze/esperienze della PCI che fanno presupporre un <strong>reinserimento a medio termine</strong> nel settore industriale
                            </td>
                            <td style="border: 1px solid #999; padding: 12px; text-align: center; width: 60px; cursor: pointer;" class="reinsertion-cell <?php echo ($reinsertion === 'medio_termine') ? 'selected' : ''; ?>" onclick="selectReinsertion('medio_termine', this)">
                                <input type="radio" name="reinsertion_assessment" value="medio_termine" style="display:none;" <?php echo ($reinsertion === 'medio_termine') ? 'checked' : ''; ?>>
                                <span style="font-size: 16pt; font-weight: bold;"><?php echo ($reinsertion === 'medio_termine') ? 'X' : ''; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #999; padding: 12px; font-size: 13pt;">
                                Competenze/esperienze della PCI che <strong>non fanno presupporre un reinserimento</strong> nel settore industriale
                            </td>
                            <td style="border: 1px solid #999; padding: 12px; text-align: center; width: 60px; cursor: pointer;" class="reinsertion-cell <?php echo ($reinsertion === 'no_reinserimento') ? 'selected' : ''; ?>" onclick="selectReinsertion('no_reinserimento', this)">
                                <input type="radio" name="reinsertion_assessment" value="no_reinserimento" style="display:none;" <?php echo ($reinsertion === 'no_reinserimento') ? 'checked' : ''; ?>>
                                <span style="font-size: 16pt; font-weight: bold;"><?php echo ($reinsertion === 'no_reinserimento') ? 'X' : ''; ?></span>
                            </td>
                        </tr>
                    </table>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Valutazione delle competenze del settore di riferimento:</div>
                        <p class="doc-subsection-hint">
                            (sulla base dei documenti redatti durante il percorso indicare quali competenze tecniche sono state rilevate, inserire qui anche se sono stati fatti anche stage che hanno permesso di rilevare/confermare competenze pratiche)
                        </p>
                        <textarea name="sector_competency_text" class="doc-field-textarea" rows="5"
                                  placeholder="Sulla base dei documenti redatti durante il percorso indicare quali competenze tecniche sono state rilevate, inserire qui anche se sono stati fatti stage che hanno permesso di rilevare/confermare competenze pratiche."><?php echo s($report->sector_competency_text ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Possibili settori e ambiti:</div>
                        <textarea name="possible_sectors" class="doc-field-textarea" rows="3"
                                  placeholder="Indicare possibili settori e ambiti professionali..."><?php echo s($report->possible_sectors ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Sintesi conclusiva:</div>
                        <textarea name="final_summary" class="doc-field-textarea" rows="4"
                                  placeholder="Sintesi conclusiva della valutazione..."><?php echo s($report->final_summary ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 3. Verifica delle competenze del partecipante -->
            <div class="doc-section">
                <h2 class="doc-section-title">3. Verifica delle competenze del partecipante</h2>
                <div class="doc-section-content">
                    <?php
                    // Scale for all competency tables.
                    $scaleLabels = ['Molto buone', 'Buone', 'Sufficienti', 'Insufficienti', 'N.V.'];
                    $scaleValues = ['molto_buone', 'buone', 'sufficienti', 'insufficienti', 'nv'];

                    $competencies = [
                        'personal' => [
                            'title' => '3.1. Competenze personali',
                            'items' => ['Impegno, motivazione', 'Iniziativa personale', 'Autonomia', 'Puntualita\'', 'Modo di presentarsi'],
                            'obs_field' => 'obs_personal'
                        ],
                        'social' => [
                            'title' => '3.2. Competenze sociali',
                            'items' => ['Capacita\' di comunicazione', 'Capacita\' di comprensione'],
                            'obs_field' => 'obs_social'
                        ],
                        'methodological' => [
                            'title' => '3.3. Competenze metodologiche',
                            'items' => ['Ritmo di lavoro', 'Capacita\' di apprendimento', 'Capacita\' di risoluzione dei problemi', 'Organizzazione del lavoro e ordine', 'Cura e precisione'],
                            'obs_field' => 'obs_methodological'
                        ],
                        'tic' => [
                            'title' => '3.4. Competenze TIC',
                            'items' => ['Conoscenze PC (Windows, Internet, ecc.) e della e-mail per comunicare e inviare documenti in allegato (scansionare, salvare, inviare documenti)'],
                            'obs_field' => 'obs_tic'
                        ],
                    ];

                    foreach ($competencies as $type => $data):
                        $saved = [];
                        $fieldname = $type . '_competencies';
                        if (isset($report->$fieldname)) {
                            $saved = json_decode($report->$fieldname, true) ?: [];
                        }
                    ?>
                    <div class="doc-subsection">
                        <div class="doc-subsection-title"><?php echo $data['title']; ?></div>
                        <table class="doc-competency-table">
                            <tr>
                                <th style="width: 40%;">Competenza</th>
                                <?php foreach ($scaleLabels as $sl): ?>
                                <th style="width: 12%; text-align: center;"><?php echo $sl; ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <?php foreach ($data['items'] as $idx => $item): ?>
                            <tr>
                                <td><?php echo $item; ?></td>
                                <?php foreach ($scaleValues as $si => $sv): ?>
                                <td class="rating-cell doc-rating-cell <?php echo (isset($saved[$idx]) && $saved[$idx] === $sv) ? 'selected' : ''; ?>">
                                    <input type="radio" name="<?php echo $type; ?>_<?php echo $idx; ?>"
                                           id="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $sv; ?>"
                                           value="<?php echo $sv; ?>"
                                           <?php echo (isset($saved[$idx]) && $saved[$idx] === $sv) ? 'checked' : ''; ?>>
                                    <label for="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $sv; ?>"><?php echo $scaleLabels[$si]; ?></label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <p style="margin: 8px 0 4px 0; font-weight: 600;">Osservazioni:</p>
                        <textarea name="<?php echo $data['obs_field']; ?>" class="doc-field-textarea" rows="2"
                                  placeholder="Osservazioni..."><?php echo s($report->{$data['obs_field']} ?? ''); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4. Valutazione dell'attivita' di ricerca impiego -->
            <div class="doc-section">
                <h2 class="doc-section-title">4. Valutazione dell'attivita' di ricerca impiego</h2>
                <div class="doc-section-content">

                    <!-- 4.1 Dossier -->
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">4.1. E' stato allestito il dossier completo di candidatura?</div>
                        <div class="doc-yesno">
                            <label class="doc-yesno-option">
                                <input type="radio" name="dossier_complete" value="1"
                                       <?php echo (!empty($report->dossier_complete)) ? 'checked' : ''; ?>>
                                <span><strong>Si'</strong></span>
                            </label>
                            <label class="doc-yesno-option">
                                <input type="radio" name="dossier_complete" value="0"
                                       <?php echo (isset($report->dossier_complete) && $report->dossier_complete == 0) ? 'checked' : ''; ?>>
                                <span><strong>No</strong></span>
                            </label>
                        </div>
                    </div>

                    <!-- Competenze ricerca impiego -->
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Competenze ricerca impiego</div>
                        <?php
                        $searchItems = [
                            'Conoscenza del mercato del lavoro nei settori ricercati',
                            'Valutazione realistica delle opportunita\' professionali',
                            'Capacita\' di interpretare / capire correttamente le offerte d\'impiego / il profilo ricercato',
                            'Capacita\' di presentarsi',
                            'Capacita\' di usare i canali adeguati per cercare lavoro'
                        ];
                        $savedSearch = [];
                        if (isset($report->search_competencies)) {
                            $savedSearch = json_decode($report->search_competencies, true) ?: [];
                        }
                        ?>
                        <table class="doc-competency-table">
                            <tr>
                                <th style="width: 40%;">Competenza</th>
                                <?php foreach ($scaleLabels as $sl): ?>
                                <th style="width: 12%; text-align: center;"><?php echo $sl; ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <?php foreach ($searchItems as $idx => $item): ?>
                            <tr>
                                <td><?php echo $item; ?></td>
                                <?php foreach ($scaleValues as $si => $sv): ?>
                                <td class="rating-cell doc-rating-cell <?php echo (isset($savedSearch[$idx]) && $savedSearch[$idx] === $sv) ? 'selected' : ''; ?>">
                                    <input type="radio" name="search_<?php echo $idx; ?>"
                                           id="search_<?php echo $idx; ?>_<?php echo $sv; ?>"
                                           value="<?php echo $sv; ?>"
                                           <?php echo (isset($savedSearch[$idx]) && $savedSearch[$idx] === $sv) ? 'checked' : ''; ?>>
                                    <label for="search_<?php echo $idx; ?>_<?php echo $sv; ?>"><?php echo $scaleLabels[$si]; ?></label>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- 4.2 Canali utilizzati -->
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">4.2. Canali utilizzati per la ricerca impiego (scegliere)</div>
                        <?php
                        $channels = [
                            'Annunci su quotidiani o riviste',
                            'Annunci su siti web specializzati o aziendali',
                            'Concorsi Foglio Ufficiale',
                            'Personalmente',
                            'Contatto telefonico',
                            'Rete di conoscenze personali e professionali',
                            'Lettere di autocandidature cartacee',
                            'Autocandidatura online (siti, e-mail)',
                            'URC',
                            'Agenzie di collocamento'
                        ];
                        $savedChannels = [];
                        if (isset($report->search_channels)) {
                            $savedChannels = json_decode($report->search_channels, true) ?: [];
                        }
                        ?>
                        <div class="doc-checkbox-group">
                            <?php foreach ($channels as $idx => $channel): ?>
                            <label class="doc-checkbox-item">
                                <input type="checkbox" name="channel_<?php echo $idx; ?>" value="<?php echo s($channel); ?>"
                                       <?php echo in_array($channel, $savedChannels) ? 'checked' : ''; ?>>
                                <span><?php echo s($channel); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin: 8px 0 4px 0; font-weight: 600;">Osservazioni:</p>
                        <textarea name="obs_search_channels" class="doc-field-textarea" rows="2"
                                  placeholder="Osservazioni sui canali di ricerca..."><?php echo s($report->obs_search_channels ?? ''); ?></textarea>
                    </div>

                    <!-- 4.3 Valutazione complessiva -->
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">4.3. Valutazione complessiva della capacita' di ricerca d'impiego</div>
                        <div class="doc-rating-scale">
                            <?php foreach ($scaleValues as $si => $sv): ?>
                            <div class="doc-rating-option">
                                <input type="radio" name="search_overall" id="search_overall_<?php echo $sv; ?>"
                                       value="<?php echo $sv; ?>"
                                       <?php echo (isset($report->search_overall) && $report->search_overall === $sv) ? 'checked' : ''; ?>>
                                <label for="search_overall_<?php echo $sv; ?>">
                                    <div class="doc-rating-label"><?php echo $scaleLabels[$si]; ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin: 8px 0 4px 0; font-weight: 600;">Osservazioni:</p>
                        <textarea name="obs_search_evaluation" class="doc-field-textarea" rows="3"
                                  placeholder="Osservazioni sulla valutazione complessiva..."><?php echo s($report->obs_search_evaluation ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 5. Riepilogo colloqui svolti durante la misura -->
            <div class="doc-section">
                <h2 class="doc-section-title">5. Riepilogo colloqui svolti durante la misura</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">5.1. Colloqui di lavoro sostenuti dalla PCI durante la partecipazione al PML</div>

                        <table class="doc-official-table" style="margin-bottom: 15px;">
                            <tr>
                                <td class="label-cell">Numero di colloqui effettuati:</td>
                                <td class="value-cell">
                                    <input type="number" name="interviews_count" class="doc-field" style="width: 100px;"
                                           min="0" value="<?php echo $report->interviews_count ?? ($student->interviews ?? 0); ?>">
                                </td>
                            </tr>
                        </table>

                        <p style="font-weight: 600; margin-bottom: 8px;">Presso quali datori di lavoro e quando?</p>
                        <textarea name="interviews_employers" class="doc-field-textarea" rows="4"
                                  placeholder="Indicare i datori di lavoro e le date dei colloqui..."><?php echo s($report->interviews_employers ?? ''); ?></textarea>

                        <p style="font-weight: 600; margin: 12px 0 8px 0;">Osservazioni:</p>
                        <textarea name="obs_interviews" class="doc-field-textarea" rows="3"
                                  placeholder="Osservazioni sui colloqui..."><?php echo s($report->obs_interviews ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 6. Esito dell'attivita' di ricerca impiego -->
            <div class="doc-section">
                <h2 class="doc-section-title">6. Esito dell'attivita' di ricerca impiego</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Nel periodo in cui la PCI ha partecipato alla misura, al piu' tardi entro la conclusione/interruzione della stessa, e' stata assunta da un'azienda?</div>
                        <div class="doc-yesno">
                            <label class="doc-yesno-option">
                                <input type="radio" name="hired" value="1" onchange="toggleHiredDetails()"
                                       <?php echo (!empty($report->hired)) ? 'checked' : ''; ?>>
                                <span><strong>Si'</strong></span>
                            </label>
                            <label class="doc-yesno-option">
                                <input type="radio" name="hired" value="0" onchange="toggleHiredDetails()"
                                       <?php echo (isset($report->hired) && $report->hired == 0) ? 'checked' : ''; ?>>
                                <span><strong>No</strong></span>
                            </label>
                        </div>

                        <div id="hired-details" class="doc-hired-details <?php echo (!empty($report->hired)) ? 'visible' : ''; ?>">
                            <p style="font-weight: 600; margin-bottom: 12px;">Se si', presso quale datore di lavoro, in quale professione, a partire da quando e con quale forma contrattuale?</p>
                            <table class="doc-official-table">
                                <tr>
                                    <td class="label-cell">Azienda (ragione sociale):</td>
                                    <td class="value-cell">
                                        <input type="text" name="hired_details" class="doc-field" style="width: 100%;"
                                               placeholder="Ragione sociale dell'azienda..."
                                               value="<?php echo s($report->hired_details ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Professione; a partire dal:</td>
                                    <td class="value-cell">
                                        <input type="text" name="hired_profession" class="doc-field" style="width: 100%;"
                                               placeholder="Es.: Meccanico; 01.05.2026"
                                               value="<?php echo s($report->hired_profession ?? ''); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">
                                        Forma contrattuale:<br>
                                        <span style="font-size: 11pt; font-style: italic; font-weight: normal;">contratto a tempo indeterminato, a tempo determinato o assunzione ad ore o su chiamata</span>
                                    </td>
                                    <td class="value-cell">
                                        <input type="text" name="hired_contract" class="doc-field" style="width: 100%;"
                                               placeholder="Es.: Contratto a tempo indeterminato"
                                               value="<?php echo s($report->hired_contract ?? ''); ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Eventuali allegati -->
            <div class="doc-section" style="margin-top: 20px;">
                <h2 class="doc-section-title">Eventuali allegati inviati al CP URC</h2>
                <div class="doc-section-content">
                    <textarea name="allegati" class="doc-field-textarea" rows="3"
                              placeholder="Indicare quali allegati vengono inviati al consulente del personale URC..."><?php echo s($report->allegati ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Firme -->
            <div class="doc-signatures">
                <p style="font-size: 13pt;">
                    <strong>Luogo e data:</strong> Taverne, <?php echo date('d.m.Y'); ?>
                </p>

                <div class="doc-signatures-row">
                    <div class="doc-signature-block">
                        <div class="doc-signature-line"></div>
                        <div class="doc-signature-label">
                            <strong>L'organizzatore:</strong><br>
                            Fondazione Terzo Millennio<br>
                            <?php echo fullname($coach); ?>
                        </div>
                    </div>
                    <div class="doc-signature-block">
                        <div class="doc-signature-line"></div>
                        <div class="doc-signature-label">
                            <strong>Il partecipante:</strong><br>
                            <?php echo fullname($user); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end doc-page -->
    </form>
</div><!-- end doc-container -->

<script>
// Keep Moodle session alive every 5 minutes while working on the report.
setInterval(function() {
    fetch('<?php echo $CFG->wwwroot; ?>/lib/ajax/service.php?sesskey=<?php echo sesskey(); ?>&info=core_session_touch', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify([{index: 0, methodname: 'core_session_touch', args: {}}])
    }).catch(function() {});
}, 300000);

function selectReinsertion(value, cell) {
    // Deselect all reinsertion cells.
    document.querySelectorAll('.reinsertion-cell').forEach(function(c) {
        c.classList.remove('selected');
        c.querySelector('span').textContent = '';
        c.querySelector('input').checked = false;
    });
    // Select clicked cell.
    cell.classList.add('selected');
    cell.querySelector('span').textContent = 'X';
    cell.querySelector('input').checked = true;
}

function toggleHiredDetails() {
    var hiredRadio = document.querySelector('input[name="hired"]:checked');
    var hired = hiredRadio ? hiredRadio.value === '1' : false;
    document.getElementById('hired-details').classList.toggle('visible', hired);
}

// Handle rating cell selection styling.
document.querySelectorAll('.doc-rating-cell input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Deselect siblings in same row.
        var row = this.closest('tr');
        row.querySelectorAll('.doc-rating-cell').forEach(function(cell) {
            cell.classList.remove('selected');
        });
        // Select this cell.
        this.closest('.doc-rating-cell').classList.add('selected');
    });
});

document.getElementById('btn-save').addEventListener('click', async function() {
    var form = document.getElementById('report-form');
    var formData = new FormData(form);
    formData.append('action', 'save');

    // Collect competency ratings (section 3).
    var types = ['personal', 'social', 'methodological', 'tic'];
    types.forEach(function(type) {
        var ratings = [];
        var idx = 0;
        while (true) {
            var selected = form.querySelector('input[name="' + type + '_' + idx + '"]:checked');
            if (!selected && idx > 0) break;
            ratings.push(selected ? selected.value : '');
            idx++;
            if (idx > 10) break;
        }
        formData.append(type + '_competencies', JSON.stringify(ratings));
    });

    // Collect search competency ratings (section 4).
    var searchRatings = [];
    for (var si = 0; si < 5; si++) {
        var sel = form.querySelector('input[name="search_' + si + '"]:checked');
        searchRatings.push(sel ? sel.value : '');
    }
    formData.append('search_competencies', JSON.stringify(searchRatings));

    // Collect search channels (section 4.2).
    var channels = [];
    document.querySelectorAll('input[name^="channel_"]:checked').forEach(function(cb) {
        channels.push(cb.value);
    });
    formData.append('search_channels', JSON.stringify(channels));

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = 'Salvataggio in corso...';
    btn.style.background = '#f39c12';

    var alertEl = document.getElementById('save-alert');
    alertEl.className = 'doc-alert';
    alertEl.style.display = 'none';

    try {
        var response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_save_report.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Server error: ' + response.status + ' ' + response.statusText);
        }

        var data = await response.json();

        if (data.success) {
            // Update report ID.
            if (data.reportid) {
                document.querySelector('input[name="reportid"]').value = data.reportid;
            }

            // Flash all sections green.
            document.querySelectorAll('.doc-section').forEach(function(section) {
                section.style.transition = 'background-color 0.3s';
                section.style.backgroundColor = '#d4edda';
            });
            setTimeout(function() {
                document.querySelectorAll('.doc-section').forEach(function(section) {
                    section.style.backgroundColor = '';
                });
            }, 1500);

            // Show success banner.
            alertEl.style.display = 'block';
            alertEl.className = 'doc-alert success';
            alertEl.innerHTML = '<strong>Salvataggio completato!</strong> Tutti i dati sono stati salvati correttamente. (' + new Date().toLocaleTimeString('it-CH') + ')';

            // Change save button to green temporarily.
            btn.style.background = '#27ae60';
            btn.innerHTML = 'Salvato!';
            setTimeout(function() {
                btn.style.background = '#3498db';
                btn.innerHTML = 'Salva';
            }, 3000);

            // Hide alert after 10 seconds.
            setTimeout(function() { alertEl.style.display = 'none'; }, 10000);

        } else {
            // Show error.
            alertEl.style.display = 'block';
            alertEl.className = 'doc-alert error';
            alertEl.innerHTML = '<strong>ERRORE nel salvataggio!</strong> ' + (data.message || 'Errore sconosciuto') + '<br>Riprovare. Se il problema persiste, ricaricare la pagina (F5).';
            btn.style.background = '#e74c3c';
            btn.innerHTML = 'Errore - Riprova';
            setTimeout(function() {
                btn.style.background = '#3498db';
                btn.innerHTML = 'Salva';
            }, 5000);
        }

    } catch (error) {
        console.error('Save error:', error);
        alertEl.style.display = 'block';
        alertEl.className = 'doc-alert error';
        alertEl.innerHTML = '<strong>ERRORE di connessione!</strong> Il salvataggio non e\' andato a buon fine.<br>' +
            'Possibile causa: sessione scaduta. <strong>Ricaricare la pagina (F5)</strong> e riprovare.<br>' +
            '<small>Dettaglio: ' + error.message + '</small>';
        btn.style.background = '#e74c3c';
        btn.innerHTML = 'Errore - Ricarica pagina';
    }

    btn.disabled = false;
});

document.getElementById('btn-export').addEventListener('click', function() {
    var reportId = document.querySelector('input[name="reportid"]').value;

    if (!reportId) {
        if (confirm('Per esportare in Word e\' necessario prima salvare il report. Salvare ora?')) {
            document.getElementById('btn-save').click();
            setTimeout(function() {
                var newReportId = document.querySelector('input[name="reportid"]').value;
                if (newReportId) {
                    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/export_word.php?id=<?php echo $id; ?>';
                }
            }, 1500);
        }
    } else {
        window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/export_word.php?id=<?php echo $id; ?>';
    }
});

// === ALADINO IMPORT ===
var aladinoFileData = null;

function handleAladinoFile(file) {
    if (!file) return;
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx' && ext !== 'xls') {
        showAladinoError('Formato non supportato. Usare .xlsx o .xls');
        return;
    }

    aladinoFileData = file;
    document.getElementById('aladino-upload').innerHTML =
        '<div style="color:#27ae60; font-size:16px; font-weight:600;">' + file.name + '</div>' +
        '<p style="font-size:13px; color:#666; margin-top:5px;">Caricamento in corso...</p>';
    document.getElementById('aladino-error').style.display = 'none';

    // Send preview request.
    var formData = new FormData();
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    formData.append('studentid', '<?php echo $id; ?>');
    formData.append('action', 'preview');
    formData.append('excelfile', file);

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_import_aladino.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            showAladinoPreview(result.data);
        } else {
            showAladinoError(result.message);
        }
    })
    .catch(function(err) {
        showAladinoError('Errore di connessione: ' + err.message);
    });
}

function showAladinoPreview(data) {
    var labels = {
        'absence_x': 'Presenze X', 'absence_o': 'Presenze O',
        'absence_a': 'A - Vacanze', 'absence_b': 'B - Malattia/gravidanza',
        'absence_c': 'C - Infortunio', 'absence_d': 'D - Congedo maternita\'',
        'absence_e': 'E - Servizio militare/civile', 'absence_f': 'F - Guadagno intermedio',
        'absence_g': 'G - Altre giustificate', 'absence_h': 'H - Festivi',
        'absence_i': 'I - Non giustificate', 'absence_total': 'Totale giorni assenza',
        'effective_days': 'Giorni partecipazione effettiva',
        'interviews': 'Colloqui di assunzione',
        'interview_date': 'Data colloquio', 'interview_company': 'Ditta colloquio',
        'interviews_detail': 'Dettaglio colloqui (salvato nel report)',
        'stages_count': 'Stage svolti',
        'stage_days': 'Giorni stage', 'date_start': 'Inizio', 'date_end_planned': 'Fine prevista',
        'date_end_actual': 'Fine effettiva', 'last_profession': 'Ultima professione',
        'urc_office': 'Ufficio URC', 'urc_consultant': 'Consulente URC',
        'occupation_grade': 'Grado occupazione', 'status_text': 'Stato',
        'avs_number': 'Nr. AVS', 'address_street': 'Via', 'address_cap': 'CAP',
        'address_city': 'Localita\'', 'birthdate': 'Data di nascita',
        'personal_number': 'Numero personale', 'exit_reason': 'Motivo',
        'observations': 'Osservazioni', 'priority': 'Priorita\''
    };

    // Group fields for display.
    var groups = {
        'Assenze e presenze': ['absence_x', 'absence_o', 'absence_a', 'absence_b', 'absence_c',
            'absence_d', 'absence_e', 'absence_f', 'absence_g', 'absence_h', 'absence_i',
            'absence_total', 'effective_days'],
        'Percorso': ['date_start', 'date_end_planned', 'date_end_actual', 'occupation_grade',
            'status_text', 'exit_reason', 'last_profession'],
        'Colloqui e stage': ['interviews', 'interview_date', 'interview_company', 'interviews_detail', 'stages_count', 'stage_days'],
        'URC': ['urc_office', 'urc_consultant', 'personal_number'],
    };

    var html = '';
    for (var groupName in groups) {
        html += '<h5 style="margin:15px 0 8px; font-size:13px; color:#555; border-bottom:1px solid #eee; padding-bottom:4px;">' + groupName + '</h5>';
        html += '<table style="width:100%; font-size:13px; border-collapse:collapse;">';
        groups[groupName].forEach(function(key) {
            var val = data[key];
            if (val !== undefined && val !== null && val !== '') {
                var label = labels[key] || key;
                var highlight = (key === 'effective_days') ? ' style="background:#e8f5e9; font-weight:600;"' : '';
                html += '<tr' + highlight + '><td style="padding:4px 8px; border-bottom:1px solid #f0f0f0; width:45%; color:#333;">' +
                    label + '</td><td style="padding:4px 8px; border-bottom:1px solid #f0f0f0; font-weight:500;">' +
                    val + '</td></tr>';
            }
        });
        html += '</table>';
    }

    document.getElementById('aladino-preview-content').innerHTML = html;
    document.getElementById('aladino-preview').style.display = 'block';

    // Bind confirm button.
    document.getElementById('btn-aladino-confirm').onclick = function() {
        confirmAladinoImport();
    };
}

function confirmAladinoImport() {
    if (!aladinoFileData) return;

    var btn = document.getElementById('btn-aladino-confirm');
    btn.disabled = true;
    btn.textContent = 'Importazione...';

    var formData = new FormData();
    formData.append('sesskey', '<?php echo sesskey(); ?>');
    formData.append('studentid', '<?php echo $id; ?>');
    formData.append('action', 'import');
    formData.append('excelfile', aladinoFileData);

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_import_aladino.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            document.getElementById('aladino-preview').style.display = 'none';
            document.getElementById('aladino-result').style.display = 'block';
            document.getElementById('aladino-result').innerHTML =
                '<div style="padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:6px; color:#155724;">' +
                '<strong>Importazione completata!</strong> I dati sono stati aggiornati. La pagina si ricarichera\'.' +
                '</div>';
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            showAladinoError(result.message);
            btn.disabled = false;
            btn.textContent = 'Importa dati';
        }
    })
    .catch(function(err) {
        showAladinoError('Errore: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Importa dati';
    });
}

function showAladinoError(msg) {
    var el = document.getElementById('aladino-error');
    el.style.display = 'block';
    el.textContent = msg;
    document.getElementById('aladino-preview').style.display = 'none';
}

function resetAladinoModal() {
    aladinoFileData = null;
    document.getElementById('aladino-upload').innerHTML =
        '<div style="font-size:40px; color:#e67e22; margin-bottom:10px;">&#128196;</div>' +
        '<p style="font-size:14px; color:#666; margin:0;">Trascina il file Excel Aladino qui oppure <strong>clicca per selezionare</strong></p>' +
        '<p style="font-size:12px; color:#999; margin-top:8px;">Formato: .xlsx / .xls</p>' +
        '<input type="file" id="aladino-file-input" accept=".xlsx,.xls" style="display:none;" onchange="if(this.files[0]) handleAladinoFile(this.files[0]);">';
    document.getElementById('aladino-preview').style.display = 'none';
    document.getElementById('aladino-result').style.display = 'none';
    document.getElementById('aladino-error').style.display = 'none';
}
</script>

<?php
echo $OUTPUT->footer();
