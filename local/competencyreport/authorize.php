<?php
/**
 * Gestione autorizzazioni - permetti/nega accesso studenti ai propri report
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

$context = context_course::instance($courseid);
$course = get_course($courseid);

// Solo docenti/admin possono gestire le autorizzazioni
$canmanage = has_capability('moodle/grade:viewall', $context) || is_siteadmin();
if (!$canmanage) {
    throw new moodle_exception('nopermissions', 'error', '', 'manage authorizations');
}

// Processa azioni
if ($action && $userid && confirm_sesskey()) {
    $authorized = ($action === 'authorize');
    \local_competencyreport\report_generator::set_student_authorization($userid, $courseid, $authorized, $USER->id);
    
    $message = $authorized ? 'Studente autorizzato' : 'Autorizzazione revocata';
    redirect(
        new moodle_url('/local/competencyreport/authorize.php', ['courseid' => $courseid]),
        $message,
        2
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyreport/authorize.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('manageauth', 'local_competencyreport'));
$PAGE->set_heading(get_string('manageauth', 'local_competencyreport'));

echo $OUTPUT->header();

echo '<div class="mb-4">';
echo '<h2>🔐 Gestione autorizzazioni</h2>';
echo '<p class="lead">Corso: <strong>' . format_string($course->fullname) . '</strong></p>';
echo '<p>Autorizza gli studenti a visualizzare il proprio report competenze.</p>';
echo '</div>';

// Toolbar
echo '<div class="btn-toolbar mb-3">';
echo '<a href="' . new moodle_url('/local/competencyreport/index.php', ['courseid' => $courseid]) . '" class="btn btn-secondary mr-2">';
echo '← Torna ai report</a>';

// Azioni di massa
echo '<form method="post" class="form-inline" style="display: inline;">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
echo '<button type="submit" name="massaction" value="authorizeall" class="btn btn-success mr-2">';
echo '✅ Autorizza tutti</button>';
echo '<button type="submit" name="massaction" value="revokeall" class="btn btn-danger">';
echo '❌ Revoca tutti</button>';
echo '</form>';
echo '</div>';

// Processa azioni di massa
$massaction = optional_param('massaction', '', PARAM_ALPHA);
if ($massaction && confirm_sesskey()) {
    $students = get_enrolled_users($context, 'mod/quiz:attempt');
    $authorized = ($massaction === 'authorizeall');
    
    foreach ($students as $student) {
        \local_competencyreport\report_generator::set_student_authorization($student->id, $courseid, $authorized, $USER->id);
    }
    
    $message = $authorized ? 'Tutti gli studenti autorizzati' : 'Tutte le autorizzazioni revocate';
    redirect(
        new moodle_url('/local/competencyreport/authorize.php', ['courseid' => $courseid]),
        $message,
        2
    );
}

// Lista studenti
$students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', 'u.lastname, u.firstname');

echo '<div class="card">';
echo '<div class="card-header bg-primary text-white">';
echo '<h4 class="mb-0">👥 Studenti (' . count($students) . ')</h4>';
echo '</div>';
echo '<div class="card-body p-0">';

echo '<table class="table table-striped mb-0">';
echo '<thead class="thead-dark">';
echo '<tr>';
echo '<th>Studente</th>';
echo '<th>Email</th>';
echo '<th class="text-center">Stato</th>';
echo '<th class="text-center">Azioni</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($students as $student) {
    $isauthorized = \local_competencyreport\report_generator::student_can_view_own_report($student->id, $courseid);
    
    echo '<tr>';
    echo '<td><strong>' . fullname($student) . '</strong></td>';
    echo '<td>' . $student->email . '</td>';
    echo '<td class="text-center">';
    if ($isauthorized) {
        echo '<span class="badge badge-success p-2">✅ Autorizzato</span>';
    } else {
        echo '<span class="badge badge-secondary p-2">❌ Non autorizzato</span>';
    }
    echo '</td>';
    echo '<td class="text-center">';
    if ($isauthorized) {
        $revokeurl = new moodle_url('/local/competencyreport/authorize.php', [
            'courseid' => $courseid,
            'userid' => $student->id,
            'action' => 'revoke',
            'sesskey' => sesskey()
        ]);
        echo '<a href="' . $revokeurl . '" class="btn btn-sm btn-danger">Revoca</a>';
    } else {
        $authurl = new moodle_url('/local/competencyreport/authorize.php', [
            'courseid' => $courseid,
            'userid' => $student->id,
            'action' => 'authorize',
            'sesskey' => sesskey()
        ]);
        echo '<a href="' . $authurl . '" class="btn btn-sm btn-success">Autorizza</a>';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

// Info
echo '<div class="alert alert-info mt-4">';
echo '<h5>ℹ️ Come funziona</h5>';
echo '<ul class="mb-0">';
echo '<li><strong>Non autorizzato:</strong> Lo studente NON può vedere il proprio report</li>';
echo '<li><strong>Autorizzato:</strong> Lo studente può vedere SOLO il proprio report (non quelli degli altri)</li>';
echo '<li>Docenti e admin possono sempre vedere tutti i report</li>';
echo '</ul>';
echo '</div>';

echo $OUTPUT->footer();
