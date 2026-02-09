<?php
/**
 * AJAX endpoint for saving coach evaluations
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/coach_evaluation_manager.php');

use local_competencymanager\coach_evaluation_manager;

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/competencymanager:evaluate', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $evaluationid = required_param('evaluationid', PARAM_INT);

    // Verify edit permission
    if (!coach_evaluation_manager::can_edit($evaluationid)) {
        throw new moodle_exception('no_permission', 'local_competencymanager');
    }

    $result = ['success' => true, 'message' => '', 'data' => []];

    switch ($action) {
        case 'save_ratings':
            $ratingsJson = required_param('ratings', PARAM_RAW);
            $ratings = json_decode($ratingsJson, true);

            if (!is_array($ratings)) {
                throw new invalid_parameter_exception('Invalid ratings format');
            }

            // Validate each rating
            foreach ($ratings as $r) {
                if (!isset($r['competencyid']) || !isset($r['rating'])) {
                    throw new invalid_parameter_exception('Missing rating data');
                }
                $rating = (int)$r['rating'];
                if ($rating < 0 || $rating > 6) {
                    throw new invalid_parameter_exception('Rating must be between 0 and 6');
                }
            }

            $count = coach_evaluation_manager::save_ratings_batch($evaluationid, $ratings);
            $result['message'] = "Saved $count ratings";
            $result['stats'] = coach_evaluation_manager::get_rating_stats($evaluationid);
            $result['average'] = coach_evaluation_manager::calculate_average($evaluationid);
            break;

        case 'save_notes':
            $notes = optional_param('notes', '', PARAM_TEXT);
            coach_evaluation_manager::update_notes($evaluationid, $notes);
            $result['message'] = 'Notes saved';
            break;

        case 'get_stats':
            $result['data'] = [
                'stats' => coach_evaluation_manager::get_rating_stats($evaluationid),
                'average' => coach_evaluation_manager::calculate_average($evaluationid)
            ];
            break;

        default:
            throw new invalid_parameter_exception('Unknown action: ' . $action);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
