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

    // Add coach observation fields to reports table.
    if ($oldversion < 2026012305) {
        $table = new xmldb_table('local_ftm_cpurc_reports');

        // Possibili settori e sintesi conclusiva.
        $field = new xmldb_field('possible_sectors', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sector_competency_text');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('final_summary', XMLDB_TYPE_TEXT, null, null, null, null, null, 'possible_sectors');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Osservazioni competenze trasversali.
        $field = new xmldb_field('obs_personal', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tic_competencies');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_social', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_personal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_methodological', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_social');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Osservazioni ricerca impiego.
        $field = new xmldb_field('obs_search_channels', XMLDB_TYPE_TEXT, null, null, null, null, null, 'search_evaluation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_search_evaluation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_search_channels');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026012305, 'local', 'ftm_cpurc');
    }

    return true;
}
