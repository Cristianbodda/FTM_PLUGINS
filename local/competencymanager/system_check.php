<?php
/**
 * DIAGNOSTICA COMPLETA SISTEMA FTM
 * ================================
 * Verifica tutti i plugin e mostra chiaramente se funzionano.
 * 
 * AGGIORNATO per Moodle 4.4+/4.5/5.0
 * - Query corrette per question_references (no più questionid in quiz_slots)
 * - Tabelle corrette per coachmanager
 * - Test grafici radar
 * - Link rapidi con courseid
 * 
 * @package    local_competencymanager
 * @author     FTM Tools
 * @version    2.1 - 2025-01-02
 */

require_once(__DIR__ . '/../../config.php');

// Verifica login admin
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/competencymanager/system_check.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Diagnostica Sistema FTM');
$PAGE->set_heading('🔬 Diagnostica Completa Sistema FTM');

echo $OUTPUT->header();

// Funzione per verificare se una tabella esiste
function table_exists($tablename) {
    global $DB;
    $tables = $DB->get_tables();
    return in_array($tablename, $tables);
}

// Funzione per contare record
function count_records_safe($table, $conditions = []) {
    global $DB;
    try {
        return $DB->count_records($table, $conditions);
    } catch (Exception $e) {
        return -1;
    }
}

// Funzione per eseguire query SQL in modo sicuro
function count_sql_safe($sql, $params = []) {
    global $DB;
    try {
        return $DB->count_records_sql($sql, $params);
    } catch (Exception $e) {
        return -1;
    }
}

// Parametro corso selezionato dall'utente
$selected_courseid = optional_param('courseid', 0, PARAM_INT);

