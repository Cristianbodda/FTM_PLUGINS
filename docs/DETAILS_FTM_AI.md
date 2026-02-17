# FTM AI INTEGRATION - DETTAGLI TECNICI (28/01/2026 - STANDBY)

## Panoramica
Plugin per integrare Azure OpenAI (Copilot) con mascheramento automatico dei dati sensibili.
**Stato:** Completo e pronto per installazione, ma in STANDBY.

## File Struttura
```
local/ftm_ai/
├── version.php                  # Plugin definition
├── settings.php                 # Admin configuration Azure
├── classes/
│   ├── anonymizer.php           # Mascheramento PII
│   ├── azure_openai.php         # Client API Azure
│   └── service.php              # Facade semplificata
├── db/
│   └── install.xml              # Tabella usage logging
├── lang/
│   └── en/local_ftm_ai.php      # Stringhe lingua
└── README.md                    # Documentazione completa
```

## Privacy - Dati MAI inviati ad Azure
Nome, cognome, email, numero AVS, telefono, indirizzo, IBAN, data di nascita.

## Uso nel Codice
```php
$service = new \local_ftm_ai\service();
if ($service->is_available()) {
    $result = $service->generate_student_suggestions($userid, $competencyData, $gapData, 'formale', $historyData);
    if ($result['success']) {
        echo $result['suggestions'];
    }
}
```

## Configurazione Admin
| Setting | Descrizione |
|---------|-------------|
| azure_endpoint | URL Azure OpenAI |
| azure_api_key | API Key del deployment |
| azure_deployment | Nome deployment (es. gpt-4) |
| daily_limit | Limite richieste giornaliere |
| max_tokens | Max token per richiesta |

## Per Riprendere lo Sviluppo
1. Copia `local/ftm_ai` nel Moodle `/local/`
2. Amministrazione > Notifiche per installare
3. Configura credenziali Azure in admin settings
4. Integra nel `student_report.php` con bottone "Genera con AI"
