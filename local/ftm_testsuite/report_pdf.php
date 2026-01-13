<?php
/**
 * FTM Test Suite - Genera PDF Certificazione
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/test_manager.php');
require_once($CFG->libdir . '/pdflib.php');

use local_ftm_testsuite\test_manager;

require_login();
require_capability('local/ftm_testsuite:viewresults', context_system::instance());

$runid = required_param('fts_runid', PARAM_INT);

$run = test_manager::get_run($runid);
if (!$run) {
    throw new moodle_exception('Run non trovato');
}

// Carica dati aggiuntivi
global $DB;
$executor = $DB->get_record('user', ['id' => $run->executedby]);
$testuser = $DB->get_record('user', ['id' => $run->testuserid]);

// Raggruppa risultati per modulo
$modules = [
    'quiz' => ['name' => 'Quiz e Competenze', 'results' => []],
    'selfassessment' => ['name' => 'Autovalutazioni', 'results' => []],
    'labeval' => ['name' => 'LabEval', 'results' => []],
    'radar' => ['name' => 'Radar e Aggregazione', 'results' => []],
    'report' => ['name' => 'Report', 'results' => []]
];

foreach ($run->results as $r) {
    if (isset($modules[$r->module])) {
        $modules[$r->module]['results'][] = $r;
    }
}

// Crea PDF
$pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('FTM Test Suite');
$pdf->SetAuthor(fullname($executor));
$pdf->SetTitle('Certificazione Sistema FTM - ' . $run->name);
$pdf->SetSubject('Report Verifica Pre-Produzione');

// Rimuovi header/footer default
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->setFooterData(array(0,0,0), array(0,0,0));
$pdf->setFooterFont(Array('helvetica', '', 8));

$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->SetFont('helvetica', '', 10);

// === PAGINA 1: COPERTINA ===
$pdf->AddPage();

// Logo/Titolo
$pdf->SetFont('helvetica', 'B', 28);
$pdf->SetTextColor(30, 60, 114);
$pdf->Cell(0, 20, 'CERTIFICAZIONE SISTEMA FTM', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 14);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 10, 'Report Verifica Pre-Produzione', 0, 1, 'C');

$pdf->Ln(20);

// Box riepilogo
$pdf->SetFillColor(240, 240, 240);
$pdf->SetDrawColor(200, 200, 200);

$status_color = $run->status === 'completed' ? array(40, 167, 69) : array(220, 53, 69);
$status_text = $run->status === 'completed' ? 'SUPERATO' : 'NON SUPERATO';

$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor($status_color[0], $status_color[1], $status_color[2]);
$pdf->Cell(0, 20, ($run->status === 'completed' ? '✓ ' : '✗ ') . $status_text, 0, 1, 'C');

$pdf->Ln(10);

// Info test
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 12);

$info_html = '
<table cellpadding="8" style="width: 100%;">
    <tr>
        <td style="width: 40%; background-color: #f8f9fa; font-weight: bold;">Nome Test:</td>
        <td style="width: 60%;">' . $run->name . '</td>
    </tr>
    <tr>
        <td style="background-color: #f8f9fa; font-weight: bold;">Data Esecuzione:</td>
        <td>' . userdate($run->timecreated, '%d/%m/%Y alle %H:%M:%S') . '</td>
    </tr>
    <tr>
        <td style="background-color: #f8f9fa; font-weight: bold;">Eseguito da:</td>
        <td>' . fullname($executor) . '</td>
    </tr>
    <tr>
        <td style="background-color: #f8f9fa; font-weight: bold;">Tasso di Successo:</td>
        <td style="font-weight: bold; color: ' . ($run->success_rate >= 80 ? '#28a745' : '#dc3545') . ';">' . $run->success_rate . '%</td>
    </tr>
</table>
';
$pdf->writeHTML($info_html, true, false, true, false, '');

$pdf->Ln(10);

// Riepilogo numerico
$summary_html = '
<table cellpadding="10" style="width: 100%;">
    <tr>
        <td style="width: 25%; text-align: center; background-color: #e9ecef;">
            <span style="font-size: 24px; font-weight: bold;">' . $run->total_tests . '</span><br/>
            <span style="font-size: 10px;">TEST TOTALI</span>
        </td>
        <td style="width: 25%; text-align: center; background-color: #d4edda;">
            <span style="font-size: 24px; font-weight: bold; color: #28a745;">' . $run->passed_tests . '</span><br/>
            <span style="font-size: 10px; color: #28a745;">PASSATI</span>
        </td>
        <td style="width: 25%; text-align: center; background-color: #f8d7da;">
            <span style="font-size: 24px; font-weight: bold; color: #dc3545;">' . $run->failed_tests . '</span><br/>
            <span style="font-size: 10px; color: #dc3545;">FALLITI</span>
        </td>
        <td style="width: 25%; text-align: center; background-color: #fff3cd;">
            <span style="font-size: 24px; font-weight: bold; color: #856404;">' . $run->warning_tests . '</span><br/>
            <span style="font-size: 10px; color: #856404;">WARNING</span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($summary_html, true, false, true, false, '');

// Hash integrità
if ($run->hash_integrity) {
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'CERTIFICATO DI INTEGRITÀ', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 6, 'Hash SHA-256: ' . $run->hash_integrity, 0, 'L');
    $pdf->MultiCell(0, 6, 'Questo hash certifica che i risultati non sono stati modificati dopo l\'esecuzione del test.', 0, 'L');
}

// === PAGINA 2+: RISULTATI DETTAGLIATI ===
$pdf->AddPage();
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, '1. RISULTATI DETTAGLIATI PER MODULO', 0, 1, 'L');
$pdf->Ln(5);

foreach ($modules as $key => $mod) {
    if (empty($mod['results'])) continue;
    
    $passed = count(array_filter($mod['results'], fn($r) => $r->status === 'passed'));
    $failed = count(array_filter($mod['results'], fn($r) => $r->status === 'failed'));
    $warning = count(array_filter($mod['results'], fn($r) => $r->status === 'warning'));
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(30, 60, 114);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, ' ' . strtoupper($mod['name']) . ' (' . $passed . '/' . count($mod['results']) . ' passati)', 1, 1, 'L', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $table_html = '<table cellpadding="4" border="1" style="border-collapse: collapse;">
        <tr style="background-color: #f8f9fa; font-weight: bold;">
            <td style="width: 8%;">Cod.</td>
            <td style="width: 32%;">Test</td>
            <td style="width: 12%;">Stato</td>
            <td style="width: 18%;">Atteso</td>
            <td style="width: 18%;">Ottenuto</td>
            <td style="width: 12%;">Tempo</td>
        </tr>';
    
    foreach ($mod['results'] as $r) {
        $status_bg = $r->status === 'passed' ? '#d4edda' : ($r->status === 'failed' ? '#f8d7da' : ($r->status === 'warning' ? '#fff3cd' : '#e9ecef'));
        $status_icon = $r->status === 'passed' ? '✓' : ($r->status === 'failed' ? '✗' : ($r->status === 'warning' ? '!' : '–'));
        
        $table_html .= '<tr>
            <td>' . $r->testcode . '</td>
            <td>' . htmlspecialchars($r->testname) . '</td>
            <td style="background-color: ' . $status_bg . '; text-align: center;">' . $status_icon . ' ' . ucfirst($r->status) . '</td>
            <td>' . htmlspecialchars(substr($r->expected_value, 0, 30)) . '</td>
            <td>' . htmlspecialchars(substr($r->actual_value, 0, 30)) . '</td>
            <td>' . round($r->execution_time * 1000, 1) . ' ms</td>
        </tr>';
    }
    
    $table_html .= '</table>';
    $pdf->writeHTML($table_html, true, false, true, false, '');
    $pdf->Ln(5);
}

// === PAGINA: TRACCIABILITÀ CALCOLI ===
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, '2. TRACCIABILITÀ CALCOLI', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 6, 'Questa sezione mostra come vengono calcolati i valori mostrati nel sistema. Ogni calcolo è verificabile step-by-step.', 0, 'L');
$pdf->Ln(5);

$trace_count = 0;
foreach ($run->results as $r) {
    if ($r->trace_data && $trace_count < 5) { // Max 5 trace per non allungare troppo
        $trace = json_decode($r->trace_data, true);
        if (!$trace) continue;
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 7, 'Test ' . $r->testcode . ': ' . $r->testname, 1, 1, 'L', true);
        
        $pdf->SetFont('helvetica', '', 9);
        foreach ($trace as $step) {
            $step_text = 'Step ' . $step['step'] . ': ' . $step['desc'];
            if (isset($step['formula'])) {
                $step_text .= ' | Formula: ' . $step['formula'];
            }
            if (isset($step['result'])) {
                $step_text .= ' | Risultato: ' . $step['result'];
            }
            $pdf->MultiCell(0, 5, '  → ' . $step_text, 0, 'L');
        }
        $pdf->Ln(3);
        $trace_count++;
    }
}

// === PAGINA: GLOSSARIO ===
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, '3. GLOSSARIO TECNICO', 0, 1, 'L');
$pdf->Ln(5);

$glossary = [
    ['Percentuale Competenza', 'Rapporto risposte corrette su totale per quella competenza', '(Σ fraction) / n_domande × 100'],
    ['Livello Bloom', 'Scala 1-6 della tassonomia di Bloom per l\'autovalutazione', '1=Ricordare, 2=Comprendere, 3=Applicare, 4=Analizzare, 5=Valutare, 6=Creare'],
    ['Gap Analysis', 'Differenza tra autovalutazione e performance reale', '(Bloom/6 × 100) - %quiz'],
    ['Radar Aree', 'Aggregazione delle competenze per area tematica', 'media(% competenze area)'],
    ['Rating LabEval', 'Punteggio comportamento osservabile nelle prove pratiche', '0=Non osservato, 1=Parziale, 3=Adeguato'],
    ['Fraction', 'Valore della risposta in Moodle (0=sbagliata, 1=corretta)', 'Range: 0.0 - 1.0'],
    ['Hash Integrità', 'Codice SHA-256 che certifica i dati non modificati', 'Generato da: testcode + status + expected + actual']
];

$glossary_html = '<table cellpadding="5" border="1" style="border-collapse: collapse;">
    <tr style="background-color: #1e3c72; color: white; font-weight: bold;">
        <td style="width: 25%;">Termine</td>
        <td style="width: 40%;">Definizione</td>
        <td style="width: 35%;">Formula/Valori</td>
    </tr>';

foreach ($glossary as $g) {
    $glossary_html .= '<tr>
        <td style="font-weight: bold;">' . $g[0] . '</td>
        <td>' . $g[1] . '</td>
        <td style="font-family: courier; font-size: 8px;">' . $g[2] . '</td>
    </tr>';
}
$glossary_html .= '</table>';

$pdf->writeHTML($glossary_html, true, false, true, false, '');

// === PAGINA: VERSIONI SISTEMA ===
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, '4. INFORMAZIONI SISTEMA', 0, 1, 'L');
$pdf->Ln(5);

$versions = json_decode($run->system_version, true) ?: [];

$version_html = '<table cellpadding="5" border="1" style="border-collapse: collapse;">
    <tr style="background-color: #f8f9fa; font-weight: bold;">
        <td style="width: 50%;">Plugin</td>
        <td style="width: 50%;">Versione</td>
    </tr>';

foreach ($versions as $plugin => $version) {
    $version_html .= '<tr>
        <td>' . $plugin . '</td>
        <td>' . $version . '</td>
    </tr>';
}
$version_html .= '</table>';

$pdf->writeHTML($version_html, true, false, true, false, '');

$pdf->Ln(10);

// Info Moodle
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Ambiente Moodle', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

$moodle_info = [
    'Versione Moodle' => $CFG->release,
    'PHP Version' => phpversion(),
    'Database' => $CFG->dbtype,
    'WWW Root' => $CFG->wwwroot
];

foreach ($moodle_info as $k => $v) {
    $pdf->Cell(60, 6, $k . ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $v, 0, 1, 'L');
}

// === FOOTER con data generazione ===
$pdf->Ln(20);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'Documento generato il ' . date('d/m/Y H:i:s') . ' da FTM Test Suite v1.0.0', 0, 1, 'C');
$pdf->Cell(0, 6, 'Questo documento è una certificazione ufficiale del funzionamento del sistema FTM.', 0, 1, 'C');

// Output PDF
$filename = 'FTM_Certificazione_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'D'); // D = Download
