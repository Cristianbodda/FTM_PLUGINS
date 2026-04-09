# Manuale Operativo FTM - Coach/Formatore

**Versione:** 3.0 - Aggiornato con nuove funzioni Marzo-Aprile 2026
**Data:** Aprile 2026
**Sistema:** FTM Academy - Gestione Competenze Professionali

---

## Indice

1. [Introduzione e Panoramica Sistema](#1-introduzione-e-panoramica-sistema)
2. [Accesso al Sistema](#2-accesso-al-sistema)
3. [Coach Dashboard V2 - Panoramica](#3-coach-dashboard-v2---panoramica)
4. [Coach Dashboard V2 - Viste e Accessibilita](#4-coach-dashboard-v2---viste-e-accessibilita)
5. [Coach Dashboard V2 - Filtri e Ricerca](#5-coach-dashboard-v2---filtri-e-ricerca)
6. [Coach Dashboard V2 - Card Studente e Azioni](#6-coach-dashboard-v2---card-studente-e-azioni)
7. [Report Studente Dettagliato](#7-report-studente-dettagliato)
8. [Report Studente - Tab e Radar](#8-report-studente---tab-e-radar)
9. [Report Studente - Gap Analysis e Spunti Colloquio](#9-report-studente---gap-analysis-e-spunti-colloquio)
10. [Valutazione Formatore](#10-valutazione-formatore)
11. [Bilancio Competenze](#11-bilancio-competenze)
12. [Note Coach](#12-note-coach)
13. [Self-Assessment Dashboard](#13-self-assessment-dashboard)
14. [FTM Scheduler](#14-ftm-scheduler)
15. [Passaporto Tecnico e Garage FTM](#15-passaporto-tecnico-e-garage-ftm)
16. [Documenti Studenti - Ricerca e Caricamento](#16-documenti-studenti---ricerca-e-caricamento)
17. [Accedi come Studente](#17-accedi-come-studente)
18. [Coaching Individualizzato (CI)](#18-coaching-individualizzato-ci)
19. [JobAIDA - Generatore Lettere di Candidatura](#19-jobaida---generatore-lettere-di-candidatura)
20. [Tabella Comparativa - Valori Editabili](#20-tabella-comparativa---valori-editabili)
21. [Rapporto Finale CPURC - Autosave](#21-rapporto-finale-cpurc---autosave)
22. [Casi d'Uso Pratici](#22-casi-duso-pratici)
23. [Risoluzione Problemi](#23-risoluzione-problemi)

**Appendici:**
- [A. Glossario Completo](#appendice-a-glossario-completo)
- [B. Scala Bloom - Guida Dettagliata con Esempi](#appendice-b-scala-bloom---guida-dettagliata-con-esempi)
- [C. Checklist Settimanale Coach](#appendice-c-checklist-settimanale-coach)
- [D. Mappa Navigazione Completa](#appendice-d-mappa-navigazione-completa)

---

## 1. Introduzione e Panoramica Sistema

### 1.1 Cos'e FTM Academy

FTM Academy e un ecosistema di plugin Moodle progettato per la gestione delle competenze professionali. Come coach/formatore, il tuo ruolo principale e:

- Monitorare il progresso degli studenti assegnati
- Valutare le competenze tecniche e trasversali
- Condurre colloqui basati su dati oggettivi
- Guidare gli studenti nel percorso formativo di 6 settimane

### 1.2 I 5 Strumenti del Coach

Il sistema mette a tua disposizione **5 strumenti principali**, collegati tra loro:

```
┌─────────────────────────────────────────────────────────────┐
│                    COACH DASHBOARD V2                         │
│          (Centro di controllo - vedi tutti i tuoi studenti)  │
└──────┬──────────┬──────────┬──────────┬─────────────────────┘
       │          │          │          │
       ▼          ▼          ▼          ▼
  ┌─────────┐ ┌──────────┐ ┌─────────┐ ┌──────────────┐
  │ REPORT  │ │VALUTAZ.  │ │BILANCIO │ │  SCHEDULER   │
  │STUDENTE │ │FORMATORE │ │COMPETENZ│ │ (Calendario)  │
  │(Analisi │ │(Scala    │ │(Colloqui│ │              │
  │ dati)   │ │ Bloom)   │ │ e match)│ │              │
  └─────────┘ └──────────┘ └─────────┘ └──────────────┘
```

| Strumento | File | Funzione |
|-----------|------|----------|
| **Coach Dashboard V2** | `coach_dashboard_v2.php` | Centro di controllo: vedi tutti i tuoi studenti, stato, progressi, azioni rapide |
| **Report Studente** | `student_report.php` | Analisi dettagliata: radar competenze, tabelle, grafici, confronto fonti |
| **Valutazione Formatore** | `coach_evaluation.php` | Valuta le competenze con scala Bloom (0-6), salva bozza/completa/firma |
| **Bilancio Competenze** | `reports_v2.php` | Report per colloqui: confronto, mappa competenze, matching lavoro |
| **FTM Scheduler** | `ftm_scheduler/index.php` | Calendario settimanale/mensile, gestione gruppi, attivita, aule |

Inoltre hai accesso alla **Self-Assessment Dashboard** (`selfassessment/index.php`) per monitorare e gestire le autovalutazioni degli studenti.

### 1.3 Le 4 Fonti Dati

Il sistema raccoglie dati sulle competenze dello studente da **4 fonti diverse**:

| Fonte | Raccolta | Descrizione |
|-------|----------|-------------|
| **Quiz** | Automatica | Risultati oggettivi dei test Moodle. Ogni quiz e collegato a competenze specifiche. |
| **Autovalutazione** | Studente | Lo studente valuta se stesso sulla scala Bloom (1-6) per ogni competenza. |
| **LabEval** | Formatore laboratorio | Valutazione pratica durante le esercitazioni di laboratorio. |
| **Coach** | Tu (coach) | La tua valutazione diretta, basata su osservazione, colloqui e prove pratiche. |

Il Report Studente sovrappone queste 4 fonti in un **radar overlay** che ti permette di vedere a colpo d'occhio dove ci sono discrepanze.

### 1.4 Flusso di Lavoro Tipico

```
Settimana 1-2: Accoglienza -> Quiz iniziali -> Prima autovalutazione
               Dashboard: verifica nuovi studenti, invia autovalutazione

Settimana 3-4: Monitoraggio -> Laboratori -> Valutazioni intermedie
               Report: analizza gap, prepara colloqui
               Valutazione: inizia a compilare scala Bloom

Settimana 5-6: Colloqui -> Valutazione finale -> Report conclusivo
               Bilancio: confronta studenti, prepara report colloquio
               Valutazione: completa e firma
               Dashboard: export Word per studenti a fine percorso
```

> **SCREENSHOT 1.4:** Diagramma del flusso di lavoro (infografica)

---

## 2. Accesso al Sistema

### 2.1 Login alla Piattaforma

**Passo 1.** Apri il browser (Chrome, Firefox o Edge consigliati) e vai all'indirizzo della piattaforma FTM.

**Passo 2.** Inserisci le tue credenziali:
- **Username:** il tuo nome utente Moodle
- **Password:** la password assegnata

**Passo 3.** Clicca sul pulsante "Login".

> **Cosa succede:** Si apre la Dashboard Moodle con il menu laterale.
> Nella barra di navigazione in alto vedi il tuo nome e il link ai tuoi corsi.

> **Attenzione:** Se il login fallisce, verifica di non avere il CAPS LOCK attivo.
> Dopo 5 tentativi falliti, l'account viene temporaneamente bloccato (15 minuti).

> **SCREENSHOT 2.1:** Pagina di login con campi username/password evidenziati

### 2.2 Tre Metodi di Accesso alla Coach Dashboard

**Metodo 1 - Blocco FTM Tools (consigliato):**

**Passo 1.** Dalla Dashboard Moodle o dalla pagina di un corso, cerca il blocco "FTM Tools" nella colonna laterale destra.

> **Cosa vedrai:** Un pannello con diversi link organizzati per sezione.
> Nella sezione "Area Coach" troverai i link ai tuoi strumenti.

**Passo 2.** Clicca su "Dashboard Coach V2".

> **Cosa succede:** Si apre direttamente la Coach Dashboard con tutti i tuoi studenti.

**Metodo 2 - FTM Tools Hub:**

**Passo 1.** Vai a `/local/ftm_hub/index.php`.

> **Cosa vedrai:** Una pagina con header verde "FTM Tools Hub" e una griglia di card
> organizzate per sezione. Nella sezione "Area Coach" troverai:
> - "Dashboard Coach V2" (pulsante verde "Apri Dashboard")
> - "Report Classe" (pulsante blu "Apri Report")
> - "Student Report" (pulsante verde "Apri Report")
> - "FTM Scheduler" (pulsante blu "Apri Calendario")
> - "Gestione Autovalutazioni" (pulsante arancione "Gestisci")
> - "Report Colloqui" (pulsante viola "Genera Report")

**Passo 2.** Clicca su "Apri Dashboard" nella card "Dashboard Coach V2".

**Metodo 3 - URL Diretto:**

Digita nella barra degli indirizzi del browser:
```
/local/coachmanager/coach_dashboard_v2.php
```

> **Suggerimento:** Salva questo URL tra i preferiti del browser per accesso immediato.

> **SCREENSHOT 2.2:** Blocco FTM Tools con link Coach Dashboard evidenziato
> **SCREENSHOT 2.3:** FTM Tools Hub con card Coach Dashboard evidenziata

### 2.3 Navigazione Breadcrumb

In ogni pagina FTM vedrai in alto una **breadcrumb** (percorso di navigazione):

```
Home > I miei corsi > [Nome Corso] > Coach Dashboard
```

Puoi cliccare su qualsiasi livello per tornare indietro. Il link "Home" ti riporta alla Dashboard Moodle.

> **Suggerimento:** Il pulsante "Indietro" del browser funziona correttamente in quasi tutte le pagine FTM.

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

---

## 7. Report Studente Dettagliato

### 7.1 Accesso al Report

**Dalla Dashboard (metodo principale):**

**Passo 1.** Trova lo studente nella griglia della Coach Dashboard.

**Passo 2.** Clicca sul pulsante "📊 Report" nella card.

> **Cosa succede:** Si apre la pagina `student_report.php` con i dati dello studente.

**Da URL diretto:**
```
/local/competencymanager/student_report.php?userid=X&courseid=Y
```

**Senza parametri (selettore studente):**

Se apri `student_report.php` senza specificare uno studente:

> **Cosa vedrai:** Una pagina con sfondo bianco e una card centrata con il titolo
> "👤 Seleziona Studente". Contiene:
> - Un dropdown "📚 Corso" per selezionare il corso
> - Un dropdown "👨‍🎓 Studente" (si popola dopo la selezione del corso)
> - Un pulsante "📊 Visualizza Report" (gradiente viola)

**Passo 1.** Seleziona un corso dal primo dropdown.

> **Cosa succede:** La pagina si ricarica e il dropdown studente si popola con
> tutti gli studenti iscritti al corso.

**Passo 2.** Seleziona uno studente.

**Passo 3.** Clicca "📊 Visualizza Report".

> **Cosa succede:** Si apre il report completo dello studente selezionato.

### 7.2 Struttura della Pagina

La pagina Report Studente e organizzata in tre zone:

1. **Header** (fisso in alto)
2. **Pannello FTM** (barra laterale con 6 tab FTM)
3. **Area principale** (7 tab di navigazione)

### 7.3 Header del Report

> **Cosa vedrai:** Un banner con sfondo gradiente viola (#667eea -> #764ba2) che contiene:
> - **Foto studente** (80x80 pixel) a sinistra
> - **Nome e cognome** in bianco, grassetto
> - **Email** in bianco semi-trasparente
> - **Corso** attivo
> - **Percentuale globale** grande (es. "72%") sulla destra

### 7.4 Le 7 Tab Principali

Sotto l'header trovi una **barra di navigazione con 7 tab** (stile Bootstrap nav-tabs):

| # | Tab | Icona | Contenuto |
|---|-----|-------|-----------|
| 1 | **Panoramica** | 📊 | Mappa competenze con card per area, barre confronto Quiz/Autoval |
| 2 | **Piano** | 📚 | Piano d'azione: eccellenze, buoni, da migliorare, critici |
| 3 | **Quiz** | 📝 | Confronto quiz con dettagli tentativi |
| 4 | **Autovalutazione** | 📝 ↗ | Link esterno a `/local/selfassessment/student_report.php` (apre nuova scheda) |
| 5 | **Laboratorio** | 🔬 ↗ | Link esterno a `/local/labeval/reports.php` (apre nuova scheda) |
| 6 | **Progressi** | 📈 | Grafico progresso nel tempo (Chart.js line chart) |
| 7 | **Dettagli** | 📋 | Tabella completa competenze con editing inline Bloom |

> **Nota:** I tab "Autovalutazione" e "Laboratorio" sono **link esterni** (indicati con ↗).
> Cliccandoli si apre una nuova scheda con il rispettivo report.

**Per navigare tra i tab:**

**Passo 1.** Clicca sul nome del tab desiderato nella barra di navigazione.

> **Cosa succede:** Il contenuto dell'area principale cambia.
> Il tab attivo appare evidenziato con un bordo inferiore colorato.
> L'URL si aggiorna con il parametro `tab=` (es. `tab=overview`, `tab=details`).

### 7.5 Tab Panoramica

**Passo 1.** Clicca su "📊 Panoramica" (e il tab predefinito).

> **Cosa vedrai:** Due colonne:
>
> **Colonna sinistra (60%):**
> - Un grafico **radar SVG/Canvas** con le aree di competenza
> - Ogni asse del radar corrisponde a un'area (A, B, C, D, ...)
> - L'area colorata mostra il livello raggiunto
>
> **Colonna destra (40%):**
> - Card per ogni area di competenza con:
>   - Nome area (es. "A. Accoglienza e Diagnosi")
>   - Badge colorato con percentuale
>   - Barra di confronto Quiz vs Autovalutazione (se disponibile)
>   - Colore in base alla fascia: ECCELLENTE (verde >=80%), BUONO (teal >=60%),
>     SUFFICIENTE (giallo >=50%), INSUFFICIENTE (arancione >=30%), CRITICO (rosso <30%)

### 7.6 Tab Piano

**Passo 1.** Clicca su "📚 Piano".

> **Cosa vedrai:** Le competenze organizzate in 4 categorie:
>
> - **Eccellenze** (>=80%): competenze con padronanza completa.
>   Azione: "Pronto per attivita avanzate e tutoraggio compagni."
> - **Buone** (60-79%): competenze acquisite con buona padronanza.
>   Azione: "Consolidare con esercizi pratici."
> - **Da Migliorare** (30-59%): lacune significative.
>   Azione: "Percorso di recupero mirato richiesto."
> - **Critiche** (<30%): competenze non acquisite.
>   Azione: "Formazione base completa richiesta."

Ogni competenza mostra: codice, nome, percentuale, risposte corrette/totali.

### 7.6b Tab Quiz

**Passo 1.** Clicca su "📝 Quiz".

> **Cosa vedrai:** Una card con header grigio scuro (bg-secondary):
> "📝 Confronto per Quiz"
> Sottotitolo: "Clicca sul nome del quiz per vedere domande e risposte dello studente"

**Tabella quiz:**

| Colonna | Contenuto |
|---------|-----------|
| **Quiz** | Nome del quiz (cliccabile). Al click apre la review Moodle in nuova scheda. Icona 🔗 accanto |
| **Tentativo** | Numero del tentativo (es. "#1", "#2") |
| **Data** | Data e ora di completamento (formato dd/mm/YYYY HH:mm) |
| **Punteggio** | Badge colorato con percentuale: verde (>=60%), giallo (40-59%), rosso (<40%) |
| **Competenze** | Numero di competenze coperte dal quiz |
| **Azione** | Pulsante "👁️ Review" (btn-outline-primary) - apre la review dettagliata |

> **Suggerimento:** Il pulsante "👁️ Review" apre la pagina Moodle standard
> `/mod/quiz/review.php?attempt=X` dove puoi vedere ogni singola domanda,
> la risposta data dallo studente e la risposta corretta. Questo e prezioso
> per capire DOVE lo studente sbaglia, non solo quanto sbaglia.

> **Nota:** Se lo studente non ha completato nessun quiz, la tabella mostra
> il messaggio "Nessun tentativo quiz completato" in grigio centrato.

> **SCREENSHOT 7.6b:** Tabella quiz con tentativi e badge punteggio colorati

### 7.6c Tab Autovalutazione e Laboratorio

Le tab "📊 Autovalutazione" e "🔬 Laboratorio" sono **link esterni**:

**Tab Autovalutazione:**

**Passo 1.** Clicca su "📊 Autovalutazione ↗".

> **Cosa succede:** Si apre in una nuova scheda la pagina
> `/local/selfassessment/student_report.php` con il report autovalutazione dello studente.

**Tab Laboratorio:**

**Passo 1.** Clicca su "🔬 Laboratorio ↗".

> **Cosa succede:** Si apre in una nuova scheda la pagina
> `/local/labeval/reports.php` con le valutazioni di laboratorio dello studente.

> **Nota:** Queste due tab hanno la freccia "↗" accanto al nome per indicare
> che aprono pagine esterne in nuova scheda.

### 7.7 Tab Progressi

**Passo 1.** Clicca su "📈 Progressi".

> **Cosa vedrai:** Una card con header blu (bg-primary):
> "📈 Progressi nel Tempo"
>
> Al centro un **grafico a linee** (Chart.js canvas `progressChart`):
> - **Asse X:** Date dei tentativi quiz (formato data)
> - **Asse Y:** Percentuali (0-100%)
> - **Linea:** Andamento della media competenze nel tempo
> - **Altezza massima:** 300px per non occupare troppo spazio verticale
>
> Se lo studente non ha ancora dati sufficienti, appare il messaggio:
> "Non ci sono ancora abbastanza dati per mostrare i progressi.
> Completa piu quiz per vedere l'andamento."

> **Suggerimento:** Usa questo tab per verificare se lo studente sta migliorando
> nel tempo. Una curva in salita indica apprendimento; una curva piatta puo
> indicare stallo e necessita di intervento.

> **SCREENSHOT 7.7:** Grafico progressi con curva in salita

### 7.8 Tab Dettagli

**Passo 1.** Clicca su "📋 Dettagli".

> **Cosa vedrai:** Una tabella completa con TUTTE le competenze dello studente.

**Colonne della tabella:**

| Colonna | Contenuto |
|---------|-----------|
| **#** | Numero progressivo |
| **Area** | Badge colorato con codice area (es. "A" in azzurro, "B" in rosso) |
| **Codice** | Codice competenza (es. MECCANICA_MIS_01) in font monospace |
| **Competenza** | Nome/descrizione della competenza |
| **Risposte** | Formato X/Y (corrette/totali) |
| **%** | Percentuale con colore (verde/giallo/rosso) |
| **Valutazione** | Badge Bloom cliccabile per editing inline |

**Intestazione della tabella:**

> **Cosa vedrai:** Card con header nero (bg-dark) e testo bianco:
> "📋 Dettaglio Competenze" con a destra il conteggio
> "X / Y competenze" (filtrate / totali) in badge chiaro.

**Filtri della tabella (sotto l'header, sopra i dati):**

**Filtro 1 - Ordina per (dropdown `sort`):**
- Per area (default) - raggruppa le competenze per area
- Per percentuale (decrescente) - dalla piu alta alla piu bassa
- Per percentuale (crescente) - dalla piu bassa alla piu alta
- Per numero risposte - le competenze con piu domande prima

**Filtro 2 - Livello (dropdown `filter`):**
- Tutti i livelli (default)
- Eccellente (>=80%) - solo le competenze forti
- Buono (60-79%) - solo le competenze acquisite
- Sufficiente (50-59%) - area di confine
- Insufficiente (30-49%) - lacune significative
- Critico (<30%) - competenze non acquisite

**Filtro 3 - Area (dropdown `filter_area`):**
- Tutte le aree (default)
- Elenco di ogni area disponibile per lo studente (es. "📁 A - Accoglienza", "📁 B - Motore")

> **Cosa succede quando filtri:** La tabella si aggiorna immediatamente
> mostrando solo le competenze che corrispondono ai filtri selezionati.
> Il conteggio nell'header si aggiorna (es. "12 / 45 competenze").

**Contatori per livello (sotto i filtri):**

> Cinque mini-badge cliccabili che mostrano il totale per ogni fascia:
> - 🟢 Eccellente: X
> - 🔵 Buono: X
> - 🟡 Sufficiente: X
> - 🟠 Insufficiente: X
> - 🔴 Critico: X
>
> Cliccando su un badge si applica il filtro per quel livello.

### 7.9 Valutazione Inline dal Tab Dettagli

Nella colonna "Valutazione" puoi valutare direttamente ogni competenza:

**Passo 1.** Nella tabella Dettagli, trova la competenza da valutare.

**Passo 2.** Clicca sul **badge Bloom** nella colonna "Valutazione".

> **Cosa vedrai:** Il badge mostra il valore corrente:
> - **Verde** (>=5): livello alto
> - **Giallo** (3-4): livello medio
> - **Rosso** (1-2): livello basso
> - **Grigio** (N/O): non osservato

**Passo 3.** Dal dropdown che appare, seleziona il nuovo livello:
- N/O (Non Osservato)
- 1 (Ricordare)
- 2 (Comprendere)
- 3 (Applicare)
- 4 (Analizzare)
- 5 (Valutare)
- 6 (Creare)

> **Cosa succede:** Dopo 500ms il sistema salva automaticamente (auto-save con debounce).
> Appare un **toast di conferma** "Salvando..." in basso a destra
> (sfondo #667eea, testo bianco, scompare dopo 2 secondi).
> Il badge si aggiorna con il nuovo colore.

> **Suggerimento:** Questa funzione e molto comoda per valutazioni rapide.
> Per una valutazione completa e strutturata, usa la pagina Valutazione Formatore dedicata.

### 7.10 Selettore Quiz

Nella parte superiore del Report (quando visualizzi la tab Panoramica o Dettagli), puoi selezionare quali quiz includere nell'analisi.

**Comportamento predefinito:**
- Il sistema pre-seleziona automaticamente i quiz del **settore primario** dello studente

**Per modificare la selezione:**

**Passo 1.** Cerca i checkbox dei quiz nella parte alta della pagina.

> **Cosa vedrai:** Un elenco di quiz disponibili con:
> - Nome del quiz
> - Corso di appartenenza
> - Checkbox per selezionare/deselezionare

**Passo 2.** Spunta o deseleziona i quiz desiderati.

**Passo 3.** Clicca "Applica Filtri" (o il report si aggiorna automaticamente).

> **Cosa succede:** I grafici radar, le tabelle e le statistiche si ricalcolano
> includendo solo i quiz selezionati. L'URL si aggiorna con i parametri `quizids[]`.

**Filtro tentativi:**
- **Tutti** (`attempt_filter=all`): include tutti i tentativi di ogni quiz
- **Solo primo** (`attempt_filter=first`): solo il primo tentativo
- **Solo ultimo** (`attempt_filter=last`): solo il tentativo piu recente

> **Suggerimento:** Per vedere il progresso, confronta il primo tentativo con l'ultimo.
> Per la valutazione finale, usa "Solo ultimo".

### 7.11 Fascia di Valutazione

Ogni competenza viene classificata in una fascia in base alla percentuale:

| Fascia | Percentuale | Icona | Colore | Azione suggerita |
|--------|-------------|-------|--------|-----------------|
| **ECCELLENTE** | >=80% | 🌟 | Verde #28a745 | Pronto per attivita avanzate e tutoraggio |
| **BUONO** | 60-79% | ✅ | Teal #20c997 | Consolidare con esercizi pratici |
| **SUFFICIENTE** | 50-59% | ⚠️ | Giallo #ffc107 | Ripasso teoria ed esercitazioni |
| **INSUFFICIENTE** | 30-49% | ⚡ | Arancione #fd7e14 | Percorso di recupero mirato |
| **CRITICO** | <30% | 🔴 | Rosso #dc3545 | Formazione base completa richiesta |

Queste fasce sono utilizzate nei badge delle tabelle, nelle card area e nel Piano d'Azione.

> **SCREENSHOT 7.11:** Tabella dettagli con dropdown Bloom aperto su una competenza

---

## 8. Report Studente - Tab e Radar

### 8.1 Il Pannello FTM (6 Tab Laterali)

Accanto ai 7 tab principali, il Report Studente ha un **pannello FTM** con 6 tab aggiuntivi dedicati alla gestione avanzata:

| # | Tab | Icona | Contenuto |
|---|-----|-------|-----------|
| 1 | **Settori** | 👤 | Gestione settori studente (primario/secondario/terziario) |
| 2 | **Ultimi 7gg** | 📅 | Quiz degli ultimi 7 giorni con stati e link Review |
| 3 | **Configurazione** | ⚙️ | Toggle visualizzazioni + soglie configurabili |
| 4 | **Progresso** | 📊 | Barra certificazione (certificati/in corso/da iniziare) |
| 5 | **Gap Analysis** | 📈 | Tabella gap con soglie configurabili |
| 6 | **Spunti Colloquio** | 💬 | Suggerimenti per il colloquio |

**Per aprire un tab FTM:**

**Passo 1.** Clicca su uno dei pulsanti nella barra FTM (sotto i tab principali).

> **Cosa succede:** Il pannello del tab selezionato si espande sotto la barra.
> Il pulsante diventa evidenziato. Cliccando di nuovo si chiude.

### 8.2 Tab Settori

**Passo 1.** Clicca su "👤 Settori".

> **Cosa vedrai:** La gestione dei settori assegnati allo studente:
> - 🥇 **Settore primario** (con medaglia oro): il settore principale dello studente
> - 🥈 **Settore secondario** (medaglia argento): settore aggiuntivo - opzionale
> - 🥉 **Settore terziario** (medaglia bronzo): terzo settore - opzionale
> - 📊 **Da quiz**: settori rilevati automaticamente dai quiz svolti

I settori determinano quali competenze vengono mostrate nei radar e nelle valutazioni.

### 8.3 Tab Ultimi 7 Giorni

**Passo 1.** Clicca su "📅 Ultimi 7gg".

> **Cosa vedrai:** Una card con header blu "📋 Quiz ultimi 7 giorni" e una tabella:

| Colonna | Contenuto |
|---------|-----------|
| **Data** | Data e ora del tentativo |
| **Quiz** | Nome del quiz |
| **Corso** | Nome del corso |
| **Stato** | Badge colorato (vedi sotto) |
| **Azioni** | Link "Review" per ogni quiz completato |

**Colori stato quiz:**

| Stato | Colore badge |
|-------|-------------|
| Completato | Verde |
| In corso | Blu |
| Scaduto | Giallo |
| Abbandonato | Rosso |

**Passo 2.** Clicca su "Review" per vedere il dettaglio delle risposte di un quiz.

> **Cosa succede:** Si apre la pagina di review del quiz Moodle con tutte
> le domande e le risposte dello studente.

### 8.4 Tab Configurazione Report

Questo tab e fondamentale per personalizzare cosa vedere nel report.

**Passo 1.** Clicca su "⚙️ Configurazione".

> **Cosa vedrai:** Un pannello con toggle e slider.

**5 Toggle di visualizzazione:**

| Toggle | Parametro | Default | Cosa attiva |
|--------|-----------|---------|-------------|
| **Doppio Radar** | `show_dual_radar` | ON (se quiz selezionati) | Radar autovalutazione affiancato al radar quiz |
| **Gap Analysis** | `show_gap` | ON | Tabella analisi scostamenti |
| **Spunti Colloquio** | `show_spunti` | ON | Suggerimenti per il colloquio |
| **Valutazione Formatore** | `show_coach_eval` | ON | Sezione valutazione coach |
| **Grafico Sovrapposizione** | `show_overlay` | ON | Radar overlay con tutte e 4 le fonti sovrapposte |

**Soglie configurabili:**

| Soglia | Parametro | Default | Range | Significato |
|--------|-----------|---------|-------|-------------|
| **Allineamento** | `soglia_allineamento` | 10% | 5-40% | Sotto questa % di gap, Quiz e Autoval sono considerati allineati (verde) |
| **Monitorare** | `soglia_monitorare` | 25% | 15-60% | Gap tra allineamento e monitorare = attenzione (arancione) |
| **Critico** | `soglia_critico` | 30% | 20-80% | Sopra questa % = gap critico (rosso) |

**Passo 2.** Modifica i toggle e le soglie secondo le tue esigenze.

**Passo 3.** Clicca il pulsante **"Aggiorna Grafici"**.

> **Cosa succede:** La pagina si ricarica con le nuove impostazioni applicate.
> I grafici radar, la gap analysis e gli spunti colloquio si aggiornano.

> **Suggerimento:** Per un primo sguardo veloce, lascia tutto attivo.
> Per la stampa, disattiva le sezioni che non servono per ridurre le pagine.

### 8.5 Grafici Radar

Il Report Studente utilizza diversi tipi di grafici radar:

**Radar SVG (statico):**
- Dimensione: 300x300 pixel
- Cerchi concentrici: 20%, 40%, 60%, 80%, 100%
- Linea di riferimento arancione al 60%
- Linea di riferimento verde all'80%
- Un asse per ogni area di competenza

**Radar Canvas (Chart.js, interattivo):**
- **radarAreas**: radar per aree, colorato per fonte
- **radarAutovalutazione**: radar autovalutazione sovrapposto
- **radarPerformanceDual**: doppio radar quiz vs autovalutazione affiancato

**Radar Overlay (4 fonti sovrapposte):**
- Quiz (colore 1) + Autovalutazione (colore 2) + LabEval (colore 3) + Coach (colore 4)
- Tutti sovrapposti sullo stesso grafico
- Leggenda con checkbox per attivare/disattivare le fonti

> **Come leggere il radar:**
> - Un'area che "sporge" molto verso l'esterno = competenza alta
> - Un'area vicina al centro = competenza bassa
> - Confrontando le forme colorate puoi vedere dove c'e accordo tra le fonti
>   e dove ci sono discrepanze (gap)

> **SCREENSHOT 8.5:** Radar overlay con 4 fonti sovrapposte e legenda

### 8.6 Tab Progresso Certificazione

**Passo 1.** Clicca su "📊 Progresso".

> **Cosa vedrai:** Una barra di progresso certificazione che mostra:
> - **Certificati** (verde): competenze con >=80%
> - **In corso** (giallo): competenze con >0% ma <80%
> - **Da iniziare** (grigio): competenze a 0%
>
> La barra mostra la percentuale complessiva e il rapporto numerico
> (es. "12 certificati / 5 in corso / 3 da iniziare su 20 totali").

### 8.7 Stampa Personalizzata (Modal)

Il Report Studente include un potente sistema di stampa personalizzata.

**Passo 1.** Clicca sul pulsante "🖨️ Stampa Personalizzata" (in alto nel report).

> **Cosa succede:** Si apre un **modal** (finestra sovrapposta) con il titolo
> e due pulsanti in fondo: "Annulla" e "🖨️ Genera Stampa".

**Contenuto del modal di stampa:**

**Sezioni da includere (checkbox):**
- ☑ Panoramica
- ☑ Piano d'Azione
- ☑ Progressi
- ☑ Dettagli Competenze
- ☑ Radar per Aree
- ☑ Doppio Radar (se attivato)
- ☑ Gap Analysis (se attivato)
- ☑ Spunti Colloquio (se attivato)

**Selezione radar per area:** Checkbox per ogni area (A, B, C, ...) per includere il radar specifico.

**Ordinamento sezioni:** Dropdown numerati (1-11) per ogni sezione, che determinano l'ordine di stampa:

| Sezione | Ordine default |
|---------|---------------|
| Valutazione | 1 |
| Progressi | 2 |
| Radar Aree | 3 |
| Radar Dettagli | 4 |
| Piano | 5 |
| Dettagli | 6 |
| Dual Radar | 7 |
| Gap Analysis | 8 |
| Spunti | 9 |
| Suggerimenti | 10 |
| Coach Eval | 11 |

**Tono suggerimenti:**
- **Formale** (per aziende/URC): terza persona, linguaggio professionale
- **Colloquiale** (per uso interno): seconda persona, linguaggio diretto

**Filtro settore per stampa parziale:** Dropdown per stampare solo un settore specifico.

**Passo 2.** Seleziona le sezioni desiderate e configura l'ordine.

**Passo 3.** Clicca "🖨️ Genera Stampa".

> **Cosa succede:** Si apre una versione stampabile del report con solo
> le sezioni selezionate, nell'ordine configurato. La finestra di stampa
> del browser si apre automaticamente.

### 8.8 Configurazione Dettagliata dei Toggle

Ogni toggle nella Configurazione Report attiva/disattiva una sezione specifica. Ecco cosa cambia esattamente:

**Toggle "Doppio Radar" (show_dual_radar):**

Quando **attivo:**
- Nella tab Panoramica appare un secondo radar accanto a quello dei quiz
- Il radar aggiuntivo mostra i dati dell'autovalutazione
- Puoi confrontare visivamente le due "forme" radar

Quando **disattivo:**
- Appare solo il radar dei quiz
- Utile se l'autovalutazione non e ancora stata completata

**Toggle "Gap Analysis" (show_gap):**

Quando **attivo:**
- Nel pannello FTM appare il tab "📈 Gap Analysis"
- La tabella gap e visibile con tutti gli indicatori colorati
- Le soglie configurabili sono attive

Quando **disattivo:**
- Il tab Gap Analysis non e disponibile
- Utile nelle prime settimane quando non ci sono ancora dati sufficienti

**Toggle "Spunti Colloquio" (show_spunti):**

Quando **attivo:**
- Nel pannello FTM appare il tab "💬 Spunti Colloquio"
- Le domande suggerite per ogni area critica sono visibili
- Le 3 categorie (Critici, Attenzione, Positivi) sono generate

Quando **disattivo:**
- Nessun suggerimento disponibile
- Utile se preferisci preparare i colloqui autonomamente

**Toggle "Valutazione Formatore" (show_coach_eval):**

Quando **attivo:**
- I dati della valutazione coach vengono inclusi nei grafici
- Il quarto poligono (Coach) appare nel radar overlay
- Le colonne "Coach" appaiono nelle tabelle

Quando **disattivo:**
- Solo 3 fonti dati (Quiz, Auto, Lab)
- Utile prima di aver iniziato la valutazione formatore

**Toggle "Grafico Sovrapposizione" (show_overlay):**

Quando **attivo:**
- Un grafico radar con 4 poligoni sovrapposti
- Leggenda interattiva per attivare/disattivare singole fonti
- La vista piu completa e informativa

Quando **disattivo:**
- Nessun radar overlay
- I radar individuali restano visibili nella tab Panoramica

> **Suggerimento:** Per la prima visita di un report, lascia tutto attivo
> (il sistema lo fa automaticamente quando ci sono quiz selezionati).
> Disattiva solo quando vuoi stampare un report snello.

### 8.9 Modificare le Soglie Gap

Le soglie determinano come vengono classificati i gap. Puoi personalizzarle per adattarle al contesto.

**Per modificare le soglie:**

**Passo 1.** Nel tab FTM "⚙️ Configurazione", trova la sezione "Soglie configurabili".

**Passo 2.** Modifica i valori con i controlli numerici o gli slider:

| Soglia | Range | Default | Effetto |
|--------|-------|---------|---------|
| **Allineamento** | 5% - 40% | 10% | Gap sotto questa soglia = "Allineato" (verde) |
| **Monitorare** | 15% - 60% | 25% | Gap tra Allineamento e Monitorare = "Da monitorare" (arancione) |
| **Critico** | 20% - 80% | 30% | Gap sopra questa soglia = "Critico" (rosso) |

> **Regole automatiche di coerenza:**
> - Monitorare deve essere sempre > Allineamento (se impostato uguale o inferiore, viene corretto automaticamente a Allineamento + 15%)
> - Critico deve essere sempre > Monitorare (se impostato uguale o inferiore, viene corretto a Monitorare + 5%)

**Passo 3.** Clicca "Aggiorna Grafici" per applicare le nuove soglie.

> **Esempio pratico:**
> - Se imposti Allineamento a 15%: piu gap saranno considerati "allineati"
>   (meno badge rossi, approccio piu tollerante)
> - Se imposti Critico a 20%: piu gap saranno "critici"
>   (piu badge rossi, approccio piu rigoroso)
>
> **Quando modificare le soglie:**
> - **Classe avanzata:** Abbassa le soglie (piu rigoroso)
> - **Classe principiante:** Alza le soglie (piu tollerante)
> - **Settore complesso:** Alza la soglia di allineamento

> **SCREENSHOT 8.9:** Modal stampa personalizzata con opzioni

---

## 9. Report Studente - Gap Analysis e Spunti Colloquio

### 9.1 Tab Gap Analysis

La Gap Analysis confronta automaticamente i risultati dei quiz con l'autovalutazione dello studente.

**Passo 1.** Clicca su "📈 Gap Analysis" nel pannello FTM (oppure attivala dalla Configurazione).

> **Cosa vedrai:** Una tabella con le competenze ordinate per magnitudine del gap
> (il gap piu grande appare per primo).

**Formula del Gap:**

```
Gap = Autovalutazione (%) - Performance Quiz (%)
```

### 9.2 Soglie e Indicatori

Le soglie determinano come viene classificato il gap:

| Intervallo Gap | Classificazione | Indicatore | Colore |
|---------------|-----------------|------------|--------|
| |Gap| < Soglia Allineamento (10%) | **Allineato** | ✓ Check | Verde |
| Soglia Allineamento < |Gap| < Soglia Critico | **Da monitorare** | ↕ Freccia | Arancione |
| |Gap| > Soglia Critico (30%) | **Critico** | ⚠ Warning | Rosso |

**Direzione del gap:**

| Gap | Significato | Indicatore |
|-----|-------------|------------|
| **Positivo (+)** = Sovrastima | Lo studente si valuta meglio di quanto risulti dai quiz | ↑ Freccia su, colore rosso |
| **Negativo (-)** = Sottovalutazione | Lo studente si valuta peggio di quanto risulti | ↓ Freccia giu, colore arancione |
| **Zero** = Allineato | Percezione corretta | ✓ Check, colore verde |

### 9.3 Interpretare la Gap Analysis

> **Esempio pratico:**
>
> Lo studente Mario si e autovalutato al 90% nell'area "Misurazione"
> ma nei quiz ha ottenuto il 45%.
>
> Gap = 90% - 45% = +45% (Sovrastima critica)
>
> Significa: Mario pensa di essere molto bravo nella misurazione,
> ma i test dicono il contrario. Argomento prioritario per il colloquio.

### 9.4 Tab Spunti Colloquio

**Passo 1.** Clicca su "💬 Spunti Colloquio" nel pannello FTM.

> **Cosa vedrai:** I suggerimenti per il colloquio organizzati in 3 categorie:

**1. Critici (gap > soglia critico):**
- Domande specifiche da porre allo studente
- Messaggio contestualizzato sulla discrepanza
- Sfondo rosso chiaro per evidenziare l'urgenza

**2. Attenzione (gap medio):**
- Suggerimenti di miglioramento
- Aree da monitorare nel tempo
- Sfondo arancione chiaro

**3. Positivi (sottovalutazione):**
- Punti di forza da valorizzare
- Competenze dove lo studente si sottovaluta
- Sfondo verde chiaro

**Domande suggerite per settore:**

Il sistema include domande specifiche per ogni area e settore. Esempi:

Per **AUTOMOBILE** - Area F (Telaio/Sospensioni):
- "Descrivi la procedura di spurgo freni che utilizzi abitualmente"
- "Come verifichi lo stato di usura dei componenti delle sospensioni?"
- "Quali controlli esegui sulla geometria dello sterzo?"

Per **MECCANICA** - Area Misurazione:
- "Quali strumenti di misura sai utilizzare?"
- "Come verifichi la taratura di uno strumento?"
- "Descrivi la procedura di misurazione con micrometro"

Per **MECCANICA** - Area CNC:
- "Quali controlli numerici conosci e utilizzi?"
- "Come imposti i parametri di lavorazione per un nuovo pezzo?"
- "Descrivi la procedura di azzeramento utensili"

> **Suggerimento:** Stampa gli spunti colloquio il giorno prima dell'incontro.
> Usa il modal di stampa personalizzata selezionando solo "Spunti Colloquio"
> e "Gap Analysis" per un foglio di preparazione compatto.

### 9.5 Suggerimenti Automatici con Tono

Il sistema genera commenti automatici basati sui gap rilevati. Sono disponibili in **due toni**:

**Tono Formale** (per report scritti, URC, aziende):
> "Lo studente mostra una percezione delle proprie competenze superiore ai risultati
> oggettivi nell'area della saldatura. Si consiglia di proporre esercitazioni
> pratiche mirate per consolidare le competenze."

**Tono Colloquiale** (per il colloquio diretto con lo studente):
> "Hai ottenuto risultati migliori di quanto pensassi nella lettura del disegno
> tecnico. Questo e un tuo punto di forza su cui costruire!"

**Per cambiare il tono:**

**Passo 1.** Nel tab FTM "⚙️ Configurazione" o nel modal di stampa, trova il selettore "Tono".

**Passo 2.** Seleziona "Formale" o "Colloquiale" (parametro `tono_commenti`).

> **Suggerimento:** Usa il tono "Formale" per la documentazione ufficiale
> e il tono "Colloquiale" per preparare il colloquio faccia a faccia.

### 9.6 Settore per la Stampa

Puoi filtrare la Gap Analysis e gli Spunti per settore nella stampa:

**Passo 1.** Nel modal "Stampa Personalizzata", trova il filtro "Settore per stampa" (parametro `print_sector`).

**Passo 2.** Seleziona "Tutti" (default) o un settore specifico.

> **Cosa succede:** La stampa includera solo le competenze del settore selezionato.
> Utile quando lo studente ha competenze in piu settori e vuoi un report focalizzato.

### 9.7 Uso Pratico della Gap Analysis

**Per un colloquio efficace basato sulla Gap Analysis:**

1. **Identifica i gap critici** (rossi): sono le priorita assolute del colloquio
2. **Prepara domande specifiche** dagli Spunti Colloquio per le aree critiche
3. **Nota i gap positivi** (sottovalutazione verde): punti di forza da valorizzare
4. **Confronta con la Valutazione Formatore:** se il tuo giudizio e diverso sia dal quiz che dall'autovalutazione, approfondisci durante il colloquio
5. **Aggiorna le soglie** se necessario: se troppi gap risultano "critici" potresti alzare la soglia, se troppo pochi potresti abbassarla

> **Attenzione:** La Gap Analysis funziona solo se lo studente ha completato
> sia almeno un quiz sia l'autovalutazione. Se manca una delle due fonti,
> i dati saranno incompleti.

> **SCREENSHOT 9.7:** Spunti colloquio con le 3 categorie colorate

---

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

## 11. Bilancio Competenze

### 11.1 Cos'e il Bilancio Competenze

Il Bilancio Competenze (`reports_v2.php`) e lo strumento per preparare i colloqui formativi. A differenza del Report Studente (che e focalizzato sull'analisi dati), il Bilancio e orientato alla **sintesi e al confronto**.

### 11.2 Accesso

**Dalla Dashboard:**

**Passo 1.** Clicca sul pulsante "💬 Colloquio" nella card di uno studente.

> **Cosa succede:** Si apre `reports_v2.php?studentid=X` con i dati dello studente.

**Accesso diretto (senza studentid):**

Se apri `/local/coachmanager/reports_v2.php` senza parametri:

> **Cosa vedrai:** Una card centrata con titolo "👤 Seleziona Studente per Report Colloquio".
> Un dropdown con tutti gli studenti (nome + email).
> Un pulsante "📋 Visualizza Report Colloquio" (gradiente viola).

**Passo 1.** Seleziona lo studente.

**Passo 2.** Clicca "📋 Visualizza Report Colloquio".

### 11.3 Header del Bilancio

> **Cosa vedrai:** Un header con gradiente (diverso dal report studente).
> - Titolo pagina
> - Nome studente
> - Metadati: numero competenze, quiz svolti, stato autovalutazione
> - Pulsanti azione nell'header

### 11.4 Le 6 Tab

Il Bilancio Competenze ha **6 tab** di navigazione:

| # | Tab | Icona | Funzione |
|---|-----|-------|----------|
| 1 | **Panoramica** | 📋 | Sintesi con stat card cliccabili, punti di forza, aree critiche |
| 2 | **Radar Confronto** | 📊 | Radar con checkbox Quiz/Autoval, filtro settore |
| 3 | **Mappa Competenze** | 🎯 | Mappa visiva con card area cliccabili |
| 4 | **Confronta Studenti** | 👥 | Confronto side-by-side tra 2 studenti |
| 5 | **Colloquio** | 💬 | Priorita, domande suggerite, note coach |
| 6 | **Matching Lavoro** | 🎯 | (Coming soon) matching con profili professionali |

**Per navigare tra i tab:**

**Passo 1.** Clicca sul tab desiderato nella barra di navigazione.

> **Cosa succede:** Il pannello del tab si attiva (classe `active`).
> Gli altri pannelli si nascondono. La navigazione e client-side (senza ricaricamento).

### 11.5 Tab Panoramica

**Passo 1.** Clicca su "📋 Panoramica" (tab predefinito).

> **Cosa vedrai:**
>
> **Sezione "Situazione Attuale" con 5 stat card cliccabili:**

| Card | Icona | Colore | Contenuto | Al click |
|------|-------|--------|-----------|----------|
| **Autovalutazione** | 🧑 | Viola | ✅ se completata, ❌ se no | Apre pagina autovalutazione |
| **Quiz completati** | 📝 | Blu | Numero quiz completati | Filtra la mappa competenze |
| **Aree eccellenti** | ✅ | Verde | Conteggio aree >=90% | Filtra per stato "excellent" |
| **Aree attenzione** | ⚠️ | Arancione | Conteggio aree 50-69% | Filtra per stato "warning" |
| **Aree critiche** | 🔴 | Rosso | Conteggio aree <50% | Filtra per stato "critical" |

Ogni card ha un testo "Clicca per vedere dettagli" in piccolo.

> **Sotto le stat card:**
> - **Punti di Forza:** Lista delle aree con percentuale piu alta
> - **Aree Critiche:** Lista delle aree con percentuale piu bassa
> - **Link "Report Dettagliato"** che apre `student_report.php`

### 11.6 Tab Radar Confronto

**Passo 1.** Clicca su "📊 Radar Confronto".

> **Cosa vedrai:**
> - **Checkbox** per selezionare le fonti da sovrapporre: Quiz, Autovalutazione
> - **Filtro settore** (dropdown) per restringere a un settore specifico
> - **2 grafici** affiancati:
>   - **Grafico Confronto:** Radar con le fonti selezionate sovrapposte
>   - **Grafico GAP:** Radar che evidenzia le differenze tra le fonti

**Come usare il Radar Confronto:**

**Passo 2.** Attiva le checkbox delle fonti che vuoi confrontare.

> **Cosa succede:** I poligoni corrispondenti appaiono/scompaiono sul radar.
> Ogni fonte ha un colore diverso con area semi-trasparente.

**Passo 3.** (Opzionale) Seleziona un settore dal dropdown "Settore".

> **Cosa succede:** I radar si ricalcolano mostrando solo le competenze
> del settore selezionato. I nodi sul bordo del radar cambiano.

> **Suggerimento:** Confronta sempre Quiz vs Autovalutazione per identificare
> dove lo studente si sopravvaluta o si sottovaluta. Le aree dove i poligoni
> divergono significativamente sono i punti da approfondire nel colloquio.

> **SCREENSHOT 11.6:** Radar Confronto con Quiz e Autovalutazione sovrapposti

### 11.7 Tab Mappa Competenze

**Passo 1.** Clicca su "🎯 Mappa Competenze".

> **Cosa vedrai:**
> - **Filtri** in alto: dropdown settore + dropdown stato
> - **Legenda colori** (4 livelli):

| Stato | Colore | Percentuale |
|-------|--------|-------------|
| **Eccellente** | Verde (#28a745) | >=90% |
| **Buono** | Teal (#17a2b8) | 70-89% |
| **Attenzione** | Giallo (#ffc107) | 50-69% |
| **Critico** | Rosso (#dc3545) | <50% |

> - **Card area cliccabili:** Una card per ogni area di competenza.
>   Ogni card mostra: nome area, icona, percentuale, colore stato.

**Passo 2.** Clicca su una card area.

> **Cosa succede:** Si apre un **modal dettaglio area** con:
> - **Header colorato** in base allo stato dell'area
> - **Statistiche dell'area:** totale competenze, media percentuale
> - **3 box valori:**
>   - **Quiz:** percentuale da quiz (blu)
>   - **Auto:** percentuale autovalutazione (viola)
>   - **Gap:** differenza (verde se positivo, rosso se negativo)
> - **Lista competenze espandibili:** clicca per vedere i tentativi quiz singoli

**Per chiudere il modal:** Clicca fuori dal modal o sul pulsante X.

### 11.8 Tab Confronta Studenti

**Passo 1.** Clicca su "👥 Confronta Studenti".

> **Cosa vedrai:**
> - **2 dropdown** per selezionare gli studenti da confrontare
> - **Tabella confronto** con barre per ogni area:
>   - Colonna Studente 1 con barra colorata
>   - Colonna Studente 2 con barra colorata
>   - Differenza evidenziata
> - **Radar sovrapposto** (caricato via AJAX da `ajax_compare_students.php`)

**Passo 2.** Seleziona il primo studente dal dropdown 1.

**Passo 3.** Seleziona il secondo studente dal dropdown 2.

> **Cosa succede:** Il sistema carica i dati via AJAX e genera:
> - La tabella di confronto
> - Il radar sovrapposto con 2 poligoni colorati

> **Suggerimento:** Usa il confronto per bilanciare il livello della classe.
> Confronta lo studente migliore con quello peggiore per capire il range.
> Puoi anche confrontare due studenti dello stesso settore per verificare
> se il percorso formativo produce risultati coerenti.

> **Attenzione:** Il confronto carica i dati via AJAX. Se la connessione
> e lenta o la sessione e scaduta, il radar potrebbe non caricarsi.
> In quel caso ricarica la pagina (Ctrl+F5).

> **SCREENSHOT 11.8:** Tab Confronta Studenti con radar sovrapposto a 2 poligoni

### 11.9 Tab Colloquio

**Passo 1.** Clicca su "💬 Colloquio".

> **Cosa vedrai:**
>
> **Sezione Priorita:**
> - Aree critiche (rosso) con percentuali e indicatori
> - Aree moderate (arancione) da monitorare
>
> **Domande suggerite:**
> - Domande specifiche per le aree critiche e moderate dello studente
> - Le domande variano in base al settore (AUTOMOBILE, MECCANICA, etc.)
>
> Esempio per area sospensioni automobile:
> - "Descrivi la procedura di spurgo freni che utilizzi abitualmente"
> - "Come verifichi lo stato di usura dei componenti delle sospensioni?"
>
> **Preparazione colloquio tecnico:**
> - Le 3 aree con punteggio peggiore evidenziate
> - Suggerimenti di approccio per ciascuna
>
> **Note coach (con salvataggio):**

**Passo 2.** Scrivi le note nella textarea "Note Coach".

**Passo 3.** Clicca il pulsante "Salva note".

> **Cosa succede:** Le note vengono salvate via AJAX.
> Il pulsante mostra feedback "Salvato!".

### 11.10 Tab Colloquio - Dettaglio Completo

Il tab Colloquio nel Bilancio e lo strumento piu utile per preparare un incontro con lo studente.

> **Cosa vedrai in dettaglio:**
>
> **1. Sezione Priorita per il Colloquio:**
> - Elenco delle aree ordinate per criticita (dalla peggiore alla migliore)
> - Per ogni area: nome, icona, percentuale, badge stato, barra colorata
> - Le aree critiche (<50%) sono evidenziate con sfondo rosso chiaro
> - Le aree moderate (50-69%) con sfondo arancione chiaro
> - Le aree buone (>=70%) con sfondo verde chiaro
>
> **2. Domande Suggerite per Area:**
> Il sistema genera domande specifiche basate sulle aree critiche dello studente.
> Le domande sono raggruppate per area e settore.
>
> **Esempio per studente AUTOMOBILE con area F critica:**
> > **Area F - Telaio, Sospensioni, Freni** (42% - CRITICO)
> > 1. "Descrivi la procedura di spurgo freni che utilizzi abitualmente"
> > 2. "Come verifichi lo stato di usura dei componenti delle sospensioni?"
> > 3. "Quali controlli esegui sulla geometria dello sterzo?"
>
> **Esempio per studente MECCANICA con area CNC critica:**
> > **Area CNC - Controllo Numerico** (35% - CRITICO)
> > 1. "Quali controlli numerici conosci e utilizzi?"
> > 2. "Come imposti i parametri di lavorazione per un nuovo pezzo?"
> > 3. "Descrivi la procedura di azzeramento utensili"
> > 4. "Come gestisci gli offset e le correzioni utensile?"
>
> **3. Preparazione Colloquio Tecnico:**
> - Le **3 aree con punteggio peggiore** sono evidenziate in un box speciale
> - Per ciascuna: suggerimento di approccio, domande mirate, obiettivo del dialogo
>
> **4. Note Coach con Salvataggio:**
> - Textarea per scrivere note prima/durante/dopo il colloquio
> - Pulsante "Salva note" con feedback AJAX

**Workflow consigliato per il colloquio:**

**Passo 1.** Apri il Bilancio dello studente (💬 Colloquio dalla Dashboard).

**Passo 2.** Vai al tab "💬 Colloquio".

**Passo 3.** Leggi le 3 aree peggiori e le domande suggerite.

**Passo 4.** Scrivi nelle note le domande aggiuntive che vuoi porre.

**Passo 5.** Clicca "Salva note" per memorizzare.

**Passo 6.** (Opzionale) Stampa la sezione dal Report Studente (modal stampa -> solo Spunti).

**Passo 7.** Dopo il colloquio, torna qui e aggiorna le note con l'esito.

### 11.10b Tab Matching Lavoro

**Passo 1.** Clicca su "🎯 Matching Lavoro".

> **Cosa vedrai:** Una sezione con il messaggio "Coming Soon" e la descrizione
> della funzionalita futura: matching tra il profilo competenze dello studente
> e i profili professionali richiesti dalle aziende.
>
> **Funzionalita prevista:** Il sistema confrontera il profilo competenze dello
> studente con i profili professionali richiesti dalle aziende partner. Mostrera
> un punteggio di compatibilita per ogni posizione disponibile.

> **Nota tecnica:** Questa funzionalita e in fase di sviluppo. Non appena sara
> attiva, i dati verranno caricati automaticamente senza bisogno di configurazione.

### 11.11 Modal Dettaglio Area (dalla Mappa Competenze)

Quando clicchi su una card area nella Mappa Competenze, si apre un modal ricco di informazioni:

> **Cosa vedrai:**
>
> **Header del modal:** colorato in base allo stato dell'area
> - Verde per Eccellente, Teal per Buono, Giallo per Attenzione, Rosso per Critico
> - Nome area e icona
>
> **Statistiche dell'area:**
> - Totale competenze nell'area
> - Media percentuale
> - Numero quiz svolti in quest'area
>
> **3 box valori affiancati:**
> - **Quiz** (sfondo blu chiaro): percentuale media da quiz
> - **Auto** (sfondo viola chiaro): percentuale autovalutazione
> - **Gap** (sfondo verde/rosso): differenza tra Auto e Quiz
>   - Verde se gap basso (allineamento)
>   - Rosso se gap alto (discrepanza)
>
> **Lista competenze espandibili:**
> - Ogni competenza mostra codice + nome + percentuale
> - Clicca su una competenza per espandere e vedere:
>   - Tentativi quiz singoli con data e punteggio
>   - Trend nel tempo (se multipli tentativi)

**Per chiudere il modal:** Clicca fuori dal modal o premi Esc.

### 11.12 Filtro Settore Globale

In tutte le tab del Bilancio, puoi filtrare per settore:

**Passo 1.** Usa il dropdown "Settore" (parametro `sector`) nell'header o nei filtri della tab.

**Passo 2.** Seleziona un settore (es. "MECCANICA").

> **Cosa succede:** Tutti i dati (stat card, radar, mappa, colloquio) vengono
> filtrati mostrando solo le competenze del settore selezionato.

**Passo 3.** Seleziona "Tutti" per rimuovere il filtro.

> **Suggerimento:** Filtra per settore quando lo studente ha competenze
> in piu settori e vuoi concentrarti su uno specifico per il colloquio.

> **SCREENSHOT 11.12:** Tab Panoramica del Bilancio con stat card

---

## 12. Note Coach

### 12.1 Il Sistema Note

Le note coach sono annotazioni testuali che puoi associare a ogni studente. Servono per:
- Registrare osservazioni durante il percorso
- Documentare colloqui effettuati
- Tracciare accordi con lo studente
- Annotare situazioni particolari

### 12.2 Note dalla Dashboard

Nella vista **Standard** e **Dettagliata** della Coach Dashboard, ogni card ha una sezione note:

> **Cosa vedrai:** Una textarea con placeholder "Scrivi qui le tue note su questo studente..."
> Sotto la textarea un pulsante "💾 Salva Note" (btn-success piccolo).
> Un testo informativo: "Visibili anche alla segreteria".

**Per salvare una nota:**

**Passo 1.** Scrivi il testo nella textarea.

**Passo 2.** Clicca "💾 Salva Note".

> **Cosa succede:** Il sistema invia la richiesta AJAX a `ajax_save_notes.php`
> con i parametri: `studentid`, `notes` (testo codificato), `sesskey`.
>
> **Feedback visivo:** Il pulsante cambia testo in "✓ Salvato!" e lo sfondo
> diventa verde (#28a745). Dopo 2 secondi torna allo stato originale.

### 12.3 Note da Reports V2 (Bilancio)

Nel tab "💬 Colloquio" del Bilancio Competenze:

> **Cosa vedrai:** Una textarea per le note nella sezione preparazione colloquio.
> Un pulsante "Salva note" che salva via AJAX.

### 12.4 Visibilita delle Note

| Chi puo vedere | Accesso |
|----------------|---------|
| ✅ Tu (coach autore) | Sempre |
| ✅ Segreteria | Sempre |
| ❌ Lo studente | **MAI** |

> **Attenzione:** Le note sono in formato sovrascrittura: ogni salvataggio
> sostituisce il testo precedente. **Non c'e storico versioni.**
> Se vuoi mantenere lo storico, aggiungi la data prima di ogni annotazione:
>
> ```
> [15/02/2026] Primo colloquio: studente motivato ma insicuro sulla misurazione.
> [18/02/2026] Secondo colloquio: miglioramento visibile dopo esercitazione pratica.
> ```

> **SCREENSHOT 12.4:** Textarea note con pulsante Salva e feedback "Salvato!"

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

## 15. Casi d'Uso Pratici

### 15.1 Caso 1: Primo Giorno - Nuovo Studente Assegnato

**Scenario:** Ti viene assegnato un nuovo studente, Mario Rossi, alla settimana 1.

**Passo 1.** Apri la Coach Dashboard V2.

**Passo 2.** Cerca Mario nella griglia. Se non lo vedi, resetta i filtri cliccando "Tutti".

> **Cosa vedrai:** La card di Mario con badge "Sett. 1" e barre di progresso tutte a 0%.

**Passo 3.** Espandi la card cliccando sull'header.

**Passo 4.** Verifica i dati: settore primario (medaglia 🥇), corso, email.

**Passo 5.** Se l'autovalutazione e mancante, clicca "📨 Sollecita" per inviargli il promemoria.

**Passo 6.** Nella sezione Note, scrivi: "[data] Primo contatto - studente assegnato."

**Passo 7.** Clicca "💾 Salva Note".

**Passo 8.** Se necessario, assegna le scelte settimanali (test + laboratorio) e clicca "✓ Salva".

> **Risultato atteso:** Mario riceve la notifica per l'autovalutazione.
> Le scelte sono assegnate. La tua nota e salvata per il follow-up.

### 15.2 Caso 2: Invio Autovalutazione e Monitoraggio

**Scenario:** 6 studenti non hanno completato l'autovalutazione.

**Passo 1.** Dalla Dashboard, clicca il Quick Filter "Manca Autoval (6)".

> **Cosa vedrai:** Solo i 6 studenti senza autovalutazione.

**Passo 2.** Per ognuno, clicca "📨 Sollecita".

**Passo 3.** Per monitorare la risposta, vai alla Self-Assessment Dashboard (`/local/selfassessment/index.php`).

**Passo 4.** Clicca il filtro "⏳ In attesa" per vedere chi non ha ancora risposto.

**Passo 5.** Se dopo 3 giorni non c'e risposta, ripeti il promemoria o contatta direttamente lo studente.

### 15.3 Caso 3: Analisi Report con Gap Critici - Preparazione Colloquio

**Scenario:** Devi preparare un colloquio con lo studente Anna che ha gap critici.

**Passo 1.** Dalla Dashboard, clicca "📊 Report" sulla card di Anna.

**Passo 2.** Nel Report, vai al tab FTM "📈 Gap Analysis".

> **Cosa vedrai:** Le competenze ordinate per magnitudine di gap.
> Le righe in rosso hanno gap > 30%.

**Passo 3.** Identifica le 3 aree con gap piu grande.

**Passo 4.** Clicca su "💬 Spunti Colloquio" nel pannello FTM.

> **Cosa vedrai:** Domande suggerite per le aree critiche di Anna.

**Passo 5.** Stampa gli spunti: clicca "🖨️ Stampa Personalizzata", seleziona solo "Gap Analysis" e "Spunti Colloquio", scegli tono "Colloquiale", clicca "🖨️ Genera Stampa".

**Passo 6.** Annota le osservazioni nelle Note Coach della Dashboard.

### 15.4 Caso 4: Valutazione Formatore Completa (dalla A alla Firma)

**Scenario:** Dopo 4 settimane di osservazione, devi completare la valutazione di Marco.

**Passo 1.** Dalla Dashboard, clicca "👤 Valutazione" sulla card di Marco.

**Passo 2.** Verifica il settore (🥇 Meccanica) nell'header.

**Passo 3.** Espandi l'area "A. Accoglienza e Diagnosi" cliccando sull'intestazione.

**Passo 4.** Per ogni competenza, clicca il pulsante del livello Bloom appropriato (es. "4" per Analizzare).

> **Cosa succede:** Il pulsante diventa viola. Il salvataggio parte automaticamente.

**Passo 5.** (Opzionale) Aggiungi note specifiche nei campi testo.

**Passo 6.** Ripeti per tutte le aree (A-G).

**Passo 7.** Scrivi le note generali in fondo.

**Passo 8.** Clicca "Salva e Completa".

> **Cosa succede:** Conferma, poi lo stato diventa "Completata" (banner verde).

**Passo 9.** Verifica il riepilogo. Se tutto e corretto, clicca "Firma Valutazione".

> **Cosa succede:** Conferma, poi la valutazione diventa "Firmata" (banner grigio).
> I pulsanti si disabilitano.

**Passo 10.** (Se necessario) Clicca "Autorizza Studente" per rendere visibile allo studente.

### 15.5 Caso 5: Confronto 2 Studenti per Bilancio Classe

**Scenario:** Vuoi confrontare Anna e Marco per capire il livello della classe.

**Passo 1.** Apri il Bilancio Competenze (`reports_v2.php?studentid=X`).

**Passo 2.** Clicca su "👥 Confronta Studenti".

**Passo 3.** Seleziona Anna dal primo dropdown.

**Passo 4.** Seleziona Marco dal secondo dropdown.

> **Cosa vedrai:** Tabella confronto con barre affiancate e radar sovrapposto.

### 15.6 Caso 6: Studente a Fine Percorso - Valutazione Finale + Export Word

**Scenario:** Lo studente Luca e alla settimana 6, devi concludere il percorso.

**Passo 1.** Dalla Dashboard, filtra "Fine 6 Sett." per trovare Luca.

> **Cosa vedrai:** La card di Luca con badge giallo "FINE 6 SETT."

**Passo 2.** Clicca "👤 Valutazione" e completa la valutazione finale.

**Passo 3.** Firma la valutazione.

**Passo 4.** Torna alla Dashboard. Clicca "📄 Word" sulla card di Luca.

> **Cosa succede:** Il browser scarica il file `.docx` con il report completo.

**Passo 5.** Apri il file Word e verificalo prima di consegnarlo.

### 15.7 Caso 7: Uso dello Scheduler per Pianificare la Settimana

**Scenario:** Devi organizzare la settimana prossima per il gruppo Giallo.

**Passo 1.** Apri lo Scheduler FTM.

**Passo 2.** Nel tab Calendario, naviga alla settimana desiderata con "Settimana succ."

**Passo 3.** Verifica le attivita gia programmate nella griglia.

**Passo 4.** Se serve una nuova attivita, clicca "📅 Nuova Attivita".

**Passo 5.** Compila il form: nome, tipo, gruppo Giallo, data, fascia, aula.

**Passo 6.** Clicca "📅 Crea Attivita".

### 15.8 Caso 8: Iscrizione Studente ad Atelier dalla Dashboard

**Scenario:** Lo studente Sara (settimana 4) deve frequentare un atelier obbligatorio.

**Passo 1.** Dalla Dashboard, espandi la card di Sara.

**Passo 2.** Scorri fino alla sezione "Atelier". Vedrai l'atelier con ⚠ "(Obbligatorio)".

**Passo 3.** Clicca "Iscrivimi".

**Passo 4.** Nel modal, seleziona una data disponibile (con posti liberi).

**Passo 5.** Conferma l'iscrizione.

> **Risultato:** Sara e iscritta all'atelier. L'alert obbligatorio scompare.

### 15.9 Caso 9: Colloquio Tecnico - Preparazione con Spunti + Note

**Scenario:** Domani hai il colloquio tecnico con Paolo.

**Preparazione (il giorno prima):**

**Passo 1.** Apri il Report di Paolo ("📊 Report" dalla Dashboard).

**Passo 2.** Tab "📊 Panoramica": identifica le aree forti e deboli.

**Passo 3.** Tab FTM "📈 Gap Analysis": nota le discrepanze.

**Passo 4.** Tab FTM "💬 Spunti Colloquio": leggi le domande suggerite.

**Passo 5.** Stampa: modal stampa personalizzata, seleziona Gap + Spunti, tono "Colloquiale".

**Passo 6.** Apri il Bilancio ("💬 Colloquio" dalla Dashboard), tab Colloquio: prepara le domande aggiuntive.

**Durante il colloquio:**

**Passo 7.** Usa i fogli stampati come guida.

**Passo 8.** Prendi appunti su carta o su un device.

**Dopo il colloquio:**

**Passo 9.** Dalla Dashboard, aggiorna le Note Coach con il resoconto.

**Passo 10.** Se necessario, aggiorna la Valutazione Formatore.

### 15.10 Caso 10: Gestione Studente senza Autovalutazione (Escalation)

**Scenario:** Lo studente non risponde dopo 2 reminder.

**Passo 1.** Verifica nella Self-Assessment Dashboard quanti reminder sono stati inviati.

**Passo 2.** Prova un contatto diretto (email personale, telefono).

**Passo 3.** Documenta i tentativi nelle Note Coach.

**Passo 4.** Se lo studente persiste a non rispondere:
- Informa la segreteria
- Valuta la possibilita di un colloquio in presenza
- Annota: "[data] Studente non raggiungibile. Segnalato a segreteria."

### 15.11 Caso 11: Stampa Personalizzata Report per Azienda

**Scenario:** Un'azienda ha chiesto il report di competenze dello studente.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Clicca "🖨️ Stampa Personalizzata".

**Passo 3.** Seleziona le sezioni: Panoramica, Piano, Radar Aree, Dettagli.

**Passo 4.** Seleziona tono "Formale" (terza persona, linguaggio professionale).

**Passo 5.** Seleziona il settore di interesse per l'azienda dal filtro settore.

**Passo 6.** Configura l'ordine: 1-Valutazione, 2-Radar, 3-Piano, 4-Dettagli.

**Passo 7.** Clicca "🖨️ Genera Stampa" e stampa in PDF.

### 15.12 Caso 12: Analisi Overlay 4 Fonti per Studente Complesso

**Scenario:** Lo studente ha risultati molto diversi tra le 4 fonti.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Nel tab FTM "⚙️ Configurazione", attiva "Grafico Sovrapposizione".

**Passo 3.** Clicca "Aggiorna Grafici".

> **Cosa vedrai:** Un radar con 4 poligoni sovrapposti:
> - Quiz, Autovalutazione, LabEval, Coach.

**Passo 4.** Identifica le aree dove i poligoni divergono:
- Se Quiz basso ma Autoval alto: lo studente si sovrastima
- Se Quiz alto ma Coach basso: forse lo studente studia ma non pratica
- Se LabEval alto ma Coach basso: verifica con il formatore lab

### 15.13 Caso 13: Cambio Settore Studente durante il Percorso

**Scenario:** Lo studente vuole cambiare da Meccanica ad Automobile.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Nel tab FTM "👤 Settori", modifica il settore primario.

**Passo 3.** Nella Valutazione Formatore, seleziona il nuovo settore dal dropdown.

> **Nota:** Le valutazioni del settore precedente restano salvate.
> Il sistema crea una nuova valutazione per il nuovo settore.

### 15.14 Caso 14: Uso Filtri Combinati per Trovare Studenti Critici

**Scenario:** Vuoi trovare tutti gli studenti del gruppo Rosso alla settimana 3+ con competenze sotto soglia.

**Passo 1.** Dalla Dashboard, apri i filtri avanzati.

**Passo 2.** Clicca il chip 🔴 Rosso.

**Passo 3.** Seleziona Settimana "3".

**Passo 4.** Seleziona Stato "Sotto soglia 50%".

> **Cosa vedrai:** Solo gli studenti che soddisfano TUTTI e 3 i criteri.

### 15.15 Caso 15: Verifica Completa di Uno Studente a Meta Percorso

**Scenario:** Lo studente e alla settimana 3, vuoi fare un check completo.

**Passo 1.** Dalla Dashboard, trova lo studente e verifica le 3 barre di progresso:
- Competenze: dovrebbe essere almeno 40-50% (quiz iniziali fatti)
- Autovalutazione: dovrebbe essere 100% (completata in settimana 1-2)
- Lab: puo essere ancora 0% (i laboratori iniziano dalla settimana 3)

**Passo 2.** Clicca "📊 Report" per aprire il Report Studente.

**Passo 3.** Tab "📊 Panoramica": identifica le aree forti (verde) e deboli (rosso) nel radar.

**Passo 4.** Tab FTM "📈 Gap Analysis": verifica se ci sono discrepanze significative tra autovalutazione e quiz.

**Passo 5.** Tab "📚 Piano": verifica il piano d'azione automatico. Le competenze critiche sono in fondo con le azioni suggerite.

**Passo 6.** Tab FTM "📊 Progresso": controlla la barra certificazione - quante competenze sono gia "certificate" (>=80%).

**Passo 7.** Inizia a compilare la Valutazione Formatore: clicca "👤 Valutazione" dalla Dashboard. Valuta almeno le competenze che hai potuto osservare.

**Passo 8.** Torna alla Dashboard e aggiorna le Note Coach con il riepilogo.

**Passo 9.** Se necessario, iscrivilo a un Atelier per rinforzare le aree deboli.

> **Risultato atteso:** Hai un quadro completo dello studente a meta percorso,
> una valutazione parziale iniziata e un piano di intervento documentato.

### 15.16 Caso 16: Export Dati per Riunione Team Coach

**Scenario:** Devi preparare materiale per la riunione settimanale dei coach.

**Passo 1.** Dalla Dashboard, clicca "Rapporto Classe" (pulsante blu in alto).

> **Cosa vedrai:** Un report aggregato con medie, distribuzione, confronti tra studenti.

**Passo 2.** Rivedi le statistiche aggregate. Prendi nota dei punti critici.

**Passo 3.** Torna alla Dashboard. Usa la vista Compatta per una panoramica veloce.

**Passo 4.** Filtra "Sotto Soglia 50%" per identificare gli studenti critici. Prepara un riassunto.

**Passo 5.** Filtra "Fine 6 Sett." per identificare chi deve uscire. Verifica le valutazioni.

**Passo 6.** Dallo Scheduler, vai al tab "📋 Attivita" e clicca "📥 Export Excel" per avere il calendario della settimana in formato foglio di calcolo.

**Passo 7.** Per ogni studente critico, genera una stampa dal Report (stampa personalizzata con Panoramica + Gap Analysis).

**Passo 8.** Usa il Bilancio Competenze -> Confronta Studenti per mostrare il range della classe.

---

## 16. Risoluzione Problemi

### 16.1 Non Vedo i Miei Studenti nella Dashboard

**Possibili cause:**
1. Filtri attivi che escludono studenti
2. Non sei assegnato come coach
3. Il corso non e selezionato

**Soluzione:**

**Passo 1.** Clicca "Tutti" nei Quick Filters per resettare.

**Passo 2.** Apri i filtri avanzati e verifica che nessun filtro sia attivo.

**Passo 3.** Se ancora vuoto, contatta la segreteria per verificare l'assegnazione.

### 16.2 Il Report Studente Non Mostra Dati

**Possibili cause:**
1. Lo studente non ha completato quiz
2. I quiz non hanno competenze assegnate
3. Il settore non e configurato

**Soluzione:**

**Passo 1.** Nel tab FTM "📅 Ultimi 7gg", verifica se ci sono quiz recenti.

**Passo 2.** Nel tab FTM "👤 Settori", verifica il settore primario.

**Passo 3.** Se non ci sono quiz, assegna un quiz allo studente dalla Dashboard (scelte settimanali).

### 16.3 Non Riesco a Salvare la Valutazione

**Possibili cause:**
1. Sessione Moodle scaduta
2. Valutazione gia firmata
3. Problemi di rete

**Soluzione:**

**Passo 1.** Verifica il banner stato: se e "Firmata" (grigio), usa "🔓 Riapri per Modifiche".

**Passo 2.** Prova a ricaricare la pagina (F5 o Ctrl+R).

**Passo 3.** Se appare un errore di sessione, esegui il logout e rientra.

> **Attenzione:** Il sistema salva automaticamente ogni 500ms.
> Se non vedi l'indicatore "Salvando...", potrebbe esserci un problema di rete.

### 16.4 I Grafici Radar Sono Vuoti

**Possibili cause:**
1. Nessun quiz selezionato
2. I quiz non hanno competenze collegate
3. Il settore filtro e sbagliato

**Soluzione:**

**Passo 1.** Nella parte superiore del Report, verifica i checkbox dei quiz.

**Passo 2.** Nel tab FTM "⚙️ Configurazione", verifica i toggle attivi.

**Passo 3.** Clicca "Aggiorna Grafici" per forzare il refresh.

### 16.5 L'Export Word Non Funziona

**Possibili cause:**
1. Lo studente non e a fine percorso (il pulsante non appare)
2. Popup bloccati dal browser
3. Errore server

**Soluzione:**

**Passo 1.** Verifica che il badge "FINE 6 SETT." sia presente sulla card.

**Passo 2.** Abilita i popup per il sito nelle impostazioni del browser.

**Passo 3.** Prova con un altro browser (Chrome consigliato).

**Passo 4.** Come alternativa, usa la stampa personalizzata e salva come PDF.

### 16.6 Il Sollecito Autovalutazione Non Parte

**Possibili cause:**
1. Lo studente ha gia completato l'autovalutazione
2. Lo studente e disabilitato nella Self-Assessment Dashboard
3. Problema AJAX

**Soluzione:**

**Passo 1.** Vai alla Self-Assessment Dashboard e verifica lo stato.

**Passo 2.** Se lo studente e "🚫 Disabilitato", clicca "🔔 Riabilita".

**Passo 3.** Riprova il sollecito dalla Dashboard.

### 16.7 La Pagina si Carica Lentamente

**Possibili cause:**
1. Cache del browser piena
2. Molti studenti visualizzati
3. Connessione lenta

**Soluzione:**

**Passo 1.** Prova Ctrl+F5 (ricarica forzata senza cache).

**Passo 2.** Usa la vista Compatta (meno elementi grafici).

**Passo 3.** Usa i filtri per ridurre il numero di studenti visualizzati.

### 16.8 Ho Perso le Mie Note

**Possibili cause:**
1. Le note sono state sovrascritte (nessuno storico)
2. Sessione scaduta prima del salvataggio

**Soluzione:**

> **Attenzione:** Le note non hanno storico versioni. Una volta sovrascritte, i contenuti precedenti sono persi.

**Passo 1.** Verifica il feedback "✓ Salvato!" dopo ogni salvataggio.

**Passo 2.** Adotta la pratica di aggiungere date alle note (vedi sezione 12.4).

### 16.9 Non Trovo il Pulsante "Word"

**Causa:** Il pulsante "Word" appare **solo** per studenti alla settimana 6+.

**Soluzione:** Verifica la settimana dello studente. Se non e ancora alla 6, il pulsante non e disponibile.

### 16.10 L'Iscrizione Atelier Fallisce

**Possibili cause:**
1. L'atelier e pieno (badge "PIENO")
2. Lo studente e gia iscritto
3. Problemi di sessione

**Soluzione:**

**Passo 1.** Verifica i posti disponibili nel modal (badge "X posti" vs "PIENO").

**Passo 2.** Se pieno, controlla date alternative.

**Passo 3.** Contatta la segreteria per richiedere posti aggiuntivi.

### 16.11 Il Confronto Studenti Non Carica i Dati

**Possibile causa:** Entrambi gli studenti devono avere almeno un quiz completato.

**Soluzione:**

**Passo 1.** Verifica che entrambi gli studenti abbiano quiz completati.

**Passo 2.** Riprova dopo aver selezionato studenti con dati disponibili.

### 16.12 La Sessione Scade Durante il Lavoro

**Causa:** La sessione Moodle ha un timeout (di solito 2 ore di inattivita).

**Sintomo:** Clicchi su un pulsante ma non succede nulla, oppure appare un errore di sessione.

**Soluzione:**

**Passo 1.** Apri una nuova scheda del browser e vai alla piattaforma FTM.

**Passo 2.** Se il login e ancora attivo, torna alla scheda precedente e ricarica (F5).

**Passo 3.** Se il login e scaduto, ri-effettua il login.

> **Suggerimento:** Per evitare la scadenza, interagisci con la pagina almeno ogni ora.
> Se devi stare lontano dal computer, salva le note prima.

### 16.13 I Filtri Sembrano Non Funzionare

**Possibili cause:**
1. Combinazione di filtri troppo restrittiva
2. Filtro nascosto ancora attivo
3. Quick Filter + Filtro avanzato in conflitto

**Soluzione:**

**Passo 1.** Clicca "Tutti" nei Quick Filters.

**Passo 2.** Apri i Filtri Avanzati e resetta ogni dropdown a "Tutti".

**Passo 3.** Deseleziona il chip colore (se selezionato).

**Passo 4.** Ricarica la pagina (F5).

### 16.14 Il Quiz dello Studente Non Appare nel Report

**Possibili cause:**
1. Il quiz non ha competenze assegnate
2. Il quiz non e del settore dello studente
3. Il quiz non e stato completato (stato "in corso")

**Soluzione:**

**Passo 1.** Nel Report, tab FTM "📅 Ultimi 7gg": verifica lo stato del quiz.

**Passo 2.** Se lo stato e "In corso" (blu) o "Abbandonato" (rosso), il quiz non e completato.

**Passo 3.** Se completato ma non appare nel radar, potrebbe mancare l'assegnazione competenze. Contatta il supporto tecnico.

### 16.15 Contatti Supporto

| Tipo di problema | Chi contattare |
|-----------------|----------------|
| **Studente non assegnato, permessi, corsi** | Segreteria FTM |
| **Bug, errori pagina, funzionalita mancanti** | Supporto tecnico IT |
| **Problemi Moodle generali (login, password, email)** | Amministratore Moodle |
| **Domande sull'uso degli strumenti** | Coordinatore coach |

---

## Appendice A: Glossario Completo

| Termine | Definizione |
|---------|-------------|
| **Area** | Raggruppamento di competenze per ambito (es. A - Accoglienza, B - Motore, C - Lubrificazione, etc.) |
| **Atelier** | Sessione pratica specializzata (dalla settimana 3+), spesso obbligatoria |
| **Autovalutazione** | Processo in cui lo studente valuta se stesso sulla scala Bloom |
| **Badge** | Etichetta visiva con colore e testo (es. "FINE 6 SETT.", "✅ Completato") |
| **Bilancio Competenze** | Report di sintesi per colloqui (reports_v2.php) |
| **Bloom (Scala)** | Tassonomia cognitiva a 6 livelli: Ricordare, Comprendere, Applicare, Analizzare, Valutare, Creare |
| **Card** | Scheda visiva nella Dashboard che rappresenta uno studente |
| **Coach** | Formatore responsabile del percorso formativo dello studente |
| **Competenza** | Capacita specifica valutata nel sistema (es. MECCANICA_MIS_01 - Utilizzo del calibro) |
| **Dashboard** | Pagina principale di gestione studenti (coach_dashboard_v2.php) |
| **Debounce** | Tecnica di salvataggio automatico: attende 500ms dopo l'ultima modifica prima di salvare |
| **Framework** | Struttura organizzativa delle competenze (FTM-01 per settori, FTM_GEN per generiche) |
| **Gap** | Differenza tra autovalutazione e performance quiz (Gap = Auto - Quiz) |
| **Gap Analysis** | Analisi sistematica delle discrepanze tra fonti dati |
| **Gruppo Colore** | Coorte di studenti identificata da un colore (Giallo, Grigio, Rosso, Marrone, Viola) |
| **KW** | Kalenderwoche - numero della settimana nel calendario |
| **LabEval** | Valutazione di laboratorio pratico |
| **Modal** | Finestra sovrapposta che richiede un'azione (es. iscrizione atelier, stampa) |
| **N/O** | Non Osservato - livello 0 della scala Bloom, usato quando non hai potuto valutare |
| **Overlay** | Sovrapposizione di piu grafici radar sullo stesso canvas |
| **Quick Actions** | Pulsanti di azione rapida nelle card studente della Dashboard |
| **Quick Filters** | Pulsanti di filtro rapido sotto le statistiche della Dashboard |
| **Radar** | Grafico circolare (spider chart) che visualizza le competenze per area |
| **Report Studente** | Pagina di analisi dettagliata per singolo studente (student_report.php) |
| **Scheduler** | Calendario e gestore attivita (ftm_scheduler/index.php) |
| **Sesskey** | Token di sicurezza Moodle incluso in ogni richiesta |
| **Settore** | Area professionale: MECCANICA, AUTOMOBILE, AUTOMAZIONE, CHIMFARM, ELETTRICITA, LOGISTICA, METALCOSTRUZIONE |
| **Soglia** | Valore percentuale per classificare i gap (Allineamento, Monitorare, Critico) |
| **Sovrastima** | Quando lo studente si valuta meglio dei risultati quiz (gap positivo) |
| **Sottovalutazione** | Quando lo studente si valuta peggio dei risultati quiz (gap negativo) |
| **Timeline** | Visualizzazione del percorso 6 settimane con indicatori di stato |
| **Presenze** | Registro presenze giornaliero per attivita (attendance.php) |
| **Capability** | Permesso Moodle che determina le funzionalita accessibili al tuo ruolo |
| **CSV** | Formato file tabellare esportabile, apribile con Excel o LibreOffice |
| **AJAX** | Tecnica di comunicazione client-server che aggiorna la pagina senza ricaricamento completo |
| **Toast** | Messaggio di feedback temporaneo (es. "Salvando...", "✓ Salvato!") |
| **Toggle** | Interruttore on/off per attivare/disattivare funzionalita |
| **Vista** | Modalita di visualizzazione della Dashboard (Classica, Compatta, Standard, Dettagliata) |
| **Zoom** | Scala di ingrandimento dell'interfaccia (90%, 100%, 120%, 140%) |
| **Aula Teoria** | Aula destinata a lezioni frontali, quiz, test (icona 📖) |
| **Laboratorio** | Aula attrezzata per attivita pratiche (icona 🔬) |
| **Postazione** | Posto di lavoro in un'aula, determina la capienza massima |
| **Prenotazione Esterna** | Occupazione aula per progetti non FTM (BIT URAR, BIT AI, etc.) |
| **Fascia Oraria** | Slot temporale: Mattina (08:30-11:45), Pomeriggio (13:15-16:30), Giornata Intera |
| **Review** | Pagina Moodle che mostra le risposte di uno studente a un quiz specifico |
| **Export Word** | Documento Word generato automaticamente con il report completo dello studente |
| **Occupazione** | Percentuale di slot occupati su slot totali di un'aula nella settimana |
| **Conflitto** | Sovrapposizione di attivita (stessa aula o stesso docente allo stesso orario) |

---

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

## Appendice C: Checklist Settimanale Coach

### Lunedi - Inizio Settimana

```
□ Aprire la Coach Dashboard
□ Verificare nuovi studenti assegnati (Quick Filter "Tutti")
□ Controllare studenti "Fine 6 Sett." - azioni urgenti
□ Verificare studenti "Sotto Soglia 50%" - pianificare interventi
□ Inviare autovalutazione a chi non l'ha completata (Quick Filter "Manca Autoval")
□ Assegnare scelte settimanali (Quick Filter "Mancano Scelte")
```

### Martedi-Giovedi - Operativita

```
□ Aggiornare le valutazioni formatore in corso
□ Prendere note dopo ogni colloquio/osservazione
□ Verificare i quiz completati (tab FTM "Ultimi 7gg" nel Report)
□ Controllare iscrizioni Atelier per studenti settimana 3+
□ Aggiornare le note coach con osservazioni giornaliere
```

### Venerdi - Chiusura Settimana

```
□ Rivedere studenti in settimana 5-6 - preparare report
□ Completare e firmare valutazioni pronte
□ Generare export Word per studenti in uscita
□ Verificare avanzamento di tutti gli studenti (Vista Compatta)
□ Preparare materiale per riunione team (Report Classe + Export)
□ Aggiornare note con riepilogo settimanale
```

### Mensile

```
□ Confrontare studenti per bilancio classe (Bilancio -> Confronta)
□ Rivedere soglie Gap Analysis se necessario
□ Verificare studenti con percorso prolungato (oltre 6 settimane)
□ Generare report per segreteria
```

---

## Appendice D: Mappa Navigazione Completa

### URL Principali

| Pagina | URL |
|--------|-----|
| **FTM Tools Hub** | `/local/ftm_hub/index.php` |
| **Coach Dashboard V2** | `/local/coachmanager/coach_dashboard_v2.php` |
| **Coach Dashboard (classica)** | `/local/coachmanager/coach_dashboard.php` |
| **Report Studente** | `/local/competencymanager/student_report.php?userid=X&courseid=Y` |
| **Valutazione Formatore** | `/local/competencymanager/coach_evaluation.php?studentid=X&sector=Y` |
| **Bilancio Competenze** | `/local/coachmanager/reports_v2.php?studentid=X` |
| **Report Classe** | `/local/coachmanager/reports_class.php` |
| **Self-Assessment Dashboard** | `/local/selfassessment/index.php` |
| **FTM Scheduler** | `/local/ftm_scheduler/index.php` |
| **Export Word** | `/local/coachmanager/export_word.php?studentid=X` |

### Flusso di Navigazione

```
FTM Tools Hub
├── Coach Dashboard V2
│   ├── [📊 Report] → Report Studente
│   │   ├── Tab Panoramica (radar, card aree)
│   │   ├── Tab Piano (azioni)
│   │   ├── Tab Quiz (confronto)
│   │   ├── Tab Autovalutazione → selfassessment/student_report.php
│   │   ├── Tab Laboratorio → labeval/reports.php
│   │   ├── Tab Progressi (grafico)
│   │   ├── Tab Dettagli (tabella + eval inline)
│   │   └── Pannello FTM
│   │       ├── Settori
│   │       ├── Ultimi 7gg
│   │       ├── Configurazione
│   │       ├── Progresso
│   │       ├── Gap Analysis
│   │       └── Spunti Colloquio
│   ├── [👤 Valutazione] → Valutazione Formatore
│   │   └── [← Torna al Report] → Report Studente
│   ├── [💬 Colloquio] → Bilancio Competenze
│   │   ├── Tab Panoramica
│   │   ├── Tab Radar Confronto
│   │   ├── Tab Mappa Competenze
│   │   ├── Tab Confronta Studenti
│   │   ├── Tab Colloquio (note coach)
│   │   └── Tab Matching Lavoro
│   ├── [📄 Word] → Download export_word.php
│   ├── [📨 Sollecita] → AJAX reminder
│   └── [Rapporto Classe] → reports_class.php
├── Self-Assessment Dashboard
│   ├── [👁️ Vedi] → reports_v2.php
│   ├── [🔕 Disabilita] → AJAX toggle
│   └── [📧 Reminder] → AJAX reminder
└── FTM Scheduler
    ├── Tab Calendario (Settimana/Mese)
    ├── Tab Gruppi
    ├── Tab Attivita
    ├── Tab Aule
    ├── Tab Atelier
    ├── Tab Presenze (condizionale)
    └── Tab Segreteria (admin)
```

### Parametri URL Principali

Quando navighi tra le pagine, l'URL contiene parametri che determinano cosa visualizzare. Ecco i principali:

**student_report.php:**

| Parametro | Descrizione | Esempio |
|-----------|-------------|---------|
| `userid` | ID utente Moodle dello studente | `userid=42` |
| `courseid` | ID del corso Moodle | `courseid=5` |
| `tab` | Tab da visualizzare | `tab=overview`, `tab=quiz`, `tab=details` |
| `sort` | Ordinamento tabella dettagli | `sort=perc_desc`, `sort=area` |
| `filter` | Filtro livello | `filter=critical`, `filter=excellent` |
| `filter_area` | Filtro per area specifica | `filter_area=B` |
| `quizids[]` | Quiz specifici da includere | `quizids[]=10&quizids[]=15` |

**coach_evaluation.php:**

| Parametro | Descrizione | Esempio |
|-----------|-------------|---------|
| `studentid` | ID studente | `studentid=42` |
| `sector` | Settore per la valutazione | `sector=MECCANICA` |

**reports_v2.php:**

| Parametro | Descrizione | Esempio |
|-----------|-------------|---------|
| `studentid` | ID studente | `studentid=42` |
| `sector` | Filtro settore (opzionale) | `sector=AUTOMOBILE` |

**attendance.php:**

| Parametro | Descrizione | Esempio |
|-----------|-------------|---------|
| `date` | Data per le presenze | `date=2026-02-15` |
| `activityid` | ID attivita selezionata | `activityid=8` |

**ftm_scheduler/index.php:**

| Parametro | Descrizione | Esempio |
|-----------|-------------|---------|
| `tab` | Tab da visualizzare | `tab=calendario`, `tab=gruppi`, `tab=aule` |
| `week` | Settimana calendario (KW) | `week=7` |
| `year` | Anno | `year=2026` |
| `view` | Tipo vista | `view=week`, `view=month` |

### Scorciatoie da Tastiera

| Scorciatoia | Azione |
|-------------|--------|
| **Ctrl+P** | Stampa report (o apri dialogo stampa) |
| **Ctrl+F** | Cerca testo nella pagina |
| **Ctrl+F5** | Ricarica forzata (cancella cache pagina) |
| **Tab** | Naviga tra campi del form |
| **Enter** | Conferma selezione |
| **Esc** | Chiudi modal aperto |

### Endpoint AJAX Principali

Il sistema usa chiamate AJAX per operazioni di salvataggio senza ricaricamento della pagina:

| Endpoint | Funzione | Chiamato da |
|----------|----------|-------------|
| `ajax_save_notes.php` | Salva note coach per studente | Dashboard, Bilancio |
| `ajax_save_choices.php` | Salva scelte settimanali | Dashboard |
| `ajax_save_evaluation.php` | Salva valutazione Bloom inline | Report Studente |
| `ajax_compare_students.php` | Carica dati confronto studenti | Bilancio tab Confronta |
| `ajax_toggle_selfassessment.php` | Abilita/Disabilita autovalutazione | Self-Assessment Dashboard |
| `ajax_send_reminder.php` | Invia promemoria autovalutazione | Self-Assessment Dashboard, Dashboard |

> **Nota tecnica:** Ogni chiamata AJAX include il parametro `sesskey` per la
> sicurezza. Se la sessione e scaduta, la chiamata fallira silenziosamente.
> In quel caso ricarica la pagina per rinnovare la sessione.

---

## 15. Passaporto Tecnico e Garage FTM

### 15.1 Cos'e il Passaporto Tecnico

Il Passaporto Tecnico e un documento stampabile che riassume le competenze dello studente in un formato professionale. Contiene fino a 12 sezioni configurabili: radar panoramico, tabella competenze, commenti del coach, overlay multi-fonte e altro.

**Come accedere:** Dal Report Studente, clicca il bottone **"Passaporto Tecnico"** nella barra superiore.

### 15.2 Cos'e il Garage FTM

Il Garage e lo strumento dove costruisci il passaporto per ogni studente. Puoi:

- **Selezionare le competenze** da includere (checkbox per area e singola competenza)
- **Scegliere il formato** di visualizzazione: percentuali numeriche o scala qualitativa (Eccellente/Buono/Sufficiente)
- **Ordinare le sezioni** con drag & drop per decidere l'ordine di stampa
- **Impostare la soglia** personalizzata per lo studente (override della soglia globale)
- **Attivare/disattivare** overlay 3 fonti, autovalutazione, valutazione coach

**Come accedere:** Dal Report Studente, clicca il bottone **"Garage FTM"**.

### 15.3 Commenti Coach per Area

Nel Passaporto Tecnico puoi aggiungere un commento testuale per ogni area di competenza. I commenti vengono salvati automaticamente e stampati nel passaporto.

---

## 16. Documenti Studenti - Ricerca e Caricamento

### 16.1 Panoramica

La funzione **Documenti Studenti** permette di accedere ai file personali degli studenti direttamente dalla piattaforma, senza dover entrare nei loro account.

**Come accedere:**
- Dalla **Coach Dashboard**: bottone azzurro con icona cartella (&#128194;) su ogni card studente
- Dalla **Dashboard CPURC**: bottone "Documenti Studenti" nella barra in alto, oppure icona cartella accanto al nome dello studente

### 16.2 Ricerca e Download File

1. Apri la pagina **Documenti Studenti**
2. Usa i filtri:
   - **Cerca studente**: per nome, cognome o email
   - **Cerca file**: per nome file (es. "CVBD", "lettera", "CV")
   - **Gruppo colore**: filtra per gruppo
3. Clicca sul nome di uno studente per espandere e vedere i suoi file
4. Per ogni file vedi: nome, cartella, dimensione, data di modifica
5. Clicca la freccia per **scaricare** un singolo file

### 16.3 Download ZIP (Scarica Multipli)

1. Seleziona gli studenti con le **checkbox** a sinistra del nome
2. Opzionalmente filtra per tipo file (es. "CVBD" nel campo ricerca)
3. Clicca **"Scarica ZIP Selezionati"**
4. Ricevi un archivio ZIP con i file organizzati per cartella studente (Cognome_Nome/)

### 16.4 Caricamento File negli Studenti

Puoi caricare documenti direttamente nei file privati degli studenti:

1. Seleziona gli studenti destinatari con le checkbox
2. Clicca **"Carica File negli Studenti"** (bottone azzurro in alto)
3. Trascina il file nel riquadro oppure clicca per selezionarlo
4. Scegli la **cartella di destinazione** dal menu a tendina (Documenti personali, Documenti di scambio, Documenti iniziali, oppure crea una nuova cartella)
5. Clicca **"Carica nei Selezionati"**
6. Il sistema conferma il caricamento per ogni studente

Lo studente trovera il file nei propri **File Privati** alla prossima apertura della piattaforma.

> **Esempio pratico:** Per raccogliere tutti i CVBD del gruppo grigio: filtra per gruppo grigio, scrivi "CVBD" nel campo ricerca file, seleziona tutti, clicca "Scarica ZIP". In pochi secondi hai tutti i CVBD in un unico archivio.

---

## 17. Accedi come Studente

### 17.1 Cos'e

La funzione "Accedi come Studente" permette di entrare nella piattaforma con l'identita dello studente, per vedere esattamente cosa vede lui: dashboard, file privati, corsi, quiz.

### 17.2 Come usarla

Il bottone si trova in tre posizioni:
- **Coach Dashboard**: icona persona blu (&#128100;) nella card studente
- **Dashboard CPURC**: icona persona blu accanto al nome
- **Student Card CPURC**: bottone "Accedi come Studente"

### 17.3 Come tornare al proprio account

Dopo aver effettuato l'accesso come studente, appare un **banner rosso fisso** in cima alla pagina con il messaggio "Ritorna al tuo account". Cliccalo per tornare al tuo profilo originale.

> **Attenzione:** Mentre sei loggato come studente, tutte le azioni che compi (modifiche file, invio quiz) vengono registrate come se fossero dello studente. Usalo solo per verifiche e configurazione.

---

## 18. Coaching Individualizzato (CI)

### 18.1 Panoramica

Il Coaching Individualizzato (precedentemente chiamato SIP) e un percorso di 10 settimane post-rilevamento per partecipanti con potenziale di collocamento.

### 18.2 Attivazione

1. Dalla **Coach Dashboard**, trova lo studente
2. Clicca il bottone **"+ CI"** (colore teal)
3. Compila la modale di attivazione:
   - **Indennita giornaliere LADI** (campo obbligatorio)
   - Settore
   - Note iniziali
4. Conferma l'attivazione

### 18.3 Cosa include

- **Griglia Valutazione PCI**: 6 criteri numerici 1-5
- **Piano d'Azione**: 7 aree di attivazione con scala 0-6 e radar overlay
- **Diario Coaching**: timeline incontri con azioni e scadenze
- **Calendario Appuntamenti**: CRUD con notifiche automatiche
- **KPI**: candidature inviate, contatti aziende, opportunita generate
- **Report Word**: esportabile in 9 sezioni
- **Area Studente**: lo studente puo inserire KPI autonomamente

### 18.4 Badge nella Dashboard

Gli studenti con CI attivo mostrano un **badge teal "CI"** nella card della Coach Dashboard. Puoi filtrare per vedere solo gli studenti con CI attivo.

---

## 19. JobAIDA - Generatore Lettere di Candidatura

### 19.1 Panoramica

JobAIDA e uno strumento basato su intelligenza artificiale che aiuta gli studenti a scrivere lettere di candidatura usando il modello AIDA (Attention, Interest, Desire, Action).

**Come accedere:** Menu navigazione → JobAIDA, oppure `/local/jobaida/index.php`

> **Nota:** Gli studenti devono essere autorizzati dal coach per utilizzare JobAIDA. L'autorizzazione si gestisce dalla pagina "Gestione Autorizzazioni" di JobAIDA.

### 19.2 Tre Modalita

| Modalita | Tab | Descrizione |
|----------|-----|-------------|
| **Express Writers** | Giallo | Genera l'intera lettera in un colpo solo |
| **Coaching Writers** | Arancione | Analizza la compatibilita CV/annuncio, pone domande, poi genera |
| **Learn & Write** | Rosso (default) | Step-by-step: una sezione alla volta con conferma dello studente |

### 19.3 Learn & Write (Modalita Formativa)

Questa e la modalita principale, pensata per formare lo studente:

1. Lo studente inserisce **annuncio di lavoro** e **CV** (testo o upload PDF/Word)
2. Clicca **"Inizia Step-by-Step"**
3. Per ogni sezione AIDA il sistema:
   - Propone un testo
   - Spiega il **rationale** (perche ha fatto quelle scelte)
   - Pone una **domanda di riflessione** allo studente
   - Mostra **suggerimenti formativi**
4. Lo studente puo:
   - **Confermare** e passare alla sezione successiva
   - **Chiedere una modifica** scrivendo cosa vuole cambiare
5. Dopo tutte e 4 le sezioni, il sistema assembla la **lettera completa** con intestazione svizzera

### 19.4 Formato Lettera Svizzera

La lettera generata segue il formato ufficiale:
```
Nome Cognome
Indirizzo, CAP Citta
Telefono
Email

Citta, data

Spettabile [Azienda],
[corpo lettera AIDA]
Cordiali saluti.
```

### 19.5 Esportazione

Da tutte le modalita puoi:
- **Copiare** la lettera negli appunti
- **Esportare in Word** (.doc)
- **Salvare nello storico** per consultarla in seguito

---

## 20. Tabella Comparativa - Valori Editabili

### 20.1 Cos'e

La Tabella Comparativa Dettagliata nel Report Studente mostra i valori di Rilevamento, Autovalutazione e Coach per ogni area. I valori sono **editabili**: puoi cliccare su qualsiasi cella per modificare il valore percentuale.

### 20.2 Come modificare i valori

1. Apri il **Report Studente** con i quiz selezionati
2. Scorri fino alla sezione **"Tabella Comparativa Dettagliata"**
3. Clicca su un **badge percentuale** (Rilevamento, Auto o Coach)
4. Si apre un campo di input: inserisci il nuovo valore (0-100)
5. Clicca **"Salva"** o premi Invio
6. Il valore viene salvato e la media/gap si ricalcolano automaticamente

I valori modificati mostrano un'icona matita per distinguerli dai valori calcolati.

### 20.3 Persistenza

- I valori modificati vengono salvati nel database e persistono tra le sessioni
- La **configurazione quiz** (quali quiz sono selezionati, opzioni di visualizzazione) viene ricordata automaticamente: riaprendo la pagina dello stesso studente, i quiz selezionati vengono ricaricati senza doverli riselezionare

---

## 21. Rapporto Finale CPURC

### 21.1 Accesso al Rapporto Finale

Per redigere il Rapporto Finale bisogna accedere alla piattaforma FTM Academy.

**Dalla Coach Dashboard V2:**
1. Accedere alla piattaforma FTM Academy
2. Dal menu principale, aprire la Coach Dashboard V2
3. Individuare lo studente nella lista
4. Nella visualizzazione compatta: cliccare sul bottone **"Rapporto"**
5. Nella visualizzazione Standard: cliccare su **"Rapporto CPURC"**

**Accesso diretto:** Se si conosce l'ID dello studente, accedere a: `/local/ftm_cpurc/report.php?userid=XX`

### 21.2 La Barra degli Strumenti

In alto nella pagina del rapporto si trova una barra scura con quattro bottoni:

| Bottone | Funzione |
|---------|----------|
| **Stampa** (grigio) | Stampa la pagina dal browser |
| **Salva** (blu) | Salva tutti i dati nel database |
| **Esporta Word** (verde) | Genera e scarica il documento Word ufficiale |
| **Carica dati Aladino** (arancione) | Importa dati da file Excel Aladino |

### 21.3 Carica Dati Aladino

Prima di compilare il rapporto, si consiglia di caricare i dati dal sistema Aladino. Questo aggiorna automaticamente assenze, date, colloqui e dati URC.

**Come esportare il file da Aladino:**
1. Accedere al sistema Aladino
2. Cercare lo studente o il gruppo di studenti e selezionarlo
3. Esportare i dati in formato Excel (.xlsx)
4. Salvare il file sul proprio computer

**Come caricare il file nella piattaforma FTM Academy:**
1. Cliccare il bottone arancione **"Carica dati Aladino"**
2. Si apre una finestra modale
3. Trascinare il file Excel nell'area tratteggiata, oppure cliccare per selezionarlo
4. Attendere il caricamento: il sistema cerca automaticamente lo studente nel file

**Anteprima dei dati:** Dopo il caricamento, viene mostrata un'anteprima organizzata in sezioni:
- **Assenze e presenze**: giorni X, giorni O, assenze A-I, totale, giorni partecipazione effettiva
- **Percorso**: date inizio/fine, grado occupazione, stato, ultima professione
- **Colloqui e stage**: numero colloqui, data e azienda del colloquio, stage svolti
- **URC**: ufficio URC, consulente, numero personale

> **Nota:** I giorni di partecipazione effettiva = X + O (incluse mezze giornate, es. 6.5 + 15.5 = 22).

**Confermare l'importazione:**
1. Verificare che i dati nell'anteprima siano corretti
2. Cliccare il bottone arancione **"Importa dati"**
3. Attendere il messaggio: "Importazione completata!"
4. La pagina si ricarica con i dati aggiornati

**Note importanti sull'import Aladino:**
- **Record multipli**: il sistema seleziona il record Aperto, poi Chiuso, poi Interrotto
- **Mezze giornate**: il sistema supporta valori decimali (es. 6.5 giorni)
- **Data fine effettiva**: se non presente, viene usata la data di fine prevista
- **Colloqui**: azienda e data vengono importati nella Sezione 5.1

### 21.4 Compilare il Rapporto

Il rapporto e diviso in sezioni che rispecchiano il documento ufficiale. Le sezioni grigie sono automatiche, quelle con campi bianchi vanno compilate manualmente.

**Consenso CI (Coaching Individualizzato):**
"La PCI ha svolto il sostegno al collocamento individuale personalizzato nel settore industriale"
Selezionare Si o No. La selezione viene riportata nel Word.

**Sezione 1 - Situazione iniziale:**
- **Situazione iniziale**: sintesi situazione iniziale, obiettivi, carriera professionale e formativa
- **Settori**: settore/i di riferimento (es. Meccanica, Automazione, Logistica)

**Sezione 2 - Situazione al termine della misura:**
- **Tabella reinserimento**: cliccare su una delle tre opzioni:
  - Reinserimento a breve termine
  - Reinserimento a medio termine
  - Non fanno presupporre un reinserimento
- Compilare: Valutazione competenze settore, Possibili settori, Sintesi conclusiva

**Sezione 3 - Verifica delle competenze:**
- **3.1 Personali** (5): Impegno, Iniziativa, Autonomia, Puntualita, Modo di presentarsi
- **3.2 Sociali** (2): Comunicazione, Comprensione
- **3.3 Metodologiche** (5): Ritmo lavoro, Apprendimento, Problemi, Organizzazione, Cura
- **3.4 TIC** (1): Conoscenze PC e email

**Scala**: Molto buone - Buone - Sufficienti - Insufficienti - N.V.

Cliccare sulla cella per selezionare. Sotto ogni tabella si trova un campo **Osservazioni**.

**Sezione 4 - Ricerca impiego:**
- **4.1 Dossier completo**: Si/No
- **Competenze ricerca**: 5 competenze, stessa scala della Sezione 3
- **4.2 Canali**: checkbox multipli (10 canali disponibili)
- **4.3 Valutazione complessiva**: valutazione globale + osservazioni

**Sezione 5 - Riepilogo colloqui:**
Precompilato da Aladino (numero colloqui, azienda, data). Aggiungere osservazioni.

**Sezione 6 - Esito ricerca impiego:**
Selezionare Si/No per l'assunzione. Se Si, compilare:
- **Azienda**: nome dell'azienda
- **Professione; a partire dal**: professione e data inizio
- **Forma contrattuale**: tipo di contratto

### 21.5 Salvataggio

**Salvataggio manuale:** Cliccare il bottone blu **"Salva"**. Appare messaggio verde: "Report salvato con successo".

**Salvataggio automatico (Autosave):** Il sistema salva automaticamente ogni volta che modifichi un campo, dopo 3 secondi di inattivita. Un indicatore accanto al bottone Salva mostra:
- **"Salvato alle HH:MM"** (verde) - salvataggio completato
- **"Salvataggio automatico..."** (arancione) - salvataggio in corso

> **Importante:** Salvare PRIMA di esportare il Word.

### 21.6 Esportare il Documento Word

1. Assicurarsi di aver salvato (bottone blu o attendere l'autosave)
2. Cliccare il bottone verde **"Esporta Word"**
3. Il file si scarica: `Rapporto_finale_Cognome_Nome_Data.docx`

Il documento contiene tutti i dati, le valutazioni con "X", i colloqui, le firme. Pronto per stampa e firma.

### 21.7 Flusso di Lavoro Consigliato

1. Aprire il rapporto dalla Coach Dashboard
2. Caricare i dati Aladino (bottone arancione)
3. Compilare Sezione 1 (situazione iniziale)
4. Compilare Sezione 2 (reinserimento, competenze settore)
5. Compilare Sezione 3 (griglie competenze + osservazioni)
6. Compilare Sezione 4 (dossier, ricerca, canali)
7. Verificare Sezione 5 (colloqui - da Aladino)
8. Compilare Sezione 6 (esito assunzione)
9. Selezionare consenso CI
10. Aggiungere allegati
11. Salvare (blu) o attendere autosave
12. Esportare Word (verde)
13. Stampare, firmare, inviare al consulente URC

### 21.8 Domande Frequenti

**D: Posso modificare un rapporto gia salvato?**
R: Si. Riaprire, modificare e salvare nuovamente.

**D: Posso esportare il Word piu volte?**
R: Si. Ogni esportazione genera un nuovo documento aggiornato.

**D: I dati Aladino sovrascrivono i miei testi?**
R: No. Aladino aggiorna solo assenze, date e colloqui. I testi narrativi restano invariati.

**D: Perche il coach mostrato non sono io?**
R: Il rapporto mostra il coach assegnato allo studente. Per cambiarlo, modificare nella Student Card.

**D: Le mezze giornate vengono gestite?**
R: Si. Valori come 6.5 giorni sono supportati.

**D: Come trova lo studente nel file Excel?**
R: Cerca per email (priorita) o per nome e cognome.

**D: Due righe per lo stesso studente?**
R: Seleziona automaticamente: Aperto > Chiuso > Interrotto, poi la data piu recente.

**D: Dove trovo il file Excel?**
R: Si esporta dal sistema Aladino cercando lo studente.

**D: I dati si salvano da soli?**
R: Si, dal Aprile 2026 il sistema ha l'autosave. Dopo 3 secondi di inattivita i dati vengono salvati automaticamente.

---

**Fine del Manuale Coach/Formatore**

*Documento generato per FTM Academy - Versione 3.0 - Aprile 2026*
