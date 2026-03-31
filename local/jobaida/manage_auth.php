<?php
/**
 * JobAIDA - Authorization management page.
 *
 * Coaches and admins can authorize / revoke student access to the generator.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/jobaida:authorize', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jobaida/manage_auth.php'));
$PAGE->set_title(get_string('manage_auth', 'local_jobaida'));
$PAGE->set_heading(get_string('manage_auth', 'local_jobaida'));
$PAGE->set_pagelayout('standard');

// Sesskey for JS.
$sesskey = sesskey();

// ── Load currently authorized students ──────────────────────────────────────
$sql = "SELECT a.id, a.userid, a.authorizedby, a.timecreated, a.timemodified,
               u.firstname, u.lastname, u.email,
               coach.firstname AS coach_firstname, coach.lastname AS coach_lastname
          FROM {local_jobaida_auth} a
          JOIN {user} u ON u.id = a.userid AND u.deleted = 0
          JOIN {user} coach ON coach.id = a.authorizedby AND coach.deleted = 0
         WHERE a.active = 1
      ORDER BY u.lastname, u.firstname";

$authorized = $DB->get_records_sql($sql);

echo $OUTPUT->header();
?>

<style>
/* ========== JobAIDA Manage Auth Styles ========== */
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

/* Cards */
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.jobaida-card-body {
    padding: 20px;
}

/* Search box */
.jobaida-search-box {
    position: relative;
    margin-bottom: 0;
}
.jobaida-search-box input {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 0.9rem;
    font-family: inherit;
    box-sizing: border-box;
}
.jobaida-search-box input:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0,102,204,0.15);
}
.jobaida-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 6px 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-height: 300px;
    overflow-y: auto;
    z-index: 100;
    display: none;
}
.jobaida-search-results.visible {
    display: block;
}
.jobaida-search-item {
    padding: 10px 14px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
}
.jobaida-search-item:hover {
    background: #f0f4ff;
}
.jobaida-search-item:last-child {
    border-bottom: none;
}
.jobaida-search-item .user-info {
    display: flex;
    flex-direction: column;
}
.jobaida-search-item .user-name {
    font-weight: 600;
    color: #1a1a2e;
}
.jobaida-search-item .user-email {
    font-size: 0.8rem;
    color: #6c757d;
}
.jobaida-search-item .btn-authorize {
    background: #28a745;
    color: #fff;
    border: none;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
}
.jobaida-search-item .btn-authorize:hover {
    background: #218838;
}
.jobaida-search-no-results {
    padding: 12px 14px;
    color: #6c757d;
    font-size: 0.85rem;
    text-align: center;
}

/* Table */
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
.jobaida-table .btn-revoke {
    background: none;
    color: #dc3545;
    border: 1px solid #dc3545;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
}
.jobaida-table .btn-revoke:hover {
    background: #dc3545;
    color: #fff;
}

/* Empty state */
.jobaida-empty {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}
.jobaida-empty .empty-icon {
    font-size: 40px;
    margin-bottom: 10px;
}
.jobaida-empty p {
    font-size: 0.95rem;
    margin: 0;
}

