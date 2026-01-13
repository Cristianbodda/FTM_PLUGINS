# 📦 Update: Validazione XML integrata in Setup Universale

## Versione 1.0 - Opzione B

---

## 📋 COSA INCLUDE QUESTO PACCHETTO

| File | Descrizione |
|------|-------------|
| `setup_universale.php` | ✨ **MODIFICATO** - Step 3 con validazione integrata |
| `download_template.php` | 🆕 **NUOVO** - Gestisce download 4 template |
| `classes/xml_validator.php` | 🆕 **NUOVO** - Classe validazione XML |
| `classes/importer.php` | Invariato (incluso per completezza) |

---

## 🚀 INSTALLAZIONE

### 1. Backup
Prima di procedere, fai backup della cartella:
```
/local/competencyxmlimport/
```

### 2. Estrai i file
Estrai il contenuto dello ZIP direttamente nella cartella del plugin:
```
/local/competencyxmlimport/
```

### 3. Svuota la cache
In Moodle: **Amministrazione → Sviluppo → Svuota tutte le cache**

---

## ✨ NUOVE FUNZIONALITÀ

### Step 3 - Carica e Valida File XML

Il nuovo Step 3 ora include:

1. **4 Template Scaricabili**
   - 📄 Template XML (struttura Moodle)
   - 📊 Excel Master (mappatura competenze)
   - 📝 Template Word (formato leggibile)
   - 🤖 Istruzioni ChatGPT (prompt per generazione)

2. **Validazione Automatica**
   - Ogni file XML viene validato al caricamento
   - Verifica: nome domanda, testo, competenza, risposte
   - Badge colorati: ✅ OK, ⚠️ Warning, ❌ Errore

3. **Blocco se Errori**
   - Se ci sono errori critici, il pulsante "Avanti" è disabilitato
   - Dettagli espandibili per ogni file con problemi

---

## 🔍 COSA VIENE VALIDATO

Per ogni domanda:

| Controllo | Tipo |
|-----------|------|
| Nome domanda presente | ❌ Errore |
| Testo domanda presente | ❌ Errore |
| Testo troppo breve (<15 char) | ⚠️ Warning |
| Competenza estratta dal nome | ❌ Errore |
| Competenza esiste nel framework | ⚠️ Warning |
| Competenza del settore corretto | ⚠️ Warning |
| Almeno 1 risposta | ❌ Errore |
| Meno di 4 risposte | ⚠️ Warning |
| Risposta corretta presente | ❌ Errore |
| Multiple risposte corrette | ⚠️ Warning |

---

## 📸 COME FUNZIONA

```
┌─────────────────────────────────────────────────────────────┐
│  STEP 3: Carica File XML                                    │
├─────────────────────────────────────────────────────────────┤
│  📋 Scarica i Template                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ 📄 XML   │ │ 📊 Excel │ │ 📝 Word  │ │ 🤖 ChatGPT│      │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
├─────────────────────────────────────────────────────────────┤
│  📤 Trascina qui i file XML                                 │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  File per MECCANICA (2)                                     │
│                                                             │
│  📄 MECC_BASE.xml                                          │
│     40 domande • 38 OK • 2 ⚠️                              │
│     [Dettagli ▼]                                           │
│                                                             │
│  📄 MECC_APPR01.xml                                        │
│     25 domande • 25 OK                               ✅    │
│                                                             │
│  [← Indietro]    [Avanti → Configura Quiz]                 │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚠️ NOTE IMPORTANTI

- I **Warning** permettono di procedere (sono solo avvisi)
- Gli **Errori** bloccano il pulsante "Avanti"
- La validazione controlla il formato, non la correttezza del contenuto
- I template sono generati dinamicamente in base al settore selezionato

---

## 🔧 TROUBLESHOOTING

### "Classe xml_validator non trovata"
Verifica che il file `classes/xml_validator.php` sia nella posizione corretta.

### "Errore 404 su download_template.php"
Verifica che il file sia stato caricato nella cartella del plugin.

### Template vuoti o corrotti
Svuota la cache del browser e riprova.

---

*Sviluppato per il progetto FTM - Fondazione Terzo Millennio*
