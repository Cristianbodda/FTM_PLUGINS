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
 * Quiz Excel/CSV exporter class.
 *
 * Handles export of quiz questions to Excel or CSV format.
 * Uses PhpSpreadsheet if available, otherwise falls back to CSV.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Class quiz_excel_exporter
 *
 * Exports quiz questions to Excel (.xlsx) or CSV format.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_excel_exporter {

    /** @var string Header background color for Excel */
    const HEADER_COLOR = '0066cc';

    /** @var string Correct answer background color */
    const CORRECT_COLOR = '28a745';

    /** @var string CSV separator for European Excel */
    const CSV_SEPARATOR = ';';

    /** @var string UTF-8 BOM for Excel compatibility */
    const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Export quiz questions to CSV format.
     *
     * Creates a semicolon-separated CSV file with UTF-8 BOM
     * for proper Excel compatibility in European locales.
     *
     * @param array $questions Array of question objects from quiz_exporter::get_quiz_questions()
     * @param string $quizname Name of the quiz (used for metadata)
     * @return string CSV content as string
     */
    public function export_quiz_to_csv(array $questions, string $quizname): string {
        $output = self::UTF8_BOM;

        // Header row.
        $headers = [
            '#',
            'Domanda',
            'Risposta A',
            'Risposta B',
            'Risposta C',
            'Risposta D',
            'Corretta',
            'Codice Competenza',
            'Nome Competenza',
            'Difficolta'
        ];
        $output .= $this->array_to_csv_line($headers);

        // Data rows.
        $rownum = 1;
        foreach ($questions as $question) {
            $row = $this->build_question_row($question, $rownum);
            $output .= $this->array_to_csv_line($row);
            $rownum++;
        }

        return $output;
    }

    /**
     * Export quiz questions to Excel format.
     *
     * Attempts to use PhpSpreadsheet for proper .xlsx output.
     * Falls back to CSV if PhpSpreadsheet is not available.
     *
     * @param array $questions Array of question objects from quiz_exporter::get_quiz_questions()
     * @param string $quizname Name of the quiz
     * @return array ['content' => string, 'extension' => string, 'mimetype' => string]
     */
    public function export_quiz_to_excel(array $questions, string $quizname): array {
        // Check if PhpSpreadsheet is available.
        if ($this->is_phpspreadsheet_available()) {
            return $this->create_xlsx($questions, $quizname);
        }

        // Fall back to CSV.
        return [
            'content' => $this->export_quiz_to_csv($questions, $quizname),
            'extension' => 'csv',
            'mimetype' => 'text/csv; charset=utf-8'
        ];
    }

    /**
     * Check if PhpSpreadsheet library is available.
     *
     * @return bool True if PhpSpreadsheet can be used
     */
    protected function is_phpspreadsheet_available(): bool {
        return class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet');
    }

    /**
     * Create Excel file using PhpSpreadsheet.
     *
     * @param array $questions Array of question objects
     * @param string $quizname Name of the quiz
     * @return array ['content' => string, 'extension' => string, 'mimetype' => string]
     */
    protected function create_xlsx(array $questions, string $quizname): array {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($this->clean_text($quizname), 0, 31));

        // Define headers.
        $headers = [
            'A1' => '#',
            'B1' => 'Domanda',
            'C1' => 'Risposta A',
            'D1' => 'Risposta B',
            'E1' => 'Risposta C',
            'F1' => 'Risposta D',
            'G1' => 'Corretta',
            'H1' => 'Codice Competenza',
            'I1' => 'Nome Competenza',
            'J1' => 'Difficolta'
        ];

        // Set header values and styling.
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style header row.
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_COLOR]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ]
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Add data rows.
        $rownum = 2;
        foreach ($questions as $question) {
            $data = $this->build_question_row($question, $rownum - 1);

            $sheet->setCellValue('A' . $rownum, $data[0]);
            $sheet->setCellValue('B' . $rownum, $data[1]);
            $sheet->setCellValue('C' . $rownum, $data[2]);
            $sheet->setCellValue('D' . $rownum, $data[3]);
            $sheet->setCellValue('E' . $rownum, $data[4]);
            $sheet->setCellValue('F' . $rownum, $data[5]);
            $sheet->setCellValue('G' . $rownum, $data[6]);
            $sheet->setCellValue('H' . $rownum, $data[7]);
            $sheet->setCellValue('I' . $rownum, $data[8]);
            $sheet->setCellValue('J' . $rownum, $data[9]);

            // Highlight correct answer cell in green.
            $correctletter = $data[6];
            if (!empty($correctletter)) {
                $correctcol = $this->letter_to_column($correctletter);
                if ($correctcol) {
                    $sheet->getStyle($correctcol . $rownum)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => self::CORRECT_COLOR]
                        ],
                        'font' => [
                            'color' => ['rgb' => 'FFFFFF'],
                            'bold' => true
                        ]
                    ]);
                }
            }

            $rownum++;
        }

        // Auto-size columns.
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set column B (question) to wrap text and fixed width.
        $sheet->getColumnDimension('B')->setAutoSize(false);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getStyle('B2:B' . ($rownum - 1))->getAlignment()->setWrapText(true);

        // Generate file content.
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return [
            'content' => $content,
            'extension' => 'xlsx',
            'mimetype' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }

    /**
     * Build a data row for a single question.
     *
     * @param object $question Question object
     * @param int $rownum Row number (1-based)
     * @return array Array of cell values
     */
    protected function build_question_row(object $question, int $rownum): array {
        // Get answers (up to 4).
        $answers = ['', '', '', ''];
        $correctindex = -1;

        if (!empty($question->answers) && is_array($question->answers)) {
            foreach ($question->answers as $index => $answer) {
                if ($index < 4) {
                    $answers[$index] = $this->clean_text($answer->answer ?? '');
                    if (!empty($answer->fraction) && $answer->fraction > 0) {
                        $correctindex = $index;
                    }
                }
            }
        }

        // Get competency info.
        $competencycode = '';
        $competencyname = '';
        if (!empty($question->competencies) && is_array($question->competencies)) {
            $firstcomp = reset($question->competencies);
            if ($firstcomp) {
                $competencycode = $firstcomp->idnumber ?? '';
                $competencyname = $firstcomp->shortname ?? '';
            }
        }

        // Get difficulty as number (1/2/3).
        $difficulty = $this->get_difficulty_number($question);

        return [
            $rownum,
            $this->clean_text($question->questiontext ?? ''),
            $answers[0],
            $answers[1],
            $answers[2],
            $answers[3],
            $this->get_answer_letter($correctindex),
            $competencycode,
            $competencyname,
            $difficulty
        ];
    }

    /**
     * Convert array to CSV line with proper escaping.
     *
     * @param array $data Array of values
     * @return string CSV formatted line
     */
    protected function array_to_csv_line(array $data): string {
        $escaped = [];
        foreach ($data as $value) {
            // Convert to string.
            $value = (string) $value;
            // Escape quotes by doubling them.
            $value = str_replace('"', '""', $value);
            // Wrap in quotes if contains separator, quotes, or newlines.
            if (strpos($value, self::CSV_SEPARATOR) !== false ||
                strpos($value, '"') !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false) {
                $value = '"' . $value . '"';
            }
            $escaped[] = $value;
        }
        return implode(self::CSV_SEPARATOR, $escaped) . "\r\n";
    }

    /**
     * Get answer letter (A/B/C/D) based on index.
     *
     * @param int $index Zero-based index (0=A, 1=B, 2=C, 3=D)
     * @return string Letter or empty string if invalid
     */
    public function get_answer_letter(int $index): string {
        $letters = ['A', 'B', 'C', 'D'];
        return $letters[$index] ?? '';
    }

    /**
     * Convert answer letter to Excel column for answers.
     *
     * @param string $letter Answer letter (A/B/C/D)
     * @return string|null Excel column letter or null
     */
    protected function letter_to_column(string $letter): ?string {
        $mapping = [
            'A' => 'C',
            'B' => 'D',
            'C' => 'E',
            'D' => 'F'
        ];
        return $mapping[strtoupper($letter)] ?? null;
    }

    /**
     * Clean text by removing HTML tags and normalizing whitespace.
     *
     * @param string $html HTML or plain text content
     * @return string Cleaned plain text
     */
    public function clean_text(string $html): string {
        // Decode HTML entities.
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace <br> and <p> with newlines before stripping.
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n", $text);

        // Strip remaining HTML tags.
        $text = strip_tags($text);

        // Normalize whitespace (but preserve intentional newlines).
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);

        // Trim.
        $text = trim($text);

        return $text;
    }

    /**
     * Get difficulty as numeric value (1/2/3).
     *
     * @param object $question Question object
     * @return int Difficulty level (1=easy, 2=medium, 3=hard) or 0 if unknown
     */
    protected function get_difficulty_number(object $question): int {
        // Check for difficulty in question tags or custom field.
        if (!empty($question->difficulty)) {
            $diff = strtolower($question->difficulty);
            if (strpos($diff, 'facil') !== false || strpos($diff, 'easy') !== false || $diff === '1') {
                return 1;
            }
            if (strpos($diff, 'medi') !== false || $diff === '2') {
                return 2;
            }
            if (strpos($diff, 'diffic') !== false || strpos($diff, 'hard') !== false || $diff === '3') {
                return 3;
            }
        }

        // Check in tags if available.
        if (!empty($question->tags) && is_array($question->tags)) {
            foreach ($question->tags as $tag) {
                $tagname = strtolower($tag->rawname ?? $tag->name ?? '');
                if (strpos($tagname, 'facil') !== false || strpos($tagname, 'easy') !== false) {
                    return 1;
                }
                if (strpos($tagname, 'medi') !== false) {
                    return 2;
                }
                if (strpos($tagname, 'diffic') !== false || strpos($tagname, 'hard') !== false) {
                    return 3;
                }
            }
        }

        return 0;
    }

    /**
     * Send CSV file as download to browser.
     *
     * @param string $content CSV content
     * @param string $filename Filename without extension
     * @return void
     */
    public function send_csv_download(string $content, string $filename): void {
        $filename = $this->sanitize_filename($filename) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $content;
    }

    /**
     * Send Excel file as download to browser.
     *
     * @param array $exportdata Result from export_quiz_to_excel()
     * @param string $filename Filename without extension
     * @return void
     */
    public function send_excel_download(array $exportdata, string $filename): void {
        $filename = $this->sanitize_filename($filename) . '.' . $exportdata['extension'];

        header('Content-Type: ' . $exportdata['mimetype']);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($exportdata['content']));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $exportdata['content'];
    }

    /**
     * Sanitize filename for safe download.
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    protected function sanitize_filename(string $filename): string {
        // Remove or replace unsafe characters.
        $filename = preg_replace('/[^\w\-. ]/', '_', $filename);
        // Remove multiple underscores/spaces.
        $filename = preg_replace('/[_\s]+/', '_', $filename);
        // Trim.
        $filename = trim($filename, '_');
        // Limit length.
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        return $filename ?: 'export';
    }
}
