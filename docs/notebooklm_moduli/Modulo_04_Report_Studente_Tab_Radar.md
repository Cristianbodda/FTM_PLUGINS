# Modulo 4: Report Studente - Tab e Radar

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: come accedere al Report Studente, l'header con foto e percentuale, le 7 tab principali (Panoramica, Piano, Quiz, Autovalutazione, Laboratorio, Progressi, Dettagli), la valutazione inline, il selettore quiz, il pannello FTM con 6 tab laterali (Settori, Ultimi 7gg, Configurazione, Progresso, Gap Analysis, Spunti), i grafici radar SVG e Chart.js, e la stampa personalizzata.

---

## 7. Report Studente Dettagliato

### 7.1 Accesso al Report

**Dalla Dashboard (metodo principale):**

**Passo 1.** Trova lo studente nella griglia della Coach Dashboard.

**Passo 2.** Clicca sul pulsante "📊 Report" nella card.

> **Cosa succede:** Si apre la pagina `student_report.php` con i dati dello studente.

**Da URL diretto:**
```
/local/competencymanager/student_report.php?userid=X&courseid=Y
```

**Senza parametri (selettore studente):**

Se apri `student_report.php` senza specificare uno studente:

> **Cosa vedrai:** Una pagina con sfondo bianco e una card centrata con il titolo
> "👤 Seleziona Studente". Contiene:
> - Un dropdown "📚 Corso" per selezionare il corso
> - Un dropdown "👨‍🎓 Studente" (si popola dopo la selezione del corso)
> - Un pulsante "📊 Visualizza Report" (gradiente viola)

**Passo 1.** Seleziona un corso dal primo dropdown.

> **Cosa succede:** La pagina si ricarica e il dropdown studente si popola con
> tutti gli studenti iscritti al corso.

**Passo 2.** Seleziona uno studente.

**Passo 3.** Clicca "📊 Visualizza Report".

> **Cosa succede:** Si apre il report completo dello studente selezionato.

### 7.2 Struttura della Pagina

La pagina Report Studente e organizzata in tre zone:

1. **Header** (fisso in alto)
2. **Pannello FTM** (barra laterale con 6 tab FTM)
3. **Area principale** (7 tab di navigazione)

### 7.3 Header del Report

