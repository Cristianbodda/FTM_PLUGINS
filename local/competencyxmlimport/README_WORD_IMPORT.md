# Setup Universale - Import Word v4.0

## Versione 4.0 - Gennaio 2026

Questa versione include:
- Import file Word (.docx) con 19 formati supportati
- Revisione domande con correzione interattiva
- Verifica contro file Excel di controllo
- Conversione automatica Word -> XML Moodle
- Supporto completo per 6 settori: AUTOVEICOLO, ELETTRONICA, CHIMICA, ELETTRICITA, LOGISTICA, MECCANICA

---

## FORMATI WORD SUPPORTATI (19 totali)

### AUTOVEICOLO (3 formati)

**FORMAT_1 - Test Base**
```
AUT_BASE_Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Competenza collegata: AUTOMOBILE_MR_A1
Risposta corretta: B
```

**FORMAT_2 - Approfondimenti 0-2**
```
Q01 (ID)

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D (checkmark sulla corretta)

Competenza (CO): AUTOMOBILE_MAu_G10
```

**FORMAT_7 - Approfondimenti 3-6**
```
Q01 - Competenza: AUTOMOBILE_XXX

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: C
```

---

### ELETTRONICA (2 formati)

**FORMAT_3 - Base/Approfondimenti**
```
Q01

Competenza: AUTOMAZIONE_OA_A1

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
```

**FORMAT_4 - Approfondimenti 03/06**
```
Q01 - AUTOMAZIONE_OA_A1

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
```

---

### CHIMICA (3 formati)

**FORMAT_5 - Base/Approfondimento 01**
```
Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Competenza: CHIMFARM_7S_01 | Risposta corretta: B |
```

**FORMAT_6 - Approfondimento 00**
```
Q01 (ID)

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Competenza (F2): CHIMFARM_9A_01
Risposta corretta: C
```

**FORMAT_8 - Approfondimenti 02/03**
```
Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
Competenza (F2): CHIMFARM_4S_08
```

---

### ELETTRICITA (5 formati)

**FORMAT_10 - Test Base**
```
ELET_BASE_Q01 - ELETTRICITA_IE_F2

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
```

**FORMAT_11 - Approfondimento 00 (Bullet)**
```
Q01 Testo della domanda...

- Risposta A
- Risposta B (checkmark)
- Risposta C
- Risposta D

Competenza: ELETTRICITA_MA_A1
```

**FORMAT_12 - Approfondimento 04 (Pipe)**
```
Q01 | Codice competenza: ELETTRICITA_IE_F2

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
```

**FORMAT_13 - Approfondimenti 01/02 (Dot)**
```
Q01. Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Codice competenza: ELETTRICITA_MA_C2
Risposta corretta: C
```

**FORMAT_14 - Approfondimenti 03/05/06 (Newline)**
```
Q01

Competenza: Descrizione competenza
Codice: ELETTRICITA_IE_F2

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: D
```

---

### LOGISTICA (4 formati)

**FORMAT_15 - Test Base**
```
1. LOG_BASE_Q01

Competenza: LOGISTICA_LO_F5

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
```

**FORMAT_16 - Approfondimenti (senza numero)**
```
LOG_APPR01_Q01

Competenza: LOGISTICA_LO_C5

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
```

**FORMAT_17 - Approfondimenti (dash su stessa riga)**
```
LOG_APPR04_Q01 - LOGISTICA_LO_H2

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: C
```

**FORMAT_18 - Approfondimento 06 (Q semplice + dash)**
```
Q1 - LOGISTICA_LO_D4

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
```

---

### MECCANICA (1 formato)

**FORMAT_19 - Tutti i file MECCANICA**
```
1. Testo della domanda (Test Base numerico)...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
Codice competenza: MECCANICA_DT_01
```

```
A1. Testo della domanda (APPR con prefisso lettera)...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
Codice competenza: MECCANICA_LMC_02
```

File supportati:
- MECC_TestBase (domande 1., 2., 3....)
- MECC_APPR_A (domande A1., A2., A3....)
- MECC_APPR_B (domande B1., B2., B3....)
- MECC_APPR_C, D, E, F (stessa struttura)
- MECC_APPR_G, H (domande 1., 2., 3....)

---

## WORKFLOW COMPLETO

```
STEP 1-2: Seleziona Framework e Settore

STEP 3: Upload File Word
         |
         v
  REVISIONE FILE WORD
  - Formato rilevato automaticamente
  - Domande estratte mostrate
  - Correzione interattiva disponibile
         |
         v
  VERIFICA EXCEL (opzionale)
  - Carica Excel di controllo
  - Seleziona foglio e colonne
  - Confronto automatico
  - Risolvi discrepanze
         |
         v
  CONVERTI IN XML
         |
         v
STEP 4-5: Configura Quiz e Import
```

---

## STRUTTURA FILE EXCEL DI CONTROLLO

### Foglio QC (uno per ogni quiz)

| Q | Codice_competenza | Risposta_corretta |
|---|-------------------|-------------------|
| Q01 | SETTORE_XX_YY | A |
| Q02 | SETTORE_XX_YY | B |
| ... | ... | ... |

### Naming convention fogli
- `QC_TEST_BASE` - per quiz Test Base
- `QC_APPR01` - per quiz Approfondimento 01
- `QC_APPR_A` - per quiz MECCANICA Approfondimento A

---

## TROUBLESHOOTING

### File non riconosciuto / 0 domande
1. Vai a `debug_word.php` nel browser
2. Carica il file Word
3. Verifica i pattern rilevati
4. Se il formato non e supportato, contatta lo sviluppatore

### Competenze non trovate
- Verifica che i codici siano nel formato SETTORE_XX_YY
- Usa il pannello di correzione per sistemare manualmente

### Discrepanze Word vs Excel
- Seleziona "Usa Excel" o "Mantieni Word" per ogni discrepanza
- Tutte le discrepanze devono essere risolte prima della conversione

---

## FILE DEL PACCHETTO

| File | Descrizione |
|------|-------------|
| `setup_universale.php` | Pagina principale |
| `download_template.php` | Download template |
| `classes/word_parser.php` | Parser Word v4.0 (19 formati) |
| `classes/word_import_helper.php` | Helper import Word |
| `classes/excel_reader.php` | Lettore Excel |
| `classes/excel_verifier.php` | Verificatore Word vs Excel |
| `ajax/word_import.php` | AJAX Word |
| `ajax/excel_verify.php` | AJAX Excel |
| `debug_word.php` | Script debug formati |

---

*Sviluppato per FTM - Fondazione Terzo Millennio*
*Versione 4.0 - Gennaio 2026*
