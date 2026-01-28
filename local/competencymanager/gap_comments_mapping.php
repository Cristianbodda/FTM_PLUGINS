<?php
/**
 * Mappatura Gap Analysis - Commenti Automatici per Area
 *
 * Genera commenti automatici basati sul confronto Autovalutazione vs Quiz
 * con suggerimenti orientati alle attivita lavorative specifiche per settore/area.
 *
 * @package    local_competencymanager
 * @author     FTM Development Team
 * @version    1.0.0
 * @since      28/01/2026
 */

defined('MOODLE_INTERNAL') || die();

// ============================================
// CONFIGURAZIONE SOGLIE DEFAULT
// ============================================
define('GAP_SOGLIA_ALLINEATO', 10);      // < 10% = Allineato (verde)
define('GAP_SOGLIA_MONITORARE', 25);     // 10-25% = Da monitorare (arancione)
// > 25% = Critico (rosso)

// ============================================
// MAPPATURA ATTIVITA LAVORATIVE PER AREA
// ============================================

/**
 * Struttura:
 * 'SETTORE_AREA' => [
 *     'nome' => 'Nome completo area',
 *     'attivita_alta' => ['Attivita 1', 'Attivita 2', ...],  // Se punteggio ALTO
 *     'attivita_bassa' => ['Cautela 1', 'Cautela 2', ...],   // Se punteggio BASSO
 *     'ruoli_adatti' => ['Ruolo 1', 'Ruolo 2'],              // Ruoli lavorativi
 *     'competenze_chiave' => ['Comp 1', 'Comp 2'],           // Competenze correlate
 * ]
 */

