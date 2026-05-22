<?php
/**
 * Gestione database aziende ticinesi.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/company_manager.php');

require_login();

$context = context_system::instance();
require_capability('local/jobmatchagent:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobmatchagent/companies.php'));
$PAGE->set_title('Database Aziende Ticino');
$PAGE->set_heading('Database Aziende Ticino');
$PAGE->set_pagelayout('admin');

$tab         = optional_param('tab', 'all', PARAM_ALPHANUMEXT);
$page        = optional_param('p', 0, PARAM_INT);
$settore_f   = optional_param('settore', '', PARAM_ALPHA);
$status_f    = optional_param('status', '', PARAM_ALPHANUMEXT);
$search_f    = optional_param('search', '', PARAM_TEXT);

$per_page = 50;

// Check that the DB upgrade has run.
if (!$DB->get_manager()->table_exists('local_jobmatch_ticino_companies')) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        'Tabella DB mancante. Vai su <a href="' .
        (new moodle_url('/admin/index.php'))->out(false) .
        '">Amministrazione → Notifiche</a> per eseguire l\'upgrade del database.',
        'error'
    );
    echo $OUTPUT->footer();
    die();
}

// Stats per badge.
$stats_all = \local_jobmatchagent\company_manager::get_stats_by_sector();
$total_all = \local_jobmatchagent\company_manager::count_companies();

$settori = \local_jobmatchagent\company_manager::SETTORI_FTM;

// Colori badge settore.
$settore_colors = [
    'MECCANICA'        => '#0066cc',
    'ELETTRICITA'      => '#EAB308',
    'CHIMFARM'         => '#28a745',
    'AUTOMAZIONE'      => '#fd7e14',
    'LOGISTICA'        => '#6f42c1',
    'METALCOSTRUZIONE' => '#6c757d',
    'AUTOMOBILE'       => '#dc3545',
    'ALTRO'            => '#adb5bd',
];

echo $OUTPUT->header();
?>
<style>
:root {
    --primary: #0066cc;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #EAB308;
    --secondary: #6c757d;
}
.ftm-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.ftm-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.ftm-tab {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    border-radius: 4px 4px 0 0;
    text-decoration: none;
    transition: all 0.15s;
}
.ftm-tab:hover { color: #0066cc; background: #f0f6ff; text-decoration: none; }
.ftm-tab.active { color: #0066cc; border-bottom-color: #0066cc; background: #f0f6ff; text-decoration: none; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.badge-settore {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-status-active   { background: #28a745; color: #fff; }
.badge-status-unverified { background: #fd7e14; color: #fff; }
.badge-status-inactive { background: #6c757d; color: #fff; }
.stats-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.stats-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
}
.filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.filter-bar select, .filter-bar input { border: 1px solid #dee2e6; border-radius: 6px; padding: 6px 10px; font-size: 14px; }
.filter-bar input { min-width: 220px; }
.companies-table { width: 100%; border-collapse: collapse; }
.companies-table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; padding: 10px 12px; font-size: 13px; text-align: left; font-weight: 600; }
.companies-table td { padding: 9px 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
.companies-table tr:hover td { background: #f8f9fa; }
.btn-sm-action { padding: 4px 10px; font-size: 12px; border-radius: 5px; border: 1px solid; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-edit   { border-color: #0066cc; color: #0066cc; background: transparent; }
.btn-edit:hover { background: #0066cc; color: #fff; text-decoration: none; }
.btn-activate { border-color: #28a745; color: #28a745; background: transparent; }
.btn-activate:hover { background: #28a745; color: #fff; }
.btn-deactivate { border-color: #6c757d; color: #6c757d; background: transparent; }
.btn-deactivate:hover { background: #6c757d; color: #fff; }
.pager { display: flex; gap: 6px; align-items: center; margin-top: 16px; flex-wrap: wrap; }
.pager a, .pager span { padding: 6px 12px; border: 1px solid #dee2e6; border-radius: 5px; font-size: 13px; text-decoration: none; color: #333; }
.pager a:hover { background: #f0f6ff; }
.pager .current { background: #0066cc; color: #fff; border-color: #0066cc; }
/* Classify tab */
.classify-select { border: 1px solid #dee2e6; border-radius: 5px; padding: 4px 8px; font-size: 12px; }
/* Discover tab */
.discover-input-row { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
.discover-input-row input { flex: 1; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px 14px; font-size: 14px; }
.result-panel { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; }
.result-field { margin-bottom: 12px; }
.result-field label { display: block; font-size: 12px; font-weight: 600; color: #6c757d; margin-bottom: 4px; }
.result-field input, .result-field select, .result-field textarea {
    width: 100%; border: 1px solid #dee2e6; border-radius: 5px;
    padding: 7px 10px; font-size: 13px;
}
.spinner { display: none; align-items: center; gap: 10px; color: #0066cc; font-size: 14px; }
.spinner.active { display: flex; }
.spinner-border { width: 20px; height: 20px; border: 2px solid #cce0ff; border-top-color: #0066cc; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ai-question { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
.ai-question label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
/* Import tab */
.drop-zone {
    border: 2px dashed #adb5bd; border-radius: 8px; padding: 40px;
    text-align: center; cursor: pointer; color: #6c757d; transition: all 0.2s;
}
.drop-zone.drag-over { border-color: #0066cc; background: #f0f6ff; color: #0066cc; }
.progress-bar-container { background: #dee2e6; border-radius: 6px; height: 10px; overflow: hidden; margin: 10px 0; }
.progress-bar-fill { height: 100%; background: #0066cc; width: 0%; transition: width 0.3s; }
/* Modal */
.ftm-modal-backdrop {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;
}
.ftm-modal-backdrop.open { display: flex; }
.ftm-modal {
    background: #fff; border-radius: 12px; width: 600px; max-width: 95vw;
    max-height: 90vh; overflow-y: auto; padding: 28px; position: relative;
}
.ftm-modal h5 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
.ftm-modal .close-btn { position: absolute; top: 14px; right: 18px; background: none; border: none; font-size: 20px; cursor: pointer; color: #6c757d; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; border: 1px solid #dee2e6; border-radius: 5px;
    padding: 8px 10px; font-size: 13px;
}
.form-group textarea { min-height: 70px; resize: vertical; }
.btn-primary { background: #0066cc; color: #fff; border: none; border-radius: 6px; padding: 9px 20px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-primary:hover { background: #0052a3; }
.btn-secondary { background: #6c757d; color: #fff; border: none; border-radius: 6px; padding: 9px 20px; cursor: pointer; font-size: 14px; }
.btn-danger { background: #dc3545; color: #fff; border: none; border-radius: 6px; padding: 9px 20px; cursor: pointer; font-size: 14px; }
.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-info    { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
</style>

<div class="ftm-card">
    <h4 style="margin:0 0 16px;font-size:22px;font-weight:700;">Database Aziende Ticino</h4>

    <!-- Stats badges -->
    <div class="stats-badges">
        <span class="stats-badge" style="background:#343a40;">
            Totale: <?php echo $total_all; ?>
        </span>
        <?php foreach ($settore_colors as $s => $col): ?>
            <?php $cnt = $stats_all[$s] ?? 0; if ($cnt === 0) continue; ?>
            <span class="stats-badge" style="background:<?php echo $col; ?>">
                <?php echo $s; ?>: <?php echo $cnt; ?>
            </span>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="ftm-tabs">
        <a href="#" class="ftm-tab <?php echo $tab === 'all' ? 'active' : ''; ?>" data-tab="all">Tutte</a>
        <a href="#" class="ftm-tab <?php echo $tab === 'classify' ? 'active' : ''; ?>" data-tab="classify">Da Classificare</a>
        <a href="#" class="ftm-tab <?php echo $tab === 'discover' ? 'active' : ''; ?>" data-tab="discover">Scopri Azienda</a>
        <a href="#" class="ftm-tab <?php echo $tab === 'import' ? 'active' : ''; ?>" data-tab="import">Importa CSV</a>
    </div>

    <!-- TAB: TUTTE -->
    <div class="tab-pane <?php echo $tab === 'all' ? 'active' : ''; ?>" id="tab-all">
        <div class="filter-bar">
            <select id="filter-settore">
                <option value="">Tutti i settori</option>
                <?php foreach ($settori as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $settore_f === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filter-status">
                <option value="">Tutti gli status</option>
                <option value="active" <?php echo $status_f === 'active' ? 'selected' : ''; ?>>Attive</option>
                <option value="unverified" <?php echo $status_f === 'unverified' ? 'selected' : ''; ?>>Non verificate</option>
                <option value="inactive" <?php echo $status_f === 'inactive' ? 'selected' : ''; ?>>Inattive</option>
            </select>
            <input type="text" id="filter-search" placeholder="Cerca nome, localita, referente..."
                   value="<?php echo s($search_f); ?>" style="min-width:240px;">
            <button class="btn-primary" onclick="applyFilters()">Filtra</button>
            <button class="btn-secondary" onclick="resetFilters()">Reset</button>
            <span id="count-label" style="color:#6c757d;font-size:13px;margin-left:8px;"></span>
        </div>

        <div id="table-container">
            <?php
            $filters_all = [];
            if ($settore_f) $filters_all['settore_ftm'] = $settore_f;
            if ($status_f)  $filters_all['status']      = $status_f;
            if ($search_f)  $filters_all['search']      = $search_f;

            $total_filtered = \local_jobmatchagent\company_manager::count_companies($filters_all);
            $companies      = \local_jobmatchagent\company_manager::get_companies($filters_all, $per_page, $page * $per_page);
            echo render_company_table($companies, $settore_colors);
            echo render_pager($total_filtered, $per_page, $page, $settore_f, $status_f, $search_f);
            ?>
        </div>
    </div>

    <!-- TAB: DA CLASSIFICARE -->
    <div class="tab-pane <?php echo $tab === 'classify' ? 'active' : ''; ?>" id="tab-classify">
        <?php
        $unclassified = \local_jobmatchagent\company_manager::get_companies(
            ['settore_ftm' => 'ALTRO'],
            200, 0
        );
        $unverified = \local_jobmatchagent\company_manager::get_companies(
            ['status' => 'unverified'],
            200, 0
        );
        // Merge + dedup by id.
        $classify_map = [];
        foreach (array_merge($unclassified, $unverified) as $c) {
            $classify_map[$c->id] = $c;
        }
        $classify_list = array_values($classify_map);
        ?>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
            <span style="font-size:14px;color:#6c757d;">
                <?php echo count($classify_list); ?> aziende da classificare
            </span>
            <button class="btn-primary" id="btn-classify-batch" onclick="classifyBatch()">
                Classifica con AI (batch)
            </button>
            <button class="btn-secondary" id="btn-save-all" onclick="saveAllClassifications()" style="display:none;">
                Salva tutte
            </button>
            <div id="classify-progress" style="display:none;flex:1;min-width:200px;">
                <div class="progress-bar-container"><div class="progress-bar-fill" id="progress-fill"></div></div>
                <span id="progress-label" style="font-size:12px;color:#6c757d;"></span>
            </div>
        </div>
        <div id="classify-alert"></div>

        <?php if (empty($classify_list)): ?>
            <div class="alert alert-success">Tutte le aziende sono classificate.</div>
        <?php else: ?>
        <table class="companies-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Settore attuale</th>
                    <th>Localita</th>
                    <th>Status</th>
                    <th>Classifica</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="classify-tbody">
                <?php foreach ($classify_list as $c): ?>
                <tr id="classify-row-<?php echo $c->id; ?>">
                    <td><strong><?php echo s($c->nome); ?></strong>
                        <?php if ($c->website): ?>
                            <a href="<?php echo s($c->website); ?>" target="_blank" style="font-size:11px;color:#6c757d;"> [sito]</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-settore" style="background:<?php echo $settore_colors[$c->settore_ftm] ?? '#adb5bd'; ?>">
                            <?php echo s($c->settore_ftm); ?>
                        </span>
                    </td>
                    <td><?php echo s($c->localita ?? '—'); ?></td>
                    <td>
                        <span class="badge-status-<?php echo s($c->status); ?>">
                            <?php echo status_label($c->status); ?>
                        </span>
                    </td>
                    <td>
                        <select class="classify-select" id="settore-sel-<?php echo $c->id; ?>">
                            <?php foreach ($settori as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $c->settore_ftm === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button class="btn-sm-action btn-edit"
                                onclick="saveClassify(<?php echo $c->id; ?>)">Salva</button>
                        <button class="btn-sm-action btn-edit"
                                onclick="classifyOne(<?php echo $c->id; ?>, '<?php echo s(addslashes($c->nome)); ?>', '<?php echo s(addslashes($c->settore_raw ?? '')); ?>')"
                                title="Classifica con AI">AI</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- TAB: SCOPRI AZIENDA -->
    <div class="tab-pane <?php echo $tab === 'discover' ? 'active' : ''; ?>" id="tab-discover">
        <div class="discover-input-row">
            <input type="url" id="discover-url" placeholder="https://www.azienda.ch — Incolla URL sito web aziendale" />
            <button class="btn-primary" onclick="discoverCompany()">Analizza</button>
        </div>
        <div class="spinner" id="discover-spinner">
            <div class="spinner-border"></div>
            <span>Analisi sito web in corso... (richiede 10-20 secondi)</span>
        </div>
        <div id="discover-alert"></div>
        <div id="discover-result" style="display:none;">
            <div class="result-panel">
                <h6 style="font-size:15px;font-weight:700;margin-bottom:16px;">Cosa ho capito</h6>
                <div class="form-row">
                    <div class="result-field">
                        <label>Nome azienda</label>
                        <input type="text" id="res-nome" />
                    </div>
                    <div class="result-field">
                        <label>Settore FTM</label>
                        <select id="res-settore">
                            <?php foreach ($settori as $s): ?>
                                <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="result-field">
                        <label>Localita</label>
                        <input type="text" id="res-localita" />
                    </div>
                    <div class="result-field">
                        <label>CAP</label>
                        <input type="text" id="res-cap" />
                    </div>
                    <div class="result-field">
                        <label>Indirizzo</label>
                        <input type="text" id="res-indirizzo" />
                    </div>
                    <div class="result-field">
                        <label>Dimensione (S/M/L/unknown)</label>
                        <select id="res-dimensione">
                            <option value="unknown">Sconosciuta</option>
                            <option value="S">S — Piccola</option>
                            <option value="M">M — Media</option>
                            <option value="L">L — Grande</option>
                        </select>
                    </div>
                    <div class="result-field">
                        <label>Website</label>
                        <input type="url" id="res-website" />
                    </div>
                    <div class="result-field">
                        <label>Email contatto</label>
                        <input type="email" id="res-email" />
                    </div>
                    <div class="result-field">
                        <label>Referente HR</label>
                        <input type="text" id="res-referente" />
                    </div>
                </div>
                <div class="result-field" style="margin-top:4px;">
                    <label>Attivita rilevata (settore raw)</label>
                    <textarea id="res-settore-raw" rows="2"></textarea>
                </div>
                <div class="result-field">
                    <label>Note interne</label>
                    <textarea id="res-note" rows="2"></textarea>
                </div>

                <div id="discover-questions" style="display:none;margin-top:16px;">
                    <h6 style="font-size:14px;font-weight:700;margin-bottom:10px;">Domande AI</h6>
                    <div id="questions-container"></div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
                    <button class="btn-primary" onclick="addDiscoveredCompany()">Aggiungi al Database</button>
                    <button class="btn-secondary" onclick="resetDiscover()">Scarta</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: IMPORTA CSV -->
    <div class="tab-pane <?php echo $tab === 'import' ? 'active' : ''; ?>" id="tab-import">
        <div class="alert alert-info" style="margin-bottom:20px;">
            <strong>Formato CSV accettato (separatore punto e virgola):</strong><br>
            <code>n;anno;nome;settore_ftm;confidence;indirizzo;localita</code><br>
            Questo e' il formato di output di <code>classify_companies.js</code>.
            Il sistema gestisce BOM UTF-8 e righe header automaticamente.
            Dedup per nome (case-insensitive) — le aziende gia presenti vengono saltate.
        </div>
        <div class="drop-zone" id="drop-zone" onclick="document.getElementById('csv-file-input').click()">
            <div style="font-size:40px;margin-bottom:10px;">CSV</div>
            <strong>Clicca o trascina qui il file CSV</strong>
            <div style="font-size:13px;margin-top:6px;color:#adb5bd;" id="drop-filename">Nessun file selezionato</div>
        </div>
        <input type="file" id="csv-file-input" accept=".csv,.txt" style="display:none;" onchange="handleFileSelect(this)">
        <div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button class="btn-primary" id="btn-import" onclick="importCsv()" disabled>Importa</button>
            <span id="import-status" style="font-size:13px;color:#6c757d;"></span>
        </div>
        <div id="import-result" style="display:none;margin-top:16px;"></div>
    </div>
</div>

<!-- MODAL EDIT AZIENDA -->
<div class="ftm-modal-backdrop" id="edit-modal">
    <div class="ftm-modal">
        <h5>Modifica Azienda</h5>
        <button class="close-btn" onclick="closeEditModal()">&times;</button>
        <form id="edit-form">
            <input type="hidden" id="edit-id" name="id">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" id="edit-nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label>Settore FTM</label>
                    <select id="edit-settore" name="settore_ftm">
                        <?php foreach ($settori as $s): ?>
                            <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Localita</label>
                    <input type="text" id="edit-localita" name="localita">
                </div>
                <div class="form-group">
                    <label>CAP</label>
                    <input type="text" id="edit-cap" name="cap">
                </div>
                <div class="form-group">
                    <label>Indirizzo</label>
                    <input type="text" id="edit-indirizzo" name="indirizzo">
                </div>
                <div class="form-group">
                    <label>Dimensione</label>
                    <select id="edit-dimensione" name="dimensione">
                        <option value="unknown">Sconosciuta</option>
                        <option value="S">S — Piccola (1-49)</option>
                        <option value="M">M — Media (50-249)</option>
                        <option value="L">L — Grande (250+)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Website</label>
                    <input type="url" id="edit-website" name="website">
                </div>
                <div class="form-group">
                    <label>Email contatto</label>
                    <input type="email" id="edit-email" name="email">
                </div>
                <div class="form-group">
                    <label>Referente</label>
                    <input type="text" id="edit-referente" name="referente">
                </div>
                <div class="form-group">
                    <label>Anno primo contatto</label>
                    <input type="number" id="edit-anno" name="anno_primo_contatto" min="2000" max="2099">
                </div>
            </div>
            <div class="form-group">
                <label>Note interne</label>
                <textarea id="edit-note" name="note_interne" rows="3"></textarea>
            </div>
            <div id="edit-alert"></div>
            <div style="display:flex;gap:10px;margin-top:6px;">
                <button type="button" class="btn-primary" onclick="submitEdit()">Salva</button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Annulla</button>
            </div>
        </form>
    </div>
</div>

<script>
var SESSKEY = '<?php echo sesskey(); ?>';
var AJAX_CRUD = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_company_crud.php'))->out(false); ?>';
var AJAX_CLASSIFY = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_classify_sectors.php'))->out(false); ?>';
var AJAX_DISCOVER = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_discover_company.php'))->out(false); ?>';
var AJAX_IMPORT = '<?php echo (new moodle_url('/local/jobmatchagent/ajax_import_companies.php'))->out(false); ?>';
var CURRENT_URL = '<?php echo (new moodle_url('/local/jobmatchagent/companies.php'))->out(false); ?>';

// --- Tab switching ---
var savedTab = localStorage.getItem('companies_tab') || 'all';
document.querySelectorAll('.ftm-tab').forEach(function(t) {
    t.addEventListener('click', function(e) {
        e.preventDefault();
        var tabId = this.dataset.tab;
        localStorage.setItem('companies_tab', tabId);
        document.querySelectorAll('.ftm-tab').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function(x) { x.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});
// Activate saved/PHP tab on load.
(function() {
    var phpTab = '<?php echo $tab; ?>';
    var activeTab = phpTab !== 'all' ? phpTab : savedTab;
    document.querySelectorAll('.ftm-tab').forEach(function(x) { x.classList.remove('active'); });
    document.querySelectorAll('.tab-pane').forEach(function(x) { x.classList.remove('active'); });
    var tabEl = document.querySelector('.ftm-tab[data-tab="' + activeTab + '"]');
    var paneEl = document.getElementById('tab-' + activeTab);
    if (tabEl) tabEl.classList.add('active');
    if (paneEl) paneEl.classList.add('active');
})();

// --- Filters ---
function applyFilters() {
    var settore = document.getElementById('filter-settore').value;
    var status  = document.getElementById('filter-status').value;
    var search  = document.getElementById('filter-search').value;
    var url = CURRENT_URL + '?tab=all';
    if (settore) url += '&settore=' + encodeURIComponent(settore);
    if (status)  url += '&status='  + encodeURIComponent(status);
    if (search)  url += '&search='  + encodeURIComponent(search);
    window.location.href = url;
}
function resetFilters() {
    window.location.href = CURRENT_URL + '?tab=all';
}
document.getElementById('filter-search').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') applyFilters();
});

// --- Edit modal ---
function openEditModal(id) {
    fetch(AJAX_CRUD + '?action=get&id=' + id + '&sesskey=' + SESSKEY)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) { alert('Errore: ' + d.message); return; }
            var c = d.data;
            document.getElementById('edit-id').value         = c.id;
            document.getElementById('edit-nome').value       = c.nome || '';
            document.getElementById('edit-settore').value    = c.settore_ftm || 'ALTRO';
            document.getElementById('edit-localita').value   = c.localita || '';
            document.getElementById('edit-cap').value        = c.cap || '';
            document.getElementById('edit-indirizzo').value  = c.indirizzo || '';
            document.getElementById('edit-dimensione').value = c.dimensione || 'unknown';
            document.getElementById('edit-website').value    = c.website || '';
            document.getElementById('edit-email').value      = c.email || '';
            document.getElementById('edit-referente').value  = c.referente || '';
            document.getElementById('edit-anno').value       = c.anno_primo_contatto || '';
            document.getElementById('edit-note').value       = c.note_interne || '';
            document.getElementById('edit-alert').innerHTML  = '';
            document.getElementById('edit-modal').classList.add('open');
        });
}
function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('open');
}
function submitEdit() {
    var data = {
        action: 'save',
        sesskey: SESSKEY,
        id:                  document.getElementById('edit-id').value,
        nome:                document.getElementById('edit-nome').value,
        settore_ftm:         document.getElementById('edit-settore').value,
        localita:            document.getElementById('edit-localita').value,
        cap:                 document.getElementById('edit-cap').value,
        indirizzo:           document.getElementById('edit-indirizzo').value,
        dimensione:          document.getElementById('edit-dimensione').value,
        website:             document.getElementById('edit-website').value,
        email:               document.getElementById('edit-email').value,
        referente:           document.getElementById('edit-referente').value,
        anno_primo_contatto: document.getElementById('edit-anno').value,
        note_interne:        document.getElementById('edit-note').value,
    };
    postAjax(AJAX_CRUD, data, function(r) {
        if (r.success) {
            closeEditModal();
            window.location.reload();
        } else {
            document.getElementById('edit-alert').innerHTML =
                '<div class="alert alert-danger">' + r.message + '</div>';
        }
    });
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// --- Toggle status ---
function toggleStatus(id, current) {
    var newStatus = current === 'active' ? 'inactive' : 'active';
    if (!confirm('Cambiare lo status a "' + newStatus + '"?')) return;
    postAjax(AJAX_CRUD, { action: 'set_status', sesskey: SESSKEY, id: id, status: newStatus }, function(r) {
        if (r.success) { window.location.reload(); }
        else { alert('Errore: ' + r.message); }
    });
}

// --- Classify tab ---
function saveClassify(id) {
    var settore = document.getElementById('settore-sel-' + id).value;
    postAjax(AJAX_CRUD, { action: 'save', sesskey: SESSKEY, id: id, settore_ftm: settore, status: 'active' }, function(r) {
        if (r.success) {
            var row = document.getElementById('classify-row-' + id);
            if (row) row.style.opacity = '0.4';
        } else {
            alert('Errore: ' + r.message);
        }
    });
}

function classifyOne(id, nome, settoreRaw) {
    var btn = event.target;
    btn.textContent = '...';
    btn.disabled = true;
    postAjax(AJAX_CLASSIFY, { action: 'classify_one', sesskey: SESSKEY, id: id, nome: nome, settore_raw: settoreRaw }, function(r) {
        btn.disabled = false;
        btn.textContent = 'AI';
        if (r.success && r.data && r.data.settore_ftm) {
            document.getElementById('settore-sel-' + id).value = r.data.settore_ftm;
        } else {
            alert('AI non ha potuto classificare: ' + (r.message || 'risposta vuota'));
        }
    });
}

function classifyBatch() {
    var rows = document.querySelectorAll('#classify-tbody tr');
    if (rows.length === 0) return;
    var ids = [];
    rows.forEach(function(r) { ids.push(parseInt(r.id.replace('classify-row-', ''))); });

    document.getElementById('classify-progress').style.display = 'flex';
    document.getElementById('btn-classify-batch').disabled = true;
    document.getElementById('classify-alert').innerHTML = '';

    var done = 0;
    var batchSize = 20;
    var total = ids.length;

    function processBatch(offset) {
        var batch = ids.slice(offset, offset + batchSize);
        if (batch.length === 0) {
            document.getElementById('progress-fill').style.width = '100%';
            document.getElementById('progress-label').textContent = 'Classificazione completata!';
            document.getElementById('btn-classify-batch').disabled = false;
            document.getElementById('btn-save-all').style.display = 'inline-block';
            document.getElementById('classify-alert').innerHTML =
                '<div class="alert alert-success">Classificazione AI completata. Clicca <strong>Salva tutte</strong> per salvare in blocco.</div>';
            return;
        }
        postAjax(AJAX_CLASSIFY, { action: 'classify_batch', sesskey: SESSKEY, ids: JSON.stringify(batch) }, function(r) {
            done += batch.length;
            var pct = Math.round(done / total * 100);
            document.getElementById('progress-fill').style.width = pct + '%';
            document.getElementById('progress-label').textContent = done + '/' + total + ' classificate...';

            if (r.success && r.data && r.data.results) {
                r.data.results.forEach(function(res) {
                    var sel = document.getElementById('settore-sel-' + res.id);
                    if (sel && res.settore_ftm) sel.value = res.settore_ftm;
                });
            }
            processBatch(offset + batchSize);
        });
    }
    processBatch(0);
}

// --- Save all classifications ---
function saveAllClassifications() {
    var rows = document.querySelectorAll('#classify-tbody tr');
    if (rows.length === 0) return;

    var btn = document.getElementById('btn-save-all');
    btn.disabled = true;
    btn.textContent = 'Salvataggio...';
    document.getElementById('classify-alert').innerHTML = '';

    var total = rows.length;
    var done = 0;
    var errors = 0;

    // Build queue: only rows not already faded (opacity < 1 means already saved).
    var queue = [];
    rows.forEach(function(row) {
        var id = parseInt(row.id.replace('classify-row-', ''));
        var sel = document.getElementById('settore-sel-' + id);
        if (sel && parseFloat(row.style.opacity || 1) > 0.5) {
            queue.push({ id: id, settore: sel.value });
        }
    });

    if (queue.length === 0) {
        btn.disabled = false;
        btn.textContent = 'Salva tutte';
        document.getElementById('classify-alert').innerHTML =
            '<div class="alert alert-info">Nessuna riga da salvare.</div>';
        return;
    }

    var totalQ = queue.length;
    document.getElementById('classify-progress').style.display = 'flex';

    function saveNext(idx) {
        if (idx >= totalQ) {
            document.getElementById('progress-fill').style.width = '100%';
            document.getElementById('progress-label').textContent = 'Salvate ' + (totalQ - errors) + '/' + totalQ;
            btn.disabled = false;
            btn.textContent = 'Salva tutte';
            document.getElementById('classify-alert').innerHTML =
                '<div class="alert alert-success">Salvate ' + (totalQ - errors) + ' aziende' +
                (errors > 0 ? ' (' + errors + ' errori)' : '') + '. Ricarica per aggiornare i badge.</div>';
            return;
        }
        var item = queue[idx];
        postAjax(AJAX_CRUD, { action: 'save', sesskey: SESSKEY, id: item.id, settore_ftm: item.settore, status: 'active' }, function(r) {
            done++;
            var pct = Math.round(done / totalQ * 100);
            document.getElementById('progress-fill').style.width = pct + '%';
            document.getElementById('progress-label').textContent = done + '/' + totalQ + ' salvate...';
            if (r.success) {
                var row = document.getElementById('classify-row-' + item.id);
                if (row) row.style.opacity = '0.4';
            } else {
                errors++;
            }
            saveNext(idx + 1);
        });
    }
    saveNext(0);
}

// --- Discover tab ---
function discoverCompany() {
    var url = document.getElementById('discover-url').value.trim();
    if (!url) { alert('Inserisci un URL.'); return; }

    document.getElementById('discover-spinner').classList.add('active');
    document.getElementById('discover-result').style.display = 'none';
    document.getElementById('discover-alert').innerHTML = '';

    postAjax(AJAX_DISCOVER, { action: 'discover', sesskey: SESSKEY, url: url }, function(r) {
        document.getElementById('discover-spinner').classList.remove('active');
        if (!r.success) {
            document.getElementById('discover-alert').innerHTML =
                '<div class="alert alert-danger">' + (r.message || 'Errore durante l\'analisi.') + '</div>';
            return;
        }
        var d = r.data;
        document.getElementById('res-nome').value       = d.nome || '';
        document.getElementById('res-localita').value   = d.localita || '';
        document.getElementById('res-cap').value        = d.cap || '';
        document.getElementById('res-indirizzo').value  = d.indirizzo || '';
        document.getElementById('res-dimensione').value = d.dimensione || 'unknown';
        document.getElementById('res-website').value    = d.website || url;
        document.getElementById('res-email').value      = d.email || '';
        document.getElementById('res-referente').value  = d.referente || '';
        document.getElementById('res-settore-raw').value = d.settore_raw || '';
        document.getElementById('res-note').value       = '';
        var settoreEl = document.getElementById('res-settore');
        if (d.settore_ftm) settoreEl.value = d.settore_ftm;

        // Questions.
        var qCont = document.getElementById('questions-container');
        qCont.innerHTML = '';
        var questionsDiv = document.getElementById('discover-questions');
        if (d.domande && d.domande.length > 0) {
            questionsDiv.style.display = 'block';
            d.domande.forEach(function(q, i) {
                var html = '<div class="ai-question"><label>' + escHtml(q.testo) + '</label>';
                if (q.tipo === 'select' && q.opzioni) {
                    html += '<select id="q-' + i + '">';
                    q.opzioni.forEach(function(o) {
                        html += '<option value="' + escHtml(o) + '">' + escHtml(o) + '</option>';
                    });
                    html += '</select>';
                } else {
                    html += '<input type="text" id="q-' + i + '" placeholder="Risposta...">';
                }
                html += '</div>';
                qCont.innerHTML += html;
            });
        } else {
            questionsDiv.style.display = 'none';
        }

        document.getElementById('discover-result').style.display = 'block';
    });
}

function addDiscoveredCompany() {
    var data = {
        action: 'save',
        sesskey: SESSKEY,
        nome:       document.getElementById('res-nome').value,
        settore_ftm: document.getElementById('res-settore').value,
        localita:   document.getElementById('res-localita').value,
        cap:        document.getElementById('res-cap').value,
        indirizzo:  document.getElementById('res-indirizzo').value,
        dimensione: document.getElementById('res-dimensione').value,
        website:    document.getElementById('res-website').value,
        email:      document.getElementById('res-email').value,
        referente:  document.getElementById('res-referente').value,
        settore_raw: document.getElementById('res-settore-raw').value,
        note_interne: document.getElementById('res-note').value,
        source:     'discover',
        status:     'unverified',
    };
    if (!data.nome) { alert('Il nome e\' obbligatorio.'); return; }

    postAjax(AJAX_CRUD, data, function(r) {
        if (r.success) {
            document.getElementById('discover-alert').innerHTML =
                '<div class="alert alert-success">Azienda aggiunta al database con ID ' + (r.data.id || '') + '.</div>';
            document.getElementById('discover-result').style.display = 'none';
            document.getElementById('discover-url').value = '';
        } else {
            document.getElementById('discover-alert').innerHTML =
                '<div class="alert alert-danger">' + (r.message || 'Errore salvataggio.') + '</div>';
        }
    });
}

function resetDiscover() {
    document.getElementById('discover-result').style.display = 'none';
    document.getElementById('discover-url').value = '';
    document.getElementById('discover-alert').innerHTML = '';
}

// --- Import CSV ---
var csvFile = null;
function handleFileSelect(input) {
    csvFile = input.files[0];
    document.getElementById('drop-filename').textContent = csvFile ? csvFile.name : 'Nessun file selezionato';
    document.getElementById('btn-import').disabled = !csvFile;
    document.getElementById('import-result').style.display = 'none';
}

var dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    var file = e.dataTransfer.files[0];
    if (file) {
        csvFile = file;
        document.getElementById('drop-filename').textContent = file.name;
        document.getElementById('btn-import').disabled = false;
    }
});

function importCsv() {
    if (!csvFile) { alert('Seleziona un file CSV.'); return; }
    var status = document.getElementById('import-status');
    var resultDiv = document.getElementById('import-result');
    status.textContent = 'Importazione in corso...';
    document.getElementById('btn-import').disabled = true;

    var formData = new FormData();
    formData.append('csvfile', csvFile);
    formData.append('sesskey', SESSKEY);

    fetch(AJAX_IMPORT, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(r) {
            document.getElementById('btn-import').disabled = false;
            status.textContent = '';
            if (r.success) {
                resultDiv.style.display = 'block';
                var msg = '<div class="alert alert-success">';
                msg += '<strong>Importazione completata:</strong><br>';
                msg += r.data.inserted + ' importate, ';
                msg += r.data.skipped + ' gia presenti (skippate), ';
                msg += (r.data.errors ? r.data.errors.length : 0) + ' errori.';
                if (r.data.errors && r.data.errors.length > 0) {
                    msg += '<ul style="margin-top:8px;font-size:12px;">';
                    r.data.errors.forEach(function(e) { msg += '<li>' + escHtml(e) + '</li>'; });
                    msg += '</ul>';
                }
                msg += '</div>';
                resultDiv.innerHTML = msg;
                // Refresh stats after short delay.
                setTimeout(function() { window.location.reload(); }, 2000);
            } else {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-danger">' + (r.message || 'Errore importazione.') + '</div>';
            }
        })
        .catch(function(err) {
            document.getElementById('btn-import').disabled = false;
            status.textContent = 'Errore di rete: ' + err;
        });
}

// --- Helpers ---
function postAjax(url, data, callback) {
    var body = Object.keys(data).map(function(k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] === null || data[k] === undefined ? '' : data[k]);
    }).join('&');
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(callback)
    .catch(function(e) { callback({ success: false, message: 'Errore di rete: ' + e }); });
}
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?php

echo $OUTPUT->footer();

// ============================================================================
// Helper functions
// ============================================================================

/**
 * Render the company table HTML.
 */
function render_company_table(array $companies, array $colors): string {
    if (empty($companies)) {
        return '<div class="alert alert-info">Nessuna azienda trovata.</div>';
    }
    $html = '<table class="companies-table">';
    $html .= '<thead><tr>
        <th>Nome</th>
        <th>Settore</th>
        <th>Localita</th>
        <th>Status</th>
        <th>Anno</th>
        <th>Azioni</th>
    </tr></thead><tbody>';

    foreach ($companies as $c) {
        $col = $colors[$c->settore_ftm] ?? '#adb5bd';
        $settore_badge = '<span class="badge-settore" style="background:' . $col . '">'
            . s($c->settore_ftm) . '</span>';
        $status_badge = '<span class="badge-status-' . s($c->status) . '">'
            . status_label($c->status) . '</span>';

        $toggle_label = $c->status === 'active' ? 'Disattiva' : 'Attiva';
        $toggle_class = $c->status === 'active' ? 'btn-deactivate' : 'btn-activate';

        $nome_html = s($c->nome);
        if ($c->website) {
            $nome_html .= ' <a href="' . s($c->website) . '" target="_blank"
                style="font-size:11px;color:#6c757d;margin-left:4px;">[sito]</a>';
        }

        $html .= '<tr>
            <td>' . $nome_html . '</td>
            <td>' . $settore_badge . '</td>
            <td>' . s($c->localita ?? '—') . '</td>
            <td>' . $status_badge . '</td>
            <td>' . s($c->anno_primo_contatto ?? '—') . '</td>
            <td>
                <button class="btn-sm-action btn-edit" onclick="openEditModal(' . (int)$c->id . ')">Modifica</button>
                <button class="btn-sm-action ' . $toggle_class . '"
                    onclick="toggleStatus(' . (int)$c->id . ', \'' . s($c->status) . '\')">'
                    . $toggle_label . '</button>
            </td>
        </tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/**
 * Render pagination.
 */
function render_pager(int $total, int $per_page, int $current_page, string $settore, string $status, string $search): string {
    $pages = (int) ceil($total / $per_page);
    if ($pages <= 1) {
        return '<p style="font-size:13px;color:#6c757d;margin-top:10px;">' . $total . ' aziende</p>';
    }

    $base = (new moodle_url('/local/jobmatchagent/companies.php', [
        'tab'     => 'all',
        'settore' => $settore,
        'status'  => $status,
        'search'  => $search,
    ]))->out(false);

    $html = '<div class="pager">';
    $html .= '<span style="color:#6c757d;font-size:13px;">' . $total . ' aziende</span>';

    if ($current_page > 0) {
        $html .= '<a href="' . $base . '&p=' . ($current_page - 1) . '">&laquo; Prec</a>';
    }
    $start = max(0, $current_page - 3);
    $end   = min($pages - 1, $current_page + 3);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current_page) {
            $html .= '<span class="current">' . ($i + 1) . '</span>';
        } else {
            $html .= '<a href="' . $base . '&p=' . $i . '">' . ($i + 1) . '</a>';
        }
    }
    if ($current_page < $pages - 1) {
        $html .= '<a href="' . $base . '&p=' . ($current_page + 1) . '">Succ &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Human-readable status label.
 */
function status_label(string $status): string {
    $map = [
        'active'     => 'Attiva',
        'inactive'   => 'Inattiva',
        'unverified' => 'Non verificata',
    ];
    return $map[$status] ?? $status;
}
