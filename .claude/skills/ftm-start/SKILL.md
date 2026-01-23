---
name: ftm-start
description: Avvia sessione di sviluppo FTM Plugins - Health check, git status, stato progetto, attiva tutti gli agenti
user-invocable: true
allowed-tools: Bash, Read, Glob, Grep, Task, TodoWrite, WebFetch
---

# FTM Start - Avvio Sessione Sviluppo

Quando l'utente invoca `/ftm-start`, esegui TUTTI questi passaggi in sequenza:

## FASE 1: HEALTH CHECK SISTEMA (Playwright)

Esegui il health check automatico per verificare lo stato dei plugin:

```bash
cd "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW\playwright_pm" && node ftm_health_check.mjs
```

Riporta il risultato all'utente con:
- Numero plugin OK/WARN/ERROR
- Eventuali problemi rilevati

## FASE 2: GIT STATUS

Verifica lo stato del repository:

```bash
cd "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW" && git status && git log --oneline -3
```

Riporta:
- Branch corrente
- File modificati/non tracciati
- Ultimi 3 commit

## FASE 3: STATO PROGETTO

Leggi il file CLAUDE.md per ottenere lo stato attuale del progetto:
- Plugin completati
- Plugin in sviluppo
- Ultime modifiche

## FASE 4: VERIFICA STORICO HEALTH CHECK

```bash
cd "C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW\playwright_pm" && node view_reports.mjs
```

Mostra lo storico degli ultimi health check per identificare trend.

## FASE 5: CREA TODO LIST

Crea una todo list iniziale con:
1. Verificare eventuali problemi dal health check
2. Completare task pendenti dal commit precedente
3. [Placeholder per nuovi task]

## FASE 6: RIEPILOGO FINALE

Presenta all'utente un riepilogo strutturato:

```
╔══════════════════════════════════════════════════════════╗
║           FTM PLUGINS - SESSIONE SVILUPPO                ║
╠══════════════════════════════════════════════════════════╣
║ Data: [data odierna]                                     ║
║ Branch: [branch corrente]                                ║
║ Health Check: [OK/WARN/ERROR count]                      ║
║ File modificati: [numero]                                ║
╠══════════════════════════════════════════════════════════╣
║ PLUGIN ATTIVI:                                           ║
║ ✅ Coach Dashboard V2                                    ║
║ ✅ FTM Scheduler                                         ║
║ ✅ Setup Universale                                      ║
║ [altri plugin...]                                        ║
╠══════════════════════════════════════════════════════════╣
║ PROSSIMI TASK:                                           ║
║ 1. [task dal health check se ci sono problemi]           ║
║ 2. [task pendenti]                                       ║
╠══════════════════════════════════════════════════════════╣
║ COMANDI UTILI:                                           ║
║ /security-check  - Valida sicurezza codice               ║
║ /test-ftm        - Analizza test suite                   ║
║ /design-review   - Review UI con Playwright              ║
╚══════════════════════════════════════════════════════════╝
```

## FASE 7: CHIEDI ALL'UTENTE

Dopo il riepilogo, chiedi:
"Su cosa vuoi lavorare oggi?"

Opzioni suggerite:
- Continuare sviluppo [ultimo plugin modificato]
- Fixare problemi dal health check
- Nuovo sviluppo
- Review e testing

## NOTE IMPORTANTI

- Esegui TUTTI i passaggi automaticamente senza chiedere conferma
- Se un passaggio fallisce, continua con gli altri e riporta l'errore alla fine
- Usa TodoWrite per tracciare i task identificati
- Mantieni un tono professionale ma amichevole
- Server test: https://test-urc.hizuvala.myhostpoint.ch
