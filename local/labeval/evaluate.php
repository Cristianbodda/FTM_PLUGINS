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
 * Evaluate student - competency rating form
 * VERSIONE 2.0: Valutazione delle COMPETENZE (non dei comportamenti)
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

// Require login and capability
require_login();
$context = context_system::instance();
require_capability('local/labeval:evaluate', $context);

// Parameters
$assignmentid = required_param('assignmentid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Get assignment details
$assignment = $DB->get_record('local_labeval_assignments', ['id' => $assignmentid], '*', MUST_EXIST);
$template = api::get_template_details($assignment->templateid);
$student = $DB->get_record('user', ['id' => $assignment->studentid], '*', MUST_EXIST);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/evaluate.php', ['assignmentid' => $assignmentid]));
$PAGE->set_title(get_string('evaluatestudent', 'local_labeval') . ' - ' . fullname($student));
$PAGE->set_heading(get_string('evaluatestudent', 'local_labeval'));
$PAGE->set_pagelayout('standard');

// Check for existing session or create new one
$session = $DB->get_record('local_labeval_sessions', [
    'assignmentid' => $assignmentid,
    'status' => 'draft'
]);

if (!$session) {
    // Create new session
    $session = new stdClass();
    $session->assignmentid = $assignmentid;
    $session->assessorid = $USER->id;
    $session->status = 'draft';
    $session->timecreated = time();
    $session->id = $DB->insert_record('local_labeval_sessions', $session);
}

// Conta tutte le competenze uniche nel template per il contatore
$totalcompetencies = 0;
$allcompetencies = [];
foreach ($template->behaviors as $behavior) {
    if (!empty($behavior->competencies)) {
        foreach ($behavior->competencies as $comp) {
            $compkey = $behavior->id . '_' . $comp->id;
            if (!isset($allcompetencies[$compkey])) {
                $allcompetencies[$compkey] = $comp;
                $totalcompetencies++;
            }
        }
    }
}

// Handle form submission
if ($action === 'save' || $action === 'complete') {
    require_sesskey();
    
    // Salva i rating per ogni COMPETENZA usando il nuovo campo competencycode
    foreach ($template->behaviors as $behavior) {
        if (!empty($behavior->competencies)) {
            foreach ($behavior->competencies as $comp) {
                $paramname = 'rating_' . $behavior->id . '_' . $comp->id;
                $notesname = 'notes_' . $behavior->id . '_' . $comp->id;
                $rating = optional_param($paramname, -1, PARAM_INT);
                $notes = optional_param($notesname, '', PARAM_TEXT);
                
                if ($rating >= 0) {
                    // Controlla se esiste già un rating per questa competenza in questo behavior
                    $existing = $DB->get_record('local_labeval_ratings', [
                        'sessionid' => $session->id,
                        'behaviorid' => $behavior->id,
                        'competencycode' => $comp->competencycode
                    ]);
                    
                    if ($existing) {
                        $existing->rating = $rating;
                        $existing->notes = $notes;
                        $DB->update_record('local_labeval_ratings', $existing);
                    } else {
                        $record = new stdClass();
                        $record->sessionid = $session->id;
                        $record->behaviorid = $behavior->id;
                        $record->competencycode = $comp->competencycode;
                        $record->rating = $rating;
                        $record->notes = $notes;
                        $DB->insert_record('local_labeval_ratings', $record);
                    }
                }
            }
        }
    }
    
    // Save general notes
    $generalnotes = optional_param('generalnotes', '', PARAM_TEXT);
    
    if ($action === 'complete') {
        // Complete the evaluation
        api::complete_session($session->id, $generalnotes);
        
        redirect(new moodle_url('/local/labeval/view_evaluation.php', ['sessionid' => $session->id]),
            get_string('evaluationcompleted', 'local_labeval'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Just save draft
        $session->notes = $generalnotes;
        $DB->update_record('local_labeval_sessions', $session);
        
        redirect($PAGE->url, get_string('evaluationsaved', 'local_labeval'), null, \core\output\notification::NOTIFY_INFO);
    }
}

// Carica i rating esistenti per competenza (usando il nuovo campo)
$existingcompratings = [];
$ratedcount = 0;
$allratings = $DB->get_records('local_labeval_ratings', ['sessionid' => $session->id]);
foreach ($allratings as $r) {
    if (!empty($r->competencycode)) {
        $key = $r->behaviorid . '_' . $r->competencycode;
        $existingcompratings[$key] = [
            'rating' => $r->rating,
            'notes' => $r->notes
        ];
        $ratedcount++;
    }
}

// Output
echo $OUTPUT->header();

echo local_labeval_get_common_styles();
?>

<style>
.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
}

.student-info {
    flex: 1;
}

.template-info {
    text-align: right;
}

.behavior-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 15px;
    overflow: hidden;
    transition: all 0.2s;
}

.behavior-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}

.behavior-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.behavior-number {
    display: inline-block;
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, #17a2b8, #0dcaf0);
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    font-weight: 600;
    margin-right: 10px;
}

