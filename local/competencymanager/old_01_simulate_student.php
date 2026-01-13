<?php
/**
 * SIMULATORE STUDENTE UNIVERSALE - Test Quiz e Autovalutazione
 * =============================================================
 * Simula uno studente che completa quiz e autovalutazioni
 * per verificare che tutto funzioni correttamente.
 * Funziona su TUTTI i corsi del sistema.
 * 
 * @package    local_competencymanager
 * @author     FTM Tools
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHANUMEXT);
$quizid = optional_param('quizid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Verifica login
require_login();

// Setup pagina base
$PAGE->set_url('/local/competencymanager/simulate_student.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Simulatore Studente Universale');
$PAGE->set_heading('🤖 Simulatore Studente - Test Automatico');

// Se abbiamo un corso, verifica permessi
if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_capability('moodle/course:manageactivities', $context);
    $PAGE->set_context($context);
}

echo $OUTPUT->header();
?>

<style>
.sim-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.sim-header { 
    background: linear-gradient(135deg, #9c27b0, #673ab7); 
    color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; 
}
.sim-card { 
    background: white; border-radius: 12px; padding: 20px; 
    margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
}
.sim-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
.status-info { color: #3498db; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #34495e; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #e8f4f8; }
.btn { 
    display: inline-block; padding: 10px 20px; border-radius: 8px; 
    text-decoration: none; margin: 5px; font-weight: 500; cursor: pointer;
    border: none; font-size: 14px;
}
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-purple { background: #9c27b0; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.progress-box { 
    background: #f8f9fa; border-radius: 8px; padding: 15px; 
    margin: 10px 0; border-left: 4px solid #3498db; 
}
.result-box { 
    background: #d4edda; border-radius: 8px; padding: 15px; 
    margin: 10px 0; border-left: 4px solid #27ae60; 
}
.error-box { 
    background: #f8d7da; border-radius: 8px; padding: 15px; 
    margin: 10px 0; border-left: 4px solid #e74c3c; 
}
.warning-box {
    background: #fff3cd; border-radius: 8px; padding: 15px; 
    margin: 10px 0; border-left: 4px solid #f39c12;
}
pre { background: #2d3436; color: #74b9ff; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
.student-select, .course-select { 
    padding: 10px; font-size: 14px; border-radius: 8px; 
    border: 1px solid #ddd; min-width: 300px; 
}
.course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.course-card {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s;
}
.course-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.course-card h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}
.course-stats {
    display: flex;
    gap: 15px;
    margin: 10px 0;
    font-size: 13px;
}
.course-stat {
    background: white;
    padding: 5px 10px;
    border-radius: 5px;
    border: 1px solid #e0e0e0;
}
.breadcrumb-nav {
    background: #ecf0f1;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.breadcrumb-nav a {
    color: #3498db;
    text-decoration: none;
}
.tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
}
.tab {
    padding: 12px 25px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.3s;
}
.tab:hover {
    background: #f8f9fa;
}
.tab.active {
    border-bottom-color: #9c27b0;
    font-weight: bold;
    color: #9c27b0;
}
</style>

<div class="sim-container">

<div class="sim-header">
    <h2>🤖 Simulatore Studente Universale</h2>
    <p>Simula automaticamente lo svolgimento di quiz e autovalutazioni per verificare il corretto funzionamento del sistema.</p>
</div>

<?php

// ============================================================================
// FUNZIONI DI SIMULAZIONE
// ============================================================================

/**
 * Simula il completamento di un quiz da parte di uno studente.
 */
