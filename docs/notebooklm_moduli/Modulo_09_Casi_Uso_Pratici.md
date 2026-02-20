# Modulo 9: Casi d'Uso Pratici - Workflow Completi

**Corso Video FTM Academy - Coach/Formatore**
Questo modulo copre 16 casi d'uso reali con workflow passo-passo completi: primo giorno con nuovo studente, invio autovalutazione, analisi gap critici e preparazione colloquio, valutazione formatore completa, confronto studenti, studente a fine percorso con export Word, uso dello Scheduler, iscrizione atelier, colloquio tecnico, gestione escalation, stampa personalizzata per azienda, analisi overlay 4 fonti, cambio settore, uso filtri combinati, verifica studente a meta percorso, export per riunione team.

---

## 15. Casi d'Uso Pratici

### 15.1 Caso 1: Primo Giorno - Nuovo Studente Assegnato

**Scenario:** Ti viene assegnato un nuovo studente, Mario Rossi, alla settimana 1.

**Passo 1.** Apri la Coach Dashboard V2.

**Passo 2.** Cerca Mario nella griglia. Se non lo vedi, resetta i filtri cliccando "Tutti".

> **Cosa vedrai:** La card di Mario con badge "Sett. 1" e barre di progresso tutte a 0%.

**Passo 3.** Espandi la card cliccando sull'header.

**Passo 4.** Verifica i dati: settore primario (medaglia 🥇), corso, email.

**Passo 5.** Se l'autovalutazione e mancante, clicca "📨 Sollecita" per inviargli il promemoria.

**Passo 6.** Nella sezione Note, scrivi: "[data] Primo contatto - studente assegnato."

**Passo 7.** Clicca "💾 Salva Note".

**Passo 8.** Se necessario, assegna le scelte settimanali (test + laboratorio) e clicca "✓ Salva".

> **Risultato atteso:** Mario riceve la notifica per l'autovalutazione.
> Le scelte sono assegnate. La tua nota e salvata per il follow-up.

### 15.2 Caso 2: Invio Autovalutazione e Monitoraggio

**Scenario:** 6 studenti non hanno completato l'autovalutazione.

**Passo 1.** Dalla Dashboard, clicca il Quick Filter "Manca Autoval (6)".

> **Cosa vedrai:** Solo i 6 studenti senza autovalutazione.

**Passo 2.** Per ognuno, clicca "📨 Sollecita".

**Passo 3.** Per monitorare la risposta, vai alla Self-Assessment Dashboard (`/local/selfassessment/index.php`).

**Passo 4.** Clicca il filtro "⏳ In attesa" per vedere chi non ha ancora risposto.

**Passo 5.** Se dopo 3 giorni non c'e risposta, ripeti il promemoria o contatta direttamente lo studente.

### 15.3 Caso 3: Analisi Report con Gap Critici - Preparazione Colloquio

**Scenario:** Devi preparare un colloquio con lo studente Anna che ha gap critici.

**Passo 1.** Dalla Dashboard, clicca "📊 Report" sulla card di Anna.

**Passo 2.** Nel Report, vai al tab FTM "📈 Gap Analysis".

> **Cosa vedrai:** Le competenze ordinate per magnitudine di gap.
> Le righe in rosso hanno gap > 30%.

**Passo 3.** Identifica le 3 aree con gap piu grande.

**Passo 4.** Clicca su "💬 Spunti Colloquio" nel pannello FTM.

> **Cosa vedrai:** Domande suggerite per le aree critiche di Anna.

**Passo 5.** Stampa gli spunti: clicca "🖨️ Stampa Personalizzata", seleziona solo "Gap Analysis" e "Spunti Colloquio", scegli tono "Colloquiale", clicca "🖨️ Genera Stampa".

**Passo 6.** Annota le osservazioni nelle Note Coach della Dashboard.

### 15.4 Caso 4: Valutazione Formatore Completa (dalla A alla Firma)

**Scenario:** Dopo 4 settimane di osservazione, devi completare la valutazione di Marco.

**Passo 1.** Dalla Dashboard, clicca "👤 Valutazione" sulla card di Marco.

**Passo 2.** Verifica il settore (🥇 Meccanica) nell'header.

**Passo 3.** Espandi l'area "A. Accoglienza e Diagnosi" cliccando sull'intestazione.

**Passo 4.** Per ogni competenza, clicca il pulsante del livello Bloom appropriato (es. "4" per Analizzare).

> **Cosa succede:** Il pulsante diventa viola. Il salvataggio parte automaticamente.

**Passo 5.** (Opzionale) Aggiungi note specifiche nei campi testo.

**Passo 6.** Ripeti per tutte le aree (A-G).

**Passo 7.** Scrivi le note generali in fondo.

**Passo 8.** Clicca "Salva e Completa".

> **Cosa succede:** Conferma, poi lo stato diventa "Completata" (banner verde).

**Passo 9.** Verifica il riepilogo. Se tutto e corretto, clicca "Firma Valutazione".

> **Cosa succede:** Conferma, poi la valutazione diventa "Firmata" (banner grigio).
> I pulsanti si disabilitano.

