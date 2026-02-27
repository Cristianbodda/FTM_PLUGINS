<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for qbank_competenciesbyquestion plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_qbank_competenciesbyquestion_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade to version 2025120601 - Add difficultylevel field
    if ($oldversion < 2025120601) {

        $table = new xmldb_table('qbank_competenciesbyquestion');

        // If table doesn't exist (broken previous install), create it from scratch.
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('difficultylevel', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $table->add_key('competencyid', XMLDB_KEY_FOREIGN, ['competencyid'], 'competency', ['id']);
            $dbman->create_table($table);
        } else {
            // Table exists, just add the new field if missing.
            $field = new xmldb_field('difficultylevel', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'competencyid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Update version timestamp
        upgrade_plugin_savepoint(true, 2025120601, 'qbank', 'competenciesbyquestion');
    }

    return true;
}