function simulate_quiz_attempt($quizid, $userid, $randomize = true) {
    global $DB, $CFG;
    
    $results = [
        'success' => false,
        'message' => '',
        'details' => [],
        'score' => 0,
        'max_score' => 0,
        'questions_answered' => 0
    ];
    
    try {
        // Carica il quiz
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        
        $results['details'][] = "📝 Quiz: " . $quiz->name;
        
        // Crea oggetto quiz
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $userid);
        
        // Crea nuovo tentativo
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
        $quba->set_preferred_behaviour($quiz->preferredbehaviour ?? 'deferredfeedback');
        
        // Ottieni le domande del quiz
        $structure = $quizobj->get_structure();
        $slots = $structure->get_slots();
        
        if (empty($slots)) {
            $results['message'] = "❌ Nessuna domanda nel quiz";
            return $results;
        }
        
        $results['details'][] = "📋 Domande trovate: " . count($slots);
        
        // Crea il tentativo nel database
        $attempt = new stdClass();
        $attempt->quiz = $quiz->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
        $attempt->currentpage = 0;
        $attempt->state = \mod_quiz\quiz_attempt::IN_PROGRESS;
        $attempt->timestart = time();
        $attempt->timefinish = 0;
        $attempt->timemodified = time();
        $attempt->timemodifiedoffline = 0;
        $attempt->sumgrades = null;
        $attempt->gradednotificationsenttime = null;
        
        // Costruisci layout
        $layout = [];
        $slot_num = 1;
        foreach ($slots as $slot) {
            $layout[] = $slot_num;
            $slot_num++;
        }
        $attempt->layout = implode(',', $layout) . ',0';
        
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);
        $results['details'][] = "✅ Tentativo creato (ID: {$attempt->id})";
        
        // Aggiungi domande al question usage
        $slot_num = 1;
        foreach ($slots as $slot) {
            try {
                $question = \question_bank::load_question($slot->questionid);
                $quba->add_question($question, $slot->maxmark ?? 1);
                $slot_num++;
            } catch (Exception $e) {
                $results['details'][] = "⚠️ Errore caricamento domanda {$slot->questionid}: " . $e->getMessage();
            }
        }
        
        // Inizia tutte le domande
        $quba->start_all_questions();
        
        // Salva il question usage
        \question_engine::save_questions_usage_by_activity($quba);
        
        // Collega quba al tentativo
        $attempt->uniqueid = $quba->get_id();
        $DB->update_record('quiz_attempts', $attempt);
        
        // Rispondi alle domande
        $total_score = 0;
        $max_score = 0;
        $answered = 0;
        
        for ($slot = 1; $slot <= $quba->question_count(); $slot++) {
            try {
                $qa = $quba->get_question_attempt($slot);
                $question = $qa->get_question();
                
                // Per domande multichoice
                $order = $qa->get_step(0)->get_qt_var('_order');
                if ($order) {
                    $choices = explode(',', $order);
                    
                    if ($randomize) {
                        $choice_idx = array_rand($choices);
                        $response = ['answer' => $choice_idx];
                    } else {
                        $response = ['answer' => 0];
                    }
                    
                    $quba->process_action($slot, $response);
                    $answered++;
                }
                
                $max_score += $qa->get_max_mark();
                
            } catch (Exception $e) {
                $results['details'][] = "⚠️ Errore risposta slot $slot: " . $e->getMessage();
            }
        }
        
        // Finalizza il tentativo
        $quba->finish_all_questions(time());
        \question_engine::save_questions_usage_by_activity($quba);
        
        // Calcola punteggio
        $attempt->state = \mod_quiz\quiz_attempt::FINISHED;
        $attempt->timefinish = time();
        $attempt->sumgrades = $quba->get_total_mark();
        $DB->update_record('quiz_attempts', $attempt);
        
        // Aggiorna gradebook
        quiz_save_best_grade($quiz, $userid);
        
        $results['success'] = true;
        $results['message'] = "✅ Quiz completato!";
        $results['score'] = round($attempt->sumgrades, 2);
        $results['max_score'] = $max_score;
        $results['questions_answered'] = $answered;
        $results['attempt_id'] = $attempt->id;
        
    } catch (Exception $e) {
        $results['message'] = "❌ Errore: " . $e->getMessage();
        $results['details'][] = "Stack: " . $e->getTraceAsString();
    }
    
    return $results;
}

/**
 * Simula la compilazione di un'autovalutazione.
 */
