<?php
/**
 * Excel Quiz Importer - Import multiple quizzes from Excel export files
 *
 * Supports .xlsx files with the following column structure:
 * A: Quiz name, B: #, C: Question, D-G: Answers A-D, H: Correct, I: Sector, J: Competency Code, K: Description, L: Difficulty
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Class excel_quiz_importer
 *
 * Imports multiple quiz questions with competencies and difficulty levels from Excel files.
 * Groups questions by Quiz name (column A) and creates separate quizzes for each.
 */
class excel_quiz_importer {

    /** @var array Column mapping for the actual format (0-indexed) */
    private const COLUMN_MAP = [
        'quiz_name' => 0,        // A: Quiz name
        'number' => 1,           // B: #
        'question' => 2,         // C: Question text
        'answer_a' => 3,         // D: Answer A
        'answer_b' => 4,         // E: Answer B
        'answer_c' => 5,         // F: Answer C
        'answer_d' => 6,         // G: Answer D
        'correct' => 7,          // H: Correct answer (A/B/C/D)
        'sector' => 8,           // I: Sector
        'competency_code' => 9,  // J: Competency code
        'competency_desc' => 10, // K: Competency description
        'difficulty' => 11       // L: Difficulty level (1-3)
    ];

    /** @var array Parsed quizzes with their questions */
    private $quizzes = [];

    /** @var array Validation errors */
    private $errors = [];

    /** @var array Validation warnings */
    private $warnings = [];

    /** @var string Original filename */
    private $filename = '';

    /** @var int Framework ID for competency validation */
    private $frameworkid = 0;

    /** @var string Sector for competency validation */
    private $sector = '';

    /** @var array Competency lookup table */
    private $competency_lookup = [];

    /** @var int Total questions count */
    private $total_questions = 0;

    /**
     * Constructor
     *
     * @param int $frameworkid Framework ID for validation
     * @param string $sector Sector prefix for validation
     */
    public function __construct($frameworkid = 0, $sector = '') {
        $this->frameworkid = $frameworkid;
        $this->sector = $sector;

        if ($frameworkid > 0) {
            $this->load_competency_lookup();
        }
    }

