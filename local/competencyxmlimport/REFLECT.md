# REFLECT - Stato Progetto FTM competencyxmlimport

**Ultimo aggiornamento:** 15 Gennaio 2026

---

## NOTA: SELF ASSESSMENT (15/01/2026)

Nella sessione del 15 gennaio è stato completato il plugin **Self Assessment** (`local_selfassessment`):
- Popup bloccante obbligatorio per studenti
- Sistema doppia password skip: `6807` (temporaneo) / `FTM` (permanente)
- Notifiche automatiche post-quiz
- Mapping aree competenze esteso (LOGISTICA, AUTOMAZIONE, ecc.)
- Lingua italiana forzata nel form

Vedi `local/selfassessment/PROJECT_STATUS.md` e `local/selfassessment/SELFASSESSMENT_FLOW.md` per dettagli.

---

## NOTA: COACH DASHBOARD (14/01/2026)

Nella sessione del 14 gennaio è stata sviluppata la **Coach Dashboard** nel plugin `local_coachmanager`.
Vedi `CLAUDE.md` nella root e `local/coachmanager/PROJECT_STATUS.md` per dettagli.

---

## STATO ATTUALE

| Aspetto | Stato | Note |
|---------|-------|------|
| **Import Word** | Funzionante | 19 formati supportati (v4.0) |
| **Verifica Excel** | Implementato | Necessita fogli QC nel formato corretto |
| **Setup Universale** | v5.0 | Workflow completo 5 step |
| **Assegnazione Competenze** | Funzionante | Via idnumber in nome domanda |
| **Download Template** | Disponibile | XML, Excel, Word, istruzioni AI |

---

## WORD PARSER v4.0 - 19 FORMATI SUPPORTATI

### AUTOVEICOLO (3 formati)
| # | Formato | Pattern |
|---|---------|---------|
| 1 | FORMAT_1_AUTOV_BASE | AUT_BASE_Q01 + Competenza collegata: |
| 2 | FORMAT_2_AUTOV_APPR | Q01 (ID) + checkmark + Competenza (CO): |
| 7 | FORMAT_7_AUTOV_APPR36 | Q01 - Competenza: AUTOMOBILE_XXX |

### ELETTRONICA (2 formati)
| # | Formato | Pattern |
|---|---------|---------|
| 3 | FORMAT_3_ELETT_BASE | Q01 + Competenza: all'inizio blocco |
| 4 | FORMAT_4_ELETT_APPR06 | Q01 - COMP_CODE (codice nel header) |

### CHIMICA (3 formati)
| # | Formato | Pattern |
|---|---------|---------|
| 5 | FORMAT_5_CHIM_BASE | Competenza: XXX \| Risposta: X \| |
| 6 | FORMAT_6_CHIM_APPR00 | Q01 (ID) + Competenza (F2): |
| 8 | FORMAT_8_CHIM_APPR23 | Competenza (F2): dopo risposte |

### ELETTRICITA (5 formati)
| # | Formato | Pattern |
|---|---------|---------|
| 10 | FORMAT_10_ELET_BASE | ELET_BASE_Q01 - ELETTRICITA_XX_YY |
| 11 | FORMAT_11_ELET_BULLET | Bullet list (-) + checkmark + Competenza: |
| 12 | FORMAT_12_ELET_PIPE | Q01 \| Codice competenza: ELETTRICITA_XX |
| 13 | FORMAT_13_ELET_DOT | Q01. Testo + Codice competenza: |
| 14 | FORMAT_14_ELET_NEWLINE | Q##\nCompetenza:\nCodice: ELETTRICITA_XX |

### LOGISTICA (4 formati)
| # | Formato | Pattern |
|---|---------|---------|
| 15 | FORMAT_15_LOGISTICA | 1. LOG_BASE_Q01 + Competenza: LOGISTICA_XX |
| 16 | FORMAT_16_LOG_APPR | LOG_APPR01_Q01 + Competenza: LOGISTICA_XX |
| 17 | FORMAT_17_LOG_APPR_DASH | LOG_APPR04_Q01 - LOGISTICA_XX (stessa riga) |
| 18 | FORMAT_18_LOG_Q_DASH | Q1 - LOGISTICA_XX (Q semplice + dash) |

