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
 * Database upgrade steps for local_ftm_cpurc.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_ftm_cpurc.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Success.
 */
function xmldb_local_ftm_cpurc_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Initial version - tables created via install.xml.
    if ($oldversion < 2026011601) {
        // No upgrade steps needed for initial version.
        upgrade_plugin_savepoint(true, 2026011601, 'local', 'ftm_cpurc');
    }

    // Add coach observation fields to reports table.
    if ($oldversion < 2026012305) {
        $table = new xmldb_table('local_ftm_cpurc_reports');

        // Possibili settori e sintesi conclusiva.
        $field = new xmldb_field('possible_sectors', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sector_competency_text');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('final_summary', XMLDB_TYPE_TEXT, null, null, null, null, null, 'possible_sectors');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Osservazioni competenze trasversali.
        $field = new xmldb_field('obs_personal', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tic_competencies');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_social', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_personal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_methodological', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_social');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Osservazioni ricerca impiego.
        $field = new xmldb_field('obs_search_channels', XMLDB_TYPE_TEXT, null, null, null, null, null, 'search_evaluation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_search_evaluation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_search_channels');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026012305, 'local', 'ftm_cpurc');
    }

    // Add new report fields for official document structure (Rapporto finale d'attivita').
    if ($oldversion < 2026032001) {
        $table = new xmldb_table('local_ftm_cpurc_reports');

        // Section 1: Settore di riferimento.
        $field = new xmldb_field('initial_situation_sector', XMLDB_TYPE_TEXT, null, null, null, null, null, 'initial_situation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Section 3: Osservazioni TIC.
        $field = new xmldb_field('obs_tic', XMLDB_TYPE_TEXT, null, null, null, null, null, 'obs_methodological');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Section 4: Competenze ricerca impiego (JSON).
        $field = new xmldb_field('search_competencies', XMLDB_TYPE_TEXT, null, null, null, null, null, 'dossier_complete');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Section 4.3: Valutazione complessiva ricerca (text scale value).
        $field = new xmldb_field('search_overall', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'search_channels');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Section 5: Colloqui.
        $field = new xmldb_field('interviews_count', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'obs_search_evaluation');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('interviews_employers', XMLDB_TYPE_TEXT, null, null, null, null, null, 'interviews_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('obs_interviews', XMLDB_TYPE_TEXT, null, null, null, null, null, 'interviews_employers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Section 6: Hired details (replaces hired_company/hired_date/hired_percentage with single textarea).
        $field = new xmldb_field('hired_details', XMLDB_TYPE_TEXT, null, null, null, null, null, 'hired');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026032001, 'local', 'ftm_cpurc');
    }

    if ($oldversion < 2026032301) {
        $table = new xmldb_table('local_ftm_cpurc_reports');

        // SIP consent (0/1/null).
        $field = new xmldb_field('sip_consent', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'hired_details');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Allegati field.
        $field = new xmldb_field('allegati', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sip_consent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026032301, 'local', 'ftm_cpurc');
    }

    if ($oldversion < 2026032501) {
        $table = new xmldb_table('local_ftm_cpurc_reports');

        // Reinsertion assessment (breve_termine/medio_termine/no_reinserimento).
        $field = new xmldb_field('reinsertion_assessment', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'initial_situation_sector');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hired structured fields (Section 6).
        $field = new xmldb_field('hired_profession', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'hired_details');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('hired_contract', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'hired_profession');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026032501, 'local', 'ftm_cpurc');
    }

    if ($oldversion < 2026032502) {
        $table = new xmldb_table('local_ftm_cpurc_students');

        // Convert absence fields from INT to NUMBER(5,1) for half-day support.
        $absenceFields = [
            'absence_x', 'absence_o',
            'absence_a', 'absence_b', 'absence_c', 'absence_d', 'absence_e',
            'absence_f', 'absence_g', 'absence_h', 'absence_i', 'absence_total',
        ];

        foreach ($absenceFields as $fname) {
            $field = new xmldb_field($fname, XMLDB_TYPE_NUMBER, '5, 1', null, XMLDB_NOTNULL, null, '0');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            }
        }

        // Also add hired_profession and hired_contract if missing from previous failed upgrade.
        $reportstable = new xmldb_table('local_ftm_cpurc_reports');

        $field = new xmldb_field('hired_profession', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'hired_details');
        if (!$dbman->field_exists($reportstable, $field)) {
            $dbman->add_field($reportstable, $field);
        }

        $field = new xmldb_field('hired_contract', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'hired_profession');
        if (!$dbman->field_exists($reportstable, $field)) {
            $dbman->add_field($reportstable, $field);
        }

        upgrade_plugin_savepoint(true, 2026032502, 'local', 'ftm_cpurc');
    }

    return true;
}
