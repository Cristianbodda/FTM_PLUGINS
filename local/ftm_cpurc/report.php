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

$id = required_param('id', PARAM_INT);

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

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/report.php', ['id' => $id]));
$PAGE->set_title('Rapporto Finale - ' . fullname($user));
$PAGE->set_heading('Rapporto Finale');
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
    text-transform: uppercase;
    letter-spacing: 1px;
}

.doc-header-subtitle {
    font-size: 11pt;
    color: #555;
    margin-top: 5px;
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

/* Info table */
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

/* Competency table */
.doc-competency-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.doc-competency-table th,
.doc-competency-table td {
    border: 1px solid #bbb;
    padding: 12px;
    text-align: left;
    font-size: 13pt;
}

.doc-competency-table th {
    background: #e8e8e8;
    font-weight: 600;
    font-size: 13pt;
}

.doc-competency-table .rating-cell {
    text-align: center;
    width: 40px;
}

/* Rating radio buttons */
.doc-rating {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.doc-rating input[type="radio"] {
    display: none;
}

.doc-rating label {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #999;
    background: white;
    cursor: pointer;
    font-size: 14pt;
    font-weight: 600;
    transition: all 0.2s;
    border-radius: 4px;
}

.doc-rating label:hover {
    background: #e3f2fd;
}

.doc-rating input[type="radio"]:checked + label {
    background: #2c3e50;
    color: white;
    border-color: #2c3e50;
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

.doc-rating-number {
    font-size: 32pt;
    font-weight: bold;
}

.doc-rating-label {
    font-size: 11pt;
    margin-top: 8px;
}

/* Checkbox styling */
.doc-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 10px 0;
}

.doc-checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    background: #f5f5f5;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13pt;
}

.doc-checkbox-item:hover {
    background: #e8e8e8;
}

.doc-checkbox-item input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
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

.doc-hired-details table {
    width: 100%;
}

.doc-hired-details td {
    padding: 5px 10px;
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

/* Print styles */
@media print {
    .doc-toolbar { display: none; }
    .doc-container { box-shadow: none; }
    .doc-field, .doc-field-textarea {
        background: transparent;
        border: none;
        border-bottom: 1px solid #000;
    }
}
</style>

<div class="doc-container">
    <!-- Toolbar -->
    <div class="doc-toolbar">
        <div class="doc-toolbar-title">
            Rapporto Finale - <?php echo fullname($user); ?>
        </div>
        <div class="doc-toolbar-buttons">
            <button type="button" class="doc-toolbar-btn doc-toolbar-btn-secondary" onclick="window.print();">
                🖨️ Stampa
            </button>
            <button type="button" id="btn-save" class="doc-toolbar-btn doc-toolbar-btn-primary">
                💾 Salva
            </button>
            <button type="button" id="btn-export" class="doc-toolbar-btn doc-toolbar-btn-success">
                📄 Esporta Word
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
                <h1 class="doc-header-title">Rapporto Finale</h1>
                <p class="doc-header-subtitle">Provvedimento cantonale d'integrazione (PCI)</p>
            </div>

            <div id="save-alert" class="doc-alert"></div>

            <!-- 1. Organizzatore -->
            <div class="doc-section">
                <h2 class="doc-section-title">1. Organizzatore</h2>
                <div class="doc-section-content">
                    <table class="doc-info-table">
                        <tr>
                            <td class="label">Organizzatore:</td>
                            <td class="value">Fondazione Terzo Millennio</td>
                        </tr>
                        <tr>
                            <td class="label">Coach responsabile:</td>
                            <td class="value"><?php echo fullname($coach); ?> (<?php echo $coachInitials; ?>)</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 2. Partecipante -->
            <div class="doc-section">
                <h2 class="doc-section-title">2. Partecipante</h2>
                <div class="doc-section-content">
                    <table class="doc-info-table">
                        <tr>
                            <td class="label">Nome e cognome:</td>
                            <td class="value"><?php echo fullname($user); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Data di nascita:</td>
                            <td class="value"><?php echo $student->birthdate ? date('d.m.Y', $student->birthdate) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Indirizzo:</td>
                            <td class="value"><?php echo s($student->address_street ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="label">CAP / Località:</td>
                            <td class="value"><?php echo s($student->address_cap ?? ''); ?> <?php echo s($student->address_city ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Nr. AVS:</td>
                            <td class="value"><?php echo s($student->avs_number ?? ''); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 3. Partecipazione -->
            <div class="doc-section">
                <h2 class="doc-section-title">3. Partecipazione</h2>
                <div class="doc-section-content">
                    <table class="doc-info-table">
                        <tr>
                            <td class="label">Data inizio:</td>
                            <td class="value"><?php echo $student->date_start ? date('d.m.Y', $student->date_start) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Data fine prevista:</td>
                            <td class="value"><?php echo $student->date_end_planned ? date('d.m.Y', $student->date_end_planned) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Data fine effettiva:</td>
                            <td class="value"><?php echo $student->date_end_actual ? date('d.m.Y', $student->date_end_actual) : '-'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Grado occupazione:</td>
                            <td class="value"><?php echo ($student->occupation_grade ?? 100); ?>%</td>
                        </tr>
                        <tr>
                            <td class="label">Ufficio URC:</td>
                            <td class="value"><?php echo s($student->urc_office ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Consulente URC:</td>
                            <td class="value"><?php echo s($student->urc_consultant ?? ''); ?></td>
                        </tr>
                    </table>

                    <h3 style="font-size: 10pt; font-weight: 600; margin: 15px 0 10px;">Assenze (in giorni)</h3>
                    <table class="doc-absence-table">
                        <tr>
                            <th>Vacanze</th>
                            <th>Malattia</th>
                            <th>Infortunio</th>
                            <th>Maternità</th>
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

            <!-- 4. Situazione iniziale -->
            <div class="doc-section">
                <h2 class="doc-section-title">4. Situazione iniziale</h2>
                <div class="doc-section-content">
                    <p style="font-size: 9pt; color: #666; margin-bottom: 10px;">
                        Descrivere la situazione iniziale della PCI: obiettivi, storia professionale, aspettative.
                    </p>
                    <textarea name="initial_situation" class="doc-field-textarea" rows="6"
                              placeholder="Inserire la descrizione della situazione iniziale..."><?php echo s($report->initial_situation ?? ''); ?></textarea>
                </div>
            </div>

            <!-- 5. Valutazione competenze settore -->
            <div class="doc-section">
                <h2 class="doc-section-title">5. Valutazione delle competenze del settore di riferimento
                    <?php if (!empty($student->sector_detected)): ?>
                        <span style="font-weight: normal; font-size: 10pt;">(<?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>)</span>
                    <?php endif; ?>
                </h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Valutazione complessiva</div>
                        <div class="doc-rating-scale">
                            <?php
                            $ratingLabels = ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Ottimo'];
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                            <div class="doc-rating-option">
                                <input type="radio" name="sector_competency_rating" id="sector_rating_<?php echo $i; ?>"
                                       value="<?php echo $i; ?>"
                                       <?php echo (isset($report->sector_competency_rating) && $report->sector_competency_rating == $i) ? 'checked' : ''; ?>>
                                <label for="sector_rating_<?php echo $i; ?>">
                                    <div class="doc-rating-number"><?php echo $i; ?></div>
                                    <div class="doc-rating-label"><?php echo $ratingLabels[$i-1]; ?></div>
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Osservazioni sulle competenze tecniche</div>
                        <textarea name="sector_competency_text" class="doc-field-textarea" rows="4"
                                  placeholder="Descrivere le competenze tecniche osservate..."><?php echo s($report->sector_competency_text ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Possibili settori e ambiti professionali</div>
                        <textarea name="possible_sectors" class="doc-field-textarea" rows="3"
                                  placeholder="Indicare possibili settori e ambiti professionali adatti al partecipante..."><?php echo s($report->possible_sectors ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Sintesi conclusiva</div>
                        <textarea name="final_summary" class="doc-field-textarea" rows="4"
                                  placeholder="Sintesi conclusiva della valutazione..."><?php echo s($report->final_summary ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 6. Competenze trasversali -->
            <div class="doc-section">
                <h2 class="doc-section-title">6. Competenze trasversali</h2>
                <div class="doc-section-content">
                    <?php
                    $competencies = [
                        'personal' => [
                            'title' => '6.1 Competenze personali',
                            'items' => ['Affidabilità', 'Puntualità', 'Autonomia', 'Iniziativa', 'Flessibilità'],
                            'obs_field' => 'obs_personal'
                        ],
                        'social' => [
                            'title' => '6.2 Competenze sociali',
                            'items' => ['Lavoro in team', 'Comunicazione', 'Rispetto regole', 'Gestione conflitti'],
                            'obs_field' => 'obs_social'
                        ],
                        'methodological' => [
                            'title' => '6.3 Competenze metodologiche',
                            'items' => ['Organizzazione', 'Problem solving', 'Gestione tempo', 'Apprendimento'],
                            'obs_field' => 'obs_methodological'
                        ],
                        'tic' => [
                            'title' => '6.4 Competenze TIC',
                            'items' => ['Competenze base PC', 'Email', 'Ricerca online', 'Software specifici'],
                            'obs_field' => null
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
                                <th style="width: 60%;">Competenza</th>
                                <th colspan="5" style="text-align: center;">Valutazione (1-5)</th>
                            </tr>
                            <?php foreach ($data['items'] as $idx => $item): ?>
                            <tr>
                                <td><?php echo s($item); ?></td>
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <td class="rating-cell">
                                    <div class="doc-rating">
                                        <input type="radio" name="<?php echo $type; ?>_<?php echo $idx; ?>"
                                               id="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $r; ?>"
                                               value="<?php echo $r; ?>"
                                               <?php echo (isset($saved[$idx]) && $saved[$idx] == $r) ? 'checked' : ''; ?>>
                                        <label for="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $r; ?>"><?php echo $r; ?></label>
                                    </div>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php if ($data['obs_field']): ?>
                        <textarea name="<?php echo $data['obs_field']; ?>" class="doc-field-textarea" rows="2"
                                  style="margin-top: 10px;"
                                  placeholder="Osservazioni..."><?php echo s($report->{$data['obs_field']} ?? ''); ?></textarea>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 7. Ricerca impiego -->
            <div class="doc-section">
                <h2 class="doc-section-title">7. Ricerca impiego</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">7.1 Dossier di candidatura</div>
                        <div class="doc-yesno">
                            <label class="doc-yesno-option">
                                <input type="radio" name="dossier_complete" value="1"
                                       <?php echo (!empty($report->dossier_complete)) ? 'checked' : ''; ?>>
                                <span>Completo</span>
                            </label>
                            <label class="doc-yesno-option">
                                <input type="radio" name="dossier_complete" value="0"
                                       <?php echo (empty($report->dossier_complete)) ? 'checked' : ''; ?>>
                                <span>Incompleto</span>
                            </label>
                        </div>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">7.2 Canali di ricerca utilizzati</div>
                        <?php
                        $channels = ['Portali online', 'Candidature spontanee', 'Rete personale', 'Agenzie interinali', 'Social media', 'Giornali'];
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
                        <textarea name="obs_search_channels" class="doc-field-textarea" rows="2"
                                  style="margin-top: 10px;"
                                  placeholder="Osservazioni sui canali di ricerca..."><?php echo s($report->obs_search_channels ?? ''); ?></textarea>
                    </div>

                    <div class="doc-subsection">
                        <div class="doc-subsection-title">7.3 Valutazione complessiva della capacità di ricerca d'impiego</div>
                        <div class="doc-rating-scale">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="doc-rating-option">
                                <input type="radio" name="search_evaluation" id="search_<?php echo $i; ?>"
                                       value="<?php echo $i; ?>"
                                       <?php echo (isset($report->search_evaluation) && $report->search_evaluation == $i) ? 'checked' : ''; ?>>
                                <label for="search_<?php echo $i; ?>">
                                    <div class="doc-rating-number"><?php echo $i; ?></div>
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <textarea name="obs_search_evaluation" class="doc-field-textarea" rows="3"
                                  placeholder="Valutazione complessiva della capacità di ricerca d'impiego..."><?php echo s($report->obs_search_evaluation ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 8. Colloqui -->
            <div class="doc-section">
                <h2 class="doc-section-title">8. Colloqui d'assunzione svolti</h2>
                <div class="doc-section-content">
                    <table class="doc-info-table">
                        <tr>
                            <td class="label">Numero colloqui:</td>
                            <td class="value"><?php echo $student->interviews ?? 0; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 9. Esito -->
            <div class="doc-section">
                <h2 class="doc-section-title">9. Esito</h2>
                <div class="doc-section-content">
                    <div class="doc-subsection">
                        <div class="doc-subsection-title">Il partecipante è stato assunto?</div>
                        <div class="doc-yesno">
                            <label class="doc-yesno-option">
                                <input type="radio" name="hired" value="1" onchange="toggleHiredDetails()"
                                       <?php echo (!empty($report->hired)) ? 'checked' : ''; ?>>
                                <span><strong>Sì</strong></span>
                            </label>
                            <label class="doc-yesno-option">
                                <input type="radio" name="hired" value="0" onchange="toggleHiredDetails()"
                                       <?php echo (empty($report->hired)) ? 'checked' : ''; ?>>
                                <span><strong>No</strong></span>
                            </label>
                        </div>

                        <div id="hired-details" class="doc-hired-details <?php echo (!empty($report->hired)) ? 'visible' : ''; ?>">
                            <table>
                                <tr>
                                    <td style="width: 150px;"><strong>Azienda:</strong></td>
                                    <td><input type="text" name="hired_company" class="doc-field" style="width: 100%;"
                                               value="<?php echo s($report->hired_company ?? ''); ?>"></td>
                                </tr>
                                <tr>
                                    <td><strong>Data assunzione:</strong></td>
                                    <td><input type="date" name="hired_date" class="doc-field"
                                               value="<?php echo !empty($report->hired_date) ? date('Y-m-d', $report->hired_date) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <td><strong>Percentuale:</strong></td>
                                    <td><input type="number" name="hired_percentage" class="doc-field" style="width: 80px;"
                                               min="0" max="100" value="<?php echo $report->hired_percentage ?? ''; ?>"> %</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end doc-page -->
    </form>
</div><!-- end doc-container -->

<script>
function toggleHiredDetails() {
    const hired = document.querySelector('input[name="hired"]:checked').value === '1';
    document.getElementById('hired-details').classList.toggle('visible', hired);
}

document.getElementById('btn-save').addEventListener('click', async function() {
    const form = document.getElementById('report-form');
    const formData = new FormData(form);
    formData.append('action', 'save');

    // Collect competency ratings
    const types = ['personal', 'social', 'methodological', 'tic'];
    types.forEach(type => {
        const ratings = [];
        let idx = 0;
        while (true) {
            const selected = form.querySelector(`input[name="${type}_${idx}"]:checked`);
            if (!selected && idx > 0) break;
            ratings.push(selected ? selected.value : '0');
            idx++;
            if (idx > 10) break;
        }
        formData.append(type + '_competencies', JSON.stringify(ratings));
    });

    // Collect search channels
    const channels = [];
    document.querySelectorAll('input[name^="channel_"]:checked').forEach(cb => {
        channels.push(cb.value);
    });
    formData.append('search_channels', JSON.stringify(channels));

    this.disabled = true;
    this.innerHTML = '⏳ Salvataggio...';

    try {
        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_save_report.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        const alert = document.getElementById('save-alert');

        if (data.success) {
            alert.className = 'doc-alert success';
            alert.textContent = '✅ ' + data.message;
            if (data.reportid) {
                document.querySelector('input[name="reportid"]').value = data.reportid;
            }
        } else {
            alert.className = 'doc-alert error';
            alert.textContent = '❌ ' + data.message;
        }

        setTimeout(() => { alert.className = 'doc-alert'; }, 5000);

    } catch (error) {
        console.error(error);
        const alert = document.getElementById('save-alert');
        alert.className = 'doc-alert error';
        alert.textContent = '❌ Errore di connessione';
    }

    this.disabled = false;
    this.innerHTML = '💾 Salva';
});

document.getElementById('btn-export').addEventListener('click', function() {
    const reportId = document.querySelector('input[name="reportid"]').value;

    if (!reportId) {
        if (confirm('Per esportare in Word è necessario prima salvare il report. Salvare ora?')) {
            document.getElementById('btn-save').click();
            setTimeout(function() {
                const newReportId = document.querySelector('input[name="reportid"]').value;
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
