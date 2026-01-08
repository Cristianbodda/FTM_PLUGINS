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
        // Estrae il prefisso (es. AUTOMOBILE, MECCANICA, ecc.)
        if (preg_match('/^([A-Z]+)_/', $c->idnumber, $m)) {
            $prefix = $m[1];
            if (!isset($sectors[$prefix])) {
                $sectors[$prefix] = ['name' => $prefix, 'count' => 0];
            }
            $sectors[$prefix]['count']++;
        }
    }
    
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
 * Estrae il codice competenza da un testo
 */
function extract_competency_code($text, $sector) {
    $pattern = '/(' . preg_quote($sector, '/') . '_[A-Za-z]+_[A-Z0-9]+)/i';
    if (preg_match($pattern, $text, $m)) {
        return strtoupper($m[1]);
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
        'INFORMATICA' => '💻',
        'LOGISTICA' => '📦',
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
    
    if (isset($_FILES['xmlfiles']) && !empty($_FILES['xmlfiles']['name'][0])) {
        $uploaded_count = 0;
        
        foreach ($_FILES['xmlfiles']['name'] as $key => $filename) {
            if ($_FILES['xmlfiles']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['xmlfiles']['tmp_name'][$key];
                
                // Verifica che sia un file XML
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($ext !== 'xml') {
                    $upload_error .= "⚠️ File ignorato (non XML): $filename<br>";
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
    
    // Leggi tutti i file XML nella cartella (non filtrati per settore, mostriamo tutto)
    $uploaded_files = [];
    if (is_dir($xml_dir)) {
        $files = scandir($xml_dir);
        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml') {
                $filepath = $xml_dir . $file;
                $content = file_get_contents($filepath);
                $question_count = preg_match_all('/<question type="multichoice"/', $content);
                
                // Verifica se è del settore corrente cercando nel CONTENUTO del file
                // Cerca codici competenza con prefisso del settore (es. MECCANICA_xxx o AUTOMOBILE_xxx)
                $sector_pattern = '/' . preg_quote($sector, '/') . '_[A-Za-z]+_[A-Z0-9]+/i';
                $is_sector = preg_match($sector_pattern, $content) > 0;
                
                $uploaded_files[] = [
                    'name' => $file,
                    'path' => $filepath,
                    'questions' => $question_count,
                    'is_sector' => $is_sector,
                    'size' => filesize($filepath)
                ];
            }
        }
    }
    
    // Filtra solo file del settore
    $sector_files = array_filter($uploaded_files, function($f) { return $f['is_sector']; });
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
        
        <!-- Box download template -->
        <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50; border-radius: 10px; padding: 20px; margin-bottom: 25px;">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <h4 style="margin: 0 0 8px 0; color: #2e7d32;">📋 Scarica il Template di Esempio</h4>
                    <p style="margin: 0; font-size: 14px; color: #555;">
                        Prima di creare le tue domande, scarica il template XML con la struttura corretta. 
                        Include 4 esempi commentati con tutte le opzioni disponibili.
                    </p>
                </div>
                <div>
                    <a href="download_template.php?type=questions&sector=<?php echo urlencode($sector); ?>" 
                       class="btn btn-success" style="background: #4caf50; white-space: nowrap;">
                        ⬇️ Scarica Template XML
                    </a>
                </div>
            </div>
        </div>
        
        <form method="post" enctype="multipart/form-data" action="?courseid=<?php echo $courseid; ?>&step=3">
            <div class="upload-area" onclick="document.getElementById('xmlfiles').click();" id="dropzone">
                <div class="icon">📁</div>
                <p><strong>Trascina qui i file XML</strong></p>
                <p style="font-size: 14px; color: #888;">oppure clicca per selezionare</p>
                <p style="font-size: 12px; color: #aaa; margin-top: 10px;">Formato: Moodle XML con domande multichoice</p>
                <input type="file" name="xmlfiles[]" id="xmlfiles" multiple accept=".xml" style="display:none;" onchange="this.form.submit();">
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
    
    <?php if (!empty($sector_files)): ?>
    <div class="panel">
        <h3><span class="icon">✅</span> File Pronti per <?php echo $sector; ?> (<?php echo count($sector_files); ?>)</h3>
        
        <div class="file-list">
            <?php foreach ($sector_files as $file): ?>
            <div class="file-item">
                <div class="file-icon">📄</div>
                <div class="file-info">
                    <div class="file-name"><?php echo $file['name']; ?></div>
                    <div class="file-meta">
                        <?php echo $file['questions']; ?> domande • 
                        <?php echo round($file['size'] / 1024, 1); ?> KB
                    </div>
                </div>
                <div class="file-status" style="color: #27ae60; font-weight: 500;">✓ Pronto</div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&step=4">
            <input type="hidden" name="files" value='<?php echo htmlspecialchars(json_encode(array_values($sector_files))); ?>'>
            
            <div class="btn-group">
                <a href="?courseid=<?php echo $courseid; ?>&step=2" class="btn btn-secondary">← Indietro</a>
                <button type="submit" class="btn btn-primary">Avanti → Configura Quiz</button>
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
            
            <p>Configura i quiz che verranno creati da ciascun file XML:</p>
            
            <?php foreach ($files as $i => $file): ?>
            <div class="quiz-config-item">
                <div class="quiz-header">
                    <span class="quiz-title">📄 <?php echo $file['name']; ?></span>
                    <span class="quiz-badge badge-base"><?php echo $file['questions']; ?> domande</span>
                </div>
                <div class="quiz-fields">
                    <div>
                        <label>Nome Quiz</label>
                        <input type="text" name="quiz[<?php echo $i; ?>][name]" 
                               value="<?php echo $sector; ?> - <?php echo pathinfo($file['name'], PATHINFO_FILENAME); ?>" required>
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
    $comp_lookup = [];
    foreach ($competencies as $c) {
        $comp_lookup[$c->idnumber] = $c->id;
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
    $quizzes_created = [];
    
    echo '<div class="log-line info">🔄 Inizio setup...</div>';
    echo '<div class="log-line">📊 Caricate ' . count($comp_lookup) . ' competenze per settore ' . $sector . '</div>';
    
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
    foreach ($quizzes_config as $config) {
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
            // Estrai nome
            preg_match('/<n><text>(.*?)<\/text><\/n>/', $qxml, $name_match);
            $full_name = isset($name_match[1]) ? trim($name_match[1]) : 'Domanda';
            
            // Estrai testo
            preg_match('/<questiontext.*?><text>(.*?)<\/text>/s', $qxml, $text_match);
            $qtext = isset($text_match[1]) ? html_entity_decode($text_match[1]) : '';
            
            // Estrai competenza
            $comp_code = extract_competency_code($full_name, $sector);
            if (!$comp_code) {
                $comp_code = extract_competency_code($qtext, $sector);
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
                continue;
            }
            
            // Crea question_bank_entry
            $qbe = new stdClass();
            $qbe->questioncategoryid = $sub_cat->id;
            $qbe->ownerid = $USER->id;
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
            preg_match_all('/<answer fraction="(\d+)".*?><text>(.*?)<\/text>/s', $qxml, $answers);
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
            'questions' => $slot
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
                <th>Azione</th>
            </tr>
            <?php foreach ($quizzes_created as $qc): ?>
            <tr>
                <td><?php echo $qc['name']; ?></td>
                <td><?php echo $qc['questions']; ?></td>
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
