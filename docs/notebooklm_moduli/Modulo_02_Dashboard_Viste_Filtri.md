# Modulo 2: Coach Dashboard - Panoramica, Viste e Filtri

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: la struttura della Dashboard, i pulsanti header, le 5 statistiche, i 6 quick filters, le 4 viste disponibili (Classica, Compatta, Standard, Dettagliata), lo zoom accessibilita, e tutti i filtri avanzati.

---

## 3. Coach Dashboard V2 - Panoramica

### 3.1 La Tua Area di Lavoro

La Coach Dashboard V2 e il centro di controllo da cui gestisci tutti i tuoi studenti. Appena la apri vedrai quattro zone principali:

```
┌──────────────────────────────────────────────────────────────┐
│  [Vista: Classica|Compatta|Standard|Dettagliata]  [A- A A+ A++] │  <- Controlli Vista/Zoom
├──────────────────────────────────────────────────────────────┤
│  I miei studenti (24)  [← Versione Classica] [Scelte Rapide] [Rapporto Classe] │  <- Header
├──────────────────────────────────────────────────────────────┤
│  [▼ Filtri Avanzati]                                         │  <- Filtri (collassabili)
│  Corso: [___] Colore: [●●●●●●●] Settimana: [___] Stato: [___] │
├──────────────────────────────────────────────────────────────┤
│  Studenti:24 │ Media:67% │ Autoval:18/24 │ Lab:15/24 │ Fine6Sett:3 │  <- Statistiche
├──────────────────────────────────────────────────────────────┤
│  [Tutti(24)] [Mancano Scelte(5)] [Manca Autoval(6)] ...     │  <- Quick Filters
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐                 │  <- Griglia Studenti
│   │ Studente │  │ Studente │  │ Studente │                 │
│   │  Card 1  │  │  Card 2  │  │  Card 3  │                 │
│   └──────────┘  └──────────┘  └──────────┘                 │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

> **SCREENSHOT 3.1:** Vista completa della Coach Dashboard V2 con aree numerate

### 3.2 I 3 Pulsanti Header

In alto, accanto al titolo "I miei studenti (N)", troverai tre pulsanti:

| Pulsante | Colore | Cosa fa |
|----------|--------|---------|
| **"← Versione Classica"** | Azzurro (btn-info) | Torna alla versione precedente della dashboard (coach_dashboard.php). Usa se preferisci il vecchio layout. |
| **"Scelte Rapide"** | Giallo (btn-warning) | Apre lo strumento per assegnare rapidamente test e laboratori. |
| **"Rapporto Classe"** | Blu (btn-primary) | Apre il report aggregato di tutta la classe (reports_class.php). |

**Per usare "Rapporto Classe":**

**Passo 1.** Clicca sul pulsante blu "Rapporto Classe".

> **Cosa succede:** Si apre una nuova pagina con le statistiche aggregate
> di tutti i tuoi studenti: medie, distribuzione competenze, confronti.

### 3.3 Le 5 Statistiche Riassuntive

Sotto i filtri trovi una riga di **5 card statistiche colorate**:

| Card | Colore | Contenuto | Esempio |
|------|--------|-----------|---------|
| **Studenti** | Viola | Numero totale dei tuoi studenti | "24" |
| **Media Competenze** | Blu | Media ponderata delle competenze | "67%" |
| **Autoval Complete** | Verde | Rapporto autovalutazioni completate | "18/24" |
| **Lab Valutati** | Arancione | Rapporto laboratori valutati | "15/24" |
| **Fine 6 Sett.** | Rosso | Studenti in fase conclusiva | "3" |

> **Cosa vedrai:** Ogni card ha un numero grande al centro, un'icona in alto
> e un'etichetta in basso. I numeri si aggiornano automaticamente quando applichi i filtri.

> **SCREENSHOT 3.3:** Box statistiche con numeri evidenziati

### 3.4 I 6 Quick Filters

Sotto le statistiche trovi **6 pulsanti di filtro rapido**. Ogni pulsante mostra il conteggio tra parentesi:

| Pulsante | Conteggio | Cosa filtra |
|----------|-----------|-------------|
| **"Tutti"** | (24) | Mostra tutti gli studenti (reset filtri) |
| **"Mancano Scelte"** | (5) | Studenti a cui non sono ancora state assegnate le scelte settimanali |
| **"Manca Autoval"** | (6) | Studenti che non hanno completato l'autovalutazione |
| **"Manca Lab"** | (9) | Studenti senza valutazione laboratorio |
| **"Sotto Soglia 50%"** | (2) | Studenti con media competenze inferiore al 50% |
| **"Fine 6 Sett."** | (3) | Studenti alla settimana 6 o oltre (styling speciale) |

**Per usare i Quick Filters:**

**Passo 1.** Clicca su uno dei pulsanti, ad esempio "Manca Autoval (6)".

> **Cosa succede:** La griglia si aggiorna istantaneamente mostrando solo
> i 6 studenti senza autovalutazione. Gli altri vengono nascosti.
> Il pulsante cliccato appare evidenziato.

**Passo 2.** Per tornare a vedere tutti, clicca su "Tutti (24)".

> **Suggerimento:** Inizia la giornata cliccando "Manca Autoval" per inviare subito i promemoria,
> poi "Sotto Soglia 50%" per identificare chi ha bisogno di attenzione urgente.

> **SCREENSHOT 3.4:** Quick filters con "Manca Autoval" selezionato

---

## 4. Coach Dashboard V2 - Viste e Accessibilita

### 4.1 Le 4 Viste Disponibili

In alto a sinistra trovi il **selettore vista** con 4 pulsanti:

| Vista | Icona | Descrizione | Quando usarla |
|-------|-------|-------------|---------------|
| **Classica** | 📄 | Layout originale, simile alla V1 | Se preferisci il vecchio stile |
| **Compatta** | ☰ | Una riga per studente, stile tabella | Molti studenti, panoramica veloce |
| **Standard** | 📋 | Card espandibili in griglia | Uso quotidiano (consigliata) |
| **Dettagliata** | 📊 | Pannelli sempre aperti, 2 colonne | Analisi approfondita |

### 4.2 Vista Compatta - Una Riga per Studente

> **Cosa vedrai:** Una tabella con una riga per ogni studente.

**Colonne della vista Compatta:**

| Colonna | Larghezza | Contenuto |
|---------|-----------|-----------|
| Colore | 40px | Indicatore colore gruppo (quadratino) |
| Studente | 250px | Nome completo + email (in piccolo sotto) |
| Settore | 120px | Badge settore con medaglia (🥇🥈🥉) |
| Settimana | 80px | Numero settimana corrente (1-6) |
| Competenze | 100px | Percentuale con colore (verde >=50%, rosso <50%) |
| Autoval | 100px | Icona stato (✓ verde = completata, ✗ rosso = mancante) |
| Lab | 100px | Icona stato (✓ verde = valutato, ✗ rosso = mancante) |
| Azioni | Resto | Pulsanti: Report, Valutazione, Colloquio, Word (se fine 6 sett.) |

**Per espandere i dettagli di uno studente:**

**Passo 1.** Clicca sulla riga dello studente.

> **Cosa succede:** La riga si espande mostrando un pannello con dettagli aggiuntivi.

> **Suggerimento:** La vista Compatta e ideale quando hai 20+ studenti e vuoi
> una panoramica rapida. Usa i filtri per ridurre la lista.

### 4.3 Vista Standard - Card Espandibili (Consigliata)

> **Cosa vedrai:** Una griglia di card (schede) disposte su piu colonne.
> Ogni card occupa minimo 350px di larghezza. Su uno schermo largo
> vedrai 2-3 card per riga.

**Struttura di ogni card:**

```
┌────────────────────────────────────────┐
│ [▼] Mario Rossi          🥇MEC  Sett.3│  <- Header (sempre visibile)
│     mario.rossi@email.it               │
│     [FINE 6 SETT.] [SOTTO SOGLIA]     │  <- Badge alert (se presenti)
├────────────────────────────────────────┤
│ [📊 Report] [👤 Valutaz.] [📋 Profilo]│  <- Quick Actions (sempre visibili)
│ [💬 Colloq.] [📄 Word] [📨 Sollecita]│
│ [✓ Salva]                              │
├────────────────────────────────────────┤
│ Progress:                              │  <- Corpo (collassabile con ▼)
│   Competenze ████████░░ 72%           │
│   Autoval    ██████████ 100%          │
│   Lab        ████░░░░░░  45%          │
│                                        │
│ Status: [✓ Quiz] [⏱ Autoval] [✗ Lab] │
│                                        │
│ Timeline: [✓][✓][✓][◯][○][○]         │
│           S1  S2  S3  S4  S5  S6      │
│                                        │
│ Note Coach:                            │
│ [________________________]             │
│ [💾 Salva Note]                        │
└────────────────────────────────────────┘
```

**Per espandere/comprimere una card:**

**Passo 1.** Clicca sull'header della card (la riga con il nome dello studente e il triangolino ▼).

> **Cosa succede:** Il corpo della card si espande mostrando progress bar, status,
> timeline e note. Cliccando di nuovo si comprime.

**Per espandere/comprimere TUTTE le card:**

**Passo 1.** Usa i pulsanti "▼ Espandi Tutto" e "▲ Comprimi Tutto" (btn-secondary piccoli, sopra la griglia).

> **Suggerimento:** Comprimi tutto e poi espandi solo le card degli studenti
> che ti interessano per una consultazione piu veloce.

> **SCREENSHOT 4.3:** Vista Standard con 3 card per riga, una espansa e due compresse

### 4.4 Vista Dettagliata - Pannelli Sempre Aperti

> **Cosa vedrai:** Ogni studente ha un pannello a larghezza piena con layout a 2 colonne.
> Tutti i dettagli sono sempre visibili senza bisogno di espandere.

**Layout 2 colonne:**

| Colonna Sinistra | Colonna Destra |
|-----------------|----------------|
| 3 stat box: Competenze, Autoval, Lab (con percentuali) | Note Coach (textarea editabile) |
| Timeline 6 settimane (compatta) | Riepilogo stato: badge Quiz, Autoval, Lab |
| Scelte settimanali (se applicabile): 2 dropdown Test + Lab | |

**In fondo a ogni pannello:** Pulsanti azione: Profilo, Report, Valutazione, Colloquio, Word, Salva Scelte.

> **Suggerimento:** La vista Dettagliata e perfetta per sessioni di lavoro
> dove devi aggiornare note e scelte per piu studenti di seguito.

### 4.5 Zoom Accessibilita

In alto a destra trovi **4 pulsanti di zoom** per regolare la dimensione del testo:

| Pulsante | Scala | Quando usarlo |
|----------|-------|---------------|
| **A-** | 90% | Schermi piccoli, vuoi vedere piu studenti |
| **A** | 100% | Impostazione standard |
| **A+** | 120% | Leggibilita migliorata |
| **A++** | 140% | Per chi ha difficolta visive |

**Per cambiare lo zoom:**

**Passo 1.** Clicca su uno dei pulsanti, ad esempio "A+".

> **Cosa succede:** Tutta l'interfaccia si ridimensiona immediatamente.
> Il pulsante selezionato appare evidenziato.
> La classe CSS `zoom-120` viene applicata al contenitore principale.

> **Risultato atteso:** Testi, pulsanti e card diventano piu grandi (o piu piccoli).
> La preferenza viene salvata automaticamente e mantenuta nelle sessioni future.

> **Nota tecnica:** Le preferenze di vista e zoom vengono salvate tramite URL params
> `view` e `zoom` con `save_prefs=1`, memorizzate nella tabella preferenze utente Moodle.

> **SCREENSHOT 4.5:** Confronto zoom 100% vs 140%

---

## 5. Coach Dashboard V2 - Filtri e Ricerca

### 5.1 Pannello Filtri Avanzati

I filtri si trovano in un pannello collassabile sotto l'header.

**Per aprire/chiudere i filtri:**

**Passo 1.** Clicca sull'intestazione "▼ Filtri Avanzati" (o sull'icona filtro).

> **Cosa succede:** Il pannello si espande mostrando 4 filtri in riga.
> Cliccando di nuovo si comprime per risparmiare spazio.

### 5.2 Filtro Corso

**Passo 1.** Clicca sul dropdown "Corso" (il primo da sinistra).

> **Cosa vedrai:** Un menu a tendina con l'elenco di tutti i corsi
> a cui sei iscritto come coach.

**Passo 2.** Seleziona un corso.

> **Cosa succede:** La griglia si aggiorna mostrando solo gli studenti
> di quel corso. Le statistiche in alto si ricalcolano.

### 5.3 Filtro Colore Gruppo

Il filtro colore usa **7 chip colorati cliccabili** disposti in riga:

| Chip | Colore | Codice |
|------|--------|--------|
| 🟡 | Giallo | #FFFF00 |
| 🔵 | Blu | #0066cc |
| 🟢 | Verde | #28a745 |
| 🟠 | Arancione | #fd7e14 |
| 🔴 | Rosso | #dc3545 |
| 🟣 | Viola | #7030A0 |
| ⚪ | Grigio | #808080 |

**Per filtrare per colore gruppo:**

**Passo 1.** Clicca su un chip colorato, ad esempio il cerchietto viola.

> **Cosa succede:** Il chip ottiene un bordo scuro e un'ombra (box-shadow)
> per indicare la selezione. Il campo nascosto `colorFilter` si aggiorna.
> Il form filtri viene inviato automaticamente.

**Passo 2.** Per deselezionare, clicca di nuovo sullo stesso chip.

> **Cosa succede:** Il bordo torna normale. Il filtro colore viene rimosso.

> **Nota tecnica:** I colori corrispondono ai gruppi definiti nello Scheduler FTM.
> Ogni gruppo ha un colore che identifica la coorte di studenti.

### 5.4 Filtro Settimana

**Passo 1.** Clicca sul dropdown "Settimana".

> **Cosa vedrai:** Le opzioni: Tutte, Settimana 1, Settimana 2, ..., Settimana 6.

**Passo 2.** Seleziona "Settimana 3".

> **Cosa succede:** Vengono mostrati solo gli studenti alla settimana 3 del percorso.

### 5.5 Filtro Stato

**Passo 1.** Clicca sul dropdown "Stato".

> **Cosa vedrai:** Le opzioni:
> - **Tutti gli stati** (default)
> - **Fine 6 settimane** (`end6`) - studenti in fase conclusiva
> - **Sotto soglia 50%** (`below50`) - studenti con competenze critiche
> - **Manca autovalutazione** (`no_autoval`) - senza autovalutazione
> - **Manca laboratorio** (`no_lab`) - senza valutazione lab
> - **Mancano scelte** (`no_choices`) - senza scelte settimanali assegnate

### 5.6 Combinazione Filtri

I filtri si **combinano** (AND logico): selezionando Corso + Colore + Settimana vedrai solo gli studenti che soddisfano TUTTI i criteri.

**Per resettare tutti i filtri:**

**Passo 1.** Clicca sul pulsante "Reset" (se visibile) oppure clicca su "Tutti" nei Quick Filters.

> **Cosa succede:** Tutti i dropdown tornano al valore predefinito.
> Il chip colore viene deselezionato. La griglia mostra tutti gli studenti.

> **Suggerimento:** Se non vedi studenti, controlla sempre i filtri attivi!
> E la causa piu comune di "non vedo i miei studenti".

> **SCREENSHOT 5.6:** Barra filtri con dropdown e chip colore

---
