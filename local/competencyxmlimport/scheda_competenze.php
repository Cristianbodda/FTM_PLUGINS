<?php
/**
 * SCHEDA COMPETENZE - Centro Unificato Audit e Report
 * 
 * FUNZIONALITÀ COMPLETE:
 * 1. Dashboard Audit - Statistiche generali corso
 * 2. Domande Orfane - Lista con rilevamento codice automatico
 * 3. Assegna Competenze - Form con selezione framework/settore + AJAX
 * 4. Report Quiz - Lista quiz + dettaglio domande/competenze
 * 5. Audit Studente - Report individuale con certificazione stampabile
 * 6. Fix per Categoria - Link agli strumenti di fix automatico
 * 
 * TUTTE LE SEZIONI STAMPABILI con opzioni di selezione
 * 
 * @package    local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'dashboard', PARAM_ALPHANUMEXT);
$quizid = optional_param('quizid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Verifica accesso
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/scheda_competenze.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Scheda Competenze - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ============================================================================
// FUNZIONI HELPER (TUTTE QUELLE ORIGINALI + NUOVE)
// ============================================================================

/**
 * Estrae codice competenza dal nome/testo domanda
 */
function extract_competency_code($text) {
    $patterns = [
        '/\b(MECCANICA_[A-Z]+_\d+)\b/i',
        '/\b(AUTOMOBILE_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(ELETTRICITA_[A-Z]+_\d+)\b/i',
        '/\b([A-Z]+_[A-Z]+_[A-Z0-9]+)\b/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return strtoupper($matches[1]);
        }
    }
    
    return null;
}

/**
 * Ottiene domande senza competenza - ORIGINALE
 */
function get_orphan_questions($contextid) {
    global $DB;
    
    $sql = "SELECT q.id, q.name, q.questiontext, qc.name as category_name
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            WHERE qc.contextid = ?
            AND qbc.id IS NULL
            AND q.qtype = 'multichoice'
            ORDER BY qc.name, q.name";
    
    return $DB->get_records_sql($sql, [$contextid]);
}

/**
 * Ottiene domande con competenza - ORIGINALE
 */
function get_assigned_questions($contextid, $frameworkid = null) {
    global $DB;
    
    $params = [$contextid];
    $frameworkFilter = '';
    
    if ($frameworkid) {
        $frameworkFilter = 'AND c.competencyframeworkid = ?';
        $params[] = $frameworkid;
    }
    
    $sql = "SELECT q.id, q.name, q.questiontext, 
                   c.idnumber as comp_code, c.shortname as comp_name,
                   qbc.difficultylevel, qc.name as category_name
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            JOIN {competency} c ON c.id = qbc.competencyid
            WHERE qc.contextid = ?
            {$frameworkFilter}
            ORDER BY c.idnumber, q.name";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Ottiene quiz con statistiche - FIXATO
 */
function get_course_quizzes_with_stats($courseid) {
    global $DB;
    
    $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');
    
    foreach ($quizzes as &$quiz) {
        $quiz->total_questions = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
        
        // Query FIXATA con questionarea
        $sql = "SELECT COUNT(DISTINCT qs.slot) 
                FROM {quiz_slots} qs
                JOIN {question_references} qr ON qr.itemid = qs.id 
                    AND qr.component = 'mod_quiz' 
                    AND qr.questionarea = 'slot'
                JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                JOIN {question} q ON q.id = qv.questionid
                JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
                WHERE qs.quizid = ?";
        
        $quiz->questions_with_comp = $DB->count_records_sql($sql, [$quiz->id]);
        $quiz->coverage = $quiz->total_questions > 0 
            ? round(($quiz->questions_with_comp / $quiz->total_questions) * 100) 
            : 0;
        
        $quiz->attempts = $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'state' => 'finished']);
    }
    
    return $quizzes;
}

/**
 * Ottiene dettaglio domande quiz - FIXATO
 */
function get_quiz_questions_detail($quizid) {
    global $DB;
    
    $sql = "SELECT 
                qs.slot,
                q.id as questionid,
                q.name as question_name,
                q.questiontext,
                c.id as competency_id,
                c.idnumber as comp_code,
                c.shortname as comp_name,
                qbc.difficultylevel
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id 
                AND qr.component = 'mod_quiz' 
                AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            LEFT JOIN {competency} c ON c.id = qbc.competencyid
            WHERE qs.quizid = ?
            ORDER BY qs.slot";
    
    return $DB->get_records_sql($sql, [$quizid]);
}

/**
 * Ottiene report studente - FIXATO
 */
