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

header('Content-Type: application/json');

$result = ['success' => false];

try {
    switch ($action) {
        case 'get_activity':
            $id = required_param('id', PARAM_INT);
            $activity = \local_ftm_scheduler\manager::get_activity($id);

            if ($activity) {
                $colors = local_ftm_scheduler_get_colors();
                $color_info = $colors[$activity->group_color ?? 'giallo'] ?? $colors['giallo'];

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
                $content .= '<p><strong>👤 Docente:</strong> Coach ' . ($activity->teacher_firstname ? substr($activity->teacher_firstname, 0, 1) . substr($activity->teacher_lastname, 0, 1) : 'GM') . '</p>';

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
                    $content .= '<p><strong>🎨 Gruppo:</strong> <span class="gruppo-badge gruppo-' . ($activity->group_color ?? 'giallo') . '">' . $color_info['emoji'] . ' ' . $color_info['name'] . $single_kw . '</span></p>';
                }

                $content .= '<p><strong>📊 Tipo:</strong> ' . ucfirst($activity->activity_type) . '</p>';
                $content .= '</div>';
                $content .= '</div>';
                
                // Enrollments table
                $content .= '<h4 style="margin-bottom: 10px;">👥 Studenti Iscritti (' . $enrolled_count . '/' . ($activity->max_participants ?? 10) . ')</h4>';
                $content .= '<table class="data-table">';
                $content .= '<thead><tr><th>Studente</th><th>Email</th><th>Notifica</th></tr></thead>';
                $content .= '<tbody>';
                
                $shown = 0;
                foreach ($enrollments as $enrollment) {
                    if ($shown < 3) {
                        $content .= '<tr>';
                        $content .= '<td>' . fullname($enrollment) . '</td>';
                        $content .= '<td>' . $enrollment->email . '</td>';
                        $content .= '<td>' . ($enrollment->notification_sent ? '✅ Inviata' : '⏳ In attesa') . '</td>';
                        $content .= '</tr>';
                    }
                    $shown++;
                }
                
                if ($enrolled_count > 3) {
                    $content .= '<tr><td colspan="3" style="text-align: center; color: #666;">... altri ' . ($enrolled_count - 3) . ' studenti</td></tr>';
                }
                
                if ($enrolled_count == 0) {
                    $content .= '<tr><td colspan="3" style="text-align: center; color: #999;">Nessuno studente iscritto</td></tr>';
                }
                
                $content .= '</tbody></table>';
                
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
            
        default:
            $result = ['success' => false, 'error' => 'Invalid action'];
    }
} catch (Exception $e) {
    $result = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($result);
