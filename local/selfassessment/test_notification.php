<?php
// ============================================
// Self Assessment - Test Notifiche
// ============================================
// Esegui questo script per testare il sistema
// di notifiche senza completare un quiz reale
// ============================================

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/test_notification.php'));
$PAGE->set_title('Test Notifiche Self Assessment');

echo $OUTPUT->header();

// Parametri
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

?>
<style>
.test-container { max-width: 800px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.test-section { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.test-title { font-size: 1.3em; font-weight: 700; margin-bottom: 15px; }
.result-ok { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; }
.result-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; }
.result-info { background: #cce5ff; color: #004085; padding: 15px; border-radius: 8px; margin: 10px 0; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
</style>

<div class="test-container">
    <h1>🧪 Test Notifiche Self Assessment</h1>

    <?php
    // ============================================
    // 1. VERIFICA CONFIGURAZIONE MESSAGE PROVIDERS
    // ============================================
    echo '<div class="test-section">';
    echo '<div class="test-title">1. Verifica Message Providers</div>';

    $messages_file = __DIR__ . '/db/messages.php';
    if (file_exists($messages_file)) {
        echo '<div class="result-ok">✓ File <code>db/messages.php</code> esiste</div>';

        // Leggi e verifica contenuto
        include($messages_file);
        if (isset($messageproviders['reminder'])) {
            echo '<div class="result-ok">✓ Provider <code>reminder</code> definito</div>';
        } else {
            echo '<div class="result-error">✗ Provider reminder NON definito</div>';
        }
        if (isset($messageproviders['assignment'])) {
            echo '<div class="result-ok">✓ Provider <code>assignment</code> definito</div>';
        } else {
            echo '<div class="result-error">✗ Provider assignment NON definito</div>';
        }
    } else {
        echo '<div class="result-error">✗ File db/messages.php NON ESISTE!</div>';
    }

    echo '</div>';

    // ============================================
    // 2. VERIFICA VERSIONE PLUGIN AGGIORNATA
    // ============================================
    echo '<div class="test-section">';
    echo '<div class="test-title">2. Verifica Versione Plugin</div>';

    $plugin_version = $DB->get_field('config_plugins', 'value', [
        'plugin' => 'local_selfassessment',
        'name' => 'version'
    ]);

    if ($plugin_version) {
        echo '<div class="result-info">Versione installata: <strong>' . $plugin_version . '</strong></div>';

        if ($plugin_version >= 2026011401) {
            echo '<div class="result-ok">✓ Versione con sistema notifiche installata</div>';
        } else {
            echo '<div class="result-error">✗ Versione obsoleta! Esegui l\'upgrade del plugin.<br>
                  Vai su <strong>Amministrazione > Notifiche</strong> per aggiornare.</div>';
        }
    } else {
        echo '<div class="result-error">✗ Plugin non installato nel database</div>';
    }

    echo '</div>';

    // ============================================
    // 3. SELEZIONA STUDENTE PER TEST
    // ============================================
    echo '<div class="test-section">';
    echo '<div class="test-title">3. Test Invio Notifica</div>';

    // Lista studenti con assegnazioni recenti
    $recent_students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
               COUNT(sa.id) as assign_count,
               MAX(sa.timecreated) as last_assign
        FROM {user} u
        JOIN {local_selfassessment_assign} sa ON sa.userid = u.id
        GROUP BY u.id, u.firstname, u.lastname, u.email
        ORDER BY last_assign DESC
        LIMIT 10
    ");

    if (empty($recent_students)) {
        echo '<div class="result-error">Nessuno studente con assegnazioni trovato</div>';
    } else {
        echo '<p>Seleziona uno studente per testare l\'invio della notifica:</p>';
        echo '<form method="post" style="margin: 15px 0;">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<select name="userid" style="padding: 10px; width: 100%; max-width: 400px; margin-bottom: 10px;">';
        foreach ($recent_students as $student) {
            $selected = ($userid == $student->id) ? 'selected' : '';
            $date = date('d/m/Y H:i', $student->last_assign);
            echo "<option value='{$student->id}' $selected>{$student->firstname} {$student->lastname} ({$student->email}) - {$student->assign_count} competenze</option>";
        }
        echo '</select><br>';
        echo '<button type="submit" name="action" value="test_assignment" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
              📤 Test Notifica Assignment
              </button>';
        echo '<button type="submit" name="action" value="test_reminder" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
              📩 Test Notifica Reminder
              </button>';
        echo '</form>';
    }

    // ============================================
    // ESEGUI TEST SE RICHIESTO
    // ============================================
    if ($action && $userid && confirm_sesskey()) {
        $student = $DB->get_record('user', ['id' => $userid]);

        if ($student) {
            require_once($CFG->dirroot . '/lib/messagelib.php');

            echo '<hr style="margin: 20px 0;">';
            echo '<h4>Risultato Test:</h4>';

            if ($action === 'test_assignment') {
                // Test notifica assignment
                $url = new moodle_url('/local/selfassessment/compile.php');

                $messagedata = new stdClass();
                $messagedata->fullname = fullname($student);
                $messagedata->quizname = 'Quiz di Test';
                $messagedata->count = 3;
                $messagedata->url = $url->out();

                $message = new \core\message\message();
                $message->component = 'local_selfassessment';
                $message->name = 'assignment';
                $message->userfrom = core_user::get_noreply_user();
                $message->userto = $student;
                $message->subject = get_string('notification_assignment_subject', 'local_selfassessment');
                $message->fullmessage = get_string('notification_assignment_body', 'local_selfassessment', $messagedata);
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '';
                $message->smallmessage = get_string('notification_assignment_small', 'local_selfassessment', $messagedata);
                $message->notification = 1;
                $message->contexturl = $url;
                $message->contexturlname = get_string('myassessment', 'local_selfassessment');

                try {
                    $messageid = message_send($message);
                    if ($messageid) {
                        echo '<div class="result-ok">
                              ✓ <strong>NOTIFICA INVIATA!</strong><br><br>
                              <strong>Tipo:</strong> Assignment (nuove competenze)<br>
                              <strong>Destinatario:</strong> ' . fullname($student) . ' (' . $student->email . ')<br>
                              <strong>Message ID:</strong> ' . $messageid . '<br><br>
                              Lo studente dovrebbe vedere la notifica nel menu campanella 🔔 o ricevere email.
                              </div>';
                    } else {
                        echo '<div class="result-error">✗ message_send() ha restituito false/null</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="result-error">✗ ERRORE: ' . $e->getMessage() . '</div>';
                }

            } else if ($action === 'test_reminder') {
                // Test notifica reminder
                $coach = $DB->get_record('user', ['id' => $USER->id]);
                $url = new moodle_url('/local/selfassessment/compile.php');

                $messagedata = new stdClass();
                $messagedata->fullname = fullname($student);
                $messagedata->coachname = fullname($coach);
                $messagedata->message = 'Questo è un messaggio di test dal sistema di notifiche.';
                $messagedata->url = $url->out();

                $message = new \core\message\message();
                $message->component = 'local_selfassessment';
                $message->name = 'reminder';
                $message->userfrom = $coach;
                $message->userto = $student;
                $message->subject = get_string('notification_reminder_subject', 'local_selfassessment');
                $message->fullmessage = get_string('notification_reminder_body', 'local_selfassessment', $messagedata);
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '';
                $message->smallmessage = get_string('notification_reminder_small', 'local_selfassessment');
                $message->notification = 1;
                $message->contexturl = $url;
                $message->contexturlname = get_string('myassessment', 'local_selfassessment');

                try {
                    $messageid = message_send($message);
                    if ($messageid) {
                        echo '<div class="result-ok">
                              ✓ <strong>REMINDER INVIATO!</strong><br><br>
                              <strong>Tipo:</strong> Reminder dal coach<br>
                              <strong>Da:</strong> ' . fullname($coach) . '<br>
                              <strong>A:</strong> ' . fullname($student) . ' (' . $student->email . ')<br>
                              <strong>Message ID:</strong> ' . $messageid . '<br><br>
                              Lo studente dovrebbe vedere la notifica nel menu campanella 🔔 o ricevere email.
                              </div>';
                    } else {
                        echo '<div class="result-error">✗ message_send() ha restituito false/null</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="result-error">✗ ERRORE: ' . $e->getMessage() . '</div>';
                }
            }
        }
    }

    echo '</div>';

    // ============================================
    // 4. VERIFICA NOTIFICHE INVIATE
    // ============================================
    echo '<div class="test-section">';
    echo '<div class="test-title">4. Notifiche Recenti nel Sistema</div>';

    $recent_notifications = $DB->get_records_sql("
        SELECT n.id, n.useridfrom, n.useridto, n.subject, n.smallmessage, n.timecreated,
               uf.firstname as from_firstname, uf.lastname as from_lastname,
               ut.firstname as to_firstname, ut.lastname as to_lastname
        FROM {notifications} n
        LEFT JOIN {user} uf ON uf.id = n.useridfrom
        LEFT JOIN {user} ut ON ut.id = n.useridto
        WHERE n.component = 'local_selfassessment'
        ORDER BY n.timecreated DESC
        LIMIT 10
    ");

    if (empty($recent_notifications)) {
        echo '<div class="result-info">Nessuna notifica selfassessment trovata nel sistema.<br>
              Questo potrebbe significare che:<br>
              - Il plugin è stato appena aggiornato<br>
              - Le notifiche usano un altro sistema di storage<br>
              - Nessuna notifica è stata ancora inviata</div>';
    } else {
        echo '<p>Ultime 10 notifiche Self Assessment:</p>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr style="background: #f8f9fa;"><th style="padding: 10px; text-align: left;">Data</th><th style="padding: 10px; text-align: left;">Da</th><th style="padding: 10px; text-align: left;">A</th><th style="padding: 10px; text-align: left;">Oggetto</th></tr>';
        foreach ($recent_notifications as $notif) {
            $date = date('d/m/Y H:i', $notif->timecreated);
            $from = $notif->from_firstname ? "{$notif->from_firstname} {$notif->from_lastname}" : 'Sistema';
            $to = "{$notif->to_firstname} {$notif->to_lastname}";
            echo "<tr><td style='padding: 10px; border-bottom: 1px solid #eee;'>$date</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>$from</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>$to</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>{$notif->subject}</td></tr>";
        }
        echo '</table>';
    }

    echo '</div>';

    // ============================================
    // 5. ISTRUZIONI
    // ============================================
    echo '<div class="test-section">';
    echo '<div class="test-title">5. Istruzioni</div>';
    echo '<ol style="line-height: 2;">';
    echo '<li><strong>Prima di testare:</strong> Assicurati di aver eseguito l\'upgrade del plugin (Amministrazione > Notifiche)</li>';
    echo '<li><strong>Svuota cache:</strong> Amministrazione > Sviluppo > Svuota tutte le cache</li>';
    echo '<li><strong>Seleziona studente:</strong> Scegli uno studente dalla lista sopra</li>';
    echo '<li><strong>Invia test:</strong> Clicca uno dei pulsanti per inviare una notifica di test</li>';
    echo '<li><strong>Verifica:</strong> Accedi come lo studente e controlla la campanella 🔔</li>';
    echo '</ol>';
    echo '</div>';
    ?>

    <div style="text-align: center; padding: 20px; color: #666;">
        <a href="diagnose.php" style="color: #007bff; margin-right: 20px;">← Diagnosi Sistema</a>
        <a href="index.php" style="color: #007bff;">Dashboard →</a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
