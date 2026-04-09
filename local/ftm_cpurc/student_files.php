<?php
/**
 * Student Files Browser - Browse and download files from students' private files.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/student_files.php'));
$PAGE->set_title('File Studenti');
$PAGE->set_heading('File Studenti');
$PAGE->set_pagelayout('standard');

// Parameters.
$search = optional_param('search', '', PARAM_TEXT);
$filesearch = optional_param('filesearch', '', PARAM_TEXT);
$groupcolor = optional_param('group', '', PARAM_ALPHA);
$downloaduserid = optional_param('download_userid', 0, PARAM_INT);
$downloadfile = optional_param('download_file', '', PARAM_RAW);
$downloadzip = optional_param('download_zip', 0, PARAM_INT);
$selectedids = optional_param('selected_ids', '', PARAM_RAW);

// ============================================================
// Handle single file download.
// ============================================================
if ($downloaduserid && $downloadfile) {
    require_sesskey();
    $usercontext = context_user::instance($downloaduserid);
    $fs = get_file_storage();

    // Decode file path.
    $filepath = dirname($downloadfile) . '/';
    $filename = basename($downloadfile);

    $file = $fs->get_file($usercontext->id, 'user', 'private', 0, $filepath, $filename);
    if ($file && !$file->is_directory()) {
        send_stored_file($file, 0, 0, true);
        die();
    }
    throw new moodle_exception('filenotfound');
}

// ============================================================
// Handle ZIP download for selected students.
// ============================================================
if ($downloadzip && $selectedids) {
    require_sesskey();
    $ids = array_filter(array_map('intval', explode(',', $selectedids)));
    if (empty($ids)) {
        throw new moodle_exception('nostudentsselected');
    }

    $fs = get_file_storage();
    $tempdir = make_temp_directory('ftm_student_files_zip');
    $zippath = $tempdir . '/studenti_files_' . date('Ymd_His') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zippath, ZipArchive::CREATE) !== true) {
        throw new moodle_exception('cannotcreatezip');
    }

    $filecount = 0;
    foreach ($ids as $uid) {
        $user = $DB->get_record('user', ['id' => $uid, 'deleted' => 0]);
        if (!$user) continue;

        $usercontext = context_user::instance($uid);
        $files = $fs->get_area_files($usercontext->id, 'user', 'private', 0, 'filepath, filename', false);

        $userfolder = clean_filename($user->lastname . '_' . $user->firstname);

        foreach ($files as $file) {
            if ($file->is_directory()) continue;

            // Apply file search filter if set.
            if ($filesearch && stripos($file->get_filename(), $filesearch) === false) {
                continue;
            }

            $inzip = $userfolder . $file->get_filepath() . $file->get_filename();
            $zip->addFromString($inzip, $file->get_content());
            $filecount++;
        }
    }

    $zip->close();

    if ($filecount === 0) {
        @unlink($zippath);
        redirect($PAGE->url, 'Nessun file trovato per gli studenti selezionati.', null,
            \core\output\notification::NOTIFY_WARNING);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="File_Studenti_' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($zippath));
    readfile($zippath);
    @unlink($zippath);
    die();
}

// ============================================================
// Load students with CPURC data.
// ============================================================
$sql = "SELECT cs.*, u.id as userid, u.firstname, u.lastname, u.email
        FROM {local_ftm_cpurc_students} cs
        JOIN {user} u ON u.id = cs.userid
        WHERE u.deleted = 0
        AND (cs.status IS NULL OR cs.status != 'cancelled')";
$params = [];

if ($search) {
    $sql .= " AND (u.firstname LIKE :fn OR u.lastname LIKE :ln OR u.email LIKE :em)";
    $params['fn'] = '%' . $search . '%';
    $params['ln'] = '%' . $search . '%';
    $params['em'] = '%' . $search . '%';
}

$sql .= " ORDER BY u.lastname ASC, u.firstname ASC";
$students = $DB->get_records_sql($sql, $params);

// Get group info for each student.
foreach ($students as &$st) {
    $group = $DB->get_record_sql("
        SELECT g.color, g.name
        FROM {local_ftm_group_members} gm
        JOIN {local_ftm_groups} g ON g.id = gm.groupid
        WHERE gm.userid = ?
        ORDER BY gm.timecreated DESC LIMIT 1
    ", [$st->userid]);
    $st->group_color = $group ? $group->color : '';
    $st->group_name = $group ? $group->name : '';
}
unset($st);

// Filter by group color.
if ($groupcolor) {
    $students = array_filter($students, function($s) use ($groupcolor) {
        return strtolower($s->group_color) === strtolower($groupcolor);
    });
}

// Get file counts and folder list for each student.
$fs = get_file_storage();
$allFolders = ['/' => true]; // Collect all unique folders across students.
foreach ($students as &$st) {
    $usercontext = context_user::instance($st->userid);
    $files = $fs->get_area_files($usercontext->id, 'user', 'private', 0, 'filepath, filename', false);
    $st->file_count = 0;
    $st->files = [];
    foreach ($files as $file) {
        // Track folders.
        $fp = $file->get_filepath();
        if ($fp !== '/') {
            $allFolders[$fp] = true;
        }
        if ($file->is_directory()) continue;
        // Apply file search filter.
        if ($filesearch && stripos($file->get_filename(), $filesearch) === false) {
            continue;
        }
        $st->file_count++;
        $st->files[] = [
            'filename' => $file->get_filename(),
            'filepath' => $file->get_filepath(),
            'fullpath' => $file->get_filepath() . $file->get_filename(),
            'filesize' => $file->get_filesize(),
            'timemodified' => $file->get_timemodified(),
            'mimetype' => $file->get_mimetype(),
        ];
    }
}
unset($st);
$folderList = array_keys($allFolders);
sort($folderList);

// Check if user can upload (view capability = coach + segreteria + admin).
$canUpload = has_capability('local/ftm_cpurc:view', $context) || is_siteadmin();

// Sort: students with files first.
usort($students, function($a, $b) {
    if ($b->file_count !== $a->file_count) {
        return $b->file_count - $a->file_count;
    }
    return strcmp($a->lastname, $b->lastname);
});

$sesskey = sesskey();
$totalfiles = array_sum(array_column($students, 'file_count'));
$studentswithfiles = count(array_filter($students, function($s) { return $s->file_count > 0; }));

echo $OUTPUT->header();
?>

<style>
.sf-container { max-width: 1200px; margin: 0 auto; }
.sf-filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px; }
.sf-filters input, .sf-filters select { padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; }
.sf-filters input[type="text"] { min-width: 200px; }
.sf-stats { display: flex; gap: 16px; margin-bottom: 20px; }
.sf-stat { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px 20px; flex: 1; text-align: center; }
.sf-stat h3 { margin: 0; font-size: 1.8rem; color: #0066cc; }
.sf-stat small { color: #6c757d; }
.sf-student { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
.sf-student-header { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background 0.2s; }
.sf-student-header:hover { background: #f0f7ff; }
.sf-student-name { font-weight: 600; font-size: 0.95rem; }
.sf-student-meta { font-size: 0.8rem; color: #6c757d; }
.sf-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
.sf-badge-files { background: #e8f5e9; color: #2e7d32; }
.sf-badge-nofiles { background: #fce4ec; color: #c62828; }
.sf-badge-giallo { background: #FFFF00; color: #000; }
.sf-badge-grigio { background: #808080; color: #fff; }
.sf-badge-rosso { background: #FF0000; color: #fff; }
.sf-badge-marrone { background: #996633; color: #fff; }
.sf-badge-viola { background: #7030A0; color: #fff; }
.sf-files { display: none; border-top: 1px solid #dee2e6; background: #fafbfc; }
.sf-files.open { display: block; }
.sf-file-row { display: flex; align-items: center; padding: 8px 16px; border-bottom: 1px solid #eee; font-size: 0.88rem; }
.sf-file-row:hover { background: #f0f7ff; }
.sf-file-icon { width: 28px; text-align: center; font-size: 1.1rem; }
.sf-file-name { flex: 1; font-weight: 500; }
.sf-file-path { color: #6c757d; font-size: 0.8rem; margin-left: 8px; }
.sf-file-size { color: #6c757d; font-size: 0.8rem; width: 80px; text-align: right; }
.sf-file-date { color: #6c757d; font-size: 0.8rem; width: 100px; text-align: right; }
.sf-file-download { width: 40px; text-align: center; }
.sf-file-download a { color: #0066cc; text-decoration: none; font-size: 1.1rem; }
.sf-file-download a:hover { color: #004c99; }
.sf-actions { display: flex; gap: 10px; margin-bottom: 20px; }
</style>

<div class="sf-container">

    <h3 style="margin-bottom: 20px;">File Studenti</h3>

    <!-- Filters -->
    <form method="get" action="" class="sf-filters">
        <input type="text" name="search" placeholder="Cerca studente (nome, cognome, email)..."
               value="<?php echo s($search); ?>">
        <input type="text" name="filesearch" placeholder="Cerca file (es. CVBD, lettera)..."
               value="<?php echo s($filesearch); ?>">
        <select name="group">
            <option value="">Tutti i gruppi</option>
            <?php foreach (['giallo', 'grigio', 'rosso', 'marrone', 'viola'] as $color): ?>
            <option value="<?php echo $color; ?>" <?php echo $groupcolor === $color ? 'selected' : ''; ?>>
                <?php echo ucfirst($color); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtra</button>
        <a href="<?php echo $PAGE->url; ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>

    <!-- Stats -->
    <div class="sf-stats">
        <div class="sf-stat">
            <h3><?php echo count($students); ?></h3>
            <small>Studenti</small>
        </div>
        <div class="sf-stat">
            <h3><?php echo $studentswithfiles; ?></h3>
            <small>Con file</small>
        </div>
        <div class="sf-stat">
            <h3><?php echo $totalfiles; ?></h3>
            <small>File totali<?php echo $filesearch ? ' ("' . s($filesearch) . '")' : ''; ?></small>
        </div>
    </div>

    <!-- Bulk actions -->
    <div class="sf-actions">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleSelectAll()">
            Seleziona/Deseleziona Tutti
        </button>
        <button type="button" class="btn btn-sm btn-success" onclick="downloadSelectedZip()">
            &#11015; Scarica ZIP Selezionati
        </button>
        <?php if ($canUpload): ?>
        <button type="button" class="btn btn-sm btn-info" onclick="toggleUploadPanel()" style="color:#fff;">
            &#11014; Carica File negli Studenti
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="expandAll()">
            Espandi Tutto
        </button>
    </div>

    <?php if ($canUpload): ?>
    <!-- Upload panel -->
    <div id="upload-panel" style="display:none; margin-bottom:20px;">
        <div style="background:#fff; border:2px solid #17a2b8; border-radius:8px; padding:20px;">
            <h5 style="margin:0 0 16px; color:#17a2b8;">&#11014; Carica File nei File Privati degli Studenti</h5>
            <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
                <div style="flex:1; min-width:250px;">
                    <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">File da caricare *</label>
                    <div id="upload-dropzone" style="border:2px dashed #dee2e6; border-radius:6px; padding:20px; text-align:center; cursor:pointer; transition:all 0.2s;"
                         onclick="document.getElementById('upload-file-input').click()">
                        <span style="font-size:1.5rem; display:block;">&#128194;</span>
                        <span id="upload-file-label">Trascina qui o clicca per selezionare</span>
                        <input type="file" id="upload-file-input" style="display:none;"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png"
                               onchange="updateUploadLabel(this)">
                    </div>
                </div>
                <div style="min-width:200px;">
                    <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Cartella destinazione</label>
                    <select id="upload-folder" style="width:100%; padding:8px 12px; border:1px solid #dee2e6; border-radius:6px; font-size:0.9rem;">
                        <option value="/">/ (radice File privati)</option>
                        <?php foreach ($folderList as $folder): ?>
                            <?php if ($folder !== '/'): ?>
                            <option value="<?php echo s($folder); ?>"><?php echo s($folder); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <option value="__new__">+ Nuova cartella...</option>
                    </select>
                    <input type="text" id="upload-newfolder" placeholder="Nome nuova cartella"
                           style="display:none; width:100%; margin-top:6px; padding:8px 12px; border:1px solid #dee2e6; border-radius:6px; font-size:0.9rem;">
                </div>
                <div>
                    <button type="button" class="btn btn-info" onclick="uploadToSelected()" style="color:#fff; padding:10px 24px;">
                        &#11014; Carica nei Selezionati
                    </button>
                </div>
            </div>
            <div id="upload-result" style="display:none; margin-top:16px;"></div>
            <p style="margin:12px 0 0; font-size:0.8rem; color:#6c757d;">
                Seleziona gli studenti con le checkbox, poi clicca "Carica". Il file verra' copiato nei File Privati di ogni studente selezionato nella cartella scelta. I file esistenti con lo stesso nome verranno sovrascritti.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Student list -->
    <?php foreach ($students as $st): ?>
    <div class="sf-student" data-userid="<?php echo $st->userid; ?>">
        <div class="sf-student-header" onclick="toggleFiles(<?php echo $st->userid; ?>)">
            <div>
                <input type="checkbox" class="sf-select-student" value="<?php echo $st->userid; ?>"
                       onclick="event.stopPropagation();" style="margin-right: 8px;">
                <span class="sf-student-name"><?php echo s($st->lastname . ' ' . $st->firstname); ?></span>
                <span class="sf-student-meta">&nbsp;(<?php echo s($st->email); ?>)</span>
                <?php if ($st->group_color): ?>
                <span class="sf-badge sf-badge-<?php echo strtolower($st->group_color); ?>">
                    <?php echo ucfirst($st->group_color); ?>
                </span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($st->file_count > 0): ?>
                <span class="sf-badge sf-badge-files"><?php echo $st->file_count; ?> file</span>
                <?php else: ?>
                <span class="sf-badge sf-badge-nofiles">Nessun file</span>
                <?php endif; ?>
                <span style="font-size: 0.8rem; color: #999; margin-left: 8px;">&#9660;</span>
            </div>
        </div>
        <div class="sf-files" id="files-<?php echo $st->userid; ?>">
            <?php if (empty($st->files)): ?>
                <div style="padding: 16px; text-align: center; color: #999; font-size: 0.9rem;">
                    Nessun file<?php echo $filesearch ? ' corrispondente a "' . s($filesearch) . '"' : ''; ?>
                </div>
            <?php else: ?>
                <?php foreach ($st->files as $f):
                    $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                    $icon = '&#128196;'; // default
                    if (in_array($ext, ['pdf'])) $icon = '&#128211;';
                    if (in_array($ext, ['doc', 'docx'])) $icon = '&#128195;';
                    if (in_array($ext, ['xls', 'xlsx'])) $icon = '&#128202;';
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = '&#128247;';
                    $size = $f['filesize'] > 1048576
                        ? round($f['filesize'] / 1048576, 1) . ' MB'
                        : round($f['filesize'] / 1024, 0) . ' KB';
                    $downloadurl = new moodle_url('/local/ftm_cpurc/student_files.php', [
                        'download_userid' => $st->userid,
                        'download_file' => $f['fullpath'],
                        'sesskey' => $sesskey,
                    ]);
                ?>
                <div class="sf-file-row">
                    <div class="sf-file-icon"><?php echo $icon; ?></div>
                    <div class="sf-file-name">
                        <?php echo s($f['filename']); ?>
                        <?php if ($f['filepath'] !== '/'): ?>
                        <span class="sf-file-path"><?php echo s($f['filepath']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sf-file-size"><?php echo $size; ?></div>
                    <div class="sf-file-date"><?php echo date('d/m/Y', $f['timemodified']); ?></div>
                    <div class="sf-file-download">
                        <a href="<?php echo $downloadurl->out(false); ?>" title="Scarica">&#11015;</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
function toggleFiles(userid) {
    var el = document.getElementById('files-' + userid);
    if (el) el.classList.toggle('open');
}

function expandAll() {
    document.querySelectorAll('.sf-files').forEach(function(el) {
        el.classList.toggle('open');
    });
}

function toggleSelectAll() {
    var checkboxes = document.querySelectorAll('.sf-select-student');
    var allChecked = Array.from(checkboxes).every(function(cb) { return cb.checked; });
    checkboxes.forEach(function(cb) { cb.checked = !allChecked; });
}

function downloadSelectedZip() {
    var selected = [];
    document.querySelectorAll('.sf-select-student:checked').forEach(function(cb) {
        selected.push(cb.value);
    });
    if (selected.length === 0) {
        alert('Seleziona almeno uno studente.');
        return;
    }
    var url = '<?php echo $PAGE->url->out(false); ?>'
        + '?download_zip=1'
        + '&selected_ids=' + selected.join(',')
        + '&filesearch=<?php echo urlencode($filesearch); ?>'
        + '&sesskey=<?php echo $sesskey; ?>';
    window.location.href = url;
}

// ========== UPLOAD FUNCTIONS ==========

function toggleUploadPanel() {
    var panel = document.getElementById('upload-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function updateUploadLabel(input) {
    var label = document.getElementById('upload-file-label');
    if (input.files.length > 0) {
        label.innerHTML = '<strong style="color:#28a745;">' + input.files[0].name + '</strong> (' +
            Math.round(input.files[0].size / 1024) + ' KB)';
    } else {
        label.textContent = 'Trascina qui o clicca per selezionare';
    }
}

// Folder selector: show new folder input.
document.getElementById('upload-folder') && document.getElementById('upload-folder').addEventListener('change', function() {
    var newfolderInput = document.getElementById('upload-newfolder');
    if (this.value === '__new__') {
        newfolderInput.style.display = 'block';
        newfolderInput.focus();
    } else {
        newfolderInput.style.display = 'none';
    }
});

// Drag & drop on upload zone.
(function() {
    var zone = document.getElementById('upload-dropzone');
    if (!zone) return;
    zone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#17a2b8'; this.style.background = '#e8f4f8'; });
    zone.addEventListener('dragleave', function(e) { e.preventDefault(); this.style.borderColor = '#dee2e6'; this.style.background = ''; });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#dee2e6';
        this.style.background = '';
        if (e.dataTransfer.files.length > 0) {
            document.getElementById('upload-file-input').files = e.dataTransfer.files;
            updateUploadLabel(document.getElementById('upload-file-input'));
        }
    });
})();

function uploadToSelected() {
    var fileInput = document.getElementById('upload-file-input');
    if (!fileInput.files.length) {
        alert('Seleziona un file da caricare.');
        return;
    }

    var selected = [];
    document.querySelectorAll('.sf-select-student:checked').forEach(function(cb) {
        selected.push(cb.value);
    });
    if (selected.length === 0) {
        alert('Seleziona almeno uno studente con le checkbox.');
        return;
    }

    var folderSelect = document.getElementById('upload-folder');
    var folder = folderSelect.value;
    if (folder === '__new__') {
        folder = document.getElementById('upload-newfolder').value.trim();
        if (!folder) {
            alert('Inserisci il nome della nuova cartella.');
            return;
        }
    }

    if (!confirm('Caricare "' + fileInput.files[0].name + '" in ' + selected.length + ' studenti nella cartella ' + folder + '?')) {
        return;
    }

    var formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('userids', selected.join(','));
    formData.append('folder', folder);
    formData.append('sesskey', '<?php echo $sesskey; ?>');

    var resultDiv = document.getElementById('upload-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div style="text-align:center; padding:12px; color:#17a2b8;">Caricamento in corso...</div>';

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_upload_student_file.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var html = '<div style="padding:12px; background:#d4edda; border-radius:6px; border:1px solid #c3e6cb;">';
            html += '<strong style="color:#155724;">' + data.message + '</strong>';
            html += '<div style="margin-top:8px; font-size:0.85rem;">';
            data.results.forEach(function(r) {
                var icon = r.success ? '&#10004;' : '&#10060;';
                var color = r.success ? '#155724' : '#721c24';
                html += '<div style="color:' + color + ';">' + icon + ' ' + (r.name || 'ID ' + r.userid) + ' - ' + r.message + '</div>';
            });
            html += '</div></div>';
            resultDiv.innerHTML = html;

            // Reload page after 2 seconds to show updated file counts.
            setTimeout(function() { location.reload(); }, 2500);
        } else {
            resultDiv.innerHTML = '<div style="padding:12px; background:#f8d7da; border-radius:6px; border:1px solid #f5c6cb; color:#721c24;">' +
                '<strong>Errore:</strong> ' + data.message + '</div>';
        }
    })
    .catch(function(err) {
        resultDiv.innerHTML = '<div style="padding:12px; background:#f8d7da; border-radius:6px; border:1px solid #f5c6cb; color:#721c24;">' +
            '<strong>Errore di connessione:</strong> ' + err.message + '</div>';
    });
}
</script>

<?php
echo $OUTPUT->footer();
