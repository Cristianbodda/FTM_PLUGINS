# Report Tecnico-Istituzionale
# Sistema Informatizzato per il Sostegno Individuale Personalizzato (SIP)

**Destinatario:** Ufficio delle Misure Attive (UMA) - Sezione del Lavoro
**Emittente:** Fondazione Terzo Millennio
**Versione Sistema:** 1.2.0
**Data:** Marzo 2026

---

## 1. INTRODUZIONE E SCOPO DEL DOCUMENTO

Il presente documento descrive il sistema informatizzato sviluppato dalla Fondazione Terzo Millennio per la gestione del **Sostegno Individuale Personalizzato (SIP)**, un percorso di accompagnamento della durata di 10 settimane destinato alle Persone in Cerca di Impiego (PCI) che, al termine della fase di Rilevamento delle Competenze (6 settimane), presentano potenziale concreto di inserimento nel settore industriale.

Il sistema e stato progettato per:
- garantire la **tracciabilita completa** del percorso di ogni PCI;
- formalizzare la **valutazione di idoneita** secondo criteri oggettivi e misurabili;
- monitorare l'**attivazione progressiva** della PCI nella ricerca di impiego;
- documentare gli **esiti finali** e l'efficacia della misura;
- produrre **reportistica istituzionale** e dati aggregati per il committente.

---

## 2. INQUADRAMENTO METODOLOGICO

### 2.1 Collegamento con il Rilevamento delle Competenze

Il SIP non e un percorso autonomo: nasce dai risultati della fase di Rilevamento delle Competenze (6 settimane). Il sistema informatizzato garantisce questa continuita attraverso:

- **Integrazione dati**: il sistema legge automaticamente dal database FTM i risultati del Passaporto Tecnico dello studente (punteggi quiz, autovalutazione, valutazione coach, settore rilevato);
- **Settore di riferimento**: il settore professionale individuato durante il rilevamento viene ereditato automaticamente nel percorso SIP;
- **Preparazione anticipata**: il coach puo iniziare a compilare il Piano d'Azione SIP gia durante le ultime settimane del rilevamento (stato "bozza"), creando un ponte operativo tra le due fasi;
- **Baseline congelata**: al momento dell'attivazione formale del SIP, i livelli iniziali di attivazione vengono congelati come baseline non modificabile, garantendo l'integrita del confronto pre/post intervento.

### 2.2 Fondamento Metodologico

Il sistema implementa fedelmente il modello descritto nel documento concettuale del SIP:

- **7 aree di attivazione** corrispondenti ai canali di ricerca di impiego;
- **Scala di attivazione 0-6** che misura il livello di utilizzo (non la competenza tecnica);
- **Roadmap in 6 fasi** distribuite sulle 10 settimane;
- **Piano d'Azione individualizzato** con obiettivi, azioni concordate e indicatori di verifica;
- **4 indicatori chiave di prestazione** (candidature, contatti, opportunita, evoluzione livelli);
- **Colloqui regolari** con registrazione strutturata e assegnazione azioni.

---

## 3. PROCESSO DI AMMISSIONE: GRIGLIA VALUTAZIONE PCI

### 3.1 Criteri di Idoneita

L'ammissione al SIP avviene attraverso una **Griglia di Valutazione PCI** strutturata, compilata dal coach al termine del periodo di rilevamento. La griglia prevede **6 criteri**, ciascuno valutato su scala **1-5**:

| # | Criterio | 1 (minimo) | 3 (intermedio) | 5 (massimo) |
|---|----------|------------|----------------|-------------|
| 1 | **Motivazione** | Passivo / poco coinvolto | Partecipa ma senza iniziativa | Proattivo, coinvolto, orientato al risultato |
| 2 | **Chiarezza Obiettivo** | Nessun obiettivo | Obiettivo generico | Obiettivo chiaro e realistico |
| 3 | **Occupabilita** | Molto bassa | Media | Alta (profilo spendibile rapidamente) |
| 4 | **Autonomia** | Totalmente dipendente | Parzialmente autonomo | Autonomo e organizzato |
| 5 | **Bisogno Coaching** | Non necessario | Utile ma non essenziale | Altamente necessario per sblocco |
| 6 | **Comportamento** | Assenze / scarso impegno | Adeguato | Eccellente (puntuale, attivo, collaborativo) |

