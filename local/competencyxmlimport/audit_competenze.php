<?php
/**
 * AUDIT COMPETENZE - DASHBOARD INTEGRATA MULTI-SETTORE
 * 
 * Dashboard unificata per:
 * - Analisi copertura per settore
 * - Competenze mancanti
 * - Analisi e pulizia duplicati
 * - Fix codici errati
 * - Analisi pattern naming
 * 
 * VERSIONE CORRETTA: Supporta 8 aree AUTOMAZIONE (A-H)
 * 
 * @package local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'dashboard', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_ALPHANUMEXT);
$subaction = optional_param('subaction', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/audit_competenze.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Audit Competenze - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================
// FUNZIONI HELPER
// ============================================================

/**
 * Rileva automaticamente il settore dominante dalle domande del corso
 */
function detect_course_sector($contextid) {
    global $DB;
    
    $sql = "SELECT 
                SUBSTRING_INDEX(c.idnumber, '_', 1) as sector,
                COUNT(*) as cnt
            FROM {competency} c
            JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
            JOIN {question} q ON q.id = qbc.questionid
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qc.contextid = ?
            GROUP BY SUBSTRING_INDEX(c.idnumber, '_', 1)
            ORDER BY cnt DESC
            LIMIT 1";
    
    $result = $DB->get_record_sql($sql, [$contextid]);
    return $result ? $result->sector : '';
}

/**
 * Ottiene i settori disponibili nel framework
 */
function get_framework_sectors($frameworkid) {
    global $DB;
    
    $sql = "SELECT DISTINCT SUBSTRING_INDEX(idnumber, '_', 1) as sector,
                   COUNT(*) as comp_count
            FROM {competency}
            WHERE competencyframeworkid = ?
            AND idnumber LIKE '%_%_%'
            GROUP BY SUBSTRING_INDEX(idnumber, '_', 1)
            ORDER BY sector";
    
    return $DB->get_records_sql($sql, [$frameworkid]);
}

/**
 * Estrae il codice area per l'audit - VERSIONE CORRETTA
 * Gestisce i diversi formati di naming per settore
 * 
 * AUTOMAZIONE: AUTOMAZIONE_OA_A1 → AUTOMAZIONE_A (raggruppa per lettera area A-H)
 * LOGISTICA: LOGISTICA_LO_A1 → LOGISTICA_A (raggruppa per lettera area A-H)
 * Altri settori: SETTORE_AREA_xx → SETTORE_AREA
 * 
 * @param string $idnumber Il codice completo della competenza
 * @return string Il codice area per il raggruppamento
 */
function extract_area_for_audit($idnumber) {
    $parts = explode('_', $idnumber);
    
    if (count($parts) < 2) {
        return 'ALTRO';
    }
    
    $sector = $parts[0];
    
    // AUTOMAZIONE: AUTOMAZIONE_OA_A1 → AUTOMAZIONE_A
    // Raggruppa per lettera area (A-H), non per profilo (OA/MA)
    if ($sector === 'AUTOMAZIONE' && count($parts) >= 3) {
        $areaLetter = substr($parts[2], 0, 1); // Estrae A, B, C, D, E, F, G, H
        return 'AUTOMAZIONE_' . $areaLetter;
    }
    
    // LOGISTICA: LOGISTICA_LO_A1 → LOGISTICA_A
    if ($sector === 'LOGISTICA' && count($parts) >= 3 && $parts[1] === 'LO') {
        $areaLetter = substr($parts[2], 0, 1);
        return 'LOGISTICA_' . $areaLetter;
    }
    
    // ELETTRICITÀ: ELETTRICITÀ_PE_A1 → ELETTRICITÀ_PE
    // METALCOSTRUZIONE: METALCOSTRUZIONE_MC_A1 → METALCOSTRUZIONE_MC
    // Per questi settori, il secondo elemento è il profilo che va bene come area
    if (in_array($sector, ['ELETTRICITÀ', 'METALCOSTRUZIONE']) && count($parts) >= 2) {
        return $sector . '_' . $parts[1];
    }
    
    // Altri settori (MECCANICA, AUTOMOBILE, CHIMFARM): SETTORE_AREA_xx → SETTORE_AREA
    if (count($parts) >= 2) {
        return $sector . '_' . $parts[1];
    }
    
    return 'ALTRO';
}

