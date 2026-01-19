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
 * English language strings for local_ftm_cpurc.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'FTM CPURC Manager';
$string['pluginname_desc'] = 'Import and manage CPURC students with extended data and final reports.';

// Capabilities.
$string['ftm_cpurc:view'] = 'View CPURC data';
$string['ftm_cpurc:import'] = 'Import CSV CPURC';
$string['ftm_cpurc:edit'] = 'Edit student data';
$string['ftm_cpurc:generatereport'] = 'Generate reports';
$string['ftm_cpurc:finalizereport'] = 'Finalize and send reports';

// Navigation.
$string['cpurc_manager'] = 'CPURC Manager';
$string['import_csv'] = 'Import CSV';
$string['student_card'] = 'Student Card';
$string['generate_report'] = 'Generate Report';

// Dashboard.
$string['dashboard'] = 'Dashboard';
$string['total_students'] = 'Total Students';
$string['active_students'] = 'Active Students';
$string['reports_draft'] = 'Draft Reports';
$string['reports_final'] = 'Final Reports';

// Import.
$string['import_title'] = 'Import CSV CPURC';
$string['import_instructions'] = 'Upload a CSV file with CPURC format (69 columns, semicolon separator).';
$string['import_file'] = 'CSV File';
$string['import_options'] = 'Import Options';
$string['update_existing'] = 'Update existing users';
$string['enrol_course'] = 'Enrol in course';
$string['assign_cohort'] = 'Assign to URC cohort';
$string['assign_group'] = 'Assign to color group';
$string['import_preview'] = 'Preview';
$string['import_start'] = 'Start Import';
$string['import_success'] = 'Import completed successfully';
$string['import_errors'] = 'Import completed with errors';
$string['rows_imported'] = '{$a} rows imported';
$string['rows_updated'] = '{$a} rows updated';
$string['rows_errors'] = '{$a} errors';

// Student card.
$string['tab_anagrafica'] = 'Personal Data';
$string['tab_percorso'] = 'Path';
$string['tab_assenze'] = 'Absences';
$string['tab_stage'] = 'Internship';
$string['tab_report'] = 'Report';

// Fields - Personal data.
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['gender'] = 'Gender';
$string['birthdate'] = 'Date of Birth';
$string['address'] = 'Address';
$string['cap'] = 'ZIP Code';
$string['city'] = 'City';
$string['phone'] = 'Phone';
$string['mobile'] = 'Mobile';
$string['email'] = 'Email';
$string['avs_number'] = 'AVS Number';
$string['nationality'] = 'Nationality';
$string['permit'] = 'Permit';
$string['iban'] = 'IBAN';
$string['civil_status'] = 'Civil Status';

// Fields - Path.
$string['personal_number'] = 'Personal Number';
$string['measure'] = 'Measure';
$string['trainer'] = 'Trainer';
$string['signal_date'] = 'Signal Date';
$string['date_start'] = 'Start Date';
$string['date_end_planned'] = 'Planned End Date';
$string['date_end_actual'] = 'Actual End Date';
$string['occupation_grade'] = 'Occupation Grade';
$string['urc_office'] = 'URC Office';
$string['urc_consultant'] = 'URC Consultant';
$string['status'] = 'Status';
$string['exit_reason'] = 'Exit Reason';
$string['last_profession'] = 'Last Profession';
$string['sector'] = 'Sector';
$string['priority'] = 'Priority';
$string['financier'] = 'Financier';
$string['unemployment_fund'] = 'Unemployment Fund';

// Fields - Absences.
$string['absence_x'] = 'Absence X';
$string['absence_o'] = 'Absence O';
$string['absence_a'] = 'Absence A';
$string['absence_b'] = 'Absence B';
$string['absence_c'] = 'Absence C';
$string['absence_d'] = 'Absence D';
$string['absence_e'] = 'Absence E';
$string['absence_f'] = 'Absence F';
$string['absence_g'] = 'Absence G';
$string['absence_h'] = 'Absence H';
$string['absence_i'] = 'Absence I';
$string['absence_total'] = 'Total Absences';

// Fields - Stage.
$string['stage_start'] = 'Internship Start';
$string['stage_end'] = 'Internship End';
$string['stage_responsible'] = 'Responsible';
$string['stage_company'] = 'Company';
$string['stage_contact'] = 'Contact Person';
$string['stage_percentage'] = 'Percentage';
$string['stage_function'] = 'Function';

// Report.
$string['report_title'] = 'Final Report';
$string['section_initial_situation'] = 'Initial Situation';
$string['section_sector_competencies'] = 'Sector Competencies';
$string['section_transversal_competencies'] = 'Transversal Competencies';
$string['section_job_search'] = 'Job Search';
$string['section_interviews'] = 'Interviews';
$string['section_outcome'] = 'Outcome';
$string['save_draft'] = 'Save Draft';
$string['export_data'] = 'Export Data';
$string['hired'] = 'Hired';
$string['not_hired'] = 'Not Hired';

// URC Offices.
$string['urc_bellinzona'] = 'URC Bellinzona';
$string['urc_chiasso'] = 'URC Chiasso';
$string['urc_lugano'] = 'URC Lugano';
$string['urc_biasca'] = 'URC Biasca';
$string['urc_locarno'] = 'URC Locarno';

// Sectors.
$string['sector_automobile'] = 'Automobile';
$string['sector_meccanica'] = 'Mechanics';
$string['sector_logistica'] = 'Logistics';
$string['sector_elettricita'] = 'Electricity';
$string['sector_automazione'] = 'Automation';
$string['sector_metalcostruzione'] = 'Metal Construction';
$string['sector_chimfarm'] = 'Chemical-Pharmaceutical';

// Messages.
$string['student_saved'] = 'Student data saved successfully';
$string['report_saved'] = 'Report saved successfully';
$string['error_invalid_csv'] = 'Invalid CSV file format';
$string['error_missing_required'] = 'Missing required fields: {$a}';
$string['error_user_exists'] = 'User already exists: {$a}';
$string['error_save_failed'] = 'Failed to save data';