Sono selezionabili anche i valori intermedi **2** e **4**.

### 3.2 Calcoli e Decisione

- **Totale**: somma automatica dei 6 punteggi (range 6-30)
- **Decisione**: **sempre manuale** da parte del coach o della segreteria (Idoneo / Non Idoneo / In attesa)
- **Raccomandazione coach**: campo consultivo (Attivare SIP / Non attivare / Rinvio ad altra misura)
- **Rinvio**: se la PCI viene indirizzata ad un'altra misura, il campo specifico documenta quale

### 3.3 Garanzie di Processo

- La valutazione e registrata nel sistema con data, autore e punteggi dettagliati;
- La segreteria puo approvare formalmente la valutazione (campo approvazione con data e responsabile);
- La decisione non e automatizzata: il sistema non blocca l'attivazione in base al punteggio, lasciando al professionista la valutazione finale;
- La griglia e esportabile nel report finale individuale.

---

## 4. IL PIANO D'AZIONE: 7 AREE DI ATTIVAZIONE

### 4.1 Struttura

Il cuore del SIP e il Piano d'Azione personalizzato, articolato su 7 aree:

| Area | Descrizione | Indicatore di Verifica |
|------|-------------|----------------------|
| **Strategia Professionale** | Chiarezza della PCI su ruolo, settore e aziende target | Elenco aziende target definito |
| **Monitoraggio Annunci** | Capacita di individuare annunci pertinenti | Numero annunci analizzati |
| **Candidature Mirate** | Risposta a posizioni aperte | Numero candidature inviate |
| **Autocandidature** | Iniziativa verso aziende senza annunci pubblici | Numero aziende contattate |
| **Contatto Diretto Aziende** | Capacita di attivare contatti diretti | Numero contatti diretti |
| **Rete Personale e Professionale** | Utilizzo della propria rete di conoscenze | Opportunita generate |
| **Intermediari Mercato Lavoro** | Utilizzo di URC e agenzie di collocamento | Numero contatti attivati |

### 4.2 Scala di Attivazione (0-6)

La scala misura il **livello di utilizzo** di ciascun canale di ricerca, non le competenze tecniche:

| Livello | Significato |
|---------|------------|
| 0 | La PCI non conosce questo ambito o non lo ha mai utilizzato |
| 1 | Conoscenza molto limitata |
| 2 | Utilizzo occasionale |
| 3 | Utilizzo minimo ma presente |
| 4 | Utilizzo regolare |
| 5 | Utilizzo attivo e strutturato |
| 6 | Utilizzo strategico e autonomo |

### 4.3 Meccanismo Baseline / Evoluzione

- **Livello Iniziale (Baseline)**: fissato all'inizio del SIP, non modificabile successivamente;
- **Livello Attuale**: aggiornato dal coach durante gli incontri;
- **Delta**: calcolato automaticamente (attuale - iniziale), visualizzato come indicatore di progresso;
- **Grafico Radar**: visualizzazione SVG sovrapposta che confronta baseline e stato attuale;
- **Storico**: ogni modifica del livello attuale viene registrata con data, autore e motivazione.

---

## 5. ROADMAP: 6 FASI SU 10 SETTIMANE

| Fase | Settimane | Obiettivo | Attivita Principali |
|------|-----------|-----------|---------------------|
| 1 | 1 | Analisi e Orientamento | Analisi risultati Passaporto Tecnico, definizione profilo professionale, identificazione aziende target |
| 2 | 2 | Costruzione della Strategia | Valutazione livello di attivazione, costruzione piano d'azione, definizione obiettivi di ricerca |
| 3 | 3-4 | Attivazione della Ricerca | Monitoraggio annunci, invio candidature, autocandidature, attivazione rete personale |
| 4 | 5-6 | Rafforzamento della Strategia | Follow-up con aziende, contatti diretti, attivazione intermediari del mercato del lavoro |
| 5 | 7-8 | Contatto con il Mercato del Lavoro | Preparazione colloqui, contatti con aziende, valutazione stage o giorni di prova |
| 6 | 9-10 | Consolidamento e Valutazione | Monitoraggio progressi, revisione piano d'azione, definizione passi successivi |

Per ogni fase il sistema traccia:
- **Note del coach** specifiche alla fase;
- **Obiettivi raggiunti** (checkbox);
- **Indicatore qualita** (verde/giallo/rosso basato sulla presenza di incontri nella fase).

