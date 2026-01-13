<?php
/**
 * Diagnostica Framework Competenze - VERSIONE 2
 * 
 * Report completo con:
 * - Selezione framework e settore
 * - Visualizzazione aree con competenze
 * - Domande assegnate per competenza
 * - Statistiche dettagliate
 * 
 * @package    local_competencyxmlimport
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);
$view = optional_param('view', 'overview', PARAM_ALPHA); // overview, areas, questions
$areacode = optional_param('area', '', PARAM_TEXT);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/diagnostics.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Diagnostica - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Ottiene i settori disponibili in un framework
 */
function get_framework_sectors($frameworkid) {
    global $DB;
    
    $competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber');
    $sectors = [];
    
    foreach ($competencies as $c) {
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
 * Ottiene le aree di un settore
 */
function get_sector_areas($frameworkid, $sector) {
    global $DB;
    
    $sql = "SELECT id, idnumber, shortname, description 
            FROM {competency} 
            WHERE competencyframeworkid = ? AND idnumber LIKE ?
            ORDER BY idnumber";
    
    $competencies = $DB->get_records_sql($sql, [$frameworkid, $sector . '_%']);
    
    // Raggruppa per area (secondo elemento del codice)
    $areas = [];
    foreach ($competencies as $c) {
        $parts = explode('_', $c->idnumber);
        if (count($parts) >= 2) {
            $areaCode = $parts[1];
            if (!isset($areas[$areaCode])) {
                $areas[$areaCode] = [
                    'code' => $areaCode,
                    'name' => get_area_name($areaCode),
                    'competencies' => [],
                    'count' => 0
                ];
            }
            $areas[$areaCode]['competencies'][] = $c;
            $areas[$areaCode]['count']++;
        }
    }
    
    return $areas;
}

/**
 * Nome descrittivo per codice area
 */
function get_area_name($code) {
    $names = [
        'DT' => 'Disegno Tecnico',
        'MIS' => 'Metrologia e Misure',
        'LMB' => 'Lavorazioni Meccaniche Base',
        'LMC' => 'Macchine Convenzionali',
        'CNC' => 'CNC e Programmazione',
        'ASS' => 'Assemblaggio',
        'GEN' => 'Processi Generali',
        'PIAN' => 'Pianificazione',
        'MAN' => 'Manutenzione',
        'AUT' => 'Automazione',
        'SAQ' => 'Sicurezza e Qualità',
        'CSP' => 'Collaborazione',
        'PRG' => 'Progettazione',
        'MR' => 'Meccatronico Riparatore',
        'MAu' => 'Meccatronico Automobile',
        'HV' => 'Alta Tensione',
        'ADAS' => 'Sistemi ADAS',
        'MOT' => 'Motore e Powertrain',
        'ELET' => 'Elettronica Veicolo',
        'HVAC' => 'Climatizzazione',
    ];
    return $names[$code] ?? $code;
}

/**
 * Icona per area
 */
function get_area_icon($code) {
    $icons = [
        'DT' => '📐', 'MIS' => '📏', 'LMB' => '🔧', 'LMC' => '⚙️',
        'CNC' => '🖥️', 'ASS' => '🔩', 'GEN' => '🏭', 'PIAN' => '📋',
        'MAN' => '🔧', 'AUT' => '🤖', 'SAQ' => '🛡️', 'CSP' => '👥', 'PRG' => '📊',
        'MR' => '🔧', 'MAu' => '🚗', 'HV' => '⚡', 'ADAS' => '📡',
        'MOT' => '🔌', 'ELET' => '💡', 'HVAC' => '❄️',
    ];
    return $icons[$code] ?? '📁';
}

/**
 * Ottiene le domande assegnate a una competenza
 */
function get_competency_questions($competencyid, $contextid) {
    global $DB;
    
    $sql = "SELECT q.id, q.name, q.questiontext, qbc.difficultylevel
            FROM {question} q
            JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qbc.competencyid = ?
            AND qc.contextid = ?
            ORDER BY q.name";
    
    return $DB->get_records_sql($sql, [$competencyid, $contextid]);
}

/**
 * Conta le domande per area
 */
function count_area_questions($frameworkid, $sector, $areacode, $contextid) {
    global $DB;
    
    $sql = "SELECT COUNT(DISTINCT q.id) as cnt
            FROM {question} q
            JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            JOIN {competency} c ON c.id = qbc.competencyid
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE c.competencyframeworkid = ?
            AND c.idnumber LIKE ?
            AND qc.contextid = ?";
    
    $pattern = $sector . '_' . $areacode . '_%';
    $result = $DB->get_record_sql($sql, [$frameworkid, $pattern, $contextid]);
    
    return $result ? $result->cnt : 0;
}

// CSS
$customcss = '
<style>
.diag-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.diag-header {
    background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);
}
.diag-header h2 { margin: 0 0 8px 0; }
.diag-header p { margin: 0; opacity: 0.9; }

.diag-panel {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    margin-bottom: 20px;
}
.diag-panel h3 {
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Filtri */
.filters-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
}
.filter-group select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    min-width: 200px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.filter-group select:focus {
    outline: none;
    border-color: #6f42c1;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.stat-card.purple { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.stat-card.green { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.stat-card.blue { background: linear-gradient(135deg, #17a2b8, #007bff); color: white; }
.stat-card.orange { background: linear-gradient(135deg, #fd7e14, #ffc107); color: white; }
.stat-card.pink { background: linear-gradient(135deg, #e83e8c, #6f42c1); color: white; }
.stat-card .number { font-size: 28px; font-weight: bold; }
.stat-card .label { font-size: 12px; opacity: 0.9; }

/* Tabs */
.view-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}
.view-tab {
    padding: 10px 20px;
    border-radius: 8px 8px 0 0;
    text-decoration: none;
    color: #666;
    font-weight: 500;
    transition: all 0.3s;
}
.view-tab:hover {
    background: #f0f0f0;
    color: #333;
}
.view-tab.active {
    background: #6f42c1;
    color: white;
}

/* Area Cards */
.areas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.area-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
}
.area-card:hover {
    border-color: #6f42c1;
    box-shadow: 0 5px 20px rgba(111, 66, 193, 0.15);
}
.area-card-header {
    padding: 15px 20px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.area-card-header .area-icon {
    font-size: 28px;
}
.area-card-header .area-info {
    flex: 1;
    margin-left: 15px;
}
.area-card-header .area-code {
    font-family: monospace;
    font-size: 12px;
    color: #6f42c1;
    background: #e9ecef;
    padding: 2px 8px;
    border-radius: 4px;
}
.area-card-header .area-name {
    font-weight: 600;
    color: #333;
    margin-top: 5px;
}
.area-card-stats {
    display: flex;
    gap: 15px;
    padding: 15px 20px;
    background: white;
}
.area-stat {
    text-align: center;
    flex: 1;
}
.area-stat .num {
    font-size: 24px;
    font-weight: bold;
    color: #6f42c1;
}
.area-stat .lbl {
    font-size: 11px;
    color: #888;
}
.area-card-footer {
    padding: 10px 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}
.area-card-footer a {
    color: #6f42c1;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.area-card-footer a:hover {
    text-decoration: underline;
}

/* Competency List */
.competency-list {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}
.competency-item {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: background 0.2s;
}
.competency-item:last-child {
    border-bottom: none;
}
.competency-item:hover {
    background: #f8f9fa;
}
.competency-item.expanded {
    background: #f0f4ff;
}
.competency-code {
    font-family: monospace;
    font-size: 12px;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 10px;
    border-radius: 4px;
    white-space: nowrap;
}
.competency-name {
    flex: 1;
    font-weight: 500;
    color: #333;
}
.competency-stats {
    display: flex;
    gap: 10px;
    align-items: center;
}
.competency-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.competency-badge.questions {
    background: #e3f2fd;
    color: #1565c0;
}
.competency-badge.no-questions {
    background: #fff3e0;
    color: #e65100;
}
.expand-btn {
    background: #6f42c1;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
.expand-btn:hover {
    background: #5a32a3;
}

/* Questions sublist */
.questions-sublist {
    background: #fafafa;
    padding: 15px 20px 15px 60px;
    border-top: 1px solid #eee;
    display: none;
}
.questions-sublist.visible {
    display: block;
}
.question-item {
    padding: 10px 15px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.question-item:last-child {
    margin-bottom: 0;
}
.question-level {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
}
.question-level.level-1 { background: #e8f5e9; color: #2e7d32; }
.question-level.level-2 { background: #fff3e0; color: #e65100; }
.question-level.level-3 { background: #fce4ec; color: #c2185b; }
.question-name {
    flex: 1;
    font-size: 13px;
}
.question-preview {
    color: #888;
    font-size: 11px;
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.data-table th, .data-table td {
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    text-align: left;
}
.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    position: sticky;
    top: 0;
}
.data-table tr:hover {
    background: #f8f9fa;
}
.data-table code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-purple { background: #e9d8fd; color: #553c9a; }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}
.btn-purple { background: #6f42c1; color: white; }
.btn-purple:hover { background: #5a32a3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn-outline {
    background: white;
    border: 2px solid #6f42c1;
    color: #6f42c1;
}
.btn-outline:hover {
    background: #6f42c1;
    color: white;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 20px;
    color: #6f42c1;
    text-decoration: none;
    font-weight: 500;
}
.back-link:hover { text-decoration: underline; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 40px;
    color: #888;
}
.empty-state .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

/* Scrollable container */
.scrollable {
    max-height: 500px;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .filters-row { flex-direction: column; }
    .filter-group select { min-width: 100%; }
    .areas-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
';

echo $OUTPUT->header();
echo $customcss;

// Carica framework
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');

// Se framework selezionato, carica settori
$sectors = [];
if ($frameworkid > 0) {
    $sectors = get_framework_sectors($frameworkid);
}

?>
<div class="diag-page">
    
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="diag-header">
        <h2>🔍 Diagnostica Framework Competenze</h2>
        <p>Report completo: Aree, Competenze e Domande assegnate per corso</p>
    </div>
    
    <!-- Filtri -->
    <div class="diag-panel">
        <form method="get" id="filterForm">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="view" value="<?php echo $view; ?>">
            
            <div class="filters-row">
                <div class="filter-group">
                    <label>📚 Framework</label>
                    <select name="frameworkid" onchange="document.getElementById('filterForm').submit()">
                        <option value="0">-- Seleziona Framework --</option>
                        <?php foreach ($frameworks as $fw): ?>
                        <option value="<?php echo $fw->id; ?>" <?php echo ($frameworkid == $fw->id) ? 'selected' : ''; ?>>
                            <?php echo format_string($fw->shortname); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($frameworkid > 0 && !empty($sectors)): ?>
                <div class="filter-group">
                    <label>🎯 Settore</label>
                    <select name="sector" onchange="document.getElementById('filterForm').submit()">
                        <option value="">-- Tutti i settori --</option>
                        <?php foreach ($sectors as $prefix => $data): ?>
                        <option value="<?php echo $prefix; ?>" <?php echo ($sector == $prefix) ? 'selected' : ''; ?>>
                            <?php echo $prefix; ?> (<?php echo $data['count']; ?> competenze)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

<?php if ($frameworkid > 0): 
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    
    // Statistiche generali
    $total_competencies = $DB->count_records('competency', ['competencyframeworkid' => $frameworkid]);
    
    // Competenze del settore selezionato
    $sector_filter = $sector ? $sector . '_%' : '%';
    $sector_competencies = $DB->get_records_sql("
        SELECT * FROM {competency} 
        WHERE competencyframeworkid = ? AND idnumber LIKE ?
        ORDER BY idnumber
    ", [$frameworkid, $sector_filter]);
    
    // Domande del corso
    $course_questions = $DB->count_records_sql("
        SELECT COUNT(q.id)
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
    ", [$context->id]);
    
    // Domande con competenza assegnata
    $assigned_questions = $DB->count_records_sql("
        SELECT COUNT(DISTINCT q.id)
        FROM {question} q
        JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
        JOIN {competency} c ON c.id = qbc.competencyid
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE c.competencyframeworkid = ?
        AND qc.contextid = ?
        " . ($sector ? "AND c.idnumber LIKE ?" : ""), 
        $sector ? [$frameworkid, $context->id, $sector . '_%'] : [$frameworkid, $context->id]
    );
    
    // Aree del settore
    $areas = $sector ? get_sector_areas($frameworkid, $sector) : [];
?>

    <!-- Statistiche -->
    <div class="diag-panel">
        <h3>📊 Statistiche <?php echo $sector ? "- Settore $sector" : "Generali"; ?></h3>
        
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="number"><?php echo $sector ? count($sector_competencies) : $total_competencies; ?></div>
                <div class="label">Competenze</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $sector ? count($areas) : count($sectors); ?></div>
                <div class="label"><?php echo $sector ? 'Aree' : 'Settori'; ?></div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $course_questions; ?></div>
                <div class="label">Domande Corso</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $assigned_questions; ?></div>
                <div class="label">Con Competenza</div>
            </div>
            <div class="stat-card pink">
                <div class="number"><?php echo $course_questions > 0 ? round($assigned_questions / $course_questions * 100) : 0; ?>%</div>
                <div class="label">Copertura</div>
            </div>
        </div>
    </div>
    
    <!-- Tabs di visualizzazione -->
    <div class="view-tabs">
        <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>&view=overview" 
           class="view-tab <?php echo ($view == 'overview') ? 'active' : ''; ?>">
            📊 Panoramica
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>&view=areas" 
           class="view-tab <?php echo ($view == 'areas') ? 'active' : ''; ?>">
            📁 Aree e Competenze
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>&view=questions" 
           class="view-tab <?php echo ($view == 'questions') ? 'active' : ''; ?>">
            ❓ Domande per Competenza
        </a>
    </div>

    <?php if ($view == 'overview'): ?>
    <!-- VISTA PANORAMICA -->
    
    <?php if ($sector && !empty($areas)): ?>
    <div class="diag-panel">
        <h3>📁 Aree del Settore <?php echo $sector; ?></h3>
        
        <div class="areas-grid">
            <?php foreach ($areas as $areaCode => $areaData): 
                $questionCount = count_area_questions($frameworkid, $sector, $areaCode, $context->id);
            ?>
            <div class="area-card">
                <div class="area-card-header">
                    <div class="area-icon"><?php echo get_area_icon($areaCode); ?></div>
                    <div class="area-info">
                        <span class="area-code"><?php echo $sector; ?>_<?php echo $areaCode; ?></span>
                        <div class="area-name"><?php echo $areaData['name']; ?></div>
                    </div>
                </div>
                <div class="area-card-stats">
                    <div class="area-stat">
                        <div class="num"><?php echo $areaData['count']; ?></div>
                        <div class="lbl">Competenze</div>
                    </div>
                    <div class="area-stat">
                        <div class="num"><?php echo $questionCount; ?></div>
                        <div class="lbl">Domande</div>
                    </div>
                </div>
                <div class="area-card-footer">
                    <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>&view=areas&area=<?php echo $areaCode; ?>">
                        Vedi dettagli →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php elseif (!$sector && !empty($sectors)): ?>
    <!-- Mostra tutti i settori -->
    <div class="diag-panel">
        <h3>🎯 Settori Disponibili</h3>
        
        <div class="areas-grid">
            <?php foreach ($sectors as $prefix => $data): ?>
            <div class="area-card">
                <div class="area-card-header">
                    <div class="area-icon">
                        <?php 
                        $sectorIcons = ['MECCANICA' => '⚙️', 'AUTOMOBILE' => '🚗', 'ELETTRICITA' => '⚡'];
                        echo $sectorIcons[$prefix] ?? '📁';
                        ?>
                    </div>
                    <div class="area-info">
                        <span class="area-code"><?php echo $prefix; ?></span>
                        <div class="area-name"><?php echo $prefix; ?></div>
                    </div>
                </div>
                <div class="area-card-stats">
                    <div class="area-stat">
                        <div class="num"><?php echo $data['count']; ?></div>
                        <div class="lbl">Competenze</div>
                    </div>
                </div>
                <div class="area-card-footer">
                    <a href="?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $prefix; ?>&view=overview">
                        Seleziona settore →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif ($view == 'areas'): ?>
    <!-- VISTA AREE E COMPETENZE -->
    
    <?php if ($sector): ?>
    <div class="diag-panel">
        <h3>📋 Competenze per Area - <?php echo $sector; ?></h3>
        
        <?php if (!empty($areas)): ?>
        <div class="scrollable">
            <?php foreach ($areas as $areaCode => $areaData): 
                // Se c'è un filtro area, mostra solo quella
                if ($areacode && $areacode != $areaCode) continue;
            ?>
            <div style="margin-bottom: 25px;">
                <h4 style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                    <span style="font-size: 24px;"><?php echo get_area_icon($areaCode); ?></span>
                    <span><?php echo $areaData['name']; ?></span>
                    <span class="badge badge-purple"><?php echo $areaCode; ?></span>
                    <span class="badge badge-info"><?php echo $areaData['count']; ?> competenze</span>
                </h4>
                
                <div class="competency-list">
                    <?php foreach ($areaData['competencies'] as $comp): 
                        $questions = get_competency_questions($comp->id, $context->id);
                        $qcount = count($questions);
                    ?>
                    <div class="competency-item" id="comp-<?php echo $comp->id; ?>">
                        <span class="competency-code"><?php echo $comp->idnumber; ?></span>
                        <span class="competency-name"><?php echo format_string($comp->shortname); ?></span>
                        <span class="competency-badge <?php echo $qcount > 0 ? 'questions' : 'no-questions'; ?>">
                            <?php echo $qcount; ?> domande
                        </span>
                        <?php if ($qcount > 0): ?>
                        <button class="expand-btn" onclick="toggleQuestions(<?php echo $comp->id; ?>)">
                            📝 Vedi
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($qcount > 0): ?>
                    <div class="questions-sublist" id="questions-<?php echo $comp->id; ?>">
                        <?php foreach ($questions as $q): ?>
                        <div class="question-item">
                            <span class="question-level level-<?php echo $q->difficultylevel ?: 1; ?>">
                                <?php echo str_repeat('⭐', $q->difficultylevel ?: 1); ?>
                            </span>
                            <span class="question-name"><?php echo format_string($q->name); ?></span>
                            <span class="question-preview">
                                <?php echo strip_tags(substr($q->questiontext, 0, 100)); ?>...
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>Nessuna competenza trovata per questo settore</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <div class="diag-panel">
        <div class="empty-state">
            <div class="icon">👆</div>
            <p>Seleziona un settore per vedere le aree e competenze</p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif ($view == 'questions'): ?>
    <!-- VISTA DOMANDE PER COMPETENZA -->
    
    <div class="diag-panel">
        <h3>❓ Domande con Competenza Assegnata</h3>
        
        <?php
        // Ottieni tutte le domande con competenza
        $questions_with_comp = $DB->get_records_sql("
            SELECT q.id, q.name, q.questiontext, 
                   c.idnumber as comp_code, c.shortname as comp_name,
                   qbc.difficultylevel
            FROM {question} q
            JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            JOIN {competency} c ON c.id = qbc.competencyid
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE c.competencyframeworkid = ?
            AND qc.contextid = ?
            " . ($sector ? "AND c.idnumber LIKE ?" : "") . "
            ORDER BY c.idnumber, q.name
        ", $sector ? [$frameworkid, $context->id, $sector . '_%'] : [$frameworkid, $context->id]);
        ?>
        
        <?php if (!empty($questions_with_comp)): ?>
        <p style="margin-bottom: 15px;">
            <span class="badge badge-info"><?php echo count($questions_with_comp); ?></span> domande con competenza assegnata
        </p>
        
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Competenza</th>
                        <th style="width: 40%;">Domanda</th>
                        <th style="width: 30%;">Anteprima</th>
                        <th style="width: 15%;">Livello</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions_with_comp as $q): ?>
                    <tr>
                        <td>
                            <code><?php echo $q->comp_code; ?></code><br>
                            <small style="color: #666;"><?php echo format_string($q->comp_name); ?></small>
                        </td>
                        <td><?php echo format_string($q->name); ?></td>
                        <td>
                            <small style="color: #888;">
                                <?php echo strip_tags(substr($q->questiontext, 0, 100)); ?>...
                            </small>
                        </td>
                        <td>
                            <span class="badge <?php 
                                echo $q->difficultylevel == 3 ? 'badge-danger' : 
                                    ($q->difficultylevel == 2 ? 'badge-warning' : 'badge-success'); 
                            ?>">
                                <?php echo str_repeat('⭐', $q->difficultylevel ?: 1); ?>
                                <?php 
                                $levels = [1 => 'Base', 2 => 'Intermedio', 3 => 'Avanzato'];
                                echo $levels[$q->difficultylevel] ?? 'Base';
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>Nessuna domanda con competenza assegnata trovata</p>
            <p><small>Usa il Setup Universale per importare domande e assegnare competenze</small></p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

<?php else: ?>
    <!-- Nessun framework selezionato -->
    <div class="diag-panel">
        <div class="empty-state">
            <div class="icon">👆</div>
            <p>Seleziona un framework per iniziare l'analisi</p>
        </div>
    </div>
<?php endif; ?>

</div>

<script>
function toggleQuestions(compId) {
    const sublist = document.getElementById('questions-' + compId);
    const item = document.getElementById('comp-' + compId);
    
    if (sublist.classList.contains('visible')) {
        sublist.classList.remove('visible');
        item.classList.remove('expanded');
    } else {
        sublist.classList.add('visible');
        item.classList.add('expanded');
    }
}
</script>

<?php

echo $OUTPUT->footer();
