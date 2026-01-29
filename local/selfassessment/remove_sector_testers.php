<?php
// ============================================
// Script: Rimuovi Settore Primario per Tester
// ============================================
// Rimuove il filtro settore per gli utenti di test
// in modo che possano testare TUTTI i corsi/quiz
// ============================================

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/remove_sector_testers.php'));
$PAGE->set_title('Configura Utenti Tester');

// Lista utenti tester (cerca per nome/cognome parziale)
$tester_names = [
    'Fabio',
    'Sandra',
    'Alessandra',
    'Francesco',
    'Graziano',
    'Critest'
];

// Azione
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo $OUTPUT->header();
?>

<style>
.tester-container { max-width: 900px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.tester-card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.tester-title { font-size: 1.5em; font-weight: 700; margin-bottom: 20px; color: #333; }
.tester-table { width: 100%; border-collapse: collapse; }
.tester-table th, .tester-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.tester-table th { background: #f8f9fa; font-weight: 600; }
.tester-table tr:hover { background: #f8f9ff; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 14px; }
.btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.result-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-top: 15px; }
</style>

<div class="tester-container">
    <div class="tester-card">
        <div class="tester-title">🧪 Configurazione Utenti Tester</div>
        <p style="color: #666; margin-bottom: 20px;">
            Questo script rimuove il settore primario dagli utenti di test, permettendo loro di ricevere
            competenze da <strong>TUTTI</strong> i settori quando completano i quiz.
        </p>

        <?php
        // Trova gli utenti tester nel database
        $testers = [];

        foreach ($tester_names as $name) {
            $name_lower = strtolower($name);
            $users = $DB->get_records_sql("
                SELECT u.id, u.username, u.firstname, u.lastname, u.email
                FROM {user} u
                WHERE u.deleted = 0
                AND (LOWER(u.firstname) LIKE ? OR LOWER(u.lastname) LIKE ? OR LOWER(u.username) LIKE ?)
                ORDER BY u.lastname, u.firstname
            ", ["%{$name_lower}%", "%{$name_lower}%", "%{$name_lower}%"]);

            foreach ($users as $user) {
                $testers[$user->id] = $user;
            }
        }

        // Verifica tabella settori
        $dbman = $DB->get_manager();
        $table_exists = $dbman->table_exists('local_student_sectors');

        if (!$table_exists) {
            echo '<div class="alert alert-info">
                  <strong>ℹ️ Info:</strong> La tabella <code>local_student_sectors</code> non esiste ancora.
                  Gli utenti non hanno filtri settore attivi.
                  </div>';
        }

        // Esegui azione
        if ($action === 'remove' && $confirm && $table_exists) {
            $removed = 0;
            $results = [];

            foreach ($testers as $user) {
                // Rimuovi settore primario
                $deleted = $DB->delete_records('local_student_sectors', [
                    'userid' => $user->id,
                    'is_primary' => 1
                ]);

                if ($deleted) {
                    $removed++;
                    $results[] = "✓ Rimosso settore primario per " . fullname($user);
                } else {
                    // Verifica se aveva un settore
                    $had_sector = $DB->record_exists('local_student_sectors', ['userid' => $user->id]);
                    if (!$had_sector) {
                        $results[] = "○ " . fullname($user) . " - nessun settore impostato";
                    }
                }
            }

            echo '<div class="alert alert-success">
                  <strong>✅ Operazione completata!</strong><br>
                  Settori primari rimossi: <strong>' . $removed . '</strong>
                  </div>';

            if (!empty($results)) {
                echo '<div class="result-box">';
                echo '<strong>Dettaglio:</strong><br>';
                foreach ($results as $r) {
                    echo $r . '<br>';
                }
                echo '</div>';
            }

            echo '<div class="alert alert-info" style="margin-top: 20px;">
                  <strong>📝 Prossimi passi:</strong><br>
                  1. Gli utenti tester devono <strong>rifare un quiz</strong> (o usare il catchup)<br>
                  2. Le competenze verranno assegnate da TUTTI i settori<br>
                  3. Potranno completare le autovalutazioni per ogni settore
                  </div>';

            echo '<div style="margin-top: 20px;">
                  <a href="catchup_assignments.php" class="btn btn-success">🔄 Esegui Catchup Assegnazioni</a>
                  <a href="diagnose.php" class="btn btn-primary" style="margin-left: 10px;">🔍 Vai a Diagnosi</a>
                  </div>';
        }

        // Mostra tabella utenti trovati
        if (!empty($testers)) {
            echo '<h3 style="margin-top: 30px;">👥 Utenti Tester Trovati (' . count($testers) . ')</h3>';
            echo '<table class="tester-table">';
            echo '<tr><th>ID</th><th>Nome</th><th>Username</th><th>Email</th><th>Settore Primario</th></tr>';

            foreach ($testers as $user) {
                // Verifica settore primario
                $sector = null;
                if ($table_exists) {
                    $sector = $DB->get_record('local_student_sectors', [
                        'userid' => $user->id,
                        'is_primary' => 1
                    ]);
                }

                $sector_badge = $sector
                    ? '<span class="badge badge-warning">' . $sector->sector . '</span>'
                    : '<span class="badge badge-success">Nessuno (OK)</span>';

                echo '<tr>';
                echo '<td>' . $user->id . '</td>';
                echo '<td><strong>' . fullname($user) . '</strong></td>';
                echo '<td>' . $user->username . '</td>';
                echo '<td>' . $user->email . '</td>';
                echo '<td>' . $sector_badge . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Conta quanti hanno settore primario
            $with_sector = 0;
            if ($table_exists) {
                foreach ($testers as $user) {
                    if ($DB->record_exists('local_student_sectors', ['userid' => $user->id, 'is_primary' => 1])) {
                        $with_sector++;
                    }
                }
            }

            if ($with_sector > 0 && $action !== 'remove') {
                echo '<div style="margin-top: 25px; padding: 20px; background: #fff3cd; border-radius: 8px;">';
                echo '<strong>⚠️ ' . $with_sector . ' utente/i hanno un settore primario impostato.</strong><br>';
                echo 'Questo limita le competenze che ricevono dai quiz. Rimuovi i settori per permettere test completi.';
                echo '</div>';

                echo '<form method="post" style="margin-top: 20px;">';
                echo '<input type="hidden" name="action" value="remove">';
                echo '<input type="hidden" name="confirm" value="1">';
                echo '<button type="submit" class="btn btn-danger">🗑️ Rimuovi Settore Primario da Tutti i Tester</button>';
                echo '</form>';
            } else if ($with_sector == 0) {
                echo '<div class="alert alert-success" style="margin-top: 20px;">';
                echo '<strong>✅ Perfetto!</strong> Nessun tester ha un settore primario impostato. ';
                echo 'Possono ricevere competenze da tutti i settori.';
                echo '</div>';

                echo '<div style="margin-top: 20px;">
                      <a href="catchup_assignments.php" class="btn btn-success">🔄 Esegui Catchup Assegnazioni</a>
                      <a href="diagnose.php" class="btn btn-primary" style="margin-left: 10px;">🔍 Vai a Diagnosi</a>
                      </div>';
            }

        } else {
            echo '<div class="alert" style="background: #f8d7da; color: #721c24;">
                  <strong>⚠️ Nessun utente trovato</strong> con i nomi specificati.
                  </div>';
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h4>📋 Utenti cercati:</h4>
            <p style="color: #666;">
                <?php echo implode(', ', $tester_names); ?>
            </p>
            <p style="color: #999; font-size: 0.9em;">
                Per aggiungere altri utenti, modifica l'array <code>$tester_names</code> all'inizio dello script.
            </p>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
