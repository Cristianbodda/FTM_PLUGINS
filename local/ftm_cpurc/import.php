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
 * CSV Import page for CPURC data.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:import', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/import.php'));
$PAGE->set_title(get_string('import_title', 'local_ftm_cpurc'));
$PAGE->set_heading(get_string('import_title', 'local_ftm_cpurc'));
$PAGE->set_pagelayout('standard');

// Get available courses for dropdown.
$courses = $DB->get_records_select('course', 'id > 1', null, 'fullname ASC', 'id, fullname');

// Find default course (Rilevamento competenze 2026).
$defaultcourseid = \local_ftm_cpurc\user_manager::find_course('Rilevamento competenze');

echo $OUTPUT->header();
?>

<style>
.cpurc-import {
    max-width: 900px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cpurc-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
}

.cpurc-card h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #333;
}

.cpurc-instructions {
    background: #e8f4fd;
    border-left: 4px solid #0066cc;
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 0 8px 8px 0;
}

.cpurc-instructions ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.cpurc-instructions li {
    margin-bottom: 5px;
}

.drop-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
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
    font-size: 48px;
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

.options-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
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

.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

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
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.preview-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
}

.preview-table tr:hover {
    background: #f8f9fa;
}

.btn-row {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.cpurc-btn {
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

.cpurc-btn-primary {
    background: #0066cc;
    color: white;
}

.cpurc-btn-success {
    background: #28a745;
    color: white;
}

.cpurc-btn-secondary {
    background: #6c757d;
    color: white;
}

.cpurc-btn:hover {
    opacity: 0.9;
}

.cpurc-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

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
    padding: 15px 20px;
    border-radius: 8px;
}

.result-warning {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 15px 20px;
    border-radius: 8px;
}

.result-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px 20px;
    border-radius: 8px;
}

.stats-row {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    padding: 10px 20px;
    background: white;
    border-radius: 6px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
}

