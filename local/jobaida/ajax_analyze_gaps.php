<?php
/**
 * AJAX endpoint to analyze gaps between a job ad and CV using OpenAI.
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
    // Check authorization.
    $canuse = has_capability('local/jobaida:use', $context);
    $isauthorized = $DB->record_exists('local_jobaida_auth', ['userid' => $USER->id, 'active' => 1]);
    if (!$canuse && !$isauthorized && !is_siteadmin()) {
        throw new Exception(get_string('not_authorized', 'local_jobaida'));
    }

    $jobad = required_param('job_ad', PARAM_RAW);
    $cv = required_param('cv_text', PARAM_RAW);

    $jobad = trim($jobad);
    $cv = trim($cv);

    if (strlen($jobad) < 50 || strlen($cv) < 50) {
        throw new Exception(get_string('error_too_short', 'local_jobaida'));
    }

    $language = get_config('local_jobaida', 'letter_language') ?: 'it';

    require_once(__DIR__ . '/classes/openai_client.php');
    $client = new \local_jobaida\openai_client();
    $result = $client->analyze_gaps($jobad, $cv, $language);

    echo json_encode([
        'success' => true,
        'data' => [
            'company_name' => $result->company_name,
            'role' => $result->role,
            'requirements' => $result->requirements,
            'strengths' => $result->strengths,
            'overall_match_percentage' => $result->overall_match_percentage,
            'coaching_tip' => $result->coaching_tip,
            'tokens_used' => $result->tokens_used,
        ],
        'message' => 'Analisi completata',
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
