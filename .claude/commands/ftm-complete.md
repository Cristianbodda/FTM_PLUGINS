# FTM Complete Command

## Overview
Marca una feature come completata e aggiorna il database.

## Workflow

### Step 1: Identifica Feature

Se ID specificato:
```
/ftm-complete F007
```

Se nessun ID, usa feature corrente in_progress:
```
/ftm-complete
```

### Step 2: Verifica Completamento

Prima di marcare completata, verifica:

```
╔═══════════════════════════════════════════════════════════════╗
║                    COMPLETION CHECKLIST                        ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                 ║
║  Feature: F007 - Complete Word Report Template                  ║
║  Plugin: ftm_cpurc                                              ║
║                                                                 ║
║  Checklist:                                                     ║
║  [ ] Codice implementato                                        ║
║  [ ] Nessun errore PHP syntax                                   ║
║  [ ] Security check passato                                     ║
║  [ ] Testato manualmente                                        ║
║  [ ] Commit effettuato                                          ║
║                                                                 ║
║  Confermi completamento? (si/no)                                ║
║                                                                 ║
╚═══════════════════════════════════════════════════════════════╝
```

### Step 3: Aggiorna Database

```json
{
  "id": "F007",
  "status": "completed",
  "completed": "2026-01-22T15:30:00",
  "completed_by": "session_abc123",
  "notes": "Implementato word_generator.php con template sostituzione"
}
```

### Step 4: Aggiorna Progress

In `.claude/ftm_progress.json`:
- Incrementa `features_done` per il plugin
- Ricalcola `completion` percentage
- Aggiorna `last_update`

### Step 5: Check Unblocks

Verifica se questa feature sblocca altre:
```
Feature F007 completed!

This unblocks:
- F008: PDF Export (was waiting for Word template)
- F009: Email report (was waiting for Word template)
```

### Step 6: Celebration

```
╔═══════════════════════════════════════════════════════════════╗
║                    🎉 FEATURE COMPLETED!                       ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                 ║
║  Feature: F007 - Complete Word Report Template                  ║
║  Plugin: ftm_cpurc                                              ║
║  Time: Started 2026-01-22 14:00, Completed 2026-01-22 15:30     ║
║  Duration: 1h 30m                                               ║
║                                                                 ║
║  Plugin Progress: ftm_cpurc 40% → 50%                           ║
║  Overall Progress: 77% → 78%                                    ║
║                                                                 ║
║  Unblocked: 2 features                                          ║
║                                                                 ║
║  Next suggested: F008 - PDF Export                              ║
║                                                                 ║
╚═══════════════════════════════════════════════════════════════╝
```

## Options

| Flag | Description |
|------|-------------|
| `--notes "text"` | Aggiungi note al completamento |
| `--skip-check` | Salta checklist verifica |
| `--with-commit` | Crea commit automatico |

## Regression

Se una feature completata ha un bug:
```
/ftm-complete regression F007 "Bug nel template con caratteri speciali"
```

Questo:
1. Riporta status a "in_progress"
2. Aggiunge nota regression
3. Decrementa contatori
