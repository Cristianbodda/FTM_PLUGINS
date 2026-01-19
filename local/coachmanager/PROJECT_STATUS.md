# CoachManager - Project Status

**Ultimo aggiornamento:** 16 Gennaio 2026
**Versione plugin:** 2.1.0 (2025122304)
**Stato:** IN TEST

---

## Riepilogo Sviluppo Coach Dashboard

### Obiettivo
Creare una dashboard unificata per i coach che integri:
- Visualizzazione studenti assegnati
- Competenze, autovalutazioni, laboratori
- Calendario settimanale/mensile
- Filtri avanzati
- Alert fine percorso 6 settimane

### Mockup Approvati
La dashboard si basa sui mockup iterati (v1-v5) con:
- Stile armonizzato con `student_report.php` (viola gradient #667eea -> #764ba2)
- Card studenti collassabili con freccia
- Colori gruppo mantenuti (giallo, blu, verde, ecc.)

---

## File Creati/Modificati

### Nuovi File
| File | Descrizione |
|------|-------------|
| `coach_dashboard.php` | Pagina principale dashboard |
| `classes/dashboard_helper.php` | Classe helper per dati dashboard |
| `styles/dashboard.css.php` | CSS con tema viola |
| `ajax_save_choices.php` | AJAX per salvare scelte settimanali |
| `ajax_send_reminder.php` | AJAX per invio promemoria |

### File Modificati
| File | Modifiche |
|------|-----------|
| `version.php` | Versione 2025122304, release 2.1.0 |
| `lib.php` | Aggiunto navigation node Coach Dashboard |
| `lang/it/local_coachmanager.php` | +90 stringhe Dashboard Coach |
| `lang/en/local_coachmanager.php` | +90 stringhe Dashboard Coach (EN) |

---

## Problemi Risolti

### 1. Versione troppo bassa
- **Errore:** "Una versione piu recente e gia stata installata!"
- **Causa:** Versione impostata a 2025011401 < 2025122303
- **Fix:** Cambiata a 2025122304

### 2. Stringhe inglesi mancanti
- **Errore:** "Invalid get_string() identifier: 'coach_dashboard'"
- **Causa:** File `lang/en/` non aveva le nuove stringhe
- **Fix:** Aggiunte 90+ stringhe in `lang/en/local_coachmanager.php`

---

## Prossimi Passi per Riprendere

### Immediato (per testare)
1. [ ] Caricare via FTP: `lang/en/local_coachmanager.php`
2. [ ] Svuotare cache Moodle
3. [ ] Testare: https://test-urc.hizuvala.myhostpoint.ch/local/coachmanager/coach_dashboard.php

### Da Verificare
- [ ] Dashboard carica correttamente
- [ ] Card studenti collassabili funzionano
- [ ] Calendario settimana/mese espandibile
- [ ] Filtri avanzati funzionano
- [ ] Alert fine percorso visibile

### Eventuali Miglioramenti Futuri
- [ ] Integrazione reale con ftm_scheduler per calendario
- [ ] Scelte rapide per assegnazione test/lab
- [ ] Report classe esportabile

---

## Riferimenti

### URL Test
- Dashboard: https://test-urc.hizuvala.myhostpoint.ch/local/coachmanager/coach_dashboard.php
- Bilancio Competenze: https://test-urc.hizuvala.myhostpoint.ch/local/coachmanager/
- Student Report (riferimento colori): https://test-urc.hizuvala.myhostpoint.ch/local/competencymanager/student_report.php

### Mockup Riferimento
- Stile da: `05_scheduler_gruppi_v3.html`
- Colori da: `student_report.php` (viola gradient)

---

## Struttura Dashboard

```
+------------------------------------------+
|  HEADER: Dashboard Coach                 |
+------------------------------------------+
|  FILTRI AVANZATI                         |
|  [Corso] [Colore] [Settimana] [Stato]   |
+------------------------------------------+
|  STATISTICHE                             |
|  [Studenti] [Competenze] [Autoval] [Lab]|
+------------------------------------------+
|  ALERT: Studenti in fine percorso       |
|  X studenti stanno completando...       |
+------------------------------------------+
|  CALENDARIO [Settimana v] [Mese v]      |
+------------------------------------------+
|  LISTA STUDENTI                          |
|  +------------------------------------+  |
|  | [v] Mario Rossi - Gruppo Giallo   |  |
|  |     Competenze: 75%               |  |
|  |     Autovalutazione: Completa     |  |
|  |     Laboratorio: In attesa        |  |
|  |     [Report] [Colloquio] [Remind] |  |
|  +------------------------------------+  |
|  | [>] Anna Bianchi - Gruppo Blu     |  |
|  +------------------------------------+  |
+------------------------------------------+
```

---

*Generato: 14 Gennaio 2026*
