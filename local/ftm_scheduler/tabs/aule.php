<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Aule - IDENTICO al mockup 05_scheduler_gruppi_v3.html

defined('MOODLE_INTERNAL') || die();

// Variables passed from index.php:
// $rooms, $colors, $active_groups

// Room header colors
$room_header_colors = [
    1 => ['bg' => '#1E40AF', 'text' => 'white'],
    2 => ['bg' => '#065F46', 'text' => 'white'],
    3 => ['bg' => '#92400E', 'text' => 'white'],
];

// Room icons
$room_icons = [
    1 => '🖥️',
    2 => '📚',
    3 => '🔧',
];

// Room capabilities (default)
$room_capabilities = [
    1 => ['Elettricità', 'Automazione', 'Pneumatica', 'Idraulica'],
    2 => ['Lezioni', 'Quiz/Test', 'Atelier'],
    3 => ['CNC Fresa', 'CNC Tornio', 'SolidWorks'],
];
?>

<!-- Gruppi Grid (reused for rooms) -->
<div class="gruppi-grid">
    <?php foreach ($rooms as $room): 
        $header_style = $room_header_colors[$room->id] ?? ['bg' => '#1E40AF', 'text' => 'white'];
        $icon = $room_icons[$room->id] ?? '🏫';
        $capabilities = $room_capabilities[$room->id] ?? [];
        
        // Try to parse JSON capabilities if available
        if (!empty($room->capabilities_json)) {
            $parsed = json_decode($room->capabilities_json, true);
            if ($parsed) {
                $capabilities = $parsed;
            }
        }
        
        // Determine room type label
        $type_label = $room->is_lab ? 'Laboratorio' : 'Aula Teoria';
        $type_icon = $room->is_lab ? '🔬' : '📖';
    ?>
        <div class="gruppo-card">
            <div class="gruppo-card-header" style="background: <?php echo $header_style['bg']; ?>; color: <?php echo $header_style['text']; ?>;">
                <h3><?php echo $icon; ?> <?php echo $room->name; ?></h3>
                <span class="gruppo-week-badge"><?php echo $room->capacity; ?> postazioni</span>
            </div>
            <div class="gruppo-card-body">
                <div class="gruppo-detail">
                    <span>Tipo</span>
                    <strong><?php echo $type_icon; ?> <?php echo $type_label; ?></strong>
                </div>
                <div class="gruppo-detail">
                    <span>Questa settimana</span>
                    <?php if (!empty($active_groups)): 
                        // Check if room is used by any active group
                        $group = reset($active_groups);
                        $color_info = $colors[$group->color] ?? $colors['giallo'];
                    ?>
                        <strong style="color: <?php echo $color_info['border']; ?>;">
                            <?php echo $color_info['emoji']; ?> <?php echo $color_info['name']; ?> - Sett. 1
                        </strong>
                    <?php else: ?>
                        <strong style="color: #28a745;">Libera</strong>
                    <?php endif; ?>
                </div>
                <?php if ($room->id == 1): ?>
                    <div class="gruppo-detail">
                        <span>Mer <?php echo date('j'); ?></span>
                        <strong style="color: #dc3545;">🏢 BIT URAR (tutto il giorno)</strong>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <strong style="font-size: 12px; color: #666;">
                        <?php echo $room->is_lab ? 'Attrezzature:' : 'Utilizzo:'; ?>
                    </strong>
                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px;">
                        <?php foreach ($capabilities as $cap): ?>
                            <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">
                                <?php echo $cap; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <?php if (empty($rooms)): ?>
        <!-- Default rooms if none in database -->
        <div class="gruppo-card">
            <div class="gruppo-card-header" style="background: #1E40AF; color: white;">
                <h3>🖥️ AULA 1</h3>
                <span class="gruppo-week-badge">8 postazioni</span>
            </div>
            <div class="gruppo-card-body">
                <div class="gruppo-detail">
                    <span>Tipo</span>
                    <strong>🔬 Laboratorio</strong>
                </div>
                <div class="gruppo-detail">
                    <span>Questa settimana</span>
                    <strong style="color: #28a745;">Libera</strong>
                </div>
                <div style="margin-top: 15px;">
                    <strong style="font-size: 12px; color: #666;">Attrezzature:</strong>
                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px;">
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Elettricità</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Automazione</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Pneumatica</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Idraulica</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="gruppo-card">
            <div class="gruppo-card-header" style="background: #065F46; color: white;">
                <h3>📚 AULA 2</h3>
                <span class="gruppo-week-badge">20 postazioni</span>
            </div>
            <div class="gruppo-card-body">
                <div class="gruppo-detail">
                    <span>Tipo</span>
                    <strong>📖 Aula Teoria</strong>
                </div>
                <div class="gruppo-detail">
                    <span>Questa settimana</span>
                    <strong style="color: #EAB308;">🟡 Giallo - Sett. 1</strong>
                </div>
                <div style="margin-top: 15px;">
                    <strong style="font-size: 12px; color: #666;">Utilizzo:</strong>
                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px;">
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Lezioni</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Quiz/Test</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">Atelier</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="gruppo-card">
            <div class="gruppo-card-header" style="background: #92400E; color: white;">
                <h3>🔧 AULA 3</h3>
                <span class="gruppo-week-badge">12 postazioni</span>
            </div>
            <div class="gruppo-card-body">
                <div class="gruppo-detail">
                    <span>Tipo</span>
                    <strong>🔬 Lab CNC</strong>
                </div>
                <div class="gruppo-detail">
                    <span>Questa settimana</span>
                    <strong style="color: #28a745;">Libera</strong>
                </div>
                <div style="margin-top: 15px;">
                    <strong style="font-size: 12px; color: #666;">Attrezzature:</strong>
                    <div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px;">
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">CNC Fresa</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">CNC Tornio</span>
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 10px; font-size: 11px;">SolidWorks</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
