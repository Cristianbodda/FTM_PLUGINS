<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information for local_ftm_suite
 *
 * Meta-installer plugin for the FTM Plugin Suite.
 * This plugin declares all 9 FTM plugins as dependencies, ensuring
 * Moodle's plugin manager validates the complete suite is installed.
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ftm_suite';
$plugin->version   = 2026010902;        // YYYYMMDDXX
$plugin->requires  = 2024042200;        // Moodle 4.4+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.0.0';

// All 9 FTM plugins as dependencies
// Installation order enforced by Moodle's dependency resolver:
// Tier 1: qbank_competenciesbyquestion (foundation)
// Tier 2: local_competencymanager (core)
// Tier 3: local_coachmanager (depends on competencymanager)
// Tier 4: local_labeval (depends on coachmanager)
// Tier 5: standalone plugins (selfassessment, competencyreport, competencyxmlimport)
// Tier 6: integration plugins (ftm_hub, block_ftm_tools)
$plugin->dependencies = [
    // Tier 1 - Foundation
    'qbank_competenciesbyquestion' => 2026010901,

    // Tier 2 - Core
    'local_competencymanager' => 2026010901,

    // Tier 3 - Management
    'local_coachmanager' => 2025122303,

    // Tier 4 - Evaluation
    'local_labeval' => 2024123001,

    // Tier 5 - Standalone
    'local_selfassessment' => 2025122402,
    'local_competencyreport' => 2025120501,
    'local_competencyxmlimport' => 2026010901,

    // Tier 6 - Integration
    'local_ftm_hub' => 2026010902,
    'block_ftm_tools' => 2026010902,
];
