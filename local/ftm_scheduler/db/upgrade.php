<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_ftm_scheduler_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026012201) {
        $table = new xmldb_table('local_ftm_enrollments');

        $field = new xmldb_field('marked_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reminder_sent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('marked_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'marked_by');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('absence_notified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'marked_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026012201, 'local', 'ftm_scheduler');
    }

    if ($oldversion < 2026012202) {
        upgrade_plugin_savepoint(true, 2026012202, 'local', 'ftm_scheduler');
    }

    // Add student individual program tables
    if ($oldversion < 2026020601) {

        // Table: local_ftm_student_program
        $table = new xmldb_table('local_ftm_student_program');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('week_number', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day_of_week', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('time_slot', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('activity_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'presenza');
        $table->add_field('activity_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('activity_details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('location', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('is_editable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('assigned_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'local_ftm_groups', ['id']);

        $table->add_index('userid_groupid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'groupid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: local_ftm_student_tests
        $table = new xmldb_table('local_ftm_student_tests');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('test_code', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('test_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('test_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sector', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('assigned_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('completed_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('score', XMLDB_TYPE_NUMBER, '5', '2', null, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'local_ftm_groups', ['id']);

        $table->add_index('userid_groupid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'groupid']);
        $table->add_index('userid_test', XMLDB_INDEX_UNIQUE, ['userid', 'groupid', 'test_code']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026020601, 'local', 'ftm_scheduler');
    }

    return true;
}
