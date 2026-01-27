# Competency Manager - Stato Progetto

**Ultimo aggiornamento:** 27 Gennaio 2026
**Versione:** 2.4.0 (2026012701)

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

## Modifiche 27/01/2026 - Student Report Print v2

### Fix Stampa PDF Professionale

#### 1. Fix Overlap Header su Pagine Successive
- **Problema:** Le sezioni DETTAGLIO (pagine 7-12) apparivano sotto l'header rosso fisso
- **Soluzione:**
  - `body padding-top: 85px` in @media print
  - Tutte le aree DETTAGLIO con `page-break-before: always` e `padding-top: 75px`
  - Classe `.page-break-before` con padding-top extra

#### 2. Stile Uniforme Titoli DETTAGLIO
- **Problema:** Area A aveva stile diverso (solo bordo) rispetto a B, C, D, E, F, G (sfondo pieno)
- **Soluzione:**
  - Tutte le aree ora iniziano su nuova pagina
  - Doppio fallback: `background-color` + `box-shadow: inset 0 0 0 1000px [color]`
  - Aggiunto `color-adjust: exact` per compatibilita browser

#### 3. Tabelle Legenda Compatte
- **Problema:** Tabella legenda finiva nella pagina successiva, separata dal grafico radar
- **Soluzione:**
  - Radar ridotto da 490px a **360px**
  - Font tabella ridotto da 8.5pt a **7pt**
  - Padding celle ridotto da 5px 8px a **2px 4px**
  - Larghezza colonne ridotte (70px -> 55px)

#### 4. Nomi Quiz e Autovalutazione
- Aggiunto caricamento nomi quiz selezionati in student_report.php
- Passaggio parametri `selectedQuizNames` e `autovalutazioneQuizName` al template stampa
- Visualizzazione "Fonte:" sopra ogni radar con nome quiz/autovalutazione

#### File Modificati
```
local/competencymanager/
├── student_report.php           # Caricamento nomi quiz per stampa
└── student_report_print.php     # Fix layout stampa professionale
```

---

## Modifiche 22/01/2026 - Student Report Print

### Nuova Funzionalità: Stampa Report Studente Professionale

Implementato sistema di stampa avanzato per il report competenze studente con:

#### Layout e Branding
- **Logo FTM** in header con sfondo bianco
- **Font Didact Gothic** (Google Fonts) per coerenza corporate
- **Colori FTM**: rosso #dd0000 per accenti
- **Header running** su ogni pagina stampata

#### Grafici Radar Migliorati
- **Dimensione aumentata**: 490px (+40% rispetto originale 340px)
- **Etichette complete**: maxLabelLen=250 caratteri (nessun troncamento)
- **Padding laterale SVG**: 180px per etichette lunghe sulla sinistra
- **Font etichette**: 9pt per leggibilità

#### Sezioni Configurabili
Il coach può impostare l'ordine delle sezioni in stampa (1-9):
1. Valutazione Globale
2. Progressi Recenti
3. Autovalutazione Radar
4. Performance Radar
5. Analisi per Area
6. Raccomandazioni
7. Piano d'Azione
8. Note Aggiuntive
9. Confronto Auto/Reale

#### Tabelle Legenda
- **Font aumentato**: 8.5pt (+20% da 7pt)
- **Titolo**: 11pt bold
- **Padding celle**: 5px 8px
- **Colonne**: Area, Auto, Reale, Gap con badge colorati

#### File Modificati
```
local/competencymanager/
├── student_report.php           # generate_svg_radar() con padding laterale
├── student_report_print.php     # Layout completo stampa professionale
└── pix/ftm_logo.png             # Logo FTM scaricato localmente
```

#### CSS Print Ottimizzato
```css
@page { size: A4; margin: 15mm; }
@media print {
    body { padding-top: 75px; }
    .page-break-before { page-break-before: always; }
    th, td { padding: 3px 5px !important; }
}
```

---

## Modifiche 16/01/2026

- Aggiunta capability `managesectors` per gestione settori segreteria/coach
- Fix sicurezza: PARAM_TEXT invece di PARAM_RAW in sector_admin.php
- Fix AJAX: aggiunto `die()` alla fine di ajax_save_sector.php
- Versione aggiornata a 2026011601 (v2.3.0)
