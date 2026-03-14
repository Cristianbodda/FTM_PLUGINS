<?php
// ============================================
// Self Assessment - Library Functions
// ============================================
// Integrazione con navigazione Moodle
// ============================================

defined('MOODLE_INTERNAL') || die();

/**
 * Aggiunge link nel menu di navigazione
 * Per STUDENTI: link alla compilazione autovalutazione
 * Per COACH/DOCENTI: link alla dashboard gestione
 */
function local_selfassessment_extend_navigation(global_navigation $navigation) {
    global $USER, $DB, $PAGE, $CFG, $SESSION;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    // Per gli studenti: mostra "La mia Autovalutazione"
    if (has_capability('local/selfassessment:complete', $context)) {
        // Verifica se l'autovalutazione è abilitata per questo utente
        $status = $DB->get_record('local_selfassessment_status', ['userid' => $USER->id]);
        $is_enabled = !$status || $status->enabled == 1;

        if ($is_enabled) {
            $node = $navigation->add(
                get_string('myassessment', 'local_selfassessment'),
                new moodle_url('/local/selfassessment/compile.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'selfassessment_compile',
                new pix_icon('i/grades', '')
            );
        }
    }

    // Per coach/docenti: mostra "Gestione Autovalutazioni"
    if (has_capability('local/selfassessment:view', $context)) {
        $node = $navigation->add(
            get_string('manageassessments', 'local_selfassessment'),
            new moodle_url('/local/selfassessment/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'selfassessment_manage',
            new pix_icon('i/report', '')
        );
    }

    // =========================================================
    // FALLBACK POPUP: inietta popup autovalutazione via JS.
    // Necessario perché il tema Adaptable non chiama l'hook
    // before_standard_head_html_generation.
    // =========================================================
    local_selfassessment_inject_popup_fallback();
}

/**
 * Inietta il popup/banner autovalutazione via additionalhtmlfooter.
 *
 * IMPORTANTE: extend_navigation() viene chiamato DOPO che standard_head_html()
 * ha già letto $CFG->additionalhtmlhead, quindi additionalhtmlhead NON funziona.
 * Usiamo additionalhtmlfooter che viene letto da standard_end_of_body_html()
 * che è SEMPRE chiamato DOPO la navigazione. Funziona con qualsiasi tema.
 *
 * Chiamato da extend_navigation come fallback per tema Adaptable.
 */
function local_selfassessment_inject_popup_fallback() {
    global $USER, $CFG, $SESSION;

    // Evita doppia iniezione.
    static $injected = false;
    if ($injected) {
        return;
    }

    // Mai per admin.
    if (is_siteadmin()) {
        return;
    }

    // Toggle admin.
    if (!get_config('local_selfassessment', 'popup_enabled')) {
        return;
    }

    // Mai durante CLI o AJAX.
    if (defined('CLI_SCRIPT') || defined('AJAX_SCRIPT') || defined('ABORT_AFTER_CONFIG')) {
        return;
    }

    // Non durante upgrade.
    if (during_initial_install() || !empty($CFG->upgraderunning)) {
        return;
    }

    // Verifica reminder status.
    $status = local_selfassessment_get_reminder_status($USER->id);

    // Redirect dopo quiz.
    if (!empty($SESSION->selfassessment_redirect_pending) && $status['should_show']) {
        unset($SESSION->selfassessment_redirect_pending);
        $compile_url = (new moodle_url('/local/selfassessment/compile.php'))->out(false);
        // Footer JS: il redirect funziona da qualsiasi posizione nel DOM.
        $CFG->additionalhtmlfooter = ($CFG->additionalhtmlfooter ?? '') .
            '<script>window.location.href = "' . $compile_url . '";</script>';
        $injected = true;
        return;
    }

    if (!$status['should_show']) {
        return;
    }

    $injected = true;

    $pending = (int)$status['pending_count'];
    $total = (int)$status['total_count'];
    $compile_url = (new moodle_url('/local/selfassessment/compile.php'))->out(false);

    // Inietta via additionalhtmlfooter (prima di </body>).
    // Il JS nel footer ha accesso completo al DOM già costruito.
    $script = '<script>
(function() {
    "use strict";
    if (document.getElementById("sa-popup-banner")) return;

    var css = document.createElement("style");
    css.textContent = "#sa-popup-banner{position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#0066cc,#004499);color:#fff;padding:14px 20px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;display:flex;align-items:center;justify-content:space-between;box-shadow:0 3px 12px rgba(0,0,0,.3);animation:sa-slide .4s ease-out}@keyframes sa-slide{from{transform:translateY(-100%)}to{transform:translateY(0)}}#sa-popup-banner .sa-text{font-size:14px;font-weight:500}#sa-popup-banner strong{font-weight:700}#sa-popup-banner .sa-badge{display:inline-block;background:rgba(255,255,255,.2);padding:2px 10px;border-radius:12px;font-size:12px;margin-left:8px}#sa-popup-banner .sa-actions{display:flex;gap:8px;align-items:center;flex-shrink:0}#sa-popup-banner .sa-btn{display:inline-block;padding:8px 18px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none}#sa-popup-banner .sa-btn-go{background:#fff;color:#0066cc}#sa-popup-banner .sa-btn-go:hover{background:#e6f0ff}#sa-popup-banner .sa-btn-skip{background:transparent;color:rgba(255,255,255,.7);font-size:12px;cursor:pointer;border:none}#sa-popup-banner .sa-btn-skip:hover{color:#fff}body.sa-active{padding-top:52px!important}#sa-modal-bg{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:100000;justify-content:center;align-items:center}#sa-modal-bg.sa-show{display:flex}#sa-modal-box{background:#fff;border-radius:12px;padding:32px;max-width:440px;width:90%;text-align:center;font-family:-apple-system,sans-serif;box-shadow:0 8px 30px rgba(0,0,0,.3)}#sa-modal-box h3{margin:0 0 12px;font-size:20px;color:#1a1a2e}#sa-modal-box p{color:#555;font-size:14px;line-height:1.5;margin:0 0 20px}#sa-modal-box .sa-mbtn{display:inline-block;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;margin:4px;cursor:pointer;border:none}#sa-modal-box .sa-mbtn-go{background:#0066cc;color:#fff}#sa-modal-box .sa-mbtn-later{background:#f0f0f0;color:#555}";
    document.head.appendChild(css);

    var b = document.createElement("div");
    b.id = "sa-popup-banner";
    b.innerHTML = "<div class=\"sa-text\"><strong>Autovalutazione da completare</strong><span class=\"sa-badge\">' . $pending . ' competenze</span></div><div class=\"sa-actions\"><a href=\"' . $compile_url . '\" class=\"sa-btn sa-btn-go\">Compila ora</a><button class=\"sa-btn-skip\" id=\"sa-skip-btn\">Dopo</button></div>";
    document.body.insertBefore(b, document.body.firstChild);
    document.body.classList.add("sa-active");

    document.getElementById("sa-skip-btn").addEventListener("click", function() {
        document.getElementById("sa-popup-banner").style.display = "none";
        document.body.classList.remove("sa-active");
    });

    var m = document.createElement("div");
    m.id = "sa-modal-bg";
    m.innerHTML = "<div id=\"sa-modal-box\"><div style=\"font-size:40px;margin-bottom:12px\">&#x1F4CB;</div><h3>Autovalutazione in attesa</h3><p>Hai <strong>' . $pending . ' competenze su ' . $total . '</strong> da valutare.<br>Completa per proseguire il tuo percorso formativo.</p><a href=\"' . $compile_url . '\" class=\"sa-mbtn sa-mbtn-go\">Compila Autovalutazione</a> <button class=\"sa-mbtn sa-mbtn-later\" id=\"sa-later-btn\">Ricordamelo dopo</button></div>";
    document.body.appendChild(m);

    document.getElementById("sa-later-btn").addEventListener("click", function() {
        document.getElementById("sa-modal-bg").classList.remove("sa-show");
    });

    setTimeout(function(){ m.classList.add("sa-show"); }, 3000);
})();
</script>';

    $CFG->additionalhtmlfooter = ($CFG->additionalhtmlfooter ?? '') . $script;
}

/**
 * Aggiunge link nelle impostazioni del sito
 */
function local_selfassessment_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;
    
    if (!has_capability('local/selfassessment:manage', context_system::instance())) {
        return;
    }
    
    if ($settingnode = $settingsnav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        $node = $settingnode->add(
            get_string('pluginname', 'local_selfassessment'),
            new moodle_url('/local/selfassessment/index.php'),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * Ritorna i livelli Bloom con descrizioni
 */
function local_selfassessment_get_bloom_levels() {
    return [
        1 => [
            'nome' => get_string('level1', 'local_selfassessment'),
            'descrizione' => get_string('level1_desc', 'local_selfassessment'),
            'colore' => '#e74c3c',
            'icona' => '🔴'
        ],
        2 => [
            'nome' => get_string('level2', 'local_selfassessment'),
            'descrizione' => get_string('level2_desc', 'local_selfassessment'),
            'colore' => '#e67e22',
            'icona' => '🟠'
        ],
        3 => [
            'nome' => get_string('level3', 'local_selfassessment'),
            'descrizione' => get_string('level3_desc', 'local_selfassessment'),
            'colore' => '#f1c40f',
            'icona' => '🟡'
        ],
        4 => [
            'nome' => get_string('level4', 'local_selfassessment'),
            'descrizione' => get_string('level4_desc', 'local_selfassessment'),
            'colore' => '#27ae60',
            'icona' => '🟢'
        ],
        5 => [
            'nome' => get_string('level5', 'local_selfassessment'),
            'descrizione' => get_string('level5_desc', 'local_selfassessment'),
            'colore' => '#3498db',
            'icona' => '🔵'
        ],
        6 => [
            'nome' => get_string('level6', 'local_selfassessment'),
            'descrizione' => get_string('level6_desc', 'local_selfassessment'),
            'colore' => '#9b59b6',
            'icona' => '🟣'
        ],
    ];
}

/**
 * Verifica se l'autovalutazione è abilitata per un utente
 */
function local_selfassessment_is_enabled($userid) {
    global $DB;
    
    $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
    
    // Default: abilitata (se non c'è record, è abilitata)
    if (!$status) {
        return true;
    }
    
    return (bool) $status->enabled;
}

/**
 * Verifica se l'utente ha già compilato l'autovalutazione
 */
function local_selfassessment_is_completed($userid) {
    global $DB;
    
    $count = $DB->count_records('local_selfassessment', ['userid' => $userid]);
    return $count > 0;
}

/**
 * Ottiene la data dell'ultima autovalutazione
 */
function local_selfassessment_get_last_update($userid) {
    global $DB;
    
    $last = $DB->get_field_sql(
        "SELECT MAX(timemodified) FROM {local_selfassessment} WHERE userid = ?",
        [$userid]
    );
    
    return $last ? $last : null;
}

/**
 * Calcola la percentuale di completamento
 */
function local_selfassessment_get_completion_percent($userid, $total_competencies) {
    global $DB;

    if ($total_competencies == 0) {
        return 0;
    }

    $completed = $DB->count_records('local_selfassessment', ['userid' => $userid]);
    return round(($completed / $total_competencies) * 100);
}

// ============================================
// SISTEMA REMINDER INVASIVI
// ============================================

/**
 * Codici di skip per l'autovalutazione
 */
if (!defined('SELFASSESSMENT_SKIP_TEMP')) {
    define('SELFASSESSMENT_SKIP_TEMP', '6807');      // Skip temporaneo (sessione)
}
if (!defined('SELFASSESSMENT_SKIP_PERMANENT')) {
    define('SELFASSESSMENT_SKIP_PERMANENT', 'FTM');  // Skip definitivo (database)
}

/**
 * Verifica centralizzata: lo studente deve vedere i reminder?
 * Ritorna array con info sullo stato
 *
 * @param int $userid User ID
 * @return array ['should_show' => bool, 'pending_count' => int, 'total_count' => int, 'skip_type' => string|null]
 */
function local_selfassessment_get_reminder_status($userid) {
    global $DB;

    $result = [
        'should_show' => false,
        'pending_count' => 0,
        'total_count' => 0,
        'completed_count' => 0,
        'skip_type' => null,
        'has_permanent_skip' => false
    ];

    // Verifica se ha capability (è uno studente)
    $context = context_system::instance();
    if (!has_capability('local/selfassessment:complete', $context, $userid)) {
        return $result;
    }

    // Verifica se coach/admin (non mostrare reminder)
    if (has_capability('local/selfassessment:view', $context, $userid)) {
        return $result;
    }

    // Verifica se autovalutazione disabilitata per questo utente
    if (!local_selfassessment_is_enabled($userid)) {
        return $result;
    }

    // Verifica skip permanente nel database
    $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
    if ($status && $status->skip_accepted) {
        $result['has_permanent_skip'] = true;
        $result['skip_type'] = 'permanent';
        return $result;
    }

    // Conta competenze assegnate
    $assigned = $DB->get_records('local_selfassessment_assign', ['userid' => $userid]);
    $total = count($assigned);

    if ($total == 0) {
        // Nessuna competenza assegnata
        return $result;
    }

    $result['total_count'] = $total;

    // Conta competenze già valutate
    $assessed = $DB->get_records('local_selfassessment', ['userid' => $userid]);
    $assessed_by_comp = [];
    foreach ($assessed as $a) {
        if ($a->level > 0) {
            $assessed_by_comp[$a->competencyid] = true;
        }
    }

    // Conta pending
    $completed = 0;
    foreach ($assigned as $a) {
        if (isset($assessed_by_comp[$a->competencyid])) {
            $completed++;
        }
    }

    $result['completed_count'] = $completed;
    $result['pending_count'] = $total - $completed;

    // Se ci sono competenze da valutare, mostra reminder
    if ($result['pending_count'] > 0) {
        $result['should_show'] = true;
    }

    return $result;
}

// ============================================
// NOTA: Il callback per l'iniezione HTML è ora in:
// classes/hook_callbacks.php (nuovo sistema hook Moodle 4.3+)
// Registrato in: db/hooks.php
// ============================================
