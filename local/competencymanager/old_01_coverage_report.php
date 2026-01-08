<?php
/**
 * FTM Coverage Manager - Diagnostica e Fix Copertura Competenze
 * 
 * Tool completo per:
 * - Analizzare copertura competenze per corso/framework
 * - Identificare competenze senza domande
 * - Rilevare duplicati nel database
 * - Trovare domande orfane (senza competenza)
 * - Eseguire fix automatici
 * 
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 * CREATO: 04/01/2026
 */

require_once(__DIR__ . '/../../config.php');
require_login();

// Verifica permessi admin
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/coverage_report.php'));
$PAGE->set_title('FTM Coverage Manager');
$PAGE->set_heading('FTM Coverage Manager');
$PAGE->set_pagelayout('admin');

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Ottieni lista settori con conteggio competenze
 */
function get_sectors_list() {
    global $DB;
    
    $sql = "SELECT 
                SUBSTRING_INDEX(idnumber, '_', 1) as sector,
                COUNT(DISTINCT idnumber) as unique_competencies,
                COUNT(*) as total_records
            FROM {competency} 
            WHERE idnumber LIKE '%\\_%\\_%'
            AND idnumber NOT LIKE 'old%'
            AND idnumber NOT LIKE 'OLD%'
            GROUP BY SUBSTRING_INDEX(idnumber, '_', 1)
            ORDER BY sector";
    
    return $DB->get_records_sql($sql);
}

/**
 * Ottieni corsi con quiz
 */
function get_courses_with_quiz() {
    global $DB;
    
    $sql = "SELECT 
                c.id,
                c.shortname,
                c.fullname,
                COUNT(DISTINCT q.id) as num_quiz
            FROM {course} c
            JOIN {quiz} q ON q.course = c.id
            WHERE c.id > 1
            GROUP BY c.id, c.shortname, c.fullname
            ORDER BY c.shortname";
    
    return $DB->get_records_sql($sql);
}

/**
 * Auto-rileva settore dal nome corso
 */
function detect_sector_from_course($coursename) {
    $coursename = strtoupper($coursename);
    
    $mappings = [
        'ELETTRIC' => 'ELETTRICITÀ',
        'AUTOMAZ' => 'AUTOMAZIONE',
        'ELETTRON' => 'AUTOMAZIONE',
        'AUTOVEICOLO' => 'AUTOMOBILE',
        'AUTO' => 'AUTOMOBILE',
        'CHIMFARM' => 'CHIMFARM',
        'CHIMICA' => 'CHIMFARM',
        'LOGISTICA' => 'LOGISTICA',
        'MECCANICA' => 'MECCANICA',
        'MECC' => 'MECCANICA',
        'METALCOSTRUZIONE' => 'METALCOSTRUZIONE',
        'METAL' => 'METALCOSTRUZIONE',
        'GENERICO' => 'GEN',
        'GEN' => 'GEN'
    ];
    
    foreach ($mappings as $key => $value) {
        if (strpos($coursename, $key) !== false) {
            return $value;
        }
    }
    
    return '';
}

/**
 * Ottieni statistiche copertura per settore
 */
