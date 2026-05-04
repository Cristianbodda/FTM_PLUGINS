<?php
/**
 * AJAX endpoint: Save acceptance form items.
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

        case 'save_item':
            $acceptanceid = required_param('id', PARAM_INT);
            $accepted = required_param('accepted', PARAM_INT);
            // baseline/target = totale 10 settimane (decise dal coach).
            $baseline = required_param('baseline', PARAM_FLOAT);
            $target = required_param('target', PARAM_FLOAT);
            // Per-settimana: opzionali (anche loro decise dal coach, non calcolate).
            $baseline_week = optional_param('baseline_week', 0, PARAM_FLOAT);
            $target_week = optional_param('target_week', 0, PARAM_FLOAT);

            \local_ftm_sip\sip_manager::save_acceptance_item(
                $acceptanceid, $accepted, $baseline, $target, $baseline_week, $target_week
            );

            echo json_encode(['success' => true, 'message' => 'Salvato.']);
            break;

        case 'save_all':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $items_json = required_param('items', PARAM_RAW);
            $items = json_decode($items_json, true);

            if (!is_array($items)) {
                throw new Exception('Dati non validi.');
            }

            foreach ($items as $item) {
                if (!isset($item['id'])) continue;
                \local_ftm_sip\sip_manager::save_acceptance_item(
                    (int)$item['id'],
                    (int)($item['accepted'] ?? 0),
                    (float)($item['baseline'] ?? 0),
                    (float)($item['target'] ?? 0),
                    (float)($item['baseline_week'] ?? 0),
                    (float)($item['target_week'] ?? 0)
                );
            }

            echo json_encode(['success' => true, 'message' => 'Accettazione salvata.']);
            break;

        case 'create':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            \local_ftm_sip\sip_manager::create_default_acceptance($enrollmentid);
            $data = \local_ftm_sip\sip_manager::get_acceptance($enrollmentid);
            echo json_encode(['success' => true, 'data' => array_values($data)]);
            break;

        default:
            throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
