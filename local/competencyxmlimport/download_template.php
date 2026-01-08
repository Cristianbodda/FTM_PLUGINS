<?php
/**
 * Download Template XML
 * 
 * Genera e scarica template XML personalizzati per il settore selezionato
 * 
 * @package    local_competencyxmlimport
 */

require_once(__DIR__ . '/../../config.php');

$type = required_param('type', PARAM_ALPHA);
$sector = optional_param('sector', 'SETTORE', PARAM_TEXT);

// Pulisci il settore
$sector = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $sector));
if (empty($sector)) {
    $sector = 'SETTORE';
}

// Template per domande
if ($type === 'questions') {
    $filename = "TEMPLATE_DOMANDE_{$sector}.xml";
    
    $template = '<?xml version="1.0" encoding="UTF-8"?>
<!--
╔══════════════════════════════════════════════════════════════════════════════╗
║                    TEMPLATE DOMANDE - ' . $sector . '                              
║                                                                              ║
║  Come usare questo template:                                                 ║
║  1. Copia una domanda di esempio                                             ║
║  2. Modifica il codice competenza: ' . $sector . '_XX_YY                            
║     - XX = tipo (es. MR, MAu)                                               ║
║     - YY = codice (es. A1, B2, C3)                                          ║
║  3. Scrivi la tua domanda e le 4 risposte                                   ║
║  4. Imposta fraction="100" per la risposta CORRETTA                         ║
║  5. Imposta fraction="0" per le risposte ERRATE                             ║
║                                                                              ║
║  Il codice competenza può essere:                                            ║
║  - Nel NOME: <n><text>Q01 - ' . $sector . '_MR_A1</text></n>                       
║  - Nel TESTO: <b>' . $sector . '_MR_A1</b> - Testo domanda...                      
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
-->
<quiz>

<!-- ══════════════════════════════════════════════════════════════════════════
     DOMANDA 1: Esempio con codice nel NOME
     ══════════════════════════════════════════════════════════════════════════ -->
<question type="multichoice">
    <n>
        <text>Q01 - ' . $sector . '_MR_A1</text>
    </n>
    <questiontext format="html">
        <text><![CDATA[<p>Scrivi qui il testo della tua prima domanda.</p>]]></text>
    </questiontext>
    <defaultgrade>1.0000000</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    <answer fraction="100" format="html">
        <text><![CDATA[<p>Risposta CORRETTA</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 1</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 2</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 3</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <generalfeedback format="html"><text></text></generalfeedback>
    <correctfeedback format="html"><text></text></correctfeedback>
    <partiallycorrectfeedback format="html"><text></text></partiallycorrectfeedback>
    <incorrectfeedback format="html"><text></text></incorrectfeedback>
    <shownumcorrect/>
</question>

<!-- ══════════════════════════════════════════════════════════════════════════
     DOMANDA 2: Esempio con codice nel TESTO (in grassetto)
     ══════════════════════════════════════════════════════════════════════════ -->
<question type="multichoice">
    <n>
        <text>Domanda descrittiva</text>
    </n>
    <questiontext format="html">
        <text><![CDATA[<p><b>' . $sector . '_MR_A2</b> - Scrivi qui il testo della domanda con il codice in grassetto all\'inizio.</p>]]></text>
    </questiontext>
    <defaultgrade>1.0000000</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    <answer fraction="100" format="html">
        <text><![CDATA[<p>Risposta CORRETTA</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 1</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 2</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 3</p>]]></text>
        <feedback format="html"><text></text></feedback>
    </answer>
    <generalfeedback format="html"><text></text></generalfeedback>
    <correctfeedback format="html"><text></text></correctfeedback>
    <partiallycorrectfeedback format="html"><text></text></partiallycorrectfeedback>
    <incorrectfeedback format="html"><text></text></incorrectfeedback>
    <shownumcorrect/>
</question>

<!-- ══════════════════════════════════════════════════════════════════════════
     DOMANDA 3: Esempio con feedback
     ══════════════════════════════════════════════════════════════════════════ -->
<question type="multichoice">
    <n>
        <text>Q03 - ' . $sector . '_MR_B1</text>
    </n>
    <questiontext format="html">
        <text><![CDATA[<p>Domanda con feedback personalizzato per ogni risposta.</p>]]></text>
    </questiontext>
    <defaultgrade>1.0000000</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <single>true</single>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>abc</answernumbering>
    <answer fraction="100" format="html">
        <text><![CDATA[<p>Risposta CORRETTA</p>]]></text>
        <feedback format="html">
            <text><![CDATA[<p>Esatto! Questa è la risposta giusta perché...</p>]]></text>
        </feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 1</p>]]></text>
        <feedback format="html">
            <text><![CDATA[<p>Non corretto. Il motivo è...</p>]]></text>
        </feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 2</p>]]></text>
        <feedback format="html">
            <text><![CDATA[<p>Non corretto. Ricorda che...</p>]]></text>
        </feedback>
    </answer>
    <answer fraction="0" format="html">
        <text><![CDATA[<p>Risposta errata 3</p>]]></text>
        <feedback format="html">
            <text><![CDATA[<p>Non corretto. La differenza è...</p>]]></text>
        </feedback>
    </answer>
    <generalfeedback format="html">
        <text><![CDATA[<p>Feedback generale mostrato a tutti dopo la risposta.</p>]]></text>
    </generalfeedback>
    <correctfeedback format="html">
        <text><![CDATA[<p>Ottimo lavoro!</p>]]></text>
    </correctfeedback>
    <partiallycorrectfeedback format="html">
        <text><![CDATA[<p>Parzialmente corretto.</p>]]></text>
    </partiallycorrectfeedback>
    <incorrectfeedback format="html">
        <text><![CDATA[<p>Risposta non corretta. Riprova!</p>]]></text>
    </incorrectfeedback>
    <shownumcorrect/>
</question>

<!-- ══════════════════════════════════════════════════════════════════════════
     COPIA E INCOLLA QUESTA STRUTTURA PER AGGIUNGERE ALTRE DOMANDE
     Ricorda di cambiare:
     - Il numero della domanda (Q04, Q05, ecc.)
     - Il codice competenza (' . $sector . '_MR_XX)
     - Il testo della domanda
     - Le 4 risposte (1 corretta con fraction="100", 3 errate con fraction="0")
     ══════════════════════════════════════════════════════════════════════════ -->

</quiz>';

    // Invia il file
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($template));
    header('Cache-Control: no-cache, must-revalidate');
    
    echo $template;
    exit;
}

