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
 * English language strings for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'FTM Scheduler';
$string['ftm_scheduler'] = 'FTM Scheduler';

// Navigation
$string['dashboard'] = 'Dashboard';
$string['calendario'] = 'Calendar';
$string['gruppi'] = 'Groups';
$string['attivita'] = 'Activities';
$string['aule'] = 'Rooms';
$string['atelier'] = 'Workshops';

// Page titles
$string['scheduler_title'] = '📅 FTM Scheduler';
$string['dashboard_segreteria'] = 'Secretary Dashboard';

// Groups
$string['gruppo'] = 'Group';
$string['gruppi_attivi'] = 'Active Groups';
$string['nuovo_gruppo'] = 'New Group';
$string['crea_gruppo'] = 'Create Group and Generate Activities';
$string['modifica_gruppo'] = 'Edit Group';
$string['elimina_gruppo'] = 'Delete Group';
$string['gruppo_giallo'] = 'Yellow Group';
$string['gruppo_grigio'] = 'Gray Group';
$string['gruppo_rosso'] = 'Red Group';
$string['gruppo_marrone'] = 'Brown Group';
$string['gruppo_viola'] = 'Purple Group';

// Colors
$string['colore'] = 'Color';
$string['giallo'] = 'Yellow';
$string['grigio'] = 'Gray';
$string['rosso'] = 'Red';
$string['marrone'] = 'Brown';
$string['viola'] = 'Purple';
$string['seleziona_colore'] = 'Select Color';

// Status
$string['status'] = 'Status';
$string['status_planning'] = 'Planning';
$string['status_active'] = 'Active';
$string['status_completed'] = 'Completed';
$string['attivo'] = 'Active';
$string['in_arrivo'] = 'Upcoming';
$string['completato'] = 'Completed';

// Dates and times
$string['data_inizio'] = 'Start Date';
$string['data_fine'] = 'End Date';
$string['data_fine_prevista'] = 'Expected End';
$string['orario'] = 'Time';
$string['mattina'] = 'Morning';
$string['pomeriggio'] = 'Afternoon';
$string['settimana'] = 'Week';
$string['settimana_corrente'] = 'Current week';
$string['settimana_num'] = 'Week {$a}';
$string['kw'] = 'CW';

// Students
$string['studenti'] = 'Students';
$string['studenti_assegnati'] = 'Assigned students';
$string['studenti_iscritti'] = 'Enrolled Students';
$string['studenti_da_assegnare'] = 'Students to Assign';
$string['studenti_selezionati'] = '{$a} students selected';
$string['iscritti'] = 'Enrolled';
$string['altri_studenti'] = '... {$a} more students';

// Activities
$string['attivita'] = 'Activities';
$string['nuova_attivita'] = 'New Activity';
$string['tipo_attivita'] = 'Type';
$string['attivita_settimana'] = 'Activities this week';
$string['week1'] = 'Week 1';
$string['week2'] = 'Week 2';
$string['week1_auto'] = 'Week 1 (Automatic)';

// Rooms
$string['aula'] = 'Room';
$string['aule'] = 'Rooms';
$string['aule_utilizzate'] = 'Rooms used';
$string['postazioni'] = 'workstations';
$string['laboratorio'] = 'Laboratory';
$string['teoria'] = 'Theory';
$string['aula_libera'] = 'Available';
$string['aula_occupata'] = 'Occupied';

// Coach/Teacher
$string['docente'] = 'Teacher';
$string['coach'] = 'Coach';
$string['responsabile'] = 'Responsible';

// External bookings
$string['prenota_aula_esterno'] = 'Book Room (External)';
$string['progetto_esterno'] = 'External Project';
$string['progetti_esterni'] = 'External Projects';
$string['nome_progetto'] = 'Project Name';
$string['fascia_oraria'] = 'Time Slot';
$string['tutto_giorno'] = 'All day';
$string['solo_mattina'] = 'Morning only';
$string['solo_pomeriggio'] = 'Afternoon only';

