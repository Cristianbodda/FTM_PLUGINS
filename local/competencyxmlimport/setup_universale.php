<?php
/**
 * Setup Universale Quiz e Competenze
 * 
 * Strumento unico per creare quiz e assegnare competenze
 * per qualsiasi framework e settore
 * 
 * @package    local_competencyxmlimport
 * @author     Assistente AI per Cristian
 * @version    1.0
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/classes/xml_validator.php');
require_once(__DIR__ . '/classes/word_import_helper.php');
require_once(__DIR__ . '/classes/excel_quiz_importer.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/setup_universale.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Setup Universale Quiz');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================================
// GESTIONE AZIONI WORD IMPORT (deve essere PRIMA di qualsiasi output)
// ============================================================================

// Azione: Converti Word in XML
if ($action === 'convertword' && confirm_sesskey()) {
    if (isset($SESSION->word_import_questions)) {
        $quiz_name = required_param('quiz_name', PARAM_TEXT);
        $xml_dir = __DIR__ . '/xml/';
        
        // Crea cartella se non esiste
        if (!is_dir($xml_dir)) {
            mkdir($xml_dir, 0755, true);
        }
        
        // Converti e salva
        $save_result = save_word_questions_as_xml(
            $SESSION->word_import_questions,
            $quiz_name,
            $xml_dir
        );
        
        if ($save_result['success']) {
            // Pulisci sessione Word
            unset($SESSION->word_import_questions);
            unset($SESSION->word_import_filename);
            unset($SESSION->word_import_suggested_name);
            unset($SESSION->word_import_valid_competencies);
            unset($SESSION->word_import_sector);
            
            redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php', 
                ['courseid' => $courseid, 'step' => 3]),
                '✅ File Word convertito in XML: ' . $save_result['filename'],
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php', 
                ['courseid' => $courseid, 'step' => 3]),
                '❌ Errore conversione: ' . $save_result['error'],
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
}

// Pulisci sessione Word se richiesto
if (optional_param('clear_word', 0, PARAM_INT)) {
    unset($SESSION->word_import_questions);
    unset($SESSION->word_import_filename);
    unset($SESSION->word_import_suggested_name);
    unset($SESSION->word_import_valid_competencies);
    unset($SESSION->word_import_sector);
    redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php',
        ['courseid' => $courseid, 'step' => 3]));
}

// Pulisci sessione Excel se richiesto
if (optional_param('clear_excel', 0, PARAM_INT)) {
    if (isset($SESSION->excel_import_file) && file_exists($SESSION->excel_import_file)) {
        @unlink($SESSION->excel_import_file);
    }
    unset($SESSION->excel_import_file);
    unset($SESSION->excel_import_filename);
    redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php',
        ['courseid' => $courseid, 'step' => 3]));
}

// Elimina file XML se richiesto
if ($action === 'deletefile' && confirm_sesskey()) {
    $filename = required_param('filename', PARAM_FILE);
    $xml_dir = __DIR__ . '/xml/';
    $filepath = $xml_dir . $filename;

    // Sicurezza: verifica che il file sia nella cartella xml e sia un .xml
    if (file_exists($filepath) &&
        realpath(dirname($filepath)) === realpath($xml_dir) &&
        strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml') {
        unlink($filepath);
        redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php',
            ['courseid' => $courseid, 'step' => 3]),
            '🗑️ File eliminato: ' . $filename,
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

// Elimina TUTTI i file XML se richiesto
if ($action === 'deleteallxml' && confirm_sesskey()) {
    $xml_dir = __DIR__ . '/xml/';
    $deleted = 0;
    if (is_dir($xml_dir)) {
        foreach (glob($xml_dir . '*.xml') as $file) {
            unlink($file);
            $deleted++;
        }
    }
    redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php',
        ['courseid' => $courseid, 'step' => 3]),
        '🗑️ Eliminati ' . $deleted . ' file XML',
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

// CSS Completo
$css = '
<style>
/* Layout principale */
.setup-page { max-width: 1100px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; }
.back-link:hover { text-decoration: underline; }

/* Header con gradient */
.setup-header { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    color: white; 
    padding: 30px; 
    border-radius: 16px; 
    margin-bottom: 25px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}
.setup-header h2 { margin: 0 0 8px 0; font-size: 28px; }
.setup-header p { margin: 0; opacity: 0.9; }

