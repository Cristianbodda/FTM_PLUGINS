<?php
/**
 * Test script to verify PhpSpreadsheet availability.
 * DELETE THIS FILE AFTER TESTING.
 *
 * @package    local_ftm_scheduler
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

echo "<h2>Test PhpSpreadsheet</h2>";

// Check if PhpSpreadsheet is available.
$spreadsheetclass = '\PhpOffice\PhpSpreadsheet\Spreadsheet';
$iofactoryclass = '\PhpOffice\PhpSpreadsheet\IOFactory';

if (class_exists($spreadsheetclass)) {
    echo "<p style='color:green;'>✅ PhpSpreadsheet è disponibile!</p>";

    // Try to create an instance.
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        echo "<p style='color:green;'>✅ Creazione istanza Spreadsheet OK</p>";

        // Check IOFactory.
        if (class_exists($iofactoryclass)) {
            echo "<p style='color:green;'>✅ IOFactory disponibile</p>";
        }

        echo "<p><strong>Puoi procedere con il test dell'import!</strong></p>";
        echo "<p><a href='import_calendar.php'>→ Vai alla pagina Import Calendario</a></p>";

    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Errore: " . $e->getMessage() . "</p>";
    }

} else {
    echo "<p style='color:red;'>❌ PhpSpreadsheet NON è disponibile</p>";
    echo "<p>Moodle potrebbe non avere questa libreria inclusa.</p>";

    // Check alternative: PHPExcel (old library).
    if (class_exists('PHPExcel')) {
        echo "<p style='color:orange;'>⚠️ PHPExcel (vecchia libreria) è disponibile</p>";
    }

    // Check Moodle's built-in Excel library.
    echo "<h3>Librerie Excel Moodle:</h3>";

    $excellib = $CFG->libdir . '/excellib.class.php';
    if (file_exists($excellib)) {
        require_once($excellib);
        echo "<p style='color:green;'>✅ excellib.class.php esiste</p>";

        if (class_exists('MoodleExcelWorkbook')) {
            echo "<p style='color:green;'>✅ MoodleExcelWorkbook disponibile (solo SCRITTURA)</p>";
        }
    }

    echo "<h3>Soluzione:</h3>";
    echo "<p>Per leggere file Excel, bisogna installare PhpSpreadsheet via Composer:</p>";
    echo "<pre>cd /path/to/moodle\ncomposer require phpoffice/phpspreadsheet</pre>";
}

echo "<hr><p><small>Elimina questo file dopo il test!</small></p>";
