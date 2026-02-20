# Modulo 8: Self-Assessment Dashboard e FTM Scheduler

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: la Self-Assessment Dashboard per monitorare le autovalutazioni (statistiche, filtri, tabella studenti, azioni Vedi/Disabilita/Reminder, assegnazione e workflow completo), e lo Scheduler FTM con il calendario settimanale e mensile, i 5 gruppi colore, la gestione gruppi/attivita/aule/atelier, le 3 modal (Nuovo Gruppo, Nuova Attivita, Prenota Aula Esterno), il registro presenze e la Dashboard Segreteria.

---

## 13. Self-Assessment Dashboard

### 13.1 Cos'e la Self-Assessment Dashboard

La Self-Assessment Dashboard (`/local/selfassessment/index.php`) ti permette di monitorare e gestire le autovalutazioni degli studenti. Da qui puoi vedere chi ha completato, chi e in attesa, e inviare promemoria.

### 13.2 Accesso

**Passo 1.** Vai a `/local/selfassessment/index.php` oppure clicca "Gestione Autovalutazioni" dal FTM Tools Hub (pulsante arancione "Gestisci").

### 13.3 Struttura della Pagina

> **Cosa vedrai:**
>
> **Header** (gradiente blu):
> - Titolo: "📊 Self-Assessment Dashboard"
> - Sottotitolo: "Gestisci le autovalutazioni degli studenti, visualizza lo stato e invia reminder."
> - Pulsante: **"📋 Assegna Autovalutazioni"** (porta a `assign.php`)

### 13.4 Le 4 Statistiche

Sotto l'header trovi **4 stat card**:

| Card | Icona | Bordo | Contenuto |
|------|-------|-------|-----------|
| **Totale studenti** | 👥 | Standard | Numero totale di studenti |
| **Completate** | ✅ | Verde | Autovalutazioni completate |
| **In attesa** | ⏳ | Giallo | Autovalutazioni in attesa |
| **Disabilitate** | 🚫 | Rosso | Autovalutazioni disabilitate |

### 13.5 Filtri

Sotto le statistiche trovi:

- **4 pulsanti toggle** (pill-shaped):
  - "Tutti" - mostra tutti
  - "✅ Completate" - solo completate
  - "⏳ In attesa" - solo in attesa
  - "🚫 Disabilitate" - solo disabilitate

- **Campo ricerca:** "🔍 Cerca studente..." (filtra per nome in tempo reale)

**Per filtrare:**

**Passo 1.** Clicca su "⏳ In attesa".

> **Cosa succede:** La tabella mostra solo gli studenti in attesa.
> Il pulsante appare evidenziato.

**Passo 2.** Digita un nome nel campo ricerca.

> **Cosa succede:** La tabella si filtra in tempo reale mostrando solo
> gli studenti il cui nome contiene il testo digitato.

### 13.6 Tabella Studenti

| Colonna | Contenuto |
|---------|-----------|
| **Studente** | Avatar + Nome + Email |
| **Stato** | Badge colorato: ✅ Completata (verde), ⏳ In attesa (giallo), 🚫 Disabilitata (rosso) |
| **Data completamento** | Data/Ora se completata, altrimenti "-" |
| **Azioni** | Pulsanti contestuali (vedi sotto) |

### 13.7 Le 3 Azioni per Studente

| Azione | Pulsante | Condizione | Cosa fa |
|--------|----------|-----------|---------|
| **Vedi** | "👁️ Vedi" | Solo se completata | Apre `reports_v2.php` con il report |
| **Disabilita/Riabilita** | "🔕 Disabilita" o "🔔 Riabilita" | Sempre | Toggle AJAX (`ajax_toggle.php`) con conferma |
| **Reminder** | "📧 Reminder" | Solo se abilitata + in attesa | Invia promemoria via AJAX |

**Per inviare un reminder:**

**Passo 1.** Trova uno studente con stato "⏳ In attesa".

**Passo 2.** Clicca su "📧 Reminder".

> **Cosa succede:** Il sistema invia una richiesta AJAX a `ajax_toggle.php`
> con `action=reminder`. Lo studente riceve una notifica/email.
> Appare un toast di conferma "Reminder inviato!" (funzione `showNotification`).

**Per disabilitare un'autovalutazione:**

**Passo 1.** Clicca su "🔕 Disabilita" accanto allo studente.

