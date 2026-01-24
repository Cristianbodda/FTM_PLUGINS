# Manuale Segreteria - Dashboard CPURC

**Versione:** 1.0 | **Data:** 24 Gennaio 2026

---

## Indice

1. [Cos'è la Dashboard CPURC](#1-cosè-la-dashboard-cpurc)
2. [Accesso alla Dashboard](#2-accesso-alla-dashboard)
3. [Panoramica Interfaccia](#3-panoramica-interfaccia)
4. [Usare i Filtri](#4-usare-i-filtri)
5. [Capire la Tabella](#5-capire-la-tabella)
6. [Assegnare il Coach](#6-assegnare-il-coach)
7. [Azioni sulla Riga](#7-azioni-sulla-riga)
8. [Export dei Dati](#8-export-dei-dati)

---

## 1. Cos'è la Dashboard CPURC

La **Dashboard CPURC** è il pannello di controllo centrale per la segreteria. Permette di:

- Vedere **tutti** gli studenti CPURC in un'unica vista
- **Filtrare** per URC, settore, coach, stato report
- **Assegnare coach** agli studenti
- **Esportare** dati in Excel o Word
- Accedere rapidamente alle **schede studente**

---

## 2. Accesso alla Dashboard

### Passo 1: Accedi a Moodle

1. Apri il browser
2. Vai a: `https://test-urc.hizuvala.myhostpoint.ch`
3. Inserisci le credenziali
4. Clicca **Accedi**

### Passo 2: Vai alla Dashboard CPURC

1. Nel menu laterale, cerca **FTM CPURC** oppure
2. Vai direttamente a: `/local/ftm_cpurc/index.php`

![Screenshot: Accesso Dashboard](../screenshots/accesso_cpurc.png)

> **Suggerimento:** Aggiungi la pagina ai preferiti per accesso rapido!

---

## 3. Panoramica Interfaccia

La dashboard è organizzata così:

```
┌─────────────────────────────────────────────────────────────┐
│  FTM CPURC - Dashboard                                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🔍 Ricerca: [_______________]                              │
│                                                             │
│  Filtri:                                                    │
│  [URC ▼] [Settore ▼] [Stato Report ▼] [Coach ▼]           │
│                                                             │
│  Esporta: [📊 Excel] [📄 Word ZIP]                         │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  │ Nome        │ URC    │ Settore │ Sett │ Coach │ Report │ │
│  ├─────────────┼────────┼─────────┼──────┼───────┼────────┤ │
│  │ Rossi Mario │ Lugano │ MECC    │ S3   │ CB ▼  │ 📝     │ │
│  │ Bianchi L.  │ Bellin │ AUTO    │ S5   │ FM ▼  │ ✅     │ │
│  │ Verdi Paolo │ Lugano │ ELETT   │ S2   │ -- ▼  │ --     │ │
│  │ ...         │ ...    │ ...     │ ...  │ ...   │ ...    │ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Elementi Principali

| Elemento | Funzione |
|----------|----------|
| **Ricerca** | Cerca per nome, cognome o email |
| **Filtri** | Restringi la lista per criteri |
| **Pulsanti Export** | Scarica dati in Excel o Word |
| **Tabella** | Lista studenti con azioni |

---

## 4. Usare i Filtri

### Filtro Ricerca (Testo Libero)

1. Clicca nel campo **🔍 Ricerca**
2. Digita nome, cognome o email
3. La lista si aggiorna mentre scrivi

**Esempi:**
- `rossi` → trova tutti i Rossi
- `mario` → trova tutti i Mario
- `@gmail` → trova tutti con email Gmail

![Screenshot: Filtro Ricerca](../screenshots/filtro_ricerca.png)

### Filtro URC (Ufficio)

Filtra per ufficio URC di provenienza:

1. Clicca sul menu **URC**
2. Seleziona l'ufficio:
   - Tutti
   - Lugano
   - Bellinzona
   - Locarno
   - Mendrisio
   - ...

![Screenshot: Filtro URC](../screenshots/filtro_urc.png)

### Filtro Settore

Filtra per settore professionale:

1. Clicca sul menu **Settore**
2. Seleziona:
   - Tutti
   - AUTOMOBILE
   - MECCANICA
   - LOGISTICA
   - ELETTRICITA
   - AUTOMAZIONE
   - METALCOSTRUZIONE
   - CHIMFARM

![Screenshot: Filtro Settore](../screenshots/filtro_settore.png)

### Filtro Stato Report

Filtra in base allo stato del report:

| Opzione | Mostra |
|---------|--------|
| **Tutti** | Tutti gli studenti |
| **Nessun Report** | Studenti senza report iniziato |
| **Bozza** | Studenti con report in bozza |
| **Completo** | Studenti con report finalizzato |

![Screenshot: Filtro Stato Report](../screenshots/filtro_stato_report.png)

### Filtro Coach

Filtra per coach assegnato:

1. Clicca sul menu **Coach**
2. Seleziona:
   - Tutti
   - Nessun Coach Assegnato
   - CB (Cristian Bodda)
   - FM (Fabio Marinoni)
   - GM (Graziano Margonar)
   - RB (Roberto Bravo)

![Screenshot: Filtro Coach](../screenshots/filtro_coach.png)

### Combinare i Filtri

Puoi usare **più filtri insieme**:

**Esempio:** Trovare tutti gli studenti:
- Dell'URC Lugano
- Nel settore MECCANICA
- Senza report

1. Seleziona **URC: Lugano**
2. Seleziona **Settore: MECCANICA**
3. Seleziona **Stato Report: Nessun Report**

La lista mostra solo gli studenti che corrispondono a TUTTI i criteri.

### Resettare i Filtri

Per tornare a vedere tutti gli studenti:
1. Seleziona **Tutti** in ogni filtro, oppure
2. Clicca su **Reset Filtri** (se disponibile), oppure
3. Ricarica la pagina

---

## 5. Capire la Tabella

### Colonne della Tabella

| Colonna | Descrizione |
|---------|-------------|
| **Nome** | Nome e cognome (link alla scheda) |
| **URC** | Ufficio URC di riferimento |
| **Settore** | Settore professionale (badge colorato) |
| **Settimana** | Settimana nel percorso (S1-S6+) |
| **Coach** | Coach assegnato (dropdown editabile) |
| **Report** | Stato del report |
| **Azioni** | Pulsanti per card, report, export |

### Badge Settore

Ogni settore ha un colore distintivo:

| Settore | Colore |
|---------|--------|
| AUTOMOBILE | 🔵 Blu |
| MECCANICA | 🟢 Verde |
| LOGISTICA | 🟡 Giallo |
| ELETTRICITA | 🔴 Rosso |
| AUTOMAZIONE | 🟣 Viola |
| METALCOSTRUZIONE | ⚫ Grigio |
| CHIMFARM | 🩷 Rosa |

### Badge Settimana

| Badge | Significato |
|-------|-------------|
| 🆕 S1-S2 | Nuovo (verde) |
| ⏳ S3-S4 | In corso (blu) |
| ⚠️ S5-S6 | Fine vicina (giallo) |
| 🔴 S7+ | Prolungamento (rosso) |

### Badge Report

| Icona | Significato |
|-------|-------------|
| -- | Nessun report |
| 📝 | Bozza in corso |
| ✅ | Report completato |
| 📤 | Report inviato |

---

## 6. Assegnare il Coach

Puoi assegnare o cambiare il coach direttamente dalla tabella.

### Come Assegnare

1. Trova lo studente nella lista
2. Clicca sul **dropdown Coach** nella sua riga
3. Seleziona il coach desiderato:
   - -- (nessuno)
   - CB (Cristian Bodda)
   - FM (Fabio Marinoni)
   - GM (Graziano Margonar)
   - RB (Roberto Bravo)
4. Il salvataggio è **automatico**

![Screenshot: Assegna Coach](../screenshots/assegna_coach.png)

### Conferma Salvataggio

Dopo la selezione vedrai:
- ✅ "Coach salvato" (messaggio verde)
- Il dropdown mostra il nuovo coach

### Rimuovere il Coach

1. Clicca sul dropdown
2. Seleziona **-- (Nessuno)**
3. Il coach viene rimosso

> **Nota:** L'assegnazione è sincronizzata con tutti i plugin FTM!

---

## 7. Azioni sulla Riga

Per ogni studente hai 3 pulsanti azione:

### Pulsante Card 📋

Apre la **Scheda Studente** completa con tutti i dati.

1. Clicca su **📋**
2. Si apre la scheda con 4 tab

### Pulsante Report 📝

Apre la pagina di **compilazione report**.

1. Clicca su **📝**
2. Si apre il report (anche se vuoto)

### Pulsante Word 📄

Scarica il **documento Word** del report.

1. Clicca su **📄**
2. Il download parte automaticamente

> **Nota:** Disponibile solo se il report è stato compilato.

![Screenshot: Pulsanti Azione](../screenshots/pulsanti_azione.png)

---

## 8. Export dei Dati

### Export Excel

Scarica un file Excel con **tutti i dati** degli studenti.

**Come fare:**

1. (Opzionale) Applica i filtri desiderati
2. Clicca **📊 Export Excel**
3. Il download parte automaticamente
4. Apri il file con Excel o LibreOffice

**Contenuto del file:**
- Tutti i campi anagrafici
- Dati URC
- Settore e coach
- Stato report
- Assenze
- Dati stage

![Screenshot: Export Excel](../screenshots/export_excel.png)

### Export Word ZIP (Bulk)

Scarica un **archivio ZIP** con tutti i report Word.

**Come fare:**

1. Clicca **📄 Export Word ZIP**
2. Seleziona quali report esportare:
   - Tutti
   - Solo completati
   - Solo bozze
3. Clicca **Genera ZIP**
4. Attendi la generazione (può richiedere tempo)
5. Il download parte automaticamente

**Contenuto del ZIP:**
```
Rapporti_CPURC_2026-01-24.zip
├── Rapporto_Rossi_Mario.docx
├── Rapporto_Bianchi_Lucia.docx
├── Rapporto_Verdi_Paolo.docx
└── ...
```

![Screenshot: Export Word ZIP](../screenshots/export_word_zip.png)

> **Attenzione:** Con molti studenti l'export può richiedere alcuni minuti.

---

## Riepilogo Operazioni

| Operazione | Come Fare |
|------------|-----------|
| Cercare uno studente | Campo ricerca o filtri |
| Vedere studenti di un URC | Filtro URC |
| Vedere studenti senza coach | Filtro Coach → Nessuno |
| Assegnare coach | Dropdown nella riga |
| Aprire scheda studente | Pulsante 📋 |
| Compilare report | Pulsante 📝 |
| Scaricare Word | Pulsante 📄 |
| Scaricare Excel | Pulsante Export Excel |
| Scaricare tutti i Word | Pulsante Export Word ZIP |

---

## Prossimo Capitolo

➡️ [02_Import_Export.md](02_Import_Export.md) - Come importare ed esportare dati

---

*Manuale Segreteria - FTM v5.0*
