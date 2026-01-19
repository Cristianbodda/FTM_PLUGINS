<?php
/**
 * Script per ricollegare le domande ai quiz.
 * Eseguire UNA SOLA VOLTA dopo aver perso i collegamenti.
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/fix_quiz_slots.php'));
$PAGE->set_title('Fix Quiz Slots');
$PAGE->set_heading('Ricollegamento Domande ai Quiz');

$execute = optional_param('execute', 0, PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<h2>Ricollegamento Domande ai Quiz</h2>';

// Trova tutti i quiz senza slot
$empty_quizzes = $DB->get_records_sql("
    SELECT q.id, q.name, q.course, c.shortname as coursename
    FROM {quiz} q
    JOIN {course} c ON c.id = q.course
    LEFT JOIN {quiz_slots} qs ON qs.quizid = q.id
    WHERE qs.id IS NULL
    ORDER BY q.name
");

// Trova tutte le categorie con domande
$categories_with_questions = $DB->get_records_sql("
    SELECT
        qc.id,
        qc.name,
        qc.contextid,
        COUNT(qbe.id) as question_count
    FROM {question_categories} qc
    JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'multichoice'
    GROUP BY qc.id, qc.name, qc.contextid
    HAVING question_count > 0
    ORDER BY qc.name
");

/**
 * Funzione per normalizzare i nomi per il confronto
 */