function get_coverage_stats($sector, $courseid = 0) {
    global $DB;
    
    $stats = [
        'sector' => $sector,
        'total_unique' => 0,
        'total_records' => 0,
        'duplicates' => 0,
        'with_questions' => 0,
        'without_questions' => 0,
        'coverage_percent' => 0,
        'profiles' => []
    ];
    
    // Totale competenze (uniche e record)
    $sql = "SELECT 
                COUNT(DISTINCT idnumber) as unique_count,
                COUNT(*) as total_count
            FROM {competency} 
            WHERE idnumber LIKE ?";
    $result = $DB->get_record_sql($sql, [$sector . '%']);
    
    $stats['total_unique'] = (int)$result->unique_count;
    $stats['total_records'] = (int)$result->total_count;
    $stats['duplicates'] = $stats['total_records'] - $stats['total_unique'];
    
    // Competenze con domande
    $sql = "SELECT COUNT(DISTINCT c.idnumber) as count
            FROM {competency} c
            JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
            WHERE c.idnumber LIKE ?";
    $result = $DB->get_record_sql($sql, [$sector . '%']);
    $stats['with_questions'] = (int)$result->count;
    
    $stats['without_questions'] = $stats['total_unique'] - $stats['with_questions'];
    $stats['coverage_percent'] = $stats['total_unique'] > 0 
        ? round(($stats['with_questions'] / $stats['total_unique']) * 100, 1) 
        : 0;
    
    // Statistiche per profilo
    $sql = "SELECT 
                SUBSTRING_INDEX(SUBSTRING_INDEX(idnumber, '_', 2), '_', -1) as profile,
                COUNT(DISTINCT idnumber) as unique_count
            FROM {competency} 
            WHERE idnumber LIKE ?
            GROUP BY profile
            ORDER BY profile";
    $profiles = $DB->get_records_sql($sql, [$sector . '%']);
    
    foreach ($profiles as $p) {
        // Conta quelle con domande per questo profilo
        $sql = "SELECT COUNT(DISTINCT c.idnumber) as count
                FROM {competency} c
                JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
                WHERE c.idnumber LIKE ?";
        $result = $DB->get_record_sql($sql, [$sector . '_' . $p->profile . '%']);
        
        $with_q = (int)$result->count;
        $total = (int)$p->unique_count;
        
        $stats['profiles'][$p->profile] = [
            'total' => $total,
            'with_questions' => $with_q,
            'without_questions' => $total - $with_q,
            'coverage' => $total > 0 ? round(($with_q / $total) * 100, 1) : 0
        ];
    }
    
    return $stats;
}

/**
 * Ottieni lista competenze senza domande
 */
function get_competencies_without_questions($sector) {
    global $DB;
    
    $sql = "SELECT DISTINCT c.id, c.idnumber, c.shortname, c.description
            FROM {competency} c
            LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
            WHERE c.idnumber LIKE ?
            AND qc.id IS NULL
            ORDER BY c.idnumber";
    
    return $DB->get_records_sql($sql, [$sector . '%']);
}

/**
 * Ottieni lista duplicati
 */
function get_duplicate_competencies($sector) {
    global $DB;
    
    $sql = "SELECT idnumber, COUNT(*) as count
            FROM {competency} 
            WHERE idnumber LIKE ?
            GROUP BY idnumber
            HAVING COUNT(*) > 1
            ORDER BY idnumber";
    
    return $DB->get_records_sql($sql, [$sector . '%']);
}

/**
 * Ottieni domande orfane (senza competenza) per un corso
 */
function get_orphan_questions($courseid) {
    global $DB;
    
    $sql = "SELECT DISTINCT qv.questionid, q.name as question_name, qz.name as quiz_name
            FROM {quiz} qz
            JOIN {quiz_slots} qs ON qs.quizid = qz.id
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
            JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
            JOIN {question} q ON q.id = qv.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            WHERE qz.course = ?
            AND qc.id IS NULL
            ORDER BY qz.name, q.name";
    
    return $DB->get_records_sql($sql, [$courseid]);
}

/**
 * Rimuovi duplicati competenze
 */
function fix_duplicate_competencies($sector) {
    global $DB;
    
    $fixed = 0;
    $duplicates = get_duplicate_competencies($sector);
    
    foreach ($duplicates as $dup) {
        // Trova tutti i record con questo idnumber
        $records = $DB->get_records('competency', ['idnumber' => $dup->idnumber], 'id ASC');
        
        if (count($records) > 1) {
            $keep_id = null;
            
            // Trova quello collegato a domande (se esiste)
            foreach ($records as $r) {
                $has_questions = $DB->record_exists('qbank_competenciesbyquestion', ['competencyid' => $r->id]);
                if ($has_questions) {
                    $keep_id = $r->id;
                    break;
                }
            }
            
            // Se nessuno ha domande, tieni il primo
            if ($keep_id === null) {
                $keep_id = reset($records)->id;
            }
            
            // Elimina gli altri
            foreach ($records as $r) {
                if ($r->id != $keep_id) {
                    // Prima aggiorna eventuali riferimenti
                    $DB->execute(
                        "UPDATE {qbank_competenciesbyquestion} SET competencyid = ? WHERE competencyid = ?",
                        [$keep_id, $r->id]
                    );
                    $DB->execute(
                        "UPDATE {local_selfassessment} SET competencyid = ? WHERE competencyid = ?",
                        [$keep_id, $r->id]
                    );
                    
                    // Poi elimina il duplicato
                    $DB->delete_records('competency', ['id' => $r->id]);
                    $fixed++;
                }
            }
        }
    }
    
    return $fixed;
}

