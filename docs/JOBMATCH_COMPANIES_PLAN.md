# JobMatch + Companies + Autocandidature — Piano Architetturale
**Creato:** 20 maggio 2026  
**Contesto:** Unificazione ftm_jobsearch + jobmatchagent con database aziende TI e sistema autocandidature

---

## DECISIONE ARCHITETTURALE

**NON si fondono i plugin.** Si rafforzano come duo:
- `local_ftm_jobsearch` = motore scraper job board (rimane quasi invariato)
- `local_jobmatchagent` = cervello + interfaccia coach (riceve tutto il nuovo)

---

## I DUE FLUSSI

### Flusso A — Offerte di lavoro (automatico)
```
Cron 24h → ftm_jobsearch scrapa job.ch/randstad/arbeit.swiss/jobroom
→ jobmatch importa offerte
→ AI matcha con profilo studente (settore + competenze Bloom)
→ Coach rivede match
→ Coach pubblica allo studente (solo se student_view attivata)
→ Studente vede offerte, segna interesse
→ Sistema apprende dai feedback (local_jobmatch_learning)
```

### Flusso B — Autocandidature mirate (manuale, coach-driven)
```
Coach apre database aziende TI → filtra per settore studente
→ Seleziona 3-5 aziende target
→ Aggiunge nota AI ("Marco forte su CNC, punta su questo")
→ Click "Genera lettera" → JobAIDA apre con dati pre-caricati
→ COACH fa la lettera (NON lo studente, per ora)
→ Coach approva + conferma invio
→ Lettera appare in lista "lettere inviate" dello studente
→ [Se CI attivo] → auto-log in local_ftm_sip_search_entries (method_letter=1, area=targeted_applications)
→ Tracking: inviata → risposta → colloquio → assunto/rifiutato
```

**REGOLA FONDAMENTALE:** Il coach ha controllo totale. Lo studente vede solo se il coach attiva la vista.

---

## DISCOVERY IMPORTANTE: CI già traccia le ricerche

Da verifica del codice (20/05/2026):

### local_ftm_sip_search_entries (campi chiave)
- `enrollmentid` → solo studenti CI attivo (10 settimane)
- `area_key` → es. 'targeted_applications', 'mandatory_searches'
- `sip_week` → settimana 1-10
- `company_name`, `companyid` (FK local_ftm_sip_companies), `company_address`, `company_email`
- `contact_person`, `position`
- **`method_letter`** (bool 0/1) → contatto via lettera/email ← questo è dove finiscono le autocandidature
- `method_person`, `method_phone`
- `result` → pending / interview / rejected / no_response
- `addedby` → chi ha inserito

### local_ftm_sip_companies (già esiste in SIP)
- Registro organico aziende contattate dagli studenti
- Creato via `find_or_create_company()` in sip_manager
- **DIVERSO** da `local_jobmatch_ticino_companies` (vedi sotto)

### Studenti 6 settimane (non CI)
**Confermato: ZERO tracking.** Non sono in `local_ftm_sip_enrollments` → non hanno search_entries.
Il sistema autocandidature funziona per TUTTI gli studenti (con e senza CI).
Il link CI è SOLO per chi ha CI attivo.

---

## NUOVE TABELLE IN local_jobmatchagent

### local_jobmatch_ticino_companies
```sql
id, nome, indirizzo, cap, localita
anno_primo_contatto (INT, dalla colonna Anno del CSV)
settore_ftm (AUTOMOBILE/AUTOMAZIONE/CHIMFARM/ELETTRICITA/LOGISTICA/MECCANICA/METALCOSTRUZIONE/GENERICO)
settore_raw (testo libero per AI)
dimensione (S/M/L/unknown)
website, email, referente, note_interne
source (csv_import / job_board / manual / zefix)
status (active / inactive / unverified)
last_job_board_seen (timestamp ultima offerta vista sui job board)
timecreated, timemodified
```
**DIVERSO da local_ftm_sip_companies** (quello è il ledger contatti, questo è il database curato FTM)

