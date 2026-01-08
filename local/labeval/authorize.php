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
 * Handle student authorization for viewing reports
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Require login and capability
require_login();
$context = context_system::instance();
require_capability('local/labeval:authorizestudents', $context);

// Parameters
$studentid = required_param('studentid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

// Verify sesskey
require_sesskey();

// Process action
$returnurl = new moodle_url('/local/labeval/reports.php', ['studentid' => $studentid, 'generate' => 1]);

if ($action === 'authorize') {
    local_labeval_authorize_student($studentid, $USER->id);
    redirect($returnurl, get_string('studentauthorized', 'local_labeval'), null, \core\output\notification::NOTIFY_SUCCESS);
} elseif ($action === 'revoke') {
    local_labeval_unauthorize_student($studentid);
    redirect($returnurl, get_string('studentunauthorized', 'local_labeval'), null, \core\output\notification::NOTIFY_INFO);
}

redirect($returnurl);
