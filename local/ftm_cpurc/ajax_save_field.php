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
 * AJAX endpoint for saving a single student field.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $studentid = required_param('studentid', PARAM_INT);
    $field = required_param('field', PARAM_ALPHANUMEXT);
    $value = required_param('value', PARAM_TEXT);
    $type = required_param('type', PARAM_ALPHA);

    // Whitelist of allowed fields (security).
    $allowed_fields = [
        // Anagrafica.
        'gender', 'address_street', 'address_cap', 'address_city',
        'birthdate', 'nationality', 'permit', 'avs_number', 'iban',
        'civil_status', 'phone', 'mobile',
        // Percorso.
        'personal_number', 'measure', 'trainer', 'signal_date',
        'date_start', 'date_end_planned', 'date_end_actual',
        'occupation_grade', 'urc_office', 'urc_consultant', 'status',
        'exit_reason', 'last_profession', 'priority', 'financier',
        'unemployment_fund', 'framework_start', 'framework_end',
        'framework_allowance', 'framework_art59d', 'company', 'observations',
        // Assenze.
        'absence_x', 'absence_o', 'absence_a', 'absence_b', 'absence_c',
        'absence_d', 'absence_e', 'absence_f', 'absence_g', 'absence_h',
        'absence_i', 'absence_total', 'interviews', 'stages_count', 'stage_days',
        // Stage.
        'stage_start', 'stage_end', 'stage_company_name', 'stage_company_cap',
        'stage_company_city', 'stage_company_street', 'stage_contact_name',
        'stage_contact_phone', 'stage_contact_email', 'stage_percentage',
        'stage_function', 'conclusion_date', 'conclusion_type', 'conclusion_reason',
    ];

    if (!in_array($field, $allowed_fields)) {
        throw new Exception('Campo non modificabile: ' . $field);
    }

    // Validate and convert by type.
    $display_value = $value;
    if ($type === 'date') {
        if (!empty($value)) {
            $ts = strtotime($value);
            if ($ts === false) {
                throw new Exception('Data non valida');
            }
            $display_value = date('d.m.Y', $ts);
            $value = $ts;
        } else {
            $value = null;
            $display_value = '-';
        }
    } else if ($type === 'int') {
        $value = $value !== '' ? (int)$value : 0;
        $display_value = (string)$value;
        // Add % suffix for percentage fields.
        if (in_array($field, ['occupation_grade', 'stage_percentage']) && $value > 0) {
            $display_value = $value . '%';
        }
    } else {
        $value = clean_param($value, PARAM_TEXT);
        $display_value = $value ?: '-';
    }

    // Load record and update single field.
    $student = $DB->get_record('local_ftm_cpurc_students', ['id' => $studentid], '*', MUST_EXIST);
    $student->{$field} = $value;
    $student->timemodified = time();
    $DB->update_record('local_ftm_cpurc_students', $student);

    echo json_encode(['success' => true, 'display_value' => $display_value, 'field' => $field]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
