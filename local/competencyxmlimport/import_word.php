<?php
/**
 * IMPORT DA WORD - Convertitore Word → Moodle XML
 * 
 * Funzionalità:
 * - Legge file Word (.docx) con domande quiz
 * - Estrae domande, risposte, competenze
 * - Valida competenze vs framework
 * - Genera XML Moodle o importa direttamente
 * - Crea quiz con competenze assegnate
 * 
 * Supporta formati:
 * - Competenza: SETTORE_AREA_NUM
 * - Codice competenza: SETTORE_AREA_NUM
 * - Codice: SETTORE_AREA_NUM
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'form', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/import_word.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Import da Word - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================
// FUNZIONI HELPER
// ============================================================

/**
 * Estrae testo da file Word (.docx)
 * Parsing XML ottimizzato per estrarre ogni nodo <w:t> correttamente
 */
function extract_text_from_docx($filepath) {
    $content = '';
    
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        return $content;
    }
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if (!$xml) {
        return $content;
    }
    
    // Metodo: Estrai ogni nodo <w:t> e ricostruisci il testo
    // I paragrafi <w:p> diventano newline
    
    $lines = [];
    $current_para = '';
    
    // Dividi per paragrafi
    $paragraphs = preg_split('/<w:p[^>]*>/', $xml);
    
    foreach ($paragraphs as $para) {
        // Estrai tutti i nodi <w:t> dal paragrafo
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $para, $matches)) {
            $para_text = implode('', $matches[1]);
            $para_text = trim($para_text);
            
            if (!empty($para_text)) {
                $lines[] = $para_text;
            }
        }
    }
    
    $content = implode("\n", $lines);
    
    // Decodifica entità HTML
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $content;
}

/**
 * Estrae domande dal testo Word
 * Supporta più formati di competenza e risposta
 * 
 * Formati supportati:
 * - A) risposta, A\) risposta, A. risposta
 * - **Competenza:** CODE, Competenza: CODE, Codice: CODE
 * - **Risposta corretta:** X, Risposta corretta: X
 */
