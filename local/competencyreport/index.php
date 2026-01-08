<?php
/**
 * Pagina principale Report Competenze
 * Lista studenti e accesso ai report
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

// Context
if ($courseid) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

// Verifica permessi - deve essere docente o admin
$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();

if (!$canviewall && !$isadmin) {
    // Se è uno studente, può vedere solo il proprio report se autorizzato
    if ($courseid && \local_competencyreport\report_generator::student_can_view_own_report($USER->id, $courseid)) {
        redirect(new moodle_url('/local/competencyreport/student.php', [
            'userid' => $USER->id,
            'courseid' => $courseid
        ]));
    }
    throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyreport/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'local_competencyreport'));
$PAGE->set_heading(get_string('pluginname', 'local_competencyreport'));

// CSS personalizzato
$PAGE->requires->css(new moodle_url('/local/competencyreport/styles.css'));

echo $OUTPUT->header();

// Header con stile
echo '<div class="competency-report-header">';
echo '<h2>📊 ' . get_string('pluginname', 'local_competencyreport') . '</h2>';
if ($course) {
    echo '<p class="lead">Corso: <strong>' . format_string($course->fullname) . '</strong></p>';
}
echo '</div>';

// Selezione corso (se admin)
if ($isadmin || !$courseid) {
    $courses = enrol_get_my_courses();
    
    echo '<div class="card mb-4"><div class="card-body">';
    echo '<form method="get" class="form-inline">';
    echo '<label for="courseid" class="mr-2"><strong>Seleziona corso:</strong></label>';
    echo '<select name="courseid" id="courseid" class="form-control mr-2" onchange="this.form.submit()">';
    echo '<option value="">-- Tutti i corsi --</option>';
    
    if ($isadmin) {
        $allcourses = $DB->get_records('course', ['visible' => 1], 'fullname', 'id, fullname');
        foreach ($allcourses as $c) {
            if ($c->id == 1) continue; // Skip site course
            $selected = ($c->id == $courseid) ? 'selected' : '';
            echo '<option value="' . $c->id . '" ' . $selected . '>' . format_string($c->fullname) . '</option>';
        }
    } else {
        foreach ($courses as $c) {
            $selected = ($c->id == $courseid) ? 'selected' : '';
            echo '<option value="' . $c->id . '" ' . $selected . '>' . format_string($c->fullname) . '</option>';
        }
    }
    echo '</select>';
    echo '</form>';
    echo '</div></div>';
}

// Se nessun corso selezionato
if (!$courseid) {
    echo '<div class="alert alert-info">';
    echo '<h4>👆 Seleziona un corso</h4>';
    echo '<p>Per visualizzare i report degli studenti, seleziona prima un corso dal menu sopra.</p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Ottieni studenti del corso
$students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', 'u.lastname, u.firstname');

if (empty($students)) {
    echo '<div class="alert alert-warning">';
    echo '<h4>⚠️ Nessuno studente</h4>';
    echo '<p>Non ci sono studenti iscritti a questo corso.</p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Toolbar
echo '<div class="btn-toolbar mb-3">';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-success mr-2">';
echo '📥 Esporta CSV classe</a>';
echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['courseid' => $courseid, 'format' => 'excel']) . '" class="btn btn-success mr-2">';
echo '📥 Esporta Excel classe</a>';
echo '<a href="' . new moodle_url('/local/competencyreport/authorize.php', ['courseid' => $courseid]) . '" class="btn btn-secondary">';
echo '🔐 Gestisci autorizzazioni</a>';
echo '</div>';

// Tabella studenti
echo '<div class="card">';
echo '<div class="card-header bg-primary text-white">';
echo '<h4 class="mb-0">👥 Studenti del corso (' . count($students) . ')</h4>';
echo '</div>';
echo '<div class="card-body p-0">';

echo '<table class="table table-striped table-hover mb-0">';
echo '<thead class="thead-dark">';
echo '<tr>';
echo '<th>Studente</th>';
echo '<th>Email</th>';
echo '<th class="text-center">Competenze</th>';
echo '<th class="text-center">Punteggio</th>';
echo '<th class="text-center">Autorizzato</th>';
echo '<th class="text-center">Azioni</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($students as $student) {
    // Calcola riepilogo veloce
    $summary = \local_competencyreport\report_generator::get_student_summary($student->id, $courseid);
    $isauthorized = \local_competencyreport\report_generator::student_can_view_own_report($student->id, $courseid);
    
    // Badge punteggio
    $scorebadge = 'secondary';
    if ($summary['overall_percentage'] >= 80) $scorebadge = 'success';
    elseif ($summary['overall_percentage'] >= 60) $scorebadge = 'primary';
    elseif ($summary['overall_percentage'] >= 40) $scorebadge = 'warning';
    elseif ($summary['total_questions'] > 0) $scorebadge = 'danger';
    
    echo '<tr>';
    echo '<td><strong>' . fullname($student) . '</strong></td>';
    echo '<td>' . $student->email . '</td>';
    echo '<td class="text-center">' . $summary['total_competencies'] . '</td>';
    echo '<td class="text-center">';
    if ($summary['total_questions'] > 0) {
        echo '<span class="badge badge-' . $scorebadge . ' p-2">' . $summary['overall_percentage'] . '%</span>';
    } else {
        echo '<span class="text-muted">-</span>';
    }
    echo '</td>';
    echo '<td class="text-center">';
    echo $isauthorized ? '✅' : '❌';
    echo '</td>';
    echo '<td class="text-center">';
    echo '<a href="' . new moodle_url('/local/competencyreport/student.php', ['userid' => $student->id, 'courseid' => $courseid]) . '" class="btn btn-sm btn-primary">';
    echo '📊 Report</a> ';
    echo '<a href="' . new moodle_url('/local/competencyreport/export.php', ['userid' => $student->id, 'courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-sm btn-outline-secondary">';
    echo 'CSV</a>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

// Legenda
echo '<div class="card mt-4">';
echo '<div class="card-body">';
echo '<h5>📋 Legenda punteggi:</h5>';
echo '<span class="badge badge-success p-2 mr-2">≥80%</span> Eccellente | ';
echo '<span class="badge badge-primary p-2 mr-2">60-79%</span> Buono | ';
echo '<span class="badge badge-warning p-2 mr-2">40-59%</span> Sufficiente | ';
echo '<span class="badge badge-danger p-2 mr-2">&lt;40%</span> Insufficiente';
echo '</div></div>';

echo $OUTPUT->footer();
