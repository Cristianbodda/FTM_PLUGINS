<?php
/**
 * Azure OpenAI Client - Integrazione con Copilot/Azure
 *
 * Client per chiamare Azure OpenAI Service con
 * gestione errori, retry e logging.
 *
 * @package    local_ftm_ai
 * @copyright  2026 Fondazione Terzo Millennio
 */

namespace local_ftm_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Client Azure OpenAI per generazione testi
 */
class azure_openai {

    /** @var string Endpoint Azure */
    private $endpoint;

    /** @var string API Key */
    private $apiKey;

    /** @var string Deployment name (modello) */
    private $deployment;

    /** @var string API Version */
    private $apiVersion = '2024-02-15-preview';

    /** @var int Timeout in secondi */
    private $timeout = 30;

    /** @var int Max retry */
    private $maxRetry = 3;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->endpoint = get_config('local_ftm_ai', 'azure_endpoint');
        $this->apiKey = get_config('local_ftm_ai', 'azure_api_key');
        $this->deployment = get_config('local_ftm_ai', 'azure_deployment');

        if (empty($this->endpoint) || empty($this->apiKey) || empty($this->deployment)) {
            throw new \moodle_exception('config_missing', 'local_ftm_ai');
        }
    }

    /**
     * Genera suggerimenti per il coach basati sui dati studente
     *
     * @param array $safeData Dati anonimizzati dello studente
     * @param string $tone 'formale' o 'colloquiale'
     * @param string $language Lingua output (default: 'it')
     * @return array ['success' => bool, 'suggestions' => string, 'error' => string|null]
     */
    public function generate_coach_suggestions(array $safeData, string $tone = 'formale', string $language = 'it'): array {

        $systemPrompt = $this->build_system_prompt($tone, $language);
        $userPrompt = $this->build_user_prompt($safeData);

        $response = $this->chat_completion($systemPrompt, $userPrompt);

        if ($response['success']) {
            return [
                'success' => true,
                'suggestions' => $response['content'],
                'usage' => $response['usage'] ?? null,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'suggestions' => null,
            'error' => $response['error'],
        ];
    }

    /**
     * Genera analisi predittiva rischi
     *
     * @param array $safeData Dati anonimizzati
     * @return array Analisi rischi
     */
    public function generate_risk_analysis(array $safeData): array {

        $systemPrompt = <<<PROMPT
Sei un analista esperto in formazione professionale. Analizza i dati dello studente e identifica potenziali rischi per il completamento del percorso formativo.

Rispondi in italiano con un JSON strutturato:
{
    "risk_level": "basso|medio|alto|critico",
    "risk_score": 0-100,
    "risk_factors": ["fattore1", "fattore2"],
    "recommendations": ["raccomandazione1", "raccomandazione2"],
    "priority_actions": ["azione1", "azione2"],
    "predicted_outcome": "positivo|incerto|negativo"
}

NON includere mai informazioni personali nella risposta.
Basa l'analisi SOLO sui dati numerici e categorici forniti.
PROMPT;

        $userPrompt = "Analizza questi dati studente e fornisci l'analisi dei rischi:\n\n" .
                      json_encode($safeData, JSON_PRETTY_PRINT);

        $response = $this->chat_completion($systemPrompt, $userPrompt, [
            'response_format' => ['type' => 'json_object']
        ]);

        if ($response['success']) {
            $parsed = json_decode($response['content'], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'analysis' => $parsed,
                    'error' => null,
                ];
            }
        }

        return [
            'success' => false,
            'analysis' => null,
            'error' => $response['error'] ?? 'Failed to parse response',
        ];
    }

    /**
     * Genera varianti linguistiche di un suggerimento base
     *
     * @param string $baseSuggestion Suggerimento template
     * @param string $tone Tono desiderato
     * @param int $variants Numero varianti
     * @return array Varianti generate
     */
    public function generate_linguistic_variants(string $baseSuggestion, string $tone = 'formale', int $variants = 3): array {

        $toneDesc = $tone === 'formale'
            ? 'professionale, formale, adatto a comunicazioni ufficiali con URC e datori di lavoro'
            : 'colloquiale, diretto, adatto a uso interno tra coach';

        $systemPrompt = <<<PROMPT
Sei un esperto di comunicazione professionale nella formazione.
Genera {$variants} varianti del testo fornito, mantenendo:
- Lo stesso significato
- Il tono: {$toneDesc}
- La stessa lunghezza approssimativa

Rispondi con un JSON:
{
    "variants": ["variante1", "variante2", "variante3"]
}

NON aggiungere informazioni. Solo riformulazioni.
PROMPT;

        $response = $this->chat_completion($systemPrompt, $baseSuggestion, [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.8, // Più creatività per varianti
        ]);

        if ($response['success']) {
            $parsed = json_decode($response['content'], true);
            if ($parsed && isset($parsed['variants'])) {
                return [
                    'success' => true,
                    'variants' => $parsed['variants'],
                    'error' => null,
                ];
            }
        }

        // Fallback: restituisci il testo originale
        return [
            'success' => false,
            'variants' => [$baseSuggestion],
            'error' => $response['error'] ?? 'Failed to generate variants',
        ];
    }

    /**
     * Costruisce il system prompt per suggerimenti coach
     */
    private function build_system_prompt(string $tone, string $language): string {
        $toneInstructions = $tone === 'formale'
            ? "Usa un linguaggio professionale e formale, adatto a comunicazioni ufficiali con URC (Ufficio Regionale Collocamento) e potenziali datori di lavoro."
            : "Usa un linguaggio colloquiale e diretto, adatto a note interne del coach. Puoi essere più informale e pratico.";

        return <<<PROMPT
Sei un assistente AI specializzato nella formazione professionale svizzera.
Il tuo compito è generare suggerimenti per coach che seguono studenti in percorsi CPURC.

REGOLE FONDAMENTALI:
1. NON inventare mai dati personali (nomi, date, numeri)
2. NON fare riferimento a persone specifiche
3. Usa SOLO i dati aggregati forniti (percentuali, metriche, settori)
4. Genera suggerimenti pratici e azionabili
5. Rispondi SEMPRE in italiano

TONO:
{$toneInstructions}

STRUTTURA SUGGERIMENTI:
- Inizia con una sintesi della situazione (1-2 frasi)
- Elenca 2-3 punti di forza da valorizzare
- Elenca 2-3 aree di miglioramento prioritarie
- Concludi con 2-3 azioni concrete raccomandate

CONTESTO FORMATIVO:
- Settori: Meccanica, Automobile, Logistica, Elettricità, Automazione, Metalcostruzione, Chimfarm
- Aree competenze: A-G per ogni settore (fondamenti, tecniche, pratiche, trasversali, problem solving, teamwork, comunicazione)
- Gap analysis: confronto autovalutazione vs quiz reali
- Soglie: <10% allineato, 10-25% monitorare, >25% critico
PROMPT;
    }

    /**
     * Costruisce lo user prompt con i dati
     */
    private function build_user_prompt(array $safeData): string {
        $prompt = "Genera suggerimenti per il coach basati su questi dati studente:\n\n";

        // Settore
        $prompt .= "SETTORE: " . ($safeData['sector'] ?? 'Non specificato') . "\n\n";

        // Competenze
        if (!empty($safeData['competencies'])) {
            $comp = $safeData['competencies'];
            $prompt .= "COMPETENZE:\n";
            $prompt .= "- Totale valutate: " . ($comp['total_count'] ?? 0) . "\n";
            $prompt .= "- Media punteggio: " . ($comp['average_score'] ?? 0) . "%\n";

            if (!empty($comp['by_area'])) {
                $prompt .= "- Per area:\n";
                foreach ($comp['by_area'] as $area => $data) {
                    $prompt .= "  - {$area}: {$data['average']}% ({$data['count']} competenze)\n";
                }
            }
            $prompt .= "\n";
        }

        // Gap Analysis
        if (!empty($safeData['gap_analysis'])) {
            $gap = $safeData['gap_analysis'];
            $prompt .= "GAP ANALYSIS (Autovalutazione vs Quiz):\n";
            $prompt .= "- Allineati: " . ($gap['count_aligned'] ?? 0) . "\n";
            $prompt .= "- Sopravvalutazione: " . ($gap['count_overestimated'] ?? 0) . "\n";
            $prompt .= "- Sottovalutazione: " . ($gap['count_underestimated'] ?? 0) . "\n";
            $prompt .= "- Gap medio: " . ($gap['average_gap'] ?? 0) . "%\n";

            if (!empty($gap['critical_areas'])) {
                $prompt .= "- AREE CRITICHE:\n";
                foreach ($gap['critical_areas'] as $crit) {
                    $prompt .= "  - {$crit['area_name']}: gap {$crit['gap']}% ({$crit['type']})\n";
                }
            }
            $prompt .= "\n";
        }

        // Storico
        if (!empty($safeData['history'])) {
            $hist = $safeData['history'];
            $prompt .= "STORICO:\n";
            if ($hist['weeks_in_program']) {
                $prompt .= "- Settimane nel programma: {$hist['weeks_in_program']}\n";
            }
            if ($hist['quiz_completion_rate']) {
                $prompt .= "- Tasso completamento quiz: {$hist['quiz_completion_rate']}%\n";
            }
            if ($hist['attendance_rate']) {
                $prompt .= "- Tasso presenza: {$hist['attendance_rate']}%\n";
            }
            if ($hist['trend']) {
                $prompt .= "- Trend: {$hist['trend']}\n";
            }
        }

        return $prompt;
    }

    /**
     * Chiamata API Azure OpenAI
     *
     * @param string $systemPrompt System message
     * @param string $userPrompt User message
     * @param array $options Opzioni aggiuntive
     * @return array Response
     */
    private function chat_completion(string $systemPrompt, string $userPrompt, array $options = []): array {

        $url = rtrim($this->endpoint, '/') . '/openai/deployments/' .
               $this->deployment . '/chat/completions?api-version=' . $this->apiVersion;

        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1500,
            'top_p' => $options['top_p'] ?? 0.95,
        ];

        if (!empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $headers = [
            'Content-Type: application/json',
            'api-key: ' . $this->apiKey,
        ];

        // Retry logic
        $lastError = null;
        for ($attempt = 1; $attempt <= $this->maxRetry; $attempt++) {
            try {
                $response = $this->http_request($url, $payload, $headers);

                if ($response['http_code'] === 200) {
                    $data = json_decode($response['body'], true);

                    if (isset($data['choices'][0]['message']['content'])) {
                        // Log usage per monitoraggio costi
                        $this->log_usage($data['usage'] ?? []);

                        return [
                            'success' => true,
                            'content' => $data['choices'][0]['message']['content'],
                            'usage' => $data['usage'] ?? null,
                            'error' => null,
                        ];
                    }
                }

                // Rate limit - aspetta e riprova
                if ($response['http_code'] === 429) {
                    $waitTime = pow(2, $attempt); // Exponential backoff
                    sleep($waitTime);
                    continue;
                }

                $lastError = "HTTP {$response['http_code']}: " . ($response['body'] ?? 'Unknown error');

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        // Log errore
        $this->log_error($lastError, $userPrompt);

        return [
            'success' => false,
            'content' => null,
            'error' => $lastError,
        ];
    }

    /**
     * Esegue richiesta HTTP
     */
    private function http_request(string $url, array $payload, array $headers): array {
        $curl = new \curl();
        $curl->setHeader($headers);

        $response = $curl->post($url, json_encode($payload));

        return [
            'http_code' => $curl->get_info()['http_code'] ?? 0,
            'body' => $response,
        ];
    }

    /**
     * Log utilizzo API per monitoraggio costi
     */
    private function log_usage(array $usage) {
        global $DB;

        if (empty($usage)) return;

        $record = new \stdClass();
        $record->timecreated = time();
        $record->prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $record->completion_tokens = $usage['completion_tokens'] ?? 0;
        $record->total_tokens = $usage['total_tokens'] ?? 0;

        try {
            $DB->insert_record('local_ftm_ai_usage', $record);
        } catch (\Exception $e) {
            // Ignora errori di logging
        }
    }

    /**
     * Log errori
     */
    private function log_error(string $error, string $prompt) {
        debugging("FTM AI Error: {$error}", DEBUG_DEVELOPER);

        // Non loggare il prompt completo per privacy
        $promptPreview = substr($prompt, 0, 100) . '...';
        debugging("FTM AI Prompt preview: {$promptPreview}", DEBUG_DEVELOPER);
    }
}
