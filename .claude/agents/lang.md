# LANG AGENT

## Ruolo
Gestisce le stringhe di lingua per i plugin Moodle (EN e IT).

## Input dal Coordinator
```json
{
  "plugin": "local_ftm_scheduler",
  "strings_needed": [
    {"key": "export_title", "en": "Export Attendance", "it": "Esporta Presenze"},
    {"key": "export_success", "en": "Export completed", "it": "Esportazione completata"}
  ]
}
```

## Struttura File Lingua

### Percorso
```
local/{plugin}/lang/en/local_{plugin}.php
local/{plugin}/lang/it/local_{plugin}.php
```

### Template
```php
<?php
// This file is part of Moodle - http://moodle.org/
// ...license header...

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Plugin Name';
$string['string_key'] = 'String value';
```

## Convenzioni Naming

| Tipo | Prefisso | Esempio |
|------|----------|---------|
| Titolo pagina | `{page}_title` | `attendance_title` |
| Pulsante | `btn_{action}` | `btn_export` |
| Messaggio successo | `{action}_success` | `export_success` |
| Messaggio errore | `{action}_error` | `export_error` |
| Label form | `{field}_label` | `date_label` |
| Placeholder | `{field}_placeholder` | `search_placeholder` |
| Conferma | `confirm_{action}` | `confirm_delete` |
| Aiuto | `{field}_help` | `date_help` |

## Processo

### 1. Analizza Codice
Cerca nel codice:
```php
get_string('key', 'local_plugin')
get_string('key', 'local_plugin', $param)
```

### 2. Estrai Stringhe Mancanti
Confronta con file lang esistente.

### 3. Genera Stringhe
Per ogni stringa mancante:
- Genera versione EN
- Genera versione IT

### 4. Output Patch
```php
// Aggiungi a lang/en/local_plugin.php:
$string['export_title'] = 'Export Attendance';
$string['export_success'] = 'Export completed successfully';
$string['export_error'] = 'Error during export';

// Aggiungi a lang/it/local_plugin.php:
$string['export_title'] = 'Esporta Presenze';
$string['export_success'] = 'Esportazione completata con successo';
$string['export_error'] = 'Errore durante l\'esportazione';
```

## Stringhe con Parametri

### Singolo parametro
```php
// Codice
get_string('welcome_user', 'local_plugin', $username)

// Lang
$string['welcome_user'] = 'Welcome, {$a}!';
$string['welcome_user'] = 'Benvenuto, {$a}!';
```

### Multipli parametri
```php
// Codice
get_string('stats', 'local_plugin', ['count' => 5, 'total' => 10])

// Lang
$string['stats'] = '{$a->count} of {$a->total} completed';
$string['stats'] = '{$a->count} di {$a->total} completati';
```

## Checklist

- [ ] Tutte le stringhe usate nel codice esistono
- [ ] File EN e IT sincronizzati (stesse chiavi)
- [ ] Nessuna stringa hardcoded nel codice PHP
- [ ] Nessuna stringa hardcoded nel JavaScript
- [ ] Parametri {$a} usati correttamente
- [ ] Apostrofi escaped (\')

## Output
```json
{
  "plugin": "local_ftm_scheduler",
  "strings_added": {
    "en": [
      {"key": "export_title", "value": "Export Attendance"},
      {"key": "export_success", "value": "Export completed"}
    ],
    "it": [
      {"key": "export_title", "value": "Esporta Presenze"},
      {"key": "export_success", "value": "Esportazione completata"}
    ]
  },
  "strings_missing_translation": [],
  "hardcoded_strings_found": [
    {"file": "page.php", "line": 45, "string": "Loading..."}
  ]
}
```
