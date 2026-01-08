<?php
/**
 * Gestione autorizzazioni studenti - Competency Manager
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

$context = context_course::instance($courseid);
$course = get_course($courseid);

require_capability('moodle/grade:viewall', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]));
$PAGE->set_title('Gestione Autorizzazioni');
$PAGE->set_heading('Gestione Autorizzazioni');

// Handle actions
if ($action && $userid && confirm_sesskey()) {
    $authorized = ($action === 'authorize') ? 1 : 0;
    \local_competencymanager\report_generator::set_student_authorization($userid, $courseid, $authorized, $USER->id);
    
    $student = $DB->get_record('user', ['id' => $userid]);
    $message = $authorized 
        ? fullname($student) . ' può ora vedere il proprio report.'
        : fullname($student) . ' non può più vedere il proprio report.';
    
    redirect(
        new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]),
        $message,
        null,
        $authorized ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

// Bulk action
if (optional_param('bulkaction', '', PARAM_ALPHA) && confirm_sesskey()) {
    $bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
    $selectedusers = optional_param_array('selected', [], PARAM_INT);
    
    if (!empty($selectedusers)) {
        $authorized = ($bulkaction === 'authorize_all') ? 1 : 0;
        foreach ($selectedusers as $uid) {
            \local_competencymanager\report_generator::set_student_authorization($uid, $courseid, $authorized, $USER->id);
        }
        $count = count($selectedusers);
        $message = $authorized 
            ? "$count studenti autorizzati."
            : "$count studenti revocati.";
        
        redirect(
            new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]),
            $message,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', 'u.lastname, u.firstname');

echo $OUTPUT->header();
?>

<style>
.auth-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.card { border-radius: 12px; margin-bottom: 20px; }
.table th { background: #34495e; color: white; }
</style>

<div class="auth-header">
    <h2>🔐 Gestione Autorizzazioni</h2>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
    <p class="mb-0"><small>Autorizza gli studenti a visualizzare il proprio report competenze.</small></p>
</div>

<?php if (empty($students)): ?>
<div class="alert alert-warning">
    <h4>⚠️ Nessuno studente</h4>
    <p>Non ci sono studenti iscritti a questo corso.</p>
</div>
<?php else: ?>

<form method="post">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">👥 Studenti (<?php echo count($students); ?>)</h4>
            <div>
                <button type="submit" name="bulkaction" value="authorize_all" class="btn btn-success btn-sm">✅ Autorizza selezionati</button>
                <button type="submit" name="bulkaction" value="revoke_all" class="btn btn-warning btn-sm">❌ Revoca selezionati</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th>Studente</th>
                        <th>Email</th>
                        <th class="text-center">Stato</th>
                        <th class="text-center">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student):
                        $isauthorized = \local_competencymanager\report_generator::student_can_view_own_report($student->id, $courseid);
                    ?>
                    <tr>
                        <td><input type="checkbox" name="selected[]" value="<?php echo $student->id; ?>" class="student-checkbox"></td>
                        <td><strong><?php echo fullname($student); ?></strong></td>
                        <td><?php echo $student->email; ?></td>
                        <td class="text-center">
                            <?php if ($isauthorized): ?>
                            <span class="badge badge-success">✅ Autorizzato</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">❌ Non autorizzato</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($isauthorized): ?>
                            <a href="<?php echo new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid, 'userid' => $student->id, 'action' => 'revoke', 'sesskey' => sesskey()]); ?>" class="btn btn-sm btn-warning">
                                Revoca
                            </a>
                            <?php else: ?>
                            <a href="<?php echo new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid, 'userid' => $student->id, 'action' => 'authorize', 'sesskey' => sesskey()]); ?>" class="btn btn-sm btn-success">
                                Autorizza
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
function toggleAll(checkbox) {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checkbox.checked);
}
</script>

<?php endif; ?>

<div style="margin-top: 20px;">
    <a href="<?php echo new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]); ?>" class="btn btn-secondary">← Torna ai Report</a>
    <a href="<?php echo new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]); ?>" class="btn btn-secondary">🏠 Dashboard</a>
</div>

<?php
echo $OUTPUT->footer();