function normalize_name($name) {
    // Rimuovi date tipo _20260113_131754
    $name = preg_replace('/_\d{8}_\d{6}/', '', $name);
    // Rimuovi numeri di versione tipo _V2_3
    $name = preg_replace('/_V\d+_\d+/', '', $name);
    // Converti in minuscolo
    $name = strtolower($name);
    // Rimuovi caratteri speciali
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

/**
 * Trova la migliore corrispondenza categoria per un quiz
 */
function find_best_category_match($quiz_name, $categories) {
    $quiz_normalized = normalize_name($quiz_name);
    $best_match = null;
    $best_score = 0;

    foreach ($categories as $cat) {
        $cat_normalized = normalize_name($cat->name);

        // Calcola similarità
        similar_text($quiz_normalized, $cat_normalized, $percent);

        // Bonus se contiene parti chiave
        if (strpos($cat_normalized, $quiz_normalized) !== false ||
            strpos($quiz_normalized, $cat_normalized) !== false) {
            $percent += 20;
        }

        if ($percent > $best_score && $percent > 50) {
            $best_score = $percent;
            $best_match = $cat;
        }
    }

    return $best_match ? ['category' => $best_match, 'score' => $best_score] : null;
}

/**
 * Collega le domande di una categoria a un quiz
 */
function link_questions_to_quiz($quizid, $categoryid) {
    global $DB;

    // Ottieni tutte le domande della categoria
    $questions = $DB->get_records_sql("
        SELECT
            qbe.id as entryid,
            q.id as questionid,
            q.name,
            q.defaultmark
        FROM {question_bank_entries} qbe
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q ON q.id = qv.questionid
        WHERE qbe.questioncategoryid = :categoryid
        AND q.qtype = 'multichoice'
        ORDER BY q.name
    ", ['categoryid' => $categoryid]);

    if (empty($questions)) {
        return 0;
    }

    $slot = 1;
    $page = 1;
    $questions_per_page = 5;
    $count = 0;

    foreach ($questions as $question) {
        // Verifica se lo slot esiste già
        $existing = $DB->get_record_sql("
            SELECT qs.id
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz'
                AND qr.questionarea = 'slot'
            WHERE qs.quizid = :quizid
            AND qr.questionbankentryid = :entryid
        ", ['quizid' => $quizid, 'entryid' => $question->entryid]);

        if ($existing) {
            continue; // Già collegata
        }

        // Crea lo slot
        $slotrecord = new stdClass();
        $slotrecord->quizid = $quizid;
        $slotrecord->slot = $slot;
        $slotrecord->page = $page;
        $slotrecord->maxmark = $question->defaultmark ?: 1.0;
        $slotrecord->id = $DB->insert_record('quiz_slots', $slotrecord);

        // Crea il riferimento alla domanda
        $reference = new stdClass();
        $reference->usingcontextid = context_module::instance(
            $DB->get_field('course_modules', 'id', [
                'instance' => $quizid,
                'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])
            ])
        )->id;
        $reference->component = 'mod_quiz';
        $reference->questionarea = 'slot';
        $reference->itemid = $slotrecord->id;
        $reference->questionbankentryid = $question->entryid;
        $reference->version = null; // Usa sempre l'ultima versione
        $DB->insert_record('question_references', $reference);

        $slot++;
        $count++;

        // Nuova pagina ogni N domande
        if (($slot - 1) % $questions_per_page == 0) {
            $page++;
        }
    }

    // Aggiorna il quiz
    if ($count > 0) {
        $DB->set_field('quiz', 'sumgrades', $count, ['id' => $quizid]);
    }

    return $count;
}

// Modalità esecuzione
if ($execute && $quizid) {
    // Esegui per un singolo quiz
    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    $match = find_best_category_match($quiz->name, $categories_with_questions);

    if ($match) {
        $linked = link_questions_to_quiz($quizid, $match['category']->id);
        echo "<div class='alert alert-success'>";
        echo "Quiz <strong>{$quiz->name}</strong>: collegate {$linked} domande dalla categoria <strong>{$match['category']->name}</strong>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>Nessuna categoria trovata per: {$quiz->name}</div>";
    }

} elseif ($execute == 2) {
    // Esegui per TUTTI i quiz
    echo "<h3>Esecuzione in corso...</h3>";

    $total_linked = 0;
    $quizzes_fixed = 0;

    foreach ($empty_quizzes as $quiz) {
        $match = find_best_category_match($quiz->name, $categories_with_questions);

        if ($match) {
            $linked = link_questions_to_quiz($quiz->id, $match['category']->id);
            if ($linked > 0) {
                echo "<div class='alert alert-success' style='padding:5px;margin:3px 0;'>";
                echo "✓ <strong>{$quiz->name}</strong>: {$linked} domande da <em>{$match['category']->name}</em> (match: {$match['score']}%)";
                echo "</div>";
                $total_linked += $linked;
                $quizzes_fixed++;
            }
        } else {
            echo "<div class='alert alert-warning' style='padding:5px;margin:3px 0;'>";
            echo "⚠ <strong>{$quiz->name}</strong>: nessuna categoria corrispondente trovata";
            echo "</div>";
        }
    }

    echo "<hr>";
    echo "<div class='alert alert-info'>";
    echo "<strong>Completato!</strong><br>";
    echo "Quiz ripristinati: {$quizzes_fixed}<br>";
    echo "Domande collegate: {$total_linked}";
    echo "</div>";

    echo "<p><strong>IMPORTANTE:</strong> Svuota la cache di Moodle dopo questa operazione!</p>";
    echo "<a href='{$CFG->wwwroot}/admin/purgecaches.php' class='btn btn-primary'>Svuota Cache</a>";

} else {
    // Mostra anteprima
    echo "<h3>Quiz senza domande: " . count($empty_quizzes) . "</h3>";
    echo "<h3>Categorie con domande: " . count($categories_with_questions) . "</h3>";

    echo "<h3>Anteprima Mappatura</h3>";
    echo "<table class='table table-striped'>";
    echo "<tr><th>Quiz</th><th>Corso</th><th>Categoria Match</th><th>Domande</th><th>Score</th><th>Azione</th></tr>";

    $can_fix = 0;
    foreach ($empty_quizzes as $quiz) {
        $match = find_best_category_match($quiz->name, $categories_with_questions);

        echo "<tr>";
        echo "<td>" . s($quiz->name) . "</td>";
        echo "<td>" . s($quiz->coursename) . "</td>";

        if ($match) {
            $can_fix++;
            echo "<td style='color:green;'>" . s($match['category']->name) . "</td>";
            echo "<td>{$match['category']->question_count}</td>";
            echo "<td>{$match['score']}%</td>";
            echo "<td><a href='?execute=1&quizid={$quiz->id}' class='btn btn-sm btn-success'>Fix questo</a></td>";
        } else {
            echo "<td style='color:red;'>Nessuna corrispondenza</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
        }
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<p><strong>Quiz che possono essere ripristinati automaticamente: {$can_fix}</strong></p>";

    if ($can_fix > 0) {
        echo "<a href='?execute=2' class='btn btn-lg btn-danger' onclick=\"return confirm('Sei sicuro di voler ricollegare TUTTI i quiz?');\">ESEGUI FIX PER TUTTI ({$can_fix} quiz)</a>";
    }
}

echo $OUTPUT->footer();
