<?php
/**
 * Gestione Coach FTM Scheduler
 * Permette di aggiungere/rimuovere coach dal sistema
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/ftm_scheduler:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_scheduler/manage_coaches.php'));
$PAGE->set_title('Gestione Coach FTM');
$PAGE->set_heading('Gestione Coach FTM');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Processa azioni
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'add':
            $userid = required_param('userid', PARAM_INT);
            $initials = strtoupper(required_param('initials', PARAM_ALPHA));
            $role = optional_param('role', 'coach', PARAM_ALPHA);

            // Verifica che l'utente esista
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if (!$user) {
                \core\notification::error('Utente non trovato');
                break;
            }

            // Verifica che le iniziali non esistano già
            if ($DB->record_exists('local_ftm_coaches', ['initials' => $initials])) {
                \core\notification::error('Iniziali già in uso');
                break;
            }

            // Verifica che l'utente non sia già coach
            if ($DB->record_exists('local_ftm_coaches', ['userid' => $userid])) {
                \core\notification::error('Utente già registrato come coach');
                break;
            }

            // Inserisci
            $record = new stdClass();
            $record->userid = $userid;
            $record->initials = $initials;
            $record->role = $role;
            $record->can_week2_mon_tue = 1;
            $record->can_week2_thu_fri = 1;
            $record->active = 1;

            $DB->insert_record('local_ftm_coaches', $record);
            \core\notification::success("Coach $initials aggiunto con successo");
            break;

        case 'delete':
            $id = required_param('id', PARAM_INT);
            if ($confirm) {
                $DB->delete_records('local_ftm_coaches', ['id' => $id]);
                \core\notification::success('Coach rimosso');
            }
            break;

        case 'toggle':
            $id = required_param('id', PARAM_INT);
            $coach = $DB->get_record('local_ftm_coaches', ['id' => $id]);
            if ($coach) {
                $coach->active = $coach->active ? 0 : 1;
                $DB->update_record('local_ftm_coaches', $coach);
                \core\notification::success('Stato aggiornato');
            }
            break;
    }

    redirect(new moodle_url('/local/ftm_scheduler/manage_coaches.php'));
}

// Carica coach esistenti
$coaches = $DB->get_records_sql("
    SELECT c.*, u.firstname, u.lastname, u.email, u.username
    FROM {local_ftm_coaches} c
    JOIN {user} u ON u.id = c.userid
    ORDER BY c.initials
");

// Carica utenti potenziali (editingteacher o manager)
$potential_users = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname, u.username, u.email
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname IN ('manager', 'editingteacher', 'teacher')
    AND u.deleted = 0
    AND u.id NOT IN (SELECT userid FROM {local_ftm_coaches})
    ORDER BY u.lastname, u.firstname
");

echo $OUTPUT->header();
?>

<style>
.coach-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 20px;
}
.coach-initials {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}
.coach-initials.inactive {
    background: #ccc;
}
.coach-info {
    flex: 1;
}
.coach-name {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
}
.coach-meta {
    color: #666;
    font-size: 13px;
}
.coach-actions {
    display: flex;
    gap: 10px;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
}
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-warning { background: #ffc107; color: #333; }
.section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
}
.section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #0066cc;
    padding-bottom: 10px;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}
.form-group select,
.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
}
.status-active { color: #28a745; }
.status-inactive { color: #dc3545; }
</style>

<div style="max-width: 900px; margin: 0 auto;">

    <div class="section">
        <h3>👥 Coach Attivi</h3>

        <?php if (empty($coaches)): ?>
            <p style="color: #666; text-align: center; padding: 20px;">Nessun coach configurato</p>
        <?php else: ?>
            <?php foreach ($coaches as $c): ?>
            <div class="coach-card">
                <div class="coach-initials <?php echo $c->active ? '' : 'inactive'; ?>">
                    <?php echo s($c->initials); ?>
                </div>
                <div class="coach-info">
                    <div class="coach-name"><?php echo s($c->firstname . ' ' . $c->lastname); ?></div>
                    <div class="coach-meta">
                        @<?php echo s($c->username); ?> | <?php echo s($c->email); ?><br>
                        Ruolo: <strong><?php echo s($c->role); ?></strong> |
                        Stato: <span class="<?php echo $c->active ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $c->active ? 'Attivo' : 'Disattivato'; ?>
                        </span>
                    </div>
                </div>
                <div class="coach-actions">
                    <a href="?action=toggle&id=<?php echo $c->id; ?>&sesskey=<?php echo sesskey(); ?>"
                       class="btn <?php echo $c->active ? 'btn-warning' : 'btn-success'; ?>">
                        <?php echo $c->active ? 'Disattiva' : 'Attiva'; ?>
                    </a>
                    <a href="?action=delete&id=<?php echo $c->id; ?>&confirm=1&sesskey=<?php echo sesskey(); ?>"
                       class="btn btn-danger"
                       onclick="return confirm('Rimuovere questo coach?')">
                        Rimuovi
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3>➕ Aggiungi Nuovo Coach</h3>

        <?php if (empty($potential_users)): ?>
            <p style="color: #666;">Nessun utente disponibile. Tutti gli utenti con ruolo teacher/manager sono già coach.</p>
        <?php else: ?>
        <form method="post" action="?action=add&sesskey=<?php echo sesskey(); ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Seleziona Utente *</label>
                    <select name="userid" required>
                        <option value="">-- Seleziona --</option>
                        <?php foreach ($potential_users as $u): ?>
                        <option value="<?php echo $u->id; ?>">
                            <?php echo s($u->firstname . ' ' . $u->lastname); ?> (@<?php echo s($u->username); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Iniziali * (es: LP)</label>
                    <input type="text" name="initials" required maxlength="5" pattern="[A-Za-z]{2,5}"
                           placeholder="LP" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label>Ruolo</label>
                    <select name="role">
                        <option value="coach">Coach</option>
                        <option value="teacher">Docente</option>
                        <option value="secretary">Segreteria</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-success">➕ Aggiungi Coach</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3>ℹ️ Note</h3>
        <ul>
            <li><strong>Iniziali:</strong> Codice breve usato nel calendario (es: CB, FM, GM, RB, LP)</li>
            <li><strong>Ruolo coach:</strong> Può gestire attività settimana 2</li>
            <li><strong>Ruolo docente:</strong> Solo visualizzazione e presenze</li>
            <li><strong>Ruolo segreteria:</strong> Accesso completo pianificazione</li>
        </ul>
        <p><a href="<?php echo new moodle_url('/local/ftm_scheduler/secretary_dashboard.php'); ?>" class="btn btn-secondary">← Torna alla Dashboard</a></p>
    </div>

</div>

<?php
echo $OUTPUT->footer();
