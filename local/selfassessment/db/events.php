<?php
// ============================================
// Self Assessment - Event Observers
// ============================================
// Cattura eventi per assegnare automaticamente
// competenze dopo completamento quiz
// ============================================

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_selfassessment\observer::quiz_attempt_submitted',
        'internal' => false,
        'priority' => 200,
    ],
];