/**
 * Mappa nomi aree per visualizzazione - AGGIORNATA con AUTOMAZIONE
 */
function get_area_display_name($area_code) {
    $area_names = [
        // AUTOMAZIONE - 8 Aree
        'AUTOMAZIONE_A' => 'A. Pianificazione & Documentazione',
        'AUTOMAZIONE_B' => 'B. Montaggio meccanico & Elettromeccanico',
        'AUTOMAZIONE_C' => 'C. Cablaggio elettrico & Quadri',
        'AUTOMAZIONE_D' => 'D. Automazione & PLC',
        'AUTOMAZIONE_E' => 'E. Strumentazione & Misure',
        'AUTOMAZIONE_F' => 'F. Reti & Comunicazione industriale',
        'AUTOMAZIONE_G' => 'G. Qualità & Sicurezza',
        'AUTOMAZIONE_H' => 'H. Manutenzione & Service',
        
        // LOGISTICA - 8 Aree
        'LOGISTICA_A' => 'A. Organizzazione mandati logistici',
        'LOGISTICA_B' => 'B. Qualità ed efficienza processi',
        'LOGISTICA_C' => 'C. Ricezione, controllo, stoccaggio',
        'LOGISTICA_D' => 'D. Commissionamento e spedizione',
        'LOGISTICA_E' => 'E. Accettazione e consulenza',
        'LOGISTICA_F' => 'F. Recapito e servizi logistici',
        'LOGISTICA_G' => 'G. Operazioni di magazzino',
        'LOGISTICA_H' => 'H. Commissionamento e carico',
        
        // ELETTRICITÀ - Profili
        'ELETTRICITÀ_PE' => 'PE - Pianificazione & Progettazione',
        'ELETTRICITÀ_IE' => 'IE - Installazione impianti BT',
        'ELETTRICITÀ_EM' => 'EM - Montaggio & Cablaggio quadri',
        'ELETTRICITÀ_ER' => 'ER - Reti di distribuzione MT/BT',
        
        // METALCOSTRUZIONE - Profili
        'METALCOSTRUZIONE_MC' => 'MC - Metalcostruttore',
        'METALCOSTRUZIONE_DF' => 'DF - Disegnatore Fabbricante',
    ];
    
    return isset($area_names[$area_code]) ? $area_names[$area_code] : $area_code;
}

/**
 * Analizza la copertura per un settore - VERSIONE CORRETTA
 */
function analyze_sector_coverage($contextid, $frameworkid, $sector) {
    global $DB;
    
    // Tutte le competenze del settore
    $all_sql = "SELECT id, idnumber, shortname, description
                FROM {competency}
                WHERE competencyframeworkid = ?
                AND idnumber LIKE ?
                ORDER BY idnumber";
    $all = $DB->get_records_sql($all_sql, [$frameworkid, $sector . '_%']);
    
    // Competenze usate
    $used_sql = "SELECT DISTINCT c.idnumber
                 FROM {competency} c
                 JOIN {qbank_competenciesbyquestion} qbc ON qbc.competencyid = c.id
                 JOIN {question} q ON q.id = qbc.questionid
                 JOIN {question_versions} qv ON qv.questionid = q.id
                 JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qc.contextid = ?
                 AND c.competencyframeworkid = ?
                 AND c.idnumber LIKE ?";
    $used_records = $DB->get_records_sql($used_sql, [$contextid, $frameworkid, $sector . '_%']);
    $used = array_keys($used_records);
    
    // Raggruppa per area - USA LA NUOVA FUNZIONE
    $by_area = [];
    $missing = [];
    
    foreach ($all as $c) {
        // USA extract_area_for_audit() invece della regex fissa
        $area = extract_area_for_audit($c->idnumber);
        
        if (!isset($by_area[$area])) {
            $by_area[$area] = ['total' => 0, 'covered' => 0, 'missing' => []];
        }
        
        $by_area[$area]['total']++;
        
        if (in_array($c->idnumber, $used)) {
            $by_area[$area]['covered']++;
        } else {
            $by_area[$area]['missing'][] = $c;
            $missing[] = $c;
        }
    }
    
    ksort($by_area);
    
    return [
        'total' => count($all),
        'covered' => count($used),
        'missing' => $missing,
        'by_area' => $by_area
    ];
}

