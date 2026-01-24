# FTM PLUGINS

Ecosistema di 12 plugin Moodle per la gestione delle competenze professionali.

**Versione:** 5.0 | **Target:** Moodle 4.5+ / 5.0 | **Licenza:** GPL-3.0

---

## Stato Sviluppo (24 Gennaio 2026)

| Plugin | Versione | Stato |
|--------|----------|-------|
| ftm_scheduler | 1.0 | **Attivo** - Vista Settimana + Mese + Gestione Coach |
| competencymanager | 2.3.0 | **Attivo** - Sector Manager + Student Report Print |
| selfassessment | 1.2.0 | **Attivo** - Popup + rilevazione settori + filtro primario |
| coachmanager | 2.2.0 | **Attivo** - Dashboard V2 con zoom accessibilita |
| competencyxmlimport | 4.1 | **Attivo** - Setup Universale migliorato |
| ftm_cpurc | 1.0.0 | **COMPLETATO 24/01** - Gestione CPURC completa |
| ftm_testsuite | 1.0 | **Attivo** - 58 test automatizzati |
| Altri plugin | - | Stabili |

---

## Sistema CPURC (NUOVO 24/01/2026)

Sistema completo per gestione studenti CPURC con import CSV e report Word.

### Funzionalita
- **Import CSV** da sistema CPURC con mapping automatico
- **Dashboard Segreteria** con filtri avanzati (URC, settore, coach, stato)
- **Student Card** con 4 tab (Anagrafica, Percorso, Assenze, Stage)
- **Coach Assignment** sincronizzato con tutti i plugin FTM
- **Multi-Settore** (Primario: quiz/autovalutazione, Secondario/Terziario: suggerimenti)
- **Report Word** professionale per ogni studente
- **Export Excel** completo di tutti i dati
- **Export Word Bulk** (ZIP con tutti i report)

### URL Principali
- Dashboard: `/local/ftm_cpurc/index.php`
- Student Card: `/local/ftm_cpurc/student_card.php?id=X`
- Report: `/local/ftm_cpurc/report.php?id=X`
- Import: `/local/ftm_cpurc/import.php`

---

## Setup Universale Quiz

Strumento completo per import quiz e assegnazione competenze automatica.

### Funzionalita
- **Import XML/Word** con parsing automatico
- **Estrazione codici competenza** con regex flessibile
- **Supporto caratteri accentati** (ELETTRICITA -> ELETTRICITA)
- **Alias settori** (AUTOVEICOLO -> AUTOMOBILE, MECC -> MECCANICA)
- **Assegnazione competenze** a domande nuove E esistenti
- **Aggiornamento livello difficolta** per competenze gia assegnate
- **Debug integrato** per troubleshooting
- **Riepilogo finale** con tabella quiz/domande/livello

### Livelli Difficolta
- Base (1)
- Intermedio (2)
- Avanzato (3)

### URL
`/local/competencyxmlimport/setup_universale.php?courseid=X`

---

## Framework Competenze

### Framework Principale (FTM-01)
Settori: AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, MECCANICA, METALCOSTRUZIONE

### Framework Generico (FTM_GEN)
Settori: GENERICO

**Nota:** GENERICO ha un framework separato.

---

## Plugin (12 totali)

### Local (10)
| Plugin | Descrizione |
|--------|-------------|
| `competencymanager` | Core gestione competenze + Sector Manager + Student Report |
| `coachmanager` | Coaching formatori + Dashboard V2 |
| `competencyreport` | Report studenti |
| `competencyxmlimport` | Import XML/Word/Excel + Setup Universale |
| `ftm_cpurc` | Gestione CPURC + Import CSV + Report Word |
| `ftm_hub` | Hub centrale |
| `ftm_scheduler` | Pianificazione calendario + Gestione Coach |
| `ftm_testsuite` | Testing automatizzato (58 test) |
| `labeval` | Valutazione laboratori |
| `selfassessment` | Autovalutazione + rilevazione settori |

### Block (1)
| Plugin | Descrizione |
|--------|-------------|
| `ftm_tools` | Blocco strumenti FTM |

### Question Bank (1)
| Plugin | Descrizione |
|--------|-------------|
| `competenciesbyquestion` | Competenze domande |

---

## Tabelle Condivise

| Tabella | Descrizione | Plugin |
|---------|-------------|--------|
| `local_student_coaching` | Assegnazione coach-studente | competencymanager, ftm_cpurc, coachmanager |
| `local_student_sectors` | Multi-settore studenti | competencymanager, ftm_cpurc, selfassessment |
| `local_ftm_coaches` | Anagrafica coach (CB, FM, GM, RB) | ftm_scheduler -> tutti |

---

## Settori e Alias

| Settore Standard | Alias Supportati |
|------------------|------------------|
| AUTOMOBILE | AUTOVEICOLO |
| AUTOMAZIONE | AUTOM, AUTOMAZ |
| CHIMFARM | CHIM, CHIMICA, FARMACEUTICA |
| ELETTRICITA | ELETTRICITA, ELETTR, ELETT |
| LOGISTICA | LOG |
| MECCANICA | MECC |
| METALCOSTRUZIONE | METAL |

---

## Coach FTM

| Sigla | Nome | Ruolo |
|-------|------|-------|
| CB | Cristian Bodda | Coach |
| FM | Fabio Marinoni | Coach |
| GM | Graziano Margonar | Coach |
| RB | Roberto Bravo | Coach |

---

## Installazione

1. Copia le cartelle plugin in Moodle:
   - `local/*` -> `/local/`
   - `blocks/*` -> `/blocks/`
   - `question/bank/*` -> `/question/bank/`
2. Amministrazione > Notifiche (per upgrade database)
3. Svuota cache: Amministrazione > Sviluppo > Svuota cache

---

## Server di Test

https://test-urc.hizuvala.myhostpoint.ch

---

## Documentazione

- `CLAUDE.md` - Istruzioni per Claude Code (IMPORTANTE per AI)
- `local/*/PROJECT_STATUS.md` - Stato di ogni plugin
- `local/ftm_cpurc/PROJECT_STATUS.md` - Documentazione CPURC
- `local/selfassessment/SELFASSESSMENT_FLOW.md` - Flusso autovalutazione
- `local/competencyxmlimport/README_WORD_IMPORT.md` - Guida import Word

---

*Sviluppato per FTM - Fondazione Terzo Millennio*
*Ultimo aggiornamento: 24 Gennaio 2026*
