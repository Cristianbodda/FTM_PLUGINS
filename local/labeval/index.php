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
 * Main dashboard for local_labeval
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

// Check capability
$context = context_system::instance();
require_capability('local/labeval:view', $context);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_labeval'));
$PAGE->set_heading(get_string('pluginname', 'local_labeval'));
$PAGE->set_pagelayout('standard');

$userid = $USER->id;
$canmanage = has_capability('local/labeval:managetemplates', $context);
$canevaluate = has_capability('local/labeval:evaluate', $context);
$canassign = has_capability('local/labeval:assignevaluations', $context);

// Get statistics
$templatecount = $DB->count_records('local_labeval_templates', ['status' => 'active']);
$assignmentcount = $DB->count_records('local_labeval_assignments');
$pendingcount = $DB->count_records('local_labeval_assignments', ['status' => 'pending']);
$completedcount = $DB->count_records('local_labeval_sessions', ['status' => 'completed']);

// Output
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/labeval/index.php'), get_string('dashboard', 'local_labeval')),
    new tabobject('templates', new moodle_url('/local/labeval/templates.php'), get_string('templates', 'local_labeval')),
    new tabobject('assignments', new moodle_url('/local/labeval/assignments.php'), get_string('assignments', 'local_labeval')),
    new tabobject('reports', new moodle_url('/local/labeval/reports.php'), get_string('reports', 'local_labeval')),
];
echo $OUTPUT->tabtree($tabs, 'dashboard');

echo local_labeval_get_common_styles();
?>

