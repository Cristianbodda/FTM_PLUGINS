<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CPURC Manager dashboard.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/index.php'));
$PAGE->set_title(get_string('cpurc_manager', 'local_ftm_cpurc'));
$PAGE->set_heading(get_string('cpurc_manager', 'local_ftm_cpurc'));
$PAGE->set_pagelayout('standard');

// Get filter parameters.
$search = optional_param('search', '', PARAM_TEXT);
$urc = optional_param('urc', '', PARAM_TEXT);
$sector = optional_param('sector', '', PARAM_TEXT);
$status = optional_param('status', '', PARAM_TEXT);
$reportstatus = optional_param('reportstatus', '', PARAM_TEXT);
$coach = optional_param('coach', 0, PARAM_INT);
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);
$groupcolor = optional_param('groupcolor', '', PARAM_TEXT);

// Parse date filters (YYYY-MM-DD from HTML date input -> timestamp).
$datefrom_ts = 0;
$dateto_ts = 0;
if ($datefrom && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datefrom, $m)) {
    $datefrom_ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
}
if ($dateto && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateto, $m)) {
    $dateto_ts = mktime(23, 59, 59, (int)$m[2], (int)$m[3], (int)$m[1]);
}
// If only "Da" is set without "A", filter for exactly that single day.
if ($datefrom_ts > 0 && $dateto_ts == 0) {
    $dateto_ts = $datefrom_ts + 86399; // Same day 23:59:59.
}

// Get data.
$stats = \local_ftm_cpurc\cpurc_manager::get_stats();
$students = \local_ftm_cpurc\cpurc_manager::get_students([
    'search' => $search,
    'urc' => $urc,
    'sector' => $sector,
    'status' => $status,
    'report_status' => $reportstatus,
    'coach' => $coach,
    'date_from' => $datefrom_ts,
    'date_to' => $dateto_ts,
    'group_color' => $groupcolor,
]);
$urclist = \local_ftm_cpurc\cpurc_manager::get_urc_offices();
$sectorlist = \local_ftm_cpurc\cpurc_manager::get_sectors();
$coachlist = \local_ftm_cpurc\cpurc_manager::get_coaches();

// Check import capability.
$canimport = has_capability('local/ftm_cpurc:import', $context);

echo $OUTPUT->header();
?>

<style>
.cpurc-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h2 {
    margin: 0;
    font-size: 24px;
}

.header-buttons {
    display: flex;
    gap: 10px;
}

