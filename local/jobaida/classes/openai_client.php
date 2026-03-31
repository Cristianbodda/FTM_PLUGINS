<?php
/**
 * OpenAI API client for AIDA cover letter generation.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobaida;

defined('MOODLE_INTERNAL') || die();

class openai_client {

    private $apikey;
    private $model;
    private $maxtokens;

    public function __construct() {
        $this->apikey = get_config('local_jobaida', 'openai_apikey');
        $this->model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';
        $this->maxtokens = (int) get_config('local_jobaida', 'max_tokens') ?: 2000;
    }

    /**
     * Generate an AIDA cover letter.
     *
     * @param string $jobad The job advertisement text.
     * @param string $cv The CV/resume text.
     * @param string $objectives Personal objectives.
     * @param string $language Language code (it, de, fr, en).
     * @return object Result with AIDA sections, rationales, full_letter, tokens_used.
     * @throws \moodle_exception If API call fails.
     */
    public function generate_letter($jobad, $cv, $objectives, $language = 'it') {
        if (empty($this->apikey)) {
            throw new \moodle_exception('error_no_apikey', 'local_jobaida');
        }

        $langnames = ['it' => 'italiano', 'de' => 'tedesco', 'fr' => 'francese', 'en' => 'inglese'];
        $langname = $langnames[$language] ?? 'italiano';

        $systemprompt = "Sei un esperto career coach specializzato nella redazione di lettere di candidatura efficaci. "
            . "Utilizzi il modello AIDA (Attention, Interest, Desire, Action) per strutturare lettere persuasive. "
            . "Rispondi SEMPRE in {$langname}. "
            . "Rispondi ESCLUSIVAMENTE con un oggetto JSON valido (senza markdown, senza ```json, solo JSON puro) con questa struttura:\n"
            . "{\n"
            . "  \"attention\": \"Testo della sezione Attention\",\n"
            . "  \"attention_rationale\": \"Perche hai scelto questo gancio\",\n"
            . "  \"interest\": \"Testo della sezione Interest\",\n"
            . "  \"interest_rationale\": \"Perche hai evidenziato queste competenze\",\n"
            . "  \"desire\": \"Testo della sezione Desire\",\n"
            . "  \"desire_rationale\": \"Perche hai fatto questa connessione\",\n"
            . "  \"action\": \"Testo della sezione Action\",\n"
            . "  \"action_rationale\": \"Perche questa chiusura\",\n"
            . "  \"full_letter\": \"La lettera completa assemblata con intestazione, corpo e chiusura formale\"\n"
            . "}";

        $userprompt = "Genera una lettera di candidatura in {$langname} basata sul modello AIDA.\n\n"
            . "--- ANNUNCIO DI LAVORO ---\n{$jobad}\n\n"
            . "--- CV DEL CANDIDATO ---\n{$cv}\n\n"
            . "--- OBIETTIVI PERSONALI ---\n{$objectives}\n\n"
            . "Istruzioni:\n"
            . "- ATTENTION: Crea un gancio iniziale forte basato sull'annuncio. Non usare formule banali.\n"
            . "- INTEREST: Collega i requisiti dell'annuncio con le competenze del CV. Sii specifico.\n"
            . "- DESIRE: Connetti i valori/obiettivi del candidato con la cultura aziendale.\n"
            . "- ACTION: Chiudi con una chiamata all'azione professionale e proattiva.\n"
            . "- Per ogni sezione, spiega nel rationale PERCHE hai fatto quella scelta.\n"
            . "- La full_letter deve essere una lettera formale completa, pronta da inviare.\n"
            . "- Rispondi SOLO con il JSON, nessun altro testo.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
            'max_tokens' => $this->maxtokens,
            'temperature' => 0.7,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apikey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \moodle_exception('error_api_failed', 'local_jobaida', '', 'cURL: ' . $error);
        }

        if ($httpcode !== 200) {
            $errordata = json_decode($response, true);
            $errormsg = $errordata['error']['message'] ?? "HTTP {$httpcode}";
            throw new \moodle_exception('error_api_failed', 'local_jobaida', '', $errormsg);
        }

        $data = json_decode($response, true);
        if (empty($data['choices'][0]['message']['content'])) {
            throw new \moodle_exception('error_api_failed', 'local_jobaida', '', 'Empty response');
        }

        $content = $data['choices'][0]['message']['content'];
        // Strip markdown code fences if present.
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $letter = json_decode($content, true);
        if (!$letter || !isset($letter['attention'])) {
            throw new \moodle_exception('error_api_failed', 'local_jobaida', '', 'Invalid JSON response');
        }

        $result = new \stdClass();
        $result->attention = $letter['attention'] ?? '';
        $result->attention_rationale = $letter['attention_rationale'] ?? '';
        $result->interest = $letter['interest'] ?? '';
        $result->interest_rationale = $letter['interest_rationale'] ?? '';
        $result->desire = $letter['desire'] ?? '';
        $result->desire_rationale = $letter['desire_rationale'] ?? '';
        $result->action = $letter['action'] ?? '';
        $result->action_rationale = $letter['action_rationale'] ?? '';
        $result->full_letter = $letter['full_letter'] ?? '';
        $result->model_used = $this->model;
        $result->tokens_used = ($data['usage']['total_tokens'] ?? 0);

        return $result;
    }
}