/**
 * Analizza i pattern di naming delle domande
 */
function analyze_naming_patterns($contextid) {
    global $DB;
    
    $sql = "SELECT q.name
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qc.contextid = ?";
    
    $questions = $DB->get_records_sql($sql, [$contextid]);
    
    $patterns = [];
    foreach ($questions as $q) {
        // Estrai prefisso (es. CHIM_BASE, AUTO_APPR01, MECC_ADV)
        if (preg_match('/^([A-Z]+_[A-Z0-9]+)_Q\d+/', $q->name, $m)) {
            $prefix = $m[1];
            if (!isset($patterns[$prefix])) {
                $patterns[$prefix] = 0;
            }
            $patterns[$prefix]++;
        }
    }
    
    arsort($patterns);
    return $patterns;
}

/**
 * Trova domande duplicate
 */
function find_duplicates($contextid) {
    global $DB;
    
    $sql = "SELECT q.id, q.name, q.qtype, qbe.id as entryid,
                   qbc.competencyid, c.idnumber as comp_code
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            LEFT JOIN {competency} c ON c.id = qbc.competencyid
            WHERE qc.contextid = ?
            ORDER BY q.name";
    
    $all_questions = $DB->get_records_sql($sql, [$contextid]);
    
    // Raggruppa per prefisso
    $groups = [];
    foreach ($all_questions as $q) {
        if (preg_match('/^([A-Z_]+_Q\d+)/', $q->name, $m)) {
            $prefix = $m[1];
        } else {
            $prefix = $q->name;
        }
        
        if (!isset($groups[$prefix])) {
            $groups[$prefix] = [];
        }
        $groups[$prefix][] = $q;
    }
    
    // Filtra solo gruppi con duplicati
    $duplicates = [];
    $to_delete = [];
    
    foreach ($groups as $prefix => $questions) {
        if (count($questions) > 1) {
            $group_info = [
                'prefix' => $prefix,
                'count' => count($questions),
                'questions' => [],
                'has_good' => false
            ];
            
            foreach ($questions as $q) {
                $is_good = !empty($q->comp_code) && preg_match('/^[A-Z]+_[A-Z0-9]+_\d+$/', $q->comp_code);
                $is_legacy = preg_match('/CHIMICA_|MECCANICA_|AUTO_OLD/', $q->name);
                
                $q_info = [
                    'id' => $q->id,
                    'name' => $q->name,
                    'comp_code' => $q->comp_code ?: '-',
                    'is_good' => $is_good,
                    'is_legacy' => $is_legacy,
                    'delete_candidate' => !$is_good || $is_legacy
                ];
                
                $group_info['questions'][] = $q_info;
                
                if ($is_good && !$is_legacy) {
                    $group_info['has_good'] = true;
                }
            }
            
            // Se ha almeno una buona, proponi di eliminare le cattive
            if ($group_info['has_good']) {
                foreach ($group_info['questions'] as $q_info) {
                    if ($q_info['delete_candidate']) {
                        $to_delete[] = $q_info;
                    }
                }
            }
            
            $duplicates[$prefix] = $group_info;
        }
    }
    
    return [
        'groups' => $duplicates,
        'to_delete' => $to_delete,
        'total_questions' => count($all_questions)
    ];
}

/**
 * Trova codici errati nelle domande
 */
