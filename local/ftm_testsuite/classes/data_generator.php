<?php
/**
 * Data Generator - Genera dati test per FTM Test Suite
 * 
 * VERSIONE 2.0 - Aggiornato 09/01/2026
 * - Supporto labeval v2.0 con campo competencycode
 * - Rating per competenza (non più per behavior)
 * - Calcolo punteggi pesati
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
            
            // Genera valutazioni lab (v2.0 con competencycode)
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
        $sql = "SELECT DISTINCT qv.questionid, q.qtype, q.name, qs.slot, qs.maxmark
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.itemid = qs.id 
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                WHERE qs.quizid = ?
                ORDER BY qs.slot";
        
        return $DB->get_records_sql($sql, [$quizid]);
    }
    
    /**
     * Crea un tentativo quiz per un utente test
     * @param object $quiz
     * @param object $testuser
     * @param array $questions
     * @return int 1 se creato, 0 altrimenti
     */
    private function create_quiz_attempt($quiz, $testuser, $questions) {
        global $DB, $CFG;
        
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        
        try {
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            
            // Crea question usage
            $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
            $quba->set_preferred_behaviour('deferredfeedback');
            
            // Aggiungi domande
            foreach ($questions as $q) {
                $question = \question_bank::load_question($q->questionid);
                $quba->add_question($question, $q->maxmark);
            }
            
            $quba->start_all_questions();
            \question_engine::save_questions_usage_by_activity($quba);
            
            // Crea quiz attempt
            $attempt = new \stdClass();
            $attempt->quiz = $quiz->id;
            $attempt->userid = $testuser->userid;
            $attempt->attempt = 1;
            $attempt->uniqueid = $quba->get_id();
            $layout_slots = [];
            foreach ($questions as $q) {
                $layout_slots[] = $q->slot;
            }
            sort($layout_slots);
            $attempt->layout = implode(',', $layout_slots) . ',0';
            $attempt->currentpage = 0;
            $attempt->preview = 0;
            $attempt->state = 'inprogress';
            $attempt->timestart = time() - 3600;
            $attempt->timefinish = 0;
            $attempt->timemodified = time();
            $attempt->timemodifiedoffline = 0;
            $attempt->sumgrades = null;
            
            $attempt->id = $DB->insert_record('quiz_attempts', $attempt);
            
            // Simula risposte basate sulla percentuale target
            $target_correct = $testuser->quiz_percentage / 100;
            $slot = 1;

            foreach ($questions as $q) {
                $rand = mt_rand(1, 100) / 100;
                $is_correct = ($rand <= $target_correct);

                // Genera risposta basata sul tipo di domanda
                $response = $this->generate_question_response($q, $is_correct, $quba, $slot);

                if ($response !== null) {
                    $quba->process_action($slot, $response);
                }
                $slot++;
            }
            
            // Finalizza
            $quba->finish_all_questions();
            \question_engine::save_questions_usage_by_activity($quba);
            
            // Aggiorna attempt
            $attempt->state = 'finished';
            $attempt->timefinish = time();
            $attempt->sumgrades = $quba->get_total_mark();
            $DB->update_record('quiz_attempts', $attempt);
            
            // Aggiorna grades
            quiz_save_best_grade($quiz, $testuser->userid);
            
            return 1;
            
        } catch (\Exception $e) {
            $this->add_log("Errore creazione quiz: " . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    /**
     * Genera una risposta per una domanda
     * @param object $question
     * @param bool $correct
     * @param \question_usage_by_activity $quba Question usage (for shuffled order)
     * @param int $slot Slot number
     * @return array|null
     */
    private function generate_question_response($question, $correct, $quba = null, $slot = 0) {
        global $DB;

        switch ($question->qtype) {
            case 'multichoice':
                // Find the correct or incorrect answer ID.
                $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
                $target_id = null;
                $fallback_id = null;
                foreach ($answers as $a) {
                    if ($correct && $a->fraction > 0) {
                        $target_id = $a->id;
                        break;
                    } else if (!$correct && $a->fraction == 0) {
                        $target_id = $a->id;
                        break;
                    }
                    $fallback_id = $a->id;
                }
                if (!$target_id) {
                    $target_id = $fallback_id;
                }

                // Get the shuffled order from the question attempt to find the choice index.
                if ($quba && $slot > 0) {
                    $qa = $quba->get_question_attempt($slot);
                    $order = $qa->get_step(0)->get_qt_data();
                    if (isset($order['_order'])) {
                        $order_ids = explode(',', $order['_order']);
                        $choice_index = array_search($target_id, $order_ids);
                        if ($choice_index !== false) {
                            return ['answer' => $choice_index];
                        }
                    }
                }

                // Fallback: try answer ID directly (truefalse-style).
                return ['answer' => $target_id];

            case 'truefalse':
                // True/false uses answer ID directly (not shuffled).
                $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
                foreach ($answers as $a) {
                    if (($correct && $a->fraction > 0) || (!$correct && $a->fraction == 0)) {
                        return ['answer' => $a->id];
                    }
                }
                break;

            case 'shortanswer':
                if ($correct) {
                    $answer = $DB->get_record_sql(
                        "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                        [$question->questionid]
                    );
                    return $answer ? ['answer' => $answer->answer] : ['answer' => 'risposta_errata'];
                }
                return ['answer' => 'risposta_errata_' . rand(1000, 9999)];

            case 'numerical':
                if ($correct) {
                    $answer = $DB->get_record_sql(
                        "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                        [$question->questionid]
                    );
                    return $answer ? ['answer' => $answer->answer] : ['answer' => '0'];
                }
                return ['answer' => '-999999'];
        }

        return ['-submit' => 1];
    }
    
    /**
     * Genera autovalutazioni per gli utenti test
     * @param int $courseid
     * @return int Numero autovalutazioni create
     */
    public function generate_selfassessment_data($courseid) {
        global $DB;
        
        $count = 0;
        
        // Ottieni competenze testate nel corso
        $competencies = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.shortname, c.idnumber
            FROM {quiz} q
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            JOIN {question_references} qr ON qr.itemid = qs.id 
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE q.course = ?
        ", [$courseid]);
        
        if (empty($competencies)) {
            $this->add_log("Nessuna competenza trovata per corso {$courseid}", 'warning');
            return 0;
        }
        
        foreach ($this->testusers as $tu) {
            $comp_index = 0;
            
            // Strategia Bloom basata su profilo
            // Low30: sovrastima (Bloom alto 4-6)
            // Medium65: allineato (Bloom medio 3-5)
            // High95: sottostima (Bloom basso 2-4)
            $bloom_range = match($tu->testprofile) {
                'low30' => [4, 6],    // Pensa di sapere ma non sa
                'medium65' => [3, 5], // Calibrato
                'high95' => [2, 4],   // Sottostima le sue capacità
                default => [3, 5]
            };
            
            foreach ($competencies as $comp) {
                // Verifica se esiste già
                $existing = $DB->get_record('local_selfassessment', [
                    'userid' => $tu->userid,
                    'competencyid' => $comp->id
                ]);
                
                if ($existing) {
                    continue;
                }
                
                // Genera livello Bloom nel range
                $bloom_level = mt_rand($bloom_range[0], $bloom_range[1]);
                
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
     * 
     * VERSIONE 2.0 - 09/01/2026
     * - Ora genera rating per COMPETENZA (non per behavior)
     * - Usa il campo competencycode nella tabella ratings
     * - Calcola punteggi pesati
     * 
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
            
            // =====================================================
            // NUOVO v2.0: Ottieni mapping behavior -> competenze
            // =====================================================
            $behavior_competencies = [];
            foreach ($behaviors as $behavior) {
                // Ottieni le competenze associate a questo behavior
                $comps = $DB->get_records('local_labeval_behavior_comp', ['behaviorid' => $behavior->id]);
                $behavior_competencies[$behavior->id] = $comps;
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
                $session->notes = 'Sessione test generata automaticamente (v2.0 con competencycode)';
                $session->timecreated = time();
                $session->timecompleted = time();
                $sessionid = $DB->insert_record('local_labeval_sessions', $session);
                
                // Genera ratings basati su percentuale target
                $target_pct = $tu->quiz_percentage / 100;
                $total_score = 0;
                $max_score = 0;
                $ratings_count = 0;
                
                // =====================================================
                // NUOVO v2.0: Rating per COMPETENZA, non per behavior
                // =====================================================
                foreach ($behaviors as $behavior) {
                    $comps = $behavior_competencies[$behavior->id] ?? [];
                    
                    if (empty($comps)) {
                        // Fallback: se il behavior non ha competenze associate,
                        // crea un rating "legacy" senza competencycode
                        $this->add_log("Behavior {$behavior->id} senza competenze - creazione rating legacy", 'warning');
                        
                        $rand = mt_rand(1, 100) / 100;
                        if ($rand <= $target_pct * 0.8) {
                            $rating = 3;
                        } elseif ($rand <= $target_pct * 1.2) {
                            $rating = 1;
                        } else {
                            $rating = 0;
                        }
                        
                        $r = new \stdClass();
                        $r->sessionid = $sessionid;
                        $r->behaviorid = $behavior->id;
                        $r->competencycode = null; // Legacy
                        $r->rating = $rating;
                        $r->notes = '';
                        $DB->insert_record('local_labeval_ratings', $r);
                        
                        $total_score += $rating;
                        $max_score += 3;
                        $ratings_count++;
                        continue;
                    }
                    
                    // Per ogni competenza associata al behavior, crea un rating
                    foreach ($comps as $comp) {
                        // Decidi rating basato su target (con variazione per competenza)
                        $rand = mt_rand(1, 100) / 100;
                        
                        // Pesi più alti = più probabile rating alto
                        $weight_bonus = ($comp->weight == 3) ? 0.1 : 0;
                        
                        if ($rand <= ($target_pct * 0.8) + $weight_bonus) {
                            $rating = 3; // Adeguato
                        } elseif ($rand <= ($target_pct * 1.2) + $weight_bonus) {
                            $rating = 1; // Da migliorare
                        } else {
                            $rating = 0; // N/A
                        }
                        
                        // =====================================================
                        // NUOVO v2.0: Usa competencycode nel rating
                        // =====================================================
                        $r = new \stdClass();
                        $r->sessionid = $sessionid;
                        $r->behaviorid = $behavior->id;
                        $r->competencycode = $comp->competencycode; // NUOVO CAMPO!
                        $r->rating = $rating;
                        $r->notes = '';
                        $DB->insert_record('local_labeval_ratings', $r);
                        
                        // Calcolo punteggio pesato
                        $total_score += ($rating * $comp->weight);
                        $max_score += (3 * $comp->weight);
                        $ratings_count++;
                    }
                }
                
                // Aggiorna session con punteggi
                $percentage = $max_score > 0 ? round(($total_score / $max_score) * 100, 2) : 0;
                $DB->update_record('local_labeval_sessions', (object)[
                    'id' => $sessionid,
                    'totalscore' => $total_score,
                    'maxscore' => $max_score,
                    'percentage' => $percentage
                ]);
                
                // Aggiorna/crea comp_scores (v2.0)
                $this->update_labeval_comp_scores_v2($sessionid);
                
                $count++;
                $this->add_log("Creata valutazione lab '{$template->name}' per {$tu->username} ({$ratings_count} ratings, {$percentage}%)", 'success');
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
     * 
     * VERSIONE 2.0 - 09/01/2026
     * - Usa il campo competencycode dalla tabella ratings
     * - Non più JOIN con behavior_comp (usa direttamente competencycode)
     * 
     * @param int $sessionid
     */
    private function update_labeval_comp_scores_v2($sessionid) {
        global $DB;
        
        // =====================================================
        // NUOVO v2.0: Raggruppa per competencycode dai ratings
        // =====================================================
        $scores = $DB->get_records_sql("
            SELECT 
                r.competencycode,
                SUM(r.rating) as score,
                SUM(3) as maxscore,
                COUNT(*) as rating_count
            FROM {local_labeval_ratings} r
            WHERE r.sessionid = ?
              AND r.competencycode IS NOT NULL
              AND r.competencycode != ''
            GROUP BY r.competencycode
        ", [$sessionid]);
        
        // Se non ci sono ratings con competencycode, prova il metodo legacy
        if (empty($scores)) {
            $this->update_labeval_comp_scores_legacy($sessionid);
            return;
        }
        
        foreach ($scores as $s) {
            $percentage = $s->maxscore > 0 ? round(($s->score / $s->maxscore) * 100, 2) : 0;
            
            // Trova competencyid dal competencycode (se esiste nella tabella competency)
            $competency = $DB->get_record_sql("
                SELECT id FROM {competency} WHERE idnumber = ?
            ", [$s->competencycode]);
            
            $competencyid = $competency ? $competency->id : null;
            
            // Verifica se esiste già un record
            $existing = $DB->get_record('local_labeval_comp_scores', [
                'sessionid' => $sessionid,
                'competencycode' => $s->competencycode
            ]);
            
            if ($existing) {
                $existing->score = $s->score;
                $existing->maxscore = $s->maxscore;
                $existing->percentage = $percentage;
                $existing->competencyid = $competencyid;
                $DB->update_record('local_labeval_comp_scores', $existing);
            } else {
                $cs = new \stdClass();
                $cs->sessionid = $sessionid;
                $cs->competencyid = $competencyid;
                $cs->competencycode = $s->competencycode;
                $cs->score = $s->score;
                $cs->maxscore = $s->maxscore;
                $cs->percentage = $percentage;
                $DB->insert_record('local_labeval_comp_scores', $cs);
            }
        }
    }
    
    /**
     * Metodo legacy per comp_scores (compatibilità con vecchi dati)
     * Usa JOIN con behavior_comp quando competencycode non è nei ratings
     * 
     * @param int $sessionid
     */
    private function update_labeval_comp_scores_legacy($sessionid) {
        global $DB;
        
        // Calcola punteggi per competenza (metodo legacy via behavior_comp)
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
