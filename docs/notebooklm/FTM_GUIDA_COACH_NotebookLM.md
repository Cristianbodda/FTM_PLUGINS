# FTM PLUGINS - GUIDA COMPLETA PER COACH
## Documento per NotebookLM - Fondazione Terzo Millennio

**Versione:** 5.1 | **Data:** 9 Febbraio 2026

---

# PARTE 1: INTRODUZIONE AL SISTEMA FTM

## Cos'è il Sistema FTM?

Il sistema FTM (Fondazione Terzo Millennio) è un ecosistema di 13 plugin Moodle sviluppati per gestire il programma CPURC (Centro Professionale URC) in Svizzera. Il sistema permette ai coach di seguire gli studenti durante un percorso formativo di 6 settimane, tracciare le loro competenze professionali e generare report per gli uffici URC.

## Chi sono i Coach FTM?

I coach FTM sono formatori professionali che accompagnano gli studenti durante il loro percorso presso la Fondazione. Attualmente ci sono 4 coach:
- **CB** - Cristian Bodda
- **FM** - Fabio Marinoni
- **GM** - Graziano Margonar
- **RB** - Roberto Bravo

## Il Percorso delle 6 Settimane

Ogni studente segue un percorso strutturato di 6 settimane:
- **Settimana 1:** Accoglienza e valutazione iniziale (FISSO)
- **Settimane 2-5:** Formazione nel settore professionale assegnato
- **Settimana 6:** Valutazione finale e preparazione al reinserimento

## I 7 Settori Professionali

Gli studenti vengono assegnati a uno dei 7 settori:
1. **AUTOMOBILE** - Meccanica auto e veicoli
2. **MECCANICA** - Meccanica di precisione e industriale
3. **LOGISTICA** - Magazzino e spedizioni
4. **ELETTRICITÀ** - Impianti elettrici
5. **AUTOMAZIONE** - Sistemi automatizzati
6. **METALCOSTRUZIONE** - Lavorazione metalli
7. **CHIMFARM** - Settore chimico-farmaceutico

---

# PARTE 2: DASHBOARD COACH V2

## Accesso alla Dashboard

Per accedere alla Dashboard Coach:
1. Accedi a Moodle con le tue credenziali
2. Vai a: `/local/coachmanager/coach_dashboard_v2.php`
3. Oppure cerca "FTM Tools" nel menu laterale

## Interfaccia della Dashboard

La Dashboard è divisa in aree principali:

### Barra Superiore
Contiene tutti i controlli:
- **Filtro Corso:** Filtra studenti per corso Moodle
- **Filtro Gruppo:** Filtra per colore gruppo (Giallo, Grigio, Rosso, Marrone, Viola)
- **Filtro Settimana:** Mostra solo studenti in una specifica settimana (1-6+)
- **Filtro Stato:** Attivo, concluso, tutti
- **Selettore Vista:** Classica, Compatta, Standard, Dettagliata
- **Zoom Accessibilità:** A- (90%), A (100%), A+ (120%), A++ (140%)

### Le 4 Viste Disponibili

1. **Vista Classica (Default)**
   - Tutte le informazioni visibili
   - Card studente complete
   - Timeline espansa
   - Consigliata per analisi dettagliata

2. **Vista Compatta**
   - Card più piccole
   - Solo informazioni essenziali
   - Ideale con molti studenti
   - Panoramica rapida

3. **Vista Standard**
   - Bilanciata tra dettaglio e spazio
   - Uso quotidiano consigliato
   - Compromesso ottimale

4. **Vista Dettagliata**
   - Massimo dettaglio possibile
   - Timeline completamente espansa
   - Per analisi approfondite

### Zoom per Accessibilità

I pulsanti A-, A, A+, A++ cambiano la dimensione di tutta l'interfaccia:
- **A- (90%):** Per vedere più contenuto su schermi piccoli
- **A (100%):** Dimensione normale, default
- **A+ (120%):** Leggibilità migliorata
- **A++ (140%):** Caratteri molto grandi, ideale per utenti 50+

Le preferenze di vista e zoom vengono salvate automaticamente.

## Le Card Studente

Ogni studente appare come una "card" (scheda) con:

### Elementi della Card
- **Pallino colorato stato:** Verde=attivo, Giallo=in pausa, Rosso=concluso
- **Nome completo:** Link cliccabile alla scheda dettagliata
- **Settore:** Badge colorato (es. MECCANICA verde)
- **Settimana corrente:** Numero da 1 a 6+
- **Gruppo colore:** Giallo, Grigio, Rosso, Marrone, Viola
- **Timeline 6 settimane:** Visualizzazione grafica del progresso

### Timeline delle 6 Settimane
- ✓ = Settimana completata
- ● = Settimana corrente
- ○ = Settimana futura
- ⚠ = Settimana con problemi

### Pulsanti Azione sulla Card
- **📝 Note:** Apre il pannello note
- **📄 Report:** Vai alla compilazione report
- **📊 Competenze:** Vai al report competenze
- **📋 Programma:** Vai al programma individuale

---

# PARTE 3: SCHEDA STUDENTE

## I 4 Tab della Scheda

### Tab Anagrafica
Mostra tutti i dati personali dello studente:
- **Dati Personali:** Nome, cognome, data nascita, genere, nazionalità
- **Contatti:** Email, telefono, cellulare
- **Indirizzo:** Via, CAP, città
- **Dati Amministrativi:** Numero AVS, IBAN, stato civile

Questi dati sono in sola lettura per i coach.

### Tab Percorso
Informazioni sul programma FTM:
- **Dati URC:** Numero personale, ufficio URC, consulente
- **Percorso FTM:** Date inizio/fine, misura, stato, grado occupazione
- **Coach Assegnato:** Nome del coach responsabile
- **Settori:** Primario (determina quiz), secondario e terziario (suggerimenti)

### Tab Assenze
Riepilogo delle assenze con codici:
- **X:** Malattia
- **O:** Ingiustificata
- **A:** Permesso
- **B:** Colloquio
- **C:** Corso
- **D-I:** Altri codici specifici
- **TOT:** Totale assenze

Interpretazione:
- Totale < 5: Buona frequenza
- Totale 5-10: Da monitorare
- Totale > 10: Attenzione richiesta

### Tab Stage
Se lo studente ha uno stage aziendale:
- **Dati Stage:** Date inizio/fine, percentuale
- **Azienda:** Nome, indirizzo, funzione
- **Contatto Aziendale:** Referente, telefono, email

---

# PARTE 4: NOTE COACH

## Come Aggiungere Note

Le note permettono di annotare osservazioni sullo studente.

### Dalla Dashboard
1. Clicca sul pulsante **📝 Note** nella card studente
2. Si apre un popup
3. Scrivi la nota
4. Clicca **Salva**

### Dalla Scheda Studente
1. Cerca la sezione **Note Coach**
2. Clicca **Aggiungi Nota**
3. Inserisci il testo
4. Clicca **Salva**

## Visibilità delle Note

| Chi | Può Vedere | Può Modificare |
|-----|-----------|----------------|
| Tu (Coach autore) | Sì | Sì |
| Altri Coach | No | No |
| Segreteria | Sì | No |

**Importante:** Le note sono visibili alla segreteria! Non scrivere informazioni sensibili inappropriate.

---

# PARTE 5: REPORT STUDENTE

## Cos'è il Report

Il Report è il documento finale che descrive il percorso dello studente. Include:
- Valutazione del comportamento
- Competenze tecniche acquisite
- Competenze trasversali
- Raccomandazioni del coach
- Conclusione e prospettive

Il report viene poi stampato, consegnato allo studente e inviato all'URC.

## Accesso al Report

1. **Dalla Dashboard:** Pulsante **📝 Report** sulla card
2. **Dalla Scheda:** Pulsante **📝 Report** in alto
3. **URL diretto:** `/local/ftm_cpurc/report.php?id=X`

## Sezioni del Report

### 1. Valutazione Comportamentale
Descrivi: puntualità, rispetto regole, attitudine al lavoro, relazioni con colleghi, evoluzione durante il percorso.

### 2. Competenze Tecniche
Descrivi: competenze specifiche del settore, strumenti utilizzati, livello raggiunto, aree di forza.

