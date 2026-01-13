<?php
/**
 * IMPORT QUIZ - Wizard Unificato
 * 
 * Funzionalità:
 * - Step 1: Selezione Framework e Settore
 * - Step 2: Carica file Word (.docx) o XML Moodle
 * - Step 3: Anteprima con validazione competenze
 * - Step 4: Import e creazione quiz
 * 
 * Include:
 * - Lista competenze per settore (copia, export CSV)
 * - Template Generator (Word + Guida AI)
 * - Validazione competenze vs framework
 * - Suggerimenti correzione codici errati
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/import.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Import Quiz - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================
// FUNZIONI HELPER
// ============================================================

/**
 * Ottiene tutti i framework disponibili
 */
function get_frameworks() {
    global $DB;
    return $DB->get_records('competency_framework', [], 'shortname', 'id, shortname, idnumber');
}

/**
 * Ottiene i settori di un framework con conteggio competenze
 */
function get_framework_sectors($frameworkid) {
    global $DB;
    
    $sql = "SELECT 
                SUBSTRING_INDEX(idnumber, '_', 1) as sector,
                COUNT(*) as count
            FROM {competency}
            WHERE competencyframeworkid = ?
            AND idnumber REGEXP '^[A-Z]+_'
            GROUP BY SUBSTRING_INDEX(idnumber, '_', 1)
            ORDER BY sector";
    
    return $DB->get_records_sql($sql, [$frameworkid]);
}

/**
 * Ottiene tutte le competenze di un settore
 */
function get_sector_competencies($frameworkid, $sector) {
    global $DB;
    
    $sql = "SELECT id, idnumber, shortname, description
            FROM {competency}
            WHERE competencyframeworkid = ?
            AND idnumber LIKE ?
            ORDER BY idnumber";
    
    return $DB->get_records_sql($sql, [$frameworkid, $sector . '_%']);
}

/**
 * Ottiene i profili/aree di un settore
 */
function get_sector_profiles($frameworkid, $sector) {
    global $DB;
    
    // Estrae la seconda parte del codice (es. OA, MA, 1C, etc.)
    $sql = "SELECT 
                SUBSTRING_INDEX(SUBSTRING_INDEX(idnumber, '_', 2), '_', -1) as profile,
                COUNT(*) as count
            FROM {competency}
            WHERE competencyframeworkid = ?
            AND idnumber LIKE ?
            GROUP BY profile
            ORDER BY profile";
    
    return $DB->get_records_sql($sql, [$frameworkid, $sector . '_%']);
}

/**
 * Estrae testo da file Word (.docx)
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
    
    $lines = [];
    $paragraphs = preg_split('/<w:p[^>]*>/', $xml);
    
    foreach ($paragraphs as $para) {
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $para, $matches)) {
            $para_text = implode('', $matches[1]);
            $para_text = trim($para_text);
            
            if (!empty($para_text)) {
                $lines[] = $para_text;
            }
        }
    }
    
    $content = implode("\n", $lines);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $content;
}

/**
 * Estrae domande dal testo Word
 */
function parse_questions_from_text($text) {
    $questions = [];
    
    // Pre-processing: Separa campi concatenati
    $text = preg_replace('/(?<!^)(?<!\n)(Competenza\s*:)/i', "\n$1", $text);
    $text = preg_replace('/(?<!^)(?<!\n)(Risposta\s*corretta\s*:)/i', "\n$1", $text);
    $text = preg_replace('/(?<!^)(?<!\n)(Codice\s*(?:competenza\s*)?:)/i', "\n$1", $text);
    $text = preg_replace('/(?<=[^\n])([ABCD]\))/i', "\n$1", $text);
    
    // Normalizza
    $text = str_replace('\\)', ')', $text);
    $text = str_replace('\\', '', $text);
    
    $lines = explode("\n", $text);
    $current = null;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        // Numero domanda
        if (preg_match('/^\*?\*?Q(\d+)\*?\*?\.?\s*$/i', $line, $m)) {
            if ($current && !empty($current['question'])) {
                $questions[] = $current;
            }
            $current = [
                'number' => (int)$m[1],
                'question' => '',
                'answers' => ['A' => '', 'B' => '', 'C' => '', 'D' => ''],
                'correct' => '',
                'competency' => ''
            ];
            continue;
        }
        
        if (!$current) continue;
        
        // Competenza
        if (preg_match('/(?:Competenza|Codice(?:\s+competenza)?)\s*:\s*([A-ZÀÈÉÌÒÙ]+_[A-Z0-9]+_[A-Z0-9]+)/i', $line, $m)) {
            $current['competency'] = strtoupper(trim($m[1]));
            continue;
        }
        
        // Risposta corretta
        if (preg_match('/Risposta(?:\s+corretta)?\s*:\s*([A-D])/i', $line, $m)) {
            $current['correct'] = strtoupper($m[1]);
            continue;
        }
        
        // Risposte A-D
        if (preg_match('/^([A-D])[\.\)]\s*(.+)$/i', $line, $m)) {
            $letter = strtoupper($m[1]);
            $current['answers'][$letter] = preg_replace('/\*\*(.+?)\*\*/', '$1', trim($m[2]));
            continue;
        }
        
        // Testo domanda
        if (stripos($line, 'ompetenza') === false && 
            stripos($line, 'isposta') === false &&
            !preg_match('/^[A-D][\.\)]/', $line)) {
            
            $line = preg_replace('/\*\*(.+?)\*\*/', '$1', $line);
            if (empty($current['question'])) {
                $current['question'] = $line;
            } else {
                $current['question'] .= ' ' . $line;
            }
        }
    }
    
    if ($current && !empty($current['question'])) {
        $questions[] = $current;
    }
    
    return $questions;
}

