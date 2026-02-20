# Manuale Operativo FTM - Segreteria

**Versione:** 1.0
**Data:** Febbraio 2026
**Sistema:** FTM Academy - Gestione Competenze Professionali

---

## Indice

1. [Introduzione](#1-introduzione)
2. [Accesso al Sistema](#2-accesso-al-sistema)
3. [Dashboard CPURC](#3-dashboard-cpurc)
4. [Import Studenti CSV](#4-import-studenti-csv)
5. [Gestione Student Card](#5-gestione-student-card)
6. [Assegnazione Coach](#6-assegnazione-coach)
7. [Gestione Settori](#7-gestione-settori)
8. [Sector Admin](#8-sector-admin)
9. [Generazione Report](#9-generazione-report)
10. [Export Dati](#10-export-dati)
11. [Setup Quiz e Competenze](#11-setup-quiz-e-competenze)
12. [FTM Scheduler](#12-ftm-scheduler)
13. [Test Suite e Diagnostica](#13-test-suite-e-diagnostica)
14. [Gestione Utenti Moodle](#14-gestione-utenti-moodle)
15. [Casi d'Uso Pratici](#15-casi-duso-pratici)
16. [Risoluzione Problemi](#16-risoluzione-problemi)

---

## 1. Introduzione

### 1.1 Cos'è FTM Academy

FTM Academy è un ecosistema completo per la gestione delle competenze professionali degli studenti CPURC. Come operatore di segreteria, sei responsabile della gestione amministrativa dell'intero sistema.

### 1.2 Il Tuo Ruolo nel Sistema

Come segreteria hai accesso completo a:
- **Gestione anagrafica** di tutti gli studenti
- **Import/Export dati** da e verso sistemi esterni
- **Assegnazione coach** e settori agli studenti
- **Monitoraggio globale** di tutti i percorsi
- **Generazione report** per studenti e istituzioni
- **Configurazione sistema** quiz e competenze

### 1.3 Panoramica Moduli

| Modulo | Funzione | Accesso |
|--------|----------|---------|
| CPURC Dashboard | Gestione studenti CPURC | /local/ftm_cpurc/ |
| Sector Admin | Gestione settori studenti | /local/competencymanager/sector_admin.php |
| Setup Universale | Import quiz e competenze | /local/competencyxmlimport/setup_universale.php |
| FTM Scheduler | Pianificazione attività | /local/ftm_scheduler/ |
| Test Suite | Diagnostica sistema | /local/ftm_testsuite/ |

> **SCREENSHOT 1.3:** Mappa visuale dei moduli con collegamenti

---

## 2. Accesso al Sistema

### 2.1 Login alla Piattaforma

1. Apri il browser e vai all'indirizzo FTM Academy
2. Inserisci le tue credenziali segreteria
3. Clicca "Login"

> **SCREENSHOT 2.1:** Pagina di login

### 2.2 Verifica Permessi

Dopo il login, verifica di avere accesso a:
- Menu "FTM CPURC" o "Gestione CPURC"
- Menu "Sector Admin"
- Menu "Setup Universale"

Se manca qualcosa, contatta l'amministratore.

> **SCREENSHOT 2.2:** Menu con voci segreteria evidenziate

### 2.3 Navigazione Rapida

**URL diretti principali:**
```
Dashboard CPURC:    /local/ftm_cpurc/index.php
Student Card:       /local/ftm_cpurc/student_card.php?id=X
Import CSV:         /local/ftm_cpurc/import.php
Sector Admin:       /local/competencymanager/sector_admin.php
Setup Universale:   /local/competencyxmlimport/setup_universale.php
Scheduler:          /local/ftm_scheduler/index.php
Test Suite:         /local/ftm_testsuite/agent_tests.php
```

> **SCREENSHOT 2.3:** Blocco FTM Tools con tutti i link

---

## 3. Dashboard CPURC

### 3.1 Panoramica

La Dashboard CPURC è il centro di controllo per la gestione di tutti gli studenti del Centro Professionale URC.

**Elementi principali:**
- **Barra filtri** - Ricerca e filtri avanzati
- **Statistiche** - Contatori e metriche
- **Tabella studenti** - Lista completa con azioni
- **Pulsanti export** - Export Excel e Word

> **SCREENSHOT 3.1:** Vista completa Dashboard CPURC con aree numerate

### 3.2 Filtri Disponibili

| Filtro | Descrizione | Opzioni |
|--------|-------------|---------|
| **Ricerca** | Testo libero | Nome, cognome, email |
| **URC** | Ufficio URC | Lista uffici |
| **Settore** | Settore professionale | 7 settori + Generico |
| **Stato Report** | Stato del report | Nessuno, Bozza, Completo |
| **Coach** | Coach assegnato | Lista coach |

**Come usare i filtri:**
1. Seleziona i valori desiderati
2. Clicca "Filtra" o premi Invio
3. La tabella si aggiorna
4. Clicca "Reset" per rimuovere i filtri

> **SCREENSHOT 3.2:** Barra filtri con dropdown aperti

### 3.3 Tabella Studenti

**Colonne:**

| Colonna | Contenuto | Note |
|---------|-----------|------|
| Nome | Nome completo | Link a Student Card |
| URC | Ufficio URC | Badge colorato |
| Settore | Settore primario | Badge colorato |
| Settimana | Settimana corrente | 1-6+ |
| Coach | Coach assegnato | Dropdown editabile |
| Stato Report | Stato documento | Nessuno/Bozza/Completo |
| Azioni | Pulsanti | Card, Report, Word |

> **SCREENSHOT 3.3:** Tabella studenti con colonne etichettate

### 3.4 Azioni Rapide

Dalla tabella puoi:

| Pulsante | Icona | Azione |
|----------|-------|--------|
| Card | 👤 | Apre Student Card completa |
| Report | 📊 | Apre compilazione report |
| Word | 📄 | Scarica report Word |

> **SCREENSHOT 3.4:** Colonna azioni con pulsanti evidenziati

### 3.5 Assegnazione Coach Rapida

Puoi assegnare il coach direttamente dalla tabella:

1. Trova lo studente nella tabella
2. Clicca sul dropdown nella colonna "Coach"
3. Seleziona il coach desiderato
4. La modifica viene salvata automaticamente

> **SCREENSHOT 3.5:** Dropdown coach aperto con selezione

### 3.6 Statistiche Dashboard

In alto trovi le statistiche aggregate:

- **Totale studenti:** Numero complessivo
- **Per settore:** Distribuzione settori
- **Per URC:** Distribuzione uffici
- **Report completati:** Percentuale completamento
- **Senza coach:** Studenti da assegnare

> **SCREENSHOT 3.6:** Box statistiche con numeri

---

## 4. Import Studenti CSV

### 4.1 Accesso alla Funzione

```
/local/ftm_cpurc/import.php
```

O dalla Dashboard CPURC, clicca "Import CSV".

> **SCREENSHOT 4.1:** Pulsante Import CSV nella dashboard

### 4.2 Formato File CSV

Il file CSV deve avere il seguente formato:

**Colonne richieste:**
```
cognome,nome,email,numero_personale,ufficio_urc,data_inizio,professione
```

**Colonne opzionali:**
```
consulente_urc,data_fine_prevista,telefono,indirizzo,cap,citta
```

**Esempio:**
```csv
cognome,nome,email,numero_personale,ufficio_urc,data_inizio,professione
Rossi,Mario,mario.rossi@email.ch,12345,Lugano,2026-01-15,Meccanico
Bianchi,Anna,anna.bianchi@email.ch,12346,Bellinzona,2026-01-20,Elettricista
```

> **SCREENSHOT 4.2:** Esempio file CSV aperto in Excel

### 4.3 Procedura Import

**Passo 1 - Upload file:**
1. Clicca "Scegli file"
2. Seleziona il file CSV
3. Clicca "Carica"

> **SCREENSHOT 4.3a:** Form upload file

**Passo 2 - Mappatura campi:**
1. Verifica la corrispondenza colonne
2. Correggi eventuali mapping errati
3. Clicca "Continua"

> **SCREENSHOT 4.3b:** Schermata mappatura campi

**Passo 3 - Anteprima:**
1. Verifica i dati da importare
2. Controlla eventuali errori segnalati
3. Clicca "Importa"

> **SCREENSHOT 4.3c:** Anteprima dati con eventuali warning

**Passo 4 - Risultato:**
1. Visualizza il riepilogo import
2. Nota eventuali record saltati
3. Clicca "Torna alla Dashboard"

> **SCREENSHOT 4.3d:** Riepilogo import completato

### 4.4 Gestione Errori Import

**Errori comuni:**

| Errore | Causa | Soluzione |
|--------|-------|-----------|
| Email duplicata | Utente già esistente | Verrà aggiornato, non duplicato |
| Data non valida | Formato errato | Usa YYYY-MM-DD |
| Campo obbligatorio vuoto | Manca un dato | Compila il CSV |
| Encoding errato | Caratteri speciali | Salva come UTF-8 |

> **SCREENSHOT 4.4:** Messaggio errore con spiegazione

### 4.5 Mapping Automatico Professione → Settore

Il sistema mappa automaticamente la professione al settore:

| Professione | Settore Assegnato |
|-------------|-------------------|
| Meccanico, Meccanico di precisione | MECCANICA |
| Carrozziere, Meccanico auto | AUTOMOBILE |
| Elettricista, Installatore elettrico | ELETTRICITÀ |
| Automazione, Robotica | AUTOMAZIONE |
| Magazziniere, Logistico | LOGISTICA |
| Fabbro, Saldatore | METALCOSTRUZIONE |
| Chimico, Farmaceutico | CHIMFARM |

> **SCREENSHOT 4.5:** Tabella mapping professione-settore

---

## 5. Gestione Student Card

### 5.1 Accesso alla Student Card

**Dalla Dashboard:**
1. Clicca sul nome dello studente, oppure
2. Clicca sull'icona 👤 nella colonna Azioni

**Da URL:**
```
/local/ftm_cpurc/student_card.php?id=X
```

> **SCREENSHOT 5.1:** Link alla Student Card evidenziato

### 5.2 Struttura della Card

La Student Card è organizzata in 4 tab:

| Tab | Contenuto |
|-----|-----------|
| **Anagrafica** | Dati personali, contatti |
| **Percorso** | Dati URC, FTM, coach, settori |
| **Assenze** | Riepilogo assenze per tipo |
| **Stage** | Dati azienda stage |

> **SCREENSHOT 5.2:** Student Card con tab visibili

### 5.3 Tab Anagrafica

**Campi visualizzati:**
- Nome e cognome
- Email
- Telefono
- Indirizzo completo
- Data di nascita
- Numero personale URC

**Campi modificabili:**
- Telefono
- Indirizzo
- Note anagrafiche

> **SCREENSHOT 5.3:** Tab Anagrafica con campi

### 5.4 Tab Percorso

**Sezione Dati URC:**
- Ufficio URC di riferimento
- Consulente URC
- Numero personale

**Sezione Percorso FTM:**
- Data inizio
- Data fine prevista
- Data fine effettiva
- Settimana corrente
- Stato percorso

**Sezione Coach:**
- Coach assegnato (dropdown modificabile)
- Data assegnazione

**Sezione Settori:**
- Settore primario (determina quiz/autovalutazione)
- Settore secondario (opzionale)
- Settore terziario (opzionale)

> **SCREENSHOT 5.4a:** Tab Percorso sezione URC
> **SCREENSHOT 5.4b:** Tab Percorso sezione Coach e Settori

### 5.5 Tab Assenze

**Codici assenza:**

| Codice | Significato |
|--------|-------------|
| X | Assenza non giustificata |
| O | Assenza giustificata |
| A | Malattia |
| B | Infortunio |
| C | Visita medica |
| D | Colloquio lavoro |
| E | Formazione esterna |
| F | Ferie |
| G | Permesso speciale |
| H | Congedo maternità/paternità |
| I | Altro |

**Visualizzazione:**
- Tabella con totali per tipo
- Totale complessivo
- Percentuale presenza

> **SCREENSHOT 5.5:** Tab Assenze con tabella riepilogo

### 5.6 Tab Stage

**Campi stage:**
- Nome azienda
- Indirizzo azienda
- Persona di contatto
- Telefono contatto
- Email contatto
- Data inizio stage
- Data fine stage
- Valutazione stage

> **SCREENSHOT 5.6:** Tab Stage con form

### 5.7 Modifica Dati

**Per modificare i dati:**
1. Clicca sul campo da modificare, oppure
2. Clicca "Modifica" nella sezione
3. Apporta le modifiche
4. Clicca "Salva"

**Campi con salvataggio automatico:**
- Coach (dropdown)
- Settori (pulsanti)

> **SCREENSHOT 5.7:** Campo in modalità modifica

---

## 6. Assegnazione Coach

### 6.1 Sistema di Assegnazione

Ogni studente può avere UN coach assegnato. Il coach:
- Vede lo studente nella sua dashboard
- Può creare valutazioni
- Può aggiungere note
- Riceve notifiche sullo studente

### 6.2 Assegnazione dalla Dashboard

**Metodo rapido:**
1. Trova lo studente nella tabella
2. Clicca sul dropdown "Coach"
3. Seleziona il coach
4. Salvataggio automatico

> **SCREENSHOT 6.2:** Dropdown coach nella tabella

### 6.3 Assegnazione dalla Student Card

**Metodo completo:**
1. Apri la Student Card
2. Vai al tab "Percorso"
3. Nella sezione "Coach", clicca sul dropdown
4. Seleziona il coach
5. Salvataggio automatico

> **SCREENSHOT 6.3:** Sezione Coach nella Student Card

### 6.4 Lista Coach Disponibili

I coach disponibili provengono dalla tabella `local_ftm_coaches`:

| Sigla | Nome Completo | Stato |
|-------|---------------|-------|
| CB | Cristian Bodda | Attivo |
| FM | Fabio Marinoni | Attivo |
| GM | Graziano Margonar | Attivo |
| RB | Roberto Bravo | Attivo |

> **SCREENSHOT 6.4:** Dropdown con lista coach

### 6.5 Rimozione Coach

Per rimuovere un coach:
1. Apri il dropdown coach
2. Seleziona "Nessuno" o l'opzione vuota
3. Il coach viene rimosso

Lo studente non apparirà più nella dashboard del coach.

> **SCREENSHOT 6.5:** Opzione "Nessuno" nel dropdown

### 6.6 Cambio Coach

Per cambiare coach:
1. Seleziona semplicemente il nuovo coach
2. Il vecchio coach viene sostituito
3. Lo storico viene mantenuto nel log

> **SCREENSHOT 6.6:** Log cambio coach

---

## 7. Gestione Settori

### 7.1 Sistema Multi-Settore

Ogni studente può avere fino a 3 settori:

| Livello | Funzione |
|---------|----------|
| **Primario** | Determina quiz e autovalutazione assegnati |
| **Secondario** | Suggerimento per il coach |
| **Terziario** | Ulteriore suggerimento |

### 7.2 Settori Disponibili

| Settore | Codice | Descrizione |
|---------|--------|-------------|
| MECCANICA | MECC | Meccanica di precisione |
| AUTOMOBILE | AUTO | Carrozzeria e meccanica auto |
| AUTOMAZIONE | AUTOM | Automazione industriale |
| ELETTRICITÀ | ELETT | Installazioni elettriche |
| LOGISTICA | LOG | Magazzino e logistica |
| METALCOSTRUZIONE | METAL | Saldatura e fabbro |
| CHIMFARM | CHIM | Chimica e farmaceutica |
| GENERICO | GEN | Test generici orientamento |

> **SCREENSHOT 7.2:** Elenco settori con icone/colori

### 7.3 Assegnazione Settore Primario

**Dalla Student Card:**
1. Vai al tab "Percorso"
2. Sezione "Settori"
3. Clicca su "Settore Primario"
4. Seleziona dal dropdown
5. Salvataggio automatico

**Conseguenze:**
- I quiz del settore vengono pre-selezionati nei report
- L'autovalutazione mostrerà competenze di quel settore
- Il sistema filtra le competenze per il settore

> **SCREENSHOT 7.3:** Selezione settore primario

### 7.4 Aggiunta Settori Secondario/Terziario

1. Clicca "Aggiungi settore"
2. Seleziona il settore
3. Viene aggiunto come secondario (o terziario se secondario già presente)

> **SCREENSHOT 7.4:** Pulsante aggiungi settore

### 7.5 Rimozione Settore

1. Trova il settore nella lista
2. Clicca sulla "X" accanto al settore
3. Conferma la rimozione

**Nota:** Non puoi rimuovere il settore primario senza assegnarne uno nuovo.

> **SCREENSHOT 7.5:** Pulsante X per rimuovere settore

### 7.6 Rilevamento Automatico Settore

Il sistema rileva automaticamente i settori in base a:
- Professione indicata nel CSV import
- Quiz completati dallo studente
- Mappatura automatica codici competenza

Il settore rilevato appare come suggerimento, ma deve essere confermato manualmente come primario.

> **SCREENSHOT 7.6:** Settore rilevato con pulsante "Conferma"

---

## 8. Sector Admin

### 8.1 Accesso

```
/local/competencymanager/sector_admin.php
```

O dal menu "FTM Tools" → "Sector Admin"

> **SCREENSHOT 8.1:** Link Sector Admin nel menu

### 8.2 Panoramica

Sector Admin permette la gestione centralizzata dei settori per tutti gli studenti.

**Funzionalità:**
- Vista tabellare tutti gli studenti
- Filtri avanzati
- Modifica rapida settori
- Export lista

> **SCREENSHOT 8.2:** Vista completa Sector Admin

### 8.3 Filtri Sector Admin

| Filtro | Descrizione |
|--------|-------------|
| Settore | Filtra per settore primario |
| Corso | Filtra per corso iscritto |
| Coorte | Filtra per gruppo/coorte |
| Ricerca | Nome, cognome, email |
| Data | Range date iscrizione |

> **SCREENSHOT 8.3:** Filtri Sector Admin

### 8.4 Tabella Studenti

**Colonne:**
- Studente (nome con link)
- Settore Primario (badge)
- Settori Rilevati (badge multipli)
- Quiz Completati (numero)
- Data Inizio

**Azioni:**
- Clicca sul settore per modificarlo
- Clicca sul nome per aprire il profilo

> **SCREENSHOT 8.4:** Tabella con badge settori

### 8.5 Modifica Settore Rapida

1. Clicca sul badge settore nella riga
2. Si apre il dropdown
3. Seleziona il nuovo settore
4. Salvataggio automatico

> **SCREENSHOT 8.5:** Modifica settore inline

### 8.6 Legenda Colori Settimana

La tabella usa colori per indicare la settimana:

| Colore | Significato |
|--------|-------------|
| Verde | < 2 settimane (nuovo ingresso) |
| Giallo | 2-4 settimane (in corso) |
| Arancione | 4-6 settimane (vicino fine) |
| Rosso | > 6 settimane (prolungato) |
| Grigio | Data non impostata |

> **SCREENSHOT 8.6:** Legenda colori nella pagina

---

## 9. Generazione Report

### 9.1 Tipi di Report

| Report | Descrizione | Destinatario |
|--------|-------------|--------------|
| Report Studente | Competenze e progressi | Coach/Studente |
| Report Word CPURC | Documento finale URC | URC/Istituzioni |
| Report Classe | Aggregato gruppo | Coach/Direzione |

### 9.2 Report Word CPURC

**Accesso:**
1. Dashboard CPURC → Clicca "Word" nella riga studente
2. Oppure Student Card → Tab Percorso → "Genera Report Word"

**Contenuto:**
- Intestazione con logo FTM
- Dati anagrafici studente
- Percorso formativo
- Valutazione comportamentale
- Competenze acquisite
- Stage e pratica
- Raccomandazioni coach
- Conclusione e firma

> **SCREENSHOT 9.2:** Report Word generato

### 9.3 Compilazione Report

**Dalla pagina Report:**
```
/local/ftm_cpurc/report.php?id=X
```

**Sezioni da compilare:**

1. **Comportamento:**
   - Puntualità
   - Collaborazione
   - Iniziativa
   - Rispetto regole

2. **Competenze Tecniche:**
   - Sintesi competenze acquisite
   - Aree di eccellenza
   - Aree di miglioramento

3. **Competenze Trasversali:**
   - Comunicazione
   - Problem solving
   - Lavoro di squadra

4. **Raccomandazioni:**
   - Suggerimenti per il futuro
   - Orientamento professionale

5. **Conclusione:**
   - Valutazione complessiva
   - Firma digitale

> **SCREENSHOT 9.3a:** Form compilazione report - Sezione Comportamento
> **SCREENSHOT 9.3b:** Form compilazione report - Sezione Competenze
> **SCREENSHOT 9.3c:** Form compilazione report - Sezione Conclusione

### 9.4 Stati del Report

| Stato | Significato | Azioni |
|-------|-------------|--------|
| **Nessuno** | Non ancora creato | Crea nuovo |
| **Bozza** | In lavorazione | Modifica, Completa |
| **Completo** | Terminato | Esporta, Modifica |
| **Inviato** | Consegnato a URC | Solo visualizza |

> **SCREENSHOT 9.4:** Badge stati report

### 9.5 Export Report Singolo

1. Apri il report dello studente
2. Clicca "Esporta Word"
3. Il file .docx viene scaricato

> **SCREENSHOT 9.5:** Pulsante Esporta Word

### 9.6 Export Report Bulk

Per esportare più report insieme:

1. Dalla Dashboard CPURC
2. Clicca "Export Word Bulk"
3. Seleziona i filtri (opzionale)
4. Clicca "Genera ZIP"
5. Scarica l'archivio con tutti i report

> **SCREENSHOT 9.6:** Dialogo Export Word Bulk

---

## 10. Export Dati

### 10.1 Export Excel Completo

**Dalla Dashboard CPURC:**
1. Clicca "Export Excel"
2. Seleziona i campi da includere
3. Applica eventuali filtri
4. Clicca "Esporta"
5. Scarica il file .xlsx

**Campi disponibili:**
- Dati anagrafici
- Dati URC
- Settori
- Coach
- Date percorso
- Stato report
- Assenze
- Stage

> **SCREENSHOT 10.1:** Dialogo Export Excel con checkbox campi

### 10.2 Export Quiz/Competenze

**Dal Setup Universale:**
```
/local/competencyxmlimport/quiz_export.php
```

1. Seleziona il corso
2. Seleziona i quiz
3. Scegli formato (CSV/Excel)
4. Clicca "Esporta"

**Contenuto export:**
- Nome quiz
- Domande
- Risposte (A, B, C, D)
- Risposta corretta
- Competenza assegnata
- Livello difficoltà

> **SCREENSHOT 10.2:** Pagina Quiz Export

### 10.3 Anteprima Prima dell'Export

Il sistema mostra un'anteprima dei dati:
- Prime 10 righe
- Colonne selezionate
- Eventuali warning

> **SCREENSHOT 10.3:** Anteprima export con tabella

### 10.4 Formati Disponibili

| Formato | Estensione | Uso |
|---------|------------|-----|
| Excel | .xlsx | Analisi, grafici |
| CSV | .csv | Import in altri sistemi |
| Word | .docx | Documenti formali |
| PDF | .pdf | Archivio, stampa |

---

## 11. Setup Quiz e Competenze

### 11.1 Setup Universale

Il Setup Universale permette di importare quiz e assegnare competenze:

```
/local/competencyxmlimport/setup_universale.php?courseid=X
```

> **SCREENSHOT 11.1:** Pagina Setup Universale

### 11.2 Flusso Import Quiz

**Step 1 - Seleziona Framework:**
- FTM-01 (Passaporto Tecnico FTM)
- FTM_GEN (Test Generici)

**Step 2 - Seleziona Settore:**
Il settore viene rilevato automaticamente dal framework.

**Step 3 - Carica File:**
- XML (formato Moodle)
- Word (verrà convertito)

**Step 4 - Configura:**
- Nome quiz
- Livello difficoltà (1-3)
- Categoria

**Step 5 - Esegui:**
- Log dettagliato dell'operazione
- Riepilogo finale

> **SCREENSHOT 11.2a:** Step 1 - Selezione Framework
> **SCREENSHOT 11.2b:** Step 2 - Selezione Settore
> **SCREENSHOT 11.2c:** Step 3 - Upload File
> **SCREENSHOT 11.2d:** Step 4 - Configurazione
> **SCREENSHOT 11.2e:** Step 5 - Log esecuzione

### 11.3 Assegnazione Competenze

Durante l'import, il sistema:
1. Estrae i codici competenza dal testo domande
2. Cerca la competenza nel framework
3. Assegna la competenza alla domanda
4. Imposta il livello di difficoltà

**Pattern riconosciuti:**
```
MECCANICA_A_01
AUTOMOBILE_B_03
ELETTRICITÀ_C_02
```

> **SCREENSHOT 11.3:** Log con competenze assegnate

### 11.4 Livelli Difficoltà

| Livello | Stelle | Descrizione |
|---------|--------|-------------|
| 1 | ⭐ | Base |
| 2 | ⭐⭐ | Intermedio |
| 3 | ⭐⭐⭐ | Avanzato |

> **SCREENSHOT 11.4:** Selezione livello difficoltà

### 11.5 Quiz Export Tool

Per analizzare i quiz esistenti:

1. Vai a Quiz Export
2. Seleziona corso e quiz
3. Visualizza anteprima HTML
4. Esporta in CSV/Excel

**Utile per:**
- Trovare domande duplicate
- Verificare competenze assegnate
- Analizzare distribuzione difficoltà

> **SCREENSHOT 11.5:** Anteprima quiz con risposte evidenziate

---

## 12. FTM Scheduler

### 12.1 Accesso

```
/local/ftm_scheduler/index.php
```

> **SCREENSHOT 12.1:** Pagina principale Scheduler

### 12.2 Vista Calendario

**Vista Settimanale:**
- Giorni Lun-Ven
- Fasce orarie
- Attività programmate

**Vista Mensile:**
- Calendario mensile
- Giorni con attività evidenziati

> **SCREENSHOT 12.2a:** Vista settimanale
> **SCREENSHOT 12.2b:** Vista mensile

### 12.3 Gestione Gruppi Colore

| Colore | Codice Hex | Gruppo |
|--------|------------|--------|
| Giallo | #FFFF00 | Gruppo A |
| Grigio | #808080 | Gruppo B |
| Rosso | #FF0000 | Gruppo C |
| Marrone | #996633 | Gruppo D |
| Viola | #7030A0 | Gruppo E |

**Come assegnare gruppo:**
1. Seleziona lo studente
2. Clicca "Assegna Gruppo"
3. Scegli il colore
4. Salva

> **SCREENSHOT 12.3:** Assegnazione gruppo colore

### 12.4 Gestione Aule

**Aule disponibili:**
- Atelier Meccanica
- Atelier Automobile
- Laboratorio Informatica
- Aula Teoria

**Per ogni aula:**
- Capacità massima
- Orari disponibilità
- Prenotazioni attive

> **SCREENSHOT 12.4:** Gestione aule

### 12.5 Creazione Attività

1. Clicca sulla cella data/ora
2. Compila il form:
   - Tipo attività
   - Gruppo/studenti
   - Aula
   - Coach responsabile
3. Salva

> **SCREENSHOT 12.5:** Form creazione attività

### 12.6 Gestione Coach

Tabella `local_ftm_coaches`:

| Campo | Descrizione |
|-------|-------------|
| id | ID univoco |
| sigla | Sigla coach (CB, FM, ecc.) |
| fullname | Nome completo |
| userid | ID utente Moodle |
| active | Stato attivo/inattivo |

> **SCREENSHOT 12.6:** Tabella coach nello scheduler

---

## 13. Test Suite e Diagnostica

### 13.1 Accesso Test Suite

```
/local/ftm_testsuite/agent_tests.php
```

> **SCREENSHOT 13.1:** Pagina Test Suite

### 13.2 Agenti di Test

| Agente | Funzione | Test |
|--------|----------|------|
| Security | Verifica sicurezza | SQL injection, XSS, CSRF |
| Database | Verifica DB | Integrità tabelle, FK |
| AJAX | Verifica endpoint | Response, errori |
| Structure | Verifica struttura | File, permessi, version |
| Language | Verifica stringhe | EN/IT completezza |

### 13.3 Esecuzione Test

1. Seleziona gli agenti da eseguire
2. Clicca "Esegui Test"
3. Attendi il completamento
4. Visualizza i risultati

**Risultati:**
- ✅ Pass: Test superato
- ⚠️ Warning: Attenzione
- ❌ Fail: Test fallito

> **SCREENSHOT 13.3:** Risultati test con icone stato

### 13.4 Interpretazione Risultati

**Security Agent:**
- Verifica input sanitization
- Controlla CSRF protection
- Testa SQL injection

**Database Agent:**
- Verifica esistenza tabelle
- Controlla chiavi esterne
- Testa integrità dati

**AJAX Agent:**
- Testa endpoint AJAX
- Verifica response JSON
- Controlla gestione errori

> **SCREENSHOT 13.4:** Dettaglio risultato singolo test

### 13.5 Tool Diagnostici

**Cleanup Empty Evaluations:**
```
/local/competencymanager/cleanup_empty_evaluations.php
```
Elimina valutazioni coach senza ratings.

**Diagnose Critest:**
```
/local/selfassessment/diagnose_critest.php
```
Diagnostica problemi autovalutazione.

**Analyze Prefixes:**
```
/local/selfassessment/analyze_all_prefixes.php
```
Analizza prefissi competenze.

> **SCREENSHOT 13.5:** Pagina diagnostica con statistiche

---

## 14. Gestione Utenti Moodle

### 14.1 Creazione Utenti

**Da CSV Import:**
Se l'utente non esiste, viene creato automaticamente durante l'import.

**Manualmente:**
1. Amministrazione → Utenti → Crea utente
2. Compila i campi obbligatori
3. Assegna ruolo "Studente FTM"

> **SCREENSHOT 14.1:** Form creazione utente

### 14.2 Ruoli FTM

| Ruolo | Permessi |
|-------|----------|
| Studente FTM | Accesso quiz, autovalutazione, visualizza report |
| Coach FTM | Dashboard coach, valutazioni, note |
| Segreteria FTM | Accesso completo amministrativo |
| Admin FTM | Configurazione sistema |

> **SCREENSHOT 14.2:** Lista ruoli FTM

### 14.3 Assegnazione Ruoli

1. Apri il profilo utente
2. Tab "Ruoli"
3. Clicca "Assegna ruolo"
4. Seleziona il ruolo
5. Seleziona il contesto (sistema o corso)
6. Conferma

> **SCREENSHOT 14.3:** Assegnazione ruolo

### 14.4 Iscrizione ai Corsi

Per iscrivere studenti ai corsi FTM:

1. Vai al corso
2. Partecipanti → Iscrivi utenti
3. Cerca lo studente
4. Seleziona ruolo "Studente"
5. Clicca "Iscrivi"

> **SCREENSHOT 14.4:** Dialog iscrizione corso

### 14.5 Gestione Coorti/Gruppi

**Coorti:**
- Gruppi di studenti a livello sistema
- Utili per iscrizioni massive

**Gruppi corso:**
- Gruppi interni al corso
- Utili per attività differenziate

> **SCREENSHOT 14.5:** Gestione coorti

---

## 15. Casi d'Uso Pratici

### 15.1 Caso: Nuovo Gruppo Studenti

**Scenario:** Arriva un nuovo gruppo di 10 studenti CPURC.

**Passi:**
1. Ricevi il file CSV dall'URC
2. Verifica il formato (colonne richieste)
3. Vai a Import CSV
4. Carica il file
5. Verifica mappatura campi
6. Esegui import
7. Verifica risultato
8. Assegna settori primari (se non rilevati)
9. Assegna coach agli studenti
10. Verifica iscrizione ai corsi

> **SCREENSHOT 15.1:** Workflow import nuovo gruppo

### 15.2 Caso: Preparazione Report Fine Percorso

**Scenario:** 5 studenti sono alla settimana 6, serve generare i report.

**Passi:**
1. Dalla Dashboard, filtra "Settimana 6"
2. Per ogni studente:
   - Verifica valutazione coach completata
   - Apri pagina Report
   - Compila sezioni mancanti
   - Salva come "Completo"
3. Export Word Bulk
4. Invia i report all'URC

> **SCREENSHOT 15.2:** Filtro settimana 6 con studenti

### 15.3 Caso: Cambio Coach a Metà Percorso

**Scenario:** Un coach va in ferie, devi riassegnare i suoi studenti.

**Passi:**
1. Dalla Dashboard, filtra per "Coach = [nome]"
2. Nota gli studenti assegnati
3. Per ogni studente:
   - Apri il dropdown coach
   - Seleziona il nuovo coach
4. Verifica che il nuovo coach veda gli studenti
5. Informa entrambi i coach del cambio

> **SCREENSHOT 15.3:** Filtro per coach e riassegnazione

### 15.4 Caso: Studente Cambia Settore

**Scenario:** Uno studente scopre di preferire un altro settore.

**Passi:**
1. Apri la Student Card
2. Tab Percorso → Settori
3. Cambia il settore primario
4. Verifica che i quiz del nuovo settore siano disponibili
5. Informa il coach del cambio
6. Aggiorna eventualmente l'autovalutazione

> **SCREENSHOT 15.4:** Cambio settore nella Student Card

### 15.5 Caso: Import Quiz Nuovo Settore

**Scenario:** Devi aggiungere quiz per un nuovo settore.

**Passi:**
1. Prepara il file XML con le domande
2. Vai a Setup Universale
3. Seleziona Framework FTM-01
4. Seleziona il settore appropriato
5. Carica il file XML
6. Configura nome e difficoltà
7. Esegui e verifica il log
8. Controlla i quiz nel corso
9. Verifica le competenze assegnate

> **SCREENSHOT 15.5:** Setup Universale completato

---

## 16. Risoluzione Problemi

### 16.1 Import CSV Fallisce

**Possibili cause:**
- Formato file errato
- Encoding non UTF-8
- Campi obbligatori mancanti

**Soluzioni:**
1. Apri il file in Excel, salva come CSV UTF-8
2. Verifica le colonne obbligatorie
3. Rimuovi caratteri speciali problematici
4. Riprova l'import

### 16.2 Studente Non Appare nella Dashboard

**Possibili cause:**
- Non importato correttamente
- Filtri attivi
- Non ha record CPURC

**Soluzioni:**
1. Rimuovi tutti i filtri
2. Cerca per email esatta
3. Verifica nella tabella utenti Moodle
4. Se necessario, importa nuovamente

### 16.3 Report Word Non Si Genera

**Possibili cause:**
- Dati incompleti
- Errore server
- Permessi file

**Soluzioni:**
1. Verifica che tutti i campi siano compilati
2. Prova con un altro browser
3. Controlla i log di errore
4. Contatta l'amministratore

### 16.4 Settore Non Viene Rilevato

**Possibili cause:**
- Professione non mappata
- Nessun quiz completato
- Competenze non assegnate ai quiz

**Soluzioni:**
1. Imposta il settore manualmente
2. Verifica il mapping professione-settore
3. Controlla che i quiz abbiano competenze

### 16.5 Coach Non Vede lo Studente

**Possibili cause:**
- Assegnazione non salvata
- Cache non aggiornata
- Permessi mancanti

**Soluzioni:**
1. Riassegna il coach dalla Student Card
2. Chiedi al coach di svuotare la cache (Ctrl+F5)
3. Verifica i permessi del ruolo coach

### 16.6 Contatti Supporto

- **Supporto tecnico:** Per problemi sistema
- **Amministratore Moodle:** Per permessi e ruoli
- **Referente URC:** Per questioni amministrative

---

## Appendice A: Checklist Settimanale Segreteria

```
□ Verificare nuovi studenti da importare
□ Controllare assegnazioni coach mancanti
□ Verificare settori non assegnati
□ Controllare studenti in settimana 5-6
□ Generare report per studenti in uscita
□ Backup dati settimanale
□ Verificare risultati Test Suite
```

## Appendice B: Mapping Professioni → Settori

| Professione CSV | Settore FTM |
|-----------------|-------------|
| Meccanico | MECCANICA |
| Meccanico di precisione | MECCANICA |
| Meccanico auto | AUTOMOBILE |
| Carrozziere | AUTOMOBILE |
| Elettricista | ELETTRICITÀ |
| Installatore elettrico | ELETTRICITÀ |
| Automazione | AUTOMAZIONE |
| Magazziniere | LOGISTICA |
| Saldatore | METALCOSTRUZIONE |
| Fabbro | METALCOSTRUZIONE |
| Chimico | CHIMFARM |
| Farmaceutico | CHIMFARM |

## Appendice C: Struttura Database FTM

**Tabelle principali:**
- `local_ftm_cpurc_students` - Anagrafica studenti CPURC
- `local_ftm_cpurc_reports` - Report Word
- `local_student_coaching` - Assegnazioni coach
- `local_student_sectors` - Settori studenti
- `local_ftm_coaches` - Anagrafica coach
- `local_coach_evaluations` - Valutazioni formatore
- `local_coach_eval_ratings` - Ratings singole competenze

---

**Fine del Manuale Segreteria**

*Documento generato per FTM Academy - Febbraio 2026*
