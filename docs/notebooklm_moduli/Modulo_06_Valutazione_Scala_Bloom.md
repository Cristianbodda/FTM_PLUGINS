# Modulo 6: Valutazione Formatore e Scala Bloom

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: cos'e la valutazione formatore, come accedere, la struttura della pagina con header gradiente, la selezione settore con medaglie, la compilazione per aree (A-G), la scala Bloom da 0 a 6 con esempi pratici per ogni settore (Meccanica, Automobile, Automazione), il salvataggio (Bozza/Completata/Firmata), la riapertura per modifiche, e la guida dettagliata su come decidere il livello Bloom corretto.

---

# PARTE 1: La Valutazione Formatore

## 10. Valutazione Formatore

### 10.1 Cos'e la Valutazione Formatore

La pagina `coach_evaluation.php` ti permette di registrare la tua valutazione diretta delle competenze dello studente sulla scala Bloom (0-6).

Questa valutazione si basa su:
- Osservazione diretta in laboratorio
- Colloqui tecnici
- Prove pratiche
- Comportamento generale

### 10.2 Accesso alla Valutazione

**Dalla Dashboard:**

**Passo 1.** Clicca sul pulsante "👤 Valutazione" nella card dello studente.

> **Cosa succede:** Si apre `coach_evaluation.php` con i dati dello studente.
> Se non esiste ancora una valutazione per il settore primario, ne viene creata
> una automaticamente in stato "Bozza".

**Dal Report Studente:**

**Passo 1.** Nel report, clicca sul link "← Torna al Report" in fondo alla pagina
(oppure usa il pulsante "Valutazione Formatore" nel footer delle card).

### 10.3 Struttura della Pagina

