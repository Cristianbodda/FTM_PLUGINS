# FTM Moodle Plugins - Guida all'Installazione

## Requisiti

- **Moodle**: 4.4+ / 4.5 / 5.0
- **PHP**: 8.1 o superiore
- **Database**: MySQL 8.0+ o MariaDB 10.6+

---

## Ordine di Installazione

⚠️ **IMPORTANTE**: Rispettare l'ordine per le dipendenze!

| # | Plugin | Percorso Moodle | Dipendenze |
|---|--------|-----------------|------------|
| 1 | qbank_competenciesbyquestion | `/question/bank/competenciesbyquestion/` | Nessuna |
| 2 | local_competencymanager | `/local/competencymanager/` | Plugin 1 |
| 3 | local_coachmanager | `/local/coachmanager/` | Plugin 2 |
| 4 | local_selfassessment | `/local/selfassessment/` | Nessuna |
| 5 | local_competencyreport | `/local/competencyreport/` | Nessuna |
| 6 | local_competencyxmlimport | `/local/competencyxmlimport/` | Nessuna |
| 7 | local_labeval | `/local/labeval/` | Plugin 3 |
| 8 | local_ftm_hub | `/local/ftm_hub/` | Plugin 2 |
| 9 | block_ftm_tools | `/blocks/ftm_tools/` | Plugin 2 |

---

## Verifica Installazione

Dopo l'installazione, verifica che tutto funzioni:

1. Vai su `/local/competencymanager/system_check.php`
2. Controlla che tutti i test siano verdi ✅

---

## URL dopo l'installazione

```
/local/ftm_hub/index.php           → Hub centrale
/local/competencymanager/index.php → Dashboard competenze
/local/coachmanager/index.php      → Gestione coach
/local/selfassessment/index.php    → Autovalutazione
/local/labeval/index.php           → Valutazione laboratorio
```

---

*Ultimo aggiornamento: 9 Gennaio 2026*
