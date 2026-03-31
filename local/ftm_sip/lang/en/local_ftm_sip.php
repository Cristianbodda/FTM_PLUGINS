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
 * English language strings for local_ftm_sip.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'FTM Individualized Coaching';
$string['pluginname_desc'] = 'Individualized Coaching: 10-week coaching program with action plan, diary and KPIs.';

// Capabilities.
$string['ftm_sip:view'] = 'View Coaching data';
$string['ftm_sip:manage'] = 'Manage Coaching enrollments';
$string['ftm_sip:edit'] = 'Edit Coaching student data';
$string['ftm_sip:coach'] = 'Access coaching features';
$string['ftm_sip:generatereport'] = 'Generate Coaching reports';
$string['ftm_sip:viewown'] = 'View own Coaching data (student)';

// Navigation.
$string['sip_manager'] = 'Individualized Coaching';
$string['dashboard'] = 'Dashboard';
$string['enrollments'] = 'Enrollments';
$string['action_plan'] = 'Action Plan';
$string['coaching_diary'] = 'Coaching Diary';
$string['appointments'] = 'Appointments';
$string['kpi_overview'] = 'KPI Overview';
$string['student_area'] = 'Student Area';
$string['company_registry'] = 'Company Registry';

// Dashboard.
$string['total_enrollments'] = 'Total Enrollments';
$string['active_enrollments'] = 'Active Enrollments';
$string['completed_enrollments'] = 'Completed Enrollments';
$string['upcoming_appointments'] = 'Upcoming Appointments';
$string['current_week'] = 'Current Week';
$string['week_of_10'] = 'Week {$a->current} of {$a->total}';
$string['dashboard_title'] = 'Individualized Coaching Dashboard';

// Enrollments.
$string['enrollment_title'] = 'Coaching Enrollments';
$string['new_enrollment'] = 'New Enrollment';
$string['edit_enrollment'] = 'Edit Enrollment';
$string['enrollment_start'] = 'Start Date';
$string['enrollment_end'] = 'End Date';
$string['enrollment_end_planned'] = 'Planned End Date';
$string['enrollment_end_actual'] = 'Actual End Date';
$string['enrollment_status'] = 'Status';
$string['enrollment_coach'] = 'Assigned Coach';
$string['enrollment_notes'] = 'Notes';
$string['enrollment_saved'] = 'Enrollment saved successfully';
$string['enrollment_deleted'] = 'Enrollment deleted';
$string['enrollment_not_found'] = 'Enrollment not found';
$string['confirm_delete_enrollment'] = 'Are you sure you want to delete this enrollment?';

// Enrollment statuses.
$string['status_active'] = 'Active';
$string['status_completed'] = 'Completed';
$string['status_suspended'] = 'Suspended';
$string['status_cancelled'] = 'Cancelled';

// Action plan.
$string['action_plan_title'] = 'Action Plan';
$string['activation_areas'] = 'Activation Areas';
$string['area_score'] = 'Score (0-6)';
$string['area_notes'] = 'Area Notes';
$string['area_objectives'] = 'Objectives';
$string['area_actions'] = 'Planned Actions';
$string['area_deadline'] = 'Deadline';
$string['action_plan_saved'] = 'Action plan saved successfully';
$string['action_plan_not_found'] = 'Action plan not found';

// 7 Activation areas (from Sostegno Individuale Personalizzato document).
$string['area_professional_strategy'] = 'Professional Strategy';
$string['area_job_monitoring'] = 'Job Posting Monitoring';
$string['area_targeted_applications'] = 'Targeted Applications';
$string['area_unsolicited_applications'] = 'Unsolicited Applications';
$string['area_direct_company_contact'] = 'Direct Company Contact';
$string['area_personal_network'] = 'Personal and Professional Network';
$string['area_intermediaries'] = 'Labour Market Intermediaries';