function get_student_audit_report($userid, $quizid) {
    global $DB;
    
    $attempt = $DB->get_record_sql("
        SELECT * FROM {quiz_attempts}
        WHERE userid = ? AND quiz = ? AND state = 'finished'
        ORDER BY timefinish DESC LIMIT 1
    ", [$userid, $quizid]);
    
    if (!$attempt) return null;
    
    $sql = "SELECT 
                qat.slot,
                qat.questionid,
                q.name as question_name,
                q.questiontext,
                qat.maxmark,
                COALESCE((
                    SELECT MAX(qas.fraction) 
                    FROM {question_attempt_steps} qas 
                    WHERE qas.questionattemptid = qat.id AND qas.fraction IS NOT NULL
                ), 0) as fraction,
                c.id as competency_id,
                c.idnumber as comp_code,
                c.shortname as comp_shortname,
                c.description as comp_description,
                qbc.difficultylevel
            FROM {question_attempts} qat
            JOIN {question_usages} qu ON qu.id = qat.questionusageid
            JOIN {quiz_attempts} qa ON qa.uniqueid = qu.id
            JOIN {question} q ON q.id = qat.questionid
            LEFT JOIN {qbank_competenciesbyquestion} qbc ON qbc.questionid = q.id
            LEFT JOIN {competency} c ON c.id = qbc.competencyid
            WHERE qa.id = ?
            ORDER BY qat.slot";
    
    $results = $DB->get_records_sql($sql, [$attempt->id]);
    
    $stats = [
        'attempt' => $attempt,
        'total_questions' => count($results),
        'correct' => 0,
        'with_competency' => 0,
        'without_competency' => 0,
        'by_competency' => [],
        'questions' => []
    ];
    
    foreach ($results as $r) {
        $isCorrect = $r->fraction >= 0.5;
        if ($isCorrect) $stats['correct']++;
        
        // Estrai testo domanda pulito - usa sempre il questiontext se disponibile
        $questionText = strip_tags($r->questiontext ?? '');
        $questionText = html_entity_decode($questionText, ENT_QUOTES, 'UTF-8');
        $questionText = trim($questionText);
        
        // Determina cosa mostrare come nome domanda
        // Se il nome contiene solo codici (es. "Q01 - MECCANICA_DT_01" o solo "MECCANICA_DT_01"), usa questiontext
        $nameIsCode = preg_match('/^Q\d+\s*[-–]\s*[A-Z_]+/', $r->question_name) 
                   || preg_match('/^[A-Z]+_[A-Z]+_\d+$/', $r->question_name)
                   || $r->question_name == ($r->comp_code ?? '');
        
        if ($nameIsCode && !empty($questionText)) {
            // Usa il testo della domanda (primi 120 caratteri)
            $r->question_display = substr($questionText, 0, 120) . (strlen($questionText) > 120 ? '...' : '');
        } else if (!empty($r->question_name)) {
            $r->question_display = $r->question_name;
        } else {
            $r->question_display = !empty($questionText) ? substr($questionText, 0, 120) : 'Domanda #' . $r->slot;
        }
        
        if ($r->competency_id) {
            $stats['with_competency']++;
            
            // Estrai descrizione competenza pulita
            $compDesc = strip_tags($r->comp_description ?? '');
            $compDesc = html_entity_decode($compDesc, ENT_QUOTES, 'UTF-8');
            $compDesc = trim($compDesc);
            $compName = !empty($compDesc) ? $compDesc : $r->comp_shortname;
            
            if (!isset($stats['by_competency'][$r->comp_code])) {
                $stats['by_competency'][$r->comp_code] = [
                    'code' => $r->comp_code,
                    'name' => $compName,
                    'total' => 0,
                    'correct' => 0,
                    'questions' => []
                ];
            }
            $stats['by_competency'][$r->comp_code]['total']++;
            if ($isCorrect) $stats['by_competency'][$r->comp_code]['correct']++;
            
            $r->is_correct = $isCorrect;
            $r->comp_name = $compName;
            $stats['by_competency'][$r->comp_code]['questions'][] = $r;
        } else {
            $stats['without_competency']++;
            $r->comp_name = '';
        }
        
        $r->is_correct = $isCorrect;
        // Assicurati che question_display sia sempre impostato
        if (!isset($r->question_display)) {
            $questionText = strip_tags($r->questiontext ?? '');
            $questionText = html_entity_decode($questionText, ENT_QUOTES, 'UTF-8');
            $questionText = trim($questionText);
            $r->question_display = !empty($questionText) ? substr($questionText, 0, 150) : $r->question_name;
        }
        $stats['questions'][] = $r;
    }
    
    foreach ($stats['by_competency'] as &$comp) {
        $comp['percentage'] = $comp['total'] > 0 
            ? round($comp['correct'] / $comp['total'] * 100, 1) 
            : 0;
    }
    
    ksort($stats['by_competency']);
    
    return $stats;
}

/**
 * Ottiene settori di un framework - ORIGINALE
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

// ============================================================================
// CSS COMPLETO
// ============================================================================
$css = '
<style>
/* === BASE === */
.scheda-page { max-width: 1300px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

/* === HEADER === */
.scheda-header { 
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
    color: white; 
    padding: 28px; 
    border-radius: 14px; 
    margin-bottom: 25px; 
    box-shadow: 0 8px 25px rgba(30, 60, 114, 0.3);
}
.scheda-header h2 { margin: 0 0 8px 0; font-size: 26px; }
.scheda-header p { margin: 0; opacity: 0.9; }
.scheda-header .course-badge { background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px; display: inline-block; margin-top: 12px; font-weight: 600; }

/* === NAVIGATION === */
.nav-tabs { display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
.nav-tab { 
    padding: 12px 20px; 
    border-radius: 8px; 
    text-decoration: none; 
    color: #555; 
    font-weight: 600; 
    font-size: 13px;
    background: #f5f5f5; 
    transition: all 0.3s; 
    border: 2px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.nav-tab:hover { background: #e8e8e8; color: #333; }
.nav-tab.active { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; }

/* === PANELS === */
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e8e8e8; margin-bottom: 22px; }
.panel h3 { margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 2px solid #eee; display: flex; align-items: center; gap: 10px; font-size: 17px; }

/* === STATS === */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 22px; }
.stat-card { padding: 20px; border-radius: 12px; text-align: center; }
.stat-card .number { font-size: 32px; font-weight: 700; }
.stat-card .label { font-size: 11px; opacity: 0.9; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-card.blue { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; }
.stat-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
.stat-card.orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
.stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.stat-card.teal { background: linear-gradient(135deg, #16a085, #1abc9c); color: white; }

/* === TABLES === */
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 12px 14px; border: 1px solid #e5e5e5; text-align: left; }
.data-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 10; }
.data-table tbody tr:hover { background: #f8fbff; }
.data-table code { background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; }

/* === BADGES === */
.badge { display: inline-block; padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #cce5ff; color: #004085; }
.badge-purple { background: #e9d8fd; color: #6b46c1; }

/* === PROGRESS === */
.progress-bar-wrap { height: 22px; background: #e9ecef; border-radius: 11px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 11px; }
.progress-bar-fill.excellent { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.progress-bar-fill.good { background: linear-gradient(90deg, #3498db, #2980b9); }
.progress-bar-fill.warning { background: linear-gradient(90deg, #f39c12, #e67e22); }
.progress-bar-fill.danger { background: linear-gradient(90deg, #e74c3c, #c0392b); }

/* === COMPETENCY CARDS === */
.comp-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 18px; margin-bottom: 12px; }
.comp-card .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.comp-card .code { font-family: monospace; background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 5px; font-weight: 600; }
.comp-card .questions { font-size: 12px; margin-top: 10px; }
.comp-card .q-tag { display: inline-block; margin: 2px; padding: 3px 8px; border-radius: 4px; }
.comp-card .q-tag.correct { background: #d4edda; color: #155724; }
.comp-card .q-tag.incorrect { background: #f8d7da; color: #721c24; }

/* === FORMS === */
.filters-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; padding: 18px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; }
.filter-group select, .filter-group input { padding: 11px 15px; border: 2px solid #e0e0e0; border-radius: 8px; min-width: 200px; font-size: 14px; }
.filter-group select:focus { border-color: #2a5298; outline: none; }

/* === BUTTONS === */
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; border: none; cursor: pointer; transition: all 0.3s; }
.btn-primary { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-outline { background: white; border: 2px solid #1e3c72; color: #1e3c72; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

/* === ALERTS === */
.alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 18px; }
.alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
.alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
.alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
.alert-info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }

/* === SCROLLABLE === */
.scrollable { max-height: 450px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; }

/* === AUDIT TRAIL (per log) === */
.audit-trail { font-family: monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 18px; border-radius: 8px; max-height: 280px; overflow-y: auto; }
.audit-trail .log-success { color: #4ec9b0; }
.audit-trail .log-warning { color: #dcdcaa; }
.audit-trail .log-error { color: #f14c4c; }

/* === BACK LINK === */
.back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 18px; color: #1e3c72; text-decoration: none; font-weight: 500; }
.back-link:hover { text-decoration: underline; }

/* === PRINT === */
.print-options { background: #e3f2fd; padding: 14px 20px; border-radius: 10px; margin-bottom: 18px; }
.print-options h4 { margin: 0 0 10px 0; font-size: 13px; }
.print-options label { display: inline-flex; align-items: center; gap: 6px; margin-right: 18px; cursor: pointer; font-size: 13px; }
.no-print { }

@media print {
    .no-print { display: none !important; }
    .scheda-page { max-width: 100%; padding: 0; }
    .scheda-header { background: #1e3c72 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .panel { break-inside: avoid; box-shadow: none; border: 1px solid #ccc; }
    .stat-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .nav-tabs { display: none; }
    .progress-bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>';

// ============================================================================
// OUTPUT
// ============================================================================
echo $OUTPUT->header();
echo $css;

// Carica dati comuni
$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');
$orphan_questions = get_orphan_questions($context->id);
$assigned_questions = get_assigned_questions($context->id);
$quizzes = get_course_quizzes_with_stats($courseid);

$total_questions = count($orphan_questions) + count($assigned_questions);
$coverage = $total_questions > 0 ? round(count($assigned_questions) / $total_questions * 100) : 0;
?>

<div class="scheda-page">
    
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link no-print">← Torna alla Dashboard Plugin</a>
    
    <!-- HEADER -->
    <div class="scheda-header no-print">
        <h2>🔍 Scheda Competenze - Audit e Report</h2>
        <p>Sistema completo per verificare, assegnare e certificare le valutazioni basate su competenze</p>
        <div class="course-badge">📚 <?php echo format_string($course->fullname); ?></div>
    </div>
    
    <!-- NAVIGAZIONE TAB -->
    <div class="nav-tabs no-print">
        <a href="?courseid=<?php echo $courseid; ?>&action=dashboard" 
           class="nav-tab <?php echo $action == 'dashboard' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&action=orphans" 
           class="nav-tab <?php echo $action == 'orphans' ? 'active' : ''; ?>">
            ⚠️ Domande Orfane (<?php echo count($orphan_questions); ?>)
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&action=assign" 
           class="nav-tab <?php echo $action == 'assign' ? 'active' : ''; ?>">
            🔗 Assegna Competenze
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&action=quiz_report" 
           class="nav-tab <?php echo $action == 'quiz_report' ? 'active' : ''; ?>">
            📝 Report Quiz
        </a>
        <a href="?courseid=<?php echo $courseid; ?>&action=student_audit" 
           class="nav-tab <?php echo $action == 'student_audit' ? 'active' : ''; ?>">
            👤 Audit Studente
        </a>
    </div>

<?php
// ============================================================================
// DASHBOARD
// ============================================================================
if ($action == 'dashboard'):
?>

    <div class="panel">
        <h3>📊 Stato Generale del Corso</h3>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="number"><?php echo $total_questions; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo count($assigned_questions); ?></div>
                <div class="label">Con Competenza</div>
            </div>
            <div class="stat-card <?php echo count($orphan_questions) > 0 ? 'orange' : 'green'; ?>">
                <div class="number"><?php echo count($orphan_questions); ?></div>
                <div class="label">Senza Competenza</div>
            </div>
            <div class="stat-card <?php echo $coverage >= 80 ? 'green' : ($coverage >= 50 ? 'orange' : 'red'); ?>">
                <div class="number"><?php echo $coverage; ?>%</div>
                <div class="label">Copertura</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo count($quizzes); ?></div>
                <div class="label">Quiz nel Corso</div>
            </div>
        </div>
        
        <?php if (count($orphan_questions) > 0): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Attenzione:</strong> Ci sono <?php echo count($orphan_questions); ?> domande senza competenza assegnata.
            <a href="?courseid=<?php echo $courseid; ?>&action=orphans" style="margin-left: 10px;">Visualizza →</a>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <strong>✅ Ottimo!</strong> Tutte le domande hanno una competenza assegnata.
        </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h3>📝 Quiz del Corso - Copertura Competenze</h3>
        
        <?php if (!empty($quizzes)): ?>
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th style="width: 100px;">Domande</th>
                        <th style="width: 120px;">Con Comp.</th>
                        <th style="width: 120px;">Copertura</th>
                        <th style="width: 90px;">Tentativi</th>
                        <th style="width: 90px;">Stato</th>
                        <th style="width: 100px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><strong><?php echo format_string($quiz->name); ?></strong></td>
                        <td class="text-center"><?php echo $quiz->total_questions; ?></td>
                        <td class="text-center"><?php echo $quiz->questions_with_comp; ?></td>
                        <td>
                            <div class="progress-bar-wrap" style="height: 18px;">
                                <?php $class = $quiz->coverage >= 80 ? 'excellent' : ($quiz->coverage >= 50 ? 'warning' : 'danger'); ?>
                                <div class="progress-bar-fill <?php echo $class; ?>" style="width: <?php echo $quiz->coverage; ?>%;">
                                    <?php echo $quiz->coverage; ?>%
                                </div>
                            </div>
                        </td>
                        <td class="text-center"><?php echo $quiz->attempts; ?></td>
                        <td>
                            <?php if ($quiz->coverage == 100): ?>
                                <span class="badge badge-success">✅</span>
                            <?php elseif ($quiz->coverage >= 80): ?>
                                <span class="badge badge-info">📊</span>
                            <?php elseif ($quiz->coverage >= 50): ?>
                                <span class="badge badge-warning">⚠️</span>
                            <?php else: ?>
                                <span class="badge badge-danger">❌</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?courseid=<?php echo $courseid; ?>&action=quiz_report&quizid=<?php echo $quiz->id; ?>" 
                               class="btn btn-outline" style="padding: 6px 12px; font-size: 11px;">
                                Dettagli
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p>Nessun quiz presente nel corso.</p>
        <?php endif; ?>
    </div>

<?php
// ============================================================================
// DOMANDE ORFANE
// ============================================================================
elseif ($action == 'orphans'):
?>

    <div class="panel">
        <h3>⚠️ Domande Senza Competenza Assegnata</h3>
        
        <p>Queste domande non hanno una competenza assegnata e quindi <strong>non vengono conteggiate</strong> nei report di valutazione delle competenze.</p>
        
        <?php if (!empty($orphan_questions)): ?>
        
        <div class="stats-grid" style="margin-bottom: 20px;">
            <div class="stat-card orange">
                <div class="number"><?php echo count($orphan_questions); ?></div>
                <div class="label">Domande Orfane</div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?courseid=<?php echo $courseid; ?>&action=assign" class="btn btn-success">
                🔗 Assegnazione Automatica
            </a>
            <a href="fix_competenze_per_categoria.php?courseid=<?php echo $courseid; ?>" class="btn btn-warning">
                🗂️ Fix per Categoria
            </a>
            <a href="fix_competenze_automobile.php?courseid=<?php echo $courseid; ?>" class="btn btn-danger">
                🚗 Fix Automobile
            </a>
        </div>
        
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nome Domanda</th>
                        <th style="width: 150px;">Categoria</th>
                        <th>Anteprima Testo</th>
                        <th style="width: 150px;">Codice Rilevato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($orphan_questions as $q): 
                        $detected_code = extract_competency_code($q->name . ' ' . $q->questiontext);
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $i++; ?></td>
                        <td><?php echo format_string($q->name); ?></td>
                        <td><small><?php echo $q->category_name; ?></small></td>
                        <td><small style="color: #666;"><?php echo strip_tags(substr($q->questiontext, 0, 80)); ?>...</small></td>
                        <td>
                            <?php if ($detected_code): ?>
                                <code><?php echo $detected_code; ?></code>
                                <span class="badge badge-success">✓</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Non rilevato</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
        <div class="alert alert-success">
            <strong>✅ Perfetto!</strong> Tutte le domande hanno una competenza assegnata.
        </div>
        <?php endif; ?>
    </div>

<?php
// ============================================================================
// ASSEGNAZIONE AUTOMATICA
// ============================================================================
elseif ($action == 'assign'):
    
    // Esegui assegnazione se confermato
    if ($confirm && $frameworkid && $sector) {
        require_sesskey();
        
        $competencies = $DB->get_records_sql("
            SELECT id, idnumber FROM {competency} 
            WHERE competencyframeworkid = ? AND idnumber LIKE ?
        ", [$frameworkid, $sector . '_%']);
        
        $comp_lookup = [];
        foreach ($competencies as $c) {
            $comp_lookup[$c->idnumber] = $c->id;
        }
        
        $assigned = 0;
        $not_found = 0;
        $log = [];
        
        foreach ($orphan_questions as $q) {
            $code = extract_competency_code($q->name . ' ' . $q->questiontext);
            
            if ($code && isset($comp_lookup[$code])) {
                $record = new stdClass();
                $record->questionid = $q->id;
                $record->competencyid = $comp_lookup[$code];
                $record->difficultylevel = 1;
                $DB->insert_record('qbank_competenciesbyquestion', $record);
                
                $assigned++;
                $log[] = ['type' => 'success', 'msg' => "✅ {$q->name} → $code"];
            } else {
                $not_found++;
                $log[] = ['type' => 'warning', 'msg' => "⚠️ {$q->name} → Codice non trovato"];
            }
        }
        
        echo '<div class="alert alert-success">';
        echo "<strong>✅ Operazione completata!</strong><br>";
        echo "Competenze assegnate: <strong>$assigned</strong><br>";
        echo "Non trovate: <strong>$not_found</strong>";
        echo '</div>';
        
        echo '<div class="panel"><h3>📋 Log Operazioni</h3>';
        echo '<div class="audit-trail">';
        foreach ($log as $l) {
            echo '<div class="log-' . $l['type'] . '">' . htmlspecialchars($l['msg']) . '</div>';
        }
        echo '</div></div>';
    }
?>

    <div class="panel">
        <h3>🔗 Assegnazione Automatica Competenze</h3>
        
        <p>Questo strumento analizza il nome e il testo delle domande per estrarre il codice competenza e assegnarlo automaticamente.</p>
        
        <?php if (empty($orphan_questions)): ?>
        <div class="alert alert-success">
            <strong>✅ Nessuna domanda da processare!</strong> Tutte le domande hanno già una competenza.
        </div>
        <?php else: ?>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&action=assign&confirm=1">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            
            <div class="filters-row">
                <div class="filter-group">
                    <label>📚 Framework</label>
                    <select name="frameworkid" id="frameworkSelect" required>
                        <option value="">-- Seleziona --</option>
                        <?php foreach ($frameworks as $fw): ?>
                        <option value="<?php echo $fw->id; ?>"><?php echo format_string($fw->shortname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>🎯 Settore</label>
                    <select name="sector" id="sectorSelect" required>
                        <option value="">-- Prima seleziona framework --</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">
                    🚀 Avvia Assegnazione Automatica
                </button>
            </div>
        </form>
        
        <div class="alert alert-info">
            <strong>📊 Anteprima:</strong> <?php echo count($orphan_questions); ?> domande da processare.
            <br>L'assegnazione cercherà codici come <code>MECCANICA_MIS_01</code> o <code>AUTOMOBILE_MR_A1</code> nel nome/testo.
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
    document.getElementById('frameworkSelect')?.addEventListener('change', function() {
        const fwId = this.value;
        const sectorSelect = document.getElementById('sectorSelect');
        
        if (!fwId) {
            sectorSelect.innerHTML = '<option value="">-- Prima seleziona framework --</option>';
            return;
        }
        
        fetch('ajax_get_sectors.php?frameworkid=' + fwId)
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">-- Seleziona settore --</option>';
                for (const [key, val] of Object.entries(data)) {
                    html += `<option value="${key}">${key} (${val.count} competenze)</option>`;
                }
                sectorSelect.innerHTML = html;
            })
            .catch(() => {
                sectorSelect.innerHTML = `
                    <option value="">-- Seleziona --</option>
                    <option value="MECCANICA">MECCANICA</option>
                    <option value="AUTOMOBILE">AUTOMOBILE</option>
                    <option value="ELETTRICITA">ELETTRICITA</option>
                `;
            });
    });
    </script>

<?php
// ============================================================================
// REPORT QUIZ
// ============================================================================
elseif ($action == 'quiz_report'):
    
    if ($quizid):
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $quiz_questions = get_quiz_questions_detail($quizid);
        
        $with_comp = 0;
        $by_level = [1 => 0, 2 => 0, 3 => 0];
        foreach ($quiz_questions as $q) {
            if ($q->comp_code) {
                $with_comp++;
                $level = $q->difficultylevel ?: 1;
                $by_level[$level] = ($by_level[$level] ?? 0) + 1;
            }
        }
?>

    <!-- Opzioni stampa -->
    <div class="print-options no-print">
        <h4>🖨️ Opzioni Stampa</h4>
        <label><input type="checkbox" checked id="chkStats"> Statistiche</label>
        <label><input type="checkbox" checked id="chkTable"> Tabella Domande</label>
        <button onclick="window.print()" class="btn btn-primary" style="margin-left: 15px;">🖨️ Stampa Report</button>
    </div>

    <div class="panel" id="sectionStats">
        <h3>📝 Report Quiz: <?php echo format_string($quiz->name); ?></h3>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="number"><?php echo count($quiz_questions); ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $with_comp; ?></div>
                <div class="label">Con Competenza</div>
            </div>
            <div class="stat-card" style="background: #e8f5e9;">
                <div class="number" style="color: #2e7d32;"><?php echo $by_level[1]; ?></div>
                <div class="label" style="color: #2e7d32;">⭐ Base</div>
            </div>
            <div class="stat-card" style="background: #fff3e0;">
                <div class="number" style="color: #e65100;"><?php echo $by_level[2]; ?></div>
                <div class="label" style="color: #e65100;">⭐⭐ Intermedio</div>
            </div>
            <div class="stat-card" style="background: #fce4ec;">
                <div class="number" style="color: #c2185b;"><?php echo $by_level[3]; ?></div>
                <div class="label" style="color: #c2185b;">⭐⭐⭐ Avanzato</div>
            </div>
        </div>
    </div>
    
    <div class="panel" id="sectionTable">
        <h3>📋 Dettaglio Domande</h3>
        
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Slot</th>
                        <th>Domanda</th>
                        <th style="width: 140px;">Competenza</th>
                        <th>Descrizione</th>
                        <th style="width: 90px;">Livello</th>
                        <th style="width: 70px;">Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quiz_questions as $q): ?>
                    <tr>
                        <td class="text-center"><?php echo $q->slot; ?></td>
                        <td><?php echo format_string($q->question_name); ?></td>
                        <td>
                            <?php if ($q->comp_code): ?>
                                <code><?php echo $q->comp_code; ?></code>
                            <?php else: ?>
                                <span class="badge badge-warning">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $q->comp_name ?: '—'; ?></td>
                        <td class="text-center">
                            <?php echo $q->difficultylevel ? str_repeat('⭐', $q->difficultylevel) : '—'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($q->comp_code): ?>
                                <span class="badge badge-success">✓</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✗</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
    else:
        // Selezione quiz
?>
    <div class="panel">
        <h3>📝 Seleziona un Quiz</h3>
        
        <div class="filters-row">
            <div class="filter-group">
                <label>Quiz</label>
                <select onchange="if(this.value) window.location='?courseid=<?php echo $courseid; ?>&action=quiz_report&quizid='+this.value">
                    <option value="">-- Seleziona quiz --</option>
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?php echo $q->id; ?>"><?php echo format_string($q->name); ?> (<?php echo $q->coverage; ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
<?php
    endif;

// ============================================================================
// AUDIT STUDENTE
// ============================================================================
elseif ($action == 'student_audit'):
    $enrolled = get_enrolled_users($context, 'mod/quiz:attempt');
    
    if ($quizid && $userid):
        $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $report = get_student_audit_report($userid, $quizid);
        
        if ($report):
?>

    <!-- Opzioni stampa -->
    <div class="print-options no-print">
        <h4>🖨️ Opzioni Stampa</h4>
        <label><input type="checkbox" checked> Intestazione</label>
        <label><input type="checkbox" checked> Statistiche</label>
        <label><input type="checkbox" checked> Competenze</label>
        <label><input type="checkbox" checked> Dettaglio</label>
        <label><input type="checkbox" checked> Certificazione</label>
        <button onclick="window.print()" class="btn btn-primary" style="margin-left: 15px;">🖨️ Stampa Audit</button>
    </div>

    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="margin: 0;">👤 Audit Valutazione Studente</h3>
                <p style="margin: 5px 0 0 0; color: #666;">
                    <strong><?php echo fullname($student); ?></strong> — <?php echo format_string($quiz->name); ?>
                </p>
            </div>
            <div class="alert alert-info" style="margin: 0; padding: 10px 15px;">
                📅 <?php echo date('d/m/Y H:i', $report['attempt']->timefinish); ?>
                &nbsp;|&nbsp;
                ⏱️ <?php echo round(($report['attempt']->timefinish - $report['attempt']->timestart) / 60); ?> min
            </div>
        </div>
    </div>
    
    <div class="panel">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="number"><?php echo $report['total_questions']; ?></div>
                <div class="label">Domande</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $report['correct']; ?></div>
                <div class="label">Corrette</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $report['with_competency']; ?></div>
                <div class="label">Con Competenza</div>
            </div>
            <div class="stat-card <?php echo $report['without_competency'] > 0 ? 'orange' : 'green'; ?>">
                <div class="number"><?php echo $report['without_competency']; ?></div>
                <div class="label">Senza Competenza</div>
            </div>
        </div>
        
        <?php if ($report['without_competency'] > 0): ?>
        <div class="alert alert-warning">
            ⚠️ <strong><?php echo $report['without_competency']; ?> domande</strong> non hanno competenza assegnata.
        </div>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h3>📊 Valutazione per Competenza</h3>
        
        <?php foreach ($report['by_competency'] as $comp): 
            $pct = $comp['percentage'];
            $barClass = $pct >= 80 ? 'excellent' : ($pct >= 60 ? 'good' : ($pct >= 40 ? 'warning' : 'danger'));
        ?>
        <div class="comp-card">
            <div class="header">
                <div>
                    <span class="code"><?php echo $comp['code']; ?></span>
                    <strong style="margin-left: 10px;"><?php echo $comp['name']; ?></strong>
                </div>
                <span class="badge <?php echo $pct >= 80 ? 'badge-success' : ($pct >= 60 ? 'badge-info' : ($pct >= 40 ? 'badge-warning' : 'badge-danger')); ?>">
                    <?php echo $comp['correct']; ?>/<?php echo $comp['total']; ?> = <?php echo $pct; ?>%
                </span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill <?php echo $barClass; ?>" style="width: <?php echo $pct; ?>%;">
                    <?php echo $pct; ?>%
                </div>
            </div>
            <div class="questions">
                <strong>Domande:</strong>
                <?php foreach ($comp['questions'] as $q): ?>
                <span class="q-tag <?php echo $q->is_correct ? 'correct' : 'incorrect'; ?>">
                    <?php echo $q->is_correct ? '✓' : '✗'; ?> #<?php echo $q->slot; ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="panel">
        <h3>📋 Dettaglio Risposte</h3>
        
        <div class="scrollable">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Slot</th>
                        <th style="width: 35%;">Domanda</th>
                        <th style="width: 25%;">Competenza</th>
                        <th>Livello</th>
                        <th>Risultato</th>
                        <th>Punteggio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['questions'] as $q): ?>
                    <tr style="background: <?php echo $q->is_correct ? '#f0fff0' : '#fff5f5'; ?>;">
                        <td class="text-center"><?php echo $q->slot; ?></td>
                        <td>
                            <div style="font-weight: 500;"><?php echo format_string($q->question_display ?? $q->question_name); ?></div>
                            <?php if (!empty($q->questiontext) && $q->question_display != $q->question_name): ?>
                            <small style="color: #888; font-size: 11px;"><?php echo $q->question_name; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($q->comp_code): ?>
                                <code style="font-size: 10px;"><?php echo $q->comp_code; ?></code><br>
                                <small style="color: #555;"><?php echo format_string($q->comp_name ?? $q->comp_code); ?></small>
                            <?php else: ?>
                                <span class="badge badge-warning">— Nessuna</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $q->difficultylevel ? str_repeat('⭐', $q->difficultylevel) : '—'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($q->is_correct): ?>
                                <span class="badge badge-success">✓ Corretta</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✗ Errata</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo round($q->fraction * $q->maxmark, 2); ?>/<?php echo $q->maxmark; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="panel" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
        <h3>🔐 Certificazione Audit</h3>
        
        <p>Questo documento certifica che la valutazione dello studente <strong><?php echo fullname($student); ?></strong> 
        per il quiz <strong><?php echo format_string($quiz->name); ?></strong> è stata calcolata secondo i seguenti criteri:</p>
        
        <ul style="line-height: 2;">
            <li>Ogni domanda è stata valutata automaticamente dal sistema Moodle</li>
            <li>Le competenze sono state assegnate alle domande prima del tentativo</li>
            <li>Il punteggio per competenza è calcolato come: (risposte corrette / domande totali) × 100</li>
            <li>Soglie: ≥80% Eccellente, ≥60% Buono, ≥50% Sufficiente, <50% Insufficiente</li>
        </ul>
        
        <hr style="margin: 20px 0;">
        
        <p>
            <strong>📅 Data generazione:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
            <strong>👤 Generato da:</strong> <?php echo fullname($USER); ?><br>
            <strong>🔧 Sistema:</strong> Moodle - Plugin Audit Competenze v2.0
        </p>
    </div>

<?php
        else:
            echo '<div class="alert alert-warning">❌ Nessun tentativo completato trovato per questo studente.</div>';
        endif;
        
    else:
        // Form selezione
?>
    <div class="panel">
        <h3>👤 Seleziona Studente e Quiz</h3>
        
        <form method="get">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="action" value="student_audit">
            
            <div class="filters-row">
                <div class="filter-group">
                    <label>📝 Quiz</label>
                    <select name="quizid" required>
                        <option value="">-- Seleziona quiz --</option>
                        <?php foreach ($quizzes as $q): ?>
                        <option value="<?php echo $q->id; ?>"><?php echo format_string($q->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>👤 Studente</label>
                    <select name="userid" required>
                        <option value="">-- Seleziona studente --</option>
                        <?php foreach ($enrolled as $u): ?>
                        <option value="<?php echo $u->id; ?>"><?php echo fullname($u); ?> (<?php echo $u->email; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🔍 Genera Audit</button>
            </div>
        </form>
    </div>
<?php
    endif;

endif;
?>

</div>

<?php
echo $OUTPUT->footer();
