<?php
/**
 * Assegna Competenze AUTOMOBILE_MA (Mapping Intelligente)
 * 
 * Assegna le competenze corrette alle domande che hanno solo "AUTOMOBILE_MA"
 * basandosi sul contenuto della domanda
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencyxmlimport/fix_automobile_ma.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Competenze AUTOMOBILE_MA');
$PAGE->set_heading($course->fullname);

$css = '<style>
.page { max-width: 1000px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th, .table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.table th { background: #f8f9fa; }
.btn { padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; display: inline-block; margin-right: 10px; }
.btn-purple { background: #9b59b6; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.ok { color: #27ae60; }
.mapping { background: #e8f5e9; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; }
</style>';

echo $OUTPUT->header();
echo $css;

// Framework ID
$framework_id = 1;

// MAPPING INTELLIGENTE basato sul contenuto delle domande
$keyword_mapping = [
    // Diagnostica OBD
    'OBD' => 'AUTOMOBILE_MAu_D2',
    'EOBD' => 'AUTOMOBILE_MAu_D2',
    'tester' => 'AUTOMOBILE_MAu_D2',
    'freeze frame' => 'AUTOMOBILE_MAu_D3',
    'readiness' => 'AUTOMOBILE_MAu_D4',
    'revisione' => 'AUTOMOBILE_MAu_D4',
    
    // Motore/Emissioni
    'DPF' => 'AUTOMOBILE_MAu_B11',
    'rigenerazione' => 'AUTOMOBILE_MAu_B11',
    'NOx' => 'AUTOMOBILE_MAu_D5',
    'SCR' => 'AUTOMOBILE_MAu_D5',
    'AdBlue' => 'AUTOMOBILE_MAu_D5',
    
    // Trasmissione
    'cambio automatico' => 'AUTOMOBILE_MAu_E3',
    'adattamenti' => 'AUTOMOBILE_MAu_E3',
    
    // Elettronica
    'ABS' => 'AUTOMOBILE_MAu_G2',
    'multimetro' => 'AUTOMOBILE_MAu_G4',
    'tensione' => 'AUTOMOBILE_MAu_G4',
    'CAN' => 'AUTOMOBILE_MAu_G5',
    'LIN' => 'AUTOMOBILE_MAu_G5',
    'rete' => 'AUTOMOBILE_MAu_G5',
    'programmazione' => 'AUTOMOBILE_MAu_G6',
    'ECU' => 'AUTOMOBILE_MAu_G6',
    'centralina' => 'AUTOMOBILE_MAu_G6',
    
    // ADAS
    'ADAS' => 'AUTOMOBILE_MAu_H1',
    'radar' => 'AUTOMOBILE_MAu_H1',
    'telecamera' => 'AUTOMOBILE_MAu_H1',
    'parabrezza' => 'AUTOMOBILE_MAu_H2',
    'calibra' => 'AUTOMOBILE_MAu_H2',
    
    // Alta Tensione HV
    'LOTO' => 'AUTOMOBILE_MAu_J1',
    'lockout' => 'AUTOMOBILE_MAu_J1',
    'alta tensione' => 'AUTOMOBILE_MAu_J1',
    'SOC' => 'AUTOMOBILE_MAu_J2',
    'SOH' => 'AUTOMOBILE_MAu_J2',
    'batteria HV' => 'AUTOMOBILE_MAu_J2',
    'elettrico' => 'AUTOMOBILE_MAu_J2',
    'ibrido' => 'AUTOMOBILE_MAu_J2',
];

// Funzione per trovare competenza basata su keywords
function find_competency_by_content($text, $keyword_mapping) {
    $text_lower = strtolower($text);
    
    foreach ($keyword_mapping as $keyword => $competency) {
        if (stripos($text_lower, strtolower($keyword)) !== false) {
            return $competency;
        }
    }
    return null;
}

// Carica competenze MAu dal framework
$competencies = $DB->get_records_sql("
    SELECT id, idnumber, shortname FROM {competency} 
    WHERE competencyframeworkid = ? AND idnumber LIKE 'AUTOMOBILE_MAu_%'
", [$framework_id]);
$comp_lookup = [];
foreach ($competencies as $c) {
    $comp_lookup[$c->idnumber] = $c->id;
}

// Trova domande con AUTOMOBILE_MA senza competenza assegnata
$questions = $DB->get_records_sql("
    SELECT q.id, q.name, q.questiontext
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    AND q.questiontext LIKE '%AUTOMOBILE_MA%'
    AND q.questiontext NOT LIKE '%AUTOMOBILE_MAu%'
    AND q.questiontext NOT LIKE '%AUTOMOBILE_MR%'
", [$context->id]);

// Analizza e crea mapping
$mappings = [];
foreach ($questions as $q) {
    $text = html_entity_decode($q->questiontext);
    
    // Verifica se già ha competenza
    $has_competency = $DB->record_exists('qbank_competenciesbyquestion', ['questionid' => $q->id]);
    
    if (!$has_competency) {
        $suggested = find_competency_by_content($text, $keyword_mapping);
        
        if ($suggested && isset($comp_lookup[$suggested])) {
            $mappings[] = [
                'id' => $q->id,
                'name' => $q->name,
                'text' => substr(strip_tags($text), 0, 100),
                'competency' => $suggested,
                'competency_id' => $comp_lookup[$suggested]
            ];
        }
    }
}

// ESEGUI ASSEGNAZIONE
if ($action === 'assign') {
    require_sesskey();
    
    $assigned = 0;
    foreach ($mappings as $m) {
        $record = new stdClass();
        $record->questionid = $m['id'];
        $record->competencyid = $m['competency_id'];
        $record->difficultylevel = 1; // Base
        $DB->insert_record('qbank_competenciesbyquestion', $record);
        $assigned++;
    }
    
    echo '<div class="page">';
    echo '<div class="header"><h2>✅ Assegnazione Completata!</h2></div>';
    echo '<div class="panel">';
    echo '<p><strong>Competenze assegnate:</strong> ' . $assigned . '</p>';
    echo '</div>';
    echo '<a href="dashboard.php?courseid=' . $courseid . '" class="btn btn-secondary">← Dashboard</a>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// PAGINA PREVIEW
?>
<div class="page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" style="color: #9b59b6;">← Dashboard</a>
    
    <div class="header">
        <h2>🔧 Fix Competenze AUTOMOBILE_MA</h2>
        <p>Mapping intelligente per le domande con codice incompleto</p>
    </div>
    
    <div class="panel">
        <h3>📋 Domande da Correggere: <?php echo count($mappings); ?></h3>
        
        <?php if (empty($mappings)): ?>
        <p class="ok">✅ Nessuna domanda da correggere! Tutte le competenze sono già assegnate.</p>
        <?php else: ?>
        
        <table class="table">
            <tr>
                <th>Domanda</th>
                <th>Contenuto</th>
                <th>Competenza Suggerita</th>
            </tr>
            <?php foreach ($mappings as $m): ?>
            <tr>
                <td><?php echo format_string($m['name']); ?></td>
                <td><?php echo htmlspecialchars(substr($m['text'], 0, 60)); ?>...</td>
                <td><span class="mapping"><?php echo $m['competency']; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <form method="post" action="?courseid=<?php echo $courseid; ?>&action=assign" style="margin-top: 20px;">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-purple">🔧 Assegna <?php echo count($mappings); ?> Competenze</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
        
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h3>ℹ️ Logica del Mapping</h3>
        <p>Le competenze vengono assegnate in base alle parole chiave nel testo della domanda:</p>
        <ul style="font-size: 13px;">
            <li><strong>OBD, EOBD, tester</strong> → AUTOMOBILE_MAu_D2</li>
            <li><strong>freeze frame</strong> → AUTOMOBILE_MAu_D3</li>
            <li><strong>readiness, revisione</strong> → AUTOMOBILE_MAu_D4</li>
            <li><strong>DPF, rigenerazione</strong> → AUTOMOBILE_MAu_B11</li>
            <li><strong>NOx, SCR, AdBlue</strong> → AUTOMOBILE_MAu_D5</li>
            <li><strong>cambio automatico</strong> → AUTOMOBILE_MAu_E3</li>
            <li><strong>ABS</strong> → AUTOMOBILE_MAu_G2</li>
            <li><strong>multimetro, tensione</strong> → AUTOMOBILE_MAu_G4</li>
            <li><strong>CAN, rete</strong> → AUTOMOBILE_MAu_G5</li>
            <li><strong>programmazione, ECU</strong> → AUTOMOBILE_MAu_G6</li>
            <li><strong>ADAS, radar, telecamera</strong> → AUTOMOBILE_MAu_H1</li>
            <li><strong>parabrezza, calibra</strong> → AUTOMOBILE_MAu_H2</li>
            <li><strong>LOTO, alta tensione</strong> → AUTOMOBILE_MAu_J1</li>
            <li><strong>SOC, SOH, batteria HV</strong> → AUTOMOBILE_MAu_J2</li>
        </ul>
    </div>
</div>
<?php

echo $OUTPUT->footer();
