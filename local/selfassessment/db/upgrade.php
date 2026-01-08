<?php
// ============================================
// Self Assessment - Database Upgrade
// ============================================

defined('MOODLE_INTERNAL') || die();

function xmldb_local_selfassessment_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    // Versione 2: Aggiunge tabella assegnazioni
    if ($oldversion < 2025122402) {
        
        // Definisci tabella local_selfassessment_assign
        $table = new xmldb_table('local_selfassessment_assign');
        
        // Aggiungi campi
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quiz');
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        // Aggiungi chiavi
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('competencyid_fk', XMLDB_KEY_FOREIGN, ['competencyid'], 'competency', ['id']);
        
        // Aggiungi indici
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('user_competency_idx', XMLDB_INDEX_UNIQUE, ['userid', 'competencyid']);
        $table->add_index('source_idx', XMLDB_INDEX_NOTUNIQUE, ['source']);
        
        // Crea tabella se non esiste
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025122402, 'local', 'selfassessment');
    }
    
    return true;
}
