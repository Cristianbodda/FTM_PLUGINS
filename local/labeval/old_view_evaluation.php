<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * View evaluation results with radar chart and expandable details
 * VERSIONE 2.0: Report con radar competenze e riquadri espandibili
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/api.php');

use local_labeval\api;

// DESCRIZIONI COMPETENZE - dall'Excel fornito
$COMPETENCY_DESCRIPTIONS = [
    'MECCANICA_CSP_03' => 'Si adatta ai cambiamenti e apprende in autonomia.',
    'MECCANICA_DT_01' => 'Legge e interpreta disegni tecnici 2D.',
    'MECCANICA_MIS_01' => 'Utilizza strumenti di misura tradizionali (calibri, micrometri, comparatori).',
    'MECCANICA_MIS_02' => 'Esegue controlli dimensionali e funzionali dei pezzi.',
    'MECCANICA_MIS_03' => 'Utilizza strumenti digitali e di misura automatica.',
    'MECCANICA_MIS_04' => 'Interpreta disegni e tolleranze geometriche.',
    'MECCANICA_MIS_05' => 'Documenta i risultati dei controlli e segnala non conformità.',
    'MECCANICA_PIAN_01' => 'Pianifica le fasi di lavorazione e i tempi di produzione.',
    'MECCANICA_SAQ_01' => 'Applica norme di sicurezza e comportamenti protettivi.',
    'MECCANICA_SAQ_02' => 'Identifica rischi legati alle lavorazioni meccaniche.',
    'MECCANICA_SAQ_03' => 'Gestisce correttamente materiali, energia e rifiuti.',
    'MECCANICA_SAQ_04' => 'Partecipa a controlli di qualità e audit interni.',
];

// Require login
require_login();
$context = context_system::instance();
require_capability('local/labeval:view', $context);

// Parameters
$sessionid = required_param('sessionid', PARAM_INT);

// Get session details
$session = api::get_session_details($sessionid);
if (!$session) {
    throw new moodle_exception('sessionnotfound', 'local_labeval');
}

// Get template with behaviors
$template = api::get_template_details($session->templateid);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/view_evaluation.php', ['sessionid' => $sessionid]));
$PAGE->set_title('Report Valutazione');
$PAGE->set_heading('Report Valutazione');
$PAGE->set_pagelayout('standard');

// Carica tutti i ratings
$allratings = $DB->get_records('local_labeval_ratings', ['sessionid' => $sessionid]);

// Organizza i dati per competenza e per comportamento
$competencyData = [];
$behaviorData = [];
$totalScore = 0;
$maxScore = 0;
$improvementCount = 0;

