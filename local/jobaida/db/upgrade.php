<?php
/**
 * Upgrade script for JobAIDA plugin.
 *
 * @package    local_jobaida
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jobaida_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041001) {

        $table = new xmldb_table('local_jobaida_interviews');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('job_ad', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('cv_text', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('language', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, 'it');
            $table->add_field('question_count', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('system_prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('conversation', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('evaluation', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('userid_status_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041001, 'local', 'jobaida');
    }

    if ($oldversion < 2026052201) {

        $table = new xmldb_table('local_jobaida_letters');

        $field_is = new xmldb_field('interest_svizzero', XMLDB_TYPE_TEXT, null, null, null, null, null, 'interest_rationale');
        if (!$dbman->field_exists($table, $field_is)) {
            $dbman->add_field($table, $field_is);
        }

        $field_fls = new xmldb_field('full_letter_svizzero', XMLDB_TYPE_TEXT, null, null, null, null, null, 'full_letter');
        if (!$dbman->field_exists($table, $field_fls)) {
            $dbman->add_field($table, $field_fls);
        }

        upgrade_plugin_savepoint(true, 2026052201, 'local', 'jobaida');
    }

    return true;
}
