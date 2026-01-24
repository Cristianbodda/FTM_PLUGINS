<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Diagnostic tool for Word template merge fields.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/diagnose_template.php'));
$PAGE->set_title('Diagnosi Template Word');

echo $OUTPUT->header();

echo '<h2>Diagnosi Template Word CPURC</h2>';

$templatepath = $CFG->dirroot . '/local/ftm_cpurc/templates/rapporto_finale_template.docx';

if (!file_exists($templatepath)) {
    echo '<div class="alert alert-danger">Template non trovato: ' . s($templatepath) . '</div>';
    echo $OUTPUT->footer();
    die();
}

echo '<div class="alert alert-info">Template trovato: ' . s($templatepath) . '</div>';

// Open template and extract merge fields
$zip = new ZipArchive();
if ($zip->open($templatepath) !== true) {
    echo '<div class="alert alert-danger">Impossibile aprire il template</div>';
    echo $OUTPUT->footer();
    die();
}

$documentXml = $zip->getFromName('word/document.xml');
$zip->close();

if ($documentXml === false) {
    echo '<div class="alert alert-danger">document.xml non trovato nel template</div>';
    echo $OUTPUT->footer();
    die();
}

// Find all merge fields with pattern «...»
preg_match_all('/«([^»]+)»/', $documentXml, $matches);
$mergeFields = array_unique($matches[1]);
sort($mergeFields);

echo '<h3>Merge Fields trovati nel template (' . count($mergeFields) . '):</h3>';
echo '<div style="background: #f5f5f5; padding: 15px; border-radius: 8px; max-height: 400px; overflow-y: auto;">';
echo '<table class="table table-sm">';
echo '<thead><tr><th>#</th><th>Merge Field</th><th>Pattern</th></tr></thead>';
echo '<tbody>';
foreach ($mergeFields as $idx => $field) {
    echo '<tr>';
    echo '<td>' . ($idx + 1) . '</td>';
    echo '<td><code>' . s($field) . '</code></td>';
    echo '<td><code>«' . s($field) . '»</code></td>';
    echo '</tr>';
}
echo '</tbody></table>';
echo '</div>';

// Check for patterns that might be split across XML tags
echo '<h3 style="margin-top: 20px;">Analisi contenuto XML (cerca pattern testo narrativo):</h3>';

// Find text content that might indicate where observations should go
$patterns = [
    'Situazione iniziale',
    'Valutazione delle competenze',
    'Possibili settori',
    'Sintesi conclusiva',
    'Osservazioni',
    'Competenze personali',
    'Competenze sociali',
    'Competenze metodologiche',
    'Canali',
    'ricerca',
];

echo '<ul>';
foreach ($patterns as $pattern) {
    if (stripos($documentXml, $pattern) !== false) {
        echo '<li style="color: green;">✓ Trovato: <strong>' . s($pattern) . '</strong></li>';
    } else {
        echo '<li style="color: red;">✗ Non trovato: <strong>' . s($pattern) . '</strong></li>';
    }
}
echo '</ul>';

// Show a sample of the XML around key sections
// Check plugin version in database vs file
echo '<h3 style="margin-top: 20px;">Verifica versione plugin:</h3>';
$dbversion = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_ftm_cpurc', 'name' => 'version']);
$fileversion = get_config('local_ftm_cpurc', 'version');

echo '<table class="table table-bordered" style="max-width: 500px;">';
echo '<tr><th>Versione nel DB</th><td>' . ($dbversion ?: 'NON TROVATA') . '</td></tr>';
echo '<tr><th>Versione attesa</th><td>2026012305</td></tr>';
echo '</table>';

