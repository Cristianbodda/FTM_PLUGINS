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
 * View template details
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/api.php');

use local_labeval\api;

// Require login
require_login();
$context = context_system::instance();
require_capability('local/labeval:view', $context);

// Parameters
$id = required_param('id', PARAM_INT);

// Get template
$template = api::get_template_details($id);
if (!$template) {
    throw new moodle_exception('Template not found');
}

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/template_view.php', ['id' => $id]));
$PAGE->set_title($template->name);
$PAGE->set_heading($template->name);
$PAGE->set_pagelayout('standard');

$canmanage = has_capability('local/labeval:managetemplates', $context);
$canassign = has_capability('local/labeval:assignevaluations', $context);

// Get usage stats
$assigncount = $DB->count_records('local_labeval_assignments', ['templateid' => $id]);
$completedcount = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_labeval_assignments} a
     JOIN {local_labeval_sessions} s ON s.assignmentid = a.id
     WHERE a.templateid = ? AND s.status = 'completed'",
    [$id]
);

// Get unique competencies
$competencies = [];
foreach ($template->behaviors as $behavior) {
    foreach ($behavior->competencies as $comp) {
        if (!isset($competencies[$comp->competencycode])) {
            $competencies[$comp->competencycode] = [
                'code' => $comp->competencycode,
                'count' => 0,
                'weight3' => 0,
                'weight1' => 0
            ];
        }
        $competencies[$comp->competencycode]['count']++;
        if ($comp->weight == 3) {
            $competencies[$comp->competencycode]['weight3']++;
        } else {
            $competencies[$comp->competencycode]['weight1']++;
        }
    }
}
ksort($competencies);

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

<style>
.behavior-item {
    background: white;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 10px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.05);
    border-left: 4px solid #17a2b8;
}

.behavior-item:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.behavior-num {
    display: inline-flex;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #17a2b8, #0dcaf0);
    color: white;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 13px;
    margin-right: 12px;
}

.behavior-competencies {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #eee;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.comp-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    background: #e3f2fd;
    color: #1976d2;
}

.comp-tag.weight-3 {
    background: #e8f5e9;
    color: #2e7d32;
    font-weight: 600;
}

.comp-tag.weight-1 {
    background: #f5f5f5;
    color: #666;
}

.competency-summary-card {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    border: 1px solid #eee;
}

.competency-summary-card .code {
    font-weight: 700;
    color: #0d6efd;
    font-size: 14px;
}

.competency-summary-card .stats {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}
</style>

<div class="labeval-container">
    
    <!-- Back button -->
    <div style="margin-bottom: 15px;">
        <a href="<?php echo new moodle_url('/local/labeval/templates.php'); ?>" class="btn btn-outline">
            ← Torna ai Template
        </a>
    </div>
    
    <!-- Header -->
    <div class="card">
        <div class="card-header purple">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="margin: 0; color: white;">📋 <?php echo $template->name; ?></h2>
                    <?php if ($template->description): ?>
                    <p style="margin: 10px 0 0; opacity: 0.9;"><?php echo $template->description; ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="badge" style="background: rgba(255,255,255,0.2); color: white; font-size: 14px; padding: 8px 15px;">
                        <?php echo $template->sectorcode; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card info">
            <div class="number"><?php echo count($template->behaviors); ?></div>
            <div class="label">📝 Comportamenti</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo count($competencies); ?></div>
            <div class="label">🎯 Competenze</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?php echo $assigncount; ?></div>
            <div class="label">📋 Assegnate</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $completedcount; ?></div>
            <div class="label">✅ Completate</div>
        </div>
    </div>
    
    <!-- Actions -->
    <?php if ($canassign): ?>
    <div class="card">
        <div class="card-body">
            <div class="btn-group">
                <a href="<?php echo new moodle_url('/local/labeval/assign.php', ['templateid' => $id]); ?>" class="btn btn-success">
                    ➕ Assegna a Studenti
                </a>
                <a href="<?php echo new moodle_url('/local/labeval/assignments.php'); ?>" class="btn btn-info">
                    📋 Vedi Assegnazioni
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Competencies Summary -->
    <div class="card">
        <div class="card-header">
            <h3>🎯 Competenze Coinvolte (<?php echo count($competencies); ?>)</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px;">
                <?php foreach ($competencies as $comp): ?>
                <div class="competency-summary-card">
                    <div class="code"><?php echo $comp['code']; ?></div>
                    <div class="stats">
                        <span style="color: #28a745;">●<?php echo $comp['weight3']; ?> principali</span>
                        <span style="color: #999; margin-left: 8px;">●<?php echo $comp['weight1']; ?> secondarie</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Behaviors List -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Comportamenti Osservabili (<?php echo count($template->behaviors); ?>)</h3>
        </div>
        <div class="card-body">
            <?php 
            $num = 1;
            foreach ($template->behaviors as $behavior): 
            ?>
            <div class="behavior-item">
                <div>
                    <span class="behavior-num"><?php echo $num++; ?></span>
                    <span style="font-weight: 500;"><?php echo $behavior->description; ?></span>
                </div>
                
                <?php if (!empty($behavior->competencies)): ?>
                <div class="behavior-competencies">
                    <?php foreach ($behavior->competencies as $comp): ?>
                    <span class="comp-tag weight-<?php echo $comp->weight; ?>">
                        <?php echo $comp->competencycode; ?>
                        <span style="opacity: 0.7;">(<?php echo $comp->weight == 3 ? '★★★' : '★'; ?>)</span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Info -->
    <div class="card">
        <div class="card-body" style="color: #666; font-size: 13px;">
            <strong>Creato da:</strong> <?php 
                $creator = $DB->get_record('user', ['id' => $template->createdby]);
                echo fullname($creator);
            ?> 
            il <?php echo userdate($template->timecreated, '%d/%m/%Y %H:%M'); ?>
            
            <?php if ($template->timemodified != $template->timecreated): ?>
            <br><strong>Ultima modifica:</strong> <?php echo userdate($template->timemodified, '%d/%m/%Y %H:%M'); ?>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
