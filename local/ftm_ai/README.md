# FTM AI Integration

Plugin Moodle per integrare Azure OpenAI (Copilot) con **mascheramento automatico dei dati sensibili**.

## Caratteristiche

- **Anonimizzazione automatica**: Rimuove nome, cognome, AVS, email, telefono prima di inviare dati
- **Suggerimenti personalizzati**: Genera testi variati basati su storico studente
- **Analisi predittiva**: Identifica studenti a rischio
- **Varianti linguistiche**: Evita ripetizioni nei testi generati
- **Fallback deterministico**: Se AI non disponibile, usa template

## Requisiti

- Moodle 4.0+
- PHP 7.4+
- Account Azure con Azure OpenAI Service attivo
- Plugin `local_competencymanager` installato

## Installazione

1. Copia la cartella `ftm_ai` in `/local/`
2. Vai in Amministrazione > Notifiche per installare
3. Configura in Amministrazione > Plugin > FTM AI Integration

## Configurazione Azure

1. Accedi a [Azure Portal](https://portal.azure.com)
2. Crea una risorsa "Azure OpenAI Service"
3. Crea un deployment (es. `gpt-4`)
4. Copia:
   - **Endpoint**: `https://tuarisorsa.openai.azure.com`
   - **API Key**: dalla sezione "Keys and Endpoint"
   - **Deployment Name**: nome del tuo deployment

## Uso nel Codice

```php
// Includi il service
$service = new \local_ftm_ai\service();

// Verifica disponibilità
if ($service->is_available()) {

    // Genera suggerimenti
    $result = $service->generate_student_suggestions(
        $userid,           // ID studente Moodle
        $competencyData,   // Array competenze
        $gapData,          // Array gap analysis
        'formale',         // Tono: 'formale' o 'colloquiale'
        $historyData       // Storico opzionale
    );

    if ($result['success']) {
        echo $result['suggestions'];
        // Il nome studente è già reinserito automaticamente!
    }
}
```

## Privacy e Sicurezza

### Dati MAI inviati ad Azure:
- Nome e cognome
- Email
- Numero AVS (756.XXXX.XXXX.XX)
- Telefono
- Indirizzo
- IBAN
- Data di nascita
- Qualsiasi altro PII

### Dati inviati (sicuri):
- Settore (MECCANICA, AUTOMOBILE, etc.)
- Percentuali competenze aggregate
- Gap percentuali (numeri)
- Trend (improving/stable/declining)
- Conteggi anonimi

### Esempio Trasformazione

**PRIMA (dati reali):**
```
Mario Rossi, AVS 756.1234.5678.90
Meccanica Area F: 75%
Gap: +32%
```

**DOPO (inviato a Azure):**
```
student_id: "a8f3b2c1..." (hash)
sector: "MECCANICA"
area_F_score: 75
gap: 32
```

## Funzionalità

### 1. Suggerimenti Variati
Ogni volta che generi suggerimenti, l'AI produce testi leggermente diversi, evitando ripetizioni.

### 2. Analisi Predittiva
```php
$riskResult = $service->generate_risk_analysis($userid, $allData);
// Ritorna: risk_level, risk_factors, recommendations
```

### 3. Varianti Linguistiche
```php
$variants = $service->generate_variants($baseText, 'colloquiale');
// Ritorna: 3 versioni diverse dello stesso concetto
```

## Controllo Costi

- **Limite giornaliero**: Configurabile in settings
- **Logging utilizzo**: Tabella `local_ftm_ai_usage`
- **Token limit**: Max token per richiesta configurabile

## Fallback

Se Azure non risponde o non è configurato:
- Il plugin usa automaticamente i **template deterministici** di `gap_comments_mapping.php`
- Il campo `ai_generated` nel risultato indica se è stato usato AI o fallback

## Troubleshooting

| Problema | Soluzione |
|----------|-----------|
| "Configuration missing" | Configura endpoint, API key e deployment |
| "Rate limit exceeded" | Attendi o aumenta il limite giornaliero |
| Risposta lenta | Normale per GPT-4 (~5-10 sec). Considera GPT-3.5 per velocità |
| Testo generico | Aumenta i dati forniti (storico, più competenze) |

## Roadmap

- [ ] Cache delle risposte per richieste simili
- [ ] Streaming per risposta progressiva
- [ ] Dashboard statistiche utilizzo
- [ ] Integrazione Copilot Studio per agenti custom

---

**Versione**: 1.0.0-alpha
**Autore**: FTM Development Team
**Licenza**: GNU GPL v3+