if ($dbversion == '2026012305') {
    echo '<div class="alert alert-warning">';
    echo '<strong>Problema identificato:</strong> Il database ha già la versione 2026012305 ma le colonne non esistono.<br>';
    echo 'Questo significa che l\'upgrade è stato "saltato". Moodle pensa che sia già fatto.<br><br>';
    echo '<strong>Soluzione:</strong> Esegui <code>/local/ftm_cpurc/manual_upgrade.php</code> per aggiungere le colonne, ';
    echo 'oppure resetta la versione nel DB a 2026011601 e rifai l\'upgrade.';
    echo '</div>';
} else if (!$dbversion) {
    echo '<div class="alert alert-danger">Plugin non installato correttamente nel database!</div>';
} else if ($dbversion < 2026012305) {
    echo '<div class="alert alert-info">Versione nel DB più vecchia - l\'upgrade DOVREBBE partire da /admin/index.php</div>';
}

// Check if database columns exist
echo '<h3 style="margin-top: 20px;">Verifica colonne database:</h3>';
$dbman = $DB->get_manager();
$table = new xmldb_table('local_ftm_cpurc_reports');
$newColumns = ['possible_sectors', 'final_summary', 'obs_personal', 'obs_social', 'obs_methodological', 'obs_search_channels', 'obs_search_evaluation'];

echo '<ul>';
foreach ($newColumns as $col) {
    $field = new xmldb_field($col);
    if ($dbman->field_exists($table, $field)) {
        echo '<li style="color: green;">✓ Colonna <code>' . $col . '</code> ESISTE nel database</li>';
    } else {
        echo '<li style="color: red;">✗ Colonna <code>' . $col . '</code> NON ESISTE - Esegui upgrade da /admin/index.php</li>';
    }
}
echo '</ul>';

echo '<h3 style="margin-top: 20px;">Verifica dati report salvati:</h3>';

