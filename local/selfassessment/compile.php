<?php
// ============================================
// Self Assessment - Form Compilazione Studente
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();

// Forza lingua italiana per questo plugin
force_current_language('it');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/selfassessment/compile.php'));
$PAGE->set_title(get_string('compile_title', 'local_selfassessment'));
$PAGE->set_heading(get_string('compile_title', 'local_selfassessment'));
$PAGE->set_pagelayout('standard');

// Password per skippare l'autovalutazione (definite in lib.php con guard, evita ridefinizione)
if (!defined('SELFASSESSMENT_SKIP_TEMP')) {
    define('SELFASSESSMENT_SKIP_TEMP', '6807');
}
if (!defined('SELFASSESSMENT_SKIP_PERMANENT')) {
    define('SELFASSESSMENT_SKIP_PERMANENT', 'FTM');
}

// Verifica permessi
require_capability('local/selfassessment:complete', $context);

// Verifica se abilitato
if (!local_selfassessment_is_enabled($USER->id)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('disabled_message', 'local_selfassessment'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Manager
$manager = new \local_selfassessment\manager();

// Verifica se lo studente ha competenze assegnate
$assigned_competencies = $manager->get_assigned_competencies($USER->id);

// SAFETY NET: Se non ci sono assegnazioni, prova assegnazione retroattiva
// Questo copre i casi in cui l'observer non ha funzionato (errori, versioning, ecc.)
if (empty($assigned_competencies)) {
    require_once(__DIR__ . '/classes/observer.php');
    $retroactive_count = \local_selfassessment\observer::retroactive_assign($USER->id);

    if ($retroactive_count > 0) {
        // Ricarica le competenze appena assegnate
        $assigned_competencies = $manager->get_assigned_competencies($USER->id);
    }
}

// Se ancora non ha assegnazioni, mostra messaggio con diagnostica
if (empty($assigned_competencies)) {
    echo $OUTPUT->header();

    // Diagnostica: controlla se lo studente ha quiz completati
    $quiz_count = $DB->count_records_sql("
        SELECT COUNT(DISTINCT qa.quiz)
        FROM {quiz_attempts} qa
        WHERE qa.userid = ? AND qa.state = 'finished'
    ", [$USER->id]);

    $attempt_count = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {quiz_attempts} qa
        WHERE qa.userid = ? AND qa.state = 'finished'
    ", [$USER->id]);

    ?>
    <div style="max-width: 800px; margin: 50px auto; text-align: center; padding: 40px;">
        <div style="font-size: 4em; margin-bottom: 20px;">📭</div>
        <h2 style="color: #2c3e50; margin-bottom: 15px;">Nessuna competenza da autovalutare</h2>
        <p style="color: #7f8c8d; font-size: 1.1em;">
            Al momento non hai competenze assegnate per l'autovalutazione.<br>
            Le competenze vengono assegnate automaticamente quando completi i quiz,<br>
            oppure possono essere assegnate dal tuo coach.
        </p>
        <?php if ($quiz_count > 0): ?>
        <div style="margin-top: 20px; padding: 20px; background: #fff3e0; border-radius: 12px; border-left: 4px solid #ff9800;">
            <strong>⚠️ Attenzione:</strong> Risultano <?php echo $attempt_count; ?> tentativi completati su <?php echo $quiz_count; ?> quiz,
            ma nessuna competenza e' stata assegnata. Questo puo' indicare che i quiz non hanno competenze associate.
            Contatta il tuo coach o l'amministratore.
        </div>
        <?php else: ?>
        <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 12px;">
            <strong>💡 Suggerimento:</strong> Completa i quiz del tuo corso per sbloccare l'autovalutazione!
        </div>
        <?php endif; ?>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// Organizza competenze assegnate per area
// MAPPING COMPLETO - Tutti i settori formativi
$areas = [];
$area_map = [
    // ============================================
    // AUTOMOBILE
    // ============================================
    'AUTOMOBILE_MAu' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db'],
    'AUTOMOBILE_MR' => ['nome' => 'Manutenzione e Riparazione Auto', 'icona' => '🔧', 'colore' => '#e74c3c'],
    'AUTOMOBILE' => ['nome' => 'Automobile', 'icona' => '🚗', 'colore' => '#3498db'],

    // ============================================
    // MECCANICA
    // ============================================
    'MECCANICA_ASS' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12'],
    'MECCANICA_AUT' => ['nome' => 'Automazione Meccanica', 'icona' => '🤖', 'colore' => '#e74c3c'],
    'MECCANICA_CNC' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4'],
    'MECCANICA_CSP' => ['nome' => 'Collaborazione', 'icona' => '🤝', 'colore' => '#8e44ad'],
    'MECCANICA_DIS' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db'],
    'MECCANICA_DT' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db'],
    'MECCANICA_LAV' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e'],
    'MECCANICA_LMC' => ['nome' => 'Lavorazioni Macchine', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'MECCANICA_LMB' => ['nome' => 'Lavorazioni Manuali', 'icona' => '🔧', 'colore' => '#795548'],
    'MECCANICA_MAN' => ['nome' => 'Manutenzione Meccanica', 'icona' => '🔨', 'colore' => '#e67e22'],
    'MECCANICA_MIS' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c'],
    'MECCANICA_PIA' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6'],
    'MECCANICA_PRO' => ['nome' => 'Programmazione', 'icona' => '💻', 'colore' => '#2ecc71'],
    'MECCANICA_PRG' => ['nome' => 'Programmazione', 'icona' => '💻', 'colore' => '#2ecc71'],  // Altro formato
    'MECCANICA_SIC' => ['nome' => 'Sicurezza e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'MECCANICA_SAQ' => ['nome' => 'Sicurezza e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b'],  // Altro formato
    'MECCANICA_PIAN' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6'],  // Formato esteso
    'MECCANICA_GEN' => ['nome' => 'Meccanica Generale', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'MECCANICA' => ['nome' => 'Meccanica', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'MECC_' => ['nome' => 'Meccanica', 'icona' => '⚙️', 'colore' => '#607d8b'],

    // ============================================
    // LOGISTICA
    // ============================================
    'LOGISTICA_LO' => ['nome' => 'Logistica', 'icona' => '📦', 'colore' => '#ff9800'],
    'LOGISTICA' => ['nome' => 'Logistica', 'icona' => '📦', 'colore' => '#ff9800'],

    // ============================================
    // AUTOMAZIONE
    // ============================================
    'AUTOMAZIONE_' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#673ab7'],
    'AUTOMAZIONE' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#673ab7'],
    'AUTO_EA' => ['nome' => 'Elettronica e Automazione', 'icona' => '⚡', 'colore' => '#673ab7'],

    // ============================================
    // ELETTRONICA / ELETTRICITA
    // ============================================
    'ELETTRONICA_' => ['nome' => 'Elettronica', 'icona' => '🔌', 'colore' => '#2196f3'],
    'ELETTRONICA' => ['nome' => 'Elettronica', 'icona' => '🔌', 'colore' => '#2196f3'],
    'ELETTRICITÀ_' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],  // Con accento
    'ELETTRICITÀ' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],   // Con accento
    'ELETTRICITA_' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],  // Senza accento
    'ELETTRICITA' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],   // Senza accento
    'ELET_' => ['nome' => 'Elettrotecnica', 'icona' => '⚡', 'colore' => '#ff9800'],

    // ============================================
    // CHIMICA / FARMACEUTICA
    // ============================================
    'CHIMFARM_' => ['nome' => 'Chimica Farmaceutica', 'icona' => '🧪', 'colore' => '#9c27b0'],
    'CHIMFARM' => ['nome' => 'Chimica Farmaceutica', 'icona' => '🧪', 'colore' => '#9c27b0'],
    'CHIMICA_' => ['nome' => 'Chimica', 'icona' => '⚗️', 'colore' => '#673ab7'],
    'CHIMICA' => ['nome' => 'Chimica', 'icona' => '⚗️', 'colore' => '#673ab7'],
    'FARMACEUTICA' => ['nome' => 'Farmaceutica', 'icona' => '💊', 'colore' => '#e91e63'],

    // ============================================
    // METALCOSTRUZIONE
    // ============================================
    'METALCOSTRUZIONE_' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#455a64'],
    'METALCOSTRUZIONE' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#455a64'],
    'METAL_' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#455a64'],

    // ============================================
    // INFORMATICA
    // ============================================
    'INFORMATICA_' => ['nome' => 'Informatica', 'icona' => '💻', 'colore' => '#00bcd4'],
    'INFORMATICA' => ['nome' => 'Informatica', 'icona' => '💻', 'colore' => '#00bcd4'],
    'ICT_' => ['nome' => 'ICT', 'icona' => '🖥️', 'colore' => '#00bcd4'],

    // ============================================
    // SICUREZZA
    // ============================================
    'SICUREZZA_' => ['nome' => 'Sicurezza', 'icona' => '🛡️', 'colore' => '#f44336'],
    'SICUREZZA' => ['nome' => 'Sicurezza', 'icona' => '🛡️', 'colore' => '#f44336'],

    // ============================================
    // COMPETENZE GENERICHE / TRASVERSALI
    // ============================================
    'GEN_' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],  // GEN_A, GEN_B, etc.
    'GEN' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'GENERICO_' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'GENERICO' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'GENERICHE' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'TRASVERSALI' => ['nome' => 'Competenze Trasversali', 'icona' => '🎯', 'colore' => '#607d8b'],
    'SOFT_' => ['nome' => 'Soft Skills', 'icona' => '🤝', 'colore' => '#9c27b0'],

    // ============================================
    // EDILIZIA / COSTRUZIONI
    // ============================================
    'EDILIZIA_' => ['nome' => 'Edilizia', 'icona' => '🏗️', 'colore' => '#795548'],
    'EDILIZIA' => ['nome' => 'Edilizia', 'icona' => '🏗️', 'colore' => '#795548'],
    'COSTRUZIONI' => ['nome' => 'Costruzioni', 'icona' => '🏗️', 'colore' => '#8d6e63'],

    // ============================================
    // RISTORAZIONE / ALIMENTARE
    // ============================================
    'RISTORAZIONE' => ['nome' => 'Ristorazione', 'icona' => '🍽️', 'colore' => '#ff5722'],
    'ALIMENTARE' => ['nome' => 'Settore Alimentare', 'icona' => '🥗', 'colore' => '#4caf50'],
    'CUCINA' => ['nome' => 'Cucina', 'icona' => '👨‍🍳', 'colore' => '#ff9800'],

    // ============================================
    // COMMERCIO / VENDITA
    // ============================================
    'COMMERCIO' => ['nome' => 'Commercio', 'icona' => '🛒', 'colore' => '#3f51b5'],
    'VENDITA' => ['nome' => 'Vendita', 'icona' => '💰', 'colore' => '#4caf50'],

    // ============================================
    // OLD_ prefixes (vecchie competenze legacy)
    // ============================================
    'OLD_LOGISTICA' => ['nome' => 'Logistica', 'icona' => '📦', 'colore' => '#ff9800'],
    'OLD_MECCANICA' => ['nome' => 'Meccanica', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'OLD_AUTOMOBILE' => ['nome' => 'Automobile', 'icona' => '🚗', 'colore' => '#3498db'],
    'OLD_CHIMFARM' => ['nome' => 'Chimica Farmaceutica', 'icona' => '🧪', 'colore' => '#9c27b0'],
    'OLD_CHIMICA' => ['nome' => 'Chimica', 'icona' => '⚗️', 'colore' => '#673ab7'],
    'OLD_ELETTRONICA' => ['nome' => 'Elettronica', 'icona' => '🔌', 'colore' => '#2196f3'],
    'OLD_ELETTRICITA' => ['nome' => 'Elettricità', 'icona' => '💡', 'colore' => '#ffc107'],
    'OLD_AUTOMAZIONE' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#673ab7'],
    'OLD_METALCOSTRUZIONE' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#455a64'],
    'OLD_INFORMATICA' => ['nome' => 'Informatica', 'icona' => '💻', 'colore' => '#00bcd4'],
    'OLD_GENERICO' => ['nome' => 'Competenze Generiche', 'icona' => '📚', 'colore' => '#795548'],
    'OLD_02' => ['nome' => 'Legacy', 'icona' => '📁', 'colore' => '#9e9e9e'],  // Formato OLD_02-04, etc.
    'OLD_' => ['nome' => 'Legacy', 'icona' => '📁', 'colore' => '#9e9e9e'],
];

foreach ($assigned_competencies as $comp) {
    $found_area = null;
    foreach ($area_map as $prefix => $info) {
        if (strpos($comp->idnumber, $prefix) === 0) {
            $found_area = $prefix;
            break;
        }
    }
    
    if (!$found_area) {
        $found_area = 'ALTRO';
        if (!isset($area_map['ALTRO'])) {
            $area_map['ALTRO'] = ['nome' => 'Altro', 'icona' => '📁', 'colore' => '#95a5a6'];
        }
    }
    
    if (!isset($areas[$found_area])) {
        $areas[$found_area] = [
            'info' => $area_map[$found_area],
            'competenze' => []
        ];
    }
    
    $areas[$found_area]['competenze'][] = $comp;
}

// Conta totale competenze assegnate
$total_assigned = count($assigned_competencies);

// Carica autovalutazioni esistenti
$existing = $manager->get_user_assessments($USER->id);
$existing_by_comp = [];
foreach ($existing as $e) {
    $existing_by_comp[$e->competencyid] = $e->level;
}

// Conta quante competenze assegnate sono state valutate
$assigned_ids = array_column($assigned_competencies, 'competencyid');
$completed_count = 0;
foreach ($assigned_ids as $compid) {
    if (isset($existing_by_comp[$compid]) && $existing_by_comp[$compid] > 0) {
        $completed_count++;
    }
}
$all_completed = ($completed_count >= $total_assigned);

// Verifica se l'utente ha uno skip permanente
$permanent_skip = $DB->get_field('local_selfassessment_status', 'skip_accepted', ['userid' => $USER->id]);
$show_blocking_modal = !$all_completed && !$permanent_skip;

// Livelli Bloom
$bloom_levels = local_selfassessment_get_bloom_levels();

echo $OUTPUT->header();
?>

<style>
/* ============================================
   SELF ASSESSMENT STYLES
   ============================================ */
.sa-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.sa-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    text-align: center;
}

.sa-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
}

.sa-header p {
    margin: 0;
    opacity: 0.9;
}

.sa-progress {
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    padding: 15px 25px;
    margin-top: 20px;
    display: inline-block;
}

.sa-progress-bar {
    background: rgba(255,255,255,0.3);
    border-radius: 10px;
    height: 10px;
    width: 200px;
    margin-top: 8px;
}

.sa-progress-fill {
    background: white;
    border-radius: 10px;
    height: 100%;
    transition: width 0.3s ease;
}

/* Bloom Legend - Versione Dettagliata Collassabile */
.bloom-legend {
    background: white;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.bloom-legend-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 18px 25px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: filter 0.2s ease;
}

.bloom-legend-header:hover {
    filter: brightness(1.05);
}

.bloom-legend-header h3 {
    margin: 0;
    font-size: 1.15em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bloom-legend-header .toggle-arrow {
    font-size: 1.2em;
    transition: transform 0.3s ease;
}

.bloom-legend.open .toggle-arrow {
    transform: rotate(180deg);
}

.bloom-legend-body {
    display: none;
    padding: 25px;
    background: #fafbfc;
}

.bloom-legend.open .bloom-legend-body {
    display: block;
}

.bloom-intro {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 0 10px 10px 0;
    font-size: 0.95em;
    line-height: 1.6;
    color: #1565c0;
}

.bloom-intro strong {
    display: block;
    margin-bottom: 8px;
    font-size: 1.05em;
}

.bloom-levels-detailed {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.bloom-level-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border-left: 5px solid;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.bloom-level-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.bloom-level-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.bloom-level-number {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4em;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}

.bloom-level-title {
    font-size: 1.15em;
    font-weight: 700;
    color: #2c3e50;
}

.bloom-level-subtitle {
    font-size: 0.9em;
    color: #7f8c8d;
    margin-top: 2px;
}

.bloom-level-description {
    color: #555;
    line-height: 1.6;
    margin-bottom: 15px;
    font-size: 0.95em;
}

.bloom-level-examples {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.bloom-level-examples strong {
    display: block;
    color: #2c3e50;
    margin-bottom: 10px;
    font-size: 0.9em;
}

.bloom-level-examples ul {
    margin: 0;
    padding-left: 20px;
}

.bloom-level-examples li {
    color: #666;
    margin-bottom: 6px;
    font-size: 0.9em;
    line-height: 1.5;
}

.bloom-level-examples li:last-child {
    margin-bottom: 0;
}

.bloom-quick-ref {
    margin-top: 20px;
    padding: 15px 20px;
    background: linear-gradient(135deg, #fff9c4 0%, #fff59d 100%);
    border-radius: 10px;
    border: 1px solid #ffd54f;
}

.bloom-quick-ref strong {
    display: block;
    color: #f57f17;
    margin-bottom: 10px;
}

.bloom-quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
}

.bloom-quick-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85em;
}

.bloom-quick-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.8em;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .bloom-level-header {
        flex-direction: column;
        text-align: center;
    }
    .bloom-quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Area Card */
.area-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.area-header {
    padding: 20px 25px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.area-header:hover {
    filter: brightness(1.05);
}

.area-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.area-title .icon {
    font-size: 1.5em;
}

.area-title h2 {
    margin: 0;
    font-size: 1.2em;
    font-weight: 600;
}

.area-title .count {
    font-size: 0.85em;
    opacity: 0.9;
}

.area-toggle {
    font-size: 1.2em;
    transition: transform 0.3s ease;
}

.area-card.open .area-toggle {
    transform: rotate(180deg);
}

.area-body {
    display: none;
    padding: 20px 25px;
    border-top: 1px solid #eee;
}

.area-card.open .area-body {
    display: block;
}

/* Competency Row */
.comp-row {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 12px;
}

.comp-row:last-child {
    margin-bottom: 0;
}

.comp-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.comp-code {
    font-size: 0.8em;
    color: #7f8c8d;
    margin-bottom: 12px;
}

/* Bloom Selector */
.bloom-selector {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.bloom-option {
    flex: 1;
    min-width: 100px;
}

.bloom-option input {
    display: none;
}

.bloom-option label {
    display: block;
    padding: 10px;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
}

.bloom-option label:hover {
    border-color: #adb5bd;
    transform: translateY(-2px);
}

.bloom-option input:checked + label {
    border-color: var(--bloom-color);
    background: var(--bloom-bg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.bloom-option .level-num {
    font-size: 1.2em;
    font-weight: 700;
    display: block;
}

.bloom-option .level-name {
    font-size: 0.7em;
    text-transform: uppercase;
    color: #6c757d;
}

/* Save Button */
.sa-footer {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 20px;
    border-top: 1px solid #eee;
    text-align: center;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
    margin-top: 30px;
    border-radius: 16px 16px 0 0;
}

.btn-save {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px 50px;
    font-size: 1.1em;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.btn-save:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Notification */
.sa-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    background: #28a745;
    color: white;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    z-index: 9999;
    display: none;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Responsive */
@media (max-width: 768px) {
    .bloom-selector {
        flex-direction: column;
    }
    .bloom-option {
        min-width: 100%;
    }
}

/* ============================================
   BLOCKING MODAL OVERLAY
   ============================================ */
.sa-blocking-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.sa-blocking-modal {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalBounce 0.5s ease;
}

@keyframes modalBounce {
    0% { transform: scale(0.8); opacity: 0; }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}

.sa-blocking-modal .modal-icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.sa-blocking-modal h2 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 1.5em;
}

.sa-blocking-modal p {
    color: #7f8c8d;
    margin-bottom: 25px;
    line-height: 1.6;
}

.sa-blocking-modal .btn-continue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px 40px;
    font-size: 1.1em;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    width: 100%;
    margin-bottom: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.sa-blocking-modal .btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.sa-blocking-modal .skip-section {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 10px;
}

.sa-blocking-modal .skip-section p {
    font-size: 0.9em;
    margin-bottom: 15px;
    color: #95a5a6;
}

.sa-blocking-modal .skip-input-group {
    display: flex;
    gap: 10px;
}

.sa-blocking-modal .skip-input {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 1em;
    text-align: center;
    letter-spacing: 3px;
}

.sa-blocking-modal .skip-input:focus {
    border-color: #667eea;
    outline: none;
}

.sa-blocking-modal .btn-skip {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s ease;
}

.sa-blocking-modal .btn-skip:hover {
    background: #7f8c8d;
}

.sa-blocking-modal .skip-error {
    color: #e74c3c;
    font-size: 0.85em;
    margin-top: 10px;
    display: none;
}

.sa-blocking-modal .competency-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    display: inline-block;
    margin-bottom: 20px;
    font-weight: 600;
}
</style>

<?php if ($show_blocking_modal): ?>
<!-- BLOCKING MODAL - Obbliga lo studente a completare l'autovalutazione -->
<div class="sa-blocking-overlay" id="blockingModal">
    <div class="sa-blocking-modal">
        <div class="modal-icon">📋</div>
        <h2>Autovalutazione Obbligatoria</h2>
        <div class="competency-count">
            <?php
            $remaining = $total_assigned - $completed_count;
            echo $remaining . ' competenze da valutare';
            if ($completed_count > 0) {
                echo ' <span style="font-size: 0.8em; opacity: 0.8;">(' . $completed_count . '/' . $total_assigned . ' completate)</span>';
            }
            ?>
        </div>
        <p>
            Prima di continuare, devi completare l'autovalutazione delle tue competenze.<br>
            Questo aiuterà il tuo coach a capire dove hai bisogno di supporto.
        </p>
        <button class="btn-continue" onclick="closeBlockingModal()">
            ✅ Compila Autovalutazione
        </button>

        <div class="skip-section">
            <p>Hai un codice di bypass?</p>
            <div class="skip-input-group">
                <input type="text" class="skip-input" id="skipPassword" placeholder="Codice" maxlength="4" style="text-transform: uppercase;">
                <button class="btn-skip" onclick="trySkip()">Salta</button>
            </div>
            <div class="skip-error" id="skipError">❌ Codice non valido</div>
            <div class="skip-info" id="skipInfo" style="display: none; color: #27ae60; font-size: 0.85em; margin-top: 10px;"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="sa-container">
    <!-- Header -->
    <div class="sa-header">
        <h1>📋 <?php echo get_string('compile_title', 'local_selfassessment'); ?></h1>
        <p><?php echo get_string('instructions', 'local_selfassessment'); ?></p>
        
        <div class="sa-progress">
            <span id="progressText">0 / <?php echo $total_assigned; ?> competenze valutate</span>
            <div class="sa-progress-bar">
                <div class="sa-progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
        </div>
    </div>
    
    <!-- Bloom Legend - Versione Dettagliata -->
    <div class="bloom-legend" id="bloomLegend">
        <div class="bloom-legend-header" onclick="toggleBloomLegend()">
            <h3>📊 Come valutare le tue competenze? (clicca per aprire)</h3>
            <span class="toggle-arrow">▼</span>
        </div>
        <div class="bloom-legend-body">
            <div class="bloom-intro">
                <strong>💡 Come funziona questa autovalutazione?</strong>
                Devi valutare quanto conosci ogni argomento usando una scala da 1 a 6.
                Non preoccuparti: non è un esame! Serve solo a capire dove sei forte e dove hai bisogno di aiuto.
                <strong>Sii onesto</strong> - più sei sincero, più il tuo coach potrà aiutarti nel modo giusto.
            </div>

            <div class="bloom-levels-detailed">
                <!-- LIVELLO 1 - RICORDO -->
                <div class="bloom-level-card" style="border-color: #e74c3c;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #e74c3c;">1</div>
                        <div>
                            <div class="bloom-level-title">RICORDO</div>
                            <div class="bloom-level-subtitle">So di cosa si parla, conosco i termini base</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Hai sentito parlare dell'argomento e ricordi alcune informazioni di base.
                        Se qualcuno ti chiede qualcosa, riesci a dire "sì, so cos'è" ma non molto di più.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>So che esiste il calibro, ma non ricordo bene come si usa</li>
                            <li>Ho sentito parlare del tornio durante il corso</li>
                            <li>Conosco i nomi dei principali attrezzi ma non sempre so a cosa servono</li>
                            <li>Ricordo che esistono norme di sicurezza ma non le so elencare</li>
                        </ul>
                    </div>
                </div>

                <!-- LIVELLO 2 - COMPRENDO -->
                <div class="bloom-level-card" style="border-color: #e67e22;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #e67e22;">2</div>
                        <div>
                            <div class="bloom-level-title">COMPRENDO</div>
                            <div class="bloom-level-subtitle">Capisco il significato e posso spiegarlo</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Non solo sai che qualcosa esiste, ma capisci anche <strong>perché</strong> funziona così e <strong>a cosa serve</strong>.
                        Potresti spiegarlo a un compagno con parole tue.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>Capisco perché si usa il calibro: per misurare con precisione</li>
                            <li>So spiegare a cosa serve la lubrificazione nelle macchine</li>
                            <li>Capisco perché bisogna indossare i DPI (guanti, occhiali...)</li>
                            <li>Posso descrivere le differenze tra due attrezzi simili</li>
                        </ul>
                    </div>
                </div>

                <!-- LIVELLO 3 - APPLICO -->
                <div class="bloom-level-card" style="border-color: #f1c40f;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #f1c40f; color: #333;">3</div>
                        <div>
                            <div class="bloom-level-title">APPLICO</div>
                            <div class="bloom-level-subtitle">So usare le conoscenze nella pratica</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Riesci a <strong>fare le cose in pratica</strong>, seguendo le istruzioni o procedure standard.
                        Se ti dicono cosa fare, lo sai fare correttamente.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>So usare il calibro e leggere correttamente la misura</li>
                            <li>Riesco a montare un pezzo seguendo il disegno tecnico</li>
                            <li>So eseguire una saldatura base rispettando la procedura</li>
                            <li>Riesco a compilare correttamente un modulo di lavoro</li>
                        </ul>
                    </div>
                </div>

                <!-- LIVELLO 4 - ANALIZZO -->
                <div class="bloom-level-card" style="border-color: #27ae60;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #27ae60;">4</div>
                        <div>
                            <div class="bloom-level-title">ANALIZZO</div>
                            <div class="bloom-level-subtitle">So scomporre i problemi e trovare le cause</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Quando qualcosa non funziona, riesci a capire <strong>dove sta il problema</strong>.
                        Sai distinguere cosa è importante da cosa non lo è.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>Se una misura è sbagliata, capisco se è colpa dello strumento o mia</li>
                            <li>So identificare quale componente causa un malfunzionamento</li>
                            <li>Riesco a capire perché un pezzo è venuto difettoso</li>
                            <li>So leggere un disegno complesso e capire l'ordine delle lavorazioni</li>
                        </ul>
                    </div>
                </div>

                <!-- LIVELLO 5 - VALUTO -->
                <div class="bloom-level-card" style="border-color: #3498db;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #3498db;">5</div>
                        <div>
                            <div class="bloom-level-title">VALUTO</div>
                            <div class="bloom-level-subtitle">So giudicare la qualità e scegliere la soluzione migliore</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Non solo trovi i problemi, ma sai anche <strong>quale soluzione è la migliore</strong> tra diverse opzioni.
                        Riesci a giudicare se un lavoro è fatto bene o male.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>So dire se un pezzo rispetta gli standard di qualità richiesti</li>
                            <li>Posso scegliere l'utensile più adatto tra diversi disponibili</li>
                            <li>So valutare se una procedura è sicura o se ci sono rischi</li>
                            <li>Riesco a decidere se un macchinario ha bisogno di manutenzione</li>
                        </ul>
                    </div>
                </div>

                <!-- LIVELLO 6 - CREO -->
                <div class="bloom-level-card" style="border-color: #9b59b6;">
                    <div class="bloom-level-header">
                        <div class="bloom-level-number" style="background: #9b59b6;">6</div>
                        <div>
                            <div class="bloom-level-title">CREO</div>
                            <div class="bloom-level-subtitle">So progettare soluzioni nuove e migliorare quelle esistenti</div>
                        </div>
                    </div>
                    <div class="bloom-level-description">
                        Sei in grado di <strong>inventare qualcosa di nuovo</strong> o migliorare significativamente
                        un processo esistente. Puoi insegnare agli altri e proporre innovazioni.
                    </div>
                    <div class="bloom-level-examples">
                        <strong>📝 Esempi pratici:</strong>
                        <ul>
                            <li>Ho ideato un metodo più veloce per fare una lavorazione</li>
                            <li>Posso progettare un pezzo da zero partendo dalle specifiche</li>
                            <li>So creare procedure di lavoro per gli altri colleghi</li>
                            <li>Riesco a combinare tecniche diverse per risolvere problemi nuovi</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Riferimento rapido -->
            <div class="bloom-quick-ref">
                <strong>🎯 Riferimento rapido - In sintesi:</strong>
                <div class="bloom-quick-grid">
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #e74c3c;">1</span>
                        <span>Ne ho sentito parlare</span>
                    </div>
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #e67e22;">2</span>
                        <span>Capisco a cosa serve</span>
                    </div>
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #f1c40f; color: #333;">3</span>
                        <span>Lo so fare</span>
                    </div>
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #27ae60;">4</span>
                        <span>Trovo i problemi</span>
                    </div>
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #3498db;">5</span>
                        <span>So qual è il meglio</span>
                    </div>
                    <div class="bloom-quick-item">
                        <span class="bloom-quick-dot" style="background: #9b59b6;">6</span>
                        <span>Invento e miglioro</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form -->
    <form id="assessmentForm">
        <?php foreach ($areas as $prefix => $area): ?>
        <div class="area-card" id="area-<?php echo $prefix; ?>">
            <div class="area-header" style="background: <?php echo $area['info']['colore']; ?>;" onclick="toggleArea('<?php echo $prefix; ?>')">
                <div class="area-title">
                    <span class="icon"><?php echo $area['info']['icona']; ?></span>
                    <div>
                        <h2><?php echo $area['info']['nome']; ?></h2>
                        <span class="count"><?php echo count($area['competenze']); ?> competenze</span>
                    </div>
                </div>
                <span class="area-toggle">▼</span>
            </div>
            <div class="area-body">
                <?php foreach ($area['competenze'] as $comp): 
                    $current_level = $existing_by_comp[$comp->competencyid] ?? 0;
                ?>
                <div class="comp-row">
                    <div class="comp-name"><?php
                        // Mostra la descrizione se disponibile, altrimenti shortname, altrimenti idnumber
                        $display_name = '';
                        if (!empty($comp->description)) {
                            // Rimuovi tag HTML e prendi solo le prime parole
                            $clean_desc = strip_tags($comp->description);
                            $display_name = mb_strlen($clean_desc) > 100 ? mb_substr($clean_desc, 0, 100) . '...' : $clean_desc;
                        } elseif (!empty($comp->shortname) && $comp->shortname !== $comp->idnumber) {
                            $display_name = $comp->shortname;
                        } else {
                            // Fallback: rendi l'idnumber più leggibile
                            $display_name = str_replace('_', ' ', $comp->idnumber);
                        }
                        echo htmlspecialchars($display_name);
                    ?></div>
                    <div class="comp-code" style="font-size: 0.75em; color: #aaa;"><?php echo $comp->idnumber; ?></div>
                    
                    <div class="bloom-selector">
                        <?php foreach ($bloom_levels as $level => $info): ?>
                        <div class="bloom-option" style="--bloom-color: <?php echo $info['colore']; ?>; --bloom-bg: <?php echo $info['colore']; ?>22;">
                            <input type="radio" 
                                   name="comp_<?php echo $comp->competencyid; ?>" 
                                   id="comp_<?php echo $comp->competencyid; ?>_<?php echo $level; ?>"
                                   value="<?php echo $level; ?>"
                                   data-compid="<?php echo $comp->competencyid; ?>"
                                   <?php echo $current_level == $level ? 'checked' : ''; ?>
                                   onchange="updateProgress()">
                            <label for="comp_<?php echo $comp->competencyid; ?>_<?php echo $level; ?>">
                                <span class="level-num" style="color: <?php echo $info['colore']; ?>;"><?php echo $level; ?></span>
                                <span class="level-name"><?php echo $info['nome']; ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
    
    <!-- Save Footer -->
    <div class="sa-footer">
        <button type="button" class="btn-save" onclick="saveAssessment()">
            💾 <?php echo get_string('save', 'local_selfassessment'); ?>
        </button>
    </div>
</div>

<!-- Notification -->
<div class="sa-notification" id="notification"></div>

<script>
const totalCompetencies = <?php echo $total_assigned; ?>;

// Toggle area open/close
function toggleArea(prefix) {
    document.getElementById('area-' + prefix).classList.toggle('open');
}

// Toggle Bloom legend open/close
function toggleBloomLegend() {
    document.getElementById('bloomLegend').classList.toggle('open');
}

// Update progress bar
function updateProgress() {
    const checked = document.querySelectorAll('.bloom-option input:checked').length;
    const percent = Math.round((checked / totalCompetencies) * 100);
    
    document.getElementById('progressText').textContent = checked + ' / ' + totalCompetencies + ' competenze valutate';
    document.getElementById('progressFill').style.width = percent + '%';
}

// Save assessment via AJAX
function saveAssessment() {
    const btn = document.querySelector('.btn-save');
    btn.disabled = true;
    btn.innerHTML = '⏳ <?php echo get_string('saving', 'local_selfassessment'); ?>';
    
    // Collect all selected values
    const assessments = {};
    document.querySelectorAll('.bloom-option input:checked').forEach(input => {
        assessments[input.dataset.compid] = input.value;
    });
    
    // Aggiungo sesskey per sicurezza Moodle
    fetch('ajax_save.php?sesskey=' + M.cfg.sesskey, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({assessments: assessments})
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '💾 <?php echo get_string('save', 'local_selfassessment'); ?>';
        
        if (data.success) {
            showNotification('✅ <?php echo get_string('saved', 'local_selfassessment'); ?>', 'success');
        } else {
            showNotification('❌ ' + (data.error || '<?php echo get_string('save_error', 'local_selfassessment'); ?>'), 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '💾 <?php echo get_string('save', 'local_selfassessment'); ?>';
        showNotification('❌ <?php echo get_string('save_error', 'local_selfassessment'); ?>', 'error');
    });
}

// Show notification
function showNotification(message, type) {
    const notif = document.getElementById('notification');
    notif.textContent = message;
    notif.style.background = type === 'success' ? '#28a745' : '#dc3545';
    notif.style.display = 'block';
    
    setTimeout(() => {
        notif.style.display = 'none';
    }, 3000);
}

// Init: open first area, update progress
document.addEventListener('DOMContentLoaded', function() {
    const firstArea = document.querySelector('.area-card');
    if (firstArea) firstArea.classList.add('open');
    updateProgress();

    <?php if ($show_blocking_modal): ?>
    // Controlla se l'utente ha già skippato temporaneamente in questa sessione
    if (sessionStorage.getItem('sa_skipped_temp') === 'true') {
        document.getElementById('blockingModal').style.display = 'none';
    }

    // Gestione Enter nel campo password
    document.getElementById('skipPassword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            trySkip();
        }
    });
    <?php endif; ?>
});

<?php if ($show_blocking_modal): ?>
// Chiude il modal e permette di compilare
function closeBlockingModal() {
    document.getElementById('blockingModal').style.display = 'none';
}

// Password per skip
const SKIP_TEMP = '<?php echo SELFASSESSMENT_SKIP_TEMP; ?>';       // 6807 - temporaneo
const SKIP_PERMANENT = '<?php echo SELFASSESSMENT_SKIP_PERMANENT; ?>'; // FTM - definitivo

function trySkip() {
    const inputPassword = document.getElementById('skipPassword').value.toUpperCase();
    const errorEl = document.getElementById('skipError');
    const infoEl = document.getElementById('skipInfo');

    // Reset
    errorEl.style.display = 'none';
    infoEl.style.display = 'none';

    if (inputPassword === SKIP_TEMP) {
        // Skip TEMPORANEO - solo per questa sessione
        sessionStorage.setItem('sa_skipped_temp', 'true');
        document.getElementById('blockingModal').style.display = 'none';
        showNotification('⏭️ Skip temporaneo - il popup riapparirà al prossimo accesso', 'success');

        // Redirect alla home dopo 1.5 secondi
        setTimeout(() => {
            window.location.href = '<?php echo $CFG->wwwroot; ?>';
        }, 1500);

    } else if (inputPassword === SKIP_PERMANENT) {
        // Skip PERMANENTE - salva nel database
        infoEl.textContent = '⏳ Salvataggio skip permanente...';
        infoEl.style.display = 'block';

        // Chiama AJAX per salvare lo skip permanente
        fetch('ajax_skip_permanent.php?sesskey=' + M.cfg.sesskey, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'skip_permanent'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('blockingModal').style.display = 'none';
                showNotification('✅ Autovalutazione incompleta accettata - non vedrai più questo popup', 'success');

                // Redirect alla home dopo 2 secondi
                setTimeout(() => {
                    window.location.href = '<?php echo $CFG->wwwroot; ?>';
                }, 2000);
            } else {
                errorEl.textContent = '❌ Errore: ' + (data.error || 'Impossibile salvare');
                errorEl.style.display = 'block';
                infoEl.style.display = 'none';
            }
        })
        .catch(error => {
            errorEl.textContent = '❌ Errore di connessione';
            errorEl.style.display = 'block';
            infoEl.style.display = 'none';
        });

    } else {
        // Password errata
        errorEl.style.display = 'block';
        document.getElementById('skipPassword').value = '';
        document.getElementById('skipPassword').focus();

        // Shake animation
        const modal = document.querySelector('.sa-blocking-modal');
        modal.style.animation = 'none';
        setTimeout(() => {
            modal.style.animation = 'shake 0.5s ease';
        }, 10);

        // Nascondi errore dopo 3 secondi
        setTimeout(() => {
            errorEl.style.display = 'none';
        }, 3000);
    }
}

// Shake animation
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-10px); }
        40%, 80% { transform: translateX(10px); }
    }
`;
document.head.appendChild(shakeStyle);
<?php endif; ?>
</script>

<?php
echo $OUTPUT->footer();
