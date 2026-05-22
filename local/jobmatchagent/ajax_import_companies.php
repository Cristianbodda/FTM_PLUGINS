<?php
/**
 * AJAX endpoint — importa aziende da file CSV.
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
    // Verifica che il file sia stato caricato.
    if (empty($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['csvfile']['error'] ?? -1;
        throw new Exception('Errore upload file (codice ' . (int)$err . '). Verifica che il file non superi il limite di dimensione.');
    }

    $file = $_FILES['csvfile'];

    // Validazione MIME / estensione.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        throw new Exception('Formato file non supportato. Caricare un file .csv o .txt');
    }

    // Copia in tmp sicuro.
    $tmp_dir  = make_temp_directory('local_jobmatchagent_import');
    $tmp_file = $tmp_dir . '/' . clean_filename($file['name']);

    if (!move_uploaded_file($file['tmp_name'], $tmp_file)) {
        throw new Exception('Impossibile salvare il file caricato. Controlla i permessi della directory temporanea.');
    }

    // Leggi contenuto.
    $content = file_get_contents($tmp_file);
    @unlink($tmp_file);

    if ($content === false || trim($content) === '') {
        throw new Exception('Il file CSV e\' vuoto o non leggibile.');
    }

    // Rileva encoding: se non UTF-8 valido, prova conversione da Latin-1.
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    // Importa.
    $result = \local_jobmatchagent\company_manager::import_from_csv($content);

    echo json_encode([
        'success' => true,
        'data'    => [
            'inserted' => $result['inserted'],
            'skipped'  => $result['skipped'],
            'errors'   => $result['errors'],
        ],
        'message' => $result['inserted'] . ' importate, ' . $result['skipped'] . ' gia presenti.',
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
