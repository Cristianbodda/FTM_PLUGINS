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
 * AJAX handler per correzioni domande Word
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);

header('Content-Type: application/json; charset=utf-8');

/**
 * Restituisce il nome leggibile di un'area competenza
 */
function get_area_name($area_code) {
    $names = [
        // Autoveicolo
        'MR' => 'Manutenzione e Riparazione',
        'EM' => 'Elettromeccanica',
        'DG' => 'Diagnostica',
        'MT' => 'Motore e Trasmissione',
        'SC' => 'Sicurezza',
        'CA' => 'Carrozzeria',
        'EL' => 'Elettronica',
        // Meccanica
        'TO' => 'Tornitura',
        'FR' => 'Fresatura',
        'SA' => 'Saldatura',
        'CN' => 'CNC',
        'ME' => 'Metrologia',
        'DT' => 'Disegno Tecnico',
        // Chimica
        'AN' => 'Analisi',
        'LA' => 'Laboratorio',
        'PR' => 'Processi',
        'SI' => 'Sicurezza e Igiene',
        // Logistica
        'MA' => 'Magazzino',
        'TR' => 'Trasporti',
        'GE' => 'Gestione',
        // Generale
        'QU' => 'Qualità',
        'AM' => 'Ambiente',
        'OR' => 'Organizzazione',
        'CO' => 'Comunicazione',
    ];
    return $names[$area_code] ?? $area_code;
}

