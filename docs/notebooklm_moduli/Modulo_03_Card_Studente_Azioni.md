# Modulo 3: Card Studente e Azioni Rapide

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: la struttura della card studente nella Dashboard, header con badge settore e alert, le 3 barre di progresso, i badge di stato, la timeline 6 settimane, le attivita settimanali, la sezione atelier, le scelte settimanali e le 7 quick actions.

---

## 6. Coach Dashboard V2 - Card Studente e Azioni

### 6.1 Header della Card

L'intestazione di ogni card studente contiene:

**Riga principale:**
- **Triangolino** (▼) per espandere/comprimere
- **Nome e cognome** in grassetto
- **Email** in testo piccolo sotto il nome

**Badge settore con medaglie:**
- 🥇 **Settore primario** (badge con gradiente viola)
- 🥈 Settore secondario (badge grigio) - opzionale
- 🥉 Settore terziario (badge marrone) - opzionale

**Badge settimana:** Numero della settimana corrente (es. "Sett. 3")

**Badge alert** (se presenti):
- **"FINE 6 SETT."** - sfondo giallo/warning - appare per studenti alla settimana 6+
- **"SOTTO SOGLIA"** - sfondo rosso/danger - appare se la media competenze e <50%

**Colore header:** Il gradiente del bordo/sfondo cambia in base al gruppo colore dello studente.

### 6.2 Sezione Progress (3 Barre)

Dentro il corpo espandibile della card trovi **3 barre di progresso**:

