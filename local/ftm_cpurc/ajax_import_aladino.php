<?php
/**
 * AJAX endpoint for importing Aladino Excel data into student record.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_cpurc:generatereport', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $studentid = required_param('studentid', PARAM_INT);

    // Get student record.
    $student = \local_ftm_cpurc\cpurc_manager::get_student($studentid);
    if (!$student) {
        throw new \Exception('Studente non trovato');
    }

    // Handle file upload.
    if (empty($_FILES['excelfile']) || $_FILES['excelfile']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('Nessun file caricato o errore nel caricamento');
    }

    $tmpfile = $_FILES['excelfile']['tmp_name'];
    $filename = $_FILES['excelfile']['name'];

    // Validate extension.
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        throw new \Exception('Formato file non supportato. Usare .xlsx o .xls');
    }

    // Parse the Excel file.
    $data = parse_aladino_excel($tmpfile, $student);

    if ($action === 'preview') {
        // Return preview data.
        echo json_encode([
            'success' => true,
            'data' => $data,
        ]);
    } else if ($action === 'import') {
        // Apply data to student record.
        apply_aladino_data($studentid, $data);

        echo json_encode([
            'success' => true,
            'message' => 'Dati Aladino importati con successo',
            'data' => $data,
        ]);
    } else {
        throw new \Exception('Azione non valida');
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();

/**
 * Parse the Aladino Excel file and find the matching student.
 *
 * @param string $filepath Path to uploaded Excel file.
 * @param object $student Student record (with firstname, lastname, email).
 * @return array Parsed data for the student.
 * @throws Exception If student not found in file.
 */
