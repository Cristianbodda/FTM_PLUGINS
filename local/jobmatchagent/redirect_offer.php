<?php
/**
 * Verifica che l'URL di un annuncio sia raggiungibile prima di fare redirect.
 * Se l'URL è morta, mostra pagina di errore con i dati dell'offerta.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$canCoach   = has_capability('local/jobmatchagent:manage', $context);
$canStudent = has_capability('local/jobmatchagent:viewown', $context);
if (!$canCoach && !$canStudent) {
    throw new required_capability_exception($context, 'local/jobmatchagent:manage', 'nopermissions', '');
}

$offerid  = required_param('offerid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);   // for back button
$from     = optional_param('from', 'review', PARAM_ALPHA); // 'review' | 'wizard' | 'student'

// ---- Handle delete action (coaches only) ----------------------------
$deleteAction = optional_param('deleteofferid', 0, PARAM_INT);
if ($deleteAction && confirm_sesskey() && $canCoach) {
    // Delete results first (pending/ai_done/discarded), then the offer.
    $DB->delete_records_select('local_jobmatch_results',
        "offer_id = :oid AND status IN ('pending','ai_done','discarded')",
        ['oid' => $deleteAction]);
    $DB->delete_records('local_jobmatch_offers', ['id' => $deleteAction]);
    // Redirect back to results page.
    $backUrl = $userid
        ? (new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $userid]))->out(false)
        : (new moodle_url('/local/jobmatchagent/coach_dashboard.php'))->out(false);
    redirect(new moodle_url($backUrl), 'Annuncio eliminato.', 2);
    die();
}

$offer = $DB->get_record('local_jobmatch_offers', ['id' => $offerid], '*', MUST_EXIST);

// Build back URL once, reused everywhere.
if ($from === 'wizard' && $userid) {
    $backUrl = (new moodle_url('/local/jobmatchagent/wizard.php', ['step' => 3, 'userid' => $userid]))->out(false);
} elseif ($from === 'student') {
    $backUrl = (new moodle_url('/local/jobmatchagent/student_view.php'))->out(false);
} elseif ($userid) {
    $backUrl = (new moodle_url('/local/jobmatchagent/coach_review.php', ['userid' => $userid]))->out(false);
} else {
    $backUrl = (new moodle_url('/local/jobmatchagent/coach_dashboard.php'))->out(false);
}

if (empty($offer->url)) {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/jobmatchagent/redirect_offer.php', ['offerid' => $offerid]));
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Nessuna URL disponibile per questa offerta.', 'error');
    echo html_writer::link($backUrl, '← Torna ai risultati', ['class' => 'btn btn-secondary mt-2']);
    echo $OUTPUT->footer();
    die();
}

$url = $offer->url;

// --- Verify URL via curl (HEAD, fallback GET with small read) ---
function check_url_alive($url) {
    if (!function_exists('curl_init')) {
        return ['alive' => true, 'code' => 0, 'error' => ''];
    }

    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    // First try HEAD.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $cerror = curl_error($ch);
    curl_close($ch);

    // Some sites (like randstad.ch) return 405 for HEAD — try GET if we get 4xx from HEAD.
    if ($code === 0 || $code >= 400) {
        $ch2 = curl_init($url);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RANGE          => '0-4095', // Only first 4KB.
        ]);
        $body = curl_exec($ch2);
        $code2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $final_url = curl_getinfo($ch2, CURLINFO_EFFECTIVE_URL);
        $cerror = curl_error($ch2);
        curl_close($ch2);

        // Use GET result only if it's better than HEAD.
        if ($code2 > 0) {
            $code = $code2;
        }

        // Check if the body itself signals a Next.js / framework crash.
        if ($code >= 200 && $code < 400 && !empty($body)) {
            $body_lower = strtolower(substr($body, 0, 2000));
            if (strpos($body_lower, 'application error') !== false
                || strpos($body_lower, 'client-side exception') !== false
                || strpos($body_lower, '__next_error') !== false) {
                return ['alive' => false, 'code' => $code, 'error' => 'La pagina carica ma mostra un errore applicativo (annuncio probabilmente scaduto).', 'final_url' => $final_url];
            }
        }
    }

    $alive = ($code >= 200 && $code < 400);
    $error = '';
    if (!$alive) {
        if ($cerror) {
            $error = 'Errore connessione: ' . $cerror;
        } else if ($code === 0) {
            $error = 'Nessuna risposta dal server.';
        } else if ($code === 404) {
            $error = 'Annuncio non trovato (404) — è stato probabilmente rimosso.';
        } else if ($code >= 500) {
            $error = 'Errore server (' . $code . ') — il sito ha un problema.';
        } else {
            $error = 'HTTP ' . $code . ' — URL non raggiungibile.';
        }
    }

    return ['alive' => $alive, 'code' => $code, 'error' => $error, 'final_url' => $final_url ?? $url];
}

$check = check_url_alive($url);

if ($check['alive']) {
    // Mark the offer as verified (set timecreated won't change, but we can do redirect).
    redirect($url);
    die();
}

// --- URL is dead: show error page with offer details ---
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/redirect_offer.php', ['offerid' => $offerid]));
$PAGE->set_title('Annuncio non disponibile');
$PAGE->set_heading('Annuncio non disponibile');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>
<style>
.dead-offer-card { max-width: 720px; margin: 2rem auto; }
.dead-offer-header { background: #dc3545; color: #fff; padding: 1.25rem 1.5rem; border-radius: 8px 8px 0 0; }
.dead-offer-body { border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px; padding: 1.5rem; background: #fff; }
.dead-offer-details { background: #f8f9fa; border-radius: 6px; padding: 1rem; margin-bottom: 1rem; font-size: 0.9rem; }
</style>
<div class="dead-offer-card">
    <div class="dead-offer-header">
        <h4 class="mb-1">&#128683; Annuncio non più disponibile</h4>
        <div style="font-size:0.9rem;opacity:.85"><?php echo s($check['error']); ?></div>
    </div>
    <div class="dead-offer-body">
        <div class="dead-offer-details">
            <strong><?php echo s($offer->title); ?></strong>
            <?php if (!empty($offer->company)): ?>
                — <?php echo s($offer->company); ?>
            <?php endif; ?>
            <?php if (!empty($offer->location)): ?>
                <br><span class="text-muted">&#128205; <?php echo s($offer->location); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($offer->parsed_text)): ?>
        <p class="text-muted" style="font-size:0.88rem">
            <strong>Testo originale dell'annuncio (salvato al momento dello scraping):</strong><br>
            <?php echo nl2br(s(substr(strip_tags($offer->parsed_text), 0, 600))); ?>
            <?php if (strlen($offer->parsed_text) > 600): ?><em>...</em><?php endif; ?>
        </p>
        <?php endif; ?>

        <p class="text-muted small">
            URL tentata: <code><?php echo s(shorten_text($url, 80)); ?></code><br>
            Risposta server: HTTP <?php echo (int)$check['code']; ?>
        </p>

        <div class="d-flex gap-2 mt-3 flex-wrap">
            <a href="<?php echo s($backUrl); ?>" class="btn btn-outline-secondary">
                &#8592; Torna ai risultati
            </a>
            <a href="<?php echo s($url); ?>" target="_blank" rel="noopener"
               class="btn btn-outline-secondary"
               onclick="return confirm('L\'URL potrebbe non funzionare. Aprire comunque?')">
               Apri comunque &#8599;
            </a>
            <?php if ($canCoach): ?>
            <form method="post"
                  action="<?php echo (new moodle_url('/local/jobmatchagent/redirect_offer.php'))->out(false); ?>"
                  style="margin:0;"
                  onsubmit="return confirm('Eliminare definitivamente questo annuncio e tutti i suoi match? Questa azione non può essere annullata.');">
                <input type="hidden" name="deleteofferid" value="<?php echo (int)$offerid; ?>">
                <input type="hidden" name="userid"        value="<?php echo (int)$userid; ?>">
                <input type="hidden" name="from"          value="<?php echo s($from); ?>">
                <input type="hidden" name="sesskey"       value="<?php echo sesskey(); ?>">
                <button type="submit" class="btn btn-danger">
                    &#128465; Elimina annuncio
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