foreach ($allratings as $rating) {
    $behavior = $DB->get_record('local_labeval_behaviors', ['id' => $rating->behaviorid]);
    if (!$behavior) continue;
    
    // Gestisci sia dati nuovi (con competencycode) che vecchi (senza)
    $compcode = '';
    
    // Controlla se il campo competencycode esiste e ha un valore
    // Usa property_exists per evitare warning su proprietà non definite
    if (property_exists($rating, 'competencycode') && !empty($rating->competencycode)) {
        $compcode = $rating->competencycode;
    } else {
        // Dati vecchi: prova a estrarre da notes (formato COMP:CODE:notes)
        if (!empty($rating->notes) && strpos($rating->notes, 'COMP:') === 0) {
            $parts = explode(':', $rating->notes, 3);
            $compcode = $parts[1] ?? '';
        }
        
        // Se ancora vuoto, cerca nella tabella behavior_comp
        if (empty($compcode)) {
            $compmaps = $DB->get_records('local_labeval_behavior_comp', ['behaviorid' => $rating->behaviorid]);
            if (!empty($compmaps)) {
                // Per ogni competenza associata al comportamento, crea un record
                foreach ($compmaps as $compmap) {
                    $compcode = $compmap->competencycode;
                    $weight = $compmap->weight;
                    
                    // Raggruppa per competenza
                    if (!isset($competencyData[$compcode])) {
                        $competencyData[$compcode] = [
                            'code' => $compcode,
                            'description' => $COMPETENCY_DESCRIPTIONS[$compcode] ?? $compcode,
                            'behaviors' => [],
                            'totalScore' => 0,
                            'maxScore' => 0
                        ];
                    }
                    
                    $competencyData[$compcode]['behaviors'][] = [
                        'behaviorid' => $rating->behaviorid,
                        'behaviordesc' => $behavior->description,
                        'weight' => $weight,
                        'rating' => $rating->rating,
                        'notes' => $rating->notes ?? ''
                    ];
                    
                    $competencyData[$compcode]['totalScore'] += $rating->rating * $weight;
                    $competencyData[$compcode]['maxScore'] += 3 * $weight;
                    
                    // Calcola totali
                    $totalScore += $rating->rating * $weight;
                    $maxScore += 3 * $weight;
                }
                
                // Raggruppa per comportamento
                if (!isset($behaviorData[$rating->behaviorid])) {
                    $behaviorData[$rating->behaviorid] = [
                        'id' => $rating->behaviorid,
                        'description' => $behavior->description,
                        'sortorder' => $behavior->sortorder ?? 0,
                        'competencies' => []
                    ];
                }
                
                foreach ($compmaps as $compmap) {
                    $behaviorData[$rating->behaviorid]['competencies'][] = [
                        'code' => $compmap->competencycode,
                        'description' => $COMPETENCY_DESCRIPTIONS[$compmap->competencycode] ?? $compmap->competencycode,
                        'weight' => $compmap->weight,
                        'rating' => $rating->rating,
                        'notes' => $rating->notes ?? ''
                    ];
                }
                
                if ($rating->rating == 1) {
                    $improvementCount++;
                }
                
                // Salta il resto del ciclo perché abbiamo già processato tutto
                continue;
            }
        }
    }
    
    if (empty($compcode)) continue;
    
    // Trova il peso dalla tabella behavior_comp
    $compmap = $DB->get_record('local_labeval_behavior_comp', [
        'behaviorid' => $rating->behaviorid,
        'competencycode' => $compcode
    ]);
    $weight = $compmap ? $compmap->weight : 1;
    
    // Estrai le note reali (rimuovi il prefisso COMP: se presente)
    $realnotes = $rating->notes ?? '';
    if (strpos($realnotes, 'COMP:') === 0) {
        $parts = explode(':', $realnotes, 3);
        $realnotes = $parts[2] ?? '';
    }
    
    // Raggruppa per competenza
    if (!isset($competencyData[$compcode])) {
        $competencyData[$compcode] = [
            'code' => $compcode,
            'description' => $COMPETENCY_DESCRIPTIONS[$compcode] ?? $compcode,
            'behaviors' => [],
            'totalScore' => 0,
            'maxScore' => 0
        ];
    }
    
    $competencyData[$compcode]['behaviors'][] = [
        'behaviorid' => $rating->behaviorid,
        'behaviordesc' => $behavior->description,
        'weight' => $weight,
        'rating' => $rating->rating,
        'notes' => $realnotes
    ];
    
    $competencyData[$compcode]['totalScore'] += $rating->rating * $weight;
    $competencyData[$compcode]['maxScore'] += 3 * $weight;
    
    // Raggruppa per comportamento
    if (!isset($behaviorData[$rating->behaviorid])) {
        $behaviorData[$rating->behaviorid] = [
            'id' => $rating->behaviorid,
            'description' => $behavior->description,
            'sortorder' => $behavior->sortorder ?? 0,
            'competencies' => []
        ];
    }
    
    $behaviorData[$rating->behaviorid]['competencies'][] = [
        'code' => $compcode,
        'description' => $COMPETENCY_DESCRIPTIONS[$compcode] ?? $compcode,
        'weight' => $weight,
        'rating' => $rating->rating,
        'notes' => $realnotes
    ];
    
    // Calcola totali
    $totalScore += $rating->rating * $weight;
    $maxScore += 3 * $weight;
    
    if ($rating->rating == 1) {
        $improvementCount++;
    }
}

// Calcola percentuali per ogni competenza
foreach ($competencyData as $code => &$data) {
    $data['percentage'] = $data['maxScore'] > 0 ? round(($data['totalScore'] / $data['maxScore']) * 100) : 0;
}
unset($data);

// Ordina comportamenti per sortorder
uasort($behaviorData, function($a, $b) {
    return ($a['sortorder'] ?? 0) - ($b['sortorder'] ?? 0);
});

// Calcola percentuale globale
$globalPercentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;

// Ordina competenze per codice
ksort($competencyData);

// Output
echo $OUTPUT->header();

echo local_labeval_get_common_styles();

// Includi Chart.js
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
?>

<style>
/* === STILI REPORT === */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
}

.student-info { flex: 1; }
.template-info { text-align: right; }

/* Radar container */
.radar-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
}

/* === RIQUADRI ESPANDIBILI === */
.expandable-section {
    margin-bottom: 10px;
}

