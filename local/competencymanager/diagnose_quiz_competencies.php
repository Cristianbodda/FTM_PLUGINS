<?php
/**
 * Diagnostic tool to see quiz competencies and sector mapping
 * Usage: diagnose_quiz_competencies.php?userid=X
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();
$context = context_system::instance();
require_capability('local/competencymanager:manage', $context);

$userid = required_param('userid', PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_quiz_competencies.php'));
$PAGE->set_title('Diagnosi Competenze Quiz');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

$user = $DB->get_record('user', ['id' => $userid]);
echo '<h2>Diagnosi Competenze Quiz - ' . fullname($user) . '</h2>';

echo '<style>
.diag-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
.diag-section h3 { margin-top: 0; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
.diag-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.diag-table th, .diag-table td { padding: 6px 10px; border: 1px solid #ddd; text-align: left; }
.diag-table th { background: #f5f5f5; }
.diag-ok { color: #28a745; font-weight: bold; }
.diag-warn { color: #ffc107; font-weight: bold; }
.diag-error { color: #dc3545; font-weight: bold; }
</style>';

// 1. Quiz completati con competenze
echo '<div class="diag-section">';
echo '<h3>1. Quiz Completati - Competenze e Settori Rilevati</h3>';

$sql = "SELECT DISTINCT
            q.id as quizid, q.name as quizname,
            c.idnumber as comp_idnumber, c.shortname as comp_name,
            qv.questionid
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {question_references} qr ON qr.itemid = qs.id
             AND qr.component = 'mod_quiz'
             AND qr.questionarea = 'slot'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {qbank_competenciesbyquestion} cq ON cq.questionid = qv.questionid
        JOIN {competency} c ON c.id = cq.competencyid
        WHERE qa.userid = :userid
          AND qa.state = 'finished'
        ORDER BY q.name, c.idnumber";

$comps = $DB->get_records_sql($sql, ['userid' => $userid]);

if (empty($comps)) {
    echo '<p class="diag-error">Nessuna competenza trovata nei quiz completati!</p>';

    // Prova query alternativa senza question_references (per quiz vecchi)
    echo '<h4>Tentativo con query legacy...</h4>';

    $sql_legacy = "SELECT DISTINCT
                q.id as quizid, q.name as quizname,
                c.idnumber as comp_idnumber, c.shortname as comp_name,
                qs.questionid
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            JOIN {quiz_slots} qs ON qs.quizid = q.id
            LEFT JOIN {qbank_competenciesbyquestion} cq ON cq.questionid = qs.questionid
            LEFT JOIN {competency} c ON c.id = cq.competencyid
            WHERE qa.userid = :userid
              AND qa.state = 'finished'
            ORDER BY q.name, c.idnumber";

    $comps = $DB->get_records_sql($sql_legacy, ['userid' => $userid]);

    if (!empty($comps)) {
        echo '<p class="diag-ok">Trovate competenze con query legacy!</p>';
    }
}

if (!empty($comps)) {
    // Raggruppa per quiz
    $quizzes = [];
    foreach ($comps as $c) {
        if (!isset($quizzes[$c->quizid])) {
            $quizzes[$c->quizid] = [
                'name' => $c->quizname,
                'competencies' => [],
                'sectors' => []
            ];
        }
        if (!empty($c->comp_idnumber)) {
            $sector = extract_sector_from_idnumber($c->comp_idnumber);
            $quizzes[$c->quizid]['competencies'][] = $c->comp_idnumber;
            if ($sector && $sector !== 'UNKNOWN') {
                $quizzes[$c->quizid]['sectors'][$sector] = true;
            }
        }
    }

    foreach ($quizzes as $qid => $qdata) {
        echo '<h4>Quiz: ' . s($qdata['name']) . ' (ID: ' . $qid . ')</h4>';

        $sectors = array_keys($qdata['sectors']);
        if (empty($sectors)) {
            echo '<p class="diag-error">Nessun settore rilevato!</p>';
        } else {
            echo '<p><strong>Settori rilevati:</strong> ';
            foreach ($sectors as $sec) {
                $class = in_array(strtoupper($sec), ['MECCANICA', 'ELETTRICITÀ', 'AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'LOGISTICA', 'METALCOSTRUZIONE']) ? 'diag-ok' : 'diag-warn';
                echo '<span class="' . $class . '">' . $sec . '</span> ';
            }
            echo '</p>';
        }

        echo '<table class="diag-table">';
        echo '<tr><th>Competenza IDNumber</th><th>Settore Estratto</th></tr>';
        foreach ($qdata['competencies'] as $idnumber) {
            $extracted = extract_sector_from_idnumber($idnumber);
            $class = ($extracted && $extracted !== 'UNKNOWN') ? 'diag-ok' : 'diag-error';
            echo '<tr>';
            echo '<td>' . s($idnumber) . '</td>';
            echo '<td class="' . $class . '">' . ($extracted ?: 'NESSUNO') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
echo '</div>';

// 2. Verifica funzione extract_sector_from_idnumber
echo '<div class="diag-section">';
echo '<h3>2. Test Funzione extract_sector_from_idnumber()</h3>';

$test_cases = [
    'MECCANICA_A_01' => 'MECCANICA',
    'MECCANICA_DT_01' => 'MECCANICA',
    'ELETTRICITÀ_B_02' => 'ELETTRICITÀ',
    'ELETTRICITA_C_03' => 'ELETTRICITÀ',
    'AUTOMOBILE_A_01' => 'AUTOMOBILE',
    'CHIMFARM_D_05' => 'CHIMFARM',
    'GEN_F_01' => 'GENERICO',
    'GENERICO_A_01' => 'GENERICO',
    'OLD_MECCANICA_01' => 'MECCANICA',
];

echo '<table class="diag-table">';
echo '<tr><th>Input IDNumber</th><th>Atteso</th><th>Risultato</th><th>Status</th></tr>';
foreach ($test_cases as $input => $expected) {
    $result = extract_sector_from_idnumber($input);
    $match = (strtoupper($result) === strtoupper($expected));
    $class = $match ? 'diag-ok' : 'diag-error';
    echo '<tr>';
    echo '<td>' . s($input) . '</td>';
    echo '<td>' . s($expected) . '</td>';
    echo '<td class="' . $class . '">' . s($result ?: 'NULL') . '</td>';
    echo '<td class="' . $class . '">' . ($match ? '✅' : '❌') . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

// 3. Settori salvati nella tabella
echo '<div class="diag-section">';
echo '<h3>3. Settori Salvati in local_student_sectors</h3>';

$saved = $DB->get_records('local_student_sectors', ['userid' => $userid]);

if (empty($saved)) {
    echo '<p class="diag-warn">Nessun settore salvato nella tabella.</p>';
} else {
    echo '<table class="diag-table">';
    echo '<tr><th>Settore</th><th>Quiz Count (tabella)</th><th>Is Primary</th><th>Course ID</th></tr>';
    foreach ($saved as $s) {
        $primaryClass = $s->is_primary ? 'diag-ok' : '';
        echo '<tr>';
        echo '<td><strong>' . s($s->sector) . '</strong></td>';
        echo '<td>' . $s->quiz_count . '</td>';
        echo '<td class="' . $primaryClass . '">' . ($s->is_primary ? '⭐ SI' : 'No') . '</td>';
        echo '<td>' . ($s->courseid ?? 0) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

// 4. Risultato funzione aggiornata
echo '<div class="diag-section">';
echo '<h3>4. Risultato get_student_sectors_with_quiz_data() (AGGIORNATO)</h3>';

require_once(__DIR__ . '/classes/sector_manager.php');
$calculated = \local_competencymanager\sector_manager::get_student_sectors_with_quiz_data($userid);

if (empty($calculated)) {
    echo '<p class="diag-error">Nessun settore calcolato!</p>';
} else {
    echo '<table class="diag-table">';
    echo '<tr><th>Settore</th><th>Quiz Count (calcolato)</th><th>Is Primary</th></tr>';
    foreach ($calculated as $c) {
        $primaryClass = $c->is_primary ? 'diag-ok' : '';
        $countClass = $c->quiz_count > 0 ? 'diag-ok' : 'diag-warn';
        echo '<tr>';
        echo '<td><strong>' . s($c->sector) . '</strong></td>';
        echo '<td class="' . $countClass . '">' . $c->quiz_count . '</td>';
        echo '<td class="' . $primaryClass . '">' . ($c->is_primary ? '⭐ SI' : 'No') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
echo '</div>';

echo $OUTPUT->footer();
