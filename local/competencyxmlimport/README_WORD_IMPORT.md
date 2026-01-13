# 📖 Setup Universale - Import Word con Verifica Excel

## 🆕 Versione 5.0 - Gennaio 2026

Questa versione include:
- ✅ Import file Word (.docx)
- ✅ Revisione domande con correzione interattiva
- ✅ **NUOVO:** Verifica contro file Excel di controllo
- ✅ Conversione automatica Word → XML Moodle

---

## 📦 FILE DEL PACCHETTO

| File | Descrizione | Tipo |
|------|-------------|------|
| `setup_universale.php` | Pagina principale | **AGGIORNATO** |
| `download_template.php` | Download template | **AGGIORNATO** |
| `classes/word_parser.php` | Parser file .docx | Esistente |
| `classes/word_import_helper.php` | Helper import Word | Esistente |
| `classes/excel_reader.php` | Lettore file .xlsx | **NUOVO** |
| `classes/excel_verifier.php` | Verificatore Word vs Excel | **NUOVO** |
| `ajax/word_import.php` | AJAX per Word | Esistente |
| `ajax/excel_verify.php` | AJAX per verifica Excel | **NUOVO** |

---

## 🚀 INSTALLAZIONE

### 1. Backup
```bash
cp -r /local/competencyxmlimport /local/competencyxmlimport_backup
```

### 2. Copia file
```
/local/competencyxmlimport/
├── setup_universale.php      ← SOSTITUISCI
├── download_template.php     ← SOSTITUISCI
├── classes/
│   ├── word_parser.php       ← copia se non presente
│   ├── word_import_helper.php ← copia se non presente
│   ├── excel_reader.php      ← NUOVO
│   └── excel_verifier.php    ← NUOVO
└── ajax/
    ├── word_import.php       ← copia se non presente
    └── excel_verify.php      ← NUOVO
```

### 3. Svuota cache Moodle
Amministrazione → Sviluppo → Svuota tutte le cache

### 4. Test
Vai a: `setup_universale.php?courseid=2`

---

## 🔄 WORKFLOW COMPLETO

```
┌─────────────────────────────────────────────────────────────────────┐
│  STEP 1-2: Seleziona Framework e Settore                           │
├─────────────────────────────────────────────────────────────────────┤
│  STEP 3: Upload File                                                │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ 📁 Carica file .xml o .docx                                   │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              ↓                                      │
│  📝 REVISIONE FILE WORD                                            │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ File: CHIMICA_APPR03.docx                                     │ │
│  │ ✅ 25 domande valide                                          │ │
│  │                                                               │ │
│  │ ☑️ Verifica con file Excel   [📥 Scarica Template]           │ │
│  │ ┌───────────────────────────────────────────────────────────┐│ │
│  │ │ 📊 Carica Excel: [Report_CHIMFARM.xlsx]                   ││ │
│  │ │ Foglio: [QC_APPR03 ▼]                                     ││ │
│  │ │ Colonne: Q | Competenza | Risposta                        ││ │
│  │ │ [🔍 Esegui Verifica]                                      ││ │
│  │ └───────────────────────────────────────────────────────────┘│ │
│  │                                                               │ │
│  │ 📋 RISULTATO: ✅ 23/25 OK | ⚠️ 2 discrepanze                │ │
│  │ ┌───────────────────────────────────────────────────────────┐│ │
│  │ │ Q12: Competenza Word=2M_01 Excel=2M_02                    ││ │
│  │ │      [Usa Excel] [Mantieni Word]                          ││ │
│  │ └───────────────────────────────────────────────────────────┘│ │
│  │                                                               │ │
│  │ [✓ Converti in XML]                                          │ │
│  └───────────────────────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────────────┤
│  STEP 4-5: Configura Quiz e Esegui Import                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📊 STRUTTURA FILE EXCEL DI CONTROLLO

### Foglio QC (uno per ogni quiz)

Il file Excel deve avere un foglio per ogni quiz con questa struttura:

| Q | Codice_competenza_CHIMFARM | Risposta_corretta |
|---|---------------------------|-------------------|
| Q01 | CHIMFARM_4S_13 | B |
| Q02 | CHIMFARM_4S_08 | B |
| Q03 | CHIMFARM_4S_02 | B |
| Q04 | CHIMFARM_1C_07 | C |
| Q05 | CHIMFARM_5S_06 | C |
| ... | ... | ... |

### Naming convention fogli
- `QC_TEST_BASE` - per quiz Test Base
- `QC_APPR01` - per quiz Approfondimento 01
- `QC_APPR02` - per quiz Approfondimento 02
- `QC_APPR03` - per quiz Approfondimento 03
- `QC_OBB01` - per quiz Obbligatorio 01

### Colonne richieste

| Colonna | Descrizione | Formato |
|---------|-------------|---------|
| **Q** | Numero domanda | Q01, Q02, ... Q25 |
| **Codice_competenza_XXX** | Codice competenza | SETTORE_XX_YY |
| **Risposta_corretta** | Risposta | A, B, C o D |

Il sistema rileva automaticamente le colonne, ma puoi selezionarle manualmente.

---

## 🔍 FUNZIONALITÀ VERIFICA EXCEL

### Cosa verifica
1. **Numero domande** - Word e Excel hanno lo stesso numero?
2. **Competenze** - Ogni Q ha la stessa competenza in Word e Excel?
3. **Risposte** - La risposta corretta coincide?

### Gestione discrepanze

Quando il sistema trova una discrepanza:

```
⚠️ Q12: Competenza
   Word: CHIMFARM_2M_01
   Excel: CHIMFARM_2M_02
   [Usa Excel] [Mantieni Word]
