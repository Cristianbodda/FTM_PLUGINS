<?php
// ============================================
// Self Assessment - Report Singolo Studente
// ============================================
// Visualizza autovalutazioni di uno studente specifico
// Per coach/admin che accedono dalla pagina competencymanager
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();

$context = context_system::instance();

// Verifica permessi (deve poter visualizzare autovalutazioni)
require_capability('local/selfassessment:view', $context);

// Parametri
$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$sector_filter = optional_param('sector', '', PARAM_ALPHANUMEXT);

// Carica dati studente
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Manager
$manager = new \local_selfassessment\manager();

// Carica TUTTE le autovalutazioni dello studente (per trovare i settori disponibili)
$all_assessments = $manager->get_user_assessments($userid);

// Carica competenze per determinare i settori
$all_competencies_info = [];
if (!empty($all_assessments)) {
    $comp_ids = array_column((array)$all_assessments, 'competencyid');
    if (!empty($comp_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($comp_ids);
        $all_competencies_info = $DB->get_records_select('competency', "id $insql", $params, '', 'id, idnumber, shortname, description');
    }
}

// Trova i settori disponibili dalle autovalutazioni
$available_sectors = [];
foreach ($all_assessments as $a) {
    $comp = $all_competencies_info[$a->competencyid] ?? null;
    if ($comp && !empty($comp->idnumber)) {
        $sector = $manager->get_sector_from_idnumber($comp->idnumber);
        if (!isset($available_sectors[$sector])) {
            $available_sectors[$sector] = 0;
        }
        $available_sectors[$sector]++;
    }
}

// Nomi display settori
$sector_display_names = [
    'AUTOMAZIONE' => 'Automazione',
    'GEN' => 'Generico',
    'MECCANICA' => 'Meccanica',
    'AUTOMOBILE' => 'Automobile',
    'LOGISTICA' => 'Logistica',
    'ELETTRICITA' => 'Elettricita',
    'METALCOSTRUZIONE' => 'Metalcostruzione',
    'CHIMFARM' => 'Chimico-Farmaceutico',
];

// Filtra autovalutazioni per settore se specificato
$assessments = [];
$competencies_info = [];
if (!empty($sector_filter)) {
    foreach ($all_assessments as $a) {
        $comp = $all_competencies_info[$a->competencyid] ?? null;
        if ($comp && !empty($comp->idnumber)) {
            $sector = $manager->get_sector_from_idnumber($comp->idnumber);
            if ($sector === $sector_filter) {
                $assessments[$a->id] = $a;
                $competencies_info[$a->competencyid] = $comp;
            }
        }
    }
} else {
    $assessments = $all_assessments;
    $competencies_info = $all_competencies_info;
}

// Ricalcola assessments_by_area con i dati filtrati
$assessments_by_area = [];
foreach ($assessments as $assessment) {
    $comp = $competencies_info[$assessment->competencyid] ?? null;
    if (!$comp) continue;

    $area = $manager->get_area_from_idnumber_public($comp->idnumber);

    if (!isset($assessments_by_area[$area])) {
        $assessments_by_area[$area] = [
            'assessments' => [],
            'sum' => 0,
            'count' => 0
        ];
    }

    $assessments_by_area[$area]['assessments'][] = $assessment;
    $assessments_by_area[$area]['sum'] += $assessment->level;
    $assessments_by_area[$area]['count']++;
}

// Calcola medie per area
foreach ($assessments_by_area as $area => &$data) {
    $data['average_level'] = $data['count'] > 0 ? round($data['sum'] / $data['count'], 1) : 0;
    $data['average_percent'] = $data['count'] > 0 ? round(($data['sum'] / $data['count']) / 6 * 100) : 0;
}
unset($data);

// Competenze assegnate
$assigned = $manager->get_assigned_competencies($userid);
$assigned_count = count($assigned);

// Stato abilitazione
$is_enabled = $manager->is_enabled($userid);

// Calcola statistiche (sui dati filtrati)
$total_assessments = count($assessments);
$sum_levels = 0;
foreach ($assessments as $a) {
    $sum_levels += $a->level;
}
$average_level = $total_assessments > 0 ? round($sum_levels / $total_assessments, 1) : 0;
$average_percent = $total_assessments > 0 ? round(($sum_levels / $total_assessments) / 6 * 100) : 0;

// Ultima modifica
$last_modified = 0;
foreach ($assessments as $a) {
    if ($a->timemodified > $last_modified) {
        $last_modified = $a->timemodified;
    }
}

// Nomi aree - Mapping completo per tutti i settori
$area_names = [
    // AUTOMOBILE
    'manutenzione-auto' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db'],
    'manutenzione-rip' => ['nome' => 'Manutenzione e Riparazione', 'icona' => '🔧', 'colore' => '#e74c3c'],
    // MECCANICA
    'assemblaggio' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12'],
    'automazione-mecc' => ['nome' => 'Automazione e Meccatronica', 'icona' => '🤖', 'colore' => '#e74c3c'],
    'cnc' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4'],
    'disegno' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db'],
    'lav-generali' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e'],
    'lav-macchine' => ['nome' => 'Lavorazioni Macchine', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'lav-base' => ['nome' => 'Lavorazioni Base', 'icona' => '🔧', 'colore' => '#795548'],
    'manutenzione' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22'],
    'misurazione' => ['nome' => 'Misure e Controlli', 'icona' => '📏', 'colore' => '#1abc9c'],
    'pianificazione' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6'],
    'programmazione' => ['nome' => 'Programmazione', 'icona' => '💻', 'colore' => '#2ecc71'],
    'sicurezza' => ['nome' => 'Sicurezza e Qualita', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'collaborazione' => ['nome' => 'Competenze Trasversali', 'icona' => '🤝', 'colore' => '#8e44ad'],
    'trattamenti' => ['nome' => 'Trattamenti e Processi', 'icona' => '🔬', 'colore' => '#607d8b'],
    // AUTOMAZIONE (aree A-H)
    'automazione' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c'],
    'automazione-A' => ['nome' => 'A. Pianificazione e Documentazione', 'icona' => '📋', 'colore' => '#3498db'],
    'automazione-B' => ['nome' => 'B. Montaggio Elettromeccanico', 'icona' => '🔧', 'colore' => '#e74c3c'],
    'automazione-C' => ['nome' => 'C. Cablaggio e Quadri', 'icona' => '🔌', 'colore' => '#f39c12'],
    'automazione-D' => ['nome' => 'D. Automazione e PLC', 'icona' => '🤖', 'colore' => '#9b59b6'],
    'automazione-E' => ['nome' => 'E. Strumentazione e Misure', 'icona' => '📏', 'colore' => '#1abc9c'],
    'automazione-F' => ['nome' => 'F. Reti e Comunicazione', 'icona' => '🌐', 'colore' => '#00bcd4'],
    'automazione-G' => ['nome' => 'G. Qualita e Sicurezza', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'automazione-H' => ['nome' => 'H. Manutenzione e Service', 'icona' => '🔨', 'colore' => '#e67e22'],
    // GENERICO (aree A-G per orientamento settoriale)
    'generico' => ['nome' => 'Test Generico', 'icona' => '📝', 'colore' => '#95a5a6'],
    'gen-A' => ['nome' => 'A. Meccanica', 'icona' => '⚙️', 'colore' => '#607d8b'],
    'gen-B' => ['nome' => 'B. Metalcostruzione', 'icona' => '🔩', 'colore' => '#795548'],
    'gen-C' => ['nome' => 'C. Elettricita', 'icona' => '⚡', 'colore' => '#f39c12'],
    'gen-D' => ['nome' => 'D. Elettronica e Automazione', 'icona' => '🤖', 'colore' => '#9b59b6'],
    'gen-E' => ['nome' => 'E. Logistica', 'icona' => '📦', 'colore' => '#3498db'],
    'gen-F' => ['nome' => 'F. Chimico-Farmaceutico', 'icona' => '🧪', 'colore' => '#1abc9c'],
    'gen-G' => ['nome' => 'G. Automobile e Manutenzione', 'icona' => '🚗', 'colore' => '#e74c3c'],
    // LOGISTICA
    'logistica' => ['nome' => 'Logistica', 'icona' => '📦', 'colore' => '#3498db'],
    'logistica-A' => ['nome' => 'A. Organizzazione Mandati', 'icona' => '📋', 'colore' => '#3498db'],
    'logistica-B' => ['nome' => 'B. Qualita e Efficienza', 'icona' => '✅', 'colore' => '#2ecc71'],
    'logistica-C' => ['nome' => 'C. Ricezione e Stoccaggio', 'icona' => '📥', 'colore' => '#f39c12'],
    'logistica-D' => ['nome' => 'D. Preparazione e Spedizione', 'icona' => '📤', 'colore' => '#e74c3c'],
    'logistica-E' => ['nome' => 'E. Accettazione e Consulenza', 'icona' => '🤝', 'colore' => '#9b59b6'],
    'logistica-F' => ['nome' => 'F. Recapito e Servizi', 'icona' => '🚚', 'colore' => '#00bcd4'],
    'logistica-G' => ['nome' => 'G. Operazioni Magazzino', 'icona' => '🏭', 'colore' => '#607d8b'],
    'logistica-H' => ['nome' => 'H. Commissionamento e Carico', 'icona' => '📦', 'colore' => '#795548'],
    // ELETTRICITA
    'elettricita' => ['nome' => 'Elettricita', 'icona' => '⚡', 'colore' => '#f39c12'],
    'elettricita-A' => ['nome' => 'A. Pianificazione e Progettazione', 'icona' => '📐', 'colore' => '#3498db'],
    'elettricita-B' => ['nome' => 'B. Installazione Impianti BT', 'icona' => '🔌', 'colore' => '#f39c12'],
    'elettricita-C' => ['nome' => 'C. Montaggio e Cablaggio Quadri', 'icona' => '🔧', 'colore' => '#e74c3c'],
    'elettricita-D' => ['nome' => 'D. Reti di Distribuzione', 'icona' => '🌐', 'colore' => '#9b59b6'],
    'elettricita-E' => ['nome' => 'E. Misure e Collaudi', 'icona' => '📏', 'colore' => '#1abc9c'],
    'elettricita-F' => ['nome' => 'F. Sicurezza e Norme', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'elettricita-G' => ['nome' => 'G. Documentazione e CAD/BIM', 'icona' => '📋', 'colore' => '#607d8b'],
    'elettricita-H' => ['nome' => 'H. Manutenzione e Service', 'icona' => '🔨', 'colore' => '#e67e22'],
    // METALCOSTRUZIONE
    'metalcostruzione' => ['nome' => 'Metalcostruzione', 'icona' => '🔩', 'colore' => '#795548'],
    'metalcostruzione-A' => ['nome' => 'A. Pianificazione e CAD', 'icona' => '📐', 'colore' => '#3498db'],
    'metalcostruzione-B' => ['nome' => 'B. Preparazione e Taglio', 'icona' => '✂️', 'colore' => '#e74c3c'],
    'metalcostruzione-C' => ['nome' => 'C. Lavorazioni e Assemblaggio', 'icona' => '🔧', 'colore' => '#f39c12'],
    'metalcostruzione-D' => ['nome' => 'D. Saldatura', 'icona' => '🔥', 'colore' => '#e67e22'],
    'metalcostruzione-E' => ['nome' => 'E. Trattamenti Superficiali', 'icona' => '🎨', 'colore' => '#9b59b6'],
    'metalcostruzione-F' => ['nome' => 'F. Montaggio e Posa', 'icona' => '🏗️', 'colore' => '#607d8b'],
    'metalcostruzione-G' => ['nome' => 'G. Misure e Qualita', 'icona' => '📏', 'colore' => '#1abc9c'],
    'metalcostruzione-H' => ['nome' => 'H. Sicurezza e Ambiente', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'metalcostruzione-I' => ['nome' => 'I. CAD/CAM e BIM', 'icona' => '💻', 'colore' => '#00bcd4'],
    'metalcostruzione-J' => ['nome' => 'J. Manutenzione e Ripristino', 'icona' => '🔨', 'colore' => '#795548'],
    // CHIMFARM
    'chimfarm' => ['nome' => 'Chimico-Farmaceutico', 'icona' => '🧪', 'colore' => '#1abc9c'],
    'chimfarm-1C' => ['nome' => '1C. Conformita e GMP', 'icona' => '✅', 'colore' => '#2ecc71'],
    'chimfarm-1G' => ['nome' => '1G. Gestione Materiali', 'icona' => '📦', 'colore' => '#3498db'],
    'chimfarm-1O' => ['nome' => '1O. Operazioni Base', 'icona' => '🔧', 'colore' => '#607d8b'],
    'chimfarm-2M' => ['nome' => '2M. Misurazione', 'icona' => '📏', 'colore' => '#1abc9c'],
    'chimfarm-3C' => ['nome' => '3C. Controllo Qualita', 'icona' => '🔬', 'colore' => '#9b59b6'],
    'chimfarm-4S' => ['nome' => '4S. Sicurezza', 'icona' => '🛡️', 'colore' => '#c0392b'],
    'chimfarm-5S' => ['nome' => '5S. Sterilita', 'icona' => '🧫', 'colore' => '#00bcd4'],
    'chimfarm-6P' => ['nome' => '6P. Produzione', 'icona' => '🏭', 'colore' => '#f39c12'],
    'chimfarm-7S' => ['nome' => '7S. Strumentazione', 'icona' => '⚗️', 'colore' => '#e74c3c'],
    'chimfarm-8T' => ['nome' => '8T. Tecnologie', 'icona' => '💻', 'colore' => '#3498db'],
    'chimfarm-9A' => ['nome' => '9A. Analisi', 'icona' => '📊', 'colore' => '#8e44ad'],
    // Fallback
    'altro' => ['nome' => 'Altro', 'icona' => '📁', 'colore' => '#95a5a6'],
];

// Setup pagina
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/selfassessment/student_report.php', ['userid' => $userid]));
$PAGE->set_title(get_string('pluginname', 'local_selfassessment') . ' - ' . fullname($student));
$PAGE->set_heading(get_string('pluginname', 'local_selfassessment'));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
?>

<style>
/* ============================================
   STUDENT REPORT STYLES
   ============================================ */
.report-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Student Header */
.student-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 30px;
    color: white;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;
}

.student-header .avatar {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2em;
    font-weight: 700;
}

.student-header .info {
    flex: 1;
}

.student-header .info h1 {
    margin: 0 0 5px 0;
    font-size: 1.8em;
}

.student-header .info p {
    margin: 0;
    opacity: 0.9;
}

.student-header .score {
    text-align: center;
    background: rgba(255,255,255,0.15);
    padding: 20px 30px;
    border-radius: 12px;
}

.student-header .score .value {
    font-size: 2.5em;
    font-weight: 700;
}

.student-header .score .label {
    font-size: 0.9em;
    opacity: 0.9;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card .icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.stat-card .value {
    font-size: 2.2em;
    font-weight: 700;
    color: #2c3e50;
}

.stat-card .label {
    color: #7f8c8d;
    font-size: 0.9em;
    text-transform: uppercase;
}

.stat-card.primary { border-left: 4px solid #3498db; }
.stat-card.success { border-left: 4px solid #28a745; }
.stat-card.warning { border-left: 4px solid #ffc107; }
.stat-card.info { border-left: 4px solid #17a2b8; }

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

@media (max-width: 900px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header {
    padding: 20px;
    font-weight: 600;
    font-size: 1.1em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header.primary { background: #3498db; color: white; }
.card-header.success { background: #28a745; color: white; }
.card-header.info { background: #17a2b8; color: white; }

.card-body {
    padding: 20px;
}

/* Radar Chart */
.radar-container {
    max-width: 400px;
    margin: 0 auto;
}

/* Area Cards */
.area-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.2s ease;
}

.area-card:hover {
    background: #e9ecef;
}

.area-card .area-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
}

.area-card .area-info {
    flex: 1;
}

.area-card .area-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.area-card .area-count {
    font-size: 0.85em;
    color: #7f8c8d;
}

.area-card .area-score {
    text-align: right;
}

.area-card .area-score .value {
    font-size: 1.3em;
    font-weight: 700;
}

.area-card .area-score .level {
    font-size: 0.8em;
    color: #7f8c8d;
}

/* Progress bar */
.progress-bar-container {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-top: 8px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Level scale */
.level-scale {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.level-scale span {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: 600;
}

/* Back button */
.back-btn {
    display: inline-block;
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background: #5a6268;
    color: white;
    text-decoration: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state .icon {
    font-size: 4em;
    margin-bottom: 20px;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
}

.status-badge.enabled {
    background: #d4edda;
    color: #155724;
}

.status-badge.disabled {
    background: #f8d7da;
    color: #721c24;
}

/* Details Table */
.details-table {
    width: 100%;
    border-collapse: collapse;
}

.details-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
}

.details-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f1f1;
}

.details-table tr:hover {
    background: #f8f9fa;
}

/* Level badge */
.level-badge {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 30px;
    text-align: center;
    border-radius: 50%;
    color: white;
    font-weight: 700;
    font-size: 0.9em;
}

.level-badge.level-1 { background: #e74c3c; }
.level-badge.level-2 { background: #f39c12; }
.level-badge.level-3 { background: #f1c40f; }
.level-badge.level-4 { background: #3498db; }
.level-badge.level-5 { background: #27ae60; }
.level-badge.level-6 { background: #2ecc71; }
</style>

<div class="report-container">
    <!-- Back Button -->
    <?php
    $back_url = new moodle_url('/local/selfassessment/index.php');
    if ($courseid) {
        $back_url = new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid]);
    }
    ?>
    <a href="<?php echo $back_url; ?>" class="back-btn">
        ← Torna indietro
    </a>

    <!-- Student Header -->
    <div class="student-header">
        <div class="avatar">
            <?php echo strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1)); ?>
        </div>
        <div class="info">
            <h1><?php echo fullname($student); ?></h1>
            <p><?php echo $student->email; ?></p>
            <p style="margin-top: 8px;">
                <span class="status-badge <?php echo $is_enabled ? 'enabled' : 'disabled'; ?>">
                    <?php echo $is_enabled ? '✅ Autovalutazione abilitata' : '🚫 Autovalutazione disabilitata'; ?>
                </span>
            </p>
        </div>
        <div class="score">
            <div class="value"><?php echo $average_percent; ?>%</div>
            <div class="label">Media Autovalutazione</div>
        </div>
    </div>

    <!-- Sector Filter -->
    <?php if (count($available_sectors) > 1): ?>
    <div class="sector-filter" style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <form method="get" action="" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <input type="hidden" name="userid" value="<?php echo $userid; ?>">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <label style="font-weight: 600; color: #2c3e50;">
                🎯 Filtra per Settore:
            </label>
            <select name="sector" onchange="this.form.submit()" style="padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1em; min-width: 200px;">
                <option value="">Tutti i settori (<?php echo count($all_assessments); ?> competenze)</option>
                <?php foreach ($available_sectors as $sec => $count):
                    $display = $sector_display_names[$sec] ?? $sec;
                    $selected = ($sector_filter === $sec) ? 'selected' : '';
                ?>
                <option value="<?php echo $sec; ?>" <?php echo $selected; ?>>
                    <?php echo $display; ?> (<?php echo $count; ?> competenze)
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($sector_filter)): ?>
            <a href="?userid=<?php echo $userid; ?>&courseid=<?php echo $courseid; ?>"
               style="padding: 10px 15px; background: #6c757d; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                ✕ Mostra tutti
            </a>
            <?php endif; ?>
        </form>
    </div>
    <?php elseif (count($available_sectors) == 1): ?>
    <div class="sector-info" style="margin-bottom: 20px; padding: 15px; background: #e8f4fd; border-radius: 12px; display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 1.5em;">🎯</span>
        <span style="font-weight: 600; color: #2c3e50;">
            Settore: <?php echo $sector_display_names[array_key_first($available_sectors)] ?? array_key_first($available_sectors); ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icon">📝</div>
            <div class="value"><?php echo $total_assessments; ?></div>
            <div class="label">Competenze Valutate<?php echo $sector_filter ? ' (' . ($sector_display_names[$sector_filter] ?? $sector_filter) . ')' : ''; ?></div>
        </div>
        <div class="stat-card success">
            <div class="icon">📊</div>
            <div class="value"><?php echo $average_level; ?>/6</div>
            <div class="label">Livello Medio</div>
        </div>
        <div class="stat-card warning">
            <div class="icon">📋</div>
            <div class="value"><?php echo $assigned_count; ?></div>
            <div class="label">Competenze Assegnate</div>
        </div>
        <div class="stat-card info">
            <div class="icon">🕐</div>
            <div class="value"><?php echo $last_modified ? date('d/m/Y', $last_modified) : '-'; ?></div>
            <div class="label">Ultima Modifica</div>
        </div>
    </div>

    <?php if (empty($assessments)): ?>
    <!-- Empty State -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="icon">📋</div>
                <h3>Nessuna autovalutazione</h3>
                <p>Lo studente non ha ancora completato autovalutazioni<?php echo $sector_filter ? ' per questo settore' : ''; ?>.</p>
            </div>
            <?php if ($assigned_count > 0): ?>
            <p style="text-align: center; color: #7f8c8d; margin-top: 20px;">
                <strong><?php echo $assigned_count; ?> competenze assegnate</strong> in attesa di valutazione.
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Radar Chart -->
        <div class="card">
            <div class="card-header primary">
                📊 Radar Autovalutazione per Area
            </div>
            <div class="card-body">
                <div class="radar-container">
                    <canvas id="radarChart"></canvas>
                </div>
                <div class="level-scale">
                    <span style="background: #e74c3c; color: white;">1 Base</span>
                    <span style="background: #f39c12; color: white;">3 Intermedio</span>
                    <span style="background: #2ecc71; color: white;">6 Esperto</span>
                </div>
            </div>
        </div>

        <!-- Areas Summary -->
        <div class="card">
            <div class="card-header success">
                📈 Riepilogo per Area
            </div>
            <div class="card-body">
                <?php foreach ($assessments_by_area as $area_key => $area_data):
                    $area_info = $area_names[$area_key] ?? $area_names['altro'];
                    $percent = $area_data['average_percent'];
                    $color = $area_info['colore'];
                ?>
                <div class="area-card">
                    <div class="area-icon" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                        <?php echo $area_info['icona']; ?>
                    </div>
                    <div class="area-info">
                        <div class="area-name"><?php echo $area_info['nome']; ?></div>
                        <div class="area-count"><?php echo $area_data['count']; ?> competenze</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $color; ?>;"></div>
                        </div>
                    </div>
                    <div class="area-score">
                        <div class="value" style="color: <?php echo $color; ?>;"><?php echo $percent; ?>%</div>
                        <div class="level">Liv. <?php echo $area_data['average_level']; ?>/6</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Details Table -->
    <div class="card">
        <div class="card-header info">
            📋 Dettaglio Competenze Autovalutate (<?php echo count($assessments); ?>)
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Competenza</th>
                        <th>Codice</th>
                        <th style="text-align: center;">Livello</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assessments as $assessment):
                        $comp = $competencies_info[$assessment->competencyid] ?? null;
                        $code = $comp ? $comp->idnumber : 'N/A';
                        // Mostra descrizione se disponibile, altrimenti shortname, altrimenti idnumber formattato
                        if ($comp) {
                            if (!empty($comp->description)) {
                                $clean_desc = strip_tags($comp->description);
                                $name = mb_strlen($clean_desc) > 80 ? mb_substr($clean_desc, 0, 80) . '...' : $clean_desc;
                            } elseif (!empty($comp->shortname) && $comp->shortname !== $comp->idnumber) {
                                $name = $comp->shortname;
                            } else {
                                $name = str_replace('_', ' ', $comp->idnumber);
                            }
                        } else {
                            $name = 'Competenza #' . $assessment->competencyid;
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo s($name); ?></strong>
                            <?php if (!empty($assessment->comment)): ?>
                            <br><small style="color: #7f8c8d;">💬 <?php echo s($assessment->comment); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo s($code); ?></code></td>
                        <td style="text-align: center;">
                            <span class="level-badge level-<?php echo $assessment->level; ?>">
                                <?php echo $assessment->level; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', $assessment->timemodified); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php if (!empty($assessments_by_area)): ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('radarChart').getContext('2d');

    var areaData = <?php
        $labels = [];
        $values = [];
        $colors = [];
        foreach ($assessments_by_area as $area_key => $area_data) {
            $area_info = $area_names[$area_key] ?? $area_names['altro'];
            $labels[] = $area_info['nome'];
            $values[] = $area_data['average_level'];
            $colors[] = $area_info['colore'];
        }
        echo json_encode([
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors
        ]);
    ?>;

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: areaData.labels,
            datasets: [{
                label: 'Autovalutazione',
                data: areaData.values,
                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(52, 152, 219, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 6,
                    min: 0,
                    ticks: {
                        stepSize: 1
                    },
                    pointLabels: {
                        font: {
                            size: 11
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
