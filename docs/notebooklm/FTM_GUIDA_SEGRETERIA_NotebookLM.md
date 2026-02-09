# FTM PLUGINS - GUIDA COMPLETA PER SEGRETERIA
## Documento per NotebookLM - Fondazione Terzo Millennio

**Versione:** 5.1 | **Data:** 9 Febbraio 2026

---

# PARTE 1: INTRODUZIONE AL SISTEMA FTM

## Cos'è il Sistema FTM?

Il sistema FTM (Fondazione Terzo Millennio) è un ecosistema di 13 plugin Moodle sviluppati per gestire il programma CPURC (Centro Professionale URC) in Svizzera. Il sistema permette alla segreteria di:
- Gestire tutti gli studenti CPURC in un'unica piattaforma
- Importare dati da file CSV
- Assegnare coach e settori agli studenti
- Esportare report in Excel e Word
- Monitorare lo stato di avanzamento dei percorsi

## Ruoli nel Sistema

| Ruolo | Responsabilità |
|-------|----------------|
| **Segreteria** | Gestione completa studenti, import/export, assegnazioni |
| **Coach** | Seguire studenti assegnati, compilare report |
| **Studente** | Completare quiz e autovalutazioni |

## I Coach FTM

I coach attivi nel sistema sono:
- **CB** - Cristian Bodda
- **FM** - Fabio Marinoni
- **GM** - Graziano Margonar
- **RB** - Roberto Bravo

Il personale di segreteria include:
- **Sandra** - Segreteria
- **Alessandra** - Segreteria

## Il Percorso delle 6 Settimane

Ogni studente segue un percorso strutturato:
- **Settimana 1:** Accoglienza e valutazione iniziale
- **Settimane 2-5:** Formazione professionale
- **Settimana 6:** Valutazione finale

Gli studenti oltre la settimana 6 sono in "prolungamento" (badge rosso).

## I 7 Settori Professionali

| Codice | Nome Completo | Colore Badge |
|--------|---------------|--------------|
| AUTOMOBILE | Automobile / Autoveicoli | Blu |
| MECCANICA | Meccanica industriale | Verde |
| LOGISTICA | Logistica e magazzino | Giallo |
| ELETTRICITÀ | Impianti elettrici | Rosso |
| AUTOMAZIONE | Sistemi automatizzati | Viola |
| METALCOSTRUZIONE | Lavorazione metalli | Grigio |
| CHIMFARM | Chimico-Farmaceutico | Rosa |

---

# PARTE 2: DASHBOARD CPURC

## Accesso alla Dashboard

1. Accedi a Moodle con le tue credenziali
2. Vai a: `/local/ftm_cpurc/index.php`
3. Oppure cerca "FTM CPURC" nel menu

## Interfaccia della Dashboard

La dashboard mostra tutti gli studenti CPURC in una tabella con:

### Barra Superiore
- **Campo Ricerca:** Cerca per nome, cognome o email
- **Filtro URC:** Filtra per ufficio URC (Lugano, Bellinzona, Locarno, Mendrisio)
- **Filtro Settore:** Filtra per settore professionale
- **Filtro Stato Report:** Nessuno, Bozza, Completo
- **Filtro Coach:** Filtra per coach assegnato o "Nessun Coach"
- **Pulsante Export Excel:** Scarica dati in Excel
- **Pulsante Export Word ZIP:** Scarica tutti i report in un archivio

### Colonne della Tabella

| Colonna | Descrizione |
|---------|-------------|
| **Nome** | Nome e cognome (link alla scheda) |
| **URC** | Ufficio URC di riferimento |
| **Settore** | Badge colorato del settore |
| **Settimana** | Settimana nel percorso (S1-S6+) |
| **Coach** | Dropdown per assegnare/cambiare coach |
| **Report** | Stato del report (--/📝/✅) |
| **Azioni** | Pulsanti 📋 Card, 📝 Report, 📄 Word |

### Badge Settimana

| Badge | Significato |
|-------|-------------|
| 🆕 S1-S2 | Studente nuovo (verde) |
| ⏳ S3-S4 | Studente in corso (blu) |
| ⚠️ S5-S6 | Fine vicina (giallo) |
| 🔴 S7+ | Prolungamento (rosso) |

### Badge Report

| Icona | Significato |
|-------|-------------|
| -- | Nessun report iniziato |
| 📝 | Bozza in corso |
| ✅ | Report finalizzato |