### 3. Competenze Trasversali
Descrivi: comunicazione, problem solving, lavoro di squadra, gestione del tempo, adattabilità.

### 4. Raccomandazioni
Suggerisci: percorsi formativi, aree da migliorare, tipologie di aziende adatte.

### 5. Conclusione
Riassumi il percorso e le prospettive future dello studente.

## Flusso di Lavoro Report

1. **Apri il Report**
2. **Compila le sezioni** (puoi fare più sessioni)
3. **Salva Bozza** (ripeti più volte)
4. **Rileggi e verifica**
5. **Finalizza Report** (non più modificabile)
6. **Esporta in Word**
7. **Stampa/Invia**

**Attenzione:** Una volta finalizzato, il report NON può più essere modificato!

---

# PARTE 6: REPORT COMPETENZE

## Accesso

1. Dashboard → Studente → **📊 Competenze**
2. URL: `/local/competencymanager/student_report.php?userid=X&courseid=Y`

## Elementi del Report

### Grafico Radar
Visualizzazione circolare delle competenze per area. Ogni asse rappresenta un'area di competenza (A, B, C, ecc.) con il livello da 0% a 100%.

### Doppio Radar (Autovalutazione vs Quiz)
Se lo studente ha completato l'autovalutazione, puoi vedere il confronto tra:
- Come lo studente si valuta (autovalutazione)
- Come ha performato nei quiz (valutazione reale)

### Gap Analysis
Confronto numerico tra autovalutazione e performance:
- **Gap < 10%:** Allineamento (verde) - lo studente si conosce bene
- **Gap 10-30%:** Attenzione (giallo) - qualche discrepanza
- **Gap > 30%:** Critico (rosso) - grande divario da discutere

### Spunti Colloquio
Domande suggerite dal sistema basate sul gap analysis per guidare il colloquio con lo studente.

### Suggerimenti Rapporto
Testi automatici basati sull'analisi dei gap, disponibili in due toni:
- **Formale:** Per il report ufficiale
- **Colloquiale:** Per il colloquio con lo studente

## Filtro Settore

Puoi filtrare le competenze mostrate per settore:
- **Tutti i settori:** Mostra tutto
- **Settore specifico:** Solo competenze di quel settore

Il badge "⭐" indica il settore primario dello studente.

## Configurazione Soglie

Puoi personalizzare le soglie per il gap analysis:
- **Soglia Allineamento:** Default 10% (sotto = allineato)
- **Soglia Gap Critico:** Default 30% (sopra = critico)

## Stampa Report Competenze

1. Clicca **🖨️ Stampa** o **Prepara Stampa**
2. Seleziona le sezioni da includere
3. Seleziona le aree radar da stampare
4. Clicca **Stampa** o Ctrl+P
5. Stampa o salva come PDF

---

# PARTE 7: CALENDARIO FTM SCHEDULER

## Accesso

URL: `/local/ftm_scheduler/index.php`

## Viste Disponibili

### Vista Settimanale
Mostra una settimana alla volta con:
- 5 giorni (Lunedì-Venerdì)
- 4 slot orari per giorno (AM1, AM2, PM1, PM2)
- Attività colorate per gruppo

### Vista Mensile
Panoramica mensile con:
- Tutti i giorni del mese
- Indicatori attività
- Navigazione rapida

## I Gruppi Colore

I gruppi colore identificano tipologie di percorsi:
- **🟡 Giallo:** [Definizione specifica FTM]
- **⬜ Grigio:** [Definizione specifica FTM]
- **🔴 Rosso:** [Definizione specifica FTM]
- **🟤 Marrone:** [Definizione specifica FTM]
- **🟣 Viola:** [Definizione specifica FTM]

## Slot Orari

| Codice | Orario |
|--------|--------|
| AM1 | 08:00 - 10:00 |
| AM2 | 10:15 - 12:15 |
| PM1 | 13:15 - 15:15 |
| PM2 | 15:30 - 17:30 |

---

# PARTE 8: PROGRAMMA INDIVIDUALE STUDENTE

## Cos'è

Il Programma Individuale mostra il calendario personalizzato di uno studente per le 6 settimane del percorso.

## Accesso