.cpurc-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.cpurc-btn-primary { background: #0066cc; color: white !important; }
.cpurc-btn-success { background: #28a745; color: white !important; }
.cpurc-btn-secondary { background: #6c757d; color: white !important; }
.cpurc-btn:hover { opacity: 0.9; text-decoration: none !important; color: white !important; }
a.cpurc-btn, a.cpurc-btn:visited, a.cpurc-btn:hover, a.cpurc-btn:active, a.cpurc-btn:focus { color: white !important; text-decoration: none !important; }

/* Stats */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
    text-align: center;
}

.stat-card.blue { border-left: 4px solid #0066cc; }
.stat-card.green { border-left: 4px solid #28a745; }
.stat-card.yellow { border-left: 4px solid #EAB308; }
.stat-card.purple { border-left: 4px solid #7030A0; }

.stat-number {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

/* Filters */
.filters-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.filters-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
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

.filter-group input, .filter-group select {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

.filter-group input[type="text"] {
    min-width: 200px;
}

/* Table */
.data-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
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

.data-table tr:hover {
    background: #f8f9fa;
}

/* Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-new { background: #D1FAE5; color: #065F46; }
.status-progress { background: #DBEAFE; color: #1E40AF; }
.status-ending { background: #FEF3C7; color: #92400E; }
.status-extended { background: #FEE2E2; color: #991B1B; }
.status-unknown { background: #E5E7EB; color: #374151; }

.sector-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    background: #E5E7EB;
    color: #374151;
}

.sector-AUTOMOBILE { background: #DBEAFE; color: #1E40AF; }
.sector-MECCANICA { background: #D1FAE5; color: #065F46; }
.sector-LOGISTICA { background: #FEF3C7; color: #92400E; }
.sector-ELETTRICITA { background: #FEE2E2; color: #991B1B; }
.sector-AUTOMAZIONE { background: #F3E8FF; color: #6B21A8; }
.sector-METALCOSTRUZIONE { background: #E5E7EB; color: #374151; }
.sector-CHIMFARM { background: #FCE7F3; color: #9D174D; }

.report-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.report-none { background: #F3F4F6; color: #6B7280; }
.report-draft { background: #FEF3C7; color: #92400E; }
.report-final { background: #D1FAE5; color: #065F46; }

/* Actions */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-btn {
    width: 30px;
    height: 30px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 14px;
}

.action-btn:hover {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

/* Empty state */
.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #666;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

/* URC breakdown */
.urc-breakdown {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.urc-chip {
    padding: 4px 10px;
    background: #f0f0f0;
    border-radius: 15px;
    font-size: 12px;
}

.urc-chip strong {
    color: #0066cc;
}

/* Add Student Modal */
.add-student-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.add-student-dialog {
    background: white;
    border-radius: 12px;
    width: 500px;
    max-width: 90vw;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.add-student-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #dee2e6;
}

.add-student-header h3 {
    margin: 0;
    font-size: 18px;
}

.add-student-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.add-student-body {
    padding: 24px;
    overflow-y: auto;
}

.add-student-search {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
}

.add-student-search:focus {
    border-color: #0066cc;
}

.add-student-hint {
    font-size: 12px;
    color: #888;
    margin-top: 6px;
}

.add-student-results {
    margin-top: 16px;
    max-height: 300px;
    overflow-y: auto;
}

.add-student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid #eee;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: background 0.15s;
}

.add-student-item:hover {
    background: #f8f9fa;
}

.add-student-item-info strong {
    display: block;
    font-size: 14px;
}

.add-student-item-info small {
    color: #888;
    font-size: 12px;
}

.add-student-item button {
    padding: 6px 16px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;
}

.add-student-item button:hover {
    background: #0052a3;
}

.add-student-item button:disabled {
    background: #28a745;
    cursor: default;
}

.add-student-msg {
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 13px;
    color: #666;
}

.add-student-msg.success {
    background: #d4edda;
    color: #155724;
}

.add-student-msg.error {
    background: #f8d7da;
    color: #721c24;
}
</style>

<div class="cpurc-dashboard">
    <!-- Header -->
    <div class="page-header">
        <h2>👥 <?php echo get_string('cpurc_manager', 'local_ftm_cpurc'); ?></h2>
        <div class="header-buttons">
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/export_excel.php', ['search' => $search, 'urc' => $urc, 'sector' => $sector, 'status' => $status, 'reportstatus' => $reportstatus, 'coach' => $coach]); ?>" class="cpurc-btn cpurc-btn-success">
                📊 Export Excel
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/export_word_bulk.php'); ?>" class="cpurc-btn cpurc-btn-success">
                📦 Export Word (ZIP)
            </a>
            <?php if ($canimport): ?>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/download_credentials.php', ['source' => 'db', 'sesskey' => sesskey(), 'search' => $search, 'urc' => $urc, 'sector' => $sector, 'status' => $status, 'reportstatus' => $reportstatus, 'coach' => $coach, 'datefrom' => $datefrom, 'dateto' => $dateto, 'groupcolor' => $groupcolor]); ?>" class="cpurc-btn cpurc-btn-success" style="background:#e67e22;">
                📋 Export Credenziali LADI
            </a>
            <button class="cpurc-btn cpurc-btn-success" onclick="document.getElementById('addStudentModal').style.display='flex'">
                ➕ Aggiungi Studente
            </button>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/import.php'); ?>" class="cpurc-btn cpurc-btn-primary">
                📥 <?php echo get_string('import_csv', 'local_ftm_cpurc'); ?>
            </a>
            <?php endif; ?>
            <?php if (is_siteadmin()): ?>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/import_production.php'); ?>" class="cpurc-btn cpurc-btn-primary" style="background:#dc3545;">
                🚀 Import Produzione
            </a>
            <?php endif; ?>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                📅 Scheduler
            </a>
            <a href="<?php echo new moodle_url('/local/competencymanager/sector_admin.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                🎯 Settori
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-number"><?php echo $stats->total; ?></div>
            <div class="stat-label"><?php echo get_string('total_students', 'local_ftm_cpurc'); ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats->active; ?></div>
            <div class="stat-label"><?php echo get_string('active_students', 'local_ftm_cpurc'); ?></div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-number"><?php echo $stats->reports_draft; ?></div>
            <div class="stat-label"><?php echo get_string('reports_draft', 'local_ftm_cpurc'); ?></div>
        </div>
        <div class="stat-card purple">
            <div class="stat-number"><?php echo $stats->reports_final; ?></div>
            <div class="stat-label"><?php echo get_string('reports_final', 'local_ftm_cpurc'); ?></div>
        </div>
    </div>

    <!-- URC Breakdown -->
    <?php if (!empty($stats->by_urc)): ?>
    <div class="filters-card">
        <strong>Distribuzione per URC:</strong>
        <div class="urc-breakdown">
            <?php foreach ($stats->by_urc as $item): ?>
                <span class="urc-chip">
                    <?php echo s($item->urc_office); ?>: <strong><?php echo $item->cnt; ?></strong>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-card">
        <form method="get" action="">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Cerca</label>
                    <input type="text" name="search" value="<?php echo s($search); ?>" placeholder="Nome, cognome, email...">
                </div>
                <div class="filter-group">
                    <label>URC</label>
                    <select name="urc">
                        <option value="">Tutti</option>
                        <?php foreach ($urclist as $item): ?>
                            <option value="<?php echo s($item->urc_office); ?>" <?php echo $urc === $item->urc_office ? 'selected' : ''; ?>>
                                <?php echo s($item->urc_office); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Settore</label>
                    <select name="sector">
                        <option value="">Tutti</option>
                        <?php foreach ($sectorlist as $item): ?>
                            <option value="<?php echo s($item->sector_detected); ?>" <?php echo $sector === $item->sector_detected ? 'selected' : ''; ?>>
                                <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($item->sector_detected)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Stato</label>
                    <select name="status">
                        <option value="">Tutti</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Attivi</option>
                        <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Chiusi</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Report</label>
                    <select name="reportstatus">
                        <option value="">Tutti</option>
                        <option value="none" <?php echo $reportstatus === 'none' ? 'selected' : ''; ?>>🔴 Non iniziato</option>
                        <option value="draft" <?php echo $reportstatus === 'draft' ? 'selected' : ''; ?>>🟡 Bozza</option>
                        <option value="complete" <?php echo $reportstatus === 'complete' ? 'selected' : ''; ?>>🟢 Completo</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Coach</label>
                    <select name="coach">
                        <option value="">Tutti</option>
                        <?php foreach ($coachlist as $c): ?>
                            <option value="<?php echo $c->userid; ?>" <?php echo $coach == $c->userid ? 'selected' : ''; ?>>
                                <?php echo s($c->lastname . ' ' . $c->firstname); ?>
                                <?php if (!empty($c->initials)): ?>(<?php echo s($c->initials); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Data Inizio Da</label>
                    <input type="date" name="datefrom" value="<?php echo s($datefrom); ?>">
                </div>
                <div class="filter-group">
                    <label>Data Inizio A</label>
                    <input type="date" name="dateto" value="<?php echo s($dateto); ?>">
                </div>
                <div class="filter-group">
                    <label>Gruppo Colore</label>
                    <select name="groupcolor">
                        <option value="">Tutti</option>
                        <option value="giallo" <?php echo $groupcolor === 'giallo' ? 'selected' : ''; ?>>🟡 Giallo</option>
                        <option value="grigio" <?php echo $groupcolor === 'grigio' ? 'selected' : ''; ?>>⚪ Grigio</option>
                        <option value="rosso" <?php echo $groupcolor === 'rosso' ? 'selected' : ''; ?>>🔴 Rosso</option>
                        <option value="marrone" <?php echo $groupcolor === 'marrone' ? 'selected' : ''; ?>>🟤 Marrone</option>
                        <option value="viola" <?php echo $groupcolor === 'viola' ? 'selected' : ''; ?>>🟣 Viola</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="cpurc-btn cpurc-btn-primary">🔍 Filtra</button>
                </div>
                <?php if ($search || $urc || $sector || $status || $reportstatus || $coach || $datefrom || $dateto || $groupcolor): ?>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                        ✕ Reset
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Students Table -->
    <div class="data-card">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>Nessuno studente trovato.</p>
                <?php if ($canimport): ?>
                <a href="<?php echo new moodle_url('/local/ftm_cpurc/import.php'); ?>" class="cpurc-btn cpurc-btn-success">
                    📥 Importa CSV CPURC
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Settimana</th>
                        <th>Nome</th>
                        <th>URC</th>
                        <th>Coach</th>
                        <th>Settore</th>
                        <th>Inizio</th>
                        <th>Report</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student):
                        $week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($student->date_start);
                        $weekstatus = \local_ftm_cpurc\cpurc_manager::get_week_status($week);
                    ?>
                    <tr>
                        <td>
                            <span class="status-badge <?php echo $weekstatus['class']; ?>">
                                <?php echo $weekstatus['icon']; ?> Sett. <?php echo $week > 0 ? $week : '-'; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo s($student->lastname); ?></strong> <?php echo s($student->firstname); ?><br>
                            <small style="color: #666;"><?php echo s($student->email); ?></small>
                        </td>
                        <td><?php echo s($student->urc_office ?? '-'); ?></td>
                        <td>
                            <select class="coach-select" data-userid="<?php echo $student->userid; ?>" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd; font-size: 12px; min-width: 100px;">
                                <option value="">-- Nessuno --</option>
                                <?php foreach ($coachlist as $c): ?>
                                    <option value="<?php echo $c->userid; ?>" <?php echo ($student->coachid == $c->userid) ? 'selected' : ''; ?>>
                                        <?php echo s($c->lastname . ' ' . $c->firstname); ?>
                                        <?php if (!empty($c->initials)): ?>(<?php echo s($c->initials); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <?php if (!empty($student->sector_detected)): ?>
                                <span class="sector-badge sector-<?php echo $student->sector_detected; ?>">
                                    <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>
                                </span>
                            <?php else: ?>
                                <span class="sector-badge">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo local_ftm_cpurc_format_date($student->date_start); ?></td>
                        <td>
                            <?php if (!empty($student->reportid)): ?>
                                <?php if ($student->report_status === 'draft'): ?>
                                    <span class="report-badge report-draft">🟡 Bozza</span>
                                <?php else: ?>
                                    <span class="report-badge report-final">🟢 Finale</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="report-badge report-none">🔴 -</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="<?php echo new moodle_url('/local/ftm_cpurc/student_card.php', ['id' => $student->id]); ?>"
                                   class="action-btn" title="Scheda">📋</a>
                                <a href="<?php echo new moodle_url('/local/ftm_cpurc/report.php', ['id' => $student->id]); ?>"
                                   class="action-btn" title="Report">📝</a>
                                <?php if (!empty($student->reportid)): ?>
                                <a href="<?php echo new moodle_url('/local/ftm_cpurc/export_word.php', ['id' => $student->id]); ?>"
                                   class="action-btn" title="Esporta Word">📄</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding: 15px; background: #f8f9fa; font-size: 13px; color: #666;">
                Trovati <?php echo count($students); ?> studenti
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Student Modal -->
<?php if ($canimport): ?>
<div class="add-student-modal" id="addStudentModal">
    <div class="add-student-dialog">
        <div class="add-student-header">
            <h3>➕ Aggiungi Studente</h3>
            <button class="add-student-close" onclick="document.getElementById('addStudentModal').style.display='none'">&times;</button>
        </div>
        <div class="add-student-body">
            <input type="text" class="add-student-search" id="addStudentSearch"
                   placeholder="Cerca per nome, cognome o email..." autocomplete="off">
            <div class="add-student-hint">Digita almeno 2 caratteri per cercare utenti Moodle non ancora in CPURC</div>
            <div class="add-student-results" id="addStudentResults"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Coach assignment AJAX
document.querySelectorAll('.coach-select').forEach(function(select) {
    select.addEventListener('change', function() {
        const userid = this.dataset.userid;
        const coachid = this.value;
        const selectEl = this;

        selectEl.style.opacity = '0.5';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_assign_coach.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid + '&coachid=' + coachid
        })
        .then(response => response.json())
        .then(data => {
            selectEl.style.opacity = '1';
            if (data.success) {
                selectEl.style.borderColor = '#28a745';
                setTimeout(() => { selectEl.style.borderColor = '#ddd'; }, 2000);
            } else {
                alert('Errore: ' + data.message);
                selectEl.style.borderColor = '#dc3545';
            }
        })
        .catch(error => {
            selectEl.style.opacity = '1';
            selectEl.style.borderColor = '#dc3545';
            console.error('Error:', error);
        });
    });
});

// --- Add Student Modal ---
(function() {
    var searchInput = document.getElementById('addStudentSearch');
    var resultsDiv = document.getElementById('addStudentResults');
    var modal = document.getElementById('addStudentModal');
    var timer = null;

    if (!searchInput) return;

    // Close modal on backdrop click.
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Search as user types.
    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        var query = this.value.trim();
        if (query.length < 2) {
            resultsDiv.innerHTML = '';
            return;
        }
        timer = setTimeout(function() { searchUsers(query); }, 300);
    });

    // Focus input when modal opens.
    var observer = new MutationObserver(function() {
        if (modal.style.display === 'flex') {
            searchInput.value = '';
            resultsDiv.innerHTML = '';
            searchInput.focus();
        }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['style'] });

    function searchUsers(query) {
        resultsDiv.innerHTML = '<div class="add-student-msg">Ricerca in corso...</div>';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_add_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sesskey=<?php echo sesskey(); ?>&action=search&query=' + encodeURIComponent(query)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                resultsDiv.innerHTML = '<div class="add-student-msg error">' + escText(data.message) + '</div>';
                return;
            }
            if (data.users.length === 0) {
                resultsDiv.innerHTML = '<div class="add-student-msg">Nessun utente trovato (o gia in CPURC)</div>';
                return;
            }
            var html = '';
            data.users.forEach(function(u) {
                html += '<div class="add-student-item" id="user-row-' + u.id + '">' +
                    '<div class="add-student-item-info">' +
                    '<strong>' + escText(u.lastname) + ' ' + escText(u.firstname) + '</strong>' +
                    '<small>' + escText(u.email) + '</small>' +
                    '</div>' +
                    '<button onclick="addStudent(' + u.id + ', this)">Aggiungi</button>' +
                    '</div>';
            });
            resultsDiv.innerHTML = html;
        })
        .catch(function() {
            resultsDiv.innerHTML = '<div class="add-student-msg error">Errore di connessione</div>';
        });
    }

    function escText(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.addStudent = function(userid, btn) {
        btn.disabled = true;
        btn.textContent = '...';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_add_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sesskey=<?php echo sesskey(); ?>&action=add&userid=' + userid
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = 'Aggiunto!';
                btn.style.background = '#28a745';
                // Reload page after short delay to show the new student.
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                btn.disabled = false;
                btn.textContent = 'Aggiungi';
                alert('Errore: ' + data.message);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Aggiungi';
            alert('Errore di connessione');
        });
    };
})();
</script>

<?php
echo $OUTPUT->footer();
