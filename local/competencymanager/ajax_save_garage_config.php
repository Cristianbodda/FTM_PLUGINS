<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

// Check permissions.
$canmanage = has_capability('local/competencymanager:evaluate', $context) || is_siteadmin();
if (!$canmanage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $userid = required_param('userid', PARAM_INT);
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $selected_areas = optional_param('selected_areas', '[]', PARAM_RAW);
    $selected_competencies = optional_param('selected_competencies', '[]', PARAM_RAW);
    $excluded_competencies = optional_param('excluded_competencies', '[]', PARAM_RAW);
    $display_format = optional_param('display_format', 'percentage', PARAM_ALPHA);
    $show_overlay = optional_param('show_overlay', 0, PARAM_INT);
    $show_autovalutazione = optional_param('show_autovalutazione', 1, PARAM_INT);
    $show_coach_eval = optional_param('show_coach_eval', 1, PARAM_INT);
    $custom_threshold = optional_param('custom_threshold', '', PARAM_RAW);
    $enabled_sections = optional_param('enabled_sections', '[]', PARAM_RAW);
    $section_order = optional_param('section_order', '[]', PARAM_RAW);

    // Validate user exists.
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new Exception('Utente non trovato');
    }

    // Validate JSON arrays.
    $areas = json_decode($selected_areas, true);
    $comps = json_decode($selected_competencies, true);
    $excluded = json_decode($excluded_competencies, true);
    if (!is_array($areas) || !is_array($comps) || !is_array($excluded)) {
        throw new Exception('Formato dati non valido');
    }

    // Validate display_format.
    if (!in_array($display_format, ['percentage', 'qualitative'])) {
        $display_format = 'percentage';
    }

    $now = time();
    $coachid = $USER->id;
    $custom_threshold_val = ($custom_threshold !== '' && is_numeric($custom_threshold)) ? (int)$custom_threshold : null;

    // Check if record exists.
    $existing = $DB->get_record('local_garage_config', [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);

    // Validate section arrays.
    $enabledSecs = json_decode($enabled_sections, true);
    $orderSecs = json_decode($section_order, true);
    if (!is_array($enabledSecs)) $enabledSecs = [];
    if (!is_array($orderSecs)) $orderSecs = [];

    if ($existing) {
        $existing->selected_areas = json_encode($areas);
        $existing->selected_competencies = json_encode($comps);
        $existing->excluded_competencies = json_encode($excluded);
        $existing->display_format = $display_format;
        $existing->show_overlay = $show_overlay ? 1 : 0;
        $existing->show_autovalutazione = $show_autovalutazione ? 1 : 0;
        $existing->show_coach_eval = $show_coach_eval ? 1 : 0;
        $existing->custom_threshold = $custom_threshold_val;
        $existing->enabled_sections = json_encode($enabledSecs);
        $existing->section_order = json_encode($orderSecs);
        $existing->coachid = $coachid;
        $existing->timemodified = $now;
        $DB->update_record('local_garage_config', $existing);
        $message = 'Configurazione aggiornata';
    } else {
        $record = new stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->selected_areas = json_encode($areas);
        $record->selected_competencies = json_encode($comps);
        $record->excluded_competencies = json_encode($excluded);
        $record->display_format = $display_format;
        $record->show_overlay = $show_overlay ? 1 : 0;
        $record->show_autovalutazione = $show_autovalutazione ? 1 : 0;
        $record->show_coach_eval = $show_coach_eval ? 1 : 0;
        $record->custom_threshold = $custom_threshold_val;
        $record->enabled_sections = json_encode($enabledSecs);
        $record->section_order = json_encode($orderSecs);
        $record->coachid = $coachid;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_garage_config', $record);
        $message = 'Configurazione salvata';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'areas_count' => count($areas),
        'competencies_count' => count($comps),
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
