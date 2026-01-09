# FTM Moodle Plugins - Contesto Progetto

## 🎯 Panoramica

Sistema di **9 plugin Moodle** interconnessi per la gestione delle competenze tecniche nei settori MECCANICA, AUTOMOBILE e CHIMFARM.

**Target:** Moodle 4.4+ / 4.5 / 5.0 con PHP 8.1+

---

## 📦 Plugin del Sistema

| # | Plugin | Percorso | Versione | Descrizione |
|---|--------|----------|----------|-------------|
| 1 | qbank_competenciesbyquestion | question/bank/ | v1.2 | Collega domande a competenze |
| 2 | local_competencymanager | local/ | v2.1.1 | **CORE** - Gestione competenze e report |
| 3 | local_coachmanager | local/ | v2.0.0 | Gestione coach e note |
| 4 | local_selfassessment | local/ | v1.1.0 | Autovalutazione studenti |
| 5 | local_competencyreport | local/ | v1.0 | Grafici radar competenze |
| 6 | local_competencyxmlimport | local/ | v1.2 | Import domande XML/Word |
| 7 | local_labeval | local/ | v1.0.0 | Valutazione laboratorio |
| 8 | local_ftm_hub | local/ | v2.0.1 | Hub navigazione centrale |
| 9 | block_ftm_tools | blocks/ | v2.0.1 | Sidebar strumenti |

---

## 🔗 Dipendenze

```
qbank_competenciesbyquestion (nessuna dipendenza) - STABLE
    ↓
local_competencymanager (dipende da qbank >= v1.2) - STABLE
    ↓
local_coachmanager (dipende da competencymanager)
    ↓
local_labeval (dipende da coachmanager)

local_ftm_hub (dipende da competencymanager)
block_ftm_tools (dipende da competencymanager)

local_selfassessment (standalone)
local_competencyreport (standalone)
local_competencyxmlimport (standalone, richiede capabilities)
```

---

## 🗄️ Tabelle Database Principali

### qbank_competenciesbyquestion
```sql
mdl_qbank_competenciesbyquestion (questionid, competencyid, difficultylevel, bloomlevel)
```

### local_competencymanager
```sql
mdl_local_competencymanager_auth (userid, courseid, authorized, authorizedby, timecreated, timemodified)
mdl_local_competencymanager_log (courseid, userid, filename, questionsimported, questionsfailed, competenciesassigned, errors, timecreated)
mdl_local_student_coaching (userid, coachid, courseid, sector, area, date_start, date_end, date_extended, current_week, status, notes, timecreated, timemodified)
```

### local_coachmanager
```sql
mdl_local_coachmanager_notes (id, userid, studentid, courseid, note, timecreated)
mdl_local_coachmanager_compare (id, userid, student1id, student2id, courseid, comparison_data)
mdl_local_coachmanager_jobs (id, name, sector, description)
mdl_local_coachmanager_matches (id, userid, jobid, score, analysis)
```

### local_selfassessment
```sql
mdl_local_selfassessment (id, userid, competencyid, courseid, value, timecreated, timemodified)
```

### local_labeval
```sql
mdl_local_labeval_templates (id, name, sector, criteria, created_by)
mdl_local_labeval_evaluations (id, templateid, studentid, evaluatorid, scores, comments)
mdl_local_labeval_assignments (id, templateid, courseid, duedate)
```

---

## 📝 Convenzioni di Sviluppo

### Naming Tabelle
```
mdl_local_{plugin}_{entità}
mdl_qbank_{plugin}
mdl_block_{plugin}
```

### Naming Funzioni
```php
{plugin}_{azione}_{oggetto}()
// Esempio: competencymanager_get_student_scores()
```

### Parametri GET (prefissi)
```
cm_   → competencymanager
coach_ → coachmanager  
sa_   → selfassessment
lab_  → labeval
qbc_  → qbank_competenciesbyquestion
```

### Namespace PHP
```php
namespace local_{plugin};
namespace local_{plugin}\classes;
namespace qbank_{plugin};
```

---

## ⚠️ Query Moodle 4.4+/4.5

In Moodle 4.x la tabella `quiz_slots` **NON HA** la colonna `questionid`.

