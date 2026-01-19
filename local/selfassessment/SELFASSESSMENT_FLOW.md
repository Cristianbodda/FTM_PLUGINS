# Self Assessment - Flusso Completo del Sistema

## Panoramica

Il sistema Self Assessment permette agli studenti di autovalutare le proprie competenze utilizzando la Tassonomia di Bloom (livelli 1-6). Le competenze vengono assegnate automaticamente quando lo studente completa quiz con domande mappate a competenze.

---

## Diagramma di Flusso

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         FLUSSO SELF ASSESSMENT                          │
└─────────────────────────────────────────────────────────────────────────┘

1. CONFIGURAZIONE INIZIALE (Admin/Formatore)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

   ┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
   │   Domanda    │────▶│  Plugin qbank    │────▶│   competency    │
   │   (question) │     │ competenciesby   │     │   (competenza)  │
   │              │     │    question      │     │                 │
   └──────────────┘     └──────────────────┘     └─────────────────┘
         │                      │                        │
         │      questionid      │      competencyid      │
         │                      ▼                        │
         │        ┌──────────────────────────┐           │
         └───────▶│ qbank_competenciesbyquest│◀──────────┘
                  │ ion (mapping table)      │
                  │ - questionid             │
                  │ - competencyid           │
                  │ - difficultylevel        │
                  └──────────────────────────┘


2. TRIGGER: STUDENTE COMPLETA QUIZ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

   ┌──────────────┐     ┌──────────────────┐
   │   Studente   │────▶│  Quiz Attempt    │
   │   completa   │     │   SUBMITTED      │
   │     quiz     │     │                  │
   └──────────────┘     └────────┬─────────┘
                                 │
                                 ▼
                  ┌──────────────────────────┐
                  │ Moodle Event System      │
                  │ \mod_quiz\event\         │
                  │ attempt_submitted        │
                  └────────────┬─────────────┘
                               │
                               ▼
                  ┌──────────────────────────┐
                  │ Observer (observer.php)  │
                  │ quiz_attempt_submitted() │
                  └────────────┬─────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
   ┌──────────────────┐              ┌──────────────────┐
   │ 1. Estrai        │              │ 2. Cerca mapping │
   │    domande       │              │    competenze    │
   │    del quiz      │              │    per domande   │
   └────────┬─────────┘              └────────┬─────────┘
            │                                 │
            │         question_attempts       │
            │         questionusageid         │
            ▼                                 ▼
   ┌──────────────────┐              ┌──────────────────┐
   │ question_ids[]   │─────────────▶│ qbank_competen   │
   │                  │              │ ciesbyquestion   │
   └──────────────────┘              └────────┬─────────┘
                                              │
                                              ▼
                                   ┌──────────────────┐
                                   │ 3. Per ogni      │
                                   │    competenza    │
                                   │    trovata       │
                                   └────────┬─────────┘
                                            │
                       ┌────────────────────┴────────────────────┐
                       ▼                                         ▼
            ┌──────────────────┐                      ┌──────────────────┐
            │ Già assegnata?   │── SÌ ───────────────▶│     SKIP         │
            │ (check exists)   │                      └──────────────────┘
            └────────┬─────────┘
                     │ NO
                     ▼
            ┌──────────────────────────────┐
            │ INSERT local_selfassessment  │
            │ _assign                       │
            │ - userid                      │
            │ - competencyid               │
            │ - source = 'quiz'            │
            │ - sourceid = quizid          │
            │ - timecreated                │
            └────────────┬─────────────────┘
                         │
                         ▼
            ┌──────────────────────────────┐
            │ 4. INVIA NOTIFICA            │
            │    (message_send)            │
            │    - popup notification      │
            │    - email (opzionale)       │
            └──────────────────────────────┘