$GAP_ACTIVITY_MAPPING = [

    // ========================================
    // AUTOMOBILE (14 aree: A-N)
    // ========================================

    'AUTOMOBILE_A' => [
        'nome' => 'Accoglienza, diagnosi preliminare e documentazione',
        'attivita_alta' => [
            'Accoglienza clienti in officina',
            'Compilazione ordini di lavoro',
            'Diagnosi preliminare guasti',
            'Gestione appuntamenti',
            'Comunicazione con clienti',
        ],
        'attivita_bassa' => [
            'Ruoli di back-office inizialmente',
            'Affiancamento in reception',
            'Supporto amministrativo',
        ],
        'ruoli_adatti' => ['Accettatore', 'Receptionist officina', 'Service advisor junior'],
        'competenze_chiave' => ['Comunicazione', 'Organizzazione', 'Uso PC e gestionali'],
    ],

    'AUTOMOBILE_B' => [
        'nome' => 'Motore e alimentazione',
        'attivita_alta' => [
            'Manutenzione ordinaria motori',
            'Sostituzione componenti alimentazione',
            'Tagliandi completi',
            'Diagnosi problemi motore',
            'Interventi su iniettori e pompe',
        ],
        'attivita_bassa' => [
            'Supervisione su interventi motore',
            'Affiancamento obbligatorio',
            'Solo interventi base con controllo',
        ],
        'ruoli_adatti' => ['Meccanico motorista', 'Tecnico manutenzione', 'Addetto tagliandi'],
        'competenze_chiave' => ['Meccanica motori', 'Sistemi iniezione', 'Diagnostica'],
    ],

    'AUTOMOBILE_C' => [
        'nome' => 'Lubrificazione e raffreddamento',
        'attivita_alta' => [
            'Cambio olio e filtri',
            'Controllo e rabbocco liquidi',
            'Sostituzione componenti raffreddamento',
            'Diagnosi perdite',
            'Manutenzione circuiti idraulici',
        ],
        'attivita_bassa' => [
            'Solo cambio olio base',
            'Controlli visivi con supervisione',
        ],
        'ruoli_adatti' => ['Addetto cambio rapido', 'Tecnico manutenzione base'],
        'competenze_chiave' => ['Sistemi lubrificazione', 'Circuiti raffreddamento'],
    ],

    'AUTOMOBILE_D' => [
        'nome' => 'Scarico e controllo emissioni',
        'attivita_alta' => [
            'Controllo emissioni e revisioni',
            'Sostituzione catalizzatori',
            'Diagnosi sistemi anti-inquinamento',
            'Interventi su impianti di scarico',
            'Taratura sonde lambda',
        ],
        'attivita_bassa' => [
            'Esclusione da revisioni autonome',
            'Affiancamento su controlli emissioni',
        ],
        'ruoli_adatti' => ['Tecnico revisioni', 'Addetto controllo emissioni'],
        'competenze_chiave' => ['Normative emissioni', 'Sistemi catalitici', 'Strumenti analisi gas'],
    ],

    'AUTOMOBILE_E' => [
        'nome' => 'Trasmissione e trazione',
        'attivita_alta' => [
            'Sostituzione frizione',
            'Interventi su cambi manuali/automatici',
            'Diagnosi problemi trasmissione',
            'Manutenzione differenziali',
            'Controllo alberi di trasmissione',
        ],
        'attivita_bassa' => [
            'No interventi cambio autonomi',
            'Supervisione stretta richiesta',
        ],
        'ruoli_adatti' => ['Meccanico trasmissioni', 'Tecnico cambi automatici'],
        'competenze_chiave' => ['Cambi manuali/automatici', 'Frizioni', 'Trazione integrale'],
    ],

    'AUTOMOBILE_F' => [
        'nome' => 'Sospensioni, sterzo e freni',
        'attivita_alta' => [
            'Sostituzione pastiglie e dischi',
            'Interventi su sospensioni',
            'Controllo geometria sterzo',
            'Diagnosi problemi frenata',
            'Manutenzione sistemi ABS',
        ],
        'attivita_bassa' => [
            'ATTENZIONE: Area sicurezza critica',
            'Affiancamento OBBLIGATORIO su freni',
            'No interventi autonomi su sterzo',
        ],
        'ruoli_adatti' => ['Tecnico freni', 'Gommista specializzato', 'Meccanico sospensioni'],
        'competenze_chiave' => ['Impianti frenanti', 'Geometrie', 'Sicurezza attiva'],
    ],

    'AUTOMOBILE_G' => [
        'nome' => 'Elettronica di bordo e reti CAN/LIN',
        'attivita_alta' => [
            'Diagnosi computerizzata',
            'Lettura e reset centraline',
            'Interventi su reti CAN-BUS',
            'Aggiornamenti software veicolo',
            'Diagnosi sensori e attuatori',
        ],
        'attivita_bassa' => [
            'Formazione aggiuntiva su diagnostica',
            'Solo lettura codici errore',
            'No programmazione centraline',
        ],
        'ruoli_adatti' => ['Elettrauto diagnostico', 'Tecnico meccatronico', 'Specialista software'],
        'competenze_chiave' => ['Diagnosi elettronica', 'Reti CAN/LIN', 'Software diagnosi'],
    ],

    'AUTOMOBILE_H' => [
        'nome' => 'Sistemi ADAS e sensori',
        'attivita_alta' => [
            'Calibrazione sensori ADAS',
            'Diagnosi sistemi assistenza guida',
            'Interventi su radar e telecamere',
            'Taratura sistemi frenata automatica',
        ],
        'attivita_bassa' => [
            'Area specialistica: richiede certificazione',
            'No interventi ADAS senza formazione specifica',
        ],
        'ruoli_adatti' => ['Tecnico ADAS certificato', 'Specialista sistemi avanzati'],
        'competenze_chiave' => ['Calibrazione ADAS', 'Sistemi radar', 'Telecamere'],
    ],

    'AUTOMOBILE_I' => [
        'nome' => 'Climatizzazione e termica abitacolo (HVAC)',
        'attivita_alta' => [
            'Ricarica climatizzatore',
            'Diagnosi impianto A/C',
            'Sostituzione compressori',
            'Interventi su riscaldamento',
            'Gestione gas refrigeranti',
        ],
        'attivita_bassa' => [
            'Solo ricariche base con supervisione',
            'Richiesta patentino F-gas per interventi completi',
        ],
        'ruoli_adatti' => ['Tecnico climatizzazione', 'Addetto A/C'],
        'competenze_chiave' => ['Impianti A/C', 'Gas refrigeranti', 'Normative ambientali'],
    ],

    'AUTOMOBILE_J' => [
        'nome' => 'Veicoli ibridi ed elettrici (alta tensione)',
        'attivita_alta' => [
            'Manutenzione veicoli elettrici',
            'Diagnosi batterie HV',
            'Interventi su sistemi alta tensione',
            'Gestione sicurezza HV',
        ],
        'attivita_bassa' => [
            'ESCLUSIONE TOTALE da veicoli HV senza certificazione PES/PAV',
            'Rischio elettrico elevato',
        ],
        'ruoli_adatti' => ['Tecnico EV certificato', 'Specialista alta tensione'],
        'competenze_chiave' => ['Sicurezza HV', 'Batterie litio', 'Motori elettrici'],
    ],

    'AUTOMOBILE_K' => [
        'nome' => 'Carrozzeria e allestimenti (interventi base)',
        'attivita_alta' => [
            'Sostituzione componenti carrozzeria',
            'Interventi base su paraurti',
            'Montaggio accessori',
            'Piccole riparazioni lamieristiche',
        ],
        'attivita_bassa' => [
            'No interventi strutturali',
            'Solo montaggio accessori semplici',
        ],
        'ruoli_adatti' => ['Aiuto carrozziere', 'Montatore accessori'],
        'competenze_chiave' => ['Lamieristica base', 'Montaggio'],
    ],

    'AUTOMOBILE_L' => [
        'nome' => 'Qualita, sicurezza e ambiente',
        'attivita_alta' => [
            'Controllo qualita lavori',
            'Gestione rifiuti officina',
            'Applicazione procedure sicurezza',
            'Documentazione conformita',
        ],
        'attivita_bassa' => [
            'Formazione su normative sicurezza',
            'Supervisione su gestione rifiuti',
        ],
        'ruoli_adatti' => ['Responsabile qualita junior', 'Addetto sicurezza'],
        'competenze_chiave' => ['Normative sicurezza', 'Gestione rifiuti', 'Qualita'],
    ],

    'AUTOMOBILE_M' => [
        'nome' => 'Relazione cliente e amministrazione',
        'attivita_alta' => [
            'Gestione rapporto clienti',
            'Preventivi e fatturazione',
            'Gestione reclami',
            'Consulenza post-vendita',
        ],
        'attivita_bassa' => [
            'Ruoli tecnici puri preferibili',
            'Supporto back-office con supervisione',
        ],
        'ruoli_adatti' => ['Service advisor', 'Addetto front-office', 'Impiegato officina'],
        'competenze_chiave' => ['Comunicazione', 'Gestionale', 'Customer care'],
    ],

    'AUTOMOBILE_N' => [
        'nome' => 'Manutenzione programmata e collaudi',
        'attivita_alta' => [
            'Esecuzione tagliandi completi',
            'Collaudi pre-consegna',
            'Check-up completi veicolo',
            'Compilazione libretti manutenzione',
        ],
        'attivita_bassa' => [
            'Solo tagliandi base con checklist',
            'Affiancamento su collaudi',
        ],
        'ruoli_adatti' => ['Tecnico tagliandi', 'Addetto collaudi'],
        'competenze_chiave' => ['Procedure manutenzione', 'Checklist', 'Documentazione'],
    ],

    // ========================================
    // LOGISTICA (8 aree: A-H)
    // ========================================

    'LOGISTICA_A' => [
        'nome' => 'Organizzazione dei mandati logistici',
        'attivita_alta' => [
            'Coordinamento spedizioni',
            'Pianificazione carichi',
            'Gestione ordini complessi',
            'Ottimizzazione percorsi',
        ],
        'attivita_bassa' => [
            'Ruoli operativi semplici',
            'Esecuzione ordini predefiniti',
        ],
        'ruoli_adatti' => ['Coordinatore logistico junior', 'Addetto pianificazione'],
        'competenze_chiave' => ['Pianificazione', 'Coordinamento', 'Software gestionali'],
    ],

    'LOGISTICA_B' => [
        'nome' => 'Qualita ed efficienza dei processi',
        'attivita_alta' => [
            'Monitoraggio KPI logistici',
            'Miglioramento processi',
            'Controllo qualita spedizioni',
            'Reportistica performance',
        ],
        'attivita_bassa' => [
            'Esecuzione procedure standard',
            'Segnalazione anomalie',
        ],
        'ruoli_adatti' => ['Addetto qualita logistica', 'Analista processi junior'],
        'competenze_chiave' => ['KPI', 'Analisi dati', 'Problem solving'],
    ],

    'LOGISTICA_C' => [
        'nome' => 'Ricezione, controllo e stoccaggio merce',
        'attivita_alta' => [
            'Gestione ricevimenti autonoma',
            'Controllo qualita merce',
            'Organizzazione stoccaggio',
            'Gestione inventari',
        ],
        'attivita_bassa' => [
            'Affiancamento in ricezione',
            'Controlli base con checklist',
        ],
        'ruoli_adatti' => ['Magazziniere', 'Addetto ricezione', 'Inventory controller'],
        'competenze_chiave' => ['Controllo merce', 'WMS', 'Inventari'],
    ],

    'LOGISTICA_D' => [
        'nome' => 'Commissionamento, preparazione e spedizione',
        'attivita_alta' => [
            'Picking ordini complessi',
            'Preparazione spedizioni',
            'Gestione imballaggi',
            'Coordinamento corrieri',
        ],
        'attivita_bassa' => [
            'Ordini semplici con supervisione',
            'Picking base con scanner',
        ],
        'ruoli_adatti' => ['Picker', 'Addetto spedizioni', 'Packer'],
        'competenze_chiave' => ['Picking', 'Imballaggio', 'Etichettatura'],
    ],

    'LOGISTICA_E' => [
        'nome' => 'Accettazione invii e consulenza',
        'attivita_alta' => [
            'Gestione clienti allo sportello',
            'Consulenza spedizioni',
            'Preventivi trasporto',
            'Gestione reclami',
        ],
        'attivita_bassa' => [
            'Supporto sportello con affiancamento',
            'Operazioni base',
        ],
        'ruoli_adatti' => ['Addetto sportello', 'Customer service logistico'],
        'competenze_chiave' => ['Customer care', 'Tariffe trasporto', 'Comunicazione'],
    ],

    'LOGISTICA_F' => [
        'nome' => 'Recapito e servizi logistici',
        'attivita_alta' => [
            'Consegne autonome',
            'Gestione giri di consegna',
            'Ritiri programmati',
            'Gestione contrassegni',
        ],
        'attivita_bassa' => [
            'Consegne accompagnate',
            'Percorsi semplici predefiniti',
        ],
        'ruoli_adatti' => ['Corriere', 'Autista consegne', 'Driver'],
        'competenze_chiave' => ['Guida', 'Orientamento', 'Gestione contanti'],
    ],

    'LOGISTICA_G' => [
        'nome' => 'Operazioni di magazzino',
        'attivita_alta' => [
            'Movimentazione merci completa',
            'Uso carrelli elevatori',
            'Gestione ubicazioni',
            'Ottimizzazione spazi',
        ],
        'attivita_bassa' => [
            'Movimentazione manuale leggera',
            'Supporto operativo',
        ],
        'ruoli_adatti' => ['Carrellista', 'Operatore magazzino', 'Mulettista'],
        'competenze_chiave' => ['Carrello elevatore', 'Sicurezza magazzino', 'WMS'],
    ],

    'LOGISTICA_H' => [
        'nome' => 'Commissionamento e carico',
        'attivita_alta' => [
            'Carico automezzi',
            'Ottimizzazione carichi',
            'Gestione piani di carico',
            'Fissaggio e sicurezza merci',
        ],
        'attivita_bassa' => [
            'Supporto carico con supervisione',
            'Operazioni semplici',
        ],
        'ruoli_adatti' => ['Addetto carico/scarico', 'Loader'],
        'competenze_chiave' => ['Carico merci', 'Fissaggio', 'Sicurezza trasporto'],
    ],

    // ========================================
    // ELETTRICITA (8 aree: A-H)
    // ========================================

    'ELETTRICITA_A' => [
        'nome' => 'Pianificazione e Progettazione',
        'attivita_alta' => [
            'Lettura schemi elettrici',
            'Dimensionamento impianti',
            'Preparazione materiali',
            'Preventivazione',
        ],
        'attivita_bassa' => [
            'Solo esecuzione da progetto',
            'Supporto tecnico',
        ],
        'ruoli_adatti' => ['Progettista junior', 'Tecnico preventivista'],
        'competenze_chiave' => ['Schemi elettrici', 'Normative', 'CAD'],
    ],

    'ELETTRICITA_B' => [
        'nome' => 'Installazione impianti BT (edifici)',
        'attivita_alta' => [
            'Installazione impianti civili completi',
            'Posa cavi e canaline',
            'Installazione quadri',
            'Cablaggio appartamenti',
        ],
        'attivita_bassa' => [
            'Lavori semplici con supervisione',
            'Assistenza installatore senior',
        ],
        'ruoli_adatti' => ['Elettricista civile', 'Installatore impianti'],
        'competenze_chiave' => ['Impianti civili', 'Normativa CEI', 'Sicurezza'],
    ],

    'ELETTRICITA_C' => [
        'nome' => 'Montaggio e Cablaggio quadri',
        'attivita_alta' => [
            'Cablaggio quadri elettrici',
            'Montaggio componentistica',
            'Connessioni e morsettiere',
            'Etichettatura professionale',
        ],
        'attivita_bassa' => [
            'Solo cablaggi semplici',
            'Assistenza quadrista',
        ],
        'ruoli_adatti' => ['Quadrista', 'Cablatore', 'Montatore quadri'],
        'competenze_chiave' => ['Cablaggio', 'Schemi quadri', 'Componentistica'],
    ],

    'ELETTRICITA_D' => [
        'nome' => 'Reti di distribuzione (media/bassa tensione)',
        'attivita_alta' => [
            'Interventi su cabine MT/BT',
            'Posa linee distribuzione',
            'Giunzioni e terminazioni',
        ],
        'attivita_bassa' => [
            'Area specialistica: richiede qualifiche',
            'Solo BT con supervisione',
        ],
        'ruoli_adatti' => ['Elettricista reti', 'Tecnico cabine'],
        'competenze_chiave' => ['Media tensione', 'Cabine', 'Sicurezza MT'],
    ],

    'ELETTRICITA_E' => [
        'nome' => 'Misure, collaudi e verifiche',
        'attivita_alta' => [
            'Collaudi impianti',
            'Misure elettriche',
            'Verifiche periodiche',
            'Redazione dichiarazioni conformita',
        ],
        'attivita_bassa' => [
            'Solo misure base',
            'Supporto collaudatore',
        ],
        'ruoli_adatti' => ['Collaudatore impianti', 'Verificatore'],
        'competenze_chiave' => ['Strumenti misura', 'Normative verifiche', 'DiCo'],
    ],

    'ELETTRICITA_F' => [
        'nome' => 'Sicurezza, norme e conformita',
        'attivita_alta' => [
            'Applicazione normative CEI',
            'Gestione sicurezza cantiere',
            'Documentazione conformita',
            'Formazione sicurezza',
        ],
        'attivita_bassa' => [
            'Rispetto procedure base',
            'Formazione continua necessaria',
        ],
        'ruoli_adatti' => ['Responsabile sicurezza junior', 'Addetto conformita'],
        'competenze_chiave' => ['Normative CEI', 'Sicurezza lavoro', 'DPI'],
    ],

    'ELETTRICITA_G' => [
        'nome' => 'Documentazione, qualita e CAD/BIM',
        'attivita_alta' => [
            'Disegno schemi CAD',
            'Documentazione as-built',
            'Gestione qualita',
            'Modellazione BIM',
        ],
        'attivita_bassa' => [
            'Solo compilazione moduli base',
            'Supporto documentale',
        ],
        'ruoli_adatti' => ['Disegnatore CAD', 'Addetto documentazione'],
        'competenze_chiave' => ['AutoCAD', 'BIM', 'Documentazione tecnica'],
    ],

    'ELETTRICITA_H' => [
        'nome' => 'Manutenzione e Service',
        'attivita_alta' => [
            'Manutenzione preventiva impianti',
            'Ricerca guasti',
            'Interventi di riparazione',
            'Assistenza clienti tecnica',
        ],
        'attivita_bassa' => [
            'Solo manutenzioni programmate semplici',
            'Affiancamento su guasti',
        ],
        'ruoli_adatti' => ['Manutentore elettrico', 'Tecnico service'],
        'competenze_chiave' => ['Troubleshooting', 'Manutenzione', 'Customer service'],
    ],

    // ========================================
    // AUTOMAZIONE (8 aree: A-H)
    // ========================================

    'AUTOMAZIONE_A' => [
        'nome' => 'Pianificazione e Documentazione',
        'attivita_alta' => [
            'Redazione documentazione tecnica',
            'Pianificazione progetti automazione',
            'Specifiche tecniche',
        ],
        'attivita_bassa' => [
            'Compilazione moduli standard',
            'Supporto documentale',
        ],
        'ruoli_adatti' => ['Tecnico documentazione', 'Project assistant'],
        'competenze_chiave' => ['Documentazione', 'Pianificazione', 'Specifiche tecniche'],
    ],

    'AUTOMAZIONE_B' => [
        'nome' => 'Montaggio meccanico ed Elettromeccanico',
        'attivita_alta' => [
            'Montaggio macchine automatiche',
            'Assemblaggio gruppi meccanici',
            'Installazione attuatori',
        ],
        'attivita_bassa' => [
            'Montaggi semplici con supervisione',
            'Assistenza montatore senior',
        ],
        'ruoli_adatti' => ['Montatore meccanico', 'Assemblatore'],
        'competenze_chiave' => ['Meccanica', 'Pneumatica', 'Assemblaggio'],
    ],

    'AUTOMAZIONE_C' => [
        'nome' => 'Cablaggio elettrico e Quadri',
        'attivita_alta' => [
            'Cablaggio quadri automazione',
            'Connessione sensori/attuatori',
            'Installazione I/O',
        ],
        'attivita_bassa' => [
            'Cablaggi base con supervisione',
            'Supporto cablatore',
        ],
        'ruoli_adatti' => ['Cablatore automazione', 'Tecnico cablaggio'],
        'competenze_chiave' => ['Cablaggio industriale', 'Sensori', 'I/O'],
    ],

    'AUTOMAZIONE_D' => [
        'nome' => 'Automazione e PLC',
        'attivita_alta' => [
            'Programmazione PLC',
            'Configurazione sistemi automazione',
            'Debug programmi',
            'Messa in servizio linee',
        ],
        'attivita_bassa' => [
            'Area specialistica: richiede formazione',
            'Solo modifiche minori con supervisione',
        ],
        'ruoli_adatti' => ['Programmatore PLC junior', 'Tecnico automazione'],
        'competenze_chiave' => ['PLC', 'Ladder', 'HMI'],
    ],

    'AUTOMAZIONE_E' => [
        'nome' => 'Strumentazione e Misure',
        'attivita_alta' => [
            'Taratura strumenti',
            'Installazione sensori',
            'Calibrazione trasduttori',
            'Misure industriali',
        ],
        'attivita_bassa' => [
            'Lettura strumenti base',
            'Supporto strumentista',
        ],
        'ruoli_adatti' => ['Strumentista', 'Tecnico misure'],
        'competenze_chiave' => ['Strumentazione', 'Calibrazione', 'Sensori industriali'],
    ],

    'AUTOMAZIONE_F' => [
        'nome' => 'Reti e Comunicazione industriale',
        'attivita_alta' => [
            'Configurazione reti industriali',
            'Setup comunicazione PLC',
            'Diagnostica reti Profinet/EtherCAT',
        ],
        'attivita_bassa' => [
            'Area specialistica avanzata',
            'Solo verifica connessioni base',
        ],
        'ruoli_adatti' => ['Tecnico reti industriali', 'System integrator junior'],
        'competenze_chiave' => ['Profinet', 'EtherCAT', 'Reti industriali'],
    ],

    'AUTOMAZIONE_G' => [
        'nome' => 'Qualita, Sicurezza e Normative',
        'attivita_alta' => [
            'Applicazione norme sicurezza macchine',
            'Documentazione CE',
            'Gestione qualita produzione',
        ],
        'attivita_bassa' => [
            'Rispetto procedure base',
            'Formazione normative richiesta',
        ],
        'ruoli_adatti' => ['Addetto sicurezza macchine', 'Quality control'],
        'competenze_chiave' => ['Direttiva Macchine', 'Marcatura CE', 'Safety'],
    ],

    'AUTOMAZIONE_H' => [
        'nome' => 'Manutenzione e Service',
        'attivita_alta' => [
            'Manutenzione impianti automatizzati',
            'Ricerca guasti PLC',
            'Interventi su robotica',
            'Assistenza tecnica clienti',
        ],
        'attivita_bassa' => [
            'Manutenzioni programmate base',
            'Affiancamento su guasti complessi',
        ],
        'ruoli_adatti' => ['Manutentore automazione', 'Service engineer junior'],
        'competenze_chiave' => ['Troubleshooting PLC', 'Manutenzione robot', 'Service'],
    ],

    // ========================================
    // METALCOSTRUZIONE (10 aree: A-J)
    // ========================================

    'METALCOSTRUZIONE_A' => [
        'nome' => 'Pianificazione, Disegno e CAD',
        'attivita_alta' => [
            'Disegno strutture metalliche',
            'Modellazione 3D',
            'Preparazione distinte materiali',
            'Preventivazione',
        ],
        'attivita_bassa' => [
            'Lettura disegni base',
            'Supporto ufficio tecnico',
        ],
        'ruoli_adatti' => ['Disegnatore CAD', 'Tecnico ufficio tecnico'],
        'competenze_chiave' => ['AutoCAD', 'SolidWorks', 'Disegno tecnico'],
    ],

    'METALCOSTRUZIONE_B' => [
        'nome' => 'Preparazione e Taglio',
        'attivita_alta' => [
            'Taglio lamiere e profilati',
            'Uso macchine taglio (plasma, laser, ossitaglio)',
            'Preparazione materiale per lavorazioni',
        ],
        'attivita_bassa' => [
            'Solo tagli manuali semplici',
            'Affiancamento su macchine',
        ],
        'ruoli_adatti' => ['Operatore taglio', 'Addetto preparazione'],
        'competenze_chiave' => ['Taglio termico', 'Taglio meccanico', 'Sicurezza'],
    ],

    'METALCOSTRUZIONE_C' => [
        'nome' => 'Lavorazioni e Assemblaggio',
        'attivita_alta' => [
            'Piegatura e calandratura',
            'Foratura e fresatura',
            'Assemblaggio strutture',
            'Saldatura di posizionamento',
        ],
        'attivita_bassa' => [
            'Lavorazioni base con supervisione',
            'Assistenza montatore',
        ],
        'ruoli_adatti' => ['Carpentiere metallico', 'Assemblatore strutture'],
        'competenze_chiave' => ['Carpenteria', 'Lavorazioni lamiera', 'Assemblaggio'],
    ],

    'METALCOSTRUZIONE_D' => [
        'nome' => 'Saldatura',
        'attivita_alta' => [
            'Saldatura MIG/MAG',
            'Saldatura TIG',
            'Saldatura elettrodo',
            'Saldature certificate',
        ],
        'attivita_bassa' => [
            'Solo saldature non strutturali',
            'Formazione patentino richiesta',
        ],
        'ruoli_adatti' => ['Saldatore', 'Saldatore certificato'],
        'competenze_chiave' => ['MIG/MAG', 'TIG', 'Patentini saldatura'],
    ],

    'METALCOSTRUZIONE_E' => [
        'nome' => 'Trattamenti superficiali e Protezione',
        'attivita_alta' => [
            'Sabbiatura',
            'Verniciatura industriale',
            'Zincatura',
            'Trattamenti anticorrosione',
        ],
        'attivita_bassa' => [
            'Preparazione superfici',
            'Supporto verniciatore',
        ],
        'ruoli_adatti' => ['Verniciatore industriale', 'Sabbiatore'],
        'competenze_chiave' => ['Verniciatura', 'Protezione metalli', 'Sicurezza'],
    ],

    'METALCOSTRUZIONE_F' => [
        'nome' => 'Montaggio e Posa in opera',
        'attivita_alta' => [
            'Montaggio strutture in cantiere',
            'Posa in opera costruzioni metalliche',
            'Lavori in quota',
            'Coordinamento squadra',
        ],
        'attivita_bassa' => [
            'Supporto montatore senior',
            'Lavori a terra',
        ],
        'ruoli_adatti' => ['Montatore strutture', 'Carpentiere cantiere'],
        'competenze_chiave' => ['Montaggio cantiere', 'Lavori quota', 'Sicurezza'],
    ],

    'METALCOSTRUZIONE_G' => [
        'nome' => 'Misure, Qualita e Conformita',
        'attivita_alta' => [
            'Controllo dimensionale',
            'Controlli qualita saldature',
            'Documentazione conformita',
            'Prove non distruttive base',
        ],
        'attivita_bassa' => [
            'Controlli visivi base',
            'Compilazione checklist',
        ],
        'ruoli_adatti' => ['Addetto qualita', 'Controllore'],
        'competenze_chiave' => ['Metrologia', 'Controllo qualita', 'NDT base'],
    ],

    'METALCOSTRUZIONE_H' => [
        'nome' => 'Sicurezza, Ambiente e Organizzazione',
        'attivita_alta' => [
            'Gestione sicurezza officina',
            'Organizzazione lavoro',
            'Gestione rifiuti metallici',
        ],
        'attivita_bassa' => [
            'Rispetto procedure sicurezza',
            'Formazione base richiesta',
        ],
        'ruoli_adatti' => ['Preposto sicurezza', 'Caposquadra junior'],
        'competenze_chiave' => ['Sicurezza lavoro', 'Organizzazione', 'Ambiente'],
    ],

    'METALCOSTRUZIONE_I' => [
        'nome' => 'CAD/CAM e BIM',
        'attivita_alta' => [
            'Programmazione macchine CNC',
            'Nesting ottimizzato',
            'Modellazione BIM strutture',
        ],
        'attivita_bassa' => [
            'Uso software base',
            'Supporto programmatore',
        ],
        'ruoli_adatti' => ['Programmatore CAM', 'BIM specialist'],
        'competenze_chiave' => ['CAD/CAM', 'BIM', 'CNC'],
    ],

    'METALCOSTRUZIONE_J' => [
        'nome' => 'Manutenzione e Ripristino',
        'attivita_alta' => [
            'Riparazione strutture metalliche',
            'Manutenzione impianti',
            'Interventi di ripristino',
        ],
        'attivita_bassa' => [
            'Manutenzioni base',
            'Supporto manutentore',
        ],
        'ruoli_adatti' => ['Manutentore', 'Riparatore strutture'],
        'competenze_chiave' => ['Manutenzione', 'Riparazione', 'Saldatura'],
    ],

    // ========================================
    // MECCANICA (13 aree)
    // ========================================

    'MECCANICA_LMB' => [
        'nome' => 'Lavorazioni meccaniche di base',
        'attivita_alta' => [
            'Lavorazioni manuali (limatura, tracciatura)',
            'Uso utensili manuali',
            'Preparazione pezzi',
            'Operazioni base al banco',
        ],
        'attivita_bassa' => [
            'Formazione propedeutica',
            'Supporto operatore',
        ],
        'ruoli_adatti' => ['Operatore meccanico base', 'Aiuto officina'],
        'competenze_chiave' => ['Utensili manuali', 'Metrologia base', 'Disegno tecnico'],
    ],

    'MECCANICA_LMC' => [
        'nome' => 'Lavorazioni su macchine convenzionali',
        'attivita_alta' => [
            'Tornitura convenzionale',
            'Fresatura convenzionale',
            'Foratura e alesatura',
            'Setup macchine tradizionali',
        ],
        'attivita_bassa' => [
            'Solo operazioni semplici',
            'Affiancamento tornitore/fresatore',
        ],
        'ruoli_adatti' => ['Tornitore', 'Fresatore', 'Operatore macchine utensili'],
        'competenze_chiave' => ['Tornio', 'Fresa', 'Utensili taglio'],
    ],

    'MECCANICA_CNC' => [
        'nome' => 'Lavorazioni CNC e tecnologie digitali',
        'attivita_alta' => [
            'Programmazione CNC',
            'Conduzione centri lavoro',
            'Setup e attrezzaggio CNC',
            'Ottimizzazione cicli',
        ],
        'attivita_bassa' => [
            'Solo carico/scarico pezzi',
            'Formazione CNC richiesta',
        ],
        'ruoli_adatti' => ['Operatore CNC', 'Programmatore CNC junior'],
        'competenze_chiave' => ['CNC', 'CAM', 'G-code'],
    ],

    'MECCANICA_ASS' => [
        'nome' => 'Assemblaggio e montaggio meccanico',
        'attivita_alta' => [
            'Assemblaggio gruppi meccanici',
            'Montaggio cuscinetti e trasmissioni',
            'Regolazione giochi e tolleranze',
        ],
        'attivita_bassa' => [
            'Montaggi semplici guidati',
            'Supporto assemblatore',
        ],
        'ruoli_adatti' => ['Montatore meccanico', 'Assemblatore'],
        'competenze_chiave' => ['Assemblaggio', 'Cuscinetti', 'Trasmissioni'],
    ],

    'MECCANICA_MIS' => [
        'nome' => 'Misurazione e controllo qualita',
        'attivita_alta' => [
            'Controllo dimensionale',
            'Uso strumenti di precisione',
            'Interpretazione tolleranze',
            'Controllo statistico',
        ],
        'attivita_bassa' => [
            'Misure base con calibro',
            'Formazione metrologia',
        ],
        'ruoli_adatti' => ['Addetto controllo qualita', 'Metrologo'],
        'competenze_chiave' => ['Metrologia', 'Tolleranze', 'Strumenti misura'],
    ],

    'MECCANICA_GEN' => [
        'nome' => 'Trattamenti e processi speciali',
        'attivita_alta' => [
            'Trattamenti termici',
            'Rivestimenti superficiali',
            'Processi speciali',
        ],
        'attivita_bassa' => [
            'Supporto operatore trattamenti',
            'Formazione specifica richiesta',
        ],
        'ruoli_adatti' => ['Operatore trattamenti', 'Addetto finiture'],
        'competenze_chiave' => ['Trattamenti termici', 'Rivestimenti', 'Materiali'],
    ],

    'MECCANICA_MAN' => [
        'nome' => 'Manutenzione e revisione impianti',
        'attivita_alta' => [
            'Manutenzione macchine utensili',
            'Revisione componenti',
            'Ricerca guasti meccanici',
            'Manutenzione preventiva',
        ],
        'attivita_bassa' => [
            'Manutenzioni ordinarie base',
            'Affiancamento manutentore',
        ],
        'ruoli_adatti' => ['Manutentore meccanico', 'Tecnico revisioni'],
        'competenze_chiave' => ['Manutenzione', 'Troubleshooting', 'Lubrificazione'],
    ],

    'MECCANICA_DT' => [
        'nome' => 'Disegno tecnico e progettazione',
        'attivita_alta' => [
            'Lettura e interpretazione disegni',
            'Disegno CAD 2D/3D',
            'Quotatura e tolleranze',
        ],
        'attivita_bassa' => [
            'Lettura disegni base',
            'Supporto ufficio tecnico',
        ],
        'ruoli_adatti' => ['Disegnatore meccanico', 'Tecnico CAD'],
        'competenze_chiave' => ['CAD', 'GD&T', 'Disegno meccanico'],
    ],

    'MECCANICA_AUT' => [
        'nome' => 'Automazione e meccatronica',
        'attivita_alta' => [
            'Integrazione sistemi meccanici/elettronici',
            'Manutenzione sistemi meccatronici',
            'Setup automazioni',
        ],
        'attivita_bassa' => [
            'Area specialistica',
            'Formazione meccatronica richiesta',
        ],
        'ruoli_adatti' => ['Tecnico meccatronico', 'Integratore sistemi'],
        'competenze_chiave' => ['Meccatronica', 'PLC base', 'Sensori'],
    ],

    'MECCANICA_PIAN' => [
        'nome' => 'Pianificazione e documentazione tecnica',
        'attivita_alta' => [
            'Pianificazione produzioni',
            'Gestione commesse',
            'Documentazione tecnica',
        ],
        'attivita_bassa' => [
            'Supporto pianificazione',
            'Compilazione documenti base',
        ],
        'ruoli_adatti' => ['Pianificatore produzione', 'Addetto commesse'],
        'competenze_chiave' => ['Pianificazione', 'MRP', 'Documentazione'],
    ],

    'MECCANICA_SAQ' => [
        'nome' => 'Sicurezza, ambiente e qualita',
        'attivita_alta' => [
            'Applicazione norme sicurezza',
            'Gestione sistema qualita',
            'Audit interni',
        ],
        'attivita_bassa' => [
            'Rispetto procedure',
            'Formazione sicurezza base',
        ],
        'ruoli_adatti' => ['Addetto sicurezza', 'Addetto qualita'],
        'competenze_chiave' => ['ISO 9001', 'Sicurezza lavoro', 'Ambiente'],
    ],

    'MECCANICA_CSP' => [
        'nome' => 'Collaborazione e sviluppo personale',
        'attivita_alta' => [
            'Lavoro in team',
            'Comunicazione tecnica',
            'Problem solving collaborativo',
            'Formazione colleghi',
        ],
        'attivita_bassa' => [
            'Lavoro individuale preferibile inizialmente',
            'Sviluppo soft skills',
        ],
        'ruoli_adatti' => ['Team leader junior', 'Tutor tecnico'],
        'competenze_chiave' => ['Teamwork', 'Comunicazione', 'Leadership'],
    ],

    'MECCANICA_PRG' => [
        'nome' => 'Progettazione avanzata',
        'attivita_alta' => [
            'Progettazione componenti',
            'Analisi FEM base',
            'Sviluppo prodotto',
        ],
        'attivita_bassa' => [
            'Area specialistica avanzata',
            'Richiede esperienza progettazione',
        ],
        'ruoli_adatti' => ['Progettista junior', 'Tecnico R&D'],
        'competenze_chiave' => ['CAD 3D', 'FEM', 'Sviluppo prodotto'],
    ],

    // ========================================
    // CHIMFARM (11 aree)
    // ========================================

    'CHIMFARM_1C' => [
        'nome' => 'Conformita e GMP',
        'attivita_alta' => [
            'Applicazione norme GMP',
            'Gestione documentazione qualita',
            'Audit e ispezioni',
            'Validazione processi',
        ],
        'attivita_bassa' => [
            'Rispetto procedure base',
            'Formazione GMP richiesta',
        ],
        'ruoli_adatti' => ['Quality Assurance', 'Addetto conformita'],
        'competenze_chiave' => ['GMP', 'Documentazione', 'Validazione'],
    ],

    'CHIMFARM_1G' => [
        'nome' => 'Gestione Materiali',
        'attivita_alta' => [
            'Gestione magazzino materie prime',
            'Campionamento materiali',
            'Tracciabilita lotti',
            'Gestione scadenze',
        ],
        'attivita_bassa' => [
            'Movimentazione base',
            'Supporto magazziniere',
        ],
        'ruoli_adatti' => ['Magazziniere farmaceutico', 'Addetto materiali'],
        'competenze_chiave' => ['Tracciabilita', 'GMP materiali', 'Logistica pharma'],
    ],

    'CHIMFARM_1O' => [
        'nome' => 'Operazioni Base',
        'attivita_alta' => [
            'Operazioni di produzione base',
            'Pesate e dosaggi',
            'Pulizia e sanitizzazione',
            'Compilazione batch record',
        ],
        'attivita_bassa' => [
            'Solo operazioni semplici con supervisione',
            'Formazione GMP richiesta',
        ],
        'ruoli_adatti' => ['Operatore produzione', 'Addetto linea'],
        'competenze_chiave' => ['Pesata', 'GMP base', 'Documentazione'],
    ],

    'CHIMFARM_2M' => [
        'nome' => 'Misurazione',
        'attivita_alta' => [
            'Uso strumenti di misura calibrati',
            'Verifica parametri processo',
            'Registrazione dati',
        ],
        'attivita_bassa' => [
            'Letture base',
            'Supporto tecnico',
        ],
        'ruoli_adatti' => ['Operatore controllo', 'Addetto misure'],
        'competenze_chiave' => ['Strumenti misura', 'Calibrazione', 'Data integrity'],
    ],

    'CHIMFARM_3C' => [
        'nome' => 'Controllo Qualita',
        'attivita_alta' => [
            'Analisi di laboratorio',
            'Controlli in processo',
            'Rilascio lotti',
            'Gestione OOS/deviazioni',
        ],
        'attivita_bassa' => [
            'Solo campionamenti',
            'Supporto analista',
        ],
        'ruoli_adatti' => ['Analista QC', 'Tecnico laboratorio'],
        'competenze_chiave' => ['Analisi chimiche', 'HPLC', 'GMP laboratorio'],
    ],

    'CHIMFARM_4S' => [
        'nome' => 'Sicurezza',
        'attivita_alta' => [
            'Gestione sicurezza laboratorio',
            'Manipolazione sostanze pericolose',
            'Gestione emergenze',
            'DPI specifici',
        ],
        'attivita_bassa' => [
            'Rispetto norme base',
            'Formazione sicurezza chimica richiesta',
        ],
        'ruoli_adatti' => ['Addetto sicurezza lab', 'HSE pharma'],
        'competenze_chiave' => ['Sicurezza chimica', 'DPI', 'Emergenze'],
    ],

    'CHIMFARM_5S' => [
        'nome' => 'Sterilita',
        'attivita_alta' => [
            'Lavoro in camera bianca',
            'Tecniche asettiche',
            'Vestizione sterile',
            'Monitoraggio ambientale',
        ],
        'attivita_bassa' => [
            'Area critica: richiede qualifica',
            'Esclusione senza formazione specifica',
        ],
        'ruoli_adatti' => ['Operatore sterile', 'Tecnico cleanroom'],
        'competenze_chiave' => ['Tecniche asettiche', 'Cleanroom', 'Sterilita'],
    ],

    'CHIMFARM_6P' => [
        'nome' => 'Produzione',
        'attivita_alta' => [
            'Conduzione linee produzione',
            'Gestione batch',
            'Setup equipment',
            'Troubleshooting processo',
        ],
        'attivita_bassa' => [
            'Solo operazioni base',
            'Affiancamento operatore senior',
        ],
        'ruoli_adatti' => ['Operatore produzione senior', 'Capoturno junior'],
        'competenze_chiave' => ['Processi pharma', 'Equipment', 'Batch record'],
    ],

    'CHIMFARM_7S' => [
        'nome' => 'Strumentazione',
        'attivita_alta' => [
            'Uso strumentazione analitica',
            'Manutenzione strumenti',
            'Calibrazione',
        ],
        'attivita_bassa' => [
            'Uso base strumenti',
            'Supporto tecnico',
        ],
        'ruoli_adatti' => ['Tecnico strumentazione', 'Analista strumentale'],
        'competenze_chiave' => ['HPLC', 'GC', 'Spettrofotometria'],
    ],

    'CHIMFARM_8T' => [
        'nome' => 'Tecnologie',
        'attivita_alta' => [
            'Uso sistemi informatici pharma',
            'LIMS',
            'Sistemi MES',
        ],
        'attivita_bassa' => [
            'Uso base PC',
            'Data entry',
        ],
        'ruoli_adatti' => ['Tecnico IT pharma', 'Operatore sistemi'],
        'competenze_chiave' => ['LIMS', 'MES', 'Data integrity'],
    ],

    'CHIMFARM_9A' => [
        'nome' => 'Analisi',
        'attivita_alta' => [
            'Analisi chimiche complete',
            'Sviluppo metodi',
            'Validazione analitica',
        ],
        'attivita_bassa' => [
            'Solo analisi routinarie',
            'Supporto analista senior',
        ],
        'ruoli_adatti' => ['Analista chimico', 'Tecnico R&D'],
        'competenze_chiave' => ['Chimica analitica', 'Validazione', 'Sviluppo metodi'],
    ],

    // ========================================
    // GENERICO - Test Orientamento (7 aree)
    // ========================================

    'GEN_A' => [
        'nome' => 'Meccanica',
        'attivita_alta' => [
            'Predisposizione per lavori meccanici',
            'Interesse per macchine utensili',
            'Attitudine manualita tecnica',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
            'Approfondimento orientamento richiesto',
        ],
        'ruoli_adatti' => ['Orientamento verso settore MECCANICA'],
        'competenze_chiave' => ['Meccanica base', 'Manualita', 'Disegno tecnico'],
    ],

    'GEN_B' => [
        'nome' => 'Metalcostruzione',
        'attivita_alta' => [
            'Predisposizione per lavori su metalli',
            'Interesse per saldatura e carpenteria',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore METALCOSTRUZIONE'],
        'competenze_chiave' => ['Saldatura base', 'Carpenteria', 'Lavoro manuale'],
    ],

    'GEN_C' => [
        'nome' => 'Elettricita',
        'attivita_alta' => [
            'Predisposizione per lavori elettrici',
            'Interesse per impianti e cablaggi',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore ELETTRICITA'],
        'competenze_chiave' => ['Elettrotecnica base', 'Sicurezza elettrica'],
    ],

    'GEN_D' => [
        'nome' => 'Elettronica & Automazione',
        'attivita_alta' => [
            'Predisposizione per elettronica e automazione',
            'Interesse per PLC e robotica',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore AUTOMAZIONE'],
        'competenze_chiave' => ['Elettronica base', 'Logica', 'Informatica'],
    ],

    'GEN_E' => [
        'nome' => 'Logistica',
        'attivita_alta' => [
            'Predisposizione per lavori logistici',
            'Interesse per magazzino e trasporti',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore LOGISTICA'],
        'competenze_chiave' => ['Organizzazione', 'Precisione', 'Informatica base'],
    ],

    'GEN_F' => [
        'nome' => 'Chimico-farmaceutico',
        'attivita_alta' => [
            'Predisposizione per lavori in laboratorio',
            'Interesse per chimica e farmaceutica',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore CHIMFARM'],
        'competenze_chiave' => ['Chimica base', 'Precisione', 'Igiene'],
    ],

    'GEN_G' => [
        'nome' => 'Automobile / Manutenzione',
        'attivita_alta' => [
            'Predisposizione per lavori su veicoli',
            'Interesse per meccanica auto',
        ],
        'attivita_bassa' => [
            'Valutare altri settori',
        ],
        'ruoli_adatti' => ['Orientamento verso settore AUTOMOBILE'],
        'competenze_chiave' => ['Meccanica auto base', 'Diagnostica', 'Elettronica veicoli'],
    ],
];

// ============================================
// TEMPLATE COMMENTI PER TONO
// ============================================

$GAP_COMMENT_TEMPLATES = [

    // ========================================
    // TONO FORMALE (per URC/datori lavoro)
    // ========================================
    'formale' => [
        'allineato' => [
            'intro' => 'Lo studente dimostra una percezione realistica delle proprie competenze nell\'area {AREA_NOME}.',
            'corpo' => 'Il gap tra autovalutazione e valutazione effettiva e contenuto ({GAP}%), indicando una buona consapevolezza delle proprie capacita.',
            'attivita' => 'Le competenze in quest\'area consentono di svolgere attivita quali: {ATTIVITA_LISTA}.',
            'ruoli' => 'Ruoli lavorativi compatibili: {RUOLI_LISTA}.',
            'conclusione' => 'Si ritiene che lo studente possa operare in questo ambito con un adeguato livello di autonomia.',
        ],
        'sopravvalutazione_lieve' => [
            'intro' => 'Lo studente manifesta una leggera tendenza alla sopravvalutazione delle proprie competenze nell\'area {AREA_NOME}.',
            'corpo' => 'Il gap rilevato ({GAP}%) suggerisce una percezione leggermente ottimistica rispetto ai risultati effettivi.',
            'attivita' => 'Per le attivita quali {ATTIVITA_LISTA}, si raccomanda un periodo iniziale di affiancamento.',
            'ruoli' => 'Ruoli suggeriti con supervisione iniziale: {RUOLI_LISTA}.',
            'conclusione' => 'Si consiglia di procedere gradualmente verso l\'autonomia, verificando periodicamente i progressi.',
        ],
        'sopravvalutazione_critica' => [
            'intro' => 'Si rileva una significativa discrepanza tra l\'autovalutazione e le competenze effettive nell\'area {AREA_NOME}.',
            'corpo' => 'Il gap del {GAP}% indica una sopravvalutazione importante che richiede attenzione.',
            'attivita' => 'Si sconsiglia l\'assegnazione autonoma ad attivita quali: {ATTIVITA_BASSA}.',
            'ruoli' => 'Prima di accedere a ruoli come {RUOLI_LISTA}, e necessario un percorso formativo mirato.',
            'conclusione' => 'Si raccomanda un piano di sviluppo specifico con obiettivi misurabili e verifiche periodiche.',
        ],
        'sottovalutazione' => [
            'intro' => 'Lo studente dimostra competenze nell\'area {AREA_NOME} superiori alla propria percezione.',
            'corpo' => 'Il gap negativo ({GAP}%) indica una sottovalutazione delle proprie capacita.',
            'attivita' => 'Le competenze dimostrate consentono gia di svolgere attivita quali: {ATTIVITA_LISTA}.',
            'ruoli' => 'Si ritiene idoneo per ruoli quali: {RUOLI_LISTA}.',
            'conclusione' => 'Si suggerisce di valorizzare questo potenziale, incoraggiando maggiore fiducia nelle proprie capacita.',
        ],
    ],

    // ========================================
    // TONO COLLOQUIALE (per uso interno coach)
    // ========================================
    'colloquiale' => [
        'allineato' => [
            'intro' => 'Ottima consapevolezza! {STUDENTE} sa bene dove si trova nell\'area {AREA_NOME}.',
            'corpo' => 'Gap del {GAP}%: la sua autovalutazione e in linea con i risultati.',
            'attivita' => 'Puo tranquillamente lavorare su: {ATTIVITA_LISTA}.',
            'ruoli' => 'Ruoli ok: {RUOLI_LISTA}.',
            'conclusione' => 'Via libera per autonomia graduale!',
        ],
        'sopravvalutazione_lieve' => [
            'intro' => 'Attenzione: {STUDENTE} si sopravvaluta un po\' nell\'area {AREA_NOME}.',
            'corpo' => 'Gap del {GAP}%: pensa di essere piu avanti di quanto mostrano i quiz.',
            'attivita' => 'Prima di farlo lavorare su {ATTIVITA_LISTA}, meglio affiancarlo.',
            'ruoli' => 'Per ruoli come {RUOLI_LISTA}: iniziare con supervisione.',
            'conclusione' => 'Non e grave, ma teniamolo d\'occhio e diamogli feedback realistici.',
        ],
        'sopravvalutazione_critica' => [
            'intro' => 'Bandiera rossa! {STUDENTE} si sopravvaluta molto nell\'area {AREA_NOME}.',
            'corpo' => 'Gap del {GAP}%: c\'e un problema di percezione importante.',
            'attivita' => 'NO a lavori autonomi su: {ATTIVITA_BASSA}.',
            'ruoli' => 'Per {RUOLI_LISTA}: serve formazione prima di tutto.',
            'conclusione' => 'Serve un colloquio per riallineare le aspettative. Attenzione!',
        ],
        'sottovalutazione' => [
            'intro' => 'Bella sorpresa! {STUDENTE} e piu bravo di quanto pensi nell\'area {AREA_NOME}.',
            'corpo' => 'Gap del {GAP}%: si sottovaluta, i risultati sono migliori dell\'autopercezione.',
            'attivita' => 'Puo gia fare: {ATTIVITA_LISTA}.',
            'ruoli' => 'Pronto per: {RUOLI_LISTA}.',
            'conclusione' => 'Diamogli fiducia e incoraggiamolo! Ha potenziale nascosto.',
        ],
    ],
];

// ============================================
// FUNZIONI DI GENERAZIONE COMMENTI
// ============================================

/**
 * Genera un commento automatico basato sul gap analysis
 *
 * @param string $areaKey Chiave area (es. 'AUTOMOBILE_A')
 * @param float $autovalutazione Percentuale autovalutazione (0-100)
 * @param float $valutazioneQuiz Percentuale valutazione quiz (0-100)
 * @param string $tono 'formale' o 'colloquiale'
 * @param string $nomeStudente Nome studente (per tono colloquiale)
 * @param int $sogliaAllineato Soglia per considerare allineato (default 10)
 * @param int $sogliaMonitorare Soglia per monitorare (default 25)
 * @return array ['tipo' => string, 'commento' => string, 'colore' => string, 'gap' => float]
 */
function generate_gap_comment($areaKey, $autovalutazione, $valutazioneQuiz, $tono = 'formale',
                               $nomeStudente = 'Lo studente', $sogliaAllineato = 10, $sogliaMonitorare = 25) {
    global $GAP_ACTIVITY_MAPPING, $GAP_COMMENT_TEMPLATES;

    // Calcola gap (positivo = sopravvalutazione, negativo = sottovalutazione)
    $gap = $autovalutazione - $valutazioneQuiz;
    $gapAbs = abs($gap);

    // Determina tipo di gap
    if ($gapAbs <= $sogliaAllineato) {
        $tipo = 'allineato';
        $colore = 'success'; // Verde
    } elseif ($gap > 0 && $gapAbs <= $sogliaMonitorare) {
        $tipo = 'sopravvalutazione_lieve';
        $colore = 'warning'; // Arancione
    } elseif ($gap > 0) {
        $tipo = 'sopravvalutazione_critica';
        $colore = 'danger'; // Rosso
    } else {
        $tipo = 'sottovalutazione';
        $colore = 'info'; // Blu
    }

    // Ottieni mapping area
    $areaData = $GAP_ACTIVITY_MAPPING[$areaKey] ?? null;
    if (!$areaData) {
        return [
            'tipo' => $tipo,
            'commento' => "Area $areaKey: gap del " . round($gap, 1) . "%",
            'colore' => $colore,
            'gap' => $gap,
        ];
    }

    // Ottieni template
    $templates = $GAP_COMMENT_TEMPLATES[$tono][$tipo] ?? $GAP_COMMENT_TEMPLATES['formale'][$tipo];

    // Prepara sostituzioni
    $replacements = [
        '{AREA_NOME}' => $areaData['nome'],
        '{GAP}' => round($gapAbs, 1),
        '{STUDENTE}' => $nomeStudente,
        '{ATTIVITA_LISTA}' => implode(', ', array_slice($areaData['attivita_alta'], 0, 3)),
        '{ATTIVITA_BASSA}' => implode(', ', array_slice($areaData['attivita_bassa'], 0, 2)),
        '{RUOLI_LISTA}' => implode(', ', $areaData['ruoli_adatti']),
        '{COMPETENZE_LISTA}' => implode(', ', $areaData['competenze_chiave']),
    ];

    // Costruisci commento
    $commento = '';
    foreach (['intro', 'corpo', 'attivita', 'ruoli', 'conclusione'] as $sezione) {
        if (!empty($templates[$sezione])) {
            $testo = $templates[$sezione];
            foreach ($replacements as $placeholder => $value) {
                $testo = str_replace($placeholder, $value, $testo);
            }
            $commento .= $testo . ' ';
        }
    }

    return [
        'tipo' => $tipo,
        'commento' => trim($commento),
        'colore' => $colore,
        'gap' => $gap,
        'area_nome' => $areaData['nome'],
        'attivita_alta' => $areaData['attivita_alta'],
        'attivita_bassa' => $areaData['attivita_bassa'],
        'ruoli_adatti' => $areaData['ruoli_adatti'],
        'competenze_chiave' => $areaData['competenze_chiave'],
    ];
}

/**
 * Genera commenti per tutte le aree di un settore
 *
 * @param string $settore Codice settore (es. 'AUTOMOBILE')
 * @param array $datiAree Array [areaKey => ['auto' => %, 'quiz' => %], ...]
 * @param string $tono 'formale' o 'colloquiale'
 * @param string $nomeStudente Nome studente
 * @return array Array di commenti per area
 */
function generate_sector_comments($settore, $datiAree, $tono = 'formale', $nomeStudente = 'Lo studente') {
    $commenti = [];

    foreach ($datiAree as $areaKey => $valori) {
        $commenti[$areaKey] = generate_gap_comment(
            $areaKey,
            $valori['auto'] ?? 0,
            $valori['quiz'] ?? 0,
            $tono,
            $nomeStudente
        );
    }

    return $commenti;
}

/**
 * Genera un riepilogo globale per lo studente
 *
 * @param array $commentiAree Array di commenti generati
 * @param string $tono 'formale' o 'colloquiale'
 * @return string Riepilogo globale
 */
function generate_global_summary($commentiAree, $tono = 'formale') {
    $countAllineato = 0;
    $countSopravLieve = 0;
    $countSopravCritico = 0;
    $countSottovalutazione = 0;
    $gapMedio = 0;

    foreach ($commentiAree as $c) {
        $gapMedio += $c['gap'];
        switch ($c['tipo']) {
            case 'allineato': $countAllineato++; break;
            case 'sopravvalutazione_lieve': $countSopravLieve++; break;
            case 'sopravvalutazione_critica': $countSopravCritico++; break;
            case 'sottovalutazione': $countSottovalutazione++; break;
        }
    }

    $totale = count($commentiAree);
    $gapMedio = $totale > 0 ? round($gapMedio / $totale, 1) : 0;

    if ($tono === 'formale') {
        $summary = "Analisi complessiva: su $totale aree valutate, ";
        $summary .= "$countAllineato risultano allineate, ";
        $summary .= "$countSopravLieve con lieve sopravvalutazione, ";
        $summary .= "$countSopravCritico con sopravvalutazione critica, ";
        $summary .= "$countSottovalutazione con sottovalutazione. ";
        $summary .= "Gap medio: {$gapMedio}%.";
    } else {
        $summary = "Riepilogo rapido: $countAllineato OK, ";
        $summary .= "$countSopravLieve da monitorare, ";
        $summary .= "$countSopravCritico critici, ";
        $summary .= "$countSottovalutazione sottovalutati. ";
        $summary .= "Gap medio: {$gapMedio}%.";

        if ($countSopravCritico > 0) {
            $summary .= " ATTENZIONE: ci sono aree critiche!";
        }
    }

    return $summary;
}