/**
 * Estrae domande da XML Moodle
 */
function parse_questions_from_xml($xml_content) {
    $questions = [];
    
    $dom = new DOMDocument();
    @$dom->loadXML($xml_content);
    
    $xpath = new DOMXPath($dom);
    $question_nodes = $xpath->query('//question[@type="multichoice"]');
    
    $num = 1;
    foreach ($question_nodes as $qnode) {
        $q = [
            'number' => $num++,
            'question' => '',
            'answers' => ['A' => '', 'B' => '', 'C' => '', 'D' => ''],
            'correct' => '',
            'competency' => ''
        ];
        
        // Nome (contiene competenza)
        $name_node = $xpath->query('name/text', $qnode)->item(0);
        if ($name_node) {
            $name = $name_node->nodeValue;
            if (preg_match('/([A-Z]+_[A-Z0-9]+_[A-Z0-9]+)/', $name, $m)) {
                $q['competency'] = $m[1];
            }
        }
        
        // Testo domanda
        $text_node = $xpath->query('questiontext/text', $qnode)->item(0);
        if ($text_node) {
            $q['question'] = strip_tags($text_node->nodeValue);
        }
        
        // Risposte
        $answer_nodes = $xpath->query('answer', $qnode);
        $letters = ['A', 'B', 'C', 'D'];
        $idx = 0;
        foreach ($answer_nodes as $anode) {
            if ($idx >= 4) break;
            $letter = $letters[$idx];
            
            $ans_text = $xpath->query('text', $anode)->item(0);
            if ($ans_text) {
                $q['answers'][$letter] = strip_tags($ans_text->nodeValue);
            }
            
            $fraction = $anode->getAttribute('fraction');
            if ($fraction == '100') {
                $q['correct'] = $letter;
            }
            
            $idx++;
        }
        
        $questions[] = $q;
    }
    
    return $questions;
}

/**
 * Valida competenze contro il framework/settore
 */
function validate_competencies($questions, $frameworkid, $sector) {
    global $DB;
    
    // Carica competenze valide per questo settore
    $valid_comps = $DB->get_fieldset_select('competency', 'idnumber', 
        "competencyframeworkid = ? AND idnumber LIKE ?", 
        [$frameworkid, $sector . '_%']);
    $valid_set = array_flip($valid_comps);
    
    $validated = [];
    foreach ($questions as $q) {
        $q['comp_valid'] = isset($valid_set[$q['competency']]);
        $q['comp_suggestion'] = '';
        $q['comp_wrong_sector'] = false;
        
        // Verifica se competenza esiste ma in altro settore
        if (!$q['comp_valid'] && !empty($q['competency'])) {
            // Estrai settore dalla competenza
            if (preg_match('/^([A-Z]+)_/', $q['competency'], $m)) {
                if ($m[1] != $sector) {
                    $q['comp_wrong_sector'] = true;
                }
            }
            
            // Cerca suggerimento
            $q['comp_suggestion'] = find_similar_competency($q['competency'], $valid_comps);
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
    if (!preg_match('/^([A-Z]+)_([A-Z0-9]+)_(\d+)$/', $wrong_code, $m)) {
        return null;
    }
    
    $sector = $m[1];
    $area = $m[2];
    $num = $m[3];
    
    // Prova formati alternativi del numero
    if (strlen($num) == 3 && $num[0] == '0') {
        $try_code = $sector . '_' . $area . '_' . substr($num, 1);
        if (in_array($try_code, $valid_codes)) return $try_code;
        
        $try_code = $sector . '_' . $area . '_' . ltrim($num, '0');
        if (in_array($try_code, $valid_codes)) return $try_code;
    }
    
    // Cerca nella stessa area
    $base = $sector . '_' . $area . '_';
    foreach ($valid_codes as $vc) {
        if (strpos($vc, $base) === 0) {
            return $vc . ' (simile)';
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
    
    $xml .= '  <question type="category">' . "\n";
    $xml .= '    <category><text>$course$/top/' . htmlspecialchars($category_name) . '</text></category>' . "\n";
    $xml .= '  </question>' . "\n\n";
    
    foreach ($questions as $q) {
        if (!$q['is_complete'] || !$q['comp_valid']) continue;
        
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
        
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $fraction = ($letter == $q['correct']) ? '100' : '0';
            $xml .= '    <answer fraction="' . $fraction . '" format="html">' . "\n";
            $xml .= '      <text><![CDATA[<p>' . htmlspecialchars($q['answers'][$letter]) . '</p>]]></text>' . "\n";
            $xml .= '      <feedback format="html"><text></text></feedback>' . "\n";
            $xml .= '    </answer>' . "\n";
        }
        
        $xml .= '    <tags><tag><text>' . htmlspecialchars($q['competency']) . '</text></tag></tags>' . "\n";
        $xml .= '  </question>' . "\n\n";
    }
    
    $xml .= '</quiz>' . "\n";
    
    return $xml;
}

// ============================================================
// GESTIONE AZIONI SPECIALI (download, export)
// ============================================================

// Export CSV competenze
if ($action == 'export_csv') {
    $frameworkid = required_param('frameworkid', PARAM_INT);
    $sector = required_param('sector', PARAM_ALPHA);
    
    $competencies = get_sector_competencies($frameworkid, $sector);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $sector . '_competenze.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Codice', 'Nome', 'Descrizione']);
    
    foreach ($competencies as $c) {
        fputcsv($output, [$c->idnumber, $c->shortname, strip_tags($c->description)]);
    }
    
    fclose($output);
    exit;
}

// Download template Word
if ($action == 'download_template') {
    $sector = required_param('sector', PARAM_ALPHA);
    $frameworkid = required_param('frameworkid', PARAM_INT);
    
    $competencies = get_sector_competencies($frameworkid, $sector);
    
    // Genera contenuto template
    $content = "QUIZ " . $sector . " - TEMPLATE\n";
    $content .= "=====================================\n\n";
    $content .= "Istruzioni: Compila le domande seguendo il formato sotto.\n";
    $content .= "Usa SOLO i codici competenza elencati.\n\n";
    $content .= "CODICI COMPETENZA DISPONIBILI:\n";
    foreach ($competencies as $c) {
        $content .= "- " . $c->idnumber . ": " . $c->shortname . "\n";
    }
    $content .= "\n=====================================\n\n";
    
    // Esempio domande
    $examples = array_slice(array_values($competencies), 0, 3);
    $num = 1;
    foreach ($examples as $comp) {
        $content .= "**Q" . sprintf('%02d', $num++) . "**\n\n";
        $content .= "[Scrivi qui il testo della domanda per " . $comp->shortname . "]\n\n";
        $content .= "A) [Prima risposta]\n";
        $content .= "B) [Seconda risposta]\n";
        $content .= "C) [Terza risposta]\n";
        $content .= "D) [Quarta risposta]\n\n";
        $content .= "Competenza: " . $comp->idnumber . "\n";
        $content .= "Risposta corretta: [A/B/C/D]\n\n";
    }
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="TEMPLATE_' . $sector . '.txt"');
    echo $content;
    exit;
}

// Download XML generato
if ($action == 'download_xml') {
    if (empty($_SESSION['import_questions'])) {
        redirect(new moodle_url('/local/competencyxmlimport/import.php', ['courseid' => $courseid]));
    }
    
    $questions = $_SESSION['import_questions'];
    $quiz_name = $_SESSION['import_quiz_name'];
    
    $xml = generate_moodle_xml($questions, $quiz_name, $quiz_name);
    
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $quiz_name . '.xml"');
    echo $xml;
    exit;
}

