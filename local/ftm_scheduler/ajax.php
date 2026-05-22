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
 * AJAX handler for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_scheduler:view', $context);

$action = required_param('action', PARAM_ALPHANUMEXT);

header('Content-Type: application/json; charset=utf-8');

$result = ['success' => false];

try {
    switch ($action) {
        case 'get_activity':
            $id = required_param('id', PARAM_INT);
            $activity = \local_ftm_scheduler\manager::get_activity($id);

            if ($activity) {
                $colors = local_ftm_scheduler_get_colors();
                $has_group = !empty($activity->groupid);
                $color_info = $has_group ? ($colors[$activity->group_color] ?? $colors['giallo']) : ['emoji' => '', 'name' => ''];

                // Get enrollments
                $enrollments = \local_ftm_scheduler\manager::get_activity_enrollments($id);
                $enrolled_count = count($enrollments);

                // Get all unique groups from enrolled students
                $enrolled_groups = [];
                $enrolled_group_ids = [];
                foreach ($enrollments as $enrollment) {
                    if (!empty($enrollment->groupid) && !in_array($enrollment->groupid, $enrolled_group_ids)) {
                        $enrolled_group_ids[] = $enrollment->groupid;
                        $group = \local_ftm_scheduler\manager::get_group($enrollment->groupid);
                        if ($group) {
                            $enrolled_groups[] = $group;
                        }
                    }
                }

                // Also add the activity's main group if not already in list
                if (!empty($activity->groupid) && !in_array($activity->groupid, $enrolled_group_ids)) {
                    $main_group = \local_ftm_scheduler\manager::get_group($activity->groupid);
                    if ($main_group) {
                        array_unshift($enrolled_groups, $main_group);
                    }
                }

                // Build title
                $title = $color_info['emoji'] . ' ' . $activity->name;
                if ($activity->group_name) {
                    $title .= ' - ' . $activity->group_name;
                }

                // Build content HTML
                $content = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
                $content .= '<div>';
                $content .= '<p><strong>📅 Data:</strong> ' . local_ftm_scheduler_format_date($activity->date_start, 'full') . '</p>';
                $content .= '<p><strong>🕐 Orario:</strong> ' . date('H:i', $activity->date_start) . ' - ' . date('H:i', $activity->date_end) . '</p>';
                $content .= '<p><strong>🏫 Aula:</strong> <span class="aula-badge aula-' . ($activity->roomid ?? 2) . '">' . ($activity->room_name ?? 'AULA 2') . '</span></p>';
                $content .= '</div>';
                $content .= '<div>';
                $teacher_label = '';
                if (!empty($activity->teacher_firstname) || !empty($activity->teacher_lastname)) {
                    $teacher_label = strtoupper(
                        substr($activity->teacher_firstname ?? '', 0, 1) .
                        substr($activity->teacher_lastname  ?? '', 0, 1)
                    );
                }
                $content .= '<p><strong>👤 Docente:</strong> ' . ($teacher_label ? 'Coach ' . $teacher_label : '<em style="color:#999">Non assegnato</em>') . '</p>';

                // Show all groups participating
                if (count($enrolled_groups) > 1) {
                    $content .= '<p><strong>🎨 Gruppi partecipanti:</strong></p>';
                    $content .= '<div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px;">';
                    foreach ($enrolled_groups as $eg) {
                        $eg_color_info = $colors[$eg->color ?? 'giallo'] ?? $colors['giallo'];
                        $eg_kw = $eg->calendar_week ? ' KW' . str_pad($eg->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                        $content .= '<span class="gruppo-badge gruppo-' . ($eg->color ?? 'giallo') . '" style="font-size: 12px;">' . $eg_color_info['emoji'] . ' ' . $eg_color_info['name'] . $eg_kw . '</span>';
                    }
                    $content .= '</div>';
                } else {
                    // Single group
                    $single_kw = !empty($activity->groupid) ? '' : '';
                    if (!empty($enrolled_groups)) {
                        $single_kw = $enrolled_groups[0]->calendar_week ? ' KW' . str_pad($enrolled_groups[0]->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                    }
                    if ($has_group) {
                        $content .= '<p><strong>🎨 Gruppo:</strong> <span class="gruppo-badge gruppo-' . $activity->group_color . '">' . $color_info['emoji'] . ' ' . $color_info['name'] . $single_kw . '</span></p>';
                    } else {
                        $content .= '<p><strong>🎨 Gruppo:</strong> <span style="color:#999;font-style:italic;">Nessun gruppo</span></p>';
                    }
                }

                $content .= '<p><strong>📊 Tipo:</strong> ' . ucfirst($activity->activity_type) . '</p>';
                $content .= '</div>';
                $content .= '</div>';
                
                // Enrollments table — full list with remove buttons
                $content .= '<h4 style="margin-bottom: 10px;">👥 Studenti Iscritti (' . $enrolled_count . '/' . ($activity->max_participants ?? 10) . ')</h4>';
                $content .= '<table class="data-table">';
                $content .= '<thead><tr><th>Studente</th><th>Email</th><th>Notifica</th><th></th></tr></thead>';
                $content .= '<tbody id="enroll-list-' . $id . '">';

                foreach ($enrollments as $enrollment) {
                    $content .= '<tr id="enroll-row-' . $enrollment->id . '">';
                    $content .= '<td>' . s(fullname($enrollment)) . '</td>';
                    $content .= '<td style="font-size:12px;color:#666;">' . s($enrollment->email) . '</td>';
                    $content .= '<td>' . ($enrollment->notification_sent ? '✅' : '⏳') . '</td>';
                    $content .= '<td><button onclick="ftmUnenrollStudent(' . $id . ',' . $enrollment->id . ',this)"'
                        . ' style="background:none;border:none;cursor:pointer;color:#dc3545;font-size:15px;padding:2px 6px;" title="Rimuovi studente">🗑️</button></td>';
                    $content .= '</tr>';
                }

                if ($enrolled_count == 0) {
                    $content .= '<tr id="enroll-row-empty"><td colspan="4" style="text-align:center;color:#999;">Nessuno studente iscritto</td></tr>';
                }

                $content .= '</tbody></table>';

                // Add-student section
                $view_groups = \local_ftm_scheduler\manager::get_groups();
                $view_colors = local_ftm_scheduler_get_colors();

                $content .= '<div style="margin-top:14px;padding:12px;background:#f8f9fa;border-radius:8px;border:1px solid #dee2e6;">';
                $content .= '<p style="font-weight:600;margin:0 0 8px;font-size:13px;">➕ Aggiungi Studenti</p>';
                $content .= '<div style="position:relative;">';
                $content .= '<input type="text" id="view-student-search" placeholder="Cerca per nome o email..."'
                    . ' autocomplete="off" oninput="ftmSearchForEnroll(this)"'
                    . ' style="width:100%;padding:8px 12px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;">';
                $content .= '<div id="view-search-results" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;'
                    . 'border:1px solid #dee2e6;border-radius:0 0 6px 6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);'
                    . 'max-height:160px;overflow-y:auto;z-index:20000;"></div>';
                $content .= '</div>';
                // Group bulk enroll
                $content .= '<select onchange="ftmEnrollGroupToActivity(this)" autocomplete="off"'
                    . ' style="margin-top:6px;width:100%;padding:8px 12px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;color:#555;">';
                $content .= '<option value="">+ Aggiungi tutti gli studenti di un gruppo...</option>';
                foreach ($view_groups as $vg) {
                    $vgi = $view_colors[$vg->color] ?? $view_colors['giallo'];
                    $vgkw = $vg->calendar_week ? ' - KW' . str_pad($vg->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                    $content .= '<option value="' . (int)$vg->id . '">' . s($vgi['emoji'] . ' ' . $vgi['name'] . $vgkw) . '</option>';
                }
                $content .= '</select>';
                $content .= '</div>';

                // Edit/Delete buttons
                $content .= '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">';
                $content .= '<a href="' . (new moodle_url('/local/ftm_scheduler/edit_activity.php', ['id' => $id]))->out() . '" class="ftm-btn ftm-btn-primary" style="margin-right: 10px;">✏️ Modifica</a>';
                $content .= '<a href="' . (new moodle_url('/local/ftm_scheduler/edit_activity.php', ['id' => $id, 'delete' => 1, 'sesskey' => sesskey()]))->out() . '" class="ftm-btn ftm-btn-danger" onclick="return confirm(\'Sei sicuro di voler eliminare questa attività?\')">🗑️ Elimina</a>';
                $content .= '</div>';
                
                $result = [
                    'success' => true,
                    'title' => $title,
                    'content' => $content,
                    'bg_color' => $color_info['bg'],
                ];
            } else {
                $result = ['success' => false, 'error' => 'Activity not found'];
            }
            break;
        
        case 'get_external':
            $id = required_param('id', PARAM_INT);
            $booking = \local_ftm_scheduler\manager::get_external_booking($id);
            
            if ($booking) {
                // Build title
                $title = '🏢 ' . $booking->project_name;
                
                // Build content HTML
                $content = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
                $content .= '<div>';
                $content .= '<p><strong>📅 Data:</strong> ' . local_ftm_scheduler_format_date($booking->date_start, 'full') . '</p>';
                $content .= '<p><strong>🕐 Orario:</strong> ' . date('H:i', $booking->date_start) . ' - ' . date('H:i', $booking->date_end) . '</p>';
                $content .= '<p><strong>🏫 Aula:</strong> <span class="aula-badge">' . ($booking->room_name ?? 'AULA 1') . '</span></p>';
                $content .= '</div>';
                $content .= '<div>';
                $content .= '<p><strong>👤 Responsabile:</strong> ' . $booking->responsible . '</p>';
                $content .= '<p><strong>🔄 Ricorrenza:</strong> ' . ($booking->recurring === 'weekly' ? 'Settimanale' : 'Singolo evento') . '</p>';
                if ($booking->recurring_until) {
                    $content .= '<p><strong>📆 Fino a:</strong> ' . local_ftm_scheduler_format_date($booking->recurring_until, 'short') . '</p>';
                }
                $content .= '</div>';
                $content .= '</div>';
                
                // Notes
                if (!empty($booking->notes)) {
                    $content .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
                    $content .= '<strong>📝 Note:</strong><br>' . nl2br(htmlspecialchars($booking->notes));
                    $content .= '</div>';
                }
                
                // Edit/Delete buttons
                $content .= '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">';
                $content .= '<a href="' . (new moodle_url('/local/ftm_scheduler/edit_external.php', ['id' => $id]))->out() . '" class="ftm-btn ftm-btn-primary" style="margin-right: 10px;">✏️ Modifica</a>';
                $content .= '<a href="' . (new moodle_url('/local/ftm_scheduler/edit_external.php', ['id' => $id, 'delete' => 1, 'sesskey' => sesskey()]))->out() . '" class="ftm-btn ftm-btn-danger" onclick="return confirm(\'Sei sicuro di voler eliminare questo progetto esterno?\')">🗑️ Elimina</a>';
                $content .= '</div>';
                
                $result = [
                    'success' => true,
                    'title' => $title,
                    'content' => $content,
                    'bg_color' => '#DBEAFE', // Light blue for external
                ];
            } else {
                $result = ['success' => false, 'error' => 'External booking not found'];
            }
            break;
            
        case 'search_users':
            $q = required_param('q', PARAM_TEXT);
            $q = trim($q);
            if (core_text::strlen($q) < 2) {
                $result = ['success' => true, 'users' => []];
                break;
            }
            $search = '%' . $DB->sql_like_escape($q) . '%';
            $users_raw = $DB->get_records_sql(
                "SELECT u.id, u.firstname, u.lastname, u.email
                   FROM {user} u
                  WHERE u.deleted = 0 AND u.suspended = 0
                    AND (" . $DB->sql_like('u.firstname', ':s1', false) . "
                         OR " . $DB->sql_like('u.lastname', ':s2', false) . "
                         OR " . $DB->sql_like('u.email', ':s3', false) . ")
                  ORDER BY u.lastname, u.firstname",
                ['s1' => $search, 's2' => $search, 's3' => $search],
                0, 20
            );
            $users_out = [];
            foreach ($users_raw as $u) {
                $users_out[] = [
                    'id'       => (int)$u->id,
                    'fullname' => fullname($u),
                    'email'    => $u->email,
                ];
            }
            $result = ['success' => true, 'users' => $users_out];
            break;

        case 'get_group_members':
            $groupid = required_param('groupid', PARAM_INT);
            $members = \local_ftm_scheduler\manager::get_group_members($groupid);
            $members_out = [];
            foreach ($members as $m) {
                if (empty($m->userid)) continue;
                $members_out[] = [
                    'userid'   => (int)$m->userid,
                    'fullname' => fullname($m),
                ];
            }
            $result = ['success' => true, 'members' => $members_out];
            break;

        case 'enroll_student':
            require_sesskey();
            require_capability('local/ftm_scheduler:manage', $context);
            $activityid = required_param('activityid', PARAM_INT);
            $userid     = required_param('userid', PARAM_INT);
            // Avoid duplicates.
            if (!$DB->record_exists('local_ftm_enrollments', ['activityid' => $activityid, 'userid' => $userid])) {
                \local_ftm_scheduler\manager::enroll_user($activityid, $userid, null);
            }
            $result = ['success' => true];
            break;

        case 'unenroll_student':
            require_sesskey();
            require_capability('local/ftm_scheduler:manage', $context);
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $DB->delete_records('local_ftm_enrollments', ['id' => $enrollmentid]);
            $result = ['success' => true];
            break;

        case 'enroll_group':
            require_sesskey();
            require_capability('local/ftm_scheduler:manage', $context);
            $activityid = required_param('activityid', PARAM_INT);
            $groupid    = required_param('groupid', PARAM_INT);
            $members    = \local_ftm_scheduler\manager::get_group_members($groupid);
            $count      = 0;
            foreach ($members as $m) {
                if (empty($m->userid)) continue;
                if (!$DB->record_exists('local_ftm_enrollments', ['activityid' => $activityid, 'userid' => $m->userid])) {
                    \local_ftm_scheduler\manager::enroll_user($activityid, $m->userid, $groupid);
                    $count++;
                }
            }
            $result = ['success' => true, 'enrolled' => $count];
            break;

        default:
            $result = ['success' => false, 'error' => 'Invalid action'];
    }
} catch (Exception $e) {
    $result = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($result);
