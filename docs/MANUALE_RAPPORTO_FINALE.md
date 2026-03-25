# Manuale Coach - Rapporto Finale d'Attivita'

## Guida completa alla compilazione e all'esportazione del Rapporto Finale CPURC

---

## 1. Come accedere al Rapporto Finale

### Dalla Coach Dashboard V2

1. Accedere alla piattaforma FTM Academy
2. Dal menu principale, aprire la **Coach Dashboard V2** (`/local/coachmanager/coach_dashboard_v2.php`)
3. Individuare lo studente nella lista
4. Cliccare sul bottone **"Rapporto"** oppure sul nome dello studente per accedere alla sua scheda
5. Dalla scheda studente (Student Card CPURC), cliccare su **"Rapporto finale"**

### Accesso diretto

Se si conosce l'ID dello studente, e' possibile accedere direttamente all'indirizzo:
```
/local/ftm_cpurc/report.php?userid=XX
```
dove `XX` e' l'ID Moodle dello studente.

---

## 2. La barra degli strumenti

In alto nella pagina del rapporto si trova una barra scura con il nome dello studente e quattro bottoni:

| Bottone | Colore | Funzione |
|---------|--------|----------|
| **Stampa** | Grigio | Stampa la pagina corrente dal browser |
| **Salva** | Blu | Salva tutti i dati compilati nel database |
| **Esporta Word** | Verde | Genera e scarica il documento Word ufficiale |
| **Carica dati Aladino** | Arancione | Importa dati da file Excel esportato da Aladino |

---

## 3. Carica dati Aladino (primo passo consigliato)

Prima di compilare manualmente il rapporto, si consiglia di caricare i dati dal sistema Aladino. Questo aggiorna automaticamente le informazioni anagrafiche, le assenze, i colloqui e le date del percorso.

### Come esportare il file da Aladino

1. Accedere al sistema Aladino
2. Cercare lo studente o il gruppo di studenti
3. Esportare i dati in formato **Excel (.xlsx)**
4. Salvare il file sul proprio computer

### Come caricare il file nella piattaforma

1. Cliccare il bottone arancione **"Carica dati Aladino"** nella barra degli strumenti
2. Si apre una finestra modale
3. **Trascinare il file Excel** nell'area tratteggiata, oppure **cliccare** per selezionarlo dal computer
4. Attendere il caricamento: il sistema cerca automaticamente lo studente nel file (per email o nome/cognome)

### Anteprima dei dati

Dopo il caricamento, viene mostrata un'anteprima organizzata in sezioni:

- **Assenze e presenze**: giorni X (presenze tipo X), giorni O (presenze tipo O), assenze A-I, totale assenze, giorni di partecipazione effettiva
- **Percorso**: date inizio/fine, grado occupazione, stato, ultima professione
- **Colloqui e stage**: numero colloqui, data e azienda del colloquio, stage svolti
- **URC**: ufficio URC, consulente, numero personale

I **giorni di partecipazione effettiva** vengono calcolati automaticamente come somma delle presenze X + O (incluse le mezze giornate, es. 6.5 + 15.5 = 22 giorni).

### Confermare l'importazione

1. Verificare che i dati mostrati nell'anteprima siano corretti
2. Cliccare il bottone arancione **"Importa dati"**
3. Attendere il messaggio di conferma: "Importazione completata!"
4. La pagina si ricarica automaticamente con i dati aggiornati

### Note importanti sull'importazione

- Se nel file Excel ci sono **piu' record** per lo stesso studente (es. un percorso interrotto e uno attivo), il sistema seleziona automaticamente quello con stato **Aperto**, poi **Chiuso**, e solo come ultima opzione **Interrotto**
- I valori possono contenere **mezze giornate** (es. 6.5 giorni)
- Se la **data di fine effettiva** non e' presente nel file, viene usata la **data di fine prevista**
- I dati dei **colloqui di assunzione** (azienda e data) vengono importati automaticamente nella Sezione 5.1 del rapporto

---

## 4. Compilare il Rapporto

Il rapporto e' diviso in sezioni che rispecchiano il documento ufficiale "Rapporto finale d'attivita'". Le sezioni con sfondo grigio (Organizzatore, Partecipante, Partecipazione) sono compilate automaticamente dai dati dello studente e dal caricamento Aladino. Le sezioni con campi editabili richiedono l'intervento del coach.

### Dati precompilati (non modificabili dalla pagina)

- **Organizzatore**: nome del coach assegnato, telefono, email (formato nome.cognome@f3m.ch)
- **Partecipante**: cognome, nome, indirizzo, Nr. AVS, data di nascita, consulente URC, ufficio URC
- **Partecipazione**: grado di occupazione, date inizio/fine, giorni di partecipazione effettiva, tabella assenze (A-I)

### Consenso SIP