## Usare i Filtri

### Ricerca Testuale
Digita nel campo ricerca per trovare:
- Per nome: "mario" trova tutti i Mario
- Per cognome: "rossi" trova tutti i Rossi
- Per email: "@gmail" trova tutte le email Gmail

### Combinare Filtri
Puoi combinare più filtri insieme. Esempio:
- URC: Lugano + Settore: MECCANICA + Report: Nessuno
- Mostra solo studenti di Lugano, settore meccanica, senza report

### Reset Filtri
Per tornare a vedere tutti:
- Seleziona "Tutti" in ogni filtro
- Oppure ricarica la pagina

---

# PARTE 3: ASSEGNARE COACH E SETTORI

## Assegnare il Coach

### Metodo Rapido (dalla Dashboard)
1. Trova lo studente nella tabella
2. Clicca sul **dropdown Coach** nella sua riga
3. Seleziona il coach (CB, FM, GM, RB)
4. Il salvataggio è **automatico**
5. Appare conferma "✅ Coach salvato"

### Metodo Completo (dalla Scheda)
1. Clicca sul nome dello studente
2. Vai al tab **Percorso**
3. Trova sezione "Coach FTM Assegnato"
4. Seleziona dal dropdown
5. Clicca **💾 Salva Coach**

### Rimuovere il Coach
1. Seleziona "-- (Nessuno)" dal dropdown
2. Il coach viene rimosso

**Nota:** L'assegnazione è sincronizzata con tutti i plugin FTM!

## Il Sistema Multi-Settore

Ogni studente può avere fino a **3 settori**:

| Livello | Icona | Funzione |
|---------|-------|----------|
| **Primario** | 🥇 | OBBLIGATORIO - Determina quiz e autovalutazione |
| **Secondario** | 🥈 | Opzionale - Suggerimento per il coach |
| **Terziario** | 🥉 | Opzionale - Suggerimento aggiuntivo |

### Importanza del Settore Primario

Il settore primario è **cruciale** perché:
1. Lo studente riceve solo quiz del settore primario
2. L'autovalutazione include solo competenze del settore primario
3. Il settore appare nel report finale

**Senza settore primario, lo studente potrebbe non ricevere i materiali corretti!**

## Assegnare i Settori

1. Apri la scheda studente
2. Vai al tab **Percorso**
3. Trova la sezione **🎯 Assegnazione Settori**
4. Seleziona:
   - 🥇 Primario: OBBLIGATORIO
   - 🥈 Secondario: Opzionale
   - 🥉 Terziario: Opzionale
5. Clicca **💾 Salva Settori**

### Regole
- I tre settori devono essere **diversi** tra loro
- Non puoi assegnare lo stesso settore a più livelli
- Solo il primario è obbligatorio

## Settori Rilevati Automaticamente

Il sistema rileva automaticamente settori quando lo studente:
- Completa un quiz del settore
- Fa l'autovalutazione di competenze del settore

I settori rilevati appaiono come badge:
```
[MECCANICA 🥇 (3 quiz)] [AUTOMOBILE (1 quiz)]
```

Il numero indica quante competenze sono state rilevate per quel settore.

## Eliminare un Settore

### Dalla Tendina
1. Clicca sulla **❌** accanto al nome settore
2. Il menu torna a "-- Seleziona --"
3. Clicca **💾 Salva Settori**

### Dai Settori Rilevati
1. Clicca sulla **❌** sul badge del settore
2. Conferma l'eliminazione

---

# PARTE 4: SCHEDA STUDENTE (STUDENT CARD)

## Accesso

1. Dashboard → Clicca sul nome studente
2. Dashboard → Pulsante **📋** nella colonna Azioni
3. URL: `/local/ftm_cpurc/student_card.php?id=X`

## I 4 Tab della Scheda

### Tab Anagrafica

**Dati Personali:**
- Nome, Cognome, Genere
- Data di nascita, Nazionalità
- Permesso di soggiorno

**Contatti:**
- Email, Telefono, Cellulare

**Indirizzo:**
- Via, CAP, Città

**Dati Amministrativi:**
- Numero AVS (756.XXXX.XXXX.XX)
- IBAN
- Stato civile

### Tab Percorso

**Dati URC:**
- Numero Personale URC
- Ufficio URC di riferimento
- Consulente URC

