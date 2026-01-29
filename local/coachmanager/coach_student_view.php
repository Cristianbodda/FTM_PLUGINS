<?php
// ============================================
// CoachManager - Vista Semplificata Studente
// ============================================
// Dashboard con 4 grandi bottoni azione per coach
// Quiz Competenze, Autovalutazione, Laboratori, Confronti
// ============================================

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/lib.php');

// Parametri
$studentid = required_param('studentid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Richiede login e capability
require_login();
$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

global $DB, $USER, $OUTPUT, $PAGE;

// Carica dati studente
$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$fullname = fullname($student);

// ============================================
// AUTO-DETECT COURSEID SE NON PASSATO
// ============================================
if ($courseid == 0) {
    // Cerca il corso principale dello studente dai suoi quiz attempts
    $sql = "SELECT q.course, c.fullname, c.shortname, COUNT(qa.id) as attempts
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON qa.quiz = q.id
            JOIN {course} c ON c.id = q.course
            WHERE qa.userid = ?
            AND qa.state = 'finished'
            GROUP BY q.course, c.fullname, c.shortname
            ORDER BY attempts DESC
            LIMIT 1";
    $maincourse = $DB->get_record_sql($sql, [$studentid]);
    if ($maincourse) {
        $courseid = $maincourse->course;
    }
}

// Carica info corso se disponibile
$course = null;
if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid]);
}

// ============================================
// FUNZIONI DI CONTROLLO DATI
// ============================================

/**
 * Verifica disponibilita quiz competenze
 */