.expandable-header {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.expandable-header:hover {
    background: #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.expandable-header.expanded {
    border-radius: 10px 10px 0 0;
    border-bottom: none;
    background: #e3f2fd;
}

.expandable-title {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.expandable-title .number {
    display: inline-block;
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #17a2b8, #0dcaf0);
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 28px;
    font-weight: 600;
    font-size: 13px;
}

.expandable-title .text {
    font-weight: 500;
    color: #333;
    flex: 1;
}

.expandable-title .code {
    font-size: 12px;
    color: #0d6efd;
    font-weight: 600;
}

.expandable-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.expandable-score {
    font-weight: 700;
    font-size: 16px;
}

.expandable-score.high { color: #28a745; }
.expandable-score.medium { color: #ffc107; }
.expandable-score.low { color: #dc3545; }

.expandable-arrow {
    font-size: 18px;
    transition: transform 0.3s;
    color: #666;
}

.expandable-header.expanded .expandable-arrow {
    transform: rotate(180deg);
}

.expandable-content {
    display: none;
    background: white;
    border: 1px solid #e9ecef;
    border-top: none;
    border-radius: 0 0 10px 10px;
    padding: 20px;
    animation: slideDown 0.3s ease;
}

.expandable-content.show {
    display: block;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Dettaglio competenza */
.competency-detail-item {
    background: #f8fbff;
    border: 1px solid #e3edf7;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.competency-detail-item:last-child {
    margin-bottom: 0;
}

.competency-detail-left {
    flex: 1;
}

.competency-detail-behavior {
    font-size: 13px;
    color: #333;
    margin-bottom: 4px;
}

.competency-detail-weight {
    font-size: 11px;
    color: #666;
}

.competency-detail-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stars {
    font-size: 16px;
}

.stars.rating-0 { color: #6c757d; }
.stars.rating-1 { color: #ffc107; }
.stars.rating-3 { color: #28a745; }

/* Calcolo formula */
.calculation-box {
    background: #f0f7ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 12px 15px;
    margin-top: 15px;
    font-size: 13px;
}

.calculation-box strong {
    color: #0d6efd;
}

/* Progress bar inline */
.progress-inline {
    width: 100px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-inline-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.progress-inline-fill.high { background: linear-gradient(135deg, #28a745, #20c997); }
.progress-inline-fill.medium { background: linear-gradient(135deg, #ffc107, #fd7e14); }
.progress-inline-fill.low { background: linear-gradient(135deg, #dc3545, #c82333); }

/* Note box */
.notes-box {
    background: #fff8e1;
    border-left: 4px solid #ffc107;
    padding: 10px 15px;
    margin-top: 10px;
    border-radius: 0 8px 8px 0;
    font-size: 13px;
    color: #666;
}

.notes-box strong {
    color: #856404;
}

/* Legenda */
.legend-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
}

.legend-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.legend-items {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

/* Messaggio nessun dato */
.no-data-message {
    text-align: center;
    padding: 40px;
    color: #666;
    background: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
}

.no-data-message p {
    margin: 10px 0;
}
</style>

<div class="labeval-container">
    
    <!-- Header -->
    <div class="card">
        <div class="card-header primary">
            <div class="report-header">
                <div class="student-info">
                    <h2 style="margin: 0;">📊 Report Valutazione Prova Pratica</h2>
                    <p style="margin: 10px 0 0; opacity: 0.9;">
                        <strong>Studente:</strong> <?php echo $session->studentfirst . ' ' . $session->studentlast; ?> 
                    </p>
                </div>
                <div class="template-info">
                    <span class="badge badge-info" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo $session->sectorcode; ?>
                    </span>
                    <p style="margin: 10px 0 0; opacity: 0.9;">
                        <strong><?php echo $session->templatename; ?></strong><br>
                        <small>Valutato il <?php echo userdate($session->timecompleted, '%d/%m/%Y'); ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats riassuntive -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="number"><?php echo $globalPercentage; ?>%</div>
            <div class="label">Punteggio Globale</div>
        </div>
        <div class="stat-card info">
            <div class="number"><?php echo count($competencyData); ?></div>
            <div class="label">Competenze Valutate</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo count($behaviorData); ?></div>
            <div class="label">Comportamenti</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?php echo $improvementCount; ?></div>
            <div class="label">Da Migliorare</div>
        </div>
    </div>
    
    <!-- Legenda -->
    <div class="legend-box">
        <div class="legend-title">📖 Legenda Valutazioni</div>
        <div class="legend-items">
            <div class="legend-item">
                <span class="stars rating-0">○</span>
                <span><strong>0</strong> - Non osservato / N/A</span>
            </div>
            <div class="legend-item">
                <span class="stars rating-1">★</span>
                <span><strong>1</strong> - Da migliorare</span>
            </div>
            <div class="legend-item">
                <span class="stars rating-3">★★★</span>
                <span><strong>3</strong> - Adeguato / Competente</span>
            </div>
        </div>
    </div>
    
    <?php if (empty($competencyData)): ?>
    <!-- Messaggio nessun dato -->
    <div class="no-data-message">
        <h3>⚠️ Nessun dato di valutazione trovato</h3>
        <p>Questa valutazione non contiene dati delle competenze.</p>
        <p>Potrebbe essere una valutazione creata con la versione precedente del plugin.</p>
        <p><strong>Soluzione:</strong> Crea una nuova valutazione per questo studente.</p>
    </div>
    
    <?php else: ?>
    
    <!-- RADAR COMPETENZE -->
    <div class="card">
        <div class="card-header purple">
            <h3 style="margin: 0;">🎯 Radar Competenze (<?php echo count($competencyData); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="radar-container">
                <canvas id="radarCompetenze"></canvas>
            </div>
            <p style="text-align: center; color: #666; font-size: 13px; margin-top: 15px;">
                <em>Ogni punta rappresenta una competenza. Il valore è la media pesata delle valutazioni ricevute.</em>
            </p>
        </div>
    </div>
    
    <!-- SEZIONE DETTAGLI COMPETENZE (Espandibili) -->
    <div class="card">
        <div class="card-header info">
            <h3 style="margin: 0;">📋 Dettaglio Competenze</h3>
            <p style="margin: 5px 0 0; opacity: 0.9; font-size: 13px;">Clicca su una competenza per vedere come è stata calcolata</p>
        </div>
        <div class="card-body">
            
            <?php foreach ($competencyData as $code => $data): 
                $scoreClass = $data['percentage'] >= 70 ? 'high' : ($data['percentage'] >= 50 ? 'medium' : 'low');
            ?>
            <div class="expandable-section">
                <div class="expandable-header" onclick="toggleExpand(this)">
                    <div class="expandable-title">
                        <span class="code"><?php echo $code; ?></span>
                        <span class="text"><?php echo $data['description']; ?></span>
                    </div>
                    <div class="expandable-right">
                        <div class="progress-inline">
                            <div class="progress-inline-fill <?php echo $scoreClass; ?>" style="width: <?php echo $data['percentage']; ?>%;"></div>
                        </div>
                        <span class="expandable-score <?php echo $scoreClass; ?>"><?php echo $data['percentage']; ?>%</span>
                        <span class="expandable-arrow">▼</span>
                    </div>
                </div>
                <div class="expandable-content">
                    <p style="margin: 0 0 15px; color: #666; font-size: 13px;">
                        Questa competenza è stata valutata in <strong><?php echo count($data['behaviors']); ?> comportamenti</strong> con i seguenti risultati:
                    </p>
                    
                    <?php foreach ($data['behaviors'] as $beh): 
                        $ratingClass = 'rating-' . $beh['rating'];
                        $ratingStars = $beh['rating'] == 0 ? '○' : ($beh['rating'] == 1 ? '★' : '★★★');
                        $ratingLabel = $beh['rating'] == 0 ? 'N/A' : ($beh['rating'] == 1 ? 'Da migliorare' : 'Adeguato');
                        $badgeClass = $beh['rating'] == 0 ? 'secondary' : ($beh['rating'] == 1 ? 'warning' : 'success');
                        $weightLabel = $beh['weight'] == 3 ? 'principale' : 'secondario';
                    ?>
                    <div class="competency-detail-item">
                        <div class="competency-detail-left">
                            <div class="competency-detail-behavior"><?php echo $beh['behaviordesc']; ?></div>
                            <div class="competency-detail-weight">Peso <?php echo $beh['weight']; ?> (<?php echo $weightLabel; ?>)</div>
                        </div>
                        <div class="competency-detail-right">
                            <span class="stars <?php echo $ratingClass; ?>"><?php echo $ratingStars; ?></span>
                            <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo $ratingLabel; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="calculation-box">
                        <strong>📐 Calcolo punteggio:</strong><br>
                        Σ(rating × peso) ÷ Σ(peso × 3) × 100 = <?php echo $data['totalScore']; ?> ÷ <?php echo $data['maxScore']; ?> × 100 = <strong><?php echo $data['percentage']; ?>%</strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
    </div>
    
    <!-- SEZIONE DETTAGLI COMPORTAMENTI (Espandibili) -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
            <h3 style="margin: 0;">📝 Dettaglio Comportamenti</h3>
            <p style="margin: 5px 0 0; opacity: 0.9; font-size: 13px;">Clicca su un comportamento per vedere le competenze valutate</p>
        </div>
        <div class="card-body">
            
            <?php 
            $behaviorNum = 1;
            foreach ($behaviorData as $behid => $beh): 
            ?>
            <div class="expandable-section">
                <div class="expandable-header" onclick="toggleExpand(this)">
                    <div class="expandable-title">
                        <span class="number"><?php echo $behaviorNum++; ?></span>
                        <span class="text"><?php echo $beh['description']; ?></span>
                    </div>
                    <div class="expandable-right">
                        <span class="badge badge-info"><?php echo count($beh['competencies']); ?> competenze</span>
                        <span class="expandable-arrow">▼</span>
                    </div>
                </div>
                <div class="expandable-content">
                    <p style="margin: 0 0 15px; color: #666; font-size: 13px;">
                        In questo comportamento sono state valutate <strong><?php echo count($beh['competencies']); ?> competenze</strong>:
                    </p>
                    
                    <?php 
                    $hasNotes = false;
                    $noteText = '';
                    foreach ($beh['competencies'] as $comp): 
                        $ratingClass = 'rating-' . $comp['rating'];
                        $ratingStars = $comp['rating'] == 0 ? '○' : ($comp['rating'] == 1 ? '★' : '★★★');
                        $ratingLabel = $comp['rating'] == 0 ? 'N/A' : ($comp['rating'] == 1 ? 'Da migliorare' : 'Adeguato');
                        $badgeClass = $comp['rating'] == 0 ? 'secondary' : ($comp['rating'] == 1 ? 'warning' : 'success');
                        $weightLabel = $comp['weight'] == 3 ? 'principale' : 'secondario';
                        
                        if (!empty($comp['notes'])) {
                            $hasNotes = true;
                            $noteText = $comp['notes'];
                        }
                    ?>
                    <div class="competency-detail-item">
                        <div class="competency-detail-left">
                            <div class="competency-detail-behavior" style="font-weight: 600; color: #0d6efd;"><?php echo $comp['description']; ?></div>
                            <div class="competency-detail-weight"><?php echo $comp['code']; ?> • Peso <?php echo $comp['weight']; ?> (<?php echo $weightLabel; ?>)</div>
                        </div>
                        <div class="competency-detail-right">
                            <span class="stars <?php echo $ratingClass; ?>"><?php echo $ratingStars; ?></span>
                            <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo $ratingLabel; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($hasNotes): ?>
                    <div class="notes-box">
                        <strong>📝 Note del coach:</strong> <?php echo s($noteText); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- Note generali -->
    <?php if (!empty($session->notes)): ?>
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">📝 Note Generali della Valutazione</h3>
        </div>
        <div class="card-body">
            <div class="notes-box" style="background: #f8f9fa; border-left-color: #17a2b8;">
                <?php echo nl2br(s($session->notes)); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Bottoni azione -->
    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; flex-wrap: wrap;">
        <a href="<?php echo new moodle_url('/local/labeval/assignments.php'); ?>" class="btn btn-secondary">← Torna alla lista</a>
        <a href="<?php echo new moodle_url('/local/labeval/export_pdf.php', ['sessionid' => $sessionid]); ?>" class="btn btn-primary">📄 Esporta PDF</a>
    </div>
    
</div>

<script>
// Toggle espansione riquadri
function toggleExpand(header) {
    const content = header.nextElementSibling;
    const isExpanded = header.classList.contains('expanded');
    
    if (isExpanded) {
        header.classList.remove('expanded');
        content.classList.remove('show');
    } else {
        header.classList.add('expanded');
        content.classList.add('show');
    }
}

<?php if (!empty($competencyData)): ?>
// Radar Chart
const ctx = document.getElementById('radarCompetenze').getContext('2d');
new Chart(ctx, {
    type: 'radar',
    data: {
        labels: <?php echo json_encode(array_keys($competencyData)); ?>,
        datasets: [{
            label: 'Punteggio %',
            data: <?php echo json_encode(array_values(array_map(function($d) { return $d['percentage']; }, $competencyData))); ?>,
            backgroundColor: 'rgba(155, 89, 182, 0.2)',
            borderColor: 'rgba(155, 89, 182, 1)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(155, 89, 182, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(155, 89, 182, 1)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    stepSize: 20
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
<?php endif; ?>
</script>

<?php
echo $OUTPUT->footer();
