---
name: security-check
description: Esegue validazione sicurezza sui file PHP modificati secondo checklist CLAUDE.md
user-invocable: true
allowed-tools: Bash, Read, Grep, Glob
---

# Security Check FTM

Esegui una validazione di sicurezza completa sui file PHP modificati.

## Checklist Sicurezza (da CLAUDE.md)

Per ogni file PHP modificato, verifica:

### SQL Injection (SEC001-SEC002)
- Usa `$DB->get_record()`, `$DB->get_records()` con placeholder `?`
- Mai concatenare variabili in query SQL
- Usa `{tablename}` per prefisso tabelle

### XSS (SEC002)
- Output escapato con `s()`, `format_string()`, `clean_text()`
- Mai echo diretto di variabili utente

### CSRF (SEC003)
- `require_sesskey()` per tutte le azioni POST/AJAX
- `sesskey()` incluso nei form

### Input Validation (SEC004)
- Usa `required_param()` / `optional_param()`
- Mai usare `$_GET`, `$_POST`, `$_REQUEST` direttamente
- Evita `PARAM_RAW`, preferisci `PARAM_INT`, `PARAM_TEXT`, `PARAM_ALPHANUMEXT`

### Authentication (SEC005-SEC006)
- `require_login()` all'inizio di ogni pagina
- `require_capability()` per azioni sensibili

## Passi da Eseguire

1. Trova file PHP modificati: `git diff --name-only HEAD~1 | grep "\.php$"`
2. Per ogni file, leggi il contenuto e analizza
3. Cerca pattern problematici:
   - `\$_GET\[` o `\$_POST\[` o `\$_REQUEST\[`
   - `echo \$` senza escape
   - Query SQL con concatenazione di variabili
   - Mancanza di `require_login()` o `require_sesskey()`
4. Genera report con:
   - File analizzati
   - Vulnerabilità trovate (con riga)
   - Suggerimenti di fix
   - Status: PASS / WARNING / FAIL