/* Steps indicator */
.steps-container { 
    display: flex; 
    justify-content: space-between; 
    margin-bottom: 30px; 
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.step-item { 
    flex: 1; 
    text-align: center; 
    position: relative;
    padding: 10px;
}
.step-item:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 25px;
    right: -50%;
    width: 100%;
    height: 3px;
    background: #e0e0e0;
    z-index: 0;
}
.step-item.completed:not(:last-child)::after { background: #667eea; }
.step-item.active:not(:last-child)::after { background: linear-gradient(90deg, #667eea 50%, #e0e0e0 50%); }

.step-number { 
    width: 50px; 
    height: 50px; 
    border-radius: 50%; 
    background: #e0e0e0; 
    color: #666;
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin: 0 auto 10px;
    font-weight: bold;
    font-size: 18px;
    position: relative;
    z-index: 1;
    transition: all 0.3s ease;
}
.step-item.active .step-number { background: #667eea; color: white; transform: scale(1.1); }
.step-item.completed .step-number { background: #27ae60; color: white; }
.step-label { font-size: 13px; color: #666; }
.step-item.active .step-label { color: #667eea; font-weight: 600; }
.step-item.completed .step-label { color: #27ae60; }

/* Panels */
.panel { 
    background: white; 
    border-radius: 12px; 
    padding: 25px; 
    box-shadow: 0 2px 15px rgba(0,0,0,0.08); 
    margin-bottom: 20px;
    border: 1px solid #eee;
}
.panel h3 { 
    margin: 0 0 20px 0; 
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0; 
    color: #333;
    font-size: 18px;
}
.panel h3 .icon { margin-right: 10px; }

/* Form elements */
.form-group { margin-bottom: 20px; }
.form-group label { 
    display: block; 
    margin-bottom: 8px; 
    font-weight: 600; 
    color: #333;
}
.form-group .hint { 
    font-size: 12px; 
    color: #888; 
    margin-top: 5px; 
}

select, input[type="text"], input[type="number"] {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.3s, box-shadow 0.3s;
}
select:focus, input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* File upload area */
.upload-area {
    border: 3px dashed #ddd;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #fafafa;
    transition: all 0.3s ease;
    cursor: pointer;
}
.upload-area:hover, .upload-area.dragover {
    border-color: #667eea;
    background: #f0f4ff;
}
.upload-area .icon { font-size: 48px; margin-bottom: 15px; }
.upload-area p { margin: 0; color: #666; }
.upload-area input[type="file"] { display: none; }

/* File list */
.file-list { margin-top: 20px; }
.file-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    border: 1px solid #eee;
}
.file-item .file-icon { font-size: 24px; margin-right: 15px; }
.file-item .file-info { flex: 1; }
.file-item .file-name { font-weight: 500; color: #333; }
.file-item .file-meta { font-size: 12px; color: #888; }
.file-item .file-remove { 
    color: #e74c3c; 
    cursor: pointer; 
    padding: 5px 10px;
    border-radius: 4px;
}
.file-item .file-remove:hover { background: #fdeaea; }

/* Quiz configuration */
.quiz-config-item {
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
}
.quiz-config-item .quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.quiz-config-item .quiz-title { font-weight: 600; color: #333; }
.quiz-config-item .quiz-badge { 
    padding: 4px 12px; 
    border-radius: 20px; 
    font-size: 12px;
    font-weight: 500;
}
.badge-base { background: #e3f2fd; color: #1976d2; }
.badge-inter { background: #fff3e0; color: #f57c00; }
.badge-adv { background: #fce4ec; color: #c2185b; }

.quiz-config-item .quiz-fields {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 15px;
}
.quiz-config-item input, .quiz-config-item select {
    padding: 10px 12px;
    font-size: 14px;
}

/* Buttons */
.btn { 
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 15px;
    border: none; 
    cursor: pointer; 
    transition: all 0.3s ease;
}
.btn-primary { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.btn-primary:hover { 
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn-success { background: #27ae60; color: white; }
.btn-danger { background: #e74c3c; color: white; }

.btn-group { 
    display: flex; 
    gap: 15px; 
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

/* Stats cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    border: 1px solid #eee;
}
.stat-card .stat-value { 
    font-size: 32px; 
    font-weight: bold; 
    color: #667eea;
}
.stat-card .stat-label { 
    font-size: 13px; 
    color: #888;
    margin-top: 5px;
}
.stat-card.success .stat-value { color: #27ae60; }
.stat-card.warning .stat-value { color: #f39c12; }
.stat-card.info .stat-value { color: #3498db; }

/* Sector selector */
.sector-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}
.sector-card {
    border: 2px solid #eee;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.sector-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}
.sector-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
}
.sector-card .sector-icon { font-size: 36px; margin-bottom: 10px; }
.sector-card .sector-name { font-weight: 600; color: #333; }
.sector-card .sector-count { font-size: 13px; color: #888; }

/* Progress log */
.progress-log {
    background: #1e1e1e;
    border-radius: 10px;
    padding: 20px;
    font-family: "Fira Code", monospace;
    font-size: 13px;
    max-height: 400px;
    overflow-y: auto;
    color: #ccc;
}
.progress-log .log-line { margin-bottom: 8px; }
.progress-log .success { color: #27ae60; }
.progress-log .error { color: #e74c3c; }
.progress-log .info { color: #3498db; }
.progress-log .warning { color: #f39c12; }

/* Competency mapping table */
.mapping-table { width: 100%; border-collapse: collapse; }
.mapping-table th, .mapping-table td { 
    padding: 12px; 
    text-align: left; 
    border-bottom: 1px solid #eee;
}
.mapping-table th { background: #f8f9fa; font-weight: 600; }
.mapping-table tr:hover { background: #fafafa; }
.mapping-table .code { 
    font-family: monospace; 
    background: #e8f5e9; 
    padding: 3px 8px; 
    border-radius: 4px;
    color: #2e7d32;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .sector-grid { grid-template-columns: 1fr; }
    .quiz-config-item .quiz-fields { grid-template-columns: 1fr; }
    .steps-container { flex-wrap: wrap; }
    .step-item { flex: 0 0 50%; margin-bottom: 15px; }
}

/* Validazione XML */
.validation-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}
.validation-badge.ok { background: #e8f5e9; color: #2e7d32; }
.validation-badge.warning { background: #fff3e0; color: #f57c00; }
.validation-badge.error { background: #ffebee; color: #c62828; }

.validation-details-toggle {
    color: #667eea;
    cursor: pointer;
    font-size: 13px;
    margin-left: 10px;
}
.validation-details-toggle:hover { text-decoration: underline; }

.validation-details-panel {
    display: none;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    border: 1px solid #eee;
}
.validation-details-panel.show { display: block; }

.validation-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 5px;
    font-size: 13px;
}
.validation-item.ok { background: #e8f5e9; }
.validation-item.warning { background: #fff3e0; }
.validation-item.error { background: #ffebee; }
.validation-item .q-name { flex: 1; font-family: monospace; }
.validation-item .q-issue { color: #666; font-size: 12px; }

/* Template download grid */
.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.template-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}
.template-card:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.template-card .icon { font-size: 32px; margin-bottom: 10px; }
.template-card .title { font-weight: 600; color: #333; margin-bottom: 5px; }
.template-card .desc { font-size: 12px; color: #888; }
</style>
';

echo $OUTPUT->header();
echo $css;

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Ottiene tutti i framework disponibili
 */
function get_frameworks() {
    global $DB;
    return $DB->get_records('competency_framework', [], 'shortname ASC', 'id, shortname, idnumber, description');
}

/**
 * Ottiene i settori (prefissi) di un framework
 */
function get_framework_sectors($frameworkid) {
    global $DB;

    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber');

    $sectors = [];
    foreach ($competencies as $c) {
        // Estrae il prefisso (tutto prima del primo underscore)
        // Supporta anche caratteri accentati come ELETTRICITÀ
        if (preg_match('/^([A-ZÀ-Ž]+)_/u', $c->idnumber, $m)) {
            $prefix = $m[1];
            if (!isset($sectors[$prefix])) {
                $sectors[$prefix] = ['name' => $prefix, 'count' => 0];
            }
            $sectors[$prefix]['count']++;
        }
    }

    // Ordina alfabeticamente
    ksort($sectors);

    return $sectors;
}

/**
 * Ottiene le competenze di un settore
 */
function get_sector_competencies($frameworkid, $sector) {
    global $DB;
    return $DB->get_records_sql("
        SELECT id, idnumber, shortname, description 
        FROM {competency} 
        WHERE competencyframeworkid = ? AND idnumber LIKE ?
        ORDER BY idnumber
    ", [$frameworkid, $sector . '_%']);
}

/**
 * Mapping alias settori (nome nel file → nome nel database)
 * Permette di usare nomi alternativi nei file Word/Excel
 */
function get_sector_aliases() {
    // Mappatura alias → settore standard nel framework FTM
    // IMPORTANTE: I settori nel framework sono:
    // 01=AUTOMOBILE, 02=CHIMFARM, 03=ELETTRICITÀ, 04=AUTOMAZIONE,
    // 05=LOGISTICA, 06=MECCANICA, 07=METALCOSTRUZIONE
    return [
        // Automobile (NON usare AUTO - ambiguo con AUTOMAZIONE)
        'AUTOVEICOLO' => 'AUTOMOBILE',

        // Automazione
        'AUTOM' => 'AUTOMAZIONE',
        'AUTOMAZ' => 'AUTOMAZIONE',

        // Meccanica
        'MECC' => 'MECCANICA',

        // Metalcostruzione (settore separato da Meccanica)
        'METAL' => 'METALCOSTRUZIONE',

        // Chimica/Farmaceutica
        'CHIM' => 'CHIMFARM',
        'CHIMICA' => 'CHIMFARM',
        'FARMACEUTICA' => 'CHIMFARM',

        // Elettricità (NOTA: nel database ha l'accento À)
        'ELETTRICITA' => 'ELETTRICITÀ',  // Senza accento → con accento
        'ELETTR' => 'ELETTRICITÀ',
        'ELETT' => 'ELETTRICITÀ',

        // Logistica
        'LOG' => 'LOGISTICA',
    ];
}

/**
 * Converte un codice competenza da alias a nome standard
 * Es: AUTOVEICOLO_MR_A1 → AUTOMOBILE_MR_A1
 * Gestisce anche il case (MAu → MAU)
 */
function normalize_competency_code($code) {
    // Prima converti tutto in maiuscolo per uniformità (mb_strtoupper per UTF-8)
    $code = mb_strtoupper(trim($code), 'UTF-8');

    $aliases = get_sector_aliases();
    foreach ($aliases as $alias => $standard) {
        // Converti anche l'alias in maiuscolo per confronto sicuro
        $alias_upper = mb_strtoupper($alias, 'UTF-8');
        if (mb_strpos($code, $alias_upper . '_', 0, 'UTF-8') === 0) {
            return $standard . mb_substr($code, mb_strlen($alias_upper, 'UTF-8'), null, 'UTF-8');
        }
    }
    return $code;
}

/**
 * Estrae il codice competenza da un testo
 * Supporta sia il settore standard che gli alias
 */
function extract_competency_code($text, $sector) {
    // Pattern base: SETTORE_CODICE_LIVELLO (es: AUTOMOBILE_MO_A1, ELETTRICITÀ_IE_F2)
    // Usiamo [A-Za-z0-9]+ per permettere codici misti lettere/numeri

    // Prima prova con il settore standard - pattern completo
    $pattern = '/(' . preg_quote($sector, '/') . '_[A-Za-z0-9]+_[A-Za-z0-9]+)/ui';
    if (preg_match($pattern, $text, $m)) {
        return mb_strtoupper($m[1], 'UTF-8');
    }

    // Poi prova con tutti gli alias che mappano a questo settore
    $aliases = get_sector_aliases();
    foreach ($aliases as $alias => $standard) {
        if ($standard === $sector) {
            $pattern = '/(' . preg_quote($alias, '/') . '_[A-Za-z0-9]+_[A-Za-z0-9]+)/ui';
            if (preg_match($pattern, $text, $m)) {
                // Converti l'alias nel nome standard (con normalizzazione UTF-8)
                return normalize_competency_code(mb_strtoupper($m[1], 'UTF-8'));
            }
        }
    }

    // Caso speciale: cerca anche gli alias che puntano al settore corrente
    // Utile per ELETTRICITÀ dove il testo potrebbe avere ELETTRICITA
    foreach ($aliases as $alias => $standard) {
        if (mb_strtoupper($standard, 'UTF-8') === mb_strtoupper($sector, 'UTF-8')) {
            continue; // Già cercato sopra
        }
        if (mb_strtoupper($alias, 'UTF-8') === mb_strtoupper($sector, 'UTF-8')) {
            $pattern = '/(' . preg_quote($standard, '/') . '_[A-Za-z0-9]+_[A-Za-z0-9]+)/ui';
            if (preg_match($pattern, $text, $m)) {
                return mb_strtoupper($m[1], 'UTF-8');
            }
        }
    }

    return null;
}

/**
 * Icona per settore
 */
function get_sector_icon($sector) {
    $icons = [
        'AUTOMOBILE' => '🚗',
        'MECCANICA' => '⚙️',
        'ELETTRONICA' => '🔌',
        'ELETTRICITÀ' => '⚡',
        'AUTOMAZIONE' => '🤖',
        'CHIMFARM' => '🧪',
        'METALCOSTRUZIONE' => '🔩',
        'LOGISTICA' => '📦',
        'GENERICO' => '📝',
        'INFORMATICA' => '💻',
        'CUCINA' => '👨‍🍳',
        'SERVIZIO' => '🍽️',
        'VENDITA' => '🛒',
        'DEFAULT' => '📋'
    ];
    return $icons[$sector] ?? $icons['DEFAULT'];
}

// ============================================================================
// GESTIONE SESSIONE PER DATI MULTI-STEP
// ============================================================================

if (!isset($_SESSION['setup_universale'])) {
    $_SESSION['setup_universale'] = [
        'frameworkid' => 0,
        'sector' => '',
        'files' => [],
        'quizzes' => []
    ];
}

// Salva dati da form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['frameworkid'])) {
        $_SESSION['setup_universale']['frameworkid'] = (int)$_POST['frameworkid'];
    }
    if (isset($_POST['sector'])) {
        $_SESSION['setup_universale']['sector'] = clean_param($_POST['sector'], PARAM_TEXT);
    }
}

$session_data = &$_SESSION['setup_universale'];

// ============================================================================
// STEP 1: SELEZIONE FRAMEWORK
// ============================================================================

if ($step == 1):
    $frameworks = get_frameworks();
?>

<div class="setup-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="setup-header">
        <h2>🛠️ Setup Universale Quiz e Competenze</h2>
        <p>Crea quiz e assegna competenze per qualsiasi framework e settore</p>
    </div>
    
    <!-- Steps indicator -->
    <div class="steps-container">
        <div class="step-item active">
            <div class="step-number">1</div>
            <div class="step-label">Framework</div>
        </div>
        <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-label">Settore</div>
        </div>
        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-label">File XML</div>
        </div>
        <div class="step-item">
            <div class="step-number">4</div>
            <div class="step-label">Configura Quiz</div>
        </div>
        <div class="step-item">
            <div class="step-number">5</div>
            <div class="step-label">Esegui</div>
        </div>
    </div>
    
    <div class="panel">
        <h3><span class="icon">📚</span> Step 1: Seleziona Framework</h3>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=2">
            <div class="form-group">
                <label>Framework di Competenze</label>
                <select name="frameworkid" required>
                    <option value="">-- Seleziona un framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo ($session_data['frameworkid'] == $fw->id) ? 'selected' : ''; ?>>
                        <?php echo format_string($fw->shortname); ?>
                        <?php if ($fw->idnumber): ?>(<?php echo $fw->idnumber; ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Seleziona il framework che contiene le competenze da assegnare</div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    Avanti →
                </button>
            </div>
        </form>
    </div>
    
    <div class="panel">
        <h3><span class="icon">ℹ️</span> Informazioni</h3>
        <p>Questo strumento ti permette di:</p>
        <ul>
            <li>✅ Importare domande da file XML</li>
            <li>✅ Creare automaticamente i quiz</li>
            <li>✅ Assegnare le competenze in base ai codici nelle domande</li>
            <li>✅ Funziona con qualsiasi framework e settore</li>
        </ul>
    </div>
</div>

<?php
endif;

// ============================================================================
// STEP 2: SELEZIONE SETTORE
// ============================================================================

if ($step == 2):
    $frameworkid = $session_data['frameworkid'];
    
    if (!$frameworkid) {
        redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php', ['courseid' => $courseid, 'step' => 1]));
    }
    
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    $sectors = get_framework_sectors($frameworkid);
?>

<div class="setup-page">
    <a href="?courseid=<?php echo $courseid; ?>&step=1" class="back-link">← Torna a Step 1</a>
    
    <div class="setup-header">
        <h2>🛠️ Setup Universale Quiz e Competenze</h2>
        <p>Framework: <strong><?php echo format_string($framework->shortname); ?></strong></p>
    </div>
    
    <!-- Steps indicator -->
    <div class="steps-container">
        <div class="step-item completed">
            <div class="step-number">✓</div>
            <div class="step-label">Framework</div>
        </div>
        <div class="step-item active">
            <div class="step-number">2</div>
            <div class="step-label">Settore</div>
        </div>
        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-label">File XML</div>
        </div>
        <div class="step-item">
            <div class="step-number">4</div>
            <div class="step-label">Configura Quiz</div>
        </div>
        <div class="step-item">
            <div class="step-number">5</div>
            <div class="step-label">Esegui</div>
        </div>
    </div>
    
    <div class="panel">
        <h3><span class="icon">🎯</span> Step 2: Seleziona Settore/Area</h3>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=3">
            
            <?php if (empty($sectors)): ?>
            <div class="alert alert-warning">
                ⚠️ Nessun settore trovato in questo framework. Verifica che le competenze abbiano un prefisso (es. AUTOMOBILE_MR_A1).
            </div>
            <?php else: ?>
            
            <p>Seleziona il settore per cui vuoi creare i quiz:</p>
            
            <div class="sector-grid">
                <?php foreach ($sectors as $prefix => $data): ?>
                <label class="sector-card" onclick="this.querySelector('input').checked = true; document.querySelectorAll('.sector-card').forEach(c => c.classList.remove('selected')); this.classList.add('selected');">
                    <input type="radio" name="sector" value="<?php echo $prefix; ?>" style="display:none;" 
                           <?php echo ($session_data['sector'] == $prefix) ? 'checked' : ''; ?> required>
                    <div class="sector-icon"><?php echo get_sector_icon($prefix); ?></div>
                    <div class="sector-name"><?php echo $prefix; ?></div>
                    <div class="sector-count"><?php echo $data['count']; ?> competenze</div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
            
            <div class="btn-group">
                <a href="?courseid=<?php echo $courseid; ?>&step=1" class="btn btn-secondary">← Indietro</a>
                <button type="submit" class="btn btn-primary">Avanti →</button>
            </div>
        </form>
    </div>
</div>

<?php
endif;

// ============================================================================
// STEP 3: UPLOAD FILE XML
// ============================================================================

if ($step == 3):
    $frameworkid = $session_data['frameworkid'];
    $sector = $session_data['sector'];
    
    if (!$frameworkid || !$sector) {
        redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php', ['courseid' => $courseid, 'step' => 1]));
    }
    
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    
    // Crea cartella xml se non esiste
    $xml_dir = __DIR__ . '/xml/';
    if (!is_dir($xml_dir)) {
        mkdir($xml_dir, 0755, true);
    }
    
    // Gestione upload file
    $upload_message = '';
    $upload_error = '';
    $show_word_review = false;
    
    if (isset($_FILES['xmlfiles']) && !empty($_FILES['xmlfiles']['name'][0])) {
        $uploaded_count = 0;
        
        foreach ($_FILES['xmlfiles']['name'] as $key => $filename) {
            if ($_FILES['xmlfiles']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['xmlfiles']['tmp_name'][$key];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // === GESTIONE FILE WORD (.docx) ===
                if ($ext === 'docx') {
                    // PULISCI SESSIONE PRECEDENTE prima di nuovo upload
                    unset($SESSION->word_import_questions);
                    unset($SESSION->word_import_filename);
                    unset($SESSION->word_import_suggested_name);
                    unset($SESSION->word_import_valid_competencies);
                    unset($SESSION->word_import_sector);
                    unset($SESSION->excel_verify_path);
                    unset($SESSION->excel_verify_results);

                    $file_data = [
                        'name' => $filename,
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['xmlfiles']['error'][$key]
                    ];

                    $word_result = process_word_upload($file_data, $sector, $frameworkid);
                    
                    if ($word_result['success']) {
                        $show_word_review = true;
                        $r = $word_result['result'];
                        $upload_message = "📄 <strong>File Word analizzato!</strong><br>" .
                                         "Trovate {$r['total_questions']} domande: " .
                                         "✅ {$r['valid_count']} valide, " .
                                         "⚠️ {$r['warning_count']} da verificare, " .
                                         "❌ {$r['error_count']} errori";
                    } else {
                        $upload_error .= "❌ Errore Word: " . $word_result['error'] . "<br>";
                    }
                    continue;
                }

                // === GESTIONE FILE EXCEL (.xlsx, .xlsb) ===
                if (in_array($ext, ['xlsx', 'xlsb', 'xls'])) {
                    // Pulisci sessione precedente
                    if (isset($SESSION->excel_import_file) && file_exists($SESSION->excel_import_file)) {
                        @unlink($SESSION->excel_import_file);
                    }
                    unset($SESSION->excel_import_file);
                    unset($SESSION->excel_import_filename);

                    // Salva il file temporaneo
                    $excel_tmp_path = $CFG->tempdir . '/excel_import_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $excel_tmp_path)) {
                        // Memorizza in sessione
                        $SESSION->excel_import_file = $excel_tmp_path;
                        $SESSION->excel_import_filename = $filename;

                        $upload_message = '📊 File Excel caricato: ' . $filename . '. Analisi in corso...';
                    } else {
                        $upload_error .= "❌ Errore caricamento Excel: $filename<br>";
                    }
                    continue;
                }

                // === GESTIONE FILE XML (codice esistente) ===
                if ($ext !== 'xml') {
                    $upload_error .= "⚠️ File ignorato (usa .xml, .docx o .xlsx): $filename<br>";
                    continue;
                }
                
                // Verifica contenuto XML valido
                $content = file_get_contents($tmp_name);
                if (strpos($content, '<question') === false) {
                    $upload_error .= "⚠️ File ignorato (non contiene domande): $filename<br>";
                    continue;
                }
                
                // Salva il file
                $destination = $xml_dir . $filename;
                if (move_uploaded_file($tmp_name, $destination)) {
                    $uploaded_count++;
                } else {
                    $upload_error .= "❌ Errore upload: $filename<br>";
                }
            }
        }
        
        if ($uploaded_count > 0) {
            $upload_message = "✅ Caricati $uploaded_count file XML con successo!";
        }
    }
    
    // Verifica se c'è una sessione Word attiva
    if (isset($SESSION->word_import_questions) && !empty($SESSION->word_import_questions)) {
        $show_word_review = true;
    }
    
    // Leggi tutti i file XML nella cartella E VALIDA
    $uploaded_files = [];
    $validator = new \local_competencyxmlimport\xml_validator($frameworkid, $sector);
    $has_blocking_errors = false;
    
    if (is_dir($xml_dir)) {
        $files = scandir($xml_dir);
        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml') {
                $filepath = $xml_dir . $file;
                $content = file_get_contents($filepath);
                $question_count = preg_match_all('/<question type="multichoice"/', $content);
                
                // Verifica se è del settore corrente cercando nel CONTENUTO del file
                $sector_pattern = '/' . preg_quote($sector, '/') . '_[A-Za-z0-9]+_[A-Z0-9]+/i';
                $is_sector = preg_match($sector_pattern, $content) > 0;
                
                // VALIDAZIONE XML
                $validation = $validator->validate_file($filepath, $file);
                
                // Se ci sono errori bloccanti, segna
                if (!$validation['can_proceed']) {
                    $has_blocking_errors = true;
                }
                
                $uploaded_files[] = [
                    'name' => $file,
                    'path' => $filepath,
                    'questions' => $question_count,
                    'is_sector' => $is_sector,
                    'size' => filesize($filepath),
                    'validation' => $validation
                ];
            }
        }
    }
    
    // Filtra solo file del settore
    $sector_files = array_filter($uploaded_files, function($f) { return $f['is_sector']; });
    
    // Verifica se ci sono errori bloccanti nei file del settore
    $can_proceed = true;
    foreach ($sector_files as $sf) {
        if (isset($sf['validation']) && !$sf['validation']['can_proceed']) {
            $can_proceed = false;
            break;
        }
    }
?>

<div class="setup-page">
    <a href="?courseid=<?php echo $courseid; ?>&step=2" class="back-link">← Torna a Step 2</a>
    
    <div class="setup-header">
        <h2>🛠️ Setup Universale Quiz e Competenze</h2>
        <p>Framework: <strong><?php echo format_string($framework->shortname); ?></strong> | Settore: <strong><?php echo $sector; ?></strong></p>
    </div>
    
    <!-- Steps indicator -->
    <div class="steps-container">
        <div class="step-item completed">
            <div class="step-number">✓</div>
            <div class="step-label">Framework</div>
        </div>
        <div class="step-item completed">
            <div class="step-number">✓</div>
            <div class="step-label">Settore</div>
        </div>
        <div class="step-item active">
            <div class="step-number">3</div>
            <div class="step-label">File XML</div>
        </div>
        <div class="step-item">
            <div class="step-number">4</div>
            <div class="step-label">Configura Quiz</div>
        </div>
        <div class="step-item">
            <div class="step-number">5</div>
            <div class="step-label">Esegui</div>
        </div>
    </div>
    
    <?php if ($upload_message): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
        <?php echo $upload_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($upload_error): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
        <?php echo $upload_error; ?>
    </div>
    <?php endif; ?>
    
    <div class="panel">
        <h3><span class="icon">📤</span> Carica File XML</h3>
        
        <!-- Box download template AMPLIATO -->
        <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50; border-radius: 10px; padding: 20px; margin-bottom: 25px;">
            <h4 style="margin: 0 0 15px 0; color: #2e7d32;">📋 Scarica i Template per creare le domande</h4>
            <p style="margin: 0 0 15px 0; font-size: 14px; color: #555;">
                Usa questi template per creare domande nel formato corretto. Il sistema validerà automaticamente i file prima dell'import.
            </p>
            
            <div class="template-grid">
                <a href="download_template.php?type=xml&sector=<?php echo urlencode($sector); ?>" class="template-card">
                    <div class="icon">📄</div>
                    <div class="title">Template XML</div>
                    <div class="desc">Struttura Moodle XML con esempi</div>
                </a>
                <a href="download_template.php?type=excel&sector=<?php echo urlencode($sector); ?>" class="template-card">
                    <div class="icon">📊</div>
                    <div class="title">Excel Master</div>
                    <div class="desc">Mappatura competenze-quiz</div>
                </a>
                <a href="download_template.php?type=word&sector=<?php echo urlencode($sector); ?>" class="template-card">
                    <div class="icon">📝</div>
                    <div class="title">Template Word</div>
                    <div class="desc">Formato leggibile per revisione</div>
                </a>
                <a href="download_template.php?type=instructions&sector=<?php echo urlencode($sector); ?>" class="template-card">
                    <div class="icon">🤖</div>
                    <div class="title">Istruzioni ChatGPT</div>
                    <div class="desc">Prompt per generare domande</div>
                </a>
            </div>
        </div>
        
        <form method="post" enctype="multipart/form-data" action="?courseid=<?php echo $courseid; ?>&step=3">
            <div class="upload-area" onclick="document.getElementById('xmlfiles').click();" id="dropzone">
                <div class="icon">📁</div>
                <p><strong>Trascina qui i file</strong></p>
                <p style="font-size: 14px; color: #888;">oppure clicca per selezionare</p>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    📄 <strong>.xml</strong> (Moodle XML) &nbsp;|&nbsp; 📝 <strong>.docx</strong> (Word)
                </p>
                <input type="file" name="xmlfiles[]" id="xmlfiles" multiple accept=".xml,.docx" style="display:none;" onchange="this.form.submit();">
            </div>
        </form>
        
        <script>
        // Drag and drop
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('xmlfiles');
        
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            fileInput.form.submit();
        });
        </script>
    </div>
    
    <?php 
    // === SEZIONE REVISIONE WORD ===
    if ($show_word_review && isset($SESSION->word_import_questions)): 
        echo get_word_review_css();
    ?>
    <div class="panel" style="border: 2px solid #667eea;">
        <h3><span class="icon">📝</span> Revisione File Word</h3>
        
        <?php echo render_word_review_table($SESSION->word_import_questions, $courseid); ?>
        
        <!-- ======== SEZIONE VERIFICA EXCEL ======== -->
        <div id="excelVerifySection" style="margin-top: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="enableExcelVerify" onchange="toggleExcelVerify()">
                    <strong>🔍 Verifica con file Excel di controllo</strong>
                </label>
                <a href="download_template.php?type=excelverify&sector=<?php echo urlencode($sector); ?>" 
                   class="btn-link" style="color: #667eea; text-decoration: none; font-size: 13px;">
                    📥 Scarica Template Excel
                </a>
            </div>
            
            <div id="excelVerifyPanel" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                <!-- Upload Excel -->
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: 500; display: block; margin-bottom: 5px;">📊 Carica file Excel di verifica:</label>
                    <input type="file" id="excelVerifyFile" accept=".xlsx" onchange="uploadExcelVerify()">
                </div>
                
                <!-- Selettore foglio -->
                <div id="excelSheetSelector" style="display: none; margin-bottom: 15px;">
                    <label style="font-weight: 500; display: block; margin-bottom: 5px;">Seleziona foglio:</label>
                    <select id="excelSheet" onchange="selectExcelSheet()" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                        <option value="">-- Seleziona foglio --</option>
                    </select>
                </div>
                
                <!-- Mapping colonne -->
                <div id="excelColumnMapping" style="display: none; padding: 15px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 15px 0; font-size: 14px;">📋 Mapping Colonne</h4>
                    <div style="display: grid; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label style="width: 150px; font-weight: 500;">Colonna Domanda:</label>
                            <select id="colQuestion" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label style="width: 150px; font-weight: 500;">Colonna Competenza:</label>
                            <select id="colCompetency" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label style="width: 150px; font-weight: 500;">Colonna Risposta:</label>
                            <select id="colAnswer" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"></select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" style="margin-top: 15px;" onclick="runExcelVerify()">🔍 Esegui Verifica</button>
                </div>
                
                <!-- Risultati verifica -->
                <div id="excelVerifyResults" style="display: none; margin-top: 15px;">
                    <!-- Popolato via JS -->
                </div>
            </div>
        </div>
        <!-- ======== FINE SEZIONE VERIFICA EXCEL ======== -->
        
        <div style="margin-top: 20px; padding: 20px; background: #f0f9ff; border-radius: 8px;">
            <form method="post" action="?courseid=<?php echo $courseid; ?>&step=3&action=convertword&sesskey=<?php echo sesskey(); ?>"
                
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Nome Quiz:</label>
                    <input type="text" name="quiz_name" 
                           value="<?php echo htmlspecialchars($SESSION->word_import_suggested_name ?? ''); ?>" 
                           style="width: 100%; max-width: 400px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                    <p style="font-size: 12px; color: #888; margin-top: 5px;">
                        Questo nome verrà usato per il file XML e come prefisso per le domande
                    </p>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        ✓ Converti in XML e Aggiungi
                    </button>
                    <a href="?courseid=<?php echo $courseid; ?>&step=3&clear_word=1" 
                       class="btn btn-secondary"
                       onclick="return confirm('Vuoi annullare l\'import Word? I dati non salvati andranno persi.');">
                        ✕ Annulla Import Word
                    </a>
                </div>
            </form>
        </div>
        
        <?php echo render_correction_modal($courseid); ?>
    </div>
    
    <!-- JavaScript per verifica Excel -->
    <script>
    var excelVerifyData = {
        sheets: [],
        selectedSheet: null,
        headers: [],
        autoDetect: {},
        discrepancies: []
    };
    
    function toggleExcelVerify() {
        var panel = document.getElementById('excelVerifyPanel');
        var checkbox = document.getElementById('enableExcelVerify');
        panel.style.display = checkbox.checked ? 'block' : 'none';
        updateConvertButton();
    }
    
    function uploadExcelVerify() {
        var fileInput = document.getElementById('excelVerifyFile');
        if (!fileInput.files.length) return;
        
        var formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);
        formData.append('action', 'loadexcel');
        formData.append('sesskey', M.cfg.sesskey);
        
        fetch('ajax/excel_verify.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                excelVerifyData.sheets = data.sheets;
                showSheetSelector(data.sheets);
            } else {
                alert('Errore: ' + data.error);
            }
        })
        .catch(function(err) { alert('Errore caricamento: ' + err); });
    }
    
    function showSheetSelector(sheets) {
        var selector = document.getElementById('excelSheetSelector');
        var select = document.getElementById('excelSheet');
        
        select.innerHTML = '<option value="">-- Seleziona foglio --</option>';
        sheets.forEach(function(sheet, index) {
            select.innerHTML += '<option value="' + index + '">' + 
                sheet.name + ' (' + sheet.rows + ' righe)</option>';
        });
        
        selector.style.display = 'block';
    }
    
    function selectExcelSheet() {
        var select = document.getElementById('excelSheet');
        var sheetIndex = select.value;
        
        if (!sheetIndex) {
            document.getElementById('excelColumnMapping').style.display = 'none';
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'selectsheet');
        formData.append('sheet_index', sheetIndex);
        formData.append('sesskey', M.cfg.sesskey);
        
        fetch('ajax/excel_verify.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                excelVerifyData.headers = data.headers;
                excelVerifyData.autoDetect = data.auto_detect;
                showColumnMapping(data.headers, data.auto_detect);
            } else {
                alert('Errore: ' + data.error);
            }
        });
    }
    
    function showColumnMapping(headers, autoDetect) {
        var panel = document.getElementById('excelColumnMapping');
        var selects = ['colQuestion', 'colCompetency', 'colAnswer'];
        var autoValues = [autoDetect.question_col, autoDetect.competency_col, autoDetect.answer_col];
        
        selects.forEach(function(selectId, i) {
            var select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Seleziona --</option>';
            
            headers.forEach(function(header, index) {
                if (header) {
                    var selected = (autoValues[i] === index) ? ' selected' : '';
                    select.innerHTML += '<option value="' + index + '"' + selected + '>' + header + '</option>';
                }
            });
        });
        
        panel.style.display = 'block';
    }
    
    function runExcelVerify() {
        var colQuestion = document.getElementById('colQuestion').value;
        var colCompetency = document.getElementById('colCompetency').value;
        var colAnswer = document.getElementById('colAnswer').value;
        
        if (!colQuestion || !colCompetency) {
            alert('Seleziona almeno le colonne Domanda e Competenza');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'verify');
        formData.append('col_question', colQuestion);
        formData.append('col_competency', colCompetency);
        formData.append('col_answer', colAnswer || -1);
        formData.append('sesskey', M.cfg.sesskey);
        
        fetch('ajax/excel_verify.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                excelVerifyData.discrepancies = data.results.discrepancies;
                showVerifyResults(data.results);
            } else {
                alert('Errore: ' + data.error);
            }
        });
    }
    
    function showVerifyResults(results) {
        var container = document.getElementById('excelVerifyResults');

        // Salva risultati per l'editor competenze
        excelVerifyData.results = results;
        excelVerifyData.discrepancies = results.discrepancies || [];

        var html = '<div style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">';
        html += '<div style="padding: 10px 15px; border-radius: 6px; background: #d1fae5; color: #065f46; font-weight: 500;">✅ ' + results.matches + '/' + results.total_word + ' corrispondono</div>';

        if (results.discrepancies.length > 0) {
            html += '<div style="padding: 10px 15px; border-radius: 6px; background: #fef3c7; color: #92400e; font-weight: 500;">⚠️ ' + results.discrepancies.length + ' discrepanze</div>';
        }

        // Mostra verifica competenze nel database
        if (results.competency_check) {
            var cc = results.competency_check;
            if (cc.all_valid) {
                html += '<div style="padding: 10px 15px; border-radius: 6px; background: #d1fae5; color: #065f46; font-weight: 500;">🎯 Tutte le ' + cc.valid_in_excel + ' competenze esistono nel DB</div>';
            } else {
                html += '<div style="padding: 10px 15px; border-radius: 6px; background: #fee2e2; color: #991b1b; font-weight: 500;">🚫 ' + cc.invalid_in_excel + ' competenze NON trovate nel DB</div>';
            }
        }
        html += '</div>';

        // Se ci sono competenze mancanti, mostra dettagli con possibilità di correggere
        if (results.competency_check && !results.competency_check.all_valid) {
            html += '<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin-bottom: 15px;">';
            html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
            html += '<p style="margin: 0; font-weight: 600; color: #991b1b;">⚠️ Competenze non trovate nel framework:</p>';
            html += '<button type="button" onclick="loadCompetencyEditor()" style="padding: 8px 15px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">🔧 Correggi Competenze</button>';
            html += '</div>';

            // Mostra riepilogo competenze mancanti
            html += '<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">';
            results.competency_check.missing_competencies.forEach(function(mc) {
                html += '<span style="background: #fee2e2; padding: 4px 10px; border-radius: 4px; font-family: monospace; font-size: 13px;">' + mc.code + ' <small>(' + mc.count + ' dom.)</small></span>';
            });
            html += '</div>';

            // Container per l'editor (inizialmente nascosto)
            html += '<div id="competencyEditorContainer" style="display: none;"></div>';
            html += '</div>';
        }
        
        if (results.discrepancies.length > 0) {
            html += '<div style="max-height: 300px; overflow-y: auto;">';
            html += '<p style="font-weight: 600; margin-bottom: 10px;">Risolvi le discrepanze:</p>';
            
            results.discrepancies.forEach(function(d) {
                var typeLabel = d.type === 'competency' ? 'Competenza' : 'Risposta';
                html += '<div class="discrepancy-item" id="disc-' + d.index + '-' + d.type + '" style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px;">';
                html += '<span style="font-weight: bold; margin-right: 15px; min-width: 50px;">' + d.question + '</span>';
                html += '<span style="margin-right: 10px; color: #666;">' + typeLabel + ':</span>';
                html += '<span style="padding: 3px 8px; border-radius: 4px; background: #fee2e2; font-family: monospace; font-size: 13px; margin-right: 5px;">Word: ' + d.word_value + '</span>';
                html += '<span style="padding: 3px 8px; border-radius: 4px; background: #d1fae5; font-family: monospace; font-size: 13px; margin-right: 15px;">Excel: ' + d.excel_value + '</span>';
                html += '<span style="display: flex; gap: 5px;">';
                html += '<button type="button" onclick="useExcelValue(' + d.index + ', \'' + d.type + '\', \'' + d.excel_value + '\')" style="padding: 5px 10px; border: none; border-radius: 4px; background: #10b981; color: white; cursor: pointer; font-size: 12px;">Usa Excel</button>';
                html += '<button type="button" onclick="keepWordValue(' + d.index + ', \'' + d.type + '\')" style="padding: 5px 10px; border: none; border-radius: 4px; background: #6b7280; color: white; cursor: pointer; font-size: 12px;">Mantieni Word</button>';
                html += '</span>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '<p id="discrepancyWarning" style="padding: 10px 15px; border-radius: 6px; background: #fee2e2; color: #991b1b; font-weight: 500; margin-top: 15px;">❌ Risolvi tutte le discrepanze prima di procedere</p>';
        } else {
            html += '<p style="padding: 10px 15px; border-radius: 6px; background: #d1fae5; color: #065f46; font-weight: 500;">✅ Tutte le domande corrispondono!</p>';
        }
        
        container.innerHTML = html;
        container.style.display = 'block';
        
        updateConvertButton();
    }
    
    function useExcelValue(index, type, value) {
        var formData = new FormData();
        formData.append('action', 'useexcelvalue');
        formData.append('index', index);
        formData.append('type', type);
        formData.append('value', value);
        formData.append('sesskey', M.cfg.sesskey);
        
        fetch('ajax/excel_verify.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                markResolved(index, type, 'excel');
            }
        });
    }
    
    function keepWordValue(index, type) {
        var formData = new FormData();
        formData.append('action', 'keepwordvalue');
        formData.append('index', index);
        formData.append('type', type);
        formData.append('sesskey', M.cfg.sesskey);
        
        fetch('ajax/excel_verify.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                markResolved(index, type, 'word');
            }
        });
    }
    
    function markResolved(index, type, choice) {
        var item = document.getElementById('disc-' + index + '-' + type);
        if (item) {
            item.style.background = '#f0fdf4';
            item.style.borderColor = '#86efac';
            item.style.opacity = '0.7';
            var actions = item.querySelector('span:last-child');
            if (actions) {
                actions.innerHTML = '<span style="color: #10b981;">✓ ' + (choice === 'excel' ? 'Usato Excel' : 'Mantenuto Word') + '</span>';
            }
        }
        
        // Rimuovi dalla lista discrepanze
        excelVerifyData.discrepancies = excelVerifyData.discrepancies.filter(function(d) {
            return !(d.index === index && d.type === type);
        });
        
        checkAllResolved();
    }
    
    function checkAllResolved() {
        var unresolvedItems = document.querySelectorAll('.discrepancy-item:not([style*="opacity: 0.7"])');
        var warning = document.getElementById('discrepancyWarning');
        
        if (unresolvedItems.length === 0 || excelVerifyData.discrepancies.length === 0) {
            if (warning) warning.style.display = 'none';
        }
        
        updateConvertButton();
    }
    
    function updateConvertButton() {
        var btn = document.querySelector('button[type="submit"]');
        var enableVerify = document.getElementById('enableExcelVerify');

        if (btn) {
            // Se verifica non attiva o tutte risolte, abilita
            if (!enableVerify || !enableVerify.checked || excelVerifyData.discrepancies.length === 0) {
                btn.disabled = false;
                btn.style.opacity = '1';
            } else {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            }
        }
    }

    // ========== EDITOR COMPETENZE ==========
    var competencyEditorData = {
        competencies: null,
        questionsToFix: []
    };

    function loadCompetencyEditor() {
        var container = document.getElementById('competencyEditorContainer');
        container.innerHTML = '<p style="color: #666;"><span class="loading-spinner"></span> Caricamento competenze...</p>';
        container.style.display = 'block';

        // Carica competenze dal framework
        var formData = new FormData();
        formData.append('action', 'getcompetencies');
        formData.append('sesskey', M.cfg.sesskey);

        fetch('ajax/word_import.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                competencyEditorData.competencies = data.grouped;
                showCompetencyEditor(data);
            } else {
                container.innerHTML = '<p style="color: #c62828;">Errore: ' + data.error + '</p>';
            }
        })
        .catch(function(err) {
            container.innerHTML = '<p style="color: #c62828;">Errore di connessione</p>';
        });
    }

    function showCompetencyEditor(data) {
        var container = document.getElementById('competencyEditorContainer');

        // Trova domande con competenze non valide
        var questionsToFix = [];
        if (excelVerifyData.results && excelVerifyData.results.details) {
            excelVerifyData.results.details.forEach(function(d, idx) {
                // Domande con competenza non valida
                var isInvalid = false;
                if (excelVerifyData.results.competency_check && excelVerifyData.results.competency_check.missing_competencies) {
                    excelVerifyData.results.competency_check.missing_competencies.forEach(function(mc) {
                        if (d.word_competency === mc.code || d.excel_competency === mc.code) {
                            isInvalid = true;
                        }
                    });
                }
                if (isInvalid || !d.word_competency) {
                    questionsToFix.push({
                        index: d.index,
                        question: d.question,
                        currentCompetency: d.word_competency || d.excel_competency || '',
                        text: d.word_competency ? 'Competenza non valida' : 'Competenza mancante'
                    });
                }
            });
        }

        competencyEditorData.questionsToFix = questionsToFix;

        var html = '<div style="border-top: 1px solid #fecaca; padding-top: 15px; margin-top: 10px;">';
        html += '<h4 style="margin: 0 0 15px; color: #333;">✏️ Correggi Competenze (' + questionsToFix.length + ' domande)</h4>';

        // Selector area competenze
        html += '<div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">';
        html += '<label style="font-weight: 500;">Filtra per area:</label>';
        html += '<select id="competencyAreaFilter" onchange="filterCompetencyList()" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; min-width: 200px;">';
        html += '<option value="">-- Tutte le aree --</option>';
        for (var area in data.grouped) {
            html += '<option value="' + area + '">' + area + ' - ' + data.grouped[area].name + ' (' + data.grouped[area].items.length + ')</option>';
        }
        html += '</select>';
        html += '<input type="text" id="competencySearchFilter" placeholder="Cerca codice..." oninput="filterCompetencyList()" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 150px;">';
        html += '</div>';

        // Lista domande da correggere
        html += '<div style="max-height: 400px; overflow-y: auto;">';

        questionsToFix.forEach(function(q, idx) {
            html += '<div class="question-fix-row" id="qfix-' + q.index + '" style="display: flex; align-items: center; gap: 10px; padding: 12px; margin-bottom: 8px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">';
            html += '<span style="font-weight: 600; min-width: 50px; color: #374151;">' + q.question + '</span>';
            html += '<span style="font-family: monospace; font-size: 13px; padding: 4px 8px; background: #fee2e2; border-radius: 4px; min-width: 150px;">' + (q.currentCompetency || 'MANCANTE') + '</span>';
            html += '<span style="color: #666;">→</span>';

            // Dropdown competenze
            html += '<select id="compSelect-' + q.index + '" class="competency-select" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-family: monospace; font-size: 13px;">';
            html += '<option value="">-- Seleziona competenza --</option>';
            for (var area in data.grouped) {
                html += '<optgroup label="' + area + ' - ' + data.grouped[area].name + '">';
                data.grouped[area].items.forEach(function(comp) {
                    var selected = (comp === q.currentCompetency) ? ' selected' : '';
                    html += '<option value="' + comp + '"' + selected + '>' + comp + '</option>';
                });
                html += '</optgroup>';
            }
            html += '</select>';

            html += '<button type="button" onclick="saveQuestionCompetency(' + q.index + ')" style="padding: 8px 15px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">✓ Salva</button>';
            html += '</div>';
        });

        html += '</div>';

        // Pulsante salva tutto
        html += '<div style="margin-top: 15px; display: flex; gap: 10px;">';
        html += '<button type="button" onclick="saveAllCompetencies()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">💾 Salva Tutte le Modifiche</button>';
        html += '<button type="button" onclick="closeCompetencyEditor()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">Chiudi</button>';
        html += '</div>';

        html += '</div>';

        container.innerHTML = html;
    }

    function filterCompetencyList() {
        var areaFilter = document.getElementById('competencyAreaFilter').value;
        var searchFilter = document.getElementById('competencySearchFilter').value.toUpperCase();

        document.querySelectorAll('.competency-select').forEach(function(select) {
            var options = select.querySelectorAll('option');
            var optgroups = select.querySelectorAll('optgroup');

            // Nascondi/mostra optgroup in base all'area
            optgroups.forEach(function(og) {
                var label = og.label.split(' - ')[0];
                if (!areaFilter || label === areaFilter) {
                    og.style.display = '';

                    // Filtra opzioni per ricerca
                    og.querySelectorAll('option').forEach(function(opt) {
                        if (!searchFilter || opt.value.indexOf(searchFilter) !== -1) {
                            opt.style.display = '';
                        } else {
                            opt.style.display = 'none';
                        }
                    });
                } else {
                    og.style.display = 'none';
                }
            });
        });
    }

    function saveQuestionCompetency(index) {
        var select = document.getElementById('compSelect-' + index);
        var competency = select.value;

        if (!competency) {
            alert('Seleziona una competenza');
            return;
        }

        var row = document.getElementById('qfix-' + index);
        row.style.opacity = '0.5';

        var formData = new FormData();
        formData.append('action', 'updatequestioncompetency');
        formData.append('index', index);
        formData.append('competency', competency);
        formData.append('sesskey', M.cfg.sesskey);

        fetch('ajax/word_import.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            row.style.opacity = '1';
            if (data.success && data.is_valid) {
                row.style.background = '#f0fdf4';
                row.style.borderColor = '#86efac';
                row.querySelector('span:nth-child(2)').style.background = '#d1fae5';
                row.querySelector('span:nth-child(2)').textContent = competency;

                // Rimuovi dalla lista da fixare
                competencyEditorData.questionsToFix = competencyEditorData.questionsToFix.filter(function(q) {
                    return q.index !== index;
                });

                // Aggiorna contatori
                updateMissingCompetenciesDisplay();
            } else {
                alert('Errore: ' + (data.error || 'Competenza non valida'));
            }
        });
    }

    function saveAllCompetencies() {
        var promises = [];
        competencyEditorData.questionsToFix.forEach(function(q) {
            var select = document.getElementById('compSelect-' + q.index);
            if (select && select.value) {
                promises.push(saveQuestionCompetencyAsync(q.index, select.value));
            }
        });

        if (promises.length === 0) {
            alert('Nessuna modifica da salvare');
            return;
        }

        Promise.all(promises).then(function(results) {
            var saved = results.filter(function(r) { return r.success; }).length;
            alert('Salvate ' + saved + '/' + promises.length + ' competenze');
            updateMissingCompetenciesDisplay();
        });
    }

    function saveQuestionCompetencyAsync(index, competency) {
        var formData = new FormData();
        formData.append('action', 'updatequestioncompetency');
        formData.append('index', index);
        formData.append('competency', competency);
        formData.append('sesskey', M.cfg.sesskey);

        return fetch('ajax/word_import.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.is_valid) {
                var row = document.getElementById('qfix-' + index);
                if (row) {
                    row.style.background = '#f0fdf4';
                    row.style.borderColor = '#86efac';
                }
            }
            return data;
        });
    }

    function updateMissingCompetenciesDisplay() {
        // Riesegui la verifica per aggiornare i contatori
        var remaining = competencyEditorData.questionsToFix.length;
        if (remaining === 0) {
            // Tutte fixate! Aggiorna il display
            var badgeContainer = document.querySelector('#excelVerifyResults > div:first-child');
            if (badgeContainer) {
                // Trova il badge delle competenze invalide e aggiornalo
                var badges = badgeContainer.querySelectorAll('div');
                badges.forEach(function(badge) {
                    if (badge.textContent.indexOf('NON trovate') !== -1) {
                        badge.style.background = '#d1fae5';
                        badge.style.color = '#065f46';
                        badge.innerHTML = '🎯 Tutte le competenze corrette!';
                    }
                });
            }
        }
    }

    function closeCompetencyEditor() {
        var container = document.getElementById('competencyEditorContainer');
        if (container) {
            container.style.display = 'none';
        }
    }
    // ========== FINE EDITOR COMPETENZE ==========
    </script>
    <?php endif; ?>

    <?php
    // === SEZIONE REVISIONE EXCEL ===
    $show_excel_review = isset($SESSION->excel_import_file) && file_exists($SESSION->excel_import_file);
    if ($show_excel_review):
        $excelImporter = new \local_competencyxmlimport\excel_quiz_importer($frameworkid, $sector);
        $excelImporter->load_file($SESSION->excel_import_file);
        $excelSummary = $excelImporter->get_summary();
        $quizCount = $excelSummary['quiz_count'];
        $totalQuestions = $excelSummary['total_questions'];
    ?>
    <div class="panel" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; margin-bottom: 20px;">
        <h3 style="color: white; margin-bottom: 15px;"><span class="icon">📊</span> Import Excel Multi-Quiz: <?php echo htmlspecialchars($SESSION->excel_import_filename); ?></h3>

        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 28px; font-weight: bold;"><?php echo $quizCount; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">Quiz da Creare</div>
            </div>
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 28px; font-weight: bold;"><?php echo $totalQuestions; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">Domande Totali</div>
            </div>
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 28px; font-weight: bold;"><?php echo $excelSummary['validation']['valid_competencies']; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">Competenze Valide</div>
            </div>
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 28px; font-weight: bold; <?php echo $excelSummary['validation']['invalid_competencies'] > 0 ? 'color: #fcd34d;' : ''; ?>"><?php echo $excelSummary['validation']['invalid_competencies']; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">Competenze Non Valide</div>
            </div>
            <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 13px;">
                    ⭐ <?php echo $excelSummary['validation']['by_difficulty'][1]; ?> |
                    ⭐⭐ <?php echo $excelSummary['validation']['by_difficulty'][2]; ?> |
                    ⭐⭐⭐ <?php echo $excelSummary['validation']['by_difficulty'][3]; ?>
                </div>
                <div style="font-size: 13px; opacity: 0.9;">Distribuzione Difficolta</div>
            </div>
        </div>

        <!-- Quiz List Preview -->
        <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px; margin-bottom: 15px; max-height: 250px; overflow-y: auto;">
            <strong style="display: block; margin-bottom: 10px;">📋 Quiz che verranno creati:</strong>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.3);">
                        <th style="text-align: left; padding: 8px 5px;">#</th>
                        <th style="text-align: left; padding: 8px 5px;">Nome Quiz</th>
                        <th style="text-align: center; padding: 8px 5px;">Domande</th>
                        <th style="text-align: center; padding: 8px 5px;">Competenze OK</th>
                        <th style="text-align: center; padding: 8px 5px;">Difficolta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $qnum = 0; foreach ($excelSummary['quizzes'] as $quiz): $qnum++; ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <td style="padding: 6px 5px;"><?php echo $qnum; ?></td>
                        <td style="padding: 6px 5px; font-weight: 500;"><?php echo htmlspecialchars($quiz['short_name']); ?></td>
                        <td style="text-align: center; padding: 6px 5px;"><?php echo $quiz['question_count']; ?></td>
                        <td style="text-align: center; padding: 6px 5px;">
                            <?php if ($quiz['invalid_competencies'] > 0): ?>
                            <span style="color: #fcd34d;"><?php echo $quiz['valid_competencies']; ?>/<?php echo $quiz['question_count']; ?></span>
                            <?php else: ?>
                            <?php echo $quiz['valid_competencies']; ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; padding: 6px 5px;">
                            <?php
                            $d = $quiz['difficulty_distribution'];
                            $avg = ($d[1] * 1 + $d[2] * 2 + $d[3] * 3) / max(1, array_sum($d));
                            echo str_repeat('⭐', round($avg));
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($excelSummary['validation']['invalid_codes'])): ?>
        <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <strong>⚠️ Competenze non trovate nel framework:</strong>
            <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                <?php foreach ($excelSummary['validation']['invalid_codes'] as $code): ?>
                <span style="background: #fcd34d; color: #92400e; padding: 4px 10px; border-radius: 4px; font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($code); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($excelSummary['warnings']) && count($excelSummary['warnings']) > 0): ?>
        <details style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <summary style="cursor: pointer; font-weight: 600;">⚠️ Avvisi (<?php echo count($excelSummary['warnings']); ?>)</summary>
            <ul style="margin: 10px 0 0 0; padding-left: 20px; max-height: 150px; overflow-y: auto;">
                <?php foreach (array_slice($excelSummary['warnings'], 0, 20) as $warning): ?>
                <li style="font-size: 13px;"><?php echo htmlspecialchars($warning); ?></li>
                <?php endforeach; ?>
                <?php if (count($excelSummary['warnings']) > 20): ?>
                <li style="font-size: 13px; font-style: italic;">... e altri <?php echo count($excelSummary['warnings']) - 20; ?> avvisi</li>
                <?php endif; ?>
            </ul>
        </details>
        <?php endif; ?>

        <?php if (!empty($excelSummary['errors'])): ?>
        <div style="background: rgba(255,0,0,0.2); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <strong>❌ Errori:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <?php foreach ($excelSummary['errors'] as $error): ?>
                <li style="font-size: 13px;"><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=5&action=executeexcel&sesskey=<?php echo sesskey(); ?>">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <p style="margin: 0 0 10px 0; font-size: 14px;">
                        Verranno creati <strong><?php echo $quizCount; ?> quiz</strong> con <strong><?php echo $totalQuestions; ?> domande</strong> totali.
                        Le categorie domande saranno organizzate come: <code style="background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($sector); ?> → [Nome Quiz]</code>
                    </p>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="background: white; color: #059669; border: none; padding: 12px 30px; font-weight: 600; font-size: 15px;" <?php echo !$excelSummary['can_import'] ? 'disabled' : ''; ?>>
                        🚀 Importa <?php echo $quizCount; ?> Quiz (<?php echo $totalQuestions; ?> Domande)
                    </button>
                </div>
                <div>
                    <a href="?courseid=<?php echo $courseid; ?>&step=3&clear_excel=1"
                       class="btn btn-secondary" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 12px 20px;">
                        ✕ Annulla
                    </a>
                </div>
            </div>
            <input type="hidden" name="assign_competencies" value="1">
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($sector_files)): ?>
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;"><span class="icon"><?php echo $can_proceed ? '✅' : '⚠️'; ?></span> File per <?php echo $sector; ?> (<?php echo count($sector_files); ?>)</h3>
            <a href="?courseid=<?php echo $courseid; ?>&step=3&action=deleteallxml&sesskey=<?php echo sesskey(); ?>"
               onclick="return confirm('Eliminare TUTTI i file XML? Questa azione non può essere annullata.');"
               class="btn btn-secondary" style="background: #dc2626; border-color: #dc2626; font-size: 13px; padding: 6px 12px;">
                🗑️ Elimina tutti i file
            </a>
        </div>

        <?php if (!$can_proceed): ?>
        <div style="background: #ffebee; color: #c62828; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px;">
            ❌ <strong>Alcuni file contengono errori.</strong> Correggi i problemi indicati prima di procedere.
        </div>
        <?php endif; ?>

        <div class="file-list">
            <?php $file_index = 0; foreach ($sector_files as $file): $file_index++; ?>
            <?php 
                $v = isset($file['validation']) ? $file['validation'] : null;
                $status_class = 'ok';
                $status_text = '✓ Valido';
                if ($v) {
                    if ($v['errors'] > 0) {
                        $status_class = 'error';
                        $status_text = '❌ ' . $v['errors'] . ' errori';
                    } elseif ($v['warnings'] > 0) {
                        $status_class = 'warning';
                        $status_text = '⚠️ ' . $v['warnings'] . ' avvisi';
                    }
                }
            ?>
            <div class="file-item">
                <div class="file-icon">📄</div>
                <div class="file-info">
                    <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                    <div class="file-meta">
                        <?php echo $file['questions']; ?> domande • 
                        <?php echo round($file['size'] / 1024, 1); ?> KB
                        <?php if ($v): ?>
                        • <span class="validation-badge <?php echo $status_class; ?>"><?php echo $v['ok']; ?> OK</span>
                        <?php if ($v['warnings'] > 0): ?>
                        <span class="validation-badge warning"><?php echo $v['warnings']; ?> ⚠️</span>
                        <?php endif; ?>
                        <?php if ($v['errors'] > 0): ?>
                        <span class="validation-badge error"><?php echo $v['errors']; ?> ❌</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="validation-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    <?php if ($v && ($v['errors'] > 0 || $v['warnings'] > 0)): ?>
                    <span class="validation-details-toggle" onclick="toggleDetails(<?php echo $file_index; ?>)">Dettagli ▼</span>
                    <?php endif; ?>
                    <a href="?courseid=<?php echo $courseid; ?>&step=3&action=deletefile&filename=<?php echo urlencode($file['name']); ?>&sesskey=<?php echo sesskey(); ?>"
                       onclick="return confirm('Eliminare il file <?php echo htmlspecialchars($file['name']); ?>?');"
                       style="color: #dc2626; text-decoration: none; font-size: 14px; padding: 4px 8px;"
                       title="Elimina file">🗑️</a>
                </div>
            </div>
            
            <?php if ($v && ($v['errors'] > 0 || $v['warnings'] > 0)): ?>
            <div id="details-<?php echo $file_index; ?>" class="validation-details-panel">
                <?php foreach ($v['questions'] as $q): ?>
                <?php if ($q['status'] !== 'ok'): ?>
                <div class="validation-item <?php echo $q['status']; ?>">
                    <span><?php echo $q['status'] === 'error' ? '❌' : '⚠️'; ?></span>
                    <span class="q-name">Q<?php echo str_pad($q['num'], 2, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars(substr($q['name'], 0, 40)); ?></span>
                    <span class="q-issue"><?php echo htmlspecialchars(implode(', ', array_filter($q['issues'], function($i) { return $i !== 'OK'; }))); ?></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php endforeach; ?>
        </div>
        
        <script>
        function toggleDetails(index) {
            var panel = document.getElementById('details-' + index);
            if (panel) {
                panel.classList.toggle('show');
            }
        }
        </script>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=4">
            <input type="hidden" name="files" value='<?php echo htmlspecialchars(json_encode(array_values($sector_files))); ?>'>
            
            <div class="btn-group">
                <a href="?courseid=<?php echo $courseid; ?>&step=2" class="btn btn-secondary">← Indietro</a>
                <?php if ($can_proceed): ?>
                <button type="submit" class="btn btn-primary">Avanti → Configura Quiz</button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;">
                    ⚠️ Correggi gli errori per procedere
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="panel">
        <h3><span class="icon">📋</span> Nessun File per <?php echo $sector; ?></h3>
        <p>Carica i file XML usando il box sopra. I file dovrebbero contenere:</p>
        <ul>
            <li>Domande con codice competenza nel nome (es. <code>Q01 - <?php echo $sector; ?>_MR_A1</code>)</li>
            <li>Oppure codice nel testo della domanda (es. <code>&lt;b&gt;<?php echo $sector; ?>_MR_A1&lt;/b&gt;</code>)</li>
        </ul>
        
        <div class="btn-group">
            <a href="?courseid=<?php echo $courseid; ?>&step=2" class="btn btn-secondary">← Indietro</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php 
    // Mostra anche altri file XML presenti (non del settore)
    $other_files = array_filter($uploaded_files, function($f) { return !$f['is_sector']; });
    if (!empty($other_files)): 
    ?>
    <div class="panel" style="opacity: 0.7;">
        <h3><span class="icon">📁</span> Altri File XML Presenti</h3>
        <p style="font-size: 13px; color: #888;">Questi file non sembrano appartenere al settore <?php echo $sector; ?>:</p>
        <div class="file-list">
            <?php foreach ($other_files as $file): ?>
            <div class="file-item" style="opacity: 0.6;">
                <div class="file-icon">📄</div>
                <div class="file-info">
                    <div class="file-name"><?php echo $file['name']; ?></div>
                    <div class="file-meta"><?php echo $file['questions']; ?> domande</div>
                </div>
                <div class="file-status" style="color: #888;">Altro settore</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
endif;

// ============================================================================
// STEP 4: CONFIGURAZIONE QUIZ
// ============================================================================

if ($step == 4):
    $frameworkid = $session_data['frameworkid'];
    $sector = $session_data['sector'];
    
    // Salva files dalla POST
    if (isset($_POST['files'])) {
        $session_data['files'] = json_decode($_POST['files'], true);
    }
    
    $files = $session_data['files'];
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
?>

<div class="setup-page">
    <a href="?courseid=<?php echo $courseid; ?>&step=3" class="back-link">← Torna a Step 3</a>
    
    <div class="setup-header">
        <h2>🛠️ Setup Universale Quiz e Competenze</h2>
        <p>Framework: <strong><?php echo format_string($framework->shortname); ?></strong> | Settore: <strong><?php echo $sector; ?></strong></p>
    </div>
    
    <!-- Steps indicator -->
    <div class="steps-container">
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Framework</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Settore</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">File XML</div></div>
        <div class="step-item active"><div class="step-number">4</div><div class="step-label">Configura Quiz</div></div>
        <div class="step-item"><div class="step-number">5</div><div class="step-label">Esegui</div></div>
    </div>
    
    <div class="panel">
        <h3><span class="icon">⚙️</span> Step 4: Configura Quiz</h3>

        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=5&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

            <p>Seleziona i quiz da creare. I quiz già esistenti sono evidenziati.</p>

            <div style="margin-bottom: 15px; padding: 10px; background: #f0f4f8; border-radius: 8px;">
                <label style="cursor: pointer; font-weight: 600;">
                    <input type="checkbox" id="selectAllQuizzes" onchange="toggleAllQuizzes(this)" checked>
                    Seleziona/Deseleziona tutti
                </label>
            </div>

            <?php
            // Verifica quiz già esistenti nel corso
            $existing_quizzes = $DB->get_records('quiz', ['course' => $courseid], '', 'id,name');
            $existing_names = array_map(function($q) { return strtolower(trim($q->name)); }, $existing_quizzes);

            foreach ($files as $i => $file):
                $default_name = $sector . ' - ' . pathinfo($file['name'], PATHINFO_FILENAME);
                $quiz_exists = in_array(strtolower(trim($default_name)), $existing_names);
            ?>
            <div class="quiz-config-item" style="<?php echo $quiz_exists ? 'border: 2px solid #f59e0b; background: #fffbeb;' : ''; ?>">
                <div class="quiz-header" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="quiz[<?php echo $i; ?>][enabled]" value="1"
                           class="quiz-checkbox" <?php echo $quiz_exists ? '' : 'checked'; ?>
                           style="width: 20px; height: 20px; cursor: pointer;">
                    <span class="quiz-title">📄 <?php echo $file['name']; ?></span>
                    <span class="quiz-badge badge-base"><?php echo $file['questions']; ?> domande</span>
                    <?php if ($quiz_exists): ?>
                    <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                        ⚠️ GIÀ ESISTENTE
                    </span>
                    <?php endif; ?>
                </div>
                <div class="quiz-fields">
                    <div>
                        <label>Nome Quiz</label>
                        <input type="text" name="quiz[<?php echo $i; ?>][name]"
                               value="<?php echo htmlspecialchars($default_name); ?>">
                    </div>
                    <div>
                        <label>Livello</label>
                        <select name="quiz[<?php echo $i; ?>][level]">
                            <option value="1">⭐ Base</option>
                            <option value="2" selected>⭐⭐ Intermedio</option>
                            <option value="3">⭐⭐⭐ Avanzato</option>
                        </select>
                    </div>
                    <div>
                        <label>Categoria</label>
                        <input type="text" name="quiz[<?php echo $i; ?>][category]"
                               value="<?php echo pathinfo($file['name'], PATHINFO_FILENAME); ?>">
                    </div>
                </div>
                <input type="hidden" name="quiz[<?php echo $i; ?>][file]" value="<?php echo $file['path']; ?>">
                <input type="hidden" name="quiz[<?php echo $i; ?>][questions]" value="<?php echo $file['questions']; ?>">
            </div>
            <?php endforeach; ?>

            <script>
            function toggleAllQuizzes(master) {
                document.querySelectorAll('.quiz-checkbox').forEach(function(cb) {
                    cb.checked = master.checked;
                });
            }
            </script>
            
            <div class="form-group" style="margin-top: 20px;">
                <label>
                    <input type="checkbox" name="assign_competencies" value="1" checked>
                    Assegna automaticamente le competenze in base ai codici nelle domande
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="smart_mapping" value="1" checked>
                    Usa mapping intelligente per codici incompleti
                </label>
            </div>
            
            <div class="btn-group">
                <a href="?courseid=<?php echo $courseid; ?>&step=3" class="btn btn-secondary">← Indietro</a>
                <button type="submit" class="btn btn-primary">🚀 Avvia Setup</button>
            </div>
        </form>
    </div>
</div>

<?php
endif;

// ============================================================================
// STEP 5: ESECUZIONE DA EXCEL
// ============================================================================

if ($step == 5 && $action === 'executeexcel'):
    require_sesskey();

    $frameworkid = $session_data['frameworkid'];
    $sector = $session_data['sector'];
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);

    $assign_competencies = optional_param('assign_competencies', 1, PARAM_INT);

    // Verify Excel file exists
    if (!isset($SESSION->excel_import_file) || !file_exists($SESSION->excel_import_file)) {
        redirect(new moodle_url('/local/competencyxmlimport/setup_universale.php',
            ['courseid' => $courseid, 'step' => 3]),
            'File Excel non trovato. Ricarica il file.',
            null, \core\output\notification::NOTIFY_ERROR);
    }
?>

<div class="setup-page">
    <div class="setup-header">
        <h2>📊 Import Excel Multi-Quiz in Esecuzione...</h2>
        <p>Framework: <strong><?php echo format_string($framework->shortname); ?></strong> | Settore: <strong><?php echo $sector; ?></strong></p>
    </div>

    <div class="steps-container">
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Framework</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Settore</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">File Excel</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Configura</div></div>
        <div class="step-item active"><div class="step-number">5</div><div class="step-label">Esegui</div></div>
    </div>

    <div class="panel">
        <h3><span class="icon">📋</span> Log Import Excel Multi-Quiz</h3>
        <div class="progress-log">
<?php
    ob_implicit_flush(true);

    echo '<div class="log-line info">🔄 Inizio import Excel multi-quiz...</div>';

    // Create importer and load file
    $importer = new \local_competencyxmlimport\excel_quiz_importer($frameworkid, $sector);
    $importer->load_file($SESSION->excel_import_file);

    $quizCount = $importer->get_quiz_count();
    $questionCount = $importer->get_total_questions();
    echo '<div class="log-line">📊 Trovati ' . $quizCount . ' quiz con ' . $questionCount . ' domande totali nel file Excel</div>';

    // Debug: show validation stats
    $summary = $importer->get_summary();
    $validComp = $summary['validation']['valid_competencies'];
    $invalidComp = $summary['validation']['invalid_competencies'];
    echo '<div class="log-line">🔍 Validazione competenze: ' . $validComp . ' valide, ' . $invalidComp . ' non trovate nel framework</div>';
    if (!empty($summary['validation']['invalid_codes'])) {
        $sampleInvalid = array_slice($summary['validation']['invalid_codes'], 0, 5);
        echo '<div class="log-line warning" style="margin-left: 20px; font-size: 12px;">Codici non trovati (esempio): ' . implode(', ', $sampleInvalid) . '</div>';
    }
    // Show sample competencies from DB
    $dbCompetencies = $DB->get_records_sql("SELECT idnumber FROM {competency} WHERE competencyframeworkid = ? LIMIT 5", [$frameworkid]);
    if ($dbCompetencies) {
        $dbCodes = array_column((array)$dbCompetencies, 'idnumber');
        echo '<div class="log-line" style="margin-left: 20px; font-size: 12px; color: #666;">Codici nel DB (esempio): ' . implode(', ', $dbCodes) . '</div>';
    }

    // Show quiz list
    $quizzes = $importer->get_quizzes();
    echo '<div class="log-line" style="margin-left: 20px; font-size: 12px; color: #666;">Quiz: ';
    $quizNames = [];
    foreach ($quizzes as $name => $data) {
        $quizNames[] = $data['short_name'] . ' (' . count($data['questions']) . ')';
    }
    echo implode(', ', $quizNames);
    echo '</div>';

    // Execute multi-quiz import
    echo '<div class="log-line info">🚀 Creazione quiz e domande...</div>';

    $result = $importer->create_all_quizzes($courseid, $assign_competencies);

    if ($result['success']) {
        echo '<div class="log-line success" style="font-size: 15px; font-weight: bold;">✅ Import completato!</div>';
        echo '<div class="log-line success">📚 Quiz creati: ' . $result['quizzes_created'] . '</div>';
        echo '<div class="log-line success">❓ Domande create: ' . $result['questions_created'] . '</div>';
        if ($result['questions_existing'] > 0) {
            echo '<div class="log-line warning">⚠️ Domande esistenti (aggiornate): ' . $result['questions_existing'] . '</div>';
        }
        echo '<div class="log-line success">🎯 Competenze assegnate: ' . $result['competencies_assigned'] . '</div>';

        // Detail per quiz
        if (!empty($result['quiz_details'])) {
            echo '<div class="log-line" style="margin-top: 15px; font-weight: bold;">📋 Dettaglio per Quiz:</div>';
            foreach ($result['quiz_details'] as $qd) {
                $status = ($qd['created'] > 0) ? '✅' : '📂';
                echo '<div class="log-line" style="margin-left: 20px; font-size: 13px;">';
                echo $status . ' <strong>' . htmlspecialchars($qd['name']) . '</strong> - ';
                echo $qd['questions'] . ' domande';
                if ($qd['existing'] > 0) {
                    echo ' (' . $qd['existing'] . ' esistenti)';
                }
                echo ', ' . $qd['competencies'] . ' competenze';
                echo '</div>';
            }
        }

        echo '<div class="log-line success" style="font-size: 16px; margin-top: 20px; padding: 10px; background: #d1fae5; border-radius: 6px;">🎉 IMPORT MULTI-QUIZ COMPLETATO CON SUCCESSO!</div>';
    } else {
        echo '<div class="log-line error">❌ Errore durante l\'import</div>';
        foreach ($result['errors'] as $error) {
            echo '<div class="log-line error">   ' . htmlspecialchars($error) . '</div>';
        }
    }

    // Cleanup
    if (file_exists($SESSION->excel_import_file)) {
        @unlink($SESSION->excel_import_file);
    }
    unset($SESSION->excel_import_file);
    unset($SESSION->excel_import_filename);
?>
        </div>

        <div class="btn-group" style="margin-top: 20px;">
            <a href="<?php echo new moodle_url('/local/competencyxmlimport/setup_universale.php', ['courseid' => $courseid, 'step' => 1]); ?>" class="btn btn-secondary">
                ← Nuovo Import
            </a>
            <a href="<?php echo new moodle_url('/course/view.php', ['id' => $courseid]); ?>" class="btn btn-primary">
                📚 Vai al Corso
            </a>
        </div>
    </div>
</div>

<?php
endif;

// ============================================================================
// STEP 5: ESECUZIONE
// ============================================================================

if ($step == 5 && $action === 'execute'):
    require_sesskey();
    
    $frameworkid = $session_data['frameworkid'];
    $sector = $session_data['sector'];
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    
    $quizzes_config = $_POST['quiz'] ?? [];
    $assign_competencies = isset($_POST['assign_competencies']);
    $smart_mapping = isset($_POST['smart_mapping']);
    
    // Carica competenze del settore
    $competencies = get_sector_competencies($frameworkid, $sector);
    // Crea lookup con chiavi MAIUSCOLE per matching case-insensitive (UTF-8)
    $comp_lookup = [];
    foreach ($competencies as $c) {
        $comp_lookup[mb_strtoupper($c->idnumber, 'UTF-8')] = $c->id;
    }
?>

<div class="setup-page">
    <div class="setup-header">
        <h2>🚀 Setup in Esecuzione...</h2>
        <p>Framework: <strong><?php echo format_string($framework->shortname); ?></strong> | Settore: <strong><?php echo $sector; ?></strong></p>
    </div>
    
    <!-- Steps indicator -->
    <div class="steps-container">
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Framework</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Settore</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">File XML</div></div>
        <div class="step-item completed"><div class="step-number">✓</div><div class="step-label">Configura Quiz</div></div>
        <div class="step-item active"><div class="step-number">5</div><div class="step-label">Esegui</div></div>
    </div>
    
    <div class="panel">
        <h3><span class="icon">📋</span> Log Operazioni</h3>
        <div class="progress-log">
<?php
    // Flush output
    ob_implicit_flush(true);
    
    $total_questions = 0;
    $total_competencies = 0;
    $total_levels_updated = 0;
    $quizzes_created = [];
    
    echo '<div class="log-line info">🔄 Inizio setup...</div>';
    echo '<div class="log-line">📊 Caricate ' . count($comp_lookup) . ' competenze per settore ' . $sector . '</div>';

    // Debug: mostra primi 5 codici competenza disponibili
    $sample_codes = array_slice(array_keys($comp_lookup), 0, 5);
    if (!empty($sample_codes)) {
        echo '<div class="log-line" style="font-size:11px;color:#666;">🔍 Esempio codici nel DB: ' . implode(', ', $sample_codes) . '</div>';
    }
    
    // Crea categoria madre
    $parent_cat = $DB->get_record('question_categories', [
        'contextid' => $context->id,
        'name' => $sector
    ]);
    
    if (!$parent_cat) {
        $parent_cat = new stdClass();
        $parent_cat->name = $sector;
        $parent_cat->contextid = $context->id;
        $parent_cat->info = 'Domande ' . $sector;
        $parent_cat->infoformat = FORMAT_HTML;
        $parent_cat->parent = 0;
        $parent_cat->sortorder = 999;
        $parent_cat->stamp = make_unique_id_code();
        $parent_cat->id = $DB->insert_record('question_categories', $parent_cat);
        echo '<div class="log-line success">✅ Creata categoria: ' . $sector . '</div>';
    }
    
    // Trova sezione corso
    $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0]);
    
    // Processa ogni quiz
    $skipped_count = 0;
    foreach ($quizzes_config as $config) {
        // Salta se non selezionato
        if (empty($config['enabled'])) {
            $skipped_count++;
            echo '<div class="log-line" style="color: #888;">⏭️ Saltato: ' . basename($config['file']) . ' (non selezionato)</div>';
            continue;
        }

        $filepath = $config['file'];
        $quiz_name = $config['name'];
        $level = (int)$config['level'];
        $category_name = $config['category'];

        echo '<div class="log-line info">📄 Processo: ' . basename($filepath) . '</div>';

        if (!file_exists($filepath)) {
            echo '<div class="log-line error">❌ File non trovato: ' . $filepath . '</div>';
            continue;
        }
        
        // Crea sottocategoria
        $sub_cat = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'name' => $category_name,
            'parent' => $parent_cat->id
        ]);
        
        if (!$sub_cat) {
            $sub_cat = new stdClass();
            $sub_cat->name = $category_name;
            $sub_cat->contextid = $context->id;
            $sub_cat->info = '';
            $sub_cat->infoformat = FORMAT_HTML;
            $sub_cat->parent = $parent_cat->id;
            $sub_cat->sortorder = 999;
            $sub_cat->stamp = make_unique_id_code();
            $sub_cat->id = $DB->insert_record('question_categories', $sub_cat);
        }
        
        // Leggi XML
        $xml = file_get_contents($filepath);
        preg_match_all('/<question type="multichoice">(.*?)<\/question>/s', $xml, $matches);
        
        $question_ids = [];
        
        foreach ($matches[0] as $qxml) {
            // Estrai nome (tag <name> nel formato Moodle XML)
            preg_match('/<name><text>(.*?)<\/text><\/name>/', $qxml, $name_match);
            $full_name = isset($name_match[1]) ? trim($name_match[1]) : 'Domanda';
            
            // Estrai testo
            preg_match('/<questiontext.*?><text>(.*?)<\/text>/s', $qxml, $text_match);
            $qtext = isset($text_match[1]) ? html_entity_decode($text_match[1]) : '';
            
            // Estrai competenza
            $comp_code = extract_competency_code($full_name, $sector);
            if (!$comp_code) {
                $comp_code = extract_competency_code($qtext, $sector);
            }

            // Debug: mostra prima domanda di ogni file per diagnostica
            static $debug_count = 0;
            if ($debug_count < 3) {
                $found_in_lookup = ($comp_code && isset($comp_lookup[$comp_code])) ? '✅' : '❌';
                echo '<div class="log-line" style="font-size:10px;color:#888;">   🔎 Debug Q' . ($debug_count+1) . ': name="' . htmlspecialchars(substr($full_name, 0, 60)) . '..." → code=' . ($comp_code ?: 'NULL') . ' ' . $found_in_lookup . '</div>';
                $debug_count++;
            }
            
            // Verifica se domanda esiste già
            $existing = $DB->get_record_sql("
                SELECT q.id FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid = ? AND q.name = ?
            ", [$sub_cat->id, $full_name]);

            if ($existing) {
                $question_ids[] = $existing->id;

                // Assegna competenza anche a domande esistenti (o aggiorna livello se già assegnata)
                if ($assign_competencies && $comp_code && isset($comp_lookup[$comp_code])) {
                    $existing_comp = $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $existing->id]);
                    if (!$existing_comp) {
                        // Nuova assegnazione
                        $rec = new stdClass();
                        $rec->questionid = $existing->id;
                        $rec->competencyid = $comp_lookup[$comp_code];
                        $rec->difficultylevel = $level;
                        $DB->insert_record('qbank_competenciesbyquestion', $rec);
                        $total_competencies++;
                    } else if ($existing_comp->difficultylevel != $level) {
                        // Aggiorna livello difficoltà se diverso
                        $DB->set_field('qbank_competenciesbyquestion', 'difficultylevel', $level, ['id' => $existing_comp->id]);
                        $total_levels_updated++;
                    }

                    // Aggiorna idnumber su question_bank_entry se mancante (fix per domande pre-esistenti)
                    $qbe_existing = $DB->get_record_sql("
                        SELECT qbe.id, qbe.idnumber
                        FROM {question_bank_entries} qbe
                        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                        WHERE qv.questionid = ?
                    ", [$existing->id]);
                    if ($qbe_existing && empty($qbe_existing->idnumber)) {
                        $DB->set_field('question_bank_entries', 'idnumber', $comp_code, ['id' => $qbe_existing->id]);
                    }
                }
                continue;
            }
            
            // Crea question_bank_entry
            $qbe = new stdClass();
            $qbe->questioncategoryid = $sub_cat->id;
            $qbe->ownerid = $USER->id;
            // IMPORTANTE: Salva il codice competenza nel campo idnumber per la test suite
            $qbe->idnumber = $comp_code ?: '';
            $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
            
            // Crea question
            $question = new stdClass();
            $question->name = $full_name;
            $question->questiontext = $qtext;
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
            $question->id = $DB->insert_record('question', $question);
            
            // Question version
            $qv = new stdClass();
            $qv->questionbankentryid = $qbe->id;
            $qv->questionid = $question->id;
            $qv->version = 1;
            $qv->status = 'ready';
            $DB->insert_record('question_versions', $qv);
            
            // Opzioni multichoice
            $opts = new stdClass();
            $opts->questionid = $question->id;
            $opts->single = 1;
            $opts->shuffleanswers = 1;
            $opts->answernumbering = 'abc';
            $opts->correctfeedback = '';
            $opts->correctfeedbackformat = FORMAT_HTML;
            $opts->partiallycorrectfeedback = '';
            $opts->partiallycorrectfeedbackformat = FORMAT_HTML;
            $opts->incorrectfeedback = '';
            $opts->incorrectfeedbackformat = FORMAT_HTML;
            $opts->shownumcorrect = 0;
            $DB->insert_record('qtype_multichoice_options', $opts);
            
            // Risposte
            preg_match_all('/<answer fraction="(\d+)"[^>]*>\s*<text>(.*?)<\/text>/s', $qxml, $answers);
            for ($i = 0; $i < count($answers[0]); $i++) {
                $ans = new stdClass();
                $ans->question = $question->id;
                $ans->answer = html_entity_decode($answers[2][$i]);
                $ans->answerformat = FORMAT_HTML;
                $ans->fraction = $answers[1][$i] / 100;
                $ans->feedback = '';
                $ans->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $ans);
            }
            
            // Assegna competenza
            if ($assign_competencies && $comp_code && isset($comp_lookup[$comp_code])) {
                $rec = new stdClass();
                $rec->questionid = $question->id;
                $rec->competencyid = $comp_lookup[$comp_code];
                $rec->difficultylevel = $level;
                $DB->insert_record('qbank_competenciesbyquestion', $rec);
                $total_competencies++;
            }
            
            $question_ids[] = $question->id;
            $total_questions++;
        }
        
        echo '<div class="log-line success">✅ Importate ' . count($question_ids) . ' domande</div>';
        
        // Crea Quiz
        $quiz = new stdClass();
        $quiz->course = $courseid;
        $quiz->name = $quiz_name;
        $quiz->intro = '<p>Quiz generato automaticamente</p>';
        $quiz->introformat = FORMAT_HTML;
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->attempts = 0;
        $quiz->grademethod = 1;
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->grade = 10;
        $quiz->sumgrades = 0;
        $quiz->shuffleanswers = 1;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->reviewattempt = 69904;
        $quiz->reviewcorrectness = 69904;
        $quiz->reviewmaxmarks = 69904;
        $quiz->reviewmarks = 69904;
        $quiz->reviewspecificfeedback = 69904;
        $quiz->reviewgeneralfeedback = 69904;
        $quiz->reviewrightanswer = 69904;
        $quiz->reviewoverallfeedback = 69904;
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->id = $DB->insert_record('quiz', $quiz);
        
        // Course module
        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
        $cm = new stdClass();
        $cm->course = $courseid;
        $cm->module = $module->id;
        $cm->instance = $quiz->id;
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->visibleoncoursepage = 1;
        $cm->added = time();
        $cm->id = $DB->insert_record('course_modules', $cm);
        
        course_add_cm_to_section($courseid, $cm->id, $section->section);
        context_module::instance($cm->id);
        $modcontext = context_module::instance($cm->id);
        
        // Quiz section
        $qs = new stdClass();
        $qs->quizid = $quiz->id;
        $qs->firstslot = 1;
        $qs->heading = '';
        $qs->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $qs);
        
        // Aggiungi domande al quiz
        $slot = 0;
        foreach ($question_ids as $qid) {
            $slot++;
            $qbe = $DB->get_record_sql("
                SELECT qbe.id FROM {question_bank_entries} qbe
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                WHERE qv.questionid = ?
            ", [$qid]);
            
            if (!$qbe) continue;
            
            $slotrecord = new stdClass();
            $slotrecord->quizid = $quiz->id;
            $slotrecord->slot = $slot;
            $slotrecord->page = ceil($slot / 5);
            $slotrecord->maxmark = 1.0;
            $slotrecord->id = $DB->insert_record('quiz_slots', $slotrecord);
            
            $qref = new stdClass();
            $qref->usingcontextid = $modcontext->id;
            $qref->component = 'mod_quiz';
            $qref->questionarea = 'slot';
            $qref->itemid = $slotrecord->id;
            $qref->questionbankentryid = $qbe->id;
            $qref->version = null;
            $DB->insert_record('question_references', $qref);
        }
        
        $DB->set_field('quiz', 'sumgrades', $slot, ['id' => $quiz->id]);
        
        echo '<div class="log-line success">✅ Creato quiz: ' . $quiz_name . ' (' . $slot . ' domande)</div>';
        
        $quizzes_created[] = [
            'name' => $quiz_name,
            'cmid' => $cm->id,
            'questions' => $slot,
            'level' => $level
        ];
    }
    
    // Rebuild cache
    rebuild_course_cache($courseid, true);
    
    echo '<div class="log-line success">✅ Cache corso aggiornata</div>';
    echo '<div class="log-line info">🎉 Setup completato!</div>';
    
    // Reset sessione
    unset($_SESSION['setup_universale']);
?>
        </div>
    </div>
    
    <!-- Statistiche finali -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-value"><?php echo count($quizzes_created); ?></div>
            <div class="stat-label">Quiz Creati</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo $total_questions; ?></div>
            <div class="stat-label">Domande Importate</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?php echo $total_competencies; ?></div>
            <div class="stat-label">Competenze Assegnate</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);">
            <div class="stat-value"><?php echo $total_levels_updated; ?></div>
            <div class="stat-label">Livelli Aggiornati</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($comp_lookup); ?></div>
            <div class="stat-label">Competenze Disponibili</div>
        </div>
    </div>
    
    <div class="panel">
        <h3><span class="icon">🎉</span> Setup Completato!</h3>
        
        <table class="mapping-table">
            <tr>
                <th>Quiz</th>
                <th>Domande</th>
                <th>Livello</th>
                <th>Azione</th>
            </tr>
            <?php
            $level_labels = [1 => '⭐ Base', 2 => '⭐⭐ Intermedio', 3 => '⭐⭐⭐ Avanzato'];
            foreach ($quizzes_created as $qc):
            ?>
            <tr>
                <td><?php echo $qc['name']; ?></td>
                <td><?php echo $qc['questions']; ?></td>
                <td><?php echo $level_labels[$qc['level']] ?? '⭐⭐ Intermedio'; ?></td>
                <td><a href="<?php echo $CFG->wwwroot; ?>/mod/quiz/view.php?id=<?php echo $qc['cmid']; ?>" class="btn btn-success" style="padding: 8px 16px; font-size: 13px;">Apri Quiz</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="btn-group">
            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>" class="btn btn-primary">📚 Vai al Corso</a>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">📊 Dashboard</a>
            <a href="?courseid=<?php echo $courseid; ?>&step=1" class="btn btn-secondary">🔄 Nuovo Setup</a>
        </div>
    </div>
</div>

<?php
endif;

echo $OUTPUT->footer();