// ============================================================
// CSS
// ============================================================

$css = '
<style>
:root {
    --primary: #7C3AED;
    --primary-dark: #6D28D9;
    --success: #059669;
    --warning: #D97706;
    --danger: #DC2626;
    --info: #2563EB;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-500: #6B7280;
    --gray-700: #374151;
    --gray-900: #111827;
}

.import-wizard {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Header */
.wizard-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
}
.wizard-header h2 { margin: 0 0 8px 0; font-size: 28px; }
.wizard-header p { margin: 0; opacity: 0.9; }

/* Steps indicator */
.steps-indicator {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 30px;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--gray-100);
    border-radius: 25px;
    color: var(--gray-500);
    font-size: 14px;
    font-weight: 500;
}
.step-item.active {
    background: var(--primary);
    color: white;
}
.step-item.completed {
    background: var(--success);
    color: white;
}
.step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

/* Panel */
.panel {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.panel h3 {
    margin: 0 0 20px 0;
    color: var(--gray-900);
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.panel-subtitle {
    color: var(--gray-500);
    font-size: 14px;
    margin-bottom: 20px;
}

/* Form elements */
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--gray-700);
}
.form-group input[type="text"],
.form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}
.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary);
    outline: none;
}

/* Radio cards */
.sector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin: 20px 0;
}
.sector-card {
    padding: 15px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}