### local_jobmatch_student_targets
```sql
id, userid, company_id (FK ticino_companies), coach_userid
note_per_ai (istruzione coach per la lettera JobAIDA)
status (pending / lettera_generata / inviata / risposta / colloquio / assunto / rifiutato)
jobaida_letter_id (link alla lettera generata, futuro)
data_invio, data_risposta
note_esito
sip_entry_id (FK local_ftm_sip_search_entries — se CI attivo, questo viene popolato)
timecreated, timemodified
```

### Modifica local_jobmatch_student_filters (già esiste)
```
+ student_view_enabled (TINYINT 0/1)
+ activated_by (INT — userid coach/segreteria)
+ activated_at (INT — timestamp)
```

---

## NUOVI FILE IN local_jobmatchagent

| File | Scopo |
|------|-------|
| `companies.php` | Admin/coach: gestione DB aziende TI, import CSV, classifica AI |
| `ajax_import_companies.php` | Processa upload CSV, parsing colonne |
| `ajax_classify_sectors.php` | Batch GPT: classifica settori da nome azienda (batch da 50) |
| `ajax_company_crud.php` | Aggiungi/modifica/disattiva/cerca azienda |
| `student_targets.php` | Coach: seleziona aziende target per studente, genera lettere |
| `ajax_save_target.php` | CRUD targets + auto-log in CI se attivo |
| `ajax_update_target_status.php` | Aggiorna stato autocandidatura (inviata/risposta/ecc.) |
| `ajax_activate_student.php` | Attiva/disattiva vista studente |
| `student_view.php` | Vista studente (solo se coach attiva) |
| `classes/company_manager.php` | Logica business DB aziende, CSV import, AI classification |
| `classes/target_manager.php` | Logica autocandidature, link CI |
| `ajax_discover_company.php` | Scrapa singolo URL azienda + GPT estrazione + genera 2-3 domande coach |
| `ajax_discover_batch.php` | Scrapa pagina directory + batch estrazione aziende + tabella review |
| `db/upgrade.php` | +3 modifiche: 2 nuove tabelle + modifica student_filters |

---

## CRESCITA DATABASE AZIENDE

### Bootstrap (immediato)
1. Import CSV 420 aziende → `local_jobmatch_ticino_companies` con `source=csv_import`, `status=unverified`
2. AI tenta classificazione settore: aziende con nome ambiguo restano `status=unverified` per classificazione manuale coach
3. Interfaccia admin mostra lista "da classificare" con dropdown settore inline

### Classificazione manuale (post-import)
- Colonna status in companies.php con filtro "Non classificate"
- Edit inline settore con dropdown FTM sectors
- Pulsante "Segna come Attiva" dopo classificazione

### Scoperta intelligente da URL (NUOVO)
**Modalità Singola** (companies.php tab "Scopri Azienda"):
- Coach incolla URL sito web aziendale
- Sistema: fetch HTML → GPT estrae nome/indirizzo/settore/descrizione/dimensione
- AI genera 2-3 domande mirate basate sui dati trovati
- Coach risponde → azienda aggiunta con status=active

**Modalità Directory** (companies.php tab "Da Directory"):
- Coach incolla URL pagina directory (CC-Ti, AITI, local.ch, zona industriale)
- Sistema: fetch HTML → GPT estrae TUTTE le aziende elencate
- Tabella batch review con confidence score per settore
- "Approva tutte sicure" (conf >80%) + classificazione manuale per le ambigue
- File: ajax_discover_company.php + ajax_discover_batch.php
- Riusa ai_scraper di ftm_jobsearch (già funzionante)

### Crescita automatica (ongoing)
- Ogni volta che ftm_jobsearch scrapa offerta da azienda ticinese:
  → Controlla se è in DB per nome
  → Se non c'è: aggiunge con `source=job_board`, `status=unverified`
  → Se c'è: aggiorna `last_job_board_seen`

