# CoachManager - Project Status

**Ultimo aggiornamento:** 22 Gennaio 2026
**Versione plugin:** 2.2.0 (2026012201)
**Stato:** FUNZIONANTE

---

## Riepilogo Sviluppo

### Coach Dashboard V2 (NUOVO - 22/01/2026)

Dashboard completamente ridisegnata per utenti 50+ anni con:

#### 4 Viste Configurabili
| Vista | Descrizione |
|-------|-------------|
| Classica | Vista originale, tutti i dettagli |
| Compatta | Card ridotte, info essenziali |
| Standard | Bilanciata info/spazio |
| Dettagliata | Massimo dettaglio con timeline espansa |

#### Accessibilita
- **Zoom A- (90%)**: Schermi piccoli
- **Zoom A (100%)**: Default
- **Zoom A+ (120%)**: Leggibilita migliorata
- **Zoom A++ (140%)**: Utenti con difficolta visive
- Preferenze salvate automaticamente per utente

#### Funzionalita
- Filtri orizzontali: Corso, Colore Gruppo, Settimana, Stato
- Statistiche rapide con card colorate
- Timeline 6 settimane per ogni studente
- Note Coach visibili a coach + segreteria
- Export Word diretto dalla card studente

---

## File Dashboard V2

### Nuovi File
| File | Descrizione |
|------|-------------|
| `coach_dashboard_v2.php` | Dashboard principale V2 |
| `export_word.php` | Generazione report Word studente |

### Struttura CSS
- Inline CSS con variabili per coerenza
- HTML table per layout filtri (override Moodle CSS)
- Responsive con media queries
- Colori FTM standard (#667eea viola, #764ba2 gradient)

---

## URL Accesso

| Pagina | URL |
|--------|-----|
| Dashboard V2 | /local/coachmanager/coach_dashboard_v2.php |
| Dashboard Originale | /local/coachmanager/coach_dashboard.php |
| Bilancio Competenze | /local/coachmanager/ |

---

## Preferenze Utente

| Chiave | Valori | Default |
|--------|--------|---------|
| `ftm_coach_view` | classica, compatta, standard, dettagliata | classica |
| `ftm_coach_zoom` | 90, 100, 120, 140 | 100 |

---

## Database

### Tabella Note Coach
```sql
local_coachmanager_notes
- id (BIGINT, PK)
- studentid (BIGINT, FK user)
- coachid (BIGINT, FK user)
- notes (TEXT)
- timecreated (INT)
- timemodified (INT)
```

---

## Testing

### Playwright Test Scripts
- `check_v2.mjs` - Test visuale con browser aperto
- `test_fix_v2.mjs` - Verifica CSS applicato
- `test_dashboard_v2.mjs` - Test completo viste e zoom

### Verifiche Effettuate
- [x] Login e navigazione
- [x] Cambio viste (4 viste)
- [x] Zoom livelli (4 livelli)
- [x] Filtri orizzontali funzionanti
- [x] Bottoni leggibili (testo visibile)
- [x] Screenshot automatici

---

## Storico Modifiche

### 22/01/2026 - V2 Release
- Creata `coach_dashboard_v2.php` con 4 viste
- Aggiunto sistema zoom accessibilita
- Filtri orizzontali con HTML table
- Fix bottoni: `color: #333 !important`
- Note coach condivise con segreteria
- Export Word con PHPWord/HTML fallback

### 16/01/2026 - V1 Completata
- Dashboard V1 funzionante
- Card studenti collassabili
- Calendario integrato

---

*Generato: 22 Gennaio 2026*
