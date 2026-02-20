# COACH DASHBOARD V2 - DETTAGLI TECNICI (19/02/2026)

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
