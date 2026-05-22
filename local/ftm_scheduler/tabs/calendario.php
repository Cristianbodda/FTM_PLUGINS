<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Calendario - Vista Settimana e Mese

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Variables passed from index.php:
// $week_dates, $calendar_data, $colors, $active_groups, $week, $year, $month
// $view, $month_weeks, $month_activities, $month_names, $current_month_name
?>

<!-- Legend -->
<div class="legend" style="display:none">
    <span style="font-weight: 600; margin-right: 10px;">Legenda:</span>
    <?php foreach ($active_groups as $group):
        $color_info = $colors[$group->color] ?? $colors['giallo'];
    ?>
        <div class="legend-item">
            <div class="legend-color" style="background: <?php echo $color_info['hex']; ?>; <?php echo $group->color === 'giallo' ? 'border: 1px solid #ccc;' : ''; ?>"></div>
            <span><?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?> (Sett. 1)</span>
        </div>
    <?php endforeach; ?>
    <?php if (empty($active_groups)): ?>
        <div class="legend-item">
            <div class="legend-color" style="background: #FFFF00; border: 1px solid #ccc;"></div>
            <span>Giallo (esempio)</span>
        </div>
    <?php endif; ?>
    <div class="legend-item">
        <div class="legend-color" style="background: #2563EB; border: 2px dashed #2563EB;"></div>
        <span>Progetti Esterni</span>
    </div>
</div>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Gruppo</label>
        <select id="filter-gruppo">
            <option value="">Tutti i gruppi</option>
            <?php foreach ($groups as $group):
                $color_info = $colors[$group->color] ?? $colors['giallo'];
                $group_kw = $group->calendar_week ?? '';
                $kw_label = $group_kw ? ' - KW' . str_pad($group_kw, 2, '0', STR_PAD_LEFT) : '';
            ?>
                <option value="<?php echo $group->id; ?>">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?><?php echo $kw_label; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Aula</label>
        <select id="filter-aula">
            <option value="">Tutte le aule</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?php echo $room->id; ?>">
                    <?php echo $room->name; ?> (<?php echo $room->capacity; ?> post.)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Tipo</label>
        <select id="filter-tipo">
            <option value="">Tutti i tipi</option>
            <option value="week1">Attivita Gruppo</option>
            <option value="atelier">Atelier</option>
            <option value="external">Progetti Esterni</option>
        </select>
    </div>

    <!-- View Toggle -->
    <div style="margin-left: auto;">
        <label style="display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 5px;">Vista</label>
        <div class="view-toggle">
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $week, 'year' => $year]); ?>"
               class="view-toggle-btn <?php echo $view === 'week' ? 'active' : ''; ?>">
                Settimana
            </a>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $month, 'year' => $year]); ?>"
               class="view-toggle-btn <?php echo $view === 'month' ? 'active' : ''; ?>">
                Mese
            </a>
        </div>
    </div>
</div>

<?php if ($view === 'week'): ?>
<!-- ======================= WEEK VIEW ======================= -->

<!-- Calendar Header -->
<div class="calendar-header">
    <div class="week-nav">
        <?php
        $prev_week = $week - 1;
        $prev_year = $year;
        if ($prev_week < 1) {
            $prev_week = 52;
            $prev_year--;
        }
        $next_week = $week + 1;
        $next_year = $year;
        if ($next_week > 52) {
            $next_week = 1;
            $next_year++;
        }
        ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $prev_week, 'year' => $prev_year]); ?>">
            Settimana prec.
        </a>
        <h3>KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?> | <?php echo date('j', $week_dates[0]['timestamp']); ?>-<?php echo date('j', $week_dates[4]['timestamp']); ?> <?php echo local_ftm_scheduler_format_date($week_dates[0]['timestamp'], 'month'); ?> <?php echo $year; ?></h3>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $next_week, 'year' => $next_year]); ?>">
            Settimana succ.
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week']); ?>" class="ftm-btn ftm-btn-sm ftm-btn-secondary">
            Oggi
        </a>
        <!-- Quick Jump to Week 1 -->
        <?php if ($week != 1 || $year != date('Y')): ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => 1, 'year' => date('Y')]); ?>" class="ftm-btn ftm-btn-sm ftm-btn-primary">
            KW01 <?php echo date('Y'); ?>
        </a>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <!-- Year Selector -->
        <select onchange="ftmChangeWeekYear(this.value)" style="padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 13px;">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button class="ftm-btn ftm-btn-sm ftm-btn-secondary">Export Excel</button>
    </div>
</div>

