<?php
/**
 * Crea il ruolo "Segreteria FTM" con tutte le capability necessarie per lo scheduler
 *
 * Uso: /local/ftm_scheduler/create_secretary_role.php
 *      /local/ftm_scheduler/create_secretary_role.php?assign=username1,username2
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/role:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/create_secretary_role.php'));
$PAGE->set_title('Crea Ruolo Segreteria FTM');
$PAGE->set_heading('Crea Ruolo Segreteria FTM');

$assign = optional_param('assign', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
echo '<h2>Creazione Ruolo Segreteria FTM</h2>';

// Definizione del ruolo
$role_shortname = 'ftm_secretary';
$role_name = 'Segreteria FTM';
$role_description = 'Ruolo per il personale di segreteria FTM. Permette accesso completo allo Scheduler e alla gestione operativa.';

// Capability da assegnare
$capabilities = [
    // FTM Scheduler - tutte le capability
    'local/ftm_scheduler:view' => CAP_ALLOW,
    'local/ftm_scheduler:manage' => CAP_ALLOW,
    'local/ftm_scheduler:managegroups' => CAP_ALLOW,
    'local/ftm_scheduler:manageactivities' => CAP_ALLOW,
    'local/ftm_scheduler:managerooms' => CAP_ALLOW,
    'local/ftm_scheduler:enrollstudents' => CAP_ALLOW,
    'local/ftm_scheduler:markattendance' => CAP_ALLOW,

    // FTM CPURC (se esiste)
    'local/ftm_cpurc:view' => CAP_ALLOW,
    'local/ftm_cpurc:edit' => CAP_ALLOW,
    'local/ftm_cpurc:import' => CAP_ALLOW,
    'local/ftm_cpurc:generatereport' => CAP_ALLOW,

    // Competency Manager
    'local/competencymanager:view' => CAP_ALLOW,
    'local/competencymanager:managesectors' => CAP_ALLOW,

    // Coach Manager (visualizzazione)
    'local/coachmanager:view' => CAP_ALLOW,
    'local/coachmanager:viewallnotes' => CAP_ALLOW,

    // Selfassessment (gestione)
    'local/selfassessment:view' => CAP_ALLOW,
    'local/selfassessment:manage' => CAP_ALLOW,

    // FTM Test Suite (opzionale per debug)
    'local/ftm_testsuite:view' => CAP_ALLOW,
    'local/ftm_testsuite:manage' => CAP_ALLOW,

    // Capability Moodle base utili
    'moodle/user:viewdetails' => CAP_ALLOW,
    'moodle/user:viewhiddendetails' => CAP_ALLOW,
    'moodle/course:view' => CAP_ALLOW,
    'moodle/course:viewparticipants' => CAP_ALLOW,
];

// Verifica se il ruolo esiste già
$existing_role = $DB->get_record('role', ['shortname' => $role_shortname]);

if ($existing_role) {
    echo '<div class="alert alert-info">';
    echo '<strong>Ruolo esistente:</strong> ' . $role_name . ' (ID: ' . $existing_role->id . ')';
    echo '</div>';
    $roleid = $existing_role->id;
} else {
    if ($confirm) {
        // Crea il ruolo
        $roleid = create_role($role_name, $role_shortname, $role_description);

        if ($roleid) {
            echo '<div class="alert alert-success">';
            echo '<strong>Ruolo creato:</strong> ' . $role_name . ' (ID: ' . $roleid . ')';
            echo '</div>';

            // Imposta il contesto del ruolo (sistema)
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        } else {
            echo '<div class="alert alert-danger">Errore nella creazione del ruolo</div>';
            echo $OUTPUT->footer();
            die();
        }
    } else {
        echo '<div class="alert alert-warning">';
        echo '<strong>Il ruolo non esiste.</strong> Clicca "Crea Ruolo" per crearlo.';
        echo '</div>';

        echo '<h3>Capability che verranno assegnate:</h3>';
        echo '<table class="table table-sm" style="font-size: 13px;">';
        echo '<thead><tr><th>Capability</th><th>Permesso</th></tr></thead><tbody>';
        foreach ($capabilities as $cap => $permission) {
            $perm_text = $permission == CAP_ALLOW ? '<span style="color:green;">CONSENTI</span>' : 'Eredita';
            echo '<tr><td><code>' . $cap . '</code></td><td>' . $perm_text . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<form method="post">';
        echo '<input type="hidden" name="confirm" value="1">';
        if ($assign) {
            echo '<input type="hidden" name="assign" value="' . s($assign) . '">';
        }
        echo '<button type="submit" class="btn btn-success btn-lg">Crea Ruolo Segreteria FTM</button>';
        echo '</form>';

        echo '</div>';
        echo $OUTPUT->footer();
        die();
    }
}

// Assegna le capability
echo '<h3>Assegnazione Capability</h3>';
echo '<table class="table table-sm" style="font-size: 13px;">';
echo '<thead><tr><th>Capability</th><th>Stato</th></tr></thead><tbody>';

$context = context_system::instance();
$assigned_count = 0;
$skipped_count = 0;

foreach ($capabilities as $cap => $permission) {
    // Verifica se la capability esiste
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        echo '<tr><td><code>' . $cap . '</code></td><td><span style="color:gray;">Capability non esiste (plugin non installato?)</span></td></tr>';
        $skipped_count++;
        continue;
    }

    // Assegna la capability
    assign_capability($cap, $permission, $roleid, $context->id, true);
    echo '<tr><td><code>' . $cap . '</code></td><td><span style="color:green;">Assegnata</span></td></tr>';
    $assigned_count++;
}

echo '</tbody></table>';
echo '<p><strong>Assegnate:</strong> ' . $assigned_count . ' | <strong>Saltate:</strong> ' . $skipped_count . '</p>';

// Assegna il ruolo agli utenti specificati
if (!empty($assign)) {
    echo '<h3>Assegnazione Ruolo a Utenti</h3>';

    $usernames = array_map('trim', explode(',', $assign));

    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Username</th><th>Nome</th><th>Stato</th></tr></thead><tbody>';

    foreach ($usernames as $username) {
        if (empty($username)) continue;

        $user = $DB->get_record('user', ['username' => $username]);

        if (!$user) {
            // Prova a cercare per email o nome
            $user = $DB->get_record('user', ['email' => $username]);
        }

        if ($user) {
            // Verifica se già assegnato
            $already_assigned = $DB->record_exists('role_assignments', [
                'roleid' => $roleid,
                'contextid' => $context->id,
                'userid' => $user->id
            ]);

            if ($already_assigned) {
                echo '<tr><td>' . $user->username . '</td><td>' . fullname($user) . '</td><td><span style="color:blue;">Già assegnato</span></td></tr>';
            } else {
                role_assign($roleid, $user->id, $context->id);
                echo '<tr><td>' . $user->username . '</td><td>' . fullname($user) . '</td><td><span style="color:green;">Assegnato</span></td></tr>';
            }
        } else {
            echo '<tr><td>' . s($username) . '</td><td>-</td><td><span style="color:red;">Utente non trovato</span></td></tr>';
        }
    }

    echo '</tbody></table>';
}

// Form per assegnare il ruolo ad altri utenti
echo '<h3>Assegna Ruolo a Utenti</h3>';
echo '<form method="get" style="margin-bottom: 20px;">';
echo '<input type="hidden" name="confirm" value="1">';
echo '<div style="display: flex; gap: 10px; align-items: center;">';
echo '<input type="text" name="assign" placeholder="username1, username2, ..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" value="' . s($assign) . '">';
echo '<button type="submit" class="btn btn-primary">Assegna Ruolo</button>';
echo '</div>';
echo '<p style="font-size: 13px; color: #666; margin-top: 5px;">Inserisci gli username separati da virgola (es: sandra, alessandra)</p>';
echo '</form>';

// Lista utenti con questo ruolo
echo '<h3>Utenti con ruolo Segreteria FTM</h3>';
$role_users = $DB->get_records_sql("
    SELECT u.id, u.username, u.firstname, u.lastname, u.email, ra.timemodified
    FROM {role_assignments} ra
    JOIN {user} u ON u.id = ra.userid
    WHERE ra.roleid = ? AND ra.contextid = ?
    ORDER BY u.lastname, u.firstname
", [$roleid, $context->id]);

if ($role_users) {
    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Username</th><th>Nome</th><th>Email</th><th>Assegnato il</th><th>Azione</th></tr></thead><tbody>';
    foreach ($role_users as $ru) {
        echo '<tr>';
        echo '<td>' . $ru->username . '</td>';
        echo '<td>' . $ru->firstname . ' ' . $ru->lastname . '</td>';
        echo '<td>' . $ru->email . '</td>';
        echo '<td>' . userdate($ru->timemodified, '%d/%m/%Y %H:%M') . '</td>';
        echo '<td><a href="?confirm=1&remove=' . $ru->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Rimuovere il ruolo a questo utente?\')">Rimuovi</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-warning">Nessun utente con questo ruolo</div>';
}

// Gestione rimozione
$remove_userid = optional_param('remove', 0, PARAM_INT);
if ($remove_userid && $confirm) {
    role_unassign($roleid, $remove_userid, $context->id);
    echo '<div class="alert alert-success">Ruolo rimosso dall\'utente</div>';
    echo '<script>window.location.href = "?confirm=1";</script>';
}

echo '<hr>';
echo '<h3>Link Utili</h3>';
echo '<ul>';
echo '<li><a href="' . new moodle_url('/local/ftm_scheduler/index.php') . '">FTM Scheduler</a></li>';
echo '<li><a href="' . new moodle_url('/admin/roles/manage.php') . '">Gestione Ruoli Moodle</a></li>';
echo '<li><a href="' . new moodle_url('/admin/roles/assign.php', ['contextid' => $context->id]) . '">Assegna Ruoli di Sistema</a></li>';
echo '</ul>';

echo '</div>';

echo $OUTPUT->footer();
