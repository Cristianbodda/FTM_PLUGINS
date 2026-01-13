<?php
/**
 * AJAX - Salva Autovalutazione Studente
 * 
 * @package    local_competencymanager
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$competencyid = required_param('competencyid', PARAM_INT);
$level = required_param('level', PARAM_INT);
$comment = optional_param('comment', '', PARAM_TEXT);

header('Content-Type: application/json');

// Validazione livello Bloom (1-6)
if ($level < 1 || $level > 6) {
    echo json_encode(['success' => false, 'error' => 'Livello non valido (deve essere 1-6)']);
    exit;
}

// Verifica che la competenza esista
$competency = $DB->get_record('competency', ['id' => $competencyid]);
if (!$competency) {
    echo json_encode(['success' => false, 'error' => 'Competenza non trovata']);
    exit;
}

$userid = $USER->id;
$now = time();

try {
    // Verifica se esiste già un'autovalutazione per questa competenza
    $existing = $DB->get_record('local_selfassessment', [
        'userid' => $userid,
        'competencyid' => $competencyid
    ]);
    
    if ($existing) {
        // Aggiorna
        $existing->level = $level;
        $existing->comment = $comment;
        $existing->timemodified = $now;
        
        $DB->update_record('local_selfassessment', $existing);
        
        echo json_encode([
            'success' => true,
            'message' => 'Autovalutazione aggiornata',
            'id' => $existing->id,
            'action' => 'updated'
        ]);
    } else {
        // Inserisci nuovo
        $record = new stdClass();
        $record->userid = $userid;
        $record->competencyid = $competencyid;
        $record->level = $level;
        $record->comment = $comment;
        $record->timecreated = $now;
        $record->timemodified = $now;
        
        $id = $DB->insert_record('local_selfassessment', $record);
        
        echo json_encode([
            'success' => true,
            'message' => 'Autovalutazione salvata',
            'id' => $id,
            'action' => 'created'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Errore database: ' . $e->getMessage()
    ]);
}
