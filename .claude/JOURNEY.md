# FTM Plugins - Development Journey

## 11 Febbraio 2026

### Valutazioni Finali Editabili (IN REVISIONE)
- **Stato:** Sezione nascosta con codice sblocco (6807) - in attesa di approvazione
- **Funzionalità:** Tutti i valori nella tabella "Metodi di Valutazione Finale" sono modificabili dal coach
- **4 Metodi di calcolo:**
  - 📊 Media Completa (4 Fonti): (Quiz + Auto + Lab + Coach) / 4
  - 🎯 Media Oggettiva (3 Fonti): (Quiz + Lab + Coach) / 3
  - 📝 Media Pratica (Quiz + Coach) / 2
  - 👨‍🏫 Solo Coach
- **Caratteristiche:**
  - Badge cliccabile con input numerico (0-100%)
  - Salvataggio AJAX immediato con feedback toast
  - Indicatore ✏️ per valori modificati manualmente
  - Pulsante ↩️ per ripristinare valore calcolato
  - **Audit trail completo:** Chi ha modificato, quando (data/ora), valore originale
  - **Tooltip hover:** Passando sopra valori modificati mostra ultima modifica
  - **Storico completo:** Bottone per visualizzare tutte le modifiche precedenti
- **Sblocco sezione:** Input password nel report, codice **6807**
- **Database:**
  - Tabella `local_compman_final_ratings`: Valori manuali per ogni area/metodo
  - Tabella `local_compman_final_history`: Storico completo modifiche
- **File:**
  - `student_report.php` - UI editabile con JavaScript inline
  - `ajax_save_final_rating.php` - Endpoint AJAX per salvataggio
  - `db/install.xml` - Schema 2 nuove tabelle
  - `db/upgrade.php` - Migrazione versione 2026021001
  - `version.php` - v2.5.0

### Sistema Ponderazione (PROSSIMO STEP)
- **Obiettivo:** Sostituire le medie semplici con valutazione ponderata configurabile
- **Fasi pianificate:**
  1. UI configurazione pesi per Area (pesi default 100%)
  2. Calcolo e tabella risultato ponderato
  3. Grafico radar con overlay ponderato
  4. Modalità avanzata per competenza singola
- **Concetto:** Ogni fonte (Quiz, Auto, Lab, Coach) ha peso configurabile
- **Normalizzazione:** Se una fonte manca, ricalcola sui pesi disponibili

---

## 10 Febbraio 2026

### Coach Evaluation - Inline Rating Editor
- **Funzionalità:** Modifica valutazioni Bloom direttamente dalla tabella in Student Report
- **Caratteristiche:**
  - Badge cliccabile con dropdown rapido (N/O, 1-6)
  - Salvataggio AJAX immediato senza reload pagina
  - Toast feedback "✅ Valutazione salvata"
  - Auto-reopen valutazioni firmate per permettere modifiche
- **File:** `student_report.php`, `ajax_save_evaluation.php`

### Coach Evaluation - Reopen Functionality
- **Funzionalità:** Bottone "🔓 Riapri per Modifiche" per valutazioni firmate
- **File:** `coach_evaluation.php`, `coach_evaluation_manager.php`

### Coach Evaluation - UI Improvements
- **Nomi area completi:** Es. "A. Accoglienza, diagnosi preliminare e documentazione"
- **Descrizioni competenze:** Mostrate sotto ogni codice competenza
- **Inizializzazione rating:** Tutti i rating inizializzati a N/O al caricamento pagina
- **File:** `student_report.php`, `coach_evaluation.php`

---

## 9 Febbraio 2026

### Student Report - Grafico Overlay Multi-Fonte
- **Funzionalità:** Nuovo grafico radar sovrapposto con 4 fonti dati
- **Fonti visualizzate:**
  - 📊 Quiz (verde) - Percentuale risposte corrette
  - 🧑 Autovalutazione (viola) - Scala Bloom normalizzata
  - 🔧 LabEval (arancione) - Valutazione laboratorio
  - 👨‍🏫 Formatore (teal) - Valutazione coach Bloom
- **Caratteristiche:**
  - Normalizzazione a percentuale (Bloom 1-6 → valore/6×100)
  - Toggle checkbox per mostrare/nascondere ogni fonte
  - Tabella comparativa con Media e Gap Max per area
  - N/O mostrato come 0%
  - Stesse dimensioni degli altri radar (550px height)
- **Fix tecnico:** Script Chart.js spostato in `window.load` per attendere caricamento libreria
- **File:** `student_report.php`

### Coach Evaluation - Miglioramenti UI
- **Fix visibility:** Numeri bottoni rating ora visibili (CSS contrast fix)
- **Competency descriptions:** Aggiunta descrizione completa competenze (non solo codice)
- **Area names italiane:** Nomi completi aree per ogni settore (es. "A. Accoglienza, diagnosi...")
- **File:** `coach_evaluation.php`, `coach_evaluation_manager.php`

### Student Report - Quiz Diagnostics
- **Pannello diagnostico:** Ultimi 7 giorni di quiz attempts
- **Link Review:** Click sul quiz apre la review con domande e risposte
- **Stati gestiti:** finished, inprogress, overdue, abandoned
- **Fix Moodle 4.x:** Query aggiornate per nuova struttura `question_references` → `question_bank_entries` → `question_versions`

---

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

1. **Student Report:**
   - ✅ Grafico Overlay Multi-Fonte completato
   - Testare stampa PDF con overlay
   - Rimuovere pannello debug in produzione

2. **Coach Evaluation:**
   - ✅ Fix UI completato
   - Testare con tutti i settori

3. **Selfassessment:**
   - Verificare funzionamento hook su produzione
   - Testare con tutti gli utenti di test

4. **Quiz Analysis:**
   - Pulire domande duplicate Chimica 23
   - Rimuovere "AREA F2" dalle risposte
   - Verificare altri corsi per problemi simili

5. **FTM AI:**
   - Ancora in STANDBY
   - Pronto per integrazione quando Azure disponibile
