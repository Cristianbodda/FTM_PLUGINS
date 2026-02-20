# FTM PLUGINS - Guida per Claude

**Ultimo aggiornamento:** 19 Febbraio 2026

## Panoramica Progetto

Ecosistema di 13 plugin Moodle per gestione competenze professionali.

Target: Moodle 4.5+ / 5.0 | Licenza: GPL-3.0

Server Test: https://test-urc.hizuvala.myhostpoint.ch

---

## STATO ATTUALE SVILUPPO (19/02/2026)

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

#### 5. Setup Universale Quiz (local_competencyxmlimport) - v1.5
- Import XML/Word/Excel/CSV, assegnazione competenze, debug integrato
- Quiz Export Tool (CSV/Excel), Excel/CSV Quiz Import (.xlsx/.xlsb/.csv)
- CSV Import: supporta file esportati dal Quiz Export Tool (semicolon, UTF-8 BOM)

#### 6. Coach Dashboard V2 (local_coachmanager) - 19/02/2026
- 4 Viste, Zoom accessibilita, Filtri, Timeline, Note Coach, Export Word
- Navigazione dentro corsi (sidebar link)
- Reports V2: link diretto a Student Report con parametri preimpostati
- Dettagli: `docs/DETAILS_COACH_DASHBOARD.md`

#### 7. Sistema CPURC (local_ftm_cpurc) - 24/01/2026
- Import CSV, Dashboard Segreteria, Student Card (4 tab), Report Word
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

## Plugin (13 totali)

### Local (11)
competencymanager, coachmanager, competencyreport, competencyxmlimport, ftm_ai (STANDBY), ftm_hub, ftm_scheduler, ftm_testsuite, ftm_cpurc, labeval, selfassessment

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
│   ├── coachmanager (+ dashboard V2)
│   └── ftm_ai [STANDBY]
├── labeval
├── ftm_scheduler (+ local_ftm_coaches)
├── ftm_testsuite
└── ftm_cpurc

Tabelle Condivise:
├── local_student_coaching (coachmanager <-> ftm_cpurc)
├── local_student_sectors (competencymanager <-> ftm_cpurc <-> selfassessment)
├── local_ftm_coaches (ftm_scheduler -> tutti)
└── local_ftm_ai_usage (ftm_ai)
```

---

## RISORSE

- Server Test: https://test-urc.hizuvala.myhostpoint.ch
- Dashboard Segreteria: /local/ftm_scheduler/secretary_dashboard.php
- CPURC Dashboard: /local/ftm_cpurc/index.php
- Coach Dashboard V2: /local/coachmanager/coach_dashboard_v2.php
- Student Report: /local/competencymanager/student_report.php?userid=X&courseid=Y
- Setup Universale: /local/competencyxmlimport/setup_universale.php?courseid=X
- Scheduler: /local/ftm_scheduler/index.php
- Sector Admin: /local/competencymanager/sector_admin.php
- Test Suite: /local/ftm_testsuite/agent_tests.php

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
