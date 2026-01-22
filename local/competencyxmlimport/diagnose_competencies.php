<?php
/**
 * Script diagnostico per analizzare problemi competenze
 *
 * @package    local_competencyxmlimport
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/diagnose_competencies.php'));
$PAGE->set_title(get_string('pluginname', 'local_competencyxmlimport') . ' - Diagnosi');
$PAGE->set_heading(get_string('pluginname', 'local_competencyxmlimport'));

echo $OUTPUT->header();

echo '<h2>🔍 Diagnosi Completa Competenze</h2>';

// 1. Conta framework e competenze
echo '<h3>1. Framework e Competenze</h3>';
$frameworks = $DB->get_records('competency_framework', [], 'id', 'id, shortname, idnumber');
echo '<table class="table table-bordered">';
echo '<tr><th>ID</th><th>Nome</th><th>IDNumber</th><th>Competenze</th></tr>';
foreach ($frameworks as $fw) {
    $count = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
    echo "<tr><td>{$fw->id}</td><td>{$fw->shortname}</td><td>{$fw->idnumber}</td><td>{$count}</td></tr>";
}
echo '</table>';

// 2. Domande totali vs con competenza assegnata
echo '<h3>2. Situazione Domande</h3>';

$total_questions = $DB->count_records('question');
$total_qbe = $DB->count_records('question_bank_entries');

// Domande con competenza in qbank_competenciesbyquestion
$with_comp_assigned = $DB->count_records_sql("
    SELECT COUNT(DISTINCT qbc.questionid)
    FROM {qbank_competenciesbyquestion} qbc
    JOIN {question} q ON q.id = qbc.questionid
");

// Question bank entries con idnumber popolato
$qbe_with_idnumber = $DB->count_records_sql("
    SELECT COUNT(*) FROM {question_bank_entries}
    WHERE idnumber IS NOT NULL AND idnumber != ''
");

// Domande con competenza MA senza idnumber nel QBE
$missing_idnumber = $DB->get_records_sql("
    SELECT COUNT(*) as cnt
    FROM {question_bank_entries} qbe
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {question} q ON q.id = qv.questionid
    JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
    WHERE (qbe.idnumber IS NULL OR qbe.idnumber = '')
");
$missing_count = reset($missing_idnumber)->cnt;

echo '<table class="table table-bordered">';
echo '<tr><th>Metrica</th><th>Valore</th><th>Note</th></tr>';
echo "<tr><td>Domande totali (question)</td><td>{$total_questions}</td><td></td></tr>";
echo "<tr><td>Question Bank Entries</td><td>{$total_qbe}</td><td></td></tr>";
echo "<tr><td>Domande con competenza assegnata (qbc)</td><td>{$with_comp_assigned}</td><td>In qbank_competenciesbyquestion</td></tr>";
echo "<tr><td>QBE con idnumber popolato</td><td>{$qbe_with_idnumber}</td><td>Quello che vede la Test Suite</td></tr>";
echo "<tr class='table-danger'><td><strong>Domande con comp. ma SENZA idnumber</strong></td><td><strong>{$missing_count}</strong></td><td>⚠️ DA CORREGGERE</td></tr>";
echo '</table>';

// 3. Analisi competenze assegnate
echo '<h3>3. Competenze Assegnate nelle Domande</h3>';

// Ottieni tutti gli ID competenza usati nelle domande
$used_comp_ids = $DB->get_records_sql("
    SELECT DISTINCT qbc.competencyid, c.idnumber, c.shortname, c.competencyframeworkid
    FROM {qbank_competenciesbyquestion} qbc
    LEFT JOIN {competency} c ON c.id = qbc.competencyid
    ORDER BY qbc.competencyid
");

$found = 0;
$not_found = 0;
$orphan_ids = [];

foreach ($used_comp_ids as $uc) {
    if ($uc->idnumber) {
        $found++;
    } else {
        $not_found++;
        $orphan_ids[] = $uc->competencyid;
    }
}

echo "<p>Competenze distinte usate nelle domande: <strong>" . count($used_comp_ids) . "</strong></p>";
echo "<p>✅ Con idnumber (trovate nel framework): <strong>{$found}</strong></p>";
echo "<p>❌ Senza idnumber (orfane/eliminate): <strong>{$not_found}</strong></p>";

if ($not_found > 0) {
    echo '<div class="alert alert-danger">';
    echo '<strong>⚠️ Competenze orfane!</strong> Questi ID competenza sono usati nelle domande ma non esistono in nessun framework:<br>';
    echo '<code>' . implode(', ', array_slice($orphan_ids, 0, 20));
    if (count($orphan_ids) > 20) {
        echo ' ... e altri ' . (count($orphan_ids) - 20);
    }
    echo '</code></div>';
}

// 4. Analisi per settore (basato su idnumber delle competenze)
echo '<h3>4. Distribuzione per Settore</h3>';

$sectors = ['AUTOMOBILE', 'MECCANICA', 'LOGISTICA', 'ELETTRICITÀ', 'ELETTRICITA', 'AUTOMAZIONE', 'METALCOSTRUZIONE', 'CHIMFARM'];

echo '<table class="table table-bordered">';
echo '<tr><th>Settore</th><th>Comp. nel Framework</th><th>Domande con idnumber</th><th>Domande senza idnumber</th></tr>';

foreach ($sectors as $sector) {
    // Competenze nel framework per questo settore
    $fw_count = $DB->count_records_sql("
        SELECT COUNT(*) FROM {competency}
        WHERE idnumber LIKE ?
    ", [$sector . '_%']);

    // QBE con idnumber di questo settore
    $qbe_with = $DB->count_records_sql("
        SELECT COUNT(*) FROM {question_bank_entries}
        WHERE idnumber LIKE ?
    ", [$sector . '_%']);

    // Domande assegnate a competenze di questo settore MA senza idnumber
    $qbe_without = $DB->count_records_sql("
        SELECT COUNT(DISTINCT qbe.id)
        FROM {question_bank_entries} qbe
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q ON q.id = qv.questionid
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
        JOIN {competency} c ON c.id = qbc.competencyid
        WHERE c.idnumber LIKE ?
        AND (qbe.idnumber IS NULL OR qbe.idnumber = '')
    ", [$sector . '_%']);

    $class = ($qbe_without > 0) ? 'table-warning' : '';
    echo "<tr class='{$class}'><td>{$sector}</td><td>{$fw_count}</td><td>{$qbe_with}</td><td>{$qbe_without}</td></tr>";
}
echo '</table>';

// 5. Verifica cosa succede con il fix
echo '<h3>5. Simulazione Fix</h3>';

// Ottieni mappa competenze
$all_competencies = $DB->get_records('competency', [], '', 'id, idnumber, competencyframeworkid');
$comp_map = [];
foreach ($all_competencies as $c) {
    if (!empty($c->idnumber)) {
        $comp_map[$c->id] = $c->idnumber;
    }
}

// Trova domande da fixare
$to_fix = $DB->get_records_sql("
    SELECT qbe.id as qbe_id, qbe.questioncategoryid, qbc.competencyid
    FROM {question_bank_entries} qbe
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {question} q ON q.id = qv.questionid
    JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
    WHERE (qbe.idnumber IS NULL OR qbe.idnumber = '')
");

// Precarica idnumber esistenti per categoria
$existing = $DB->get_records_sql("
    SELECT id, questioncategoryid, idnumber
    FROM {question_bank_entries}
    WHERE idnumber IS NOT NULL AND idnumber != ''
");
$used = [];
foreach ($existing as $e) {
    $key = $e->questioncategoryid . '-' . $e->idnumber;
    $used[$key] = true;
}

$can_fix = 0;
$no_comp = 0;
$would_dup = 0;

foreach ($to_fix as $q) {
    if (!isset($comp_map[$q->competencyid])) {
        $no_comp++;
        continue;
    }

    $key = $q->questioncategoryid . '-' . $comp_map[$q->competencyid];
    if (isset($used[$key])) {
        $would_dup++;
    } else {
        $can_fix++;
        $used[$key] = true;
    }
}

echo '<table class="table table-bordered">';
echo '<tr><th>Status</th><th>Conteggio</th><th>Spiegazione</th></tr>';
echo "<tr class='table-success'><td>✅ Correggibili</td><td><strong>{$can_fix}</strong></td><td>Riceveranno l'idnumber</td></tr>";
echo "<tr class='table-danger'><td>❌ Competenza orfana</td><td>{$no_comp}</td><td>L'ID competenza non esiste in nessun framework</td></tr>";
echo "<tr class='table-warning'><td>⏭️ Duplicato</td><td>{$would_dup}</td><td>Stessa competenza già presente nella categoria (normale)</td></tr>";
echo "<tr><td><strong>Totale da processare</strong></td><td>" . count($to_fix) . "</td><td></td></tr>";
echo '</table>';

// 6. ANALISI DOMANDE NEI QUIZ (come fa la Test Suite)
echo '<h3>6. Domande nei Quiz (come vede la Test Suite)</h3>';

// Domande totali nei quiz
$quiz_total = $DB->get_record_sql("
    SELECT COUNT(DISTINCT qv.questionid) as total
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
");

// Domande nei quiz con competenze (qualsiasi framework)
$quiz_with_any_comp = $DB->get_record_sql("
    SELECT COUNT(DISTINCT qv.questionid) as total
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
");

// Domande nei quiz con competenze framework 11
$quiz_with_fw11 = $DB->get_record_sql("
    SELECT COUNT(DISTINCT qv.questionid) as total
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
    JOIN {competency} c ON c.id = qbc.competencyid
    WHERE c.competencyframeworkid = 11
");

// Domande nei quiz con competenze framework vecchi (1 o 9)
$quiz_with_old_fw = $DB->get_record_sql("
    SELECT COUNT(DISTINCT qv.questionid) as total
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
    JOIN {competency} c ON c.id = qbc.competencyid
    WHERE c.competencyframeworkid IN (1, 9)
");

// Domande nei quiz SENZA competenze
$quiz_without_comp = $DB->get_record_sql("
    SELECT COUNT(DISTINCT qv.questionid) as total
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = qv.questionid
    WHERE qbc.id IS NULL
");

echo '<table class="table table-bordered">';
echo '<tr><th>Metrica</th><th>Valore</th><th>% su totale</th></tr>';
echo "<tr><td><strong>Domande totali nei quiz</strong></td><td><strong>{$quiz_total->total}</strong></td><td>100%</td></tr>";
echo "<tr class='table-success'><td>Con competenze (qualsiasi framework)</td><td>{$quiz_with_any_comp->total}</td><td>" . round($quiz_with_any_comp->total / $quiz_total->total * 100, 1) . "%</td></tr>";
echo "<tr class='table-info'><td>→ Framework 11 (attuale)</td><td>{$quiz_with_fw11->total}</td><td>" . round($quiz_with_fw11->total / $quiz_total->total * 100, 1) . "%</td></tr>";
echo "<tr class='table-warning'><td>→ Framework vecchi (1, 9)</td><td>{$quiz_with_old_fw->total}</td><td>" . round($quiz_with_old_fw->total / $quiz_total->total * 100, 1) . "%</td></tr>";
echo "<tr class='table-danger'><td><strong>SENZA competenze</strong></td><td><strong>{$quiz_without_comp->total}</strong></td><td>" . round($quiz_without_comp->total / $quiz_total->total * 100, 1) . "%</td></tr>";
echo '</table>';

// Dettaglio per quiz
echo '<h4>Dettaglio per Quiz</h4>';
$quizzes = $DB->get_records_sql("
    SELECT q.id, q.name,
           COUNT(DISTINCT qv.questionid) as total_q,
           (SELECT COUNT(DISTINCT qv2.questionid)
            FROM {quiz_slots} qs2
            JOIN {question_references} qr2 ON qr2.itemid = qs2.id AND qr2.component = 'mod_quiz'
            JOIN {question_bank_entries} qbe2 ON qbe2.id = qr2.questionbankentryid
            JOIN {question_versions} qv2 ON qv2.questionbankentryid = qbe2.id
            JOIN {qbank_competenciesbyquestion} qbc2 ON qbc2.questionid = qv2.questionid
            JOIN {competency} c2 ON c2.id = qbc2.competencyid AND c2.competencyframeworkid = 11
            WHERE qs2.quizid = q.id) as with_comp
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    GROUP BY q.id, q.name
    ORDER BY q.name
");

echo '<table class="table table-sm">';
echo '<tr><th>Quiz</th><th>Domande</th><th>Con Comp (FW11)</th><th>%</th><th>Status</th></tr>';
foreach ($quizzes as $quiz) {
    $pct = $quiz->total_q > 0 ? round($quiz->with_comp / $quiz->total_q * 100, 1) : 0;
    $status = $pct == 100 ? '✅' : ($pct >= 80 ? '⚠️' : '❌');
    $class = $pct == 100 ? 'table-success' : ($pct >= 80 ? 'table-warning' : '');
    echo "<tr class='{$class}'><td>" . substr($quiz->name, 0, 50) . "</td><td>{$quiz->total_q}</td><td>{$quiz->with_comp}</td><td>{$pct}%</td><td>{$status}</td></tr>";
}
echo '</table>';

// 7. Link alle azioni
echo '<h3>7. Azioni Disponibili</h3>';

echo '<div class="btn-group" role="group">';
echo '<a href="fix_missing_idnumbers_v2.php" class="btn btn-primary">🔧 Esegui Fix v2</a> ';
echo '<a href="' . $CFG->wwwroot . '/local/ftm_testsuite/agent_tests.php" class="btn btn-secondary">📊 Test Suite</a>';
echo '</div>';

if ($no_comp > 0) {
    echo '<div class="alert alert-warning mt-3">';
    echo '<strong>⚠️ Nota:</strong> Ci sono ' . $no_comp . ' domande con competenze orfane. ';
    echo 'Queste domande hanno un ID competenza che non esiste più in nessun framework. ';
    echo 'Potrebbero essere state importate con un vecchio framework poi eliminato.';
    echo '</div>';
}

echo $OUTPUT->footer();
