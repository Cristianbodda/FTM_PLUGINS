# FTM Plugins - Development Journey

## 29 Gennaio 2026

### Selfassessment - Hook System Migration
- **Problema:** Moodle 4.3+ deprecation warning per `before_standard_html_head` callback
- **Soluzione:** Migrato al nuovo sistema hook
- **File creati:**
  - `classes/hook_callbacks.php` - Nuova classe callback
  - `db/hooks.php` - Registrazione hook
- **File modificati:**
  - `lib.php` - Rimossa vecchia funzione callback
  - `version.php` - Aggiornato a 1.3.1 (requires Moodle 4.3+)

### Selfassessment - Area Mapping Fix
- **Problema:** Competenze CHIMFARM, ELETTRICITÀ, GEN finivano in "ALTRO"
- **Soluzione:** Esteso area_map in compile.php con tutti i prefissi:
  - CHIMFARM, CHIMICA, FARMACEUTICA
  - ELETTRICITÀ (con accento), ELETTRICITA (senza)
  - GEN, GEN_, GENERICO, GENERICHE, TRASVERSALI, SOFT_
  - MECCANICA_PRG, MECCANICA_SAQ, MECCANICA_PIAN
  - OLD_02, OLD_ (legacy)

### Selfassessment - Bloom Legend Enhancement
- **Richiesta:** Legenda Bloom più dettagliata con esempi pratici
- **Implementazione:**
  - Sezione collassabile con freccia toggle
  - 6 livelli con descrizioni dettagliate
  - Esempi pratici per utenti senza istruzione superiore
  - Quick reference per autovalutazione rapida
- **File:** `compile.php`

### Selfassessment - Diagnostic Tools
- **Creati script diagnostici:**
  - `diagnose_critest.php` - Diagnosi completa utente test
  - `catchup_test_users.php` - Assegnazione retroattiva competenze
  - `analyze_all_prefixes.php` - Analisi prefissi competenze DB
  - `check_fabio_comps.php` - Verifica competenze utente Fabio
  - `debug_chimfarm.php`, `debug_fabio.php`, `debug_observer.php`
  - `fix_observer.php`, `force_assign.php`

### Quiz Export Tool - Competencyxmlimport
- **Funzionalità:** Export domande quiz con risposte e competenze
- **Uso:** Analisi duplicati in corsi (es. Chimica 23)
- **Output:** CSV con colonne Quiz, #, Domanda, Risposte A-D, Corretta, Competenza, Difficoltà
- **File:** `quiz_export.php`, `classes/quiz_exporter.php`, `classes/quiz_excel_exporter.php`

### Coach Manager - New Files
- `coach_navigation.php` - Navbar unificata
- `coach_student_view.php` - Vista studente per coach
- `ajax_send_invitation.php` - Invio inviti autovalutazione

### Analisi Chimica 23
- **Problemi trovati:**
  - Quiz APPR00_OBBLIGATORIO duplicato (2 versioni identiche)
  - 15 domande duplicate nel quiz OBBLIGATORIO
  - 50+ risposte con testo spurio "AREA F2:..."
- **Raccomandazioni:**
  - Eliminare quiz duplicato
  - Pulire risposte con AREA F2
  - Verificare question bank

---

## 28 Gennaio 2026

### Gap Comments System
- Sistema automatico suggerimenti basati su gap analysis
- 79 aree mappate con attività lavorative
- Due toni: Formale e Colloquiale
- File: `gap_comments_mapping.php`

### Student Report Print v2
- Radar 360px compatto
- Rettangoli colorati per aree DETTAGLIO
- Fix overlap header
- Font tabella 7pt

---

## 27 Gennaio 2026

### Coach Dashboard V2
- 4 viste configurabili
- Zoom accessibilità (90-140%)
- Timeline 6 settimane
- Export Word

---

## 24 Gennaio 2026

### Sistema CPURC Completato
- Import CSV
- Dashboard segreteria
- Student Card (4 tab)
- Coach Assignment
- Multi-Settore
- Report Word
- Export Excel/Word Bulk

---

## TODO per prossime sessioni

1. **Selfassessment:**
   - Verificare funzionamento hook su produzione
   - Testare con tutti gli utenti di test

2. **Quiz Analysis:**
   - Pulire domande duplicate Chimica 23
   - Rimuovere "AREA F2" dalle risposte
   - Verificare altri corsi per problemi simili

3. **Coach Manager:**
   - Completare coach_student_view.php
   - Testare sistema inviti

4. **FTM AI:**
   - Ancora in STANDBY
   - Pronto per integrazione quando Azure disponibile
