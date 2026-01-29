# FTM PLUGINS - Guida Completa per Claude

**Ultimo aggiornamento:** 29 Gennaio 2026

## Panoramica Progetto

Ecosistema di 13 plugin Moodle per gestione competenze professionali.

Target: Moodle 4.5+ / 5.0 | Licenza: GPL-3.0

Server Test: https://test-urc.hizuvala.myhostpoint.ch

---

## STATO ATTUALE SVILUPPO (29/01/2026)

### COMPLETATI E FUNZIONANTI

#### 1. FTM Scheduler (local_ftm_scheduler)
- Vista Calendario Settimanale e Mensile
- Gestione Gruppi colore (Giallo, Grigio, Rosso, Marrone, Viola)
- Gestione Aule e Atelier
- Generazione automatica attivita
- Tabella `local_ftm_coaches` per gestione coach (CB, FM, GM, RB)

#### 2. Sector Manager + Student Report (local_competencymanager) - AGGIORNATO 28/01/2026
- Sistema Multi-Settore per studenti
- Interfaccia segreteria: `sector_admin.php`
- Rilevamento automatico settori da quiz
- Capability `managesectors` per coach/segreteria
- **Gap Comments System (NUOVO 28/01/2026):** Suggerimenti automatici basati su gap analysis
- **Student Report Print v2** con:
  - Radar 360px compatto per far stare grafico + tabella nella stessa pagina
  - Rettangoli colorati pieni per TUTTE le aree DETTAGLIO (A-G)
  - Fix overlap header su pagine successive (padding-top 85px)
  - Nomi quiz/autovalutazione visibili sopra i radar ("Fonte:")
  - Font tabella 7pt compatto, logo FTM, sezioni configurabili
- Tabella `local_student_sectors` per multi-settore (primary, secondary, tertiary)
- Tabella `local_student_coaching` per assegnazione coach-studente (condivisa)

#### 2b. Gap Comments System (NUOVO 28/01/2026)
Sistema automatico di suggerimenti basati su gap analysis:
- **79 aree mappate** con attivita lavorative specifiche
- **Confronto Quiz vs Autovalutazione** per ogni area
- **Suggerimenti contestuali** basati su sovra/sottostima
- **Due toni:** Formale (report) e Colloquiale (spunti colloquio)
- **File:** `gap_comments_mapping.php`

#### 2c. FTM AI Integration (IN STANDBY - local_ftm_ai)
Plugin per integrare Azure OpenAI/Copilot con mascheramento dati sensibili:
- **Anonimizzazione automatica:** Rimuove nome, cognome, AVS, email, telefono prima di inviare
- **Varianti linguistiche:** Evita ripetizioni nei testi generati
- **Analisi predittiva:** Identifica studenti a rischio
- **Fallback deterministico:** Se AI non disponibile, usa template
- **Stato:** STANDBY - Plugin completo, pronto per installazione

#### 3. Test Suite (local_ftm_testsuite)
- 5 Agenti di test: Security, Database, AJAX, Structure, Language
- 58 test automatizzati totali
- Interfaccia web: `agent_tests.php`

#### 4. Self Assessment (local_selfassessment) - AGGIORNATO 29/01/2026
- Popup bloccante per autovalutazione
- Sistema doppia password skip (6807 temporaneo, FTM permanente)
- Observer per rilevazione settori
- **Filtro settore primario:** assegna solo competenze del settore primario studente
- **NUOVO: Hook System Moodle 4.3+** - Migrato da callback deprecato a nuovo sistema hook
- **NUOVO: Bloom Legend Dettagliata** - Legenda collassabile con esempi pratici per ogni livello (1-6)
- **NUOVO: Area Mapping Completo** - Supporto tutti i prefissi (CHIMFARM, ELETTRICITÀ, GEN, MECCANICA_*, OLD_*)
- **NUOVO: Tool Diagnostici** - diagnose_critest.php, catchup_test_users.php, analyze_all_prefixes.php
- **File hook:** `classes/hook_callbacks.php`, `db/hooks.php`
- **Version:** 1.3.1 (2026012903)

