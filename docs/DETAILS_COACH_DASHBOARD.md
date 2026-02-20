# COACH DASHBOARD V2 - DETTAGLI TECNICI (20/02/2026)

## Viste Disponibili

| Vista | Descrizione | Uso Consigliato |
|-------|-------------|-----------------|
| **Classica** | Vista originale completa | Default, tutti i dettagli |
| **Compatta** | Card piccole, info essenziali | Molti studenti, panoramica rapida |
| **Standard** | Bilanciata tra info e spazio | Uso quotidiano |
| **Dettagliata** | Massimo dettaglio, timeline espansa | Analisi approfondita |

## Livelli Zoom

| Livello | Scala | Target |
|---------|-------|--------|
| A- | 90% | Schermi piccoli |
| A | 100% | Default |
| A+ | 120% | Leggibilita migliorata |
| A++ | 140% | Utenti con difficolta visive |

## Preferenze Salvate
- `ftm_coach_view`: Vista selezionata (classica, compatta, standard, dettagliata)
- `ftm_coach_zoom`: Livello zoom (90, 100, 120, 140)

## Note Coach
- Tabella: `local_coachmanager_notes`
- Campi: `id`, `studentid`, `coachid`, `notes`, `timecreated`, `timemodified`
- Visibilita: Coach proprietario + Segreteria (capability `local/coachmanager:viewallnotes`)

## Export Word
- File: `export_word.php`
- Libreria: PHPWord (se disponibile) oppure HTML Word-compatible (fallback)
- Contenuto: Info studente, progressi, timeline, note coach

## Navigazione dentro Corsi (19/02/2026)
- Funzione: `local_coachmanager_extend_navigation_course()` in `lib.php`
- Aggiunge link "Coach Dashboard" nella sidebar di navigazione del corso
- Capability: `local/coachmanager:view`
- Parametro: `courseid` passato automaticamente

## Reports V2 - Link Student Report (19/02/2026)
- Card "Autovalutazione" apre Student Report in nuova tab (`window.open`)
- Carica settore primario da `local_student_sectors`
- Parametri preimpostati: `viz_configured=1`, tutte le opzioni visualizzazione attive
- Anchor: `#overlay-radar-section` per scroll al grafico overlay

## Week Planner Modal (20/02/2026)

### Funzionalita
Il coach clicca su una settimana (Sett. 1-6) nella timeline e si apre un modale per gestire le attivita assegnate.

### Modale
- **Attivita Attuali:** Lista con badge tipo (ATELIER/TEST/LAB/ESTERNA) e bottone rimuovi
- **Aggiungi Attivita:** 4 sezioni con dropdown:
  - Atelier: catalogo → date con posti disponibili → Iscrivi
  - Test Teoria: quiz disponibili → Assegna
  - Laboratorio: lab disponibili → Assegna
  - Attivita Esterna: campo testo libero → Aggiungi

### Supporto per tutte le 4 viste
| Vista | Interazione |
|-------|-------------|
| Standard | `timeline-week` cliccabile con hover scale |
| Dettagliata | `timeline-week` cliccabile con hover scale |
| Classica | Riga di 6 bottoni S1-S6 dopo status-row |
| Compatta | 6 micro-badges (18x18px) nella colonna Settimana |

### AJAX Endpoint
- File: `ajax_week_planner.php`
- Azioni: `getplan`, `assignatelier`, `assignactivity`, `removeactivity`, `getatelierdates`

### Backend (dashboard_helper.php)
- `get_week_plan($userid, $week)` - Piano settimana con ateliers/tests/labs/external
- `get_available_activities_for_week($week)` - Attivita disponibili per dropdown
- `assign_week_activity($userid, $week, $type, $data, $assignedby)` - Inserisce in student_program
- `remove_week_activity($programid, $userid)` - Rimuove da student_program

### Data Model
- Ateliers → `local_ftm_enrollments` + `local_ftm_activities` (capacita condivisa)
- Test/Lab/External → `local_ftm_student_program` (calendario individuale)
- Rimozione atelier → `status = 'cancelled'`
- Rimozione test/lab/ext → DELETE da student_program