// Activation area descriptions.
$string['area_professional_strategy_desc'] = 'Clarity on target role, sector and companies';
$string['area_job_monitoring_desc'] = 'Ability to identify relevant job postings (portals, social media, press)';
$string['area_targeted_applications_desc'] = 'Response to open positions';
$string['area_unsolicited_applications_desc'] = 'Initiative towards companies without public openings';
$string['area_direct_company_contact_desc'] = 'Ability to initiate direct contacts with companies';
$string['area_personal_network_desc'] = 'Use of personal and professional network';
$string['area_intermediaries_desc'] = 'Use of URC and employment agencies';

// Activation area objectives (default templates).
$string['area_professional_strategy_obj'] = 'Define a realistic professional profile and target companies';
$string['area_job_monitoring_obj'] = 'Improve ability to identify opportunities';
$string['area_targeted_applications_obj'] = 'Increase number and quality of applications';
$string['area_unsolicited_applications_obj'] = 'Expand opportunities through direct contact';
$string['area_direct_company_contact_obj'] = 'Promote direct contact with the labour market';
$string['area_personal_network_obj'] = 'Activate opportunities through contacts';
$string['area_intermediaries_obj'] = 'Improve use of intermediaries';

// Activation area verification indicators.
$string['area_professional_strategy_verify'] = 'Target company list defined';
$string['area_job_monitoring_verify'] = 'Number of postings analysed';
$string['area_targeted_applications_verify'] = 'Number of applications sent';
$string['area_unsolicited_applications_verify'] = 'Number of companies contacted';
$string['area_direct_company_contact_verify'] = 'Number of direct contacts';
$string['area_personal_network_verify'] = 'Opportunities generated';
$string['area_intermediaries_verify'] = 'Number of contacts activated';

// Activation scale (0-6) - NOT Bloom, measures activation level.
$string['score_0'] = 'Unknown / never used';
$string['score_1'] = 'Very limited knowledge';
$string['score_2'] = 'Occasional use';
$string['score_3'] = 'Minimal but present use';
$string['score_4'] = 'Regular use';
$string['score_5'] = 'Active and structured use';
$string['score_6'] = 'Strategic and autonomous use';

// Roadmap phases.
$string['phase_1'] = 'Analysis and Orientation';
$string['phase_1_desc'] = 'Analyse competencies and define professional target';
$string['phase_2'] = 'Strategy Building';
$string['phase_2_desc'] = 'Assess activation levels, identify development priorities, implement agreed measures';
$string['phase_3'] = 'Job Search Activation';
$string['phase_3_desc'] = 'Structured job search';
$string['phase_4'] = 'Strategy Reinforcement';
$string['phase_4_desc'] = 'Improve application effectiveness and expand opportunities';
$string['phase_5'] = 'Market Contact';
$string['phase_5_desc'] = 'Facilitate access to interviews and concrete opportunities';
$string['phase_6'] = 'Consolidation and Evaluation';
$string['phase_6_desc'] = 'Consolidate results and adapt strategy';

// SIP activation.
$string['activate_sip'] = 'Activate Coaching';
$string['prepare_sip'] = 'Prepare Coaching Plan';
$string['sip_activation_motivation'] = 'Motivation for Coaching activation';
$string['sip_start_date'] = 'Coaching Start Date';
$string['sip_badge'] = 'IC';
$string['sip_color'] = '#0891B2';
$string['plan_status_draft'] = 'Draft';
$string['plan_status_active'] = 'Active';
$string['plan_status_frozen'] = 'Baseline Frozen';
$string['student_visibility'] = 'Student can see own plan';
$string['student_visibility_help'] = 'If enabled, the student can see their action plan, appointments and submit KPIs';

// Coaching diary.
$string['diary_title'] = 'Coaching Diary';
$string['new_entry'] = 'New Entry';
$string['edit_entry'] = 'Edit Entry';
$string['entry_date'] = 'Date';
$string['entry_type'] = 'Type';
$string['entry_duration'] = 'Duration (minutes)';
$string['entry_summary'] = 'Summary';
$string['entry_details'] = 'Details';
$string['entry_next_steps'] = 'Next Steps';
$string['entry_saved'] = 'Diary entry saved successfully';
$string['entry_deleted'] = 'Entry deleted';
$string['entry_not_found'] = 'Entry not found';
$string['confirm_delete_entry'] = 'Are you sure you want to delete this diary entry?';
$string['no_diary_entries'] = 'No diary entries yet';

