<?php
/**
 * Script per creare categorie domande dal framework competenze
 * 
 * Questo script:
 * 1. Legge il framework delle competenze
 * 2. Permette di selezionare un ramo (es. Meccanica)
 * 3. Crea le categorie di domande nel corso mantenendo la gerarchia
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/create_categories_from_framework.php'));
$PAGE->set_title('📂 Crea Categorie da Framework');
$PAGE->set_heading('📂 Crea Categorie da Framework Competenze');

// Parametri
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$parentcompetencyid = optional_param('parentcompetencyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

// Banner
echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin-bottom: 20px; border-radius: 8px;">';
echo '<h2 style="margin: 0;">📂 Crea Categorie da Framework Competenze</h2>';
echo '<p style="margin: 10px 0 0 0;">Crea automaticamente le categorie di domande basate sulla struttura del framework.</p>';
echo '</div>';

// ========================================
// FUNZIONI HELPER
// ========================================

/**
 * Ottiene tutti i framework disponibili
 */
function get_all_frameworks() {
    global $DB;
    return $DB->get_records('competency_framework', null, 'shortname');
}

/**
 * Ottiene tutti i corsi
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
 * Ottiene le competenze di primo livello di un framework
 */
function get_root_competencies($frameworkid) {
    global $DB;
    return $DB->get_records('competency', [
        'competencyframeworkid' => $frameworkid,
        'parentid' => 0
    ], 'sortorder, shortname');
}

/**
 * Ottiene le sotto-competenze di una competenza
 */
function get_child_competencies($parentid) {
    global $DB;
    return $DB->get_records('competency', ['parentid' => $parentid], 'sortorder, shortname');
}

/**
 * Costruisce l'albero delle competenze ricorsivamente
 */
function build_competency_tree($competencyid, $level = 0) {
    global $DB;
    
    $competency = $DB->get_record('competency', ['id' => $competencyid]);
    if (!$competency) return null;
    
    $node = [
        'id' => $competency->id,
        'shortname' => $competency->shortname,
        'idnumber' => $competency->idnumber,
        'description' => $competency->description,
        'level' => $level,
        'children' => []
    ];
    
    $children = get_child_competencies($competencyid);
    foreach ($children as $child) {
        $childnode = build_competency_tree($child->id, $level + 1);
        if ($childnode) {
            $node['children'][] = $childnode;
        }
    }
    
    return $node;
}

/**
 * Conta il numero totale di nodi nell'albero
 */
function count_tree_nodes($tree) {
    $count = 1;
    foreach ($tree['children'] as $child) {
        $count += count_tree_nodes($child);
    }
    return $count;
}

/**
 * Renderizza l'albero delle competenze come HTML
 */
function render_competency_tree($tree, $selectedid = 0) {
    $html = '<ul style="list-style: none; padding-left: 20px; margin: 5px 0;">';
    
    $hasChildren = !empty($tree['children']);
    $isSelected = ($tree['id'] == $selectedid);
    
    $style = $isSelected ? 'background: #e3f2fd; padding: 5px 10px; border-radius: 4px; border: 2px solid #1565c0;' : 'padding: 5px 10px;';
    
    $html .= '<li style="' . $style . '">';
    
    // Icona
    if ($hasChildren) {
        $html .= '📁 ';
    } else {
        $html .= '📄 ';
    }
    
    // Nome e codice
    $html .= '<strong>' . htmlspecialchars($tree['shortname']) . '</strong>';
    if (!empty($tree['idnumber'])) {
        $html .= ' <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' . htmlspecialchars($tree['idnumber']) . '</code>';
    }
    
    // Numero figli
    if ($hasChildren) {
        $html .= ' <span style="color: #666; font-size: 12px;">(' . count($tree['children']) . ' sotto-elementi)</span>';
    }
    
    // Sotto-elementi
    if ($hasChildren) {
        foreach ($tree['children'] as $child) {
            $html .= render_competency_tree($child, $selectedid);
        }
    }
    
    $html .= '</li>';
    $html .= '</ul>';
    
    return $html;
}

