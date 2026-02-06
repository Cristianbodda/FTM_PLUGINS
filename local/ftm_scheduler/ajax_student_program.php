<?php
/**
 * AJAX endpoint for Student Individual Program
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();

// Coach o Segreteria possono modificare
$canmanage = has_capability('local/ftm_scheduler:manage', $context);
$cancoach = has_capability('local/ftm_scheduler:markattendance', $context);

if (!$canmanage && !$cancoach) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permesso negato']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHA);
$userid = required_param('userid', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);

$result = ['success' => false, 'message' => ''];

try {
    switch ($action) {

        case 'save_program':
            $changes_json = required_param('changes', PARAM_RAW);
            $changes = json_decode($changes_json, true);

            if (empty($changes)) {
                throw new Exception('Nessuna modifica da salvare');
            }

            $now = time();
            $saved = 0;

            foreach ($changes as $key => $change) {
                $week = (int)$change['week'];
                $day = (int)$change['day'];
                $slot = $change['slot'];

                // Don't allow editing week 1
                if ($week == 1) {
                    continue;
                }

                // Find existing record
                $record = $DB->get_record('local_ftm_student_program', [
                    'userid' => $userid,
                    'groupid' => $groupid,
                    'week_number' => $week,
                    'day_of_week' => $day,
                    'time_slot' => $slot
                ]);

                if ($record) {
                    // Update existing
                    $record->activity_type = $change['type'];
                    $record->activity_name = $change['activity'];
                    $record->activity_details = $change['details'];
                    $record->location = $change['location'];
                    $record->assigned_by = $USER->id;
                    $record->timemodified = $now;

                    $DB->update_record('local_ftm_student_program', $record);
                    $saved++;
                } else {
                    // Insert new
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->groupid = $groupid;
                    $record->week_number = $week;
                    $record->day_of_week = $day;
                    $record->time_slot = $slot;
                    $record->activity_type = $change['type'];
                    $record->activity_name = $change['activity'];
                    $record->activity_details = $change['details'];
                    $record->location = $change['location'];
                    $record->is_editable = 1;
                    $record->status = 'pending';
                    $record->assigned_by = $USER->id;
                    $record->timecreated = $now;
                    $record->timemodified = $now;

                    $DB->insert_record('local_ftm_student_program', $record);
                    $saved++;
                }
            }

            $result = [
                'success' => true,
                'message' => "Salvate $saved modifiche",
                'saved' => $saved
            ];
            break;

        case 'save_tests':
            $tests_str = optional_param('tests', '', PARAM_TEXT);
            $selected_tests = $tests_str ? explode(',', $tests_str) : [];

            $now = time();

            // Get current assigned tests
            $current_tests = $DB->get_records('local_ftm_student_tests', [
                'userid' => $userid,
                'groupid' => $groupid
            ], '', 'test_code, id');

            $current_codes = array_keys($current_tests);

            // Tests to add
            $to_add = array_diff($selected_tests, $current_codes);

            // Tests to remove
            $to_remove = array_diff($current_codes, $selected_tests);

            // Remove unselected tests
            foreach ($to_remove as $code) {
                $DB->delete_records('local_ftm_student_tests', [
                    'userid' => $userid,
                    'groupid' => $groupid,
                    'test_code' => $code
                ]);
            }

            // Add new tests
            $test_catalog = local_ftm_scheduler_get_full_test_catalog();

            foreach ($to_add as $code) {
                if (isset($test_catalog[$code])) {
                    $test_info = $test_catalog[$code];

                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->groupid = $groupid;
                    $record->test_code = $code;
                    $record->test_name = $test_info['name'];
                    $record->test_type = $test_info['type'];
                    $record->sector = implode(',', $test_info['sectors']);
                    $record->assigned_by = $USER->id;
                    $record->status = 'pending';
                    $record->timecreated = $now;
                    $record->timemodified = $now;

                    $DB->insert_record('local_ftm_student_tests', $record);
                }
            }

            $result = [
                'success' => true,
                'message' => 'Test aggiornati',
                'added' => count($to_add),
                'removed' => count($to_remove)
            ];
            break;

        case 'mark_test_complete':
            $test_code = required_param('test_code', PARAM_ALPHANUMEXT);

            $test = $DB->get_record('local_ftm_student_tests', [
                'userid' => $userid,
                'groupid' => $groupid,
                'test_code' => $test_code
            ]);

            if (!$test) {
                throw new Exception('Test non trovato');
            }

            $test->status = 'completed';
            $test->completed_date = time();
            $test->timemodified = time();

            $DB->update_record('local_ftm_student_tests', $test);

            $result = [
                'success' => true,
                'message' => 'Test completato'
            ];
            break;

        case 'get_program':
            $program = $DB->get_records('local_ftm_student_program', [
                'userid' => $userid,
                'groupid' => $groupid
            ], 'week_number, day_of_week, time_slot');

            $data = [];
            foreach ($program as $p) {
                $key = "{$p->week_number}-{$p->day_of_week}-{$p->time_slot}";
                $data[$key] = [
                    'id' => $p->id,
                    'type' => $p->activity_type,
                    'activity' => $p->activity_name,
                    'details' => $p->activity_details,
                    'location' => $p->location,
                    'editable' => $p->is_editable,
                    'status' => $p->status
                ];
            }

            $result = [
                'success' => true,
                'data' => $data
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

/**
 * Get full test catalog as associative array
 */