// Diary entry types.
$string['type_meeting'] = 'Meeting';
$string['type_phone'] = 'Phone Call';
$string['type_email'] = 'Email';
$string['type_workshop'] = 'Workshop';
$string['type_observation'] = 'Observation';
$string['type_other'] = 'Other';

// Appointments.
$string['appointments_title'] = 'Appointments Calendar';
$string['new_appointment'] = 'New Appointment';
$string['edit_appointment'] = 'Edit Appointment';
$string['appointment_date'] = 'Date';
$string['appointment_time'] = 'Time';
$string['appointment_duration'] = 'Duration';
$string['appointment_location'] = 'Location';
$string['appointment_topic'] = 'Topic';
$string['appointment_status'] = 'Status';
$string['appointment_saved'] = 'Appointment saved successfully';
$string['appointment_deleted'] = 'Appointment deleted';
$string['appointment_not_found'] = 'Appointment not found';
$string['confirm_delete_appointment'] = 'Are you sure you want to delete this appointment?';
$string['no_appointments'] = 'No appointments scheduled';

// Appointment statuses.
$string['appt_scheduled'] = 'Scheduled';
$string['appt_confirmed'] = 'Confirmed';
$string['appt_completed'] = 'Completed';
$string['appt_cancelled'] = 'Cancelled';
$string['appt_no_show'] = 'No-Show';

// KPIs.
$string['kpi_title'] = 'Key Performance Indicators';
$string['kpi_applications'] = 'Applications Sent';
$string['kpi_company_contacts'] = 'Company Contacts';
$string['kpi_opportunities'] = 'Opportunities Identified';
$string['kpi_interviews'] = 'Interviews Obtained';
$string['kpi_trials'] = 'Trial Days';
$string['kpi_offers'] = 'Job Offers';
$string['kpi_period'] = 'Period';
$string['kpi_weekly'] = 'This Week';
$string['kpi_total'] = 'Total';
$string['kpi_target'] = 'Target';
$string['kpi_actual'] = 'Actual';
$string['kpi_saved'] = 'KPIs saved successfully';
$string['kpi_trend_up'] = 'Trending up';
$string['kpi_trend_down'] = 'Trending down';
$string['kpi_trend_stable'] = 'Stable';
$string['add_kpi_entry'] = 'Add KPI Entry';
$string['kpi_week_label'] = 'Week {$a}';

// Student self-service area.
$string['student_area_title'] = 'My Coaching Area';
$string['my_action_plan'] = 'My Action Plan';
$string['my_appointments'] = 'My Appointments';
$string['my_kpis'] = 'My KPIs';
$string['my_progress'] = 'My Progress';
$string['next_appointment'] = 'Next Appointment';
$string['no_next_appointment'] = 'No upcoming appointment';
$string['progress_week'] = 'Week {$a->current} of {$a->total}';
$string['progress_percentage'] = '{$a}% completed';
$string['student_notes'] = 'My Notes';
$string['student_notes_placeholder'] = 'Write your notes and reflections here...';
$string['student_notes_saved'] = 'Notes saved successfully';
$string['submit_kpi'] = 'Submit Weekly KPIs';
$string['kpi_submitted'] = 'Weekly KPIs submitted successfully';

// Company registry.
$string['company_registry_title'] = 'Company Registry';
$string['new_company'] = 'New Company';
$string['edit_company'] = 'Edit Company';
$string['company_name'] = 'Company Name';
$string['company_sector'] = 'Sector';
$string['company_address'] = 'Address';
$string['company_city'] = 'City';
$string['company_phone'] = 'Phone';
$string['company_email'] = 'Email';
$string['company_website'] = 'Website';
$string['company_contact_person'] = 'Contact Person';
$string['company_contact_role'] = 'Contact Role';
$string['company_notes'] = 'Notes';
$string['company_last_contact'] = 'Last Contact';
$string['company_status'] = 'Status';
$string['company_saved'] = 'Company saved successfully';
$string['company_deleted'] = 'Company deleted';
$string['company_not_found'] = 'Company not found';
$string['confirm_delete_company'] = 'Are you sure you want to delete this company?';
$string['no_companies'] = 'No companies registered';
$string['search_companies'] = 'Search companies...';
$string['error_already_enrolled'] = 'Student is already enrolled in the Coaching program';

