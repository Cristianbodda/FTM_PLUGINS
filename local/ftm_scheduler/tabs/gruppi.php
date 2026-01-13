<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Gruppi - IDENTICO al mockup 05_scheduler_gruppi_v3.html

defined('MOODLE_INTERNAL') || die();

// Variables passed from index.php:
// $groups, $active_groups, $colors
?>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Stato</label>
        <select id="filter-stato">
            <option value="">Tutti</option>
            <option value="active" selected>Attivi</option>
            <option value="completed">Completati</option>
            <option value="planning">In arrivo</option>
        </select>
    </div>
    <button class="ftm-btn ftm-btn-success" style="align-self: flex-end;" onclick="ftmOpenModal('newGruppo')">
        ➕ Nuovo Gruppo
    </button>
</div>

<!-- Gruppi Grid -->
<div class="gruppi-grid">
    <?php foreach ($groups as $group): 
        $color_info = $colors[$group->color] ?? $colors['giallo'];
        $progress = 17; // Placeholder - calcolare in base alla settimana corrente
        $is_future = $group->status === 'planning';
        $opacity = $is_future ? '0.7' : '1';
    ?>
        <div class="gruppo-card" style="opacity: <?php echo $opacity; ?>;">
            <div class="gruppo-card-header <?php echo $group->color; ?>">
                <h3><?php echo $color_info['emoji']; ?> Gruppo <?php echo $color_info['name']; ?></h3>
                <span class="gruppo-week-badge">
                    <?php if ($group->status === 'active'): ?>
                        Sett. 1 di 6
                    <?php elseif ($group->status === 'planning'): ?>
                        In arrivo
                    <?php else: ?>
                        Completato
                    <?php endif; ?>
                </span>
            </div>
            <div class="gruppo-card-body">
                <div class="gruppo-detail">
                    <span>📅 Data ingresso</span>
                    <strong><?php echo local_ftm_scheduler_format_date($group->entry_date, 'date_only'); ?> (KW<?php echo str_pad($group->calendar_week, 2, '0', STR_PAD_LEFT); ?>)</strong>
                </div>
                <div class="gruppo-detail">
                    <span>👥 Studenti</span>
                    <strong><?php echo $group->member_count ?? 0; ?> / 10</strong>
                </div>
                <div class="gruppo-detail">
                    <span>📊 Stato</span>
                    <?php if ($group->status === 'active'): ?>
                        <span class="status-badge status-active">Attivo</span>
                    <?php elseif ($group->status === 'planning'): ?>
                        <span class="status-badge status-planning">In pianificazione</span>
                    <?php else: ?>
                        <span class="status-badge status-completed">Completato</span>
                    <?php endif; ?>
                </div>
                <div class="gruppo-detail">
                    <span>🎯 Fine prevista</span>
                    <strong><?php echo local_ftm_scheduler_format_date($group->planned_end_date, 'date_only'); ?></strong>
                </div>
                
                <?php if ($group->status === 'active'): ?>
                <div class="gruppo-progress">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
                        <span>Progresso</span>
                        <span><?php echo $progress; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $group->color; ?>" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="gruppo-card-footer">
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/group.php', ['id' => $group->id]); ?>" class="ftm-btn ftm-btn-secondary ftm-btn-sm">
                    👁 Dettagli
                </a>
                <a href="<?php echo new moodle_url('/local/ftm_scheduler/members.php', ['groupid' => $group->id]); ?>" class="ftm-btn ftm-btn-primary ftm-btn-sm">
                    👥 Studenti
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Placeholder per nuovo gruppo -->
    <div class="gruppo-card" style="border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; min-height: 300px; cursor: pointer;" onclick="ftmOpenModal('newGruppo')">
        <div style="text-align: center; color: #999;">
            <div style="font-size: 48px; margin-bottom: 10px;">➕</div>
            <div>Crea nuovo gruppo</div>
        </div>
    </div>
</div>
