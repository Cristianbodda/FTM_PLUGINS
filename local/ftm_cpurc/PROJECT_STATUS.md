# FTM CPURC - Project Status

**Plugin:** local_ftm_cpurc
**Versione:** 1.0.0
**Stato:** COMPLETATO
**Ultimo aggiornamento:** 24 Gennaio 2026

---

## Panoramica

Sistema completo per la gestione degli studenti CPURC (Centro Professionale URC) con:
- Import dati da CSV
- Gestione anagrafica e percorso
- Assegnazione coach e settori
- Generazione report Word

---

## Funzionalita Completate

### 1. Import CSV (import.php)
- [x] Upload file CSV da sistema CPURC
- [x] Mapping automatico campi CSV -> database
- [x] Validazione dati
- [x] Creazione utenti Moodle se non esistenti
- [x] Report import con errori/successi

### 2. Dashboard Segreteria (index.php)
- [x] Lista studenti CPURC
- [x] Filtro per ricerca (nome, cognome, email)
- [x] Filtro per URC office
- [x] Filtro per settore
- [x] Filtro per stato report (Nessuno, Bozza, Completo)
- [x] Filtro per coach assegnato
- [x] Colonna coach editabile (dropdown AJAX)
- [x] Badge settore colorato
- [x] Badge stato report
- [x] Indicatore settimana (1-6+)
- [x] Pulsanti azione (Card, Report, Word)
- [x] Export Excel
- [x] Export Word ZIP (bulk)

### 3. Student Card (student_card.php)
- [x] Tab Anagrafica (dati personali, contatti, indirizzo, amministrativi)
- [x] Tab Percorso (URC, FTM, professione, settore)
- [x] Tab Assenze (riepilogo X, O, A-I, totale)
- [x] Tab Stage (azienda, contatto, date, conclusione)
- [x] Sezione Coach Assignment con dropdown e AJAX save
- [x] Sezione Multi-Settore (Primario, Secondario, Terziario)
- [x] Pulsanti X per eliminare settori
- [x] Settori rilevati automaticamente da quiz

### 4. Report Word (report.php)
- [x] Interfaccia compilazione stile documento
- [x] Campi narrativi (comportamento, competenze, raccomandazioni)
- [x] Salvataggio automatico bozza
- [x] Finalizzazione report
- [x] Generazione Word professionale

### 5. Export (export_*.php)
- [x] Export Excel con tutti i campi (30+ colonne)
- [x] Export Word singolo studente
- [x] Export Word bulk (ZIP con tutti i report)
- [x] Filtro per stato report (draft/final)

### 6. AJAX Endpoints
- [x] ajax_assign_coach.php - Assegnazione coach
- [x] ajax_save_sectors.php - Salvataggio settori (primary, secondary, tertiary)
- [x] ajax_delete_sector.php - Eliminazione singolo settore

---

## Integrazione con Altri Plugin

### Tabelle Condivise
| Tabella | Owner | Uso in CPURC |
|---------|-------|--------------|
| local_student_coaching | competencymanager | Assegnazione coach |
| local_student_sectors | competencymanager | Multi-settore |
| local_ftm_coaches | ftm_scheduler | Lista coach disponibili |

### Sincronizzazione
- Coach assignment sincronizzato con coachmanager
- Settore primario sincronizzato con selfassessment (filtro competenze)
- Profession mapper usa stessi alias di competencymanager

---

## Tabelle Database

### local_ftm_cpurc_students
Dati anagrafici e percorso studente CPURC.

| Campo | Tipo | Note |
|-------|------|------|
| id | BIGINT | PK |
| userid | BIGINT | FK -> mdl_user |
| personal_number | VARCHAR(50) | Numero URC |
| urc_office | VARCHAR(100) | Ufficio URC |
| urc_consultant | VARCHAR(200) | Consulente |
| date_start | BIGINT | Inizio percorso |
| date_end_planned | BIGINT | Fine prevista |
| date_end_actual | BIGINT | Fine effettiva |
| sector_detected | VARCHAR(50) | Settore |
| last_profession | VARCHAR(200) | Professione |
| status | VARCHAR(20) | active/closed |
| absence_* | INT | Campi assenze |
| stage_* | Vari | Campi stage |

### local_ftm_cpurc_reports
Report Word per ogni studente.

| Campo | Tipo | Note |
|-------|------|------|
| id | BIGINT | PK |
| studentid | BIGINT | FK -> students |
| coachid | BIGINT | FK -> mdl_user |
| status | VARCHAR(20) | draft/final/sent |
| narrative_* | TEXT | Sezioni narrative |
| conclusion_* | Vari | Conclusione |

### local_ftm_cpurc_imports
Storico import CSV.

---

## Capabilities

| Capability | Ruolo | Descrizione |
|------------|-------|-------------|
| local/ftm_cpurc:view | Coach, Segreteria | Visualizza dashboard |
| local/ftm_cpurc:edit | Segreteria | Modifica dati |
| local/ftm_cpurc:import | Segreteria | Import CSV |
| local/ftm_cpurc:generatereport | Coach, Segreteria | Report Word |

---

## URL Principali

| Pagina | URL |
|--------|-----|
| Dashboard | /local/ftm_cpurc/index.php |
| Student Card | /local/ftm_cpurc/student_card.php?id=X |
| Report | /local/ftm_cpurc/report.php?id=X |
| Import CSV | /local/ftm_cpurc/import.php |
| Export Excel | /local/ftm_cpurc/export_excel.php |
| Export Word Bulk | /local/ftm_cpurc/export_word_bulk.php |

---

## Changelog

### v1.0.0 (24/01/2026)
- Release iniziale completa
- Dashboard segreteria con filtri
- Student card con 4 tab
- Coach assignment AJAX
- Multi-settore (primary, secondary, tertiary)
- Export Excel e Word bulk
- Integrazione con competencymanager e selfassessment

---

## Note Tecniche

### Settore Primario
Il settore primario determina quali quiz e autovalutazioni vengono assegnati allo studente. L'observer in selfassessment filtra le competenze per assegnare solo quelle del settore primario.

### Coach Assignment
L'assegnazione coach usa la tabella condivisa `local_student_coaching` per garantire sincronizzazione tra tutti i plugin FTM.

### Profession Mapper
La classe `profession_mapper.php` contiene il mapping automatico professione -> settore con supporto per alias e normalizzazione caratteri accentati.
