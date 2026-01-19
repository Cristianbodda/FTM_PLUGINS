<?php
// ============================================
// CoachManager - AJAX Send Reminder
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->libdir . '/messagelib.php');
require_once('lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/coachmanager:edit', $context);

$studentid = required_param('studentid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA); // autoval, lab, quiz

header('Content-Type: application/json');

try {
    // Verifica che lo studente esista
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

    // Prepara il messaggio in base al tipo
    $subject = '';
    $body = '';

    switch ($type) {
        case 'autoval':
            $subject = get_string('reminder_autoval_subject', 'local_coachmanager');
            $body = get_string('reminder_autoval_body', 'local_coachmanager', fullname($student));
            if (empty($subject)) {
                $subject = 'Promemoria: Completa la tua Autovalutazione';
            }
            if (empty($body)) {
                $body = "Ciao " . fullname($student) . ",\n\n"
                      . "Ti ricordiamo di completare la tua autovalutazione delle competenze.\n\n"
                      . "L'autovalutazione ci aiuta a personalizzare il tuo percorso formativo.\n\n"
                      . "Accedi alla piattaforma e vai nella sezione Autovalutazione.\n\n"
                      . "Cordiali saluti,\n"
                      . fullname($USER) . "\n"
                      . "(Il tuo Coach)";
            }
            break;

        case 'lab':
            $subject = 'Promemoria: Valutazione Laboratorio';
            $body = "Ciao " . fullname($student) . ",\n\n"
                  . "Ti ricordiamo che hai una valutazione di laboratorio in sospeso.\n\n"
                  . "Contatta il tuo formatore per completare la valutazione.\n\n"
                  . "Cordiali saluti,\n"
                  . fullname($USER) . "\n"
                  . "(Il tuo Coach)";
            break;

        case 'quiz':
            $subject = 'Promemoria: Completa i Quiz';
            $body = "Ciao " . fullname($student) . ",\n\n"
                  . "Ti ricordiamo di completare i quiz di valutazione assegnati.\n\n"
                  . "I quiz sono importanti per verificare le tue competenze.\n\n"
                  . "Accedi alla piattaforma e vai nella sezione Quiz.\n\n"
                  . "Cordiali saluti,\n"
                  . fullname($USER) . "\n"
                  . "(Il tuo Coach)";
            break;

        default:
            throw new Exception('Tipo di promemoria non valido');
    }

    // Invia il messaggio usando il sistema di messaggistica Moodle
    $message = new \core\message\message();
    $message->component = 'local_coachmanager';
    $message->name = 'reminder';
    $message->userfrom = $USER;
    $message->userto = $student;
    $message->subject = $subject;
    $message->fullmessage = $body;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = nl2br(s($body));
    $message->smallmessage = $subject;
    $message->notification = 1;
    $message->contexturl = new moodle_url('/local/coachmanager/');
    $message->contexturlname = get_string('coachmanager', 'local_coachmanager');

    // Prova a inviare il messaggio
    $messageid = message_send($message);

    if ($messageid) {
        // Log evento
        $event = \core\event\message_sent::create([
            'objectid' => $messageid,
            'userid' => $USER->id,
            'context' => $context,
            'relateduserid' => $studentid,
            'other' => [
                'type' => $type
            ]
        ]);
        // $event->trigger(); // Commentato per evitare errori se l'evento non esiste

        echo json_encode([
            'success' => true,
            'message' => get_string('reminder_sent', 'local_coachmanager'),
            'data' => [
                'studentid' => $studentid,
                'type' => $type,
                'messageid' => $messageid
            ]
        ]);
    } else {
        // Fallback: prova a inviare via email
        $sent = email_to_user(
            $student,
            $USER,
            $subject,
            $body
        );

        if ($sent) {
            echo json_encode([
                'success' => true,
                'message' => get_string('reminder_sent', 'local_coachmanager') . ' (via email)',
                'data' => [
                    'studentid' => $studentid,
                    'type' => $type,
                    'method' => 'email'
                ]
            ]);
        } else {
            throw new Exception('Impossibile inviare il promemoria');
        }
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
