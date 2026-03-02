<?php
/**
 * One-time fix: Correct coach userid assignments in local_ftm_coaches.
 *
 * Shows current coach-user mappings and allows fixing incorrect ones.
 *
 * Usage: Navigate to /local/ftm_scheduler/fix_coaches.php
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', 'Solo admin');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/fix_coaches.php'));
$PAGE->set_title('Fix Coach Assignments');
$PAGE->set_heading('Fix Coach Assignments');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<h3>Correzione assegnazione Coach</h3>';
echo '<p>Questo strumento mostra le associazioni coach &harr; utente Moodle e permette di correggerle.</p>';

// Load current coaches.
$coaches = $DB->get_records_sql("
    SELECT c.id, c.userid, c.initials, c.role, c.active,
           u.firstname, u.lastname, u.username, u.email
    FROM {local_ftm_coaches} c
    LEFT JOIN {user} u ON u.id = c.userid
    ORDER BY c.initials
");

if ($action === 'fix' && confirm_sesskey()) {
    $coachid = required_param('coachid', PARAM_INT);
    $newuserid = required_param('newuserid', PARAM_INT);

    $coach = $DB->get_record('local_ftm_coaches', ['id' => $coachid]);
    $newuser = $DB->get_record('user', ['id' => $newuserid, 'deleted' => 0]);

    if ($coach && $newuser) {
        $olduserid = $coach->userid;
        $DB->set_field('local_ftm_coaches', 'userid', $newuserid, ['id' => $coachid]);

        echo '<div class="alert alert-success">';
        echo '<strong>Corretto!</strong> Coach <strong>' . s($coach->initials) . '</strong>: ';
        echo 'userid cambiato da ' . $olduserid . ' a ' . $newuserid;
        echo ' (<strong>' . s($newuser->firstname . ' ' . $newuser->lastname) . '</strong>)';
        echo '</div>';

        // Reload coaches.
        $coaches = $DB->get_records_sql("
            SELECT c.id, c.userid, c.initials, c.role, c.active,
                   u.firstname, u.lastname, u.username, u.email
            FROM {local_ftm_coaches} c
            LEFT JOIN {user} u ON u.id = c.userid
            ORDER BY c.initials
        ");
    } else {
        echo '<div class="alert alert-danger">Coach o utente non trovato.</div>';
    }
}

// Display current mappings.
echo '<h4>Mappature attuali</h4>';
echo '<table class="table table-bordered table-sm" style="font-size: 14px;">';
echo '<thead style="background: #f0f0f0;"><tr>';
echo '<th>ID</th><th>Iniziali</th><th>Ruolo</th><th>Attivo</th>';
echo '<th>UserID</th><th>Utente Moodle Associato</th><th>Username</th><th>Email</th>';
echo '<th>Azione</th>';
echo '</tr></thead><tbody>';

foreach ($coaches as $c) {
    $rowstyle = '';
    $warning = '';
    if (empty($c->firstname)) {
        $rowstyle = 'background: #ffe0e0;';
        $warning = ' <strong style="color:red;">[UTENTE NON TROVATO]</strong>';
    }

    echo '<tr style="' . $rowstyle . '">';
    echo '<td>' . $c->id . '</td>';
    echo '<td><strong>' . s($c->initials) . '</strong></td>';
    echo '<td>' . s($c->role) . '</td>';
    echo '<td>' . ($c->active ? '<span style="color:green;">Si</span>' : '<span style="color:red;">No</span>') . '</td>';
    echo '<td>' . $c->userid . '</td>';
    echo '<td>' . s(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) . $warning . '</td>';
    echo '<td>' . s($c->username ?? '-') . '</td>';
    echo '<td>' . s($c->email ?? '-') . '</td>';
    echo '<td>';
    echo '<button type="button" class="btn btn-sm btn-warning fix-btn" data-coachid="' . $c->id . '" ';
    echo 'data-initials="' . s($c->initials) . '">Cambia Utente</button>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Search and fix form.
echo '<div id="fix-panel" style="display:none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px solid #0066cc;">';
echo '<h4 id="fix-title">Cerca utente corretto</h4>';
echo '<p>Cerca l\'utente Moodle da associare al coach:</p>';
echo '<div style="display: flex; gap: 10px; margin-bottom: 15px;">';
echo '<input type="text" id="search-user" placeholder="Nome, cognome o email..." style="flex:1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">';
echo '<button type="button" id="btn-search" class="btn btn-primary" style="padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 6px; cursor: pointer;">Cerca</button>';
echo '</div>';
echo '<div id="search-results"></div>';
echo '</div>';

?>

<script>
var currentCoachId = 0;

document.querySelectorAll('.fix-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        currentCoachId = this.dataset.coachid;
        var initials = this.dataset.initials;
        document.getElementById('fix-panel').style.display = 'block';
        document.getElementById('fix-title').textContent = 'Cerca utente corretto per coach: ' + initials;
        document.getElementById('search-user').value = '';
        document.getElementById('search-results').innerHTML = '';
        document.getElementById('search-user').focus();
    });
});

document.getElementById('btn-search').addEventListener('click', doSearch);
document.getElementById('search-user').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { doSearch(); }
});

function doSearch() {
    var q = document.getElementById('search-user').value.trim();
    if (q.length < 2) { return; }

    var resultsEl = document.getElementById('search-results');
    resultsEl.innerHTML = '<em>Cercando...</em>';

    // Use Moodle AJAX to search users.
    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_scheduler/ajax_search_users.php?sesskey=<?php echo sesskey(); ?>&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || data.users.length === 0) {
                resultsEl.innerHTML = '<em>Nessun utente trovato.</em>';
                return;
            }
            var html = '<table class="table table-sm table-bordered" style="font-size:13px;">';
            html += '<thead><tr><th>ID</th><th>Nome</th><th>Username</th><th>Email</th><th></th></tr></thead><tbody>';
            data.users.forEach(function(u) {
                html += '<tr>';
                html += '<td>' + u.id + '</td>';
                html += '<td><strong>' + u.firstname + ' ' + u.lastname + '</strong></td>';
                html += '<td>' + u.username + '</td>';
                html += '<td>' + u.email + '</td>';
                html += '<td><a href="?action=fix&coachid=' + currentCoachId + '&newuserid=' + u.id + '&sesskey=<?php echo sesskey(); ?>" ';
                html += 'class="btn btn-sm btn-success" style="background:#28a745;color:white;padding:4px 12px;border-radius:4px;text-decoration:none;" ';
                html += 'onclick="return confirm(\'Associare questo utente al coach?\')">Assegna</a></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            resultsEl.innerHTML = html;
        })
        .catch(function(err) {
            resultsEl.innerHTML = '<em style="color:red;">Errore nella ricerca.</em>';
        });
}
</script>

<?php

echo '<div style="margin-top: 30px;">';
echo '<a href="' . new moodle_url('/local/ftm_scheduler/manage_coaches.php') . '" class="btn btn-secondary" style="padding: 10px 20px; background: #6c757d; color: white; border-radius: 6px; text-decoration: none;">Torna a Gestione Coach</a>';
echo '</div>';

echo $OUTPUT->footer();
