<?php
// ============================================
// CoachManager - AJAX Send Invitation
// Invia inviti per autovalutazione agli studenti
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
$type = optional_param('type', 'autoval', PARAM_ALPHA); // autoval (default), altri tipi futuri

header('Content-Type: application/json');

try {
    global $DB, $USER, $CFG;

    // Verifica che lo studente esista
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);

    // Prepara il messaggio in base al tipo
    $subject = '';
    $bodytext = '';
    $bodyhtml = '';
    $contexturl = null;
    $contexturlname = '';

    switch ($type) {
        case 'autoval':
            // URL alla pagina di autovalutazione
            $selfassessment_url = new moodle_url('/local/selfassessment/index.php');

            $subject = get_string('invitation_autoval_subject', 'local_coachmanager');
            if (empty($subject) || $subject === '[[invitation_autoval_subject]]') {
                $subject = 'Invito: Completa la tua Autovalutazione';
            }

            // Corpo del messaggio - testo
            $bodytext = get_string('invitation_autoval_body', 'local_coachmanager', fullname($student));
            if (empty($bodytext) || strpos($bodytext, '[[') !== false) {
                $bodytext = "Ciao " . fullname($student) . ",\n\n"
                          . "Il tuo Coach ti invita a completare la tua autovalutazione delle competenze.\n\n"
                          . "L'autovalutazione e' uno strumento importante che ci aiuta a:\n"
                          . "- Capire meglio le tue competenze attuali\n"
                          . "- Personalizzare il tuo percorso formativo\n"
                          . "- Identificare le aree su cui lavorare insieme\n\n"
                          . "Per completare l'autovalutazione, accedi alla piattaforma e vai alla sezione Autovalutazione:\n"
                          . $selfassessment_url->out(false) . "\n\n"
                          . "Se hai domande, non esitare a contattarmi.\n\n"
                          . "Cordiali saluti,\n"
                          . fullname($USER) . "\n"
                          . "(Il tuo Coach)";
            }

            // Corpo del messaggio - HTML
            $bodyhtml = build_invitation_html($student, $USER, $selfassessment_url);

            $contexturl = $selfassessment_url;
            $contexturlname = get_string('selfassessment', 'local_coachmanager');
            if (empty($contexturlname) || strpos($contexturlname, '[[') !== false) {
                $contexturlname = 'Autovalutazione';
            }
            break;

        default:
            throw new Exception('Tipo di invito non valido: ' . $type);
    }

    $notification_sent = false;
    $email_sent = false;
    $messageid = null;

    // 1. Invia notifica Moodle
    try {
        $message = new \core\message\message();
        $message->component = 'local_coachmanager';
        $message->name = 'invitation';
        $message->userfrom = $USER;
        $message->userto = $student;
        $message->subject = $subject;
        $message->fullmessage = $bodytext;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = $subject;
        $message->notification = 1;

        if ($contexturl) {
            $message->contexturl = $contexturl;
            $message->contexturlname = $contexturlname;
        }

        $messageid = message_send($message);
        $notification_sent = !empty($messageid);
    } catch (Exception $e) {
        debugging('CoachManager: Failed to send Moodle notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $notification_sent = false;
    }

    // 2. Invia email come backup/complemento
    try {
        $email_sent = email_to_user(
            $student,
            $USER,
            $subject,
            $bodytext,
            $bodyhtml
        );
    } catch (Exception $e) {
        debugging('CoachManager: Failed to send email: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $email_sent = false;
    }

    // Verifica che almeno uno dei due metodi abbia funzionato
    if (!$notification_sent && !$email_sent) {
        throw new Exception(get_string('invitation_send_failed', 'local_coachmanager') ?: 'Impossibile inviare l\'invito. Riprova piu\' tardi.');
    }

    // Log dell'invito (opzionale: evento Moodle)
    $eventdata = [
        'context' => $context,
        'userid' => $USER->id,
        'relateduserid' => $studentid,
        'other' => [
            'type' => $type,
            'notification_sent' => $notification_sent,
            'email_sent' => $email_sent
        ]
    ];

    // Trigger event if exists
    // \local_coachmanager\event\invitation_sent::create($eventdata)->trigger();

    // Prepara messaggio di risposta
    $method = [];
    if ($notification_sent) {
        $method[] = 'notifica';
    }
    if ($email_sent) {
        $method[] = 'email';
    }

    $success_message = get_string('invitation_sent', 'local_coachmanager');
    if (empty($success_message) || strpos($success_message, '[[') !== false) {
        $success_message = 'Invito inviato con successo';
    }
    $success_message .= ' (' . implode(' + ', $method) . ')';

    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'data' => [
            'studentid' => $studentid,
            'studentname' => fullname($student),
            'type' => $type,
            'notification_sent' => $notification_sent,
            'email_sent' => $email_sent,
            'messageid' => $messageid
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Costruisce il corpo HTML dell'invito per autovalutazione
 *
 * @param object $student Oggetto utente studente
 * @param object $coach Oggetto utente coach (mittente)
 * @param moodle_url $url URL alla pagina di autovalutazione
 * @return string HTML del messaggio
 */
function build_invitation_html($student, $coach, $url) {
    $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

    // Header
    $html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px 8px 0 0; text-align: center;">';
    $html .= '<h1 style="margin: 0; font-size: 24px;">Invito Autovalutazione</h1>';
    $html .= '<p style="margin: 10px 0 0 0; opacity: 0.9;">Percorso Formativo FTM</p>';
    $html .= '</div>';

    // Body
    $html .= '<div style="background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; border-top: none;">';

    $html .= '<p style="color: #333; font-size: 16px; margin-top: 0;">Ciao <strong>' . s(fullname($student)) . '</strong>,</p>';

    $html .= '<p style="color: #555; line-height: 1.6;">Il tuo Coach ti invita a completare la tua <strong>autovalutazione delle competenze</strong>.</p>';

    // Info box
    $html .= '<div style="background: white; border-left: 4px solid #667eea; padding: 15px 20px; margin: 20px 0; border-radius: 0 4px 4px 0;">';
    $html .= '<p style="margin: 0 0 10px 0; color: #333; font-weight: bold;">Perche\' e\' importante?</p>';
    $html .= '<ul style="margin: 0; padding-left: 20px; color: #555;">';
    $html .= '<li style="margin-bottom: 5px;">Capire meglio le tue competenze attuali</li>';
    $html .= '<li style="margin-bottom: 5px;">Personalizzare il tuo percorso formativo</li>';
    $html .= '<li style="margin-bottom: 5px;">Identificare le aree su cui lavorare insieme</li>';
    $html .= '</ul>';
    $html .= '</div>';

    // CTA Button
    $html .= '<div style="text-align: center; margin: 30px 0;">';
    $html .= '<a href="' . s($url->out(false)) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 15px 40px; border-radius: 30px; font-size: 16px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">Completa Autovalutazione</a>';
    $html .= '</div>';

    $html .= '<p style="color: #555; line-height: 1.6;">Se hai domande, non esitare a contattarmi.</p>';

    // Firma
    $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">';
    $html .= '<p style="color: #333; margin: 0;">Cordiali saluti,</p>';
    $html .= '<p style="color: #667eea; font-weight: bold; margin: 5px 0;">' . s(fullname($coach)) . '</p>';
    $html .= '<p style="color: #888; font-size: 14px; margin: 0;">Il tuo Coach</p>';
    $html .= '</div>';

    $html .= '</div>';

    // Footer
    $html .= '<div style="background: #343a40; color: #adb5bd; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px;">';
    $html .= '<p style="margin: 0;">Questo messaggio e\' stato inviato automaticamente dalla piattaforma FTM.</p>';
    $html .= '<p style="margin: 5px 0 0 0;">Se non riesci a cliccare il pulsante, copia questo link: ' . s($url->out(false)) . '</p>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}