<script>
function ftmChangeWeekYear(selectedYear) {
    var week = <?php echo $week; ?>;
    // If changing year, check if week is valid (some years have 52 or 53 weeks)
    if (week > 52) week = 52;
    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php?tab=calendario&view=week&week=' + week + '&year=' + selectedYear;
}
</script>

<!-- Calendar Grid -->
<div class="calendar-grid">
    <!-- Headers -->
    <div class="calendar-time-header">Orario</div>
    <?php foreach ($week_dates as $day): ?>
        <div class="calendar-day-header">
            <?php echo $day['day_name']; ?><br>
            <?php if ($day['day_of_week'] === 1 && !empty($active_groups)): ?>
                <small>Inizio Gruppo</small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Mattina -->
    <div class="time-slot-label">
        <strong>MATTINA</strong>
        <span>08:30</span>
        <span>11:45</span>
    </div>

    <?php foreach ($week_dates as $day): ?>
        <div class="calendar-cell">
            <?php
            $day_activities = $calendar_data[$day['day_of_week']]['matt'] ?? [];
            foreach ($day_activities as $activity):
                if (!empty($activity->is_external)):
            ?>
                    <div class="activity-block external"
                         style="cursor: pointer;"
                         onclick="ftmViewExternal(<?php echo $activity->id; ?>)"
                         data-groupid=""
                         data-roomid="<?php echo $activity->roomid ?? ''; ?>"
                         data-type="external">
                        <div class="activity-title"><?php echo $activity->project_name; ?></div>
                        <div class="activity-info"><?php echo $activity->room_shortname ?? 'AULA'; ?> - <?php echo $activity->responsible; ?></div>
                    </div>
                <?php else:
                    $group_color = $activity->group_color ?? 'neutro';
                    $color_info = $colors[$group_color] ?? ['emoji' => '⬜', 'name' => ''];
                    $activity_type = !empty($activity->is_atelier) ? 'atelier' : 'week1';
                ?>
                    <?php
                    $coach_initials = '';
                    if (!empty($activity->teacher_firstname) || !empty($activity->teacher_lastname)) {
                        $coach_initials = strtoupper(
                            substr($activity->teacher_firstname ?? '', 0, 1) .
                            substr($activity->teacher_lastname  ?? '', 0, 1)
                        );
                    }
                    ?>
                    <div class="activity-block <?php echo $group_color; ?>"
                         style="cursor: pointer;"
                         onclick="ftmViewActivity(<?php echo $activity->id; ?>)"
                         data-groupid="<?php echo $activity->groupid ?? ''; ?>"
                         data-roomid="<?php echo $activity->roomid ?? ''; ?>"
                         data-type="<?php echo $activity_type; ?>">
                        <div class="activity-title">
                            <span class="activity-gruppo-dot dot-<?php echo $group_color; ?>"></span>
                            <?php echo $activity->name; ?>
                        </div>
                        <div class="activity-info">
                            <?php echo $activity->room_shortname ?? ''; ?>
                            <?php if ($coach_initials): ?> - Coach <?php echo $coach_initials; ?><?php endif; ?>
                        </div>
                        <div class="activity-info"><?php echo $activity->enrolled_count ?? 0; ?>/<?php echo $activity->max_participants ?? 10; ?> iscritti</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Pomeriggio -->
    <div class="time-slot-label">
        <strong>POMERIGGIO</strong>
        <span>13:15</span>
        <span>16:30</span>
    </div>

    <?php foreach ($week_dates as $day): ?>
        <div class="calendar-cell">
            <?php
            $day_activities = $calendar_data[$day['day_of_week']]['pom'] ?? [];
            foreach ($day_activities as $activity):
                if (!empty($activity->is_external)):
            ?>
                    <div class="activity-block external"
                         style="cursor: pointer;"
                         onclick="ftmViewExternal(<?php echo $activity->id; ?>)"
                         data-groupid=""
                         data-roomid="<?php echo $activity->roomid ?? ''; ?>"
                         data-type="external">
                        <div class="activity-title"><?php echo $activity->project_name; ?></div>
                        <div class="activity-info"><?php echo $activity->room_shortname ?? 'AULA'; ?> - <?php echo $activity->responsible; ?></div>
                    </div>
                <?php else:
                    $group_color = $activity->group_color ?? 'neutro';
                    $color_info = $colors[$group_color] ?? ['emoji' => '⬜', 'name' => ''];
                    $activity_type = !empty($activity->is_atelier) ? 'atelier' : 'week1';
                ?>
                    <?php
                    $coach_initials_pom = '';
                    if (!empty($activity->teacher_firstname) || !empty($activity->teacher_lastname)) {
                        $coach_initials_pom = strtoupper(
                            substr($activity->teacher_firstname ?? '', 0, 1) .
                            substr($activity->teacher_lastname  ?? '', 0, 1)
                        );
                    }
                    ?>
                    <div class="activity-block <?php echo $group_color; ?>"
                         style="cursor: pointer;"
                         onclick="ftmViewActivity(<?php echo $activity->id; ?>)"
                         data-groupid="<?php echo $activity->groupid ?? ''; ?>"
                         data-roomid="<?php echo $activity->roomid ?? ''; ?>"
                         data-type="<?php echo $activity_type; ?>">
                        <div class="activity-title">
                            <span class="activity-gruppo-dot dot-<?php echo $group_color; ?>"></span>
                            <?php echo $activity->name; ?>
                        </div>
                        <div class="activity-info">
                            <?php echo $activity->room_shortname ?? ''; ?>
                            <?php if ($coach_initials_pom): ?> - Coach <?php echo $coach_initials_pom; ?><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
