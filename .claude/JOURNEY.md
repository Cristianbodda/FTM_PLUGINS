# FTM Plugins - Development Journey

## 12 Febbraio 2026 (Sessione 2)

### Sezione Confronto 4 Fonti Collapsabile
- **Modifica:** La sezione "📊 Confronto 4 Fonti" è ora un accordion collapsabile
- **Comportamento:** Inizialmente chiusa, il coach la apre se necessario
- **Icona:** ▶ (chiuso) / ▼ (aperto)
- **Persistenza:** Stato salvato in localStorage
- **File:** `student_report.php` (linee 3814-4397)

### Pre-selezione Quiz Settore Primario
- **Modifica:** I quiz non sono più tutti selezionati di default
- **Nuova logica:**
  - Se selectedQuizzes ha valori → usa selezione esistente
  - Se vuoto + settore primario → pre-seleziona solo quiz del settore primario
  - Se vuoto + no settore primario → nessun quiz selezionato
- **File:** `student_report.php` (linea 2365)

### Fix Duplicate Sector in Dashboard Helper
- **Problema:** Errore "Duplicate value 'GEN' found in column 'sector'"
- **Causa:** Query SQL usava `sector` come prima colonna (non unica)
- **Soluzione:** Aggiunto `id` come prima colonna nella SELECT
- **File:** `dashboard_helper.php` (linea 370)

### Fix Auto-Default Opzioni Visualizzazione
- **Problema:** Il grafico sovrapposizione non veniva settato di default quando si selezionava un quiz
- **Causa:** Le opzioni venivano auto-settate via JavaScript solo la prima volta (`!previouslyHadSelection`), ma al reload della pagina i valori non erano preservati
- **Soluzione:**
  - Aggiunto flag `viz_configured` per tracciare se l'utente ha già configurato le opzioni
  - Se quiz selezionati e `viz_configured=0` → tutte le opzioni attive di default nel PHP
  - Dopo il submit → `viz_configured=1` → preferenze utente preservate
- **File:** `student_report.php` (linee 81, 155-165, 2321, 2621-2628, 2760)

### Sistema Tab Orizzontale per Student Report
- **Nuova UI:** Barra tab orizzontale per organizzare sezioni del report
- **6 Tab disponibili:**
  1. 👤 Settori - Gestione settori studente (primary, secondary, tertiary)
  2. 📅 Ultimi 7gg - Quiz completati negli ultimi 7 giorni
  3. ⚙️ Configurazione - Filtri quiz, opzioni visualizzazione, ponderazioni
  4. 📊 Progresso - Progresso certificazione
  5. 📈 Gap Analysis - Confronto autovalutazione vs performance
  6. 💬 Spunti Colloquio - Suggerimenti per il colloquio coach
- **Comportamento:**
  - Tutti i tab chiusi di default
  - Multi-apertura: più tab aperti contemporaneamente
  - LocalStorage: salva stato tab per utente/corso
  - Mobile: layout a 2 righe su schermi piccoli
- **Mini-accordion:** Sezioni collassabili dentro la tab Configurazione
- **File:** `student_report.php` (linee 1795-3200)

### Fix Grafico Overlay (Sovrapposizione)
- **Problema:** Il checkbox "Grafico Sovrapposizione" non veniva preservato tra le richieste
- **Causa:** Mancava hidden input `show_overlay` nel `quizFilterForm`
- **Soluzione:** Aggiunto `<?php if ($showOverlayRadar): ?><input type="hidden" name="show_overlay" value="1"><?php endif; ?>`
- **File:** `student_report.php` (linea 2310)

### Auto-Submit Quiz Selection
- **Funzionalità:** Quando l'utente seleziona un quiz:
  1. Auto-attiva tutte le opzioni di visualizzazione (Dual Radar, Gap, Spunti, Coach, Overlay)
  2. Auto-seleziona il settore nel dropdown
  3. Submit automatico dopo 800ms di debounce
- **Logica:** Solo alla prima selezione (`!previouslyHadSelection`)
- **Hidden inputs:** Crea dinamicamente hidden inputs per preservare stato checkbox
- **File:** `student_report.php` (linee 2506-2605)

### Fix Valutazione Coach - Settore GEN
- **Problema:** Errore "Settore non valido" quando si creava valutazione per GENERICO
- **Causa:** 'GEN' mancante dalla lista `$validSectors`
- **Soluzione:** Aggiunto 'GEN' all'array dei settori validi
- **File:** `coach_evaluation.php`

### Fix Race Condition Salvataggio Ratings
- **Problema:** Ratings non salvati quando si cliccava "Completa"
- **Causa:** Navigazione alla pagina di conferma prima che AJAX completasse
- **Soluzione:**
  - Cambiato link "Completa" da `<a>` a `<button>`
  - Nuova funzione `saveAndComplete()` che:
    1. Mostra messaggio "Salvando..."
    2. Chiama `saveAllRatings()` con callback
    3. Solo dopo il successo, naviga alla pagina di conferma
