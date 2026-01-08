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
 * Settings for local_labeval
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_labeval', get_string('pluginname', 'local_labeval'));
    
    // Add link to dashboard
    $settings->add(new admin_setting_heading(
        'local_labeval/dashboard_link',
        '',
        '<a href="' . new moodle_url('/local/labeval/index.php') . '" class="btn btn-primary">' .
        get_string('dashboard', 'local_labeval') . '</a>'
    ));
    
    $ADMIN->add('localplugins', $settings);
}
