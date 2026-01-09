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
 * English language strings for local_ftm_suite
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['pluginname'] = 'FTM Suite Installer';

// Privacy
$string['privacy:metadata'] = 'The FTM Suite Installer plugin does not store any personal data.';

// Capabilities
$string['ftm_suite:viewstatus'] = 'View FTM Suite installation status';

// Status page
$string['pagetitle'] = 'FTM Suite Installation Status';
$string['pageheading'] = 'FTM Plugin Suite Status';
$string['description'] = 'This page displays the installation status of all FTM plugins that make up the complete suite.';

// Table headers
$string['plugin'] = 'Plugin';
$string['component'] = 'Component';
$string['requiredversion'] = 'Required Version';
$string['installedversion'] = 'Installed Version';
$string['status'] = 'Status';

// Status labels
$string['status_installed'] = 'Installed';
$string['status_missing'] = 'Missing';
$string['status_outdated'] = 'Outdated';
$string['status_unknown'] = 'Unknown';

// Plugin descriptions
$string['plugin_qbank_competenciesbyquestion'] = 'Question Bank - Competencies';
$string['plugin_local_competencymanager'] = 'Competency Manager (Core)';
$string['plugin_local_coachmanager'] = 'Coach Manager';
$string['plugin_local_labeval'] = 'Laboratory Evaluation';
$string['plugin_local_selfassessment'] = 'Self Assessment';
$string['plugin_local_competencyreport'] = 'Competency Report';
$string['plugin_local_competencyxmlimport'] = 'XML/Word Import';
$string['plugin_local_ftm_hub'] = 'FTM Hub';
$string['plugin_block_ftm_tools'] = 'FTM Tools Block';

// Summary
$string['summary'] = 'Summary';
$string['total_plugins'] = 'Total plugins';
$string['installed_plugins'] = 'Installed';
$string['missing_plugins'] = 'Missing';
$string['outdated_plugins'] = 'Outdated';

// Messages
$string['all_installed'] = 'All FTM plugins are installed and up to date.';
$string['some_missing'] = 'Some FTM plugins are missing or outdated. Please install them to use the complete suite.';
$string['install_instructions'] = 'To install missing plugins, copy them to the appropriate Moodle directory and visit the admin notifications page.';

// Navigation
$string['navigation_label'] = 'FTM Suite Status';
