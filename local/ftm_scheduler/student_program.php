<?php
/**
 * Student Individual Program - Programma Individuale Studente
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
// Coach o Segreteria possono accedere
$canmanage = has_capability('local/ftm_scheduler:manage', $context);
$cancoach = has_capability('local/ftm_scheduler:markattendance', $context);

if (!$canmanage && !$cancoach) {
    require_capability('local/ftm_scheduler:view', $context);
}

$userid = required_param('userid', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);

// Get student info
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$group = $DB->get_record('local_ftm_groups', ['id' => $groupid], '*', MUST_EXIST);

// Verify student is member of group
$membership = $DB->get_record('local_ftm_group_members', ['userid' => $userid, 'groupid' => $groupid]);
if (!$membership) {
    throw new moodle_exception('studentnotingroup', 'local_ftm_scheduler');
}

// Get student's primary sector
$primary_sector = '';
$sector_record = $DB->get_record('local_student_sectors', ['userid' => $userid, 'priority' => 'primary']);
if ($sector_record) {
    $primary_sector = $sector_record->sector;
}

// Get assigned coach
$coach = null;
$coaching = $DB->get_record('local_student_coaching', ['userid' => $userid]);
if ($coaching && $coaching->coachid) {
    $coach = $DB->get_record('user', ['id' => $coaching->coachid]);
}

// Get colors
$colors = local_ftm_scheduler_get_colors();
$color_info = $colors[$group->color] ?? $colors['giallo'];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/student_program.php', ['userid' => $userid, 'groupid' => $groupid]));
$PAGE->set_title('Programma Individuale - ' . fullname($student));
$PAGE->set_heading('Programma Individuale');

// Get or generate program
$program = local_ftm_scheduler_get_student_program($userid, $groupid);

// Get assigned tests
$tests = $DB->get_records('local_ftm_student_tests', ['userid' => $userid, 'groupid' => $groupid], 'test_code ASC');

// Test catalog (based on sector)
$test_catalog = local_ftm_scheduler_get_test_catalog($primary_sector);

// Days and slots
$days = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
$slots = [
    'matt' => ['label' => 'Mattina', 'time' => '08:30-11:45'],
    'pom' => ['label' => 'Pomeriggio', 'time' => '13:15-16:30']
];

echo $OUTPUT->header();
?>

<style>
.student-program {
    max-width: 1400px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.program-header {
    background: linear-gradient(135deg, <?php echo $color_info['hex']; ?> 0%, <?php echo $color_info['hex']; ?>aa 100%);
    color: <?php echo $group->color === 'giallo' ? '#333' : 'white'; ?>;
    padding: 25px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}

.program-header h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    color: inherit !important;
}

.header-info {
    font-size: 14px;
}

.header-info strong {
    display: block;
    font-size: 12px;
    opacity: 0.8;
    margin-bottom: 3px;
}

.header-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    font-size: 12px;
    margin-top: 5px;
}

.section-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    margin-bottom: 25px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 20px;
}

/* Calendar Grid */
.calendar-grid {
    display: grid;
    grid-template-columns: 100px repeat(5, 1fr);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    background: #f8f9fa;
    padding: 10px;
    font-weight: 600;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
}

