<?php
/**
 * English strings - Competency Manager
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Competency Manager';
$string['dashboard'] = 'Dashboard';
$string['createquiz'] = 'Create Quiz';
$string['reports'] = 'Reports';
$string['studentreport'] = 'Student Report';
$string['authorize'] = 'Manage Authorizations';
$string['export'] = 'Export Data';
$string['diagnostics'] = 'Diagnostics';
$string['students'] = 'Students';
$string['templates'] = 'Templates';
$string['dashboard'] = 'Dashboard';
$string['reports'] = 'Reports';

// Sector Admin
$string['sector_admin'] = 'Student Sector Management';
$string['sector'] = 'Sector';
$string['all_sectors'] = 'All sectors';
$string['all_courses'] = 'All courses';
$string['all_cohorts'] = 'All cohorts';
$string['cohort'] = 'Cohort/Group';
$string['search_student'] = 'Search student';
$string['search_placeholder'] = 'Name, surname or email...';
$string['date_from'] = 'From';
$string['date_to'] = 'To';
$string['date_start'] = 'Start Date';
$string['student'] = 'Student';
$string['primary_sector'] = 'Primary Sector';
$string['detected_sectors'] = 'Detected Sectors';
$string['no_primary_sector'] = 'No primary sector';
$string['students_found'] = 'Found {$a} students';
$string['no_students_found'] = 'No students found';
$string['edit_sector'] = 'Edit Sector';
$string['sector_saved'] = 'Sector saved successfully';
$string['sector_removed'] = 'Primary sector removed';
$string['invalid_user'] = 'Invalid user';
$string['invalid_sector'] = 'Invalid sector';
$string['quiz_count'] = '{$a} quizzes completed';

// Color legend
$string['legend'] = 'Legend';
$string['color_green'] = '< 2 weeks (new entry)';
$string['color_yellow'] = '2-4 weeks (in progress)';
$string['color_orange'] = '4-6 weeks (near end)';
$string['color_red'] = '> 6 weeks (extended/delayed)';
$string['color_gray'] = 'Date not set';

// Capabilities
$string['competencymanager:view'] = 'View competency reports';
$string['competencymanager:manage'] = 'Manage competencies';
$string['competencymanager:managecoaching'] = 'Manage student coaching';
$string['competencymanager:assigncoach'] = 'Assign students to coaches';
$string['competencymanager:managesectors'] = 'Manage student sectors';
$string['competencymanager:evaluate'] = 'Evaluate students (coach evaluation)';
$string['competencymanager:viewallevaluations'] = 'View all coach evaluations';
$string['competencymanager:editallevaluations'] = 'Edit all coach evaluations';
$string['competencymanager:authorizestudentview'] = 'Authorize student to view evaluation';

// ============================================================================
// COACH EVALUATION - Valutazione Formatore
// ============================================================================

// Page titles and headers
$string['coach_evaluation'] = 'Coach Evaluation';
$string['coach_evaluation_title'] = 'Coach Competency Evaluation';
$string['evaluation_for'] = 'Evaluation for {$a}';
$string['new_evaluation'] = 'New Evaluation';
$string['edit_evaluation'] = 'Edit Evaluation';
$string['view_evaluation'] = 'View Evaluation';
$string['evaluation_history'] = 'Evaluation History';
$string['my_evaluations'] = 'My Evaluations';

// Status
$string['status_draft'] = 'Draft';
$string['status_completed'] = 'Completed';
$string['status_signed'] = 'Signed (Locked)';
$string['evaluation_draft'] = 'This evaluation is still a draft and can be modified.';
$string['evaluation_completed'] = 'This evaluation has been completed.';
$string['evaluation_signed'] = 'This evaluation has been signed and cannot be modified.';

// Bloom Scale
$string['bloom_scale'] = 'Bloom Scale';
$string['bloom_not_observed'] = 'Not Observed - No opportunity to assess this competency';
$string['bloom_1_remember'] = 'Remember - Recalls facts, terms, basic concepts';
$string['bloom_2_understand'] = 'Understand - Explains ideas or concepts in own words';
$string['bloom_3_apply'] = 'Apply - Uses information in new situations';
$string['bloom_4_analyze'] = 'Analyze - Distinguishes between different parts, identifies patterns';
$string['bloom_5_evaluate'] = 'Evaluate - Justifies decisions, makes judgments';
$string['bloom_6_create'] = 'Create - Produces new work, develops original solutions';

// Rating labels
$string['rating_no'] = 'N/O';
$string['rating_1'] = '1 - Remember';
$string['rating_2'] = '2 - Understand';
$string['rating_3'] = '3 - Apply';
$string['rating_4'] = '4 - Analyze';
$string['rating_5'] = '5 - Evaluate';
$string['rating_6'] = '6 - Create';
$string['select_rating'] = 'Select rating';

// Form and interface
$string['competency_area'] = 'Area {$a}';
$string['expand_area'] = 'Expand area {$a}';
$string['collapse_area'] = 'Collapse area {$a}';
$string['competency'] = 'Competency';
$string['coach_rating'] = 'Coach Rating';
$string['notes'] = 'Notes';
$string['notes_placeholder'] = 'Optional notes for this competency...';
$string['general_notes'] = 'General Notes';
$string['general_notes_help'] = 'Add general observations about the student\'s overall performance.';
$string['is_final_week'] = 'Final Week Evaluation';
$string['is_final_week_help'] = 'Mark this as the mandatory end-of-journey evaluation.';

// Actions
$string['save_draft'] = 'Save as Draft';
$string['save_and_complete'] = 'Save and Complete';
$string['sign_evaluation'] = 'Sign Evaluation';
$string['sign_confirm'] = 'Are you sure? A signed evaluation cannot be modified.';
$string['delete_evaluation'] = 'Delete Evaluation';
$string['delete_confirm'] = 'Are you sure you want to delete this evaluation? This action cannot be undone.';
$string['authorize_student'] = 'Authorize Student View';
$string['revoke_student'] = 'Revoke Student View';

// Messages
$string['evaluation_saved'] = 'Evaluation saved successfully.';
$string['evaluation_completed_msg'] = 'Evaluation marked as completed.';
$string['evaluation_signed_msg'] = 'Evaluation has been signed and locked.';
$string['evaluation_deleted'] = 'Evaluation deleted.';
$string['student_authorized'] = 'Student has been authorized to view this evaluation.';
$string['student_revoked'] = 'Student view authorization has been revoked.';
$string['cannot_edit_signed'] = 'This evaluation is signed and cannot be modified.';
$string['no_permission'] = 'You do not have permission to perform this action.';

// Statistics
$string['competencies_rated'] = '{$a->rated} of {$a->total} competencies rated';
$string['not_observed_count'] = '{$a} marked as N/O (Not Observed)';
$string['average_rating'] = 'Average rating: {$a}';
$string['no_ratings'] = 'No ratings yet';

// History
$string['history_created'] = 'Evaluation created';
$string['history_updated'] = 'Field "{$a->field}" changed';
$string['history_deleted'] = 'Evaluation deleted';
$string['changed_by'] = 'Changed by {$a}';
$string['on_date'] = 'on {$a}';

// Report integration
$string['coach_evaluation_data'] = 'Coach Evaluation';
$string['show_coach_evaluation'] = 'Show Coach Evaluation';
$string['hide_coach_evaluation'] = 'Hide Coach Evaluation';
$string['no_coach_evaluation'] = 'No coach evaluation available for this student/sector.';
$string['evaluation_date'] = 'Evaluation date: {$a}';
$string['evaluated_by'] = 'Evaluated by: {$a}';

// Validation
$string['select_sector_first'] = 'Please select a sector first.';
$string['no_competencies_found'] = 'No competencies found for this sector.';
$string['missing_required_ratings'] = 'Please rate all competencies before completing the evaluation.';
