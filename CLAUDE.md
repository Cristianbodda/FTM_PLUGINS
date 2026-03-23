# FTM PLUGINS - Guida per Claude

**Ultimo aggiornamento:** 23 Marzo 2026 (v2)

## Panoramica Progetto

Ecosistema di 13 plugin Moodle per gestione competenze professionali.

Target: Moodle 4.5+ / 5.0 | Licenza: GPL-3.0

Server Test: https://moodletest45.hizuvala.myhostpoint.ch

---

## STATO ATTUALE SVILUPPO (23/03/2026)

### COMPLETATI E FUNZIONANTI

#### 1. FTM Scheduler (local_ftm_scheduler) - v2026020601
- Calendario Settimanale/Mensile, Gruppi colore, Aule, Atelier
- Programma Individuale Studente (calendario 6 settimane, test T01-T25)
- Dashboard Segreteria (5 tab, CRUD inline, modali) - Dettagli: `docs/DETAILS_SECRETARY_DASHBOARD.md`
- Gestione Coach (setup_coaches.php, manage_coaches.php)
- Filtri Calendario (Gruppo/KW, Aula, Tipo, Reset)
- Excel Calendar Import (3 aule, colori celle, coach-group inference) - Dettagli: `docs/DETAILS_SCHEDULER_IMPORT.md`

#### 2. Sector Manager + Student Report (local_competencymanager) - 12/02/2026
- Multi-Settore, sector_admin.php, capability managesectors
- Gap Comments System (79 aree, 2 toni) - Dettagli: `docs/DETAILS_GAP_COMMENTS.md`
- Sistema Tab Orizzontale (6 tab, localStorage, auto-submit)
- Grafico Overlay Multi-Fonte (4 fonti radar, normalizzazione Bloom)
- Quiz Diagnostics Panel (ultimi 7gg, link Review)
- Student Report Print v2 (radar 490px, branding FTM) - Dettagli: `docs/DETAILS_STUDENT_REPORT.md`

#### 2c. Coach Evaluation System (10/02/2026)
- Valutazione Bloom (0-6), per Area (A-G), descrizioni competenze
- Inline Rating Editor + Reopen Evaluation
- File: `coach_evaluation.php`, `classes/coach_evaluation_manager.php`
- Tabelle: `local_coach_evaluations`, `local_coach_eval_ratings`, `local_coach_eval_history`

#### 2d. FTM AI Integration (STANDBY - local_ftm_ai)
- Azure OpenAI con anonimizzazione PII - Dettagli: `docs/DETAILS_FTM_AI.md`

#### 3. Test Suite (local_ftm_testsuite) - v1.2.0
- 5 Agenti, 58 test, interfaccia web agent_tests.php
- Demo Coach Generator (3 coach, 21 studenti demo)
- Admin Quiz Tester (selezione studente/quiz/percentuale)
- Fix quiz attempts: layout slot e multichoice shuffle

#### 4. Self Assessment (local_selfassessment) - v1.3.1
- Popup bloccante, doppia password skip (6807/FTM)
- Hook System Moodle 4.3+, Bloom Legend, Area Mapping completo
- **Observer affidabile:** Versioning fallback Moodle 4.x, retroactive assignment safety net in compile.php
- **Toggle admin:** settings.php con checkbox `popup_enabled` (default OFF per setup produzione)
- **Fix notifiche email:** Coach riceve notifica quiz/autovalutazione SOLO per studenti assegnati (non piu tutti i coach)

#### 5. Setup Universale Quiz (local_competencyxmlimport) - v1.5
- Import XML/Word/Excel/CSV, assegnazione competenze, debug integrato
- Quiz Export Tool (CSV/Excel), Excel/CSV Quiz Import (.xlsx/.xlsb/.csv)
- CSV Import: supporta file esportati dal Quiz Export Tool (semicolon, UTF-8 BOM)
- **Sostituisci domande:** Checkbox per cancellare quiz+domande vecchie e reimportare da Excel pulito
- **Fix HTML quiz:** Rimosso wrapping `<p>` e `<br>` da import (tutte le path) + script fix DB esistenti