---

## 6. MONITORAGGIO DELL'ATTIVITA DI COACHING

### 6.1 Diario Coaching

Ogni incontro tra coach e PCI viene registrato con:
- Data, durata e modalita (presenza / remoto / telefono / email);
- Riepilogo sintetico e note dettagliate del coach;
- Settimana SIP in cui si e svolto (calcolata automaticamente);
- Azioni assegnate alla PCI con scadenze.

### 6.2 Azioni Assegnate

Le azioni concordate durante gli incontri sono tracciate individualmente:
- Descrizione dell'azione;
- Scadenza;
- Stato progressivo: In attesa → In corso → Completata / Non svolta;
- Possibilita per la PCI di segnare autonomamente il completamento;
- Notifiche automatiche di promemoria.

### 6.3 Calendario Appuntamenti

Il sistema gestisce la programmazione degli incontri:
- Fissazione appuntamento con data, ora, durata, modalita e luogo;
- Notifica automatica alla PCI alla creazione dell'appuntamento;
- Promemoria automatico 1 giorno prima;
- Gestione stati: programmato → confermato → completato / annullato / assente;
- Creazione automatica della bozza di incontro nel diario quando un appuntamento viene segnato come completato.

---

## 7. INDICATORI CHIAVE DI PRESTAZIONE (KPI)

### 7.1 I 4 KPI del SIP

Coerentemente con il documento concettuale, il sistema traccia:

**1. Candidature inviate**
- Numero totale di candidature inviate durante il percorso;
- Dettaglio: azienda, posizione, data, tipo (mirata / autocandidatura), stato risposta;
- Sotto-indicatori: quante hanno portato a un colloquio.

**2. Contatti con aziende**
- Numero di aziende contattate direttamente;
- Tipologia di contatto: telefono, visita, email, LinkedIn, rete personale;
- Esito: positivo / neutro / negativo;
- Persona contattata.

**3. Opportunita attivate**
- Numero di opportunita concrete generate;
- Tipologia: colloquio, giorno di prova, stage, guadagno intermedio, formazione, assunzione;
- Stato: pianificata → in corso → completata / annullata.

**4. Evoluzione del livello di attivazione**
- Variazione nelle 7 aree del piano d'azione;
- Visualizzazione tramite grafico radar sovrapposto (baseline vs attuale);
- Storico delle variazioni con date e autore.

### 7.2 Inserimento Dati

I KPI possono essere inseriti:
- **dal coach** durante o dopo gli incontri;
- **dalla PCI stessa** attraverso l'area self-service (se abilitata dal coach).

Ogni inserimento alimenta automaticamente il **Registro Aziende condiviso**, costruendo nel tempo una base dati delle aziende del territorio con storico delle interazioni.

---

## 8. CHIUSURA DEL PERCORSO E CLASSIFICAZIONE ESITI

### 8.1 Prerequisiti di Chiusura

Il sistema impone **4 requisiti obbligatori** prima di poter chiudere un percorso SIP:

| Requisito | Descrizione | Motivazione |
|-----------|-------------|-------------|
| Livelli finali completi | Tutte le 7 aree devono avere il livello attuale assegnato | Garantisce la misurabilita dell'evoluzione |
| Minimo 3 incontri registrati | Almeno 3 incontri devono essere documentati nel diario | Dimostra l'effettivo accompagnamento |
| Esito finale selezionato | L'esito deve essere classificato | Necessario per la rendicontazione |
| Valutazione finale coach | Il coach deve compilare una valutazione scritta | Documenta il giudizio professionale |

Se uno qualsiasi dei requisiti non e soddisfatto, il sistema **blocca la chiusura** e indica esattamente cosa manca.

### 8.2 Classificazione degli Esiti

| Esito | Descrizione |
|-------|-------------|
| **Assunto** | La PCI ha ottenuto un posto di lavoro |
| **Stage** | La PCI e stata inserita in uno stage/tirocinio |
| **Giorno di prova / Tryout** | La PCI ha svolto giorni di prova presso un'azienda |
| **Guadagno intermedio** | La PCI ha attivato un impiego intermedio |
| **Formazione mirata** | La PCI e stata indirizzata a una formazione specifica |
| **Non collocato ma maggiore attivazione** | Nessun inserimento ma miglioramento misurabile dei livelli di attivazione |
| **Interrotto** | Il percorso e stato interrotto anticipatamente (con motivazione obbligatoria) |
| **Non idoneo a prosecuzione** | Valutazione negativa per la prosecuzione (con possibilita di rinvio ad altra misura) |

