<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Attività - IDENTICO al mockup 05_scheduler_gruppi_v3.html

defined('MOODLE_INTERNAL') || die();

// Variables passed from index.php:
// $activities, $groups, $colors, $week
?>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Gruppo</label>
        <select id="filter-gruppo-att">
            <option value="">Tutti</option>
            <?php foreach ($groups as $group): 
                $color_info = $colors[$group->color] ?? $colors['giallo'];
            ?>
                <option value="<?php echo $group->id; ?>">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Settimana</label>
        <select id="filter-settimana">
            <option value="">Tutte</option>
            <option value="<?php echo $week; ?>" selected>KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?> (corrente)</option>
            <option value="<?php echo $week + 1; ?>">KW<?php echo str_pad($week + 1, 2, '0', STR_PAD_LEFT); ?></option>
            <option value="<?php echo $week + 2; ?>">KW<?php echo str_pad($week + 2, 2, '0', STR_PAD_LEFT); ?></option>
        </select>
    </div>
    <div class="filter-group">
        <label>Tipo</label>
        <select id="filter-tipo-att">
            <option value="">Tutti</option>
            <option value="week1">Settimana 1 (Fisse)</option>
            <option value="week2_test">Settimana 2 (Test)</option>
            <option value="atelier">Atelier</option>
            <option value="external">Esterni</option>
        </select>
    </div>
    <button class="ftm-btn ftm-btn-primary" style="align-self: flex-end;">📥 Export Excel</button>
</div>

<!-- Data Table -->
<table class="data-table">
    <thead>
        <tr>
            <th>Attività</th>
            <th>Gruppo</th>
            <th>Data/Ora</th>
            <th>Aula</th>
            <th>Docente</th>
            <th>Iscritti</th>
            <th>Tipo</th>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($activities)): ?>
            <tr>
                <td colspan="8" style="text-align: center; color: #999; padding: 40px;">
                    Nessuna attività trovata per i filtri selezionati
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($activities as $activity): 
                $color_info = $colors[$activity->group_color ?? 'giallo'] ?? $colors['giallo'];
                $is_external = $activity->activity_type === 'external';
                $row_style = $is_external ? 'background: #DBEAFE;' : '';
                
                // Format date
                $date_str = local_ftm_scheduler_format_date($activity->date_start, 'short');
                $time_start = date('H:i', $activity->date_start);
                $time_end = date('H:i', $activity->date_end);
                
                // Get room badge class
                $room_class = '';
                if ($activity->roomid) {
                    $room_class = 'aula-' . $activity->roomid;
                }
                
                // Get type label
                $type_labels = [
                    'week1' => 'Sett. 1',
                    'week2_test' => 'Sett. 2 Test',
                    'week2_lab' => 'Sett. 2 Lab',
                    'atelier' => 'Atelier',
                    'external' => 'Esterno',
                ];
                $type_label = $type_labels[$activity->activity_type] ?? $activity->activity_type;
            ?>
                <tr style="<?php echo $row_style; ?>">
                    <td>
                        <strong>
                            <?php if ($is_external): ?>
                                🏢 <?php echo $activity->name; ?>
                            <?php else: ?>
                                <?php echo $activity->name; ?>
                            <?php endif; ?>
                        </strong>
                    </td>
                    <td>
                        <?php if ($is_external): ?>
                            <span style="color: #666;">Esterno</span>
                        <?php else: ?>
                            <span class="gruppo-badge gruppo-<?php echo $activity->group_color; ?>">
                                <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $date_str; ?> • <?php echo $time_start; ?>-<?php echo $time_end; ?></td>
                    <td>
                        <?php if ($activity->room_shortname): ?>
                            <span class="aula-badge <?php echo $room_class; ?>"><?php echo $activity->room_shortname; ?></span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($activity->teacher_firstname): ?>
                            Coach <?php echo substr($activity->teacher_firstname, 0, 1) . substr($activity->teacher_lastname, 0, 1); ?>
                        <?php elseif ($is_external): ?>
                            <?php echo $activity->responsible ?? 'GM'; ?>
                        <?php else: ?>
                            Coach GM
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$is_external): ?>
                            <strong><?php echo $activity->enrolled_count ?? 0; ?></strong>/<?php echo $activity->max_participants ?? 10; ?>
                            <?php if (($activity->enrolled_count ?? 0) >= ($activity->max_participants ?? 10)): ?>
                                ✅
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo $type_label; ?></td>
                    <td>
                        <div class="quick-actions">
                            <a href="#" class="action-icon" onclick="ftmViewActivity(<?php echo $activity->id; ?>); return false;" title="Visualizza">👁</a>
                            <?php if ($is_external): ?>
                                <a href="#" class="action-icon" title="Elimina">🗑️</a>
                            <?php else: ?>
                                <a href="<?php echo new moodle_url('/local/ftm_scheduler/edit_activity.php', ['id' => $activity->id]); ?>" class="action-icon" title="Modifica">✏️</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
