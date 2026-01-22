<?php
/**
 * AJAX endpoint per navigazione studente a cascata
 *
 * Gestisce le richieste per popolare i selettori:
 * - Coorti -> Colori -> Settimane KW -> Studenti
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/sector_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    $result = [
        'success' => true,
        'data' => [],
        'message' => ''
    ];

    switch ($action) {
        case 'get_cohorts':
            // Ottieni coorti con studenti
            $cohorts = \local_competencymanager\sector_manager::get_cohorts_with_students();
            $result['data'] = array_values($cohorts);
            break;

        case 'get_colors':
            // Ottieni colori per una coorte
            $cohortid = optional_param('cohortid', 0, PARAM_INT);
            $colors = \local_competencymanager\sector_manager::get_colors_for_cohort($cohortid);

            // Formatta con nomi leggibili
            $formatted = [];
            foreach ($colors as $color) {
                $formatted[] = [
                    'color' => $color->color,
                    'color_hex' => $color->color_hex,
                    'name' => \local_competencymanager\sector_manager::COLOR_NAMES[$color->color] ?? ucfirst($color->color),
                    'student_count' => (int)$color->student_count
                ];
            }
            $result['data'] = $formatted;
            break;

        case 'get_weeks':
            // Ottieni settimane KW per coorte e colore
            $cohortid = optional_param('cohortid', 0, PARAM_INT);
            $color = optional_param('color', '', PARAM_ALPHANUMEXT);
            $weeks = \local_competencymanager\sector_manager::get_weeks_for_color($cohortid, $color);

            // Formatta con label leggibili
            $formatted = [];
            foreach ($weeks as $week) {
                $entry_date = $week->entry_date ? date('d/m/Y', $week->entry_date) : '';
                $formatted[] = [
                    'calendar_week' => (int)$week->calendar_week,
                    'entry_date' => $entry_date,
                    'entry_timestamp' => (int)$week->entry_date,
                    'group_name' => $week->group_name,
                    'color' => $week->color,
                    'student_count' => (int)$week->student_count,
                    'label' => 'KW' . str_pad($week->calendar_week, 2, '0', STR_PAD_LEFT) .
                               ($entry_date ? ' (' . $entry_date . ')' : '')
                ];
            }
            $result['data'] = $formatted;
            break;

        case 'get_students':
            // Ottieni studenti filtrati
            $cohortid = optional_param('cohortid', 0, PARAM_INT);
            $color = optional_param('color', '', PARAM_ALPHANUMEXT);
            $calendar_week = optional_param('calendar_week', 0, PARAM_INT);

            $students = \local_competencymanager\sector_manager::get_students_for_navigation(
                $cohortid, $color, $calendar_week
            );

            // Formatta per il dropdown
            $formatted = [];
            foreach ($students as $student) {
                $formatted[] = [
                    'id' => (int)$student->id,
                    'fullname' => fullname($student),
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                    'group_color' => $student->group_color ?? '',
                    'color_hex' => $student->color_hex ?? '',
                    'calendar_week' => (int)($student->calendar_week ?? 0),
                    'group_name' => $student->group_name ?? '',
                    'current_week' => (int)($student->current_week ?? 0)
                ];
            }

            // Ordina per cognome, nome
            usort($formatted, function($a, $b) {
                $cmp = strcasecmp($a['lastname'], $b['lastname']);
                return $cmp !== 0 ? $cmp : strcasecmp($a['firstname'], $b['firstname']);
            });

            $result['data'] = $formatted;
            $result['count'] = count($formatted);
            break;

        case 'get_student_sectors':
            // Ottieni settori di uno studente (per filtro intelligente)
            $userid = required_param('userid', PARAM_INT);
            $sectors = \local_competencymanager\sector_manager::get_student_sectors_with_quiz_data($userid);

            $formatted = [];
            foreach ($sectors as $sector) {
                $formatted[] = [
                    'sector' => $sector->sector,
                    'name' => \local_competencymanager\sector_manager::SECTORS[$sector->sector] ?? $sector->sector,
                    'quiz_count' => (int)$sector->quiz_count,
                    'is_primary' => (int)$sector->is_primary
                ];
            }

            // Ordina per quiz_count decrescente
            usort($formatted, function($a, $b) {
                return $b['quiz_count'] - $a['quiz_count'];
            });

            $result['data'] = $formatted;
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
        'data' => []
    ]);
}

die();
