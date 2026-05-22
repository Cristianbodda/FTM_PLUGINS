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

        // Today's date for the letter.
        $today = date('d.m.Y');

        $systemprompt = "Sei un career coach senior con 20 anni di esperienza nel placement professionale in Svizzera. "
            . "Il tuo compito e aiutare persone in cerca di impiego a scrivere lettere di candidatura CONCRETE e PERSONALIZZATE. "
            . "Utilizzi il modello AIDA (Attention, Interest, Desire, Action). "
            . "Rispondi SEMPRE in {$langname}.\n\n"
            . "DATA DI OGGI: {$today}\n\n"
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
            . "  \"interest\": \"Testo della sezione Interest (Modello Manzoni - narrativo)\",\n"
            . "  \"interest_rationale\": \"Spiegazione educativa con mapping requisiti-competenze\",\n"
            . "  \"interest_svizzero\": \"Testo della sezione Interest nel Modello Svizzerò (una frase introduttiva + bullet points con → per ogni requisito)\",\n"
            . "  \"desire\": \"Testo della sezione Desire\",\n"
            . "  \"desire_rationale\": \"Spiegazione educativa su come valori e cultura si collegano\",\n"
            . "  \"action\": \"Testo della sezione Action\",\n"
            . "  \"action_rationale\": \"Spiegazione educativa sulla strategia di chiusura\",\n"
            . "  \"full_letter\": \"Lettera completa con sezione Interest narrativa (Modello Manzoni)\",\n"
            . "  \"full_letter_svizzero\": \"Lettera completa identica ma con la sezione interest_svizzero al posto di interest (Modello Svizzerò)\"\n"
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
            . "INTEREST - MODELLO MANZONI (campo 'interest' - stile narrativo):\n"
            . "- Prendi OGNI requisito chiave dall'annuncio e trova la competenza/esperienza CORRISPONDENTE nel CV.\n"
            . "- Scrivi un paragrafo narrativo fluido che integra almeno 3 match concreti tra annuncio e CV.\n"
            . "- Cita specificamente: nome azienda, ruolo svolto, durata, risultati ottenuti.\n"
            . "- Se un requisito non e coperto dal CV, NON inventarlo. Menziona competenze trasferibili reali o la volonta di apprendere.\n"
            . "- Nel rationale: elenca i match trovati in formato 'Requisito annuncio -> Competenza CV' e spiega perche ogni match e convincente.\n\n"
            . "INTEREST - MODELLO SVIZZERÒ (campo 'interest_svizzero' - stile puntato):\n"
            . "Questa e la variante svizzera moderna della sezione Interest. STRUTTURA OBBLIGATORIA:\n"
            . "- PRIMA RIGA: una frase introduttiva breve che aggancia l'azienda e introduce l'elenco puntato.\n"
            . "  Esempio: 'Sono particolarmente interessato alla vostra enfasi su [elemento specifico annuncio]. Durante i miei [X anni] presso [azienda dal CV],'\n"
            . "- POI: per OGNI requisito principale dell'annuncio, UNA RIGA con il simbolo → all'inizio:\n"
            . "  → [argomento concreto dal CV che risponde ESATTAMENTE a quel requisito: nome azienda, ruolo, durata, competenza tecnica specifica]\n"
            . "- Usa ESATTAMENTE il simbolo → (freccia unicode) come prefisso di ogni bullet, su una riga separata.\n"
            . "- Minimo 3 bullet points, uno per ogni requisito principale. Ogni bullet: 1-2 frasi concrete.\n"
            . "- NON duplicare il testo del Modello Manzoni: e una versione piu strutturata e diretta.\n\n"
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
            . "FULL_LETTER MANZONI (campo 'full_letter') - FORMATO OBBLIGATORIO (ogni elemento su riga separata con \\n):\n\n"
            . "La lettera DEVE seguire ESATTAMENTE questo modello:\n\n"
            . "--- INIZIO MODELLO ---\n"
            . "Nome Cognome\n"
            . "Indirizzo, CAP Citta\n"
            . "Numero di telefono\n"
            . "Indirizzo email\n"
            . "\n"
            . "Citta, {$today}\n"
            . "\n"
            . "Spettabile [Nome Azienda],\n"
            . "[paragrafo ATTENTION]\n"
            . "[paragrafo INTEREST]\n"
            . "[paragrafo DESIRE]\n"
            . "[paragrafo ACTION + chiusura saluti]\n"
            . "--- FINE MODELLO ---\n\n"
            . "REGOLE INTESTAZIONE:\n"
            . "1. DATI CANDIDATO (prime 4 righe): Estrai dal CV nome, cognome, indirizzo completo (via, CAP, citta), telefono, email.\n"
            . "   Se un dato manca nel CV, usa [placeholder tra parentesi quadre] (es. [Il tuo indirizzo], [Telefono], [Email]).\n"
            . "2. DATA: Usa la citta del candidato + data di OGGI {$today}. MAI inventare date diverse.\n"
            . "3. APERTURA: 'Spettabile' seguito dal nome azienda estratto dall'annuncio + virgola.\n"
            . "4. CORPO: Le 4 sezioni AIDA assemblate in modo fluido come testo unico. MASSIMO 300 parole.\n"
            . "5. CHIUSURA: Formula di saluto formale (es. 'Ringraziandovi per l'attenzione, porgo i miei piu cordiali saluti.').\n"
            . "   NON aggiungere firma/nome dopo i saluti.\n\n"
            . "FULL_LETTER SVIZZERÒ (campo 'full_letter_svizzero'):\n"
            . "Lettera IDENTICA a full_letter (stessa intestazione, stesso ATTENTION, stesso DESIRE, stesso ACTION e chiusura),\n"
            . "MA con il contenuto di 'interest_svizzero' al posto della sezione INTEREST narrativa.\n"
            . "I bullet → devono apparire come righe separate nel testo della lettera.\n\n"
            . "RICORDA: Ogni rationale deve essere una LEZIONE per lo studente. Spiega la strategia comunicativa, "
            . "fai riferimento a parti ESATTE dell'annuncio e del CV, e insegna PERCHE certe scelte sono efficaci nel contesto del mercato del lavoro svizzero.\n\n"
            . "Rispondi SOLO con il JSON, nessun altro testo.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
            // Extra headroom for both Manzoni + Svizzerò letters in a single response.
            'max_tokens' => max($this->maxtokens, 5500),
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
        $result->interest_svizzero = $letter['interest_svizzero'] ?? '';
        $result->desire = $letter['desire'] ?? '';
        $result->desire_rationale = $letter['desire_rationale'] ?? '';
        $result->action = $letter['action'] ?? '';
        $result->action_rationale = $letter['action_rationale'] ?? '';
        $result->full_letter = $letter['full_letter'] ?? '';
        $result->full_letter_svizzero = $letter['full_letter_svizzero'] ?? '';
        $result->model_used = $this->model;
        $result->tokens_used = ($data['usage']['total_tokens'] ?? 0);

        return $result;
    }

    /**
     * Analyze gaps between job ad requirements and CV, generate targeted questions.
     *
     * @param string $jobad The job advertisement text.
     * @param string $cv The CV/resume text.
     * @param string $language Language code (it, de, fr, en).
     * @return object Result with requirements, matches, gaps, questions.
     * @throws \moodle_exception If API call fails.
     */
    public function analyze_gaps($jobad, $cv, $language = 'it') {
        if (empty($this->apikey)) {
            throw new \moodle_exception('error_no_apikey', 'local_jobaida');
        }

        $langnames = ['it' => 'italiano', 'de' => 'tedesco', 'fr' => 'francese', 'en' => 'inglese'];
        $langname = $langnames[$language] ?? 'italiano';

        $systemprompt = "Sei un career coach specializzato nell'analisi di compatibilita tra profili professionali e offerte di lavoro. "
            . "Rispondi SEMPRE in {$langname}. "
            . "Il tuo compito e analizzare un annuncio di lavoro e un CV, identificare i requisiti, "
            . "trovare le corrispondenze e i gap, e generare domande mirate per il candidato.\n\n"
            . "Rispondi ESCLUSIVAMENTE con un oggetto JSON valido (senza markdown, senza ```json, solo JSON puro) con questa struttura:\n"
            . "{\n"
            . "  \"company_name\": \"Nome azienda (se deducibile dall'annuncio)\",\n"
            . "  \"role\": \"Ruolo offerto\",\n"
            . "  \"requirements\": [\n"
            . "    {\n"
            . "      \"requirement\": \"Descrizione del requisito richiesto dall'annuncio\",\n"
            . "      \"category\": \"hard_skill|soft_skill|experience|education|language|other\",\n"
            . "      \"importance\": \"essential|preferred|nice_to_have\",\n"
            . "      \"match_status\": \"full_match|partial_match|no_match\",\n"
            . "      \"cv_evidence\": \"Cosa nel CV corrisponde a questo requisito (vuoto se no_match)\",\n"
            . "      \"question\": \"Domanda da porre al candidato per questo requisito (specialmente se partial o no_match)\",\n"
            . "      \"question_hint\": \"Suggerimento per aiutare il candidato a rispondere\"\n"
            . "    }\n"
            . "  ],\n"
            . "  \"strengths\": [\"Punto di forza 1 del candidato rispetto all'annuncio\", ...],\n"
            . "  \"overall_match_percentage\": 75,\n"
            . "  \"coaching_tip\": \"Un consiglio strategico per il candidato su come presentarsi\"\n"
            . "}";

        $userprompt = "Analizza la compatibilita tra questo annuncio di lavoro e questo CV.\n\n"
            . "=== ANNUNCIO DI LAVORO ===\n{$jobad}\n\n"
            . "=== CV DEL CANDIDATO ===\n{$cv}\n\n"
            . "ISTRUZIONI:\n"
            . "1. Identifica TUTTI i requisiti dell'annuncio (hard skill, soft skill, esperienza, formazione, lingue).\n"
            . "2. Per ogni requisito, cerca nel CV se c'e una corrispondenza (full_match, partial_match, no_match).\n"
            . "3. Per i requisiti con partial_match o no_match, formula una DOMANDA SPECIFICA per il candidato.\n"
            . "   La domanda deve aiutare il candidato a riflettere se ha esperienze, certificati o competenze "
            . "   che potrebbero essere rilevanti ma che non ha menzionato nel CV.\n"
            . "4. Per i full_match, formula comunque una domanda per approfondire e valorizzare quella competenza.\n"
            . "5. Le domande devono essere in tono amichevole e incoraggiante, non intimidatorio.\n"
            . "6. Identifica i punti di forza del candidato rispetto all'annuncio.\n"
            . "7. Dai un consiglio strategico su come il candidato dovrebbe posizionarsi.\n\n"
            . "ESEMPI DI DOMANDE EFFICACI:\n"
            . "- 'L'annuncio richiede esperienza con SAP. Hai utilizzato SAP o altri sistemi ERP simili? In quale contesto?'\n"
            . "- 'Il ruolo prevede gestione di un team. Puoi descrivere una situazione in cui hai coordinato colleghi o progetti?'\n"
            . "- 'E richiesta la conoscenza del tedesco. Qual e il tuo livello reale? Lo usi o lo hai usato in ambito lavorativo?'\n"
            . "- 'Noto che hai lavorato in [azienda]. Quali responsabilita avevi che potrebbero essere rilevanti per questo ruolo?'\n\n"
            . "Rispondi SOLO con il JSON, nessun altro testo.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt],
            ],
            'max_tokens' => $this->maxtokens,
            'temperature' => 0.4,
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
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $analysis = json_decode($content, true);
        if (!$analysis || !isset($analysis['requirements'])) {
            throw new \moodle_exception('error_api_failed', 'local_jobaida', '', 'Invalid JSON response from gap analysis');
        }

        $result = new \stdClass();
        $result->company_name = $analysis['company_name'] ?? '';
        $result->role = $analysis['role'] ?? '';
        $result->requirements = $analysis['requirements'] ?? [];
        $result->strengths = $analysis['strengths'] ?? [];
        $result->overall_match_percentage = (int)($analysis['overall_match_percentage'] ?? 0);
        $result->coaching_tip = $analysis['coaching_tip'] ?? '';
        $result->tokens_used = ($data['usage']['total_tokens'] ?? 0);

        return $result;
    }

    /**
     * Generate an AIDA cover letter with gap answers included.
     * Enhanced version that includes candidate's answers to gap questions.
     *
     * @param string $jobad The job advertisement text.
     * @param string $cv The CV/resume text.
     * @param string $objectives Personal objectives.
     * @param array $gap_answers Array of ['question' => ..., 'answer' => ...].
     * @param string $language Language code.
     * @return object Result with AIDA sections.
     */
    public function generate_letter_with_answers($jobad, $cv, $objectives, $gap_answers, $language = 'it') {
        // Build enriched objectives with gap answers.
        $enriched = $objectives . "\n\n";
        $enriched .= "=== RISPOSTE DEL CANDIDATO A DOMANDE SPECIFICHE ===\n";
        foreach ($gap_answers as $qa) {
            $enriched .= "D: " . ($qa['question'] ?? '') . "\n";
            $enriched .= "R: " . ($qa['answer'] ?? '') . "\n\n";
        }

        // Use the standard generate_letter with enriched context.
        return $this->generate_letter($jobad, $cv, $enriched, $language);
    }
}
