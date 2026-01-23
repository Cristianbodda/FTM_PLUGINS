---
name: design-review
description: Review UI/UX delle pagine FTM usando Playwright per verificare coerenza grafica, accessibilità e standard di design
user-invocable: true
allowed-tools: Bash, Read, Grep, WebFetch
---

# Design Review FTM

Esegue una review completa dell'interfaccia utente usando Playwright per catturare screenshot e verificare la coerenza con gli standard FTM.

## Standard Grafici FTM (da CLAUDE.md)

### Colori Principali
| Variabile | Colore | Uso |
|-----------|--------|-----|
| `--primary` | #0066cc | Pulsanti primari, link |
| `--success` | #28a745 | Conferme, test passati |
| `--danger` | #dc3545 | Errori, eliminazioni |
| `--warning` | #EAB308 | Avvisi, warning |
| `--secondary` | #6c757d | Elementi secondari |

### Gruppi Colore (Scheduler)
| Gruppo | Colore |
|--------|--------|
| Giallo | #FFFF00 |
| Grigio | #808080 |
| Rosso | #FF0000 |
| Marrone | #996633 |
| Viola | #7030A0 |

### Tipografia
```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
```

### Border Radius
- Buttons: 6px
- Cards: 8px
- Modals: 12px

### Border Color
- Standard: #dee2e6

## Checklist Design Review

### 1. Coerenza Visuale
- [ ] Colori coerenti con palette FTM
- [ ] Font consistente (system font stack)
- [ ] Border radius uniformi
- [ ] Spaziature consistenti (padding/margin)

### 2. Componenti UI
- [ ] Pulsanti con stile corretto (primary, secondary, danger)
- [ ] Cards con border-radius 8px e shadow leggera
- [ ] Tabelle con header distinguibile
- [ ] Form con label chiare e validazione visiva

### 3. Feedback Utente
- [ ] Stati di loading visibili
- [ ] Messaggi di errore chiari (rosso #dc3545)
- [ ] Messaggi di successo (verde #28a745)
- [ ] Warning ben visibili (giallo #EAB308)

### 4. Accessibilità
- [ ] Contrasto testo/sfondo sufficiente
- [ ] Focus visibile su elementi interattivi
- [ ] Testi leggibili (min 14px)
- [ ] Icone con significato chiaro

### 5. Responsive
- [ ] Layout adattivo su mobile
- [ ] Tabelle scrollabili su schermi piccoli
- [ ] Menu navigabile su touch

## Come Eseguire Review

### Con Playwright (automatico)
1. Apri la pagina da revieware con Playwright
2. Cattura screenshot full-page
3. Analizza elementi UI
4. Verifica CSS applicati
5. Genera report

### Comandi Utili
```bash
# Screenshot pagina
npx playwright screenshot --full-page [URL] screenshot.png

# Apri browser interattivo
npx playwright open [URL]
```

## Pagine da Verificare Regolarmente

| Pagina | URL | Priorita |
|--------|-----|----------|
| **Coach Dashboard V2** | /local/coachmanager/coach_dashboard_v2.php | ALTA |
| Dashboard Test Suite | /local/ftm_testsuite/index.php | Media |
| Risultati Test | /local/ftm_testsuite/results.php | Media |
| Setup Universale | /local/competencyxmlimport/setup_universale.php | Media |
| Scheduler | /local/ftm_scheduler/index.php | Media |
| Sector Admin | /local/competencymanager/sector_admin.php | Media |
| Autovalutazione | /local/selfassessment/compile.php | Media |

### Coach Dashboard V2 - Verifiche Specifiche

| Elemento | Verifica |
|----------|----------|
| Bottoni Vista | Testo visibile (#333), hover funzionante |
| Filtri | Layout orizzontale (4 colonne) |
| Zoom | Tutti i livelli (90%, 100%, 120%, 140%) |
| Card Studenti | Collassabili, colori gruppo corretti |
| Timeline | 6 settimane visibili |
| Note Coach | Textarea funzionante |
| Export Word | Pulsante attivo |

## Output Report

```markdown
## Design Review Report - [Nome Pagina]

**URL**: [url]
**Data**: [data]
**Screenshot**: [path]

### Coerenza Visuale
- [OK/ISSUE] Colori: ...
- [OK/ISSUE] Font: ...
- [OK/ISSUE] Spacing: ...

### Componenti
- [OK/ISSUE] Pulsanti: ...
- [OK/ISSUE] Cards: ...
- [OK/ISSUE] Tabelle: ...

### Accessibilità
- [OK/ISSUE] Contrasto: ...
- [OK/ISSUE] Focus: ...

### Issues Trovate
1. [Descrizione issue + suggerimento fix]

### Screenshot
[Immagine allegata]
```

## Integrazione con Playwright MCP

Quando usi questa skill con Playwright MCP attivo:
1. "Apri la pagina X con Playwright"
2. "Fai screenshot della pagina"
3. "Verifica i colori dei pulsanti"
4. "Controlla se le cards hanno il border-radius corretto"
5. "Analizza l'accessibilità della pagina"
