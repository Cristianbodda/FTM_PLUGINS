<?php
/**
 * Sector Admin - Gestione Settori Studenti (Segreteria)
 * Stile uniforme con FTM Scheduler
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/sector_manager.php');

use local_competencymanager\sector_manager;

require_login();
$context = context_system::instance();
require_capability('local/competencymanager:manage', $context);

// Parametri filtro
$courseid = optional_param('courseid', 0, PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_ALPHANUMEXT);
$search = optional_param('search', '', PARAM_TEXT);
$date_from = optional_param('date_from', '', PARAM_TEXT);
$date_to = optional_param('date_to', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/competencymanager/sector_admin.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('sector_admin', 'local_competencymanager'));
$PAGE->set_heading(get_string('sector_admin', 'local_competencymanager'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

// Ottieni dati per i filtri
$courses = sector_manager::get_active_courses();
$cohorts = sector_manager::get_visible_cohorts();
$sectors = sector_manager::SECTORS;

// Prepara filtri
$filters = [];
if ($courseid > 0) $filters['courseid'] = $courseid;
if ($cohortid > 0) $filters['cohortid'] = $cohortid;
if (!empty($sector)) $filters['sector'] = $sector;
if (!empty($search)) $filters['search'] = $search;
if (!empty($date_from)) {
    $filters['date_from'] = strtotime($date_from);
}
if (!empty($date_to)) {
    $filters['date_to'] = strtotime($date_to) + 86399;
}

// Ottieni studenti
$students = sector_manager::get_students_with_sectors($filters);

// Statistiche
$stats = [
    'total' => count($students),
    'with_primary' => 0,
    'with_detected' => 0,
    'new_entries' => 0,
    'critical' => 0
];

foreach ($students as $s) {
    if (!empty($s->primary_sector)) $stats['with_primary']++;
    if (!empty($s->sectors) && count($s->sectors) > ($s->primary_sector ? 1 : 0)) $stats['with_detected']++;
    if ($s->color_class === 'green') $stats['new_entries']++;
    if ($s->color_class === 'red') $stats['critical']++;
}

// Icone status per settimane
$status_icons = [
    'green' => ['icon' => '🆕', 'label' => 'Nuovo ingresso', 'bg' => '#D1FAE5', 'border' => '#10B981'],
    'yellow' => ['icon' => '⏳', 'label' => 'In corso', 'bg' => '#DBEAFE', 'border' => '#3B82F6'],
    'orange' => ['icon' => '⚠️', 'label' => 'Fine vicina', 'bg' => '#FEF3C7', 'border' => '#F59E0B'],
    'red' => ['icon' => '🔴', 'label' => 'Prolungo', 'bg' => '#FEE2E2', 'border' => '#EF4444'],
    'gray' => ['icon' => '➖', 'label' => 'Non impostato', 'bg' => '#F3F4F6', 'border' => '#9CA3AF']
];

?>

<!-- CSS in stile FTM Scheduler -->
<style>
/* Reset e base - IDENTICO allo scheduler */
.ftm-sector-admin * {
    box-sizing: border-box;
}

.ftm-sector-admin {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #333;
    max-width: 1600px;
    margin: 0 auto;
}

/* Buttons - stile scheduler */
.ftm-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}

