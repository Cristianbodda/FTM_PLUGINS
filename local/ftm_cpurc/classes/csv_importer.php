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
 * CSV importer for CPURC data.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses and imports CPURC CSV files.
 */
class csv_importer {

    /**
     * Column mapping: CSV column index => field name.
     */
    const COLUMN_MAP = [
        0 => 'cpurc_id',           // #
        1 => 'firstname',          // Nome
        2 => 'lastname',           // Cognome
        3 => 'gender',             // Sesso
        4 => 'title',              // Indirizzo - Titolo
        5 => 'address_street',     // Indirizzo - Via
        6 => 'address_cap',        // Indirizzo - CAP
        7 => 'address_city',       // Indirizzo - Localita
        8 => 'birthdate',          // Data di nascita
        9 => 'civil_status',       // Stato civile
        10 => 'avs_number',        // Nr. AVS
        11 => 'nationality',       // Nazionalita
        12 => 'permit',            // Permesso
        13 => 'iban',              // IBAN
        14 => 'phone',             // Telefono
        15 => 'mobile',            // Cellulare
        16 => 'email',             // E-mail
        17 => 'personal_number',   // Numero personale
        18 => 'measure',           // Misura
        19 => 'trainer',           // Formatore
        20 => 'signal_date',       // Data segnalazione
        21 => 'date_start',        // Inizio
        22 => 'date_end_planned',  // Fine prevista
        23 => 'date_end_actual',   // Fine effettiva
        24 => 'occupation_grade',  // Grado occ.
        25 => 'urc_office',        // Ufficio URC
        26 => 'urc_consultant',    // Consulente URC
        27 => 'status',            // Stato
        28 => 'exit_reason',       // Motivo
        29 => 'company',           // Ditta
        30 => 'observations',      // Osservazioni
        31 => 'absence_x',         // X
        32 => 'absence_o',         // O
        33 => 'absence_a',         // A
        34 => 'absence_b',         // B
        35 => 'absence_c',         // C
        36 => 'absence_d',         // D
        37 => 'absence_e',         // E
        38 => 'absence_f',         // F
        39 => 'absence_g',         // G
        40 => 'absence_h',         // H
        41 => 'absence_i',         // I
        42 => 'absence_total',     // Giorni assenza totali
        43 => 'interviews',        // Colloqui di assunzione
        44 => 'stages_count',      // Stage svolti
        45 => 'stage_days',        // Giorni effettivi di Stage
        46 => 'last_profession',   // Ultima professione
        47 => 'priority',          // Priorita
        48 => 'financier',         // Finanziatore
        49 => 'unemployment_fund', // Cassa
        50 => 'framework_start',   // Periodo quadro - Inizio
        51 => 'framework_end',     // Periodo quadro - Fine
        52 => 'framework_allowance', // Periodo quadro - Indennita
        53 => 'framework_art59d',  // Periodo quadro - Art. 59d
        54 => 'stage_start',       // Data inizio (stage)
        55 => 'stage_end',         // Data fine (stage)
        56 => 'stage_responsible', // Responsabile
        57 => 'stage_company_name', // Ditta - Nome
        58 => 'stage_company_cap', // Ditta - CAP
        59 => 'stage_company_city', // Ditta - Localita
        60 => 'stage_company_street', // Ditta - Via
        61 => 'stage_contact_name', // Ditta - Persona riferimento - Nome cognome
        62 => 'stage_contact_phone', // Ditta - Persona riferimento - Telefono
        63 => 'stage_contact_email', // Ditta - Persona riferimento - E-mail
        64 => 'stage_percentage',  // Percentuale
        65 => 'stage_function',    // Funzione
        66 => 'conclusion_date',   // Conclusione - Data
        67 => 'conclusion_type',   // Conclusione - Tipo
        68 => 'conclusion_reason', // Conclusione - Motivo
    ];

    /**
     * Date fields that need parsing.
     */
    const DATE_FIELDS = [
        'birthdate',
        'signal_date',
        'date_start',
        'date_end_planned',
        'date_end_actual',
        'framework_start',
        'framework_end',
        'stage_start',
        'stage_end',
        'conclusion_date',
    ];

    /**
     * Integer fields.
     */
    const INT_FIELDS = [
        'occupation_grade',
        'absence_x',
        'absence_o',
        'absence_a',
        'absence_b',
        'absence_c',
        'absence_d',
        'absence_e',
        'absence_f',
        'absence_g',
        'absence_h',
        'absence_i',
        'absence_total',
        'interviews',
        'stages_count',
        'stage_days',
        'framework_allowance',
        'stage_percentage',
    ];

