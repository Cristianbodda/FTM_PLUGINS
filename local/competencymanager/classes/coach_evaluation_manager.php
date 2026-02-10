<?php
/**
 * Coach Evaluation Manager - Gestione valutazioni formatore
 *
 * Sistema per la valutazione delle competenze da parte del coach
 * usando scala Bloom (1-6) + N/O (Non Osservato)
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

class coach_evaluation_manager {

    /** Status constants */
    const STATUS_DRAFT = 'draft';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SIGNED = 'signed';

    /** Rating constants */
    const RATING_NOT_OBSERVED = 0;
    const RATING_BLOOM_MIN = 1;
    const RATING_BLOOM_MAX = 6;

    /**
     * Scala Bloom con descrizioni
     */
    public static function get_bloom_scale(): array {
        return [
            0 => ['label' => 'N/O', 'description' => get_string('bloom_not_observed', 'local_competencymanager')],
            1 => ['label' => '1', 'description' => get_string('bloom_1_remember', 'local_competencymanager')],
            2 => ['label' => '2', 'description' => get_string('bloom_2_understand', 'local_competencymanager')],
            3 => ['label' => '3', 'description' => get_string('bloom_3_apply', 'local_competencymanager')],
            4 => ['label' => '4', 'description' => get_string('bloom_4_analyze', 'local_competencymanager')],
            5 => ['label' => '5', 'description' => get_string('bloom_5_evaluate', 'local_competencymanager')],
            6 => ['label' => '6', 'description' => get_string('bloom_6_create', 'local_competencymanager')],
        ];
    }

    /**
     * Crea una nuova valutazione
     *
     * @param int $studentid ID dello studente
     * @param int $coachid ID del coach
     * @param string $sector Settore (MECCANICA, AUTOMOBILE, ecc.)
     * @param int $courseid ID corso (opzionale)
     * @param bool $is_final_week Se è valutazione fine percorso
     * @return int ID della valutazione creata
     */
    public static function create_evaluation(int $studentid, int $coachid, string $sector,
                                             int $courseid = 0, bool $is_final_week = false): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->studentid = $studentid;
        $record->coachid = $coachid;
        $record->sector = strtoupper(trim($sector));
        $record->courseid = $courseid;
        $record->status = self::STATUS_DRAFT;
        $record->is_final_week = $is_final_week ? 1 : 0;
        $record->evaluation_date = null;
        $record->notes = null;
        $record->student_can_view = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $id = $DB->insert_record('local_coach_evaluations', $record);

        // Log creation in history
        self::log_history($id, null, 'created', null, null, null, $USER->id);

        return $id;
    }

    /**
     * Recupera una valutazione per ID
     *
     * @param int $evaluationid
     * @return object|false
     */
    public static function get_evaluation(int $evaluationid) {
        global $DB;
        return $DB->get_record('local_coach_evaluations', ['id' => $evaluationid]);
    }

    /**
     * Recupera tutte le valutazioni di uno studente
     *
     * @param int $studentid
     * @param string|null $sector Filtra per settore
     * @return array
     */
    public static function get_student_evaluations(int $studentid, ?string $sector = null): array {
        global $DB;

        $params = ['studentid' => $studentid];
        $sql = "SELECT e.*, u.firstname, u.lastname, u.email
                FROM {local_coach_evaluations} e
                JOIN {user} u ON u.id = e.coachid
                WHERE e.studentid = :studentid";

        if ($sector) {
            $sql .= " AND e.sector = :sector";
            $params['sector'] = strtoupper(trim($sector));
        }

        $sql .= " ORDER BY e.timecreated DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Recupera valutazioni create da un coach
     *
     * @param int $coachid
     * @return array
     */
    public static function get_coach_evaluations(int $coachid): array {
        global $DB;

        $sql = "SELECT e.*, u.firstname, u.lastname, u.email as student_email
                FROM {local_coach_evaluations} e
                JOIN {user} u ON u.id = e.studentid
                WHERE e.coachid = :coachid
                ORDER BY e.timecreated DESC";

        return $DB->get_records_sql($sql, ['coachid' => $coachid]);
    }

    /**
     * Salva un voto per una competenza
     *
     * @param int $evaluationid
     * @param int $competencyid
     * @param int $rating 0-6 (0=N/O, 1-6=Bloom)
     * @param string|null $notes
     * @return int ID del rating
     */
    public static function save_rating(int $evaluationid, int $competencyid, int $rating, ?string $notes = null): int {
        global $DB, $USER;

        // Validate rating
        if ($rating < self::RATING_NOT_OBSERVED || $rating > self::RATING_BLOOM_MAX) {
            throw new \invalid_parameter_exception('Rating must be between 0 and 6');
        }

        $now = time();
        $existing = $DB->get_record('local_coach_eval_ratings', [
            'evaluationid' => $evaluationid,
            'competencyid' => $competencyid
        ]);

        if ($existing) {
            // Update existing
            $old_rating = $existing->rating;
            $old_notes = $existing->notes;

            $existing->rating = $rating;
            $existing->notes = $notes;
            $existing->timemodified = $now;

            $DB->update_record('local_coach_eval_ratings', $existing);

            // Log changes
            if ($old_rating != $rating) {
                self::log_history($evaluationid, $existing->id, 'updated', 'rating',
                                  (string)$old_rating, (string)$rating, $USER->id);
            }
            if ($old_notes != $notes) {
                self::log_history($evaluationid, $existing->id, 'updated', 'notes',
                                  $old_notes, $notes, $USER->id);
            }

            return $existing->id;
        } else {
            // Insert new
            $record = new \stdClass();
            $record->evaluationid = $evaluationid;
            $record->competencyid = $competencyid;
            $record->rating = $rating;
            $record->notes = $notes;
            $record->timecreated = $now;
            $record->timemodified = $now;

            $id = $DB->insert_record('local_coach_eval_ratings', $record);

            self::log_history($evaluationid, $id, 'created', 'rating', null, (string)$rating, $USER->id);

            return $id;
        }
    }

    /**
     * Salva voti multipli in batch
     *
     * @param int $evaluationid
     * @param array $ratings Array di ['competencyid' => X, 'rating' => Y, 'notes' => Z]
     * @return int Numero di rating salvati
     */
    public static function save_ratings_batch(int $evaluationid, array $ratings): int {
        $count = 0;
        foreach ($ratings as $r) {
            if (isset($r['competencyid']) && isset($r['rating'])) {
                self::save_rating(
                    $evaluationid,
                    (int)$r['competencyid'],
                    (int)$r['rating'],
                    $r['notes'] ?? null
                );
                $count++;
            }
        }
        return $count;
    }

    /**
     * Recupera tutti i voti di una valutazione
     *
     * @param int $evaluationid
     * @return array
     */
    public static function get_evaluation_ratings(int $evaluationid): array {
        global $DB;

        $sql = "SELECT r.*, c.idnumber, c.shortname, c.description as comp_description
                FROM {local_coach_eval_ratings} r
                JOIN {competency} c ON c.id = r.competencyid
                WHERE r.evaluationid = :evaluationid
                ORDER BY c.idnumber";

        return $DB->get_records_sql($sql, ['evaluationid' => $evaluationid]);
    }

    /**
     * Recupera voti raggruppati per area (A, B, C, ecc.)
     *
     * @param int $evaluationid
     * @return array Struttura: ['A' => [...ratings], 'B' => [...ratings], ...]
     */
    public static function get_ratings_by_area(int $evaluationid): array {
        $ratings = self::get_evaluation_ratings($evaluationid);
        $byArea = [];

        foreach ($ratings as $r) {
            // Estrae l'area dall'idnumber (es. MECCANICA_A_01 -> A)
            $area = self::extract_area_from_idnumber($r->idnumber);
            if (!isset($byArea[$area])) {
                $byArea[$area] = [];
            }
            $byArea[$area][] = $r;
        }

        ksort($byArea);
        return $byArea;
    }

    /**
     * Estrae l'area (A, B, C, ...) dall'idnumber della competenza
     * Usa la stessa logica di get_area_info() in area_mapping.php
     *
     * @param string $idnumber Es: AUTOMOBILE_AU_A01, MECCANICA_CNC_01
     * @return string Area (A, B, C, CNC, LMB, ...) o 'OTHER'
     */
    public static function extract_area_from_idnumber(string $idnumber): string {
        // Include area_mapping.php se non già caricato
        static $mappingLoaded = false;
        if (!$mappingLoaded) {
            $mappingFile = __DIR__ . '/../area_mapping.php';
            if (file_exists($mappingFile)) {
                require_once($mappingFile);
            }
            $mappingLoaded = true;
        }

        // Usa get_area_info() se disponibile (definita in area_mapping.php)
        if (function_exists('get_area_info')) {
            $areaInfo = get_area_info($idnumber);
            return $areaInfo['code'] ?? 'OTHER';
        }

        // Fallback: logica semplificata
        // Rimuovi prefisso OLD_ se presente
        if (strpos($idnumber, 'OLD_') === 0) {
            $idnumber = substr($idnumber, 4);
        }

        $parts = explode('_', $idnumber);
        if (count($parts) < 2) {
            return 'OTHER';
        }

        $sector = strtoupper($parts[0]);

        // Settori letter-based (AUTOMOBILE, LOGISTICA, ELETTRICITÀ)
        // Format: SETTORE_XX_A01 -> area = A
        $letterBasedSectors = ['AUTOMOBILE', 'LOGISTICA', 'ELETTRICITÀ', 'ELETTRICITA'];
        if (in_array($sector, $letterBasedSectors) && count($parts) >= 3) {
            if (preg_match('/^([A-Z])/i', $parts[2], $matches)) {
                return strtoupper($matches[1]);
            }
        }

        // Settori code-based (MECCANICA, CHIMFARM, METALCOSTRUZIONE)
        // Format: SETTORE_CODE_01 -> area = CODE
        $codeBasedSectors = ['MECCANICA', 'CHIMFARM', 'METALCOSTRUZIONE', 'AUTOMAZIONE'];
        if (in_array($sector, $codeBasedSectors) && count($parts) >= 2) {
            return strtoupper($parts[1]);
        }

        // GEN format: GEN_A_01 -> area = A
        if ($sector === 'GEN' && count($parts) >= 2) {
            $letter = strtoupper($parts[1]);
            if (preg_match('/^[A-Z]$/', $letter)) {
                return $letter;
            }
        }

        // Fallback: seconda parte
        return strtoupper($parts[1] ?? 'OTHER');
    }

    /**
     * Completa una valutazione
     *
     * @param int $evaluationid
     * @return bool
     */
    public static function complete_evaluation(int $evaluationid): bool {
        global $DB, $USER;

        $evaluation = self::get_evaluation($evaluationid);
        if (!$evaluation) {
            return false;
        }

        $now = time();
        $evaluation->status = self::STATUS_COMPLETED;
        $evaluation->evaluation_date = $now;
        $evaluation->timemodified = $now;

        $DB->update_record('local_coach_evaluations', $evaluation);

        self::log_history($evaluationid, null, 'updated', 'status',
                          self::STATUS_DRAFT, self::STATUS_COMPLETED, $USER->id);

        return true;
    }

    /**
     * Firma una valutazione (non più modificabile)
     *
     * @param int $evaluationid
     * @return bool
     */
    public static function sign_evaluation(int $evaluationid): bool {
        global $DB, $USER;

        $evaluation = self::get_evaluation($evaluationid);
        if (!$evaluation || $evaluation->status !== self::STATUS_COMPLETED) {
            return false;
        }

        $evaluation->status = self::STATUS_SIGNED;
        $evaluation->timemodified = time();

        $DB->update_record('local_coach_evaluations', $evaluation);

        self::log_history($evaluationid, null, 'updated', 'status',
                          self::STATUS_COMPLETED, self::STATUS_SIGNED, $USER->id);

        return true;
    }

    /**
     * Aggiorna le note generali della valutazione
     *
     * @param int $evaluationid
     * @param string $notes
     * @return bool
     */
    public static function update_notes(int $evaluationid, string $notes): bool {
        global $DB, $USER;

        $evaluation = self::get_evaluation($evaluationid);
        if (!$evaluation) {
            return false;
        }

        $old_notes = $evaluation->notes;
        $evaluation->notes = $notes;
        $evaluation->timemodified = time();

        $DB->update_record('local_coach_evaluations', $evaluation);

        if ($old_notes !== $notes) {
            self::log_history($evaluationid, null, 'updated', 'notes', $old_notes, $notes, $USER->id);
        }

        return true;
    }

    /**
     * Autorizza lo studente a vedere la valutazione
     *
     * @param int $evaluationid
     * @param bool $canview
     * @return bool
     */
    public static function set_student_can_view(int $evaluationid, bool $canview): bool {
        global $DB, $USER;

        $evaluation = self::get_evaluation($evaluationid);
        if (!$evaluation) {
            return false;
        }

        $old_value = $evaluation->student_can_view;
        $evaluation->student_can_view = $canview ? 1 : 0;
        $evaluation->timemodified = time();

        $DB->update_record('local_coach_evaluations', $evaluation);

        if ($old_value != $evaluation->student_can_view) {
            self::log_history($evaluationid, null, 'updated', 'student_can_view',
                              (string)$old_value, (string)$evaluation->student_can_view, $USER->id);
        }

        return true;
    }

    /**
     * Verifica permessi di modifica
     *
     * @param int $evaluationid
     * @param int|null $userid User to check (default current user)
     * @return bool
     */
    public static function can_edit(int $evaluationid, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? $USER->id;
        $evaluation = self::get_evaluation($evaluationid);

        if (!$evaluation) {
            return false;
        }

        // Signed evaluations cannot be edited
        if ($evaluation->status === self::STATUS_SIGNED) {
            return false;
        }

        // Owner can always edit draft/completed
        if ($evaluation->coachid == $userid) {
            return true;
        }

        // Users with editallevaluations capability can edit (with tracking)
        $context = \context_system::instance();
        if (has_capability('local/competencymanager:editallevaluations', $context, $userid)) {
            return true;
        }

        return false;
    }

    /**
     * Verifica permessi di visualizzazione
     *
     * @param int $evaluationid
     * @param int|null $userid
     * @return bool
     */
    public static function can_view(int $evaluationid, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? $USER->id;
        $evaluation = self::get_evaluation($evaluationid);

        if (!$evaluation) {
            return false;
        }

        // Owner can always view
        if ($evaluation->coachid == $userid) {
            return true;
        }

        // Student can view if authorized
        if ($evaluation->studentid == $userid && $evaluation->student_can_view) {
            return true;
        }

        // Users with viewallevaluations capability can view
        $context = \context_system::instance();
        if (has_capability('local/competencymanager:viewallevaluations', $context, $userid)) {
            return true;
        }

        return false;
    }

    /**
     * Registra una modifica nello storico
     *
     * @param int $evaluationid
     * @param int|null $ratingid
     * @param string $action
     * @param string|null $field_changed
     * @param string|null $old_value
     * @param string|null $new_value
     * @param int $userid
     */
    public static function log_history(int $evaluationid, ?int $ratingid, string $action,
                                        ?string $field_changed, ?string $old_value,
                                        ?string $new_value, int $userid): void {
        global $DB;

        $record = new \stdClass();
        $record->evaluationid = $evaluationid;
        $record->ratingid = $ratingid;
        $record->action = $action;
        $record->field_changed = $field_changed;
        $record->old_value = $old_value;
        $record->new_value = $new_value;
        $record->userid = $userid;
        $record->timecreated = time();

        $DB->insert_record('local_coach_eval_history', $record);
    }

    /**
     * Recupera lo storico modifiche di una valutazione
     *
     * @param int $evaluationid
     * @return array
     */
    public static function get_history(int $evaluationid): array {
        global $DB;

        $sql = "SELECT h.*, u.firstname, u.lastname
                FROM {local_coach_eval_history} h
                JOIN {user} u ON u.id = h.userid
                WHERE h.evaluationid = :evaluationid
                ORDER BY h.timecreated DESC";

        return $DB->get_records_sql($sql, ['evaluationid' => $evaluationid]);
    }

    /**
     * Calcola la media dei voti per una valutazione (esclude N/O)
     *
     * @param int $evaluationid
     * @return float|null
     */
    public static function calculate_average(int $evaluationid): ?float {
        global $DB;

        $sql = "SELECT AVG(rating) as avg_rating
                FROM {local_coach_eval_ratings}
                WHERE evaluationid = :evaluationid
                AND rating > 0";

        $result = $DB->get_record_sql($sql, ['evaluationid' => $evaluationid]);

        return $result && $result->avg_rating !== null ? round($result->avg_rating, 2) : null;
    }

    /**
     * Conta quante competenze sono state valutate (esclude N/O)
     *
     * @param int $evaluationid
     * @return array ['total' => X, 'rated' => Y, 'not_observed' => Z]
     */
    public static function get_rating_stats(int $evaluationid): array {
        global $DB;

        $total = $DB->count_records('local_coach_eval_ratings', ['evaluationid' => $evaluationid]);
        $not_observed = $DB->count_records('local_coach_eval_ratings', [
            'evaluationid' => $evaluationid,
            'rating' => self::RATING_NOT_OBSERVED
        ]);

        return [
            'total' => $total,
            'rated' => $total - $not_observed,
            'not_observed' => $not_observed
        ];
    }

    /**
     * Recupera i dati di valutazione formattati per il radar chart
     *
     * @param int $studentid
     * @param string $sector
     * @return array Array di ['area' => X, 'value' => Y] normalizzato 0-100
     */
    public static function get_radar_data(int $studentid, string $sector, bool $include_draft = true): array {
        global $DB;

        // Find the most recent evaluation for this student/sector
        // Include draft if requested (for overlay chart), otherwise only completed/signed
        $statusFilter = $include_draft ? "('draft', 'completed', 'signed')" : "('completed', 'signed')";

        $evaluation = $DB->get_record_sql(
            "SELECT * FROM {local_coach_evaluations}
             WHERE studentid = :studentid AND sector = :sector
             AND status IN $statusFilter
             ORDER BY evaluation_date DESC, id DESC
             LIMIT 1",
            ['studentid' => $studentid, 'sector' => strtoupper($sector)]
        );

        if (!$evaluation) {
            return [];
        }

        // Get ratings grouped by area
        $ratingsByArea = self::get_ratings_by_area($evaluation->id);
        $radarData = [];

        foreach ($ratingsByArea as $area => $ratings) {
            // Calculate average for area
            $sum = 0;
            $countRated = 0;
            $countTotal = count($ratings);

            foreach ($ratings as $r) {
                if ($r->rating > 0) {
                    $sum += $r->rating;
                    $countRated++;
                }
            }

            // Include area even if all N/O (show as 0)
            if ($countTotal > 0) {
                if ($countRated > 0) {
                    $avgBloom = $sum / $countRated;
                    $percentage = round(($avgBloom / 6) * 100, 1);
                } else {
                    // All N/O - show as 0
                    $avgBloom = 0;
                    $percentage = 0;
                }

                $radarData[] = [
                    'area' => $area,
                    'label' => "Area $area",
                    'value' => $percentage,
                    'bloom_avg' => round($avgBloom, 2),
                    'rated_count' => $countRated,
                    'total_count' => $countTotal
                ];
            }
        }

        return $radarData;
    }

    /**
     * Verifica se esiste già una valutazione per studente/settore in corso
     *
     * @param int $studentid
     * @param string $sector
     * @return object|false
     */
    public static function get_active_evaluation(int $studentid, string $sector) {
        global $DB;

        return $DB->get_record_sql(
            "SELECT * FROM {local_coach_evaluations}
             WHERE studentid = :studentid
             AND sector = :sector
             AND status = :status
             ORDER BY timecreated DESC
             LIMIT 1",
            [
                'studentid' => $studentid,
                'sector' => strtoupper($sector),
                'status' => self::STATUS_DRAFT
            ]
        );
    }

    /**
     * Recupera o crea una valutazione per lo studente
     *
     * @param int $studentid
     * @param int $coachid
     * @param string $sector
     * @param int $courseid
     * @param bool $is_final_week
     * @return int ID della valutazione
     */
    public static function get_or_create_evaluation(int $studentid, int $coachid, string $sector,
                                                     int $courseid = 0, bool $is_final_week = false): int {
        $existing = self::get_active_evaluation($studentid, $sector);

        if ($existing) {
            return $existing->id;
        }

        return self::create_evaluation($studentid, $coachid, $sector, $courseid, $is_final_week);
    }

    /**
     * Elimina una valutazione (solo draft, solo owner o admin)
     *
     * @param int $evaluationid
     * @return bool
     */
    public static function delete_evaluation(int $evaluationid): bool {
        global $DB, $USER;

        $evaluation = self::get_evaluation($evaluationid);
        if (!$evaluation) {
            return false;
        }

        // Only draft can be deleted
        if ($evaluation->status !== self::STATUS_DRAFT) {
            return false;
        }

        // Check permission
        $context = \context_system::instance();
        $isOwner = ($evaluation->coachid == $USER->id);
        $isAdmin = has_capability('local/competencymanager:editallevaluations', $context);

        if (!$isOwner && !$isAdmin) {
            return false;
        }

        // Delete ratings first
        $DB->delete_records('local_coach_eval_ratings', ['evaluationid' => $evaluationid]);

        // Log before deleting
        self::log_history($evaluationid, null, 'deleted', null, null, null, $USER->id);

        // Delete evaluation (history remains for audit)
        $DB->delete_records('local_coach_evaluations', ['id' => $evaluationid]);

        return true;
    }
}
