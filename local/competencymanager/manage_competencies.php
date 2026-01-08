<?php
/**
 * Gestione Competenze Domande - Vista e modifica assegnazioni
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$assignmentid = optional_param('assignmentid', 0, PARAM_INT);
$newlevel = optional_param('newlevel', 0, PARAM_INT);
$filtercomp = optional_param('filtercomp', '', PARAM_TEXT);
$filterquiz = optional_param('filterquiz', 0, PARAM_INT);
$filterlevel = optional_param('filterlevel', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/manage_competencies.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Gestione Competenze Domande');
$PAGE->set_heading('Gestione Competenze Domande');

// AZIONI AJAX
if ($action === 'updatelevel' && $assignmentid > 0 && $newlevel > 0) {
    require_sesskey();
    $DB->set_field('qbank_competenciesbyquestion', 'difficultylevel', $newlevel, ['id' => $assignmentid]);
    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        echo json_encode(['success' => true, 'message' => 'Livello aggiornato']);
        exit;
    }
    redirect(new moodle_url('/local/competencymanager/manage_competencies.php', [
        'courseid' => $courseid, 
        'filtercomp' => $filtercomp,
        'filterquiz' => $filterquiz,
        'filterlevel' => $filterlevel,
        'search' => $search
    ]));
}

if ($action === 'remove' && $assignmentid > 0) {
    require_sesskey();
    $DB->delete_records('qbank_competenciesbyquestion', ['id' => $assignmentid]);
    redirect(new moodle_url('/local/competencymanager/manage_competencies.php', [
        'courseid' => $courseid,
        'filtercomp' => $filtercomp,
        'filterquiz' => $filterquiz,
        'filterlevel' => $filterlevel,
        'search' => $search
    ]), 'Assegnazione rimossa', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bulk remove
if ($action === 'bulkremove') {
    require_sesskey();
    $ids = optional_param_array('selected', [], PARAM_INT);
    if (!empty($ids)) {
        list($insql, $params) = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('qbank_competenciesbyquestion', "id $insql", $params);
        redirect(new moodle_url('/local/competencymanager/manage_competencies.php', ['courseid' => $courseid]), 
            count($ids) . ' assegnazioni rimosse', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
?>

<style>
.manage-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.manage-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
.manage-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.manage-card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px; }
.stat-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; }
.stat-box .number { font-size: 28px; font-weight: 700; }
.stat-box .label { font-size: 11px; color: #666; margin-top: 5px; }
.stat-box.primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.stat-box.primary .label { color: rgba(255,255,255,0.8); }
.stat-box.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.stat-box.success .number { color: #155724; }
.stat-box.info { background: linear-gradient(135deg, #cce5ff, #b8daff); }
.stat-box.info .number { color: #004085; }
.filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
.filter-bar .filter-group { display: flex; flex-direction: column; }
.filter-bar label { font-size: 12px; color: #666; margin-bottom: 3px; }
.filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
.filter-bar select { min-width: 180px; }
.filter-bar input[type="text"] { min-width: 200px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 10px 8px; border: 1px solid #e0e0e0; text-align: left; }
th { background: #34495e; color: white; font-weight: 500; position: sticky; top: 0; }
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #f0f7ff; }
.comp-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background: #e8f4f8; color: #2980b9; }
.quiz-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; background: #f0f0f0; color: #666; margin: 1px; }
.level-select { padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; cursor: pointer; }
.level-select:focus { border-color: #667eea; outline: none; }
.btn { display: inline-block; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; cursor: pointer; border: none; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-sm { padding: 4px 8px; font-size: 11px; }
.checkbox-col { width: 30px; text-align: center; }
.actions-col { width: 80px; text-align: center; }
.pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
.pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
.pagination a:hover { background: #f0f0f0; }
.pagination .current { background: #667eea; color: white; border-color: #667eea; }
.bulk-actions { background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: none; }
.bulk-actions.show { display: block; }
</style>

<div class="manage-container">

<div class="manage-header">
    <h2>🎯 Gestione Competenze Domande</h2>
    <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
</div>

<?php
// Statistiche
$stats = $DB->get_record_sql("
    SELECT 
        COUNT(DISTINCT qcbq.id) as total_assignments,
        COUNT(DISTINCT qcbq.questionid) as total_questions,
        COUNT(DISTINCT qcbq.competencyid) as total_competencies,
        COUNT(DISTINCT CASE WHEN qcbq.difficultylevel = 1 THEN qcbq.id END) as level1,
        COUNT(DISTINCT CASE WHEN qcbq.difficultylevel = 2 THEN qcbq.id END) as level2,
        COUNT(DISTINCT CASE WHEN qcbq.difficultylevel = 3 THEN qcbq.id END) as level3
    FROM {qbank_competenciesbyquestion} qcbq
    JOIN {question} q ON q.id = qcbq.questionid
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
", [$context->id]);
?>

<div class="manage-card">
    <div class="stats-grid">
        <div class="stat-box primary">
            <div class="number"><?php echo $stats->total_assignments ?: 0; ?></div>
            <div class="label">Assegnazioni Totali</div>
        </div>
        <div class="stat-box info">
            <div class="number"><?php echo $stats->total_questions ?: 0; ?></div>
            <div class="label">Domande con Competenza</div>
        </div>
        <div class="stat-box success">
            <div class="number"><?php echo $stats->total_competencies ?: 0; ?></div>
            <div class="label">Competenze Utilizzate</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php echo ($stats->level1 ?: 0); ?> / <?php echo ($stats->level2 ?: 0); ?> / <?php echo ($stats->level3 ?: 0); ?></div>
            <div class="label">⭐ / ⭐⭐ / ⭐⭐⭐</div>
        </div>
        <div class="stat-box">
            <div class="number"><?php 
                $quizCount = $DB->count_records('quiz', ['course' => $courseid]);
                echo $quizCount;
            ?></div>
            <div class="label">Quiz nel Corso</div>
        </div>
    </div>
</div>

<?php
// Carica dati per i filtri
$competencies = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.idnumber, c.shortname
    FROM {qbank_competenciesbyquestion} qcbq
    JOIN {competency} c ON c.id = qcbq.competencyid
    JOIN {question} q ON q.id = qcbq.questionid
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    WHERE qc.contextid = ?
    ORDER BY c.idnumber
", [$context->id]);

$quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name');
?>

<div class="manage-card">
    <h3>🔍 Filtri e Ricerca</h3>
    
    <form method="get" action="">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
        
        <div class="filter-bar">
            <div class="filter-group">
                <label>Competenza</label>
                <select name="filtercomp">
                    <option value="">-- Tutte --</option>
                    <?php foreach ($competencies as $c): ?>
                    <option value="<?php echo $c->idnumber; ?>" <?php echo $filtercomp == $c->idnumber ? 'selected' : ''; ?>>
                        <?php echo $c->idnumber; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Quiz</label>
                <select name="filterquiz">
                    <option value="">-- Tutti --</option>
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?php echo $q->id; ?>" <?php echo $filterquiz == $q->id ? 'selected' : ''; ?>>
                        <?php echo format_string(substr($q->name, 0, 40)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Livello</label>
                <select name="filterlevel">
                    <option value="">-- Tutti --</option>
                    <option value="1" <?php echo $filterlevel == 1 ? 'selected' : ''; ?>>⭐ Base</option>
                    <option value="2" <?php echo $filterlevel == 2 ? 'selected' : ''; ?>>⭐⭐ Intermedio</option>
                    <option value="3" <?php echo $filterlevel == 3 ? 'selected' : ''; ?>>⭐⭐⭐ Avanzato</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Cerca domanda</label>
                <input type="text" name="search" value="<?php echo s($search); ?>" placeholder="Nome domanda...">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">🔍 Filtra</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">✖ Reset</a>
            </div>
        </div>
    </form>
</div>

<?php
// Query principale
$params = ['contextid' => $context->id];
$where = "WHERE qc.contextid = :contextid";

if ($filtercomp) {
    $where .= " AND c.idnumber = :filtercomp";
    $params['filtercomp'] = $filtercomp;
}

if ($filterlevel) {
    $where .= " AND qcbq.difficultylevel = :filterlevel";
    $params['filterlevel'] = $filterlevel;
}

if ($search) {
    $where .= " AND " . $DB->sql_like('q.name', ':search', false);
    $params['search'] = '%' . $search . '%';
}

// Se filtro quiz, join con quiz_slots
$quizJoin = "";
$quizWhere = "";
if ($filterquiz) {
    $quizJoin = "
        JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
        JOIN {quiz_slots} qs ON qs.id = qr.itemid AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    ";
    $quizWhere = " AND qs.quizid = :filterquiz";
    $params['filterquiz'] = $filterquiz;
}

// Conta totale
$countSql = "
    SELECT COUNT(DISTINCT qcbq.id)
    FROM {qbank_competenciesbyquestion} qcbq
    JOIN {question} q ON q.id = qcbq.questionid
    JOIN {competency} c ON c.id = qcbq.competencyid
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    $quizJoin
    $where $quizWhere
";
$totalCount = $DB->count_records_sql($countSql, $params);

// Query dati
$sql = "
    SELECT DISTINCT
        qcbq.id as assignmentid,
        qcbq.questionid,
        qcbq.competencyid,
        qcbq.difficultylevel,
        q.name as questionname,
        c.idnumber as comp_code,
        c.shortname as comp_name,
        cf.shortname as framework_name
    FROM {qbank_competenciesbyquestion} qcbq
    JOIN {question} q ON q.id = qcbq.questionid
    JOIN {competency} c ON c.id = qcbq.competencyid
    JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
    $quizJoin
    $where $quizWhere
    ORDER BY c.idnumber, q.name
";

$assignments = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Per ogni domanda, trova in quali quiz è inserita
$questionQuizzes = [];
foreach ($assignments as $a) {
    if (!isset($questionQuizzes[$a->questionid])) {
        $qQuizzes = $DB->get_records_sql("
            SELECT DISTINCT quiz.id, quiz.name
            FROM {quiz} quiz
            JOIN {quiz_slots} qs ON qs.quizid = quiz.id
            JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            WHERE qv.questionid = ?
            AND quiz.course = ?
        ", [$a->questionid, $courseid]);
        $questionQuizzes[$a->questionid] = $qQuizzes;
    }
}
?>

<div class="manage-card">
    <h3>📋 Assegnazioni Domande-Competenze (<?php echo $totalCount; ?> risultati)</h3>
    
    <?php if (empty($assignments)): ?>
    <p style="text-align: center; padding: 40px; color: #666;">
        <em>Nessuna assegnazione trovata con i filtri selezionati.</em>
    </p>
    <?php else: ?>
    
    <form method="post" action="?courseid=<?php echo $courseid; ?>&action=bulkremove&sesskey=<?php echo sesskey(); ?>" id="bulkForm">
        
        <div class="bulk-actions" id="bulkActions">
            <strong>Azioni di massa:</strong>
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Rimuovere le assegnazioni selezionate?');">
                🗑️ Rimuovi selezionate
            </button>
            <span id="selectedCount" style="margin-left: 15px;">0 selezionate</span>
        </div>
        
        <div style="max-height: 600px; overflow-y: auto;">
        <table>
            <thead>
                <tr>
                    <th class="checkbox-col">
                        <input type="checkbox" id="selectAll" title="Seleziona tutti">
                    </th>
                    <th>ID</th>
                    <th>Domanda</th>
                    <th>Competenza</th>
                    <th>Framework</th>
                    <th>Quiz</th>
                    <th>Livello</th>
                    <th class="actions-col">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td class="checkbox-col">
                        <input type="checkbox" name="selected[]" value="<?php echo $a->assignmentid; ?>" class="row-checkbox">
                    </td>
                    <td><?php echo $a->questionid; ?></td>
                    <td title="<?php echo htmlspecialchars($a->questionname); ?>">
                        <?php echo format_string(substr($a->questionname, 0, 45)); ?>
                        <?php echo strlen($a->questionname) > 45 ? '...' : ''; ?>
                    </td>
                    <td>
                        <span class="comp-badge"><?php echo $a->comp_code; ?></span>
                    </td>
                    <td><?php echo $a->framework_name; ?></td>
                    <td>
                        <?php 
                        $qQuizzes = $questionQuizzes[$a->questionid] ?? [];
                        if (empty($qQuizzes)) {
                            echo '<em style="color:#999;font-size:11px;">Nessun quiz</em>';
                        } else {
                            foreach ($qQuizzes as $qz) {
                                echo '<span class="quiz-badge" title="' . htmlspecialchars($qz->name) . '">';
                                echo format_string(substr($qz->name, 0, 20));
                                echo '</span> ';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <select class="level-select" 
                                data-assignmentid="<?php echo $a->assignmentid; ?>"
                                onchange="updateLevel(this)">
                            <option value="1" <?php echo $a->difficultylevel == 1 ? 'selected' : ''; ?>>⭐</option>
                            <option value="2" <?php echo $a->difficultylevel == 2 ? 'selected' : ''; ?>>⭐⭐</option>
                            <option value="3" <?php echo $a->difficultylevel == 3 ? 'selected' : ''; ?>>⭐⭐⭐</option>
                        </select>
                    </td>
                    <td class="actions-col">
                        <a href="?courseid=<?php echo $courseid; ?>&action=remove&assignmentid=<?php echo $a->assignmentid; ?>&sesskey=<?php echo sesskey(); ?>&filtercomp=<?php echo urlencode($filtercomp); ?>&filterquiz=<?php echo $filterquiz; ?>&filterlevel=<?php echo $filterlevel; ?>&search=<?php echo urlencode($search); ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Rimuovere questa assegnazione?');"
                           title="Rimuovi">
                            🗑️
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </form>
    
    <?php
    // Paginazione
    if ($totalCount > $perpage):
        $totalPages = ceil($totalCount / $perpage);
    ?>
    <div class="pagination">
        <?php if ($page > 0): ?>
        <a href="?courseid=<?php echo $courseid; ?>&page=<?php echo $page - 1; ?>&filtercomp=<?php echo urlencode($filtercomp); ?>&filterquiz=<?php echo $filterquiz; ?>&filterlevel=<?php echo $filterlevel; ?>&search=<?php echo urlencode($search); ?>">← Prec</a>
        <?php endif; ?>
        
        <?php for ($i = max(0, $page - 2); $i < min($totalPages, $page + 3); $i++): ?>
            <?php if ($i == $page): ?>
            <span class="current"><?php echo $i + 1; ?></span>
            <?php else: ?>
            <a href="?courseid=<?php echo $courseid; ?>&page=<?php echo $i; ?>&filtercomp=<?php echo urlencode($filtercomp); ?>&filterquiz=<?php echo $filterquiz; ?>&filterlevel=<?php echo $filterlevel; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i + 1; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages - 1): ?>
        <a href="?courseid=<?php echo $courseid; ?>&page=<?php echo $page + 1; ?>&filtercomp=<?php echo urlencode($filtercomp); ?>&filterquiz=<?php echo $filterquiz; ?>&filterlevel=<?php echo $filterlevel; ?>&search=<?php echo urlencode($search); ?>">Succ →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<p style="margin-top: 20px;">
    <a href="assign_competencies.php?courseid=<?php echo $courseid; ?>" class="btn btn-success">➕ Assegna Competenze</a>
    <a href="question_check.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">📋 Verifica Domande</a>
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">🏠 Dashboard</a>
</p>

</div>

<script>
// Aggiorna livello via redirect (semplice, senza AJAX)
function updateLevel(select) {
    var assignmentId = select.dataset.assignmentid;
    var newLevel = select.value;
    var url = '?courseid=<?php echo $courseid; ?>&action=updatelevel&assignmentid=' + assignmentId + 
              '&newlevel=' + newLevel + '&sesskey=<?php echo sesskey(); ?>' +
              '&filtercomp=<?php echo urlencode($filtercomp); ?>&filterquiz=<?php echo $filterquiz; ?>' +
              '&filterlevel=<?php echo $filterlevel; ?>&search=<?php echo urlencode($search); ?>';
    window.location.href = url;
}

// Selezione multipla
document.getElementById('selectAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = this.checked;
    }.bind(this));
    updateBulkActions();
});

document.querySelectorAll('.row-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    var checked = document.querySelectorAll('.row-checkbox:checked').length;
    var bulkActions = document.getElementById('bulkActions');
    var selectedCount = document.getElementById('selectedCount');
    
    if (checked > 0) {
        bulkActions.classList.add('show');
        selectedCount.textContent = checked + ' selezionate';
    } else {
        bulkActions.classList.remove('show');
    }
}
</script>

<?php
echo $OUTPUT->footer();
