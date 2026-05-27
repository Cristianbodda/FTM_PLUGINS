<?php
// Room grid inner table — shared by week view (collapsible) and aule view (primary).
// Requires: $rooms, $week_dates, $room_matrix, $colors available in scope.
defined('MOODLE_INTERNAL') || die();
?>
<table class="room-main-table">
    <thead>
        <tr>
            <th class="rmt-room" rowspan="2">Aula</th>
            <?php foreach ($week_dates as $wday): ?>
            <th colspan="2">
                <?php echo $wday['day_name']; ?><br>
                <small style="font-weight:400"><?php echo date('j/n', $wday['timestamp']); ?></small>
            </th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($week_dates as $wday): ?>
            <th class="rmt-slot matt">🌅 Matt</th>
            <th class="rmt-slot pom">🌇 Pom</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rooms as $room): ?>
        <tr>
            <td class="rmt-label">
                <?php echo s($room->shortname ?: $room->name); ?>
                <small><?php if ($room->shortname && $room->name !== $room->shortname) echo s($room->name); ?></small>
            </td>
            <?php foreach ($week_dates as $wday): ?>
            <?php foreach (['matt', 'pom'] as $slot):
                $act = $room_matrix[$room->id][$wday['day_of_week']][$slot] ?? null;
                $pre_date = date('Y-m-d', $wday['timestamp']);
            ?>
            <td>
                <?php if ($act): ?>
                    <?php if (!empty($act->is_external)): ?>
                        <div class="rmt-act ext"
                             data-roomid="<?php echo (int)$room->id; ?>"
                             data-groupid=""
                             data-type="external"
                             onclick="ceOpenPop(<?php echo (int)$act->id; ?>,
                                <?php echo htmlspecialchars(json_encode($act->project_name), ENT_QUOTES); ?>,
                                <?php echo (int)($act->roomid??0); ?>, 0,
                                <?php echo (int)($act->teacherid??0); ?>,
                                this, event)"
                             title="Clicca per modificare">
                            <span class="rmt-edit-hint">✏️</span>
                            <span class="rmt-act-name"><?php echo s($act->project_name); ?></span>
                            <span class="rmt-act-meta"><?php echo s($act->responsible??''); ?></span>
                        </div>
                    <?php else:
                        $gc  = $act->group_color ?? 'neutro';
                        $gid = (int)($act->groupid  ?? 0);
                        $tid = (int)($act->teacherid ?? 0);
                        $at  = !empty($act->is_atelier) ? 'atelier' : 'week1';
                        $initials = '';
                        if (!empty($act->teacher_firstname) || !empty($act->teacher_lastname)) {
                            $initials = strtoupper(
                                substr($act->teacher_firstname ?? '', 0, 1) .
                                substr($act->teacher_lastname  ?? '', 0, 1)
                            );
                        }
                        $ci = $colors[$gc] ?? ['emoji'=>'⬜','name'=>''];
                        $grp_short = $gid && !empty($act->group_name) ? $act->group_name : ($gid ? $ci['name'] : '');
                        $meta_parts = array_filter([$initials, $grp_short]);
                    ?>
                        <div class="rmt-act <?php echo $gc; ?>"
                             data-actid="<?php echo (int)$act->id; ?>"
                             data-roomid="<?php echo (int)$room->id; ?>"
                             data-groupid="<?php echo $gid; ?>"
                             data-type="<?php echo $at; ?>"
                             onclick="ceOpenPop(<?php echo (int)$act->id; ?>,
                                <?php echo htmlspecialchars(json_encode($act->name), ENT_QUOTES); ?>,
                                <?php echo (int)$room->id; ?>,
                                <?php echo $gid; ?>,
                                <?php echo $tid; ?>,
                                this, event)"
                             title="Clicca per modificare">
                            <span class="rmt-edit-hint">✏️</span>
                            <span class="rmt-act-name"><?php echo s($act->name); ?></span>
                            <?php if ($meta_parts): ?>
                            <span class="rmt-act-meta"><?php echo s(implode(' · ', $meta_parts)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="rmt-cell rmt-free"
                         onclick="ftmQuickCreate(<?php echo (int)$room->id; ?>, '<?php echo $pre_date; ?>', '<?php echo $slot; ?>')"
                         title="Crea attività — <?php echo s($room->shortname?:$room->name); ?>, <?php echo date('d/m',$wday['timestamp']); ?> <?php echo $slot==='matt'?'Mattina':'Pomeriggio'; ?>">
                        +
                    </div>
                <?php endif; ?>
            </td>
            <?php endforeach; endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
