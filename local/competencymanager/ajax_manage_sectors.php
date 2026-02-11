<?php
/**
 * AJAX endpoint per gestione settori studente (Coach Assignment)
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
require_capability('local/competencymanager:managesectors', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    global $DB, $USER;

    switch ($action) {
        case 'get_sectors':
            // Recupera i settori di uno studente con ranking
            $userid = required_param('userid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);

            $sectors = \local_competencymanager\sector_manager::get_student_sectors_ranked($userid, $courseid);

            echo json_encode([
                'success' => true,
                'data' => $sectors,
                'message' => ''
            ]);
            break;

        case 'save_sectors':
            // Salva tutti i settori (primario, secondario, terziario)
            $userid = required_param('userid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $primary = optional_param('primary', '', PARAM_ALPHANUMEXT);
            $secondary = optional_param('secondary', '', PARAM_ALPHANUMEXT);
            $tertiary = optional_param('tertiary', '', PARAM_ALPHANUMEXT);

            // Validate user exists
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if (!$user) {
                throw new invalid_parameter_exception('Utente non trovato');
            }

            // Validate sectors are different
            $sectorArray = array_filter([$primary, $secondary, $tertiary]);
            if (count($sectorArray) !== count(array_unique($sectorArray))) {
                throw new invalid_parameter_exception('I settori devono essere diversi tra loro');
            }

            // Save sectors
            $result = \local_competencymanager\sector_manager::set_student_sectors(
                $userid, $primary, $secondary, $tertiary, $courseid
            );

            echo json_encode([
                'success' => true,
                'data' => [
                    'primary' => $primary,
                    'secondary' => $secondary,
                    'tertiary' => $tertiary
                ],
                'message' => 'Settori salvati con successo'
            ]);
            break;

        case 'add_sector':
            // Aggiunge un singolo settore (secondario/terziario)
            $userid = required_param('userid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);

            // Validate user exists
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if (!$user) {
                throw new invalid_parameter_exception('Utente non trovato');
            }

            // Validate sector is in allowed list
            $allowedSectors = array_keys(\local_competencymanager\sector_manager::SECTORS);
            if (!in_array($sector, $allowedSectors)) {
                throw new invalid_parameter_exception('Settore non valido: ' . $sector);
            }

            $result = \local_competencymanager\sector_manager::add_sector($userid, $sector, $courseid);

            echo json_encode([
                'success' => $result,
                'data' => ['sector' => $sector],
                'message' => $result ? 'Settore aggiunto' : 'Errore nell\'aggiunta del settore'
            ]);
            break;

        case 'remove_sector':
            // Rimuove un settore
            $userid = required_param('userid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);

            $result = \local_competencymanager\sector_manager::remove_sector($userid, $sector, $courseid);

            echo json_encode([
                'success' => true,
                'data' => ['sector' => $sector, 'removed' => $result],
                'message' => $result ? 'Settore rimosso' : 'Settore non presente'
            ]);
            break;

        case 'set_primary':
            // Imposta un settore come primario
            $userid = required_param('userid', PARAM_INT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $sector = required_param('sector', PARAM_ALPHANUMEXT);

            // Validate user exists
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if (!$user) {
                throw new invalid_parameter_exception('Utente non trovato');
            }

            $result = \local_competencymanager\sector_manager::set_primary_sector($userid, $sector, $courseid);

            echo json_encode([
                'success' => $result,
                'data' => ['sector' => $sector],
                'message' => $result ? 'Settore primario impostato' : 'Errore nell\'impostazione'
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
