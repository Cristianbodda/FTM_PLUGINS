# Manuale Coach — Passaporto Tecnico

**Versione:** 2.9.6 | **Data:** Maggio 2026

---

## Cos'è il Passaporto Tecnico

Il Passaporto Tecnico è un documento ufficiale di sintesi delle competenze di uno studente, pensato per essere consegnato all'URC (Ufficio Regionale di Collocamento) o a potenziali datori di lavoro. Contiene:

- Radar grafico delle competenze per area
- Punteggi per area (quiz, autovalutazione, valutazione coach)
- Commenti tecnici del coach per ogni area
- Nota finale di sintesi
- Timbro di approvazione (se approvato)

---

## Come aprire il Passaporto

Dalla **Coach Dashboard V2** → clicca sul nome dello studente → pulsante **Passaporto Tecnico**

Oppure direttamente via URL:
```
/local/competencymanager/technical_passport.php?userid=X&courseid=Y
```

---

## Flusso di lavoro consigliato

```
1. GARAGE FTM        → configura struttura e profilo AI
2. PASSAPORTO        → scrivi/genera commenti per area
3. SALVA             → premi "Salva Commenti" nella barra inferiore
4. REVISIONA         → controlla e correggi i testi
5. APPROVA           → premi "Approva Passaporto"
6. STAMPA            → Ctrl+P o pulsante Stampa
```

---

## Parte 1: Il Garage FTM (configurazione)

Il Garage FTM si apre dal link **"Garage FTM"** nella pagina del passaporto (in alto).

Serve a personalizzare il passaporto PRIMA di aprirlo.

### 1.1 Sezioni attivabili

Spunta le sezioni che vuoi includere nel passaporto stampato:

| Sezione | Descrizione | Default |
|---|---|---|
| Progressi Certificazione | Barra % completamento complessivo | ON |
| Radar Aree | Grafico radar principale | ON |
| Radar Dettagli per Area | Radar secondario per competenza | OFF |
| Piano d'Azione | Tabella punti di forza/debolezza | ON |
| Dettagli Competenze | Tabella con tutte le competenze | ON |
| Radar Duale (Quiz vs Auto) | Confronto quiz vs autovalutazione | OFF |
| Overlay Multi-Fonte | Radar con 4 fonti sovrapposte | OFF |
| Gap Analysis | Tabella divario autovalutazione vs quiz | OFF |
| Spunti Colloquio | Domande suggerite per il colloquio | OFF |
| Valutazione Coach | Griglia Bloom del coach | OFF |

Puoi **trascinare** le sezioni per cambiarne l'ordine nel documento stampato.

### 1.2 Formato punteggi

- **Percentuali (%)** → es. "80%" — più preciso, preferito per l'URC
- **Qualitativo** → es. "Buono", "Sufficiente" — più leggibile dal datore di lavoro

### 1.3 Sovrapposizioni radar

Attiva/disattiva le fonti nel grafico overlay:
- Autovalutazione studente
- Valutazione coach

### 1.4 Soglia minima competenze

Imposta la percentuale minima per includere una competenza nel passaporto.