// Carica lista corsi con quiz
function get_courses_with_quiz() {
    global $DB;
    return $DB->get_records_sql("
        SELECT DISTINCT c.id, c.fullname, c.shortname,
               COUNT(DISTINCT q.id) as quiz_count,
               COUNT(DISTINCT qa.id) as attempts_count
        FROM {course} c
        JOIN {quiz} q ON q.course = c.id
        LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
        GROUP BY c.id, c.fullname, c.shortname
        ORDER BY c.fullname
    ");
}

$courses_with_quiz = get_courses_with_quiz();

// Se è stato selezionato un corso, carica i suoi dati
$selected_course = null;
if ($selected_courseid > 0) {
    $selected_course = $DB->get_record('course', ['id' => $selected_courseid]);
}

?>

<style>
.check-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.check-header { 
    background: linear-gradient(135deg, #2c3e50, #34495e); 
    color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; 
    text-align: center;
}
.check-card { 
    background: white; border-radius: 12px; padding: 20px; 
    margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
}
.check-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.status-ok { color: #27ae60; font-weight: bold; }
.status-error { color: #e74c3c; font-weight: bold; }
.status-warning { color: #f39c12; font-weight: bold; }
.status-info { color: #3498db; font-weight: bold; }

.plugin-status {
    display: flex;
    align-items: center;
    padding: 15px;
    margin: 10px 0;
    border-radius: 10px;
    font-size: 16px;
}
.plugin-ok { background: #d4edda; border-left: 5px solid #27ae60; }
.plugin-error { background: #f8d7da; border-left: 5px solid #e74c3c; }
.plugin-warning { background: #fff3cd; border-left: 5px solid #f39c12; }

.plugin-icon { font-size: 24px; margin-right: 15px; }
.plugin-name { font-weight: bold; flex: 1; }
.plugin-result { font-weight: bold; }

.summary-box {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    margin: 20px 0;
}
.summary-box h2 { margin: 0 0 10px 0; font-size: 28px; }
.summary-box p { margin: 0; font-size: 18px; opacity: 0.9; }

.detail-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.detail-table th, .detail-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.detail-table th { background: #34495e; color: white; }
.detail-table tr:nth-child(even) { background: #f9f9f9; }

.big-number { font-size: 48px; font-weight: bold; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
.stat-box { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; }
.stat-box .number { font-size: 32px; font-weight: bold; color: #3498db; }
.stat-box .label { color: #666; font-size: 14px; }

.test-section { margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
.test-section h4 { margin-top: 0; color: #2c3e50; }

.moodle-version { 
    background: #e3f2fd; 
    padding: 10px 15px; 
    border-radius: 8px; 
    display: inline-block;
    margin: 10px 0;
}

.btn { 
    display: inline-block; 
    padding: 10px 20px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-weight: 600;
    margin: 5px;
    transition: all 0.3s;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn-purple { background: #9b59b6; color: white; }
.btn-disabled { background: #ccc; color: #666; pointer-events: none; }

.radar-preview {
    display: inline-block;
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #667eea22, #764ba222);
    border-radius: 50%;
    margin: 10px;
    position: relative;
    border: 3px solid #667eea;
}
.radar-preview::after {
    content: '📊';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 40px;
}
.radar-preview.radar-ok { border-color: #27ae60; background: linear-gradient(135deg, #27ae6022, #2ecc7122); }
.radar-preview.radar-error { border-color: #e74c3c; background: linear-gradient(135deg, #e74c3c22, #c0392b22); }
</style>

<div class="check-container">

<div class="check-header">
    <h1>🔬 Diagnostica Completa Sistema FTM</h1>
    <p>Verifica automatica di tutti i plugin e componenti</p>
    <p style="opacity: 0.7;">Data: <?php echo date('d/m/Y H:i:s'); ?></p>
    <div class="moodle-version">
        <strong>Moodle:</strong> <?php echo $CFG->release; ?> | 
        <strong>PHP:</strong> <?php echo phpversion(); ?>
    </div>
</div>

<!-- SELETTORE CORSO -->
<div class="check-card" style="background: linear-gradient(135deg, #e3f2fd, #bbdefb);">
    <h3>📚 Seleziona Corso da Testare</h3>
    <p>Scegli il corso su cui eseguire i test funzionali e i link rapidi.</p>
    
    <form method="get" action="" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <select name="courseid" id="courseid" style="flex: 1; min-width: 300px; padding: 12px; font-size: 16px; border: 2px solid #1976d2; border-radius: 8px;">
            <option value="0">-- Seleziona un corso --</option>
            <?php foreach ($courses_with_quiz as $c): ?>
            <option value="<?php echo $c->id; ?>" <?php echo ($selected_courseid == $c->id) ? 'selected' : ''; ?>>
                <?php echo format_string($c->fullname); ?> 
                (<?php echo $c->quiz_count; ?> quiz, <?php echo $c->attempts_count; ?> tentativi)
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="padding: 12px 25px; font-size: 16px;">
            🔍 Testa questo corso
        </button>
    </form>
    
    <?php if ($selected_courseid > 0 && $selected_course): ?>
    <div style="margin-top: 15px; padding: 15px; background: #c8e6c9; border-radius: 8px; border-left: 5px solid #4caf50;">
        <strong>✅ Corso selezionato:</strong> <?php echo format_string($selected_course->fullname); ?> (ID: <?php echo $selected_courseid; ?>)
    </div>
    <?php elseif ($selected_courseid == 0): ?>
    <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 5px solid #ffc107;">
        <strong>⚠️ Nessun corso selezionato.</strong> I test funzionali useranno dati generici. Seleziona un corso per test specifici.
    </div>
    <?php endif; ?>
</div>

<?php
// ============================================================================
// INIZIA DIAGNOSTICA
// ============================================================================

$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'details' => []
];

// ============================================================================
// 1. VERIFICA TABELLE DATABASE
// ============================================================================
?>

<div class="check-card">
    <h3>📊 1. Verifica Tabelle Database</h3>
    <p>Controllo che tutte le tabelle necessarie esistano nel database.</p>
    
    <?php
    // TABELLE CORRETTE per i plugin FTM
    $required_tables = [
        // Plugin qbank_competenciesbyquestion
        'qbank_competenciesbyquestion' => ['desc' => 'Collegamento domande → competenze', 'required' => true],
        
        // Plugin selfassessment
        'local_selfassessment' => ['desc' => 'Autovalutazioni studenti', 'required' => true],
        'local_selfassessment_status' => ['desc' => 'Stato abilitazione autovalutazione', 'required' => false],
        'local_selfassessment_assign' => ['desc' => 'Competenze assegnate da autovalutare', 'required' => false],
        
        // Plugin coachmanager
        'local_coachmanager_notes' => ['desc' => 'Note del coach sugli studenti', 'required' => false],
        'local_coachmanager_compare' => ['desc' => 'Storico confronti studenti', 'required' => false],
        'local_coachmanager_jobs' => ['desc' => 'Annunci lavoro per matching', 'required' => false],
        'local_coachmanager_matches' => ['desc' => 'Risultati matching studente-lavoro', 'required' => false],
        
        // Plugin competencymanager
        'local_competencymanager_auth' => ['desc' => 'Autorizzazioni studenti per report', 'required' => false],
        'local_student_coaching' => ['desc' => 'Assegnazione studenti ai coach', 'required' => false],
        
        // Core Moodle
        'competency' => ['desc' => 'Competenze (core Moodle)', 'required' => true],
        'competency_framework' => ['desc' => 'Framework competenze (core Moodle)', 'required' => true],
        'quiz_attempts' => ['desc' => 'Tentativi quiz (core Moodle)', 'required' => true],
        'question_attempts' => ['desc' => 'Risposte domande (core Moodle)', 'required' => true],
        'question_references' => ['desc' => 'Riferimenti domande Moodle 4.x (core)', 'required' => true]
    ];
    
    foreach ($required_tables as $table => $info) {
        $results['total']++;
        $exists = table_exists($table);
        
        if ($exists) {
            $count = count_records_safe($table);
            $results['passed']++;
            $results['details']['tables'][$table] = ['status' => 'ok', 'count' => $count];
            ?>
            <div class="plugin-status plugin-ok">
                <span class="plugin-icon">✅</span>
                <span class="plugin-name"><?php echo $table; ?> <small style="color:#666;">(<?php echo $info['desc']; ?>)</small></span>
                <span class="plugin-result"><?php echo $count >= 0 ? $count . ' record' : 'OK'; ?></span>
            </div>
            <?php
        } else {
            if (!$info['required']) {
                $results['warnings']++;
                $results['details']['tables'][$table] = ['status' => 'warning', 'count' => 0];
                ?>
                <div class="plugin-status plugin-warning">
                    <span class="plugin-icon">⚠️</span>
                    <span class="plugin-name"><?php echo $table; ?> <small style="color:#666;">(<?php echo $info['desc']; ?>)</small></span>
                    <span class="plugin-result">Non ancora creata (opzionale)</span>
                </div>
                <?php
            } else {
                $results['failed']++;
                $results['details']['tables'][$table] = ['status' => 'error', 'count' => 0];
                ?>
                <div class="plugin-status plugin-error">
                    <span class="plugin-icon">❌</span>
                    <span class="plugin-name"><?php echo $table; ?> <small style="color:#666;">(<?php echo $info['desc']; ?>)</small></span>
                    <span class="plugin-result">MANCANTE!</span>
                </div>
                <?php
            }
        }
    }
    ?>
</div>

<?php
// ============================================================================
// 2. VERIFICA PLUGIN INSTALLATI
// ============================================================================
?>

<div class="check-card">
    <h3>🔌 2. Verifica Plugin Installati</h3>
    <p>Controllo che tutti i plugin FTM siano installati correttamente.</p>
    
    <?php
    $plugins_to_check = [
        ['path' => '/local/competencymanager', 'name' => 'Competency Manager', 'type' => 'local', 'required' => true],
        ['path' => '/local/coachmanager', 'name' => 'Coach Manager', 'type' => 'local', 'required' => true],
        ['path' => '/local/selfassessment', 'name' => 'Self Assessment', 'type' => 'local', 'required' => true],
        ['path' => '/local/competencyreport', 'name' => 'Competency Report', 'type' => 'local', 'required' => false],
        ['path' => '/local/competencyxmlimport', 'name' => 'XML Import', 'type' => 'local', 'required' => false],
        ['path' => '/local/labeval', 'name' => 'Lab Evaluation', 'type' => 'local', 'required' => false],
        ['path' => '/local/ftm_hub', 'name' => 'FTM Hub', 'type' => 'local', 'required' => false],
        ['path' => '/question/bank/competenciesbyquestion', 'name' => 'Competencies by Question', 'type' => 'qbank', 'required' => true],
        ['path' => '/blocks/ftm_tools', 'name' => 'FTM Tools Block', 'type' => 'block', 'required' => false]
    ];
    
    foreach ($plugins_to_check as $plugin_info) {
        $results['total']++;
        $fullpath = $CFG->dirroot . $plugin_info['path'];
        $version_file = $fullpath . '/version.php';
        
        if (file_exists($version_file)) {
            // Leggi versione in modo sicuro (senza include per evitare conflitti)
            $version_content = file_get_contents($version_file);
            $version = 'N/A';
            
            // Cerca $plugin->release o $plugin->version nel file
            if (preg_match('/\$plugin\s*->\s*release\s*=\s*[\'"]([^\'"]+)[\'"]/', $version_content, $matches)) {
                $version = $matches[1];
            } elseif (preg_match('/\$plugin\s*->\s*version\s*=\s*(\d+)/', $version_content, $matches)) {
                $version = $matches[1];
            }
            
            $results['passed']++;
            ?>
            <div class="plugin-status plugin-ok">
                <span class="plugin-icon">✅</span>
                <span class="plugin-name"><?php echo $plugin_info['name']; ?> <small style="color:#666;">(<?php echo $plugin_info['type']; ?>)</small></span>
                <span class="plugin-result">Installato <?php echo $version != 'N/A' ? "- $version" : ''; ?></span>
            </div>
            <?php
        } else {
            if ($plugin_info['required']) {
                $results['failed']++;
                ?>
                <div class="plugin-status plugin-error">
                    <span class="plugin-icon">❌</span>
                    <span class="plugin-name"><?php echo $plugin_info['name']; ?></span>
                    <span class="plugin-result">NON INSTALLATO!</span>
                </div>
                <?php
            } else {
                $results['warnings']++;
                ?>
                <div class="plugin-status plugin-warning">
                    <span class="plugin-icon">⚠️</span>
                    <span class="plugin-name"><?php echo $plugin_info['name']; ?></span>
                    <span class="plugin-result">Non installato (opzionale)</span>
                </div>
                <?php
            }
        }
    }
    ?>
</div>

<?php
// ============================================================================
// 3. STATISTICHE SISTEMA
// ============================================================================
?>

<div class="check-card">
    <h3>📈 3. Statistiche Sistema</h3>
    <p>Panoramica dei dati presenti nel sistema.</p>
    
    <?php
    // Raccogli statistiche
    $stats = [
        'frameworks' => count_records_safe('competency_framework'),
        'competencies' => count_records_safe('competency'),
        'questions_with_comp' => count_records_safe('qbank_competenciesbyquestion'),
        'quiz_attempts' => count_records_safe('quiz_attempts', ['state' => 'finished']),
        'selfassessments' => count_records_safe('local_selfassessment'),
        'coach_notes' => count_records_safe('local_coachmanager_notes'),
        'courses_with_quiz' => count_sql_safe("SELECT COUNT(DISTINCT course) FROM {quiz}"),
        'students_with_attempts' => count_sql_safe("SELECT COUNT(DISTINCT userid) FROM {quiz_attempts} WHERE state = 'finished'")
    ];
    ?>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="number"><?php echo $stats['frameworks'] >= 0 ? $stats['frameworks'] : '?'; ?></div>
            <div class="label">Framework Competenze</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['competencies'] >= 0 ? $stats['competencies'] : '?'; ?></div>
            <div class="label">Competenze Totali</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['questions_with_comp'] >= 0 ? $stats['questions_with_comp'] : '?'; ?></div>
            <div class="label">Domande con Competenze</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['quiz_attempts'] >= 0 ? $stats['quiz_attempts'] : '?'; ?></div>
            <div class="label">Quiz Completati</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['selfassessments'] >= 0 ? $stats['selfassessments'] : '?'; ?></div>
            <div class="label">Autovalutazioni</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['coach_notes'] >= 0 ? $stats['coach_notes'] : '?'; ?></div>
            <div class="label">Note Coach</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['courses_with_quiz'] >= 0 ? $stats['courses_with_quiz'] : '?'; ?></div>
            <div class="label">Corsi con Quiz</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo $stats['students_with_attempts'] >= 0 ? $stats['students_with_attempts'] : '?'; ?></div>
            <div class="label">Studenti con Quiz</div>
        </div>
    </div>
</div>

<?php
// ============================================================================
// 4. TEST FUNZIONALE - Calcolo Competenze
// ============================================================================
?>

<div class="check-card">
    <h3>🧪 4. Test Funzionale: Calcolo Competenze</h3>
    <p>Verifica che il sistema calcoli correttamente i punteggi delle competenze dai quiz.</p>
    <p><small class="status-info">ℹ️ Query aggiornata per Moodle 4.4+/4.5 (usa question_references)</small></p>
    <?php if ($selected_courseid > 0): ?>
    <p><small class="status-ok">✅ Test eseguito sul corso selezionato: <strong><?php echo format_string($selected_course->fullname); ?></strong></small></p>
    <?php endif; ?>
    
    <?php
    $results['total']++;
    
    // Trova un tentativo quiz - filtra per corso se selezionato
    $course_filter = $selected_courseid > 0 ? "AND q.course = {$selected_courseid}" : "";
    $test_attempt = $DB->get_record_sql("
        SELECT qa.id, qa.userid, qa.quiz, q.name as quiz_name, q.course, c.fullname as course_name
        FROM {quiz_attempts} qa
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {course} c ON c.id = q.course
        WHERE qa.state = 'finished' {$course_filter}
        ORDER BY qa.id DESC
        LIMIT 1
    ");
    
    if ($test_attempt) {
        // QUERY CORRETTA per Moodle 4.x - usa question_references
        $questions_with_competencies = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qc.questionid)
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id 
                AND qr.component = 'mod_quiz' 
                AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
            WHERE qs.quizid = ?
        ", [$test_attempt->quiz]);
        
        // Calcola punteggi competenze con query corretta
        $competency_scores = $DB->get_records_sql("
            SELECT 
                c.id,
                c.idnumber,
                c.shortname,
                COUNT(*) as total_questions,
                SUM(CASE WHEN qas.fraction >= 0.5 THEN 1 ELSE 0 END) as correct,
                ROUND(AVG(qas.fraction) * 100, 1) as percentage
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id AND qas.fraction IS NOT NULL
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE qa.id = ?
            GROUP BY c.id, c.idnumber, c.shortname
            ORDER BY c.idnumber
        ", [$test_attempt->id]);
        
        if (!empty($competency_scores)) {
            $results['passed']++;
            ?>
            <div class="plugin-status plugin-ok">
                <span class="plugin-icon">✅</span>
                <span class="plugin-name">Calcolo Competenze</span>
                <span class="plugin-result">FUNZIONA!</span>
            </div>
            
            <div class="test-section">
                <h4>📋 Esempio di calcolo (Tentativo #<?php echo $test_attempt->id; ?>)</h4>
                <p><strong>Quiz:</strong> <?php echo $test_attempt->quiz_name; ?></p>
                <p><strong>Corso:</strong> <?php echo $test_attempt->course_name; ?></p>
                <p><strong>Domande con competenze nel quiz:</strong> <?php echo $questions_with_competencies; ?></p>
                
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Competenza</th>
                            <th>Domande</th>
                            <th>Corrette</th>
                            <th>Percentuale</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($competency_scores, 0, 10) as $score): ?>
                        <tr>
                            <td><?php echo $score->idnumber; ?></td>
                            <td><?php echo $score->total_questions; ?></td>
                            <td><?php echo $score->correct; ?></td>
                            <td><?php echo $score->percentage; ?>%</td>
                            <td>
                                <?php if ($score->percentage >= 70): ?>
                                    <span class="status-ok">✅ Acquisita</span>
                                <?php elseif ($score->percentage >= 50): ?>
                                    <span class="status-warning">⚠️ In corso</span>
                                <?php else: ?>
                                    <span class="status-error">❌ Da migliorare</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($competency_scores) > 10): ?>
                        <tr><td colspan="5" style="text-align:center; color:#666;">... e altre <?php echo count($competency_scores) - 10; ?> competenze</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            $results['warnings']++;
            ?>
            <div class="plugin-status plugin-warning">
                <span class="plugin-icon">⚠️</span>
                <span class="plugin-name">Calcolo Competenze</span>
                <span class="plugin-result">Nessun dato da calcolare</span>
            </div>
            <p>Le domande del quiz non hanno competenze assegnate, oppure non ci sono risposte valide.</p>
            <p><strong>Quiz testato:</strong> <?php echo $test_attempt->quiz_name; ?> (ID: <?php echo $test_attempt->quiz; ?>)</p>
            <p><strong>Domande con competenze:</strong> <?php echo $questions_with_competencies; ?></p>
            <?php
        }
    } else {
        $results['warnings']++;
        ?>
        <div class="plugin-status plugin-warning">
            <span class="plugin-icon">⚠️</span>
            <span class="plugin-name">Calcolo Competenze</span>
            <span class="plugin-result">Nessun tentativo quiz trovato</span>
        </div>
        <p>Non ci sono tentativi quiz completati nel sistema. Usa il simulatore per creare dati di test.</p>
        <?php
    }
    ?>
</div>

<?php
// ============================================================================
// 5. TEST FUNZIONALE - Autovalutazioni
// ============================================================================
?>

<div class="check-card">
    <h3>🧪 5. Test Funzionale: Autovalutazioni</h3>
    <p>Verifica che il sistema delle autovalutazioni funzioni correttamente.</p>
    
    <?php
    $results['total']++;
    
    if (!table_exists('local_selfassessment')) {
        $results['failed']++;
        ?>
        <div class="plugin-status plugin-error">
            <span class="plugin-icon">❌</span>
            <span class="plugin-name">Tabella Autovalutazioni</span>
            <span class="plugin-result">MANCANTE!</span>
        </div>
        <p class="status-error">La tabella <code>mdl_local_selfassessment</code> non esiste.</p>
        <?php
    } else {
        $sa_count = count_records_safe('local_selfassessment');
        
        if ($sa_count > 0) {
            $results['passed']++;
            
            $sample_sa = $DB->get_records_sql("
                SELECT sa.*, u.firstname, u.lastname, c.idnumber, c.shortname
                FROM {local_selfassessment} sa
                JOIN {user} u ON u.id = sa.userid
                JOIN {competency} c ON c.id = sa.competencyid
                ORDER BY sa.timecreated DESC
                LIMIT 5
            ");
            ?>
            <div class="plugin-status plugin-ok">
                <span class="plugin-icon">✅</span>
                <span class="plugin-name">Sistema Autovalutazioni</span>
                <span class="plugin-result"><?php echo $sa_count; ?> autovalutazioni</span>
            </div>
            
            <div class="test-section">
                <h4>📋 Ultime autovalutazioni</h4>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Studente</th>
                            <th>Competenza</th>
                            <th>Livello Bloom</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_sa as $sa): 
                            $levels = [0 => '❓ Non valutato', 1 => '1️⃣ Ricordare', 2 => '2️⃣ Comprendere', 
                                       3 => '3️⃣ Applicare', 4 => '4️⃣ Analizzare', 5 => '5️⃣ Valutare', 6 => '6️⃣ Creare'];
                        ?>
                        <tr>
                            <td><?php echo $sa->firstname . ' ' . $sa->lastname; ?></td>
                            <td><?php echo $sa->idnumber ?: $sa->shortname; ?></td>
                            <td><?php echo isset($levels[$sa->level]) ? $levels[$sa->level] : "Livello {$sa->level}"; ?></td>
                            <td><?php echo date('d/m/Y H:i', $sa->timecreated); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            $results['warnings']++;
            ?>
            <div class="plugin-status plugin-warning">
                <span class="plugin-icon">⚠️</span>
                <span class="plugin-name">Sistema Autovalutazioni</span>
                <span class="plugin-result">Nessuna autovalutazione</span>
            </div>
            <p>La tabella esiste ma è vuota.</p>
            <?php
        }
    }
    ?>
</div>

<?php
// ============================================================================
// 6. TEST FUNZIONALE - Report e Grafici Radar
// ============================================================================
?>

<div class="check-card">
    <h3>🧪 6. Test Funzionale: Report e Grafici Radar</h3>
    <p>Verifica che i report e i grafici radar possano essere generati correttamente.</p>
    <?php if ($selected_courseid > 0): ?>
    <p><small class="status-ok">✅ Test eseguito sul corso selezionato: <strong><?php echo format_string($selected_course->fullname); ?></strong></small></p>
    <?php endif; ?>
    
    <?php
    $results['total']++;
    
    // Trova uno studente con dati - filtra per corso se selezionato
    $course_filter = $selected_courseid > 0 ? "AND q.course = {$selected_courseid}" : "";
    $student_with_data = $DB->get_record_sql("
        SELECT qa.userid, u.firstname, u.lastname, q.course, c.fullname as course_name,
               COUNT(DISTINCT qa.id) as attempts_count
        FROM {quiz_attempts} qa
        JOIN {user} u ON u.id = qa.userid
        JOIN {quiz} q ON q.id = qa.quiz
        JOIN {course} c ON c.id = q.course
        WHERE qa.state = 'finished' {$course_filter}
        GROUP BY qa.userid, u.firstname, u.lastname, q.course, c.fullname
        HAVING COUNT(DISTINCT qa.id) >= 1
        ORDER BY attempts_count DESC
        LIMIT 1
    ");
    
    if ($student_with_data) {
        // Verifica che ci siano dati per il radar (competenze con punteggi)
        $radar_data_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT c.id)
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE qa.userid = ? AND qa.state = 'finished'
        ", [$student_with_data->userid]);
        
        // Verifica aree per aggregazione radar
        $areas_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT SUBSTRING_INDEX(c.idnumber, '_', 2))
            FROM {quiz_attempts} qa
            JOIN {question_usages} qu ON qu.id = qa.uniqueid
            JOIN {question_attempts} qat ON qat.questionusageid = qu.id
            JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qat.questionid
            JOIN {competency} c ON c.id = qc.competencyid
            WHERE qa.userid = ? AND qa.state = 'finished'
        ", [$student_with_data->userid]);
        
        $results['passed']++;
        ?>
        <div class="plugin-status plugin-ok">
            <span class="plugin-icon">✅</span>
            <span class="plugin-name">Dati per Report e Radar</span>
            <span class="plugin-result">DISPONIBILI</span>
        </div>
        
        <div class="test-section">
            <h4>📊 Verifica Grafici Radar</h4>
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
                <div style="text-align: center;">
                    <div class="radar-preview <?php echo $radar_data_count > 0 ? 'radar-ok' : 'radar-error'; ?>"></div>
                    <p><strong>Radar Competenze</strong><br>
                    <?php echo $radar_data_count; ?> competenze</p>
                </div>
                
                <div style="text-align: center;">
                    <div class="radar-preview <?php echo $areas_count > 0 ? 'radar-ok' : 'radar-error'; ?>"></div>
                    <p><strong>Radar Aree</strong><br>
                    <?php echo $areas_count; ?> aree</p>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <table class="detail-table">
                        <tr>
                            <th>Componente</th>
                            <th>Stato</th>
                            <th>Dati</th>
                        </tr>
                        <tr>
                            <td>📊 Radar Competenze (competencymanager)</td>
                            <td><?php echo $radar_data_count > 0 ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ No dati</span>'; ?></td>
                            <td><?php echo $radar_data_count; ?> competenze</td>
                        </tr>
                        <tr>
                            <td>📈 Radar Aree (competencymanager)</td>
                            <td><?php echo $areas_count > 0 ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ No dati</span>'; ?></td>
                            <td><?php echo $areas_count; ?> aree</td>
                        </tr>
                        <tr>
                            <td>👨‍🏫 Confronto Coach (coachmanager)</td>
                            <td><?php echo $student_with_data ? '<span class="status-ok">✅ OK</span>' : '<span class="status-warning">⚠️ No studenti</span>'; ?></td>
                            <td>Studente: <?php echo $student_with_data->firstname; ?></td>
                        </tr>
                        <tr>
                            <td>📉 Grafico Progressi</td>
                            <td><?php echo $student_with_data->attempts_count > 0 ? '<span class="status-ok">✅ OK</span>' : '<span class="status-warning">⚠️ No tentativi</span>'; ?></td>
                            <td><?php echo $student_with_data->attempts_count; ?> tentativi</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <h4 style="margin-top: 20px;">📋 Studente di Test</h4>
            <p><strong>Nome:</strong> <?php echo $student_with_data->firstname . ' ' . $student_with_data->lastname; ?></p>
            <p><strong>Corso:</strong> <?php echo $student_with_data->course_name; ?> (ID: <?php echo $student_with_data->course; ?>)</p>
            <p><strong>Quiz completati:</strong> <?php echo $student_with_data->attempts_count; ?></p>
            
            <p style="margin-top: 15px;">
                <a href="student_report.php?courseid=<?php echo $student_with_data->course; ?>&userid=<?php echo $student_with_data->userid; ?>" 
                   class="btn btn-success" target="_blank">
                    📊 Apri Report con Radar
                </a>
                <a href="../coachmanager/index.php?courseid=<?php echo $student_with_data->course; ?>" 
                   class="btn btn-info" target="_blank">
                    👨‍🏫 Apri Coach Manager
                </a>
            </p>
        </div>
        <?php
    } else {
        $results['warnings']++;
        ?>
        <div class="plugin-status plugin-warning">
            <span class="plugin-icon">⚠️</span>
            <span class="plugin-name">Dati per Report e Radar</span>
            <span class="plugin-result">NESSUN DATO</span>
        </div>
        <p>Non ci sono studenti con quiz completati. I grafici radar non possono essere testati.</p>
        <?php
    }
    ?>
</div>

<?php
// ============================================================================
// 7. TEST FILE CLASSI PHP
// ============================================================================
?>

<div class="check-card">
    <h3>🧪 7. Test File Classi PHP</h3>
    <p>Verifica che le classi PHP principali esistano e siano accessibili.</p>
    
    <?php
    $class_files = [
        ['path' => '/local/competencymanager/classes/manager.php', 'name' => 'competencymanager\manager'],
        ['path' => '/local/competencymanager/classes/assessment_manager.php', 'name' => 'competencymanager\assessment_manager'],
        ['path' => '/local/competencymanager/classes/coach_manager.php', 'name' => 'competencymanager\coach_manager'],
        ['path' => '/local/competencymanager/classes/report_generator.php', 'name' => 'competencymanager\report_generator'],
        ['path' => '/local/selfassessment/classes/manager.php', 'name' => 'selfassessment\manager'],
        ['path' => '/local/labeval/classes/api.php', 'name' => 'labeval\api'],
        ['path' => '/question/bank/competenciesbyquestion/classes/local/manager.php', 'name' => 'qbank\manager'],
    ];
    
    foreach ($class_files as $class) {
        $results['total']++;
        $fullpath = $CFG->dirroot . $class['path'];
        
        if (file_exists($fullpath)) {
            $results['passed']++;
            ?>
            <div class="plugin-status plugin-ok">
                <span class="plugin-icon">✅</span>
                <span class="plugin-name"><?php echo $class['name']; ?></span>
                <span class="plugin-result">Presente</span>
            </div>
            <?php
        } else {
            $results['warnings']++;
            ?>
            <div class="plugin-status plugin-warning">
                <span class="plugin-icon">⚠️</span>
                <span class="plugin-name"><?php echo $class['name']; ?></span>
                <span class="plugin-result">Non trovato</span>
            </div>
            <?php
        }
    }
    ?>
</div>

<?php
// ============================================================================
// RIEPILOGO FINALE
// ============================================================================

$success_rate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100) : 0;
$overall_status = ($results['failed'] == 0) ? 'ok' : 'error';
?>

<div class="summary-box" style="<?php echo $overall_status == 'ok' ? '' : 'background: linear-gradient(135deg, #e74c3c, #c0392b);'; ?>">
    <?php if ($overall_status == 'ok'): ?>
        <h2>✅ SISTEMA FUNZIONANTE</h2>
        <p>Tutti i componenti critici sono installati e funzionanti!</p>
    <?php else: ?>
        <h2>❌ PROBLEMI RILEVATI</h2>
        <p>Alcuni componenti richiedono attenzione.</p>
    <?php endif; ?>
    
    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 30px;">
        <div>
            <div class="big-number"><?php echo $results['passed']; ?></div>
            <div>Test Passati</div>
        </div>
        <div>
            <div class="big-number"><?php echo $results['failed']; ?></div>
            <div>Test Falliti</div>
        </div>
        <div>
            <div class="big-number"><?php echo $results['warnings']; ?></div>
            <div>Avvisi</div>
        </div>
        <div>
            <div class="big-number"><?php echo $success_rate; ?>%</div>
            <div>Successo</div>
        </div>
    </div>
</div>

<div class="check-card">
    <h3>🔗 Link Rapidi</h3>
    <?php if ($selected_courseid > 0 && $selected_course): ?>
    <p class="status-ok">✅ Link configurati per il corso: <strong><?php echo format_string($selected_course->fullname); ?></strong> (ID: <?php echo $selected_courseid; ?>)</p>
    <?php else: ?>
    <p class="status-warning">⚠️ Seleziona un corso in alto per abilitare i link rapidi.</p>
    <?php endif; ?>
    
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
        <!-- Link che richiedono courseid -->
        <?php if ($selected_courseid > 0): ?>
        <a href="dashboard.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-primary">🏠 Dashboard</a>
        <a href="reports.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-success">📊 Report Classe</a>
        <a href="diagnostics.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-warning">🩺 Diagnostica Quiz</a>
        <a href="question_check.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-info">❓ Verifica Domande</a>
        <a href="../coachmanager/index.php?courseid=<?php echo $selected_courseid; ?>" class="btn btn-purple">👨‍🏫 Coach Manager</a>
        <?php else: ?>
        <span class="btn btn-disabled">🏠 Dashboard</span>
        <span class="btn btn-disabled">📊 Report Classe</span>
        <span class="btn btn-disabled">🩺 Diagnostica Quiz</span>
        <span class="btn btn-disabled">❓ Verifica Domande</span>
        <span class="btn btn-disabled">👨‍🏫 Coach Manager</span>
        <?php endif; ?>
        
        <!-- Link che NON richiedono courseid -->
        <a href="../ftm_hub/index.php" class="btn btn-purple">🛠️ FTM Hub</a>
        <a href="?<?php echo $selected_courseid > 0 ? 'courseid=' . $selected_courseid : ''; ?>" class="btn btn-success">🔄 Riesegui Test</a>
    </div>
</div>

</div>

<?php
echo $OUTPUT->footer();