> **Cosa vedrai:** Un banner con sfondo gradiente viola (#667eea -> #764ba2) che contiene:
> - **Foto studente** (80x80 pixel) a sinistra
> - **Nome e cognome** in bianco, grassetto
> - **Email** in bianco semi-trasparente
> - **Corso** attivo
> - **Percentuale globale** grande (es. "72%") sulla destra

### 7.4 Le 7 Tab Principali

Sotto l'header trovi una **barra di navigazione con 7 tab** (stile Bootstrap nav-tabs):

| # | Tab | Icona | Contenuto |
|---|-----|-------|-----------|
| 1 | **Panoramica** | 📊 | Mappa competenze con card per area, barre confronto Quiz/Autoval |
| 2 | **Piano** | 📚 | Piano d'azione: eccellenze, buoni, da migliorare, critici |
| 3 | **Quiz** | 📝 | Confronto quiz con dettagli tentativi |
| 4 | **Autovalutazione** | 📝 ↗ | Link esterno a `/local/selfassessment/student_report.php` (apre nuova scheda) |
| 5 | **Laboratorio** | 🔬 ↗ | Link esterno a `/local/labeval/reports.php` (apre nuova scheda) |
| 6 | **Progressi** | 📈 | Grafico progresso nel tempo (Chart.js line chart) |
| 7 | **Dettagli** | 📋 | Tabella completa competenze con editing inline Bloom |

> **Nota:** I tab "Autovalutazione" e "Laboratorio" sono **link esterni** (indicati con ↗).
> Cliccandoli si apre una nuova scheda con il rispettivo report.

**Per navigare tra i tab:**

**Passo 1.** Clicca sul nome del tab desiderato nella barra di navigazione.

> **Cosa succede:** Il contenuto dell'area principale cambia.
> Il tab attivo appare evidenziato con un bordo inferiore colorato.
> L'URL si aggiorna con il parametro `tab=` (es. `tab=overview`, `tab=details`).

### 7.5 Tab Panoramica

**Passo 1.** Clicca su "📊 Panoramica" (e il tab predefinito).

> **Cosa vedrai:** Due colonne:
>
> **Colonna sinistra (60%):**
> - Un grafico **radar SVG/Canvas** con le aree di competenza
> - Ogni asse del radar corrisponde a un'area (A, B, C, D, ...)
> - L'area colorata mostra il livello raggiunto
>
> **Colonna destra (40%):**
> - Card per ogni area di competenza con:
>   - Nome area (es. "A. Accoglienza e Diagnosi")
>   - Badge colorato con percentuale
>   - Barra di confronto Quiz vs Autovalutazione (se disponibile)
>   - Colore in base alla fascia: ECCELLENTE (verde >=80%), BUONO (teal >=60%),
>     SUFFICIENTE (giallo >=50%), INSUFFICIENTE (arancione >=30%), CRITICO (rosso <30%)

### 7.6 Tab Piano

**Passo 1.** Clicca su "📚 Piano".

> **Cosa vedrai:** Le competenze organizzate in 4 categorie:
>
> - **Eccellenze** (>=80%): competenze con padronanza completa.
>   Azione: "Pronto per attivita avanzate e tutoraggio compagni."
> - **Buone** (60-79%): competenze acquisite con buona padronanza.
>   Azione: "Consolidare con esercizi pratici."
> - **Da Migliorare** (30-59%): lacune significative.
>   Azione: "Percorso di recupero mirato richiesto."
> - **Critiche** (<30%): competenze non acquisite.
>   Azione: "Formazione base completa richiesta."

Ogni competenza mostra: codice, nome, percentuale, risposte corrette/totali.

### 7.6b Tab Quiz

**Passo 1.** Clicca su "📝 Quiz".

> **Cosa vedrai:** Una card con header grigio scuro (bg-secondary):
> "📝 Confronto per Quiz"
> Sottotitolo: "Clicca sul nome del quiz per vedere domande e risposte dello studente"

**Tabella quiz:**

| Colonna | Contenuto |
|---------|-----------|
| **Quiz** | Nome del quiz (cliccabile). Al click apre la review Moodle in nuova scheda. Icona 🔗 accanto |
| **Tentativo** | Numero del tentativo (es. "#1", "#2") |
| **Data** | Data e ora di completamento (formato dd/mm/YYYY HH:mm) |
| **Punteggio** | Badge colorato con percentuale: verde (>=60%), giallo (40-59%), rosso (<40%) |
| **Competenze** | Numero di competenze coperte dal quiz |
| **Azione** | Pulsante "👁️ Review" (btn-outline-primary) - apre la review dettagliata |

> **Suggerimento:** Il pulsante "👁️ Review" apre la pagina Moodle standard
> `/mod/quiz/review.php?attempt=X` dove puoi vedere ogni singola domanda,
> la risposta data dallo studente e la risposta corretta. Questo e prezioso
> per capire DOVE lo studente sbaglia, non solo quanto sbaglia.

> **Nota:** Se lo studente non ha completato nessun quiz, la tabella mostra
> il messaggio "Nessun tentativo quiz completato" in grigio centrato.

> **SCREENSHOT 7.6b:** Tabella quiz con tentativi e badge punteggio colorati

### 7.6c Tab Autovalutazione e Laboratorio

Le tab "📊 Autovalutazione" e "🔬 Laboratorio" sono **link esterni**:

**Tab Autovalutazione:**

**Passo 1.** Clicca su "📊 Autovalutazione ↗".

> **Cosa succede:** Si apre in una nuova scheda la pagina
> `/local/selfassessment/student_report.php` con il report autovalutazione dello studente.

**Tab Laboratorio:**

**Passo 1.** Clicca su "🔬 Laboratorio ↗".

> **Cosa succede:** Si apre in una nuova scheda la pagina
> `/local/labeval/reports.php` con le valutazioni di laboratorio dello studente.

> **Nota:** Queste due tab hanno la freccia "↗" accanto al nome per indicare
> che aprono pagine esterne in nuova scheda.

### 7.7 Tab Progressi

**Passo 1.** Clicca su "📈 Progressi".

> **Cosa vedrai:** Una card con header blu (bg-primary):
> "📈 Progressi nel Tempo"
>
> Al centro un **grafico a linee** (Chart.js canvas `progressChart`):
> - **Asse X:** Date dei tentativi quiz (formato data)
> - **Asse Y:** Percentuali (0-100%)
> - **Linea:** Andamento della media competenze nel tempo
> - **Altezza massima:** 300px per non occupare troppo spazio verticale
>
> Se lo studente non ha ancora dati sufficienti, appare il messaggio:
> "Non ci sono ancora abbastanza dati per mostrare i progressi.
> Completa piu quiz per vedere l'andamento."

> **Suggerimento:** Usa questo tab per verificare se lo studente sta migliorando
> nel tempo. Una curva in salita indica apprendimento; una curva piatta puo
> indicare stallo e necessita di intervento.

> **SCREENSHOT 7.7:** Grafico progressi con curva in salita

### 7.8 Tab Dettagli

**Passo 1.** Clicca su "📋 Dettagli".

> **Cosa vedrai:** Una tabella completa con TUTTE le competenze dello studente.

**Colonne della tabella:**

| Colonna | Contenuto |
|---------|-----------|
| **#** | Numero progressivo |
| **Area** | Badge colorato con codice area (es. "A" in azzurro, "B" in rosso) |
| **Codice** | Codice competenza (es. MECCANICA_MIS_01) in font monospace |
| **Competenza** | Nome/descrizione della competenza |
| **Risposte** | Formato X/Y (corrette/totali) |
| **%** | Percentuale con colore (verde/giallo/rosso) |
| **Valutazione** | Badge Bloom cliccabile per editing inline |

**Intestazione della tabella:**

> **Cosa vedrai:** Card con header nero (bg-dark) e testo bianco:
> "📋 Dettaglio Competenze" con a destra il conteggio
> "X / Y competenze" (filtrate / totali) in badge chiaro.

**Filtri della tabella (sotto l'header, sopra i dati):**

**Filtro 1 - Ordina per (dropdown `sort`):**
- Per area (default) - raggruppa le competenze per area
- Per percentuale (decrescente) - dalla piu alta alla piu bassa
- Per percentuale (crescente) - dalla piu bassa alla piu alta
- Per numero risposte - le competenze con piu domande prima

**Filtro 2 - Livello (dropdown `filter`):**
- Tutti i livelli (default)
- Eccellente (>=80%) - solo le competenze forti
- Buono (60-79%) - solo le competenze acquisite
- Sufficiente (50-59%) - area di confine
- Insufficiente (30-49%) - lacune significative
- Critico (<30%) - competenze non acquisite

**Filtro 3 - Area (dropdown `filter_area`):**
- Tutte le aree (default)
- Elenco di ogni area disponibile per lo studente (es. "📁 A - Accoglienza", "📁 B - Motore")

> **Cosa succede quando filtri:** La tabella si aggiorna immediatamente
> mostrando solo le competenze che corrispondono ai filtri selezionati.
> Il conteggio nell'header si aggiorna (es. "12 / 45 competenze").

**Contatori per livello (sotto i filtri):**

> Cinque mini-badge cliccabili che mostrano il totale per ogni fascia:
> - 🟢 Eccellente: X
> - 🔵 Buono: X
> - 🟡 Sufficiente: X
> - 🟠 Insufficiente: X
> - 🔴 Critico: X
>
> Cliccando su un badge si applica il filtro per quel livello.

### 7.9 Valutazione Inline dal Tab Dettagli

Nella colonna "Valutazione" puoi valutare direttamente ogni competenza:

**Passo 1.** Nella tabella Dettagli, trova la competenza da valutare.

**Passo 2.** Clicca sul **badge Bloom** nella colonna "Valutazione".

> **Cosa vedrai:** Il badge mostra il valore corrente:
> - **Verde** (>=5): livello alto
> - **Giallo** (3-4): livello medio
> - **Rosso** (1-2): livello basso
> - **Grigio** (N/O): non osservato

**Passo 3.** Dal dropdown che appare, seleziona il nuovo livello:
- N/O (Non Osservato)
- 1 (Ricordare)
- 2 (Comprendere)
- 3 (Applicare)
- 4 (Analizzare)
- 5 (Valutare)
- 6 (Creare)

> **Cosa succede:** Dopo 500ms il sistema salva automaticamente (auto-save con debounce).
> Appare un **toast di conferma** "Salvando..." in basso a destra
> (sfondo #667eea, testo bianco, scompare dopo 2 secondi).
> Il badge si aggiorna con il nuovo colore.

> **Suggerimento:** Questa funzione e molto comoda per valutazioni rapide.
> Per una valutazione completa e strutturata, usa la pagina Valutazione Formatore dedicata.

### 7.10 Selettore Quiz

Nella parte superiore del Report (quando visualizzi la tab Panoramica o Dettagli), puoi selezionare quali quiz includere nell'analisi.

**Comportamento predefinito:**
- Il sistema pre-seleziona automaticamente i quiz del **settore primario** dello studente

**Per modificare la selezione:**

**Passo 1.** Cerca i checkbox dei quiz nella parte alta della pagina.

> **Cosa vedrai:** Un elenco di quiz disponibili con:
> - Nome del quiz
> - Corso di appartenenza
> - Checkbox per selezionare/deselezionare

**Passo 2.** Spunta o deseleziona i quiz desiderati.

**Passo 3.** Clicca "Applica Filtri" (o il report si aggiorna automaticamente).

> **Cosa succede:** I grafici radar, le tabelle e le statistiche si ricalcolano
> includendo solo i quiz selezionati. L'URL si aggiorna con i parametri `quizids[]`.

**Filtro tentativi:**
- **Tutti** (`attempt_filter=all`): include tutti i tentativi di ogni quiz
- **Solo primo** (`attempt_filter=first`): solo il primo tentativo
- **Solo ultimo** (`attempt_filter=last`): solo il tentativo piu recente

> **Suggerimento:** Per vedere il progresso, confronta il primo tentativo con l'ultimo.
> Per la valutazione finale, usa "Solo ultimo".

### 7.11 Fascia di Valutazione

Ogni competenza viene classificata in una fascia in base alla percentuale:

| Fascia | Percentuale | Icona | Colore | Azione suggerita |
|--------|-------------|-------|--------|-----------------|
| **ECCELLENTE** | >=80% | 🌟 | Verde #28a745 | Pronto per attivita avanzate e tutoraggio |
| **BUONO** | 60-79% | ✅ | Teal #20c997 | Consolidare con esercizi pratici |
| **SUFFICIENTE** | 50-59% | ⚠️ | Giallo #ffc107 | Ripasso teoria ed esercitazioni |
| **INSUFFICIENTE** | 30-49% | ⚡ | Arancione #fd7e14 | Percorso di recupero mirato |
| **CRITICO** | <30% | 🔴 | Rosso #dc3545 | Formazione base completa richiesta |

Queste fasce sono utilizzate nei badge delle tabelle, nelle card area e nel Piano d'Azione.

> **SCREENSHOT 7.11:** Tabella dettagli con dropdown Bloom aperto su una competenza

---

## 8. Report Studente - Tab e Radar

### 8.1 Il Pannello FTM (6 Tab Laterali)

Accanto ai 7 tab principali, il Report Studente ha un **pannello FTM** con 6 tab aggiuntivi dedicati alla gestione avanzata:

| # | Tab | Icona | Contenuto |
|---|-----|-------|-----------|
| 1 | **Settori** | 👤 | Gestione settori studente (primario/secondario/terziario) |
| 2 | **Ultimi 7gg** | 📅 | Quiz degli ultimi 7 giorni con stati e link Review |
| 3 | **Configurazione** | ⚙️ | Toggle visualizzazioni + soglie configurabili |
| 4 | **Progresso** | 📊 | Barra certificazione (certificati/in corso/da iniziare) |
| 5 | **Gap Analysis** | 📈 | Tabella gap con soglie configurabili |
| 6 | **Spunti Colloquio** | 💬 | Suggerimenti per il colloquio |

**Per aprire un tab FTM:**

**Passo 1.** Clicca su uno dei pulsanti nella barra FTM (sotto i tab principali).

> **Cosa succede:** Il pannello del tab selezionato si espande sotto la barra.
> Il pulsante diventa evidenziato. Cliccando di nuovo si chiude.

### 8.2 Tab Settori

**Passo 1.** Clicca su "👤 Settori".

> **Cosa vedrai:** La gestione dei settori assegnati allo studente:
> - 🥇 **Settore primario** (con medaglia oro): il settore principale dello studente
> - 🥈 **Settore secondario** (medaglia argento): settore aggiuntivo - opzionale
> - 🥉 **Settore terziario** (medaglia bronzo): terzo settore - opzionale
> - 📊 **Da quiz**: settori rilevati automaticamente dai quiz svolti

I settori determinano quali competenze vengono mostrate nei radar e nelle valutazioni.

### 8.3 Tab Ultimi 7 Giorni

**Passo 1.** Clicca su "📅 Ultimi 7gg".

> **Cosa vedrai:** Una card con header blu "📋 Quiz ultimi 7 giorni" e una tabella:

| Colonna | Contenuto |
|---------|-----------|
| **Data** | Data e ora del tentativo |
| **Quiz** | Nome del quiz |
| **Corso** | Nome del corso |
| **Stato** | Badge colorato (vedi sotto) |
| **Azioni** | Link "Review" per ogni quiz completato |

**Colori stato quiz:**

| Stato | Colore badge |
|-------|-------------|
| Completato | Verde |
| In corso | Blu |
| Scaduto | Giallo |
| Abbandonato | Rosso |

**Passo 2.** Clicca su "Review" per vedere il dettaglio delle risposte di un quiz.

> **Cosa succede:** Si apre la pagina di review del quiz Moodle con tutte
> le domande e le risposte dello studente.

### 8.4 Tab Configurazione Report

Questo tab e fondamentale per personalizzare cosa vedere nel report.

**Passo 1.** Clicca su "⚙️ Configurazione".

> **Cosa vedrai:** Un pannello con toggle e slider.

**5 Toggle di visualizzazione:**

| Toggle | Parametro | Default | Cosa attiva |
|--------|-----------|---------|-------------|
| **Doppio Radar** | `show_dual_radar` | ON (se quiz selezionati) | Radar autovalutazione affiancato al radar quiz |
| **Gap Analysis** | `show_gap` | ON | Tabella analisi scostamenti |
| **Spunti Colloquio** | `show_spunti` | ON | Suggerimenti per il colloquio |
| **Valutazione Formatore** | `show_coach_eval` | ON | Sezione valutazione coach |
| **Grafico Sovrapposizione** | `show_overlay` | ON | Radar overlay con tutte e 4 le fonti sovrapposte |

**Soglie configurabili:**

| Soglia | Parametro | Default | Range | Significato |
|--------|-----------|---------|-------|-------------|
| **Allineamento** | `soglia_allineamento` | 10% | 5-40% | Sotto questa % di gap, Quiz e Autoval sono considerati allineati (verde) |
| **Monitorare** | `soglia_monitorare` | 25% | 15-60% | Gap tra allineamento e monitorare = attenzione (arancione) |
| **Critico** | `soglia_critico` | 30% | 20-80% | Sopra questa % = gap critico (rosso) |

**Passo 2.** Modifica i toggle e le soglie secondo le tue esigenze.

**Passo 3.** Clicca il pulsante **"Aggiorna Grafici"**.

> **Cosa succede:** La pagina si ricarica con le nuove impostazioni applicate.
> I grafici radar, la gap analysis e gli spunti colloquio si aggiornano.

> **Suggerimento:** Per un primo sguardo veloce, lascia tutto attivo.
> Per la stampa, disattiva le sezioni che non servono per ridurre le pagine.

### 8.5 Grafici Radar

Il Report Studente utilizza diversi tipi di grafici radar:

**Radar SVG (statico):**
- Dimensione: 300x300 pixel
- Cerchi concentrici: 20%, 40%, 60%, 80%, 100%
- Linea di riferimento arancione al 60%
- Linea di riferimento verde all'80%
- Un asse per ogni area di competenza

**Radar Canvas (Chart.js, interattivo):**
- **radarAreas**: radar per aree, colorato per fonte
- **radarAutovalutazione**: radar autovalutazione sovrapposto
- **radarPerformanceDual**: doppio radar quiz vs autovalutazione affiancato

**Radar Overlay (4 fonti sovrapposte):**
- Quiz (colore 1) + Autovalutazione (colore 2) + LabEval (colore 3) + Coach (colore 4)
- Tutti sovrapposti sullo stesso grafico
- Leggenda con checkbox per attivare/disattivare le fonti

> **Come leggere il radar:**
> - Un'area che "sporge" molto verso l'esterno = competenza alta
> - Un'area vicina al centro = competenza bassa
> - Confrontando le forme colorate puoi vedere dove c'e accordo tra le fonti
>   e dove ci sono discrepanze (gap)

> **SCREENSHOT 8.5:** Radar overlay con 4 fonti sovrapposte e legenda

### 8.6 Tab Progresso Certificazione

**Passo 1.** Clicca su "📊 Progresso".

> **Cosa vedrai:** Una barra di progresso certificazione che mostra:
> - **Certificati** (verde): competenze con >=80%
> - **In corso** (giallo): competenze con >0% ma <80%
> - **Da iniziare** (grigio): competenze a 0%
>
> La barra mostra la percentuale complessiva e il rapporto numerico
> (es. "12 certificati / 5 in corso / 3 da iniziare su 20 totali").

### 8.7 Stampa Personalizzata (Modal)

Il Report Studente include un potente sistema di stampa personalizzata.

**Passo 1.** Clicca sul pulsante "🖨️ Stampa Personalizzata" (in alto nel report).

> **Cosa succede:** Si apre un **modal** (finestra sovrapposta) con il titolo
> e due pulsanti in fondo: "Annulla" e "🖨️ Genera Stampa".

**Contenuto del modal di stampa:**

**Sezioni da includere (checkbox):**
- ☑ Panoramica
- ☑ Piano d'Azione
- ☑ Progressi
- ☑ Dettagli Competenze
- ☑ Radar per Aree
- ☑ Doppio Radar (se attivato)
- ☑ Gap Analysis (se attivato)
- ☑ Spunti Colloquio (se attivato)

**Selezione radar per area:** Checkbox per ogni area (A, B, C, ...) per includere il radar specifico.

**Ordinamento sezioni:** Dropdown numerati (1-11) per ogni sezione, che determinano l'ordine di stampa:

| Sezione | Ordine default |
|---------|---------------|
| Valutazione | 1 |
| Progressi | 2 |
| Radar Aree | 3 |
| Radar Dettagli | 4 |
| Piano | 5 |
| Dettagli | 6 |
| Dual Radar | 7 |
| Gap Analysis | 8 |
| Spunti | 9 |
| Suggerimenti | 10 |
| Coach Eval | 11 |

**Tono suggerimenti:**
- **Formale** (per aziende/URC): terza persona, linguaggio professionale
- **Colloquiale** (per uso interno): seconda persona, linguaggio diretto

**Filtro settore per stampa parziale:** Dropdown per stampare solo un settore specifico.

**Passo 2.** Seleziona le sezioni desiderate e configura l'ordine.

**Passo 3.** Clicca "🖨️ Genera Stampa".

> **Cosa succede:** Si apre una versione stampabile del report con solo
> le sezioni selezionate, nell'ordine configurato. La finestra di stampa
> del browser si apre automaticamente.

### 8.8 Configurazione Dettagliata dei Toggle

Ogni toggle nella Configurazione Report attiva/disattiva una sezione specifica. Ecco cosa cambia esattamente:

**Toggle "Doppio Radar" (show_dual_radar):**

Quando **attivo:**
- Nella tab Panoramica appare un secondo radar accanto a quello dei quiz
- Il radar aggiuntivo mostra i dati dell'autovalutazione
- Puoi confrontare visivamente le due "forme" radar

Quando **disattivo:**
- Appare solo il radar dei quiz
- Utile se l'autovalutazione non e ancora stata completata

**Toggle "Gap Analysis" (show_gap):**

Quando **attivo:**
- Nel pannello FTM appare il tab "📈 Gap Analysis"
- La tabella gap e visibile con tutti gli indicatori colorati
- Le soglie configurabili sono attive

Quando **disattivo:**
- Il tab Gap Analysis non e disponibile
- Utile nelle prime settimane quando non ci sono ancora dati sufficienti

**Toggle "Spunti Colloquio" (show_spunti):**

Quando **attivo:**
- Nel pannello FTM appare il tab "💬 Spunti Colloquio"
- Le domande suggerite per ogni area critica sono visibili
- Le 3 categorie (Critici, Attenzione, Positivi) sono generate

Quando **disattivo:**
- Nessun suggerimento disponibile
- Utile se preferisci preparare i colloqui autonomamente

**Toggle "Valutazione Formatore" (show_coach_eval):**

Quando **attivo:**
- I dati della valutazione coach vengono inclusi nei grafici
- Il quarto poligono (Coach) appare nel radar overlay
- Le colonne "Coach" appaiono nelle tabelle

Quando **disattivo:**
- Solo 3 fonti dati (Quiz, Auto, Lab)
- Utile prima di aver iniziato la valutazione formatore

**Toggle "Grafico Sovrapposizione" (show_overlay):**

Quando **attivo:**
- Un grafico radar con 4 poligoni sovrapposti
- Leggenda interattiva per attivare/disattivare singole fonti
- La vista piu completa e informativa

Quando **disattivo:**
- Nessun radar overlay
- I radar individuali restano visibili nella tab Panoramica

> **Suggerimento:** Per la prima visita di un report, lascia tutto attivo
> (il sistema lo fa automaticamente quando ci sono quiz selezionati).
> Disattiva solo quando vuoi stampare un report snello.

### 8.9 Modificare le Soglie Gap

Le soglie determinano come vengono classificati i gap. Puoi personalizzarle per adattarle al contesto.

**Per modificare le soglie:**

**Passo 1.** Nel tab FTM "⚙️ Configurazione", trova la sezione "Soglie configurabili".

**Passo 2.** Modifica i valori con i controlli numerici o gli slider:

| Soglia | Range | Default | Effetto |
|--------|-------|---------|---------|
| **Allineamento** | 5% - 40% | 10% | Gap sotto questa soglia = "Allineato" (verde) |
| **Monitorare** | 15% - 60% | 25% | Gap tra Allineamento e Monitorare = "Da monitorare" (arancione) |
| **Critico** | 20% - 80% | 30% | Gap sopra questa soglia = "Critico" (rosso) |

> **Regole automatiche di coerenza:**
> - Monitorare deve essere sempre > Allineamento (se impostato uguale o inferiore, viene corretto automaticamente a Allineamento + 15%)
> - Critico deve essere sempre > Monitorare (se impostato uguale o inferiore, viene corretto a Monitorare + 5%)

**Passo 3.** Clicca "Aggiorna Grafici" per applicare le nuove soglie.

> **Esempio pratico:**
> - Se imposti Allineamento a 15%: piu gap saranno considerati "allineati"
>   (meno badge rossi, approccio piu tollerante)
> - Se imposti Critico a 20%: piu gap saranno "critici"
>   (piu badge rossi, approccio piu rigoroso)
>
> **Quando modificare le soglie:**
> - **Classe avanzata:** Abbassa le soglie (piu rigoroso)
> - **Classe principiante:** Alza le soglie (piu tollerante)
> - **Settore complesso:** Alza la soglia di allineamento

> **SCREENSHOT 8.9:** Modal stampa personalizzata con opzioni

---
