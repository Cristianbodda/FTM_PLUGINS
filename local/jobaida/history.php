<?php
/**
 * JobAIDA - Letter history page.
 *
 * Shows all generated letters for the current user, or all users if
 * the viewer holds the local/jobaida:viewall capability.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobaida/history.php'));
$PAGE->set_title(get_string('history', 'local_jobaida'));
$PAGE->set_heading(get_string('history', 'local_jobaida'));
$PAGE->set_pagelayout('standard');

// Capabilities.
$canviewall = has_capability('local/jobaida:viewall', $context) || is_siteadmin();
$canmanage  = has_capability('local/jobaida:authorize', $context) || is_siteadmin();

// Pagination parameters.
$page    = optional_param('page', 0, PARAM_INT);
$perpage = 20;

// Filter parameter (student name, only when viewall).
$filteruser = optional_param('filteruser', '', PARAM_TEXT);

// Sesskey for JS.
$sesskey = sesskey();

// ── Build query ──────────────────────────────────────────────────────────────
$params = [];
$where  = [];

if ($canviewall) {
    // All letters, optionally filtered by student name.
    $from = "{local_jobaida_letters} l
             JOIN {user} u ON u.id = l.userid";
    if ($filteruser !== '') {
        $fullname   = $DB->sql_fullname('u.firstname', 'u.lastname');
        $likename   = $DB->sql_like($fullname, ':fname', false);
        $where[]    = $likename;
        $params['fname'] = '%' . $DB->sql_like_escape($filteruser) . '%';
    }
} else {
    // Own letters only.
    $from = "{local_jobaida_letters} l
             JOIN {user} u ON u.id = l.userid";
    $where[]          = 'l.userid = :uid';
    $params['uid']    = $USER->id;
}

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countsql = "SELECT COUNT(l.id) FROM {$from} {$wheresql}";
$totalcount = $DB->count_records_sql($countsql, $params);

$sql = "SELECT l.id, l.userid, l.job_ad, l.attention, l.attention_rationale,
               l.interest, l.interest_rationale, l.desire, l.desire_rationale,
               l.action, l.action_rationale, l.full_letter, l.language,
               l.model_used, l.tokens_used, l.timecreated,
               u.firstname, u.lastname, u.email
          FROM {$from}
               {$wheresql}
      ORDER BY l.timecreated DESC";

$letters = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// ── Output ───────────────────────────────────────────────────────────────────

echo $OUTPUT->header();
?>

<style>
/* ========== JobAIDA History Styles ========== */
.jobaida-container {
    max-width: 1000px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.jobaida-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.jobaida-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a1a2e;
}
.jobaida-nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.jobaida-nav .btn {
    font-size: 0.85rem;
    padding: 6px 14px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}

/* Filter bar */
.jobaida-filter {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.jobaida-filter input[type="text"] {
    flex: 1;
    min-width: 200px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.9rem;
    font-family: inherit;
}
.jobaida-filter input[type="text"]:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
}
.jobaida-filter .btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Card / table */
.jobaida-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #dee2e6;
    margin-bottom: 20px;
    overflow: hidden;
}
.jobaida-card-header {
    padding: 14px 20px;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    color: #1a1a2e;
}
.jobaida-card-body {
    padding: 0;
}