```

- **Usa Excel**: Aggiorna il valore nel Word con quello dell'Excel
- **Mantieni Word**: Ignora la discrepanza e usa il valore del Word

### Blocco conversione

Se ci sono discrepanze non risolte, il pulsante "Converti in XML" è **disabilitato**.
Devi risolvere tutte le discrepanze prima di procedere.

---

## 📐 FORMATI WORD SUPPORTATI

### Formato 1 - Standard
```
Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Competenza: CHIMFARM_7S_01 | Risposta corretta: B
```

### Formato 2 - F2 (con descrizione)
```
Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Competenza (F2): CHIMFARM_7S_01 — Descrizione competenza

Risposta corretta: B
```

### Formato 3 - LogStyle V2
```
Q01

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B

Competenza (F2): CHIMFARM_7S_01 — Descrizione
```

---

## 💡 WORKFLOW CONSIGLIATO CON CHATGPT

### 1. Prepara il template Excel
Crea un foglio QC vuoto con le colonne:
- Q
- Codice_competenza
- Testo_domanda
- Risposta_A
- Risposta_B
- Risposta_C
- Risposta_D
- Risposta_corretta

### 2. Chiedi a ChatGPT
```
Genera 25 domande a risposta multipla sul tema [TEMA].
Ogni domanda deve:
- Avere 4 risposte (A, B, C, D)
- Una sola risposta corretta
- Essere collegata a una competenza del framework FTM

Compila questa tabella:
| Q | Codice_competenza | Testo | A | B | C | D | Corretta |
```

### 3. Copia in Excel
Incolla le domande nel foglio QC del tuo Excel Master.

### 4. Genera Word (opzionale)
Dall'Excel, genera il Word per revisione umana.

### 5. Importa in Moodle
Upload Word → Verifica vs Excel → Converti → Quiz creato!

---

## 🔧 TROUBLESHOOTING

### "Nessun file Excel caricato"
- Ricarica la pagina e carica di nuovo l'Excel

### "Nessun file Word caricato"
- Carica prima il Word, poi l'Excel

### Colonne non rilevate automaticamente
- Seleziona manualmente le colonne corrette dal dropdown

### Discrepanze con valore "1" o vuoto
- Hai selezionato il foglio sbagliato (es. Copertura invece di QC)
- Verifica che il foglio abbia le colonne Q, Competenza, Risposta

### Le competenze non vengono trovate
- Verifica che i codici siano nel formato SETTORE_XX_YY
- Usa il pannello di correzione per sistemare manualmente

---

## 📁 STRUTTURA CARTELLE

```
/local/competencyxmlimport/
├── setup_universale.php        # Pagina principale
├── download_template.php       # Download template
├── classes/
│   ├── word_parser.php         # Parser Word
│   ├── word_import_helper.php  # Helper Word
│   ├── excel_reader.php        # Lettore Excel
│   ├── excel_verifier.php      # Verificatore
│   ├── xml_validator.php       # Validatore XML
│   └── importer.php            # Importatore
├── ajax/
│   ├── word_import.php         # AJAX Word
│   └── excel_verify.php        # AJAX Excel
├── xml/                        # File XML generati
└── templates/                  # Template Mustache
```

---

## 📞 SUPPORTO

Per problemi o suggerimenti:
- Usa il pulsante 👎 in Moodle per feedback
- Contatta il supporto FTM

---

*Sviluppato per FTM - Fondazione Terzo Millennio*
*Versione 5.0 - Gennaio 2026*
