<?php
/**
 * Coach Evaluation Page - Valutazione Formatore
 *
 * Interfaccia per i coach per valutare le competenze degli studenti
 * usando scala Bloom (1-6) + N/O
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/coach_evaluation_manager.php');
require_once(__DIR__ . '/area_mapping.php');

use local_competencymanager\coach_evaluation_manager;

require_login();

$context = context_system::instance();
require_capability('local/competencymanager:evaluate', $context);

// Parameters
$studentid = required_param('studentid', PARAM_INT);
$sector = required_param('sector', PARAM_ALPHANUMEXT);
$evaluationid = optional_param('evaluationid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Validate student
$student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

// Validate sector
$sector = strtoupper(trim($sector));
$validSectors = ['AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITÀ', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE', 'GENERICO'];
if (!in_array($sector, $validSectors)) {
    throw new moodle_exception('invalid_sector', 'local_competencymanager');
}

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/coach_evaluation.php', [
    'studentid' => $studentid,
    'sector' => $sector,
    'evaluationid' => $evaluationid
]));
$PAGE->set_title(get_string('coach_evaluation_title', 'local_competencymanager'));
$PAGE->set_heading(get_string('evaluation_for', 'local_competencymanager', fullname($student)));
$PAGE->set_pagelayout('standard');

// Get or create evaluation
if ($evaluationid) {
    $evaluation = coach_evaluation_manager::get_evaluation($evaluationid);
    if (!$evaluation) {
        throw new moodle_exception('invalid_evaluation', 'local_competencymanager');
    }
    // Check view permission
    if (!coach_evaluation_manager::can_view($evaluationid)) {
        throw new moodle_exception('no_permission', 'local_competencymanager');
    }
} else {
    // Get or create draft evaluation
    $evaluationid = coach_evaluation_manager::get_or_create_evaluation(
        $studentid,
        $USER->id,
        $sector,
        $courseid
    );
    $evaluation = coach_evaluation_manager::get_evaluation($evaluationid);
}

$canEdit = coach_evaluation_manager::can_edit($evaluationid);

// Handle actions
if ($action && confirm_sesskey() && $canEdit) {
    switch ($action) {
        case 'complete':
            coach_evaluation_manager::complete_evaluation($evaluationid);
            redirect($PAGE->url, get_string('evaluation_completed_msg', 'local_competencymanager'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'sign':
            coach_evaluation_manager::sign_evaluation($evaluationid);
            redirect($PAGE->url, get_string('evaluation_signed_msg', 'local_competencymanager'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'delete':
            if (coach_evaluation_manager::delete_evaluation($evaluationid)) {
                $returnurl = new moodle_url('/local/competencymanager/student_report.php', [
                    'userid' => $studentid,
                    'courseid' => $courseid
                ]);
                redirect($returnurl, get_string('evaluation_deleted', 'local_competencymanager'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            break;

        case 'authorize':
            coach_evaluation_manager::set_student_can_view($evaluationid, true);
            redirect($PAGE->url, get_string('student_authorized', 'local_competencymanager'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'revoke':
            coach_evaluation_manager::set_student_can_view($evaluationid, false);
            redirect($PAGE->url, get_string('student_revoked', 'local_competencymanager'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Get competencies for this sector
$competencies = $DB->get_records_sql(
    "SELECT c.id, c.idnumber, c.shortname, c.description
     FROM {competency} c
     JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
     WHERE c.idnumber LIKE :pattern
     AND cf.idnumber IN ('FTM-01', 'FTM_GEN')
     ORDER BY c.idnumber",
    ['pattern' => $sector . '_%']
);

// If sector is ELETTRICITÀ, also try without accent
if (empty($competencies) && $sector === 'ELETTRICITÀ') {
    $competencies = $DB->get_records_sql(
        "SELECT c.id, c.idnumber, c.shortname, c.description
         FROM {competency} c
         JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
         WHERE (c.idnumber LIKE :pattern1 OR c.idnumber LIKE :pattern2)
         AND cf.idnumber IN ('FTM-01', 'FTM_GEN')
         ORDER BY c.idnumber",
        ['pattern1' => 'ELETTRICITÀ_%', 'pattern2' => 'ELETTRICITA_%']
    );
}

// Group competencies by area con nome completo
$competenciesByArea = [];
$areaFullNames = []; // Mappa codice area -> nome completo

foreach ($competencies as $comp) {
    // Usa get_area_info() per ottenere codice e nome completo
    $areaInfo = get_area_info($comp->idnumber);
    $areaCode = $areaInfo['code'];
    $areaFullName = $areaInfo['name'];

    if (!isset($competenciesByArea[$areaCode])) {
        $competenciesByArea[$areaCode] = [];
        $areaFullNames[$areaCode] = $areaFullName;
    }
    $competenciesByArea[$areaCode][] = $comp;
}
ksort($competenciesByArea);

// Get existing ratings
$existingRatings = [];
$ratings = coach_evaluation_manager::get_evaluation_ratings($evaluationid);
foreach ($ratings as $r) {
    $existingRatings[$r->competencyid] = $r;
}

// Initialize missing ratings to N/O (0) so they appear in overlay radar
// This ensures all competencies have a rating record in the database
$ratingsInitialized = 0;
foreach ($competencies as $comp) {
    if (!isset($existingRatings[$comp->id])) {
        // Create N/O rating for this competency
        $ratingId = coach_evaluation_manager::save_rating($evaluationid, $comp->id, 0, null);
        // Add to existingRatings so it shows correctly in the form
        $newRating = new stdClass();
        $newRating->id = $ratingId;
        $newRating->competencyid = $comp->id;
        $newRating->rating = 0;
        $newRating->notes = null;
        $existingRatings[$comp->id] = $newRating;
        $ratingsInitialized++;
    }
}

// Reload stats after initialization
if ($ratingsInitialized > 0) {
    $stats = coach_evaluation_manager::get_rating_stats($evaluationid);
}

// Get statistics
$stats = coach_evaluation_manager::get_rating_stats($evaluationid);
$average = coach_evaluation_manager::calculate_average($evaluationid);

// Bloom scale
$bloomScale = coach_evaluation_manager::get_bloom_scale();

// CSS and JS
$PAGE->requires->css('/local/competencymanager/styles/coach_evaluation.css');

echo $OUTPUT->header();

// Status banner
$statusClass = 'alert-info';
$statusMsg = '';
switch ($evaluation->status) {
    case 'draft':
        $statusClass = 'alert-warning';
        $statusMsg = get_string('evaluation_draft', 'local_competencymanager');
        break;
    case 'completed':
        $statusClass = 'alert-success';
        $statusMsg = get_string('evaluation_completed', 'local_competencymanager');
        break;
    case 'signed':
        $statusClass = 'alert-secondary';
        $statusMsg = get_string('evaluation_signed', 'local_competencymanager');
        break;
}
?>

<style>
.eval-container { max-width: 1200px; margin: 0 auto; }
.eval-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.eval-header h2 { margin: 0; font-size: 1.5rem; }
.eval-header .student-info { opacity: 0.9; margin-top: 5px; }
.eval-header .sector-badge { background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; display: inline-block; margin-top: 10px; }

.status-banner { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; }
.status-banner.alert-warning { background: #fff3cd; border: 1px solid #ffc107; }
.status-banner.alert-success { background: #d4edda; border: 1px solid #28a745; }
.status-banner.alert-secondary { background: #e2e3e5; border: 1px solid #6c757d; }

.stats-bar { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 30px; flex-wrap: wrap; }
.stat-item { display: flex; align-items: center; gap: 8px; }
.stat-value { font-size: 1.25rem; font-weight: 600; color: #333; }
.stat-label { color: #666; font-size: 0.875rem; }

.area-accordion { margin-bottom: 10px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
.area-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; gap: 15px; }
.area-header:hover { background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%); }
.area-header h3 { margin: 0; font-size: 1rem; color: #333; font-weight: 600; flex: 1; }
.area-header .area-stats { font-size: 0.875rem; color: #666; background: #fff; padding: 4px 10px; border-radius: 12px; white-space: nowrap; }
.area-header .toggle-icon { transition: transform 0.3s; font-size: 0.8rem; }
.area-header.collapsed .toggle-icon { transform: rotate(-90deg); }

.area-content { padding: 0; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out, padding 0.3s; }
.area-content.expanded { max-height: 2000px; padding: 15px 20px; }

.competency-row { display: grid; grid-template-columns: 1.5fr 200px 1fr; gap: 15px; padding: 12px 0; border-bottom: 1px solid #eee; align-items: start; }
.competency-row:last-child { border-bottom: none; }
.comp-info { display: flex; flex-direction: column; gap: 4px; }
.comp-code { font-size: 0.7rem; color: #667eea; font-family: monospace; font-weight: 600; background: #f0f0ff; padding: 2px 6px; border-radius: 4px; display: inline-block; }
.comp-name { font-weight: 600; color: #333; font-size: 0.95rem; margin-top: 4px; }
.comp-desc { font-size: 0.85rem; color: #555; line-height: 1.4; margin-top: 2px; }

.rating-buttons { display: flex; gap: 4px; flex-wrap: wrap; }
.rating-btn { width: 36px; height: 36px; border: 2px solid #495057; background: #f8f9fa; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; color: #212529; font-size: 14px; }
.rating-btn:hover:not(:disabled) { border-color: #667eea; background: #e0e0ff; color: #333; }
.rating-btn.selected { background: #667eea; color: white; border-color: #667eea; }
.rating-btn.no { font-size: 0.65rem; background: #e9ecef; }
.rating-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.notes-input { width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.875rem; resize: vertical; min-height: 36px; }
.notes-input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
.notes-input:disabled { background: #f8f9fa; }

.general-notes-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
.general-notes-section h4 { margin-top: 0; }
.general-notes-section textarea { width: 100%; min-height: 100px; }

.action-bar { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; justify-content: space-between; }
.action-group { display: flex; gap: 10px; flex-wrap: wrap; }

.btn-eval { padding: 10px 20px; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; }
.btn-save { background: #667eea; color: white; }
.btn-save:hover { background: #5a6fd6; }
.btn-complete { background: #28a745; color: white; }
.btn-complete:hover { background: #218838; }
.btn-sign { background: #dc3545; color: white; }
.btn-sign:hover { background: #c82333; }
.btn-delete { background: #6c757d; color: white; }
.btn-delete:hover { background: #5a6268; }
.btn-auth { background: #17a2b8; color: white; }
.btn-auth:hover { background: #138496; }

.bloom-legend { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; }
.bloom-legend h4 { margin-top: 0; margin-bottom: 15px; font-size: 1rem; }
.bloom-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; }
.bloom-item { display: flex; align-items: flex-start; gap: 10px; }
.bloom-num { width: 28px; height: 28px; background: #667eea; color: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.bloom-num.no { background: #6c757d; font-size: 0.7rem; }
.bloom-desc { font-size: 0.875rem; color: #666; }
.bloom-title { font-weight: 500; color: #333; }

.saving-indicator { position: fixed; bottom: 20px; right: 20px; background: #667eea; color: white; padding: 10px 20px; border-radius: 8px; display: none; z-index: 1000; }
.saving-indicator.show { display: block; animation: fadeInOut 2s; }
@keyframes fadeInOut { 0% { opacity: 0; } 20% { opacity: 1; } 80% { opacity: 1; } 100% { opacity: 0; } }
</style>

<div class="eval-container">
    <!-- Header -->
    <div class="eval-header">
        <h2><?php echo get_string('coach_evaluation_title', 'local_competencymanager'); ?></h2>
        <div class="student-info"><?php echo fullname($student); ?> (<?php echo $student->email; ?>)</div>
        <div class="sector-badge"><?php echo $sector; ?></div>
    </div>

    <!-- Status Banner -->
    <div class="status-banner <?php echo $statusClass; ?>">
        <?php echo $statusMsg; ?>
        <?php if ($evaluation->evaluation_date): ?>
            <br><small><?php echo get_string('evaluation_date', 'local_competencymanager', userdate($evaluation->evaluation_date)); ?></small>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value"><?php echo $stats['rated']; ?>/<?php echo $stats['total']; ?></div>
            <div class="stat-label"><?php echo get_string('competencies_rated', 'local_competencymanager', (object)$stats); ?></div>
        </div>
        <?php if ($stats['not_observed'] > 0): ?>
        <div class="stat-item">
            <div class="stat-value"><?php echo $stats['not_observed']; ?></div>
            <div class="stat-label">N/O</div>
        </div>
        <?php endif; ?>
        <?php if ($average): ?>
        <div class="stat-item">
            <div class="stat-value"><?php echo $average; ?></div>
            <div class="stat-label"><?php echo get_string('average_rating', 'local_competencymanager', ''); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bloom Legend (collapsible) -->
    <details class="bloom-legend">
        <summary><h4 style="display:inline; cursor:pointer;"><?php echo get_string('bloom_scale', 'local_competencymanager'); ?> ▼</h4></summary>
        <div class="bloom-grid">
            <?php foreach ($bloomScale as $num => $info): ?>
            <div class="bloom-item">
                <div class="bloom-num <?php echo $num === 0 ? 'no' : ''; ?>"><?php echo $num === 0 ? 'N/O' : $num; ?></div>
                <div>
                    <div class="bloom-title"><?php echo $info['label']; ?></div>
                    <div class="bloom-desc"><?php echo $info['description']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- Competency Areas -->
    <form id="evaluation-form" method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="evaluationid" value="<?php echo $evaluationid; ?>">

        <?php foreach ($competenciesByArea as $area => $comps): ?>
            <?php
            // Count rated in this area
            $areaRated = 0;
            foreach ($comps as $c) {
                if (isset($existingRatings[$c->id]) && $existingRatings[$c->id]->rating >= 0) {
                    $areaRated++;
                }
            }
            // Nome completo dell'area (es. "A. Accoglienza, diagnosi...")
            $areaDisplayName = $areaFullNames[$area] ?? "Area $area";
            ?>
            <div class="area-accordion">
                <div class="area-header" onclick="toggleArea(this)">
                    <h3><?php echo s($areaDisplayName); ?></h3>
                    <div>
                        <span class="area-stats"><?php echo $areaRated; ?>/<?php echo count($comps); ?></span>
                        <span class="toggle-icon">▼</span>
                    </div>
                </div>
                <div class="area-content expanded">
                    <?php foreach ($comps as $comp): ?>
                        <?php
                        $currentRating = isset($existingRatings[$comp->id]) ? $existingRatings[$comp->id]->rating : -1;
                        $currentNotes = isset($existingRatings[$comp->id]) ? $existingRatings[$comp->id]->notes : '';
                        ?>
                        <?php
                        // Estrai descrizione pulita
                        $cleanDesc = strip_tags($comp->description ?? '');
                        $cleanDesc = html_entity_decode($cleanDesc, ENT_QUOTES, 'UTF-8');
                        $cleanDesc = trim($cleanDesc);
                        // Se la descrizione è uguale allo shortname o vuota, non mostrarla
                        $showDesc = !empty($cleanDesc) && $cleanDesc !== $comp->shortname;
                        ?>
                        <div class="competency-row" data-compid="<?php echo $comp->id; ?>">
                            <div class="comp-info">
                                <div class="comp-code"><?php echo s($comp->idnumber); ?></div>
                                <div class="comp-name"><?php echo format_string($comp->shortname); ?></div>
                                <?php if ($showDesc): ?>
                                <div class="comp-desc"><?php echo s($cleanDesc); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="rating-buttons">
                                <?php for ($i = 0; $i <= 6; $i++): ?>
                                    <button type="button"
                                            class="rating-btn <?php echo $i === 0 ? 'no' : ''; ?> <?php echo $currentRating === $i ? 'selected' : ''; ?>"
                                            data-rating="<?php echo $i; ?>"
                                            onclick="selectRating(<?php echo $comp->id; ?>, <?php echo $i; ?>, this)"
                                            <?php echo !$canEdit ? 'disabled' : ''; ?>
                                            title="<?php echo s($bloomScale[$i]['description']); ?>">
                                        <?php echo $i === 0 ? 'N/O' : $i; ?>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <div>
                                <input type="text"
                                       class="notes-input"
                                       name="notes[<?php echo $comp->id; ?>]"
                                       placeholder="<?php echo get_string('notes_placeholder', 'local_competencymanager'); ?>"
                                       value="<?php echo s($currentNotes); ?>"
                                       onchange="saveRating(<?php echo $comp->id; ?>)"
                                       <?php echo !$canEdit ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- General Notes -->
        <div class="general-notes-section">
            <h4><?php echo get_string('general_notes', 'local_competencymanager'); ?></h4>
            <p class="text-muted"><?php echo get_string('general_notes_help', 'local_competencymanager'); ?></p>
            <textarea name="general_notes"
                      class="notes-input"
                      id="general-notes"
                      onchange="saveGeneralNotes()"
                      <?php echo !$canEdit ? 'disabled' : ''; ?>><?php echo s($evaluation->notes); ?></textarea>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="action-group">
                <?php if ($canEdit && $evaluation->status === 'draft'): ?>
                    <button type="button" class="btn-eval btn-save" onclick="saveAllRatings()">
                        <?php echo get_string('save_draft', 'local_competencymanager'); ?>
                    </button>
                    <a href="<?php echo $PAGE->url; ?>&action=complete&sesskey=<?php echo sesskey(); ?>"
                       class="btn-eval btn-complete"
                       onclick="return confirm('<?php echo get_string('sign_confirm', 'local_competencymanager'); ?>');">
                        <?php echo get_string('save_and_complete', 'local_competencymanager'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($canEdit && $evaluation->status === 'completed'): ?>
                    <a href="<?php echo $PAGE->url; ?>&action=sign&sesskey=<?php echo sesskey(); ?>"
                       class="btn-eval btn-sign"
                       onclick="return confirm('<?php echo get_string('sign_confirm', 'local_competencymanager'); ?>');">
                        <?php echo get_string('sign_evaluation', 'local_competencymanager'); ?>
                    </a>
                <?php endif; ?>

                <?php if (has_capability('local/competencymanager:authorizestudentview', $context)): ?>
                    <?php if (!$evaluation->student_can_view): ?>
                        <a href="<?php echo $PAGE->url; ?>&action=authorize&sesskey=<?php echo sesskey(); ?>" class="btn-eval btn-auth">
                            <?php echo get_string('authorize_student', 'local_competencymanager'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $PAGE->url; ?>&action=revoke&sesskey=<?php echo sesskey(); ?>" class="btn-eval btn-delete">
                            <?php echo get_string('revoke_student', 'local_competencymanager'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="action-group">
                <?php if ($canEdit && $evaluation->status === 'draft'): ?>
                    <a href="<?php echo $PAGE->url; ?>&action=delete&sesskey=<?php echo sesskey(); ?>"
                       class="btn-eval btn-delete"
                       onclick="return confirm('<?php echo get_string('delete_confirm', 'local_competencymanager'); ?>');">
                        <?php echo get_string('delete_evaluation', 'local_competencymanager'); ?>
                    </a>
                <?php endif; ?>

                <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', ['userid' => $studentid, 'courseid' => $courseid]); ?>"
                   class="btn-eval" style="background:#6c757d;color:white;">
                    ← Torna al Report
                </a>
            </div>
        </div>
    </form>
</div>

<div class="saving-indicator" id="saving-indicator">Salvando...</div>

<script>
const evaluationId = <?php echo $evaluationid; ?>;
const sesskey = '<?php echo sesskey(); ?>';
const ajaxUrl = M.cfg.wwwroot + '/local/competencymanager/ajax_save_evaluation.php';
let pendingRatings = {};
let saveTimer = null;

function toggleArea(header) {
    header.classList.toggle('collapsed');
    const content = header.nextElementSibling;
    content.classList.toggle('expanded');
}

function selectRating(compId, rating, btn) {
    // Update UI
    const row = btn.closest('.competency-row');
    row.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');

    // Queue for save
    const notes = row.querySelector('.notes-input').value;
    pendingRatings[compId] = { rating: rating, notes: notes };

    // Debounced save
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveAllRatings, 500);
}

function saveRating(compId) {
    const row = document.querySelector(`.competency-row[data-compid="${compId}"]`);
    const selectedBtn = row.querySelector('.rating-btn.selected');
    const rating = selectedBtn ? parseInt(selectedBtn.dataset.rating) : -1;
    const notes = row.querySelector('.notes-input').value;

    if (rating >= 0) {
        pendingRatings[compId] = { rating: rating, notes: notes };
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveAllRatings, 500);
    }
}

function saveGeneralNotes() {
    const notes = document.getElementById('general-notes').value;
    showSaving();

    fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_notes&evaluationid=${evaluationId}&notes=${encodeURIComponent(notes)}&sesskey=${sesskey}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Error saving notes');
        }
    })
    .catch(err => console.error('Save error:', err));
}

function saveAllRatings() {
    if (Object.keys(pendingRatings).length === 0) return;

    showSaving();

    const ratings = Object.entries(pendingRatings).map(([compId, data]) => ({
        competencyid: parseInt(compId),
        rating: data.rating,
        notes: data.notes
    }));

    fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_ratings&evaluationid=${evaluationId}&ratings=${encodeURIComponent(JSON.stringify(ratings))}&sesskey=${sesskey}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            pendingRatings = {};
            updateStats(data.stats);
        } else {
            alert(data.message || 'Error saving ratings');
        }
    })
    .catch(err => console.error('Save error:', err));
}

function showSaving() {
    const indicator = document.getElementById('saving-indicator');
    indicator.classList.add('show');
    setTimeout(() => indicator.classList.remove('show'), 2000);
}

function updateStats(stats) {
    if (stats) {
        // Update statistics display if needed
    }
}
</script>

<?php
echo $OUTPUT->footer();
