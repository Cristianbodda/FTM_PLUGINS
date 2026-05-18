<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student feedback endpoint: interested / not_interested / already_applied.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:viewown', $context);

$resultid = required_param('resultid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);
$reasontext = optional_param('reason_text', '', PARAM_TEXT);

global $USER, $DB;

$valid = ['interested', 'not_interested', 'already_applied', 'applied', 'hired', 'rejected'];
if (!in_array($action, $valid, true)) {
    throw new moodle_exception('invalidparameter', 'error');
}

$result = $DB->get_record('local_jobmatch_results', ['id' => $resultid], '*', MUST_EXIST);

// Ownership check.
if ((int) $result->userid !== (int) $USER->id) {
    throw new moodle_exception('err_invalid_match', 'local_jobmatchagent');
}

// Must be published.
if ($result->status !== 'published') {
    throw new moodle_exception('err_invalid_match', 'local_jobmatchagent');
}

$record = (object) [
    'result_id' => $resultid,
    'userid' => $USER->id,
    'action' => $action,
    'reason_text' => $reasontext ?: null,
    'ai_letter_id' => null,
    'timecreated' => time(),
];
$DB->insert_record('local_jobmatch_student_actions', $record);

// If "interested", redirect to JobAIDA precompiled with offer text + CV.
if ($action === 'interested') {
    $offer = $DB->get_record('local_jobmatch_offers', ['id' => $result->offer_id]);
    $cv = null;
    if ($result->cv_snapshot_id) {
        $cv = $DB->get_record('local_jobmatch_cv_snapshots', ['id' => $result->cv_snapshot_id]);
    }

    // Prefill via session for JobAIDA to read (JobAIDA index.php would need a small hook).
    // For now, just redirect to JobAIDA index — student can manually paste from this match.
    // F3 will add proper bridge.
    $url = new moodle_url('/local/jobaida/index.php');
    redirect($url, get_string('sv_action_saved', 'local_jobmatchagent'), 2);
}

redirect(new moodle_url('/local/jobmatchagent/student_view.php'),
    get_string('sv_action_saved', 'local_jobmatchagent'), 2);
