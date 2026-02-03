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
 * Edit external booking page for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:managerooms', $context);

$id = required_param('id', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/edit_external.php', ['id' => $id]));
$PAGE->set_title(get_string('modifica', 'local_ftm_scheduler') . ' - Progetto Esterno');
$PAGE->set_heading(get_string('modifica', 'local_ftm_scheduler') . ' - Progetto Esterno');
$PAGE->set_pagelayout('standard');

// Get booking
global $DB;
$booking = $DB->get_record('local_ftm_external_bookings', ['id' => $id], '*', MUST_EXIST);

// Get rooms
$rooms = $DB->get_records('local_ftm_rooms', null, 'name ASC');

// Handle delete
if ($delete && confirm_sesskey()) {
    $DB->delete_records('local_ftm_external_bookings', ['id' => $id]);
    \core\notification::success('Progetto esterno eliminato con successo');
    redirect(new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $booking->project_name = required_param('project_name', PARAM_TEXT);
    $booking->roomid = optional_param('roomid', null, PARAM_INT);
    $booking->responsible = optional_param('responsible', '', PARAM_TEXT);
    $booking->notes = optional_param('notes', '', PARAM_TEXT);

    // Parse date and time
    $date_str = required_param('booking_date', PARAM_TEXT);
    $time_start = required_param('time_start', PARAM_TEXT);
    $time_end = required_param('time_end', PARAM_TEXT);

    $date_ts = strtotime($date_str);
    $start_parts = explode(':', $time_start);
    $end_parts = explode(':', $time_end);

    $booking->date_start = mktime((int)$start_parts[0], (int)$start_parts[1], 0,
        date('n', $date_ts), date('j', $date_ts), date('Y', $date_ts));
    $booking->date_end = mktime((int)$end_parts[0], (int)$end_parts[1], 0,
        date('n', $date_ts), date('j', $date_ts), date('Y', $date_ts));

    $DB->update_record('local_ftm_external_bookings', $booking);

    \core\notification::success('Progetto esterno aggiornato con successo');
    redirect(new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']));
}

echo $OUTPUT->header();
?>

<style>
.ftm-edit-form {
    max-width: 700px;
    margin: 0 auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.ftm-edit-form h2 {
    margin-bottom: 25px;
    color: #333;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
}
.ftm-form-group {
    margin-bottom: 20px;
}
.ftm-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
}
.ftm-form-group input,
.ftm-form-group select,
.ftm-form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.ftm-form-group textarea {
    min-height: 100px;
    resize: vertical;
}
.ftm-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.ftm-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}
.ftm-btn-primary {
    background: #333;
    color: white;
}
.ftm-btn-primary:hover {
    background: #555;
}
.ftm-btn-secondary {
    background: #6c757d;
    color: white;
}
.ftm-btn-danger {
    background: #dc3545;
    color: white;
}
.ftm-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="ftm-edit-form">
    <h2>Modifica Progetto Esterno</h2>

    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <div class="ftm-form-group">
            <label for="project_name">Nome Progetto</label>
            <input type="text" id="project_name" name="project_name"
                   value="<?php echo s($booking->project_name); ?>" required>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="roomid">Aula</label>
                <select id="roomid" name="roomid">
                    <option value="">-- Nessuna aula --</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r->id; ?>" <?php echo $booking->roomid == $r->id ? 'selected' : ''; ?>>
                        <?php echo s($r->name); ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($rooms)): ?>
                    <option value="1" <?php echo $booking->roomid == 1 ? 'selected' : ''; ?>>Aula 1</option>
                    <option value="2" <?php echo $booking->roomid == 2 ? 'selected' : ''; ?>>Aula 2</option>
                    <option value="3" <?php echo $booking->roomid == 3 ? 'selected' : ''; ?>>Aula 3</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="ftm-form-group">
                <label for="responsible">Responsabile</label>
                <input type="text" id="responsible" name="responsible"
                       value="<?php echo s($booking->responsible); ?>">
            </div>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="booking_date">Data</label>
                <input type="date" id="booking_date" name="booking_date"
                       value="<?php echo date('Y-m-d', $booking->date_start); ?>" required>
            </div>

            <div class="ftm-form-group">
                <label>&nbsp;</label>
                <div style="padding-top: 5px; color: #666;">
                    Progetto esterno (LADI, BIT, URAR, ecc.)
                </div>
            </div>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="time_start">Ora Inizio</label>
                <input type="time" id="time_start" name="time_start"
                       value="<?php echo date('H:i', $booking->date_start); ?>" required>
            </div>

            <div class="ftm-form-group">
                <label for="time_end">Ora Fine</label>
                <input type="time" id="time_end" name="time_end"
                       value="<?php echo date('H:i', $booking->date_end); ?>" required>
            </div>
        </div>

        <div class="ftm-form-group">
            <label for="notes">Note</label>
            <textarea id="notes" name="notes"><?php echo s($booking->notes ?? ''); ?></textarea>
        </div>

        <div class="ftm-actions">
            <div>
                <button type="submit" class="ftm-btn ftm-btn-primary">💾 Salva Modifiche</button>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>"
                   class="ftm-btn ftm-btn-secondary">Annulla</a>
            </div>
            <div>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/edit_external.php', ['id' => $id, 'delete' => 1, 'sesskey' => sesskey()]); ?>"
                   class="ftm-btn ftm-btn-danger"
                   onclick="return confirm('Sei sicuro di voler eliminare questo progetto esterno?')">
                    🗑️ Elimina
                </a>
            </div>
        </div>
    </form>
</div>

<?php
echo $OUTPUT->footer();
