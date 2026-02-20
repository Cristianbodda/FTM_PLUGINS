<?php
/**
 * Admin Quiz Tester - Generate quiz attempts for specific students/quizzes.
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/admin_quiz_tester.php'));
$PAGE->set_title('Admin Quiz Tester');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$selected_userid = optional_param('userid', 0, PARAM_INT);
$selected_courseid = optional_param('courseid', 0, PARAM_INT);
$target_pct = optional_param('percentage', 65, PARAM_INT);
$cleanup_first = optional_param('cleanup', 0, PARAM_INT);

echo $OUTPUT->header();
?>
<style>
.aqt-container { max-width: 1000px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
.aqt-header { background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.aqt-header h2 { margin: 0; }
.aqt-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; }
.aqt-card-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; font-weight: 600; }
.aqt-card-body { padding: 20px; }
.aqt-form-row { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 15px; flex-wrap: wrap; }
.aqt-form-group { flex: 1; min-width: 200px; }
.aqt-form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #555; }
.aqt-form-group select, .aqt-form-group input { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
.aqt-form-group select:focus, .aqt-form-group input:focus { border-color: #6f42c1; outline: none; }
.aqt-btn { padding: 10px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
.aqt-btn-primary { background: #6f42c1; color: white; }
.aqt-btn-primary:hover { background: #5a32a3; }
.aqt-btn-danger { background: #dc3545; color: white; }
.aqt-btn-success { background: #28a745; color: white; }
.aqt-quiz-list { max-height: 400px; overflow-y: auto; }
.aqt-quiz-item { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; }
.aqt-quiz-item:hover { background: #f8f5ff; }
.aqt-quiz-item label { cursor: pointer; flex: 1; margin: 0; }
.aqt-log { background: #1e1e2e; color: #a6e3a1; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 13px; line-height: 1.8; max-height: 500px; overflow-y: auto; }
.aqt-log .error { color: #f38ba8; }
.aqt-log .success { color: #a6e3a1; }
.aqt-log .warn { color: #f9e2af; }
.aqt-select-all { padding: 8px 15px; background: #e8e0f5; border-radius: 6px; margin-bottom: 10px; }
</style>

<div class="aqt-container">
    <div class="aqt-header">
        <h2>🎯 Admin Quiz Tester</h2>
        <p style="margin:5px 0 0; opacity:0.9;">Genera tentativi quiz per studenti specifici con percentuale target</p>
    </div>

<?php
// Get all students (demo + test users).
$students = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname
    FROM {user} u
    WHERE (u.username LIKE 'demo_%' OR u.username LIKE 'ftm_test_%')
      AND u.deleted = 0
    ORDER BY u.lastname, u.firstname
");

// Get courses with quizzes.
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, COUNT(q.id) as quiz_count
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    GROUP BY c.id, c.fullname
    ORDER BY c.fullname
");

// ========================
// GENERATE ACTION
// ========================
if ($action === 'generate' && confirm_sesskey()) {
    $quiz_ids = optional_param_array('quizids', [], PARAM_INT);

    if (empty($selected_userid) || empty($quiz_ids)) {
        echo '<div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px;">Seleziona uno studente e almeno un quiz.</div>';
    } else {
        $user = $DB->get_record('user', ['id' => $selected_userid]);
        echo '<div class="aqt-card"><div class="aqt-card-header">📋 Log Generazione</div><div class="aqt-log">';
        echo "🎯 Studente: <strong>{$user->firstname} {$user->lastname}</strong> | Target: <strong>{$target_pct}%</strong><br>";

        $generated = 0;
        $cleaned_count = 0;

        foreach ($quiz_ids as $qid) {
            $quiz = $DB->get_record('quiz', ['id' => $qid]);
            if (!$quiz) {
                continue;
            }

            // Cleanup existing attempts if requested.
            if ($cleanup_first) {
                $old_attempts = $DB->get_records('quiz_attempts', ['quiz' => $qid, 'userid' => $selected_userid]);
                foreach ($old_attempts as $oa) {
                    $qas = $DB->get_records('question_attempts', ['questionusageid' => $oa->uniqueid]);
                    foreach ($qas as $qa) {
                        $steps = $DB->get_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
                        foreach ($steps as $step) {
                            $DB->delete_records('question_attempt_step_data', ['attemptstepid' => $step->id]);
                        }
                        $DB->delete_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
                    }
                    $DB->delete_records('question_attempts', ['questionusageid' => $oa->uniqueid]);
                    $DB->delete_records('question_usages', ['id' => $oa->uniqueid]);
                    $DB->delete_records('quiz_attempts', ['id' => $oa->id]);
                    $cleaned_count++;
                }
                $DB->delete_records('quiz_grades', ['quiz' => $qid, 'userid' => $selected_userid]);
            }

            // Skip if attempt exists and no cleanup.
            if (!$cleanup_first && $DB->record_exists('quiz_attempts', ['quiz' => $qid, 'userid' => $selected_userid, 'state' => 'finished'])) {
                echo "<span class='warn'>⏭️ {$quiz->name} - tentativo gia esistente (usa 'Pulisci prima')</span><br>";
                continue;
            }

            // Get questions.
            $questions = $DB->get_records_sql("
                SELECT DISTINCT qv.questionid, q.qtype, q.name, qs.slot, qs.maxmark
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.itemid = qs.id
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                WHERE qs.quizid = ?
                ORDER BY qs.slot
            ", [$qid]);

            if (empty($questions)) {
                echo "<span class='warn'>⚠️ {$quiz->name} - nessuna domanda</span><br>";
                continue;
            }

            try {
                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
                $ctx = context_module::instance($cm->id);

                // Ensure student is enrolled.
                $coursecontext = context_course::instance($quiz->course);
                if (!is_enrolled($coursecontext, $selected_userid)) {
                    $enrol = $DB->get_record('enrol', ['courseid' => $quiz->course, 'enrol' => 'manual', 'status' => 0]);
                    if ($enrol) {
                        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
                        if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $selected_userid])) {
                            $ue = new stdClass();
                            $ue->enrolid = $enrol->id;
                            $ue->userid = $selected_userid;
                            $ue->status = 0;
                            $ue->timestart = 0;
                            $ue->timeend = 0;
                            $ue->timecreated = time();
                            $ue->timemodified = time();
                            $DB->insert_record('user_enrolments', $ue);
                            role_assign($studentroleid, $selected_userid, $coursecontext->id);
                        }
                    }
                }

                $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $ctx);
                $quba->set_preferred_behaviour('deferredfeedback');

                foreach ($questions as $q) {
                    $question = question_bank::load_question($q->questionid);
                    $quba->add_question($question, $q->maxmark);
                }
                $quba->start_all_questions();
                question_engine::save_questions_usage_by_activity($quba);

                $attempt = new stdClass();
                $attempt->quiz = $quiz->id;
                $attempt->userid = $selected_userid;
                $attempt->attempt = $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $selected_userid]) + 1;
                $attempt->uniqueid = $quba->get_id();
                $layout_slots = [];
                foreach ($questions as $q) {
                    $layout_slots[] = $q->slot;
                }
                sort($layout_slots);
                $attempt->layout = implode(',', $layout_slots) . ',0';
                $attempt->currentpage = 0;
                $attempt->preview = 0;
                $attempt->state = 'inprogress';
                $attempt->timestart = time() - 3600;
                $attempt->timefinish = 0;
                $attempt->timemodified = time();
                $attempt->timemodifiedoffline = 0;
                $attempt->sumgrades = null;
                $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

                $target_correct = $target_pct / 100;
                $slot = 1;
                $correct_count = 0;
                $total_count = count($questions);

                foreach ($questions as $q) {
                    $rand = mt_rand(1, 100) / 100;
                    $is_correct = ($rand <= $target_correct);
                    $response = aqt_generate_response($q, $is_correct, $quba, $slot);
                    if ($response !== null) {
                        $quba->process_action($slot, $response);
                        if ($is_correct) {
                            $correct_count++;
                        }
                    }
                    $slot++;
                }

                $quba->finish_all_questions();
                question_engine::save_questions_usage_by_activity($quba);

                $attempt->state = 'finished';
                $attempt->timefinish = time();
                $attempt->sumgrades = $quba->get_total_mark();
                $DB->update_record('quiz_attempts', $attempt);

                quiz_save_best_grade($quiz, $selected_userid);

                $actual_pct = $total_count > 0 ? round(($correct_count / $total_count) * 100) : 0;
                echo "<span class='success'>✅ {$quiz->name} - {$correct_count}/{$total_count} corrette (~{$actual_pct}%)</span><br>";
                $generated++;

            } catch (Exception $e) {
                echo "<span class='error'>❌ {$quiz->name}: " . s($e->getMessage()) . "</span><br>";
            }
        }

        echo "<br><strong class='success'>🎉 Completato: {$generated} quiz generati" . ($cleaned_count > 0 ? ", {$cleaned_count} vecchi puliti" : "") . "</strong>";
        echo '</div></div>';
    }
}
?>

    <!-- FORM -->
    <div class="aqt-card">
        <div class="aqt-card-header">⚙️ Configurazione</div>
        <div class="aqt-card-body">
            <form method="post" action="">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="generate">

                <div class="aqt-form-row">
                    <div class="aqt-form-group">
                        <label>👤 Studente</label>
                        <select name="userid" required>
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php echo ($selected_userid == $s->id) ? 'selected' : ''; ?>>
                                <?php echo s($s->firstname . ' ' . $s->lastname) . ' (' . $s->username . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="aqt-form-group">
                        <label>🎯 Percentuale Target</label>
                        <input type="number" name="percentage" min="0" max="100" value="<?php echo $target_pct; ?>" style="max-width:120px;">
                    </div>

                    <div class="aqt-form-group" style="min-width:auto;">
                        <label>
                            <input type="checkbox" name="cleanup" value="1" <?php echo $cleanup_first ? 'checked' : ''; ?>>
                            🗑️ Pulisci prima
                        </label>
                    </div>
                </div>

                <div class="aqt-form-row">
                    <div class="aqt-form-group">
                        <label>📚 Filtra per Corso</label>
                        <select onchange="filterQuizzes(this.value)">
                            <option value="0">-- Tutti i corsi --</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c->id; ?>" <?php echo ($selected_courseid == $c->id) ? 'selected' : ''; ?>>
                                <?php echo s($c->fullname) . ' (' . $c->quiz_count . ' quiz)'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Quiz list -->
                <div style="margin-top:15px;">
                    <div class="aqt-select-all">
                        <label><input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)"> <strong>Seleziona tutti</strong></label>
                    </div>

                    <div class="aqt-quiz-list">
                        <?php
                        $all_quizzes = $DB->get_records_sql("
                            SELECT q.id, q.name, q.course, c.fullname as coursename,
                                   (SELECT COUNT(*) FROM {quiz_slots} qs WHERE qs.quizid = q.id) as qcount
                            FROM {quiz} q
                            JOIN {course} c ON c.id = q.course
                            WHERE c.id > 1
                            ORDER BY c.fullname, q.name
                        ");
                        foreach ($all_quizzes as $q):
                        ?>
                        <div class="aqt-quiz-item" data-courseid="<?php echo $q->course; ?>">
                            <input type="checkbox" name="quizids[]" value="<?php echo $q->id; ?>" class="quiz-cb" id="quiz_<?php echo $q->id; ?>">
                            <label for="quiz_<?php echo $q->id; ?>">
                                <strong><?php echo s($q->name); ?></strong>
                                <span style="color:#888; font-size:12px;"> — <?php echo s($q->coursename); ?> (<?php echo $q->qcount; ?> domande)</span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="aqt-btn aqt-btn-primary" style="font-size:16px; padding:12px 30px;">
                        ▶️ Genera Tentativi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p><a href="<?php echo (new moodle_url('/local/ftm_testsuite/index.php'))->out(); ?>">← Torna alla Test Suite</a>
     | <a href="<?php echo (new moodle_url('/local/ftm_testsuite/generate_coach_demo.php'))->out(); ?>">🎓 Genera Demo Coach</a></p>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.quiz-cb:not([style*="display: none"])').forEach(function(cb) {
        var item = cb.closest('.aqt-quiz-item');
        if (item.style.display !== 'none') {
            cb.checked = checked;
        }
    });
}

function filterQuizzes(courseid) {
    document.querySelectorAll('.aqt-quiz-item').forEach(function(item) {
        if (courseid == 0 || item.dataset.courseid == courseid) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
            item.querySelector('.quiz-cb').checked = false;
        }
    });
    document.getElementById('selectAll').checked = false;
}
</script>

<?php
echo $OUTPUT->footer();

/**
 * Generate response for a question.
 */
