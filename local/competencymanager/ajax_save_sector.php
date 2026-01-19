<?php
/**
 * AJAX Endpoint - Salva Settore Primario
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/sector_manager.php');

use local_competencymanager\sector_manager;

header('Content-Type: application/json');

try {
    require_login();
    require_sesskey();

    $context = context_system::instance();
    require_capability('local/competencymanager:manage', $context);

    $userid = required_param('userid', PARAM_INT);
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $sector = optional_param('sector', '', PARAM_ALPHANUMEXT);

    // Valida userid
    if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        throw new moodle_exception('invalid_user', 'local_competencymanager');
    }

    // Valida settore (se specificato)
    if (!empty($sector) && !isset(sector_manager::SECTORS[$sector])) {
        throw new moodle_exception('invalid_sector', 'local_competencymanager');
    }

    if (empty($sector)) {
        // Rimuovi settore primario (imposta tutti a is_primary=0)
        $DB->execute(
            "UPDATE {local_student_sectors}
             SET is_primary = 0, timemodified = ?
             WHERE userid = ? AND courseid = ?",
            [time(), $userid, $courseid]
        );

        // Rimuovi anche da local_student_coaching
        $DB->execute(
            "UPDATE {local_student_coaching}
             SET sector = NULL, timemodified = ?
             WHERE userid = ? AND courseid = ?",
            [time(), $userid, $courseid]
        );

        echo json_encode([
            'success' => true,
            'message' => get_string('sector_removed', 'local_competencymanager')
        ]);
    } else {
        // Imposta settore primario
        sector_manager::set_primary_sector($userid, $sector, $courseid);

        echo json_encode([
            'success' => true,
            'message' => get_string('sector_saved', 'local_competencymanager'),
            'sector' => $sector,
            'sector_name' => sector_manager::SECTORS[$sector]
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

die();
