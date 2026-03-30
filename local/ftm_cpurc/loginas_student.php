<?php
/**
 * Login as student - proper loginas with return support.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$userid = required_param('userid', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$context = context_system::instance();

// Check capability: import permission OR loginas OR siteadmin.
$canloginas = has_capability('moodle/user:loginas', $context)
           || has_capability('local/ftm_cpurc:import', $context)
           || is_siteadmin();

if (!$canloginas) {
    throw new moodle_exception('nopermissions', 'error', '', 'login as student');
}

// Validate target user exists and is not deleted.
$targetuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
if (!$targetuser) {
    throw new moodle_exception('invaliduser');
}

// Cannot login as admin users.
if (is_siteadmin($userid)) {
    throw new moodle_exception('nopermissions', 'error', '', 'login as admin user');
}

// Perform the login-as using Moodle's session manager.
\core\session\manager::loginas($userid, $context);

// Redirect to student dashboard.
if ($returnurl) {
    redirect(new moodle_url($returnurl));
} else {
    redirect(new moodle_url('/my/'));
}