function simulate_selfassessment($courseid, $userid, $randomize = true) {
    global $DB;
    
    $results = [
        'success' => false,
        'message' => '',
        'details' => [],
        'competencies_rated' => 0
    ];
    
    try {
        // Verifica se esiste la tabella local_selfassessment
        $tables = $DB->get_tables();
        if (!isset($tables['local_selfassessment'])) {
            $results['message'] = "⚠️ Plugin autovalutazione non installato";
            return $results;
        }
        
        // Ottieni competenze del corso
        $course_competencies = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.shortname, c.idnumber
            FROM {competency} c
            JOIN {competency_coursecomp} cc ON cc.competencyid = c.id
            WHERE cc.courseid = ?
        ", [$courseid]);
        
        if (empty($course_competencies)) {
            $results['message'] = "⚠️ Nessuna competenza nel corso";
            return $results;
        }
        
        $bloom_levels = [1, 2, 3, 4, 5, 6];
        $rated = 0;
        
        foreach ($course_competencies as $comp) {
            $level = $randomize ? $bloom_levels[array_rand($bloom_levels)] : 3;
            
            $existing = $DB->get_record('local_selfassessment', [
                'userid' => $userid,
                'competencyid' => $comp->id,
                'courseid' => $courseid
            ]);
            
            if ($existing) {
                $existing->bloomlevel = $level;
                $existing->timemodified = time();
                $DB->update_record('local_selfassessment', $existing);
            } else {
                $record = new stdClass();
                $record->userid = $userid;
                $record->competencyid = $comp->id;
                $record->courseid = $courseid;
                $record->bloomlevel = $level;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('local_selfassessment', $record);
            }
            $rated++;
        }
        
        $results['success'] = true;
        $results['message'] = "✅ Autovalutazione completata!";
        $results['competencies_rated'] = $rated;
        
    } catch (Exception $e) {
        $results['message'] = "❌ Errore: " . $e->getMessage();
    }
    
    return $results;
}

/**
 * Ottieni tutti i corsi con quiz.
 */
function get_courses_with_quizzes() {
    global $DB;
    
    $sql = "SELECT c.id, c.fullname, c.shortname, 
                   COUNT(DISTINCT q.id) as quiz_count,
                   COUNT(DISTINCT cc.competencyid) as competency_count,
                   (SELECT COUNT(*) FROM {user_enrolments} ue 
                    JOIN {enrol} e ON e.id = ue.enrolid 
                    WHERE e.courseid = c.id) as student_count
            FROM {course} c
            LEFT JOIN {quiz} q ON q.course = c.id
            LEFT JOIN {competency_coursecomp} cc ON cc.courseid = c.id
            WHERE c.id > 1
            GROUP BY c.id, c.fullname, c.shortname
            HAVING COUNT(DISTINCT q.id) > 0
            ORDER BY c.fullname";
    
    return $DB->get_records_sql($sql);
}

/**
 * Ottieni studenti del corso.
 */
function get_course_students($courseid) {
    $context = context_course::instance($courseid);
    return get_enrolled_users($context, 'mod/quiz:attempt');
}

/**
 * Ottieni quiz del corso.
 */
function get_course_quizzes($courseid) {
    global $DB;
    return $DB->get_records('quiz', ['course' => $courseid], 'name ASC');
}

// ============================================================================
// GESTIONE AZIONI
// ============================================================================