function local_ftm_scheduler_get_full_test_catalog() {
    return [
        'T01' => ['name' => 'Matematica', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE', 'ELETTRICITA']],
        'T02' => ['name' => 'Disegno tecnico', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        'T03' => ['name' => 'Materiali di base', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        'T04' => ['name' => 'Simbologia disegno tecnico', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'ELETTRICITA']],
        'T05' => ['name' => 'Programmazione macchine', 'type' => 'teorico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE']],
        'T06' => ['name' => 'Disegno d\'officina', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        'T07' => ['name' => 'Parametri di lavorazione', 'type' => 'teorico', 'sectors' => ['MECCANICA']],
        'T08' => ['name' => 'Fresa', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        'T09' => ['name' => 'Tornio', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        'T10' => ['name' => 'Lavorazioni trapano', 'type' => 'pratico', 'sectors' => ['MECCANICA']],
        'T11' => ['name' => 'Metrologia base', 'type' => 'pratico', 'sectors' => ['MECCANICA', 'METALCOSTRUZIONE']],
        'T12' => ['name' => 'Montaggio avanzato', 'type' => 'pratico', 'sectors' => ['MECCANICA', 'AUTOMAZIONE']],
        'T13' => ['name' => 'Elementi elettronici', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        'T14' => ['name' => 'Elettronica digitale', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        'T15' => ['name' => 'Corrente alternata', 'type' => 'teorico', 'sectors' => ['ELETTRICITA']],
        'T16' => ['name' => 'Elettricità', 'type' => 'teorico', 'sectors' => ['ELETTRICITA']],
        'T17' => ['name' => 'Utilizzo strumento di misura', 'type' => 'pratico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        'T18' => ['name' => 'Elettronica', 'type' => 'teorico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
        'T19' => ['name' => 'Realizzazione quadro', 'type' => 'pratico', 'sectors' => ['ELETTRICITA']],
        'T20' => ['name' => 'Ricerca guasti su quadro', 'type' => 'pratico', 'sectors' => ['ELETTRICITA']],
        'T21' => ['name' => 'PLC', 'type' => 'teorico', 'sectors' => ['AUTOMAZIONE', 'ELETTRICITA']],
        'T22' => ['name' => 'Office e PC', 'type' => 'teorico', 'sectors' => ['LOGISTICA', 'CHIMFARM']],
        'T23' => ['name' => 'Linguaggio di programmazione', 'type' => 'pratico', 'sectors' => ['AUTOMAZIONE']],
        'T24' => ['name' => 'Pneumatica', 'type' => 'pratico', 'sectors' => ['AUTOMAZIONE', 'MECCANICA']],
        'T25' => ['name' => 'Rilevamento schema', 'type' => 'pratico', 'sectors' => ['ELETTRICITA', 'AUTOMAZIONE']],
    ];
}