#### 5. Setup Universale Quiz (local_competencyxmlimport) - AGGIORNATO 29/01/2026
Sistema completo per import quiz e assegnazione competenze:
- **Import XML/Word** con parsing automatico
- **Estrazione codici competenza** con regex flessibile (supporta caratteri accentati)
- **Assegnazione competenze** a domande nuove E esistenti
- **Aggiornamento livello difficoltà** per competenze già assegnate
- **Debug integrato** per troubleshooting
- **Riepilogo finale** con tabella quiz/domande/livello
- **NUOVO: Quiz Export Tool** - Export domande, risposte e competenze in CSV/Excel
  - Selezione multipla quiz per corso
  - Anteprima HTML con risposte corrette evidenziate
  - Export CSV con colonne: Quiz, #, Domanda, Risposte A-D, Corretta, Competenza, Difficoltà
  - Utile per analisi duplicati e pulizia question bank
  - **File:** `quiz_export.php`, `classes/quiz_exporter.php`, `classes/quiz_excel_exporter.php`

#### 6. Coach Dashboard V2 (local_coachmanager) - AGGIORNATO 29/01/2026
Dashboard avanzata per coach con interfaccia ottimizzata per utenti 50+:
- **NUOVO: Coach Navigation** - Navbar unificata per navigazione coach (`coach_navigation.php`)
- **NUOVO: Coach Student View** - Vista studente dedicata per coach (`coach_student_view.php`)
- **NUOVO: Inviti Autovalutazione** - Sistema invio inviti con AJAX (`ajax_send_invitation.php`)
- **4 Viste Configurabili:** Classica, Compatta, Standard, Dettagliata
- **Zoom Accessibilità:** A- (90%), A (100%), A+ (120%), A++ (140%)
- **Filtri Orizzontali:** Corso, Colore Gruppo, Settimana, Stato
- **Timeline 6 Settimane:** Dettaglio attività per settimana
- **Note Coach:** Visibili a coach E segreteria
- **Export Word:** Genera report studente in formato .docx
- **Preferenze Utente:** Vista e zoom salvati automaticamente
- **File:** `coach_dashboard_v2.php`, `export_word.php`

#### 7. Sistema CPURC (local_ftm_cpurc) - COMPLETATO 24/01/2026
Sistema completo per gestione studenti CPURC con import CSV e report Word:
- **Import CSV:** Importa utenti da file CSV CPURC con mapping automatico campi
- **Dashboard Segreteria:** Lista studenti con filtri (URC, settore, stato report, coach)
- **Student Card:** Scheda studente completa con 4 tab (Anagrafica, Percorso, Assenze, Stage)
- **Coach Assignment:** Assegnazione coach FTM sincronizzata con tutti i plugin
- **Multi-Settore:** Primario (quiz/autovalutazione), Secondario, Terziario (suggerimenti coach)
- **Report Word:** Generazione documento Word finale per ogni studente
- **Export Excel:** Esportazione completa dati in formato Excel
- **Export Word Bulk:** ZIP con tutti i report Word (filtro draft/final)
- **Profession Mapper:** Mapping automatico professione → settore

---

## SETUP UNIVERSALE - FUNZIONALITA' (19/01/2026)

### Flusso Operativo
1. **Step 1:** Seleziona Framework (FTM-01 o FTM_GEN)
2. **Step 2:** Seleziona Settore (rilevato automaticamente dal framework)
3. **Step 3:** Seleziona file XML da importare
4. **Step 4:** Configura quiz (nome, livello difficoltà, categoria)
5. **Step 5:** Esecuzione con log dettagliato

### Caratteristiche Tecniche

