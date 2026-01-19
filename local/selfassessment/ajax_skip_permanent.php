<?php
// ============================================
// Self Assessment - AJAX Skip Permanente
// ============================================
// Salva lo skip permanente nel database
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');

// Verifica login e sesskey
require_login();
require_sesskey();

header('Content-Type: application/json');

try {
    global $DB, $USER;

    // Verifica che l'utente abbia la capability
    $context = context_system::instance();
    require_capability('local/selfassessment:complete', $context);

    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action']) || $input['action'] !== 'skip_permanent') {
        throw new Exception('Azione non valida');
    }

    // Controlla se esiste già un record per l'utente
    $existing = $DB->get_record('local_selfassessment_status', ['userid' => $USER->id]);

    if ($existing) {
        // Aggiorna il record esistente
        $existing->skip_accepted = 1;
        $existing->skip_time = time();
        $DB->update_record('local_selfassessment_status', $existing);
    } else {
        // Crea nuovo record
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->enabled = 1;
        $record->skip_accepted = 1;
        $record->skip_time = time();
        $record->timecreated = time();
        $DB->insert_record('local_selfassessment_status', $record);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Skip permanente salvato'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
