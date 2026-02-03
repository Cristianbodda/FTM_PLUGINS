<?php
/**
 * Test semplice per debug
 */
require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/test_debug.php'));
$PAGE->set_title('Test Debug');

echo $OUTPUT->header();
echo '<h2>Test Debug Parser</h2>';
echo '<p>Se vedi questo messaggio, PHP funziona!</p>';

// Test PhpSpreadsheet
echo '<h3>Test PhpSpreadsheet:</h3>';
try {
    require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');
    echo '<p style="color:green;">✅ PhpSpreadsheet caricato correttamente</p>';
} catch (Exception $e) {
    echo '<p style="color:red;">❌ Errore: ' . $e->getMessage() . '</p>';
}

// Form per upload
echo '<h3>Test Upload:</h3>';
echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="file" name="excelfile" accept=".xlsx,.xls" class="form-control mb-2">';
echo '<button type="submit" name="upload" class="btn btn-primary">Test Upload</button>';
echo '</form>';

if (isset($_POST['upload'])) {
    echo '<h4>Risultato Upload:</h4>';
    if (isset($_FILES['excelfile'])) {
        echo '<pre>';
        print_r($_FILES['excelfile']);
        echo '</pre>';

        if (is_uploaded_file($_FILES['excelfile']['tmp_name'])) {
            echo '<p style="color:green;">✅ File caricato: ' . $_FILES['excelfile']['name'] . '</p>';

            // Try to read first sheet
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['excelfile']['tmp_name']);
                $spreadsheet = $reader->load($_FILES['excelfile']['tmp_name']);
                $sheets = $spreadsheet->getSheetNames();
                echo '<p style="color:green;">✅ Excel letto! Fogli: ' . implode(', ', $sheets) . '</p>';
            } catch (Exception $e) {
                echo '<p style="color:red;">❌ Errore lettura Excel: ' . $e->getMessage() . '</p>';
            }
        } else {
            echo '<p style="color:red;">❌ Upload fallito</p>';
        }
    } else {
        echo '<p style="color:red;">❌ Nessun file ricevuto</p>';
    }
}

echo $OUTPUT->footer();
