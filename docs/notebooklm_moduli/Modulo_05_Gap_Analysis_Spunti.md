# Modulo 5: Gap Analysis e Spunti per il Colloquio

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre: come leggere la Gap Analysis (differenza tra autovalutazione e performance quiz), le soglie configurabili (Allineamento, Monitorare, Critico), gli indicatori di sopravvalutazione e sottovalutazione, il tab Spunti Colloquio con le 3 categorie (Critici, Attenzione, Positivi), i suggerimenti automatici con tono formale/colloquiale, e l'uso pratico della gap analysis.

---

## 9. Report Studente - Gap Analysis e Spunti Colloquio

### 9.1 Tab Gap Analysis

La Gap Analysis confronta automaticamente i risultati dei quiz con l'autovalutazione dello studente.

**Passo 1.** Clicca su "📈 Gap Analysis" nel pannello FTM (oppure attivala dalla Configurazione).

> **Cosa vedrai:** Una tabella con le competenze ordinate per magnitudine del gap
> (il gap piu grande appare per primo).

**Formula del Gap:**

```
Gap = Autovalutazione (%) - Performance Quiz (%)
```

### 9.2 Soglie e Indicatori

Le soglie determinano come viene classificato il gap:

| Intervallo Gap | Classificazione | Indicatore | Colore |
|---------------|-----------------|------------|--------|
| |Gap| < Soglia Allineamento (10%) | **Allineato** | ✓ Check | Verde |
| Soglia Allineamento < |Gap| < Soglia Critico | **Da monitorare** | ↕ Freccia | Arancione |
| |Gap| > Soglia Critico (30%) | **Critico** | ⚠ Warning | Rosso |

**Direzione del gap:**

| Gap | Significato | Indicatore |
|-----|-------------|------------|
| **Positivo (+)** = Sovrastima | Lo studente si valuta meglio di quanto risulti dai quiz | ↑ Freccia su, colore rosso |
| **Negativo (-)** = Sottovalutazione | Lo studente si valuta peggio di quanto risulti | ↓ Freccia giu, colore arancione |
| **Zero** = Allineato | Percezione corretta | ✓ Check, colore verde |

### 9.3 Interpretare la Gap Analysis

> **Esempio pratico:**
>
> Lo studente Mario si e autovalutato al 90% nell'area "Misurazione"
> ma nei quiz ha ottenuto il 45%.
>
> Gap = 90% - 45% = +45% (Sovrastima critica)
>
> Significa: Mario pensa di essere molto bravo nella misurazione,
> ma i test dicono il contrario. Argomento prioritario per il colloquio.

### 9.4 Tab Spunti Colloquio

**Passo 1.** Clicca su "💬 Spunti Colloquio" nel pannello FTM.

> **Cosa vedrai:** I suggerimenti per il colloquio organizzati in 3 categorie:

**1. Critici (gap > soglia critico):**
- Domande specifiche da porre allo studente
- Messaggio contestualizzato sulla discrepanza
- Sfondo rosso chiaro per evidenziare l'urgenza

**2. Attenzione (gap medio):**
- Suggerimenti di miglioramento
- Aree da monitorare nel tempo
- Sfondo arancione chiaro

**3. Positivi (sottovalutazione):**
- Punti di forza da valorizzare
- Competenze dove lo studente si sottovaluta
- Sfondo verde chiaro

**Domande suggerite per settore:**

Il sistema include domande specifiche per ogni area e settore. Esempi:

Per **AUTOMOBILE** - Area F (Telaio/Sospensioni):
- "Descrivi la procedura di spurgo freni che utilizzi abitualmente"
- "Come verifichi lo stato di usura dei componenti delle sospensioni?"
- "Quali controlli esegui sulla geometria dello sterzo?"

Per **MECCANICA** - Area Misurazione:
- "Quali strumenti di misura sai utilizzare?"
- "Come verifichi la taratura di uno strumento?"
- "Descrivi la procedura di misurazione con micrometro"

Per **MECCANICA** - Area CNC:
- "Quali controlli numerici conosci e utilizzi?"
- "Come imposti i parametri di lavorazione per un nuovo pezzo?"
- "Descrivi la procedura di azzeramento utensili"

> **Suggerimento:** Stampa gli spunti colloquio il giorno prima dell'incontro.
> Usa il modal di stampa personalizzata selezionando solo "Spunti Colloquio"
> e "Gap Analysis" per un foglio di preparazione compatto.

### 9.5 Suggerimenti Automatici con Tono

Il sistema genera commenti automatici basati sui gap rilevati. Sono disponibili in **due toni**:

**Tono Formale** (per report scritti, URC, aziende):
> "Lo studente mostra una percezione delle proprie competenze superiore ai risultati
> oggettivi nell'area della saldatura. Si consiglia di proporre esercitazioni
> pratiche mirate per consolidare le competenze."

**Tono Colloquiale** (per il colloquio diretto con lo studente):
> "Hai ottenuto risultati migliori di quanto pensassi nella lettura del disegno
> tecnico. Questo e un tuo punto di forza su cui costruire!"

**Per cambiare il tono:**

**Passo 1.** Nel tab FTM "⚙️ Configurazione" o nel modal di stampa, trova il selettore "Tono".

**Passo 2.** Seleziona "Formale" o "Colloquiale" (parametro `tono_commenti`).

> **Suggerimento:** Usa il tono "Formale" per la documentazione ufficiale
> e il tono "Colloquiale" per preparare il colloquio faccia a faccia.

### 9.6 Settore per la Stampa

Puoi filtrare la Gap Analysis e gli Spunti per settore nella stampa:

**Passo 1.** Nel modal "Stampa Personalizzata", trova il filtro "Settore per stampa" (parametro `print_sector`).

**Passo 2.** Seleziona "Tutti" (default) o un settore specifico.

> **Cosa succede:** La stampa includera solo le competenze del settore selezionato.
> Utile quando lo studente ha competenze in piu settori e vuoi un report focalizzato.

### 9.7 Uso Pratico della Gap Analysis

**Per un colloquio efficace basato sulla Gap Analysis:**

1. **Identifica i gap critici** (rossi): sono le priorita assolute del colloquio
2. **Prepara domande specifiche** dagli Spunti Colloquio per le aree critiche
3. **Nota i gap positivi** (sottovalutazione verde): punti di forza da valorizzare
4. **Confronta con la Valutazione Formatore:** se il tuo giudizio e diverso sia dal quiz che dall'autovalutazione, approfondisci durante il colloquio
5. **Aggiorna le soglie** se necessario: se troppi gap risultano "critici" potresti alzare la soglia, se troppo pochi potresti abbassarla

> **Attenzione:** La Gap Analysis funziona solo se lo studente ha completato
> sia almeno un quiz sia l'autovalutazione. Se manca una delle due fonti,
> i dati saranno incompleti.

> **SCREENSHOT 9.7:** Spunti colloquio con le 3 categorie colorate

---
