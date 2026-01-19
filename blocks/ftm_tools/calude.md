# FTM PLUGINS - Guida Completa per Claude

## Panoramica Progetto
Ecosistema di 11 plugin Moodle per gestione competenze professionali.
Target: Moodle 4.5+ / 5.0 | Licenza: GPL-3.0

## Plugin (11 totali)
### Local (9)
- competencymanager - Core gestione competenze
- coachmanager - Coaching formatori
- competencyreport - Report studenti
- competencyxmlimport - Import XML/Word/Excel
- ftm_hub - Hub centrale
- ftm_scheduler - Pianificazione
- ftm_testsuite - Testing
- labeval - Valutazione laboratori
- selfassessment - Autovalutazione

### Block (1)
- ftm_tools - Blocco strumenti

### Question Bank (1)
- competenciesbyquestion - Competenze domande

---

# MODALITA' OPERATIVE

## 1. DEVELOPER MODE - Sviluppo Funzionalita
Quando l'utente chiede di aggiungere funzionalita:
- Analizza i file esistenti prima di modificare
- Segui la struttura esistente del plugin
- Crea file in classes/ per nuove classi PHP
- Aggiungi stringhe in ENTRAMBE le lingue (lang/en/ e lang/it/)
- Aggiorna version.php incrementando la versione
- Se serve database, crea upgrade.php

### Struttura file obbligatoria:
```
plugin/
├── classes/           # Classi PHP (autoload)
├── db/access.php      # Capabilities
├── db/install.xml     # Schema database
├── db/upgrade.php     # Migrazioni
├── lang/en/*.php      # Inglese
├── lang/it/*.php      # Italiano
├── lib.php            # Funzioni globali
├── version.php        # Versione
```

## 2. BUG FIXER MODE - Correzione Bug
Quando l'utente segnala un bug:
- Chiedi dettagli: messaggio errore, file, riga
- Leggi il file PRIMA di proporre fix
- Fai modifiche MINIME e CHIRURGICHE
- Non toccare codice funzionante
- Testa mentalmente il fix
- Spiega cosa hai cambiato e perche

## 3. SECURITY AUDITOR MODE - Controllo Sicurezza
Verifica SEMPRE questi punti:
```php
// OBBLIGATORIO in ogni pagina
require_login();
require_sesskey(); // per POST/AJAX

// Input validation
$id = required_param('id', PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);

// Capabilities
require_capability('local/plugin:view', $context);

// Output escaping
echo format_string($text);
echo s($userdata);

// Database - MAI concatenare SQL
$DB->get_record('table', ['id' => $id]); // CORRETTO
$DB->get_record_sql("SELECT * FROM {table} WHERE id = ?", [$id]); // CORRETTO
```

### Vulnerabilita da evitare:
- SQL Injection: usare placeholder $DB
- XSS: escape con s() o format_string()
- CSRF: verificare sesskey()
- Access Control: verificare capabilities

## 4. DATABASE SPECIALIST MODE - Modifiche DB
Per modifiche al database:
1. MAI modificare install.xml direttamente per DB esistenti
2. Creare upgrade.php con la migrazione
3. Incrementare versione in version.php
4. Testare upgrade path

### Template upgrade.php:
```php
function xmldb_local_PLUGIN_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026011300) {
        // Definisci nuova tabella o campo
        $table = new xmldb_table('local_plugin_newtable');
        // ... definizione campi

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026011300, 'local', 'PLUGIN');
    }
    return true;
}
```

## 5. TRANSLATOR MODE - Stringhe Lingua
Per traduzioni:
- SEMPRE aggiungere in ENTRAMBI i file (en + it)
- Usare chiavi descrittive: $string['key'] = 'Value';
- Non usare HTML nelle stringhe
- Usare placeholder {$a} per variabili

### Esempio:
```php
// lang/en/local_plugin.php
$string['reporttitle'] = 'Competency Report';
$string['nostudents'] = 'No students found in {$a}';

// lang/it/local_plugin.php
$string['reporttitle'] = 'Report Competenze';
$string['nostudents'] = 'Nessuno studente trovato in {$a}';
```

## 6. CODE REVIEWER MODE - Revisione Codice
Checklist revisione:
- [ ] Coding standards Moodle rispettati
- [ ] Nessun codice duplicato
- [ ] Funzioni brevi e leggibili
- [ ] Commenti dove necessario
- [ ] Nessun debug code (var_dump, print_r)
- [ ] Nessuna password/credenziale hardcoded
- [ ] Error handling appropriato
- [ ] Capabilities verificate

## 7. TEST CREATOR MODE - Creazione Test
Per test in ftm_testsuite:
- Creare test case specifici
- Verificare scenari positivi e negativi
- Testare edge cases
- Documentare cosa testa ogni test

## 8. MOCKUP DESIGNER MODE - Anteprima Visiva
IMPORTANTE: Per utenti che preferiscono vedere le modifiche visivamente.

