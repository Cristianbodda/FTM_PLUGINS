# Manuale Segreteria - Import ed Export

**Versione:** 1.0 | **Data:** 24 Gennaio 2026

---

## Indice

1. [Import CSV da CPURC](#1-import-csv-da-cpurc)
2. [Formato del File CSV](#2-formato-del-file-csv)
3. [Procedura di Import](#3-procedura-di-import)
4. [Gestione Errori Import](#4-gestione-errori-import)
5. [Export Excel](#5-export-excel)
6. [Export Word Singolo](#6-export-word-singolo)
7. [Export Word Massivo (ZIP)](#7-export-word-massivo-zip)

---

## 1. Import CSV da CPURC

L'import CSV permette di caricare i dati degli studenti dal sistema CPURC.

### Quando Usarlo

- Nuovi studenti da inserire nel sistema
- Aggiornamento dati esistenti
- Import iniziale di un gruppo

### Accesso

1. Dashboard CPURC → **Import CSV** oppure
2. URL diretto: `/local/ftm_cpurc/import.php`

![Screenshot: Accesso Import](../screenshots/accesso_import.png)

---

## 2. Formato del File CSV

Il file CSV deve seguire un formato specifico.

### Requisiti Tecnici

| Requisito | Valore |
|-----------|--------|
| Formato | CSV (virgola o punto e virgola) |
| Codifica | UTF-8 (preferibile) |
| Prima riga | Intestazioni colonne |

### Colonne Obbligatorie

| Colonna | Descrizione | Esempio |
|---------|-------------|---------|
| `email` | Email utente (univoca) | mario.rossi@email.com |
| `firstname` | Nome | Mario |
| `lastname` | Cognome | Rossi |

### Colonne Opzionali

| Colonna | Descrizione | Esempio |
|---------|-------------|---------|
| `personal_number` | Numero personale URC | 123456 |
| `urc_office` | Ufficio URC | Lugano |
| `urc_consultant` | Consulente URC | Anna Verdi |
| `phone` | Telefono | 091 123 4567 |
| `mobile` | Cellulare | 079 123 4567 |
| `birthdate` | Data nascita | 1985-03-15 |
| `gender` | Genere | M / F |
| `nationality` | Nazionalità | Italiana |
| `address_street` | Via | Via Roma 15 |
| `address_cap` | CAP | 6900 |
| `address_city` | Città | Lugano |
| `date_start` | Data inizio | 2026-01-15 |
| `date_end_planned` | Data fine prevista | 2026-02-26 |
| `measure` | Tipo misura | CPURC |
| `last_profession` | Ultima professione | Meccanico |
| `avs_number` | Numero AVS | 756.1234.5678.90 |

### Esempio File CSV

```csv
email,firstname,lastname,personal_number,urc_office,date_start,last_profession
mario.rossi@email.com,Mario,Rossi,123456,Lugano,2026-01-15,Meccanico
lucia.bianchi@email.com,Lucia,Bianchi,123457,Bellinzona,2026-01-20,Logistica
paolo.verdi@email.com,Paolo,Verdi,123458,Lugano,2026-01-22,Elettricista
```

### Note sul Formato Date

Le date possono essere in questi formati:
- `YYYY-MM-DD` (preferito): 2026-01-15
- `DD/MM/YYYY`: 15/01/2026
- `DD.MM.YYYY`: 15.01.2026

---

## 3. Procedura di Import

### Passo 1: Prepara il File

1. Esporta i dati da CPURC in formato CSV
2. Verifica che contenga le colonne obbligatorie
3. Salva il file con codifica UTF-8

### Passo 2: Carica il File

1. Vai alla pagina **Import CSV**
2. Clicca **Scegli file** o trascina il file
3. Seleziona il file CSV preparato

![Screenshot: Carica File](../screenshots/carica_file.png)

### Passo 3: Anteprima

Prima dell'import vedi un'anteprima:

```
┌─────────────────────────────────────────────────────────┐
│  ANTEPRIMA IMPORT                                       │
├─────────────────────────────────────────────────────────┤
│  File: studenti_gennaio.csv                             │
│  Righe totali: 15                                       │
│  Nuovi utenti: 12                                       │
│  Utenti esistenti (aggiornamento): 3                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  │ Email              │ Nome   │ Cognome │ Stato      │ │
│  │ mario@email.com    │ Mario  │ Rossi   │ ✅ Nuovo   │ │
│  │ lucia@email.com    │ Lucia  │ Bianchi │ 🔄 Agg.   │ │
│  │ paolo@email.com    │ Paolo  │ Verdi   │ ✅ Nuovo   │ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

![Screenshot: Anteprima Import](../screenshots/anteprima_import.png)

### Passo 4: Conferma Import

1. Verifica che i dati siano corretti
2. Clicca **▶️ Avvia Import**
3. Attendi il completamento

### Passo 5: Report Import

Al termine vedi il report:

```
┌─────────────────────────────────────────────────────────┐
│  ✅ IMPORT COMPLETATO                                   │
├─────────────────────────────────────────────────────────┤
│  Totale processati: 15                                  │
│  ✅ Importati con successo: 14                          │
│  ⚠️ Errori: 1                                          │
├─────────────────────────────────────────────────────────┤
│  Dettaglio errori:                                      │
│  - Riga 8: Email non valida "test@"                    │
└─────────────────────────────────────────────────────────┘
```

![Screenshot: Report Import](../screenshots/report_import.png)

---

## 4. Gestione Errori Import

### Errori Comuni

| Errore | Causa | Soluzione |
|--------|-------|-----------|
| "Email non valida" | Email malformata | Correggi l'email nel CSV |
| "Email duplicata" | Email già esistente | Rimuovi duplicato o aggiorna |
| "Campo obbligatorio mancante" | Nome/cognome/email vuoto | Compila i campi mancanti |
| "Data non valida" | Formato data errato | Usa YYYY-MM-DD |
| "Errore codifica" | File non UTF-8 | Risalva come UTF-8 |

### Come Risolvere

1. **Leggi il messaggio di errore** - indica riga e problema
2. **Apri il CSV** con Excel o editor testo
3. **Correggi la riga** indicata
4. **Risalva** il file
5. **Riprova l'import**

### Import Parziale

Se ci sono errori:
- Le righe corrette vengono importate
- Le righe con errori vengono saltate
- Puoi correggere e reimportare solo le righe problematiche

---

## 5. Export Excel

Esporta tutti i dati in un file Excel per analisi o backup.

### Come Esportare

1. Vai alla **Dashboard CPURC**
2. (Opzionale) Applica filtri
3. Clicca **📊 Export Excel**
4. Il download parte automaticamente

![Screenshot: Export Excel](../screenshots/export_excel_button.png)

### Contenuto del File

Il file Excel contiene **30+ colonne**:

**Dati Personali:**
- Nome, Cognome, Email
- Telefono, Cellulare
- Data nascita, Genere, Nazionalità

**Indirizzo:**
- Via, CAP, Città

**Dati URC:**
- Numero personale
- Ufficio URC, Consulente

**Percorso:**
- Data inizio, Data fine
- Misura, Stato
- Settore, Coach

**Assenze:**
- Tutte le tipologie (X, O, A-I)
- Totale assenze

**Stage:**
- Azienda, Contatto
- Date stage

**Report:**
- Stato report
- Data ultima modifica

### Aprire il File

1. **Microsoft Excel:** Doppio click sul file
2. **LibreOffice Calc:** File → Apri
3. **Google Sheets:** Upload su Drive

---

## 6. Export Word Singolo

Esporta il report di **un singolo studente** in Word.

### Dalla Dashboard

1. Trova lo studente nella lista
2. Clicca il pulsante **📄** nella colonna Azioni
3. Il download parte automaticamente

### Dalla Scheda Studente

1. Apri la scheda studente
2. Clicca **📄 Export Word**

### Dalla Pagina Report

1. Vai al report dello studente
2. Clicca **📄 Esporta Word**

![Screenshot: Export Word Singolo](../screenshots/export_word_singolo.png)

### Requisiti

- Il report deve essere stato compilato (almeno bozza)
- Servono i permessi di visualizzazione

---

## 7. Export Word Massivo (ZIP)

Esporta **tutti i report** in un archivio ZIP.

### Accesso

1. Dashboard CPURC → **📄 Export Word ZIP** oppure
2. URL: `/local/ftm_cpurc/export_word_bulk.php`

### Interfaccia

```
┌─────────────────────────────────────────────────────────┐
│  📦 Export Word Massivo                                 │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐            │
│  │    15     │ │     8     │ │    23     │            │
│  │  Report   │ │  Report   │ │  Totale   │            │
│  │ Completi  │ │  Bozza    │ │  Report   │            │
│  └───────────┘ └───────────┘ └───────────┘            │
│                                                         │
│  Quali report esportare?                               │
│  [Tutti (23 report)              ▼]                    │
│                                                         │
│  [📦 Genera ZIP e Scarica]                             │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Opzioni Export

| Opzione | Descrizione |
|---------|-------------|
| **Tutti** | Esporta tutti i report (bozze + completi) |
| **Solo completi** | Esporta solo report finalizzati |
| **Solo bozze** | Esporta solo bozze in corso |

### Procedura

1. Seleziona l'opzione desiderata
2. Clicca **📦 Genera ZIP e Scarica**
3. Attendi la generazione (può richiedere tempo)
4. Il download parte automaticamente

![Screenshot: Export ZIP](../screenshots/export_zip.png)

### Contenuto ZIP

```
Rapporti_CPURC_2026-01-24_151030.zip
├── Rapporto_Rossi_Mario.docx
├── Rapporto_Bianchi_Lucia.docx
├── Rapporto_Verdi_Paolo.docx
├── Rapporto_Neri_Giovanni.docx
└── ... (altri file)
```

### Tempi di Generazione

| Numero Report | Tempo Stimato |
|---------------|---------------|
| 1-10 | < 30 secondi |
| 10-50 | 1-2 minuti |
| 50-100 | 3-5 minuti |
| 100+ | 5+ minuti |

> **Suggerimento:** Per molti report, lancia l'export e attendi. Non chiudere la pagina.

### Errori Possibili

| Errore | Soluzione |
|--------|-----------|
| "Nessun report" | Verifica filtri, compila almeno un report |
| "Timeout" | Riprova, contatta supporto se persiste |
| "Errore generazione" | Verifica log, contatta supporto |

---

## Riepilogo Operazioni

| Operazione | Dove | Come |
|------------|------|------|
| Import CSV | Import CSV | Carica file → Conferma |
| Export Excel | Dashboard | Pulsante Export Excel |
| Export Word singolo | Dashboard/Scheda | Pulsante 📄 |
| Export Word massivo | Export Word Bulk | Seleziona → Genera ZIP |

---

## Prossimo Capitolo

➡️ [03_Gestione_Settori.md](03_Gestione_Settori.md) - Come gestire i settori degli studenti

---

*Manuale Segreteria - FTM v5.0*
