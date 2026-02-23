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
 * AJAX endpoint for CSV import.
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
require_capability('local/ftm_cpurc:import', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    // Check for uploaded file.
    if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('No file uploaded or upload error');
    }

    $tmpfile = $_FILES['csvfile']['tmp_name'];
    $filename = $_FILES['csvfile']['name'];

    // Initialize importer.
    $importer = new \local_ftm_cpurc\csv_importer();

    if ($action === 'preview') {
        // Preview mode - just parse and return first 5 rows.
        $rows = $importer->preview_file($tmpfile, 5, $filename);

        // Add detected sector to each row for preview.
        foreach ($rows as &$row) {
            $row['sector_detected'] = \local_ftm_cpurc\profession_mapper::detect_sector($row['last_profession'] ?? '');
        }

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'message' => count($rows) . ' rows found',
        ]);
    } else if ($action === 'import') {
        // Full import.
        $courseid = optional_param('courseid', 0, PARAM_INT);
        $updateexisting = optional_param('update_existing', 0, PARAM_INT);
        $enrolcourse = optional_param('enrol_course', 0, PARAM_INT);
        $assigncohort = optional_param('assign_cohort', 0, PARAM_INT);
        $assigngroup = optional_param('assign_group', 0, PARAM_INT);

        $options = [
            'courseid' => $courseid,
            'update_existing' => (bool)$updateexisting,
            'enrol_course' => (bool)$enrolcourse,
            'assign_cohort' => (bool)$assigncohort,
            'assign_group' => (bool)$assigngroup,
        ];

        // Parse file.
        $rows = $importer->parse_file($tmpfile, $filename);

        if (empty($rows)) {
            throw new \Exception('No valid rows found in file');
        }

        // Import each row.
        $credentials = [];
        foreach ($rows as $row) {
            $result = $importer->import_row($row, $options);

            // Collect credentials for newly created users.
            if ($result['created'] && isset($result['username']) && isset($result['password'])) {
                $credentials[] = [
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'email' => $row['email'],
                    'username' => $result['username'],
                    'password' => $result['password'],
                ];
            }
        }

        // Log import.
        $importer->log_import($filename);

        // Return results.
        echo json_encode([
            'success' => true,
            'stats' => $importer->get_stats(),
            'errors' => $importer->get_errors(),
            'credentials' => $credentials,
            'batch_id' => $importer->get_batch_id(),
            'message' => 'Import completed',
        ]);
    } else {
        throw new \Exception('Invalid action');
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
