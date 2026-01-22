# FTM DEVELOPMENT WORKFLOW

## Sistema Agenti Paralleli

Questo progetto usa un sistema di agenti coordinati per lo sviluppo.

## Architettura

```
┌─────────────────────────────────────────────────────────────────────┐
│                    MASTER ORCHESTRATOR (Claude)                      │
│                    Riceve task → Attiva workflow                     │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      COORDINATOR AGENT                               │
│  1. Analizza task                                                    │
│  2. Chiama Schema Analyzer                                           │
│  3. Crea contratto per gli agenti                                    │
│  4. Lancia agenti in parallelo                                       │
│  5. Verifica coerenza output                                         │
│  6. Risolve conflitti                                                │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
        ┌────────────┬────────────┼────────────┬────────────┐
        ▼            ▼            ▼            ▼            ▼
   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
   │ SCHEMA  │ │ BACKEND │ │FRONTEND │ │VALIDATOR│ │  LANG   │
   │ANALYZER │ │  AGENT  │ │  AGENT  │ │  AGENT  │ │  AGENT  │
   └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘
```

## Flusso Standard per Nuovo Sviluppo

### FASE 1: ANALISI (Sequenziale)
```
1. Coordinator riceve task
2. Coordinator lancia Schema Analyzer
3. Schema Analyzer restituisce mappa DB
4. Coordinator crea CONTRATTO
```

### FASE 2: SVILUPPO (Parallelo)
```
In parallelo:
├── Backend Agent  → PHP, classi, AJAX
├── Frontend Agent → HTML, CSS, JS
└── Lang Agent     → Stringhe EN/IT
```

### FASE 3: VALIDAZIONE (Sequenziale)
```
1. Validator Agent verifica tutto il codice
2. Coordinator verifica coerenza inter-agente
3. Report finale
```

### FASE 4: INTEGRAZIONE
```
1. Coordinator assembla i file
2. Risolve conflitti minori
3. Output finale al Master
```

## Contratto Standard

Ogni sviluppo usa un contratto JSON:

```json
{
  "task_id": "TASK_XXX",
  "task_name": "Descrizione task",

  "schema": {
    "tables": ["table1", "table2"],
    "fields_map": {}
  },

  "backend": {
    "files": [],
    "functions": [],
    "ajax_actions": []
  },

  "frontend": {
    "css_prefix": "prefix-",
    "js_namespace": "Namespace",
    "html_ids": []
  },

  "lang": {
    "component": "local_plugin",
    "strings": []
  }
}
```

## Comandi Workflow

### Avvia Nuovo Sviluppo
```
/dev [descrizione task]
```

### Solo Analisi Schema
```
/schema [plugin] [tabelle]
```

### Solo Validazione
```
/validate [file o cartella]
```

### Genera Stringhe Lingua
```
/lang [plugin] [stringhe]
```

## Prevenzione Errori

### Errori DB Prevenuti
| Errore | Come Prevenuto |
|--------|----------------|
| Nome campo sbagliato | Schema Analyzer verifica PRIMA |
| JOIN errato | Template query pre-verificati |
| Tabella inesistente | Mappa completa da install.xml |

### Errori Integrazione Prevenuti
| Errore | Come Prevenuto |
|--------|----------------|
| Funzione JS chiama PHP inesistente | Contratto definisce entrambi |
| CSS sovrascrive altri | Prefisso univoco obbligatorio |
| ID HTML duplicati | Prefisso da contratto |

### Errori Sicurezza Prevenuti
| Errore | Come Prevenuto |
|--------|----------------|
| SQL Injection | Validator controlla tutte le query |
| XSS | Validator controlla tutti gli output |
| CSRF | Validator verifica sesskey |

## File Agenti

- `.claude/agents/coordinator.md` - Coordinatore
- `.claude/agents/schema_analyzer.md` - Analisi DB
- `.claude/agents/backend.md` - Sviluppo PHP
- `.claude/agents/frontend.md` - Sviluppo UI
- `.claude/agents/validator.md` - Validazione
- `.claude/agents/lang.md` - Stringhe lingua

## Esempio Completo

### Task: "Crea export presenze Excel"

**FASE 1: Schema Analyzer**
```json
{
  "tables": {
    "local_ftm_activities": {
      "fields": ["id", "name", "date_start", "date_end", "roomid"]
    },
    "local_ftm_enrollments": {
      "fields": ["id", "activityid", "userid", "status", "marked_at"]
    }
  }
}
```

**FASE 2: Contratto**
```json
{
  "backend": {
    "files": ["classes/excel_exporter.php", "ajax_export.php"],
    "functions": ["export_attendance", "get_data_for_export"],
    "ajax_actions": ["export_excel", "preview"]
  },
  "frontend": {
    "css_prefix": "export-",
    "js_namespace": "AttendanceExport",
    "html_ids": ["export-btn", "export-modal"]
  }
}
```

**FASE 3: Sviluppo Parallelo**
- Backend Agent crea PHP
- Frontend Agent crea UI
- Lang Agent crea stringhe

**FASE 4: Validazione**
- Tutti i campi DB verificati ✓
- Security checklist passed ✓
- Coerenza inter-agente ✓

**FASE 5: Output**
Files pronti per upload.
