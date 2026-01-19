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
 * Database upgrade steps for local_ftm_cpurc.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_ftm_cpurc.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Success.
 */
function xmldb_local_ftm_cpurc_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Initial version - tables created via install.xml.
    if ($oldversion < 2026011601) {
        // No upgrade steps needed for initial version.
        upgrade_plugin_savepoint(true, 2026011601, 'local', 'ftm_cpurc');
    }

    return true;
}
