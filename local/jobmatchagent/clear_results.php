<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Clear all match results (and CV snapshots) for a single student.
 * Useful for testing: lets the coach reset and re-run searches from scratch.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/match_engine.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$studentid = required_param('userid', PARAM_INT);
$alsosnapshots = optional_param('snapshots', 0, PARAM_INT) ? true : false;
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

global $USER, $DB;

if (!\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $studentid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$studentname = fullname($student);

$starttime = microtime(true);

// Count before delete.
$countresults = $DB->count_records('local_jobmatch_results', ['userid' => $studentid]);
$countactions = $DB->count_records('local_jobmatch_student_actions', ['userid' => $studentid]);
$countsnapshots = 0;

// Delete student actions first (FK to results).
$DB->delete_records('local_jobmatch_student_actions', ['userid' => $studentid]);

// Delete results.
$DB->delete_records('local_jobmatch_results', ['userid' => $studentid]);

// Optionally delete CV snapshots.
if ($alsosnapshots) {
    $countsnapshots = $DB->count_records('local_jobmatch_cv_snapshots', ['userid' => $studentid]);
    $DB->delete_records('local_jobmatch_cv_snapshots', ['userid' => $studentid]);
}

$durationms = (int) round((microtime(true) - $starttime) * 1000);

// Audit log.
$DB->insert_record('local_jobmatch_logs', (object) [
    'run_time' => time(),
    'task_name' => 'clear_results',
    'source_id' => null,
    'offers_fetched' => 0,
    'results_created' => -$countresults,
    'ai_calls' => 0,
    'ai_tokens' => 0,
    'errors_json' => json_encode([
        'student' => $studentname,
        'deleted_results' => $countresults,
        'deleted_actions' => $countactions,
        'deleted_snapshots' => $countsnapshots,
    ]),
    'duration_ms' => $durationms,
]);

$msg = 'Reset completato per ' . $studentname . ': '
    . $countresults . ' match cancellati, '
    . $countactions . ' feedback cancellati'
    . ($alsosnapshots ? ', ' . $countsnapshots . ' snapshot CV cancellati' : '')
    . '.';

$target = $returnurl
    ? new moodle_url($returnurl)
    : new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $studentid]);

redirect($target, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
