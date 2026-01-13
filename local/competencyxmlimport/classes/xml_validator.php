<?php
namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

/**
 * XML Validator - Validazione file XML domande pre-import
 * 
 * Verifica struttura XML, formato domande, codici competenza e risposte
 * prima dell'importazione in Moodle.
 * 
 * @package    local_competencyxmlimport
 * @author     Assistente AI per Cristian
 * @version    1.0
 */

class xml_validator {
    
    /** @var int ID del framework per verifica competenze */
    private $frameworkid;
    
    /** @var string Prefisso settore (es. AUTOMOBILE, MECCANICA) */
    private $sector;
    
    /** @var array Cache delle competenze dal framework */
    private $competencies_cache = [];
    
    /** @var array Pattern regex per estrazione competenza */
    private $competency_patterns = [];
    
    /**
     * Costruttore
     * @param int $frameworkid ID del framework competenze
     * @param string $sector Prefisso settore (es. AUTOMOBILE)
     */
    public function __construct($frameworkid = 0, $sector = '') {
        $this->frameworkid = $frameworkid;
        $this->sector = $sector;
        
        if ($frameworkid > 0) {
            $this->load_competencies_from_framework($frameworkid);
        }
        
        // Costruisci pattern per il settore
        $this->build_patterns($sector);
    }
    
    /**
     * Costruisce i pattern regex per il settore
     * @param string $sector
     */
    private function build_patterns($sector) {
        if (empty($sector)) {
            // Pattern generici
            $this->competency_patterns = [
                '/([A-Z]+_[A-Za-z]+_[A-Z0-9]+)/i',
                '/([A-Z]+_[A-Za-z0-9]+_[A-Za-z0-9]+)/i',
            ];
        } else {
            // Pattern specifici per settore
            $escaped = preg_quote($sector, '/');
            $this->competency_patterns = [
                '/(' . $escaped . '_[A-Za-z]+_[A-Z0-9]+)/i',
                '/(' . $escaped . '_[A-Za-z0-9]+_[A-Za-z0-9]+)/i',
                '/(' . $escaped . '_[A-Za-z0-9_]+)/i',
            ];
        }
    }
    
    /**
     * Carica competenze dal framework Moodle
     * @param int $frameworkid
     */
    private function load_competencies_from_framework($frameworkid) {
        global $DB;
        
        $competencies = $DB->get_records('competency', 
            ['competencyframeworkid' => $frameworkid], 
            '', 
            'id, idnumber, shortname'
        );
        
        foreach ($competencies as $comp) {
            if (!empty($comp->idnumber)) {
                $this->competencies_cache[strtoupper($comp->idnumber)] = $comp;
            }
        }
    }
    
    /**
     * Valida un file XML
     * @param string $filepath Percorso file XML
     * @param string $filename Nome file originale
     * @return array Risultati validazione
     */
    public function validate_file($filepath, $filename = '') {
        $results = [
            'filename' => $filename ?: basename($filepath),
            'total' => 0,
            'ok' => 0,
            'warnings' => 0,
            'errors' => 0,
            'questions' => [],
            'global_issues' => [],
            'can_proceed' => true  // Se false, blocca "Avanti"
        ];
        
        // Verifica file esiste
        if (!file_exists($filepath)) {
            $results['global_issues'][] = 'File non trovato';
            $results['can_proceed'] = false;
            return $results;
        }
        
        // Leggi contenuto
        $content = file_get_contents($filepath);
        if (empty($content)) {
            $results['global_issues'][] = 'File vuoto';
            $results['can_proceed'] = false;
            return $results;
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $results['global_issues'][] = 'Errore XML linea ' . $error->line . ': ' . trim($error->message);
            }
            libxml_clear_errors();
            $results['can_proceed'] = false;
            return $results;
        }
        
        // Valida ogni domanda
        $question_num = 0;
        foreach ($xml->question as $question) {
            $type = (string)$question['type'];
            
            // Salta categorie
            if ($type === 'category') {
                continue;
            }
            
            $question_num++;
            $results['total']++;
            
            $q_result = $this->validate_question($question, $question_num);
            $results['questions'][] = $q_result;
            
            // Aggiorna contatori
            if ($q_result['status'] === 'ok') {
                $results['ok']++;
            } elseif ($q_result['status'] === 'warning') {
                $results['warnings']++;
            } else {
                $results['errors']++;
            }
        }
        
        // Se ci sono errori critici, non permettere di procedere
        if ($results['errors'] > 0) {
            $results['can_proceed'] = false;
        }
        