.ftm-btn-primary { background: #0066cc; color: white; }
.ftm-btn-secondary { background: #6c757d; color: white; }
.ftm-btn-success { background: #28a745; color: white; }
.ftm-btn-sm { padding: 6px 12px; font-size: 13px; }
.ftm-btn:hover { opacity: 0.9; text-decoration: none; color: white; transform: translateY(-1px); }

/* Page Title - stile scheduler */
.page-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title h2 { font-size: 24px; margin: 0; }

.page-title-buttons {
    display: flex;
    gap: 10px;
}

/* Stats Row - stile scheduler */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    border: 1px solid #dee2e6;
    text-align: center;
}

.stat-card.blue { border-left: 4px solid #0066cc; }
.stat-card.green { border-left: 4px solid #28a745; }
.stat-card.yellow { border-left: 4px solid #EAB308; }
.stat-card.orange { border-left: 4px solid #fd7e14; }
.stat-card.red { border-left: 4px solid #dc3545; }

.stat-number { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: #666; margin-top: 5px; }

/* Legend - stile scheduler */
.legend {
    display: flex;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
    border: 1px solid #dee2e6;
}

.legend-title {
    font-weight: 600;
    color: #666;
    margin-right: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    padding: 5px 12px;
    border-radius: 20px;
    background: white;
    border: 1px solid #dee2e6;
}

/* Filters - stile scheduler */
.filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.filter-group select, .filter-group input {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

.filter-group input[type="text"] {
    min-width: 200px;
}

.filter-group input[type="date"] {
    min-width: 140px;
}

/* Data Table - stile scheduler */
.data-table-container {
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th, .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

.data-table tr:hover { background: #f8f9fa; }
.data-table tr:last-child td { border-bottom: none; }

/* Status Badge con icona */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid;
}

/* Sector Badge */
.sector-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin: 2px;
}

.sector-primary {
    background: #0066cc;
    color: white;
}

.sector-detected {
    background: #e9ecef;
    color: #495057;
    border: 1px solid #dee2e6;
}

/* Student Info */
.student-info {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #333;
}

.student-email {
    font-size: 12px;
    color: #999;
}

/* Quick Actions - stile scheduler */
.quick-actions {
    display: flex;
    gap: 8px;
}

.action-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    text-decoration: none;
    color: #333;
    font-size: 14px;
}

.action-icon:hover {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

/* Modal - stile scheduler */
.ftm-modal-overlay {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0,0,0,0.5) !important;
    z-index: 99999 !important;
    align-items: center !important;
    justify-content: center !important;
}

.ftm-modal-overlay.active { display: flex !important; }

.ftm-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.ftm-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border-radius: 12px 12px 0 0;
}

.ftm-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.ftm-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ftm-modal-close:hover {
    background: #eee;
}

.ftm-modal-body { padding: 20px; }

.ftm-modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

/* Form elements - stile scheduler */
.form-group { margin-bottom: 20px; }

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.info-row:last-child { border-bottom: none; }

.info-label {
    font-weight: 600;
    width: 130px;
    color: #666;
}

.info-value {
    flex: 1;
}

/* Alert box - stile scheduler */
.ftm-alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ftm-alert-info {
    background: #DBEAFE;
    border: 1px solid #3B82F6;
    color: #1E40AF;
}

/* Results count */
.results-count {
    padding: 10px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    color: #666;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .page-title {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .filters {
        flex-direction: column;
    }

    .filter-group select, .filter-group input {
        width: 100%;
    }
}
</style>

<div class="ftm-sector-admin">
    <!-- Page Title -->
    <div class="page-title">
        <h2>👥 <?php echo get_string('sector_admin', 'local_competencymanager'); ?></h2>
        <div class="page-title-buttons">
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="ftm-btn ftm-btn-secondary">
                📅 Vai allo Scheduler
            </a>
            <button class="ftm-btn ftm-btn-primary" onclick="location.reload()">
                🔄 Aggiorna
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Studenti Totali</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['with_primary']; ?></div>
            <div class="stat-label">Con Settore Primario</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-number"><?php echo $stats['with_detected']; ?></div>
            <div class="stat-label">Con Settori Rilevati</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['new_entries']; ?></div>
            <div class="stat-label">Nuovi Ingressi</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['critical']; ?></div>
            <div class="stat-label">In Prolungo</div>
        </div>
    </div>

    <!-- Legend -->
    <div class="legend">
        <span class="legend-title">📊 STATO PERCORSO:</span>
        <?php foreach ($status_icons as $key => $icon): ?>
            <div class="legend-item" style="background: <?php echo $icon['bg']; ?>; border-color: <?php echo $icon['border']; ?>;">
                <span><?php echo $icon['icon']; ?></span>
                <span><?php echo $icon['label']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="get" action="<?php echo $PAGE->url; ?>">
        <div class="filters">
            <div class="filter-group">
                <label>🔍 Cerca Studente</label>
                <input type="text" name="search" value="<?php echo s($search); ?>"
                       placeholder="Nome, cognome o email...">
            </div>

            <div class="filter-group">
                <label>📚 Corso</label>
                <select name="courseid">
                    <option value="0">Tutti i corsi</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php echo $courseid == $c->id ? 'selected' : ''; ?>>
                            <?php echo format_string($c->shortname ?: $c->fullname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>👥 Coorte</label>
                <select name="cohortid">
                    <option value="0">Tutte le coorti</option>
                    <?php foreach ($cohorts as $ch): ?>
                        <option value="<?php echo $ch->id; ?>" <?php echo $cohortid == $ch->id ? 'selected' : ''; ?>>
                            <?php echo format_string($ch->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>🏭 Settore</label>
                <select name="sector">
                    <option value="">Tutti i settori</option>
                    <?php foreach ($sectors as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $sector === $code ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>📅 Da</label>
                <input type="date" name="date_from" value="<?php echo s($date_from); ?>">
            </div>

            <div class="filter-group">
                <label>📅 A</label>
                <input type="date" name="date_to" value="<?php echo s($date_to); ?>">
            </div>

            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="ftm-btn ftm-btn-primary">
                    🔍 Cerca
                </button>
            </div>

            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="<?php echo $PAGE->url; ?>" class="ftm-btn ftm-btn-secondary">
                    ↺ Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Data Table -->
    <div class="data-table-container">
        <div class="results-count">
            📋 Trovati <strong><?php echo count($students); ?></strong> studenti
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Stato</th>
                    <th>Studente</th>
                    <th>Corso</th>
                    <th>Coorte</th>
                    <th>Ingresso</th>
                    <th>Settore Primario</th>
                    <th>Settori Rilevati</th>
                    <th style="width: 80px;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                            😕 Nessuno studente trovato con i filtri selezionati
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student):
                        $icon_data = $status_icons[$student->color_class] ?? $status_icons['gray'];
                    ?>
                        <tr>
                            <td>
                                <span class="status-badge"
                                      style="background: <?php echo $icon_data['bg']; ?>; border-color: <?php echo $icon_data['border']; ?>;"
                                      title="<?php echo $icon_data['label']; ?>">
                                    <?php echo $icon_data['icon']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="student-info">
                                    <span class="student-name"><?php echo fullname($student); ?></span>
                                    <span class="student-email"><?php echo $student->email; ?></span>
                                </div>
                            </td>
                            <td><?php echo $student->course_name ?: '<span style="color:#999">-</span>'; ?></td>
                            <td><?php echo $student->cohort_name ?: '<span style="color:#999">-</span>'; ?></td>
                            <td>
                                <?php if ($student->date_start): ?>
                                    <?php echo userdate($student->date_start, '%d/%m/%Y'); ?>
                                <?php else: ?>
                                    <span style="color:#999">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student->primary_sector): ?>
                                    <span class="sector-badge sector-primary">
                                        <?php echo $sectors[$student->primary_sector] ?? $student->primary_sector; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#999">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $detected = [];
                                foreach ($student->sectors as $sec) {
                                    if (!$sec->is_primary) {
                                        $detected[] = $sec;
                                    }
                                }
                                if (!empty($detected)):
                                    foreach ($detected as $sec): ?>
                                        <span class="sector-badge sector-detected"
                                              title="<?php echo $sec->quiz_count; ?> quiz completati">
                                            <?php echo $sectors[$sec->sector] ?? $sec->sector; ?>
                                            <small>(<?php echo $sec->quiz_count; ?>)</small>
                                        </span>
                                    <?php endforeach;
                                else: ?>
                                    <span style="color:#999">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $modalData = json_encode([
                                    'userid' => $student->id,
                                    'username' => fullname($student),
                                    'email' => $student->email,
                                    'course' => $student->course_name ?: '-',
                                    'cohort' => $student->cohort_name ?: '-',
                                    'datestart' => $student->date_start ? userdate($student->date_start, '%d/%m/%Y') : '-',
                                    'primary' => $student->primary_sector ?: '',
                                    'sectors' => array_map(function($s) {
                                        return ['sector' => $s->sector, 'quiz_count' => $s->quiz_count, 'is_primary' => $s->is_primary];
                                    }, $student->sectors),
                                    'courseid' => $student->coaching_courseid ?: 0
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <div class="quick-actions">
                                    <button type="button" class="action-icon"
                                            title="Modifica settore"
                                            onclick='openEditModal(<?php echo $modalData; ?>)'>
                                        ✏️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Modifica Settore -->
<div class="ftm-modal-overlay" id="editModal">
    <div class="ftm-modal">
        <div class="ftm-modal-header">
            <h3>✏️ Modifica Settore Studente</h3>
            <button class="ftm-modal-close" onclick="closeEditModal()">×</button>
        </div>
        <div class="ftm-modal-body">
            <div class="info-row">
                <span class="info-label">👤 Studente:</span>
                <span class="info-value" id="modal-student-name"></span>
            </div>
            <div class="info-row">
                <span class="info-label">📧 Email:</span>
                <span class="info-value" id="modal-student-email"></span>
            </div>
            <div class="info-row">
                <span class="info-label">📚 Corso:</span>
                <span class="info-value" id="modal-course-name"></span>
            </div>
            <div class="info-row">
                <span class="info-label">👥 Coorte:</span>
                <span class="info-value" id="modal-cohort-name"></span>
            </div>
            <div class="info-row">
                <span class="info-label">📅 Ingresso:</span>
                <span class="info-value" id="modal-date-start"></span>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">

            <div class="form-group">
                <label>🏭 Settore Primario</label>
                <select id="modal-primary-sector">
                    <option value="">-- Nessun settore primario --</option>
                    <?php foreach ($sectors as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>📊 Settori Rilevati dai Quiz</label>
                <div id="modal-detected-sectors" style="padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 40px;">
                    <span style="color: #999;">Nessun settore rilevato</span>
                </div>
            </div>

            <input type="hidden" id="modal-userid" value="">
            <input type="hidden" id="modal-courseid" value="">
        </div>
        <div class="ftm-modal-footer">
            <button type="button" class="ftm-btn ftm-btn-secondary" onclick="closeEditModal()">
                Annulla
            </button>
            <button type="button" class="ftm-btn ftm-btn-success" onclick="saveSector()">
                ✅ Salva
            </button>
        </div>
    </div>
</div>

<script>
var sectorsMap = <?php echo json_encode($sectors); ?>;

// Debug mode
var DEBUG = true;
function log() {
    if (DEBUG) console.log.apply(console, ['[Sector Admin]'].concat(Array.prototype.slice.call(arguments)));
}

function openEditModal(data) {
    log('openEditModal called with:', data);

    try {
        document.getElementById('modal-student-name').textContent = data.username;
        document.getElementById('modal-student-email').textContent = data.email;
        document.getElementById('modal-course-name').textContent = data.course;
        document.getElementById('modal-cohort-name').textContent = data.cohort;
        document.getElementById('modal-date-start').textContent = data.datestart;
        document.getElementById('modal-primary-sector').value = data.primary;
        document.getElementById('modal-userid').value = data.userid;
        document.getElementById('modal-courseid').value = data.courseid;

        // Mostra settori rilevati
        var detectedHtml = '';
        if (data.sectors && data.sectors.length > 0) {
            data.sectors.forEach(function(s) {
                if (!s.is_primary) {
                    var sectorName = sectorsMap[s.sector] || s.sector;
                    detectedHtml += '<span class="sector-badge sector-detected">' +
                                   sectorName + ' <small>(' + s.quiz_count + ' quiz)</small></span> ';
                }
            });
        }
        if (!detectedHtml) {
            detectedHtml = '<span style="color: #999;">Nessun settore rilevato</span>';
        }
        document.getElementById('modal-detected-sectors').innerHTML = detectedHtml;

        var modal = document.getElementById('editModal');
        log('Modal element:', modal);
        modal.classList.add('active');
        modal.style.display = 'flex';
        log('Modal opened');

    } catch (error) {
        console.error('Error in openEditModal:', error);
        alert('Errore: ' + error.message);
    }
}

function closeEditModal() {
    log('closeEditModal called');
    var modal = document.getElementById('editModal');
    modal.classList.remove('active');
    modal.style.display = 'none';
}

function saveSector() {
    var userid = document.getElementById('modal-userid').value;
    var courseid = document.getElementById('modal-courseid').value;
    var sector = document.getElementById('modal-primary-sector').value;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax_save_sector.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert('Errore: ' + (resp.error || 'Errore nel salvataggio'));
                    }
                } catch(e) {
                    alert('Errore nella risposta del server');
                }
            } else {
                alert('Errore di connessione');
            }
        }
    };
    xhr.send('sesskey=<?php echo sesskey(); ?>&userid=' + userid +
             '&courseid=' + courseid + '&sector=' + encodeURIComponent(sector));
}

// Chiudi modal cliccando fuori
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Chiudi modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>

<?php
echo $OUTPUT->footer();
