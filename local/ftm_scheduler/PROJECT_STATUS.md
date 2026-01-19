# FTM Scheduler - Stato Progetto

**Ultimo aggiornamento:** 16 Gennaio 2026

## Stato: IN SVILUPPO ATTIVO

### Funzionalita Completate

#### Vista Calendario Settimanale
- Griglia Lun-Ven con slot Mattina/Pomeriggio
- Navigazione settimane con frecce
- Attivita colorate per gruppo (giallo, grigio, rosso, marrone, viola)
- Prenotazioni esterne con bordo tratteggiato blu
- Filtri: gruppo, aula, tipo
- Modal dettaglio attivita

#### Vista Calendario Mensile (NUOVO 15/01/2026)
- Toggle Settimana/Mese nei filtri
- Griglia mensile con tutte le settimane del mese
- Colonna KW con numero settimana e date
- Attivita in formato compatto (max 4 per cella)
- Link "+X altre..." per celle piene
- Link "Dettagli" per saltare a vista settimanale
- Navigazione mesi con frecce
- Selettore anno dropdown (2025, 2026, 2027)
- Pulsante rapido "Gennaio 2026"

#### Tab Gruppi
- Griglia card gruppi colorati
- Stato gruppo (attivo, in pianificazione, completato)
- Progress bar settimane
- Link a Gestione Settori (NUOVO)

#### Toolbar
- Link "Gestione Settori" (NUOVO)
- Pulsanti: Nuovo Gruppo, Nuova Attivita, Prenota Aula

### File Principali

```
local/ftm_scheduler/
├── index.php                 # Pagina principale + CSS
├── lib.php                   # Funzioni helper
├── classes/
│   └── manager.php           # Classe gestione dati
├── tabs/
│   ├── calendario.php        # Vista settimana + mese
│   ├── gruppi.php            # Tab gruppi
│   ├── attivita.php          # Tab attivita
│   ├── aule.php              # Tab aule
│   └── atelier.php           # Tab atelier
├── db/
│   ├── install.xml           # Schema database
│   └── access.php            # Capabilities
└── lang/
    ├── en/local_ftm_scheduler.php
    └── it/local_ftm_scheduler.php
```

### Metodi Manager Aggiunti (15/01/2026)

```php
// Ottiene tutte le settimane di un mese
public static function get_month_weeks($year, $month)

// Ottiene attivita organizzate per settimana/giorno/slot
public static function get_month_activities($year, $month)
```

### URL di Test

- Vista Settimana: `/local/ftm_scheduler/index.php?tab=calendario&view=week`
- Vista Mese: `/local/ftm_scheduler/index.php?tab=calendario&view=month`
- Tab Gruppi: `/local/ftm_scheduler/index.php?tab=gruppi`

### Prossimi Sviluppi

- [ ] Export Excel calendario
- [ ] Drag & drop attivita
- [ ] Generazione automatica attivita settimana 2-6
- [ ] Notifiche email/calendario
