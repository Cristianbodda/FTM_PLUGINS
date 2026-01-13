<?php
// ============================================
// Self Assessment - Event Observer
// ============================================
// Quando uno studente completa un quiz,
// assegna automaticamente le competenze associate
// ============================================

namespace local_selfassessment;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    /**
     * Gestisce l'evento quiz_attempt_submitted
     * Assegna automaticamente le competenze del quiz per l'autovalutazione
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        $userid = $event->relateduserid;
        $quizid = $event->other['quizid'] ?? null;
        $attemptid = $event->objectid;
        
        if (!$userid || !$quizid) {
            return;
        }
        
        // Trova le domande del quiz attempt
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return;
        }
        
        // Ottieni le domande dal quiz attempt
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);
        
        if (empty($questions)) {
            return;
        }
        
        $questionids = array_keys($questions);
        
        // Cerca tabella competenze-domande
        $comp_tables = [
            'qbank_competenciesbyquestion' => 'questionid',
            'qbank_comp_question' => 'questionid',
            'local_competencymanager_qcomp' => 'questionid'
        ];
        
        $comp_question_table = null;
        $comp_question_field = null;
        
        foreach ($comp_tables as $table => $field) {
            if ($DB->get_manager()->table_exists($table)) {
                $comp_question_table = $table;
                $comp_question_field = $field;
                break;
            }
        }
        
        if (!$comp_question_table) {
            // Nessuna tabella di mapping trovata
            return;
        }
        
        // Trova competenze associate alle domande
        list($sql_in, $params) = $DB->get_in_or_equal($questionids);
        $mappings = $DB->get_records_sql("
            SELECT DISTINCT competencyid 
            FROM {{$comp_question_table}} 
            WHERE {$comp_question_field} $sql_in
        ", $params);
        
        if (empty($mappings)) {
            return;
        }
        
        // Assegna ogni competenza per l'autovalutazione
        $now = time();
        $assigned = 0;
        
        foreach ($mappings as $mapping) {
            $competencyid = $mapping->competencyid;
            
            // Verifica se già assegnata
            $exists = $DB->record_exists('local_selfassessment_assign', [
                'userid' => $userid,
                'competencyid' => $competencyid
            ]);
            
            if (!$exists) {
                $record = new \stdClass();
                $record->userid = $userid;
                $record->competencyid = $competencyid;
                $record->source = 'quiz';
                $record->sourceid = $quizid;
                $record->timecreated = $now;
                
                try {
                    $DB->insert_record('local_selfassessment_assign', $record);
                    $assigned++;
                } catch (\Exception $e) {
                    // Ignora errori (es. duplicati)
                }
            }
        }
        
        // Log per debug (opzionale)
        if ($assigned > 0) {
            debugging("SelfAssessment: Assigned $assigned competencies to user $userid from quiz $quizid", DEBUG_DEVELOPER);
        }
    }
}
