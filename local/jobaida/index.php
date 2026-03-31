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
        </div>
    </div>

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

        // Validate minimum length.
        if (jobAd.length < 50 || cvText.length < 50) {
            showError(STRINGS.error_short);
            // Highlight invalid fields.
            if (jobAd.length < 50) {
                document.getElementById('jobaida-jobad').classList.add('is-invalid');
            }
            if (cvText.length < 50) {
                document.getElementById('jobaida-cv').classList.add('is-invalid');
            }
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

})();
</script>

<?php
echo $OUTPUT->footer();
