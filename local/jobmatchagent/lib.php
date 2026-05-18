<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for local_jobmatchagent.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation entry for users with access to JobMatch.
 *
 * @param global_navigation $navigation
 */
function local_jobmatchagent_extend_navigation(global_navigation $navigation) {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    $canmanage = has_capability('local/jobmatchagent:manage', $context);
    $isstudent = has_capability('local/jobmatchagent:viewown', $context);

    // For students: show link only if they have published matches.
    $hasmatches = false;
    if ($isstudent && !$canmanage) {
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_jobmatch_results')) {
            $hasmatches = $DB->record_exists_select(
                'local_jobmatch_results',
                "userid = :uid AND status = 'published'",
                ['uid' => $USER->id]
            );
        }
    }

    if ($canmanage || is_siteadmin()) {
        // Main entry point: the guided wizard.
        $jobmatchnode = $navigation->add(
            'JobMatch — Cerca lavoro',
            new moodle_url('/local/jobmatchagent/wizard.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'jobmatch_wizard',
            new pix_icon('i/search', '')
        );
        // Sub-items for advanced users / admin.
        $jobmatchnode->add(
            '⚙ Modalita avanzata (dashboard tecnica)',
            new moodle_url('/local/jobmatchagent/coach_dashboard.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'jobmatch_coach_advanced',
            new pix_icon('i/settings', '')
        );
        $jobmatchnode->add(
            'Aggiorna catalogo annunci (admin)',
            new moodle_url('/local/jobmatchagent/fetch_now.php', ['sesskey' => sesskey()]),
            navigation_node::TYPE_CUSTOM,
            null,
            'jobmatch_fetch',
            new pix_icon('i/reload', '')
        );
    }

    if ($hasmatches) {
        $navigation->add(
            get_string('mymatches', 'local_jobmatchagent'),
            new moodle_url('/local/jobmatchagent/student_view.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'jobmatch_student',
            new pix_icon('i/star', '')
        );
    }
}
