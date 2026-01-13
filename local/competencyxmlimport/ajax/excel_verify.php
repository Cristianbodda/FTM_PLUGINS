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
 * AJAX handler per verifica Excel
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/excel_reader.php');
require_once(__DIR__ . '/../classes/excel_verifier.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        
        case 'loadexcel':
            // Carica file Excel e restituisce lista fogli
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nessun file caricato o errore upload');
            }
            
            $tmp_file = $_FILES['excel_file']['tmp_name'];
            $filename = $_FILES['excel_file']['name'];
            
            // Verifica estensione
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
                throw new Exception('Il file deve essere in formato .xlsx');
            }
            
            // Salva temporaneamente
            $temp_path = sys_get_temp_dir() . '/excel_verify_' . sesskey() . '.xlsx';
            move_uploaded_file($tmp_file, $temp_path);
            
            // Salva percorso in sessione
            $SESSION->excel_verify_path = $temp_path;
            
            // Carica e ottieni fogli
            $reader = new \local_competencyxmlimport\excel_reader($temp_path);
            $sheets_info = $reader->get_sheets_info();
            
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'sheets' => $sheets_info
            ]);
            break;
            
        case 'selectsheet':
            // Seleziona foglio e restituisce colonne
            $sheet_index = required_param('sheet_index', PARAM_INT);
            
            if (!isset($SESSION->excel_verify_path) || !file_exists($SESSION->excel_verify_path)) {
                throw new Exception('Nessun file Excel caricato');
            }
            
            $reader = new \local_competencyxmlimport\excel_reader($SESSION->excel_verify_path);
            $sheet_names = $reader->get_sheet_names();
            
            if (!isset($sheet_names[$sheet_index])) {
                throw new Exception('Foglio non trovato');
            }
            
            // Salva foglio selezionato
            $SESSION->excel_verify_sheet = $sheet_index;
            
            // Auto-detect colonne
            $auto_detect = $reader->auto_detect_columns($sheet_index);
            $headers = $reader->get_headers($sheet_index);
            
            // Preview prime righe
            $data = $reader->get_sheet_data($sheet_index);
            $preview = array_slice($data, 0, 5);
            
            echo json_encode([
                'success' => true,
                'sheet_name' => $sheet_names[$sheet_index],
                'headers' => $headers,
                'auto_detect' => [
                    'question_col' => $auto_detect['question_col'],
                    'competency_col' => $auto_detect['competency_col'],
                    'answer_col' => $auto_detect['answer_col']
                ],
                'preview' => $preview
            ]);
            break;
            
        case 'verify':
            // Esegue la verifica
            $col_question = required_param('col_question', PARAM_INT);
            $col_competency = required_param('col_competency', PARAM_INT);
            $col_answer = optional_param('col_answer', -1, PARAM_INT);

            if (!isset($SESSION->excel_verify_path) || !file_exists($SESSION->excel_verify_path)) {
                throw new Exception('Nessun file Excel caricato');
            }

            if (!isset($SESSION->word_import_questions)) {
                throw new Exception('Nessun file Word caricato');
            }

            $sheet_index = $SESSION->excel_verify_sheet ?? 0;

            // Crea verifier
            $verifier = new \local_competencyxmlimport\excel_verifier();
            $verifier->load_excel($SESSION->excel_verify_path);
            $verifier->select_sheet($sheet_index);
            $verifier->set_column_mapping($col_question, $col_competency, $col_answer >= 0 ? $col_answer : null);

            // Esegui verifica
            $results = $verifier->verify($SESSION->word_import_questions);

            // ========== VERIFICA COMPETENZE ESISTENTI NEL DATABASE ==========
            $valid_competencies = $SESSION->word_import_valid_competencies ?? [];
            $results['competency_check'] = [
                'total_valid' => count($valid_competencies),
                'missing_competencies' => [],
                'valid_in_excel' => 0,
                'invalid_in_excel' => 0
            ];

            // Funzione per normalizzare codici competenza (alias → standard, case-insensitive)
            $normalize_code = function($code) {
                // Prima converti in maiuscolo
                $code = strtoupper(trim($code));

                $aliases = [
                    // Automobile (NON usare AUTO - ambiguo con AUTOMAZIONE)
                    'AUTOVEICOLO' => 'AUTOMOBILE',
                    // Automazione
                    'AUTOM' => 'AUTOMAZIONE',
                    'AUTOMAZ' => 'AUTOMAZIONE',
                    // Meccanica
                    'MECC' => 'MECCANICA',
                    'METAL' => 'MECCANICA',
                    'METALCOSTRUZIONE' => 'MECCANICA',
                    // Chimica/Farmaceutica
                    'CHIM' => 'CHIMFARM',
                    'CHIMICA' => 'CHIMFARM',
                    'FARMACEUTICA' => 'CHIMFARM',
                    // Altri
                    'LOG' => 'LOGISTICA',
                    'ELETTRO' => 'ELETTRONICA',
                    'INFO' => 'INFORMATICA',
                    'IT' => 'INFORMATICA',
                ];
                foreach ($aliases as $alias => $standard) {
                    if (strpos($code, $alias . '_') === 0) {
                        return $standard . substr($code, strlen($alias));
                    }
                }
                return $code;
            };

            // Crea array di competenze valide in MAIUSCOLO per confronto case-insensitive
            $valid_competencies_upper = array_map('strtoupper', $valid_competencies);

            // Ottieni tutte le competenze uniche trovate nelle domande
            $found_competencies = [];
            foreach ($SESSION->word_import_questions as $q) {
                if (!empty($q['competency'])) {
                    $comp = strtoupper(trim($q['competency']));
                    $normalized = $normalize_code($comp);

                    if (!isset($found_competencies[$comp])) {
                        $found_competencies[$comp] = [
                            'count' => 0,
                            'exists' => false,
                            'normalized' => $normalized
                        ];
                    }
                    $found_competencies[$comp]['count']++;
                    // Verifica case-insensitive sia codice originale che normalizzato
                    $found_competencies[$comp]['exists'] = in_array($comp, $valid_competencies_upper)
                        || in_array($normalized, $valid_competencies_upper);
                }
            }

            // Conta e registra competenze mancanti
            foreach ($found_competencies as $comp => $info) {
                if ($info['exists']) {
                    $results['competency_check']['valid_in_excel']++;
                } else {
                    $results['competency_check']['invalid_in_excel']++;
                    $results['competency_check']['missing_competencies'][] = [
                        'code' => $comp,
                        'normalized' => $info['normalized'],
                        'count' => $info['count']
                    ];
                }
            }

            $results['competency_check']['all_valid'] = empty($results['competency_check']['missing_competencies']);
            // ========== FINE VERIFICA COMPETENZE ==========

            // Salva risultati in sessione
            $SESSION->excel_verify_results = $results;
            $SESSION->excel_verify_discrepancies = [];
            foreach ($results['discrepancies'] as $d) {
                $key = $d['index'] . '_' . $d['type'];
                $SESSION->excel_verify_discrepancies[$key] = [
                    'resolved' => false,
                    'choice' => null,
                    'excel_value' => $d['excel_value']
                ];
            }

            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            break;
            
        case 'useexcelvalue':
            // Usa il valore dall'Excel
            $index = required_param('index', PARAM_INT);
            $type = required_param('type', PARAM_ALPHA);
            $value = required_param('value', PARAM_TEXT);
            
            if (!isset($SESSION->word_import_questions[$index])) {
                throw new Exception('Domanda non trovata');
            }
            
            // Aggiorna il valore nel Word
            if ($type === 'competency') {
                $SESSION->word_import_questions[$index]['competency'] = strtoupper($value);
            } elseif ($type === 'answer') {
                $SESSION->word_import_questions[$index]['correct_answer'] = strtoupper($value);
            }
            
            // Segna come risolto
            $key = $index . '_' . $type;
            if (isset($SESSION->excel_verify_discrepancies[$key])) {
                $SESSION->excel_verify_discrepancies[$key]['resolved'] = true;
                $SESSION->excel_verify_discrepancies[$key]['choice'] = 'excel';
            }
            
            echo json_encode([
                'success' => true,
                'updated_question' => $SESSION->word_import_questions[$index]
            ]);
            break;
            
        case 'keepwordvalue':
            // Mantieni il valore dal Word
            $index = required_param('index', PARAM_INT);
            $type = required_param('type', PARAM_ALPHA);
            
            // Segna come risolto
            $key = $index . '_' . $type;
            if (isset($SESSION->excel_verify_discrepancies[$key])) {
                $SESSION->excel_verify_discrepancies[$key]['resolved'] = true;
                $SESSION->excel_verify_discrepancies[$key]['choice'] = 'word';
            }
            
            echo json_encode([
                'success' => true
            ]);
            break;
            
        case 'checkresolved':
            // Verifica se tutte le discrepanze sono risolte
            $all_resolved = true;
            
            if (isset($SESSION->excel_verify_discrepancies)) {
                foreach ($SESSION->excel_verify_discrepancies as $d) {
                    if (!$d['resolved']) {
                        $all_resolved = false;
                        break;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'all_resolved' => $all_resolved
            ]);
            break;
            
        default:
            throw new Exception('Azione non riconosciuta');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
