<?php
/**
 * Setup Coach FTM - Popola la tabella local_ftm_coaches
 *
 * Esegui UNA SOLA VOLTA per inserire i coach predefiniti.
 * URL: /local/ftm_scheduler/setup_coaches.php
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_scheduler:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/setup_coaches.php'));
$PAGE->set_title('Setup Coach FTM');
$PAGE->set_heading('Setup Coach FTM');

$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
echo '<h2>Setup Coach FTM Scheduler</h2>';

// Coach da inserire con mappatura username -> iniziali
$coaches_to_add = [
    // Formato: 'iniziali' => ['cerca per username o email parziale', 'nome fallback']
    'CB' => ['bodda', 'Cristian Bodda'],
    'FM' => ['marinoni', 'Fabio Marinoni'],
    'GM' => ['margonar', 'Graziano Margonar'],
    'RB' => ['bravo', 'Roberto Bravo'],
    'LP' => ['lp', 'LP'], // Modifica 'lp' con username o parte email di LP
];

// Verifica coach esistenti
$existing = $DB->get_records('local_ftm_coaches', null, '', 'initials, userid');
$existing_initials = array_column($existing, 'initials');

echo '<h3>Coach da configurare:</h3>';
echo '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
echo '<thead><tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #ddd;">Iniziali</th><th style="padding: 10px; border: 1px solid #ddd;">Nome</th><th style="padding: 10px; border: 1px solid #ddd;">Stato</th><th style="padding: 10px; border: 1px solid #ddd;">Utente Moodle</th></tr></thead>';
echo '<tbody>';

$to_insert = [];

foreach ($coaches_to_add as $initials => $info) {
    $search = $info[0];
    $fallback_name = $info[1];

    echo '<tr>';
    echo '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; font-size: 18px;">' . $initials . '</td>';
    echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $fallback_name . '</td>';

    if (in_array($initials, $existing_initials)) {
        echo '<td style="padding: 10px; border: 1px solid #ddd; color: green;">✅ Già presente</td>';
        echo '<td style="padding: 10px; border: 1px solid #ddd;">-</td>';
    } else {
        // Cerca utente
        $user = $DB->get_record_sql("
            SELECT id, username, firstname, lastname, email
            FROM {user}
            WHERE deleted = 0
            AND (username LIKE ? OR email LIKE ? OR LOWER(firstname) LIKE ? OR LOWER(lastname) LIKE ?)
            LIMIT 1
        ", ["%$search%", "%$search%", "%$search%", "%$search%"]);

        if ($user) {
            echo '<td style="padding: 10px; border: 1px solid #ddd; color: orange;">⏳ Da aggiungere</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">@' . $user->username . ' (' . $user->firstname . ' ' . $user->lastname . ')</td>';
            $to_insert[$initials] = $user->id;
        } else {
            echo '<td style="padding: 10px; border: 1px solid #ddd; color: red;">❌ Utente non trovato</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;"><em>Cerca: "' . $search . '"</em></td>';
        }
    }
    echo '</tr>';
}

echo '</tbody></table>';

if (!empty($to_insert)) {
    if ($confirm) {
        echo '<h3>Inserimento in corso...</h3>';
        echo '<ul>';
        foreach ($to_insert as $initials => $userid) {
            $record = new stdClass();
            $record->userid = $userid;
            $record->initials = $initials;
            $record->role = 'coach';
            $record->can_week2_mon_tue = 1;
            $record->can_week2_thu_fri = 1;
            $record->active = 1;

            try {
                $DB->insert_record('local_ftm_coaches', $record);
                echo '<li style="color: green;">✅ ' . $initials . ' inserito con successo</li>';
            } catch (Exception $e) {
                echo '<li style="color: red;">❌ ' . $initials . ' errore: ' . $e->getMessage() . '</li>';
            }
        }
        echo '</ul>';
        echo '<div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-top: 20px;">';
        echo '<strong>✅ Setup completato!</strong> Ora i coach appariranno nei dropdown.';
        echo '</div>';
        echo '<p><a href="' . new moodle_url('/local/ftm_scheduler/secretary_dashboard.php') . '" style="display: inline-block; padding: 10px 20px; background: #0066cc; color: white; border-radius: 6px; text-decoration: none; margin-top: 15px;">Vai alla Dashboard</a></p>';
    } else {
        echo '<form method="post">';
        echo '<input type="hidden" name="confirm" value="1">';
        echo '<button type="submit" style="padding: 15px 30px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer;">✅ Inserisci Coach Mancanti</button>';
        echo '</form>';
    }
} else {
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 8px;">';
    echo '<strong>✅ Tutti i coach sono già configurati!</strong>';
    echo '</div>';
}

// Lista utenti disponibili per aggiunta manuale
echo '<hr style="margin: 30px 0;">';
echo '<h3>Aggiungi manualmente</h3>';
echo '<p>Se un coach non è stato trovato automaticamente, puoi aggiungerlo dalla pagina:</p>';
echo '<p><a href="' . new moodle_url('/local/ftm_scheduler/manage_coaches.php') . '" style="display: inline-block; padding: 10px 20px; background: #0066cc; color: white; border-radius: 6px; text-decoration: none;">👤 Gestione Coach</a></p>';

// Mostra utenti con ruolo teacher/manager per aiutare a identificare LP
echo '<h4>Utenti con ruolo docente/manager (per trovare LP):</h4>';
$teachers = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname IN ('manager', 'editingteacher', 'teacher')
    AND u.deleted = 0
    ORDER BY u.lastname, u.firstname
    LIMIT 30
");

if ($teachers) {
    echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
    echo '<thead><tr style="background: #f8f9fa;"><th style="padding: 8px; border: 1px solid #ddd;">Username</th><th style="padding: 8px; border: 1px solid #ddd;">Nome</th><th style="padding: 8px; border: 1px solid #ddd;">Email</th></tr></thead>';
    echo '<tbody>';
    foreach ($teachers as $t) {
        $is_coach = $DB->record_exists('local_ftm_coaches', ['userid' => $t->id]);
        $style = $is_coach ? 'background: #d4edda;' : '';
        echo '<tr style="' . $style . '">';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $t->username . ($is_coach ? ' ✅' : '') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $t->firstname . ' ' . $t->lastname . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $t->email . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p style="font-size: 12px; color: #666;">✅ = già coach</p>';
}

echo '</div>';

echo $OUTPUT->footer();