/* History table */
.jobaida-table {
    width: 100%;
    border-collapse: collapse;
}
.jobaida-table th {
    background: #f8f9fa;
    padding: 10px 16px;
    text-align: left;
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}
.jobaida-table td {
    padding: 12px 16px;
    font-size: 0.9rem;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.jobaida-table tr:hover {
    background: #f8f9fa;
}
.jobaida-table .actions {
    white-space: nowrap;
}
.jobaida-table .actions .btn {
    padding: 4px 10px;
    font-size: 0.8rem;
    border-radius: 4px;
    font-weight: 500;
    margin-right: 4px;
    text-decoration: none;
}
.jobaida-table .job-preview {
    color: #495057;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Expanded detail */
.jobaida-detail {
    display: none;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}
.jobaida-detail.open {
    display: table-row;
}
.jobaida-detail-inner {
    padding: 20px;
}
.jobaida-detail h4 {
    margin: 0 0 6px 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a2e;
}
.jobaida-aida-section {
    margin-bottom: 16px;
    padding: 12px 16px;
    border-radius: 6px;
    border-left: 4px solid;
}
.jobaida-aida-section.aida-a { background: #fde8e8; border-color: #dc3545; }
.jobaida-aida-section.aida-i { background: #e8f0fe; border-color: #0066cc; }
.jobaida-aida-section.aida-d { background: #e8f5e9; border-color: #28a745; }
.jobaida-aida-section.aida-ac { background: #fff3e0; border-color: #f59e0b; }
.jobaida-aida-section .aida-label {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.jobaida-aida-section .aida-text {
    font-size: 0.9rem;
    line-height: 1.5;
    white-space: pre-wrap;
}
.jobaida-aida-section .aida-rationale {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 6px;
    font-style: italic;
}
.jobaida-full-letter {
    margin-top: 16px;
    padding: 16px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    white-space: pre-wrap;
    font-size: 0.9rem;
    line-height: 1.6;
}
.jobaida-full-letter h4 {
    margin-bottom: 10px;
}

/* Empty state */
.jobaida-empty {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}
.jobaida-empty .empty-icon {
    font-size: 48px;
    margin-bottom: 12px;
}
.jobaida-empty p {
    font-size: 1rem;
}

/* Stats badge */
.jobaida-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.jobaida-stat {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.jobaida-stat .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
    color: #0066cc;
}
.jobaida-stat .stat-label {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Pagination */
.jobaida-pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    gap: 4px;
}
.jobaida-pagination .btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
}
.jobaida-pagination .btn.active {
    background: #0066cc;
    color: #fff;
    border-color: #0066cc;
}

/* Toast notification */
.jobaida-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: none;
}
.jobaida-toast.success { background: #28a745; }
.jobaida-toast.error { background: #dc3545; }
</style>

<div class="jobaida-container">

    <!-- Header -->
    <div class="jobaida-header">
        <h2><?php echo get_string('history', 'local_jobaida'); ?></h2>
        <div class="jobaida-nav">
            <a href="<?php echo new moodle_url('/local/jobaida/index.php'); ?>"
               class="btn btn-primary">
                &#9664; <?php echo get_string('generator', 'local_jobaida'); ?>
            </a>
            <?php if ($canmanage): ?>
            <a href="<?php echo new moodle_url('/local/jobaida/manage_auth.php'); ?>"
               class="btn btn-outline-secondary">
                <?php echo get_string('manage_auth', 'local_jobaida'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="jobaida-stats">
        <div class="jobaida-stat">
            <span class="stat-number"><?php echo (int)$totalcount; ?></span>
            <span class="stat-label"><?php echo get_string('letters_generated', 'local_jobaida'); ?></span>
        </div>
    </div>

    <?php if ($canviewall): ?>
    <!-- Filter bar (coaches/admins) -->
    <form class="jobaida-filter" method="get" action="<?php echo new moodle_url('/local/jobaida/history.php'); ?>">
        <input type="text" name="filteruser"
               value="<?php echo s($filteruser); ?>"
               placeholder="<?php echo get_string('search_student', 'local_jobaida'); ?>">
        <button type="submit" class="btn btn-primary">Filtra</button>
        <?php if ($filteruser !== ''): ?>
        <a href="<?php echo new moodle_url('/local/jobaida/history.php'); ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if (empty($letters)): ?>
    <!-- Empty state -->
    <div class="jobaida-card">
        <div class="jobaida-card-body">
            <div class="jobaida-empty">
                <div class="empty-icon">&#128196;</div>
                <p><?php echo get_string('no_letters', 'local_jobaida'); ?></p>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Letters table -->
    <div class="jobaida-card">
        <div class="jobaida-card-header">
            <?php echo get_string('history_title', 'local_jobaida'); ?>
            <?php if ($totalcount > $perpage): ?>
                <span style="font-weight:400; font-size:0.85rem; color:#6c757d;">
                    (<?php echo ($page * $perpage + 1); ?>-<?php echo min(($page + 1) * $perpage, $totalcount); ?>
                     / <?php echo $totalcount; ?>)
                </span>
            <?php endif; ?>
        </div>
        <div class="jobaida-card-body">
            <table class="jobaida-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <?php if ($canviewall): ?>
                        <th>Studente</th>
                        <?php endif; ?>
                        <th>Annuncio</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($letters as $letter): ?>
                    <tr id="row-<?php echo (int)$letter->id; ?>">
                        <td><?php echo userdate($letter->timecreated, '%d/%m/%Y %H:%M'); ?></td>
                        <?php if ($canviewall): ?>
                        <td><?php echo s(fullname($letter)); ?></td>
                        <?php endif; ?>
                        <td class="job-preview" title="<?php echo s($letter->job_ad); ?>">
                            <?php echo s(substr($letter->job_ad, 0, 80)); ?>
                            <?php if (strlen($letter->job_ad) > 80) echo '...'; ?>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-view"
                                    data-letterid="<?php echo (int)$letter->id; ?>"
                                    onclick="toggleDetail(<?php echo (int)$letter->id; ?>)">
                                <?php echo get_string('view_letter', 'local_jobaida'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    onclick="deleteLetter(<?php echo (int)$letter->id; ?>)">
                                <?php echo get_string('delete_letter', 'local_jobaida'); ?>
                            </button>
                        </td>
                    </tr>
                    <!-- Expanded detail row -->
                    <tr class="jobaida-detail" id="detail-<?php echo (int)$letter->id; ?>">
                        <td colspan="<?php echo $canviewall ? 4 : 3; ?>">
                            <div class="jobaida-detail-inner">

                                <!-- AIDA Breakdown -->
                                <?php if (!empty($letter->attention)): ?>
                                <div class="jobaida-aida-section aida-a">
                                    <div class="aida-label">A - Attention</div>
                                    <div class="aida-text"><?php echo s($letter->attention); ?></div>
                                    <?php if (!empty($letter->attention_rationale)): ?>
                                    <div class="aida-rationale"><?php echo s($letter->attention_rationale); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($letter->interest)): ?>
                                <div class="jobaida-aida-section aida-i">
                                    <div class="aida-label">I - Interest</div>
                                    <div class="aida-text"><?php echo s($letter->interest); ?></div>
                                    <?php if (!empty($letter->interest_rationale)): ?>
                                    <div class="aida-rationale"><?php echo s($letter->interest_rationale); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($letter->desire)): ?>
                                <div class="jobaida-aida-section aida-d">
                                    <div class="aida-label">D - Desire</div>
                                    <div class="aida-text"><?php echo s($letter->desire); ?></div>
                                    <?php if (!empty($letter->desire_rationale)): ?>
                                    <div class="aida-rationale"><?php echo s($letter->desire_rationale); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($letter->action)): ?>
                                <div class="jobaida-aida-section aida-ac">
                                    <div class="aida-label">A - Action</div>
                                    <div class="aida-text"><?php echo s($letter->action); ?></div>
                                    <?php if (!empty($letter->action_rationale)): ?>
                                    <div class="aida-rationale"><?php echo s($letter->action_rationale); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Full letter -->
                                <?php if (!empty($letter->full_letter)): ?>
                                <div class="jobaida-full-letter">
                                    <h4><?php echo get_string('full_letter', 'local_jobaida'); ?></h4>
                                    <?php echo s($letter->full_letter); ?>
                                </div>
                                <?php endif; ?>

                                <!-- Meta info -->
                                <div style="margin-top:12px; font-size:0.8rem; color:#6c757d;">
                                    <?php if (!empty($letter->model_used)): ?>
                                        Model: <?php echo s($letter->model_used); ?> |
                                    <?php endif; ?>
                                    <?php if (!empty($letter->tokens_used)): ?>
                                        Tokens: <?php echo (int)$letter->tokens_used; ?> |
                                    <?php endif; ?>
                                    Lang: <?php echo s($letter->language); ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    // ── Pagination ───────────────────────────────────────────────────────
    if ($totalcount > $perpage):
        $totalpages = ceil($totalcount / $perpage);
    ?>
    <div class="jobaida-pagination">
        <?php for ($p = 0; $p < $totalpages; $p++):
            $url = new moodle_url('/local/jobaida/history.php', ['page' => $p]);
            if ($filteruser !== '') {
                $url->param('filteruser', $filteruser);
            }
        ?>
            <a href="<?php echo $url; ?>"
               class="btn btn-outline-secondary <?php echo ($p === $page) ? 'active' : ''; ?>">
                <?php echo $p + 1; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; // end if empty letters ?>

</div>

<!-- Toast -->
<div class="jobaida-toast" id="jobaida-toast"></div>

<script>
(function() {
    var sesskey = '<?php echo $sesskey; ?>';
    var ajaxUrl = '<?php echo (new moodle_url('/local/jobaida/ajax_auth.php'))->out(false); ?>';

    /**
     * Toggle inline detail for a letter.
     */
    window.toggleDetail = function(id) {
        var row = document.getElementById('detail-' + id);
        if (!row) return;
        var btn = document.querySelector('[data-letterid="' + id + '"]');
        if (row.classList.contains('open')) {
            row.classList.remove('open');
            if (btn) btn.textContent = '<?php echo get_string('view_letter', 'local_jobaida'); ?>';
        } else {
            row.classList.add('open');
            if (btn) btn.textContent = 'Chiudi';
        }
    };

    /**
     * Delete a letter with confirmation.
     */
    window.deleteLetter = function(id) {
        if (!confirm('<?php echo get_string('delete_confirm', 'local_jobaida'); ?>')) {
            return;
        }
        var formData = new FormData();
        formData.append('sesskey', sesskey);
        formData.append('action', 'delete_letter');
        formData.append('letterid', id);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    // Remove both rows.
                    var mainRow = document.getElementById('row-' + id);
                    var detailRow = document.getElementById('detail-' + id);
                    if (mainRow) mainRow.remove();
                    if (detailRow) detailRow.remove();
                    showToast(data.message || '<?php echo get_string('letter_deleted', 'local_jobaida'); ?>', 'success');
                } else {
                    showToast(data.message || 'Error', 'error');
                }
            })
            .catch(function(e) {
                showToast('Network error: ' + e.message, 'error');
            });
    };

    /**
     * Show toast notification.
     */
    function showToast(msg, type) {
        var toast = document.getElementById('jobaida-toast');
        toast.textContent = msg;
        toast.className = 'jobaida-toast ' + (type || 'success');
        toast.style.display = 'block';
        setTimeout(function() { toast.style.display = 'none'; }, 3000);
    }
})();
</script>

<?php
echo $OUTPUT->footer();