| Funzionalità | Descrizione |
|--------------|-------------|
| **Regex competenze** | `SETTORE_[A-Za-z0-9]+_[A-Za-z0-9]+` - supporta codici misti |
| **Alias settori** | AUTOVEICOLO→AUTOMOBILE, MECC→MECCANICA, ELETTRICITA→ELETTRICITÀ |
| **Domande esistenti** | Assegna competenze anche se domanda già presente |
| **Livello difficoltà** | Aggiorna livello se diverso da quello esistente |
| **Debug output** | Mostra primi codici DB e prime 3 domande processate |

### Livelli Difficoltà
- ⭐ Base (1)
- ⭐⭐ Intermedio (2)
- ⭐⭐⭐ Avanzato (3)

### Statistiche Finali
- Quiz Creati
- Domande Importate
- Competenze Assegnate (nuove)
- Livelli Aggiornati (modifiche a esistenti)
- Competenze Disponibili

---

## FRAMEWORK COMPETENZE

### Framework Principale (id=9)
- **Nome:** Passaporto tecnico FTM
- **IDNumber:** FTM-01
- **Settori:** AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITÀ, LOGISTICA, MECCANICA, METALCOSTRUZIONE

### Framework Generico (id=10)
- **Nome:** Test generici – orientamento settoriale
- **IDNumber:** FTM_GEN
- **Settori:** GENERICO

**IMPORTANTE:** GENERICO ha un framework separato! Non viene aggiunto automaticamente al framework principale.

---

## SETTORI E ALIAS

| Settore Standard | Alias Supportati |
|------------------|------------------|
| AUTOMOBILE | AUTOVEICOLO |
| AUTOMAZIONE | AUTOM, AUTOMAZ |
| CHIMFARM | CHIM, CHIMICA, FARMACEUTICA |
| ELETTRICITÀ | ELETTRICITA, ELETTR, ELETT |
| LOGISTICA | LOG |
| MECCANICA | MECC |
| METALCOSTRUZIONE | METAL |

---

## Plugin (13 totali)

### Local (11)
- **competencymanager** - Core gestione competenze + Sector Manager + Gap Comments
- **coachmanager** - Coaching formatori + Dashboard V2
- **competencyreport** - Report studenti
- **competencyxmlimport** - Import XML/Word/Excel + Setup Universale
- **ftm_ai** - **NUOVO** Integrazione Azure OpenAI con anonimizzazione (STANDBY)
- **ftm_hub** - Hub centrale
- **ftm_scheduler** - Pianificazione calendario
- **ftm_testsuite** - Testing automatizzato
- **ftm_cpurc** - Gestione CPURC + Report Word
- **labeval** - Valutazione laboratori
- **selfassessment** - Autovalutazione + rilevazione settori

### Block (1)
- **ftm_tools** - Blocco strumenti

### Question Bank (1)
- **competenciesbyquestion** - Competenze domande

---

## COACH E PERSONALE

| Sigla | Nome Completo | Ruolo |
|-------|---------------|-------|
| CB | Cristian Bodda | Coach |
| FM | Fabio Marinoni | Coach |
| GM | Graziano Margonar | Coach |
| RB | Roberto Bravo | Coach |
| SANDRA | Sandra | Segreteria |
| ALE | Alessandra | Segreteria |

---

## CHECKLIST VALIDAZIONE PRE-CONSEGNA CODICE

**IMPORTANTE:** Prima di fornire qualsiasi codice PHP, Claude DEVE verificare mentalmente questi controlli.

### SICUREZZA (SEC001-SEC012)

```
□ SEC001: SQL Injection - Usare $DB->get_record() con placeholder
□ SEC002: XSS - Output escapato con s(), format_string()
□ SEC003: CSRF - require_sesskey() per POST/AJAX
□ SEC004: Input - required_param/optional_param (mai $_GET/$_POST)
□ SEC005: Auth - require_login() sempre
□ SEC006: Capabilities - require_capability() per azioni sensibili
□ SEC007: File Inclusion - Mai include con input utente
□ SEC008: Path Traversal - Validare percorsi file
□ SEC009: Error Disclosure - Mai esporre errori DB
□ SEC010: Credentials - Mai password hardcoded
□ SEC011: Object Reference - Verificare ownership record
□ SEC012: PARAM_RAW - Evitare, preferire PARAM_TEXT/INT
```

