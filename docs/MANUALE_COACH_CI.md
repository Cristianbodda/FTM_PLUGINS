# MANUALE COACH — COACHING INDIVIDUALIZZATO (CI)
### Fondazione Terzo Millennio — FTM Academy
**Versione 1.0 — Maggio 2026**

---

## INDICE

1. [Cos'è il Coaching Individualizzato](#1-cosè-il-coaching-individualizzato)
2. [Accesso alla scheda CI dello studente](#2-accesso-alla-scheda-ci-dello-studente)
3. [Fase 0 — Valutazione Idoneità (Griglia PCI)](#3-fase-0--valutazione-idoneità-griglia-pci)
4. [Fase 1 — Attivazione del CI](#4-fase-1--attivazione-del-ci)
5. [Tab: Accettazione — Il modulo di accordo iniziale](#5-tab-accettazione--il-modulo-di-accordo-iniziale)
6. [Tab: Piano d'Azione — Il cuore del CI](#6-tab-piano-dazione--il-cuore-del-ci)
7. [Tab: Diario Coaching — Incontri e Azioni](#7-tab-diario-coaching--incontri-e-azioni)
8. [Tab: Calendario — Appuntamenti](#8-tab-calendario--appuntamenti)
9. [Tab: Tracking Settimanale — La matrice completa](#9-tab-tracking-settimanale--la-matrice-completa)
10. [Tab: KPI & Ricerche — I numeri](#10-tab-kpi--ricerche--i-numeri)
11. [Tab: Roadmap — La visione d'insieme](#11-tab-roadmap--la-visione-dinsieme)
12. [Guida Settimana per Settimana](#12-guida-settimana-per-settimana)
13. [Le 12 Aree di Attivazione — Guida dettagliata](#13-le-12-aree-di-attivazione--guida-dettagliata)
14. [Chiusura del CI](#14-chiusura-del-ci)

---

## 1. Cos'è il Coaching Individualizzato

Il CI è un percorso strutturato di **10 settimane** dedicato agli studenti PCI (Partecipanti al Corso di Inserimento) che hanno dimostrato un potenziale di collocamento. L'obiettivo è supportare lo studente in modo sistematico nella ricerca attiva di lavoro.

Il percorso è organizzato in:
- **6 fasi** progressive (analisi → orientamento → attivazione → rafforzamento → contatto mercato → consolidamento)
- **10 settimane** con attività settimanali misurabili
- **12 aree di attivazione** (10 quantitative + 2 qualitative) su cui il coach e lo studente lavorano insieme

### Il semaforo di idoneità
Prima di attivare il CI, ogni studente viene valutato su **6 criteri** con punteggio da **1 a 6** ciascuno (totale massimo 36):

| Colore | Punteggio | Significato |
|--------|-----------|-------------|
| 🔴 Rosso | 0-20 | Non idoneo al CI |
| 🟠 Arancione | 21-28 | Idoneo al CI |
| 🟢 Verde | 29-36 | Idoneo prioritario |

---

## 2. Accesso alla scheda CI dello studente

### Dalla Coach Dashboard V2
1. Vai su **Coach Dashboard V2** → `/local/coachmanager/coach_dashboard_v2.php`
2. Trova lo studente nella lista
3. Se ha già un CI attivo vedrai il badge verde **"CI S.X/10"** accanto al suo nome
   - Clicca il pulsante **"📝 CI"** (verde) che appare nella riga dello studente
4. Se non ha ancora un CI vedrai il badge grigio **"CI ✎"** oppure nessun badge
   - Clicca su **"Attiva CI"** (disponibile nel menu azioni dello studente) oppure apri la scheda manualmente

### URL diretto della scheda CI
```
/local/ftm_sip/sip_student.php?userid=USERID
```
Sostituisci `USERID` con l'ID numerico dello studente.

### Dashboard CI (tutti gli studenti)
Per vedere tutti gli studenti CI in un'unica schermata:
```
/local/ftm_sip/sip_dashboard.php
```
Qui trovi:
- Statistiche globali (attivi, completati, bozze, appuntamenti prossimi 7 giorni)
- Tabella studenti con stato, settimana attuale, KPI rapidi
- Filtri per stato: Tutti / Attivi / Completati / Sospesi
- Link agli appuntamenti imminenti

---

## 3. Fase 0 — Valutazione Idoneità (Griglia PCI)

Questa fase avviene **prima** di aprire la scheda CI dello studente, tipicamente durante o subito dopo il colloquio di orientamento.

### Come aprire il modale di valutazione
1. Vai nella **Coach Dashboard V2**
2. Trova lo studente nel pannello
3. Clicca su **"Valuta Idoneità CI"** o sul pulsante di attivazione CI (apre il modale)

### Cosa compilare nel modale

Il modale ha **due sezioni**:

#### Sezione 1 — Griglia di Valutazione (6 criteri)
Per ogni criterio assegna un punteggio da **1** (minimo) a **6** (massimo):

| # | Criterio | Cosa valuta |
|---|----------|-------------|
| 1 | **Motivazione** | Quanto è motivato attivamente a trovare lavoro |
| 2 | **Chiarezza dell'obiettivo** | Ha un obiettivo professionale chiaro e realistico |
| 3 | **Occupabilità** | Competenze, esperienza, presentazione personale |
| 4 | **Autonomia** | Capacità di agire in autonomia senza assistenza continua |
| 5 | **Bisogno di coaching** | Quanto beneficerà del percorso CI |
| 6 | **Comportamento** | Affidabilità, puntualità, atteggiamento |

> **Il punteggio totale viene calcolato automaticamente** e il semaforo si aggiorna in tempo reale.

#### Sezione 2 — Dati di Attivazione
- **Motivazione (testo)**: Nota descrittiva sul perché si attiva il CI
- **Data di inizio**: Data a partire dalla quale inizia il percorso di 10 settimane
- **Giorni LADI**: Numero di giornate LADI disponibili (campo **obbligatorio** per attivare)

### Salva come bozza vs Attiva
- **"Salva Bozza"**: Salva la valutazione senza attivare il CI (puoi completare dopo)
- **"Attiva CI"**: Attiva il percorso → crea l'enrollment ufficiale → genera il piano delle 12 aree → la scheda CI diventa accessibile

> ⚠️ **Senza il numero di giornate LADI il CI non può essere attivato.**

---

## 4. Fase 1 — Attivazione del CI

Una volta cliccato **"Attiva CI"** nel modale, il sistema:
1. Crea il record di iscrizione (`local_ftm_sip_enrollments`) con stato **Attivo**
2. Genera automaticamente il **Piano d'Azione** con le 12 aree (con valori di default)
3. Genera i record di **Accettazione** per le 12 aree
4. Collega il coach attuale come responsabile del percorso
5. Calcola la **data di fine pianificata** (data inizio + 70 giorni)

Dopo l'attivazione, cliccando sulla scheda CI dello studente troverai **7 tab**:

| Tab | Scopo |
|-----|-------|
| **Accettazione** | Modulo accordo iniziale coach-studente |
| **Piano d'Azione** | Tracker settimanale + dettaglio 12 aree |
| **Diario Coaching** | Registro incontri + azioni assegnate |
| **Calendario** | Appuntamenti futuri |
| **KPI & Ricerche** | Numeri aggregati e Foglio URC |
| **Tracking Settimanale** | Matrice riassuntiva completa |
| **Roadmap** | Visualizzazione grafica del percorso |

---

## 5. Tab: Accettazione — Il modulo di accordo iniziale

**Quando usarlo:** Prima settimana. Compilare insieme allo studente nel primo incontro.

### Struttura del tab
Il tab mostra una **tabella Excel-like** con le 12 aree di attivazione. Per ogni area devi definire:

| Colonna | Significato |
|---------|-------------|
| **Area** | Nome dell'area di attivazione |
| **Accettata** | Checkbox: lo studente accetta di lavorare su questa area |
| **Baseline** | Quante attività fa attualmente (punto di partenza) |
| **Target totale** | Obiettivo da raggiungere nelle 10 settimane |
| **Baseline/sett.** | Attività settimanali attuali |
| **Target/sett.** | Obiettivo settimanale |
| **Effettivo** | (calcolato auto dal sistema) Quanto ha fatto realmente |

### Come compilare
1. Clicca su una cella della riga per modificarla
2. Per ogni area decidi con lo studente se includerla (spunta la checkbox)
3. Inserisci la baseline (es. "attualmente manda 2 CV a settimana")
4. Inserisci il target (es. "obiettivo: 10 CV a settimana")
5. Il sistema calcola automaticamente baseline/sett. e target/sett.
6. Clicca **"Salva"** in fondo alla tabella

> **Nota:** I valori di target vengono copiati automaticamente nel Piano d'Azione. Non è necessario ridefinirli lì.

---

## 6. Tab: Piano d'Azione — Il cuore del CI

**Quando usarlo:** Ogni settimana, per ogni incontro di coaching.

### Struttura del tab
Il Piano d'Azione mostra le **12 aree** in forma di schede espandibili, ordinate per settimane di attivazione.

#### Per ogni area trovi:
- **Badge colorato** con il nome dell'area
- **Settimane di riferimento** (es. "Sett. 1-4")
- **Barra di progresso** rispetto al target
- **Bottoni W1 → W10** + **Totale**: mostrano quante attività sono state registrate in ciascuna settimana (numero in verde = dati presenti)
- **Bottone "+" / "Apri"**: espande il pannello inline per aggiungere dati

### Come registrare un'attività settimanale

#### Per le aree quantitative (8 aree)
1. Clicca sul **bottone della settimana** (es. "W3") oppure sul **"+"** della scheda area
2. Si apre un pannello inline con il form di inserimento
3. Compila i campi (variano per area — vedi sezione 13)
4. Clicca **"Aggiungi"**
5. Il contatore sul bottone della settimana si aggiorna immediatamente

#### Per l'area "Canali di Ricerca"
1. Clicca sul **bottone della settimana** desiderata
2. Si apre la griglia dei **18 canali** (LinkedIn, Job-Room, Email, ecc.)
3. Per ogni canale attivato per la prima volta quella settimana, clicca il **toggle** (diventa verde)
4. Il contatore si aggiorna automaticamente
5. ⚠️ Un canale attivato **non può essere spostato** in un'altra settimana

#### Per le aree qualitative (Miglioramento Strategia / Autonomia Crescente)
1. Clicca sul bottone della settimana
2. Si apre il form con **slider 1-10** (valutazione del coach)
3. Aggiungi eventuali note
4. Clicca **"Salva valutazione"**

### Il Radar delle Aree
In cima al tab Piano trovi il **radar SVG** con 12 assi che mostra:
- **Poligono grigio tratteggiato**: livello iniziale (compilato nel form Accettazione)
- **Poligono teal pieno**: livello attuale calcolato automaticamente dal tracking

Il radar si aggiorna ogni volta che vengono aggiunti dati nel tracker.

---

## 7. Tab: Diario Coaching — Incontri e Azioni

**Quando usarlo:** Dopo ogni incontro con lo studente (da registrare entro 24h).

### Registrare un incontro

1. Clicca **"+ Nuovo Incontro"** in cima al tab
2. Compila il form:
   - **Data incontro**
   - **Durata** (in minuti)
   - **Modalità**: In presenza / Remoto / Telefono / Email
   - **Settimana CI** (calcolata automaticamente)
   - **Sintesi**: cosa è emerso durante l'incontro
   - **Note aggiuntive**
3. Clicca **"Salva Incontro"**

### Aggiungere azioni (task) a un incontro

Dopo aver salvato un incontro:
1. Nell'elenco degli incontri trovi il bottone **"+ Azione"** accanto a ciascun incontro
2. Clicca per aprire il form azione
3. Compila:
   - **Descrizione** dell'azione assegnata allo studente
   - **Scadenza**
   - **Stato iniziale**: In attesa / In corso
4. Salva

### Aggiornare lo stato di un'azione
1. Nell'elenco delle azioni clicca sul **menu a tendina dello stato**
2. Seleziona: In attesa / In corso / Completata / Non eseguita
3. Lo stato viene salvato automaticamente

---

## 8. Tab: Calendario — Appuntamenti

**Quando usarlo:** Per pianificare i prossimi incontri con lo studente.

### Creare un appuntamento
1. Clicca **"+ Nuovo Appuntamento"**
2. Compila:
   - **Data e ora** dell'appuntamento
   - **Durata** (minuti)
   - **Modalità**: In presenza / Remoto / Telefono / Email
   - **Luogo** (se in presenza)
   - **Argomento / Topic**
3. Clicca **"Salva"**
4. Il sistema invia automaticamente una notifica allo studente

### Stati degli appuntamenti
- **Pianificato** → **Confermato** → **Completato**
- **Annullato** / **No-show** (studente assente)

> Gli appuntamenti dei prossimi 7 giorni appaiono anche nella **Dashboard CI principale**.

---

## 9. Tab: Tracking Settimanale — La matrice completa

**Quando usarlo:** Per avere una visione d'insieme dell'avanzamento su tutte le aree.

### Struttura
Il tab mostra una **matrice 12×11** (12 aree × 10 settimane + totale). Ogni cella mostra:
- Il **numero di attività** registrate in quella settimana per quell'area
- Sfondo **teal chiaro** se ci sono dati
- Sfondo **grigio** se l'area non è ancora attiva in quella settimana
- Sfondo **bianco** = area attiva ma nessun dato ancora

### Vedere il dettaglio di una cella
1. Clicca su una cella con dati (numero > 0)
2. La pagina si aggiorna mostrando l'**elenco dettagliato** delle voci per quell'area/settimana
3. Per le aree quantitative: lista delle entry con azienda, metodo, risultato
4. Per i canali di ricerca: lista dei canali attivati quella settimana
5. Per le aree qualitative: la valutazione coach della settimana

---

## 10. Tab: KPI & Ricerche — I numeri

**Quando usarlo:** Revisione settimanale/mensile per monitorare i progressi complessivi.

### KPI principali mostrati
- **Candidature totali** inviate
- **Candidature con colloquio** ottenuto
- **Contatti aziende** effettuati
- **Contatti positivi**
- **Opportunità generate**
- **Incontri di coaching** effettuati
- **Azioni in sospeso**
- **Documenti caricati** (prove Job-Room)

### Upload prove documentali (PDF Job-Room)
In questo tab trovi anche la sezione **"Documenti caricati"**:
1. Clicca **"Carica documento"**
2. Seleziona il PDF/JPG esportato da Job-Room
3. Il file viene salvato associato all'enrollment

**Funzione AI di analisi (se configurata):**
1. Dopo aver caricato i PDF, clicca **"Analizza con AI"**
2. Il sistema chiama OpenAI per estrarre automaticamente le ricerche dal documento
3. Viene mostrata un'anteprima delle entry estratte
4. Clicca **"Importa"** per inserirle automaticamente nel Foglio URC (area `mandatory_searches`)

---

## 11. Tab: Roadmap — La visione d'insieme

**Quando usarlo:** Per mostrare allo studente dove si trova nel percorso.

### Contenuto
- **Timeline visuale** delle 6 fasi con stato (completata / corrente / futura)
- **Indicatore settimana attuale** evidenziato
- **Note per fase**: il coach può inserire osservazioni su ogni fase
- **Data inizio / data fine pianificata**
- **Semaforo idoneità** con il punteggio della griglia PCI

### Aggiungere note a una fase
1. Clicca sull'icona matita accanto alla fase
2. Digita le note
3. Clicca **"Salva"**

---

## 12. Guida Settimana per Settimana

### SETTIMANA 1 — Analisi e orientamento (Fase 1)

**Obiettivo:** Definire il punto di partenza e stipulare l'accordo CI.

**Cosa fare:**

1. **Apri la scheda CI** dello studente
2. **Tab Accettazione** → Compila insieme allo studente la tabella delle 12 aree
   - Definite insieme baseline e target per ogni area
   - Spunta le aree che verranno attivate
   - Clicca **"Salva"**
3. **Tab Piano d'Azione** → Verifica che i target siano stati recepiti correttamente
4. **Area: Creare lista aziende target** (attiva dalla settimana 1)
   - Clicca sul bottone **W1** dell'area "Lista Aziende Target"
   - Inserisci le prime aziende identificate insieme allo studente
   - Per ogni azienda: nome, settore, città, commenti
5. **Area: Foglio URC / Ricerche obbligatorie** (attiva dalla settimana 1)
   - Clicca **W1** dell'area "Ricerche Obbligatorie"
   - Registra le ricerche già effettuate nella settimana
6. **Tab Diario Coaching** → Registra l'incontro di questa settimana
7. **Tab Calendario** → Pianifica il prossimo appuntamento
8. **Tab Roadmap** → Aggiungi una nota sulla fase 1

---

### SETTIMANA 2 — Costruzione strategia (Fase 2)

**Obiettivo:** Identificare la strategia di ricerca e attivare i primi canali.

**Cosa fare:**

1. **Tab Piano d'Azione**:
   - **Area: Foglio URC** → Registra le ricerche della settimana (clicca **W2**)
   - **Area: Lista Aziende Target** → Aggiorna/espandi la lista (clicca **W2**)
   - **Area: Canali di Ricerca** (si attiva dalla settimana 2!)
     - Clicca **W2** dell'area "Canali di Ricerca"
     - Si apre la griglia dei 18 canali
     - Attiva i canali che lo studente utilizzerà (es. LinkedIn, Job-Room, Email)
     - Clicca il toggle per ciascun canale → diventa verde
2. **Tab Diario Coaching** → Registra l'incontro + assegna azioni per la settimana
   - Es. "Creare profilo LinkedIn completo" (scadenza: entro settimana 3)
   - Es. "Inviare 5 candidature questa settimana"
3. **Tab Calendario** → Pianifica prossimo appuntamento

---

### SETTIMANE 3-4 — Attivazione ricerca (Fase 3)

**Obiettivo:** Lo studente inizia attivamente a candidarsi su più fronti.

**Cosa fare ogni settimana:**

1. **Tab Piano d'Azione**:
   - **Foglio URC** (W3/W4) → Inserisci le ricerche della settimana
   - **Canali di Ricerca** (W3/W4) → Aggiungi nuovi canali se attivati
   - **Social Network** (si attiva dalla settimana 3!)
     - Clicca **W3** di "LinkedIn / Facebook"
     - Registra le attività su social (profilo aggiornato, contatti, ecc.)
   - **Rete Personale** (si attiva dalla settimana 3!)
     - Clicca **W3** di "Rete Personale"
     - Registra i contatti attivati (ex colleghi, conoscenti, ecc.)
   - **Candidature ad Annunci** (si attiva dalla settimana 3!)
     - Clicca **W3** di "Candidature Mirate"
     - Per ogni candidatura: azienda, posizione, metodo (email/online/persona), risultato (in attesa/positivo/negativo)
   - **Autocandidature** (si attiva dalla settimana 3!)
     - Clicca **W3** di "Autocandidature"
     - Registra le candidature spontanee inviate
2. **Tab Diario Coaching** → Registra incontro + azioni
3. **Tab Calendario** → Pianifica prossimo incontro
4. **Tab Tracking Settimanale** → Controlla la matrice per vedere i progressi complessivi

---

### SETTIMANE 5-6 — Rafforzamento strategia (Fase 4)

**Obiettivo:** Analizzare risultati delle prime settimane e rafforzare i punti deboli.

**Cosa fare:**

1. **Tab Tracking Settimanale** → Analizza la matrice
   - Identifica le aree con pochi dati
   - Discuti con lo studente come aumentare l'attività
2. **Tab Piano d'Azione**:
   - Continua ad aggiornare tutte le aree già attive (W5/W6)
   - **Agenzie e URC** (si attiva dalla settimana 5!)
     - Clicca **W5** di "Agenzie e Job-Room"
     - Registra contatti con agenzie di collocamento e URC
   - **Training Colloqui** (si attiva dalla settimana 5!)
     - Clicca **W5** di "Preparazione Colloqui"
     - Registra le sessioni di preparazione (simulazioni, feedback)
3. **Tab KPI** → Controlla i numeri aggregati
4. **Tab Diario Coaching** → Registra incontro, aggiorna azioni precedenti
5. **Tab Calendario** → Pianifica prossimo incontro

---

### SETTIMANE 7-8 — Contatto mercato lavoro (Fase 5)

**Obiettivo:** Massimizzare i contatti con il mercato, valutare prime opportunità.

**Cosa fare:**

1. **Tab Piano d'Azione**:
   - Continua ad aggiornare tutte le aree attive (W7/W8)
   - **Stage / Prove Lavorative** (si attiva dalla settimana 7!)
     - Clicca **W7** di "Stage / Prova"
     - Registra eventuali stage, giorni prova, tirocini
   - **Miglioramento Strategia** (si attiva dalla settimana 7 — area qualitativa!)
     - Clicca **W7** di "Miglioramento Strategia"
     - Si apre il form con **slider 1-10** (tua valutazione come coach)
     - Inserisci il punteggio e le note → Salva
   - **Autonomia Crescente** (si attiva dalla settimana 7 — area qualitativa!)
     - Clicca **W7** di "Autonomia Crescente"
     - Slider 1-10 + note → Salva
2. **Tab Diario Coaching** → Registra incontro
3. **Tab KPI** → Verifica progressi, carica PDF Job-Room se lo studente l'ha scaricato

---

### SETTIMANE 9-10 — Consolidamento e chiusura (Fase 6)

**Obiettivo:** Valutare l'esito del percorso e, se necessario, procedere alla chiusura.

**Cosa fare:**

1. **Tab Piano d'Azione** → Aggiorna tutte le aree (W9/W10)
2. **Tab Tracking Settimanale** → Verifica che la matrice sia completa
3. **Tab Roadmap** → Aggiungi nota sulla fase 6 con valutazione finale
4. **Tab Diario Coaching** → Registra l'incontro finale

#### Chiusura del CI (settimana 10)
1. Dalla scheda CI dello studente, scorri in basso fino alla sezione **"Chiudi CI"** (o usa il bottone nell'header)
2. Seleziona il **tipo di esito**:
   - **Assunto** → inserisci nome azienda e data
   - **Altra opportunità** → inserisci descrizione
   - **Continuazione** → il percorso prosegue oltre le 10 settimane
   - **Abbandono** → lo studente ha interrotto
3. Inserisci le **note finali**
4. Clicca **"Chiudi CI"** → lo stato passa a **Completato**

---

## 13. Le 12 Aree di Attivazione — Guida dettagliata

### 1. Lista Aziende Target
- **Settimane attive:** 1-4
- **Target default:** 30 aziende
- **Cosa registrare:** nome azienda, settore, città, email/telefono, persona contatto, note
- **Come:** Clicca W1-W4 → form inline → inserisci azienda → Aggiungi

### 2. Ricerche Obbligatorie (Foglio URC)
- **Settimane attive:** 1-10
- **Target default:** 40 ricerche totali
- **Cosa registrare:** Per ogni ricerca di lavoro effettuata dallo studente:
  - Data, Nome azienda, Indirizzo, Email
  - Tipo occupazione: Tempo pieno / Part-time
  - Metodo: Lettera / Di persona / Telefono
  - URC assegnato: Sì/No
  - Risultato: In attesa / Positivo / Negativo
- **Come:** Clicca W1-W10 → form inline → compila → Aggiungi
- **Import PDF Job-Room:** Carica il PDF nel tab KPI → clicca "Analizza con AI" → importa automaticamente

### 3. Canali di Ricerca
- **Settimane attive:** 2-10
- **Target default:** 15 canali distinti
- **18 canali disponibili:**
  Email, Portali del lavoro, Siti aziendali, Foglio ufficiale, Sito confederazione, LinkedIn, Facebook, Giornali, Job-Room, Registro di commercio, Elenco telefonico, Contatti personali, Agenzie, Bacheche negozi, Mailing-list, Porta a porta, Telefonate, Sindacati
- **Come:** Clicca W2+ → griglia canali → attiva toggle → si blocca (non spostabile)
- **Logica:** Un canale attivato resta associato alla settimana in cui è stato attivato per la prima volta

### 4. Social Network (LinkedIn/Facebook)
- **Settimane attive:** 3-10
- **Target default:** 2 piattaforme
- **Cosa registrare:** aggiornamento profilo, connessioni attivate, messaggi inviati, post pubblicati
- **Come:** Clicca W3+ → form inline → Aggiungi

### 5. Rete Personale
- **Settimane attive:** 3-10
- **Target default:** 10 contatti
- **Cosa registrare:** nome contatto, tipo relazione, azione effettuata (chiamata, email, incontro), risultato
- **Come:** Clicca W3+ → form inline → Aggiungi

### 6. Candidature ad Annunci (Mirate)
- **Settimane attive:** 3-10
- **Target default:** 30 candidature
- **Cosa registrare:** annuncio, azienda, canale, data, stato risposta
- **Come:** Clicca W3+ → form inline → Aggiungi

### 7. Autocandidature
- **Settimane attive:** 3-10
- **Target default:** 10 autocandidature
- **Cosa registrare:** azienda, posizione, metodo, data, risposta
- **Come:** Clicca W3+ → form inline → Aggiungi

### 8. Agenzie e URC
- **Settimane attive:** 5-10
- **Target default:** 5 contatti
- **Cosa registrare:** nome agenzia/URC, contatto, appuntamento, esito
- **Come:** Clicca W5+ → form inline → Aggiungi

### 9. Training Colloqui
- **Settimane attive:** 5-10
- **Target default:** 2 sessioni
- **Cosa registrare:** tipo sessione (simulazione, feedback, preparazione), durata, note
- **Come:** Clicca W5+ → form inline → Aggiungi

### 10. Stage / Prove Lavorative
- **Settimane attive:** 7-10
- **Target default:** 2 esperienze
- **Cosa registrare:** azienda, tipo (stage/prova giornaliera/tirocinio), date, valutazione
- **Come:** Clicca W7+ → form inline → Aggiungi

### 11. Miglioramento Strategia *(qualitativa)*
- **Settimane attive:** 7-10
- **Scala:** 1-10 (valutazione coach)
- **Cosa valutare:** Quanto lo studente ha migliorato la sua strategia di ricerca rispetto all'inizio
- **Come:** Clicca W7+ → slider 1-10 + note → Salva valutazione coach

### 12. Autonomia Crescente *(qualitativa)*
- **Settimane attive:** 7-10
- **Scala:** 1-10 (valutazione coach)
- **Cosa valutare:** Quanto lo studente agisce in autonomia senza bisogno di stimoli del coach
- **Come:** Clicca W7+ → slider 1-10 + note → Salva valutazione coach

---

## 14. Chiusura del CI

### Prerequisiti prima di chiudere
1. Almeno un **incontro registrato** nel Diario Coaching
2. Almeno una **attività nel Foglio URC** (area Ricerche Obbligatorie)
3. Piano d'azione **non più in bozza**
4. Settimana ≥ 9 (o decisione motivata anticipata)

### Esiti possibili
| Codice | Descrizione |
|--------|-------------|
| **Assunto** | Lo studente ha trovato lavoro tramite il percorso CI |
| **Altra opportunità** | Stage, lavoro parziale, formazione avanzata |
| **Continuazione** | Il percorso viene prolungato oltre le 10 settimane |
| **Abbandono** | Lo studente ha interrotto il percorso |

### Come procedere
1. Apri la scheda CI dello studente
2. Clicca **"Chiudi CI"** (bottone nell'header della scheda)
3. Seleziona l'esito, inserisci data effettiva di chiusura e note
4. Conferma → lo stato passa a **Completato** (badge blu)

### Dopo la chiusura
- La scheda rimane accessibile in **sola lettura**
- Tutti i dati sono preservati
- Lo studente non appare più nelle statistiche degli attivi
- Il badge nella Coach Dashboard V2 diventa blu "CI Completato"

---

## RIEPILOGO RAPIDO — Checklist settimanale del coach

```
□ Aprire la scheda CI dello studente
□ Tab Piano d'Azione → aggiornare tutte le aree ATTIVE nella settimana corrente
  □ Foglio URC (ogni settimana, W1-W10)
  □ Canali di Ricerca (dalla W2)
  □ Candidature / Autocandidature (dalla W3)
  □ Agenzie/URC (dalla W5)
  □ Aree qualitative — inserire valutazione coach (dalla W7)
□ Tab Diario Coaching → registrare l'incontro
  □ Aggiornare stato delle azioni precedenti
  □ Aggiungere nuove azioni con scadenza
□ Tab Calendario → pianificare prossimo appuntamento
□ Tab Tracking Settimanale → verificare la matrice globale
□ Tab KPI → controllare i numeri aggregati
```

---

*Manuale generato automaticamente dall'analisi del codice sorgente FTM Academy — Maggio 2026*
