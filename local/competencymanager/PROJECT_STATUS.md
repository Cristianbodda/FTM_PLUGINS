# Competency Manager - Stato Progetto

**Ultimo aggiornamento:** 16 Gennaio 2026
**Versione:** 2.3.0 (2026011601)

## Stato: ATTIVO

### Moduli

#### 1. Core Competency Manager
Gestione framework competenze FTM con 7 settori.

#### 2. Sector Manager (NUOVO 15/01/2026)
Sistema multi-settore per studenti.

### Sector Manager - Dettaglio

#### Funzionalita
- **Multi-Settore:** Ogni studente puo avere N settori
  - 1 Settore Primario (assegnato manualmente)
  - N Settori Rilevati (automaticamente dai quiz)
- **Interfaccia Segreteria:** sector_admin.php
- **Rilevazione Automatica:** Observer in selfassessment

#### Icone Stato Percorso
| Icona | Stato | Settimane |
|-------|-------|-----------|
| 🆕 | Nuovo ingresso | < 2 |
| ⏳ | In corso | 2-4 |
| ⚠️ | Fine vicina | 4-6 |
| 🔴 | Prolungo | > 6 |
| ➖ | Non impostato | - |

#### File Creati
```
local/competencymanager/
├── sector_admin.php              # Interfaccia segreteria
├── ajax_save_sector.php          # Endpoint AJAX
├── classes/
│   └── sector_manager.php        # Classe gestione settori
├── db/
│   ├── install.xml               # + tabella local_student_sectors
│   └── upgrade.php               # Migrazione v2026011501
└── lang/
    ├── en/local_competencymanager.php  # + stringhe sector
    └── it/local_competencymanager.php  # + stringhe sector
```

#### Database - Tabella local_student_sectors
```sql
CREATE TABLE local_student_sectors (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    userid BIGINT NOT NULL,
    courseid BIGINT NOT NULL DEFAULT 0,
    sector VARCHAR(50) NOT NULL,
    is_primary TINYINT NOT NULL DEFAULT 0,
    source VARCHAR(20) NOT NULL DEFAULT 'quiz',
    quiz_count INT NOT NULL DEFAULT 0,
    first_detected INT,
    last_detected INT,
    timecreated INT NOT NULL,
    timemodified INT NOT NULL,

    UNIQUE INDEX (userid, courseid, sector),
    INDEX (userid, is_primary),
    INDEX (sector)
);
```

#### Metodi sector_manager.php
```php
get_student_sectors($userid, $courseid = 0)
get_primary_sector($userid, $courseid = 0)
get_effective_sector($userid, $courseid = 0)
set_primary_sector($userid, $sector, $courseid = 0)
detect_sectors_from_quiz($userid, $quizid, $competencyids)
get_students_with_sectors($filters)
get_date_color_class($date_start)
get_active_courses()
get_visible_cohorts()
get_sector_stats($courseid = 0)
```

#### Filtri Disponibili
- Ricerca studente (nome, cognome, email)
- Corso
- Coorte
- Settore
- Data ingresso (da/a)

### URL

- Sector Admin: `/local/competencymanager/sector_admin.php`
- Link da Scheduler: toolbar + tab gruppi

### Settori FTM

| Codice | Nome |
|--------|------|
| AUTOMOBILE | Automobile |
| MECCANICA | Meccanica |
| LOGISTICA | Logistica |
| ELETTRICITA | Elettricita |
| AUTOMAZIONE | Automazione |
| METALCOSTRUZIONE | Metalcostruzione |
| CHIMFARM | Chimico-Farmaceutico |

### Integrazione con Altri Plugin

- **selfassessment:** Observer rileva settori dai quiz completati
- **ftm_scheduler:** Link a sector_admin in toolbar e tab gruppi
- **coachmanager:** Puo usare sector_manager per filtri coach
- **ftm_cpurc:** (pianificato) Utilizzo sector_manager per import

---

## Capabilities (v2.3.0)

| Capability | Descrizione | Ruoli |
|------------|-------------|-------|
| `competencymanager:view` | Visualizzare report competenze | teacher, manager |
| `competencymanager:manage` | Gestire competenze | manager |
| `competencymanager:managecoaching` | Gestire coaching studenti | editingteacher, manager |
| `competencymanager:assigncoach` | Assegnare studenti ai coach | manager |
| `competencymanager:managesectors` | **NUOVO** Gestire settori studenti | editingteacher, manager |

---

## Modifiche 16/01/2026

- Aggiunta capability `managesectors` per gestione settori segreteria/coach
- Fix sicurezza: PARAM_TEXT invece di PARAM_RAW in sector_admin.php
- Fix AJAX: aggiunto `die()` alla fine di ajax_save_sector.php
- Versione aggiornata a 2026011601 (v2.3.0)
