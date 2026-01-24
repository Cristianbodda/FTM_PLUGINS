# FTM - Indice Manuali

**Sistema FTM - Fondazione Terzo Millennio**
**Versione:** 5.0 | **Data:** 24 Gennaio 2026

---

## Come Usare Questa Documentazione

### Per Iniziare
Leggi prima la **Guida Rapida** per avere una panoramica del sistema.

### Per Approfondire
Scegli il manuale adatto al tuo ruolo (Coach o Segreteria).

### Per Risolvere Problemi
Consulta la guida **Troubleshooting** alla fine.

---

## Manuali Disponibili

### Guida Rapida (Tutti)
| Documento | Descrizione | Pagine |
|-----------|-------------|--------|
| [00_GUIDA_RAPIDA.md](00_GUIDA_RAPIDA.md) | Panoramica generale del sistema | ~4 |

---

### Manuali Coach

| # | Documento | Descrizione | Pagine |
|---|-----------|-------------|--------|
| 1 | [coach/01_Dashboard_Coach.md](coach/01_Dashboard_Coach.md) | Come usare la Dashboard Coach | ~10 |
| 2 | [coach/02_Gestione_Studenti.md](coach/02_Gestione_Studenti.md) | Gestire le schede studente | ~8 |
| 3 | [coach/03_Report_Competenze.md](coach/03_Report_Competenze.md) | Compilare report e competenze | ~10 |

---

### Manuali Segreteria

| # | Documento | Descrizione | Pagine |
|---|-----------|-------------|--------|
| 1 | [segreteria/01_Dashboard_CPURC.md](segreteria/01_Dashboard_CPURC.md) | Dashboard CPURC completa | ~12 |
| 2 | [segreteria/02_Import_Export.md](segreteria/02_Import_Export.md) | Import CSV ed Export dati | ~10 |
| 3 | [segreteria/03_Gestione_Settori.md](segreteria/03_Gestione_Settori.md) | Gestire settori e coach | ~8 |

---

### Supporto

| Documento | Descrizione | Pagine |
|-----------|-------------|--------|
| [99_TROUBLESHOOTING.md](99_TROUBLESHOOTING.md) | Risoluzione problemi comuni | ~8 |

---

## Percorsi di Lettura Consigliati

### Sei un Coach Nuovo?
1. Guida Rapida
2. Dashboard Coach
3. Gestione Studenti
4. Report e Competenze

### Sei in Segreteria?
1. Guida Rapida
2. Dashboard CPURC
3. Import/Export
4. Gestione Settori

### Hai un Problema?
1. Troubleshooting
2. Se non risolto → Contatta supporto

---

## Convenzioni Usate

### Simboli

| Simbolo | Significato |
|---------|-------------|
| ✅ | Completato / Corretto |
| ❌ | Errore / Da evitare |
| ⚠️ | Attenzione / Importante |
| 💡 | Suggerimento |
| 📝 | Nota |

### Box Informativi

> **Suggerimento:** Testo utile ma non essenziale

> **Importante:** Informazione critica da non ignorare

> **Nota:** Informazione aggiuntiva

### Screenshot

Le immagini sono indicate con:
```
![Screenshot: Descrizione](percorso/immagine.png)
```

Le immagini devono essere aggiunte nella cartella `screenshots/`.

---

## Generare PDF

Per convertire i manuali in PDF:

### Metodo 1: Pandoc (Consigliato)
```bash
pandoc 00_GUIDA_RAPIDA.md -o Guida_Rapida.pdf
```

### Metodo 2: VS Code
1. Installa estensione "Markdown PDF"
2. Apri il file .md
3. Ctrl+Shift+P → "Markdown PDF: Export (pdf)"

### Metodo 3: Online
1. Vai su https://dillinger.io/
2. Incolla il contenuto Markdown
3. Export → PDF

---

## Contribuire

Per segnalare errori o proporre miglioramenti:
- Apri una issue su GitHub
- Contatta il team di sviluppo

---

*Fondazione Terzo Millennio - Sistema FTM v5.0*
