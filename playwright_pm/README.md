# FTM Plugins - Playwright Project Manager

Sistema di automazione test e monitoraggio per FTM Plugins usando Playwright.

## Installazione

```bash
npm install playwright
```

## Script Disponibili

### ftm_health_check.mjs
Verifica lo stato di salute di tutti i plugin FTM.

```bash
node ftm_health_check.mjs
```

**Output:**
- Console log con stato di ogni plugin
- Screenshot salvati in `./screenshots/`
- Riepilogo finale con OK/WARN/ERROR

### coach_dashboard_test.mjs
Test specifico per Coach Dashboard V2.

```bash
node coach_dashboard_test.mjs
```

**Verifica:**
- 4 viste (Classica, Compatta, Standard, Dettagliata)
- 4 livelli zoom (A-, A, A+, A++)
- Filtri orizzontali
- Export Word

## Plugin Monitorati

| Plugin | URL | Priorita |
|--------|-----|----------|
| Coach Dashboard V2 | /local/coachmanager/coach_dashboard_v2.php | ALTA |
| Coach Dashboard | /local/coachmanager/coach_dashboard.php | MEDIA |
| FTM Scheduler | /local/ftm_scheduler/index.php | MEDIA |
| Sector Admin | /local/competencymanager/sector_admin.php | MEDIA |
| Test Suite | /local/ftm_testsuite/ | BASSA |
| Setup Universale | /local/competencyxmlimport/setup_universale.php | MEDIA |

## Workflow Consigliato

1. **Dopo ogni modifica codice:**
   ```bash
   node ftm_health_check.mjs
   ```

2. **Prima di ogni commit:**
   - Verifica tutti gli screenshot generati
   - Controlla che non ci siano errori

3. **Per debug specifico:**
   - Modifica `headless: false` per vedere il browser
   - Aumenta i timeout se necessario

## Struttura Cartelle

```
playwright_pm/
|-- ftm_health_check.mjs      # Health check completo
|-- coach_dashboard_test.mjs  # Test Coach Dashboard V2
|-- README.md                  # Questa documentazione
|-- screenshots/               # Screenshot generati
    |-- coach_dashboard_v2.png
    |-- ftm_scheduler.png
    |-- ...
```

## Credenziali Test

Le credenziali sono hardcoded negli script per ambiente di test.
**NON USARE IN PRODUZIONE!**

## Report e Screenshot

I report automatici vengono salvati in:
- `reports/` - JSON con risultati health check
- `screenshots/` - Screenshot di tutte le pagine testate
- `logs/` - Log dello scheduler

### Screenshot Recenti (27/01/2026)
- `competencymanager_student_report.png` - Report studente con radar
- `coachmanager_coach_dashboard_v2.png` - Dashboard coach v2
- `ftm_scheduler_ftm_scheduler.png` - Scheduler FTM

---

*Ultimo aggiornamento: 27 Gennaio 2026*
