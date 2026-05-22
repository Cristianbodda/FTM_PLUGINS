<?php
/**
 * AJAX endpoint — CRUD aziende ticinesi.
 *
 * Azioni supportate:
 *   get        — restituisce dati singola azienda (per modale edit)
 *   save       — crea o aggiorna azienda
 *   set_status — cambia status (active|inactive|unverified)
 *   delete     — soft-delete (imposta status=inactive)
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/company_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];

    switch ($action) {

        // ------------------------------------------------------------------ //
        case 'get':
            $id = required_param('id', PARAM_INT);
            if ($id <= 0) {
                throw new Exception('ID non valido.');
            }
            $company = \local_jobmatchagent\company_manager::get_company($id);
            if (!$company) {
                throw new Exception('Azienda non trovata (ID ' . $id . ').');
            }
            $result['data'] = (array) $company;
            break;

        // ------------------------------------------------------------------ //
        case 'save':
            $id = optional_param('id', 0, PARAM_INT);

            // nome is required for new records, optional for updates.
            $nome = optional_param('nome', '', PARAM_TEXT);
            if ($id <= 0 && $nome === '') {
                throw new Exception('Il nome è obbligatorio.');
            }

            $data = [
                'settore_ftm'         => optional_param('settore_ftm',         'ALTRO',     PARAM_ALPHA),
                'localita'            => optional_param('localita',            '',          PARAM_TEXT),
                'cap'                 => optional_param('cap',                 '',          PARAM_ALPHANUMEXT),
                'indirizzo'           => optional_param('indirizzo',           '',          PARAM_TEXT),
                'dimensione'          => optional_param('dimensione',          'unknown',   PARAM_ALPHANUMEXT),
                'website'             => optional_param('website',             '',          PARAM_URL),
                'email'               => optional_param('email',               '',          PARAM_EMAIL),
                'referente'           => optional_param('referente',           '',          PARAM_TEXT),
                'anno_primo_contatto' => optional_param('anno_primo_contatto', null,        PARAM_INT),
                'settore_raw'         => optional_param('settore_raw',         '',          PARAM_TEXT),
                'note_interne'        => optional_param('note_interne',        '',          PARAM_TEXT),
                'source'              => optional_param('source',              'manual',    PARAM_ALPHANUMEXT),
                'status'              => optional_param('status',              'unverified', PARAM_ALPHANUMEXT),
            ];

            if ($nome !== '') {
                $data['nome'] = $nome;
            }

            // Sanity: website vuoto salvato come null.
            if (empty($data['website'])) {
                $data['website'] = null;
            }
            if (empty($data['email'])) {
                $data['email'] = null;
            }
            if ($data['anno_primo_contatto'] !== null && ($data['anno_primo_contatto'] < 1990 || $data['anno_primo_contatto'] > 2099)) {
                $data['anno_primo_contatto'] = null;
            }

            if ($id > 0) {
                $data['id'] = $id;
            }

            $saved_id = \local_jobmatchagent\company_manager::save_company($data);
            $result['data']    = ['id' => $saved_id];
            $result['message'] = $id > 0 ? 'Azienda aggiornata.' : 'Azienda creata con ID ' . $saved_id . '.';
            break;

        // ------------------------------------------------------------------ //
        case 'set_status':
            $id     = required_param('id',     PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);

            if ($id <= 0) {
                throw new Exception('ID non valido.');
            }

            $allowed_statuses = ['active', 'inactive', 'unverified'];
            if (!in_array($status, $allowed_statuses, true)) {
                throw new Exception('Status non valido: ' . $status);
            }

            // Verifica esistenza.
            $company = \local_jobmatchagent\company_manager::get_company($id);
            if (!$company) {
                throw new Exception('Azienda non trovata (ID ' . $id . ').');
            }

            \local_jobmatchagent\company_manager::set_status($id, $status);
            $result['message'] = 'Status aggiornato a "' . $status . '".';
            $result['data']    = ['id' => $id, 'status' => $status];
            break;

        // ------------------------------------------------------------------ //
        case 'delete':
            $id = required_param('id', PARAM_INT);
            if ($id <= 0) {
                throw new Exception('ID non valido.');
            }

            $company = \local_jobmatchagent\company_manager::get_company($id);
            if (!$company) {
                throw new Exception('Azienda non trovata (ID ' . $id . ').');
            }

            // Soft delete — imposta status=inactive.
            \local_jobmatchagent\company_manager::set_status($id, 'inactive');
            $result['message'] = 'Azienda disattivata (soft delete).';
            $result['data']    = ['id' => $id];
            break;

        default:
            throw new Exception('Azione non riconosciuta: ' . $action);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data'    => [],
    ]);
}

die();
