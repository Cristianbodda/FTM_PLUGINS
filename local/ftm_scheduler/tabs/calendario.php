<?php
/**
 * Tab Calendario — Vista Unificata (Tabella Excel), Settimana e Mese
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG, $DB;

// Variables passed from index.php:
// $week_dates, $calendar_data, $colors, $active_groups, $groups, $rooms, $week, $year, $month
// $view, $month_weeks, $month_activities, $month_names, $current_month_name, $manager

// Load coaches if not already loaded
if (!isset($coaches)) {
    $coaches = $manager::get_coaches();
}

// ---------- Room matrix (week) ----------
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

// ---------- Coach / Secretary colors ----------
$coach_personal_colors = [
    'CB' => ['bg' => '#00B0F0', 'text' => '#000'],
    'FM' => ['bg' => '#FFFF00', 'text' => '#333'],
    'GM' => ['bg' => '#00B050', 'text' => '#fff'],
    'RB' => ['bg' => '#FF0000', 'text' => '#fff'],
    'LP' => ['bg' => '#CC00FF', 'text' => '#fff'],
    'DB' => ['bg' => '#FFA500', 'text' => '#000'],
];
$secretary_colors = [
    'SANDRA' => ['bg' => '#00B0F0', 'text' => '#000'],
    'ALE'    => ['bg' => '#FF9800', 'text' => '#000'],
];
$secretaries = ['SANDRA', 'ALE'];

// ---------- Load coaches count ----------
$n_coaches = count($coaches);

// ---------- Load daily status + extra activities for visible range ----------
$initial_status_map = [];
$extra_map = [];
if ($view === 'week') {
    $range_start = $week_dates[0]['timestamp'];
    $range_end   = $week_dates[4]['timestamp'] + 86399;
} elseif (!empty($month_weeks)) {
    $range_start = $month_weeks[0]['monday_ts'];
    $last_mw     = end($month_weeks);
    $range_end   = $last_mw['friday_ts'] + 86399;
}
if (isset($range_start)) {
    if ($DB->get_manager()->table_exists('local_ftm_daily_status')) {
        $status_records = $DB->get_records_select(
            'local_ftm_daily_status',
            'date_day >= ? AND date_day <= ?',
            [$range_start, $range_end]
        );
        foreach ($status_records as $rec) {
            $dkey = date('Y-m-d', $rec->date_day);
            $initial_status_map[$rec->actor_name . '|' . $dkey . '|' . $rec->slot] = $rec->status;
        }
    }
    if ($DB->get_manager()->table_exists('local_ftm_extra_activities')) {
        $extra_records = $DB->get_records_select(
            'local_ftm_extra_activities',
            'date_day >= ? AND date_day <= ?',
            [$range_start, $range_end]
        );
        foreach ($extra_records as $rec) {
            $extra_map[date('Y-m-d', $rec->date_day) . '|' . $rec->slot] = $rec->notes;
        }
    }
}

// ---------- JS data maps ----------
$js_rooms = [];
foreach ($rooms as $r) {
    $js_rooms[(int)$r->id] = ['id' => (int)$r->id, 'name' => ($r->shortname ?: $r->name), 'fullname' => $r->name];
}

$js_groups = [];
foreach ($groups as $g) {
    $ci = $colors[$g->color] ?? $colors['giallo'];
    $kw = $g->calendar_week ? ' KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
    $js_groups[(int)$g->id] = ['id' => (int)$g->id, 'name' => $ci['emoji'] . ' ' . $ci['name'] . $kw, 'color' => $g->color];
}

$js_coaches_map = [];
foreach ($coaches as $c) {
    $js_coaches_map[(int)$c->userid] = ['id' => (int)$c->userid, 'name' => $c->firstname . ' ' . $c->lastname, 'initials' => $c->initials];
}
?>

<style>
/* ===== Unified calendar table ===== */
.cal-unified {
    border-collapse: collapse;
    font-size: 11px;
    min-width: 1100px;
    width: 100%;
}
.cal-unified th, .cal-unified td {
    border: 1px solid #dee2e6;
    padding: 4px 5px;
    vertical-align: middle;
}
.cu-sect {
    text-align: center;
    font-size: 10px;
    font-weight: 700;
    padding: 5px 3px;
    letter-spacing: .3px;
}
.cu-sect-sw   { background: #E0F7FA; color: #006064; }
.cu-sect-ass  { background: #FFEBEE; color: #B71C1C; }
.cu-sect-room { background: #E8F5E9; color: #1B5E20; }
.cu-day { background: #f8f9fa; min-width: 60px; text-align: center; font-weight: 700; font-size: 10px; }
.cu-actor { font-size: 10px; font-weight: 700; text-align: center; min-width: 26px; padding: 3px 2px; }
.cu-sub { background: #fafafa; font-size: 10px; color: #666; text-align: center; }
.cu-day-cell {
    background: #f8f9fa; text-align: center; font-size: 11px;
    border-bottom: 2px solid #dee2e6;
    min-width: 70px;
}
.cu-slot-cell {
    font-size: 10px; font-weight: 700; text-align: center;
    white-space: nowrap; min-width: 46px;
    padding: 4px 3px;
}
.cu-slot-cell.matt { background: #FFFDE7; color: #795548; }
.cu-slot-cell.pom  { background: #E3F2FD; color: #0D47A1; }
.cu-row-matt { background: #FAFAFA; }
.cu-row-pom  { background: #F5F5F5; }
.cu-status-cell {
    text-align: center; cursor: pointer; min-width: 26px;
    transition: background .1s;
}
.cu-status-cell:hover { background: rgba(0,0,0,0.05); }
.ds-badge {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.4;
    white-space: nowrap;
}
.cu-room-chi { text-align: center; cursor: pointer; min-width: 30px; }
.cu-room-att { font-size: 10px; cursor: pointer; min-width: 80px; max-width: 120px; }
.cu-room-att:hover { background: rgba(0,102,204,0.05); }
.cu-room-free { text-align: center; color: #ccc; cursor: pointer; font-size: 18px; }
.cu-room-free:hover { background: #f0f7ff; color: #0066cc !important; }
.cu-extra { font-size: 10px; cursor: pointer; min-width: 80px; max-width: 140px; }
.cu-extra:hover { background: rgba(0,0,0,0.03); }
.cu-grp-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    vertical-align: middle; margin-top: -1px;
}
.cu-grp-dot.giallo  { background: #EAB308; }
.cu-grp-dot.grigio  { background: #6B7280; }
.cu-grp-dot.rosso   { background: #EF4444; }
.cu-grp-dot.marrone { background: #92400E; }
.cu-grp-dot.viola   { background: #7C3AED; }

/* ===== Popover editor (room grid) ===== */
#ce-pop {
    display: none;
    position: fixed;
    z-index: 20000;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: 0 8px 28px rgba(0,0,0,.18);
    padding: 16px;
    min-width: 270px;
    max-width: 340px;
}
#ce-pop .ce-pop-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
#ce-pop .ce-pop-title { font-weight: 600; font-size: 13px; }
#ce-pop .ce-pop-close {
    background: none; border: none; font-size: 20px;
    cursor: pointer; color: #666; line-height: 1; padding: 0;
}
#ce-pop .ce-pop-field { margin-bottom: 10px; }
#ce-pop .ce-pop-field label {
    display: block; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px;
    color: #666; margin-bottom: 3px;
}
#ce-pop .ce-pop-field input,
#ce-pop .ce-pop-field select {
    width: 100%; padding: 7px 10px; border: 1px solid #dee2e6;
    border-radius: 6px; font-size: 13px;
}
#ce-pop .ce-pop-field input:focus,
#ce-pop .ce-pop-field select:focus {
    outline: none; border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,.1);
}
.ce-pop-saved { color: #28a745; font-size: 11px; font-weight: 600; }

/* ===== Legacy week/aule styles kept for month view compatibility ===== */
.act-block {
    padding: 8px 10px; border-radius: 6px; font-size: 11px; margin-bottom: 5px;
    border-left: 4px solid; background: #F8FAFC; border-left-color: #94A3B8; position: relative;
}
.act-block.external { background:#DBEAFE; border-left-color:#2563EB; border-left-style:dashed; }
.month-activity-mini {
    font-size: 10px; border-radius: 3px; padding: 1px 4px;
    margin-bottom: 2px; cursor: pointer; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
    background: #f1f5f9; color: #334155;
}
.month-activity-mini.giallo  { background:#FEF9C3; color:#854D0E; }
.month-activity-mini.grigio  { background:#F3F4F6; color:#374151; }
.month-activity-mini.rosso   { background:#FEE2E2; color:#991B1B; }
.month-activity-mini.marrone { background:#FED7AA; color:#7C2D12; }
.month-activity-mini.viola   { background:#F3E8FF; color:#5B21B6; }
.month-activity-mini.external{ background:#DBEAFE; color:#1E40AF; }
.month-more-link { font-size:10px;color:#0066cc;cursor:pointer;text-decoration:underline; }
.cu-kw-sep {
    background: #e9ecef; padding: 3px 8px; font-size: 11px;
    border-top: 2px solid #adb5bd; color: #444; font-weight: 600;
}
.cu-row-othermonth { opacity: 0.35; }
</style>

<!-- Legend (hidden) -->
<div class="legend" style="display:none">
    <?php foreach ($active_groups as $group):
        $color_info = $colors[$group->color] ?? $colors['giallo'];
    ?>
    <div class="legend-item">
        <div class="legend-color" style="background:<?php echo $color_info['hex']; ?>;<?php echo $group->color === 'giallo' ? 'border:1px solid #ccc;' : ''; ?>"></div>
        <span><?php echo $color_info['emoji'] . ' ' . $color_info['name']; ?></span>
    </div>
    <?php endforeach; ?>
    <div class="legend-item">
        <div class="legend-color" style="background:#2563EB;border:2px dashed #2563EB;"></div>
        <span>Esterni</span>
    </div>
</div>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Gruppo</label>
        <select id="filter-gruppo">
            <option value="">Tutti i gruppi</option>
            <?php foreach ($groups as $g):
                $ci = $colors[$g->color] ?? $colors['giallo'];
                $kw = $g->calendar_week ? ' - KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
            ?>
            <option value="<?php echo $g->id; ?>"><?php echo $ci['emoji'] . ' ' . $ci['name'] . $kw; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Tipo</label>
        <select id="filter-tipo">
            <option value="">Tutti i tipi</option>
            <option value="week1">Attività Gruppo</option>
            <option value="atelier">Atelier</option>
            <option value="external">Esterni</option>
        </select>
    </div>

    <!-- View Toggle: Settimana | Mese -->
    <div style="margin-left:auto;">
        <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:5px;">Vista</label>
        <div class="view-toggle">
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $week, 'year' => $year]); ?>"
               class="view-toggle-btn <?php echo $view === 'week' ? 'active' : ''; ?>">Settimana</a>
            <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $month, 'year' => $year]); ?>"
               class="view-toggle-btn <?php echo $view === 'month' ? 'active' : ''; ?>">Mese</a>
        </div>
    </div>
</div>

<?php if ($view === 'week'): ?>
<!-- ===================== WEEK NAVIGATION ===================== -->
<?php
$prev_week = $week - 1; $prev_year = $year;
if ($prev_week < 1)  { $prev_week = 52; $prev_year--; }
$next_week = $week + 1; $next_year = $year;
if ($next_week > 52) { $next_week = 1;  $next_year++; }
?>
<div class="calendar-header">
    <div class="week-nav">
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $prev_week, 'year' => $prev_year]); ?>">&#8592; Sett. prec.</a>
        <h3>KW<?php echo str_pad($week, 2, '0', STR_PAD_LEFT); ?> | <?php echo date('j', $week_dates[0]['timestamp']); ?>&#8211;<?php echo date('j', $week_dates[4]['timestamp']); ?> <?php echo local_ftm_scheduler_format_date($week_dates[0]['timestamp'], 'month'); ?> <?php echo $year; ?></h3>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week', 'week' => $next_week, 'year' => $next_year]); ?>">Sett. succ. &#8594;</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'week']); ?>" class="ftm-btn ftm-btn-sm ftm-btn-secondary">Oggi</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <select onchange="ftmChangeWeekYear(this.value)" style="padding:8px 12px;border:1px solid #dee2e6;border-radius:6px;font-size:13px;">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>
<script>
function ftmChangeWeekYear(y) {
    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php?tab=calendario&view=week&week=<?php echo $week; ?>&year=' + y;
}
</script>

<!-- ===================== VISTA SETTIMANA — TABELLA UNIFICATA ===================== -->
<div style="overflow-x:auto; margin-top:16px;">
<table class="cal-unified">
    <thead>
        <tr>
            <th rowspan="2" class="cu-day">Giorno</th>
            <th rowspan="2" class="cu-sub"></th>
            <th colspan="<?php echo $n_coaches; ?>" class="cu-sect cu-sect-sw">SW Coach</th>
            <th colspan="<?php echo $n_coaches; ?>" class="cu-sect cu-sect-ass">Assenze Coach</th>
            <?php foreach ($rooms as $r): ?>
            <th colspan="2" class="cu-sect cu-sect-room"><?php echo s($r->shortname ?: $r->name); ?></th>
            <?php endforeach; ?>
            <th colspan="2" class="cu-sect cu-sect-sw">SW Segreteria</th>
            <th colspan="2" class="cu-sect cu-sect-ass">Assenze Segreteria</th>
            <th class="cu-sect">Altre Attivita</th>
        </tr>
        <tr>
            <!-- Coach iniziali SW -->
            <?php foreach ($coaches as $c):
                $ini = $c->initials;
                $cc  = $coach_personal_colors[$ini] ?? ['bg' => '#ccc', 'text' => '#000'];
            ?>
            <th class="cu-actor" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></th>
            <?php endforeach; ?>
            <!-- Coach iniziali Assenze -->
            <?php foreach ($coaches as $c):
                $ini = $c->initials;
                $cc  = $coach_personal_colors[$ini] ?? ['bg' => '#ccc', 'text' => '#000'];
            ?>
            <th class="cu-actor" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></th>
            <?php endforeach; ?>
            <!-- Room sub-headers -->
            <?php foreach ($rooms as $r): ?>
            <th class="cu-sub">Chi</th>
            <th class="cu-sub">ATT.</th>
            <?php endforeach; ?>
            <!-- Secretary SW -->
            <?php foreach ($secretaries as $s):
                $sc = $secretary_colors[$s];
            ?>
            <th class="cu-actor" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></th>
            <?php endforeach; ?>
            <!-- Secretary Assenze -->
            <?php foreach ($secretaries as $s):
                $sc = $secretary_colors[$s];
            ?>
            <th class="cu-actor" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></th>
            <?php endforeach; ?>
            <th class="cu-sub"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($week_dates as $wday):
        $dow      = $wday['day_of_week'];
        $date_str = date('Y-m-d', $wday['timestamp']);
    ?>
    <?php foreach (['matt', 'pom'] as $si => $slot): ?>
    <tr class="<?php echo $slot === 'matt' ? 'cu-row-matt' : 'cu-row-pom'; ?>">

        <?php if ($si === 0): ?>
        <td class="cu-day-cell" rowspan="2">
            <strong><?php echo s($wday['day_name']); ?></strong><br>
            <small><?php echo date('d/m', $wday['timestamp']); ?></small>
        </td>
        <?php endif; ?>

        <td class="cu-slot-cell <?php echo $slot; ?>">
            <?php echo $slot === 'matt' ? 'Matt.' : 'Pom.'; ?>
        </td>

        <!-- Coach SW cells -->
        <?php foreach ($coaches as $c):
            $ini      = $c->initials;
            $skey     = $ini . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $cc       = $coach_personal_colors[$ini] ?? ['bg' => '#ccc', 'text' => '#000'];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="sw"
            title="<?php echo s($ini); ?> Smart Working"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($ini), ENT_QUOTES); ?>,'coach',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'sw',this)">
            <?php if ($cur_status === 'sw'): ?>
            <span class="ds-badge" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>

        <!-- Coach Assenze cells -->
        <?php foreach ($coaches as $c):
            $ini      = $c->initials;
            $skey     = $ini . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $cc       = $coach_personal_colors[$ini] ?? ['bg' => '#ccc', 'text' => '#000'];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="assenza"
            title="<?php echo s($ini); ?> Assente"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($ini), ENT_QUOTES); ?>,'coach',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'absent',this)">
            <?php if ($cur_status === 'absent'): ?>
            <span class="ds-badge" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>

        <!-- Room cells (Chi + ATT.) -->
        <?php foreach ($rooms as $room):
            $act      = $room_matrix[$room->id][$dow][$slot] ?? null;
            $pre_date = $date_str;
        ?>
        <?php if ($act && !empty($act->is_external)): ?>
        <td class="cu-room-chi"
            onclick="ftmViewExternal(<?php echo (int)$act->id; ?>)"
            style="cursor:pointer"
            title="Esterno">
            <span class="ds-badge" style="background:#DBEAFE;color:#1E40AF;font-size:10px;">EXT</span>
        </td>
        <td class="cu-room-att"
            onclick="ftmViewExternal(<?php echo (int)$act->id; ?>)"
            style="cursor:pointer"><?php echo s($act->project_name); ?></td>
        <?php elseif ($act):
            $gc        = $act->group_color ?? 'neutro';
            $tid       = (int)($act->teacherid ?? 0);
            $gid       = (int)($act->groupid ?? 0);
            $at        = !empty($act->is_atelier) ? 'atelier' : 'week1';
            $coach_ini = '';
            $coach_bg  = '#ccc';
            $coach_txt = '#000';
            if (!empty($act->teacher_firstname) || !empty($act->teacher_lastname)) {
                $coach_ini = strtoupper(
                    substr($act->teacher_firstname ?? '', 0, 1) .
                    substr($act->teacher_lastname  ?? '', 0, 1)
                );
                $cc2       = $coach_personal_colors[$coach_ini] ?? ['bg' => '#ccc', 'text' => '#000'];
                $coach_bg  = $cc2['bg'];
                $coach_txt = $cc2['text'];
            }
        ?>
        <td class="cu-room-chi"
            data-actid="<?php echo (int)$act->id; ?>"
            data-roomid="<?php echo (int)$room->id; ?>"
            data-groupid="<?php echo $gid; ?>"
            data-type="<?php echo $at; ?>"
            onclick="ceOpenPop(<?php echo (int)$act->id; ?>,<?php echo htmlspecialchars(json_encode($act->name), ENT_QUOTES); ?>,<?php echo (int)$room->id; ?>,<?php echo $gid; ?>,<?php echo $tid; ?>,this,event)"
            style="cursor:pointer">
            <?php if ($coach_ini): ?>
            <span class="ds-badge" style="background:<?php echo $coach_bg; ?>;color:<?php echo $coach_txt; ?>"><?php echo s($coach_ini); ?></span>
            <?php endif; ?>
            <?php if ($gid): ?><br><span class="cu-grp-dot <?php echo s($gc); ?>"></span><?php endif; ?>
        </td>
        <td class="cu-room-att"
            data-actid="<?php echo (int)$act->id; ?>"
            onclick="ceOpenPop(<?php echo (int)$act->id; ?>,<?php echo htmlspecialchars(json_encode($act->name), ENT_QUOTES); ?>,<?php echo (int)$room->id; ?>,<?php echo $gid; ?>,<?php echo $tid; ?>,document.querySelector('[data-actid=\'<?php echo (int)$act->id; ?>\'].cu-room-chi'),event)"
            style="cursor:pointer"><?php echo s($act->name); ?></td>
        <?php else: ?>
        <td class="cu-room-free" colspan="2"
            onclick="ftmQuickCreate(<?php echo (int)$room->id; ?>,'<?php echo $pre_date; ?>','<?php echo $slot; ?>')"
            title="Crea attivita">+</td>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Secretary SW cells -->
        <?php foreach ($secretaries as $s):
            $skey      = $s . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $sc        = $secretary_colors[$s];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="sw"
            title="<?php echo s($s); ?> Smart Working"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>,'secretary',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'sw',this)">
            <?php if ($cur_status === 'sw'): ?>
            <span class="ds-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>

        <!-- Secretary Assenze cells -->
        <?php foreach ($secretaries as $s):
            $skey      = $s . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $sc        = $secretary_colors[$s];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="assenza"
            title="<?php echo s($s); ?> Assente"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>,'secretary',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'absent',this)">
            <?php if ($cur_status === 'absent'): ?>
            <span class="ds-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>

        <!-- Altre attivita cell -->
        <?php
        $ekey   = $date_str . '|' . $slot;
        $enotes = $extra_map[$ekey] ?? '';
        ?>
        <td class="cu-extra"
            data-extrakey="<?php echo htmlspecialchars($ekey, ENT_QUOTES); ?>"
            onclick="dsEditExtra(this)"
            title="Clicca per editare"><?php echo $enotes ? s($enotes) : ''; ?></td>

    </tr>
    <?php endforeach; // slot ?>
    <?php endforeach; // week_dates ?>
    </tbody>
</table>
</div>

<?php else: ?>
<!-- ===================== VISTA MESE — TABELLA UNIFICATA ===================== -->
<?php
$prev_month = $month - 1; $prev_month_year = $year;
if ($prev_month < 1)  { $prev_month = 12; $prev_month_year--; }
$next_month = $month + 1; $next_month_year = $year;
if ($next_month > 12) { $next_month = 1;  $next_month_year++; }
$day_names_short = ['', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven'];
$total_cols = 7 + 2 * $n_coaches + 2 * count($rooms);
?>
<div class="calendar-header">
    <div class="week-nav">
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $prev_month, 'year' => $prev_month_year]); ?>">&#8592; Mese prec.</a>
        <h3><?php echo $current_month_name; ?> <?php echo $year; ?></h3>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month', 'month' => $next_month, 'year' => $next_month_year]); ?>">Mese succ. &#8594;</a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'calendario', 'view' => 'month']); ?>" class="ftm-btn ftm-btn-sm ftm-btn-secondary">Mese Corrente</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <select onchange="ftmChangeYear(this.value)" style="padding:5px 8px;border:1px solid #dee2e6;border-radius:6px;font-size:12px;">
            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>
<script>
function ftmChangeYear(y) {
    window.location.href = '<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/index.php?tab=calendario&view=month&month=<?php echo $month; ?>&year=' + y;
}
</script>

<div style="overflow-x:auto; margin-top:8px;">
<table class="cal-unified">
    <thead>
        <tr>
            <th rowspan="2" class="cu-day">Giorno</th>
            <th rowspan="2" class="cu-sub"></th>
            <th colspan="<?php echo $n_coaches; ?>" class="cu-sect cu-sect-sw">SW Coach</th>
            <th colspan="<?php echo $n_coaches; ?>" class="cu-sect cu-sect-ass">Assenze Coach</th>
            <?php foreach ($rooms as $r): ?>
            <th colspan="2" class="cu-sect cu-sect-room"><?php echo s($r->shortname ?: $r->name); ?></th>
            <?php endforeach; ?>
            <th colspan="2" class="cu-sect cu-sect-sw">SW Segreteria</th>
            <th colspan="2" class="cu-sect cu-sect-ass">Assenze Segreteria</th>
            <th class="cu-sect">Altre Attivita</th>
        </tr>
        <tr>
            <?php foreach ($coaches as $c): $ini = $c->initials; $cc = $coach_personal_colors[$ini] ?? ['bg'=>'#ccc','text'=>'#000']; ?>
            <th class="cu-actor" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></th>
            <?php endforeach; ?>
            <?php foreach ($coaches as $c): $ini = $c->initials; $cc = $coach_personal_colors[$ini] ?? ['bg'=>'#ccc','text'=>'#000']; ?>
            <th class="cu-actor" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></th>
            <?php endforeach; ?>
            <?php foreach ($rooms as $r): ?>
            <th class="cu-sub">Chi</th>
            <th class="cu-sub">ATT.</th>
            <?php endforeach; ?>
            <?php foreach ($secretaries as $s): $sc = $secretary_colors[$s]; ?>
            <th class="cu-actor" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></th>
            <?php endforeach; ?>
            <?php foreach ($secretaries as $s): $sc = $secretary_colors[$s]; ?>
            <th class="cu-actor" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></th>
            <?php endforeach; ?>
            <th class="cu-sub"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($month_weeks as $week_info):
        $wn = $week_info['week_num'];
        // Build room matrix for this week from month_activities
        $wk_room_matrix = [];
        foreach ($week_info['days'] as $mday) {
            $mdow = $mday['day_of_week'];
            foreach (['matt', 'pom'] as $wk_slot) {
                foreach ($month_activities[$wn][$mdow][$wk_slot] ?? [] as $act) {
                    $rid = (int)($act->roomid ?? 0);
                    if ($rid > 0) {
                        $wk_room_matrix[$rid][$mdow][$wk_slot] = $act;
                    }
                }
            }
        }
    ?>
    <tr>
        <td colspan="<?php echo $total_cols; ?>" class="cu-kw-sep">
            <strong>KW<?php echo str_pad($wn, 2, '0', STR_PAD_LEFT); ?></strong>
            &nbsp;<?php echo date('j/n', $week_info['monday_ts']); ?> &#8211; <?php echo date('j/n', $week_info['friday_ts']); ?>
            &nbsp;<a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab'=>'calendario','view'=>'week','week'=>$wn,'year'=>$week_info['year']]); ?>"
               style="font-size:10px;color:#0066cc;">Dettaglio &#8594;</a>
        </td>
    </tr>
    <?php foreach ($week_info['days'] as $mday):
        $dow      = $mday['day_of_week'];
        $day_ts   = $week_info['monday_ts'] + ($dow - 1) * 86400;
        $date_str = date('Y-m-d', $day_ts);
        $row_cls  = $mday['is_current_month'] ? '' : ' cu-row-othermonth';
    ?>
    <?php foreach (['matt', 'pom'] as $msi => $slot): ?>
    <tr class="<?php echo $slot === 'matt' ? 'cu-row-matt' : 'cu-row-pom'; ?><?php echo $row_cls; ?>">
        <?php if ($msi === 0): ?>
        <td class="cu-day-cell" rowspan="2">
            <strong><?php echo $day_names_short[$dow]; ?></strong><br>
            <small><?php echo $mday['day_num']; ?></small>
        </td>
        <?php endif; ?>
        <td class="cu-slot-cell <?php echo $slot; ?>">
            <?php echo $slot === 'matt' ? 'Matt.' : 'Pom.'; ?>
        </td>
        <!-- Coach SW -->
        <?php foreach ($coaches as $c):
            $ini = $c->initials;
            $skey = $ini . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $cc = $coach_personal_colors[$ini] ?? ['bg'=>'#ccc','text'=>'#000'];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="sw"
            title="<?php echo s($ini); ?> SW"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($ini), ENT_QUOTES); ?>,'coach',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'sw',this)">
            <?php if ($cur_status === 'sw'): ?>
            <span class="ds-badge" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <!-- Coach Assenze -->
        <?php foreach ($coaches as $c):
            $ini = $c->initials;
            $skey = $ini . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $cc = $coach_personal_colors[$ini] ?? ['bg'=>'#ccc','text'=>'#000'];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="assenza"
            title="<?php echo s($ini); ?> Assente"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($ini), ENT_QUOTES); ?>,'coach',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'absent',this)">
            <?php if ($cur_status === 'absent'): ?>
            <span class="ds-badge" style="background:<?php echo $cc['bg']; ?>;color:<?php echo $cc['text']; ?>"><?php echo s($ini); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <!-- Room cells -->
        <?php foreach ($rooms as $room):
            $act      = $wk_room_matrix[$room->id][$dow][$slot] ?? null;
            $pre_date = $date_str;
        ?>
        <?php if ($act && !empty($act->is_external)): ?>
        <td class="cu-room-chi" onclick="ftmViewExternal(<?php echo (int)$act->id; ?>)" style="cursor:pointer">
            <span class="ds-badge" style="background:#DBEAFE;color:#1E40AF;font-size:10px;">EXT</span>
        </td>
        <td class="cu-room-att" onclick="ftmViewExternal(<?php echo (int)$act->id; ?>)" style="cursor:pointer">
            <?php echo s($act->project_name); ?></td>
        <?php elseif ($act):
            $gc = $act->group_color ?? 'neutro';
            $tid = (int)($act->teacherid ?? 0);
            $gid = (int)($act->groupid ?? 0);
            $at  = !empty($act->is_atelier) ? 'atelier' : 'week1';
            $coach_ini2 = '';
            $coach_bg2  = '#ccc';
            $coach_txt2 = '#000';
            if (!empty($act->teacher_firstname) || !empty($act->teacher_lastname)) {
                $coach_ini2 = strtoupper(substr($act->teacher_firstname ?? '', 0, 1) . substr($act->teacher_lastname ?? '', 0, 1));
                $cc2        = $coach_personal_colors[$coach_ini2] ?? ['bg'=>'#ccc','text'=>'#000'];
                $coach_bg2  = $cc2['bg'];
                $coach_txt2 = $cc2['text'];
            }
        ?>
        <td class="cu-room-chi"
            data-actid="<?php echo (int)$act->id; ?>"
            data-roomid="<?php echo (int)$room->id; ?>"
            data-groupid="<?php echo $gid; ?>"
            data-type="<?php echo $at; ?>"
            onclick="ceOpenPop(<?php echo (int)$act->id; ?>,<?php echo htmlspecialchars(json_encode($act->name), ENT_QUOTES); ?>,<?php echo (int)$room->id; ?>,<?php echo $gid; ?>,<?php echo $tid; ?>,this,event)"
            style="cursor:pointer">
            <?php if ($coach_ini2): ?>
            <span class="ds-badge" style="background:<?php echo $coach_bg2; ?>;color:<?php echo $coach_txt2; ?>"><?php echo s($coach_ini2); ?></span>
            <?php endif; ?>
            <?php if ($gid): ?><br><span class="cu-grp-dot <?php echo s($gc); ?>"></span><?php endif; ?>
        </td>
        <td class="cu-room-att"
            onclick="ceOpenPop(<?php echo (int)$act->id; ?>,<?php echo htmlspecialchars(json_encode($act->name), ENT_QUOTES); ?>,<?php echo (int)$room->id; ?>,<?php echo $gid; ?>,<?php echo $tid; ?>,document.querySelector('[data-actid=\'<?php echo (int)$act->id; ?>\'].cu-room-chi'),event)"
            style="cursor:pointer"><?php echo s($act->name); ?></td>
        <?php else: ?>
        <td class="cu-room-free" colspan="2"
            onclick="ftmQuickCreate(<?php echo (int)$room->id; ?>,'<?php echo $pre_date; ?>','<?php echo $slot; ?>')"
            title="Crea">+</td>
        <?php endif; ?>
        <?php endforeach; ?>
        <!-- Secretary SW -->
        <?php foreach ($secretaries as $s):
            $skey = $s . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $sc = $secretary_colors[$s];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="sw"
            title="<?php echo s($s); ?> SW"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>,'secretary',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'sw',this)">
            <?php if ($cur_status === 'sw'): ?>
            <span class="ds-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <!-- Secretary Assenze -->
        <?php foreach ($secretaries as $s):
            $skey = $s . '|' . $date_str . '|' . $slot;
            $cur_status = $initial_status_map[$skey] ?? 'present';
            $sc = $secretary_colors[$s];
        ?>
        <td class="cu-status-cell"
            data-dskey="<?php echo htmlspecialchars($skey, ENT_QUOTES); ?>"
            data-ds-section="assenza"
            title="<?php echo s($s); ?> Assente"
            onclick="dsToggle(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>,'secretary',<?php echo htmlspecialchars(json_encode($date_str), ENT_QUOTES); ?>,<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES); ?>,'absent',this)">
            <?php if ($cur_status === 'absent'): ?>
            <span class="ds-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>"><?php echo s($s); ?></span>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <!-- Extra activities -->
        <?php $ekey = $date_str . '|' . $slot; $enotes = $extra_map[$ekey] ?? ''; ?>
        <td class="cu-extra"
            data-extrakey="<?php echo htmlspecialchars($ekey, ENT_QUOTES); ?>"
            onclick="dsEditExtra(this)"
            title="Clicca per editare"><?php echo $enotes ? s($enotes) : ''; ?></td>
    </tr>
    <?php endforeach; // slot ?>
    <?php endforeach; // days ?>
    <?php endforeach; // month_weeks ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- ===================== SHARED POPOVER (room grid editor) ===================== -->
