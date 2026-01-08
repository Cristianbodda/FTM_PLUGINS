<?php
// Import XML questions with automatic competency assignment
// VERSIONE CORRETTA - Con selezione corso e categorie filtrate

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/importer.php');

require_login();
$context = context_system::instance();
require_capability('moodle/question:add', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/import.php'));
$PAGE->set_title(get_string('importxml', 'local_competencyxmlimport'));
$PAGE->set_heading(get_string('importxml', 'local_competencyxmlimport'));

// Recupera parametri
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);

// Process form submission
$imported = 0;
$assigned = 0;
$errors = 0;
$log = [];
$showerrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    
    // VALIDAZIONE INPUT
    $categoryid = optional_param('category', 0, PARAM_INT);
    $frameworkid = optional_param('framework', 0, PARAM_INT);
    $defaultlevel = optional_param('defaultlevel', 1, PARAM_INT);
    $selectedcourseid = optional_param('courseid', 0, PARAM_INT);
    
    // Verifica categoria selezionata
    if ($categoryid <= 0) {
        $showerrors[] = '❌ Devi selezionare una categoria di destinazione!';
    }
    
    // Verifica framework selezionato
    if ($frameworkid <= 0) {
        $showerrors[] = '❌ Devi selezionare un framework delle competenze!';
    }
    
    // Verifica file XML caricato
    if (!isset($_FILES['xmlfile']) || $_FILES['xmlfile']['error'] !== UPLOAD_ERR_OK) {
        if (!isset($_FILES['xmlfile'])) {
            $showerrors[] = '❌ Nessun file XML caricato!';
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
    
    // Verifica che il file sia effettivamente un XML
    if (empty($showerrors) && isset($_FILES['xmlfile']['tmp_name'])) {
        $xmlcontent = @file_get_contents($_FILES['xmlfile']['tmp_name']);
        
        if ($xmlcontent === false || empty($xmlcontent)) {
            $showerrors[] = '❌ Impossibile leggere il file o il file è vuoto!';
        } elseif (strpos($xmlcontent, '<?xml') === false && strpos($xmlcontent, '<quiz') === false) {
            $showerrors[] = '❌ Il file caricato non sembra essere un XML valido!';
        }
    }
    
    // Se non ci sono errori di validazione, procedi con l'import
    if (empty($showerrors)) {
        
        // Handle mapping CSV upload (optional - use default if not provided)
        $mapping = [];
        if (isset($_FILES['mappingfile']) && $_FILES['mappingfile']['error'] === UPLOAD_ERR_OK) {
            $mappingcontent = file_get_contents($_FILES['mappingfile']['tmp_name']);
            $mapping = \local_competencyxmlimport\importer::parse_mapping_csv($mappingcontent);
        } else {
            // Use default mapping from plugin
            $mappingpath = __DIR__ . '/mapping_competenze_livelli.csv';
            if (file_exists($mappingpath)) {
                $mappingcontent = file_get_contents($mappingpath);
                $mapping = \local_competencyxmlimport\importer::parse_mapping_csv($mappingcontent);
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
            $showerrors[] = '❌ ERRORE GRAVE: ' . $e->getMessage();
            $log[] = $e->getTraceAsString();
        }
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
    
    // Ottieni il context del corso
    $coursecontext = context_course::instance($courseid);
    
    // Recupera tutte le categorie per questo context e i suoi figli (moduli)
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
    
    // Recupera le categorie
    list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    
    $sql = "SELECT qc.id, qc.name, qc.contextid, qc.parent
            FROM {question_categories} qc
            WHERE qc.contextid $insql
            ORDER BY qc.name";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Recupera le categorie di sistema (non legate a corsi)
 */
function get_system_question_categories() {
    global $DB;
    
    $systemcontext = context_system::instance();
    
    return $DB->get_records('question_categories', 
        ['contextid' => $systemcontext->id], 
        'name', 
        'id, name, contextid, parent'
    );
}

/**
 * Costruisce il menu delle categorie con gerarchia
 */
function build_category_menu($categories, $coursename = '') {
    $menu = [];
    
    foreach ($categories as $cat) {
        $prefix = $coursename ? "[$coursename] " : "";
        $menu[$cat->id] = $prefix . $cat->name;
    }
    
    return $menu;
}

// ========================================
// OUTPUT HTML
// ========================================

echo $OUTPUT->header();

// Show validation errors if any
if (!empty($showerrors)) {
    echo html_writer::start_div('alert alert-danger');
    echo html_writer::tag('h3', '⚠️ Errori di validazione');
    foreach ($showerrors as $error) {
        echo html_writer::tag('p', $error);
    }
    echo html_writer::end_div();
}

// Show results if import was performed
if ($imported > 0 || (!empty($log) && $errors >= 0)) {
    $alertclass = ($errors > 0 && $assigned == 0) ? 'alert alert-warning' : 'alert alert-success';
    echo html_writer::start_div($alertclass);
    echo html_writer::tag('h3', '📊 Risultati Import');
    echo html_writer::tag('p', "📥 Domande importate: <strong>{$imported}</strong>");
    echo html_writer::tag('p', "✅ Competenze assegnate: <strong>{$assigned}</strong>");
    if ($errors > 0) {
        echo html_writer::tag('p', "⚠️ Errori/Warning: <strong>{$errors}</strong>", ['class' => 'text-warning']);
    }
    
    // Show log
    if (!empty($log)) {
        echo html_writer::tag('h4', '📋 Dettagli import:', ['style' => 'margin-top: 15px;']);
        echo html_writer::start_tag('div', [
            'style' => 'max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; font-family: "Consolas", "Monaco", monospace; font-size: 13px;'
        ]);
        foreach ($log as $entry) {
            // Colora le righe in base al contenuto
            $style = '';
            if (strpos($entry, '✅') !== false) {
                $style = 'color: #4ec9b0;'; // verde
            } elseif (strpos($entry, '❌') !== false) {
                $style = 'color: #f14c4c;'; // rosso
            } elseif (strpos($entry, '⚠️') !== false) {
                $style = 'color: #cca700;'; // giallo
            } elseif (strpos($entry, '📝') !== false) {
                $style = 'color: #569cd6;'; // blu
            } elseif (strpos($entry, '===') !== false) {
                $style = 'color: #c586c0; font-weight: bold;'; // viola
            }
            echo "<div style='{$style}'>" . htmlspecialchars($entry) . "</div>";
        }
        echo html_writer::end_tag('div');
    }
    
    echo html_writer::end_div();
}

// ========================================
// STEP 1: Selezione Corso
// ========================================

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header bg-primary text-white');
echo html_writer::tag('h4', '📚 Step 1: Seleziona il Corso', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'class' => 'form-inline'
]);

$courses = get_all_courses();
$coursemenu = [0 => '-- Seleziona un corso --'];
foreach ($courses as $course) {
    $label = $course->fullname;
    if (!empty($course->categoryname)) {
        $label = "[{$course->categoryname}] {$course->fullname}";
    }
    $coursemenu[$course->id] = $label;
}

echo html_writer::tag('label', 'Corso: ', ['for' => 'courseid', 'class' => 'mr-2']);
echo html_writer::select($coursemenu, 'courseid', $selectedcourseid, false, [
    'class' => 'form-control mr-3',
    'id' => 'courseid',
    'onchange' => 'this.form.submit()'
]);

echo html_writer::tag('small', '👆 Seleziona un corso per vedere le sue categorie di domande', ['class' => 'text-muted ml-2']);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

// ========================================
// STEP 2: Form Import (solo se corso selezionato)
// ========================================

if ($selectedcourseid > 0) {
    
    // Recupera info corso
    $selectedcourse = $DB->get_record('course', ['id' => $selectedcourseid]);
    
    // Recupera categorie del corso
    $coursecategories = get_course_question_categories($selectedcourseid);
    
    echo html_writer::start_div('card');
    echo html_writer::start_div('card-header bg-success text-white');
    echo html_writer::tag('h4', "📝 Step 2: Import per \"{$selectedcourse->fullname}\"", ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    
    if (empty($coursecategories)) {
        echo html_writer::div(
            '⚠️ Nessuna categoria di domande trovata per questo corso. Crea prima una categoria nella Banca delle domande del corso.',
            'alert alert-warning'
        );
    } else {
        
        // Import form
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'class' => 'mform'
        ]);
        
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);
        
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'courseid',
            'value' => $selectedcourseid
        ]);
        
        // XML file upload
        echo html_writer::start_div('form-group row mb-3');
        echo html_writer::tag('label', '📄 File XML', ['for' => 'xmlfile', 'class' => 'col-sm-3 col-form-label']);
        echo html_writer::start_div('col-sm-9');
        echo html_writer::empty_tag('input', [
            'type' => 'file',
            'name' => 'xmlfile',
            'id' => 'xmlfile',
            'accept' => '.xml',
            'required' => 'required',
            'class' => 'form-control'
        ]);
        echo html_writer::tag('small', 'Formato: Moodle XML con codici competenza nel nome (es. "MECC_APPR_1_DT - 01 Descrizione")', ['class' => 'form-text text-muted']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Category selection
        echo html_writer::start_div('form-group row mb-3');
        echo html_writer::tag('label', '📂 Categoria destinazione', ['for' => 'category', 'class' => 'col-sm-3 col-form-label']);
        echo html_writer::start_div('col-sm-9');
        
        $categorymenu = build_category_menu($coursecategories);
        echo html_writer::select($categorymenu, 'category', '', ['' => '-- Seleziona categoria --'], [
            'class' => 'form-control',
            'required' => 'required'
        ]);
        echo html_writer::tag('small', "✅ Categorie del corso \"{$selectedcourse->shortname}\"", ['class' => 'form-text text-success']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Framework selection
        echo html_writer::start_div('form-group row mb-3');
        echo html_writer::tag('label', '🎯 Framework competenze', ['for' => 'framework', 'class' => 'col-sm-3 col-form-label']);
        echo html_writer::start_div('col-sm-9');
        
        $frameworks = $DB->get_records('competency_framework', null, 'shortname', 'id, shortname, idnumber');
        $frameworkmenu = [];
        foreach ($frameworks as $fw) {
            $label = $fw->shortname;
            if (!empty($fw->idnumber)) {
                $label .= " ({$fw->idnumber})";
            }
            $frameworkmenu[$fw->id] = $label;
        }
        
        echo html_writer::select($frameworkmenu, 'framework', '', ['' => '-- Seleziona framework --'], [
            'class' => 'form-control',
            'required' => 'required'
        ]);
        echo html_writer::tag('small', '⚠️ Seleziona il framework che contiene le competenze (es. MECC_APPR_1_DT)', ['class' => 'form-text text-warning']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Mapping file upload (optional)
        echo html_writer::start_div('form-group row mb-3');
        echo html_writer::tag('label', '📋 File mappatura (opzionale)', ['for' => 'mappingfile', 'class' => 'col-sm-3 col-form-label']);
        echo html_writer::start_div('col-sm-9');
        echo html_writer::empty_tag('input', [
            'type' => 'file',
            'name' => 'mappingfile',
            'id' => 'mappingfile',
            'accept' => '.csv',
            'class' => 'form-control'
        ]);
        echo html_writer::tag('small', 'CSV con mappatura competenze → livelli. Se non caricato, usa mappatura predefinita.', ['class' => 'form-text text-muted']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Default level
        echo html_writer::start_div('form-group row mb-3');
        echo html_writer::tag('label', '⭐ Livello predefinito', ['for' => 'defaultlevel', 'class' => 'col-sm-3 col-form-label']);
        echo html_writer::start_div('col-sm-9');
        $levels = [
            1 => '⭐ Base',
            2 => '⭐⭐ Intermedio',
            3 => '⭐⭐⭐ Avanzato'
        ];
        echo html_writer::select($levels, 'defaultlevel', 1, false, ['class' => 'form-control']);
        echo html_writer::tag('small', 'Livello da assegnare se la competenza non è nella mappatura', ['class' => 'form-text text-muted']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Submit button
        echo html_writer::start_div('form-group row');
        echo html_writer::start_div('col-sm-9 offset-sm-3');
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => '🚀 Importa domande e assegna competenze',
            'class' => 'btn btn-primary btn-lg'
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        echo html_writer::end_tag('form');
    }
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// ========================================
// Help section
// ========================================

echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header bg-info text-white');
echo html_writer::tag('h4', '💡 Come usare questo import', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');

echo html_writer::tag('ol', 
    html_writer::tag('li', '<strong>Seleziona il corso</strong> - Scegli il corso dove importare le domande') .
    html_writer::tag('li', '<strong>Carica il file XML</strong> - File Moodle XML con codici competenza nel nome') .
    html_writer::tag('li', '<strong>Seleziona la categoria</strong> - Categoria del corso dove importare') .
    html_writer::tag('li', '<strong>Seleziona il framework</strong> - Framework contenente le competenze') .
    html_writer::tag('li', '<strong>Clicca Importa</strong> - Avvia importazione e assegnazione automatica')
);

echo html_writer::tag('h5', '📝 Formato nome domande:', ['class' => 'mt-3']);
echo html_writer::tag('p', 'Il codice competenza deve essere all\'inizio del nome, seguito da " - ":');
echo html_writer::tag('code', 'MECC_APPR_1_DT - 01 Tratto continuo grosso', ['class' => 'd-block bg-light p-2 mb-2']);
echo html_writer::tag('code', 'MECC_MIS_L1 - Domanda sulla metrologia', ['class' => 'd-block bg-light p-2 mb-2']);
echo html_writer::tag('code', 'MECCANICA_DT_01 - Descrizione domanda', ['class' => 'd-block bg-light p-2']);

echo html_writer::end_div();
echo html_writer::end_div();

// ========================================
// Debug info (solo per admin)
// ========================================

if (is_siteadmin() && $selectedcourseid > 0) {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-header bg-secondary text-white');
    echo html_writer::tag('h4', '🔧 Debug Info (solo admin)', ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    
    echo html_writer::tag('p', '<strong>Corso selezionato:</strong> ' . $selectedcourseid);
    echo html_writer::tag('p', '<strong>Categorie trovate:</strong> ' . count($coursecategories));
    
    if (!empty($coursecategories)) {
        echo html_writer::tag('h5', 'Dettaglio categorie:');
        echo '<ul>';
        foreach ($coursecategories as $cat) {
            echo "<li><strong>{$cat->name}</strong> (ID: {$cat->id}, Context: {$cat->contextid})</li>";
        }
        echo '</ul>';
    }
    
    // Mostra anche i framework disponibili
    echo html_writer::tag('h5', 'Framework disponibili:', ['class' => 'mt-3']);
    $allframeworks = $DB->get_records('competency_framework', null, 'shortname');
    echo '<ul>';
    foreach ($allframeworks as $fw) {
        // Conta competenze nel framework
        $compcount = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
        echo "<li><strong>{$fw->shortname}</strong> (ID: {$fw->id}) - {$compcount} competenze</li>";
    }
    echo '</ul>';
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