    /**
     * Required fields for validation.
     */
    const REQUIRED_FIELDS = ['firstname', 'lastname', 'email'];

    /** @var array Import statistics */
    private $stats = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'errors' => 0,
    ];

    /** @var array Error details */
    private $errors = [];

    /** @var string Batch ID */
    private $batchid;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->batchid = uniqid('cpurc_', true);
    }

    /**
     * Parse a file (CSV or Excel) and return rows.
     * Detects format from the original filename extension.
     *
     * @param string $filepath Path to uploaded temp file.
     * @param string $originalname Original filename for extension detection.
     * @return array Array of parsed rows.
     */
    public function parse_file($filepath, $originalname = '') {
        $ext = strtolower(pathinfo($originalname, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'])) {
            return $this->parse_excel_file($filepath);
        }
        return $this->parse_csv_file($filepath);
    }

    /**
     * Parse Excel file (.xlsx/.xls) and return rows.
     *
     * @param string $filepath Path to Excel file.
     * @return array Array of parsed rows.
     */
    public function parse_excel_file($filepath) {
        $rows = [];

        if (!file_exists($filepath)) {
            $this->errors[] = ['row' => 0, 'error' => 'File not found: ' . $filepath];
            return $rows;
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
            $spreadsheet = $reader->load($filepath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Identify date column indices (0-based) from COLUMN_MAP.
            $dateindices = [];
            foreach (self::COLUMN_MAP as $idx => $fieldname) {
                if (in_array($fieldname, self::DATE_FIELDS)) {
                    $dateindices[] = $idx;
                }
            }

            $linenum = 0;
            foreach ($worksheet->getRowIterator() as $wsrow) {
                $linenum++;

                // Skip header rows (first 2 lines: categories + column names).
                if ($linenum <= 2) {
                    continue;
                }

                $cellIterator = $wsrow->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $fields = [];
                $colidx = 0;
                foreach ($cellIterator as $cell) {
                    $val = $cell->getValue();
                    // Handle Excel date serial numbers.
                    if (in_array($colidx, $dateindices) && is_numeric($val) && $val > 10000) {
                        try {
                            $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                            $val = $dateObj->format('d.m.Y');
                        } catch (\Exception $e) {
                            // Keep original value.
                        }
                    }
                    $fields[] = $val !== null ? (string)$val : '';
                    $colidx++;
                }

                // Skip empty rows.
                $nonEmpty = array_filter($fields, function($v) { return trim($v) !== ''; });
                if (empty($nonEmpty)) {
                    continue;
                }

                // Skip if not enough columns.
                if (count($fields) < 17) {
                    continue;
                }

                $row = $this->map_row($fields);
                $row['_line'] = $linenum;
                $rows[] = $row;
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

        } catch (\Exception $e) {
            $this->errors[] = ['row' => 0, 'error' => 'Errore lettura Excel: ' . $e->getMessage()];
        }

        $this->stats['total'] = count($rows);
        return $rows;
    }

    /**
     * Parse CSV file and return rows.
     *
     * @param string $filepath Path to CSV file.
     * @return array Array of parsed rows.
     */
    public function parse_csv_file($filepath) {
        $rows = [];

        if (!file_exists($filepath)) {
            $this->errors[] = ['row' => 0, 'error' => 'File not found: ' . $filepath];
            return $rows;
        }

        // Read file with proper encoding.
        $content = file_get_contents($filepath);

        // Handle BOM.
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Convert encoding if needed.
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        // Split into lines.
        $lines = explode("\n", $content);

        $linenum = 0;
        foreach ($lines as $line) {
            $linenum++;

            // Skip empty lines.
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip header rows (first 2 lines).
            if ($linenum <= 2) {
                continue;
            }

            // Parse CSV with semicolon separator.
            $fields = str_getcsv($line, ';', '"');

            // Skip if not enough columns.
            if (count($fields) < 17) {
                continue;
            }

            $row = $this->map_row($fields);
            $row['_line'] = $linenum;
            $rows[] = $row;
        }

        $this->stats['total'] = count($rows);
        return $rows;
    }

    /**
     * Map CSV fields to named array.
     *
     * @param array $fields Raw CSV fields.
     * @return array Mapped row.
     */
    private function map_row($fields) {
        $row = [];

        foreach (self::COLUMN_MAP as $index => $fieldname) {
            $value = isset($fields[$index]) ? trim($fields[$index]) : '';

            // Handle date fields.
            if (in_array($fieldname, self::DATE_FIELDS)) {
                $value = local_ftm_cpurc_parse_date($value);
            }
            // Handle integer fields.
            else if (in_array($fieldname, self::INT_FIELDS)) {
                $value = (int)$value;
            }

            $row[$fieldname] = $value;
        }

        return $row;
    }

    /**
     * Validate a row.
     *
     * @param array $row Row data.
     * @return array Array of validation errors (empty if valid).
     */
    public function validate_row($row) {
        $errors = [];

        // Check required fields.
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($row[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate email format.
        if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format: {$row['email']}";
        }

        return $errors;
    }

    /**
     * Import a single row.
     *
     * @param array $row Row data.
     * @param array $options Import options.
     * @return array Result with success flag and message.
     */
    public function import_row($row, $options = []) {
        global $DB;

        $result = [
            'success' => false,
            'message' => '',
            'userid' => 0,
            'created' => false,
            'updated' => false,
        ];

        // Validate row.
        $errors = $this->validate_row($row);
        if (!empty($errors)) {
            $result['message'] = implode('; ', $errors);
            $this->errors[] = ['row' => $row['_line'] ?? 0, 'error' => $result['message']];
            $this->stats['errors']++;
            return $result;
        }

        try {
            // Create or find user.
            $userresult = user_manager::create_or_find_user([
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'mobile' => $row['mobile'],
            ], $options['update_existing'] ?? false);

            $result['userid'] = $userresult->userid;
            $result['created'] = $userresult->created;
            $result['updated'] = $userresult->updated;

            if (isset($userresult->username)) {
                $result['username'] = $userresult->username;
            }
            if (isset($userresult->password_plain)) {
                $result['password'] = $userresult->password_plain;
            }

            // Enroll in course if requested.
            if (!empty($options['enrol_course']) && !empty($options['courseid'])) {
                user_manager::enrol_in_course($userresult->userid, $options['courseid']);
            }

            // Add to cohort if requested.
            if (!empty($options['assign_cohort']) && !empty($row['urc_office'])) {
                user_manager::add_to_cohort($userresult->userid, $row['urc_office']);
            }

            // Add to color group if requested.
            if (!empty($options['assign_group']) && !empty($row['date_start'])) {
                user_manager::add_to_color_group($userresult->userid, $row['date_start']);
            }

            // Detect sector from profession.
            $sector = profession_mapper::detect_sector($row['last_profession']);
            $row['sector_detected'] = $sector;

            // Sync with sector_manager if available.
            if ($sector && !empty($options['courseid'])) {
                user_manager::sync_sector($userresult->userid, $sector, $options['courseid']);
            }

            // Assign coach from trainer field.
            if (!empty($row['trainer']) && !empty($options['courseid'])) {
                $coachid = self::find_coach_by_trainer($row['trainer']);
                if ($coachid) {
                    $datestart = !empty($row['date_start']) ? (int)$row['date_start'] : 0;
                    cpurc_manager::assign_coach($userresult->userid, $coachid, $options['courseid'], $datestart);
                }
            }

            // Save extended CPURC data.
            $this->save_cpurc_student($userresult->userid, $row);

            $result['success'] = true;
            $result['message'] = $userresult->created ? 'User created' : ($userresult->updated ? 'User updated' : 'User found');

            if ($userresult->created) {
                $this->stats['imported']++;
            } else if ($userresult->updated) {
                $this->stats['updated']++;
            }

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $this->errors[] = ['row' => $row['_line'] ?? 0, 'error' => $e->getMessage()];
            $this->stats['errors']++;
        }

        return $result;
    }

    /**
     * Save CPURC student extended data.
     *
     * @param int $userid User ID.
     * @param array $row Row data.
     * @return int Record ID.
     */
    private function save_cpurc_student($userid, $row) {
        global $DB;

        // Check if record exists.
        $existing = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);

        $record = new \stdClass();
        $record->userid = $userid;

        // Map all fields.
        $fields = [
            'cpurc_id', 'gender', 'title', 'address_street', 'address_cap', 'address_city',
            'birthdate', 'civil_status', 'avs_number', 'nationality', 'permit', 'iban',
            'phone', 'mobile', 'personal_number', 'measure', 'trainer', 'signal_date',
            'date_start', 'date_end_planned', 'date_end_actual', 'occupation_grade',
            'urc_office', 'urc_consultant', 'status', 'exit_reason', 'company', 'observations',
            'absence_x', 'absence_o', 'absence_a', 'absence_b', 'absence_c', 'absence_d',
            'absence_e', 'absence_f', 'absence_g', 'absence_h', 'absence_i', 'absence_total',
            'interviews', 'stages_count', 'stage_days', 'last_profession', 'priority',
            'financier', 'unemployment_fund', 'framework_start', 'framework_end',
            'framework_allowance', 'framework_art59d', 'stage_start', 'stage_end',
            'stage_responsible', 'stage_company_name', 'stage_company_cap', 'stage_company_city',
            'stage_company_street', 'stage_contact_name', 'stage_contact_phone',
            'stage_contact_email', 'stage_percentage', 'stage_function', 'conclusion_date',
            'conclusion_type', 'conclusion_reason', 'sector_detected',
        ];

        foreach ($fields as $field) {
            if (isset($row[$field])) {
                $record->$field = $row[$field];
            }
        }

        $record->import_batch = $this->batchid;
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_ftm_cpurc_students', $record);
            return $existing->id;
        } else {
            $record->timecreated = time();
            return $DB->insert_record('local_ftm_cpurc_students', $record);
        }
    }

    /**
     * Get import statistics.
     *
     * @return array Statistics.
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Get errors.
     *
     * @return array Error details.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get batch ID.
     *
     * @return string Batch ID.
     */
    public function get_batch_id() {
        return $this->batchid;
    }

    /**
     * Log import to database.
     *
     * @param string $filename Original filename.
     * @return int Import log ID.
     */
    public function log_import($filename = '') {
        global $DB, $USER;

        $log = new \stdClass();
        $log->batch_id = $this->batchid;
        $log->filename = $filename;
        $log->total_rows = $this->stats['total'];
        $log->imported_count = $this->stats['imported'];
        $log->updated_count = $this->stats['updated'];
        $log->error_count = $this->stats['errors'];
        $log->errors_json = json_encode($this->errors);
        $log->importedby = $USER->id;
        $log->timecreated = time();

        return $DB->insert_record('local_ftm_cpurc_imports', $log);
    }

    /**
     * Find coach user ID from trainer name in CSV.
     * Matches against local_ftm_coaches table first, then fallback to user table.
     *
     * @param string $trainername Trainer name from CSV (e.g. "Cristian Bodda").
     * @return int|null Coach user ID or null if not found.
     */
    private static function find_coach_by_trainer($trainername) {
        global $DB;

        static $coachcache = null;

        // Build cache on first call.
        if ($coachcache === null) {
            $coachcache = [];

            // Try local_ftm_coaches table first.
            if ($DB->get_manager()->table_exists('local_ftm_coaches')) {
                $coaches = $DB->get_records('local_ftm_coaches');
                foreach ($coaches as $coach) {
                    $fullname = strtolower(trim($coach->firstname . ' ' . $coach->lastname));
                    $coachcache[$fullname] = $coach->userid;
                    // Also cache by last name only for partial match.
                    $coachcache['_ln_' . strtolower(trim($coach->lastname))] = $coach->userid;
                }
            }

            // Fallback: known coach names from user table.
            if (empty($coachcache)) {
                $knowncoaches = ['cristian bodda', 'fabio marinoni', 'graziano margonar', 'roberto bravo'];
                foreach ($knowncoaches as $name) {
                    $parts = explode(' ', $name);
                    $coach = $DB->get_record_select('user',
                        'LOWER(firstname) = :fn AND LOWER(lastname) = :ln AND deleted = 0',
                        ['fn' => $parts[0], 'ln' => $parts[1]],
                        'id',
                        IGNORE_MULTIPLE
                    );
                    if ($coach) {
                        $coachcache[$name] = $coach->id;
                        $coachcache['_ln_' . $parts[1]] = $coach->id;
                    }
                }
            }
        }

        $trainer = strtolower(trim($trainername));
        if (empty($trainer)) {
            return null;
        }

        // Full name match.
        foreach ($coachcache as $key => $uid) {
            if (strpos($key, '_ln_') === 0) {
                continue; // Skip lastname-only entries.
            }
            if (strpos($trainer, $key) !== false || strpos($key, $trainer) !== false) {
                return $uid;
            }
        }

        // Partial match on last name.
        foreach ($coachcache as $key => $uid) {
            if (strpos($key, '_ln_') !== 0) {
                continue;
            }
            $lastname = substr($key, 4);
            if (strpos($trainer, $lastname) !== false) {
                return $uid;
            }
        }

        return null;
    }

    /**
     * Get preview of first N rows.
     *
     * @param string $filepath Path to uploaded file.
     * @param int $limit Number of rows to preview.
     * @param string $originalname Original filename for format detection.
     * @return array Preview data.
     */
    public function preview_file($filepath, $limit = 5, $originalname = '') {
        $rows = $this->parse_file($filepath, $originalname);
        return array_slice($rows, 0, $limit);
    }
}
