<?php
namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

class assessment_manager {
    
    /**
     * Genera report completo per colloquio - MERGE quiz + autovalutazioni
     * Compatibile con Moodle 4.x
     */
    public static function generate_colloquio_report($studentid, $quizids = null, $courseid = null) {
        global $DB;
        
        $report = new \stdClass();
        $report->studentid = $studentid;
        $report->generated = time();
        $report->gaps = [];
        $report->stats = new \stdClass();
        $report->stats->total_competencies = 0;
        $report->stats->with_quiz = 0;
        $report->stats->with_self_assessment = 0;
        $report->stats->completed_quizzes = 0;
        
        // 1. Recupera risultati quiz
        $quizResults = [];
        if (class_exists('\\local_competencymanager\\report_generator')) {
            try {
                $quizResults = report_generator::get_student_competency_scores($studentid, $courseid);
                if (!is_array($quizResults)) {
                    $quizResults = [];
                }
            } catch (\Exception $e) {
                $quizResults = [];
            }
        }
        
        // 2. Recupera autovalutazioni
        $selfAssessments = self::get_student_selfassessments($studentid);
        $selfAssessmentsByComp = [];
        foreach ($selfAssessments as $sa) {
            $selfAssessmentsByComp[$sa->competencyid] = $sa;
        }
        
        // 3. Recupera competenze - Query Moodle 4.x
        $competencies = [];
        if ($courseid) {
            $sql = "SELECT DISTINCT c.id, c.idnumber, c.shortname, c.description
                    FROM {competency} c
                    JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
                    JOIN {question_versions} qv ON qv.questionid = qbc.questionid
                    JOIN {question_references} qr ON qr.questionbankentryid = qv.questionbankentryid
                    JOIN {quiz_slots} qs ON qs.id = qr.itemid
                    JOIN {quiz} q ON q.id = qs.quizid
                    WHERE q.course = :courseid
                    AND qr.component = 'mod_quiz'
                    AND qr.questionarea = 'slot'
                    ORDER BY c.idnumber";
            
            try {
                $competencies = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } catch (\Exception $e) {
                $competencies = [];
            }
        }
        
        // 4. Crea gap analysis
        foreach ($competencies as $comp) {
            $gap = new \stdClass();
            $gap->competencyid = $comp->id;
            // NOMI CORRETTI per reports.php
            $gap->competency_idnumber = $comp->idnumber;
            $gap->competency_name = !empty($comp->shortname) ? $comp->shortname : $comp->description;
            $gap->idnumber = $comp->idnumber;
            $gap->name = $gap->competency_name;
            $gap->description = $comp->description;
            
            // Dati quiz
            $gap->quiz_score = null;
            $gap->quiz_percentage = null;
            $gap->quiz_total = 0;
            $gap->quiz_correct = 0;
            
            // Cerca risultati quiz
            $found = false;
            $qr = null;
            if (isset($quizResults[$comp->id])) {
                $qr = $quizResults[$comp->id];
                $found = true;
            } elseif (isset($quizResults[$comp->idnumber])) {
                $qr = $quizResults[$comp->idnumber];
                $found = true;
            } else {
                foreach ($quizResults as $key => $val) {
                    if (is_array($val) && isset($val['idnumber']) && $val['idnumber'] == $comp->idnumber) {
                        $qr = $val;
                        $found = true;
                        break;
                    }
                }
            }
            
            if ($found && is_array($qr)) {
                $gap->quiz_percentage = isset($qr['percentage']) ? $qr['percentage'] : 0;
                $gap->quiz_total = isset($qr['total_questions']) ? $qr['total_questions'] : 0;
                $gap->quiz_correct = isset($qr['correct_questions']) ? $qr['correct_questions'] : 0;
                $gap->quiz_score = $gap->quiz_percentage; $gap->real_performance_percentage = $gap->quiz_percentage; $gap->real_performance = null;
                $report->stats->with_quiz++;
            }
            
            // Autovalutazione (1-6 Bloom)
            $gap->self_assessment = null;
            $gap->self_comment = null;
            
            if (isset($selfAssessmentsByComp[$comp->id])) {
                $sa = $selfAssessmentsByComp[$comp->id];
                $gap->self_assessment = $sa->level;
                $gap->self_comment = isset($sa->comment) ? $sa->comment : '';
                $report->stats->with_self_assessment++;
            }
            
            // Calcola gap
            $gap->gap_value = null;
            $gap->gap_type = 'no_data';
            
            if ($gap->quiz_percentage !== null && $gap->self_assessment !== null) {
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
            
            // Estrai area
            $parts = explode('_', $gap->competency_idnumber);
            $gap->area = isset($parts[1]) ? $parts[1] : 'OTHER';
            $gap->sector = isset($parts[0]) ? $parts[0] : 'OTHER';
            
            $report->gaps[] = $gap;
            $report->stats->total_competencies++;
        }
        
        // Quiz completati
        try {
            $sql = "SELECT COUNT(DISTINCT qa.quiz) as cnt
                    FROM {quiz_attempts} qa 
                    JOIN {quiz} q ON q.id = qa.quiz
                    WHERE qa.userid = :userid AND qa.state = 'finished'";
            $params = ['userid' => $studentid];
            
            if ($courseid) {
                $sql .= " AND q.course = :courseid";
                $params['courseid'] = $courseid;
            }
            
            $result = $DB->get_record_sql($sql, $params);
            $report->stats->completed_quizzes = $result ? $result->cnt : 0;
        } catch (\Exception $e) {
            $report->stats->completed_quizzes = 0;
        }
        
        return $report;
    }
    
    public static function get_student_selfassessments($userid, $competencyids = null) {
        global $DB;
        
        try {
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
        } catch (\Exception $e) {
            return [];
        }
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
