# FTM Ecosystem Inventory
**Generato:** 2026-05-07  
**Cartella:** `C:\Users\cristian.bodda\Desktop\FTM_PLUGINS_NEW`  
**Totale plugin:** 18 (13 local_ + 1 block_ + 1 qbank_ + 1 ftm_common + 2 ALPHA)

---

## INDICE

| # | Plugin | Versione | Tipo | Stato |
|---|--------|----------|------|-------|
| 1 | [local_coachmanager](#1-local_coachmanager) | v2.5.5 | local | STABLE |
| 2 | [local_competencymanager](#2-local_competencymanager) | v2.9.1 | local | STABLE |
| 3 | [local_competencyreport](#3-local_competencyreport) | v1.0 | local | STABLE |
| 4 | [local_competencyxmlimport](#4-local_competencyxmlimport) | v1.4 | local | STABLE |
| 5 | [local_ftm_ai](#5-local_ftm_ai) | v1.0.0-alpha | local | ALPHA |
| 6 | [local_ftm_common](#6-local_ftm_common) | v1.0.0 | local | STABLE |
| 7 | [local_ftm_cpurc](#7-local_ftm_cpurc) | v1.6.0 | local | BETA |
| 8 | [local_ftm_hub](#8-local_ftm_hub) | v2.0.0 | local | STABLE |
| 9 | [local_ftm_jobsearch](#9-local_ftm_jobsearch) | v1.0.0 | local | ALPHA |
| 10 | [local_ftm_scheduler](#10-local_ftm_scheduler) | v1.2.0 | local | STABLE |
| 11 | [local_ftm_sip](#11-local_ftm_sip) | v3.0.0 | local | BETA |
| 12 | [local_ftm_testsuite](#12-local_ftm_testsuite) | v1.1.0 | local | STABLE |
| 13 | [local_jobaida](#13-local_jobaida) | v1.1.0 | local | BETA |
| 14 | [local_jobmatchagent](#14-local_jobmatchagent) | v0.7.0 | local | ALPHA |
| 15 | [local_labeval](#15-local_labeval) | v2.0.0 | local | STABLE |
| 16 | [local_selfassessment](#16-local_selfassessment) | v1.6.3 | local | STABLE |
| 17 | [block_ftm_tools](#17-block_ftm_tools) | v2.0.0 | block | STABLE |
| 18 | [qbank_competenciesbyquestion](#18-qbank_competenciesbyquestion) | v1.1 | qbank | STABLE |

---

## PLUGIN LOCALI

---

### 1. local_coachmanager

**Percorso:** `local/coachmanager/`  
**Component:** `local_coachmanager`  
**Versione:** `2026042800` | Release: `v2.5.5` | Maturity: STABLE  

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION, opzionale)

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_coachmanager_notes` | Note coach su studenti |
| `local_coachmanager_compare` | Storico confronti valutazioni studenti |
| `local_coachmanager_jobs` | Annunci lavoro (job board) |
| `local_coachmanager_matches` | Risultati matching studente-lavoro |

**Capabilities** (`db/access.php`):
- `local/coachmanager:view`
- `local/coachmanager:edit`
- `local/coachmanager:compare`
- `local/coachmanager:export`
- `local/coachmanager:managejobs`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `coach_dashboard.php` | Dashboard coach v1 (legacy) |
| `coach_dashboard_v2.php` | Dashboard coach v2 (attiva) |
| `coach_student_view.php` | Vista singolo studente |
| `reports_v2.php` | Report studenti v2 |
| `coach_navigation.php` | Navigazione sidebar |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_ftm_group_members`, `local_ftm_groups` (da ftm_scheduler)
- `local_ftm_cpurc_students` (da ftm_cpurc)
- `qbank_competenciesbyquestion`
- `local_student_coaching` (tabella condivisa con competencymanager)

---

### 2. local_competencymanager

**Percorso:** `local/competencymanager/`  
**Component:** `local_competencymanager`  
**Versione:** `2026042901` | Release: `v2.9.1` | Maturity: STABLE  

**Dipendenze dichiarate:**
- `qbank_competenciesbyquestion` (ANY_VERSION)

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_competencymanager_auth` | Autorizzazioni studente a vedere il proprio report |
| `local_competencymanager_log` | Log import competenze |
| `local_student_coaching` | Assegnazione studenti a coach (condivisa) |
| `local_student_sectors` | Settori assegnati agli studenti (condivisa) |
| `local_coach_evaluations` | Header valutazione coach (record padre) |
| `local_coach_eval_ratings` | Valutazioni singole per competenza |
| `local_coach_eval_history` | Storico modifiche valutazioni inline |
| `local_compman_final_ratings` | Valutazioni finali manuali (tabella comparativa) |
| `local_compman_final_history` | Storico modifiche valutazioni finali |
| `local_compman_weights` | Pesi ponderazione per area/settore |
| `local_passport_comments` | Commenti coach per area (Passaporto Tecnico) |
| `local_garage_config` | Configurazione Garage FTM per studente |

**Capabilities** (`db/access.php`):
- `local/competencymanager:view`
- `local/competencymanager:manage`
- `local/competencymanager:managecoaching`
- `local/competencymanager:assigncoach`
- `local/competencymanager:managesectors`
- `local/competencymanager:evaluate`
- `local/competencymanager:viewallevaluations`
- `local/competencymanager:editallevaluations`
- `local/competencymanager:authorizestudentview`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `student_report.php` | Report studente con radar e tabella comparativa |
| `technical_passport.php` | Passaporto Tecnico (12 sezioni configurabili) |
| `garage_ftm.php` | Garage FTM — costruzione passaporto |
| `sector_admin.php` | Amministrazione settori |
| `coach_evaluation.php` | Valutazione Bloom coach |
| `my_selfassessment.php` | Redirect a selfassessment/compile.php (deprecato) |
| `settings.php` | Impostazioni plugin (soglia passaporto) |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_ftm_scheduler` (coach, gruppi)
- `local_selfassessment` (dati autovalutazione)
- `local_labeval` (valutazioni lab)
- `local_coachmanager` (coach dashboard)
- `local_ftm_cpurc` (dati studenti)
- `qbank_competenciesbyquestion` (competenze per domanda)

---

### 3. local_competencyreport

**Percorso:** `local/competencyreport/`  
**Component:** `local_competencyreport`  
**Versione:** `2025120501` | Release: `v1.0` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_competencyreport_auth` | Autorizzazione studente a vedere il proprio report |

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `student.php` | Report per singolo studente |
| `export.php` | Export dati report |
| `student_print.php` | Versione stampa report |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager`

---

### 4. local_competencyxmlimport

**Percorso:** `local/competencyxmlimport/`  
**Component:** `local_competencyxmlimport`  
**Versione:** `2026020304` | Release: `v1.4` | Maturity: STABLE  

**Dipendenze dichiarate:**
- `qbank_competenciesbyquestion` (ANY_VERSION)

**Tabelle DB:** nessuna

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `import.php` | Import XML framework competenze |
| `import_word.php` | Import da file Word |
| `setup_universale.php` | Setup universale quiz (import XML/Word/Excel/CSV) |
| `dashboard.php` | Dashboard import |
| `create_categories_from_framework.php` | Crea categorie quiz da framework |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `qbank_competenciesbyquestion`
- `local_competencymanager`

---

### 5. local_ftm_ai

**Percorso:** `local/ftm_ai/`  
**Component:** `local_ftm_ai`  
**Versione:** `2026012800` | Release: `v1.0.0-alpha` | Maturity: ALPHA — **STANDBY**

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION)

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_ftm_ai_usage` | Tracciamento uso API per monitoraggio costi |

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `settings.php` | Configurazione Azure OpenAI |

**Lingue:** `en`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager`

> **Nota:** Plugin in STANDBY. Integrazione Azure OpenAI con anonimizzazione PII.

---

### 6. local_ftm_common

**Percorso:** `local/ftm_common/`  
**Component:** `local_ftm_common`  
**Versione:** `2026012100` | Release: `v1.0.0` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB:** nessuna

**Capabilities:** nessuna

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `preview.php` | Preview documenti/componenti condivisi |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:** nessuna specifica

---

### 7. local_ftm_cpurc

**Percorso:** `local/ftm_cpurc/`  
**Component:** `local_ftm_cpurc`  
**Versione:** `2026032601` | Release: `v1.6.0` | Maturity: BETA  

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION)
- `local_ftm_scheduler` (ANY_VERSION)

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_ftm_cpurc_students` | Dati iscrizione studenti CPURC |
| `local_ftm_cpurc_reports` | Dati report finale studenti |
| `local_ftm_cpurc_imports` | Log operazioni import CSV/Excel |

**Capabilities:** nessuna dichiarata (accesso per siteadmin/manager)

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Dashboard segreteria CPURC |
| `student_card.php` | Scheda studente (4 tab) |
| `import.php` | Import CSV studenti |
| `import_production.php` | Import avanzato da Excel con dedup |
| `export_word.php` | Export rapporto finale Word (137 merge field) |
| `export_excel.php` | Export credenziali LADI formato Excel |
| `loginas_student.php` | Endpoint custom login-as studente |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (settori, coach)
- `local_ftm_scheduler` (gruppi, aule)
- `local_coachmanager` (coach dashboard)
- `local_student_coaching` (tabella condivisa)
- `local_student_sectors` (tabella condivisa)

---

### 8. local_ftm_hub

**Percorso:** `local/ftm_hub/`  
**Component:** `local_ftm_hub`  
**Versione:** `2026010700` | Release: `v2.0.0` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB:** nessuna

**Capabilities:**
- `local/ftm_hub:view`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Hub centrale navigazione FTM |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:** hub di navigazione verso tutti i plugin

---

### 9. local_ftm_jobsearch

**Percorso:** `local/ftm_jobsearch/`  
**Component:** `local_ftm_jobsearch`  
**Versione:** `2026041600` | Release: `v1.0.0` | Maturity: ALPHA  

**Dipendenze dichiarate:** nessuna

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_ftm_jobsearch_offers` | Offerte lavoro recuperate |
| `local_ftm_jobsearch_searches` | Storico ricerche effettuate |
| `local_ftm_jobsearch_cities` | Comuni/città per ricerca geolocalizzata |

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Interfaccia ricerca offerte |
| `settings.php` | Configurazione scraper |
| `ajax_search.php` | Endpoint AJAX ricerca |
| `debug_scraper.php` | Debug scraping |

**Lingue:** `it`, `en`

**Integrazioni cross-plugin rilevate:**
- `local_jobmatchagent` (usa i risultati jobsearch)

---

### 10. local_ftm_scheduler

**Percorso:** `local/ftm_scheduler/`  
**Component:** `local_ftm_scheduler`  
**Versione:** `2026020601` | Release: `v1.2.0` | Maturity: STABLE  

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION)

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_ftm_groups` | Gruppi studenti (colore, KW, date) |
| `local_ftm_group_members` | Appartenenza studenti ai gruppi |
| `local_ftm_activities` | Attività calendario (lezioni, test, atelier) |
| `local_ftm_rooms` | Aule e spazi |
| `local_ftm_coaches` | Docenti/coach registrati |
| `local_ftm_external_bookings` | Prenotazioni esterne aule |
| `local_ftm_student_program` | Programma individuale studente |

**Capabilities** (`db/access.php`):
- `local/ftm_scheduler:view`
- `local/ftm_scheduler:manage`
- `local/ftm_scheduler:managegroups`
- `local/ftm_scheduler:manageactivities`
- `local/ftm_scheduler:managerooms`
- `local/ftm_scheduler:enrollstudents`
- `local/ftm_scheduler:markattendance`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Calendario settimanale/mensile |
| `secretary_dashboard.php` | Dashboard segreteria (5 tab) |
| `import_calendar.php` | Import Excel calendario (3 aule, colori celle) |
| `student_program.php` | Programma individuale studente |
| `setup_coaches.php` | Setup docenti |
| `manage_coaches.php` | Gestione docenti |
| `action.php` | CRUD attività |
| `attendance.php` | Registrazione presenze |

**Lingue:** `it`, `en`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (settori, framework)
- `local_coachmanager` (coach dashboard)
- `local_ftm_sip` (date programma)

---

### 11. local_ftm_sip

**Percorso:** `local/ftm_sip/`  
**Component:** `local_ftm_sip`  
**Versione:** `2026060300` | Release: `v3.0.0` | Maturity: BETA  

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION)
- `local_coachmanager` (ANY_VERSION)
- `local_ftm_scheduler` (ANY_VERSION)

**Tabelle DB** (`db/install.xml` + upgrade):

| Tabella | Descrizione |
|---------|-------------|
| `local_ftm_sip_enrollments` | Iscrizioni CI (con campi draft e LADI) |
| `local_ftm_sip_eligibility` | Criteri idoneità (3 attivi, scala 1-6) |
| `local_ftm_sip_action_plan` | Piano azione 12 aree |
| `local_ftm_sip_appointments` | Appuntamenti con notifiche |
| `local_ftm_sip_meetings` | Incontri registrati |
| `local_ftm_sip_channel_usage` | Canali ricerca attivati per settimana |
| `local_ftm_sip_channel_assess` | Valutazione canali 0-6 (iniziale/target/finale) |
| `local_ftm_sip_companies` | Registro aziende condiviso |
| `local_ftm_sip_applications` | Candidature inviate |
| `local_ftm_sip_contacts` | Contatti aziende |
| `local_ftm_sip_opportunities` | Opportunità generate |
| `local_ftm_sip_actions` | Azioni assegnate con scadenze |
| `local_ftm_sip_phase_notes` | Note per fase |
| `local_ftm_sip_coach_evals` | Valutazioni coach settimanali (Strategia/Autonomia 1-10) |
| `local_ftm_sip_acceptance` | Form accettazione 12 obiettivi (baseline/target/actual) |
| `local_ftm_sip_search_entries` | Registrazione dettagliata contatti/candidature per area/settimana |

**Capabilities** (`db/access.php`):
- `local/ftm_sip:view`
- `local/ftm_sip:manage`
- `local/ftm_sip:edit`
- `local/ftm_sip:coach`
- `local/ftm_sip:generatereport`
- `local/ftm_sip:viewown`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `sip_dashboard.php` | Dashboard coach — tutti gli studenti CI |
| `sip_student.php` | Vista singolo studente (tab: eligibility, piano, tracking, canali, ecc.) |
| `sip_my.php` | Area studente — inserimento KPI autonomo |
| `sip_stats.php` | Statistiche aggregate per direzione/URC |
| `sip_export_report.php` | Export report Word (9 sezioni) |
| `ajax_save_eligibility.php` | Salva griglia idoneità |
| `ajax_save_tracking.php` | CRUD search_entries + coach_evals + proof upload |
| `ajax_save_acceptance.php` | Salva form accettazione 12 aree |
| `ajax_save_channels.php` | Gestisce attivazione canali ricerca |
| `ajax_save_channel_assessment.php` | Salva valutazione canali 0-6 |
| `ajax_parse_proofs.php` | AI parser JPG/PNG/PDF via OpenAI Vision → mandatory_searches |
| `ajax_get_eligibility.php` | Recupera dati eligibility per modal |
| `ajax_inform_secretariat.php` | Notifica segreteria attivazione CI |
| `ajax_request_activation.php` | Richiesta attivazione da coach |
| `admin_reset_all.php` | Reset dati CI (solo siteadmin) |
| `companies.php` | Registro aziende |
| `settings.php` | Impostazioni plugin CI |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (settori, dati quiz)
- `local_coachmanager` (badge CI, filtri, modal attivazione)
- `local_ftm_scheduler` (date programma, coach)
- `local_selfassessment` (dati autovalutazione)
- `local_jobaida` (lettere presentazione)

> **Note architetturali:**
> - 12 aree attivazione (10 quantitative + 2 qualitative)
> - Semaforo idoneità: 0-10 non_idoneo / 11-14 idoneo / 15-18 idoneo_prioritario
> - Scala criteri: 1-6
> - AI Proof Parser per Foglio Ricerche URC (GPT-4o Vision)

---

### 12. local_ftm_testsuite

**Percorso:** `local/ftm_testsuite/`  
**Component:** `local_ftm_testsuite`  
**Versione:** `2026020501` | Release: `v1.1.0` | Maturity: STABLE  

**Dipendenze dichiarate:**
- `local_competencymanager` (ANY_VERSION)
- `local_selfassessment` (ANY_VERSION)
- `local_labeval` (ANY_VERSION)
- `local_coachmanager` (ANY_VERSION)
- `qbank_competenciesbyquestion` (ANY_VERSION)

**Tabelle DB:** nessuna propria

**Capabilities:** nessuna dichiarata (solo siteadmin)

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `agent_tests.php` | Interfaccia web 5 agenti, 58 test |
| `run.php` | Esecuzione test runner |
| `generate.php` | Demo Coach Generator (3 coach, 21 studenti) |
| `fix.php` | Fix quiz attempts (slot, multichoice shuffle) |
| `cleanup.php` | Pulizia dati demo |
| `results.php` | Visualizzazione risultati |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- Tutti i plugin elencati nelle dipendenze

---

### 13. local_jobaida

**Percorso:** `local/jobaida/`  
**Component:** `local_jobaida`  
**Versione:** `2026041001` | Release: `v1.1.0` | Maturity: BETA  

**Dipendenze dichiarate:** nessuna

**Tabelle DB:** nessuna dichiarata in install.xml (configurazione in upgrade.php)

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Generatore lettere AIDA (Express + Coaching Writers) |
| `manage_auth.php` | Gestione autorizzazioni accesso |
| `history.php` | Storico lettere generate |
| `ajax_generate.php` | Endpoint AJAX generazione AI |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_jobmatchagent` (condivide contesto AI)
- `local_ftm_ai` (configurazione Azure OpenAI)
- `local_ftm_sip` (lettere per candidature CI)

> **Note:** Richiede Moodle 4.4+. Dual mode: Express Writers + Coaching Writers. Simulazione colloqui AI. Export Word lettere.

---

### 14. local_jobmatchagent

**Percorso:** `local/jobmatchagent/`  
**Component:** `local_jobmatchagent`  
**Versione:** `2026042700` | Release: `v0.7.0` | Maturity: ALPHA  

**Dipendenze dichiarate:**
- `local_jobaida` (ANY_VERSION, required)
- `local_competencymanager` (ANY_VERSION)
- `local_coachmanager` (ANY_VERSION)
- `local_ftm_jobsearch` (ANY_VERSION, opzionale)

**Tabelle DB:** nessuna dichiarata in install.xml

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point agente matching |
| `wizard.php` | Wizard configurazione matching |
| `student_view.php` | Vista studente opportunità |
| `coach_dashboard.php` | Dashboard coach matching |
| `student_action.php` | Azioni studente su match |
| `fetch_now.php` | Fetch immediato offerte |

**Lingue:** `it`, `en`

**Integrazioni cross-plugin rilevate:**
- `local_jobaida` (generazione lettere)
- `local_competencymanager` (profilo competenze)
- `local_coachmanager` (coach dashboard)
- `local_ftm_jobsearch` (offerte lavoro, opzionale)

---

### 15. local_labeval

**Percorso:** `local/labeval/`  
**Component:** `local_labeval`  
**Versione:** `2025010901` | Release: `v2.0.0` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_labeval_templates` | Template valutazioni laboratorio |
| `local_labeval_behaviors` | Comportamenti osservabili per template |
| `local_labeval_behavior_comp` | Mapping comportamenti → competenze con peso |
| `local_labeval_assignments` | Assegnazioni valutazione a studenti |
| `local_labeval_sessions` | Sessioni di valutazione |
| `local_labeval_ratings` | Rating singoli comportamenti |
| `local_labeval_comp_scores` | Cache punteggi competenza per sessione |
| `local_labeval_auth` | Autorizzazione studente a vedere il report |

**Capabilities** (`db/access.php`):
- `local/labeval:managetemplates`
- `local/labeval:importtemplates`
- `local/labeval:assignevaluations`
- `local/labeval:evaluate`
- `local/labeval:viewownreport`
- `local/labeval:viewallreports`
- `local/labeval:authorizestudents`
- `local/labeval:view`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `templates.php` | Gestione template |
| `template_view.php` | Vista dettaglio template |
| `import.php` | Import template da file |
| `assignments.php` | Lista assegnazioni |
| `assign.php` | Nuova assegnazione studente |
| `evaluate.php` | Compilazione valutazione |
| `view_evaluation.php` | Vista valutazione completata |
| `reports.php` | Report aggregati |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (framework competenze)
- `local_ftm_testsuite` (test integrazione)

---

### 16. local_selfassessment

**Percorso:** `local/selfassessment/`  
**Component:** `local_selfassessment`  
**Versione:** `2026031603` | Release: `v1.6.3` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `local_selfassessment` | Autovalutazioni studenti per competenza (livello Bloom) |
| `local_selfassessment_status` | Stato abilitazione autovalutazione per studente |
| `local_selfassessment_assign` | Competenze assegnate per autovalutazione |
| `local_selfassessment_reminders` | Log reminder inviati a studenti |

**Capabilities** (`db/access.php`):
- `local/selfassessment:complete`
- `local/selfassessment:view`
- `local/selfassessment:manage`
- `local/selfassessment:sendreminder`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `index.php` | Entry point principale |
| `compile.php` | **Unico punto attivo** — compilazione autovalutazione studente |
| `assign.php` | Assegnazione competenze a studente |
| `force_assign.php` | Assegnazione forzata (backfill) |
| `student_report.php` | Report autovalutazione |

**Lingue:** `it`, `en`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (framework, settori, filtro settore con fallback)
- `local_ftm_testsuite` (test)
- `local_ftm_sip` (dati autovalutazione per CI)

> **Note architetturali:**
> - Popup bloccante con doppia password skip (6807/FTM)
> - Hook System Moodle 4.3+ con versioning fallback
> - Filtro settore con fallback: se il settore primario scarta TUTTE le competenze, assegna comunque
> - `my_selfassessment.php` in competencymanager è redirect qui (sistema deprecato)

---

## PLUGIN BLOCCO

---

### 17. block_ftm_tools

**Percorso:** `blocks/ftm_tools/`  
**Component:** `block_ftm_tools`  
**Versione:** `2026010700` | Release: `v2.0.0` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB:** nessuna

**Capabilities** (`db/access.php`):
- `block/ftm_tools:addinstance`
- `block/ftm_tools:myaddinstance`

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `block_ftm_tools.php` | Classe blocco principale |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:** navigazione verso plugin FTM

---

## PLUGIN QUESTION BANK

---

### 18. qbank_competenciesbyquestion

**Percorso:** `question/bank/competenciesbyquestion/`  
**Component:** `qbank_competenciesbyquestion`  
**Versione:** `2025120601` | Release: `v1.1` | Maturity: STABLE  

**Dipendenze dichiarate:** nessuna

**Tabelle DB** (`db/install.xml`):

| Tabella | Descrizione |
|---------|-------------|
| `qbank_competencies` | Competenze collegate a categorie domande |
| `qbank_comp_question` | Mapping domande → competenze |

**Capabilities:** nessuna dichiarata

**Entry point principali:**

| File | Descrizione |
|------|-------------|
| `edit.php` | Editor mapping competenze per domanda |

**Lingue:** `en`, `it`

**Integrazioni cross-plugin rilevate:**
- `local_competencymanager` (framework competenze)
- `local_competencyxmlimport` (import quiz)
- `local_ftm_testsuite` (test integrazione)

---

## INTEGRATION MAP

### Dipendenze dichiarate in version.php

```
qbank_competenciesbyquestion  ◄──── local_competencymanager
                              ◄──── local_competencyxmlimport
                              ◄──── local_ftm_testsuite

local_competencymanager       ◄──── local_coachmanager (opt)
                              ◄──── local_ftm_ai
                              ◄──── local_ftm_scheduler
                              ◄──── local_ftm_cpurc
                              ◄──── local_ftm_sip
                              ◄──── local_ftm_testsuite
                              ◄──── local_jobmatchagent

local_ftm_scheduler           ◄──── local_ftm_cpurc
                              ◄──── local_ftm_sip

local_coachmanager            ◄──── local_ftm_sip
                              ◄──── local_jobmatchagent

local_jobaida                 ◄──── local_jobmatchagent
```

---

### Mappa completa integrazioni (dipendenze + riferimenti codice)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FTM ECOSYSTEM                               │
│                                                                     │
│  [qbank_competenciesbyquestion]  ◄── base dati competenze/domande  │
│           │                                                         │
│           ▼                                                         │
│  [local_competencymanager]  ◄── CORE CENTRALE                      │
│    Tabelle condivise:                                               │
│    • local_student_coaching                                         │
│    • local_student_sectors                                          │
│    • local_compman_final_ratings                                    │
│    • local_passport_comments                                        │
│    • local_garage_config                                            │
│           │                                                         │
│    ┌──────┼──────────────────────────┐                             │
│    │      │                          │                             │
│    ▼      ▼                          ▼                             │
│ [local_  [local_          [local_competencyxmlimport]             │
│ labeval] selfassessment]   Import XML/Word/Excel/CSV               │
│    │          │                                                     │
│    └──────────┼──────────────────────────────────┐                 │
│               │                                  │                 │
│               ▼                                  │                 │
│  [local_ftm_scheduler]   ◄── Tabelle condivise:  │                 │
│    • local_ftm_groups          local_ftm_coaches  │                 │
│    • local_ftm_group_members                      │                 │
│    • local_ftm_coaches                            │                 │
│           │                                       │                 │
│    ┌──────┼──────────────┐                        │                 │
│    │      │              │                        │                 │
│    ▼      ▼              ▼                        │                 │
│ [local_ [local_      [local_ftm_sip]              │                 │
│ coachm.] ftm_cpurc]   CI v2.0 — 16 tabelle       │                 │
│    │         │         • sip_enrollments          │                 │
│    │         │         • sip_channel_assess       │                 │
│    │         │         • sip_search_entries       │                 │
│    │         │         • sip_acceptance           │                 │
│    │         │              │                     │                 │
│    └────┬───┘         ┌────┘                      │                 │
│         │             │                           │                 │
│         ▼             ▼                           │                 │
│  [local_ftm_hub]  [block_ftm_tools]               │                 │
│   Hub navigazione  Blocco strumenti               │                 │
│                                                   │                 │
│  ─ ─ ─ ─ ─ ─ ─ AI LAYER ─ ─ ─ ─ ─ ─ ─ ─ ─ ─    │                 │
│                                                   │                 │
│  [local_ftm_ai]  ──► Azure OpenAI (STANDBY)       │                 │
│       │                                           │                 │
│       ▼                                           │                 │
│  [local_jobaida]  Lettere AIDA + colloqui AI      │                 │
│       │                                           │                 │
│       ▼                                           │                 │
│  [local_jobmatchagent]  ◄── [local_ftm_jobsearch] │                 │
│   Matching offerte AI       Scraping offerte      │                 │
│                                                   │                 │
│  ─ ─ ─ ─ ─ ─ TEST ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─    │                 │
│                                                   │                 │
│  [local_ftm_testsuite]  ◄── tutti i plugin core   │                 │
│   5 agenti, 58 test                               │                 │
│                                                   │                 │
│  [local_ftm_common]  ◄── utility condivise        │                 │
│                                                   │                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Tabelle DB condivise tra più plugin

| Tabella | Owner | Letta da |
|---------|-------|----------|
| `local_student_coaching` | competencymanager | coachmanager, ftm_cpurc, ftm_sip |
| `local_student_sectors` | competencymanager | selfassessment, ftm_cpurc, ftm_sip |
| `local_ftm_coaches` | ftm_scheduler | coachmanager, ftm_cpurc, ftm_sip |
| `local_ftm_groups` | ftm_scheduler | coachmanager, ftm_cpurc, ftm_sip |
| `local_ftm_group_members` | ftm_scheduler | coachmanager, ftm_cpurc, ftm_sip |
| `local_compman_final_ratings` | competencymanager | technical_passport.php |
| `local_garage_config` | competencymanager | technical_passport.php, garage_ftm.php |
| `local_passport_comments` | competencymanager | technical_passport.php |
| `local_ftm_ai_usage` | ftm_ai | jobaida |

---

### Conteggio tabelle DB per plugin

| Plugin | N. Tabelle |
|--------|-----------|
| local_ftm_sip | 16 |
| local_competencymanager | 12 |
| local_labeval | 8 |
| local_ftm_scheduler | 7 |
| local_selfassessment | 4 |
| local_ftm_cpurc | 3 |
| local_ftm_jobsearch | 3 |
| local_coachmanager | 4 |
| qbank_competenciesbyquestion | 2 |
| local_competencyreport | 1 |
| local_ftm_ai | 1 |
| local_ftm_hub | 0 |
| local_ftm_common | 0 |
| local_ftm_testsuite | 0 |
| local_jobaida | 0 |
| local_jobmatchagent | 0 |
| local_competencyxmlimport | 0 |
| block_ftm_tools | 0 |
| **TOTALE** | **72** |

---

*Inventario generato automaticamente il 2026-05-07.*  
*Per aggiornamenti: rigenerare con Claude Code dalla cartella `FTM_PLUGINS_NEW`.*
