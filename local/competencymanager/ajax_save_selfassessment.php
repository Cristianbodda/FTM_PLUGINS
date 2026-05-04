<?php
/**
 * DEPRECATO: vecchio endpoint di salvataggio autovalutazione.
 * Dal 14/04/2026 il salvataggio è gestito da local/selfassessment/ajax_save.php.
 * Questo file è lasciato come stub per segnalare l'errore in caso di chiamate residue.
 *
 * @package    local_competencymanager
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Endpoint deprecato. Usa /local/selfassessment/ajax_save.php',
]);
die();
