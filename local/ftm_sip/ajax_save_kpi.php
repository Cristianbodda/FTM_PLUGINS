<?php
/**
 * AJAX endpoint for coach KPI management (add, update status, delete).
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
require_capability('local/ftm_sip:edit', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {
        case 'add_application':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $company_name = required_param('company_name', PARAM_TEXT);
            $position = optional_param('position', '', PARAM_TEXT);
            $app_type = optional_param('application_type', 'targeted', PARAM_ALPHANUMEXT);
            $date_str = optional_param('application_date', date('Y-m-d'), PARAM_TEXT);

            $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);
            $date = strtotime($date_str) ?: time();
            $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);

            $now = time();
            $r = new stdClass();
            $r->enrollmentid = $enrollmentid;
            $r->companyid = $companyid;
            $r->company_name = trim($company_name);
            $r->position = $position;
            $r->application_date = $date;
            $r->application_type = $app_type;
            $r->status = 'sent';
            $r->addedby = $USER->id;
            $r->timecreated = $now;
            $r->timemodified = $now;
            $DB->insert_record('local_ftm_sip_applications', $r);
            echo json_encode(['success' => true, 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'add_contact':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $company_name = required_param('company_name', PARAM_TEXT);
            $contact_type = required_param('contact_type', PARAM_ALPHANUMEXT);
            $date_str = optional_param('contact_date', date('Y-m-d'), PARAM_TEXT);
            $contact_person = optional_param('contact_person', '', PARAM_TEXT);

            $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);
            $date = strtotime($date_str) ?: time();
            $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);

            $now = time();
            $r = new stdClass();
            $r->enrollmentid = $enrollmentid;
            $r->companyid = $companyid;
            $r->company_name = trim($company_name);
            $r->contact_type = $contact_type;
            $r->contact_date = $date;
            $r->contact_person = $contact_person;
            $r->outcome = 'neutral';
            $r->addedby = $USER->id;
            $r->timecreated = $now;
            $r->timemodified = $now;
            $DB->insert_record('local_ftm_sip_contacts', $r);
            echo json_encode(['success' => true, 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'add_opportunity':
            $enrollmentid = required_param('enrollmentid', PARAM_INT);
            $company_name = required_param('company_name', PARAM_TEXT);
            $opp_type = required_param('opportunity_type', PARAM_ALPHANUMEXT);
            $date_str = optional_param('opportunity_date', date('Y-m-d'), PARAM_TEXT);

            $enrollment = $DB->get_record('local_ftm_sip_enrollments', ['id' => $enrollmentid], '*', MUST_EXIST);
            $date = strtotime($date_str) ?: time();
            $companyid = sip_manager::find_or_create_company($company_name, $USER->id, $enrollment->sector);

            $now = time();
            $r = new stdClass();
            $r->enrollmentid = $enrollmentid;
            $r->companyid = $companyid;
            $r->company_name = trim($company_name);
            $r->opportunity_type = $opp_type;
            $r->opportunity_date = $date;
            $r->status = 'planned';
            $r->addedby = $USER->id;
            $r->timecreated = $now;
            $r->timemodified = $now;
            $DB->insert_record('local_ftm_sip_opportunities', $r);
            echo json_encode(['success' => true, 'message' => get_string('kpi_saved', 'local_ftm_sip')]);
            break;

        case 'update_app_status':
            $id = required_param('id', PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);
            $allowed = ['sent', 'waiting', 'interview', 'rejected', 'no_response'];
            if (!in_array($status, $allowed)) throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            $r = new stdClass(); $r->id = $id; $r->status = $status; $r->timemodified = time();
            $DB->update_record('local_ftm_sip_applications', $r);
            echo json_encode(['success' => true]);
            break;

        case 'update_contact_outcome':
            $id = required_param('id', PARAM_INT);
            $outcome = required_param('outcome', PARAM_ALPHANUMEXT);
            $allowed = ['positive', 'neutral', 'negative'];
            if (!in_array($outcome, $allowed)) throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            $r = new stdClass(); $r->id = $id; $r->outcome = $outcome; $r->timemodified = time();
            $DB->update_record('local_ftm_sip_contacts', $r);
            echo json_encode(['success' => true]);
            break;

        case 'update_opp_status':
            $id = required_param('id', PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);
            $allowed = ['planned', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($status, $allowed)) throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            $r = new stdClass(); $r->id = $id; $r->status = $status; $r->timemodified = time();
            $DB->update_record('local_ftm_sip_opportunities', $r);
            echo json_encode(['success' => true]);
            break;

        case 'delete_entry':
            $table = required_param('table', PARAM_ALPHANUMEXT);
            $id = required_param('id', PARAM_INT);
            $allowed_tables = ['applications', 'contacts', 'opportunities'];
            if (!in_array($table, $allowed_tables)) throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            $DB->delete_records('local_ftm_sip_' . $table, ['id' => $id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
