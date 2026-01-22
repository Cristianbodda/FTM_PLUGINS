<?php
// ============================================
// FTM Scheduler - Attendance Page
// ============================================
// Registro presenze per docenti/coach
// Permette di segnare presente/assente gli studenti
// Invia notifiche automatiche in caso di assenza
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('classes/notification_helper.php');

require_login();

$context = context_system::instance();
// Docenti e coach possono accedere
$can_manage = has_capability('local/ftm_scheduler:manage', $context) ||
              has_capability('local/coachmanager:view', $context);

if (!$can_manage) {
    throw new moodle_exception('nopermissions', 'error', '', 'manage attendance');
}

// Parametri
$date = optional_param('date', date('Y-m-d'), PARAM_TEXT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Validazione data
$date_timestamp = strtotime($date);
if (!$date_timestamp) {
    $date = date('Y-m-d');
    $date_timestamp = strtotime($date);
}

// Setup pagina
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/attendance.php', ['date' => $date]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('attendance', 'local_ftm_scheduler'));
$PAGE->set_heading(get_string('attendance', 'local_ftm_scheduler'));
$PAGE->set_pagelayout('standard');

// Gestione azioni POST
if ($action && confirm_sesskey()) {
    if ($action === 'mark') {
        $enrollmentid = required_param('enrollmentid', PARAM_INT);
        $status = required_param('status', PARAM_ALPHA); // attended or absent

        $enrollment = $DB->get_record('local_ftm_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);

        // Aggiorna stato
        $enrollment->status = $status;
        $enrollment->attended = ($status === 'attended') ? 1 : 0;
        $enrollment->marked_by = $USER->id;
        $enrollment->marked_at = time();
        $enrollment->timemodified = time();

        $DB->update_record('local_ftm_enrollments', $enrollment);

        // Se assente, invia notifica
        if ($status === 'absent' && !$enrollment->absence_notified) {
            $notifier = new \local_ftm_scheduler\notification_helper();
            $notifier->notify_absence($enrollment);

            // Marca come notificato
            $DB->set_field('local_ftm_enrollments', 'absence_notified', 1, ['id' => $enrollmentid]);
        }

        // Redirect per evitare resubmit
        redirect(new moodle_url('/local/ftm_scheduler/attendance.php', [
            'date' => $date,
            'activityid' => $enrollment->activityid,
            'marked' => 1
        ]));
    }
}

// Carica attività del giorno
$day_start = strtotime($date . ' 00:00:00');
$day_end = strtotime($date . ' 23:59:59');

$activities = $DB->get_records_sql("
    SELECT a.*, r.name as room_name, r.shortname as room_shortname,
           ac.name as atelier_name,
           (SELECT COUNT(*) FROM {local_ftm_enrollments} e
            WHERE e.activityid = a.id AND e.status IN ('enrolled', 'attended', 'absent')) as enrolled_count
    FROM {local_ftm_activities} a
    LEFT JOIN {local_ftm_rooms} r ON a.roomid = r.id
    LEFT JOIN {local_ftm_atelier_catalog} ac ON a.atelierid = ac.id
    WHERE a.date_start BETWEEN ? AND ?
    AND a.status IN ('scheduled', 'in_progress')
    ORDER BY a.date_start
", [$day_start, $day_end]);

// Se selezionata un'attività, carica gli iscritti
$enrollments = [];
$selected_activity = null;
if ($activityid > 0) {
    $selected_activity = $DB->get_record('local_ftm_activities', ['id' => $activityid]);

    if ($selected_activity) {
        $enrollments = $DB->get_records_sql("
            SELECT e.*,
                   u.id as userid, u.firstname, u.lastname, u.email,
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                   g.name as group_name, g.color as group_color,
                   marker.firstname as marker_firstname, marker.lastname as marker_lastname
            FROM {local_ftm_enrollments} e
            JOIN {user} u ON e.userid = u.id
            LEFT JOIN {local_ftm_groups} g ON e.groupid = g.id
            LEFT JOIN {user} marker ON e.marked_by = marker.id
            WHERE e.activityid = ?
            AND e.status IN ('enrolled', 'attended', 'absent')
            ORDER BY u.lastname, u.firstname
        ", [$activityid]);
    }
}

// Statistiche rapide
$stats = [
    'total' => count($enrollments),
    'attended' => 0,
    'absent' => 0,
    'pending' => 0
];
foreach ($enrollments as $e) {
    if ($e->status === 'attended') $stats['attended']++;
    elseif ($e->status === 'absent') $stats['absent']++;
    else $stats['pending']++;
}

echo $OUTPUT->header();

// Messaggio di conferma
if (optional_param('marked', 0, PARAM_INT)) {
    echo '<div class="alert alert-success">Presenza registrata con successo!</div>';
}
?>

<style>
.attendance-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.date-selector {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    flex-wrap: wrap;
}

.date-selector label {
    font-weight: 600;
    color: #333;
}

.date-selector input[type="date"] {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
}

.date-nav-btn {
    padding: 10px 15px;
    background: #f3f4f6;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.date-nav-btn:hover {
    background: #e5e7eb;
}

.date-nav-btn.today {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.activities-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.activity-card {
    background: white;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.activity-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.activity-card.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}

.activity-card .time {
    font-size: 24px;
    font-weight: 700;
    color: #1e40af;
}

.activity-card .name {
    font-size: 16px;
    font-weight: 600;
    margin: 8px 0;
    color: #333;
}

.activity-card .room {
    font-size: 14px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.activity-card .enrolled-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 10px;
}

.attendance-panel {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    overflow: hidden;
}

.attendance-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
}

.attendance-header h2 {
    margin: 0 0 5px;
    font-size: 20px;
}

.attendance-header .subtitle {
    opacity: 0.9;
    font-size: 14px;
}

.attendance-stats {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    padding: 8px 15px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    font-size: 14px;
}

.stat-item strong {
    font-size: 18px;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.students-table th {
    text-align: left;
    padding: 15px 20px;
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
}

.students-table td {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
}

.students-table tr:hover {
    background: #f8f9fa;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #6b7280;
}

.student-name {
    font-weight: 600;
    color: #333;
}

.student-email {
    font-size: 13px;
    color: #666;
}

.group-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.group-badge.giallo { background: #fef3c7; color: #a16207; }
.group-badge.grigio { background: #e5e7eb; color: #4b5563; }
.group-badge.rosso { background: #fee2e2; color: #dc2626; }
.group-badge.marrone { background: #fde68a; color: #92400e; }
.group-badge.viola { background: #ede9fe; color: #7c3aed; }
.group-badge.blu { background: #dbeafe; color: #1e40af; }
.group-badge.verde { background: #dcfce7; color: #166534; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-badge.attended {
    background: #dcfce7;
    color: #166534;
}

.status-badge.absent {
    background: #fee2e2;
    color: #dc2626;
}

.status-badge.pending {
    background: #fef3c7;
    color: #a16207;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-attend, .btn-absent {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-attend {
    background: #22c55e;
    color: white;
}

.btn-attend:hover {
    background: #16a34a;
}

.btn-absent {
    background: #ef4444;
    color: white;
}

.btn-absent:hover {
    background: #dc2626;
}

.btn-attend:disabled, .btn-absent:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.marked-info {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.no-activities {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-activities .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.quick-actions {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.quick-actions .btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
}

.btn-mark-all-present {
    background: #22c55e;
    color: white;
}

.btn-export {
    background: #3b82f6;
    color: white;
}
</style>

<div class="attendance-container">

    <!-- Date Selector -->
    <div class="date-selector">
        <button class="date-nav-btn" onclick="changeDate(-1)">&#9664; Ieri</button>

        <label>Data:</label>
        <input type="date" id="dateInput" value="<?php echo $date; ?>" onchange="goToDate(this.value)">

        <button class="date-nav-btn" onclick="changeDate(1)">Domani &#9654;</button>

        <?php if ($date !== date('Y-m-d')): ?>
        <button class="date-nav-btn today" onclick="goToDate('<?php echo date('Y-m-d'); ?>')">Oggi</button>
        <?php endif; ?>

        <span style="margin-left: auto; color: #666;">
            <?php echo userdate($date_timestamp, '%A %d %B %Y'); ?>
        </span>
    </div>

    <?php if (empty($activities)): ?>
    <div class="no-activities">
        <div class="icon">&#128197;</div>
        <h3>Nessuna attività programmata</h3>
        <p>Non ci sono attività per il <?php echo userdate($date_timestamp, '%d %B %Y'); ?></p>
    </div>
    <?php else: ?>

    <!-- Activities List -->
    <h3 style="margin-bottom: 15px;">Attività del giorno (<?php echo count($activities); ?>)</h3>
    <div class="activities-list">
        <?php foreach ($activities as $activity): ?>
        <div class="activity-card <?php echo $activity->id == $activityid ? 'selected' : ''; ?>"
             onclick="selectActivity(<?php echo $activity->id; ?>)">
            <div class="time"><?php echo userdate($activity->date_start, '%H:%M'); ?></div>
            <div class="name"><?php echo $activity->atelier_name ?? $activity->name; ?></div>
            <div class="room">
                &#127970; <?php echo $activity->room_name ?? 'Aula non assegnata'; ?>
            </div>
            <div class="enrolled-badge">
                <?php echo $activity->enrolled_count; ?> iscritti
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <?php if ($selected_activity && !empty($enrollments)): ?>
    <!-- Attendance Panel -->
    <div class="attendance-panel">
        <div class="attendance-header">
            <h2><?php echo $selected_activity->name; ?></h2>
            <div class="subtitle">
                <?php echo userdate($selected_activity->date_start, '%H:%M'); ?> -
                <?php echo userdate($selected_activity->date_end, '%H:%M'); ?>
                | <?php echo $DB->get_field('local_ftm_rooms', 'name', ['id' => $selected_activity->roomid]) ?? 'Aula N/D'; ?>
            </div>
            <div class="attendance-stats">
                <div class="stat-item">
                    <strong><?php echo $stats['total']; ?></strong> Iscritti
                </div>
                <div class="stat-item">
                    <strong><?php echo $stats['attended']; ?></strong> Presenti
                </div>
                <div class="stat-item">
                    <strong><?php echo $stats['absent']; ?></strong> Assenti
                </div>
                <div class="stat-item">
                    <strong><?php echo $stats['pending']; ?></strong> Da registrare
                </div>
            </div>
        </div>

        <table class="students-table">
            <thead>
                <tr>
                    <th>Studente</th>
                    <th>Gruppo</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $enrollment):
                    $initials = strtoupper(substr($enrollment->firstname, 0, 1) . substr($enrollment->lastname, 0, 1));
                ?>
                <tr>
                    <td>
                        <div class="student-info">
                            <div class="student-avatar"><?php echo $initials; ?></div>
                            <div>
                                <div class="student-name"><?php echo fullname($enrollment); ?></div>
                                <div class="student-email"><?php echo $enrollment->email; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($enrollment->group_color): ?>
                        <span class="group-badge <?php echo $enrollment->group_color; ?>">
                            <?php echo $enrollment->group_name ?? ucfirst($enrollment->group_color); ?>
                        </span>
                        <?php else: ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($enrollment->status === 'attended'): ?>
                        <span class="status-badge attended">&#10004; Presente</span>
                        <?php elseif ($enrollment->status === 'absent'): ?>
                        <span class="status-badge absent">&#10008; Assente</span>
                        <?php else: ?>
                        <span class="status-badge pending">&#9711; Da registrare</span>
                        <?php endif; ?>

                        <?php if ($enrollment->marked_at): ?>
                        <div class="marked-info">
                            Registrato da <?php echo $enrollment->marker_firstname . ' ' . $enrollment->marker_lastname; ?>
                            il <?php echo userdate($enrollment->marked_at, '%d/%m %H:%M'); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="mark">
                            <input type="hidden" name="enrollmentid" value="<?php echo $enrollment->id; ?>">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <div class="action-buttons">
                                <button type="submit" name="status" value="attended"
                                        class="btn-attend"
                                        <?php echo $enrollment->status === 'attended' ? 'disabled' : ''; ?>>
                                    &#10004; Presente
                                </button>
                                <button type="submit" name="status" value="absent"
                                        class="btn-absent"
                                        <?php echo $enrollment->status === 'absent' ? 'disabled' : ''; ?>
                                        onclick="return confirm('Confermi l\'assenza? Verrà inviata una notifica al coach e alla segreteria.')">
                                    &#10008; Assente
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="quick-actions">
            <form method="post" id="markAllForm">
                <input type="hidden" name="action" value="markall">
                <input type="hidden" name="activityid" value="<?php echo $activityid; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            </form>
            <button class="btn btn-mark-all-present" onclick="markAllPresent()">
                &#10004; Segna tutti presenti
            </button>
            <button class="btn btn-export" onclick="exportAttendance()">
                &#128190; Esporta lista
            </button>
        </div>
    </div>
    <?php elseif ($activityid > 0): ?>
    <div class="attendance-panel">
        <div style="text-align: center; padding: 40px; color: #666;">
            <div style="font-size: 32px; margin-bottom: 10px;">&#128100;</div>
            <p>Nessuno studente iscritto a questa attività.</p>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function changeDate(days) {
    const input = document.getElementById('dateInput');
    const date = new Date(input.value);
    date.setDate(date.getDate() + days);
    goToDate(date.toISOString().split('T')[0]);
}

function goToDate(date) {
    window.location.href = 'attendance.php?date=' + date;
}

function selectActivity(activityId) {
    const date = document.getElementById('dateInput').value;
    window.location.href = 'attendance.php?date=' + date + '&activityid=' + activityId;
}

function markAllPresent() {
    if (!confirm('Sei sicuro di voler segnare tutti gli studenti come presenti?')) {
        return;
    }

    // Submit per ogni studente non ancora registrato
    const forms = document.querySelectorAll('form[method="post"]');
    // Per semplicità, ricarica la pagina con un parametro speciale
    // In produzione si potrebbe usare AJAX
    alert('Funzione in sviluppo. Segna manualmente ogni studente.');
}

function exportAttendance() {
    // Genera CSV con i dati della tabella
    const rows = document.querySelectorAll('.students-table tbody tr');
    let csv = 'Nome,Email,Gruppo,Stato\n';

    rows.forEach(row => {
        const name = row.querySelector('.student-name').textContent.trim();
        const email = row.querySelector('.student-email').textContent.trim();
        const group = row.querySelector('.group-badge')?.textContent.trim() || '-';
        const status = row.querySelector('.status-badge').textContent.trim();
        csv += `"${name}","${email}","${group}","${status}"\n`;
    });

    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'presenze_<?php echo $date; ?>.csv';
    a.click();
}
</script>

<?php
echo $OUTPUT->footer();