> **Cosa succede:** Appare una conferma. Se confermato, lo stato cambia
> a "Disabilitata" via AJAX. Il badge diventa rosso 🚫.

**Per riabilitare:**

**Passo 1.** Clicca su "🔔 Riabilita" accanto allo studente disabilitato.

### 13.8 Assegnazione Autovalutazioni

**Passo 1.** Clicca il pulsante "📋 Assegna Autovalutazioni" nell'header.

> **Cosa succede:** Si apre la pagina `assign.php` dove puoi:
> - Selezionare un framework di competenze
> - Selezionare un settore
> - Assegnare l'autovalutazione a studenti specifici o a tutti
>
> L'autovalutazione include tutte le competenze del settore selezionato.
> Lo studente vedra la scala Bloom (1-6) per ogni competenza e dovra
> auto-valutarsi.

### 13.9 Paginazione

In fondo alla tabella trovi **link numerati** per la paginazione. Clicca sui numeri per navigare tra le pagine.

### 13.10 Workflow Completo Autovalutazione

Il flusso completo dell'autovalutazione e:

```
1. Coach assegna autovalutazione (assign.php)
   ↓
2. Studente riceve notifica/email
   ↓
3. Studente compila autovalutazione (scala Bloom 1-6 per ogni competenza)
   ↓
4. Coach monitora lo stato (Self-Assessment Dashboard)
   ↓
5. Se lo studente non risponde → Coach invia Reminder
   ↓
6. Autovalutazione completata → Dati disponibili in:
   - Report Studente (radar autovalutazione)
   - Gap Analysis (confronto con quiz)
   - Bilancio Competenze (tab Radar Confronto)
```

> **Suggerimento:** Assegna l'autovalutazione alla settimana 1-2.
> Invia il primo reminder dopo 3 giorni. Se dopo una settimana non c'e risposta,
> prova il contatto diretto e poi escala alla segreteria.

> **SCREENSHOT 13.10:** Self-Assessment Dashboard con filtri e tabella

---

## 14. FTM Scheduler

### 14.1 Cos'e lo Scheduler

Lo Scheduler FTM (`/local/ftm_scheduler/index.php`) e il calendario settimanale e mensile per la pianificazione delle attivita formative. Gestisce gruppi, aule, attivita e presenze.

### 14.2 Accesso

**Passo 1.** Vai a `/local/ftm_scheduler/index.php` oppure clicca "Apri Calendario" dal FTM Tools Hub.

### 14.3 Struttura della Pagina

> **Cosa vedrai:**
>
> **Titolo:** "📅 FTM Scheduler"
>
> **Barra gruppi attivi** (in alto):
> Chip colorati con emoji + nome colore + badge settimana KW.
> Esempio: "🟨 Giallo Sett. 1" "🟥 Rosso Sett. 3" "🟪 Viola Sett. 5"
>
> **Alert automatico** (se presente):
> "🚀 Settimana KW[XX] - Le attivita sono state generate automaticamente"
>
> **5 Pulsanti azione** (in alto a destra):
> - "👥 Gestione Settori" (btn-secondary)
> - "📊 Importa Excel" (btn-secondary)
> - "➕ Nuovo Gruppo" (btn-success verde)
> - "📅 Nuova Attivita" (btn-primary blu)
> - "🏢 Prenota Aula (Esterno)" (btn-secondary)

### 14.4 Le 5 Statistiche

| Card | Contenuto |
|------|-----------|
| **Gruppi Attivi** | Numero di gruppi attualmente in corso |
| **Studenti in Percorso** | Totale studenti nelle attivita |
| **Attivita Settimana** | Numero attivita della settimana corrente |
| **Aule Utilizzate** | Numero aule occupate |
| **Progetti Esterni** | Numero prenotazioni esterne |

### 14.5 Tab dello Scheduler

Lo Scheduler ha **fino a 7 tab** (alcuni condizionali):

| # | Tab | Icona | Contenuto |
|---|-----|-------|-----------|
| 1 | **Calendario** | 📅 | Vista settimanale/mensile |
| 2 | **Gruppi** | 🎨 | Card gruppi con stato e dettagli |
| 3 | **Attivita** | 📋 | Tabella attivita con filtri |
| 4 | **Aule** | 🏫 | Gestione aule e disponibilita |
| 5 | **Atelier** | 🎭 | Sessioni atelier speciali |
| 6 | **Presenze** | 📋 | Per chi ha la capability (condizionale) |
| 7 | **Segreteria** | 🏢 | Solo admin (condizionale) |

