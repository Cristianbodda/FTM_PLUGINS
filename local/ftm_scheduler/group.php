<?php
/**
 * Group details page.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:view', $context);

$id = required_param('id', PARAM_INT);

$colors = local_ftm_scheduler_get_colors();

// Handle edit form submission.
$edit_error = '';
if (optional_param('action', '', PARAM_ALPHA) === 'edit') {
    require_capability('local/ftm_scheduler:manage', $context);
    require_sesskey();

    $new_color     = required_param('color', PARAM_ALPHA);
    $new_entry     = required_param('entry_date', PARAM_TEXT);
    $new_end       = required_param('planned_end_date', PARAM_TEXT);
    $new_kw        = required_param('calendar_week', PARAM_INT);
    $new_status    = required_param('status', PARAM_ALPHANUMEXT);

    $entry_ts = strtotime($new_entry);
    $end_ts   = strtotime($new_end);

    $valid_statuses = ['planning', 'active', 'completed'];
    if (!$entry_ts || !$end_ts) {
        $edit_error = 'Date non valide.';
    } elseif ($end_ts <= $entry_ts) {
        $edit_error = 'La data di fine deve essere successiva alla data di inizio.';
    } elseif (!isset($colors[$new_color])) {
        $edit_error = 'Colore non valido.';
    } elseif (!in_array($new_status, $valid_statuses)) {
        $edit_error = 'Stato non valido.';
    } else {
        $ci = $colors[$new_color];
        $kw_str = str_pad($new_kw, 2, '0', STR_PAD_LEFT);

        $upd = new stdClass();
        $upd->id               = $id;
        $upd->color            = $new_color;
        $upd->color_hex        = $ci['hex'];
        $upd->entry_date       = $entry_ts;
        $upd->planned_end_date = $end_ts;
        $upd->calendar_week    = $new_kw;
        $upd->name             = 'Gruppo ' . $ci['name'] . ' - KW' . $kw_str;
        $upd->status           = $new_status;
        $upd->timemodified     = time();

        $DB->update_record('local_ftm_groups', $upd);
        redirect(
            new moodle_url('/local/ftm_scheduler/group.php', ['id' => $id]),
            'Gruppo aggiornato con successo.',
            2,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Get group info
$group = $DB->get_record('local_ftm_groups', ['id' => $id], '*', MUST_EXIST);
$color_info = $colors[$group->color] ?? $colors['giallo'];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/group.php', ['id' => $id]));
$PAGE->set_title('Dettagli Gruppo - ' . $group->name);
$PAGE->set_heading('Dettagli Gruppo');

// Get group members
$members = \local_ftm_scheduler\manager::get_group_members($id);

// Get activities for this group (include coach/teacher info).
$activities = $DB->get_records_sql("
    SELECT a.*, r.name as room_name, r.id as room_id,
           u.firstname as coach_firstname, u.lastname as coach_lastname,
           c.id as coach_row_id
    FROM {local_ftm_activities} a
    LEFT JOIN {local_ftm_rooms} r ON r.id = a.roomid
    LEFT JOIN {user} u ON u.id = a.teacherid
    LEFT JOIN {local_ftm_coaches} c ON c.userid = a.teacherid AND c.active = 1
    WHERE a.groupid = ?
    ORDER BY a.date_start ASC
", [$id]);

// Load rooms, coaches and activity catalogs for the edit modal selects.
$modal_rooms   = $DB->get_records('local_ftm_rooms',  ['active' => 1], 'sortorder ASC, id ASC');
$modal_coaches = [];
if ($DB->get_manager()->table_exists('local_ftm_coaches')) {
    $modal_coaches = $DB->get_records_sql(
        "SELECT c.id, c.userid, u.firstname, u.lastname
         FROM {local_ftm_coaches} c
         JOIN {user} u ON u.id = c.userid
         WHERE c.active = 1
         ORDER BY u.lastname, u.firstname"
    );
}
$modal_week1   = $DB->get_manager()->table_exists('local_ftm_week1_template')
    ? $DB->get_records('local_ftm_week1_template', ['active' => 1], 'day_of_week ASC, sortorder ASC')
    : [];
$modal_atelier = $DB->get_manager()->table_exists('local_ftm_atelier_catalog')
    ? $DB->get_records('local_ftm_atelier_catalog', ['active' => 1], 'sortorder ASC, id ASC')
    : [];

// Calculate current week in the 6-week journey
$current_week = 0;
$progress_percent = 0;
if ($group->entry_date && $group->entry_date <= time()) {
    $days_elapsed = floor((time() - $group->entry_date) / 86400);
    $current_week = min(6, max(1, ceil(($days_elapsed + 1) / 7)));
    $total_days = 42; // 6 weeks
    $progress_percent = min(100, round(($days_elapsed / $total_days) * 100));
}

// Determine status
$now = time();
$status_label = 'In pianificazione';
$status_class = 'status-planning';
if ($group->entry_date <= $now && $group->planned_end_date >= $now) {
    $status_label = 'Attivo - Settimana ' . $current_week;
    $status_class = 'status-active';
} elseif ($group->planned_end_date < $now) {
    $status_label = 'Completato';
    $status_class = 'status-completed';
}

echo $OUTPUT->header();
?>

<style>
.group-page {
    max-width: 1200px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.group-header {
    padding: 25px 30px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
}

.group-header.giallo { background: #FFFF00; color: #333 !important; }
.group-header.grigio { background: #808080; color: white !important; }
.group-header.rosso { background: #FF0000; color: white !important; }
.group-header.marrone { background: #996633; color: white !important; }
.group-header.viola { background: #7030A0; color: white !important; }

.group-header h2 {
    margin: 0;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 15px;
    color: inherit !important;
}

.group-header .status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    background: rgba(255,255,255,0.3);
    color: inherit !important;
}

.group-body {
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 12px 12px;
    padding: 25px 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.info-card {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px;
    border-left: 4px solid #0066cc;
}

.info-card-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-card-value {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.progress-section {
    margin-bottom: 30px;
}

.progress-bar-container {
    background: #e9ecef;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s;
}

.progress-bar-fill.giallo { background: #EAB308; }
.progress-bar-fill.grigio { background: #6B7280; }
.progress-bar-fill.rosso { background: #EF4444; }
.progress-bar-fill.marrone { background: #92400E; }
.progress-bar-fill.viola { background: #7C3AED; }

.week-markers {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin: 30px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.member-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.member-actions {
    display: flex;
    gap: 6px;
    margin-left: auto;
}

.member-btn {
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: opacity 0.2s;
}

.member-btn:hover {
    opacity: 0.85;
    text-decoration: none !important;
}

.member-btn-program {
    background: #0066cc;
    color: white !important;
}

.member-btn-report {
    background: #28a745;
    color: white !important;
}

.member-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #0066cc;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}

.member-info {
    flex: 1;
}

.member-name {
    font-weight: 600;
    color: #333;
}

.member-email {
    font-size: 12px;
    color: #666;
}

.activities-table {
    width: 100%;
    border-collapse: collapse;
}

.activities-table th,
.activities-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.activities-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

.activities-table tr:hover {
    background: #f8f9fa;
}

.btn {
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

.btn-primary { background: #0066cc; color: white !important; }
.btn-secondary { background: #6c757d; color: white !important; }
.btn-success { background: #28a745; color: white !important; }
.btn:hover { opacity: 0.9; text-decoration: none; }

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.status-active { background: #D1FAE5; color: #065F46; }
.status-planning { background: #DBEAFE; color: #1E40AF; }
.status-completed { background: #E5E7EB; color: #374151; }

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}
</style>

<div class="group-page">
    <!-- Header -->
    <div class="group-header <?php echo $group->color; ?>">
        <h2><?php echo $color_info['emoji']; ?> <?php echo s($group->name); ?></h2>
        <div style="display:flex;align-items:center;gap:12px">
            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
            <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
            <button onclick="document.getElementById('modal-editGroup').classList.add('active')"
                    style="background:rgba(255,255,255,0.25);border:1px solid rgba(255,255,255,0.5);color:inherit;padding:7px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
                ✏️ Modifica
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($edit_error): ?>
    <div style="background:#fee;border:1px solid #dc3545;color:#7b0000;padding:10px 16px;border-radius:6px;margin-bottom:16px">
        ⚠️ <?php echo s($edit_error); ?>
    </div>
    <?php endif; ?>

    <div class="group-body">
        <!-- Info Cards -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-label">Data Inizio</div>
                <div class="info-card-value"><?php echo local_ftm_scheduler_format_date($group->entry_date, 'date_only'); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Settimana Calendario</div>
                <div class="info-card-value">KW<?php echo str_pad($group->calendar_week, 2, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Data Fine Prevista</div>
                <div class="info-card-value"><?php echo local_ftm_scheduler_format_date($group->planned_end_date, 'date_only'); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Studenti</div>
                <div class="info-card-value"><?php echo count($members); ?> iscritti</div>
            </div>
        </div>

        <!-- Progress -->
        <?php if ($group->entry_date <= $now): ?>
        <div class="progress-section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600;">Progresso Percorso</span>
                <span style="color: #666;"><?php echo $progress_percent; ?>% completato</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill <?php echo $group->color; ?>" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
            <div class="week-markers">
                <span>Sett. 1</span>
                <span>Sett. 2</span>
                <span>Sett. 3</span>
                <span>Sett. 4</span>
                <span>Sett. 5</span>
                <span>Sett. 6</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activities -->
        <h3 class="section-title">📅 Attività Programmate (<?php echo count($activities); ?>)</h3>
        <?php if (empty($activities)): ?>
            <div class="empty-state">
                <p>Nessuna attività programmata per questo gruppo.</p>
                <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>" class="btn btn-primary">
                    ➕ Crea Attività
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="activities-table" id="activities-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Orario</th>
                        <th>Attività</th>
                        <th>Tipo</th>
                        <th>Aula</th>
                        <th>Coach</th>
                        <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
                        <th style="width:48px"></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity):
                        $hour = (int)date('H', $activity->date_start);
                        $time_slot_label = ($hour < 12) ? 'Mattina' : 'Pomeriggio';
                        $time_slot_val   = ($hour < 12) ? 'matt' : 'pom';
                        // Detect custom: not exactly 08:30/11:45 and not 13:15/16:30
                        $is_custom = !(
                            (date('H:i', $activity->date_start) === '08:30' && date('H:i', $activity->date_end) === '11:45') ||
                            (date('H:i', $activity->date_start) === '13:15' && date('H:i', $activity->date_end) === '16:30')
                        );
                        if ($is_custom) { $time_slot_val = 'custom'; $time_slot_label = 'Personalizzato'; }
                        $type_labels = [
                            'week1'        => 'Settimana 1',
                            'week2_mon_tue'=> 'Sett. 2 (Lun-Mar)',
                            'week2_thu_fri'=> 'Sett. 2 (Gio-Ven)',
                            'week3_5'      => 'Settimane 3-5',
                            'week6'        => 'Settimana 6',
                            'atelier'      => 'Atelier',
                        ];
                        $coach_name = ($activity->coach_firstname || $activity->coach_lastname)
                            ? trim($activity->coach_firstname . ' ' . $activity->coach_lastname)
                            : '';
                    ?>
                    <tr id="act-row-<?php echo $activity->id; ?>">
                        <td>
                            <strong><?php echo date('d/m/Y', $activity->date_start); ?></strong><br>
                            <small style="color:#666"><?php echo local_ftm_scheduler_format_date($activity->date_start, 'weekday'); ?></small>
                        </td>
                        <td>
                            <?php echo date('H:i', $activity->date_start); ?> – <?php echo date('H:i', $activity->date_end); ?><br>
                            <small style="color:#666"><?php echo $time_slot_label; ?></small>
                        </td>
                        <td><?php echo s($activity->name); ?></td>
                        <td><span class="status-badge status-planning"><?php echo $type_labels[$activity->activity_type] ?? $activity->activity_type; ?></span></td>
                        <td><?php echo s($activity->room_name ?? '—'); ?></td>
                        <td style="color:#555;font-size:13px"><?php echo $coach_name ? s($coach_name) : '<span style="color:#bbb">—</span>'; ?></td>
                        <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
                        <td>
                            <button type="button"
                                    class="gp-edit-btn"
                                    data-id="<?php echo (int)$activity->id; ?>"
                                    data-date="<?php echo date('Y-m-d', $activity->date_start); ?>"
                                    data-slot="<?php echo $time_slot_val; ?>"
                                    data-tstart="<?php echo date('H:i', $activity->date_start); ?>"
                                    data-tend="<?php echo date('H:i', $activity->date_end); ?>"
                                    data-name="<?php echo htmlspecialchars($activity->name, ENT_QUOTES); ?>"
                                    data-type="<?php echo htmlspecialchars($activity->activity_type, ENT_QUOTES); ?>"
                                    data-roomid="<?php echo (int)($activity->roomid ?? 0); ?>"
                                    data-teacherid="<?php echo (int)($activity->teacherid ?? 0); ?>"
                                    title="Modifica attività"
                                    style="background:none;border:1px solid #dee2e6;border-radius:5px;padding:5px 8px;cursor:pointer;color:#0066cc;font-size:15px;line-height:1">
                                ✏️
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Members -->
        <h3 class="section-title">👥 Membri del Gruppo (<?php echo count($members); ?>)</h3>
        <?php if (empty($members)): ?>
            <div class="empty-state">
                <p>Nessun membro assegnato a questo gruppo.</p>
                <?php if (has_capability('local/ftm_scheduler:enrollstudents', $context)): ?>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $id]); ?>" class="btn btn-success">
                    ➕ Aggiungi Studenti
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach ($members as $member):
                    $initials = strtoupper(substr($member->firstname, 0, 1) . substr($member->lastname, 0, 1));
                    $canManageOrCoach = has_capability('local/ftm_scheduler:manage', $context) ||
                                        has_capability('local/ftm_scheduler:markattendance', $context);
                ?>
                <div class="member-card">
                    <div class="member-avatar"><?php echo $initials; ?></div>
                    <div class="member-info">
                        <div class="member-name"><?php echo s($member->firstname . ' ' . $member->lastname); ?></div>
                        <div class="member-email"><?php echo s($member->email); ?></div>
                    </div>
                    <?php if ($canManageOrCoach): ?>
                    <div class="member-actions">
                        <a href="<?php echo new moodle_url('/local/ftm_scheduler/student_program.php', [
                            'userid' => $member->userid,
                            'groupid' => $id
                        ]); ?>" class="member-btn member-btn-program" title="Programma Individuale">
                            📋 Programma
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>" class="btn btn-secondary">
                ← Torna ai Gruppi
            </a>
            <?php if (has_capability('local/ftm_scheduler:enrollstudents', $context)): ?>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $id]); ?>" class="btn btn-primary">
                👥 Gestione Studenti
            </a>
            <?php endif; ?>
            <?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>" class="btn btn-success">
                📅 Pianifica Attività
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
<!-- ===== Edit Group Modal ===== -->
<style>
.eg-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.eg-overlay.active { display: flex; }
.eg-modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.eg-header {
    padding: 18px 24px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.eg-header h3 { margin: 0; font-size: 17px; }
.eg-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #666; line-height: 1; }
.eg-body { padding: 24px; }
.eg-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #dee2e6; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 10px; }
.eg-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.eg-form-group { margin-bottom: 16px; }
.eg-form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .4px; }
.eg-form-group input, .eg-form-group select {
    width: 100%; padding: 9px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 14px;
}
.eg-form-group input:focus, .eg-form-group select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.1); }
/* Color picker */
.eg-color-grid { display: flex; gap: 10px; flex-wrap: wrap; }
.eg-color-opt {
    width: 44px; height: 44px; border-radius: 8px; cursor: pointer;
    border: 3px solid transparent; display: flex; align-items: center; justify-content: center;
    font-size: 20px; transition: transform .15s, border-color .15s;
}
.eg-color-opt:hover { transform: scale(1.1); }
.eg-color-opt.selected { border-color: #0066cc; transform: scale(1.1); box-shadow: 0 0 0 2px #fff, 0 0 0 4px #0066cc; }
</style>

<div class="eg-overlay" id="modal-editGroup" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="eg-modal">
        <div class="eg-header">
            <h3>✏️ Modifica Gruppo</h3>
            <button class="eg-close" onclick="document.getElementById('modal-editGroup').classList.remove('active')">×</button>
        </div>
        <form method="post" action="<?php echo (new moodle_url('/local/ftm_scheduler/group.php', ['id' => $id]))->out(false); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="color" id="eg-color-hidden" value="<?php echo $group->color; ?>">
            <div class="eg-body">

                <!-- Color -->
                <div class="eg-form-group">
                    <label>Colore Gruppo</label>
                    <div class="eg-color-grid">
                        <?php foreach ($colors as $ckey => $cval): ?>
                        <div class="eg-color-opt <?php echo $group->color === $ckey ? 'selected' : ''; ?>"
                             style="background:<?php echo $cval['hex']; ?>"
                             title="<?php echo s($cval['name']); ?>"
                             onclick="egSelectColor('<?php echo $ckey; ?>', this)">
                            <?php echo $cval['emoji']; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Dates -->
                <div class="eg-form-row">
                    <div class="eg-form-group" style="margin-bottom:0">
                        <label>Data Inizio</label>
                        <input type="date" name="entry_date" required
                               value="<?php echo date('Y-m-d', $group->entry_date); ?>">
                    </div>
                    <div class="eg-form-group" style="margin-bottom:0">
                        <label>Data Fine</label>
                        <input type="date" name="planned_end_date" required
                               value="<?php echo date('Y-m-d', $group->planned_end_date); ?>">
                    </div>
                </div>

                <!-- KW + Status -->
                <div class="eg-form-row" style="margin-top:16px">
                    <div class="eg-form-group" style="margin-bottom:0">
                        <label>Settimana Calendario (KW)</label>
                        <input type="number" name="calendar_week" min="1" max="53" required
                               value="<?php echo (int)$group->calendar_week; ?>">
                    </div>
                    <div class="eg-form-group" style="margin-bottom:0">
                        <label>Stato</label>
                        <select name="status">
                            <option value="planning"   <?php echo $group->status === 'planning'   ? 'selected' : ''; ?>>In pianificazione</option>
                            <option value="active"     <?php echo $group->status === 'active'     ? 'selected' : ''; ?>>Attivo</option>
                            <option value="completed"  <?php echo $group->status === 'completed'  ? 'selected' : ''; ?>>Completato</option>
                        </select>
                    </div>
                </div>

            </div>
            <div class="eg-footer">
                <button type="button" onclick="document.getElementById('modal-editGroup').classList.remove('active')"
                        style="padding:9px 20px;border:1px solid #dee2e6;background:#fff;border-radius:6px;cursor:pointer;font-size:14px">
                    Annulla
                </button>
                <button type="submit"
                        style="padding:9px 20px;background:#0066cc;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600">
                    💾 Salva Modifiche
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function egSelectColor(colorKey, el) {
    document.querySelectorAll('.eg-color-opt').forEach(function(o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('eg-color-hidden').value = colorKey;
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('modal-editGroup').classList.remove('active');
});
<?php if ($edit_error): ?>
document.getElementById('modal-editGroup').classList.add('active');
<?php endif; ?>
</script>
<?php endif; ?>

<?php if (has_capability('local/ftm_scheduler:manage', $context)): ?>
<!-- ===== Edit Activity Modal ===== -->
<style>
.ea-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:10000; align-items:center; justify-content:center; }
.ea-overlay.active { display:flex; }
.ea-modal { background:#fff; border-radius:12px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.ea-header { padding:16px 22px; background:#f8f9fa; border-bottom:1px solid #dee2e6; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center; }
.ea-header h3 { margin:0; font-size:16px; }
.ea-close { background:none; border:none; font-size:22px; cursor:pointer; color:#666; }
.ea-body { padding:22px; display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.ea-full { grid-column:1/-1; }
.ea-group label { display:block; font-size:11px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
.ea-group input, .ea-group select { width:100%; padding:8px 11px; border:1px solid #dee2e6; border-radius:6px; font-size:14px; }
.ea-group input:focus, .ea-group select:focus { outline:none; border-color:#0066cc; box-shadow:0 0 0 3px rgba(0,102,204,.1); }
.ea-footer { padding:14px 22px; background:#f8f9fa; border-top:1px solid #dee2e6; border-radius:0 0 12px 12px; display:flex; justify-content:flex-end; gap:10px; }
.ea-slot-row { display:flex; gap:8px; }
.ea-slot-btn { flex:1; padding:8px; border:1px solid #dee2e6; border-radius:6px; background:#fff; cursor:pointer; font-size:13px; font-weight:500; text-align:center; transition:all .15s; }
.ea-slot-btn.active { background:#0066cc; color:#fff; border-color:#0066cc; }
#ea-custom-times { display:none; }
</style>

<div class="ea-overlay" id="modal-editActivity" onclick="if(event.target===this)this.classList.remove('active')">
  <div class="ea-modal">
    <div class="ea-header">
      <h3>✏️ Modifica Attività</h3>
      <button class="ea-close" onclick="document.getElementById('modal-editActivity').classList.remove('active')">×</button>
    </div>
    <div class="ea-body">
      <!-- Scegli dall'elenco -->
      <div class="ea-group ea-full">
        <label>Scegli dall'elenco</label>
        <select id="ea-catalog" onchange="gpPickFromCatalog(this)">
          <option value="">— Seleziona oppure scrivi sotto —</option>
          <?php if (!empty($modal_week1)): ?>
          <optgroup label="Attività Settimana 1">
            <?php foreach ($modal_week1 as $w): ?>
            <option value="<?php echo htmlspecialchars($w->name, ENT_QUOTES); ?>"><?php echo htmlspecialchars($w->name, ENT_QUOTES); ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endif; ?>
          <?php if (!empty($modal_atelier)): ?>
          <optgroup label="Atelier">
            <?php foreach ($modal_atelier as $at): ?>
            <option value="<?php echo htmlspecialchars($at->name, ENT_QUOTES); ?>"><?php echo htmlspecialchars($at->name, ENT_QUOTES); ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endif; ?>
        </select>
      </div>
      <!-- Nome -->
      <div class="ea-group ea-full">
        <label>Nome Attività <small style="color:#999;font-weight:normal">(modifica libera)</small></label>
        <input type="text" id="ea-name" autocomplete="off">
      </div>
      <!-- Data -->
      <div class="ea-group">
        <label>Data</label>
        <input type="date" id="ea-date">
      </div>
      <!-- Tipo -->
      <div class="ea-group">
        <label>Tipo Settimana</label>
        <select id="ea-type">
          <option value="week1">Settimana 1</option>
          <option value="week2_mon_tue">Sett. 2 (Lun-Mar)</option>
          <option value="week2_thu_fri">Sett. 2 (Gio-Ven)</option>
          <option value="week3_5">Settimane 3-5</option>
          <option value="week6">Settimana 6</option>
          <option value="atelier">Atelier</option>
        </select>
      </div>
      <!-- Slot mattina / pomeriggio / personalizzato -->
      <div class="ea-group ea-full">
        <label>Orario</label>
        <div class="ea-slot-row">
          <button type="button" class="ea-slot-btn" id="ea-btn-matt" onclick="gpSetSlot('matt')">🌅 Mattina<br><small>08:30 – 11:45</small></button>
          <button type="button" class="ea-slot-btn" id="ea-btn-pom"  onclick="gpSetSlot('pom')">🌇 Pomeriggio<br><small>13:15 – 16:30</small></button>
          <button type="button" class="ea-slot-btn" id="ea-btn-custom" onclick="gpSetSlot('custom')">🕐 Personalizzato</button>
        </div>
      </div>
      <!-- Orari personalizzati -->
      <div class="ea-group ea-full" id="ea-custom-times">
        <label>Orari manuali</label>
        <div style="display:flex;gap:12px;align-items:center">
          <div style="flex:1"><label style="font-size:11px;color:#999">Inizio</label><input type="time" id="ea-time-start" step="300"></div>
          <span style="padding-top:18px;color:#666">→</span>
          <div style="flex:1"><label style="font-size:11px;color:#999">Fine</label><input type="time" id="ea-time-end" step="300"></div>
        </div>
      </div>
      <!-- Aula -->
      <div class="ea-group">
        <label>Aula</label>
        <select id="ea-roomid">
          <option value="0">— Nessuna aula —</option>
          <?php foreach ($modal_rooms as $room): ?>
          <option value="<?php echo (int)$room->id; ?>"><?php echo s($room->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Coach -->
      <div class="ea-group">
        <label>Coach</label>
        <select id="ea-teacherid">
          <option value="0">— Nessun coach —</option>
          <?php foreach ($modal_coaches as $coach): ?>
          <option value="<?php echo (int)$coach->userid; ?>"><?php echo s($coach->firstname . ' ' . $coach->lastname); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="ea-footer">
      <button type="button" onclick="document.getElementById('modal-editActivity').classList.remove('active')"
              style="padding:8px 18px;border:1px solid #dee2e6;background:#fff;border-radius:6px;cursor:pointer;font-size:14px">Annulla</button>
      <button type="button" onclick="gpSaveActivity()"
              style="padding:8px 18px;background:#0066cc;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600">
        💾 Salva Modifiche
      </button>
    </div>
  </div>
</div>

<script>
var gpCurrentActId = 0;
var gpCurrentSlot  = 'matt';
var gpSesskey      = '<?php echo sesskey(); ?>';
var gpAjaxUrl      = '<?php echo (new moodle_url('/local/ftm_scheduler/ajax_secretary.php'))->out(false); ?>';

var gpRoomNames    = <?php echo json_encode(array_column(array_map(function($r){ return ['id'=>(int)$r->id,'name'=>$r->name]; }, $modal_rooms), null, 'id')); ?>;
var gpCoachNames   = <?php echo json_encode(array_column(array_map(function($c){ return ['id'=>(int)$c->userid,'name'=>$c->firstname.' '.$c->lastname]; }, $modal_coaches), null, 'id')); ?>;
var gpTypeLabels   = {
    'week1':'Settimana 1','week2_mon_tue':'Sett. 2 (Lun-Mar)',
    'week2_thu_fri':'Sett. 2 (Gio-Ven)','week3_5':'Settimane 3-5',
    'week6':'Settimana 6','atelier':'Atelier'
};

document.addEventListener('click', function(e) {
    var btn = e.target.closest('.gp-edit-btn');
    if (!btn) return;
    var d = btn.dataset;
    gpCurrentActId = d.id;
    document.getElementById('ea-name').value      = d.name;
    document.getElementById('ea-date').value      = d.date;
    document.getElementById('ea-type').value      = d.type;
    document.getElementById('ea-roomid').value    = d.roomid;
    document.getElementById('ea-teacherid').value = d.teacherid;
    document.getElementById('ea-time-start').value = d.tstart;
    document.getElementById('ea-time-end').value   = d.tend;
    gpSetSlot(d.slot);
    document.getElementById('modal-editActivity').classList.add('active');
});

function gpSetSlot(slot) {
    gpCurrentSlot = slot;
    ['matt','pom','custom'].forEach(function(s) {
        document.getElementById('ea-btn-' + s).classList.toggle('active', s === slot);
    });
    document.getElementById('ea-custom-times').style.display = (slot === 'custom') ? 'block' : 'none';
    if (slot === 'matt') {
        document.getElementById('ea-time-start').value = '08:30';
        document.getElementById('ea-time-end').value   = '11:45';
    } else if (slot === 'pom') {
        document.getElementById('ea-time-start').value = '13:15';
        document.getElementById('ea-time-end').value   = '16:30';
    }
}

function gpPickFromCatalog(sel) {
    if (sel.value) {
        document.getElementById('ea-name').value = sel.value;
        sel.value = '';
    }
}

function gpSaveActivity() {
    var fd = new FormData();
    fd.append('action',       'update_activity');
    fd.append('sesskey',      gpSesskey);
    fd.append('id',           gpCurrentActId);
    fd.append('name',         document.getElementById('ea-name').value);
    fd.append('date',         document.getElementById('ea-date').value);
    fd.append('time_slot',    gpCurrentSlot);
    fd.append('time_start',   document.getElementById('ea-time-start').value);
    fd.append('time_end',     document.getElementById('ea-time-end').value);
    fd.append('activity_type', document.getElementById('ea-type').value);
    fd.append('roomid',       document.getElementById('ea-roomid').value);
    fd.append('teacherid',    document.getElementById('ea-teacherid').value);

    fetch(gpAjaxUrl, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.success) { alert('Errore: ' + (data.message || 'sconosciuto')); return; }

            // Aggiorna la riga nella tabella senza ricaricare la pagina.
            var date    = document.getElementById('ea-date').value;
            var tstart  = document.getElementById('ea-time-start').value;
            var tend    = document.getElementById('ea-time-end').value;
            var name    = document.getElementById('ea-name').value;
            var type    = document.getElementById('ea-type').value;
            var roomid  = parseInt(document.getElementById('ea-roomid').value);
            var tid     = parseInt(document.getElementById('ea-teacherid').value);

            var slotLabel = gpCurrentSlot === 'matt' ? 'Mattina' :
                            gpCurrentSlot === 'pom'  ? 'Pomeriggio' : 'Personalizzato';

            var d = new Date(date + 'T' + tstart);
            var weekdays = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
            var dateStr = d.getDate().toString().padStart(2,'0') + '/' +
                          (d.getMonth()+1).toString().padStart(2,'0') + '/' + d.getFullYear();
            var wday = weekdays[d.getDay()];

            var row = document.getElementById('act-row-' + gpCurrentActId);
            var cells = row.querySelectorAll('td');
            cells[0].innerHTML = '<strong>' + dateStr + '</strong><br><small style="color:#666">' + wday + '</small>';
            cells[1].innerHTML = tstart + ' – ' + tend + '<br><small style="color:#666">' + slotLabel + '</small>';
            cells[2].textContent = name;
            cells[3].innerHTML = '<span class="status-badge status-planning">' + (gpTypeLabels[type] || type) + '</span>';
            cells[4].textContent = (roomid && gpRoomNames[roomid]) ? gpRoomNames[roomid].name : '—';
            cells[5].textContent = (tid && gpCoachNames[tid]) ? gpCoachNames[tid].name : '—';

            document.getElementById('modal-editActivity').classList.remove('active');
        })
        .catch(function(){ alert('Errore di connessione.'); });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('modal-editActivity').classList.remove('active');
});
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
