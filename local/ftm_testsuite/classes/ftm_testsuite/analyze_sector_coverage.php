<?php
/**
 * Analisi copertura competenze per settore
 *
 * Identifica le competenze NON coperte da domande quiz
 * per ogni settore del framework FTM.
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/analyze_sector_coverage.php'));
$PAGE->set_title('Analisi Copertura Settori - FTM Test Suite');
$PAGE->set_heading('Analisi Copertura Competenze per Settore');
$PAGE->set_pagelayout('admin');

$frameworkid = optional_param('frameworkid', 9, PARAM_INT); // Default: Passaporto tecnico FTM
$sector = optional_param('sector', '', PARAM_ALPHA);
$export = optional_param('export', '', PARAM_ALPHA);

// Se richiesto export CSV
if ($export === 'csv' && $sector) {
    export_missing_csv($frameworkid, $sector);
    exit;
}

echo $OUTPUT->header();

echo '<div style="max-width: 1400px; margin: 0 auto;">';

// Header
echo '<div style="background: linear-gradient(135deg, #fd7e14 0%, #ffa94d 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px;">';
echo '<h2 style="margin: 0 0 10px 0;">📊 Analisi Copertura Competenze</h2>';
echo '<p style="margin: 0; opacity: 0.9;">Identifica le competenze NON coperte da domande quiz per raggiungere il 100%.</p>';
echo '</div>';

// Selettore framework
$frameworks = $DB->get_records('competency_framework', [], 'shortname');
echo '<div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
echo '<form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">';
echo '<label><strong>Framework:</strong></label>';
echo '<select name="frameworkid" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-width: 300px;">';
foreach ($frameworks as $fw) {
    $selected = ($fw->id == $frameworkid) ? 'selected' : '';
    echo "<option value=\"{$fw->id}\" {$selected}>" . s($fw->shortname) . " (ID: {$fw->id})</option>";
}
echo '</select>';
echo '<button type="submit" style="padding: 10px 20px; background: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer;">Analizza</button>';
echo '</form>';
echo '</div>';

// Calcola copertura per ogni settore
$sectors_coverage = $DB->get_records_sql("
    SELECT
        SUBSTRING_INDEX(c.idnumber, '_', 1) as sector,
        COUNT(DISTINCT c.id) as total_competencies,
        COUNT(DISTINCT CASE WHEN qc.id IS NOT NULL THEN c.id END) as covered_competencies
    FROM {competency} c
    LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
    WHERE c.competencyframeworkid = ?
      AND c.idnumber LIKE '%\\_%\\_%'
    GROUP BY sector
    ORDER BY sector
", [$frameworkid]);

// Mostra riepilogo settori
echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">';
echo '<h3 style="margin: 0 0 15px 0;">📈 Riepilogo Copertura per Settore</h3>';

echo '<table style="width: 100%; border-collapse: collapse;">';
echo '<thead><tr style="background: #f8f9fa;">';
echo '<th style="padding: 12px; text-align: left;">Settore</th>';
echo '<th style="padding: 12px; text-align: center;">Totale</th>';
echo '<th style="padding: 12px; text-align: center;">Coperte</th>';
echo '<th style="padding: 12px; text-align: center;">Mancanti</th>';
echo '<th style="padding: 12px; text-align: center;">Copertura</th>';
echo '<th style="padding: 12px; text-align: center;">Azioni</th>';
echo '</tr></thead><tbody>';

foreach ($sectors_coverage as $sc) {
    $missing = $sc->total_competencies - $sc->covered_competencies;
    $pct = $sc->total_competencies > 0 ? round(($sc->covered_competencies / $sc->total_competencies) * 100, 1) : 0;

    // Colore barra in base a copertura
    if ($pct >= 80) {
        $bar_color = '#28a745';
        $status = '✅';
    } elseif ($pct >= 50) {
        $bar_color = '#ffc107';
        $status = '⚠️';
    } else {
        $bar_color = '#dc3545';
        $status = '❌';
    }

    $selected_class = ($sector === $sc->sector) ? 'background: #fff3cd;' : '';

    echo "<tr style='border-bottom: 1px solid #eee; {$selected_class}'>";
    echo "<td style='padding: 12px;'><strong>{$sc->sector}</strong></td>";
    echo "<td style='padding: 12px; text-align: center;'>{$sc->total_competencies}</td>";
    echo "<td style='padding: 12px; text-align: center; color: #28a745;'>{$sc->covered_competencies}</td>";
    echo "<td style='padding: 12px; text-align: center; color: #dc3545; font-weight: bold;'>{$missing}</td>";
    echo "<td style='padding: 12px; text-align: center;'>";
    echo "<div style='display: flex; align-items: center; justify-content: center; gap: 10px;'>";
    echo "<div style='width: 100px; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden;'>";
    echo "<div style='width: {$pct}%; height: 100%; background: {$bar_color};'></div>";
    echo "</div>";
    echo "<span style='font-weight: bold;'>{$pct}%</span> {$status}";
    echo "</div>";
    echo "</td>";
    echo "<td style='padding: 12px; text-align: center;'>";
    if ($missing > 0) {
        $url = new moodle_url('/local/ftm_testsuite/analyze_sector_coverage.php', [
            'frameworkid' => $frameworkid,
            'sector' => $sc->sector
        ]);
        echo "<a href='{$url}' style='background: #fd7e14; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 12px;'>🔍 Dettagli</a>";
    } else {
        echo "<span style='color: #28a745;'>✓ Completo</span>";
    }
    echo "</td>";
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';

// Se selezionato un settore, mostra dettagli competenze mancanti
if ($sector) {
    // Competenze mancanti (non coperte da domande)
    $missing_competencies = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname, c.description
        FROM {competency} c
        WHERE c.competencyframeworkid = ?
          AND c.idnumber LIKE ?
          AND NOT EXISTS (
              SELECT 1 FROM {qbank_competenciesbyquestion} qc
              WHERE qc.competencyid = c.id
          )
        ORDER BY c.idnumber
    ", [$frameworkid, $sector . '_%']);

    $missing_count = count($missing_competencies);

    // Competenze coperte
    $covered_competencies = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname, c.description,
               COUNT(DISTINCT qc.questionid) as question_count
        FROM {competency} c
        JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
        WHERE c.competencyframeworkid = ?
          AND c.idnumber LIKE ?
        GROUP BY c.id, c.idnumber, c.shortname, c.description
        ORDER BY c.idnumber
    ", [$frameworkid, $sector . '_%']);

    $covered_count = count($covered_competencies);
    $total = $missing_count + $covered_count;
    $pct = $total > 0 ? round(($covered_count / $total) * 100, 1) : 0;

    echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">';
    echo "<h3 style='margin: 0 0 15px 0;'>🔍 Dettaglio Settore: <span style='color: #fd7e14;'>{$sector}</span></h3>";

    // Statistiche settore
    echo '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">';
    echo "<div style='padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 28px; font-weight: bold; color: #1e3c72;'>{$total}</div>";
    echo "<div style='font-size: 12px; color: #666;'>Totale Competenze</div>";
    echo "</div>";
    echo "<div style='padding: 15px; background: #d4edda; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 28px; font-weight: bold; color: #28a745;'>{$covered_count}</div>";
    echo "<div style='font-size: 12px; color: #155724;'>Coperte ✅</div>";
    echo "</div>";
    echo "<div style='padding: 15px; background: #f8d7da; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 28px; font-weight: bold; color: #dc3545;'>{$missing_count}</div>";
    echo "<div style='font-size: 12px; color: #721c24;'>Mancanti ❌</div>";
    echo "</div>";
    echo "<div style='padding: 15px; background: #fff3cd; border-radius: 8px; text-align: center;'>";
    echo "<div style='font-size: 28px; font-weight: bold; color: #856404;'>{$pct}%</div>";
    echo "<div style='font-size: 12px; color: #856404;'>Copertura</div>";
    echo "</div>";
    echo '</div>';

    // Link export CSV
    $export_url = new moodle_url('/local/ftm_testsuite/analyze_sector_coverage.php', [
        'frameworkid' => $frameworkid,
        'sector' => $sector,
        'export' => 'csv'
    ]);
    echo "<p style='margin-bottom: 15px;'><a href='{$export_url}' style='background: #28a745; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none;'>📥 Esporta Competenze Mancanti (CSV)</a></p>";

    // Tabella competenze MANCANTI
    echo '<h4 style="color: #dc3545; margin: 20px 0 10px 0;">❌ Competenze MANCANTI ({$missing_count})</h4>';
    echo '<p style="color: #666; font-size: 13px; margin-bottom: 10px;">Queste competenze non hanno domande associate nei quiz. Devi creare domande per queste competenze.</p>';

    if (!empty($missing_competencies)) {
        echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        echo '<thead><tr style="background: #f8d7da;">';
        echo '<th style="padding: 8px; text-align: left;">ID Number</th>';
        echo '<th style="padding: 8px; text-align: left;">Nome</th>';
        echo '<th style="padding: 8px; text-align: left;">Descrizione</th>';
        echo '</tr></thead><tbody>';

        foreach ($missing_competencies as $c) {
            $desc = strip_tags($c->description ?? '');
            $desc = strlen($desc) > 100 ? substr($desc, 0, 97) . '...' : $desc;

            echo '<tr style="border-bottom: 1px solid #eee;">';
            echo "<td style='padding: 8px; font-family: monospace; color: #dc3545;'><strong>{$c->idnumber}</strong></td>";
            echo "<td style='padding: 8px;'>" . s($c->shortname) . "</td>";
            echo "<td style='padding: 8px; color: #666;'>" . s($desc) . "</td>";
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    // Tabella competenze COPERTE (collassabile)
    echo '<details style="margin-top: 20px;">';
    echo "<summary style='cursor: pointer; padding: 10px; background: #d4edda; border-radius: 6px; font-weight: bold; color: #155724;'>✅ Competenze COPERTE ({$covered_count}) - clicca per espandere</summary>";

    if (!empty($covered_competencies)) {
        echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px;">';
        echo '<thead><tr style="background: #d4edda;">';
        echo '<th style="padding: 8px; text-align: left;">ID Number</th>';
        echo '<th style="padding: 8px; text-align: left;">Nome</th>';
        echo '<th style="padding: 8px; text-align: center;">N° Domande</th>';
        echo '</tr></thead><tbody>';

        foreach ($covered_competencies as $c) {
            echo '<tr style="border-bottom: 1px solid #eee;">';
            echo "<td style='padding: 8px; font-family: monospace; color: #28a745;'>{$c->idnumber}</td>";
            echo "<td style='padding: 8px;'>" . s($c->shortname) . "</td>";
            echo "<td style='padding: 8px; text-align: center;'><span style='background: #e3f2fd; padding: 2px 8px; border-radius: 10px;'>{$c->question_count}</span></td>";
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</details>';
    echo '</div>';

    // Suggerimenti per completare la copertura
    echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #1976d2;">';
    echo '<h4 style="margin: 0 0 10px 0; color: #1565c0;">💡 Come completare la copertura al 100%</h4>';
    echo '<ol style="margin: 0; padding-left: 20px; color: #1565c0;">';
    echo '<li>Esporta il CSV delle competenze mancanti</li>';
    echo '<li>Per ogni competenza, crea almeno una domanda nel question bank</li>';
    echo '<li>Usa <strong>Setup Universale</strong> per importare domande da file XML/Word</li>';
    echo '<li>Assicurati che ogni domanda abbia il codice competenza nel testo (es. <code>' . $sector . '_XX_XX</code>)</li>';
    echo '<li>Riesegui questa analisi per verificare la copertura</li>';
    echo '</ol>';
    echo '</div>';
}

echo '</div>';

// Link ritorno
echo '<p style="margin-top: 20px;">';
echo '<a href="index.php" style="color: #1e3c72;">← Torna alla Dashboard</a> | ';
echo '<a href="run.php" style="color: #1e3c72;">Esegui Test Suite</a> | ';
echo '<a href="find_orphan_questions.php" style="color: #1e3c72;">Trova Domande Orfane</a>';
echo '</p>';

echo $OUTPUT->footer();

/**
 * Esporta le competenze mancanti in CSV
 */
function export_missing_csv($frameworkid, $sector) {
    global $DB;

    $missing = $DB->get_records_sql("
        SELECT c.id, c.idnumber, c.shortname, c.description
        FROM {competency} c
        WHERE c.competencyframeworkid = ?
          AND c.idnumber LIKE ?
          AND NOT EXISTS (
              SELECT 1 FROM {qbank_competenciesbyquestion} qc
              WHERE qc.competencyid = c.id
          )
        ORDER BY c.idnumber
    ", [$frameworkid, $sector . '_%']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="competenze_mancanti_' . $sector . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM per Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, ['ID Number', 'Nome', 'Descrizione'], ';');

    foreach ($missing as $c) {
        fputcsv($output, [
            $c->idnumber,
            $c->shortname,
            strip_tags($c->description ?? '')
        ], ';');
    }

    fclose($output);
}
