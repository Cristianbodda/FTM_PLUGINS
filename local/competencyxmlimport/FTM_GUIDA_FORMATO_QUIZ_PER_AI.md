# FORMATO STANDARD QUIZ FTM
## Guida per la Generazione Domande con AI (ChatGPT, Claude, etc.)

**Versione 4.0 - Gennaio 2026**

---

## 1. FORMATO DOMANDA OBBLIGATORIO

Ogni domanda **DEVE** seguire **ESATTAMENTE** questo formato:

```
**Q01**

Testo della domanda qui. Puo includere termini in **grassetto** se necessario.

A) Prima opzione di risposta
B) Seconda opzione di risposta
C) Terza opzione di risposta
D) Quarta opzione di risposta

Competenza: SETTORE_AREA_NUMERO
Risposta corretta: X
```

---

## 2. REGOLE OBBLIGATORIE

| Elemento | Formato | Esempio |
|----------|---------|---------|
| **Numero** | `**Q01**`, `**Q02**`, etc. | `**Q01**` |
| **Domanda** | Testo libero, puo includere `**grassetto**` | In un circuito... |
| **Risposte** | `A)`, `B)`, `C)`, `D)` - SEMPRE 4 opzioni | `A) Vero` |
| **Competenza** | `Competenza: SETTORE_AREA_NUMERO` | `Competenza: AUTOMAZIONE_OA_A2` |
| **Risposta** | `Risposta corretta: X` (una lettera) | `Risposta corretta: C` |

---

## 3. SETTORI E PREFISSI COMPETENZA (7 Settori)

| # | Settore | Prefisso | Esempio Codice |
|---|---------|----------|----------------|
| 01 | Automobile | `AUTOMOBILE_` | `AUTOMOBILE_MAu_A1`, `AUTOMOBILE_OA_B3` |
| 02 | Chimico-Farmaceutico | `CHIMFARM_` | `CHIMFARM_1C_01`, `CHIMFARM_6P_05` |
| 03 | Elettricita | `ELETTRICITA_` | `ELETTRICITA_IE_F2`, `ELETTRICITA_MA_A1` |
| 04 | Automazione | `AUTOMAZIONE_` | `AUTOMAZIONE_OA_A1`, `AUTOMAZIONE_MA_C2` |
| 05 | Logistica | `LOGISTICA_` | `LOGISTICA_LO_A1`, `LOGISTICA_LO_F5` |
| 06 | Meccanica | `MECCANICA_` | `MECCANICA_DT_01`, `MECCANICA_CNC_03` |
| 07 | Metalcostruzione | `METALCOSTRUZIONE_` | `METALCOSTRUZIONE_MA_A1`, `METALCOSTRUZIONE_OA_B2` |

---

## 4. STRUTTURA CODICE COMPETENZA

```
SETTORE_PROFILO_AREA+NUMERO
```

**Esempi scomposti:**

| Codice | Settore | Profilo/Area | Numero |
|--------|---------|--------------|--------|
| `AUTOMAZIONE_OA_A2` | AUTOMAZIONE | OA (Operatore) - A | 2 |
| `AUTOMAZIONE_MA_C5` | AUTOMAZIONE | MA (Manutentore) - C | 5 |
| `CHIMFARM_1C_01` | CHIMFARM | 1C | 01 |
| `MECCANICA_DT_03` | MECCANICA | DT (Disegno Tecnico) | 03 |
| `MECCANICA_LMC_01` | MECCANICA | LMC (Lavorazioni MC) | 01 |
| `LOGISTICA_LO_F5` | LOGISTICA | LO | F5 |

---

## 5. FORMATI ALTERNATIVI PER SETTORE

### FORMATO LOGISTICA (con codice ID)
```
1. LOG_BASE_Q01

Competenza: LOGISTICA_LO_F5

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: A
```

### FORMATO MECCANICA (con prefisso lettera)
```
A1. Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: B
Codice competenza: MECCANICA_LMC_01
```

### FORMATO CON "Codice competenza:"
```
**Q01**

Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Codice competenza: MECCANICA_DT_01
Risposta corretta: A
```

---

## 6. ESEMPIO COMPLETO (5 domande)

```
**Q01**

In un circuito in **corrente alternata**, il valore efficace (RMS) di una tensione rappresenta:

A) Il valore massimo della tensione
B) Il valore medio aritmetico
C) Il valore equivalente in DC
D) Il valore istantaneo

Competenza: AUTOMAZIONE_OA_A2
Risposta corretta: C

**Q02**

La **frequenza** di una tensione alternata indica:

A) L'ampiezza del segnale
B) Il numero di cicli al secondo
C) Il valore massimo della tensione
D) La potenza assorbita

Competenza: AUTOMAZIONE_OA_A2
Risposta corretta: B

**Q03**

Un **condensatore** in corrente alternata si comporta come:

A) Un corto circuito fisso
B) Una resistenza costante
C) Un'impedenza dipendente dalla frequenza
D) Un interruttore

Competenza: AUTOMAZIONE_MA_C2
Risposta corretta: C

**Q04**

Un ingresso PLC rimane sempre a "1" anche a sensore disattivato. Qual e la causa piu probabile?

A) Errore di temporizzazione
B) Cortocircuito o cablaggio errato
C) Sensore sporco
D) Errore di alimentazione del PLC

Competenza: AUTOMAZIONE_OA_H4
Risposta corretta: B

**Q05**

In fase di diagnostica, e corretto procedere:

A) Dall'attuatore al sensore
B) Dal PLC verso il campo
C) Dalla segnalazione all'origine
D) A caso, per tentativi

Competenza: AUTOMAZIONE_OA_H5
Risposta corretta: C
```

