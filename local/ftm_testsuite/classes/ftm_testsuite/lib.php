<?php
/**
 * Library functions for FTM Test Suite
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Aggiunge navigazione al plugin
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 */
function local_ftm_testsuite_extend_navigation_course($parentnode, $course, $context) {
    // Non aggiungiamo navigazione a livello corso
}

/**
 * Aggiunge link alla navigazione admin
 *
 * @param settings_navigation $nav
 * @param context $context
 */
function local_ftm_testsuite_extend_settings_navigation($nav, $context) {
    global $PAGE;
    
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return;
    }
    
    if (!has_capability('local/ftm_testsuite:manage', $context)) {
        return;
    }
    
    $pluginname = get_string('pluginname', 'local_ftm_testsuite');
    
    if ($settingsnode = $nav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        $url = new moodle_url('/local/ftm_testsuite/index.php');
        $node = navigation_node::create(
            $pluginname,
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'ftm_testsuite',
            new pix_icon('i/settings', '')
        );
        $settingsnode->add_node($node);
    }
}

/**
 * Definisce le pagine del plugin per la navigazione
 */
function local_ftm_testsuite_get_navigation_pages() {
    return [
        'index' => [
            'url' => '/local/ftm_testsuite/index.php',
            'title' => 'Dashboard',
            'icon' => 'i/dashboard'
        ],
        'generate' => [
            'url' => '/local/ftm_testsuite/generate.php',
            'title' => 'Genera Dati',
            'icon' => 'i/import'
        ],
        'run' => [
            'url' => '/local/ftm_testsuite/run.php',
            'title' => 'Esegui Test',
            'icon' => 'i/checkpermissions'
        ],
        'results' => [
            'url' => '/local/ftm_testsuite/results.php',
            'title' => 'Risultati',
            'icon' => 'i/report'
        ],
        'cleanup' => [
            'url' => '/local/ftm_testsuite/cleanup.php',
            'title' => 'Pulizia',
            'icon' => 'i/trash'
        ]
    ];
}

/**
 * Ottiene le statistiche rapide del sistema
 *
 * @return array
 */
function local_ftm_testsuite_get_quick_stats() {
    global $DB;
    
    return [
        'courses' => $DB->count_records_sql("SELECT COUNT(DISTINCT course) FROM {quiz}"),
        'quizzes' => $DB->count_records('quiz'),
        'competencies' => $DB->count_records('competency'),
        'test_runs' => $DB->count_records('local_ftm_testsuite_runs'),
        'test_users' => $DB->count_records('local_ftm_testsuite_users')
    ];
}

/**
 * Funzione di utilità per formattare lo stato del test
 *
 * @param string $status
 * @return array [icon, color, label]
 */
function local_ftm_testsuite_format_status($status) {
    $formats = [
        'passed' => ['✓', '#28a745', 'Passato'],
        'failed' => ['✗', '#dc3545', 'Fallito'],
        'warning' => ['!', '#ffc107', 'Warning'],
        'skipped' => ['–', '#6c757d', 'Saltato'],
        'running' => ['↻', '#17a2b8', 'In esecuzione'],
        'completed' => ['✓', '#28a745', 'Completato']
    ];
    
    return $formats[$status] ?? ['?', '#6c757d', 'Sconosciuto'];
}

/**
 * Calcola la percentuale di successo
 *
 * @param int $passed
 * @param int $total
 * @return float
 */
function local_ftm_testsuite_success_rate($passed, $total) {
    if ($total <= 0) {
        return 0;
    }
    return round(($passed / $total) * 100, 2);
}

/**
 * Genera un nome univoco per il run
 *
 * @param string $prefix
 * @return string
 */
function local_ftm_testsuite_generate_run_name($prefix = 'Test') {
    return $prefix . ' ' . date('d/m/Y H:i:s');
}

/**
 * Verifica se il plugin è configurato correttamente
 *
 * @return array Lista di problemi trovati
 */
function local_ftm_testsuite_check_configuration() {
    global $DB;
    
    $issues = [];
    
    // Verifica tabelle richieste
    $required_tables = [
        'quiz',
        'quiz_attempts',
        'question_attempts',
        'question_attempt_steps',
        'competency',
        'qbank_competenciesbyquestion',
        'local_selfassessment',
        'local_labeval_templates',
        'local_competencymanager_auth'
    ];
    
    $dbman = $DB->get_manager();
    foreach ($required_tables as $table) {
        if (!$dbman->table_exists($table)) {
            $issues[] = "Tabella mancante: {$table}";
        }
    }
    
    // Verifica competenze
    $comp_count = $DB->count_records('competency');
    if ($comp_count == 0) {
        $issues[] = "Nessuna competenza definita nel sistema";
    }
    
    // Verifica quiz con competenze
    $quiz_with_comp = $DB->count_records_sql("
        SELECT COUNT(DISTINCT q.id)
        FROM {quiz} q
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {question_references} qr ON qr.itemid = qs.id
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    ");
    
    if ($quiz_with_comp == 0) {
        $issues[] = "Nessun quiz con competenze assegnate alle domande";
    }
    
    return $issues;
}

/**
 * Hook per l'installazione del plugin
 */
function local_ftm_testsuite_install() {
    // Nessuna azione specifica richiesta all'installazione
    return true;
}

/**
 * Hook per la disinstallazione del plugin
 */
function local_ftm_testsuite_uninstall() {
    global $DB;
    
    // Elimina tutti gli utenti test creati
    $testusers = $DB->get_records('local_ftm_testsuite_users');
    foreach ($testusers as $tu) {
        // Non eliminiamo gli utenti Moodle, solo i riferimenti
    }
    
    return true;
}
