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
 * Database upgrade steps for local_ftm_sip.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_ftm_sip_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026032001) {

        // -------------------------------------------------------
        // 1. Create new table: local_ftm_sip_eligibility.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_eligibility');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sector_placeable', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'yes');
        $table->add_field('motivation_level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'high');
        $table->add_field('insertion_potential', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'high');
        $table->add_field('favorable_factors', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('critical_factors', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('coach_recommendation', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'activate');
        $table->add_field('referral_detail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('approved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('approvedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('approved_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('assessedby_fk', XMLDB_KEY_FOREIGN, ['assessedby'], 'user', ['id']);

        $table->add_index('userid_uq', XMLDB_INDEX_UNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // -------------------------------------------------------
        // 2. Add new fields to local_ftm_sip_enrollments.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_enrollments');

        // outcome_percentage.
        $field = new xmldb_field('outcome_percentage', XMLDB_TYPE_INTEGER, '3', null, null, null, null, 'outcome_notes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // interruption_reason.
        $field = new xmldb_field('interruption_reason', XMLDB_TYPE_TEXT, null, null, null, null, null, 'outcome_percentage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // referral_measure.
        $field = new xmldb_field('referral_measure', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'interruption_reason');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // coach_final_evaluation.
        $field = new xmldb_field('coach_final_evaluation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'referral_measure');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // next_steps.
        $field = new xmldb_field('next_steps', XMLDB_TYPE_TEXT, null, null, null, null, null, 'coach_final_evaluation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // closure_validated.
        $field = new xmldb_field('closure_validated', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'next_steps');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // eligibility_id.
        $field = new xmldb_field('eligibility_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'closure_validated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add foreign key for eligibility_id.
        $key = new xmldb_key('eligibility_id_fk', XMLDB_KEY_FOREIGN, ['eligibility_id'], 'local_ftm_sip_eligibility', ['id']);
        $dbman->add_key($table, $key);

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2026032001, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026032002) {

        // -------------------------------------------------------
        // Migrate local_ftm_sip_eligibility to Griglia Valutazione PCI schema.
        // Drop old text/char fields, add 6 numeric criteria + totale + decisione + note.
        // Keep: coach_recommendation, referral_detail, approved, approvedby, approved_date, timecreated, timemodified.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_eligibility');

        // --- Drop old fields ---

        $field = new xmldb_field('sector_placeable');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('motivation_level');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('insertion_potential');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('favorable_factors');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('critical_factors');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Drop old 'notes' field (will be replaced by 'note').
        $field = new xmldb_field('notes');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // --- Add new fields ---

        // motivazione (1-5).
        $field = new xmldb_field('motivazione', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'assessedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // chiarezza_obiettivo (1-5).
        $field = new xmldb_field('chiarezza_obiettivo', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'motivazione');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // occupabilita (1-5).
        $field = new xmldb_field('occupabilita', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'chiarezza_obiettivo');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // autonomia (1-5).
        $field = new xmldb_field('autonomia', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'occupabilita');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // bisogno_coaching (1-5).
        $field = new xmldb_field('bisogno_coaching', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'autonomia');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // comportamento (1-5).
        $field = new xmldb_field('comportamento', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'bisogno_coaching');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // totale (6-30).
        $field = new xmldb_field('totale', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'comportamento');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // decisione (idoneo, non_idoneo, pending).
        $field = new xmldb_field('decisione', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending', 'totale');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Change coach_recommendation to nullable (advisory only).
        $field = new xmldb_field('coach_recommendation', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        // note (free text, replaces old 'notes').
        $field = new xmldb_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null, 'referral_detail');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2026032002, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026032601) {

        // -------------------------------------------------------
        // Add ladi_indemnity field to local_ftm_sip_enrollments.
        // LADI daily indemnities remaining at activation.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_enrollments');
        $field = new xmldb_field('ladi_indemnity', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'motivation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2026032601, 'local', 'ftm_sip');
    }

    return true;
}
