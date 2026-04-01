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
        $this->maxtokens = (int) get_config('local_jobaida', 'max_tokens') ?: 4000;
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

        $systemprompt = "Sei un career coach senior con 20 anni di esperienza nel placement professionale in Svizzera. "
            . "Il tuo compito e aiutare persone in cerca di impiego a scrivere lettere di candidatura CONCRETE e PERSONALIZZATE. "
            . "Utilizzi il modello AIDA (Attention, Interest, Desire, Action). "
            . "Rispondi SEMPRE in {$langname}.\n\n"
            . "REGOLE FONDAMENTALI:\n"
            . "1. OGNI affermazione nella lettera DEVE essere basata su dati REALI presenti nel CV fornito. NON inventare competenze, esperienze o qualifiche.\n"
            . "2. Se il CV non copre un requisito dell'annuncio, NON fingere che il candidato lo abbia. Piuttosto, evidenzia competenze trasferibili reali o la motivazione ad apprendere.\n"
            . "3. Cita SPECIFICAMENTE: nomi di aziende, ruoli, durate, competenze tecniche e risultati PRESENTI nel CV.\n"
            . "4. Fai riferimento ESPLICITO a elementi dell'annuncio: nome azienda, ruolo offerto, requisiti specifici menzionati.\n"
            . "5. Il tono deve essere professionale, diretto e autentico. No frasi fatte, no formule burocratiche, no adulazione.\n"
            . "6. Ogni sezione del rationale DEVE essere EDUCATIVA: spiega allo studente la STRATEGIA usata, citando le parti esatte dell'annuncio e del CV che hai collegato.\n\n"
            . "Rispondi ESCLUSIVAMENTE con un oggetto JSON valido (senza markdown, senza ```json, solo JSON puro) con questa struttura:\n"
            . "{\n"
            . "  \"attention\": \"Testo della sezione Attention\",\n"
            . "  \"attention_rationale\": \"Spiegazione educativa con riferimenti esatti all'annuncio e al CV\",\n"
            . "  \"interest\": \"Testo della sezione Interest\",\n"
            . "  \"interest_rationale\": \"Spiegazione educativa con mapping requisiti-competenze\",\n"
            . "  \"desire\": \"Testo della sezione Desire\",\n"
            . "  \"desire_rationale\": \"Spiegazione educativa su come valori e cultura si collegano\",\n"
            . "  \"action\": \"Testo della sezione Action\",\n"
            . "  \"action_rationale\": \"Spiegazione educativa sulla strategia di chiusura\",\n"
            . "  \"full_letter\": \"La lettera completa formale pronta da inviare\"\n"
            . "}";

        $userprompt = "Genera una lettera di candidatura in {$langname} basata sul modello AIDA.\n\n"
            . "=== ANNUNCIO DI LAVORO ===\n{$jobad}\n\n"
            . "=== CV DEL CANDIDATO ===\n{$cv}\n\n"
            . "=== OBIETTIVI E MOTIVAZIONI PERSONALI ===\n{$objectives}\n\n"
            . "ISTRUZIONI DETTAGLIATE PER OGNI SEZIONE:\n\n"
            . "ATTENTION (Cattura l'attenzione del selezionatore):\n"
            . "- Identifica l'elemento PIU RILEVANTE dell'annuncio (es. un progetto specifico, un valore aziendale, una sfida menzionata).\n"
            . "- Collegalo IMMEDIATAMENTE a un'esperienza CONCRETA dal CV del candidato.\n"
            . "- Esempio: 'La vostra ricerca di un [ruolo] con esperienza in [X] ha catturato la mia attenzione, avendo maturato [Y anni] in questo ambito presso [azienda dal CV].'\n"
            . "- VIETATO: 'Con la presente...', 'Mi permetto di...', aperture generiche.\n"
            . "- Nel rationale: spiega QUALE elemento dell'annuncio hai scelto come gancio e PERCHE, citando la riga esatta.\n\n"
            . "INTEREST (Dimostra che il candidato ha le competenze richieste):\n"
            . "- Prendi OGNI requisito chiave dall'annuncio e trova la competenza/esperienza CORRISPONDENTE nel CV.\n"
            . "- Cita specificamente: nome azienda, ruolo svolto, durata, risultati ottenuti.\n"
            . "- Se un requisito non e coperto dal CV, NON inventarlo. Menziona competenze trasferibili reali o la volonta di apprendere.\n"
            . "- Usa almeno 3 match concreti tra annuncio e CV.\n"
            . "- Nel rationale: elenca i match trovati in formato 'Requisito annuncio -> Competenza CV' e spiega perche ogni match e convincente.\n\n"
            . "DESIRE (Crea la connessione emotiva/valoriale):\n"
            . "- Usa gli obiettivi personali del candidato per creare un collegamento autentico con l'azienda.\n"
            . "- Se l'annuncio menziona valori aziendali, cultura, missione: collegali ai valori del candidato.\n"
            . "- Mostra come il candidato si vede crescere IN QUESTA specifica azienda, non in generale.\n"
            . "- Nel rationale: spiega come hai costruito la connessione e perche risulta autentica, citando elementi specifici dagli obiettivi e dall'annuncio.\n\n"
            . "ACTION (Chiusura con chiamata all'azione):\n"
            . "- Proponi un'azione CONCRETA: colloquio conoscitivo, prova pratica, giornata di stage.\n"
            . "- Mostra proattivita senza arroganza.\n"
            . "- Includi disponibilita specifica (es. 'Sono disponibile per un colloquio a partire da...').\n"
            . "- Nel rationale: spiega perche questa chiusura e efficace e quale impressione lascia.\n\n"
            . "FULL_LETTER:\n"
            . "- Assembla le 4 sezioni in una lettera formale completa.\n"
            . "- Includi: luogo e data, destinatario (se deducibile dall'annuncio), oggetto, corpo, formula di saluto, firma.\n"
            . "- Lunghezza ideale: 250-350 parole per il corpo.\n\n"
            . "RICORDA: Ogni rationale deve essere una LEZIONE per lo studente. Spiega la strategia comunicativa, "
            . "fai riferimento a parti ESATTE dell'annuncio e del CV, e insegna PERCHE certe scelte sono efficaci nel contesto del mercato del lavoro svizzero.\n\n"
            . "Rispondi SOLO con il JSON, nessun altro testo.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
            'max_tokens' => $this->maxtokens,
            'temperature' => 0.5,
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
