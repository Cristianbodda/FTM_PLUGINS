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
 * Group members management page.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:enrollstudents', $context);

$groupid = required_param('groupid', PARAM_INT);
$filter_cohort = optional_param('cohort', 0, PARAM_INT);
$filter_date_from = optional_param('date_from', '', PARAM_TEXT);
$filter_date_to = optional_param('date_to', '', PARAM_TEXT);

// Get group info
$group = $DB->get_record('local_ftm_groups', ['id' => $groupid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $groupid]));
$PAGE->set_title('Gestione Membri - ' . $group->name);
$PAGE->set_heading('Gestione Membri Gruppo');

// Get current members
$members = \local_ftm_scheduler\manager::get_group_members($groupid);
$member_ids = array_column($members, 'userid');

// Get all cohorts for filter dropdown
$cohorts = $DB->get_records('cohort', ['visible' => 1], 'name ASC');

// Build query for available students with enrollment info
$params = [];
$where_clauses = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];

// Join with cohort_members if filtering by cohort
$cohort_join = '';
if ($filter_cohort) {
    $cohort_join = "JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid";
    $params['cohortid'] = $filter_cohort;
}

// Date filters on local_student_coaching.date_start
$date_join = "LEFT JOIN {local_student_coaching} lsc ON lsc.userid = u.id";
if ($filter_date_from) {
    $from_ts = strtotime($filter_date_from);
    if ($from_ts) {
        $where_clauses[] = "(lsc.date_start >= :date_from OR lsc.date_start IS NULL)";
        $params['date_from'] = $from_ts;
    }
}
if ($filter_date_to) {
    $to_ts = strtotime($filter_date_to . ' 23:59:59');
    if ($to_ts) {
        $where_clauses[] = "(lsc.date_start <= :date_to OR lsc.date_start IS NULL)";
        $params['date_to'] = $to_ts;
    }
}

$where = implode(' AND ', $where_clauses);

$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               lsc.date_start, lsc.sector,
               (SELECT GROUP_CONCAT(c.name SEPARATOR ', ')
                FROM {cohort} c
                JOIN {cohort_members} cm2 ON cm2.cohortid = c.id
                WHERE cm2.userid = u.id) as cohort_names
        FROM {user} u
        $date_join
        $cohort_join
        WHERE $where
        ORDER BY lsc.date_start DESC, u.lastname, u.firstname";

$all_users = $DB->get_records_sql($sql, $params);

// Group users by enrollment date (week)
$users_by_date = [];
$users_no_date = [];

foreach ($all_users as $user) {
    if (in_array($user->id, $member_ids)) {
        continue; // Skip current members
    }

    if ($user->date_start) {
        // Group by Monday of the week
        $week_start = strtotime('monday this week', $user->date_start);
        $week_key = date('Y-m-d', $week_start);
        $week_num = date('W', $user->date_start);
        $week_label = "KW" . $week_num . " - " . date('d/m/Y', $week_start);

        if (!isset($users_by_date[$week_key])) {
            $users_by_date[$week_key] = [
                'label' => $week_label,
                'date' => $week_start,
                'users' => []
            ];
        }
        $users_by_date[$week_key]['users'][] = $user;
    } else {
        $users_no_date[] = $user;
    }
}

// Sort by date descending
krsort($users_by_date);

// Get colors
$colors = local_ftm_scheduler_get_colors();
$color_info = $colors[$group->color] ?? $colors['giallo'];

echo $OUTPUT->header();
?>

