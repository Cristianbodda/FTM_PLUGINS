# FTM Plugins - Workflow Ottimizzato con Claude Code

**Ultimo aggiornamento:** 22 Gennaio 2026

## Quick Reference

### Comandi Principali
| Comando | Descrizione |
|---------|-------------|
| `/ftm-start` | **AVVIA SESSIONE** - Health check, git status, attiva agenti |
| `/ftm-review` | **CODE REVIEW** - 4 agenti paralleli analizzano il codice |
| `/ftm-validate` | **VALIDAZIONE** - Sintassi PHP, struttura, security scan |
| `/ftm-thorough` | **3-PASS VERIFICATION** - Verifica completa prima del commit |
| `/ftm-deepreview` | **DEEP REVIEW** - Analisi approfondita con tutti gli agenti |
| `/ftm-health` | **HEALTH CHECK** - Verifica rapida con Playwright |
| `/ftm-release` | **RELEASE** - Prepara e pubblica nuova versione |

### Comandi Feature Tracking (AutoCoder-style)
| Comando | Descrizione |
|---------|-------------|
| `/ftm-features` | **LISTA FEATURES** - Mostra features per plugin e stato |
| `/ftm-progress` | **AVANZAMENTO** - Report visuale progress per plugin |
| `/ftm-next` | **PROSSIMA FEATURE** - Suggerisce prossima feature da implementare |
| `/ftm-complete` | **COMPLETA FEATURE** - Marca feature come completata |

### Comandi Legacy
| Comando | Descrizione |
|---------|-------------|
| `/security-check` | Valida sicurezza file PHP modificati |
| `/test-ftm` | Analizza risultati Test Suite |
| `/moodle-review` | Review codice standard Moodle |
| `/design-review` | Review UI/UX con Playwright |

---

## PLAYWRIGHT COME PROJECT MANAGER (NUOVO)

### Concetto
Usare Playwright non solo per test UI, ma come **Project Manager automatizzato**:
- Verifica automatica stato funzionalita dopo ogni deploy
- Screenshot comparativi prima/dopo modifiche
- Report visuale dello stato del sistema
- Monitoraggio regressioni UI

### Script di Test Disponibili

| Script | Percorso | Funzione |
|--------|----------|----------|
| `check_v2.mjs` | Desktop | Test visuale dashboard V2 (browser visibile) |
| `test_fix_v2.mjs` | Desktop | Verifica CSS applicato |
| `test_dashboard_v2.mjs` | Desktop | Test completo viste e zoom |

### Workflow PM con Playwright

```
1. Modifica codice
2. Upload FTP sul server test
3. Esegui script Playwright: node test_dashboard_v2.mjs
4. Verifica screenshot generati
5. Se OK -> commit, altrimenti -> fix e ripeti
```

### Comandi Rapidi Playwright

```bash
# Test visuale con browser aperto (30 sec)
node C:/Users/cristian.bodda/Desktop/check_v2.mjs

# Verifica CSS
node C:/Users/cristian.bodda/Desktop/test_fix_v2.mjs

# Test completo dashboard
node C:/Users/cristian.bodda/Desktop/test_dashboard_v2.mjs
```

### Screenshot Generati
- `v2_live.png` - Stato live dashboard
- `dashboard_v2_classica.png` - Vista Classica
- `dashboard_v2_compatta.png` - Vista Compatta
- `dashboard_v2_standard.png` - Vista Standard
- `dashboard_v2_dettagliata.png` - Vista Dettagliata
- `dashboard_v2_zoom_140.png` - Test zoom A++

---

## 1. CUSTOM SKILLS DISPONIBILI

### /security-check
Esegue validazione sicurezza secondo checklist CLAUDE.md:
- SQL Injection (SEC001-SEC002)
- XSS (SEC002)
- CSRF (SEC003)
- Input Validation (SEC004)
- Authentication (SEC005-SEC006)

**Uso**: Dopo modifiche a file PHP, esegui `/security-check` prima del commit.

### /test-ftm
Analizza i risultati della Test Suite FTM:
- Identifica test falliti
- Suggerisce fix specifici
- Fornisce codice correttivo

