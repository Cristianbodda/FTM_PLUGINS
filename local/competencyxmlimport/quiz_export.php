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
 * Quiz Export Page - Export quiz questions with answers and competencies.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/competencyxmlimport/classes/quiz_exporter.php');

require_login();

$context = context_system::instance();

// IMPORTANT: Set context BEFORE set_url to avoid $PAGE->context error.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/quiz_export.php'));
$PAGE->set_title(get_string('quizexport', 'local_competencyxmlimport'));
$PAGE->set_heading(get_string('quizexport', 'local_competencyxmlimport'));
$PAGE->set_pagelayout('admin');

require_capability('moodle/question:viewall', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$quizids = optional_param_array('quizids', [], PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

// Get courses with quizzes.
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    ORDER BY c.fullname
");

// Get quizzes for selected course.
$quizzes = [];
if ($courseid > 0) {
    $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name', 'id, name, timeopen, timeclose');
}

// Handle export action for multiple quizzes.
if ($action === 'export' && !empty($quizids)) {
    $exporter = new \local_competencyxmlimport\quiz_exporter();

    if ($format === 'excel' || $format === 'csv') {
        // Get course name for filename.
        $course = $DB->get_record('course', ['id' => $courseid], 'shortname');
        $filename = clean_filename(($course ? $course->shortname : 'quiz') . '_export_' . date('Y-m-d')) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM for Excel.
        echo "\xEF\xBB\xBF";

        // Header row.
        echo "Quiz;#;Domanda;Risposta A;Risposta B;Risposta C;Risposta D;Corretta;Settore;Codice Competenza;Descrizione Competenza;Difficolta\n";

        foreach ($quizids as $qid) {
            $quiz = $DB->get_record('quiz', ['id' => $qid], 'name');
            if (!$quiz) {
                continue;
            }
            $quizname = str_replace(';', ',', $quiz->name);
            $questions = $exporter->get_quiz_questions($qid);

            $num = 1;
            foreach ($questions as $q) {
                $questiontext = strip_tags(html_entity_decode($q->questiontext, ENT_QUOTES, 'UTF-8'));
                $questiontext = preg_replace('/\s+/', ' ', trim($questiontext));
                $questiontext = str_replace(';', ',', $questiontext);

                $answers = ['', '', '', ''];
                $correct = '';
                $letters = ['A', 'B', 'C', 'D'];
                $i = 0;
                foreach ($q->answers as $ans) {
                    $anstext = strip_tags(html_entity_decode($ans->answer, ENT_QUOTES, 'UTF-8'));
                    $anstext = preg_replace('/\s+/', ' ', trim($anstext));
                    $anstext = str_replace(';', ',', $anstext);
                    if ($i < 4) {
                        $answers[$i] = $anstext;
                        if ($ans->fraction > 0) {
                            $correct = $letters[$i];
                        }
                    }
                    $i++;
                }

                $compcode = $q->competency_code ?? '';
                // Pulisce HTML dalla descrizione competenza
                $compdesc = $q->competency_description ?? '';
                $compdesc = strip_tags(html_entity_decode($compdesc, ENT_QUOTES, 'UTF-8'));
                $compdesc = preg_replace('/\s+/', ' ', trim($compdesc));
                $compdesc = str_replace(';', ',', $compdesc);
                $difficulty = $q->difficultylevel ?? 1;

                // Extract sector from competency code.
                $sector = $exporter->extract_sector_from_competency($compcode);

                echo "{$quizname};{$num};{$questiontext};{$answers[0]};{$answers[1]};{$answers[2]};{$answers[3]};{$correct};{$sector};{$compcode};{$compdesc};{$difficulty}\n";
                $num++;
            }
        }

        exit;
    }
}

echo $OUTPUT->header();

// Page description.
echo html_writer::tag('p', get_string('quizexport_desc', 'local_competencyxmlimport'), ['class' => 'lead']);

?>
<style>
    .quiz-export-form {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid #dee2e6;
    }
    .quiz-export-form .form-group {
        margin-bottom: 20px;
    }
    .quiz-export-form label {
        font-weight: 600;
        margin-bottom: 8px;
        display: block;
    }
    .quiz-export-form select {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
    }
    .quiz-export-form select:focus {
        border-color: #0066cc;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }
    .btn-export {
        background: #0066cc;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        margin-right: 10px;
    }
    .btn-export:hover {
        background: #0052a3;
        color: white;
    }
    .btn-export-excel {
        background: #28a745;
    }
    .btn-export-excel:hover {
        background: #1e7e34;
    }
    .btn-export:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }
    .export-buttons {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #dee2e6;
    }

    /* Quiz list with checkboxes */
    .quiz-list {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        max-height: 400px;
        overflow-y: auto;
        margin-top: 10px;
    }
    .quiz-list-header {
        background: #e9ecef;
        padding: 12px 15px;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .quiz-list-header label {
        margin: 0;
        font-weight: 600;
    }
    .quiz-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        transition: background 0.2s;
    }
    .quiz-item:hover {
        background: #f8f9fa;
    }
    .quiz-item:last-child {
        border-bottom: none;
    }
    .quiz-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 12px;
        cursor: pointer;
    }
    .quiz-item label {
        margin: 0;
        cursor: pointer;
        flex: 1;
        font-weight: normal;
    }
    .quiz-item .quiz-info {
        color: #6c757d;
        font-size: 12px;
        margin-left: 10px;
    }
    .quiz-count {
        background: #0066cc;
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 13px;
    }
    .no-quizzes {
        padding: 30px;
        text-align: center;
        color: #6c757d;
    }

    /* Preview section */
    #quiz-preview {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 25px;
        margin-top: 20px;
        display: none;
    }
    #quiz-preview.visible {
        display: block;
    }
    .question-item {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .question-number {
        background: #0066cc;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 10px;
    }
    .question-text {
        font-size: 16px;
        margin: 15px 0;
        line-height: 1.6;
    }
    .answer-item {
        padding: 10px 15px;
        margin: 5px 0;
        border-radius: 6px;
        background: white;
        border: 1px solid #dee2e6;
    }
    .answer-item.correct {
        background: #d4edda;
        border-color: #28a745;
        color: #155724;
    }
    .answer-letter {
        font-weight: bold;
        margin-right: 10px;
        color: #6c757d;
    }
    .answer-item.correct .answer-letter {
        color: #155724;
    }
    .competency-badge {
        display: inline-block;
        background: #e9ecef;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        margin-top: 10px;
    }
    .competency-badge.has-comp {
        background: #cce5ff;
        color: #004085;
    }
    .difficulty-stars {
        color: #ffc107;
        margin-left: 10px;
    }
    .quiz-separator {
        background: #0066cc;
        color: white;
        padding: 10px 15px;
        margin: 20px 0;
        border-radius: 6px;
        font-weight: bold;
    }
    .print-header {
        display: none;
    }
    @media print {
        .quiz-export-form, .export-buttons, #region-main-settings-menu, .navbar, footer, #page-header {
            display: none !important;
        }
        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 30px;
        }
        .print-header h1 {
            margin-bottom: 10px;
        }
        #quiz-preview {
            display: block !important;
            border: none;
            padding: 0;
        }
        .question-item {
            page-break-inside: avoid;
        }
    }
