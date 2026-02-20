<?php
/**
 * Data Generator - Genera dati test per FTM Test Suite
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_testsuite;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per la generazione di dati test
 */
class data_generator {
    
    /** @var array Log delle operazioni */
    private $log = [];
    
    /** @var array Utenti test */
    private $testusers = [];
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_test_users();
    }
    
    /**
     * Carica gli utenti test
     */
    private function load_test_users() {
        global $DB;
        $this->testusers = $DB->get_records('local_ftm_testsuite_users');
    }
    
    /**
     * Ottiene il log delle operazioni
     * @return array
     */
    public function get_log() {
        return $this->log;
    }
    
    /**
     * Aggiunge una voce al log
     * @param string $message
     * @param string $type info|success|warning|error
     */
    private function add_log($message, $type = 'info') {
        $this->log[] = [
            'time' => date('H:i:s'),
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Genera tutti i dati test per un corso
     * @param int $courseid ID corso (0 = tutti)
     * @return array Statistiche generazione
     */
    public function generate_all($courseid = 0) {
        global $DB;
        
        $stats = [
            'quiz_attempts' => 0,
            'selfassessments' => 0,
            'labeval_sessions' => 0,
            'errors' => []
        ];
        
        if (empty($this->testusers)) {
            $stats['errors'][] = 'Nessun utente test trovato. Creare prima gli utenti test.';
            return $stats;
        }
        
        // Ottieni corsi da processare
        if ($courseid > 0) {
            $courses = [$DB->get_record('course', ['id' => $courseid])];
        } else {
            // Tutti i corsi con quiz
            $courses = $DB->get_records_sql("
                SELECT DISTINCT c.*
                FROM {course} c
                JOIN {quiz} q ON q.course = c.id
                WHERE c.id > 1
                ORDER BY c.fullname
            ");
        }
        
        foreach ($courses as $course) {
            $this->add_log("Elaborazione corso: {$course->fullname}", 'info');
            
            // Iscrivi utenti test al corso
            $this->enrol_test_users($course->id);
            
            // Genera dati quiz
            $quiz_count = $this->generate_quiz_data($course->id);
            $stats['quiz_attempts'] += $quiz_count;
            
            // Genera autovalutazioni
            $sa_count = $this->generate_selfassessment_data($course->id);
            $stats['selfassessments'] += $sa_count;
            
            // Genera valutazioni lab
            $lab_count = $this->generate_labeval_data($course->id);
            $stats['labeval_sessions'] += $lab_count;
        }
        
        $this->add_log("Generazione completata!", 'success');
        
        return $stats;
    }
    
    /**
     * Iscrivi gli utenti test al corso
     * @param int $courseid
     */
    private function enrol_test_users($courseid) {
        global $DB;
        
        // Trova il metodo di iscrizione manuale
        $enrol = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
            'status' => 0
        ]);
        
        if (!$enrol) {
            // Crea metodo iscrizione manuale
            $enrol = new \stdClass();
            $enrol->enrol = 'manual';
            $enrol->courseid = $courseid;
            $enrol->status = 0;
            $enrol->roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
            $enrol->timecreated = time();
            $enrol->timemodified = time();
            $enrol->id = $DB->insert_record('enrol', $enrol);
        }
        
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $context = \context_course::instance($courseid);
        
        foreach ($this->testusers as $tu) {
            // Verifica se già iscritto
            $existing = $DB->get_record('user_enrolments', [
                'enrolid' => $enrol->id,
                'userid' => $tu->userid
            ]);
            
            if (!$existing) {
                // Iscrivi
                $ue = new \stdClass();
                $ue->enrolid = $enrol->id;
                $ue->userid = $tu->userid;
                $ue->status = 0;
                $ue->timestart = 0;
                $ue->timeend = 0;
                $ue->timecreated = time();
                $ue->timemodified = time();
                $DB->insert_record('user_enrolments', $ue);
                
                // Assegna ruolo
                role_assign($studentroleid, $tu->userid, $context->id);
                
                $this->add_log("Iscritto utente {$tu->username} al corso", 'info');
            }
        }
    }
    
    /**
     * Genera tentativi quiz per gli utenti test
     * @param int $courseid
     * @return int Numero tentativi creati
     */
    public function generate_quiz_data($courseid) {
        global $DB;
        
        $count = 0;
        
        // Ottieni quiz del corso
        $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
        
        if (empty($quizzes)) {
            $this->add_log("Nessun quiz trovato nel corso {$courseid}", 'warning');
            return 0;
        }
        
        foreach ($quizzes as $quiz) {
            // Ottieni domande del quiz (Moodle 4.x)
            $questions = $this->get_quiz_questions($quiz->id);
            
            if (empty($questions)) {
                $this->add_log("Quiz {$quiz->name}: nessuna domanda trovata", 'warning');
                continue;
            }
            
            foreach ($this->testusers as $tu) {
                // Verifica se esiste già un tentativo
                $existing = $DB->get_record('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'userid' => $tu->userid,
                    'state' => 'finished'
                ]);
                
                if ($existing) {
                    continue; // Già esistente
                }
                
                // Crea tentativo
                $attempt_count = $this->create_quiz_attempt($quiz, $tu, $questions);
                if ($attempt_count > 0) {
                    $count++;
                    $this->add_log("Creato tentativo quiz '{$quiz->name}' per {$tu->username} ({$tu->quiz_percentage}%)", 'success');
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Ottiene le domande di un quiz (compatibile Moodle 4.x)
     * @param int $quizid
     * @return array
     */
    private function get_quiz_questions($quizid) {
        global $DB;
        
        // Query per Moodle 4.x con question_references
        return $DB->get_records_sql("
            SELECT qs.id as slotid, qs.slot, qv.questionid, q.qtype, q.name as questionname
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id 
                AND qr.component = 'mod_quiz' 
                AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            WHERE qs.quizid = ?
            ORDER BY qs.slot
        ", [$quizid]);
    }
    
    /**
     * Crea un tentativo quiz completo
     * @param object $quiz
     * @param object $testuser
     * @param array $questions
     * @return int 1 se creato, 0 se errore
     */
    private function create_quiz_attempt($quiz, $testuser, $questions) {
        global $DB;
        
        try {
            // 1. Crea question_usage
            $quba = new \stdClass();
            $quba->contextid = \context_module::instance(
                $DB->get_field('course_modules', 'id', [
                    'course' => $quiz->course,
                    'instance' => $quiz->id,
                    'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])
                ])
            )->id;
            $quba->component = 'mod_quiz';
            $quba->preferredbehaviour = 'deferredfeedback';
            $qubaid = $DB->insert_record('question_usages', $quba);
            
            // 2. Crea quiz_attempt
            $attempt = new \stdClass();
            $attempt->quiz = $quiz->id;
            $attempt->userid = $testuser->userid;
            $attempt->attempt = 1;
            $attempt->uniqueid = $qubaid;
            $attempt->layout = implode(',', array_map(fn($q) => $q->slot, $questions)) . ',0';
            $attempt->currentpage = 0;
            $attempt->preview = 0;
            $attempt->state = 'finished';
            $attempt->timestart = time() - 3600; // 1 ora fa
            $attempt->timefinish = time() - 1800; // 30 min fa
            $attempt->timemodified = time();
            $attempt->timecheckstate = null;
            $attempt->sumgrades = 0;
            $attemptid = $DB->insert_record('quiz_attempts', $attempt);
            
            // 3. Crea question_attempts e risposte
            $target_percentage = $testuser->quiz_percentage / 100;
            $total_grade = 0;
            $slot = 0;
            
            foreach ($questions as $q) {
                $slot++;
                
                // Determina se risposta corretta (basato su percentuale target)
                $is_correct = (mt_rand(1, 100) / 100) <= $target_percentage;
                $fraction = $is_correct ? 1.0 : 0.0;
                
                // Crea question_attempt
                $qa = new \stdClass();
                $qa->questionusageid = $qubaid;
                $qa->slot = $slot;
                $qa->behaviour = 'deferredfeedback';
                $qa->questionid = $q->questionid;
                $qa->variant = 1;
                $qa->maxmark = 1.0;
                $qa->minfraction = 0;
                $qa->maxfraction = 1;
                $qa->flagged = 0;
                $qa->questionsummary = $q->questionname;
                $qa->rightanswer = 'Risposta corretta';
                $qa->responsesummary = $is_correct ? 'Risposta corretta' : 'Risposta errata';
                $qa->timemodified = time();
                $qaid = $DB->insert_record('question_attempts', $qa);
                
                // Crea question_attempt_step (stato finale)
                $step = new \stdClass();
                $step->questionattemptid = $qaid;
                $step->sequencenumber = 2; // 0=start, 1=answer, 2=finish
                $step->state = $is_correct ? 'gradedright' : 'gradedwrong';
                $step->fraction = $fraction;
                $step->timecreated = time();
                $step->userid = $testuser->userid;
                $DB->insert_record('question_attempt_steps', $step);
                
                $total_grade += $fraction;
            }
            
            // 4. Aggiorna sumgrades
            $DB->set_field('quiz_attempts', 'sumgrades', $total_grade, ['id' => $attemptid]);
            
            return 1;
            
        } catch (\Exception $e) {
            $this->add_log("Errore creazione tentativo: " . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    /**
     * Genera autovalutazioni per gli utenti test
     * @param int $courseid
     * @return int Numero autovalutazioni create
     */
    public function generate_selfassessment_data($courseid) {
        global $DB;
        
        $count = 0;
        
        // Ottieni competenze del corso tramite le domande dei quiz
        $competencies = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.idnumber, c.shortname
            FROM {competency} c
            JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
            JOIN {question_versions} qv ON qv.questionid = qc.questionid
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
            JOIN {quiz_slots} qs ON qs.id = qr.itemid AND qr.component = 'mod_quiz'
            JOIN {quiz} q ON q.id = qs.quizid
            WHERE q.course = ?
            ORDER BY c.idnumber
        ", [$courseid]);
        
        if (empty($competencies)) {
            $this->add_log("Nessuna competenza trovata per il corso {$courseid}", 'warning');
            return 0;
        }
        
        // Distribuzione Bloom strategica per creare gap intenzionali
        // Per studente 30%: Bloom alto (sovrastima)
        // Per studente 65%: Bloom medio (allineato)  
        // Per studente 95%: Bloom basso/medio (sottostima)
        $bloom_distributions = [
            'low30' => [4, 5, 5, 6, 6, 6], // Sovrastima (pensa di sapere ma non sa)
            'medium65' => [3, 3, 4, 4, 4, 5], // Allineato
            'high95' => [2, 3, 3, 4, 4, 5]  // Sottostima (sa più di quanto pensa)
        ];
        
        foreach ($this->testusers as $tu) {
            $bloom_dist = $bloom_distributions[$tu->testprofile] ?? [3, 3, 4, 4, 5, 5];
            $comp_index = 0;
            
            foreach ($competencies as $comp) {
                // Verifica se esiste già
                $existing = $DB->get_record('local_selfassessment', [
                    'userid' => $tu->userid,
                    'competencyid' => $comp->id
                ]);
                
                if ($existing) {
                    continue;
                }
                
                // Scegli livello Bloom dalla distribuzione
                $bloom_level = $bloom_dist[$comp_index % count($bloom_dist)];
                
                // Crea autovalutazione
                $sa = new \stdClass();
                $sa->userid = $tu->userid;
                $sa->competencyid = $comp->id;
                $sa->level = $bloom_level;  // Campo corretto: level (non bloomlevel)
                $sa->timecreated = time();
                $sa->timemodified = time();
                
                $DB->insert_record('local_selfassessment', $sa);
                $count++;
                $comp_index++;
            }
            
            $this->add_log("Create {$comp_index} autovalutazioni per {$tu->username}", 'success');
        }
        
        return $count;
    }
    
    /**
     * Genera valutazioni laboratorio per gli utenti test
     * @param int $courseid
     * @return int Numero sessioni create
     */
    public function generate_labeval_data($courseid) {
        global $DB, $USER;
        
        $count = 0;
        
        // Determina il settore del corso dal nome o dalle competenze
        $course = $DB->get_record('course', ['id' => $courseid]);
        $sector = $this->detect_sector($course);
        
        if (!$sector) {
            $this->add_log("Settore non rilevato per corso {$course->fullname}", 'warning');
            return 0;
        }
        
        // Trova template per questo settore
        $templates = $DB->get_records('local_labeval_templates', [
            'sectorcode' => $sector,
            'status' => 'active'
        ]);
        
        if (empty($templates)) {
            $this->add_log("Nessun template labeval per settore {$sector}", 'warning');
            return 0;
        }
        
        foreach ($templates as $template) {
            // Ottieni comportamenti del template
            $behaviors = $DB->get_records('local_labeval_behaviors', ['templateid' => $template->id], 'sortorder');
            
            if (empty($behaviors)) {
                continue;
            }
            
            foreach ($this->testusers as $tu) {
                // Verifica se esiste già un assignment completato
                $existing = $DB->get_record_sql("
                    SELECT a.id
                    FROM {local_labeval_assignments} a
                    JOIN {local_labeval_sessions} s ON s.assignmentid = a.id
                    WHERE a.templateid = ? AND a.studentid = ? AND s.status = 'completed'
                ", [$template->id, $tu->userid]);
                
                if ($existing) {
                    continue;
                }
                
                // Crea assignment
                $assignment = new \stdClass();
                $assignment->templateid = $template->id;
                $assignment->studentid = $tu->userid;
                $assignment->assignedby = $USER->id;
                $assignment->courseid = $courseid;
                $assignment->status = 'completed';
                $assignment->timecreated = time();
                $assignment->timemodified = time();
                $assignmentid = $DB->insert_record('local_labeval_assignments', $assignment);
                
                // Crea session
                $session = new \stdClass();
                $session->assignmentid = $assignmentid;
                $session->assessorid = $USER->id;
                $session->status = 'completed';
                $session->notes = 'Sessione test generata automaticamente';
                $session->timecreated = time();
                $session->timecompleted = time();
                $sessionid = $DB->insert_record('local_labeval_sessions', $session);
                
                // Genera ratings basati su percentuale target
                $target_pct = $tu->quiz_percentage / 100;
                $total_score = 0;
                $max_score = 0;
                
                foreach ($behaviors as $behavior) {
                    // Decidi rating basato su target
                    $rand = mt_rand(1, 100) / 100;
                    if ($rand <= $target_pct * 0.8) {
                        $rating = 3; // Adeguato
                    } elseif ($rand <= $target_pct * 1.2) {
                        $rating = 1; // Parziale
                    } else {
                        $rating = 0; // Non osservato
                    }
                    
                    // Crea rating
                    $r = new \stdClass();
                    $r->sessionid = $sessionid;
                    $r->behaviorid = $behavior->id;
                    $r->rating = $rating;
                    $r->notes = '';
                    $DB->insert_record('local_labeval_ratings', $r);
                    
                    $total_score += $rating;
                    $max_score += 3;
                }
                
                // Aggiorna session con punteggi
                $percentage = $max_score > 0 ? round(($total_score / $max_score) * 100, 2) : 0;
                $DB->update_record('local_labeval_sessions', (object)[
                    'id' => $sessionid,
                    'totalscore' => $total_score,
                    'maxscore' => $max_score,
                    'percentage' => $percentage
                ]);
                
                // Aggiorna/crea comp_scores
                $this->update_labeval_comp_scores($sessionid);
                
                $count++;
                $this->add_log("Creata valutazione lab '{$template->name}' per {$tu->username}", 'success');
            }
        }
        
        return $count;
    }
    
    /**
     * Rileva il settore di un corso
     * @param object $course
     * @return string|null
     */
    private function detect_sector($course) {
        $name = strtolower($course->fullname . ' ' . $course->shortname);
        
        if (strpos($name, 'mecc') !== false || strpos($name, 'meccanica') !== false) {
            return 'MECCANICA';
        }
        if (strpos($name, 'auto') !== false || strpos($name, 'veicol') !== false) {
            return 'AUTOMOBILE';
        }
        if (strpos($name, 'chim') !== false || strpos($name, 'farm') !== false) {
            return 'CHIMFARM';
        }
        if (strpos($name, 'elettr') !== false) {
            return 'ELETTRICITA';
        }
        if (strpos($name, 'logist') !== false) {
            return 'LOGISTICA';
        }
        
        return null;
    }
    
    /**
     * Aggiorna la cache comp_scores per una sessione labeval
     * @param int $sessionid
     */
    private function update_labeval_comp_scores($sessionid) {
        global $DB;
        
        // Calcola punteggi per competenza
        $scores = $DB->get_records_sql("
            SELECT bc.competencyid, bc.competencycode,
                   SUM(r.rating * bc.weight) as score,
                   SUM(3 * bc.weight) as maxscore
            FROM {local_labeval_ratings} r
            JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = r.behaviorid
            WHERE r.sessionid = ?
            GROUP BY bc.competencyid, bc.competencycode
        ", [$sessionid]);
        
        foreach ($scores as $s) {
            $percentage = $s->maxscore > 0 ? round(($s->score / $s->maxscore) * 100, 2) : 0;
            
            // Verifica se esiste
            $existing = $DB->get_record('local_labeval_comp_scores', [
                'sessionid' => $sessionid,
                'competencyid' => $s->competencyid
            ]);
            
            if ($existing) {
                $existing->score = $s->score;
                $existing->maxscore = $s->maxscore;
                $existing->percentage = $percentage;
                $DB->update_record('local_labeval_comp_scores', $existing);
            } else {
                $cs = new \stdClass();
                $cs->sessionid = $sessionid;
                $cs->competencyid = $s->competencyid;
                $cs->competencycode = $s->competencycode;
                $cs->score = $s->score;
                $cs->maxscore = $s->maxscore;
                $cs->percentage = $percentage;
                $DB->insert_record('local_labeval_comp_scores', $cs);
            }
        }
    }
    
    /**
     * Pulisce tutti i dati test
     * @return array Statistiche pulizia
     */
    public function cleanup_all() {
        global $DB;
        
        $stats = [
            'quiz_attempts' => 0,
            'selfassessments' => 0,
            'labeval_sessions' => 0
        ];
        
        if (empty($this->testusers)) {
            return $stats;
        }
        
        $userids = array_map(fn($tu) => $tu->userid, $this->testusers);
        list($insql, $params) = $DB->get_in_or_equal($userids);
        
        // Pulisci quiz attempts
        $attempts = $DB->get_records_select('quiz_attempts', "userid {$insql}", $params);
        foreach ($attempts as $a) {
            // Elimina question_attempt_steps
            $qas = $DB->get_records('question_attempts', ['questionusageid' => $a->uniqueid]);
            foreach ($qas as $qa) {
                $DB->delete_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
            }
            // Elimina question_attempts
            $DB->delete_records('question_attempts', ['questionusageid' => $a->uniqueid]);
            // Elimina question_usages
            $DB->delete_records('question_usages', ['id' => $a->uniqueid]);
            // Elimina quiz_attempt
            $DB->delete_records('quiz_attempts', ['id' => $a->id]);
            $stats['quiz_attempts']++;
        }
        
        // Pulisci selfassessments
        $stats['selfassessments'] = $DB->delete_records_select('local_selfassessment', "userid {$insql}", $params);
        
        // Pulisci labeval
        $assignments = $DB->get_records_select('local_labeval_assignments', "studentid {$insql}", $params);
        foreach ($assignments as $a) {
            $sessions = $DB->get_records('local_labeval_sessions', ['assignmentid' => $a->id]);
            foreach ($sessions as $s) {
                $DB->delete_records('local_labeval_ratings', ['sessionid' => $s->id]);
                $DB->delete_records('local_labeval_comp_scores', ['sessionid' => $s->id]);
                $DB->delete_records('local_labeval_sessions', ['id' => $s->id]);
                $stats['labeval_sessions']++;
            }
            $DB->delete_records('local_labeval_assignments', ['id' => $a->id]);
        }
        
        $this->add_log("Pulizia completata: {$stats['quiz_attempts']} quiz, {$stats['selfassessments']} autovalutazioni, {$stats['labeval_sessions']} sessioni lab", 'success');
        
        return $stats;
    }
}
