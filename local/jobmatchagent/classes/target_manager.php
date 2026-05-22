<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manager for student autocandidatura targets.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages student–company targets (autocandidature) and the related CI integration.
 */
class target_manager {

    /** Valid target status values. */
    const STATI_VALIDI = [
        'pending',
        'lettera_generata',
        'inviata',
        'risposta',
        'colloquio',
        'assunto',
        'rifiutato',
    ];

    /**
     * Returns all targets for a student, joined with company data.
     *
     * @param int $userid Student user id.
     * @return array Array of stdClass records (target + company fields prefixed with company_).
     */
    public static function get_student_targets(int $userid): array {
        global $DB;

        $sql = "SELECT t.*,
                       c.nome            AS company_nome,
                       c.indirizzo       AS company_indirizzo,
                       c.cap             AS company_cap,
                       c.localita        AS company_localita,
                       c.settore_ftm     AS company_settore_ftm,
                       c.settore_raw     AS company_settore_raw,
                       c.dimensione      AS company_dimensione,
                       c.website         AS company_website,
                       c.email           AS company_email,
                       c.referente       AS company_referente,
                       c.status          AS company_status
                  FROM {local_jobmatch_student_targets} t
                  JOIN {local_jobmatch_ticino_companies} c ON c.id = t.company_id
                 WHERE t.userid = :userid
              ORDER BY t.timecreated DESC";

        return array_values($DB->get_records_sql($sql, ['userid' => $userid]));
    }

