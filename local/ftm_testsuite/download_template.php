<?php
/**
 * Download Template - Genera e scarica template per la creazione domande
 * 
 * Tipi supportati:
 * - xml: Template XML Moodle con esempi
 * - excel: Excel Master con mappatura competenze
 * - word: Template Word per revisione
 * - instructions: Istruzioni per ChatGPT
 * 
 * @package    local_competencyxmlimport
 * @version    1.0
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$type = required_param('type', PARAM_ALPHA);
$sector = optional_param('sector', 'MECCANICA', PARAM_TEXT);

// Pulisci settore
$sector = strtoupper(preg_replace('/[^A-Za-z]/', '', $sector));
if (empty($sector)) {
    $sector = 'MECCANICA';
}

// Determina file e content type
switch ($type) {
    case 'xml':
        $filename = "TEMPLATE_DOMANDE_{$sector}.xml";
        $content = generate_xml_template($sector);
        $contenttype = 'application/xml';
        break;
        
    case 'excel':
        // Genera CSV (compatibile con Excel)
        $filename = "EXCEL_MASTER_{$sector}.csv";
        $content = generate_excel_template($sector);
        $contenttype = 'text/csv';
        break;
        
    case 'word':
        // Genera HTML (apribile con Word)
        $filename = "TEMPLATE_WORD_{$sector}.html";
        $content = generate_word_template($sector);
        $contenttype = 'text/html';
        break;
        
    case 'instructions':
        $filename = "ISTRUZIONI_CHATGPT_{$sector}.md";
        $content = generate_instructions_template($sector);
        $contenttype = 'text/markdown';
        break;
        
    case 'questions':
        // Retrocompatibilità con il vecchio link
        $filename = "TEMPLATE_DOMANDE_{$sector}.xml";
        $content = generate_xml_template($sector);
        $contenttype = 'application/xml';
        break;
        
    case 'excelverify':
        // Template Excel per verifica import Word
        $filename = "TEMPLATE_VERIFICA_{$sector}.xlsx";
        $content = generate_excel_verify_template($sector);
        $contenttype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
        
    default:
        die('Tipo template non valido');
}

// Invia headers per download
header('Content-Type: ' . $contenttype . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo $content;
exit;

// ============================================================================
// FUNZIONI GENERAZIONE TEMPLATE
// ============================================================================

/**
 * Genera template XML Moodle
 */
function generate_xml_template($sector) {
    $examples = get_sector_examples($sector);
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<quiz>
<!-- 
    ╔═══════════════════════════════════════════════════════════════════╗
    ║  TEMPLATE DOMANDE XML - SETTORE: ' . str_pad($sector, 20) . '     ║
    ║  Plugin: local_competencyxmlimport                                ║
    ╚═══════════════════════════════════════════════════════════════════╝
    
    FORMATO NOME DOMANDA:
    [CODICE_QUIZ]_Q[NN] - [CODICE_COMPETENZA]
    
    Esempio: ' . $examples['quiz_code'] . '_Q01 - ' . $examples['comp_code'] . '
    
    CODICI QUIZ per ' . $sector . ':
    - ' . $sector . '_BASE      = Test Base (40 domande)
    - ' . $sector . '_APPR01    = Approfondimento 1 (25 domande)
    - ' . $sector . '_APPR02    = Approfondimento 2 (25 domande)
    ...
    
    CODICI COMPETENZA:
    - ' . $examples['comp_code'] . ' (esempio)
    - Formato: ' . $sector . '_[AREA]_[CODICE]
-->

<!-- DOMANDA ESEMPIO 1 - Corretta ✅ -->
<question type="multichoice">
    <name>
        <text>' . $examples['quiz_code'] . '_Q01 - ' . $examples['comp_code'] . '</text>
    </name>
    <questiontext format="html">
        <text><![CDATA[<p>' . $examples['question_text'] . '</p>]]></text>
    </questiontext>
    <generalfeedback format="html">
        <text></text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    
    <!-- Risposta CORRETTA (fraction="100") -->
    <answer fraction="100" format="html">
        <text><![CDATA[' . $examples['correct_answer'] . ']]></text>
        <feedback format="html"><text>Corretto!</text></feedback>
    </answer>
    
    <!-- Risposte ERRATE (fraction="0") -->
    <answer fraction="0" format="html">
        <text><![CDATA[' . $examples['wrong_answers'][0] . ']]></text>
        <feedback format="html"><text>Errato.</text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[' . $examples['wrong_answers'][1] . ']]></text>
        <feedback format="html"><text>Errato.</text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[' . $examples['wrong_answers'][2] . ']]></text>
        <feedback format="html"><text>Errato.</text></feedback>
    </answer>