### 8.3 Dati di Chiusura

Per ogni chiusura vengono registrati:
- Esito principale (dalla classificazione sopra);
- Azienda (se applicabile: nome azienda per assunzione/stage/tryout);
- Data esito;
- Percentuale di impiego (se assunzione);
- Motivo interruzione (se interrotto);
- Rinvio ad altra misura (se applicabile);
- **Valutazione finale del coach** (testo obbligatorio);
- **Prossimi passi raccomandati** (testo).

---

## 9. SISTEMA DI NOTIFICHE E ALERT

Il sistema implementa **7 tipi di notifica** automatica via email e piattaforma Moodle:

| Notifica | Destinatario | Quando | Scopo |
|----------|-------------|--------|-------|
| Nuovo appuntamento | PCI | Alla creazione | Informare la PCI dell'incontro programmato |
| Promemoria appuntamento | PCI | 1 giorno prima | Ridurre le assenze |
| Azioni da completare | PCI | 2 giorni prima della scadenza | Stimolare l'attivazione |
| Aggiornamento piano | PCI | Quando il coach modifica il piano | Mantenere la PCI informata |
| Inattivita studente | Coach | Dopo 7 giorni senza attivita | Identificare PCI a rischio disimpegno |
| Incontro non registrato | Coach | 24h dopo appuntamento completato | Garantire la documentazione |
| Frequenza incontri | Coach | Giovedi/venerdi se nessun incontro nella settimana | Garantire la regolarita del coaching |

Le notifiche vengono elaborate automaticamente dal sistema ogni giorno lavorativo (lunedi-venerdi) alle ore 7:00.

---

## 10. DASHBOARD AGGREGATA E STATISTICHE

### 10.1 Metriche Disponibili

Il sistema produce una dashboard aggregata accessibile alla direzione e ai responsabili, con:

**Indicatori quantitativi:**
- Numero SIP attivati nel periodo;
- Numero completati e tasso di completamento;
- Numero interrotti e tasso di interruzione;
- **Tasso di inserimento** = (assunti + stage + tryout) / completati × 100;

**Distribuzione esiti:**
- Tabella con percentuali per ciascun tipo di esito;
- Visualizzazione con barre proporzionali.

**Evoluzione media dei livelli di attivazione:**
- Per ogni area: media livello iniziale vs media livello finale dei SIP completati;
- Delta medio per area, con indicatore positivo/negativo.

**Performance per coach:**
- Numero studenti per coach;
- Tasso di completamento per coach;
- Tasso di inserimento per coach;
- Media incontri/settimana per coach;
- Numero candidature totali per coach.

### 10.2 Filtri

Tutti i dati sono filtrabili per:
- **Periodo** (data da / data a);
- **Coach** specifico;
- **Settore** professionale.

---

## 11. REPORT FINALE INDIVIDUALE

Per ogni PCI e possibile generare un **documento Word esportabile** contenente 9 sezioni:

1. **Dati PCI**: nome, email, settore, coach, date SIP, durata effettiva;
2. **Valutazione di Idoneita**: griglia PCI con i 6 criteri e punteggi;
3. **Baseline**: livelli iniziali delle 7 aree di attivazione;
4. **Livelli Finali e Progressi**: confronto iniziale/finale con delta per area;
5. **Riepilogo Incontri**: numero totale, ore, frequenza media, modalita prevalente;
6. **Indicatori KPI**: candidature, contatti, opportunita con dettagli;
7. **Esito Finale**: classificazione, azienda, data, dettagli;
8. **Valutazione Finale Coach**: testo della valutazione professionale;
9. **Prossimi Passi**: raccomandazioni per il proseguimento.

---

## 12. GARANZIE DI QUALITA DEL DATO

### 12.1 Controlli di Completezza

Il sistema implementa un **indicatore di qualita** in tempo reale per ogni percorso SIP:

