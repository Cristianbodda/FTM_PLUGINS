<?php
/**
 * JobAIDA - Main generator page.
 *
 * Cover letter generator using the AIDA model (Attention, Interest, Desire, Action).
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobaida/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_jobaida'));
$PAGE->set_heading(get_string('pluginname', 'local_jobaida'));
$PAGE->set_pagelayout('standard');

// Authorization check: capability OR local_jobaida_auth table (active=1) OR siteadmin.
$isauthorized = false;
if (is_siteadmin()) {
    $isauthorized = true;
} else if (has_capability('local/jobaida:use', $context)) {
    $isauthorized = true;
} else {
    global $DB, $USER;
    $auth = $DB->get_record('local_jobaida_auth', ['userid' => $USER->id, 'active' => 1]);
    if ($auth) {
        $isauthorized = true;
    }
}

// Check if user can manage authorizations (coach/admin).
$canmanage = has_capability('local/jobaida:authorize', $context) || is_siteadmin();

// Sesskey for JS.
$sesskey = sesskey();

echo $OUTPUT->header();

// If not authorized, show denial message and stop.
if (!$isauthorized) {
    echo '<div style="max-width:900px; margin:40px auto; text-align:center;">';
    echo '<div style="background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); padding:40px;">';
    echo '<div style="font-size:48px; margin-bottom:16px;">&#128274;</div>';
    echo '<h3 style="color:#dc3545; margin-bottom:12px;">' . get_string('not_authorized', 'local_jobaida') . '</h3>';
    echo '<p style="color:#6c757d; margin-bottom:24px;">' .
         get_string('not_authorized', 'local_jobaida') . '</p>';
    echo '<a href="' . new moodle_url('/my/') . '" class="btn btn-secondary">' .
         get_string('back') . '</a>';
    echo '</div></div>';
    echo $OUTPUT->footer();
    die();
}

?>

<style>
/* ========== JobAIDA Styles ========== */
.jobaida-container {
    max-width: 900px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header */
.jobaida-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.jobaida-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a1a2e;
}
.jobaida-nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.jobaida-nav .btn {
    font-size: 0.85rem;
    padding: 6px 14px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}

/* Cards */
.jobaida-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
    margin-bottom: 20px;
    overflow: hidden;
}
.jobaida-card-header {
    padding: 14px 20px;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.jobaida-card-body {
    padding: 20px;
}

/* AIDA Explanation Card */
.jobaida-aida-info {
    background: #f8f9fa;
}
.jobaida-aida-info .jobaida-card-header {
    background: #f8f9fa;
    color: #495057;
}
.jobaida-aida-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 8px;
}
.jobaida-aida-step {
    padding: 12px;
    border-radius: 6px;
    text-align: center;
}
.jobaida-aida-step .step-letter {
    font-size: 1.6rem;
    font-weight: 700;
    display: block;
    margin-bottom: 4px;
}
.jobaida-aida-step .step-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.jobaida-aida-step .step-desc {
    font-size: 0.75rem;
    margin-top: 6px;
    opacity: 0.85;
}
.step-attention { background: #fde8e8; color: #dc3545; }
.step-interest { background: #e8f0fe; color: #0066cc; }
.step-desire { background: #e8f5e9; color: #28a745; }
.step-action { background: #fff3e0; color: #f59e0b; }

/* Form */
.jobaida-form-group {
    margin-bottom: 18px;
}
.jobaida-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #1a1a2e;
    font-size: 0.9rem;
}
.jobaida-form-group .help-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 6px;
}
.jobaida-form-group textarea {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 0.9rem;
    font-family: inherit;
    resize: vertical;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.jobaida-form-group textarea:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
}
.jobaida-form-group textarea.is-invalid {
    border-color: #dc3545;
}
/* Drag & Drop zone */
.jobaida-dropzone {
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    padding: 12px;
    text-align: center;
    color: #999;
    font-size: 0.8rem;
    margin-bottom: 8px;
    transition: all 0.2s;
    cursor: pointer;
}
.jobaida-dropzone.dragover {
    border-color: #0066cc;
    background: #f0f7ff;
    color: #0066cc;
}
.jobaida-dropzone .drop-icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 4px;
}
.jobaida-dropzone .drop-file-name {
    font-weight: 600;
    color: #28a745;
}
.jobaida-char-count {
    font-size: 0.75rem;
    color: #6c757d;
    text-align: right;
    margin-top: 4px;
}
.jobaida-char-count.too-short {
    color: #dc3545;
}

/* Generate button */
.jobaida-generate-btn {
    background: #28a745;
    color: #fff;
    border: none;
    padding: 12px 32px;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
    width: 100%;
    justify-content: center;
}
.jobaida-generate-btn:hover {
    background: #218838;
}
.jobaida-generate-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

/* Loading spinner */
.jobaida-spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: jobaida-spin 0.6s linear infinite;
}
@keyframes jobaida-spin {
    to { transform: rotate(360deg); }
}
@keyframes learnPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Error message */
.jobaida-error {
    background: #fde8e8;
    color: #dc3545;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 0.9rem;
    display: none;
    border: 1px solid #f5c6cb;
}

/* Result section */
.jobaida-result {
    display: none;
}
.jobaida-result.visible {
    display: block;
}

/* AIDA result cards */
.jobaida-aida-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
    margin-bottom: 16px;
    overflow: hidden;
}
.jobaida-aida-card .aida-header {
    padding: 12px 20px;
    color: #fff;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.jobaida-aida-card .aida-header .aida-letter {
    font-size: 1.2rem;
    font-weight: 700;
    margin-right: 8px;
}
.jobaida-aida-card .aida-content {
    padding: 16px 20px;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #333;
    white-space: pre-wrap;
}
.jobaida-aida-card .aida-rationale {
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}
.jobaida-aida-card .aida-rationale.expanded {
    max-height: 500px;
    padding: 12px 20px;
}
.jobaida-aida-card .aida-rationale-text {
    font-size: 0.82rem;
    color: #6c757d;
    line-height: 1.5;
    font-style: italic;
}
.aida-rationale-toggle {
    background: none;
    border: none;
    color: rgba(255,255,255,0.85);
    font-size: 0.8rem;
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}
.aida-rationale-toggle:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
}