function check_quiz_data($userid, $courseid) {
    global $DB;

    $result = [
        'available' => false,
        'count' => 0,
        'last_attempt' => null,
        'avg_score' => 0
    ];

    // Usa report_generator se disponibile
    if (class_exists('\\local_competencymanager\\report_generator')) {
        require_once(__DIR__ . '/../competencymanager/classes/report_generator.php');
        $quizzes = \local_competencymanager\report_generator::get_available_quizzes($userid, $courseid);
        if (!empty($quizzes)) {
            $result['available'] = true;
            $result['count'] = count($quizzes);
            // Trova ultimo tentativo
            $last = 0;
            foreach ($quizzes as $q) {
                if ($q->last_attempt > $last) {
                    $last = $q->last_attempt;
                }
            }
            $result['last_attempt'] = $last > 0 ? $last : null;
        }
    } else {
        // Fallback query diretta
        $params = ['userid' => $userid];
        $coursewhere = '';
        if ($courseid > 0) {
            $coursewhere = ' AND q.course = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT COUNT(DISTINCT q.id) as quiz_count, MAX(qa.timefinish) as last_attempt
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE qa.userid = :userid
                AND qa.state = 'finished'
                {$coursewhere}";

        $data = $DB->get_record_sql($sql, $params);
        if ($data && $data->quiz_count > 0) {
            $result['available'] = true;
            $result['count'] = $data->quiz_count;
            $result['last_attempt'] = $data->last_attempt;
        }
    }

    // Calcola media punteggio
    if ($result['available']) {
        $params = ['userid' => $userid];
        $coursewhere = '';
        if ($courseid > 0) {
            $coursewhere = ' AND q.course = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT AVG(qas.fraction) * 100 as avg_score
                FROM {quiz_attempts} quiza
                JOIN {quiz} q ON quiza.quiz = q.id
                JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE quiza.userid = :userid
                AND quiza.state = 'finished'
                {$coursewhere}
                AND qas.sequencenumber = (
                    SELECT MAX(qas2.sequencenumber)
                    FROM {question_attempt_steps} qas2
                    WHERE qas2.questionattemptid = qa.id
                )";

        $avgdata = $DB->get_record_sql($sql, $params);
        if ($avgdata && $avgdata->avg_score !== null) {
            $result['avg_score'] = round($avgdata->avg_score, 1);
        }
    }

    return $result;
}

/**
 * Verifica disponibilita autovalutazione
 */
function check_autovalutazione_data($userid) {
    global $DB;

    $result = [
        'available' => false,
        'count' => 0,
        'avg_level' => 0,
        'last_update' => null
    ];

    // Check local_selfassessment table
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_selfassessment')) {
        $sql = "SELECT COUNT(*) as cnt, AVG(level) as avg_level, MAX(timecreated) as last_update
                FROM {local_selfassessment}
                WHERE userid = ?";
        $data = $DB->get_record_sql($sql, [$userid]);

        if ($data && $data->cnt > 0) {
            $result['available'] = true;
            $result['count'] = $data->cnt;
            $result['avg_level'] = round($data->avg_level, 1);
            $result['last_update'] = $data->last_update;
            return $result;
        }
    }

    // Check local_coachmanager_assessment table
    if ($dbman->table_exists('local_coachmanager_assessment')) {
        $assessment = $DB->get_record('local_coachmanager_assessment', ['userid' => $userid]);
        if ($assessment) {
            $details = json_decode($assessment->details, true);
            if (!empty($details['competencies'])) {
                $sum = 0;
                $count = 0;
                foreach ($details['competencies'] as $comp) {
                    if (isset($comp['bloom_level'])) {
                        $sum += $comp['bloom_level'];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $result['available'] = true;
                    $result['count'] = $count;
                    $result['avg_level'] = round($sum / $count, 1);
                    $result['last_update'] = $assessment->timecreated ?? null;
                }
            }
        }
    }

    return $result;
}

/**
 * Verifica disponibilita valutazioni laboratorio
 */
function check_laboratori_data($userid) {
    global $DB;

    $result = [
        'available' => false,
        'count' => 0,
        'avg_score' => 0,
        'last_evaluation' => null,
        'pending' => 0
    ];

    // Usa labeval API se disponibile
    if (class_exists('\\local_labeval\\api')) {
        require_once(__DIR__ . '/../labeval/classes/api.php');
        $evaluations = \local_labeval\api::get_student_evaluations($userid, true);

        if (!empty($evaluations)) {
            $result['available'] = true;
            $result['count'] = count($evaluations);

            $sum = 0;
            $last = 0;
            foreach ($evaluations as $eval) {
                $sum += $eval->percentage ?? 0;
                if ($eval->timecompleted > $last) {
                    $last = $eval->timecompleted;
                }
            }
            $result['avg_score'] = round($sum / count($evaluations), 1);
            $result['last_evaluation'] = $last > 0 ? $last : null;
        }

        // Check pending
        $pending = \local_labeval\api::get_pending_assignments($userid);
        $result['pending'] = count($pending);

    } else {
        // Fallback: query diretta alle tabelle labeval
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_labeval_sessions') && $dbman->table_exists('local_labeval_assignments')) {
            $sql = "SELECT COUNT(*) as cnt, AVG(s.percentage) as avg_score, MAX(s.timecompleted) as last_eval
                    FROM {local_labeval_sessions} s
                    JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                    WHERE a.studentid = ?
                    AND s.status = 'completed'";

            $data = $DB->get_record_sql($sql, [$userid]);
            if ($data && $data->cnt > 0) {
                $result['available'] = true;
                $result['count'] = $data->cnt;
                $result['avg_score'] = round($data->avg_score, 1);
                $result['last_evaluation'] = $data->last_eval;
            }

            // Check pending
            $pending = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {local_labeval_assignments} WHERE studentid = ? AND status = 'pending'",
                [$userid]
            );
            $result['pending'] = $pending;
        }
    }

    return $result;
}

/**
 * Determina settore studente
 */
function get_student_sector($userid) {
    global $DB;

    // Cerca dalle competenze valutate nei quiz
    $sql = "SELECT c.idnumber
            FROM {quiz_attempts} qa
            JOIN {question_attempts} qat ON qat.questionusageid = qa.uniqueid
            JOIN {question} q ON q.id = qat.questionid
            JOIN {qbank_competenciesbyquestion} qcq ON qcq.questionid = q.id
            JOIN {competency} c ON c.id = qcq.competencyid
            WHERE qa.userid = ?
            AND qa.state = 'finished'
            LIMIT 1";

    $comp = $DB->get_record_sql($sql, [$userid]);
    if ($comp && !empty($comp->idnumber)) {
        $parts = explode('_', $comp->idnumber);
        if (!empty($parts[0])) {
            return strtoupper($parts[0]);
        }
    }

    return 'N/D';
}

/**
 * Determina settimana corrente studente
 */
function get_student_week($userid) {
    global $DB;

    // Cerca il primo quiz attempt per calcolare settimana
    $first = $DB->get_record_sql(
        "SELECT MIN(timefinish) as first_attempt FROM {quiz_attempts} WHERE userid = ? AND state = 'finished'",
        [$userid]
    );

    if ($first && $first->first_attempt > 0) {
        $weeks = ceil((time() - $first->first_attempt) / (7 * 24 * 60 * 60));
        return min(max($weeks, 1), 6);
    }

    return 1;
}

