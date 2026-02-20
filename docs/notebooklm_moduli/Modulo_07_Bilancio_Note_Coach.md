# Modulo 7: Bilancio Competenze e Note Coach

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: cos'e il Bilancio Competenze (reports_v2.php), come accedere, le 6 tab (Panoramica con stat card cliccabili, Radar Confronto, Mappa Competenze con card area e modal dettaglio, Confronta Studenti side-by-side, Colloquio con domande suggerite e note coach, Matching Lavoro), il filtro settore globale, e il sistema note coach (dalla Dashboard e dal Bilancio) con visibilita e suggerimenti per lo storico.

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
