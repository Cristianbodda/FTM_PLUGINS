<?php
/**
 * AJAX endpoint per salvare valutazioni finali modificate manualmente
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/competencymanager:evaluate', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {
        case 'save_final_rating':
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);
            $areacode = required_param('areacode', PARAM_ALPHANUMEXT);
            $method = required_param('method', PARAM_ALPHANUMEXT);
            $manualvalue = required_param('manualvalue', PARAM_INT);
            $calculatedvalue = optional_param('calculatedvalue', null, PARAM_INT);

            // Validazione valore (0-100 per percentuale)
            if ($manualvalue < 0 || $manualvalue > 100) {
                throw new invalid_parameter_exception('Il valore deve essere tra 0 e 100');
            }

            global $DB, $USER;
            $now = time();

            // Cerca record esistente
            $existing = $DB->get_record('local_compman_final_ratings', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector,
                'area_code' => $areacode,
                'method' => $method
            ]);

            if ($existing) {
                // Salva nello storico prima di aggiornare
                $history = new stdClass();
                $history->ratingid = $existing->id;
                $history->old_value = $existing->manual_value;
                $history->new_value = $manualvalue;
                $history->modifiedby = $USER->id;
                $history->timecreated = $now;
                $DB->insert_record('local_compman_final_history', $history);

                // Aggiorna record esistente
                $existing->manual_value = $manualvalue;
                $existing->modifiedby = $USER->id;
                $existing->timemodified = $now;
                $DB->update_record('local_compman_final_ratings', $existing);

                $ratingid = $existing->id;
            } else {
                // Crea nuovo record
                $newrecord = new stdClass();
                $newrecord->studentid = $studentid;
                $newrecord->courseid = $courseid;
                $newrecord->sector = $sector;
                $newrecord->area_code = $areacode;
                $newrecord->method = $method;
                $newrecord->calculated_value = $calculatedvalue;
                $newrecord->manual_value = $manualvalue;
                $newrecord->modifiedby = $USER->id;
                $newrecord->timecreated = $now;
                $newrecord->timemodified = $now;

                $ratingid = $DB->insert_record('local_compman_final_ratings', $newrecord);

                // Prima modifica va comunque nello storico
                $history = new stdClass();
                $history->ratingid = $ratingid;
                $history->old_value = $calculatedvalue;
                $history->new_value = $manualvalue;
                $history->modifiedby = $USER->id;
                $history->timecreated = $now;
                $DB->insert_record('local_compman_final_history', $history);
            }

            // Recupera info utente per la risposta
            $modifierinfo = $DB->get_record('user', ['id' => $USER->id], 'id, firstname, lastname');

            echo json_encode([
                'success' => true,
                'data' => [
                    'ratingid' => $ratingid,
                    'value' => $manualvalue,
                    'modifiedby' => fullname($modifierinfo),
                    'timemodified' => userdate($now, '%d/%m/%Y %H:%M')
                ],
                'message' => 'Valore salvato correttamente'
            ]);
            break;

        case 'get_rating_history':
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);
            $areacode = required_param('areacode', PARAM_ALPHANUMEXT);
            $method = required_param('method', PARAM_ALPHANUMEXT);

            global $DB;

            // Trova il rating
            $rating = $DB->get_record('local_compman_final_ratings', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector,
                'area_code' => $areacode,
                'method' => $method
            ]);

            if (!$rating) {
                echo json_encode([
                    'success' => true,
                    'data' => ['history' => [], 'hasHistory' => false],
                    'message' => 'Nessuna modifica manuale'
                ]);
                break;
            }

            // Recupera storico
            $history = $DB->get_records_sql("
                SELECT h.*, u.firstname, u.lastname
                FROM {local_compman_final_history} h
                JOIN {user} u ON u.id = h.modifiedby
                WHERE h.ratingid = ?
                ORDER BY h.timecreated DESC
                LIMIT 10
            ", [$rating->id]);

            $historyData = [];
            foreach ($history as $h) {
                $historyData[] = [
                    'oldValue' => $h->old_value !== null ? $h->old_value . '%' : 'N/D',
                    'newValue' => $h->new_value . '%',
                    'modifiedBy' => $h->firstname . ' ' . $h->lastname,
                    'date' => userdate($h->timecreated, '%d/%m/%Y'),
                    'time' => userdate($h->timecreated, '%H:%M')
                ];
            }

            // Info sul valore calcolato originale
            $originalCalculated = $rating->calculated_value !== null ? $rating->calculated_value . '%' : 'N/D';

            echo json_encode([
                'success' => true,
                'data' => [
                    'history' => $historyData,
                    'hasHistory' => !empty($historyData),
                    'originalCalculated' => $originalCalculated,
                    'currentValue' => $rating->manual_value . '%'
                ],
                'message' => ''
            ]);
            break;

        default:
            throw new invalid_parameter_exception('Azione non valida: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