// ---- Room Occupancy Grid (week view only) ----
// Build matrix: $room_matrix[$roomid][$day_of_week][$slot] = activity|booking
$room_matrix = [];
foreach ($week_dates as $wday) {
    foreach (['matt', 'pom'] as $slot) {
        foreach ($calendar_data[$wday['day_of_week']][$slot] ?? [] as $act) {
            $rid = (int)($act->roomid ?? 0);
            if ($rid > 0) {
                $room_matrix[$rid][$wday['day_of_week']][$slot] = $act;
            }
        }
    }
}
?>

<div class="room-occ-section">
    <div class="room-occ-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? '' : 'none'; this.querySelector('.room-occ-toggle').textContent = this.nextElementSibling.style.display === 'none' ? '▼ Mostra' : '▲ Nascondi';">
        <h4>🏫 Occupazione Aule — KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?></h4>
        <span class="room-occ-toggle">▲ Nascondi</span>
    </div>
    <div class="room-occ-body">
        <table class="room-occ-table">
            <thead>
                <tr>
                    <th class="room-col" rowspan="2">Aula</th>
                    <?php foreach ($week_dates as $wday): ?>
                        <th colspan="2"><?php echo $wday['day_name']; ?><br><small style="font-weight:400"><?php echo date('j/n', $wday['timestamp']); ?></small></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($week_dates as $wday): ?>
                        <th class="slot-th">Matt</th>
                        <th class="slot-th">Pom</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td class="room-label">
                        <?php echo s($room->shortname ?: $room->name); ?>
                        <small><?php if ($room->shortname && $room->name !== $room->shortname) echo s($room->name); ?></small>
                    </td>
                    <?php foreach ($week_dates as $wday): ?>
                        <?php foreach (['matt', 'pom'] as $slot): ?>
                            <?php $act = $room_matrix[$room->id][$wday['day_of_week']][$slot] ?? null; ?>
                            <td>
                            <?php if ($act): ?>
                                <?php if (!empty($act->is_external)): ?>
                                    <div class="room-occ-activity ext"
                                         onclick="ftmViewExternal(<?php echo (int)$act->id; ?>)"
                                         style="cursor:pointer" title="Clicca per vedere/modificare">
                                        <span class="room-occ-name"><?php echo s($act->project_name); ?></span>
                                        <span class="room-occ-coach"><?php echo s($act->responsible); ?></span>
                                    </div>
                                <?php else:
                                    $color = $act->group_color ?? 'neutro';
                                    $initials = '';
                                    if (!empty($act->teacher_firstname) || !empty($act->teacher_lastname)) {
                                        $initials = strtoupper(
                                            substr($act->teacher_firstname ?? '', 0, 1) .
                                            substr($act->teacher_lastname  ?? '', 0, 1)
                                        );
                                    }
                                ?>
                                    <div class="room-occ-activity <?php echo $color; ?>"
                                         onclick="ftmViewActivity(<?php echo (int)$act->id; ?>)"
                                         style="cursor:pointer" title="Clicca per vedere/modificare">
                                        <span class="room-occ-name"><?php echo s($act->name); ?></span>
                                        <?php if ($initials || !empty($act->group_name)): ?>
                                        <span class="room-occ-coach">
                                            <?php if ($initials) echo $initials; ?>
                                            <?php if (!empty($act->group_name)) echo ' · ' . s($act->group_name); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else:
                                $pre_date = date('Y-m-d', $wday['timestamp']);
                            ?>
                                <div class="room-occ-cell free"
                                     onclick="ftmQuickCreate(<?php echo (int)$room->id; ?>, '<?php echo $pre_date; ?>', '<?php echo $slot; ?>')"
                                     style="cursor:pointer;text-align:center;line-height:36px;font-size:18px;color:#ccc"
                                     title="Crea attività — <?php echo s($room->shortname ?: $room->name); ?>, <?php echo date('d/m', $wday['timestamp']); ?> <?php echo $slot === 'matt' ? 'Mattina' : 'Pomeriggio'; ?>">
                                    +
                                </div>
                            <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ======================= MONTH VIEW ======================= -->