> **Cosa vedrai:**
>
> **Header** (sfondo gradiente viola #667eea -> #764ba2):
> - Titolo "Valutazione Formatore"
> - Nome studente e email
> - **Selettore settore** (dropdown in alto a destra)
> - Badge settore con medaglia e stato (📝 Draft, ✅ Completed, 🔒 Signed)
>
> **Banner stato** (sotto l'header):
> - Giallo (#fff3cd) se Bozza: "Valutazione in bozza - puoi modificare liberamente"
> - Verde (#d4edda) se Completata: "Valutazione completata"
> - Grigio (#e2e3e5) se Firmata: "Valutazione firmata e bloccata"
>
> **Barra statistiche:**
> - "X/Y competenze valutate" | "Z N/O" | "Media: M.M"
>
> **Legenda Bloom** (collassabile):
> - Griglia con tutti i livelli 0-6 e descrizioni
>
> **Accordion aree** (A, B, C, D, E, F, G):
> - Ogni area espandibile/comprimibile con contatore "X/Y"
>
> **Note generali** (textarea in fondo):
> - Campo di testo libero per osservazioni generali
>
> **Barra azioni** (pulsanti in fondo):
> - I pulsanti cambiano in base allo stato della valutazione

### 10.4 Selezione del Settore

**Passo 1.** Nel dropdown "Settore da valutare" (in alto a destra dell'header), vedi i settori dello studente:

> **Cosa vedrai:**
> - 🥇 Meccanica (settore primario - proposto automaticamente)
> - 🥈 Automobile (settore secondario)
> - 🥉 Automazione (settore terziario)
> - 📊 Logistica (rilevato da quiz)

**Passo 2.** Seleziona un settore diverso se necessario.

> **Cosa succede:** La pagina si ricarica (`changeSector(newSector)`).
> Le competenze cambiano in base al nuovo settore.
> Se esiste gia una valutazione per quel settore, viene caricata.
> Altrimenti ne viene creata una nuova in bozza.

**Sotto il dropdown:** "🥇 Primario | 🥈 Secondario | 🥉 Terziario | 📊 Da quiz"

### 10.5 Compilazione delle Competenze

Le competenze sono raggruppate per **Area** (accordion espandibile).

**Passo 1.** Clicca sull'intestazione di un'area (es. "A. Accoglienza, diagnosi...").

> **Cosa succede:** L'area si espande mostrando tutte le competenze.
> L'intestazione mostra il contatore "X/Y" (competenze valutate su totali).
> Il triangolino ▼ ruota quando l'area e collassata.

**Per ogni competenza vedrai:**

```
┌───────────────────────────────────────────────────────────┐
│ MECCANICA_MIS_01          [N/O] [1] [2] [3] [4] [5] [6] │
│ Utilizzo del calibro      [                              ]│
│ Descrizione competenza... │           Note (campo testo)  │
└───────────────────────────────────────────────────────────┘
```

- **Codice competenza** (in viola, font monospace, sfondo #f0f0ff)
- **Nome competenza** (grassetto)
- **Descrizione** (se disponibile, testo piu piccolo)
- **7 pulsanti di valutazione**: N/O, 1, 2, 3, 4, 5, 6
- **Campo note** (input testo per annotazioni specifiche)

**Passo 2.** Clicca sul pulsante del livello appropriato (es. "4" per Analizzare).

> **Cosa succede:** Il pulsante diventa viola (#667eea) con testo bianco.
> Gli altri pulsanti tornano grigi. Il salvataggio parte automaticamente
> dopo **500ms** (debounce) tramite AJAX a `ajax_save_evaluation.php`.

**Passo 3.** (Opzionale) Scrivi una nota specifica nel campo testo accanto.

> **Cosa succede:** Quando esci dal campo (onchange), la nota viene
> inclusa nel prossimo salvataggio automatico.

**Feedback di salvataggio:**
> In basso a destra appare un indicatore "Salvando..." (sfondo #667eea, testo bianco).
> Scompare dopo 2 secondi con animazione fade.

> **Suggerimento:** Non devi salvare manualmente dopo ogni valutazione.
> Il sistema salva automaticamente. Puoi compilare velocemente cliccando
> i pulsanti uno dopo l'altro.

### 10.6 Scala Bloom - Guida Rapida

| Livello | Pulsante | Nome | Cosa significa |
|---------|----------|------|----------------|
| **0** | N/O | Non Osservato | Non hai avuto modo di valutare questa competenza |
| **1** | 1 | Ricordare | Lo studente ricorda fatti e termini base |
| **2** | 2 | Comprendere | Spiega idee o concetti con parole proprie |
| **3** | 3 | Applicare | Usa le conoscenze in situazioni standard |
| **4** | 4 | Analizzare | Distingue le parti, identifica pattern |
| **5** | 5 | Valutare | Giustifica decisioni, esprime giudizi fondati |
| **6** | 6 | Creare | Produce soluzioni originali, sviluppa nuovi approcci |

> Per la guida completa con esempi per settore, vedi [Appendice B](#appendice-b-scala-bloom---guida-dettagliata-con-esempi).

### 10.7 Note Generali

In fondo alla pagina, sotto tutte le aree:

> **Cosa vedrai:** Un box grigio (#f8f9fa) con titolo "Note Generali" e una textarea.

**Passo 1.** Scrivi le tue osservazioni generali sullo studente.

> **Cosa succede:** Quando esci dal campo (onchange), le note vengono salvate
> automaticamente via AJAX (`action=save_notes`).

### 10.8 Salvataggio e Stati

La valutazione ha **3 stati** con transizioni specifiche:

```
           ┌──────────────────────┐
           │       BOZZA          │  <- Stato iniziale
           │  (modificabile)      │
           └──────┬───────────────┘
                  │ "Salva e Completa"
                  ▼
           ┌──────────────────────┐
           │     COMPLETATA       │
           │  (ancora modific.)   │
           └──────┬───────────────┘
                  │ "Firma Valutazione"
                  ▼
           ┌──────────────────────┐
           │       FIRMATA        │
           │  (bloccata)          │
           └──────────────────────┘
                  │ "🔓 Riapri per Modifiche"
                  ▼
           (Torna a BOZZA)
```

**Pulsanti disponibili per stato:**

| Stato | Pulsanti visibili |
|-------|-------------------|
| **Bozza** | "Salva Bozza" (blu #667eea) + "Salva e Completa" (verde #28a745) + "Elimina" (grigio) |
| **Completata** | "Firma Valutazione" (rosso #dc3545) + "🔓 Riapri per Modifiche" (teal #17a2b8) |
| **Firmata** | "🔓 Riapri per Modifiche" (teal #17a2b8) |

**Per completare una valutazione:**

**Passo 1.** Con la valutazione in stato Bozza, clicca "Salva e Completa".

> **Cosa succede:** Appare una finestra di conferma. Se confermi, il sistema
> prima salva tutti i rating pendenti, poi imposta lo stato a "Completata".
> La pagina si ricarica con il banner verde.

**Per firmare una valutazione:**

**Passo 1.** Con la valutazione Completata, clicca "Firma Valutazione".

> **Cosa succede:** Appare una finestra di conferma ("Sei sicuro di voler
> firmare questa valutazione?"). Se confermi, la valutazione diventa Firmata.
> I pulsanti di valutazione si disabilitano (grigio, cursor: not-allowed).

**Per riaprire una valutazione firmata:**

**Passo 1.** Clicca "🔓 Riapri per Modifiche".

> **Cosa succede:** Appare la conferma "Sei sicuro di voler riaprire
> questa valutazione per modifiche?". Se confermi, lo stato torna a Bozza.
> Puoi nuovamente modificare i valori.

### 10.9 Pulsanti Aggiuntivi

| Pulsante | Condizione | Azione |
|----------|-----------|--------|
| **"Autorizza Studente"** | Se hai la capability `authorizestudentview` e lo studente non puo vedere | Rende la valutazione visibile allo studente |
| **"Revoca Autorizzazione"** | Se lo studente puo attualmente vedere | Toglie la visibilita allo studente |
| **"← Torna al Report"** | Sempre visibile (grigio) | Torna a `student_report.php` |

> **SCREENSHOT 10.9:** Pagina valutazione con accordion area espansa e pulsanti

---

---

# PARTE 2: Scala Bloom - Guida Dettagliata con Esempi

Questa sezione approfondisce ogni livello della scala Bloom con esempi concreti per i settori professionali.

## Appendice B: Scala Bloom - Guida Dettagliata con Esempi

La Tassonomia di Bloom e la scala utilizzata per tutte le valutazioni nel sistema FTM. Ogni livello rappresenta un grado crescente di complessita cognitiva.

### Livello 0 - N/O (Non Osservato)

**Definizione:** Non hai avuto l'opportunita di osservare questa competenza.

**Quando usarlo:**
- Lo studente e appena arrivato e non ha ancora affrontato l'argomento
- Non ci sono state situazioni in cui la competenza potesse emergere
- Lo studente era assente durante le attivita rilevanti

> **Attenzione:** Usa N/O solo quando davvero non hai potuto osservare.
> Non usarlo come "scorciatoia" per competenze che non sai valutare.

### Livello 1 - Ricordare

**Definizione:** Lo studente ricorda fatti, termini e concetti di base.

**Verbi associati:** elencare, definire, ripetere, riconoscere, nominare, descrivere

**Esempio MECCANICA:** Lo studente sa elencare i principali strumenti di misura (calibro, micrometro, comparatore) e le loro caratteristiche di base.

**Esempio AUTOMOBILE:** Lo studente riconosce e nomina i componenti principali del sistema frenante (disco, pastiglia, pinza, liquido freni).

**Esempio AUTOMAZIONE:** Lo studente elenca i componenti base di un circuito elettrico (resistenza, condensatore, rele) e li riconosce visivamente.

**Come riconoscerlo:** Lo studente risponde correttamente a domande "Cos'e...?", "Quali sono...?", "Elenca...". Ma non sa spiegare il perche.

### Livello 2 - Comprendere

**Definizione:** Lo studente spiega idee o concetti con parole proprie.

**Verbi associati:** spiegare, descrivere, interpretare, riassumere, classificare, confrontare

**Esempio MECCANICA:** Lo studente spiega perche si usa il micrometro invece del calibro per misure di precisione e descrive la differenza di risoluzione.

**Esempio AUTOMOBILE:** Lo studente spiega il funzionamento del circuito frenante idraulico e perche il liquido freni deve essere sostituito periodicamente.

**Esempio AUTOMAZIONE:** Lo studente descrive come un PLC legge gli ingressi, esegue il programma e attiva le uscite, usando parole proprie.

**Come riconoscerlo:** Lo studente puo rispondere "Perche...?" e "Come funziona...?" con spiegazioni logiche, anche se semplificate.

### Livello 3 - Applicare

**Definizione:** Lo studente usa le conoscenze in situazioni standard.

**Verbi associati:** utilizzare, eseguire, implementare, applicare, risolvere, dimostrare

**Esempio MECCANICA:** Lo studente esegue correttamente una misurazione con il micrometro su un pezzo standard, seguendo la procedura appresa.

**Esempio AUTOMOBILE:** Lo studente esegue uno spurgo freni seguendo la procedura standard, utilizzando correttamente l'attrezzatura.

**Esempio AUTOMAZIONE:** Lo studente scrive un semplice programma ladder per il PLC seguendo un esempio dato, con ingressi e uscite base.

**Come riconoscerlo:** Lo studente esegue le procedure in modo corretto quando le condizioni sono standard. In situazioni nuove puo avere difficolta.

### Livello 4 - Analizzare

**Definizione:** Lo studente distingue le parti, identifica pattern e relazioni causa-effetto.

**Verbi associati:** analizzare, distinguere, confrontare, diagnosticare, investigare, scomporre

**Esempio MECCANICA:** Lo studente analizza una misura fuori tolleranza, identifica la causa (usura utensile, errore setup, deformazione termica) e propone la correzione.

**Esempio AUTOMOBILE:** Lo studente diagnostica un problema frenante partendo dai sintomi (rumore, vibrazione, pedale lungo), distinguendo le possibili cause e testandole.

**Esempio AUTOMAZIONE:** Lo studente analizza un malfunzionamento della linea automatizzata, isola il componente guasto confrontando il comportamento atteso vs reale.

**Come riconoscerlo:** Lo studente non si limita a seguire procedure, ma investiga attivamente, fa domande pertinenti e arriva a conclusioni logiche.

### Livello 5 - Valutare

**Definizione:** Lo studente giustifica decisioni ed esprime giudizi fondati.

**Verbi associati:** valutare, giudicare, giustificare, criticare, raccomandare, argomentare

**Esempio MECCANICA:** Lo studente valuta quale processo di lavorazione e piu adatto per un dato pezzo, considerando tolleranze, materiale, quantita, costo e giustifica la scelta.

**Esempio AUTOMOBILE:** Lo studente valuta se un componente sospensione deve essere sostituito o puo essere recuperato, motivando la decisione con dati oggettivi.

**Esempio AUTOMAZIONE:** Lo studente confronta due soluzioni di automazione per lo stesso processo, valutando pro e contro in termini di affidabilita, costo e manutenibilita.

**Come riconoscerlo:** Lo studente prende decisioni autonome e le motiva. Sa dire "Ho scelto X perche..." con argomentazioni solide.

### Livello 6 - Creare

**Definizione:** Lo studente produce soluzioni originali e sviluppa nuovi approcci.

**Verbi associati:** creare, progettare, sviluppare, inventare, pianificare, costruire

**Esempio MECCANICA:** Lo studente progetta un attrezzaggio personalizzato per una lavorazione non standard, disegnando e realizzando la soluzione.

**Esempio AUTOMOBILE:** Lo studente sviluppa una procedura diagnostica migliorata per un problema ricorrente, integrando piu strumenti e tecniche.

**Esempio AUTOMAZIONE:** Lo studente progetta un sistema di automazione completo per un nuovo processo, dalla specifica alla programmazione alla messa in servizio.

**Come riconoscerlo:** Lo studente non si limita ad applicare cio che ha imparato, ma crea qualcosa di nuovo e funzionale. E raro nei percorsi formativi iniziali.

### Guida Pratica: Come Decidere il Livello

Quando sei incerto sul livello da assegnare, usa questa guida:

**Domandati:** "Cosa ha fatto lo studente CONCRETAMENTE?"

| Se lo studente... | Allora il livello e... |
|---|---|
| Ha ripetuto una definizione a memoria | **1 - Ricordare** |
| Ha spiegato un concetto con esempi propri | **2 - Comprendere** |
| Ha eseguito una procedura standard correttamente | **3 - Applicare** |
| Ha identificato un guasto partendo dai sintomi | **4 - Analizzare** |
| Ha scelto tra due soluzioni e motivato la scelta | **5 - Valutare** |
| Ha inventato una soluzione nuova per un problema inedito | **6 - Creare** |

**Errori comuni da evitare:**

- **Non confondere "Applicare" con "Ricordare":** se lo studente esegue la procedura solo quando gliela detti tu, e Livello 1 (Ricordare), non 3 (Applicare).
- **Non sopravvalutare per simpatia:** valuta la competenza, non la persona.
- **Non sottovalutare per prudenza:** se lo studente dimostra di saper fare, riconosci il livello reale.
- **Usa N/O con parsimonia:** e per quando davvero non hai visto, non per "non so".

### Tabella Riepilogativa

| Livello | Nome | Domanda Tipo | Indicatore Rapido |
|---------|------|-------------|-------------------|
| 0 | N/O | - | Non osservato |
| 1 | Ricordare | "Cos'e?" | Sa definire |
| 2 | Comprendere | "Perche?" | Sa spiegare |
| 3 | Applicare | "Fai vedere" | Sa fare (standard) |
| 4 | Analizzare | "Cosa non va?" | Sa diagnosticare |
| 5 | Valutare | "Qual e meglio?" | Sa decidere e motivare |
| 6 | Creare | "Cosa proponi?" | Sa inventare soluzioni |

### Distribuzione Attesa

In un percorso formativo di 6 settimane, la distribuzione tipica dei livelli e:

| Livello | Frequenza attesa | Note |
|---------|-----------------|------|
| **N/O** | 10-20% | Normale se ci sono aree non ancora affrontate |
| **1-2** | 30-40% | La maggioranza degli studenti in formazione iniziale |
| **3** | 20-30% | Competenze in fase di acquisizione pratica |
| **4** | 10-15% | Studenti piu avanzati o con esperienza pregressa |
| **5-6** | 5-10% | Raro, tipico di studenti con esperienza professionale |

Se la distribuzione di uno studente e molto diversa da questa, potrebbe indicare:
- Troppe N/O: forse non stai osservando abbastanza
- Troppi 5-6: forse stai sopravvalutando
- Troppi 1-2: verifica se lo studente ha difficolta di apprendimento

---