// ============================================
// GESTIONE AZIONI
// ============================================

$message = '';
$messagetype = '';

if ($action === 'fixduplicates' && confirm_sesskey() && $sector) {
    $fixed = fix_duplicate_competencies($sector);
    $message = "✅ Rimossi $fixed duplicati per il settore $sector";
    $messagetype = 'success';
}

// ============================================
// CARICAMENTO DATI
// ============================================

$sectors = get_sectors_list();
$courses = get_courses_with_quiz();

$stats = null;
$competencies_missing = [];
$duplicates = [];
$orphan_questions = [];
$course = null;

if ($sector) {
    $stats = get_coverage_stats($sector, $courseid);
    $competencies_missing = get_competencies_without_questions($sector);
    $duplicates = get_duplicate_competencies($sector);
}

if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    $orphan_questions = get_orphan_questions($courseid);
    
    // Auto-detect settore se non specificato
    if (!$sector && $course) {
        $sector = detect_sector_from_course($course->shortname);
        if ($sector) {
            $stats = get_coverage_stats($sector, $courseid);
            $competencies_missing = get_competencies_without_questions($sector);
            $duplicates = get_duplicate_competencies($sector);
        }
    }
}

// ============================================
// OUTPUT HTML
// ============================================

echo $OUTPUT->header();
?>