function parse_aladino_excel($filepath, $student) {
    global $CFG;

    require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filepath);
    $sheet = $spreadsheet->getActiveSheet();

    // Row 2 = headers, Row 3+ = data.
    $headers = [];
    foreach ($sheet->getRowIterator(2, 2) as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $val = trim((string) $cell->getValue());
            if ($val !== '') {
                $headers[$cell->getColumn()] = $val;
            }
        }
    }

    if (empty($headers)) {
        throw new \Exception('File Excel non valido: headers non trovati nella riga 2');
    }

    // Column mapping: Excel header -> our field name.
    $colmap = [
        '#' => 'cpurc_id',
        'Nome' => 'firstname',
        'Cognome' => 'lastname',
        'Sesso' => 'gender',
        'Indirizzo - Titolo' => 'title',
        'Indirizzo - Via' => 'address_street',
        'Indirizzo - CAP' => 'address_cap',
        'Indirizzo - Località' => 'address_city',
        'Data di nascita' => 'birthdate',
        'Stato civile' => 'civil_status',
        'Nr. AVS' => 'avs_number',
        'Nazionalità' => 'nationality',
        'Permesso' => 'permit',
        'IBAN' => 'iban',
        'Telefono' => 'phone',
        'Cellulare' => 'mobile',
        'E-mail' => 'email',
        'Numero personale' => 'personal_number',
        'Misura' => 'measure',
        'Formatore' => 'trainer',
        'Data segnalazione' => 'signal_date',
        'Inizio' => 'date_start',
        'Fine prevista' => 'date_end_planned',
        'Fine effettiva' => 'date_end_actual',
        'Grado occ.' => 'occupation_grade',
        'Ufficio URC' => 'urc_office',
        'Consulente URC' => 'urc_consultant',
        'Stato' => 'status_text',
        'Motivo' => 'exit_reason',
        'Ditta' => 'company',
        'Osservazioni' => 'observations',
        'X' => 'absence_x',
        'O' => 'absence_o',
        'A' => 'absence_a',
        'B' => 'absence_b',
        'C' => 'absence_c',
        'D' => 'absence_d',
        'E' => 'absence_e',
        'F' => 'absence_f',
        'G' => 'absence_g',
        'H' => 'absence_h',
        'I' => 'absence_i',
        'Giorni assenza totali' => 'absence_total',
        'Colloqui di assunzione' => 'interviews',
        'Stage svolti' => 'stages_count',
        'Giorni effettivi di Stage' => 'stage_days',
        'Ultima professione' => 'last_profession',
        'Priorità' => 'priority',
        'Finanziatore' => 'financier',
        'Cassa' => 'unemployment_fund',
        'Periodo quadro - Inizio' => 'framework_start',
        'Periodo quadro - Fine' => 'framework_end',
        'Periodo quadro - Indennità' => 'framework_allowance',
        'Periodo quadro - Art. 59d' => 'framework_art59d',
        'Data inizio' => 'stage_start',
        'Data fine' => 'stage_end',
        'Responsabile' => 'stage_responsible',
        'Ditta - Nome' => 'stage_company_name',
        'Ditta - CAP' => 'stage_company_cap',
        'Ditta - Località' => 'stage_company_city',
        'Ditta - Via' => 'stage_company_street',
        'Ditta - Persona riferimento - Nome cognome' => 'stage_contact_name',
        'Ditta - Persona riferimento - Telefono' => 'stage_contact_phone',
        'Ditta - Persona riferimento - E-mail' => 'stage_contact_email',
        'Percentuale' => 'stage_percentage',
        'Funzione' => 'stage_function',
        'Conclusione - Data' => 'conclusion_date',
        'Conclusione - Tipo' => 'conclusion_type',
        'Conclusione - Motivo' => 'conclusion_reason',
    ];

    // Build reverse mapping: column letter -> field name.
    $colToField = [];
    foreach ($headers as $colLetter => $headerText) {
        if (isset($colmap[$headerText])) {
            $colToField[$colLetter] = $colmap[$headerText];
        }
    }

    // Detect interview columns (COLLOQUI D'ASSUNZIONE section).
    // These have duplicate header names ("Data", "Ditta", "Formatore"),
    // so we identify them by the category header in row 1.
    $interviewCols = [];
    foreach ($sheet->getRowIterator(1, 1) as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $val = trim((string) $cell->getValue());
            if (stripos($val, 'COLLOQUI') !== false) {
                // Found the interview section start. Map columns from here.
                $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn());
                // Map: offset 0=Data, 1=Orario inizio, 2=Orario fine, 3=Formatore, 4=Ditta, 5=Svolto.
                $interviewColMap = [
                    0 => 'interview_date',
                    4 => 'interview_company',
                ];
                foreach ($interviewColMap as $offset => $fieldName) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol + $offset);
                    $interviewCols[$colLetter] = $fieldName;
                }
                break;
            }
        }
    }

    // Search for ALL matching rows for this student.
    $studentEmail = strtolower(trim($student->email));
    $studentFirstname = strtolower(trim($student->firstname));
    $studentLastname = strtolower(trim($student->lastname));

    $matchedRows = [];
    $highestRow = $sheet->getHighestRow();

    for ($rowIdx = 3; $rowIdx <= $highestRow; $rowIdx++) {
        $rowData = [];
        foreach ($colToField as $colLetter => $fieldName) {
            $val = $sheet->getCell($colLetter . $rowIdx)->getValue();
            if ($val !== null) {
                $rowData[$fieldName] = trim((string) $val);
            }
        }

        // Also read interview columns if present.
        foreach ($interviewCols as $colLetter => $fieldName) {
            $val = $sheet->getCell($colLetter . $rowIdx)->getValue();
            if ($val !== null && trim((string) $val) !== '') {
                $rowData[$fieldName] = trim((string) $val);
            }
        }

        if (empty($rowData) || empty($rowData['firstname'])) {
            continue;
        }

        // Match by email (primary) or by firstname+lastname (fallback).
        $rowEmail = strtolower($rowData['email'] ?? '');
        $rowFirst = strtolower($rowData['firstname'] ?? '');
        $rowLast = strtolower($rowData['lastname'] ?? '');

        if (($rowEmail !== '' && $rowEmail === $studentEmail) ||
            ($rowFirst === $studentFirstname && $rowLast === $studentLastname)) {
            $matchedRows[] = $rowData;
        }
    }

    if (empty($matchedRows)) {
        throw new \Exception(
            'Studente "' . $student->firstname . ' ' . $student->lastname .
            '" (' . $student->email . ') non trovato nel file Excel'
        );
    }

    // Select the best record: Aperto > Chiuso > Interrotto (most recent date_start).
    $statusPriority = ['Aperto' => 3, 'Chiuso' => 2, 'Interrotto' => 1, 'Annullato' => 0];
    $bestRow = null;
    $bestScore = -1;

    foreach ($matchedRows as $row) {
        $status = $row['status_text'] ?? '';
        $score = $statusPriority[$status] ?? 0;

        // Parse date_start for tiebreaking.
        $dateStart = $row['date_start'] ?? '';
        $dateParts = explode('.', $dateStart);
        $dateTs = 0;
        if (count($dateParts) === 3) {
            $dateTs = mktime(0, 0, 0, (int)$dateParts[1], (int)$dateParts[0], (int)$dateParts[2]);
        }

        // Higher status priority wins; same status = more recent date wins.
        if ($score > $bestScore || ($score === $bestScore && $dateTs > ($bestRow['_date_ts'] ?? 0))) {
            $bestRow = $row;
            $bestRow['_date_ts'] = $dateTs;
            $bestScore = $score;
        }
    }

    $matchedRow = $bestRow;
    unset($matchedRow['_date_ts']);

    if (count($matchedRows) > 1) {
        $matchedRow['_info'] = count($matchedRows) . ' record trovati, selezionato: ' .
            ($matchedRow['status_text'] ?? '?') . ' dal ' . ($matchedRow['date_start'] ?? '?');
    }

    // Compose interviews detail text from interview_date + interview_company.
    $interviewDate = $matchedRow['interview_date'] ?? '';
    $interviewCompany = $matchedRow['interview_company'] ?? '';
    if ($interviewCompany !== '' || $interviewDate !== '') {
        $parts = [];
        if ($interviewCompany !== '') {
            $parts[] = $interviewCompany;
        }
        if ($interviewDate !== '') {
            $parts[] = $interviewDate;
        }
        $matchedRow['interviews_detail'] = implode(' - ', $parts);
    }

    // If no actual end date, use planned end date.
    $endActual = trim($matchedRow['date_end_actual'] ?? '');
    if ($endActual === '' || $endActual === '-') {
        $matchedRow['date_end_actual'] = $matchedRow['date_end_planned'] ?? '';
    }

    // Calculate effective participation days (supports half-days).
    $presenze_x = (float) ($matchedRow['absence_x'] ?? 0);
    $presenze_o = (float) ($matchedRow['absence_o'] ?? 0);
    $effective = $presenze_x + $presenze_o;
    // Format: show decimal only if needed (6.5 but 22 not 22.0).
    $matchedRow['effective_days'] = (floor($effective) == $effective)
        ? (string) (int) $effective
        : (string) $effective;

    return $matchedRow;
}

