<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Plugin version and dependencies.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_jobmatchagent';
$plugin->version = 2026042700;
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v0.7.0 — Prompt AI svizzero/TI con regole AFC, lingue, esperienza';
$plugin->dependencies = [
    'local_jobaida' => ANY_VERSION,
    'local_competencymanager' => ANY_VERSION,
    'local_coachmanager' => ANY_VERSION,
    // local_ftm_jobsearch is OPTIONAL — checked at runtime (no hard dependency).
];
