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
 * Library functions for local_competencyxmlimport.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation to add quiz export link.
 *
 * @param global_navigation $navigation The navigation object.
 */
function local_competencyxmlimport_extend_navigation(global_navigation $navigation) {
    global $PAGE;

    // Only add to question bank area or site administration.
    if (!has_capability('moodle/question:viewall', context_system::instance())) {
        return;
    }

    // Add to site administration if present.
    $siteadmin = $navigation->find('siteadministration', navigation_node::TYPE_SITE_ADMIN);
    if ($siteadmin) {
        $questionbank = $siteadmin->find('questions', navigation_node::TYPE_SETTING);
        if ($questionbank) {
            $questionbank->add(
                get_string('quizexport', 'local_competencyxmlimport'),
                new moodle_url('/local/competencyxmlimport/quiz_export.php'),
                navigation_node::TYPE_SETTING,
                null,
                'quizexport',
                new pix_icon('i/export', '')
            );
        }
    }
}

/**
 * Add link to settings navigation.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The context.
 */
function local_competencyxmlimport_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    // Add to course settings if in a course.
    if ($context instanceof context_course || $context instanceof context_module) {
        if (has_capability('moodle/question:viewall', $context)) {
            $coursesettings = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
            if ($coursesettings) {
                // Try to find questionbank node (any type).
                $questionbank = $coursesettings->find('questionbank', null);

                // If not found, add our link directly to course settings.
                $parentnode = $questionbank ?: $coursesettings;

                // Check if our node already exists to avoid duplicates.
                if (!$parentnode->find('quizexport', navigation_node::TYPE_SETTING)) {
                    $courseid = ($context instanceof context_course) ? $context->instanceid : $context->get_course_context()->instanceid;
                    $parentnode->add(
                        get_string('quizexport', 'local_competencyxmlimport'),
                        new moodle_url('/local/competencyxmlimport/quiz_export.php', ['courseid' => $courseid]),
                        navigation_node::TYPE_SETTING,
                        null,
                        'quizexport',
                        new pix_icon('i/export', '')
                    );
                }
            }
        }
    }
}
