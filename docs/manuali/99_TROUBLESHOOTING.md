# FTM - Guida alla Risoluzione Problemi

**Versione:** 1.0 | **Data:** 24 Gennaio 2026

---

## Indice

1. [Problemi di Accesso](#1-problemi-di-accesso)
2. [Problemi Dashboard](#2-problemi-dashboard)
3. [Problemi Report](#3-problemi-report)
4. [Problemi Import/Export](#4-problemi-importexport)
5. [Problemi Settori e Coach](#5-problemi-settori-e-coach)
6. [Errori Comuni](#6-errori-comuni)
7. [Contatti Supporto](#7-contatti-supporto)

---

## 1. Problemi di Accesso

### "Non riesco ad accedere a Moodle"

**Sintomi:**
- Pagina di login non carica
- Errore "credenziali non valide"
- Pagina bianca

**Soluzioni:**

| Problema | Soluzione |
|----------|-----------|
| Password dimenticata | Clicca "Password dimenticata" nel login |
| Credenziali errate | Verifica maiuscole/minuscole |
| Browser lento | Svuota cache (Ctrl+Shift+Delete) |
| Pagina non carica | Prova un altro browser (Chrome, Firefox) |

### "Non vedo il menu FTM"

**Sintomi:**
- Menu laterale senza voci FTM
- Errore "Non hai i permessi"

**Soluzioni:**

1. **Verifica il tuo ruolo:**
   - Vai su Profilo → I miei corsi
   - Controlla di essere iscritto ai corsi corretti

2. **Contatta l'amministratore:**
   - Potrebbe mancare il ruolo Coach/Segreteria

3. **Prova l'URL diretto:**
   - Coach: `/local/coachmanager/coach_dashboard_v2.php`
   - Segreteria: `/local/ftm_cpurc/index.php`

### "Accesso negato a una pagina"

**Sintomi:**
- Messaggio "Non hai i permessi per accedere"
- Pagina 403

**Soluzioni:**

1. Verifica di avere il ruolo corretto
2. Verifica di essere nel corso giusto
3. Contatta l'amministratore per i permessi

---

## 2. Problemi Dashboard

### "Non vedo nessuno studente"

**Sintomi:**
- Lista vuota
- Messaggio "Nessun risultato"

**Soluzioni:**

| Causa | Soluzione |
|-------|-----------|
| Filtri troppo restrittivi | Resetta tutti i filtri a "Tutti" |
| Corso sbagliato | Seleziona il corso corretto |
| Nessuno studente assegnato | Verifica con la segreteria |
| Dati non importati | Controlla l'import CSV |

**Come resettare i filtri:**
1. Seleziona "Tutti" in ogni menu filtro
2. Cancella il campo ricerca
3. Ricarica la pagina (F5)

### "La dashboard è molto lenta"

**Sintomi:**
- Caricamento lento (> 10 secondi)
- Pagina che si blocca

**Soluzioni:**

1. **Riduci i dati visualizzati:**
   - Applica filtri per ridurre la lista
   - Usa vista "Compatta"

2. **Problemi browser:**
   - Chiudi altre schede
   - Svuota cache
   - Prova Chrome o Firefox

3. **Problemi connessione:**
   - Verifica la connessione internet
   - Prova da un'altra rete

### "Il layout è strano / i colori sono sbagliati"

**Soluzioni:**

1. Svuota la cache del browser:
   - **Chrome:** Ctrl+Shift+Delete → Immagini e file nella cache
   - **Firefox:** Ctrl+Shift+Delete → Cache

2. Ricarica forzato: Ctrl+F5

3. Prova modalità incognito/privata

---

## 3. Problemi Report

### "Non riesco a salvare il report"

**Sintomi:**
- Pulsante "Salva" non funziona
- Errore dopo il salvataggio
- Dati persi

**Soluzioni:**

| Causa | Soluzione |
|-------|-----------|
| Connessione persa | Verifica internet, riprova |
| Sessione scaduta | Rieffettua il login |
| Campo troppo lungo | Riduci il testo |
| Report già finalizzato | Contatta segreteria per sblocco |

**Prevenzione:** Salva frequentemente! Copia il testo prima di salvare.

### "Non posso modificare un report finalizzato"

**Spiegazione:** I report finalizzati sono bloccati per evitare modifiche accidentali.

**Soluzione:**
1. Contatta la segreteria
2. Spiega cosa va modificato
3. Un amministratore può sbloccare il report

### "Il Word scaricato è vuoto o corrotto"

**Soluzioni:**

1. **Verifica che il report sia compilato:**
   - Apri il report nel sistema
   - Verifica che ci sia del testo

2. **Riprova il download:**
   - Ricarica la pagina
   - Clicca di nuovo su Export Word

3. **Prova un altro browser:**
   - Chrome e Firefox sono i più compatibili

4. **Verifica Microsoft Word:**
   - Prova ad aprire con LibreOffice
   - Potrebbe essere un problema di Word

---

## 4. Problemi Import/Export

### "Errore durante l'import CSV"

**Errori comuni e soluzioni:**

| Messaggio Errore | Causa | Soluzione |
|------------------|-------|-----------|
| "Email non valida" | Email malformata | Correggi email (es: @manca) |
| "Email duplicata" | Email già esistente | Rimuovi duplicato o usa altra email |
| "Campo obbligatorio" | Nome/cognome vuoto | Compila i campi mancanti |
| "Data non valida" | Formato data errato | Usa YYYY-MM-DD |
| "Errore codifica" | File non UTF-8 | Risalva come UTF-8 |
| "File troppo grande" | > 2MB | Dividi in file più piccoli |

**Come salvare in UTF-8:**
1. Apri il CSV con Blocco Note
2. File → Salva con nome
3. Codifica: UTF-8
4. Salva

### "Export Excel non parte"

**Soluzioni:**

1. **Popup bloccati:**
   - Controlla la barra del browser per popup bloccati
   - Consenti popup per questo sito

2. **Troppi dati:**
   - Applica filtri per ridurre
   - Prova in orari di minor carico

3. **Browser:**
   - Prova con Chrome
   - Disabilita estensioni

### "Export Word ZIP si blocca"

**Causa:** Troppi report da generare

**Soluzioni:**

1. **Riduci il numero:**
   - Esporta solo "Completi" invece di "Tutti"
   - Filtra per URC o settore prima

2. **Attendi:**
   - Non chiudere la pagina
   - Può richiedere 5+ minuti per molti report

3. **Riprova più tardi:**
   - In orari di minor carico del server

---

## 5. Problemi Settori e Coach

### "Lo studente non riceve i quiz corretti"

**Causa probabile:** Settore primario non assegnato o errato

**Soluzione:**
1. Apri la scheda studente
2. Tab Percorso → Assegnazione Settori
3. Verifica/imposta il settore **Primario**
4. Salva

### "Il coach non vede lo studente"

**Cause e soluzioni:**

| Causa | Soluzione |
|-------|-----------|
| Coach non assegnato | Assegna il coach allo studente |
| Coach sbagliato | Cambia assegnazione |
| Corso sbagliato | Verifica il corso dello studente |

### "Non riesco a salvare il coach/settore"

**Soluzioni:**

1. **Verifica la connessione:**
   - Il salvataggio è via AJAX
   - Serve connessione stabile

2. **Ricarica la pagina:**
   - F5 per ricaricare
   - Riprova il salvataggio

3. **Verifica i permessi:**
   - Solo la segreteria può assegnare

---

## 6. Errori Comuni

### Messaggi di Errore e Significato

| Messaggio | Significato | Azione |
|-----------|-------------|--------|
| "Sessione scaduta" | Login scaduto | Rieffettua il login |
| "Errore di connessione" | Internet assente | Verifica connessione |
| "Errore interno" | Problema server | Riprova, contatta supporto |
| "Non autorizzato" | Permessi mancanti | Contatta amministratore |
| "Record non trovato" | Dato cancellato | Verifica esistenza record |
| "Dati non validi" | Input errato | Controlla i campi compilati |

### Codici Errore HTTP

| Codice | Significato | Azione |
|--------|-------------|--------|
| 400 | Richiesta errata | Ricarica e riprova |
| 403 | Accesso negato | Verifica permessi |
| 404 | Pagina non trovata | Verifica URL |
| 500 | Errore server | Riprova, contatta supporto |
| 502/503 | Server non disponibile | Attendi e riprova |

---

## 7. Contatti Supporto

### Prima di Contattare

Raccogli queste informazioni:
1. **Cosa stavi facendo** quando è apparso l'errore
2. **Messaggio di errore** esatto (screenshot)
3. **URL della pagina** (copia dalla barra indirizzi)
4. **Browser** utilizzato (Chrome, Firefox, etc.)
5. **Ora e data** del problema

### Come Segnalare

**Email:** [inserire email supporto]

**Oggetto:** [FTM] Problema: [descrizione breve]

**Corpo:**
```
Nome: [tuo nome]
Ruolo: Coach / Segreteria
Data/Ora: [quando è successo]

Problema:
[descrizione dettagliata]

Passi per riprodurre:
1. [passo 1]
2. [passo 2]
3. [errore]

Messaggio di errore:
[copia esatto il messaggio]

Screenshot: [allega se possibile]
```

### Orari Supporto

| Giorno | Orario |
|--------|--------|
| Lunedì - Venerdì | 08:00 - 17:00 |
| Sabato - Domenica | Non disponibile |

### Urgenze

Per problemi **bloccanti** (sistema inaccessibile, perdita dati):
- Telefono: [inserire numero]
- Priorità alta

---

## Checklist Problemi

Prima di contattare il supporto, verifica:

- [ ] Ho provato a ricaricare la pagina?
- [ ] Ho provato con un altro browser?
- [ ] Ho svuotato la cache?
- [ ] Ho verificato la connessione internet?
- [ ] Ho controllato i miei permessi?
- [ ] Ho verificato i filtri attivi?
- [ ] Ho provato in modalità incognito?

---

*Manuale FTM - Troubleshooting v1.0*
