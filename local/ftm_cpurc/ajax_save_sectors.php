<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to save student sectors (primary, secondary, tertiary).
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);
    $primary = optional_param('primary', '', PARAM_ALPHANUMEXT);
    $secondary = optional_param('secondary', '', PARAM_ALPHANUMEXT);
    $tertiary = optional_param('tertiary', '', PARAM_ALPHANUMEXT);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('Utente non trovato');
    }

    // Validate sectors are different.
    $sectors = array_filter([$primary, $secondary, $tertiary]);
    if (count($sectors) !== count(array_unique($sectors))) {
        throw new Exception('I settori devono essere diversi tra loro');
    }

    // Save sectors using cpurc_manager.
    $result = \local_ftm_cpurc\cpurc_manager::set_student_sectors($userid, $primary, $secondary, $tertiary);

    // Also notify competencymanager if available (for quiz/autovalutazione assignment).
    $sectorManagerFile = $CFG->dirroot . '/local/competencymanager/classes/sector_manager.php';
    if (file_exists($sectorManagerFile) && !empty($primary)) {
        require_once($sectorManagerFile);
        // Set primary sector in competencymanager (triggers quiz assignment).
        \local_competencymanager\sector_manager::set_primary_sector($userid, $primary, 0);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settori salvati con successo',
        'primary' => $primary,
        'secondary' => $secondary,
        'tertiary' => $tertiary
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

die();