**Passo 10.** (Se necessario) Clicca "Autorizza Studente" per rendere visibile allo studente.

### 15.5 Caso 5: Confronto 2 Studenti per Bilancio Classe

**Scenario:** Vuoi confrontare Anna e Marco per capire il livello della classe.

**Passo 1.** Apri il Bilancio Competenze (`reports_v2.php?studentid=X`).

**Passo 2.** Clicca su "👥 Confronta Studenti".

**Passo 3.** Seleziona Anna dal primo dropdown.

**Passo 4.** Seleziona Marco dal secondo dropdown.

> **Cosa vedrai:** Tabella confronto con barre affiancate e radar sovrapposto.

### 15.6 Caso 6: Studente a Fine Percorso - Valutazione Finale + Export Word

**Scenario:** Lo studente Luca e alla settimana 6, devi concludere il percorso.

**Passo 1.** Dalla Dashboard, filtra "Fine 6 Sett." per trovare Luca.

> **Cosa vedrai:** La card di Luca con badge giallo "FINE 6 SETT."

**Passo 2.** Clicca "👤 Valutazione" e completa la valutazione finale.

**Passo 3.** Firma la valutazione.

**Passo 4.** Torna alla Dashboard. Clicca "📄 Word" sulla card di Luca.

> **Cosa succede:** Il browser scarica il file `.docx` con il report completo.

**Passo 5.** Apri il file Word e verificalo prima di consegnarlo.

### 15.7 Caso 7: Uso dello Scheduler per Pianificare la Settimana

**Scenario:** Devi organizzare la settimana prossima per il gruppo Giallo.

**Passo 1.** Apri lo Scheduler FTM.

**Passo 2.** Nel tab Calendario, naviga alla settimana desiderata con "Settimana succ."

**Passo 3.** Verifica le attivita gia programmate nella griglia.

**Passo 4.** Se serve una nuova attivita, clicca "📅 Nuova Attivita".

**Passo 5.** Compila il form: nome, tipo, gruppo Giallo, data, fascia, aula.

**Passo 6.** Clicca "📅 Crea Attivita".

### 15.8 Caso 8: Iscrizione Studente ad Atelier dalla Dashboard

**Scenario:** Lo studente Sara (settimana 4) deve frequentare un atelier obbligatorio.

**Passo 1.** Dalla Dashboard, espandi la card di Sara.

**Passo 2.** Scorri fino alla sezione "Atelier". Vedrai l'atelier con ⚠ "(Obbligatorio)".

**Passo 3.** Clicca "Iscrivimi".

**Passo 4.** Nel modal, seleziona una data disponibile (con posti liberi).

**Passo 5.** Conferma l'iscrizione.

> **Risultato:** Sara e iscritta all'atelier. L'alert obbligatorio scompare.

### 15.9 Caso 9: Colloquio Tecnico - Preparazione con Spunti + Note

**Scenario:** Domani hai il colloquio tecnico con Paolo.

**Preparazione (il giorno prima):**

**Passo 1.** Apri il Report di Paolo ("📊 Report" dalla Dashboard).

**Passo 2.** Tab "📊 Panoramica": identifica le aree forti e deboli.

**Passo 3.** Tab FTM "📈 Gap Analysis": nota le discrepanze.

**Passo 4.** Tab FTM "💬 Spunti Colloquio": leggi le domande suggerite.

**Passo 5.** Stampa: modal stampa personalizzata, seleziona Gap + Spunti, tono "Colloquiale".

**Passo 6.** Apri il Bilancio ("💬 Colloquio" dalla Dashboard), tab Colloquio: prepara le domande aggiuntive.

**Durante il colloquio:**

**Passo 7.** Usa i fogli stampati come guida.

**Passo 8.** Prendi appunti su carta o su un device.

**Dopo il colloquio:**

**Passo 9.** Dalla Dashboard, aggiorna le Note Coach con il resoconto.

**Passo 10.** Se necessario, aggiorna la Valutazione Formatore.

### 15.10 Caso 10: Gestione Studente senza Autovalutazione (Escalation)

**Scenario:** Lo studente non risponde dopo 2 reminder.

**Passo 1.** Verifica nella Self-Assessment Dashboard quanti reminder sono stati inviati.

**Passo 2.** Prova un contatto diretto (email personale, telefono).

**Passo 3.** Documenta i tentativi nelle Note Coach.

**Passo 4.** Se lo studente persiste a non rispondere:
- Informa la segreteria
- Valuta la possibilita di un colloquio in presenza
- Annota: "[data] Studente non raggiungibile. Segnalato a segreteria."

### 15.11 Caso 11: Stampa Personalizzata Report per Azienda

**Scenario:** Un'azienda ha chiesto il report di competenze dello studente.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Clicca "🖨️ Stampa Personalizzata".

**Passo 3.** Seleziona le sezioni: Panoramica, Piano, Radar Aree, Dettagli.

**Passo 4.** Seleziona tono "Formale" (terza persona, linguaggio professionale).

