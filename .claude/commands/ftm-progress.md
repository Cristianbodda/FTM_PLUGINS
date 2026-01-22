# FTM Progress Command

## Overview
Mostra lo stato di avanzamento complessivo del progetto FTM Plugins con metriche e statistiche.

## Workflow

### Step 1: Carica Dati
Leggi i file:
- `.claude/ftm_progress.json` - Avanzamento per plugin
- `.claude/ftm_features.json` - Database features
- `.claude/ftm_dependencies.json` - Grafo dipendenze

### Step 2: Genera Report Visuale

```
╔═══════════════════════════════════════════════════════════════════════╗
║                      FTM PLUGINS - PROGRESS REPORT                     ║
║                          22 Gennaio 2026                               ║
╠═══════════════════════════════════════════════════════════════════════╣
║                                                                         ║
║  OVERALL COMPLETION: ████████████████░░░░ 77%                          ║
║                                                                         ║
║  Health Status: 🟢 GOOD (15 OK, 2 WARN, 0 ERROR)                       ║
║                                                                         ║
╠═══════════════════════════════════════════════════════════════════════╣
║                         PLUGIN BREAKDOWN                               ║
╠═══════════════════════════════════════════════════════════════════════╣
║                                                                         ║
║  🟢 competencyxmlimport  ████████████████████░ 95%  Setup Universale   ║
║  🟢 ftm_scheduler        ████████████████████░ 95%  Calendario         ║
║  🟢 competencymanager    ██████████████████░░░ 90%  Sector Manager     ║
║  🟢 selfassessment       ██████████████████░░░ 90%  Observer settori   ║
║  🟢 coachmanager         █████████████████░░░░ 85%  Dashboard V2       ║
║  🟢 ftm_testsuite        █████████████████░░░░ 85%  5 agenti test      ║
║  🟡 competencyreport     ████████████████░░░░░ 80%  Report base        ║
║  🟡 ftm_hub              ███████████████░░░░░░ 75%  Hub navigazione    ║
║  🟡 ftm_common           ████████████░░░░░░░░░ 60%  Design system      ║
║  🟡 labeval              ██████████░░░░░░░░░░░ 50%  Valutazione base   ║
║  🔴 ftm_cpurc            ████████░░░░░░░░░░░░░ 40%  Import CSV         ║
║                                                                         ║
╠═══════════════════════════════════════════════════════════════════════╣
║                         SESSION STATS                                  ║
╠═══════════════════════════════════════════════════════════════════════╣
║  Last Session: 2026-01-22 (180 min)                                    ║
║  Features Completed: 5                                                  ║
║  Commits: 4                                                             ║
║  Total Sessions: 1                                                      ║
╚═══════════════════════════════════════════════════════════════════════╝
```

### Step 3: Raccomandazioni

Basandosi sui dati, suggerisci:

1. **Prossime priorità** (plugin con completion < 80%)
2. **Quick wins** (features facili da completare)
3. **Blockers** (dipendenze non soddisfatte)

### Step 4: Aggiorna Sessione

Alla fine di ogni sessione di lavoro:
```json
{
  "date": "2026-01-22",
  "duration_minutes": 180,
  "features_completed": 5,
  "commits": 4,
  "notes": "Descrizione lavoro svolto"
}
```

## Output Format

```markdown
## FTM Progress Report

### Overall
- **Completion:** 77%
- **Health:** GOOD
- **Plugins:** 11
- **Features:** 87 total, 65 done, 5 in progress

### Top Priorities
1. **ftm_cpurc** (40%) - Completare Word report template
2. **labeval** (50%) - Aggiungere valutazione avanzata
3. **ftm_common** (60%) - Estrarre utilities condivise

### Recent Progress
| Date | Features | Commits | Notes |
|------|----------|---------|-------|
| 2026-01-22 | 5 | 4 | Dashboard V2, agenti, security fix |

### Next Actions
1. Completare ftm_cpurc Word template
2. Refactoring DRY in ftm_common
3. Aggiungere lang strings mancanti
```

## Legend

| Icon | Meaning |
|------|---------|
| 🟢 | 80-100% complete |
| 🟡 | 50-79% complete |
| 🔴 | 0-49% complete |
| ████ | Progress bar filled |
| ░░░░ | Progress bar empty |