**Uso**: Incolla output test falliti e chiedi analisi.

### /moodle-review
Review completa secondo standard Moodle:
- Struttura plugin
- Standard codice
- Database best practices
- AJAX patterns

**Uso**: Prima di consegnare codice, esegui review.

### /design-review
Review UI/UX con Playwright:
- Coerenza colori FTM (primary #0066cc, success #28a745, etc.)
- Tipografia corretta (system font stack)
- Border radius uniformi (buttons 6px, cards 8px, modals 12px)
- Accessibilità e contrasto
- Responsive design

**Uso**: Apri pagina con Playwright e verifica design.

---

## 2. HOOKS CONFIGURATI

### Post-Edit PHP Validation
Dopo ogni modifica a file PHP, viene eseguito automaticamente:
```
php -l <file>
```
Se ci sono errori di sintassi, verrai avvisato immediatamente.

### Pre-Bash Safety Check
Prima di eseguire comandi bash, blocca automaticamente:
- `DROP TABLE`, `DROP DATABASE`
- `TRUNCATE`, `DELETE FROM...WHERE 1`
- `rm -rf /`

---

## 3. AGENTI FTM SPECIALIZZATI (NUOVO)

### Sistema Multi-Agente FTM

Basato su [AutoMaker Agents](https://github.com/AutoMaker-Org/automaker/tree/main/.claude/agents), adattato per Moodle/FTM.

| Agente | Colore | Ruolo | File |
|--------|--------|-------|------|
| **moodle-security-scanner** | Rosso | Audit sicurezza Moodle | `.claude/agents/moodle-security-scanner.md` |
| **ftm-investigator** | Giallo | Analisi bug, root cause | `.claude/agents/ftm-investigator.md` |
| **ftm-implementer** | Verde | Implementazione codice | `.claude/agents/ftm-implementer.md` |
| **moodle-refactor** | Blu | Code quality, DRY | `.claude/agents/moodle-refactor.md` |
| **ftm-orchestrator** | Viola | Coordinamento agenti | `.claude/agents/ftm-orchestrator.md` |

### Workflow con Agenti Paralleli

Gli agenti lavorano **in parallelo** per massimizzare efficienza:

```
╔═══════════════════════════════════════════════════════════════╗
║ FASE 1: INVESTIGAZIONE (Parallelo)                            ║
╠═══════════════════════════════════════════════════════════════╣
║  ftm-investigator ──┬── Analizza bug, trova root cause        ║
║  security-scanner ──┴── Verifica sicurezza codice correlato   ║
╠═══════════════════════════════════════════════════════════════╣
║ FASE 2: IMPLEMENTAZIONE (Sequenziale)                         ║
╠═══════════════════════════════════════════════════════════════╣
║  ftm-implementer ────── Applica fix basato su handoff         ║
╠═══════════════════════════════════════════════════════════════╣
║ FASE 3: VERIFICA (Parallelo)                                  ║
╠═══════════════════════════════════════════════════════════════╣
║  security-scanner ──┬── Audit codice modificato               ║
║  moodle-refactor ───┼── Verifica qualità codice               ║
║  Playwright ────────┴── Test visuale                          ║
╚═══════════════════════════════════════════════════════════════╝
```

### Pattern di Utilizzo

**Bug Fix:**
```
1. ftm-investigator: Trova causa
2. ftm-implementer: Applica fix
3. security-scanner + Playwright: Verifica
```

**Nuova Feature:**
```
1. ftm-investigator + moodle-refactor: Analisi parallela
2. ftm-implementer: Implementa
3. security-scanner + moodle-refactor: Review parallela
```

**Code Review:**
```
1. security-scanner + moodle-refactor + ftm-investigator: Review parallela completa
```

### Attivazione Automatica

Gli agenti si attivano automaticamente quando lavori su FTM Plugins:
- Ogni modifica PHP → security-scanner verifica
- Ogni bug report → ftm-investigator analizza
- Ogni implementazione → ftm-implementer segue standard
- Ogni refactoring → moodle-refactor guida

---

## 4. AGENTI CLAUDE CODE STANDARD

### Explore Agent
**Quando usarlo**: Ricerca nel codebase, trovare file, capire struttura.

```
"Usa l'agente Explore per trovare tutti i file che gestiscono le competenze"
"Esplora come funziona il sistema di autovalutazione"
```

### Plan Agent
**Quando usarlo**: Pianificare implementazioni complesse.

```
"Usa l'agente Plan per progettare l'implementazione del nuovo modulo export"
"Pianifica come aggiungere il supporto multi-lingua"
```

### General-Purpose Agent
**Quando usarlo**: Task multi-step complessi, ricerche approfondite.

```
"Usa un agente per investigare perché il test 3.6 fallisce"
"Analizza tutte le dipendenze tra i plugin FTM"
```

### Bash Agent
**Quando usarlo**: Operazioni git, comandi di sistema.

```
"Usa l'agente Bash per fare commit di tutte le modifiche"
```

---

## 5. MCP SERVERS CONSIGLIATI

### Playwright MCP (Browser Automation)
**Installazione**:
```bash
claude mcp add playwright npx '@playwright/mcp@latest'
```

**Uso**:
```
"Usa playwright mcp per aprire il browser e testare la pagina di login"
"Fai uno screenshot della dashboard FTM"
"Verifica che il form di autovalutazione funzioni"
```

**Vantaggi**:
- Test visuale delle interfacce
- Automazione browser
- Screenshot per debug
- Login manuale per sessioni autenticate

### GitHub MCP
**Installazione**:
```bash
claude mcp add github --transport http https://api.githubcopilot.com/mcp/
```

**Uso**:
```
"Crea una PR per le modifiche correnti"
"Mostra i commenti sulla PR #123"
```

---

## 6. WORKFLOW TIPICI

### Workflow: Nuova Feature
1. Descrivi la feature richiesta
2. Usa `/moodle-review` per verificare struttura esistente
3. Chiedi implementazione con `Plan Agent`
4. Implementa con feedback iterativo
5. Usa `/security-check` per validazione
6. Commit con messaggio strutturato

### Workflow: Fix Bug
1. Descrivi il bug
2. Usa `Explore Agent` per trovare codice coinvolto
3. Analizza e proponi fix
4. Implementa
5. Testa con `/test-ftm`
6. Commit

### Workflow: Code Review Pre-Consegna
1. Esegui `/security-check`
2. Esegui `/moodle-review`
3. Esegui test suite sul server
4. Analizza con `/test-ftm`
5. Fix eventuali issue
6. Push finale

### Workflow: Test UI con Playwright
1. Configura Playwright MCP
2. "Apri la pagina di test suite"
3. "Fai login come admin"
4. "Esegui i test e mostrami i risultati"
5. "Fai screenshot dei test falliti"

---

## 7. BEST PRACTICES

### Usa gli Agenti Proattivamente
Invece di fare ricerche manuali, chiedi:
- "Usa Explore per trovare dove è definito X"
- "Usa Plan per progettare come implementare Y"

### Sfrutta le Skills
Dopo ogni sessione di coding:
- `/security-check` prima del commit
- `/moodle-review` per nuovi file

### Automatizza con Hooks
I hook controllano automaticamente:
- Sintassi PHP dopo ogni modifica
- Blocco comandi pericolosi

### Documenta nel CLAUDE.md
Aggiorna CLAUDE.md quando:
- Aggiungi nuove funzionalità
- Cambi struttura
- Trovi pattern utili

---

## 8. COMANDI RAPIDI

```bash
# Aggiungi MCP Playwright
claude mcp add playwright npx '@playwright/mcp@latest'

# Lista MCP configurati
claude mcp list

# Verifica configurazione
claude config

# Esegui con skill specifica
claude "/security-check"
```

---

## 9. RISOLUZIONE PROBLEMI

### Skill non trovata
```bash
# Verifica che la cartella skill esista
ls .claude/skills/
```

### Hook non eseguito
```bash
# Verifica settings.json
cat .claude/settings.json
```

### MCP non risponde
```bash
# Reinstalla
claude mcp remove playwright
claude mcp add playwright npx '@playwright/mcp@latest'
```