<div id="ce-pop">
    <div class="ce-pop-header">
        <span class="ce-pop-title" id="ce-pop-title">Modifica Attivita</span>
        <button class="ce-pop-close" onclick="ceClearPop()">&#215;</button>
    </div>
    <div class="ce-pop-field">
        <label>Nome Attivita</label>
        <input type="text" id="ce-pop-name" autocomplete="off">
    </div>
    <div class="ce-pop-field">
        <label>Aula</label>
        <select id="ce-pop-room">
            <option value="0">— Nessuna aula —</option>
            <?php foreach ($rooms as $r): ?>
            <option value="<?php echo (int)$r->id; ?>"><?php echo s($r->shortname ?: $r->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="ce-pop-field">
        <label>Gruppo</label>
        <select id="ce-pop-group">
            <option value="0">— Nessun gruppo —</option>
            <?php foreach ($groups as $g):
                $ci = $colors[$g->color] ?? $colors['giallo'];
                $kw = $g->calendar_week ? ' KW' . str_pad($g->calendar_week, 2, '0', STR_PAD_LEFT) : '';
            ?>
            <option value="<?php echo (int)$g->id; ?>"><?php echo $ci['emoji'] . ' ' . $ci['name'] . $kw; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="ce-pop-field">
        <label>Coach / Docente</label>
        <select id="ce-pop-coach">
            <option value="0">— Nessun coach —</option>
            <?php foreach ($coaches as $c): ?>
            <option value="<?php echo (int)$c->userid; ?>"><?php echo s($c->initials . ' - ' . $c->firstname . ' ' . $c->lastname); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div id="ce-pop-status" style="min-height:16px;"></div>
</div>

<!-- ===================== JAVASCRIPT ===================== -->
<script>
// ---- Data maps ----
var ceAjaxUrl    = '<?php echo (new moodle_url('/local/ftm_scheduler/ajax_secretary.php'))->out(false); ?>';
var ceSesskey    = '<?php echo sesskey(); ?>';
var ceRooms      = <?php echo json_encode($js_rooms); ?>;
var ceGroups     = <?php echo json_encode($js_groups); ?>;
var ceCoaches    = <?php echo json_encode($js_coaches_map); ?>;
var cePopActId   = 0;
var cePopCellEl  = null;

// ---- Room grid popover ----
function ceOpenPop(actid, name, roomid, groupid, teacherid, cellEl, event) {
    cePopActId  = actid;
    cePopCellEl = cellEl;

    document.getElementById('ce-pop-title').textContent = name || 'Modifica Attivita';
    document.getElementById('ce-pop-name').value  = name;
    document.getElementById('ce-pop-room').value  = roomid || 0;
    document.getElementById('ce-pop-group').value = groupid || 0;
    document.getElementById('ce-pop-coach').value = teacherid || 0;
    document.getElementById('ce-pop-status').innerHTML = '';

    ['room', 'group', 'coach'].forEach(function(f) {
        var el    = document.getElementById('ce-pop-' + f);
        var field = f === 'room' ? 'roomid' : (f === 'group' ? 'groupid' : 'teacherid');
        el.onchange = function() { cePopSave(field, el.value); };
    });
    var nameEl = document.getElementById('ce-pop-name');
    nameEl.onblur  = function() { if (nameEl.value.trim()) cePopSave('name', nameEl.value.trim()); };
    nameEl.onkeydown = function(e) { if (e.key === 'Enter') nameEl.blur(); };

    var pop = document.getElementById('ce-pop');
    pop.style.display = 'block';
    if (event) {
        var x = event.clientX + 10;
        var y = event.clientY;
        if (x + 310 > window.innerWidth) x = event.clientX - 320;
        pop.style.left = Math.max(8, x) + 'px';
        pop.style.top  = Math.max(8, y) + 'px';
    }
    if (event) event.stopPropagation();
}

function cePopSave(field, value) {
    if (!cePopActId) return;
    var statusEl = document.getElementById('ce-pop-status');
    statusEl.innerHTML = '<span style="color:#999;font-size:11px;">Salvataggio...</span>';
    var fd = new FormData();
    fd.append('action', 'update_activity');
    fd.append('sesskey', ceSesskey);
    fd.append('id', cePopActId);
    fd.append(field, value);
    fetch(ceAjaxUrl, {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                statusEl.innerHTML = '<span style="color:#28a745;font-size:11px;">&#10003; Salvato</span>';
                if (field === 'name') {
                    document.getElementById('ce-pop-title').textContent = value;
                }
                setTimeout(function() { statusEl.innerHTML = ''; }, 2000);
            } else {
                statusEl.innerHTML = '<span style="color:#dc3545;font-size:11px;">&#9888; Errore</span>';
            }
        })
        .catch(function() {
            statusEl.innerHTML = '<span style="color:#dc3545;font-size:11px;">&#9888; Errore rete</span>';
        });
}

