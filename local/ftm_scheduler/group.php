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

// Get group info
$group = $DB->get_record('local_ftm_groups', ['id' => $id], '*', MUST_EXIST);
$colors = local_ftm_scheduler_get_colors();
$color_info = $colors[$group->color] ?? $colors['giallo'];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/group.php', ['id' => $id]));
$PAGE->set_title('Dettagli Gruppo - ' . $group->name);
$PAGE->set_heading('Dettagli Gruppo');

// Get group members
$members = \local_ftm_scheduler\manager::get_group_members($id);

// Get activities for this group
$activities = $DB->get_records_sql("
    SELECT a.*, r.name as room_name
    FROM {local_ftm_activities} a
    LEFT JOIN {local_ftm_rooms} r ON r.id = a.roomid
    WHERE a.groupid = ?
    ORDER BY a.date_start ASC
", [$id]);

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
        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
    </div>

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
            <table class="activities-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Orario</th>
                        <th>Attività</th>
                        <th>Tipo</th>
                        <th>Aula</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity):
                        $time_slot = (date('H', $activity->date_start) < 12) ? 'Mattina' : 'Pomeriggio';
                        $type_labels = [
                            'week1' => 'Settimana 1',
                            'week2_mon_tue' => 'Sett. 2 (Lun-Mar)',
                            'week2_thu_fri' => 'Sett. 2 (Gio-Ven)',
                            'week3_5' => 'Settimane 3-5',
                            'week6' => 'Settimana 6',
                            'atelier' => 'Atelier',
                        ];
                    ?>
                    <tr>
                        <td><strong><?php echo date('d/m/Y', $activity->date_start); ?></strong><br>
                            <small style="color: #666;"><?php echo local_ftm_scheduler_format_date($activity->date_start, 'weekday'); ?></small>
                        </td>
                        <td><?php echo date('H:i', $activity->date_start); ?> - <?php echo date('H:i', $activity->date_end); ?><br>
                            <small style="color: #666;"><?php echo $time_slot; ?></small>
                        </td>
                        <td><?php echo s($activity->name); ?></td>
                        <td><span class="status-badge status-planning"><?php echo $type_labels[$activity->activity_type] ?? $activity->activity_type; ?></span></td>
                        <td><?php echo s($activity->room_name ?? '-'); ?></td>
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
                            'userid' => $member->id,
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

<?php
echo $OUTPUT->footer();
