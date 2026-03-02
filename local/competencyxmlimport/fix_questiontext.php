<?php
/**
 * One-time fix: clean questiontext and answer HTML in existing questions.
 *
 * Removes unnecessary <p> wrapping and trailing <br> tags that cause
 * large gaps between question text and answer options in quiz display.
 *
 * Usage: Navigate to /local/competencyxmlimport/fix_questiontext.php
 * - First run shows preview (dry run)
 * - Click "Applica Fix" to execute the cleanup
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/competencyxmlimport/lib.php');

require_login();

if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', 'Solo admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/fix_questiontext.php'));
$PAGE->set_title('Fix Question Text HTML');
$PAGE->set_heading('Fix Question Text HTML');

$apply = optional_param('apply', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<h3>Fix HTML nei testi delle domande quiz</h3>';
echo '<p>Questo strumento rimuove i tag <code>&lt;p&gt;</code> e <code>&lt;br&gt;</code> inutili
      dal testo delle domande e delle risposte, che causano spazi vuoti eccessivi nella visualizzazione dei quiz.</p>';

// Find questions with <p> wrapping.
$sql_questions = "SELECT q.id, q.name, q.questiontext
                    FROM {question} q
                   WHERE q.qtype = 'multichoice'
                     AND (q.questiontext LIKE '<p>%</p>'
                          OR q.questiontext LIKE '<p>%</p>%<br%'
                          OR q.questiontext LIKE '%<br>%'
                          OR q.questiontext LIKE '%<br/>%'
                          OR q.questiontext LIKE '%<br />%')";

$questions = $DB->get_records_sql($sql_questions);

// Find answers with <p> wrapping.
$sql_answers = "SELECT qa.id, qa.question, qa.answer, q.name as question_name
                  FROM {question_answers} qa
                  JOIN {question} q ON q.id = qa.question
                 WHERE q.qtype = 'multichoice'
                   AND (qa.answer LIKE '<p>%</p>'
                        OR qa.answer LIKE '%<br>%'
                        OR qa.answer LIKE '%<br/>%'
                        OR qa.answer LIKE '%<br />%')";

$answers = $DB->get_records_sql($sql_answers);

$totalq = count($questions);
$totala = count($answers);

echo "<div style='background:#f0f0f0; padding:15px; border-radius:8px; margin:15px 0;'>";
echo "<strong>Trovate:</strong> {$totalq} domande e {$totala} risposte con HTML da pulire.";
echo "</div>";

if ($totalq == 0 && $totala == 0) {
    echo '<div class="alert alert-success">Nessuna domanda da correggere. Tutto OK!</div>';
    echo $OUTPUT->footer();
    die();
}

if ($apply && confirm_sesskey()) {
    // ============================================================
    // APPLY FIX
    // ============================================================
    $fixedq = 0;
    $fixeda = 0;

    foreach ($questions as $q) {
        $cleaned = local_competencyxmlimport_clean_questiontext($q->questiontext);
        if ($cleaned !== $q->questiontext) {
            $DB->set_field('question', 'questiontext', $cleaned, ['id' => $q->id]);
            $fixedq++;
        }
    }

    foreach ($answers as $a) {
        $cleaned = local_competencyxmlimport_clean_questiontext($a->answer);
        if ($cleaned !== $a->answer) {
            $DB->set_field('question_answers', 'answer', $cleaned, ['id' => $a->id]);
            $fixeda++;
        }
    }

    // Purge question cache.
    cache_helper::purge_by_event('questionupdated');

    echo "<div class='alert alert-success' style='font-size:16px;'>";
    echo "<strong>Fix applicato!</strong><br>";
    echo "Domande corrette: <strong>{$fixedq}</strong> / {$totalq}<br>";
    echo "Risposte corrette: <strong>{$fixeda}</strong> / {$totala}<br>";
    echo "Cache domande purgata.";
    echo "</div>";

    echo '<p><a href="' . new moodle_url('/local/competencyxmlimport/fix_questiontext.php') . '" class="btn btn-secondary">Verifica di nuovo</a></p>';

} else {
    // ============================================================
    // PREVIEW (DRY RUN)
    // ============================================================
    echo '<h4>Anteprima domande da correggere (prime 20):</h4>';

    echo '<table class="table table-sm table-bordered" style="font-size:13px;">';
    echo '<thead><tr><th>ID</th><th>Nome</th><th>Testo attuale</th><th>Testo corretto</th></tr></thead>';
    echo '<tbody>';

    $count = 0;
    foreach ($questions as $q) {
        if ($count >= 20) {
            break;
        }
        $cleaned = local_competencyxmlimport_clean_questiontext($q->questiontext);
        if ($cleaned === $q->questiontext) {
            continue; // No actual change.
        }

        $original = s(substr($q->questiontext, 0, 120));
        $fixed = s(substr($cleaned, 0, 120));

        echo '<tr>';
        echo '<td>' . $q->id . '</td>';
        echo '<td>' . s(substr($q->name, 0, 50)) . '</td>';
        echo '<td style="background:#ffe0e0;"><code>' . $original . '...</code></td>';
        echo '<td style="background:#e0ffe0;"><code>' . $fixed . '...</code></td>';
        echo '</tr>';
        $count++;
    }

    if ($count == 0) {
        echo '<tr><td colspan="4">Nessuna modifica effettiva necessaria per le domande.</td></tr>';
    }
    echo '</tbody></table>';

    if ($totala > 0) {
        echo '<h4>Anteprima risposte da correggere (prime 10):</h4>';
        echo '<table class="table table-sm table-bordered" style="font-size:13px;">';
        echo '<thead><tr><th>ID</th><th>Domanda</th><th>Risposta attuale</th><th>Risposta corretta</th></tr></thead>';
        echo '<tbody>';

        $count = 0;
        foreach ($answers as $a) {
            if ($count >= 10) {
                break;
            }
            $cleaned = local_competencyxmlimport_clean_questiontext($a->answer);
            if ($cleaned === $a->answer) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . $a->id . '</td>';
            echo '<td>' . s(substr($a->question_name, 0, 40)) . '</td>';
            echo '<td style="background:#ffe0e0;"><code>' . s($a->answer) . '</code></td>';
            echo '<td style="background:#e0ffe0;"><code>' . s($cleaned) . '</code></td>';
            echo '</tr>';
            $count++;
        }
        echo '</tbody></table>';
    }

    // Apply button.
    echo '<form method="post" style="margin-top:20px;">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<button type="submit" class="btn btn-danger btn-lg" onclick="return confirm(\'Sei sicuro? Verra\' modificato il testo di ' . $totalq . ' domande e ' . $totala . ' risposte.\');">';
    echo 'Applica Fix (' . ($totalq + $totala) . ' record)';
    echo '</button>';
    echo ' <a href="' . new moodle_url('/local/competencyxmlimport/setup_universale.php') . '" class="btn btn-secondary btn-lg">Annulla</a>';
    echo '</form>';
}

echo $OUTPUT->footer();