### DATABASE (DB001-DB012)

```
□ DB001: Usare $DB methods (get_record, insert_record, etc.)
□ DB002: Placeholders - Mai concatenare SQL
□ DB003: Table prefix - Usare {tablename}
□ DB004: XMLDB types corretti
□ DB005: Naming - local_pluginname_tablename
□ DB006: Primary key - Sempre 'id' BIGINT
□ DB007: Timestamps - timecreated, timemodified
□ DB008: Foreign keys in XMLDB
□ DB009: Indexes per campi ricerca
□ DB010: upgrade.php per modifiche DB esistente
```

### AJAX ENDPOINTS (AJAX001-AJAX010)

```
□ AJAX001: define('AJAX_SCRIPT', true)
□ AJAX002: require_login()
□ AJAX003: require_sesskey()
□ AJAX004: header Content-Type JSON
□ AJAX005: Response ['success' => bool, 'data' => ..., 'message' => ...]
□ AJAX006: try/catch con error response
□ AJAX007: http_response_code per errori
□ AJAX008: Tutti parametri validati
□ AJAX009: Nessun output prima di JSON
□ AJAX010: die() alla fine
```

### STRUTTURA PLUGIN (STR001-STR014)

```
□ STR001: version.php con component, version, requires
□ STR002: Namespace corretto
□ STR003: PHPDoc headers
□ STR004: defined('MOODLE_INTERNAL') in file include
□ STR005: Lang files EN + IT
□ STR006: get_string() per tutte le stringhe
□ STR007: Capabilities in access.php
□ STR008: $PAGE->set_context e set_url
□ STR009: $OUTPUT->header/footer
□ STR014: Query utenti con TUTTI i campi fullname
```

---

## TEMPLATE: Nuovo File PHP

```php
<?php
/**
 * [Descrizione breve]
 *
 * @package    local_pluginname
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/pluginname:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pluginname/page.php'));
$PAGE->set_title(get_string('pagetitle', 'local_pluginname'));
$PAGE->set_heading(get_string('pageheading', 'local_pluginname'));

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action && confirm_sesskey()) {
    // Elabora azione
}

echo $OUTPUT->header();
// Contenuto pagina
echo $OUTPUT->footer();
```

---

## TEMPLATE: Endpoint AJAX

```php
<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/pluginname:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $result = ['success' => true, 'data' => [], 'message' => ''];
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
```

---

## STILE GRAFICO FTM

Mantenere coerenza con FTM Scheduler:

```css
/* Colori principali */
--primary: #0066cc;
--success: #28a745;
--danger: #dc3545;
--warning: #EAB308;
--secondary: #6c757d;

/* Gruppi colore */
--giallo: #FFFF00;
--grigio: #808080;
--rosso: #FF0000;
--marrone: #996633;
--viola: #7030A0;

/* Font */
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

/* Border radius */
buttons: 6px;
cards: 8px;
modals: 12px;

/* Border color */
--border: #dee2e6;
```

---

## DIPENDENZE TRA PLUGIN

```
ftm_hub (centrale)
├── competencymanager (core + sector_manager + gap_comments)
│   ├── competencyreport
│   ├── competencyxmlimport (+ setup_universale)
│   ├── selfassessment (+ observer settori + filtro primario)
│   ├── coachmanager (+ dashboard V2)
│   └── ftm_ai (integrazione AI con anonimizzazione) [STANDBY]
├── labeval
├── ftm_scheduler (+ local_ftm_coaches)
├── ftm_testsuite
└── ftm_cpurc (gestione completa CPURC)

qbank_competenciesbyquestion <- competencymanager
block_ftm_tools -> ftm_hub

Tabelle Condivise:
├── local_student_coaching (coachmanager ↔ ftm_cpurc)
├── local_student_sectors (competencymanager ↔ ftm_cpurc ↔ selfassessment)
├── local_ftm_coaches (ftm_scheduler → tutti)
└── local_ftm_ai_usage (ftm_ai - logging chiamate API)
```