**Percorso FTM:**
- Misura attiva
- Data inizio e fine prevista
- Data fine effettiva (se concluso)
- Stato: Aperto/Chiuso
- Grado di occupazione (%)

**Coach Assegnato:**
- Nome e email del coach
- Dropdown per cambiare coach

**Assegnazione Settori:**
- Settore Primario (obbligatorio)
- Settore Secondario (opzionale)
- Settore Terziario (opzionale)

**Professione:**
- Ultima professione svolta
- Settore rilevato dalla professione

### Tab Assenze

Riepilogo completo delle assenze:

| Codice | Significato |
|--------|-------------|
| X | Malattia |
| O | Ingiustificata |
| A | Permesso |
| B | Colloquio |
| C | Corso |
| D-I | Altri codici |
| TOT | Totale |

Interpretazione:
- TOT < 5: Buona frequenza ✅
- TOT 5-10: Da monitorare ⚠️
- TOT > 10: Attenzione 🔴

### Tab Stage

Se lo studente ha uno stage:

**Dati Stage:**
- Data inizio e fine
- Percentuale impiego

**Azienda:**
- Nome azienda
- Indirizzo completo
- Funzione/ruolo

**Contatto Aziendale:**
- Nome referente
- Telefono e email

---

# PARTE 5: IMPORT CSV

## Quando Usarlo

- Nuovi studenti da inserire nel sistema
- Aggiornamento dati esistenti
- Import iniziale di un gruppo

## Accesso

1. Dashboard CPURC → **Import CSV**
2. URL: `/local/ftm_cpurc/import.php`

## Formato del File CSV

### Requisiti Tecnici
- Formato: CSV (virgola o punto e virgola)
- Codifica: UTF-8 (preferibile)
- Prima riga: Intestazioni colonne

### Colonne Obbligatorie

| Colonna | Descrizione | Esempio |
|---------|-------------|---------|
| email | Email utente (univoca) | mario.rossi@email.com |
| firstname | Nome | Mario |
| lastname | Cognome | Rossi |

### Colonne Opzionali

| Colonna | Descrizione |
|---------|-------------|
| personal_number | Numero personale URC |
| urc_office | Ufficio URC |
| urc_consultant | Consulente URC |
| phone | Telefono |
| mobile | Cellulare |
| birthdate | Data nascita (YYYY-MM-DD) |
| gender | Genere (M/F) |
| nationality | Nazionalità |
| address_street | Via |
| address_cap | CAP |
| address_city | Città |
| date_start | Data inizio (YYYY-MM-DD) |
| date_end_planned | Data fine prevista |
| measure | Tipo misura |
| last_profession | Ultima professione |
| avs_number | Numero AVS |

### Esempio File CSV
```csv
email,firstname,lastname,personal_number,urc_office,date_start,last_profession
mario.rossi@email.com,Mario,Rossi,123456,Lugano,2026-01-15,Meccanico
lucia.bianchi@email.com,Lucia,Bianchi,123457,Bellinzona,2026-01-20,Logistica
paolo.verdi@email.com,Paolo,Verdi,123458,Lugano,2026-01-22,Elettricista
```

## Procedura di Import

### Passo 1: Prepara il File
1. Esporta dati da CPURC in CSV
2. Verifica colonne obbligatorie
3. Salva con codifica UTF-8

### Passo 2: Carica il File
1. Vai alla pagina Import CSV
2. Clicca **Scegli file** o trascina
3. Seleziona il file CSV

### Passo 3: Anteprima
Prima dell'import vedi:
- Numero righe totali
- Quanti nuovi utenti
- Quanti aggiornamenti
- Stato per ogni riga

### Passo 4: Conferma
1. Verifica i dati nell'anteprima
2. Clicca **▶️ Avvia Import**
3. Attendi il completamento

### Passo 5: Report
Al termine vedi:
- Totale processati
- Importati con successo
- Errori e dettagli

## Gestione Errori Import

| Errore | Causa | Soluzione |
|--------|-------|-----------|
| "Email non valida" | Email malformata | Correggi nel CSV |
| "Email duplicata" | Email già esistente | Rimuovi o aggiorna |
| "Campo obbligatorio mancante" | Nome/cognome/email vuoto | Compila |
| "Data non valida" | Formato errato | Usa YYYY-MM-DD |
| "Errore codifica" | File non UTF-8 | Risalva come UTF-8 |

