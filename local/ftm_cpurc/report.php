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

// Get Moodle user.
$user = $DB->get_record('user', ['id' => $student->userid]);

// Get existing report if any.
$report = \local_ftm_cpurc\cpurc_manager::get_report($id);

// Get current user as coach.
$coach = $USER;
$coachInitials = strtoupper(substr($coach->firstname, 0, 1) . substr($coach->lastname, 0, 1));
$coachInitialLower = strtolower(substr($coach->firstname, 0, 1) . substr($coach->lastname, 0, 1));

// Calculate participation days.
$participationDays = 0;
if (!empty($student->date_start) && !empty($student->date_end_actual)) {
    $start = new DateTime();
    $start->setTimestamp($student->date_start);
    $end = new DateTime();
    $end->setTimestamp($student->date_end_actual);
    $interval = $start->diff($end);
    // Count weekdays only.
    $days = 0;
    $current = clone $start;
    while ($current <= $end) {
        $dow = (int)$current->format('N');
        if ($dow <= 5) {
            $days++;
        }
        $current->modify('+1 day');
    }
    $participationDays = $days - ($student->absence_total ?? 0);
} elseif (!empty($student->date_start) && !empty($student->date_end_planned)) {
    $start = new DateTime();
    $start->setTimestamp($student->date_start);
    $end = new DateTime();
    $end->setTimestamp($student->date_end_planned);
    $days = 0;
    $current = clone $start;
    while ($current <= $end) {
        $dow = (int)$current->format('N');
        if ($dow <= 5) {
            $days++;
        }
        $current->modify('+1 day');
    }
    $participationDays = $days - ($student->absence_total ?? 0);
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
    background: #fffef5;
    padding: 4px 8px;
    font-family: inherit;
    font-size: inherit;
    transition: all 0.2s;
}

.doc-field:focus {
    outline: none;
    background: #fff9c4;
    border-bottom: 2px solid #2c3e50;
}

.doc-field-textarea {
    width: 100%;
    min-height: 120px;
    border: 2px solid #ccc;
    background: #fffef5;
    padding: 15px;
    font-family: inherit;
    font-size: 14pt;
    resize: vertical;
    border-radius: 4px;
}

.doc-field-textarea:focus {
    outline: none;
    background: #fff9c4;
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
    font-size: 9pt;
    color: #666;
    font-style: italic;
    margin-bottom: 10px;
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
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    display: none;
}

.doc-alert.success {
    display: block;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.doc-alert.error {
    display: block;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
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
                            <td class="value-cell">091 945 01 38 / <?php echo $coachInitialLower; ?>@f3m.ch</td>
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

                    <h3 style="font-size: 12pt; font-weight: 600; margin: 15px 0 10px;">N&deg; giorni per tipo di assenza</h3>
                    <table class="doc-absence-table">
                        <tr>
                            <th>Vacanze</th>
                            <th>Malattia</th>
                            <th>Infortunio</th>
                            <th>Maternita'</th>
                            <th>Prot. civile</th>
                            <th>Altro giust.</th>
                            <th>Festivi</th>
                            <th><strong>Totale</strong></th>
                        </tr>
                        <tr>
                            <td><?php echo $student->absence_a ?? 0; ?></td>
                            <td><?php echo $student->absence_b ?? 0; ?></td>
                            <td><?php echo $student->absence_c ?? 0; ?></td>
                            <td><?php echo $student->absence_d ?? 0; ?></td>
                            <td><?php echo $student->absence_e ?? 0; ?></td>
                            <td><?php echo $student->absence_g ?? 0; ?></td>
                            <td><?php echo $student->absence_h ?? 0; ?></td>
                            <td><strong><?php echo $student->absence_total ?? 0; ?></strong></td>
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
                                  placeholder="Inserire la descrizione della situazione iniziale..."><?php echo s($report->initial_situation ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Quale/Quali sono il/i settore/i di riferimento su cui viene effettuato il rilevamento?</div>
                        <p class="doc-subsection-hint">
                            Es.: Generico, meccanica, automazione, logistica, elettrico ecc...
                        </p>
                        <textarea name="initial_situation_sector" class="doc-field-textarea" rows="3"
                                  placeholder="Indicare il/i settore/i di riferimento..."><?php echo s($report->initial_situation_sector ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 2. Situazione della persona in cerca d'impiego al termine della misura -->
            <div class="doc-section">
                <h2 class="doc-section-title">2. Situazione della persona in cerca d'impiego al termine della misura</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Valutazione delle competenze del settore di riferimento:</div>
                        <p class="doc-subsection-hint">
                            (sulla base dei documenti redatti durante il percorso indicare quali competenze tecniche sono state rilevate, inserire qui anche se sono stati fatti anche stage che hanno permesso di rilevare/confermare competenze pratiche)
                        </p>
                        <textarea name="sector_competency_text" class="doc-field-textarea" rows="5"
                                  placeholder="Descrivere le competenze tecniche rilevate..."><?php echo s($report->sector_competency_text ?? ''); ?></textarea>
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
                            <p style="font-weight: 600; margin-bottom: 8px;">Se si', presso quale datore di lavoro, in quale professione, a partire da quando e con quale forma contrattuale?</p>
                            <textarea name="hired_details" class="doc-field-textarea" rows="4"
                                      placeholder="Indicare datore di lavoro, professione, data inizio e forma contrattuale..."><?php echo s($report->hired_details ?? ''); ?></textarea>
                        </div>
                    </div>
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

    this.disabled = true;
    this.innerHTML = 'Salvataggio...';

    try {
        var response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_save_report.php', {
            method: 'POST',
            body: formData
        });

        var data = await response.json();
        var alertEl = document.getElementById('save-alert');

        if (data.success) {
            alertEl.className = 'doc-alert success';
            alertEl.textContent = data.message;
            if (data.reportid) {
                document.querySelector('input[name="reportid"]').value = data.reportid;
            }
        } else {
            alertEl.className = 'doc-alert error';
            alertEl.textContent = data.message;
        }

        setTimeout(function() { alertEl.className = 'doc-alert'; }, 5000);

    } catch (error) {
        console.error(error);
        var alertEl = document.getElementById('save-alert');
        alertEl.className = 'doc-alert error';
        alertEl.textContent = 'Errore di connessione';
    }

    this.disabled = false;
    this.innerHTML = 'Salva';
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
</script>

<?php
echo $OUTPUT->footer();
