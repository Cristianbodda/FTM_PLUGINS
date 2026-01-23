---
name: test-ftm
description: Analizza i risultati della FTM Test Suite e suggerisce fix per test falliti
user-invocable: true
allowed-tools: Read, Grep, Glob, WebFetch
---

# FTM Test Suite Analysis

Analizza lo stato della Test Suite FTM e fornisci indicazioni per risolvere i problemi.

## Informazioni Test Suite

- **URL Test Suite**: https://test-urc.hizuvala.myhostpoint.ch/local/ftm_testsuite/
- **File principale**: `local/ftm_testsuite/classes/test_runner.php`
- **Risultati**: `local/ftm_testsuite/results.php`

## Moduli Test (8 totali)

| Modulo | Codici | Descrizione |
|--------|--------|-------------|
| quiz | 1.x | Quiz e competenze |
| selfassessment | 2.x | Autovalutazioni |
| labeval | 3.x | LabEval |
| radar | 4.x | Radar e aggregazione |
| report | 5.x | Report PDF |
| coverage | 6.x | Copertura competenze |
| integrity | 7.x | Integrità dati |
| assignments | 8.x | Assegnazioni |

## Quando Invocato

1. Chiedi all'utente di incollare i risultati del test o il codice test fallito
2. Analizza il problema identificando:
   - Quale test è fallito (codice e nome)
   - Valore atteso vs ottenuto
   - Possibile causa root
3. Cerca nel codebase file correlati al test
4. Proponi fix specifico con codice

## Pattern Comuni di Fix

### Test 1.x (Quiz)
- Competenze orfane → Verifica `qbank_competenciesbyquestion`
- idnumber malformato → Formato `SETTORE_AREA_NUMERO`

### Test 2.x (Selfassessment)
- Mismatch → Verifica `local_selfassessment_assign`
- Range Bloom → Valori 1-6

### Test 3.x (LabEval)
- Calcolo errato → Verifica formula pesata in `generate_labeval.php`
- Cache mancante → Rigenera `comp_scores`

### Test 6.x (Coverage)
- Copertura bassa → Aggiungi domande per competenze mancanti

### Test 8.x (Assignments)
- Observer non attivo → Verifica `local/selfassessment/db/events.php`
- Source errato → Controlla creazione record con `source='quiz'`