Nella sezione "Partecipazione" si trova la domanda:
> "La PCI ha svolto il sostegno al collocamento individuale personalizzato nel settore industriale"

Selezionare **Si'** o **No** cliccando sul relativo cerchietto. Questa selezione viene riportata anche nel documento Word esportato.

---

### Sezione 1 - Situazione iniziale

Due campi da compilare:

1. **Qual e' la situazione iniziale della PCI?**
   Scrivere una sintesi della situazione iniziale e degli obiettivi, includendo una breve storia della carriera professionale e formativa dello studente.

2. **Quale/Quali sono il/i settore/i di riferimento?**
   Indicare il settore o i settori su cui viene effettuato il rilevamento (es. Meccanica, Automazione, Logistica, ecc.). Specificare eventuali rilevamenti approfonditi teorici o pratici.

---

### Sezione 2 - Situazione al termine della misura

1. **Tabella reinserimento**: cliccare su una delle tre opzioni:
   - Reinserimento **a breve termine** nel settore industriale
   - Reinserimento **a medio termine** nel settore industriale
   - **Non** fanno presupporre un reinserimento nel settore industriale

   La cella selezionata diventa scura con una "X". Nel documento Word, la X apparira' nella cella corrispondente.

2. **Valutazione delle competenze del settore di riferimento**: descrivere le competenze tecniche rilevate durante il percorso, inclusi eventuali stage.

3. **Possibili settori e ambiti**: indicare i settori professionali possibili per lo studente.

4. **Sintesi conclusiva**: sintesi complessiva della valutazione.

---

### Sezione 3 - Verifica delle competenze del partecipante

Quattro sotto-sezioni con tabelle di valutazione:

- **3.1 Competenze personali** (5 item): Impegno/motivazione, Iniziativa, Autonomia, Puntualita', Modo di presentarsi
- **3.2 Competenze sociali** (2 item): Capacita' di comunicazione, Capacita' di comprensione
- **3.3 Competenze metodologiche** (5 item): Ritmo di lavoro, Apprendimento, Risoluzione problemi, Organizzazione, Cura e precisione
- **3.4 Competenze TIC** (1 item): Conoscenze PC e email

**Come compilare**: per ogni competenza, cliccare sulla cella corrispondente alla valutazione desiderata. La scala e':
- **Molto buone** - **Buone** - **Sufficienti** - **Insufficienti** - **N.V.** (Non Valutabile)

La cella selezionata diventa scura. Nel documento Word, apparira' una "X" nella cella corrispondente.

Sotto ogni tabella c'e' un campo **Osservazioni** per aggiungere commenti liberi.

---

### Sezione 4 - Valutazione dell'attivita' di ricerca impiego

1. **4.1 Dossier completo**: selezionare **Si'** o **No** per indicare se e' stato allestito il dossier di candidatura.

2. **Competenze ricerca impiego** (5 item): stessa modalita' della Sezione 3, valutare ogni competenza sulla scala da "Molto buone" a "N.V.".

3. **4.2 Canali utilizzati**: selezionare (checkbox) i canali utilizzati dallo studente per la ricerca impiego:
   - Annunci su quotidiani o riviste
   - Annunci su siti web specializzati
   - Concorsi Foglio Ufficiale
   - Personalmente
   - Contatto telefonico
   - Rete di conoscenze
   - Lettere di autocandidatura
   - Autocandidatura online
   - URC
   - Agenzie di collocamento

   Sotto i canali c'e' un campo **Osservazioni**.

4. **4.3 Valutazione complessiva**: selezionare la valutazione globale della capacita' di ricerca d'impiego sulla scala da "Molto buone" a "N.V.". Aggiungere eventuali osservazioni.

---

### Sezione 5 - Riepilogo colloqui svolti

1. **Numero di colloqui effettuati**: inserire il numero. Se i dati Aladino sono stati caricati, questo campo viene precompilato automaticamente.

2. **Presso quali datori di lavoro e quando?**: descrivere i datori di lavoro e le date dei colloqui. Se i dati Aladino sono stati caricati, l'azienda e la data del colloquio vengono inserite automaticamente.

3. **Osservazioni**: commenti liberi sui colloqui.

---

### Sezione 6 - Esito dell'attivita' di ricerca impiego

1. Selezionare **Si'** o **No** alla domanda "E' stata assunta da un'azienda?"

2. Se si seleziona **Si'**, appare una tabella con tre campi:
   - **Azienda (ragione sociale)**: nome dell'azienda
   - **Professione; a partire dal**: professione e data di inizio (es. "Meccanico; 01.05.2026")
   - **Forma contrattuale**: tipo di contratto (es. "Contratto a tempo indeterminato")

---

### Eventuali allegati

Campo di testo libero per indicare quali allegati vengono inviati al consulente del personale URC.

---

