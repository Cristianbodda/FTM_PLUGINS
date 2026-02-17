# DASHBOARD SEGRETERIA - DETTAGLI TECNICI (05/02/2026)

## Panoramica
Centro di controllo completo per la segreteria con gestione inline di attivita e prenotazioni.

## File Principali
```
local/ftm_scheduler/
├── secretary_dashboard.php    # Dashboard principale con 5 tab
├── ajax_secretary.php         # Endpoint AJAX per CRUD
├── manage_coaches.php         # Gestione coach
├── setup_coaches.php          # Setup iniziale coach
├── guida_segreteria.php       # Guida operativa in Moodle
└── tabs/calendario.php        # Calendario con filtri
```

## Tab Dashboard
| Tab | Contenuto |
|-----|-----------|
| **Panoramica** | Attivita oggi, statistiche, conflitti, azioni rapide |
| **Occupazione Aule** | Matrice settimanale slot/aule con % occupazione |
| **Carico Docenti** | Ore per coach, barra carico, soglia sovraccarico |
| **Conflitti** | Lista conflitti aula/docente con link modifica |
| **Pianificazione** | Creazione rapida e visualizzazione slot liberi |

## Endpoint AJAX (ajax_secretary.php)
| Action | Metodo | Descrizione |
|--------|--------|-------------|
| `get_activity` | GET | Recupera dati attivita per modifica |
| `get_external` | GET | Recupera dati prenotazione esterna |
| `create_activity` | POST | Crea nuova attivita |
| `create_external` | POST | Crea prenotazione esterna |
| `update_activity` | POST | Aggiorna attivita esistente |
| `update_external` | POST | Aggiorna prenotazione esistente |
| `delete_activity` | POST | Elimina attivita e iscrizioni |
| `delete_external` | POST | Elimina prenotazione esterna |
| `get_options` | GET | Recupera gruppi, aule, coach per dropdown |

## Fasce Orarie
| Fascia | Orario |
|--------|--------|
| `matt` | 08:30 - 11:45 |
| `pom` | 13:15 - 16:30 |
| `all` | 08:30 - 16:30 (tutto il giorno) |

## Funzioni JavaScript
```javascript
// Modali
ftmOpenModal('createActivity')   // Apre modale creazione
ftmCloseModal('editActivity')    // Chiude modale

// CRUD
ftmSubmitActivity(event)         // Crea attivita
ftmEditActivity(id)              // Apre modifica attivita
ftmUpdateActivity(event)         // Salva modifiche
ftmDeleteActivity()              // Elimina con conferma

ftmSubmitExternal(event)         // Crea prenotazione
ftmEditExternal(id)              // Apre modifica prenotazione
ftmUpdateExternal(event)         // Salva modifiche
ftmDeleteExternal()              // Elimina con conferma

// Quick actions
ftmQuickCreate(cell)             // Crea da slot vuoto
ftmQuickActivity(roomId, date, slot)   // Crea attivita precompilata
ftmQuickBook(roomId, date, slot)       // Crea prenotazione precompilata

// Feedback
showToast(message, type)         // Toast notification (success/error)
```

## Capabilities Richieste
| Pagina | Capability |
|--------|------------|
| secretary_dashboard.php | `local/ftm_scheduler:manage` |
| ajax_secretary.php | `local/ftm_scheduler:manage` |
| manage_coaches.php | `local/ftm_scheduler:manage` |
| guida_segreteria.php | `local/ftm_scheduler:view` |

## Ruolo Segreteria FTM
Creato con `create_secretary_role.php`, include:
- `local/ftm_scheduler:*`, `local/ftm_cpurc:*`
- `local/competencymanager:view`, `managesectors`
- `local/coachmanager:view`, `viewallnotes`
- `local/selfassessment:view`, `manage`
