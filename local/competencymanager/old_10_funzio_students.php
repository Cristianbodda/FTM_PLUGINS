<?php
/**
 * Students List - Competency Manager
 * @package    local_competencymanager
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);
} else {
    $context = context_system::instance();
    $PAGE->set_context($context);
}

$PAGE->set_url('/local/competencymanager/students.php', ['courseid' => $courseid]);
$PAGE->set_title('Studenti - Competency Manager');
$PAGE->set_heading('Studenti');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Tab navigazione
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/competencymanager/index.php', ['courseid' => $courseid]), 'Dashboard'),
    new tabobject('students', new moodle_url('/local/competencymanager/students.php', ['courseid' => $courseid]), 'Studenti'),
    new tabobject('selfassessments', new moodle_url('/local/competencymanager/selfassessments.php', ['courseid' => $courseid]), 'Autovalutazioni'),
    new tabobject('reports', new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]), 'Report'),
];
echo $OUTPUT->tabtree($tabs, 'students');

if (!$courseid) {
    echo '<div class="alert alert-warning">Seleziona un corso per vedere gli studenti.</div>';
    echo $OUTPUT->footer();
    exit;
}

// Recupera studenti iscritti al corso
$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
        (SELECT COUNT(*) FROM {quiz_attempts} qa 
         JOIN {quiz} q ON q.id = qa.quiz 
         WHERE qa.userid = u.id AND q.course = :courseid1 AND qa.state = 'finished') as quiz_completati,
        (SELECT COUNT(*) FROM {local_selfassessment} sa WHERE sa.userid = u.id) as autovalutazioni
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid = :courseid2
        AND u.deleted = 0
        ORDER BY u.lastname, u.firstname";

$students = $DB->get_records_sql($sql, ['courseid1' => $courseid, 'courseid2' => $courseid]);
?>

<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
        <h3 class="mb-0">👥 Studenti del Corso: <?php echo htmlspecialchars($course->fullname); ?></h3>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Studente</th>
                    <th>Email</th>
                    <th>Quiz Completati</th>
                    <th>Autovalutazioni</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($student->lastname . ' ' . $student->firstname); ?></strong></td>
                    <td><?php echo htmlspecialchars($student->email); ?></td>
                    <td>
                        <?php if ($student->quiz_completati > 0): ?>
                            <span class="badge badge-success"><?php echo $student->quiz_completati; ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($student->autovalutazioni > 0): ?>
                            <span class="badge badge-info"><?php echo $student->autovalutazioni; ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $student->id; ?>" 
                           class="btn btn-sm btn-primary">📊 Report</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
echo $OUTPUT->footer();
