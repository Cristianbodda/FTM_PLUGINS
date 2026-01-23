---
name: moodle-review
description: Review codice Moodle per standard, best practices e checklist pre-consegna
user-invocable: true
allowed-tools: Read, Grep, Glob
---

# Moodle Code Review

Esegue una review completa del codice secondo gli standard Moodle e le checklist FTM.

## Checklist Struttura Plugin (STR001-STR014)

### File Obbligatori
- [ ] `version.php` con component, version, requires
- [ ] `lang/en/*.php` stringhe inglese
- [ ] `lang/it/*.php` stringhe italiano
- [ ] `db/access.php` per capabilities
- [ ] `db/install.xml` per tabelle DB

### Standard Codice
- [ ] Namespace corretto: `namespace local_pluginname;`
- [ ] PHPDoc headers con @package, @copyright, @license
- [ ] `defined('MOODLE_INTERNAL') || die();` in file inclusi
- [ ] `$PAGE->set_context()` e `$PAGE->set_url()` in pagine
- [ ] `$OUTPUT->header()` / `$OUTPUT->footer()` per output

### Stringhe
- [ ] Usa `get_string('key', 'local_pluginname')` per testi
- [ ] Mai stringhe hardcoded nell'interfaccia
- [ ] Chiavi stringhe in snake_case

### Query Utenti
- [ ] Includi TUTTI i campi per fullname: firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename

## Checklist Database (DB001-DB010)

- [ ] Usa metodi $DB (get_record, insert_record, update_record, delete_records)
- [ ] Placeholder `?` per parametri, mai concatenazione
- [ ] Prefisso tabelle con `{tablename}`
- [ ] Primary key sempre `id` BIGINT AUTOINCREMENT
- [ ] Campi timestamp: `timecreated`, `timemodified`
- [ ] Foreign keys definite in XMLDB
- [ ] Indici su campi di ricerca frequente

## Checklist AJAX (AJAX001-AJAX010)

```php
<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();
header('Content-Type: application/json; charset=utf-8');

try {
    // logica
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
die();
```

## Come Eseguire Review

1. Identifica i file da revieware (modificati o nuovi)
2. Per ogni file, verifica le checklist appropriate
3. Genera report con:
   - File reviewato
   - Issues trovate (con riga)
   - Severity: CRITICAL / WARNING / INFO
   - Suggerimento fix

## Output Atteso

```
## Code Review Report

### file.php
- [CRITICAL] Riga 45: SQL injection - usa placeholder invece di concatenazione
- [WARNING] Riga 12: Manca require_capability()
- [INFO] Riga 78: Considera aggiungere commento per logica complessa

### Riepilogo
- Critical: 1
- Warning: 1
- Info: 1
- Status: NEEDS FIX
```
