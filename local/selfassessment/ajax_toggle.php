<?php
// ============================================
// Self Assessment - AJAX Toggle/Reminder Endpoint
// ============================================
// Abilita/disabilita studenti e invia reminder
// ============================================

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();

// Headers JSON
header('Content-Type: application/json');

// Verifica permessi
$context = context_system::instance();
if (!has_capability('local/selfassessment:manage', $context)) {
    echo json_encode(['success' => false, 'error' => get_string('error_permission', 'local_selfassessment')]);
    exit;
}

// Leggi input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['userid'])) {
    echo json_encode(['success' => false, 'error' => 'Missing userid']);
    exit;
}

$userid = intval($input['userid']);

// Verifica che l'utente esista
if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
    echo json_encode(['success' => false, 'error' => get_string('error_notfound', 'local_selfassessment')]);
    exit;
}

// Manager
$manager = new \local_selfassessment\manager();

// Determina azione
if (isset($input['action']) && $input['action'] === 'reminder') {
    // Invia reminder
    if (!has_capability('local/selfassessment:sendreminder', $context)) {
        echo json_encode(['success' => false, 'error' => get_string('error_permission', 'local_selfassessment')]);
        exit;
    }
    
    $message = isset($input['message']) ? clean_param($input['message'], PARAM_TEXT) : '';
    $result = $manager->send_reminder($userid, $USER->id, $message);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? get_string('reminder_sent', 'local_selfassessment', 1) : 'Error sending reminder'
    ]);
    exit;
}

// Toggle enable/disable
if (!isset($input['enable'])) {
    echo json_encode(['success' => false, 'error' => 'Missing enable parameter']);
    exit;
}

$enable = intval($input['enable']);

if ($enable) {
    $result = $manager->enable_user($userid, $USER->id);
} else {
    $reason = isset($input['reason']) ? clean_param($input['reason'], PARAM_TEXT) : '';
    $result = $manager->disable_user($userid, $USER->id, $reason);
}

echo json_encode([
    'success' => $result,
    'message' => get_string('status_changed', 'local_selfassessment')
]);
