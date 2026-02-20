<?php
// ============================================
// Self Assessment - Hook Callbacks (Moodle 4.x+)
// ============================================
// Nuovo sistema di hook per iniettare contenuto
// ============================================

namespace local_selfassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_selfassessment
 */
class hook_callbacks {

    /**
     * Callback for before_standard_head_html_generation hook
     * Inietta banner, modal e script per reminder invasivi
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $USER, $PAGE, $CFG, $SESSION;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Mai per admin del sito (non hanno bisogno di autovalutazione)
        if (is_siteadmin()) {
            return;
        }

        // Mai durante CLI o AJAX di sistema
        if (defined('CLI_SCRIPT') || defined('ABORT_AFTER_CONFIG')) {
            return;
        }

        // Protezione: se $PAGE->url non è impostata, non fare nulla
        try {
            $current_url = $PAGE->url->out_omit_querystring();
        } catch (\Throwable $e) {
            return;
        }

        // Non mostrare nelle pagine admin (upgrade, settings, ecc.)
        if (strpos($current_url, '/admin/') !== false || strpos($current_url, '/admin.php') !== false) {
            return;
        }

        // Non mostrare durante installazione/upgrade
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }

        // Carica lib.php per le funzioni helper
        require_once($CFG->dirroot . '/local/selfassessment/lib.php');

        // Non mostrare nella pagina compile.php (già gestita lì)
        if (strpos($current_url, '/local/selfassessment/compile.php') !== false) {
            // Se c'è un redirect pending, resettalo (siamo già su compile.php)
            if (!empty($SESSION->selfassessment_redirect_pending)) {
                unset($SESSION->selfassessment_redirect_pending);
            }
            return;
        }

        // Verifica se deve mostrare reminder
        $status = local_selfassessment_get_reminder_status($USER->id);

        // REDIRECT DOPO QUIZ: se c'è flag di redirect pendente, fai redirect immediato
        $redirect_pending = !empty($SESSION->selfassessment_redirect_pending);
        if ($redirect_pending && $status['should_show']) {
            // Resetta il flag
            unset($SESSION->selfassessment_redirect_pending);
            // Redirect a compile.php (sarà eseguito via JavaScript per evitare problemi con header)
            $compile_url = new \moodle_url('/local/selfassessment/compile.php');
            $hook->add_html('<script>window.location.href = "' . $compile_url->out() . '";</script>');
            return;
        }

        if (!$status['should_show']) {
            return;
        }

        // Prepara dati per JavaScript
        $compile_url = new \moodle_url('/local/selfassessment/compile.php');
        $ajax_url = new \moodle_url('/local/selfassessment/ajax_skip_permanent.php', ['sesskey' => sesskey()]);

        $pending = $status['pending_count'];
        $total = $status['total_count'];

        // Verifica se siamo su una pagina quiz (per il blocco quiz)
        $is_quiz_page = (strpos($current_url, '/mod/quiz/view.php') !== false ||
                         strpos($current_url, '/mod/quiz/startattempt.php') !== false);

        // Output CSS e JS per banner e modal
        $output = self::get_reminder_html($compile_url, $ajax_url, $pending, $total, $CFG->wwwroot);

        // QUIZ BLOCKING: se siamo su una pagina quiz, aggiungi blocco
        if ($is_quiz_page) {
            $output .= self::get_quiz_block_html($compile_url, $pending);
        }

        $hook->add_html($output);
    }

    /**
     * Genera HTML per banner e modal reminder
     */
    private static function get_reminder_html($compile_url, $ajax_url, $pending, $total, $wwwroot): string {
        $compile_url_str = $compile_url->out();
        $ajax_url_str = $ajax_url->out(false);

        return <<<HTML
<style>
/* ============================================
   SELFASSESSMENT INVASIVE REMINDER
   ============================================ */

/* BANNER PERSISTENTE (Top of every page) */
#sa-reminder-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
    padding: 12px 20px;
    z-index: 99998;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    animation: sa-banner-pulse 2s ease-in-out infinite;
}

@keyframes sa-banner-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.9; }
}

#sa-reminder-banner .sa-banner-icon {
    font-size: 1.5em;
    animation: sa-bounce 1s ease infinite;
}

@keyframes sa-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

#sa-reminder-banner .sa-banner-text {
    font-weight: 600;
    font-size: 0.95em;
}

#sa-reminder-banner .sa-banner-count {
    background: rgba(255,255,255,0.25);
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 700;
}

#sa-reminder-banner .sa-banner-btn {
    background: white;
    color: #ee5a24;
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

#sa-reminder-banner .sa-banner-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

/* Sposta il contenuto Moodle per fare spazio al banner */
body.sa-has-banner {
    padding-top: 55px !important;
}

body.sa-has-banner #page-wrapper,
body.sa-has-banner .navbar {
    margin-top: 0 !important;
}

/* LOGIN MODAL */
#sa-login-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

#sa-login-modal-overlay.sa-show {
    display: flex;
}

.sa-login-modal {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 480px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    animation: sa-modal-bounce 0.5s ease;
}