#### 6. Coach Dashboard V2 (local_coachmanager) - 27/02/2026
- 4 Viste, Zoom accessibilita, Filtri, Timeline, Note Coach, Export Word
- Navigazione dentro corsi (sidebar link)
- Reports V2: link diretto a Student Report con parametri preimpostati
- **Week Planner Modal:** Pianificazione attivita settimanale (atelier, test, lab, esterne) da timeline cliccabile
- **Ricerca studenti:** Filtro per nome/cognome/email sempre visibile
- **Filtri avanzati:** Pannello collassabile con reset, badge conteggio filtri attivi
- **Vista compatta ottimizzata:** Grid layout con overflow control, badge settore read-only
- **Bottone Percorso Studente:** Link diretto a student_program.php (tutte le viste + CPURC student card)
- **Fix tabelle gruppi:** `get_student_group()` usa `local_ftm_group_members`/`local_ftm_groups` (corrette)
- **Ordinamento studenti:** Dropdown sort (recenti/fine 6 sett./alfabetico) in filtri avanzati
- Dettagli: `docs/DETAILS_COACH_DASHBOARD.md`

#### 7. Sistema CPURC (local_ftm_cpurc) - 23/03/2026 (v1.4.1)
- Import CSV, Dashboard Segreteria, Student Card (4 tab), Report Word
- **Report Finale riscritto:** Allineato al documento ufficiale "Rapporto finale PML_V2026.docx"
  - Titolo: "Rapporto finale d'attivita'"
  - Scala competenze: Molto buone/Buone/Sufficienti/Insufficienti/N.V. (non piu 1-5)
  - Item competenze allineati al documento ufficiale (Impegno motivazione, Iniziativa, ecc.)
  - 10 canali ricerca impiego ufficiali
  - Sezione firme (Organizzatore + Partecipante)
  - 8 nuovi campi DB (initial_situation_sector, search_competencies, ecc.)
- **Export Word competenze:** 137 merge field nel template (32 MERGEFIELD + 95 griglie competenze + 10 narrativi)
  - Griglie: P0-P4 (personali), S0-S1 (sociali), M0-M4 (metodologiche), T0 (TIC), R0-R4 (ricerca), VO (complessiva)
  - Ogni cella ha sigla `«XX_YY»` (es. `«P0_MB»`), sostituita con "X" se selezionata
  - Tag narrativi: SITUAZIONE_INIZIALE, VALUTAZIONE_SETTORE, POSSIBILI_SETTORI, SINTESI_CONCLUSIVA, OSS_*
- **SIP consent + Allegati:** Campi sip_consent (Si/No) e allegati nel report finale
- **Bottone Percorso:** Link a student_program.php nell'header della Student Card
- **Import Produzione:** `import_production.php` - Upload Excel, dedup, anteprima, assegnazione gruppo/coach/settore/corso
- **Filtri avanzati dashboard:** Data inizio da/a, Gruppo colore (giallo/grigio/rosso/marrone/viola)
- **Fix user creation:** `user_create_user()` API + `enrol_get_plugin('manual')` per enrollment corretto
- **Username:** cognome3+nome3 (prime 3 lettere primo cognome + prime 3 lettere primo nome)
- **Export Credenziali LADI:** Excel con formato LADI template, filtri dashboard, modalita session/db
- **Import dedup:** Per email, tiene row con date_start piu recente
- **Coach auto-assign:** Import CSV assegna coach tramite mapping formatore->coach
- **Settore GENERICO:** Aggiunto a student_card percorso (dropdown + CSS badge)
- **Fix coach dropdown:** Deduplicazione per userid, siteadmin in manage_coaches
- **Bottone Gestione Coach:** Link diretto a manage_coaches.php nella dashboard CPURC
- Dettagli: `docs/DETAILS_CPURC.md`

---

## FRAMEWORK COMPETENZE

### Framework Principale (id=9)
- **IDNumber:** FTM-01
- **Settori:** AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, MECCANICA, METALCOSTRUZIONE

### Framework Generico (id=10)
- **IDNumber:** FTM_GEN
- **Settori:** GENERICO (framework separato!)

---

## SETTORI E ALIAS

| Settore | Alias |
|---------|-------|
| AUTOMOBILE | AUTOVEICOLO |
| AUTOMAZIONE | AUTOM, AUTOMAZ |
| CHIMFARM | CHIM, CHIMICA, FARMACEUTICA |
| ELETTRICITA | ELETTRICITA, ELETTR, ELETT |
| LOGISTICA | LOG |
| MECCANICA | MECC |
| METALCOSTRUZIONE | METAL |

---