/**
 * Crea le categorie di domande ricorsivamente
 */
function create_categories_from_tree($tree, $contextid, $parentcategoryid = 0, &$log = [], $level = 0) {
    global $DB;
    
    // Nome categoria = shortname della competenza
    $categoryname = $tree['shortname'];
    
    // Info = idnumber della competenza (per riferimento)
    $info = '';
    if (!empty($tree['idnumber'])) {
        $info = 'Competenza: ' . $tree['idnumber'];
    }
    
    // Verifica se la categoria esiste già
    $existing = $DB->get_record('question_categories', [
        'contextid' => $contextid,
        'name' => $categoryname,
        'parent' => $parentcategoryid
    ]);
    
    if ($existing) {
        $log[] = str_repeat('  ', $level) . '⚠️ Categoria già esistente: ' . $categoryname . ' (ID: ' . $existing->id . ')';
        $categoryid = $existing->id;
    } else {
        // Crea la nuova categoria
        $category = new stdClass();
        $category->name = $categoryname;
        $category->contextid = $contextid;
        $category->parent = $parentcategoryid;
        $category->info = $info;
        $category->infoformat = FORMAT_HTML;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->idnumber = $tree['idnumber'] ?? null;
        
        $categoryid = $DB->insert_record('question_categories', $category);
        $log[] = str_repeat('  ', $level) . '✅ Creata: ' . $categoryname . ' (ID: ' . $categoryid . ')';
    }
    
    // Crea le sotto-categorie
    foreach ($tree['children'] as $child) {
        create_categories_from_tree($child, $contextid, $categoryid, $log, $level + 1);
    }
    
    return $categoryid;
}

/**
 * Ottiene o crea la categoria radice per il corso
 */
function get_or_create_root_category($contextid, $rootname) {
    global $DB;
    
    // Cerca la categoria "top" del contesto
    $topcategory = $DB->get_record('question_categories', [
        'contextid' => $contextid,
        'parent' => 0
    ]);
    
    if (!$topcategory) {
        // Crea la categoria top
        $topcategory = new stdClass();
        $topcategory->name = 'top';
        $topcategory->contextid = $contextid;
        $topcategory->parent = 0;
        $topcategory->info = '';
        $topcategory->infoformat = FORMAT_HTML;
        $topcategory->sortorder = 0;
        $topcategory->stamp = make_unique_id_code();
        $topcategory->id = $DB->insert_record('question_categories', $topcategory);
    }
    
    return $topcategory->id;
}

// ========================================
// ESECUZIONE AZIONE
// ========================================

if ($action === 'create' && $confirm && $frameworkid > 0 && $courseid > 0 && $parentcompetencyid > 0) {
    
    $course = $DB->get_record('course', ['id' => $courseid]);
    $competency = $DB->get_record('competency', ['id' => $parentcompetencyid]);
    $coursecontext = context_course::instance($courseid);
    
    if ($course && $competency) {
        
        echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0;">🔄 Creazione categorie in corso...</h3>';
        
        // Costruisci l'albero
        $tree = build_competency_tree($parentcompetencyid);
        
        // Ottieni la categoria radice
        $rootcategoryid = get_or_create_root_category($coursecontext->id, 'top');
        
        // Crea le categorie
        $log = [];
        create_categories_from_tree($tree, $coursecontext->id, $rootcategoryid, $log);
        
        echo '<h4>📜 Log creazione:</h4>';
        echo '<div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px; max-height: 400px; overflow-y: auto;">';
        foreach ($log as $entry) {
            $style = 'color: #d4d4d4;';
            if (strpos($entry, '✅') !== false) $style = 'color: #4ec9b0;';
            elseif (strpos($entry, '⚠️') !== false) $style = 'color: #cca700;';
            elseif (strpos($entry, '❌') !== false) $style = 'color: #f14c4c;';
            echo '<div style="' . $style . '">' . htmlspecialchars($entry) . '</div>';
        }
        echo '</div>';
        
        echo '<div style="margin-top: 15px; padding: 15px; background: #c8e6c9; border-radius: 8px;">';
        echo '✅ <strong>Completato!</strong> Le categorie sono state create nel corso "' . htmlspecialchars($course->fullname) . '"';
        echo '</div>';
        
        echo '</div>';
    }
}

