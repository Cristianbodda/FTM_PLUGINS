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
 * AJAX endpoint for calendar import from Excel.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_scheduler/classes/calendar_importer.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $year = optional_param('year', date('Y'), PARAM_INT);
    $sheets = optional_param_array('sheets', [], PARAM_RAW);
    $updateexisting = optional_param('update_existing', 0, PARAM_INT);
    $dryrun = optional_param('dry_run', 1, PARAM_INT);

    // Check for uploaded file.
    if (!isset($_FILES['excelfile']) || $_FILES['excelfile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(get_string('import_error_nofile', 'local_ftm_scheduler'));
    }

    $uploadedfile = $_FILES['excelfile'];
    $filename = clean_param($uploadedfile['name'], PARAM_FILE);

    // Validate file extension.
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        throw new Exception(get_string('import_error_invalidfile', 'local_ftm_scheduler'));
    }

    // Move uploaded file to temp directory.
    $tempdir = make_temp_directory('ftm_scheduler_import');
    $tempfile = $tempdir . '/' . uniqid('calendar_') . '.' . $ext;

    if (!move_uploaded_file($uploadedfile['tmp_name'], $tempfile)) {
        throw new Exception(get_string('import_error_upload', 'local_ftm_scheduler'));
    }

    // Create importer instance.
    $importer = new \local_ftm_scheduler\calendar_importer($year);

    $result = ['success' => false];

    switch ($action) {
        case 'get_sheets':
            // Get list of sheets from Excel file.
            $preview = $importer->preview_file($tempfile, null, 0);
            $result = [
                'success' => true,
                'sheets' => $preview['sheets'],
            ];
            break;

        case 'preview':
            // Preview activities from selected sheets.
            $allactivities = [];

            if (empty($sheets)) {
                // Preview all sheets if none selected.
                $preview = $importer->preview_file($tempfile);
                $sheets = $preview['sheets'];
            }

            foreach ($sheets as $sheetname) {
                $preview = $importer->preview_file($tempfile, $sheetname, 100);
                if (!empty($preview['preview'])) {
                    $allactivities = array_merge($allactivities, $preview['preview']);
                }
            }

            // Sort by date.
            usort($allactivities, function($a, $b) {
                return $a['timestamp_start'] - $b['timestamp_start'];
            });

            // Limit preview to first 50 activities.
            $previewactivities = array_slice($allactivities, 0, 50);

            $result = [
                'success' => true,
                'preview' => $previewactivities,
                'total_activities' => count($allactivities),
                'sheets_processed' => count($sheets),
            ];
            break;

        case 'import':
            // Import activities.
            $options = [
                'sheets' => $sheets,
                'update_existing' => (bool)$updateexisting,
                'dry_run' => (bool)$dryrun,
            ];

            $importresult = $importer->import_file($tempfile, $options);

            $result = [
                'success' => $importresult['success'],
                'stats' => $importresult['stats'],
                'errors' => $importresult['errors'],
                'dry_run' => (bool)$dryrun,
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }

    // Clean up temp file.
    if (file_exists($tempfile)) {
        unlink($tempfile);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