try {
    switch ($action) {
        
        case 'savecorrection':
            // Salva correzione di una domanda nella sessione
            $index = required_param('index', PARAM_INT);
            $competency = required_param('competency', PARAM_TEXT);
            $correct_answer = required_param('correct_answer', PARAM_ALPHA);
            
            // Recupera dati dalla sessione
            if (!isset($SESSION->word_import_questions)) {
                throw new Exception('Nessun import in corso');
            }
            
            // Aggiorna la domanda
            if (!isset($SESSION->word_import_questions[$index])) {
                throw new Exception('Domanda non trovata');
            }
            
            $SESSION->word_import_questions[$index]['competency'] = strtoupper(clean_param($competency, PARAM_TEXT));
            $SESSION->word_import_questions[$index]['correct_answer'] = strtoupper($correct_answer);
            $SESSION->word_import_questions[$index]['competency_guessed'] = false;
            $SESSION->word_import_questions[$index]['manually_corrected'] = true;
            
            // Rivalida
            $issues = [];
            $status = 'ok';
            $q = &$SESSION->word_import_questions[$index];
            
            // Verifica competenza nel framework
            if (!empty($SESSION->word_import_valid_competencies)) {
                if (in_array($q['competency'], $SESSION->word_import_valid_competencies)) {
                    $q['competency_valid'] = true;
                } else {
                    $issues[] = 'Competenza non nel framework';
                    $status = 'warning';
                    $q['competency_valid'] = false;
                }
            }
            
            // Verifica risposta corretta
            if (!isset($q['answers'][$q['correct_answer']])) {
                $issues[] = 'Risposta corretta non valida';
                $status = 'error';
            }
            
            $q['status'] = $status;
            $q['issues'] = $issues;
            
            echo json_encode([
                'success' => true,
                'question' => $q
            ]);
            break;
            
        case 'suggestcompetencies':
            // Suggerisci competenze simili
            $partial = required_param('partial', PARAM_TEXT);
            $sector = optional_param('sector', '', PARAM_TEXT);
            
            $suggestions = [];
            
            // Recupera competenze dal framework
            if (!empty($SESSION->word_import_valid_competencies)) {
                $partial_upper = strtoupper($partial);
                
                foreach ($SESSION->word_import_valid_competencies as $code) {
                    // Match parziale
                    if (strpos($code, $partial_upper) !== false) {
                        $suggestions[] = $code;
                    }
                    // Levenshtein per typo
                    elseif (strlen($partial) >= 5 && levenshtein($partial_upper, $code) <= 3) {
                        $suggestions[] = $code;
                    }
                    
                    if (count($suggestions) >= 10) break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions
            ]);
            break;
            
        case 'getquestiondetails':
            // Ottieni dettagli di una domanda
            $index = required_param('index', PARAM_INT);
            
            if (!isset($SESSION->word_import_questions[$index])) {
                throw new Exception('Domanda non trovata');
            }
            
            echo json_encode([
                'success' => true,
                'question' => $SESSION->word_import_questions[$index]
            ]);
            break;
            
        case 'getstatus':
            // Ottieni stato attuale dell'import
            if (!isset($SESSION->word_import_questions)) {
                throw new Exception('Nessun import in corso');
            }

            $valid = 0;
            $warning = 0;
            $error = 0;

            foreach ($SESSION->word_import_questions as $q) {
                switch ($q['status']) {
                    case 'ok': $valid++; break;
                    case 'warning': $warning++; break;
                    case 'error': $error++; break;
                }
            }

            echo json_encode([
                'success' => true,
                'total' => count($SESSION->word_import_questions),
                'valid' => $valid,
                'warning' => $warning,
                'error' => $error,
                'can_import' => ($valid + $warning) > 0
            ]);
            break;

        case 'getcompetencies':
            // Ottieni tutte le competenze del settore raggruppate per area/categoria
            $sector = $SESSION->word_import_sector ?? '';

            if (empty($SESSION->word_import_valid_competencies)) {
                throw new Exception('Nessuna competenza caricata');
            }

            $competencies = $SESSION->word_import_valid_competencies;
            $grouped = [];

            // Raggruppa per area (es. AUTOVEICOLO_MR -> MR, AUTOVEICOLO_EM -> EM)
            foreach ($competencies as $code) {
                // Pattern: SETTORE_AREA_NUMERO (es. AUTOVEICOLO_MR_A1)
                if (preg_match('/^([A-Z]+)_([A-Z]+)_(.+)$/i', $code, $matches)) {
                    $area = $matches[2]; // MR, EM, DG, etc.
                    if (!isset($grouped[$area])) {
                        $grouped[$area] = [
                            'name' => get_area_name($area),
                            'items' => []
                        ];
                    }
                    $grouped[$area]['items'][] = $code;
                } else {
                    // Se non matcha il pattern, metti in "Altro"
                    if (!isset($grouped['ALTRO'])) {
                        $grouped['ALTRO'] = ['name' => 'Altro', 'items' => []];
                    }
                    $grouped['ALTRO']['items'][] = $code;
                }
            }

            // Ordina le aree
            ksort($grouped);

            echo json_encode([
                'success' => true,
                'sector' => $sector,
                'total' => count($competencies),
                'grouped' => $grouped
            ]);
            break;

        case 'updatequestioncompetency':
            // Aggiorna la competenza di una singola domanda
            $index = required_param('index', PARAM_INT);
            $competency = required_param('competency', PARAM_TEXT);

            if (!isset($SESSION->word_import_questions[$index])) {
                throw new Exception('Domanda non trovata');
            }

            $competency = strtoupper(clean_param($competency, PARAM_TEXT));

            // Verifica che la competenza esista nel framework
            $is_valid = in_array($competency, $SESSION->word_import_valid_competencies ?? []);

            // Aggiorna la domanda
            $SESSION->word_import_questions[$index]['competency'] = $competency;
            $SESSION->word_import_questions[$index]['competency_valid'] = $is_valid;
            $SESSION->word_import_questions[$index]['manually_corrected'] = true;

            // Rivalida status
            $q = &$SESSION->word_import_questions[$index];
            $issues = [];
            $status = 'ok';

            if (!$is_valid) {
                $issues[] = 'Competenza non nel framework';
                $status = 'warning';
            }

            if (empty($q['correct_answer']) || !isset($q['answers'][$q['correct_answer']])) {
                $issues[] = 'Risposta corretta non definita';
                $status = 'error';
            }

            $q['status'] = $status;
            $q['issues'] = $issues;

            echo json_encode([
                'success' => true,
                'question' => $q,
                'is_valid' => $is_valid
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
