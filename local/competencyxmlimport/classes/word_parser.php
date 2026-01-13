<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Parser per file Word (.docx) contenenti domande quiz - VERSIONE 3.0 MULTI-FORMATO
 * 
 * Estrae domande, risposte e competenze da file Word nel formato FTM.
 * Supporta 14 formati diversi per AUTOVEICOLO, ELETTRONICA, CHIMICA, ELETTRICITÀ.
 *
 * FORMATI SUPPORTATI:
 * 
 * AUTOVEICOLO (3 formati):
 *  1. FORMATO_1_AUTOV_BASE    - AUT_BASE_Q01 + Competenza collegata:
 *  2. FORMATO_2_AUTOV_APPR    - Q01 (ID) + checkmark ✔ + Competenza (CO):
 *  7. FORMATO_7_AUTOV_APPR36  - Q01 – Competenza: AUTOMOBILE_XXX
 * 
 * ELETTRONICA (2 formati):
 *  3. FORMATO_3_ELETT_BASE    - Q01 + Competenza: all'inizio blocco
 *  4. FORMATO_4_ELETT_APPR06  - Q01 – COMP_CODE (codice nel header)
 * 
 * CHIMICA (3 formati):
 *  5. FORMATO_5_CHIM_BASE     - Q01 + Competenza: XXX | Risposta: X |
 *  6. FORMATO_6_CHIM_APPR00   - Q01 (ID) + Competenza (F2):
 *  8. FORMATO_8_CHIM_APPR23   - Q01 + Competenza (F2): dopo risposte
 * 
 * ELETTRICITÀ (5 formati):
 * 10. FORMATO_10_ELET_BASE    - ELET_BASE_Q01 — ELETTRICITÀ_XX_YY
 * 11. FORMATO_11_ELET_BULLET  - Bullet list (-) + checkmark ✔ + Competenza:
 * 12. FORMATO_12_ELET_PIPE    - Q01 | Codice competenza: ELETTRICITÀ_XX
 * 13. FORMATO_13_ELET_DOT     - Q01. Testo + Codice competenza:
 * 14. FORMATO_14_ELET_NEWLINE - Q01\nCompetenza: desc\nCodice: ELETTRICITÀ_XX
 * 
 * LOGISTICA (1 formato):
 * 15. FORMATO_15_LOGISTICA    - 1. LOG_BASE_Q01 + Competenza: LOGISTICA_XX
 *
 * GENERICO:
 *  9. FORMATO_9_NO_COMP       - File senza competenze (richiede Excel)
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM - Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    3.0 - Aggiunto supporto ELETTRICITÀ (5 formati)
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per il parsing di file Word contenenti domande quiz
 */
class word_parser {
    
    /** @var string Testo estratto dal documento */
    private $text = '';
    
    /** @var string Nome del file originale */
    private $filename = '';
    
    /** @var array Domande estratte */
    private $questions = [];
    
    /** @var array Errori riscontrati */
    private $errors = [];
    
    /** @var array Warnings (domande con problemi ma recuperabili) */
    private $warnings = [];
    
    /** @var string Settore corrente (es. CHIMFARM, MECCANICA, AUTOMOBILE, AUTOMAZIONE, ELETTRICITA) */
    private $sector = '';
    
    /** @var array Competenze valide nel framework */
    private $valid_competencies = [];
    
    /** @var string Formato rilevato del file */
    private $detected_format = '';
    
    // =========================================================================
    // COSTANTI PER I FORMATI - 14 formati totali
    // =========================================================================
    
    // AUTOVEICOLO (3 formati)
    const FORMAT_1_AUTOV_BASE = 'FORMATO_1_AUTOV_BASE';
    const FORMAT_2_AUTOV_APPR = 'FORMATO_2_AUTOV_APPR';
    const FORMAT_7_AUTOV_APPR36 = 'FORMATO_7_AUTOV_APPR36';
    
    // ELETTRONICA (2 formati)
    const FORMAT_3_ELETT_BASE = 'FORMATO_3_ELETT_BASE';
    const FORMAT_4_ELETT_APPR06 = 'FORMATO_4_ELETT_APPR06';
    
    // CHIMICA (3 formati)
    const FORMAT_5_CHIM_BASE = 'FORMATO_5_CHIM_BASE';
    const FORMAT_6_CHIM_APPR00 = 'FORMATO_6_CHIM_APPR00';
    const FORMAT_8_CHIM_APPR23 = 'FORMATO_8_CHIM_APPR23';
    
    // ELETTRICITÀ (5 formati) - NUOVI in V3
    const FORMAT_10_ELET_BASE = 'FORMATO_10_ELET_BASE';
    const FORMAT_11_ELET_BULLET = 'FORMATO_11_ELET_BULLET';
    const FORMAT_12_ELET_PIPE = 'FORMATO_12_ELET_PIPE';
    const FORMAT_13_ELET_DOT = 'FORMATO_13_ELET_DOT';
    const FORMAT_14_ELET_NEWLINE = 'FORMATO_14_ELET_NEWLINE';
    
    // LOGISTICA (1 formato)
    const FORMAT_15_LOGISTICA = 'FORMATO_15_LOGISTICA';

    // GENERICI
    const FORMAT_9_NO_COMP = 'FORMATO_9_NO_COMP';
    const FORMAT_UNKNOWN = 'FORMATO_SCONOSCIUTO';
    
    /**
     * Costruttore
     * 
     * @param string $sector Settore per la validazione competenze (es. CHIMFARM, AUTOMOBILE, ELETTRICITA)
     * @param array $valid_competencies Array di codici competenza validi nel framework
     */
    public function __construct($sector = '', $valid_competencies = []) {
        $this->sector = $sector;
        // Normalizza tutte le competenze in UPPERCASE per confronto case-insensitive (UTF-8)
        $this->valid_competencies = array_map(function($c) {
            return mb_strtoupper($c, 'UTF-8');
        }, $valid_competencies);
    }
    