- **File:** `coach_evaluation.php`

---

## 12 Febbraio 2026

### Fix Mapping Competenze - Tutti i Settori
- **Problema:** Competenze con idnumber numerico (06, 06-01, ecc.) non venivano riconosciute
- **Causa:** La funzione `normalize_sector_name()` non gestiva i codici numerici
- **Soluzione:** Aggiunto mapping completo per tutti i codici numerici:
  - `01`, `01-01`...`01-14` → AUTOMOBILE
  - `02`, `02-01`...`02-11` → CHIMFARM
  - `03`, `03-01`...`03-08` → ELETTRICITA
  - `04`, `04-01`...`04-08` → AUTOMAZIONE
  - `05`, `05-01`...`05-08` → LOGISTICA
  - `06`, `06-01`...`06-13` → MECCANICA
  - `07`, `07-01`...`07-10` → METALCOSTRUZIONE
- **Verifica:** 591/591 competenze mappate correttamente ✅
- **File:** `area_mapping.php`

### Fix Selezione Valutazione Coach
- **Problema:** Student Report mostrava 0/0 competenze anche con valutazioni esistenti
- **Causa:** Il sistema prendeva la valutazione più recente (vuota) invece di quella con ratings
- **Soluzione:**
  - Modificata logica per scegliere valutazione con più ratings
  - `get_radar_data()` ora accetta parametro `evaluationid` opzionale
  - Cerca automaticamente valutazioni con ratings effettivi
- **File:** `student_report.php`, `classes/coach_evaluation_manager.php`
- **Version:** v2.6.4 (2026021201)

### Script Diagnostici
- **diagnose_all_sectors.php** - Verifica completa tutti i 7 settori + GENERICO
  - Test `normalize_sector_name()` con 29 casi
  - Verifica mapping competenze testuali e numeriche
  - Verifica estrazione aree per ogni settore
- **diagnose_meccanica.php** - Diagnosi specifica settore MECCANICA
- **diagnose_coach_eval.php** - Debug valutazioni coach per utente/settore
- **test_coach_meccanica.php** - Test rapido valutazione coach MECCANICA

### Riepilogo Settori Verificati
| Settore | Competenze | Aree | Status |
|---------|------------|------|--------|
| AUTOMOBILE | 101 | 14 | ✅ |
| CHIMFARM | 95 | 11 | ✅ |
| ELETTRICITA | 93 | 8 | ✅ |
| AUTOMAZIONE | 81 | 8 | ✅ |
| LOGISTICA | 47 | 8 | ✅ |
| MECCANICA | 86 | 13 | ✅ |
| METALCOSTRUZIONE | 88 | 10 | ✅ |
| GENERICO (FTM_GEN) | 35 | 7 | ✅ |

### Test Suite - Sector Mapping Validator (NUOVO)
- **Nuovo agente di test:** `sector_mapping_validator.php`
- **21 test totali, 100% pass rate** ✅
- **Test funzioni:**
  - SECT001-SECT005: `normalize_sector_name()` (codici numerici, pattern XX-YY, alias, accenti)
  - SECT006-SECT007: `extract_sector_from_idnumber()` (testuali e numerici)
  - SECT008: `get_area_info()` per tutti i settori
- **Test database:**
  - SECT009-SECT015: Verifica mapping per ogni settore (AUTOMOBILE, CHIMFARM, ecc.)
  - SECT016: Framework GENERICO (FTM_GEN)
  - SECT017: Nessuna competenza con settore UNKNOWN
- **Test Coach Evaluation:**
  - COACH001: `get_rating_stats()` funziona correttamente
  - COACH002: `get_radar_data()` funziona correttamente
  - COACH003: Selezione valutazioni con ratings
  - COACH004: Valutazioni vuote non selezionate
- **File:** `local/ftm_testsuite/classes/agents/sector_mapping_validator.php`

### Fix Variabili Globali area_mapping.php
- **Problema:** TypeError su `in_array()` quando file incluso da funzioni
- **Causa:** Variabili definite nello scope locale invece che globale
- **Soluzione:** Aggiunto `global` prima delle definizioni:
  - `global $AREA_NAMES;`
  - `global $LETTER_BASED_SECTORS;`
  - `global $CODE_BASED_SECTORS;`
  - `global $SECTOR_DISPLAY_NAMES;`
- **File:** `area_mapping.php`

### Pulizia Valutazioni Coach Vuote
- **Script:** `cleanup_empty_evaluations.php`
- **Funzionalità:**
  - Trova valutazioni senza ratings
  - Mostra statistiche (totali, con ratings, vuote)
  - Elenco dettagliato con studente, settore, coach, status
  - Conferma prima dell'eliminazione
- **Protezioni:** require_capability + sesskey
- **Risultato:** Eliminate valutazioni vuote che causavano il bug 0/0

---

## 11 Febbraio 2026