@keyframes sa-modal-bounce {
    0% { transform: scale(0.8); opacity: 0; }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); opacity: 1; }
}

.sa-login-modal .sa-modal-icon {
    font-size: 4em;
    margin-bottom: 15px;
}

.sa-login-modal h2 {
    color: #2c3e50;
    margin: 0 0 10px 0;
    font-size: 1.6em;
}

.sa-login-modal .sa-modal-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    display: inline-block;
    margin-bottom: 15px;
    font-weight: 600;
}

.sa-login-modal p {
    color: #7f8c8d;
    margin-bottom: 25px;
    line-height: 1.6;
}

.sa-login-modal .btn-primary-sa {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 14px 35px;
    font-size: 1.1em;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    width: 100%;
    margin-bottom: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.sa-login-modal .btn-primary-sa:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.sa-login-modal .sa-modal-skip {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 10px;
}

.sa-login-modal .sa-modal-skip p {
    font-size: 0.85em;
    margin-bottom: 12px;
    color: #95a5a6;
}

.sa-login-modal .sa-skip-input-group {
    display: flex;
    gap: 10px;
}

.sa-login-modal .sa-skip-input {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 1em;
    text-align: center;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.sa-login-modal .sa-skip-input:focus {
    border-color: #667eea;
    outline: none;
}

.sa-login-modal .btn-skip-sa {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
}

.sa-login-modal .btn-skip-sa:hover {
    background: #7f8c8d;
}

.sa-login-modal .sa-skip-error {
    color: #e74c3c;
    font-size: 0.85em;
    margin-top: 10px;
    display: none;
}

.sa-login-modal .sa-skip-success {
    color: #27ae60;
    font-size: 0.85em;
    margin-top: 10px;
    display: none;
}

/* Shake animation */
@keyframes sa-shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-10px); }
    40%, 80% { transform: translateX(10px); }
}
</style>

<!-- BANNER PERSISTENTE -->
<div id="sa-reminder-banner">
    <span class="sa-banner-icon">📋</span>
    <span class="sa-banner-text">Hai competenze da autovalutare!</span>
    <span class="sa-banner-count">{$pending} di {$total}</span>
    <button class="sa-banner-btn" onclick="window.location.href='{$compile_url_str}'">
        Compila Ora
    </button>
</div>

<!-- LOGIN MODAL -->
<div id="sa-login-modal-overlay">
    <div class="sa-login-modal">
        <div class="sa-modal-icon">📝</div>
        <h2>Autovalutazione in Sospeso</h2>
        <div class="sa-modal-count">{$pending} competenze da valutare</div>
        <p>
            Prima di continuare, completa l'autovalutazione delle tue competenze.<br>
            Questo aiuterà il tuo coach a capire dove hai bisogno di supporto.
        </p>
        <button class="btn-primary-sa" onclick="window.location.href='{$compile_url_str}'">
            ✅ Compila Autovalutazione
        </button>
        <button class="btn-primary-sa" style="background: #95a5a6; margin-bottom: 0;" onclick="saCloseLoginModal()">
            Continua Senza Compilare
        </button>

        <div class="sa-modal-skip">
            <p>Hai un codice di bypass?</p>
            <div class="sa-skip-input-group">
                <input type="text" class="sa-skip-input" id="saGlobalSkipCode" placeholder="Codice" maxlength="4">
                <button class="btn-skip-sa" onclick="saGlobalTrySkip()">Salta</button>
            </div>
            <div class="sa-skip-error" id="saGlobalSkipError">❌ Codice non valido</div>
            <div class="sa-skip-success" id="saGlobalSkipSuccess"></div>
        </div>
    </div>
</div>

