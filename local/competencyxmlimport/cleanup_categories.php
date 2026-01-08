<?php
/**
 * Script di pulizia categorie duplicate
 * 
 * Questo script:
 * 1. Mostra tutte le categorie del corso
 * 2. Identifica i duplicati
 * 3. Permette di eliminare le categorie vuote o spostare le domande
 * 
 * ATTENZIONE: Fare sempre un backup del database prima di eseguire!
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/cleanup_categories.php'));
$PAGE->set_title('🧹 Pulizia Categorie Domande');
$PAGE->set_heading('🧹 Pulizia Categorie Domande');

// Parametri
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$targetcategoryid = optional_param('targetcategoryid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

// Banner
echo '<div style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 20px; margin-bottom: 20px; border-radius: 8px;">';
echo '<h2 style="margin: 0;">🧹 Pulizia Categorie Domande</h2>';
echo '<p style="margin: 10px 0 0 0;">Questo strumento aiuta a rimuovere categorie duplicate e inutili.</p>';
echo '</div>';

// Warning
echo '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
echo '<h4 style="margin-top: 0; color: #856404;">⚠️ ATTENZIONE</h4>';
echo '<ul style="margin-bottom: 0; color: #856404;">';
echo '<li>Fai sempre un <strong>backup del database</strong> prima di procedere!</li>';
echo '<li>Le domande eliminate <strong>non possono essere recuperate</strong></li>';
echo '<li>Controlla attentamente prima di confermare ogni azione</li>';
echo '</ul>';
echo '</div>';

// ========================================
// FUNZIONI HELPER
// ========================================

function get_courses_with_questions() {
    global $DB;
    
    $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {question_categories} qc ON qc.contextid = ctx.id
            WHERE c.id > 1
            ORDER BY c.fullname";
    
    return $DB->get_records_sql($sql);
}

function get_course_categories_detailed($courseid) {
    global $DB;
    
    $coursecontext = context_course::instance($courseid);
    
    // Ottieni tutti i context del corso (corso + moduli)
    $sql = "SELECT ctx.id
            FROM {context} ctx
            WHERE ctx.id = :coursecontextid
            
            UNION
            
            SELECT ctx.id
            FROM {context} ctx
            JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = 70
            WHERE cm.course = :courseid";
    
    $contexts = $DB->get_records_sql($sql, [
        'coursecontextid' => $coursecontext->id,
        'courseid' => $courseid
    ]);
    
    if (empty($contexts)) {
        return [];
    }
    
    $contextids = array_keys($contexts);
    list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    
    // Conta le domande per categoria
    $sql = "SELECT qc.id, qc.name, qc.contextid, qc.parent, qc.info,
                   ctx.contextlevel,
                   (SELECT COUNT(DISTINCT q.id) 
                    FROM {question} q
                    JOIN {question_versions} qv ON qv.questionid = q.id
                    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                    WHERE qbe.questioncategoryid = qc.id) as questioncount
            FROM {question_categories} qc
            JOIN {context} ctx ON ctx.id = qc.contextid
            WHERE qc.contextid $insql
            ORDER BY qc.name, qc.id";
    
    return $DB->get_records_sql($sql, $params);
}

function get_category_context_info($contextid) {
    global $DB;
    
    $ctx = $DB->get_record('context', ['id' => $contextid]);
    if (!$ctx) return 'Sconosciuto';
    
    if ($ctx->contextlevel == 50) {
        return '📚 Corso';
    } elseif ($ctx->contextlevel == 70) {
        $cm = $DB->get_record('course_modules', ['id' => $ctx->instanceid]);
        if ($cm) {
            $module = $DB->get_record('modules', ['id' => $cm->module]);
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
            if ($quiz) {
                return '📝 Quiz: ' . $quiz->name;
            }
            return '📦 Modulo: ' . ($module ? $module->name : 'Sconosciuto');
        }
    }
    return 'Context: ' . $ctx->contextlevel;
}

function delete_category_with_questions($categoryid) {
    global $DB;
    
    // Elimina le domande nella categoria
    $sql = "SELECT q.id
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid";
    
    $questions = $DB->get_records_sql($sql, ['categoryid' => $categoryid]);
    
    foreach ($questions as $question) {
        question_delete_question($question->id);
    }
    
    // Elimina la categoria
    $DB->delete_records('question_categories', ['id' => $categoryid]);
    
    return count($questions);
}

function move_questions_to_category($fromcategoryid, $tocategoryid) {
    global $DB;
    
    // Aggiorna le question_bank_entries per spostare le domande
    $count = $DB->count_records('question_bank_entries', ['questioncategoryid' => $fromcategoryid]);
    
    $DB->set_field('question_bank_entries', 'questioncategoryid', $tocategoryid, 
                   ['questioncategoryid' => $fromcategoryid]);
    
    return $count;
}

// ========================================
// GESTIONE AZIONI
// ========================================

if ($action === 'delete' && $categoryid > 0 && $confirm) {
    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
    if ($category) {
        $deleted = delete_category_with_questions($categoryid);
        echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
        echo '✅ <strong>Categoria eliminata:</strong> ' . htmlspecialchars($category->name) . '<br>';
        echo '📊 Domande eliminate: ' . $deleted;
        echo '</div>';
    }
}

if ($action === 'move' && $categoryid > 0 && $targetcategoryid > 0 && $confirm) {
    $fromcat = $DB->get_record('question_categories', ['id' => $categoryid]);
    $tocat = $DB->get_record('question_categories', ['id' => $targetcategoryid]);
    if ($fromcat && $tocat) {
        $moved = move_questions_to_category($categoryid, $targetcategoryid);
        
        // Elimina la categoria vuota
        $DB->delete_records('question_categories', ['id' => $categoryid]);
        
        echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
        echo '✅ <strong>Domande spostate:</strong> ' . $moved . '<br>';
        echo '📂 Da: ' . htmlspecialchars($fromcat->name) . '<br>';
        echo '📂 A: ' . htmlspecialchars($tocat->name) . '<br>';
        echo '🗑️ Categoria originale eliminata';
        echo '</div>';
    }
}

if ($action === 'deleteempty' && $courseid > 0 && $confirm) {
    $categories = get_course_categories_detailed($courseid);
    $deleted = 0;
    foreach ($categories as $cat) {
        if ($cat->questioncount == 0) {
            $DB->delete_records('question_categories', ['id' => $cat->id]);
            $deleted++;
        }
    }
    echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
    echo '✅ <strong>Categorie vuote eliminate:</strong> ' . $deleted;
    echo '</div>';
}

if ($action === 'deletedefaults' && $courseid > 0 && $confirm) {
    $categories = get_course_categories_detailed($courseid);
    $deleted = 0;
    $questionsdeleted = 0;
    foreach ($categories as $cat) {
        if (strpos($cat->name, 'Default') === 0 || $cat->name === 'top') {
            $questionsdeleted += delete_category_with_questions($cat->id);
            $deleted++;
        }
    }
    echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 8px;">';
    echo '✅ <strong>Categorie "Default/top" eliminate:</strong> ' . $deleted . '<br>';
    echo '📊 Domande eliminate: ' . $questionsdeleted;
    echo '</div>';
}

// ========================================
// STEP 1: Selezione Corso
// ========================================

echo '<div style="background: #1565c0; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
echo '<h3 style="margin: 0;">📚 Step 1: Seleziona il Corso</h3>';
echo '</div>';
echo '<div style="background: #e3f2fd; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';

echo '<form method="get">';
$courses = get_courses_with_questions();
echo '<select name="courseid" onchange="this.form.submit()" style="width: 100%; max-width: 500px; padding: 12px; font-size: 16px; border: 2px solid #1565c0; border-radius: 4px;">';
echo '<option value="0">-- Seleziona un corso --</option>';
foreach ($courses as $course) {
    $selected = ($courseid == $course->id) ? 'selected' : '';
    echo '<option value="' . $course->id . '" ' . $selected . '>' . htmlspecialchars($course->fullname) . '</option>';
}
echo '</select>';
echo '</form>';

echo '</div>';

// ========================================
// STEP 2: Visualizza Categorie
// ========================================

if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    $categories = get_course_categories_detailed($courseid);
    
    // Raggruppa per nome per trovare duplicati
    $byname = [];
    foreach ($categories as $cat) {
        $byname[$cat->name][] = $cat;
    }
    
    echo '<div style="background: #2e7d32; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
    echo '<h3 style="margin: 0;">📂 Categorie del corso "' . htmlspecialchars($course->fullname) . '"</h3>';
    echo '</div>';
    echo '<div style="background: #e8f5e9; padding: 20px; border-radius: 0 0 8px 8px; margin-bottom: 20px;">';
    
    // Statistiche
    $totalcats = count($categories);
    $duplicates = 0;
    $emptycats = 0;
    $defaultcats = 0;
    
    foreach ($byname as $name => $cats) {
        if (count($cats) > 1) $duplicates += count($cats) - 1;
    }
    foreach ($categories as $cat) {
        if ($cat->questioncount == 0) $emptycats++;
        if (strpos($cat->name, 'Default') === 0 || $cat->name === 'top') $defaultcats++;
    }
    
    echo '<div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">';
    echo '<div style="background: #fff; padding: 15px 25px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<div style="font-size: 28px; font-weight: bold; color: #1565c0;">' . $totalcats . '</div>';
    echo '<div style="color: #666;">Totale categorie</div>';
    echo '</div>';
    echo '<div style="background: #fff; padding: 15px 25px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<div style="font-size: 28px; font-weight: bold; color: #e65100;">' . $duplicates . '</div>';
    echo '<div style="color: #666;">Duplicati</div>';
    echo '</div>';
    echo '<div style="background: #fff; padding: 15px 25px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<div style="font-size: 28px; font-weight: bold; color: #9e9e9e;">' . $emptycats . '</div>';
    echo '<div style="color: #666;">Vuote</div>';
    echo '</div>';
    echo '<div style="background: #fff; padding: 15px 25px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<div style="font-size: 28px; font-weight: bold; color: #f44336;">' . $defaultcats . '</div>';
    echo '<div style="color: #666;">Default/top</div>';
    echo '</div>';
    echo '</div>';
    
    // Azioni rapide
    echo '<div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h4 style="margin-top: 0;">⚡ Azioni Rapide</h4>';
    echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
    
    if ($emptycats > 0) {
        echo '<a href="?courseid=' . $courseid . '&action=deleteempty&confirm=1" ';
        echo 'onclick="return confirm(\'Eliminare tutte le ' . $emptycats . ' categorie vuote?\');" ';
        echo 'style="background: #ff9800; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">';
        echo '🗑️ Elimina categorie vuote (' . $emptycats . ')';
        echo '</a>';
    }
    
    if ($defaultcats > 0) {
        echo '<a href="?courseid=' . $courseid . '&action=deletedefaults&confirm=1" ';
        echo 'onclick="return confirm(\'Eliminare tutte le ' . $defaultcats . ' categorie Default/top e le loro domande?\');" ';
        echo 'style="background: #f44336; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">';
        echo '🗑️ Elimina Default/top (' . $defaultcats . ')';
        echo '</a>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // Tabella categorie
    echo '<h4>📋 Elenco Categorie</h4>';
    echo '<table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden;">';
    echo '<thead>';
    echo '<tr style="background: #f5f5f5;">';
    echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">ID</th>';
    echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Nome</th>';
    echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Contesto</th>';
    echo '<th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd;">Domande</th>';
    echo '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Stato</th>';
    echo '<th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd;">Azioni</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($categories as $cat) {
        $isDuplicate = count($byname[$cat->name]) > 1;
        $isEmpty = $cat->questioncount == 0;
        $isDefault = (strpos($cat->name, 'Default') === 0 || $cat->name === 'top');
        
        $rowStyle = '';
        if ($isDefault) $rowStyle = 'background: #ffebee;';
        elseif ($isDuplicate) $rowStyle = 'background: #fff3e0;';
        elseif ($isEmpty) $rowStyle = 'background: #f5f5f5;';
        
        echo '<tr style="' . $rowStyle . '">';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . $cat->id . '</td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>' . htmlspecialchars($cat->name) . '</strong></td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee; font-size: 12px;">' . get_category_context_info($cat->contextid) . '</td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">';
        if ($cat->questioncount > 0) {
            echo '<span style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 12px;">' . $cat->questioncount . '</span>';
        } else {
            echo '<span style="color: #999;">0</span>';
        }
        echo '</td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">';
        if ($isDefault) echo '<span style="background: #f44336; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">DEFAULT</span> ';
        if ($isDuplicate) echo '<span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">DUPLICATO</span> ';
        if ($isEmpty) echo '<span style="background: #9e9e9e; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">VUOTA</span>';
        echo '</td>';
        echo '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">';
        
        // Pulsante elimina
        echo '<a href="?courseid=' . $courseid . '&action=delete&categoryid=' . $cat->id . '&confirm=1" ';
        echo 'onclick="return confirm(\'Eliminare la categoria \\\''.addslashes($cat->name).'\\\' e le sue ' . $cat->questioncount . ' domande?\');" ';
        echo 'style="color: #f44336; margin-right: 10px;" title="Elimina">';
        echo '🗑️';
        echo '</a>';
        
        // Pulsante sposta (se ha domande e ci sono duplicati)
        if ($cat->questioncount > 0 && $isDuplicate) {
            echo '<a href="#" onclick="showMoveDialog(' . $cat->id . ', \'' . addslashes($cat->name) . '\', ' . $courseid . '); return false;" ';
            echo 'style="color: #1565c0;" title="Sposta domande">';
            echo '📦';
            echo '</a>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>';
    
    // ========================================
    // Categorie consigliate da tenere
    // ========================================
    
    echo '<div style="background: #4caf50; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
    echo '<h3 style="margin: 0;">✅ Struttura Consigliata</h3>';
    echo '</div>';
    echo '<div style="background: #e8f5e9; padding: 20px; border-radius: 0 0 8px 8px;">';
    
    echo '<p>Dopo la pulizia, dovresti avere <strong>una sola categoria</strong> per ogni competenza:</p>';
    
    echo '<table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px;">';
    echo '<tr style="background: #c8e6c9;">';
    echo '<th style="padding: 10px; text-align: left;">Categoria</th>';
    echo '<th style="padding: 10px; text-align: left;">Codice Competenza</th>';
    echo '</tr>';
    
    $recommended = [
        'Disegno tecnico – Livello 1' => 'MECC_DT_L1',
        'Disegno tecnico – Livello 2' => 'MECC_DT_L2',
        'Metrologia – Livello 1' => 'MECC_MIS_L1',
        'Metrologia – Livello 2' => 'MECC_MIS_L2',
        'Lavorazioni meccaniche – Livello 1' => 'MECC_LAV_L1',
        'Lavorazioni meccaniche e CNC – Livello 2' => 'MECC_LAV_L2',
        'Materiali – Livello 1' => 'MECC_MAT_L1',
        'Materiali e trattamenti – Livello 2' => 'MECC_MAT_L2',
        'Controllo qualità – Livello 1' => 'MECC_CQ_L1',
        'Controllo qualità – Livello 2' => 'MECC_CQ_L2',
    ];
    
    foreach ($recommended as $name => $code) {
        echo '<tr>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . $name . '</td>';
        echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><code style="background: #e8f5e9; padding: 2px 6px; border-radius: 4px;">' . $code . '</code></td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</div>';
}

// Dialog per spostamento
echo '<div id="moveDialog" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">';
echo '<div style="background: white; max-width: 500px; margin: 100px auto; padding: 20px; border-radius: 8px;">';
echo '<h3 id="moveDialogTitle">Sposta domande</h3>';
echo '<form method="get">';
echo '<input type="hidden" name="courseid" id="moveCourseid">';
echo '<input type="hidden" name="action" value="move">';
echo '<input type="hidden" name="categoryid" id="moveCategoryid">';
echo '<input type="hidden" name="confirm" value="1">';
echo '<p>Seleziona la categoria di destinazione:</p>';
echo '<select name="targetcategoryid" id="moveTarget" style="width: 100%; padding: 10px; margin-bottom: 15px;">';
echo '</select>';
echo '<div style="text-align: right;">';
echo '<button type="button" onclick="hideMoveDialog()" style="padding: 10px 20px; margin-right: 10px;">Annulla</button>';
echo '<button type="submit" style="background: #1565c0; color: white; padding: 10px 20px; border: none; border-radius: 4px;">Sposta</button>';
echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '<script>
var allCategories = ' . json_encode(array_values($categories ?? [])) . ';

function showMoveDialog(categoryId, categoryName, courseId) {
    document.getElementById("moveDialogTitle").textContent = "Sposta domande da: " + categoryName;
    document.getElementById("moveCategoryid").value = categoryId;
    document.getElementById("moveCourseid").value = courseId;
    
    var select = document.getElementById("moveTarget");
    select.innerHTML = "";
    
    allCategories.forEach(function(cat) {
        if (cat.id != categoryId && cat.name === categoryName) {
            var option = document.createElement("option");
            option.value = cat.id;
            option.textContent = cat.name + " (ID: " + cat.id + ") - " + cat.questioncount + " domande";
            select.appendChild(option);
        }
    });
    
    document.getElementById("moveDialog").style.display = "block";
}

function hideMoveDialog() {
    document.getElementById("moveDialog").style.display = "none";
}
</script>';

echo $OUTPUT->footer();
