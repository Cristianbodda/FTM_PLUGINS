# SISTEMA CPURC - DETTAGLI TECNICI (26/02/2026)

## Panoramica
Sistema completo per la gestione degli studenti CPURC (Centro Professionale URC) con import da CSV, gestione anagrafica, assegnazione coach/settori e generazione report Word.

## File Principali
```
local/ftm_cpurc/
├── index.php                 # Dashboard segreteria con filtri
├── import_production.php     # Import produzione (admin, Excel, dedup, anteprima)
├── student_card.php          # Scheda studente (4 tab)
├── report.php                # Compilazione report Word
├── import.php                # Import CSV CPURC
├── export_excel.php          # Export Excel completo
├── export_word.php           # Export singolo Word
├── export_word_bulk.php      # Export ZIP tutti i Word
├── ajax_assign_coach.php     # AJAX assegnazione coach
├── ajax_save_sectors.php     # AJAX salvataggio settori
├── ajax_delete_sector.php    # AJAX eliminazione settore
├── classes/
│   ├── cpurc_manager.php     # Manager principale
│   ├── csv_importer.php      # Parser CSV/Excel
│   ├── user_manager.php      # Creazione utenti, iscrizione, gruppi, settori
│   ├── word_exporter.php     # Generatore Word
│   └── profession_mapper.php # Mapping professione->settore
└── db/
    └── install.xml           # Schema database
```

## Tabelle Database

### local_ftm_cpurc_students
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | BIGINT | Primary key |
| userid | BIGINT | FK -> mdl_user.id |
| personal_number | VARCHAR(50) | Numero personale URC |
| urc_office | VARCHAR(100) | Ufficio URC di riferimento |
| urc_consultant | VARCHAR(200) | Consulente URC |
| date_start | BIGINT | Data inizio percorso |
| date_end_planned | BIGINT | Data fine pianificata |
| date_end_actual | BIGINT | Data fine effettiva |
| sector_detected | VARCHAR(50) | Settore rilevato |
| last_profession | VARCHAR(200) | Ultima professione |
| status | VARCHAR(20) | Stato (active, closed) |
| absence_* | INT | Campi assenze (x, o, a, b, c, d, e, f, g, h, i) |
| stage_* | Vari | Campi stage (company, contact, dates) |

### local_ftm_cpurc_reports
| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | BIGINT | Primary key |
| studentid | BIGINT | FK -> local_ftm_cpurc_students.id |
| coachid | BIGINT | FK -> mdl_user.id (coach) |
| status | VARCHAR(20) | draft, final, sent |
| narrative_* | TEXT | Campi narrativi (comportamento, competenze, etc.) |
| conclusion_* | Vari | Campi conclusione |

## Dashboard Segreteria (index.php)

### Filtri Disponibili
| Filtro | Tipo | Descrizione |
|--------|------|-------------|
| search | Text | Ricerca nome/cognome/email |
| urc | Select | Ufficio URC |
| sector | Select | Settore |
| status | Select | Attivi/Chiusi |
| reportstatus | Select | Nessuno/Bozza/Completo |
| coach | Select | Coach assegnato |
| datefrom | Date | Data inizio da |
| dateto | Date | Data inizio a |
| groupcolor | Select | Gruppo colore (giallo/grigio/rosso/marrone/viola) |

### Colonne Tabella
- Nome studente (link a student_card)
- URC, Settore (badge), Settimana (1-6+), Coach (dropdown), Stato Report (badge), Azioni

## Student Card (student_card.php)

### Tab: Anagrafica, Percorso, Assenze, Stage

### Bottone Percorso Studente (26/02/2026)
- Link diretto a `student_program.php?userid=X&groupid=Y` nell'header
- Condizione: visibile solo se studente ha gruppo scheduler assegnato (`local_ftm_group_members`)
- Stile: arancione `cpurc-btn-percorso` (`#f59e0b`)
- Query groupid eseguita all'init della pagina

### Coach Assignment
- Dropdown con coach da `local_ftm_coaches` (scheduler)
- Fallback a ruolo editingteacher se tabella non presente
- Salvataggio in `local_student_coaching` (condivisa)

### Multi-Settore
- **Primario:** Determina quiz e autovalutazione assegnati
- **Secondario/Terziario:** Suggerimenti per il coach
- Salvataggio in `local_student_sectors` (condivisa)

## Report Word (report.php)

### Campi Narrativi
- `narrative_behavior`, `narrative_technical`, `narrative_transversal`, `narrative_recommendations`, `narrative_conclusion`

## Import Produzione (import_production.php)

### Accesso
- Solo `is_siteadmin()` - bottone rosso nella dashboard CPURC

### Flusso
1. Upload Excel (.xlsx) con drag & drop
2. Parsing con `csv_importer::parse_file()`
3. Deduplicazione per email (tiene `date_start` piu' recente)
4. Anteprima tabella con match Moodle, coach, gruppo, settore, warning
5. Dati salvati in `$SESSION` (non hidden field)
6. Conferma import con sesskey

### Logica per studente
- Cerca utente Moodle per email (NON crea utenti nuovi)
- Salva record CPURC (insert/update)
- Rileva settore con `profession_mapper::detect_sector()`
- **Solo studenti "Aperto":** iscrizione corso R.comp, assegnazione gruppo colore, coach
- **Studenti "Interrotto"/"Annullato":** status=closed, no gruppo/coach

### Mapping Formatore -> Coach
Match per nome/cognome (fullmatch + fallback cognome) contro `local_ftm_coaches`

### Mapping Data Inizio -> Gruppo Colore
Mapping esplicito (non modulo KW): 19.01=giallo, 02.02=grigio, 16.02=rosso, 02.03=marrone, 16.03=viola, 30.03=giallo, 13.04=grigio, 27.04=rosso

### Corso R.comp
Cercato per nome (`find_course('R.comp')`) - non hardcoded. Se non trovato, iscrizione saltata con warning.

## Capabilities
| Capability | Descrizione |
|------------|-------------|
| local/ftm_cpurc:view | Visualizza dashboard e student card |
| local/ftm_cpurc:edit | Modifica dati, assegna coach/settori |
| local/ftm_cpurc:import | Importa CSV |
| local/ftm_cpurc:generatereport | Genera e esporta report Word |