<style>
.ftm-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.ftm-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.group-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid <?php echo $color_info['bg']; ?>; }
.group-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; background: <?php echo $color_info['bg']; ?>; color: <?php echo $color_info['text']; ?>; font-weight: 600; }
.members-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
.members-section { background: #f8fafc; border-radius: 8px; padding: 20px; }
.members-section h3 { margin: 0 0 15px 0; font-size: 16px; color: #374151; }
.user-list { max-height: 500px; overflow-y: auto; }
.user-item { display: flex; align-items: center; gap: 10px; padding: 10px; background: #fff; border-radius: 6px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
.user-item:hover { background: #e5e7eb; }
.user-item.selected { background: #dbeafe; border-color: #3b82f6; }
.user-item input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; }
.user-info { flex: 1; min-width: 0; }
.user-name { font-weight: 500; }
.user-email { font-size: 12px; color: #6b7280; }
.user-meta { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.btn-container { margin-top: 20px; display: flex; gap: 10px; justify-content: center; }
.ftm-btn { padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; }
.ftm-btn-primary { background: #3b82f6; color: white; }
.ftm-btn-primary:hover { background: #2563eb; }
.ftm-btn-danger { background: #ef4444; color: white; }
.ftm-btn-secondary { background: #6b7280; color: white; }
.search-box { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 15px; }
.stats { display: flex; gap: 20px; margin-bottom: 15px; }
.stat { font-size: 14px; color: #6b7280; }
.stat strong { color: #111827; }
.filters-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 12px; font-weight: 500; color: #374151; }
.filter-group select, .filter-group input[type="date"] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 150px; }
.date-group-header { background: #e0e7ff; padding: 10px 15px; border-radius: 6px; margin: 15px 0 10px 0; font-weight: 600; color: #3730a3; display: flex; justify-content: space-between; align-items: center; }
.date-group-header .count { background: #3730a3; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
.select-all-btn { font-size: 11px; background: #4f46e5; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; }
.select-all-btn:hover { background: #4338ca; }
.no-date-section { background: #fef3c7; }
.no-date-section .date-group-header { background: #fbbf24; color: #78350f; }
.cohort-badge { display: inline-block; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 4px; }
.remove-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; background: #fee2e2; color: #dc2626; text-decoration: none; transition: all 0.2s; flex-shrink: 0; }
.remove-btn:hover { background: #dc2626; color: white; }
.member-item { display: flex; align-items: center; }
</style>

<div class="ftm-container">
    <div class="ftm-card">
        <div class="group-header">
            <span class="group-badge"><?php echo $color_info['emoji']; ?> <?php echo $group->name; ?></span>
            <span style="color: #6b7280;">Data inizio gruppo: <?php echo date('d/m/Y', $group->entry_date); ?> (KW<?php echo date('W', $group->entry_date); ?>)</span>
        </div>

        <div class="stats">
            <div class="stat">Membri attuali: <strong><?php echo count($members); ?></strong></div>
            <div class="stat">Stato: <strong><?php echo ucfirst($group->status); ?></strong></div>
        </div>

        <!-- Filters -->
        <form method="get" class="filters-bar">
            <input type="hidden" name="groupid" value="<?php echo $groupid; ?>">

            <div class="filter-group">
                <label>Coorte</label>
                <select name="cohort" onchange="this.form.submit()">
                    <option value="0">-- Tutte le coorti --</option>
                    <?php foreach ($cohorts as $cohort): ?>
                        <option value="<?php echo $cohort->id; ?>" <?php echo $filter_cohort == $cohort->id ? 'selected' : ''; ?>>
                            <?php echo format_string($cohort->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Data Iscrizione Da</label>
                <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>">
            </div>

            <div class="filter-group">
                <label>Data Iscrizione A</label>
                <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>">
            </div>

            <button type="submit" class="ftm-btn ftm-btn-primary" style="padding: 8px 16px;">Filtra</button>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $groupid]); ?>" class="ftm-btn ftm-btn-secondary" style="padding: 8px 16px;">Reset</a>
        </form>

        <form method="post" action="<?php echo new moodle_url('/local/ftm_scheduler/action.php'); ?>" id="addMembersForm">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="action" value="add_group_members">
            <input type="hidden" name="groupid" value="<?php echo $groupid; ?>">

            <div class="members-grid">
                <!-- Current Members -->
                <div class="members-section">
                    <h3>Membri Attuali del Gruppo (<?php echo count($members); ?>)</h3>
                    <div class="user-list">
                        <?php if (empty($members)): ?>
                            <p style="color: #9ca3af; text-align: center; padding: 20px;">Nessun membro nel gruppo</p>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <div class="user-item member-item" style="cursor: default;">
                                    <div class="user-info">
                                        <div class="user-name"><?php echo fullname($member); ?></div>
                                        <div class="user-email"><?php echo $member->email; ?></div>
                                    </div>
                                    <span style="font-size: 12px; color: #6b7280; margin-right: 10px;">Sett. <?php echo $member->current_week; ?></span>
                                    <a href="<?php echo new moodle_url('/local/ftm_scheduler/action.php', [
                                        'action' => 'remove_group_member',
                                        'groupid' => $groupid,
                                        'userid' => $member->userid,
                                        'sesskey' => sesskey()
                                    ]); ?>"
                                       class="remove-btn"
                                       onclick="return confirm('Sei sicuro di voler rimuovere <?php echo fullname($member); ?> dal gruppo?');"
                                       title="Rimuovi dal gruppo">
                                        🗑️
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available Users -->
                <div class="members-section">
                    <h3>Studenti Disponibili
                        <span style="font-weight: normal; color: #6b7280;">
                            (<?php echo count($all_users) - count($member_ids); ?> trovati)
                        </span>
                    </h3>
                    <input type="text" class="search-box" id="searchUsers" placeholder="Cerca per nome o email..." onkeyup="filterUsers()">

                    <div class="user-list" id="userList">
                        <?php foreach ($users_by_date as $week_key => $week_data): ?>
                            <div class="date-group-header">
                                <span><?php echo $week_data['label']; ?></span>
                                <span>
                                    <span class="count"><?php echo count($week_data['users']); ?></span>
                                    <button type="button" class="select-all-btn" onclick="selectGroup('<?php echo $week_key; ?>')">Seleziona tutti</button>
                                </span>
                            </div>
                            <?php foreach ($week_data['users'] as $user): ?>
                                <label class="user-item" data-name="<?php echo strtolower(fullname($user) . ' ' . $user->email); ?>" data-group="<?php echo $week_key; ?>">
                                    <input type="checkbox" name="userids[]" value="<?php echo $user->id; ?>">
                                    <div class="user-info">
                                        <div class="user-name"><?php echo fullname($user); ?></div>
                                        <div class="user-email"><?php echo $user->email; ?></div>
                                        <div class="user-meta">
                                            <?php if ($user->date_start): ?>
                                                Iscritto: <?php echo date('d/m/Y', $user->date_start); ?>
                                            <?php endif; ?>
                                            <?php if ($user->sector): ?>
                                                | <?php echo $user->sector; ?>
                                            <?php endif; ?>
                                            <?php if ($user->cohort_names): ?>
                                                <br><?php
                                                $cohort_arr = explode(', ', $user->cohort_names);
                                                foreach ($cohort_arr as $cn) {
                                                    echo '<span class="cohort-badge">' . $cn . '</span>';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <?php if (!empty($users_no_date)): ?>
                            <div class="no-date-section">
                                <div class="date-group-header">
                                    <span>Senza data iscrizione</span>
                                    <span>
                                        <span class="count"><?php echo count($users_no_date); ?></span>
                                        <button type="button" class="select-all-btn" onclick="selectGroup('no-date')">Seleziona tutti</button>
                                    </span>
                                </div>
                                <?php foreach ($users_no_date as $user): ?>
                                    <label class="user-item" data-name="<?php echo strtolower(fullname($user) . ' ' . $user->email); ?>" data-group="no-date">
                                        <input type="checkbox" name="userids[]" value="<?php echo $user->id; ?>">
                                        <div class="user-info">
                                            <div class="user-name"><?php echo fullname($user); ?></div>
                                            <div class="user-email"><?php echo $user->email; ?></div>
                                            <?php if ($user->cohort_names): ?>
                                                <div class="user-meta">
                                                    <?php
                                                    $cohort_arr = explode(', ', $user->cohort_names);
                                                    foreach ($cohort_arr as $cn) {
                                                        echo '<span class="cohort-badge">' . $cn . '</span>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($users_by_date) && empty($users_no_date)): ?>
                            <p style="color: #9ca3af; text-align: center; padding: 20px;">Nessuno studente trovato con i filtri selezionati</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="btn-container">
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>" class="ftm-btn ftm-btn-secondary">Indietro</a>
                <button type="submit" class="ftm-btn ftm-btn-primary">Aggiungi Selezionati al Gruppo</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterUsers() {
    var input = document.getElementById('searchUsers');
    var filter = input.value.toLowerCase();
    var items = document.querySelectorAll('#userList .user-item');

    items.forEach(function(item) {
        var name = item.getAttribute('data-name');
        if (name && name.indexOf(filter) > -1) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectGroup(groupKey) {
    var items = document.querySelectorAll('#userList .user-item[data-group="' + groupKey + '"]');
    var allChecked = true;

    // Check if all are already checked
    items.forEach(function(item) {
        if (item.style.display !== 'none') {
            var checkbox = item.querySelector('input[type="checkbox"]');
            if (!checkbox.checked) {
                allChecked = false;
            }
        }
    });

    // Toggle all
    items.forEach(function(item) {
        if (item.style.display !== 'none') {
            var checkbox = item.querySelector('input[type="checkbox"]');
            checkbox.checked = !allChecked;
        }
    });
}
</script>

<?php
echo $OUTPUT->footer();