### Grafico Overlay - Rilevamento (Quiz + Lab)
- **Modifica principale:** "Quiz" rinominato in "🔍 Rilevamento"
- **Rilevamento:** Combina Quiz + Laboratorio (media se entrambi presenti)
- **Lab separato:** Nascosto di default, mostrabile con toggle "🔧 Lab (separato)"
- **Contrasti migliorati:** Colori aggiornati per accessibilità
  - Formatore: #0066cc (blu)
  - Header card: gradiente blu
- **File:** `student_report.php`

### Tabella Comparativa Editabile
- **Funzionalità:** Valori Rilevamento, Auto e Coach modificabili dal formatore
- **Caratteristiche:**
  - Badge cliccabile con input numerico (0-100%)
  - Indicatore ✏️ per valori modificati
  - Pulsante ↩️ per ripristinare valore calcolato
  - Storico modifiche consultabile
  - Ricalcolo automatico Media e Gap Max dopo modifica
- **Audit trail:** Chi ha modificato, quando (data/ora), valore originale
- **File:** `student_report.php`, `ajax_save_final_rating.php`

### Sistema Ponderazione - Fase 1
- **Sezione:** "⚖️ Configurazione Ponderazione Valutazioni" (collassabile)
- **Pesi Globali:** Applica a tutte le aree con un click
- **Pesi per Area:** Tabella con 4 input (Quiz, Auto, Lab, Coach) per ogni area
- **Default:** Tutti i pesi a 100%
- **Salvataggio AJAX:** Immediato con feedback
- **Reset:** Pulsante per tornare tutti a 100%
- **Database:** Tabella `local_compman_weights`
- **File:**
  - `student_report.php` - UI configurazione
  - `ajax_save_weights.php` - Endpoint AJAX
  - `db/install.xml`, `db/upgrade.php` - Schema
  - `version.php` - v2.6.0 (2026021101)

### Valutazioni Finali Editabili (IN REVISIONE)
- **Stato:** Sezione nascosta con codice sblocco (6807) - in attesa di approvazione
- **Sblocco:** Input password nel report, codice **6807**
- **Database:**
  - Tabella `local_compman_final_ratings`: Valori manuali
  - Tabella `local_compman_final_history`: Storico modifiche

### Gestione Settori da Coach
- **Funzionalità:** Coach può assegnare settori primario/secondario/terziario allo studente
- **Ubicazione:** Sezione collassabile nel Student Report (dopo box valutazione)
- **Caratteristiche:**
  - Visualizzazione settori attuali con badge colorati
  - 3 dropdown: Primario 🥇, Secondario 🥈, Terziario 🥉
  - Validazione: settori devono essere diversi tra loro
  - Salvataggio AJAX con feedback immediato
  - Solo visibile a chi ha capability `managesectors`
- **File:**
  - `classes/sector_manager.php` - Nuovi metodi: `set_student_sectors()`, `add_sector()`, `remove_sector()`, `get_student_sectors_ranked()`
  - `ajax_manage_sectors.php` - Endpoint AJAX per gestione settori
  - `student_report.php` - UI sezione gestione settori
  - `version.php` - v2.6.1 (2026021102)

### Coach Dashboard - Multi-Settore
- **Funzionalità:** Dashboard coach mostra tutti i settori assegnati (non solo primario)
- **Visualizzazione:** Badge con medaglie 🥇🥈🥉 disposti in colonna
- **Colori badge:**
  - Primario: viola/blu (originale)
  - Secondario: grigio
  - Terziario: marrone/bronzo
- **File:**
  - `local/coachmanager/classes/dashboard_helper.php` - Metodo `get_student_all_sectors()`
  - `local/coachmanager/coach_dashboard_v2.php` - Funzione `render_sector_badges()` + CSS
  - `local/coachmanager/version.php` - v2.1.2 (2026021102)

### Filtro Multi-Settore Migliorato
- **Dropdown settore:** Mostra medaglie 🥇🥈🥉 in base al ranking
- **Indicatore fonte:** "(X quiz)" per settori con quiz, "(assegnato)" per settori manuali
- **Avviso:** Messaggio info quando settore selezionato non ha quiz completati
- **File:**
  - `student_report.php` - Dropdown migliorato
  - `version.php` - v2.6.2 (2026021103)

### Coach Evaluation Multi-Settore
- **Funzionalità:** Coach può valutare TUTTI i settori assegnati allo studente (non solo primario)
- **Selettore settore:** Dropdown nell'header della pagina valutazione
- **Indicatori:**
  - 🥇 Settore primario
  - 🥈 Settore secondario
  - 🥉 Settore terziario
  - 📊 Settore rilevato da quiz
- **Status evaluation:** Mostra se esiste già valutazione per il settore (📝 draft, ✅ completed, 🔒 signed)
- **Parametro sector:** Ora opzionale, usa primario se non specificato
- **File:**
  - `coach_evaluation.php` - Selettore settore + logica multi-settore
  - `version.php` - v2.6.3 (2026021104)

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
