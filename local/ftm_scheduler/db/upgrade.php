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

    return true;
}