/**
 * Apply Aladino data to the student record.
 *
 * @param int $studentid CPURC student ID.
 * @param array $data Parsed row data.
 */
function apply_aladino_data($studentid, $data) {
    global $DB;

    $record = $DB->get_record('local_ftm_cpurc_students', ['id' => $studentid]);
    if (!$record) {
        throw new \Exception('Record studente non trovato');
    }

    // Date fields that need dd.mm.yyyy -> timestamp conversion.
    $dateFields = [
        'birthdate', 'signal_date', 'date_start', 'date_end_planned', 'date_end_actual',
        'framework_start', 'framework_end', 'stage_start', 'stage_end', 'conclusion_date',
    ];

    // Numeric fields that support decimals (half-days).
    $decimalFields = [
        'absence_x', 'absence_o',
        'absence_a', 'absence_b', 'absence_c', 'absence_d', 'absence_e',
        'absence_f', 'absence_g', 'absence_h', 'absence_i', 'absence_total',
    ];

    // Integer fields.
    $intFields = [
        'occupation_grade', 'interviews', 'stages_count', 'stage_days',
        'framework_allowance', 'stage_percentage',
    ];

    // Fields to skip (not DB columns or read-only).
    $skipFields = ['firstname', 'lastname', 'email', 'effective_days', 'status_text', '_info',
        'interview_date', 'interview_company', 'interviews_detail'];

    foreach ($data as $field => $value) {
        if (in_array($field, $skipFields)) {
            continue;
        }

        if ($value === '' || $value === null) {
            continue;
        }

        // Handle status_text -> status mapping.
        if ($field === 'status_text') {
            continue; // Handled separately below.
        }

        if (in_array($field, $dateFields)) {
            // Parse dd.mm.yyyy to timestamp.
            $ts = parse_aladino_date($value);
            if ($ts !== false) {
                $record->$field = $ts;
            }
        } else if (in_array($field, $decimalFields)) {
            // Decimal fields (half-days: 6.5, 15.5, etc.).
            $record->$field = (float) $value;
        } else if (in_array($field, $intFields)) {
            $record->$field = (int) $value;
        } else {
            // String fields - only update if we have a value.
            if (property_exists($record, $field)) {
                $record->$field = (string) $value;
            }
        }
    }

    // Map status text.
    if (!empty($data['status_text'])) {
        $statusMap = [
            'Aperto' => 'active',
            'Chiuso' => 'closed',
            'Interrotto' => 'closed',
            'Annullato' => 'closed',
        ];
        $statusText = $data['status_text'];
        if (isset($statusMap[$statusText])) {
            $record->status = $statusMap[$statusText];
        }
    }

    $record->timemodified = time();
    $DB->update_record('local_ftm_cpurc_students', $record);

    // Also update interviews detail in the report (section 5.1) if present.
    if (!empty($data['interviews_detail'])) {
        $report = $DB->get_record('local_ftm_cpurc_reports', ['studentid' => $studentid]);
        if ($report) {
            $report->interviews_employers = $data['interviews_detail'];
            $report->interviews_count = (int) ($data['interviews'] ?? $report->interviews_count ?? 0);
            $report->timemodified = time();
            $DB->update_record('local_ftm_cpurc_reports', $report);
        }
    }
}

/**
 * Parse a date in dd.mm.yyyy format to timestamp.
 *
 * @param string $datestr Date string.
 * @return int|false Timestamp or false on failure.
 */
function parse_aladino_date($datestr) {
    $datestr = trim((string) $datestr);
    if (empty($datestr)) {
        return false;
    }

    // Try dd.mm.yyyy format.
    $parts = explode('.', $datestr);
    if (count($parts) === 3) {
        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];
        if ($day > 0 && $month > 0 && $year > 0 && checkdate($month, $day, $year)) {
            return mktime(0, 0, 0, $month, $day, $year);
        }
    }

    return false;
}
