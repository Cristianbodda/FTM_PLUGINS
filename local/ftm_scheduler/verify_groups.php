<?php
/**
 * Verifica e corregge le date dei gruppi FTM
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/verify_groups.php'));
$PAGE->set_title('Verifica Gruppi FTM');
$PAGE->set_heading('Verifica Gruppi FTM');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">';
echo '<h2>Verifica Date Gruppi FTM</h2>';

// Dati corretti forniti dall'utente
// Formato: KW inizio => [data_inizio, data_fine]
$correct_data = [
    // 2025
    47 => ['2025-11-17', '2025-12-26'],
    49 => ['2025-12-01', '2026-01-09'],
    // 2026
    4  => ['2026-01-19', '2026-02-27'],
    6  => ['2026-02-02', '2026-03-13'],
    8  => ['2026-02-16', '2026-03-27'],
    10 => ['2026-03-02', '2026-04-10'],
    12 => ['2026-03-16', '2026-04-24'],
    14 => ['2026-03-30', '2026-05-08'],
    16 => ['2026-04-13', '2026-05-22'],
    18 => ['2026-04-27', '2026-06-05'],
    20 => ['2026-05-11', '2026-06-19'],
    22 => ['2026-05-25', '2026-07-03'],
    24 => ['2026-06-08', '2026-07-17'],
    26 => ['2026-06-22', '2026-07-31'],
    28 => ['2026-07-06', '2026-08-14'],
    30 => ['2026-07-20', '2026-08-28'],
    32 => ['2026-08-03', '2026-09-11'],
    34 => ['2026-08-17', '2026-09-25'],
    36 => ['2026-08-31', '2026-10-09'],
    38 => ['2026-09-14', '2026-10-23'],
    40 => ['2026-09-28', '2026-11-06'],
    42 => ['2026-10-12', '2026-11-20'],
    44 => ['2026-10-26', '2026-12-04'],
    46 => ['2026-11-09', '2026-12-18'],
    48 => ['2026-11-23', '2027-01-01'],
    50 => ['2026-12-07', '2027-01-15'],
];

// Carica gruppi esistenti dal DB
$groups = $DB->get_records('local_ftm_groups', null, 'calendar_week ASC, entry_date ASC');

echo '<h3>Gruppi nel Database</h3>';

if (empty($groups)) {
    echo '<div class="alert alert-warning">Nessun gruppo trovato nel database.</div>';
} else {
    echo '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">';
    echo '<thead><tr style="background: #f8f9fa;">';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">ID</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Nome</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Colore</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">KW</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Data Inizio DB</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Data Fine DB</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Data Inizio Corretta</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Data Fine Corretta</th>';
    echo '<th style="padding: 10px; border: 1px solid #ddd;">Stato</th>';
    echo '</tr></thead><tbody>';

    $issues = [];

    foreach ($groups as $g) {
        $kw = $g->calendar_week;
        $db_start = $g->entry_date ? date('Y-m-d', $g->entry_date) : '-';
        $db_end = $g->planned_end_date ? date('Y-m-d', $g->planned_end_date) : '-';

        $correct_start = '-';
        $correct_end = '-';
        $status = '';
        $row_style = '';

        if (isset($correct_data[$kw])) {
            $correct_start = $correct_data[$kw][0];
            $correct_end = $correct_data[$kw][1];

            $start_ok = ($db_start === $correct_start);
            $end_ok = ($db_end === $correct_end);

            if ($start_ok && $end_ok) {
                $status = '<span style="color: green;">✅ OK</span>';
            } else {
                $status = '<span style="color: red;">❌ Da correggere</span>';
                $row_style = 'background: #fff3cd;';
                $issues[] = [
                    'id' => $g->id,
                    'name' => $g->name,
                    'kw' => $kw,
                    'correct_start' => $correct_start,
                    'correct_end' => $correct_end,
                ];
            }
        } else {
            $status = '<span style="color: orange;">⚠️ KW non in tabella</span>';
            $row_style = 'background: #f8d7da;';
        }

        // Calcola KW effettiva dalla data inizio
        $actual_kw = '-';
        if ($g->entry_date) {
            $actual_kw = (int)date('W', $g->entry_date);
        }

        $kw_match = ($kw == $actual_kw) ? '' : ' <span style="color:red;">(KW reale: ' . $actual_kw . ')</span>';

        echo '<tr style="' . $row_style . '">';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $g->id . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . s($g->name) . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . s($g->color) . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;"><strong>' . $kw . '</strong>' . $kw_match . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $db_start . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $db_end . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd; color: blue;">' . $correct_start . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd; color: blue;">' . $correct_end . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $status . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Pulsante per correggere
    if (!empty($issues) && $action !== 'fix') {
        echo '<h3>Correzioni Necessarie: ' . count($issues) . '</h3>';
        echo '<form method="post" action="?action=fix">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<button type="submit" style="padding: 15px 30px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer;">';
        echo '🔧 Correggi ' . count($issues) . ' gruppi';
        echo '</button>';
        echo '</form>';
    }

    // Esegui correzioni
    if ($action === 'fix' && confirm_sesskey()) {
        echo '<h3>Esecuzione Correzioni...</h3>';
        echo '<ul>';

        foreach ($groups as $g) {
            $kw = $g->calendar_week;
            if (isset($correct_data[$kw])) {
                $correct_start = strtotime($correct_data[$kw][0]);
                $correct_end = strtotime($correct_data[$kw][1]);

                $needs_update = false;
                if ($g->entry_date != $correct_start || $g->planned_end_date != $correct_end) {
                    $needs_update = true;
                }

                if ($needs_update) {
                    $g->entry_date = $correct_start;
                    $g->planned_end_date = $correct_end;
                    $g->timemodified = time();
                    $DB->update_record('local_ftm_groups', $g);
                    echo '<li style="color: green;">✅ Gruppo ' . s($g->name) . ' (KW' . $kw . ') aggiornato: ' . $correct_data[$kw][0] . ' → ' . $correct_data[$kw][1] . '</li>';
                }
            }
        }

        echo '</ul>';
        echo '<div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-top: 20px;">';
        echo '<strong>✅ Correzioni completate!</strong>';
        echo '</div>';
        echo '<p><a href="?" style="display: inline-block; padding: 10px 20px; background: #0066cc; color: white; border-radius: 6px; text-decoration: none; margin-top: 15px;">🔄 Ricarica per verificare</a></p>';
    }
}

// Tabella di riferimento
echo '<hr style="margin: 30px 0;">';
echo '<h3>📅 Tabella di Riferimento (Dati Corretti)</h3>';
echo '<p>Durata standard: <strong>6 settimane</strong> per gruppo</p>';

echo '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
echo '<thead><tr style="background: #e9ecef;">';
echo '<th style="padding: 8px; border: 1px solid #ddd;">KW Inizio</th>';
echo '<th style="padding: 8px; border: 1px solid #ddd;">Data Inizio</th>';
echo '<th style="padding: 8px; border: 1px solid #ddd;">Data Fine</th>';
echo '<th style="padding: 8px; border: 1px solid #ddd;">Durata (giorni)</th>';
echo '</tr></thead><tbody>';

foreach ($correct_data as $kw => $dates) {
    $start = strtotime($dates[0]);
    $end = strtotime($dates[1]);
    $days = round(($end - $start) / 86400);

    echo '<tr>';
    echo '<td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><strong>KW' . str_pad($kw, 2, '0', STR_PAD_LEFT) . '</strong></td>';
    echo '<td style="padding: 6px; border: 1px solid #ddd;">' . date('d/m/Y', $start) . ' (' . date('l', $start) . ')</td>';
    echo '<td style="padding: 6px; border: 1px solid #ddd;">' . date('d/m/Y', $end) . ' (' . date('l', $end) . ')</td>';
    echo '<td style="padding: 6px; border: 1px solid #ddd; text-align: center;">' . $days . ' giorni (~' . round($days/7, 1) . ' sett.)</td>';
    echo '</tr>';
}

echo '</tbody></table>';

echo '<hr style="margin: 30px 0;">';
echo '<p><a href="' . new moodle_url('/local/ftm_scheduler/secretary_dashboard.php') . '" class="btn btn-secondary">← Torna alla Dashboard</a></p>';

echo '</div>';

echo $OUTPUT->footer();
