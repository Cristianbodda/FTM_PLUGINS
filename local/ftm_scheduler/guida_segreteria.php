<?php
/**
 * Guida Operativa per Segreteria FTM Scheduler
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
// Solo visualizzazione richiesta
require_capability('local/ftm_scheduler:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/guida_segreteria.php'));
$PAGE->set_title('Guida Segreteria - FTM Scheduler');
$PAGE->set_heading('Guida Segreteria - FTM Scheduler');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<style>
.guide-container {
    max-width: 900px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.guide-header {
    background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}
.guide-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
}
.guide-header p {
    margin: 0;
    opacity: 0.9;
}
.guide-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.guide-section h2 {
    color: #0066cc;
    border-bottom: 3px solid #0066cc;
    padding-bottom: 10px;
    margin-top: 0;
}
.guide-section h3 {
    color: #333;
    margin-top: 25px;
}
.tip-box {
    background: #e8f4fc;
    border-left: 4px solid #0066cc;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 0 8px 8px 0;
}
.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 0 8px 8px 0;
}
.success-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 0 8px 8px 0;
}
.step {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    align-items: flex-start;
}
.step-num {
    background: #0066cc;
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}
.step-content h4 {
    margin: 0 0 5px 0;
}
.step-content p {
    margin: 0;
    color: #666;
}
.btn-demo {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    margin: 5px 5px 5px 0;
    text-decoration: none;
}
.btn-green { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
.btn-blue { background: linear-gradient(135deg, #0066cc 0%, #00a3e0 100%); color: white; }
.btn-red { background: #dc3545; color: white; }
.color-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 15px 0;
}
.color-badge {
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 13px;
}
.badge-giallo { background: #FEF9C3; border-left: 4px solid #EAB308; }
.badge-grigio { background: #F3F4F6; border-left: 4px solid #6B7280; }
.badge-rosso { background: #FEE2E2; border-left: 4px solid #EF4444; }
.badge-marrone { background: #FED7AA; border-left: 4px solid #92400E; }
.badge-viola { background: #F3E8FF; border-left: 4px solid #7C3AED; }
.badge-esterno { background: #DBEAFE; border-left: 4px solid #2563EB; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}
th, td {
    padding: 12px 15px;
    text-align: left;
    border: 1px solid #dee2e6;
}
th {
    background: #f8f9fa;
}
.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}
.quick-link {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    border-left: 4px solid #0066cc;
    transition: transform 0.2s;
}
.quick-link:hover {
    transform: translateX(5px);
    background: #e9ecef;
}
.quick-link strong {
    display: block;
    margin-bottom: 5px;
}
.quick-link span {
    font-size: 13px;
    color: #666;
}
.toc {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}
.toc h3 { margin-top: 0; }
.toc ul { margin: 0; padding-left: 20px; }
.toc li { margin: 8px 0; }
.toc a { color: #0066cc; text-decoration: none; }
.toc a:hover { text-decoration: underline; }
.print-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #0066cc;
    color: white;
    padding: 15px 25px;
    border-radius: 30px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1000;
}
.print-btn:hover {
    background: #0052a3;
    color: white;
}
@media print {
    .print-btn { display: none; }
    .guide-section { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<div class="guide-container">

<div class="guide-header">
    <h1>Guida Segreteria FTM Scheduler</h1>
    <p>Manuale operativo per la gestione del calendario e delle attivita</p>
    <div style="margin-top: 15px; font-size: 13px; opacity: 0.8;">Versione 2.0 - Febbraio 2026</div>
</div>

<div class="toc">
    <h3>Indice</h3>
    <ul>
        <li><a href="#creare">Creare Attivita</a></li>
        <li><a href="#prenotare">Prenotare Aule (Esterni)</a></li>
        <li><a href="#modificare">Modificare e Eliminare</a></li>
        <li><a href="#occupazione">Occupazione Aule</a></li>
        <li><a href="#carico">Carico Docenti</a></li>
        <li><a href="#conflitti">Gestire Conflitti</a></li>
        <li><a href="#colori">Legenda Colori</a></li>
        <li><a href="#link">Link Utili</a></li>
    </ul>
</div>

<!-- CREARE ATTIVITA -->
<div class="guide-section" id="creare">
    <h2>Creare una Nuova Attivita</h2>

    <p>Puoi creare attivita in <strong>3 modi</strong>:</p>

    <h3>Metodo 1: Pulsante Verde</h3>
    <div class="step">
        <div class="step-num">1</div>
        <div class="step-content">
            <h4>Clicca il pulsante</h4>
            <p><span class="btn-demo btn-green">+ Nuova Attivita</span> nella sezione Azioni Rapide</p>
        </div>
    </div>
    <div class="step">
        <div class="step-num">2</div>
        <div class="step-content">
            <h4>Compila il modulo</h4>
            <p>Nome, Tipo, Gruppo, Data, Fascia oraria, Aula, Coach</p>
        </div>
    </div>
    <div class="step">
        <div class="step-num">3</div>
        <div class="step-content">
            <h4>Salva</h4>
            <p>Clicca "Crea Attivita"</p>
        </div>
    </div>

    <h3>Metodo 2: Click su Slot Vuoto</h3>
    <p>Nella matrice Occupazione Aule, clicca su uno slot con <span style="color: #28a745; font-size: 18px;">OK</span> verde.</p>

    <h3>Fasce Orarie Disponibili</h3>
    <table>
        <tr><th>Fascia</th><th>Orario</th></tr>
        <tr><td>Mattina</td><td>08:30 - 11:45</td></tr>
        <tr><td>Pomeriggio</td><td>13:15 - 16:30</td></tr>
        <tr><td><strong>Tutto il giorno</strong></td><td>08:30 - 16:30</td></tr>
    </table>

    <div class="success-box">
        <strong>Conferma:</strong> Dopo la creazione vedrai un messaggio verde e la pagina si aggiornera.
    </div>
</div>

<!-- PRENOTARE AULE -->
<div class="guide-section" id="prenotare">
    <h2>Prenotare un'Aula (Progetto Esterno)</h2>

    <p>Per progetti esterni (BIT URAR, BIT AI, Corsi LADI):</p>

    <div class="step">
        <div class="step-num">1</div>
        <div class="step-content">
            <h4>Clicca il pulsante blu</h4>
            <p><span class="btn-demo btn-blue">Prenota Aula (Esterno)</span></p>
        </div>
    </div>
    <div class="step">
        <div class="step-num">2</div>
        <div class="step-content">
            <h4>Seleziona progetto e compila</h4>
            <p>Progetto, Aula, Data, Fascia oraria, Responsabile</p>
        </div>
    </div>

    <div class="tip-box">
        <strong>Nota:</strong> Le prenotazioni esterne appaiono in <strong>colore viola/blu</strong> nel calendario.
    </div>
</div>

<!-- MODIFICARE -->
<div class="guide-section" id="modificare">
    <h2>Modificare e Eliminare</h2>

    <h3>Per Modificare</h3>
    <div class="step">
        <div class="step-num">1</div>
        <div class="step-content">
            <h4>Clicca sull'attivita colorata</h4>
            <p>Nella matrice Occupazione Aule, clicca direttamente sul blocco</p>
        </div>
    </div>
    <div class="step">
        <div class="step-num">2</div>
        <div class="step-content">
            <h4>Modifica i campi</h4>
            <p>Cambia cio che serve nel modulo</p>
        </div>
    </div>
    <div class="step">
        <div class="step-num">3</div>
        <div class="step-content">
            <h4>Salva</h4>
            <p>Clicca "Salva Modifiche"</p>
        </div>
    </div>

    <h3>Per Eliminare</h3>
    <p>Nel modulo di modifica, clicca il pulsante rosso <span class="btn-demo btn-red">Elimina</span> in basso a sinistra.</p>

    <div class="warning-box">
        <strong>Attenzione:</strong> L'eliminazione e <strong>irreversibile</strong>!
    </div>
</div>

<!-- OCCUPAZIONE -->
<div class="guide-section" id="occupazione">
    <h2>Visualizzare Occupazione Aule</h2>

    <p>La tab <strong>Occupazione Aule</strong> mostra una matrice settimanale.</p>

    <h3>Come Leggere</h3>
    <table>
        <tr><th>Simbolo</th><th>Significato</th></tr>
        <tr><td style="color: #28a745; font-size: 18px;">OK</td><td>Slot libero - clicca per creare</td></tr>
        <tr><td><span style="background: #FEF9C3; padding: 3px 8px; border-radius: 4px;">Lezione</span></td><td>Attivita gruppo - clicca per modificare</td></tr>
        <tr><td><span style="background: #DBEAFE; padding: 3px 8px; border-radius: 4px;">BIT</span></td><td>Progetto esterno - clicca per modificare</td></tr>
    </table>

    <h3>Percentuale Occupazione</h3>
    <ul>
        <li><strong style="color: #28a745;">0-40%</strong> - Bassa (verde)</li>
        <li><strong style="color: #ffc107;">41-70%</strong> - Media (giallo)</li>
        <li><strong style="color: #dc3545;">71-100%</strong> - Alta (rosso)</li>
    </ul>
</div>

<!-- CARICO -->
<div class="guide-section" id="carico">
    <h2>Monitorare Carico Docenti</h2>

    <p>La tab <strong>Carico Docenti</strong> mostra le ore di ogni coach.</p>

    <h3>Informazioni Visualizzate</h3>
    <ul>
        <li>Nome e iniziali (CB, FM, GM, RB, LP)</li>
        <li>Ore totali settimana</li>
        <li>Barra di carico visiva</li>
        <li>Dettaglio giornaliero</li>
    </ul>

    <div class="tip-box">
        <strong>Sovraccarico:</strong> Se un coach supera 25 ore, appare l'etichetta rossa "SOVRACCARICO".
    </div>
</div>

<!-- CONFLITTI -->
<div class="guide-section" id="conflitti">
    <h2>Gestire i Conflitti</h2>

    <h3>Tipi di Conflitto</h3>
    <table>
        <tr><th>Tipo</th><th>Descrizione</th></tr>
        <tr><td><strong>Conflitto Aula</strong></td><td>Due attivita nella stessa aula, stesso orario</td></tr>
        <tr><td><strong>Conflitto Docente</strong></td><td>Stesso coach in due attivita contemporanee</td></tr>
    </table>

    <h3>Come Risolvere</h3>
    <ol>
        <li>Vai alla tab <strong>Conflitti</strong></li>
        <li>Clicca "Modifica 1" o "Modifica 2"</li>
        <li>Sposta l'attivita in un altro slot/aula</li>
    </ol>
</div>

<!-- COLORI -->
<div class="guide-section" id="colori">
    <h2>Legenda Colori Gruppi</h2>

    <div class="color-legend">
        <div class="color-badge badge-giallo">Giallo</div>
        <div class="color-badge badge-grigio">Grigio</div>
        <div class="color-badge badge-rosso">Rosso</div>
        <div class="color-badge badge-marrone">Marrone</div>
        <div class="color-badge badge-viola">Viola</div>
        <div class="color-badge badge-esterno">Progetto Esterno</div>
    </div>

    <div class="tip-box">
        <strong>KW = Settimana Calendario:</strong> Distingue gruppi dello stesso colore in settimane diverse (es: "Giallo - KW05" vs "Giallo - KW07").
    </div>
</div>

<!-- LINK UTILI -->
<div class="guide-section" id="link">
    <h2>Link Utili</h2>

    <div class="quick-links">
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>" class="quick-link">
            <strong>Dashboard Segreteria</strong>
            <span>Centro di controllo principale</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php'); ?>" class="quick-link">
            <strong>Calendario</strong>
            <span>Vista calendario completa</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/index.php', ['tab' => 'gruppi']); ?>" class="quick-link">
            <strong>Gestione Gruppi</strong>
            <span>Crea e modifica gruppi colore</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/import_calendar.php'); ?>" class="quick-link">
            <strong>Import Excel</strong>
            <span>Importa calendario da file Excel</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/attendance.php'); ?>" class="quick-link">
            <strong>Registro Presenze</strong>
            <span>Segna presenze studenti</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_scheduler/manage_coaches.php'); ?>" class="quick-link">
            <strong>Gestione Coach</strong>
            <span>Aggiungi/rimuovi coach</span>
        </a>
        <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="quick-link">
            <strong>Gestione CPURC</strong>
            <span>Studenti e report CPURC</span>
        </a>
    </div>

    <h3>Supporto</h3>
    <p>Per problemi tecnici contatta: <strong>Cristian Bodda</strong></p>
</div>

</div>

<a href="javascript:window.print()" class="print-btn">Stampa Guida</a>

<?php
echo $OUTPUT->footer();
