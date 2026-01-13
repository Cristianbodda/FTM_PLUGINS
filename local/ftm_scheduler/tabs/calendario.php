<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Calendario - IDENTICO al mockup 05_scheduler_gruppi_v3.html

defined('MOODLE_INTERNAL') || die();

// Variables passed from index.php:
// $week_dates, $calendar_data, $colors, $active_groups, $week, $year
?>

<!-- Legend -->
<div class="legend">
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
            <span>🟡 Giallo (esempio)</span>
        </div>
    <?php endif; ?>
    <div class="legend-item">
        <div class="legend-color" style="background: #2563EB; border: 2px dashed #2563EB;"></div>
        <span>🏢 Progetti Esterni</span>
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
            ?>
                <option value="<?php echo $group->id; ?>">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>
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
            <option value="week1">Attività Gruppo</option>
            <option value="atelier">Atelier</option>
            <option value="external">Progetti Esterni</option>
        </select>
    </div>
</div>

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
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'week' => $prev_week, 'year' => $prev_year]); ?>">
            ◀ Settimana prec.
        </a>
        <h3>📆 KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?> | <?php echo date('j', $week_dates[0]['timestamp']); ?>-<?php echo date('j', $week_dates[4]['timestamp']); ?> <?php echo local_ftm_scheduler_format_date($week_dates[0]['timestamp'], 'month'); ?> <?php echo $year; ?></h3>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'week' => $next_week, 'year' => $next_year]); ?>">
            Settimana succ. ▶
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario']); ?>" class="ftm-btn ftm-btn-sm ftm-btn-secondary">
            Oggi
        </a>
    </div>
    <div>
        <button class="ftm-btn ftm-btn-sm ftm-btn-secondary">📥 Export Excel</button>
    </div>
</div>

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
                    <div class="activity-block external" style="cursor: pointer;" onclick="ftmViewExternal(<?php echo $activity->id; ?>)">
                        <div class="activity-title">🏢 <?php echo $activity->project_name; ?></div>
                        <div class="activity-info">📍 <?php echo $activity->room_shortname ?? 'AULA'; ?> • <?php echo $activity->responsible; ?></div>
                    </div>
                <?php else: 
                    $group_color = $activity->group_color ?? 'giallo';
                    $color_info = $colors[$group_color] ?? $colors['giallo'];
                ?>
                    <div class="activity-block <?php echo $group_color; ?>" style="cursor: pointer;" onclick="ftmViewActivity(<?php echo $activity->id; ?>)">
                        <div class="activity-title">
                            <span class="activity-gruppo-dot dot-<?php echo $group_color; ?>"></span>
                            <?php echo $activity->name; ?>
                        </div>
                        <div class="activity-info">📍 <?php echo $activity->room_shortname ?? 'AULA 2'; ?> • 👤 Coach <?php echo $activity->teacher_initials ?? 'GM'; ?></div>
                        <div class="activity-info">👥 <?php echo $activity->enrolled_count ?? 0; ?>/<?php echo $activity->max_participants ?? 10; ?> iscritti</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php 
            // Show REMOTO for Wednesday if no activities
            if ($day['day_of_week'] === 3 && empty($day_activities)): 
                foreach ($active_groups as $group):
                    $color_info = $colors[$group->color] ?? $colors['giallo'];
            ?>
                <div class="remote-slot">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>: REMOTO
                </div>
            <?php 
                endforeach;
            endif; 
            ?>
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
                    <div class="activity-block external" style="cursor: pointer;" onclick="ftmViewExternal(<?php echo $activity->id; ?>)">
                        <div class="activity-title">🏢 <?php echo $activity->project_name; ?></div>
                        <div class="activity-info">📍 <?php echo $activity->room_shortname ?? 'AULA'; ?> • <?php echo $activity->responsible; ?></div>
                    </div>
                <?php else: 
                    $group_color = $activity->group_color ?? 'giallo';
                    $color_info = $colors[$group_color] ?? $colors['giallo'];
                ?>
                    <div class="activity-block <?php echo $group_color; ?>" style="cursor: pointer;" onclick="ftmViewActivity(<?php echo $activity->id; ?>)">
                        <div class="activity-title">
                            <span class="activity-gruppo-dot dot-<?php echo $group_color; ?>"></span>
                            <?php echo $activity->name; ?>
                        </div>
                        <div class="activity-info">📍 <?php echo $activity->room_shortname ?? 'AULA 2'; ?> • 👤 Coach <?php echo $activity->teacher_initials ?? 'GM'; ?></div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php 
            // Show REMOTO for Wednesday and Friday afternoon if no activities
            if (($day['day_of_week'] === 3 || $day['day_of_week'] === 5) && empty($day_activities)): 
                foreach ($active_groups as $group):
                    $color_info = $colors[$group->color] ?? $colors['giallo'];
            ?>
                <div class="remote-slot">
                    <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?>: REMOTO
                </div>
            <?php 
                endforeach;
            endif; 
            ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
// Helper function to get month name in Italian
function local_ftm_scheduler_format_date_month($timestamp) {
    $months = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
               'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    return $months[(int)date('n', $timestamp)];
}
