<?php
/**
 * Anonymizer - Mascheramento dati sensibili per AI
 *
 * Rimuove tutti i PII (Personally Identifiable Information)
 * prima di inviare dati a servizi AI esterni.
 *
 * @package    local_ftm_ai
 * @copyright  2026 Fondazione Terzo Millennio
 */

namespace local_ftm_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per anonimizzare e de-anonimizzare dati studente
 */
class anonymizer {

    /** @var array Mappa token -> valore reale */
    private $tokenMap = [];

    /** @var string Prefisso per i token */
    private const TOKEN_PREFIX = '__FTM_';

    /** @var array Campi da mascherare sempre */
    private const PII_FIELDS = [
        'firstname',
        'lastname',
        'fullname',
        'email',
        'phone1',
        'phone2',
        'address',
        'city',
        'country',
        'idnumber',      // Numero AVS o altro ID
        'institution',
        'department',
        'description',
        'imagealt',
        'alternatename',
        // Campi custom FTM
        'numero_avs',
        'numero_assicurato',
        'iban',
        'data_nascita',
        'indirizzo',
        'cellulare',
        'telefono',
    ];

    /** @var array Pattern regex per PII comuni */
    private const PII_PATTERNS = [
        // Numero AVS Svizzero: 756.XXXX.XXXX.XX
        'avs' => '/\b756\.\d{4}\.\d{4}\.\d{2}\b/',
        // Email
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        // Telefono svizzero
        'phone_ch' => '/\b(\+41|0041|0)\s?\d{2}\s?\d{3}\s?\d{2}\s?\d{2}\b/',
        // IBAN
        'iban' => '/\b[A-Z]{2}\d{2}[A-Z0-9]{4,30}\b/',
        // Data di nascita (vari formati)
        'date' => '/\b\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{2,4}\b/',
    ];

    /**
     * Anonimizza un oggetto studente
     *
     * @param object $student Oggetto studente Moodle
     * @return object Studente con dati mascherati
     */
    public function anonymize_student($student) {
        $anonymized = clone $student;

        foreach (self::PII_FIELDS as $field) {
            if (isset($anonymized->$field) && !empty($anonymized->$field)) {
                $token = $this->create_token($field);
                $this->tokenMap[$token] = $anonymized->$field;
                $anonymized->$field = $token;
            }
        }

        return $anonymized;
    }

    /**
     * Anonimizza un array di dati
     *
     * @param array $data Array associativo con dati
     * @return array Dati mascherati
     */
    public function anonymize_array(array $data): array {
        $anonymized = [];

        foreach ($data as $key => $value) {
            if (in_array($key, self::PII_FIELDS)) {
                $token = $this->create_token($key);
                $this->tokenMap[$token] = $value;
                $anonymized[$key] = $token;
            } elseif (is_string($value)) {
                // Controlla pattern PII nel testo
                $anonymized[$key] = $this->anonymize_text($value);
            } elseif (is_array($value)) {
                $anonymized[$key] = $this->anonymize_array($value);
            } else {
                $anonymized[$key] = $value;
            }
        }

        return $anonymized;
    }

    /**
     * Anonimizza testo libero cercando pattern PII
     *
     * @param string $text Testo da anonimizzare
     * @return string Testo mascherato
     */
    public function anonymize_text(string $text): string {
        $result = $text;

        foreach (self::PII_PATTERNS as $type => $pattern) {
            $result = preg_replace_callback($pattern, function($matches) use ($type) {
                $token = $this->create_token($type);
                $this->tokenMap[$token] = $matches[0];
                return $token;
            }, $result);
        }

        return $result;
    }

    /**
     * Ripristina i dati reali nel testo generato dall'AI
     *
     * @param string $text Testo con token
     * @return string Testo con dati reali
     */
    public function deanonymize(string $text): string {
        $result = $text;

        foreach ($this->tokenMap as $token => $realValue) {
            $result = str_replace($token, $realValue, $result);
        }

        return $result;
    }

