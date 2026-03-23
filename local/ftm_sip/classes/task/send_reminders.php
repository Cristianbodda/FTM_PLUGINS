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
 * Scheduled task to send SIP reminders and alerts.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_sip\task;

defined('MOODLE_INTERNAL') || die();

class send_reminders extends \core\task\scheduled_task {

    /**
     * Return the task name.
     */
    public function get_name() {
        return get_string('pluginname', 'local_ftm_sip') . ' - Send Reminders';
    }

    /**
     * Execute the task.
     */
    public function execute() {
        require_once(__DIR__ . '/../../lib.php');
        require_once(__DIR__ . '/../sip_manager.php');
        require_once(__DIR__ . '/../notification_helper.php');

        mtrace('SIP: Processing reminders...');

        \local_ftm_sip\notification_helper::process_reminders();

        mtrace('SIP: Reminders processed.');
    }
}
