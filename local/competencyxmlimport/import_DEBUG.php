<?php
// Import XML questions with automatic competency assignment
// VERSIONE DEBUG - Con selezione corso, categorie filtrate e debug dettagliato

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/importer.php');

// Abilita tutti gli errori PHP per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_login();
$context = context_system::instance();
require_capability('moodle/question:add', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/import_DEBUG.php'));
$PAGE->set_title('DEBUG - Import XML Competenze');
$PAGE->set_heading('DEBUG - Import XML Competenze');

// Recupera parametri
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);

// Process form submission
$imported = 0;
$assigned = 0;
$errors = 0;
$log = [];
$showerrors = [];

echo $OUTPUT->header();

// Banner DEBUG
echo '<div style="background: linear-gradient(135deg, #ff9800 0%, #f44336 100%); color: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">';
echo '<h2 style="margin: 0;">🔬 MODALITÀ DEBUG ATTIVA</h2>';
echo '<p style="margin: 10px 0 0 0;">Questa versione mostra tutti gli errori e i dettagli di elaborazione.</p>';
echo '</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    
    // ========================================
    // DEBUG: Mostra dati ricevuti
    // ========================================
    echo '<div style="background: #263238; color: #aed581; padding: 20px; margin-bottom: 20px; border-radius: 8px; font-family: monospace;">';
    echo '<h3 style="color: #4fc3f7; margin-top: 0;">📋 DEBUG: Dati ricevuti dal form</h3>';
    
    echo '<h4 style="color: #fff59d;">$_POST:</h4>';
    echo '<pre style="background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto;">';
    print_r($_POST);
    echo '</pre>';
    
    echo '<h4 style="color: #fff59d;">$_FILES:</h4>';
    echo '<pre style="background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto;">';
    print_r($_FILES);
    echo '</pre>';
    
    echo '</div>';
    
    // VALIDAZIONE INPUT
    $categoryid = optional_param('category', 0, PARAM_INT);
    $frameworkid = optional_param('framework', 0, PARAM_INT);
    $defaultlevel = optional_param('defaultlevel', 1, PARAM_INT);
    $selectedcourseid = optional_param('courseid', 0, PARAM_INT);
    
    // Debug parametri
    echo '<div style="background: #1a237e; color: #82b1ff; padding: 20px; margin-bottom: 20px; border-radius: 8px; font-family: monospace;">';
    echo '<h3 style="color: #448aff; margin-top: 0;">📋 DEBUG: Parametri estratti</h3>';
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #3949ab;"><strong>Course ID:</strong></td><td style="padding: 8px; border-bottom: 1px solid #3949ab;">' . $selectedcourseid . '</td></tr>';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #3949ab;"><strong>Category ID:</strong></td><td style="padding: 8px; border-bottom: 1px solid #3949ab;">' . $categoryid . '</td></tr>';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #3949ab;"><strong>Framework ID:</strong></td><td style="padding: 8px; border-bottom: 1px solid #3949ab;">' . $frameworkid . '</td></tr>';
    echo '<tr><td style="padding: 8px;"><strong>Default Level:</strong></td><td style="padding: 8px;">' . $defaultlevel . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // Verifica categoria
    if ($categoryid > 0) {
        $catinfo = $DB->get_record('question_categories', ['id' => $categoryid]);
        if ($catinfo) {
            echo '<div style="background: #1b5e20; color: #a5d6a7; padding: 15px; margin-bottom: 10px; border-radius: 8px;">';
            echo '✅ <strong>Categoria trovata:</strong> ' . htmlspecialchars($catinfo->name) . ' (ID: ' . $catinfo->id . ', Context: ' . $catinfo->contextid . ')';
            echo '</div>';
        }
    } else {
        $showerrors[] = '❌ Devi selezionare una categoria di destinazione!';
    }
    
    // Verifica framework
    if ($frameworkid > 0) {
        $fwinfo = $DB->get_record('competency_framework', ['id' => $frameworkid]);
        if ($fwinfo) {
            $compcount = $DB->count_records('competency', ['competencyframeworkid' => $frameworkid]);
            echo '<div style="background: #1b5e20; color: #a5d6a7; padding: 15px; margin-bottom: 10px; border-radius: 8px;">';
            echo '✅ <strong>Framework trovato:</strong> ' . htmlspecialchars($fwinfo->shortname) . ' (ID: ' . $fwinfo->id . ') - <strong>' . $compcount . ' competenze</strong>';
            echo '</div>';
            
            // Mostra prime 10 competenze per debug
            $comps = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], 'idnumber', 'id, idnumber, shortname', 0, 10);
            if (!empty($comps)) {
                echo '<div style="background: #0d47a1; color: #90caf9; padding: 15px; margin-bottom: 10px; border-radius: 8px;">';
                echo '<strong>🔍 Prime 10 competenze nel framework:</strong><br>';
                echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                foreach ($comps as $comp) {
                    echo '<li><code>' . htmlspecialchars($comp->idnumber) . '</code> - ' . htmlspecialchars($comp->shortname) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
    } else {
        $showerrors[] = '❌ Devi selezionare un framework delle competenze!';
    }
    
    // Verifica file XML caricato
    if (!isset($_FILES['xmlfile']) || $_FILES['xmlfile']['error'] !== UPLOAD_ERR_OK) {
        if (!isset($_FILES['xmlfile'])) {
            $showerrors[] = '❌ Nessun file XML caricato! ($_FILES[xmlfile] non esiste)';
        } else {
            switch ($_FILES['xmlfile']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $showerrors[] = '❌ Il file è troppo grande!';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $showerrors[] = '❌ Nessun file selezionato!';
                    break;
                default:
                    $showerrors[] = '❌ Errore nel caricamento del file (codice: ' . $_FILES['xmlfile']['error'] . ')';
            }
        }
    }
    
    // Verifica contenuto file
    $xmlcontent = '';
    if (empty($showerrors) && isset($_FILES['xmlfile']['tmp_name'])) {
        echo '<div style="background: #004d40; color: #80cbc4; padding: 20px; margin-bottom: 20px; border-radius: 8px; font-family: monospace;">';
        echo '<h3 style="color: #4db6ac; margin-top: 0;">📄 DEBUG: Lettura file XML</h3>';
        
        $tmpfile = $_FILES['xmlfile']['tmp_name'];
        echo '<p><strong>File temporaneo:</strong> ' . $tmpfile . '</p>';
        echo '<p><strong>File esiste:</strong> ' . (file_exists($tmpfile) ? '<span style="color: #69f0ae;">SÌ ✅</span>' : '<span style="color: #ff5252;">NO ❌</span>') . '</p>';
        
        if (file_exists($tmpfile)) {
            echo '<p><strong>File leggibile:</strong> ' . (is_readable($tmpfile) ? '<span style="color: #69f0ae;">SÌ ✅</span>' : '<span style="color: #ff5252;">NO ❌</span>') . '</p>';
            echo '<p><strong>Dimensione file:</strong> ' . number_format(filesize($tmpfile)) . ' bytes</p>';
            
            $xmlcontent = @file_get_contents($tmpfile);
            
            if ($xmlcontent === false) {
                $showerrors[] = '❌ Impossibile leggere il contenuto del file!';
                echo '<p><span style="color: #ff5252;"><strong>Lettura contenuto:</strong> FALLITA ❌</span></p>';
            } elseif (empty($xmlcontent)) {
                $showerrors[] = '❌ Il file è vuoto!';
                echo '<p><span style="color: #ff5252;"><strong>Contenuto:</strong> VUOTO ❌</span></p>';
            } else {
                echo '<p><span style="color: #69f0ae;"><strong>Lettura contenuto:</strong> OK ✅</span> (' . number_format(strlen($xmlcontent)) . ' caratteri)</p>';
                
                // Verifica XML
                if (strpos($xmlcontent, '<?xml') === false && strpos($xmlcontent, '<quiz') === false) {
                    $showerrors[] = '❌ Il file non sembra essere un XML valido!';
                    echo '<p><span style="color: #ff5252;"><strong>Formato XML:</strong> NON VALIDO ❌</span></p>';
                } else {
                    echo '<p><span style="color: #69f0ae;"><strong>Formato XML:</strong> OK ✅</span></p>';
                    
                    // Conta domande nel file
                    $questioncount = substr_count($xmlcontent, '<question type="multichoice"');
                    $questioncount += substr_count($xmlcontent, '<question type="truefalse"');
                    $questioncount += substr_count($xmlcontent, '<question type="shortanswer"');
                    echo '<p><strong>Domande rilevate (stima):</strong> ~' . $questioncount . '</p>';
                }
            }
        } else {
            $showerrors[] = '❌ Il file temporaneo non esiste!';
        }
        
        echo '</div>';
    }
    
    // Mostra errori di validazione
    if (!empty($showerrors)) {
        echo '<div style="background: #b71c1c; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h2 style="margin-top: 0;">⚠️ ERRORI DI VALIDAZIONE</h2>';
        foreach ($showerrors as $error) {
            echo '<p style="font-size: 16px; margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 4px;">' . $error . '</p>';
        }
        echo '</div>';
    }
    
    // Se non ci sono errori, procedi con l'import
    if (empty($showerrors) && !empty($xmlcontent)) {
        
        echo '<div style="background: #f57c00; color: white; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
        echo '<h3 style="margin: 0;">🚀 Avvio import...</h3>';
        echo '</div>';
        
        // Handle mapping CSV upload
        $mapping = [];
        if (isset($_FILES['mappingfile']) && $_FILES['mappingfile']['error'] === UPLOAD_ERR_OK) {
            $mappingcontent = file_get_contents($_FILES['mappingfile']['tmp_name']);
            $mapping = \local_competencyxmlimport\importer::parse_mapping_csv($mappingcontent);
            echo '<p>✅ Mapping personalizzato caricato: ' . count($mapping) . ' voci</p>';
        } else {
            $mappingpath = __DIR__ . '/mapping_competenze_livelli.csv';
            if (file_exists($mappingpath)) {
                $mappingcontent = file_get_contents($mappingpath);
                $mapping = \local_competencyxmlimport\importer::parse_mapping_csv($mappingcontent);
                echo '<p>✅ Mapping predefinito caricato: ' . count($mapping) . ' voci</p>';
            } else {
                echo '<p>⚠️ Nessun file di mapping trovato - verrà usato il livello predefinito</p>';
            }
        }
        
        // Import questions
        try {
            $importer = new \local_competencyxmlimport\importer($frameworkid, $categoryid, $mapping, $defaultlevel);
            $result = $importer->import_xml($xmlcontent);
            
            $imported = $result['imported'];
            $assigned = $result['assigned'];
            $errors = $result['errors'];
            $log = $result['log'];
            
        } catch (Exception $e) {
            echo '<div style="background: #b71c1c; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
            echo '<h2 style="margin-top: 0;">❌ ERRORE GRAVE DURANTE IMPORT</h2>';
            echo '<p><strong>Messaggio:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . $e->getFile() . '</p>';
            echo '<p><strong>Linea:</strong> ' . $e->getLine() . '</p>';
            echo '<h4>Stack trace:</h4>';
            echo '<pre style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 4px; overflow: auto; font-size: 11px;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
            $errors = 1;
        }
    }
    
    // Show results
    if ($imported > 0 || !empty($log)) {
        $bgColor = ($errors > 0 && $assigned == 0) ? '#e65100' : '#2e7d32';
        echo '<div style="background: ' . $bgColor . '; color: white; padding: 20px; margin: 20px 0; border-radius: 8px;">';
        echo '<h2 style="margin-top: 0;">📊 RISULTATI IMPORT</h2>';
        echo '<div style="display: flex; gap: 20px; flex-wrap: wrap;">';
        echo '<div style="background: rgba(255,255,255,0.2); padding: 15px 25px; border-radius: 8px; text-align: center;">';
        echo '<div style="font-size: 36px; font-weight: bold;">' . $imported . '</div>';
        echo '<div>Domande importate</div>';
        echo '</div>';
        echo '<div style="background: rgba(255,255,255,0.2); padding: 15px 25px; border-radius: 8px; text-align: center;">';
        echo '<div style="font-size: 36px; font-weight: bold;">' . $assigned . '</div>';
        echo '<div>Competenze assegnate</div>';
        echo '</div>';
        if ($errors > 0) {
            echo '<div style="background: rgba(244,67,54,0.5); padding: 15px 25px; border-radius: 8px; text-align: center;">';
            echo '<div style="font-size: 36px; font-weight: bold;">' . $errors . '</div>';
            echo '<div>Errori/Warning</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Show log
        if (!empty($log)) {
            echo '<h3 style="margin-top: 20px;">📜 Log dettagliato:</h3>';
            echo '<div style="max-height: 500px; overflow-y: auto; background: #1e1e1e; padding: 15px; border-radius: 8px; font-family: \'Consolas\', \'Monaco\', monospace; font-size: 13px;">';
            foreach ($log as $entry) {
                $style = 'color: #d4d4d4;';
                if (strpos($entry, '✅') !== false) $style = 'color: #4ec9b0;';
                elseif (strpos($entry, '❌') !== false) $style = 'color: #f14c4c;';
                elseif (strpos($entry, '⚠️') !== false) $style = 'color: #cca700;';
                elseif (strpos($entry, '📝') !== false) $style = 'color: #569cd6;';
                elseif (strpos($entry, '===') !== false) $style = 'color: #c586c0; font-weight: bold;';
                elseif (strpos($entry, '💡') !== false) $style = 'color: #dcdcaa;';
                
                echo '<div style="' . $style . '">' . htmlspecialchars($entry) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo '<div style="background: #b71c1c; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h2 style="margin-top: 0;">❌ ERRORE: Sesskey non valido!</h2>';
        echo '<p>La sessione potrebbe essere scaduta. Ricarica la pagina e riprova.</p>';
        echo '</div>';
    }
}

// ========================================
// FUNZIONI HELPER
// ========================================

/**
 * Recupera tutti i corsi disponibili
 */
function get_all_courses() {
    global $DB;
    
    $sql = "SELECT c.id, c.fullname, c.shortname, cat.name as categoryname
            FROM {course} c
            LEFT JOIN {course_categories} cat ON cat.id = c.category
            WHERE c.id > 1
            ORDER BY cat.name, c.fullname";
    
    return $DB->get_records_sql($sql);
}

/**
 * Recupera le categorie di domande per un corso specifico
 */
function get_course_question_categories($courseid) {
    global $DB;
    
    if ($courseid <= 0) {
        return [];
    }
    
    $coursecontext = context_course::instance($courseid);
    $contextids = [$coursecontext->id];
    
    // Aggiungi i context dei moduli (quiz) nel corso
    $sql = "SELECT DISTINCT ctx.id
            FROM {context} ctx
            JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
            WHERE cm.course = :courseid";
    
    $modulecontexts = $DB->get_records_sql($sql, [
        'contextlevel' => CONTEXT_MODULE,
        'courseid' => $courseid
    ]);
    
    foreach ($modulecontexts as $ctx) {
        $contextids[] = $ctx->id;
    }
    
    if (empty($contextids)) {
        return [];
    }
    
    list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    
    $sql = "SELECT qc.id, qc.name, qc.contextid, qc.parent
            FROM {question_categories} qc
            WHERE qc.contextid $insql
            ORDER BY qc.name";
    
    return $DB->get_records_sql($sql, $params);
}

// ========================================
// FORM DI IMPORT
// ========================================

echo '<hr style="margin: 30px 0; border: none; border-top: 3px solid #e0e0e0;">';

// ========================================
// STEP 1: Selezione Corso
// ========================================

echo '<div style="background: #1565c0; color: white; padding: 20px; border-radius: 8px 8px 0 0;">';
echo '<h3 style="margin: 0;">📚 Step 1: Seleziona il Corso</h3>';
echo '</div>';
echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';

echo '<form method="get">';

$courses = get_all_courses();
echo '<select name="courseid" onchange="this.form.submit()" style="width: 100%; max-width: 600px; padding: 12px; font-size: 16px; border: 2px solid #1565c0; border-radius: 4px;">';
echo '<option value="0">-- Seleziona un corso --</option>';
foreach ($courses as $course) {
    $label = $course->fullname;
    if (!empty($course->categoryname)) {
        $label = "[{$course->categoryname}] {$course->fullname}";
    }
    $selected = ($selectedcourseid == $course->id) ? 'selected' : '';
    echo '<option value="' . $course->id . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
}
echo '</select>';
echo '<p style="margin: 10px 0 0 0; color: #1565c0;">👆 Seleziona un corso per vedere le sue categorie di domande</p>';

echo '</form>';
echo '</div>';

// ========================================
// STEP 2: Form Import (solo se corso selezionato)
// ========================================

if ($selectedcourseid > 0) {
    
    $selectedcourse = $DB->get_record('course', ['id' => $selectedcourseid]);
    $coursecategories = get_course_question_categories($selectedcourseid);
    
    echo '<div style="background: #2e7d32; color: white; padding: 20px; border-radius: 8px 8px 0 0;">';
    echo '<h3 style="margin: 0;">📝 Step 2: Import per "' . htmlspecialchars($selectedcourse->fullname) . '"</h3>';
    echo '</div>';
    echo '<div style="background: #e8f5e9; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';
    
    if (empty($coursecategories)) {
        echo '<div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; border-radius: 4px;">';
        echo '⚠️ <strong>Nessuna categoria di domande trovata per questo corso.</strong><br>';
        echo 'Crea prima una categoria nella Banca delle domande del corso.';
        echo '</div>';
    } else {
        
        // Debug: mostra categorie trovate
        echo '<div style="background: #fff; border: 1px solid #c8e6c9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
        echo '<strong>🔍 DEBUG: ' . count($coursecategories) . ' categorie trovate per questo corso:</strong>';
        echo '<ul style="margin: 10px 0 0 0;">';
        foreach ($coursecategories as $cat) {
            echo '<li><code>' . htmlspecialchars($cat->name) . '</code> (ID: ' . $cat->id . ')</li>';
        }
        echo '</ul>';
        echo '</div>';
        
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="courseid" value="' . $selectedcourseid . '">';
        
        // XML file
        echo '<div style="margin-bottom: 20px;">';
        echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">📄 File XML</label>';
        echo '<input type="file" name="xmlfile" accept=".xml" required style="width: 100%; padding: 10px; border: 2px solid #4caf50; border-radius: 4px; background: white;">';
        echo '<small style="color: #666;">Formato: Moodle XML con codici competenza nel nome (es. "MECC_APPR_1_DT - 01 Descrizione")</small>';
        echo '</div>';
        
        // Categoria
        echo '<div style="margin-bottom: 20px;">';
        echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">📂 Categoria destinazione</label>';
        echo '<select name="category" required style="width: 100%; max-width: 600px; padding: 12px; border: 2px solid #4caf50; border-radius: 4px;">';
        echo '<option value="">-- Seleziona categoria --</option>';
        foreach ($coursecategories as $cat) {
            echo '<option value="' . $cat->id . '">' . htmlspecialchars($cat->name) . '</option>';
        }
        echo '</select>';
        echo '<br><small style="color: #2e7d32;">✅ Categorie del corso "' . htmlspecialchars($selectedcourse->shortname) . '"</small>';
        echo '</div>';
        
        // Framework
        echo '<div style="margin-bottom: 20px;">';
        echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">🎯 Framework competenze</label>';
        $frameworks = $DB->get_records('competency_framework', null, 'shortname');
        echo '<select name="framework" required style="width: 100%; max-width: 600px; padding: 12px; border: 2px solid #ff9800; border-radius: 4px;">';
        echo '<option value="">-- Seleziona framework --</option>';
        foreach ($frameworks as $fw) {
            $compcount = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
            $label = $fw->shortname . ' (' . $compcount . ' competenze)';
            if (!empty($fw->idnumber)) {
                $label .= ' [' . $fw->idnumber . ']';
            }
            echo '<option value="' . $fw->id . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '<br><small style="color: #e65100;">⚠️ IMPORTANTE: Seleziona il framework che contiene le competenze (es. MECC_APPR_1_DT)</small>';
        echo '</div>';
        
        // Mapping file
        echo '<div style="margin-bottom: 20px;">';
        echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">📋 File mappatura (opzionale)</label>';
        echo '<input type="file" name="mappingfile" accept=".csv" style="width: 100%; padding: 10px; border: 2px solid #9e9e9e; border-radius: 4px; background: white;">';
        echo '<small style="color: #666;">CSV con mappatura competenze → livelli. Se non caricato, usa livello predefinito.</small>';
        echo '</div>';
        
        // Default level
        echo '<div style="margin-bottom: 20px;">';
        echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">⭐ Livello predefinito</label>';
        echo '<select name="defaultlevel" style="width: 200px; padding: 12px; border: 2px solid #9e9e9e; border-radius: 4px;">';
        echo '<option value="1">⭐ Base</option>';
        echo '<option value="2">⭐⭐ Intermedio</option>';
        echo '<option value="3">⭐⭐⭐ Avanzato</option>';
        echo '</select>';
        echo '</div>';
        
        // Submit
        echo '<button type="submit" style="background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; padding: 15px 40px; font-size: 18px; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">';
        echo '🚀 Importa domande e assegna competenze';
        echo '</button>';
        
        echo '</form>';
    }
    
    echo '</div>';
}

// ========================================
// Help section
// ========================================

echo '<div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin-top: 20px; border-radius: 4px;">';
echo '<h3 style="margin-top: 0; color: #1565c0;">💡 Come usare questo import</h3>';
echo '<ol>';
echo '<li><strong>Seleziona il corso</strong> - Scegli il corso dove importare le domande</li>';
echo '<li><strong>Carica il file XML</strong> - File Moodle XML con codici competenza nel nome</li>';
echo '<li><strong>Seleziona la categoria</strong> - Categoria del corso dove importare</li>';
echo '<li><strong>Seleziona il framework</strong> - Framework contenente le competenze</li>';
echo '<li><strong>Clicca Importa</strong> - Avvia importazione e assegnazione automatica</li>';
echo '</ol>';

echo '<h4 style="color: #1565c0;">📝 Formato nome domande supportati:</h4>';
echo '<code style="display: block; background: #fff; padding: 10px; margin: 5px 0; border-radius: 4px;">MECC_APPR_1_DT - 01 Tratto continuo grosso</code>';
echo '<code style="display: block; background: #fff; padding: 10px; margin: 5px 0; border-radius: 4px;">MECC_MIS_L1 - Domanda sulla metrologia</code>';
echo '<code style="display: block; background: #fff; padding: 10px; margin: 5px 0; border-radius: 4px;">MECCANICA_DT_01 - Descrizione domanda</code>';
echo '</div>';

echo $OUTPUT->footer();