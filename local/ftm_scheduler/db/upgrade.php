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
 * FTM Scheduler upgrade script.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_ftm_scheduler.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_ftm_scheduler_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade to add attendance tracking fields.
    if ($oldversion < 2026012201) {

        // Define new fields for local_ftm_enrollments table.
        $table = new xmldb_table('local_ftm_enrollments');

        // Field marked_by - who marked the attendance.
        $field = new xmldb_field('marked_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reminder_sent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Field marked_at - when attendance was marked.
        $field = new xmldb_field('marked_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'marked_by');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Field absence_notified - if absence notification was sent.
        $field = new xmldb_field('absence_notified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'marked_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update max_participants default in atelier_catalog to 16.
        $table = new xmldb_table('local_ftm_atelier_catalog');
        $field = new xmldb_field('max_participants', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '16');
        $dbman->change_field_default($table, $field);

        // Update existing atelier records with max_participants = 10 to 16.
        $DB->execute("UPDATE {local_ftm_atelier_catalog} SET max_participants = 16 WHERE max_participants = 10");

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2026012201, 'local', 'ftm_scheduler');
    }

    return true;
}
