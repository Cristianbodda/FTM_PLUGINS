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
 * Student card page.
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

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'anagrafica', PARAM_ALPHA);

// Get student data.
$student = \local_ftm_cpurc\cpurc_manager::get_student($id);
if (!$student) {
    throw new moodle_exception('Student not found');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/student_card.php', ['id' => $id, 'tab' => $tab]));
$PAGE->set_title(get_string('student_card', 'local_ftm_cpurc') . ': ' . fullname($student));
$PAGE->set_heading(get_string('student_card', 'local_ftm_cpurc'));
$PAGE->set_pagelayout('standard');

// Calculate week.
$week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($student->date_start);
$weekstatus = \local_ftm_cpurc\cpurc_manager::get_week_status($week);

// Can edit?
$canedit = has_capability('local/ftm_cpurc:edit', $context);

echo $OUTPUT->header();
?>

<style>
.student-card {
    max-width: 1200px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.card-header {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.student-info h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.student-meta {
    display: flex;
    gap: 20px;
    font-size: 14px;
    color: #666;
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
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

.cpurc-btn-primary { background: #0066cc; color: white; }
.cpurc-btn-success { background: #28a745; color: white; }
.cpurc-btn-secondary { background: #6c757d; color: white; }
.cpurc-btn:hover { opacity: 0.9; text-decoration: none; color: white; }

/* Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.status-new { background: #D1FAE5; color: #065F46; }
.status-progress { background: #DBEAFE; color: #1E40AF; }
.status-ending { background: #FEF3C7; color: #92400E; }
.status-extended { background: #FEE2E2; color: #991B1B; }
.status-unknown { background: #E5E7EB; color: #374151; }

.sector-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.sector-AUTOMOBILE { background: #DBEAFE; color: #1E40AF; }
.sector-MECCANICA { background: #D1FAE5; color: #065F46; }
.sector-LOGISTICA { background: #FEF3C7; color: #92400E; }
.sector-ELETTRICITA { background: #FEE2E2; color: #991B1B; }
.sector-AUTOMAZIONE { background: #F3E8FF; color: #6B21A8; }
.sector-METALCOSTRUZIONE { background: #E5E7EB; color: #374151; }
.sector-CHIMFARM { background: #FCE7F3; color: #9D174D; }

/* Tabs */
.card-tabs {
    display: flex;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}

.card-tab {
    padding: 15px 25px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    color: #666;
    text-decoration: none;
}

.card-tab:hover { background: #f8f9fa; text-decoration: none; color: #666; }
.card-tab.active { color: #0066cc; border-bottom-color: #0066cc; background: white; }

.tab-content {
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 25px;
}

/* Form */
.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    font-size: 16px;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.form-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.form-group input, .form-group select, .form-group textarea {
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-group input:disabled, .form-group select:disabled, .form-group textarea:disabled {
    background: #f8f9fa;
    color: #666;
}

.form-group .value-display {
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 14px;
    min-height: 38px;
}

/* Absences Table */
.absences-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
}

.absence-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.absence-item .code {
    font-weight: 700;
    font-size: 18px;
    color: #333;
}

.absence-item .value {
    font-size: 24px;
    font-weight: 700;
    margin: 10px 0;
}

.absence-item .label {
    font-size: 11px;
    color: #666;
}

.absence-total {
    background: #FEE2E2 !important;
}

.absence-total .value {
    color: #991B1B;
}

/* Stage section */
.stage-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
}

.stage-card h4 {
    margin: 0 0 15px 0;
    font-size: 14px;
}

/* Sector clear button */
.sector-clear {
    cursor: pointer;
    font-size: 12px;
    margin-left: 8px;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.sector-clear:hover {
    opacity: 1;
}

/* Sector delete button in badges */
.sector-delete-btn {
    cursor: pointer;
    font-size: 10px;
    margin-left: 5px;
    opacity: 0.7;
    transition: all 0.2s;
}

.sector-delete-btn:hover {
    opacity: 1;
    transform: scale(1.2);
}
</style>

<div class="student-card">
    <!-- Header -->
    <div class="card-header">
        <div class="student-info">
            <h2><?php echo s($student->lastname); ?> <?php echo s($student->firstname); ?></h2>
            <div class="student-meta">
                <span>📧 <?php echo s($student->email); ?></span>
                <span>📞 <?php echo s($student->mobile ?: $student->phone ?: '-'); ?></span>
                <span>🏢 <?php echo s($student->urc_office ?: '-'); ?></span>
            </div>
        </div>
        <div class="header-buttons">
            <span class="status-badge <?php echo $weekstatus['class']; ?>">
                <?php echo $weekstatus['icon']; ?> Settimana <?php echo $week > 0 ? $week : '-'; ?>
            </span>
            <?php if (!empty($student->sector_detected)): ?>
            <span class="sector-badge sector-<?php echo $student->sector_detected; ?>">
                <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>
            </span>
            <?php endif; ?>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/report.php', ['id' => $id]); ?>" class="cpurc-btn cpurc-btn-success">
                📝 Report
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="cpurc-btn cpurc-btn-secondary">
                ← Torna
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="card-tabs">
        <a href="?id=<?php echo $id; ?>&tab=anagrafica" class="card-tab <?php echo $tab === 'anagrafica' ? 'active' : ''; ?>">
            👤 <?php echo get_string('tab_anagrafica', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=percorso" class="card-tab <?php echo $tab === 'percorso' ? 'active' : ''; ?>">
            📋 <?php echo get_string('tab_percorso', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=assenze" class="card-tab <?php echo $tab === 'assenze' ? 'active' : ''; ?>">
            📊 <?php echo get_string('tab_assenze', 'local_ftm_cpurc'); ?>
        </a>
        <a href="?id=<?php echo $id; ?>&tab=stage" class="card-tab <?php echo $tab === 'stage' ? 'active' : ''; ?>">
            🏭 <?php echo get_string('tab_stage', 'local_ftm_cpurc'); ?>
        </a>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($tab === 'anagrafica'): ?>
            <!-- ANAGRAFICA -->
            <div class="form-section">
                <h3>Dati Personali</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('firstname', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->firstname); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('lastname', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->lastname); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('gender', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->gender ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('birthdate', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->birthdate); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('nationality', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->nationality ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('permit', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->permit ?: '-'); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Contatti</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('email', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->email); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('phone', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->phone ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('mobile', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->mobile ?: '-'); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Indirizzo</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label><?php echo get_string('address', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->address_street ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('cap', 'local_ftm_cpurc'); ?> / <?php echo get_string('city', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->address_cap); ?> <?php echo s($student->address_city); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Dati Amministrativi</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('avs_number', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->avs_number ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('iban', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->iban ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('civil_status', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->civil_status ?: '-'); ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'percorso'): ?>
            <!-- PERCORSO -->
            <div class="form-section">
                <h3>Dati URC</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('personal_number', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->personal_number ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('urc_office', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->urc_office ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('urc_consultant', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->urc_consultant ?: '-'); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Percorso FTM</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('measure', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->measure ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_start', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->date_start); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_end_planned', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->date_end_planned); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('date_end_actual', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->date_end_actual) ?: '-'; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('status', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->status ?: 'Aperto'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('occupation_grade', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo $student->occupation_grade ? $student->occupation_grade . '%' : '-'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Coach FTM Assignment -->
            <?php
            $coaches = \local_ftm_cpurc\cpurc_manager::get_coaches();
            $currentCoach = \local_ftm_cpurc\cpurc_manager::get_student_coach($student->userid);
            ?>
            <div class="form-section" id="coach-assignment-section">
                <h3>👨‍🏫 Coach FTM Assegnato</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                    Assegna il coach responsabile per questo studente. Sincronizzato con tutti i plugin FTM.
                </p>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Coach Attuale</label>
                        <div id="current-coach-display" class="value-display" style="display: flex; align-items: center; gap: 10px;">
                            <?php if ($currentCoach): ?>
                                <span class="coach-badge" style="background: #E0F2FE; color: #0369A1; padding: 4px 12px; border-radius: 15px; font-weight: 600;">
                                    <?php echo s($currentCoach->firstname . ' ' . $currentCoach->lastname); ?>
                                </span>
                                <small style="color: #666;">(<?php echo s($currentCoach->email); ?>)</small>
                            <?php else: ?>
                                <span style="color: #999;">⚠️ Nessun coach assegnato</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Cambia Coach <span class="sector-clear" data-target="coach_select" title="Rimuovi assegnazione">❌</span></label>
                        <select id="coach_select" data-userid="<?php echo $student->userid; ?>" style="flex: 1;">
                            <option value="">-- Seleziona Coach --</option>
                            <?php foreach ($coaches as $c): ?>
                                <?php
                                $coachLabel = $c->firstname . ' ' . $c->lastname;
                                if (!empty($c->initials)) {
                                    $coachLabel .= ' (' . $c->initials . ')';
                                }
                                $selected = ($currentCoach && $currentCoach->id == $c->userid) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $c->userid; ?>" <?php echo $selected; ?>>
                                    <?php echo s($coachLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="button" id="btn-save-coach" class="cpurc-btn cpurc-btn-primary">
                        💾 Salva Coach
                    </button>
                    <span id="coach-save-status" style="margin-left: 15px; font-size: 13px;"></span>
                </div>
            </div>

            <div class="form-section">
                <h3>Professione e Settore</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label><?php echo get_string('last_profession', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->last_profession ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label>Settore rilevato (da professione)</label>
                        <div class="value-display">
                            <?php if (!empty($student->sector_detected)): ?>
                                <span class="sector-badge sector-<?php echo $student->sector_detected; ?>">
                                    <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($student->sector_detected)); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Sector Assignment -->
            <?php
            $allSectors = [
                'AUTOMOBILE' => 'Automobile',
                'MECCANICA' => 'Meccanica',
                'LOGISTICA' => 'Logistica',
                'ELETTRICITA' => 'Elettricita',
                'AUTOMAZIONE' => 'Automazione',
                'METALCOSTRUZIONE' => 'Metalcostruzione',
                'CHIMFARM' => 'Chimico-Farmaceutico',
            ];
            $studentSectors = \local_ftm_cpurc\cpurc_manager::get_student_sectors($student->userid);
            $primarySector = '';
            $secondarySector = '';
            $tertiarySector = '';

            $rank = 1;
            foreach ($studentSectors as $sec) {
                if ($sec->is_primary == 1) {
                    $primarySector = $sec->sector;
                } else if ($rank == 1 && empty($primarySector)) {
                    $primarySector = $sec->sector;
                } else if ($rank == 2 || (empty($secondarySector) && $sec->sector != $primarySector)) {
                    $secondarySector = $sec->sector;
                    $rank = 3;
                } else if (empty($tertiarySector) && $sec->sector != $primarySector && $sec->sector != $secondarySector) {
                    $tertiarySector = $sec->sector;
                }
            }
            ?>
            <div class="form-section" id="sector-assignment-section">
                <h3>🎯 Assegnazione Settori</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                    <strong>Primario:</strong> Assegna quiz e autovalutazione |
                    <strong>Secondario/Terziario:</strong> Suggerimenti per il coach
                </p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>🥇 Settore Primario <span class="sector-clear" data-target="sector_primary" title="Rimuovi">❌</span></label>
                        <select id="sector_primary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($primarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🥈 Settore Secondario <span class="sector-clear" data-target="sector_secondary" title="Rimuovi">❌</span></label>
                        <select id="sector_secondary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Nessuno --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($secondarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🥉 Settore Terziario <span class="sector-clear" data-target="sector_tertiary" title="Rimuovi">❌</span></label>
                        <select id="sector_tertiary" class="sector-select" data-userid="<?php echo $student->userid; ?>">
                            <option value="">-- Nessuno --</option>
                            <?php foreach ($allSectors as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($tertiarySector === $code) ? 'selected' : ''; ?>>
                                    <?php echo s($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="button" id="btn-save-sectors" class="cpurc-btn cpurc-btn-primary">
                        💾 Salva Settori
                    </button>
                    <span id="sector-save-status" style="margin-left: 15px; font-size: 13px;"></span>
                </div>

                <?php if (!empty($studentSectors)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <strong style="font-size: 12px;">Settori rilevati automaticamente (da quiz):</strong>
                    <div id="detected-sectors-list" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($studentSectors as $sec): ?>
                            <span class="sector-badge sector-<?php echo $sec->sector; ?>" style="display: inline-flex; align-items: center; gap: 5px;" data-sector="<?php echo $sec->sector; ?>">
                                <?php if ($sec->is_primary): ?>🥇<?php endif; ?>
                                <?php echo s(\local_ftm_cpurc\profession_mapper::get_sector_name($sec->sector)); ?>
                                <?php if ($sec->quiz_count > 0): ?>
                                    <small>(<?php echo $sec->quiz_count; ?> quiz)</small>
                                <?php endif; ?>
                                <span class="sector-delete-btn" data-sector="<?php echo $sec->sector; ?>" data-userid="<?php echo $student->userid; ?>" title="Elimina settore">❌</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <h3>Periodo Quadro</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Inizio</label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->framework_start); ?></div>
                    </div>
                    <div class="form-group">
                        <label>Fine</label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->framework_end); ?></div>
                    </div>
                    <div class="form-group">
                        <label>Indennita</label>
                        <div class="value-display"><?php echo $student->framework_allowance ?: '-'; ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('financier', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->financier ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('unemployment_fund', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo s($student->unemployment_fund ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label>Art. 59d</label>
                        <div class="value-display"><?php echo s($student->framework_art59d ?: '-'); ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'assenze'): ?>
            <!-- ASSENZE -->
            <div class="form-section">
                <h3>Riepilogo Assenze</h3>
                <div class="absences-grid">
                    <div class="absence-item">
                        <div class="code">X</div>
                        <div class="value"><?php echo (int)$student->absence_x; ?></div>
                        <div class="label">Malattia</div>
                    </div>
                    <div class="absence-item">
                        <div class="code">O</div>
                        <div class="value"><?php echo (int)$student->absence_o; ?></div>
                        <div class="label">Ingiustificata</div>
                    </div>
                    <div class="absence-item">
                        <div class="code">A</div>
                        <div class="value"><?php echo (int)$student->absence_a; ?></div>
                        <div class="label">Permesso</div>
                    </div>
                    <div class="absence-item">
                        <div class="code">B</div>
                        <div class="value"><?php echo (int)$student->absence_b; ?></div>
                        <div class="label">Colloquio</div>
                    </div>
                    <div class="absence-item">
                        <div class="code">C</div>
                        <div class="value"><?php echo (int)$student->absence_c; ?></div>
                        <div class="label">Corso</div>
                    </div>
                    <div class="absence-item absence-total">
                        <div class="code">TOT</div>
                        <div class="value"><?php echo (int)$student->absence_total; ?></div>
                        <div class="label">Totale</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Dettaglio Assenze (D-I)</h3>
                <div class="absences-grid">
                    <div class="absence-item">
                        <div class="code">D</div>
                        <div class="value"><?php echo (int)$student->absence_d; ?></div>
                    </div>
                    <div class="absence-item">
                        <div class="code">E</div>
                        <div class="value"><?php echo (int)$student->absence_e; ?></div>
                    </div>
                    <div class="absence-item">
                        <div class="code">F</div>
                        <div class="value"><?php echo (int)$student->absence_f; ?></div>
                    </div>
                    <div class="absence-item">
                        <div class="code">G</div>
                        <div class="value"><?php echo (int)$student->absence_g; ?></div>
                    </div>
                    <div class="absence-item">
                        <div class="code">H</div>
                        <div class="value"><?php echo (int)$student->absence_h; ?></div>
                    </div>
                    <div class="absence-item">
                        <div class="code">I</div>
                        <div class="value"><?php echo (int)$student->absence_i; ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Stage e Colloqui</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Colloqui di Assunzione</label>
                        <div class="value-display"><?php echo (int)$student->interviews; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Stage Svolti</label>
                        <div class="value-display"><?php echo (int)$student->stages_count; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Giorni di Stage</label>
                        <div class="value-display"><?php echo (int)$student->stage_days; ?></div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'stage'): ?>
            <!-- STAGE -->
            <?php if (!empty($student->stage_company_name)): ?>
            <div class="form-section">
                <h3>Dati Stage</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo get_string('stage_start', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->stage_start); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('stage_end', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->stage_end); ?></div>
                    </div>
                    <div class="form-group">
                        <label><?php echo get_string('stage_percentage', 'local_ftm_cpurc'); ?></label>
                        <div class="value-display"><?php echo $student->stage_percentage ? $student->stage_percentage . '%' : '-'; ?></div>
                    </div>
                </div>

                <div class="stage-card">
                    <h4>🏭 Azienda</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome Azienda</label>
                            <div class="value-display"><?php echo s($student->stage_company_name ?: '-'); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Indirizzo</label>
                            <div class="value-display">
                                <?php echo s($student->stage_company_street); ?><br>
                                <?php echo s($student->stage_company_cap); ?> <?php echo s($student->stage_company_city); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Funzione</label>
                            <div class="value-display"><?php echo s($student->stage_function ?: '-'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="stage-card">
                    <h4>👤 Contatto</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome</label>
                            <div class="value-display"><?php echo s($student->stage_contact_name ?: '-'); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Telefono</label>
                            <div class="value-display"><?php echo s($student->stage_contact_phone ?: '-'); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="value-display"><?php echo s($student->stage_contact_email ?: '-'); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($student->conclusion_date)): ?>
                <div class="stage-card">
                    <h4>✅ Conclusione</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Data</label>
                            <div class="value-display"><?php echo local_ftm_cpurc_format_date($student->conclusion_date); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Tipo</label>
                            <div class="value-display"><?php echo s($student->conclusion_type ?: '-'); ?></div>
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            <div class="value-display"><?php echo s($student->conclusion_reason ?: '-'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">🏭</div>
                <p>Nessun dato stage disponibile per questo studente.</p>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
// Clear sector dropdown buttons
document.querySelectorAll('.sector-clear').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const targetId = this.dataset.target;
        const select = document.getElementById(targetId);
        if (select) {
            select.value = '';
        }
    });
});

// Delete detected sector buttons
document.querySelectorAll('.sector-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const sector = this.dataset.sector;
        const userid = this.dataset.userid;
        const badge = this.closest('.sector-badge');

        if (!confirm('Eliminare il settore ' + sector + '?')) {
            return;
        }

        badge.style.opacity = '0.5';

        fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_delete_sector.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid + '&sector=' + encodeURIComponent(sector)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                badge.remove();
                // Also clear from dropdowns if selected
                ['sector_primary', 'sector_secondary', 'sector_tertiary'].forEach(function(id) {
                    const select = document.getElementById(id);
                    if (select && select.value === sector) {
                        select.value = '';
                    }
                });
            } else {
                badge.style.opacity = '1';
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            badge.style.opacity = '1';
            console.error('Error:', error);
        });
    });
});

// Clear coach dropdown button
document.querySelectorAll('.sector-clear[data-target="coach_select"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const select = document.getElementById('coach_select');
        if (select) {
            select.value = '';
        }
    });
});

// Save coach
document.getElementById('btn-save-coach')?.addEventListener('click', function() {
    const userid = document.getElementById('coach_select').dataset.userid;
    const coachid = document.getElementById('coach_select').value;

    const statusEl = document.getElementById('coach-save-status');
    const btn = this;
    const displayEl = document.getElementById('current-coach-display');

    btn.disabled = true;
    btn.innerHTML = '⏳ Salvataggio...';
    statusEl.innerHTML = '';

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_assign_coach.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid + '&coachid=' + coachid
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '💾 Salva Coach';

        if (data.success) {
            statusEl.innerHTML = '<span style="color: #28a745;">✅ ' + data.message + '</span>';

            // Update display
            if (data.coach) {
                displayEl.innerHTML = '<span class="coach-badge" style="background: #E0F2FE; color: #0369A1; padding: 4px 12px; border-radius: 15px; font-weight: 600;">' +
                    data.coach.name + '</span>' +
                    (data.coach.email ? '<small style="color: #666;">(' + data.coach.email + ')</small>' : '');
            } else {
                displayEl.innerHTML = '<span style="color: #999;">⚠️ Nessun coach assegnato</span>';
            }
        } else {
            statusEl.innerHTML = '<span style="color: #dc3545;">❌ ' + data.message + '</span>';
        }

        setTimeout(() => { statusEl.innerHTML = ''; }, 5000);
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '💾 Salva Coach';
        statusEl.innerHTML = '<span style="color: #dc3545;">❌ Errore di connessione</span>';
        console.error('Error:', error);
    });
});

// Save sectors
document.getElementById('btn-save-sectors')?.addEventListener('click', function() {
    const userid = document.getElementById('sector_primary').dataset.userid;
    const primary = document.getElementById('sector_primary').value;
    const secondary = document.getElementById('sector_secondary').value;
    const tertiary = document.getElementById('sector_tertiary').value;

    const statusEl = document.getElementById('sector-save-status');
    const btn = this;

    btn.disabled = true;
    btn.innerHTML = '⏳ Salvataggio...';
    statusEl.innerHTML = '';

    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_cpurc/ajax_save_sectors.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sesskey=<?php echo sesskey(); ?>&userid=' + userid +
              '&primary=' + encodeURIComponent(primary) +
              '&secondary=' + encodeURIComponent(secondary) +
              '&tertiary=' + encodeURIComponent(tertiary)
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '💾 Salva Settori';

        if (data.success) {
            statusEl.innerHTML = '<span style="color: #28a745;">✅ ' + data.message + '</span>';
        } else {
            statusEl.innerHTML = '<span style="color: #dc3545;">❌ ' + data.message + '</span>';
        }

        setTimeout(() => { statusEl.innerHTML = ''; }, 5000);
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '💾 Salva Settori';
        statusEl.innerHTML = '<span style="color: #dc3545;">❌ Errore di connessione</span>';
        console.error('Error:', error);
    });
});
</script>

<?php
echo $OUTPUT->footer();
