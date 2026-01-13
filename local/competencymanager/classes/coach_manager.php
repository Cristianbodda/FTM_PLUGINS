<?php
namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

class coach_manager {
    
    /**
     * Recupera studenti - dal corso se courseid fornito, altrimenti da coach_assign
     */
    public static function get_coach_students($coachid, $courseid = null) {
        global $DB;
        
        // Se c'è un courseid, prendi gli studenti iscritti al corso
        if ($courseid && is_numeric($courseid) && $courseid > 0) {
            $context = \context_course::instance($courseid);
            return get_enrolled_users($context, 'mod/quiz:attempt');
        }
        
        // Altrimenti prova dalla tabella coach_assign
        $sql = "SELECT DISTINCT u.* FROM {user} u
                JOIN {local_coachmanager_coach_assign} ca ON ca.studentid = u.id
                WHERE ca.coachid = :coachid AND ca.status = 1";
        $params = ['coachid' => $coachid];
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Verifica se utente è coach
     */
    public static function is_coach($userid, $courseid = null) {
        global $DB;
        
        // Se c'è courseid, verifica se è docente del corso
        if ($courseid && is_numeric($courseid) && $courseid > 0) {
            $context = \context_course::instance($courseid);
            return has_capability('moodle/course:manageactivities', $context, $userid);
        }
        
        return $DB->record_exists('local_coachmanager_coach_assign', [
            'coachid' => $userid,
            'status' => 1
        ]);
    }
    
    public static function assign_coach($coachid, $studentid, $assignedby) {
        global $DB;
        
        $record = new \stdClass();
        $record->coachid = $coachid;
        $record->studentid = $studentid;
        $record->assignedby = $assignedby;
        $record->status = 1;
        $record->timecreated = time();
        $record->timemodified = time();
        
        return $DB->insert_record('local_coachmanager_coach_assign', $record);
    }
    
    public static function remove_coach($coachid, $studentid) {
        global $DB;
        
        return $DB->delete_records('local_coachmanager_coach_assign', [
            'coachid' => $coachid,
            'studentid' => $studentid
        ]);
    }
}
