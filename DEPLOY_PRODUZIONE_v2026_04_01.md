# DEPLOY PRODUZIONE - Aggiornamenti 26/03 - 01/04/2026

**Server produzione:** https://ftmacademy.hizuvala.myhostpoint.ch
**Commit di riferimento:** 2780b5d
**Comando per richiedere il deploy:** `/deploy-produzione-aprile2026`

---

## ORDINE DI DEPLOY

### FASE 1: Plugin Competency Manager (v2.8.1)

| # | File locale | Path server | Tipo |
|---|---|---|---|
| 1 | `local/competencymanager/version.php` | stesso | Aggiornato |
| 2 | `local/competencymanager/settings.php` | stesso | **NUOVO** |
| 3 | `local/competencymanager/db/install.xml` | stesso | Aggiornato |
| 4 | `local/competencymanager/db/upgrade.php` | stesso | Aggiornato |
| 5 | `local/competencymanager/classes/report_generator.php` | stesso | Aggiornato |
| 6 | `local/competencymanager/area_mapping.php` | stesso | Aggiornato |
| 7 | `local/competencymanager/technical_passport.php` | stesso | **NUOVO** |
| 8 | `local/competencymanager/ajax_save_passport_comments.php` | stesso | **NUOVO** |
| 9 | `local/competencymanager/garage_ftm.php` | stesso | **NUOVO** |
| 10 | `local/competencymanager/ajax_save_garage_config.php` | stesso | **NUOVO** |
| 11 | `local/competencymanager/student_report.php` | stesso | Aggiornato |
| 12 | `local/competencymanager/lang/en/local_competencymanager.php` | stesso | Aggiornato |
| 13 | `local/competencymanager/lang/it/local_competencymanager.php` | stesso | Aggiornato |

### FASE 2: Plugin CPURC (v1.6.0)

| # | File locale | Path server | Tipo |
|---|---|---|---|
| 14 | `local/ftm_cpurc/version.php` | stesso | Aggiornato |
| 15 | `local/ftm_cpurc/index.php` | stesso | Aggiornato |
| 16 | `local/ftm_cpurc/student_card.php` | stesso | Aggiornato |
| 17 | `local/ftm_cpurc/report.php` | stesso | Aggiornato |
| 18 | `local/ftm_cpurc/export_word.php` | stesso | Aggiornato |
| 19 | `local/ftm_cpurc/classes/cpurc_manager.php` | stesso | Aggiornato |
| 20 | `local/ftm_cpurc/ajax_cancel_enrollment.php` | stesso | **NUOVO** |
| 21 | `local/ftm_cpurc/loginas_student.php` | stesso | **NUOVO** |
| 22 | `local/ftm_cpurc/lib.php` | stesso | Aggiornato |
| 23 | `local/ftm_cpurc/lang/en/local_ftm_cpurc.php` | stesso | Aggiornato |
| 24 | `local/ftm_cpurc/lang/it/local_ftm_cpurc.php` | stesso | Aggiornato |

### FASE 3: Plugin Coaching Individualizzato (ex SIP)

| # | File locale | Path server | Tipo |
|---|---|---|---|
| 25 | `local/ftm_sip/version.php` | stesso | Aggiornato |
| 26 | `local/ftm_sip/db/install.xml` | stesso | Aggiornato |
| 27 | `local/ftm_sip/db/upgrade.php` | stesso | Aggiornato |
| 28 | `local/ftm_sip/ajax_activate_sip.php` | stesso | Aggiornato |
| 29 | `local/ftm_sip/sip_student.php` | stesso | Aggiornato |
| 30 | `local/ftm_sip/sip_stats.php` | stesso | Aggiornato |
| 31 | `local/ftm_sip/sip_my.php` | stesso | Aggiornato |
| 32 | `local/ftm_sip/sip_export_report.php` | stesso | Aggiornato |
| 33 | `local/ftm_sip/ajax_prepare_sip.php` | stesso | Aggiornato |
| 34 | `local/ftm_sip/classes/notification_helper.php` | stesso | Aggiornato |
| 35 | `local/ftm_sip/lang/en/local_ftm_sip.php` | stesso | Aggiornato |
| 36 | `local/ftm_sip/lang/it/local_ftm_sip.php` | stesso | Aggiornato |

### FASE 4: Coach Dashboard V2

| # | File locale | Path server | Tipo |
|---|---|---|---|
| 37 | `local/coachmanager/coach_dashboard_v2.php` | stesso | Aggiornato |

---

## DOPO IL CARICAMENTO FTP

1. **Upgrade DB:** Navigare a `https://ftmacademy.hizuvala.myhostpoint.ch/admin/index.php`
   - Crea tabelle: `local_garage_config`, `local_passport_comments`
   - Aggiunge campo: `ladi_indemnity` su `local_ftm_sip_enrollments`
   - Aggiunge campi: `enabled_sections`, `section_order` su `local_garage_config`

2. **Purga cache:** `https://ftmacademy.hizuvala.myhostpoint.ch/admin/purgecaches.php`

3. **Configura soglia:** Amministrazione > Plugin > Local > Competency Manager > Soglia minima passaporto (default 60%)

---

## COSA INCLUDONO QUESTI AGGIORNAMENTI

### 1. Annullamento Iscrizione CPURC
- Bottone rosso "Annulla Iscrizione" nella Student Card
- Modale di conferma con dettaglio azioni
- Studente rimosso da: dashboard, corso, gruppo colore, coach, settori
- Filtro "Annullati" nella dashboard CPURC

### 2. Passaporto Tecnico
- Pagina dedicata con radar SVG + tabella competenze + commenti coach
- 12 sezioni configurabili dal Garage FTM
- Stampa con header FTM rosso + logo
- Formato: percentuali o scala qualitativa

### 3. Garage FTM
- Selettore competenze con checkbox per area/competenza
- Drag & drop per ordinamento sezioni stampa
- Toggle: formato, overlay 3 fonti, autovalutazione, coach eval
- Soglia personalizzabile per studente

### 4. Accedi come Studente (Login-as)
- Bottone blu one-click in: CPURC dashboard, student card, coach dashboard V2
- Banner rosso "Ritorna al tuo account" (fix tema Adaptable)

### 5. Coaching Individualizzato (rinomina SIP)
- Tutte le etichette UI: "SIP" -> "Coaching Individualizzato" / "CI"
- Badge, bottoni, filtri, notifiche email aggiornati

### 6. Campo LADI Obbligatorio
- Indennita giornaliere LADI richieste per attivazione CI
- Campo numerico nel modale di attivazione

### 7. Soglia Globale Passaporto
- Impostazione admin per soglia minima % competenze

### 8. Fix Quiz senza courseid
- get_available_quizzes() cerca in tutti i corsi quando courseid=0

### 9. Fix Settore ELETTRICIT
- Alias per accento troncato da PARAM_ALPHANUMEXT

### 10. Fix Stampa Multi-pagina
- overflow:visible per tema Adaptable (contenuto non tagliato dopo pagina 1)

---

## TOTALE: 37 file (8 nuovi + 29 aggiornati)