/* Badge count */
.jobaida-badge {
    background: #0066cc;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
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
        <h2><?php echo get_string('manage_auth', 'local_jobaida'); ?></h2>
        <div class="jobaida-nav">
            <a href="<?php echo new moodle_url('/local/jobaida/index.php'); ?>"
               class="btn btn-primary">
                &#9664; <?php echo get_string('generator', 'local_jobaida'); ?>
            </a>
            <a href="<?php echo new moodle_url('/local/jobaida/history.php'); ?>"
               class="btn btn-outline-secondary">
                <?php echo get_string('history', 'local_jobaida'); ?>
            </a>
        </div>
    </div>

    <!-- Search / Add Student Card -->
    <div class="jobaida-card">
        <div class="jobaida-card-header">
            <?php echo get_string('authorize_student', 'local_jobaida'); ?>
        </div>
        <div class="jobaida-card-body">
            <div class="jobaida-search-box" id="search-box">
                <input type="text" id="search-input"
                       placeholder="<?php echo get_string('search_student', 'local_jobaida'); ?>"
                       autocomplete="off">
                <div class="jobaida-search-results" id="search-results"></div>
            </div>
        </div>
    </div>

    <!-- Authorized Students Table -->
    <div class="jobaida-card">
        <div class="jobaida-card-header">
            <?php echo get_string('authorized_students', 'local_jobaida'); ?>
            <span class="jobaida-badge" id="auth-count"><?php echo count($authorized); ?></span>
        </div>
        <div class="jobaida-card-body" style="padding:0;">
            <?php if (empty($authorized)): ?>
            <div class="jobaida-empty" id="empty-state">
                <div class="empty-icon">&#128100;</div>
                <p>Nessuno studente autorizzato.</p>
            </div>
            <?php endif; ?>

            <table class="jobaida-table" id="auth-table"
                   style="<?php echo empty($authorized) ? 'display:none;' : ''; ?>">
                <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Email</th>
                        <th>Autorizzato da</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody id="auth-tbody">
                    <?php foreach ($authorized as $auth): ?>
                    <tr id="auth-row-<?php echo (int)$auth->userid; ?>">
                        <td><?php echo s(fullname($auth)); ?></td>
                        <td><?php echo s($auth->email); ?></td>
                        <td>
                            <?php
                            $coachobj = new stdClass();
                            $coachobj->firstname = $auth->coach_firstname;
                            $coachobj->lastname = $auth->coach_lastname;
                            echo s(fullname($coachobj));
                            ?>
                        </td>
                        <td><?php echo userdate($auth->timecreated, '%d/%m/%Y %H:%M'); ?></td>
                        <td>
                            <button type="button" class="btn-revoke"
                                    onclick="revokeAuth(<?php echo (int)$auth->userid; ?>, this)">
                                <?php echo get_string('revoke_student', 'local_jobaida'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Toast -->
<div class="jobaida-toast" id="jobaida-toast"></div>

<script>
(function() {
    var sesskey = '<?php echo $sesskey; ?>';
    var ajaxUrl = '<?php echo (new moodle_url('/local/jobaida/ajax_auth.php'))->out(false); ?>';
    var searchInput = document.getElementById('search-input');
    var searchResults = document.getElementById('search-results');
    var searchTimer = null;

    // ── Live search ──────────────────────────────────────────────────────
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        if (q.length < 2) {
            searchResults.classList.remove('visible');
            searchResults.innerHTML = '';
            return;
        }
        searchTimer = setTimeout(function() { doSearch(q); }, 300);
    });

    // Close dropdown on outside click.
    document.addEventListener('click', function(e) {
        if (!document.getElementById('search-box').contains(e.target)) {
            searchResults.classList.remove('visible');
        }
    });

    function doSearch(query) {
        var formData = new FormData();
        formData.append('sesskey', sesskey);
        formData.append('action', 'search');
        formData.append('query', query);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    showToast(data.message || 'Error', 'error');
                    return;
                }
                renderSearchResults(data.data);
            })
            .catch(function(e) {
                showToast('Network error: ' + e.message, 'error');
            });
    }

    function renderSearchResults(users) {
        searchResults.innerHTML = '';
        if (users.length === 0) {
            searchResults.innerHTML = '<div class="jobaida-search-no-results">Nessun risultato trovato.</div>';
            searchResults.classList.add('visible');
            return;
        }
        users.forEach(function(u) {
            var div = document.createElement('div');
            div.className = 'jobaida-search-item';
            div.innerHTML =
                '<div class="user-info">' +
                    '<span class="user-name">' + escapeHtml(u.fullname) + '</span>' +
                    '<span class="user-email">' + escapeHtml(u.email) + '</span>' +
                '</div>' +
                '<button type="button" class="btn-authorize" data-userid="' + u.id + '">' +
                    '<?php echo get_string('authorize_student', 'local_jobaida'); ?>' +
                '</button>';
            div.querySelector('.btn-authorize').addEventListener('click', function(e) {
                e.stopPropagation();
                authorizeUser(u.id, u.fullname, u.email);
            });
            searchResults.appendChild(div);
        });
        searchResults.classList.add('visible');
    }

    // ── Authorize ────────────────────────────────────────────────────────
    function authorizeUser(userid, fullname, email) {
        var formData = new FormData();
        formData.append('sesskey', sesskey);
        formData.append('action', 'authorize');
        formData.append('userid', userid);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    showToast(data.message || 'Error', 'error');
                    return;
                }
                showToast(data.message || '<?php echo get_string('student_authorized', 'local_jobaida'); ?>', 'success');

                // Add row to table.
                addAuthRow(userid, fullname, email);

                // Clear search.
                searchInput.value = '';
                searchResults.classList.remove('visible');
                searchResults.innerHTML = '';
            })
            .catch(function(e) {
                showToast('Network error: ' + e.message, 'error');
            });
    }

    function addAuthRow(userid, fullname, email) {
        var table = document.getElementById('auth-table');
        var tbody = document.getElementById('auth-tbody');
        var empty = document.getElementById('empty-state');

        // Show table, hide empty.
        table.style.display = '';
        if (empty) empty.style.display = 'none';

        var now = new Date();
        var dateStr = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear() +
                      ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

        var tr = document.createElement('tr');
        tr.id = 'auth-row-' + userid;
        tr.innerHTML =
            '<td>' + escapeHtml(fullname) + '</td>' +
            '<td>' + escapeHtml(email) + '</td>' +
            '<td><?php echo s(fullname($USER)); ?></td>' +
            '<td>' + dateStr + '</td>' +
            '<td>' +
                '<button type="button" class="btn-revoke" onclick="revokeAuth(' + userid + ', this)">' +
                    '<?php echo get_string('revoke_student', 'local_jobaida'); ?>' +
                '</button>' +
            '</td>';
        tbody.appendChild(tr);

        updateCount(1);
    }

    // ── Revoke ───────────────────────────────────────────────────────────
    window.revokeAuth = function(userid, btn) {
        if (!confirm('<?php echo get_string('delete_confirm', 'local_jobaida'); ?>')) {
            return;
        }
        var formData = new FormData();
        formData.append('sesskey', sesskey);
        formData.append('action', 'revoke');
        formData.append('userid', userid);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    showToast(data.message || 'Error', 'error');
                    return;
                }
                showToast(data.message || '<?php echo get_string('student_revoked', 'local_jobaida'); ?>', 'success');

                // Remove row.
                var row = document.getElementById('auth-row-' + userid);
                if (row) row.remove();

                updateCount(-1);

                // If table is empty, show empty state.
                var tbody = document.getElementById('auth-tbody');
                if (tbody && tbody.children.length === 0) {
                    document.getElementById('auth-table').style.display = 'none';
                    var empty = document.getElementById('empty-state');
                    if (empty) {
                        empty.style.display = '';
                    } else {
                        // Create empty state.
                        var cardBody = document.getElementById('auth-table').parentElement;
                        var div = document.createElement('div');
                        div.className = 'jobaida-empty';
                        div.id = 'empty-state';
                        div.innerHTML = '<div class="empty-icon">&#128100;</div><p>Nessuno studente autorizzato.</p>';
                        cardBody.insertBefore(div, document.getElementById('auth-table'));
                    }
                }
            })
            .catch(function(e) {
                showToast('Network error: ' + e.message, 'error');
            });
    };

    // ── Helpers ──────────────────────────────────────────────────────────
    function updateCount(delta) {
        var badge = document.getElementById('auth-count');
        var current = parseInt(badge.textContent, 10) || 0;
        badge.textContent = Math.max(0, current + delta);
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

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
