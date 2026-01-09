# FTM Moodle Plugins - Guida all'Installazione

## Requisiti

- **Moodle**: 4.4+ / 4.5 / 5.0
- **PHP**: 8.1 o superiore
- **Database**: MySQL 8.0+ o MariaDB 10.6+
- **Estensioni PHP**: fileinfo, simplexml, json

---

## Versioni Correnti

| Plugin | Versione | Build |
|--------|----------|-------|
| qbank_competenciesbyquestion | v1.2 | 2026010901 |
| local_competencymanager | v2.1.1 | 2026010901 |
| local_coachmanager | v2.0.0 | 2025122303 |
| local_selfassessment | v1.1.0 | 2025122402 |
| local_competencyreport | v1.0 | 2025120501 |
| local_competencyxmlimport | v1.2 | 2026010901 |
| local_labeval | v1.0.0 | 2024123001 |
| local_ftm_hub | v2.0.2 | 2026010902 |
| block_ftm_tools | v2.0.2 | 2026010902 |
| **local_ftm_suite** | **v1.0.0** | **2026010902** |

---

## Installazione Rapida con FTM Suite (Raccomandato)

Il plugin **local_ftm_suite** è un meta-installer che verifica automaticamente che tutti i 9 plugin FTM siano installati correttamente.

### Vantaggi:
- ✅ Validazione automatica delle dipendenze
- ✅ Dashboard per verificare lo stato di tutti i plugin
- ✅ Moodle blocca l'installazione se mancano dipendenze

### Procedura:
1. Copia tutti i 10 plugin nelle rispettive cartelle Moodle
2. Visita `/admin/index.php`
3. Moodle installerà i plugin nell'ordine corretto automaticamente
4. Verifica lo stato su `/local/ftm_suite/index.php`

---

## Ordine di Installazione (Manuale)

⚠️ **IMPORTANTE**: Se installi manualmente, rispetta l'ordine per le dipendenze!

| # | Plugin | Percorso Moodle | Dipendenze |
|---|--------|-----------------|------------|
| 1 | qbank_competenciesbyquestion | `/question/bank/competenciesbyquestion/` | Nessuna |
| 2 | local_competencymanager | `/local/competencymanager/` | Plugin 1 (>= v1.2) |
| 3 | local_coachmanager | `/local/coachmanager/` | Plugin 2 |
| 4 | local_selfassessment | `/local/selfassessment/` | Nessuna |
| 5 | local_competencyreport | `/local/competencyreport/` | Nessuna |
| 6 | local_competencyxmlimport | `/local/competencyxmlimport/` | Nessuna |
| 7 | local_labeval | `/local/labeval/` | Plugin 3 |
| 8 | local_ftm_hub | `/local/ftm_hub/` | Plugin 2 |
| 9 | block_ftm_tools | `/blocks/ftm_tools/` | Plugin 2 |
| 10 | **local_ftm_suite** | `/local/ftm_suite/` | **Tutti i plugin sopra** |

---

## Installazione Nuova

1. **Scarica** il pacchetto da [GitHub Releases](https://github.com/Cristianbodda/FTM_PLUGINS/releases)

2. **Estrai** i file nella root di Moodle:
   ```bash
   unzip FTM_PLUGINS-v1.3.zip -d /path/to/moodle/
   ```

3. **Accedi** come amministratore a Moodle

4. **Visita** `/admin/index.php` - Moodle rileverà i nuovi plugin

5. **Conferma** l'installazione e segui le istruzioni

6. **Verifica** su `/local/competencymanager/system_check.php`

---

## Aggiornamento da Versioni Precedenti

1. **Backup** del database e dei file
   ```bash
   mysqldump -u user -p moodle > backup_pre_upgrade.sql
   ```

2. **Sostituisci** i file dei plugin con la nuova versione

3. **Visita** `/admin/index.php` per eseguire l'upgrade

4. **Verifica** che tutte le nuove capabilities siano registrate:
   - Site Administration → Users → Permissions → Define roles

---

## Capabilities Richieste

### local_competencyxmlimport (NUOVO in v1.2)
| Capability | Descrizione | Default |
|------------|-------------|---------|
| `local/competencyxmlimport:import` | Import domande XML/Word | Manager, Teacher |
| `local/competencyxmlimport:managediagnostics` | Strumenti diagnostica | Manager |
| `local/competencyxmlimport:assigncompetencies` | Assegna competenze | Manager, Teacher |

### local_competencymanager
| Capability | Descrizione | Default |
|------------|-------------|---------|
| `local/competencymanager:managecoaching` | Gestione coaching | Manager, Teacher |

---

## Verifica Installazione

Dopo l'installazione, verifica che tutto funzioni:

1. Vai su `/local/ftm_suite/index.php` - Verifica che tutti i 9 plugin siano "Installed" ✅
2. Vai su `/local/competencymanager/system_check.php` - Controlla che tutti i test siano verdi
3. Testa l'export Excel dalla dashboard
4. Verifica il salvataggio coaching

---

## URL dopo l'installazione

```
/local/ftm_suite/index.php            → Status dashboard (verifica installazione)
/local/ftm_hub/index.php              → Hub centrale
/local/competencymanager/dashboard.php → Dashboard competenze
/local/coachmanager/index.php         → Gestione coach
/local/selfassessment/index.php       → Autovalutazione
/local/labeval/index.php              → Valutazione laboratorio
/local/competencyxmlimport/dashboard.php → Import XML
```

---

## Troubleshooting

### Errore "Plugin dependency not met"
Assicurati di installare i plugin nell'ordine corretto. `qbank_competenciesbyquestion` deve essere >= v1.2.

### Errore capabilities
Dopo l'upgrade, svuota la cache: Site Administration → Development → Purge caches

### Errore export Excel
Verifica che PhpSpreadsheet sia installato in `/lib/phpspreadsheet/`

---

## Supporto

- **Repository**: https://github.com/Cristianbodda/FTM_PLUGINS
- **Issues**: https://github.com/Cristianbodda/FTM_PLUGINS/issues
- **Release Notes**: https://github.com/Cristianbodda/FTM_PLUGINS/releases

---

*Ultimo aggiornamento: 9 Gennaio 2026 - v1.3*