| Barra | Colore | Cosa misura |
|-------|--------|-------------|
| **Competenze** | Viola (gradiente #667eea -> #764ba2) oppure Rosso se <50% | Percentuale media competenze da quiz |
| **Autovalutazione** | Teal (gradiente #11998e -> #38ef7d) | Percentuale completamento autovalutazione |
| **Laboratorio** | Arancione (gradiente) | Percentuale valutazione laboratorio |

Accanto a ogni barra c'e la **percentuale numerica** (es. "72%").

> **Cosa vedrai:** Tre barre orizzontali colorate che si riempiono da sinistra a destra.
> Se la barra Competenze e sotto il 50%, diventa rossa invece che viola per
> segnalare una situazione critica.

### 6.3 Badge di Stato

Sotto le barre di progresso trovi i **badge di stato** per ogni componente:

| Badge | Aspetto | Significato |
|-------|---------|-------------|
| **Done** | ✓ su sfondo verde (#d4edda) | Completato |
| **Pending** | ⏱ su sfondo giallo (#fff3cd) | In attesa |
| **Missing** | ✗ su sfondo rosso (#f8d7da) | Mancante |
| **End Path** | 🏁 su sfondo giallo | Fine percorso |

### 6.4 Timeline 6 Settimane

La timeline mostra **6 indicatori** in riga, uno per ogni settimana del percorso:

| Icona | Significato |
|-------|-------------|
| ✓ (cerchio verde) | Settimana completata |
| ◯ (cerchio giallo pieno) | Settimana corrente |
| ○ (cerchio grigio vuoto) | Settimana futura |

**Esempio per studente alla settimana 3:**
```
[✓] [✓] [◯] [○] [○] [○]
 S1   S2   S3   S4   S5   S6
```

### 6.5 Attivita della Settimana

Se disponibile, trovi una tabella con le attivita della settimana corrente:

| Colonna | Contenuto |
|---------|-----------|
| Giorno | Lunedi, Martedi, ... |
| Attivita | Nome dell'attivita programmata |
| Aula | Aula assegnata |
| Orario | Fascia oraria (Matt. 08:30-11:45 / Pom. 13:15-16:30) |

**Contatore assenze:**
- Verde (0-10% assenze): situazione buona
- Arancione (10-20%): attenzione
- Rosso (>20%): critico - formato: "Assenze: X/Y (Z%)"

### 6.6 Sezione Atelier (da Settimana 3+)

A partire dalla settimana 3, nella card appare la sezione Atelier con:

- **Completati:** Atelier gia frequentati (sfondo verde #dcfce7)
- **Iscritti:** Atelier a cui lo studente e iscritto (sfondo giallo #fef3c7)
- **Disponibili:** Atelier con posti liberi e pulsante **"Iscrivimi"**
- **Alert obbligatori:** Icona ⚠ + testo "(Obbligatorio)" in rosso

**Per iscrivere uno studente a un Atelier:**

**Passo 1.** Clicca sul pulsante "Iscrivimi" accanto all'atelier disponibile.

> **Cosa succede:** Si apre un **modal** (finestra sovrapposta) con il titolo
> "Iscrizione: [Nome Atelier]" e il testo "Seleziona una data disponibile:".

**Passo 2.** Nel modal, vedrai una lista di date con informazioni:
- **Data** (es. "15/02/2026") + **orario**
- **Aula** (nome dell'aula o "Aula da definire")
- **Posti disponibili:** badge blu "X posti" oppure badge rosso "PIENO"

**Passo 3.** Clicca sulla data disponibile (quelle con posti).

> **Cosa succede:** Appare una finestra di conferma.

**Passo 4.** Conferma l'iscrizione.

> **Cosa succede:** Il sistema invia una richiesta AJAX a `ajax_enroll_atelier.php`.
> La pagina si ricarica. L'atelier appare ora nella sezione "Iscritti".

> **Attenzione:** Le date con badge "PIENO" non sono cliccabili.

**Per chiudere il modal senza iscrivere:** Clicca il pulsante "Annulla" oppure clicca fuori dal modal.

### 6.7 Scelte Settimanali

Se lo studente ha bisogno che vengano assegnate le scelte settimanali:

> **Cosa vedrai:** Due dropdown (menu a tendina) nella card:
> - **Test Teoria:** dropdown con i quiz disponibili per il settore dello studente
> - **Laboratorio:** dropdown con i laboratori disponibili

**Per assegnare le scelte:**

**Passo 1.** Seleziona un test dal primo dropdown.

**Passo 2.** Seleziona un laboratorio dal secondo dropdown.

**Passo 3.** Clicca il pulsante **"✓ Salva"** (quick-btn salva).

> **Cosa succede:** Il sistema invia una richiesta AJAX a `ajax_save_choices.php`.
> Se il salvataggio ha successo, la pagina si ricarica automaticamente.
> Il badge "Mancano Scelte" scompare dalla card.

> **Attenzione:** Almeno uno dei due campi deve essere selezionato.
> Se entrambi sono vuoti, appare un messaggio di errore.

### 6.8 Le 7 Quick Actions

Ogni card ha fino a **7 pulsanti di azione rapida** (sempre visibili nella vista Standard):

| # | Pulsante | Icona/Label | Stile CSS | Azione |
|---|----------|-------------|-----------|--------|
| 1 | **Report** | 📊 Report | quick-btn report | Apre `student_report.php` con i dati dello studente |
| 2 | **Valutazione** | 👤 Valutazione | quick-btn eval | Apre `coach_evaluation.php` per valutare lo studente |
| 3 | **Profilo** | 📋 Profilo | quick-btn profile | Apre il profilo semplice dello studente |
| 4 | **Colloquio** | 💬 Colloquio | quick-btn colloquio | Apre `reports_v2.php` (Bilancio Competenze) |
| 5 | **Word** | 📄 Word | quick-btn word | Scarica export Word (solo se `is_end6` = true) |
| 6 | **Sollecita** | 📨 Sollecita | quick-btn sollecita | Invia reminder autovalutazione (solo se mancante) |
| 7 | **Salva** | ✓ Salva | quick-btn salva | Salva le scelte settimanali (solo se necessario) |

> **Nota:** I pulsanti 5, 6 e 7 appaiono **solo quando necessario**:
> - "Word" appare solo per studenti a fine percorso (6+ settimane)
> - "Sollecita" appare solo se l'autovalutazione e mancante
> - "Salva" appare solo se ci sono scelte da assegnare

**Per aprire il Report di uno studente:**

**Passo 1.** Clicca sul pulsante "📊 Report" nella card dello studente.

> **Cosa succede:** Si apre la pagina Report Studente (`student_report.php`)
> in una nuova scheda. In alto vedi la foto dello studente, il nome e
> la percentuale globale su sfondo viola sfumato.

**Per scaricare l'export Word:**

**Passo 1.** Verifica che lo studente sia alla settimana 6+ (badge "FINE 6 SETT." presente).

**Passo 2.** Clicca sul pulsante "📄 Word".

> **Cosa succede:** Il browser scarica automaticamente un file `.docx`
> con il report completo dello studente. La richiesta va a `export_word.php`.

**Per inviare un sollecito autovalutazione:**

**Passo 1.** Clicca sul pulsante "📨 Sollecita".

> **Cosa succede:** Il sistema invia una notifica allo studente (via Moodle e/o email).
> Un feedback visivo conferma l'invio.

> **SCREENSHOT 6.8:** Card studente con tutti i pulsanti action evidenziati

