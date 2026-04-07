<?php
/**
 * Generate a single AIDA section for Learn & Write mode.
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
    // Check authorization.
    $canuse = has_capability('local/jobaida:use', $context);
    $isauthorized = $DB->record_exists('local_jobaida_auth', ['userid' => $USER->id, 'active' => 1]);
    if (!$canuse && !$isauthorized && !is_siteadmin()) {
        throw new Exception(get_string('not_authorized', 'local_jobaida'));
    }

    $jobad = required_param('job_ad', PARAM_RAW);
    $cv = required_param('cv_text', PARAM_RAW);
    $objectives = optional_param('objectives', '', PARAM_RAW);
    $section = required_param('section', PARAM_ALPHA); // attention, interest, desire, action
    $previous_sections = optional_param('previous_sections', '{}', PARAM_RAW);
    $user_feedback = optional_param('user_feedback', '', PARAM_RAW);

    if (empty(trim($jobad)) || empty(trim($cv))) {
        throw new Exception(get_string('error_empty_fields', 'local_jobaida'));
    }

    $valid_sections = ['attention', 'interest', 'desire', 'action', 'assemble'];
    if (!in_array($section, $valid_sections)) {
        throw new Exception('Sezione non valida');
    }

    $language = get_config('local_jobaida', 'letter_language') ?: 'it';
    $langnames = ['it' => 'italiano', 'de' => 'tedesco', 'fr' => 'francese', 'en' => 'inglese'];
    $langname = $langnames[$language] ?? 'italiano';

    $today = date('d.m.Y');

    // Decode previous sections.
    $prev = json_decode($previous_sections, true) ?: [];

    $section_labels = [
        'attention' => 'ATTENTION (Cattura l\'attenzione)',
        'interest' => 'INTEREST (Suscita interesse)',
        'desire' => 'DESIRE (Crea il desiderio)',
        'action' => 'ACTION (Invito all\'azione)',
    ];

    $section_instructions = [
        'attention' => "Genera SOLO la sezione ATTENTION della lettera di candidatura.\n"
            . "- MASSIMO 50-70 parole per questa sezione.\n"
            . "- INIZIA OBBLIGATORIAMENTE con 'Spettabile [Nome Azienda],' (estrai il nome dall'annuncio).\n"
            . "- Subito dopo, sulla riga successiva, scrivi il paragrafo di apertura che cattura l'attenzione.\n"
            . "- Identifica l'elemento PIU RILEVANTE dell'annuncio.\n"
            . "- Collegalo a un'esperienza CONCRETA dal CV del candidato.\n"
            . "- NON usare aperture banali come 'Con la presente...', 'Mi permetto di...'.\n"
            . "- Cita specificamente nomi di aziende, ruoli e durate dal CV.",

        'interest' => "Genera SOLO la sezione INTEREST della lettera di candidatura.\n"
            . "- MASSIMO 80-100 parole per questa sezione.\n"
            . "- Prendi OGNI requisito chiave dall'annuncio.\n"
            . "- Trova la competenza/esperienza CORRISPONDENTE nel CV.\n"
            . "- Cita specificamente: nome azienda, ruolo svolto, durata, risultati.\n"
            . "- Se un requisito non e coperto, menziona competenze trasferibili.\n"
            . "- Almeno 3 match concreti tra annuncio e CV.",

        'desire' => "Genera SOLO la sezione DESIRE della lettera di candidatura.\n"
            . "- MASSIMO 50-70 parole per questa sezione.\n"
            . "- Usa gli obiettivi personali del candidato.\n"
            . "- Crea un collegamento autentico con l'azienda.\n"
            . "- Se l'annuncio menziona valori aziendali, collegali ai valori del candidato.\n"
            . "- Mostra come il candidato si vede crescere IN QUESTA azienda.",

        'action' => "Genera SOLO la sezione ACTION della lettera di candidatura.\n"
            . "- MASSIMO 40-60 parole per questa sezione.\n"
            . "- Proponi un'azione CONCRETA: colloquio, prova pratica, stage.\n"
            . "- Mostra proattivita senza arroganza.\n"
            . "- Includi disponibilita specifica.\n"
            . "- CONCLUDI OBBLIGATORIAMENTE con formula di saluto (es. 'Ringraziandovi per l'attenzione, porgo i miei piu cordiali saluti.').\n"
            . "- NON aggiungere nome e firma alla fine (vengono aggiunti automaticamente nell'assemblaggio).",
    ];

    // Handle assembly separately.
    if ($section === 'assemble') {
        // Assemble full letter from confirmed sections with proper Swiss format.
        $systemprompt_asm = "Sei un career coach formatore. Il tuo compito e assemblare una lettera di candidatura completa "
            . "in formato svizzero formale, usando le 4 sezioni AIDA gia confermate dallo studente. Rispondi in {$langname}.\n\n"
            . "DATA DI OGGI: {$today}\n\n"
            . "FORMATO LETTERA OBBLIGATORIO (ogni elemento su RIGA SEPARATA, usa \\n per andare a capo):\n\n"
            . "[Nome Cognome]\n"
            . "[Indirizzo], [CAP] [Citta]\n"
            . "[Numero di telefono]\n"
            . "[Indirizzo email]\n"
            . "\n"
            . "[Citta], {$today}\n"
            . "\n"
            . "Spettabile [Nome Azienda],\n"
            . "[corpo lettera AIDA fluido]\n"
            . "\n"
            . "[Formula di chiusura saluti]\n\n"
            . "REGOLE:\n"
            . "1. Estrai nome, cognome, indirizzo, citta, telefono, email dal CV. Se mancano, usa [placeholder tra parentesi quadre].\n"
            . "2. Estrai nome azienda dall'annuncio.\n"
            . "3. Le 4 sezioni AIDA devono essere collegate in modo fluido come un unico testo coerente.\n"
            . "4. NON modificare il contenuto delle sezioni, solo collegarle con transizioni naturali.\n"
            . "5. I dati mancanti vanno SEMPRE tra [parentesi quadre] come placeholder.\n"
            . "6. La data e OGGI: {$today}. NON inventare date diverse.\n"
            . "7. Il CORPO della lettera (escluse intestazioni) NON deve superare 300 parole.\n"
            . "8. La prima sezione ATTENTION contiene gia 'Spettabile [Azienda],' come apertura - NON duplicarlo.\n\n"
            . "Rispondi con JSON puro:\n"
            . "{\"full_letter\": \"La lettera completa formattata\"}";

        $userprompt_asm = "=== ANNUNCIO DI LAVORO ===\n" . trim($jobad) . "\n\n"
            . "=== CV DEL CANDIDATO ===\n" . trim($cv) . "\n\n"
            . "=== SEZIONI AIDA CONFERMATE DALLO STUDENTE ===\n";
        foreach ($prev as $key => $val) {
            $label = $section_labels[$key] ?? strtoupper($key);
            $userprompt_asm .= $label . ":\n" . $val . "\n\n";
        }
        $userprompt_asm .= "Assembla la lettera completa in formato svizzero. Rispondi SOLO con il JSON.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt_asm],
                ['role' => 'user', 'content' => $userprompt_asm],
            ],
            'max_tokens' => 2000,
            'temperature' => 0.3,
        ];

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
            throw new Exception('cURL: ' . $error);
        }
        if ($httpcode !== 200) {
            $errordata = json_decode($response, true);
            throw new Exception($errordata['error']['message'] ?? "HTTP {$httpcode}");
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $result = json_decode($content, true);
        if (!$result || !isset($result['full_letter'])) {
            throw new Exception('Risposta AI non valida per assemblaggio');
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'section' => 'assemble',
                'full_letter' => $result['full_letter'],
            ],
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
        ]);
        die();
    }

    // Build system prompt for individual sections.
    $has_feedback = !empty(trim($user_feedback));

    $systemprompt = "Sei un career coach formatore. Stai guidando uno studente nella scrittura di una lettera di candidatura, "
        . "sezione per sezione, usando il modello AIDA. Rispondi in {$langname}.\n\n"
        . "DATA DI OGGI: {$today}\n\n"
        . "REGOLE:\n"
        . "1. Genera SOLO la sezione richiesta, NON tutta la lettera.\n"
        . "2. Basa tutto su dati REALI dal CV. NON inventare.\n"
        . "3. Cita specificamente dall'annuncio e dal CV.\n"
        . "4. Il rationale deve essere EDUCATIVO: spiega allo studente PERCHE hai fatto quelle scelte, "
        . "come se fossi il suo tutor.\n"
        . "5. La domanda di conferma deve stimolare la RIFLESSIONE dello studente.\n"
        . "6. I dati mancanti vanno come [placeholder] tra parentesi quadre.\n"
        . "7. LIMITE TASSATIVO: la lettera completa (corpo, senza intestazioni) NON deve MAI superare 300 parole. Sii conciso e incisivo.\n";

    if ($has_feedback) {
        $systemprompt .= "\n=== REGOLA CRITICA SUL FEEDBACK ===\n"
            . "Lo studente ha fornito un FEEDBACK SPECIFICO per riscrivere questa sezione.\n"
            . "- Se il feedback e PERTINENTE e sensato: DEVI riscrivere il testo incorporando TUTTE le richieste dello studente. "
            . "Imposta feedback_applied=true.\n"
            . "- Se il feedback e inappropriato, senza senso, non pertinente alla sezione, o impossibile da applicare: "
            . "NON modificare il testo, imposta feedback_applied=false e spiega in feedback_note PERCHE il feedback non e applicabile.\n"
            . "Il feedback dello studente ha la PRIORITA MASSIMA quando e pertinente.\n";
    }

    $systemprompt .= "\nRispondi con JSON puro:\n"
        . "{\n"
        . "  \"section_text\": \"Il testo della sezione\",\n"
        . "  \"rationale\": \"Spiegazione educativa dettagliata di PERCHE hai scritto cosi\",\n"
        . "  \"question\": \"Domanda di riflessione per lo studente\",\n"
        . "  \"tips\": [\"Suggerimento formativo 1\", \"Suggerimento formativo 2\"]"
        . ($has_feedback ? ",\n  \"feedback_applied\": true,\n  \"feedback_note\": \"\"" : "")
        . "\n}";

    // Build user prompt.
    $userprompt = "=== ANNUNCIO DI LAVORO ===\n" . trim($jobad) . "\n\n"
        . "=== CV DEL CANDIDATO ===\n" . trim($cv) . "\n\n";

    if (!empty(trim($objectives))) {
        $userprompt .= "=== OBIETTIVI PERSONALI ===\n" . trim($objectives) . "\n\n";
    }

    // Add previous confirmed sections for context.
    if (!empty($prev)) {
        $userprompt .= "=== SEZIONI GIA CONFERMATE ===\n";
        foreach ($prev as $key => $val) {
            $label = $section_labels[$key] ?? strtoupper($key);
            $userprompt .= $label . ":\n" . $val . "\n\n";
        }
    }

    $userprompt .= "GENERA LA SEZIONE: " . $section_labels[$section] . "\n\n"
        . $section_instructions[$section] . "\n\n";

    // Add user feedback AFTER section instructions so it has highest priority.
    if (!empty(trim($user_feedback))) {
        $userprompt .= "=== ATTENZIONE: FEEDBACK DELLO STUDENTE (PRIORITA MASSIMA) ===\n"
            . "Lo studente ha chiesto ESPLICITAMENTE queste modifiche:\n"
            . "\"" . trim($user_feedback) . "\"\n\n"
            . "DEVI riscrivere il testo della sezione incorporando queste richieste.\n"
            . "Se le richieste sono inappropriate o senza senso, imposta feedback_applied=false e spiega perche.\n\n";
    }

    $userprompt .= "Rispondi SOLO con il JSON.";

    // Call OpenAI.
    $apikey = get_config('local_jobaida', 'openai_apikey');
    $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';
    $maxtokens = (int) get_config('local_jobaida', 'max_tokens') ?: 4000;

    if (empty($apikey)) {
        throw new Exception(get_string('error_no_apikey', 'local_jobaida'));
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemprompt],
            ['role' => 'user', 'content' => $userprompt],
        ],
        'max_tokens' => 1500,
        'temperature' => 0.5,
    ];

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
        throw new Exception('cURL: ' . $error);
    }
    if ($httpcode !== 200) {
        $errordata = json_decode($response, true);
        throw new Exception($errordata['error']['message'] ?? "HTTP {$httpcode}");
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    $content = preg_replace('/^```json\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $content = trim($content);

    $result = json_decode($content, true);
    if (!$result || !isset($result['section_text'])) {
        throw new Exception('Risposta AI non valida');
    }

    $responsedata = [
        'section' => $section,
        'section_text' => $result['section_text'] ?? '',
        'rationale' => $result['rationale'] ?? '',
        'question' => $result['question'] ?? '',
        'tips' => $result['tips'] ?? [],
    ];

    // Include feedback validation fields if feedback was sent.
    if ($has_feedback) {
        $responsedata['feedback_applied'] = !empty($result['feedback_applied']);
        $responsedata['feedback_note'] = $result['feedback_note'] ?? '';
    }

    echo json_encode([
        'success' => true,
        'data' => $responsedata,
        'tokens_used' => $data['usage']['total_tokens'] ?? 0,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
