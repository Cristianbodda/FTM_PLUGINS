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
 * List of assignments
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

// Parameters
$status = optional_param('status', '', PARAM_ALPHA);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/assignments.php', ['status' => $status]));
$PAGE->set_title(get_string('assignments', 'local_labeval'));
$PAGE->set_heading(get_string('assignments', 'local_labeval'));
$PAGE->set_pagelayout('standard');

$canevaluate = has_capability('local/labeval:evaluate', $context);
$canassign = has_capability('local/labeval:assignevaluations', $context);

// Build query
$where = "1=1";
$params = [];

if ($status) {
    $where .= " AND a.status = ?";
    $params[] = $status;
}

$sql = "SELECT a.*, t.name as templatename, t.sectorcode,
               student.firstname as studentfirst, student.lastname as studentlast, student.email,
               coach.firstname as coachfirst, coach.lastname as coachlast,
               s.id as sessionid, s.status as sessionstatus, s.percentage, s.timecompleted
        FROM {local_labeval_assignments} a
        JOIN {local_labeval_templates} t ON t.id = a.templateid
        JOIN {user} student ON student.id = a.studentid
        JOIN {user} coach ON coach.id = a.assignedby
        LEFT JOIN {local_labeval_sessions} s ON s.assignmentid = a.id AND s.status = 'completed'
        WHERE {$where}
        ORDER BY a.timecreated DESC";

$assignments = $DB->get_records_sql($sql, $params);

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
    
    <!-- Filters -->
    <div class="card filters-bar">
        <div class="card-body">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Filtra per stato:</label>
                    <div class="btn-group">
                        <a href="?status=" class="btn btn-sm <?php echo !$status ? 'btn-primary' : 'btn-outline'; ?>">
                            Tutti
                        </a>
                        <a href="?status=pending" class="btn btn-sm <?php echo $status == 'pending' ? 'btn-warning' : 'btn-outline'; ?>">
                            ⏳ In attesa
                        </a>
                        <a href="?status=completed" class="btn btn-sm <?php echo $status == 'completed' ? 'btn-success' : 'btn-outline'; ?>">
                            ✅ Completate
                        </a>
                    </div>
                </div>
                
                <?php if ($canassign): ?>
                <div class="filter-group">
                    <a href="<?php echo new moodle_url('/local/labeval/assign.php'); ?>" class="btn btn-success">
                        ➕ Nuova Assegnazione
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assignments List -->
    <div class="card">
        <div class="card-header">
            <h3>📋 <?php echo get_string('assignments', 'local_labeval'); ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($assignments)): ?>
            <div class="no-data">
                <p><?php echo get_string('noassignments', 'local_labeval'); ?></p>
                <?php if ($canassign): ?>
                <a href="<?php echo new moodle_url('/local/labeval/assign.php'); ?>" class="btn btn-success">
                    ➕ Assegna Prima Prova
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Prova</th>
                        <th>Assegnata da</th>
                        <th>Data</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th>Risultato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): 
                        $isexpired = $a->status == 'pending' && $a->duedate && $a->duedate < time();
                    ?>
                    <tr class="<?php echo $isexpired ? 'table-danger' : ''; ?>">
                        <td>
                            <strong><?php echo $a->studentfirst . ' ' . $a->studentlast; ?></strong><br>
                            <small class="text-muted"><?php echo $a->email; ?></small>
                        </td>
                        <td>
                            <?php echo $a->templatename; ?><br>
                            <span class="badge badge-info"><?php echo $a->sectorcode; ?></span>
                        </td>
                        <td><?php echo $a->coachfirst . ' ' . $a->coachlast; ?></td>
                        <td><?php echo userdate($a->timecreated, '%d/%m/%Y'); ?></td>
                        <td>
                            <?php if ($a->duedate): ?>
                                <?php if ($isexpired): ?>
                                    <span class="badge badge-danger"><?php echo userdate($a->duedate, '%d/%m/%Y'); ?></span>
                                <?php else: ?>
                                    <?php echo userdate($a->duedate, '%d/%m/%Y'); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo local_labeval_get_status_badge($a->status); ?></td>
                        <td>
                            <?php if ($a->sessionid && $a->percentage !== null): ?>
                                <span class="badge badge-<?php echo $a->percentage >= 70 ? 'success' : ($a->percentage >= 50 ? 'warning' : 'danger'); ?>">
                                    <?php echo round($a->percentage); ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a->status == 'pending' && $canevaluate): ?>
                                <a href="<?php echo new moodle_url('/local/labeval/evaluate.php', ['assignmentid' => $a->id]); ?>" 
                                   class="btn btn-success btn-sm">✏️ Valuta</a>
                            <?php elseif ($a->sessionid): ?>
                                <a href="<?php echo new moodle_url('/local/labeval/view_evaluation.php', ['sessionid' => $a->sessionid]); ?>" 
                                   class="btn btn-primary btn-sm">👁️ Dettagli</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