        return $results;
    }
    
    /**
     * Valida una singola domanda
     * @param \SimpleXMLElement $question
     * @param int $num Numero progressivo
     * @return array Risultato validazione
     */
    private function validate_question($question, $num) {
        $result = [
            'num' => $num,
            'name' => '',
            'competency' => '',
            'answers' => 0,
            'correct' => 0,
            'status' => 'ok',
            'issues' => []
        ];
        
        // === 1. NOME DOMANDA ===
        $name_elem = $question->name->text ?? $question->n->text ?? null;
        $result['name'] = $name_elem ? $this->clean_text((string)$name_elem) : '';
        
        if (empty($result['name'])) {
            $result['issues'][] = 'Nome domanda mancante';
            $result['status'] = 'error';
        }
        
        // === 2. TESTO DOMANDA ===
        $text_elem = $question->questiontext->text ?? null;
        $qtext = $text_elem ? $this->clean_text((string)$text_elem) : '';
        
        if (empty($qtext)) {
            $result['issues'][] = 'Testo domanda mancante';
            $result['status'] = 'error';
        } elseif (strlen($qtext) < 15) {
            $result['issues'][] = 'Testo troppo breve';
            if ($result['status'] === 'ok') {
                $result['status'] = 'warning';
            }
        }
        
        // === 3. COMPETENZA ===
        $result['competency'] = $this->extract_competency($question, $result['name']);
        
        if (empty($result['competency'])) {
            $result['issues'][] = 'Competenza non trovata nel nome';
            $result['status'] = 'error';
        } else {
            // Verifica competenza esiste nel framework
            if (!empty($this->competencies_cache)) {
                $comp_upper = strtoupper($result['competency']);
                if (!isset($this->competencies_cache[$comp_upper])) {
                    $result['issues'][] = 'Competenza non esiste nel framework';
                    if ($result['status'] === 'ok') {
                        $result['status'] = 'warning';
                    }
                }
            }
            
            // Verifica formato competenza (se settore specificato)
            if (!empty($this->sector)) {
                if (stripos($result['competency'], $this->sector . '_') !== 0) {
                    $result['issues'][] = 'Competenza non del settore ' . $this->sector;
                    if ($result['status'] === 'ok') {
                        $result['status'] = 'warning';
                    }
                }
            }
        }
        
        // === 4. RISPOSTE ===
        $correct_count = 0;
        $answer_count = 0;
        
        foreach ($question->answer as $answer) {
            $answer_count++;
            $fraction = (float)$answer['fraction'];
            if ($fraction > 0) {
                $correct_count++;
            }
        }
        
        $result['answers'] = $answer_count;
        $result['correct'] = $correct_count;
        
        if ($answer_count === 0) {
            $result['issues'][] = 'Nessuna risposta';
            $result['status'] = 'error';
        } elseif ($answer_count < 4) {
            $result['issues'][] = "Solo $answer_count risposte (consigliato 4)";
            if ($result['status'] === 'ok') {
                $result['status'] = 'warning';
            }
        }
        
        if ($correct_count === 0) {
            $result['issues'][] = 'Nessuna risposta corretta';
            $result['status'] = 'error';
        } elseif ($correct_count > 1) {
            $single = (string)($question->single ?? 'true');
            if ($single === 'true' || $single === '1') {
                $result['issues'][] = "Multiple risposte corrette ($correct_count)";
                if ($result['status'] === 'ok') {
                    $result['status'] = 'warning';
                }
            }
        }
        
        // Se nessun problema, segna come OK
        if (empty($result['issues'])) {
            $result['issues'][] = 'OK';
        }
        
        return $result;
    }
    
    /**
     * Estrae il codice competenza dalla domanda
     * @param \SimpleXMLElement $question
     * @param string $name Nome domanda
     * @return string Codice competenza o stringa vuota
     */
    private function extract_competency($question, $name) {
        // 1. Prova a estrarre dal nome usando i pattern
        foreach ($this->competency_patterns as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return $matches[1];
            }
        }
        
        // 2. Prova a trovare nei tag
        if (isset($question->tags)) {
            foreach ($question->tags->tag as $tag) {
                $tag_text = $this->clean_text((string)$tag->text);
                foreach ($this->competency_patterns as $pattern) {
                    if (preg_match($pattern, $tag_text, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Pulisce il testo da HTML e spazi
     * @param string $text
     * @return string
     */
    private function clean_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Rimuovi CDATA
        $text = preg_replace('/<!\[CDATA\[|\]\]>/', '', $text);
        
        // Rimuovi tag HTML
        $text = strip_tags($text);
        
        // Decodifica entità HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Normalizza spazi
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Genera HTML per il riepilogo validazione
     * @param array $results Risultati validazione
     * @return string HTML
     */
    public static function render_validation_summary($results) {
        $html = '<div class="validation-summary">';
        
        // Badge stato generale
        if ($results['errors'] > 0) {
            $html .= '<div class="validation-status error">❌ ' . $results['errors'] . ' errori - Correggere prima di procedere</div>';
        } elseif ($results['warnings'] > 0) {
            $html .= '<div class="validation-status warning">⚠️ ' . $results['warnings'] . ' avvisi - Verifica consigliata</div>';
        } else {
            $html .= '<div class="validation-status ok">✅ Tutte le ' . $results['total'] . ' domande sono valide</div>';
        }
        
        // Statistiche
        $html .= '<div class="validation-stats">';
        $html .= '<span class="stat ok">' . $results['ok'] . ' OK</span>';
        $html .= '<span class="stat warning">' . $results['warnings'] . ' Warning</span>';
        $html .= '<span class="stat error">' . $results['errors'] . ' Errori</span>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Genera HTML per il dettaglio domande
     * @param array $results Risultati validazione
     * @param bool $show_all Mostra tutte o solo problemi
     * @return string HTML
     */
    public static function render_validation_details($results, $show_all = false) {
        $html = '<div class="validation-details">';
        
        foreach ($results['questions'] as $q) {
            // Se non show_all, mostra solo problemi
            if (!$show_all && $q['status'] === 'ok') {
                continue;
            }
            
            $status_class = $q['status'];
            $status_icon = $q['status'] === 'ok' ? '✅' : ($q['status'] === 'warning' ? '⚠️' : '❌');
            
            $html .= '<div class="validation-item ' . $status_class . '">';
            $html .= '<div class="item-header">';
            $html .= '<span class="item-icon">' . $status_icon . '</span>';
            $html .= '<span class="item-name">Q' . str_pad($q['num'], 2, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars(substr($q['name'], 0, 50)) . '</span>';
            $html .= '<span class="item-competency">' . htmlspecialchars($q['competency'] ?: '-') . '</span>';
            $html .= '</div>';
            
            if ($q['status'] !== 'ok') {
                $html .= '<div class="item-issues">';
                foreach ($q['issues'] as $issue) {
                    if ($issue !== 'OK') {
                        $html .= '<span class="issue">' . htmlspecialchars($issue) . '</span>';
                    }
                }
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
