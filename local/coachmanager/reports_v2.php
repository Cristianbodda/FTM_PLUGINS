<?php
// ============================================
// BILANCIO COMPETENZE - Sistema FTM
// Pagina unificata per Report Studente
// ============================================
// Versione: 2.0
// Data: 23/12/2025
// Autore: Sviluppato con Claude AI
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/competencymanager/area_mapping.php');

// Parametri
$studentid = optional_param('studentid', 0, PARAM_INT);
$tab = optional_param('tab', 'panoramica', PARAM_ALPHA);
$sector_filter = optional_param('sector', 'all', PARAM_ALPHANUMEXT); // Filtro settore globale
$print_sector = optional_param('print_sector', 'all', PARAM_ALPHANUMEXT); // Settore per stampa

// Richiede login e capability
require_login();
$context = context_system::instance();
require_capability('local/coachmanager:viewreports', $context);

// ============================================
// SELETTORE STUDENTE (se studentid non passato)
// ============================================
if ($studentid == 0) {
    global $OUTPUT, $PAGE;
    
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/coachmanager/reports_v2.php'));
    $PAGE->set_title('Report Colloqui - Seleziona Studente');
    $PAGE->set_heading('📋 Report Colloqui');
    $PAGE->set_pagelayout('report');
    
    echo $OUTPUT->header();
    ?>
    <style>
        .selector-container { max-width: 800px; margin: 40px auto; padding: 30px; }
        .selector-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px; }
        .selector-title { font-size: 1.8rem; font-weight: 600; color: #333; margin-bottom: 30px; text-align: center; }
        .selector-form { display: flex; flex-direction: column; gap: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: #555; font-size: 1rem; }
        .form-group select { padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s; }
        .form-group select:focus { border-color: #9b59b6; outline: none; box-shadow: 0 0 0 3px rgba(155,89,182,0.2); }
        .btn-view { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; border: none; padding: 14px 30px; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(155,89,182,0.4); }
        .btn-view:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        .info-text { color: #888; font-size: 0.9rem; text-align: center; margin-top: 20px; }
    </style>
    
    <div class="selector-container">
        <div class="selector-card">
            <h2 class="selector-title">👤 Seleziona Studente per Report Colloquio</h2>
            
            <form method="get" class="selector-form">
                <div class="form-group">
                    <label for="studentid">👨‍🎓 Studente</label>
                    <select name="studentid" id="studentid" required>
                        <option value="">-- Seleziona uno studente --</option>
                        <?php
                        // Ottieni tutti gli studenti con ruolo student
                        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                                FROM {user} u
                                JOIN {role_assignments} ra ON ra.userid = u.id
                                JOIN {role} r ON r.id = ra.roleid
                                WHERE r.shortname = 'student'
                                AND u.deleted = 0
                                AND u.suspended = 0
                                ORDER BY u.lastname, u.firstname";
                        $students = $DB->get_records_sql($sql);
                        
                        foreach ($students as $s) {
                            echo '<option value="' . $s->id . '">' . fullname($s) . ' (' . $s->email . ')</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-view">📋 Visualizza Report Colloquio</button>
            </form>
            
            <p class="info-text">Seleziona uno studente per visualizzare il report completo per il colloquio formativo.</p>
        </div>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}
// ============================================
// FINE SELETTORE STUDENTE
// ============================================

// Carica dati studente
$student = $DB->get_record('user', array('id' => $studentid), '*', MUST_EXIST);
$fullname = fullname($student);

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Pulisce il testo delle domande da marker CDATA
 */
function clean_question_text($text) {
    $text = str_replace('<![CDATA[', '', $text);
    $text = str_replace(']]>', '', $text);
    $text = str_replace(']]&gt;', '', $text);
    $text = strip_tags($text);
    $text = trim($text);
    if (strlen($text) > 100) {
        $text = substr($text, 0, 100) . '...';
    }
    return $text;
}

/**
 * Mappa dei prefissi competenze alle aree
 * IMPORTANTE: I prefissi più lunghi devono venire PRIMA per il matching corretto
 */
function get_area_map() {
    return [
        // Automobile - prefissi specifici
        'AUTOMOBILE_MAu_' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db', 'settore' => 'automobile', 'classe' => 'manutenzione-auto'],
        'AUTOMOBILE_MR_' => ['nome' => 'Manutenzione e Riparazione', 'icona' => '🔧', 'colore' => '#e74c3c', 'settore' => 'automobile', 'classe' => 'manutenzione-rip'],
        'AUTOMOBILE_MAu' => ['nome' => 'Manutenzione Auto', 'icona' => '🚗', 'colore' => '#3498db', 'settore' => 'automobile', 'classe' => 'manutenzione-auto'],
        'AUTOMOBILE_MR' => ['nome' => 'Manutenzione e Riparazione', 'icona' => '🔧', 'colore' => '#e74c3c', 'settore' => 'automobile', 'classe' => 'manutenzione-rip'],
        
        // Meccanica - prefissi specifici
        'MECCANICA_ASS_' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12', 'settore' => 'meccanica', 'classe' => 'assemblaggio'],
        'MECCANICA_AUT_' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c', 'settore' => 'automazione', 'classe' => 'automazione'],
        'MECCANICA_CSP_' => ['nome' => 'Collaborazione e Sviluppo Personale', 'icona' => '🤝', 'colore' => '#8e44ad', 'settore' => 'meccanica', 'classe' => 'collaborazione'],
        'MECCANICA_CNC_' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4', 'settore' => 'automazione', 'classe' => 'cnc'],
        'MECCANICA_DIS_' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db', 'settore' => 'meccanica', 'classe' => 'disegno'],
        'MECCANICA_LAV_' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e', 'settore' => 'meccanica', 'classe' => 'lav-generali'],
        'MECCANICA_LMC_' => ['nome' => 'Lavorazioni Macchine Convenzionali', 'icona' => '⚙️', 'colore' => '#607d8b', 'settore' => 'meccanica', 'classe' => 'lav-macchine'],
        'MECCANICA_LMB_' => ['nome' => 'Lavorazioni Manuali di Base', 'icona' => '🔧', 'colore' => '#795548', 'settore' => 'meccanica', 'classe' => 'lav-base'],
        'MECCANICA_MAN_' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22', 'settore' => 'meccanica', 'classe' => 'manutenzione'],
        'MECCANICA_MIS_' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c', 'settore' => 'meccanica', 'classe' => 'misurazione'],
        'MECCANICA_PIA_' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6', 'settore' => 'meccanica', 'classe' => 'pianificazione'],
        'MECCANICA_PRO_' => ['nome' => 'Programmazione e Progettazione', 'icona' => '💻', 'colore' => '#2ecc71', 'settore' => 'automazione', 'classe' => 'programmazione'],
        'MECCANICA_SIC_' => ['nome' => 'Sicurezza, Ambiente e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b', 'settore' => 'meccanica', 'classe' => 'sicurezza'],
        
        // Fallback senza underscore finale
        'MECCANICA_ASS' => ['nome' => 'Assemblaggio', 'icona' => '🔩', 'colore' => '#f39c12', 'settore' => 'meccanica', 'classe' => 'assemblaggio'],
        'MECCANICA_AUT' => ['nome' => 'Automazione', 'icona' => '🤖', 'colore' => '#e74c3c', 'settore' => 'automazione', 'classe' => 'automazione'],
        'MECCANICA_CSP' => ['nome' => 'Collaborazione e Sviluppo Personale', 'icona' => '🤝', 'colore' => '#8e44ad', 'settore' => 'meccanica', 'classe' => 'collaborazione'],
        'MECCANICA_CNC' => ['nome' => 'Controllo Numerico CNC', 'icona' => '🖥️', 'colore' => '#00bcd4', 'settore' => 'automazione', 'classe' => 'cnc'],
        'MECCANICA_DIS' => ['nome' => 'Disegno Tecnico', 'icona' => '📐', 'colore' => '#3498db', 'settore' => 'meccanica', 'classe' => 'disegno'],
        'MECCANICA_LAV' => ['nome' => 'Lavorazioni Generali', 'icona' => '🏭', 'colore' => '#9e9e9e', 'settore' => 'meccanica', 'classe' => 'lav-generali'],
        'MECCANICA_LMC' => ['nome' => 'Lavorazioni Macchine Convenzionali', 'icona' => '⚙️', 'colore' => '#607d8b', 'settore' => 'meccanica', 'classe' => 'lav-macchine'],
        'MECCANICA_LMB' => ['nome' => 'Lavorazioni Manuali di Base', 'icona' => '🔧', 'colore' => '#795548', 'settore' => 'meccanica', 'classe' => 'lav-base'],
        'MECCANICA_MAN' => ['nome' => 'Manutenzione', 'icona' => '🔨', 'colore' => '#e67e22', 'settore' => 'meccanica', 'classe' => 'manutenzione'],
        'MECCANICA_MIS' => ['nome' => 'Misurazione', 'icona' => '📏', 'colore' => '#1abc9c', 'settore' => 'meccanica', 'classe' => 'misurazione'],
        'MECCANICA_PIA' => ['nome' => 'Pianificazione', 'icona' => '📋', 'colore' => '#9b59b6', 'settore' => 'meccanica', 'classe' => 'pianificazione'],
        'MECCANICA_PRO' => ['nome' => 'Programmazione e Progettazione', 'icona' => '💻', 'colore' => '#2ecc71', 'settore' => 'automazione', 'classe' => 'programmazione'],
        'MECCANICA_SIC' => ['nome' => 'Sicurezza, Ambiente e Qualità', 'icona' => '🛡️', 'colore' => '#c0392b', 'settore' => 'meccanica', 'classe' => 'sicurezza'],
    ];
}

/**
 * Domande suggerite per colloquio per ogni area
 * Supporta sia le vecchie classi (per retrocompatibilità) che le nuove basate su AREA
 */
function get_domande_suggerite() {
    return [
        // ========================================
        // AUTOMOBILE - Aree A-N
        // ========================================
        'automobile-a' => [
            '"Come accogli il cliente e raccogli le informazioni sul veicolo?"',
            '"Descrivi la procedura di diagnosi preliminare che utilizzi"',
            '"Come documenti gli interventi effettuati?"'
        ],
        'automobile-b' => [
            '"Descrivi la procedura di diagnosi guasti motore"',
            '"Come verifichi il sistema di alimentazione benzina/diesel?"',
            '"Quali controlli esegui sul sistema di distribuzione?"'
        ],
        'automobile-c' => [
            '"Come esegui un cambio olio secondo le specifiche?"',
            '"Descrivi la procedura di controllo del circuito di raffreddamento"',
            '"Come diagnostichi un problema al termostato?"'
        ],
        'automobile-d' => [
            '"Come misuri le emissioni e interpreti i risultati?"',
            '"Descrivi la procedura di rigenerazione DPF"',
            '"Quali controlli esegui sul sistema SCR/AdBlue?"'
        ],
        'automobile-e' => [
            '"Come diagnostichi un problema alla frizione?"',
            '"Descrivi la manutenzione di un cambio manuale"',
            '"Quali controlli esegui su semiassi e omocinetiche?"'
        ],
        'automobile-f' => [
            '"Descrivi la procedura di spurgo freni che utilizzi abitualmente"',
            '"Come verifichi lo stato di usura dei componenti delle sospensioni?"',
            '"Quali controlli esegui sulla geometria dello sterzo?"'
        ],
        'automobile-g' => [
            '"Come utilizzi lo schema elettrico per la diagnosi?"',
            '"Descrivi la procedura di ricerca guasti elettrici"',
            '"Quali strumenti utilizzi per le misure elettriche?"'
        ],
        'automobile-h' => [
            '"Quali strumenti diagnostici hai utilizzato per sistemi ADAS?"',
            '"Come verifichi il funzionamento di ACC, AEB, LKAS?"',
            '"Descrivi la procedura di calibrazione telecamera/radar"'
        ],
        'automobile-i' => [
            '"Come diagnostichi un problema al circuito di climatizzazione?"',
            '"Descrivi la procedura di recupero e ricarica gas refrigerante"',
            '"Come individui le perdite nel circuito A/C?"'
        ],
        'automobile-j' => [
            '"Quali procedure di sicurezza HV conosci e applichi?"',
            '"Hai mai lavorato su veicoli elettrici o ibridi? In quale contesto?"',
            '"Come verificheresti lo stato di salute di una batteria ad alta tensione?"'
        ],
        'automobile-k' => [
            '"Descrivi le operazioni di smontaggio/montaggio componenti carrozzeria"',
            '"Come intervieni su chiusure e alzacristalli?"'
        ],
        'automobile-l' => [
            '"Quali DPI utilizzi nelle diverse situazioni lavorative?"',
            '"Come gestisci i rifiuti e gli sversamenti in officina?"'
        ],
        'automobile-m' => [
            '"Come spieghi gli interventi al cliente?"',
            '"Descrivi come fornisci consigli di manutenzione"'
        ],
        'automobile-n' => [
            '"Quali controlli esegui durante un tagliando completo?"',
            '"Descrivi la procedura di collaudo post-intervento"'
        ],
        
        // ========================================
        // Vecchie classi (retrocompatibilità)
        // ========================================
        'manutenzione-auto' => [
            '"Quali procedure di sicurezza HV conosci e applichi?"',
            '"Hai mai lavorato su veicoli elettrici o ibridi? In quale contesto?"',
            '"Come verificheresti lo stato di salute di una batteria ad alta tensione?"',
            '"Quali strumenti diagnostici hai utilizzato per sistemi ADAS?"',
            '"Descrivi la procedura di diagnosi guasti con tester OBD/EOBD"'
        ],
        'manutenzione-rip' => [
            '"Descrivi la procedura di spurgo freni che utilizzi abitualmente"',
            '"Come diagnostichi un problema al circuito di climatizzazione?"',
            '"Quali controlli esegui durante un tagliando completo?"',
            '"Come verifichi lo stato di usura dei componenti delle sospensioni?"'
        ],
        
        // ========================================
        // MECCANICA
        // ========================================
        'assemblaggio' => [
            '"Descrivi le fasi di assemblaggio di un componente meccanico"',
            '"Quali strumenti utilizzi per verificare la qualità dell\'assemblaggio?"',
            '"Come gestisci la sequenza di montaggio di elementi multipli?"'
        ],
        'automazione' => [
            '"Hai esperienza con PLC? Quali marche?"',
            '"Come diagnostichi un guasto su una linea automatizzata?"',
            '"Descrivi la procedura di messa in sicurezza di un impianto automatico"'
        ],
        'cnc' => [
            '"Quali controlli numerici conosci e utilizzi?"',
            '"Come imposti i parametri di lavorazione per un nuovo pezzo?"',
            '"Descrivi la procedura di azzeramento utensili"',
            '"Come gestisci gli offset e le correzioni utensile?"'
        ],
        'lav-macchine' => [
            '"Quali macchine utensili sai utilizzare?"',
            '"Descrivi la procedura di setup per una lavorazione al tornio"',
            '"Come scegli i parametri di taglio per diversi materiali?"'
        ],
        'lav-base' => [
            '"Quali utensili manuali utilizzi più frequentemente?"',
            '"Descrivi la procedura di tracciatura di un pezzo"',
            '"Come verifichi la planarità di una superficie lavorata manualmente?"'
        ],
        'manutenzione' => [
            '"Descrivi un intervento di manutenzione preventiva che hai eseguito"',
            '"Come pianifichi le attività di manutenzione ordinaria?"',
            '"Quali registri o documentazione utilizzi per tracciare gli interventi?"'
        ],
        'misurazione' => [
            '"Quali strumenti di misura sai utilizzare?"',
            '"Come verifichi la taratura di uno strumento?"',
            '"Descrivi la procedura di misurazione con micrometro"'
        ],
        'pianificazione' => [
            '"Come organizzi il tuo lavoro giornaliero?"',
            '"Descrivi come pianifichi un\'attività complessa"',
            '"Come gestisci le priorità quando hai più compiti urgenti?"'
        ],
        'sicurezza' => [
            '"Quali DPI utilizzi nelle diverse situazioni lavorative?"',
            '"Descrivi la procedura in caso di incidente sul lavoro"',
            '"Come verifichi la conformità di un\'attrezzatura prima dell\'uso?"'
        ],
        'disegno' => [
            '"Sai leggere un disegno tecnico meccanico?"',
            '"Quali software CAD conosci?"',
            '"Come interpreti le tolleranze dimensionali su un disegno?"'
        ],
        'collaborazione' => [
            '"Come ti relazioni con i colleghi durante un lavoro di squadra?"',
            '"Descrivi una situazione in cui hai dovuto gestire un conflitto"',
            '"Come comunichi un problema al tuo responsabile?"'
        ],
        'programmazione' => [
            '"Hai esperienza nella programmazione di macchine CNC?"',
            '"Quali linguaggi di programmazione conosci?"',
            '"Descrivi un programma che hai sviluppato o modificato"'
        ],
        'lav-generali' => [
            '"Quali lavorazioni meccaniche generali sai eseguire?"',
            '"Come prepari la postazione di lavoro?"',
            '"Descrivi la procedura di controllo qualità che applichi"'
        ],
        
        // ========================================
        // MECCANICA - Nuove classi per area
        // ========================================
        'meccanica-lmb' => [
            '"Quali utensili manuali utilizzi più frequentemente?"',
            '"Descrivi la procedura di tracciatura di un pezzo"'
        ],
        'meccanica-lmc' => [
            '"Quali macchine utensili convenzionali sai utilizzare?"',
            '"Descrivi la procedura di setup per una lavorazione"'
        ],
        'meccanica-cnc' => [
            '"Quali controlli numerici CNC conosci?"',
            '"Come imposti i parametri di lavorazione?"'
        ],
        'meccanica-ass' => [
            '"Descrivi le fasi di assemblaggio di un componente"',
            '"Come verifichi la qualità dell\'assemblaggio?"'
        ],
        'meccanica-mis' => [
            '"Quali strumenti di misura sai utilizzare?"',
            '"Come verifichi la taratura di uno strumento?"'
        ],
    ];
}

/**
 * Determina l'area di una competenza dal suo idnumber
 * USA area_mapping.php per estrarre l'AREA corretta (A, B, C...) non il profilo
 */
function get_competency_area($idnumber) {
    // Usa get_area_info() da area_mapping.php
    $area_info = get_area_info($idnumber);
    $area_code = $area_info['code'];
    $area_name = $area_info['name'];
    $area_key = $area_info['key'];
    
    // Estrai il settore dall'idnumber
    $sector = extract_sector_from_idnumber($idnumber);
    
    // Mappa icone per area (lettera)
    $area_icons = [
        'A' => '📋', 'B' => '🔧', 'C' => '🛢️', 'D' => '💨', 'E' => '⚙️',
        'F' => '🛞', 'G' => '💻', 'H' => '📡', 'I' => '❄️', 'J' => '🔋',
        'K' => '🚗', 'L' => '🛡️', 'M' => '👤', 'N' => '✅',
        // Per MECCANICA e CHIMFARM (codici)
        'LMB' => '🔧', 'LMC' => '⚙️', 'CNC' => '🖥️', 'ASS' => '🔩',
        'MIS' => '📏', 'GEN' => '🏭', 'MAN' => '🔨', 'DT' => '📐',
        'AUT' => '🤖', 'PIAN' => '📋', 'SAQ' => '🛡️', 'CSP' => '🤝', 'PRG' => '💡',
        '1C' => '📜', '1G' => '📦', '1O' => '⚗️', '2M' => '📏',
        '3C' => '🔬', '4S' => '🛡️', '5S' => '🧫', '6P' => '🏭',
        '7S' => '🔧', '8T' => '💻', '9A' => '📊',
    ];
    
    // Mappa colori per area
    $area_colors = [
        'A' => '#3498db', 'B' => '#e74c3c', 'C' => '#f39c12', 'D' => '#9b59b6',
        'E' => '#1abc9c', 'F' => '#e67e22', 'G' => '#2ecc71', 'H' => '#00bcd4',
        'I' => '#3f51b5', 'J' => '#ff5722', 'K' => '#795548', 'L' => '#607d8b',
        'M' => '#8bc34a', 'N' => '#009688',
        // Per MECCANICA e CHIMFARM
        'LMB' => '#795548', 'LMC' => '#607d8b', 'CNC' => '#00bcd4', 'ASS' => '#f39c12',
        'MIS' => '#1abc9c', 'GEN' => '#9e9e9e', 'MAN' => '#e67e22', 'DT' => '#3498db',
        'AUT' => '#e74c3c', 'PIAN' => '#9b59b6', 'SAQ' => '#c0392b', 'CSP' => '#8e44ad', 'PRG' => '#2ecc71',
        '1C' => '#3498db', '1G' => '#e67e22', '1O' => '#9b59b6', '2M' => '#1abc9c',
        '3C' => '#2ecc71', '4S' => '#e74c3c', '5S' => '#00bcd4', '6P' => '#f39c12',
        '7S' => '#607d8b', '8T' => '#3f51b5', '9A' => '#8bc34a',
    ];
    
    $icon = $area_icons[$area_code] ?? '📁';
    $color = $area_colors[$area_code] ?? '#95a5a6';
    
    // Genera classe CSS safe
    $classe = strtolower($sector . '-' . $area_code);
    $classe = preg_replace('/[^a-z0-9-]/', '-', $classe);
    
    return [
        'prefix' => $area_key,
        'nome' => $area_name,
        'icona' => $icon,
        'colore' => $color,
        'settore' => strtolower($sector),
        'classe' => $classe,
        'code' => $area_code
    ];
}

/**
 * Determina lo stato in base alla percentuale
 */
function get_status_from_percentage($percentage) {
    if ($percentage >= 90) return ['stato' => 'excellent', 'label' => 'Eccellente', 'colore' => '#28a745'];
    if ($percentage >= 70) return ['stato' => 'good', 'label' => 'Buono', 'colore' => '#17a2b8'];
    if ($percentage >= 50) return ['stato' => 'warning', 'label' => 'Attenzione', 'colore' => '#ffc107'];
    return ['stato' => 'critical', 'label' => 'Critico', 'colore' => '#dc3545'];
}

/**
 * Livelli Bloom per autovalutazione
 */
function get_bloom_levels() {
    return [
        1 => ['nome' => 'RICORDO', 'descrizione' => 'Riesco a ricordare le informazioni base', 'colore' => '#e74c3c'],
        2 => ['nome' => 'COMPRENDO', 'descrizione' => 'Comprendo i concetti fondamentali', 'colore' => '#e67e22'],
        3 => ['nome' => 'APPLICO', 'descrizione' => 'Riesco ad applicare le procedure in situazioni standard', 'colore' => '#f1c40f'],
        4 => ['nome' => 'ANALIZZO', 'descrizione' => 'Sono in grado di analizzare situazioni e prendere decisioni', 'colore' => '#27ae60'],
        5 => ['nome' => 'VALUTO', 'descrizione' => 'Posso valutare situazioni complesse e proporre soluzioni', 'colore' => '#3498db'],
        6 => ['nome' => 'CREO', 'descrizione' => 'Sono in grado di creare soluzioni innovative', 'colore' => '#9b59b6'],
    ];
}

// ============================================
// CARICAMENTO DATI
// ============================================

// 1. Carica tutte le competenze dal framework
$competencies = $DB->get_records_sql("
    SELECT c.id, c.shortname, c.description, c.idnumber
    FROM {competency} c
    JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
    WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
    ORDER BY c.idnumber
");

// 2. Organizza competenze per area
$areas_data = [];
$area_map = get_area_map();

foreach ($competencies as $comp) {
    $area_info = get_competency_area($comp->idnumber);
    $area_key = $area_info['classe'];
    
    if (!isset($areas_data[$area_key])) {
        $areas_data[$area_key] = [
            'info' => $area_info,
            'competenze' => [],
            'totale' => 0,
            'quiz_sum' => 0,
            'quiz_count' => 0,
            'autoval_sum' => 0,
            'autoval_count' => 0
        ];
    }
    
    $areas_data[$area_key]['competenze'][] = $comp;
    $areas_data[$area_key]['totale']++;
}

// 3. Carica risultati quiz per lo studente
$quiz_results = $DB->get_records_sql("
    SELECT 
        qas.id as attemptid,
        qa.questionid,
        qa.maxmark,
        qas.fraction,
        quiza.quiz as quizid,
        quiza.userid,
        quiza.timefinish,
        q.name as questionname,
        q.questiontext
    FROM {quiz_attempts} quiza
    JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
    JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
    JOIN {question} q ON q.id = qa.questionid
    WHERE quiza.userid = ?
    AND quiza.state = 'finished'
    AND qas.sequencenumber = (
        SELECT MAX(qas2.sequencenumber) 
        FROM {question_attempt_steps} qas2 
        WHERE qas2.questionattemptid = qa.id
    )
    ORDER BY quiza.timefinish DESC
", [$studentid]);

// 4. Cerca tabella competenze-domande
$comp_question_table = null;
$tables_to_check = [
    'qbank_competenciesbyquestion',
    'qbank_comp_question', 
    'local_competencymanager_qcomp'
];

foreach ($tables_to_check as $table) {
    if ($DB->get_manager()->table_exists($table)) {
        $comp_question_table = $table;
        break;
    }
}

// 5. Se esiste la tabella, mappa domande a competenze
$question_competencies = [];
if ($comp_question_table) {
    $mappings = $DB->get_records($comp_question_table);
    foreach ($mappings as $map) {
        $qid = $map->questionid;
        $cid = $map->competencyid;
        if (!isset($question_competencies[$qid])) {
            $question_competencies[$qid] = [];
        }
        $question_competencies[$qid][] = $cid;
    }
}

// 6. Calcola punteggi per area dai quiz
foreach ($quiz_results as $result) {
    if (!isset($question_competencies[$result->questionid])) continue;
    
    foreach ($question_competencies[$result->questionid] as $compid) {
        // Trova la competenza
        if (!isset($competencies[$compid])) continue;
        $comp = $competencies[$compid];
        
        // Trova l'area
        $area_info = get_competency_area($comp->idnumber);
        $area_key = $area_info['classe'];
        
        if (isset($areas_data[$area_key])) {
            $score = ($result->fraction !== null) ? floatval($result->fraction) * 100 : 0;
            $areas_data[$area_key]['quiz_sum'] += $score;
            $areas_data[$area_key]['quiz_count']++;
        }
    }
}

// 7. Carica autovalutazioni (se esistono)
$autoval_table = null;
if ($DB->get_manager()->table_exists('local_autovalutazione')) {
    $autoval_table = 'local_autovalutazione';
} elseif ($DB->get_manager()->table_exists('local_selfassessment')) {
    $autoval_table = 'local_selfassessment';
}

$has_autovalutazione = false;
$autovalutazioni = [];

if ($autoval_table) {
    $autovalutazioni = $DB->get_records($autoval_table, ['userid' => $studentid]);
    $has_autovalutazione = !empty($autovalutazioni);
    
    // Mappa autovalutazioni alle aree
    foreach ($autovalutazioni as $av) {
        if (!isset($av->competencyid)) continue;
        if (!isset($competencies[$av->competencyid])) continue;
        
        $comp = $competencies[$av->competencyid];
        $area_info = get_competency_area($comp->idnumber);
        $area_key = $area_info['classe'];
        
        if (isset($areas_data[$area_key])) {
            $level = isset($av->level) ? intval($av->level) : 0;
            $percentage = ($level / 6) * 100; // Bloom 1-6
            $areas_data[$area_key]['autoval_sum'] += $percentage;
            $areas_data[$area_key]['autoval_count']++;
        }
    }
}

// 8. Calcola medie per area
foreach ($areas_data as $key => &$area) {
    $area['quiz_media'] = $area['quiz_count'] > 0 ? round($area['quiz_sum'] / $area['quiz_count'], 1) : null;
    $area['autoval_media'] = $area['autoval_count'] > 0 ? round($area['autoval_sum'] / $area['autoval_count'], 1) : null;
    
    // Calcola GAP
    if ($area['quiz_media'] !== null && $area['autoval_media'] !== null) {
        $area['gap'] = round($area['autoval_media'] - $area['quiz_media'], 1);
    } else {
        $area['gap'] = null;
    }
    
    // Determina stato
    $main_percentage = $area['quiz_media'] ?? 0;
    $area['status'] = get_status_from_percentage($main_percentage);
}
unset($area);

// 9. Conta statistiche generali
$total_competenze = array_sum(array_column($areas_data, 'totale'));
$quiz_completati = count($quiz_results);
$aree_eccellenti = count(array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 0) >= 90));
$aree_attenzione = count(array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 0) >= 50 && ($a['quiz_media'] ?? 0) < 70));
$aree_critiche = count(array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 0) < 50 && $a['quiz_count'] > 0));

