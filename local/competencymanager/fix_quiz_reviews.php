<?php
/**
 * Fix Quiz Review Options
 * Aggiorna tutti i quiz del corso per mostrare le risposte corrette
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/fix_quiz_reviews.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Quiz Review Options');
$PAGE->set_heading('Fix Quiz Review Options');

echo $OUTPUT->header();

// Valore per attivare TUTTE le review options in TUTTI i momenti
$reviewvalue = 0x1F111; // = 127249

$quizzes = $DB->get_records('quiz', ['course' => $courseid]);

if ($confirm) {
    // Aggiorna tutti i quiz
    $updated = 0;
    foreach ($quizzes as $quiz) {
        $quiz->reviewattempt = $reviewvalue;
        $quiz->reviewcorrectness = $reviewvalue;
        $quiz->reviewmarks = $reviewvalue;
        $quiz->reviewspecificfeedback = $reviewvalue;
        $quiz->reviewgeneralfeedback = $reviewvalue;
        $quiz->reviewrightanswer = $reviewvalue;
        $quiz->reviewoverallfeedback = $reviewvalue;
        
        // Verifica se esiste reviewmaxmarks (Moodle 4.x)
        if (property_exists($quiz, 'reviewmaxmarks')) {
            $quiz->reviewmaxmarks = $reviewvalue;
        }
        
        $DB->update_record('quiz', $quiz);
        $updated++;
    }
    
    echo '<div style="max-width:600px;margin:40px auto;padding:20px">';
    echo '<div style="background:#d4edda;border-radius:12px;padding:30px;text-align:center">';
    echo '<div style="font-size:60px;margin-bottom:15px">✅</div>';
    echo '<h2 style="color:#155724">Quiz Aggiornati!</h2>';
    echo '<p><strong>' . $updated . '</strong> quiz sono stati aggiornati.</p>';
    echo '<p>Ora gli studenti potranno vedere le risposte corrette/sbagliate dopo aver completato il quiz.</p>';
    echo '</div>';
    echo '<p style="margin-top:20px;text-align:center">';
    echo '<a href="dashboard.php?courseid=' . $courseid . '" class="btn btn-primary">🏠 Torna alla Dashboard</a>';
    echo '</p>';
    echo '</div>';
    
} else {
    // Mostra anteprima
    echo '<div style="max-width:800px;margin:40px auto;padding:20px">';
    echo '<h2>🔧 Fix Review Options</h2>';
    echo '<p>Questo script aggiorna tutti i quiz del corso per permettere agli studenti di vedere:</p>';
    echo '<ul>';
    echo '<li>✅ Le risposte date</li>';
    echo '<li>✅ Se la risposta è corretta o sbagliata (verde/rosso)</li>';
    echo '<li>✅ I punti ottenuti</li>';
    echo '<li>✅ La risposta corretta</li>';
    echo '<li>✅ Il feedback</li>';
    echo '</ul>';
    
    echo '<h3>Quiz da aggiornare:</h3>';
    echo '<table style="width:100%;border-collapse:collapse">';
    echo '<tr style="background:#34495e;color:white"><th style="padding:10px;border:1px solid #ddd">ID</th><th style="padding:10px;border:1px solid #ddd">Nome Quiz</th><th style="padding:10px;border:1px solid #ddd">Review Attempt Attuale</th></tr>';
    
    foreach ($quizzes as $quiz) {
        $status = ($quiz->reviewattempt == $reviewvalue) ? '✅ OK' : '⚠️ Da aggiornare';
        echo '<tr>';
        echo '<td style="padding:10px;border:1px solid #ddd">' . $quiz->id . '</td>';
        echo '<td style="padding:10px;border:1px solid #ddd">' . format_string($quiz->name) . '</td>';
        echo '<td style="padding:10px;border:1px solid #ddd">' . $quiz->reviewattempt . ' ' . $status . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<div style="margin-top:30px;padding:20px;background:#fff3cd;border-radius:8px">';
    echo '<strong>⚠️ Attenzione:</strong> Questa operazione modificherà le impostazioni di tutti i ' . count($quizzes) . ' quiz.';
    echo '</div>';
    
    echo '<p style="margin-top:20px">';
    echo '<a href="?courseid=' . $courseid . '&confirm=1" class="btn btn-success" style="background:#28a745;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;margin-right:10px">✅ Conferma e Aggiorna</a>';
    echo '<a href="dashboard.php?courseid=' . $courseid . '" class="btn btn-secondary" style="background:#6c757d;color:white;padding:12px 24px;border-radius:6px;text-decoration:none">Annulla</a>';
    echo '</p>';
    echo '</div>';
}

echo $OUTPUT->footer();