<style>
.coverage-container {
    max-width: 1400px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.coverage-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.coverage-header h1 { margin: 0 0 10px 0; font-size: 28px; }
.coverage-header p { margin: 0; opacity: 0.9; }

.selector-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: end;
}
.selector-group { flex: 1; min-width: 200px; }
.selector-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #495057; }
.selector-group select {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1em;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-primary { background: #1e3c72; color: white; }
.btn-primary:hover { background: #2a5298; }
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-warning { background: #ffc107; color: #333; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
}
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { color: #666; font-size: 14px; margin-top: 5px; }
.stat-card.success .number { color: #28a745; }
.stat-card.warning .number { color: #ffc107; }
.stat-card.danger .number { color: #dc3545; }
.stat-card.info .number { color: #17a2b8; }

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-body { padding: 20px; }

.table {
    width: 100%;
    border-collapse: collapse;
}
.table th, .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.table th { background: #f8f9fa; font-weight: 600; }
.table tr:hover { background: #f8f9fa; }

.progress-bar {
    height: 24px;
    background: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}
.progress-bar .fill {
    height: 100%;
    border-radius: 12px;
    transition: width 0.3s;
}
.progress-bar .text {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-weight: 600;
    font-size: 12px;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #d1ecf1; color: #0c5460; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

.problem-card {
    border-left: 4px solid;
    padding: 15px 20px;
    margin-bottom: 15px;
    background: #f8f9fa;
    border-radius: 0 8px 8px 0;
}
.problem-card.critical { border-color: #dc3545; }
.problem-card.warning { border-color: #ffc107; }
.problem-card.info { border-color: #17a2b8; }

.tabs {
    display: flex;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
}
.tab {
    padding: 12px 24px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
}
.tab:hover { color: #1e3c72; }
.tab.active {
    color: #1e3c72;
    border-bottom-color: #1e3c72;
}

.competency-list {
    max-height: 400px;
    overflow-y: auto;
}
.competency-item {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.competency-item:hover { background: #f8f9fa; }
.competency-item code {
    background: #e9ecef;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
}
</style>

<div class="coverage-container">
    
    <!-- Header -->
    <div class="coverage-header">
        <h1>📊 FTM Coverage Manager</h1>
        <p>Diagnostica e gestione copertura competenze per corsi e framework</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Selettori -->
    <div class="selector-card">
        <form method="get" action="" style="display: contents;">
            <div class="selector-group">
                <label>📚 Corso</label>
                <select name="courseid" onchange="this.form.submit()">
                    <option value="">-- Seleziona Corso --</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c->id; ?>" <?php echo $courseid == $c->id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c->shortname); ?> (<?php echo $c->num_quiz; ?> quiz)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="selector-group">
                <label>🎯 Framework/Settore</label>
                <select name="sector" onchange="this.form.submit()">
                    <option value="">-- Seleziona Settore --</option>
                    <?php foreach ($sectors as $s): ?>
                    <option value="<?php echo $s->sector; ?>" <?php echo $sector == $s->sector ? 'selected' : ''; ?>>
                        <?php echo $s->sector; ?> (<?php echo $s->unique_competencies; ?> competenze)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
        </form>
    </div>
    
    <?php if ($stats): ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=overview" 
           class="tab <?php echo $tab == 'overview' ? 'active' : ''; ?>">📊 Panoramica</a>
        <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=problems" 
           class="tab <?php echo $tab == 'problems' ? 'active' : ''; ?>">⚠️ Problemi (<?php echo count($duplicates) + (count($competencies_missing) > 0 ? 1 : 0) + (count($orphan_questions) > 0 ? 1 : 0); ?>)</a>
        <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=missing" 
           class="tab <?php echo $tab == 'missing' ? 'active' : ''; ?>">📝 Competenze Mancanti (<?php echo count($competencies_missing); ?>)</a>
        <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=fix" 
           class="tab <?php echo $tab == 'fix' ? 'active' : ''; ?>">🔧 Fix Automatici</a>
    </div>
    
    <?php if ($tab == 'overview'): ?>
    <!-- TAB: PANORAMICA -->
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="number"><?php echo $stats['total_unique']; ?></div>
            <div class="label">🎯 Competenze Framework</div>
        </div>
        <div class="stat-card <?php echo $stats['coverage_percent'] >= 80 ? 'success' : ($stats['coverage_percent'] >= 50 ? 'warning' : 'danger'); ?>">
            <div class="number"><?php echo $stats['coverage_percent']; ?>%</div>
            <div class="label">📊 Copertura</div>
        </div>
        <div class="stat-card success">
            <div class="number"><?php echo $stats['with_questions']; ?></div>
            <div class="label">✅ Con Domande</div>
        </div>
        <div class="stat-card danger">
            <div class="number"><?php echo $stats['without_questions']; ?></div>
            <div class="label">❌ Senza Domande</div>
        </div>
        <div class="stat-card <?php echo $stats['duplicates'] > 0 ? 'warning' : 'success'; ?>">
            <div class="number"><?php echo $stats['duplicates']; ?></div>
            <div class="label">⚠️ Duplicati</div>
        </div>
    </div>
    
    <!-- Copertura Globale -->
    <div class="card">
        <div class="card-header">
            <span>📈 Copertura Globale: <?php echo $sector; ?></span>
            <span class="badge badge-<?php echo $stats['coverage_percent'] >= 80 ? 'success' : ($stats['coverage_percent'] >= 50 ? 'warning' : 'danger'); ?>">
                <?php echo $stats['with_questions']; ?>/<?php echo $stats['total_unique']; ?>
            </span>
        </div>
        <div class="card-body">
            <div class="progress-bar">
                <div class="fill" style="width: <?php echo $stats['coverage_percent']; ?>%; background: <?php echo $stats['coverage_percent'] >= 80 ? '#28a745' : ($stats['coverage_percent'] >= 50 ? '#ffc107' : '#dc3545'); ?>;"></div>
                <span class="text"><?php echo $stats['coverage_percent']; ?>%</span>
            </div>
        </div>
    </div>
    
    <!-- Copertura per Profilo -->
    <div class="card">
        <div class="card-header">📊 Copertura per Profilo</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Profilo</th>
                        <th>Framework</th>
                        <th>Coperte</th>
                        <th>Gap</th>
                        <th>Copertura</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['profiles'] as $profile => $data): 
                        $color = $data['coverage'] >= 80 ? '#28a745' : ($data['coverage'] >= 50 ? '#ffc107' : '#dc3545');
                        $badge = $data['coverage'] >= 80 ? 'success' : ($data['coverage'] >= 50 ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td><strong><?php echo $profile; ?></strong></td>
                        <td><?php echo $data['total']; ?></td>
                        <td style="color: #28a745; font-weight: bold;"><?php echo $data['with_questions']; ?></td>
                        <td style="color: #dc3545; font-weight: bold;"><?php echo $data['without_questions']; ?></td>
                        <td style="width: 200px;">
                            <div class="progress-bar" style="height: 20px;">
                                <div class="fill" style="width: <?php echo $data['coverage']; ?>%; background: <?php echo $color; ?>;"></div>
                                <span class="text" style="font-size: 11px;"><?php echo $data['coverage']; ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php elseif ($tab == 'problems'): ?>
    <!-- TAB: PROBLEMI -->
    
    <div class="card">
        <div class="card-header">⚠️ Problemi Rilevati</div>
        <div class="card-body">
            
            <?php if ($stats['duplicates'] > 0): ?>
            <div class="problem-card critical">
                <h4>🔴 Duplicati nel Database</h4>
                <p><strong><?php echo $stats['duplicates']; ?></strong> competenze duplicate trovate nel framework <?php echo $sector; ?>.</p>
                <p>Ogni competenza esiste in più copie, causando potenziali problemi di collegamento.</p>
                <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=fix" class="btn btn-danger">🔧 Vai ai Fix</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($competencies_missing) > 0): ?>
            <div class="problem-card <?php echo $stats['coverage_percent'] < 50 ? 'critical' : 'warning'; ?>">
                <h4><?php echo $stats['coverage_percent'] < 50 ? '🔴' : '🟡'; ?> Competenze Senza Copertura Quiz</h4>
                <p><strong><?php echo count($competencies_missing); ?></strong> competenze non hanno domande associate.</p>
                <p>Copertura attuale: <strong><?php echo $stats['coverage_percent']; ?>%</strong></p>
                <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=missing" class="btn btn-warning">📝 Vedi Lista</a>
            </div>
            <?php endif; ?>
            
            <?php if ($courseid && count($orphan_questions) > 0): ?>
            <div class="problem-card warning">
                <h4>🟡 Domande Orfane nel Corso</h4>
                <p><strong><?php echo count($orphan_questions); ?></strong> domande nei quiz non sono collegate a nessuna competenza.</p>
                <p>Queste domande non contribuiscono alla valutazione delle competenze.</p>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; font-weight: 600;">Vedi lista domande</summary>
                    <div class="competency-list" style="margin-top: 10px;">
                        <?php foreach ($orphan_questions as $q): ?>
                        <div class="competency-item">
                            <span><?php echo htmlspecialchars($q->question_name); ?></span>
                            <span class="badge badge-info"><?php echo htmlspecialchars($q->quiz_name); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['duplicates'] == 0 && count($competencies_missing) == 0 && count($orphan_questions) == 0): ?>
            <div class="alert alert-success">
                ✅ Nessun problema critico rilevato per questo settore/corso!
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <?php elseif ($tab == 'missing'): ?>
    <!-- TAB: COMPETENZE MANCANTI -->
    
    <div class="card">
        <div class="card-header">
            <span>📝 Competenze Senza Domande (<?php echo count($competencies_missing); ?>)</span>
            <a href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=missing&export=excel" class="btn btn-success" style="padding: 6px 15px; font-size: 13px;">📥 Esporta Excel</a>
        </div>
        <div class="card-body">
            <?php if (count($competencies_missing) > 0): ?>
            
            <?php
            // Raggruppa per profilo
            $by_profile = [];
            foreach ($competencies_missing as $comp) {
                $parts = explode('_', $comp->idnumber);
                $profile = isset($parts[1]) ? $parts[1] : 'ALTRO';
                if (!isset($by_profile[$profile])) {
                    $by_profile[$profile] = [];
                }
                $by_profile[$profile][] = $comp;
            }
            ksort($by_profile);
            ?>
            
            <?php foreach ($by_profile as $profile => $comps): ?>
            <details style="margin-bottom: 15px;" open>
                <summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                    <?php echo $profile; ?> (<?php echo count($comps); ?> mancanti)
                </summary>
                <div class="competency-list" style="margin-top: 10px; max-height: none;">
                    <?php foreach ($comps as $comp): ?>
                    <div class="competency-item">
                        <div>
                            <code><?php echo htmlspecialchars($comp->idnumber); ?></code>
                            <span style="margin-left: 10px; color: #666;"><?php echo htmlspecialchars($comp->shortname ?: $comp->idnumber); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endforeach; ?>
            
            <?php else: ?>
            <div class="alert alert-success">
                ✅ Tutte le competenze hanno almeno una domanda associata!
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($tab == 'fix'): ?>
    <!-- TAB: FIX AUTOMATICI -->
    
    <div class="card">
        <div class="card-header">🔧 Fix Automatici Disponibili</div>
        <div class="card-body">
            
            <?php if ($stats['duplicates'] > 0): ?>
            <div class="problem-card critical" style="background: white; border: 1px solid #eee;">
                <h4>🔴 Rimuovi Duplicati Competenze</h4>
                <p>Ci sono <strong><?php echo $stats['duplicates']; ?></strong> record duplicati nel framework <?php echo $sector; ?>.</p>
                <p style="font-size: 14px; color: #666;">
                    Questa operazione:<br>
                    • Mantiene il record collegato a domande (se esiste)<br>
                    • Trasferisce eventuali collegamenti al record mantenuto<br>
                    • Elimina i duplicati in eccesso
                </p>
                <form method="post" action="" style="margin-top: 15px;" onsubmit="return confirm('Sei sicuro di voler rimuovere i duplicati? Questa operazione non può essere annullata.');">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="action" value="fixduplicates">
                    <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                    <input type="hidden" name="sector" value="<?php echo $sector; ?>">
                    <input type="hidden" name="tab" value="fix">
                    <button type="submit" class="btn btn-danger">
                        ⚠️ Esegui Fix Duplicati
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="alert alert-success">
                ✅ Nessun duplicato trovato per il settore <?php echo $sector; ?>
            </div>
            <?php endif; ?>
            
            <div class="problem-card info" style="background: white; border: 1px solid #eee; margin-top: 20px;">
                <h4>📊 Verifica Integrità</h4>
                <p>Controlla la coerenza tra framework, domande e quiz.</p>
                <button class="btn btn-primary" onclick="alert('Funzionalità in sviluppo');">
                    🔍 Esegui Verifica
                </button>
            </div>
            
        </div>
    </div>
    
    <?php endif; ?>
    
    <?php else: ?>
    <!-- Nessun settore selezionato -->
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 60px;">
            <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
            <h3>Seleziona un Corso o Settore</h3>
            <p style="color: #666;">Usa i selettori sopra per analizzare la copertura delle competenze</p>
        </div>
    </div>
    
    <!-- Riepilogo tutti i settori -->
    <div class="card">
        <div class="card-header">📋 Riepilogo Settori</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Settore</th>
                        <th>Competenze Uniche</th>
                        <th>Record Totali</th>
                        <th>Duplicati</th>
                        <th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectors as $s): 
                        $dups = $s->total_records - $s->unique_competencies;
                    ?>
                    <tr>
                        <td><strong><?php echo $s->sector; ?></strong></td>
                        <td><?php echo $s->unique_competencies; ?></td>
                        <td><?php echo $s->total_records; ?></td>
                        <td>
                            <?php if ($dups > 0): ?>
                            <span class="badge badge-warning"><?php echo $dups; ?> ⚠️</span>
                            <?php else: ?>
                            <span class="badge badge-success">0 ✅</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?sector=<?php echo $s->sector; ?>" class="btn btn-primary" style="padding: 5px 12px; font-size: 12px;">
                                📊 Analizza
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- Link rapidi -->
    <div class="card">
        <div class="card-header">🔗 Link Rapidi</div>
        <div class="card-body">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="../ftm_hub/index.php" class="btn btn-primary">🛠️ FTM Hub</a>
                <a href="simulate_student.php" class="btn btn-success">🤖 Simulatore</a>
                <a href="../ftm_testsuite/index.php" class="btn btn-warning">🧪 Test Suite</a>
                <?php if ($courseid): ?>
                <a href="reports.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">📊 Report Corso</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>

<?php
echo $OUTPUT->footer();