// Company statuses.
$string['company_status_prospect'] = 'Prospect';
$string['company_status_contacted'] = 'Contacted';
$string['company_status_interested'] = 'Interested';
$string['company_status_collaborating'] = 'Collaborating';
$string['company_status_inactive'] = 'Inactive';

// Fields - Personal data.
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['email'] = 'Email';
$string['phone'] = 'Phone';
$string['coach'] = 'Coach';

// Common actions.
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['delete'] = 'Delete';
$string['edit'] = 'Edit';
$string['view'] = 'View';
$string['back'] = 'Back';
$string['search'] = 'Search';
$string['filter'] = 'Filter';
$string['export'] = 'Export';
$string['print'] = 'Print';
$string['close'] = 'Close';
$string['confirm'] = 'Confirm';
$string['reset_filters'] = 'Reset Filters';

// Export.
$string['export_word'] = 'Export to Word';
$string['export_pdf'] = 'Export to PDF';
$string['export_excel'] = 'Export to Excel';
$string['export_success'] = 'Export completed successfully';
$string['export_error'] = 'Error during export';

// Messages.
$string['error_save_failed'] = 'Failed to save data';
$string['error_permission'] = 'You do not have permission to perform this action';
$string['error_invalid_data'] = 'Invalid data provided';
$string['error_missing_required'] = 'Missing required fields: {$a}';
$string['error_student_not_found'] = 'Student not found';
$string['error_date_invalid'] = 'Invalid date format';
$string['error_score_range'] = 'Score must be between 0 and 6';
$string['success_saved'] = 'Data saved successfully';
$string['confirm_action'] = 'Are you sure you want to proceed?';
$string['loading'] = 'Loading...';
$string['no_data'] = 'No data available';
$string['click_to_edit'] = 'Click to edit';

// Inline editing.
$string['field_saved'] = 'Field saved successfully';
$string['field_save_error'] = 'Error saving field';

// Notifications.
$string['notification_appointment_reminder'] = 'Reminder: you have an appointment on {$a->date} at {$a->time}';
$string['notification_kpi_reminder'] = 'Reminder: please submit your weekly KPIs';
$string['notification_plan_update'] = 'Your action plan has been updated by your coach';

// Message providers.
$string['messageprovider:appointment_reminder'] = 'Coaching appointment reminders';
$string['messageprovider:appointment_created'] = 'Coaching new appointment notifications';
$string['messageprovider:plan_updated'] = 'Coaching plan update notifications';
$string['messageprovider:action_reminder'] = 'Coaching action reminders';
$string['messageprovider:student_inactivity'] = 'Coaching student inactivity alerts';
$string['messageprovider:meeting_not_logged'] = 'Coaching unlogged meeting reminders';

// Reports.
$string['report_title'] = 'Coaching Report';
$string['report_summary'] = 'Summary Report';
$string['report_progress'] = 'Progress Report';
$string['report_kpi_chart'] = 'KPI Chart';
$string['report_area_radar'] = 'Activation Areas Radar';
$string['report_generated'] = 'Report generated on {$a}';

// SIP student page strings.
$string['sip_student_initial_level'] = 'Initial Level';
$string['sip_student_current_level'] = 'Current Level';
$string['sip_student_in_development'] = 'In development';
$string['sip_student_objectives_met'] = 'Objectives met';
$string['sip_student_phase'] = 'Phase {$a}';
$string['sip_student_phase_notes_placeholder'] = 'Notes for this phase...';
$string['sip_student_verification'] = 'Verification Indicator';
$string['sip_student_weeks_label'] = 'Weeks {$a}';

