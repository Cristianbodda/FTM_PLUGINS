<?php
// ============================================
// Self Assessment - Manager Class
// ============================================
// Logica business per autovalutazioni
// ============================================

namespace local_selfassessment;

defined('MOODLE_INTERNAL') || die();

class manager {
    
    /**
     * Salva o aggiorna un'autovalutazione
     */
    public function save_assessment($userid, $competencyid, $level, $comment = '') {
        global $DB;
        
        $now = time();
        
        // Cerca se esiste già
        $existing = $DB->get_record('local_selfassessment', [
            'userid' => $userid,
            'competencyid' => $competencyid
        ]);
        
        if ($existing) {
            // Aggiorna
            $existing->level = $level;
            $existing->comment = $comment;
            $existing->timemodified = $now;
            $DB->update_record('local_selfassessment', $existing);
            return $existing->id;
        } else {
            // Inserisci nuovo
            $record = new \stdClass();
            $record->userid = $userid;
            $record->competencyid = $competencyid;
            $record->level = $level;
            $record->comment = $comment;
            $record->timecreated = $now;
            $record->timemodified = $now;
            return $DB->insert_record('local_selfassessment', $record);
        }
    }
    
    /**
     * Salva autovalutazioni in batch (per area)
     */
    public function save_batch($userid, $assessments) {
        global $DB;
        
        $saved = 0;
        
        foreach ($assessments as $competencyid => $level) {
            if ($level >= 1 && $level <= 6) {
                $this->save_assessment($userid, $competencyid, $level);
                $saved++;
            }
        }
        
        return $saved;
    }
    
    /**
     * Ottiene tutte le autovalutazioni di un utente
     */
    public function get_user_assessments($userid) {
        global $DB;
        
        return $DB->get_records('local_selfassessment', ['userid' => $userid], 'competencyid ASC');
    }
    
    /**
     * Ottiene autovalutazione per una specifica competenza
     */
    public function get_assessment($userid, $competencyid) {
        global $DB;
        
        return $DB->get_record('local_selfassessment', [
            'userid' => $userid,
            'competencyid' => $competencyid
        ]);
    }
    
    /**
     * Abilita autovalutazione per un utente
     */
    public function enable_user($userid, $coachid = null) {
        global $DB;
        
        $now = time();
        
        $existing = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
        
        if ($existing) {
            $existing->enabled = 1;
            $existing->disabledby = null;
            $existing->reason = null;
            $existing->timemodified = $now;
            $DB->update_record('local_selfassessment_status', $existing);
        }
        // Se non esiste, è già abilitato di default
        
        return true;
    }
    
    /**
     * Disabilita autovalutazione per un utente
     */
    public function disable_user($userid, $coachid, $reason = '') {
        global $DB;
        
        $now = time();
        
        $existing = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
        
        if ($existing) {
            $existing->enabled = 0;
            $existing->disabledby = $coachid;
            $existing->reason = $reason;
            $existing->timemodified = $now;
            $DB->update_record('local_selfassessment_status', $existing);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->enabled = 0;
            $record->disabledby = $coachid;
            $record->reason = $reason;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_selfassessment_status', $record);
        }
        
        return true;
    }
    
    /**
     * Verifica se un utente è abilitato
     */
    public function is_enabled($userid) {
        global $DB;
        
        $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
        
        // Default: abilitato
        if (!$status) {
            return true;
        }
        
        return (bool) $status->enabled;
    }
    
    /**
     * Ottiene statistiche generali per dashboard coach
     */
    public function get_stats() {
        global $DB;
        
        // Conta studenti che hanno fatto quiz (studenti "attivi")
        $total_students = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid) 
            FROM {quiz_attempts} 
            WHERE state = 'finished'
        ");
        