---

## RISORSE

- Server Test: https://test-urc.hizuvala.myhostpoint.ch
- **CPURC Dashboard:** /local/ftm_cpurc/index.php
- **CPURC Student Card:** /local/ftm_cpurc/student_card.php?id=X
- **CPURC Report:** /local/ftm_cpurc/report.php?id=X
- **CPURC Import CSV:** /local/ftm_cpurc/import.php
- **Coach Dashboard V2:** /local/coachmanager/coach_dashboard_v2.php
- Coach Dashboard (originale): /local/coachmanager/coach_dashboard.php
- **Student Report:** /local/competencymanager/student_report.php?userid=X&courseid=Y
- **Student Report Print:** /local/competencymanager/student_report_print.php?userid=X&courseid=Y
- Setup Universale: /local/competencyxmlimport/setup_universale.php?courseid=X
- Scheduler: /local/ftm_scheduler/index.php
- Sector Admin: /local/competencymanager/sector_admin.php
- Test Suite: /local/ftm_testsuite/agent_tests.php
- Moodle Docs: https://docs.moodle.org/dev/

---

## COACH DASHBOARD V2 - DETTAGLI TECNICI (22/01/2026)

### Viste Disponibili

| Vista | Descrizione | Uso Consigliato |
|-------|-------------|-----------------|
| **Classica** | Vista originale completa | Default, tutti i dettagli |
| **Compatta** | Card più piccole, info essenziali | Molti studenti, panoramica rapida |
| **Standard** | Bilanciata tra info e spazio | Uso quotidiano |
| **Dettagliata** | Massimo dettaglio, timeline espansa | Analisi approfondita |

### Livelli Zoom

| Livello | Scala | Target |
|---------|-------|--------|
| A- | 90% | Schermi piccoli |
| A | 100% | Default |
| A+ | 120% | Leggibilità migliorata |
| A++ | 140% | Utenti con difficoltà visive |

### Preferenze Salvate
- `ftm_coach_view`: Vista selezionata (classica, compatta, standard, dettagliata)
- `ftm_coach_zoom`: Livello zoom (90, 100, 120, 140)

### Note Coach
- Tabella: `local_coachmanager_notes`
- Campi: `id`, `studentid`, `coachid`, `notes`, `timecreated`, `timemodified`
- Visibilità: Coach proprietario + Segreteria (capability `local/coachmanager:viewallnotes`)

### Export Word
- File: `export_word.php`
- Libreria: PHPWord (se disponibile) oppure HTML Word-compatible (fallback)
- Contenuto: Info studente, progressi, timeline, note coach

---

## STUDENT REPORT PRINT - DETTAGLI TECNICI (22/01/2026)

### Sistema Stampa Professionale

Implementato in `local/competencymanager/` per generare report PDF/stampa di alta qualità.

### File Coinvolti
```
local/competencymanager/
├── student_report.php           # Pagina principale + generate_svg_radar()
├── student_report_print.php     # Template stampa completo
└── pix/ftm_logo.png             # Logo FTM (scaricato localmente)
```

### Funzione generate_svg_radar()

```php
function generate_svg_radar(
    $data,                    // Array di ['label' => ..., 'value' => ...]
    $title = '',              // Titolo opzionale
    $size = 300,              // Dimensione grafico (ora 490px)
    $fillColor = 'rgba(...)', // Colore riempimento
    $strokeColor = '#667eea', // Colore bordo
    $labelFontSize = 9,       // Font etichette (ora 9)
    $maxLabelLen = 250        // Max caratteri etichetta
)
```

