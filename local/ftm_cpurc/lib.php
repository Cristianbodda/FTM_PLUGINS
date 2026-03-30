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
 * Library functions for local_ftm_cpurc.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation to add CPURC Manager link.
 *
 * @param global_navigation $navigation The navigation object.
 */
function local_ftm_cpurc_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    if (has_capability('local/ftm_cpurc:view', $context)) {
        $node = $navigation->add(
            get_string('cpurc_manager', 'local_ftm_cpurc'),
            new moodle_url('/local/ftm_cpurc/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'ftm_cpurc',
            new pix_icon('i/user', '')
        );
    }
}

/**
 * Extend settings navigation.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The context.
 */
function local_ftm_cpurc_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    if ($settingnode = $settingsnav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        if (has_capability('local/ftm_cpurc:view', context_system::instance())) {
            $settingnode->add(
                get_string('cpurc_manager', 'local_ftm_cpurc'),
                new moodle_url('/local/ftm_cpurc/index.php'),
                navigation_node::TYPE_SETTING
            );
        }
    }
}

/**
 * Inject a floating "Return to your account" banner when logged in as another user.
 * Workaround for Adaptable theme not showing the standard Moodle loginas return link.
 */
function local_ftm_cpurc_before_footer() {
    global $USER, $CFG;

    if (\core\session\manager::is_loggedinas()) {
        $realuser = \core\session\manager::get_realuser();
        $returnurl = $CFG->wwwroot . '/course/loginas.php?id=1&sesskey=' . sesskey();
        echo '<div id="ftm-loginas-banner" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 99999;
            background: linear-gradient(135deg, #dc3545, #c0392b);
            color: #fff;
            padding: 8px 20px;
            font-size: 14px;
            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        ">
            <span>
                <strong>Stai operando come:</strong> ' . s(fullname($USER)) . '
                &nbsp;|&nbsp;
                <strong>Il tuo account:</strong> ' . s(fullname($realuser)) . '
            </span>
            <a href="' . $returnurl . '" style="
                background: #fff;
                color: #dc3545;
                padding: 6px 18px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 700;
                font-size: 13px;
            ">Ritorna al tuo account</a>
        </div>
        <div style="height: 42px;"></div>';
    }
}

/**
 * URC office codes and names.
 *
 * @return array Array of URC offices.
 */
function local_ftm_cpurc_get_urc_offices() {
    return [
        'URC Bellinzona' => 'urc_bellinzona',
        'URC Chiasso' => 'urc_chiasso',
        'URC Lugano' => 'urc_lugano',
        'URC Biasca' => 'urc_biasca',
        'URC Locarno' => 'urc_locarno',
    ];
}

/**
 * Get color group based on calendar week.
 *
 * @param int $calendarweek The calendar week number.
 * @return string The color name.
 */
function local_ftm_cpurc_get_color_for_week($calendarweek) {
    $colors = ['giallo', 'grigio', 'rosso', 'marrone', 'viola'];
    // Cycle through colors every 5 weeks.
    $index = ($calendarweek - 1) % 5;
    return $colors[$index];
}

/**
 * Parse Swiss date format (dd.mm.yyyy) to timestamp.
 *
 * @param string $datestr Date string in dd.mm.yyyy format.
 * @return int|null Timestamp or null if invalid.
 */
function local_ftm_cpurc_parse_date($datestr) {
    if (empty($datestr)) {
        return null;
    }

    $datestr = trim($datestr);

    // Handle dd.mm.yyyy format.
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $datestr, $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];

        if (checkdate($month, $day, $year)) {
            return mktime(0, 0, 0, $month, $day, $year);
        }
    }

    return null;
}

/**
 * Format timestamp to Swiss date format.
 *
 * @param int $timestamp The timestamp.
 * @return string Date in dd.mm.yyyy format.
 */
function local_ftm_cpurc_format_date($timestamp) {
    if (empty($timestamp)) {
        return '';
    }
    return date('d.m.Y', $timestamp);
}