---

# PARTE 6: EXPORT DATI

## Export Excel

Scarica tutti i dati in un file Excel.

### Come Esportare
1. Dashboard CPURC
2. (Opzionale) Applica filtri
3. Clicca **📊 Export Excel**
4. Download automatico

### Contenuto del File (30+ colonne)

**Dati Personali:** Nome, Cognome, Email, Telefono, Data nascita, Genere, Nazionalità

**Indirizzo:** Via, CAP, Città

**Dati URC:** Numero personale, Ufficio, Consulente

**Percorso:** Date, Misura, Stato, Settore, Coach

**Assenze:** Tutte le tipologie + Totale

**Stage:** Azienda, Contatto, Date

**Report:** Stato, Data ultima modifica

## Export Word Singolo

Scarica il report di UN singolo studente.

### Dove Trovarlo
1. Dashboard → Pulsante **📄** nella riga
2. Scheda studente → **📄 Export Word**
3. Pagina Report → **📄 Esporta Word**

### Requisiti
- Il report deve essere compilato (almeno bozza)
- Servono i permessi di visualizzazione

## Export Word Massivo (ZIP)

Scarica TUTTI i report in un archivio ZIP.

### Accesso
1. Dashboard → **📄 Export Word ZIP**
2. URL: `/local/ftm_cpurc/export_word_bulk.php`

### Opzioni

| Opzione | Descrizione |
|---------|-------------|
| **Tutti** | Esporta tutti i report |
| **Solo completi** | Solo report finalizzati |
| **Solo bozze** | Solo bozze in corso |

### Procedura
1. Seleziona l'opzione
2. Clicca **📦 Genera ZIP e Scarica**
3. Attendi la generazione
4. Download automatico

### Contenuto ZIP
```
Rapporti_CPURC_2026-02-09.zip
├── Rapporto_Rossi_Mario.docx
├── Rapporto_Bianchi_Lucia.docx
├── Rapporto_Verdi_Paolo.docx
└── ...
```

### Tempi di Generazione

| Numero Report | Tempo Stimato |
|---------------|---------------|
| 1-10 | < 30 secondi |
| 10-50 | 1-2 minuti |
| 50-100 | 3-5 minuti |
| 100+ | 5+ minuti |

---

# PARTE 7: GESTIONE REPORT

## Stati del Report

| Stato | Icona | Significato |
|-------|-------|-------------|
| Nessuno | -- | Report non iniziato |
| Bozza | 📝 | In compilazione |
| Completo | ✅ | Finalizzato |
| Inviato | 📤 | Consegnato a URC |

## Monitorare i Report

### Trovare Report Mancanti
1. Dashboard → Filtro Stato Report → **Nessun Report**
2. Vedi tutti gli studenti senza report

### Trovare Bozze in Corso
1. Dashboard → Filtro Stato Report → **Bozza**
2. Verifica se i coach stanno lavorando

### Trovare Report Completi
1. Dashboard → Filtro Stato Report → **Completo**
2. Pronti per export Word

## Flusso Report

```
1. Coach inizia report
      ↓
2. Salva come bozza (più volte)
      ↓
3. Finalizza report
      ↓
4. Segreteria esporta Word
      ↓
5. Stampa/Invio a URC
```

**Importante:** Una volta finalizzato, il report NON può essere modificato. Solo un admin può sbloccarlo.

---

# PARTE 8: CALENDARIO FTM SCHEDULER

## Accesso

URL: `/local/ftm_scheduler/index.php`

## Funzionalità Segreteria

### Gestione Gruppi
Creare e gestire gruppi di studenti con colori:
- 🟡 Giallo
- ⬜ Grigio
- 🔴 Rosso
- 🟤 Marrone
- 🟣 Viola

### Gestione Aule
Configurare aule disponibili per le attività.

### Gestione Atelier
Configurare spazi atelier per attività pratiche.

### Pianificazione Attività
Creare attività per gruppi con:
- Data e orario
- Aula assegnata
- Tipo attività

### Slot Orari

| Codice | Orario |
|--------|--------|
| AM1 | 08:00 - 10:00 |
| AM2 | 10:15 - 12:15 |
| PM1 | 13:15 - 15:15 |
| PM2 | 15:30 - 17:30 |

## Vista Gruppo

