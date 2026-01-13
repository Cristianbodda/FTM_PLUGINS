<?php
/**
 * Manager class - Competency Manager
 * Gestisce competenze, domande e importazioni
 * @package    local_competencymanager
 */

namespace local_competencymanager;

defined('MOODLE_INTERNAL') || die();

class manager {
    
    /**
     * Ottiene tutte le competenze di un framework
     * @param int $frameworkid ID del framework
     * @return array Array di competenze
     */
    public static function get_framework_competencies($frameworkid) {
        global $DB;
        
        if (!$frameworkid) {
            return [];
        }
        
        $competencies = $DB->get_records('competency', [
            'competencyframeworkid' => $frameworkid
        ], 'idnumber ASC');
        
        return $competencies;
    }
    
    /**
     * Assegna una competenza a una domanda
     * @param int $questionid ID domanda
     * @param int $competencyid ID competenza
     * @param int $difficultylevel Livello difficoltà (1-3)
     * @return bool|int ID record o false
     */
    public static function assign_competency($questionid, $competencyid, $difficultylevel = 1) {
        global $DB;
        
        // Verifica se esiste già
        $existing = $DB->get_record('qbank_competenciesbyquestion', [
            'questionid' => $questionid,
            'competencyid' => $competencyid
        ]);
        
        if ($existing) {
            // Aggiorna livello se diverso
            if ($existing->difficultylevel != $difficultylevel) {
                $existing->difficultylevel = $difficultylevel;
                $DB->update_record('qbank_competenciesbyquestion', $existing);
            }
            return $existing->id;
        }
        
        // Crea nuovo record
        $record = new \stdClass();
        $record->questionid = $questionid;
        $record->competencyid = $competencyid;
        $record->difficultylevel = $difficultylevel;
        
        return $DB->insert_record('qbank_competenciesbyquestion', $record);
    }
    
    /**
     * Log importazione
     * @param int $courseid
     * @param string $filename
     * @param int $imported
     * @param int $failed
     * @param int $competencies
     * @param array $errors
     * @return int|bool
     */
    public static function log_import($courseid, $filename, $imported, $failed, $competencies, $errors = []) {
        global $DB, $USER;
        
        // Verifica se la tabella esiste
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_competencymanager_log')) {
            // Tabella non esiste, skip log
            return true;
        }
        
        try {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->filename = $filename;
            $record->questionsimported = $imported;
            $record->questionsfailed = $failed;
            $record->competenciesassigned = $competencies;
            $record->errors = json_encode($errors);
            $record->timecreated = time();
            
            return $DB->insert_record('local_competencymanager_log', $record);
        } catch (\Exception $e) {
            // Ignora errori di log
            return true;
        }
    }
    
    /**
     * Ottiene le domande del corso con info competenze
     * @param int $contextid Context ID del corso
     * @param int $frameworkid Framework ID (opzionale)
     * @return array
     */
    public static function get_course_questions($contextid, $frameworkid = 0) {
        global $DB;
        
        $sql = "SELECT DISTINCT 
                    q.id,
                    q.name,
                    q.qtype,
                    q.createdby,
                    q.timecreated,
                    qc.name as category_name,
                    qcbq.competencyid,
                    qcbq.difficultylevel,
                    comp.idnumber as competency_code,
                    comp.shortname as competency_name
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                LEFT JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = q.id
                LEFT JOIN {competency} comp ON comp.id = qcbq.competencyid
                WHERE qc.contextid = :contextid
                AND q.parent = 0
                ORDER BY q.name ASC";
        
        $questions = $DB->get_records_sql($sql, ['contextid' => $contextid]);
        
        // Aggiungi competency_status a ogni domanda
        foreach ($questions as $q) {
            // Estrai codice competenza dal nome
            $extractedCode = self::extract_competency_code($q->name);
            
            if (!empty($q->competency_code)) {
                // Ha già una competenza assegnata
                $q->competency_status = 'found';
            } else if ($extractedCode && $frameworkid > 0) {
                // Cerca se esiste nel framework
                $comp = $DB->get_record('competency', [
                    'idnumber' => $extractedCode,
                    'competencyframeworkid' => $frameworkid
                ]);
                if ($comp) {
                    $q->competency_status = 'found';
                    $q->competency_code = $extractedCode;
                } else {
                    $q->competency_status = 'not_found';
                    $q->competency_code = $extractedCode;
                }
            } else if ($extractedCode) {
                // Ha un codice ma nessun framework selezionato
                $q->competency_status = 'not_found';
                $q->competency_code = $extractedCode;
            } else {
                // Nessun codice trovato
                $q->competency_status = 'no_code';
                $q->competency_code = null;
            }
        }
        
        // Se richiesto, filtra per framework
        if ($frameworkid > 0) {
            $filtered = [];
            foreach ($questions as $q) {
                // Includi se non ha competenza O se la competenza è del framework richiesto
                if (empty($q->competencyid)) {
                    $filtered[$q->id] = $q;
                } else {
                    $comp = $DB->get_record('competency', ['id' => $q->competencyid]);
                    if ($comp && $comp->competencyframeworkid == $frameworkid) {
                        $filtered[$q->id] = $q;
                    }
                }
            }
            return $filtered;
        }
        
        return $questions;
    }
    
    /**
     * Estrae codice competenza dal nome domanda
     * Pattern: cerca SETTORE_AREA_NUMERO (es. MECCANICA_DT_01)
     * @param string $name Nome domanda
     * @return string|null Codice trovato o null
     */
    public static function extract_competency_code($name) {
        // Pattern per codici tipo MECCANICA_ASS_05, AUTOMOBILE_MR_A1, etc.
        if (preg_match('/([A-Z]+_[A-Z]+_[A-Z0-9]+)/i', $name, $matches)) {
            return strtoupper($matches[1]);
        }
        
        // Pattern alternativo: solo AREA_NUMERO
        if (preg_match('/([A-Z]{2,}_\d+)/i', $name, $matches)) {
            return strtoupper($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Trova competenza per codice in un framework
     * @param string $code Codice competenza
     * @param int $frameworkid ID framework
     * @return object|null Competenza o null
     */
    public static function find_competency_by_code($code, $frameworkid) {
        global $DB;
        
        return $DB->get_record('competency', [
            'idnumber' => $code,
            'competencyframeworkid' => $frameworkid
        ]);
    }
}