### Parametri Radar Attuali
| Parametro | Valore | Note |
|-----------|--------|------|
| Size | 490px | +40% rispetto originale 340px |
| horizontalPadding | 180px | Spazio laterale per etichette lunghe |
| SVG Width | size + 360px | 490 + 360 = 850px totale |
| labelFontSize | 9 | Font etichette |
| maxLabelLen | 250 | Nessun troncamento pratico |

### Sezioni Configurabili (Ordine 1-9)
1. `valutazione` - Valutazione Globale
2. `progressi` - Progressi Recenti
3. `autovalutazione` - Radar Autovalutazione
4. `performance` - Radar Performance
5. `dettaglio_aree` - Analisi per Area
6. `raccomandazioni` - Raccomandazioni
7. `piano_azione` - Piano d'Azione
8. `note` - Note Aggiuntive
9. `confronto` - Confronto Auto/Reale

### Tabelle Legenda (Dimensioni +20%)
| Elemento | Valore |
|----------|--------|
| Font tabella | 8.5pt |
| Titolo h6 | 11pt bold |
| Padding celle | 5px 8px |
| Larghezza colonne | 70px/65px |

### CSS Print
```css
@page { size: A4; margin: 15mm; }
body { padding-top: 75px; } /* Spazio header */
.page-break-before { page-break-before: always; }
```

### Branding FTM
- **Logo:** `/local/competencymanager/pix/ftm_logo.png`
- **Font:** Didact Gothic (Google Fonts)
- **Colore accento:** #dd0000 (rosso FTM)
- **Header running:** Logo + nome organizzazione su ogni pagina

---

## SISTEMA CPURC - DETTAGLI TECNICI (24/01/2026)

### Panoramica
Sistema completo per la gestione degli studenti CPURC (Centro Professionale URC) con import da CSV, gestione anagrafica, assegnazione coach/settori e generazione report Word.

### File Principali
```
local/ftm_cpurc/
├── index.php                 # Dashboard segreteria con filtri
├── student_card.php          # Scheda studente (4 tab)
├── report.php                # Compilazione report Word
├── import.php                # Import CSV CPURC
├── export_excel.php          # Export Excel completo
├── export_word.php           # Export singolo Word
├── export_word_bulk.php      # Export ZIP tutti i Word
├── ajax_assign_coach.php     # AJAX assegnazione coach
├── ajax_save_sectors.php     # AJAX salvataggio settori
├── ajax_delete_sector.php    # AJAX eliminazione settore
├── classes/
│   ├── cpurc_manager.php     # Manager principale
│   ├── csv_importer.php      # Parser CSV
│   ├── word_exporter.php     # Generatore Word
│   └── profession_mapper.php # Mapping professione→settore
└── db/
    └── install.xml           # Schema database
```

### Tabelle Database

#### local_ftm_cpurc_students
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | BIGINT | Primary key |
| userid | BIGINT | FK → mdl_user.id |
| personal_number | VARCHAR(50) | Numero personale URC |
| urc_office | VARCHAR(100) | Ufficio URC di riferimento |
| urc_consultant | VARCHAR(200) | Consulente URC |
| date_start | BIGINT | Data inizio percorso |
| date_end_planned | BIGINT | Data fine pianificata |
| date_end_actual | BIGINT | Data fine effettiva |
| sector_detected | VARCHAR(50) | Settore rilevato |
| last_profession | VARCHAR(200) | Ultima professione |
| status | VARCHAR(20) | Stato (active, closed) |
| absence_* | INT | Campi assenze (x, o, a, b, c, d, e, f, g, h, i) |
| stage_* | Vari | Campi stage (company, contact, dates) |

#### local_ftm_cpurc_reports
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | BIGINT | Primary key |
| studentid | BIGINT | FK → local_ftm_cpurc_students.id |
| coachid | BIGINT | FK → mdl_user.id (coach) |
| status | VARCHAR(20) | draft, final, sent |
| narrative_* | TEXT | Campi narrativi (comportamento, competenze, etc.) |
| conclusion_* | Vari | Campi conclusione |

