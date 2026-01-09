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
 * Post-installation hook for local_ftm_suite
 *
 * This hook runs after the plugin is installed. It verifies that all
 * FTM plugins are properly installed and logs the installation.
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation procedure
 *
 * @return bool True on success
 */
function xmldb_local_ftm_suite_install() {
    global $CFG;

    // Log installation.
    mtrace('FTM Suite Installer: Installation complete.');
    mtrace('FTM Suite Installer: Visit /local/ftm_suite/ to view plugin status.');

    // All actual dependency validation is handled by Moodle's plugin manager
    // based on the dependencies declared in version.php.
    // If this hook runs, all dependencies have been satisfied.

    return true;
}
