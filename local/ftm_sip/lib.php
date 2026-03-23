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
 * Library functions for local_ftm_sip.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * SIP color constant (teal).
 */
define('LOCAL_FTM_SIP_COLOR', '#0891B2');
define('LOCAL_FTM_SIP_COLOR_BG', '#ECFEFF');
define('LOCAL_FTM_SIP_COLOR_BORDER', '#06B6D4');
define('LOCAL_FTM_SIP_COLOR_TEXT', '#155E75');

/**
 * SIP total weeks.
 */
define('LOCAL_FTM_SIP_TOTAL_WEEKS', 10);

/**
 * SIP total phases.
 */
define('LOCAL_FTM_SIP_TOTAL_PHASES', 6);

/**
 * Extend main navigation with SIP links.
 *
 * @param global_navigation $navigation
 */
function local_ftm_sip_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    // Coach/Segreteria: Dashboard SIP.
    if (has_capability('local/ftm_sip:view', $context)) {
        $navigation->add(
            get_string('sip_manager', 'local_ftm_sip'),
            new moodle_url('/local/ftm_sip/sip_dashboard.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'ftm_sip_dashboard',
            new pix_icon('i/report', '')
        );
    }

    // Studente: Area personale SIP.
    if (has_capability('local/ftm_sip:viewown', $context) &&
        !has_capability('local/ftm_sip:view', $context)) {
        global $USER;
        if (local_ftm_sip_has_active_enrollment($USER->id)) {
            $navigation->add(
                get_string('student_area_title', 'local_ftm_sip'),
                new moodle_url('/local/ftm_sip/sip_my.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'ftm_sip_my',
                new pix_icon('i/user', '')
            );
        }
    }
}

/**
 * Check if a user has an active SIP enrollment.
 *
 * @param int $userid
 * @return bool
 */
function local_ftm_sip_has_active_enrollment($userid) {
    global $DB;
    return $DB->record_exists('local_ftm_sip_enrollments', [
        'userid' => $userid,
        'status' => 'active',
    ]);
}

/**
 * Get SIP enrollment for a user.
 *
 * @param int $userid
 * @return object|false
 */
function local_ftm_sip_get_enrollment($userid) {
    global $DB;
    return $DB->get_record('local_ftm_sip_enrollments', ['userid' => $userid]);
}

/**
 * Calculate current SIP week for a user.
 *
 * @param int $date_start Unix timestamp of SIP start.
 * @return int Week number (1-10+).
 */
function local_ftm_sip_calculate_week($date_start) {
    $now = time();
    if ($date_start > $now) {
        return 0; // Not started yet.
    }
    $diff = $now - $date_start;
    $weeks = floor($diff / (7 * 86400)) + 1;
    return (int) $weeks;
}

/**
 * Get current phase based on week number.
 *
 * @param int $week Week number (1-10).
 * @return int Phase number (1-6).
 */
function local_ftm_sip_get_phase($week) {
    if ($week <= 1) {
        return 1; // Analisi e orientamento.
    }
    if ($week <= 2) {
        return 2; // Costruzione strategia.
    }
    if ($week <= 4) {
        return 3; // Attivazione ricerca.
    }
    if ($week <= 6) {
        return 4; // Rafforzamento strategia.
    }
    if ($week <= 8) {
        return 5; // Contatto mercato lavoro.
    }
    return 6; // Consolidamento e valutazione.
}

/**
 * Get the 7 activation areas definition.
 *
 * @return array Area key => [name_key, desc_key, obj_key, verify_key, icon, color]
 */
function local_ftm_sip_get_activation_areas() {
    return [
        'professional_strategy' => [
            'name' => 'area_professional_strategy',
            'desc' => 'area_professional_strategy_desc',
            'objective' => 'area_professional_strategy_obj',
            'verify' => 'area_professional_strategy_verify',
            'icon' => 'fa-bullseye',
            'color' => '#2563EB',
        ],
        'job_monitoring' => [
            'name' => 'area_job_monitoring',
            'desc' => 'area_job_monitoring_desc',
            'objective' => 'area_job_monitoring_obj',
            'verify' => 'area_job_monitoring_verify',
            'icon' => 'fa-search',
            'color' => '#7C3AED',
        ],
        'targeted_applications' => [
            'name' => 'area_targeted_applications',
            'desc' => 'area_targeted_applications_desc',
            'objective' => 'area_targeted_applications_obj',
            'verify' => 'area_targeted_applications_verify',
            'icon' => 'fa-paper-plane',
            'color' => '#059669',
        ],
        'unsolicited_applications' => [
            'name' => 'area_unsolicited_applications',
            'desc' => 'area_unsolicited_applications_desc',
            'objective' => 'area_unsolicited_applications_obj',
            'verify' => 'area_unsolicited_applications_verify',
            'icon' => 'fa-envelope-open',
            'color' => '#D97706',
        ],
        'direct_company_contact' => [
            'name' => 'area_direct_company_contact',
            'desc' => 'area_direct_company_contact_desc',
            'objective' => 'area_direct_company_contact_obj',
            'verify' => 'area_direct_company_contact_verify',
            'icon' => 'fa-handshake-o',
            'color' => '#DC2626',
        ],
        'personal_network' => [
            'name' => 'area_personal_network',
            'desc' => 'area_personal_network_desc',
            'objective' => 'area_personal_network_obj',
            'verify' => 'area_personal_network_verify',
            'icon' => 'fa-users',
            'color' => '#0891B2',
        ],
        'intermediaries' => [
            'name' => 'area_intermediaries',
            'desc' => 'area_intermediaries_desc',
            'objective' => 'area_intermediaries_obj',
            'verify' => 'area_intermediaries_verify',
            'icon' => 'fa-building',
            'color' => '#64748B',
        ],
    ];
}

/**
 * Get the 6 roadmap phases definition.
 *
 * @return array Phase number => [name_key, desc_key, weeks]
 */
function local_ftm_sip_get_phases() {
    return [
        1 => ['name' => 'phase_1', 'desc' => 'phase_1_desc', 'weeks' => '1'],
        2 => ['name' => 'phase_2', 'desc' => 'phase_2_desc', 'weeks' => '2'],
        3 => ['name' => 'phase_3', 'desc' => 'phase_3_desc', 'weeks' => '3-4'],
        4 => ['name' => 'phase_4', 'desc' => 'phase_4_desc', 'weeks' => '5-6'],
        5 => ['name' => 'phase_5', 'desc' => 'phase_5_desc', 'weeks' => '7-8'],
        6 => ['name' => 'phase_6', 'desc' => 'phase_6_desc', 'weeks' => '9-10'],
    ];
}

/**
 * Get the activation scale labels (0-6).
 *
 * @return array Level => lang string key
 */
function local_ftm_sip_get_activation_scale() {
    return [
        0 => 'score_0',
        1 => 'score_1',
        2 => 'score_2',
        3 => 'score_3',
        4 => 'score_4',
        5 => 'score_5',
        6 => 'score_6',
    ];
}