---

## 7. PROMPT SUGGERITO PER CHATGPT

Copia questo prompt per generare nuove domande:

```
Genera [NUMERO] domande quiz per il settore [SETTORE] seguendo ESATTAMENTE questo formato:

**Q01**

[Testo domanda]

A) [Opzione A]
B) [Opzione B]
C) [Opzione C]
D) [Opzione D]

Competenza: [CODICE_COMPETENZA]
Risposta corretta: [A/B/C/D]

REGOLE:
1. Usa SOLO questi codici competenza: [LISTA CODICI]
2. La risposta corretta deve essere UNA sola lettera
3. Mantieni la numerazione progressiva Q01, Q02, Q03...
4. NON aggiungere spiegazioni dopo la risposta
5. Le risposte devono essere plausibili (evita opzioni palesemente errate)
```

### Prompt specifico per MECCANICA:
```
Genera 25 domande quiz per MECCANICA seguendo questo formato:

1. Testo della domanda...

A) Risposta A
B) Risposta B
C) Risposta C
D) Risposta D

Risposta corretta: X
Codice competenza: MECCANICA_XX_YY

Usa questi codici competenza:
- MECCANICA_DT_01 a DT_04 (Disegno Tecnico)
- MECCANICA_LMC_01 a LMC_05 (Lavorazioni Macchine Convenzionali)
- MECCANICA_CNC_01 a CNC_05 (CNC e Tecnologie Digitali)
- MECCANICA_MAN_01 a MAN_05 (Manutenzione)
- MECCANICA_AUT_01 a AUT_05 (Automazione)
- MECCANICA_CSP_01 a CSP_05 (Controllo Statistico Processo)
- MECCANICA_PRG_01 a PRG_10 (Progettazione)
```

---

## 8. ERRORI DA EVITARE

**NON fare:**
```
Q1. Domanda...        # Formato sbagliato (Q1. invece di **Q01**)
a) risposta           # Minuscolo
Risposta: C           # Manca "corretta"
Competenza AUTOMAZIONE_OA_A1  # Manca ":"
```

**Formato CORRETTO:**
```
**Q01**

Domanda...

A) Risposta
B) Risposta
C) Risposta
D) Risposta

Competenza: AUTOMAZIONE_OA_A1
Risposta corretta: C
```

---

## 9. FORMATI ALTERNATIVI ACCETTATI (Retrocompatibilita)

Il parser riconosce anche questi formati:

| Formato | Esempio |
|---------|---------|
| Standard | `Competenza: AUTOMAZIONE_OA_A1` |
| Con "Codice" | `Codice competenza: MECCANICA_DT_01` |
| Solo "Codice" | `Codice: ELETTRICITA_IE_F2` |
| Risposta variante | `Risposta corretta: A` |
| Con checkmark | `Risposta corretta: A` |
| Competenza (F2) | `Competenza (F2): CHIMFARM_7S_01` |
| Competenza (CO) | `Competenza (CO): AUTOMOBILE_MAu_G10` |
| Competenza collegata | `Competenza collegata: AUTOMOBILE_MR_A1` |

---

## 10. CHECKLIST PRIMA DI SALVARE

- [ ] Tutte le domande iniziano con `**Qxx**` o formato alternativo supportato
- [ ] Ogni domanda ha esattamente 4 risposte (A, B, C, D)
- [ ] Ogni domanda ha il codice competenza
- [ ] Ogni domanda ha la risposta corretta (una sola lettera)
- [ ] I codici competenza esistono nel framework FTM
- [ ] Il file e salvato come `.docx`

---

## 11. DEBUG E VERIFICA

Se il file non viene riconosciuto:
1. Vai a `/local/competencyxmlimport/debug_word.php`
2. Carica il file Word
3. Verifica i pattern rilevati
4. Il sistema mostra quale formato e stato rilevato

---

## 12. SUPPORTO

Per verificare i codici competenza validi, consulta:
- Dashboard Audit: `/local/competencyxmlimport/audit_competenze.php`
- Framework CSV: `Passaporto_tecnico_FTM-FTM-01.csv`

---

*Documento generato per il sistema FTM - Formazione Tecnica Moodle*
*Versione 4.0 - Gennaio 2026*
