# Local LabEval - Valutazione Prove Pratiche di Laboratorio

Plugin Moodle per la gestione delle valutazioni di prove pratiche in laboratorio, con integrazione completa nel sistema FTM.

## 🎯 Funzionalità Principali

### 📋 Gestione Template
- Importazione template da file Excel
- Definizione comportamenti osservabili
- Mapping comportamenti → competenze con pesi (1 o 3)
- Supporto multi-settore (MECCANICA, AUTOMAZIONE, ecc.)

### ✏️ Valutazione Studenti
- Scheda valutazione interattiva
- Scala valutativa: 0 (N/A), 1★ (da migliorare), 3★★★ (adeguato)
- Note per singolo comportamento
- Salvataggio bozza e completamento

### 📊 Report Integrato
- Radar chart con 3 serie: Quiz + Autovalutazione + Prove Pratiche
- Gap analysis tra le diverse fonti
- Competenze non testate visualizzate in grigio con link "Attiva"
- Esportazione PDF per colloqui

### 🔐 Autorizzazioni
- Coach assegna prove agli studenti
- Coach autorizza studenti a vedere i propri report
- Integrazione con local_coachmanager

## 📦 Installazione

1. Copiare la cartella `local_labeval` in `/local/`
2. Accedere a Moodle come admin
3. Seguire la procedura di installazione plugin
4. Configurare i permessi per coach/docenti

## 📁 Struttura File

```
local_labeval/
├── classes/
│   └── api.php              # API per integrazione con altri plugin
├── db/
│   ├── access.php           # Capabilities
│   ├── install.xml          # Schema database
│   └── upgrade.php          # Script upgrade
├── lang/
│   ├── en/local_labeval.php # Stringhe inglese
│   └── it/local_labeval.php # Stringhe italiano
├── assign.php               # Assegna prove a studenti
├── assignments.php          # Lista assegnazioni
├── authorize.php            # Gestione autorizzazioni
├── evaluate.php             # Scheda valutazione
├── import.php               # Importa template da Excel
├── index.php                # Dashboard principale
├── lib.php                  # Funzioni helper
├── reports.php              # Report integrato con radar
├── settings.php             # Impostazioni admin
├── template_view.php        # Visualizza template
├── templates.php            # Lista template
├── version.php              # Versione plugin
└── view_evaluation.php      # Visualizza valutazione
```

## 📊 Schema Database

- `local_labeval_templates` - Template prove pratiche
- `local_labeval_behaviors` - Comportamenti osservabili
- `local_labeval_behavior_comp` - Mapping comportamento→competenza
- `local_labeval_assignments` - Assegnazioni a studenti
- `local_labeval_sessions` - Sessioni di valutazione
- `local_labeval_ratings` - Valutazioni singoli comportamenti
- `local_labeval_comp_scores` - Cache punteggi per competenza
- `local_labeval_auth` - Autorizzazioni studenti

## 🔗 API per Integrazione

```php
use local_labeval\api;

// Ottieni punteggi competenze studente
$scores = api::get_student_competency_scores($studentid, 'MECCANICA');

// Ottieni valutazioni completate
$evaluations = api::get_student_evaluations($studentid);

// Ottieni copertura competenze
$coverage = api::get_competency_coverage($studentid, 'MECCANICA');
```

## 📋 Formato Excel per Import

| Comportamento | Codice Competenza | Descrizione | Peso |
|---------------|-------------------|-------------|------|
| Identifica il pezzo | MECCANICA_DT_01 | Lettura disegno | 3 |
| | MECCANICA_MIS_04 | Tolleranze | 1 |
| Sceglie strumento | MECCANICA_MIS_01 | Strumenti misura | 3 |

- Colonna 1: Comportamento (vuoto = aggiunge competenza al precedente)
- Colonna 2: Codice competenza
- Colonna 3: Descrizione (opzionale)
- Colonna 4: Peso (1=secondario, 3=principale)

## 📄 Licenza

GNU GPL v3 - Vedi LICENSE

## 👥 Credits

Sviluppato per FTM - Formazione Tecnica Meccanica
Copyright © 2024