        // Conta studenti che hanno compilato autovalutazione
        $completed = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid) 
            FROM {local_selfassessment}
        ");
        
        // Conta studenti disabilitati
        $disabled = $DB->count_records('local_selfassessment_status', ['enabled' => 0]);
        
        // Pending = totali - completati - disabilitati
        $pending = max(0, $total_students - $completed - $disabled);
        
        return [
            'total' => $total_students,
            'completed' => $completed,
            'pending' => $pending,
            'disabled' => $disabled
        ];
    }
    
    /**
     * Ottiene lista studenti con stato autovalutazione
     */
    public function get_students_with_status($filter = 'all', $search = '', $page = 0, $perpage = 20) {
        global $DB;
        
        // Query base: studenti con quiz completati
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       (SELECT MAX(sa.timemodified) FROM {local_selfassessment} sa WHERE sa.userid = u.id) as last_assessment,
                       (SELECT COUNT(*) FROM {local_selfassessment} sa2 WHERE sa2.userid = u.id) as assessment_count,
                       COALESCE(sas.enabled, 1) as enabled
                FROM {user} u
                JOIN {quiz_attempts} qa ON qa.userid = u.id
                LEFT JOIN {local_selfassessment_status} sas ON sas.userid = u.id
                WHERE qa.state = 'finished'";
        
        $params = [];
        
        // Filtro ricerca
        if (!empty($search)) {
            $sql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
            $searchparam = '%' . $search . '%';
            $params = [$searchparam, $searchparam, $searchparam];
        }
        
        // Raggruppa per utente
        $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email, 
                          u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                          sas.enabled";
        
        // Filtro stato
        switch ($filter) {
            case 'completed':
                $sql .= " HAVING assessment_count > 0";
                break;
            case 'pending':
                $sql .= " HAVING assessment_count = 0 AND COALESCE(sas.enabled, 1) = 1";
                break;
            case 'disabled':
                $sql .= " HAVING COALESCE(sas.enabled, 1) = 0";
                break;
        }
        
        $sql .= " ORDER BY u.lastname, u.firstname";
        
        return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
    }
    
    /**
     * Conta studenti per filtro
     */
    public function count_students($filter = 'all', $search = '') {
        global $DB;
        
        $students = $this->get_students_with_status($filter, $search, 0, 10000);
        return count($students);
    }
    
    /**
     * Invia reminder a uno studente
     */
    public function send_reminder($userid, $coachid, $message = '') {
        global $DB, $CFG;
        
        require_once($CFG->dirroot . '/lib/messagelib.php');
        
        $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $coach = $DB->get_record('user', ['id' => $coachid], '*', MUST_EXIST);
        
        // Registra il reminder
        $reminder = new \stdClass();
        $reminder->userid = $userid;
        $reminder->sentby = $coachid;
        $reminder->message = $message;
        $reminder->timesent = time();
        $DB->insert_record('local_selfassessment_reminders', $reminder);
        
        // Invia messaggio Moodle
        $eventdata = new \core\message\message();
        $eventdata->component = 'local_selfassessment';
        $eventdata->name = 'reminder';
        $eventdata->userfrom = $coach;
        $eventdata->userto = $student;
        $eventdata->subject = get_string('pluginname', 'local_selfassessment');
        $eventdata->fullmessage = !empty($message) ? $message : get_string('instructions', 'local_selfassessment');
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '';
        $eventdata->smallmessage = get_string('pluginname', 'local_selfassessment');
        $eventdata->notification = 1;
        $eventdata->contexturl = new \moodle_url('/local/selfassessment/compile.php');
        $eventdata->contexturlname = get_string('myassessment', 'local_selfassessment');
        
        try {
            message_send($eventdata);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Ottiene autovalutazioni raggruppate per area
     */
    public function get_assessments_by_area($userid) {
        global $DB;
        
        $assessments = $this->get_user_assessments($userid);
        
        // Carica competenze per determinare le aree
        $competencies = $DB->get_records_sql("
            SELECT c.id, c.idnumber, c.shortname
            FROM {competency} c
            JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
            WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
        ");
        
        $by_area = [];
        
        foreach ($assessments as $assessment) {
            $comp = $competencies[$assessment->competencyid] ?? null;
            if (!$comp) continue;
            
            // Determina area dal prefisso idnumber
            $area = $this->get_area_from_idnumber($comp->idnumber);
            
            if (!isset($by_area[$area])) {
                $by_area[$area] = [
                    'assessments' => [],
                    'sum' => 0,
                    'count' => 0
                ];
            }
            
            $by_area[$area]['assessments'][] = $assessment;
            $by_area[$area]['sum'] += $assessment->level;
            $by_area[$area]['count']++;
        }
        
        // Calcola medie
        foreach ($by_area as $area => &$data) {
            $data['average_level'] = $data['count'] > 0 ? round($data['sum'] / $data['count'], 1) : 0;
            $data['average_percent'] = $data['count'] > 0 ? round(($data['sum'] / $data['count']) / 6 * 100) : 0;
        }
        
        return $by_area;
    }
    
    /**
     * Determina l'area da un idnumber di competenza
     */
    private function get_area_from_idnumber($idnumber) {
        $area_prefixes = [
            'AUTOMOBILE_MAu' => 'manutenzione-auto',
            'AUTOMOBILE_MR' => 'manutenzione-rip',
            'MECCANICA_ASS' => 'assemblaggio',
            'MECCANICA_AUT' => 'automazione',
            'MECCANICA_CNC' => 'cnc',
            'MECCANICA_DIS' => 'disegno',
            'MECCANICA_LAV' => 'lav-generali',
            'MECCANICA_LMC' => 'lav-macchine',
            'MECCANICA_LMB' => 'lav-base',
            'MECCANICA_MAN' => 'manutenzione',
            'MECCANICA_MIS' => 'misurazione',
            'MECCANICA_PIA' => 'pianificazione',
            'MECCANICA_PRO' => 'programmazione',
            'MECCANICA_SIC' => 'sicurezza',
            'MECCANICA_CSP' => 'collaborazione',
        ];
        
        foreach ($area_prefixes as $prefix => $area) {
            if (strpos($idnumber, $prefix) === 0) {
                return $area;
            }
        }
        
        return 'altro';
    }
    
    // ============================================
    // GESTIONE ASSEGNAZIONI
    // ============================================
    
    /**
     * Ottiene le competenze assegnate a uno studente per autovalutazione
     */
    public function get_assigned_competencies($userid) {
        global $DB;
        
        return $DB->get_records_sql("
            SELECT sa.id as assignid, sa.competencyid, sa.source, sa.sourceid, sa.timecreated,
                   c.idnumber, c.shortname, c.description
            FROM {local_selfassessment_assign} sa
            JOIN {competency} c ON c.id = sa.competencyid
            WHERE sa.userid = ?
            ORDER BY c.idnumber
        ", [$userid]);
    }
    
    /**
     * Conta le competenze assegnate a uno studente
     */
    public function count_assigned($userid) {
        global $DB;
        return $DB->count_records('local_selfassessment_assign', ['userid' => $userid]);
    }
    
    /**
     * Verifica se lo studente ha competenze da autovalutare
     */
    public function has_assignments($userid) {
        return $this->count_assigned($userid) > 0;
    }
    
    /**
     * Assegna una singola competenza a uno studente (dal coach)
     */
    public function assign_competency($userid, $competencyid, $coachid) {
        global $DB;
        
        // Verifica se già assegnata
        $exists = $DB->record_exists('local_selfassessment_assign', [
            'userid' => $userid,
            'competencyid' => $competencyid
        ]);
        
        if ($exists) {
            return true; // Già assegnata
        }
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->competencyid = $competencyid;
        $record->source = 'coach_comp';
        $record->sourceid = $coachid;
        $record->timecreated = time();
        
        return $DB->insert_record('local_selfassessment_assign', $record);
    }
    
    /**
     * Assegna tutte le competenze di un'area a uno studente (dal coach)
     */
    public function assign_area($userid, $area_prefix, $coachid) {
        global $DB;
        
        // Trova tutte le competenze dell'area
        $competencies = $DB->get_records_sql("
            SELECT c.id
            FROM {competency} c
            JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
            WHERE (cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%')
            AND c.idnumber LIKE ?
        ", [$area_prefix . '%']);
        
        $assigned = 0;
        $now = time();
        
        foreach ($competencies as $comp) {
            // Verifica se già assegnata
            $exists = $DB->record_exists('local_selfassessment_assign', [
                'userid' => $userid,
                'competencyid' => $comp->id
            ]);
            
            if (!$exists) {
                $record = new \stdClass();
                $record->userid = $userid;
                $record->competencyid = $comp->id;
                $record->source = 'coach_area';
                $record->sourceid = $coachid;
                $record->timecreated = $now;
                
                $DB->insert_record('local_selfassessment_assign', $record);
                $assigned++;
            }
        }
        
        return $assigned;
    }
    
    /**
     * Assegna area a più studenti (azione di massa)
     */
    public function assign_area_bulk($userids, $area_prefix, $coachid) {
        $total = 0;
        foreach ($userids as $userid) {
            $total += $this->assign_area($userid, $area_prefix, $coachid);
        }
        return $total;
    }
    
    /**
     * Rimuove assegnazione competenza
     */
    public function unassign_competency($userid, $competencyid) {
        global $DB;
        
        return $DB->delete_records('local_selfassessment_assign', [
            'userid' => $userid,
            'competencyid' => $competencyid
        ]);
    }
    
    /**
     * Rimuove tutte le assegnazioni di un'area
     */
    public function unassign_area($userid, $area_prefix) {
        global $DB;
        
        // Trova competenze dell'area
        $competencies = $DB->get_records_sql("
            SELECT c.id
            FROM {competency} c
            WHERE c.idnumber LIKE ?
        ", [$area_prefix . '%']);
        
        $removed = 0;
        foreach ($competencies as $comp) {
            if ($DB->delete_records('local_selfassessment_assign', [
                'userid' => $userid,
                'competencyid' => $comp->id
            ])) {
                $removed++;
            }
        }
        
        return $removed;
    }
    
    /**
     * Ottiene statistiche assegnazioni per dashboard coach
     */
    public function get_assignment_stats() {
        global $DB;
        
        // Studenti con assegnazioni
        $with_assignments = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid) FROM {local_selfassessment_assign}
        ");
        
        // Assegnazioni da quiz
        $from_quiz = $DB->count_records('local_selfassessment_assign', ['source' => 'quiz']);
        
        // Assegnazioni da coach
        $from_coach = $DB->count_records_sql("
            SELECT COUNT(*) FROM {local_selfassessment_assign} 
            WHERE source IN ('coach_area', 'coach_comp')
        ");
        
        return [
            'students_with_assignments' => $with_assignments,
            'from_quiz' => $from_quiz,
            'from_coach' => $from_coach,
            'total' => $from_quiz + $from_coach
        ];
    }
    
    /**
     * Ottiene le aree disponibili per assegnazione
     */
    public function get_available_areas() {
        return [
            'AUTOMOBILE_MAu' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db'],
            'AUTOMOBILE_MR' => ['nome' => 'Manutenzione e Riparazione', 'icona' => '🔧', 'colore' => '#e74c3c'],
            'MECCANICA_ASS' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12'],
            'MECCANICA_AUT' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c'],
            'MECCANICA_CNC' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4'],
            'MECCANICA_CSP' => ['nome' => 'Collaborazione', 'icona' => '🤝', 'colore' => '#8e44ad'],
            'MECCANICA_DIS' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db'],
            'MECCANICA_LAV' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e'],
            'MECCANICA_LMC' => ['nome' => 'Lavorazioni Macchine', 'icona' => '⚙️', 'colore' => '#607d8b'],
            'MECCANICA_LMB' => ['nome' => 'Lavorazioni Manuali', 'icona' => '🔧', 'colore' => '#795548'],
            'MECCANICA_MAN' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22'],
            'MECCANICA_MIS' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c'],
            'MECCANICA_PIA' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6'],
            'MECCANICA_PRO' => ['nome' => 'Programmazione', 'icona' => '💻', 'colore' => '#2ecc71'],
            'MECCANICA_SIC' => ['nome' => 'Sicurezza e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b'],
        ];
    }
}