<script>
(function() {
    // Costanti skip
    const SA_SKIP_TEMP = '6807';
    const SA_SKIP_PERMANENT = 'FTM';
    const SA_AJAX_URL = '{$ajax_url_str}';
    const SA_COMPILE_URL = '{$compile_url_str}';
    const SA_HOME_URL = '{$wwwroot}';

    // Verifica skip temporaneo (sessionStorage)
    function hasTemporarySkip() {
        return sessionStorage.getItem('sa_skipped_temp') === 'true';
    }

    // Verifica se mostrare login modal (solo al primo accesso della sessione)
    function shouldShowLoginModal() {
        if (hasTemporarySkip()) return false;
        if (sessionStorage.getItem('sa_login_modal_shown') === 'true') return false;
        return true;
    }

    // Inizializzazione
    document.addEventListener('DOMContentLoaded', function() {
        // Aggiungi classe al body per lo spazio del banner
        if (!hasTemporarySkip()) {
            document.body.classList.add('sa-has-banner');
        } else {
            // Nascondi banner se skip temporaneo
            var banner = document.getElementById('sa-reminder-banner');
            if (banner) banner.style.display = 'none';
        }

        // Mostra login modal se necessario
        if (shouldShowLoginModal()) {
            setTimeout(function() {
                var modal = document.getElementById('sa-login-modal-overlay');
                if (modal) {
                    modal.classList.add('sa-show');
                    sessionStorage.setItem('sa_login_modal_shown', 'true');
                }
            }, 1000); // Delay 1 secondo per non essere troppo aggressivo
        }

        // Gestione Enter nel campo codice
        var skipInput = document.getElementById('saGlobalSkipCode');
        if (skipInput) {
            skipInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') saGlobalTrySkip();
            });
        }
    });

    // Chiudi login modal
    window.saCloseLoginModal = function() {
        var modal = document.getElementById('sa-login-modal-overlay');
        if (modal) modal.classList.remove('sa-show');
    };

    // Gestione skip codes (globale - funziona ovunque)
    window.saGlobalTrySkip = function() {
        var input = document.getElementById('saGlobalSkipCode');
        var code = input.value.toUpperCase().trim();
        var errorEl = document.getElementById('saGlobalSkipError');
        var successEl = document.getElementById('saGlobalSkipSuccess');

        errorEl.style.display = 'none';
        successEl.style.display = 'none';

        if (code === SA_SKIP_TEMP) {
            // Skip temporaneo
            sessionStorage.setItem('sa_skipped_temp', 'true');
            successEl.textContent = '⏭️ Skip temporaneo attivato';
            successEl.style.display = 'block';

            setTimeout(function() {
                // Nascondi tutto
                document.getElementById('sa-reminder-banner').style.display = 'none';
                document.body.classList.remove('sa-has-banner');
                saCloseLoginModal();
            }, 1000);

        } else if (code === SA_SKIP_PERMANENT) {
            // Skip permanente - salva nel DB
            successEl.textContent = '⏳ Salvataggio...';
            successEl.style.display = 'block';

            fetch(SA_AJAX_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'skip_permanent'})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    successEl.textContent = '✅ Skip permanente salvato - non vedrai più questi reminder';
                    setTimeout(function() {
                        document.getElementById('sa-reminder-banner').style.display = 'none';
                        document.body.classList.remove('sa-has-banner');
                        saCloseLoginModal();
                    }, 1500);
                } else {
                    errorEl.textContent = '❌ Errore: ' + (data.error || 'Impossibile salvare');
                    errorEl.style.display = 'block';
                    successEl.style.display = 'none';
                }
            })
            .catch(function() {
                errorEl.textContent = '❌ Errore di connessione';
                errorEl.style.display = 'block';
                successEl.style.display = 'none';
            });

        } else {
            // Codice errato
            errorEl.style.display = 'block';
            input.value = '';
            input.focus();

            // Shake animation
            var modal = document.querySelector('.sa-login-modal');
            if (modal) {
                modal.style.animation = 'none';
                setTimeout(function() { modal.style.animation = 'sa-shake 0.5s ease'; }, 10);
            }
        }
    };
})();
</script>
HTML;
    }

    /**
     * Genera HTML per blocco quiz
     */
    private static function get_quiz_block_html($compile_url, $pending): string {
        $compile_url_str = $compile_url->out();

        return <<<JSBLOCK
<script>
(function() {
    // Quiz blocking - disabilita il pulsante di avvio quiz
    document.addEventListener('DOMContentLoaded', function() {
        // Non bloccare se c'è skip temporaneo
        if (sessionStorage.getItem('sa_skipped_temp') === 'true') return;

        // Trova e blocca pulsanti di avvio quiz
        var attemptButtons = document.querySelectorAll(
            'button[type="submit"], ' +
            'input[type="submit"][value*="Attempt"], ' +
            'input[type="submit"][value*="tentativo"], ' +
            'input[type="submit"][value*="Inizia"], ' +
            '.quizattempt, ' +
            'a[href*="startattempt"], ' +
            'button.btn-primary'
        );

        attemptButtons.forEach(function(btn) {
            // Verifica se è un pulsante di avvio quiz
            var text = (btn.value || btn.textContent || '').toLowerCase();
            if (text.indexOf('attempt') !== -1 ||
                text.indexOf('tentativo') !== -1 ||
                text.indexOf('inizia') !== -1 ||
                text.indexOf('continua') !== -1 ||
                text.indexOf('riprendi') !== -1) {

                // Blocca il pulsante
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';

                // Aggiungi wrapper con messaggio
                var wrapper = document.createElement('div');
                wrapper.innerHTML = '<div style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); ' +
                    'color: white; padding: 20px; border-radius: 12px; margin: 15px 0; text-align: center;">' +
                    '<div style="font-size: 2em; margin-bottom: 10px;">⚠️</div>' +
                    '<strong style="font-size: 1.2em;">Quiz Temporaneamente Bloccato</strong><br><br>' +
                    'Hai <strong>{$pending} competenze</strong> da autovalutare prima di continuare con i quiz.<br><br>' +
                    '<a href="{$compile_url_str}" style="display: inline-block; background: white; color: #ee5a24; ' +
                    'padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: bold; ' +
                    'margin-top: 10px;">📋 Compila Autovalutazione</a>' +
                    '</div>';
                btn.parentNode.insertBefore(wrapper, btn);
            }
        });
    });
})();
</script>
JSBLOCK;
    }
}
