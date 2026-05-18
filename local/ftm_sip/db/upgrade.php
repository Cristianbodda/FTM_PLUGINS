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

    // ===========================================================
    // CI v2.0 — Ristrutturazione 12 aree + accettazione + tracking settimanale
    // ===========================================================
    if ($oldversion < 2026042100) {

        // -------------------------------------------------------
        // 1. New table: local_ftm_sip_acceptance
        // Form "Accettazione e partenza" — 12 obiettivi con accettazione, baseline, target.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_acceptance');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accepted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('baseline_value', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('target_value', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('actual_value', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
        $table->add_index('enrollment_area_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'area_key']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // -------------------------------------------------------
        // 2. New table: local_ftm_sip_search_entries
        // Registrazione dettagliata per area/settimana (modulo URC digitale).
        // Ogni riga = un contatto/candidatura/azione dello studente.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_search_entries');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('entry_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        // Azienda (testo + FK opzionale al registro condiviso).
        $table->add_field('company_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('company_address', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('company_email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('company_phone', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('contact_person', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        // Ruolo cercato.
        $table->add_field('position', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        // Modulo URC: assegnato dall'URC, occupazione, metodo candidatura, risultato.
        $table->add_field('urc_assigned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('occupation_fulltime', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('occupation_parttime', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('method_letter', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('method_person', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('method_phone', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('result', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('result_reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        // Canale usato (per aree come canali ricerca, social, rete personale).
        $table->add_field('channel', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        // Note e metadata.
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('addedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
        $table->add_key('companyid_fk', XMLDB_KEY_FOREIGN, ['companyid'], 'local_ftm_sip_companies', ['id']);
        $table->add_key('addedby_fk', XMLDB_KEY_FOREIGN, ['addedby'], 'user', ['id']);

        $table->add_index('enrollment_area_week_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'area_key', 'sip_week']);
        $table->add_index('entry_date_idx', XMLDB_INDEX_NOTUNIQUE, ['entry_date']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // -------------------------------------------------------
        // 3. New table: local_ftm_sip_coach_evals
        // Valutazione coach settimanale (1-10) per Strategia e Autonomia.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_coach_evals');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('coachid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
        $table->add_key('coachid_fk', XMLDB_KEY_FOREIGN, ['coachid'], 'user', ['id']);
        $table->add_index('enrollment_area_week_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'area_key', 'sip_week']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // -------------------------------------------------------
        // 4. New table: local_ftm_sip_search_proofs
        // Upload PDF fogli ricerche Job-Room ufficiali.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_search_proofs');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('month_year', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('uploadedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
        $table->add_key('uploadedby_fk', XMLDB_KEY_FOREIGN, ['uploadedby'], 'user', ['id']);
        $table->add_index('enrollment_month_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'month_year']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // -------------------------------------------------------
        // 5. Add new fields to local_ftm_sip_action_plan for 12-area system.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_action_plan');

        // week_start: settimana in cui l'area si attiva (1-10).
        $field = new xmldb_field('week_start', XMLDB_TYPE_INTEGER, '2', null, null, null, '1', 'area_key');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // week_end: ultima settimana attiva (1-10).
        $field = new xmldb_field('week_end', XMLDB_TYPE_INTEGER, '2', null, null, null, '10', 'week_start');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // area_type: quantitative (conteggio contatti) o qualitative (valutazione coach 1-10).
        $field = new xmldb_field('area_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quantitative', 'week_end');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // target_global: obiettivo numerico globale sulle 10 settimane.
        $field = new xmldb_field('target_global', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'area_type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // -------------------------------------------------------
        // 6. Enrich local_ftm_sip_companies with additional fields.
        // -------------------------------------------------------
        $table = new xmldb_table('local_ftm_sip_companies');

        // canton — Cantone (TI, ZH, etc.).
        $field = new xmldb_field('canton', XMLDB_TYPE_CHAR, '5', null, null, null, null, 'city');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // last_contact_date — Data ultimo contatto da qualsiasi studente.
        $field = new xmldb_field('last_contact_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'interaction_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2026042100, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026042403) {
        // Aggiunge 3 campi draft a local_ftm_sip_eligibility per persistere
        // anche i dati di attivazione (motivazione/LADI/data) prima dell'attivazione vera.
        $table = new xmldb_table('local_ftm_sip_eligibility');

        $field = new xmldb_field('draft_motivation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'approved_date');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('draft_ladi_indemnity', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'draft_motivation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('draft_date_start', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'draft_ladi_indemnity');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026042403, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026042408) {
        // Aggiunge 2 campi a local_ftm_sip_acceptance per separare valore per-settimana
        // dal valore totale 10 settimane (decisi indipendentemente dal coach).
        $table = new xmldb_table('local_ftm_sip_acceptance');

        $field = new xmldb_field('baseline_per_week', XMLDB_TYPE_NUMBER, '8, 2', null, null, null, '0', 'baseline_value');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('target_per_week', XMLDB_TYPE_NUMBER, '8, 2', null, null, null, '0', 'target_value');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026042408, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026050401) {
        // New table: local_ftm_sip_channel_usage
        // Tracks which search channels have been activated per enrollment.
        // One activation per channel per enrollment (unique index on enrollmentid+channel_key).
        $table = new xmldb_table('local_ftm_sip_channel_usage');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('channel_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('activated_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('activatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
        $table->add_key('activatedby_fk', XMLDB_KEY_FOREIGN, ['activatedby'], 'user', ['id']);

        $table->add_index('enrollment_channel_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'channel_key']);
        $table->add_index('enrollmentid_week_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'sip_week']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050401, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026060101) {
        // Safety re-check: create local_ftm_sip_channel_usage if it was missed
        // because the server DB version skipped step 2026050401.
        $table = new xmldb_table('local_ftm_sip_channel_usage');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('channel_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activated_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('activatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
            $table->add_key('activatedby_fk', XMLDB_KEY_FOREIGN, ['activatedby'], 'user', ['id']);

            $table->add_index('enrollment_channel_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'channel_key']);
            $table->add_index('enrollmentid_week_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'sip_week']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060101, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026060200) {
        // Ensure local_ftm_sip_channel_usage exists regardless of which upgrade path was used.
        $table = new xmldb_table('local_ftm_sip_channel_usage');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('channel_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activated_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('activatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);
            $table->add_key('activatedby_fk', XMLDB_KEY_FOREIGN, ['activatedby'], 'user', ['id']);

            $table->add_index('enrollment_channel_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'channel_key']);
            $table->add_index('enrollmentid_week_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'sip_week']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060200, 'local', 'ftm_sip');
    }

    if ($oldversion < 2026060300) {
        // New table: local_ftm_sip_channel_assess — channel assessment per enrollment.
        $table = new xmldb_table('local_ftm_sip_channel_assess');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('enrollmentid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('channel_key',   XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('level_initial', XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('level_target',  XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('level_final',   XMLDB_TYPE_INTEGER, '2',  null, null,          null, null);
            $table->add_field('actions_text',  XMLDB_TYPE_TEXT,    null, null, null,          null, null);
            $table->add_field('createdby',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('modifiedby',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('enrollmentid_fk', XMLDB_KEY_FOREIGN, ['enrollmentid'], 'local_ftm_sip_enrollments', ['id']);

            $table->add_index('enrollment_channel_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'channel_key']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026060300, 'local', 'ftm_sip');
    }

    return true;
}
