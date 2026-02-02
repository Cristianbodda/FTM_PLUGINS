# FTM Agents - Guida all'Uso

**Ultimo aggiornamento:** 30 Gennaio 2026

## Panoramica

Questa cartella contiene 11 agenti specializzati per lo sviluppo FTM Plugins.
Gli agenti sono **opzionali** - usali solo quando il task lo giustifica.

---

## Quando Usare gli Agenti

| Tipo di Task | Approccio Consigliato |
|--------------|----------------------|
| Fix piccolo (1-2 file) | **Diretto** - non serve agente |
| Bug semplice | **Diretto** - non serve agente |
| Cambio configurazione | **Diretto** - non serve agente |
| Feature nuova complessa | **Agenti** - orchestrator + team |
| Bug misterioso | **Agenti** - investigator |
| Audit sicurezza pre-release | **Agenti** - security-scanner |
| Refactoring grande | **Agenti** - refactor + implementer |

**Regola generale:** Se il task richiede meno di 30 minuti, fallo diretto.

---

## Catalogo Agenti

### Core Team (Sviluppo Feature)

| Agente | File | Quando Usarlo |
|--------|------|---------------|
| **ftm-orchestrator** | `ftm-orchestrator.md` | Coordinare task multi-fase con più agenti |
| **ftm-investigator** | `ftm-investigator.md` | Bug complessi, trace data flow, root cause analysis |
| **ftm-implementer** | `ftm-implementer.md` | Implementare codice dopo investigazione |

### Quality & Security

| Agente | File | Quando Usarlo |
|--------|------|---------------|
| **moodle-security-scanner** | `moodle-security-scanner.md` | Audit OWASP pre-release, review sicurezza |
| **moodle-refactor** | `moodle-refactor.md` | Refactoring architetturale, DRY, code smell |
| **validator** | `validator.md` | Validazione checklist sicurezza e best practices |

### Specialisti

| Agente | File | Quando Usarlo |
|--------|------|---------------|
| **backend** | `backend.md` | Sviluppo PHP Moodle (classi, AJAX, query) |
| **frontend** | `frontend.md` | Sviluppo UI (HTML, CSS, JavaScript) |
| **lang** | `lang.md` | Gestione stringhe lingua EN/IT |
| **schema-analyzer** | `schema-analyzer.md` | Analisi struttura DB prima di sviluppo |
| **coordinator** | `coordinator.md` | Coordinare sviluppo parallelo multi-agente |

---

## Pattern di Utilizzo

### Pattern 1: Bug Complesso
```
1. ftm-investigator  → Trova root cause
2. ftm-implementer   → Applica fix
3. validator         → Verifica checklist
```

### Pattern 2: Nuova Feature
```
1. ftm-orchestrator  → Pianifica e coordina
2. schema-analyzer   → Analizza DB se necessario
3. backend + frontend + lang  → Sviluppo parallelo
4. validator         → Validazione finale
5. moodle-security-scanner  → Audit sicurezza
```

### Pattern 3: Refactoring
```
1. moodle-refactor   → Identifica miglioramenti
2. ftm-implementer   → Applica refactoring
3. moodle-security-scanner  → Verifica sicurezza mantenuta
```

### Pattern 4: Pre-Release Audit
```
1. moodle-security-scanner  → Scan tutti i plugin
2. validator         → Checklist completa
```

---

## Checklist Rapide (dai file agenti)

### Sicurezza (SEC001-SEC012)
- SEC001: SQL Injection → Usare placeholder $DB
- SEC002: XSS → Escape con s(), format_string()
- SEC003: CSRF → require_sesskey()
- SEC004: Input → required_param/optional_param
- SEC005: Auth → require_login()
- SEC006: Capabilities → require_capability()

### AJAX (AJAX001-AJAX010)
- AJAX001: define('AJAX_SCRIPT', true)
- AJAX002: require_login()
- AJAX003: require_sesskey()
- AJAX004: header Content-Type JSON
- AJAX005: Response standard {success, data, message}

### Database (DB001-DB010)
- DB001: Usare metodi $DB
- DB002: Placeholder, mai concatenare
- DB003: Table prefix {tablename}

---

## Quando NON Usare Agenti

- Task < 30 minuti
- Fix singola riga
- Cambio configurazione
- Aggiunta stringa lingua
- Update version.php
- Commit e push

**In questi casi:** Lavora diretto, è più veloce.

---

## Note Tecniche

- Gli agenti sono file Markdown, non consumano risorse
- Possono essere invocati tramite Task tool con subagent_type
- Ogni agente ha input/output definiti nel suo file
- Gli agenti possono essere combinati in pipeline

---

## Manutenzione

Aggiorna gli agenti quando:
- Cambiano i pattern Moodle
- Aggiungi nuove checklist
- Modifichi la struttura del progetto
- Trovi nuovi best practices

---

*Creato per FTM Plugins - Moodle 4.5+*
