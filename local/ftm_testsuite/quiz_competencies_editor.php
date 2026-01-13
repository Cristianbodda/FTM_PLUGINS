<?php
/**
 * FTM Test Suite - Quiz Competencies Editor
 * 
 * Visualizza TUTTE le domande di un quiz con:
 * - Testo completo domanda
 * - Risposte con corretta evidenziata
 * - Competenza attuale e suggerita
 * - Possibilità di modifica inline
 * - Export/Import Excel e XML
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/quiz_competencies_editor.php'));
$PAGE->set_title('Quiz Competencies Editor - FTM Test Suite');
$PAGE->set_heading('Quiz Competencies Editor');
$PAGE->set_pagelayout('admin');

// Parametri
$quizid = required_param('quizid', PARAM_INT);
$action = optional_param('fts_action', '', PARAM_ALPHA);
$frameworkid = optional_param('fts_frameworkid', 0, PARAM_INT);
$export_format = optional_param('export', '', PARAM_ALPHA);

global $DB, $CFG;

// Carica info quiz
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

// Nome file per export (nome quiz pulito + data)
$quiz_name_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $quiz->name);
$quiz_name_clean = preg_replace('/_+/', '_', $quiz_name_clean);
$export_date = date('Y-m-d');
$export_filename = "{$quiz_name_clean}_{$export_date}_export";

// Carica framework disponibili
$frameworks = $DB->get_records_sql("
    SELECT cf.id, cf.shortname, COUNT(c.id) as comp_count
    FROM {competency_framework} cf
    LEFT JOIN {competency} c ON c.competencyframeworkid = cf.id
    GROUP BY cf.id, cf.shortname
    ORDER BY cf.shortname
");

// Se non è selezionato un framework, prova a rilevarlo dalle competenze esistenti nel quiz
if ($frameworkid == 0) {
    $detected = $DB->get_record_sql("
        SELECT c.competencyframeworkid, COUNT(*) as cnt
        FROM {quiz_slots} qs
        JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
        JOIN {competency} c ON c.id = qc.competencyid
        WHERE qs.quizid = ?
        GROUP BY c.competencyframeworkid
        ORDER BY cnt DESC
        LIMIT 1
    ", [$quizid]);
    
    if ($detected) {
        $frameworkid = $detected->competencyframeworkid;
    }
}

// Carica competenze del framework selezionato
$competencies = [];
$competencies_by_idnumber = [];
if ($frameworkid > 0) {
    $competencies = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname, c.description
        FROM {competency} c
        WHERE c.competencyframeworkid = ?
        ORDER BY c.idnumber
    ", [$frameworkid]);
    
    foreach ($competencies as $c) {
        $competencies_by_idnumber[strtoupper($c->idnumber)] = $c;
    }
}

// Carica TUTTE le domande del quiz con risposte
$questions = $DB->get_records_sql("
    SELECT 
        qv.questionid,
        qst.id as qid,
        qst.name as questionname,
        qst.questiontext,
        qst.qtype,
        qs.slot,
        qc.competencyid,
        c.idnumber as comp_idnumber,
        c.shortname as comp_shortname
    FROM {quiz_slots} qs
    JOIN {question_references} qr ON qr.itemid = qs.id 
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {question} qst ON qst.id = qv.questionid
    LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    LEFT JOIN {competency} c ON c.id = qc.competencyid
    WHERE qs.quizid = ?
    ORDER BY qs.slot
", [$quizid]);

// Carica risposte per ogni domanda
$questions_with_answers = [];
foreach ($questions as $q) {
    $q->answers = [];
    $q->suggested_competency = null;
    
    // Estrai competenza suggerita dal nome
    if (preg_match('/([A-Z]+_[A-Z]+_[A-Z0-9]+)\s*$/', $q->questionname, $matches)) {
        $suggested_idnumber = strtoupper($matches[1]);
        if (isset($competencies_by_idnumber[$suggested_idnumber])) {
            $q->suggested_competency = $competencies_by_idnumber[$suggested_idnumber];
        }
    }
    
    // Carica risposte in base al tipo
    if ($q->qtype === 'multichoice' || $q->qtype === 'truefalse') {
        $answers = $DB->get_records('question_answers', ['question' => $q->questionid], 'id ASC');
        foreach ($answers as $ans) {
            $q->answers[] = (object)[
                'text' => strip_tags($ans->answer),
                'fraction' => $ans->fraction,
                'is_correct' => ($ans->fraction > 0)
            ];
        }
    }
    
    $questions_with_answers[$q->questionid] = $q;
}

$message = '';
$messagetype = '';

// ============================================================================
// GESTIONE AZIONI
// ============================================================================

// Salvataggio competenze
if ($action === 'save' && confirm_sesskey()) {
    $new_competencies = optional_param_array('fts_comp', [], PARAM_INT);
    $saved = 0;
    $updated = 0;
    $removed = 0;
    
    foreach ($new_competencies as $questionid => $new_compid) {
        $questionid = (int)$questionid;
        $new_compid = (int)$new_compid;
        
        // Trova competenza attuale
        $current = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $questionid]);
        
        if ($new_compid > 0) {
            if ($current) {
                if ($current->competencyid != $new_compid) {
                    // Aggiorna
                    $current->competencyid = $new_compid;
                    $current->timemodified = time();
                    $DB->update_record('qbank_competenciesbyquestion', $current);
                    $updated++;
                }
            } else {
                // Inserisci nuova
                $record = new stdClass();
                $record->questionid = $questionid;
                $record->competencyid = $new_compid;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('qbank_competenciesbyquestion', $record);
                $saved++;
            }
        } else {
            if ($current) {
                // Rimuovi
                $DB->delete_records('qbank_competenciesbyquestion', ['id' => $current->id]);
                $removed++;
            }
        }
    }
    
    $message = "✅ Salvato! {$saved} nuove assegnazioni, {$updated} aggiornate, {$removed} rimosse.";
    $messagetype = 'success';
    
    // Ricarica dati
    redirect(new moodle_url('/local/ftm_testsuite/quiz_competencies_editor.php', [
        'quizid' => $quizid,
        'fts_frameworkid' => $frameworkid,
        'msg' => $message
    ]));
}

// ============================================================================
// EXPORT EXCEL
// ============================================================================
if ($export_format === 'excel') {
    require_once($CFG->libdir . '/excellib.class.php');
    
    $filename = $export_filename . '.xlsx';
    
    $workbook = new MoodleExcelWorkbook($filename);
    $worksheet = $workbook->add_worksheet($quiz->name);
    
    // Formati
    $format_header = $workbook->add_format(['bold' => 1, 'bg_color' => '#1e3c72', 'color' => 'white']);
    $format_correct = $workbook->add_format(['bg_color' => '#d4edda', 'color' => '#155724']);
    $format_orphan = $workbook->add_format(['bg_color' => '#f8d7da', 'color' => '#721c24']);
    $format_normal = $workbook->add_format();
    
    // Intestazioni
    $headers = ['Slot', 'ID', 'Codice Domanda', 'Testo Domanda', 'Risposta A', 'Risposta B', 'Risposta C', 'Risposta D', 'Risposta E', 'Corretta', 'Competenza Attuale', 'Competenza Suggerita', 'Nuova Competenza (idnumber)'];
    $col = 0;
    foreach ($headers as $h) {
        $worksheet->write(0, $col++, $h, $format_header);
    }
    
    // Dati
    $row = 1;
    foreach ($questions_with_answers as $q) {
        $col = 0;
        $format = $q->competencyid ? $format_normal : $format_orphan;
        
        $worksheet->write($row, $col++, $q->slot, $format);
        $worksheet->write($row, $col++, $q->questionid, $format);
        $worksheet->write($row, $col++, $q->questionname, $format);
        $worksheet->write($row, $col++, strip_tags($q->questiontext), $format);
        
        // Risposte (max 5)
        $correct_letter = '';
        $letters = ['A', 'B', 'C', 'D', 'E'];
        for ($i = 0; $i < 5; $i++) {
            if (isset($q->answers[$i])) {
                $ans_text = $q->answers[$i]->text;
                if ($q->answers[$i]->is_correct) {
                    $ans_text = "✓ " . $ans_text;
                    $correct_letter = $letters[$i];
                }
                $worksheet->write($row, $col++, $ans_text, $q->answers[$i]->is_correct ? $format_correct : $format);
            } else {
                $worksheet->write($row, $col++, '', $format);
            }
        }
        
        $worksheet->write($row, $col++, $correct_letter, $format);
        $worksheet->write($row, $col++, $q->comp_idnumber ?? '', $format);
        $worksheet->write($row, $col++, $q->suggested_competency ? $q->suggested_competency->idnumber : '', $format);
        $worksheet->write($row, $col++, '', $format); // Nuova competenza (da compilare)
        
        $row++;
    }
    
    // Larghezza colonne
    $worksheet->set_column(0, 0, 5);   // Slot
    $worksheet->set_column(1, 1, 8);   // ID
    $worksheet->set_column(2, 2, 30);  // Codice
    $worksheet->set_column(3, 3, 60);  // Testo
    $worksheet->set_column(4, 8, 30);  // Risposte
    $worksheet->set_column(9, 9, 10);  // Corretta
    $worksheet->set_column(10, 12, 25); // Competenze
    
    $workbook->close();
    exit;
}

// ============================================================================
// EXPORT XML (Moodle format)
// ============================================================================
if ($export_format === 'xml') {
    $filename = $export_filename . '.xml';
    
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<quiz>' . "\n";
    echo '  <!-- Quiz: ' . htmlspecialchars($quiz->name) . ' -->' . "\n";
    echo '  <!-- Corso: ' . htmlspecialchars($course->fullname) . ' -->' . "\n";
    echo '  <!-- Esportato: ' . date('Y-m-d H:i:s') . ' -->' . "\n";
    echo '  <!-- Framework: ' . ($frameworkid > 0 ? htmlspecialchars($frameworks[$frameworkid]->shortname) : 'N/D') . ' -->' . "\n\n";
    
    foreach ($questions_with_answers as $q) {
        echo '  <question type="' . $q->qtype . '">' . "\n";
        echo '    <id>' . $q->questionid . '</id>' . "\n";
        echo '    <slot>' . $q->slot . '</slot>' . "\n";
        echo '    <name><text>' . htmlspecialchars($q->questionname) . '</text></name>' . "\n";
        echo '    <questiontext format="html"><text><![CDATA[' . $q->questiontext . ']]></text></questiontext>' . "\n";
        
        if (!empty($q->answers)) {
            echo '    <answers>' . "\n";
            foreach ($q->answers as $idx => $ans) {
                $letter = chr(65 + $idx); // A, B, C, D...
                echo '      <answer fraction="' . ($ans->fraction * 100) . '" letter="' . $letter . '">' . "\n";
                echo '        <text>' . htmlspecialchars($ans->text) . '</text>' . "\n";
                echo '        <correct>' . ($ans->is_correct ? 'true' : 'false') . '</correct>' . "\n";
                echo '      </answer>' . "\n";
            }
            echo '    </answers>' . "\n";
        }
        
        echo '    <competency>' . "\n";
        echo '      <current>' . htmlspecialchars($q->comp_idnumber ?? '') . '</current>' . "\n";
        echo '      <suggested>' . htmlspecialchars($q->suggested_competency ? $q->suggested_competency->idnumber : '') . '</suggested>' . "\n";
        echo '      <new></new>' . "\n";
        echo '    </competency>' . "\n";
        echo '  </question>' . "\n\n";
    }
    
    echo '</quiz>' . "\n";
    exit;
}

// ============================================================================
// IMPORT (gestione upload)
// ============================================================================
if ($action === 'import' && confirm_sesskey()) {
    if (isset($_FILES['importfile']) && $_FILES['importfile']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['importfile']['name'], PATHINFO_EXTENSION));
        $tmp_file = $_FILES['importfile']['tmp_name'];
        
        $imported = 0;
        $errors = [];
        
        if ($file_ext === 'xml') {
            // Parse XML
            $xml = simplexml_load_file($tmp_file);
            if ($xml) {
                foreach ($xml->question as $xq) {
                    $qid = (int)$xq->id;
                    $new_comp_idnumber = trim((string)$xq->competency->new);
                    
                    if ($qid > 0 && !empty($new_comp_idnumber) && isset($competencies_by_idnumber[strtoupper($new_comp_idnumber)])) {
                        $comp = $competencies_by_idnumber[strtoupper($new_comp_idnumber)];
                        
                        // Verifica se esiste già
                        $exists = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $qid]);
                        
                        if ($exists) {
                            $exists->competencyid = $comp->id;
                            $exists->timemodified = time();
                            $DB->update_record('qbank_competenciesbyquestion', $exists);
                        } else {
                            $record = new stdClass();
                            $record->questionid = $qid;
                            $record->competencyid = $comp->id;
                            $record->timecreated = time();
                            $record->timemodified = time();
                            $DB->insert_record('qbank_competenciesbyquestion', $record);
                        }
                        $imported++;
                    }
                }
            }
        } elseif ($file_ext === 'xlsx' || $file_ext === 'xls') {
            // Parse Excel
            require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            // Salta header
            array_shift($rows);
            
            foreach ($rows as $row) {
                $qid = (int)($row[1] ?? 0); // Colonna B = ID
                $new_comp_idnumber = trim($row[12] ?? ''); // Colonna M = Nuova Competenza
                
                if ($qid > 0 && !empty($new_comp_idnumber) && isset($competencies_by_idnumber[strtoupper($new_comp_idnumber)])) {
                    $comp = $competencies_by_idnumber[strtoupper($new_comp_idnumber)];
                    
                    $exists = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $qid]);
                    
                    if ($exists) {
                        $exists->competencyid = $comp->id;
                        $exists->timemodified = time();
                        $DB->update_record('qbank_competenciesbyquestion', $exists);
                    } else {
                        $record = new stdClass();
                        $record->questionid = $qid;
                        $record->competencyid = $comp->id;
                        $record->timecreated = time();
                        $record->timemodified = time();
                        $DB->insert_record('qbank_competenciesbyquestion', $record);
                    }
                    $imported++;
                }
            }
        }
        
        $message = "📥 Importate {$imported} competenze dal file!";
        $messagetype = 'success';
        
        redirect(new moodle_url('/local/ftm_testsuite/quiz_competencies_editor.php', [
            'quizid' => $quizid,
            'fts_frameworkid' => $frameworkid,
            'msg' => $message
        ]));
    }
}

// Messaggio dalla redirect
$msg_param = optional_param('msg', '', PARAM_TEXT);
if ($msg_param) {
    $message = $msg_param;
    $messagetype = 'success';
}

// Statistiche
$total_questions = count($questions_with_answers);
$with_competency = 0;
$orphans = 0;
foreach ($questions_with_answers as $q) {
    if ($q->competencyid) {
        $with_competency++;
    } else {
        $orphans++;
    }
}

echo $OUTPUT->header();
?>

<style>
.editor-container { max-width: 1600px; margin: 0 auto; }

.editor-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.editor-header h1 { margin: 0 0 5px 0; font-size: 24px; }
.editor-header .quiz-info { opacity: 0.9; font-size: 14px; }

.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    flex: 1;
    padding: 15px 20px;
    border-radius: 10px;
    text-align: center;
}
.stat-card.total { background: #e3f2fd; }
.stat-card.ok { background: #d4edda; }
.stat-card.orphan { background: #f8d7da; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; color: #666; }

.toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
    align-items: center;
}
.toolbar .framework-select { flex: 1; min-width: 250px; }
.toolbar select { padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-size: 13px;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-warning { background: #ffc107; color: #333; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; }

/* Question Cards */
.question-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
    border-left: 5px solid #28a745;
}
.question-card.orphan {
    border-left-color: #dc3545;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}
.question-header .meta {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #666;
}
.question-header .meta span { display: flex; align-items: center; gap: 4px; }
.question-header .slot {
    background: #1e3c72;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
}

.question-body { padding: 20px; }

.question-text {
    font-size: 15px;
    line-height: 1.6;
    color: #333;
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #1e3c72;
}

.answers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.answer-item {
    padding: 12px 15px;
    border-radius: 8px;
    background: #f8f9fa;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.answer-item.correct {
    background: #d4edda;
    border: 2px solid #28a745;
}
.answer-item .letter {
    min-width: 28px;
    height: 28px;
    background: #dee2e6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}
.answer-item.correct .letter {
    background: #28a745;
    color: white;
}
.answer-item .text { flex: 1; font-size: 14px; }

.competency-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
    padding: 15px;
    background: #e9ecef;
    border-radius: 8px;
}
.competency-row .field { flex: 1; min-width: 200px; }
.competency-row label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #666; }
.competency-row .value { 
    padding: 8px 12px; 
    background: white; 
    border-radius: 6px; 
    font-size: 13px;
    min-height: 38px;
    display: flex;
    align-items: center;
}
.competency-row .value.empty { color: #dc3545; font-style: italic; }
.competency-row .value.suggested { color: #007bff; }
.competency-row select {
    width: 100%;
    padding: 10px;
    border: 2px solid #1e3c72;
    border-radius: 6px;
    font-size: 13px;
}

.import-section {
    background: #fff3cd;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.import-section h4 { margin: 0 0 15px 0; }
.import-section input[type="file"] { margin-right: 10px; }
</style>

<div class="editor-container">
    
    <div class="editor-header">
        <h1>📝 <?php echo $quiz->name; ?></h1>
        <div class="quiz-info">
            📚 Corso: <?php echo $course->fullname; ?> | 
            🔢 <?php echo $total_questions; ?> domande |
            📅 <?php echo date('d/m/Y'); ?>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="stats-row">
        <div class="stat-card total">
            <div class="number"><?php echo $total_questions; ?></div>
            <div class="label">Domande Totali</div>
        </div>
        <div class="stat-card ok">
            <div class="number"><?php echo $with_competency; ?></div>
            <div class="label">Con Competenza ✅</div>
        </div>
        <div class="stat-card orphan">
            <div class="number"><?php echo $orphans; ?></div>
            <div class="label">Senza Competenza ❌</div>
        </div>
    </div>
    
    <!-- Toolbar -->
    <div class="toolbar">
        <div class="framework-select">
            <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="quizid" value="<?php echo $quizid; ?>">
                <label>🎯 Framework:</label>
                <select name="fts_frameworkid" onchange="this.form.submit()">
                    <option value="0">-- Seleziona --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?>>
                        <?php echo $fw->shortname; ?> (<?php echo $fw->comp_count; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <a href="?quizid=<?php echo $quizid; ?>&fts_frameworkid=<?php echo $frameworkid; ?>&export=excel" class="btn btn-success">
            📥 Esporta Excel
        </a>
        <a href="?quizid=<?php echo $quizid; ?>&fts_frameworkid=<?php echo $frameworkid; ?>&export=xml" class="btn btn-info">
            📥 Esporta XML
        </a>
    </div>
    
    <!-- Import Section -->
    <div class="import-section">
        <h4>📤 Importa da File</h4>
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="fts_action" value="import">
            <input type="hidden" name="fts_frameworkid" value="<?php echo $frameworkid; ?>">
            <input type="file" name="importfile" accept=".xml,.xlsx,.xls" required>
            <button type="submit" class="btn btn-warning">📤 Importa Competenze</button>
            <span style="font-size: 12px; color: #666; margin-left: 10px;">
                Compila la colonna "Nuova Competenza" nel file esportato e reimportalo qui.
            </span>
        </form>
    </div>
    
    <!-- Form Modifica -->
    <form method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="fts_action" value="save">
        <input type="hidden" name="fts_frameworkid" value="<?php echo $frameworkid; ?>">
        
        <!-- Questions -->
        <?php foreach ($questions_with_answers as $q): ?>
        <div class="question-card <?php echo !$q->competencyid ? 'orphan' : ''; ?>">
            
            <div class="question-header">
                <div class="meta">
                    <span class="slot">#<?php echo $q->slot; ?></span>
                    <span>🔢 ID: <?php echo $q->questionid; ?></span>
                    <span>📌 <?php echo $q->questionname; ?></span>
                    <span>📋 <?php echo $q->qtype; ?></span>
                </div>
            </div>
            
            <div class="question-body">
                
                <!-- Testo domanda -->
                <div class="question-text">
                    <?php echo strip_tags($q->questiontext); ?>
                </div>
                
                <!-- Risposte -->
                <?php if (!empty($q->answers)): ?>
                <div class="answers-grid">
                    <?php 
                    $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                    foreach ($q->answers as $idx => $ans): 
                    ?>
                    <div class="answer-item <?php echo $ans->is_correct ? 'correct' : ''; ?>">
                        <div class="letter"><?php echo $letters[$idx] ?? '?'; ?></div>
                        <div class="text">
                            <?php echo $ans->text; ?>
                            <?php if ($ans->is_correct): ?>
                            <strong style="color: #28a745;"> ✓ CORRETTA</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Competenze -->
                <div class="competency-row">
                    <div class="field">
                        <label>📍 Competenza Attuale</label>
                        <div class="value <?php echo !$q->competencyid ? 'empty' : ''; ?>">
                            <?php if ($q->competencyid): ?>
                                <?php echo $q->comp_idnumber; ?> - <?php echo $q->comp_shortname; ?>
                            <?php else: ?>
                                ❌ Nessuna competenza assegnata
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>💡 Suggerimento (dal nome)</label>
                        <div class="value <?php echo $q->suggested_competency ? 'suggested' : 'empty'; ?>">
                            <?php if ($q->suggested_competency): ?>
                                <?php echo $q->suggested_competency->idnumber; ?> - <?php echo $q->suggested_competency->shortname; ?>
                            <?php else: ?>
                                — Nessun suggerimento
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>🎯 Assegna Competenza</label>
                        <?php if ($frameworkid > 0): ?>
                        <select name="fts_comp[<?php echo $q->questionid; ?>]">
                            <option value="0">-- Nessuna / Rimuovi --</option>
                            <?php foreach ($competencies as $comp): ?>
                            <option value="<?php echo $comp->id; ?>" 
                                <?php echo $q->competencyid == $comp->id ? 'selected' : ''; ?>
                                <?php if ($q->suggested_competency && $q->suggested_competency->id == $comp->id && !$q->competencyid): ?>
                                    style="background: #cce5ff;"
                                <?php endif; ?>
                            >
                                <?php echo $comp->idnumber; ?> - <?php echo $comp->shortname; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <div class="value empty">Seleziona un framework</div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Save Button -->
        <?php if ($frameworkid > 0): ?>
        <div style="position: sticky; bottom: 20px; text-align: center; padding: 20px;">
            <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px;">
                💾 Salva Tutte le Modifiche
            </button>
        </div>
        <?php endif; ?>
        
    </form>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 25px; padding-bottom: 80px;">
        <a href="fix_orphan_questions.php" class="btn btn-secondary">← Torna a Domande Orfane</a>
        <a href="run.php" class="btn btn-secondary" style="margin-left: 10px;">▶️ Esegui Test</a>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
