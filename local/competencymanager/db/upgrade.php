<?php
/**
 * Upgrade script for Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_competencymanager_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2025122802) {
        
        $table = new xmldb_table('local_student_coaching');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sector', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('area', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('date_start', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('date_end', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('date_extended', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('current_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('coachid_fk', XMLDB_KEY_FOREIGN, ['coachid'], 'user', ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        
        $table->add_index('userid_courseid_idx', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('sector_idx', XMLDB_INDEX_NOTUNIQUE, ['sector']);
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025122802, 'local', 'competencymanager');
    }
    
    return true;
}
