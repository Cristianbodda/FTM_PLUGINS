<?php
namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/bank/competenciesbyquestion/classes/local/manager.php');

/**
 * IMPORTER XML - VERSIONE CORRETTA PER CODICI CON LETTERE MINUSCOLE
 * Supporta formati come: AUTOMOBILE_MAu_H2, MECC_APPR_1_DT, MECCANICA_DT_01, ecc.
 * 
 * FIX 2025-12-11: Aggiunto supporto per lettere minuscole nei codici (es. MAu)
 */

class importer {
    
    private $frameworkid;
    private $categoryid;
    private $mapping;
    private $defaultlevel;
    private $log;
    
    public function __construct($frameworkid, $categoryid, $mapping = [], $defaultlevel = 1) {
        $this->frameworkid = $frameworkid;
        $this->categoryid = $categoryid;
        $this->mapping = $mapping;
        $this->defaultlevel = $defaultlevel;
        $this->log = [];
    }
    
    public static function parse_mapping_csv($csvcontent) {
        $mapping = [];
        $lines = explode("\n", $csvcontent);
        array_shift($lines);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = str_getcsv($line);
            if (count($parts) >= 3) {
                $code = trim($parts[0]);
                $level = (int)trim($parts[2]);
                $mapping[$code] = ['level' => $level];
            }
        }
        return $mapping;
    }
    
    /**
     * Estrae il codice competenza dal nome della domanda
     * 
     * VERSIONE CORRETTA - Supporta codici con lettere MAIUSCOLE e minuscole
     * 
     * Esempi supportati:
     * "Q01 - AUTOMOBILE_MAu_H2" → "AUTOMOBILE_MAu_H2"
     * "Q06 - AUTOMOBILE_MR_H3" → "AUTOMOBILE_MR_H3"
     * "MECC_APPR_1_DT - 01 Tratto continuo" → "MECC_APPR_1_DT"
     * "MECCANICA_LMB_04" → "MECCANICA_LMB_04"
     */
    private function extract_competency_code($questionname) {
        
        // Prima pulisci il nome
        $name = trim($questionname);
        
        // Pattern 1: Codice all'INIZIO seguito da " - " (es. "MECC_APPR_1_DT - 01 Descrizione")
        // CORRETTO: Ora include anche lettere minuscole [A-Za-z0-9_]
        if (preg_match('/^([A-Z][A-Za-z0-9_]+)\s*-/', $name, $matches)) {
            $code = trim($matches[1]);
            // Rimuovi underscore finale se presente
            $code = rtrim($code, '_');
            if ($this->is_valid_competency_code($code)) {
                return $code;
            }
        }
        
        // Pattern 2: "Q01 - CODICE_COMPETENZA" o "01 - CODICE_COMPETENZA"
        // Il codice è DOPO il trattino
        // CORRETTO: Ora include anche lettere minuscole [A-Za-z0-9_]
        if (preg_match('/^\w+\s*-\s*([A-Z][A-Za-z0-9_]+)/', $name, $matches)) {
            $code = trim($matches[1]);
            $code = rtrim($code, '_');
            if ($this->is_valid_competency_code($code)) {
                return $code;
            }
        }
        
        // Pattern 3: Cerca codice competenza ovunque nel testo
        // Formato: LETTERE_LETTERE/NUMERI_LETTERE/NUMERI (minimo 2 parti con underscore)
        // CORRETTO: Ora include anche lettere minuscole
        if (preg_match('/([A-Z][A-Za-z0-9]*(?:_[A-Za-z0-9]+){1,5})/', $name, $matches)) {
            $code = trim($matches[1]);
            $code = rtrim($code, '_');
            if ($this->is_valid_competency_code($code)) {
                return $code;
            }
        }
        
        return null;
    }
    
    /**
     * Verifica se un codice sembra essere un codice competenza valido
     */
    private function is_valid_competency_code($code) {
        // Deve contenere almeno un underscore
        if (strpos($code, '_') === false) {
            return false;
        }
        
        // Deve avere almeno 2 parti
        $parts = explode('_', $code);
        if (count($parts) < 2) {
            return false;
        }
        
        // Non deve essere troppo corto (minimo 5 caratteri tipo "A_B1")
        if (strlen($code) < 4) {
            return false;
        }
        
        return true;
    }
    
    private function find_competency($idnumber) {
        global $DB;
        return $DB->get_record('competency', [
            'competencyframeworkid' => $this->frameworkid,
            'idnumber' => $idnumber
        ]);
    }
    
    private function get_level_for_code($code) {
        if (isset($this->mapping[$code])) {
            return $this->mapping[$code]['level'];
        }
        return $this->defaultlevel;
    }
    
    private function get_questions_in_category($categoryid, $limit = 0) {
        global $DB;
        
        $sql = "SELECT q.*, qbe.id as bankentryid
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid = :categoryid
                AND qv.version = (
                    SELECT MAX(version)
                    FROM {question_versions}
                    WHERE questionbankentryid = qbe.id
                )
                ORDER BY q.id DESC";
        
        if ($limit > 0) {
            return $DB->get_records_sql($sql, ['categoryid' => $categoryid], 0, $limit);
        }
        return $DB->get_records_sql($sql, ['categoryid' => $categoryid]);
    }
    
    private function count_questions_in_category($categoryid) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT q.id)
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid = :categoryid
                AND qv.version = (
                    SELECT MAX(version)
                    FROM {question_versions}
                    WHERE questionbankentryid = qbe.id
                )";
        
        return $DB->count_records_sql($sql, ['categoryid' => $categoryid]);
    }
    
    private function get_level_name($level) {
        switch ($level) {
            case 1: return 'Base ⭐';
            case 2: return 'Intermedio ⭐⭐';
            case 3: return 'Avanzato ⭐⭐⭐';
            default: return 'Unknown';
        }
    }
    
    /**
     * Import XML con assegnazione livelli CORRETTA
     */
    public function import_xml($xmlcontent) {
        global $DB, $CFG;
        
        $imported = 0;
        $assigned = 0;
        $errors = 0;
        
        try {
            $this->log[] = "=== INIZIO IMPORT ===";
            
            // Verifica XML
            $xml = @simplexml_load_string($xmlcontent);
            if ($xml === false) {
                throw new \Exception('XML non valido o corrotto');
            }
            $this->log[] = "✅ XML caricato correttamente";
            
            // Conta codici competenza nell'XML (per info)
            $xmlcodes = 0;
            foreach ($xml->question as $questionxml) {
                $type = (string)$questionxml['type'];
                if ($type !== 'category') $xmlcodes++;
            }
            $this->log[] = "✅ Trovate {$xmlcodes} domande nell'XML";
            
            // Conta domande prima
            $questionsbefore = $this->count_questions_in_category($this->categoryid);
            $this->log[] = "📊 Domande prima dell'import: {$questionsbefore}";
            
            // Salva XML in file permanente
            $tempdir = !empty($CFG->tempdir) ? $CFG->tempdir : sys_get_temp_dir();
            $permanentfile = $tempdir . '/xml_import_' . time() . '_' . rand(1000, 9999) . '.xml';
            
            $written = file_put_contents($permanentfile, $xmlcontent);
            if ($written === false) {
                throw new \Exception('Impossibile salvare il file temporaneo');
            }
            $this->log[] = "📁 File salvato: {$permanentfile}";
            
            // Prepara categoria e context
            $category = $DB->get_record('question_categories', ['id' => $this->categoryid], '*', MUST_EXIST);
            $context = \context::instance_by_id($category->contextid);
            
            $this->log[] = "📂 Categoria: {$category->name} (ID: {$category->id})";
            
            // ========================================
            // IMPORT MOODLE NATIVO
            // ========================================
            
            $qformat = new \qformat_xml();
            $qformat->setCategory($category);
            $qformat->setContexts([$context]);
            $qformat->setCatfromfile(false);
            $qformat->setMatchgrades('nearest');
            $qformat->setStoponerror(false);
            $qformat->setFilename($permanentfile);
            
            $this->log[] = "🔄 Avvio import...";
            
            ob_start();
            $success = $qformat->importprocess($permanentfile);
            ob_end_clean();
            
            @unlink($permanentfile);
            
            // Conta domande dopo
            $questionsafter = $this->count_questions_in_category($this->categoryid);
            $imported = $questionsafter - $questionsbefore;
            
            $this->log[] = "📊 Domande dopo l'import: {$questionsafter}";
            $this->log[] = "✅ Nuove domande: {$imported}";
            
            // ========================================
            // ASSEGNAZIONE COMPETENZE E LIVELLI
            // ========================================
            
            if ($imported > 0) {
                $this->log[] = "=== ASSEGNAZIONE COMPETENZE ===";
                
                // Recupera le domande appena importate
                $newquestions = $this->get_questions_in_category($this->categoryid, $imported);
                
                foreach ($newquestions as $question) {
                    // ESTRAI IL CODICE DAL NOME DELLA DOMANDA
                    $code = $this->extract_competency_code($question->name);
                    
                    $this->log[] = "📝 Domanda: \"{$question->name}\"";
                    $this->log[] = "   Codice estratto: " . ($code ? $code : "NESSUNO");
                    
                    if ($code) {
                        // Trova la competenza nel framework
                        $competency = $this->find_competency($code);
                        
                        if ($competency) {
                            // Ottieni il livello dalla mappatura
                            $level = $this->get_level_for_code($code);
                            $levelname = $this->get_level_name($level);
                            
                            $this->log[] = "   Competenza trovata: {$competency->shortname} (ID: {$competency->id})";
                            $this->log[] = "   Livello: {$levelname}";
                            
                            try {
                                \qbank_competenciesbyquestion\local\manager::set_competency_for_question(
                                    $question->id,
                                    $competency->id,
                                    $level
                                );
                                $assigned++;
                                $this->log[] = "   ✅ ASSEGNATO: {$code} → {$levelname}";
                            } catch (\Exception $e) {
                                $this->log[] = "   ❌ Errore: " . $e->getMessage();
                                $errors++;
                            }
                        } else {
                            $this->log[] = "   ⚠️ Competenza NON trovata nel framework!";
                            $this->log[] = "   💡 Verifica che '{$code}' esista nel framework selezionato";
                            $errors++;
                        }
                    } else {
                        $this->log[] = "   ⚠️ Nessun codice competenza nel nome";
                        $errors++;
                    }
                    
                    $this->log[] = ""; // Riga vuota per separare
                }
            }
            
            $this->log[] = "=== RIEPILOGO ===";
            $this->log[] = "📊 Domande importate: {$imported}";
            $this->log[] = "📊 Competenze assegnate: {$assigned}";
            if ($errors > 0) {
                $this->log[] = "⚠️ Errori/Warning: {$errors}";
            }
            
        } catch (\Exception $e) {
            $this->log[] = "❌ ERRORE GRAVE: " . $e->getMessage();
            $errors++;
        }
        
        return [
            'imported' => $imported,
            'assigned' => $assigned,
            'errors' => $errors,
            'log' => $this->log
        ];
    }
}