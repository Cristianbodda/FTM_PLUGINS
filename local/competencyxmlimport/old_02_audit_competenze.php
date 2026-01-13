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
 * Analizza la copertura per un settore
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
    
    // Raggruppa per area
    $by_area = [];
    $missing = [];
    
    foreach ($all as $c) {
        if (preg_match('/^([A-Z]+_[A-Z0-9]+)_/', $c->idnumber, $m)) {
            $area = $m[1];
        } else {
            $area = 'ALTRO';
        }
        
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
$frameworks = $DB->get_records('competency_framework', [], 'shortname', 'id, shortname, idnumber');

// Se non specificato, usa il primo framework
if (!$frameworkid && !empty($frameworks)) {
    $frameworkid = array_key_first($frameworks);
}

// Lista settori
$sectors = $frameworkid ? get_framework_sectors($frameworkid) : [];

// Auto-detect settore se non specificato
if (!$sector && $action != 'dashboard') {
    $sector = detect_course_sector($context->id);
}

// ============================================================
// CSS GLOBALE
// ============================================================

$css = '
<style>
.audit-page { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.audit-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 16px; margin-bottom: 25px; }
.audit-header h2 { margin: 0 0 8px 0; font-size: 28px; }
.audit-header p { margin: 0; opacity: 0.9; }

/* Tabs */
.tabs { display: flex; gap: 5px; margin-bottom: 25px; flex-wrap: wrap; }
.tab { padding: 12px 20px; background: white; border: 2px solid #e0e0e0; border-radius: 10px; text-decoration: none; color: #666; font-weight: 600; transition: all 0.2s; }
.tab:hover { border-color: #667eea; color: #667eea; }
.tab.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: transparent; }

/* Stats Grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-card { padding: 20px; border-radius: 12px; text-align: center; color: white; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 11px; margin-top: 5px; text-transform: uppercase; opacity: 0.9; }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.stat-card.orange { background: linear-gradient(135deg, #e67e22, #d35400); }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.stat-card.teal { background: linear-gradient(135deg, #1abc9c, #16a085); }

/* Panels */
.panel { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; color: #333; }

/* Forms */
.filter-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; }
.filter-row label { font-weight: 600; color: #555; }
.filter-row select { padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-width: 200px; }

/* Tables */
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
th { background: #f8f9fa; font-weight: 600; color: #555; }
tr:hover { background: #fafafa; }

/* Code badges */
.code { font-family: "SF Mono", Monaco, monospace; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
.code-good { background: #d4edda; color: #155724; }
.code-bad { background: #f8d7da; color: #721c24; }
.code-none { background: #e9ecef; color: #6c757d; }
.code-new { background: #cce5ff; color: #004085; }

/* Progress bars */
.progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.progress-fill.green { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.progress-fill.orange { background: linear-gradient(90deg, #e67e22, #f39c12); }
.progress-fill.red { background: linear-gradient(90deg, #e74c3c, #c0392b); }

/* Buttons */
.btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-info { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* Messages */
.alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-info { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }

/* Area cards */
.area-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 10px; padding: 15px; margin-bottom: 12px; }
.area-card.complete { border-left: 4px solid #27ae60; }
.area-card.incomplete { border-left: 4px solid #e74c3c; }
.area-header { display: flex; justify-content: space-between; align-items: center; }
.area-name { font-weight: 600; font-size: 15px; }
.area-stats { display: flex; gap: 15px; align-items: center; }
.area-pct { font-weight: 700; font-size: 16px; }
.area-pct.green { color: #27ae60; }
.area-pct.red { color: #e74c3c; }

/* Missing items */
.missing-item { display: flex; gap: 12px; padding: 10px; background: white; border: 1px solid #f5c6cb; border-radius: 6px; margin-top: 8px; }
.missing-code { white-space: nowrap; }
.missing-name { flex: 1; font-size: 12px; color: #555; }

/* Group cards for duplicates */
.group-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 12px; }
.group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.group-prefix { font-family: monospace; background: #e3f2fd; color: #1565c0; padding: 5px 10px; border-radius: 5px; font-weight: 600; }
.group-count { background: #ffebee; color: #c62828; padding: 3px 10px; border-radius: 12px; font-size: 12px; }

.row-good { background: #f1f8e9; }
.row-bad { background: #fff8e1; }
.row-delete { background: #ffebee; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; margin-left: 5px; }
.badge-keep { background: #c8e6c9; color: #2e7d32; }
.badge-delete { background: #ffcdd2; color: #c62828; }
.badge-legacy { background: #ffe0b2; color: #e65100; }

.scrollable { max-height: 500px; overflow-y: auto; }

/* Sector selector */
.sector-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.sector-btn { padding: 15px; border: 2px solid #e0e0e0; border-radius: 10px; text-decoration: none; color: #333; text-align: center; transition: all 0.2s; }
.sector-btn:hover { border-color: #667eea; background: #f8f9ff; }
.sector-btn.active { border-color: #667eea; background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.sector-btn .name { font-weight: 600; font-size: 14px; }
.sector-btn .count { font-size: 12px; opacity: 0.8; }
</style>';

echo $OUTPUT->header();
echo $css;

// ============================================================
// RENDER PAGINA
// ============================================================

$base_url = "audit_competenze.php?courseid={$courseid}";

?>
<div class="audit-page">
    <div class="audit-header">
        <h2>🔍 Audit Competenze</h2>
        <p><?php echo $course->fullname; ?> - Dashboard di analisi e correzione</p>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <a href="<?php echo $base_url; ?>&action=dashboard" class="tab <?php echo $action == 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
        <a href="<?php echo $base_url; ?>&action=coverage" class="tab <?php echo $action == 'coverage' ? 'active' : ''; ?>">📈 Copertura</a>
        <a href="<?php echo $base_url; ?>&action=missing" class="tab <?php echo $action == 'missing' ? 'active' : ''; ?>">🎯 Mancanti</a>
        <a href="<?php echo $base_url; ?>&action=duplicates" class="tab <?php echo $action == 'duplicates' ? 'active' : ''; ?>">🔍 Duplicati</a>
        <a href="<?php echo $base_url; ?>&action=fixcodes" class="tab <?php echo $action == 'fixcodes' ? 'active' : ''; ?>">🔧 Fix Codici</a>
        <a href="<?php echo $base_url; ?>&action=naming" class="tab <?php echo $action == 'naming' ? 'active' : ''; ?>">📋 Naming</a>
    </div>

<?php

// ============================================================
// TAB: DASHBOARD
// ============================================================
if ($action == 'dashboard'):
    // Statistiche generali
    $total_questions = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT q.id)
         FROM {question} q
         JOIN {question_versions} qv ON qv.questionid = q.id
         JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
         JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
         WHERE qc.contextid = ?", [$context->id]);
    
    $with_comp = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT q.id)
         FROM {question} q
         JOIN {question_versions} qv ON qv.questionid = q.id
         JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
         JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
         JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
         WHERE qc.contextid = ?", [$context->id]);
    
    $without_comp = $total_questions - $with_comp;
    $detected_sector = detect_course_sector($context->id);
    $dup_data = find_duplicates($context->id);
?>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="number"><?php echo $total_questions; ?></div>
            <div class="label">Domande Totali</div>
        </div>
        <div class="stat-card green">
            <div class="number"><?php echo $with_comp; ?></div>
            <div class="label">Con Competenza</div>
        </div>
        <div class="stat-card <?php echo $without_comp > 0 ? 'red' : 'green'; ?>">
            <div class="number"><?php echo $without_comp; ?></div>
            <div class="label">Senza Competenza</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo $detected_sector ?: '-'; ?></div>
            <div class="label">Settore Rilevato</div>
        </div>
        <div class="stat-card <?php echo count($dup_data['to_delete']) > 0 ? 'orange' : 'teal'; ?>">
            <div class="number"><?php echo count($dup_data['to_delete']); ?></div>
            <div class="label">Duplicati da Pulire</div>
        </div>
    </div>

    <div class="panel">
        <h3>🎯 Azioni Rapide</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo $base_url; ?>&action=coverage&sector=<?php echo $detected_sector; ?>" class="btn btn-info">📈 Analizza Copertura <?php echo $detected_sector; ?></a>
            <a href="<?php echo $base_url; ?>&action=missing&sector=<?php echo $detected_sector; ?>" class="btn btn-primary">🎯 Vedi Competenze Mancanti</a>
            <?php if (count($dup_data['to_delete']) > 0): ?>
            <a href="<?php echo $base_url; ?>&action=duplicates" class="btn btn-danger">🗑️ Pulisci <?php echo count($dup_data['to_delete']); ?> Duplicati</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Settori disponibili -->
    <div class="panel">
        <h3>📂 Settori nel Framework</h3>
        <div class="sector-grid">
            <?php foreach ($sectors as $s): ?>
            <a href="<?php echo $base_url; ?>&action=coverage&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $s->sector; ?>" class="sector-btn <?php echo $s->sector == $detected_sector ? 'active' : ''; ?>">
                <div class="name"><?php echo $s->sector; ?></div>
                <div class="count"><?php echo $s->comp_count; ?> competenze</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

<?php
// ============================================================
// TAB: COPERTURA
// ============================================================
elseif ($action == 'coverage'):
    $coverage = $sector ? analyze_sector_coverage($context->id, $frameworkid, $sector) : null;
?>
    <div class="filter-row">
        <label>Framework:</label>
        <select onchange="location.href='<?php echo $base_url; ?>&action=coverage&frameworkid='+this.value">
            <?php foreach ($frameworks as $f): ?>
            <option value="<?php echo $f->id; ?>" <?php echo $f->id == $frameworkid ? 'selected' : ''; ?>><?php echo $f->shortname; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>Settore:</label>
        <select onchange="location.href='<?php echo $base_url; ?>&action=coverage&frameworkid=<?php echo $frameworkid; ?>&sector='+this.value">
            <option value="">-- Seleziona Settore --</option>
            <?php foreach ($sectors as $s): ?>
            <option value="<?php echo $s->sector; ?>" <?php echo $s->sector == $sector ? 'selected' : ''; ?>><?php echo $s->sector; ?> (<?php echo $s->comp_count; ?> comp.)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($coverage): 
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
        <div class="stat-card <?php echo $pct == 100 ? 'teal' : 'purple'; ?>">
            <div class="number"><?php echo $pct; ?>%</div>
            <div class="label">Copertura</div>
        </div>
    </div>

    <div class="panel">
        <h3>📊 Dettaglio per Area (<?php echo $sector; ?>)</h3>
        <?php foreach ($coverage['by_area'] as $area => $data): 
            $area_pct = $data['total'] > 0 ? round(($data['covered'] / $data['total']) * 100) : 0;
            $is_complete = empty($data['missing']);
            $bar_class = $area_pct == 100 ? 'green' : ($area_pct >= 50 ? 'orange' : 'red');
        ?>
        <div class="area-card <?php echo $is_complete ? 'complete' : 'incomplete'; ?>">
            <div class="area-header">
                <span class="area-name"><?php echo $area; ?></span>
                <div class="area-stats">
                    <div class="progress-bar" style="width:150px;">
                        <div class="progress-fill <?php echo $bar_class; ?>" style="width:<?php echo $area_pct; ?>%;"></div>
                    </div>
                    <span class="area-pct <?php echo $is_complete ? 'green' : 'red'; ?>"><?php echo $area_pct; ?>%</span>
                    <span style="color:#666; font-size:13px;"><?php echo $data['covered']; ?>/<?php echo $data['total']; ?></span>
                    <?php if ($is_complete): ?>
                    <span style="color:#27ae60;">✅</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (count($coverage['missing']) > 0): ?>
        <div style="margin-top:20px;">
            <a href="<?php echo $base_url; ?>&action=missing&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>" class="btn btn-primary">
                🎯 Vedi <?php echo count($coverage['missing']); ?> Competenze Mancanti
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-info">Seleziona un settore per vedere la copertura.</div>
    <?php endif; ?>

<?php
// ============================================================
// TAB: MANCANTI
// ============================================================
elseif ($action == 'missing'):
    $coverage = $sector ? analyze_sector_coverage($context->id, $frameworkid, $sector) : null;
?>
    <div class="filter-row">
        <label>Settore:</label>
        <select onchange="location.href='<?php echo $base_url; ?>&action=missing&frameworkid=<?php echo $frameworkid; ?>&sector='+this.value">
            <option value="">-- Seleziona Settore --</option>
            <?php foreach ($sectors as $s): ?>
            <option value="<?php echo $s->sector; ?>" <?php echo $s->sector == $sector ? 'selected' : ''; ?>><?php echo $s->sector; ?> (<?php echo $s->comp_count; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($coverage && count($coverage['missing']) > 0): ?>
    <div class="alert alert-warning">
        <strong>🎯 Per raggiungere il 100%</strong> devi creare domande per <strong><?php echo count($coverage['missing']); ?></strong> competenze mancanti.
    </div>

    <div class="panel">
        <h3>📋 Competenze Mancanti per <?php echo $sector; ?></h3>
        <?php foreach ($coverage['by_area'] as $area => $data): 
            if (empty($data['missing'])) continue;
        ?>
        <div class="area-card incomplete">
            <div class="area-header">
                <span class="area-name"><?php echo $area; ?></span>
                <span style="color:#c62828;"><?php echo count($data['missing']); ?> mancanti</span>
            </div>
            <div style="margin-top:10px;">
                <?php foreach ($data['missing'] as $m): ?>
                <div class="missing-item">
                    <span class="code code-bad missing-code"><?php echo $m->idnumber; ?></span>
                    <span class="missing-name"><?php echo htmlspecialchars($m->shortname); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Export -->
        <div style="margin-top:20px; padding:15px; background:#f5f5f5; border-radius:8px;">
            <strong>📋 Lista codici (copia per creare domande):</strong>
            <pre style="background:white; padding:10px; border-radius:4px; margin-top:10px; font-size:11px; max-height:200px; overflow:auto;"><?php 
foreach ($coverage['missing'] as $m) {
    echo $m->idnumber . " - " . $m->shortname . "\n";
}
            ?></pre>
        </div>
    </div>
    <?php elseif ($coverage): ?>
    <div class="alert alert-success">✅ Tutte le competenze di <?php echo $sector; ?> sono coperte!</div>
    <?php else: ?>
    <div class="alert alert-info">Seleziona un settore per vedere le competenze mancanti.</div>
    <?php endif; ?>

<?php
// ============================================================
// TAB: DUPLICATI
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