.stat-label {
    font-size: 12px;
    color: #666;
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

.credentials-list {
    max-height: 300px;
    overflow-y: auto;
    margin-top: 15px;
    font-size: 13px;
}

.credentials-list table {
    width: 100%;
    border-collapse: collapse;
}

.credentials-list th, .credentials-list td {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.credentials-list th {
    background: #f8f9fa;
    position: sticky;
    top: 0;
}
</style>

<div class="cpurc-import">
    <div class="cpurc-card">
        <h3>📁 <?php echo get_string('import_title', 'local_ftm_cpurc'); ?></h3>

        <div class="cpurc-instructions">
            <strong><?php echo get_string('import_instructions', 'local_ftm_cpurc'); ?></strong>
            <ul>
                <li>File <strong>Excel (.xlsx)</strong> o CSV (separatore punto e virgola)</li>
                <li>69-75 colonne formato CPURC svizzero</li>
                <li>Prima riga: intestazione categorie</li>
                <li>Seconda riga: nomi colonne</li>
            </ul>
        </div>

        <form id="import-form" enctype="multipart/form-data">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

            <div class="drop-zone" id="drop-zone">
                <div class="drop-zone-icon">📄</div>
                <div class="drop-zone-text">
                    Trascina qui il file Excel o CSV oppure <strong>clicca per selezionare</strong>
                </div>
                <div class="drop-zone-file" id="file-name" style="display:none;"></div>
                <input type="file" name="csvfile" id="csvfile" accept=".csv,.xlsx,.xls" style="display:none;">
            </div>
        </form>
    </div>

    <div class="cpurc-card preview-section" id="preview-section">
        <h3>👁 Anteprima (prime 5 righe)</h3>
        <div id="preview-content"></div>
    </div>

    <div class="cpurc-card">
        <h3>⚙️ <?php echo get_string('import_options', 'local_ftm_cpurc'); ?></h3>

        <div class="form-group">
            <label><?php echo get_string('enrol_course', 'local_ftm_cpurc'); ?></label>
            <select name="courseid" id="courseid">
                <option value="">-- Seleziona corso --</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course->id; ?>" <?php echo ($course->id == $defaultcourseid) ? 'selected' : ''; ?>>
                        <?php echo s($course->fullname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="options-grid">
            <div class="option-item">
                <input type="checkbox" name="update_existing" id="update_existing" value="1">
                <label for="update_existing"><?php echo get_string('update_existing', 'local_ftm_cpurc'); ?></label>
            </div>
            <div class="option-item">
                <input type="checkbox" name="enrol_course" id="enrol_course" value="1" checked>
                <label for="enrol_course"><?php echo get_string('enrol_course', 'local_ftm_cpurc'); ?></label>
            </div>
            <div class="option-item">
                <input type="checkbox" name="assign_cohort" id="assign_cohort" value="1" checked>
                <label for="assign_cohort"><?php echo get_string('assign_cohort', 'local_ftm_cpurc'); ?></label>
            </div>
            <div class="option-item">
                <input type="checkbox" name="assign_group" id="assign_group" value="1" checked>
                <label for="assign_group"><?php echo get_string('assign_group', 'local_ftm_cpurc'); ?></label>
            </div>
        </div>

        <div class="btn-row">
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                ← Torna alla Dashboard
            </a>
            <button type="button" id="btn-preview" class="cpurc-btn cpurc-btn-primary" disabled>
                👁 Anteprima
            </button>
            <button type="button" id="btn-import" class="cpurc-btn cpurc-btn-success" disabled>
                📥 <?php echo get_string('import_start', 'local_ftm_cpurc'); ?>
            </button>
        </div>
    </div>

    <div class="import-result" id="import-result">
        <div id="result-content"></div>

        <div class="progress-bar" id="progress-container" style="display:none;">
            <div class="progress-fill" id="progress-fill"></div>
        </div>

        <div id="credentials-section" style="display:none;">
            <h4 style="margin-top:20px;">Credenziali Utenti</h4>
            <div class="credentials-list" id="credentials-list"></div>
            <div style="display:flex; gap:12px; margin-top:15px;">
                <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/download_credentials.php?sesskey=<?php echo sesskey(); ?>"
                   id="btn-download-excel" class="cpurc-btn cpurc-btn-success" style="display:none; text-decoration:none;">
                    Scarica Excel Credenziali (LADI)
                </a>
                <button type="button" id="btn-download-credentials" class="cpurc-btn cpurc-btn-primary">
                    Scarica CSV Credenziali
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('csvfile');
    const fileName = document.getElementById('file-name');
    const btnPreview = document.getElementById('btn-preview');
    const btnImport = document.getElementById('btn-import');
    const previewSection = document.getElementById('preview-section');
    const previewContent = document.getElementById('preview-content');
    const importResult = document.getElementById('import-result');
    const resultContent = document.getElementById('result-content');

    let selectedFile = null;
    let importedCredentials = [];

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
        var name = file.name.toLowerCase();
        if (!name.endsWith('.csv') && !name.endsWith('.xlsx') && !name.endsWith('.xls')) {
            alert('Seleziona un file Excel (.xlsx) o CSV (.csv)');
            return;
        }

        selectedFile = file;
        fileName.textContent = '✅ ' + file.name + ' (' + formatSize(file.size) + ')';
        fileName.style.display = 'block';
        dropZone.classList.add('has-file');
        btnPreview.disabled = false;
        btnImport.disabled = false;
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Preview button
    btnPreview.addEventListener('click', async () => {
        if (!selectedFile) return;

        btnPreview.disabled = true;
        btnPreview.innerHTML = '⏳ Caricamento...';

        const formData = new FormData();
        formData.append('sesskey', '<?php echo sesskey(); ?>');
        formData.append('action', 'preview');
        formData.append('csvfile', selectedFile);

        try {
            const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_import.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showPreview(data.data);
            } else {
                alert('Errore: ' + data.message);
            }
        } catch (error) {
            alert('Errore di connessione');
            console.error(error);
        }

        btnPreview.disabled = false;
        btnPreview.innerHTML = '👁 Anteprima';
    });

    function showPreview(rows) {
        if (!rows || rows.length === 0) {
            previewContent.innerHTML = '<p>Nessuna riga trovata nel file.</p>';
            previewSection.classList.add('active');
            return;
        }

        let html = '<table class="preview-table"><thead><tr>';
        html += '<th>#</th><th>Nome</th><th>Cognome</th><th>Email</th><th>URC</th><th>Professione</th><th>Settore</th>';
        html += '</tr></thead><tbody>';

        rows.forEach((row, i) => {
            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td>' + escapeHtml(row.firstname || '') + '</td>';
            html += '<td>' + escapeHtml(row.lastname || '') + '</td>';
            html += '<td>' + escapeHtml(row.email || '') + '</td>';
            html += '<td>' + escapeHtml(row.urc_office || '') + '</td>';
            html += '<td>' + escapeHtml(row.last_profession || '').substring(0, 50) + '</td>';
            html += '<td>' + escapeHtml(row.sector_detected || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<p style="margin-top:10px;color:#666;">Trovate ' + rows.length + ' righe (anteprima prime 5)</p>';

        previewContent.innerHTML = html;
        previewSection.classList.add('active');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Import button
    btnImport.addEventListener('click', async () => {
        if (!selectedFile) return;

        if (!confirm('Sei sicuro di voler importare il file? Questa operazione creera nuovi utenti Moodle.')) {
            return;
        }

        btnImport.disabled = true;
        btnImport.innerHTML = '⏳ Importazione in corso...';

        importResult.classList.add('active');
        resultContent.innerHTML = '<div class="result-warning">⏳ Importazione in corso...</div>';

        const formData = new FormData();
        formData.append('sesskey', '<?php echo sesskey(); ?>');
        formData.append('action', 'import');
        formData.append('csvfile', selectedFile);
        formData.append('courseid', document.getElementById('courseid').value);
        formData.append('update_existing', document.getElementById('update_existing').checked ? '1' : '0');
        formData.append('enrol_course', document.getElementById('enrol_course').checked ? '1' : '0');
        formData.append('assign_cohort', document.getElementById('assign_cohort').checked ? '1' : '0');
        formData.append('assign_group', document.getElementById('assign_group').checked ? '1' : '0');

        try {
            const response = await fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_import.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showImportResult(data);
            } else {
                resultContent.innerHTML = '<div class="result-error">❌ Errore: ' + escapeHtml(data.message) + '</div>';
            }
        } catch (error) {
            resultContent.innerHTML = '<div class="result-error">❌ Errore di connessione</div>';
            console.error(error);
        }

        btnImport.disabled = false;
        btnImport.innerHTML = '📥 <?php echo get_string('import_start', 'local_ftm_cpurc'); ?>';
    });

    function showImportResult(data) {
        const stats = data.stats;
        const hasErrors = stats.errors > 0;

        let html = '<div class="' + (hasErrors ? 'result-warning' : 'result-success') + '">';
        html += hasErrors ? '⚠️ Importazione completata con alcuni errori' : '✅ Importazione completata con successo!';
        html += '</div>';

        html += '<div class="stats-row">';
        html += '<div class="stat-item"><div class="stat-number">' + stats.total + '</div><div class="stat-label">Totali</div></div>';
        html += '<div class="stat-item" style="color:#28a745"><div class="stat-number">' + stats.imported + '</div><div class="stat-label">Creati</div></div>';
        html += '<div class="stat-item" style="color:#0066cc"><div class="stat-number">' + stats.updated + '</div><div class="stat-label">Aggiornati</div></div>';
        html += '<div class="stat-item" style="color:#dc3545"><div class="stat-number">' + stats.errors + '</div><div class="stat-label">Errori</div></div>';
        html += '</div>';

        resultContent.innerHTML = html;

        // Show credentials for all processed users.
        if (data.credentials && data.credentials.length > 0) {
            importedCredentials = data.credentials;
            showCredentials(data.credentials);
            // Show Excel download button.
            document.getElementById('btn-download-excel').style.display = 'inline-flex';
        }
    }

    function showCredentials(credentials) {
        const section = document.getElementById('credentials-section');
        const list = document.getElementById('credentials-list');

        let html = '<table><thead><tr><th>#</th><th>Nome</th><th>Cognome</th><th>Localita</th><th>E-mail</th><th>N. Personale</th><th>Formatore</th><th>Username</th><th>Password</th><th>Gruppo</th><th>Stato</th></tr></thead><tbody>';

        credentials.forEach((cred, i) => {
            const isNew = cred.created;
            html += '<tr style="' + (isNew ? 'background:#d4edda;' : '') + '">';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td>' + escapeHtml(cred.firstname) + '</td>';
            html += '<td><strong>' + escapeHtml(cred.lastname) + '</strong></td>';
            html += '<td>' + escapeHtml(cred.city || '') + '</td>';
            html += '<td>' + escapeHtml(cred.email) + '</td>';
            html += '<td>' + escapeHtml(cred.personal_number || '') + '</td>';
            html += '<td>' + escapeHtml(cred.trainer || '') + '</td>';
            html += '<td><code>' + escapeHtml(cred.username) + '</code></td>';
            html += '<td><code>' + escapeHtml(cred.password) + '</code></td>';
            html += '<td>' + escapeHtml(cred.group || '') + '</td>';
            html += '<td>' + (isNew ? '<span style="color:#28a745;font-weight:600;">Nuovo</span>' : '<span style="color:#0066cc;">Esistente</span>') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        list.innerHTML = html;
        section.style.display = 'block';
    }

    // Download credentials CSV
    document.getElementById('btn-download-credentials').addEventListener('click', () => {
        if (importedCredentials.length === 0) return;

        let csv = 'Nome;Cognome;Username;Password\n';
        importedCredentials.forEach(cred => {
            csv += cred.firstname + ';' + cred.lastname + ';' + cred.username + ';' + cred.password + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'credenziali_cpurc_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
    });
});
</script>

<?php
echo $OUTPUT->footer();
