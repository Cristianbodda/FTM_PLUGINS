# 📋 FORMATO STANDARD QUIZ FTM
## Guida per la Generazione Domande con AI (ChatGPT, Claude, etc.)

---

## 1. FORMATO DOMANDA OBBLIGATORIO

Ogni domanda **DEVE** seguire **ESATTAMENTE** questo formato:

```
**Q01**

Testo della domanda qui. Può includere termini in **grassetto** se necessario.

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
| **Domanda** | Testo libero, può includere `**grassetto**` | In un circuito... |
| **Risposte** | `A)`, `B)`, `C)`, `D)` - SEMPRE 4 opzioni | `A) Vero` |
| **Competenza** | `Competenza: SETTORE_AREA_NUMERO` | `Competenza: AUTOMAZIONE_OA_A2` |
| **Risposta** | `Risposta corretta: X` (una lettera) | `Risposta corretta: C` |

---

## 3. SETTORI E PREFISSI COMPETENZA

| Settore | Prefisso | Esempio Codice |
|---------|----------|----------------|
| Automazione | `AUTOMAZIONE_` | `AUTOMAZIONE_OA_A1`, `AUTOMAZIONE_MA_C2` |
| Automobile | `AUTOMOBILE_` | `AUTOMOBILE_MAu_A1`, `AUTOMOBILE_OA_B3` |
| Chimico-Farmaceutico | `CHIMFARM_` | `CHIMFARM_1C_01`, `CHIMFARM_6P_05` |
| Elettricità | `ELETTRICITÀ_` | `ELETTRICITÀ_IE_F2`, `ELETTRICITÀ_MA_A1` |
| Logistica | `LOGISTICA_` | `LOGISTICA_LO_A1`, `LOGISTICA_MA_B2` |
| Meccanica | `MECCANICA_` | `MECCANICA_DT_01`, `MECCANICA_CNC_03` |
| Metalcostruzione | `METALCOSTRUZIONE_` | `METALCOSTRUZIONE_MA_A1`, `METALCOSTRUZIONE_OA_B2` |

---

## 4. STRUTTURA CODICE COMPETENZA

```
SETTORE_PROFILO_AREA+NUMERO
```

**Esempi scomposti:**

| Codice | Settore | Profilo | Area | Numero |
|--------|---------|---------|------|--------|
| `AUTOMAZIONE_OA_A2` | AUTOMAZIONE | OA (Operatore) | A | 2 |
| `AUTOMAZIONE_MA_C5` | AUTOMAZIONE | MA (Manutentore) | C | 5 |
| `CHIMFARM_1C_01` | CHIMFARM | 1 | C | 01 |
| `MECCANICA_DT_03` | MECCANICA | - | DT | 03 |

---

## 5. ESEMPIO COMPLETO (5 domande)

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

Un ingresso PLC rimane sempre a "1" anche a sensore disattivato. Qual è la causa più probabile?

A) Errore di temporizzazione
B) Cortocircuito o cablaggio errato
C) Sensore sporco
D) Errore di alimentazione del PLC

Competenza: AUTOMAZIONE_OA_H4
Risposta corretta: B

**Q05**

In fase di diagnostica, è corretto procedere:

A) Dall'attuatore al sensore
B) Dal PLC verso il campo
C) Dalla segnalazione all'origine
D) A caso, per tentativi

Competenza: AUTOMAZIONE_OA_H5
Risposta corretta: C
```

---

## 6. PROMPT SUGGERITO PER CHATGPT

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

---

## 7. ERRORI DA EVITARE

❌ **NON fare:**
```
Q1. Domanda...        # Formato sbagliato (Q1. invece di **Q01**)
a) risposta           # Minuscolo
Risposta: C           # Manca "corretta"
Competenza AUTOMAZIONE_OA_A1  # Manca ":"
```

✅ **Formato CORRETTO:**
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

## 8. FORMATI ALTERNATIVI ACCETTATI

Il parser riconosce anche questi formati (per retrocompatibilità):

| Formato | Esempio |
|---------|---------|
| Standard | `Competenza: AUTOMAZIONE_OA_A1` |
| Con "Codice" | `Codice competenza: MECCANICA_DT_01` |
| Solo "Codice" | `Codice: ELETTRICITÀ_IE_F2` |
| Risposta variante | `✓ Risposta corretta: A` |

---

## 9. CHECKLIST PRIMA DI SALVARE

- [ ] Tutte le domande iniziano con `**Qxx**`
- [ ] Ogni domanda ha esattamente 4 risposte (A, B, C, D)
- [ ] Ogni domanda ha il codice competenza
- [ ] Ogni domanda ha la risposta corretta (una sola lettera)
- [ ] I codici competenza esistono nel framework FTM
- [ ] Il file è salvato come `.docx`

---

## 10. SUPPORTO

Per verificare i codici competenza validi, consulta:
- Dashboard Audit: `/local/competencyxmlimport/audit_competenze.php`
- Framework CSV: `Passaporto_tecnico_FTM-FTM-01.csv`

---

*Documento generato per il sistema FTM - Formazione Tecnica Moodle*
