<?php
/**
 * AJAX endpoint per gestione pesi ponderazione valutazioni
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

    global $DB, $USER;

    switch ($action) {
        case 'get_weights':
            // Recupera i pesi per uno studente/corso/settore
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);
            $leveltype = optional_param('leveltype', 'area', PARAM_ALPHA);

            $weights = $DB->get_records('local_compman_weights', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector,
                'level_type' => $leveltype
            ]);

            $result = [];
            foreach ($weights as $w) {
                $result[$w->item_code] = [
                    'quiz' => (int)$w->weight_quiz,
                    'auto' => (int)$w->weight_auto,
                    'lab' => (int)$w->weight_lab,
                    'coach' => (int)$w->weight_coach
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => ''
            ]);
            break;

        case 'save_weight':
            // Salva un singolo peso
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);
            $leveltype = optional_param('leveltype', 'area', PARAM_ALPHA);
            $itemcode = required_param('itemcode', PARAM_ALPHANUMEXT);
            $source = required_param('source', PARAM_ALPHA); // quiz, auto, lab, coach
            $weight = required_param('weight', PARAM_INT);

            // Validazione
            if ($weight < 0 || $weight > 100) {
                throw new invalid_parameter_exception('Il peso deve essere tra 0 e 100');
            }

            if (!in_array($source, ['quiz', 'auto', 'lab', 'coach'])) {
                throw new invalid_parameter_exception('Fonte non valida');
            }

            $now = time();

            // Cerca record esistente
            $existing = $DB->get_record('local_compman_weights', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector,
                'level_type' => $leveltype,
                'item_code' => $itemcode
            ]);

            $fieldname = 'weight_' . $source;

            if ($existing) {
                $existing->$fieldname = $weight;
                $existing->modifiedby = $USER->id;
                $existing->timemodified = $now;
                $DB->update_record('local_compman_weights', $existing);
            } else {
                $newrecord = new stdClass();
                $newrecord->studentid = $studentid;
                $newrecord->courseid = $courseid;
                $newrecord->sector = $sector;
                $newrecord->level_type = $leveltype;
                $newrecord->item_code = $itemcode;
                $newrecord->weight_quiz = 100;
                $newrecord->weight_auto = 100;
                $newrecord->weight_lab = 100;
                $newrecord->weight_coach = 100;
                $newrecord->$fieldname = $weight;
                $newrecord->modifiedby = $USER->id;
                $newrecord->timecreated = $now;
                $newrecord->timemodified = $now;
                $DB->insert_record('local_compman_weights', $newrecord);
            }

            echo json_encode([
                'success' => true,
                'data' => ['weight' => $weight, 'source' => $source, 'item' => $itemcode],
                'message' => 'Peso salvato'
            ]);
            break;

        case 'save_all_weights':
            // Salva tutti i pesi per un'area in una volta
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);
            $leveltype = optional_param('leveltype', 'area', PARAM_ALPHA);
            $itemcode = required_param('itemcode', PARAM_ALPHANUMEXT);
            $weightquiz = required_param('weight_quiz', PARAM_INT);
            $weightauto = required_param('weight_auto', PARAM_INT);
            $weightlab = required_param('weight_lab', PARAM_INT);
            $weightcoach = required_param('weight_coach', PARAM_INT);

            // Validazione
            foreach ([$weightquiz, $weightauto, $weightlab, $weightcoach] as $w) {
                if ($w < 0 || $w > 100) {
                    throw new invalid_parameter_exception('I pesi devono essere tra 0 e 100');
                }
            }

            $now = time();

            $existing = $DB->get_record('local_compman_weights', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector,
                'level_type' => $leveltype,
                'item_code' => $itemcode
            ]);

            if ($existing) {
                $existing->weight_quiz = $weightquiz;
                $existing->weight_auto = $weightauto;
                $existing->weight_lab = $weightlab;
                $existing->weight_coach = $weightcoach;
                $existing->modifiedby = $USER->id;
                $existing->timemodified = $now;
                $DB->update_record('local_compman_weights', $existing);
            } else {
                $newrecord = new stdClass();
                $newrecord->studentid = $studentid;
                $newrecord->courseid = $courseid;
                $newrecord->sector = $sector;
                $newrecord->level_type = $leveltype;
                $newrecord->item_code = $itemcode;
                $newrecord->weight_quiz = $weightquiz;
                $newrecord->weight_auto = $weightauto;
                $newrecord->weight_lab = $weightlab;
                $newrecord->weight_coach = $weightcoach;
                $newrecord->modifiedby = $USER->id;
                $newrecord->timecreated = $now;
                $newrecord->timemodified = $now;
                $DB->insert_record('local_compman_weights', $newrecord);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'item' => $itemcode,
                    'quiz' => $weightquiz,
                    'auto' => $weightauto,
                    'lab' => $weightlab,
                    'coach' => $weightcoach
                ],
                'message' => 'Pesi salvati'
            ]);
            break;

        case 'reset_weights':
            // Resetta tutti i pesi a 100 per uno studente/corso/settore
            $studentid = required_param('studentid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);

            $DB->delete_records('local_compman_weights', [
                'studentid' => $studentid,
                'courseid' => $courseid,
                'sector' => $sector
            ]);

            echo json_encode([
                'success' => true,
                'data' => [],
                'message' => 'Pesi resettati a 100%'
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