// Actions
$string['visualizza'] = 'View';
$string['modifica'] = 'Edit';
$string['elimina'] = 'Delete';
$string['salva'] = 'Save';
$string['annulla'] = 'Cancel';
$string['chiudi'] = 'Close';
$string['dettagli'] = 'Details';
$string['export_excel'] = 'Export Excel';

// Filters
$string['tutti'] = 'All';
$string['tutti_gruppi'] = 'All groups';
$string['tutte_aule'] = 'All rooms';
$string['tutti_tipi'] = 'All types';

// Stats
$string['stats_gruppi_attivi'] = 'Active Groups';
$string['stats_studenti'] = 'Students in Program';
$string['stats_attivita'] = 'Weekly Activities';
$string['stats_aule'] = 'Rooms Used';
$string['stats_conflitti'] = 'Conflicts';

// Notifications
$string['notifica'] = 'Notification';
$string['notifica_inviata'] = 'Sent';
$string['notifica_non_inviata'] = 'Not sent';

// Atelier
$string['catalogo_atelier'] = 'Workshop Catalog';
$string['atelier_disponibili'] = 'Workshops available from Week 3';
$string['settimana_tipica'] = 'Typical Week';
$string['max_partecipanti'] = 'Max Participants';
$string['obbligatorio'] = 'Mandatory';
$string['bilancio_fine_misura'] = 'End of program assessment';

// Week 2 choices
$string['scelte_settimana2'] = 'Week 2 Choices';
$string['da_completare'] = 'to complete';
$string['test_teorico'] = 'Theory Test';
$string['lab_pratico'] = 'Practical Lab';
$string['seleziona_test'] = '-- Select Test --';
$string['seleziona_lab'] = '-- Select Lab --';

// Messages
$string['gruppo_creato'] = 'Group created successfully. Week 1 activities have been automatically generated.';
$string['attivita_create'] = 'Week 1 activities have been automatically generated.';
$string['info_creazione_gruppo'] = 'What happens when you create the group:';
$string['info_creazione_1'] = 'Week 1 activities are created automatically';
$string['info_creazione_2'] = 'All students are enrolled in activities';
$string['info_creazione_3'] = 'Email + calendar notifications sent to students';

// Legend
$string['legenda'] = 'Legend';

// Progress
$string['progresso'] = 'Progress';

// Remote
$string['remoto'] = 'REMOTE';

// Errors
$string['error_no_permission'] = 'You do not have permission to access this page.';
$string['error_group_not_found'] = 'Group not found.';
$string['error_activity_not_found'] = 'Activity not found.';

// Capabilities
$string['ftm_scheduler:view'] = 'View FTM Scheduler';
$string['ftm_scheduler:manage'] = 'Manage FTM Scheduler';
$string['ftm_scheduler:managegroups'] = 'Manage groups';
$string['ftm_scheduler:manageactivities'] = 'Manage activities';
$string['ftm_scheduler:managerooms'] = 'Manage rooms';
$string['ftm_scheduler:enrollstudents'] = 'Enroll students';
$string['ftm_scheduler:markattendance'] = 'Mark student attendance';

// Attendance
$string['attendance'] = 'Attendance';
$string['attendance_title'] = 'Attendance Register';
$string['attendance_date'] = 'Date';
$string['attendance_student'] = 'Student';
$string['attendance_status'] = 'Status';
$string['attendance_actions'] = 'Actions';
$string['attendance_present'] = 'Present';
$string['attendance_absent'] = 'Absent';
$string['attendance_pending'] = 'Pending';
$string['attendance_marked_by'] = 'Marked by';
$string['attendance_marked_at'] = 'Marked at';
$string['mark_present'] = 'Mark Present';
$string['mark_absent'] = 'Mark Absent';
$string['mark_all_present'] = 'Mark All Present';
$string['student'] = 'Student';
$string['activity'] = 'Activity';
$string['date'] = 'Date';
$string['time'] = 'Time';
$string['room'] = 'Room';
$string['no_activities_today'] = 'No activities scheduled for this date';
$string['select_activity'] = 'Select an activity to mark attendance';
$string['export_attendance'] = 'Export Attendance';
$string['attendance_updated'] = 'Attendance updated successfully';
$string['prev_day'] = 'Previous Day';
$string['next_day'] = 'Next Day';
$string['today'] = 'Today';

