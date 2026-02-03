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
 * Import calendar from Excel file.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/import_calendar.php'));
$PAGE->set_title(get_string('import_calendar', 'local_ftm_scheduler'));
$PAGE->set_heading(get_string('import_calendar', 'local_ftm_scheduler'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
.scheduler-import {
    max-width: 1000px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.import-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
}

.import-card h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-instructions {
    background: #e8f4fd;
    border-left: 4px solid #0066cc;
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 0 8px 8px 0;
}

.import-instructions ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.import-instructions li {
    margin-bottom: 5px;
}

.drop-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 50px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: #fafafa;
}

.drop-zone:hover, .drop-zone.dragover {
    border-color: #0066cc;
    background: #f0f7ff;
}

.drop-zone.has-file {
    border-color: #28a745;
    background: #f0fff4;
}

.drop-zone-icon {
    font-size: 56px;
    margin-bottom: 15px;
}

.drop-zone-text {
    font-size: 16px;
    color: #666;
}

.drop-zone-file {
    margin-top: 15px;
    font-size: 14px;
    color: #28a745;
    font-weight: 600;
}

.sheet-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.sheet-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.sheet-checkbox:hover {
    background: #e9ecef;
}

.sheet-checkbox.selected {
    background: #cce5ff;
    border: 1px solid #0066cc;
}

.sheet-checkbox input {
    width: 16px;
    height: 16px;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 15px;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.option-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.option-item label {
    margin: 0;
    font-size: 14px;
    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group select, .form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.btn-row {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.ftm-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.ftm-btn-primary { background: #0066cc; color: white; }
.ftm-btn-success { background: #28a745; color: white; }
.ftm-btn-secondary { background: #6c757d; color: white; }
.ftm-btn-warning { background: #ffc107; color: #333; }

.ftm-btn:hover { opacity: 0.9; text-decoration: none; color: white; }
.ftm-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Preview section */
.preview-section {
    display: none;
}

.preview-section.active {
    display: block;
}

.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 15px;
}

.preview-table th, .preview-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.preview-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    position: sticky;
    top: 0;
}

.preview-table tr:hover {
    background: #f8f9fa;
}

.preview-scroll {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-coach { background: #cce5ff; color: #004085; }
.badge-giallo { background: #FFFF00; color: #333; }
.badge-grigio { background: #808080; color: white; }
.badge-rosso { background: #FF0000; color: white; }
.badge-marrone { background: #996633; color: white; }
.badge-viola { background: #7030A0; color: white; }
.badge-activity { background: #d4edda; color: #155724; }

/* Results */
.import-result {
    display: none;
    margin-top: 20px;
}

.import-result.active {
    display: block;
}

.result-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 20px;
    border-radius: 8px;
}

.result-warning {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 20px;
    border-radius: 8px;
}

.result-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 20px;
    border-radius: 8px;
}

.stats-row {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    padding: 15px 25px;
    background: white;
    border-radius: 8px;
    text-align: center;
    min-width: 100px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    margin-top: 15px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #0066cc;
    width: 0;
    transition: width 0.3s;
}
</style>

<div class="scheduler-import">
    <div class="import-card">
        <h3>📅 <?php echo get_string('import_calendar', 'local_ftm_scheduler'); ?></h3>

        <div class="import-instructions">
            <strong>Istruzioni per l'import:</strong>
            <ul>
                <li>File Excel (.xlsx) con planning mensile attività formatori</li>
                <li>Ogni foglio rappresenta un mese (Gennaio, Febbraio, ecc.)</li>
                <li>Struttura: Data | Matt/Pom | Coach | Aula | Attività</li>
                <li>Coach supportati: CB, FM, GM, RB</li>
                <li>Gruppi: GR. GIALLO, GR. GRIGIO, GR. ROSSO, GR. MARRONE, GR. VIOLA</li>
            </ul>
        </div>

        <form id="import-form" enctype="multipart/form-data">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

            <div class="drop-zone" id="drop-zone">
                <div class="drop-zone-icon">📊</div>
                <div class="drop-zone-text">
                    Trascina qui il file Excel oppure <strong>clicca per selezionare</strong>
                </div>
                <div class="drop-zone-file" id="file-name" style="display:none;"></div>
                <input type="file" name="excelfile" id="excelfile" accept=".xlsx,.xls" style="display:none;">
            </div>
        </form>
    </div>

    <div class="import-card" id="sheets-section" style="display:none;">
        <h3>📑 Seleziona Fogli da Importare</h3>
        <div class="sheet-selector" id="sheet-list">
            <!-- Populated via JavaScript -->
        </div>
        <div style="margin-top: 15px;">
            <button type="button" class="ftm-btn ftm-btn-secondary" onclick="selectAllSheets(true)">Seleziona Tutti</button>
            <button type="button" class="ftm-btn ftm-btn-secondary" onclick="selectAllSheets(false)">Deseleziona Tutti</button>
        </div>
    </div>

    <div class="import-card preview-section" id="preview-section">
        <h3>👁 Anteprima Attività</h3>
        <p id="preview-summary"></p>
        <div class="preview-scroll">
            <table class="preview-table" id="preview-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Fascia</th>
                        <th>Orario</th>
                        <th>Coach</th>
                        <th>Gruppi</th>
                        <th>Attività</th>
                    </tr>
                </thead>
                <tbody id="preview-body">
                </tbody>
            </table>
        </div>
    </div>

    <div class="import-card">
        <h3>⚙️ Opzioni Import</h3>

        <div class="form-group">
            <label>Anno di riferimento</label>
            <select name="import_year" id="import_year">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="options-grid">
            <div class="option-item">
                <input type="checkbox" name="update_existing" id="update_existing" value="1">
                <label for="update_existing">Aggiorna attività esistenti (stesso giorno/ora)</label>
            </div>
            <div class="option-item">
                <input type="checkbox" name="dry_run" id="dry_run" value="1" checked>
                <label for="dry_run">Solo anteprima (non importare)</label>
            </div>
        </div>

        <div class="btn-row">
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="ftm-btn ftm-btn-secondary">
                ← Torna al Calendario
            </a>
            <button type="button" id="btn-preview" class="ftm-btn ftm-btn-primary" disabled>
                👁 Anteprima
            </button>
            <button type="button" id="btn-import" class="ftm-btn ftm-btn-success" disabled>
                📥 Importa Attività
            </button>
        </div>
    </div>

    <div class="import-result" id="import-result">
        <div id="result-content"></div>
    </div>

    <!-- Sezione Gestione Attività -->
    <div class="import-card" style="border-left: 4px solid #dc3545;">
        <h3>🗑️ Gestione Attività in Blocco</h3>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
            <!-- Cancella importate oggi -->
            <div style="padding: 15px; background: #fff5f5; border-radius: 8px; border: 1px solid #ffcccc;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Annulla Import Oggi</h4>
                <p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">
                    Elimina tutte le attività importate nella giornata odierna.
                </p>
                <button type="button" class="ftm-btn ftm-btn-danger ftm-btn-sm" onclick="deleteImportedToday()">
                    🗑️ Cancella Import Oggi
                </button>
                <span id="count-today" style="margin-left: 10px; font-size: 12px; color: #666;"></span>
            </div>

            <!-- Cancella per data -->
            <div style="padding: 15px; background: #fff5f5; border-radius: 8px; border: 1px solid #ffcccc;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Cancella per Periodo</h4>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="date" id="delete-date-from" style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                    <input type="date" id="delete-date-to" style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="ftm-btn ftm-btn-danger ftm-btn-sm" onclick="deleteByDateRange()">
                        🗑️ Solo Attività
                    </button>
                    <button type="button" class="ftm-btn ftm-btn-danger ftm-btn-sm" onclick="deleteAllInPeriod()">
                        🗑️ TUTTO (Attività + Esterni)
                    </button>
                </div>
                <span id="count-range" style="display: block; margin-top: 10px; font-size: 12px; color: #666;"></span>
            </div>
        </div>

        <!-- Preset periodi -->
        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
            <span style="font-size: 13px; color: #666; align-self: center;">Preset:</span>
            <button type="button" class="ftm-btn ftm-btn-secondary ftm-btn-sm" onclick="setDateRange('thisweek')">Questa Settimana</button>
            <button type="button" class="ftm-btn ftm-btn-secondary ftm-btn-sm" onclick="setDateRange('thismonth')">Questo Mese</button>
            <button type="button" class="ftm-btn ftm-btn-secondary ftm-btn-sm" onclick="setDateRange('february')">Febbraio 2026</button>
            <button type="button" class="ftm-btn ftm-btn-secondary ftm-btn-sm" onclick="setDateRange('year')">Anno 2026</button>
        </div>

        <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
            <strong style="color: #dc3545;">⚠️ Attenzione:</strong>
            <span style="font-size: 13px;">Le operazioni di eliminazione sono irreversibili. Assicurati di aver selezionato il periodo corretto.</span>
        </div>
    </div>

    <!-- Gestione Gruppi -->
    <div class="import-card" style="border-left: 4px solid #fd7e14;">
        <h3>🎨 Gestione Gruppi</h3>
        <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
            I gruppi attivi mostrano "REMOTO" nel calendario anche senza attività. Disattivali per pulire il calendario.
        </p>

        <div id="groups-list" style="margin-bottom: 15px;">
            <!-- Populated via JavaScript -->
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="ftm-btn ftm-btn-secondary ftm-btn-sm" onclick="loadGroups()">
                🔄 Aggiorna Lista
            </button>
            <button type="button" class="ftm-btn ftm-btn-warning ftm-btn-sm" onclick="deactivateAllGroups()">
                ⏸️ Disattiva Tutti i Gruppi
            </button>
            <button type="button" class="ftm-btn ftm-btn-danger ftm-btn-sm" onclick="resetCalendar()">
                🔥 RESET COMPLETO
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('excelfile');
    const fileName = document.getElementById('file-name');
    const btnPreview = document.getElementById('btn-preview');
    const btnImport = document.getElementById('btn-import');
    const sheetsSection = document.getElementById('sheets-section');
    const sheetList = document.getElementById('sheet-list');
    const previewSection = document.getElementById('preview-section');
    const previewBody = document.getElementById('preview-body');
    const previewSummary = document.getElementById('preview-summary');
    const importResult = document.getElementById('import-result');
    const resultContent = document.getElementById('result-content');

    let selectedFile = null;
    let availableSheets = [];

    // Drop zone events
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFile(fileInput.files[0]);
        }
    });

    function handleFile(file) {
        if (!file.name.match(/\.xlsx?$/i)) {
            alert('Seleziona un file Excel (.xlsx o .xls)');
            return;
        }

        selectedFile = file;
        fileName.textContent = '✅ ' + file.name + ' (' + formatSize(file.size) + ')';
        fileName.style.display = 'block';
        dropZone.classList.add('has-file');
        btnPreview.disabled = false;

        // Load sheet list
        loadSheets();
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    async function loadSheets() {
        const formData = new FormData();
        formData.append('sesskey', '<?php echo sesskey(); ?>');
        formData.append('action', 'get_sheets');
        formData.append('excelfile', selectedFile);

        try {
            const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_import_calendar.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.sheets) {
                availableSheets = data.sheets;
                renderSheetList(data.sheets);
                sheetsSection.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading sheets:', error);
        }
    }

    function renderSheetList(sheets) {
        sheetList.innerHTML = '';
        sheets.forEach((sheet, index) => {
            const div = document.createElement('div');
            div.className = 'sheet-checkbox selected';
            div.innerHTML = `
                <input type="checkbox" name="sheets[]" value="${escapeHtml(sheet)}" id="sheet-${index}" checked>
                <label for="sheet-${index}">${escapeHtml(sheet)}</label>
            `;
            div.querySelector('input').addEventListener('change', function() {
                div.classList.toggle('selected', this.checked);
            });
            sheetList.appendChild(div);
        });
    }

    window.selectAllSheets = function(select) {
        document.querySelectorAll('#sheet-list input[type="checkbox"]').forEach(cb => {
            cb.checked = select;
            cb.closest('.sheet-checkbox').classList.toggle('selected', select);
        });
    };

    // Preview button
    btnPreview.addEventListener('click', async () => {
        if (!selectedFile) return;

        btnPreview.disabled = true;
        btnPreview.innerHTML = '⏳ Caricamento...';

        const formData = new FormData();
        formData.append('sesskey', '<?php echo sesskey(); ?>');
        formData.append('action', 'preview');
        formData.append('excelfile', selectedFile);
        formData.append('year', document.getElementById('import_year').value);

        // Get selected sheets
        document.querySelectorAll('#sheet-list input:checked').forEach(cb => {
            formData.append('sheets[]', cb.value);
        });

        try {
            const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_import_calendar.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                renderPreview(data);
                btnImport.disabled = false;
            } else {
                alert('Errore: ' + (data.message || 'Errore sconosciuto'));
            }
        } catch (error) {
            alert('Errore di connessione');
            console.error(error);
        }

        btnPreview.disabled = false;
        btnPreview.innerHTML = '👁 Anteprima';
    });

    function renderPreview(data) {
        previewBody.innerHTML = '';

        if (!data.preview || data.preview.length === 0) {
            previewBody.innerHTML = '<tr><td colspan="6">Nessuna attività trovata nel file.</td></tr>';
            previewSection.classList.add('active');
            return;
        }

        previewSummary.textContent = `Trovate ${data.total_activities || data.preview.length} attività da importare`;

        data.preview.forEach(activity => {
            const tr = document.createElement('tr');

            // Date
            const date = new Date(activity.timestamp_start * 1000);
            const dateStr = date.toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'short' });

            // Coaches badges
            let coachesBadges = '';
            if (activity.coaches) {
                activity.coaches.forEach(c => {
                    coachesBadges += `<span class="badge badge-coach">${escapeHtml(c.initials)}</span> `;
                });
            }

            // Groups badges
            let groupsBadges = '';
            if (activity.groups) {
                activity.groups.forEach(g => {
                    groupsBadges += `<span class="badge badge-${g.color}">${escapeHtml(g.label)}</span> `;
                });
            }

            // Activities badges
            let activitiesBadges = '';
            if (activity.activities) {
                activity.activities.forEach(a => {
                    activitiesBadges += `<span class="badge badge-activity">${escapeHtml(a.label)}</span> `;
                });
            }

            // External projects badges
            if (activity.external_projects) {
                activity.external_projects.forEach(p => {
                    activitiesBadges += `<span class="badge" style="background:#DBEAFE;color:#1E40AF;">${escapeHtml(p.name)}</span> `;
                });
            }

            tr.innerHTML = `
                <td>${dateStr}</td>
                <td>${activity.slot_label}</td>
                <td>${activity.time_start} - ${activity.time_end}</td>
                <td>${coachesBadges || '-'}</td>
                <td>${groupsBadges || '-'}</td>
                <td>${activitiesBadges || '-'}</td>
            `;

            previewBody.appendChild(tr);
        });

        previewSection.classList.add('active');
    }

    // Import button
    btnImport.addEventListener('click', async () => {
        if (!selectedFile) return;

        const dryRun = document.getElementById('dry_run').checked;

        if (!dryRun && !confirm('Sei sicuro di voler importare le attività? Questa operazione creerà nuove attività nel calendario.')) {
            return;
        }

        btnImport.disabled = true;
        btnImport.innerHTML = '⏳ Importazione...';

        importResult.classList.add('active');
        resultContent.innerHTML = '<div class="result-warning">⏳ Importazione in corso...</div>';

        const formData = new FormData();
        formData.append('sesskey', '<?php echo sesskey(); ?>');
        formData.append('action', 'import');
        formData.append('excelfile', selectedFile);
        formData.append('year', document.getElementById('import_year').value);
        formData.append('update_existing', document.getElementById('update_existing').checked ? '1' : '0');
        formData.append('dry_run', dryRun ? '1' : '0');

        // Get selected sheets
        document.querySelectorAll('#sheet-list input:checked').forEach(cb => {
            formData.append('sheets[]', cb.value);
        });

        try {
            const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_import_calendar.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            showImportResult(data, dryRun);

        } catch (error) {
            resultContent.innerHTML = '<div class="result-error">❌ Errore di connessione</div>';
            console.error(error);
        }

        btnImport.disabled = false;
        btnImport.innerHTML = '📥 Importa Attività';
    });

    function showImportResult(data, dryRun) {
        const stats = data.stats || {};
        const hasErrors = (stats.errors || 0) > 0;

        let html = '<div class="' + (hasErrors ? 'result-warning' : 'result-success') + '">';

        if (dryRun) {
            html += '👁 <strong>Anteprima completata</strong> - Nessuna modifica effettuata';
        } else {
            html += hasErrors ? '⚠️ Importazione completata con alcuni errori' : '✅ Importazione completata con successo!';
        }
        html += '</div>';

        html += '<div class="stats-row">';
        html += `<div class="stat-item"><div class="stat-number">${stats.total_rows || 0}</div><div class="stat-label">Righe Analizzate</div></div>`;
        html += `<div class="stat-item" style="color:#28a745"><div class="stat-number">${stats.activities_created || 0}</div><div class="stat-label">Create</div></div>`;
        html += `<div class="stat-item" style="color:#0066cc"><div class="stat-number">${stats.activities_updated || 0}</div><div class="stat-label">Aggiornate</div></div>`;
        html += `<div class="stat-item" style="color:#6c757d"><div class="stat-number">${stats.skipped || 0}</div><div class="stat-label">Saltate</div></div>`;
        html += `<div class="stat-item" style="color:#dc3545"><div class="stat-number">${stats.errors || 0}</div><div class="stat-label">Errori</div></div>`;
        html += '</div>';

        if (data.errors && data.errors.length > 0) {
            html += '<div style="margin-top:20px;"><strong>Errori:</strong><ul style="margin-top:10px;">';
            data.errors.forEach(err => {
                html += `<li style="color:#dc3545">${escapeHtml(err)}</li>`;
            });
            html += '</ul></div>';
        }

        if (!dryRun && (stats.activities_created > 0 || stats.activities_updated > 0)) {
            html += `<div style="margin-top:20px;">
                <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php" class="ftm-btn ftm-btn-primary">
                    📅 Vai al Calendario
                </a>
            </div>`;
        }

        resultContent.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load count of activities imported today on page load.
    loadTodayCount();
});

// Count activities imported today.
async function loadTodayCount() {
    try {
        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=count_imported_today&sesskey=<?php echo sesskey(); ?>');
        const data = await response.json();
        if (data.success) {
            document.getElementById('count-today').textContent = `(${data.count} attività)`;
        }
    } catch (error) {
        console.error('Error counting activities:', error);
    }
}

// Delete activities imported today.
async function deleteImportedToday() {
    // First get the count.
    try {
        const countResponse = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=count_imported_today&sesskey=<?php echo sesskey(); ?>');
        const countData = await countResponse.json();

        if (!countData.success || countData.count === 0) {
            alert('Nessuna attività importata oggi da eliminare.');
            return;
        }

        if (!confirm(`Sei sicuro di voler eliminare ${countData.count} attività importate oggi?\n\nQuesta operazione è irreversibile!`)) {
            return;
        }

        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=delete_imported_today&sesskey=<?php echo sesskey(); ?>');
        const data = await response.json();

        if (data.success) {
            alert(`✅ ${data.message}`);
            loadTodayCount();
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (error) {
        alert('❌ Errore di connessione');
        console.error(error);
    }
}

// Delete activities by date range.
async function deleteByDateRange() {
    const dateFrom = document.getElementById('delete-date-from').value;
    const dateTo = document.getElementById('delete-date-to').value;

    if (!dateFrom || !dateTo) {
        alert('Seleziona entrambe le date (da e a)');
        return;
    }

    // Convert dates to timestamps.
    const fromTs = new Date(dateFrom + 'T00:00:00').getTime() / 1000;
    const toTs = new Date(dateTo + 'T23:59:59').getTime() / 1000;

    if (fromTs > toTs) {
        alert('La data "Da" deve essere precedente alla data "A"');
        return;
    }

    try {
        // First count.
        const countUrl = `<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=count_by_date_range&sesskey=<?php echo sesskey(); ?>&date_from=${fromTs}&date_to=${toTs}`;
        const countResponse = await fetch(countUrl);
        const countData = await countResponse.json();

        if (!countData.success || countData.count === 0) {
            alert('Nessuna attività trovata nel periodo selezionato.');
            return;
        }

        document.getElementById('count-range').textContent = `(${countData.count} attività)`;

        if (!confirm(`Sei sicuro di voler eliminare ${countData.count} attività dal ${dateFrom} al ${dateTo}?\n\nQuesta operazione è irreversibile!`)) {
            return;
        }

        const deleteUrl = `<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=delete_by_date_range&sesskey=<?php echo sesskey(); ?>&date_from=${fromTs}&date_to=${toTs}`;
        const response = await fetch(deleteUrl);
        const data = await response.json();

        if (data.success) {
            alert(`✅ ${data.message}`);
            document.getElementById('count-range').textContent = '';
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (error) {
        alert('❌ Errore di connessione');
        console.error(error);
    }
}

// Delete ALL (activities + external bookings) in date range.
async function deleteAllInPeriod() {
    const dateFrom = document.getElementById('delete-date-from').value;
    const dateTo = document.getElementById('delete-date-to').value;

    if (!dateFrom || !dateTo) {
        alert('Seleziona entrambe le date (da e a)');
        return;
    }

    const fromTs = new Date(dateFrom + 'T00:00:00').getTime() / 1000;
    const toTs = new Date(dateTo + 'T23:59:59').getTime() / 1000;

    if (fromTs > toTs) {
        alert('La data "Da" deve essere precedente alla data "A"');
        return;
    }

    if (!confirm(`⚠️ ATTENZIONE!\n\nQuesto eliminerà TUTTE le attività E le prenotazioni esterne (BIT URAR, BIT AI, ecc.) dal ${dateFrom} al ${dateTo}.\n\nSei sicuro di voler procedere?`)) {
        return;
    }

    try {
        const deleteUrl = `<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=delete_all_in_period&sesskey=<?php echo sesskey(); ?>&date_from=${fromTs}&date_to=${toTs}`;
        const response = await fetch(deleteUrl);
        const data = await response.json();

        if (data.success) {
            alert(`✅ ${data.message}`);
            document.getElementById('count-range').textContent = '';
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (error) {
        alert('❌ Errore di connessione');
        console.error(error);
    }
}

// Load groups list.
async function loadGroups() {
    try {
        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=list_groups&sesskey=<?php echo sesskey(); ?>');
        const data = await response.json();

        const container = document.getElementById('groups-list');

        if (!data.success || data.count === 0) {
            container.innerHTML = '<p style="color: #666; font-size: 13px;">Nessun gruppo trovato.</p>';
            return;
        }

        let html = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
        data.groups.forEach(g => {
            const statusColor = g.status === 'active' ? '#28a745' : '#6c757d';
            const statusIcon = g.status === 'active' ? '🟢' : '⚪';
            html += `
                <div style="padding: 10px 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid ${getColorHex(g.color)};">
                    <strong>${g.name}</strong><br>
                    <span style="font-size: 12px; color: ${statusColor};">${statusIcon} ${g.status} (${g.members} membri)</span>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    } catch (error) {
        console.error('Error loading groups:', error);
    }
}

function getColorHex(color) {
    const colors = {
        'giallo': '#FFFF00',
        'grigio': '#808080',
        'rosso': '#FF0000',
        'marrone': '#996633',
        'viola': '#7030A0'
    };
    return colors[color] || '#CCCCCC';
}

// Deactivate all groups.
async function deactivateAllGroups() {
    if (!confirm('Sei sicuro di voler disattivare tutti i gruppi attivi?\n\nI gruppi rimarranno nel database ma non appariranno più nel calendario.')) {
        return;
    }

    try {
        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=deactivate_groups&sesskey=<?php echo sesskey(); ?>');
        const data = await response.json();

        if (data.success) {
            alert(`✅ ${data.message}`);
            loadGroups();
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (error) {
        alert('❌ Errore di connessione');
        console.error(error);
    }
}

// Full calendar reset.
async function resetCalendar() {
    if (!confirm('⚠️ ATTENZIONE!\n\nQuesto eseguirà un RESET COMPLETO:\n- Elimina TUTTE le attività\n- Elimina TUTTE le prenotazioni esterne\n- Disattiva TUTTI i gruppi\n\nSei ASSOLUTAMENTE sicuro?')) {
        return;
    }

    // Double confirmation.
    const confirmText = prompt('Per confermare, scrivi "RESET" (tutto maiuscolo):');
    if (confirmText !== 'RESET') {
        alert('Reset annullato.');
        return;
    }

    try {
        const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_delete_activities.php?action=reset_calendar&sesskey=<?php echo sesskey(); ?>&confirm=RESET_COMPLETO');
        const data = await response.json();

        if (data.success) {
            alert(`✅ ${data.message}`);
            loadGroups();
            loadTodayCount();
        } else {
            alert('❌ Errore: ' + data.message);
        }
    } catch (error) {
        alert('❌ Errore di connessione');
        console.error(error);
    }
}

// Load groups on page load.
loadGroups();

// Set date range presets.
function setDateRange(preset) {
    const dateFrom = document.getElementById('delete-date-from');
    const dateTo = document.getElementById('delete-date-to');
    const today = new Date();

    switch (preset) {
        case 'thisweek':
            // Get Monday of current week.
            const monday = new Date(today);
            const dayOfWeek = monday.getDay();
            const diff = monday.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
            monday.setDate(diff);
            const friday = new Date(monday);
            friday.setDate(monday.getDate() + 4);
            dateFrom.value = monday.toISOString().split('T')[0];
            dateTo.value = friday.toISOString().split('T')[0];
            break;

        case 'thismonth':
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            dateFrom.value = firstDay.toISOString().split('T')[0];
            dateTo.value = lastDay.toISOString().split('T')[0];
            break;

        case 'february':
            dateFrom.value = '2026-02-01';
            dateTo.value = '2026-02-28';
            break;

        case 'year':
            dateFrom.value = '2026-01-01';
            dateTo.value = '2026-12-31';
            break;
    }
}
</script>

<?php
echo $OUTPUT->footer();
