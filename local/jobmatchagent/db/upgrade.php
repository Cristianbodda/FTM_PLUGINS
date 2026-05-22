<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jobmatchagent_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042301) {
        // Add manual_cv_text field to student_filters.
        $table = new xmldb_table('local_jobmatch_student_filters');
        $field = new xmldb_field('manual_cv_text', XMLDB_TYPE_TEXT, null, null, null, null, null, 'extra_notes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026042301, 'local', 'jobmatchagent');
    }

    if ($oldversion < 2026052001) {

        // --- Table: local_jobmatch_ticino_companies ---
        $table = new xmldb_table('local_jobmatch_ticino_companies');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('nome', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('indirizzo', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('cap', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('localita', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('anno_primo_contatto', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
            $table->add_field('settore_ftm', XMLDB_TYPE_CHAR, '30', null, null, null, 'ALTRO');
            $table->add_field('settore_raw', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('dimensione', XMLDB_TYPE_CHAR, '10', null, null, null, 'unknown');
            $table->add_field('website', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('referente', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('note_interne', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'manual');
            $table->add_field('status', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, 'unverified');
            $table->add_field('last_job_board_seen', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('settore_ftm_idx', XMLDB_INDEX_NOTUNIQUE, ['settore_ftm']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('localita_idx', XMLDB_INDEX_NOTUNIQUE, ['localita']);

            $dbman->create_table($table);
        }

        // --- Table: local_jobmatch_student_targets ---
        $table = new xmldb_table('local_jobmatch_student_targets');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('company_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coach_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note_per_ai', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('jobaida_letter_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('data_invio', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('data_risposta', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('note_esito', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sip_entry_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('company_idx', XMLDB_INDEX_NOTUNIQUE, ['company_id']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('userid_company_uq', XMLDB_INDEX_UNIQUE, ['userid', 'company_id']);

            $dbman->create_table($table);
        }

        // --- Add columns to local_jobmatch_student_filters ---
        $filtertable = new xmldb_table('local_jobmatch_student_filters');

        $field = new xmldb_field('student_view_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'manual_cv_text');
        if (!$dbman->field_exists($filtertable, $field)) {
            $dbman->add_field($filtertable, $field);
        }

        $field = new xmldb_field('activated_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'student_view_enabled');
        if (!$dbman->field_exists($filtertable, $field)) {
            $dbman->add_field($filtertable, $field);
        }

        $field = new xmldb_field('activated_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activated_by');
        if (!$dbman->field_exists($filtertable, $field)) {
            $dbman->add_field($filtertable, $field);
        }

        upgrade_plugin_savepoint(true, 2026052001, 'local', 'jobmatchagent');
    }

    if ($oldversion < 2026052002) {

        // Create local_jobmatch_ticino_companies if missing (idempotent).
        $table = new xmldb_table('local_jobmatch_ticino_companies');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('nome', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('indirizzo', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('cap', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('localita', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('anno_primo_contatto', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
            $table->add_field('settore_ftm', XMLDB_TYPE_CHAR, '30', null, null, null, 'ALTRO');
            $table->add_field('settore_raw', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('dimensione', XMLDB_TYPE_CHAR, '10', null, null, null, 'unknown');
            $table->add_field('website', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('referente', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('note_interne', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'manual');
            $table->add_field('status', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, 'unverified');
            $table->add_field('last_job_board_seen', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('settore_ftm_idx', XMLDB_INDEX_NOTUNIQUE, ['settore_ftm']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('localita_idx', XMLDB_INDEX_NOTUNIQUE, ['localita']);
            $dbman->create_table($table);
        }

        // Create local_jobmatch_student_targets if missing (idempotent).
        $table = new xmldb_table('local_jobmatch_student_targets');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('company_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coach_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note_per_ai', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('jobaida_letter_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('data_invio', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('data_risposta', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('note_esito', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sip_entry_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('company_idx', XMLDB_INDEX_NOTUNIQUE, ['company_id']);
            $table->add_index('status_idx2', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('userid_company_uq', XMLDB_INDEX_UNIQUE, ['userid', 'company_id']);
            $dbman->create_table($table);
        }

        // Add student_view columns to local_jobmatch_student_filters if missing.
        $filtertable = new xmldb_table('local_jobmatch_student_filters');
        if ($dbman->table_exists($filtertable)) {
            $field = new xmldb_field('student_view_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
            $field = new xmldb_field('activated_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
            $field = new xmldb_field('activated_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026052002, 'local', 'jobmatchagent');
    }

    if ($oldversion < 2026052003) {
        // Ensure student_view_enabled, activated_by, activated_at exist on student_filters.
        $filtertable = new xmldb_table('local_jobmatch_student_filters');
        if ($dbman->table_exists($filtertable)) {
            $field = new xmldb_field('student_view_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
            $field = new xmldb_field('activated_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
            $field = new xmldb_field('activated_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($filtertable, $field)) {
                $dbman->add_field($filtertable, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026052003, 'local', 'jobmatchagent');
    }

    if ($oldversion < 2026052201) {
        // Add descrizione_ai and telefono to local_jobmatch_ticino_companies.
        $table = new xmldb_table('local_jobmatch_ticino_companies');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('descrizione_ai', XMLDB_TYPE_TEXT, null, null, null, null, null, 'referente');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $field = new xmldb_field('telefono', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'email');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026052201, 'local', 'jobmatchagent');
    }

    return true;
}
