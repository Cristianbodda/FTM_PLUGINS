<?php
/**
 * Trova competenze con idnumber mal formattati
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/find_bad_idnumbers.php'));
$PAGE->set_title('Competenze con idnumber mal formattati');
$PAGE->set_heading('Competenze con idnumber mal formattati');

echo $OUTPUT->header();

echo '<div style="max-width: 1000px; margin: 0 auto; padding: 20px;">';
echo '<h2>Competenze con idnumber mal formattati</h2>';
echo '<p>Formato atteso: <code>SETTORE_AREA_NUMERO</code> (almeno 2 underscore)</p>';

// Trova TUTTE le competenze usate nelle domande e verifica in PHP
$all_comps = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.idnumber, c.shortname, c.competencyframeworkid, cf.shortname as framework_name,
           COUNT(qc.id) as usage_count
    FROM {competency} c
    JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
    LEFT JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
    GROUP BY c.id, c.idnumber, c.shortname, c.competencyframeworkid, cf.shortname
    ORDER BY c.idnumber
");

// Filtra in PHP contando gli underscore
$bad_comps = [];
foreach ($all_comps as $comp) {
    $underscore_count = substr_count($comp->idnumber ?? '', '_');
    if (empty($comp->idnumber) || $underscore_count < 2) {
        $comp->underscore_count = $underscore_count;
        $bad_comps[] = $comp;
    }
}

// Debug: mostra anche il totale
echo '<div style="background: #e7f3ff; padding: 10px; border-radius: 6px; margin-bottom: 15px;">';
echo '<strong>Debug:</strong> Totale competenze usate: ' . count($all_comps) . ' | ';
echo 'Con formato errato (PHP check): ' . count($bad_comps);
echo '</div>';

if (empty($bad_comps)) {
    echo '<div style="background: #d4edda; padding: 20px; border-radius: 8px; color: #155724;">';
    echo '<strong>Nessuna competenza mal formattata trovata.</strong>';
    echo '</div>';
} else {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<strong>Trovate ' . count($bad_comps) . ' competenze con idnumber non valido:</strong>';
    echo '</div>';

    echo '<table style="width: 100%; border-collapse: collapse; background: white;">';
    echo '<thead><tr style="background: #f8f9fa;">';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">ID</th>';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">idnumber</th>';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Nome</th>';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Framework</th>';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Usato in</th>';
    echo '<th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Problema</th>';
    echo '</tr></thead><tbody>';

    foreach ($bad_comps as $comp) {
        // Determina il problema
        if (empty($comp->idnumber)) {
            $problem = '<span style="color: #dc3545;">idnumber vuoto/NULL</span>';
        } else {
            $underscores = substr_count($comp->idnumber, '_');
            $problem = "<span style='color: #fd7e14;'>Solo {$underscores} underscore (servono almeno 2)</span>";
        }

        $idnumber_display = empty($comp->idnumber)
            ? '<em style="color: #999;">(vuoto)</em>'
            : '<code>' . s($comp->idnumber) . '</code>';

        echo '<tr>';
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$comp->id}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$idnumber_display}</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . s($comp->shortname) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . s($comp->framework_name) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: center;'>{$comp->usage_count} domande</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>{$problem}</td>";
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Suggerimenti
    echo '<div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 8px;">';
    echo '<h4 style="margin: 0 0 10px 0;">Come correggere:</h4>';
    echo '<ol style="margin: 0; padding-left: 20px;">';
    echo '<li>Vai su <strong>Amministrazione > Competenze > Framework</strong></li>';
    echo '<li>Trova la competenza e modifica l\'idnumber nel formato <code>SETTORE_AREA_NN</code></li>';
    echo '<li>Esempio: <code>MECCANICA_A1_01</code>, <code>AUTOMOBILE_B2_03</code></li>';
    echo '</ol>';
    echo '</div>';
}

echo '</div>';

echo '<p style="margin-top: 20px;"><a href="index.php">&larr; Torna alla Dashboard</a></p>';

echo $OUTPUT->footer();
