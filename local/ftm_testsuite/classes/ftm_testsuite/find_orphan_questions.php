<?php
/**
 * Script per trovare le domande orfane (senza competenze)
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/find_orphan_questions.php'));
$PAGE->set_title('Domande Orfane - FTM Test Suite');
$PAGE->set_heading('Domande Orfane (senza competenze)');
$PAGE->set_pagelayout('admin');

$frameworkid = optional_param('frameworkid', 9, PARAM_INT); // Default: Passaporto tecnico FTM

echo $OUTPUT->header();

// Selettore framework
$frameworks = $DB->get_records('competency_framework', [], 'shortname');
echo '<div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">';
echo '<form method="get" style="display: flex; gap: 10px; align-items: center;">';
echo '<label><strong>Framework:</strong></label>';
echo '<select name="frameworkid" style="padding: 8px; border-radius: 4px;">';
echo '<option value="0">-- Tutti --</option>';
foreach ($frameworks as $fw) {
    $selected = ($fw->id == $frameworkid) ? 'selected' : '';
    echo "<option value=\"{$fw->id}\" {$selected}>" . s($fw->shortname) . " (ID: {$fw->id})</option>";
}
echo '</select>';
echo '<button type="submit" style="padding: 8px 16px; background: #1e3c72; color: white; border: none; border-radius: 4px;">Filtra</button>';
echo '</form>';
echo '</div>';

// Query per trovare domande orfane con dettagli
if ($frameworkid > 0) {
    $sql = "
        SELECT qv.questionid, q2.name as question_name, q2.qtype,
               quiz.name as quiz_name, quiz.id as quizid,
               c.fullname as course_name, c.id as courseid,
               qcat.name as category_name
        FROM {quiz} quiz
        JOIN {course} c ON c.id = quiz.course
        JOIN {quiz_slots} qs ON qs.quizid = quiz.id
        JOIN {question_references} qr ON qr.itemid = qs.id
            AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q2 ON q2.id = qv.questionid
        JOIN {question_categories} qcat ON qcat.id = qbe.questioncategoryid
        WHERE NOT EXISTS (
            SELECT 1 FROM {qbank_competenciesbyquestion} qc
            JOIN {competency} comp ON comp.id = qc.competencyid
            WHERE qc.questionid = qv.questionid AND comp.competencyframeworkid = ?
        )
        ORDER BY c.fullname, quiz.name, q2.name
    ";
    $params = [$frameworkid];
} else {
    $sql = "
        SELECT qv.questionid, q2.name as question_name, q2.qtype,
               quiz.name as quiz_name, quiz.id as quizid,
               c.fullname as course_name, c.id as courseid,
               qcat.name as category_name
        FROM {quiz} quiz
        JOIN {course} c ON c.id = quiz.course
        JOIN {quiz_slots} qs ON qs.quizid = quiz.id
        JOIN {question_references} qr ON qr.itemid = qs.id
            AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {question} q2 ON q2.id = qv.questionid
        JOIN {question_categories} qcat ON qcat.id = qbe.questioncategoryid
        LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
        WHERE qc.id IS NULL
        ORDER BY c.fullname, quiz.name, q2.name
    ";
    $params = [];
}

$orphans = $DB->get_records_sql($sql, $params);
$count = count($orphans);

echo "<h3>📊 Trovate <span style='color: #dc3545;'>{$count}</span> domande orfane</h3>";

if ($count > 0) {
    // Raggruppa per corso/quiz
    $grouped = [];
    foreach ($orphans as $o) {
        $key = $o->courseid . '_' . $o->quizid;
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'course' => $o->course_name,
                'courseid' => $o->courseid,
                'quiz' => $o->quiz_name,
                'quizid' => $o->quizid,
                'questions' => []
            ];
        }
        $grouped[$key]['questions'][] = $o;
    }

    // Mostra raggruppato
    foreach ($grouped as $g) {
        $qcount = count($g['questions']);
        echo '<div style="margin: 20px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
        echo '<h4 style="margin: 0 0 10px 0;">';
        echo "<span style='color: #1e3c72;'>📚 " . s($g['course']) . "</span>";
        echo " → <span style='color: #28a745;'>📝 " . s($g['quiz']) . "</span>";
        echo " <span style='background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;'>{$qcount} orfane</span>";
        echo '</h4>';

        // Link a Setup Universale
        $setup_url = new moodle_url('/local/competencyxmlimport/setup_universale.php', ['courseid' => $g['courseid']]);
        echo '<p><a href="' . $setup_url . '" class="btn" style="background: #28a745; color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 12px;">🔧 Assegna competenze con Setup Universale</a></p>';

        echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        echo '<thead><tr style="background: #f8f9fa;">';
        echo '<th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">ID</th>';
        echo '<th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Nome Domanda</th>';
        echo '<th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Tipo</th>';
        echo '<th style="padding: 8px; text-align: left; border-bottom: 2px solid #dee2e6;">Categoria</th>';
        echo '</tr></thead><tbody>';

        foreach ($g['questions'] as $q) {
            $name = strlen($q->question_name) > 80 ? substr($q->question_name, 0, 77) . '...' : $q->question_name;
            echo '<tr>';
            echo "<td style='padding: 6px 8px; border-bottom: 1px solid #eee;'>{$q->questionid}</td>";
            echo "<td style='padding: 6px 8px; border-bottom: 1px solid #eee;'>" . s($name) . "</td>";
            echo "<td style='padding: 6px 8px; border-bottom: 1px solid #eee;'>{$q->qtype}</td>";
            echo "<td style='padding: 6px 8px; border-bottom: 1px solid #eee;'>" . s($q->category_name) . "</td>";
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // Riepilogo per settore (analisi testo domande)
    echo '<h3 style="margin-top: 30px;">📈 Analisi Possibili Settori (dal testo domande)</h3>';
    echo '<p style="color: #666;">Questa analisi cerca keyword nei nomi delle domande per suggerire il settore:</p>';

    $sector_keywords = [
        'AUTOMOBILE' => ['auto', 'veicol', 'motore', 'cambio', 'freno', 'ruota', 'pneumatic'],
        'MECCANICA' => ['mecc', 'tornio', 'fresa', 'tolleranz', 'filettatur', 'cnc'],
        'ELETTRICITÀ' => ['elettr', 'circuit', 'volt', 'ampere', 'cablaggio', 'impianto'],
        'AUTOMAZIONE' => ['plc', 'autom', 'pneumatic', 'sensor', 'attuator'],
        'LOGISTICA' => ['logist', 'magazz', 'stoccag', 'spediz', 'trasport'],
        'CHIMFARM' => ['chim', 'farm', 'laborator', 'reagent', 'soluzion'],
        'METALCOSTRUZIONE' => ['metal', 'saldatur', 'carpent', 'lamier', 'tagli']
    ];

    $sector_counts = [];
    foreach ($orphans as $o) {
        $text = strtolower($o->question_name . ' ' . $o->category_name);
        foreach ($sector_keywords as $sector => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false) {
                    if (!isset($sector_counts[$sector])) {
                        $sector_counts[$sector] = 0;
                    }
                    $sector_counts[$sector]++;
                    break;
                }
            }
        }
    }

    arsort($sector_counts);

    if (!empty($sector_counts)) {
        echo '<table style="width: auto; border-collapse: collapse; margin-top: 10px;">';
        echo '<tr style="background: #f8f9fa;"><th style="padding: 8px 15px;">Settore</th><th style="padding: 8px 15px;">Domande</th></tr>';
        foreach ($sector_counts as $sector => $cnt) {
            echo "<tr><td style='padding: 6px 15px; border-bottom: 1px solid #eee;'><strong>{$sector}</strong></td>";
            echo "<td style='padding: 6px 15px; border-bottom: 1px solid #eee;'>{$cnt}</td></tr>";
        }
        echo '</table>';
    } else {
        echo '<p style="color: #999;">Nessun settore rilevato dalle keyword.</p>';
    }

} else {
    echo '<div style="padding: 20px; background: #d4edda; border-radius: 8px; color: #155724;">';
    echo '<strong>✅ Ottimo!</strong> Nessuna domanda orfana trovata. Tutte le domande hanno competenze assegnate.';
    echo '</div>';
}

// Link ritorno
echo '<p style="margin-top: 30px;">';
echo '<a href="index.php" style="color: #1e3c72;">← Torna alla Dashboard</a> | ';
echo '<a href="run.php" style="color: #1e3c72;">Esegui Test Suite</a>';
echo '</p>';

echo $OUTPUT->footer();
