<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * API class for local_labeval - used for integration with coachmanager
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labeval;

defined('MOODLE_INTERNAL') || die();

/**
 * API class providing data for integration with other plugins
 */
class api {
    
    /**
     * Get competency scores for a student from lab evaluations
     * This is the main API method called by coachmanager for report integration
     *
     * @param int $studentid Student ID
     * @param string $sectorcode Optional sector filter (e.g., 'MECCANICA')
     * @return array Competency scores indexed by competency code
     */
    public static function get_student_competency_scores($studentid, $sectorcode = null) {
        global $DB;
        
        // Build query to get latest completed evaluation scores
        $sql = "SELECT cs.competencycode, 
                       AVG(cs.percentage) as percentage,
                       SUM(cs.score) as totalscore,
                       SUM(cs.maxscore) as maxscore,
                       COUNT(DISTINCT s.id) as evalcount
                FROM {local_labeval_comp_scores} cs
                JOIN {local_labeval_sessions} s ON s.id = cs.sessionid
                JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                JOIN {local_labeval_templates} t ON t.id = a.templateid
                WHERE a.studentid = ?
                  AND s.status = 'completed'";
        
        $params = [$studentid];
        
        if ($sectorcode) {
            $sql .= " AND t.sectorcode = ?";
            $params[] = $sectorcode;
        }
        
        $sql .= " GROUP BY cs.competencycode
                  ORDER BY cs.competencycode";
        
        $records = $DB->get_records_sql($sql, $params);
        
        $result = [];
        foreach ($records as $record) {
            $result[$record->competencycode] = [
                'code' => $record->competencycode,
                'percentage' => round($record->percentage, 2),
                'totalscore' => $record->totalscore,
                'maxscore' => $record->maxscore,
                'evalcount' => $record->evalcount
            ];
        }
        
        return $result;
    }
    
    /**
     * Get all evaluations for a student
     *
     * @param int $studentid Student ID
     * @param bool $completedonly Only return completed evaluations
     * @return array List of evaluations with details
     */
    public static function get_student_evaluations($studentid, $completedonly = true) {
        global $DB;
        
        $statusfilter = $completedonly ? "AND s.status = 'completed'" : "";
        
        $sql = "SELECT s.id as sessionid, s.totalscore, s.maxscore, s.percentage,
                       s.status as sessionstatus, s.timecompleted, s.notes,
                       a.id as assignmentid, a.status as assignmentstatus, a.timecreated as assigned,
                       t.id as templateid, t.name as templatename, t.sectorcode,
                       u.firstname as assessorfirst, u.lastname as assessorlast
                FROM {local_labeval_sessions} s
                JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                JOIN {local_labeval_templates} t ON t.id = a.templateid
                JOIN {user} u ON u.id = s.assessorid
                WHERE a.studentid = ?
                {$statusfilter}
                ORDER BY s.timecompleted DESC";
        
        return $DB->get_records_sql($sql, [$studentid]);
    }
    
