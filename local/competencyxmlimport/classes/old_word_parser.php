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
 * Parser per file Word (.docx) contenenti domande quiz
 * 
 * Estrae domande, risposte e competenze da file Word nel formato FTM.
 * Supporta multipli formati di documento generati da ChatGPT.
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM - Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    
    /** @var string Settore corrente (es. CHIMFARM, MECCANICA) */
    private $sector = '';
    
    /** @var array Competenze valide nel framework */
    private $valid_competencies = [];
    
    /**
     * Costruttore
     * 
     * @param string $sector Settore per la validazione competenze (es. CHIMFARM)
     * @param array $valid_competencies Array di codici competenza validi nel framework
     */
    public function __construct($sector = '', $valid_competencies = []) {
        $this->sector = $sector;
        $this->valid_competencies = $valid_competencies;
    }
    
    /**
     * Parsing di un file Word
     * 
     * @param string $filepath Percorso completo al file .docx
     * @return array Risultato con 'success', 'questions', 'errors', 'warnings'
     */
    public function parse_file($filepath) {
        $this->errors = [];
        $this->warnings = [];
        $this->questions = [];
        
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
        
        // Parsa le domande
        $this->parse_questions();
        
        return $this->get_result();
    }
    
    /**
     * Estrae il testo da un file .docx
     * 
     * Un file .docx è uno ZIP contenente file XML.
     * Il testo principale è in word/document.xml
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
        
        // Parsa XML
        $xml = simplexml_load_string($xml_content);
        if ($xml === false) {
            return false;
        }
        
        // Registra namespace Word
        $namespaces = $xml->getNamespaces(true);
        $w = isset($namespaces['w']) ? $namespaces['w'] : 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        
        // Estrai tutto il testo dai paragrafi
        $text_parts = [];
        
        // Cerca tutti gli elementi w:t (text)
        $xml->registerXPathNamespace('w', $w);
        $text_nodes = $xml->xpath('//w:t');
        
        $current_paragraph = '';
        $last_was_paragraph_end = false;
        
        foreach ($text_nodes as $node) {
            $text = (string)$node;
            $current_paragraph .= $text;
            
            // Verifica se è fine paragrafo guardando il parent
            $parent = $node->xpath('parent::*')[0] ?? null;
            if ($parent) {
                $grandparent = $parent->xpath('parent::*')[0] ?? null;
            }
        }
        
        // Metodo alternativo: estrai testo rimuovendo tag XML
        $text = strip_tags(str_replace('<', ' <', $xml_content));
        
        // Pulisci spazi multipli ma mantieni newline
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Ricostruisci struttura paragrafi dal XML originale
        $text = $this->extract_paragraphs_from_xml($xml_content);
        
        return $text;
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
     */
    private function parse_questions() {
        $text = $this->text;
        
        // Pattern per trovare domande: Q01, Q02, etc. (con possibili varianti)
        // Supporta: Q01, AUT_BASE_Q01, PREFISSO_Q01
        $pattern = '/\n(?:[A-Z_]+_)?Q(\d+)\n/';
        
        // Split per domande
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Il primo elemento è l'header (prima di Q01), lo saltiamo
        // Poi alternano: numero domanda, contenuto, numero domanda, contenuto...
        
        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $question_num = $parts[$i];
            $content = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
            
            $question = $this->parse_single_question($question_num, $content);
            if ($question) {
                $this->questions[] = $question;
            }
        }
    }
    
    /**
     * Parsa una singola domanda
     * 
     * @param string $num Numero della domanda
     * @param string $content Contenuto della domanda
     * @return array|null Array con dati domanda o null se non valida
     */
    private function parse_single_question($num, $content) {
        $question = [
            'num' => str_pad($num, 2, '0', STR_PAD_LEFT),
            'text' => '',
            'answers' => [],
            'correct_answer' => '',
            'competency' => '',
            'competency_valid' => false,
            'status' => 'ok', // ok, warning, error
            'issues' => []
        ];
        
        // === ESTRAI COMPETENZA ===
        $competency_found = $this->extract_competency($content);
        $question['competency'] = $competency_found['code'];
        $question['competency_guessed'] = $competency_found['guessed'];
        
        // === ESTRAI RISPOSTA CORRETTA ===
        $correct = $this->extract_correct_answer($content);
        $question['correct_answer'] = $correct;
        
        // === ESTRAI TESTO DOMANDA ===
        $question['text'] = $this->extract_question_text($content);
        
        // === ESTRAI RISPOSTE A, B, C, D ===
        $question['answers'] = $this->extract_answers($content);
        
        // === VALIDAZIONE ===
        $this->validate_question($question);
        
        return $question;
    }
    
    /**
     * Estrae il codice competenza dal contenuto
     * Supporta multipli formati
     * 
     * @param string $content Contenuto della domanda
     * @return array ['code' => codice, 'guessed' => bool se indovinato]
     */
    private function extract_competency($content) {
        $result = ['code' => '', 'guessed' => false];
        
        // FORMATO 1: Competenza: CHIMFARM_XXX | Risposta corretta: X
        if (preg_match('/Competenza:\s*(\S+)\s*\|/i', $content, $m)) {
            $result['code'] = $this->clean_competency_code($m[1]);
            return $result;
        }
        
        // FORMATO 2: Competenza (F2): CHIMFARM_XXX — descrizione
        if (preg_match('/Competenza\s*\(F2\):\s*(\S+)\s*(?:—|---|-)/i', $content, $m)) {
            $result['code'] = $this->clean_competency_code($m[1]);
            return $result;
        }
        
        // FORMATO 3: Cerca pattern SETTORE_XXX_YY ovunque nel testo
        $sector_pattern = $this->sector ? preg_quote($this->sector, '/') : '[A-Z]+';
        if (preg_match('/(' . $sector_pattern . '_[A-Z0-9]+_[A-Z0-9]+)/i', $content, $m)) {
            $result['code'] = strtoupper($m[1]);
            $result['guessed'] = true;
            return $result;
        }
        
        // FORMATO 4: Pattern generico SETTORE_XX_YY (più permissivo)
        if (preg_match('/([A-Z]{3,}_[A-Z0-9]{1,4}_[A-Z0-9]{1,4})/i', $content, $m)) {
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
     * @return string Codice pulito
     */
    private function clean_competency_code($code) {
        // Rimuovi backslash, underscore doppi, spazi
        $code = str_replace(['\\', '  '], ['', ' '], $code);
        $code = trim($code);
        $code = strtoupper($code);
        return $code;
    }
    
    /**
     * Estrae la risposta corretta
     * 
     * @param string $content Contenuto della domanda
     * @return string Lettera (A, B, C, D) o vuoto
     */
    private function extract_correct_answer($content) {
        // Pattern 1: Risposta corretta: X
        if (preg_match('/Risposta corretta:\s*([A-D])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern 2: | Risposta corretta: X |
        if (preg_match('/\|\s*Risposta corretta:\s*([A-D])\s*\|/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern 3: Risposta: X
        if (preg_match('/Risposta:\s*([A-D])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        // Pattern 4: Corretta: X
        if (preg_match('/Corretta:\s*([A-D])/i', $content, $m)) {
            return strtoupper($m[1]);
        }
        
        return '';
    }
    
    /**
     * Estrae il testo della domanda
     * 
     * @param string $content Contenuto completo
     * @return string Testo della domanda
     */
    private function extract_question_text($content) {
        // Il testo è tutto prima delle risposte A) B) C) D)
        // Taglia a "Competenza" se presente prima
        
        $text = $content;
        
        // Taglia prima di Competenza
        $comp_pos = stripos($text, 'Competenza');
        if ($comp_pos !== false) {
            $text = substr($text, 0, $comp_pos);
        }
        
        // Trova la prima risposta A) o A.
        if (preg_match('/^(.*?)(?=\n\s*A[\)\.])/s', $text, $m)) {
            $text = $m[1];
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
     * @return array Array di risposte ['A' => testo, 'B' => testo, ...]
     */
    private function extract_answers($content) {
        $answers = [];
        
        // Taglia prima di Competenza per non catturare testo dopo
        $comp_pos = stripos($content, 'Competenza');
        if ($comp_pos !== false) {
            $content = substr($content, 0, $comp_pos);
        }
        
        // Taglia anche prima di "Risposta corretta" per non includerlo nelle risposte
        $risp_pos = stripos($content, 'Risposta corretta');
        if ($risp_pos !== false) {
            $content = substr($content, 0, $risp_pos);
        }
        
        // Pattern per risposte: A) testo o A. testo (all'inizio riga)
        $lines = explode("\n", $content);
        $current_letter = '';
        $current_text = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Nuova risposta?
            if (preg_match('/^([A-D])[\)\.\s]\s*(.*)$/i', $line, $m)) {
                // Salva risposta precedente
                if ($current_letter && $current_text) {
                    $answers[$current_letter] = trim(implode(' ', $current_text));
                }
                
                // Inizia nuova risposta
                $current_letter = strtoupper($m[1]);
                $current_text = [$m[2]];
            } elseif ($current_letter && $line) {
                // Continua risposta corrente
                $current_text[] = $line;
            }
        }
        
        // Salva ultima risposta
        if ($current_letter && $current_text) {
            $answers[$current_letter] = trim(implode(' ', $current_text));
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
        
        // Verifica competenza
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
            // Match esatto parziale
            if (strpos($code, $partial_upper) !== false) {
                $suggestions[] = $code;
            }
            // Distanza Levenshtein per typo
            elseif (levenshtein($partial_upper, $code) <= 3) {
                $suggestions[] = $code;
            }
        }
        
        // Limita a 10 suggerimenti
        return array_slice($suggestions, 0, 10);
    }
    
    /**
     * Genera il nome quiz suggerito dal nome file
     * 
     * @return string Nome suggerito
     */
    public function get_suggested_quiz_name() {
        $name = $this->filename;
        
        // Rimuovi estensione
        $name = preg_replace('/\.docx?$/i', '', $name);
        
        // Rimuovi caratteri speciali comuni nei nomi file
        $name = str_replace(['_', '-', '.'], ' ', $name);
        
        // Rimuovi versioni e suffissi comuni
        $name = preg_replace('/\s*(V\d+|GOLD|F2|LOGSTYLE|NOMI)\s*/i', ' ', $name);
        
        // Pulisci spazi multipli
        $name = preg_replace('/\s+/', ' ', $name);
        
        return trim($name);
    }
}
