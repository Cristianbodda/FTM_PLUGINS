<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Coach decision endpoint: publish / discard / onhold a match.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$resultid = required_param('resultid', PARAM_INT);
$decision = required_param('decision', PARAM_ALPHA);
$note = optional_param('coach_note', '', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

global $USER, $DB;

$valid = ['published', 'discarded', 'onhold'];
if (!in_array($decision, $valid, true)) {
    throw new moodle_exception('invalidparameter', 'error');
}

$result = $DB->get_record('local_jobmatch_results', ['id' => $resultid], '*', MUST_EXIST);

if (!\local_jobmatchagent\match_engine::coach_can_manage_student($USER->id, $result->userid)) {
    throw new moodle_exception('err_invalid_student', 'local_jobmatchagent');
}

$update = (object) [
    'id' => $resultid,
    'status' => $decision,
    'coach_decision_userid' => $USER->id,
    'coach_decision_time' => time(),
];
if ($note !== '') {
    $update->coach_note = $note;
}
if ($decision === 'published') {
    $update->published_to_student_at = time();
}

$DB->update_record('local_jobmatch_results', $update);

// Notify student if published.
if ($decision === 'published') {
    $student = $DB->get_record('user', ['id' => $result->userid]);
    if ($student) {
        $offer = $DB->get_record('local_jobmatch_offers', ['id' => $result->offer_id]);
        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $student;
        $message->subject = '[JobMatch] ' . ($offer ? $offer->title : 'Nuova opportunita');
        $message->fullmessage = 'Il tuo coach ha selezionato una nuova opportunita per te. Vai su "Le mie opportunita" per visualizzarla.';
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>Il tuo coach ha selezionato una nuova opportunita per te.</p>'
            . '<p><a href="' . (new moodle_url('/local/jobmatchagent/student_view.php'))->out(false) . '">Vai alle mie opportunita</a></p>';
        $message->smallmessage = 'Nuova opportunita JobMatch disponibile';
        $message->notification = 1;
        $message->contexturl = (new moodle_url('/local/jobmatchagent/student_view.php'))->out(false);
        $message->contexturlname = 'Le mie opportunita';
        message_send($message);
    }
}

if ($returnurl) {
    redirect(new moodle_url($returnurl));
}
redirect(new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $result->userid]));
