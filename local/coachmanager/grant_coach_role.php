<?php
/**
 * Script per assegnare il ruolo Coach a un utente
 *
 * Uso: Accedere come admin e visitare questa pagina
 *
 * @package    local_coachmanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/role:assign', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/coachmanager/grant_coach_role.php'));
$PAGE->set_title('Assegna Ruolo Coach');
$PAGE->set_heading('Assegna Ruolo Coach');

// Parametri
$username = optional_param('username', '', PARAM_USERNAME);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';

if (empty($username)) {
    // Form per inserire username
    echo '<h3>Assegna Ruolo Coach</h3>';
    echo '<p>Questo script assegna la capability <code>local/coachmanager:view</code> a un utente.</p>';

    echo '<form method="get" action="">';
    echo '<div style="margin: 20px 0;">';
    echo '<label for="username" style="display: block; margin-bottom: 5px; font-weight: bold;">Username:</label>';
    echo '<input type="text" name="username" id="username" value="roberto.bravo" style="padding: 8px; width: 300px; border: 1px solid #ccc; border-radius: 4px;">';
    echo '</div>';
    echo '<button type="submit" style="background: #0066cc; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Verifica Utente</button>';
    echo '</form>';

} else {
    // Cerca l'utente
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

    if (!$user) {
        echo '<div style="background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;">';
        echo '<strong>Errore:</strong> Utente "' . s($username) . '" non trovato.';
        echo '</div>';
        echo '<p><a href="?">Torna indietro</a></p>';
    } else {
        echo '<h3>Utente Trovato</h3>';
        echo '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        echo '<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>ID</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . $user->id . '</td></tr>';
        echo '<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Username</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . s($user->username) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Nome</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . s(fullname($user)) . '</td></tr>';
        echo '<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;"><strong>Email</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . s($user->email) . '</td></tr>';
        echo '</table>';

        // Verifica ruoli attuali
        $context = context_system::instance();
        $roles = get_user_roles($context, $user->id, false);

        echo '<h4>Ruoli Attuali (Sistema)</h4>';
        if (empty($roles)) {
            echo '<p style="color: #666;">Nessun ruolo a livello sistema.</p>';
        } else {
            echo '<ul>';
            foreach ($roles as $role) {
                $rolename = role_get_name($DB->get_record('role', ['id' => $role->roleid]));
                echo '<li>' . s($rolename) . '</li>';
            }
            echo '</ul>';
        }

        // Verifica se ha già la capability
        $hascap = has_capability('local/coachmanager:view', $context, $user->id);

        echo '<h4>Capability local/coachmanager:view</h4>';
        if ($hascap) {
            echo '<p style="color: #28a745;"><strong>✅ L\'utente ha già questa capability!</strong></p>';
            echo '<p>L\'utente dovrebbe poter accedere alla Dashboard Coach V2.</p>';
        } else {
            echo '<p style="color: #dc3545;"><strong>❌ L\'utente NON ha questa capability.</strong></p>';

            if ($confirm && confirm_sesskey()) {
                // Cerca un ruolo adatto (editingteacher o teacher)
                $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
                if (!$roleid) {
                    $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
                }

                if ($roleid) {
                    // Assegna il ruolo a livello sistema
                    role_assign($roleid, $user->id, $context->id);

                    $rolename = role_get_name($DB->get_record('role', ['id' => $roleid]));
                    echo '<div style="background: #d4edda; padding: 15px; border-radius: 4px; color: #155724; margin: 20px 0;">';
                    echo '<strong>✅ Ruolo assegnato!</strong><br>';
                    echo 'Ruolo "' . s($rolename) . '" assegnato a ' . s(fullname($user)) . ' a livello sistema.';
                    echo '</div>';

                    // Verifica di nuovo
                    $hascap_now = has_capability('local/coachmanager:view', $context, $user->id);
                    if ($hascap_now) {
                        echo '<p style="color: #28a745;"><strong>✅ Ora l\'utente ha la capability!</strong></p>';
                    } else {
                        echo '<p style="color: #ffc107;"><strong>⚠️ La capability potrebbe richiedere un refresh della cache.</strong></p>';
                        echo '<p>Prova ad accedere alla <a href="/local/coachmanager/coach_dashboard_v2.php" target="_blank">Dashboard Coach V2</a></p>';
                    }
                } else {
                    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;">';
                    echo '<strong>Errore:</strong> Nessun ruolo teacher/editingteacher trovato nel sistema.';
                    echo '</div>';
                }
            } else {
                // Mostra pulsante per assegnare
                echo '<form method="get" action="">';
                echo '<input type="hidden" name="username" value="' . s($username) . '">';
                echo '<input type="hidden" name="confirm" value="1">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                echo '<button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">Assegna Ruolo Teacher (Sistema)</button>';
                echo '</form>';
                echo '<p style="color: #666; font-size: 0.9em; margin-top: 10px;">Questo assegnerà il ruolo "Editing Teacher" a livello sistema, che include la capability necessaria.</p>';
            }
        }

        echo '<p style="margin-top: 20px;"><a href="?">← Torna indietro</a></p>';
    }
}

echo '</div>';

echo $OUTPUT->footer();