// ========================================
// STEP 1: Selezione Framework
// ========================================

echo '<div style="background: #1565c0; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
echo '<h3 style="margin: 0;">🎯 Step 1: Seleziona Framework e Corso</h3>';
echo '</div>';
echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';

echo '<form method="get">';

// Framework
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Framework competenze:</label>';
$frameworks = get_all_frameworks();
echo '<select name="frameworkid" onchange="this.form.submit()" style="width: 100%; max-width: 500px; padding: 12px; border: 2px solid #1565c0; border-radius: 4px;">';
echo '<option value="0">-- Seleziona framework --</option>';
foreach ($frameworks as $fw) {
    $compcount = $DB->count_records('competency', ['competencyframeworkid' => $fw->id]);
    $selected = ($frameworkid == $fw->id) ? 'selected' : '';
    echo '<option value="' . $fw->id . '" ' . $selected . '>' . htmlspecialchars($fw->shortname) . ' (' . $compcount . ' competenze)</option>';
}
echo '</select>';
echo '</div>';

// Corso
echo '<div style="margin-bottom: 15px;">';
echo '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Corso destinazione:</label>';
$courses = get_all_courses();
echo '<select name="courseid" onchange="this.form.submit()" style="width: 100%; max-width: 500px; padding: 12px; border: 2px solid #1565c0; border-radius: 4px;">';
echo '<option value="0">-- Seleziona corso --</option>';
foreach ($courses as $course) {
    $label = $course->fullname;
    if (!empty($course->categoryname)) {
        $label = '[' . $course->categoryname . '] ' . $course->fullname;
    }
    $selected = ($courseid == $course->id) ? 'selected' : '';
    echo '<option value="' . $course->id . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
}
echo '</select>';
echo '</div>';

echo '</form>';
echo '</div>';

// ========================================
// STEP 2: Selezione Ramo Competenze
// ========================================

if ($frameworkid > 0 && $courseid > 0) {
    
    $framework = $DB->get_record('competency_framework', ['id' => $frameworkid]);
    $course = $DB->get_record('course', ['id' => $courseid]);
    
    echo '<div style="background: #2e7d32; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
    echo '<h3 style="margin: 0;">🌳 Step 2: Seleziona il Ramo da Importare</h3>';
    echo '</div>';
    echo '<div style="background: #e8f5e9; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';
    
    echo '<p>Seleziona il ramo delle competenze da cui creare le categorie:</p>';
    
    // Mostra le competenze di primo livello come opzioni
    $rootcompetencies = get_root_competencies($frameworkid);
    
    echo '<form method="get">';
    echo '<input type="hidden" name="frameworkid" value="' . $frameworkid . '">';
    echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
    
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; margin-bottom: 20px;">';
    
    foreach ($rootcompetencies as $comp) {
        $childcount = $DB->count_records('competency', ['parentid' => $comp->id]);
        $checked = ($parentcompetencyid == $comp->id) ? 'checked' : '';
        $style = ($parentcompetencyid == $comp->id) 
            ? 'background: #c8e6c9; border: 2px solid #2e7d32;' 
            : 'background: #fff; border: 2px solid #e0e0e0;';
        
        echo '<label style="display: block; padding: 15px; border-radius: 8px; cursor: pointer; ' . $style . '">';
        echo '<input type="radio" name="parentcompetencyid" value="' . $comp->id . '" ' . $checked . ' onchange="this.form.submit()" style="margin-right: 10px;">';
        echo '<strong>' . htmlspecialchars($comp->shortname) . '</strong>';
        if ($childcount > 0) {
            echo '<br><span style="color: #666; font-size: 12px;">📁 ' . $childcount . ' sotto-elementi</span>';
        }
        echo '</label>';
    }
    
    echo '</div>';
    echo '</form>';
    
    echo '</div>';
}