</question>

<!-- DOMANDA ESEMPIO 2 -->
<question type="multichoice">
    <name>
        <text>' . $examples['quiz_code'] . '_Q02 - ' . $examples['comp_code2'] . '</text>
    </name>
    <questiontext format="html">
        <text><![CDATA[<p>Seconda domanda di esempio per il settore ' . $sector . '.</p>]]></text>
    </questiontext>
    <generalfeedback format="html">
        <text></text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    
    <answer fraction="100" format="html">
        <text><![CDATA[Risposta corretta]]></text>
        <feedback format="html"><text>Esatto!</text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[Risposta errata 1]]></text>
        <feedback format="html"><text>Non corretto.</text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[Risposta errata 2]]></text>
        <feedback format="html"><text>Non corretto.</text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[Risposta errata 3]]></text>
        <feedback format="html"><text>Non corretto.</text></feedback>
    </answer>
</question>

<!-- AGGIUNGI LE TUE DOMANDE QUI -->

</quiz>';

    return $xml;
}

/**
 * Genera template Excel (CSV)
 */
function generate_excel_template($sector) {
    $examples = get_sector_examples($sector);
    
    $csv = "Area,Competenza,Codice,BASE,APPR01,APPR02,APPR03,APPR04,APPR05,APPR06\n";
    $csv .= "\"Esempio Area 1\",\"Competenza di esempio 1\",\"{$examples['comp_code']}\",5,3,0,0,0,0,0\n";
    $csv .= "\"Esempio Area 2\",\"Competenza di esempio 2\",\"{$examples['comp_code2']}\",3,5,2,0,0,0,0\n";
    $csv .= "\"Esempio Area 3\",\"Competenza di esempio 3\",\"{$sector}_XX_03\",2,0,5,3,0,0,0\n";
    $csv .= "\n";
    $csv .= "LEGENDA:\n";
    $csv .= "Numero = quante domande per quella competenza in quel quiz\n";
    $csv .= "BASE = Test Base (livello 1)\n";
    $csv .= "APPR01-06 = Quiz di Approfondimento (livello 2-3)\n";
    
    return $csv;
}

/**
 * Genera template Word (HTML)
 */
