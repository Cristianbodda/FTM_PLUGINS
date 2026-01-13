# FIX CRITICO - Risposte Quiz

## Problemi Identificati
1. **word_import_helper.php**: generava XML con CDATA e newline incompatibili
2. **setup_universale.php**: regex non tollerante a whitespace

## Soluzioni Applicate

### 1. word_import_helper.php
Genera ora XML nel formato corretto:
```xml
<answer fraction="100" format="html"><text>&lt;p&gt;risposta&lt;/p&gt;</text>...</answer>
```

### 2. setup_universale.php  
Regex modificata per accettare whitespace:
```php
// PRIMA (non funzionava con newline)
preg_match_all('/<answer fraction="(\d+)".*?><text>(.*?)<\/text>/s', ...)

// DOPO (funziona sempre)
preg_match_all('/<answer fraction="(\d+)"[^>]*>\s*<text>(.*?)<\/text>/s', ...)
```

## Installazione
1. Sostituisci TUTTI i file in `/local/competencyxmlimport/`
2. Prima di reimportare, pulisci le domande corrotte:
   ```sql
   DELETE FROM mdl_qtype_multichoice_options WHERE questionid IN (
     SELECT q.id FROM mdl_question q WHERE q.qtype = 'multichoice' 
     AND NOT EXISTS (SELECT 1 FROM mdl_question_answers qa WHERE qa.question = q.id)
   );
   DELETE FROM mdl_question WHERE qtype = 'multichoice' 
   AND NOT EXISTS (SELECT 1 FROM mdl_question_answers qa WHERE qa.question = mdl_question.id);
   ```
3. Reimporta il file Word
4. Le risposte appariranno correttamente
