<?php
/**
 * Diagnostica autovalutazione per uno studente specifico.
 * USO: /local/selfassessment/diagnose_risto.php?userid=26
 *
 * Mostra: settore primario, quiz completati, competenze trovate,
 * filtro settore applicato, stato local_selfassessment_assign.
 *
 * @package    local_selfassessment
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = optional_param('userid', 26, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/selfassessment/diagnose_risto.php', ['userid' => $userid]);
$PAGE->set_title('Diagnostica autovalutazione');
$PAGE->set_heading('Diagnostica autovalutazione - userid ' . $userid);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
echo '<h2>Utente: ' . fullname($user) . ' (id=' . $user->id . ', username=' . s($user->username) . ')</h2>';

// Helper: replica la logica di observer::get_student_primary_sector().
function diag_get_primary_sector($userid) {
    global $DB;
    if (!$DB->get_manager()->table_exists('local_student_sectors')) {
        return null;
    }
    $rec = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
    return $rec ? $rec->sector : null;
}

// Helper: replica la logica di observer::get_competency_sector().
function diag_get_competency_sector($competencyid) {
    global $DB;
    $c = $DB->get_record('competency', ['id' => $competencyid]);
    if (!$c || empty($c->idnumber)) {
        return null;
    }
    $parts = explode('_', $c->idnumber);
    if (!empty($parts[0])) {
        $sector = strtoupper($parts[0]);
        return str_replace(['À','È','É','Ì','Ò','Ù'], ['A','E','E','I','O','U'], $sector);
    }
    return null;
}

// 1. Settore primario
echo '<h3>1. Settore primario (local_student_sectors)</h3>';
if ($DB->get_manager()->table_exists('local_student_sectors')) {
    $sectors = $DB->get_records('local_student_sectors', ['userid' => $userid]);
    if ($sectors) {
        echo '<pre>';
        foreach ($sectors as $s) {
            print_r($s);
        }
        echo '</pre>';
    } else {
        echo '<p><em>Nessun record in local_student_sectors per questo utente.</em></p>';
    }
} else {
    echo '<p><em>Tabella local_student_sectors non esiste.</em></p>';
}

$primarySector = diag_get_primary_sector($userid);
echo '<p><strong>Settore primario rilevato:</strong> ' . ($primarySector ?: '<em>NESSUNO</em>') . '</p>';

// 2. Quiz completati
echo '<h3>2. Quiz attempts completati</h3>';
$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.uniqueid, qa.state, qa.preview, qa.timemodified, q.name AS quizname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ? AND qa.state = 'finished'
    ORDER BY qa.timemodified DESC
", [$userid]);

echo '<p>Totale tentativi finished: <strong>' . count($attempts) . '</strong></p>';
echo '<table class="generaltable"><thead><tr><th>ID</th><th>Quiz</th><th>Preview</th><th>Data</th></tr></thead><tbody>';
foreach ($attempts as $a) {
    echo '<tr><td>' . $a->id . '</td><td>' . s($a->quizname) . '</td><td>' . ($a->preview ? 'SI' : 'NO') . '</td><td>' . userdate($a->timemodified) . '</td></tr>';
}
echo '</tbody></table>';

// 3. Tabella mapping
echo '<h3>3. Tabella mapping competenze-domande</h3>';
$comp_tables = ['qbank_competenciesbyquestion', 'qbank_comp_question', 'local_competencymanager_qcomp'];
$comp_table_found = null;
foreach ($comp_tables as $t) {
    if ($DB->get_manager()->table_exists($t)) {
        $count = $DB->count_records($t);
        echo '<p>' . $t . ': <strong>' . $count . '</strong> record</p>';
        if (!$comp_table_found) {
            $comp_table_found = $t;
        }
    } else {
        echo '<p>' . $t . ': <em>non esiste</em></p>';
    }
}
echo '<p><strong>Tabella usata dall\'observer:</strong> ' . ($comp_table_found ?: 'NESSUNA') . '</p>';

if (!$comp_table_found) {
    echo '<p class="alert alert-danger">Nessuna tabella mapping trovata. Impossibile diagnosticare.</p>';
    echo $OUTPUT->footer();
    die();
}

// 4. Per ogni attempt, mostra competenze trovate e filtro settore
echo '<h3>4. Analisi competenze per ogni attempt</h3>';

$all_competencies_found = [];
$all_competencies_kept = [];
$all_competencies_filtered = [];

foreach ($attempts as $a) {
    echo '<h4>Attempt ' . $a->id . ' - ' . s($a->quizname) . '</h4>';

    // Questions
    $questions = $DB->get_records_sql("
        SELECT DISTINCT qa.questionid
        FROM {question_attempts} qa
        WHERE qa.questionusageid = ?
    ", [$a->uniqueid]);

    if (empty($questions)) {
        echo '<p><em>Nessuna domanda trovata (uniqueid=' . $a->uniqueid . ')</em></p>';
        continue;
    }

    $qids = array_keys($questions);
    echo '<p>Domande: ' . count($qids) . ' → IDs: ' . implode(', ', array_slice($qids, 0, 10)) . (count($qids) > 10 ? '...' : '') . '</p>';

    // Match diretto
    list($sql_in, $params) = $DB->get_in_or_equal($qids);
    $direct = $DB->get_records_sql("
        SELECT DISTINCT competencyid
        FROM {" . $comp_table_found . "}
        WHERE questionid $sql_in
    ", $params);
    echo '<p>Match diretto: <strong>' . count($direct) . '</strong> competenze</p>';

    // Versioning fallback
    if (empty($direct) && $DB->get_manager()->table_exists('question_versions')) {
        $all_versions = $DB->get_records_sql("
            SELECT DISTINCT qv2.questionid
            FROM {question_versions} qv1
            JOIN {question_versions} qv2 ON qv2.questionbankentryid = qv1.questionbankentryid
            WHERE qv1.questionid $sql_in
        ", $params);

        if (!empty($all_versions)) {
            $allids = array_keys($all_versions);
            list($sql_in2, $params2) = $DB->get_in_or_equal($allids);
            $direct = $DB->get_records_sql("
                SELECT DISTINCT competencyid
                FROM {" . $comp_table_found . "}
                WHERE questionid $sql_in2
            ", $params2);
            echo '<p>Versioning fallback: <strong>' . count($direct) . '</strong> competenze (da ' . count($allids) . ' version IDs)</p>';
        }
    }

    if (empty($direct)) {
        echo '<p class="alert alert-warning">Nessuna competenza mappata a queste domande.</p>';
        continue;
    }

    // Per ogni competenza, mostra idnumber e settore
    echo '<table class="generaltable"><thead><tr><th>Comp ID</th><th>IDNumber</th><th>Settore</th><th>Primary Sector</th><th>Filtrata?</th></tr></thead><tbody>';
    foreach ($direct as $m) {
        $comp = $DB->get_record('competency', ['id' => $m->competencyid]);
        if (!$comp) { continue; }

        $all_competencies_found[$m->competencyid] = $comp;

        $compSector = diag_get_competency_sector($m->competencyid);
        $generic = ['GEN', 'GENERICO', 'GENERICHE', 'TRASVERSALI'];

        $filtered = false;
        if (!empty($primarySector) && !empty($compSector)
            && $compSector !== $primarySector
            && !in_array($compSector, $generic)) {
            $filtered = true;
            $all_competencies_filtered[$m->competencyid] = $comp;
        } else {
            $all_competencies_kept[$m->competencyid] = $comp;
        }

        echo '<tr>';
        echo '<td>' . $m->competencyid . '</td>';
        echo '<td>' . s($comp->idnumber) . '</td>';
        echo '<td>' . s($compSector ?: '?') . '</td>';
        echo '<td>' . s($primarySector ?: '(nessuno)') . '</td>';
        echo '<td>' . ($filtered ? '<strong style="color:red">SI</strong>' : 'no') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// 5. Riepilogo
echo '<h3>5. Riepilogo</h3>';
echo '<ul>';
echo '<li>Competenze totali trovate dai quiz: <strong>' . count($all_competencies_found) . '</strong></li>';
echo '<li>Competenze che passerebbero il filtro: <strong style="color:green">' . count($all_competencies_kept) . '</strong></li>';
echo '<li>Competenze filtrate (settore mismatch): <strong style="color:red">' . count($all_competencies_filtered) . '</strong></li>';
echo '</ul>';

// 6. Stato attuale tabelle
echo '<h3>6. Stato attuale DB per userid=' . $userid . '</h3>';
$assign_count = $DB->count_records('local_selfassessment_assign', ['userid' => $userid]);
$sa_count = $DB->count_records('local_selfassessment', ['userid' => $userid]);
echo '<ul>';
echo '<li>local_selfassessment_assign: <strong>' . $assign_count . '</strong> record</li>';
echo '<li>local_selfassessment (autovalutazioni salvate): <strong>' . $sa_count . '</strong> record</li>';
echo '</ul>';

if ($sa_count > 0) {
    echo '<h4>Autovalutazioni salvate (dal vecchio o nuovo sistema):</h4>';
    $sas = $DB->get_records('local_selfassessment', ['userid' => $userid]);
    echo '<table class="generaltable"><thead><tr><th>Comp ID</th><th>IDNumber</th><th>Livello</th><th>Data</th></tr></thead><tbody>';
    foreach ($sas as $sa) {
        $comp = $DB->get_record('competency', ['id' => $sa->competencyid]);
        echo '<tr>';
        echo '<td>' . $sa->competencyid . '</td>';
        echo '<td>' . s($comp->idnumber ?? '?') . '</td>';
        echo '<td>' . $sa->level . '</td>';
        echo '<td>' . userdate($sa->timecreated) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo $OUTPUT->footer();
