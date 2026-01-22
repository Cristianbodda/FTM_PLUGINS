# FTM Next Command

## Overview
Determina la prossima feature da implementare basandosi su priorità, dipendenze e stato corrente.

## Workflow

### Step 1: Carica Stato
Leggi:
- `.claude/ftm_features.json` - Features pending
- `.claude/ftm_dependencies.json` - Dipendenze
- `.claude/ftm_progress.json` - Contesto recente

### Step 2: Algoritmo Selezione

```
1. Filtra features con status = "pending"
2. Rimuovi features con dipendenze non soddisfatte
3. Ordina per:
   a. Priorità plugin (HIGH > MEDIUM > LOW)
   b. Priorità feature (HIGH > MEDIUM > LOW)
   c. Data creazione (più vecchie prima)
4. Seleziona la prima
```

### Step 3: Prepara Contesto

Per la feature selezionata, raccogli:
- File coinvolti
- Dipendenze del plugin
- Features correlate completate
- Note precedenti

### Step 4: Output

```
╔═══════════════════════════════════════════════════════════════╗
║                      NEXT FEATURE TO IMPLEMENT                 ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                 ║
║  ID: F007                                                       ║
║  Plugin: ftm_cpurc                                              ║
║  Priority: HIGH                                                 ║
║                                                                 ║
║  Feature: Complete Word Report Template                         ║
║                                                                 ║
║  Description:                                                   ║
║  Completare il template Word per la generazione del report      ║
║  finale studente con dati CPURC importati.                      ║
║                                                                 ║
╠═══════════════════════════════════════════════════════════════╣
║  CONTEXT                                                        ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                 ║
║  Files:                                                         ║
║  - local/ftm_cpurc/classes/word_generator.php                   ║
║  - local/ftm_cpurc/templates/report_template.docx               ║
║                                                                 ║
║  Dependencies: None                                             ║
║                                                                 ║
║  Related Completed:                                             ║
║  - F005: CSV Import base (completed 2026-01-20)                 ║
║  - F006: Data validation (completed 2026-01-21)                 ║
║                                                                 ║
╠═══════════════════════════════════════════════════════════════╣
║  SUGGESTED APPROACH                                             ║
╠═══════════════════════════════════════════════════════════════╣
║                                                                 ║
║  1. Esaminare template Word esistente                           ║
║  2. Identificare placeholder da sostituire                      ║
║  3. Implementare word_generator.php                             ║
║  4. Testare con dati reali                                      ║
║  5. Verificare output PDF/DOCX                                  ║
║                                                                 ║
╚═══════════════════════════════════════════════════════════════╝

Vuoi procedere con questa feature? (si/no/skip)
```

### Step 5: Claim Feature

Se l'utente conferma:
1. Aggiorna status a "in_progress"
2. Registra timestamp inizio
3. Aggiorna `.claude/ftm_features.json`

## Alternative Actions

| Action | Description |
|--------|-------------|
| `si` | Inizia lavoro sulla feature |
| `no` | Annulla, non fare nulla |
| `skip` | Salta questa feature, mostra la prossima |
| `list` | Mostra top 5 features pending |

## Skip Reasons

Se skip, chiedi motivo:
- `blocked` - Dipendenza esterna
- `unclear` - Requisiti non chiari
- `later` - Da fare dopo
- `other` - Altro motivo

Registra nel JSON per analytics.
