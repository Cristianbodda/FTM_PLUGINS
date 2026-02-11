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

    if ($oldversion < 2026011501) {

        $table = new xmldb_table('local_student_sectors');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sector', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('is_primary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quiz');
        $table->add_field('quiz_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('first_detected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('last_detected', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table->add_index('userid_courseid_sector_idx', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'sector']);
        $table->add_index('userid_primary_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'is_primary']);
        $table->add_index('sector_idx', XMLDB_INDEX_NOTUNIQUE, ['sector']);
        $table->add_index('source_idx', XMLDB_INDEX_NOTUNIQUE, ['source']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate existing sectors from local_student_coaching
        if ($dbman->table_exists(new xmldb_table('local_student_coaching'))) {
            $existing = $DB->get_records_sql("
                SELECT id, userid, courseid, sector
                FROM {local_student_coaching}
                WHERE sector IS NOT NULL AND sector <> ''
            ");

            $now = time();
            foreach ($existing as $record) {
                $exists = $DB->record_exists('local_student_sectors', [
                    'userid' => $record->userid,
                    'courseid' => $record->courseid,
                    'sector' => $record->sector
                ]);

                if (!$exists) {
                    $newrecord = new stdClass();
                    $newrecord->userid = $record->userid;
                    $newrecord->courseid = $record->courseid;
                    $newrecord->sector = $record->sector;
                    $newrecord->is_primary = 1;
                    $newrecord->source = 'legacy';
                    $newrecord->quiz_count = 0;
                    $newrecord->timecreated = $now;
                    $newrecord->timemodified = $now;

                    $DB->insert_record('local_student_sectors', $newrecord);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026011501, 'local', 'competencymanager');
    }

    // Valutazione Formatore - 3 nuove tabelle
    if ($oldversion < 2026020901) {

        // Tabella 1: local_coach_evaluations (header valutazioni)
        $table = new xmldb_table('local_coach_evaluations');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sector', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('is_final_week', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('evaluation_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('student_can_view', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('studentid_fk', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table->add_key('coachid_fk', XMLDB_KEY_FOREIGN, ['coachid'], 'user', ['id']);

        $table->add_index('studentid_sector_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid', 'sector']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Tabella 2: local_coach_eval_ratings (voti per competenza)
        $table2 = new xmldb_table('local_coach_eval_ratings');

        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('evaluationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('rating', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table2->add_key('evaluationid_fk', XMLDB_KEY_FOREIGN, ['evaluationid'], 'local_coach_evaluations', ['id']);
        $table2->add_key('competencyid_fk', XMLDB_KEY_FOREIGN, ['competencyid'], 'competency', ['id']);

        $table2->add_index('evaluationid_competencyid_idx', XMLDB_INDEX_UNIQUE, ['evaluationid', 'competencyid']);

        if (!$dbman->table_exists($table2)) {
            $dbman->create_table($table2);
        }

        // Tabella 3: local_coach_eval_history (audit trail)
        $table3 = new xmldb_table('local_coach_eval_history');

        $table3->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table3->add_field('evaluationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('ratingid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table3->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('field_changed', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table3->add_field('old_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table3->add_field('new_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table3->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table3->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table3->add_key('evaluationid_fk', XMLDB_KEY_FOREIGN, ['evaluationid'], 'local_coach_evaluations', ['id']);
        $table3->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table3->add_index('evaluationid_time_idx', XMLDB_INDEX_NOTUNIQUE, ['evaluationid', 'timecreated']);

        if (!$dbman->table_exists($table3)) {
            $dbman->create_table($table3);
        }

        upgrade_plugin_savepoint(true, 2026020901, 'local', 'competencymanager');
    }

    // Versione 2026021001: Tabelle per valutazioni finali modificabili
    if ($oldversion < 2026021001) {

        // Tabella 1: Valutazioni finali modificate manualmente
        $table1 = new xmldb_table('local_compman_final_ratings');

        $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table1->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table1->add_field('sector', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('area_code', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('method', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('calculated_value', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table1->add_field('manual_value', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table1->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table1->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table1->add_key('studentid_fk', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table1->add_key('modifiedby_fk', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);

        $table1->add_index('student_sector_area_method_idx', XMLDB_INDEX_UNIQUE, ['studentid', 'courseid', 'sector', 'area_code', 'method']);

        if (!$dbman->table_exists($table1)) {
            $dbman->create_table($table1);
        }

        // Tabella 2: Storico modifiche
        $table2 = new xmldb_table('local_compman_final_history');

        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('ratingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('old_value', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table2->add_field('new_value', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table2->add_key('ratingid_fk', XMLDB_KEY_FOREIGN, ['ratingid'], 'local_compman_final_ratings', ['id']);
        $table2->add_key('modifiedby_fk', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);

        $table2->add_index('ratingid_time_idx', XMLDB_INDEX_NOTUNIQUE, ['ratingid', 'timecreated']);

        if (!$dbman->table_exists($table2)) {
            $dbman->create_table($table2);
        }

        upgrade_plugin_savepoint(true, 2026021001, 'local', 'competencymanager');
    }

    return true;
}