// Se non abbiamo un corso, mostra lista corsi
if (!$courseid) {
    $courses = get_courses_with_quizzes();
    ?>
    
    <div class="sim-card">
        <h3>📚 Seleziona un Corso</h3>
        <p>Scegli il corso su cui eseguire le simulazioni. Sono mostrati solo i corsi che contengono quiz.</p>
        
        <?php if (empty($courses)): ?>
            <div class="warning-box">
                <strong>⚠️ Nessun corso con quiz trovato.</strong>
                <p>Crea prima dei quiz in almeno un corso.</p>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                <div class="course-card">
                    <h4>📘 <?php echo format_string($course->fullname); ?></h4>
                    <p style="color: #666; font-size: 13px;"><?php echo $course->shortname; ?> (ID: <?php echo $course->id; ?>)</p>
                    <div class="course-stats">
                        <span class="course-stat">📝 <?php echo $course->quiz_count; ?> Quiz</span>
                        <span class="course-stat">🎯 <?php echo $course->competency_count; ?> Competenze</span>
                        <span class="course-stat">👥 <?php echo $course->student_count; ?> Studenti</span>
                    </div>
                    <a href="?courseid=<?php echo $course->id; ?>" class="btn btn-primary">
                        🚀 Apri Simulatore
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Simulazione GLOBALE -->
    <div class="sim-card">
        <h3>🌐 Simulazione Globale (Tutti i Corsi)</h3>
        <p>Esegui la simulazione su TUTTI i corsi contemporaneamente.</p>
        <div class="warning-box">
            <strong>⚠️ Attenzione:</strong> Questa operazione potrebbe richiedere molto tempo e creerà dati in tutti i corsi.
        </div>
        <a href="?action=simulate_global&confirm=1" class="btn btn-danger" onclick="return confirm('Vuoi davvero simulare su TUTTI i corsi? Questa operazione potrebbe richiedere diversi minuti.');">
            🌐 Simula su TUTTI i Corsi
        </a>
    </div>
    
    <?php
    
} elseif ($action == 'simulate_quiz' && $quizid && $userid && $confirm) {
    // Simulazione singolo quiz
    ?>
    <div class="breadcrumb-nav">
        <a href="?">🏠 Tutti i Corsi</a> → 
        <a href="?courseid=<?php echo $courseid; ?>"><?php echo format_string($course->shortname); ?></a> → 
        Simulazione Quiz
    </div>
    
    <div class="sim-card">
        <h3>🎯 Simulazione Quiz</h3>
        <?php
        $result = simulate_quiz_attempt($quizid, $userid, true);
        
        if ($result['success']) {
            echo '<div class="result-box">';
            echo '<h4>' . $result['message'] . '</h4>';
            echo '<p><strong>Punteggio:</strong> ' . $result['score'] . ' / ' . $result['max_score'] . '</p>';
            echo '<p><strong>Domande risposte:</strong> ' . $result['questions_answered'] . '</p>';
            echo '</div>';
        } else {
            echo '<div class="error-box"><h4>' . $result['message'] . '</h4></div>';
        }
        
        echo '<h4>📋 Log:</h4><pre>';
        foreach ($result['details'] as $d) echo htmlspecialchars($d) . "\n";
        echo '</pre>';
        ?>
        <p>
            <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">← Torna al corso</a>
            <a href="/local/competencymanager/reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $userid; ?>" class="btn btn-success">📊 Vedi Report</a>
        </p>
    </div>
    <?php
    
} elseif ($action == 'simulate_selfassess' && $userid && $confirm) {
    // Simulazione autovalutazione
    ?>
    <div class="breadcrumb-nav">
        <a href="?">🏠 Tutti i Corsi</a> → 
        <a href="?courseid=<?php echo $courseid; ?>"><?php echo format_string($course->shortname); ?></a> → 
        Simulazione Autovalutazione
    </div>
    
    <div class="sim-card">
        <h3>📊 Simulazione Autovalutazione</h3>
        <?php
        $result = simulate_selfassessment($courseid, $userid, true);
        
        if ($result['success']) {
            echo '<div class="result-box">';
            echo '<h4>' . $result['message'] . '</h4>';
            echo '<p><strong>Competenze valutate:</strong> ' . $result['competencies_rated'] . '</p>';
            echo '</div>';
        } else {
            echo '<div class="error-box"><h4>' . $result['message'] . '</h4></div>';
        }
        ?>
        <p>
            <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">← Torna al corso</a>
            <a href="/local/competencymanager/reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $userid; ?>" class="btn btn-success">📊 Vedi Report</a>
        </p>
    </div>
    <?php
    
} elseif ($action == 'simulate_all' && $userid && $confirm) {
    // Simulazione completa corso
    ?>
    <div class="breadcrumb-nav">
        <a href="?">🏠 Tutti i Corsi</a> → 
        <a href="?courseid=<?php echo $courseid; ?>"><?php echo format_string($course->shortname); ?></a> → 
        Simulazione Completa
    </div>
    
    <div class="sim-card">
        <h3>🚀 Simulazione COMPLETA - <?php echo format_string($course->shortname); ?></h3>
        <?php
        $quizzes = get_course_quizzes($courseid);
        $completed = 0;
        $errors = 0;
        
        foreach ($quizzes as $quiz) {
            echo '<div class="progress-box">';
            echo "<strong>📝 {$quiz->name}</strong> → ";
            
            $result = simulate_quiz_attempt($quiz->id, $userid, true);
            
            if ($result['success']) {
                echo '<span class="status-ok">✅ ' . $result['score'] . '/' . $result['max_score'] . '</span>';
                $completed++;
            } else {
                echo '<span class="status-error">❌ ' . $result['message'] . '</span>';
                $errors++;
            }
            echo '</div>';
            ob_flush(); flush();
        }
        
        // Autovalutazione
        echo '<div class="progress-box"><strong>📊 Autovalutazione</strong> → ';
        $sa = simulate_selfassessment($courseid, $userid, true);
        echo $sa['success'] ? '<span class="status-ok">✅</span>' : '<span class="status-warning">⚠️</span>';
        echo '</div>';
        ?>
        
        <div class="result-box">
            <h3>📊 Riepilogo</h3>
            <p>Quiz completati: <strong><?php echo $completed; ?>/<?php echo count($quizzes); ?></strong></p>
            <p>Errori: <strong><?php echo $errors; ?></strong></p>
        </div>
        
        <p>
            <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-primary">← Torna al corso</a>
            <a href="/local/competencymanager/reports.php?courseid=<?php echo $courseid; ?>&studentid=<?php echo $userid; ?>" class="btn btn-success">📊 Vedi Report Studente</a>
        </p>
    </div>
    <?php
    
} elseif ($action == 'simulate_global' && $confirm) {
    // Simulazione GLOBALE su tutti i corsi
    ?>
    <div class="breadcrumb-nav">
        <a href="?">🏠 Tutti i Corsi</a> → 
        Simulazione Globale
    </div>
    
    <div class="sim-card">
        <h3>🌐 Simulazione GLOBALE - Tutti i Corsi</h3>
        <?php
        $courses = get_courses_with_quizzes();
        $total_courses = count($courses);
        $total_quizzes = 0;
        $total_completed = 0;
        
        foreach ($courses as $course_item) {
            $cid = $course_item->id;
            echo '<div class="sim-card" style="margin: 10px 0;">';
            echo '<h4>📘 ' . format_string($course_item->fullname) . '</h4>';
            
            // Trova primo studente
            $students = get_course_students($cid);
            if (empty($students)) {
                echo '<p class="status-warning">⚠️ Nessuno studente iscritto</p>';
                echo '</div>';
                continue;
            }
            
            $student = reset($students);
            $quizzes = get_course_quizzes($cid);
            
            foreach ($quizzes as $quiz) {
                echo '<div class="progress-box" style="padding: 8px; margin: 5px 0;">';
                echo "<small>📝 {$quiz->name}</small> → ";
                
                $result = simulate_quiz_attempt($quiz->id, $student->id, true);
                $total_quizzes++;
                
                if ($result['success']) {
                    echo '<span class="status-ok">✅</span>';
                    $total_completed++;
                } else {
                    echo '<span class="status-error">❌</span>';
                }
                echo '</div>';
                ob_flush(); flush();
            }
            
            // Autovalutazione
            simulate_selfassessment($cid, $student->id, true);
            
            echo '</div>';
        }
        ?>
        
        <div class="result-box">
            <h3>📊 Riepilogo Globale</h3>
            <p>Corsi elaborati: <strong><?php echo $total_courses; ?></strong></p>
            <p>Quiz completati: <strong><?php echo $total_completed; ?>/<?php echo $total_quizzes; ?></strong></p>
        </div>
        
        <p><a href="?" class="btn btn-primary">← Torna alla lista corsi</a></p>
    </div>
    <?php
    
} else {
    // PAGINA CORSO - Selezione studente e azioni
    $students = get_course_students($courseid);
    $quizzes = get_course_quizzes($courseid);
    ?>
    
    <div class="breadcrumb-nav">
        <a href="?">🏠 Tutti i Corsi</a> → 
        <strong><?php echo format_string($course->fullname); ?></strong>
    </div>
    
    <!-- Info Corso -->
    <div class="sim-card" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
        <h3 style="color: white; border-color: rgba(255,255,255,0.3);">📘 <?php echo format_string($course->fullname); ?></h3>
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <div><strong><?php echo count($quizzes); ?></strong> Quiz</div>
            <div><strong><?php echo count($students); ?></strong> Studenti</div>
            <div>ID Corso: <strong><?php echo $courseid; ?></strong></div>
        </div>
    </div>
    
    <!-- Selezione Studente -->
    <div class="sim-card">
        <h3>👤 1. Seleziona Studente</h3>
        <?php if (empty($students)): ?>
            <div class="warning-box">
                <strong>⚠️ Nessuno studente iscritto.</strong>
                <p>Iscrivi almeno uno studente al corso per poter simulare.</p>
            </div>
        <?php else: ?>
            <select id="studentSelect" class="student-select">
                <option value="">-- Seleziona studente --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s->id; ?>"><?php echo fullname($s); ?> (<?php echo $s->email; ?>)</option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
    
    <!-- Quiz Singoli -->
    <div class="sim-card">
        <h3>📝 2. Simula Quiz Singolo</h3>
        <?php if (empty($quizzes)): ?>
            <p class="status-warning">⚠️ Nessun quiz nel corso.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>ID</th><th>Nome Quiz</th><th>Domande</th><th>Azione</th></tr></thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): 
                        $slots = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
                    ?>
                    <tr>
                        <td><?php echo $quiz->id; ?></td>
                        <td><?php echo format_string($quiz->name); ?></td>
                        <td><?php echo $slots; ?></td>
                        <td><button onclick="simQuiz(<?php echo $quiz->id; ?>)" class="btn btn-primary btn-sm">🎯 Simula</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Autovalutazione -->
    <div class="sim-card">
        <h3>📊 3. Simula Autovalutazione</h3>
        <button onclick="simSelfAssess()" class="btn btn-purple">🎯 Simula Autovalutazione</button>
    </div>
    
    <!-- Simulazione Completa -->
    <div class="sim-card">
        <h3>🚀 4. Simulazione COMPLETA</h3>
        <p>Completa <strong>TUTTI</strong> i quiz + autovalutazione in un click.</p>
        <button onclick="simAll()" class="btn btn-success" style="font-size: 16px; padding: 12px 25px;">
            🚀 AVVIA SIMULAZIONE COMPLETA
        </button>
    </div>
    
    <!-- Link -->
    <div class="sim-card">
        <h3>🔗 Link Utili</h3>
        <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🏠 Dashboard</a>
        <a href="diagnostics.php?courseid=<?php echo $courseid; ?>" class="btn btn-warning">🔬 Diagnostica</a>
        <a href="question_check.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">❓ Verifica Domande</a>
        <a href="?" class="btn btn-secondary">📚 Altri Corsi</a>
    </div>
    
    <script>
    function getStudent() {
        var s = document.getElementById('studentSelect');
        if (!s || !s.value) { alert('⚠️ Seleziona prima uno studente!'); return null; }
        return s.value;
    }
    function simQuiz(qid) {
        var u = getStudent(); if (!u) return;
        if (confirm('Simulare il quiz?')) location.href='?courseid=<?php echo $courseid; ?>&action=simulate_quiz&quizid='+qid+'&userid='+u+'&confirm=1';
    }
    function simSelfAssess() {
        var u = getStudent(); if (!u) return;
        if (confirm('Simulare autovalutazione?')) location.href='?courseid=<?php echo $courseid; ?>&action=simulate_selfassess&userid='+u+'&confirm=1';
    }
    function simAll() {
        var u = getStudent(); if (!u) return;
        if (confirm('⚠️ Simulare TUTTI i quiz + autovalutazione?')) location.href='?courseid=<?php echo $courseid; ?>&action=simulate_all&userid='+u+'&confirm=1';
    }
    </script>
    
    <?php
}
?>

</div>

<?php
echo $OUTPUT->footer();