    /**
     * Get competency coverage for a student
     * Returns which competencies have been tested and which haven't
     *
     * @param int $studentid Student ID
     * @param string $sectorcode Sector to check
     * @return array ['tested' => [...], 'nottested' => [...]]
     */
    public static function get_competency_coverage($studentid, $sectorcode) {
        global $DB;
        
        // Get all competencies for this sector from Moodle competency framework
        $sql = "SELECT c.id, c.shortname, c.idnumber
                FROM {competency} c
                JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                WHERE c.idnumber LIKE ?
                ORDER BY c.sortorder";
        
        $allcompetencies = $DB->get_records_sql($sql, [$sectorcode . '%']);
        
        // Get tested competencies for this student
        $testedcodes = self::get_student_competency_scores($studentid, $sectorcode);
        
        $result = [
            'tested' => [],
            'nottested' => []
        ];
        
        foreach ($allcompetencies as $comp) {
            $code = $comp->idnumber;
            if (isset($testedcodes[$code])) {
                $result['tested'][$code] = [
                    'id' => $comp->id,
                    'code' => $code,
                    'name' => $comp->shortname,
                    'percentage' => $testedcodes[$code]['percentage'],
                    'evalcount' => $testedcodes[$code]['evalcount']
                ];
            } else {
                $result['nottested'][$code] = [
                    'id' => $comp->id,
                    'code' => $code,
                    'name' => $comp->shortname
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get pending assignments for a student
     *
     * @param int $studentid Student ID
     * @return array List of pending assignments
     */
    public static function get_pending_assignments($studentid) {
        global $DB;
        
        $sql = "SELECT a.*, t.name as templatename, t.sectorcode,
                       u.firstname, u.lastname
                FROM {local_labeval_assignments} a
                JOIN {local_labeval_templates} t ON t.id = a.templateid
                JOIN {user} u ON u.id = a.assignedby
                WHERE a.studentid = ?
                  AND a.status = 'pending'
                ORDER BY a.duedate ASC, a.timecreated DESC";
        
        return $DB->get_records_sql($sql, [$studentid]);
    }
    
    /**
     * Get available templates
     *
     * @param string $sectorcode Optional sector filter
     * @return array List of templates
     */
    public static function get_templates($sectorcode = null) {
        global $DB;
        
        $conditions = ['status' => 'active'];
        if ($sectorcode) {
            $conditions['sectorcode'] = $sectorcode;
        }
        
        return $DB->get_records('local_labeval_templates', $conditions, 'name ASC');
    }
    
    /**
     * Get template details with behaviors and competencies
     *
     * @param int $templateid Template ID
     * @return object|null Template with behaviors
     */
    public static function get_template_details($templateid) {
        global $DB;
        
        $template = $DB->get_record('local_labeval_templates', ['id' => $templateid]);
        if (!$template) {
            return null;
        }
        
        // Get behaviors
        $template->behaviors = $DB->get_records('local_labeval_behaviors', 
            ['templateid' => $templateid], 'sortorder ASC');
        
        // Get competency mappings for each behavior
        foreach ($template->behaviors as &$behavior) {
            $behavior->competencies = $DB->get_records('local_labeval_behavior_comp',
                ['behaviorid' => $behavior->id]);
        }
        
        // Count unique competencies
        $sql = "SELECT COUNT(DISTINCT bc.competencycode) as cnt
                FROM {local_labeval_behaviors} b
                JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = b.id
                WHERE b.templateid = ?";
        $template->competencycount = $DB->get_field_sql($sql, [$templateid]);
        
        return $template;
    }
    
    /**
     * Create an assignment
     *
     * @param int $templateid Template ID
     * @param int $studentid Student ID
     * @param int $assignedby Coach ID
     * @param int $courseid Optional course ID
     * @param int $duedate Optional due date timestamp
     * @return int Assignment ID
     */
    public static function create_assignment($templateid, $studentid, $assignedby, $courseid = null, $duedate = null) {
        global $DB;
        
        $record = new \stdClass();
        $record->templateid = $templateid;
        $record->studentid = $studentid;
        $record->assignedby = $assignedby;
        $record->courseid = $courseid;
        $record->duedate = $duedate;
        $record->status = 'pending';
        $record->timecreated = time();
        $record->timemodified = time();
        
        return $DB->insert_record('local_labeval_assignments', $record);
    }
    
    /**
     * Create an evaluation session
     *
     * @param int $assignmentid Assignment ID
     * @param int $assessorid Assessor (coach) ID
     * @return int Session ID
     */
    public static function create_session($assignmentid, $assessorid) {
        global $DB;
        
        $record = new \stdClass();
        $record->assignmentid = $assignmentid;
        $record->assessorid = $assessorid;
        $record->status = 'draft';
        $record->timecreated = time();
        
        return $DB->insert_record('local_labeval_sessions', $record);
    }
    
    /**
     * Save a rating for a behavior
     *
     * @param int $sessionid Session ID
     * @param int $behaviorid Behavior ID
     * @param int $rating Rating (0, 1, or 3)
     * @param string $notes Optional notes
     * @return int Rating ID
     */
    public static function save_rating($sessionid, $behaviorid, $rating, $notes = '') {
        global $DB;
        
        // Check if rating exists
        $existing = $DB->get_record('local_labeval_ratings', [
            'sessionid' => $sessionid,
            'behaviorid' => $behaviorid
        ]);
        
        if ($existing) {
            $existing->rating = $rating;
            $existing->notes = $notes;
            $DB->update_record('local_labeval_ratings', $existing);
            return $existing->id;
        } else {
            $record = new \stdClass();
            $record->sessionid = $sessionid;
            $record->behaviorid = $behaviorid;
            $record->rating = $rating;
            $record->notes = $notes;
            return $DB->insert_record('local_labeval_ratings', $record);
        }
    }
    
    /**
     * Complete an evaluation session
     *
     * @param int $sessionid Session ID
     * @param string $notes General notes
     * @return bool Success
     */
    public static function complete_session($sessionid, $notes = '') {
        global $DB;
        
        // Calculate and save competency scores
        $scores = \local_labeval_calculate_competency_scores($sessionid);
        \local_labeval_save_competency_scores($sessionid, $scores);
        
        // Update session totals
        \local_labeval_update_session_totals($sessionid);
        
        // Mark session as completed
        $session = $DB->get_record('local_labeval_sessions', ['id' => $sessionid]);
        $session->status = 'completed';
        $session->notes = $notes;
        $session->timecompleted = time();
        $DB->update_record('local_labeval_sessions', $session);
        
        // Mark assignment as completed
        $assignment = $DB->get_record('local_labeval_assignments', ['id' => $session->assignmentid]);
        $assignment->status = 'completed';
        $assignment->timemodified = time();
        $DB->update_record('local_labeval_assignments', $assignment);
        
        return true;
    }
    
    /**
     * Get session with all ratings
     * VERSIONE 2.0: Usa id come prima colonna per evitare warning "Duplicate value"
     *
     * @param int $sessionid Session ID
     * @return object|null Session with ratings
     */
    public static function get_session_details($sessionid) {
        global $DB;
        
        $sql = "SELECT s.*, 
                       a.studentid, a.templateid, a.courseid,
                       t.name as templatename, t.sectorcode,
                       student.firstname as studentfirst, student.lastname as studentlast,
                       assessor.firstname as assessorfirst, assessor.lastname as assessorlast
                FROM {local_labeval_sessions} s
                JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                JOIN {local_labeval_templates} t ON t.id = a.templateid
                JOIN {user} student ON student.id = a.studentid
                JOIN {user} assessor ON assessor.id = s.assessorid
                WHERE s.id = ?";
        
        $session = $DB->get_record_sql($sql, [$sessionid]);
        if (!$session) {
            return null;
        }
        
        // Get ratings - usa id come prima colonna per garantire unicità
        // Ora ci possono essere più ratings per lo stesso behaviorid (uno per competenza)
        $sql = "SELECT id, behaviorid, rating, notes, competencycode
                FROM {local_labeval_ratings}
                WHERE sessionid = ?
                ORDER BY behaviorid, competencycode";
        $session->ratings = $DB->get_records_sql($sql, [$sessionid]);
        
        // Get competency scores - usa id come prima colonna
        $sql = "SELECT id, competencycode, score, maxscore, percentage
                FROM {local_labeval_comp_scores}
                WHERE sessionid = ?
                ORDER BY competencycode";
        $session->competencyscores = $DB->get_records_sql($sql, [$sessionid]);
        
        return $session;
    }
    
    /**
     * Check if labeval plugin is installed and has data
     *
     * @return bool
     */
    public static function is_available() {
        global $DB;
        
        try {
            // Check if tables exist
            $dbman = $DB->get_manager();
            return $dbman->table_exists('local_labeval_templates');
        } catch (\Exception $e) {
            return false;
        }
    }
}
