<?php
/**
 * AJAX endpoint to generate an AIDA cover letter using OpenAI.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    // Check authorization: capability OR auth table OR siteadmin.
    $canuse = has_capability('local/jobaida:use', $context);
    $isauthorized = $DB->record_exists('local_jobaida_auth', ['userid' => $USER->id, 'active' => 1]);
    if (!$canuse && !$isauthorized && !is_siteadmin()) {
        throw new Exception(get_string('not_authorized', 'local_jobaida'));
    }

    $jobad = required_param('job_ad', PARAM_RAW);
    $cv = required_param('cv_text', PARAM_RAW);
    $objectives = optional_param('objectives', '', PARAM_RAW);

    // Basic validation.
    $jobad = trim($jobad);
    $cv = trim($cv);
    $objectives = trim($objectives);

    if (strlen($jobad) < 50 || strlen($cv) < 50) {
        throw new Exception(get_string('error_too_short', 'local_jobaida'));
    }

    // Get language setting.
    $language = get_config('local_jobaida', 'letter_language') ?: 'it';

    // Generate letter via OpenAI.
    require_once(__DIR__ . '/classes/openai_client.php');
    $client = new \local_jobaida\openai_client();
    $result = $client->generate_letter($jobad, $cv, $objectives, $language);

    // Save to history.
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->job_ad = $jobad;
    $record->cv_text = $cv;
    $record->objectives = $objectives;
    $record->attention = $result->attention;
    $record->attention_rationale = $result->attention_rationale;
    $record->interest = $result->interest;
    $record->interest_rationale = $result->interest_rationale;
    $record->desire = $result->desire;
    $record->desire_rationale = $result->desire_rationale;
    $record->action = $result->action;
    $record->action_rationale = $result->action_rationale;
    $record->full_letter = $result->full_letter;
    $record->language = $language;
    $record->model_used = $result->model_used;
    $record->tokens_used = $result->tokens_used;
    $record->timecreated = time();
    $letterid = $DB->insert_record('local_jobaida_letters', $record);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $letterid,
            'attention' => $result->attention,
            'attention_rationale' => $result->attention_rationale,
            'interest' => $result->interest,
            'interest_rationale' => $result->interest_rationale,
            'desire' => $result->desire,
            'desire_rationale' => $result->desire_rationale,
            'action' => $result->action,
            'action_rationale' => $result->action_rationale,
            'full_letter' => $result->full_letter,
            'tokens_used' => $result->tokens_used,
            'model_used' => $result->model_used,
        ],
        'message' => 'Lettera generata con successo',
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
