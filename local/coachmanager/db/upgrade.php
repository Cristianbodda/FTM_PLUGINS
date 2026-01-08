<?php
// ============================================
// CoachManager - Database Upgrade
// ============================================
// File: db/upgrade.php
// Gestisce gli aggiornamenti del database
// ============================================

defined('MOODLE_INTERNAL') || die();

function xmldb_local_coachmanager_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    // Upgrade alla versione 2025122301 - Tabella note coach
    if ($oldversion < 2025122301) {
        
        // Tabella note coach
        $table = new xmldb_table('local_coachmanager_notes');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('studentid_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
            $table->add_index('coachid_idx', XMLDB_INDEX_NOTUNIQUE, ['coachid']);
            $table->add_index('student_coach_idx', XMLDB_INDEX_UNIQUE, ['studentid', 'coachid']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025122301, 'local', 'coachmanager');
    }
    
    // Upgrade alla versione 2025122302 - Tabella confronti
    if ($oldversion < 2025122302) {
        
        $table = new xmldb_table('local_coachmanager_compare');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('student1id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('student2id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('coachid_idx', XMLDB_INDEX_NOTUNIQUE, ['coachid']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025122302, 'local', 'coachmanager');
    }
    
    // Upgrade alla versione 2025122303 - Tabelle matching lavoro
    if ($oldversion < 2025122303) {
        
        // Tabella annunci lavoro
        $table = new xmldb_table('local_coachmanager_jobs');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('company', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('location', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('competencies_required', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            
            $dbman->create_table($table);
        }
        
        // Tabella risultati matching
        $table2 = new xmldb_table('local_coachmanager_matches');
        
        if (!$dbman->table_exists($table2)) {
            $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table2->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table2->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table2->add_field('matchscore', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, null);
            $table2->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table2->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table2->add_index('jobid_idx', XMLDB_INDEX_NOTUNIQUE, ['jobid']);
            $table2->add_index('studentid_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
            $table2->add_index('matchscore_idx', XMLDB_INDEX_NOTUNIQUE, ['matchscore']);
            
            $dbman->create_table($table2);
        }
        
        upgrade_plugin_savepoint(true, 2025122303, 'local', 'coachmanager');
    }
    
    return true;
}
