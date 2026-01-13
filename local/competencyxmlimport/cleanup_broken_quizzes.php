<?php
/**
 * Pulizia Quiz Rotti
 * 
 * Elimina i quiz che hanno course_modules non validi
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/cleanup_broken_quizzes.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Pulizia Quiz Rotti');
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Trova quiz senza course_module valido
$broken_quizzes = $DB->get_records_sql("
    SELECT q.* FROM {quiz} q
    WHERE q.course = ?
    AND NOT EXISTS (
        SELECT 1 FROM {course_modules} cm 
        JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
        WHERE cm.instance = q.id AND cm.course = q.course
    )
", [$courseid]);

// Trova course_modules orfani
$orphan_cms = $DB->get_records_sql("
    SELECT cm.* FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
    WHERE cm.course = ?
    AND NOT EXISTS (
        SELECT 1 FROM {quiz} q WHERE q.id = cm.instance
    )
", [$courseid]);

echo '<h3>🔍 Analisi Quiz Corso ' . format_string($course->shortname) . '</h3>';

if (empty($broken_quizzes) && empty($orphan_cms)) {
    echo '<div style="padding: 20px; background: #d4edda; border-radius: 8px; margin: 20px 0;">';
    echo '<h4>✅ Nessun problema trovato!</h4>';
    echo '<p>Tutti i quiz del corso sono configurati correttamente.</p>';
    echo '</div>';
    echo '<p><a href="dashboard.php?courseid=' . $courseid . '">← Torna alla Dashboard</a></p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<div style="padding: 20px; background: #f8d7da; border-radius: 8px; margin: 20px 0;">';
echo '<h4>⚠️ Problemi Trovati</h4>';

if (!empty($broken_quizzes)) {
    echo '<p><strong>Quiz senza course_module:</strong> ' . count($broken_quizzes) . '</p>';
    echo '<ul>';
    foreach ($broken_quizzes as $q) {
        echo '<li>' . format_string($q->name) . ' (ID: ' . $q->id . ')</li>';
    }
    echo '</ul>';
}

if (!empty($orphan_cms)) {
    echo '<p><strong>Course_modules orfani:</strong> ' . count($orphan_cms) . '</p>';
    echo '<ul>';
    foreach ($orphan_cms as $cm) {
        echo '<li>CM ID: ' . $cm->id . ' (instance: ' . $cm->instance . ')</li>';
    }
    echo '</ul>';
}
echo '</div>';

if ($confirm) {
    require_sesskey();
    
    echo '<h4>🗑️ Eliminazione in corso...</h4>';
    
    // Elimina quiz rotti
    foreach ($broken_quizzes as $q) {
        // Elimina slots
        $DB->delete_records('quiz_slots', ['quizid' => $q->id]);
        // Elimina sections
        $DB->delete_records('quiz_sections', ['quizid' => $q->id]);
        // Elimina quiz
        $DB->delete_records('quiz', ['id' => $q->id]);
        echo '<p>✅ Eliminato quiz: ' . format_string($q->name) . '</p>';
    }
    
    // Elimina course_modules orfani
    foreach ($orphan_cms as $cm) {
        // Rimuovi dalla sequence della sezione
        $section = $DB->get_record('course_sections', ['id' => $cm->section]);
        if ($section && $section->sequence) {
            $sequence = explode(',', $section->sequence);
            $sequence = array_filter($sequence, function($id) use ($cm) {
                return $id != $cm->id;
            });
            $DB->set_field('course_sections', 'sequence', implode(',', $sequence), ['id' => $section->id]);
        }
        
        // Elimina question_references
        $DB->delete_records('question_references', ['usingcontextid' => context_module::instance($cm->id)->id]);
        
        // Elimina context
        $DB->delete_records('context', ['contextlevel' => CONTEXT_MODULE, 'instanceid' => $cm->id]);
        
        // Elimina course_module
        $DB->delete_records('course_modules', ['id' => $cm->id]);
        echo '<p>✅ Eliminato course_module ID: ' . $cm->id . '</p>';
    }
    
    // Rebuild cache
    rebuild_course_cache($courseid, true);
    
    echo '<div style="padding: 20px; background: #d4edda; border-radius: 8px; margin: 20px 0;">';
    echo '<h4>✅ Pulizia completata!</h4>';
    echo '</div>';
    
    echo '<p><a href="dashboard.php?courseid=' . $courseid . '">← Torna alla Dashboard</a></p>';
    
} else {
    echo '<form method="post">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" style="padding: 12px 24px; background: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px;">🗑️ Elimina Quiz Rotti</button>';
    echo '<a href="dashboard.php?courseid=' . $courseid . '" style="padding: 12px 24px; background: #6c757d; color: white; border-radius: 6px; text-decoration: none;">Annulla</a>';
    echo '</form>';
}

echo $OUTPUT->footer();