/**
 * Ottiene nome coach assegnato
 */
function get_assigned_coach($userid) {
    global $DB, $USER;

    // Per ora ritorna il coach corrente
    return fullname($USER);
}

// ============================================
// CARICA DATI
// ============================================
$quiz_data = check_quiz_data($studentid, $courseid);
$autoval_data = check_autovalutazione_data($studentid);
$lab_data = check_laboratori_data($studentid);
$sector = get_student_sector($studentid);
$week = get_student_week($studentid);
$coach_name = get_assigned_coach($studentid);

// ============================================
// SETUP PAGINA
// ============================================
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coachmanager/coach_student_view.php', ['studentid' => $studentid, 'courseid' => $courseid]));
$PAGE->set_title("Vista Studente - {$fullname}");
$PAGE->set_heading("Vista Studente - {$fullname}");
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
?>

<style>
/* ============================================
   FTM Design System - Coach Student View
   ============================================ */

/* Container principale */
.ftm-student-view {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

/* Header con info studente */
.ftm-student-header {
    background: linear-gradient(135deg, #F5A623 0%, #f7b84e 100%);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(245, 166, 35, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.ftm-student-header-left h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
}

.ftm-student-header-left .student-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 14px;
    opacity: 0.95;
}

.ftm-student-header-left .student-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ftm-student-header-right {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.ftm-header-btn {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    border: 2px solid white;
    color: white;
    background: transparent;
}

.ftm-header-btn:hover {
    background: white;
    color: #F5A623;
    text-decoration: none;
}

.ftm-header-btn.primary {
    background: white;
    color: #F5A623;
    border-color: white;
}

.ftm-header-btn.primary:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Griglia azioni principali */
.ftm-action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-bottom: 25px;
}

@media (max-width: 768px) {
    .ftm-action-grid {
        grid-template-columns: 1fr;
    }
}

/* Action Card - stile ftm_testsuite */
.ftm-action-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    border: 3px solid transparent;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    min-height: 280px;
    position: relative;
    overflow: hidden;
}

.ftm-action-card:hover {
    border-color: #F5A623;
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(245, 166, 35, 0.25);
    text-decoration: none;
    color: inherit;
}

.ftm-action-card .icon {
    font-size: 64px;
    margin-bottom: 15px;
    line-height: 1;
}

.ftm-action-card h4 {
    margin: 0 0 12px 0;
    color: #1A5A5A;
    font-weight: 700;
    font-size: 20px;
}

.ftm-action-card p {
    margin: 0 0 20px 0;
    color: #64748B;
    font-size: 14px;
    line-height: 1.5;
    flex-grow: 1;
}

/* Status indicator */
.ftm-status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.ftm-status-indicator.available {
    background: #d4edda;
    color: #155724;
}

.ftm-status-indicator.missing {
    background: #fff3cd;
    color: #856404;
}

.ftm-status-indicator.pending {
    background: #e2e3e5;
    color: #383d41;
}

/* Bottone azione card */
.ftm-card-btn {
    display: inline-block;
    padding: 12px 28px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    margin-top: auto;
}

.ftm-card-btn.primary {
    background: #1A5A5A;
    color: white;
}

.ftm-card-btn.primary:hover {
    background: #134545;
    box-shadow: 0 4px 15px rgba(26, 90, 90, 0.3);
    transform: translateY(-2px);
}

.ftm-card-btn.success {
    background: #28a745;
    color: white;
}

.ftm-card-btn.success:hover {
    background: #218838;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.ftm-card-btn.warning {
    background: #F5A623;
    color: white;
}

.ftm-card-btn.warning:hover {
    background: #e09000;
    box-shadow: 0 4px 15px rgba(245, 166, 35, 0.4);
}

.ftm-card-btn.secondary {
    background: #6c757d;
    color: white;
}

.ftm-card-btn.secondary:hover {
    background: #5a6268;
}

.ftm-card-btn.disabled {
    background: #ccc;
    color: #666;
    cursor: not-allowed;
    pointer-events: none;
}

/* Card colori per tipo */
.ftm-action-card.quiz {
    border-left: 5px solid #9b59b6;
}
.ftm-action-card.quiz:hover {
    border-color: #9b59b6;
}

.ftm-action-card.autoval {
    border-left: 5px solid #1abc9c;
}
.ftm-action-card.autoval:hover {
    border-color: #1abc9c;
}