function ceClearPop() {
    document.getElementById('ce-pop').style.display = 'none';
    cePopActId  = 0;
    cePopCellEl = null;
}

document.addEventListener('click', function(e) {
    var pop = document.getElementById('ce-pop');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target)) ceClearPop();
});
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') ceClearPop(); });

// ---- DS Status (coach/secretary toggle) ----
var dsStatusMap  = <?php echo json_encode($initial_status_map); ?>;
var dsAjaxUrl    = ceAjaxUrl;
var dsSesskey    = ceSesskey;
var dsCoachColors = {
    'CB': {bg: '#00B0F0', text: '#000'},
    'FM': {bg: '#FFFF00', text: '#333'},
    'GM': {bg: '#00B050', text: '#fff'},
    'RB': {bg: '#FF0000', text: '#fff'},
    'LP': {bg: '#CC00FF', text: '#fff'},
    'DB': {bg: '#FFA500', text: '#000'}
};
var dsSecrColors = {
    'SANDRA': {bg: '#00B0F0', text: '#000'},
    'ALE':    {bg: '#FF9800', text: '#000'}
};

function dsMakeBadge(actor, actorType) {
    var span   = document.createElement('span');
    span.className = 'ds-badge';
    var colors = actorType === 'secretary' ? dsSecrColors : dsCoachColors;
    var c      = colors[actor] || {bg: '#999', text: '#fff'};
    span.style.background = c.bg;
    span.style.color      = c.text;
    span.textContent      = actor;
    return span;
}

