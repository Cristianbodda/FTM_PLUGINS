<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manager for the Ticino company database.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages CRUD operations, import, and statistics for local_jobmatch_ticino_companies.
 */
class company_manager {

    /** Valid FTM sector values. */
    const SETTORI_FTM = [
        'AUTOMOBILE',
        'AUTOMAZIONE',
        'CHIMFARM',
        'ELETTRICITA',
        'LOGISTICA',
        'MECCANICA',
        'METALCOSTRUZIONE',
        'ALTRO',
    ];

    /** Valid status values. */
    const STATI_VALIDI = ['active', 'inactive', 'unverified'];

    /** Valid dimensione values. */
    const DIMENSIONI_VALIDE = ['S', 'M', 'L', 'unknown'];

    /**
     * Returns a list of companies with optional filters.
     *
     * Supported filter keys:
     *   - settore_ftm  (string)  exact match on settore_ftm
     *   - status       (string)  exact match on status
     *   - localita     (string)  exact match on localita
     *   - search       (string)  partial match on nome (LIKE)
     *
     * @param array $filters  Optional filter conditions.
     * @param int   $limit    Max rows to return (0 = no limit).
     * @param int   $offset   Rows to skip for pagination.
     * @return array Array of stdClass records.
     */
    public static function get_companies(array $filters = [], int $limit = 100, int $offset = 0): array {
        global $DB;

        [$where, $params] = self::build_where($filters);
        $sql = "SELECT * FROM {local_jobmatch_ticino_companies}" . $where . " ORDER BY nome ASC";

        if ($limit > 0) {
            return array_values($DB->get_records_sql($sql, $params, $offset, $limit));
        }
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Returns the total count of companies matching the given filters.
     *
     * @param array $filters Optional filter conditions (same keys as get_companies).
     * @return int
     */
    public static function count_companies(array $filters = []): int {
        global $DB;

        [$where, $params] = self::build_where($filters);
        $sql = "SELECT COUNT(*) FROM {local_jobmatch_ticino_companies}" . $where;
        return (int)$DB->count_records_sql($sql, $params);
    }

    /**
     * Returns a single company by id, or null if not found.
     *
     * @param int $id
     * @return stdClass|null
     */
    public static function get_company(int $id): ?object {
        global $DB;
        $record = $DB->get_record('local_jobmatch_ticino_companies', ['id' => $id], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Creates or updates a company record.
     *
     * If $data contains an 'id' key with a positive value, the existing record is
     * updated; otherwise a new record is inserted.
     *
     * @param array $data Associative array of field values.
     * @return int The id of the saved record.
     */
    public static function save_company(array $data): int {
        global $DB;

        $now = time();

        // Normalise and sanitise.
        if (isset($data['settore_ftm']) && !in_array($data['settore_ftm'], self::SETTORI_FTM, true)) {
            $data['settore_ftm'] = 'ALTRO';
        }
        if (isset($data['dimensione']) && !in_array($data['dimensione'], self::DIMENSIONI_VALIDE, true)) {
            $data['dimensione'] = 'unknown';
        }
        if (isset($data['status']) && !in_array($data['status'], self::STATI_VALIDI, true)) {
            $data['status'] = 'unverified';
        }

        if (!empty($data['id'])) {
            $record = (object)$data;
            $record->timemodified = $now;
            $DB->update_record('local_jobmatch_ticino_companies', $record);
            return (int)$record->id;
        }

        $record = (object)$data;
        $record->timecreated = $now;
        $record->timemodified = $now;
        if (empty($record->settore_ftm)) {
            $record->settore_ftm = 'ALTRO';
        }
        if (empty($record->dimensione)) {
            $record->dimensione = 'unknown';
        }
        if (empty($record->source)) {
            $record->source = 'manual';
        }
        if (empty($record->status)) {
            $record->status = 'unverified';
        }
        return (int)$DB->insert_record('local_jobmatch_ticino_companies', $record);
    }

    /**
     * Changes the status of a company.
     *
     * @param int    $id     Company id.
     * @param string $status One of: active, inactive, unverified.
     * @return void
     */
    public static function set_status(int $id, string $status): void {
        global $DB;
        if (!in_array($status, self::STATI_VALIDI, true)) {
            throw new \invalid_parameter_exception("Status non valido: $status");
        }
        $DB->set_field('local_jobmatch_ticino_companies', 'status', $status, ['id' => $id]);
        $DB->set_field('local_jobmatch_ticino_companies', 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Imports companies from a CSV string.
     *
     * Expected format (semicolon-separated, with header row):
     *   n;anno;nome;settore_ftm;confidence;indirizzo;localita
     *
     * Rows with an empty nome are skipped. Existing records (matched by nome,
     * case-insensitive) are skipped (no update) to avoid overwriting manual edits.
     *
     * @param string $csv_content Raw CSV content (UTF-8, may include BOM).
     * @return array ['inserted' => int, 'skipped' => int, 'errors' => string[]]
     */
    public static function import_from_csv(string $csv_content): array {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => []];

        // Strip UTF-8 BOM if present.
        $csv_content = ltrim($csv_content, "\xEF\xBB\xBF");

        $lines = preg_split('/\r\n|\r|\n/', trim($csv_content));
        if (empty($lines)) {
            $result['errors'][] = 'File CSV vuoto.';
            return $result;
        }

        // Detect and skip header row.
        $firstline = strtolower(trim($lines[0]));
        $startindex = 0;
        if (strpos($firstline, 'nome') !== false || strpos($firstline, 'settore') !== false) {
            $startindex = 1;
        }

        foreach ($lines as $lineno => $rawline) {
            if ($lineno < $startindex) {
                continue;
            }
            $line = trim($rawline);
            if ($line === '') {
                continue;
            }
            $cols = explode(';', $line);

            // n ; anno ; nome ; settore_ftm ; confidence ; indirizzo ; localita
            $nome = isset($cols[2]) ? trim($cols[2]) : '';
            if ($nome === '') {
                $result['skipped']++;
                continue;
            }

            // Dedup: skip if a record with the same nome already exists.
            if (self::find_by_name($nome) !== null) {
                $result['skipped']++;
                continue;
            }

            $anno_raw = isset($cols[1]) ? trim($cols[1]) : '';
            $settore_raw = isset($cols[3]) ? trim($cols[3]) : '';
            $indirizzo = isset($cols[5]) ? trim($cols[5]) : '';
            $localita  = isset($cols[6]) ? trim($cols[6]) : '';

            // Normalise settore.
            $settore_ftm = self::normalise_settore($settore_raw);

            $data = [
                'nome'               => $nome,
                'anno_primo_contatto'=> ($anno_raw !== '' && ctype_digit($anno_raw)) ? (int)$anno_raw : null,
                'settore_ftm'        => $settore_ftm,
                'settore_raw'        => $settore_raw,
                'indirizzo'          => $indirizzo ?: null,
                'localita'           => $localita ?: null,
                'source'             => 'csv_import',
                'status'             => 'unverified',
                'dimensione'         => 'unknown',
            ];

            try {
                self::save_company($data);
                $result['inserted']++;
            } catch (\Exception $e) {
                $result['errors'][] = "Riga " . ($lineno + 1) . " ($nome): " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Finds a company by exact nome (case-insensitive).
     *
     * @param string $nome Company name.
     * @return stdClass|null The first matching record, or null.
     */
    public static function find_by_name(string $nome): ?object {
        global $DB;
        $sql = "SELECT * FROM {local_jobmatch_ticino_companies} WHERE " . $DB->sql_compare_text('nome') . " = :nome LIMIT 1";
        $record = $DB->get_record_sql($sql, ['nome' => trim($nome)], IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Returns the count of companies grouped by settore_ftm.
     *
     * @return array Associative array ['MECCANICA' => 80, ...]
     */
    public static function get_stats_by_sector(): array {
        global $DB;
        $sql = "SELECT settore_ftm, COUNT(*) AS cnt
                FROM {local_jobmatch_ticino_companies}
                GROUP BY settore_ftm
                ORDER BY cnt DESC";
        $rows = $DB->get_records_sql($sql);
        $stats = [];
        foreach ($rows as $row) {
            $stats[$row->settore_ftm] = (int)$row->cnt;
        }
        return $stats;
    }

    /**
     * Updates last_job_board_seen timestamp for a company matched by nome.
     * Optionally narrows the match by localita. Called by jobsearch integration.
     *
     * @param string $nome    Company name as it appears on the job board.
     * @param string $localita Optional locality to help disambiguation.
     * @return void
     */
    public static function update_job_board_seen(string $nome, string $localita = ''): void {
        global $DB;

        $now = time();
        $nome = trim($nome);
        if ($nome === '') {
            return;
        }

        // Try to match by nome (and localita if provided).
        $sql = "SELECT id FROM {local_jobmatch_ticino_companies}
                WHERE " . $DB->sql_compare_text('nome') . " = :nome";
        $params = ['nome' => $nome];

        if ($localita !== '') {
            $sql .= " AND " . $DB->sql_compare_text('localita') . " = :localita";
            $params['localita'] = trim($localita);
        }

        $sql .= " LIMIT 1";
        $record = $DB->get_record_sql($sql, $params, IGNORE_MISSING);
        if (!$record) {
            return;
        }

        $DB->set_field('local_jobmatch_ticino_companies', 'last_job_board_seen', $now, ['id' => $record->id]);
        $DB->set_field('local_jobmatch_ticino_companies', 'timemodified', $now, ['id' => $record->id]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Builds the WHERE clause and param array from a filters array.
     *
     * @param array $filters
     * @return array [string $where_clause, array $params]
     */
    private static function build_where(array $filters): array {
        $conditions = [];
        $params = [];

        if (!empty($filters['settore_ftm'])) {
            $conditions[] = 'settore_ftm = :settore_ftm';
            $params['settore_ftm'] = $filters['settore_ftm'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['localita'])) {
            $conditions[] = 'localita = :localita';
            $params['localita'] = $filters['localita'];
        }
        if (!empty($filters['search'])) {
            global $DB;
            $conditions[] = $DB->sql_like('nome', ':search', false, false);
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (empty($conditions)) {
            return ['', []];
        }
        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * Maps a raw sector string to a canonical FTM sector or 'ALTRO'.
     *
     * @param string $raw
     * @return string
     */
    private static function normalise_settore(string $raw): string {
        $map = [
            'automobile'       => 'AUTOMOBILE',
            'autoveicolo'      => 'AUTOMOBILE',
            'auto'             => 'AUTOMOBILE',
            'automazione'      => 'AUTOMAZIONE',
            'autom'            => 'AUTOMAZIONE',
            'automaz'          => 'AUTOMAZIONE',
            'chimfarm'         => 'CHIMFARM',
            'chim'             => 'CHIMFARM',
            'chimica'          => 'CHIMFARM',
            'farmaceutica'     => 'CHIMFARM',
            'elettricita'      => 'ELETTRICITA',
            'elettricità'      => 'ELETTRICITA',
            'elettr'           => 'ELETTRICITA',
            'elett'            => 'ELETTRICITA',
            'logistica'        => 'LOGISTICA',
            'log'              => 'LOGISTICA',
            'meccanica'        => 'MECCANICA',
            'mecc'             => 'MECCANICA',
            'metalcostruzione' => 'METALCOSTRUZIONE',
            'metal'            => 'METALCOSTRUZIONE',
        ];

        $key = strtolower(trim($raw));
        return $map[$key] ?? 'ALTRO';
    }
}