/* AIDA header colors */
.aida-header-attention { background: #dc3545; }
.aida-header-interest { background: #0066cc; }
.aida-header-desire { background: #28a745; }
.aida-header-action { background: #f59e0b; }

/* Full letter card */
.jobaida-full-letter {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    border: 2px solid #0066cc;
    margin-top: 24px;
    margin-bottom: 16px;
    overflow: hidden;
}
.jobaida-full-letter .full-letter-header {
    background: #0066cc;
    color: #fff;
    padding: 14px 20px;
    font-weight: 600;
    font-size: 1rem;
}
.jobaida-full-letter .full-letter-content {
    padding: 20px;
    font-size: 0.9rem;
    line-height: 1.7;
    color: #333;
    white-space: pre-wrap;
}

/* Action buttons */
.jobaida-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
    margin-bottom: 24px;
}
.jobaida-actions .btn {
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.btn-copy {
    background: #0066cc;
    color: #fff;
}
.btn-copy:hover {
    background: #0055b3;
    color: #fff;
}
.btn-copy.copied {
    background: #28a745;
}
.btn-save {
    background: #28a745;
    color: #fff;
}
.btn-save:hover {
    background: #218838;
    color: #fff;
}
.btn-save:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

/* Collapsible toggle arrow */
.collapse-arrow {
    transition: transform 0.2s;
    font-size: 0.8rem;
}
.collapse-arrow.collapsed {
    transform: rotate(-90deg);
}

/* Save success toast */
.jobaida-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #28a745;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 9999;
    transform: translateY(100px);
    opacity: 0;
    transition: transform 0.3s, opacity 0.3s;
}
.jobaida-toast.visible {
    transform: translateY(0);
    opacity: 1;
}

/* Responsive */
@media (max-width: 600px) {
    .jobaida-header { flex-direction: column; align-items: flex-start; }
    .jobaida-aida-steps { grid-template-columns: 1fr 1fr; }
    .jobaida-actions { flex-direction: column; }
    .jobaida-actions .btn { width: 100%; justify-content: center; }
}
</style>

<div class="jobaida-container">

    <!-- Header -->
    <div class="jobaida-header">
        <h2><?php echo get_string('generator', 'local_jobaida'); ?></h2>
        <div class="jobaida-nav">
            <a href="<?php echo new moodle_url('/local/jobaida/history.php'); ?>"
               class="btn btn-outline-secondary">
                <?php echo get_string('history', 'local_jobaida'); ?>
            </a>
            <?php if ($canmanage): ?>
            <a href="<?php echo new moodle_url('/local/jobaida/manage_auth.php'); ?>"
               class="btn btn-outline-primary">
                <?php echo get_string('manage_auth', 'local_jobaida'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mode Tabs -->
    <div class="jobaida-mode-tabs" style="display:flex; gap:3px; margin-bottom:24px; border-radius:8px; overflow:hidden; background:#e5e7eb; padding:3px;">
        <button onclick="switchMode('express')" id="tab-express" class="jobaida-mode-tab"
                style="flex:1; padding:14px 20px; border:none; font-size:1rem; font-weight:600; cursor:pointer; transition:all 0.2s; border-radius:6px;
                       background:#fff; color:#EAB308;">
            Express Writers
        </button>
        <button onclick="switchMode('coaching')" id="tab-coaching" class="jobaida-mode-tab"
                style="flex:1; padding:14px 20px; border:none; font-size:1rem; font-weight:600; cursor:pointer; transition:all 0.2s; border-radius:6px;
                       background:#fff; color:#e97a0a;">
            Coaching Writers
        </button>
        <button onclick="switchMode('learn')" id="tab-learn" class="jobaida-mode-tab active"
                style="flex:1; padding:14px 20px; border:none; font-size:1rem; font-weight:600; cursor:pointer; transition:all 0.2s; border-radius:6px;
                       background:#dc3545; color:#fff;">
            Learn &amp; Write
        </button>
    </div>

    <!-- ========== EXPRESS MODE ========== -->
    <div id="mode-express" class="jobaida-mode-content" style="display:none;">

    <!-- AIDA Explanation (collapsible) -->
    <div class="jobaida-card jobaida-aida-info">
        <div class="jobaida-card-header" onclick="toggleAidaInfo()">
            <span><?php echo get_string('aida_model', 'local_jobaida'); ?></span>
            <span class="collapse-arrow" id="aida-info-arrow">&#9660;</span>
        </div>
        <div class="jobaida-card-body" id="aida-info-body">
            <p style="margin:0 0 12px; font-size:0.88rem; color:#495057;">
                <?php echo get_string('aida_explanation', 'local_jobaida'); ?>
            </p>
            <div class="jobaida-aida-steps">
                <div class="jobaida-aida-step step-attention">
                    <span class="step-letter">A</span>
                    <span class="step-label">Attention</span>
                    <span class="step-desc"><?php echo s(get_string('attention_desc', 'local_jobaida')); ?></span>
                </div>
                <div class="jobaida-aida-step step-interest">
                    <span class="step-letter">I</span>
                    <span class="step-label">Interest</span>
                    <span class="step-desc"><?php echo s(get_string('interest_desc', 'local_jobaida')); ?></span>
                </div>
                <div class="jobaida-aida-step step-desire">
                    <span class="step-letter">D</span>
                    <span class="step-label">Desire</span>
                    <span class="step-desc"><?php echo s(get_string('desire_desc', 'local_jobaida')); ?></span>
                </div>
                <div class="jobaida-aida-step step-action">
                    <span class="step-letter">A</span>
                    <span class="step-label">Action</span>
                    <span class="step-desc"><?php echo s(get_string('action_desc', 'local_jobaida')); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Input Form -->
    <div class="jobaida-card">
        <div class="jobaida-card-header" style="cursor:default;">
            <?php echo get_string('generate', 'local_jobaida'); ?>
        </div>
        <div class="jobaida-card-body">
            <div id="jobaida-error" class="jobaida-error"></div>

            <!-- Job Ad -->
            <div class="jobaida-form-group">
                <label for="jobaida-jobad">
                    <?php echo get_string('job_ad', 'local_jobaida'); ?> <span style="color:#dc3545;">*</span>
                </label>
                <div class="help-text"><?php echo s(get_string('job_ad_help', 'local_jobaida')); ?></div>
                <div class="jobaida-dropzone" id="dropzone-jobad" onclick="document.getElementById('file-jobad').click()">
                    <span class="drop-icon">&#128195;</span>
                    Trascina qui il file dell'annuncio (PDF/Word) oppure clicca per selezionarlo
                    <input type="file" id="file-jobad" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'jobaida-jobad', 'dropzone-jobad')">
                </div>
                <textarea id="jobaida-jobad" rows="6"
                          placeholder="<?php echo s(get_string('job_ad_placeholder', 'local_jobaida')); ?>"
                          oninput="updateCharCount('jobad')"><?php
                ?></textarea>
                <div class="jobaida-char-count" id="charcount-jobad">0 / 50 min</div>
            </div>

            <!-- CV -->
            <div class="jobaida-form-group">
                <label for="jobaida-cv">
                    <?php echo get_string('cv_text', 'local_jobaida'); ?> <span style="color:#dc3545;">*</span>
                </label>
                <div class="help-text"><?php echo s(get_string('cv_text_help', 'local_jobaida')); ?></div>
                <div class="jobaida-dropzone" id="dropzone-cv" onclick="document.getElementById('file-cv').click()">
                    <span class="drop-icon">&#128196;</span>
                    Trascina qui il tuo CV (PDF/Word) oppure clicca per selezionarlo
                    <input type="file" id="file-cv" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'jobaida-cv', 'dropzone-cv')">
                </div>
                <textarea id="jobaida-cv" rows="6"
                          placeholder="<?php echo s(get_string('cv_text_placeholder', 'local_jobaida')); ?>"
                          oninput="updateCharCount('cv')"><?php
                ?></textarea>
                <div class="jobaida-char-count" id="charcount-cv">0 / 50 min</div>
            </div>

            <!-- Objectives (optional) -->
            <div class="jobaida-form-group">
                <label for="jobaida-objectives">
                    <?php echo get_string('objectives', 'local_jobaida'); ?>
                    <span style="color:#6c757d; font-weight:400; font-size:0.8rem;">(opzionale)</span>
                </label>
                <div class="help-text"><?php echo s(get_string('objectives_help', 'local_jobaida')); ?></div>
                <textarea id="jobaida-objectives" rows="4"
                          placeholder="<?php echo s(get_string('objectives_placeholder', 'local_jobaida')); ?>"><?php
                ?></textarea>
            </div>

            <!-- Generate Button -->
            <button type="button" class="jobaida-generate-btn" id="btn-generate" onclick="generateLetter()">
                <?php echo get_string('generate', 'local_jobaida'); ?>
            </button>
        </div>
    </div>

    <!-- Result Section (initially hidden) -->
    <div class="jobaida-result" id="jobaida-result">

        <!-- Attention -->
        <div class="jobaida-aida-card">
            <div class="aida-header aida-header-attention">
                <span>
                    <span class="aida-letter">A</span>
                    <?php echo get_string('attention', 'local_jobaida'); ?>
                </span>
                <button type="button" class="aida-rationale-toggle"
                        onclick="toggleRationale('attention')">
                    <?php echo get_string('rationale', 'local_jobaida'); ?> &#9660;
                </button>
            </div>
            <div class="aida-content" id="result-attention"></div>
            <div class="aida-rationale" id="rationale-attention">
                <div class="aida-rationale-text" id="rationale-attention-text"></div>
            </div>
        </div>

        <!-- Interest -->
        <div class="jobaida-aida-card">
            <div class="aida-header aida-header-interest">
                <span>
                    <span class="aida-letter">I</span>
                    <?php echo get_string('interest', 'local_jobaida'); ?>
                </span>
                <button type="button" class="aida-rationale-toggle"
                        onclick="toggleRationale('interest')">
                    <?php echo get_string('rationale', 'local_jobaida'); ?> &#9660;
                </button>
            </div>
            <div class="aida-content" id="result-interest"></div>
            <div class="aida-rationale" id="rationale-interest">
                <div class="aida-rationale-text" id="rationale-interest-text"></div>
            </div>
        </div>

        <!-- Desire -->
        <div class="jobaida-aida-card">
            <div class="aida-header aida-header-desire">
                <span>
                    <span class="aida-letter">D</span>
                    <?php echo get_string('desire', 'local_jobaida'); ?>
                </span>
                <button type="button" class="aida-rationale-toggle"
                        onclick="toggleRationale('desire')">
                    <?php echo get_string('rationale', 'local_jobaida'); ?> &#9660;
                </button>
            </div>
            <div class="aida-content" id="result-desire"></div>
            <div class="aida-rationale" id="rationale-desire">
                <div class="aida-rationale-text" id="rationale-desire-text"></div>
            </div>
        </div>

        <!-- Action -->
        <div class="jobaida-aida-card">
            <div class="aida-header aida-header-action">
                <span>
                    <span class="aida-letter">A</span>
                    <?php echo get_string('action', 'local_jobaida'); ?>
                </span>
                <button type="button" class="aida-rationale-toggle"
                        onclick="toggleRationale('action')">
                    <?php echo get_string('rationale', 'local_jobaida'); ?> &#9660;
                </button>
            </div>
            <div class="aida-content" id="result-action"></div>
            <div class="aida-rationale" id="rationale-action">
                <div class="aida-rationale-text" id="rationale-action-text"></div>
            </div>
        </div>

        <!-- Full Letter -->
        <div class="jobaida-full-letter">
            <div class="full-letter-header">
                <?php echo get_string('full_letter', 'local_jobaida'); ?>
            </div>
            <div class="full-letter-content" id="result-full-letter"></div>
        </div>

        <!-- Action Buttons -->
        <div class="jobaida-actions">
            <button type="button" class="btn btn-copy" id="btn-copy" onclick="copyLetter()">
                &#128203; <?php echo get_string('copy_letter', 'local_jobaida'); ?>
            </button>
            <button type="button" class="btn btn-save" id="btn-save" onclick="saveLetter()">
                &#128190; Salva nello Storico
            </button>
            <button type="button" class="btn" id="btn-export-word" onclick="exportWord()" style="background:#2ecc71; color:#fff; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-weight:500;">
                &#128196; Esporta Word
            </button>
        </div>
    </div>

    </div><!-- /mode-express -->

    <!-- ========== COACHING MODE ========== -->
    <div id="mode-coaching" class="jobaida-mode-content" style="display:none;">
        <!-- Step 1: Input -->
        <div class="jobaida-card" id="coaching-step1">
            <div class="jobaida-card-header" style="background:#f0fdf4; color:#065f46; cursor:default;">
                Step 1 - Inserisci i Dati
            </div>
            <div class="jobaida-card-body">
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; display:block; margin-bottom:6px;">Annuncio di Lavoro *</label>
                    <div class="jobaida-dropzone" id="dropzone-coaching-jobad" onclick="document.getElementById('file-coaching-jobad').click()">
                        <span class="drop-icon">&#128195;</span>
                        Trascina qui il file dell'annuncio (PDF/Word) oppure clicca per selezionarlo
                        <input type="file" id="file-coaching-jobad" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'coaching-jobad', 'dropzone-coaching-jobad')">
                    </div>
                    <textarea id="coaching-jobad" rows="6" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:10px; font-size:0.9rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Incolla qui l'annuncio di lavoro..."></textarea>
                    <small id="coaching-jobad-count" style="color:#999;">0 caratteri</small>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; display:block; margin-bottom:6px;">Il tuo CV *</label>
                    <div class="jobaida-dropzone" id="dropzone-coaching-cv" onclick="document.getElementById('file-coaching-cv').click()">
                        <span class="drop-icon">&#128196;</span>
                        Trascina qui il tuo CV (PDF/Word) oppure clicca per selezionarlo
                        <input type="file" id="file-coaching-cv" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'coaching-cv', 'dropzone-coaching-cv')">
                    </div>
                    <textarea id="coaching-cv" rows="6" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:10px; font-size:0.9rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Incolla qui il tuo CV..."></textarea>
                    <small id="coaching-cv-count" style="color:#999;">0 caratteri</small>
                </div>
                <button onclick="analyzeGaps()" id="btn-analyze" style="padding:12px 28px; background:#28a745; color:#fff; border:none; border-radius:6px; font-size:1rem; font-weight:600; cursor:pointer; width:100%;">
                    Analizza Compatibilita
                </button>
            </div>
        </div>

        <!-- Step 2: Gap Analysis Results + Questions (hidden initially) -->
        <div id="coaching-step2" style="display:none;">
            <!-- Match overview -->
            <div class="jobaida-card" style="border-left:4px solid #0066cc;">
                <div class="jobaida-card-body">
                    <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                        <div style="text-align:center;">
                            <div id="match-percentage" style="font-size:2.5rem; font-weight:700; color:#0066cc;">0%</div>
                            <div style="font-size:0.85rem; color:#666;">Compatibilita</div>
                        </div>
                        <div style="flex:1;">
                            <div id="match-role" style="font-size:1.1rem; font-weight:600; color:#333;"></div>
                            <div id="match-company" style="font-size:0.9rem; color:#666;"></div>
                            <div id="coaching-tip" style="margin-top:8px; padding:10px; background:#fff7ed; border-radius:6px; font-size:0.85rem; color:#92400e;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strengths -->
            <div id="strengths-section" class="jobaida-card" style="border-left:4px solid #28a745;">
                <div class="jobaida-card-header" style="background:#f0fdf4; color:#065f46; cursor:default;">
                    Punti di Forza
                </div>
                <div class="jobaida-card-body" id="strengths-list"></div>
            </div>

            <!-- Questions for gaps -->
            <div class="jobaida-card" style="border-left:4px solid #f59e0b;">
                <div class="jobaida-card-header" style="background:#fffbeb; color:#92400e; cursor:default;">
                    Rispondi alle Domande del Coach
                </div>
                <div class="jobaida-card-body">
                    <p style="font-size:0.85rem; color:#666; margin-bottom:16px;">
                        Per ogni requisito dell'annuncio, rispondi alle domande. Le tue risposte saranno usate per costruire una lettera personalizzata.
                    </p>
                    <div id="gap-questions-container"></div>
                </div>
            </div>

            <!-- Objectives (optional) -->
            <div class="jobaida-card">
                <div class="jobaida-card-header" style="background:#f8f9fa; color:#495057; cursor:default;">
                    I tuoi Obiettivi (opzionale)
                </div>
                <div class="jobaida-card-body">
                    <textarea id="coaching-objectives" rows="3" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:10px; font-size:0.9rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Cosa ti motiva? Quali sono i tuoi obiettivi professionali?"></textarea>
                </div>
            </div>

            <!-- Generate button -->
            <div style="text-align:center; margin:20px 0;">
                <button onclick="generateCoachingLetter()" id="btn-coaching-generate" style="padding:14px 40px; background:#0066cc; color:#fff; border:none; border-radius:6px; font-size:1.1rem; font-weight:600; cursor:pointer;">
                    Genera Lettera con le tue Risposte
                </button>
            </div>
        </div>

        <!-- Step 3: Results -->
        <div id="coaching-step3" style="display:none;">
            <div id="coaching-results"></div>
        </div>
    </div><!-- /mode-coaching -->

    <!-- ========== LEARN & WRITE MODE ========== -->
    <div id="mode-learn" class="jobaida-mode-content">

        <!-- Intro -->
        <div class="jobaida-card" style="border-left:4px solid #8b5cf6; margin-bottom:20px;">
            <div class="jobaida-card-body" style="background:#faf5ff;">
                <h3 style="margin:0 0 8px; color:#7c3aed; font-size:1.1rem;">Come funziona Learn &amp; Write</h3>
                <p style="font-size:0.9rem; color:#6b7280; margin:0;">
                    Il sistema costruisce la lettera <strong>una sezione alla volta</strong>. Per ogni sezione AIDA:
                    l'AI propone un testo e spiega perche ha fatto quelle scelte. Tu puoi <strong>confermare, modificare o chiedere una riscrittura</strong>.
                    Solo dopo la tua conferma si passa alla sezione successiva.
                </p>
            </div>
        </div>

        <!-- Step 0: Input -->
        <div class="jobaida-card" id="learn-step-input">
            <div class="jobaida-card-header" style="background:#f0fdf4; color:#065f46; cursor:default;">
                Inserisci i Dati
            </div>
            <div class="jobaida-card-body">
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; display:block; margin-bottom:6px;">Annuncio di Lavoro *</label>
                    <div class="jobaida-dropzone" id="dropzone-learn-jobad" onclick="document.getElementById('file-learn-jobad').click()">
                        <span class="drop-icon">&#128195;</span>
                        Trascina qui il file dell'annuncio oppure clicca per selezionarlo
                        <input type="file" id="file-learn-jobad" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'learn-jobad', 'dropzone-learn-jobad')">
                    </div>
                    <textarea id="learn-jobad" rows="8" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:12px; font-size:0.95rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Incolla qui l'annuncio di lavoro..."></textarea>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; display:block; margin-bottom:6px;">Il tuo CV *</label>
                    <div class="jobaida-dropzone" id="dropzone-learn-cv" onclick="document.getElementById('file-learn-cv').click()">
                        <span class="drop-icon">&#128196;</span>
                        Trascina qui il tuo CV oppure clicca per selezionarlo
                        <input type="file" id="file-learn-cv" accept=".pdf,.doc,.docx,.txt" style="display:none;" onchange="handleFileSelect(this, 'learn-cv', 'dropzone-learn-cv')">
                    </div>
                    <textarea id="learn-cv" rows="8" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:12px; font-size:0.95rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Incolla qui il tuo CV..."></textarea>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-weight:600; display:block; margin-bottom:6px;">I tuoi Obiettivi (opzionale)</label>
                    <textarea id="learn-objectives" rows="4" style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:12px; font-size:0.95rem; resize:vertical; box-sizing:border-box;"
                              placeholder="Cosa ti motiva? Quali sono i tuoi obiettivi?"></textarea>
                </div>
                <button onclick="learnStartSection('attention')" id="btn-learn-start"
                        style="padding:14px 28px; background:#7c3aed; color:#fff; border:none; border-radius:6px; font-size:1rem; font-weight:600; cursor:pointer; width:100%;">
                    Inizia Step-by-Step
                </button>
            </div>
        </div>

        <!-- Progress bar -->
        <div id="learn-progress" style="display:none; margin-bottom:20px;">
            <div style="display:flex; gap:4px;">
                <div id="learn-prog-attention" class="learn-prog-step" style="flex:1; height:6px; background:#dee2e6; border-radius:3px;"></div>
                <div id="learn-prog-interest" class="learn-prog-step" style="flex:1; height:6px; background:#dee2e6; border-radius:3px;"></div>
                <div id="learn-prog-desire" class="learn-prog-step" style="flex:1; height:6px; background:#dee2e6; border-radius:3px;"></div>
                <div id="learn-prog-action" class="learn-prog-step" style="flex:1; height:6px; background:#dee2e6; border-radius:3px;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#999; margin-top:4px;">
                <span>A - Attention</span><span>I - Interest</span><span>D - Desire</span><span>A - Action</span>
            </div>
        </div>

        <!-- Section workspace (dynamic) -->
        <div id="learn-workspace" style="display:none;"></div>

        <!-- Final letter (after all 4 confirmed) -->
        <div id="learn-final" style="display:none;"></div>

    </div><!-- /mode-learn -->