<!-- Calendar Header -->
<div class="calendar-header">
    <div class="week-nav">
        <?php
        $prev_month = $month - 1;
        $prev_month_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_month_year--;
        }
        $next_month = $month + 1;
        $next_month_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_month_year++;
        }
        ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $prev_month, 'year' => $prev_month_year]); ?>">
            Mese prec.
        </a>
        <h3><?php echo $current_month_name; ?> <?php echo $year; ?></h3>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $next_month, 'year' => $next_month_year]); ?>">
            Mese succ.
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month']); ?>" class="ftm-btn ftm-btn-sm ftm-btn-secondary">
            Mese Corrente
        </a>
        <!-- Quick Jump to January -->
        <?php if ($month != 1 || $year != date('Y')): ?>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => 1, 'year' => date('Y')]); ?>" class="ftm-btn ftm-btn-sm ftm-btn-primary">
            Gennaio <?php echo date('Y'); ?>
        </a>
        <?php endif; ?>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <!-- Year Selector -->
        <select onchange="ftmChangeYear(this.value)" style="padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 13px;">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button class="ftm-btn ftm-btn-sm ftm-btn-secondary">Export Excel</button>
    </div>
</div>

<script>
function ftmChangeYear(selectedYear) {
    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php?tab=calendario&view=month&month=<?php echo $month; ?>&year=' + selectedYear;
}
</script>

<!-- Month Grid -->
<div class="month-grid">
    <!-- Day Headers -->
    <div class="month-day-header"></div>
    <div class="month-day-header">Lunedi</div>
    <div class="month-day-header">Martedi</div>
    <div class="month-day-header">Mercoledi</div>
    <div class="month-day-header">Giovedi</div>
    <div class="month-day-header">Venerdi</div>

    <?php foreach ($month_weeks as $week_info): ?>
        <!-- Week Label -->
        <div class="month-week-label">
            <div class="kw">KW<?php echo str_pad($week_info['week_num'], 2, '0', STR_PAD_LEFT); ?></div>
            <div class="dates">
                <?php echo date('j/n', $week_info['monday_ts']); ?> - <?php echo date('j/n', $week_info['friday_ts']); ?>
            </div>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $week_info['week_num'], 'year' => $week_info['year']]); ?>"
               style="font-size: 10px; color: #0066cc; margin-top: 5px;">Dettagli</a>
        </div>

        <?php foreach ($week_info['days'] as $day):
            $day_of_week = $day['day_of_week'];
            $week_num = $week_info['week_num'];

            // Get activities for this day
            $day_activities_matt = $month_activities[$week_num][$day_of_week]['matt'] ?? [];
            $day_activities_pom = $month_activities[$week_num][$day_of_week]['pom'] ?? [];
            $all_activities = array_merge($day_activities_matt, $day_activities_pom);

            $cell_class = $day['is_current_month'] ? '' : 'other-month';
        ?>
            <div class="month-cell <?php echo $cell_class; ?>">
                <span class="day-num"><?php echo $day['day_num']; ?></span>

                <?php
                $shown = 0;
                $max_shown = 4;

                foreach ($all_activities as $activity):
                    if ($shown >= $max_shown) break;
                    $shown++;

                    if (!empty($activity->is_external)):
                ?>
                        <div class="month-activity-mini external" onclick="ftmViewExternal(<?php echo $activity->id; ?>)">
                            <?php echo substr($activity->project_name, 0, 15); ?>
                        </div>
                    <?php else:
                        $group_color = $activity->group_color ?? 'neutro';
                    ?>
                        <div class="month-activity-mini <?php echo $group_color; ?>" onclick="ftmViewActivity(<?php echo $activity->id; ?>)">
                            <?php echo substr($activity->name, 0, 15); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (count($all_activities) > $max_shown): ?>
                    <div class="month-more-link" onclick="ftmGoToWeek(<?php echo $week_info['week_num']; ?>, <?php echo $week_info['year']; ?>)">
                        +<?php echo count($all_activities) - $max_shown; ?> altre...
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<script>
function ftmGoToWeek(week, year) {
    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php?tab=calendario&view=week&week=' + week + '&year=' + year;
}
</script>