### Firme

La sezione firme mostra automaticamente:
- **L'organizzatore**: Fondazione Terzo Millennio, nome del coach assegnato
- **Il partecipante**: nome dello studente
- **Luogo e data**: Taverne, data corrente

---

## 5. Salvare il Rapporto

1. Dopo aver compilato tutti i campi desiderati, cliccare il bottone blu **"Salva"**
2. Appare un messaggio verde in alto: "Report salvato con successo"
3. Il messaggio scompare dopo 5 secondi

**Importante**: salvare **prima** di esportare il Word. I dati non salvati non verranno inclusi nel documento esportato.

E' possibile salvare piu' volte durante la compilazione. Ogni salvataggio aggiorna i dati nel database.

---

## 6. Esportare il documento Word

1. Assicurarsi di aver **salvato** il rapporto (bottone blu "Salva")
2. Cliccare il bottone verde **"Esporta Word"**
3. Se il rapporto non e' ancora stato salvato, il sistema chiede conferma per salvarlo prima dell'esportazione
4. Il file Word viene scaricato automaticamente con il nome: `Rapporto_finale_Cognome_Nome_YYYY-MM-DD.docx`

### Cosa contiene il documento Word esportato

Il documento Word generato e' basato sul template ufficiale "Rapporto finale d'attivita'" e contiene:

- Tutti i dati anagrafici dello studente
- Nome completo e email del coach assegnato
- Tabella assenze con i valori reali (incluse mezze giornate)
- Giorni di partecipazione effettiva calcolati automaticamente
- Tutte le valutazioni delle competenze (con "X" nelle celle selezionate)
- I canali di ricerca selezionati
- I dati dei colloqui (azienda e data)
- I dettagli dell'assunzione (se applicabile)
- Il consenso SIP (Si'/No con checkbox)
- Sezione firme con luogo e data

Il documento e' pronto per essere stampato, firmato e inviato al consulente del personale URC.

---

## 7. Flusso di lavoro consigliato

Per compilare un rapporto finale completo, seguire questi passaggi nell'ordine:

1. **Aprire il rapporto** dello studente dalla Coach Dashboard
2. **Caricare i dati Aladino** (bottone arancione) per aggiornare assenze, date e colloqui
3. **Compilare la Sezione 1** (situazione iniziale e settori)
4. **Compilare la Sezione 2** (reinserimento, competenze settore, sintesi)
5. **Compilare la Sezione 3** (griglie competenze trasversali + osservazioni)
6. **Compilare la Sezione 4** (dossier, ricerca impiego, canali, valutazione)
7. **Verificare la Sezione 5** (colloqui - precompilata da Aladino)
8. **Compilare la Sezione 6** (esito, dettagli assunzione se applicabile)
9. **Selezionare il consenso SIP** (Si'/No)
10. **Aggiungere eventuali allegati**
11. **Salvare** (bottone blu)
12. **Esportare il Word** (bottone verde)
13. Stampare il documento, farlo firmare dallo studente e dall'organizzatore
14. Inviare al consulente URC tramite MFT

---

## 8. Domande frequenti

**D: Posso modificare un rapporto gia' salvato?**
R: Si'. Aprire lo stesso rapporto, modificare i campi desiderati e salvare nuovamente.

**D: Posso esportare il Word piu' volte?**
R: Si'. Ogni esportazione genera un nuovo documento con i dati attualmente salvati.

**D: I dati Aladino sovrascrivono quello che ho scritto manualmente?**
R: I dati Aladino aggiornano solo i campi della scheda studente (assenze, date, colloqui). I testi narrativi che hai scritto nel rapporto (situazione iniziale, osservazioni, ecc.) non vengono toccati.

**D: Perche' il coach mostrato non sono io?**
R: Il rapporto mostra il coach **assegnato** allo studente, non chi e' attualmente loggato. Se lo studente e' assegnato a un altro coach, quel nome appare nel documento. Per cambiare l'assegnazione, modificare il coach nella Student Card CPURC.

**D: Le mezze giornate vengono gestite?**
R: Si'. Il sistema supporta valori decimali per le presenze (es. 6.5 giorni). Vengono importati correttamente dal file Aladino e mostrati nel rapporto.

**D: Il file Aladino contiene piu' studenti. Come fa il sistema a trovare quello giusto?**
R: Il sistema cerca automaticamente lo studente nel file per **indirizzo email** (priorita') oppure per **nome e cognome** (fallback). Se lo studente non viene trovato, appare un messaggio di errore.

**D: Nel file Aladino ci sono due righe per lo stesso studente (es. un percorso interrotto e uno attivo). Quale viene usata?**
R: Il sistema seleziona automaticamente il record con stato **Aperto** (priorita' massima), poi **Chiuso**, e solo come ultima opzione **Interrotto** o **Annullato**.
