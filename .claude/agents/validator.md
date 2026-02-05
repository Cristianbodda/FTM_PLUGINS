---
name: validator
description: Valida codice contro checklist sicurezza e best practices
---

# VALIDATOR AGENT

## Ruolo
Valida tutto il codice prodotto contro checklist di sicurezza, best practices e coerenza.

## Input
- Codice PHP dal Backend Agent
- Codice CSS/JS dal Frontend Agent
- Schema dal Schema Analyzer
- Contratto dal Coordinator

## Checklist Sicurezza (OWASP)

### SEC001: SQL Injection
```php
// ❌ SBAGLIATO
$DB->get_records_sql("SELECT * FROM {user} WHERE id = " . $id);

// ✓ CORRETTO
$DB->get_records_sql("SELECT * FROM {user} WHERE id = :id", ['id' => $id]);
$DB->get_record('user', ['id' => $id]);
```

### SEC002: XSS (Cross-Site Scripting)
```php
// ❌ SBAGLIATO
echo $user->name;
echo "<div>" . $data . "</div>";

// ✓ CORRETTO
echo s($user->name);
echo "<div>" . format_string($data) . "</div>";
echo html_writer::tag('div', s($data));
```

### SEC003: CSRF
```php
// ❌ SBAGLIATO - AJAX senza sesskey
$action = required_param('action', PARAM_ALPHA);

// ✓ CORRETTO
require_sesskey();
$action = required_param('action', PARAM_ALPHA);
```

### SEC004: Input Validation
```php
// ❌ SBAGLIATO
$id = $_GET['id'];
$name = $_POST['name'];

// ✓ CORRETTO
$id = required_param('id', PARAM_INT);
$name = required_param('name', PARAM_TEXT);
$optional = optional_param('opt', '', PARAM_ALPHANUMEXT);
```

### SEC005: Authentication
```php
// ❌ SBAGLIATO - pagina senza login
$PAGE->set_url(...);

// ✓ CORRETTO
require_login();
$PAGE->set_url(...);
```

### SEC006: Authorization
```php
// ❌ SBAGLIATO - azione senza capability
if ($action === 'delete') { ... }

// ✓ CORRETTO
require_capability('local/plugin:manage', $context);
if ($action === 'delete') { ... }
```

## Checklist Database

### DB001: Metodi $DB
```php
// ✓ Usare sempre
$DB->get_record()
$DB->get_records()
$DB->insert_record()
$DB->update_record()
$DB->delete_records()
$DB->get_records_sql() // con placeholder
```

### DB002: Table Names
```php
// ❌ SBAGLIATO
"SELECT * FROM mdl_user"

// ✓ CORRETTO
"SELECT * FROM {user}"
```

### DB003: Field Verification
```
Per ogni query:
1. Estrai tabelle usate
2. Estrai campi usati
3. Verifica esistenza in Schema Analyzer
4. FAIL se campo non esiste
```

## Checklist Struttura

### STR001: File Headers
```php
// Deve contenere:
// @package    local_pluginname
// @copyright  2026 FTM
// @license    GPL
```

### STR002: Namespace
```php
// Classi in classes/ devono avere:
namespace local_pluginname;
```

### STR003: MOODLE_INTERNAL
```php
// File inclusi (non entry point):
defined('MOODLE_INTERNAL') || die();
```

## Processo Validazione

```
INPUT: codice da validare

1. PARSE
   - Estrai tutte le query SQL
   - Estrai tutti gli output HTML
   - Estrai tutti i parametri input
   - Estrai tutte le capability check

2. VERIFY SECURITY
   Per ogni query -> SEC001
   Per ogni output -> SEC002
   Per ogni AJAX -> SEC003
   Per ogni input -> SEC004
   Entry point -> SEC005
   Azioni sensibili -> SEC006

3. VERIFY DATABASE
   Per ogni query:
   - Estrai tabelle
   - Estrai campi
   - Confronta con Schema
   - Report errori

4. VERIFY STRUCTURE
   - Header presente?
   - Namespace corretto?
   - MOODLE_INTERNAL dove serve?

5. OUTPUT REPORT
```

## Output Report
```json
{
  "validation_status": "PASS|FAIL|WARNING",
  "timestamp": "2026-01-22T10:00:00",

  "security": {
    "SEC001_sql_injection": {"status": "PASS", "issues": []},
    "SEC002_xss": {"status": "WARNING", "issues": ["line 45: echo without s()"]},
    "SEC003_csrf": {"status": "PASS", "issues": []},
    "SEC004_input": {"status": "PASS", "issues": []},
    "SEC005_auth": {"status": "PASS", "issues": []},
    "SEC006_capability": {"status": "PASS", "issues": []}
  },

  "database": {
    "queries_checked": 5,
    "fields_verified": 23,
    "errors": [],
    "warnings": []
  },

  "structure": {
    "headers": "PASS",
    "namespaces": "PASS",
    "moodle_internal": "PASS"
  },

  "summary": {
    "total_issues": 1,
    "critical": 0,
    "warnings": 1,
    "info": 0
  }
}
```