<div class="labeval-container">
    
    <!-- Header -->
    <div class="card">
        <div class="card-header primary">
            <h2 style="margin: 0;">🔬 <?php echo get_string('pluginname', 'local_labeval'); ?></h2>
            <p style="margin: 10px 0 0; opacity: 0.9;">Gestione valutazioni prove pratiche di laboratorio</p>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card purple">
            <div class="number"><?php echo $templatecount; ?></div>
            <div class="label">📋 Template Prove</div>
        </div>
        <div class="stat-card info">
            <div class="number"><?php echo $assignmentcount; ?></div>
            <div class="label">📝 Prove Assegnate</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?php echo $pendingcount; ?></div>
            <div class="label">⏳ In Attesa</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $completedcount; ?></div>
            <div class="label">✅ Completate</div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Azioni Rapide</h3>
        </div>
        <div class="card-body">
            <div class="btn-group" style="flex-wrap: wrap; gap: 10px;">
                <?php if ($canassign): ?>
                <a href="<?php echo new moodle_url('/local/labeval/assign.php'); ?>" class="btn btn-success">
                    ➕ Assegna Prova
                </a>
                <?php endif; ?>
                
                <?php if ($canevaluate): ?>
                <a href="<?php echo new moodle_url('/local/labeval/assignments.php', ['status' => 'pending']); ?>" class="btn btn-warning">
                    📝 Valuta Prove in Attesa (<?php echo $pendingcount; ?>)
                </a>
                <?php endif; ?>
                
                <?php if ($canmanage): ?>
                <a href="<?php echo new moodle_url('/local/labeval/templates.php'); ?>" class="btn btn-purple">
                    📋 Gestisci Template
                </a>
                <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>" class="btn btn-info">
                    📥 Importa da Excel
                </a>
                <?php endif; ?>
                
                <a href="<?php echo new moodle_url('/local/labeval/reports.php'); ?>" class="btn btn-primary">
                    📊 Report
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($pendingcount > 0 && $canevaluate): ?>
    <!-- Pending Evaluations -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: #333;">
            <h3 style="margin: 0;">⏳ Prove in Attesa di Valutazione</h3>
        </div>
        <div class="card-body">
            <?php
            $pending = $DB->get_records_sql(
                "SELECT a.*, t.name as templatename, t.sectorcode,
                        u.firstname, u.lastname, u.email
                 FROM {local_labeval_assignments} a
                 JOIN {local_labeval_templates} t ON t.id = a.templateid
                 JOIN {user} u ON u.id = a.studentid
                 WHERE a.status = 'pending'
                 ORDER BY a.duedate ASC, a.timecreated DESC
                 LIMIT 10"
            );
            
            if ($pending):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Prova</th>
                        <th>Settore</th>
                        <th>Assegnata</th>
                        <th>Scadenza</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $a): 
                        $isexpired = $a->duedate && $a->duedate < time();
                        $isurgent = $a->duedate && $a->duedate < time() + (2 * 24 * 60 * 60);
                    ?>
                    <tr class="<?php echo $isexpired ? 'table-danger' : ($isurgent ? 'table-warning' : ''); ?>">
                        <td>
                            <strong><?php echo $a->firstname . ' ' . $a->lastname; ?></strong><br>
                            <small class="text-muted"><?php echo $a->email; ?></small>
                        </td>
                        <td><?php echo $a->templatename; ?></td>
                        <td><span class="badge badge-info"><?php echo $a->sectorcode; ?></span></td>
                        <td><?php echo userdate($a->timecreated, '%d/%m/%Y'); ?></td>
                        <td>
                            <?php if ($a->duedate): ?>
                                <?php if ($isexpired): ?>
                                    <span class="badge badge-danger">Scaduta</span>
                                <?php elseif ($isurgent): ?>
                                    <span class="badge badge-warning"><?php echo userdate($a->duedate, '%d/%m/%Y'); ?></span>
                                <?php else: ?>
                                    <?php echo userdate($a->duedate, '%d/%m/%Y'); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo new moodle_url('/local/labeval/evaluate.php', ['assignmentid' => $a->id]); ?>" 
                               class="btn btn-success btn-sm">
                                ✏️ Valuta
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($pendingcount > 10): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="<?php echo new moodle_url('/local/labeval/assignments.php', ['status' => 'pending']); ?>" class="btn btn-outline">
                    Vedi tutte le <?php echo $pendingcount; ?> prove in attesa →
                </a>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="no-data">
                <p>Nessuna prova in attesa di valutazione</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Completed -->
    <div class="card">
        <div class="card-header">
            <h3>✅ Ultime Valutazioni Completate</h3>
        </div>
        <div class="card-body">
            <?php
            $completed = $DB->get_records_sql(
                "SELECT s.*, a.studentid, t.name as templatename, t.sectorcode,
                        u.firstname, u.lastname
                 FROM {local_labeval_sessions} s
                 JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                 JOIN {local_labeval_templates} t ON t.id = a.templateid
                 JOIN {user} u ON u.id = a.studentid
                 WHERE s.status = 'completed'
                 ORDER BY s.timecompleted DESC
                 LIMIT 5"
            );
            
            if ($completed):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Prova</th>
                        <th>Completata</th>
                        <th>Punteggio</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed as $s): 
                        $percentage = $s->percentage ?? 0;
                        $percentclass = $percentage >= 70 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td><strong><?php echo $s->firstname . ' ' . $s->lastname; ?></strong></td>
                        <td>
                            <?php echo $s->templatename; ?>
                            <span class="badge badge-info"><?php echo $s->sectorcode; ?></span>
                        </td>
                        <td><?php echo userdate($s->timecompleted, '%d/%m/%Y %H:%M'); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="progress-bar" style="width: 80px;">
                                    <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%; background: var(--<?php echo $percentclass; ?>-color, #28a745);"></div>
                                </div>
                                <span class="badge badge-<?php echo $percentclass; ?>"><?php echo round($percentage); ?>%</span>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo new moodle_url('/local/labeval/view_evaluation.php', ['sessionid' => $s->id]); ?>" 
                               class="btn btn-primary btn-sm">
                                👁️ Dettagli
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>Nessuna valutazione completata</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Templates Overview -->
    <?php if ($canmanage): ?>
    <div class="card">
        <div class="card-header">
            <h3>📋 Template Disponibili</h3>
        </div>
        <div class="card-body">
            <?php
            $templates = $DB->get_records_sql(
                "SELECT t.*, 
                        (SELECT COUNT(*) FROM {local_labeval_behaviors} b WHERE b.templateid = t.id) as behaviorcount,
                        (SELECT COUNT(DISTINCT bc.competencycode) 
                         FROM {local_labeval_behaviors} b 
                         JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = b.id 
                         WHERE b.templateid = t.id) as compcount,
                        (SELECT COUNT(*) FROM {local_labeval_assignments} a WHERE a.templateid = t.id) as assigncount
                 FROM {local_labeval_templates} t
                 WHERE t.status = 'active'
                 ORDER BY t.name"
            );
            
            if ($templates):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome Template</th>
                        <th>Settore</th>
                        <th>Comportamenti</th>
                        <th>Competenze</th>
                        <th>Utilizzi</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><strong><?php echo $t->name; ?></strong></td>
                        <td><span class="badge badge-purple"><?php echo $t->sectorcode; ?></span></td>
                        <td><?php echo $t->behaviorcount; ?></td>
                        <td><?php echo $t->compcount; ?></td>
                        <td><?php echo $t->assigncount; ?></td>
                        <td>
                            <a href="<?php echo new moodle_url('/local/labeval/template_view.php', ['id' => $t->id]); ?>" 
                               class="btn btn-primary btn-sm">👁️</a>
                            <a href="<?php echo new moodle_url('/local/labeval/template_edit.php', ['id' => $t->id]); ?>" 
                               class="btn btn-secondary btn-sm">✏️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 15px;">
                <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>" class="btn btn-success">
                    ➕ Importa Nuovo Template da Excel
                </a>
            </div>
            <?php else: ?>
            <div class="no-data">
                <p>Nessun template configurato</p>
                <a href="<?php echo new moodle_url('/local/labeval/import.php'); ?>" class="btn btn-success">
                    ➕ Importa il primo Template da Excel
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<?php
echo $OUTPUT->footer();