.behavior-text {
    font-weight: 500;
    color: #333;
}

.behavior-body {
    padding: 20px;
}

/* STILI PER COMPETENZE VALUTABILI */
.competency-eval-card {
    background: #f8fbff;
    border: 1px solid #e3edf7;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 12px;
}

.competency-eval-card:last-child {
    margin-bottom: 0;
}

.competency-eval-card.rated {
    border-left: 4px solid #28a745;
}

.competency-info {
    margin-bottom: 12px;
}

/* Descrizione SOPRA - più grande e in evidenza */
.competency-description {
    font-size: 14px;
    color: #333;
    font-weight: 500;
    margin-bottom: 5px;
}

/* Codice SOTTO - più piccolo */
.competency-code {
    font-weight: 600;
    color: #0d6efd;
    font-size: 12px;
}

.competency-weight {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 8px;
}

.weight-3 {
    background: #28a745;
    color: white;
}

.weight-1 {
    background: #6c757d;
    color: white;
}

.rating-row {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.rating-buttons {
    display: flex;
    gap: 8px;
}

.rating-btn {
    padding: 10px 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    min-width: 90px;
}

.rating-btn:hover {
    border-color: #28a745;
    transform: scale(1.02);
}

.rating-btn.selected {
    transform: scale(1.02);
}

.rating-btn.selected.rating-0 {
    border-color: #6c757d;
    background: #6c757d;
    color: white;
}

.rating-btn.selected.rating-1 {
    border-color: #ffc107;
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #333;
}

.rating-btn.selected.rating-3 {
    border-color: #28a745;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.rating-btn input[type="radio"] {
    display: none;
}

.rating-stars {
    font-size: 16px;
    display: block;
}

.rating-label {
    font-size: 11px;
    display: block;
    margin-top: 2px;
}

.notes-input {
    flex: 1;
    min-width: 200px;
}

.notes-input input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
}

.notes-input input:focus {
    border-color: #28a745;
    outline: none;
}