// Diary & meetings.
$string['meeting_date'] = 'Meeting Date';
$string['meeting_duration'] = 'Duration (min)';
$string['meeting_modality'] = 'Modality';
$string['meeting_modality_presence'] = 'In Person';
$string['meeting_modality_remote'] = 'Remote';
$string['meeting_modality_phone'] = 'Phone Call';
$string['meeting_modality_email'] = 'Email';
$string['meeting_summary'] = 'Summary';
$string['meeting_notes'] = 'Coach Notes';
$string['meeting_sip_week'] = 'Coaching Week';
$string['meeting_saved'] = 'Meeting saved successfully';
$string['meeting_deleted'] = 'Meeting deleted';
$string['new_meeting'] = 'Log New Meeting';
$string['no_meetings'] = 'No meetings logged yet';
$string['meeting_actions_title'] = 'Actions Assigned';
$string['meeting_previous_actions'] = 'Previous Actions Status';

// Actions.
$string['action_description'] = 'Action Description';
$string['action_deadline'] = 'Deadline';
$string['action_status'] = 'Status';
$string['action_status_pending'] = 'Pending';
$string['action_status_in_progress'] = 'In Progress';
$string['action_status_completed'] = 'Completed';
$string['action_status_not_done'] = 'Not Done';
$string['action_saved'] = 'Action saved';
$string['add_action'] = 'Add Action';
$string['no_pending_actions'] = 'No pending actions';

// Appointment calendar.
$string['appointment_modality_presence'] = 'In Person';
$string['appointment_modality_remote'] = 'Remote';
$string['appointment_modality_phone'] = 'Phone';
$string['appointment_saved_notification'] = 'Appointment created. Student will be notified.';
$string['appointment_confirm_delete'] = 'Delete this appointment?';
$string['today'] = 'Today';
$string['this_week'] = 'This Week';
$string['no_upcoming'] = 'No upcoming appointments';
$string['past_appointments'] = 'Past Appointments';

// Eligibility - Griglia Valutazione PCI (6 criteria, scale 1-5).
$string['eligibility_title'] = 'PCI Evaluation Grid';
$string['eligibility_section'] = 'PCI Evaluation';
$string['eligibility_criterion_motivazione'] = 'Motivation';
$string['eligibility_criterion_chiarezza'] = 'Objective Clarity';
$string['eligibility_criterion_occupabilita'] = 'Employability';
$string['eligibility_criterion_autonomia'] = 'Autonomy';
$string['eligibility_criterion_bisogno_coaching'] = 'Coaching Need';
$string['eligibility_criterion_comportamento'] = 'Behaviour';
$string['eligibility_desc_motivazione_1'] = 'passive / barely involved';
$string['eligibility_desc_motivazione_3'] = 'participates but without initiative';
$string['eligibility_desc_motivazione_5'] = 'proactive, involved, result-oriented';
$string['eligibility_desc_chiarezza_1'] = 'no objective';
$string['eligibility_desc_chiarezza_3'] = 'generic objective';
$string['eligibility_desc_chiarezza_5'] = 'clear and realistic objective';
$string['eligibility_desc_occupabilita_1'] = 'very low';
$string['eligibility_desc_occupabilita_3'] = 'medium';
$string['eligibility_desc_occupabilita_5'] = 'high (quickly employable profile)';
$string['eligibility_desc_autonomia_1'] = 'totally dependent';
$string['eligibility_desc_autonomia_3'] = 'partially autonomous';
$string['eligibility_desc_autonomia_5'] = 'autonomous and organised';
$string['eligibility_desc_bisogno_coaching_1'] = 'not necessary';
$string['eligibility_desc_bisogno_coaching_3'] = 'useful but not essential';
$string['eligibility_desc_bisogno_coaching_5'] = 'highly necessary for unblocking';
$string['eligibility_desc_comportamento_1'] = 'absences / poor commitment';
$string['eligibility_desc_comportamento_3'] = 'adequate';
$string['eligibility_desc_comportamento_5'] = 'excellent (punctual, active, collaborative)';
$string['eligibility_totale'] = 'Total';
$string['eligibility_decisione'] = 'Decision';
$string['eligibility_decisione_idoneo'] = 'Suitable';
$string['eligibility_decisione_non_idoneo'] = 'Not Suitable';
$string['eligibility_decisione_pending'] = 'Pending';
$string['eligibility_note'] = 'Notes';
$string['eligibility_recommendation'] = 'Coach recommendation (advisory)';
$string['eligibility_recommend_activate'] = 'Activate Coaching';
$string['eligibility_recommend_not_activate'] = 'Do not activate';
$string['eligibility_recommend_refer'] = 'Refer to other measure';
$string['eligibility_referral_detail'] = 'Referral to';
$string['eligibility_scale_hint'] = 'Rate each criterion from 1 (lowest) to 5 (highest)';
$string['eligibility_activation_section'] = 'Coaching Activation';
$string['eligibility_save_only'] = 'Save assessment only';
$string['eligibility_save_and_activate'] = 'Save and Activate Coaching';
$string['eligibility_saved'] = 'PCI evaluation saved';
$string['eligibility_summary'] = 'PCI Evaluation';
$string['eligibility_approved'] = 'Approved by secretary';
$string['eligibility_approved_msg'] = 'Eligibility approved';

