<?php
/**
 * AJAX endpoint for company registry management.
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
        case 'create':
            $name = required_param('name', PARAM_TEXT);
            $sector = optional_param('sector', '', PARAM_TEXT);
            $city = optional_param('city', '', PARAM_TEXT);
            $phone = optional_param('phone', '', PARAM_TEXT);
            $email = optional_param('email', '', PARAM_TEXT);
            $website = optional_param('website', '', PARAM_TEXT);
            $contact_person = optional_param('contact_person', '', PARAM_TEXT);
            $contact_role = optional_param('contact_role', '', PARAM_TEXT);
            $notes = optional_param('notes', '', PARAM_TEXT);

            $now = time();
            $record = new stdClass();
            $record->name = trim($name);
            $record->name_normalized = strtolower(trim($name));
            $record->sector = $sector ?: null;
            $record->city = $city ?: null;
            $record->phone = $phone ?: null;
            $record->email = $email ?: null;
            $record->website = $website ?: null;
            $record->contact_person = $contact_person ?: null;
            $record->contact_role = $contact_role ?: null;
            $record->notes = $notes ?: null;
            $record->status = 'prospect';
            $record->interaction_count = 0;
            $record->createdby = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;

            // Check for duplicates.
            $existing = $DB->get_record('local_ftm_sip_companies', ['name_normalized' => $record->name_normalized]);
            if ($existing) {
                // Redirect back with message if form POST.
                if (!defined('AJAX_SCRIPT') || !AJAX_SCRIPT) {
                    redirect(new moodle_url('/local/ftm_sip/companies.php'),
                        'Azienda gia esistente: ' . $existing->name, null, \core\output\notification::NOTIFY_WARNING);
                }
                throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            }

            $id = $DB->insert_record('local_ftm_sip_companies', $record);

            // If called from form POST, redirect.
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
                redirect(new moodle_url('/local/ftm_sip/companies.php'),
                    get_string('company_saved', 'local_ftm_sip'), null, \core\output\notification::NOTIFY_SUCCESS);
            }

            echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => get_string('company_saved', 'local_ftm_sip')]);
            break;

        case 'update_status':
            $companyid = required_param('companyid', PARAM_INT);
            $status = required_param('status', PARAM_ALPHANUMEXT);
            $allowed = ['prospect', 'contacted', 'interested', 'collaborating', 'inactive'];
            if (!in_array($status, $allowed)) {
                throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            }
            $record = new stdClass();
            $record->id = $companyid;
            $record->status = $status;
            $record->timemodified = time();
            $DB->update_record('local_ftm_sip_companies', $record);
            echo json_encode(['success' => true]);
            break;

        case 'update':
            $companyid = required_param('companyid', PARAM_INT);
            $field = required_param('field', PARAM_ALPHANUMEXT);
            $value = required_param('value', PARAM_TEXT);
            $allowed_fields = ['name', 'sector', 'city', 'phone', 'email', 'website',
                               'contact_person', 'contact_role', 'notes', 'address'];
            if (!in_array($field, $allowed_fields)) {
                throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
            }
            $record = new stdClass();
            $record->id = $companyid;
            $record->{$field} = $value;
            $record->timemodified = time();
            if ($field === 'name') {
                $record->name_normalized = strtolower(trim($value));
            }
            $DB->update_record('local_ftm_sip_companies', $record);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $companyid = required_param('companyid', PARAM_INT);
            require_capability('local/ftm_sip:manage', $context);
            $DB->delete_records('local_ftm_sip_companies', ['id' => $companyid]);
            echo json_encode(['success' => true, 'message' => get_string('company_deleted', 'local_ftm_sip')]);
            break;

        case 'search':
            $query = required_param('query', PARAM_TEXT);
            $results = sip_manager::search_companies($query, 10);
            $data = [];
            foreach ($results as $c) {
                $data[] = ['id' => $c->id, 'name' => $c->name, 'sector' => $c->sector, 'city' => $c->city];
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            throw new moodle_exception('error_invalid_data', 'local_ftm_sip');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