    /**
     * Crea un token univoco
     *
     * @param string $type Tipo di dato
     * @return string Token
     */
    private function create_token(string $type): string {
        return self::TOKEN_PREFIX . strtoupper($type) . '_' . bin2hex(random_bytes(4)) . '__';
    }

    /**
     * Resetta la mappa dei token
     */
    public function reset() {
        $this->tokenMap = [];
    }

    /**
     * Ottieni la mappa dei token (per debug)
     *
     * @return array
     */
    public function get_token_map(): array {
        return $this->tokenMap;
    }

    /**
     * Prepara dati sicuri per l'AI (solo metriche, no PII)
     *
     * @param int $userid ID studente
     * @param array $competencyData Dati competenze
     * @param array $gapData Dati gap analysis
     * @param array $historyData Storico studente (opzionale)
     * @return array Dati sicuri per AI
     */
    public function prepare_safe_data_for_ai(
        int $userid,
        array $competencyData,
        array $gapData,
        array $historyData = []
    ): array {
        // ID anonimo per riferimento interno
        $anonymousId = 'STUDENT_' . hash('sha256', $userid . get_config('local_ftm_ai', 'salt'));

        $safeData = [
            'student_id' => $anonymousId, // Hash, non ID reale
            'timestamp' => time(),

            // Dati aggregati competenze (sicuri)
            'competencies' => [
                'total_count' => count($competencyData),
                'average_score' => $this->calculate_average($competencyData, 'score'),
                'by_area' => $this->aggregate_by_area($competencyData),
            ],

            // Dati gap analysis (sicuri - sono solo percentuali)
            'gap_analysis' => [
                'count_aligned' => count(array_filter($gapData, fn($g) => $g['tipo'] === 'allineato')),
                'count_overestimated' => count(array_filter($gapData, fn($g) => strpos($g['tipo'], 'sopravvalutazione') !== false)),
                'count_underestimated' => count(array_filter($gapData, fn($g) => $g['tipo'] === 'sottovalutazione')),
                'average_gap' => $this->calculate_average($gapData, 'differenza'),
                'critical_areas' => $this->get_critical_areas($gapData),
            ],

            // Storico (solo metriche aggregate)
            'history' => [
                'weeks_in_program' => $historyData['weeks'] ?? null,
                'quiz_completion_rate' => $historyData['quiz_rate'] ?? null,
                'attendance_rate' => $historyData['attendance'] ?? null,
                'trend' => $historyData['trend'] ?? null, // 'improving', 'stable', 'declining'
            ],

            // Settore (sicuro - è categorico)
            'sector' => $competencyData[0]['sector'] ?? 'UNKNOWN',
        ];

        return $safeData;
    }

    /**
     * Calcola media di un campo
     */
    private function calculate_average(array $data, string $field): float {
        if (empty($data)) return 0;
        $sum = array_sum(array_column($data, $field));
        return round($sum / count($data), 2);
    }

    /**
     * Aggrega dati per area
     */
    private function aggregate_by_area(array $data): array {
        $byArea = [];
        foreach ($data as $item) {
            $area = $item['area'] ?? 'OTHER';
            if (!isset($byArea[$area])) {
                $byArea[$area] = ['count' => 0, 'total_score' => 0];
            }
            $byArea[$area]['count']++;
            $byArea[$area]['total_score'] += $item['score'] ?? 0;
        }

        foreach ($byArea as $area => &$areaData) {
            $areaData['average'] = round($areaData['total_score'] / $areaData['count'], 2);
            unset($areaData['total_score']);
        }

        return $byArea;
    }

    /**
     * Ottieni aree critiche (gap > 25%)
     */
    private function get_critical_areas(array $gapData): array {
        $critical = [];
        foreach ($gapData as $gap) {
            if (abs($gap['differenza'] ?? 0) > 25) {
                $critical[] = [
                    'area' => $gap['area'] ?? 'UNKNOWN',
                    'area_name' => $gap['area_name'] ?? '',
                    'gap' => $gap['differenza'],
                    'type' => $gap['tipo'],
                ];
            }
        }
        return $critical;
    }
}
