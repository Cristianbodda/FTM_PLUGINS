# FTM PLUGINS

Ecosistema di 11 plugin Moodle per la gestione delle competenze professionali.

**Versione:** 5.0 | **Target:** Moodle 4.5+ / 5.0 | **Licenza:** GPL-3.0

---

## Stato Sviluppo (19 Gennaio 2026)

| Plugin | Versione | Stato |
|--------|----------|-------|
| ftm_scheduler | 1.0 | **Attivo** - Vista Settimana + Mese |
| competencymanager | 2.3.0 | **Attivo** - Sector Manager + capability managesectors |
| selfassessment | 1.2.0 | **Completato** - Popup bloccante + rilevazione settori |
| coachmanager | 2.1.0 | In test - Dashboard Coach Integrata |
| competencyxmlimport | 4.1 | **Aggiornato 19/01** - Setup Universale migliorato |
| ftm_cpurc | - | **PIANIFICATO** - Import utenti CPURC + Report Word |
| Altri plugin | - | Stabili |

---

## Setup Universale Quiz (AGGIORNATO 19/01/2026)

Strumento completo per import quiz e assegnazione competenze automatica.

### Funzionalita
- **Import XML/Word** con parsing automatico
- **Estrazione codici competenza** con regex flessibile
- **Supporto caratteri accentati** (ELETTRICITA -> ELETTRICITÀ)
- **Alias settori** (AUTOVEICOLO -> AUTOMOBILE, MECC -> MECCANICA)
- **Assegnazione competenze** a domande nuove E esistenti
- **Aggiornamento livello difficolta** per competenze gia assegnate
- **Debug integrato** per troubleshooting
- **Riepilogo finale** con tabella quiz/domande/livello

### Livelli Difficolta
- ⭐ Base (1)
- ⭐⭐ Intermedio (2)
- ⭐⭐⭐ Avanzato (3)

### URL
`/local/competencyxmlimport/setup_universale.php?courseid=X`

---

## Framework Competenze

### Framework Principale (FTM-01)
Settori: AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITÀ, LOGISTICA, MECCANICA, METALCOSTRUZIONE

### Framework Generico (FTM_GEN)
Settori: GENERICO

**Nota:** GENERICO ha un framework separato.

---

## Plugin (11 totali)

### Local (9)
| Plugin | Descrizione |
|--------|-------------|
| `competencymanager` | Core gestione competenze + Sector Manager |
| `coachmanager` | Coaching formatori + Dashboard |
| `competencyreport` | Report studenti |
| `competencyxmlimport` | Import XML/Word/Excel + Setup Universale |
| `ftm_hub` | Hub centrale |
| `ftm_scheduler` | Pianificazione calendario (settimana + mese) |
| `ftm_testsuite` | Testing |
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

## Settori e Alias

| Settore Standard | Alias Supportati |
|------------------|------------------|
| AUTOMOBILE | AUTOVEICOLO |
| AUTOMAZIONE | AUTOM, AUTOMAZ |
| CHIMFARM | CHIM, CHIMICA, FARMACEUTICA |
| ELETTRICITÀ | ELETTRICITA, ELETTR, ELETT |
| LOGISTICA | LOG |
| MECCANICA | MECC |
| METALCOSTRUZIONE | METAL |

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
- `local/selfassessment/SELFASSESSMENT_FLOW.md` - Flusso autovalutazione
- `local/competencyxmlimport/README_WORD_IMPORT.md` - Guida import Word

---

*Sviluppato per FTM - Fondazione Terzo Millennio*
*Ultimo aggiornamento: 19 Gennaio 2026*
