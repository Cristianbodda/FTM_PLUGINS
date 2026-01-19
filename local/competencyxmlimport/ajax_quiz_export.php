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
 * AJAX endpoint for quiz export.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/competencyxmlimport/classes/quiz_exporter.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('moodle/question:viewall', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHA);
    $quizid = required_param('quizid', PARAM_INT);

    $exporter = new \local_competencyxmlimport\quiz_exporter();

    switch ($action) {
        case 'preview':
            $questions = $exporter->get_quiz_questions($quizid);
            $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, course');
            $course = $DB->get_record('course', ['id' => $quiz->course], 'id, fullname');

            // Format questions for JSON.
            $formatted = [];
            foreach ($questions as $q) {
                $formatted[] = [
                    'id' => $q->questionid,
                    'name' => format_string($q->question_name),
                    'questiontext' => format_text($q->questiontext, FORMAT_HTML),
                    'qtype' => $q->qtype,
                    'competency_code' => $q->competency_code,
                    'competency_name' => $q->competency_name,
                    'difficultylevel' => $q->difficultylevel,
                    'answers' => array_values(array_map(function($a) {
                        return [
                            'answer' => format_text($a->answer, FORMAT_HTML),
                            'fraction' => $a->fraction,
                        ];
                    }, $q->answers)),
                ];
            }

            echo json_encode([
                'success' => true,
                'quizname' => format_string($quiz->name),
                'coursename' => format_string($course->fullname),
                'questions' => $formatted,
                'total' => count($formatted),
            ]);
            break;

        case 'export':
            $format = optional_param('format', 'csv', PARAM_ALPHA);
            $questions = $exporter->get_quiz_questions($quizid);

            // Return export data.
            echo json_encode([
                'success' => true,
                'message' => get_string('exportsuccessful', 'local_competencyxmlimport'),
                'count' => count($questions),
            ]);
            break;

        default:
            throw new \Exception('Invalid action');
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
