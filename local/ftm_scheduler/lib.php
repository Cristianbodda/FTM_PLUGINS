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
 * Library functions for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation to add FTM Scheduler link.
 *
 * @param global_navigation $navigation
 */
function local_ftm_scheduler_extend_navigation(global_navigation $navigation) {
    global $USER, $PAGE;
    
    if (isloggedin() && !isguestuser()) {
        $context = context_system::instance();
        if (has_capability('local/ftm_scheduler:view', $context)) {
            $node = $navigation->add(
                get_string('ftm_scheduler', 'local_ftm_scheduler'),
                new moodle_url('/local/ftm_scheduler/index.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'ftm_scheduler',
                new pix_icon('i/calendar', '')
            );
        }
    }
}

/**
 * Add link to navigation block.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_ftm_scheduler_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    // Nothing needed here for now.
}

/**
 * Get group colors configuration.
 *
 * @return array
 */
function local_ftm_scheduler_get_colors() {
    return [
        'giallo' => [
            'name' => 'Giallo',
            'hex' => '#FFFF00',
            'bg' => '#FEF9C3',
            'border' => '#EAB308',
            'text' => '#333',
            'emoji' => '🟡'
        ],
        'grigio' => [
            'name' => 'Grigio',
            'hex' => '#808080',
            'bg' => '#F3F4F6',
            'border' => '#6B7280',
            'text' => '#fff',
            'emoji' => '⚫'
        ],
        'rosso' => [
            'name' => 'Rosso',
            'hex' => '#FF0000',
            'bg' => '#FEE2E2',
            'border' => '#EF4444',
            'text' => '#fff',
            'emoji' => '🔴'
        ],
        'marrone' => [
            'name' => 'Marrone',
            'hex' => '#996633',
            'bg' => '#FED7AA',
            'border' => '#92400E',
            'text' => '#fff',
            'emoji' => '🟤'
        ],
        'viola' => [
            'name' => 'Viola',
            'hex' => '#7030A0',
            'bg' => '#F3E8FF',
            'border' => '#7C3AED',
            'text' => '#fff',
            'emoji' => '🟣'
        ],
    ];
}

/**
 * Get room badge colors.
 *
 * @return array
 */
function local_ftm_scheduler_get_room_colors() {
    return [
        1 => ['bg' => '#DBEAFE', 'text' => '#1E40AF'], // AULA 1 - blu
        2 => ['bg' => '#D1FAE5', 'text' => '#065F46'], // AULA 2 - verde
        3 => ['bg' => '#FEF3C7', 'text' => '#92400E'], // AULA 3 - giallo
    ];
}

/**
 * Get time slots configuration.
 *
 * @return array
 */
function local_ftm_scheduler_get_time_slots() {
    return [
        'matt' => [
            'name' => get_string('mattina', 'local_ftm_scheduler'),
            'start' => '08:30',
            'end' => '11:45'
        ],
        'pom' => [
            'name' => get_string('pomeriggio', 'local_ftm_scheduler'),
            'start' => '13:15',
            'end' => '16:30'
        ],
    ];
}

/**
 * Calculate calendar week (KW) from timestamp.
 *
 * @param int $timestamp
 * @return int
 */
function local_ftm_scheduler_get_calendar_week($timestamp) {
    return (int) date('W', $timestamp);
}

/**
 * Get Monday of the given week.
 *
 * @param int $year
 * @param int $week
 * @return int timestamp
 */
function local_ftm_scheduler_get_monday_of_week($year, $week) {
    $dto = new DateTime();
    $dto->setISODate($year, $week, 1);
    return $dto->getTimestamp();
}

/**
 * Format date in Italian style.
 *
 * @param int $timestamp
 * @param string $format
 * @return string
 */
function local_ftm_scheduler_format_date($timestamp, $format = 'full') {
    $days = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $months = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
               'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    
    $day_name = $days[date('w', $timestamp)];
    $day_num = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    switch ($format) {
        case 'full':
            return "$day_name $day_num $month $year";
        case 'short':
            return "$day_name $day_num";
        case 'date_only':
            return "$day_num $month $year";
        case 'day_month':
            return "$day_num/$month";
        case 'month':
            return $month;
        default:
            return date('d/m/Y', $timestamp);
    }
}