</style>

<div class="quiz-export-form">
    <form id="export-form" method="get" action="<?php echo $CFG->wwwroot; ?>/local/competencyxmlimport/quiz_export.php">
        <input type="hidden" name="action" value="export">
        <input type="hidden" name="format" value="excel">

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="courseid"><?php echo get_string('selectcourse', 'local_competencyxmlimport'); ?></label>
                    <select name="courseid" id="courseid" onchange="loadCourseQuizzes(this.value)">
                        <option value="">-- <?php echo get_string('selectcourse', 'local_competencyxmlimport'); ?> --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course->id; ?>" <?php echo ($courseid == $course->id) ? 'selected' : ''; ?>>
                                <?php echo format_string($course->fullname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label><?php echo get_string('selectquiz', 'local_competencyxmlimport'); ?></label>
                    <div class="quiz-list" id="quiz-list">
                        <?php if ($courseid > 0 && !empty($quizzes)): ?>
                            <div class="quiz-list-header">
                                <label>
                                    <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                                    Seleziona tutti
                                </label>
                                <span class="quiz-count" id="selected-count">0 selezionati</span>
                            </div>
                            <?php foreach ($quizzes as $quiz): ?>
                                <div class="quiz-item">
                                    <input type="checkbox" name="quizids[]" value="<?php echo $quiz->id; ?>"
                                           id="quiz-<?php echo $quiz->id; ?>" class="quiz-checkbox"
                                           onchange="updateSelection()">
                                    <label for="quiz-<?php echo $quiz->id; ?>">
                                        <?php echo format_string($quiz->name); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($courseid > 0): ?>
                            <div class="no-quizzes">
                                <i class="fa fa-info-circle"></i> <?php echo get_string('noquizzes', 'local_competencyxmlimport'); ?>
                            </div>
                        <?php else: ?>
                            <div class="no-quizzes">
                                <i class="fa fa-arrow-left"></i> Seleziona prima un corso
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="export-buttons">
            <button type="button" class="btn-export" id="btn-preview" onclick="loadPreview()" disabled>
                <i class="fa fa-eye"></i> Anteprima
            </button>
            <button type="button" class="btn-export" id="btn-print" onclick="printQuiz()" disabled>
                <i class="fa fa-print"></i> <?php echo get_string('printbutton', 'local_competencyxmlimport'); ?>
            </button>
            <button type="submit" class="btn-export btn-export-excel" id="btn-excel" disabled>
                <i class="fa fa-file-excel-o"></i> <?php echo get_string('downloadexcel', 'local_competencyxmlimport'); ?>
            </button>
        </div>
    </form>
</div>

<div class="print-header">
    <h1 id="print-quiz-name"></h1>
    <p id="print-course-name"></p>
    <p id="print-date"></p>
</div>

<div id="quiz-preview">
    <h3><?php echo get_string('quizquestions', 'local_competencyxmlimport'); ?></h3>
    <p id="total-questions"></p>
    <div id="questions-container"></div>
</div>

<script>
var wwwroot = '<?php echo $CFG->wwwroot; ?>';
var sesskey = '<?php echo sesskey(); ?>';

function loadCourseQuizzes(courseid) {
    if (!courseid) {
        document.getElementById('quiz-list').innerHTML = '<div class="no-quizzes"><i class="fa fa-arrow-left"></i> Seleziona prima un corso</div>';
        updateButtons();
        return;
    }
    window.location.href = wwwroot + '/local/competencyxmlimport/quiz_export.php?courseid=' + courseid;
}

function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('.quiz-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
    updateSelection();
}

function updateSelection() {
    var checkboxes = document.querySelectorAll('.quiz-checkbox');
    var checked = document.querySelectorAll('.quiz-checkbox:checked');
    var countSpan = document.getElementById('selected-count');
    var selectAll = document.getElementById('select-all');

    if (countSpan) {
        countSpan.textContent = checked.length + ' selezionati';
    }

    if (selectAll) {
        selectAll.checked = (checked.length === checkboxes.length && checkboxes.length > 0);
    }

    updateButtons();
}

function updateButtons() {
    var checked = document.querySelectorAll('.quiz-checkbox:checked');
    var hasSelection = checked.length > 0;

    document.getElementById('btn-preview').disabled = !hasSelection;
    document.getElementById('btn-print').disabled = !hasSelection;
    document.getElementById('btn-excel').disabled = !hasSelection;
}

function getSelectedQuizIds() {
    var checked = document.querySelectorAll('.quiz-checkbox:checked');
    return Array.from(checked).map(function(cb) { return cb.value; });
}

function loadPreview() {
    var quizIds = getSelectedQuizIds();
    if (quizIds.length === 0) {
        alert('Seleziona almeno un quiz');
        return;
    }

    var container = document.getElementById('questions-container');
    container.innerHTML = '<p><i class="fa fa-spinner fa-spin"></i> Caricamento in corso...</p>';
    document.getElementById('quiz-preview').classList.add('visible');

    var courseName = document.getElementById('courseid').options[document.getElementById('courseid').selectedIndex].text;
    document.getElementById('print-course-name').textContent = 'Corso: ' + courseName;
    document.getElementById('print-date').textContent = 'Data: ' + new Date().toLocaleDateString('it-IT');

    // Load each quiz
    var promises = quizIds.map(function(quizid) {
        var url = wwwroot + '/local/competencyxmlimport/ajax_quiz_export.php?action=preview&quizid=' + quizid + '&sesskey=' + sesskey;
        console.log('Fetching:', url);

        return fetch(url)
            .then(function(response) {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status + ' for quiz ' + quizid);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('Data received for quiz ' + quizid + ':', data);
                if (!data.success) {
                    throw new Error(data.message || 'Errore sconosciuto per quiz ' + quizid);
                }
                return data;
            });
    });

    Promise.all(promises).then(function(results) {
        console.log('All results:', results);
        renderMultipleQuizzes(results);
    }).catch(function(error) {
        console.error('Error:', error);
        container.innerHTML = '<div class="alert alert-danger"><strong>Errore:</strong> ' + error.message + '<br><small>Controlla la console del browser per dettagli (F12)</small></div>';
    });
}