### 14.6 Vista Settimanale

**Passo 1.** Nel tab Calendario, assicurati che "Settimana" sia selezionato (toggle in alto).

> **Cosa vedrai:**
>
> **Navigazione settimana:**
> - "Settimana prec." (pulsante a sinistra)
> - Titolo: "KW[XX] | [intervallo date] [mese] [anno]"
> - "Settimana succ." (pulsante a destra)
> - "Oggi" (pulsante per tornare alla settimana corrente)
> - "KW01 [anno]" (pulsante per tornare a inizio anno)
> - Selettore anno (dropdown)
>
> **Filtri riga:**
> - Gruppo (dropdown con tutti i gruppi)
> - Aula (dropdown con aula e capienza)
> - Tipo (dropdown: Attivita Gruppo, Atelier, Progetti Esterni)
> - Pulsante Reset filtri
>
> **Griglia calendario:**

```
         │ Lunedi 10/02  │ Martedi 11/02 │ Mercoledi 12/02 │ Giovedi 13/02 │ Venerdi 14/02 │
─────────┼───────────────┼───────────────┼─────────────────┼───────────────┼───────────────┤
MATTINA  │ [🟡 Teoria    │ [🟡 Lab       │                 │ [🟥 Teoria    │ [🟥 Lab       │
08:30-   │  Aula A1      │  Lab Mecc     │    REMOTO       │  Aula B2      │  Lab Auto     │
11:45    │  Coach GM     │  Coach RB  ]  │                 │  Coach GM  ]  │  Coach FM  ]  │
─────────┼───────────────┼───────────────┼─────────────────┼───────────────┼───────────────┤
POMERIG. │ [🟡 Pratica   │               │                 │ [🟥 Pratica   │               │
13:15-   │  Lab Mecc     │               │    REMOTO       │  Lab Auto     │               │
16:30    │  Coach GM  ]  │               │                 │  Coach FM  ]  │               │
─────────┴───────────────┴───────────────┴─────────────────┴───────────────┴───────────────┘
```