### Workflow obbligatorio per modifiche UI:
1. PRIMA di modificare qualsiasi interfaccia, creare un file HTML di mockup
2. Salvare il mockup in: mockups/nome_funzionalita.html
3. Attendere approvazione dell'utente
4. SOLO dopo approvazione, implementare in Moodle

### Template mockup HTML:
```html
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mockup - [Nome Funzionalita]</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #1177d1; color: white; padding: 15px; margin: -20px -20px 20px; border-radius: 8px 8px 0 0; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #1177d1; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .alert { padding: 15px; border-radius: 4px; margin: 15px 0; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-success { background: #d4edda; color: #155724; }
        .note { background: #fff3cd; padding: 15px; border-radius: 4px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>[Titolo Pagina]</h1>
        </div>

        <!-- CONTENUTO MOCKUP QUI -->

        <div class="note">
            <strong>Note per revisione:</strong><br>
            - Punto 1 da verificare<br>
            - Punto 2 da verificare
        </div>
    </div>
</body>
</html>
```

### Istruzioni per l'utente:
Quando vedi un mockup:
1. Apri il file .html nel browser (doppio clic)
2. Verifica che l'interfaccia sia come desideri
3. Rispondi: "Approvo" oppure "Modifica: [cosa cambiare]"

### Cosa includere nel mockup:
- Layout completo della pagina/sezione
- Tutti i pulsanti e azioni
- Tabelle con dati di esempio
- Form con tutti i campi
- Messaggi di feedback (successo/errore)
- Colori e stili simili a Moodle

---

# REGOLE GENERALI

## Cosa fare SEMPRE:
1. Leggere il file PRIMA di modificarlo
2. Fare backup mentale delle modifiche
3. Spiegare cosa fai e perche
4. Mantenere compatibilita backward
5. Seguire pattern esistenti nel codice
6. Per modifiche UI: creare PRIMA il mockup

## Cosa NON fare MAI:
1. Modificare file senza leggerli
2. Cambiare codice funzionante senza motivo
3. Rimuovere funzionalita esistenti
4. Usare funzioni deprecate Moodle
5. Hardcodare valori (usare config/lang)
6. Ignorare sicurezza
7. Implementare UI senza approvazione mockup

## Comandi utili Moodle:
```bash
php admin/cli/purge_caches.php    # Pulisci cache
php admin/cli/upgrade.php         # Aggiorna DB
php admin/cli/cron.php            # Esegui cron
```

---

# DIPENDENZE TRA PLUGIN

```
ftm_hub (centrale)
├── competencymanager (core)
│   ├── competencyreport
│   ├── competencyxmlimport
│   ├── selfassessment
│   └── coachmanager
├── labeval
├── ftm_scheduler
└── ftm_testsuite

qbank_competenciesbyquestion <- competencymanager
block_ftm_tools -> ftm_hub
```

---

# WORKFLOW CONSIGLIATO

## Per nuove funzionalita con UI:
1. Utente descrive cosa vuole
2. Claude crea mockup HTML in mockups/
3. Utente apre mockup nel browser
4. Utente approva o chiede modifiche
5. Claude implementa in Moodle
6. Claude verifica sicurezza
7. Claude aggiorna traduzioni IT/EN

## Per bug fix:
1. Utente descrive il problema
2. Claude analizza il codice
3. Claude propone fix minimale
4. Claude implementa
5. Claude verifica che non rompa altro

## Per modifiche database:
1. Claude verifica struttura attuale
2. Claude crea upgrade.php
3. Claude aggiorna version.php
4. Claude documenta la migrazione

---

# WORD PARSER - FORMATI SUPPORTATI (v4.0)

Il parser Word (`local/competencyxmlimport/classes/word_parser.php`) supporta **19 formati** per 6 settori:

## Formati per Settore

| Settore | Formati | Pattern |
|---------|---------|---------|
| **AUTOVEICOLO** | 3 | AUT_BASE_Q01, Q01 (ID), Q01 - Competenza: |
| **ELETTRONICA** | 2 | Q01 + Competenza:, Q01 - COMP_CODE |
| **CHIMICA** | 3 | Competenza: XXX \|, Q01 (ID) + (F2):, (F2): dopo risposte |
| **ELETTRICITA** | 5 | ELET_BASE_Q01, Bullet + checkmark, Q01 \|, Q01., Q##\nCodice: |
| **LOGISTICA** | 4 | 1. LOG_XXX_Q01, LOG_APPR01_Q01, LOG_APPR04_Q01 -, Q1 - |
| **MECCANICA** | 1 | [A-H]?##. + Codice competenza: MECCANICA_XX |

---

# CONTATTI E RISORSE

Repository: https://github.com/Cristianbodda/FTM_PLUGINS
Moodle Docs: https://docs.moodle.org/dev/
Coding Style: https://moodledev.io/general/development/policies/codingstyle