// 10. Identifica aree con GAP critico (sopravvalutazione > 15%)
$aree_gap_critico = array_filter($areas_data, fn($a) => ($a['gap'] ?? 0) > 15);
usort($aree_gap_critico, fn($a, $b) => ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0));

// 11. Identifica punti di forza (quiz >= 90%)
$punti_forza = array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 0) >= 90);

// ============================================
// 11b. RAGGRUPPA AREE PER SETTORE
// ============================================
$sectors_data = [];
$all_sectors = [];

// Lista settori validi riconosciuti
$valid_sectors = [
    'AUTOMOBILE' => 'Automobile',
    'MECCANICA' => 'Meccanica', 
    'LOGISTICA' => 'Logistica',
    'CHIMFARM' => 'Chimico-Farmaceutico',
    'ELETTRICITA' => 'Elettricità',
    'AUTOMAZIONE' => 'Automazione',
    'METALCOSTRUZIONE' => 'Metalcostruzione'
];

foreach ($areas_data as $area_key => $area) {
    // Estrai settore dalla chiave area (es. "automobile-a" -> "AUTOMOBILE")
    $sector_raw = strtoupper($area['info']['settore'] ?? '');
    
    // Prova anche a estrarlo dalla chiave
    if (empty($sector_raw) || !isset($valid_sectors[$sector_raw])) {
        // Estrai dalla chiave area (prima parte prima del trattino)
        $parts = explode('-', $area_key);
        $sector_raw = strtoupper($parts[0] ?? '');
    }
    
    // Verifica se è un settore valido
    $sector = 'ALTRO';
    $display_name = 'Altro';
    
    foreach ($valid_sectors as $valid_code => $valid_name) {
        if (strpos($sector_raw, $valid_code) !== false || 
            strpos(strtoupper($area_key), strtolower($valid_code)) !== false ||
            strpos(strtoupper($area['info']['nome'] ?? ''), $valid_code) !== false) {
            $sector = $valid_code;
            $display_name = $valid_name;
            break;
        }
    }
    
    // Salta aree senza dati quiz (opzionale - commenta per mostrare tutto)
    // if (($area['quiz_count'] ?? 0) == 0 && ($area['autoval_count'] ?? 0) == 0) continue;
    
    if (!isset($sectors_data[$sector])) {
        $sectors_data[$sector] = [
            'name' => $sector,
            'display_name' => $display_name,
            'areas' => [],
            'total_competenze' => 0,
            'quiz_sum' => 0,
            'quiz_count' => 0,
            'autoval_count' => 0
        ];
    }
    
    $sectors_data[$sector]['areas'][$area_key] = $area;
    $sectors_data[$sector]['total_competenze'] += $area['totale'];
    $sectors_data[$sector]['quiz_sum'] += $area['quiz_sum'] ?? 0;
    $sectors_data[$sector]['quiz_count'] += $area['quiz_count'] ?? 0;
    $sectors_data[$sector]['autoval_count'] += $area['autoval_count'] ?? 0;
    
    $all_sectors[$sector] = $display_name;
}