function renderMultipleQuizzes(results) {
    var container = document.getElementById('questions-container');
    container.innerHTML = '';

    var totalQuestions = 0;
    var quizNames = [];
    var letters = ['A', 'B', 'C', 'D'];

    results.forEach(function(data, idx) {
        if (!data.success) return;

        totalQuestions += data.questions.length;
        quizNames.push(data.quizname);

        // Quiz separator
        var separator = document.createElement('div');
        separator.className = 'quiz-separator';
        separator.innerHTML = '<i class="fa fa-file-text-o"></i> ' + data.quizname + ' (' + data.questions.length + ' domande)';
        container.appendChild(separator);

        data.questions.forEach(function(q, index) {
            var qDiv = document.createElement('div');
            qDiv.className = 'question-item';

            var html = '<div><span class="question-number">' + (index + 1) + '</span><strong>' + (q.name || 'Domanda ' + (index + 1)) + '</strong></div>';
            html += '<div class="question-text">' + q.questiontext + '</div>';

            html += '<div class="answers">';
            q.answers.forEach(function(ans, i) {
                var isCorrect = parseFloat(ans.fraction) > 0;
                html += '<div class="answer-item ' + (isCorrect ? 'correct' : '') + '">';
                html += '<span class="answer-letter">' + letters[i] + ')</span> ';
                html += ans.answer;
                if (isCorrect) {
                    html += ' <strong>&#10004;</strong>';
                }
                html += '</div>';
            });
            html += '</div>';

            if (q.competency_code) {
                // Extract sector from competency code (first part before underscore).
                var sector = q.competency_code.split('_')[0] || '';

                html += '<div class="competency-badge has-comp">';
                if (sector) {
                    html += '<strong>Settore:</strong> ' + sector + ' | ';
                }
                html += '<strong>Competenza:</strong> ' + q.competency_code;
                if (q.competency_description) {
                    html += ' - ' + q.competency_description;
                }
                var stars = '';
                for (var s = 0; s < (q.difficultylevel || 1); s++) {
                    stars += '&#9733;';
                }
                html += '<span class="difficulty-stars">' + stars + '</span>';
                html += '</div>';
            } else {
                html += '<div class="competency-badge">Nessuna competenza assegnata</div>';
            }

            qDiv.innerHTML = html;
            container.appendChild(qDiv);
        });
    });

    document.getElementById('print-quiz-name').textContent = quizNames.join(', ');
    document.getElementById('total-questions').textContent = 'Totale domande: ' + totalQuestions + ' (da ' + results.length + ' quiz)';
}

function printQuiz() {
    var quizIds = getSelectedQuizIds();
    if (quizIds.length === 0) {
        alert('Seleziona almeno un quiz');
        return;
    }

    // First load preview if not visible
    if (!document.getElementById('quiz-preview').classList.contains('visible')) {
        loadPreview();
        // Wait for content to load before printing
        setTimeout(function() {
            var container = document.getElementById('questions-container');
            if (container.querySelector('.question-item')) {
                window.print();
            } else {
                alert('Attendi il caricamento del contenuto prima di stampare');
            }
        }, 2000);
    } else {
        window.print();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelection();
});
</script>

<?php

echo $OUTPUT->footer();
