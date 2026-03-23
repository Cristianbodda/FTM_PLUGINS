<?php
/**
 * AJAX endpoint for student self-service KPI entry.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

use local_ftm_sip\sip_manager;

require_login();
require_sesskey();

$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    // Student must have viewown capability.
    require_capability('local/ftm_sip:viewown', $context);

    $action = required_param('action', PARAM_ALPHANUMEXT);

    // Get student's enrollment.
    $enrollment = sip_manager::get_enrollment($USER->id);
    if (!$enrollment || !$enrollment->student_visible) {
        throw new moodle_exception('error_permission', 'local_ftm_sip');
    }

    switch ($action) {
        case 'add_application':
            $company_name = required_param('company_name', PARAM_TEXT);
            $position = optional_param('position', '', PARAM_TEXT);
            $application_type = optional_param('application_type', 'targeted', PARAM_ALPHANUMEXT);
            $date_str = optional_param('application_date', date('Y-m-d'), PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            $date = strtotime($date_str) ?: time();

            // Find or create company.
            $companyid = null;
            if (!empty($company_name)) {
                $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);
            }

            $now = time();
            $record = new stdClass();
            $record->enrollmentid = $enrollment->id;
            $record->companyid = $companyid;
            $record->company_name = trim($company_name);
            $record->position = $position;
            $record->application_date = $date;
            $record->application_type = $application_type;
            $record->status = 'sent';
            $record->notes = $notes;
            $record->addedby = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $id = $DB->insert_record('local_ftm_sip_applications', $record);

            echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'add_contact':
            $company_name = required_param('company_name', PARAM_TEXT);
            $contact_type = required_param('contact_type', PARAM_ALPHANUMEXT);
            $date_str = optional_param('contact_date', date('Y-m-d'), PARAM_TEXT);
            $contact_person = optional_param('contact_person', '', PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            $date = strtotime($date_str) ?: time();

            $companyid = null;
            if (!empty($company_name)) {
                $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);
            }

            $now = time();
            $record = new stdClass();
            $record->enrollmentid = $enrollment->id;
            $record->companyid = $companyid;
            $record->company_name = trim($company_name);
            $record->contact_type = $contact_type;
            $record->contact_date = $date;
            $record->contact_person = $contact_person;
            $record->outcome = 'neutral';
            $record->notes = $notes;
            $record->addedby = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $id = $DB->insert_record('local_ftm_sip_contacts', $record);

            echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'add_opportunity':
            $company_name = required_param('company_name', PARAM_TEXT);
            $opportunity_type = required_param('opportunity_type', PARAM_ALPHANUMEXT);
            $date_str = optional_param('opportunity_date', date('Y-m-d'), PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            $date = strtotime($date_str) ?: time();

            $companyid = null;
            if (!empty($company_name)) {
                $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);
            }

            $now = time();
            $record = new stdClass();
            $record->enrollmentid = $enrollment->id;
            $record->companyid = $companyid;
            $record->company_name = trim($company_name);
            $record->opportunity_type = $opportunity_type;
            $record->opportunity_date = $date;
            $record->status = 'planned';
            $record->notes = $notes;
            $record->addedby = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $id = $DB->insert_record('local_ftm_sip_opportunities', $record);

            echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'complete_action':
            $actionid = required_param('actionid', PARAM_INT);
            // Verify action belongs to this student's enrollment.
            $act = $DB->get_record('local_ftm_sip_actions', ['id' => $actionid], '*', MUST_EXIST);
            if ($act->enrollmentid != $enrollment->id) {
                throw new moodle_exception('error_permission', 'local_ftm_sip');
            }
            sip_manager::update_action_status($actionid, 'completed', $USER->id);
            echo json_encode(['success' => true, 'message' => get_string('action_saved', 'local_ftm_sip')]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