**Query CORRETTA per ottenere domande di un quiz:**
```sql
SELECT q.id, q.name, qc.competencyid
FROM {quiz_slots} qs
JOIN {question_references} qr ON qr.itemid = qs.id 
    AND qr.component = 'mod_quiz' 
    AND qr.questionarea = 'slot'
JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
JOIN {question} q ON q.id = qv.questionid
LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = q.id
WHERE qs.quizid = :quizid
```

---

## 🔧 Livelli e Valori Standard

### Settori
- `MECCANICA` - Meccanica industriale
- `AUTOMOBILE` - Autoveicoli  
- `CHIMFARM` - Chimico-farmaceutico

### Livelli Difficoltà
```
1 = Base
2 = Intermedio
3 = Avanzato
```

### Livelli Bloom
```
1 = Ricordare
2 = Comprendere
3 = Applicare
4 = Analizzare
5 = Valutare
6 = Creare
```

---

## 📁 File Principali per Plugin

### local_competencymanager
- `dashboard.php` - Dashboard principale coach
- `student_report.php` - Report singolo studente
- `reports.php` - Report aggregati
- `system_check.php` - Diagnostica sistema
- `classes/manager.php` - Logica core
- `classes/assessment_manager.php` - Gestione valutazioni

### local_coachmanager
- `index.php` - Lista studenti
- `reports_v2.php` - Report avanzati
- `ajax_save_notes.php` - Salvataggio note

### local_selfassessment
- `index.php` - Lista autovalutazioni
- `compile.php` - Compilazione autovalutazione
- `classes/manager.php` - Logica autovalutazione

### local_ftm_hub
- `index.php` - Hub centrale con selettore corso

---

## 🚫 File da NON Modificare

- Cartelle `BACKUP_old/`, `Back_Up_*/`
- File con prefisso `old_`, `Old_`, `OLD_`, `V1_`
- File `.bak`, `.backup`

---

## 🌐 URL Principali (relativi a Moodle root)

```
/local/ftm_hub/index.php              # Hub centrale
/local/competencymanager/dashboard.php?courseid=X  # Dashboard
/local/competencymanager/student_report.php?courseid=X&userid=Y
/local/competencymanager/system_check.php   # Diagnostica
/local/coachmanager/index.php?courseid=X
/local/selfassessment/index.php
/local/labeval/index.php
```

---

## 🔐 Capabilities

### local_competencyxmlimport
```php
local/competencyxmlimport:import              // Import domande XML/Word
local/competencyxmlimport:managediagnostics   // Strumenti diagnostica (solo manager)
local/competencyxmlimport:assigncompetencies  // Assegna competenze a domande
```

### local_competencymanager
```php
local/competencymanager:managecoaching        // Gestione percorsi coaching
```

---

## 🛡️ Sicurezza - Best Practices

### Validazione Input
```php
// MAI usare PARAM_RAW per input utente
$value = required_param('value', PARAM_TEXT);      // Testo generico
$id = required_param('id', PARAM_INT);             // Numeri interi
$name = required_param('name', PARAM_ALPHANUMEXT); // Alfanumerico + _-
```

### AJAX Endpoints
```php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();  // OBBLIGATORIO per AJAX
require_capability('local/plugin:capability', $context);
```

### Output Escaping
```php
echo s($userdata);                    // Escape generico
echo htmlspecialchars($exception->getMessage());  // Messaggi errore
echo (int)$id;                        // Numeri in attributi
```

### File Upload
```php
// Validazione completa
$max_size = 10 * 1024 * 1024;  // 10MB
$allowed_mime = ['text/xml', 'application/xml'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp_name);
$safe_name = clean_filename($filename);
```

---

## 📊 Changelog Recente

### v1.2 - 9 Gennaio 2026
**Security & Quality Release**

- **FIX CRITICO:** SQL injection in `ajax_save_coaching.php` (PARAM_RAW rimosso)
- **FIX CRITICO:** Sostituito `MoodleExcelWorkbook` deprecato con PhpSpreadsheet
- **FIX CRITICO:** Aggiunte capabilities mancanti a `competencyxmlimport`
- **FIX ALTO:** Validazione upload file completa (MIME, size, XML parsing)
- **FIX ALTO:** Plugin qbank aggiornato a MATURITY_STABLE
- **FIX MEDIO:** Sesskey validation in endpoint AJAX
- **FIX MEDIO:** Session timeout 30min in setup wizard
- **FIX MEDIO:** Output escaping migliorato

---

*Ultimo aggiornamento: 9 Gennaio 2026*
