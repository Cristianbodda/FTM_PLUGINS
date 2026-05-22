<?php
/**
 * Autocandidature (student targets) page — coach view.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/target_manager.php');
require_once(__DIR__ . '/classes/company_manager.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:managetargets', $context);

$userid = required_param('userid', PARAM_INT);

global $DB, $USER;

// Guard: DB tables must exist (run Admin → Notifiche first).
if (!$DB->get_manager()->table_exists('local_jobmatch_ticino_companies') ||
    !$DB->get_manager()->table_exists('local_jobmatch_student_targets')) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/jobmatchagent/student_targets.php'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        'Tabelle DB mancanti. Vai su <a href="' .
        (new moodle_url('/admin/index.php'))->out(false) .
        '">Amministrazione → Notifiche</a> per eseguire l\'upgrade.',
        'error'
    );
    echo $OUTPUT->footer();
    die();
}

// Load student.
$student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

// Verify coach owns this student (or siteadmin).
if (!is_siteadmin()) {
    $assigned = $DB->record_exists_sql(
        "SELECT 1 FROM {local_student_coaching}
          WHERE studentid = :uid
            AND coachid = :cid
            AND status = 'active'",
        ['uid' => $userid, 'cid' => $USER->id]
    );
    if (!$assigned) {
        throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/student_targets.php', ['userid' => $userid]));
$PAGE->set_title(get_string('st_title', 'local_jobmatchagent', fullname($student)));
$PAGE->set_heading(get_string('st_title', 'local_jobmatchagent', fullname($student)));
$PAGE->set_pagelayout('admin');

// Collect student sector from local_student_sectors.
$student_sector = '';
try {
    if ($DB->get_manager()->table_exists('local_student_sectors')) {
        $sector_rec = $DB->get_record_sql(
            "SELECT sector FROM {local_student_sectors} WHERE userid = :uid AND is_primary = 1",
            ['uid' => $userid], IGNORE_MISSING
        );
        if ($sector_rec) {
            $student_sector = $sector_rec->sector;
        }
    }
} catch (\Throwable $e) {
    // Non-critical — continue without sector.
}
$student_sector_display = $student_sector ?: '—';

// Load CV info for the AI suggest modal.
require_once(__DIR__ . '/classes/matcher.php');
$cv_for_modal = '';
$cv_source_label = '';
try {
    $cvres = \local_jobmatchagent\matcher::resolve_cv($userid);
    if (!empty($cvres['text'])) {
        $cv_for_modal   = $cvres['text'];
        $cv_source_label = ($cvres['source'] === 'manual') ? 'manuale' : 'da JobAIDA';
    }
} catch (\Throwable $e) {
    // Non-critical.
}

// Collect assigned coach name.
$coach_name = '—';
try {
    if ($DB->get_manager()->table_exists('local_student_coaching')) {
        $coach_rec = $DB->get_record_sql(
            "SELECT u.id, u.firstname, u.lastname
               FROM {local_student_coaching} sc
               JOIN {user} u ON u.id = sc.coachid
              WHERE sc.studentid = :uid AND sc.status = 'active'",
            ['uid' => $userid], IGNORE_MISSING
        );
        if ($coach_rec) {
            $coach_name = fullname($coach_rec);
        }
    }
} catch (\Throwable $e) {
    // Non-critical — continue without coach name.
}

// Check CI active (local_ftm_sip_enrollments if plugin present).
$ci_active = false;
try {
    if ($DB->get_manager()->table_exists('local_ftm_sip_enrollments')) {
        $ci_active = $DB->record_exists_sql(
            "SELECT 1 FROM {local_ftm_sip_enrollments} WHERE userid = :uid AND status = 'active'",
            ['uid' => $userid]
        );
    }
} catch (\Throwable $e) {
    // Non-critical.
}

// Student view enabled?
$sv_enabled = \local_jobmatchagent\target_manager::student_view_enabled($userid);

// Load targets.
try {
    $targets = \local_jobmatchagent\target_manager::get_student_targets($userid);
} catch (\Throwable $e) {
    $targets = [];
}

// Status badge map.
$status_badge = [
    'pending'          => ['label' => get_string('st_status_pending', 'local_jobmatchagent'),          'class' => 'bg-secondary'],
    'lettera_generata' => ['label' => get_string('st_status_lettera_generata', 'local_jobmatchagent'), 'class' => 'bg-warning text-dark'],
    'inviata'          => ['label' => get_string('st_status_inviata', 'local_jobmatchagent'),          'class' => 'bg-primary'],
    'risposta'         => ['label' => get_string('st_status_risposta', 'local_jobmatchagent'),         'class' => 'bg-info text-dark'],
    'colloquio'        => ['label' => get_string('st_status_colloquio', 'local_jobmatchagent'),        'class' => 'bg-orange text-dark'],
    'assunto'          => ['label' => get_string('st_status_assunto', 'local_jobmatchagent'),          'class' => 'bg-success'],
    'rifiutato'        => ['label' => get_string('st_status_rifiutato', 'local_jobmatchagent'),        'class' => 'bg-danger'],
];

echo $OUTPUT->header();
?>

<style>
:root {
    --primary: #0066cc;
    --success: #28a745;
    --danger: #dc3545;
    --warning-y: #EAB308;
    --orange: #fd7e14;
    --border: #dee2e6;
}
.bg-orange { background-color: var(--orange) !important; }
.target-card {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: #fff;
    transition: box-shadow 0.15s;
}
.target-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.10); }
.target-card .tc-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.target-card .tc-company-name {
    font-size: 1.05rem;
    font-weight: 600;
    color: #212529;
}
.target-card .tc-meta {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 0.15rem;
}
.target-card .tc-actions {
    margin-left: auto;
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    align-items: flex-start;
}
.note-area {
    width: 100%;
    min-height: 60px;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 0.4rem 0.6rem;
    font-size: 0.875rem;
    resize: vertical;
}
.status-select {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    border: 1px solid var(--border);
    min-width: 140px;
}
.sip-badge {
    font-size: 0.75rem;
    background: #e7f5ea;
    color: #155724;
    border: 1px solid #b7dfbb;
    border-radius: 4px;
    padding: 0.15rem 0.5rem;
    display: inline-block;
    margin-top: 0.25rem;
}
.info-bar {
    background: #f8f9fa;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.info-bar strong { color: #343a40; }
/* FTM custom modals */
.ftm-modal-backdrop {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;
}
.ftm-modal-backdrop.open { display: flex; }
.ftm-modal {
    background: #fff; border-radius: 12px; width: 640px; max-width: 96vw;
    max-height: 90vh; overflow-y: auto; padding: 0; position: relative;
}
.ftm-modal-header {
    background: #0066cc; color: #fff; padding: 16px 20px;
    border-radius: 12px 12px 0 0; display: flex; align-items: center; gap: 10px;
}
.ftm-modal-header h5 { margin: 0; font-size: 16px; font-weight: 700; flex: 1; }
.ftm-modal-body { padding: 20px; }
.ftm-modal-footer { padding: 12px 20px; border-top: 1px solid #dee2e6; display: flex; gap: 8px; justify-content: flex-end; }
.ftm-modal-sm { width: 420px; }
.close-btn { background: none; border: none; color: #fff; font-size: 22px; cursor: pointer; line-height: 1; padding: 0; }
#company-search-results { max-height: 240px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; min-height: 52px; }
.company-result-item {
    padding: 0.55rem 0.75rem; border-bottom: 1px solid #f0f0f0;
    cursor: pointer; transition: background 0.1s;
}
.company-result-item:hover { background: #f0f4ff; }
.company-result-item.selected { background: #d4e8ff; }
.company-result-item .cr-name { font-weight: 600; font-size: 0.9rem; }
.company-result-item .cr-meta { font-size: 0.78rem; color: #6c757d; }
.form-label-sm { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
.ftm-input { width: 100%; border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; font-size: 13px; }
.ftm-select { border: 1px solid #dee2e6; border-radius: 5px; padding: 6px 10px; font-size: 13px; width: 100%; }
.ftm-textarea { width: 100%; border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; font-size: 13px; min-height: 70px; resize: vertical; }
.alert-selected { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 6px; padding: 8px 12px; margin: 8px 0; }
.btn-ftm-primary { background: #0066cc; color: #fff; border: none; border-radius: 6px; padding: 9px 20px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-ftm-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-ftm-primary:hover:not(:disabled) { background: #0052a3; }
.btn-ftm-secondary { background: #6c757d; color: #fff; border: none; border-radius: 6px; padding: 9px 20px; cursor: pointer; font-size: 14px; }
#toggle-sv-badge { font-size: 0.9rem; padding: 0.4rem 0.75rem; border-radius: 6px; cursor: pointer; display: inline-block; color: #fff; }
/* AI suggest */
.suggest-card { border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.6rem; background: #fff; transition: box-shadow 0.15s; }
.suggest-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.suggest-card-header { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem; }
.suggest-company-name { font-weight: 700; font-size: 1rem; flex: 1; }
.suggest-score { background: #0066cc; color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
.suggest-meta { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.4rem; font-size: 0.83rem; color: #6c757d; }
.suggest-motivo { font-size: 0.88rem; color: #343a40; margin-bottom: 0.5rem; font-style: italic; }
.suggest-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.ai-loading { text-align: center; padding: 2.5rem; color: #6c757d; }
.ai-spinner { display: inline-block; width: 34px; height: 34px; border: 3px solid #dee2e6; border-top-color: #0066cc; border-radius: 50%; animation: ftm-spin 0.8s linear infinite; }
@keyframes ftm-spin { to { transform: rotate(360deg); } }
.cv-source-badge { font-size: 0.78rem; background: #e7f5ea; color: #155724; border-radius: 4px; padding: 2px 8px; }
.cv-warn-badge { font-size: 0.78rem; background: #fff3cd; color: #856404; border-radius: 4px; padding: 2px 8px; }
/* Company detail modal */
.detail-label { font-size: 0.75rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.detail-value { font-size: 0.95rem; color: #212529; margin-bottom: 0.75rem; }
.detail-description { font-size: 0.9rem; line-height: 1.6; color: #343a40; background: #f8f9fa; border-radius: 6px; padding: 0.75rem 1rem; margin-top: 0.5rem; border-left: 3px solid #0066cc; }
</style>

<?php
// Header info bar.
echo '<div class="info-bar">';
echo '<div><strong>' . get_string('st_info_sector', 'local_jobmatchagent') . ':</strong> ' . s($student_sector_display) . '</div>';
echo '<div><strong>' . get_string('st_info_coach', 'local_jobmatchagent') . ':</strong> ' . s($coach_name) . '</div>';
echo '<div><strong>CI:</strong> ';
if ($ci_active) {
    echo '<span class="badge bg-success">' . get_string('st_ci_active', 'local_jobmatchagent') . '</span>';
} else {
    echo '<span class="badge bg-secondary">' . get_string('st_ci_inactive', 'local_jobmatchagent') . '</span>';
}
echo '</div>';

// Student view toggle.
echo '<div id="toggle-sv" class="ms-auto d-flex align-items-center gap-2">';
echo '<span class="fw-semibold" style="font-size:0.9rem">' . get_string('st_student_view', 'local_jobmatchagent') . ':</span>';
$sv_class = $sv_enabled ? 'bg-success' : 'bg-secondary';
$sv_text  = $sv_enabled ? get_string('st_sv_on', 'local_jobmatchagent') : get_string('st_sv_off', 'local_jobmatchagent');
echo '<span class="badge ' . $sv_class . '" id="sv-badge" onclick="toggleStudentView()" title="' . get_string('st_sv_toggle_help', 'local_jobmatchagent') . '">' . $sv_text . '</span>';
echo '</div>';
echo '</div>';

// --- Pannello Profilo Candidato ---
$cv_word_count = !empty($cv_for_modal) ? str_word_count($cv_for_modal) : 0;
?>
<div id="profile-panel" style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1rem">
    <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none"
         onclick="toggleProfilePanel()">
        <div style="font-weight:700;font-size:0.95rem;color:#212529">
            &#128100; Profilo candidato
            <?php if (empty($student_sector)): ?>
                <span class="badge bg-warning text-dark ms-2" style="font-size:0.72rem">Settore mancante</span>
            <?php endif; ?>
            <?php if (empty($cv_for_modal)): ?>
                <span class="badge bg-warning text-dark ms-2" style="font-size:0.72rem">CV mancante</span>
            <?php endif; ?>
        </div>
        <span id="profile-panel-arrow" style="font-size:1.1rem;color:#6c757d">&#9650;</span>
    </div>
    <div id="profile-panel-body" style="margin-top:0.9rem">
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap">
            <!-- Settore -->
            <div style="flex:0 0 220px">
                <label class="form-label-sm" style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:4px">
                    Settore candidato
                </label>
                <div style="display:flex;gap:6px;align-items:center">
                    <select id="setup-sector-select" class="ftm-select" style="flex:1"
                            onchange="saveSector(this.value)">
                        <option value="">— non impostato —</option>
                        <?php
                        $sectors_list = ['AUTOMOBILE','AUTOMAZIONE','CHIMFARM','ELETTRICITA','LOGISTICA','MECCANICA','METALCOSTRUZIONE'];
                        foreach ($sectors_list as $sec) {
                            $sel = ($student_sector === $sec) ? 'selected' : '';
                            echo '<option value="' . s($sec) . '" ' . $sel . '>' . s($sec) . '</option>';
                        }
                        ?>
                    </select>
                    <span id="sector-saved-icon" style="display:none;color:#28a745;font-size:1.1rem">&#10003;</span>
                </div>
            </div>

            <!-- CV -->
            <div style="flex:1;min-width:260px">
                <label class="form-label-sm" style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:4px">
                    CV del candidato
                    <?php if ($cv_word_count > 0): ?>
                        <span class="cv-source-badge" style="margin-left:6px">
                            &#10003; <?php echo $cv_word_count; ?> parole &mdash; <?php echo s($cv_source_label); ?>
                        </span>
                    <?php else: ?>
                        <span class="cv-warn-badge" style="margin-left:6px">&#9888; Nessun CV caricato</span>
                    <?php endif; ?>
                </label>
                <textarea id="setup-cv-textarea" class="ftm-textarea" style="min-height:90px;font-size:0.8rem"
                    placeholder="Incolla qui il testo del CV (da Word, PDF convertito, ecc.)..."><?php echo s($cv_for_modal); ?></textarea>
                <div style="display:flex;align-items:center;gap:8px;margin-top:5px">
                    <button class="btn btn-sm btn-primary" onclick="saveCv()" id="btn-save-cv">
                        &#128190; Salva CV
                    </button>
                    <span id="cv-saved-msg" style="display:none;font-size:0.82rem;color:#28a745;font-weight:600">&#10003; CV salvato</span>
                    <span style="font-size:0.75rem;color:#6c757d" id="cv-word-counter">
                        <?php echo $cv_word_count > 0 ? $cv_word_count . ' parole' : ''; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// Add target button.
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h5 class="mb-0">' . get_string('st_targets_list', 'local_jobmatchagent') . ' <span class="badge bg-secondary">' . count($targets) . '</span></h5>';
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-outline-primary" onclick="openAISuggestModal()">'
    . '&#129302; ' . get_string('st_ai_suggest', 'local_jobmatchagent') . '</button>';
echo '<button class="btn btn-success" onclick="openAddModal()">'
    . '+ ' . get_string('st_add_target', 'local_jobmatchagent') . '</button>';
echo '</div>';
echo '</div>';

// Targets list.
if (empty($targets)) {
    echo '<div class="alert alert-light border text-muted text-center py-4">'
        . get_string('st_no_targets', 'local_jobmatchagent') . '</div>';
} else {
    echo '<div id="targets-list">';
    foreach ($targets as $t) {
        $st = $t->status ?? 'pending';
        $badge_info = $status_badge[$st] ?? $status_badge['pending'];
        $jobaida_url = new moodle_url('/local/jobaida/index.php', [
            'userid'         => $userid,
            'company_name'   => $t->company_nome,
            'company_sector' => $t->company_settore_ftm,
            'company_note'   => $t->note_per_ai ?? '',
            'source'         => 'jobmatch',
            'target_id'      => $t->id,
        ]);
        ?>
        <div class="target-card" id="target-<?php echo (int)$t->id; ?>">
            <div class="tc-header">
                <div style="flex:1; min-width:0">
                    <div class="tc-company-name"><?php echo s($t->company_nome); ?></div>
                    <div class="tc-meta">
                        <?php if (!empty($t->company_settore_ftm)): ?>
                            <span class="badge bg-info text-dark me-1"><?php echo s($t->company_settore_ftm); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t->company_localita)): ?>
                            <span><?php echo s($t->company_localita); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t->company_dimensione) && $t->company_dimensione !== 'unknown'): ?>
                            <span class="ms-2 text-muted"><?php echo s($t->company_dimensione); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($t->sip_entry_id)): ?>
                        <div class="sip-badge">&#10003; <?php echo get_string('st_registered_ci', 'local_jobmatchagent'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="tc-actions">
                    <select class="status-select" onchange="updateStatus(<?php echo (int)$t->id; ?>, this)"
                            data-current="<?php echo s($st); ?>">
                        <?php foreach ($status_badge as $skey => $sinfo): ?>
                            <option value="<?php echo $skey; ?>" <?php echo ($st === $skey) ? 'selected' : ''; ?>>
                                <?php echo s($sinfo['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="badge <?php echo $badge_info['class']; ?>" id="status-badge-<?php echo (int)$t->id; ?>">
                        <?php echo $badge_info['label']; ?>
                    </span>
                </div>
            </div>
            <div class="mt-2">
                <label class="form-label mb-1" style="font-size:0.8rem;color:#6c757d">
                    <?php echo get_string('st_note_per_ai', 'local_jobmatchagent'); ?>
                </label>
                <textarea class="note-area" id="note-<?php echo (int)$t->id; ?>"
                    data-target-id="<?php echo (int)$t->id; ?>"
                    onblur="saveNote(<?php echo (int)$t->id; ?>)"><?php echo s($t->note_per_ai ?? ''); ?></textarea>
            </div>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-primary"
                        data-nome="<?php echo s($t->company_nome); ?>"
                        data-localita="<?php echo s($t->company_localita ?? ''); ?>"
                        data-settore-ftm="<?php echo s($t->company_settore_ftm ?? ''); ?>"
                        data-settore-raw="<?php echo s($t->company_settore_raw ?? ''); ?>"
                        data-target-id="<?php echo (int)$t->id; ?>"
                        onclick="generateLetterFromCard(this)">
                    &#9993; <?php echo get_string('st_btn_generate_letter', 'local_jobmatchagent'); ?>
                </button>
                <button class="btn btn-sm btn-outline-info"
                        onclick="openCompanyDetail(<?php echo (int)$t->id; ?>, '<?php echo s($t->company_nome); ?>', '<?php echo s($t->company_website ?? ''); ?>', <?php echo (int)$t->company_id; ?>)">
                    &#8505; <?php echo get_string('st_btn_detail', 'local_jobmatchagent'); ?>
                </button>
                <?php if ($st !== 'inviata' && $st !== 'risposta' && $st !== 'colloquio' && $st !== 'assunto' && $st !== 'rifiutato'): ?>
                <button class="btn btn-sm btn-outline-success" onclick="confirmSent(<?php echo (int)$t->id; ?>)">
                    &#10003; <?php echo get_string('st_btn_confirm_sent', 'local_jobmatchagent'); ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteTarget(<?php echo (int)$t->id; ?>)">
                    <?php echo get_string('st_btn_delete', 'local_jobmatchagent'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}
?>

<!-- Add Target Modal -->
<div class="ftm-modal-backdrop" id="modal-add-target" onclick="if(event.target===this)closeModal('modal-add-target')">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h5><?php echo get_string('st_modal_title', 'local_jobmatchagent'); ?></h5>
            <button class="close-btn" onclick="closeModal('modal-add-target')">&times;</button>
        </div>
        <div class="ftm-modal-body">
            <div style="display:flex;gap:12px;margin-bottom:12px">
                <div style="flex:2">
                    <label class="form-label-sm"><?php echo get_string('st_modal_search', 'local_jobmatchagent'); ?></label>
                    <input type="text" id="company-search-input" class="ftm-input"
                           placeholder="<?php echo get_string('st_modal_search_placeholder', 'local_jobmatchagent'); ?>"
                           oninput="searchCompanies()">
                </div>
                <div style="flex:1">
                    <label class="form-label-sm"><?php echo get_string('st_modal_filter_sector', 'local_jobmatchagent'); ?></label>
                    <select id="company-sector-filter" class="ftm-select" onchange="searchCompanies()">
                        <option value=""><?php echo get_string('st_modal_all_sectors', 'local_jobmatchagent'); ?></option>
                        <?php
                        $sectors = ['AUTOMOBILE','AUTOMAZIONE','CHIMFARM','ELETTRICITA','LOGISTICA','MECCANICA','METALCOSTRUZIONE','ALTRO'];
                        foreach ($sectors as $sec) {
                            echo '<option value="' . s($sec) . '">' . s($sec) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div id="company-search-results" style="margin-bottom:12px">
                <div class="text-muted text-center py-3" id="search-placeholder">
                    <?php echo get_string('st_modal_type_to_search', 'local_jobmatchagent'); ?>
                </div>
            </div>

            <div id="selected-company-info" style="display:none" class="alert-selected">
                <strong><?php echo get_string('st_modal_selected', 'local_jobmatchagent'); ?>:</strong>
                <span id="selected-company-name"></span>
                <input type="hidden" id="selected-company-id" value="">
            </div>

            <div style="margin-top:12px">
                <label class="form-label-sm"><?php echo get_string('st_note_per_ai', 'local_jobmatchagent'); ?></label>
                <textarea id="modal-note-per-ai" class="ftm-textarea"
                          placeholder="<?php echo get_string('st_note_per_ai_placeholder', 'local_jobmatchagent'); ?>"></textarea>
                <div style="font-size:12px;color:#6c757d;margin-top:4px"><?php echo get_string('st_note_per_ai_help', 'local_jobmatchagent'); ?></div>
            </div>
        </div>
        <div class="ftm-modal-footer">
            <button class="btn-ftm-secondary" onclick="closeModal('modal-add-target')">
                <?php echo get_string('cancel', 'core'); ?>
            </button>
            <button class="btn-ftm-primary" onclick="addTarget()" id="btn-add-target" disabled>
                <?php echo get_string('st_btn_add', 'local_jobmatchagent'); ?>
            </button>
        </div>
    </div>
</div>

<!-- AI Suggest Modal -->
<div class="ftm-modal-backdrop" id="modal-ai-suggest" onclick="if(event.target===this)closeModal('modal-ai-suggest')">
    <div class="ftm-modal" style="width:740px">
        <div class="ftm-modal-header">
            <h5>&#129302; <?php echo get_string('st_ai_suggest_title', 'local_jobmatchagent'); ?></h5>
            <button class="close-btn" onclick="closeModal('modal-ai-suggest')">&times;</button>
        </div>
        <div class="ftm-modal-body">
            <div style="font-size:0.85rem;color:#6c757d;margin-bottom:0.75rem;background:#f8f9fa;border-radius:6px;padding:0.5rem 0.75rem">
                Settore: <strong id="modal-sector-display"><?php echo s($student_sector ?: '— non impostato'); ?></strong>
                &nbsp;|&nbsp;
                CV: <strong><?php echo $cv_word_count > 0 ? $cv_word_count . ' parole' : '— non presente'; ?></strong>
                &nbsp;<a href="#" onclick="closeModal('modal-ai-suggest');setTimeout(()=>{document.getElementById('profile-panel-body').style.display='';document.getElementById('profile-panel-arrow').textContent='▲';document.getElementById('setup-sector-select').focus();},200);return false;" style="font-size:0.8rem">
                    &#9998; Modifica
                </a>
            </div>

            <div id="ai-suggest-loading" class="ai-loading" style="display:none">
                <div class="ai-spinner"></div>
                <div class="mt-2"><?php echo get_string('st_ai_suggest_loading', 'local_jobmatchagent'); ?></div>
            </div>
            <div id="ai-suggest-error" style="display:none">
                <div class="alert alert-danger mb-0" id="ai-suggest-error-msg"></div>
            </div>
            <div id="ai-suggest-meta" style="display:none;margin-bottom:0.75rem;font-size:0.84rem;color:#6c757d;gap:0.6rem;align-items:center;flex-wrap:wrap"></div>
            <div id="ai-suggest-results" style="display:none;max-height:44vh;overflow-y:auto;padding-right:4px"></div>
        </div>
        <div class="ftm-modal-footer">
            <button class="btn-ftm-secondary" onclick="closeModal('modal-ai-suggest')">Chiudi</button>
            <button class="btn-ftm-primary" onclick="runAISuggest()" id="btn-run-suggest">
                &#129302; Suggerisci aziende
            </button>
        </div>
    </div>
</div>

<!-- Company Detail Modal -->
<div class="ftm-modal-backdrop" id="modal-company-detail" onclick="if(event.target===this)closeModal('modal-company-detail')">
    <div class="ftm-modal" style="width:640px">
        <div class="ftm-modal-header">
            <h5 id="detail-modal-title">&#8505; <?php echo get_string('st_detail_title', 'local_jobmatchagent'); ?></h5>
            <button class="close-btn" onclick="closeModal('modal-company-detail')">&times;</button>
        </div>
        <div class="ftm-modal-body">
            <div id="detail-loading" class="ai-loading">
                <div class="ai-spinner"></div>
                <div class="mt-2"><?php echo get_string('st_detail_loading', 'local_jobmatchagent'); ?></div>
            </div>
            <div id="detail-error" style="display:none">
                <div class="alert alert-warning mb-0" id="detail-error-msg"></div>
            </div>
            <div id="detail-content" style="display:none"></div>
        </div>
        <div class="ftm-modal-footer" style="flex-wrap:wrap;gap:6px">
            <a id="detail-website-link" href="#" target="_blank" rel="noopener" class="btn-ftm-secondary" style="text-decoration:none;display:none">Sito web &#8599;</a>
            <button id="btn-use-context" class="btn-ftm-secondary" onclick="useAsLetterContext()" style="display:none;background:#17a2b8">
                &#128203; Imposta contesto lettera
            </button>
            <button id="btn-generate-letter-detail" class="btn-ftm-primary" onclick="generateLetterWithContext()" style="display:none">
                &#9993; Genera lettera di autocandidatura
            </button>
            <button class="btn-ftm-secondary" onclick="closeModal('modal-company-detail')" style="margin-left:auto">Chiudi</button>
        </div>
    </div>
</div>

<!-- Status Note Modal -->
<div class="ftm-modal-backdrop" id="modal-status-note" onclick="if(event.target===this)cancelStatusChange()">
    <div class="ftm-modal ftm-modal-sm">
        <div class="ftm-modal-header">
            <h5><?php echo get_string('st_modal_note_esito', 'local_jobmatchagent'); ?></h5>
            <button class="close-btn" onclick="cancelStatusChange()">&times;</button>
        </div>
        <div class="ftm-modal-body">
            <textarea id="note-esito-input" class="ftm-textarea"
                placeholder="<?php echo get_string('st_note_esito_placeholder', 'local_jobmatchagent'); ?>"></textarea>
        </div>
        <div class="ftm-modal-footer">
            <button class="btn-ftm-secondary" onclick="cancelStatusChange()">
                <?php echo get_string('cancel', 'core'); ?>
            </button>
            <button class="btn-ftm-primary" onclick="confirmStatusChange()">
                <?php echo get_string('st_btn_confirm_status', 'local_jobmatchagent'); ?>
            </button>
        </div>
    </div>
</div>

<script>
const USERID = <?php echo (int)$userid; ?>;
const SESSKEY = '<?php echo sesskey(); ?>';
const AJAX_TARGET   = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_save_target.php'))->out(false); ?>';
const AJAX_STATUS   = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_update_target_status.php'))->out(false); ?>';
const AJAX_ACTIVATE = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_activate_student.php'))->out(false); ?>';
const AJAX_SUGGEST  = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_suggest_targets.php'))->out(false); ?>';
const AJAX_DISCOVER = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_discover_company.php'))->out(false); ?>';
const AJAX_SETUP    = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_save_student_setup.php'))->out(false); ?>';
const JOBAIDA_BASE  = '<?php echo (new moodle_url('/local/jobaida/index.php'))->out(false); ?>';

const STATUS_LABELS = <?php echo json_encode(array_map(fn($v) => $v['label'], $status_badge)); ?>;
const STATUS_CLASSES = <?php echo json_encode(array_map(fn($v) => $v['class'], $status_badge)); ?>;
const STR_CONFIRM_DELETE = <?php echo json_encode(get_string('st_confirm_delete', 'local_jobmatchagent')); ?>;
const STR_CONFIRM_SENT = <?php echo json_encode(get_string('st_confirm_sent_help', 'local_jobmatchagent')); ?>;

let pendingStatusChange = null; // {targetId, newStatus, selectEl}
let searchTimer = null;

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ---- Student view toggle ----
function toggleStudentView() {
    const badge = document.getElementById('sv-badge');
    const currentlyOn = badge.textContent.trim() === <?php echo json_encode(get_string('st_sv_on', 'local_jobmatchagent')); ?>;
    const newEnabled = currentlyOn ? 0 : 1;
    fetch(AJAX_ACTIVATE, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'toggle',
            userid: USERID,
            enabled: newEnabled,
            sesskey: SESSKEY
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            badge.textContent = data.enabled
                ? <?php echo json_encode(get_string('st_sv_on', 'local_jobmatchagent')); ?>
                : <?php echo json_encode(get_string('st_sv_off', 'local_jobmatchagent')); ?>;
            badge.className = 'badge ' + (data.enabled ? 'bg-success' : 'bg-secondary');
            showToast(data.message, data.enabled ? 'success' : 'secondary');
        }
    })
    .catch(() => showToast('Errore di rete', 'danger'));
}

// ---- Open add modal ----
function openAddModal() {
    document.getElementById('company-search-input').value = '';
    document.getElementById('company-sector-filter').value = '';
    document.getElementById('company-search-results').innerHTML =
        '<div class="text-muted text-center py-3"><?php echo get_string('st_modal_type_to_search', 'local_jobmatchagent'); ?></div>';
    document.getElementById('selected-company-info').style.display = 'none';
    document.getElementById('selected-company-id').value = '';
    document.getElementById('selected-company-name').textContent = '';
    document.getElementById('modal-note-per-ai').value = '';
    document.getElementById('btn-add-target').disabled = true;
    document.getElementById('modal-add-target').classList.add('open');
}

// ---- Company search ----
function searchCompanies() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(_doSearch, 280);
}

function _doSearch() {
    const q = document.getElementById('company-search-input').value.trim();
    const settore = document.getElementById('company-sector-filter').value;
    const container = document.getElementById('company-search-results');

    if (q.length < 2 && settore === '') {
        container.innerHTML = '<div class="text-muted text-center py-3"><?php echo get_string('st_modal_type_to_search', 'local_jobmatchagent'); ?></div>';
        return;
    }

    container.innerHTML = '<div class="text-muted text-center py-2">...</div>';

    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'search_companies', q: q, settore: settore, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { container.innerHTML = '<div class="text-danger p-2">' + data.message + '</div>'; return; }
        if (!data.data || data.data.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3"><?php echo get_string('st_modal_no_results', 'local_jobmatchagent'); ?></div>';
            return;
        }
        const selectedId = document.getElementById('selected-company-id').value;
        container.innerHTML = data.data.map(c => {
            const sel = String(c.id) === String(selectedId) ? ' selected' : '';
            return `<div class="company-result-item${sel}" onclick="selectCompany(${c.id}, '${escHtml(c.nome)}')">
                <div class="cr-name">${escHtml(c.nome)}</div>
                <div class="cr-meta">${escHtml(c.settore_ftm || '')} &middot; ${escHtml(c.localita || '')}${c.dimensione && c.dimensione !== 'unknown' ? ' &middot; ' + escHtml(c.dimensione) : ''}</div>
            </div>`;
        }).join('');
    })
    .catch(() => { container.innerHTML = '<div class="text-danger p-2">Errore di rete</div>'; });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function selectCompany(id, nome) {
    document.getElementById('selected-company-id').value = id;
    document.getElementById('selected-company-name').textContent = nome;
    document.getElementById('selected-company-info').style.display = '';
    document.getElementById('btn-add-target').disabled = false;
    document.querySelectorAll('.company-result-item').forEach(el => el.classList.remove('selected'));
    document.querySelectorAll('.company-result-item').forEach(el => {
        if (el.getAttribute('onclick') && el.getAttribute('onclick').includes('(' + id + ',')) {
            el.classList.add('selected');
        }
    });
}

// ---- Add target ----
function addTarget() {
    const companyId = document.getElementById('selected-company-id').value;
    if (!companyId) return;
    const note = document.getElementById('modal-note-per-ai').value;
    document.getElementById('btn-add-target').disabled = true;
    document.getElementById('btn-add-target').textContent = '...';

    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'create',
            userid: USERID,
            company_id: companyId,
            note_per_ai: note,
            sesskey: SESSKEY
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('btn-add-target').disabled = false;
        document.getElementById('btn-add-target').textContent = <?php echo json_encode(get_string('st_btn_add', 'local_jobmatchagent')); ?>;
        if (data.success) {
            closeModal('modal-add-target');
            showToast(data.message || 'Azienda aggiunta.', 'success');
            // Reload page to show new target.
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.message || 'Errore', 'danger');
        }
    })
    .catch(() => {
        document.getElementById('btn-add-target').disabled = false;
        document.getElementById('btn-add-target').textContent = <?php echo json_encode(get_string('st_btn_add', 'local_jobmatchagent')); ?>;
        showToast('Errore di rete', 'danger');
    });
}

// ---- Save note ----
function saveNote(targetId) {
    const note = document.getElementById('note-' + targetId).value;
    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'update_note', id: targetId, note_per_ai: note, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => { if (!data.success) showToast(data.message || 'Errore salvataggio nota', 'warning'); })
    .catch(() => {});
}

// ---- Update status ----
function updateStatus(targetId, selectEl) {
    const newStatus = selectEl.value;
    const terminal = ['risposta','colloquio','assunto','rifiutato'];
    if (terminal.includes(newStatus)) {
        pendingStatusChange = {targetId, newStatus, selectEl};
        document.getElementById('note-esito-input').value = '';
        document.getElementById('modal-status-note').classList.add('open');
    } else {
        _sendStatusUpdate(targetId, newStatus, '', selectEl);
    }
}

function cancelStatusChange() {
    if (pendingStatusChange) {
        pendingStatusChange.selectEl.value = pendingStatusChange.selectEl.dataset.current;
    }
    pendingStatusChange = null;
    closeModal('modal-status-note');
}

function confirmStatusChange() {
    if (!pendingStatusChange) return;
    const noteEsito = document.getElementById('note-esito-input').value;
    closeModal('modal-status-note');
    _sendStatusUpdate(pendingStatusChange.targetId, pendingStatusChange.newStatus, noteEsito, pendingStatusChange.selectEl);
    pendingStatusChange = null;
}

function confirmSent(targetId) {
    if (!confirm(STR_CONFIRM_SENT)) return;
    const selectEl = document.querySelector('#target-' + targetId + ' .status-select');
    if (selectEl) { selectEl.value = 'inviata'; selectEl.dataset.current = 'inviata'; }
    _sendStatusUpdate(targetId, 'inviata', '', selectEl);
}

function _sendStatusUpdate(targetId, newStatus, noteEsito, selectEl) {
    fetch(AJAX_STATUS, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'update_status', id: targetId, status: newStatus, note_esito: noteEsito, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (selectEl) selectEl.dataset.current = newStatus;
            const badge = document.getElementById('status-badge-' + targetId);
            if (badge && STATUS_LABELS[newStatus]) {
                badge.textContent = STATUS_LABELS[newStatus];
                badge.className = 'badge ' + (STATUS_CLASSES[newStatus] || 'bg-secondary');
            }
            // Show/hide "Conferma Invio" button.
            const card = document.getElementById('target-' + targetId);
            if (card) {
                const sentBtn = card.querySelector('.btn-outline-success');
                const noSentStatuses = ['inviata','risposta','colloquio','assunto','rifiutato'];
                if (sentBtn) sentBtn.style.display = noSentStatuses.includes(newStatus) ? 'none' : '';
            }
            // Show CI badge if sip_entry_id set.
            if (data.data && data.data.sip_entry_id) {
                const card = document.getElementById('target-' + targetId);
                if (card && !card.querySelector('.sip-badge')) {
                    const sipBadge = document.createElement('div');
                    sipBadge.className = 'sip-badge';
                    sipBadge.textContent = '✓ <?php echo get_string('st_registered_ci', 'local_jobmatchagent'); ?>';
                    card.querySelector('.tc-meta').after(sipBadge);
                }
            }
            showToast(data.message || 'Stato aggiornato.', 'success');
        } else {
            if (selectEl) selectEl.value = selectEl.dataset.current;
            showToast(data.message || 'Errore', 'danger');
        }
    })
    .catch(() => {
        if (selectEl) selectEl.value = selectEl.dataset.current;
        showToast('Errore di rete', 'danger');
    });
}

// ---- Delete target ----
function deleteTarget(targetId) {
    if (!confirm(STR_CONFIRM_DELETE)) return;
    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'delete', id: targetId, userid: USERID, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('target-' + targetId);
            if (card) { card.style.transition = 'opacity 0.3s'; card.style.opacity = 0; setTimeout(() => card.remove(), 320); }
            showToast(data.message || 'Eliminato.', 'success');
        } else {
            showToast(data.message || 'Errore', 'danger');
        }
    })
    .catch(() => showToast('Errore di rete', 'danger'));
}

// ---- Profile panel ----
function toggleProfilePanel() {
    const body  = document.getElementById('profile-panel-body');
    const arrow = document.getElementById('profile-panel-arrow');
    const open  = body.style.display !== 'none';
    body.style.display  = open ? 'none' : '';
    arrow.textContent   = open ? '▼' : '▲';
}

let sectorSaveTimer = null;
function saveSector(sector) {
    clearTimeout(sectorSaveTimer);
    if (!sector) return;
    sectorSaveTimer = setTimeout(() => {
        fetch(AJAX_SETUP, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'save_sector', userid: USERID, sector: sector, sesskey: SESSKEY})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const icon = document.getElementById('sector-saved-icon');
                icon.style.display = '';
                setTimeout(() => { icon.style.display = 'none'; }, 2000);
                // Aggiorna anche il dropdown nel modal
                const modalSel = document.getElementById('ai-sector-select');
                if (modalSel) modalSel.value = sector;
                showToast('Settore salvato: ' + sector, 'success');
            } else {
                showToast(data.message || 'Errore salvataggio settore', 'danger');
            }
        })
        .catch(() => showToast('Errore di rete', 'danger'));
    }, 300);
}

function saveCv() {
    const cv_text = document.getElementById('setup-cv-textarea').value.trim();
    if (!cv_text) { showToast('Testo CV vuoto.', 'warning'); return; }
    const btn = document.getElementById('btn-save-cv');
    btn.disabled = true; btn.textContent = '...';
    fetch(AJAX_SETUP, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'save_cv', userid: USERID, cv_text: cv_text, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.innerHTML = '&#128190; Salva CV';
        if (data.success) {
            const msg = document.getElementById('cv-saved-msg');
            msg.style.display = '';
            setTimeout(() => { msg.style.display = 'none'; }, 3000);
            document.getElementById('cv-word-counter').textContent = data.word_count + ' parole';
            // Aggiorna anche la textarea nel modal
            const modalTa = document.getElementById('ai-cv-textarea');
            if (modalTa) modalTa.value = cv_text;
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Errore salvataggio CV', 'danger');
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '&#128190; Salva CV';
        showToast('Errore di rete', 'danger');
    });
}

// Contatore parole live sulla textarea CV
document.addEventListener('DOMContentLoaded', function() {
    const ta = document.getElementById('setup-cv-textarea');
    if (ta) {
        ta.addEventListener('input', function() {
            const wc = this.value.trim() ? this.value.trim().split(/\s+/).length : 0;
            document.getElementById('cv-word-counter').textContent = wc > 0 ? wc + ' parole' : '';
        });
    }
});

// ---- AI Suggest ----
function openAISuggestModal() {
    // Reset results area, keep the setup form visible.
    document.getElementById('ai-suggest-loading').style.display = 'none';
    document.getElementById('ai-suggest-results').style.display = 'none';
    document.getElementById('ai-suggest-error').style.display = 'none';
    document.getElementById('ai-suggest-meta').style.display = 'none';
    document.getElementById('modal-ai-suggest').classList.add('open');
}

async function runAISuggest() {
    // Legge i valori dal pannello profilo (sorgente primaria) o dal modal.
    const sectorEl  = document.getElementById('setup-sector-select') || document.getElementById('ai-sector-select');
    const cvEl      = document.getElementById('setup-cv-textarea')   || document.getElementById('ai-cv-textarea');
    const sector    = sectorEl ? sectorEl.value : '';
    const cv_text   = cvEl ? cvEl.value.trim() : '';

    if (!sector && !cv_text) {
        showToast('Imposta almeno il settore oppure incolla il CV per continuare.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-run-suggest');
    btn.disabled = true;
    btn.textContent = '...';

    document.getElementById('ai-suggest-loading').style.display = '';
    document.getElementById('ai-suggest-results').style.display = 'none';
    document.getElementById('ai-suggest-error').style.display = 'none';
    document.getElementById('ai-suggest-meta').style.display = 'none';

    try {
        const params = {userid: USERID, sesskey: SESSKEY};
        if (sector)  params.sector   = sector;
        if (cv_text) params.cv_text  = cv_text;

        const resp = await fetch(AJAX_SUGGEST, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(params)
        });
        const data = await resp.json();
        document.getElementById('ai-suggest-loading').style.display = 'none';
        btn.disabled = false;
        btn.textContent = '&#129302; Suggerisci aziende';

        if (!data.success) {
            document.getElementById('ai-suggest-error').style.display = '';
            document.getElementById('ai-suggest-error-msg').textContent = data.message || 'Errore sconosciuto.';
            return;
        }

        const metaDiv = document.getElementById('ai-suggest-meta');
        const cvLabel = data.cv_source === 'manual'  ? '<span class="cv-source-badge">CV manuale</span>' :
                        data.cv_source === 'jobaida' ? '<span class="cv-source-badge">CV da JobAIDA</span>' :
                        '<span class="cv-warn-badge">&#9888; Solo settore — nessun CV</span>';
        metaDiv.innerHTML = cvLabel + '&nbsp;&nbsp;<span>' + (data.n_companies || 0) + ' aziende analizzate</span>';
        metaDiv.style.display = 'flex';

        const container = document.getElementById('ai-suggest-results');
        if (!data.data || data.data.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3"><?php echo get_string('st_ai_suggest_none', 'local_jobmatchagent'); ?></div>';
        } else {
            container.innerHTML = data.data.map(item => `
                <div class="suggest-card" id="suggest-${item.company_id}">
                    <div class="suggest-card-header">
                        <div class="suggest-company-name">${escHtml(item.nome)}</div>
                        <span class="suggest-score">${item.score}%</span>
                    </div>
                    <div class="suggest-meta">
                        <span class="badge bg-info text-dark">${escHtml(item.settore_ftm)}</span>
                        ${item.localita ? '<span>' + escHtml(item.localita) + '</span>' : ''}
                        ${item.dimensione && item.dimensione !== 'unknown' ? '<span class="badge bg-light text-dark border">' + escHtml(item.dimensione) + '</span>' : ''}
                    </div>
                    <div class="suggest-motivo">"${escHtml(item.motivo)}"</div>
                    <div class="suggest-actions">
                        ${item.already_target
                            ? '<span class="badge bg-secondary"><?php echo get_string('st_ai_suggest_already', 'local_jobmatchagent'); ?></span>'
                            : `<button class="btn btn-sm btn-success" onclick="addSuggestedTarget(${item.company_id}, '${escHtml(item.nome).replace(/'/g,"\\'")}', this)"><?php echo get_string('st_ai_add_target', 'local_jobmatchagent'); ?></button>`
                        }
                        ${item.website ? `<a href="${escHtml(item.website)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Sito &#8599;</a>` : ''}
                    </div>
                </div>
            `).join('');
        }
        container.style.display = '';
    } catch (e) {
        document.getElementById('ai-suggest-loading').style.display = 'none';
        btn.disabled = false;
        btn.textContent = '&#129302; Suggerisci aziende';
        document.getElementById('ai-suggest-error').style.display = '';
        document.getElementById('ai-suggest-error-msg').textContent = 'Errore di rete. Riprova.';
    }
}

function addSuggestedTarget(companyId, companyName, btn) {
    btn.disabled = true;
    btn.textContent = '...';
    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'create', userid: USERID, company_id: companyId, note_per_ai: '', sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = '<?php echo get_string('st_ai_added', 'local_jobmatchagent'); ?>';
            btn.className = 'btn btn-sm btn-secondary';
            btn.disabled = true;
            showToast(companyName + ' aggiunto al piano.', 'success');
        } else {
            btn.disabled = false;
            btn.textContent = '<?php echo get_string('st_ai_add_target', 'local_jobmatchagent'); ?>';
            showToast(data.message || 'Errore', 'danger');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '<?php echo get_string('st_ai_add_target', 'local_jobmatchagent'); ?>';
        showToast('Errore di rete', 'danger');
    });
}

// ---- Company Detail ----
let _detailTargetId   = null;
let _detailNome       = '';
let _detailAiData     = null;
let _detailCompanyId  = null;

async function openCompanyDetail(targetId, nome, website, companyId) {
    _detailTargetId  = targetId;
    _detailNome      = nome;
    _detailAiData    = null;
    _detailCompanyId = companyId || null;

    document.getElementById('detail-loading').style.display = '';
    document.getElementById('detail-content').style.display = 'none';
    document.getElementById('detail-error').style.display = 'none';
    document.getElementById('detail-modal-title').textContent = '🏭 ' + nome;
    document.getElementById('btn-use-context').style.display = 'none';
    document.getElementById('btn-generate-letter-detail').style.display = 'none';

    const wsLink = document.getElementById('detail-website-link');
    if (website) { wsLink.href = website; wsLink.style.display = ''; }
    else          { wsLink.style.display = 'none'; }

    document.getElementById('modal-company-detail').classList.add('open');

    // Build discover params: use website if available, else ask AI to describe from company name/sector.
    const discoverParams = {sesskey: SESSKEY};
    if (website) {
        discoverParams.action = 'discover';
        discoverParams.url    = website;
    } else {
        discoverParams.action = 'describe';
    }
    if (_detailCompanyId) discoverParams.company_id = _detailCompanyId;

    try {
        const resp = await fetch(AJAX_DISCOVER, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(discoverParams)
        });
        const data = await resp.json();
        document.getElementById('detail-loading').style.display = 'none';

        if (!data.success) {
            document.getElementById('detail-error').style.display = '';
            document.getElementById('detail-error-msg').textContent = data.message || 'Errore analisi sito.';
            return;
        }

        _detailAiData = data.data;
        const d = _detailAiData;

        let html = '';
        if (d.indirizzo || d.localita) {
            html += `<div class="detail-label">Indirizzo</div>
                     <div class="detail-value">${escHtml([d.indirizzo, d.cap, d.localita].filter(Boolean).join(', '))}</div>`;
        }
        if (d.telefono) {
            html += `<div class="detail-label">Telefono</div>
                     <div class="detail-value"><a href="tel:${escHtml(d.telefono)}">${escHtml(d.telefono)}</a></div>`;
        }
        if (d.email) {
            html += `<div class="detail-label">Email</div>
                     <div class="detail-value"><a href="mailto:${escHtml(d.email)}">${escHtml(d.email)}</a></div>`;
        }
        if (d.referente) {
            html += `<div class="detail-label">Referente HR</div>
                     <div class="detail-value">${escHtml(d.referente)}</div>`;
        }
        const settoreLabel = (d.settore_ftm && d.settore_ftm !== 'ALTRO') ? d.settore_ftm : (d.settore_raw || '');
        if (settoreLabel) {
            html += `<div class="detail-label">Settore / Attività</div>
                     <div class="detail-value">
                       <span class="badge bg-info text-dark">${escHtml(settoreLabel)}</span>
                       ${d.dimensione && d.dimensione !== 'unknown' ? ' <span class="badge bg-light text-dark border">' + escHtml(d.dimensione) + '</span>' : ''}
                       ${d.settore_raw && d.settore_raw !== settoreLabel ? '<span style="font-size:0.85rem;color:#6c757d;margin-left:6px">' + escHtml(d.settore_raw) + '</span>' : ''}
                     </div>`;
        }
        if (d.descrizione) {
            const cacheNote = data.data.cached
                ? ' <span style="font-size:0.72rem;background:#e7f5ea;color:#155724;border-radius:4px;padding:1px 6px;margin-left:4px">&#9889; Da archivio</span>'
                : '';
            html += `<div class="detail-label">Descrizione azienda${cacheNote}</div>
                     <div class="detail-description">${escHtml(d.descrizione)}</div>`;
        }
        document.getElementById('detail-content').innerHTML = html || '<div class="text-muted">Nessun dato estratto dal sito.</div>';
        document.getElementById('detail-content').style.display = '';

        // Abilita bottoni azione se abbiamo dati utili e un target associato.
        if (_detailTargetId) {
            document.getElementById('btn-use-context').style.display = '';
            document.getElementById('btn-generate-letter-detail').style.display = '';
        } else if (!_detailTargetId && _detailAiData) {
            // Senza targetId mostra solo il bottone lettera (per uso futuro).
            document.getElementById('btn-generate-letter-detail').style.display = '';
        }
    } catch (e) {
        document.getElementById('detail-loading').style.display = 'none';
        document.getElementById('detail-error').style.display = '';
        document.getElementById('detail-error-msg').textContent = 'Errore di rete durante l\'analisi del sito.';
    }
}

function buildLetterContext(d) {
    const parts = [];
    if (d.settore_raw)  parts.push('Attività: ' + d.settore_raw);
    if (d.descrizione)  parts.push(d.descrizione);
    if (d.dimensione && d.dimensione !== 'unknown') parts.push('Dimensione: ' + d.dimensione);
    return parts.join('\n\n');
}

function generateLetterWithContext() {
    if (!_detailAiData) { showToast('Nessun dato azienda disponibile. Riapri il dettaglio.', 'warning'); return; }

    const d = _detailAiData;

    // Costruisce il testo "annuncio" con il profilo aziendale completo.
    const jobadParts = ['Azienda: ' + _detailNome];
    if (d.localita)       jobadParts.push('Sede: ' + d.localita);
    if (d.settore_raw)    jobadParts.push('Settore / Attività: ' + d.settore_raw);
    if (d.descrizione)    jobadParts.push('\n' + d.descrizione);
    if (d.email)          jobadParts.push('Email: ' + d.email);
    if (d.telefono)       jobadParts.push('Tel: ' + d.telefono);
    if (d.referente)      jobadParts.push('Referente: ' + d.referente);
    const jobad = jobadParts.join('\n');

    // CV dallo studente (pannello profilo).
    const cvEl = document.getElementById('setup-cv-textarea');
    const cv   = cvEl ? cvEl.value.trim() : '';

    // Passa i dati a JobAIDA tramite localStorage (meccanismo jobaida_prefill).
    const prefill = {jobad: jobad, timestamp: Date.now()};
    if (cv) prefill.cv = cv;

    try {
        localStorage.setItem('jobaida_prefill', JSON.stringify(prefill));
    } catch(e) {
        showToast('Errore localStorage. Verifica che i cookie/storage non siano bloccati.', 'danger');
        return;
    }

    const url = JOBAIDA_BASE
        + '?userid='    + USERID
        + '&source=jobmatch'
        + (_detailTargetId ? '&target_id=' + _detailTargetId : '');

    window.open(url, '_blank');
    showToast('JobAIDA aperto — il profilo aziendale è già nel campo "Annuncio".', 'success');
}

function generateLetterFromCard(btn) {
    const nome       = btn.dataset.nome      || '';
    const localita   = btn.dataset.localita  || '';
    const settoreFtm = btn.dataset.settoreFtm || '';
    const settoreRaw = btn.dataset.settoreRaw || '';
    const targetId   = btn.dataset.targetId  || '';

    const jobadParts = ['Autocandidatura — ' + nome];
    if (localita) jobadParts.push('Sede: ' + localita);
    const settore = settoreRaw || settoreFtm;
    if (settore) jobadParts.push('Settore / Attività: ' + settore);

    const jobad = jobadParts.join('\n');
    const cvEl  = document.getElementById('setup-cv-textarea');
    const cv    = cvEl ? cvEl.value.trim() : '';

    const prefill = {jobad: jobad, timestamp: Date.now()};
    if (cv) prefill.cv = cv;

    try {
        localStorage.setItem('jobaida_prefill', JSON.stringify(prefill));
    } catch(e) {
        showToast('Errore localStorage. Verifica che i cookie/storage non siano bloccati.', 'danger');
        return;
    }

    const url = JOBAIDA_BASE + '?userid=' + USERID + '&source=jobmatch' + (targetId ? '&target_id=' + targetId : '');
    window.open(url, '_blank');
    showToast('JobAIDA aperto — il profilo aziendale è nel campo "Annuncio".', 'success');
}

function useAsLetterContext() {
    if (!_detailTargetId || !_detailAiData) return;
    const context = buildLetterContext(_detailAiData);
    if (!context) { showToast('Nessun dato da salvare.', 'warning'); return; }

    const btn = document.getElementById('btn-use-context');
    btn.disabled = true; btn.textContent = '...';

    fetch(AJAX_TARGET, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'update_note', id: _detailTargetId, note_per_ai: context, sesskey: SESSKEY})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.innerHTML = '&#128203; Imposta contesto lettera';
        if (data.success) {
            // Aggiorna la textarea della nota nella card target.
            const ta = document.getElementById('note-' + _detailTargetId);
            if (ta) ta.value = context;
            showToast('Contesto aziendale salvato nelle note. Ora puoi generare la lettera.', 'success');
            btn.innerHTML = '&#10003; Contesto salvato';
            btn.style.background = '#28a745';
        } else {
            showToast(data.message || 'Errore salvataggio contesto.', 'danger');
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '&#128203; Imposta contesto lettera';
        showToast('Errore di rete', 'danger');
    });
}

// ---- Toast ----
function showToast(msg, type) {
    const color = {success:'#28a745', danger:'#dc3545', warning:'#EAB308', secondary:'#6c757d', info:'#0dcaf0'}[type] || '#0066cc';
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;background:${color};color:#fff;padding:.65rem 1.2rem;border-radius:8px;z-index:9999;box-shadow:0 4px 14px rgba(0,0,0,.2);font-size:.9rem;transition:opacity .4s`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = 0; setTimeout(() => t.remove(), 420); }, 2800);
}
</script>

<?php
echo $OUTPUT->footer();
