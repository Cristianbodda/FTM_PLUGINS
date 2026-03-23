<?php
// ============================================
// Self Assessment - Library Functions
// ============================================
// Integrazione con navigazione Moodle
// ============================================

defined('MOODLE_INTERNAL') || die();

/**
 * Inietta popup autovalutazione nella navbar (Moodle 4.x Boost-based themes).
 * Questo callback viene chiamato SEMPRE da core_renderer::render_navbar_output().
 * È il metodo più affidabile per iniettare contenuto in Moodle 4.x.
 *
 * @param \renderer_base $renderer
 * @return string HTML da aggiungere alla navbar
 */
function local_selfassessment_render_navbar_output(\renderer_base $renderer): string {
    global $USER, $CFG, $SESSION;

    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return '';
    }

    if (!get_config('local_selfassessment', 'popup_enabled')) {
        return '';
    }

    if (defined('CLI_SCRIPT') || defined('AJAX_SCRIPT') || defined('ABORT_AFTER_CONFIG')) {
        return '';
    }

    if (during_initial_install() || !empty($CFG->upgraderunning)) {
        return '';
    }

    require_once($CFG->dirroot . '/local/selfassessment/lib.php');

    $status = local_selfassessment_get_reminder_status($USER->id);
    if (!$status['should_show']) {
        return '';
    }

    $pending = (int)$status['pending_count'];
    $total = (int)$status['total_count'];
    $compile_url = (new moodle_url('/local/selfassessment/compile.php'))->out(false);

    // Redirect immediato dopo quiz (se flag pendente)
    if (!empty($SESSION->selfassessment_redirect_pending)) {
        unset($SESSION->selfassessment_redirect_pending);
        return '<script>window.location.href = "' . $compile_url . '";</script>';
    }

    // Banner fisso + modal popup
    return '
    <style>
    #sa-navbar-banner{position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:center;gap:15px;box-shadow:0 3px 15px rgba(0,0,0,.3);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    #sa-navbar-banner .sa-icon{font-size:1.5em;animation:sa-b 1s ease infinite}
    @keyframes sa-b{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
    #sa-navbar-banner .sa-text{font-weight:600;font-size:.95em}
    #sa-navbar-banner .sa-count{background:rgba(255,255,255,.25);padding:4px 12px;border-radius:20px;font-weight:700}
    #sa-navbar-banner .sa-btn{background:#fff;color:#ee5a24;border:none;padding:8px 20px;border-radius:20px;font-weight:700;cursor:pointer;text-decoration:none}
    #sa-navbar-banner .sa-btn:hover{transform:scale(1.05);box-shadow:0 2px 10px rgba(0,0,0,.2)}
    body.sa-active{padding-top:55px!important}
    #sa-modal-bg{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:100000;align-items:center;justify-content:center}
    #sa-modal-bg.sa-show{display:flex}
    #sa-modal-box{background:#fff;border-radius:16px;padding:35px;max-width:460px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:sa-pop .4s ease}
    @keyframes sa-pop{from{transform:scale(.8);opacity:0}to{transform:scale(1);opacity:1}}
    #sa-modal-box h3{margin:0 0 10px;font-size:1.4em;color:#2c3e50}
    #sa-modal-box p{color:#666;line-height:1.6;margin:0 0 20px}
    #sa-modal-box .sa-mbtn{display:inline-block;padding:12px 28px;border-radius:25px;font-size:1em;font-weight:600;text-decoration:none;margin:5px;cursor:pointer;border:none}
    #sa-modal-box .sa-go{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
    #sa-modal-box .sa-later{background:#f0f0f0;color:#555}
    #sa-modal-box .sa-skip-area{border-top:1px solid #eee;padding-top:15px;margin-top:15px}
    #sa-modal-box .sa-skip-row{display:flex;gap:8px;justify-content:center}
    #sa-modal-box .sa-skip-input{padding:8px 12px;border:2px solid #e0e0e0;border-radius:8px;text-align:center;letter-spacing:2px;width:100px;font-size:1em}
    #sa-modal-box .sa-skip-btn{padding:8px 14px;background:#95a5a6;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600}
    .sa-skip-msg{font-size:.85em;margin-top:8px}
    </style>

    <div id="sa-navbar-banner">
        <span class="sa-icon">📋</span>
        <span class="sa-text">Hai competenze da autovalutare!</span>
        <span class="sa-count">' . $pending . ' di ' . $total . '</span>
        <a href="' . $compile_url . '" class="sa-btn">Compila Ora</a>
    </div>

    <div id="sa-modal-bg">
        <div id="sa-modal-box">
            <div style="font-size:3em;margin-bottom:10px">📝</div>
            <h3>Autovalutazione in Sospeso</h3>
            <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:6px 18px;border-radius:20px;display:inline-block;margin-bottom:15px;font-weight:600">' . $pending . ' competenze da valutare</div>
            <p>Prima di continuare, completa l\'autovalutazione delle tue competenze.<br>Questo aiuterà il tuo coach a capire dove hai bisogno di supporto.</p>
            <a href="' . $compile_url . '" class="sa-mbtn sa-go">Compila Autovalutazione</a>
            <button class="sa-mbtn sa-later" onclick="document.getElementById(\'sa-modal-bg\').classList.remove(\'sa-show\')">Continua Senza Compilare</button>
            <div class="sa-skip-area">
                <p style="color:#999;font-size:.85em;margin-bottom:8px">Hai un codice di bypass?</p>
                <div class="sa-skip-row">
                    <input type="text" class="sa-skip-input" id="saSkipCode" placeholder="Codice" maxlength="4">
                    <button class="sa-skip-btn" onclick="saSkip()">Salta</button>
                </div>
                <div id="saSkipMsg" class="sa-skip-msg" style="display:none"></div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var skipped = sessionStorage.getItem("sa_skip") === "1";
        if (skipped) {
            var b = document.getElementById("sa-navbar-banner");
            if (b) b.style.display = "none";
            return;
        }
        document.body.classList.add("sa-active");
        var shown = sessionStorage.getItem("sa_modal_shown");
        if (!shown) {
            setTimeout(function(){
                var m = document.getElementById("sa-modal-bg");
                if (m) m.classList.add("sa-show");
                sessionStorage.setItem("sa_modal_shown", "1");
            }, 1500);
        }
    })();
    function saSkip(){
        var code = document.getElementById("saSkipCode").value.toUpperCase().trim();
        var msg = document.getElementById("saSkipMsg");
        if (code === "6807") {
            sessionStorage.setItem("sa_skip", "1");
            msg.style.display = "block";
            msg.style.color = "#27ae60";
            msg.textContent = "Skip temporaneo attivato";
            setTimeout(function(){
                document.getElementById("sa-navbar-banner").style.display = "none";
                document.body.classList.remove("sa-active");
                document.getElementById("sa-modal-bg").classList.remove("sa-show");
            }, 800);
        } else if (code === "FTM") {
            msg.style.display = "block";
            msg.style.color = "#27ae60";
            msg.textContent = "Salvataggio...";
            fetch("' . (new moodle_url('/local/selfassessment/ajax_skip_permanent.php', ['sesskey' => sesskey()]))->out(false) . '", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({action: "skip_permanent"})
            }).then(function(r){return r.json()}).then(function(d){
                if(d.success){
                    msg.textContent = "Skip permanente salvato";
                    setTimeout(function(){
                        document.getElementById("sa-navbar-banner").style.display = "none";
                        document.body.classList.remove("sa-active");
                        document.getElementById("sa-modal-bg").classList.remove("sa-show");
                    }, 1000);
                }
            });
        } else {
            msg.style.display = "block";
            msg.style.color = "#e74c3c";
            msg.textContent = "Codice non valido";
            document.getElementById("saSkipCode").value = "";
        }
    }
    document.getElementById("saSkipCode").addEventListener("keypress", function(e){if(e.key==="Enter")saSkip()});
    </script>';
}

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

    // =========================================================
    // INIEZIONE DIRETTA via $PAGE->requires (funziona con TUTTI i temi)
    // $PAGE->requires->js_init_code() aggiunge JS al loader AMD/YUI
    // che viene eseguito SEMPRE, indipendentemente dal tema.
    // =========================================================
    if (!is_siteadmin() && get_config('local_selfassessment', 'popup_enabled')
        && !defined('CLI_SCRIPT') && !defined('AJAX_SCRIPT')
        && !during_initial_install() && empty($CFG->upgraderunning)) {

        $sa_status = local_selfassessment_get_reminder_status($USER->id);
        if (!empty($sa_status['should_show'])) {
            $sa_pending = (int)$sa_status['pending_count'];
            $sa_total = (int)$sa_status['total_count'];
            $sa_url = (new moodle_url('/local/selfassessment/compile.php'))->out(false);

            // Redirect immediato dopo quiz
            if (!empty($SESSION->selfassessment_redirect_pending)) {
                unset($SESSION->selfassessment_redirect_pending);
                $PAGE->requires->js_init_code('window.location.href = "' . $sa_url . '";', true);
            } else {
                // Banner + Modal via DOM injection
                $sa_js = '
(function(){
    if (document.getElementById("sa-inject-banner")) return;
    var css = document.createElement("style");
    css.textContent = "#sa-inject-banner{position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:center;gap:15px;box-shadow:0 3px 15px rgba(0,0,0,.3);font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif}#sa-inject-banner .sa-i{font-size:1.5em}#sa-inject-banner .sa-t{font-weight:600}#sa-inject-banner .sa-c{background:rgba(255,255,255,.25);padding:4px 12px;border-radius:20px;font-weight:700}#sa-inject-banner .sa-b{background:#fff;color:#ee5a24;border:none;padding:8px 20px;border-radius:20px;font-weight:700;cursor:pointer;text-decoration:none}body.sa-on{padding-top:55px!important}#sa-inject-modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:100000;align-items:center;justify-content:center}#sa-inject-modal.sa-show{display:flex}#sa-inject-modal .sa-box{background:#fff;border-radius:16px;padding:35px;max-width:460px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}#sa-inject-modal .sa-box h3{margin:0 0 10px;font-size:1.4em;color:#2c3e50}#sa-inject-modal .sa-box p{color:#666;line-height:1.6;margin:0 0 20px}#sa-inject-modal .sa-go{display:inline-block;padding:12px 28px;border-radius:25px;font-weight:600;text-decoration:none;margin:5px;border:none;cursor:pointer;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}#sa-inject-modal .sa-no{display:inline-block;padding:12px 28px;border-radius:25px;font-weight:600;margin:5px;border:none;cursor:pointer;background:#f0f0f0;color:#555}";
    document.head.appendChild(css);
    if (sessionStorage.getItem("sa_injskip")==="1") return;
    var b = document.createElement("div");
    b.id = "sa-inject-banner";
    b.innerHTML = "<span class=sa-i>\\uD83D\\uDCCB</span><span class=sa-t>Hai competenze da autovalutare!</span><span class=sa-c>' . $sa_pending . ' di ' . $sa_total . '</span><a href=\\"' . $sa_url . '\\" class=sa-b>Compila Ora</a>";
    document.body.insertBefore(b, document.body.firstChild);
    document.body.classList.add("sa-on");
    if (!sessionStorage.getItem("sa_injmod")) {
        setTimeout(function(){
            var m = document.createElement("div");
            m.id = "sa-inject-modal";
            m.innerHTML = "<div class=sa-box><div style=\\"font-size:3em;margin-bottom:10px\\">\\uD83D\\uDCDD</div><h3>Autovalutazione in Sospeso</h3><p>Hai <strong>' . $sa_pending . ' competenze</strong> da autovalutare.<br>Completa per proseguire il tuo percorso formativo.</p><a href=\\"' . $sa_url . '\\" class=sa-go>Compila Autovalutazione</a><button class=sa-no onclick=\\"this.parentNode.parentNode.classList.remove(\'sa-show\')\\">Dopo</button></div>";
            document.body.appendChild(m);
            m.classList.add("sa-show");
            sessionStorage.setItem("sa_injmod", "1");
        }, 1500);
    }
})();';
                $PAGE->requires->js_init_code($sa_js, true);
            }
        }
    }
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
 * Fallback before_footer: chiamato da standard_end_of_body_html() in TUTTI i temi.
 * Questo è il metodo più affidabile per iniettare contenuto in Moodle 4.x.
 * Viene chiamato SEMPRE, indipendentemente dal tema o dalla versione di Moodle.
 */
function local_selfassessment_before_footer() {
    // Riusa la stessa logica del fallback popup
    local_selfassessment_inject_popup_fallback();
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