| Criterio | Verde | Giallo | Rosso |
|----------|-------|--------|-------|
| Livelli baseline | Tutti 7 compilati | 4-6 compilati | < 4 compilati |
| Incontri registrati | >= 3 | 1-2 | 0 |
| Voci KPI | Almeno 1 | - | 0 |
| Frequenza incontri | >= 0.8/settimana | 0.4-0.8/settimana | < 0.4/settimana |

### 12.2 Uniformita tra Coach

Il sistema garantisce uniformita metodologica attraverso:
- **Criteri di valutazione standardizzati** (Griglia PCI con descrittori predefiniti);
- **Obiettivi precompilati** per ciascuna area del piano d'azione (personalizzabili ma non vuoti);
- **Requisiti minimi di documentazione** per la chiusura (4 prerequisiti obbligatori);
- **Alert automatici** in caso di mancata documentazione o incontri insufficienti.

### 12.3 Audit Trail

Ogni operazione significativa e tracciata nel database:
- Data e autore di ogni modifica ai livelli di attivazione;
- Storico delle variazioni con livello precedente e successivo;
- Data e autore della valutazione di idoneita e dell'approvazione;
- Timestamp di creazione e modifica di tutti i record.

---

## 13. AREA SELF-SERVICE PER LA PCI

Su decisione del coach, la PCI puo accedere a un'area personale dove:
- **Visualizza** il proprio piano d'azione e i livelli di attivazione (sola lettura);
- **Vede** gli appuntamenti programmati;
- **Segna come completate** le azioni assegnate dal coach;
- **Inserisce autonomamente** candidature inviate, contatti con aziende e opportunita;
- **Monitora** i propri progressi tramite grafico a barre.

La PCI **non puo** modificare i livelli di attivazione ne le note del coach. L'accesso e controllato individualmente dal coach tramite un toggle dedicato.

Questa funzionalita e presentata come **strumento di co-attivazione e responsabilizzazione della PCI**, in linea con l'approccio orientato all'autonomia progressiva descritto nel concetto SIP.

---

## 14. REGISTRO AZIENDE

Il sistema include un registro condiviso delle aziende del territorio che:
- **Cresce automaticamente** con l'uso (ogni candidatura o contatto alimenta il registro);
- **Evita duplicati** tramite normalizzazione dei nomi;
- **Classifica le aziende** per settore professionale (MECCANICA, AUTOMOBILE, etc.);
- **Traccia le interazioni** (quanti studenti hanno contattato ogni azienda);
- Consente ai coach di **coordinare gli approcci** al mercato del lavoro.

---

## 15. ARCHITETTURA TECNICA

### 15.1 Infrastruttura

- **Piattaforma**: Moodle 4.4+ (LMS istituzionale);
- **Plugin**: local_ftm_sip, componente del ecosistema FTM;
- **Database**: 12 tabelle dedicate, 28 chiavi esterne;
- **Sicurezza**: conforme agli standard OWASP (SQL injection protection, XSS prevention, CSRF tokens, capability-based access control);
- **Localizzazione**: completa in italiano e inglese (500+ stringhe).

### 15.2 Integrazione

Il sistema SIP si integra nativamente con:
- **Rilevamento Competenze** (local_competencymanager): lettura risultati quiz, settore, valutazioni;
- **Dashboard Coach** (local_coachmanager): badge SIP visibili nella dashboard principale, filtri dedicati;
- **Calendario FTM** (local_ftm_scheduler): riferimento ai coach e ai gruppi.

---

## 16. CONCLUSIONE

Il sistema informatizzato per il Sostegno Individuale Personalizzato risponde in maniera strutturata e completa alle esigenze operative, metodologiche e istituzionali della misura, garantendo:

- **Tracciabilita**: ogni fase del percorso e documentata e verificabile;
- **Standardizzazione**: la Griglia Valutazione PCI e i requisiti di chiusura garantiscono uniformita tra coach;
- **Misurabilita**: i 4 KPI e l'evoluzione dei 7 livelli di attivazione forniscono dati quantitativi;
- **Rendicontabilita**: la dashboard aggregata e i report individuali esportabili permettono la rendicontazione al committente;
- **Flessibilita**: il sistema supporta la personalizzazione del percorso mantenendo i requisiti minimi di qualita;
- **Continuita**: l'integrazione con il Rilevamento Competenze garantisce un percorso unitario dalla valutazione all'inserimento.