// Closure.
$string['closure_title'] = 'Coaching Closure';
$string['closure_warning'] = 'Coaching Closure';
$string['closure_outcome'] = 'Final outcome';
$string['closure_outcome_hired'] = 'Hired';
$string['closure_outcome_stage'] = 'Internship/Stage';
$string['closure_outcome_training'] = 'Further Training';
$string['closure_outcome_interrupted'] = 'Interrupted';
$string['closure_outcome_not_suitable'] = 'Not suitable';
$string['closure_outcome_none'] = 'No outcome';
$string['closure_company'] = 'Company (if applicable)';
$string['closure_company_placeholder'] = 'Company name...';
$string['closure_date'] = 'Outcome date';
$string['closure_percentage'] = 'Employment % (if hired)';
$string['closure_interruption_reason'] = 'Interruption reason';
$string['closure_interruption_placeholder'] = 'Describe the reason for interruption...';
$string['closure_referral'] = 'Referral to another measure';
$string['closure_referral_placeholder'] = 'Specify the referral measure...';
$string['closure_coach_evaluation'] = 'Coach final evaluation';
$string['closure_coach_evaluation_placeholder'] = 'Overall evaluation of the student\'s Coaching pathway (min. 100 characters)...';
$string['closure_next_steps'] = 'Next steps';
$string['closure_next_steps_placeholder'] = 'Recommendations for continuation...';
$string['closure_validation'] = 'Validation';
$string['closure_confirm_btn'] = 'Complete Coaching';
$string['closure_cancel'] = 'Cancel';
$string['closure_complete_sip'] = 'Complete Coaching';
$string['closure_saved'] = 'Coaching closure completed';
$string['closure_select_outcome'] = 'Select outcome...';

// Quality indicators.
$string['quality_baseline_levels'] = 'Baseline levels';
$string['quality_baseline_complete'] = '{$a}/7 levels set';
$string['quality_meetings_count'] = 'Meetings logged';
$string['quality_meetings_info'] = '{$a} meetings (min. 3)';
$string['quality_kpi_entries'] = 'KPI entries';
$string['quality_kpi_info'] = '{$a} KPI entries logged';
$string['quality_meeting_frequency'] = 'Meeting frequency';
$string['quality_frequency_info'] = '{$a->ratio} meetings/week';
$string['quality_check_ok'] = 'Complete';
$string['quality_check_partial'] = 'Partial';
$string['quality_check_missing'] = 'Missing';

// Eligibility assessment - legacy/compatibility strings (kept for reports).
$string['eligibility_referral'] = 'Referral to';
$string['eligibility_recommend_not'] = 'Do not activate';
$string['level_high'] = 'High';
$string['level_medium'] = 'Medium';
$string['level_low'] = 'Low';
$string['sector_yes'] = 'Yes';
$string['sector_partial'] = 'Partial';
$string['sector_no'] = 'No';