### Dashboard Segreteria (index.php)

#### Filtri Disponibili
| Filtro | Tipo | Descrizione |
|--------|------|-------------|
| search | Text | Ricerca nome/cognome/email |
| urc | Select | Ufficio URC |
| sector | Select | Settore |
| reportstatus | Select | Nessuno/Bozza/Completo |
| coach | Select | Coach assegnato |

#### Colonne Tabella
- Nome studente (link a student_card)
- URC
- Settore (badge colorato)
- Settimana (1-6+)
- Coach (dropdown editabile)
- Stato Report (badge)
- Azioni (Card, Report, Word)

#### Export
- **Excel:** Tutti i dati in formato .xlsx
- **Word ZIP:** Archivio con tutti i report Word

### Student Card (student_card.php)

#### Tab Disponibili
1. **Anagrafica:** Dati personali, contatti, indirizzo, dati amministrativi
2. **Percorso:** Dati URC, percorso FTM, coach assegnato, settori multi-livello
3. **Assenze:** Riepilogo assenze (X, O, A, B, C, D, E, F, G, H, I, TOT)
4. **Stage:** Dati azienda, contatto, date, conclusione

#### Coach Assignment
- Dropdown con coach da `local_ftm_coaches` (scheduler)
- Fallback a ruolo editingteacher se tabella non presente
- Salvataggio in `local_student_coaching` (condivisa)
- Sincronizzato con competencymanager e coachmanager

#### Multi-Settore
- **Primario:** Determina quiz e autovalutazione assegnati
- **Secondario/Terziario:** Suggerimenti per il coach
- Pulsanti X per eliminare settori
- Salvataggio in `local_student_sectors` (condivisa)

### Report Word (report.php)

#### Sezioni Documento
1. Intestazione con logo FTM
2. Dati anagrafici studente
3. Percorso formativo
4. Valutazione comportamentale
5. Competenze acquisite
6. Stage e pratica
7. Raccomandazioni coach
8. Conclusione e firma

#### Campi Narrativi
- `narrative_behavior` - Comportamento
- `narrative_technical` - Competenze tecniche
- `narrative_transversal` - Competenze trasversali
- `narrative_recommendations` - Raccomandazioni
- `narrative_conclusion` - Conclusione

### Integrazione con Altri Plugin

#### selfassessment/observer.php
```php
// Filtra competenze per settore primario
$primarySector = self::get_student_primary_sector($userid);
if (!empty($primarySector)) {
    $competencySector = self::get_competency_sector($competencyid);
    if ($competencySector !== $primarySector) {
        continue; // Skip competenza di altro settore
    }
}
```

#### Tabelle Condivise
| Tabella | Plugin Owner | Usata da |
|---------|--------------|----------|
| local_student_coaching | competencymanager | ftm_cpurc, coachmanager |
| local_student_sectors | competencymanager | ftm_cpurc, selfassessment |
| local_ftm_coaches | ftm_scheduler | ftm_cpurc, coachmanager |

### Capabilities
| Capability | Descrizione |
|------------|-------------|
| local/ftm_cpurc:view | Visualizza dashboard e student card |
| local/ftm_cpurc:edit | Modifica dati, assegna coach/settori |
| local/ftm_cpurc:import | Importa CSV |
| local/ftm_cpurc:generatereport | Genera e esporta report Word |

---

## GAP COMMENTS SYSTEM - DETTAGLI TECNICI (28/01/2026)

### Panoramica
Sistema automatico per generare suggerimenti basati sul confronto tra autovalutazione e quiz performance.

### File Principale
```
local/competencymanager/
└── gap_comments_mapping.php    # 79 aree mappate con attivita lavorative
```

