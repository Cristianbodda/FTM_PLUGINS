# 📦 Validazione XML + Template COMPLETI per tutti i Settori

## Versione 3.0 - TUTTI I 7 SETTORI DEL FRAMEWORK

---

## ✅ SETTORI SUPPORTATI (7 totali)

| # | Settore | Codice | Profili/Aree | Template |
|---|---------|--------|--------------|----------|
| 01 | **AUTOMOBILE** | `AUTOMOBILE_MR_XX`, `AUTOMOBILE_MAu_XX` | MR, MAu + Aree A-N | ✅ |
| 02 | **CHIMFARM** | `CHIMFARM_1G_XX`, `CHIMFARM_2G_XX`... | Aree 1G-9A | ✅ |
| 03 | **ELETTRICITA** | `ELETTRICITA_PE_XX`, `ELETTRICITA_IE_XX`... | PE, IE, EM, ER | ✅ |
| 04 | **AUTOMAZIONE** | `AUTOMAZIONE_MA_XX`, `AUTOMAZIONE_OA_XX` | MA, OA | ✅ |
| 05 | **LOGISTICA** | `LOGISTICA_LO_XX` | LO | ✅ |
| 06 | **MECCANICA** | `MECCANICA_DT_XX`, `MECCANICA_CNC_XX`... | 13 Aree | ✅ |
| 07 | **METALCOSTRUZIONE** | `METALCOSTRUZIONE_MC_XX`, `METALCOSTRUZIONE_DF_XX` | MC, DF + Aree E-J | ✅ |

---

## 📋 CONTENUTO PACCHETTO

### File PHP
| File | Descrizione |
|------|-------------|
| `setup_universale.php` | ✨ Step 3 con validazione integrata |
| `download_template.php` | 🆕 Genera template per TUTTI i settori |
| `classes/xml_validator.php` | 🆕 Classe validazione XML |
| `classes/importer.php` | Classe import esistente |

### Template XML (7 file)
| File | Settore | Domande esempio |
|------|---------|-----------------|
| `TEMPLATE_DOMANDE_AUTOMOBILE.xml` | 01 | 3 domande |
| `TEMPLATE_DOMANDE_CHIMFARM.xml` | 02 | 3 domande |
| `TEMPLATE_DOMANDE_ELETTRICITA.xml` | 03 | 3 domande |
| `TEMPLATE_DOMANDE_AUTOMAZIONE.xml` | 04 | 3 domande |
| `TEMPLATE_DOMANDE_LOGISTICA.xml` | 05 | 3 domande |
| `TEMPLATE_DOMANDE_MECCANICA.xml` | 06 | 3 domande |
| `TEMPLATE_DOMANDE_METALCOSTRUZIONE.xml` | 07 | 3 domande |

---

## 🚀 INSTALLAZIONE

1. **Backup** della cartella `/local/competencyxmlimport/`
2. **Estrai** lo ZIP nella cartella del plugin
3. **Svuota cache** Moodle: Amministrazione → Sviluppo → Svuota cache
4. **Testa** il Setup Universale per ogni settore

---

## 📐 FORMATO CODICI COMPETENZA

### AUTOMOBILE (01)
```
AUTOMOBILE_[PROFILO]_[AREA][NUMERO]

Profili: MR (Riparatore), MAu (Automazione)
Aree: A-N (14 aree)

Esempi:
- AUTOMOBILE_MR_A1  → Accoglienza
- AUTOMOBILE_MR_B3  → Motore
- AUTOMOBILE_MAu_H1 → ADAS
```

### CHIMFARM (02)
```
CHIMFARM_[AREA]_[NUMERO]

Aree: 1G, 1C, 1O, 2G, 3C, 4S, 5S, 6P, 7S, 8T, 9A

Esempi:
- CHIMFARM_1G_01 → Gestione sostanze
- CHIMFARM_2G_01 → Vettori energetici
```

### ELETTRICITA (03)
```
ELETTRICITA_[PROFILO]_[AREA][NUMERO]

Profili: PE, IE, EM, ER

Esempi:
- ELETTRICITA_PE_A1 → Progettazione
- ELETTRICITA_IE_B1 → Installazione
```

### AUTOMAZIONE (04)
```
AUTOMAZIONE_[PROFILO]_[AREA][NUMERO]

Profili: MA (Montatore), OA (Operatore)

Esempi:
- AUTOMAZIONE_MA_A1 → Montatore
- AUTOMAZIONE_OA_B1 → Operatore
```

### LOGISTICA (05)
```
LOGISTICA_LO_[AREA][NUMERO]

Profilo: LO (unico)

Esempi:
- LOGISTICA_LO_A1 → Identificazione
- LOGISTICA_LO_B1 → Magazzino
```

### MECCANICA (06)
```
MECCANICA_[AREA]_[NUMERO]

13 Aree: LMB, LMC, CNC, ASS, MIS, GEN, MAN, DT, AUT, PIAN, SAQ, CSP, PRG

Esempi:
- MECCANICA_DT_01  → Disegno tecnico
- MECCANICA_CNC_01 → CNC
- MECCANICA_LMB_01 → Lavorazioni base
```

### METALCOSTRUZIONE (07)
```
METALCOSTRUZIONE_[PROFILO]_[AREA][NUMERO]

Profili: MC, DF
Aree: E, F, G, H, I, J

Esempi:
- METALCOSTRUZIONE_MC_E1 → Trattamenti
- METALCOSTRUZIONE_DF_I1 → CAD/CAM
```

---

## 🔍 VALIDAZIONE AUTOMATICA

Per ogni domanda viene verificato:

| Controllo | Errore/Warning |
|-----------|---------------|
| Nome domanda presente | ❌ Errore |
| Testo domanda presente | ❌ Errore |
| Competenza nel nome | ❌ Errore |
| Competenza esiste nel framework | ⚠️ Warning |
| Competenza del settore corretto | ⚠️ Warning |
| Almeno 4 risposte | ⚠️ Warning |
| Risposta corretta presente | ❌ Errore |

---

## 📸 INTERFACCIA STEP 3

```
┌─────────────────────────────────────────────────────────────┐
│  📋 Scarica i Template                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │📄 XML    │ │📊 Excel  │ │📝 Word   │ │🤖 ChatGPT│       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
├─────────────────────────────────────────────────────────────┤
│  File per [SETTORE] (N)                                     │
│                                                             │
│  📄 TEMPLATE_DOMANDE_[SETTORE].xml                         │
│     3 domande • 3 OK                          ✅ Valido    │
│                                                             │
│  [← Indietro]    [Avanti → Configura Quiz]                 │
└─────────────────────────────────────────────────────────────┘
```

---

*Sviluppato per il progetto FTM - Fondazione Terzo Millennio*
*Framework: Passaporto tecnico FTM - 591 competenze totali*
