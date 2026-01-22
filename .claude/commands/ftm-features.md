# FTM Features Command

## Overview
Gestisce le features da implementare per i plugin FTM. Legge e aggiorna il database features persistente.

## Workflow

### Step 1: Carica Database
Leggi il file `.claude/ftm_features.json` per ottenere lo stato attuale delle features.

### Step 2: Mostra Features

#### Lista per Plugin
```
╔═══════════════════════════════════════════════════════════════╗
║                    FTM FEATURES DATABASE                       ║
╠═══════════════════════════════════════════════════════════════╣
║ Plugin              │ Total │ Done │ Progress │ Priority      ║
╠═══════════════════════════════════════════════════════════════╣
║ coachmanager        │   10  │   8  │ ████████░░ 80% │ HIGH   ║
║ competencymanager   │   12  │  11  │ █████████░ 92% │ HIGH   ║
║ competencyxmlimport │    8  │   7  │ █████████░ 88% │ HIGH   ║
║ ...                 │  ...  │ ...  │ ...            │ ...    ║
╚═══════════════════════════════════════════════════════════════╝
```

#### Lista Features Pending
```
╔═══════════════════════════════════════════════════════════════╗
║                    PENDING FEATURES                            ║
╠═══════════════════════════════════════════════════════════════╣
║ ID   │ Plugin         │ Feature              │ Priority       ║
╠═══════════════════════════════════════════════════════════════╣
║ F001 │ coachmanager   │ Export PDF report    │ HIGH           ║
║ F002 │ ftm_common     │ Shared CSS utilities │ MEDIUM         ║
║ F003 │ ftm_cpurc      │ Word report template │ LOW            ║
╚═══════════════════════════════════════════════════════════════╝
```

### Step 3: Operazioni Disponibili

#### Aggiungere Feature
```json
{
  "id": "F001",
  "plugin": "coachmanager",
  "name": "Export PDF report",
  "description": "Esportare report studente in PDF",
  "priority": "high",
  "status": "pending",
  "dependencies": [],
  "created": "2026-01-22",
  "completed": null
}
```

#### Aggiornare Stato
- `pending` → `in_progress` → `completed`
- `pending` → `blocked` (se dipendenze non soddisfatte)
- `completed` → `regression` (se bug trovato)

### Step 4: Salva Database
Dopo modifiche, aggiorna `.claude/ftm_features.json` con:
- Nuovo conteggio totali
- Timestamp aggiornamento
- Feature modificate

## Output Format

```markdown
## FTM Features Report

**Database Version:** 1.0.0
**Last Updated:** 2026-01-22

### Summary
| Status | Count |
|--------|-------|
| Completed | 65 |
| In Progress | 5 |
| Pending | 15 |
| Blocked | 2 |

### By Plugin
[Table with plugin breakdown]

### Pending Features (Priority Order)
1. **[HIGH]** coachmanager: Export PDF report
2. **[HIGH]** ftm_cpurc: Complete Word template
3. **[MEDIUM]** ftm_common: Extract shared CSS

### Recently Completed
- 2026-01-22: coachmanager - Dashboard V2 navigation
- 2026-01-22: coachmanager - Collapsible sections
```

## Commands

| Action | Usage |
|--------|-------|
| List all | `/ftm-features` |
| Add feature | `/ftm-features add [plugin] [name]` |
| Complete | `/ftm-features complete [id]` |
| Start | `/ftm-features start [id]` |
| Block | `/ftm-features block [id] [reason]` |