function dsToggle(actor, actorType, date, slot, targetStatus, clickedCell) {
    var key     = actor + '|' + date + '|' + slot;
    var current = dsStatusMap[key] || 'present';
    var newStatus = (current === targetStatus) ? 'present' : targetStatus;
    dsStatusMap[key] = newStatus;

    // Update all cells sharing this key
    document.querySelectorAll('[data-dskey="' + key + '"]').forEach(function(cell) {
        var section    = cell.getAttribute('data-ds-section');
        var badge      = cell.querySelector('.ds-badge');
        var shouldShow = (newStatus === 'sw' && section === 'sw') ||
                         (newStatus === 'absent' && section === 'assenza');
        if (shouldShow) {
            if (!badge) cell.appendChild(dsMakeBadge(actor, actorType));
        } else {
            if (badge) badge.remove();
        }
    });

    // AJAX persist
    var fd = new FormData();
    fd.append('action', 'save_status');
    fd.append('sesskey', dsSesskey);
    fd.append('actor_name', actor);
    fd.append('actor_type', actorType);
    fd.append('date', date);
    fd.append('slot', slot);
    fd.append('status', newStatus);
    fetch(dsAjaxUrl, {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                // Revert on failure
                dsStatusMap[key] = current;
                location.reload();
            }
        })
        .catch(function() {
            dsStatusMap[key] = current;
        });
}

