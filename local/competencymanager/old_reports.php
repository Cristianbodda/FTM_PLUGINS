<?php
/**
 * Reports - Lista studenti del corso
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();

if (!$canviewall && !$isadmin) {
    if ($courseid && \local_competencymanager\report_generator::student_can_view_own_report($USER->id, $courseid)) {
        redirect(new moodle_url('/local/competencymanager/student_report.php', [
            'userid' => $USER->id,
            'courseid' => $courseid
        ]));
    }
    throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]));
$PAGE->set_title('Report Competenze');
$PAGE->set_heading('Report Competenze');

echo $OUTPUT->header();
?>

<style>
.report-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.report-header h2 { margin: 0 0 8px; }
.report-header p { margin: 0; opacity: 0.9; }
.card { border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.card-header { border-radius: 12px 12px 0 0 !important; }
.btn-toolbar { margin-bottom: 20px; }
.btn-toolbar .btn { margin-right: 10px; margin-bottom: 10px; }
.badge { padding: 8px 12px; font-size: 0.9em; }
.table th { background: #34495e; color: white; }
.table td { vertical-align: middle; }
.legend-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px; }
.legend-box .badge { margin-right: 5px; }
</style>

<div class="report-header">
    <h2>📊 Report Competenze</h2>
    <?php if ($course): ?>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
    <?php endif; ?>
</div>

<?php if ($isadmin || !$courseid): ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="form-inline">
            <label for="courseid" class="mr-2"><strong>Seleziona corso:</strong></label>
            <select name="courseid" id="courseid" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">-- Tutti i corsi --</option>
                <?php
                if ($isadmin) {
                    $allcourses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id, fullname');
                    foreach ($allcourses as $c) {
                        if ($c->id == 1) continue;
                        $selected = ($c->id == $courseid) ? 'selected' : '';
                        echo '<option value="' . $c->id . '" ' . $selected . '>' . format_string($c->fullname) . '</option>';
                    }
                } else {
                    $courses = enrol_get_my_courses();
                    foreach ($courses as $c) {
                        $selected = ($c->id == $courseid) ? 'selected' : '';
                        echo '<option value="' . $c->id . '" ' . $selected . '>' . format_string($c->fullname) . '</option>';
                    }
                }
                ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$courseid): ?>
<div class="alert alert-info">
    <h4>👆 Seleziona un corso</h4>
    <p>Per visualizzare i report degli studenti, seleziona prima un corso dal menu sopra.</p>
</div>
<?php echo $OUTPUT->footer(); exit; endif; ?>

<?php
$students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', 'u.lastname, u.firstname');

if (empty($students)):
?>
<div class="alert alert-warning">
    <h4>⚠️ Nessuno studente</h4>
    <p>Non ci sono studenti iscritti a questo corso.</p>
</div>
<?php echo $OUTPUT->footer(); exit; endif; ?>

<div class="btn-toolbar">
    <a href="<?php echo new moodle_url('/local/competencymanager/export.php', ['courseid' => $courseid, 'format' => 'csv']); ?>" class="btn btn-success">
        📥 Esporta CSV classe
    </a>
    <a href="<?php echo new moodle_url('/local/competencymanager/export.php', ['courseid' => $courseid, 'format' => 'excel']); ?>" class="btn btn-success">
        📥 Esporta Excel classe
    </a>
    <a href="<?php echo new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]); ?>" class="btn btn-secondary">
        🔐 Gestisci autorizzazioni
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">👥 Studenti del corso (<?php echo count($students); ?>)</h4>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>Studente</th>
                    <th>Email</th>
                    <th class="text-center">Competenze</th>
                    <th class="text-center">Punteggio</th>
                    <th class="text-center">Autorizzato</th>
                    <th class="text-center">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student):
                    $summary = \local_competencymanager\report_generator::get_student_summary($student->id, $courseid);
                    $isauthorized = \local_competencymanager\report_generator::student_can_view_own_report($student->id, $courseid);
                    
                    $scorebadge = 'secondary';
                    if ($summary['overall_percentage'] >= 80) $scorebadge = 'success';
                    elseif ($summary['overall_percentage'] >= 60) $scorebadge = 'primary';
                    elseif ($summary['overall_percentage'] >= 40) $scorebadge = 'warning';
                    elseif ($summary['total_questions'] > 0) $scorebadge = 'danger';
                ?>
                <tr>
                    <td><strong><?php echo fullname($student); ?></strong></td>
                    <td><?php echo $student->email; ?></td>
                    <td class="text-center"><?php echo $summary['total_competencies']; ?></td>
                    <td class="text-center">
                        <?php if ($summary['total_questions'] > 0): ?>
                        <span class="badge badge-<?php echo $scorebadge; ?>"><?php echo $summary['overall_percentage']; ?>%</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php echo $isauthorized ? '✅' : '❌'; ?>
                    </td>
                    <td class="text-center">
                        <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', ['userid' => $student->id, 'courseid' => $courseid]); ?>" class="btn btn-sm btn-primary">
                            📊 Report
                        </a>
                        <a href="<?php echo new moodle_url('/local/competencymanager/export.php', ['userid' => $student->id, 'courseid' => $courseid, 'format' => 'csv']); ?>" class="btn btn-sm btn-outline-secondary">
                            CSV
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="legend-box">
    <h5>📋 Legenda punteggi:</h5>
    <span class="badge badge-success">≥80%</span> Eccellente |
    <span class="badge badge-primary">60-79%</span> Buono |
    <span class="badge badge-warning">40-59%</span> Sufficiente |
    <span class="badge badge-danger">&lt;40%</span> Insufficiente
</div>

<div style="margin-top: 20px;">
    <a href="<?php echo new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]); ?>" class="btn btn-secondary">
        ← Torna alla Dashboard
    </a>
</div>

<?php
echo $OUTPUT->footer();