### Verifica nominativi (3 livelli)
1. **AI dal nome**: funziona per "AB Automazioni SA", "Elettro Celio" → immediato
2. **Note coach**: campo `note_interne` per arricchimento manuale ("fa solo stampaggio CNC")
3. **Futuro**: scraping pagina "Chi siamo" con GPT per descrizione reale

### Status aziende
- `unverified` = aggiunta da job_board o CSV senza controllo umano
- `active` = FTM ha contatto diretto (o verificata da admin)
- `inactive` = non più operativa

---

## ATTIVAZIONE STUDENTE

```
Default: studente NON vede nulla

Coach → scheda studente → "Attiva ricerca lavoro"
    ↓
Sceglie:
  ☑ Offerte automatiche (Flusso A — cron + AI matching)
  ☑ Vista autocandidature (Flusso B — coach seleziona aziende)

student_view_enabled = 1, activated_by = coachid, activated_at = now()
Studente riceve notifica Moodle
```

---

## INTEGRAZIONE CI (solo per studenti con CI attivo)

Quando coach conferma lettera inviata in student_targets:
```php
if (sip_enrollment attivo) {
    sip_manager::create_search_entry($enrollmentid, 'targeted_applications', $current_week, [
        'company_name' => $company->nome,
        'company_address' => $company->indirizzo . ', ' . $company->cap . ' ' . $company->localita,
        'method_letter' => 1,
        'result' => 'pending',
        'notes' => 'Autocandidatura generata via JobAIDA/JobMatch'
    ], $coachid);
    // Aggiorna sip_entry_id in student_targets
}
```

### Studenti 6 settimane (non CI): ZERO integrazione CI
- Tutto il tracking è SOLO in `local_jobmatch_student_targets`
- Il coach vede la lista lettere generate/inviate nella pagina student_targets

---

## AGENZIE INTERINALI (Manpower, Randstad, Adecco, Indeed)
**Non entrano nel DB aziende TI.**
Sono canali di ricerca, gestiti in CI come `search_channels`.
In jobmatch: trattate come source dei job board (already handled).

---

## COERENZA GRAFICA FTM
- Palette: `--primary: #0066cc`, rosso FTM `#dc3545`, border-radius 8px cards
- Tab orizzontali con localStorage (come coachmanager)
- Stessa struttura header/footer Moodle
- Pattern modali identico a coach_dashboard_v2
- Badge settore colorati: MECCANICA=blu, ELETTRICITA=giallo, ecc. (allineati a selfassessment)

---

## ORDINE DI SVILUPPO (da implementare)

- [ ] FASE 1: DB upgrade.php + install.xml (3 modifiche tabelle)
- [ ] FASE 2: company_manager.php (class backend)
- [ ] FASE 3: companies.php (UI admin/coach)
- [ ] FASE 4: ajax_import_companies.php + ajax_classify_sectors.php
- [ ] FASE 5: ajax_company_crud.php
- [ ] FASE 6: target_manager.php (class backend)
- [ ] FASE 7: student_targets.php (UI coach per studente)
- [ ] FASE 8: ajax_save_target.php + ajax_update_target_status.php (con link CI)
- [ ] FASE 9: ajax_activate_student.php + student_view.php
- [ ] FASE 10: Hook in ftm_jobsearch per auto-add aziende TI
- [ ] FASE 11: version.php bump + lang strings IT/EN

---

## FILE DA NON TOCCARE
- `local/competencymanager/student_report.php` — avallato dai coach, NON modificare
- `local/ftm_sip/` — solo lettura (sip_manager::create_search_entry) per il log CI
- `local/jobaida/` — aperto via URL con parametri, nessuna modifica

---

## NOTE TECNICHE
- `PARAM_ALPHANUMEXT` tronca accenti → settore ELETTRICITA (senza accento) OK
- Framework produzione: id=14 (NON 9 come su test)
- Tabelle gruppi: `local_ftm_groups` + `local_ftm_group_members`
- API key OpenAI condivisa tra jobmatch, jobsearch, jobaida (già implementato)
- FTP: stessa struttura relativa locale → server
