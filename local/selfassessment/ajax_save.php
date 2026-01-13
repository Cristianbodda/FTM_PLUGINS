<?php
// ============================================
// Self Assessment - AJAX Save Endpoint
// ============================================
// Salva le autovalutazioni dello studente
// ============================================

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();
require_sesskey();

// Headers JSON
header('Content-Type: application/json');

// Verifica permessi
$context = context_system::instance();
if (!has_capability('local/selfassessment:complete', $context)) {
    echo json_encode(['success' => false, 'error' => get_string('error_permission', 'local_selfassessment')]);
    exit;
}

// Verifica se abilitato
if (!local_selfassessment_is_enabled($USER->id)) {
    echo json_encode(['success' => false, 'error' => get_string('error_disabled', 'local_selfassessment')]);
    exit;
}

// Leggi input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['assessments']) || !is_array($input['assessments'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Manager
$manager = new \local_selfassessment\manager();

// Salva ogni autovalutazione
$saved = 0;
$errors = 0;

foreach ($input['assessments'] as $competencyid => $level) {
    $competencyid = intval($competencyid);
    $level = intval($level);
    
    // Valida livello (1-6)
    if ($level < 1 || $level > 6) {
        continue;
    }
    
    // Verifica che la competenza esista
    if (!$DB->record_exists('competency', ['id' => $competencyid])) {
        $errors++;
        continue;
    }
    
    try {
        $manager->save_assessment($USER->id, $competencyid, $level);
        $saved++;
    } catch (Exception $e) {
        $errors++;
    }
}

echo json_encode([
    'success' => true,
    'saved' => $saved,
    'errors' => $errors,
    'message' => get_string('save_success', 'local_selfassessment')
]);
