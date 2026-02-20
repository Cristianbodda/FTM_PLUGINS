# Modulo 1: Introduzione e Accesso al Sistema

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: Cos'e FTM Academy, i 5 strumenti del coach, le 4 fonti dati, il flusso di lavoro, e come accedere al sistema.

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

