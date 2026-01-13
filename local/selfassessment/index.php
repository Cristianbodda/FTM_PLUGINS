<?php
// ============================================
// Self Assessment - Dashboard Coach
// ============================================

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Richiede login
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/selfassessment/index.php'));
$PAGE->set_title(get_string('index_title', 'local_selfassessment'));
$PAGE->set_heading(get_string('index_title', 'local_selfassessment'));
$PAGE->set_pagelayout('report');

// Verifica permessi
require_capability('local/selfassessment:view', $context);

// Parametri
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

// Manager
$manager = new \local_selfassessment\manager();

// Statistiche
$stats = $manager->get_stats();

// Lista studenti
$students = $manager->get_students_with_status($filter, $search, $page, $perpage);
$total = $manager->count_students($filter, $search);

echo $OUTPUT->header();
?>

<style>
/* ============================================
   DASHBOARD COACH STYLES
   ============================================ */
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    background: linear-gradient(135deg, #2c3e50, #3498db);
    color: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;
}

.dashboard-header h1 {
    margin: 0 0 10px 0;
    font-size: 1.8em;
}

.dashboard-header p {
    margin: 0;
    opacity: 0.9;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card .icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.stat-card .value {
    font-size: 2.5em;
    font-weight: 700;
    color: #2c3e50;
}

.stat-card .label {
    color: #7f8c8d;
    font-size: 0.9em;
    text-transform: uppercase;
}

.stat-card.completed { border-left: 4px solid #28a745; }
.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.disabled { border-left: 4px solid #dc3545; }

/* Filters */
.filters-bar {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 8px 16px;
    border: 2px solid #e9ecef;
    background: white;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9em;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    border-color: #3498db;
}

.filter-btn.active {
    background: #3498db;
    border-color: #3498db;
    color: white;
}

.search-box {
    flex: 1;
    min-width: 200px;
}

.search-box input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95em;
}

.search-box input:focus {
    outline: none;
    border-color: #3498db;
}

/* Students Table */
.students-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.students-table th {
    background: #f8f9fa;
    padding: 15px 20px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
}

.students-table td {
    padding: 15px 20px;
    border-bottom: 1px solid #f1f1f1;
    vertical-align: middle;
}

.students-table tr:hover {
    background: #f8f9fa;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.student-name {
    font-weight: 600;
    color: #2c3e50;
}

.student-email {
    font-size: 0.85em;
    color: #7f8c8d;
}

/* Status Badge */
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.disabled {
    background: #f8d7da;
    color: #721c24;
}

/* Actions */
.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85em;
    margin-right: 5px;
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.action-btn.view { background: #e3f2fd; color: #1976d2; }
.action-btn.disable { background: #ffebee; color: #c62828; }
.action-btn.enable { background: #e8f5e9; color: #2e7d32; }
.action-btn.reminder { background: #fff3e0; color: #ef6c00; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    padding: 20px;
}

.pagination a, .pagination span {
    padding: 8px 14px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
}

.pagination a:hover {
    background: #f8f9fa;
}

.pagination .current {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state .icon {
    font-size: 4em;
    margin-bottom: 20px;
}

/* Notification */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    background: #28a745;
    color: white;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    z-index: 9999;
    display: none;
}
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <h1>📊 <?php echo get_string('index_title', 'local_selfassessment'); ?></h1>
        <p>Gestisci le autovalutazioni degli studenti, visualizza lo stato e invia reminder.</p>
        <div style="margin-top: 15px;">
            <a href="assign.php" style="display: inline-block; padding: 10px 20px; background: rgba(255,255,255,0.2); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                📋 Assegna Autovalutazioni
            </a>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">👥</div>
            <div class="value"><?php echo $stats['total']; ?></div>
            <div class="label"><?php echo get_string('total_students', 'local_selfassessment'); ?></div>
        </div>
        <div class="stat-card completed">
            <div class="icon">✅</div>
            <div class="value"><?php echo $stats['completed']; ?></div>
            <div class="label"><?php echo get_string('completed_count', 'local_selfassessment'); ?></div>
        </div>
        <div class="stat-card pending">
            <div class="icon">⏳</div>
            <div class="value"><?php echo $stats['pending']; ?></div>
            <div class="label"><?php echo get_string('pending_count', 'local_selfassessment'); ?></div>
        </div>
        <div class="stat-card disabled">
            <div class="icon">🚫</div>
            <div class="value"><?php echo $stats['disabled']; ?></div>
            <div class="label"><?php echo get_string('disabled_count', 'local_selfassessment'); ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <a href="?filter=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                <?php echo get_string('filter_all', 'local_selfassessment'); ?>
            </a>
            <a href="?filter=completed&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                ✅ <?php echo get_string('filter_completed', 'local_selfassessment'); ?>
            </a>
            <a href="?filter=pending&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                ⏳ <?php echo get_string('filter_pending', 'local_selfassessment'); ?>
            </a>
            <a href="?filter=disabled&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter == 'disabled' ? 'active' : ''; ?>">
                🚫 <?php echo get_string('filter_disabled', 'local_selfassessment'); ?>
            </a>
        </div>
        <div class="search-box">
            <form method="get">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="🔍 Cerca studente..." onchange="this.form.submit()">
            </form>
        </div>
    </div>
    
    <!-- Students Table -->
    <div class="students-card">
        <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p><?php echo get_string('no_students', 'local_selfassessment'); ?></p>
        </div>
        <?php else: ?>
        <table class="students-table">
            <thead>
                <tr>
                    <th><?php echo get_string('student', 'local_selfassessment'); ?></th>
                    <th><?php echo get_string('status', 'local_selfassessment'); ?></th>
                    <th><?php echo get_string('completed_date', 'local_selfassessment'); ?></th>
                    <th><?php echo get_string('actions', 'local_selfassessment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): 
                    $initials = strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1));
                    $is_completed = $student->assessment_count > 0;
                    $is_enabled = $student->enabled == 1;
                    
                    if (!$is_enabled) {
                        $status_class = 'disabled';
                        $status_text = get_string('status_disabled', 'local_selfassessment');
                    } elseif ($is_completed) {
                        $status_class = 'completed';
                        $status_text = get_string('status_completed', 'local_selfassessment');
                    } else {
                        $status_class = 'pending';
                        $status_text = get_string('status_pending', 'local_selfassessment');
                    }
                ?>
                <tr>
                    <td>
                        <div class="student-info">
                            <div class="student-avatar"><?php echo $initials; ?></div>
                            <div>
                                <div class="student-name"><?php echo fullname($student); ?></div>
                                <div class="student-email"><?php echo $student->email; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </td>
                    <td>
                        <?php if ($student->last_assessment): ?>
                            <?php echo date('d/m/Y H:i', $student->last_assessment); ?>
                        <?php else: ?>
                            <span style="color: #aaa;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_completed): ?>
                        <a href="/local/coachmanager/reports_v2.php?studentid=<?php echo $student->id; ?>" class="action-btn view" title="<?php echo get_string('view_detail', 'local_selfassessment'); ?>">
                            👁️ Vedi
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($is_enabled): ?>
                        <button class="action-btn disable" onclick="toggleStatus(<?php echo $student->id; ?>, 0)" title="<?php echo get_string('disable', 'local_selfassessment'); ?>">
                            🔕 Disabilita
                        </button>
                        <?php if (!$is_completed): ?>
                        <button class="action-btn reminder" onclick="sendReminder(<?php echo $student->id; ?>)" title="<?php echo get_string('send_reminder', 'local_selfassessment'); ?>">
                            📧 Reminder
                        </button>
                        <?php endif; ?>
                        <?php else: ?>
                        <button class="action-btn enable" onclick="toggleStatus(<?php echo $student->id; ?>, 1)" title="<?php echo get_string('enable', 'local_selfassessment'); ?>">
                            🔔 Riabilita
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total > $perpage): ?>
        <div class="pagination">
            <?php
            $totalpages = ceil($total / $perpage);
            for ($i = 0; $i < $totalpages; $i++):
                $pageurl = "?filter=$filter&search=" . urlencode($search) . "&page=$i";
            ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i + 1; ?></span>
            <?php else: ?>
                <a href="<?php echo $pageurl; ?>"><?php echo $i + 1; ?></a>
            <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<!-- Notification -->
<div class="notification" id="notification"></div>

<script>
// Toggle student status
function toggleStatus(userid, enable) {
    if (!confirm(enable ? '<?php echo get_string('confirm_enable', 'local_selfassessment'); ?>' : '<?php echo get_string('confirm_disable', 'local_selfassessment'); ?>')) {
        return;
    }
    
    fetch('ajax_toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({userid: userid, enable: enable})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ <?php echo get_string('status_changed', 'local_selfassessment'); ?>');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('❌ ' + (data.error || 'Errore'), 'error');
        }
    });
}

// Send reminder
function sendReminder(userid) {
    fetch('ajax_toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({userid: userid, action: 'reminder'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ Reminder inviato!');
        } else {
            showNotification('❌ ' + (data.error || 'Errore'), 'error');
        }
    });
}

// Show notification
function showNotification(message, type) {
    const notif = document.getElementById('notification');
    notif.textContent = message;
    notif.style.background = type === 'error' ? '#dc3545' : '#28a745';
    notif.style.display = 'block';
    setTimeout(() => notif.style.display = 'none', 3000);
}
</script>

<?php
echo $OUTPUT->footer();