function parse_questions_from_text($text) {
    $questions = [];
    
    // ============================================================
    // PRE-PROCESSING: Separa campi che potrebbero essere concatenati
    // ============================================================
    
    // Separa "Competenza:" su una nuova riga
    $text = preg_replace('/(?<!^)(?<!\n)(Competenza\s*:)/i', "\n$1", $text);
    
    // Separa "Risposta corretta:" su una nuova riga  
    $text = preg_replace('/(?<!^)(?<!\n)(Risposta\s*corretta\s*:)/i', "\n$1", $text);
    
    // Separa "Codice competenza:" su una nuova riga
    $text = preg_replace('/(?<!^)(?<!\n)(Codice\s*(?:competenza\s*)?:)/i', "\n$1", $text);
    
    // Separa risposte A), B), C), D) su nuove righe se sono concatenate
    $text = preg_replace('/(?<=[^\n])([ABCD]\))/i', "\n$1", $text);
    
    // Normalizza il testo
    // Rimuovi backslash di escape (A\) -> A))
    $text = str_replace('\\)', ')', $text);
    // Rimuovi \\ a fine riga
    $text = str_replace('\\', '', $text);
    
    $lines = explode("\n", $text);
    $current = null;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        
        // Salta righe vuote
        if (empty($line)) continue;
        
        // Nuovo numero domanda: **Q01**, Q01, **Q1**, Q1., etc.
        if (preg_match('/^\*?\*?Q(\d+)\*?\*?\.?\s*$/i', $line, $m)) {
            // Salva domanda precedente
            if ($current && !empty($current['question'])) {
                $questions[] = $current;
            }
            $current = [
                'number' => (int)$m[1],
                'question' => '',
                'answers' => ['A' => '', 'B' => '', 'C' => '', 'D' => ''],
                'correct' => '',
                'competency' => '',
                'raw_lines' => []
            ];
            continue;
        }
        
        if (!$current) continue;
        
        // Competenza (vari formati) - PRIMA delle risposte per evitare conflitti
        // Formato: **Competenza:** CODE o Competenza: CODE o Codice: CODE
        // Pattern codice: SETTORE_AREA_NUMERO (es. AUTOMAZIONE_OA_A1, CHIMFARM_1C_01)
        if (preg_match('/(?:Competenza|Codice(?:\s+competenza)?)\s*:\s*([A-ZÀÈÉÌÒÙ]+_[A-Z0-9]+_[A-Z0-9]+)/i', $line, $m)) {
            $current['competency'] = strtoupper(trim($m[1]));
            continue;
        }
        
        // Risposta corretta (vari formati)
        // Formato: **Risposta corretta:** X o Risposta corretta: X
        if (preg_match('/\*?\*?(?:✓\s*)?Risposta(?:\s+corretta)?\*?\*?\s*:?\*?\*?\s*([A-D])/i', $line, $m)) {
            $current['correct'] = strtoupper($m[1]);
            continue;
        }
        
        // Risposte A), B), C), D) - anche con backslash originale già rimosso
        if (preg_match('/^([A-D])[\.\)]\s*(.+)$/i', $line, $m)) {
            $letter = strtoupper($m[1]);
            // Rimuovi eventuali asterischi markdown dalla risposta
            $answer_text = preg_replace('/\*\*(.+?)\*\*/', '$1', trim($m[2]));
            $current['answers'][$letter] = $answer_text;
            continue;
        }
        
        // Risposte inline: A) ... B) ... C) ... D) ... (tutte su una riga)
        if (preg_match('/A[\.\)]\s*.+B[\.\)]\s*.+/i', $line)) {
            // Estrai tutte le risposte dalla riga
            if (preg_match('/A[\.\)]\s*(.+?)\s*B[\.\)]/i', $line, $m)) {
                $current['answers']['A'] = trim($m[1]);
            }
            if (preg_match('/B[\.\)]\s*(.+?)\s*C[\.\)]/i', $line, $m)) {
                $current['answers']['B'] = trim($m[1]);
            }
            if (preg_match('/C[\.\)]\s*(.+?)\s*D[\.\)]/i', $line, $m)) {
                $current['answers']['C'] = trim($m[1]);
            }
            if (preg_match('/D[\.\)]\s*(.+?)$/i', $line, $m)) {
                $current['answers']['D'] = trim($m[1]);
            }
            continue;
        }
        
        // Se arriviamo qui ed è testo, è parte della domanda
        // Salta se contiene "Competenza" o "Risposta" o è una risposta
        if (stripos($line, 'ompetenza') !== false || 
            stripos($line, 'isposta') !== false ||
            preg_match('/^[A-D][\.\)]/', $line)) {
            continue;
        }
        
        if (empty($current['question'])) {
            // Prima riga della domanda - rimuovi grassetto markdown
            $line = preg_replace('/\*\*(.+?)\*\*/', '$1', $line);
            $current['question'] = $line;
        } else {
            // Continua la domanda su più righe
            $line = preg_replace('/\*\*(.+?)\*\*/', '$1', $line);
            $current['question'] .= ' ' . $line;
        }
        
        $current['raw_lines'][] = $line;
    }
    
    // Ultima domanda
    if ($current && !empty($current['question'])) {
        $questions[] = $current;
    }
    
    return $questions;
}

/**
 * Valida le competenze contro il framework
 */
function validate_competencies($questions, $frameworkid) {
    global $DB;
    
    // Carica tutte le competenze del framework
    $valid_comps = $DB->get_fieldset_select('competency', 'idnumber', 
        'competencyframeworkid = ?', [$frameworkid]);
    $valid_set = array_flip($valid_comps);
    
    $validated = [];
    foreach ($questions as $q) {
        $q['comp_valid'] = isset($valid_set[$q['competency']]);
        $q['comp_suggestion'] = '';
        
        // Se non valida, cerca suggerimento
        if (!$q['comp_valid'] && !empty($q['competency'])) {
            $suggestion = find_similar_competency($q['competency'], $valid_comps);
            $q['comp_suggestion'] = $suggestion;
        }
        
        // Verifica completezza
        $q['is_complete'] = !empty($q['question']) && 
                           !empty($q['answers']['A']) &&
                           !empty($q['answers']['B']) &&
                           !empty($q['answers']['C']) &&
                           !empty($q['answers']['D']) &&
                           !empty($q['correct']) &&
                           !empty($q['competency']);
        
        $validated[] = $q;
    }
    
    return $validated;
}

/**
 * Trova competenza simile per suggerimento
 */