    /**
     * Returns a single target record by id.
     *
     * @param int $id
     * @return stdClass|null
     */
    public static function get_target(int $id): ?object {
        global $DB;
        $record = $DB->get_record('local_jobmatch_student_targets', ['id' => $id], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Creates a new student–company target.
     *
     * Throws a \moodle_exception if the pair (userid, company_id) already exists.
     *
     * @param int    $userid       Student user id.
     * @param int    $company_id   Company id from local_jobmatch_ticino_companies.
     * @param int    $coach_userid Coach who created the target.
     * @param string $note_per_ai  Optional contextual note for AI letter generation.
     * @return int Id of the new record.
     */
    public static function create_target(int $userid, int $company_id, int $coach_userid, string $note_per_ai = ''): int {
        global $DB;

        // Enforce unique constraint at application level to give a readable error.
        $exists = $DB->record_exists('local_jobmatch_student_targets', [
            'userid'     => $userid,
            'company_id' => $company_id,
        ]);
        if ($exists) {
            throw new \moodle_exception('errorduplicatetarget', 'local_jobmatchagent',
                '', null, "userid=$userid company_id=$company_id");
        }

        $now = time();
        $record = (object)[
            'userid'       => $userid,
            'company_id'   => $company_id,
            'coach_userid' => $coach_userid,
            'note_per_ai'  => $note_per_ai,
            'status'       => 'pending',
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return (int)$DB->insert_record('local_jobmatch_student_targets', $record);
    }

    /**
     * Updates an arbitrary subset of fields on a target.
     *
     * Status changes must go through update_status() to trigger side-effects.
     *
     * @param int   $id   Target id.
     * @param array $data Associative array of fields to update.
     * @return void
     */
    public static function update_target(int $id, array $data): void {
        global $DB;

        // Prevent accidental status change through this method.
        unset($data['status']);
        unset($data['id']);

        if (empty($data)) {
            return;
        }

        $record = (object)$data;
        $record->id = $id;
        $record->timemodified = time();
        $DB->update_record('local_jobmatch_student_targets', $record);
    }

    /**
     * Updates the status of a target and triggers CI integration when appropriate.
     *
     * When status transitions to 'inviata' and the student has an active CI
     * enrollment, a search entry is auto-logged in local_ftm_sip_search_entries.
     *
     * @param int    $id         Target id.
     * @param string $status     New status value (see STATI_VALIDI).
     * @param string $note_esito Optional outcome note.
     * @return void
     */
    public static function update_status(int $id, string $status, string $note_esito = ''): void {
        global $DB;

        if (!in_array($status, self::STATI_VALIDI, true)) {
            throw new \invalid_parameter_exception("Status non valido: $status");
        }

        $target = self::get_target($id);
        if (!$target) {
            throw new \moodle_exception('errorrecordnotfound', 'local_jobmatchagent');
        }

        $update = (object)[
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
        ];

        if ($note_esito !== '') {
            $update->note_esito = $note_esito;
        }

        // Set data_invio timestamp when marking as sent.
        if ($status === 'inviata' && empty($target->data_invio)) {
            $update->data_invio = time();
        }

        // Set data_risposta timestamp when a response is received.
        if (in_array($status, ['risposta', 'colloquio', 'assunto', 'rifiutato'], true) && empty($target->data_risposta)) {
            $update->data_risposta = time();
        }

        $DB->update_record('local_jobmatch_student_targets', $update);

        // Trigger CI log when the application is marked as sent.
        if ($status === 'inviata') {
            $target->status = $status;
            $company = company_manager::get_company((int)$target->company_id);
            if ($company) {
                self::maybe_log_to_ci($target, $company);
            }
        }
    }

    /**
     * Activates or deactivates the student view for a given user.
     *
     * This writes into local_jobmatch_student_filters (creating the row if absent).
     *
     * @param int  $userid      Student user id.
     * @param bool $enabled     True to enable, false to disable.
     * @param int  $coach_userid Coach performing the action.
     * @return void
     */
    public static function set_student_view(int $userid, bool $enabled, int $coach_userid): void {
        global $DB;

        $filter = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid], '*', IGNORE_MISSING);
        $now = time();

        if ($filter) {
            $update = (object)[
                'id'                   => $filter->id,
                'student_view_enabled' => $enabled ? 1 : 0,
                'timemodified'         => $now,
            ];
            if ($enabled) {
                $update->activated_by = $coach_userid;
                $update->activated_at = $now;
            }
            $DB->update_record('local_jobmatch_student_filters', $update);
        } else {
            $record = (object)[
                'userid'               => $userid,
                'active'               => 1,
                'student_view_enabled' => $enabled ? 1 : 0,
                'updatedby'            => $coach_userid,
                'timecreated'          => $now,
                'timemodified'         => $now,
            ];
            if ($enabled) {
                $record->activated_by = $coach_userid;
                $record->activated_at = $now;
            }
            $DB->insert_record('local_jobmatch_student_filters', $record);
        }
    }

    /**
     * Returns true if the student has the student view enabled.
     *
     * @param int $userid Student user id.
     * @return bool
     */
    public static function student_view_enabled(int $userid): bool {
        global $DB;
        // Use SELECT * so the query succeeds even if student_view_enabled column
        // hasn't been added yet (e.g. upgrade pending).
        try {
            $filter = $DB->get_record('local_jobmatch_student_filters',
                ['userid' => $userid], '*', IGNORE_MISSING);
        } catch (\Throwable $e) {
            return false;
        }
        if (!$filter) {
            return false;
        }
        return !empty($filter->student_view_enabled);
    }

    /**
     * Returns activation info (activated_by, activated_at) for a student, or null if not set.
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function get_activation_info(int $userid): ?object {
        global $DB;
        $record = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid],
            'student_view_enabled, activated_by, activated_at', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Deletes a target, verifying it belongs to the given student.
     *
     * @param int $id     Target id.
     * @param int $userid Student user id (ownership check).
     * @return void
     */
    public static function delete_target(int $id, int $userid): void {
        global $DB;

        $target = $DB->get_record('local_jobmatch_student_targets',
            ['id' => $id, 'userid' => $userid], 'id', IGNORE_MISSING);
        if (!$target) {
            throw new \moodle_exception('errorrecordnotfound', 'local_jobmatchagent');
        }

        $DB->delete_records('local_jobmatch_student_targets', ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Logs the autocandidatura to CI (local_ftm_sip) if the student has an active enrollment.
     *
     * This method is intentionally defensive: any missing table, class, or DB record
     * causes a silent return so it never breaks the target status update.
     *
     * @param stdClass $target  The target record (must have userid, coach_userid, id).
     * @param stdClass $company The company record (must have nome, indirizzo, cap, localita).
     * @return void
     */
    private static function maybe_log_to_ci(object $target, object $company): void {
        global $DB;

        if (!class_exists('\\local_ftm_sip\\sip_manager')) {
            return;
        }

        // Look for an active CI enrollment for this student.
        $enrollment = $DB->get_record('local_ftm_sip_enrollments',
            ['userid' => $target->userid, 'status' => 'active'], '*', IGNORE_MISSING);
        if (!$enrollment) {
            return;
        }

        if (!$DB->get_manager()->table_exists('local_ftm_sip_search_entries')) {
            return;
        }

        // Calculate current CI week (1-10).
        $week = 1;
        if (!empty($enrollment->date_start)) {
            $weeks_elapsed = (int)floor((time() - $enrollment->date_start) / (7 * 86400));
            $week = max(1, min(10, $weeks_elapsed + 1));
        }

        // Build a clean address string.
        $address_parts = array_filter([
            $company->indirizzo ?? '',
            $company->cap       ?? '',
            $company->localita  ?? '',
        ]);
        $address = trim(implode(' ', $address_parts));

        try {
            \local_ftm_sip\sip_manager::create_search_entry($enrollment->id, 'targeted_applications', $week, [
                'company_name'    => $company->nome,
                'company_address' => $address,
                'method_letter'   => 1,
                'result'          => 'pending',
                'notes'           => 'Autocandidatura generata via JobMatch/JobAIDA',
            ], $target->coach_userid);
        } catch (\Throwable $e) {
            // Swallow silently — CI log is best-effort.
            return;
        }

        // Retrieve the last inserted search entry and store the id on the target.
        $last = $DB->get_record_sql(
            "SELECT id FROM {local_ftm_sip_search_entries}
              WHERE enrollmentid = :eid
           ORDER BY id DESC
              LIMIT 1",
            ['eid' => $enrollment->id],
            IGNORE_MISSING
        );

        if ($last) {
            $DB->set_field('local_jobmatch_student_targets', 'sip_entry_id', $last->id, ['id' => $target->id]);
        }
    }
}
