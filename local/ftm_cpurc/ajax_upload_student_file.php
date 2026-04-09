<?php
/**
 * AJAX endpoint for uploading files to student private files.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:view', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_FILES['file'])) {
        throw new Exception('Nessun file caricato');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE => 'Nessun file selezionato',
        ];
        throw new Exception($errors[$file['error']] ?? 'Errore upload: ' . $file['error']);
    }

    $userids_raw = required_param('userids', PARAM_RAW);
    $userids = array_filter(array_map('intval', explode(',', $userids_raw)));
    if (empty($userids)) {
        throw new Exception('Nessuno studente selezionato');
    }

    $folder = optional_param('folder', '/', PARAM_RAW);
    // Sanitize folder path.
    $folder = '/' . trim($folder, '/');
    if ($folder !== '/') {
        $folder .= '/';
    }

    $filename = clean_param($file['name'], PARAM_FILE);
    $tmppath = $file['tmp_name'];

    $fs = get_file_storage();
    $results = [];
    $successcount = 0;

    foreach ($userids as $uid) {
        $user = $DB->get_record('user', ['id' => $uid, 'deleted' => 0]);
        if (!$user) {
            $results[] = ['userid' => $uid, 'success' => false, 'message' => 'Utente non trovato'];
            continue;
        }

        $usercontext = context_user::instance($uid);

        // Ensure directory exists.
        if ($folder !== '/') {
            $fs->create_directory($usercontext->id, 'user', 'private', 0, $folder);
        }

        // Check if file already exists - if so, delete it first (overwrite).
        $existing = $fs->get_file($usercontext->id, 'user', 'private', 0, $folder, $filename);
        if ($existing) {
            $existing->delete();
        }

        // Save file.
        $fileinfo = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => $folder,
            'filename' => $filename,
        ];

        $newfile = $fs->create_file_from_pathname($fileinfo, $tmppath);
        if ($newfile) {
            $successcount++;
            $results[] = [
                'userid' => $uid,
                'success' => true,
                'name' => fullname($user),
                'message' => 'OK',
            ];
        } else {
            $results[] = [
                'userid' => $uid,
                'success' => false,
                'name' => fullname($user),
                'message' => 'Errore nel salvataggio',
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "File caricato per {$successcount}/" . count($userids) . " studenti",
        'results' => $results,
        'filename' => $filename,
        'folder' => $folder,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
