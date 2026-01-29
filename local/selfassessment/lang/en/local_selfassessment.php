<?php
// ============================================
// Self Assessment - English Strings
// ============================================

defined('MOODLE_INTERNAL') || die();

// Plugin info
$string['pluginname'] = 'Self Assessment';
$string['selfassessment'] = 'Self Assessment';
$string['selfassessment:complete'] = 'Complete self assessment';
$string['selfassessment:view'] = 'View student self assessments';
$string['selfassessment:manage'] = 'Manage student enablement';
$string['selfassessment:sendreminder'] = 'Send reminders to students';

// Navigation
$string['myassessment'] = 'My Self Assessment';
$string['manageassessments'] = 'Manage Assessments';
$string['dashboard'] = 'Self Assessment Dashboard';

// Page titles
$string['compile_title'] = 'Self Assessment - Competencies';
$string['manage_title'] = 'Manage Self Assessments';
$string['index_title'] = 'Self Assessment Dashboard';

// Instructions
$string['instructions'] = 'Rate your skill level for each competency area using the Bloom scale (1-6).';
$string['instructions_detail'] = 'Be honest in your self-assessment. This will help your coach understand where you need support.';

// Bloom levels
$string['level1'] = 'REMEMBER';
$string['level1_desc'] = 'I can recall basic information and terminology';
$string['level2'] = 'UNDERSTAND';
$string['level2_desc'] = 'I can explain concepts in my own words';
$string['level3'] = 'APPLY';
$string['level3_desc'] = 'I can use knowledge in standard situations';
$string['level4'] = 'ANALYZE';
$string['level4_desc'] = 'I can break down problems and identify causes';
$string['level5'] = 'EVALUATE';
$string['level5_desc'] = 'I can judge quality and choose the best solution';
$string['level6'] = 'CREATE';
$string['level6_desc'] = 'I can design new solutions or improve existing ones';

// Status
$string['status_enabled'] = 'Enabled';
$string['status_disabled'] = 'Disabled';
$string['status_completed'] = 'Completed';
$string['status_pending'] = 'Pending';
$string['status_never'] = 'Never compiled';

// Actions
$string['save'] = 'Save Self Assessment';
$string['saving'] = 'Saving...';
$string['saved'] = 'Self assessment saved successfully!';
$string['enable'] = 'Enable';
$string['disable'] = 'Disable';
$string['send_reminder'] = 'Send Reminder';
$string['view_detail'] = 'View Detail';

// Messages
$string['save_success'] = 'Your self assessment has been saved successfully.';
$string['save_error'] = 'Error saving self assessment. Please try again.';
$string['disabled_message'] = 'Self assessment has been disabled for your account. Contact your coach for more information.';
$string['already_completed'] = 'You have already completed your self assessment. You can update it at any time.';
$string['reminder_sent'] = 'Reminder sent successfully to {$a} student(s).';
$string['status_changed'] = 'Status changed successfully.';

// Dashboard
$string['total_students'] = 'Total Students';
$string['completed_count'] = 'Completed';
$string['pending_count'] = 'Pending';
$string['disabled_count'] = 'Disabled';
$string['last_update'] = 'Last Update';
$string['no_students'] = 'No students found.';

// Filters
$string['filter_all'] = 'All';
$string['filter_completed'] = 'Completed';
$string['filter_pending'] = 'Pending';
$string['filter_disabled'] = 'Disabled';

// Table headers
$string['student'] = 'Student';
$string['status'] = 'Status';
$string['completed_date'] = 'Completed';
$string['actions'] = 'Actions';

// Confirmation
$string['confirm_disable'] = 'Are you sure you want to disable self assessment for this student?';
$string['confirm_enable'] = 'Are you sure you want to enable self assessment for this student?';

// Errors
$string['error_notfound'] = 'Student not found.';
$string['error_permission'] = 'You do not have permission to perform this action.';
$string['error_disabled'] = 'Self assessment is disabled for your account.';

// Progress
$string['progress'] = 'Progress';
$string['areas_completed'] = '{$a->completed} of {$a->total} areas rated';
$string['completion_percent'] = '{$a}% complete';

// Areas (for display)
$string['area_manutenzione_auto'] = 'Car Maintenance';
$string['area_assemblaggio'] = 'Assembly';
$string['area_automazione'] = 'Automation';
$string['area_cnc'] = 'CNC Control';
$string['area_disegno'] = 'Technical Drawing';
$string['area_misurazione'] = 'Measurement';
$string['area_pianificazione'] = 'Planning';
$string['area_sicurezza'] = 'Safety and Quality';

// Message providers
$string['messageprovider:reminder'] = 'Self assessment reminder from coach';
$string['messageprovider:assignment'] = 'New competencies assigned for self assessment';

// Notification messages
$string['notification_assignment_subject'] = 'New competencies to self-assess';
$string['notification_assignment_body'] = 'Hello {$a->fullname},

After completing the quiz "{$a->quizname}", you have been assigned {$a->count} new competencies to self-assess.

Access your self assessment area to complete them:
{$a->url}

This self-assessment will help your coach understand where you need support.';
$string['notification_assignment_small'] = '{$a->count} new competencies to self-assess';

$string['notification_reminder_subject'] = 'Reminder: Complete your self assessment';
$string['notification_reminder_body'] = 'Hello {$a->fullname},

Your coach {$a->coachname} reminds you to complete your competency self-assessment.

{$a->message}

Click here to complete it:
{$a->url}';
$string['notification_reminder_small'] = 'Self assessment reminder from coach';

// No competencies
$string['no_competencies'] = 'No competencies to self-assess';
$string['no_competencies_desc'] = 'You currently have no competencies assigned for self-assessment. Complete a quiz with questions linked to competencies to unlock this feature.';

// Success message after quiz completion
$string['competencies_assigned_success'] = 'Great job! {$a} new competencies have been assigned for your self-assessment. Keep up the good work!';

// Invasive reminder strings
$string['reminder_banner_text'] = 'You have competencies to self-assess!';
$string['reminder_banner_button'] = 'Complete Now';
$string['reminder_modal_title'] = 'Self-Assessment Pending';
$string['reminder_modal_text'] = 'Before continuing, complete your competency self-assessment. This will help your coach understand where you need support.';
$string['reminder_modal_button'] = 'Complete Self-Assessment';
$string['reminder_modal_skip'] = 'Continue Without Completing';
$string['reminder_skip_code_prompt'] = 'Do you have a bypass code?';
$string['reminder_skip_temp_success'] = 'Temporary skip activated';
$string['reminder_skip_perm_success'] = 'Permanent skip saved - you will not see these reminders again';
$string['reminder_skip_error'] = 'Invalid code';
$string['quiz_blocked_title'] = 'Quiz Blocked';
$string['quiz_blocked_message'] = 'To continue with quizzes, you must first complete the self-assessment of your already assigned competencies.';
