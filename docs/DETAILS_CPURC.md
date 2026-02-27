# SISTEMA CPURC - DETTAGLI TECNICI (27/02/2026)

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
├── ajax_import.php           # AJAX endpoint import (preview/import + dedup)
├── download_credentials.php  # Export Excel credenziali LADI (session/db)
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

## Export Credenziali LADI (27/02/2026)

### File: `download_credentials.php`
Due modalita di funzionamento:
- **`source=session`**: Dopo un import, legge da `$SESSION->cpurc_credentials` (impostato da `ajax_import.php`)
- **`source=db`**: Dalla dashboard, usa `cpurc_manager::get_students($filters)` con gli stessi filtri attivi

### Formato Excel
Layout LADI template con PhpSpreadsheet:
- **Row 1:** "Inizio" + data (piu recente date_start)
- **Row 3:** Headers (A=#, B=Nome, C=Cognome, D=Localita, E=E-mail, F=N.Personale, G=Formatore, H=Username, I=Password, J=Gruppo)
- **Colori:** Header grigio scuro (#595959), Input azzurro (#D6E4F0), F3M Academy arancio (#F2DCAB)
- **Username:** cognome3+nome3 (prime 3 lettere primo cognome + prime 3 primo nome, no accenti/apostrofi)
- **Password:** `123` + Nome (ucfirst, primo nome solo) + `*`
- **Gruppo:** `KW XX gruppo colore` (calcolato da date_start)

### Bottone Dashboard
Nella header buttons di `index.php`, arancione (#e67e22), visibile con capability `import`.
Passa tutti i filtri correnti (search, urc, sector, status, reportstatus, coach, datefrom, dateto, groupcolor).

## Fix User Creation & Enrollment (27/02/2026)

### user_manager::create_or_find_user()
- Usa `user_create_user()` API Moodle (non `$DB->insert_record('user')`)
- Eventi e cache gestiti correttamente
- Lookup email case-insensitive con `LOWER()`
- Update con `user_update_user()` se `update_existing=true`

### user_manager::enrol_in_course()
- Usa `enrol_get_plugin('manual')->enrol_user()` (non manipolazione diretta tabelle)
- Crea enrol instance con `add_instance()` se non presente
- Check `is_enrolled()` prima di iscrivere (idempotente)

### user_manager::generate_username()
- Formato: `cognome3` + `nome3` (es. "Brunner Ziggy" -> "bruzig")
- `extract_first_name()` gestisce nomi composti (De Rossi -> "der", Von Moos -> "vonmoo")
- Prefissi: de, di, da, la, le, lo, li, del, dal, van, von, el, al
- Dedup: `username + counter` se gia esistente

### Import Deduplication (ajax_import.php)
- Per email (case-insensitive), tiene la row con `date_start` piu recente
- Credenziali salvate in `$SESSION` per download Excel successivo

### Coach Auto-Assignment (csv_importer.php)
- `find_coach_by_trainer()`: match nome formatore CSV -> `local_ftm_coaches` + fallback user table
- Coach assegnato durante import con `cpurc_manager::assign_coach()` usando `date_start` reale dal CSV

## Capabilities
| Capability | Descrizione |
|------------|-------------|
| local/ftm_cpurc:view | Visualizza dashboard e student card |
| local/ftm_cpurc:edit | Modifica dati, assegna coach/settori |
| local/ftm_cpurc:import | Importa CSV + Export Credenziali LADI |
| local/ftm_cpurc:generatereport | Genera e esporta report Word |