</div>

<!-- Toast notification -->
<div class="jobaida-toast" id="jobaida-toast"></div>

<script>
(function() {
    'use strict';

    var SESSKEY = '<?php echo $sesskey; ?>';
    var AJAX_URL = '<?php echo (new moodle_url('/local/jobaida/ajax_generate.php'))->out(false); ?>';
    var STRINGS = {
        generate: <?php echo json_encode(get_string('generate', 'local_jobaida')); ?>,
        generating: <?php echo json_encode(get_string('generating', 'local_jobaida')); ?>,
        copied: <?php echo json_encode(get_string('copied', 'local_jobaida')); ?>,
        copy_letter: <?php echo json_encode(get_string('copy_letter', 'local_jobaida')); ?>,
        error_empty: <?php echo json_encode(get_string('error_empty_fields', 'local_jobaida')); ?>,
        error_short: <?php echo json_encode(get_string('error_too_short', 'local_jobaida')); ?>
    };

    // Store the last generated data for saving.
    var lastGeneratedData = null;
    var letterSaved = false;

    /**
     * Toggle AIDA explanation card.
     */
    window.toggleAidaInfo = function() {
        var body = document.getElementById('aida-info-body');
        var arrow = document.getElementById('aida-info-arrow');
        if (body.style.display === 'none') {
            body.style.display = '';
            arrow.classList.remove('collapsed');
        } else {
            body.style.display = 'none';
            arrow.classList.add('collapsed');
        }
    };

    /**
     * Update character count for a textarea.
     * @param {string} field - 'jobad' or 'cv'
     */
    window.updateCharCount = function(field) {
        var textarea = document.getElementById('jobaida-' + (field === 'jobad' ? 'jobad' : 'cv'));
        var counter = document.getElementById('charcount-' + field);
        if (!textarea || !counter) return;

        var len = textarea.value.trim().length;
        counter.textContent = len + ' / 50 min';
        if (len < 50 && len > 0) {
            counter.classList.add('too-short');
            textarea.classList.add('is-invalid');
        } else {
            counter.classList.remove('too-short');
            textarea.classList.remove('is-invalid');
        }
    };

    /**
     * Toggle rationale section for an AIDA card.
     * @param {string} section - 'attention', 'interest', 'desire', 'action'
     */
    window.toggleRationale = function(section) {
        var el = document.getElementById('rationale-' + section);
        if (!el) return;
        el.classList.toggle('expanded');
    };

    /**
     * Show error message.
     * @param {string} msg
     */
    function showError(msg) {
        var el = document.getElementById('jobaida-error');
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    /**
     * Hide error message.
     */
    function hideError() {
        document.getElementById('jobaida-error').style.display = 'none';
    }

    /**
     * Show a toast notification.
     * @param {string} msg
     * @param {string} color - CSS color (default green)
     */
    function showToast(msg, color) {
        var toast = document.getElementById('jobaida-toast');
        toast.textContent = msg;
        toast.style.background = color || '#28a745';
        toast.classList.add('visible');
        setTimeout(function() {
            toast.classList.remove('visible');
        }, 3000);
    }

    /**
     * Escape HTML in text for safe display.
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    /**
     * Generate the letter via AJAX.
     */
    window.generateLetter = function() {
        hideError();

        var jobAd = document.getElementById('jobaida-jobad').value.trim();
        var cvText = document.getElementById('jobaida-cv').value.trim();
        var objectives = document.getElementById('jobaida-objectives').value.trim();

        // Validate required fields.
        if (!jobAd || !cvText) {
            showError(STRINGS.error_empty);
            return;
        }

        // Validate minimum length (skip if empty, show warning only).
        if (jobAd.length === 0 || cvText.length === 0) {
            showError('Compila entrambi i campi Annuncio e CV.');
            if (jobAd.length === 0) document.getElementById('jobaida-jobad').classList.add('is-invalid');
            if (cvText.length === 0) document.getElementById('jobaida-cv').classList.add('is-invalid');
            return;
        }

        // Show loading state.
        var btn = document.getElementById('btn-generate');
        btn.disabled = true;
        btn.innerHTML = '<span class="jobaida-spinner"></span> ' + STRINGS.generating;

        // Hide previous results.
        document.getElementById('jobaida-result').classList.remove('visible');

        // AJAX POST.
        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'generate');
        formData.append('job_ad', jobAd);
        formData.append('cv_text', cvText);
        formData.append('objectives', objectives);

        fetch(AJAX_URL, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Reset button.
            btn.disabled = false;
            btn.innerHTML = STRINGS.generate;

            if (!data.success) {
                showError(data.message || 'Errore sconosciuto');
                return;
            }

            var result = data.data;

            // Populate AIDA sections.
            document.getElementById('result-attention').textContent = result.attention || '';
            document.getElementById('result-interest').textContent = result.interest || '';
            document.getElementById('result-desire').textContent = result.desire || '';
            document.getElementById('result-action').textContent = result.action || '';

            // Populate rationale sections.
            document.getElementById('rationale-attention-text').textContent = result.attention_rationale || '';
            document.getElementById('rationale-interest-text').textContent = result.interest_rationale || '';
            document.getElementById('rationale-desire-text').textContent = result.desire_rationale || '';
            document.getElementById('rationale-action-text').textContent = result.action_rationale || '';

            // Populate full letter.
            document.getElementById('result-full-letter').textContent = result.full_letter || '';

            // Store for saving.
            lastGeneratedData = {
                job_ad: jobAd,
                cv_text: cvText,
                objectives: objectives,
                attention: result.attention || '',
                attention_rationale: result.attention_rationale || '',
                interest: result.interest || '',
                interest_rationale: result.interest_rationale || '',
                desire: result.desire || '',
                desire_rationale: result.desire_rationale || '',
                action: result.action || '',
                action_rationale: result.action_rationale || '',
                full_letter: result.full_letter || '',
                model_used: result.model_used || '',
                tokens_used: result.tokens_used || 0
            };
            letterSaved = false;

            // Reset save button.
            var saveBtn = document.getElementById('btn-save');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salva nello Storico';

            // Show result section.
            document.getElementById('jobaida-result').classList.add('visible');

            // Scroll to results.
            document.getElementById('jobaida-result').scrollIntoView({behavior: 'smooth', block: 'start'});

            // Collapse all rationales.
            var sections = ['attention', 'interest', 'desire', 'action'];
            for (var i = 0; i < sections.length; i++) {
                document.getElementById('rationale-' + sections[i]).classList.remove('expanded');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = STRINGS.generate;
            showError('Errore di rete: ' + err.message);
        });
    };

    /**
     * Copy the full letter to clipboard.
     */
    window.copyLetter = function() {
        var letterText = document.getElementById('result-full-letter').textContent;
        if (!letterText) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(letterText).then(function() {
                var btn = document.getElementById('btn-copy');
                btn.classList.add('copied');
                btn.innerHTML = '&#10003; ' + STRINGS.copied;
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = '&#128203; ' + STRINGS.copy_letter;
                }, 2000);
            }).catch(function() {
                fallbackCopy(letterText);
            });
        } else {
            fallbackCopy(letterText);
        }
    };

    /**
     * Fallback copy using a temporary textarea.
     * @param {string} text
     */
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            var btn = document.getElementById('btn-copy');
            btn.classList.add('copied');
            btn.innerHTML = '&#10003; ' + STRINGS.copied;
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '&#128203; ' + STRINGS.copy_letter;
            }, 2000);
        } catch (e) {
            showToast('Impossibile copiare', '#dc3545');
        }
        document.body.removeChild(textarea);
    }

    /**
     * Save the generated letter to history via AJAX.
     */
    window.saveLetter = function() {
        if (!lastGeneratedData || letterSaved) return;

        var saveBtn = document.getElementById('btn-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Salvataggio...';

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'save');
        formData.append('job_ad', lastGeneratedData.job_ad);
        formData.append('cv_text', lastGeneratedData.cv_text);
        formData.append('objectives', lastGeneratedData.objectives);
        formData.append('attention', lastGeneratedData.attention);
        formData.append('attention_rationale', lastGeneratedData.attention_rationale);
        formData.append('interest', lastGeneratedData.interest);
        formData.append('interest_rationale', lastGeneratedData.interest_rationale);
        formData.append('desire', lastGeneratedData.desire);
        formData.append('desire_rationale', lastGeneratedData.desire_rationale);
        formData.append('action_text', lastGeneratedData.action);
        formData.append('action_rationale', lastGeneratedData.action_rationale);
        formData.append('full_letter', lastGeneratedData.full_letter);
        formData.append('model_used', lastGeneratedData.model_used);
        formData.append('tokens_used', lastGeneratedData.tokens_used);

        fetch(AJAX_URL, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                letterSaved = true;
                saveBtn.textContent = 'Salvata';
                showToast('Lettera salvata nello storico');
            } else {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salva nello Storico';
                showToast(data.message || 'Errore nel salvataggio', '#dc3545');
            }
        })
        .catch(function(err) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salva nello Storico';
            showToast('Errore di rete: ' + err.message, '#dc3545');
        });
    };

    // ========== MODE SWITCHING ==========

    /**
     * Switch between Express and Coaching modes.
     * @param {string} mode - 'express' or 'coaching'
     */
    var TAB_COLORS = {
        express:  '#EAB308',
        coaching: '#e97a0a',
        learn:    '#dc3545'
    };

    window.switchMode = function(mode) {
        document.getElementById('mode-express').style.display = mode === 'express' ? 'block' : 'none';
        document.getElementById('mode-coaching').style.display = mode === 'coaching' ? 'block' : 'none';
        document.getElementById('mode-learn').style.display = mode === 'learn' ? 'block' : 'none';

        ['express', 'coaching', 'learn'].forEach(function(key) {
            var tab = document.getElementById('tab-' + key);
            if (key === mode) {
                tab.style.background = TAB_COLORS[key];
                tab.style.color = '#fff';
            } else {
                tab.style.background = '#fff';
                tab.style.color = TAB_COLORS[key];
            }
        });
    };

    // ========== DRAG & DROP FILE HANDLING ==========
    // Setup drag & drop for all dropzones.
    document.querySelectorAll('.jobaida-dropzone').forEach(function(zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                var file = files[0];
                var textareaId = this.id.replace('dropzone-', 'jobaida-');
                if (this.id === 'dropzone-coaching-jobad') textareaId = 'coaching-jobad';
                if (this.id === 'dropzone-coaching-cv') textareaId = 'coaching-cv';
                if (this.id === 'dropzone-learn-jobad') textareaId = 'learn-jobad';
                if (this.id === 'dropzone-learn-cv') textareaId = 'learn-cv';
                processDroppedFile(file, textareaId, this.id);
            }
        });
    });

    window.handleFileSelect = function(input, textareaId, dropzoneId) {
        if (input.files.length > 0) {
            processDroppedFile(input.files[0], textareaId, dropzoneId);
        }
    };

    function processDroppedFile(file, textareaId, dropzoneId) {
        var zone = document.getElementById(dropzoneId);
        var textarea = document.getElementById(textareaId);
        var validExts = ['.txt', '.pdf', '.doc', '.docx'];
        var ext = '.' + file.name.split('.').pop().toLowerCase();

        if (validExts.indexOf(ext) === -1) {
            zone.innerHTML = '<span class="drop-icon" style="color:#dc3545;">&#10060;</span>'
                + 'Formato non supportato. Usa PDF, Word o TXT.';
            setTimeout(function() { resetDropzone(zone, dropzoneId); }, 3000);
            return;
        }

        // Show loading state.
        zone.innerHTML = '<span class="drop-icon" style="color:#0066cc;">&#9203;</span>'
            + '<span class="drop-file-name">' + file.name + '</span><br>'
            + '<small style="color:#0066cc;">Estrazione testo in corso...</small>';

        // Upload file to server for text extraction.
        var formData = new FormData();
        formData.append('file', file);
        formData.append('sesskey', M.cfg.sesskey);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', M.cfg.wwwroot + '/local/jobaida/ajax_extract_text.php', true);

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.text) {
                        textarea.value = resp.text;
                        textarea.dispatchEvent(new Event('input'));
                        zone.innerHTML = '<span class="drop-icon" style="color:#28a745;">&#10004;</span>'
                            + '<span class="drop-file-name">' + resp.filename + '</span> caricato!'
                            + '<br><small style="color:#28a745;">' + resp.chars + ' caratteri estratti</small>';
                    } else {
                        zone.innerHTML = '<span class="drop-icon" style="color:#f59e0b;">&#9888;</span>'
                            + '<span class="drop-file-name">' + file.name + '</span><br>'
                            + '<small style="color:#dc3545;">' + (resp.message || 'Errore estrazione') + '</small><br>'
                            + '<small style="color:#666;">Prova ad aprire il file e copiare il testo manualmente (Ctrl+A, Ctrl+C, Ctrl+V).</small>';
                        textarea.focus();
                    }
                } catch (e) {
                    zone.innerHTML = '<span class="drop-icon" style="color:#dc3545;">&#10060;</span>'
                        + 'Errore di comunicazione. Copia il testo manualmente.';
                    textarea.focus();
                }
            }
        };

        xhr.send(formData);
    }

    function resetDropzone(zone, dropzoneId) {
        var icon = dropzoneId.indexOf('cv') !== -1 ? '&#128196;' : '&#128195;';
        var label = dropzoneId.indexOf('cv') !== -1
            ? 'Trascina qui il tuo CV (PDF/Word) oppure clicca per selezionarlo'
            : 'Trascina qui il file dell\'annuncio (PDF/Word) oppure clicca per selezionarlo';
        zone.innerHTML = '<span class="drop-icon">' + icon + '</span>' + label;
    }

    // Character counters for coaching mode.
    document.getElementById('coaching-jobad').addEventListener('input', function() {
        document.getElementById('coaching-jobad-count').textContent = this.value.length + ' caratteri';
    });
    document.getElementById('coaching-cv').addEventListener('input', function() {
        document.getElementById('coaching-cv-count').textContent = this.value.length + ' caratteri';
    });

    // ========== GAP ANALYSIS ==========

    /**
     * Send job ad + CV for gap analysis.
     */
    window.analyzeGaps = function() {
        var jobad = document.getElementById('coaching-jobad').value.trim();
        var cv = document.getElementById('coaching-cv').value.trim();

        if (jobad.length === 0 || cv.length === 0) {
            alert('Compila entrambi i campi Annuncio e CV.');
            return;
        }

        var btn = document.getElementById('btn-analyze');
        btn.disabled = true;
        btn.textContent = 'Analisi in corso...';
        btn.style.background = '#6c757d';

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'analyze_gaps');
        formData.append('job_ad', jobad);
        formData.append('cv_text', cv);

        fetch(M.cfg.wwwroot + '/local/jobaida/ajax_analyze_gaps.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(resp) {
            btn.disabled = false;
            btn.textContent = 'Analizza Compatibilita';
            btn.style.background = '#28a745';

            if (resp.success) {
                displayGapAnalysis(resp.data);
            } else {
                alert('Errore: ' + (resp.message || 'Sconosciuto'));
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Analizza Compatibilita';
            btn.style.background = '#28a745';
            alert('Errore di comunicazione con il server.');
        });
    };

    /**
     * Display gap analysis results and build the questions UI.
     * @param {object} data - Analysis response data.
     */
    function displayGapAnalysis(data) {
        // Show step 2.
        document.getElementById('coaching-step2').style.display = 'block';

        // Match overview.
        var pct = data.overall_match_percentage;
        var pctColor = pct >= 70 ? '#28a745' : (pct >= 50 ? '#f59e0b' : '#dc3545');
        document.getElementById('match-percentage').textContent = pct + '%';
        document.getElementById('match-percentage').style.color = pctColor;
        document.getElementById('match-role').textContent = data.role || 'Ruolo';
        document.getElementById('match-company').textContent = data.company_name ? 'Azienda: ' + data.company_name : '';
        document.getElementById('coaching-tip').textContent = data.coaching_tip || '';

        // Strengths.
        var strengthsHtml = '';
        if (data.strengths && data.strengths.length > 0) {
            data.strengths.forEach(function(s) {
                strengthsHtml += '<div style="padding:6px 0; border-bottom:1px solid #eee; font-size:0.9rem;">&#10004; ' + escapeHtml(s) + '</div>';
            });
        }
        document.getElementById('strengths-list').innerHTML = strengthsHtml;

        // Questions.
        var questionsHtml = '';
        if (data.requirements && data.requirements.length > 0) {
            data.requirements.forEach(function(req) {
                var statusColor = req.match_status === 'full_match' ? '#28a745' : (req.match_status === 'partial_match' ? '#f59e0b' : '#dc3545');
                var statusLabel = req.match_status === 'full_match' ? 'Corrispondenza' : (req.match_status === 'partial_match' ? 'Parziale' : 'Non trovato');
                var statusIcon = req.match_status === 'full_match' ? '&#10004;' : (req.match_status === 'partial_match' ? '&#9888;' : '&#10060;');
                var importanceLabel = req.importance === 'essential' ? 'Essenziale' : (req.importance === 'preferred' ? 'Preferito' : 'Opzionale');

                questionsHtml += '<div style="margin-bottom:20px; padding:16px; background:#f8f9fa; border-radius:8px; border-left:4px solid ' + statusColor + ';">';
                questionsHtml += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">';
                questionsHtml += '<strong style="font-size:0.95rem;">' + escapeHtml(req.requirement) + '</strong>';
                questionsHtml += '<div style="display:flex; gap:8px;">';
                questionsHtml += '<span style="padding:2px 10px; border-radius:12px; font-size:0.75rem; font-weight:600; background:' + statusColor + '20; color:' + statusColor + ';">' + statusIcon + ' ' + statusLabel + '</span>';
                questionsHtml += '<span style="padding:2px 10px; border-radius:12px; font-size:0.75rem; background:#e5e7eb; color:#374151;">' + importanceLabel + '</span>';
                questionsHtml += '</div></div>';

                if (req.cv_evidence) {
                    questionsHtml += '<div style="font-size:0.85rem; color:#059669; margin-bottom:8px; padding:6px 10px; background:#ecfdf5; border-radius:4px;">Dal CV: ' + escapeHtml(req.cv_evidence) + '</div>';
                }

                if (req.question) {
                    questionsHtml += '<div style="font-size:0.9rem; color:#1e40af; margin-bottom:6px; font-weight:500;">' + escapeHtml(req.question) + '</div>';
                    if (req.question_hint) {
                        questionsHtml += '<div style="font-size:0.8rem; color:#9ca3af; margin-bottom:8px; font-style:italic;">' + escapeHtml(req.question_hint) + '</div>';
                    }
                    questionsHtml += '<textarea class="coaching-answer" data-question="' + escapeHtml(req.question).replace(/"/g, '&quot;') + '" rows="2" '
                        + 'style="width:100%; border:1px solid #dee2e6; border-radius:6px; padding:8px 10px; font-size:0.85rem; resize:vertical; box-sizing:border-box;" '
                        + 'placeholder="La tua risposta..."></textarea>';
                }

                questionsHtml += '</div>';
            });
        }
        document.getElementById('gap-questions-container').innerHTML = questionsHtml;

        // Scroll to results.
        document.getElementById('coaching-step2').scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    // ========== GENERATE COACHING LETTER ==========

    /**
     * Collect answers and generate a coaching-enriched letter.
     */
    window.generateCoachingLetter = function() {
        var jobad = document.getElementById('coaching-jobad').value.trim();
        var cv = document.getElementById('coaching-cv').value.trim();
        var objectives = document.getElementById('coaching-objectives').value.trim();

        // Collect answers.
        var answers = [];
        var answerTextareas = document.querySelectorAll('.coaching-answer');
        for (var i = 0; i < answerTextareas.length; i++) {
            var ta = answerTextareas[i];
            var answer = ta.value.trim();
            if (answer) {
                answers.push({
                    question: ta.getAttribute('data-question'),
                    answer: answer
                });
            }
        }

        var btn = document.getElementById('btn-coaching-generate');
        btn.disabled = true;
        btn.textContent = 'Generazione in corso...';
        btn.style.background = '#6c757d';

        // Build enriched objectives with answers.
        var enrichedObjectives = objectives;
        if (answers.length > 0) {
            enrichedObjectives += '\n\n=== RISPOSTE DEL CANDIDATO ===\n';
            answers.forEach(function(qa) {
                enrichedObjectives += 'D: ' + qa.question + '\nR: ' + qa.answer + '\n\n';
            });
        }

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'generate');
        formData.append('job_ad', jobad);
        formData.append('cv_text', cv);
        formData.append('objectives', enrichedObjectives);

        fetch(AJAX_URL, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(resp) {
            btn.disabled = false;
            btn.textContent = 'Genera Lettera con le tue Risposte';
            btn.style.background = '#0066cc';

            if (resp.success) {
                displayCoachingResults(resp.data);
            } else {
                alert('Errore: ' + (resp.message || 'Sconosciuto'));
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Genera Lettera con le tue Risposte';
            btn.style.background = '#0066cc';
            alert('Errore di comunicazione.');
        });
    };

    /**
     * Display the coaching-generated letter results.
     * @param {object} data - Generation response data.
     */
    function displayCoachingResults(data) {
        lastCoachingData = data;
        document.getElementById('coaching-step3').style.display = 'block';

        var html = '';
        var sections = [
            {key: 'attention', label: 'ATTENTION', desc: "Cattura l'Attenzione", color: '#dc3545'},
            {key: 'interest', label: 'INTEREST', desc: 'Suscita Interesse', color: '#0066cc'},
            {key: 'desire', label: 'DESIRE', desc: 'Crea il Desiderio', color: '#28a745'},
            {key: 'action', label: 'ACTION', desc: "Invito all'Azione", color: '#f59e0b'}
        ];

        sections.forEach(function(s) {
            html += '<div class="jobaida-card" style="border-left:4px solid ' + s.color + '; margin-bottom:16px;">';
            html += '<div class="jobaida-card-header" style="background:' + s.color + '10; color:' + s.color + '; cursor:default;">';
            html += s.label + ' - ' + s.desc;
            html += '</div>';
            html += '<div class="jobaida-card-body">';
            html += '<p style="font-size:0.95rem; line-height:1.7; white-space:pre-wrap;">' + escapeHtml(data[s.key]) + '</p>';
            if (data[s.key + '_rationale']) {
                html += '<details style="margin-top:12px; padding:10px; background:#f8f9fa; border-radius:6px;">';
                html += '<summary style="cursor:pointer; font-weight:600; font-size:0.85rem; color:#6c757d;">Perche questa scelta</summary>';
                html += '<p style="margin-top:8px; font-size:0.85rem; color:#495057; line-height:1.6;">' + escapeHtml(data[s.key + '_rationale']) + '</p>';
                html += '</details>';
            }
            html += '</div></div>';
        });

        // Full letter.
        html += '<div class="jobaida-card" style="border:2px solid #0066cc;">';
        html += '<div class="jobaida-card-header" style="background:#0066cc; color:#fff; cursor:default;">Lettera Completa</div>';
        html += '<div class="jobaida-card-body">';
        html += '<pre style="white-space:pre-wrap; font-family:inherit; font-size:0.95rem; line-height:1.7; margin:0;" id="coaching-full-letter">' + escapeHtml(data.full_letter) + '</pre>';
        html += '<div style="margin-top:16px; display:flex; gap:10px;">';
        html += '<button onclick="copyCoachingLetter()" style="padding:8px 20px; background:#0066cc; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:500;">Copia Lettera</button>';
        html += '<button onclick="exportCoachingWord()" style="padding:8px 20px; background:#2ecc71; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:500;">&#128196; Esporta Word</button>';
        html += '</div></div></div>';

        document.getElementById('coaching-results').innerHTML = html;
        document.getElementById('coaching-step3').scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Copy the coaching letter to clipboard.
     */
    window.copyCoachingLetter = function() {
        var text = document.getElementById('coaching-full-letter').textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Lettera copiata!');
            }).catch(function() {
                showToast('Impossibile copiare', '#dc3545');
            });
        } else {
            fallbackCopy(text);
        }
    };

    // ========== EXPORT WORD ==========
    // Store last generated data for export.
    var lastExpressData = null;
    var lastCoachingData = null;

    // Override result display to save data.
    var origDisplayResults = window.displayResults;
    if (typeof origDisplayResults === 'function') {
        window.displayResults = function(data) {
            lastExpressData = data;
            origDisplayResults(data);
        };
    }

    var origDisplayCoachingResults = window.displayCoachingResults;
    if (typeof origDisplayCoachingResults === 'function') {
        window.displayCoachingResults = function(data) {
            lastCoachingData = data;
            origDisplayCoachingResults(data);
        };
    }

    // Also capture data from the AJAX responses directly.
    var origOnSuccess = null;

    window.exportWord = function() {
        // Get data from lastGeneratedData (set by Express generation).
        var data = lastGeneratedData || lastExpressData || getDataFromDOM('express');
        if (!data || !data.full_letter) {
            alert('Genera prima una lettera.');
            return;
        }
        submitWordExport(data);
    };

    window.exportCoachingWord = function() {
        var data = lastCoachingData || getDataFromDOM('coaching');
        if (!data) {
            alert('Genera prima una lettera.');
            return;
        }
        submitWordExport(data);
    };

    function getDataFromDOM(mode) {
        // Try to get data from visible result elements.
        var prefix = mode === 'coaching' ? 'coaching-' : '';
        var fullLetter = document.getElementById(prefix + 'full-letter');
        if (!fullLetter) return null;

        return {
            attention: document.getElementById(prefix + 'result-attention')?.textContent || '',
            attention_rationale: document.getElementById(prefix + 'result-attention-rationale')?.textContent || '',
            interest: document.getElementById(prefix + 'result-interest')?.textContent || '',
            interest_rationale: document.getElementById(prefix + 'result-interest-rationale')?.textContent || '',
            desire: document.getElementById(prefix + 'result-desire')?.textContent || '',
            desire_rationale: document.getElementById(prefix + 'result-desire-rationale')?.textContent || '',
            action: document.getElementById(prefix + 'result-action')?.textContent || '',
            action_rationale: document.getElementById(prefix + 'result-action-rationale')?.textContent || '',
            full_letter: fullLetter.textContent || ''
        };
    }

    function submitWordExport(data) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = M.cfg.wwwroot + '/local/jobaida/ajax_export_word.php';
        form.target = '_blank';

        var fields = {
            sesskey: M.cfg.sesskey,
            attention: data.attention || '',
            attention_rationale: data.attention_rationale || '',
            interest: data.interest || '',
            interest_rationale: data.interest_rationale || '',
            desire: data.desire || '',
            desire_rationale: data.desire_rationale || '',
            action: data.action || '',
            action_rationale: data.action_rationale || '',
            full_letter: data.full_letter || '',
            student_name: '<?php echo s(fullname($USER)); ?>'
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    // ========== LEARN & WRITE MODE ==========

    var learnState = {
        sections: ['attention', 'interest', 'desire', 'action'],
        currentIndex: 0,
        confirmed: {},
        jobAd: '',
        cvText: '',
        objectives: '',
        generating: false
    };

    var LEARN_AJAX_URL = M.cfg.wwwroot + '/local/jobaida/ajax_generate_section.php';

    var SECTION_META = {
        attention: {label: 'ATTENTION', desc: "Cattura l'Attenzione", color: '#dc3545', letter: 'A'},
        interest:  {label: 'INTEREST', desc: 'Suscita Interesse', color: '#0066cc', letter: 'I'},
        desire:    {label: 'DESIRE', desc: 'Crea il Desiderio', color: '#28a745', letter: 'D'},
        action:    {label: 'ACTION', desc: "Invito all'Azione", color: '#f59e0b', letter: 'A'}
    };

    /**
     * Update progress bar to reflect current state.
     */
    function learnUpdateProgress() {
        for (var i = 0; i < learnState.sections.length; i++) {
            var s = learnState.sections[i];
            var bar = document.getElementById('learn-prog-' + s);
            if (!bar) continue;
            if (learnState.confirmed[s]) {
                bar.style.background = SECTION_META[s].color;
                bar.style.animation = '';
            } else if (i === learnState.currentIndex) {
                bar.style.background = SECTION_META[s].color + '80';
                bar.style.animation = 'learnPulse 1.5s ease-in-out infinite';
            } else {
                bar.style.background = '#dee2e6';
                bar.style.animation = '';
            }
        }
    }

    /**
     * Build summary HTML for already-confirmed sections (shown above current).
     */
    function learnBuildConfirmedSummary() {
        var html = '';
        for (var i = 0; i < learnState.currentIndex; i++) {
            var s = learnState.sections[i];
            if (!learnState.confirmed[s]) continue;
            var meta = SECTION_META[s];
            var text = learnState.confirmed[s];
            var preview = text.length > 150 ? text.substring(0, 150) + '...' : text;
            html += '<div class="jobaida-card" style="border-left:4px solid ' + meta.color + '; opacity:0.85; margin-bottom:12px;">'
                + '<div class="jobaida-card-header" style="background:' + meta.color + '10; color:' + meta.color + '; cursor:default; padding:10px 16px; font-size:0.85rem;">'
                + '<span>&#10004; ' + meta.letter + ' - ' + meta.label + '</span>'
                + '<span style="font-size:0.75rem; color:#999;">Confermata</span>'
                + '</div>'
                + '<div class="jobaida-card-body" style="padding:10px 16px; font-size:0.85rem; color:#666; white-space:pre-wrap;">'
                + escapeHtml(preview)
                + '</div></div>';
        }
        return html;
    }

    /**
     * Generate the current section via AJAX.
     * @param {string} [userFeedback] - Optional feedback for rewrite.
     */
    function learnGenerate(userFeedback) {
        if (learnState.generating) return;
        learnState.generating = true;

        var section = learnState.sections[learnState.currentIndex];
        var meta = SECTION_META[section];

        learnUpdateProgress();

        // Show loading in workspace.
        var workspace = document.getElementById('learn-workspace');
        var html = learnBuildConfirmedSummary();
        html += '<div class="jobaida-card" style="border-left:4px solid ' + meta.color + ';">'
            + '<div class="jobaida-card-header" style="background:' + meta.color + '15; color:' + meta.color + '; cursor:default;">'
            + '<span style="font-weight:700; font-size:1.1rem; margin-right:8px;">' + meta.letter + '</span> '
            + meta.label + ' - ' + meta.desc
            + '</div>'
            + '<div class="jobaida-card-body" style="text-align:center; padding:40px;">'
            + '<div class="jobaida-spinner" style="border-color:' + meta.color + '30; border-top-color:' + meta.color + '; width:32px; height:32px; margin:0 auto 16px;"></div>'
            + '<p style="color:#6c757d; font-size:0.9rem;">L\'AI sta elaborando la sezione <strong>' + meta.label + '</strong>...</p>'
            + '<p style="color:#999; font-size:0.8rem;">Questo puo richiedere fino a 30 secondi</p>'
            + '</div></div>';
        workspace.innerHTML = html;

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('job_ad', learnState.jobAd);
        formData.append('cv_text', learnState.cvText);
        formData.append('objectives', learnState.objectives);
        formData.append('section', section);
        formData.append('previous_sections', JSON.stringify(learnState.confirmed));
        if (userFeedback) {
            formData.append('user_feedback', userFeedback);
        }

        fetch(LEARN_AJAX_URL, {method: 'POST', body: formData})
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            learnState.generating = false;
            if (resp.success) {
                learnDisplaySection(resp.data);
            } else {
                learnShowError(resp.message || 'Errore sconosciuto');
            }
        })
        .catch(function(err) {
            learnState.generating = false;
            learnShowError('Errore di rete: ' + err.message);
        });
    }

    /**
     * Show error in workspace with retry button.
     */
    function learnShowError(msg) {
        var workspace = document.getElementById('learn-workspace');
        var html = learnBuildConfirmedSummary();
        html += '<div class="jobaida-card" style="border-left:4px solid #dc3545;">'
            + '<div class="jobaida-card-body">'
            + '<p style="color:#dc3545; font-weight:600; margin-bottom:12px;">' + escapeHtml(msg) + '</p>'
            + '<button onclick="learnRetry()" style="padding:10px 24px; background:#0066cc; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:500;">Riprova</button>'
            + '</div></div>';
        workspace.innerHTML = html;
    }

    window.learnRetry = function() {
        learnGenerate();
    };

    /**
     * Display the AI-generated section with rationale, question, tips, and action buttons.
     */
    function learnDisplaySection(data) {
        var section = data.section;
        var meta = SECTION_META[section];
        var workspace = document.getElementById('learn-workspace');

        var html = learnBuildConfirmedSummary();

        // Current section card.
        html += '<div class="jobaida-card" style="border:2px solid ' + meta.color + '40;">';

        // Header.
        html += '<div style="background:' + meta.color + '; color:#fff; padding:14px 20px; font-weight:600; font-size:1rem;">'
            + '<span style="font-weight:700; font-size:1.2rem; margin-right:8px;">' + meta.letter + '</span> '
            + meta.label + ' - ' + meta.desc
            + '</div>';

        html += '<div class="jobaida-card-body">';

        // Feedback rejection warning (red box).
        if (data.feedback_applied === false && data.feedback_note) {
            html += '<div style="background:#fde8e8; border:2px solid #dc3545; border-radius:8px; padding:16px; margin-bottom:16px;">'
                + '<div style="font-size:0.8rem; color:#dc3545; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; font-weight:700;">Modifica non applicabile</div>'
                + '<div style="font-size:0.95rem; color:#991b1b; font-weight:500;">' + escapeHtml(data.feedback_note) + '</div>'
                + '</div>';
        }

        // Proposed text.
        html += '<div style="background:#f8f9fa; border-radius:8px; padding:16px; margin-bottom:16px;">'
            + '<div style="font-size:0.75rem; color:#999; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Testo proposto dall\'AI</div>'
            + '<div id="learn-proposed-text" style="font-size:0.95rem; line-height:1.7; color:#333; white-space:pre-wrap;">' + escapeHtml(data.section_text) + '</div>'
            + '</div>';

        // Rationale (educational).
        if (data.rationale) {
            html += '<div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px; margin-bottom:16px;">'
                + '<div style="font-size:0.75rem; color:#1e40af; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; font-weight:600;">Perche questa scelta</div>'
                + '<div style="font-size:0.88rem; line-height:1.6; color:#1e3a5f;">' + escapeHtml(data.rationale) + '</div>'
                + '</div>';
        }

        // Reflection question.
        if (data.question) {
            html += '<div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:16px; margin-bottom:16px;">'
                + '<div style="font-size:0.75rem; color:#92400e; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; font-weight:600;">Domanda di riflessione</div>'
                + '<div style="font-size:0.95rem; color:#78350f; font-weight:500;">' + escapeHtml(data.question) + '</div>'
                + '</div>';
        }

        // Tips.
        if (data.tips && data.tips.length > 0) {
            html += '<div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:16px; margin-bottom:16px;">'
                + '<div style="font-size:0.75rem; color:#166534; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; font-weight:600;">Suggerimenti formativi</div>'
                + '<ul style="margin:0; padding-left:20px;">';
            for (var i = 0; i < data.tips.length; i++) {
                html += '<li style="font-size:0.88rem; color:#166534; margin-bottom:4px;">' + escapeHtml(data.tips[i]) + '</li>';
            }
            html += '</ul></div>';
        }

        // Action buttons.
        var isLast = (learnState.currentIndex === learnState.sections.length - 1);
        var confirmLabel = isLast ? '&#10004; Conferma e Assembla Lettera' : '&#10004; Conferma e Prosegui';
        html += '<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">'
            + '<button onclick="learnConfirmSection()" style="flex:1; min-width:200px; padding:14px 24px; background:' + meta.color + '; color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;">'
            + confirmLabel + '</button>'
            + '<button onclick="learnShowFeedback()" id="btn-learn-modify" style="flex:1; min-width:200px; padding:14px 24px; background:#fff; color:' + meta.color + '; border:2px solid ' + meta.color + '; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer;">'
            + '&#9998; Chiedi Modifica</button>'
            + '</div>';

        // Feedback form (hidden initially).
        html += '<div id="learn-feedback-form" style="display:none; margin-top:16px;">'
            + '<label style="font-weight:600; display:block; margin-bottom:6px; font-size:0.9rem; color:#333;">Cosa vorresti cambiare?</label>'
            + '<textarea id="learn-feedback-text" rows="5" style="width:100%; border:2px solid ' + meta.color + '60; border-radius:8px; padding:12px; font-size:0.95rem; resize:vertical; box-sizing:border-box;" '
            + 'placeholder="Es: Vorrei un tono piu formale, aggiungi il mio stage presso X, cambia l\'apertura..."></textarea>'
            + '<div style="display:flex; gap:10px; margin-top:10px;">'
            + '<button onclick="learnRequestRewrite()" style="padding:10px 24px; background:' + meta.color + '; color:#fff; border:none; border-radius:6px; font-size:0.9rem; font-weight:600; cursor:pointer;">Riscrivi con le mie indicazioni</button>'
            + '<button onclick="learnHideFeedback()" style="padding:10px 24px; background:#f3f4f6; color:#6b7280; border:none; border-radius:6px; font-size:0.9rem; cursor:pointer;">Annulla</button>'
            + '</div></div>';

        html += '</div></div>'; // close card-body and card

        workspace.innerHTML = html;
        workspace.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Show/hide the feedback textarea for requesting modifications.
     */
    window.learnShowFeedback = function() {
        document.getElementById('learn-feedback-form').style.display = 'block';
        document.getElementById('learn-feedback-text').focus();
    };

    window.learnHideFeedback = function() {
        document.getElementById('learn-feedback-form').style.display = 'none';
    };

    /**
     * Start the Learn & Write flow.
     */
    window.learnStartSection = function(section) {
        var jobAd = document.getElementById('learn-jobad').value.trim();
        var cvText = document.getElementById('learn-cv').value.trim();

        if (!jobAd || !cvText) {
            alert('Compila Annuncio di Lavoro e CV prima di iniziare.');
            return;
        }

        // Store input data and reset state.
        learnState.jobAd = jobAd;
        learnState.cvText = cvText;
        learnState.objectives = document.getElementById('learn-objectives').value.trim();
        learnState.confirmed = {};
        learnState.generating = false;

        learnState.currentIndex = learnState.sections.indexOf(section);
        if (learnState.currentIndex === -1) learnState.currentIndex = 0;

        // Hide input form, show progress + workspace.
        document.getElementById('learn-step-input').style.display = 'none';
        document.getElementById('learn-progress').style.display = 'block';
        document.getElementById('learn-workspace').style.display = 'block';
        document.getElementById('learn-final').style.display = 'none';

        learnGenerate();
    };

    /**
     * Confirm the current section and move to next.
     */
    window.learnConfirmSection = function() {
        var section = learnState.sections[learnState.currentIndex];
        var proposedText = document.getElementById('learn-proposed-text');
        if (!proposedText) return;

        // Save confirmed text.
        learnState.confirmed[section] = proposedText.textContent;

        // Update progress bar to solid.
        var bar = document.getElementById('learn-prog-' + section);
        if (bar) {
            bar.style.background = SECTION_META[section].color;
            bar.style.animation = '';
        }

        showToast('Sezione ' + SECTION_META[section].label + ' confermata!');

        // Move to next section or assemble final.
        learnState.currentIndex++;
        if (learnState.currentIndex >= learnState.sections.length) {
            learnAssembleFinal();
        } else {
            learnGenerate();
        }
    };

    /**
     * Request a rewrite with user feedback.
     */
    window.learnRequestRewrite = function() {
        var feedback = document.getElementById('learn-feedback-text').value.trim();
        if (!feedback) {
            alert('Scrivi cosa vorresti cambiare prima di richiedere la riscrittura.');
            return;
        }
        learnState.pendingFeedback = feedback;
        learnGenerate(feedback);
    };

    /**
     * Assemble the final letter: call AI to wrap confirmed sections in proper Swiss format.
     */
    function learnAssembleFinal() {
        learnUpdateProgress();

        // Show loading in workspace while assembling.
        var workspace = document.getElementById('learn-workspace');
        workspace.innerHTML = '<div class="jobaida-card" style="border:2px solid #0066cc;">'
            + '<div class="jobaida-card-header" style="background:#0066cc; color:#fff; cursor:default;">Assemblaggio Lettera Finale</div>'
            + '<div class="jobaida-card-body" style="text-align:center; padding:40px;">'
            + '<div class="jobaida-spinner" style="border-color:#0066cc30; border-top-color:#0066cc; width:32px; height:32px; margin:0 auto 16px;"></div>'
            + '<p style="color:#6c757d; font-size:0.9rem;">Assemblaggio della lettera completa con intestazione svizzera...</p>'
            + '</div></div>';

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('job_ad', learnState.jobAd);
        formData.append('cv_text', learnState.cvText);
        formData.append('objectives', learnState.objectives);
        formData.append('section', 'assemble');
        formData.append('previous_sections', JSON.stringify(learnState.confirmed));

        fetch(LEARN_AJAX_URL, {method: 'POST', body: formData})
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success && resp.data.full_letter) {
                learnShowFinalLetter(resp.data.full_letter);
            } else {
                // Fallback: simple concatenation if assembly fails.
                var fallback = (learnState.confirmed.attention || '') + '\n\n'
                    + (learnState.confirmed.interest || '') + '\n\n'
                    + (learnState.confirmed.desire || '') + '\n\n'
                    + (learnState.confirmed.action || '');
                learnShowFinalLetter(fallback);
                showToast('Assemblaggio automatico non riuscito, lettera senza intestazione', '#f59e0b');
            }
        })
        .catch(function() {
            var fallback = (learnState.confirmed.attention || '') + '\n\n'
                + (learnState.confirmed.interest || '') + '\n\n'
                + (learnState.confirmed.desire || '') + '\n\n'
                + (learnState.confirmed.action || '');
            learnShowFinalLetter(fallback);
            showToast('Errore di rete, lettera senza intestazione', '#dc3545');
        });
    }

    /**
     * Display the final assembled letter with sections summary and action buttons.
     */
    function learnShowFinalLetter(fullLetter) {
        document.getElementById('learn-workspace').style.display = 'none';
        var finalDiv = document.getElementById('learn-final');
        finalDiv.style.display = 'block';

        var html = '';

        // Success header.
        html += '<div style="text-align:center; margin-bottom:24px;">'
            + '<div style="font-size:2.5rem; margin-bottom:8px;">&#127881;</div>'
            + '<h3 style="color:#059669; margin:0 0 8px;">Lettera Completata!</h3>'
            + '<p style="color:#6b7280; font-size:0.9rem;">Hai costruito la tua lettera sezione per sezione. Ecco il risultato.</p>'
            + '</div>';

        // Show each confirmed section.
        for (var i = 0; i < learnState.sections.length; i++) {
            var s = learnState.sections[i];
            var meta = SECTION_META[s];
            html += '<div class="jobaida-card" style="border-left:4px solid ' + meta.color + '; margin-bottom:12px;">'
                + '<div class="jobaida-card-header" style="background:' + meta.color + '10; color:' + meta.color + '; cursor:default; padding:10px 16px;">'
                + '<span style="font-weight:700; margin-right:6px;">' + meta.letter + '</span> ' + meta.label + ' - ' + meta.desc
                + '</div>'
                + '<div class="jobaida-card-body" style="padding:12px 16px;">'
                + '<div style="font-size:0.95rem; line-height:1.7; white-space:pre-wrap;">' + escapeHtml(learnState.confirmed[s] || '') + '</div>'
                + '</div></div>';
        }

        // Full assembled letter with proper Swiss format.
        html += '<div class="jobaida-card" style="border:2px solid #0066cc; margin-top:24px;">'
            + '<div class="jobaida-card-header" style="background:#0066cc; color:#fff; cursor:default;">Lettera Completa - Pronta da Inviare</div>'
            + '<div class="jobaida-card-body">'
            + '<div id="learn-full-letter" style="font-size:0.95rem; line-height:1.8; white-space:pre-wrap; color:#333;">' + escapeHtml(fullLetter) + '</div>'
            + '</div></div>';

        // Action buttons.
        html += '<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; margin-bottom:24px;">'
            + '<button onclick="learnCopyLetter()" style="padding:12px 24px; background:#0066cc; color:#fff; border:none; border-radius:6px; font-size:0.95rem; font-weight:600; cursor:pointer;">&#128203; Copia Lettera</button>'
            + '<button onclick="learnExportWord()" style="padding:12px 24px; background:#2ecc71; color:#fff; border:none; border-radius:6px; font-size:0.95rem; font-weight:600; cursor:pointer;">&#128196; Esporta Word</button>'
            + '<button onclick="learnSaveLetter()" id="btn-learn-save" style="padding:12px 24px; background:#28a745; color:#fff; border:none; border-radius:6px; font-size:0.95rem; font-weight:600; cursor:pointer;">&#128190; Salva nello Storico</button>'
            + '<button onclick="learnStartOver()" style="padding:12px 24px; background:#f3f4f6; color:#6b7280; border:none; border-radius:6px; font-size:0.95rem; cursor:pointer;">&#8634; Ricomincia</button>'
            + '</div>';

        finalDiv.innerHTML = html;
        finalDiv.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Copy Learn & Write final letter to clipboard.
     */
    window.learnCopyLetter = function() {
        var text = document.getElementById('learn-full-letter').textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Lettera copiata!');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    /**
     * Export Learn & Write letter as Word document.
     */
    window.learnExportWord = function() {
        submitWordExport({
            attention: learnState.confirmed.attention || '',
            attention_rationale: '',
            interest: learnState.confirmed.interest || '',
            interest_rationale: '',
            desire: learnState.confirmed.desire || '',
            desire_rationale: '',
            action: learnState.confirmed.action || '',
            action_rationale: '',
            full_letter: document.getElementById('learn-full-letter').textContent || ''
        });
    };

    /**
     * Save Learn & Write letter to history.
     */
    window.learnSaveLetter = function() {
        var btn = document.getElementById('btn-learn-save');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Salvataggio...';

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'save');
        formData.append('job_ad', learnState.jobAd);
        formData.append('cv_text', learnState.cvText);
        formData.append('objectives', learnState.objectives);
        formData.append('attention', learnState.confirmed.attention || '');
        formData.append('attention_rationale', '');
        formData.append('interest', learnState.confirmed.interest || '');
        formData.append('interest_rationale', '');
        formData.append('desire', learnState.confirmed.desire || '');
        formData.append('desire_rationale', '');
        formData.append('action_text', learnState.confirmed.action || '');
        formData.append('action_rationale', '');
        formData.append('full_letter', document.getElementById('learn-full-letter').textContent || '');
        formData.append('model_used', '');
        formData.append('tokens_used', 0);

        fetch(AJAX_URL, {method: 'POST', body: formData})
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = 'Salvata!';
                showToast('Lettera salvata nello storico');
            } else {
                btn.disabled = false;
                btn.textContent = 'Salva nello Storico';
                showToast(data.message || 'Errore nel salvataggio', '#dc3545');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Salva nello Storico';
            showToast('Errore di rete', '#dc3545');
        });
    };

    /**
     * Reset Learn & Write mode to start over.
     */
    window.learnStartOver = function() {
        learnState.currentIndex = 0;
        learnState.confirmed = {};
        learnState.generating = false;

        document.getElementById('learn-step-input').style.display = 'block';
        document.getElementById('learn-progress').style.display = 'none';
        document.getElementById('learn-workspace').style.display = 'none';
        document.getElementById('learn-workspace').innerHTML = '';
        document.getElementById('learn-final').style.display = 'none';
        document.getElementById('learn-final').innerHTML = '';

        // Reset progress bars.
        for (var i = 0; i < learnState.sections.length; i++) {
            var bar = document.getElementById('learn-prog-' + learnState.sections[i]);
            if (bar) {
                bar.style.background = '#dee2e6';
                bar.style.animation = '';
            }
        }
    };

})();
</script>

<?php
echo $OUTPUT->footer();