function find_wrong_codes($contextid, $frameworkid, $sector = '') {
    global $DB;
    
    // Prendi tutti i codici validi dal framework
    $valid_sql = "SELECT idnumber FROM {competency} WHERE competencyframeworkid = ?";
    if ($sector) {
        $valid_sql .= " AND idnumber LIKE ?";
        $valid_codes = $DB->get_fieldset_sql($valid_sql, [$frameworkid, $sector . '_%']);
    } else {
        $valid_codes = $DB->get_fieldset_sql($valid_sql, [$frameworkid]);
    }
    
    // Prendi tutte le domande
    $sql = "SELECT q.id, q.name
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qc.contextid = ?
            ORDER BY q.name";
    
    $questions = $DB->get_records_sql($sql, [$contextid]);
    
    $wrong = [];
    foreach ($questions as $q) {
        // Cerca codici nel nome (pattern: SETTORE_AREA_NUM)
        if (preg_match_all('/([A-Z]+_[A-Z0-9]+_\d+)/', $q->name, $matches)) {
            foreach ($matches[1] as $code) {
                // Salta se è un pattern tipo CHIMFARM_BASE_Q01 (non è un codice competenza)
                if (preg_match('/_Q\d+$/', $code)) continue;
                
                if (!in_array($code, $valid_codes)) {
                    // Prova a suggerire correzione
                    $suggested = suggest_correction($code, $valid_codes);
                    $wrong[] = [
                        'question_id' => $q->id,
                        'question_name' => $q->name,
                        'wrong_code' => $code,
                        'suggested' => $suggested
                    ];
                }
            }
        }
    }
    
    return $wrong;
}

/**
 * Suggerisce correzione per un codice errato
 */
function suggest_correction($wrong_code, $valid_codes) {
    // Estrai parti
    if (!preg_match('/^([A-Z]+)_([A-Z0-9]+)_(\d+)$/', $wrong_code, $m)) {
        return null;
    }
    
    $sector = $m[1];
    $area = $m[2];
    $num = $m[3];
    
    // Se il numero ha 3 cifre (es. 010), prova con 2 cifre (01)
    if (strlen($num) == 3 && $num[0] == '0') {
        $try_code = $sector . '_' . $area . '_' . substr($num, 1);
        if (in_array($try_code, $valid_codes)) {
            return $try_code;
        }
    }
    
    // Prova a cercare codici simili
    $base = $sector . '_' . $area . '_';
    foreach ($valid_codes as $vc) {
        if (strpos($vc, $base) === 0) {
            // Trovato almeno uno valido in quest'area
            return $vc; // Ritorna il primo valido come suggerimento
        }
    }
    
    return null;
}

// ============================================================
// CARICAMENTO DATI
// ============================================================

// Lista framework disponibili
$frameworks = $DB->get_records('competency_framework', null, 'shortname ASC');

// Se non specificato, usa il primo framework
if (!$frameworkid && !empty($frameworks)) {
    $first = reset($frameworks);
    $frameworkid = $first->id;
}

// Settori disponibili
$sectors = [];
if ($frameworkid) {
    $sectors = get_framework_sectors($frameworkid);
}

// Se non specificato, rileva settore dal corso
if (!$sector && empty($sector)) {
    $detected = detect_course_sector($context->id);
    if ($detected) {
        $sector = $detected;
    }
}

// URL base per i link
$base_url = new moodle_url('/local/competencyxmlimport/audit_competenze.php', [
    'courseid' => $courseid,
    'frameworkid' => $frameworkid,
    'sector' => $sector
]);

// ============================================================
// OUTPUT HTML
// ============================================================

echo $OUTPUT->header();
?>

