<?php
/**
 * FTM Job Search - Main page.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_jobsearch:use', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_jobsearch/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_ftm_jobsearch'));
$PAGE->set_heading('FTM Job Search — Cerca Lavoro in Ticino');
$PAGE->set_pagelayout('standard');

// Load students assigned to this coach.
$my_students = [];
$coach_record = $DB->get_record('local_ftm_coaches', ['userid' => $USER->id]);
if ($coach_record) {
    $my_students = $DB->get_records_sql(
        'SELECT u.id, u.firstname, u.lastname, ss.sector
           FROM {local_student_coaching} sc
           JOIN {user} u ON u.id = sc.userid
      LEFT JOIN (SELECT userid, sector FROM {local_student_sectors} WHERE is_primary = 1) ss ON ss.userid = u.id
          WHERE sc.coachid = :cid AND sc.status = :st
       ORDER BY u.lastname, u.firstname',
        ['cid' => $coach_record->id, 'st' => 'active']
    );
}

// Load cities for dropdown.
$cities = \local_ftm_jobsearch\job_manager::get_cities();

echo $OUTPUT->header();

$sesskey = sesskey();
$ajax_url = (new moodle_url('/local/ftm_jobsearch/ajax_search.php'))->out(false);

$settori = [
    'MECCANICA' => 'Meccanica',
    'AUTOMOBILE' => 'Automobile',
    'CHIMFARM' => 'Chimica/Farm.',
    'LOGISTICA' => 'Logistica',
    'ELETTRICITA' => 'Elettricita',
    'AUTOMAZIONE' => 'Automazione',
    'METALCOSTRUZIONE' => 'Metalcostruzione',
];
?>

<style>
.js-container { max-width: 900px; margin: 0 auto; }
.js-card { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.js-card-header { background: linear-gradient(135deg, #0066cc, #004d99); color: #fff; padding: 18px 24px; border-radius: 10px; margin-bottom: 20px; }
.js-card-header h2 { margin: 0 0 4px; font-size: 1.4rem; }
.js-card-header p { margin: 0; opacity: 0.85; font-size: 0.9rem; }
.js-label { font-weight: 600; display: block; margin-bottom: 6px; color: #333; font-size: 0.95rem; }
.js-input, .js-select { width: 100%; padding: 10px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.95rem; box-sizing: border-box; }
.js-input:focus, .js-select:focus { border-color: #0066cc; outline: none; box-shadow: 0 0 0 3px rgba(0,102,204,0.15); }
.js-row { display: grid; gap: 16px; margin-bottom: 16px; }
.js-row-2 { grid-template-columns: 1fr 1fr; }
.js-row-3 { grid-template-columns: 2fr 1fr 1fr; }
.js-sectors { display: flex; flex-wrap: wrap; gap: 8px; }
.js-sector-btn { padding: 8px 16px; border: 2px solid #dee2e6; border-radius: 20px; background: #fff; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: #555; transition: all 0.2s; }
.js-sector-btn:hover { border-color: #0066cc; color: #0066cc; }
.js-sector-btn.active { background: #0066cc; color: #fff; border-color: #0066cc; }
.js-btn-search { width: 100%; padding: 14px; background: #0066cc; color: #fff; border: none; border-radius: 8px; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
.js-btn-search:hover { background: #004d99; }
.js-btn-search:disabled { background: #94a3b8; cursor: not-allowed; }

/* Results */
.js-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.js-results-count { font-size: 0.9rem; color: #6b7280; }
.js-offer { display: flex; gap: 16px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 10px; background: #fff; transition: box-shadow 0.2s; }
.js-offer:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.js-offer-body { flex: 1; min-width: 0; }
.js-offer-title { font-weight: 700; font-size: 1rem; color: #1e293b; margin: 0 0 4px; }
.js-offer-title a { color: #0066cc; text-decoration: none; }
.js-offer-title a:hover { text-decoration: underline; }
.js-offer-meta { font-size: 0.82rem; color: #6b7280; display: flex; flex-wrap: wrap; gap: 12px; }
.js-offer-meta span { display: flex; align-items: center; gap: 3px; }
.js-offer-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.js-badge-fonte { background: #dbeafe; color: #1e40af; }
.js-badge-dist { background: #dcfce7; color: #166534; }
.js-badge-tipo { background: #fef3c7; color: #92400e; }
.js-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid #fff3; border-top-color: #fff; border-radius: 50%; animation: jsspin 0.6s linear infinite; }
@keyframes jsspin { to { transform: rotate(360deg); } }
.js-empty { text-align: center; padding: 40px; color: #9ca3af; }

/* Match bar */
.js-match { margin-top: 8px; }
.js-match-bar { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.js-match-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
.js-match-label { display: flex; justify-content: space-between; align-items: center; margin-top: 3px; }
.js-match-pct { font-weight: 800; font-size: 0.9rem; }
.js-match-reason { font-size: 0.78rem; color: #6b7280; flex: 1; margin-left: 10px; font-style: italic; }
@media (max-width: 640px) { .js-row-2, .js-row-3 { grid-template-columns: 1fr; } .js-sectors { justify-content: center; } }
</style>

<div class="js-container">

    <div class="js-card-header">
        <h2>Cerca Lavoro in Ticino — Vista Coach</h2>
        <p>Seleziona uno studente oppure imposta i criteri manualmente. Le offerte vengono dai portali job-room.ch e indeed.ch.</p>
    </div>

    <div class="js-card">

        <?php if (!empty($my_students)): ?>
        <!-- Selettore studente -->
        <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; margin-bottom:16px;">
            <label class="js-label" style="color:#1e40af;">Cerca offerte per uno studente</label>
            <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end;">
                <select id="js-student-select" class="js-select" onchange="loadStudent(this.value)">
                    <option value="">— Seleziona uno studente —</option>
                    <?php foreach ($my_students as $st): ?>
                    <option value="<?php echo $st->id; ?>"
                            data-sector="<?php echo s($st->sector ?? ''); ?>">
                        <?php echo s(fullname($st)); ?>
                        <?php if ($st->sector): ?>(<?php echo s($st->sector); ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="loadStudent(document.getElementById('js-student-select').value)"
                        style="padding:10px 18px; background:#0066cc; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; white-space:nowrap;">
                    Carica settore
                </button>
            </div>
            <p style="margin:6px 0 0; font-size:0.8rem; color:#6b7280;">Selezionando uno studente si pre-seleziona il suo settore primario.</p>
        </div>
        <?php endif; ?>

        <!-- Row 1: Mansione + Tipo -->
        <div class="js-row js-row-2">
            <div>
                <label class="js-label">Mansione cercata</label>
                <input type="text" id="js-mansione" class="js-input"
                       placeholder="es. Tornio CNC, Magazziniere, Elettricista...">
            </div>
            <div>
                <label class="js-label">Tipo di contratto</label>
                <select id="js-tipo" class="js-select">
                    <option value="">Tutti</option>
                    <option value="fulltime">Tempo pieno</option>
                    <option value="parttime">Part-time</option>
                    <option value="stage">Stage</option>
                    <option value="apprendistato">Apprendistato</option>
                    <option value="temporaneo">Temporaneo</option>
                </select>
            </div>
        </div>

        <!-- Row 2: Settore (multi-selezione) -->
        <div style="margin-bottom:16px;">
            <label class="js-label">Settore professionale</label>
            <div class="js-sectors" id="js-sectors">
                <?php foreach ($settori as $code => $label): ?>
                <button type="button" class="js-sector-btn" data-sector="<?php echo $code; ?>"
                        onclick="toggleSector(this)"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Row 3: Citta + Raggio + Occupazione -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
            <div>
                <label class="js-label">Citta in Ticino</label>
                <select id="js-citta" class="js-select">
                    <option value="">Tutta la regione</option>
                    <?php foreach ($cities as $c): ?>
                    <option value="<?php echo s($c->nome); ?>"><?php echo s($c->nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="js-label">Raggio: <span id="js-raggio-label">30</span> km</label>
                <input type="range" id="js-raggio" min="10" max="100" step="10" value="30"
                       style="width:100%; margin-top:8px; accent-color:#0066cc;"
                       oninput="document.getElementById('js-raggio-label').textContent=this.value">
            </div>
            <div>
                <label class="js-label">Occupazione</label>
                <select id="js-workload" class="js-select">
                    <option value="">Tutti</option>
                    <option value="100">100%</option>
                    <option value="80">80-100%</option>
                    <option value="50">50-80%</option>
                    <option value="0">Sotto 50%</option>
                </select>
            </div>
        </div>

        <!-- CV text (opzionale — per matching) -->
        <div style="margin-bottom:16px;">
            <label class="js-label">CV dello studente — testo (opzionale, per calcolare compatibilita)</label>
            <textarea id="js-cv" class="js-input" rows="5" style="resize:vertical; font-size:0.9rem;"
                      placeholder="Incolla qui il CV dello studente per ordinare i risultati per compatibilita..."></textarea>
            <div id="js-cv-status" style="display:none; margin-top:6px; font-size:0.82rem; padding:6px 10px; border-radius:4px;"></div>
        </div>

        <!-- Force refresh + Search button -->
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:12px;">
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.85rem; color:#6b7280;">
                <input type="checkbox" id="js-force" style="accent-color:#0066cc;">
                Forza aggiornamento (ignora cache — usa solo se i risultati sembrano vecchi)
            </label>
        </div>
        <button id="js-btn-search" class="js-btn-search" onclick="doSearch()">
            Cerca offerte di lavoro
        </button>
    </div>

    <!-- Results area -->
    <div id="js-results" style="display:none;">
        <div class="js-card">
            <div class="js-results-header">
                <h3 style="margin:0; font-size:1.1rem; color:#1e293b;">Risultati</h3>
                <span id="js-results-info" class="js-results-count"></span>
            </div>
            <div id="js-results-list"></div>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';

    var AJAX_URL = <?php echo json_encode($ajax_url); ?>;
    var SESSKEY = <?php echo json_encode($sesskey); ?>;
    var selectedSectors = [];

    // Multi-sector toggle.
    window.toggleSector = function(btn) {
        btn.classList.toggle('active');
        selectedSectors = [];
        document.querySelectorAll('.js-sector-btn.active').forEach(function(b) {
            selectedSectors.push(b.getAttribute('data-sector'));
        });
    };

    // Load student sector when selected from dropdown.
    window.loadStudent = function(userid) {
        if (!userid) return;
        var sel = document.getElementById('js-student-select');
        var opt = sel.options[sel.selectedIndex];
        var sector = opt ? opt.getAttribute('data-sector') : '';

        // Deactivate all sector buttons, then activate the student's sector.
        document.querySelectorAll('.js-sector-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        selectedSectors = [];
        if (sector) {
            var btn = document.querySelector('.js-sector-btn[data-sector="' + sector + '"]');
            if (btn) {
                btn.classList.add('active');
                selectedSectors = [sector];
            }
        }
    };

    window.doSearch = function() {
        var cvText = document.getElementById('js-cv').value.trim();
        var hasCv = cvText.length > 30;

        // Settore obbligatorio SOLO se non c'e il CV.
        if (selectedSectors.length === 0 && !hasCv) {
            alert('Seleziona almeno un settore, oppure inserisci il tuo CV per una ricerca automatica.');
            return;
        }

        var btn = document.getElementById('js-btn-search');
        btn.disabled = true;
        btn.innerHTML = '<span class="js-spinner"></span> Ricerca in corso... (puo richiedere 20-30 secondi)';

        var forceRefresh = document.getElementById('js-force').checked ? 1 : 0;

        // Show CV status.
        var cvStatus = document.getElementById('js-cv-status');
        if (hasCv) {
            cvStatus.style.display = 'block';
            cvStatus.style.background = '#dbeafe';
            cvStatus.style.color = '#1e40af';
            cvStatus.textContent = selectedSectors.length === 0
                ? 'Ricerca automatica dal CV — il sistema rilevera il settore adatto.'
                : 'CV caricato — verra analizzato per il matching.';
        } else {
            cvStatus.style.display = 'none';
        }

        var formData = new FormData();
        formData.append('sesskey', SESSKEY);
        formData.append('action', 'search');
        formData.append('settori', selectedSectors.join(','));
        formData.append('mansione', document.getElementById('js-mansione').value.trim());
        formData.append('tipo_lavoro', document.getElementById('js-tipo').value);
        formData.append('citta', document.getElementById('js-citta').value);
        formData.append('raggio_km', document.getElementById('js-raggio').value);
        formData.append('workload', document.getElementById('js-workload').value);
        formData.append('force', forceRefresh);
        if (hasCv) {
            formData.append('cv_text', cvText);
        }

        fetch(AJAX_URL, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            btn.disabled = false;
            btn.textContent = 'Cerca offerte di lavoro';

            if (!resp.success) {
                alert('Errore: ' + resp.message);
                return;
            }

            showResults(resp.data);
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = 'Cerca offerte di lavoro';
            alert('Errore di connessione: ' + err.message);
        });
    };

    function showResults(data) {
        var area = document.getElementById('js-results');
        var list = document.getElementById('js-results-list');
        var info = document.getElementById('js-results-info');

        area.style.display = 'block';

        var cacheNote = data.from_cache ? ' (da cache)' : ' (aggiornate ora da ' + data.scraped_sites + ' siti)';
        var matchNote = data.has_matching ? ' — ordinati per compatibilita CV' : '';
        info.textContent = data.total + ' offerte trovate' + cacheNote + matchNote;

        // Show debug info if present.
        if (data.debug) {
            var dbg = '<div style="background:#1e1e2e; color:#cdd6f4; padding:10px 14px; border-radius:6px; font-size:0.78rem; margin-bottom:12px; font-family:monospace;">';
            for (var k in data.debug) { dbg += '<div><strong style="color:#89b4fa;">' + k + ':</strong> ' + escHtml(data.debug[k]) + '</div>'; }
            dbg += '</div>';
            list.innerHTML = dbg;
        } else {
            list.innerHTML = '';
        }

        if (!data.offers || data.offers.length === 0) {
            list.innerHTML += '<div class="js-empty">Nessuna offerta trovata per questi criteri.<br>Prova a cambiare settore o ad ampliare il raggio.</div>';
            area.scrollIntoView({ behavior: 'smooth' });
            return;
        }

        // Sort by match % descending if matching was done.
        if (data.has_matching) {
            data.offers.sort(function(a, b) { return (b.match_pct || 0) - (a.match_pct || 0); });
        }

        // Store offers globally for sendToJobAIDA.
        window._jsOffers = data.offers;

        var html = '';
        data.offers.forEach(function(o, idx) {
            html += '<div class="js-offer">';
            html += '<div class="js-offer-body">';
            html += '<div class="js-offer-title"><a href="' + escHtml(o.url) + '" target="_blank" rel="noopener">' + escHtml(o.titolo) + '</a></div>';
            html += '<div class="js-offer-meta">';
            if (o.azienda) html += '<span>' + escHtml(o.azienda) + '</span>';
            if (o.citta) html += '<span>' + escHtml(o.citta) + '</span>';
            if (o.data) html += '<span>' + timeAgo(o.data) + '</span>';
            if (o.workload) html += '<span>' + escHtml(o.workload) + '</span>';
            html += '</div>';
            html += '<div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">';
            html += '<span class="js-offer-badge js-badge-fonte">' + escHtml(o.fonte) + '</span>';
            if (o.distanza !== null) html += '<span class="js-offer-badge js-badge-dist">' + o.distanza + ' km</span>';
            if (o.tipo) html += '<span class="js-offer-badge js-badge-tipo">' + escHtml(o.tipo) + '</span>';
            html += '</div>';

            // "Crea lettera" button → sends offer to JobAIDA.
            html += '<div style="margin-top:8px;">';
            html += '<button onclick="sendToJobAIDA(' + idx + ')" '
                + 'style="padding:6px 14px; background:#7c3aed; color:#fff; border:none; border-radius:5px; font-size:0.82rem; font-weight:600; cursor:pointer; transition:background 0.2s;"'
                + ' onmouseover="this.style.background=\'#6d28d9\'" onmouseout="this.style.background=\'#7c3aed\'">'
                + 'Crea lettera con JobAIDA</button>';
            html += '</div>';

            // Match bar (only if CV was provided).
            if (o.match_pct !== undefined && o.match_pct !== null) {
                var pct = o.match_pct;
                var barColor = pct >= 70 ? '#28a745' : (pct >= 40 ? '#f59e0b' : '#dc3545');
                html += '<div class="js-match">';
                html += '<div class="js-match-bar"><div class="js-match-fill" style="width:' + pct + '%; background:' + barColor + ';"></div></div>';
                html += '<div class="js-match-label">';
                html += '<span class="js-match-pct" style="color:' + barColor + ';">' + pct + '% compatibile</span>';
                if (o.match_reason) html += '<span class="js-match-reason">' + escHtml(o.match_reason) + '</span>';
                html += '</div>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';
        });

        list.innerHTML += html;
        area.scrollIntoView({ behavior: 'smooth' });
    }

    // Send selected offer to JobAIDA via localStorage.
    window.sendToJobAIDA = function(idx) {
        var o = window._jsOffers[idx];
        if (!o) return;

        // Build a structured job ad text from the offer data.
        var adText = o.titolo + '\n';
        if (o.azienda) adText += 'Azienda: ' + o.azienda + '\n';
        if (o.citta) adText += 'Luogo: ' + o.citta + '\n';
        if (o.tipo) adText += 'Tipo: ' + o.tipo + '\n';
        if (o.fonte) adText += 'Fonte: ' + o.fonte + '\n';
        if (o.url) adText += 'Link annuncio: ' + o.url + '\n';
        adText += '\n(Per una lettera migliore, apri il link sopra e incolla qui sotto il testo completo dell\'annuncio)';

        // Also pass the CV if the user had pasted one.
        var cvText = document.getElementById('js-cv').value.trim();

        // Save to localStorage for JobAIDA to pick up.
        var payload = { jobad: adText, cv: cvText, source: 'ftm_jobsearch', timestamp: Date.now() };
        localStorage.setItem('jobaida_prefill', JSON.stringify(payload));

        // Open JobAIDA in new tab.
        window.open(<?php echo json_encode((new moodle_url('/local/jobaida/index.php'))->out(false)); ?>, '_blank');
    };

    // Relative time display.
    function timeAgo(dateStr) {
        if (!dateStr) return '';
        // Parse dd/mm/yyyy or yyyy-mm-dd.
        var d;
        if (dateStr.indexOf('/') > -1) {
            var p = dateStr.split('/');
            d = new Date(p[2], p[1]-1, p[0]);
        } else {
            d = new Date(dateStr);
        }
        if (isNaN(d.getTime())) return dateStr;
        var diff = Math.floor((Date.now() - d.getTime()) / 86400000);
        if (diff < 0) return 'Oggi';
        if (diff === 0) return 'Oggi';
        if (diff === 1) return 'Ieri';
        if (diff < 7) return diff + ' giorni fa';
        if (diff < 14) return '1 settimana fa';
        if (diff < 30) return Math.floor(diff / 7) + ' settimane fa';
        if (diff < 60) return '1 mese fa';
        return Math.floor(diff / 30) + ' mesi fa';
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})();
</script>

<?php
echo $OUTPUT->footer();
