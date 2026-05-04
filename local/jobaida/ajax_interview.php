<?php
/**
 * AJAX endpoint for interview simulation chat.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    // Authorization check: capability OR jobaida_auth table OR siteadmin.
    $isauthorized = false;
    if (is_siteadmin()) {
        $isauthorized = true;
    } else if (has_capability('local/jobaida:use', $context)) {
        $isauthorized = true;
    } else {
        $auth = $DB->get_record('local_jobaida_auth', ['userid' => $USER->id, 'active' => 1]);
        if ($auth) {
            $isauthorized = true;
        }
    }
    if (!$isauthorized) {
        throw new Exception(get_string('not_authorized', 'local_jobaida'));
    }

    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {

        // ========== START: Create new interview session ==========
        case 'start':
            $jobad = required_param('job_ad', PARAM_RAW);
            $cv = required_param('cv_text', PARAM_RAW);
            $accesscode = required_param('access_code', PARAM_RAW);

            if (trim($accesscode) !== '6807') {
                throw new Exception('Codice di accesso non valido.');
            }

            if (empty(trim($jobad)) || empty(trim($cv))) {
                throw new Exception('Annuncio e CV sono obbligatori.');
            }

            $language = get_config('local_jobaida', 'letter_language') ?: 'it';
            $langnames = ['it' => 'italiano', 'de' => 'tedesco', 'fr' => 'francese', 'en' => 'inglese'];
            $langname = $langnames[$language] ?? 'italiano';

            // Build system prompt for the HR interviewer.
            $systemprompt = "Sei un responsabile risorse umane (HR) di un'azienda svizzera. "
                . "Stai conducendo un colloquio di lavoro REALE con un candidato che ha risposto al tuo annuncio. "
                . "Parla in {$langname}.\n\n"
                . "=== ANNUNCIO DI LAVORO (che hai scritto tu) ===\n" . trim($jobad) . "\n\n"
                . "=== CV DEL CANDIDATO ===\n" . trim($cv) . "\n\n"
                . "REGOLE DEL COLLOQUIO:\n"
                . "1. Conduci il colloquio in modo REALISTICO, professionale ma cordiale.\n"
                . "2. Fai UNA domanda alla volta. Aspetta la risposta prima di procedere.\n"
                . "3. Reagisci NATURALMENTE alle risposte: fai follow-up, approfondisci, mostra interesse.\n"
                . "4. Basa le domande sui DATI REALI dell'annuncio e del CV.\n"
                . "5. Il colloquio ha 12 domande totali, distribuite cosi:\n"
                . "   - Domanda 1: Accoglienza (presentazione personale)\n"
                . "   - Domande 2-3: Motivazione (perche questo ruolo/azienda)\n"
                . "   - Domande 4-5: Esperienza professionale (dal CV)\n"
                . "   - Domande 6-7: Competenze tecniche (requisiti annuncio)\n"
                . "   - Domande 8-9: Competenze trasversali (soft skills, esempi concreti)\n"
                . "   - Domande 10-11: Aspettative (salario, disponibilita, obiettivi)\n"
                . "   - Domanda 12: Chiusura (domande del candidato)\n"
                . "6. NON rivelare mai che sei un'AI. Comportati come un vero HR.\n"
                . "7. DOMANDE ADATTIVE (fondamentale per rendere il colloquio formativo):\n"
                . "   - Se il candidato risponde in modo VAGO o GENERICO (es. 'sono bravo nel lavoro di squadra' "
                . "senza esempio concreto), NON passare alla domanda successiva. Chiedi: "
                . "'Puo farmi un esempio concreto?' oppure 'In che situazione specifica?'\n"
                . "   - Se il candidato risponde in modo troppo BREVE (meno di 2 frasi), approfondisci: "
                . "'Puo elaborare un po'?' oppure 'Cosa intende esattamente?'\n"
                . "   - Se il candidato non risponde alla domanda (cambia argomento o divaga), "
                . "riportalo gentilmente al tema: 'Capisco, ma tornando alla mia domanda...'\n"
                . "   - I follow-up NON contano come nuove domande: il conteggio delle 12 domande "
                . "avanza solo quando passi a un nuovo argomento.\n"
                . "8. Alla fine della domanda 12, scrivi '---FINE_COLLOQUIO---' su una riga separata.\n\n"
                . "Rispondi SOLO con il testo della conversazione (come un HR reale). "
                . "Inizia presentandoti con nome e ruolo, saluta il candidato e fai la prima domanda.";

            // Create interview session in DB.
            $session = new stdClass();
            $session->userid = $USER->id;
            $session->job_ad = $jobad;
            $session->cv_text = $cv;
            $session->language = $language;
            $session->question_count = 0;
            $session->status = 'active';
            $session->system_prompt = $systemprompt;
            $session->conversation = json_encode([]);
            $session->timecreated = time();
            $session->timemodified = time();
            $sessionid = $DB->insert_record('local_jobaida_interviews', $session);

            // Call OpenAI for the first message (HR introduction + first question).
            $apikey = get_config('local_jobaida', 'openai_apikey');
            $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';

            if (empty($apikey)) {
                throw new Exception('Chiave API OpenAI non configurata.');
            }

            $messages = [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => 'Inizia il colloquio. Presentati e fai la prima domanda.'],
            ];

            $useaudio = (get_config('local_jobaida', 'tts_provider') !== 'browser');
            $result = call_openai($apikey, $model, $messages, 800, $useaudio);

            // Save conversation.
            $conversation = [
                ['role' => 'assistant', 'content' => $result['text'], 'timestamp' => time()],
            ];
            $DB->set_field('local_jobaida_interviews', 'conversation', json_encode($conversation), ['id' => $sessionid]);
            $DB->set_field('local_jobaida_interviews', 'question_count', 1, ['id' => $sessionid]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'session_id' => $sessionid,
                    'message' => $result['text'],
                    'audio' => $result['audio'],
                    'question_number' => 1,
                    'total_questions' => 12,
                    'finished' => false,
                ],
            ]);
            break;

        // ========== REPLY: Send candidate's answer and get next question ==========
        case 'reply':
            $sessionid = required_param('session_id', PARAM_INT);
            $answer = required_param('answer', PARAM_RAW);

            if (empty(trim($answer))) {
                throw new Exception('Scrivi una risposta prima di inviarla.');
            }

            // Load session.
            $session = $DB->get_record('local_jobaida_interviews', ['id' => $sessionid, 'userid' => $USER->id]);
            if (!$session) {
                throw new Exception('Sessione non trovata.');
            }
            if ($session->status !== 'active') {
                throw new Exception('Questo colloquio e gia terminato.');
            }

            $conversation = json_decode($session->conversation, true) ?: [];
            $systemprompt = $session->system_prompt;

            // Add candidate's answer.
            $conversation[] = ['role' => 'user', 'content' => trim($answer), 'timestamp' => time()];

            // Build messages for OpenAI.
            $messages = [['role' => 'system', 'content' => $systemprompt]];
            // Add the initial trigger.
            $messages[] = ['role' => 'user', 'content' => 'Inizia il colloquio. Presentati e fai la prima domanda.'];
            // Add conversation history.
            foreach ($conversation as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            $questionNum = $session->question_count + 1;

            // Add instruction for current phase.
            if ($questionNum >= 12) {
                $messages[] = ['role' => 'system', 'content' => "Questa e l'ultima domanda (12/12). "
                    . "Chiedi al candidato se ha domande per te. Dopo la sua risposta, "
                    . "concludi il colloquio ringraziandolo e dicendo che lo ricontatterete. "
                    . "Alla fine scrivi '---FINE_COLLOQUIO---' su una riga separata."];
            }

            $apikey = get_config('local_jobaida', 'openai_apikey');
            $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';
            $useaudio = (get_config('local_jobaida', 'tts_provider') !== 'browser');
            $result = call_openai($apikey, $model, $messages, 800, $useaudio);

            $aitext = $result['text'];

            // Add AI response.
            $conversation[] = ['role' => 'assistant', 'content' => $aitext, 'timestamp' => time()];

            // Check if interview is finished.
            $finished = (strpos($aitext, '---FINE_COLLOQUIO---') !== false);
            if ($finished) {
                $aitext = str_replace('---FINE_COLLOQUIO---', '', $aitext);
                $aitext = trim($aitext);
                $conversation[count($conversation) - 1]['content'] = $aitext;
            }

            // Save.
            $DB->set_field('local_jobaida_interviews', 'conversation', json_encode($conversation), ['id' => $sessionid]);
            $DB->set_field('local_jobaida_interviews', 'question_count', $questionNum, ['id' => $sessionid]);
            $DB->set_field('local_jobaida_interviews', 'timemodified', time(), ['id' => $sessionid]);

            if ($finished) {
                $DB->set_field('local_jobaida_interviews', 'status', 'completed', ['id' => $sessionid]);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'session_id' => $sessionid,
                    'message' => $aitext,
                    'audio' => $result['audio'],
                    'question_number' => $questionNum,
                    'total_questions' => 12,
                    'finished' => $finished,
                ],
            ]);
            break;

        // ========== EVALUATE: Generate final evaluation ==========
        case 'evaluate':
            $sessionid = required_param('session_id', PARAM_INT);

            $session = $DB->get_record('local_jobaida_interviews', ['id' => $sessionid, 'userid' => $USER->id]);
            if (!$session) {
                throw new Exception('Sessione non trovata.');
            }

            $conversation = json_decode($session->conversation, true) ?: [];
            $language = $session->language ?: 'it';
            $langnames = ['it' => 'italiano', 'de' => 'tedesco', 'fr' => 'francese', 'en' => 'inglese'];
            $langname = $langnames[$language] ?? 'italiano';

            // Build evaluation prompt.
            $evalPrompt = "Sei un esperto di selezione del personale. Hai appena assistito a un colloquio simulato. "
                . "Rispondi in {$langname}.\n\n"
                . "=== ANNUNCIO ===\n" . $session->job_ad . "\n\n"
                . "=== CV CANDIDATO ===\n" . $session->cv_text . "\n\n"
                . "=== TRASCRIZIONE COLLOQUIO ===\n";

            foreach ($conversation as $msg) {
                $role = $msg['role'] === 'assistant' ? 'HR' : 'CANDIDATO';
                $evalPrompt .= "{$role}: {$msg['content']}\n\n";
            }

            $evalPrompt .= "=== ISTRUZIONI ===\n"
                . "Valuta la performance del candidato in modo FORMATIVO. Rispondi con JSON puro.\n"
                . "IMPORTANTE: nella sezione 'feedback_per_domanda', analizza OGNI coppia domanda-risposta. "
                . "Per ciascuna indica cosa ha funzionato, cosa mancava, e come avrebbe dovuto rispondere "
                . "(risposta ideale breve ma concreta, basata sui dati reali dell'annuncio e del CV). "
                . "Verifica anche se ha usato il metodo STAR (Situazione-Task-Azione-Risultato) per le "
                . "domande su esperienza e soft skills.\n\n"
                . "Formato JSON richiesto:\n"
                . "{\n"
                . "  \"punteggio_globale\": 7.5,\n"
                . "  \"valutazioni\": {\n"
                . "    \"presentazione\": {\"voto\": 8, \"commento\": \"...\"},\n"
                . "    \"motivazione\": {\"voto\": 7, \"commento\": \"...\"},\n"
                . "    \"esperienza\": {\"voto\": 7, \"commento\": \"...\"},\n"
                . "    \"competenze_tecniche\": {\"voto\": 6, \"commento\": \"...\"},\n"
                . "    \"soft_skills\": {\"voto\": 8, \"commento\": \"...\"},\n"
                . "    \"aspettative\": {\"voto\": 7, \"commento\": \"...\"}\n"
                . "  },\n"
                . "  \"feedback_per_domanda\": [\n"
                . "    {\n"
                . "      \"domanda\": \"La domanda posta dall'HR\",\n"
                . "      \"risposta\": \"Sintesi della risposta del candidato\",\n"
                . "      \"voto\": 7,\n"
                . "      \"cosa_ha_funzionato\": \"Aspetto positivo specifico\",\n"
                . "      \"cosa_mancava\": \"Cosa avrebbe dovuto aggiungere o fare diversamente\",\n"
                . "      \"risposta_ideale\": \"Come avrebbe dovuto rispondere (breve, 2-3 frasi)\",\n"
                . "      \"star_usato\": true\n"
                . "    }\n"
                . "  ],\n"
                . "  \"punti_forza\": [\"punto 1\", \"punto 2\", \"punto 3\"],\n"
                . "  \"aree_miglioramento\": [\"area 1\", \"area 2\", \"area 3\"],\n"
                . "  \"consiglio_finale\": \"Un consiglio concreto per il prossimo colloquio reale\",\n"
                . "  \"probabilita_assunzione\": \"alta/media/bassa\"\n"
                . "}";

            $apikey = get_config('local_jobaida', 'openai_apikey');
            $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';

            $messages = [
                ['role' => 'system', 'content' => 'Sei un valutatore esperto di colloqui di lavoro. Rispondi SOLO con JSON valido.'],
                ['role' => 'user', 'content' => $evalPrompt],
            ];

            $airesult = call_openai($apikey, $model, $messages, 4000);
            $aitext = $airesult['text'];

            // Parse JSON (strip markdown fences if present).
            $aitext = preg_replace('/^```json\s*/i', '', $aitext);
            $aitext = preg_replace('/\s*```$/', '', $aitext);
            $evaluation = json_decode(trim($aitext), true);

            if (!$evaluation || !isset($evaluation['punteggio_globale'])) {
                throw new Exception('Errore nella valutazione AI.');
            }

            // Save evaluation.
            $DB->set_field('local_jobaida_interviews', 'evaluation', json_encode($evaluation), ['id' => $sessionid]);
            $DB->set_field('local_jobaida_interviews', 'status', 'evaluated', ['id' => $sessionid]);

            echo json_encode([
                'success' => true,
                'data' => $evaluation,
            ]);
            break;

        // ========== HISTORY: Get past interviews ==========
        case 'history':
            // Coaches/admin see all interviews, students see only their own.
            $canviewall = has_capability('local/jobaida:authorize', $context) || is_siteadmin();
            $viewuserid = optional_param('userid', 0, PARAM_INT);

            if ($canviewall && $viewuserid > 0) {
                $conditions = ['userid' => $viewuserid];
            } else if ($canviewall) {
                $conditions = []; // All interviews.
            } else {
                $conditions = ['userid' => $USER->id];
            }

            $interviews = $DB->get_records('local_jobaida_interviews',
                $conditions,
                'timecreated DESC',
                'id, userid, status, question_count, language, timecreated, evaluation',
                0, 50
            );

            $list = [];
            foreach ($interviews as $iv) {
                $eval = json_decode($iv->evaluation, true);
                $entry = [
                    'id' => $iv->id,
                    'date' => userdate($iv->timecreated, '%d/%m/%Y %H:%M'),
                    'status' => $iv->status,
                    'questions' => $iv->question_count,
                    'score' => $eval['punteggio_globale'] ?? null,
                    'probability' => $eval['probabilita_assunzione'] ?? null,
                ];
                // Add student name for coaches.
                if ($canviewall) {
                    $ivuser = $DB->get_record('user', ['id' => $iv->userid], 'id, firstname, lastname');
                    $entry['student'] = $ivuser ? fullname($ivuser) : 'ID ' . $iv->userid;
                }
                $list[] = $entry;
            }

            echo json_encode(['success' => true, 'data' => $list, 'canviewall' => $canviewall]);
            break;

        // ========== LOAD: Load a past interview for review ==========
        case 'load':
            $sessionid = required_param('session_id', PARAM_INT);

            // Coaches/admin can view any interview.
            $canviewall = has_capability('local/jobaida:authorize', $context) || is_siteadmin();
            if ($canviewall) {
                $session = $DB->get_record('local_jobaida_interviews', ['id' => $sessionid]);
            } else {
                $session = $DB->get_record('local_jobaida_interviews', ['id' => $sessionid, 'userid' => $USER->id]);
            }
            if (!$session) {
                throw new Exception('Colloquio non trovato.');
            }

            $conversation = json_decode($session->conversation, true) ?: [];
            $evaluation = json_decode($session->evaluation, true);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $session->id,
                    'date' => userdate($session->timecreated, '%d/%m/%Y %H:%M'),
                    'status' => $session->status,
                    'question_count' => $session->question_count,
                    'conversation' => $conversation,
                    'evaluation' => $evaluation,
                ],
            ]);
            break;

        default:
            throw new Exception('Azione non valida.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();

/**
 * Call OpenAI chat completion API.
 * If $withaudio is true, uses gpt-4o-audio-preview to get audio+text in one call.
 *
 * @return array ['text' => string, 'audio' => string|null (base64 mp3)]
 */
function call_openai($apikey, $model, $messages, $maxtokens = 800, $withaudio = false) {
    $voice = get_config('local_jobaida', 'tts_openai_voice') ?: 'onyx';

    $payload = [
        'model' => $withaudio ? 'gpt-4o-audio-preview' : $model,
        'messages' => $messages,
        'max_tokens' => $maxtokens,
        'temperature' => 0.6,
    ];

    if ($withaudio) {
        $payload['modalities'] = ['text', 'audio'];
        $payload['audio'] = [
            'voice' => $voice,
            'format' => 'mp3',
        ];
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Errore connessione: ' . $error);
    }
    if ($httpcode !== 200) {
        $errordata = json_decode($response, true);
        throw new Exception('OpenAI: ' . ($errordata['error']['message'] ?? "HTTP {$httpcode}"));
    }

    $data = json_decode($response, true);
    $message = $data['choices'][0]['message'] ?? [];

    $result = ['text' => '', 'audio' => null];

    // Extract text: from audio transcript or content.
    if (isset($message['audio']['transcript'])) {
        $result['text'] = trim($message['audio']['transcript']);
    } else {
        $result['text'] = trim($message['content'] ?? '');
    }

    // Extract audio (base64 mp3).
    if (isset($message['audio']['data'])) {
        $result['audio'] = $message['audio']['data'];
    }

    return $result;
}
