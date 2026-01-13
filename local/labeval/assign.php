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
 * Assign lab evaluation to students
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/api.php');

use local_labeval\api;

// Require login and capability
require_login();
$context = context_system::instance();
require_capability('local/labeval:assignevaluations', $context);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/assign.php'));
$PAGE->set_title(get_string('assignevaluation', 'local_labeval'));
$PAGE->set_heading(get_string('assignevaluation', 'local_labeval'));
$PAGE->set_pagelayout('standard');

// Get available templates
$templates = $DB->get_records('local_labeval_templates', ['status' => 'active'], 'name ASC');
$templateoptions = [];
foreach ($templates as $t) {
    $templateoptions[$t->id] = $t->name . ' (' . $t->sectorcode . ')';
}

// Get students - try coachmanager first, fallback to all users with student role
$students = [];

// Check if coachmanager is installed
if ($DB->get_manager()->table_exists('local_coachmanager_coach_assign')) {
    // Get students from coachmanager
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
            FROM {local_coachmanager_coach_assign} ca
            JOIN {user} u ON u.id = ca.studentid
            WHERE ca.coachid = ? AND ca.status = 1
            ORDER BY u.lastname, u.firstname";
    $students = $DB->get_records_sql($sql, [$USER->id]);
}

// If no students from coachmanager, get all enrolled students
if (empty($students)) {
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND u.deleted = 0
            ORDER BY u.lastname, u.firstname";
    $students = $DB->get_records_sql($sql);
}

$studentoptions = [];
foreach ($students as $s) {
    $studentoptions[$s->id] = $s->lastname . ' ' . $s->firstname . ' (' . $s->email . ')';
}

// Form definition
class assign_form extends moodleform {
    protected function definition() {
        global $templateoptions, $studentoptions;
        
        $mform = $this->_form;
        
        // Template selection
        $mform->addElement('select', 'templateid', get_string('selecttemplate', 'local_labeval'), $templateoptions);
        $mform->addRule('templateid', get_string('required'), 'required', null, 'client');
        
        // Student selection (multi-select)
        $select = $mform->addElement('select', 'studentids', get_string('selectstudents', 'local_labeval'), $studentoptions);
        $select->setMultiple(true);
        $mform->addRule('studentids', get_string('required'), 'required', null, 'client');
        
        // Due date (optional)
        $mform->addElement('date_selector', 'duedate', get_string('duedate', 'local_labeval'), ['optional' => true]);
        $mform->setDefault('duedate', time() + (14 * 24 * 60 * 60)); // 2 weeks
        
        // Buttons
        $this->add_action_buttons(true, get_string('assignevaluation', 'local_labeval'));
    }
}

$form = new assign_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/labeval/assignments.php'));
    
} else if ($data = $form->get_data()) {
    // Create assignments
    $count = 0;
    $duedate = !empty($data->duedate) ? $data->duedate : null;
    
    foreach ($data->studentids as $studentid) {
        api::create_assignment($data->templateid, $studentid, $USER->id, null, $duedate);
        $count++;
    }
    
    redirect(new moodle_url('/local/labeval/assignments.php'),
        get_string('assignmentcreated', 'local_labeval', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Output
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/labeval/index.php'), get_string('dashboard', 'local_labeval')),
    new tabobject('templates', new moodle_url('/local/labeval/templates.php'), get_string('templates', 'local_labeval')),
    new tabobject('assignments', new moodle_url('/local/labeval/assignments.php'), get_string('assignments', 'local_labeval')),
    new tabobject('reports', new moodle_url('/local/labeval/reports.php'), get_string('reports', 'local_labeval')),
];
echo $OUTPUT->tabtree($tabs, 'assignments');

echo local_labeval_get_common_styles();
?>

<div class="labeval-container">
    
    <div class="card">
        <div class="card-header primary">
            <h2 style="margin: 0;">➕ <?php echo get_string('assignevaluation', 'local_labeval'); ?></h2>
            <p style="margin: 10px 0 0; opacity: 0.9;">Assegna una prova pratica agli studenti</p>
        </div>
        <div class="card-body">
            
            <?php if (empty($templates)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Attenzione:</strong> Nessun template disponibile. 
                <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>">Importa un template</a> prima di assegnare prove.
            </div>
            <?php elseif (empty($studentoptions)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Attenzione:</strong> Nessuno studente disponibile. 
                Verifica di avere studenti assegnati in Coach Manager.
            </div>
            <?php else: ?>
            
            <div class="alert alert-info">
                <strong>💡 Suggerimento:</strong> Puoi selezionare più studenti tenendo premuto CTRL (o CMD su Mac).
            </div>
            
            <?php $form->display(); ?>
            
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