$studentid = optional_param('id', 0, PARAM_INT);
if ($studentid > 0) {
    $report = $DB->get_record('local_ftm_cpurc_reports', ['studentid' => $studentid]);
    if ($report) {
        echo '<div class="alert alert-success">Report trovato per studentid=' . $studentid . '</div>';
        echo '<table class="table table-bordered">';
        echo '<tr><th>Campo</th><th>Valore</th></tr>';

        $fields = [
            'initial_situation' => 'Situazione iniziale',
            'sector_competency_text' => 'Competenze settore',
            'possible_sectors' => 'Possibili settori',
            'final_summary' => 'Sintesi conclusiva',
            'obs_personal' => 'Oss. personali',
            'obs_social' => 'Oss. sociali',
            'obs_methodological' => 'Oss. metodologiche',
            'obs_search_channels' => 'Oss. canali ricerca',
            'obs_search_evaluation' => 'Oss. valutazione ricerca',
        ];

        foreach ($fields as $field => $label) {
            $value = $report->$field ?? '<em>(vuoto)</em>';
            $color = !empty($report->$field) ? 'green' : 'gray';
            echo '<tr>';
            echo '<td>' . s($label) . '</td>';
            echo '<td style="color: ' . $color . ';">' . s(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="alert alert-warning">Nessun report trovato per studentid=' . $studentid . '</div>';
    }
} else {
    echo '<p>Aggiungi <code>?id=STUDENTID</code> alla URL per verificare i dati salvati di uno studente.</p>';
}

echo '<h3 style="margin-top: 20px;">Mappatura attuale word_exporter.php:</h3>';
echo '<p>I seguenti merge field sono configurati ma <strong>devono esistere nel template</strong>:</p>';
echo '<ul>';
$mappings = [
    'F3' => 'Nome completo',
    'F6' => 'Indirizzo',
    'F7' => 'CAP',
    'F8' => 'Città',
    'F11' => 'Numero AVS',
    'F20' => 'Iniziali coach',
    'F22' => 'Data inizio',
    'F23' => 'Data fine prevista',
    'F24' => 'Data fine effettiva',
    'SITUAZIONE_INIZIALE' => 'Testo situazione iniziale (NUOVO)',
    'POSSIBILI_SETTORI' => 'Possibili settori (NUOVO)',
    'SINTESI_CONCLUSIVA' => 'Sintesi conclusiva (NUOVO)',
    'OSS_PERSONALI' => 'Osservazioni personali (NUOVO)',
    'OSS_SOCIALI' => 'Osservazioni sociali (NUOVO)',
    'OSS_METODOLOGICHE' => 'Osservazioni metodologiche (NUOVO)',
    'OSS_CANALI_RICERCA' => 'Osservazioni canali (NUOVO)',
    'OSS_VALUTAZIONE_RICERCA' => 'Osservazioni valutazione (NUOVO)',
];

foreach ($mappings as $field => $desc) {
    $exists = in_array($field, $mergeFields);
    $icon = $exists ? '✓' : '✗';
    $color = $exists ? 'green' : 'red';
    echo '<li style="color: ' . $color . ';">' . $icon . ' <code>«' . s($field) . '»</code> - ' . s($desc) . '</li>';
}
echo '</ul>';

echo '<div class="alert alert-warning" style="margin-top: 20px;">';
echo '<strong>AZIONE RICHIESTA:</strong><br>';
echo 'Per far funzionare l\'export dei campi narrativi, devi aggiungere i merge field nel template Word.<br>';
echo 'Apri il template in Word, posizionati dove vuoi inserire il testo e inserisci il campo unione con il nome esatto (es. <code>«SITUAZIONE_INIZIALE»</code>).';
echo '</div>';

// Debug: show raw XML around new field names
echo '<h3 style="margin-top: 20px;">Debug XML - Ricerca campi inseriti:</h3>';

$searchTerms = ['SITUAZIONE', 'INIZIALE', 'OSS_', 'POSSIBILI', 'SINTESI'];
foreach ($searchTerms as $term) {
    $pos = stripos($documentXml, $term);
    if ($pos !== false) {
        $start = max(0, $pos - 100);
        $length = 300;
        $excerpt = substr($documentXml, $start, $length);
        // Make it readable
        $excerpt = htmlspecialchars($excerpt);
        echo '<div style="background: #ffe; padding: 10px; margin: 10px 0; border: 1px solid #cc0; font-family: monospace; font-size: 11px; word-break: break-all;">';
        echo '<strong>Trovato "' . s($term) . '":</strong><br>';
        echo $excerpt;
        echo '</div>';
    } else {
        echo '<p style="color: red;">Non trovato: ' . s($term) . '</p>';
    }
}

// Show what characters are being used for « »
echo '<h3 style="margin-top: 20px;">Debug caratteri « »:</h3>';
preg_match_all('/(.{0,5})[«»](.{0,5})/', $documentXml, $charMatches);
if (!empty($charMatches[0])) {
    echo '<p>Esempi di utilizzo dei caratteri « »:</p>';
    echo '<ul>';
    $shown = 0;
    foreach (array_unique($charMatches[0]) as $match) {
        if ($shown >= 10) break;
        echo '<li><code>' . htmlspecialchars($match) . '</code></li>';
        $shown++;
    }
    echo '</ul>';
}

// Check for the specific new fields we're looking for
echo '<h3>Verifica campi specifici nel documento:</h3>';
$checkFields = ['SITUAZIONE_INIZIALE', 'OSS_PERSONALI', 'OSS_SOCIALI', 'OSS_METODOLOGICHE', 'POSSIBILI_SETTORI', 'SINTESI_CONCLUSIVA'];
foreach ($checkFields as $field) {
    // Check exact match
    if (strpos($documentXml, '«' . $field . '»') !== false) {
        echo '<p style="color: green;">✓ <code>«' . $field . '»</code> trovato INTATTO</p>';
    } else if (stripos($documentXml, $field) !== false) {
        echo '<p style="color: orange;">⚠ <code>' . $field . '</code> trovato ma SPEZZATO nell\'XML</p>';
    } else {
        echo '<p style="color: red;">✗ <code>' . $field . '</code> NON trovato</p>';
    }
}

echo $OUTPUT->footer();
