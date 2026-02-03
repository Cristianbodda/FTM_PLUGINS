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
 * Edit activity page for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manageactivities', $context);

$id = required_param('id', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/edit_activity.php', ['id' => $id]));
$PAGE->set_title(get_string('modifica', 'local_ftm_scheduler') . ' - Attività');
$PAGE->set_heading(get_string('modifica', 'local_ftm_scheduler') . ' - Attività');
$PAGE->set_pagelayout('standard');

// Get activity
global $DB;
$activity = $DB->get_record('local_ftm_activities', ['id' => $id], '*', MUST_EXIST);

// Get related data
$groups = $DB->get_records('local_ftm_groups', ['status' => 'active'], 'name ASC');
$rooms = $DB->get_records('local_ftm_rooms', null, 'name ASC');
$coaches = [];
if ($DB->get_manager()->table_exists('local_ftm_coaches')) {
    // Join with user table to get fullname
    $sql = "SELECT c.*, u.firstname, u.lastname,
                   CONCAT(u.firstname, ' ', u.lastname) AS fullname
            FROM {local_ftm_coaches} c
            JOIN {user} u ON u.id = c.userid
            WHERE c.active = 1
            ORDER BY u.lastname, u.firstname";
    $coaches = $DB->get_records_sql($sql);
}

// Handle delete
if ($delete && confirm_sesskey()) {
    $DB->delete_records('local_ftm_activities', ['id' => $id]);
    $DB->delete_records('local_ftm_enrollments', ['activityid' => $id]);
    \core\notification::success('Attività eliminata con successo');
    redirect(new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $activity->name = required_param('name', PARAM_TEXT);
    $activity->type = required_param('type', PARAM_ALPHANUMEXT);
    $activity->groupid = optional_param('groupid', null, PARAM_INT);
    $activity->roomid = optional_param('roomid', null, PARAM_INT);
    $activity->teacherid = optional_param('teacherid', null, PARAM_INT);
    $activity->max_participants = optional_param('max_participants', 10, PARAM_INT);

    // Parse date and time
    $date_str = required_param('activity_date', PARAM_TEXT);
    $time_start = required_param('time_start', PARAM_TEXT);
    $time_end = required_param('time_end', PARAM_TEXT);

    $date_ts = strtotime($date_str);
    $start_parts = explode(':', $time_start);
    $end_parts = explode(':', $time_end);

    $activity->date_start = mktime((int)$start_parts[0], (int)$start_parts[1], 0,
        date('n', $date_ts), date('j', $date_ts), date('Y', $date_ts));
    $activity->date_end = mktime((int)$end_parts[0], (int)$end_parts[1], 0,
        date('n', $date_ts), date('j', $date_ts), date('Y', $date_ts));

    $activity->timemodified = time();

    $DB->update_record('local_ftm_activities', $activity);

    \core\notification::success('Attività aggiornata con successo');
    redirect(new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']));
}

// Get group info if assigned
$group = null;
if ($activity->groupid) {
    $group = $DB->get_record('local_ftm_groups', ['id' => $activity->groupid]);
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
    border-bottom: 2px solid #0066cc;
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
.ftm-form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.ftm-form-group input:focus,
.ftm-form-group select:focus {
    border-color: #0066cc;
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
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
    background: #0066cc;
    color: white;
}
.ftm-btn-primary:hover {
    background: #0052a3;
}
.ftm-btn-secondary {
    background: #6c757d;
    color: white;
}
.ftm-btn-danger {
    background: #dc3545;
    color: white;
}
.ftm-btn-danger:hover {
    background: #c82333;
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
    <h2>Modifica Attività</h2>

    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <div class="ftm-form-group">
            <label for="name">Nome Attività</label>
            <input type="text" id="name" name="name" value="<?php echo s($activity->name); ?>" required>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="type">Tipo</label>
                <select id="type" name="type">
                    <option value="general" <?php echo $activity->type === 'general' ? 'selected' : ''; ?>>Generale</option>
                    <option value="group" <?php echo $activity->type === 'group' ? 'selected' : ''; ?>>Gruppo</option>
                    <option value="lab" <?php echo $activity->type === 'lab' ? 'selected' : ''; ?>>Laboratorio</option>
                    <option value="atelier" <?php echo $activity->type === 'atelier' ? 'selected' : ''; ?>>Atelier</option>
                    <option value="bilancio" <?php echo $activity->type === 'bilancio' ? 'selected' : ''; ?>>Bilancio</option>
                    <option value="oml" <?php echo $activity->type === 'oml' ? 'selected' : ''; ?>>OML</option>
                </select>
            </div>

            <div class="ftm-form-group">
                <label for="groupid">Gruppo</label>
                <select id="groupid" name="groupid">
                    <option value="">-- Nessun gruppo --</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g->id; ?>" <?php echo $activity->groupid == $g->id ? 'selected' : ''; ?>>
                        <?php echo s($g->name); ?> (<?php echo ucfirst($g->color); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="roomid">Aula</label>
                <select id="roomid" name="roomid">
                    <option value="">-- Nessuna aula --</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r->id; ?>" <?php echo $activity->roomid == $r->id ? 'selected' : ''; ?>>
                        <?php echo s($r->name); ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($rooms)): ?>
                    <option value="1" <?php echo $activity->roomid == 1 ? 'selected' : ''; ?>>Aula 1</option>
                    <option value="2" <?php echo $activity->roomid == 2 ? 'selected' : ''; ?>>Aula 2</option>
                    <option value="3" <?php echo $activity->roomid == 3 ? 'selected' : ''; ?>>Aula 3</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="ftm-form-group">
                <label for="teacherid">Coach</label>
                <select id="teacherid" name="teacherid">
                    <option value="">-- Nessun coach --</option>
                    <?php foreach ($coaches as $c): ?>
                    <option value="<?php echo $c->userid; ?>" <?php echo $activity->teacherid == $c->userid ? 'selected' : ''; ?>>
                        <?php echo s($c->fullname ?? $c->firstname . ' ' . $c->lastname); ?> (<?php echo s($c->initials); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="activity_date">Data</label>
                <input type="date" id="activity_date" name="activity_date"
                       value="<?php echo date('Y-m-d', $activity->date_start); ?>" required>
            </div>

            <div class="ftm-form-group">
                <label for="max_participants">Max Partecipanti</label>
                <input type="number" id="max_participants" name="max_participants"
                       value="<?php echo $activity->max_participants; ?>" min="1" max="50">
            </div>
        </div>

        <div class="ftm-form-row">
            <div class="ftm-form-group">
                <label for="time_start">Ora Inizio</label>
                <input type="time" id="time_start" name="time_start"
                       value="<?php echo date('H:i', $activity->date_start); ?>" required>
            </div>

            <div class="ftm-form-group">
                <label for="time_end">Ora Fine</label>
                <input type="time" id="time_end" name="time_end"
                       value="<?php echo date('H:i', $activity->date_end); ?>" required>
            </div>
        </div>

        <div class="ftm-actions">
            <div>
                <button type="submit" class="ftm-btn ftm-btn-primary">💾 Salva Modifiche</button>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>"
                   class="ftm-btn ftm-btn-secondary">Annulla</a>
            </div>
            <div>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/edit_activity.php', ['id' => $id, 'delete' => 1, 'sesskey' => sesskey()]); ?>"
                   class="ftm-btn ftm-btn-danger"
                   onclick="return confirm('Sei sicuro di voler eliminare questa attività?')">
                    🗑️ Elimina
                </a>
            </div>
        </div>
    </form>
</div>

<?php
echo $OUTPUT->footer();