1. Dashboard → Card studente → **📋 Programma**
2. Pagina gruppo → **📋 Programma** su ogni membro
3. URL: `/local/ftm_scheduler/student_program.php?userid=X&groupid=Y`

## Struttura

Per ogni settimana (1-6):
- **Settimana 1:** Marcata come "FISSO" (attività standard per tutti)
- **Settimane 2-6:** Attività personalizzabili

Per ogni giorno e slot:
- Nome attività
- Dettagli aggiuntivi
- Location/Aula

## Export

Il programma può essere esportato in:
- **Excel:** Foglio di calcolo con tutte le settimane
- **PDF:** Documento stampabile

---

# PARTE 9: AUTOVALUTAZIONE STUDENTE

## Come Funziona

Gli studenti completano un'autovalutazione delle loro competenze usando la scala Bloom (1-6):

### Scala Bloom Semplificata
1. **Ricordare:** So di cosa si tratta, ne ho sentito parlare
2. **Capire:** Posso spiegarlo con parole mie
3. **Applicare:** Posso farlo seguendo istruzioni
4. **Analizzare:** Posso identificare problemi e cause
5. **Valutare:** Posso giudicare se è fatto bene
6. **Creare:** Posso inventare nuovi modi di farlo

### Per il Coach
L'autovalutazione permette di:
- Confrontare percezione vs realtà (Gap Analysis)
- Identificare aree di sovrastima/sottostima
- Guidare colloqui più mirati
- Personalizzare il percorso formativo

---

# PARTE 10: FUNZIONALITÀ AVANZATE

## Export Word

Genera documenti Word professionali con:
- Intestazione con logo FTM
- Dati anagrafici completi
- Sezioni del report
- Footer professionale

## Inviti Autovalutazione

Puoi inviare inviti agli studenti per completare l'autovalutazione:
1. Vai alla scheda studente
2. Clicca **Invia Invito Autovalutazione**
3. Lo studente riceve una notifica

## Coach Navigation

Barra di navigazione unificata per spostarsi rapidamente tra:
- Dashboard Coach
- Calendario
- Report Competenze
- Lista Studenti

---

# PARTE 11: RISOLUZIONE PROBLEMI

## Problemi Comuni

### Non vedo nessuno studente
- Verifica i filtri attivi
- Clicca "Tutti" in ogni filtro per resettare
- Verifica di avere studenti assegnati

### La pagina è troppo piccola/grande
- Usa i pulsanti zoom (A-, A, A+, A++)
- Le preferenze vengono salvate automaticamente

### Non trovo uno studente specifico
- Usa la ricerca se disponibile
- Controlla che sia nel corso corretto
- Verifica che sia assegnato a te come coach

### Il report non si salva
- Verifica la connessione internet
- Non chiudere la pagina durante il salvataggio
- Contatta supporto se persiste

### Non riesco a modificare il report
- Verifica che non sia già finalizzato
- Controlla di essere il coach assegnato
- Un report finalizzato richiede intervento admin

### Il quiz non appare nel report
- Verifica che lo studente abbia completato il quiz
- Controlla di usare il courseid corretto
- Verifica che le domande abbiano competenze assegnate

---

# PARTE 12: GLOSSARIO

| Termine | Significato |
|---------|-------------|
| **CPURC** | Centro Professionale URC |
| **URC** | Ufficio Regionale di Collocamento |
| **FTM** | Fondazione Terzo Millennio |
| **Settore Primario** | Settore principale dello studente, determina quiz e autovalutazione |
| **Gap Analysis** | Confronto tra autovalutazione e performance reale |
| **Bloom** | Scala di valutazione competenze (1-6) |
| **Card** | Scheda riepilogativa studente nella dashboard |
| **Timeline** | Visualizzazione grafica delle 6 settimane |

---

# CONTATTI SUPPORTO

Per problemi tecnici o domande sul sistema:
- Server Test: https://test-urc.hizuvala.myhostpoint.ch
- Documentazione: `/docs/manuali/`

---

*Documento generato il 9 Febbraio 2026 - FTM Plugins v5.1*
*Fondazione Terzo Millennio - Sistema di Gestione Competenze*
