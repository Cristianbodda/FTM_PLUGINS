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
        'GEN' => 'Generico',
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

    // ============================================
    // FUNZIONI NAVIGAZIONE STUDENTE (Cascata)
    // ============================================

    /**
     * Verifica se le tabelle FTM Scheduler esistono
     *
     * @return bool True se le tabelle esistono
     */
    public static function ftm_scheduler_tables_exist() {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists('local_ftm_groups') &&
               $dbman->table_exists('local_ftm_group_members');
    }

    /**
     * Ottiene le coorti che hanno studenti con dati FTM
     * (studenti che sono in un gruppo FTM o hanno fatto quiz)
     *
     * @return array Array di coorti con conteggio studenti
     */
    public static function get_cohorts_with_students() {
        global $DB;

        // Coorti con studenti che hanno quiz completati o sono in gruppi FTM
        $sql = "SELECT DISTINCT ch.id, ch.name, ch.idnumber,
                       COUNT(DISTINCT cm.userid) as student_count
                FROM {cohort} ch
                JOIN {cohort_members} cm ON cm.cohortid = ch.id
                JOIN {user} u ON u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0
                LEFT JOIN {quiz_attempts} qa ON qa.userid = u.id AND qa.state = 'finished'
                WHERE ch.visible = 1
                  AND (qa.id IS NOT NULL OR EXISTS (
                      SELECT 1 FROM {local_ftm_group_members} gm WHERE gm.userid = u.id
                  ))
                GROUP BY ch.id, ch.name, ch.idnumber
                HAVING student_count > 0
                ORDER BY ch.name";

        $cohorts = $DB->get_records_sql($sql);

        // Se non ci sono risultati con quiz/gruppi, ritorna tutte le coorti visibili
        if (empty($cohorts)) {
            return $DB->get_records_sql("
                SELECT ch.id, ch.name, ch.idnumber,
                       COUNT(DISTINCT cm.userid) as student_count
                FROM {cohort} ch
                JOIN {cohort_members} cm ON cm.cohortid = ch.id
                JOIN {user} u ON u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0
                WHERE ch.visible = 1
                GROUP BY ch.id, ch.name, ch.idnumber
                HAVING student_count > 0
                ORDER BY ch.name
            ");
        }

        return $cohorts;
    }

    /**
     * Ottiene i colori (gruppi) disponibili per una coorte
     *
     * @param int $cohortid ID coorte (0 = tutte)
     * @return array Array di colori con conteggio studenti
     */
    public static function get_colors_for_cohort($cohortid = 0) {
        global $DB;

        if (!self::ftm_scheduler_tables_exist()) {
            return [];
        }

        $params = [];
        $cohort_join = '';
        $cohort_where = '';

        if ($cohortid > 0) {
            $cohort_join = "JOIN {cohort_members} cm ON cm.userid = gm.userid";
            $cohort_where = "AND cm.cohortid = :cohortid";
            $params['cohortid'] = $cohortid;
        }

        $sql = "SELECT g.color, g.color_hex,
                       COUNT(DISTINCT gm.userid) as student_count
                FROM {local_ftm_groups} g
                JOIN {local_ftm_group_members} gm ON gm.groupid = g.id
                JOIN {user} u ON u.id = gm.userid AND u.deleted = 0 AND u.suspended = 0
                $cohort_join
                WHERE gm.status = 'active'
                $cohort_where
                GROUP BY g.color, g.color_hex
                ORDER BY FIELD(g.color, 'giallo', 'grigio', 'rosso', 'marrone', 'viola')";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Ottiene le settimane KW disponibili per una coorte e colore
     *
     * @param int $cohortid ID coorte (0 = tutte)
     * @param string $color Colore gruppo (vuoto = tutti)
     * @return array Array di settimane con conteggio studenti
     */
    public static function get_weeks_for_color($cohortid = 0, $color = '') {
        global $DB;

        if (!self::ftm_scheduler_tables_exist()) {
            return [];
        }

        $params = [];
        $joins = [];
        $wheres = ["gm.status = 'active'"];

        if ($cohortid > 0) {
            $joins[] = "JOIN {cohort_members} cm ON cm.userid = gm.userid";
            $wheres[] = "cm.cohortid = :cohortid";
            $params['cohortid'] = $cohortid;
        }

        if (!empty($color)) {
            $wheres[] = "g.color = :color";
            $params['color'] = $color;
        }

        $join_sql = implode(' ', $joins);
        $where_sql = implode(' AND ', $wheres);

        $sql = "SELECT g.calendar_week, g.entry_date,
                       COUNT(DISTINCT gm.userid) as student_count,
                       g.color, g.name as group_name
                FROM {local_ftm_groups} g
                JOIN {local_ftm_group_members} gm ON gm.groupid = g.id
                JOIN {user} u ON u.id = gm.userid AND u.deleted = 0 AND u.suspended = 0
                $join_sql
                WHERE $where_sql
                GROUP BY g.calendar_week, g.entry_date, g.color, g.name
                ORDER BY g.entry_date DESC, g.calendar_week DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Ottiene gli studenti filtrati per navigazione cascata
     *
     * @param int $cohortid ID coorte (0 = tutte)
     * @param string $color Colore gruppo (vuoto = tutti)
     * @param int $calendar_week Settimana KW (0 = tutte)
     * @return array Array di studenti
     */
    public static function get_students_for_navigation($cohortid = 0, $color = '', $calendar_week = 0) {
        global $DB;

        $params = [];
        $joins = [];
        $wheres = ["u.deleted = 0", "u.suspended = 0", "u.id > 2"];

        // Join base
        $joins[] = "LEFT JOIN {local_ftm_group_members} gm ON gm.userid = u.id AND gm.status = 'active'";
        $joins[] = "LEFT JOIN {local_ftm_groups} g ON g.id = gm.groupid";

        // Filtro coorte
        if ($cohortid > 0) {
            $joins[] = "JOIN {cohort_members} cm ON cm.userid = u.id";
            $wheres[] = "cm.cohortid = :cohortid";
            $params['cohortid'] = $cohortid;
        }

        // Filtro colore
        if (!empty($color)) {
            $wheres[] = "g.color = :color";
            $params['color'] = $color;
        }

        // Filtro settimana KW
        if ($calendar_week > 0) {
            $wheres[] = "g.calendar_week = :calendar_week";
            $params['calendar_week'] = $calendar_week;
        }

        $join_sql = implode(' ', $joins);
        $where_sql = implode(' AND ', $wheres);

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       g.color as group_color, g.color_hex, g.calendar_week,
                       g.name as group_name, g.entry_date,
                       gm.current_week, gm.status as member_status
                FROM {user} u
                $join_sql
                WHERE $where_sql
                ORDER BY u.lastname, u.firstname";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Ottiene i settori di uno studente con conteggio quiz (per filtro intelligente)
     *
     * @param int $userid ID studente
     * @return array Array di settori con quiz_count
     */
    public static function get_student_sectors_with_quiz_data($userid) {
        global $DB;

        // Ottieni settori dalla tabella dedicata (include is_primary)
        $savedSectors = self::get_student_sectors($userid);

        // SEMPRE calcola i settori dai quiz completati per avere conteggi aggiornati
        // Query corretta per Moodle 4.x con question_references e question_versions
        $sql = "SELECT DISTINCT c.idnumber
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                JOIN {quiz_slots} qs ON qs.quizid = q.id
                JOIN {question_references} qr ON qr.itemid = qs.id
                     AND qr.component = 'mod_quiz'
                     AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {qbank_competenciesbyquestion} cq ON cq.questionid = qv.questionid
                JOIN {competency} c ON c.id = cq.competencyid
                WHERE qa.userid = :userid
                  AND qa.state = 'finished'
                  AND c.idnumber IS NOT NULL
                  AND c.idnumber != ''";

        $competencies = $DB->get_records_sql($sql, ['userid' => $userid]);

        // Conta competenze per settore (rappresenta quante domande con competenza per settore)
        $sector_counts = [];
        foreach ($competencies as $comp) {
            $sector = extract_sector_from_idnumber($comp->idnumber);
            if ($sector && $sector !== 'UNKNOWN') {
                $sectorUpper = strtoupper($sector);
                if (!isset($sector_counts[$sectorUpper])) {
                    $sector_counts[$sectorUpper] = 0;
                }
                $sector_counts[$sectorUpper]++;
            }
        }

        // Combina settori salvati con conteggi calcolati
        $result = [];

        // Prima aggiungi settori salvati con conteggi aggiornati
        foreach ($savedSectors as $saved) {
            $sectorUpper = strtoupper($saved->sector);
            $obj = new \stdClass();
            $obj->sector = $sectorUpper;
            $obj->quiz_count = $sector_counts[$sectorUpper] ?? 0;
            $obj->is_primary = $saved->is_primary ?? 0;
            $result[$sectorUpper] = $obj;
            // Rimuovi dal conteggio per non duplicare
            unset($sector_counts[$sectorUpper]);
        }

        // Poi aggiungi settori rilevati dai quiz che non erano salvati
        foreach ($sector_counts as $sector => $count) {
            if (!isset($result[$sector])) {
                $obj = new \stdClass();
                $obj->sector = $sector;
                $obj->quiz_count = $count;
                $obj->is_primary = 0;
                $result[$sector] = $obj;
            }
        }

        // Controlla se lo studente ha autovalutazioni con competenze GENERICO (framework FTM_GEN)
        // Questo è necessario perché GENERICO ha un framework separato (FTM_GEN) e potrebbe non essere
        // rilevato dalla query quiz sopra
        if (!isset($result['GEN']) && !isset($result['GENERICO'])) {
            $genCount = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT sa.competencyid)
                 FROM {local_selfassessment} sa
                 JOIN {competency} c ON c.id = sa.competencyid
                 JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                 WHERE sa.userid = :userid
                   AND cf.idnumber = 'FTM_GEN'",
                ['userid' => $userid]
            );

            if ($genCount > 0) {
                $obj = new \stdClass();
                $obj->sector = 'GEN';
                $obj->quiz_count = 0; // Autovalutazioni, non quiz
                $obj->is_primary = 0;
                $obj->has_selfassessment = true;
                $result['GEN'] = $obj;
            }
        }

        // Ordina: primario prima, poi per quiz_count decrescente
        uasort($result, function($a, $b) {
            if ($a->is_primary != $b->is_primary) {
                return $b->is_primary - $a->is_primary;
            }
            return $b->quiz_count - $a->quiz_count;
        });

        return $result;
    }

    /**
     * Nomi display per i colori FTM
     */
    const COLOR_NAMES = [
        'giallo' => 'Giallo',
        'grigio' => 'Grigio',
        'rosso' => 'Rosso',
        'marrone' => 'Marrone',
        'viola' => 'Viola',
    ];

    /**
     * Colori HEX per i gruppi FTM
     */
    const COLOR_HEX = [
        'giallo' => '#FFFF00',
        'grigio' => '#808080',
        'rosso' => '#FF0000',
        'marrone' => '#996633',
        'viola' => '#7030A0',
    ];

    // ============================================
    // GESTIONE MULTI-SETTORE (Coach Assignment)
    // ============================================

    /**
     * Imposta i settori dello studente (primario, secondario, terziario)
     *
     * @param int $userid ID studente
     * @param string $primary Settore primario
     * @param string $secondary Settore secondario (opzionale)
     * @param string $tertiary Settore terziario (opzionale)
     * @param int $courseid ID corso (0 = globale)
     * @return bool Success
     */
    public static function set_student_sectors($userid, $primary, $secondary = '', $tertiary = '', $courseid = 0) {
        global $DB;

        $now = time();

        // Clear all existing is_primary flags for this user
        $DB->execute(
            "UPDATE {local_student_sectors} SET is_primary = 0, timemodified = :time WHERE userid = :userid AND courseid = :courseid",
            ['time' => $now, 'userid' => $userid, 'courseid' => $courseid]
        );

        // Helper function to upsert sector
        $upsertSector = function($sector, $isPrimary) use ($DB, $userid, $courseid, $now) {
            if (empty($sector)) {
                return;
            }

            $existing = $DB->get_record('local_student_sectors', [
                'userid' => $userid,
                'courseid' => $courseid,
                'sector' => $sector
            ]);

            if ($existing) {
                $existing->is_primary = $isPrimary;
                $existing->source = 'manual';
                $existing->timemodified = $now;
                $DB->update_record('local_student_sectors', $existing);
            } else {
                $record = new \stdClass();
                $record->userid = $userid;
                $record->courseid = $courseid;
                $record->sector = $sector;
                $record->is_primary = $isPrimary;
                $record->source = 'manual';
                $record->quiz_count = 0;
                $record->first_detected = $now;
                $record->last_detected = $now;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_student_sectors', $record);
            }
        };

        // Set primary sector
        $upsertSector($primary, 1);

        // Set secondary sector
        $upsertSector($secondary, 0);

        // Set tertiary sector
        $upsertSector($tertiary, 0);

        // Sync with coaching table if primary sector is set
        if (!empty($primary)) {
            self::sync_coaching_sector($userid, $courseid, $primary);
        }

        return true;
    }

    /**
     * Aggiunge un settore a uno studente (secondario/terziario)
     *
     * @param int $userid ID studente
     * @param string $sector Codice settore
     * @param int $courseid ID corso (0 = globale)
     * @return bool Success
     */
    public static function add_sector($userid, $sector, $courseid = 0) {
        global $DB;

        if (empty($sector)) {
            return false;
        }

        // Check if sector already exists
        $existing = $DB->get_record('local_student_sectors', [
            'userid' => $userid,
            'courseid' => $courseid,
            'sector' => $sector
        ]);

        if ($existing) {
            // Already exists, no need to add
            return true;
        }

        $now = time();
        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->sector = $sector;
        $record->is_primary = 0; // Secondary/tertiary sectors are not primary
        $record->source = 'manual';
        $record->quiz_count = 0;
        $record->first_detected = $now;
        $record->last_detected = $now;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record('local_student_sectors', $record) > 0;
    }

    /**
     * Rimuove un settore da uno studente
     *
     * @param int $userid ID studente
     * @param string $sector Codice settore
     * @param int $courseid ID corso (0 = globale)
     * @return bool Success
     */
    public static function remove_sector($userid, $sector, $courseid = 0) {
        global $DB;

        if (empty($sector)) {
            return false;
        }

        return $DB->delete_records('local_student_sectors', [
            'userid' => $userid,
            'courseid' => $courseid,
            'sector' => $sector
        ]);
    }

    /**
     * Ottiene i settori con rank (primary=1, secondary=2, tertiary=3)
     *
     * @param int $userid ID studente
     * @param int $courseid ID corso (0 = globale)
     * @return array Array con chiavi 'primary', 'secondary', 'tertiary'
     */
    public static function get_student_sectors_ranked($userid, $courseid = 0) {
        $sectors = self::get_student_sectors($userid, $courseid);

        $result = [
            'primary' => null,
            'secondary' => null,
            'tertiary' => null,
            'all' => []
        ];

        $rank = 0;
        foreach ($sectors as $sec) {
            $result['all'][] = $sec;
            if ($sec->is_primary) {
                $result['primary'] = $sec->sector;
            } else if ($rank == 0 && empty($result['primary'])) {
                // First one becomes primary if no primary flag set
                $result['primary'] = $sec->sector;
            } else if (empty($result['secondary']) && $sec->sector !== $result['primary']) {
                $result['secondary'] = $sec->sector;
            } else if (empty($result['tertiary']) && $sec->sector !== $result['primary'] && $sec->sector !== $result['secondary']) {
                $result['tertiary'] = $sec->sector;
            }
            $rank++;
        }

        return $result;
    }
}
