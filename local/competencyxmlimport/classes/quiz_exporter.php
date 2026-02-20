<?php
/**
 * Quiz exporter class for exporting quizzes to HTML and Excel formats.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    2026021901 - Fixed get_recordset_sql for duplicate slot warning
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');

/**
 * Class quiz_exporter
 *
 * Exports quiz questions with competencies to HTML and Excel formats.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_exporter {

    /**
     * Get all quizzes in a course.
     *
     * @param int $courseid The course ID.
     * @return array Array of quiz objects with id, name, and intro.
     */
    public function get_quizzes_in_course($courseid) {
        global $DB;

        $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name, intro, timeopen, timeclose');

        return $quizzes;
    }

    /**
     * Get all questions in a quiz with their answers and competencies.
     *
     * @param int $quizid The quiz ID.
     * @return array Array of question objects with answers and competencies.
     */
    public function get_quiz_questions($quizid) {
        global $DB;

        // Main query to get questions with competencies.
        $sql = "SELECT
                    qs.slot,
                    q.id AS questionid,
                    q.name AS question_name,
                    q.questiontext,
                    q.questiontextformat,
                    q.qtype,
                    comp.idnumber AS competency_code,
                    comp.shortname AS competency_name,
                    comp.description AS competency_description,
                    COALESCE(qcbq.difficultylevel, 1) AS difficultylevel
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.component = 'mod_quiz'
                    AND qr.questionarea = 'slot'
                    AND qr.itemid = qs.id
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                WHERE qs.quizid = :quizid
                ORDER BY qs.slot";

        // Use get_recordset_sql to avoid "duplicate key" warning when
        // questions have multiple versions or competencies.
        $rs = $DB->get_recordset_sql($sql, ['quizid' => $quizid]);
        $questions = [];
        foreach ($rs as $record) {
            $questions[$record->slot] = $record;
        }
        $rs->close();

        // Get answers for each question.
        foreach ($questions as $question) {
            $question->answers = $this->get_question_answers($question->questionid);
        }

        return $questions;
    }

    /**
     * Get answers for a specific question.
     *
     * @param int $questionid The question ID.
     * @return array Array of answer objects.
     */
    protected function get_question_answers($questionid) {
        global $DB;

        $sql = "SELECT id, answer, answerformat, fraction
                FROM {question_answers}
                WHERE question = :questionid
                ORDER BY id";

        return $DB->get_records_sql($sql, ['questionid' => $questionid]);
    }

    /**
     * Export a quiz to printable HTML format.
     *
     * @param int $quizid The quiz ID.
     * @return string HTML content.
     */
    public function export_to_html($quizid) {
        global $DB;

        // Get quiz info.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

        // Get questions.
        $questions = $this->get_quiz_questions($quizid);

        // Build HTML.
        $html = $this->build_html_header($quiz->name, $course->fullname);
        $html .= $this->build_html_content($questions, $quiz->name, $course->fullname);
        $html .= $this->build_html_footer();

        return $html;
    }

    /**
     * Build the HTML header with CSS styles.
     *
     * @param string $title The document title.
     * @param string $coursename The course name.
     * @return string HTML header.
     */
    protected function build_html_header($title, $coursename) {
        $escapedtitle = s($title);
        $escapedcourse = s($coursename);

        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedtitle} - {$escapedcourse}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #333;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #0066cc;
            font-size: 18pt;
            margin: 0 0 5px 0;
        }

        .header h2 {
            color: #666;
            font-size: 14pt;
            font-weight: normal;
            margin: 0;
        }

        .question {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            page-break-inside: avoid;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .question-number {
            background: #0066cc;
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 11pt;
        }

        .question-difficulty {
            color: #EAB308;
            font-size: 14pt;
        }

        .question-text {
            margin-bottom: 15px;
            font-size: 11pt;
        }

        .question-text p {
            margin: 0;
        }

        .answers {
            margin-left: 20px;
        }

        .answer {
            margin-bottom: 8px;
            padding: 8px 12px;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .answer-correct {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .answer-label {
            font-weight: bold;
            margin-right: 8px;
        }

        .competency-info {
            margin-top: 15px;
            padding: 10px;
            background: #e7f3ff;
            border-radius: 4px;
            font-size: 10pt;
        }

        .competency-code {
            font-weight: bold;
            color: #0066cc;
        }

        .competency-name {
            color: #333;
        }

        .no-competency {
            color: #6c757d;
            font-style: italic;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10pt;
            color: #6c757d;
        }

        /* Print styles */
        @media print {
            body {
                padding: 0;
                font-size: 11pt;
            }

            .question {
                border: 1px solid #ccc;
                page-break-inside: avoid;
            }

            .header {
                page-break-after: avoid;
            }

            .answer-correct {
                background: #d4edda !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .competency-info {
                background: #e7f3ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
HTML;
    }

    /**
     * Build the HTML content with questions.
     *
     * @param array $questions Array of question objects.
     * @param string $quizname The quiz name.
     * @param string $coursename The course name.
     * @return string HTML content.
     */
    protected function build_html_content($questions, $quizname, $coursename) {
        $html = '<div class="header">';
        $html .= '<h1>' . s($quizname) . '</h1>';
        $html .= '<h2>' . s($coursename) . '</h2>';
        $html .= '<p>' . get_string('totalquestions', 'local_competencyxmlimport') . ': ' . count($questions) . '</p>';
        $html .= '</div>';

        $html .= '<div class="questions">';

        $questionnumber = 1;
        $answerletters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($questions as $question) {
            $html .= '<div class="question">';

            // Question header with number and difficulty.
            $html .= '<div class="question-header">';
            $html .= '<span class="question-number">' . get_string('question', 'local_competencyxmlimport') . ' ' . $questionnumber . '</span>';
            $html .= '<span class="question-difficulty">' . $this->get_difficulty_stars($question->difficultylevel) . '</span>';
            $html .= '</div>';

            // Question text.
            $html .= '<div class="question-text">';
            $html .= format_text($question->questiontext, $question->questiontextformat);
            $html .= '</div>';

            // Answers.
            if (!empty($question->answers)) {
                $html .= '<div class="answers">';
                $answerindex = 0;
                foreach ($question->answers as $answer) {
                    $letter = isset($answerletters[$answerindex]) ? $answerletters[$answerindex] : ($answerindex + 1);
                    $iscorrect = ($answer->fraction > 0);
                    $answerclass = $iscorrect ? 'answer answer-correct' : 'answer';

                    $html .= '<div class="' . $answerclass . '">';
                    $html .= '<span class="answer-label">' . $letter . '.</span>';
                    $html .= format_text($answer->answer, $answer->answerformat);
                    $html .= '</div>';

                    $answerindex++;
                }
                $html .= '</div>';
            }

            // Competency info.
            $html .= '<div class="competency-info">';
            if (!empty($question->competency_code)) {
                $html .= '<span class="competency-code">[' . s($question->competency_code) . ']</span> ';
                $html .= '<span class="competency-name">' . s($question->competency_name) . '</span>';
            } else {
                $html .= '<span class="no-competency">' . get_string('nocompetencyassigned', 'local_competencyxmlimport') . '</span>';
            }
            $html .= '</div>';

            $html .= '</div>';

            $questionnumber++;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build the HTML footer.
     *
     * @return string HTML footer.
     */
    protected function build_html_footer() {
        $date = userdate(time(), get_string('strftimedatetime', 'langconfig'));

        return <<<HTML
    <div class="footer">
        <p>FTM - Fondazione Terzo Millennio</p>
        <p>{$date}</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Convert difficulty level to star representation.
     *
     * @param int $level Difficulty level (1-5).
     * @return string Star representation.
     */
    protected function get_difficulty_stars($level) {
        $level = max(1, min(5, (int)$level));
        $stars = str_repeat("\u{2B50}", $level);
        $empty = str_repeat("\u{2606}", 5 - $level);
        return $stars . $empty;
    }

    /**
     * Extract sector from competency idnumber.
     *
     * The idnumber format is SETTORE_AREA_NUMERO (e.g., MECCANICA_DT_01).
     * This method extracts the first part (sector).
     *
     * @param string $competencycode The competency idnumber.
     * @return string The sector name or empty string if not found.
     */
    public function extract_sector_from_competency($competencycode) {
        if (empty($competencycode)) {
            return '';
        }

        // Split by underscore and get the first part.
        $parts = explode('_', $competencycode);
        if (empty($parts[0])) {
            return '';
        }

        $sector = strtoupper(trim($parts[0]));

        // Handle common aliases to normalize sector names.
        $aliases = [
            'AUTOVEICOLO' => 'AUTOMOBILE',
            'AUTOM' => 'AUTOMAZIONE',
            'AUTOMAZ' => 'AUTOMAZIONE',
            'CHIM' => 'CHIMFARM',
            'CHIMICA' => 'CHIMFARM',
            'FARMACEUTICA' => 'CHIMFARM',
            'ELETTRICITA' => 'ELETTRICITÀ',
            'ELETTR' => 'ELETTRICITÀ',
            'ELETT' => 'ELETTRICITÀ',
            'LOG' => 'LOGISTICA',
            'MECC' => 'MECCANICA',
            'METAL' => 'METALCOSTRUZIONE',
            'GEN' => 'GENERICO',
            'GENERICO' => 'GENERICO',
            'GENERICHE' => 'GENERICO',
            'TRASVERSALI' => 'GENERICO',
            'SOFT' => 'GENERICO',
            'OLD' => 'LEGACY',
        ];

        return $aliases[$sector] ?? $sector;
    }

    /**
     * Export a quiz to Excel format.
     *
     * @param int $quizid The quiz ID.
     * @return string Path to the generated Excel file.
     */
    public function export_to_excel($quizid) {
        global $DB, $CFG;

        // Get quiz info.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

        // Get questions.
        $questions = $this->get_quiz_questions($quizid);

        // Create filename.
        $filename = clean_filename($quiz->name . '_' . date('Y-m-d'));
        $filepath = $CFG->tempdir . '/' . $filename . '.xlsx';

        // Create workbook.
        $workbook = new \MoodleExcelWorkbook($filepath);
        $worksheet = $workbook->add_worksheet(get_string('questions', 'local_competencyxmlimport'));

        // Define formats.
        $formatheader = $workbook->add_format([
            'bold' => 1,
            'bg_color' => '#0066cc',
            'color' => 'white',
            'align' => 'center',
            'border' => 1
        ]);

        $formatcorrect = $workbook->add_format([
            'bg_color' => '#d4edda',
            'border' => 1
        ]);

        $formatnormal = $workbook->add_format([
            'border' => 1
        ]);

        $formatcompetency = $workbook->add_format([
            'bg_color' => '#e7f3ff',
            'border' => 1
        ]);

        // Set column widths.
        $worksheet->set_column(0, 0, 5);   // #
        $worksheet->set_column(1, 1, 50);  // Question text
        $worksheet->set_column(2, 2, 30);  // Answer A
        $worksheet->set_column(3, 3, 30);  // Answer B
        $worksheet->set_column(4, 4, 30);  // Answer C
        $worksheet->set_column(5, 5, 30);  // Answer D
        $worksheet->set_column(6, 6, 15);  // Correct
        $worksheet->set_column(7, 7, 15);  // Competency Code
        $worksheet->set_column(8, 8, 30);  // Competency Name
        $worksheet->set_column(9, 9, 10);  // Difficulty

        // Write header row.
        $row = 0;
        $headers = [
            '#',
            get_string('questiontext', 'question'),
            get_string('answera', 'local_competencyxmlimport'),
            get_string('answerb', 'local_competencyxmlimport'),
            get_string('answerc', 'local_competencyxmlimport'),
            get_string('answerd', 'local_competencyxmlimport'),
            get_string('correctanswer', 'local_competencyxmlimport'),
            get_string('competencycode', 'local_competencyxmlimport'),
            get_string('competencydescription', 'local_competencyxmlimport'),
            get_string('difficulty', 'local_competencyxmlimport')
        ];

        $col = 0;
        foreach ($headers as $header) {
            $worksheet->write_string($row, $col, $header, $formatheader);
            $col++;
        }

        // Write question data.
        $row = 1;
        $questionnumber = 1;
        $answerletters = ['A', 'B', 'C', 'D'];

        foreach ($questions as $question) {
            // Question number.
            $worksheet->write_number($row, 0, $questionnumber, $formatnormal);

            // Question text (strip HTML).
            $questiontext = strip_tags(format_text($question->questiontext, $question->questiontextformat));
            $worksheet->write_string($row, 1, $questiontext, $formatnormal);

            // Answers.
            $correctanswer = '';
            $answerindex = 0;
            if (!empty($question->answers)) {
                foreach ($question->answers as $answer) {
                    if ($answerindex < 4) {
                        $answertext = strip_tags(format_text($answer->answer, $answer->answerformat));
                        $format = ($answer->fraction > 0) ? $formatcorrect : $formatnormal;
                        $worksheet->write_string($row, 2 + $answerindex, $answertext, $format);

                        if ($answer->fraction > 0) {
                            $correctanswer = $answerletters[$answerindex];
                        }
                    }
                    $answerindex++;
                }
            }

            // Fill empty answer cells.
            for ($i = $answerindex; $i < 4; $i++) {
                $worksheet->write_string($row, 2 + $i, '', $formatnormal);
            }

            // Correct answer letter.
            $worksheet->write_string($row, 6, $correctanswer, $formatcorrect);

            // Competency info.
            $competencycode = !empty($question->competency_code) ? $question->competency_code : '';
            $competencydesc = !empty($question->competency_description) ? $question->competency_description : '';
            // Pulisce HTML dalla descrizione
            $competencydesc = strip_tags(html_entity_decode($competencydesc, ENT_QUOTES, 'UTF-8'));
            $worksheet->write_string($row, 7, $competencycode, $formatcompetency);
            $worksheet->write_string($row, 8, $competencydesc, $formatcompetency);

            // Difficulty.
            $worksheet->write_number($row, 9, (int)$question->difficultylevel, $formatnormal);

            $row++;
            $questionnumber++;
        }

        // Close workbook.
        $workbook->close();

        return $filepath;
    }

    /**
     * Get quiz information including question count.
     *
     * @param int $quizid The quiz ID.
     * @return object Quiz object with additional info.
     */
    public function get_quiz_info($quizid) {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

        // Count questions.
        $sql = "SELECT COUNT(DISTINCT qs.id)
                FROM {quiz_slots} qs
                WHERE qs.quizid = :quizid";
        $quiz->questioncount = $DB->count_records_sql($sql, ['quizid' => $quizid]);

        // Count questions with competencies.
        $sql = "SELECT COUNT(DISTINCT q.id)
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.component = 'mod_quiz'
                    AND qr.questionarea = 'slot'
                    AND qr.itemid = qs.id
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                WHERE qs.quizid = :quizid";
        $quiz->questionsWithCompetency = $DB->count_records_sql($sql, ['quizid' => $quizid]);

        return $quiz;
    }
}
