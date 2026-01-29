<?php
// Verifica formato idnumber competenze di Fabio
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/check_fabio_comps.php'));

echo $OUTPUT->header();
echo '<h1>Competenze assegnate a Fabio</h1>';

// Trova Fabio
$fabio = $DB->get_record_sql("
    SELECT * FROM {user}
    WHERE firstname LIKE '%Fabio%'
    OR username LIKE '%fabio%'
    LIMIT 1
");

if (!$fabio) {
    echo '<p>Fabio non trovato!</p>';
    echo $OUTPUT->footer();
    exit;
}

echo "<p>Utente: " . fullname($fabio) . " (ID: {$fabio->id})</p>";

// Trova competenze assegnate
$assignments = $DB->get_records_sql("
    SELECT a.*, c.idnumber, c.shortname, c.description
    FROM {local_selfassessment_assign} a
    JOIN {competency} c ON c.id = a.competencyid
    WHERE a.userid = ?
    ORDER BY c.idnumber
", [$fabio->id]);

echo "<p>Totale: " . count($assignments) . " competenze</p>";

// Raggruppa per prefisso
$prefixes = [];
foreach ($assignments as $a) {
    // Estrai prefisso (prima parte fino al primo underscore o primi 10 caratteri)
    $parts = explode('_', $a->idnumber);
    $prefix = $parts[0];
    if (!isset($prefixes[$prefix])) {
        $prefixes[$prefix] = [];
    }
    $prefixes[$prefix][] = $a->idnumber;
}

echo '<h2>Competenze raggruppate per prefisso</h2>';
echo '<table border="1" cellpadding="5"><tr><th>Prefisso</th><th>Conteggio</th><th>Esempi</th></tr>';
foreach ($prefixes as $prefix => $items) {
    $examples = array_slice($items, 0, 3);
    echo "<tr><td><strong>{$prefix}</strong></td><td>" . count($items) . "</td><td>" . implode('<br>', $examples) . "</td></tr>";
}
echo '</table>';

// Mostra tutte le competenze che finiscono in "ALTRO"
echo '<h2>Competenze che andrebbero in "ALTRO"</h2>';
$area_map_prefixes = [
    'AUTOMOBILE', 'MECCANICA', 'LOGISTICA', 'AUTOMAZIONE', 'AUTO_EA',
    'ELETTRONICA', 'MECC_', 'CHIMFARM', 'CHIMICA', 'METALCOSTRUZIONE',
    'METAL_', 'OLD_LOGISTICA', 'OLD_MECCANICA', 'OLD_AUTOMOBILE',
    'OLD_CHIMFARM', 'OLD_CHIMICA'
];

$altro = [];
foreach ($assignments as $a) {
    $matched = false;
    foreach ($area_map_prefixes as $prefix) {
        if (strpos($a->idnumber, $prefix) === 0) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $altro[] = $a->idnumber;
    }
}

if (empty($altro)) {
    echo '<p style="color: green;">Nessuna competenza finirà in "ALTRO"!</p>';
} else {
    echo '<p style="color: red;">Le seguenti ' . count($altro) . ' competenze finiranno in "ALTRO":</p>';
    echo '<ul>';
    foreach ($altro as $idn) {
        echo "<li>{$idn}</li>";
    }
    echo '</ul>';
}

echo $OUTPUT->footer();