// ---- Extra activities ----
var dsExtraMap = <?php echo json_encode($extra_map); ?>;

function dsEditExtra(cell) {
    if (cell.querySelector('input')) return;
    var key     = cell.getAttribute('data-extrakey');
    var current = dsExtraMap[key] || '';
    var orig    = cell.innerHTML;
    var inp     = document.createElement('input');
    inp.type    = 'text';
    inp.value   = current;
    inp.style.cssText = 'width:100%;font-size:10px;border:1px solid #0066cc;border-radius:3px;padding:2px 4px;';
    inp.onclick  = function(e) { e.stopPropagation(); };
    inp.onkeydown = function(e) {
        if (e.key === 'Enter')  inp.blur();
        if (e.key === 'Escape') { cell.innerHTML = orig; }
    };
    inp.onblur = function() {
        var val = inp.value.trim();
        dsExtraMap[key] = val;
        cell.textContent = val;
        var parts = key.split('|');
        var fd = new FormData();
        fd.append('action', 'save_extra');
        fd.append('sesskey', dsSesskey);
        fd.append('date', parts[0]);
        fd.append('slot', parts[1]);
        fd.append('notes', val);
        fetch(dsAjaxUrl, {method: 'POST', body: fd});
    };
    cell.innerHTML = '';
    cell.appendChild(inp);
    inp.focus();
    inp.select();
}
</script>

