# Manuali FTM - Istruzioni

Questa cartella contiene la documentazione completa per gli utenti del sistema FTM.

## Struttura

```
docs/manuali/
├── INDICE.md                    # Indice generale
├── README.md                    # Questo file
├── 00_GUIDA_RAPIDA.md          # Panoramica generale
├── 99_TROUBLESHOOTING.md       # Risoluzione problemi
├── coach/                       # Manuali per Coach
│   ├── 01_Dashboard_Coach.md
│   ├── 02_Gestione_Studenti.md
│   └── 03_Report_Competenze.md
├── segreteria/                  # Manuali per Segreteria
│   ├── 01_Dashboard_CPURC.md
│   ├── 02_Import_Export.md
│   └── 03_Gestione_Settori.md
└── screenshots/                 # Screenshot per i manuali
    └── (immagini da aggiungere)
```

## Aggiungere Screenshot

Per completare i manuali servono screenshot annotati.

### Screenshot Necessari

**Generali:**
- [ ] login.png - Pagina di login Moodle
- [ ] menu_ftm.png - Menu laterale con voci FTM

**Dashboard Coach:**
- [ ] dashboard_coach.png - Vista generale
- [ ] dashboard_barra.png - Barra filtri
- [ ] filtro_corso.png - Menu filtro corso
- [ ] filtro_gruppo.png - Menu filtro gruppo
- [ ] pulsanti_vista.png - Pulsanti cambio vista
- [ ] pulsanti_zoom.png - Pulsanti zoom
- [ ] card_studente.png - Card singolo studente
- [ ] card_pulsanti.png - Pulsanti azione nella card

**Scheda Studente:**
- [ ] student_card.png - Vista generale
- [ ] tab_navigazione.png - Tab della scheda
- [ ] tab_anagrafica.png - Tab anagrafica
- [ ] tab_assenze.png - Tab assenze
- [ ] tab_stage.png - Tab stage
- [ ] sezione_coach.png - Sezione coach assegnato
- [ ] multi_settore.png - Sezione multi-settore
- [ ] aggiungi_nota.png - Form aggiungi nota

**Report:**
- [ ] pulsante_report.png - Pulsante accesso report
- [ ] sezione_comportamento.png - Sezione compilazione
- [ ] salva_bozza.png - Pulsante salva bozza
- [ ] finalizza_report.png - Dialog finalizzazione
- [ ] esporta_word.png - Pulsante export Word
- [ ] report_competenze.png - Radar competenze

**Dashboard CPURC (Segreteria):**
- [ ] dashboard_cpurc.png - Vista generale
- [ ] accesso_cpurc.png - Come accedere
- [ ] filtro_ricerca.png - Campo ricerca
- [ ] filtro_urc.png - Filtro URC
- [ ] filtro_settore.png - Filtro settore
- [ ] filtro_stato_report.png - Filtro stato
- [ ] filtro_coach.png - Filtro coach
- [ ] assegna_coach.png - Dropdown coach
- [ ] assegna_coach_dashboard.png - Assegnazione dalla lista
- [ ] assegna_coach_scheda.png - Assegnazione dalla scheda
- [ ] pulsanti_azione.png - Pulsanti riga
- [ ] export_excel.png - Pulsante Excel
- [ ] export_excel_button.png - Click su Excel

**Import/Export:**
- [ ] accesso_import.png - Pagina import
- [ ] carica_file.png - Upload file
- [ ] anteprima_import.png - Anteprima dati
- [ ] report_import.png - Report risultati
- [ ] export_word_singolo.png - Export singolo
- [ ] export_zip.png - Pagina export bulk
- [ ] export_word_zip.png - Generazione ZIP

**Settori:**
- [ ] assegnazione_settori.png - Form settori
- [ ] elimina_settore_tendina.png - X nella tendina
- [ ] elimina_settore_badge.png - X nel badge
- [ ] sector_admin.png - Pagina admin settori

### Come Fare Screenshot

1. **Strumento consigliato:** Greenshot, Snipping Tool, o ShareX

2. **Dimensioni:** Larghezza ideale 800-1200px

3. **Annotazioni:** Usa frecce e riquadri per evidenziare elementi

4. **Formato:** PNG preferito

5. **Naming:** Usa esattamente i nomi indicati sopra

### Dove Salvare

Salva gli screenshot nella cartella:
```
docs/manuali/screenshots/
```

## Generare PDF

### Opzione 1: Pandoc (Tutti i sistemi)

Installa Pandoc: https://pandoc.org/installing.html

```bash
# Singolo file
pandoc 00_GUIDA_RAPIDA.md -o output/Guida_Rapida.pdf

# Manuale Coach completo
pandoc coach/*.md -o output/Manuale_Coach.pdf

# Manuale Segreteria completo
pandoc segreteria/*.md -o output/Manuale_Segreteria.pdf
```

### Opzione 2: VS Code + Estensione

1. Installa VS Code
2. Installa estensione "Markdown PDF"
3. Apri file .md
4. Ctrl+Shift+P → "Markdown PDF: Export (pdf)"

### Opzione 3: Typora (Editor Markdown)

1. Scarica Typora: https://typora.io/
2. Apri file .md
3. File → Export → PDF

### Opzione 4: Word (Microsoft)

1. Copia contenuto Markdown
2. Incolla in Word
3. Formatta manualmente
4. Salva come PDF

## Generare Pagine Moodle

Per inserire i manuali direttamente in Moodle:

1. **Crea una pagina libro:**
   - Corso → Attiva modifica → Aggiungi attività → Libro

2. **Per ogni capitolo:**
   - Copia il contenuto Markdown
   - Incolla nell'editor Moodle (usa formato HTML)
   - Converti la sintassi Markdown se necessario

3. **Alternativa - Plugin Markdown:**
   - Installa plugin "Atto Markdown" per Moodle
   - Incolla direttamente il Markdown

## Manutenzione

### Aggiornare i Manuali

Quando cambia una funzionalità:
1. Aggiorna il file .md corrispondente
2. Aggiorna lo screenshot se necessario
3. Aggiorna la data "Ultimo aggiornamento"
4. Rigenera i PDF

### Versioning

Mantieni la versione nei file:
```markdown
**Versione:** 1.0 | **Data:** 24 Gennaio 2026
```

Incrementa la versione per modifiche significative.

## Contatti

Per domande sulla documentazione:
- Email: [email supporto]
- GitHub: Issues sul repository
