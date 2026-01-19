# FTM PLUGINS - Guida Completa per Claude

**Ultimo aggiornamento:** 19 Gennaio 2026 (ore 15:00)

## Panoramica Progetto

Ecosistema di 11 plugin Moodle per gestione competenze professionali.

Target: Moodle 4.5+ / 5.0 | Licenza: GPL-3.0

Server Test: https://test-urc.hizuvala.myhostpoint.ch

---

## STATO ATTUALE SVILUPPO (19/01/2026)

### COMPLETATI E FUNZIONANTI

#### 1. FTM Scheduler (local_ftm_scheduler)
- Vista Calendario Settimanale e Mensile
- Gestione Gruppi colore (Giallo, Grigio, Rosso, Marrone, Viola)
- Gestione Aule e Atelier
- Generazione automatica attivita

#### 2. Sector Manager (local_competencymanager)
- Sistema Multi-Settore per studenti
- Interfaccia segreteria: `sector_admin.php`
- Rilevamento automatico settori da quiz
- Capability `managesectors` per coach/segreteria

#### 3. Test Suite (local_ftm_testsuite)
- 5 Agenti di test: Security, Database, AJAX, Structure, Language
- 58 test automatizzati totali
- Interfaccia web: `agent_tests.php`

#### 4. Self Assessment (local_selfassessment)
- Popup bloccante per autovalutazione
- Sistema doppia password skip
- Observer per rilevazione settori

#### 5. Setup Universale Quiz (local_competencyxmlimport) - AGGIORNATO 19/01/2026
Sistema completo per import quiz e assegnazione competenze:
- **Import XML/Word** con parsing automatico
- **Estrazione codici competenza** con regex flessibile (supporta caratteri accentati)
- **Assegnazione competenze** a domande nuove E esistenti
- **Aggiornamento livello difficoltà** per competenze già assegnate
- **Debug integrato** per troubleshooting
- **Riepilogo finale** con tabella quiz/domande/livello

### IN SVILUPPO

#### 6. Sistema Import CPURC (local_ftm_cpurc)
Sistema per importare utenti da CSV CPURC e generare report Word finali.

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

## Plugin (11 totali)

### Local (9)
- **competencymanager** - Core gestione competenze + Sector Manager
- **coachmanager** - Coaching formatori + Dashboard
- **competencyreport** - Report studenti
- **competencyxmlimport** - Import XML/Word/Excel + Setup Universale
- **ftm_hub** - Hub centrale
- **ftm_scheduler** - Pianificazione calendario
- **ftm_testsuite** - Testing automatizzato
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
├── competencymanager (core + sector_manager)
│   ├── competencyreport
│   ├── competencyxmlimport (+ setup_universale)
│   ├── selfassessment (+ observer settori)
│   └── coachmanager
├── labeval
├── ftm_scheduler (+ link sector_admin)
├── ftm_testsuite
└── ftm_cpurc (import CSV + report Word)

qbank_competenciesbyquestion <- competencymanager
block_ftm_tools -> ftm_hub
```

---

## RISORSE

- Server Test: https://test-urc.hizuvala.myhostpoint.ch
- Setup Universale: /local/competencyxmlimport/setup_universale.php?courseid=X
- Scheduler: /local/ftm_scheduler/index.php
- Sector Admin: /local/competencymanager/sector_admin.php
- Test Suite: /local/ftm_testsuite/agent_tests.php
- Moodle Docs: https://docs.moodle.org/dev/
