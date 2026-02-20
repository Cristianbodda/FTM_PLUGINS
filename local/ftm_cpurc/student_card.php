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
 * Student card page.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:view', $context);

$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$tab = optional_param('tab', 'anagrafica', PARAM_ALPHA);

// Support lookup by Moodle userid (from coach dashboard).
if (!$id && $userid) {
    $cpurc_student = \local_ftm_cpurc\cpurc_manager::get_student_by_userid($userid);
    if ($cpurc_student) {
        $id = $cpurc_student->id;
    } else {
        // Auto-create CPURC record for this user.
        $moodle_user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $newstudent = new stdClass();
        $newstudent->userid = $userid;
        $newstudent->timecreated = time();
        $newstudent->timemodified = time();
        $id = $DB->insert_record('local_ftm_cpurc_students', $newstudent);
    }
}

if (!$id) {
    throw new moodle_exception('Missing id or userid parameter');
}

// Get student data.
$student = \local_ftm_cpurc\cpurc_manager::get_student($id);
if (!$student) {
    throw new moodle_exception('Student not found');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/student_card.php', ['id' => $id, 'tab' => $tab]));
$PAGE->set_title(get_string('student_card', 'local_ftm_cpurc') . ': ' . fullname($student));
$PAGE->set_heading(get_string('student_card', 'local_ftm_cpurc'));
$PAGE->set_pagelayout('standard');

// Calculate week.
$week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($student->date_start);
$weekstatus = \local_ftm_cpurc\cpurc_manager::get_week_status($week);

// Can edit?
$canedit = has_capability('local/ftm_cpurc:edit', $context);

echo $OUTPUT->header();
?>

<style>
.student-card {
    max-width: 1200px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.card-header {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.student-info h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.student-meta {
    display: flex;
    gap: 20px;
    font-size: 14px;
    color: #666;
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.header-buttons {
    display: flex;
    gap: 10px;
}

.cpurc-btn {
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

.cpurc-btn-primary { background: #0066cc; color: white !important; }
.cpurc-btn-success { background: #28a745; color: white !important; }
.cpurc-btn-secondary { background: #6c757d; color: white !important; }
.cpurc-btn:hover { opacity: 0.9; text-decoration: none !important; color: white !important; }
a.cpurc-btn, a.cpurc-btn:visited, a.cpurc-btn:hover, a.cpurc-btn:active, a.cpurc-btn:focus { color: white !important; text-decoration: none !important; }

/* Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-new { background: #D1FAE5; color: #065F46; }
.status-progress { background: #DBEAFE; color: #1E40AF; }
.status-ending { background: #FEF3C7; color: #92400E; }
.status-extended { background: #FEE2E2; color: #991B1B; }
.status-unknown { background: #E5E7EB; color: #374151; }

.sector-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.sector-AUTOMOBILE { background: #DBEAFE; color: #1E40AF; }
.sector-MECCANICA { background: #D1FAE5; color: #065F46; }
.sector-LOGISTICA { background: #FEF3C7; color: #92400E; }
.sector-ELETTRICITA { background: #FEE2E2; color: #991B1B; }
.sector-AUTOMAZIONE { background: #F3E8FF; color: #6B21A8; }
.sector-METALCOSTRUZIONE { background: #E5E7EB; color: #374151; }
.sector-CHIMFARM { background: #FCE7F3; color: #9D174D; }

/* Tabs */
.card-tabs {
    display: flex;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}

.card-tab {
    padding: 15px 25px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    color: #666;
    text-decoration: none;
}

.card-tab:hover { background: #f8f9fa; text-decoration: none; color: #666; }
.card-tab.active { color: #0066cc; border-bottom-color: #0066cc; background: white; }

.tab-content {
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 25px;
}

/* Form */
.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    font-size: 16px;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.form-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.form-group input, .form-group select, .form-group textarea {
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-group input:disabled, .form-group select:disabled, .form-group textarea:disabled {
    background: #f8f9fa;
    color: #666;
}

.form-group .value-display {
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 14px;
    min-height: 38px;
}

/* Absences Table */
.absences-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
}

.absence-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.absence-item .code {
    font-weight: 700;
    font-size: 18px;
    color: #333;
}

.absence-item .value {
    font-size: 24px;
    font-weight: 700;
    margin: 10px 0;
}

.absence-item .label {
    font-size: 11px;
    color: #666;
}

.absence-total {
    background: #FEE2E2 !important;
}

.absence-total .value {
    color: #991B1B;
}

/* Stage section */
.stage-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
}

.stage-card h4 {
    margin: 0 0 15px 0;
    font-size: 14px;
}

/* Sector clear button */
.sector-clear {
    cursor: pointer;
    font-size: 12px;
    margin-left: 8px;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.sector-clear:hover {
    opacity: 1;
}

/* Sector delete button in badges */
.sector-delete-btn {
    cursor: pointer;
    font-size: 10px;
    margin-left: 5px;
    opacity: 0.7;
    transition: all 0.2s;
}

.sector-delete-btn:hover {
    opacity: 1;
    transform: scale(1.2);
}

/* Inline edit */
.value-display.editable {
    cursor: pointer;
    position: relative;
    padding-right: 30px;
    transition: background 0.2s;
}

.value-display.editable:hover {
    background: #e8f4fd;
}

.edit-pencil {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    opacity: 0.4;
    cursor: pointer;
}

.value-display.editable:hover .edit-pencil {
    opacity: 1;
}

.value-display.editing {
    padding: 0;
    background: white;
}

.value-display.editing input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #0066cc;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}

.edit-feedback {
    font-size: 12px;
    margin-left: 5px;
}

/* Absence item editable */
.absence-item.editable {
    cursor: pointer;
    position: relative;
    transition: background 0.2s;
}

.absence-item.editable:hover {
    background: #e8f4fd;
}

.absence-item .edit-pencil {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 11px;
    opacity: 0;
    transform: none;
}

.absence-item.editable:hover .edit-pencil {
    opacity: 1;
}

.absence-total.editable:hover {
    background: #fce4e4;
}
</style>

<div class="student-card">
    <!-- Header -->
    <div class="card-header">
        <div class="student-info">
            <h2><?php echo s($student->lastname); ?> <?php echo s($student->firstname); ?></h2>
            <div class="student-meta">
                <span><?php echo s($student->email); ?></span>
                <span><?php echo s($student->mobile ?: $student->phone ?: '-'); ?></span>
                <span><?php echo s($student->urc_office ?: '-'); ?></span>
            </div>
        </div>
        <div class="header-buttons">
            <span class="status-badge <?php echo $weekstatus['class']; ?>">
                <?php echo $weekstatus['icon']; ?> Settimana <?php echo $week > 0 ? $week : '-'; ?>
            </span>
            <?php if (!empty($student->sector_detected)): ?>
            <span class="sector-badge sector-<?php echo s($student->sector_detected); ?>">
                <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>
            </span>
            <?php endif; ?>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/report.php', ['id' => $id]); ?>" class="cpurc-btn cpurc-btn-success">
                Report
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                Torna
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="card-tabs">
        <a href="?id=<?php echo $id; ?>&tab=anagrafica" class="card-tab <?php echo $tab === 'anagrafica' ? 'active' : ''; ?>">
            <?php echo get_string('tab_anagrafica', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=percorso" class="card-tab <?php echo $tab === 'percorso' ? 'active' : ''; ?>">
            <?php echo get_string('tab_percorso', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=assenze" class="card-tab <?php echo $tab === 'assenze' ? 'active' : ''; ?>">
            <?php echo get_string('tab_assenze', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=stage" class="card-tab <?php echo $tab === 'stage' ? 'active' : ''; ?>">
            <?php echo get_string('tab_stage', 'local_ftm_cpurc'); ?>
        </a>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($tab === 'anagrafica'): ?>
            <!-- ANAGRAFICA -->
            <div class="form-section">
                <h3>Dati Personali</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('firstname', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->firstname); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('lastname', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->lastname); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('gender', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="gender" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->gender ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('birthdate', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="birthdate" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->birthdate ? date('Y-m-d', $student->birthdate) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->birthdate); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('nationality', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="nationality" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->nationality ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('permit', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="permit" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->permit ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Contatti</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('email', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->email); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('phone', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="phone" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->phone ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('mobile', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="mobile" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->mobile ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Indirizzo</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('address', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="address_street" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->address_street ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('cap', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="address_cap" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->address_cap ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('city', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="address_city" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->address_city ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Dati Amministrativi</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('avs_number', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="avs_number" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->avs_number ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('iban', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="iban" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->iban ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('civil_status', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="civil_status" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->civil_status ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'percorso'): ?>
            <!-- PERCORSO -->
            <div class="form-section">
                <h3>Dati URC</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('personal_number', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="personal_number" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->personal_number ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('urc_office', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="urc_office" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->urc_office ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('urc_consultant', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="urc_consultant" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->urc_consultant ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Percorso FTM</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('measure', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="measure" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->measure ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_start', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="date_start" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->date_start ? date('Y-m-d', $student->date_start) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->date_start); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_end_planned', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="date_end_planned" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->date_end_planned ? date('Y-m-d', $student->date_end_planned) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->date_end_planned); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_end_actual', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="date_end_actual" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->date_end_actual ? date('Y-m-d', $student->date_end_actual) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->date_end_actual) ?: '-'; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('status', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="status" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->status ?: 'Aperto'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('occupation_grade', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="occupation_grade" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo $student->occupation_grade ? $student->occupation_grade . '%' : '-'; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

            <!-- Coach FTM Assignment -->
            <?php
            $coaches = \local_ftm_cpurc\cpurc_manager::get_coaches();
            $currentCoach = \local_ftm_cpurc\cpurc_manager::get_student_coach($student->userid);
            ?>
            <div class="form-section" id="coach-assignment-section">
                <h3>Coach FTM Assegnato</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                    Assegna il coach responsabile per questo studente. Sincronizzato con tutti i plugin FTM.
                </p>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Coach Attuale</label>
                        <div id="current-coach-display" class="value-display" style="display: flex; align-items: center; gap: 10px;">
                            <?php if ($currentCoach): ?>
                                <span class="coach-badge" style="background: #E0F2FE; color: #0369A1; padding: 4px 12px; border-radius: 15px; font-weight: 600;">
                                    <?php echo s($currentCoach->firstname . ' ' . $currentCoach->lastname); ?>
                                </span>
                                <small style="color: #666;">(<?php echo s($currentCoach->email); ?>)</small>
                            <?php else: ?>
                                <span style="color: #999;">Nessun coach assegnato</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Cambia Coach <span class="sector-clear" data-target="coach_select" title="Rimuovi assegnazione">&#10060;</span></label>
                        <select id="coach_select" data-userid="<?php echo $student->userid; ?>" style="flex: 1;">
                            <option value="">-- Seleziona Coach --</option>
                            <?php foreach ($coaches as $c): ?>
                                <?php
                                $coachLabel = $c->firstname . ' ' . $c->lastname;
                                if (!empty($c->initials)) {
                                    $coachLabel .= ' (' . $c->initials . ')';
                                }
                                $selected = ($currentCoach && $currentCoach->id == $c->userid) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $c->userid; ?>" <?php echo $selected; ?>>
                                    <?php echo s($coachLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="button" id="btn-save-coach" class="cpurc-btn cpurc-btn-primary">
                        Salva Coach
                    </button>
                    <span id="coach-save-status" style="margin-left: 15px; font-size: 13px;"></span>
                </div>
            </div>

            <div class="form-section">
                <h3>Professione e Settore</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label><?php echo get_string('last_profession', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="last_profession" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->last_profession ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Settore rilevato (da professione)</label>
                        <div class="value-display">
                            <?php if (!empty($student->sector_detected)): ?>
                                <span class="sector-badge sector-<?php echo s($student->sector_detected); ?>">
                                    <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Sector Assignment -->
            <?php
            $allSectors = [
                'AUTOMOBILE' => 'Automobile',
                'MECCANICA' => 'Meccanica',
                'LOGISTICA' => 'Logistica',
                'ELETTRICITA' => 'Elettricita',
                'AUTOMAZIONE' => 'Automazione',
                'METALCOSTRUZIONE' => 'Metalcostruzione',
                'CHIMFARM' => 'Chimico-Farmaceutico',
            ];
            $studentSectors = \local_ftm_cpurc\cpurc_manager::get_student_sectors($student->userid);
            $primarySector = '';
            $secondarySector = '';
            $tertiarySector = '';

            $rank = 1;
            foreach ($studentSectors as $sec) {
                if ($sec->is_primary == 1) {
                    $primarySector = $sec->sector;
                } else if ($rank == 1 && empty($primarySector)) {
                    $primarySector = $sec->sector;
                } else if ($rank == 2 || (empty($secondarySector) && $sec->sector != $primarySector)) {
                    $secondarySector = $sec->sector;
                    $rank = 3;
                } else if (empty($tertiarySector) && $sec->sector != $primarySector && $sec->sector != $secondarySector) {
                    $tertiarySector = $sec->sector;
                }
            }
            ?>
            <div class="form-section" id="sector-assignment-section">
                <h3>Assegnazione Settori</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                    <strong>Primario:</strong> Assegna quiz e autovalutazione |
                    <strong>Secondario/Terziario:</strong> Suggerimenti per il coach
                </p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Settore Primario <span class="sector-clear" data-target="sector_primary" title="Rimuovi">&#10060;</span></label>
                        <select id="sector_primary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($primarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Settore Secondario <span class="sector-clear" data-target="sector_secondary" title="Rimuovi">&#10060;</span></label>
                        <select id="sector_secondary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Nessuno --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($secondarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Settore Terziario <span class="sector-clear" data-target="sector_tertiary" title="Rimuovi">&#10060;</span></label>
                        <select id="sector_tertiary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Nessuno --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($tertiarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="button" id="btn-save-sectors" class="cpurc-btn cpurc-btn-primary">
                        Salva Settori
                    </button>
                    <span id="sector-save-status" style="margin-left: 15px; font-size: 13px;"></span>
                </div>

                <?php if (!empty($studentSectors)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <strong style="font-size: 12px;">Settori rilevati automaticamente (da quiz):</strong>
                    <div id="detected-sectors-list" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($studentSectors as $sec): ?>
                            <span class="sector-badge sector-<?php echo $sec->sector; ?>" style="display: inline-flex; align-items: center; gap: 5px;" data-sector="<?php echo $sec->sector; ?>">
                                <?php if ($sec->is_primary): ?>&#129351;<?php endif; ?>
                                <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($sec->sector)); ?>
                                <?php if ($sec->quiz_count > 0): ?>
                                    <small>(<?php echo $sec->quiz_count; ?> quiz)</small>
                                <?php endif; ?>
                                <span class="sector-delete-btn" data-sector="<?php echo $sec->sector; ?>" data-userid="<?php echo $student->userid; ?>" title="Elimina settore">&#10060;</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <h3>Periodo Quadro</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Inizio</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="framework_start" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->framework_start ? date('Y-m-d', $student->framework_start) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->framework_start); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Fine</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="framework_end" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->framework_end ? date('Y-m-d', $student->framework_end) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->framework_end); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Indennita</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="framework_allowance" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo $student->framework_allowance ?: '-'; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('financier', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="financier" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->financier ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('unemployment_fund', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="unemployment_fund" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->unemployment_fund ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Art. 59d</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="framework_art59d" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->framework_art59d ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'assenze'): ?>
            <!-- ASSENZE -->
            <div class="form-section">
                <h3>Riepilogo Assenze</h3>
                <div class="absences-grid">
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_x" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">X</div>
                        <div class="value"><?php echo (int)$student->absence_x; ?></div>
                        <div class="label">Malattia</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_o" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">O</div>
                        <div class="value"><?php echo (int)$student->absence_o; ?></div>
                        <div class="label">Ingiustificata</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_a" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">A</div>
                        <div class="value"><?php echo (int)$student->absence_a; ?></div>
                        <div class="label">Permesso</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_b" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">B</div>
                        <div class="value"><?php echo (int)$student->absence_b; ?></div>
                        <div class="label">Colloquio</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_c" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">C</div>
                        <div class="value"><?php echo (int)$student->absence_c; ?></div>
                        <div class="label">Corso</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item absence-total<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_total" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">TOT</div>
                        <div class="value"><?php echo (int)$student->absence_total; ?></div>
                        <div class="label">Totale</div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Dettaglio Assenze (D-I)</h3>
                <div class="absences-grid">
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_d" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">D</div>
                        <div class="value"><?php echo (int)$student->absence_d; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_e" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">E</div>
                        <div class="value"><?php echo (int)$student->absence_e; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_f" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">F</div>
                        <div class="value"><?php echo (int)$student->absence_f; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_g" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">G</div>
                        <div class="value"><?php echo (int)$student->absence_g; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_h" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">H</div>
                        <div class="value"><?php echo (int)$student->absence_h; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                    <div class="absence-item<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="absence_i" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>>
                        <div class="code">I</div>
                        <div class="value"><?php echo (int)$student->absence_i; ?></div>
                        <?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Stage e Colloqui</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Colloqui di Assunzione</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="interviews" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo (int)$student->interviews; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Stage Svolti</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stages_count" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo (int)$student->stages_count; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Giorni di Stage</label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_days" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo (int)$student->stage_days; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'stage'): ?>
            <!-- STAGE -->
            <?php if (!empty($student->stage_company_name) || $canedit): ?>
            <div class="form-section">
                <h3>Dati Stage</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('stage_start', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_start" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->stage_start ? date('Y-m-d', $student->stage_start) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->stage_start); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('stage_end', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_end" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->stage_end ? date('Y-m-d', $student->stage_end) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->stage_end); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('stage_percentage', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_percentage" data-type="int" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo $student->stage_percentage ? $student->stage_percentage . '%' : '-'; ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                    </div>
                </div>

                <div class="stage-card">
                    <h4>Azienda</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome Azienda</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_company_name" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_company_name ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Indirizzo</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_company_street" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_company_street ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>CAP</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_company_cap" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_company_cap ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Citta</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_company_city" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_company_city ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Funzione</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_function" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_function ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                    </div>
                </div>

                <div class="stage-card">
                    <h4>Contatto</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_contact_name" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_contact_name ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Telefono</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_contact_phone" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_contact_phone ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="stage_contact_email" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->stage_contact_email ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($student->conclusion_date) || $canedit): ?>
                <div class="stage-card">
                    <h4>Conclusione</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Data</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="conclusion_date" data-type="date" data-sid="<?php echo $student->id; ?>" data-raw="<?php echo $student->conclusion_date ? date('Y-m-d', $student->conclusion_date) : ''; ?>"<?php endif; ?>><?php echo local_ftm_cpurc_format_date($student->conclusion_date); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Tipo</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="conclusion_type" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->conclusion_type ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            <div class="value-display<?php echo $canedit ? ' editable' : ''; ?>"<?php if ($canedit): ?> data-field="conclusion_reason" data-type="text" data-sid="<?php echo $student->id; ?>"<?php endif; ?>><?php echo s($student->conclusion_reason ?: '-'); ?><?php if ($canedit): ?><span class="edit-pencil">&#9998;</span><?php endif; ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">&#127981;</div>
                <p>Nessun dato stage disponibile per questo studente.</p>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
// Clear sector dropdown buttons
document.querySelectorAll('.sector-clear').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var targetId = this.dataset.target;
        var select = document.getElementById(targetId);
        if (select) {
            select.value = '';
        }
    });
});

// Delete detected sector buttons
document.querySelectorAll('.sector-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var sector = this.dataset.sector;
        var userid = this.dataset.userid;
        var badge = this.closest('.sector-badge');

        if (!confirm('Eliminare il settore ' + sector + '?')) {
            return;
        }

        badge.style.opacity = '0.5';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_delete_sector.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid + '&sector=' + encodeURIComponent(sector)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                badge.remove();
                ['sector_primary', 'sector_secondary', 'sector_tertiary'].forEach(function(id) {
                    var select = document.getElementById(id);
                    if (select && select.value === sector) {
                        select.value = '';
                    }
                });
            } else {
                badge.style.opacity = '1';
                alert('Errore: ' + data.message);
            }
        })
        .catch(function(error) {
            badge.style.opacity = '1';
            console.error('Error:', error);
        });
    });
});

// Clear coach dropdown button
document.querySelectorAll('.sector-clear[data-target="coach_select"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var select = document.getElementById('coach_select');
        if (select) {
            select.value = '';
        }
    });
});

// Save coach
var btnSaveCoach = document.getElementById('btn-save-coach');
if (btnSaveCoach) {
    btnSaveCoach.addEventListener('click', function() {
        var userid = document.getElementById('coach_select').dataset.userid;
        var coachid = document.getElementById('coach_select').value;

        var statusEl = document.getElementById('coach-save-status');
        var btn = this;
        var displayEl = document.getElementById('current-coach-display');

        btn.disabled = true;
        btn.innerHTML = 'Salvataggio...';
        statusEl.innerHTML = '';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_assign_coach.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid + '&coachid=' + coachid
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = 'Salva Coach';

            if (data.success) {
                statusEl.innerHTML = '<span style="color: #28a745;">' + data.message + '</span>';

                if (data.coach) {
                    displayEl.innerHTML = '<span class="coach-badge" style="background: #E0F2FE; color: #0369A1; padding: 4px 12px; border-radius: 15px; font-weight: 600;">' +
                        data.coach.name + '</span>' +
                        (data.coach.email ? '<small style="color: #666;">(' + data.coach.email + ')</small>' : '');
                } else {
                    displayEl.innerHTML = '<span style="color: #999;">Nessun coach assegnato</span>';
                }
            } else {
                statusEl.innerHTML = '<span style="color: #dc3545;">' + data.message + '</span>';
            }

            setTimeout(function() { statusEl.innerHTML = ''; }, 5000);
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = 'Salva Coach';
            statusEl.innerHTML = '<span style="color: #dc3545;">Errore di connessione</span>';
            console.error('Error:', error);
        });
    });
}

// Save sectors
var btnSaveSectors = document.getElementById('btn-save-sectors');
if (btnSaveSectors) {
    btnSaveSectors.addEventListener('click', function() {
        var userid = document.getElementById('sector_primary').dataset.userid;
        var primary = document.getElementById('sector_primary').value;
        var secondary = document.getElementById('sector_secondary').value;
        var tertiary = document.getElementById('sector_tertiary').value;

        var statusEl = document.getElementById('sector-save-status');
        var btn = this;

        btn.disabled = true;
        btn.innerHTML = 'Salvataggio...';
        statusEl.innerHTML = '';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_save_sectors.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid +
                  '&primary=' + encodeURIComponent(primary) +
                  '&secondary=' + encodeURIComponent(secondary) +
                  '&tertiary=' + encodeURIComponent(tertiary)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = 'Salva Settori';

            if (data.success) {
                statusEl.innerHTML = '<span style="color: #28a745;">' + data.message + '</span>';
            } else {
                statusEl.innerHTML = '<span style="color: #dc3545;">' + data.message + '</span>';
            }

            setTimeout(function() { statusEl.innerHTML = ''; }, 5000);
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = 'Salva Settori';
            statusEl.innerHTML = '<span style="color: #dc3545;">Errore di connessione</span>';
            console.error('Error:', error);
        });
    });
}

// --- INLINE EDITING ---
// Regular fields (value-display.editable)
document.querySelectorAll('.value-display.editable').forEach(function(el) {
    var pencil = el.querySelector('.edit-pencil');
    if (pencil) {
        pencil.addEventListener('click', function(e) {
            e.stopPropagation();
            startEdit(el);
        });
    }
});

// Absence items (absence-item.editable)
document.querySelectorAll('.absence-item.editable').forEach(function(el) {
    var pencil = el.querySelector('.edit-pencil');
    if (pencil) {
        pencil.addEventListener('click', function(e) {
            e.stopPropagation();
            startAbsenceEdit(el);
        });
    }
});

function startEdit(el) {
    if (el.classList.contains('editing')) return;

    var field = el.dataset.field;
    var type = el.dataset.type;
    var sid = el.dataset.sid;
    var currentText = el.textContent.replace('\u270E', '').trim();

    el.classList.add('editing');

    var input;
    if (type === 'date') {
        input = document.createElement('input');
        input.type = 'date';
        input.value = el.dataset.raw || '';
    } else if (type === 'int') {
        input = document.createElement('input');
        input.type = 'number';
        input.value = currentText === '-' ? '' : currentText.replace('%', '');
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.value = currentText === '-' ? '' : currentText;
    }

    el.innerHTML = '';
    el.appendChild(input);
    input.focus();
    if (input.select) input.select();

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { cancelEdit(el, currentText); }
    });

    input.addEventListener('blur', function() {
        saveField(el, sid, field, type, input.value, currentText);
    });
}

function startAbsenceEdit(el) {
    var valueDiv = el.querySelector('.value');
    if (valueDiv.querySelector('input')) return;

    var field = el.dataset.field;
    var sid = el.dataset.sid;
    var currentVal = valueDiv.textContent.trim();
    var cancelled = false;

    var input = document.createElement('input');
    input.type = 'number';
    input.value = currentVal;
    input.style.cssText = 'width: 60px; text-align: center; font-size: 20px; font-weight: 700; border: 2px solid #0066cc; border-radius: 6px; padding: 5px;';

    valueDiv.innerHTML = '';
    valueDiv.appendChild(input);
    input.focus();
    if (input.select) input.select();

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') {
            cancelled = true;
            valueDiv.textContent = currentVal;
        }
    });

    input.addEventListener('blur', function() {
        if (!cancelled) {
            saveAbsenceField(el, valueDiv, sid, field, input.value, currentVal);
        }
    });
}

function cancelEdit(el, oldText) {
    el.classList.remove('editing');
    el.innerHTML = escapeHtml(oldText) + '<span class="edit-pencil">\u270E</span>';
    bindPencil(el);
}

function saveField(el, sid, field, type, newValue, oldDisplay) {
    el.classList.remove('editing');
    el.innerHTML = '<span class="edit-feedback" style="color: #666;">...</span>';

    fetch(M.cfg.wwwroot + '/local/ftm_cpurc/ajax_save_field.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'sesskey=' + M.cfg.sesskey + '&studentid=' + sid + '&field=' + field + '&value=' + encodeURIComponent(newValue) + '&type=' + type
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var displayVal = data.display_value || newValue || '-';
            el.innerHTML = escapeHtml(displayVal) + '<span class="edit-pencil">\u270E</span>';
            if (type === 'date') el.dataset.raw = newValue;
            bindPencil(el);
            el.style.background = '#d4edda';
            setTimeout(function() { el.style.background = ''; }, 1500);
        } else {
            el.innerHTML = escapeHtml(oldDisplay) + '<span class="edit-pencil">\u270E</span>';
            bindPencil(el);
            el.style.background = '#f8d7da';
            setTimeout(function() { el.style.background = ''; }, 2000);
        }
    })
    .catch(function() {
        el.innerHTML = escapeHtml(oldDisplay) + '<span class="edit-pencil">\u270E</span>';
        bindPencil(el);
        el.style.background = '#f8d7da';
        setTimeout(function() { el.style.background = ''; }, 2000);
    });
}

function saveAbsenceField(el, valueDiv, sid, field, newValue, oldValue) {
    valueDiv.textContent = '...';

    fetch(M.cfg.wwwroot + '/local/ftm_cpurc/ajax_save_field.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'sesskey=' + M.cfg.sesskey + '&studentid=' + sid + '&field=' + field + '&value=' + encodeURIComponent(newValue) + '&type=int'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            valueDiv.textContent = data.display_value || newValue || '0';
            el.style.background = '#d4edda';
            var isTotal = el.classList.contains('absence-total');
            setTimeout(function() { el.style.background = isTotal ? '#FEE2E2' : ''; }, 1500);
            recalcAbsenceTotal();
        } else {
            valueDiv.textContent = oldValue;
            el.style.background = '#f8d7da';
            var isTotal2 = el.classList.contains('absence-total');
            setTimeout(function() { el.style.background = isTotal2 ? '#FEE2E2' : ''; }, 2000);
        }
    })
    .catch(function() {
        valueDiv.textContent = oldValue;
    });
}

function recalcAbsenceTotal() {
    var total = 0;
    var fields = ['absence_x', 'absence_o', 'absence_a', 'absence_b', 'absence_c',
                  'absence_d', 'absence_e', 'absence_f', 'absence_g', 'absence_h', 'absence_i'];
    fields.forEach(function(f) {
        var item = document.querySelector('.absence-item[data-field="' + f + '"]');
        if (item) {
            total += parseInt(item.querySelector('.value').textContent) || 0;
        }
    });
    var totalItem = document.querySelector('.absence-item[data-field="absence_total"]');
    if (totalItem) {
        totalItem.querySelector('.value').textContent = total;
    }
}

function bindPencil(el) {
    var pencil = el.querySelector('.edit-pencil');
    if (pencil) {
        pencil.addEventListener('click', function(e) {
            e.stopPropagation();
            startEdit(el);
        });
    }
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
echo $OUTPUT->footer();