// Template per categorie (futuro uso)
if ($type === 'categories') {
    $filename = "TEMPLATE_CATEGORIE_{$sector}.xml";
    
    $template = '<?xml version="1.0" encoding="UTF-8"?>
<!--
    Template Categorie per ' . $sector . '
    Struttura gerarchica delle categorie domande
-->
<categories>
    <category>
        <name>' . $sector . '</name>
        <info>Categoria principale per ' . $sector . '</info>
        <subcategories>
            <category>
                <name>Test Base</name>
                <info>Domande di livello base</info>
            </category>
            <category>
                <name>Approfondimento 1</name>
                <info>Domande di approfondimento - Argomento 1</info>
            </category>
            <category>
                <name>Approfondimento 2</name>
                <info>Domande di approfondimento - Argomento 2</info>
            </category>
        </subcategories>
    </category>
</categories>';

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($template));
    
    echo $template;
    exit;
}

// Template per mapping competenze (CSV)
if ($type === 'mapping') {
    $filename = "TEMPLATE_MAPPING_{$sector}.csv";
    
    $template = "codice_competenza;livello;descrizione
{$sector}_MR_A1;1;Competenza base - Area A - Punto 1
{$sector}_MR_A2;1;Competenza base - Area A - Punto 2
{$sector}_MR_B1;2;Competenza intermedia - Area B - Punto 1
{$sector}_MR_B2;2;Competenza intermedia - Area B - Punto 2
{$sector}_MAu_C1;3;Competenza avanzata - Area C - Punto 1
{$sector}_MAu_C2;3;Competenza avanzata - Area C - Punto 2
";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($template));
    
    echo $template;
    exit;
}

// Tipo non riconosciuto
header('HTTP/1.1 400 Bad Request');
echo 'Tipo template non valido. Usa: questions, categories, mapping';