.ftm-action-card.lab {
    border-left: 5px solid #e67e22;
}
.ftm-action-card.lab:hover {
    border-color: #e67e22;
}

.ftm-action-card.compare {
    border-left: 5px solid #3498db;
}
.ftm-action-card.compare:hover {
    border-color: #3498db;
}

/* Card statistiche rapide */
.ftm-quick-stats {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.ftm-quick-stat {
    text-align: center;
}

.ftm-quick-stat .value {
    font-size: 24px;
    font-weight: 700;
    color: #1A5A5A;
    line-height: 1;
}

.ftm-quick-stat .label {
    font-size: 11px;
    color: #64748B;
    text-transform: uppercase;
    margin-top: 4px;
}

/* Bottone stampa completa */
.ftm-print-section {
    margin-top: 25px;
}

.ftm-print-card {
    background: linear-gradient(135deg, #1A5A5A 0%, #134545 100%);
    border-radius: 16px;
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    flex-wrap: wrap;
    gap: 15px;
}

.ftm-print-card-left h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
}

.ftm-print-card-left p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.ftm-print-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: 8px;
    background: white;
    color: #1A5A5A;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.2s;
}

.ftm-print-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    text-decoration: none;
    color: #1A5A5A;
}

/* Data extra nelle card */
.ftm-card-extra {
    font-size: 12px;
    color: #64748B;
    margin-top: 10px;
}

/* Invito autovalutazione */
.ftm-invite-box {
    background: #fff3cd;
    border: 2px dashed #ffc107;
    border-radius: 12px;
    padding: 15px;
    margin-top: 15px;
    text-align: center;
}

.ftm-invite-box p {
    margin: 0 0 10px 0;
    color: #856404;
    font-size: 13px;
}

.ftm-invite-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #ffc107;
    color: #333;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.ftm-invite-btn:hover {
    background: #e0a800;
    transform: translateY(-1px);
}

/* Alert messages */
.ftm-alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 12px;
}

.ftm-alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.ftm-alert.warning {
    background: #fff3cd;
    color: #856404;
    border-left-color: #ffc107;
}

.ftm-alert.info {
    background: #e8f4f8;
    color: #0c5460;
    border-left-color: #1A5A5A;
}