    /**
     * Parsing di un file Word
     * 
     * @param string $filepath Percorso completo al file .docx
     * @return array Risultato con 'success', 'questions', 'errors', 'warnings', 'format'
     */
    public function parse_file($filepath) {
        $this->errors = [];
        $this->warnings = [];
        $this->questions = [];
        $this->detected_format = '';
        
        // Verifica che il file esista
        if (!file_exists($filepath)) {
            $this->errors[] = "File non trovato: {$filepath}";
            return $this->get_result();
        }
        
        // Estrai nome file
        $this->filename = basename($filepath);
        
        // Estrai testo dal Word
        $text = $this->extract_text_from_docx($filepath);
        if ($text === false) {
            $this->errors[] = "Impossibile leggere il file Word. Verifica che sia un .docx valido.";
            return $this->get_result();
        }
        
        $this->text = $text;
        
        // Normalizza encoding ELETTRICITA -> ELETTRICITÀ
        $this->text = $this->normalize_elettricita_encoding($this->text);
        
        // Rileva formato automaticamente
        $this->detected_format = $this->detect_format($this->text);
        
        // Parsa le domande con il formato rilevato
        $this->parse_questions();
        
        return $this->get_result();
    }
    
    /**
     * Normalizza l'encoding per ELETTRICITÀ (gestisce varianti con/senza accento)
     * 
     * @param string $text Testo da normalizzare
     * @return string Testo normalizzato
     */
    private function normalize_elettricita_encoding($text) {
        // ELETTRICITA senza accento -> ELETTRICITÀ con accento
        $text = preg_replace('/ELETTRICITA(?!À)/u', 'ELETTRICITÀ', $text);
        return $text;
    }
    
    /**
     * Rileva automaticamente il formato del file Word
     * Supporta 14 formati per 4 settori
     * 
     * @param string $text Testo estratto dal documento
     * @return string Costante formato rilevato
     */
    private function detect_format($text) {
        // =====================================================================
        // CARATTERISTICHE DISTINTIVE
        // =====================================================================
        
        // Checkmarks
        $has_checkmark = (strpos($text, '✔') !== false) || 
                         (strpos($text, '✓') !== false) || 
                         (strpos($text, '✅') !== false);
        
        // Pattern AUTOVEICOLO
        $has_competenza_collegata = (strpos($text, 'Competenza collegata:') !== false);
        $has_competenza_co = (bool) preg_match('/Competenza\s*\(CO\):/u', $text);
        $has_prefix_aut_q = (bool) preg_match('/\n[A-Z_]+_Q\d+\n/u', $text);
        $has_q_parenthesis = (bool) preg_match('/\nQ\d+\s*\([A-Z_]+/u', $text);
        $has_q_dash_competenza = (bool) preg_match('/\nQ\d+\s*[–—-]\s*Competenza:/u', $text);
        
        // Pattern ELETTRONICA
        $has_competenza_inline = (bool) preg_match('/\nCompetenza:\s*[A-Z]/u', $text);
        $has_q_dash_comp_code = (bool) preg_match('/\nQ\d+\s*[–—-]\s*[A-Z_]+_[A-Z0-9]+\n/u', $text);
        
        // Pattern CHIMICA
        $has_pipe_competency = (bool) preg_match('/Competenza:[^|]+\|[^|]+\|/u', $text);
        $has_competenza_f2 = (bool) preg_match('/Competenza\s*\(F2\):/u', $text);
        
        // Pattern ELETTRICITÀ (NUOVI)
        $has_elet_base_q = (bool) preg_match('/ELET_BASE_Q\d+\s*[—–-]\s*ELETTRICITÀ/u', $text);
        $has_bullet_checkmark = (bool) preg_match('/\n-\s+[^\n]+[✔✓✅]/u', $text);
        $has_q_pipe_codice = (bool) preg_match('/\nQ\d+\s*\|\s*Codice\s*competenza:/ui', $text);
        $has_q_dot_codice = (bool) preg_match('/\nQ\d+\.\s/u', $text) && 
                            (bool) preg_match('/Codice\s*competenza:\s*ELETTRICITÀ/ui', $text);
        $has_codice_newline = (bool) preg_match('/\nCodice:\s*ELETTRICITÀ/ui', $text);
        
        // Pattern LOGISTICA
        $has_numbered_log_q = (bool) preg_match('/\n\d+\.\s*[A-Z_]+_Q\d+\n/u', $text);
        $has_competenza_logistica = (bool) preg_match('/Competenza:\s*LOGISTICA_/ui', $text);

        // Nessuna competenza
        $has_no_competency = !$has_competenza_collegata && !$has_competenza_co &&
                            !$has_competenza_f2 && !$has_competenza_inline &&
                            !$has_q_dash_comp_code && !$has_q_dash_competenza &&
                            !$has_elet_base_q && !$has_q_pipe_codice &&
                            !$has_q_dot_codice && !$has_codice_newline &&
                            !$has_bullet_checkmark && !$has_competenza_logistica;
        
        // =====================================================================
        // RILEVAMENTO FORMATO CON PRIORITÀ
        // =====================================================================
        
        // --- ELETTRICITÀ (testare prima perché hanno pattern specifici) ---
        
        // FORMATO 10: ELETTRICITÀ Test Base - ELET_BASE_Q01 — ELETTRICITÀ_XX
        if ($has_elet_base_q) {
            return self::FORMAT_10_ELET_BASE;
        }
        
        // FORMATO 12: ELETTRICITÀ APPR04 - Q01 | Codice competenza:
        if ($has_q_pipe_codice) {
            return self::FORMAT_12_ELET_PIPE;
        }
        
        // FORMATO 11: ELETTRICITÀ APPR00 - Bullet list + checkmark + Competenza:
        if ($has_bullet_checkmark && (bool) preg_match('/\nCompetenza:\s*ELETTRICITÀ/ui', $text)) {
            return self::FORMAT_11_ELET_BULLET;
        }
        
        // FORMATO 14: ELETTRICITÀ APPR03/05/06 - Q##\nCompetenza:\nCodice:
        if ($has_codice_newline) {
            return self::FORMAT_14_ELET_NEWLINE;
        }
        
        // FORMATO 13: ELETTRICITÀ APPR01/02 - Q01. + Codice competenza:
        if ($has_q_dot_codice) {
            return self::FORMAT_13_ELET_DOT;
        }
        
        // --- LOGISTICA ---

        // FORMATO 15: LOGISTICA - 1. LOG_BASE_Q01 + Competenza: LOGISTICA_XX
        if ($has_numbered_log_q && $has_competenza_logistica) {
            return self::FORMAT_15_LOGISTICA;
        }

        // --- AUTOVEICOLO ---

        // FORMATO 1: AUTOVEICOLO Test Base - AUT_BASE_Q01 + Competenza collegata
        if ($has_prefix_aut_q && $has_competenza_collegata) {
            return self::FORMAT_1_AUTOV_BASE;
        }
        
        // FORMATO 2: AUTOVEICOLO APPR0-2 - Q01 (ID) + checkmark + Competenza (CO)
        if ($has_q_parenthesis && $has_checkmark && $has_competenza_co) {
            return self::FORMAT_2_AUTOV_APPR;
        }
        
        // FORMATO 7: AUTOVEICOLO APPR03-06 - Q01 – Competenza: XXX
        if ($has_q_dash_competenza) {
            return self::FORMAT_7_AUTOV_APPR36;
        }
        
        // --- ELETTRONICA ---
        
        // FORMATO 4: ELETTRONICA APPR03/06 - Q01 – COMP_CODE (codice nel header)
        if ($has_q_dash_comp_code) {
            return self::FORMAT_4_ELETT_APPR06;
        }
        
        // FORMATO 3: ELETTRONICA Base/APPR - Competenza: XXX all'inizio blocco
        if ($has_competenza_inline) {
            return self::FORMAT_3_ELETT_BASE;
        }
        
        // --- CHIMICA ---
        
        // FORMATO 5: CHIMICA Base/APPR01 - pipe separator
        if ($has_pipe_competency) {
            return self::FORMAT_5_CHIM_BASE;
        }
        
        // FORMATO 6: CHIMICA APPR00 - Q01 (ID) + Competenza (F2)
        if ($has_q_parenthesis && $has_competenza_f2) {
            return self::FORMAT_6_CHIM_APPR00;
        }
        
        // FORMATO 8: CHIMICA APPR02/03 - Competenza (F2) dopo risposte
        if ($has_competenza_f2) {
            return self::FORMAT_8_CHIM_APPR23;
        }
        
        // --- GENERICO ---
        
        // FORMATO 9: File senza competenze (richiede Excel)
        if ($has_no_competency) {
            return self::FORMAT_9_NO_COMP;
        }
        
        return self::FORMAT_UNKNOWN;
    }
    
