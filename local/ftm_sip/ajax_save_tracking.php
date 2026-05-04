<?php
/**
 * AJAX endpoint: Save weekly tracking entries (search entries + coach evals).
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:coach', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {

        // ===== SEARCH ENTRIES =====

        case 'create_entry':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $area_key = required_param('area_key', PARAM_ALPHANUMEXT);
            $sip_week = required_param('sip_week', PARAM_INT);

            $data = [
                'entry_date' => optional_param('entry_date', 0, PARAM_INT),
                'company_name' => optional_param('company_name', '', PARAM_TEXT),
                'company_address' => optional_param('company_address', '', PARAM_TEXT),
                'company_email' => optional_param('company_email', '', PARAM_TEXT),
                'company_phone' => optional_param('company_phone', '', PARAM_TEXT),
                'contact_person' => optional_param('contact_person', '', PARAM_TEXT),
                'position' => optional_param('position', '', PARAM_TEXT),
                'urc_assigned' => optional_param('urc_assigned', 0, PARAM_INT),
                'occupation_fulltime' => optional_param('occupation_fulltime', 0, PARAM_INT),
                'occupation_parttime' => optional_param('occupation_parttime', 0, PARAM_INT),
                'method_letter' => optional_param('method_letter', 0, PARAM_INT),
                'method_person' => optional_param('method_person', 0, PARAM_INT),
                'method_phone' => optional_param('method_phone', 0, PARAM_INT),
                'result' => optional_param('result', 'pending', PARAM_ALPHANUMEXT),
                'result_reason' => optional_param('result_reason', '', PARAM_TEXT),
                'channel' => optional_param('channel', '', PARAM_TEXT),
                'notes' => optional_param('notes', '', PARAM_TEXT),
            ];

            // Parse date string if provided as YYYY-MM-DD.
            $date_str = optional_param('entry_date_str', '', PARAM_TEXT);
            if (!empty($date_str)) {
                $ts = strtotime($date_str);
                if ($ts) {
                    $data['entry_date'] = $ts;
                }
            }

            $id = \local_ftm_sip\sip_manager::create_search_entry($enrollmentid, $area_key, $sip_week, $data, $USER->id);

            echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => 'Voce aggiunta.']);
            break;

        case 'delete_entry':
            $entryid = required_param('entryid', PARAM_INT);
            $enrollmentid = required_param('enrollmentid', PARAM_INT);

            \local_ftm_sip\sip_manager::delete_search_entry($entryid, $enrollmentid);

            echo json_encode(['success' => true, 'message' => 'Voce eliminata.']);
            break;

        case 'get_entries':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $area_key = optional_param('area_key', '', PARAM_ALPHANUMEXT);
            $sip_week = optional_param('sip_week', 0, PARAM_INT);

            $entries = \local_ftm_sip\sip_manager::get_search_entries($enrollmentid, $area_key, $sip_week);

            echo json_encode(['success' => true, 'data' => array_values($entries)]);
            break;

        case 'get_weekly_summary':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);

            $summary = \local_ftm_sip\sip_manager::get_weekly_summary($enrollmentid);

            echo json_encode(['success' => true, 'data' => $summary]);
            break;

        // ===== COACH EVALUATIONS (Strategia / Autonomia) =====

        case 'save_eval':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $area_key = required_param('area_key', PARAM_ALPHANUMEXT);
            $sip_week = required_param('sip_week', PARAM_INT);
            $score = required_param('score', PARAM_INT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            \local_ftm_sip\sip_manager::save_coach_eval($enrollmentid, $area_key, $sip_week, $score, $USER->id, $notes);

            echo json_encode(['success' => true, 'message' => 'Valutazione salvata.']);
            break;

        case 'get_evals':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);

            $evals = \local_ftm_sip\sip_manager::get_coach_evals($enrollmentid);

            echo json_encode(['success' => true, 'data' => $evals]);
            break;

        // ===== SEARCH PROOFS (PDF UPLOAD) =====

        case 'upload_proof':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $month_year = required_param('month_year', PARAM_TEXT);

            if (empty($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File non caricato correttamente.');
            }

            $file = $_FILES['proof_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                throw new Exception('Formato non supportato. Usa PDF, JPG o PNG.');
            }

            // Store via Moodle file API.
            $fs = get_file_storage();
            $contenthash = sha1_file($file['tmp_name']);
            $fileinfo = [
                'contextid' => $context->id,
                'component' => 'local_ftm_sip',
                'filearea' => 'search_proofs',
                'itemid' => $enrollmentid,
                'filepath' => '/' . $month_year . '/',
                'filename' => $file['name'],
            ];

            // Delete existing file with same name if any.
            $existing = $fs->get_file(
                $fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']
            );
            if ($existing) {
                $existing->delete();
            }

            $stored = $fs->create_file_from_pathname($fileinfo, $file['tmp_name']);

            $proofid = \local_ftm_sip\sip_manager::save_search_proof(
                $enrollmentid,
                $month_year,
                $file['name'],
                $stored->get_contenthash(),
                $file['size'],
                $USER->id
            );

            echo json_encode(['success' => true, 'data' => ['id' => $proofid, 'filename' => $file['name']], 'message' => 'Documento caricato.']);
            break;

        case 'get_proofs':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $proofs = \local_ftm_sip\sip_manager::get_search_proofs($enrollmentid);

            $list = [];
            foreach ($proofs as $p) {
                $list[] = [
                    'id' => $p->id,
                    'month_year' => $p->month_year,
                    'filename' => $p->filename,
                    'date' => userdate($p->timecreated, '%d/%m/%Y'),
                ];
            }

            echo json_encode(['success' => true, 'data' => $list]);
            break;

        default:
            throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