3. STUDENTE COMPILA AUTOVALUTAZIONE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

   ┌──────────────────┐
   │ Studente riceve  │
   │ notifica o       │────▶ /local/selfassessment/compile.php
   │ accede al menu   │
   └──────────────────┘
            │
            ▼
   ┌──────────────────────────────┐
   │ Manager::get_assigned_       │
   │ competencies($userid)        │
   └────────────┬─────────────────┘
                │
                ▼
   ┌──────────────────────────────┐
   │ SELECT FROM                  │
   │ local_selfassessment_assign  │
   │ JOIN competency              │
   │ WHERE userid = ?             │
   │   AND not completed          │
   └────────────┬─────────────────┘
                │
                ▼
   ┌──────────────────────────────┐
   │ Raggruppa per AREA           │
   │ (MECCANICA_*, AUTOMOBILE_*,  │
   │  LOGISTICA_*, etc.)          │
   └────────────┬─────────────────┘
                │
                ▼
   ┌──────────────────────────────┐
   │ Mostra form con slider       │
   │ Bloom Level 1-6              │
   │ per ogni competenza          │
   └────────────┬─────────────────┘
                │
                ▼ (AJAX save)
   ┌──────────────────────────────┐
   │ INSERT/UPDATE                │
   │ local_selfassessment         │
   │ - userid                     │
   │ - competencyid               │
   │ - level (1-6 Bloom)          │
   │ - timemodified               │
   └──────────────────────────────┘


4. COACH MONITORA STUDENTI
━━━━━━━━━━━━━━━━━━━━━━━━━━

   ┌──────────────────┐
   │ Coach accede a   │────▶ /local/selfassessment/index.php
   │ dashboard        │      o Coach Dashboard (/local/coachmanager/)
   └──────────────────┘
            │
            ▼
   ┌──────────────────────────────┐
   │ Visualizza lista studenti   │
   │ - Status: completed/pending │
   │ - Data ultima compilazione  │
   │ - Livello medio per area    │
   └────────────┬─────────────────┘
                │
                ▼
   ┌──────────────────────────────┐
   │ Azioni disponibili:          │
   │ - Invia reminder            │
   │ - Abilita/Disabilita        │
   │ - Visualizza dettaglio      │
   └──────────────────────────────┘