#### 8. SIP - Sostegno Individuale Personalizzato (local_ftm_sip) - 23/03/2026 (v1.2.0) NUOVO
- Percorso 10 settimane post-rilevamento per PCI con potenziale di collocamento
- **Griglia Valutazione PCI:** 6 criteri numerici 1-5 (Motivazione, Chiarezza Obiettivo, Occupabilita, Autonomia, Bisogno Coaching, Comportamento)
- **Piano d'Azione:** 7 aree attivazione (scala 0-6), baseline congelata, radar SVG overlay
- **Diario Coaching:** Timeline incontri, azioni assegnate con scadenze e stati
- **Calendario Appuntamenti:** CRUD con notifiche automatiche
- **KPI:** Candidature inviate, contatti aziende, opportunita generate
- **Chiusura:** 4 prerequisiti obbligatori, 8 classificazioni esito, blocco senza dati
- **Dashboard Aggregata:** Statistiche per direzione/URC (tassi inserimento, evoluzione livelli)
- **Report Word:** 9 sezioni esportabili
- **Notifiche:** 7 tipi (appuntamenti, azioni, inattivita, frequenza incontri)
- **Registro Aziende:** Condiviso, crescita organica, autocomplete
- **Area Studente:** sip_my.php con inserimento KPI autonomo
- **12 tabelle DB, 31 file, ~11.000 righe, 500+ stringhe EN/IT**
- Integrato nella Coach Dashboard V2 (badge teal, filtri, modal attivazione)
- Dettagli: `docs/MANUALE_SIP.md`, `docs/REPORT_ISTITUZIONALE_SIP.md`

---

#### 9. Student Report PDF Fix - 20/03/2026
- **Fix punti interrogativi:** Rimossi emoji Unicode (TCPDF non li supporta) tramite `pdf_strip_emoji()`
- **Fix tabelle strette:** Width da px fissi a percentuali (dual legend, gap analysis, overlay)

---

## Plugin (14 totali)

### Local (12)
competencymanager, coachmanager, competencyreport, competencyxmlimport, ftm_ai (STANDBY), ftm_hub, ftm_scheduler, ftm_testsuite, ftm_cpurc, ftm_sip (NUOVO), labeval, selfassessment

### Block (1): ftm_tools | Question Bank (1): competenciesbyquestion

---

## COACH E PERSONALE

| Sigla | Nome | Ruolo |
|-------|------|-------|
| CB | Cristian Bodda | Coach |
| FM | Fabio Marinoni | Coach |
| GM | Graziano Margonar | Coach |
| RB | Roberto Bravo | Coach |
| LP | LP | Coach |
| SANDRA | Sandra | Segreteria |
| ALE | Alessandra | Segreteria |

---

## CHECKLIST VALIDAZIONE PRE-CONSEGNA CODICE

**IMPORTANTE:** Prima di fornire qualsiasi codice PHP, Claude DEVE verificare questi controlli.

### SICUREZZA (SEC001-SEC012)
```
SEC001: SQL Injection - $DB->get_record() con placeholder
SEC002: XSS - Output escapato con s(), format_string()
SEC003: CSRF - require_sesskey() per POST/AJAX
SEC004: Input - required_param/optional_param (mai $_GET/$_POST)
SEC005: Auth - require_login() sempre
SEC006: Capabilities - require_capability() per azioni sensibili
SEC007: File Inclusion - Mai include con input utente
SEC008: Path Traversal - Validare percorsi file
SEC009: Error Disclosure - Mai esporre errori DB
SEC010: Credentials - Mai password hardcoded
SEC011: Object Reference - Verificare ownership record
SEC012: PARAM_RAW - Evitare, preferire PARAM_TEXT/INT
```

### DATABASE (DB001-DB010)
```
DB001: Usare $DB methods (get_record, insert_record, etc.)
DB002: Placeholders - Mai concatenare SQL
DB003: Table prefix - {tablename}
DB004: XMLDB types corretti
DB005: Naming - local_pluginname_tablename
DB006: Primary key - Sempre 'id' BIGINT
DB007: Timestamps - timecreated, timemodified
DB008: Foreign keys in XMLDB
DB009: Indexes per campi ricerca
DB010: upgrade.php per modifiche DB esistente
```

