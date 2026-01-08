<?php
// ============================================
// CoachManager - Index (Lista Studenti)
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');

require_login();
$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

// Setup pagina
$PAGE->set_url(new moodle_url('/local/coachmanager/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coachmanager', 'local_coachmanager'));
$PAGE->set_heading(get_string('coachmanager', 'local_coachmanager'));
$PAGE->set_pagelayout('report');

// Parametri filtro
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

// Query studenti con quiz completati
$sql_count = "SELECT COUNT(DISTINCT u.id) 
              FROM {user} u
              JOIN {quiz_attempts} qa ON qa.userid = u.id
              WHERE qa.state = 'finished'";

$sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               (SELECT COUNT(*) FROM {quiz_attempts} qa2 WHERE qa2.userid = u.id AND qa2.state = 'finished') as quiz_count,
               (SELECT MAX(qa3.timefinish) FROM {quiz_attempts} qa3 WHERE qa3.userid = u.id) as last_quiz
        FROM {user} u
        JOIN {quiz_attempts} qa ON qa.userid = u.id
        WHERE qa.state = 'finished'";

$params = [];

if ($search) {
    $sql .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
    $sql_count .= " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
    $searchparam = '%' . $search . '%';
    $params = [$searchparam, $searchparam, $searchparam];
}

$sql .= " ORDER BY u.lastname, u.firstname";

$total = $DB->count_records_sql($sql_count, $params);
$students = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

echo $OUTPUT->header();
?>

<style>
.coachmanager-index {
    max-width: 1200px;
    margin: 0 auto;
}

.search-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1em;
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: #3498db;
}

.search-box button {
    padding: 12px 25px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
}

.search-box button:hover {
    background: linear-gradient(135deg, #2980b9, #1f6dad);
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.student-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.2s;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    text-decoration: none;
    color: inherit;
}

.student-card .avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3498db, #9b59b6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
    font-weight: 600;
    margin-bottom: 15px;
}

.student-card h3 {
    margin: 0 0 5px 0;
    font-size: 1.1em;
    color: #2c3e50;
}

.student-card .email {
    color: #7f8c8d;
    font-size: 0.9em;
    margin-bottom: 15px;
}

.student-card .stats {
    display: flex;
    gap: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.student-card .stat {
    text-align: center;
    flex: 1;
}

.student-card .stat-value {
    font-size: 1.3em;
    font-weight: 700;
    color: #3498db;
}

.student-card .stat-label {
    font-size: 0.75em;
    color: #7f8c8d;
    text-transform: uppercase;
}

.pagination-info {
    text-align: center;
    margin-top: 20px;
    color: #7f8c8d;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: #7f8c8d;
}

.no-results .icon {
    font-size: 3em;
    margin-bottom: 15px;
}
</style>

<div class="coachmanager-index">
    
    <div class="search-box">
        <form method="get" action="" style="display: flex; gap: 15px; flex: 1; flex-wrap: wrap;">
            <input type="text" name="search" placeholder="🔍 Cerca studente per nome o email..." value="<?php echo s($search); ?>">
            <button type="submit">Cerca</button>
            <?php if ($search): ?>
            <a href="index.php" style="padding: 12px 20px; background: #6c757d; color: white; border-radius: 8px; text-decoration: none;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (empty($students)): ?>
    <div class="no-results">
        <div class="icon">📭</div>
        <h3>Nessuno studente trovato</h3>
        <p>Non ci sono studenti con quiz completati<?php echo $search ? ' che corrispondono alla ricerca' : ''; ?>.</p>
    </div>
    <?php else: ?>
    
    <div class="students-grid">
        <?php foreach ($students as $student): 
            $initials = strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1));
            $last_quiz_date = $student->last_quiz ? userdate($student->last_quiz, '%d/%m/%Y') : '-';
        ?>
        <a href="reports_v2.php?studentid=<?php echo $student->id; ?>" class="student-card">
            <div class="avatar"><?php echo $initials; ?></div>
            <h3><?php echo fullname($student); ?></h3>
            <div class="email"><?php echo $student->email; ?></div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?php echo $student->quiz_count; ?></div>
                    <div class="stat-label">Quiz</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $last_quiz_date; ?></div>
                    <div class="stat-label">Ultimo quiz</div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="pagination-info">
        <?php 
        $start = $page * $perpage + 1;
        $end = min(($page + 1) * $perpage, $total);
        echo "Mostrando $start-$end di $total studenti";
        ?>
    </div>
    
    <?php 
    // Paginazione
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/coachmanager/index.php', ['search' => $search]));
    ?>
    
    <?php endif; ?>
    
</div>

<?php
echo $OUTPUT->footer();
?>