.week-label {
    background: #e9ecef;
    padding: 10px;
    font-weight: 600;
    text-align: center;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.week-label .week-num {
    font-size: 16px;
    color: #0066cc;
}

.week-label .week-type {
    font-size: 10px;
    color: #666;
    margin-top: 3px;
}

.day-cell {
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    min-height: 80px;
}

.day-cell:last-child {
    border-right: none;
}

.slot-row {
    display: flex;
    flex-direction: column;
}

.time-slot {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
    min-height: 40px;
    cursor: pointer;
    transition: background 0.2s;
}

.time-slot:last-child {
    border-bottom: none;
}

.time-slot:hover {
    background: #f8f9fa;
}

.time-slot.presenza {
    background: #D1FAE5;
    border-left: 3px solid #10B981;
}

.time-slot.remoto {
    background: #DBEAFE;
    border-left: 3px solid #3B82F6;
}

.time-slot.empty {
    background: #f8f9fa;
    color: #999;
}

.slot-time {
    font-size: 10px;
    color: #666;
    margin-bottom: 3px;
}

.slot-activity {
    font-size: 12px;
    font-weight: 500;
}

.slot-details {
    font-size: 10px;
    color: #666;
    margin-top: 2px;
}

.slot-editable {
    position: relative;
}

.slot-editable::after {
    content: '✏️';
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 10px;
    opacity: 0;
    transition: opacity 0.2s;
}

.slot-editable:hover::after {
    opacity: 1;
}

.week-fixed .time-slot {
    cursor: default;
}

.week-fixed .slot-editable::after {
    display: none;
}

/* Tests Section */
.tests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.test-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.test-card.assigned {
    background: #D1FAE5;
    border-color: #10B981;
}

.test-card.completed {
    background: #E5E7EB;
    border-color: #6B7280;
}

.test-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.test-info {
    flex: 1;
}

.test-code {
    font-weight: 600;
    font-size: 14px;
}

.test-name {
    font-size: 12px;
    color: #666;
}

.test-type {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #E5E7EB;
    color: #374151;
}

.test-type.teorico {
    background: #DBEAFE;
    color: #1E40AF;
}

.test-type.pratico {
    background: #FEF3C7;
    color: #92400E;
}

/* Buttons */
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
.btn-success { background: #28a745; color: white !important; }
.btn-secondary { background: #6c757d; color: white !important; }
.btn-warning { background: #ffc107; color: #333 !important; }
.btn:hover { opacity: 0.9; text-decoration: none; }

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h4 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

/* Legend */
.legend {
    display: flex;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-color.presenza { background: #D1FAE5; border-left: 3px solid #10B981; }
.legend-color.remoto { background: #DBEAFE; border-left: 3px solid #3B82F6; }

/* Print styles */
@media print {
    .no-print { display: none !important; }
    .program-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .time-slot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<div class="student-program">
    <!-- Header -->
    <div class="program-header">
        <div>
            <h2><?php echo $color_info['emoji']; ?> Programma Individuale</h2>
            <div class="header-info">
                <strong>Studente</strong>
                <?php echo fullname($student); ?>
                <span class="header-badge"><?php echo s($student->email); ?></span>
            </div>
        </div>
        <div>
            <div class="header-info">
                <strong>Gruppo</strong>
                <?php echo s($group->name); ?>
                <span class="header-badge">KW<?php echo str_pad($group->calendar_week, 2, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="header-info" style="margin-top: 10px;">
                <strong>Periodo</strong>
                <?php echo date('d/m/Y', $group->entry_date); ?> - <?php echo date('d/m/Y', $group->planned_end_date); ?>
            </div>
        </div>
        <div>
            <div class="header-info">
                <strong>Coach Assegnato</strong>
                <?php echo $coach ? fullname($coach) : '<em>Non assegnato</em>'; ?>
            </div>
            <div class="header-info" style="margin-top: 10px;">
                <strong>Settore Primario</strong>
                <?php echo $primary_sector ?: '<em>Non rilevato</em>'; ?>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="legend no-print">
        <div class="legend-item">
            <div class="legend-color presenza"></div>
            <span>In Presenza</span>
        </div>
        <div class="legend-item">
            <div class="legend-color remoto"></div>
            <span>Remoto</span>
        </div>
        <div class="legend-item">
            <span>🔒 Settimana 1 = Non modificabile</span>
        </div>
        <div class="legend-item">
            <span>✏️ Settimane 2-6 = Modificabili dal Coach/Segreteria</span>
        </div>
    </div>

    <!-- Calendar Section -->
    <div class="section-card">
        <div class="section-header">
            <h3>📅 Calendario 6 Settimane</h3>
            <div class="btn-group no-print">
                <button type="button" class="btn btn-secondary" onclick="window.print();">🖨️ Stampa</button>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/export_student_program.php', ['userid' => $userid, 'groupid' => $groupid, 'format' => 'excel']); ?>" class="btn btn-success">📊 Excel</a>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/export_student_program.php', ['userid' => $userid, 'groupid' => $groupid, 'format' => 'pdf']); ?>" class="btn btn-warning">📄 PDF</a>
            </div>
        </div>
        <div class="section-body">
            <?php for ($week = 1; $week <= 6; $week++):
                $week_class = ($week == 1) ? 'week-fixed' : 'week-editable';
                $week_type = ($week == 1) ? 'In Presenza' : (($week == 2) ? 'Mista' : 'Remoto');

                // Calculate actual dates for this week
                $week_start = $group->entry_date + (($week - 1) * 7 * 86400);
            ?>
            <h4 style="margin: 20px 0 10px 0;">Settimana <?php echo $week; ?> <?php echo $week == 1 ? '🔒' : '✏️'; ?> <small style="color: #666; font-weight: normal;">(<?php echo date('d/m', $week_start); ?> - <?php echo date('d/m', $week_start + 4*86400); ?>)</small></h4>
            <div class="calendar-grid <?php echo $week_class; ?>">
                <!-- Header row -->
                <div class="calendar-header"></div>
                <?php for ($day = 1; $day <= 5; $day++): ?>
                    <div class="calendar-header">
                        <?php echo $days[$day]; ?><br>
                        <small><?php echo date('d/m', $week_start + ($day - 1) * 86400); ?></small>
                    </div>
                <?php endfor; ?>

                <!-- Time slots row -->
                <?php foreach ($slots as $slot_key => $slot_info): ?>
                    <div class="week-label">
                        <span><?php echo $slot_info['label']; ?></span>
                        <span class="week-type"><?php echo $slot_info['time']; ?></span>
                    </div>
                    <?php for ($day = 1; $day <= 5; $day++):
                        // Find activity for this slot
                        $activity = null;
                        foreach ($program as $p) {
                            if ($p->week_number == $week && $p->day_of_week == $day && $p->time_slot == $slot_key) {
                                $activity = $p;
                                break;
                            }
                        }

                        $slot_class = $activity ? $activity->activity_type : 'empty';
                        $editable_class = ($week > 1 && ($canmanage || $cancoach)) ? 'slot-editable' : '';
                    ?>
                    <div class="day-cell">
                        <div class="time-slot <?php echo $slot_class; ?> <?php echo $editable_class; ?>"
                             <?php if ($week > 1 && ($canmanage || $cancoach)): ?>
                             onclick="editSlot(<?php echo $week; ?>, <?php echo $day; ?>, '<?php echo $slot_key; ?>')"
                             <?php endif; ?>
                             data-week="<?php echo $week; ?>"
                             data-day="<?php echo $day; ?>"
                             data-slot="<?php echo $slot_key; ?>">
                            <?php if ($activity && $activity->activity_name): ?>
                                <div class="slot-activity"><?php echo s($activity->activity_name); ?></div>
                                <?php if ($activity->activity_details): ?>
                                    <div class="slot-details"><?php echo s($activity->activity_details); ?></div>
                                <?php endif; ?>
                                <?php if ($activity->location): ?>
                                    <div class="slot-details">📍 <?php echo s($activity->location); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="slot-activity" style="color: #999;">-</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Tests Section -->
    <div class="section-card">
        <div class="section-header">
            <h3>📝 Test Assegnati</h3>
            <?php if ($canmanage || $cancoach): ?>
            <button type="button" class="btn btn-primary no-print" onclick="openTestModal();">➕ Gestisci Test</button>
            <?php endif; ?>
        </div>
        <div class="section-body">
            <?php if (empty($tests)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">Nessun test assegnato. <?php if ($canmanage || $cancoach): ?>Clicca "Gestisci Test" per assegnare i test.<?php endif; ?></p>
            <?php else: ?>
                <div class="tests-grid">
                    <?php foreach ($tests as $test): ?>
                    <div class="test-card <?php echo $test->status; ?>">
                        <input type="checkbox" class="test-checkbox" checked disabled>
                        <div class="test-info">
                            <div class="test-code"><?php echo s($test->test_code); ?></div>
                            <div class="test-name"><?php echo s($test->test_name); ?></div>
                        </div>
                        <span class="test-type <?php echo $test->test_type; ?>"><?php echo ucfirst($test->test_type); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="btn-group no-print" style="margin-top: 20px;">
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/group.php', ['id' => $groupid]); ?>" class="btn btn-secondary">← Torna al Gruppo</a>
        <?php if ($canmanage || $cancoach): ?>
        <button type="button" class="btn btn-primary" onclick="saveProgram();">💾 Salva Modifiche</button>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Edit Slot -->
<div class="modal-overlay" id="modal-edit-slot">
    <div class="modal">
        <div class="modal-header">
            <h4>✏️ Modifica Attività</h4>
            <button class="modal-close" onclick="closeModal('edit-slot')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-week">
            <input type="hidden" id="edit-day">
            <input type="hidden" id="edit-slot">

            <div class="form-group">
                <label>Tipo</label>
                <select id="edit-type">
                    <option value="presenza">In Presenza</option>
                    <option value="remoto">Remoto</option>
                </select>
            </div>

            <div class="form-group">
                <label>Attività</label>
                <select id="edit-activity-select">
                    <option value="">-- Seleziona o scrivi manualmente --</option>
                    <option value="Rilevamento competenze">Rilevamento competenze</option>
                    <option value="Laboratorio approfondimento">Laboratorio approfondimento</option>
                    <option value="Atelier CVBD">Atelier CVBD</option>
                    <option value="Newsletter e redazione">Newsletter e redazione</option>
                    <option value="Call con coach">Call con coach</option>
                    <option value="Redazione lettere">Redazione lettere</option>
                    <option value="Ricerca lavoro">Ricerca lavoro</option>
                    <option value="custom">Altro (personalizzato)</option>
                </select>
                <input type="text" id="edit-activity-custom" placeholder="Inserisci attività personalizzata" style="display: none; margin-top: 10px;">
            </div>

            <div class="form-group">
                <label>Dettagli (orario call, note...)</label>
                <textarea id="edit-details" rows="2" placeholder="Es: Call ore 10:00, Aula 2..."></textarea>
            </div>

            <div class="form-group">
                <label>Luogo</label>
                <input type="text" id="edit-location" placeholder="Es: Aula 1, Remoto, Teams...">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('edit-slot')">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="saveSlot()">💾 Salva</button>
        </div>
    </div>
</div>

<!-- Modal: Manage Tests -->
<div class="modal-overlay" id="modal-tests">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h4>📝 Gestisci Test Assegnati</h4>
            <button class="modal-close" onclick="closeModal('tests')">×</button>
        </div>
        <div class="modal-body">
            <p>Seleziona i test da assegnare allo studente. I test suggeriti sono basati sul settore: <strong><?php echo $primary_sector ?: 'Non definito'; ?></strong></p>
            <div class="tests-grid" id="test-catalog">
                <?php foreach ($test_catalog as $test):
                    $is_assigned = isset($tests[$test['code']]) || $DB->record_exists('local_ftm_student_tests', ['userid' => $userid, 'groupid' => $groupid, 'test_code' => $test['code']]);
                ?>
                <div class="test-card <?php echo $is_assigned ? 'assigned' : ''; ?>">
                    <input type="checkbox" class="test-checkbox" name="tests[]" value="<?php echo $test['code']; ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
                    <div class="test-info">
                        <div class="test-code"><?php echo s($test['code']); ?></div>
                        <div class="test-name"><?php echo s($test['name']); ?></div>
                    </div>
                    <span class="test-type <?php echo $test['type']; ?>"><?php echo ucfirst($test['type']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('tests')">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="saveTests()">💾 Salva Test</button>
        </div>
    </div>
</div>

<script>
const userid = <?php echo $userid; ?>;
const groupid = <?php echo $groupid; ?>;
const sesskey = '<?php echo sesskey(); ?>';
const wwwroot = '<?php echo $CFG->wwwroot; ?>';

// Store program data for saving
let programChanges = {};

function openModal(id) {
    document.getElementById('modal-' + id).classList.add('active');
}

function closeModal(id) {
    document.getElementById('modal-' + id).classList.remove('active');
}

function editSlot(week, day, slot) {
    // Store current slot being edited
    document.getElementById('edit-week').value = week;
    document.getElementById('edit-day').value = day;
    document.getElementById('edit-slot').value = slot;

    // Find current values
    const cell = document.querySelector(`[data-week="${week}"][data-day="${day}"][data-slot="${slot}"]`);
    const currentType = cell.classList.contains('remoto') ? 'remoto' : 'presenza';
    const currentActivity = cell.querySelector('.slot-activity')?.textContent?.trim() || '';
    const currentDetails = cell.querySelector('.slot-details')?.textContent?.replace('📍 ', '') || '';

    // Set form values
    document.getElementById('edit-type').value = currentType;
    document.getElementById('edit-details').value = currentDetails;

    // Check if activity is in the dropdown
    const activitySelect = document.getElementById('edit-activity-select');
    let found = false;
    for (let option of activitySelect.options) {
        if (option.value === currentActivity) {
            activitySelect.value = currentActivity;
            found = true;
            break;
        }
    }
    if (!found && currentActivity && currentActivity !== '-') {
        activitySelect.value = 'custom';
        document.getElementById('edit-activity-custom').style.display = 'block';
        document.getElementById('edit-activity-custom').value = currentActivity;
    }

    openModal('edit-slot');
}

// Show/hide custom activity field
document.getElementById('edit-activity-select').addEventListener('change', function() {
    const customField = document.getElementById('edit-activity-custom');
    if (this.value === 'custom') {
        customField.style.display = 'block';
        customField.focus();
    } else {
        customField.style.display = 'none';
    }
});

function saveSlot() {
    const week = document.getElementById('edit-week').value;
    const day = document.getElementById('edit-day').value;
    const slot = document.getElementById('edit-slot').value;
    const type = document.getElementById('edit-type').value;
    let activity = document.getElementById('edit-activity-select').value;
    if (activity === 'custom') {
        activity = document.getElementById('edit-activity-custom').value;
    }
    const details = document.getElementById('edit-details').value;
    const location = document.getElementById('edit-location').value;

    // Store change
    const key = `${week}-${day}-${slot}`;
    programChanges[key] = {
        week: week,
        day: day,
        slot: slot,
        type: type,
        activity: activity,
        details: details,
        location: location
    };

    // Update UI
    const cell = document.querySelector(`[data-week="${week}"][data-day="${day}"][data-slot="${slot}"]`);
    cell.className = `time-slot ${type} slot-editable`;

    let html = '';
    if (activity) {
        html += `<div class="slot-activity">${activity}</div>`;
        if (details) {
            html += `<div class="slot-details">${details}</div>`;
        }
        if (location) {
            html += `<div class="slot-details">📍 ${location}</div>`;
        }
    } else {
        html = '<div class="slot-activity" style="color: #999;">-</div>';
    }
    cell.innerHTML = html;

    closeModal('edit-slot');
}

function openTestModal() {
    openModal('tests');
}

function saveTests() {
    const checkboxes = document.querySelectorAll('#test-catalog input[type="checkbox"]');
    const selectedTests = [];
    checkboxes.forEach(cb => {
        if (cb.checked) {
            selectedTests.push(cb.value);
        }
    });

    // Save via AJAX
    fetch(wwwroot + '/local/ftm_scheduler/ajax_student_program.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=save_tests&sesskey=${sesskey}&userid=${userid}&groupid=${groupid}&tests=${selectedTests.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test salvati con successo!');
            location.reload();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });

    closeModal('tests');
}

function saveProgram() {
    if (Object.keys(programChanges).length === 0) {
        alert('Nessuna modifica da salvare.');
        return;
    }

    fetch(wwwroot + '/local/ftm_scheduler/ajax_student_program.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=save_program&sesskey=${sesskey}&userid=${userid}&groupid=${groupid}&changes=${encodeURIComponent(JSON.stringify(programChanges))}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Programma salvato con successo!');
            programChanges = {};
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();

// ====================
// HELPER FUNCTIONS
// ====================

/**
 * Get or generate student program
 */
function local_ftm_scheduler_get_student_program($userid, $groupid) {
    global $DB;

    // Check if program exists
    $program = $DB->get_records('local_ftm_student_program', ['userid' => $userid, 'groupid' => $groupid]);

    if (empty($program)) {
        // Generate default program
        $program = local_ftm_scheduler_generate_default_program($userid, $groupid);
    }

    return $program;
}

/**
 * Generate default program based on template
 */
function local_ftm_scheduler_generate_default_program($userid, $groupid) {
    global $DB, $USER;

    $now = time();
    $program = [];

    // Week 1 template (fixed)
    $week1_activities = [
        // Monday
        ['week' => 1, 'day' => 1, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Inizio misura', 'editable' => 0],
        ['week' => 1, 'day' => 1, 'slot' => 'pom', 'type' => 'presenza', 'name' => 'Piano d\'azione, spiegazioni', 'editable' => 0],
        // Tuesday
        ['week' => 1, 'day' => 2, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Test settoriale d\'entrata', 'editable' => 0],
        ['week' => 1, 'day' => 2, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 0],
        // Wednesday
        ['week' => 1, 'day' => 3, 'slot' => 'matt', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 0],
        ['week' => 1, 'day' => 3, 'slot' => 'pom', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 0],
        // Thursday
        ['week' => 1, 'day' => 4, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Atelier CVBD, redazione CV', 'editable' => 0],
        ['week' => 1, 'day' => 4, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 0],
        // Friday
        ['week' => 1, 'day' => 5, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Newsletter e redazione', 'editable' => 0],
        ['week' => 1, 'day' => 5, 'slot' => 'pom', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 0],
    ];

    // Week 2 template (editable)
    $week2_activities = [
        ['week' => 2, 'day' => 1, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Rilevamento competenze', 'details' => 'Da scegliere', 'editable' => 1],
        ['week' => 2, 'day' => 1, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 1],
        ['week' => 2, 'day' => 2, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Laboratorio approfondimento', 'editable' => 1],
        ['week' => 2, 'day' => 2, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 1],
        ['week' => 2, 'day' => 3, 'slot' => 'matt', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 1],
        ['week' => 2, 'day' => 3, 'slot' => 'pom', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 1],
        ['week' => 2, 'day' => 4, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Rilevamento competenze', 'details' => 'Da scegliere', 'editable' => 1],
        ['week' => 2, 'day' => 4, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 1],
        ['week' => 2, 'day' => 5, 'slot' => 'matt', 'type' => 'presenza', 'name' => 'Laboratorio approfondimento', 'editable' => 1],
        ['week' => 2, 'day' => 5, 'slot' => 'pom', 'type' => 'presenza', 'name' => '', 'editable' => 1],
    ];

    // Weeks 3-6 template (remote by default, editable)
    $remote_activities = [];
    for ($week = 3; $week <= 6; $week++) {
        for ($day = 1; $day <= 5; $day++) {
            $remote_activities[] = ['week' => $week, 'day' => $day, 'slot' => 'matt', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 1];
            $remote_activities[] = ['week' => $week, 'day' => $day, 'slot' => 'pom', 'type' => 'remoto', 'name' => 'REMOTO', 'editable' => 1];
        }
    }

    // Combine all activities
    $all_activities = array_merge($week1_activities, $week2_activities, $remote_activities);

    // Insert into database
    foreach ($all_activities as $act) {
        $record = new stdClass();
        $record->userid = $userid;
        $record->groupid = $groupid;
        $record->week_number = $act['week'];
        $record->day_of_week = $act['day'];
        $record->time_slot = $act['slot'];
        $record->activity_type = $act['type'];
        $record->activity_name = $act['name'];
        $record->activity_details = $act['details'] ?? '';
        $record->location = '';
        $record->is_editable = $act['editable'];
        $record->status = 'pending';
        $record->assigned_by = $USER->id;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record('local_ftm_student_program', $record);
        $program[] = $record;
    }

    return $program;
}

/**
 * Get test catalog based on sector
 */
function local_ftm_scheduler_get_test_catalog($sector = '') {
    // Full test catalog
    $catalog = [
        ['code' => 'T01', 'name' => 'Matematica', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE', 'ELETTRICITA']],
        ['code' => 'T02', 'name' => 'Disegno tecnico', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        ['code' => 'T03', 'name' => 'Materiali di base', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        ['code' => 'T04', 'name' => 'Simbologia disegno tecnico', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'ELETTRICITA']],
        ['code' => 'T05', 'name' => 'Programmazione macchine', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE']],
        ['code' => 'T06', 'name' => 'Disegno d\'officina', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        ['code' => 'T07', 'name' => 'Parametri di lavorazione', 'type' => 'teorico', 'sectors' => ['MECCANICA']],
        ['code' => 'T08', 'name' => 'Fresa', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        ['code' => 'T09', 'name' => 'Tornio', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        ['code' => 'T10', 'name' => 'Lavorazioni trapano', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        ['code' => 'T11', 'name' => 'Metrologia base', 'type' => 'pratico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        ['code' => 'T12', 'name' => 'Montaggio avanzato', 'type' => 'pratico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE']],
        ['code' => 'T13', 'name' => 'Elementi elettronici', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        ['code' => 'T14', 'name' => 'Elettronica digitale', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        ['code' => 'T15', 'name' => 'Corrente alternata', 'type' => 'teorico', 'sectors' => ['ELETTRICITA']],
        ['code' => 'T16', 'name' => 'Elettricità', 'type' => 'teorico', 'sectors' => ['ELETTRICITA']],
        ['code' => 'T17', 'name' => 'Utilizzo strumento di misura', 'type' => 'pratico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        ['code' => 'T18', 'name' => 'Elettronica', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        ['code' => 'T19', 'name' => 'Realizzazione quadro', 'type' => 'pratico', 'sectors' => ['ELETTRICITA']],
        ['code' => 'T20', 'name' => 'Ricerca guasti su quadro', 'type' => 'pratico', 'sectors' => ['ELETTRICITA']],
        ['code' => 'T21', 'name' => 'PLC', 'type' => 'teorico', 'sectors' => ['AUTOMAZIONE', 'ELETTRICITA']],
        ['code' => 'T22', 'name' => 'Office e PC', 'type' => 'teorico', 'sectors' => ['LOGISTICA', 'CHIMFARM']],
        ['code' => 'T23', 'name' => 'Linguaggio di programmazione', 'type' => 'pratico', 'sectors' => ['AUTOMAZIONE']],
        ['code' => 'T24', 'name' => 'Pneumatica', 'type' => 'pratico', 'sectors' => ['AUTOMAZIONE', 'MECCANICA']],
        ['code' => 'T25', 'name' => 'Rilevamento schema', 'type' => 'pratico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
    ];

    // If sector specified, prioritize those tests
    if ($sector) {
        // Sort to put sector-related tests first
        usort($catalog, function($a, $b) use ($sector) {
            $a_match = in_array($sector, $a['sectors']) ? 0 : 1;
            $b_match = in_array($sector, $b['sectors']) ? 0 : 1;
            if ($a_match === $b_match) {
                return strcmp($a['code'], $b['code']);
            }
            return $a_match - $b_match;
        });
    }

    return $catalog;
}