// Calcola media per settore
foreach ($sectors_data as $sector => &$sdata) {
    $sdata['quiz_media'] = $sdata['quiz_count'] > 0 
        ? round($sdata['quiz_sum'] / $sdata['quiz_count'], 1) 
        : 0;
    $sdata['areas_count'] = count($sdata['areas']);
}
unset($sdata);

// Ordina settori alfabeticamente
ksort($all_sectors);

// ============================================
// 11c. FILTRA AREE IN BASE AL SETTORE SELEZIONATO
// ============================================
$filtered_areas_data = $areas_data;

if ($sector_filter !== 'all' && isset($sectors_data[strtoupper($sector_filter)])) {
    $filtered_areas_data = $sectors_data[strtoupper($sector_filter)]['areas'];
}

// Ricalcola statistiche per i dati filtrati
$filtered_total_competenze = array_sum(array_column($filtered_areas_data, 'totale'));
$filtered_aree_eccellenti = count(array_filter($filtered_areas_data, fn($a) => ($a['quiz_media'] ?? 0) >= 90));
$filtered_aree_attenzione = count(array_filter($filtered_areas_data, fn($a) => ($a['quiz_media'] ?? 0) >= 50 && ($a['quiz_media'] ?? 0) < 70));
$filtered_aree_critiche = count(array_filter($filtered_areas_data, fn($a) => ($a['quiz_media'] ?? 0) < 50 && $a['quiz_count'] > 0));

// ============================================
// 12. CARICA DATI DETTAGLIATI PER MODAL
// ============================================

// Crea mappa autovalutazione per competency_id
$autoval_by_comp = [];
if ($autoval_table && !empty($autovalutazioni)) {
    foreach ($autovalutazioni as $av) {
        if (isset($av->competencyid)) {
            $autoval_by_comp[$av->competencyid] = $av;
        }
    }
}

// Crea mappa quiz per question_id
$quiz_by_question = [];
foreach ($quiz_results as $result) {
    $qid = $result->questionid;
    if (!isset($quiz_by_question[$qid])) {
        $quiz_by_question[$qid] = [];
    }
    $quiz_by_question[$qid][] = $result;
}

// Carica nomi quiz
$quiz_names = [];
$quiz_ids = array_unique(array_column((array)$quiz_results, 'quizid'));
if (!empty($quiz_ids)) {
    list($sql_in, $params_in) = $DB->get_in_or_equal($quiz_ids);
    $quizzes = $DB->get_records_sql("SELECT id, name FROM {quiz} WHERE id $sql_in", $params_in);
    foreach ($quizzes as $q) {
        $quiz_names[$q->id] = $q->name;
    }
}

// Costruisci dati dettagliati per ogni competenza
$competencies_detailed = [];
$bloom_levels = get_bloom_levels();

foreach ($competencies as $comp) {
    $comp_data = [
        'id' => $comp->id,
        'idnumber' => $comp->idnumber,
        'shortname' => $comp->shortname,
        'description' => clean_question_text($comp->description ?? ''),
        'autoval' => null,
        'quizzes' => [],
        'quiz_media' => null,
        'gap' => null
    ];
    
    // Aggiungi autovalutazione se presente
    if (isset($autoval_by_comp[$comp->id])) {
        $av = $autoval_by_comp[$comp->id];
        $level = isset($av->level) ? intval($av->level) : 0;
        $percentage = round(($level / 6) * 100);
        $bloom = $bloom_levels[$level] ?? ['nome' => 'N/D', 'descrizione' => '', 'colore' => '#ccc'];
        
        $comp_data['autoval'] = [
            'level' => $level,
            'percentage' => $percentage,
            'bloom_nome' => $bloom['nome'],
            'bloom_desc' => $bloom['descrizione'],
            'bloom_colore' => $bloom['colore']
        ];
    }
    
    // Aggiungi quiz se presenti (tramite mapping competenze-domande)
    if ($comp_question_table) {
        // Trova domande associate a questa competenza
        $questions_for_comp = $DB->get_records($comp_question_table, ['competencyid' => $comp->id]);
        
        $quiz_scores = [];
        foreach ($questions_for_comp as $qmap) {
            $qid = $qmap->questionid;
            if (isset($quiz_by_question[$qid])) {
                foreach ($quiz_by_question[$qid] as $result) {
                    $quiz_id = $result->quizid;
                    $quiz_name = $quiz_names[$quiz_id] ?? 'Quiz #' . $quiz_id;
                    $score_pct = ($result->fraction !== null) ? round(floatval($result->fraction) * 100) : 0;
                    
                    // Raggruppa per quiz
                    if (!isset($quiz_scores[$quiz_id])) {
                        $quiz_scores[$quiz_id] = [
                            'quiz_id' => $quiz_id,
                            'quiz_name' => $quiz_name,
                            'scores' => [],
                            'date' => $result->timefinish
                        ];
                    }
                    $quiz_scores[$quiz_id]['scores'][] = $score_pct;
                }
            }
        }
        
        // Calcola media per ogni quiz
        foreach ($quiz_scores as $qid => $qdata) {
            $avg = count($qdata['scores']) > 0 ? round(array_sum($qdata['scores']) / count($qdata['scores'])) : 0;
            $comp_data['quizzes'][] = [
                'quiz_id' => $qid,
                'quiz_name' => $qdata['quiz_name'],
                'score' => $avg,
                'date' => date('d/m/Y', $qdata['date']),
                'timestamp' => $qdata['date']
            ];
        }
        
        // Calcola media quiz per competenza
        if (!empty($comp_data['quizzes'])) {
            $all_scores = array_column($comp_data['quizzes'], 'score');
            $comp_data['quiz_media'] = round(array_sum($all_scores) / count($all_scores));
        }
    }
    
    // Calcola GAP
    if ($comp_data['autoval'] !== null && $comp_data['quiz_media'] !== null) {
        $comp_data['gap'] = $comp_data['autoval']['percentage'] - $comp_data['quiz_media'];
    }
    
    $competencies_detailed[$comp->id] = $comp_data;
}

// Aggiungi dati dettagliati alle aree
foreach ($areas_data as $area_key => &$area) {
    $area['competenze_detailed'] = [];
    foreach ($area['competenze'] as $comp) {
        if (isset($competencies_detailed[$comp->id])) {
            $area['competenze_detailed'][] = $competencies_detailed[$comp->id];
        }
    }
}
unset($area);

// ============================================
// SETUP PAGINA
// ============================================
$PAGE->set_url(new moodle_url('/local/coachmanager/reports.php', ['studentid' => $studentid]));
$PAGE->set_context($context);
$PAGE->set_title("Bilancio Competenze - $fullname");
$PAGE->set_heading("Bilancio Competenze");
$PAGE->set_pagelayout('report');