.sector-card:hover {
    border-color: var(--primary);
    background: #FAF5FF;
}
.sector-card.selected {
    border-color: var(--primary);
    background: #FAF5FF;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}
.sector-card input { display: none; }
.sector-name {
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 4px;
}
.sector-count {
    font-size: 13px;
    color: var(--gray-500);
}

/* File type selector */
.file-type-selector {
    display: flex;
    gap: 15px;
    margin: 20px 0;
}
.file-type-option {
    flex: 1;
    padding: 20px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}
.file-type-option:hover {
    border-color: var(--primary);
}
.file-type-option.selected {
    border-color: var(--primary);
    background: #FAF5FF;
}
.file-type-option input { display: none; }
.file-type-icon { font-size: 32px; margin-bottom: 8px; }
.file-type-name { font-weight: 600; color: var(--gray-900); }
.file-type-desc { font-size: 12px; color: var(--gray-500); margin-top: 4px; }

/* Upload zone */
.upload-zone {
    border: 3px dashed var(--gray-200);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: var(--gray-50);
    transition: all 0.2s;
    cursor: pointer;
}
.upload-zone:hover {
    border-color: var(--primary);
    background: #FAF5FF;
}
.upload-zone.dragover {
    border-color: var(--primary);
    background: #FAF5FF;
}
.upload-zone input[type="file"] { display: none; }
.upload-icon { font-size: 48px; margin-bottom: 15px; }
.upload-text { color: var(--gray-500); }
.upload-text strong { color: var(--primary); }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
.btn-success { background: linear-gradient(135deg, var(--success), #047857); color: white; }
.btn-secondary { background: var(--gray-500); color: white; }
.btn-outline { background: white; border: 2px solid var(--gray-200); color: var(--gray-700); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-sm { padding: 8px 16px; font-size: 13px; }

.btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

/* Competencies list */
.competencies-panel {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    margin-top: 20px;
}
.competencies-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--gray-200);
    cursor: pointer;
}
.competencies-header h4 { margin: 0; font-size: 14px; color: var(--gray-700); }
.competencies-toggle { color: var(--primary); font-size: 12px; }
.competencies-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s;
}
.competencies-content.expanded { max-height: 400px; overflow-y: auto; }
.competencies-list {
    padding: 15px 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}
.comp-item {
    font-family: monospace;
    font-size: 12px;
    padding: 6px 10px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    color: var(--gray-700);
}
.competencies-actions {
    padding: 10px 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 10px;
}

/* Stats grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    color: white;
}
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 12px; margin-top: 5px; opacity: 0.9; }
.stat-card.purple { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
.stat-card.green { background: linear-gradient(135deg, var(--success), #047857); }
.stat-card.red { background: linear-gradient(135deg, var(--danger), #B91C1C); }
.stat-card.blue { background: linear-gradient(135deg, var(--info), #1D4ED8); }
.stat-card.orange { background: linear-gradient(135deg, var(--warning), #B45309); }

/* Table */
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--gray-200); }
th { background: var(--gray-50); font-weight: 600; color: var(--gray-700); position: sticky; top: 0; }
tr:hover { background: var(--gray-50); }

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.badge-success { background: #D1FAE5; color: #065F46; }
.badge-error { background: #FEE2E2; color: #991B1B; }
.badge-warning { background: #FEF3C7; color: #92400E; }
.badge-info { background: #DBEAFE; color: #1E40AF; }

.code {
    font-family: monospace;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
}
.code-valid { background: #D1FAE5; color: #065F46; }
.code-invalid { background: #FEE2E2; color: #991B1B; }
.code-suggestion { background: #DBEAFE; color: #1E40AF; }

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.alert-icon { font-size: 20px; }
.alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
.alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
.alert-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
.alert-info { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }

/* Navigation */
.wizard-nav {
    display: flex;
    justify-content: space-between;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid var(--gray-100);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 15px;
}
.back-link:hover { text-decoration: underline; }

/* Scrollable */
.scrollable { max-height: 400px; overflow-y: auto; }

/* Info box */
.info-box {
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    border-radius: 10px;
    padding: 15px 20px;
    margin: 20px 0;
}
.info-box h4 { margin: 0 0 8px 0; color: #1E40AF; font-size: 14px; }
.info-box p { margin: 0; color: #1E40AF; font-size: 13px; }

/* Profile badges */
.profiles-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.profile-badge {
    padding: 6px 12px;
    background: var(--gray-100);
    border-radius: 20px;
    font-size: 12px;
    color: var(--gray-700);
}
.profile-badge strong { color: var(--primary); }
</style>';

// ============================================================
// JAVASCRIPT
// ============================================================

$js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Sector card selection
    document.querySelectorAll(".sector-card").forEach(function(card) {
        card.addEventListener("click", function() {
            document.querySelectorAll(".sector-card").forEach(c => c.classList.remove("selected"));
            this.classList.add("selected");
            this.querySelector("input").checked = true;
            
            // Update competencies panel
            var sector = this.querySelector("input").value;
            updateCompetenciesPanel(sector);
        });
    });
    
    // File type selection
    document.querySelectorAll(".file-type-option").forEach(function(opt) {
        opt.addEventListener("click", function() {
            document.querySelectorAll(".file-type-option").forEach(o => o.classList.remove("selected"));
            this.classList.add("selected");
            this.querySelector("input").checked = true;
        });
    });
    
    // Toggle competencies panel
    document.querySelectorAll(".competencies-header").forEach(function(header) {
        header.addEventListener("click", function() {
            var content = this.nextElementSibling;
            var toggle = this.querySelector(".competencies-toggle");
            content.classList.toggle("expanded");
            toggle.textContent = content.classList.contains("expanded") ? "▲ Nascondi" : "▼ Mostra";
        });
    });
    
    // Copy competencies
    window.copyCompetencies = function() {
        var items = document.querySelectorAll(".comp-item");
        var codes = Array.from(items).map(i => i.textContent).join(", ");
        navigator.clipboard.writeText(codes).then(function() {
            alert("Copiati " + items.length + " codici!");
        });
    };
    
    // Drag and drop
    var uploadZone = document.querySelector(".upload-zone");
    if (uploadZone) {
        uploadZone.addEventListener("dragover", function(e) {
            e.preventDefault();
            this.classList.add("dragover");
        });
        uploadZone.addEventListener("dragleave", function() {
            this.classList.remove("dragover");
        });
        uploadZone.addEventListener("drop", function(e) {
            e.preventDefault();
            this.classList.remove("dragover");
            var input = this.querySelector("input[type=file]");
            input.files = e.dataTransfer.files;
            // Show filename
            if (e.dataTransfer.files.length > 0) {
                this.querySelector(".upload-text").innerHTML = "<strong>" + e.dataTransfer.files[0].name + "</strong> selezionato";
            }
        });
        
        var fileInput = uploadZone.querySelector("input[type=file]");
        if (fileInput) {
            fileInput.addEventListener("change", function() {
                if (this.files.length > 0) {
                    uploadZone.querySelector(".upload-text").innerHTML = "<strong>" + this.files[0].name + "</strong> selezionato";
                }
            });
        }
    }
});
</script>';

// ============================================================
// OUTPUT
// ============================================================

echo $OUTPUT->header();
echo $css;
echo $js;

$base_url = new moodle_url('/local/competencyxmlimport/import.php', ['courseid' => $courseid]);
$dashboard_url = new moodle_url('/local/competencyxmlimport/dashboard.php', ['courseid' => $courseid]);

// Lista framework
$frameworks = get_frameworks();
$default_frameworkid = optional_param('frameworkid', 0, PARAM_INT);
if (!$default_frameworkid && !empty($frameworks)) {
    $default_frameworkid = array_key_first($frameworks);
}

?>
<div class="import-wizard">
    <a href="<?php echo $dashboard_url; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="wizard-header">
        <h2>📥 Import Quiz</h2>
        <p>Importa domande da file Word o XML Moodle con assegnazione automatica competenze</p>
    </div>
    
    <!-- Steps Indicator -->
    <div class="steps-indicator">
        <div class="step-item <?php echo $step == 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
            <span class="step-num"><?php echo $step > 1 ? '✓' : '1'; ?></span>
            Framework
        </div>
        <div class="step-item <?php echo $step == 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
            <span class="step-num"><?php echo $step > 2 ? '✓' : '2'; ?></span>
            Carica File
        </div>
        <div class="step-item <?php echo $step == 3 ? 'active' : ($step > 3 ? 'completed' : ''); ?>">
            <span class="step-num"><?php echo $step > 3 ? '✓' : '3'; ?></span>
            Anteprima
        </div>
        <div class="step-item <?php echo $step == 4 ? 'active' : ''; ?>">
            <span class="step-num">4</span>
            Import
        </div>
    </div>

<?php

// ============================================================
// STEP 1: Selezione Framework e Settore
// ============================================================
if ($step == 1):
    
    $sectors = get_framework_sectors($default_frameworkid);
    
?>
    <form method="POST" action="<?php echo $base_url; ?>&step=2">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <div class="panel">
            <h3>📚 Seleziona Framework</h3>
            
            <div class="form-group">
                <label>Framework Competenze</label>
                <select name="frameworkid" id="frameworkSelect" onchange="location.href='<?php echo $base_url; ?>&frameworkid='+this.value">
                    <?php foreach ($frameworks as $f): ?>
                    <option value="<?php echo $f->id; ?>" <?php echo $f->id == $default_frameworkid ? 'selected' : ''; ?>>
                        <?php echo $f->shortname; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="panel">
            <h3>🏭 Seleziona Settore</h3>
            <p class="panel-subtitle">Scegli il settore per cui vuoi importare le domande</p>
            
            <div class="sector-grid">
                <?php foreach ($sectors as $s): ?>
                <label class="sector-card">
                    <input type="radio" name="sector" value="<?php echo $s->sector; ?>" required>
                    <div class="sector-name"><?php echo $s->sector; ?></div>
                    <div class="sector-count"><?php echo $s->count; ?> competenze</div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <?php 
            // Mostra dettagli primo settore come esempio
            $first_sector = reset($sectors);
            if ($first_sector):
                $profiles = get_sector_profiles($default_frameworkid, $first_sector->sector);
                $competencies = get_sector_competencies($default_frameworkid, $first_sector->sector);
            ?>
            
            <div class="info-box" id="sectorInfo">
                <h4>ℹ️ Dettagli Settore Selezionato</h4>
                <p>Seleziona un settore per vedere i dettagli delle competenze disponibili.</p>
            </div>
            
            <!-- Competencies Panel -->
            <div class="competencies-panel">
                <div class="competencies-header">
                    <h4>📋 Lista Competenze (<?php echo count($competencies); ?>)</h4>
                    <span class="competencies-toggle">▼ Mostra</span>
                </div>
                <div class="competencies-content">
                    <div class="competencies-list">
                        <?php foreach ($competencies as $c): ?>
                        <span class="comp-item" title="<?php echo htmlspecialchars($c->shortname); ?>"><?php echo $c->idnumber; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="competencies-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="copyCompetencies()">📋 Copia Codici</button>
                    <a href="<?php echo $base_url; ?>&action=export_csv&frameworkid=<?php echo $default_frameworkid; ?>&sector=<?php echo $first_sector->sector; ?>" class="btn btn-sm btn-outline">📥 Export CSV</a>
                    <a href="<?php echo $base_url; ?>&action=download_template&frameworkid=<?php echo $default_frameworkid; ?>&sector=<?php echo $first_sector->sector; ?>" class="btn btn-sm btn-outline">📄 Scarica Template</a>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
        
        <div class="panel">
            <h3>📄 Risorse per la Creazione Domande</h3>
            <div class="btn-group">
                <a href="FTM_GUIDA_FORMATO_QUIZ_PER_AI.md" class="btn btn-outline" download>📖 Guida Formato per AI</a>
                <a href="FTM_TEMPLATE_QUIZ_STANDARD.docx" class="btn btn-outline" download>📝 Template Word Standard</a>
            </div>
        </div>
        
        <div class="wizard-nav">
            <div></div>
            <button type="submit" class="btn btn-primary">Continua ▶</button>
        </div>
    </form>

<?php

// ============================================================
// STEP 2: Carica File
// ============================================================
elseif ($step == 2):
    
    require_sesskey();
    
    $frameworkid = required_param('frameworkid', PARAM_INT);
    $sector = required_param('sector', PARAM_ALPHA);
    
    $competencies = get_sector_competencies($frameworkid, $sector);
    $profiles = get_sector_profiles($frameworkid, $sector);
    
    // Salva in sessione
    $_SESSION['import_frameworkid'] = $frameworkid;
    $_SESSION['import_sector'] = $sector;
    
?>
    <form method="POST" action="<?php echo $base_url; ?>&step=3" enctype="multipart/form-data">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="frameworkid" value="<?php echo $frameworkid; ?>">
        <input type="hidden" name="sector" value="<?php echo $sector; ?>">
        
        <div class="alert alert-info">
            <span class="alert-icon">ℹ️</span>
            <div>
                <strong>Settore selezionato: <?php echo $sector; ?></strong><br>
                <?php echo count($competencies); ?> competenze disponibili in <?php echo count($profiles); ?> profili
                <div class="profiles-list">
                    <?php foreach ($profiles as $p): ?>
                    <span class="profile-badge"><strong><?php echo $p->profile; ?></strong> (<?php echo $p->count; ?>)</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <h3>📁 Tipo File</h3>
            
            <div class="file-type-selector">
                <label class="file-type-option selected">
                    <input type="radio" name="file_type" value="word" checked>
                    <div class="file-type-icon">📄</div>
                    <div class="file-type-name">Word (.docx)</div>
                    <div class="file-type-desc">File Word con domande formattate</div>
                </label>
                <label class="file-type-option">
                    <input type="radio" name="file_type" value="xml">
                    <div class="file-type-icon">📋</div>
                    <div class="file-type-name">XML Moodle</div>
                    <div class="file-type-desc">File XML esportato da Moodle</div>
                </label>
            </div>
        </div>
        
        <div class="panel">
            <h3>📤 Carica File</h3>
            
            <label class="upload-zone">
                <input type="file" name="quizfile" accept=".docx,.xml" required>
                <div class="upload-icon">📁</div>
                <div class="upload-text">
                    <strong>Clicca per selezionare</strong> o trascina un file qui
                </div>
            </label>
            
            <div class="form-group" style="margin-top: 20px;">
                <label>Nome Quiz</label>
                <input type="text" name="quiz_name" placeholder="es. <?php echo $sector; ?>_TEST_BASE" required 
                       pattern="[A-Za-z0-9_]+" title="Solo lettere, numeri e underscore">
            </div>
        </div>
        
        <!-- Quick reference competencies -->
        <div class="panel">
            <h3>📋 Competenze Attese per <?php echo $sector; ?></h3>
            <div class="competencies-panel" style="margin-top: 0;">
                <div class="competencies-header">
                    <h4><?php echo count($competencies); ?> competenze disponibili</h4>
                    <span class="competencies-toggle">▼ Mostra</span>
                </div>
                <div class="competencies-content">
                    <div class="competencies-list">
                        <?php foreach ($competencies as $c): ?>
                        <span class="comp-item"><?php echo $c->idnumber; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="competencies-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="copyCompetencies()">📋 Copia</button>
                    <a href="<?php echo $base_url; ?>&action=export_csv&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>" class="btn btn-sm btn-outline">📥 CSV</a>
                </div>
            </div>
        </div>
        
        <div class="wizard-nav">
            <a href="<?php echo $base_url; ?>&step=1&frameworkid=<?php echo $frameworkid; ?>" class="btn btn-secondary">◀ Indietro</a>
            <button type="submit" class="btn btn-primary">Analizza File ▶</button>
        </div>
    </form>

<?php

// ============================================================
// STEP 3: Anteprima
// ============================================================
elseif ($step == 3):
    
    require_sesskey();
    
    $frameworkid = required_param('frameworkid', PARAM_INT);
    $sector = required_param('sector', PARAM_ALPHA);
    $file_type = required_param('file_type', PARAM_ALPHA);
    $quiz_name = required_param('quiz_name', PARAM_ALPHANUMEXT);
    
    $questions = [];
    $error = '';
    
    if (empty($_FILES['quizfile']['tmp_name'])) {
        $error = 'Nessun file caricato';
    } else {
        $filepath = $_FILES['quizfile']['tmp_name'];
        $filename = $_FILES['quizfile']['name'];
        
        if ($file_type == 'word') {
            // Parse Word
            $text = extract_text_from_docx($filepath);
            if (empty($text)) {
                $error = 'Impossibile leggere il file Word';
            } else {
                $questions = parse_questions_from_text($text);
            }
        } else {
            // Parse XML
            $xml_content = file_get_contents($filepath);
            if (empty($xml_content)) {
                $error = 'Impossibile leggere il file XML';
            } else {
                $questions = parse_questions_from_xml($xml_content);
            }
        }
    }
    
    if ($error):
?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div><?php echo $error; ?></div>
    </div>
    <a href="<?php echo $base_url; ?>&step=2&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>" class="btn btn-secondary">◀ Torna indietro</a>

<?php
    else:
        // Valida competenze
        $questions = validate_competencies($questions, $frameworkid, $sector);
        
        // Statistiche
        $total = count($questions);
        $complete = count(array_filter($questions, fn($q) => $q['is_complete']));
        $valid_comp = count(array_filter($questions, fn($q) => $q['comp_valid']));
        $invalid_comp = count(array_filter($questions, fn($q) => !$q['comp_valid'] && !empty($q['competency'])));
        $missing_comp = count(array_filter($questions, fn($q) => empty($q['competency'])));
        $wrong_sector = count(array_filter($questions, fn($q) => $q['comp_wrong_sector'] ?? false));
        $ready = count(array_filter($questions, fn($q) => $q['is_complete'] && $q['comp_valid']));
        
        // Salva in sessione
        $_SESSION['import_questions'] = $questions;
        $_SESSION['import_quiz_name'] = $quiz_name;
        $_SESSION['import_frameworkid'] = $frameworkid;
        $_SESSION['import_sector'] = $sector;
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
        <div class="stat-card green">
            <div class="number"><?php echo $ready; ?></div>
            <div class="label">Pronte per Import</div>
        </div>
    </div>
    
    <?php if ($wrong_sector > 0): ?>
    <div class="alert alert-warning">
        <span class="alert-icon">⚠️</span>
        <div>
            <strong><?php echo $wrong_sector; ?> domande</strong> hanno competenze di un settore diverso da <?php echo $sector; ?>.
            Verifica che il file sia corretto.
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($invalid_comp > 0): ?>
    <div class="alert alert-warning">
        <span class="alert-icon">⚠️</span>
        <div>
            <strong><?php echo $invalid_comp; ?> competenze</strong> non esistono nel framework. Verifica i suggerimenti nella tabella.
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($missing_comp > 0): ?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div>
            <strong><?php echo $missing_comp; ?> domande</strong> senza codice competenza. Non saranno importate.
        </div>
    </div>
    <?php endif; ?>
    
    <div class="panel">
        <h3>📋 Anteprima Domande</h3>
        <div class="table-container scrollable">
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
                    $row_style = '';
                    if (!$q['is_complete']) $row_style = 'background:#FEF2F2;';
                    elseif (!$q['comp_valid']) $row_style = 'background:#FFFBEB;';
                ?>
                <tr style="<?php echo $row_style; ?>">
                    <td><strong>Q<?php echo sprintf('%02d', $q['number']); ?></strong></td>
                    <td><?php echo htmlspecialchars(mb_substr($q['question'], 0, 50)) . (mb_strlen($q['question']) > 50 ? '...' : ''); ?></td>
                    <td>
                        <?php 
                        $has_all = !empty($q['answers']['A']) && !empty($q['answers']['B']) && 
                                   !empty($q['answers']['C']) && !empty($q['answers']['D']);
                        echo $has_all ? '<span class="badge badge-success">A B C D</span>' : '<span class="badge badge-error">Incomplete</span>';
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($q['correct'])): ?>
                        <strong style="color:var(--success);"><?php echo $q['correct']; ?></strong>
                        <?php else: ?>
                        <span class="badge badge-error">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($q['comp_valid']): ?>
                        <span class="code code-valid"><?php echo $q['competency']; ?></span>
                        <?php elseif (!empty($q['competency'])): ?>
                        <span class="code code-invalid"><?php echo $q['competency']; ?></span>
                        <?php if (!empty($q['comp_suggestion'])): ?>
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
    </div>
    
    <div class="wizard-nav">
        <a href="<?php echo $base_url; ?>&step=2&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>" class="btn btn-secondary">◀ Indietro</a>
        <div class="btn-group">
            <a href="<?php echo $base_url; ?>&action=download_xml" class="btn btn-outline">📥 Scarica XML</a>
            <a href="<?php echo $base_url; ?>&step=4" class="btn btn-success">🚀 Importa <?php echo $ready; ?> domande ▶</a>
        </div>
    </div>

<?php
    endif;

// ============================================================
// STEP 4: Import
// ============================================================
elseif ($step == 4):
    
    if (empty($_SESSION['import_questions'])) {
        redirect(new moodle_url('/local/competencyxmlimport/import.php', ['courseid' => $courseid]));
    }
    
    $questions = $_SESSION['import_questions'];
    $quiz_name = $_SESSION['import_quiz_name'];
    $frameworkid = $_SESSION['import_frameworkid'];
    $sector = $_SESSION['import_sector'];
    
    // Filtra solo complete e valide
    $valid_questions = array_filter($questions, fn($q) => $q['is_complete'] && $q['comp_valid']);
    
    if (empty($valid_questions)):
?>
    <div class="alert alert-error">
        <span class="alert-icon">❌</span>
        <div>Nessuna domanda valida da importare.</div>
    </div>
    <a href="<?php echo $base_url; ?>&step=1" class="btn btn-secondary">◀ Ricomincia</a>

<?php
    else:
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
        
        // Crea o trova categoria
        $existing_cat = $DB->get_record('question_categories', [
            'name' => $quiz_name,
            'contextid' => $context->id
        ]);
        
        if ($existing_cat) {
            $category_id = $existing_cat->id;
        } else {
            $category = new stdClass();
            $category->name = $quiz_name;
            $category->contextid = $context->id;
            $category->info = 'Importato da ' . $sector . ' - ' . date('Y-m-d H:i');
            $category->infoformat = FORMAT_HTML;
            $category->stamp = make_unique_id_code();
            $category->parent = 0;
            $category->sortorder = 999;
            $category_id = $DB->insert_record('question_categories', $category);
        }
        
        $imported = 0;
        $assigned = 0;
        $errors = [];
        
        // Importa ogni domanda direttamente nel database
        foreach ($valid_questions as $q) {
            try {
                // Nome domanda con competenza
                $prefix = preg_replace('/[^A-Z0-9_]/i', '_', $quiz_name);
                $q_name = sprintf('%s_Q%02d - %s', $prefix, $q['number'], $q['competency']);
                
                // Prepara la struttura domanda per Moodle
                $question = new stdClass();
                $question->category = $category_id;
                $question->parent = 0;
                $question->name = $q_name;
                $question->questiontext = '<p>' . htmlspecialchars($q['question']) . '</p>';
                $question->questiontextformat = FORMAT_HTML;
                $question->generalfeedback = '';
                $question->generalfeedbackformat = FORMAT_HTML;
                $question->defaultmark = 1;
                $question->penalty = 0.3333333;
                $question->qtype = 'multichoice';
                $question->length = 1;
                $question->stamp = make_unique_id_code();
                $question->timecreated = time();
                $question->timemodified = time();
                $question->createdby = $USER->id;
                $question->modifiedby = $USER->id;
                
                // Inserisci domanda base
                $question->id = $DB->insert_record('question', $question);
                
                // Crea question_bank_entry (Moodle 4.0+)
                $qbe = new stdClass();
                $qbe->questioncategoryid = $category_id;
                $qbe->idnumber = null;
                $qbe->ownerid = $USER->id;
                $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
                
                // Crea question_version
                $qv = new stdClass();
                $qv->questionbankentryid = $qbe->id;
                $qv->questionid = $question->id;
                $qv->version = 1;
                $qv->status = 'ready';
                $DB->insert_record('question_versions', $qv);
                
                // Opzioni multichoice
                $options = new stdClass();
                $options->questionid = $question->id;
                $options->layout = 0;
                $options->single = 1;
                $options->shuffleanswers = 1;
                $options->correctfeedback = 'Risposta corretta!';
                $options->correctfeedbackformat = FORMAT_HTML;
                $options->partiallycorrectfeedback = '';
                $options->partiallycorrectfeedbackformat = FORMAT_HTML;
                $options->incorrectfeedback = 'Risposta errata.';
                $options->incorrectfeedbackformat = FORMAT_HTML;
                $options->answernumbering = 'abc';
                $options->shownumcorrect = 0;
                $options->showstandardinstruction = 0;
                $DB->insert_record('qtype_multichoice_options', $options);
                
                // Inserisci le 4 risposte
                $letters = ['A', 'B', 'C', 'D'];
                foreach ($letters as $letter) {
                    $answer = new stdClass();
                    $answer->question = $question->id;
                    $answer->answer = '<p>' . htmlspecialchars($q['answers'][$letter]) . '</p>';
                    $answer->answerformat = FORMAT_HTML;
                    $answer->fraction = ($letter == $q['correct']) ? 1.0 : 0.0;
                    $answer->feedback = '';
                    $answer->feedbackformat = FORMAT_HTML;
                    $DB->insert_record('question_answers', $answer);
                }
                
                $imported++;
                
                // Assegna competenza
                $comp = $DB->get_record('competency', [
                    'idnumber' => $q['competency'],
                    'competencyframeworkid' => $frameworkid
                ]);
                
                if ($comp) {
                    $existing = $DB->get_record('qbank_competenciesbyquestion', [
                        'questionid' => $question->id,
                        'competencyid' => $comp->id
                    ]);
                    
                    if (!$existing) {
                        $record = new stdClass();
                        $record->questionid = $question->id;
                        $record->competencyid = $comp->id;
                        $record->timecreated = time();
                        $record->timemodified = time();
                        $DB->insert_record('qbank_competenciesbyquestion', $record);
                        $assigned++;
                    }
                }
                
            } catch (Exception $e) {
                $errors[] = "Q{$q['number']}: " . $e->getMessage();
            }
        }
        
        // Pulisci sessione
        unset($_SESSION['import_questions']);
        unset($_SESSION['import_quiz_name']);
        unset($_SESSION['import_frameworkid']);
        unset($_SESSION['import_sector']);
?>

    <div class="alert alert-success">
        <span class="alert-icon">✅</span>
        <div><strong>Importazione completata con successo!</strong></div>
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
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
        <span class="alert-icon">⚠️</span>
        <div>
            <strong>Alcuni errori durante l'import:</strong><br>
            <?php echo implode('<br>', array_slice($errors, 0, 5)); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="panel">
        <h3>🎯 Prossimi Passi</h3>
        <div class="btn-group">
            <a href="create_quiz.php?courseid=<?php echo $courseid; ?>&category=<?php echo $category_id; ?>" class="btn btn-success">🎮 Crea Quiz</a>
            <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>&action=coverage" class="btn btn-primary">📊 Verifica Copertura</a>
            <a href="<?php echo $base_url; ?>" class="btn btn-outline">📤 Importa Altro File</a>
            <a href="<?php echo $dashboard_url; ?>" class="btn btn-secondary">← Dashboard</a>
        </div>
    </div>

<?php
    endif;

endif;

?>
</div>
<?php 

echo $OUTPUT->footer();
