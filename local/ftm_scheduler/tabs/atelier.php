<?php
// This file is part of Moodle - http://moodle.org/
//
// Tab Atelier - IDENTICO al mockup 05_scheduler_gruppi_v3.html

defined('MOODLE_INTERNAL') || die();

// Variables passed from index.php:
// $atelier_catalog, $active_groups, $colors

// Get first active group info
$first_group = !empty($active_groups) ? reset($active_groups) : null;
$group_color_info = $first_group ? ($colors[$first_group->color] ?? $colors['giallo']) : null;
?>

<!-- Alert -->
<div class="ftm-alert ftm-alert-warning">
    <span>⏳</span>
    <div>
        <strong>Atelier disponibili dalla Settimana 3</strong><br>
        <?php if ($first_group): ?>
            Il <?php echo $group_color_info['emoji']; ?> Gruppo <?php echo $group_color_info['name']; ?> è attualmente in Settimana 1. 
            Gli atelier saranno disponibili per l'iscrizione a partire dalla Settimana 3.
        <?php else: ?>
            Nessun gruppo attivo. Gli atelier saranno disponibili dalla Settimana 3 dopo l'attivazione di un gruppo.
        <?php endif; ?>
    </div>
</div>

<h3 style="margin-bottom: 15px;">📋 Catalogo Atelier</h3>

<table class="data-table">
    <thead>
        <tr>
            <th>Atelier</th>
            <th>Codice Excel</th>
            <th>Settimana Tipica</th>
            <th>Giorno/Ora</th>
            <th>Max Part.</th>
            <th>Stato</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($atelier_catalog)): ?>
            <!-- Default atelier if none in database -->
            <tr>
                <td><strong>Canali - strumenti e mercato del lavoro</strong></td>
                <td>At. Canali</td>
                <td>Sett. 3-5</td>
                <td>Mercoledì Matt.</td>
                <td>10</td>
                <td><span class="status-badge status-active">Attivo</span></td>
            </tr>
            <tr>
                <td><strong>Colloquio di lavoro</strong></td>
                <td>At. Collo.</td>
                <td>Sett. 3-5</td>
                <td>Mercoledì Pom.</td>
                <td>10</td>
                <td><span class="status-badge status-active">Attivo</span></td>
            </tr>
            <tr>
                <td><strong>Curriculum Vitae - redazione/revisione</strong></td>
                <td>At. CV</td>
                <td>Sett. 3-5</td>
                <td>Mercoledì</td>
                <td>10</td>
                <td><span class="status-badge status-active">Attivo</span></td>
            </tr>
            <tr>
                <td><strong>Lettere AC + RA - redazione/revisione</strong></td>
                <td>At. AC/RA</td>
                <td>Sett. 4-6</td>
                <td>Mercoledì</td>
                <td>10</td>
                <td><span class="status-badge status-active">Attivo</span></td>
            </tr>
            <tr>
                <td><strong>Agenzie e guadagno intermedio</strong></td>
                <td>At. Ag. e GI</td>
                <td>Sett. 4-6</td>
                <td>Mercoledì Matt.</td>
                <td>10</td>
                <td><span class="status-badge status-active">Attivo</span></td>
            </tr>
            <tr style="background: #FEF3C7;">
                <td><strong>⭐ Bilancio di fine misura</strong></td>
                <td>BILANCIO</td>
                <td>Sett. 6 (OBBLIGATORIO)</td>
                <td>Mercoledì 15:00-16:30</td>
                <td>10</td>
                <td><span class="status-badge" style="background: #FEF3C7; color: #92400E;">Obbligatorio</span></td>
            </tr>
        <?php else: ?>
            <?php foreach ($atelier_catalog as $atelier): 
                $is_mandatory = $atelier->is_mandatory;
                $row_style = $is_mandatory ? 'background: #FEF3C7;' : '';
                
                // Format typical day/slot
                $day_names = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
                $slot_names = ['matt' => 'Matt.', 'pom' => 'Pom.'];
                $day_slot = '';
                if ($atelier->typical_day) {
                    $day_slot = $day_names[$atelier->typical_day] ?? '';
                    if ($atelier->typical_slot) {
                        $day_slot .= ' ' . ($slot_names[$atelier->typical_slot] ?? '');
                    }
                }
            ?>
                <tr style="<?php echo $row_style; ?>">
                    <td>
                        <strong>
                            <?php if ($is_mandatory): ?>⭐ <?php endif; ?>
                            <?php echo $atelier->name; ?>
                        </strong>
                    </td>
                    <td><?php echo $atelier->shortname; ?></td>
                    <td>
                        Sett. <?php echo $atelier->typical_week_start; ?>-<?php echo $atelier->typical_week_end; ?>
                        <?php if ($is_mandatory): ?>
                            (OBBLIGATORIO)
                        <?php endif; ?>
                    </td>
                    <td><?php echo $day_slot ?: 'Mercoledì'; ?></td>
                    <td><?php echo $atelier->max_participants; ?></td>
                    <td>
                        <?php if ($is_mandatory): ?>
                            <span class="status-badge" style="background: #FEF3C7; color: #92400E;">Obbligatorio</span>
                        <?php else: ?>
                            <span class="status-badge status-active">Attivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