// CSS inline per evitare dipendenze esterne
$custom_css = '
<style>
/* Reset e base */
.bilancio-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* Header studente */
.student-header {
    background: linear-gradient(135deg, #2c3e50, #3498db);
    color: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.student-header h1 {
    font-size: 1.8em;
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-header .subtitle {
    opacity: 0.9;
    font-size: 1em;
}

.student-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.meta-item {
    background: rgba(255,255,255,0.15);
    padding: 10px 18px;
    border-radius: 10px;
    text-align: center;
}

.meta-item .label {
    font-size: 0.75em;
    text-transform: uppercase;
    opacity: 0.8;
}

.meta-item .value {
    font-size: 1.1em;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Bottoni */
.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: white;
    color: #2c3e50;
}

.btn-primary:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
}

.btn-secondary {
    background: rgba(255,255,255,0.2);
    color: white;
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.3);
}

.btn-save {
    background: #28a745;
    color: white;
}

.btn-save:hover {
    background: #218838;
}

/* Navigazione tabs */
.nav-tabs {
    display: flex;
    gap: 5px;
    background: white;
    padding: 8px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    flex-wrap: wrap;
}

.nav-tab {
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    background: transparent;
    color: #6c757d;
    text-decoration: none;
}

.nav-tab:hover {
    background: #f8f9fa;
    color: #2c3e50;
    text-decoration: none;
}

.nav-tab.active {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

/* Section card */
.section-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.section-header {
    padding: 18px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.section-header h2 {
    font-size: 1.2em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 25px;
}

/* Collapsible sections */
.collapsible-header {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
}

.collapsible-header:hover {
    filter: brightness(1.05);
}

.collapse-arrow {
    font-size: 14px;
    transition: transform 0.3s ease;
    display: inline-block;
    width: 20px;
}

.collapsible-content {
    transition: all 0.3s ease;
}

/* Stats grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.stat-card {
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-card.blue { background: linear-gradient(135deg, #e8f4fc, #d1ecf1); border-left: 4px solid #17a2b8; }
.stat-card.green { background: linear-gradient(135deg, #e8f8f0, #d4edda); border-left: 4px solid #28a745; }
.stat-card.orange { background: linear-gradient(135deg, #fff8e8, #fff3cd); border-left: 4px solid #ffc107; }
.stat-card.red { background: linear-gradient(135deg, #fce8e8, #f8d7da); border-left: 4px solid #dc3545; }
.stat-card.purple { background: linear-gradient(135deg, #f3e8fc, #e2d5f1); border-left: 4px solid #9b59b6; }

.stat-icon { font-size: 2em; margin-bottom: 8px; }
.stat-value { font-size: 2em; font-weight: 700; color: #2c3e50; }
.stat-label { font-size: 0.85em; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
.click-hint { font-size: 0.7em; color: #6c757d; margin-top: 5px; opacity: 0; transition: opacity 0.2s; }
.stat-card:hover .click-hint { opacity: 1; }

/* Areas grid - Cubotti */
.areas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 18px;
}

.area-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border-left: 5px solid;
    position: relative;
    overflow: hidden;
}

.area-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.area-card .area-icon { font-size: 2.2em; margin-bottom: 8px; }
.area-card .area-name { font-weight: 600; font-size: 1em; color: #2c3e50; margin-bottom: 4px; line-height: 1.3; }
.area-card .area-count { font-size: 0.8em; color: #7f8c8d; margin-bottom: 12px; }

.area-card .area-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.area-card .area-percentage { font-size: 1.4em; font-weight: 700; }
.area-card .area-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7em;
    font-weight: 600;
    text-transform: uppercase;
}

.status-excellent { background: #d4edda; color: #155724; }
.status-good { background: #cce5ff; color: #004085; }
.status-warning { background: #fff3cd; color: #856404; }
.status-critical { background: #f8d7da; color: #721c24; }

/* Mini comparison bars */
.area-mini-comparison { display: flex; gap: 5px; margin-top: 8px; }
.mini-bar-container { flex: 1; }
.mini-bar-label { font-size: 0.65em; color: #7f8c8d; margin-bottom: 2px; }
.mini-bar { height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden; }
.mini-bar-fill { height: 100%; border-radius: 2px; }
.mini-bar-fill.quiz { background: #3498db; }
.mini-bar-fill.autoval { background: #9b59b6; }

/* Colori bordo per settore */
.area-card.assemblaggio { border-left-color: #f39c12; }
.area-card.automazione { border-left-color: #e74c3c; }
.area-card.collaborazione { border-left-color: #8e44ad; }
.area-card.cnc { border-left-color: #00bcd4; }
.area-card.disegno { border-left-color: #3498db; }
.area-card.lav-generali { border-left-color: #9e9e9e; }
.area-card.lav-macchine { border-left-color: #607d8b; }
.area-card.lav-base { border-left-color: #795548; }
.area-card.manutenzione { border-left-color: #e67e22; }
.area-card.manutenzione-auto { border-left-color: #3498db; }
.area-card.manutenzione-rip { border-left-color: #e74c3c; }
.area-card.misurazione { border-left-color: #1abc9c; }
.area-card.pianificazione { border-left-color: #9b59b6; }
.area-card.programmazione { border-left-color: #2ecc71; }
.area-card.sicurezza { border-left-color: #c0392b; }

/* Strengths grid */
.strengths-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.strength-item {
    background: #e8f8f0;
    border-left: 4px solid #28a745;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.strength-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
    background: #d4edda;
}

.strength-item .icon { font-size: 1.5em; }
.strength-item .info { flex: 1; }
.strength-item .name { font-weight: 600; font-size: 0.95em; color: #155724; }
.strength-item .percentage { font-size: 1.2em; font-weight: 700; color: #28a745; }

/* Critical areas */
.critical-area-item {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.critical-area-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
}

.critical-area-item.warning-item {
    background: #fffbf0;
    border-left-color: #ffc107;
}

.critical-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.critical-title { display: flex; align-items: center; gap: 12px; }
.critical-title .icon { font-size: 2em; }
.critical-title h3 { font-size: 1.1em; color: #2c3e50; margin: 0; }
.critical-title .code { font-size: 0.85em; color: #7f8c8d; display: block; }

.critical-values { display: flex; gap: 15px; flex-wrap: wrap; }

.critical-value-box {
    text-align: center;
    padding: 10px 15px;
    background: white;
    border-radius: 8px;
    min-width: 100px;
    cursor: pointer;
    transition: all 0.2s;
}

.critical-value-box:hover {
    background: #f8f9fa;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.critical-value-box .label { font-size: 0.7em; color: #7f8c8d; text-transform: uppercase; }
.critical-value-box .value { font-size: 1.3em; font-weight: 700; }

.gap-indicator {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
}

.gap-indicator.sopravvalutazione { background: #f8d7da; color: #721c24; }
.gap-indicator.sottovalutazione { background: #d1ecf1; color: #0c5460; }
.gap-indicator.allineato { background: #d4edda; color: #155724; }

/* Colloquio questions */
.colloquio-questions {
    background: white;
    border-radius: 10px;
    padding: 15px 20px;
    margin-top: 15px;
}

.colloquio-questions h4 {
    font-size: 0.95em;
    color: #2c3e50;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.colloquio-questions ul { list-style: none; padding: 0; margin: 0; }
.colloquio-questions li {
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
    font-style: italic;
    color: #555;
}
.colloquio-questions li:last-child { border-bottom: none; }
.colloquio-questions li::before { content: "💬 "; }

/* Coach notes */
.coach-notes-textarea {
    width: 100%;
    min-height: 150px;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-family: inherit;
    font-size: 1em;
    resize: vertical;
    transition: border-color 0.2s;
}

.coach-notes-textarea:focus {
    outline: none;
    border-color: #3498db;
}

.notes-actions { display: flex; gap: 10px; margin-top: 15px; }

/* Advanced report link */
.advanced-report-link {
    display: flex;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 20px;
    margin-top: 30px;
    transition: all 0.2s;
}

.advanced-report-link:hover {
    border-color: #3498db;
    background: linear-gradient(135deg, #e8f4fc, #d1ecf1);
}

.advanced-report-link .link-icon { font-size: 2.5em; }
.advanced-report-link .link-content { flex: 1; }
.advanced-report-link .link-content h4 { color: #2c3e50; margin: 0 0 5px 0; }
.advanced-report-link .link-content p { color: #6c757d; font-size: 0.9em; margin: 0; }

.btn-advanced {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 12px 20px;
    font-size: 0.95em;
    white-space: nowrap;
}

.btn-advanced:hover {
    background: linear-gradient(135deg, #2980b9, #1f6dad);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    color: white;
    text-decoration: none;
}

/* Radar controls */
.radar-controls {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-start;
}

.control-group {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 10px;
    min-width: 200px;
}

.control-group h4 {
    font-size: 0.85em;
    color: #6c757d;
    text-transform: uppercase;
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.control-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    cursor: pointer;
    font-size: 0.9em;
}

.control-group input[type="checkbox"],
.control-group input[type="radio"] {
    width: 16px;
    height: 16px;
}

.control-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9em;
    background: white;
}

/* Radar container */
.radar-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 900px) {
    .radar-container { grid-template-columns: 1fr; }
}

.radar-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.radar-card h3 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.radar-canvas-container {
    position: relative;
    max-width: 400px;
    margin: 0 auto;
}

.radar-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85em;
}

.legend-color {
    width: 20px;
    height: 4px;
    border-radius: 2px;
}

/* Legenda */
.legend {
    display: flex;
    justify-content: center;
    gap: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.legend .legend-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
}

/* Filter bar */
.filter-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-bar select {
    padding: 10px 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 0.9em;
    background: white;
    min-width: 180px;
}

.filter-bar .filter-label { font-weight: 500; color: #6c757d; }

/* Compare section */
.compare-section {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 25px;
}

.compare-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.compare-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #2c3e50;
    margin: 0;
}

.student-selector { display: flex; gap: 15px; flex-wrap: wrap; }

.student-select-box {
    background: white;
    border-radius: 10px;
    padding: 15px;
    min-width: 250px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.student-select-box label {
    display: block;
    font-size: 0.85em;
    color: #6c757d;
    margin-bottom: 8px;
}

.student-select-box select {
    width: 100%;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.95em;
}

.compare-table {
    width: 100%;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-collapse: collapse;
}

.compare-table th,
.compare-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.compare-table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
.compare-table tr:last-child td { border-bottom: none; }

/* Matching preview */
.matching-preview {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
}

.matching-preview h3 { margin: 0 0 10px 0; font-size: 1.3em; }
.matching-preview p { opacity: 0.9; margin: 0 0 20px 0; }
.matching-preview .coming-soon {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

/* Tab panels */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-content {
    background: white;
    border-radius: 16px;
    max-width: 850px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-header h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.3em;
    margin: 0;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover { background: rgba(255,255,255,0.3); }

.modal-body { padding: 20px 25px; }

/* Competency item */
.competency-item {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
}

.competency-header {
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    transition: background 0.2s;
}

.competency-header:hover { background: #e9ecef; }

.competency-info { flex: 1; }
.competency-name { font-weight: 600; font-size: 0.95em; color: #2c3e50; margin-bottom: 3px; }
.competency-code { font-size: 0.8em; color: #7f8c8d; }

.competency-values { display: flex; gap: 12px; align-items: center; }

.value-box {
    text-align: center;
    min-width: 65px;
    padding: 6px 10px;
    background: white;
    border-radius: 8px;
}

.value-box .label { font-size: 0.65em; color: #7f8c8d; text-transform: uppercase; }
.value-box .value { font-size: 1em; font-weight: 600; }

.competency-toggle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 12px;
    transition: all 0.3s ease;
    font-size: 0.85em;
}

.competency-item.open .competency-toggle {
    background: #3498db;
    color: white;
    transform: rotate(180deg);
}

.competency-details {
    display: none;
    padding: 20px;
    background: white;
    border-top: 1px solid #e9ecef;
}

.competency-item.open .competency-details { display: block; }

.detail-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #e9ecef;
}

.detail-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

.detail-section-title {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Self assessment card */
.self-assessment-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    border-left: 4px solid #9b59b6;
}

.level-badge {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.level-badge.level-1 { background: #e74c3c; }
.level-badge.level-2 { background: #e67e22; }
.level-badge.level-3 { background: #f1c40f; color: #333; }
.level-badge.level-4 { background: #27ae60; }
.level-badge.level-5 { background: #3498db; }
.level-badge.level-6 { background: #9b59b6; }

/* Quiz item */
.quiz-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    border-left: 4px solid #3498db;
}

.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.quiz-name { font-weight: 600; color: #2c3e50; }

.quiz-score {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.quiz-score.good { background: #d4edda; color: #155724; }
.quiz-score.warning { background: #fff3cd; color: #856404; }
.quiz-score.bad { background: #f8d7da; color: #721c24; }

.quiz-details {
    display: flex;
    gap: 15px;
    font-size: 0.85em;
    color: #6c757d;
    flex-wrap: wrap;
}

.quiz-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.quiz-link:hover { text-decoration: underline; }

.wrong-questions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #dee2e6;
}

.wrong-title { font-size: 0.85em; color: #dc3545; font-weight: 600; margin-bottom: 8px; }

.wrong-item {
    background: #fff5f5;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 6px;
    font-size: 0.85em;
    border-left: 3px solid #dc3545;
}

.no-data-message {
    text-align: center;
    padding: 20px;
    color: #7f8c8d;
    font-style: italic;
    background: #f8f9fa;
    border-radius: 8px;
}

.gap-analysis-box {
    padding: 12px 15px;
    border-radius: 8px;
    margin-top: 12px;
}

.gap-analysis-box.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
.gap-analysis-box.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
.gap-analysis-box.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }

/* Notification */
.notification {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #2c3e50;
    color: white;
    padding: 12px 25px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 9999;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translate(-50%, 20px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Print styles */
@media print {
    body { background: white; }
    .bilancio-container { max-width: 100%; padding: 0; }
    .btn, .header-actions, .nav-tabs { display: none !important; }
    .section-card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
    .modal-overlay { display: none !important; }
    .area-card { box-shadow: none; border: 1px solid #ddd; }
    
    /* Filtro settore in stampa */
    .sector-filter-global { page-break-after: avoid; }
    .sector-filter-global select, 
    .sector-filter-global input[type="checkbox"],
    .sector-filter-global label[for="printSectorOnly"] { display: none !important; }
    .sector-filter-global .sector-summary-card { cursor: default; }
    .sector-filter-global .sector-summary-card:not(.print-active) { opacity: 0.5; }
    
    /* Mostra indicazione settore filtrato */
    .sector-filter-global[data-print-sector]:after {
        content: "Filtro: " attr(data-print-sector);
        display: block;
        padding: 10px;
        background: #f0f0f0;
        border-radius: 5px;
        margin-top: 10px;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .student-header { flex-direction: column; text-align: center; }
    .student-meta { justify-content: center; }
    .header-actions { justify-content: center; }
    .areas-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .radar-controls { flex-direction: column; }
    .control-group { width: 100%; }
}
</style>
';

echo $OUTPUT->header();
echo $custom_css;

// Genera dati JSON per JavaScript
$areas_json = json_encode($areas_data);
$filtered_areas_json = json_encode($filtered_areas_data);
$sectors_json = json_encode($sectors_data);
$radar_labels = json_encode(array_values(array_map(fn($a) => $a['info']['nome'], $filtered_areas_data)));
$radar_quiz_data = json_encode(array_values(array_map(fn($a) => $a['quiz_media'] ?? 0, $filtered_areas_data)));
$radar_autoval_data = json_encode(array_values(array_map(fn($a) => $a['autoval_media'] ?? 0, $filtered_areas_data)));
$current_sector_filter = json_encode($sector_filter);
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="bilancio-container">
    
    <!-- ============================================
         HEADER STUDENTE
         ============================================ -->
    <div class="student-header">
        <div class="student-info">
            <h1>📊 Bilancio Competenze</h1>
            <div class="subtitle">👤 <?php echo $fullname; ?> - 🏭 Settore: MECCANICA INDUSTRIALE</div>
        </div>
        <div class="student-meta">
            <div class="meta-item">
                <div class="label">Competenze</div>
                <div class="value"><?php echo $total_competenze; ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Quiz svolti</div>
                <div class="value"><?php echo $quiz_completati; ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Autovalutazione</div>
                <div class="value"><?php echo $has_autovalutazione ? '✅' : '❌'; ?></div>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="window.print()">🖨️ Stampa</button>
            <a href="export_pdf.php?studentid=<?php echo $studentid; ?>" class="btn btn-secondary">📄 Esporta PDF</a>
        </div>
    </div>
    
    <!-- ============================================
         FILTRO SETTORE GLOBALE + RIEPILOGO
         ============================================ -->
    <div class="section-card sector-filter-global" id="sectorFilterCard" style="margin-bottom: 20px;">
        <div class="section-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
            <h2 style="margin: 0;">🏭 Filtro Settore</h2>
        </div>
        <div class="section-body">
            <!-- Filtro Settore -->
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 20px;">
                <label style="font-weight: 600; color: #333;">📂 Visualizza Settore:</label>
                <select id="globalSectorFilter" onchange="applySectorFilter(this.value)" 
                        style="padding: 10px 15px; border: 2px solid #667eea; border-radius: 8px; font-size: 1rem; min-width: 200px;">
                    <option value="all" <?php echo $sector_filter === 'all' ? 'selected' : ''; ?>>🌐 Tutti i settori</option>
                    <?php 
                    // Mostra solo settori validi con dati
                    foreach ($sectors_data as $code => $sdata): 
                        // Salta settori senza dati o "ALTRO"
                        if ($code === 'ALTRO') continue;
                        if ($sdata['quiz_count'] == 0 && $sdata['autoval_count'] == 0) continue;
                    ?>
                    <option value="<?php echo strtolower($code); ?>" <?php echo strtolower($sector_filter) === strtolower($code) ? 'selected' : ''; ?>>
                        <?php echo $sdata['display_name']; ?> (<?php echo $sdata['areas_count'] ?? 0; ?> aree)
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <label style="margin-left: 20px;">
                    <input type="checkbox" id="printSectorOnly" onchange="updatePrintSettings()" 
                           style="margin-right: 5px;" <?php echo $sector_filter !== 'all' ? 'checked' : ''; ?>>
                    🖨️ Stampa solo questo settore
                </label>
            </div>
            
            <!-- Riepilogo per Settore -->
            <div style="margin-top: 15px;">
                <h4 style="margin-bottom: 15px; color: #333;">📊 Riepilogo per Settore</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach ($sectors_data as $sector => $sdata): 
                        // Salta settori senza dati o "ALTRO"
                        if ($sector === 'ALTRO') continue;
                        if ($sdata['quiz_count'] == 0 && $sdata['autoval_count'] == 0) continue;
                        $is_selected = (strtolower($sector_filter) === strtolower($sector));
                        $card_style = $is_selected ? 'border: 3px solid #667eea; box-shadow: 0 4px 15px rgba(102,126,234,0.3);' : 'border: 1px solid #e0e0e0;';
                    ?>
                    <div class="sector-summary-card" onclick="applySectorFilter('<?php echo strtolower($sector); ?>')"
                         style="background: white; padding: 15px; border-radius: 12px; cursor: pointer; transition: all 0.3s; <?php echo $card_style; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-weight: 600; color: #333;"><?php echo $sdata['display_name']; ?></span>
                            <?php if ($is_selected): ?>
                            <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem;">ATTIVO</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.85rem;">
                            <div>
                                <span style="color: #888;">Aree:</span>
                                <strong><?php echo $sdata['areas_count']; ?></strong>
                            </div>
                            <div>
                                <span style="color: #888;">Competenze:</span>
                                <strong><?php echo $sdata['total_competenze']; ?></strong>
                            </div>
                            <div>
                                <span style="color: #888;">Quiz:</span>
                                <strong><?php echo $sdata['quiz_count']; ?></strong>
                            </div>
                            <div>
                                <span style="color: #888;">Media:</span>
                                <strong style="color: <?php echo $sdata['quiz_media'] >= 60 ? '#28a745' : ($sdata['quiz_media'] >= 40 ? '#ffc107' : '#dc3545'); ?>">
                                    <?php echo $sdata['quiz_media']; ?>%
                                </strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($sector_filter !== 'all'): ?>
            <div style="margin-top: 15px; padding: 12px; background: #e8f4fd; border-radius: 8px; border-left: 4px solid #667eea;">
                <strong>📌 Filtro attivo:</strong> Stai visualizzando solo il settore <strong><?php echo $all_sectors[strtoupper($sector_filter)] ?? $sector_filter; ?></strong>
                <a href="?studentid=<?php echo $studentid; ?>&sector=all" style="margin-left: 15px; color: #667eea;">❌ Rimuovi filtro</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================
         NAVIGAZIONE TABS
         ============================================ -->
    <div class="nav-tabs">
        <a href="#" class="nav-tab <?php echo $tab == 'panoramica' ? 'active' : ''; ?>" onclick="showTab('panoramica'); return false;">📋 Panoramica</a>
        <a href="#" class="nav-tab <?php echo $tab == 'radar' ? 'active' : ''; ?>" onclick="showTab('radar'); return false;">📊 Radar Confronto</a>
        <a href="#" class="nav-tab <?php echo $tab == 'competenze' ? 'active' : ''; ?>" onclick="showTab('competenze'); return false;">🎯 Mappa Competenze</a>
        <a href="#" class="nav-tab <?php echo $tab == 'confronta' ? 'active' : ''; ?>" onclick="showTab('confronta'); return false;">👥 Confronta Studenti</a>
        <a href="#" class="nav-tab <?php echo $tab == 'colloquio' ? 'active' : ''; ?>" onclick="showTab('colloquio'); return false;">💬 Colloquio</a>
        <a href="#" class="nav-tab <?php echo $tab == 'matching' ? 'active' : ''; ?>" onclick="showTab('matching'); return false;">🎯 Matching Lavoro</a>
    </div>
    
    <!-- ============================================
         TAB: PANORAMICA
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'panoramica' ? 'active' : ''; ?>" id="tab-panoramica">
        
        <!-- Situazione Attuale -->
        <div class="section-card">
            <div class="section-header">
                <h2>📋 Situazione Attuale</h2>
            </div>
            <div class="section-body">
                <div class="stats-grid">
                    <div class="stat-card purple" onclick="goToAutovalutazione()" title="Clicca per vedere l'autovalutazione">
                        <div class="stat-icon">🧑</div>
                        <div class="stat-value"><?php echo $has_autovalutazione ? '✅' : '❌'; ?></div>
                        <div class="stat-label">Autovalutazione</div>
                        <div class="click-hint">Clicca per vedere dettagli</div>
                    </div>
                    <div class="stat-card blue" onclick="showTab('competenze')" title="Clicca per vedere i quiz">
                        <div class="stat-icon">📝</div>
                        <div class="stat-value"><?php echo $quiz_completati; ?></div>
                        <div class="stat-label">Quiz completati</div>
                        <div class="click-hint">Clicca per vedere i quiz</div>
                    </div>
                    <div class="stat-card green" onclick="filterByStatus('excellent')" title="Clicca per filtrare">
                        <div class="stat-icon">✅</div>
                        <div class="stat-value"><?php echo $aree_eccellenti; ?></div>
                        <div class="stat-label">Aree eccellenti</div>
                        <div class="click-hint">Clicca per filtrare</div>
                    </div>
                    <div class="stat-card orange" onclick="filterByStatus('warning')" title="Clicca per filtrare">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-value"><?php echo $aree_attenzione; ?></div>
                        <div class="stat-label">Aree attenzione</div>
                        <div class="click-hint">Clicca per filtrare</div>
                    </div>
                    <div class="stat-card red" onclick="filterByStatus('critical')" title="Clicca per filtrare">
                        <div class="stat-icon">🔴</div>
                        <div class="stat-value"><?php echo $aree_critiche; ?></div>
                        <div class="stat-label">Aree critiche</div>
                        <div class="click-hint">Clicca per filtrare</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Punti di Forza -->
        <?php if (!empty($punti_forza)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2>💪 Punti di Forza</h2>
            </div>
            <div class="section-body">
                <div class="strengths-grid">
                    <?php foreach ($punti_forza as $key => $area): ?>
                    <div class="strength-item" onclick="openModal('<?php echo $key; ?>')" title="Clicca per vedere dettagli">
                        <span class="icon"><?php echo $area['info']['icona']; ?></span>
                        <div class="info"><div class="name"><?php echo $area['info']['nome']; ?></div></div>
                        <span class="percentage"><?php echo round($area['quiz_media']); ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aree Critiche -->
        <?php if (!empty($aree_gap_critico)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2>⚠️ Aree con Scostamento Critico</h2>
            </div>
            <div class="section-body">
                <?php foreach ($aree_gap_critico as $key => $area): 
                    $is_warning = ($area['gap'] ?? 0) <= 25;
                ?>
                <div class="critical-area-item <?php echo $is_warning ? 'warning-item' : ''; ?>" onclick="openModal('<?php echo $area['info']['classe']; ?>')" title="Clicca per vedere dettaglio">
                    <div class="critical-header">
                        <div class="critical-title">
                            <span class="icon"><?php echo $area['info']['icona']; ?></span>
                            <div>
                                <h3><?php echo $area['info']['nome']; ?></h3>
                                <span class="code"><?php echo $area['totale']; ?> competenze - Settore <?php echo strtoupper($area['info']['settore']); ?></span>
                            </div>
                        </div>
                        <div class="critical-values">
                            <div class="critical-value-box" onclick="event.stopPropagation(); goToAutovalutazioneArea('<?php echo $area['info']['classe']; ?>')">
                                <div class="label">Autovalutazione</div>
                                <div class="value" style="color: #9b59b6;"><?php echo round($area['autoval_media'] ?? 0); ?>%</div>
                            </div>
                            <div class="critical-value-box" onclick="event.stopPropagation(); openModal('<?php echo $area['info']['classe']; ?>')">
                                <div class="label">Quiz tecnici</div>
                                <div class="value" style="color: <?php echo $area['status']['colore']; ?>;"><?php echo round($area['quiz_media'] ?? 0); ?>%</div>
                            </div>
                            <div class="critical-value-box">
                                <span class="gap-indicator sopravvalutazione">⚠️ +<?php echo round($area['gap']); ?>% Sopravvalutazione</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Link a CompetencyManager -->
        <div class="section-card">
            <div class="section-body">
                <div class="advanced-report-link">
                    <div class="link-icon">📈</div>
                    <div class="link-content">
                        <h4>Report Dettagliato Quiz (CompetencyManager)</h4>
                        <p>Analisi approfondita di ogni singolo quiz: risposte date, tempi di risposta, storico tentativi</p>
                    </div>
                    <a href="/local/competencymanager/student_report.php?userid=<?php echo $studentid; ?>" class="btn btn-advanced" target="_blank">
                        🔗 Apri Report Dettagliato
                    </a>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- ============================================
         TAB: RADAR CONFRONTO
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'radar' ? 'active' : ''; ?>" id="tab-radar">
        <div class="section-card">
            <div class="section-header">
                <h2>📊 Radar Confronto: Quiz vs Autovalutazione</h2>
            </div>
            <div class="section-body">
                
                <div class="radar-controls">
                    <div class="control-group">
                        <h4>📊 Visualizza</h4>
                        <label><input type="checkbox" id="showQuiz" checked onchange="updateRadar()"> 🔵 Quiz Tecnici</label>
                        <label><input type="checkbox" id="showAutoval" checked onchange="updateRadar()"> 🟣 Autovalutazione</label>
                    </div>
                    <div class="control-group">
                        <h4>🏭 Settore</h4>
                        <select id="filterSector" onchange="updateRadar()">
                            <option value="all">Tutti i settori</option>
                            <?php foreach ($sectors_data as $code => $sdata): 
                                if ($code === 'ALTRO') continue;
                                if ($sdata['quiz_count'] == 0 && $sdata['autoval_count'] == 0) continue;
                            ?>
                            <option value="<?php echo strtolower($code); ?>"><?php echo $sdata['display_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="radar-container">
                    <div class="radar-card">
                        <h3>🔵 Quiz vs 🟣 Autovalutazione</h3>
                        <div class="radar-canvas-container">
                            <canvas id="radarConfronto"></canvas>
                        </div>
                        <div class="radar-legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(52, 152, 219, 0.8);"></div>
                                <span>Quiz Tecnici</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(155, 89, 182, 0.8);"></div>
                                <span>Autovalutazione</span>
                            </div>
                        </div>
                    </div>
                    <div class="radar-card">
                        <h3>📈 Dettaglio Scostamento (GAP)</h3>
                        <div class="radar-canvas-container">
                            <canvas id="radarGap"></canvas>
                        </div>
                        <div class="radar-legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(231, 76, 60, 0.8);"></div>
                                <span>Sopravvalutazione</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(46, 204, 113, 0.8);"></div>
                                <span>Sottovalutazione</span>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================
         TAB: MAPPA COMPETENZE
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'competenze' ? 'active' : ''; ?>" id="tab-competenze">
        <div class="section-card">
            <div class="section-header">
                <h2>🎯 Mappa Competenze per Area</h2>
            </div>
            <div class="section-body">
                
                <div class="filter-bar">
                    <span class="filter-label">Filtra per:</span>
                    <select id="filterAreaSector" onchange="applyFilters()">
                        <option value="all">Tutti i settori</option>
                        <?php foreach ($sectors_data as $code => $sdata): 
                            if ($code === 'ALTRO') continue;
                            if ($sdata['quiz_count'] == 0 && $sdata['autoval_count'] == 0) continue;
                        ?>
                        <option value="<?php echo strtolower($code); ?>"><?php echo $sdata['display_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterAreaStatus" onchange="applyFilters()">
                        <option value="all">Tutti gli stati</option>
                        <option value="critical">Solo critici</option>
                        <option value="warning">Solo attenzione</option>
                        <option value="good">Solo buoni</option>
                        <option value="excellent">Solo eccellenti</option>
                    </select>
                    <button class="btn btn-secondary" onclick="resetFilters()" style="background: #6c757d; color: white;">
                        🔄 Reset filtri
                    </button>
                </div>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #28a745;"></div>
                        <span>Eccellente (≥90%)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #17a2b8;"></div>
                        <span>Buono (70-89%)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #ffc107;"></div>
                        <span>Attenzione (50-69%)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #dc3545;"></div>
                        <span>Critico (&lt;50%)</span>
                    </div>
                </div>
                
                <div class="areas-grid">
                    <?php foreach ($areas_data as $key => $area): 
                        $quiz_pct = $area['quiz_media'] ?? 0;
                        $autoval_pct = $area['autoval_media'] ?? 0;
                        $status = $area['status'];
                    ?>
                    <div class="area-card <?php echo $area['info']['classe']; ?>" 
                         onclick="openModal('<?php echo $key; ?>')"
                         data-sector="<?php echo $area['info']['settore']; ?>"
                         data-status="<?php echo $status['stato']; ?>">
                        <div class="area-icon"><?php echo $area['info']['icona']; ?></div>
                        <div class="area-name"><?php echo $area['info']['nome']; ?></div>
                        <div class="area-count"><?php echo $area['totale']; ?> competenze</div>
                        <div class="area-mini-comparison">
                            <div class="mini-bar-container">
                                <div class="mini-bar-label">Quiz</div>
                                <div class="mini-bar"><div class="mini-bar-fill quiz" style="width: <?php echo $quiz_pct; ?>%;"></div></div>
                            </div>
                            <div class="mini-bar-container">
                                <div class="mini-bar-label">Auto</div>
                                <div class="mini-bar"><div class="mini-bar-fill autoval" style="width: <?php echo $autoval_pct; ?>%;"></div></div>
                            </div>
                        </div>
                        <div class="area-stats">
                            <span class="area-percentage" style="color: <?php echo $status['colore']; ?>;">
                                <?php echo $quiz_pct > 0 ? round($quiz_pct) . '%' : '-'; ?>
                            </span>
                            <span class="area-status status-<?php echo $status['stato']; ?>"><?php echo $status['label']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Link a CompetencyManager -->
                <div class="advanced-report-link" style="margin-top: 25px;">
                    <div class="link-icon">📈</div>
                    <div class="link-content">
                        <h4>Report Dettagliato Quiz (CompetencyManager)</h4>
                        <p>Analisi approfondita con storico tentativi, tempi di risposta e dettaglio errori</p>
                    </div>
                    <a href="/local/competencymanager/student_report.php?userid=<?php echo $studentid; ?>" class="btn btn-advanced" target="_blank">
                        🔗 Apri Report Dettagliato
                    </a>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================
         TAB: CONFRONTA STUDENTI
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'confronta' ? 'active' : ''; ?>" id="tab-confronta">
        <div class="section-card">
            <div class="section-header">
                <h2>👥 Confronta Studenti</h2>
            </div>
            <div class="section-body">
                
                <div class="compare-section">
                    <div class="compare-header">
                        <h3>📊 Seleziona studenti da confrontare</h3>
                    </div>
                    
                    <div class="student-selector">
                        <div class="student-select-box">
                            <label>👤 Studente 1</label>
                            <select id="student1" onchange="updateComparison()">
                                <option value="<?php echo $studentid; ?>" selected><?php echo $fullname; ?></option>
                                <?php 
                                // Carica altri studenti (include tutti i campi nome per fullname())
                                $other_students = $DB->get_records_sql("
                                    SELECT DISTINCT u.id, u.firstname, u.lastname, 
                                           u.firstnamephonetic, u.lastnamephonetic, 
                                           u.middlename, u.alternatename
                                    FROM {user} u
                                    JOIN {quiz_attempts} qa ON qa.userid = u.id
                                    WHERE u.id != ?
                                    ORDER BY u.lastname, u.firstname
                                    LIMIT 50
                                ", [$studentid]);
                                foreach ($other_students as $s): ?>
                                <option value="<?php echo $s->id; ?>"><?php echo fullname($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="student-select-box">
                            <label>👤 Studente 2</label>
                            <select id="student2" onchange="updateComparison()">
                                <option value="">Seleziona...</option>
                                <?php foreach ($other_students as $s): ?>
                                <option value="<?php echo $s->id; ?>"><?php echo fullname($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="compareResults" style="margin-top: 20px;">
                        <p style="color: #6c757d; font-style: italic;">Seleziona due studenti per vedere il confronto</p>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================
         TAB: COLLOQUIO
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'colloquio' ? 'active' : ''; ?>" id="tab-colloquio">

        <!-- SEZIONE PRIORITÀ PER IL COLLOQUIO (da competencymanager/reports.php) -->
        <?php
        // Prepara dati per priorità colloquio
        $priorita_critici = array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 100) < 50 && $a['quiz_count'] > 0);
        $priorita_attenzione = array_filter($areas_data, fn($a) => ($a['quiz_media'] ?? 100) >= 50 && ($a['quiz_media'] ?? 100) < 70 && $a['quiz_count'] > 0);

        // Ordina per quiz_media crescente (peggiori prima)
        uasort($priorita_critici, fn($a, $b) => ($a['quiz_media'] ?? 0) <=> ($b['quiz_media'] ?? 0));
        uasort($priorita_attenzione, fn($a, $b) => ($a['quiz_media'] ?? 0) <=> ($b['quiz_media'] ?? 0));
        ?>

        <?php if (!empty($priorita_critici) || !empty($priorita_attenzione)): ?>
        <div class="section-card" style="margin-bottom: 20px;">
            <div class="section-header collapsible-header" onclick="toggleSection('priorita-colloquio')" style="background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; cursor: pointer;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <span class="collapse-arrow" id="arrow-priorita-colloquio">▼</span>
                    🎯 Priorità per il Colloquio
                </h2>
            </div>
            <div class="section-body collapsible-content" id="content-priorita-colloquio">

                <?php if (!empty($priorita_critici)): ?>
                <div class="hints-group mb-4">
                    <h4 style="color: #dc3545; margin-bottom: 15px;">🔴 Gap Critici - Priorità Alta</h4>
                    <?php foreach ($priorita_critici as $area_key => $area): ?>
                    <div class="hint-card critical" style="background: #fff5f5; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
                        <div class="hint-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div>
                                <span style="background: <?php echo $area['info']['colore'] ?? '#6c757d'; ?>; color: white; padding: 4px 12px; border-radius: 15px; font-size: 13px;">
                                    <?php echo ($area['info']['icona'] ?? '📊') . ' ' . ($area['info']['nome'] ?? $area_key); ?>
                                </span>
                                <strong style="margin-left: 10px;"><?php echo $area['totale']; ?> competenze</strong>
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <?php if ($has_autovalutazione && isset($area['autoval_media'])): ?>
                                <span style="color: #9b59b6;">🧑 Auto: <?php echo round($area['autoval_media']); ?>%</span>
                                <?php endif; ?>
                                <span style="color: #dc3545; font-weight: bold;">📊 Quiz: <?php echo round($area['quiz_media'] ?? 0); ?>%</span>
                                <?php if (isset($area['gap']) && $area['gap'] !== null): ?>
                                <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px;">
                                    Gap: <?php echo ($area['gap'] > 0 ? '+' : '') . round($area['gap']); ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="hint-body">
                            <p style="color: #666; margin-bottom: 10px;">
                                <?php if (($area['quiz_media'] ?? 0) < 30): ?>
                                    ⚠️ Area molto critica. Lo studente ha difficoltà significative che richiedono attenzione immediata.
                                <?php else: ?>
                                    ⚠️ Area da approfondire. Performance sotto la soglia minima del 50%.
                                <?php endif; ?>
                            </p>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px;">
                                <h5 style="margin: 0 0 8px 0; font-size: 14px;">💬 Domande suggerite per il colloquio:</h5>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li><em>"Quali difficoltà hai incontrato in quest'area?"</em></li>
                                    <li><em>"Hai avuto modo di praticare queste competenze sul lavoro?"</em></li>
                                    <li><em>"Come pensi di poter migliorare in questo ambito?"</em></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($priorita_attenzione)): ?>
                <div class="hints-group mb-4">
                    <h4 style="color: #ffc107; margin-bottom: 15px;">⚠️ Gap Moderati - Attenzione</h4>
                    <?php foreach ($priorita_attenzione as $area_key => $area): ?>
                    <div class="hint-card warning" style="background: #fffbf0; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
                        <div class="hint-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div>
                                <span style="background: <?php echo $area['info']['colore'] ?? '#6c757d'; ?>; color: white; padding: 4px 12px; border-radius: 15px; font-size: 13px;">
                                    <?php echo ($area['info']['icona'] ?? '📊') . ' ' . ($area['info']['nome'] ?? $area_key); ?>
                                </span>
                                <strong style="margin-left: 10px;"><?php echo $area['totale']; ?> competenze</strong>
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <?php if ($has_autovalutazione && isset($area['autoval_media'])): ?>
                                <span style="color: #9b59b6;">🧑 Auto: <?php echo round($area['autoval_media']); ?>%</span>
                                <?php endif; ?>
                                <span style="color: #ffc107; font-weight: bold;">📊 Quiz: <?php echo round($area['quiz_media'] ?? 0); ?>%</span>
                            </div>
                        </div>
                        <div class="hint-body">
                            <p style="color: #666; margin-bottom: 10px;">
                                📋 Area con margini di miglioramento. Performance tra 50% e 70%.
                            </p>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px;">
                                <h5 style="margin: 0 0 8px 0; font-size: 14px;">💬 Domande suggerite:</h5>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li><em>"Come valuti le tue competenze in quest'area?"</em></li>
                                    <li><em>"Quali aspetti vorresti approfondire?"</em></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- SEZIONE PREPARAZIONE COLLOQUIO TECNICO (originale) -->
        <div class="section-card">
            <div class="section-header collapsible-header" onclick="toggleSection('preparazione-colloquio')" style="cursor: pointer;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <span class="collapse-arrow" id="arrow-preparazione-colloquio">▼</span>
                    💬 Preparazione Colloquio Tecnico
                </h2>
            </div>
            <div class="section-body collapsible-content" id="content-preparazione-colloquio">
                
                <?php 
                // Ottieni le domande suggerite
                $all_domande = get_domande_suggerite();
                
                // Determina quali aree mostrare:
                // 1. Se c'è autovalutazione: aree con GAP critico (sopravvalutazione > 15%)
                // 2. Se NON c'è autovalutazione: aree con quiz < 70% (aree deboli)
                
                $aree_da_mostrare = [];
                
                if ($has_autovalutazione && !empty($aree_gap_critico)) {
                    // Mostra aree con GAP critico
                    $aree_da_mostrare = $aree_gap_critico;
                } else {
                    // Mostra aree con quiz sotto 70% (aree deboli)
                    foreach ($areas_data as $key => $area) {
                        if ($area['quiz_count'] > 0 && ($area['quiz_media'] ?? 100) < 70) {
                            $aree_da_mostrare[$key] = $area;
                        }
                    }
                    // Ordina per quiz_media crescente (le più deboli prima)
                    uasort($aree_da_mostrare, fn($a, $b) => ($a['quiz_media'] ?? 0) <=> ($b['quiz_media'] ?? 0));
                }
                
                // Se ancora vuoto, mostra le 3 aree con punteggio più basso
                if (empty($aree_da_mostrare)) {
                    $sorted = $areas_data;
                    uasort($sorted, fn($a, $b) => ($a['quiz_media'] ?? 100) <=> ($b['quiz_media'] ?? 100));
                    $aree_da_mostrare = array_slice($sorted, 0, 3, true);
                }
                
                foreach ($aree_da_mostrare as $area_key => $area): 
                    $has_gap = isset($area['gap']) && $area['gap'] !== null;
                    $gap_value = $area['gap'] ?? 0;
                    $is_warning = $gap_value <= 25 && $gap_value > 0;
                    $is_critical = ($area['quiz_media'] ?? 100) < 50;
                    
                    // Determina colore bordo
                    $border_color = $is_critical ? '#dc3545' : ($is_warning ? '#ffc107' : '#17a2b8');
                    $bg_color = $is_critical ? '#fff5f5' : ($is_warning ? '#fffbf0' : '#f8f9fa');
                ?>
                <div class="critical-area-item" style="background: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;" onclick="openModal('<?php echo $area['info']['classe']; ?>')" title="Clicca per vedere dettaglio competenze">
                    <div class="critical-header">
                        <div class="critical-title">
                            <span class="icon"><?php echo $area['info']['icona']; ?></span>
                            <div>
                                <h3><?php echo $area['info']['nome']; ?></h3>
                                <span class="code">
                                    <?php echo $area['totale']; ?> competenze - Settore <?php echo strtoupper($area['info']['settore']); ?>
                                    <?php if ($has_gap && $gap_value > 0): ?>
                                    | Gap: +<?php echo round($gap_value); ?>% sopravvalutazione
                                    <?php elseif ($has_gap && $gap_value < 0): ?>
                                    | Gap: <?php echo round($gap_value); ?>% sottovalutazione
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="critical-values">
                            <?php if ($has_autovalutazione): ?>
                            <div class="critical-value-box" onclick="event.stopPropagation(); goToAutovalutazioneArea('<?php echo $area['info']['classe']; ?>')" title="Clicca per vedere autovalutazione">
                                <div class="label">Autovalutazione</div>
                                <div class="value" style="color: #9b59b6;"><?php echo round($area['autoval_media'] ?? 0); ?>%</div>
                            </div>
                            <?php endif; ?>
                            <div class="critical-value-box" onclick="event.stopPropagation(); openModal('<?php echo $area['info']['classe']; ?>')" title="Clicca per vedere quiz">
                                <div class="label">Quiz tecnici</div>
                                <div class="value" style="color: <?php echo $area['status']['colore']; ?>;"><?php echo round($area['quiz_media'] ?? 0); ?>%</div>
                            </div>
                            <?php if ($has_gap): ?>
                            <div class="critical-value-box">
                                <?php if ($gap_value > 15): ?>
                                <span class="gap-indicator sopravvalutazione">⚠️ +<?php echo round($gap_value); ?>% Sopravvalutazione</span>
                                <?php elseif ($gap_value < -15): ?>
                                <span class="gap-indicator sottovalutazione">📈 <?php echo round($gap_value); ?>% Sottovalutazione</span>
                                <?php else: ?>
                                <span class="gap-indicator allineato">✅ Allineato</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="critical-value-box">
                                <span class="gap-indicator" style="background: #e9ecef; color: #6c757d;">📭 Autoval. non compilata</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="colloquio-questions">
                        <h4>💬 Domande suggerite:</h4>
                        <ul>
                            <?php 
                            $domande = $all_domande[$area['info']['classe']] ?? [
                                '"Puoi descrivere la tua esperienza in quest\'area?"',
                                '"Quali strumenti utilizzi abitualmente?"',
                                '"Descrivi una situazione lavorativa in cui hai applicato queste competenze"'
                            ];
                            foreach ($domande as $d): ?>
                            <li><?php echo $d; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($aree_da_mostrare)): ?>
                <div class="no-data-message">
                    ✅ Nessuna area critica rilevata.<br>
                    <small>Lo studente ha ottenuto buoni risultati in tutte le aree valutate.</small>
                </div>
                <?php endif; ?>
                
                <!-- Note Coach -->
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;">📝 Note del Coach</h3>
                    <textarea class="coach-notes-textarea" id="coachNotes" placeholder="Scrivi qui le tue osservazioni dopo il colloquio tecnico..."><?php 
                        // Carica note esistenti se presenti
                        $existing_notes = $DB->get_field('local_coachmanager_notes', 'notes', ['studentid' => $studentid]);
                        echo $existing_notes ? $existing_notes : '';
                    ?></textarea>
                    <div class="notes-actions">
                        <button class="btn btn-save" onclick="saveNotes()">💾 Salva note</button>
                    </div>
                </div>
                
                <!-- Link a CompetencyManager -->
                <div class="advanced-report-link">
                    <div class="link-icon">📈</div>
                    <div class="link-content">
                        <h4>Report Dettagliato Quiz (CompetencyManager)</h4>
                        <p>Per un'analisi approfondita di ogni singolo quiz, le risposte date e i tempi</p>
                    </div>
                    <a href="/local/competencymanager/student_report.php?userid=<?php echo $studentid; ?>" class="btn btn-advanced" target="_blank">
                        🔗 Apri CompetencyManager
                    </a>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================
         TAB: MATCHING LAVORO
         ============================================ -->
    <div class="tab-panel <?php echo $tab == 'matching' ? 'active' : ''; ?>" id="tab-matching">
        <div class="section-card">
            <div class="section-header">
                <h2>🎯 Matching con Annunci di Lavoro</h2>
            </div>
            <div class="section-body">
                
                <div class="matching-preview">
                    <h3>🚀 Funzionalità in arrivo!</h3>
                    <p>Questa sezione permetterà di confrontare il profilo dello studente con gli annunci di lavoro disponibili.</p>
                    <span class="coming-soon">🔜 Coming Soon</span>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                    <h4 style="margin-bottom: 15px;">📋 Come funzionerà:</h4>
                    <ol style="padding-left: 20px; color: #555;">
                        <li style="padding: 8px 0;">Inserimento annuncio di lavoro (testo o link)</li>
                        <li style="padding: 8px 0;">Estrazione automatica delle competenze richieste</li>
                        <li style="padding: 8px 0;">Confronto con il profilo dello studente</li>
                        <li style="padding: 8px 0;">Score di compatibilità (match %)</li>
                        <li style="padding: 8px 0;">Suggerimenti per migliorare il match</li>
                        <li style="padding: 8px 0;">Classifica studenti più adatti all'annuncio</li>
                    </ol>
                </div>
                
            </div>
        </div>
    </div>
    
</div>

<!-- ============================================
     MODAL DETTAGLIO AREA
     ============================================ -->
<div class="modal-overlay" id="modal" onclick="closeModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header" id="modalHeader">
            <h2 id="modalTitle">Dettaglio Area</h2>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenuto dinamico -->
        </div>
    </div>
</div>

<script>
// ============================================
// FUNZIONE TOGGLE SEZIONI COLLASSABILI
// ============================================
function toggleSection(sectionId) {
    const content = document.getElementById('content-' + sectionId);
    const arrow = document.getElementById('arrow-' + sectionId);

    if (content.style.display === 'none') {
        content.style.display = 'block';
        arrow.textContent = '▼';
        arrow.style.transform = 'rotate(0deg)';
    } else {
        content.style.display = 'none';
        arrow.textContent = '▶';
        arrow.style.transform = 'rotate(0deg)';
    }
}

// Dati dal PHP
const areasData = <?php echo $areas_json; ?>;
const radarLabels = <?php echo $radar_labels; ?>;
const radarQuizData = <?php echo $radar_quiz_data; ?>;
const radarAutovalData = <?php echo $radar_autoval_data; ?>;
const studentId = <?php echo $studentid; ?>;

// Charts
let radarConfrontoChart = null;
let radarGapChart = null;

// Variabili globali per filtro settore
const currentSectorFilter = <?php echo $current_sector_filter; ?>;
const allSectorsData = <?php echo $sectors_json; ?>;

// ============================================
// FILTRO SETTORE GLOBALE
// ============================================
function applySectorFilter(sector) {
    // Aggiorna URL con il nuovo filtro settore
    const url = new URL(window.location.href);
    url.searchParams.set('sector', sector);
    window.location.href = url.toString();
}

function updatePrintSettings() {
    const printSectorOnly = document.getElementById('printSectorOnly').checked;
    const sectorFilterCard = document.getElementById('sectorFilterCard');
    
    if (printSectorOnly) {
        // Il settore selezionato verrà incluso nella stampa
        sectorFilterCard.classList.add('print-include');
    } else {
        sectorFilterCard.classList.remove('print-include');
    }
}

// ============================================
// TAB NAVIGATION
// ============================================
function showTab(tabId) {
    // Nascondi tutti i pannelli
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    // Rimuovi active da tutti i tab
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    // Mostra il pannello selezionato
    document.getElementById('tab-' + tabId).classList.add('active');
    // Attiva il tab
    document.querySelectorAll('.nav-tab').forEach(tab => {
        if (tab.textContent.toLowerCase().includes(tabId.substring(0,4))) {
            tab.classList.add('active');
        }
    });
    
    // Inizializza radar se necessario
    if (tabId === 'radar') {
        setTimeout(initRadarCharts, 100);
    }
}

// ============================================
// RADAR CHARTS
// ============================================
function initRadarCharts() {
    const ctxConfronto = document.getElementById('radarConfronto');
    const ctxGap = document.getElementById('radarGap');
    
    if (!ctxConfronto || !ctxGap) return;
    
    // Distruggi chart esistenti
    if (radarConfrontoChart) radarConfrontoChart.destroy();
    if (radarGapChart) radarGapChart.destroy();
    
    // Prendi solo le prime 8 aree per leggibilità
    const labels = radarLabels.map(l => l.substring(0, 15));
    const quizData = radarQuizData;
    const autovalData = radarAutovalData;
    
    // Radar Confronto
    radarConfrontoChart = new Chart(ctxConfronto, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Quiz Tecnici',
                    data: quizData,
                    borderColor: 'rgba(52, 152, 219, 1)',
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(52, 152, 219, 1)'
                },
                {
                    label: 'Autovalutazione',
                    data: autovalData,
                    borderColor: 'rgba(155, 89, 182, 1)',
                    backgroundColor: 'rgba(155, 89, 182, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(155, 89, 182, 1)'
                }
            ]
        },
        options: {
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { stepSize: 20 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Radar GAP
    const gapData = autovalData.map((v, i) => v - quizData[i]);
    radarGapChart = new Chart(ctxGap, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                label: 'GAP',
                data: gapData,
                borderColor: 'rgba(231, 76, 60, 1)',
                backgroundColor: 'rgba(231, 76, 60, 0.3)',
                borderWidth: 2
            }]
        },
        options: {
            scales: {
                r: {
                    beginAtZero: false,
                    min: -50,
                    max: 50
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function updateRadar() {
    initRadarCharts();
}

// ============================================
// NAVIGAZIONE DA CARD
// ============================================
function goToAutovalutazione() {
    showTab('radar');
    showNotification('📊 Visualizzazione: Radar Confronto');
}

function goToAutovalutazioneArea(area) {
    showTab('radar');
    showNotification('📊 Confronto area: ' + area);
}

function goToQuizArea(area) {
    openModal(area);
}

// ============================================
// FILTRI
// ============================================
function filterByStatus(status) {
    showTab('competenze');
    document.getElementById('filterAreaStatus').value = status;
    applyFilters();
}

function applyFilters() {
    const statusFilter = document.getElementById('filterAreaStatus').value;
    const sectorFilter = document.getElementById('filterAreaSector').value;
    
    const cards = document.querySelectorAll('.area-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardStatus = card.dataset.status;
        const cardSector = card.dataset.sector;
        
        const matchStatus = (statusFilter === 'all' || cardStatus === statusFilter);
        const matchSector = (sectorFilter === 'all' || cardSector === sectorFilter);
        
        if (matchStatus && matchSector) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.3s ease';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    showNotification('🎯 ' + visibleCount + ' aree visualizzate');
}

function resetFilters() {
    document.getElementById('filterAreaStatus').value = 'all';
    document.getElementById('filterAreaSector').value = 'all';
    
    document.querySelectorAll('.area-card').forEach(card => {
        card.style.display = 'block';
    });
    
    showNotification('🔄 Filtri resettati');
}

// ============================================
// MODAL - DETTAGLIO COMPETENZE
// ============================================
function openModal(areaKey) {
    const area = areasData[areaKey];
    if (!area) {
        showNotification('⚠️ Area non trovata');
        return;
    }
    
    document.getElementById('modalTitle').innerHTML = area.info.icona + ' ' + area.info.nome;
    document.getElementById('modalHeader').style.background = 'linear-gradient(135deg, ' + area.info.colore + ', ' + area.info.colore + 'cc)';
    
    let bodyHtml = '';
    
    // Header con statistiche area
    bodyHtml += '<p style="margin-bottom: 15px; color: #6c757d;">' + area.totale + ' competenze - Settore: ' + area.info.settore.toUpperCase() + '</p>';
    
    // Stats area
    bodyHtml += '<div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">';
    bodyHtml += '<div class="critical-value-box"><div class="label">Quiz Media</div><div class="value" style="color: ' + (area.status ? area.status.colore : '#333') + ';">' + Math.round(area.quiz_media || 0) + '%</div></div>';
    bodyHtml += '<div class="critical-value-box"><div class="label">Autovalutazione</div><div class="value" style="color: #9b59b6;">' + Math.round(area.autoval_media || 0) + '%</div></div>';
    if (area.gap !== null && area.gap !== undefined) {
        let gapClass = 'allineato';
        let gapIcon = '✅';
        if (area.gap > 15) { gapClass = 'sopravvalutazione'; gapIcon = '⚠️ +'; }
        else if (area.gap < -15) { gapClass = 'sottovalutazione'; gapIcon = '📈'; }
        bodyHtml += '<div class="critical-value-box"><span class="gap-indicator ' + gapClass + '">' + gapIcon + Math.round(area.gap) + '% Gap</span></div>';
    }
    bodyHtml += '</div>';
    
    // Lista competenze dettagliate
    bodyHtml += '<h4 style="margin-bottom: 15px;">📋 Competenze in quest\'area:</h4>';
    
    // Usa competenze_detailed se disponibili, altrimenti competenze semplici
    const competenze = area.competenze_detailed || area.competenze || [];
    
    competenze.forEach((comp, idx) => {
        const compId = 'comp_' + areaKey + '_' + idx;
        const hasAutoval = comp.autoval && comp.autoval !== null;
        const hasQuiz = comp.quizzes && comp.quizzes.length > 0;
        const quizMedia = comp.quiz_media || 0;
        const autovalPct = hasAutoval ? comp.autoval.percentage : 0;
        
        // Determina colore quiz
        let quizColor = '#28a745'; // verde
        if (quizMedia < 50) quizColor = '#dc3545'; // rosso
        else if (quizMedia < 70) quizColor = '#ffc107'; // giallo
        else if (quizMedia < 90) quizColor = '#17a2b8'; // blu
        
        bodyHtml += '<div class="competency-item" id="' + compId + '">';
        
        // HEADER COMPETENZA
        bodyHtml += '<div class="competency-header" onclick="toggleCompetency(\'' + compId + '\')">';
        bodyHtml += '<div class="competency-info">';
        bodyHtml += '<div class="competency-name">' + (comp.shortname || comp.description || comp.idnumber) + '</div>';
        bodyHtml += '<div class="competency-code">' + comp.idnumber + '</div>';
        bodyHtml += '</div>';
        
        // Values boxes
        bodyHtml += '<div class="competency-values">';
        if (hasAutoval) {
            bodyHtml += '<div class="value-box"><div class="label">Autoval.</div><div class="value" style="color: #9b59b6;">' + autovalPct + '%</div></div>';
        }
        if (hasQuiz) {
            bodyHtml += '<div class="value-box"><div class="label">Quiz</div><div class="value" style="color: ' + quizColor + ';">' + quizMedia + '%</div></div>';
        }
        if (!hasAutoval && !hasQuiz) {
            bodyHtml += '<div class="value-box"><div class="label">Stato</div><div class="value" style="color: #6c757d;">-</div></div>';
        }
        bodyHtml += '</div>';
        
        bodyHtml += '<div class="competency-toggle">▼</div>';
        bodyHtml += '</div>';
        
        // DETTAGLI COMPETENZA (espandibile)
        bodyHtml += '<div class="competency-details">';
        
        // Sezione Autovalutazione
        if (hasAutoval) {
            const av = comp.autoval;
            bodyHtml += '<div class="detail-section">';
            bodyHtml += '<div class="detail-section-title">🧑 Autovalutazione</div>';
            bodyHtml += '<div class="self-assessment-card">';
            bodyHtml += '<span class="level-badge level-' + av.level + '">Livello ' + av.level + ' - ' + av.bloom_nome + '</span>';
            bodyHtml += '<p style="margin-top: 10px; color: #6c757d; font-style: italic;">"' + av.bloom_desc + '"</p>';
            
            // Gap analysis
            if (comp.gap !== null && comp.gap !== undefined) {
                let gapBoxClass = 'info';
                let gapText = '✅ Allineato con i quiz';
                if (comp.gap > 15) {
                    gapBoxClass = 'warning';
                    gapText = '⚠️ Scostamento: +' + Math.round(comp.gap) + '% sopravvalutazione';
                } else if (comp.gap < -15) {
                    gapBoxClass = 'success';
                    gapText = '📈 Scostamento: ' + Math.round(comp.gap) + '% sottovalutazione';
                }
                bodyHtml += '<div class="gap-analysis-box ' + gapBoxClass + '" style="margin-top: 10px;">' + gapText + '</div>';
            }
            bodyHtml += '</div>';
            bodyHtml += '</div>';
        } else {
            bodyHtml += '<div class="detail-section">';
            bodyHtml += '<div class="detail-section-title">🧑 Autovalutazione</div>';
            bodyHtml += '<div class="no-data-message" style="padding: 15px;">📭 Autovalutazione non compilata per questa competenza</div>';
            bodyHtml += '</div>';
        }
        
        // Sezione Quiz
        bodyHtml += '<div class="detail-section">';
        bodyHtml += '<div class="detail-section-title">📝 Quiz Tecnici</div>';
        
        if (hasQuiz) {
            comp.quizzes.forEach(quiz => {
                let scoreClass = 'good';
                if (quiz.score < 50) scoreClass = 'bad';
                else if (quiz.score < 70) scoreClass = 'warning';
                
                bodyHtml += '<div class="quiz-item">';
                bodyHtml += '<div class="quiz-header">';
                bodyHtml += '<span class="quiz-name">' + quiz.quiz_name + '</span>';
                bodyHtml += '<span class="quiz-score ' + scoreClass + '">' + quiz.score + '%</span>';
                bodyHtml += '</div>';
                bodyHtml += '<div class="quiz-details">';
                bodyHtml += '<span>📅 ' + quiz.date + '</span>';
                bodyHtml += '<a href="/mod/quiz/review.php?attempt=' + quiz.quiz_id + '" class="quiz-link" target="_blank">🔍 Vedi tentativo</a>';
                bodyHtml += '</div>';
                bodyHtml += '</div>';
            });
        } else {
            bodyHtml += '<div class="no-data-message" style="padding: 15px;">📭 Nessun quiz svolto per questa competenza</div>';
        }
        bodyHtml += '</div>';
        
        bodyHtml += '</div>'; // competency-details
        bodyHtml += '</div>'; // competency-item
    });
    
    document.getElementById('modalBody').innerHTML = bodyHtml;
    document.getElementById('modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(event) {
    if (!event || event.target.classList.contains('modal-overlay')) {
        document.getElementById('modal').classList.remove('active');
        document.body.style.overflow = '';
    }
}

function toggleCompetency(id) {
    document.getElementById(id).classList.toggle('open');
}

// ============================================
// SAVE NOTES
// ============================================
function saveNotes() {
    const notes = document.getElementById('coachNotes').value;
    
    fetch('ajax_save_notes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'studentid=' + studentId + '&notes=' + encodeURIComponent(notes)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('💾 Note salvate con successo!');
        } else {
            showNotification('❌ Errore nel salvataggio');
        }
    })
    .catch(() => {
        showNotification('❌ Errore di connessione');
    });
}

// ============================================
// COMPARE STUDENTS - AJAX
// ============================================
let compareRadarChart = null;

function updateComparison() {
    const student1 = document.getElementById('student1').value;
    const student2 = document.getElementById('student2').value;
    
    if (!student1 || !student2) {
        document.getElementById('compareResults').innerHTML = '<p style="color: #6c757d; font-style: italic;">Seleziona due studenti per vedere il confronto</p>';
        return;
    }
    
    if (student1 === student2) {
        document.getElementById('compareResults').innerHTML = '<div class="no-data-message">⚠️ Seleziona due studenti diversi</div>';
        return;
    }
    
    // Mostra loading
    document.getElementById('compareResults').innerHTML = '<div class="no-data-message">🔄 Caricamento confronto in corso...</div>';
    
    // Chiamata AJAX
    fetch('ajax_compare_students.php?student1=' + student1 + '&student2=' + student2)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('compareResults').innerHTML = '<div class="no-data-message">❌ ' + (data.error || 'Errore nel caricamento') + '</div>';
                return;
            }
            
            renderComparison(data);
        })
        .catch(error => {
            console.error('Errore:', error);
            document.getElementById('compareResults').innerHTML = '<div class="no-data-message">❌ Errore di connessione</div>';
        });
}

function renderComparison(data) {
    let html = '';
    
    // Header con nomi studenti
    html += '<div style="display: flex; justify-content: space-around; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">';
    html += '<div style="text-align: center; padding: 15px 25px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 10px;">';
    html += '<div style="font-size: 0.8em; opacity: 0.9;">Studente 1</div>';
    html += '<div style="font-size: 1.2em; font-weight: 600;">🔵 ' + data.student1.name + '</div>';
    html += '</div>';
    html += '<div style="text-align: center; padding: 15px 25px; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border-radius: 10px;">';
    html += '<div style="font-size: 0.8em; opacity: 0.9;">Studente 2</div>';
    html += '<div style="font-size: 1.2em; font-weight: 600;">🔴 ' + data.student2.name + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Tabella confronto
    html += '<h4 style="margin: 20px 0 15px 0;">📊 Confronto per Area:</h4>';
    html += '<table class="compare-table">';
    html += '<thead><tr>';
    html += '<th>Area</th>';
    html += '<th>🔵 ' + data.student1.name.split(' ')[0] + '</th>';
    html += '<th>🔴 ' + data.student2.name.split(' ')[0] + '</th>';
    html += '<th>Differenza</th>';
    html += '</tr></thead><tbody>';
    
    data.comparison.forEach(item => {
        const diffColor = item.diff > 5 ? '#28a745' : (item.diff < -5 ? '#dc3545' : '#6c757d');
        const diffSign = item.diff > 0 ? '+' : '';
        
        html += '<tr>';
        html += '<td>' + item.icona + ' ' + item.nome + '</td>';
        html += '<td>';
        html += '<div style="display: flex; align-items: center; gap: 10px;">';
        html += '<span style="min-width: 45px;">' + item.student1 + '%</span>';
        html += '<div style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">';
        html += '<div style="height: 100%; width: ' + item.student1 + '%; background: #3498db; border-radius: 4px;"></div>';
        html += '</div>';
        html += '</div>';
        html += '</td>';
        html += '<td>';
        html += '<div style="display: flex; align-items: center; gap: 10px;">';
        html += '<span style="min-width: 45px;">' + item.student2 + '%</span>';
        html += '<div style="flex: 1; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">';
        html += '<div style="height: 100%; width: ' + item.student2 + '%; background: #e74c3c; border-radius: 4px;"></div>';
        html += '</div>';
        html += '</div>';
        html += '</td>';
        html += '<td style="color: ' + diffColor + '; font-weight: 600;">' + diffSign + item.diff + '%</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    // Radar chart
    html += '<div style="margin-top: 30px;">';
    html += '<h4 style="margin-bottom: 15px;">📊 Radar Confronto:</h4>';
    html += '<div style="max-width: 500px; margin: 0 auto;">';
    html += '<canvas id="compareRadarCanvas"></canvas>';
    html += '</div>';
    html += '<div class="radar-legend" style="margin-top: 15px;">';
    html += '<div class="legend-item"><div class="legend-color" style="background: rgba(52, 152, 219, 0.8);"></div><span>' + data.student1.name + '</span></div>';
    html += '<div class="legend-item"><div class="legend-color" style="background: rgba(231, 76, 60, 0.8);"></div><span>' + data.student2.name + '</span></div>';
    html += '</div>';
    html += '</div>';
    
    document.getElementById('compareResults').innerHTML = html;
    
    // Inizializza radar chart
    setTimeout(() => {
        initCompareRadar(data.radar, data.student1.name, data.student2.name);
    }, 100);
    
    showNotification('👥 Confronto caricato');
}

function initCompareRadar(radarData, name1, name2) {
    const ctx = document.getElementById('compareRadarCanvas');
    if (!ctx) return;
    
    if (compareRadarChart) {
        compareRadarChart.destroy();
    }
    
    compareRadarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: radarData.labels,
            datasets: [
                {
                    label: name1,
                    data: radarData.data1,
                    borderColor: 'rgba(52, 152, 219, 1)',
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(52, 152, 219, 1)'
                },
                {
                    label: name2,
                    data: radarData.data2,
                    borderColor: 'rgba(231, 76, 60, 1)',
                    backgroundColor: 'rgba(231, 76, 60, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(231, 76, 60, 1)'
                }
            ]
        },
        options: {
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { stepSize: 20 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// ============================================
// NOTIFICATIONS
// ============================================
function showNotification(message) {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.innerHTML = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 2500);
}

// ============================================
// INIT
// ============================================
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// Sincronizza filtro Mappa Competenze con filtro globale
if (currentSectorFilter && currentSectorFilter !== "all") {
    const filterSelect = document.getElementById("filterAreaSector");
    if (filterSelect) {
        filterSelect.value = currentSectorFilter;
        applyFilters();
    }
}

// Inizializza radar se tab attivo
if (document.getElementById('tab-radar').classList.contains('active')) {
    setTimeout(initRadarCharts, 500);
}
</script>

<?php
echo $OUTPUT->footer();
?>