function aqt_generate_response($question, $correct, $quba, $slot) {
    global $DB;

    switch ($question->qtype) {
        case 'multichoice':
            $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
            $target_id = null;
            $fallback_id = null;
            foreach ($answers as $a) {
                if ($correct && $a->fraction > 0) {
                    $target_id = $a->id;
                    break;
                } else if (!$correct && $a->fraction == 0) {
                    $target_id = $a->id;
                    break;
                }
                $fallback_id = $a->id;
            }
            if (!$target_id) {
                $target_id = $fallback_id;
            }
            $qa = $quba->get_question_attempt($slot);
            $order = $qa->get_step(0)->get_qt_data();
            if (isset($order['_order'])) {
                $order_ids = explode(',', $order['_order']);
                $choice_index = array_search($target_id, $order_ids);
                if ($choice_index !== false) {
                    return ['answer' => $choice_index];
                }
            }
            return ['answer' => $target_id];

        case 'truefalse':
            $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
            foreach ($answers as $a) {
                if (($correct && $a->fraction > 0) || (!$correct && $a->fraction == 0)) {
                    return ['answer' => $a->id];
                }
            }
            break;

        case 'shortanswer':
            if ($correct) {
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                    [$question->questionid]
                );
                return $answer ? ['answer' => $answer->answer] : ['answer' => 'wrong'];
            }
            return ['answer' => 'wrong_' . rand(1000, 9999)];

        case 'numerical':
            if ($correct) {
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                    [$question->questionid]
                );
                return $answer ? ['answer' => $answer->answer] : ['answer' => '0'];
            }
            return ['answer' => '-999999'];
    }

    return ['-submit' => 1];
}