### MECCANICA (1 formato)
| # | Formato | Pattern |
|---|---------|---------|
| 19 | FORMAT_19_MECCANICA | [A-H]?##. + Codice competenza: MECCANICA_XX |

### GENERICO
| # | Formato | Pattern |
|---|---------|---------|
| 9 | FORMAT_9_NO_COMP | File senza competenze (richiede Excel) |

---

## SVILUPPI RECENTI (Sessione 13/01/2026)

### 1. Supporto LOGISTICA completo
- Fix pattern `[A-Z0-9_]+` per matchare LOG_APPR05_Q01 (numeri nel prefisso)
- Fix pattern `(?:^|\n)` per matchare domande a inizio testo
- Fix `\s*$` invece di `\s*\n` per fine riga con flag multiline
- Aggiunto FORMAT_17 e FORMAT_18 per varianti con dash

### 2. Supporto MECCANICA
- Nuovo FORMAT_19_MECCANICA per tutti i file MECC_*
- Pattern domanda: `[A-H]?\d+.` (es. 1., A1., B2.)
- Pattern competenza: `Codice competenza: MECCANICA_XX_YY`
- Supporta Test Base (1., 2.) e APPR (A1., B1., ecc.)

### 3. Script di Debug
- `debug_word.php` per analizzare struttura file Word
- Mostra pattern rilevati e risultati split
- Utile per diagnosticare formati non riconosciuti

---

## FILE CREATI/MODIFICATI

| File | Azione | Descrizione |
|------|--------|-------------|
| `classes/word_parser.php` | Aggiornato v4.0 | 19 formati, supporto LOGISTICA + MECCANICA |
| `debug_word.php` | Nuovo | Script debug analisi Word |
| `analyze_meccanica.php` | Nuovo | Script analisi batch file MECCANICA |
| `CLAUDE.md` | Aggiornato | Documentazione formati Word |
| `REFLECT.md` | Aggiornato | Questo file |

---

## ISSUE RISOLTI

### 1. LOGISTICA APPR05 - 0 domande estratte
**Problema:** Pattern `[A-Z_]+_Q\d+` non matchava `LOG_APPR05_Q01` (05 sono numeri)
**Soluzione:** Cambiato in `[A-Z0-9_]+_Q\d+` per includere numeri

### 2. Excel verification - risultati contraddittori
**Problema:** Mostrava "0/25 match" ma "Tutte le domande corrispondono!"
**Soluzione:** Fix `normalize_question_num()` per prioritizzare pattern `_Q##` alla fine

### 3. Prima domanda non riconosciuta
**Problema:** Pattern richiedeva `\n` prima della domanda, ma testo iniziava direttamente
**Soluzione:** Usato `(?:^|\n)` per accettare sia inizio testo che newline

---

## PROSSIMI PASSI SUGGERITI

1. [ ] Caricare word_parser.php aggiornato via FTP sul server
2. [ ] Testare import LOGISTICA (tutti i file APPR01-06 + Test Base)
3. [ ] Testare import MECCANICA (Test Base + APPR A-H)
4. [ ] Validare workflow completo Word -> Excel verify -> Moodle

---

## CONTESTO PROGETTO

**Plugin:** local_competencyxmlimport
**Piattaforma:** Moodle 4.4+/4.5/5.0
**Framework:** Passaporto tecnico FTM
**Settori:** MECCANICA, AUTOMOBILE, CHIMFARM, ELETTRICITA, AUTOMAZIONE, LOGISTICA, METALCOSTRUZIONE

---

## RIFERIMENTI

- `README_WORD_IMPORT.md` - Guida import Word
- `FTM_GUIDA_FORMATO_QUIZ_PER_AI.md` - Guida per AI
- `CLAUDE.md` - Istruzioni per Claude Code
- `debug_word.php` - Script debug formati

---

*Generato per sessione di sviluppo FTM - 13 Gennaio 2026*
