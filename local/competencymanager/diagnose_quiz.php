<?php
/**
 * Diagnostic tool for quiz/competency issues
 * Usage: diagnose_quiz.php?userid=X or diagnose_quiz.php?quizname=meccanica
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/competencymanager:manage', $context);

$userid = optional_param('userid', 0, PARAM_INT);
$quizname = optional_param('quizname', '', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_quiz.php'));
$PAGE->set_title('Diagnosi Quiz/Competenze');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo '<h2>🔍 Diagnosi Quiz e Competenze</h2>';

// Style
echo '<style>
.diag-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
.diag-section h3 { margin-top: 0; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
.diag-table { width: 100%; border-collapse: collapse; }
.diag-table th, .diag-table td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
.diag-table th { background: #f5f5f5; }
.diag-ok { color: #28a745; font-weight: bold; }
.diag-warn { color: #ffc107; font-weight: bold; }
.diag-error { color: #dc3545; font-weight: bold; }
.diag-info { background: #e7f3ff; padding: 15px; border-radius: 6px; margin: 10px 0; }
</style>';

// Form
echo '<div class="diag-section">';
echo '<h3>Parametri Ricerca</h3>';
echo '<form method="get" style="display: flex; gap: 20px; flex-wrap: wrap;">';
echo '<div><label>User ID: <input type="number" name="userid" value="' . $userid . '" style="padding: 8px;"></label></div>';
echo '<div><label>Nome Quiz (parziale): <input type="text" name="quizname" value="' . s($quizname) . '" placeholder="es: meccanica" style="padding: 8px;"></label></div>';
echo '<div><button type="submit" style="padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 6px;">Cerca</button></div>';
echo '</form>';
echo '</div>';

if ($userid || $quizname) {

    // 1. SEARCH QUIZZES
    echo '<div class="diag-section">';
    echo '<h3>1. Quiz Trovati</h3>';

    $quizParams = [];
    $quizWhere = "1=1";

    if ($quizname) {
        $quizWhere .= " AND q.name LIKE :quizname";
        $quizParams['quizname'] = '%' . $quizname . '%';
    }

    $quizSql = "SELECT q.id, q.name, q.course, c.shortname as coursename, c.fullname as coursefullname,
                       (SELECT COUNT(*) FROM {quiz_attempts} qa WHERE qa.quiz = q.id) as total_attempts,
                       (SELECT COUNT(*) FROM {quiz_attempts} qa WHERE qa.quiz = q.id AND qa.state = 'finished') as finished_attempts
                FROM {quiz} q
                JOIN {course} c ON c.id = q.course
                WHERE $quizWhere
                ORDER BY q.name";

    $quizzes = $DB->get_records_sql($quizSql, $quizParams);

    if (empty($quizzes)) {
        echo '<p class="diag-error">Nessun quiz trovato con questi criteri.</p>';
    } else {
        echo '<table class="diag-table">';
        echo '<tr><th>Quiz ID</th><th>Nome Quiz</th><th>Corso</th><th>Tentativi Totali</th><th>Completati</th><th>Azioni</th></tr>';
        foreach ($quizzes as $q) {
            $statusClass = $q->finished_attempts > 0 ? 'diag-ok' : 'diag-warn';
            echo '<tr>';
            echo '<td>' . $q->id . '</td>';
            echo '<td><strong>' . s($q->name) . '</strong></td>';
            echo '<td>' . s($q->coursename) . ' (' . $q->course . ')</td>';
            echo '<td>' . $q->total_attempts . '</td>';
            echo '<td class="' . $statusClass . '">' . $q->finished_attempts . '</td>';
            echo '<td><a href="?userid=' . $userid . '&quizname=' . urlencode($q->name) . '">Dettagli</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';

    // 2. CHECK COMPETENCIES ON QUIZ QUESTIONS
    if (!empty($quizzes)) {
        echo '<div class="diag-section">';
        echo '<h3>2. Competenze Assegnate alle Domande</h3>';

        foreach ($quizzes as $quiz) {
            echo '<h4>Quiz: ' . s($quiz->name) . ' (ID: ' . $quiz->id . ')</h4>';

            // Get questions in this quiz
            $questionSql = "SELECT qs.id as slotid, qs.slot, qs.questionid, q.name as questionname, q.qtype,
                                   qcbq.competencyid, qcbq.difficultylevel, comp.idnumber, comp.shortname as compname
                            FROM {quiz_slots} qs
                            JOIN {question_references} qr ON qr.itemid = qs.id
                                AND qr.component = 'mod_quiz'
                                AND qr.questionarea = 'slot'
                            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                            JOIN {question} q ON q.id = qv.questionid
                            LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                            LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                            WHERE qs.quizid = :quizid
                            ORDER BY qs.slot";

            $questions = $DB->get_records_sql($questionSql, ['quizid' => $quiz->id]);

            if (empty($questions)) {
                // Try alternative method for older Moodle
                $questionSql2 = "SELECT qs.id as slotid, qs.slot, qs.questionid, q.name as questionname, q.qtype,
                                        qcbq.competencyid, qcbq.difficultylevel, comp.idnumber, comp.shortname as compname
                                 FROM {quiz_slots} qs
                                 JOIN {question} q ON q.id = qs.questionid
                                 LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                                 LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                                 WHERE qs.quizid = :quizid
                                 ORDER BY qs.slot";
                $questions = $DB->get_records_sql($questionSql2, ['quizid' => $quiz->id]);
            }

            $withComp = 0;
            $withoutComp = 0;

            echo '<table class="diag-table">';
            echo '<tr><th>Slot</th><th>Domanda</th><th>Tipo</th><th>Competenza</th><th>Livello</th></tr>';

            foreach ($questions as $q) {
                $hasComp = !empty($q->competencyid);
                if ($hasComp) $withComp++; else $withoutComp++;

                $compClass = $hasComp ? 'diag-ok' : 'diag-error';
                $compText = $hasComp ? ($q->idnumber . ' - ' . $q->compname) : '<em>NESSUNA</em>';
                $levelText = $hasComp ? ('Liv. ' . ($q->difficultylevel ?: 1)) : '-';

                echo '<tr>';
                echo '<td>' . $q->slot . '</td>';
                echo '<td>' . s(substr($q->questionname, 0, 50)) . (strlen($q->questionname) > 50 ? '...' : '') . '</td>';
                echo '<td>' . $q->qtype . '</td>';
                echo '<td class="' . $compClass . '">' . $compText . '</td>';
                echo '<td>' . $levelText . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            echo '<div class="diag-info">';
            echo '<strong>Riepilogo:</strong> ' . count($questions) . ' domande totali, ';
            echo '<span class="diag-ok">' . $withComp . ' con competenza</span>, ';
            echo '<span class="' . ($withoutComp > 0 ? 'diag-error' : 'diag-ok') . '">' . $withoutComp . ' senza competenza</span>';

            if ($withoutComp > 0) {
                echo '<br><br><strong class="diag-error">PROBLEMA RILEVATO:</strong> Ci sono ' . $withoutComp . ' domande senza competenza assegnata. ';
                echo 'Queste domande NON appariranno nel report studente.';
                echo '<br><br><strong>Soluzione:</strong> Vai al Question Bank, seleziona le domande e assegna le competenze usando il plugin "Competencies by Question".';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    // 3. CHECK USER ATTEMPTS
    if ($userid) {
        echo '<div class="diag-section">';
        echo '<h3>3. Tentativi Utente (ID: ' . $userid . ')</h3>';

        $user = $DB->get_record('user', ['id' => $userid]);
        if ($user) {
            echo '<p><strong>Utente:</strong> ' . fullname($user) . ' (' . $user->email . ')</p>';
        }

        $attemptWhere = "qa.userid = :userid";
        $attemptParams = ['userid' => $userid];

        if ($quizname) {
            $attemptWhere .= " AND q.name LIKE :quizname";
            $attemptParams['quizname'] = '%' . $quizname . '%';
        }

        $attemptSql = "SELECT qa.id, qa.quiz, qa.attempt, qa.state, qa.timefinish, qa.sumgrades,
                              q.name as quizname, q.grade as maxgrade, q.course, c.shortname as coursename
                       FROM {quiz_attempts} qa
                       JOIN {quiz} q ON q.id = qa.quiz
                       JOIN {course} c ON c.id = q.course
                       WHERE $attemptWhere
                       ORDER BY qa.timefinish DESC";

        $attempts = $DB->get_records_sql($attemptSql, $attemptParams);

        if (empty($attempts)) {
            echo '<p class="diag-error">Nessun tentativo trovato per questo utente' . ($quizname ? ' con questo quiz' : '') . '.</p>';
        } else {
            echo '<table class="diag-table">';
            echo '<tr><th>Attempt ID</th><th>Quiz</th><th>Corso</th><th>Tentativo #</th><th>Stato</th><th>Data Fine</th><th>Voto</th></tr>';

            foreach ($attempts as $a) {
                $stateClass = $a->state === 'finished' ? 'diag-ok' : 'diag-warn';
                $dateStr = $a->timefinish ? date('d/m/Y H:i', $a->timefinish) : '-';
                $gradeStr = $a->sumgrades !== null ? round($a->sumgrades, 2) . '/' . $a->maxgrade : '-';

                echo '<tr>';
                echo '<td>' . $a->id . '</td>';
                echo '<td><strong>' . s($a->quizname) . '</strong></td>';
                echo '<td>' . s($a->coursename) . ' (' . $a->course . ')</td>';
                echo '<td>' . $a->attempt . '</td>';
                echo '<td class="' . $stateClass . '">' . $a->state . '</td>';
                echo '<td>' . $dateStr . '</td>';
                echo '<td>' . $gradeStr . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Check if finished attempts have competency data
            $finishedAttempts = array_filter($attempts, fn($a) => $a->state === 'finished');
            if (!empty($finishedAttempts)) {
                echo '<h4>Dettaglio Competenze nei Tentativi Completati</h4>';

                foreach ($finishedAttempts as $attempt) {
                    echo '<details style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
                    echo '<summary style="cursor: pointer; font-weight: bold;">' . s($attempt->quizname) . ' - Tentativo ' . $attempt->attempt . ' (' . date('d/m/Y', $attempt->timefinish) . ')</summary>';

                    // Get questions with competencies for this attempt
                    $qSql = "SELECT qat.id, qat.slot, q.name as questionname,
                                    COALESCE((SELECT MAX(fraction) FROM {question_attempt_steps} qas2
                                              WHERE qas2.questionattemptid = qat.id AND qas2.fraction IS NOT NULL), 0) as fraction,
                                    qcbq.competencyid, comp.idnumber, comp.shortname as compname
                             FROM {quiz_attempts} qa
                             JOIN {question_usages} qu ON qu.id = qa.uniqueid
                             JOIN {question_attempts} qat ON qat.questionusageid = qu.id
                             JOIN {question} q ON q.id = qat.questionid
                             LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                             LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                             WHERE qa.id = :attemptid
                             ORDER BY qat.slot";

                    $attemptQuestions = $DB->get_records_sql($qSql, ['attemptid' => $attempt->id]);

                    $withCompetency = 0;
                    $withoutCompetency = 0;

                    echo '<table class="diag-table" style="margin-top: 10px;">';
                    echo '<tr><th>Slot</th><th>Domanda</th><th>Risultato</th><th>Competenza</th></tr>';

                    foreach ($attemptQuestions as $aq) {
                        $hasComp = !empty($aq->competencyid);
                        if ($hasComp) $withCompetency++; else $withoutCompetency++;

                        $resultPercent = round($aq->fraction * 100) . '%';
                        $resultClass = $aq->fraction >= 0.5 ? 'diag-ok' : 'diag-error';
                        $compClass = $hasComp ? '' : 'diag-error';
                        $compText = $hasComp ? $aq->idnumber : '<em>NESSUNA</em>';

                        echo '<tr>';
                        echo '<td>' . $aq->slot . '</td>';
                        echo '<td>' . s(substr($aq->questionname, 0, 40)) . '</td>';
                        echo '<td class="' . $resultClass . '">' . $resultPercent . '</td>';
                        echo '<td class="' . $compClass . '">' . $compText . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';

                    if ($withoutCompetency > 0) {
                        echo '<p class="diag-error" style="margin-top: 10px;"><strong>PROBLEMA:</strong> ' . $withoutCompetency . ' domande senza competenza - non appariranno nel report!</p>';
                    } else {
                        echo '<p class="diag-ok" style="margin-top: 10px;">Tutte le ' . $withCompetency . ' domande hanno competenze assegnate.</p>';
                    }

                    echo '</details>';
                }
            }
        }
        echo '</div>';
    }

    // 4. SUMMARY AND RECOMMENDATIONS
    echo '<div class="diag-section">';
    echo '<h3>4. Riepilogo e Raccomandazioni</h3>';
    echo '<div class="diag-info">';
    echo '<p><strong>Checklist per il corretto funzionamento del Report:</strong></p>';
    echo '<ol>';
    echo '<li>Le domande del quiz devono avere <strong>competenze assegnate</strong> nel Question Bank</li>';
    echo '<li>Lo studente deve aver <strong>completato</strong> il quiz (stato = finished)</li>';
    echo '<li>Il report deve essere aperto con il <strong>corso corretto</strong> nel parametro courseid</li>';
    echo '<li>Le competenze devono avere un <strong>idnumber valido</strong> (es: MECCANICA_DT_01)</li>';
    echo '</ol>';
    echo '</div>';
    echo '</div>';
}

echo $OUTPUT->footer();
