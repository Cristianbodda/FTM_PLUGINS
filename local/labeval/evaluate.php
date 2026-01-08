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
 * Evaluate student - behavior rating form
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

// Load existing ratings
$existingratings = $DB->get_records('local_labeval_ratings', ['sessionid' => $session->id], '', 'behaviorid, rating, notes');

// Handle form submission
if ($action === 'save' || $action === 'complete') {
    require_sesskey();
    
    // Save all ratings
    foreach ($template->behaviors as $behavior) {
        $rating = optional_param('rating_' . $behavior->id, -1, PARAM_INT);
        $notes = optional_param('notes_' . $behavior->id, '', PARAM_TEXT);
        
        if ($rating >= 0) {
            api::save_rating($session->id, $behavior->id, $rating, $notes);
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

.behavior-card.rated {
    border-left: 4px solid #28a745;
}

.behavior-card.unrated {
    border-left: 4px solid #ffc107;
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

.competencies-list {
    margin-bottom: 15px;
    padding: 10px 15px;
    background: #f0f7ff;
    border-radius: 8px;
}

.competency-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    font-size: 13px;
}

.competency-code {
    font-weight: 600;
    color: #0d6efd;
}

.competency-weight {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
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
    gap: 20px;
    flex-wrap: wrap;
}

.rating-buttons {
    display: flex;
    gap: 10px;
}

.rating-btn {
    padding: 12px 20px;
    border: 2px solid #ddd;
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    min-width: 100px;
}

.rating-btn:hover {
    border-color: #28a745;
    transform: scale(1.05);
}

.rating-btn.selected {
    border-color: #28a745;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.rating-btn.rating-0.selected {
    background: #6c757d;
    border-color: #6c757d;
}

.rating-btn.rating-1.selected {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    border-color: #ffc107;
    color: #333;
}

.rating-btn input {
    display: none;
}

.rating-stars {
    font-size: 18px;
    display: block;
    margin-bottom: 5px;
}

.rating-label {
    font-size: 11px;
    display: block;
}

.notes-input {
    flex: 1;
    min-width: 200px;
}

.notes-input input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 13px;
}

.notes-input input:focus {
    border-color: #28a745;
    outline: none;
}

.progress-indicator {
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    padding: 15px 20px;
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
            <span><strong id="ratedCount"><?php echo count($existingratings); ?></strong> / <?php echo count($template->behaviors); ?> comportamenti valutati</span>
            <span id="progressPercent"><?php echo count($template->behaviors) > 0 ? round(count($existingratings) / count($template->behaviors) * 100) : 0; ?>%</span>
        </div>
        <div class="progress-bar-container">
            <div class="progress-fill" id="progressBar" style="width: <?php echo count($template->behaviors) > 0 ? round(count($existingratings) / count($template->behaviors) * 100) : 0; ?>%;"></div>
        </div>
    </div>
    
    <!-- Evaluation Form -->
    <form method="post" action="<?php echo $PAGE->url; ?>" id="evaluationForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <?php 
        $behaviornum = 1;
        foreach ($template->behaviors as $behavior): 
            $existing = $existingratings[$behavior->id] ?? null;
            $israted = $existing !== null;
        ?>
        <div class="behavior-card <?php echo $israted ? 'rated' : 'unrated'; ?>" data-behaviorid="<?php echo $behavior->id; ?>">
            <div class="behavior-header">
                <span class="behavior-number"><?php echo $behaviornum++; ?></span>
                <span class="behavior-text"><?php echo $behavior->description; ?></span>
            </div>
            <div class="behavior-body">
                
                <!-- Competencies -->
                <?php if (!empty($behavior->competencies)): ?>
                <div class="competencies-list">
                    <small style="color: #666; display: block; margin-bottom: 5px;">Competenze associate:</small>
                    <?php foreach ($behavior->competencies as $comp): ?>
                    <div class="competency-item">
                        <span class="competency-code"><?php echo $comp->competencycode; ?></span>
                        <span class="competency-weight weight-<?php echo $comp->weight; ?>">
                            Peso <?php echo $comp->weight; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Rating -->
                <div class="rating-row">
                    <div class="rating-buttons">
                        <label class="rating-btn rating-0 <?php echo ($existing && $existing->rating == 0) ? 'selected' : ''; ?>">
                            <input type="radio" name="rating_<?php echo $behavior->id; ?>" value="0" 
                                   <?php echo ($existing && $existing->rating == 0) ? 'checked' : ''; ?>
                                   onchange="updateProgress()">
                            <span class="rating-stars">○</span>
                            <span class="rating-label">N/A</span>
                        </label>
                        
                        <label class="rating-btn rating-1 <?php echo ($existing && $existing->rating == 1) ? 'selected' : ''; ?>">
                            <input type="radio" name="rating_<?php echo $behavior->id; ?>" value="1"
                                   <?php echo ($existing && $existing->rating == 1) ? 'checked' : ''; ?>
                                   onchange="updateProgress()">
                            <span class="rating-stars">★</span>
                            <span class="rating-label">Da migliorare</span>
                        </label>
                        
                        <label class="rating-btn rating-3 <?php echo ($existing && $existing->rating == 3) ? 'selected' : ''; ?>">
                            <input type="radio" name="rating_<?php echo $behavior->id; ?>" value="3"
                                   <?php echo ($existing && $existing->rating == 3) ? 'checked' : ''; ?>
                                   onchange="updateProgress()">
                            <span class="rating-stars">★★★</span>
                            <span class="rating-label">Adeguato</span>
                        </label>
                    </div>
                    
                    <div class="notes-input">
                        <input type="text" name="notes_<?php echo $behavior->id; ?>" 
                               placeholder="Note opzionali..."
                               value="<?php echo $existing ? s($existing->notes) : ''; ?>">
                    </div>
                </div>
                
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
// Update rating button selection visual
document.querySelectorAll('.rating-btn input').forEach(function(input) {
    input.addEventListener('change', function() {
        var card = this.closest('.behavior-card');
        var buttons = card.querySelectorAll('.rating-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('selected');
        });
        this.closest('.rating-btn').classList.add('selected');
        card.classList.remove('unrated');
        card.classList.add('rated');
    });
});

// Update progress indicator
function updateProgress() {
    var total = <?php echo count($template->behaviors); ?>;
    var rated = document.querySelectorAll('.behavior-card.rated').length;
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