// ========================================
// STEP 3: Anteprima e Conferma
// ========================================

if ($frameworkid > 0 && $courseid > 0 && $parentcompetencyid > 0) {
    
    $competency = $DB->get_record('competency', ['id' => $parentcompetencyid]);
    $course = $DB->get_record('course', ['id' => $courseid]);
    
    // Costruisci l'albero
    $tree = build_competency_tree($parentcompetencyid);
    $totalNodes = count_tree_nodes($tree);
    
    echo '<div style="background: #ff9800; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
    echo '<h3 style="margin: 0;">👁️ Step 3: Anteprima Categorie</h3>';
    echo '</div>';
    echo '<div style="background: #fff3e0; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';
    
    echo '<div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffcc80;">';
    echo '<h4 style="margin-top: 0;">📊 Riepilogo:</h4>';
    echo '<ul style="margin-bottom: 0;">';
    echo '<li><strong>Framework:</strong> ' . htmlspecialchars($DB->get_field('competency_framework', 'shortname', ['id' => $frameworkid])) . '</li>';
    echo '<li><strong>Ramo selezionato:</strong> ' . htmlspecialchars($competency->shortname) . '</li>';
    echo '<li><strong>Corso destinazione:</strong> ' . htmlspecialchars($course->fullname) . '</li>';
    echo '<li><strong>Categorie da creare:</strong> ' . $totalNodes . '</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<h4>🌳 Struttura categorie che verranno create:</h4>';
    echo '<div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; max-height: 400px; overflow-y: auto;">';
    echo render_competency_tree($tree);
    echo '</div>';
    
    // Pulsante conferma
    echo '<div style="margin-top: 20px; text-align: center;">';
    echo '<form method="get" style="display: inline;">';
    echo '<input type="hidden" name="frameworkid" value="' . $frameworkid . '">';
    echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
    echo '<input type="hidden" name="parentcompetencyid" value="' . $parentcompetencyid . '">';
    echo '<input type="hidden" name="action" value="create">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" style="background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; padding: 15px 40px; font-size: 18px; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">';
    echo '✅ Crea ' . $totalNodes . ' Categorie nel Corso';
    echo '</button>';
    echo '</form>';
    echo '</div>';
    
    echo '</div>';
}

// ========================================
// Help
// ========================================

echo '<div style="background: #f5f5f5; border-left: 4px solid #9e9e9e; padding: 20px; margin-top: 20px; border-radius: 4px;">';
echo '<h3 style="margin-top: 0; color: #616161;">💡 Come funziona</h3>';
echo '<ol>';
echo '<li><strong>Seleziona il framework</strong> - Es. "01_Passaporto tecnico FTM"</li>';
echo '<li><strong>Seleziona il corso</strong> - Es. "Meccanica"</li>';
echo '<li><strong>Seleziona il ramo</strong> - Es. "Meccanica" per creare solo le categorie di meccanica</li>';
echo '<li><strong>Verifica l\'anteprima</strong> - Controlla la struttura delle categorie</li>';
echo '<li><strong>Clicca "Crea Categorie"</strong> - Le categorie vengono create nel corso</li>';
echo '</ol>';
echo '<p style="margin-bottom: 0;"><strong>Nota:</strong> Le categorie esistenti con lo stesso nome non vengono duplicate.</p>';
echo '</div>';

echo $OUTPUT->footer();
