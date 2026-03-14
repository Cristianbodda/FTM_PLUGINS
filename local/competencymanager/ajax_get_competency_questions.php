<?php
/**
 * AJAX endpoint per ottenere le domande quiz mappate a una competenza specifica
 * Gestisce courseid=0 (tutti i corsi) e question versioning Moodle 4.x
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/competencymanager:view', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $competencyid = required_param('competencyid', PARAM_INT);
    $studentid = required_param('studentid', PARAM_INT);
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $quizids = optional_param_array('quizids', [], PARAM_INT);

    global $DB;

    // Verifica che la tabella qbank esista
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('qbank_competenciesbyquestion')) {
        echo json_encode(['success' => true, 'data' => ['questions' => []]]);
        die();
    }

    // Costruisci filtro quiz: usa quizids se forniti, altrimenti courseid, altrimenti tutti
    $quizCondition = '';
    $params = ['userid' => $studentid, 'competencyid' => $competencyid];

    if (!empty($quizids)) {
        list($quizinsql, $quizparams) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');
        $quizCondition = "AND qa.quiz $quizinsql";
        $params = array_merge($params, $quizparams);
    } elseif ($courseid > 0) {
        $quizzes = $DB->get_records('quiz', ['course' => $courseid], '', 'id');
        if (empty($quizzes)) {
            echo json_encode(['success' => true, 'data' => ['questions' => []]]);
            die();
        }
        list($quizinsql, $quizparams) = $DB->get_in_or_equal(array_keys($quizzes), SQL_PARAMS_NAMED, 'quiz');
        $quizCondition = "AND qa.quiz $quizinsql";
        $params = array_merge($params, $quizparams);
    }
    // Se courseid=0 e nessun quizids: cerca in tutti i quiz (nessun filtro)

    // Query con lo stesso pattern di join del report_generator::get_attempt_question_results
    $sql = "SELECT
                qat.id as attemptquestionid,
                qat.questionid,
                qat.rightanswer,
                qat.responsesummary,
                qat.maxmark,
                q.questiontext,
                q.name as questionname,
                qcbq.difficultylevel,
                qa.id as attemptid,
                qa.timefinish,
                COALESCE(
                    (SELECT MAX(qas2.fraction)
                     FROM {question_attempt_steps} qas2
                     WHERE qas2.questionattemptid = qat.id
                     AND qas2.fraction IS NOT NULL),
                    0
                ) as fraction
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question} q ON q.id = qat.questionid
            JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
            WHERE qa.userid = :userid
              AND qa.state = 'finished'
              AND qcbq.competencyid = :competencyid
              $quizCondition
            ORDER BY qa.timefinish DESC, qat.slot ASC";

    $records = $DB->get_records_sql($sql, $params);

    // Fallback versioning: se nessun risultato, prova via question_versions
    if (empty($records) && $dbman->table_exists('question_versions')) {
        $params2 = ['userid' => $studentid, 'competencyid' => $competencyid];
        $quizCondition2 = '';
        if (!empty($quizids)) {
            list($quizinsql2, $quizparams2) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');
            $quizCondition2 = "AND qa.quiz $quizinsql2";
            $params2 = array_merge($params2, $quizparams2);
        } elseif ($courseid > 0) {
            list($quizinsql2, $quizparams2) = $DB->get_in_or_equal(array_keys($quizzes), SQL_PARAMS_NAMED, 'quiz');
            $quizCondition2 = "AND qa.quiz $quizinsql2";
            $params2 = array_merge($params2, $quizparams2);
        }

        $sql = "SELECT
                    qat.id as attemptquestionid,
                    qat.questionid,
                    qat.rightanswer,
                    qat.responsesummary,
                    qat.maxmark,
                    q.questiontext,
                    q.name as questionname,
                    qcbq.difficultylevel,
                    qa.id as attemptid,
                    qa.timefinish,
                    COALESCE(
                        (SELECT MAX(qas2.fraction)
                         FROM {question_attempt_steps} qas2
                         WHERE qas2.questionattemptid = qat.id
                         AND qas2.fraction IS NOT NULL),
                        0
                    ) as fraction
                FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qat ON qat.questionusageid = qu.id
                JOIN {question} q ON q.id = qat.questionid
                JOIN {question_versions} qv1 ON qv1.questionid = q.id
                JOIN {question_versions} qv2 ON qv2.questionbankentryid = qv1.questionbankentryid
                JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = qv2.questionid
                WHERE qa.userid = :userid
                  AND qa.state = 'finished'
                  AND qcbq.competencyid = :competencyid
                  $quizCondition2
                ORDER BY qa.timefinish DESC, qat.slot ASC";

        $records = $DB->get_records_sql($sql, $params2);
    }

    // Deduplica per questionid (tieni solo dal tentativo piu recente)
    $questions = [];
    $seenQuestionIds = [];

    foreach ($records as $rec) {
        if (in_array($rec->questionid, $seenQuestionIds)) {
            continue;
        }
        $seenQuestionIds[] = $rec->questionid;

        $iscorrect = ($rec->fraction !== null && (float)$rec->fraction > 0);
        $questions[] = [
            'questiontext' => strip_tags(substr($rec->questiontext ?? '', 0, 200)),
            'questionname' => $rec->questionname,
            'studentanswer' => $rec->responsesummary ?? '-',
            'correctanswer' => $rec->rightanswer ?? '-',
            'iscorrect' => $iscorrect,
            'reviewurl' => (new moodle_url('/mod/quiz/review.php', [
                'attempt' => $rec->attemptid
            ]))->out(false),
            'difficultylevel' => $rec->difficultylevel ? (int)$rec->difficultylevel : null,
        ];
    }

    echo json_encode(['success' => true, 'data' => ['questions' => $questions]]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