function find_similar_competency($wrong_code, $valid_codes) {
    // Estrai parti del codice
    if (!preg_match('/^([A-Z]+)_([A-Z0-9]+)_(\d+)$/', $wrong_code, $m)) {
        return null;
    }
    
    $sector = $m[1];
    $area = $m[2];
    $num = $m[3];
    
    // Se numero a 3 cifre (010), prova con 2 cifre (10)
    if (strlen($num) == 3 && $num[0] == '0') {
        $try_code = $sector . '_' . $area . '_' . substr($num, 1);
        if (in_array($try_code, $valid_codes)) {
            return $try_code;
        }
        // Prova con 1 cifra
        $try_code = $sector . '_' . $area . '_' . ltrim($num, '0');
        if (in_array($try_code, $valid_codes)) {
            return $try_code;
        }
    }
    
    // Cerca qualsiasi codice nella stessa area
    $base = $sector . '_' . $area . '_';
    foreach ($valid_codes as $vc) {
        if (strpos($vc, $base) === 0) {
            return $vc . ' (o simile)';
        }
    }
    
    return null;
}

/**
 * Genera XML Moodle dalle domande
 */
function generate_moodle_xml($questions, $quiz_name, $category_name) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<quiz>' . "\n";
    
    // Categoria
    $xml .= '  <question type="category">' . "\n";
    $xml .= '    <category><text>$course$/top/' . htmlspecialchars($category_name) . '</text></category>' . "\n";
    $xml .= '  </question>' . "\n\n";
    
    foreach ($questions as $q) {
        if (!$q['is_complete']) continue;
        
        // Nome domanda: PREFIX_Qnn - COMPETENZA
        $prefix = preg_replace('/[^A-Z0-9_]/i', '_', $quiz_name);
        $q_name = sprintf('%s_Q%02d - %s', $prefix, $q['number'], $q['competency']);
        
        $xml .= '  <question type="multichoice">' . "\n";
        $xml .= '    <name><text>' . htmlspecialchars($q_name) . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html">' . "\n";
        $xml .= '      <text><![CDATA[<p>' . htmlspecialchars($q['question']) . '</p>]]></text>' . "\n";
        $xml .= '    </questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text></text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>1.0000000</defaultgrade>' . "\n";
        $xml .= '    <penalty>0.3333333</penalty>' . "\n";
        $xml .= '    <hidden>0</hidden>' . "\n";
        $xml .= '    <single>true</single>' . "\n";
        $xml .= '    <shuffleanswers>true</shuffleanswers>' . "\n";
        $xml .= '    <answernumbering>abc</answernumbering>' . "\n";
        $xml .= '    <correctfeedback format="html"><text>Risposta corretta!</text></correctfeedback>' . "\n";
        $xml .= '    <partiallycorrectfeedback format="html"><text></text></partiallycorrectfeedback>' . "\n";
        $xml .= '    <incorrectfeedback format="html"><text>Risposta errata.</text></incorrectfeedback>' . "\n";
        
        // Risposte
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $fraction = ($letter == $q['correct']) ? '100' : '0';
            $xml .= '    <answer fraction="' . $fraction . '" format="html">' . "\n";
            $xml .= '      <text><![CDATA[<p>' . htmlspecialchars($q['answers'][$letter]) . '</p>]]></text>' . "\n";
            $xml .= '      <feedback format="html"><text></text></feedback>' . "\n";
            $xml .= '    </answer>' . "\n";
        }
        
        // Tag con competenza
        $xml .= '    <tags>' . "\n";
        $xml .= '      <tag><text>' . htmlspecialchars($q['competency']) . '</text></tag>' . "\n";
        $xml .= '    </tags>' . "\n";
        
        $xml .= '  </question>' . "\n\n";
    }
    
    $xml .= '</quiz>' . "\n";
    
    return $xml;
}

/**
 * Rileva il settore dominante dalle competenze
 */
function detect_sector_from_questions($questions) {
    $sectors = [];
    foreach ($questions as $q) {
        if (!empty($q['competency'])) {
            if (preg_match('/^([A-Z]+)_/', $q['competency'], $m)) {
                $sector = $m[1];
                if (!isset($sectors[$sector])) $sectors[$sector] = 0;
                $sectors[$sector]++;
            }
        }
    }
    arsort($sectors);
    return $sectors ? array_key_first($sectors) : '';
}

// ============================================================
// CSS
// ============================================================

