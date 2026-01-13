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
 * Templates management page
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require login
require_login();
$context = context_system::instance();
require_capability('local/labeval:view', $context);

$canmanage = has_capability('local/labeval:managetemplates', $context);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/templates.php'));
$PAGE->set_title(get_string('templates', 'local_labeval'));
$PAGE->set_heading(get_string('templates', 'local_labeval'));
$PAGE->set_pagelayout('standard');

// Get templates with stats
$sql = "SELECT t.*, 
               u.firstname, u.lastname,
               (SELECT COUNT(*) FROM {local_labeval_behaviors} b WHERE b.templateid = t.id) as behaviorcount,
               (SELECT COUNT(DISTINCT bc.competencycode) 
                FROM {local_labeval_behaviors} b 
                JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = b.id 
                WHERE b.templateid = t.id) as compcount,
               (SELECT COUNT(*) FROM {local_labeval_assignments} a WHERE a.templateid = t.id) as assigncount,
               (SELECT COUNT(*) FROM {local_labeval_assignments} a 
                JOIN {local_labeval_sessions} s ON s.assignmentid = a.id 
                WHERE a.templateid = t.id AND s.status = 'completed') as completedcount
        FROM {local_labeval_templates} t
        JOIN {user} u ON u.id = t.createdby
        ORDER BY t.status DESC, t.name";

$templates = $DB->get_records_sql($sql);

// Output
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/labeval/index.php'), get_string('dashboard', 'local_labeval')),
    new tabobject('templates', new moodle_url('/local/labeval/templates.php'), get_string('templates', 'local_labeval')),
    new tabobject('assignments', new moodle_url('/local/labeval/assignments.php'), get_string('assignments', 'local_labeval')),
    new tabobject('reports', new moodle_url('/local/labeval/reports.php'), get_string('reports', 'local_labeval')),
];
echo $OUTPUT->tabtree($tabs, 'templates');

echo local_labeval_get_common_styles();
?>

<div class="labeval-container">
    
    <?php if ($canmanage): ?>
    <!-- Actions Bar -->
    <div class="card filters-bar">
        <div class="card-body">
            <div class="filters-row">
                <div class="filter-group">
                    <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>" class="btn btn-success">
                        📥 Importa da Excel
                    </a>
                    <a href="<?php echo new moodle_url('/local/labeval/import.php', ['downloadexample' => 1]); ?>" class="btn btn-info">
                        📋 Scarica Template Esempio
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Templates List -->
    <div class="card">
        <div class="card-header purple">
            <h3 style="margin: 0; color: white;">📋 <?php echo get_string('templatelist', 'local_labeval'); ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
            <div class="no-data">
                <p><?php echo get_string('notemplates', 'local_labeval'); ?></p>
                <?php if ($canmanage): ?>
                <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>" class="btn btn-success">
                    📥 Importa Primo Template
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                <?php foreach ($templates as $t): ?>
                <div class="card" style="margin: 0; <?php echo $t->status == 'archived' ? 'opacity: 0.6;' : ''; ?>">
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h4 style="margin: 0; font-size: 15px;"><?php echo $t->name; ?></h4>
                            <?php echo local_labeval_get_status_badge($t->status); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom: 15px;">
                            <span class="badge badge-purple"><?php echo $t->sectorcode; ?></span>
                        </div>
                        
                        <?php if ($t->description): ?>
                        <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                            <?php echo shorten_text($t->description, 100); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #17a2b8;"><?php echo $t->behaviorcount; ?></div>
                                <div style="font-size: 11px; color: #666;">Comportamenti</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #9b59b6;"><?php echo $t->compcount; ?></div>
                                <div style="font-size: 11px; color: #666;">Competenze</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #ffc107;"><?php echo $t->assigncount; ?></div>
                                <div style="font-size: 11px; color: #666;">Assegnate</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #28a745;"><?php echo $t->completedcount; ?></div>
                                <div style="font-size: 11px; color: #666;">Completate</div>
                            </div>
                        </div>
                        
                        <div style="font-size: 12px; color: #888; margin-bottom: 15px;">
                            Creato da <?php echo $t->firstname . ' ' . $t->lastname; ?> 
                            il <?php echo userdate($t->timecreated, '%d/%m/%Y'); ?>
                        </div>
                        
                        <div class="btn-group">
                            <a href="<?php echo new moodle_url('/local/labeval/template_view.php', ['id' => $t->id]); ?>" 
                               class="btn btn-primary btn-sm">👁️ Visualizza</a>
                            <?php if ($canmanage): ?>
                            <a href="<?php echo new moodle_url('/local/labeval/assign.php', ['templateid' => $t->id]); ?>" 
                               class="btn btn-success btn-sm">➕ Assegna</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
