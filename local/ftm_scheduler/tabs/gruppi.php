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
    <div style="display: flex; gap: 10px; align-self: flex-end;">
        <a href="<?php echo new moodle_url('/local/competencymanager/sector_admin.php'); ?>" class="ftm-btn ftm-btn-secondary">
            👥 Gestione Settori
        </a>
        <button class="ftm-btn ftm-btn-success" onclick="ftmOpenModal('newGruppo')">
            ➕ Nuovo Gruppo
        </button>
    </div>
</div>

<!-- Gruppi Grid -->
<div class="gruppi-grid" id="gruppi-grid">
    <?php foreach ($groups as $group):
        $color_info = $colors[$group->color] ?? $colors['giallo'];

        // Effective status computed from dates (DB status may be stale for manually-created groups).
        $now_ts = time();
        $effective_status = $group->status;
        if ($effective_status === 'planning' && $group->entry_date <= $now_ts) {
            $effective_status = 'active';
        }
        if ($effective_status === 'active' && $group->planned_end_date < $now_ts) {
            $effective_status = 'completed';
        }

        // Progress percentage based on elapsed time in the 6-week programme.
        $total_secs = $group->planned_end_date - $group->entry_date;
        $elapsed    = $now_ts - $group->entry_date;
        $progress   = ($total_secs > 0 && $effective_status === 'active')
            ? max(0, min(100, (int)round($elapsed / $total_secs * 100)))
            : ($effective_status === 'completed' ? 100 : 0);

        $opacity = $effective_status === 'planning' ? '0.7' : '1';
    ?>
        <div class="gruppo-card" data-status="<?php echo $effective_status; ?>" style="opacity: <?php echo $opacity; ?>;">
            <div class="gruppo-card-header <?php echo $group->color; ?>">
                <h3><?php echo $color_info['emoji']; ?> Gruppo <?php echo $color_info['name']; ?></h3>
                <span class="gruppo-week-badge">
                    <?php if ($effective_status === 'active'): ?>
                        Sett. 1 di 6
                    <?php elseif ($effective_status === 'planning'): ?>
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
                    <?php if ($effective_status === 'active'): ?>
                        <span class="status-badge status-active">Attivo</span>
                    <?php elseif ($effective_status === 'planning'): ?>
                        <span class="status-badge status-planning">In pianificazione</span>
                    <?php else: ?>
                        <span class="status-badge status-completed">Completato</span>
                    <?php endif; ?>
                </div>
                <div class="gruppo-detail">
                    <span>🎯 Fine prevista</span>
                    <strong><?php echo local_ftm_scheduler_format_date($group->planned_end_date, 'date_only'); ?></strong>
                </div>

                <?php if ($effective_status === 'active'): ?>
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
                <button class="ftm-btn ftm-btn-danger ftm-btn-sm"
                        style="margin-left:auto;"
                        onclick="ftmDeleteGruppo(<?php echo (int)$group->id; ?>, '<?php echo s($color_info['name']); ?>', <?php echo (int)($group->member_count ?? 0); ?>)"
                        title="Elimina gruppo">
                    🗑
                </button>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Placeholder per nuovo gruppo -->
    <div class="gruppo-card gruppo-card-new" style="border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; min-height: 300px; cursor: pointer;" onclick="ftmOpenModal('newGruppo')">
        <div style="text-align: center; color: #999;">
            <div style="font-size: 48px; margin-bottom: 10px;">➕</div>
            <div>Crea nuovo gruppo</div>
        </div>
    </div>
</div>

<script>
(function() {
    var filterStato = document.getElementById('filter-stato');
    if (!filterStato) return;

    function applyGruppiFilter() {
        var val = filterStato.value;
        document.querySelectorAll('#gruppi-grid .gruppo-card[data-status]').forEach(function(card) {
            card.style.display = (!val || card.dataset.status === val) ? '' : 'none';
        });
    }

    filterStato.addEventListener('change', applyGruppiFilter);
    applyGruppiFilter(); // apply on load (default = "active" is pre-selected)
})();

function ftmDeleteGruppo(groupid, colorName, memberCount) {
    var msg = 'Eliminare definitivamente il Gruppo ' + colorName + '?\n\n';
    if (memberCount > 0) {
        msg += '⚠️ Contiene ' + memberCount + ' studenti.\n\n';
    }
    msg += 'Verranno eliminati:\n• Tutti i membri del gruppo\n• Tutte le attività pianificate\n• Programmi e test studenti\n\nQuesta azione è IRREVERSIBILE.';

    if (!confirm(msg)) return;

    var btn = event.currentTarget;
    btn.disabled = true;
    btn.textContent = '⏳';

    fetch('<?php echo (new moodle_url('/local/ftm_scheduler/ajax_delete_activities.php'))->out(false); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete_group&groupid=' + groupid + '&sesskey=<?php echo sesskey(); ?>'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var card = btn.closest('.gruppo-card');
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(function() { card.remove(); }, 300);
            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            }
        } else {
            alert('Errore: ' + data.message);
            btn.disabled = false;
            btn.textContent = '🗑';
        }
    })
    .catch(function() {
        alert('Errore di rete. Riprova.');
        btn.disabled = false;
        btn.textContent = '🗑';
    });
}
</script>