### AJAX ENDPOINTS (AJAX001-AJAX010)
```
AJAX001: define('AJAX_SCRIPT', true)
AJAX002: require_login()
AJAX003: require_sesskey()
AJAX004: header Content-Type JSON
AJAX005: Response ['success' => bool, 'data' => ..., 'message' => ...]
AJAX006: try/catch con error response
AJAX007: http_response_code per errori
AJAX008: Tutti parametri validati
AJAX009: Nessun output prima di JSON
AJAX010: die() alla fine
```

### STRUTTURA PLUGIN (STR001-STR014)
```
STR001: version.php con component, version, requires
STR002: Namespace corretto
STR003: PHPDoc headers
STR004: defined('MOODLE_INTERNAL') in file include
STR005: Lang files EN + IT
STR006: get_string() per tutte le stringhe
STR007: Capabilities in access.php
STR008: $PAGE->set_context e set_url
STR009: $OUTPUT->header/footer
STR014: Query utenti con TUTTI i campi fullname
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

```css
/* Colori principali */
--primary: #0066cc;
--success: #28a745;
--danger: #dc3545;
--warning: #EAB308;
--secondary: #6c757d;

/* Gruppi colore */
--giallo: #FFFF00; --grigio: #808080; --rosso: #FF0000;
--marrone: #996633; --viola: #7030A0;

/* Font e border */
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
buttons: 6px; cards: 8px; modals: 12px;
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
│   ├── coachmanager (+ dashboard V2 + badge SIP)
│   └── ftm_ai [STANDBY]
├── labeval
├── ftm_scheduler (+ local_ftm_coaches)
├── ftm_testsuite
├── ftm_cpurc (+ report finale ufficiale)
└── ftm_sip (NUOVO - 12 tabelle, 31 file)
    ├── Integrato in coachmanager (badge, filtri, modal)
    ├── Legge da: competencymanager, selfassessment, ftm_scheduler
    └── 7 tipi notifica + cron task

Tabelle Condivise:
├── local_student_coaching (coachmanager <-> ftm_cpurc <-> ftm_sip)
├── local_student_sectors (competencymanager <-> ftm_cpurc <-> selfassessment <-> ftm_sip)
├── local_ftm_coaches (ftm_scheduler -> tutti)
└── local_ftm_ai_usage (ftm_ai)
```

---

## RISORSE

- Server Test: https://moodletest45.hizuvala.myhostpoint.ch
- Dashboard Segreteria: /local/ftm_scheduler/secretary_dashboard.php
- CPURC Dashboard: /local/ftm_cpurc/index.php
- Coach Dashboard V2: /local/coachmanager/coach_dashboard_v2.php
- Student Report: /local/competencymanager/student_report.php?userid=X&courseid=Y
- Setup Universale: /local/competencyxmlimport/setup_universale.php?courseid=X
- Scheduler: /local/ftm_scheduler/index.php
- Sector Admin: /local/competencymanager/sector_admin.php
- Test Suite: /local/ftm_testsuite/agent_tests.php
- SIP Dashboard: /local/ftm_sip/sip_dashboard.php
- SIP Studente: /local/ftm_sip/sip_student.php?userid=X
- SIP Statistiche: /local/ftm_sip/sip_stats.php
- SIP Area Studente: /local/ftm_sip/sip_my.php
- Registro Aziende: /local/ftm_sip/companies.php

---

## DETTAGLI TECNICI (file separati)

Per dettagli implementativi specifici, consultare:
- `docs/DETAILS_SECRETARY_DASHBOARD.md` - Dashboard Segreteria (tab, AJAX, JS functions)
- `docs/DETAILS_SCHEDULER_IMPORT.md` - Excel Calendar Import (colonne, colori, parser)
- `docs/DETAILS_STUDENT_REPORT.md` - Student Report Print (radar SVG, CSS print, branding)
- `docs/DETAILS_COACH_DASHBOARD.md` - Coach Dashboard V2 (viste, zoom, note, export)
- `docs/DETAILS_CPURC.md` - Sistema CPURC (tabelle DB, student card, report Word)
- `docs/DETAILS_GAP_COMMENTS.md` - Gap Comments (funzione, toni, 79 aree)
- `docs/DETAILS_FTM_AI.md` - FTM AI Integration (Azure, anonimizzazione, API)
- `docs/MANUALE_SIP.md` - Manuale utente SIP per coach (16 sezioni)
- `docs/REPORT_ISTITUZIONALE_SIP.md` - Report tecnico-istituzionale per UMA (16 sezioni)