.progress-indicator {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.progress-bar-container {
    height: 10px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #28a745, #20c997);
    transition: width 0.3s;
}

.action-buttons {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
</style>

<div class="labeval-container">
    
    <!-- Header -->
    <div class="card">
        <div class="card-header primary">
            <div class="evaluation-header">
                <div class="student-info">
                    <h2 style="margin: 0;">✏️ Valutazione Prova Pratica</h2>
                    <p style="margin: 10px 0 0; opacity: 0.9;">
                        <strong>Studente:</strong> <?php echo fullname($student); ?> (<?php echo $student->email; ?>)
                    </p>
                </div>
                <div class="template-info">
                    <span class="badge badge-info" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo $template->sectorcode; ?>
                    </span>
                    <p style="margin: 10px 0 0; opacity: 0.9;">
                        <strong><?php echo $template->name; ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rating Scale Legend -->
    <div class="card">
        <div class="card-header">
            <h3>📊 <?php echo get_string('ratingscale', 'local_labeval'); ?></h3>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">○</span>
                    <span><strong>0</strong> - <?php echo get_string('rating0', 'local_labeval'); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px; color: #ffc107;">★</span>
                    <span><strong>1</strong> - <?php echo get_string('rating1', 'local_labeval'); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px; color: #28a745;">★★★</span>
                    <span><strong>3</strong> - <?php echo get_string('rating3', 'local_labeval'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Progress Indicator -->
    <div class="progress-indicator" id="progressIndicator">
        <div class="progress-stats">
            <span><strong id="ratedCount"><?php echo $ratedcount; ?></strong> / <?php echo $totalcompetencies; ?> <strong>competenze</strong> valutate</span>
            <span id="progressPercent"><?php echo $totalcompetencies > 0 ? round($ratedcount / $totalcompetencies * 100) : 0; ?>%</span>
        </div>
        <div class="progress-bar-container">
            <div class="progress-fill" id="progressBar" style="width: <?php echo $totalcompetencies > 0 ? round($ratedcount / $totalcompetencies * 100) : 0; ?>%;"></div>
        </div>
    </div>
    
    <!-- Evaluation Form -->
    <form method="post" action="<?php echo $PAGE->url; ?>" id="evaluationForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <?php 
        $behaviornum = 1;
        foreach ($template->behaviors as $behavior): 
        ?>
        <div class="behavior-card">
            <!-- COMPORTAMENTO come TITOLO (non valutabile) -->
            <div class="behavior-header">
                <span class="behavior-number"><?php echo $behaviornum++; ?></span>
                <span class="behavior-text"><?php echo $behavior->description; ?></span>
            </div>
            <div class="behavior-body">
                
                <?php if (!empty($behavior->competencies)): ?>
                    <?php foreach ($behavior->competencies as $comp): 
                        $compkey = $behavior->id . '_' . $comp->competencycode;
                        $existing = $existingcompratings[$compkey] ?? null;
                        $israted = $existing !== null;
                        
                        // Ottieni la descrizione dalla variabile globale
                        global $COMPETENCY_DESCRIPTIONS;
                        $compdescription = $COMPETENCY_DESCRIPTIONS[$comp->competencycode] ?? '';
                        
                        // Se non trovata, prova a cercare nel database
                        if (empty($compdescription)) {
                            $compinfo = local_labeval_get_competency_info($comp->competencycode);
                            $compdescription = $compinfo['description'];
                        }
                        
                        // Fallback finale: usa il codice
                        if (empty($compdescription)) {
                            $compdescription = $comp->competencycode;
                        }
                    ?>
                    <!-- COMPETENZA VALUTABILE -->
                    <div class="competency-eval-card <?php echo $israted ? 'rated' : ''; ?>" data-compid="<?php echo $behavior->id . '_' . $comp->id; ?>">
                        <div class="competency-info">
                            <!-- DESCRIZIONE SOPRA (in evidenza) -->
                            <div class="competency-description"><?php echo $compdescription; ?></div>
                            <!-- CODICE SOTTO (più piccolo) -->
                            <span class="competency-code"><?php echo $comp->competencycode; ?></span>
                            <span class="competency-weight weight-<?php echo $comp->weight; ?>">
                                Peso <?php echo $comp->weight; ?>
                            </span>
                        </div>
                        
                        <div class="rating-row">
                            <div class="rating-buttons">
                                <label class="rating-btn rating-0 <?php echo ($existing && $existing['rating'] == 0) ? 'selected' : ''; ?>">
                                    <input type="radio" name="rating_<?php echo $behavior->id; ?>_<?php echo $comp->id; ?>" value="0" 
                                           <?php echo ($existing && $existing['rating'] == 0) ? 'checked' : ''; ?>
                                           onchange="updateProgress(); selectRating(this)">
                                    <span class="rating-stars">○</span>
                                    <span class="rating-label">N/A</span>
                                </label>
                                
                                <label class="rating-btn rating-1 <?php echo ($existing && $existing['rating'] == 1) ? 'selected' : ''; ?>">
                                    <input type="radio" name="rating_<?php echo $behavior->id; ?>_<?php echo $comp->id; ?>" value="1"
                                           <?php echo ($existing && $existing['rating'] == 1) ? 'checked' : ''; ?>
                                           onchange="updateProgress(); selectRating(this)">
                                    <span class="rating-stars">★</span>
                                    <span class="rating-label">Da migliorare</span>
                                </label>
                                
                                <label class="rating-btn rating-3 <?php echo ($existing && $existing['rating'] == 3) ? 'selected' : ''; ?>">
                                    <input type="radio" name="rating_<?php echo $behavior->id; ?>_<?php echo $comp->id; ?>" value="3"
                                           <?php echo ($existing && $existing['rating'] == 3) ? 'checked' : ''; ?>
                                           onchange="updateProgress(); selectRating(this)">
                                    <span class="rating-stars">★★★</span>
                                    <span class="rating-label">Adeguato</span>
                                </label>
                            </div>
                            
                            <div class="notes-input">
                                <input type="text" name="notes_<?php echo $behavior->id; ?>_<?php echo $comp->id; ?>" 
                                       placeholder="Note opzionali..."
                                       value="<?php echo $existing ? s($existing['notes']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #888; font-style: italic;">Nessuna competenza associata a questo comportamento.</p>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- General Notes -->
        <div class="card">
            <div class="card-header">
                <h3>📝 <?php echo get_string('generalnotes', 'local_labeval'); ?></h3>
            </div>
            <div class="card-body">
                <textarea name="generalnotes" class="form-control" rows="4" 
                          placeholder="Osservazioni generali sulla prova..."><?php echo s($session->notes ?? ''); ?></textarea>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <div>
                <a href="<?php echo new moodle_url('/local/labeval/assignments.php'); ?>" class="btn btn-secondary">
                    ← Annulla
                </a>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="action" value="save" class="btn btn-info">
                    💾 Salva Bozza
                </button>
                <button type="submit" name="action" value="complete" class="btn btn-success btn-lg" 
                        onclick="return confirm('<?php echo get_string('confirmevaluation', 'local_labeval'); ?>');">
                    ✅ Completa Valutazione
                </button>
            </div>
        </div>
        
    </form>
    
</div>

<script>
// Seleziona rating (feedback visivo)
function selectRating(input) {
    var card = input.closest('.competency-eval-card');
    var buttons = card.querySelectorAll('.rating-btn');
    buttons.forEach(function(btn) {
        btn.classList.remove('selected');
    });
    input.closest('.rating-btn').classList.add('selected');
    card.classList.add('rated');
}

// Update progress indicator
function updateProgress() {
    var total = <?php echo $totalcompetencies; ?>;
    var rated = document.querySelectorAll('.competency-eval-card .rating-btn.selected').length;
    var percent = total > 0 ? Math.round(rated / total * 100) : 0;
    
    document.getElementById('ratedCount').textContent = rated;
    document.getElementById('progressPercent').textContent = percent + '%';
    document.getElementById('progressBar').style.width = percent + '%';
}

// Initial update
updateProgress();
</script>

<?php
echo $OUTPUT->footer();
