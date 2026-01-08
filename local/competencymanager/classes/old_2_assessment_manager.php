<?php
namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

class assessment_manager {
    
    /**
     * Genera report completo per colloquio - MERGE quiz + autovalutazioni
     */
    public static function generate_colloquio_report($studentid, $quizids = null, $courseid = null) {
        global $DB;
        
        $report = new \stdClass();
        $report->studentid = $studentid;
        $report->gaps = [];
        $report->stats = new \stdClass();
        $report->stats->total_competencies = 0;
        $report->stats->with_quiz = 0;
        $report->stats->with_self_assessment = 0;
        $report->stats->completed_quizzes = 0;
        
        // 1. Recupera risultati quiz usando report_generator
        $quizResults = [];
        if (class_exists('\\local_competencymanager\\report_generator')) {
            $quizResults = report_generator::get_student_competency_scores($studentid, $courseid);
        }
        
        // 2. Recupera autovalutazioni
        $selfAssessments = self::get_student_selfassessments($studentid);
        $selfAssessmentsByComp = [];
        foreach ($selfAssessments as $sa) {
            $selfAssessmentsByComp[$sa->competencyid] = $sa;
        }
        
        // 3. Recupera tutte le competenze del framework
        $competencies = [];
        if ($courseid) {
            $sql = "SELECT DISTINCT c.id, c.idnumber, c.shortname, c.description
                    FROM {competency} c
                    JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
                    JOIN {question} q ON q.id = qbc.questionid
                    JOIN {quiz_slots} qs ON qs.questionid = q.id
                    JOIN {quiz} quiz ON quiz.id = qs.quizid
                    WHERE quiz.course = :courseid
                    ORDER BY c.idnumber";
            $competencies = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        }
        
        // 4. Crea gap analysis per ogni competenza
        foreach ($competencies as $comp) {
            $gap = new \stdClass();
            $gap->competencyid = $comp->id;
            $gap->idnumber = $comp->idnumber;
            $gap->name = $comp->shortname ?: $comp->description;
            $gap->description = $comp->description;
            
            // Dati quiz
            $gap->quiz_score = null;
            $gap->quiz_percentage = null;
            $gap->quiz_total = 0;
            $gap->quiz_correct = 0;
            
            if (isset($quizResults[$comp->id])) {
                $qr = $quizResults[$comp->id];
                $gap->quiz_percentage = $qr['percentage'];
                $gap->quiz_total = $qr['total_questions'];
                $gap->quiz_correct = $qr['correct_questions'];
                $gap->quiz_score = $qr['percentage'];
                $report->stats->with_quiz++;
            }
            
            // Dati autovalutazione (1-6 Bloom)
            $gap->self_assessment = null;
            $gap->self_comment = null;
            
            if (isset($selfAssessmentsByComp[$comp->id])) {
                $sa = $selfAssessmentsByComp[$comp->id];
                $gap->self_assessment = $sa->level;
                $gap->self_comment = $sa->comment;
                $report->stats->with_self_assessment++;
            }
            
            // Calcola gap (differenza tra autovalutazione e risultato quiz)
            $gap->gap_value = null;
            $gap->gap_type = 'no_data';
            
            if ($gap->quiz_percentage !== null && $gap->self_assessment !== null) {
                // Converti autovalutazione Bloom (1-6) in percentuale (0-100)
                $selfPercentage = ($gap->self_assessment / 6) * 100;
                $gap->gap_value = $selfPercentage - $gap->quiz_percentage;
                
                if (abs($gap->gap_value) <= 15) {
                    $gap->gap_type = 'aligned';
                } elseif ($gap->gap_value > 15) {
                    $gap->gap_type = 'overconfident';
                } else {
                    $gap->gap_type = 'underconfident';
                }
            } elseif ($gap->quiz_percentage !== null) {
                $gap->gap_type = 'quiz_only';
            } elseif ($gap->self_assessment !== null) {
                $gap->gap_type = 'self_only';
            }
            
            // Estrai area dal codice
            $parts = explode('_', $gap->idnumber);
            $gap->area = isset($parts[1]) ? $parts[1] : 'OTHER';
            $gap->sector = isset($parts[0]) ? $parts[0] : 'OTHER';
            
            $report->gaps[] = $gap;
            $report->stats->total_competencies++;
        }
        
        // Conta quiz completati
        $report->stats->completed_quizzes = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT qa.quiz) 
             FROM {quiz_attempts} qa 
             JOIN {quiz} q ON q.id = qa.quiz
             WHERE qa.userid = :userid AND qa.state = 'finished'" .
             ($courseid ? " AND q.course = :courseid" : ""),
            $courseid ? ['userid' => $studentid, 'courseid' => $courseid] : ['userid' => $studentid]
        );
        
        return $report;
    }
    
    public static function get_student_selfassessments($userid, $competencyids = null) {
        global $DB;
        
        $sql = "SELECT sa.*, c.idnumber, c.shortname, c.description
                FROM {local_selfassessment} sa
                JOIN {competency} c ON c.id = sa.competencyid
                WHERE sa.userid = :userid";
        $params = ['userid' => $userid];
        
        if ($competencyids) {
            list($insql, $inparams) = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
            $sql .= " AND sa.competencyid $insql";
            $params = array_merge($params, $inparams);
        }
        
        $sql .= " ORDER BY c.idnumber";
        
        return $DB->get_records_sql($sql, $params);
    }
    
    public static function get_selfassessment_by_competency($userid, $competencyid) {
        global $DB;
        return $DB->get_record('local_selfassessment', [
            'userid' => $userid,
            'competencyid' => $competencyid
        ]);
    }
    
    public static function save_selfassessment($userid, $competencyid, $level, $comment = '') {
        global $DB;
        $existing = self::get_selfassessment_by_competency($userid, $competencyid);
        
        if ($existing) {
            $existing->level = $level;
            $existing->comment = $comment;
            $existing->timemodified = time();
            return $DB->update_record('local_selfassessment', $existing);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->competencyid = $competencyid;
            $record->level = $level;
            $record->comment = $comment;
            $record->timecreated = time();
            $record->timemodified = time();
            return $DB->insert_record('local_selfassessment', $record);
        }
    }
    
    public static function get_bloom_levels() {
        return [
            1 => ['name' => 'Ricordare', 'description' => 'Richiamo di informazioni'],
            2 => ['name' => 'Comprendere', 'description' => 'Comprensione del significato'],
            3 => ['name' => 'Applicare', 'description' => 'Uso in situazioni nuove'],
            4 => ['name' => 'Analizzare', 'description' => 'Scomporre in parti'],
            5 => ['name' => 'Valutare', 'description' => 'Giudizio critico'],
            6 => ['name' => 'Creare', 'description' => 'Produzione originale']
        ];
    }
}