// Closure / Outcome - additional strings.
$string['closure_outcome_tryout'] = 'Trial day / Tryout';
$string['closure_outcome_intermediate'] = 'Intermediate earning';
$string['closure_outcome_not_placed'] = 'Not placed but more activated';
$string['closure_complete'] = 'Complete Coaching';
$string['closure_validation_error'] = 'Cannot close Coaching. Missing: {$a}';
$string['closure_missing_levels'] = 'final activation levels for all 7 areas';
$string['closure_missing_meetings'] = 'minimum 3 registered meetings';
$string['closure_missing_outcome'] = 'final outcome selection';
$string['closure_missing_evaluation'] = 'final coach evaluation';

// Quality indicators - additional strings.
$string['quality_complete'] = 'Complete';
$string['quality_partial'] = 'Partial';
$string['quality_missing'] = 'Missing';
$string['quality_baseline_incomplete'] = 'Baseline levels incomplete';
$string['quality_meetings_low'] = 'Few meetings registered';
$string['quality_no_kpi'] = 'No KPI entries';
$string['quality_meetings_per_week'] = '{$a} meetings/week';
$string['quality_alert_no_meeting'] = 'No meeting registered this week for {$a}';

// Aggregate statistics.
$string['stats_title'] = 'Coaching Statistics';
$string['stats_activated'] = 'Coaching Activated';
$string['stats_completed'] = 'Completed';
$string['stats_interrupted'] = 'Interrupted';
$string['stats_completion_rate'] = 'Completion Rate';
$string['stats_insertion_rate'] = 'Insertion Rate';
$string['stats_outcome_distribution'] = 'Outcome Distribution';
$string['stats_level_evolution'] = 'Average Activation Level Evolution';
$string['stats_coach_performance'] = 'Coach Performance';
$string['stats_no_completed'] = 'No completed Coaching in the selected period';
$string['stats_no_coach_data'] = 'No coach data in the selected period';

// Data quality - quality_checker.
$string['quality_missing_enrollment'] = 'Enrollment not found';
$string['quality_missing_final_levels'] = 'Final levels incomplete (all 7 areas required)';
$string['quality_missing_meetings'] = 'Insufficient meetings (minimum 3 required)';
$string['quality_missing_outcome'] = 'Outcome not selected';
$string['quality_missing_evaluation'] = 'Coach final evaluation missing';

// Message provider: meeting frequency alert.
$string['messageprovider:meeting_frequency_alert'] = 'Coaching meeting frequency alerts';

// Competency assessment results (Gap G integration).
$string['assessment_results'] = 'Competency Assessment Results (6 weeks)';
$string['assessment_sector'] = 'Detected Sector';
$string['assessment_quiz_avg'] = 'Quiz Average';
$string['assessment_autoval_avg'] = 'Self-Assessment Average';
$string['assessment_coach_avg'] = 'Coach Evaluation Average';
$string['assessment_quiz_count'] = 'Quizzes Completed';
$string['assessment_comp_count'] = 'Competencies Assessed';
$string['assessment_baseline_note'] = 'Data from the FTM Competency Assessment system. These values represent the starting baseline for the Coaching pathway.';

// SIP Final Report export.
$string['report_sip_title'] = 'Individualized Coaching Report';
$string['report_section_pci'] = 'PCI Data';
$string['report_section_eligibility'] = 'Eligibility Assessment';
$string['report_section_baseline'] = 'Baseline - Initial Levels';
$string['report_section_final'] = 'Final Levels and Progress';
$string['report_section_meetings'] = 'Meetings Summary';
$string['report_section_kpi'] = 'Key Performance Indicators';
$string['report_section_outcome'] = 'Final Outcome';
$string['report_section_evaluation'] = 'Coach Final Evaluation';
$string['report_total_meetings'] = 'Total meetings';
$string['report_total_hours'] = 'Total hours';
$string['report_avg_frequency'] = 'Average frequency';
$string['report_generated_by'] = 'Report generated on {$a->date} by {$a->user}';
$string['report_download'] = 'Download Report';
$string['report_duration_weeks'] = 'Effective duration (weeks)';
$string['report_level_description'] = 'Level Description';
$string['report_initial'] = 'Initial';
$string['report_final'] = 'Final';
$string['report_meetings_per_week'] = 'meetings/week';
$string['report_most_frequent_modality'] = 'Most frequent modality';
$string['report_with_interview'] = 'with interview';
$string['report_positive_outcome'] = 'positive outcome';
$string['report_completed'] = 'completed';
$string['report_position'] = 'Position';
$string['report_type'] = 'Type';
$string['report_contact_type'] = 'Contact Type';
$string['report_contact_person'] = 'Contact Person';
$string['report_outcome_col'] = 'Outcome';

