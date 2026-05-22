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

// Domains known to block server-side requests (anti-bot / Cloudflare / JS challenge).
// For these we skip the liveness check and redirect directly — false positives are worse
// than the rare case of an expired offer slipping through.
$ANTIBOT_DOMAINS = [
    // Anti-bot / Cloudflare — server-side HEAD/GET always fails.
    'randstad.ch', 'randstad.com',
    'manpower.ch', 'manpower.com',
    'adecco.ch', 'adecco.com',
    'linkedin.com',
    'indeed.com', 'indeed.ch',
    'monster.ch', 'monster.com',
    'michael-page.ch',
    'hays.ch',
    // Angular SPA — returns HTTP 200 for ALL routes (even expired),
    // so server-side liveness check is useless.
    'job-room.ch',
    'jobs.ch',
    'jobup.ch',
    'jobscout24.ch',
    'carriera.ch',
];

function url_is_antibot(string $url, array $domains): bool {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    foreach ($domains as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) {
            return true;
        }
    }
    return false;
}

if (url_is_antibot($url, $ANTIBOT_DOMAINS)) {
    // Cannot check server-side — redirect directly and show interstitial info page.
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/jobmatchagent/redirect_offer.php', ['offerid' => $offerid]));
    $PAGE->set_title('Annuncio esterno');
    $PAGE->set_heading('Annuncio esterno');
    $PAGE->set_pagelayout('standard');
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    // Sites that crash on expired listings (Randstad Next.js).
    $CRASH_DOMAINS = ['randstad.ch', 'randstad.com'];
    $is_crash_site = false;
    foreach ($CRASH_DOMAINS as $cd) {
        if ($host === $cd || str_ends_with($host, '.' . $cd)) { $is_crash_site = true; break; }
    }

    // job-room.ch: Angular SPA but has a real REST API we can query server-side.
    // Extract UUID from URL and call GET /jobadservice/api/jobAdvertisements/{uuid}
    // to verify if the specific offer is still active.
    $JOBROOM_DOMAINS = ['job-room.ch'];
    $is_jobroom = false;
    foreach ($JOBROOM_DOMAINS as $jd) {
        if ($host === $jd || str_ends_with($host, '.' . $jd)) { $is_jobroom = true; break; }
    }

    if ($is_jobroom) {
        // job-room.ch Angular SPA — deep link problem:
        // Angular redirects to /job-search when the UUID is not found or not in URL.
        //
        // Strategy: search the API using title keywords + company name filter.
        // Match by UUID (if stored) OR by title similarity (if UUID missing from URL).
        // When found: prefer the company's external application URL over the Angular
        // detail page — this bypasses Angular entirely for offers with an external ATS.
        // Update the stored URL in DB so future clicks are instant.

        // Step 1: try to extract UUID from the stored URL (several format variants).
        $uuid = '';
        if (preg_match('|/job-search/detail/([a-zA-Z0-9\-]+)|', $url, $m)) {
            $uuid = $m[1];
        }

        $is_still_active    = false;
        $api_confirmed_dead = false;
        $best_redirect_url  = null;

        // Title normalizer for fuzzy matching (no UUID in URL case).
        $norm_title = function(string $t): string {
            $t = mb_strtolower(strip_tags($t));
            $t = str_replace(['\'', '’', '–', '-', '/', '(', ')'], ' ', $t);
            return trim(preg_replace('/\s+/', ' ', $t));
        };
        $our_title_norm = $norm_title($offer->title);
        $company        = trim($offer->company ?? '');

        // Helper: call job-room.ch search API. Returns decoded array or null on error.
        $jr_search = function(array $kw, array $cantons, int $size, ?string $cname) {
            $body = json_encode([
                'workloadPercentageMin' => 0,
                'workloadPercentageMax' => 100,
                'permanent'             => null,
                'companyName'           => $cname ?: null,
                'onlineSince'           => 90,
                'displayRestricted'     => false,
                'professionCodes'       => [],
                'keywords'              => $kw,
                'communalCodes'         => [],
                'cantonCodes'           => $cantons,
            ]);
            $ch = curl_init('https://www.job-room.ch/jobadservice/api/jobAdvertisements/_search'
                . '?page=0&size=' . $size . '&sort=date_desc&_ng=aXQ=');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Accept-Language: it',
                    'Accept-Encoding: gzip, deflate',
                ],
                CURLOPT_ENCODING       => '',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || !$raw) return null;
            $d = json_decode($raw, true);
            return is_array($d) ? $d : null;
        };

        // Helper: pick best redirect URL from an API job object.
        // Prefer external company ATS (bypasses Angular). Fallback: Angular detail page.
        $best_url_from = function(array $job_obj, string $found_uuid) use ($url): string {
            $apply = $job_obj['jobContent']['applyChannel'] ?? [];
            $ext = $apply['url'] ?? ($apply['formUrl'] ?? ($job_obj['publication']['externalUrl'] ?? ''));
            if ($ext && str_starts_with($ext, 'http') && stripos($ext, 'job-room.ch') === false) {
                return $ext;
            }
            return 'https://www.job-room.ch/job-search/detail/' . $found_uuid;
        };

        if (function_exists('curl_init')) {
            $title_words = preg_split('/[\s\/\-|,]+/u', strip_tags($offer->title), -1, PREG_SPLIT_NO_EMPTY);
            $kw3 = implode(' ', array_slice($title_words, 0, 3));

            // Search strategies (ordered: most targeted first, broadest last).
            $strategies = [];
            if ($company) {
                // Best: keywords + company name filter (very targeted).
                $strategies[] = ['kw' => [$kw3], 'cantons' => [],       'size' => 50,  'cname' => $company];
                // Company-only — catches wording differences in titles.
                $strategies[] = ['kw' => [],      'cantons' => [],       'size' => 50,  'cname' => $company];
            }
            // Title keywords, all of Switzerland.
            $strategies[] = ['kw' => [$kw3], 'cantons' => [],       'size' => 100, 'cname' => null];
            // All Ticino — broadest safety net.
            $strategies[] = ['kw' => [],      'cantons' => ['TI'],   'size' => 200, 'cname' => null];

            foreach ($strategies as $s) {
                if ($is_still_active) break;

                $results = $jr_search($s['kw'], $s['cantons'], $s['size'], $s['cname']);
                if (!is_array($results) || empty($results)) continue;

                foreach ($results as $item) {
                    $job_obj  = $item['jobAdvertisement'] ?? $item;
                    $api_uuid = (string)($job_obj['id'] ?? '');
                    if (empty($api_uuid)) continue;

                    $matched = false;

                    // Primary match: UUID from stored URL.
                    if ($uuid && strtolower($api_uuid) === strtolower($uuid)) {
                        $matched = true;
                    }

                    // Secondary match: title similarity (for offers without UUID in URL).
                    if (!$matched) {
                        $descs = $job_obj['jobContent']['jobDescriptions'] ?? [];
                        $api_title_raw = '';
                        foreach ($descs as $d) {
                            if (($d['languageIsoCode'] ?? '') === 'it' && !empty($d['title'])) {
                                $api_title_raw = $d['title'];
                                break;
                            }
                        }
                        if (empty($api_title_raw) && !empty($descs[0]['title'])) {
                            $api_title_raw = $descs[0]['title'];
                        }
                        if (!empty($api_title_raw)) {
                            similar_text($our_title_norm, $norm_title($api_title_raw), $sim_pct);
                            if ($sim_pct >= 72) {
                                $matched = true;
                                if (!$uuid) {
                                    $uuid = $api_uuid; // Found UUID from title match.
                                }
                            }
                        }
                    }

                    if ($matched) {
                        $is_still_active   = true;
                        $best_redirect_url = $best_url_from($job_obj, $api_uuid);
                        // Fix stored URL in DB so future clicks skip this detection logic.
                        if ($best_redirect_url !== $url) {
                            $DB->set_field('local_jobmatch_offers', 'url', $best_redirect_url, ['id' => $offer->id]);
                        }
                        break 2; // Exit both foreach loops.
                    }
                }

                // API returned results + UUID-based search failed → mark as expired.
                if (!$is_still_active && $uuid && count($results) > 10) {
                    $api_confirmed_dead = true;
                }
            }
        }

        // Confirmed active → redirect to best URL (external ATS or Angular detail).
        if ($is_still_active && $best_redirect_url) {
            redirect(new moodle_url($best_redirect_url));
            die();
        }

        // Confirmed expired → mark in DB and discard pending results.
        if ($api_confirmed_dead) {
            $DB->set_field('local_jobmatch_offers', 'status', 'expired', ['id' => $offer->id]);
            $DB->execute(
                "UPDATE {local_jobmatch_results} SET status = 'discarded'
                  WHERE offer_id = :oid AND status IN ('pending', 'ai_done')",
                ['oid' => $offer->id]
            );
        }

        // Build keyword+company search URL for the interstitial fallback button.
        $words = preg_split('/[\s\/\-|,]+/u', strip_tags($offer->title), -1, PREG_SPLIT_NO_EMPTY);
        $kw_for_search = implode(' ', array_slice($words, 0, 4));
        $jobroom_search_url = 'https://www.job-room.ch/job-search?query-values='
            . urlencode('{"keywords":["' . str_replace('"', '', $kw_for_search) . '"]}');

        // Resolve CV for the target student (for JobAIDA prefill).
        $target_userid = $userid > 0 ? (int)$userid : (int)$USER->id;
        $cv_text = '';
        if ($target_userid > 0 && class_exists('\local_jobmatchagent\matcher')) {
            $cv_resolved = \local_jobmatchagent\matcher::resolve_cv($target_userid);
            $cv_text = $cv_resolved['text'] ?? '';
        }

        // JobAIDA URL (if plugin is installed).
        global $CFG;
        $jobaida_url = '';
        if (file_exists($CFG->dirroot . '/local/jobaida/index.php')) {
            $jobaida_url = (new moodle_url('/local/jobaida/index.php', ['userid' => $target_userid]))->out(false);
        }

        $PAGE->set_context($context);
        $PAGE->set_url(new moodle_url('/local/jobmatchagent/redirect_offer.php', ['offerid' => $offerid]));
        $PAGE->set_title('Annuncio job-room.ch');
        $PAGE->set_heading('Annuncio job-room.ch');
        $PAGE->set_pagelayout('standard');
        echo $OUTPUT->header();
        ?>
        <style>
        .jr-card { max-width:800px; margin:1.5rem auto; }
        .jr-header { background:#1565c0; color:#fff; padding:1rem 1.5rem; border-radius:8px 8px 0 0; }
        .jr-body { border:1px solid #dee2e6; border-top:none; border-radius:0 0 8px 8px; padding:1.5rem; background:#fff; }
        .jr-meta { color:#555; font-size:.88rem; margin-bottom:.75rem; }
        .jr-offertext { background:#f8f9fa; border-radius:6px; padding:1rem; font-size:.88rem;
                        max-height:400px; overflow-y:auto; white-space:pre-wrap; margin-bottom:1rem; }
        .jr-note { background:#fff8e1; border-left:4px solid #f59e0b; color:#78350f;
                   padding:.6rem 1rem; border-radius:4px; font-size:.83rem; margin-bottom:1rem; }
        </style>
        <div class="jr-card">
            <div class="jr-header">
                <h4 class="mb-0">&#128196; <?php echo s($offer->title); ?></h4>
                <?php if (!empty($offer->company)): ?>
                    <div style="font-size:.9rem;opacity:.85;margin-top:.2rem"><?php echo s($offer->company); ?></div>
                <?php endif; ?>
            </div>
            <div class="jr-body">
                <div class="jr-meta">
                    <?php if (!empty($offer->location)): ?>&#128205; <?php echo s($offer->location); ?>&nbsp;&nbsp;<?php endif; ?>
                    &#128336; Annuncio salvato il <?php echo userdate((int)($offer->timecreated ?? time()), '%d/%m/%Y'); ?>
                    &nbsp;&nbsp;&#127760; job-room.ch (arbeit.swiss)
                </div>

                <?php if ($api_confirmed_dead): ?>
                <div class="jr-note">
                    &#128683; <strong>Offerta scaduta su job-room.ch</strong> — verificato tramite API in tempo reale.
                    L&#39;annuncio è stato rimosso dal portale e segnato come scaduto nel sistema. Non apparirà più nelle liste.
                    Il testo qui sotto è salvato e puoi comunque usarlo per generare la lettera.
                </div>
                <?php else: ?>
                <div class="jr-note">
                    &#9888; <strong>Annuncio non verificabile in tempo reale</strong> — il link specifico non è disponibile.
                    Prova il link diretto o cerca con le parole chiave qui sotto.
                </div>
                <?php endif; ?>

                <?php if (!empty($offer->parsed_text)): ?>
                <div class="jr-offertext"><?php echo s(strip_tags($offer->parsed_text)); ?></div>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap mt-2">
                    <a href="<?php echo s($backUrl); ?>" class="btn btn-outline-secondary">
                        &#8592; Torna ai risultati
                    </a>
                    <a href="<?php echo s($url); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                        &#128279; Prova link diretto &#8599;
                    </a>
                    <a href="<?php echo s($jobroom_search_url); ?>" target="_blank" rel="noopener" class="btn btn-primary">
                        &#128269; Cerca su job-room.ch &#8599;
                    </a>
                    <?php if ($jobaida_url): ?>
                    <button type="button" class="btn btn-success" onclick="openJobAIDA()">
                        &#9993; Genera lettera (JobAIDA)
                    </button>
                    <?php endif; ?>
                    <?php if ($canCoach): ?>
                    <form method="post"
                          action="<?php echo (new moodle_url('/local/jobmatchagent/redirect_offer.php'))->out(false); ?>"
                          style="margin:0;"
                          onsubmit="return confirm('Eliminare definitivamente questo annuncio e tutti i suoi match?');">
                        <input type="hidden" name="offerid"       value="<?php echo (int)$offerid; ?>">
                        <input type="hidden" name="deleteofferid" value="<?php echo (int)$offerid; ?>">
                        <input type="hidden" name="userid"        value="<?php echo (int)$userid; ?>">
                        <input type="hidden" name="from"          value="<?php echo s($from); ?>">
                        <input type="hidden" name="sesskey"       value="<?php echo sesskey(); ?>">
                        <button type="submit" class="btn btn-outline-danger">
                            &#128465; Elimina annuncio
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cv_text)): ?>
                <div class="text-muted small mt-2">
                    &#10003; CV del candidato disponibile — verrà iniettato automaticamente in JobAIDA.
                </div>
                <?php elseif ($jobaida_url): ?>
                <div class="text-muted small mt-2">
                    &#9888; CV non trovato nel sistema JobMatch — dovrai incollarlo manualmente in JobAIDA.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($jobaida_url): ?>
        <script>
        function openJobAIDA() {
            var prefill = {
                jobad: <?php echo json_encode(mb_substr(strip_tags($offer->parsed_text ?? ''), 0, 8000)); ?>,
                cv:    <?php echo json_encode(mb_substr($cv_text, 0, 8000)); ?>,
                timestamp: Date.now()
            };
            localStorage.setItem('jobaida_prefill', JSON.stringify(prefill));
            window.open(<?php echo json_encode($jobaida_url); ?>, '_blank');
        }
        </script>
        <?php endif; ?>
        <?php
        echo $OUTPUT->footer();
        die();
    }

    echo $OUTPUT->header();
    ?>
    <style>
    .antibot-card { max-width:720px; margin:2rem auto; }
    .antibot-header { padding:1.1rem 1.5rem; border-radius:8px 8px 0 0; color:#fff; }
    .antibot-header.ok { background:#0066cc; }
    .antibot-header.warn { background:#dc3545; }
    .antibot-body { border:1px solid #dee2e6; border-top:none; border-radius:0 0 8px 8px; padding:1.5rem; background:#fff; }
    .antibot-details { background:#f8f9fa; border-radius:6px; padding:1rem; margin-bottom:1rem; font-size:.9rem; }
    .antibot-note { padding:.7rem 1rem; border-radius:4px; font-size:.85rem; margin-bottom:1rem; }
    .antibot-note.warn { background:#fff8e1; border-left:4px solid #f59e0b; color:#78350f; }
    .antibot-note.danger { background:#fee; border-left:4px solid #dc3545; color:#7b0000; }
    </style>
    <div class="antibot-card">
        <div class="antibot-header <?php echo $is_crash_site ? 'warn' : 'ok'; ?>">
            <h4 class="mb-1">
                <?php if ($is_crash_site): ?>
                    &#9888; Annuncio Randstad — alta probabilità di errore
                <?php else: ?>
                    &#128279; Annuncio su <?php echo s($host); ?>
                <?php endif; ?>
            </h4>
            <div style="font-size:.85rem;opacity:.9">
                <?php if ($is_crash_site): ?>
                    Randstad.ch usa protezione anti-bot. Le offerte scadute mostrano "Application error".
                    Se l'annuncio non si apre correttamente, eliminalo dal sistema.
                <?php else: ?>
                    Apri l'annuncio. Se il sito ti porta alla pagina di ricerca generica, l'offerta è scaduta — usa <strong>Elimina annuncio</strong>.
                <?php endif; ?>
            </div>
        </div>
        <div class="antibot-body">
            <div class="antibot-details">
                <strong><?php echo s($offer->title); ?></strong>
                <?php if (!empty($offer->company)): ?>
                    — <?php echo s($offer->company); ?>
                <?php endif; ?>
                <?php if (!empty($offer->location)): ?>
                    <br><span class="text-muted">&#128205; <?php echo s($offer->location); ?></span>
                <?php endif; ?>
                <?php if (!empty($offer->parsed_text)): ?>
                    <div style="margin-top:.5rem;font-size:.82rem;color:#555">
                        <?php echo nl2br(s(mb_substr(strip_tags($offer->parsed_text), 0, 300))); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?php echo s($backUrl); ?>" class="btn btn-outline-secondary">
                    &#8592; Torna ai risultati
                </a>
                <a href="<?php echo s($url); ?>" target="_blank" rel="noopener"
                   class="btn <?php echo $is_crash_site ? 'btn-warning' : 'btn-primary'; ?>">
                    &#128279; Apri su <?php echo s($host); ?> &#8599;
                </a>
                <?php if ($canCoach): ?>
                <form method="post"
                      action="<?php echo (new moodle_url('/local/jobmatchagent/redirect_offer.php'))->out(false); ?>"
                      style="margin:0;"
                      onsubmit="return confirm('Eliminare definitivamente questo annuncio e tutti i suoi match?');">
                    <input type="hidden" name="offerid"       value="<?php echo (int)$offerid; ?>">
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
    die();
}

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
                <input type="hidden" name="offerid"       value="<?php echo (int)$offerid; ?>">
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