**Passo 5.** Seleziona il settore di interesse per l'azienda dal filtro settore.

**Passo 6.** Configura l'ordine: 1-Valutazione, 2-Radar, 3-Piano, 4-Dettagli.

**Passo 7.** Clicca "🖨️ Genera Stampa" e stampa in PDF.

### 15.12 Caso 12: Analisi Overlay 4 Fonti per Studente Complesso

**Scenario:** Lo studente ha risultati molto diversi tra le 4 fonti.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Nel tab FTM "⚙️ Configurazione", attiva "Grafico Sovrapposizione".

**Passo 3.** Clicca "Aggiorna Grafici".

> **Cosa vedrai:** Un radar con 4 poligoni sovrapposti:
> - Quiz, Autovalutazione, LabEval, Coach.

**Passo 4.** Identifica le aree dove i poligoni divergono:
- Se Quiz basso ma Autoval alto: lo studente si sovrastima
- Se Quiz alto ma Coach basso: forse lo studente studia ma non pratica
- Se LabEval alto ma Coach basso: verifica con il formatore lab

### 15.13 Caso 13: Cambio Settore Studente durante il Percorso

**Scenario:** Lo studente vuole cambiare da Meccanica ad Automobile.

**Passo 1.** Apri il Report dello studente.

**Passo 2.** Nel tab FTM "👤 Settori", modifica il settore primario.

**Passo 3.** Nella Valutazione Formatore, seleziona il nuovo settore dal dropdown.

> **Nota:** Le valutazioni del settore precedente restano salvate.
> Il sistema crea una nuova valutazione per il nuovo settore.

### 15.14 Caso 14: Uso Filtri Combinati per Trovare Studenti Critici

**Scenario:** Vuoi trovare tutti gli studenti del gruppo Rosso alla settimana 3+ con competenze sotto soglia.

**Passo 1.** Dalla Dashboard, apri i filtri avanzati.

**Passo 2.** Clicca il chip 🔴 Rosso.

**Passo 3.** Seleziona Settimana "3".

**Passo 4.** Seleziona Stato "Sotto soglia 50%".

> **Cosa vedrai:** Solo gli studenti che soddisfano TUTTI e 3 i criteri.

### 15.15 Caso 15: Verifica Completa di Uno Studente a Meta Percorso

**Scenario:** Lo studente e alla settimana 3, vuoi fare un check completo.

**Passo 1.** Dalla Dashboard, trova lo studente e verifica le 3 barre di progresso:
- Competenze: dovrebbe essere almeno 40-50% (quiz iniziali fatti)
- Autovalutazione: dovrebbe essere 100% (completata in settimana 1-2)
- Lab: puo essere ancora 0% (i laboratori iniziano dalla settimana 3)

**Passo 2.** Clicca "📊 Report" per aprire il Report Studente.

**Passo 3.** Tab "📊 Panoramica": identifica le aree forti (verde) e deboli (rosso) nel radar.

**Passo 4.** Tab FTM "📈 Gap Analysis": verifica se ci sono discrepanze significative tra autovalutazione e quiz.

**Passo 5.** Tab "📚 Piano": verifica il piano d'azione automatico. Le competenze critiche sono in fondo con le azioni suggerite.

**Passo 6.** Tab FTM "📊 Progresso": controlla la barra certificazione - quante competenze sono gia "certificate" (>=80%).

**Passo 7.** Inizia a compilare la Valutazione Formatore: clicca "👤 Valutazione" dalla Dashboard. Valuta almeno le competenze che hai potuto osservare.

**Passo 8.** Torna alla Dashboard e aggiorna le Note Coach con il riepilogo.

**Passo 9.** Se necessario, iscrivilo a un Atelier per rinforzare le aree deboli.

> **Risultato atteso:** Hai un quadro completo dello studente a meta percorso,
> una valutazione parziale iniziata e un piano di intervento documentato.

### 15.16 Caso 16: Export Dati per Riunione Team Coach

**Scenario:** Devi preparare materiale per la riunione settimanale dei coach.

**Passo 1.** Dalla Dashboard, clicca "Rapporto Classe" (pulsante blu in alto).

> **Cosa vedrai:** Un report aggregato con medie, distribuzione, confronti tra studenti.

**Passo 2.** Rivedi le statistiche aggregate. Prendi nota dei punti critici.

**Passo 3.** Torna alla Dashboard. Usa la vista Compatta per una panoramica veloce.

**Passo 4.** Filtra "Sotto Soglia 50%" per identificare gli studenti critici. Prepara un riassunto.

**Passo 5.** Filtra "Fine 6 Sett." per identificare chi deve uscire. Verifica le valutazioni.

**Passo 6.** Dallo Scheduler, vai al tab "📋 Attivita" e clicca "📥 Export Excel" per avere il calendario della settimana in formato foglio di calcolo.

**Passo 7.** Per ogni studente critico, genera una stampa dal Report (stampa personalizzata con Panoramica + Gap Analysis).

**Passo 8.** Usa il Bilancio Competenze -> Confronta Studenti per mostrare il range della classe.

---
