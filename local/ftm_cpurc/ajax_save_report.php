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
 * AJAX endpoint for saving report data.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:generatereport', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $studentid = required_param('studentid', PARAM_INT);
    $reportid = optional_param('reportid', 0, PARAM_INT);

    // Verify student exists.
    $student = \local_ftm_cpurc\cpurc_manager::get_student($studentid);
    if (!$student) {
        throw new \Exception('Student not found');
    }

    // Build report data.
    $data = new \stdClass();
    $data->studentid = $studentid;

    if ($reportid > 0) {
        $data->id = $reportid;
    }

    // Section 1: Situazione iniziale.
    $data->initial_situation = optional_param('initial_situation', '', PARAM_TEXT);
    $data->initial_situation_sector = optional_param('initial_situation_sector', '', PARAM_TEXT);

    // Section 2: Reinsertion assessment + Valutazione competenze settore.
    $data->reinsertion_assessment = optional_param('reinsertion_assessment', '', PARAM_ALPHANUMEXT);
    $data->sector_competency_rating = optional_param('sector_competency_rating', null, PARAM_INT);
    $data->sector_competency_text = optional_param('sector_competency_text', '', PARAM_TEXT);
    $data->possible_sectors = optional_param('possible_sectors', '', PARAM_TEXT);
    $data->final_summary = optional_param('final_summary', '', PARAM_TEXT);

    // Section 3: Competenze trasversali (JSON with text scale values).
    $data->personal_competencies = optional_param('personal_competencies', '[]', PARAM_RAW);
    $data->social_competencies = optional_param('social_competencies', '[]', PARAM_RAW);
    $data->methodological_competencies = optional_param('methodological_competencies', '[]', PARAM_RAW);
    $data->tic_competencies = optional_param('tic_competencies', '[]', PARAM_RAW);

    // Section 3b: Observation fields for transversal competencies.
    $data->obs_personal = optional_param('obs_personal', '', PARAM_TEXT);
    $data->obs_social = optional_param('obs_social', '', PARAM_TEXT);
    $data->obs_methodological = optional_param('obs_methodological', '', PARAM_TEXT);
    $data->obs_tic = optional_param('obs_tic', '', PARAM_TEXT);

    // Section 4: Ricerca impiego.
    $data->dossier_complete = optional_param('dossier_complete', 0, PARAM_INT);
    $data->search_competencies = optional_param('search_competencies', '[]', PARAM_RAW);
    $data->search_channels = optional_param('search_channels', '[]', PARAM_RAW);
    $data->search_overall = optional_param('search_overall', '', PARAM_TEXT);

    // Section 4b: Observation fields for job search.
    $data->obs_search_channels = optional_param('obs_search_channels', '', PARAM_TEXT);
    $data->obs_search_evaluation = optional_param('obs_search_evaluation', '', PARAM_TEXT);

    // Section 5: Colloqui.
    $data->interviews_count = optional_param('interviews_count', 0, PARAM_INT);
    $data->interviews_employers = optional_param('interviews_employers', '', PARAM_TEXT);
    $data->obs_interviews = optional_param('obs_interviews', '', PARAM_TEXT);

    // Section 6: Esito.
    $data->hired = optional_param('hired', 0, PARAM_INT);
    $data->hired_details = optional_param('hired_details', '', PARAM_TEXT);
    $data->hired_profession = optional_param('hired_profession', '', PARAM_TEXT);
    $data->hired_contract = optional_param('hired_contract', '', PARAM_TEXT);

    // SIP consent + Allegati.
    $data->sip_consent = optional_param('sip_consent', null, PARAM_INT);
    $data->allegati = optional_param('allegati', '', PARAM_TEXT);

    // Save report.
    $newid = \local_ftm_cpurc\cpurc_manager::save_report($data);

    echo json_encode([
        'success' => true,
        'message' => get_string('report_saved', 'local_ftm_cpurc'),
        'reportid' => $newid,
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