### Funzione Principale
```php
function generate_gap_comment($areaKey, $autovalutazione, $performance, $tone = 'formale') {
    // Calcola gap: autovalutazione - performance
    // gap > 0: Sovrastima (studente si valuta meglio di quanto sia)
    // gap < 0: Sottostima (studente si sottovaluta)
    // gap = 0: Allineamento

    return [
        'tipo' => 'sovrastima|sottostima|allineamento',
        'commento' => '...testo generato...',
        'attivita' => ['attivita1', 'attivita2', ...]
    ];
}
```

### Toni Disponibili
| Tono | Uso | Stile |
|------|-----|-------|
| `formale` | Suggerimenti Rapporto | Terza persona, professionale |
| `colloquiale` | Spunti Colloquio | Diretto al "tu", empatico |

### Integrazione nel Report
- **Sezione "Suggerimenti Rapporto":** Testo formale per documentazione
- **Sezione "Spunti Colloquio":** Punti di discussione per il coach

### Aree Coperte (79 totali)
Tutti i 7 settori FTM con aree A-G e sotto-aree (es. MECCANICA_A_01, AUTOMOBILE_B_03).

---

## FTM AI INTEGRATION - DETTAGLI TECNICI (28/01/2026 - STANDBY)

### Panoramica
Plugin per integrare Azure OpenAI (Copilot) con mascheramento automatico dei dati sensibili.
**Stato:** Completo e pronto per installazione, ma in STANDBY.

### File Struttura
```
local/ftm_ai/
├── version.php                  # Plugin definition
├── settings.php                 # Admin configuration Azure
├── classes/
│   ├── anonymizer.php           # Mascheramento PII
│   ├── azure_openai.php         # Client API Azure
│   └── service.php              # Facade semplificata
├── db/
│   └── install.xml              # Tabella usage logging
├── lang/
│   └── en/local_ftm_ai.php      # Stringhe lingua
└── README.md                    # Documentazione completa
```

### Privacy - Dati MAI inviati ad Azure
- Nome e cognome
- Email
- Numero AVS (756.XXXX.XXXX.XX)
- Telefono
- Indirizzo
- IBAN
- Data di nascita

### Uso nel Codice
```php
$service = new \local_ftm_ai\service();

if ($service->is_available()) {
    $result = $service->generate_student_suggestions(
        $userid,           // ID studente Moodle
        $competencyData,   // Array competenze
        $gapData,          // Array gap analysis
        'formale',         // Tono: 'formale' o 'colloquiale'
        $historyData       // Storico opzionale
    );

    if ($result['success']) {
        echo $result['suggestions']; // Nome studente gia reinserito!
    }
}
```

### Configurazione Admin
| Setting | Descrizione |
|---------|-------------|
| azure_endpoint | URL Azure OpenAI (es. https://myresource.openai.azure.com) |
| azure_api_key | API Key del deployment |
| azure_deployment | Nome deployment (es. gpt-4) |
| daily_limit | Limite richieste giornaliere |
| max_tokens | Max token per richiesta |

### Per Riprendere lo Sviluppo
1. Copia `local/ftm_ai` nel Moodle `/local/`
2. Amministrazione > Notifiche per installare
3. Configura credenziali Azure in admin settings
4. Integra nel `student_report.php` con bottone "Genera con AI"

---

## GUIDA COACH - POWERPOINT (28/01/2026)

### File Generati
- **Screenshots:** `playwright_pm/coach_guide_screenshots.mjs` (Playwright)
- **PowerPoint:** `C:\Users\cristian.bodda\Downloads\create_coach_guide_pptx.py`
- **Output:** `FTM_Guida_Coach_Operativa.pptx` (16 slide)

### Contenuto Guida
1. Accesso Dashboard Coach V2
2. Configurazione Vista e Zoom
3. Filtri e Ricerca Studenti
4. Lettura Card Studente
5. Timeline 6 Settimane
6. Report Competenze
7. Gap Analysis e Suggerimenti
8. Export Word