<?php endif; ?>

<!-- Filter JavaScript -->
<script>
(function() {
    'use strict';

    // Filter elements
    const filterGruppo = document.getElementById('filter-gruppo');
    const filterAula = document.getElementById('filter-aula');
    const filterTipo = document.getElementById('filter-tipo');

    // Apply filters function
    function applyFilters() {
        const gruppoValue = filterGruppo ? filterGruppo.value : '';
        const aulaValue = filterAula ? filterAula.value : '';
        const tipoValue = filterTipo ? filterTipo.value : '';

        // Get all activity blocks
        const activityBlocks = document.querySelectorAll('.activity-block');
        let visibleCount = 0;
        let hiddenCount = 0;

        activityBlocks.forEach(function(block) {
            const blockGroupId = block.getAttribute('data-groupid') || '';
            const blockRoomId = block.getAttribute('data-roomid') || '';
            const blockType = block.getAttribute('data-type') || '';

            let show = true;

            // Filter by gruppo
            if (gruppoValue && blockGroupId !== gruppoValue) {
                show = false;
            }

            // Filter by aula
            if (aulaValue && blockRoomId !== aulaValue) {
                show = false;
            }

            // Filter by tipo
            if (tipoValue && blockType !== tipoValue) {
                show = false;
            }

            // Apply visibility
            if (show) {
                block.style.display = '';
                visibleCount++;
            } else {
                block.style.display = 'none';
                hiddenCount++;
            }
        });

        // Also filter month view activities if present
        const monthActivities = document.querySelectorAll('.month-activity-mini');
        monthActivities.forEach(function(block) {
            const blockType = block.classList.contains('external') ? 'external' :
                              (block.classList.contains('atelier') ? 'atelier' : 'week1');

            let show = true;

            // Filter by tipo only for month view (no data attributes available)
            if (tipoValue && blockType !== tipoValue) {
                show = false;
            }

            if (show) {
                block.style.display = '';
            } else {
                block.style.display = 'none';
            }
        });

        // Update filter status indicator
        updateFilterStatus(gruppoValue, aulaValue, tipoValue, visibleCount, hiddenCount);
    }

    // Show filter status
    function updateFilterStatus(gruppo, aula, tipo, visible, hidden) {
        let statusEl = document.getElementById('filter-status');

        if (!statusEl) {
            statusEl = document.createElement('div');
            statusEl.id = 'filter-status';
            statusEl.style.cssText = 'margin-left: 15px; font-size: 12px; color: #666; display: flex; align-items: center; gap: 10px;';

            const filtersDiv = document.querySelector('.filters');
            if (filtersDiv) {
                // Insert before view toggle
                const viewToggle = filtersDiv.querySelector('[style*="margin-left: auto"]');
                if (viewToggle) {
                    filtersDiv.insertBefore(statusEl, viewToggle);
                } else {
                    filtersDiv.appendChild(statusEl);
                }
            }
        }

        const hasFilters = gruppo || aula || tipo;

        if (hasFilters) {
            statusEl.innerHTML = '<span style="background: #DBEAFE; color: #1E40AF; padding: 4px 10px; border-radius: 15px; font-weight: 500;">' +
                                 visible + ' attivita visibili</span>' +
                                 (hidden > 0 ? '<span style="color: #999;">(' + hidden + ' nascoste)</span>' : '') +
                                 '<button onclick="ftmResetFilters()" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 12px; text-decoration: underline;">Reset filtri</button>';
        } else {
            statusEl.innerHTML = '';
        }
    }

    // Attach event listeners
    if (filterGruppo) {
        filterGruppo.addEventListener('change', applyFilters);
    }
    if (filterAula) {
        filterAula.addEventListener('change', applyFilters);
    }
    if (filterTipo) {
        filterTipo.addEventListener('change', applyFilters);
    }

    // Reset filters function (global)
    window.ftmResetFilters = function() {
        if (filterGruppo) filterGruppo.value = '';
        if (filterAula) filterAula.value = '';
        if (filterTipo) filterTipo.value = '';
        applyFilters();
    };

    // Apply filters on page load if any are set (e.g., from URL or browser cache)
    document.addEventListener('DOMContentLoaded', function() {
        const hasPresetFilters = (filterGruppo && filterGruppo.value) ||
                                  (filterAula && filterAula.value) ||
                                  (filterTipo && filterTipo.value);
        if (hasPresetFilters) {
            applyFilters();
        }
    });
})();
</script>
