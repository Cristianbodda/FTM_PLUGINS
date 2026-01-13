<?php
/**
 * Index - Redirect to dashboard
 * @package    local_competencymanager
 */
require_once(__DIR__ . '/../../config.php');
require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    redirect(new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]));
} else {
    // Se non c'è courseid, mostra lista corsi disponibili
    $PAGE->set_url('/local/competencymanager/index.php');
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title('Competency Manager');
    $PAGE->set_heading('Competency Manager');
    
    echo $OUTPUT->header();
    echo '<div class="alert alert-info">Seleziona un corso dal menu per accedere al Competency Manager.</div>';
    
    // Lista corsi con quiz
    $courses = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname 
         FROM {course} c 
         JOIN {quiz} q ON q.course = c.id 
         ORDER BY c.fullname"
    );
    
    if ($courses) {
        echo '<h3>Corsi disponibili:</h3><ul class="list-group">';
        foreach ($courses as $course) {
            echo '<li class="list-group-item"><a href="dashboard.php?courseid=' . $course->id . '">' . 
                 htmlspecialchars($course->fullname) . '</a></li>';
        }
        echo '</ul>';
    }
    
    echo $OUTPUT->footer();
}