- **Soglia globale** (impostata dall'amministratore): default 60%
- **Soglia personalizzata**: spunta "Soglia personalizzata" per sovrascrivere solo per questo studente

Le competenze sotto soglia appaiono sbiadite nel Garage e vengono escluse dal passaporto.

### 1.5 Profilo AI (campi per la generazione automatica)

Questi campi vengono usati dall'AI per personalizzare i commenti generati:

| Campo | Descrizione | Esempio |
|---|---|---|
| Settore/Mansione target | Il lavoro che lo studente cerca | "Meccanico CNC, officina di precisione" |
| Disponibilità | Orario disponibile | "Tempo pieno", "Part-time" |
| Mobilità | Raggio disponibile | "Canton Ticino", "Tutta la Svizzera" |
| Motivazione ricerca lavoro | Slider 0-100% | 75% |
| Punti di forza | Caratteristiche positive da valorizzare | "Esperienza 10 anni in Germania" |
| Note aggiuntive | Contesto rilevante per l'AI | "Ha un diploma in meccanica industriale" |

> **Importante:** più questi campi sono compilati, più i commenti AI saranno specifici e personalizzati.

---

## Parte 2: Il Passaporto Tecnico (commenti)

### 2.1 Struttura della pagina

Ogni area di competenza (A, B, C, ...) appare con:
- **Badge %** — punteggio dell'area (quiz o valutazione coach)
- **Campo commento** — testo scritto dal coach (modificabile)
- **4 pulsanti AI** — strumenti di assistenza alla scrittura

### 2.2 I 4 pulsanti AI per ogni area

| Pulsante | Funzione | Quando usarlo |
|---|---|---|
| **🤖 Gen** | Genera un commento da zero basandosi sui dati | Area vuota, primo utilizzo |
| **✨ Migliora** | Corregge grammatica e fluidità del testo esistente, senza cambiare i fatti | Hai scritto una bozza e vuoi renderla più professionale |
| **🔄 Riscrivi** | Riscrive completamente usando i dati + la tua bozza come contesto | Vuoi mantenere le tue osservazioni ma con una struttura migliore |
| **↩ Ripristina** | Torna al testo originale scritto dal coach | Dopo modifiche AI non soddisfacenti |

> **Nota sul Ripristina:** funziona solo se hai salvato almeno una volta con "Salva Commenti". Il sistema memorizza il testo originale del coach al primo salvataggio e non lo sovrascrive mai.

### 2.3 Pulsante "Genera Tutto"

In cima al passaporto appare un banner **"Genera automaticamente tutti i commenti"** se le aree sono vuote. Cliccandolo l'AI genera in un'unica chiamata tutti i commenti + la nota finale.

> **Attenzione:** il "Genera Tutto" usa l'AI con i dati oggettivi. Rileggi sempre i testi generati prima di approvare.

### 2.4 La Nota Finale

In fondo al passaporto c'è la sezione **"Nota Finale per il Datore di Lavoro"** — un testo di sintesi di ~100 parole.

Anche questa ha i 4 pulsanti AI (Gen/Migliora/Riscrivi/Ripristina), funzionano allo stesso modo delle aree.

La nota finale **non appare in stampa** se il campo è vuoto.

---

## Parte 3: Salvataggio e Approvazione

### 3.1 Barra inferiore

In fondo alla pagina (barra sticky) trovi sempre:

- **Stato approvazione** — "Non ancora approvato" o "Approvato il [data] da [coach]"
- **Salva Commenti** — salva tutti i commenti delle aree
- **Approva** / **Approvato — Annulla** — approva o revoca l'approvazione

### 3.2 Salva Commenti

Clicca **Salva Commenti** dopo ogni modifica. Il sistema:
1. Salva tutti i commenti delle aree nel database
2. Se è il primo salvataggio, crea una copia "originale" immutabile del tuo testo (usata da Ripristina)

> **Regola:** l'AI non sovrascrive mai la copia originale. Solo le tue modifiche manuali vengono protette come baseline.

### 3.3 Approva Passaporto

Quando il passaporto è completo e soddisfacente, clicca **Approva**.

**Cosa succede:**
- Viene salvato uno snapshot del passaporto con tutti i commenti, il settore e il tuo nome
- Il passaporto riceve un **timbro di approvazione** visibile in stampa
- I commenti vengono usati automaticamente come **esempi di stile** per la AI nelle generazioni future dello stesso settore

**Effetto sull'AI:** più passaporti vengono approvati per un settore, più la AI impara lo stile FTM e migliora la qualità dei testi generati automaticamente. È un sistema di apprendimento continuo.

Per revocare un'approvazione: clicca **"Approvato — Annulla"** (richiede conferma).

---

## Parte 4: Stampa

### Come stampare

1. Clicca il pulsante **"Stampa Passaporto"** in alto a destra
2. Oppure usa **Ctrl+P** dal browser

### Cosa appare nella stampa

- Header FTM rosso con logo e dati studente
- Solo le sezioni attivate nel Garage
- Radar SVG (compatibile con la stampa)
- Commenti coach per ogni area
- Nota finale (se compilata)
- Timbro di approvazione (se il passaporto è stato approvato)

### Cosa NON appare nella stampa

- Badge percentuali colorati
- Pulsanti AI (Gen/Migliora/Riscrivi/Ripristina)
- Barra inferiore (Salva/Approva)
- Campi input/textarea

---

## Parte 5: Qualità dei testi AI

### Come ottenere testi migliori

1. **Compila il Profilo AI nel Garage** — settore target, disponibilità, mobilità e punti di forza sono fondamentali
2. **Scrivi una bozza prima di "Riscrivi"** — l'AI usa le tue osservazioni come contesto e produce testi più aderenti alla realtà
3. **Usa "Migliora" per piccole correzioni** — mantiene i tuoi fatti, migliora solo la forma
4. **Approva passaporti di qualità** — ogni approvazione allena la AI sullo stile corretto per il settore

### Come l'AI interpreta i punteggi

| Punteggio | Fascia | Apertura del commento |
|---|---|---|
| ≥ 70% | Buono/Eccellente | Apre con ciò che lo studente sa fare concretamente |
| 50-69% | Discreto/Elementare | Apre con il punto di forza, poi indica sviluppi |
| 30-49% | Base | Apre con il contesto (background), poi descrive il gap |
| < 30% | Insufficiente | Apre direttamente con le lacune concrete |

> **Nota:** se esiste una valutazione coach Bloom per l'area, quella ha **priorità** sul punteggio quiz come metrica principale del commento.

---

## Domande frequenti

**D: Il commento AI non rispecchia la realtà dello studente — cosa faccio?**
R: Scrivi una bozza manuale con le tue osservazioni, poi usa "Riscrivi" — l'AI userà la tua bozza come contesto e produrrà un testo più accurato.

**D: Ho schiacciato "Ripristina" ma non funziona — perché?**
R: Il pulsante Ripristina funziona solo se hai prima salvato il passaporto con "Salva Commenti". Senza un salvataggio manuale precedente, non esiste una baseline da ripristinare.

**D: Posso modificare i commenti dopo l'approvazione?**
R: Sì. Puoi modificare e salvare anche dopo l'approvazione. Se vuoi aggiornare lo snapshot AI dovrai revocare e riapprovare.

**D: Lo studente può vedere il passaporto?**
R: Lo studente può accedere al passaporto solo se ha i permessi di visualizzazione del proprio report. Il timbro di approvazione è visibile anche allo studente.

**D: Il Garage FTM è separato per ogni studente?**
R: Sì, ogni configurazione del Garage (sezioni attive, soglia, formato, profilo AI) è salvata per studente e per corso.

---

## Riferimenti rapidi

| Pagina | URL |
|---|---|
| Passaporto Tecnico | `/local/competencymanager/technical_passport.php?userid=X&courseid=Y` |
| Garage FTM | `/local/competencymanager/garage_ftm.php?userid=X&courseid=Y` |
| Student Report | `/local/competencymanager/student_report.php?userid=X&courseid=Y` |
| Soglia globale (admin) | Amministrazione → Plugin → Competency Manager → Soglia minima % |
| Esempi stile AI (admin) | Amministrazione → Plugin → Competency Manager → Esempi di stile AI |