/* Score colors */
.score-excellent { color: #28a745; }
.score-good { color: #17a2b8; }
.score-warning { color: #ffc107; }
.score-critical { color: #dc3545; }

/* Responsive */
@media (max-width: 600px) {
    .ftm-student-header {
        flex-direction: column;
        text-align: center;
    }

    .ftm-student-header-left .student-meta {
        justify-content: center;
    }

    .ftm-student-header-right {
        justify-content: center;
    }

    .ftm-print-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="ftm-student-view">

    <!-- Header con info studente -->
    <div class="ftm-student-header">
        <div class="ftm-student-header-left">
            <h1><?php echo $fullname; ?></h1>
            <div class="student-meta">
                <span title="Settore"><span style="font-size:18px;">&#127981;</span> <?php echo $sector; ?></span>
                <span title="Settimana"><span style="font-size:18px;">&#128197;</span> Settimana <?php echo $week; ?>/6</span>
                <span title="Coach"><span style="font-size:18px;">&#128100;</span> <?php echo $coach_name; ?></span>
                <?php if ($course): ?>
                <span title="Corso"><span style="font-size:18px;">&#128218;</span> <?php echo format_string($course->shortname); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ftm-student-header-right">
            <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_dashboard.php" class="ftm-header-btn">
                &#8592; Lista Studenti
            </a>
            <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/reports_v2.php?studentid=<?php echo $studentid; ?>" class="ftm-header-btn primary">
                Vista Avanzata
            </a>
        </div>
    </div>

    <!-- Griglia 4 azioni principali -->
    <div class="ftm-action-grid">

        <!-- 1. Quiz Competenze -->
        <div class="ftm-action-card quiz">
            <div class="icon">&#128221;</div>
            <h4>Quiz Competenze</h4>
            <p>Risultati dei quiz di verifica competenze tecniche. Visualizza punteggi per area, domande errate e andamento nel tempo.</p>

            <?php if ($quiz_data['available']): ?>
            <div class="ftm-status-indicator available">
                <span>&#10003;</span> Dati disponibili
            </div>
            <div class="ftm-quick-stats">
                <div class="ftm-quick-stat">
                    <div class="value <?php echo $quiz_data['avg_score'] >= 70 ? 'score-good' : ($quiz_data['avg_score'] >= 50 ? 'score-warning' : 'score-critical'); ?>">
                        <?php echo $quiz_data['avg_score']; ?>%
                    </div>
                    <div class="label">Media</div>
                </div>
                <div class="ftm-quick-stat">
                    <div class="value"><?php echo $quiz_data['count']; ?></div>
                    <div class="label">Quiz</div>
                </div>
            </div>
            <?php if ($quiz_data['last_attempt']): ?>
            <div class="ftm-card-extra">
                Ultimo tentativo: <?php echo userdate($quiz_data['last_attempt'], '%d/%m/%Y'); ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo $studentid; ?><?php echo $courseid ? '&courseid='.$courseid : ''; ?>"
               class="ftm-card-btn primary">
                Visualizza Report Quiz
            </a>
            <?php else: ?>
            <div class="ftm-status-indicator missing">
                <span>!</span> Nessun quiz completato
            </div>
            <p style="color: #856404; font-size: 13px;">Lo studente non ha ancora completato quiz di competenze.</p>
            <span class="ftm-card-btn disabled">Nessun dato</span>
            <?php endif; ?>
        </div>

        <!-- 2. Autovalutazione -->
        <div class="ftm-action-card autoval">
            <div class="icon">&#128100;</div>
            <h4>Autovalutazione</h4>
            <p>Autovalutazione delle competenze secondo i livelli Bloom. Confronta la percezione dello studente con i risultati oggettivi.</p>

            <?php if ($autoval_data['available']): ?>
            <div class="ftm-status-indicator available">
                <span>&#10003;</span> Compilata
            </div>
            <div class="ftm-quick-stats">
                <div class="ftm-quick-stat">
                    <div class="value score-good"><?php echo $autoval_data['avg_level']; ?></div>
                    <div class="label">Livello Medio</div>
                </div>
                <div class="ftm-quick-stat">
                    <div class="value"><?php echo $autoval_data['count']; ?></div>
                    <div class="label">Competenze</div>
                </div>
            </div>
            <?php if ($autoval_data['last_update']): ?>
            <div class="ftm-card-extra">
                Compilata il: <?php echo userdate($autoval_data['last_update'], '%d/%m/%Y'); ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo $CFG->wwwroot; ?>/local/selfassessment/student_report.php?userid=<?php echo $studentid; ?>"
               class="ftm-card-btn primary">
                Visualizza Autovalutazione
            </a>
            <?php else: ?>
            <div class="ftm-status-indicator missing">
                <span>!</span> Non compilata
            </div>
            <div class="ftm-invite-box">
                <p>Lo studente non ha ancora compilato l'autovalutazione.</p>
                <button class="ftm-invite-btn" onclick="sendAutovalReminder(<?php echo $studentid; ?>)">
                    <span>&#9993;</span> Invia Sollecito
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- 3. Laboratori -->
        <div class="ftm-action-card lab">
            <div class="icon">&#128300;</div>
            <h4>Laboratori</h4>
            <p>Valutazioni pratiche in laboratorio. Osservazione dei comportamenti professionali e competenze operative.</p>

            <?php if ($lab_data['available']): ?>
            <div class="ftm-status-indicator available">
                <span>&#10003;</span> Valutazioni presenti
            </div>
            <div class="ftm-quick-stats">
                <div class="ftm-quick-stat">
                    <div class="value <?php echo $lab_data['avg_score'] >= 70 ? 'score-good' : ($lab_data['avg_score'] >= 50 ? 'score-warning' : 'score-critical'); ?>">
                        <?php echo $lab_data['avg_score']; ?>%
                    </div>
                    <div class="label">Media</div>
                </div>
                <div class="ftm-quick-stat">
                    <div class="value"><?php echo $lab_data['count']; ?></div>
                    <div class="label">Valutazioni</div>
                </div>
                <?php if ($lab_data['pending'] > 0): ?>
                <div class="ftm-quick-stat">
                    <div class="value score-warning"><?php echo $lab_data['pending']; ?></div>
                    <div class="label">In Attesa</div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($lab_data['last_evaluation']): ?>
            <div class="ftm-card-extra">
                Ultima valutazione: <?php echo userdate($lab_data['last_evaluation'], '%d/%m/%Y'); ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo $CFG->wwwroot; ?>/local/labeval/reports.php?studentid=<?php echo $studentid; ?>"
               class="ftm-card-btn primary">
                Visualizza Valutazioni Lab
            </a>
            <?php elseif ($lab_data['pending'] > 0): ?>
            <div class="ftm-status-indicator pending">
                <span>&#9203;</span> <?php echo $lab_data['pending']; ?> in attesa
            </div>
            <p style="color: #383d41; font-size: 13px;">Ci sono valutazioni laboratorio assegnate ma non ancora completate.</p>
            <a href="<?php echo $CFG->wwwroot; ?>/local/labeval/index.php?studentid=<?php echo $studentid; ?>"
               class="ftm-card-btn warning">
                Completa Valutazioni
            </a>
            <?php else: ?>
            <div class="ftm-status-indicator missing">
                <span>!</span> Nessuna valutazione
            </div>
            <p style="color: #856404; font-size: 13px;">Non sono ancora state effettuate valutazioni pratiche in laboratorio.</p>
            <a href="<?php echo $CFG->wwwroot; ?>/local/labeval/index.php?action=assign&studentid=<?php echo $studentid; ?>"
               class="ftm-card-btn success">
                Assegna Valutazione
            </a>
            <?php endif; ?>
        </div>

        <!-- 4. Confronti -->
        <div class="ftm-action-card compare">
            <div class="icon">&#128200;</div>
            <h4>Confronti</h4>
            <p>Confronta questo studente con altri della stessa classe o settore. Analisi Gap tra autovalutazione e risultati oggettivi.</p>

            <?php
            // Per i confronti, serve almeno un dato disponibile
            $can_compare = $quiz_data['available'] || $autoval_data['available'] || $lab_data['available'];
            ?>

            <?php if ($can_compare): ?>
            <div class="ftm-status-indicator available">
                <span>&#10003;</span> Confronto possibile
            </div>
            <div class="ftm-quick-stats">
                <?php if ($quiz_data['available'] && $autoval_data['available']): ?>
                <?php
                // Calcola gap approssimativo
                $quiz_normalized = $quiz_data['avg_score'] / 100 * 6; // Normalizza a scala 1-6
                $gap = round($autoval_data['avg_level'] - $quiz_normalized, 1);
                $gap_class = abs($gap) <= 1 ? 'score-good' : (abs($gap) <= 2 ? 'score-warning' : 'score-critical');
                ?>
                <div class="ftm-quick-stat">
                    <div class="value <?php echo $gap_class; ?>"><?php echo ($gap > 0 ? '+' : '') . $gap; ?></div>
                    <div class="label">Gap Autoval</div>
                </div>
                <?php endif; ?>
            </div>
            <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/reports_v2.php?studentid=<?php echo $studentid; ?>&tab=radar"
               class="ftm-card-btn primary">
                Apri Radar Confronto
            </a>
            <?php else: ?>
            <div class="ftm-status-indicator missing">
                <span>!</span> Dati insufficienti
            </div>
            <p style="color: #856404; font-size: 13px;">Servono almeno quiz o autovalutazione per effettuare confronti.</p>
            <span class="ftm-card-btn disabled">Non disponibile</span>
            <?php endif; ?>
        </div>

    </div>

    <!-- Bottone Stampa Completa -->
    <div class="ftm-print-section">
        <div class="ftm-print-card">
            <div class="ftm-print-card-left">
                <h4><span style="font-size:20px;">&#128424;</span> Stampa Completa</h4>
                <p>Genera un report PDF completo con tutti i dati disponibili: quiz, autovalutazione, laboratori e confronti.</p>
            </div>
            <form action="<?php echo $CFG->wwwroot; ?>/local/coachmanager/export_word.php" method="post" style="display:inline;">
                <input type="hidden" name="studentid" value="<?php echo $studentid; ?>">
                <?php if ($courseid): ?>
                <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                <?php endif; ?>
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <button type="submit" class="ftm-print-btn">
                    <span style="font-size:18px;">&#128196;</span> Genera Report Completo
                </button>
            </form>
        </div>
    </div>

</div>

<script>
// Invia sollecito autovalutazione
function sendAutovalReminder(studentId) {
    if (confirm('Vuoi inviare un promemoria allo studente per completare l\'autovalutazione?')) {
        fetch('<?php echo $CFG->wwwroot; ?>/local/coachmanager/ajax_send_reminder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'studentid=' + studentId + '&type=autoval&sesskey=<?php echo sesskey(); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Promemoria inviato con successo!');
            } else {
                alert('Errore: ' + (data.message || 'Impossibile inviare il promemoria'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Errore di connessione');
        });
    }
}
</script>

<?php
echo $OUTPUT->footer();