**Blocchi attivita:**
- Colorati per gruppo (giallo, grigio, rosso, marrone, viola)
- Mostrano: pallino colore + nome attivita, aula abbreviata + iniziali coach, iscritti/max
- **Bordo tratteggiato blu** (#DBEAFE) = prenotazioni esterne
- **"REMOTO"** = mercoledi/venerdi senza attivita

**Per vedere i dettagli di un'attivita:**

**Passo 1.** Clicca su un blocco attivita nel calendario.

> **Cosa succede:** Si apre un modal con header colorato per gruppo:
> "🟡 Attivita - Gruppo [Nome]". Il contenuto viene caricato via AJAX.
> In fondo: pulsanti "Chiudi" e "✏️ Modifica".

### 14.7 Vista Mensile

**Passo 1.** Clicca su "Mese" nel toggle di vista.

> **Cosa vedrai:** Una griglia con:
> - Colonna KW (numeri settimana) + 5 colonne giorni (Lun-Ven)
> - Navigazione: "Mese prec." / "Mese succ." / "Mese Corrente"
> - Ogni cella mostra: numero giorno (in alto a destra), mini blocchi attivita (max 4)
> - Se ci sono piu di 4 attivita: "+X altre..." cliccabile

### 14.8 I 5 Gruppi Colore

| Emoji | Nome | Codice | Uso tipico |
|-------|------|--------|------------|
| 🟨 | Giallo | #FFFF00 | Gruppo A - testo scuro |
| ⬜ | Grigio | #808080 | Gruppo B - testo bianco |
| 🟥 | Rosso | #FF0000 | Gruppo C - testo bianco |
| 🟫 | Marrone | #996633 | Gruppo D - testo bianco |
| 🟪 | Viola | #7030A0 | Gruppo E - testo bianco |
| 🔵 | Esterno | #DBEAFE | Progetti esterni - bordo tratteggiato |

### 14.9 Tab Gruppi

**Passo 1.** Clicca su "🎨 Gruppi".

> **Cosa vedrai:**
> - **Filtro stato:** dropdown (Tutti, Attivi, Completati, In arrivo)
> - **Griglia card** (3 per riga), ogni card:
>   - Header colorato: "Gruppo [Colore]" + badge "Sett. X di 6"
>   - 📅 Data ingresso + KW
>   - 👥 Studenti: X/10
>   - 📊 Stato: badge (Attivo verde / In pianificazione blu / Completato grigio)
>   - 🎯 Fine prevista: data
>   - Barra progresso (se attivo)
>   - Footer: "👁 Dettagli" + "👥 Studenti"

### 14.10 Creare un Nuovo Gruppo

**Passo 1.** Clicca su "➕ Nuovo Gruppo" (pulsante verde in alto).

> **Cosa succede:** Si apre il modal "➕ Crea Nuovo Gruppo".

**Passo 2.** Seleziona il colore cliccando su uno dei 5 pulsanti emoji colorati.

**Passo 3.** Il nome gruppo si genera automaticamente: "Gruppo [Colore] - KW[XX]".

**Passo 4.** Seleziona la settimana calendario (KW) dal dropdown.

> **Cosa succede:** Il nome si aggiorna automaticamente con il nuovo KW.

**Passo 5.** Inserisci la data di inizio (Lunedi).

**Passo 6.** Clicca "✅ Crea Gruppo e Genera Attivita".

> **Cosa succede:** Il sistema:
> 1. Crea il gruppo nel database
> 2. Genera automaticamente le attivita della settimana 1
> 3. Iscrive tutti gli studenti assegnati
> 4. Invia notifiche email e calendario
>
> Il box info nel modal spiega: "💡 Cosa succede quando crei il gruppo"

### 14.11 Creare una Nuova Attivita

**Passo 1.** Clicca su "📅 Nuova Attivita" (pulsante blu in alto).

> **Cosa succede:** Si apre il modal "📅 Crea Nuova Attivita".

**Campi del form:**

| Campo | Tipo | Obbligatorio | Note |
|-------|------|-------------|------|
| **Nome Attivita** | Testo | Si | Nome dell'attivita |
| **Tipo Attivita** | Dropdown | Si | Week 1, Week 2 (Lun-Mar), Week 2 (Gio-Ven), Weeks 3-5, Week 6, Atelier |
| **Gruppo** | Dropdown | No | Selezione gruppo (o nessun gruppo) |
| **Data** | Date picker | Si | Data dell'attivita |
| **Fascia Oraria** | Dropdown | Si | Mattina, Pomeriggio, Giornata intera |
| **Aula** | Dropdown | No | Selezione aula (opzionale) |
| **Coach/Docente** | Dropdown | No | Lista coach con iniziali |
| **Partecipanti Max** | Numero | No | Default 10, range 1-50 |
| **Note** | Textarea | No | Note aggiuntive |

**Passo 2.** Compila i campi.

**Passo 3.** Clicca "📅 Crea Attivita".

### 14.12 Prenotare Aula per Esterno

**Passo 1.** Clicca su "🏢 Prenota Aula (Esterno)".

> **Cosa succede:** Si apre il modal "🏢 Prenota Aula per Progetto Esterno".

**Campi:**

| Campo | Opzioni |
|-------|---------|
| **Nome Progetto** | BIT URAR, BIT AI, Corso Extra LADI, Altro |
| **Aula** | Lista aule con capienza |
| **Data** | Date picker |
| **Fascia Oraria** | Giornata intera, Solo mattina, Solo pomeriggio |
| **Responsabile** | Lista responsabili (GM, RB, CB, FM, Altro) |

**Passo 2.** Compila e clicca "📅 Prenota Aula".

> **Risultato:** L'aula viene bloccata per il progetto esterno.
> Nel calendario appare con **bordo tratteggiato blu** e icona 🏢.

### 14.13 Tab Attivita

**Passo 1.** Clicca su "📋 Attivita".

> **Cosa vedrai:** Una tabella con filtri + pulsante "📥 Export Excel".

**Filtri:** Gruppo, Settimana (KW), Tipo (Settimana 1, Sett. 2 Test, Atelier, Esterni).

**Colonne tabella:**

| Colonna | Contenuto |
|---------|-----------|
| Attivita | Nome (con 🏢 se esterno) |
| Gruppo | Badge colorato |
| Data/Ora | Data + fascia oraria |
| Aula | Badge con nome abbreviato |
| Docente | Iniziali coach |
| Iscritti | Conteggio/max |
| Tipo | Etichetta tipo |
| Azioni | 👁 Dettagli + ✏️ Modifica |

### 14.14 Tab Aule

**Passo 1.** Clicca su "🏫 Aule".

> **Cosa vedrai:** Una griglia di **card aula** (stile simile alle card gruppo),
> una card per ogni aula disponibile nel centro.

**Struttura di ogni card aula:**

> **Header colorato** (colore diverso per ogni aula):
> - AULA 1: sfondo blu scuro (#1E40AF), icona 🖥️
> - AULA 2: sfondo verde scuro (#065F46), icona 📚
> - AULA 3: sfondo marrone (#92400E), icona 🔧
>
> Nel badge dell'header: numero postazioni (es. "8 postazioni")

> **Corpo della card:**
> - **Tipo:** indica se e "🔬 Laboratorio" o "📖 Aula Teoria"
> - **Questa settimana:** mostra il gruppo attualmente assegnato con emoji colore
>   (es. "🟡 Giallo - Sett. 1") oppure "Libera" in verde se non occupata
> - **Prenotazioni esterne** (se presenti): es. "🏢 BIT URAR (tutto il giorno)"
>   evidenziato in rosso
> - **Attrezzature/Utilizzo:** tag pill grigie con le capabilities dell'aula

**Le 3 aule standard del centro:**

| Aula | Tipo | Postazioni | Attrezzature |
|------|------|-----------|-------------|
| 🖥️ **AULA 1** | Laboratorio | 8 | Elettricita, Automazione, Pneumatica, Idraulica |
| 📚 **AULA 2** | Aula Teoria | 20 | Lezioni, Quiz/Test, Atelier |
| 🔧 **AULA 3** | Lab CNC | 12 | CNC Fresa, CNC Tornio, SolidWorks |

> **Suggerimento:** Le attrezzature sono visualizzate come tag arrotondati con
> sfondo grigio chiaro (#e9ecef). Se l'aula ha attrezzature personalizzate
> nel database, quelle sostituiscono le attrezzature predefinite.

> **SCREENSHOT 14.14:** Le 3 card aula con colori diversi e tag attrezzature

### 14.15 Tab Atelier

**Passo 1.** Clicca su "🎭 Atelier".

> **Cosa vedrai:**
>
> **Alert in alto** (sfondo giallo/warning):
> "⏳ **Atelier disponibili dalla Settimana 3**"
> Seguito dal messaggio: "Il [emoji] Gruppo [Colore] e attualmente in Settimana X.
> Gli atelier saranno disponibili per l'iscrizione a partire dalla Settimana 3."
>
> **Titolo:** "📋 Catalogo Atelier"
>
> **Tabella catalogo** con le seguenti colonne:

| Colonna | Contenuto |
|---------|-----------|
| **Atelier** | Nome completo dell'atelier (grassetto). Se obbligatorio: preceduto da ⭐ |
| **Codice Excel** | Codice abbreviato per import/export (es. "At. Canali", "At. CV") |
| **Settimana Tipica** | Range settimane (es. "Sett. 3-5"). Se obbligatorio: "(OBBLIGATORIO)" |
| **Giorno/Ora** | Giorno e fascia tipica (es. "Mercoledi Matt.", "Mercoledi Pom.") |
| **Max Part.** | Numero massimo partecipanti (tipicamente 10) |
| **Stato** | Badge: "Attivo" (verde) o "Obbligatorio" (giallo/marrone) |

**Gli atelier predefiniti nel catalogo:**

| # | Nome Atelier | Codice | Settimane | Giorno | Obbl. |
|---|-------------|--------|-----------|--------|-------|
| 1 | Canali - strumenti e mercato del lavoro | At. Canali | 3-5 | Mer. Matt. | No |
| 2 | Colloquio di lavoro | At. Collo. | 3-5 | Mer. Pom. | No |
| 3 | Curriculum Vitae - redazione/revisione | At. CV | 3-5 | Mercoledi | No |
| 4 | Lettere AC + RA - redazione/revisione | At. AC/RA | 4-6 | Mercoledi | No |
| 5 | Agenzie e guadagno intermedio | At. Ag. e GI | 4-6 | Mer. Matt. | No |
| 6 | ⭐ Bilancio di fine misura | BILANCIO | 6 | Mer. 15:00-16:30 | **Si** |

> **Attenzione:** L'atelier "Bilancio di fine misura" e **obbligatorio** per
> tutti gli studenti in settimana 6. La riga e evidenziata con sfondo giallo
> chiaro (#FEF3C7) per distinguerla dagli atelier opzionali.

> **Nota tecnica:** Se gli atelier sono configurati nel database, vengono
> caricati dinamicamente. Il giorno viene formattato automaticamente dal sistema
> (Lunedi, Martedi, Mercoledi, Giovedi, Venerdi) con la fascia oraria
> (Matt., Pom.). Altrimenti vengono mostrati i 6 atelier predefiniti.

> **SCREENSHOT 14.15:** Catalogo atelier con tabella e riga obbligatoria evidenziata

### 14.16 Tipi di Attivita

Il sistema supporta diversi tipi di attivita con strutture specifiche:

| Tipo | Descrizione | Giorni tipici |
|------|-------------|---------------|
| **Week 1** | Attivita della prima settimana (accoglienza, orientamento) | Lun-Ven |
| **Week 2 (Lun-Mar)** | Prima parte settimana 2 (test, teoria) | Lunedi-Martedi |
| **Week 2 (Gio-Ven)** | Seconda parte settimana 2 (pratica, lab) | Giovedi-Venerdi |
| **Weeks 3-5** | Attivita settimane centrali (formazione, pratica) | Variabile |
| **Week 6** | Settimana conclusiva (valutazioni finali, report) | Lun-Ven |
| **Atelier** | Sessioni pratiche specializzate | Date specifiche |

### 14.17 Fasce Orarie

| Fascia | Orario |
|--------|--------|
| **Mattina** | 08:30 - 11:45 |
| **Pomeriggio** | 13:15 - 16:30 |
| **Giornata intera** | 08:30 - 16:30 |

> **Nota:** Il mercoledi e il venerdi senza attivita programmate mostrano
> l'indicatore "REMOTO" nel calendario settimanale.

> **SCREENSHOT 14.17:** Scheduler con vista settimanale e blocchi colorati

### 14.18 Tab Presenze (Condizionale)

Questa tab e visibile solo se hai la capability `local/ftm_scheduler:markattendance`.
Cliccandoci vieni portato alla **pagina di registrazione presenze** (`attendance.php`).

**Passo 1.** Clicca su "📋 Presenze" nella barra tab dello Scheduler.

> **Cosa succede:** Si apre la pagina `attendance.php` (pagina separata, non un tab inline).

**Passo 2.** Seleziona la data dal selettore in alto.

> **Cosa vedrai:**
>
> **Barra selettore data:**
> - Pulsante "◀ Ieri" (navigazione indietro)
> - Campo data (input type="date") con la data corrente
> - Pulsante "Domani ▶" (navigazione avanti)
> - Pulsante "Oggi" (sfondo blu, solo se non sei gia su oggi)
> - Testo del giorno corrente: "Lunedi 16 Febbraio 2026" (formattazione completa)
>
> **Se non ci sono attivita nella data selezionata:**
> Un'area vuota con icona calendario grande e il messaggio
> "Nessuna attivita programmata" / "Non ci sono attivita per il [data]".

**Passo 3.** Clicca su una card attivita del giorno.

> **Cosa vedrai:** Le attivita sono presentate come **card cliccabili** in una
> griglia responsiva (min 280px per card). Ogni card mostra:
> - **Orario** in grande (es. "08:30") - font 24px, grassetto, colore blu #1e40af
> - **Nome attivita** (es. "Laboratorio Meccanica") - font 16px, grassetto
> - **Aula** con icona 🏢 (es. "AULA 3")
> - **Badge iscritti** (es. "8 iscritti") - sfondo azzurro #dbeafe
>
> La card selezionata ha bordo blu (#3b82f6) e sfondo azzurro chiaro (#eff6ff).
> Al hover tutte le card mostrano un'ombra e si sollevano leggermente.

> **Cosa succede:** Sotto le card appare il **pannello presenze** per l'attivita selezionata.

**Passo 4.** Registra le presenze nel pannello.

> **Cosa vedrai:**
>
> **Header del pannello** (gradiente blu #1e40af → #3b82f6, testo bianco):
> - Nome attivita
> - Orario inizio - fine | Nome aula
> - **4 statistiche:** Iscritti (totale), Presenti (conteggio), Assenti (conteggio), Da registrare (conteggio)
>
> **Tabella studenti** con colonne:
>
> | Colonna | Contenuto |
> |---------|-----------|
> | **Studente** | Avatar (iniziali in cerchio grigio) + Nome completo + Email |
> | **Gruppo** | Badge colorato con nome gruppo (giallo/grigio/rosso/marrone/viola) |
> | **Stato** | Badge: "✔ Presente" (verde), "✘ Assente" (rosso), "○ Da registrare" (giallo) |
> | **Azioni** | 2 pulsanti: "✔ Presente" (verde #22c55e) e "✘ Assente" (rosso #ef4444) |

**Per segnare presente:**

**Passo 5.** Clicca il pulsante "✔ Presente" (verde) nella riga dello studente.

> **Cosa succede:** La pagina si ricarica. Lo stato cambia in "✔ Presente" (badge verde).
> Il pulsante "Presente" diventa disabilitato (opacita ridotta, non cliccabile).

**Per segnare assente:**

**Passo 6.** Clicca il pulsante "✘ Assente" (rosso) nella riga dello studente.

> **Cosa succede:** Appare un dialogo di conferma:
> "Confermi l'assenza? Verra inviata una notifica al coach e alla segreteria."
> Se confermi, la pagina si ricarica con lo stato "✘ Assente" e una notifica
> viene inviata automaticamente.

> **Attenzione:** La marcatura di assenza **invia una notifica automatica** al
> coach responsabile e alla segreteria. Questa azione non puo essere annullata
> facilmente (la notifica e gia partita).

**Sotto la tabella studenti:**

> **Barra azioni rapide** (sfondo grigio #f8f9fa):
> - Pulsante "✔ Segna tutti presenti" (verde #22c55e) - segna tutti come presenti in un click
> - Pulsante "💾 Esporta lista" (blu #3b82f6) - scarica un file CSV con le presenze

**Per esportare le presenze:**

**Passo 7.** Clicca "💾 Esporta lista".

> **Cosa succede:** Viene scaricato un file CSV con nome `presenze_[data].csv`
> contenente le colonne: Nome, Email, Gruppo, Stato.

> **Nota tecnica:** Le informazioni su chi ha registrato la presenza sono
> salvate nel database. Sotto lo stato appare la dicitura:
> "Registrato da [Nome Coach] il [data] [ora]".

> **SCREENSHOT 14.18:** Pagina presenze con card attivita e tabella studenti

### 14.19 Tab Segreteria (Solo Admin)

Questa tab e visibile solo se hai la capability `local/ftm_scheduler:manage` (tipicamente admin e segreteria). Cliccandoci vieni portato alla **Dashboard Segreteria** (`secretary_dashboard.php`).

**Passo 1.** Clicca su "🏢 Segreteria" nella barra tab dello Scheduler.

> **Cosa succede:** Si apre la Dashboard Segreteria in una pagina dedicata.

> **Cosa vedrai:** Una dashboard di gestione avanzata con le seguenti sezioni:

**Sezione 1: Occupazione Aule**

> Una matrice settimanale che mostra per ogni aula:
> - 5 giorni (Lun-Ven) x 2 fasce (Mattina/Pomeriggio) = 10 slot
> - Slot occupati colorati per gruppo
> - Slot con prenotazioni esterne evidenziati
> - Conteggio ore totali per aula
> - Slot liberi vs occupati
> - **Percentuale occupazione** per ogni aula (es. "70%")

**Sezione 2: Carico Docenti**

> Una tabella che mostra per ogni coach/docente:
> - Nome e iniziali
> - Ore totali nella settimana
> - Numero attivita assegnate
> - Ore per giorno (Lun-Ven)
> - **Soglia sovraccarico:** 35 ore/settimana. Se superata, il coach e evidenziato in rosso.

**Sezione 3: Rilevamento Conflitti**

> Il sistema rileva automaticamente i conflitti:
> - **Conflitti aula:** stessa aula prenotata due volte nello stesso slot
> - **Conflitti docente:** stesso docente assegnato a due attivita contemporanee
> - **Conflitti prenotazioni esterne:** sovrapposizioni con prenotazioni esterne
>
> I conflitti sono mostrati come alert con messaggio dettagliato:
> "Conflitto aula: [Nome Aula] il [data] [ora]"

**Sezione 4: Pianificazione Rapida e Statistiche**

> Strumenti di pianificazione rapida e statistiche generali sulla settimana.

> **Suggerimento:** La Dashboard Segreteria e lo strumento ideale per chi
> gestisce le risorse (aule, docenti, calendari). Non e necessaria per il
> lavoro quotidiano del coach, che puo usare il tab Calendario e Gruppi.

> **SCREENSHOT 14.19:** Dashboard Segreteria con matrice occupazione aule

---
