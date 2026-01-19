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
 * Report generation page.
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

// Get existing report if any.
$report = \local_ftm_cpurc\cpurc_manager::get_report($id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/report.php', ['id' => $id]));
$PAGE->set_title(get_string('report_title', 'local_ftm_cpurc') . ': ' . fullname($student));
$PAGE->set_heading(get_string('report_title', 'local_ftm_cpurc'));
$PAGE->set_pagelayout('standard');

// Calculate week.
$week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($student->date_start);

echo $OUTPUT->header();
?>

<style>
.report-page {
    max-width: 900px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.report-header {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.report-header h2 {
    margin: 0;
    font-size: 20px;
}

.header-buttons {
    display: flex;
    gap: 10px;
}

.cpurc-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.cpurc-btn-primary { background: #0066cc; color: white; }
.cpurc-btn-success { background: #28a745; color: white; }
.cpurc-btn-secondary { background: #6c757d; color: white; }
.cpurc-btn:hover { opacity: 0.9; text-decoration: none; color: white; }

.report-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
}

.form-group input, .form-group select {
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

/* Rating scale */
.rating-scale {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.rating-option {
    flex: 1;
    text-align: center;
}

.rating-option input {
    display: none;
}

.rating-option label {
    display: block;
    padding: 15px 10px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.rating-option label:hover {
    border-color: #0066cc;
    background: #f0f7ff;
}

.rating-option input:checked + label {
    border-color: #0066cc;
    background: #0066cc;
    color: white;
}

.rating-number {
    font-size: 24px;
    font-weight: 700;
}

.rating-label {
    font-size: 11px;
    margin-top: 5px;
}

/* Competency items */
.competency-grid {
    display: grid;
    gap: 15px;
}

.competency-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.competency-name {
    font-weight: 500;
}

.competency-rating {
    display: flex;
    gap: 5px;
}

.competency-rating input {
    display: none;
}

.competency-rating label {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    background: white;
}

.competency-rating label:hover {
    border-color: #0066cc;
}

.competency-rating input:checked + label {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

/* Checkbox grid */
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.checkbox-item input {
    width: 18px;
    height: 18px;
}

/* Interviews table */
.interviews-table {
    width: 100%;
    border-collapse: collapse;
}

.interviews-table th, .interviews-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.interviews-table th {
    background: #f8f9fa;
    font-size: 12px;
}

.interviews-table input {
    width: 100%;
}

/* Form actions */
.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Alert */
.save-alert {
    display: none;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.save-alert.success {
    display: block;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.save-alert.error {
    display: block;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* Student info bar */
.student-bar {
    background: #e8f4fd;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 30px;
}

.student-bar-item {
    display: flex;
    flex-direction: column;
}

.student-bar-item .label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
}

.student-bar-item .value {
    font-weight: 600;
}
</style>

<div class="report-page">
    <!-- Header -->
    <div class="report-header">
        <h2>📝 Report: <?php echo s($student->lastname); ?> <?php echo s($student->firstname); ?></h2>
        <div class="header-buttons">
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/student_card.php', ['id' => $id]); ?>" class="cpurc-btn cpurc-btn-secondary">
                📋 Scheda
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                ← Torna
            </a>
        </div>
    </div>

    <!-- Student info bar -->
    <div class="student-bar">
        <div class="student-bar-item">
            <span class="label">Settore</span>
            <span class="value"><?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected) ?: '-'); ?></span>
        </div>
        <div class="student-bar-item">
            <span class="label">Settimana</span>
            <span class="value"><?php echo $week; ?>/6</span>
        </div>
        <div class="student-bar-item">
            <span class="label">URC</span>
            <span class="value"><?php echo s($student->urc_office ?: '-'); ?></span>
        </div>
        <div class="student-bar-item">
            <span class="label">Inizio</span>
            <span class="value"><?php echo local_ftm_cpurc_format_date($student->date_start); ?></span>
        </div>
        <div class="student-bar-item">
            <span class="label">Fine prevista</span>
            <span class="value"><?php echo local_ftm_cpurc_format_date($student->date_end_planned); ?></span>
        </div>
    </div>

    <div id="save-alert" class="save-alert"></div>

    <form id="report-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="studentid" value="<?php echo $id; ?>">
        <input type="hidden" name="reportid" value="<?php echo $report ? $report->id : ''; ?>">

        <!-- Sezione 4: Situazione iniziale -->
        <div class="report-section">
            <div class="section-header">
                <span>4️⃣</span> <?php echo get_string('section_initial_situation', 'local_ftm_cpurc'); ?>
            </div>
            <div class="section-body">
                <div class="form-group">
                    <label>Descrizione della situazione iniziale del partecipante</label>
                    <textarea name="initial_situation" rows="5" placeholder="Descrivere la situazione iniziale, le aspettative e gli obiettivi del partecipante..."><?php echo s($report->initial_situation ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sezione 5: Competenze settore -->
        <div class="report-section">
            <div class="section-header">
                <span>5️⃣</span> <?php echo get_string('section_sector_competencies', 'local_ftm_cpurc'); ?>
                <?php if (!empty($student->sector_detected)): ?>
                    (<?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>)
                <?php endif; ?>
            </div>
            <div class="section-body">
                <div class="form-group">
                    <label>Valutazione complessiva (1-5)</label>
                    <div class="rating-scale">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="rating-option">
                            <input type="radio" name="sector_competency_rating" id="rating_<?php echo $i; ?>" value="<?php echo $i; ?>"
                                   <?php echo (isset($report->sector_competency_rating) && $report->sector_competency_rating == $i) ? 'checked' : ''; ?>>
                            <label for="rating_<?php echo $i; ?>">
                                <div class="rating-number"><?php echo $i; ?></div>
                                <div class="rating-label">
                                    <?php
                                    $labels = ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Ottimo'];
                                    echo $labels[$i-1];
                                    ?>
                                </div>
                            </label>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Commento sulle competenze tecniche</label>
                    <textarea name="sector_competency_text" rows="4" placeholder="Descrivere le competenze tecniche osservate..."><?php echo s($report->sector_competency_text ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sezione 6: Competenze trasversali -->
        <div class="report-section">
            <div class="section-header">
                <span>6️⃣</span> <?php echo get_string('section_transversal_competencies', 'local_ftm_cpurc'); ?>
            </div>
            <div class="section-body">
                <?php
                $competencies = [
                    'personal' => ['Affidabilita', 'Puntualita', 'Autonomia', 'Iniziativa', 'Flessibilita'],
                    'social' => ['Lavoro in team', 'Comunicazione', 'Rispetto regole', 'Gestione conflitti'],
                    'methodological' => ['Organizzazione', 'Problem solving', 'Gestione tempo', 'Apprendimento'],
                    'tic' => ['Competenze base PC', 'Email', 'Ricerca online', 'Software specifici'],
                ];

                foreach ($competencies as $type => $items):
                    $saved = [];
                    $fieldname = $type . '_competencies';
                    if (isset($report->$fieldname)) {
                        $saved = json_decode($report->$fieldname, true) ?: [];
                    }
                ?>
                <div class="form-group">
                    <label><?php echo ucfirst($type === 'tic' ? 'TIC' : $type); ?></label>
                    <div class="competency-grid">
                        <?php foreach ($items as $idx => $item): ?>
                        <div class="competency-item">
                            <span class="competency-name"><?php echo s($item); ?></span>
                            <div class="competency-rating">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <input type="radio" name="<?php echo $type; ?>_<?php echo $idx; ?>" id="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $r; ?>" value="<?php echo $r; ?>"
                                       <?php echo (isset($saved[$idx]) && $saved[$idx] == $r) ? 'checked' : ''; ?>>
                                <label for="<?php echo $type; ?>_<?php echo $idx; ?>_<?php echo $r; ?>"><?php echo $r; ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sezione 7: Ricerca impiego -->
        <div class="report-section">
            <div class="section-header">
                <span>7️⃣</span> <?php echo get_string('section_job_search', 'local_ftm_cpurc'); ?>
            </div>
            <div class="section-body">
                <div class="form-group">
                    <label>Dossier di candidatura completo?</label>
                    <div class="checkbox-grid" style="grid-template-columns: 1fr;">
                        <div class="checkbox-item">
                            <input type="checkbox" name="dossier_complete" id="dossier_complete" value="1"
                                   <?php echo (!empty($report->dossier_complete)) ? 'checked' : ''; ?>>
                            <label for="dossier_complete">Si, il dossier e completo (CV, lettera, certificati)</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Canali di ricerca utilizzati</label>
                    <?php
                    $channels = ['Portali online', 'Candidature spontanee', 'Rete personale', 'Agenzie interinali', 'Social media', 'Giornali'];
                    $savedChannels = [];
                    if (isset($report->search_channels)) {
                        $savedChannels = json_decode($report->search_channels, true) ?: [];
                    }
                    ?>
                    <div class="checkbox-grid">
                        <?php foreach ($channels as $idx => $channel): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="channel_<?php echo $idx; ?>" id="channel_<?php echo $idx; ?>" value="<?php echo s($channel); ?>"
                                   <?php echo in_array($channel, $savedChannels) ? 'checked' : ''; ?>>
                            <label for="channel_<?php echo $idx; ?>"><?php echo s($channel); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Valutazione impegno nella ricerca (1-5)</label>
                    <div class="rating-scale">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="rating-option">
                            <input type="radio" name="search_evaluation" id="search_<?php echo $i; ?>" value="<?php echo $i; ?>"
                                   <?php echo (isset($report->search_evaluation) && $report->search_evaluation == $i) ? 'checked' : ''; ?>>
                            <label for="search_<?php echo $i; ?>">
                                <div class="rating-number"><?php echo $i; ?></div>
                            </label>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sezione 9: Esito -->
        <div class="report-section">
            <div class="section-header">
                <span>9️⃣</span> <?php echo get_string('section_outcome', 'local_ftm_cpurc'); ?>
            </div>
            <div class="section-body">
                <div class="form-group">
                    <label>Il partecipante e stato assunto?</label>
                    <div class="checkbox-grid" style="grid-template-columns: 1fr;">
                        <div class="checkbox-item">
                            <input type="checkbox" name="hired" id="hired" value="1"
                                   <?php echo (!empty($report->hired)) ? 'checked' : ''; ?>
                                   onchange="toggleHiredFields()">
                            <label for="hired">Si, il partecipante ha trovato impiego</label>
                        </div>
                    </div>
                </div>

                <div id="hired-fields" style="display: <?php echo (!empty($report->hired)) ? 'block' : 'none'; ?>;">
                    <div class="form-group">
                        <label>Azienda</label>
                        <input type="text" name="hired_company" value="<?php echo s($report->hired_company ?? ''); ?>" placeholder="Nome dell'azienda">
                    </div>
                    <div class="form-group">
                        <label>Data assunzione</label>
                        <input type="date" name="hired_date" value="<?php echo !empty($report->hired_date) ? date('Y-m-d', $report->hired_date) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Percentuale</label>
                        <input type="number" name="hired_percentage" min="0" max="100" value="<?php echo $report->hired_percentage ?? ''; ?>" placeholder="100">
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="button" id="btn-save" class="cpurc-btn cpurc-btn-primary">
                💾 <?php echo get_string('save_draft', 'local_ftm_cpurc'); ?>
            </button>
            <button type="button" id="btn-export" class="cpurc-btn cpurc-btn-success">
                📤 <?php echo get_string('export_data', 'local_ftm_cpurc'); ?>
            </button>
        </div>
    </form>
</div>

<script>
function toggleHiredFields() {
    const hired = document.getElementById('hired').checked;
    document.getElementById('hired-fields').style.display = hired ? 'block' : 'none';
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
            alert.className = 'save-alert success';
            alert.textContent = '✅ ' + data.message;
            if (data.reportid) {
                document.querySelector('input[name="reportid"]').value = data.reportid;
            }
        } else {
            alert.className = 'save-alert error';
            alert.textContent = '❌ ' + data.message;
        }

        setTimeout(() => { alert.className = 'save-alert'; }, 5000);

    } catch (error) {
        console.error(error);
        const alert = document.getElementById('save-alert');
        alert.className = 'save-alert error';
        alert.textContent = '❌ Errore di connessione';
    }

    this.disabled = false;
    this.innerHTML = '💾 <?php echo get_string('save_draft', 'local_ftm_cpurc'); ?>';
});

document.getElementById('btn-export').addEventListener('click', function() {
    // Export data as JSON for manual Word compilation
    const form = document.getElementById('report-form');
    const data = {
        student: {
            name: '<?php echo s($student->firstname . ' ' . $student->lastname); ?>',
            email: '<?php echo s($student->email); ?>',
            sector: '<?php echo s($student->sector_detected); ?>',
            urc: '<?php echo s($student->urc_office); ?>',
            date_start: '<?php echo local_ftm_cpurc_format_date($student->date_start); ?>',
            date_end: '<?php echo local_ftm_cpurc_format_date($student->date_end_planned); ?>',
        },
        report: {}
    };

    // Collect form data
    const formData = new FormData(form);
    for (let [key, value] of formData.entries()) {
        if (key !== 'sesskey' && key !== 'studentid' && key !== 'reportid') {
            data.report[key] = value;
        }
    }

    // Download as JSON
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'report_<?php echo s($student->lastname . '_' . $student->firstname); ?>_<?php echo date('Y-m-d'); ?>.json';
    link.click();
});
</script>

<?php
echo $OUTPUT->footer();