// Absence notifications
$string['absence_notification_title'] = 'Student Absence Notification';
$string['absence_notification_subject'] = 'Absence: {$a}';
$string['absence_notification_intro'] = 'A student has been marked absent from an activity.';
$string['absence_notification_small'] = '{$a->student_name} absent from {$a->activity_title} on {$a->activity_date}';
$string['absence_notification_footer'] = 'This is an automatic notification from FTM Scheduler.';

// Message provider
$string['messageprovider:absence_notification'] = 'Absence notifications';

// Calendar Import
$string['import_calendar'] = 'Import Calendar from Excel';
$string['import_instructions'] = 'Import activities from Excel planning file';
$string['import_year'] = 'Reference Year';
$string['import_sheets'] = 'Sheets to Import';
$string['import_preview'] = 'Preview';
$string['import_execute'] = 'Import Activities';
$string['import_update_existing'] = 'Update existing activities';
$string['import_dry_run'] = 'Preview only (do not import)';
$string['import_success'] = 'Import completed successfully';
$string['import_success_stats'] = '{$a->created} activities created, {$a->updated} updated, {$a->skipped} skipped';
$string['import_error_nofile'] = 'No file uploaded';
$string['import_error_invalidfile'] = 'Invalid file type. Please upload an Excel file (.xlsx or .xls)';
$string['import_error_upload'] = 'Error uploading file';
$string['import_error_reading'] = 'Error reading Excel file';
$string['import_select_sheets'] = 'Select Sheets to Import';
$string['import_select_all'] = 'Select All';
$string['import_deselect_all'] = 'Deselect All';
$string['import_activities_found'] = '{$a} activities found';
$string['import_back_to_calendar'] = 'Back to Calendar';

// Student Individual Program
$string['student_program'] = 'Individual Program';
$string['student_program_title'] = 'Student Individual Program';
$string['student_program_calendar'] = '6-Week Calendar';
$string['student_program_tests'] = 'Assigned Tests';
$string['student_program_edit'] = 'Edit Activity';
$string['student_program_save'] = 'Save Changes';
$string['student_program_export_excel'] = 'Export Excel';
$string['student_program_export_pdf'] = 'Export PDF';
$string['student_program_print'] = 'Print';
$string['student_program_presence'] = 'In Presence';
$string['student_program_remote'] = 'Remote';
$string['student_program_week_fixed'] = 'Week 1 = Not editable';
$string['student_program_week_editable'] = 'Weeks 2-6 = Editable by Coach/Secretary';
$string['student_program_no_tests'] = 'No tests assigned.';
$string['student_program_manage_tests'] = 'Manage Tests';
$string['student_program_saved'] = 'Program saved successfully';
$string['student_program_tests_saved'] = 'Tests updated successfully';
$string['student_program_no_changes'] = 'No changes to save';
$string['studentnotingroup'] = 'Student is not a member of this group';

// Test catalog
$string['test_catalog'] = 'Test Catalog';
$string['test_code'] = 'Code';
$string['test_name'] = 'Test Name';
$string['test_type'] = 'Type';
$string['test_type_theorico'] = 'Theory';
$string['test_type_pratico'] = 'Practical';
$string['test_pending'] = 'Pending';
$string['test_completed'] = 'Completed';
$string['test_completion_date'] = 'Completion Date';