function generate_word_template($sector) {
    $examples = get_sector_examples($sector);
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Template Domande ' . $sector . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        h1 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .question { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .question h3 { color: #333; margin: 0 0 15px 0; }
        .question-text { font-size: 16px; margin-bottom: 15px; }
        .answers { list-style-type: upper-alpha; padding-left: 25px; }
        .answers li { margin: 8px 0; padding: 5px 10px; }
        .correct { background: #d4edda; color: #155724; font-weight: bold; border-radius: 4px; }
        .code { font-family: monospace; background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .instructions { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>📝 Template Domande - Settore ' . $sector . '</h1>
    
    <div class="instructions">
        <strong>📋 Istruzioni:</strong>
        <ol>
            <li>Copia questo formato per ogni nuova domanda</li>
            <li>Il nome domanda deve contenere il codice competenza: <code class="code">' . $examples['quiz_code'] . '_Q01 - ' . $examples['comp_code'] . '</code></li>
            <li>Segna la risposta corretta in <span class="correct">verde grassetto</span></li>
            <li>Ogni domanda deve avere 4 risposte (1 corretta, 3 errate)</li>
        </ol>
    </div>
    
    <div class="question">
        <h3>' . $examples['quiz_code'] . '_Q01 - ' . $examples['comp_code'] . '</h3>
        <div class="question-text">' . $examples['question_text'] . '</div>
        <ol class="answers">
            <li class="correct">✓ ' . $examples['correct_answer'] . '</li>
            <li>' . $examples['wrong_answers'][0] . '</li>
            <li>' . $examples['wrong_answers'][1] . '</li>
            <li>' . $examples['wrong_answers'][2] . '</li>
        </ol>
    </div>
    
    <div class="question">
        <h3>' . $examples['quiz_code'] . '_Q02 - ' . $examples['comp_code2'] . '</h3>
        <div class="question-text">Seconda domanda di esempio per il settore ' . $sector . '.</div>
        <ol class="answers">
            <li class="correct">✓ Risposta corretta</li>
            <li>Risposta errata 1</li>
            <li>Risposta errata 2</li>
            <li>Risposta errata 3</li>
        </ol>
    </div>
    
    <hr>
    <p><em>Aggiungi le tue domande seguendo questo formato.</em></p>
</body>
</html>';

    return $html;
}

/**
 * Genera istruzioni per ChatGPT
 */
function generate_instructions_template($sector) {
    $examples = get_sector_examples($sector);
    
    $md = "# 🤖 Istruzioni per Generare Domande con ChatGPT

## Settore: {$sector}

---

## 📋 FORMATO RICHIESTO

### Nome Domanda
```
[CODICE_QUIZ]_Q[NN] - [CODICE_COMPETENZA]
```

**Esempio:** `{$examples['quiz_code']}_Q01 - {$examples['comp_code']}`

### Codici Quiz per {$sector}
| Codice | Tipo | Domande |
|--------|------|---------|
| {$sector}_BASE | Test Base | 40 |
| {$sector}_APPR01 | Approfondimento 1 | 25 |
| {$sector}_APPR02 | Approfondimento 2 | 25 |
| {$sector}_APPR03 | Approfondimento 3 | 25 |
| {$sector}_APPR04 | Approfondimento 4 | 25 |
| {$sector}_APPR05 | Approfondimento 5 | 25 |
| {$sector}_APPR06 | Approfondimento 6 | 25 |

---

## 💬 PROMPT BASE

Copia e incolla questo prompt in ChatGPT:

```
Sei un esperto di {$sector} e devi creare domande a risposta multipla per un quiz di valutazione tecnica.

Per ogni domanda:
1. Nome: [{$sector}_BASE]_Q[numero] - [CODICE_COMPETENZA]
2. Testo: chiaro e professionale
3. 4 risposte: 1 corretta e 3 errate plausibili
4. Segna la risposta corretta con ✓

Competenza da valutare: [INSERISCI CODICE]
Numero domande: [INSERISCI NUMERO]

Formato output:
---
**{$examples['quiz_code']}_Q01 - {$examples['comp_code']}**
[Testo domanda]

A) ✓ [Risposta corretta]
B) [Risposta errata 1]
C) [Risposta errata 2]
D) [Risposta errata 3]
---
```

---

## ✅ CHECKLIST QUALITÀ

Prima di consegnare le domande, verifica:

- [ ] Ogni domanda ha il codice competenza nel nome
- [ ] Ogni domanda ha esattamente 4 risposte
- [ ] Ogni domanda ha UNA SOLA risposta corretta segnata con ✓
- [ ] Il testo è chiaro e senza errori grammaticali
- [ ] Le risposte errate sono plausibili (non ovviamente sbagliate)
- [ ] Non ci sono domande duplicate

---

## 📄 CONVERSIONE A XML

Dopo aver generato le domande in Word, usa il plugin per convertirle in XML Moodle.

---

*Generato automaticamente per il settore {$sector}*
";

    return $md;
}

/**
 * Ottiene esempi specifici per settore - CODICI REALI DAL FRAMEWORK
 * 
 * IMPORTANTE: Questi codici sono stati verificati nel framework "Passaporto tecnico FTM"
 * e corrispondono a competenze REALMENTE ESISTENTI
 * 
 * SETTORI (7 totali):
 * 01 - AUTOMOBILE
 * 02 - CHIMFARM
 * 03 - ELETTRICITA
 * 04 - AUTOMAZIONE
 * 05 - LOGISTICA
 * 06 - MECCANICA
 * 07 - METALCOSTRUZIONE
 */
function get_sector_examples($sector) {
    $examples = [
        // =====================================================================
        // 01 - AUTOMOBILE
        // Profili: MR (Meccatronico Riparatore), MAu (Meccatronico Automazione)
        // Aree: A-N
        // =====================================================================
        'AUTOMOBILE' => [
            'quiz_code' => 'AUT_BASE',
            'comp_code' => 'AUTOMOBILE_MR_A1',      // VERIFICATO - Accoglienza
            'comp_code2' => 'AUTOMOBILE_MR_B1',     // VERIFICATO - Motore
            'question_text' => 'Qual è la prima operazione da eseguire quando si accoglie un veicolo in officina per una diagnosi?',
            'correct_answer' => 'Raccogliere i sintomi dal cliente e verificare la documentazione del veicolo',
            'wrong_answers' => ['Smontare immediatamente il motore', 'Sostituire tutti i filtri', 'Lavare il veicolo'],
            'areas' => [
                'MR_A' => 'Accoglienza e diagnosi',
                'MR_B' => 'Motore e alimentazione',
                'MR_C' => 'Lubrificazione e raffreddamento',
                'MR_D' => 'Scarico e emissioni',
                'MR_E' => 'Trasmissione e trazione',
                'MR_F' => 'Sospensioni, sterzo, freni',
                'MR_G' => 'Impianto elettrico',
                'MAu_H' => 'ADAS e sicurezza',
                'MR_I' => 'Climatizzazione',
                'MR_J' => 'Carrozzeria e vetri',
                'MR_K' => 'Pneumatici e ruote',
                'MR_L' => 'Revisione e collaudo',
                'MR_M' => 'Diagnosi avanzata',
                'MR_N' => 'Manutenzione programmata'
            ]
        ],
        
        // =====================================================================
        // 02 - CHIMFARM (Chimico-farmaceutico)
        // Aree: 1G, 2G, 3G, 4S, 5S, 6P, 7S, 8T, 9A
        // =====================================================================
        'CHIMFARM' => [
            'quiz_code' => 'CHIMFARM_BASE',
            'comp_code' => 'CHIMFARM_1G_01',        // VERIFICATO - Gestione sostanze
            'comp_code2' => 'CHIMFARM_2G_01',       // VERIFICATO - Vettori energetici
            'question_text' => 'Quale procedura è fondamentale per garantire la sterilità in un ambiente di produzione farmaceutica?',
            'correct_answer' => 'Sterilizzazione degli strumenti e uso di flusso laminare',
            'wrong_answers' => ['Pulizia ordinaria del pavimento', 'Ventilazione naturale', 'Uso di guanti in lattice standard'],
            'areas' => [
                '1G' => 'Gestione sostanze di processo',
                '1C' => 'Controllo',
                '1O' => 'Organizzazione',
                '2G' => 'Manipolazione vettori energetici',
                '3C' => 'Configurazione e riparazione',
                '4S' => 'Svolgimento processi',
                '5S' => 'Svolgimento pulizia',
                '6P' => 'Pianificazione laboratorio',
                '7S' => 'Svolgimento laboratorio',
                '8T' => 'Trattamento dati',
                '9A' => 'Adattamento metodi'
            ]
        ],
        
        // =====================================================================
        // 03 - ELETTRICITA
        // Profili: PE (Progettazione), IE (Installazione), EM (Manutenzione), ER (Reti)
        // =====================================================================
        'ELETTRICITA' => [
            'quiz_code' => 'ELETT_BASE',
            'comp_code' => 'ELETTRICITA_PE_A1',    // VERIFICATO - Progettazione
            'comp_code2' => 'ELETTRICITA_IE_A1',   // VERIFICATO - Installazione
            'question_text' => 'Quale normativa regola gli impianti elettrici in bassa tensione in Svizzera?',
            'correct_answer' => 'NIBT (Norme sugli Impianti elettrici a Bassa Tensione)',
            'wrong_answers' => ['ISO 9001', 'DIN 5035', 'UNI EN 12464'],
            'areas' => [
                'PE_A' => 'Progettazione elettrica - Area A',
                'PE_B' => 'Progettazione elettrica - Area B',
                'IE_A' => 'Installazione elettrica - Area A',
                'IE_B' => 'Installazione elettrica - Area B',
                'EM_A' => 'Manutenzione elettrica - Area A',
                'ER_A' => 'Reti elettriche - Area A'
            ]
        ],
        
        // =====================================================================
        // 04 - AUTOMAZIONE
        // Profili: MA (Montatore), OA (Operatore)
        // =====================================================================
        'AUTOMAZIONE' => [
            'quiz_code' => 'AUTOM_BASE',
            'comp_code' => 'AUTOMAZIONE_MA_A1',    // VERIFICATO - Montatore
            'comp_code2' => 'AUTOMAZIONE_OA_A1',   // VERIFICATO - Operatore
            'question_text' => 'Cosa significa la sigla PLC nel contesto dell\'automazione industriale?',
            'correct_answer' => 'Programmable Logic Controller - Controllore Logico Programmabile',
            'wrong_answers' => ['Power Line Connector', 'Precision Laser Control', 'Primary Level Circuit'],
            'areas' => [
                'MA_A' => 'Montatore Automazione - Area A',
                'MA_B' => 'Montatore Automazione - Area B',
                'MA_C' => 'Montatore Automazione - Area C',
                'OA_A' => 'Operatore Automazione - Area A',
                'OA_B' => 'Operatore Automazione - Area B'
            ]
        ],
        
        // =====================================================================
        // 05 - LOGISTICA
        // Profilo: LO (unico)
        // =====================================================================
        'LOGISTICA' => [
            'quiz_code' => 'LOG_BASE',
            'comp_code' => 'LOGISTICA_LO_A1',      // VERIFICATO
            'comp_code2' => 'LOGISTICA_LO_B1',     // VERIFICATO
            'question_text' => 'Cosa indica il codice a barre EAN-13 su un prodotto?',
            'correct_answer' => 'Un codice univoco di 13 cifre che identifica il prodotto a livello internazionale',
            'wrong_answers' => ['La data di scadenza del prodotto', 'Il peso del prodotto', 'Il prezzo di vendita'],
            'areas' => [
                'LO_A' => 'Identificazione e codifica',
                'LO_B' => 'Gestione magazzino',
                'LO_C' => 'Trasporto e spedizione',
                'LO_D' => 'Inventario e controllo'
            ]
        ],
        
        // =====================================================================
        // 06 - MECCANICA
        // Aree: LMB, LMC, CNC, ASS, MIS, GEN, MAN, DT, AUT, PIAN, SAQ, CSP, PRG
        // =====================================================================
        'MECCANICA' => [
            'quiz_code' => 'MECC_BASE',
            'comp_code' => 'MECCANICA_DT_01',       // VERIFICATO - Disegno tecnico
            'comp_code2' => 'MECCANICA_CNC_01',     // VERIFICATO - CNC
            'question_text' => 'Quale tipo di linea viene utilizzata nel disegno tecnico per rappresentare i contorni nascosti?',
            'correct_answer' => 'Linea tratteggiata',
            'wrong_answers' => ['Linea continua grossa', 'Linea a tratto e punto', 'Linea continua sottile'],
            'areas' => [
                'LMB' => 'Lavorazioni meccaniche di base',
                'LMC' => 'Lavorazioni su macchine convenzionali',
                'CNC' => 'Lavorazioni CNC e tecnologie digitali',
                'ASS' => 'Assemblaggio e montaggio meccanico',
                'MIS' => 'Misurazione e controllo qualità',
                'GEN' => 'Trattamenti e processi speciali',
                'MAN' => 'Manutenzione e revisione impianti',
                'DT'  => 'Disegno tecnico e progettazione',
                'AUT' => 'Automazione e meccatronica',
                'PIAN' => 'Pianificazione e documentazione',
                'SAQ' => 'Sicurezza, ambiente e qualità',
                'CSP' => 'Collaborazione e sviluppo personale',
                'PRG' => 'Progettazione avanzata'
            ]
        ],
        
        // =====================================================================
        // 07 - METALCOSTRUZIONE
        // Profili: MC (Metalcostruttore), DF (Disegnatore/Fabbricatore)
        // Aree: E, F, G, H, I, J
        // =====================================================================
        'METALCOSTRUZIONE' => [
            'quiz_code' => 'METAL_BASE',
            'comp_code' => 'METALCOSTRUZIONE_MC_E1',  // VERIFICATO - Trattamenti
            'comp_code2' => 'METALCOSTRUZIONE_MC_F1', // VERIFICATO - Montaggio
            'question_text' => 'Quale trattamento superficiale protegge l\'acciaio dalla corrosione mediante immersione in zinco fuso?',
            'correct_answer' => 'Zincatura a caldo (galvanizzazione)',
            'wrong_answers' => ['Verniciatura a polvere', 'Sabbiatura', 'Anodizzazione'],
            'areas' => [
                'MC_E' => 'Trattamenti e protezione',
                'MC_F' => 'Montaggio e posa in opera',
                'MC_G' => 'Misure, qualità e conformità',
                'MC_H' => 'Sicurezza, ambiente e organizzazione',
                'DF_I' => 'CAD/CAM e BIM',
                'MC_J' => 'Manutenzione e ripristino'
            ]
        ]
    ];
    
    // Ritorna esempi per il settore o esempi generici
    if (isset($examples[$sector])) {
        return $examples[$sector];
    }
    
    // Esempi generici per settori sconosciuti
    return [
        'quiz_code' => $sector . '_BASE',
        'comp_code' => $sector . '_XX_01',
        'comp_code2' => $sector . '_XX_02',
        'question_text' => 'Domanda di esempio per il settore ' . $sector . '.',
        'correct_answer' => 'Risposta corretta',
        'wrong_answers' => ['Risposta errata 1', 'Risposta errata 2', 'Risposta errata 3'],
        'areas' => []
    ];
}

/**
 * Genera template Excel .xlsx per verifica import Word
 * 
 * @param string $sector Settore
 * @return string Contenuto binario del file xlsx
 */
function generate_excel_verify_template($sector) {
    // Un file .xlsx è un ZIP con struttura XML
    $temp_file = tempnam(sys_get_temp_dir(), 'xlsx_');
    
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Impossibile creare file Excel');
    }
    
    // Esempi per il settore
    $examples = get_sector_examples($sector);
    $sector_prefix = strtoupper($sector);
    
    // Genera dati di esempio
    $rows = [];
    for ($i = 1; $i <= 25; $i++) {
        $q_num = 'Q' . str_pad($i, 2, '0', STR_PAD_LEFT);
        $rows[] = [
            $q_num,
            $sector_prefix . '_QUIZ_' . $q_num,
            $sector_prefix . '_' . chr(64 + (($i - 1) % 9) + 1) . str_pad((($i - 1) % 7) + 1, 2, '0', STR_PAD_LEFT),
            chr(65 + (($i - 1) % 4)) // A, B, C, D
        ];
    }
    
    // [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $content_types);
    
    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);
    
    // xl/_rels/workbook.xml.rels
    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);
    
    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="QC_' . $sector . '" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);
    
    // xl/styles.xml (stili base)
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
    </fills>
    <borders count="1"><border/></borders>
    <cellStyleXfs count="1"><xf/></cellStyleXfs>
    <cellXfs count="2">
        <xf/>
        <xf fontId="1" fillId="2" applyFont="1" applyFill="1"/>
    </cellXfs>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);
    
    // Raccogli tutte le stringhe per sharedStrings
    $headers = ['Q', 'QID', 'Codice_competenza_' . $sector_prefix, 'Risposta_corretta'];
    $all_strings = $headers;
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if (!in_array($cell, $all_strings)) {
                $all_strings[] = $cell;
            }
        }
    }
    
    // xl/sharedStrings.xml
    $shared_strings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($all_strings) . '" uniqueCount="' . count($all_strings) . '">';
    foreach ($all_strings as $str) {
        $shared_strings .= '<si><t>' . htmlspecialchars($str, ENT_XML1) . '</t></si>';
    }
    $shared_strings .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $shared_strings);
    
    // Funzione helper per ottenere indice stringa
    $string_index = array_flip($all_strings);
    
    // xl/worksheets/sheet1.xml
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <cols>
        <col min="1" max="1" width="8" customWidth="1"/>
        <col min="2" max="2" width="25" customWidth="1"/>
        <col min="3" max="3" width="30" customWidth="1"/>
        <col min="4" max="4" width="18" customWidth="1"/>
    </cols>
    <sheetData>';
    
    // Header row
    $sheet .= '<row r="1">';
    $cols = ['A', 'B', 'C', 'D'];
    foreach ($headers as $col_idx => $header) {
        $sheet .= '<c r="' . $cols[$col_idx] . '1" t="s" s="1"><v>' . $string_index[$header] . '</v></c>';
    }
    $sheet .= '</row>';
    
    // Data rows
    $row_num = 2;
    foreach ($rows as $row) {
        $sheet .= '<row r="' . $row_num . '">';
        foreach ($row as $col_idx => $cell) {
            $sheet .= '<c r="' . $cols[$col_idx] . $row_num . '" t="s"><v>' . $string_index[$cell] . '</v></c>';
        }
        $sheet .= '</row>';
        $row_num++;
    }
    
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    
    $zip->close();
    
    // Leggi contenuto e cancella temp file
    $content = file_get_contents($temp_file);
    unlink($temp_file);
    
    return $content;
}