    /**
     * Estrae il testo da un file .docx
     * 
     * @param string $filepath Percorso al file .docx
     * @return string|false Testo estratto o false in caso di errore
     */
    private function extract_text_from_docx($filepath) {
        // Apri il file come ZIP
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            return false;
        }
        
        // Leggi document.xml
        $xml_content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml_content === false) {
            return false;
        }
        
        // Estrai paragrafi dal XML
        return $this->extract_paragraphs_from_xml($xml_content);
    }
    
    /**
     * Estrae paragrafi dal contenuto XML di Word
     * 
     * @param string $xml_content Contenuto XML di document.xml
     * @return string Testo con paragrafi separati da newline
     */
    private function extract_paragraphs_from_xml($xml_content) {
        $paragraphs = [];
        
        // Pattern per trovare paragrafi <w:p>...</w:p>
        preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml_content, $matches);
        
        foreach ($matches[1] as $p_content) {
            // Estrai testo da ogni <w:t>...</w:t>
            preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $p_content, $text_matches);
            $paragraph_text = implode('', $text_matches[1]);
            
            if (trim($paragraph_text) !== '') {
                $paragraphs[] = trim($paragraph_text);
            }
        }
        
        return implode("\n", $paragraphs);
    }
    
    /**
     * Parsa il testo estratto e trova le domande
     * Usa il formato rilevato per lo split corretto
     */
    private function parse_questions() {
        $text = $this->text;
        $format = $this->detected_format;
        
        // Split in base al formato
        $parts = $this->split_questions_by_format($text, $format);
        
        // Gestione speciale per formati che catturano gruppi multipli
        if (in_array($format, [self::FORMAT_4_ELETT_APPR06, self::FORMAT_10_ELET_BASE, self::FORMAT_12_ELET_PIPE])) {
            // parts[0] = header, poi (Q##, COMP, content), (Q##, COMP, content), ...
            for ($i = 1; $i < count($parts) - 2; $i += 3) {
                $question_id = $parts[$i];
                $competency_code = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
                $content = isset($parts[$i + 2]) ? $parts[$i + 2] : '';
                
                $question = $this->parse_single_question($question_id, $content, $format, $competency_code);
                if ($question) {
                    $this->questions[] = $question;
                }
            }
        } else {
            // Standard: (Q##, content), (Q##, content), ...
            for ($i = 1; $i < count($parts) - 1; $i += 2) {
                $question_id = $parts[$i];
                $content = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
                
                $question = $this->parse_single_question($question_id, $content, $format);
                if ($question) {
                    $this->questions[] = $question;
                }
            }
        }
    }
    
    /**
     * Split del testo in base al formato rilevato
     * 
     * @param string $text Testo completo
     * @param string $format Formato rilevato
     * @return array Parti splittate
     */
    private function split_questions_by_format($text, $format) {
        switch ($format) {
            // --- AUTOVEICOLO ---
            case self::FORMAT_1_AUTOV_BASE:
                // Split su PREFIX_Q## (es. AUT_BASE_Q01)
                return preg_split('/\n([A-Z_]+_Q\d+)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_2_AUTOV_APPR:
            case self::FORMAT_6_CHIM_APPR00:
                // Split su Q## (ID) o Q##  (ID)
                return preg_split('/\n(Q\d+)\s*\([^)]+\)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_7_AUTOV_APPR36:
                // Split su Q## – Competenza: (con dash UTF-8)
                return preg_split('/\n(Q\d+)\s*[–—-]\s*Competenza:/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            // --- ELETTRONICA ---
            case self::FORMAT_4_ELETT_APPR06:
                // Split su Q## – COMP_CODE (cattura Q## e COMP_CODE separatamente)
                return preg_split('/\n(Q\d+)\s*[–—-]\s*([A-Z_]+_[A-Z0-9_]+)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            // --- ELETTRICITÀ (NUOVI) ---
            case self::FORMAT_10_ELET_BASE:
                // Split su ELET_BASE_Q## — ELETTRICITÀ_XX (cattura Q## e COMP separatamente)
                return preg_split('/(ELET_BASE_Q\d+)\s*[—–-]\s*([A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_11_ELET_BULLET:
                // Split su Q## seguito da spazio (senza punto)
                return preg_split('/\n(Q\d+)\s+(?=[A-Z])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_12_ELET_PIPE:
                // Split su Q## | Codice competenza: COMP (cattura Q## e COMP)
                return preg_split('/\n(Q\d+)\s*\|\s*Codice\s*competenza:\s*([A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+)\n/ui', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_13_ELET_DOT:
                // Split su Q##. (con punto)
                return preg_split('/\n(Q\d+)\.\s/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            case self::FORMAT_14_ELET_NEWLINE:
                // Split su Q## semplice (numero su riga singola)
                return preg_split('/\n(Q\d+)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

            // --- LOGISTICA ---
            case self::FORMAT_15_LOGISTICA:
                // Split su "1. LOG_BASE_Q01" (numero + punto + codice)
                return preg_split('/\n\d+\.\s*([A-Z_]+_Q\d+)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

            default:
                // Split su Q## semplice (tutti gli altri formati)
                return preg_split('/\n(Q\d+)\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        }
    }
    
    /**
     * Parsa una singola domanda
     * 
     * @param string $id Identificatore della domanda
     * @param string $content Contenuto della domanda
     * @param string $format Formato del file
     * @param string $pre_extracted_competency Competenza già estratta (per formati con cattura multipla)
     * @return array|null Array con dati domanda o null se non valida
     */
    private function parse_single_question($id, $content, $format, $pre_extracted_competency = '') {
        // Estrai numero domanda dall'ID
        if (preg_match('/Q?(\d+)/', $id, $m)) {
            $num = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        } else {
            $num = $id;
        }
        
        $question = [
            'num' => $num,
            'text' => '',
            'answers' => [],
            'correct_answer' => '',
            'competency' => '',
            'competency_valid' => false,
            'competency_guessed' => false,
            'status' => 'ok',
            'issues' => [],
            'format' => $format
        ];
        
        // === ESTRAI COMPETENZA (in base al formato) ===
        if (!empty($pre_extracted_competency)) {
            // Competenza già estratta dallo split
            $question['competency'] = $this->clean_competency_code($pre_extracted_competency);
            $question['competency_guessed'] = false;
        } else {
            $competency_found = $this->extract_competency($content, $format);
            $question['competency'] = $competency_found['code'];
            $question['competency_guessed'] = $competency_found['guessed'];
        }
        
        // === ESTRAI RISPOSTA CORRETTA (in base al formato) ===
        $question['correct_answer'] = $this->extract_correct_answer($content, $format);
        
        // === ESTRAI TESTO DOMANDA ===
        $question['text'] = $this->extract_question_text($content, $format);
        
        // === ESTRAI RISPOSTE A, B, C, D ===
        $question['answers'] = $this->extract_answers($content, $format);
        
        // === VALIDAZIONE ===
        $this->validate_question($question);
        
        return $question;
    }
    
    /**
     * Estrae il codice competenza dal contenuto
     * Supporta tutti i 14 formati
     * 
     * @param string $content Contenuto della domanda
     * @param string $format Formato del file
     * @return array ['code' => codice, 'guessed' => bool se indovinato]
     */
    private function extract_competency($content, $format) {
        $result = ['code' => '', 'guessed' => false];
        
        switch ($format) {
            // --- AUTOVEICOLO ---
            case self::FORMAT_1_AUTOV_BASE:
                // Competenza collegata: AUTOMOBILE_MR_A1
                if (preg_match('/Competenza\s+collegata:\s*([A-Z_]+_[A-Z0-9_]+)/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_2_AUTOV_APPR:
                // Competenza (CO): AUTOMOBILE_MAu_G10
                if (preg_match('/Competenza\s*\(CO\):\s*([A-Z_]+_[A-Z0-9_]+)/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_7_AUTOV_APPR36:
                // Il contenuto inizia con il codice competenza (dopo split su "Competenza:")
                $first_line = strtok(trim($content), "\n");
                if (preg_match('/^([A-Z_]+_[A-Z0-9_]+)/i', $first_line, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            // --- ELETTRONICA ---
            case self::FORMAT_3_ELETT_BASE:
                // Competenza: AUTOMAZIONE_OA_A1 (all'inizio o nel mezzo)
                if (preg_match('/Competenza:\s*([A-Z_]+_[A-Z0-9_]+)/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_4_ELETT_APPR06:
                // Competenza già estratta nello split, fallback al contenuto
                if (preg_match('/([A-Z]{3,}_[A-Z0-9]+_[A-Z0-9]+)/u', $content, $m)) {
                    $result['code'] = strtoupper($m[1]);
                    return $result;
                }
                break;
            
            // --- CHIMICA ---
            case self::FORMAT_5_CHIM_BASE:
                // Competenza: CHIMFARM_2M_04 | Risposta corretta: X |
                if (preg_match('/Competenza:\s*([A-Z_]+_[A-Z0-9_]+)\s*\|/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_6_CHIM_APPR00:
            case self::FORMAT_8_CHIM_APPR23:
                // Competenza (F2): CHIMFARM_9A_01
                if (preg_match('/Competenza\s*\(F2\):\s*([A-Z_]+_[A-Z0-9_]+)/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            // --- ELETTRICITÀ (NUOVI) ---
            case self::FORMAT_10_ELET_BASE:
                // Competenza già estratta nello split
                break;
            
            case self::FORMAT_11_ELET_BULLET:
                // Competenza: ELETTRICITÀ_XX alla fine del blocco
                if (preg_match('/Competenza:\s*([A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+)/ui', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_12_ELET_PIPE:
                // Competenza già estratta nello split
                break;
            
            case self::FORMAT_13_ELET_DOT:
                // Codice competenza: ELETTRICITÀ_XX
                if (preg_match('/Codice\s*competenza:\s*([A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+)/ui', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_14_ELET_NEWLINE:
                // Codice: ELETTRICITÀ_XX (su riga separata)
                if (preg_match('/Codice:\s*([A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+)/ui', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
            
            case self::FORMAT_9_NO_COMP:
                // Nessuna competenza nel file
                return $result;

            // --- LOGISTICA ---
            case self::FORMAT_15_LOGISTICA:
                // Competenza: LOGISTICA_LO_F5
                if (preg_match('/Competenza:\s*([A-Z_]+_[A-Z0-9_]+)/i', $content, $m)) {
                    $result['code'] = $this->clean_competency_code($m[1]);
                    return $result;
                }
                break;
        }

        // Fallback generico: cerca pattern SETTORE_XX_YY ovunque
        if (preg_match('/([A-Z]{3,}_[A-Z0-9]+_[A-Z0-9]+)/i', $content, $m)) {
            $result['code'] = strtoupper($m[1]);
            $result['guessed'] = true;
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Pulisce il codice competenza
     * 
     * @param string $code Codice grezzo
     * @return string Codice pulito in UPPERCASE con normalizzazione ELETTRICITÀ
     */
    private function clean_competency_code($code) {
        $code = str_replace(['\\', '  '], ['', ' '], $code);
        $code = trim($code);
        $code = mb_strtoupper($code, 'UTF-8');
        // Normalizza ELETTRICITA -> ELETTRICITÀ (se non ha già l'accento)
        $code = preg_replace('/ELETTRICITA(?!À)/u', 'ELETTRICITÀ', $code);
        return $code;
    }
    
    /**
     * Estrae la risposta corretta in base al formato
     * 
     * @param string $content Contenuto della domanda
     * @param string $format Formato del file
     * @return string Lettera (A, B, C, D) o vuoto
     */
    private function extract_correct_answer($content, $format) {
        // FORMATO 2 AUTOVEICOLO: Checkmark ✔ alla fine della risposta corretta
        if ($format === self::FORMAT_2_AUTOV_APPR) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (strpos($line, '✔') !== false || strpos($line, '✓') !== false) {
                    if (preg_match('/^\s*([A-Da-d])[\)\.]/', $line, $m)) {
                        return strtoupper($m[1]);
                    }
                }
            }
        }
        
        // FORMATO 11 ELETTRICITÀ BULLET: Checkmark in bullet list
        if ($format === self::FORMAT_11_ELET_BULLET) {
            $lines = explode("\n", $content);
            $bullet_count = 0;
            foreach ($lines as $line) {
                if (preg_match('/^\s*-\s+/', $line)) {
                    $bullet_count++;
                    if (strpos($line, '✔') !== false || 
                        strpos($line, '✓') !== false || 
                        strpos($line, '✅') !== false) {
                        // A=1, B=2, C=3, D=4
                        return chr(64 + $bullet_count);
                    }
                }
            }
        }
        
        // FORMATO 5 CHIMICA: | Risposta corretta: X |
        if ($format === self::FORMAT_5_CHIM_BASE) {
            if (preg_match('/\|\s*Risposta\s+corretta:\s*([A-Da-d])/i', $content, $m)) {
                return strtoupper($m[1]);
            }
        }
        
        // Pattern con checkmark emoji ✅ Risposta corretta: X
        if (preg_match('/✅\s*Risposta\s+corretta:\s*([A-Da-d])/ui', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern standard: Risposta corretta: X
        if (preg_match('/Risposta\s+corretta:\s*([A-Da-d])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern alternativo: Risposta: X
        if (preg_match('/Risposta:\s*([A-Da-d])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern: Corretta: X
        if (preg_match('/Corretta:\s*([A-Da-d])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        return '';
    }
    
    /**
     * Estrae il testo della domanda
     * 
     * @param string $content Contenuto completo
     * @param string $format Formato del file
     * @return string Testo della domanda
     */
    private function extract_question_text($content, $format) {
        $text = $content;

        // Per FORMATO_7 AUTOVEICOLO, rimuovi la competenza all'inizio
        if ($format === self::FORMAT_7_AUTOV_APPR36) {
            $text = preg_replace('/^[A-Z_]+_[A-Z0-9_]+\s*\n/i', '', trim($text));
        }

        // Per FORMATO_3 ELETTRONICA, la competenza è dopo Q01 ma prima della domanda
        if ($format === self::FORMAT_3_ELETT_BASE) {
            $text = preg_replace('/^Competenza:\s*[A-Z_]+_[A-Z0-9_]+\s*\n/im', '', $text);
        }

        // Per FORMATO_14 ELETTRICITÀ, rimuovi Competenza: e Codice: all'inizio
        if ($format === self::FORMAT_14_ELET_NEWLINE) {
            $text = preg_replace('/^Competenza:[^\n]+\n/im', '', $text);
            $text = preg_replace('/^Codice:\s*[A-Z_ÀÈÉÌÒÙ]+_[A-Z0-9_]+\s*\n/imu', '', $text);
        }

        // Per FORMATO_15 LOGISTICA, rimuovi "Competenza: LOGISTICA_XX" all'inizio
        if ($format === self::FORMAT_15_LOGISTICA) {
            $text = preg_replace('/^Competenza:\s*[A-Z_]+_[A-Z0-9_]+\s*\n/im', '', trim($text));
        }

        // Taglia prima di markers finali (ma NON per formati che hanno Competenza: all'inizio)
        $skip_competenza_marker = in_array($format, [
            self::FORMAT_3_ELETT_BASE,
            self::FORMAT_14_ELET_NEWLINE,
            self::FORMAT_15_LOGISTICA
        ]);

        foreach (['Risposta corretta', 'Competenza collegata', 'Competenza (CO)',
                  'Competenza (F2)', 'Codice competenza:', 'Codice:',
                  'Rif. Master', 'Confidenza:'] as $marker) {
            $pos = stripos($text, $marker);
            if ($pos !== false) {
                $text = substr($text, 0, $pos);
            }
        }
        
        // Trova la prima risposta A) o A.
        if (preg_match('/^(.*?)(?=\n\s*[Aa][\)\.])/s', $text, $m)) {
            $text = $m[1];
        }
        
        // Per FORMATO_11 bullet, rimuovi le righe con -
        if ($format === self::FORMAT_11_ELET_BULLET) {
            $lines = explode("\n", $text);
            $clean_lines = [];
            foreach ($lines as $line) {
                if (!preg_match('/^\s*-\s+/', $line)) {
                    $clean_lines[] = $line;
                }
            }
            $text = implode("\n", $clean_lines);
        }
        
        // Pulisci
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return $text;
    }
    
    /**
     * Estrae le risposte A, B, C, D
     * 
     * @param string $content Contenuto completo
     * @param string $format Formato del file
     * @return array Array di risposte ['A' => testo, 'B' => testo, ...]
     */
    private function extract_answers($content, $format) {
        $answers = [];
        
        // Per FORMATO_11 ELETTRICITÀ bullet, estrai da elenco puntato
        if ($format === self::FORMAT_11_ELET_BULLET) {
            $lines = explode("\n", $content);
            $letters = ['A', 'B', 'C', 'D'];
            $idx = 0;
            foreach ($lines as $line) {
                if (preg_match('/^\s*-\s+(.+)$/u', $line, $m)) {
                    if ($idx < 4) {
                        $text = trim($m[1]);
                        // Rimuovi checkmark
                        $text = preg_replace('/\s*[✔✓✅]\s*$/u', '', $text);
                        $answers[$letters[$idx]] = $text;
                        $idx++;
                    }
                }
            }
            if (count($answers) >= 2) {
                return $answers;
            }
        }
        
        // Prepara il contenuto in base al formato
        $clean_content = $content;
        
        // Per LOGISTICA, rimuovi la riga Competenza: all'inizio prima di cercare risposte
        if ($format === self::FORMAT_15_LOGISTICA) {
            $clean_content = preg_replace('/^Competenza:\s*[A-Z_]+_[A-Z0-9_]+\s*\n/im', '', trim($clean_content));
        }

        // Per alcuni formati, NON tagliare prima di "Competenza" perché è all'inizio/nel mezzo
        // FORMATO_13_ELET_DOT ha Codice competenza PRIMA delle risposte, quindi non tagliare
        if (!in_array($format, [self::FORMAT_7_AUTOV_APPR36, self::FORMAT_3_ELETT_BASE,
                                self::FORMAT_14_ELET_NEWLINE, self::FORMAT_13_ELET_DOT,
                                self::FORMAT_15_LOGISTICA])) {
            // Taglia prima di markers di competenza
            foreach (['Competenza collegata', 'Competenza (CO)', 'Competenza (F2)',
                      'Competenza:', 'Codice competenza:', 'Codice:'] as $marker) {
                $pos = stripos($clean_content, $marker);
                if ($pos !== false) {
                    $clean_content = substr($clean_content, 0, $pos);
                }
            }
        }
        
        // Taglia sempre prima di markers finali
        foreach (['Risposta corretta', 'Rif. Master', 'Confidenza:'] as $marker) {
            $pos = stripos($clean_content, $marker);
            if ($pos !== false) {
                $clean_content = substr($clean_content, 0, $pos);
            }
        }
        
        // METODO 1: Pattern per risposte standard (A), A., A-, A:)
        $lines = explode("\n", $clean_content);
        $current_letter = '';
        $current_text = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Salta linee di competenza
            if (preg_match('/^(Competenza|Codice)/i', $line)) {
                continue;
            }
            
            // Pattern: A) A. A- A: o solo "A " seguito da testo
            if (preg_match('/^([A-Da-d])[\)\.\-:\s]\s*(.+)$/u', $line, $m)) {
                // Salva risposta precedente
                if ($current_letter && $current_text) {
                    $text = trim(implode(' ', $current_text));
                    // Rimuovi checkmark
                    $text = preg_replace('/\s*[✔✓✅]\s*$/u', '', $text);
                    $text = rtrim($text, '.');
                    if (strlen($text) > 2) {
                        $answers[$current_letter] = $text;
                    }
                }
                
                // Inizia nuova risposta
                $current_letter = strtoupper($m[1]);
                $current_text = [$m[2]];
            } elseif ($current_letter && $line && !preg_match('/^(Risposta|Competenza|Codice|Rif\.|Confidenza)/i', $line)) {
                // Continua risposta corrente
                $current_text[] = $line;
            }
        }
        
        // Salva ultima risposta
        if ($current_letter && $current_text) {
            $text = trim(implode(' ', $current_text));
            $text = preg_replace('/\s*[✔✓✅]\s*$/u', '', $text);
            $text = rtrim($text, '.');
            if (strlen($text) > 2) {
                $answers[$current_letter] = $text;
            }
        }
        
        // METODO 2: Se nessuna risposta trovata, prova regex su tutto il contenuto
        if (empty($answers)) {
            if (preg_match_all('/(?:^|\n)\s*([A-Da-d])[\)\.\-:]\s*([^\n]+)/u', $clean_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $letter = strtoupper($m[1]);
                    $text = trim($m[2]);
                    $text = preg_replace('/\s*[✔✓✅]\s*$/u', '', $text);
                    if (!empty($text) && strlen($text) > 2) {
                        $answers[$letter] = $text;
                    }
                }
            }
        }
        
        // METODO 3: Cerca elenchi puntati convertiti (●, •, -, *)
        if (empty($answers)) {
            $bullet_answers = [];
            $letter_index = 0;
            $letters = ['A', 'B', 'C', 'D'];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^[●•\-\*]\s*(.+)$/u', $line, $m)) {
                    if ($letter_index < 4) {
                        $bullet_answers[$letters[$letter_index]] = trim($m[1]);
                        $letter_index++;
                    }
                }
            }
            
            if (count($bullet_answers) >= 2) {
                $answers = $bullet_answers;
            }
        }
        
        return $answers;
    }
    
    /**
     * Valida una domanda e imposta status/issues
     * 
     * @param array $question Domanda da validare (passata per riferimento)
     */
    private function validate_question(&$question) {
        $issues = [];
        $status = 'ok';
        
        // Verifica testo domanda
        if (empty($question['text']) || strlen($question['text']) < 10) {
            $issues[] = 'Testo domanda mancante o troppo corto';
            $status = 'error';
        }
        
        // Verifica risposte
        if (count($question['answers']) < 2) {
            $issues[] = 'Meno di 2 risposte trovate';
            $status = 'error';
        } elseif (count($question['answers']) < 4) {
            $issues[] = 'Trovate solo ' . count($question['answers']) . ' risposte (dovrebbero essere 4)';
            if ($status !== 'error') $status = 'warning';
        }
        
        // Verifica risposta corretta
        if (empty($question['correct_answer'])) {
            $issues[] = 'Risposta corretta non trovata';
            $status = 'error';
        } elseif (!isset($question['answers'][$question['correct_answer']])) {
            $issues[] = 'Risposta corretta "' . $question['correct_answer'] . '" non presente tra le opzioni';
            $status = 'error';
        }
        
        // Verifica competenza (solo se non è FORMATO_9_NO_COMP)
        if ($question['format'] !== self::FORMAT_9_NO_COMP) {
            if (empty($question['competency'])) {
                $issues[] = 'Codice competenza non trovato';
                $status = 'error';
            } else {
                // Verifica se esiste nel framework
                if (!empty($this->valid_competencies)) {
                    if (in_array($question['competency'], $this->valid_competencies)) {
                        $question['competency_valid'] = true;
                    } else {
                        $issues[] = 'Competenza "' . $question['competency'] . '" non trovata nel framework';
                        if ($status !== 'error') $status = 'warning';
                    }
                }
                
                // Verifica settore
                if (!empty($this->sector)) {
                    if (strpos($question['competency'], $this->sector . '_') !== 0) {
                        $issues[] = 'Competenza non appartiene al settore ' . $this->sector;
                        if ($status !== 'error') $status = 'warning';
                    }
                }
                
                // Se è stata indovinata, segnala
                if ($question['competency_guessed']) {
                    $issues[] = 'Competenza estratta automaticamente (verifica)';
                    if ($status !== 'error') $status = 'warning';
                }
            }
        } else {
            // Per FORMATO_9, la mancanza di competenza è attesa
            $issues[] = 'File senza competenze - richiede Excel per mapping';
            $status = 'warning';
        }
        
        $question['status'] = $status;
        $question['issues'] = $issues;
        
        // Aggiorna contatori globali
        if ($status === 'error') {
            $this->errors[] = "Q{$question['num']}: " . implode('; ', $issues);
        } elseif ($status === 'warning') {
            $this->warnings[] = "Q{$question['num']}: " . implode('; ', $issues);
        }
    }
    
    /**
     * Restituisce il risultato del parsing
     * 
     * @return array
     */
    private function get_result() {
        $valid_count = 0;
        $warning_count = 0;
        $error_count = 0;
        
        foreach ($this->questions as $q) {
            switch ($q['status']) {
                case 'ok':
                    $valid_count++;
                    break;
                case 'warning':
                    $warning_count++;
                    break;
                case 'error':
                    $error_count++;
                    break;
            }
        }
        
        return [
            'success' => empty($this->errors) || $valid_count > 0,
            'filename' => $this->filename,
            'format' => $this->detected_format,
            'total_questions' => count($this->questions),
            'valid_count' => $valid_count,
            'warning_count' => $warning_count,
            'error_count' => $error_count,
            'questions' => $this->questions,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'can_import' => $valid_count > 0 || $warning_count > 0
        ];
    }
    
    /**
     * Restituisce il formato rilevato
     * 
     * @return string
     */
    public function get_detected_format() {
        return $this->detected_format;
    }
    
    /**
     * Aggiorna una domanda con correzioni manuali
     * 
     * @param int $index Indice della domanda nell'array
     * @param array $corrections Array con correzioni ['competency' => X, 'correct_answer' => Y]
     * @return bool Successo
     */
    public function update_question($index, $corrections) {
        if (!isset($this->questions[$index])) {
            return false;
        }
        
        if (isset($corrections['competency'])) {
            $this->questions[$index]['competency'] = $this->clean_competency_code($corrections['competency']);
            $this->questions[$index]['competency_guessed'] = false;
        }
        
        if (isset($corrections['correct_answer'])) {
            $this->questions[$index]['correct_answer'] = strtoupper($corrections['correct_answer']);
        }
        
        // Rivalida
        $this->validate_question($this->questions[$index]);
        
        return true;
    }
    
    /**
     * Restituisce le domande parsate
     * 
     * @return array
     */
    public function get_questions() {
        return $this->questions;
    }
    
    /**
     * Restituisce suggerimenti di competenze simili
     * 
     * @param string $partial Codice parziale o errato
     * @return array Array di codici competenza simili
     */
    public function suggest_competencies($partial) {
        if (empty($this->valid_competencies)) {
            return [];
        }
        
        $suggestions = [];
        $partial_upper = strtoupper($partial);
        
        foreach ($this->valid_competencies as $code) {
            if (strpos($code, $partial_upper) !== false) {
                $suggestions[] = $code;
            } elseif (levenshtein($partial_upper, $code) <= 3) {
                $suggestions[] = $code;
            }
        }
        
        return array_slice($suggestions, 0, 10);
    }
    
    /**
     * Genera il nome quiz suggerito dal nome file
     * 
     * @return string Nome suggerito
     */
    public function get_suggested_quiz_name() {
        $name = $this->filename;
        $name = preg_replace('/\.docx?$/i', '', $name);
        $name = str_replace(['_', '-', '.'], ' ', $name);
        $name = preg_replace('/\s*(V\d+|GOLD|F2|LOGSTYLE|NOMI)\s*/i', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
    
    /**
     * Descrive il formato rilevato in modo user-friendly
     * 
     * @return string Descrizione del formato
     */
    public function get_format_description() {
        $descriptions = [
            // AUTOVEICOLO
            self::FORMAT_1_AUTOV_BASE => 'AUTOVEICOLO Test Base (AUT_BASE_Q01 + Competenza collegata)',
            self::FORMAT_2_AUTOV_APPR => 'AUTOVEICOLO Approfondimenti (Q01 (ID) + checkmark ✔)',
            self::FORMAT_7_AUTOV_APPR36 => 'AUTOVEICOLO Appr03-06 (Q01 – Competenza: XXX)',
            
            // ELETTRONICA
            self::FORMAT_3_ELETT_BASE => 'ELETTRONICA Base/Appr (Q01 + Competenza: inizio)',
            self::FORMAT_4_ELETT_APPR06 => 'ELETTRONICA Appr03/06 (Q01 – COMP nel header)',
            
            // CHIMICA
            self::FORMAT_5_CHIM_BASE => 'CHIMICA Base (Competenza: XXX | Risposta: X |)',
            self::FORMAT_6_CHIM_APPR00 => 'CHIMICA Appr00 (Q01 (ID) + Competenza (F2))',
            self::FORMAT_8_CHIM_APPR23 => 'CHIMICA Appr02/03 (Competenza (F2) dopo risposte)',
            
            // ELETTRICITÀ
            self::FORMAT_10_ELET_BASE => 'ELETTRICITÀ Test Base (ELET_BASE_Q01 — ELETTRICITÀ_XX)',
            self::FORMAT_11_ELET_BULLET => 'ELETTRICITÀ Appr00 (Bullet list - + checkmark ✔)',
            self::FORMAT_12_ELET_PIPE => 'ELETTRICITÀ Appr04 (Q01 | Codice competenza:)',
            self::FORMAT_13_ELET_DOT => 'ELETTRICITÀ Appr01/02 (Q01. + Codice competenza:)',
            self::FORMAT_14_ELET_NEWLINE => 'ELETTRICITÀ Appr03/05/06 (Q##\nCodice: ELETTRICITÀ)',
            
            // LOGISTICA
            self::FORMAT_15_LOGISTICA => 'LOGISTICA (1. LOG_BASE_Q01 + Competenza: LOGISTICA_XX)',

            // GENERICI
            self::FORMAT_9_NO_COMP => 'File senza competenze (richiede Excel)',
            self::FORMAT_UNKNOWN => 'Formato non riconosciuto'
        ];

        return $descriptions[$this->detected_format] ?? 'Formato sconosciuto';
    }
    
    /**
     * Restituisce tutti i formati supportati
     * 
     * @return array Array di [costante => descrizione]
     */
    public static function get_supported_formats() {
        return [
            // AUTOVEICOLO
            self::FORMAT_1_AUTOV_BASE => 'AUTOVEICOLO Test Base',
            self::FORMAT_2_AUTOV_APPR => 'AUTOVEICOLO Approfondimenti 0-2',
            self::FORMAT_7_AUTOV_APPR36 => 'AUTOVEICOLO Approfondimenti 3-6',
            
            // ELETTRONICA
            self::FORMAT_3_ELETT_BASE => 'ELETTRONICA Base/Appr',
            self::FORMAT_4_ELETT_APPR06 => 'ELETTRONICA Appr03/06',
            
            // CHIMICA
            self::FORMAT_5_CHIM_BASE => 'CHIMICA Base/Appr01',
            self::FORMAT_6_CHIM_APPR00 => 'CHIMICA Appr00',
            self::FORMAT_8_CHIM_APPR23 => 'CHIMICA Appr02/03',
            
            // ELETTRICITÀ
            self::FORMAT_10_ELET_BASE => 'ELETTRICITÀ Test Base',
            self::FORMAT_11_ELET_BULLET => 'ELETTRICITÀ Appr00 (Bullet)',
            self::FORMAT_12_ELET_PIPE => 'ELETTRICITÀ Appr04 (Pipe)',
            self::FORMAT_13_ELET_DOT => 'ELETTRICITÀ Appr01/02 (Dot)',
            self::FORMAT_14_ELET_NEWLINE => 'ELETTRICITÀ Appr03/05/06 (Newline)',
            
            // LOGISTICA
            self::FORMAT_15_LOGISTICA => 'LOGISTICA',

            // GENERICI
            self::FORMAT_9_NO_COMP => 'File senza competenze',
        ];
    }
}