$css = '
<style>
.import-page { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.import-header { background: linear-gradient(135deg, #8B5CF6, #7C3AED); color: white; padding: 30px; border-radius: 16px; margin-bottom: 25px; }
.import-header h2 { margin: 0 0 8px 0; font-size: 28px; }
.import-header p { margin: 0; opacity: 0.9; }

.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
.panel h3 { margin: 0 0 20px 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; }

.upload-zone { border: 3px dashed #d1d5db; border-radius: 12px; padding: 40px; text-align: center; background: #f9fafb; transition: all 0.2s; }
.upload-zone:hover { border-color: #8B5CF6; background: #faf5ff; }
.upload-zone input[type="file"] { display: none; }
.upload-zone label { cursor: pointer; display: block; }
.upload-icon { font-size: 48px; margin-bottom: 15px; }
.upload-text { font-size: 16px; color: #6b7280; }
.upload-text strong { color: #8B5CF6; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #374151; }
.form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.form-group input:focus, .form-group select:focus { border-color: #8B5CF6; outline: none; }

.btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: linear-gradient(135deg, #8B5CF6, #7C3AED); color: white; }
.btn-success { background: linear-gradient(135deg, #10B981, #059669); color: white; }
.btn-secondary { background: #6b7280; color: white; }
.btn-danger { background: linear-gradient(135deg, #EF4444, #DC2626); color: white; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-card { padding: 20px; border-radius: 12px; text-align: center; color: white; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; opacity: 0.9; }
.stat-card.purple { background: linear-gradient(135deg, #8B5CF6, #7C3AED); }
.stat-card.green { background: linear-gradient(135deg, #10B981, #059669); }
.stat-card.red { background: linear-gradient(135deg, #EF4444, #DC2626); }
.stat-card.blue { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.stat-card.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }

table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
th { background: #f9fafb; font-weight: 600; color: #374151; }
tr:hover { background: #f9fafb; }

.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-error { background: #fee2e2; color: #991b1b; }
.badge-warning { background: #fef3c7; color: #92400e; }

.code { font-family: "SF Mono", Monaco, monospace; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.code-valid { background: #d1fae5; color: #065f46; }
.code-invalid { background: #fee2e2; color: #991b1b; }
.code-suggestion { background: #dbeafe; color: #1e40af; }

.alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

.back-link { display: inline-block; margin-bottom: 15px; color: #8B5CF6; text-decoration: none; font-weight: 500; }
.back-link:hover { text-decoration: underline; }

.scrollable { max-height: 500px; overflow-y: auto; }

.actions-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0; }

.question-preview { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
.question-preview.error { border-color: #fca5a5; background: #fef2f2; }
.question-preview.warning { border-color: #fcd34d; background: #fffbeb; }
.question-preview h4 { margin: 0 0 8px 0; font-size: 14px; }
.question-preview p { margin: 0 0 8px 0; color: #374151; }
.question-preview .answers { margin: 10px 0; padding-left: 20px; }
.question-preview .answer { margin: 4px 0; }
.question-preview .answer.correct { color: #059669; font-weight: 600; }
.question-preview .meta { display: flex; gap: 15px; margin-top: 10px; font-size: 12px; color: #6b7280; }
</style>';

// ============================================================
// GESTIONE AZIONI
// ============================================================

echo $OUTPUT->header();
echo $css;

$base_url = "import_word.php?courseid={$courseid}";
$dashboard_url = "dashboard.php?courseid={$courseid}";

// Lista framework
$frameworks = $DB->get_records('competency_framework', [], 'shortname', 'id, shortname');
if (!$frameworkid && !empty($frameworks)) {
    $frameworkid = array_key_first($frameworks);
}

?>
<div class="import-page">
    <a href="<?php echo $dashboard_url; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="import-header">
        <h2>📄 Import da Word</h2>
        <p>Carica un file Word (.docx) con domande quiz e convertilo in formato Moodle</p>
    </div>

<?php

// ============================================================
// ACTION: FORM (upload)
// ============================================================
if ($action == 'form'):
?>
    <div class="panel">
        <h3>📤 Carica File Word</h3>
        
        <form method="POST" action="<?php echo $base_url; ?>&action=preview" enctype="multipart/form-data">
            <div class="upload-zone">
                <label for="wordfile">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">
                        <strong>Clicca per selezionare</strong> o trascina un file .docx
                    </div>
                </label>
                <input type="file" id="wordfile" name="wordfile" accept=".docx" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 25px;">
                <div class="form-group">
                    <label>Nome Quiz</label>
                    <input type="text" name="quiz_name" placeholder="es. AUTO_TEST_BASE" required>
                </div>
                <div class="form-group">
                    <label>Framework Competenze</label>
                    <select name="frameworkid">
                        <?php foreach ($frameworks as $f): ?>
                        <option value="<?php echo $f->id; ?>" <?php echo $f->id == $frameworkid ? 'selected' : ''; ?>><?php echo $f->shortname; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">📥 Analizza File</button>
                <a href="/mnt/user-data/outputs/FTM_TEMPLATE_QUIZ_STANDARD.docx" class="btn btn-secondary" download>📋 Scarica Template</a>
            </div>
        </form>
    </div>
    
    <div class="panel">
        <h3>📖 Formato Supportato</h3>
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 13px;">
            <strong>**Q01**</strong><br><br>
            Testo della domanda qui...<br><br>
            A) Prima risposta<br>
            B) Seconda risposta<br>
            C) Terza risposta<br>
            D) Quarta risposta<br><br>
            <strong>Competenza:</strong> SETTORE_AREA_NUMERO<br>
            <strong>Risposta corretta:</strong> C
        </div>
        <p style="margin-top: 15px; color: #6b7280; font-size: 13px;">
            💡 Scarica la <a href="/local/competencyxmlimport/FTM_GUIDA_FORMATO_QUIZ_PER_AI.md">Guida Formato</a> per istruzioni dettagliate e prompt per ChatGPT.
        </p>
    </div>

<?php
// ============================================================
// ACTION: PREVIEW (analizza file)
// ============================================================
elseif ($action == 'preview'):
    
    if (empty($_FILES['wordfile']['tmp_name'])) {
        echo '<div class="alert alert-error">❌ Nessun file caricato</div>';
        echo '<a href="' . $base_url . '" class="btn btn-secondary">← Torna indietro</a>';
    } else {
        $quiz_name = required_param('quiz_name', PARAM_ALPHANUMEXT);
        $frameworkid = required_param('frameworkid', PARAM_INT);
        
        // Estrai testo
        $text = extract_text_from_docx($_FILES['wordfile']['tmp_name']);
        
        if (empty($text)) {
            echo '<div class="alert alert-error">❌ Impossibile leggere il file Word</div>';
            echo '<a href="' . $base_url . '" class="btn btn-secondary">← Torna indietro</a>';
        } else {
            // Parse domande
            $questions = parse_questions_from_text($text);
            
            // Valida competenze
            $questions = validate_competencies($questions, $frameworkid);
            
            // Statistiche
            $total = count($questions);
            $complete = count(array_filter($questions, fn($q) => $q['is_complete']));
            $valid_comp = count(array_filter($questions, fn($q) => $q['comp_valid']));
            $invalid_comp = count(array_filter($questions, fn($q) => !$q['comp_valid'] && !empty($q['competency'])));
            $missing_comp = count(array_filter($questions, fn($q) => empty($q['competency'])));
            $detected_sector = detect_sector_from_questions($questions);
            
            // Salva in sessione per azioni successive
            $_SESSION['import_word_questions'] = $questions;
            $_SESSION['import_word_quiz_name'] = $quiz_name;
            $_SESSION['import_word_frameworkid'] = $frameworkid;
?>
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="number"><?php echo $total; ?></div>
            <div class="label">Domande Trovate</div>
        </div>
        <div class="stat-card <?php echo $complete == $total ? 'green' : 'orange'; ?>">
            <div class="number"><?php echo $complete; ?></div>
            <div class="label">Complete</div>
        </div>
        <div class="stat-card <?php echo $valid_comp == $total ? 'green' : 'blue'; ?>">
            <div class="number"><?php echo $valid_comp; ?></div>
            <div class="label">Competenze Valide</div>
        </div>
        <div class="stat-card <?php echo $invalid_comp > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo $invalid_comp; ?></div>
            <div class="label">Competenze Errate</div>
        </div>
        <div class="stat-card blue">
            <div class="number"><?php echo $detected_sector ?: '-'; ?></div>
            <div class="label">Settore Rilevato</div>
        </div>
    </div>

    <?php if ($invalid_comp > 0): ?>
    <div class="alert alert-warning">
        ⚠️ <strong><?php echo $invalid_comp; ?> competenze</strong> non esistono nel framework. Verifica i suggerimenti nella tabella.
    </div>
    <?php endif; ?>
    
    <?php if ($missing_comp > 0): ?>
    <div class="alert alert-error">
        ❌ <strong><?php echo $missing_comp; ?> domande</strong> senza codice competenza. Non saranno esportate.
    </div>
    <?php endif; ?>

    <div class="panel">
        <h3>📋 Anteprima Domande</h3>
        <div class="scrollable">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Domanda</th>
                        <th>Risposte</th>
                        <th>Corretta</th>
                        <th>Competenza</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q): 
                    $row_class = '';
                    if (!$q['is_complete']) $row_class = 'style="background:#fef2f2;"';
                    elseif (!$q['comp_valid']) $row_class = 'style="background:#fffbeb;"';
                ?>
                <tr <?php echo $row_class; ?>>
                    <td><strong>Q<?php echo sprintf('%02d', $q['number']); ?></strong></td>
                    <td><?php echo htmlspecialchars(substr($q['question'], 0, 60)) . (strlen($q['question']) > 60 ? '...' : ''); ?></td>
                    <td>
                        <?php 
                        $has_all = !empty($q['answers']['A']) && !empty($q['answers']['B']) && 
                                   !empty($q['answers']['C']) && !empty($q['answers']['D']);
                        echo $has_all ? '<span class="badge badge-success">A B C D</span>' : '<span class="badge badge-error">Incomplete</span>';
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($q['correct'])): ?>
                        <strong style="color:#059669;"><?php echo $q['correct']; ?></strong>
                        <?php else: ?>
                        <span class="badge badge-error">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($q['comp_valid']): ?>
                        <span class="code code-valid"><?php echo $q['competency']; ?></span>
                        <?php elseif (!empty($q['competency'])): ?>
                        <span class="code code-invalid"><?php echo $q['competency']; ?></span>
                        <?php if ($q['comp_suggestion']): ?>
                        <br><small>→ <span class="code code-suggestion"><?php echo $q['comp_suggestion']; ?></span></small>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="badge badge-error">Mancante</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($q['is_complete'] && $q['comp_valid']): ?>
                        <span class="badge badge-success">✅ OK</span>
                        <?php elseif ($q['is_complete']): ?>
                        <span class="badge badge-warning">⚠️ Comp.</span>
                        <?php else: ?>
                        <span class="badge badge-error">❌ Incompleta</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="actions-bar">
            <a href="<?php echo $base_url; ?>&action=download_xml" class="btn btn-primary">📥 Scarica XML Moodle</a>
            <a href="<?php echo $base_url; ?>&action=import_direct" class="btn btn-success">🚀 Importa e Crea Quiz</a>
            <a href="<?php echo $base_url; ?>" class="btn btn-secondary">← Carica Altro File</a>
        </div>
    </div>

<?php
        }
    }

// ============================================================
// ACTION: DOWNLOAD XML
// ============================================================
elseif ($action == 'download_xml'):
    
    if (empty($_SESSION['import_word_questions'])) {
        echo '<div class="alert alert-error">❌ Nessuna domanda in sessione. Carica prima un file.</div>';
        echo '<a href="' . $base_url . '" class="btn btn-secondary">← Torna indietro</a>';
    } else {
        $questions = $_SESSION['import_word_questions'];
        $quiz_name = $_SESSION['import_word_quiz_name'];
        
        // Genera XML
        $xml = generate_moodle_xml($questions, $quiz_name, $quiz_name);
        
        // Download
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $quiz_name . '.xml"');
        echo $xml;
        exit;
    }

// ============================================================
// ACTION: IMPORT DIRECT (importa e crea quiz)
// ============================================================
elseif ($action == 'import_direct'):
    
    if (empty($_SESSION['import_word_questions'])) {
        echo '<div class="alert alert-error">❌ Nessuna domanda in sessione. Carica prima un file.</div>';
        echo '<a href="' . $base_url . '" class="btn btn-secondary">← Torna indietro</a>';
    } else {
        $questions = $_SESSION['import_word_questions'];
        $quiz_name = $_SESSION['import_word_quiz_name'];
        $frameworkid = $_SESSION['import_word_frameworkid'];
        
        // Filtra solo complete e valide
        $valid_questions = array_filter($questions, fn($q) => $q['is_complete'] && $q['comp_valid']);
        
        if (empty($valid_questions)) {
            echo '<div class="alert alert-error">❌ Nessuna domanda valida da importare.</div>';
            echo '<a href="' . $base_url . '&action=preview" class="btn btn-secondary">← Torna all\'anteprima</a>';
        } else {
            // Genera XML temporaneo
            $xml = generate_moodle_xml($valid_questions, $quiz_name, $quiz_name);
            $temp_file = $CFG->tempdir . '/' . $quiz_name . '_' . time() . '.xml';
            file_put_contents($temp_file, $xml);
            
            // Importa usando il formato XML di Moodle
            $qformat = new qformat_xml();
            $qformat->setFilename($temp_file);
            $qformat->setContexts([$context]);
            $qformat->setCourse($course);
            
            // Crea categoria
            $category = new stdClass();
            $category->name = $quiz_name;
            $category->contextid = $context->id;
            $category->info = 'Importato da Word - ' . date('Y-m-d H:i');
            $category->infoformat = FORMAT_HTML;
            $category->stamp = make_unique_id_code();
            $category->parent = 0;
            $category->sortorder = 999;
            
            $existing_cat = $DB->get_record('question_categories', [
                'name' => $quiz_name,
                'contextid' => $context->id
            ]);
            
            if ($existing_cat) {
                $category_id = $existing_cat->id;
            } else {
                $category_id = $DB->insert_record('question_categories', $category);
            }
            
            $qformat->setCategory($DB->get_record('question_categories', ['id' => $category_id]));
            
            // Importa
            $imported = 0;
            $errors = [];
            
            if ($qformat->importpreprocess()) {
                if ($questions_imported = $qformat->readquestions($xml)) {
                    foreach ($questions_imported as $q) {
                        try {
                            if ($qformat->importprocess([$q])) {
                                $imported++;
                            }
                        } catch (Exception $e) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
            
            // Pulisci file temporaneo
            @unlink($temp_file);
            
            // Assegna competenze
            $assigned = 0;
            $imported_questions = $DB->get_records_sql(
                "SELECT q.id, q.name
                 FROM {question} q
                 JOIN {question_versions} qv ON qv.questionid = q.id
                 JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid = ?",
                [$category_id]
            );
            
            foreach ($imported_questions as $q) {
                // Estrai codice competenza dal nome
                if (preg_match('/([A-Z]+_[A-Z0-9]+_\d+)/', $q->name, $m)) {
                    $comp_code = $m[1];
                    $comp = $DB->get_record('competency', [
                        'idnumber' => $comp_code,
                        'competencyframeworkid' => $frameworkid
                    ]);
                    
                    if ($comp) {
                        // Verifica se già assegnata
                        $existing = $DB->get_record('qbank_competenciesbyquestion', [
                            'questionid' => $q->id,
                            'competencyid' => $comp->id
                        ]);
                        
                        if (!$existing) {
                            $record = new stdClass();
                            $record->questionid = $q->id;
                            $record->competencyid = $comp->id;
                            $record->timecreated = time();
                            $record->timemodified = time();
                            $DB->insert_record('qbank_competenciesbyquestion', $record);
                            $assigned++;
                        }
                    }
                }
            }
?>
    <div class="alert alert-success">
        ✅ <strong>Importazione completata!</strong>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="number"><?php echo $imported; ?></div>
            <div class="label">Domande Importate</div>
        </div>
        <div class="stat-card blue">
            <div class="number"><?php echo $assigned; ?></div>
            <div class="label">Competenze Assegnate</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo $quiz_name; ?></div>
            <div class="label">Categoria Creata</div>
        </div>
    </div>
    
    <div class="panel">
        <h3>🎯 Prossimi Passi</h3>
        <div class="actions-bar" style="border-top: none; padding-top: 0; margin-top: 0;">
            <a href="create_quiz.php?courseid=<?php echo $courseid; ?>&category=<?php echo $category_id; ?>" class="btn btn-success">🎮 Crea Quiz</a>
            <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=coverage" class="btn btn-primary">📊 Verifica Copertura</a>
            <a href="<?php echo $base_url; ?>" class="btn btn-secondary">📤 Importa Altro File</a>
        </div>
    </div>

<?php
            // Pulisci sessione
            unset($_SESSION['import_word_questions']);
            unset($_SESSION['import_word_quiz_name']);
            unset($_SESSION['import_word_frameworkid']);
        }
    }

endif;

?>
</div>
<?php echo $OUTPUT->footer();
