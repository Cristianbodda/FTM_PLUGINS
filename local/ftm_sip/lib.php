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
        // --- 10 aree quantitative (conteggio contatti/azioni per settimana) ---
        'target_companies' => [
            'name' => 'area_target_companies',
            'desc' => 'area_target_companies_desc',
            'objective' => 'area_target_companies_obj',
            'verify' => 'area_target_companies_verify',
            'icon' => 'fa-list-ol',
            'color' => '#2563EB',
            'week_start' => 1, 'week_end' => 4,
            'type' => 'quantitative',
            'default_target' => 30,
        ],
        'mandatory_searches' => [
            'name' => 'area_mandatory_searches',
            'desc' => 'area_mandatory_searches_desc',
            'objective' => 'area_mandatory_searches_obj',
            'verify' => 'area_mandatory_searches_verify',
            'icon' => 'fa-search',
            'color' => '#7C3AED',
            'week_start' => 1, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 40,
        ],
        'search_channels' => [
            'name' => 'area_search_channels',
            'desc' => 'area_search_channels_desc',
            'objective' => 'area_search_channels_obj',
            'verify' => 'area_search_channels_verify',
            'icon' => 'fa-sitemap',
            'color' => '#059669',
            'week_start' => 2, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 15,
        ],
        'social_network' => [
            'name' => 'area_social_network',
            'desc' => 'area_social_network_desc',
            'objective' => 'area_social_network_obj',
            'verify' => 'area_social_network_verify',
            'icon' => 'fa-share-alt',
            'color' => '#0EA5E9',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        'personal_network' => [
            'name' => 'area_personal_network',
            'desc' => 'area_personal_network_desc',
            'objective' => 'area_personal_network_obj',
            'verify' => 'area_personal_network_verify',
            'icon' => 'fa-users',
            'color' => '#0891B2',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 10,
        ],
        'targeted_applications' => [
            'name' => 'area_targeted_applications',
            'desc' => 'area_targeted_applications_desc',
            'objective' => 'area_targeted_applications_obj',
            'verify' => 'area_targeted_applications_verify',
            'icon' => 'fa-paper-plane',
            'color' => '#D97706',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 30,
        ],
        'unsolicited_applications' => [
            'name' => 'area_unsolicited_applications',
            'desc' => 'area_unsolicited_applications_desc',
            'objective' => 'area_unsolicited_applications_obj',
            'verify' => 'area_unsolicited_applications_verify',
            'icon' => 'fa-envelope-open',
            'color' => '#DC2626',
            'week_start' => 3, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 10,
        ],
        'agencies_urc' => [
            'name' => 'area_agencies_urc',
            'desc' => 'area_agencies_urc_desc',
            'objective' => 'area_agencies_urc_obj',
            'verify' => 'area_agencies_urc_verify',
            'icon' => 'fa-building',
            'color' => '#64748B',
            'week_start' => 5, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 5,
        ],
        'interview_training' => [
            'name' => 'area_interview_training',
            'desc' => 'area_interview_training_desc',
            'objective' => 'area_interview_training_obj',
            'verify' => 'area_interview_training_verify',
            'icon' => 'fa-microphone',
            'color' => '#E11D48',
            'week_start' => 5, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        'stage_trials' => [
            'name' => 'area_stage_trials',
            'desc' => 'area_stage_trials_desc',
            'objective' => 'area_stage_trials_obj',
            'verify' => 'area_stage_trials_verify',
            'icon' => 'fa-briefcase',
            'color' => '#9333EA',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'quantitative',
            'default_target' => 2,
        ],
        // --- 2 aree qualitative (valutazione coach 1-10 per settimana) ---
        'strategy_improvement' => [
            'name' => 'area_strategy_improvement',
            'desc' => 'area_strategy_improvement_desc',
            'objective' => 'area_strategy_improvement_obj',
            'verify' => 'area_strategy_improvement_verify',
            'icon' => 'fa-line-chart',
            'color' => '#F59E0B',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'qualitative',
            'default_target' => 5,
        ],
        'growing_autonomy' => [
            'name' => 'area_growing_autonomy',
            'desc' => 'area_growing_autonomy_desc',
            'objective' => 'area_growing_autonomy_obj',
            'verify' => 'area_growing_autonomy_verify',
            'icon' => 'fa-graduation-cap',
            'color' => '#10B981',
            'week_start' => 7, 'week_end' => 10,
            'type' => 'qualitative',
            'default_target' => 5,
        ],
    ];
}

/**
 * Get the old 7 activation areas (for backward compatibility with existing data).
 * @return array
 */
function local_ftm_sip_get_legacy_areas() {
    return ['professional_strategy', 'job_monitoring', 'targeted_applications',
            'unsolicited_applications', 'direct_company_contact', 'personal_network', 'intermediaries'];
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
