# local_competencyxmlimport

Plugin Moodle per import quiz da XML, Word, Excel e CSV con assegnazione automatica competenze.

**Versione:** 5.3 | **Word Parser:** v5.0 (20 formati) | **Ultimo aggiornamento:** 19/02/2026

---

## Setup Universale (AGGIORNATO 19/02/2026)

Strumento completo per import quiz e assegnazione competenze automatica.

### Nuove Funzionalita
- **Import CSV** da Quiz Export Tool (semicolon-separated, UTF-8 BOM) - NUOVO 19/02/2026
- **Regex flessibile** per codici competenza (`[A-Za-z0-9]+` invece di `[A-Za-z]+`)
- **Supporto caratteri accentati** (ELETTRICITA -> ELETTRICITÀ)
- **Alias settori** automatici (AUTOVEICOLO -> AUTOMOBILE, MECC -> MECCANICA)
- **Assegnazione competenze** a domande nuove E esistenti
- **Aggiornamento livello difficolta** se diverso da esistente
- **Debug integrato** con esempi codici DB e prime domande processate
- **Statistiche finali** con contatori dettagliati
- **Tabella riepilogo** con colonna Livello per ogni quiz
- **Robustezza CSV:** Strip BOM, rimozione debug HTML Moodle, header detection
- **Sicurezza:** strip_tags() su nomi quiz, truncate categorie a 255 chars

### Livelli Difficolta
| Livello | Valore | Icona |
|---------|--------|-------|
| Base | 1 | ⭐ |
| Intermedio | 2 | ⭐⭐ |
| Avanzato | 3 | ⭐⭐⭐ |

### Statistiche Finali
- Quiz Creati
- Domande Importate
- Competenze Assegnate (nuove)
- Livelli Aggiornati (modifiche a esistenti)
- Competenze Disponibili

### URL
`/local/competencyxmlimport/setup_universale.php?courseid=X`

---

## Formati Word Supportati (20)

| # | Formato | Settore | Pattern |
|---|---------|---------|---------|
| 1 | FORMAT_1_AUTOV_BASE | AUTOVEICOLO | AUT_BASE_Q01 |
| 2 | FORMAT_2_AUTOV_APPR | AUTOVEICOLO | Q01 (ID) + checkmark |
| 3 | FORMAT_3_ELETT_BASE | ELETTRONICA | Q01 + Competenza: |
| 4 | FORMAT_4_ELETT_APPR06 | ELETTRONICA | Q01 - COMP_CODE |
| 5 | FORMAT_5_CHIM_BASE | CHIMICA | Competenza: XXX \| |
| 6 | FORMAT_6_CHIM_APPR00 | CHIMICA | Q01 (ID) + (F2): |
| 7 | FORMAT_7_AUTOV_APPR36 | AUTOVEICOLO | Q01 - Competenza: |
| 8 | FORMAT_8_CHIM_APPR23 | CHIMICA | (F2): dopo risposte |
| 9 | FORMAT_9_NO_COMP | GENERICO | Senza competenze |
| 10 | FORMAT_10_ELET_BASE | ELETTRICITA | ELET_BASE_Q01 |
| 11 | FORMAT_11_ELET_BULLET | ELETTRICITA | Bullet + checkmark |
| 12 | FORMAT_12_ELET_PIPE | ELETTRICITA | Q01 \| Codice: |
| 13 | FORMAT_13_ELET_DOT | ELETTRICITA | Q01. Testo |
| 14 | FORMAT_14_ELET_NEWLINE | ELETTRICITA | Q##\nCodice: |
| 15 | FORMAT_15_LOGISTICA | LOGISTICA | 1. LOG_BASE_Q01 |
| 16 | FORMAT_16_LOG_APPR | LOGISTICA | LOG_APPR01_Q01 |
| 17 | FORMAT_17_LOG_APPR_DASH | LOGISTICA | LOG_APPR04_Q01 - |
| 18 | FORMAT_18_LOG_Q_DASH | LOGISTICA | Q1 - LOGISTICA_XX |
| 19 | FORMAT_19_MECCANICA | MECCANICA | [A-H]?##. + Codice: |
| 20 | FORMAT_20_GEN_LOGSTYLE | GENERICO | Domanda X + GEN_XX |

---

## Settori e Alias

| Settore Standard | Alias Supportati |
|------------------|------------------|
| AUTOMOBILE | AUTOVEICOLO |
| AUTOMAZIONE | AUTOM, AUTOMAZ |
| CHIMFARM | CHIM, CHIMICA, FARMACEUTICA |
| ELETTRICITÀ | ELETTRICITA, ELETTR, ELETT |
| LOGISTICA | LOG |
| MECCANICA | MECC |
| METALCOSTRUZIONE | METAL |

---

## Framework Competenze

### Framework Principale (FTM-01, id=9)
Settori: AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITÀ, LOGISTICA, MECCANICA, METALCOSTRUZIONE

### Framework Generico (FTM_GEN, id=10)
Settori: GENERICO

**IMPORTANTE:** GENERICO ha un framework separato!

---

## Workflow

```
1. Seleziona Framework (FTM-01 o FTM_GEN)
2. Seleziona Settore (rilevato automaticamente)
3. Upload File Word/XML/CSV
4. Revisione (correzione interattiva)
5. Verifica Excel (opzionale)
6. Configura Quiz (nome, livello, categoria)
7. Import in Moodle
8. Visualizza riepilogo con livelli
```

---

## File Principali

| File | Descrizione |
|------|-------------|
| `setup_universale.php` | Pagina principale wizard (AGGIORNATO) |
| `lib.php` | Hook navigazione (CORRETTO duplicati) |
| `download_template.php` | Download template |
| `debug_word.php` | Debug formati Word |
| `classes/word_parser.php` | Parser Word v4.0 |
| `classes/word_import_helper.php` | Helper import Word |
| `classes/excel_quiz_importer.php` | Import Excel/CSV multi-quiz |
| `classes/quiz_exporter.php` | Export quiz con competenze |
| `classes/excel_reader.php` | Lettore Excel |
| `classes/excel_verifier.php` | Verificatore Word vs Excel |
| `ajax/word_import.php` | AJAX Word |
| `ajax/excel_verify.php` | AJAX Excel |

---

## Debug

Se un file Word non viene riconosciuto:
1. Vai a `/local/competencyxmlimport/debug_word.php`
2. Carica il file Word
3. Verifica i pattern rilevati
4. Il sistema mostra quale formato viene rilevato

Durante import setup_universale.php:
- Mostra primi 5 codici competenza dal DB
- Mostra prime 3 domande con codice estratto
- Indica con ✅/❌ se il codice esiste nel lookup

---

## Documentazione

- `README_WORD_IMPORT.md` - Guida completa import Word
- `FTM_GUIDA_FORMATO_QUIZ_PER_AI.md` - Guida formati per AI
- `REFLECT.md` - Stato sviluppo progetto

---

*Versione 5.3 - 19 Febbraio 2026*