Per ogni gruppo puoi vedere:
- Membri del gruppo
- Attività programmate
- Progressione nel percorso
- Link al programma individuale di ogni membro

---

# PARTE 9: SECTOR ADMIN (GESTIONE AVANZATA SETTORI)

## Accesso

URL: `/local/competencymanager/sector_admin.php`

## Funzionalità

### Panoramica Settori
Visualizza statistiche per settore:
- Numero studenti per settore
- Studenti senza settore primario
- Distribuzione settori

### Assegnazioni Massive
Puoi assegnare settori a più studenti contemporaneamente.

### Modifica Settore Singolo
1. Trova lo studente nella lista
2. Clicca sull'icona **✏️ (matita)**
3. Modifica i settori
4. Salva

## Best Practice

| Situazione | Azione Consigliata |
|------------|---------------------|
| Nuovo studente | Assegna subito settore primario e coach |
| Cambio interessi | Aggiorna settore secondario/terziario |
| Errore settore | Elimina e riassegna |
| Coach in ferie | Riassegna temporaneamente a altro coach |

---

# PARTE 10: STRUMENTI DIAGNOSTICI

## Diagnose Quiz Selector

URL: `/local/competencymanager/diagnose_quiz_selector.php?userid=X`

Mostra:
- Tutti i quiz completati dallo studente
- Il courseid corretto da usare
- Link diretti al report con parametri corretti

## Diagnose Quiz Competencies

URL: `/local/competencymanager/diagnose_quiz_competencies.php?userid=X`

Mostra:
- Competenze di ogni quiz completato
- Settore estratto da ogni competenza
- Settori salvati vs calcolati

## Quando Usarli

- Studente non vede il suo quiz nel report
- Settore mostrato è diverso da quello atteso
- Quiz count mostra 0 per un settore

---

# PARTE 11: RISOLUZIONE PROBLEMI

## Problemi Comuni

### Import CSV fallisce
- Verifica formato file (CSV, UTF-8)
- Controlla colonne obbligatorie
- Leggi messaggio errore specifico

### Studente non ha settore
1. Vai alla scheda studente
2. Tab Percorso
3. Assegna settore primario
4. Salva

### Coach non assegnato
1. Dashboard → Filtro Coach → Nessuno
2. Per ogni studente, seleziona coach dal dropdown

### Export Word non funziona
- Verifica che il report sia compilato
- Prova con altro browser
- Contatta supporto tecnico

### Quiz non appare nel report studente
- Usa tool diagnostico `diagnose_quiz_selector.php`
- Verifica courseid corretto
- Controlla che quiz sia completato (state=finished)

### Settore mostra 0 quiz
- Usa tool `diagnose_quiz_competencies.php`
- Verifica mapping competenze→settore
- Controlla che domande abbiano competenze assegnate

---

# PARTE 12: GLOSSARIO

| Termine | Significato |
|---------|-------------|
| **CPURC** | Centro Professionale URC - Programma di reinserimento |
| **URC** | Ufficio Regionale di Collocamento |
| **FTM** | Fondazione Terzo Millennio |
| **Settore Primario** | Settore principale, determina quiz e autovalutazione |
| **Multi-Settore** | Sistema che permette 3 settori per studente |
| **Gap Analysis** | Confronto autovalutazione vs performance |
| **Student Card** | Scheda completa dello studente |
| **Badge** | Etichetta colorata (es. settore, settimana) |

---

# PARTE 13: CHECKLIST OPERATIVE

## Nuovo Studente

- [ ] Importa da CSV o crea manualmente
- [ ] Verifica dati anagrafici
- [ ] Assegna settore primario
- [ ] Assegna coach
- [ ] Verifica date percorso

## Fine Percorso Studente

- [ ] Coach ha finalizzato report
- [ ] Export Word del report
- [ ] Stampa documento
- [ ] Invio a URC
- [ ] Aggiorna stato a "Chiuso"

## Export Mensile

- [ ] Filtra per periodo
- [ ] Export Excel completo
- [ ] Export Word ZIP report completi
- [ ] Archivia documenti

---

# CONTATTI SUPPORTO

Per problemi tecnici:
- Server Test: https://test-urc.hizuvala.myhostpoint.ch
- Documentazione: `/docs/manuali/`

---

*Documento generato il 9 Febbraio 2026 - FTM Plugins v5.1*
*Fondazione Terzo Millennio - Sistema di Gestione Competenze*
