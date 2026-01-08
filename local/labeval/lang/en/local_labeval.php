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
 * English language strings for local_labeval
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin info
$string['pluginname'] = 'Lab Evaluation';
$string['labeval:managetemplates'] = 'Manage evaluation templates';
$string['labeval:importtemplates'] = 'Import templates from Excel';
$string['labeval:assignevaluations'] = 'Assign evaluations to students';
$string['labeval:evaluate'] = 'Evaluate students';
$string['labeval:viewownreport'] = 'View own report';
$string['labeval:viewallreports'] = 'View all reports';
$string['labeval:authorizestudents'] = 'Authorize students';
$string['labeval:view'] = 'View Lab Evaluation';

// Navigation
$string['dashboard'] = 'Dashboard';
$string['templates'] = 'Templates';
$string['assignments'] = 'Assignments';
$string['evaluations'] = 'Evaluations';
$string['reports'] = 'Reports';

// Templates
$string['templatelist'] = 'Evaluation Templates';
$string['newtemplate'] = 'New Template';
$string['edittemplate'] = 'Edit Template';
$string['importtemplate'] = 'Import from Excel';
$string['downloadtemplate'] = 'Download Excel Template';
$string['templatename'] = 'Template Name';
$string['templatedesc'] = 'Description';
$string['sectorcode'] = 'Sector Code';
$string['behaviors'] = 'Observable Behaviors';
$string['behaviorscount'] = '{$a} behaviors';
$string['competenciescount'] = '{$a} competencies';
$string['notemplatess'] = 'No templates found';
$string['templatecreated'] = 'Template created successfully';
$string['templateupdated'] = 'Template updated successfully';
$string['templatedeleted'] = 'Template deleted';
$string['confirmdeletetemplate'] = 'Are you sure you want to delete this template?';

// Import
$string['importexcel'] = 'Import Excel File';
$string['selectfile'] = 'Select Excel file';
$string['importpreview'] = 'Import Preview';
$string['importconfirm'] = 'Confirm Import';
$string['importsuccess'] = 'Import completed: {$a->behaviors} behaviors, {$a->competencies} competency mappings';
$string['importerror'] = 'Import error: {$a}';
$string['downloadexample'] = 'Download Example Excel';
$string['excelformat'] = 'Excel must have columns: Behavior, Competency Code, Description, Weight (1-3)';

// Behaviors
$string['behavior'] = 'Observable Behavior';
$string['addbehavior'] = 'Add Behavior';
$string['editbehavior'] = 'Edit Behavior';
$string['deletebehavior'] = 'Delete Behavior';
$string['competency'] = 'Competency';
$string['competencies'] = 'Competencies';
$string['weight'] = 'Weight';
$string['weightprimary'] = 'Primary (3)';
$string['weightsecondary'] = 'Secondary (1)';

// Assignments
$string['assignevaluation'] = 'Assign Evaluation';
$string['assignto'] = 'Assign to';
$string['selectstudents'] = 'Select Students';
$string['selecttemplate'] = 'Select Template';
$string['duedate'] = 'Due Date';
$string['assignmentcreated'] = 'Evaluation assigned to {$a} students';
$string['noassignments'] = 'No assignments found';
$string['assignmentstatus'] = 'Status';
$string['pending'] = 'Pending';
$string['completed'] = 'Completed';
$string['expired'] = 'Expired';

// Evaluation
$string['evaluate'] = 'Evaluate';
$string['evaluatestudent'] = 'Evaluate Student';
$string['evaluationform'] = 'Evaluation Form';
$string['ratingscale'] = 'Rating Scale';
$string['rating0'] = 'Not observed / N/A';
$string['rating1'] = 'Needs improvement';
$string['rating3'] = 'Adequate / Competent';
$string['savenotes'] = 'Save Notes';
$string['savedraft'] = 'Save Draft';
$string['completeevaluation'] = 'Complete Evaluation';
$string['evaluationcompleted'] = 'Evaluation completed successfully';
$string['evaluationsaved'] = 'Evaluation saved as draft';
$string['confirmevaluation'] = 'Confirm and complete evaluation?';
$string['totalscore'] = 'Total Score';
$string['maxscore'] = 'Max Score';
$string['percentage'] = 'Percentage';
$string['generalnotes'] = 'General Notes';

// Reports
$string['studentreport'] = 'Student Report';
$string['integratedreport'] = 'Integrated Report';
$string['selectsources'] = 'Select Data Sources';
$string['source_quiz'] = 'Quiz (theoretical knowledge)';
$string['source_selfassess'] = 'Self-assessment (Bloom)';
$string['source_labeval'] = 'Lab Evaluations (practical)';
$string['selectvisualization'] = 'Select Visualization';
$string['viz_radar_table'] = 'Radar + Gap Table (recommended)';
$string['viz_radar_only'] = 'Radar only';
$string['viz_table_only'] = 'Table only';
$string['options'] = 'Options';
$string['showallcompetencies'] = 'Show all competencies';
$string['highlightgaps'] = 'Highlight significant gaps (>15%)';
$string['includesuggestions'] = 'Include suggestions for interview';
$string['generatereport'] = 'Generate Report';
$string['printpdf'] = 'Print PDF';
$string['sendtostudent'] = 'Send to Student';

// Gap analysis
$string['gapanalysis'] = 'Gap Analysis';
$string['quiz'] = 'Quiz';
$string['selfassessment'] = 'Self-Assessment';
$string['practical'] = 'Practical';
$string['gap'] = 'Gap';
$string['aligned'] = 'Aligned';
$string['activate'] = 'Activate';
$string['nottested'] = 'Not tested';

// Authorization
$string['authorizestudent'] = 'Authorize Student';
$string['authorizedstudents'] = 'Authorized Students';
$string['studentauthorized'] = 'Student authorized to view report';
$string['studentunauthorized'] = 'Authorization removed';

// PDF
$string['reporttitle'] = 'Integrated Competency Report';
$string['datasources'] = 'Data Sources Included';
$string['competencydetail'] = 'Competency Detail';
$string['coachnotes'] = 'Coach Notes and Observations';
$string['interviewdate'] = 'Interview Date';
$string['coachsignature'] = 'Coach Signature';
$string['studentsignature'] = 'Student Signature';

// Misc
$string['nodata'] = 'No data available';
$string['student'] = 'Student';
$string['coach'] = 'Coach';
$string['date'] = 'Date';
$string['actions'] = 'Actions';
$string['view'] = 'View';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['confirm'] = 'Confirm';
$string['success'] = 'Operation completed successfully';
$string['error'] = 'An error occurred';
$string['back'] = 'Back';
$string['next'] = 'Next';