<!-- ===================== FILTER JS ===================== -->
<script>
(function() {
    'use strict';
    var fg = document.getElementById('filter-gruppo');
    var ft = document.getElementById('filter-tipo');

    function applyFilters() {
        var gv = fg ? fg.value : '';
        var tv = ft ? ft.value : '';

        // Month view mini activities
        document.querySelectorAll('.month-activity-mini').forEach(function(b) {
            var bt = b.classList.contains('external') ? 'external' :
                     (b.classList.contains('atelier') ? 'atelier' : 'week1');
            b.style.display = (!tv || bt === tv) ? '' : 'none';
        });

        // Unified table: cu-room-chi / cu-room-att by data-type
        document.querySelectorAll('[data-type]').forEach(function(b) {
            var bt = b.getAttribute('data-type') || '';
            var bg = b.getAttribute('data-groupid') || '';
            var show = true;
            if (tv && bt !== tv) show = false;
            if (gv && bg && bg !== gv) show = false;
            b.style.display = show ? '' : 'none';
        });
    }

    if (fg) fg.addEventListener('change', applyFilters);
    if (ft) ft.addEventListener('change', applyFilters);

    window.ftmResetFilters = function() {
        if (fg) fg.value = '';
        if (ft) ft.value = '';
        applyFilters();
    };
})();
</script>
