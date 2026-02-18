<?php
/**
 * AJAX endpoint per Dashboard Segreteria
 * Gestisce creazione, modifica, eliminazione di attività e prenotazioni
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHANUMEXT);

$result = ['success' => false, 'message' => ''];

try {
    $manager = new \local_ftm_scheduler\manager();

    switch ($action) {

        // ============================================
        // GET - Recupera dati per modifica
        // ============================================

        case 'get_activity':
            $id = required_param('id', PARAM_INT);
            $activity = $manager::get_activity($id);

            if (!$activity) {
                throw new Exception('Attività non trovata');
            }

            $result = [
                'success' => true,
                'data' => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'activity_type' => $activity->activity_type,
                    'groupid' => $activity->groupid,
                    'date_start' => date('Y-m-d', $activity->date_start),
                    'time_slot' => (function($start, $end) {
                        $start_hour = (int)date('H', $start);
                        $end_hour = (int)date('H', $end);
                        // Se inizia la mattina e finisce dopo le 14, è tutto il giorno
                        if ($start_hour < 12 && $end_hour >= 14) {
                            return 'all';
                        }
                        return ($start_hour < 12) ? 'matt' : 'pom';
                    })($activity->date_start, $activity->date_end),
                    'roomid' => $activity->roomid,
                    'teacherid' => $activity->teacherid,
                    'max_participants' => $activity->max_participants,
                    'notes' => $activity->notes,
                    'group_name' => $activity->group_name,
                    'room_name' => $activity->room_name,
                    'teacher_name' => trim(($activity->teacher_firstname ?? '') . ' ' . ($activity->teacher_lastname ?? '')),
                ]
            ];
            break;

        case 'get_external':
            $id = required_param('id', PARAM_INT);
            $booking = $manager::get_external_booking($id);

            if (!$booking) {
                throw new Exception('Prenotazione non trovata');
            }

            $result = [
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'project_name' => $booking->project_name,
                    'roomid' => $booking->roomid,
                    'date_start' => date('Y-m-d', $booking->date_start),
                    'time_slot' => (date('H', $booking->date_start) < 12) ? 'matt' : 'pom',
                    'responsible' => $booking->responsible,
                    'notes' => $booking->notes,
                    'recurring' => $booking->recurring,
                    'room_name' => $booking->room_name,
                ]
            ];
            break;

        // ============================================
        // CREATE - Creazione rapida
        // ============================================

        case 'create_activity':
            $name = required_param('name', PARAM_TEXT);
            $activity_type = optional_param('activity_type', 'week1', PARAM_ALPHANUMEXT);
            $groupid = optional_param('groupid', 0, PARAM_INT);
            $date = required_param('date', PARAM_TEXT);
            $time_slot = required_param('time_slot', PARAM_ALPHA);
            $roomid = optional_param('roomid', 0, PARAM_INT);
            $teacherid = optional_param('teacherid', 0, PARAM_INT);
            $max_participants = optional_param('max_participants', 10, PARAM_INT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            // Calcola orari
            $date_ts = strtotime($date);
            if ($time_slot === 'matt') {
                $start_time = strtotime($date . ' 08:30:00');
                $end_time = strtotime($date . ' 11:45:00');
            } elseif ($time_slot === 'pom') {
                $start_time = strtotime($date . ' 13:15:00');
                $end_time = strtotime($date . ' 16:30:00');
            } else { // all - tutto il giorno
                $start_time = strtotime($date . ' 08:30:00');
                $end_time = strtotime($date . ' 16:30:00');
            }

            $data = new stdClass();
            $data->name = $name;
            $data->activity_type = $activity_type;
            $data->groupid = $groupid ?: null;
            $data->date_start = $start_time;
            $data->date_end = $end_time;
            $data->roomid = $roomid ?: null;
            $data->teacherid = $teacherid ?: null;
            $data->max_participants = $max_participants;
            $data->notes = $notes;
            $data->atelierid = optional_param('atelierid', 0, PARAM_INT) ?: null;

            $activityid = $manager::create_activity($data);

            $result = [
                'success' => true,
                'message' => 'Attività creata con successo',
                'id' => $activityid
            ];
            break;

        case 'create_external':
            $project_name = required_param('project_name', PARAM_TEXT);
            $roomid = required_param('roomid', PARAM_INT);
            $date = required_param('date', PARAM_TEXT);
            $time_slot = required_param('time_slot', PARAM_ALPHA);
            $responsible = optional_param('responsible', '', PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);
            $recurring = optional_param('recurring', 'none', PARAM_ALPHA);

            // Calcola orari
            if ($time_slot === 'matt') {
                $start_time = strtotime($date . ' 08:30:00');
                $end_time = strtotime($date . ' 11:45:00');
            } elseif ($time_slot === 'pom') {
                $start_time = strtotime($date . ' 13:15:00');
                $end_time = strtotime($date . ' 16:30:00');
            } else { // all day
                $start_time = strtotime($date . ' 08:30:00');
                $end_time = strtotime($date . ' 16:30:00');
            }

            $data = new stdClass();
            $data->project_name = $project_name;
            $data->roomid = $roomid;
            $data->date_start = $start_time;
            $data->date_end = $end_time;
            $data->responsible = $responsible;
            $data->notes = $notes;
            $data->recurring = $recurring;

            $bookingid = $manager::create_external_booking($data);

            $result = [
                'success' => true,
                'message' => 'Prenotazione creata con successo',
                'id' => $bookingid
            ];
            break;

        // ============================================
        // UPDATE - Modifica inline
        // ============================================

        case 'update_activity':
            global $DB;

            $id = required_param('id', PARAM_INT);
            $activity = $DB->get_record('local_ftm_activities', ['id' => $id]);

            if (!$activity) {
                throw new Exception('Attività non trovata');
            }

            // Aggiorna solo i campi forniti
            $update = new stdClass();
            $update->id = $id;
            $update->timemodified = time();

            if (($name = optional_param('name', null, PARAM_TEXT)) !== null) {
                $update->name = $name;
            }
            if (($groupid = optional_param('groupid', null, PARAM_INT)) !== null) {
                $update->groupid = $groupid ?: null;
            }
            if (($roomid = optional_param('roomid', null, PARAM_INT)) !== null) {
                $update->roomid = $roomid ?: null;
            }
            if (($teacherid = optional_param('teacherid', null, PARAM_INT)) !== null) {
                $update->teacherid = $teacherid ?: null;
            }
            if (($max_participants = optional_param('max_participants', null, PARAM_INT)) !== null) {
                $update->max_participants = $max_participants;
            }
            if (($notes = optional_param('notes', null, PARAM_TEXT)) !== null) {
                $update->notes = $notes;
            }
            if (($activity_type = optional_param('activity_type', null, PARAM_ALPHANUMEXT)) !== null) {
                $update->activity_type = $activity_type;
            }

            // Gestione data e orario
            $date = optional_param('date', null, PARAM_TEXT);
            $time_slot = optional_param('time_slot', null, PARAM_ALPHA);

            if ($date !== null || $time_slot !== null) {
                $date_str = $date ?? date('Y-m-d', $activity->date_start);
                $slot = $time_slot ?? ((date('H', $activity->date_start) < 12) ? 'matt' : 'pom');

                if ($slot === 'matt') {
                    $update->date_start = strtotime($date_str . ' 08:30:00');
                    $update->date_end = strtotime($date_str . ' 11:45:00');
                } elseif ($slot === 'pom') {
                    $update->date_start = strtotime($date_str . ' 13:15:00');
                    $update->date_end = strtotime($date_str . ' 16:30:00');
                } else { // all - tutto il giorno
                    $update->date_start = strtotime($date_str . ' 08:30:00');
                    $update->date_end = strtotime($date_str . ' 16:30:00');
                }
            }

            $DB->update_record('local_ftm_activities', $update);

            $result = [
                'success' => true,
                'message' => 'Attività aggiornata con successo'
            ];
            break;

        case 'update_external':
            global $DB;

            $id = required_param('id', PARAM_INT);
            $booking = $DB->get_record('local_ftm_external_bookings', ['id' => $id]);

            if (!$booking) {
                throw new Exception('Prenotazione non trovata');
            }

            $update = new stdClass();
            $update->id = $id;

            if (($project_name = optional_param('project_name', null, PARAM_TEXT)) !== null) {
                $update->project_name = $project_name;
            }
            if (($roomid = optional_param('roomid', null, PARAM_INT)) !== null) {
                $update->roomid = $roomid;
            }
            if (($responsible = optional_param('responsible', null, PARAM_TEXT)) !== null) {
                $update->responsible = $responsible;
            }
            if (($notes = optional_param('notes', null, PARAM_TEXT)) !== null) {
                $update->notes = $notes;
            }
            if (($recurring = optional_param('recurring', null, PARAM_ALPHA)) !== null) {
                $update->recurring = $recurring;
            }

            // Gestione data e orario
            $date = optional_param('date', null, PARAM_TEXT);
            $time_slot = optional_param('time_slot', null, PARAM_ALPHA);

            if ($date !== null || $time_slot !== null) {
                $date_str = $date ?? date('Y-m-d', $booking->date_start);
                $slot = $time_slot ?? ((date('H', $booking->date_start) < 12) ? 'matt' : 'pom');

                if ($slot === 'matt') {
                    $update->date_start = strtotime($date_str . ' 08:30:00');
                    $update->date_end = strtotime($date_str . ' 11:45:00');
                } elseif ($slot === 'pom') {
                    $update->date_start = strtotime($date_str . ' 13:15:00');
                    $update->date_end = strtotime($date_str . ' 16:30:00');
                } else {
                    $update->date_start = strtotime($date_str . ' 08:30:00');
                    $update->date_end = strtotime($date_str . ' 16:30:00');
                }
            }

            $DB->update_record('local_ftm_external_bookings', $update);

            $result = [
                'success' => true,
                'message' => 'Prenotazione aggiornata con successo'
            ];
            break;

        // ============================================
        // DELETE - Eliminazione
        // ============================================

        case 'delete_activity':
            global $DB;

            $id = required_param('id', PARAM_INT);

            // Elimina prima le iscrizioni
            $DB->delete_records('local_ftm_enrollments', ['activityid' => $id]);
            // Poi l'attività
            $DB->delete_records('local_ftm_activities', ['id' => $id]);

            $result = [
                'success' => true,
                'message' => 'Attività eliminata con successo'
            ];
            break;

        case 'delete_external':
            global $DB;

            $id = required_param('id', PARAM_INT);
            $DB->delete_records('local_ftm_external_bookings', ['id' => $id]);

            $result = [
                'success' => true,
                'message' => 'Prenotazione eliminata con successo'
            ];
            break;

        // ============================================
        // UTILITIES
        // ============================================

        case 'get_options':
            // Restituisce tutte le opzioni per i dropdown
            $groups = $manager::get_groups();
            $rooms = $manager::get_rooms();
            $coaches = $manager::get_coaches();
            $colors = local_ftm_scheduler_get_colors();

            $groups_arr = [];
            foreach ($groups as $g) {
                $color_info = $colors[$g->color] ?? $colors['giallo'];
                $kw = $g->calendar_week ? ' - KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
                $groups_arr[] = [
                    'id' => $g->id,
                    'name' => $color_info['emoji'] . ' ' . $color_info['name'] . $kw,
                    'color' => $g->color,
                ];
            }

            $rooms_arr = [];
            foreach ($rooms as $r) {
                $rooms_arr[] = [
                    'id' => $r->id,
                    'name' => $r->name . ' (' . $r->capacity . ' posti)',
                ];
            }

            $coaches_arr = [];
            foreach ($coaches as $c) {
                $coaches_arr[] = [
                    'id' => $c->userid,
                    'name' => $c->firstname . ' ' . $c->lastname,
                    'initials' => $c->initials,
                ];
            }

            $result = [
                'success' => true,
                'data' => [
                    'groups' => $groups_arr,
                    'rooms' => $rooms_arr,
                    'coaches' => $coaches_arr,
                ]
            ];
            break;

        default:
            throw new Exception('Azione non valida: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    $result = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($result);
die();
