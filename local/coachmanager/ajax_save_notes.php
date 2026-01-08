<?php
// ============================================
// AJAX: Salva note del coach
// ============================================
// File: ajax_save_notes.php
// Chiamato via AJAX da reports_v2.php
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_login();

header('Content-Type: application/json');

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Parametri
$studentid = required_param('studentid', PARAM_INT);
$notes = required_param('notes', PARAM_RAW);

// Pulisci le note
$notes = clean_param($notes, PARAM_TEXT);

// Verifica permessi
$context = context_system::instance();
if (!has_capability('moodle/site:viewreports', $context)) {
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

// Verifica che lo studente esista
$student = $DB->get_record('user', ['id' => $studentid]);
if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Studente non trovato']);
    exit;
}

try {
    // Verifica se la tabella esiste
    $dbman = $DB->get_manager();
    
    if (!$dbman->table_exists('local_coachmanager_notes')) {
        // Crea la tabella al volo se non esiste
        // (Normalmente andrebbe nel db/install.xml, ma per semplicità la creiamo qui)
        $table = new xmldb_table('local_coachmanager_notes');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('studentid_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
        $table->add_index('coachid_idx', XMLDB_INDEX_NOTUNIQUE, ['coachid']);
        
        $dbman->create_table($table);
    }
    
    // Cerca record esistente per questo studente e coach
    $existing = $DB->get_record('local_coachmanager_notes', [
        'studentid' => $studentid,
        'coachid' => $USER->id
    ]);
    
    $now = time();
    
    if ($existing) {
        // Aggiorna record esistente
        $existing->notes = $notes;
        $existing->timemodified = $now;
        $DB->update_record('local_coachmanager_notes', $existing);
        $recordid = $existing->id;
    } else {
        // Inserisci nuovo record
        $record = new stdClass();
        $record->studentid = $studentid;
        $record->coachid = $USER->id;
        $record->notes = $notes;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $recordid = $DB->insert_record('local_coachmanager_notes', $record);
    }
    
    echo json_encode([
        'success' => true,
        'recordid' => $recordid,
        'message' => 'Note salvate con successo',
        'timestamp' => date('d/m/Y H:i', $now)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Errore database: ' . $e->getMessage()
    ]);
}
