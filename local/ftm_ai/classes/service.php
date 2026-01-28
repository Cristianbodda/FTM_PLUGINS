<?php
/**
 * FTM AI Service - Interfaccia principale
 *
 * Classe facade che semplifica l'uso delle funzionalità AI
 * con gestione automatica dell'anonimizzazione.
 *
 * @package    local_ftm_ai
 * @copyright  2026 Fondazione Terzo Millennio
 */

namespace local_ftm_ai;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/anonymizer.php');
require_once(__DIR__ . '/azure_openai.php');

/**
 * Servizio principale per l'integrazione AI
 *
 * Esempio d'uso:
 *
 * $service = new \local_ftm_ai\service();
 * $result = $service->generate_student_suggestions($userid, $competencyData, $gapData, 'formale');
 *
 * if ($result['success']) {
 *     echo $result['suggestions']; // Testo con nome studente già reinserito
 * }
 */
class service {

    /** @var anonymizer */
    private $anonymizer;

    /** @var azure_openai */
    private $ai;

    /** @var bool Se il servizio è configurato */
    private $configured = false;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->anonymizer = new anonymizer();

        try {
            $this->ai = new azure_openai();
            $this->configured = true;
        } catch (\Exception $e) {
            $this->configured = false;
            debugging('FTM AI not configured: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Verifica se il servizio AI è disponibile
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->configured;
    }

    /**
     * Genera suggerimenti personalizzati per uno studente
     *
     * Questa è la funzione principale. Gestisce automaticamente:
     * 1. Anonimizzazione dati sensibili
     * 2. Chiamata API Azure OpenAI
     * 3. Re-inserimento nome studente nel testo generato
     *
     * @param int $userid ID studente Moodle
     * @param array $competencyData Dati competenze
     * @param array $gapData Dati gap analysis
     * @param string $tone 'formale' o 'colloquiale'
     * @param array $historyData Storico opzionale
     * @return array ['success' => bool, 'suggestions' => string, 'error' => string|null]
     */
    public function generate_student_suggestions(
        int $userid,
        array $competencyData,
        array $gapData,
        string $tone = 'formale',
        array $historyData = []
    ): array {
        global $DB;

        if (!$this->configured) {
            return $this->fallback_suggestions($competencyData, $gapData, $tone);
        }

        // Controlla limiti giornalieri
        if (!$this->check_daily_limit()) {
            return [
                'success' => false,
                'suggestions' => null,
                'error' => get_string('error_rate_limit', 'local_ftm_ai'),
            ];
        }

        try {
            // Ottieni nome studente per re-injection
            $student = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname');
            $studentName = $student ? fullname($student) : 'Lo studente';

            // Prepara dati sicuri (anonimizzati)
            $safeData = $this->anonymizer->prepare_safe_data_for_ai(
                $userid,
                $competencyData,
                $gapData,
                $historyData
            );

            // Genera suggerimenti con AI
            $result = $this->ai->generate_coach_suggestions($safeData, $tone);

            if ($result['success']) {
                // Sostituisci placeholder con nome reale
                $suggestions = str_replace(
                    ['[STUDENTE]', '[studente]', 'lo studente', 'Lo studente'],
                    [$studentName, $studentName, $studentName, $studentName],
                    $result['suggestions']
                );

                return [
                    'success' => true,
                    'suggestions' => $suggestions,
                    'ai_generated' => true,
                    'error' => null,
                ];
            }

            // Fallback se AI fallisce
            return $this->fallback_suggestions($competencyData, $gapData, $tone);

        } catch (\Exception $e) {
            debugging('FTM AI Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->fallback_suggestions($competencyData, $gapData, $tone);
        }
    }

    /**
     * Genera analisi predittiva dei rischi
     *
     * @param int $userid ID studente
     * @param array $allData Tutti i dati disponibili
     * @return array Analisi rischi
     */
    public function generate_risk_analysis(int $userid, array $allData): array {
        if (!$this->configured || !get_config('local_ftm_ai', 'enable_risk_analysis')) {
            return [
                'success' => false,
                'analysis' => null,
                'error' => 'Risk analysis not available',
            ];
        }

        // Prepara dati sicuri
        $safeData = $this->anonymizer->prepare_safe_data_for_ai(
            $userid,
            $allData['competencies'] ?? [],
            $allData['gap'] ?? [],
            $allData['history'] ?? []
        );

        return $this->ai->generate_risk_analysis($safeData);
    }

    /**
     * Genera varianti linguistiche di un testo
     *
     * @param string $baseText Testo base
     * @param string $tone Tono desiderato
     * @return array Varianti
     */
    public function generate_variants(string $baseText, string $tone = 'formale'): array {
        if (!$this->configured || !get_config('local_ftm_ai', 'enable_linguistic_variants')) {
            return [
                'success' => true,
                'variants' => [$baseText], // Ritorna originale
                'error' => null,
            ];
        }

        return $this->ai->generate_linguistic_variants($baseText, $tone, 3);
    }

    /**
     * Fallback: usa template deterministici se AI non disponibile
     *
     * @param array $competencyData
     * @param array $gapData
     * @param string $tone
     * @return array
     */
    private function fallback_suggestions(array $competencyData, array $gapData, string $tone): array {
        // Usa il sistema template esistente (gap_comments_mapping.php)
        global $CFG;
        require_once($CFG->dirroot . '/local/competencymanager/gap_comments_mapping.php');

        $suggestions = [];

        foreach ($gapData as $gap) {
            $areaKey = $gap['area_key'] ?? '';
            if (!empty($areaKey)) {
                $comment = generate_gap_comment(
                    $areaKey,
                    $gap['autovalutazione'] ?? 0,
                    $gap['performance'] ?? 0,
                    $tone
                );
                $suggestions[] = $comment['commento'];
            }
        }

        $text = !empty($suggestions)
            ? implode("\n\n", $suggestions)
            : "Dati insufficienti per generare suggerimenti.";

        return [
            'success' => true,
            'suggestions' => $text,
            'ai_generated' => false, // Flag che indica fallback
            'error' => null,
        ];
    }

    /**
     * Controlla limite giornaliero chiamate API
     *
     * @return bool True se sotto il limite
     */
    private function check_daily_limit(): bool {
        global $DB;

        $dailyLimit = get_config('local_ftm_ai', 'daily_limit');
        if (empty($dailyLimit) || $dailyLimit == 0) {
            return true; // Nessun limite
        }

        $todayStart = strtotime('today midnight');

        try {
            $count = $DB->count_records_select(
                'local_ftm_ai_usage',
                'timecreated >= ?',
                [$todayStart]
            );

            return $count < $dailyLimit;
        } catch (\Exception $e) {
            return true; // In caso di errore, permetti
        }
    }
}
