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
 * AJAX endpoint for bulk delete activities.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    $result = ['success' => false, 'message' => '', 'deleted' => 0];

    switch ($action) {
        case 'delete_by_date_range':
            // Delete activities within a date range.
            $datefrom = required_param('date_from', PARAM_INT);
            $dateto = required_param('date_to', PARAM_INT);

            $count = $DB->count_records_select(
                'local_ftm_activities',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $DB->delete_records_select(
                'local_ftm_activities',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count attività",
            ];
            break;

        case 'delete_by_created_time':
            // Delete activities created within a time range (useful to undo imports).
            $createdfrom = required_param('created_from', PARAM_INT);
            $createdto = optional_param('created_to', time(), PARAM_INT);

            $count = $DB->count_records_select(
                'local_ftm_activities',
                'timecreated >= :createdfrom AND timecreated <= :createdto',
                ['createdfrom' => $createdfrom, 'createdto' => $createdto]
            );

            $DB->delete_records_select(
                'local_ftm_activities',
                'timecreated >= :createdfrom AND timecreated <= :createdto',
                ['createdfrom' => $createdfrom, 'createdto' => $createdto]
            );

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count attività create nel periodo specificato",
            ];
            break;

        case 'delete_by_ids':
            // Delete specific activities by ID.
            $ids = required_param('ids', PARAM_RAW);
            $idarray = explode(',', $ids);
            $idarray = array_filter(array_map('intval', $idarray));

            if (empty($idarray)) {
                throw new Exception('Nessun ID valido specificato');
            }

            list($insql, $inparams) = $DB->get_in_or_equal($idarray, SQL_PARAMS_NAMED);
            $count = $DB->count_records_select('local_ftm_activities', "id $insql", $inparams);
            $DB->delete_records_select('local_ftm_activities', "id $insql", $inparams);

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count attività",
            ];
            break;

        case 'delete_by_type':
            // Delete activities by type.
            $type = required_param('type', PARAM_ALPHANUMEXT);

            $count = $DB->count_records('local_ftm_activities', ['type' => $type]);
            $DB->delete_records('local_ftm_activities', ['type' => $type]);

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count attività di tipo '$type'",
            ];
            break;

        case 'delete_imported_today':
            // Delete all activities created today (undo today's imports).
            $todaystart = mktime(0, 0, 0);
            $todayend = mktime(23, 59, 59);

            $count = $DB->count_records_select(
                'local_ftm_activities',
                'timecreated >= :todaystart AND timecreated <= :todayend',
                ['todaystart' => $todaystart, 'todayend' => $todayend]
            );

            $DB->delete_records_select(
                'local_ftm_activities',
                'timecreated >= :todaystart AND timecreated <= :todayend',
                ['todaystart' => $todaystart, 'todayend' => $todayend]
            );

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count attività importate oggi",
            ];
            break;

        case 'delete_external_bookings':
            // Delete external bookings (BIT URAR, BIT AI, etc.) by date range.
            $datefrom = required_param('date_from', PARAM_INT);
            $dateto = required_param('date_to', PARAM_INT);

            $count = $DB->count_records_select(
                'local_ftm_external_bookings',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $DB->delete_records_select(
                'local_ftm_external_bookings',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate $count prenotazioni esterne",
            ];
            break;

        case 'delete_all_in_period':
            // Delete ALL activities AND external bookings in a date range.
            $datefrom = required_param('date_from', PARAM_INT);
            $dateto = required_param('date_to', PARAM_INT);

            // Count and delete activities.
            $countActivities = $DB->count_records_select(
                'local_ftm_activities',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );
            $DB->delete_records_select(
                'local_ftm_activities',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            // Count and delete external bookings.
            $countExternal = $DB->count_records_select(
                'local_ftm_external_bookings',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );
            $DB->delete_records_select(
                'local_ftm_external_bookings',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $total = $countActivities + $countExternal;
            $result = [
                'success' => true,
                'deleted' => $total,
                'deleted_activities' => $countActivities,
                'deleted_external' => $countExternal,
                'message' => "Eliminate $countActivities attività e $countExternal prenotazioni esterne",
            ];
            break;

        case 'delete_all':
            // Delete ALL activities (dangerous!).
            $confirm = required_param('confirm', PARAM_ALPHANUMEXT);
            if ($confirm !== 'ELIMINA_TUTTO') {
                throw new Exception('Conferma non valida. Usa confirm=ELIMINA_TUTTO');
            }

            $count = $DB->count_records('local_ftm_activities');
            $DB->delete_records('local_ftm_activities');

            $result = [
                'success' => true,
                'deleted' => $count,
                'message' => "Eliminate TUTTE le $count attività",
            ];
            break;

        case 'count_by_date_range':
            // Count activities in date range (preview before delete).
            $datefrom = required_param('date_from', PARAM_INT);
            $dateto = required_param('date_to', PARAM_INT);

            $count = $DB->count_records_select(
                'local_ftm_activities',
                'date_start >= :datefrom AND date_start <= :dateto',
                ['datefrom' => $datefrom, 'dateto' => $dateto]
            );

            $result = [
                'success' => true,
                'count' => $count,
                'message' => "Trovate $count attività nel periodo",
            ];
            break;

        case 'count_imported_today':
            // Count activities created today.
            $todaystart = mktime(0, 0, 0);
            $todayend = mktime(23, 59, 59);

            $count = $DB->count_records_select(
                'local_ftm_activities',
                'timecreated >= :todaystart AND timecreated <= :todayend',
                ['todaystart' => $todaystart, 'todayend' => $todayend]
            );

            $result = [
                'success' => true,
                'count' => $count,
                'message' => "Trovate $count attività importate oggi",
            ];
            break;

        case 'deactivate_groups':
            // Deactivate all active groups.
            $count = $DB->count_records('local_ftm_groups', ['status' => 'active']);
            $DB->set_field('local_ftm_groups', 'status', 'completed', ['status' => 'active']);

            $result = [
                'success' => true,
                'count' => $count,
                'message' => "Disattivati $count gruppi",
            ];
            break;

        case 'delete_groups':
            // Delete all groups (and their members).
            $groups = $DB->get_records('local_ftm_groups');
            $countGroups = count($groups);
            $countMembers = 0;

            foreach ($groups as $group) {
                // Delete group members first.
                $countMembers += $DB->count_records('local_ftm_group_members', ['groupid' => $group->id]);
                $DB->delete_records('local_ftm_group_members', ['groupid' => $group->id]);
            }

            // Delete groups.
            $DB->delete_records('local_ftm_groups');

            $result = [
                'success' => true,
                'deleted_groups' => $countGroups,
                'deleted_members' => $countMembers,
                'message' => "Eliminati $countGroups gruppi e $countMembers membri",
            ];
            break;

        case 'list_groups':
            // List all groups.
            $groups = $DB->get_records('local_ftm_groups', null, 'name ASC');
            $groupList = [];
            foreach ($groups as $g) {
                $memberCount = $DB->count_records('local_ftm_group_members', ['groupid' => $g->id]);
                $groupList[] = [
                    'id' => $g->id,
                    'name' => $g->name,
                    'color' => $g->color,
                    'status' => $g->status,
                    'members' => $memberCount,
                ];
            }

            $result = [
                'success' => true,
                'groups' => $groupList,
                'count' => count($groupList),
            ];
            break;

        case 'reset_calendar':
            // FULL RESET: Delete all activities, external bookings, and deactivate groups.
            $confirm = required_param('confirm', PARAM_ALPHANUMEXT);
            if ($confirm !== 'RESET_COMPLETO') {
                throw new Exception('Conferma non valida. Usa confirm=RESET_COMPLETO');
            }

            // Delete all activities.
            $countActivities = $DB->count_records('local_ftm_activities');
            $DB->delete_records('local_ftm_activities');

            // Delete all external bookings.
            $countExternal = $DB->count_records('local_ftm_external_bookings');
            $DB->delete_records('local_ftm_external_bookings');

            // Deactivate all groups.
            $countGroups = $DB->count_records('local_ftm_groups', ['status' => 'active']);
            $DB->set_field('local_ftm_groups', 'status', 'completed', ['status' => 'active']);

            $result = [
                'success' => true,
                'deleted_activities' => $countActivities,
                'deleted_external' => $countExternal,
                'deactivated_groups' => $countGroups,
                'message' => "Reset completo: $countActivities attività, $countExternal esterni, $countGroups gruppi disattivati",
            ];
            break;

        default:
            throw new Exception('Azione non valida: ' . $action);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
