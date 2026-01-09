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
 * Library functions for local_ftm_suite
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation node to site administration.
 *
 * @param navigation_node $nav The navigation node to extend
 */
function local_ftm_suite_extend_navigation(global_navigation $nav) {
    // This function is called for all users, but we only want to show the link to admins.
    // The actual capability check is done in index.php.
}

/**
 * Add settings to the admin tree.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context
 */
function local_ftm_suite_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    // Only show in site administration for admins.
    if (!has_capability('moodle/site:config', context_system::instance())) {
        return;
    }

    // Get the root node for site admin.
    $siteadminnode = $settingsnav->find('siteadministration', navigation_node::TYPE_SITE_ADMIN);
    if (!$siteadminnode) {
        return;
    }

    // Try to find the plugins category.
    $pluginsnode = $siteadminnode->find('localplugins', navigation_node::TYPE_SETTING);
    if ($pluginsnode) {
        $pluginsnode->add(
            get_string('navigation_label', 'local_ftm_suite'),
            new moodle_url('/local/ftm_suite/index.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_ftm_suite',
            new pix_icon('i/info', '')
        );
    }
}