    /**
     * Load competency lookup table from database
     */
    private function load_competency_lookup() {
        global $DB;

        $competencies = $DB->get_records_sql("
            SELECT id, idnumber, shortname, description
            FROM {competency}
            WHERE competencyframeworkid = ?
            ORDER BY idnumber
        ", [$this->frameworkid]);

        foreach ($competencies as $c) {
            $key = mb_strtoupper(trim($c->idnumber), 'UTF-8');
            $this->competency_lookup[$key] = $c;
        }
    }

    /**
     * Load and parse Excel file
     *
     * @param string $filepath Path to Excel file
     * @return bool Success status
     */
    public function load_file($filepath) {
        global $CFG;

        $this->quizzes = [];
        $this->errors = [];
        $this->warnings = [];
        $this->total_questions = 0;
        $this->filename = basename($filepath);

        if (!file_exists($filepath)) {
            $this->errors[] = 'File non trovato: ' . $filepath;
            return false;
        }

        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        // Only xlsx is supported
        if ($ext !== 'xlsx') {
            $this->errors[] = 'Formato non supportato: .' . $ext . '. Usa il formato .xlsx (Salva con nome in Excel).';
            return false;
        }

        try {
            // Use Xlsx reader directly
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filepath);
            $sheet = $spreadsheet->getActiveSheet();

            return $this->parse_sheet($sheet);

        } catch (\Exception $e) {
            $this->errors[] = 'Errore lettura Excel: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Parse worksheet data and group by quiz name
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @return bool Success status
     */
    private function parse_sheet($sheet) {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Verify we have enough columns (need at least L = 12 columns)
        $colCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        if ($colCount < 10) {
            $this->errors[] = 'Il file Excel deve avere almeno 10 colonne (A-J). Trovate: ' . $colCount;
            return false;
        }

        // Check header row
        $headerA = $this->get_cell_value($sheet, 0, 1);
        if (stripos($headerA, 'quiz') === false && stripos($headerA, 'test') === false) {
            // First row might be data, not header - check if it looks like a quiz name
            if (strpos($headerA, 'MECCANICA') !== false || strpos($headerA, 'AUTOMOBILE') !== false) {
                // Data starts at row 1, no header
                $startRow = 1;
            } else {
                $startRow = 2; // Skip header row
            }
        } else {
            $startRow = 2; // Skip header row
        }

        // Parse all rows and group by quiz name
        for ($row = $startRow; $row <= $highestRow; $row++) {
            $rowData = $this->parse_row($sheet, $row);

            if ($rowData !== null) {
                $quizName = $rowData['quiz_name'];

                if (!isset($this->quizzes[$quizName])) {
                    $this->quizzes[$quizName] = [
                        'name' => $quizName,
                        'short_name' => $this->extract_short_name($quizName),
                        'questions' => [],
                        'sector' => $rowData['sector'],
                        'valid_competencies' => 0,
                        'invalid_competencies' => 0,
                        'difficulty_distribution' => [1 => 0, 2 => 0, 3 => 0]
                    ];
                }

                $this->quizzes[$quizName]['questions'][] = $rowData;
                $this->quizzes[$quizName]['difficulty_distribution'][$rowData['difficulty']]++;

                if ($rowData['competency_valid']) {
                    $this->quizzes[$quizName]['valid_competencies']++;
                } else if (!empty($rowData['competency_code'])) {
                    $this->quizzes[$quizName]['invalid_competencies']++;
                }

                $this->total_questions++;
            }
        }

        if (empty($this->quizzes)) {
            $this->errors[] = 'Nessuna domanda trovata nel file Excel.';
            return false;
        }

        return true;
    }

    /**
     * Parse a single row into question data
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $row Row number
     * @return array|null Question data or null if row is empty/invalid
     */
    private function parse_row($sheet, $row) {
        // Get quiz name (required - column A)
        $quizName = trim($this->get_cell_value($sheet, self::COLUMN_MAP['quiz_name'], $row));
        if (empty($quizName)) {
            return null; // Skip empty rows
        }

        // Get question text (required - column C)
        $questionText = trim($this->get_cell_value($sheet, self::COLUMN_MAP['question'], $row));
        if (empty($questionText)) {
            return null; // Skip rows without question
        }

        // Get question number (column B)
        $number = $this->get_cell_value($sheet, self::COLUMN_MAP['number'], $row);
        if (empty($number)) {
            $number = $row;
        }

        // Get answers (columns D-G)
        $answerA = trim($this->get_cell_value($sheet, self::COLUMN_MAP['answer_a'], $row));
        $answerB = trim($this->get_cell_value($sheet, self::COLUMN_MAP['answer_b'], $row));
        $answerC = trim($this->get_cell_value($sheet, self::COLUMN_MAP['answer_c'], $row));
        $answerD = trim($this->get_cell_value($sheet, self::COLUMN_MAP['answer_d'], $row));

        // Validate we have at least 2 answers
        $answersCount = count(array_filter([$answerA, $answerB, $answerC, $answerD], function($a) {
            return !empty($a);
        }));
        if ($answersCount < 2) {
            $this->warnings[] = "Riga $row: meno di 2 risposte valide";
        }

        // Get correct answer (column H)
        $correctLetter = strtoupper(trim($this->get_cell_value($sheet, self::COLUMN_MAP['correct'], $row)));
        if (!in_array($correctLetter, ['A', 'B', 'C', 'D'])) {
            $this->warnings[] = "Riga $row: risposta corretta non valida '$correctLetter', uso A come default";
            $correctLetter = 'A';
        }

        // Get sector (column I)
        $sector = trim($this->get_cell_value($sheet, self::COLUMN_MAP['sector'], $row));

        // Get competency code (column J)
        $competencyCode = trim($this->get_cell_value($sheet, self::COLUMN_MAP['competency_code'], $row));
        $competencyCodeUpper = mb_strtoupper($competencyCode, 'UTF-8');

        // Get competency description (column K)
        $competencyDesc = trim($this->get_cell_value($sheet, self::COLUMN_MAP['competency_desc'], $row));

        // Get difficulty level (column L)
        $difficulty = (int) $this->get_cell_value($sheet, self::COLUMN_MAP['difficulty'], $row);
        if ($difficulty < 1 || $difficulty > 3) {
            $difficulty = 2; // Default to intermediate
        }

        // Validate competency against framework
        $competencyValid = false;
        $competencyId = null;

        if (!empty($competencyCode) && !empty($this->competency_lookup)) {
            if (isset($this->competency_lookup[$competencyCodeUpper])) {
                $competencyValid = true;
                $competencyId = $this->competency_lookup[$competencyCodeUpper]->id;
            } else {
                // Try fuzzy match
                $suggested = $this->find_similar_competency($competencyCodeUpper);
                if ($suggested) {
                    $this->warnings[] = "Riga $row: '$competencyCode' non trovato. Simile: $suggested";
                }
            }
        }

        // Build question data
        return [
            'row' => $row,
            'quiz_name' => $quizName,
            'number' => $number,
            'name' => $this->generate_question_name($number, $competencyCode),
            'questiontext' => $questionText,
            'answers' => [
                ['text' => $answerA, 'fraction' => ($correctLetter === 'A') ? 1 : 0],
                ['text' => $answerB, 'fraction' => ($correctLetter === 'B') ? 1 : 0],
                ['text' => $answerC, 'fraction' => ($correctLetter === 'C') ? 1 : 0],
                ['text' => $answerD, 'fraction' => ($correctLetter === 'D') ? 1 : 0],
            ],
            'correct_letter' => $correctLetter,
            'sector' => $sector,
            'competency_code' => $competencyCode,
            'competency_code_upper' => $competencyCodeUpper,
            'competency_description' => $competencyDesc,
            'difficulty' => $difficulty,
            'competency_valid' => $competencyValid,
            'competency_id' => $competencyId
        ];
    }

    /**
     * Get cell value by column index and row
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $col Column index (0-based)
     * @param int $row Row number
     * @return string Cell value
     */
    private function get_cell_value($sheet, $col, $row) {
        try {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($colLetter . $row);
            $value = $cell->getValue();

            // Handle rich text
            if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $value = $value->getPlainText();
            }

            return (string) $value;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extract short name from full quiz name
     * E.g., "MECCANICA - MECC_APPR_A_Produzione_meccanica_operativa_DEFINITIVO_20260113_184015"
     * becomes "MECC_APPR_A_Produzione_meccanica_operativa"
     *
     * @param string $fullName Full quiz name
     * @return string Short name
     */
    private function extract_short_name($fullName) {
        // Remove sector prefix if present (e.g., "MECCANICA - ")
        if (preg_match('/^[A-Z]+\s*-\s*(.+)$/i', $fullName, $matches)) {
            $name = $matches[1];
        } else {
            $name = $fullName;
        }

        // Remove _DEFINITIVO_YYYYMMDD_HHMMSS suffix if present
        $name = preg_replace('/_DEFINITIVO_\d{8}_\d{6}$/i', '', $name);

        return $name;
    }

    /**
     * Generate question name from number and competency code
     *
     * @param mixed $number Question number
     * @param string $competencyCode Competency code
     * @return string Question name
     */
    private function generate_question_name($number, $competencyCode) {
        $num = str_pad((int)$number, 2, '0', STR_PAD_LEFT);
        if (!empty($competencyCode)) {
            return "Q{$num} - {$competencyCode}";
        }
        return "Q{$num}";
    }

    /**
     * Find similar competency code (fuzzy matching)
     *
     * @param string $code Competency code to match
     * @return string|null Similar code or null
     */
    private function find_similar_competency($code) {
        $bestMatch = null;
        $bestScore = 0;

        foreach (array_keys($this->competency_lookup) as $existingCode) {
            similar_text($code, $existingCode, $percent);
            if ($percent > 80 && $percent > $bestScore) {
                $bestScore = $percent;
                $bestMatch = $existingCode;
            }
        }

        return $bestMatch;
    }

    /**
     * Get all parsed quizzes
     *
     * @return array Quizzes data
     */
    public function get_quizzes() {
        return $this->quizzes;
    }

    /**
     * Get quiz count
     *
     * @return int Number of quizzes
     */
    public function get_quiz_count() {
        return count($this->quizzes);
    }

    /**
     * Get total question count
     *
     * @return int Total number of questions
     */
    public function get_total_questions() {
        return $this->total_questions;
    }

    /**
     * Get validation errors
     *
     * @return array Errors
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get validation warnings
     *
     * @return array Warnings
     */
    public function get_warnings() {
        return $this->warnings;
    }

    /**
     * Get filename
     *
     * @return string Filename
     */
    public function get_filename() {
        return $this->filename;
    }

    /**
     * Check if file has errors
     *
     * @return bool True if errors exist
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Validate all competencies
     *
     * @return array Validation stats
     */
    public function validate() {
        $stats = [
            'total_quizzes' => count($this->quizzes),
            'total_questions' => $this->total_questions,
            'valid_competencies' => 0,
            'invalid_competencies' => 0,
            'invalid_codes' => [],
            'by_difficulty' => [1 => 0, 2 => 0, 3 => 0]
        ];

        foreach ($this->quizzes as $quiz) {
            $stats['valid_competencies'] += $quiz['valid_competencies'];
            $stats['invalid_competencies'] += $quiz['invalid_competencies'];

            foreach ($quiz['difficulty_distribution'] as $level => $count) {
                $stats['by_difficulty'][$level] += $count;
            }

            // Collect invalid codes
            foreach ($quiz['questions'] as $q) {
                if (!empty($q['competency_code']) && !$q['competency_valid']) {
                    if (!in_array($q['competency_code'], $stats['invalid_codes'])) {
                        $stats['invalid_codes'][] = $q['competency_code'];
                    }
                }
            }
        }

        $stats['all_valid'] = ($stats['invalid_competencies'] === 0);

        return $stats;
    }

    /**
     * Get summary for display
     *
     * @return array Summary data
     */
    public function get_summary() {
        $validation = $this->validate();

        $quizSummaries = [];
        foreach ($this->quizzes as $name => $quiz) {
            $quizSummaries[] = [
                'name' => $quiz['name'],
                'short_name' => $quiz['short_name'],
                'question_count' => count($quiz['questions']),
                'valid_competencies' => $quiz['valid_competencies'],
                'invalid_competencies' => $quiz['invalid_competencies'],
                'difficulty_distribution' => $quiz['difficulty_distribution'],
                'sector' => $quiz['sector']
            ];
        }

        return [
            'filename' => $this->filename,
            'quiz_count' => count($this->quizzes),
            'total_questions' => $this->total_questions,
            'quizzes' => $quizSummaries,
            'validation' => $validation,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'can_import' => empty($this->errors)
        ];
    }

    /**
     * Create all quizzes and import questions
     *
     * @param int $courseid Course ID
     * @param bool $assigncompetencies Whether to assign competencies
     * @return array Result with stats
     */
    public function create_all_quizzes($courseid, $assigncompetencies = true) {
        global $DB, $USER;

        $result = [
            'success' => false,
            'quizzes_created' => 0,
            'questions_created' => 0,
            'questions_existing' => 0,
            'competencies_assigned' => 0,
            'quiz_details' => [],
            'errors' => []
        ];

        try {
            $context = \context_course::instance($courseid);

            // Get or create parent category (sector name)
            $sectorName = $this->sector ?: 'Import Excel';
            $parentCat = $this->get_or_create_category($context->id, $sectorName, 0);

            foreach ($this->quizzes as $quizName => $quizData) {
                // Create subcategory for this quiz
                $subCatName = $quizData['short_name'];
                $subCat = $this->get_or_create_category($context->id, $subCatName, $parentCat->id);

                // Create questions
                $questionIds = [];
                $questionsCreated = 0;
                $questionsExisting = 0;
                $competenciesAssigned = 0;

                foreach ($quizData['questions'] as $qdata) {
                    $questionResult = $this->create_question($qdata, $subCat, $assigncompetencies);

                    if ($questionResult['id']) {
                        $questionIds[] = $questionResult['id'];

                        if ($questionResult['created']) {
                            $questionsCreated++;
                            $result['questions_created']++;
                        } else {
                            $questionsExisting++;
                            $result['questions_existing']++;
                        }

                        if ($questionResult['competency_assigned']) {
                            $competenciesAssigned++;
                            $result['competencies_assigned']++;
                        }
                    }
                }

                // Create quiz activity
                $quiz = $this->create_quiz_activity($courseid, $quizData['name']);

                if ($quiz) {
                    // Add questions to quiz
                    $this->add_questions_to_quiz($quiz->id, $questionIds);

                    $result['quizzes_created']++;
                    $result['quiz_details'][] = [
                        'name' => $quizData['name'],
                        'id' => $quiz->id,
                        'questions' => count($questionIds),
                        'created' => $questionsCreated,
                        'existing' => $questionsExisting,
                        'competencies' => $competenciesAssigned
                    ];
                }
            }

            $result['success'] = true;

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get or create question category
     *
     * @param int $contextid Context ID
     * @param string $name Category name
     * @param int $parentid Parent category ID (0 for top-level)
     * @return object Category record
     */
    private function get_or_create_category($contextid, $name, $parentid) {
        global $DB;

        $category = $DB->get_record('question_categories', [
            'contextid' => $contextid,
            'name' => $name,
            'parent' => $parentid
        ]);

        if (!$category) {
            $category = new \stdClass();
            $category->name = $name;
            $category->contextid = $contextid;
            $category->info = 'Importato da Excel';
            $category->infoformat = FORMAT_HTML;
            $category->parent = $parentid;
            $category->sortorder = 999;
            $category->stamp = make_unique_id_code();
            $category->id = $DB->insert_record('question_categories', $category);
        }

        return $category;
    }

    /**
     * Create a single question
     *
     * @param array $qdata Question data
     * @param object $category Question category
     * @param bool $assigncompetency Whether to assign competency
     * @return array Result with id, created flag, competency_assigned flag
     */
    private function create_question($qdata, $category, $assigncompetency) {
        global $DB, $USER;

        $result = [
            'id' => null,
            'created' => false,
            'competency_assigned' => false
        ];

        // Check if question already exists
        $existing = $DB->get_record_sql("
            SELECT q.id FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = ? AND q.name = ?
        ", [$category->id, $qdata['name']]);

        if ($existing) {
            $result['id'] = $existing->id;
            $result['created'] = false;

            // Assign/update competency for existing question
            if ($assigncompetency && $qdata['competency_valid'] && $qdata['competency_id']) {
                $this->assign_competency_to_question($existing->id, $qdata['competency_id'], $qdata['difficulty']);
                $result['competency_assigned'] = true;
            }

            return $result;
        }

        // Create question bank entry
        // Note: idnumber left empty - competency code is stored in qbank_competenciesbyquestion table
        $qbe = new \stdClass();
        $qbe->questioncategoryid = $category->id;
        $qbe->ownerid = $USER->id;
        $qbe->idnumber = null;
        $qbe->id = $DB->insert_record('question_bank_entries', $qbe);

        // Create question
        $question = new \stdClass();
        $question->name = $qdata['name'];
        $question->questiontext = $qdata['questiontext'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = 'multichoice';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;
        $question->id = $DB->insert_record('question', $question);

        // Create question version
        $qv = new \stdClass();
        $qv->questionbankentryid = $qbe->id;
        $qv->questionid = $question->id;
        $qv->version = 1;
        $qv->status = 'ready';
        $DB->insert_record('question_versions', $qv);

        // Create multichoice options
        $opts = new \stdClass();
        $opts->questionid = $question->id;
        $opts->single = 1;
        $opts->shuffleanswers = 1;
        $opts->answernumbering = 'abc';
        $opts->correctfeedback = '';
        $opts->correctfeedbackformat = FORMAT_HTML;
        $opts->partiallycorrectfeedback = '';
        $opts->partiallycorrectfeedbackformat = FORMAT_HTML;
        $opts->incorrectfeedback = '';
        $opts->incorrectfeedbackformat = FORMAT_HTML;
        $opts->shownumcorrect = 0;
        $DB->insert_record('qtype_multichoice_options', $opts);

        // Create answers
        foreach ($qdata['answers'] as $answer) {
            if (!empty(trim($answer['text']))) {
                $ans = new \stdClass();
                $ans->question = $question->id;
                $ans->answer = $answer['text'];
                $ans->answerformat = FORMAT_HTML;
                $ans->fraction = $answer['fraction'];
                $ans->feedback = '';
                $ans->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $ans);
            }
        }

        // Assign competency
        if ($assigncompetency && $qdata['competency_valid'] && $qdata['competency_id']) {
            $this->assign_competency_to_question($question->id, $qdata['competency_id'], $qdata['difficulty']);
            $result['competency_assigned'] = true;
        }

        $result['id'] = $question->id;
        $result['created'] = true;

        return $result;
    }

    /**
     * Assign competency to question
     *
     * @param int $questionid Question ID
     * @param int $competencyid Competency ID
     * @param int $difficulty Difficulty level (1-3)
     */
    private function assign_competency_to_question($questionid, $competencyid, $difficulty) {
        global $DB;

        $existing = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $questionid]);

        if ($existing) {
            if ($existing->competencyid != $competencyid || $existing->difficultylevel != $difficulty) {
                $existing->competencyid = $competencyid;
                $existing->difficultylevel = $difficulty;
                $DB->update_record('qbank_competenciesbyquestion', $existing);
            }
        } else {
            $rec = new \stdClass();
            $rec->questionid = $questionid;
            $rec->competencyid = $competencyid;
            $rec->difficultylevel = $difficulty;
            $DB->insert_record('qbank_competenciesbyquestion', $rec);
        }
    }

    /**
     * Create quiz activity in course
     *
     * @param int $courseid Course ID
     * @param string $name Quiz name
     * @return object|null Quiz object
     */
    private function create_quiz_activity($courseid, $name) {
        global $DB;

        $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);

        $quiz = new \stdClass();
        $quiz->course = $courseid;
        $quiz->name = $name;
        $quiz->intro = '<p>Quiz importato da Excel</p>';
        $quiz->introformat = FORMAT_HTML;
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->attempts = 0;
        $quiz->grademethod = 1;
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->grade = 10;
        $quiz->sumgrades = 0;
        $quiz->shuffleanswers = 1;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->reviewattempt = 69904;
        $quiz->reviewcorrectness = 69904;
        $quiz->reviewmaxmarks = 69904;
        $quiz->reviewmarks = 69904;
        $quiz->reviewspecificfeedback = 69904;
        $quiz->reviewgeneralfeedback = 69904;
        $quiz->reviewrightanswer = 69904;
        $quiz->reviewoverallfeedback = 69904;
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->id = $DB->insert_record('quiz', $quiz);

        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $cm = new \stdClass();
        $cm->course = $courseid;
        $cm->module = $module->id;
        $cm->instance = $quiz->id;
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->visibleoncoursepage = 1;
        $cm->added = time();
        $cm->id = $DB->insert_record('course_modules', $cm);

        course_add_cm_to_section($courseid, $cm->id, $section->section);
        \context_module::instance($cm->id);

        $qs = new \stdClass();
        $qs->quizid = $quiz->id;
        $qs->firstslot = 1;
        $qs->heading = '';
        $qs->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $qs);

        return $quiz;
    }

    /**
     * Add questions to quiz
     *
     * @param int $quizid Quiz ID
     * @param array $questionids Question IDs
     */
    private function add_questions_to_quiz($quizid, $questionids) {
        global $DB;

        $slot = 0;
        foreach ($questionids as $qid) {
            $slot++;

            $qbe = $DB->get_record_sql("
                SELECT qbe.id FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = ?
            ", [$qid]);

            if (!$qbe) {
                continue;
            }

            $slotrecord = new \stdClass();
            $slotrecord->quizid = $quizid;
            $slotrecord->slot = $slot;
            $slotrecord->page = ceil($slot / 5);
            $slotrecord->maxmark = 1.0;
            $slotrecord->id = $DB->insert_record('quiz_slots', $slotrecord);

            $cmid = $DB->get_field('course_modules', 'id', [
                'module' => $DB->get_field('modules', 'id', ['name' => 'quiz']),
                'instance' => $quizid
            ]);

            $ref = new \stdClass();
            $ref->usingcontextid = \context_module::instance($cmid)->id;
            $ref->component = 'mod_quiz';
            $ref->questionarea = 'slot';
            $ref->itemid = $slotrecord->id;
            $ref->questionbankentryid = $qbe->id;
            $ref->version = null;
            $DB->insert_record('question_references', $ref);
        }

        $DB->set_field('quiz', 'sumgrades', $slot, ['id' => $quizid]);
    }
}