// PCI Evaluation Grid - 6 numeric criteria (1-5).
$string['eligibility_grid_title'] = 'PCI Evaluation Grid';
$string['eligibility_grid_instruction'] = 'Rate each criterion from 1 (minimum) to 5 (maximum)';
$string['eligibility_criterion_motivazione'] = 'Motivation';
$string['eligibility_criterion_chiarezza'] = 'Objective Clarity';
$string['eligibility_criterion_occupabilita'] = 'Employability';
$string['eligibility_criterion_autonomia'] = 'Autonomy';
$string['eligibility_criterion_bisogno_coaching'] = 'Coaching Need';
$string['eligibility_criterion_comportamento'] = 'Behaviour';
$string['eligibility_desc_motivazione_1'] = 'Passive / disengaged';
$string['eligibility_desc_motivazione_3'] = 'Participates but without initiative';
$string['eligibility_desc_motivazione_5'] = 'Proactive, engaged, result-oriented';
$string['eligibility_desc_chiarezza_1'] = 'No objective';
$string['eligibility_desc_chiarezza_3'] = 'Generic objective';
$string['eligibility_desc_chiarezza_5'] = 'Clear and realistic objective';
$string['eligibility_desc_occupabilita_1'] = 'Very low';
$string['eligibility_desc_occupabilita_3'] = 'Medium';
$string['eligibility_desc_occupabilita_5'] = 'High (immediately marketable profile)';
$string['eligibility_desc_autonomia_1'] = 'Totally dependent';
$string['eligibility_desc_autonomia_3'] = 'Partially autonomous';
$string['eligibility_desc_autonomia_5'] = 'Autonomous and organised';
$string['eligibility_desc_bisogno_coaching_1'] = 'Not needed';
$string['eligibility_desc_bisogno_coaching_3'] = 'Useful but not essential';
$string['eligibility_desc_bisogno_coaching_5'] = 'Highly needed for unblocking';
$string['eligibility_desc_comportamento_1'] = 'Absences / poor commitment';
$string['eligibility_desc_comportamento_3'] = 'Adequate';
$string['eligibility_desc_comportamento_5'] = 'Excellent (punctual, active, collaborative)';
$string['eligibility_total'] = 'Total';
$string['eligibility_decisione'] = 'Decision';
$string['eligibility_decisione_idoneo'] = 'Eligible';
$string['eligibility_decisione_non_idoneo'] = 'Not Eligible';
$string['eligibility_decisione_pending'] = 'Pending';
$string['eligibility_note'] = 'Notes';
$string['eligibility_note_placeholder'] = 'Additional notes on the assessment...';
$string['eligibility_activation_title'] = 'Coaching Activation (if eligible)';
$string['eligibility_motivation_detail'] = 'Detailed motivation';
$string['eligibility_motivation_placeholder'] = 'Why this person can benefit from Coaching...';
$string['eligibility_save_assessment'] = 'Save assessment';
$string['eligibility_save_and_activate'] = 'Save and Activate Coaching';
$string['eligibility_report_criterion'] = 'Criterion';
$string['eligibility_report_score'] = 'Score';

// LADI indemnity.
$string['ladi_indemnity'] = 'Remaining LADI daily indemnities';
$string['ladi_indemnity_help'] = 'Number of remaining LADI daily indemnities. Required to activate Individualized Coaching.';
$string['ladi_indemnity_required'] = 'LADI indemnities are required to activate Individualized Coaching';
$string['ladi_insufficient'] = 'Insufficient LADI indemnities';