<style>
/* Stili CSS - mantieni quelli esistenti */
.audit-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.tabs { display: flex; gap: 5px; margin-bottom: 20px; flex-wrap: wrap; }
.tab { padding: 12px 24px; background: #e9ecef; border-radius: 8px 8px 0 0; cursor: pointer; text-decoration: none; color: #333; font-weight: 500; }
.tab:hover { background: #dee2e6; text-decoration: none; }
.tab.active { background: #0d6efd; color: white; }
.panel { background: white; padding: 25px; border-radius: 0 8px 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { padding: 20px; border-radius: 12px; text-align: center; color: white; }
.stat-card .number { font-size: 32px; font-weight: bold; }
.stat-card .label { font-size: 13px; opacity: 0.9; margin-top: 5px; }
.stat-card.blue { background: linear-gradient(135deg, #0d6efd, #0dcaf0); }
.stat-card.green { background: linear-gradient(135deg, #198754, #20c997); }
.stat-card.red { background: linear-gradient(135deg, #dc3545, #fd7e14); }
.stat-card.orange { background: linear-gradient(135deg, #fd7e14, #ffc107); }
.filter-row { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
.filter-row label { font-weight: 500; color: #666; }
.filter-row select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; min-width: 200px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; font-weight: 600; color: #333; }
tr:hover { background: #f8f9fa; }
.area-card { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
.area-header { display: flex; justify-content: space-between; align-items: center; }
.area-name { font-weight: 600; color: #333; }
.area-stats { display: flex; gap: 15px; }
.progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; flex: 1; min-width: 100px; }
.progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.progress-fill.green { background: #198754; }
.progress-fill.orange { background: #fd7e14; }
.progress-fill.red { background: #dc3545; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.badge-success { background: #d1e7dd; color: #0f5132; }
.badge-warning { background: #fff3cd; color: #664d03; }
.badge-danger { background: #f8d7da; color: #842029; }
.code { font-family: monospace; background: #f1f1f1; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
.code-good { background: #d1e7dd; color: #0f5132; }
.code-bad { background: #f8d7da; color: #842029; }
.code-new { background: #cfe2ff; color: #084298; }
.code-none { background: #e9ecef; color: #6c757d; }
.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; }
.alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
.alert-warning { background: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
.alert-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
.alert-info { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
.btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: #0d6efd; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn:hover { opacity: 0.9; text-decoration: none; }
.scrollable { max-height: 500px; overflow-y: auto; }
.group-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 15px; overflow: hidden; }
.group-header { background: #f8f9fa; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
.group-prefix { font-family: monospace; font-weight: 600; }
.group-count { color: #666; font-size: 13px; }
.row-good { background: #d1e7dd; }
.row-delete { background: #f8d7da; }
.badge-keep { background: #198754; color: white; }
.badge-delete { background: #dc3545; color: white; }
</style>

<div class="audit-container">
    <h2>🔍 Audit Competenze</h2>
    <p style="color:#666;"><?php echo $course->shortname; ?> - Dashboard di analisi e correzione</p>

    <!-- TABS -->
    <div class="tabs">
        <a href="<?php echo $base_url; ?>&action=dashboard" class="tab <?php echo $action == 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
        <a href="<?php echo $base_url; ?>&action=coverage" class="tab <?php echo $action == 'coverage' ? 'active' : ''; ?>">📈 Copertura</a>
        <a href="<?php echo $base_url; ?>&action=missing" class="tab <?php echo $action == 'missing' ? 'active' : ''; ?>">🎯 Mancanti</a>
        <a href="<?php echo $base_url; ?>&action=duplicates" class="tab <?php echo $action == 'duplicates' ? 'active' : ''; ?>">🔍 Duplicati</a>
        <a href="<?php echo $base_url; ?>&action=fixcodes" class="tab <?php echo $action == 'fixcodes' ? 'active' : ''; ?>">🔧 Fix Codici</a>
        <a href="<?php echo $base_url; ?>&action=naming" class="tab <?php echo $action == 'naming' ? 'active' : ''; ?>">📋 Naming</a>
    </div>

    <!-- FILTRI -->
    <div class="filter-row">
        <label>Framework:</label>
        <select onchange="location.href='<?php echo new moodle_url('/local/competencyxmlimport/audit_competenze.php', ['courseid' => $courseid, 'action' => $action]); ?>&frameworkid='+this.value">
            <?php foreach ($frameworks as $fw): ?>
            <option value="<?php echo $fw->id; ?>" <?php echo $fw->id == $frameworkid ? 'selected' : ''; ?>><?php echo $fw->shortname; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>Settore:</label>
        <select onchange="location.href='<?php echo $base_url; ?>&action=<?php echo $action; ?>&sector='+this.value">
            <option value="">-- Seleziona Settore --</option>
            <?php foreach ($sectors as $s): ?>
            <option value="<?php echo $s->sector; ?>" <?php echo $s->sector == $sector ? 'selected' : ''; ?>><?php echo $s->sector; ?> (<?php echo $s->comp_count; ?> comp.)</option>
            <?php endforeach; ?>
        </select>
    </div>

<?php
// ============================================================
// TAB: DASHBOARD
// ============================================================
if ($action == 'dashboard'):
    if ($sector):
        $coverage = analyze_sector_coverage($context->id, $frameworkid, $sector);
        $pct = $coverage['total'] > 0 ? round(($coverage['covered'] / $coverage['total']) * 100) : 0;
?>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo $coverage['total']; ?></div>
            <div class="label"><?php echo $sector; ?></div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $coverage['covered']; ?></div>
            <div class="label">Coperte</div>
        </div>
        <div class="stat-card <?php echo count($coverage['missing']) > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo count($coverage['missing']); ?></div>
            <div class="label">Mancanti</div>
        </div>
        <div class="stat-card <?php echo $pct >= 80 ? 'green' : ($pct >= 50 ? 'orange' : 'red'); ?>">
            <div class="number"><?php echo $pct; ?>%</div>
            <div class="label">Copertura</div>
        </div>
    </div>

    <div class="panel">
        <h3>📊 Dettaglio per Area (<?php echo $sector; ?>)</h3>
        <?php foreach ($coverage['by_area'] as $area => $data): 
            $area_pct = $data['total'] > 0 ? round(($data['covered'] / $data['total']) * 100) : 0;
            $bar_class = $area_pct == 100 ? 'green' : ($area_pct >= 50 ? 'orange' : 'red');
            $area_display = get_area_display_name($area);
        ?>
        <div class="area-card">
            <div class="area-header">
                <span class="area-name"><?php echo $area_display; ?></span>
                <div class="area-stats">
                    <span class="badge <?php echo $area_pct == 100 ? 'badge-success' : 'badge-warning'; ?>"><?php echo $area_pct; ?>%</span>
                    <span><?php echo $data['covered']; ?>/<?php echo $data['total']; ?></span>
                    <?php if ($area_pct == 100): ?>
                    <span class="badge badge-success">✅</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="progress-bar" style="margin-top:10px;">
                <div class="progress-fill <?php echo $bar_class; ?>" style="width:<?php echo $area_pct; ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">Seleziona un settore per visualizzare le statistiche.</div>
<?php endif;

// ============================================================
// TAB: COVERAGE
// ============================================================
elseif ($action == 'coverage'):
    if ($sector):
        $coverage = analyze_sector_coverage($context->id, $frameworkid, $sector);
?>
    <div class="panel">
        <h3>📈 Copertura Dettagliata - <?php echo $sector; ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Totale</th>
                    <th>Coperte</th>
                    <th>Mancanti</th>
                    <th>%</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($coverage['by_area'] as $area => $data): 
                $area_pct = $data['total'] > 0 ? round(($data['covered'] / $data['total']) * 100) : 0;
                $area_display = get_area_display_name($area);
            ?>
                <tr>
                    <td><strong><?php echo $area_display; ?></strong></td>
                    <td><?php echo $data['total']; ?></td>
                    <td><?php echo $data['covered']; ?></td>
                    <td><?php echo $data['total'] - $data['covered']; ?></td>
                    <td><?php echo $area_pct; ?>%</td>
                    <td>
                        <?php if ($area_pct == 100): ?>
                        <span class="badge badge-success">✅ Completa</span>
                        <?php elseif ($area_pct >= 50): ?>
                        <span class="badge badge-warning">⚠️ Parziale</span>
                        <?php else: ?>
                        <span class="badge badge-danger">❌ Carente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Seleziona un settore per visualizzare la copertura.</div>
<?php endif;

// ============================================================
// TAB: MISSING
// ============================================================
elseif ($action == 'missing'):
    if ($sector):
        $coverage = analyze_sector_coverage($context->id, $frameworkid, $sector);
?>
    <div class="panel">
        <h3>🎯 Competenze Mancanti - <?php echo $sector; ?></h3>
        
        <?php if (empty($coverage['missing'])): ?>
        <div class="alert alert-success">✅ Tutte le competenze sono coperte!</div>
        <?php else: ?>
        <p style="color:#666; margin-bottom:15px;">Totale mancanti: <strong><?php echo count($coverage['missing']); ?></strong></p>
        
        <div class="scrollable">
            <table>
                <thead>
                    <tr><th>Codice</th><th>Area</th><th>Descrizione</th></tr>
                </thead>
                <tbody>
                <?php foreach ($coverage['missing'] as $c): 
                    $area = extract_area_for_audit($c->idnumber);
                    $area_display = get_area_display_name($area);
                ?>
                <tr>
                    <td><span class="code"><?php echo $c->idnumber; ?></span></td>
                    <td><?php echo $area_display; ?></td>
                    <td><?php echo $c->shortname ?: strip_tags($c->description); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">Seleziona un settore per visualizzare le competenze mancanti.</div>
<?php endif;

// ============================================================
// TAB: DUPLICATES
// ============================================================
elseif ($action == 'duplicates'):
    $dup_data = find_duplicates($context->id);
    
    // Gestione eliminazione
    if ($subaction == 'delete' && !empty($dup_data['to_delete'])):
        if ($confirm == 1):
            $deleted = 0;
            foreach ($dup_data['to_delete'] as $q) {
                try {
                    $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $q['id']]);
                    question_delete_question($q['id']);
                    $deleted++;
                } catch (Exception $e) {
                    // Log error
                }
            }
            echo '<div class="alert alert-success">✅ Eliminate ' . $deleted . ' domande duplicate!</div>';
            // Ricarica dati
            $dup_data = find_duplicates($context->id);
        else:
?>
    <div class="alert alert-danger">
        <strong>⚠️ ATTENZIONE!</strong> Stai per eliminare <?php echo count($dup_data['to_delete']); ?> domande. Questa azione è IRREVERSIBILE.
    </div>
    <div class="panel">
        <h3>Domande da eliminare</h3>
        <table>
            <thead><tr><th>#</th><th>Nome</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($dup_data['to_delete'] as $i => $q): ?>
            <tr class="row-delete">
                <td><?php echo $i+1; ?></td>
                <td><?php echo htmlspecialchars($q['name']); ?></td>
                <td><?php echo $q['is_legacy'] ? 'Legacy' : 'Senza competenza'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:20px;">
            <a href="<?php echo $base_url; ?>&action=duplicates&subaction=delete&confirm=1" class="btn btn-danger" onclick="return confirm('CONFERMA FINALE: Eliminare definitivamente?');">🗑️ CONFERMA ELIMINAZIONE</a>
            <a href="<?php echo $base_url; ?>&action=duplicates" class="btn btn-secondary">❌ Annulla</a>
        </div>
    </div>
<?php
        endif;
    else:
?>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo $dup_data['total_questions']; ?></div>
            <div class="label">Domande Totali</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo count($dup_data['groups']); ?></div>
            <div class="label">Gruppi Duplicati</div>
        </div>
        <div class="stat-card <?php echo count($dup_data['to_delete']) > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo count($dup_data['to_delete']); ?></div>
            <div class="label">Da Eliminare</div>
        </div>
    </div>

    <?php if (count($dup_data['to_delete']) > 0): ?>
    <div class="alert alert-warning">
        <strong>🗑️ Azione consigliata:</strong> <?php echo count($dup_data['to_delete']); ?> domande duplicate/legacy possono essere eliminate.
        <br><br>
        <a href="<?php echo $base_url; ?>&action=duplicates&subaction=delete" class="btn btn-danger">🗑️ Elimina Duplicati</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($dup_data['groups'])): ?>
    <div class="panel">
        <h3>📋 Gruppi con Duplicati</h3>
        <div class="scrollable">
        <?php 
        $shown = 0;
        foreach ($dup_data['groups'] as $prefix => $group): 
            if ($shown++ >= 20) {
                echo '<p style="color:#666; padding:10px;">... e altri ' . (count($dup_data['groups']) - 20) . ' gruppi</p>';
                break;
            }
        ?>
        <div class="group-card">
            <div class="group-header">
                <span class="group-prefix"><?php echo htmlspecialchars($prefix); ?></span>
                <span class="group-count"><?php echo $group['count']; ?> versioni</span>
            </div>
            <table>
                <thead><tr><th>Nome</th><th>Competenza</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ($group['questions'] as $q): 
                    $row_class = ($q['is_good'] && !$q['is_legacy']) ? 'row-good' : 'row-delete';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo htmlspecialchars($q['name']); ?></td>
                    <td><span class="code <?php echo $q['is_good'] ? 'code-good' : 'code-none'; ?>"><?php echo $q['comp_code']; ?></span></td>
                    <td>
                        <?php if ($q['is_good'] && !$q['is_legacy']): ?>
                        <span class="badge badge-keep">✅ TENERE</span>
                        <?php else: ?>
                        <span class="badge badge-delete">🗑️ ELIMINARE</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success">✅ Nessun duplicato trovato!</div>
    <?php endif; ?>
<?php 
    endif;

// ============================================================
// TAB: FIX CODICI
// ============================================================
elseif ($action == 'fixcodes'):
    $wrong_codes = find_wrong_codes($context->id, $frameworkid, $sector);
?>
    <div class="filter-row">
        <label>Settore (filtro):</label>
        <select onchange="location.href='<?php echo $base_url; ?>&action=fixcodes&frameworkid=<?php echo $frameworkid; ?>&sector='+this.value">
            <option value="">Tutti i settori</option>
            <?php foreach ($sectors as $s): ?>
            <option value="<?php echo $s->sector; ?>" <?php echo $s->sector == $sector ? 'selected' : ''; ?>><?php echo $s->sector; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="stats-grid">
        <div class="stat-card <?php echo count($wrong_codes) > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo count($wrong_codes); ?></div>
            <div class="label">Codici Errati</div>
        </div>
    </div>

    <?php if (!empty($wrong_codes)): ?>
    <div class="panel">
        <h3>⚠️ Codici Non Validi Trovati</h3>
        <p style="color:#666; margin-bottom:15px;">Questi codici nel nome delle domande non esistono nel framework:</p>
        <table>
            <thead><tr><th>Nome Domanda</th><th>Codice Errato</th><th>Suggerimento</th></tr></thead>
            <tbody>
            <?php foreach ($wrong_codes as $w): ?>
            <tr>
                <td><?php echo htmlspecialchars($w['question_name']); ?></td>
                <td><span class="code code-bad"><?php echo $w['wrong_code']; ?></span></td>
                <td>
                    <?php if ($w['suggested']): ?>
                    <span class="code code-new"><?php echo $w['suggested']; ?></span>
                    <?php else: ?>
                    <span style="color:#999;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-success">✅ Nessun codice errato trovato<?php echo $sector ? " per {$sector}" : ''; ?>!</div>
    <?php endif; ?>

<?php
// ============================================================
// TAB: NAMING
// ============================================================
elseif ($action == 'naming'):
    $patterns = analyze_naming_patterns($context->id);
?>
    <div class="panel">
        <h3>📋 Pattern di Naming Rilevati</h3>
        <p style="color:#666; margin-bottom:15px;">Analisi dei prefissi usati nei nomi delle domande:</p>
        
        <?php if (!empty($patterns)): ?>
        <table>
            <thead><tr><th>Pattern</th><th>Domande</th><th>Esempio Nome</th></tr></thead>
            <tbody>
            <?php foreach ($patterns as $pattern => $count): ?>
            <tr>
                <td><span class="code code-good"><?php echo $pattern; ?>_Qxx</span></td>
                <td><strong><?php echo $count; ?></strong></td>
                <td style="color:#666;"><?php echo $pattern; ?>_Q01 - SETTORE_AREA_01</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-info">Nessun pattern standard rilevato.</div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>📖 Convenzione Naming Consigliata</h3>
        <table>
            <tr><th>Elemento</th><th>Formato</th><th>Esempio</th></tr>
            <tr><td>Nome domanda</td><td><code>PREFISSO_TIPO_Qnn - SETTORE_AREA_nn</code></td><td>CHIM_BASE_Q01 - CHIMFARM_1C_01</td></tr>
            <tr><td>Prefisso settore</td><td>3-4 lettere</td><td>CHIM, AUTO, MECC, METAL, LOGI</td></tr>
            <tr><td>Tipo quiz</td><td>BASE, APPR01-03, ADV</td><td>BASE = base, APPR = apprendista, ADV = avanzato</td></tr>
            <tr><td>Codice competenza</td><td><code>SETTORE_AREA_nn</code></td><td>CHIMFARM_1C_01, AUTOMOBILE_2M_05</td></tr>
        </table>
    </div>

<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