```

---

## Tabelle Database

### `local_selfassessment_assign`
Assegnazioni di competenze agli studenti per l'autovalutazione.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | INT | Primary key |
| userid | INT | ID studente |
| competencyid | INT | ID competenza |
| source | VARCHAR | Origine: 'quiz', 'legacy', 'simulator', 'manual' |
| sourceid | INT | ID quiz/attività sorgente |
| timecreated | INT | Timestamp creazione |

### `local_selfassessment`
Autovalutazioni compilate dagli studenti.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | INT | Primary key |
| userid | INT | ID studente |
| competencyid | INT | ID competenza |
| level | INT | Livello Bloom (1-6) |
| timemodified | INT | Timestamp ultimo aggiornamento |

### `local_selfassessment_status`
Stato abilitazione autovalutazione per studente.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | INT | Primary key |
| userid | INT | ID studente |
| enabled | INT | 0 = disabilitato, 1 = abilitato |
| skip_accepted | INT | 1 = skip permanente accettato |
| skip_time | INT | Quando è stato fatto lo skip permanente |
| timecreated | INT | Timestamp |

### `local_selfassessment_reminders`
Log dei reminder inviati.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | INT | Primary key |
| userid | INT | ID studente destinatario |
| sentby | INT | ID coach che ha inviato |
| message | TEXT | Testo del reminder |
| timesent | INT | Timestamp invio |

### `qbank_competenciesbyquestion`
Mapping domande-competenze (plugin esterno).

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| id | INT | Primary key |
| questionid | INT | ID domanda |
| competencyid | INT | ID competenza |
| difficultylevel | INT | Livello difficoltà |

---

## Assegnazione Coach-Studente

Il sistema supporta **4 meccanismi** per determinare il coach di uno studente:

### 1. Tabella `local_student_coaching` (Principale)
```sql
SELECT coachid FROM local_student_coaching
WHERE userid = :studentid AND active = 1
```

### 2. Tabella `local_coachmanager_coach_assign` (Legacy)
```sql
SELECT coachid FROM local_coachmanager_coach_assign
WHERE studentid = :studentid
```

### 3. Gruppi FTM (via attività)
```sql
SELECT a.teacherid FROM local_ftm_activities a
JOIN local_ftm_group_members gm ON gm.groupid = a.groupid
WHERE gm.userid = :studentid
```

### 4. Ruolo Moodle nel corso
```sql
SELECT ra.userid FROM role_assignments ra
JOIN context ctx ON ctx.id = ra.contextid
JOIN course c ON c.id = ctx.instanceid
WHERE ra.roleid = :coachroleid AND ctx.contextlevel = 50
```

---

## Sistema Notifiche

### Message Providers (`db/messages.php`)

1. **reminder** - Reminder manuale dal coach
2. **assignment** - Notifica automatica nuove competenze

### Quando vengono inviate

| Evento | Notifica | Destinatario |
|--------|----------|--------------|
| Quiz completato con nuove competenze | assignment | Studente |
| Coach clicca "Invia Reminder" | reminder | Studente |

### Configurazione utente
Gli studenti possono configurare la ricezione in:
`Preferenze > Preferenze di messaggistica > Self Assessment`

---

## File Principali

| File | Funzione |
|------|----------|
| `classes/observer.php` | Intercetta quiz completati e assegna competenze |
| `classes/manager.php` | Business logic (get competenze, save, reminder) |
| `compile.php` | Pagina studente per compilare autovalutazione |
| `index.php` | Dashboard coach per gestione studenti |
| `db/events.php` | Registrazione observer |
| `db/messages.php` | Registrazione message providers |
| `diagnose.php` | Script diagnostica problemi |

---

## Livelli Bloom

| Livello | Nome IT | Nome EN | Descrizione |
|---------|---------|---------|-------------|
| 1 | RICORDO | REMEMBER | Conoscenza base terminologia |
| 2 | COMPRENDO | UNDERSTAND | Spiego con parole mie |
| 3 | APPLICO | APPLY | Uso in situazioni standard |
| 4 | ANALIZZO | ANALYZE | Scompongo problemi |
| 5 | VALUTO | EVALUATE | Giudico e scelgo |
| 6 | CREO | CREATE | Progetto soluzioni nuove |

---

## Popup Bloccante e Sistema Skip

### Come Funziona

Quando lo studente accede a `compile.php` e ha competenze non valutate:
1. Appare un **popup bloccante** a schermo intero
2. Lo studente deve scegliere:
   - **Compila Autovalutazione** → chiude popup e compila
   - **Inserisci password** → skip temporaneo o permanente

### Password Skip

| Password | Tipo | Comportamento |
|----------|------|---------------|
| `6807` | Temporaneo | Skip solo per questa sessione browser. Al prossimo login riappare |
| `FTM` | Permanente | Accetta autovalutazione incompleta. Popup non riappare mai più |

### Logica Visualizzazione Popup

```php
$show_blocking_modal = !$all_completed && !$permanent_skip;
```

Il popup appare SOLO SE:
- Non tutte le competenze assegnate sono state valutate **E**
- L'utente non ha fatto skip permanente (`skip_accepted = 0`)

### File Coinvolti

| File | Funzione |
|------|----------|
| `compile.php` | Contiene HTML/CSS/JS del popup |
| `ajax_skip_permanent.php` | Endpoint per salvare skip permanente |
| `db/upgrade.php` | Aggiunge campi `skip_accepted`, `skip_time` |

---

## Troubleshooting

### Le competenze non vengono assegnate dopo il quiz

1. **Verifica mapping**: Controllare che le domande del quiz abbiano competenze associate in `qbank_competenciesbyquestion`
2. **Svuota cache**: Amministrazione > Sviluppo > Svuota cache
3. **Verifica observer**: Controllare `db/events.php` e che l'observer sia registrato
4. **Esegui diagnose.php**: `/local/selfassessment/diagnose.php`

### Lo studente non vede le competenze

1. **Verifica capability**: Lo studente deve avere `local/selfassessment:complete`
2. **Verifica status**: Controllare `local_selfassessment_status.enabled = 1`
3. **Verifica assegnazioni**: Query `SELECT * FROM local_selfassessment_assign WHERE userid = X`

### Le notifiche non arrivano

1. **Verifica cron**: Il task di messaggistica deve essere attivo
2. **Verifica preferenze**: Lo studente deve aver abilitato le notifiche popup/email
3. **Controllare `db/messages.php`**: Deve esistere e definire i message providers

---

## Versione

- **Plugin**: local_selfassessment
- **Versione**: 1.2.0 (2026011404)
- **Documentazione**: 15 Gennaio 2026
- **Novità v2026011404**: Popup bloccante, skip temporaneo/permanente, mapping aree esteso
