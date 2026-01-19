<?php
/**
 * Sector Manager - Gestione settori studenti
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../area_mapping.php');

class sector_manager {

    // Lista settori supportati
    const SECTORS = [
        'AUTOMOBILE' => 'Automobile',
        'MECCANICA' => 'Meccanica',
        'LOGISTICA' => 'Logistica',
        'ELETTRICITA' => 'Elettricita',
        'AUTOMAZIONE' => 'Automazione',
        'METALCOSTRUZIONE' => 'Metalcostruzione',
        'CHIMFARM' => 'Chimico-Farmaceutico',
    ];

    /**
     * Ottiene tutti i settori di uno studente
     *
     * @param int $userid ID studente
     * @param int $courseid ID corso (0 = tutti i corsi)
     * @return array Array di oggetti settore
     */
    public static function get_student_sectors($userid, $courseid = 0) {
        global $DB;

        $params = ['userid' => $userid];
        $where = 'userid = :userid';

        if ($courseid > 0) {
            $params['courseid'] = $courseid;
            $where .= ' AND courseid = :courseid';
        }

        return $DB->get_records_select('local_student_sectors', $where, $params, 'is_primary DESC, quiz_count DESC');
    }

    /**
     * Ottiene il settore primario di uno studente
     *
     * @param int $userid ID studente
     * @param int $courseid ID corso (0 = qualsiasi)
     * @return string|null Codice settore o null
     */
    public static function get_primary_sector($userid, $courseid = 0) {
        global $DB;

        $params = ['userid' => $userid, 'is_primary' => 1];
        $where = 'userid = :userid AND is_primary = :is_primary';

        if ($courseid > 0) {
            $params['courseid'] = $courseid;
            $where .= ' AND courseid = :courseid';
        }

        $record = $DB->get_record_select('local_student_sectors', $where, $params, 'sector', IGNORE_MULTIPLE);
        return $record ? $record->sector : null;
    }

    /**
     * Ottiene il settore effettivo (primario o quello con piu quiz)
     *
     * @param int $userid ID studente
     * @param int $courseid ID corso (0 = qualsiasi)
     * @return string|null Codice settore o null
     */
    public static function get_effective_sector($userid, $courseid = 0) {
        // Prima cerca settore primario
        $primary = self::get_primary_sector($userid, $courseid);
        if ($primary) {
            return $primary;
        }

        // Fallback: settore con piu quiz
        $sectors = self::get_student_sectors($userid, $courseid);
        if (!empty($sectors)) {
            $first = reset($sectors);
            return $first->sector;
        }

        return null;
    }

    /**
     * Imposta il settore primario di uno studente
     *
     * @param int $userid ID studente
     * @param string $sector Codice settore
     * @param int $courseid ID corso (0 = globale)
     * @return bool Success
     */
    public static function set_primary_sector($userid, $sector, $courseid = 0) {
        global $DB;

        $now = time();

        // Rimuovi flag primario da tutti i settori dello studente/corso
        $DB->execute(
            "UPDATE {local_student_sectors}
             SET is_primary = 0, timemodified = ?
             WHERE userid = ? AND courseid = ?",
            [$now, $userid, $courseid]
        );

        // Verifica se esiste gia il record per questo settore
        $existing = $DB->get_record('local_student_sectors', [
            'userid' => $userid,
            'courseid' => $courseid,
            'sector' => $sector
        ]);

        if ($existing) {
            // Aggiorna record esistente
            $existing->is_primary = 1;
            $existing->source = 'manual';
            $existing->timemodified = $now;
            $DB->update_record('local_student_sectors', $existing);
        } else {
            // Crea nuovo record
            $record = new \stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->sector = $sector;
            $record->is_primary = 1;
            $record->source = 'manual';
            $record->quiz_count = 0;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_student_sectors', $record);
        }

        // Sincronizza con local_student_coaching
        self::sync_coaching_sector($userid, $courseid, $sector);

        return true;
    }

    /**
     * Rileva e registra i settori da un quiz completato
     *
     * @param int $userid ID studente
     * @param int $quizid ID quiz
     * @param array $competencyids Array di competency IDs
     * @return array Array di settori rilevati
     */
    public static function detect_sectors_from_quiz($userid, $quizid, $competencyids) {
        global $DB;

        if (empty($competencyids)) {
            return [];
        }

        // Ottieni courseid dal quiz
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course');
        $courseid = $quiz ? $quiz->course : 0;

        // Ottieni gli idnumber delle competenze
        list($sql_in, $params) = $DB->get_in_or_equal($competencyids);
        $competencies = $DB->get_records_sql(
            "SELECT id, idnumber FROM {competency} WHERE id $sql_in",
            $params
        );

        // Conta le competenze per settore
        $sector_counts = [];
        foreach ($competencies as $comp) {
            if (!empty($comp->idnumber)) {
                $sector = extract_sector_from_idnumber($comp->idnumber);
                if ($sector && $sector !== 'UNKNOWN') {
                    if (!isset($sector_counts[$sector])) {
                        $sector_counts[$sector] = 0;
                    }
                    $sector_counts[$sector]++;
                }
            }
        }

        $now = time();
        $detected = [];

        // Aggiorna/inserisci ogni settore rilevato
        foreach ($sector_counts as $sector => $count) {
            $existing = $DB->get_record('local_student_sectors', [
                'userid' => $userid,
                'courseid' => $courseid,
                'sector' => $sector
            ]);

            if ($existing) {
                // Aggiorna record esistente
                $existing->quiz_count += 1;
                $existing->last_detected = $now;
                $existing->timemodified = $now;
                $DB->update_record('local_student_sectors', $existing);
            } else {
                // Crea nuovo record (non primario, source=quiz)
                $record = new \stdClass();
                $record->userid = $userid;
                $record->courseid = $courseid;
                $record->sector = $sector;
                $record->is_primary = 0;
                $record->source = 'quiz';
                $record->quiz_count = 1;
                $record->first_detected = $now;
                $record->last_detected = $now;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_student_sectors', $record);
            }

            $detected[] = $sector;
        }

        return $detected;
    }

    /**
     * Ottiene lista studenti con i loro settori
     *
     * @param array $filters Filtri: courseid, cohortid, sector, search, date_from, date_to
     * @return array Array di studenti con settori
     */
    public static function get_students_with_sectors($filters = []) {
        global $DB;

        $params = [];
        $where_parts = ["1=1"];
        $join_parts = [];

        // Base: studenti con ruolo student - include tutti i campi nome richiesti da Moodle
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       lsc.date_start, lsc.courseid as coaching_courseid,
                       lsc.current_week, lsc.status as coaching_status,
                       c.fullname as course_name,
                       ch.name as cohort_name
                FROM {user} u";

        // Join con coaching
        $join_parts[] = "LEFT JOIN {local_student_coaching} lsc ON lsc.userid = u.id";
        $join_parts[] = "LEFT JOIN {course} c ON c.id = lsc.courseid";

        // Join con coorti
        $join_parts[] = "LEFT JOIN {cohort_members} chm ON chm.userid = u.id";
        $join_parts[] = "LEFT JOIN {cohort} ch ON ch.id = chm.cohortid";

        // Filtro corso
        if (!empty($filters['courseid'])) {
            $where_parts[] = "lsc.courseid = :courseid";
            $params['courseid'] = $filters['courseid'];
        }

        // Filtro coorte
        if (!empty($filters['cohortid'])) {
            $where_parts[] = "chm.cohortid = :cohortid";
            $params['cohortid'] = $filters['cohortid'];
        }

        // Filtro ricerca nome
        if (!empty($filters['search'])) {
            $search = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where_parts[] = "(" . $DB->sql_like('u.firstname', ':search1', false) .
                             " OR " . $DB->sql_like('u.lastname', ':search2', false) .
                             " OR " . $DB->sql_like('u.email', ':search3', false) . ")";
            $params['search1'] = $search;
            $params['search2'] = $search;
            $params['search3'] = $search;
        }

        // Filtro data ingresso
        if (!empty($filters['date_from'])) {
            $where_parts[] = "lsc.date_start >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where_parts[] = "lsc.date_start <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        // Solo utenti attivi non admin
        $where_parts[] = "u.deleted = 0 AND u.suspended = 0 AND u.id > 2";

        $sql .= " " . implode(" ", $join_parts);
        $sql .= " WHERE " . implode(" AND ", $where_parts);
        $sql .= " ORDER BY lsc.date_start DESC, u.lastname, u.firstname";

        $students = $DB->get_records_sql($sql, $params);

        // Aggiungi i settori per ogni studente
        foreach ($students as &$student) {
            $student->sectors = self::get_student_sectors($student->id);
            $student->primary_sector = self::get_primary_sector($student->id);

            // Calcola colore in base a data ingresso
            $student->color_class = self::get_date_color_class($student->date_start);
        }

        // Filtro settore (post-query perche serve la lista settori)
        if (!empty($filters['sector'])) {
            $students = array_filter($students, function($s) use ($filters) {
                foreach ($s->sectors as $sec) {
                    if ($sec->sector === $filters['sector']) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $students;
    }

    /**
     * Ottiene la classe CSS colore in base alla data di ingresso
     *
     * @param int|null $date_start Timestamp data ingresso
     * @return string Classe CSS (green, yellow, orange, red)
     */
    public static function get_date_color_class($date_start) {
        if (empty($date_start)) {
            return 'gray';
        }

        $weeks = floor((time() - $date_start) / (7 * 24 * 60 * 60));

        if ($weeks < 2) {
            return 'green';  // < 2 settimane: nuovo
        } elseif ($weeks < 4) {
            return 'yellow'; // 2-4 settimane: in corso
        } elseif ($weeks < 6) {
            return 'orange'; // 4-6 settimane: fine vicina
        } else {
            return 'red';    // > 6 settimane: prolungo
        }
    }

    /**
     * Sincronizza il settore primario con local_student_coaching
     *
     * @param int $userid ID studente
     * @param int $courseid ID corso
     * @param string $sector Codice settore
     */
    private static function sync_coaching_sector($userid, $courseid, $sector) {
        global $DB;

        if ($courseid > 0) {
            $DB->execute(
                "UPDATE {local_student_coaching}
                 SET sector = ?, timemodified = ?
                 WHERE userid = ? AND courseid = ?",
                [$sector, time(), $userid, $courseid]
            );
        } else {
            // Se courseid = 0, aggiorna tutti i record coaching dello studente
            $DB->execute(
                "UPDATE {local_student_coaching}
                 SET sector = ?, timemodified = ?
                 WHERE userid = ?",
                [$sector, time(), $userid]
            );
        }
    }

    /**
     * Ottiene la lista dei corsi attivi
     *
     * @return array Array di corsi
     */
    public static function get_active_courses() {
        global $DB;

        return $DB->get_records_sql("
            SELECT c.id, c.fullname, c.shortname
            FROM {course} c
            WHERE c.visible = 1 AND c.id > 1
            ORDER BY c.fullname
        ");
    }

    /**
     * Ottiene la lista delle coorti visibili
     *
     * @return array Array di coorti
     */
    public static function get_visible_cohorts() {
        global $DB;

        return $DB->get_records_sql("
            SELECT ch.id, ch.name, ch.idnumber, ch.description
            FROM {cohort} ch
            WHERE ch.visible = 1
            ORDER BY ch.name
        ");
    }

    /**
     * Ottiene statistiche settori per un corso
     *
     * @param int $courseid ID corso (0 = tutti)
     * @return array Array di statistiche per settore
     */
    public static function get_sector_stats($courseid = 0) {
        global $DB;

        $params = [];
        $where = '';

        if ($courseid > 0) {
            $where = 'WHERE courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        return $DB->get_records_sql("
            SELECT sector,
                   COUNT(DISTINCT userid) as student_count,
                   SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) as primary_count,
                   SUM(quiz_count) as total_quizzes
            FROM {local_student_sectors}
            $where
            GROUP BY sector
            ORDER BY student_count DESC
        ", $params);
    }
}
